<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

/**
 * WhatsApp due-reminder — one send, one customer, one open balance.
 *
 * The Meta Cloud API engine lives portal-side (super-admin/actions/_lib.php
 * waSend), but every tenant page already reaches the control plane through
 * Database::master() — see subscription.php. This endpoint reads the same
 * wa_* config, speaks the same Graph REST call, and logs into the same wa_log
 * table, so the portal's WhatsApp page stays the single switchboard: flip
 * WhatsApp off there and reminders stop here too.
 *
 * Safety by construction:
 *  - the number is read from the customers row (whatsapp column when present,
 *    phone otherwise) — the browser never supplies a number;
 *  - dues come from the ledger (bill balances minus receipts, the same math
 *    the dues pages show), so nobody can remind a settled account;
 *  - max 3 reminders per number per day, counted in wa_log.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Json::error('Method not allowed.', 405);
}

function wrInr(float $n): string
{
    $neg = $n < 0.0;
    $n = abs($n);
    $int = (string) (int) floor($n);
    $dec = substr(number_format($n, 2, '.', ''), -3);
    if (strlen($int) > 3) {
        $last3 = substr($int, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($int, 0, -3));
        $int = $rest . ',' . $last3;
    }
    return ($neg ? '-' : '') . "\xe2\x82\xb9" . $int . $dec;
}

function wrNorm(string $raw, string $cc): string
{
    $d = preg_replace('/\D+/', '', $raw) ?? '';
    if ($d === '') {
        return '';
    }
    if (strlen($d) === 10) {
        return $cc . $d;
    }
    if (strlen($d) === 11 && $d[0] === '0') {
        return $cc . substr($d, 1);
    }
    if ($cc !== '' && strlen($d) === strlen($cc) + 10 && strpos($d, $cc) === 0) {
        return $d;
    }
    if ($cc !== '' && strlen($d) === strlen($cc) + 11 && $d[strlen($cc)] === '0') {
        return $cc . substr($d, strlen($cc) + 1);
    }
    return $d;
}

/** Same transport ladder as the portal: curl when present, streams always work. */
function wrPost(string $url, array $payload, string $token): array
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
            if (preg_match('#^HTTP/\S+ (\d{3})#', $h, $m)) { $code = (int) $m[1]; break; }
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

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
$customerId = (int) ($input['customerId'] ?? 0);
if ($customerId <= 0) {
    Json::error('Missing customer.', 422);
}

$pdo = Tenant::db();

try {
    $stmt = $pdo->prepare('SELECT id, name, phone FROM customers WHERE id = ?');
    $stmt->execute([$customerId]);
    $cust = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    Json::error('Customer lookup failed: ' . $e->getMessage(), 500);
}
if (!$cust) {
    Json::error('Customer not found.', 404);
}

/* Dues exactly as the ledger sees them: open bill balances minus all receipts. */
try {
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(balance_due),0) AS bal,
                                  SUM(CASE WHEN balance_due > 0 THEN 1 ELSE 0 END) AS open_bills,
                                  MIN(CASE WHEN balance_due > 0 THEN sale_date END) AS oldest_open
                           FROM sales WHERE customer_id = ?');
    $stmt->execute([$customerId]);
    $b = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['bal' => 0, 'open_bills' => 0, 'oldest_open' => null];
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS pays FROM payments WHERE party_type = 'customer' AND party_id = ?");
    $stmt->execute([$customerId]);
    $paysRow = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    Json::error('Could not read the dues ledger: ' . $e->getMessage(), 500);
}
$outstanding = round(max(0.0, (float) $b['bal'] - (float) ($paysRow['pays'] ?? 0)), 2);
if ($outstanding <= 0.009) {
    Json::error('This account is settled — nothing to remind.', 422);
}

/* Number: dedicated whatsapp column wins when the store has filled it in. */
$to = (string) ($cust['phone'] ?? '');
try {
    if ($pdo->query("SHOW COLUMNS FROM customers LIKE 'whatsapp'")->fetch()) {
        $stmt = $pdo->prepare('SELECT whatsapp FROM customers WHERE id = ?');
        $stmt->execute([$customerId]);
        $w = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($w !== '') {
            $to = $w;
        }
    }
} catch (Throwable $e) { /* no column, no problem — phone is enough */ }

try {
    $m = Database::master();
} catch (Throwable $e) {
    Json::error('Control plane unreachable — WhatsApp is managed centrally.', 503);
}

