<?php
/**
 * core/WaSend.php — the tenant side of WhatsApp. Shared by
 *   api/v1/wa-reminder.php   (dues reminder, one send)
 *   api/v1/whatsapp-manage.php (tenant management page data + test sends)
 *
 * The WhatsApp LINE itself (token, phone_id, templates, on/off switch) lives in
 * the portal control plane: master DB `billing_settings` wa_* keys and `wa_log`,
 * managed on the portal's WhatsApp page. Every tenant already reaches master for
 * billing (see subscription.php), so there is exactly ONE switch for the whole
 * platform: flip WhatsApp off portal-side and tenant sends stop too.
 *
 * This file mirrors the portal's waSend() contract (alert template params
 * {1}store {2}message {3}date, session-text fallback, same wa_log columns,
 * degrade-never-crash) without reaching across app directories.
 *
 * Requires: Database::master() (middleware/tenant.php), PDO.
 */
class WaSend
{
    const KIND_REMINDER = 'due_reminder';
    const KIND_TEST = 'wa_test';
    const REMINDERS_PER_NUM_PER_DAY = 3;
    const TESTS_PER_NUM_PER_DAY = 5;

    /**
     * Resolved send config with a three-tier precedence:
     *   tenant wa_mode='own'       → the store's own Meta number (fields stored in the
     *                                TENANT settings table under the same wa_* keys)
     *   tenant wa_mode='off'       → tenant-originated sends paused (platform sends,
     *                                e.g. billing receipts, are untouched)
     *   otherwise (default)        → the platform line from master billing_settings
     * $t null → platform-only (portal use). 'source' tells the UI which line is live.
     */
    public static function cfg(PDO $m, ?PDO $t = null): array
    {
        $out = ['enabled' => '', 'token' => '', 'phone_id' => '', 'ver' => 'v21.0', 'lang' => 'en', 'cc' => '91',
                'tpl_onboard' => '', 'tpl_paid' => '', 'tpl_alert' => ''];
        try {
            foreach ($m->query("SELECT `key`, `value` FROM billing_settings WHERE `key` LIKE 'wa_%'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $k = substr((string) $r['key'], 3);
                if (array_key_exists($k, $out)) { $out[$k] = (string) $r['value']; }
            }
        } catch (Throwable $e) { /* control plane unreadable → treated as off below */ }
        $platformOn = ($out['enabled'] === '1' && $out['token'] !== '' && $out['phone_id'] !== '');

        $mode = 'platform';
        $ov = [];
        if ($t !== null) {
            try {
                foreach ($t->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'wa_%'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $ov[substr((string) $r['setting_key'], 3)] = trim((string) $r['setting_value']);
                }
            } catch (Throwable $e) { /* no rows yet = plain platform mode */ }
            $m0 = (string) ($ov['mode'] ?? '');
            if ($m0 === 'own' || $m0 === 'off' || $m0 === 'platform') { $mode = $m0; }
        }
        $out['mode'] = $mode;
        $out['own'] = ['phone_id' => (string) ($ov['phone_id'] ?? ''), 'ver' => (string) ($ov['ver'] ?? ''),
                       'lang' => (string) ($ov['lang'] ?? ''), 'cc' => (string) ($ov['cc'] ?? ''),
                       'tpl_alert' => (string) ($ov['tpl_alert'] ?? ''), 'has_token' => (string) ($ov['token'] ?? '') !== ''];

        if ($mode === 'own') {
            foreach (['token', 'phone_id', 'ver', 'lang', 'cc', 'tpl_alert'] as $k) {
                if (($ov[$k] ?? '') !== '') { $out[$k] = (string) $ov[$k]; }
            }
            $out['source'] = 'own';
            $out['on'] = ($out['token'] !== '' && $out['phone_id'] !== '');
        } elseif ($mode === 'off') {
            $out['source'] = 'off';
            $out['on'] = false;
        } else {
            $out['source'] = 'platform';
            $out['on'] = $platformOn;
        }
        return $out;
    }

    /** Portal client id for this tenant, same host logic as subscription.php. Null when master/APP_DOMAIN absent. */
    public static function clientId(PDO $m): ?int
    {
        try {
            $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
            $host = preg_replace('/:\d+$/', '', $host);
            $base = strtolower((string) (getenv('APP_DOMAIN') ?: ''));
            if ($base === '' || $host === $base || substr($host, -strlen('.' . $base)) !== '.' . $base) { return null; }
            $sub = trim(substr($host, 0, -strlen('.' . $base)));
            if ($sub === '' || strpos($sub, '.') !== false) { return null; }
            $s = $m->prepare('SELECT id FROM clients WHERE subdomain = ' . $m->quote($sub) . ' LIMIT 1');
            $s->execute();
            $id = $s->fetchColumn();
            return ($id === false) ? null : (int) $id;
        } catch (Throwable $e) { return null; }
    }

    /** 9876543210 / +91 9876543210 / 09876543210 → 919876543210. Idempotent. */
    public static function norm(string $raw, string $cc): string
    {
        $d = preg_replace('/\D+/', '', $raw) ?? '';
        if ($d === '') { return ''; }
        if ($cc === '') { $cc = '91'; }
        if (strlen($d) === 10) { return $cc . $d; }
        if (strlen($d) === 11 && $d[0] === '0') { return $cc . substr($d, 1); }
        if (strlen($d) === strlen($cc) + 10 && strpos($d, $cc) === 0) { return $d; }
        if (strlen($d) === strlen($cc) + 11 && $d[strlen($cc)] === '0') { return $cc . substr($d, strlen($cc) + 1); }
        return $d;
    }

    /** ₹1,23,456.78 — Indian grouping without iconv games. */
    public static function inr(float $n): string
    {
        $neg = $n < 0.0;
        $n = abs($n);
        $int = (string) (int) floor($n);
        $dec = substr(number_format($n, 2, '.', ''), -3);
        if (strlen($int) > 3) {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($int, 0, -3));
            $int = $rest . ',' . substr($int, -3);
        }
        return ($neg ? '-' : '') . "\xe2\x82\xb9" . $int . $dec;
    }

