<?php
/**
 * core/OfflineEngine.php — shared server logic for Phase C offline billing.
 * Used by api/v1/offline.php (policy/ticket/admin) and api/v1/offline-sync.php
 * (batch apply). All state lives in the TENANT database:
 *
 *   offline_devices  device_id PK, user_id, role, salt, pin_hash, ticket_v,
 *                    revoked_at NULL, last_seen, created_at
 *   offline_journal  client_key PK, device_id, status(applied|held|voided|starting),
 *                    invoice_no, hold_reason, payload, applied_at  ← dedupe + hold queue
 *
 * Policy lives in `settings` (setting_key/setting_value):
 *   offline_enabled(1/0) offline_max_bill(₹) offline_credit_ok(1/0)
 *   offline_snap_max_h(72) offline_price_tol(2, %) offline_secret (auto)
 *
 * Apply = validate + REPLAY the journalled payload against the store's own
 * /api/v1/sales.php over an internal same-origin call with the synced session
 * cookie. Deliberate: one sales-writing path, zero schema duplication, and the
 * official invoice number comes from the real sequence. 'starting' rows that
 * crash mid-replay flip to held(needs_review) — a double-post can never happen
 * silently, worst case a human clears a hold.
 */

function oeSettings(PDO $pdo): array
{
    $out = ['offline_enabled' => '0', 'offline_max_bill' => '5000', 'offline_credit_ok' => '1',
            'offline_snap_max_h' => '72', 'offline_price_tol' => '2'];
    try {
        $rows = $pdo->query('SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE \'offline_%\'')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) { $out[$r['setting_key']] = (string) $r['setting_value']; }
    } catch (Throwable $e) { }
    return $out;
}