$cfg = ['enabled' => '', 'token' => '', 'phone_id' => '', 'ver' => 'v21.0', 'lang' => 'en', 'cc' => '91', 'tpl_alert' => ''];
try {
    foreach ($m->query("SELECT `key`, `value` FROM billing_settings WHERE `key` LIKE 'wa_%'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $k = substr((string) $r['key'], 3);
        if (array_key_exists($k, $cfg)) {
            $cfg[$k] = (string) $r['value'];
        }
    }
} catch (Throwable $e) {
    Json::error('WhatsApp settings not readable from the control plane.', 503);
}
if ($cfg['enabled'] !== '1' || $cfg['token'] === '' || $cfg['phone_id'] === '') {
    Json::error('WhatsApp is not configured (or is switched off) on the platform.', 503);
}

$num = wrNorm($to, $cfg['cc'] !== '' ? $cfg['cc'] : '91');
if ($num === '') {
    Json::error('This customer has no phone number to remind.', 422);
}

/* Log table first — it is also the anti-spam ledger. */
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
} catch (Throwable $e) { /* portal provisions it too; live without it if denied */ }
$todayCount = -1;
try {
    $stmt = $m->prepare("SELECT COUNT(*) FROM wa_log WHERE kind = 'due_reminder' AND to_number = ? AND created_at >= CURDATE()");
    $stmt->execute([$num]);
    $todayCount = (int) $stmt->fetchColumn();
} catch (Throwable $e) { /* no log table → the cap is skipped, the send still guards itself */ }
if ($todayCount >= 3) {
    Json::error('Reminder limit reached for this number today (max 3 per day).', 429);
}

$store = '';
try {
    $store = trim((string) ($pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'store_name'")->fetchColumn() ?: ''));
} catch (Throwable $e) { }
if ($store === '') {
    $h = preg_replace('/:\d+$/', '', strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? ''))));
    $store = $h !== '' ? explode('.', $h)[0] : 'the store';
}

$oldestTxt = '';
if (!empty($b['oldest_open'])) {
    $ts = strtotime((string) $b['oldest_open']);
    if ($ts) {
        $oldestTxt = date('d/m/Y', $ts);
    }
}
$today = date('d/m/Y');
$msg = 'You have unpaid dues of ' . wrInr($outstanding) . ' across ' . max(1, (int) $b['open_bills']) . ' bill(s)'
     . ($oldestTxt !== '' ? ' (oldest dated ' . $oldestTxt . ')' : '')
     . '. Kindly settle at your earliest — we would really appreciate it.';

$url = 'https://graph.facebook.com/' . $cfg['ver'] . '/' . $cfg['phone_id'] . '/messages';
$tpl = trim($cfg['tpl_alert']);
if ($tpl !== '') {
    /* Alert template contract (portal WhatsApp page): {1}store {2}message {3}date */
    $params = [];
    foreach ([$store, mb_substr($msg, 0, 300), $today] as $pv) {
        $params[] = ['type' => 'text', 'text' => (string) $pv];
    }
    $payload = ['messaging_product' => 'whatsapp', 'to' => $num, 'type' => 'template',
                'template' => ['name' => $tpl, 'language' => ['code' => $cfg['lang'] !== '' ? $cfg['lang'] : 'en'],
                               'components' => [['type' => 'body', 'parameters' => $params]]]];
    $via = 'template';
} else {
    $first = trim((string) preg_replace('/\s+/', ' ', (string) $cust['name']));
    $body = 'Hi ' . $first . '! This is ' . $store . '. ' . $msg;
    $payload = ['messaging_product' => 'whatsapp', 'to' => $num, 'type' => 'text', 'text' => ['body' => mb_substr($body, 0, 1000)]];
    $via = 'session';
}

$res = wrPost($url, $payload, $cfg['token']);
try {
    $m->query('INSERT INTO wa_log (client_id, to_number, kind, via, ok, api_id, error, payload) VALUES ('
        . 'NULL, ' . $m->quote($num) . ", 'due_reminder', " . $m->quote($via) . ', '
        . ($res['ok'] ? 1 : 0) . ', '
        . (!empty($res['api_id']) ? $m->quote($res['api_id']) : 'NULL') . ', '
        . (!empty($res['error']) ? $m->quote(substr($res['error'], 0, 240)) : 'NULL') . ', '
        . $m->quote(mb_substr(json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '', 0, 1200)) . ')');
} catch (Throwable $e) { /* logging must not sink the send */ }

if (!$res['ok']) {
    Json::error('Meta rejected the send: ' . (isset($res['error']) ? $res['error'] : 'unknown error'), 502);
}

Json::ok(['data' => [
    'sent' => true,
    'to' => '+' . substr($num, 0, strlen($num) - 5) . str_repeat('•', max(0, strlen($num) - 5)) . substr($num, -2),
    'amount' => $outstanding,
    'via' => $via,
    'receipt' => isset($res['api_id']) ? $res['api_id'] : '',
]]);