    public static function mask(string $num): string
    {
        $len = strlen($num);
        if ($len <= 4) { return str_repeat('•', max(0, $len - 2)) . substr($num, -2); }
        return str_repeat('•', $len - 4) . substr($num, -4);
    }

    /** wa_log must exist before counting or logging. Cheap and idempotent. */
    public static function ensure(PDO $m): void
    {
        try {
            $m->query('CREATE TABLE IF NOT EXISTS wa_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                client_id INT NULL,
                to_number VARCHAR(20) NOT NULL,
                kind VARCHAR(24) NOT NULL,
                via VARCHAR(16) NOT NULL DEFAULT "template",
                ok TINYINT(1) NOT NULL DEFAULT 0,
                api_id VARCHAR(80) NULL,
                error VARCHAR(255) NULL,
                payload TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY wl_client (client_id), KEY wl_kind (kind)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        } catch (Throwable $e) { /* portal provisions it too; counting degrades */ }
    }

    public static function todayCount(PDO $m, string $num, string $kind): int
    {
        try {
            self::ensure($m);
            $s = $m->prepare('SELECT COUNT(*) FROM wa_log WHERE kind = ? AND to_number = ? AND created_at >= CURDATE()');
            $s->execute([$kind, $num]);
            return (int) $s->fetchColumn();
        } catch (Throwable $e) { return 0; }
    }

    /** curl when present, streams always work. Same ladder as the portal. */
    public static function post(string $url, array $payload, string $token): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}", 'Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $body, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = (string) curl_error($ch);
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => ['method' => 'POST',
                'header' => "Authorization: Bearer {$token}\r\nContent-Type: application/json\r\n",
                'content' => $body, 'timeout' => 15, 'ignore_errors' => true]]);
            $raw = @file_get_contents($url, false, $ctx);
            $code = 0;
            foreach ((array) ($http_response_header ?? []) as $h) {
                if (preg_match('#^HTTP/\S+ (\d{3})#', $h, $m2)) { $code = (int) $m2[1]; break; }
            }
            $err = $raw === false ? 'transport failed' : '';
        }
        $json = is_string($raw) ? json_decode($raw, true) : null;
        if ($code >= 200 && $code < 300 && is_array($json)) {
            return ['ok' => true, 'api_id' => (string) ($json['messages'][0]['id'] ?? ''), 'payload' => $payload];
        }
        $why = $err !== '' ? $err : (is_array($json) ? (string) ($json['error']['message'] ?? json_encode($json)) : substr((string) $raw, 0, 160));
        return ['ok' => false, 'error' => substr($why . ' (HTTP ' . $code . ')', 0, 240), 'payload' => $payload];
    }

    /**
     * One entry point for tenant-originated sends.
     * $kind is the wa_log row kind; template comes from cfg['tpl_alert'] when set,
     * free text otherwise. Never throws — inspect ['ok'] / ['error'].
     */
    public static function send(PDO $m, array $cfg, ?int $clientId, string $to, string $kind, array $tplParams, string $text): array
    {
        if (!$cfg['on']) {
            return ['ok' => false, 'skipped' => true, 'error' => 'WhatsApp is not configured (or is switched off) on the platform.'];
        }
        $num = self::norm($to, $cfg['cc']);
        if ($num === '') {
            return ['ok' => false, 'error' => 'No valid WhatsApp number on file for this customer.'];
        }
        $tpl = trim((string) ($cfg['tpl_alert'] ?? ''));
        if ($tpl !== '') {
            $params = [];
            foreach (array_slice($tplParams, 0, 10) as $pv) {
                $params[] = ['type' => 'text', 'text' => mb_substr((string) $pv, 0, 300)];
            }
            $payload = ['messaging_product' => 'whatsapp', 'to' => $num, 'type' => 'template',
                        'template' => ['name' => $tpl, 'language' => ['code' => $cfg['lang'] !== '' ? $cfg['lang'] : 'en']]];
            if ($params) { $payload['template']['components'] = [['type' => 'body', 'parameters' => $params]]; }
            $via = 'template';
        } elseif (trim($text) !== '') {
            $payload = ['messaging_product' => 'whatsapp', 'to' => $num, 'type' => 'text', 'text' => ['body' => mb_substr(trim($text), 0, 1000)]];
            $via = 'session';
        } else {
            return ['ok' => false, 'error' => 'No alert template configured and no free text given.'];
        }
        $res = self::post("https://graph.facebook.com/{$cfg['ver']}/{$cfg['phone_id']}/messages", $payload, $cfg['token']);
        $res['via'] = $via;
        $res['to'] = $num;
        try {
            self::ensure($m);
            $m->query('INSERT INTO wa_log (client_id, to_number, kind, via, ok, api_id, error, payload) VALUES ('
                . ($clientId !== null ? (int) $clientId : 'NULL') . ', ' . $m->quote($num) . ', '
                . $m->quote($kind) . ', ' . $m->quote($via) . ', ' . ($res['ok'] ? 1 : 0) . ', '
                . (!empty($res['api_id']) ? $m->quote($res['api_id']) : 'NULL') . ', '
                . (!empty($res['error']) ? $m->quote(substr($res['error'], 0, 240)) : 'NULL') . ', '
                . $m->quote(mb_substr(json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '', 0, 1200)) . ')');
        } catch (Throwable $e) { /* logging must not sink the send */ }
        return $res;
    }
}