function oeSecret(PDO $pdo): string
{
    try {
        $s = (string) ($pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'offline_secret'")->fetchColumn() ?: '');
    } catch (Throwable $e) { $s = ''; }
    if ($s === '') {
        $s = bin2hex(random_bytes(24));
        try {
            $st = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('offline_secret', :v) ON DUPLICATE KEY UPDATE setting_value = setting_value");
            $st->execute(['v' => $s]);
        } catch (Throwable $e) { }
    }
    return $s;
}

function oeTables(PDO $pdo): void
{
    static $done = false;
    if ($done) { return; }
    $done = true;
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS offline_devices (
            device_id VARCHAR(32) NOT NULL PRIMARY KEY,
            user_id INT NULL,
            role VARCHAR(24) NOT NULL DEFAULT "owner",
            salt CHAR(32) NOT NULL,
            pin_hash CHAR(64) NOT NULL,
            ticket_v INT NOT NULL DEFAULT 1,
            revoked_at DATETIME NULL,
            last_seen DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    } catch (Throwable $e) { }
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS offline_journal (
            client_key VARCHAR(64) NOT NULL PRIMARY KEY,
            device_id VARCHAR(32) NOT NULL,
            user_id INT NULL,
            status VARCHAR(12) NOT NULL DEFAULT "starting",
            invoice_no VARCHAR(32) NULL,
            hold_reason VARCHAR(190) NULL,
            payload MEDIUMTEXT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY oj_status (status), KEY oj_device (device_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    } catch (Throwable $e) { }
}

function oeB64(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }
function oeUnb64(string $b64): string { $r = base64_decode(strtr($b64, '-_', '+/'), true); return $r === false ? '' : $r; }

/** ticket = b64url(json).hmac — carried by the client, verified at sync. */
function oeSign(string $secret, array $payload): string
{
    $body = oeB64(json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}');
    return $body . '.' . hash_hmac('sha256', $body, $secret);
}

function oeVerify(string $secret, string $ticket, string $deviceId): ?array
{
    $bits = explode('.', $ticket);
    if (count($bits) !== 2) { return null; }
    if (!hash_equals(hash_hmac('sha256', $bits[0], $secret), $bits[1])) { return null; }
    $p = json_decode(oeUnb64($bits[0]), true);
    if (!is_array($p)) { return null; }
    if ((string) ($p['d'] ?? '') !== $deviceId) { return null; }
    if (time() > (int) ($p['exp'] ?? 0)) { return null; }
    return $p;
}

/** Whitelist the replay body — the journal payload can only ever say "make a sale". */
function oeSalePayload(array $p): ?array
{
    if (isset($p['schedule_block'])) { return null; }
    $items = [];
    foreach ((array) ($p['items'] ?? []) as $it) {
        if (!is_array($it)) { return null; }
        $items[] = [
            'medId' => (int) ($it['medId'] ?? 0),
            'batchId' => (int) ($it['batchId'] ?? 0),
            'qty' => round((float) ($it['qty'] ?? 0), 2),
            'rate' => round((float) ($it['rate'] ?? 0), 2),
            'discPct' => round((float) ($it['discPct'] ?? 0), 2),
            'unit' => (string) ($it['unit'] ?? 'pack'),
        ];
    }
    if (!$items) { return null; }
    $mode = strtolower((string) ($p['paymentMode'] ?? 'cash'));
    if (!in_array($mode, ['cash', 'upi', 'card', 'credit', 'split'], true)) { $mode = 'cash'; }
    return [
        'customerId' => (int) ($p['customerId'] ?? 0),
        'paymentMode' => $mode,
        'globalDiscPct' => round((float) ($p['globalDiscPct'] ?? 0), 2),
        'splitCash' => round((float) ($p['splitCash'] ?? 0), 2),
        'splitUpi' => round((float) ($p['splitUpi'] ?? 0), 2),
        'cashReceived' => round((float) ($p['cashReceived'] ?? 0), 2),
        'changeReturned' => round((float) ($p['changeReturned'] ?? 0), 2),
        'items' => $items,
    ];
}

/** Live outstanding for a customer — same disjoint-books math as the dues pages. */
function oeLiveDues(PDO $pdo, int $customerId): float
{
    try {
        $q = (int) $customerId;
        $bal = (float) ($pdo->query("SELECT COALESCE(SUM(balance_due),0) FROM sales WHERE customer_id = {$q}")->fetchColumn() ?: 0);
        $pay = (float) ($pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE party_type = 'customer' AND party_id = {$q}")->fetchColumn() ?: 0);
        return round(max(0.0, $bal - $pay), 2);
    } catch (Throwable $e) { return 0.0; }
}

/**
 * Apply one journal entry: dedupe handled by caller's row insert; here we
 * validate (credit only, unless $force), replay through the real sales API,
 * and map the outcome to applied/held. Never throws.
 */
function oeApply(PDO $pdo, array $entry, bool $force, string $sessionCookie): array
{
    $payload = json_decode((string) ($entry['payload'] ?? ''), true);
    if (!is_array($payload)) { return ['status' => 'held', 'reason' => 'journal payload unreadable']; }
    /* Rows stored by the sync INSERT are enveloped ({kind,meta,payload}); the hold/
       approve path re-reads that column, so unwrap before validating. Without this,
       every approve failed with "payload not a billable sale". */
    if (isset($payload['kind'], $payload['payload']) && is_array($payload['payload'])) { $payload = $payload['payload']; }
    $sale = oeSalePayload($payload);
    if ($sale === null) { return ['status' => 'held', 'reason' => 'payload not a billable sale']; }

    if (!$force && $sale['paymentMode'] === 'credit' && $sale['customerId'] > 0) {
        $pol = oeSettings($pdo);
        if ($pol['offline_credit_ok'] !== '1') { return ['status' => 'held', 'reason' => 'credit sales not allowed offline by policy']; }
        try {
            $limit = (float) ($pdo->query('SELECT COALESCE(credit_limit,0) FROM customers WHERE id = ' . (int) $sale['customerId'])->fetchColumn() ?: 0);
            if ($limit > 0) {
                $dues = oeLiveDues($pdo, (int) $sale['customerId']);
                $bill = (float) ($payload['_meta']['grandTotal'] ?? 0);
                if ($dues + $bill > $limit + 0.004) {
                    return ['status' => 'held', 'reason' => "credit limit: dues ₹{$dues} + bill ₹{$bill} > cap ₹{$limit}"];
                }
            }
        } catch (Throwable $e) { /* customers layout surprise — replay anyway, sales.php re-checks what it can */ }
    }

    $res = oeReplay($sale, $sessionCookie);
    if (!empty($res['ok'])) {
        $inv = (string) ($res['invoiceNo'] ?? '');
        if ($inv === '' && isset($res['data']['invoiceNo'])) { $inv = (string) $res['data']['invoiceNo']; }
        return ['status' => 'applied', 'invoice_no' => $inv];
    }
    return ['status' => 'held', 'reason' => substr((string) ($res['error'] ?? 'engine rejected the sale'), 0, 180)];
}

/** Same-origin replay against the store's own sales API with the syncing session. */
function oeReplay(array $sale, string $sessionCookie): array
{
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443 ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $url = "{$scheme}://{$host}/api/v1/sales.php";
    $body = json_encode($sale, JSON_UNESCAPED_UNICODE) ?: '{}';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Requested-With: fetch', 'X-Offline-Replay: 1', 'Cookie: ' . $sessionCookie],
            CURLOPT_POSTFIELDS => $body, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 40,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = (string) curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'ignore_errors' => true, 'timeout' => 40,
            'header' => "Content-Type: application/json\r\nX-Requested-With: fetch\r\nX-Offline-Replay: 1\r\nCookie: {$sessionCookie}\r\n",
            'content' => $body]]);
        $raw = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ((array) ($http_response_header ?? []) as $h) { if (preg_match('#^HTTP/\S+ (\d{3})#', $h, $m)) { $code = (int) $m[1]; break; } }
        $err = $raw === false ? 'internal loopback failed' : '';
    }
    $json = is_string($raw) ? json_decode($raw, true) : null;
    if ($code >= 200 && $code < 300 && is_array($json)) {
        $flat = isset($json['ok']) ? $json : ['ok' => true, 'data' => $json];
        if (isset($flat['data']) && is_array($flat['data'])) { $flat = array_merge($flat['data'], $flat); }
        return $flat;
    }
    $why = $err !== '' ? $err : (is_array($json) ? (string) ($json['error'] ?? 'HTTP ' . $code) : 'unreadable replay response');
    return ['ok' => false, 'error' => substr($why, 0, 200)];
}
