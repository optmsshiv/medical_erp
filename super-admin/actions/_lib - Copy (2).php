<?php
/**
 * Shared wiring for super-admin action endpoints:
 * guard, master-DB handle, audit writer, flash+redirect back to clients.php.
 */
require_once __DIR__ . '/../_guard.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';

/** The controllable module map. Keep keys stable — tenants read them verbatim. */
const CLIENT_FEATURES = [
    'wholesale'        => 'Wholesale billing module',
    'quick_refill'     => 'Quick Refill (repeat prescriptions)',
    'rx_inbox'         => 'Rx inbox (scan & send)',
    'compliance_export'=> 'Compliance register exports (H1/X PDF/Excel)',
    'customer_dues'    => 'Customer dues / credit book',
    'expenses'         => 'Expense tracking',
    'supplier_dues'    => 'Supplier dues',
    'price_bulk'       => 'Bulk price update',
];

/* ---- Feature control v2: every sidebar menu & submenu, per client. -------
 * Page-level keys are 'menu_<slug>' — the sidebar label lowercased with every
 * non-alphanumeric run collapsed to a single '_': 'Products / Medicines' →
 * menu_products_medicines. Slugs, NOT raw labels: PHP rewrites dots and
 * spaces in submitted form-field names, so any key containing a space cannot
 * round-trip the modal form — that was the v2.0 "toggles snap back on" bug.
 * MenuGate::featKey() and MF._featKey() derive the identical slug from the
 * label, so the tenant app never needs a manual key list. The eight legacy
 * module keys stay authoritative for the APIs that already read them. */
const CLIENT_FEATURE_GROUPS = [
    'Sales' => [
        'menu_retail_pos' => ['Retail POS page', 'Counter billing screen'],
        'menu_wholesale_billing' => ['Wholesale Billing page', ''],
        'menu_sales_invoices' => ['Sales Invoices page', ''],
        'menu_sales_returns' => ['Sales Returns page', ''],
        'menu_customers' => ['Customers page', ''],
        'menu_customer_dues' => ['Customer Dues page', ''],
        'wholesale' => ['Wholesale module (API)', 'B2B billing engine & credit notes'],
        'customer_dues' => ['Customer dues ledger (API)', 'Credit book & reminders'],
    ],
    'Purchases' => [
        'menu_new_purchase' => ['New Purchase page', ''],
        'menu_purchase_invoices' => ['Purchase Invoices page', ''],
        'menu_purchase_returns' => ['Purchase Returns page', ''],
        'menu_suppliers' => ['Suppliers page', ''],
        'menu_supplier_dues' => ['Supplier Dues page', ''],
        'supplier_dues' => ['Supplier dues ledger (API)', 'Outstanding payables'],
    ],
    'Inventory' => [
        'menu_products_medicines' => ['Medicine master page', ''],
        'menu_stock_overview' => ['Stock Overview page', ''],
        'menu_medicine_ledger' => ['Medicine Ledger page', ''],
        'menu_batch_management' => ['Batch Management tab', ''],
        'menu_expiry_management' => ['Expiry Management tab', ''],
        'menu_low_stock' => ['Low Stock tab', ''],
        'menu_stock_adjustment' => ['Stock Adjustment tab', ''],
        'menu_stock_transfer' => ['Stock Transfer tab', ''],
        'price_bulk' => ['Bulk price update (API)', 'Batch price corrections'],
    ],
    'Pharmacy' => [
        'menu_prescriptions' => ['Prescriptions page', ''],
        'menu_doctors' => ['Doctors page', ''],
        'menu_manufacturers' => ['Manufacturers page', ''],
        'menu_medicine_categories' => ['Medicine Categories page', ''],
        'menu_schedule_class' => ['Schedule / Class page', ''],
        'menu_compliance_register' => ['Compliance Register page', ''],
        'compliance_export' => ['Compliance exports', 'H1/X register PDF & Excel'],
        'rx_inbox' => ['Rx inbox', 'Scan & send prescriptions'],
        'quick_refill' => ['Quick Refill', 'Repeat previous prescriptions'],
    ],
    'Accounts' => [
        'menu_payments' => ['Payments page', ''],
        'menu_expenses' => ['Expenses page', ''],
        'menu_cash_book' => ['Cash Book page', ''],
        'menu_gst' => ['GST register page', ''],
        'expenses' => ['Expense tracking (API)', 'Shop expense ledger'],
    ],
    'Reports' => [
        'menu_sales_reports' => ['Sales Reports', ''],
        'menu_purchase_reports' => ['Purchase Reports', ''],
        'menu_stock_reports' => ['Stock Reports', ''],
        'menu_expiry_reports' => ['Expiry Reports', ''],
        'menu_profit_reports' => ['Profit Reports', ''],
        'menu_gst_reports' => ['GST Reports', ''],
        'menu_due_reports' => ['Due Reports', ''],
    ],
    'Administration' => [
        'menu_whatsapp' => ['WhatsApp page', 'Customer numbers & due-reminder control'],
    ],
];

/** Checkbox presence that survives PHP's name mangling (dots/spaces → '_'). */
function posted(string $name): bool {
    if (isset($_POST[$name])) { return true; }
    $alt = str_replace(['.', ' '], '_', $name);
    return $alt !== $name && isset($_POST[$alt]);
}

/** Flat key → label view (persistence loops iterate this). */
function clientFeatureKeys(): array {
    $flat = CLIENT_FEATURES;
    foreach (CLIENT_FEATURE_GROUPS as $items) {
        foreach ($items as $k => $v) { $flat[$k] = $v[0]; }
    }
    return $flat;
}

function master(): PDO { return Database::master(); }

function audit(string $action, ?int $clientId = null, string $detail = ''): void {
    try {
        master()->query(
            'INSERT INTO admin_audit (actor, client_id, action, detail) VALUES ('
            . master()->quote('key-session') . ', '
            . ($clientId !== null ? (int) $clientId : 'NULL') . ', '
            . master()->quote($action) . ', '
            . master()->quote($detail) . ')'
        );
    } catch (Throwable $e) { /* audit must never break the action itself */ }
}

function findClient(int $id): ?array {
    if ($id <= 0) { return null; }
    $row = master()->query('SELECT * FROM clients WHERE id = ' . (int) $id . ' LIMIT 1')->fetch();
    return is_array($row) && $row ? $row : null;
}

function flashOk(string $msg): void { $_SESSION['flash_ok_msg'] = $msg; }
function flashErr(string $msg): void { $_SESSION['flash_error'] = $msg; }

function backTo(string $path = '/super-admin/clients.php'): never {
    header('Location: ' . $path);
    exit;
}
function back(): never { backTo(); }

/** True when a master table/column actually exists (defensive — never assume). */
function hasColumn(string $table, string $column): bool {
    try {
        $r = master()->query(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = ' . master()->quote($table)
            . ' AND COLUMN_NAME = ' . master()->quote($column)
        )->fetch();
        return $r && (int) array_values($r)[0] > 0;
    } catch (Throwable $e) { return false; }
}

function clientFeaturesOn(int $clientId): array {
    $on = [];
    foreach (clientFeatureKeys() as $k => $_) { $on[$k] = true; }        // fail-open default — EVERY key (menu + legacy), the v2.0 loader bug
    try {
        foreach (master()->query('SELECT feature, allowed FROM client_features WHERE client_id = ' . (int) $clientId)->fetchAll() as $r) {
            if (array_key_exists($r['feature'], $on)) { $on[$r['feature']] = (bool) $r['allowed']; }
        }
    } catch (Throwable $e) { /* defaults stand */ }
    return $on;
}

/* ---- Plan caps: telemetry compares live usage against these (metering only,
   tenant-side hard blocks come with the enforcement phase). -1 = uncapped. ---- */
const PLAN_CAPS = [
    'trial'    => ['invoices_month' => 500,  'users' => 2, 'medicines' => 2000],
    'standard' => ['invoices_month' => 8000, 'users' => 5, 'medicines' => 20000],
    'annual'   => ['invoices_month' => -1,   'users' => -1, 'medicines' => -1],
];

function planCaps(string $plan): array {
    return PLAN_CAPS[strtolower($plan)] ?? PLAN_CAPS['trial'];
}

/** Connect to one tenant's own database through the SAME path the app itself uses:
 *  core/Tenant::db() → Database::client(db_name, db_user, db_password, db_host).
 *  Rows provisioned before credentials were stored fall back to the master-db account. */
function tenantDb(array $client): PDO {
    $dbName = (string) ($client['db_name'] ?? '');
    if ($dbName === '') { throw new RuntimeException('Client row has no db_name.'); }
    $user = (string) ($client['db_user'] ?? '');
    $pass = (string) ($client['db_password'] ?? '');
    $host = (string) ($client['db_host'] ?? '');
    if ($user === '') {
        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $user = (string) $config['username'];
        $pass = (string) $config['password'];
        $host = $host !== '' ? $host : (string) $config['host'];
    }
    if ($host === '') { $host = 'localhost'; }
    return Database::client($dbName, $user, $pass, $host);
}

/* ---- billing helpers ---- */
function billingSetting(string $key, string $default = ''): string {
    try {
        $r = master()->query('SELECT `value` FROM billing_settings WHERE `key` = ' . master()->quote($key) . ' LIMIT 1')->fetch();
        return $r ? (string) $r['value'] : $default;
    } catch (Throwable $e) { return $default; }
}

function planPrices(): array {
    $out = [];
    try {
        foreach (master()->query('SELECT code, price, active FROM billing_plans')->fetchAll() as $p) {
            $out[strtolower((string) $p['code'])] = ['price' => (float) $p['price'], 'active' => (int) $p['active']];
        }
    } catch (Throwable $e) { }
    return $out;
}

/* ==================== WhatsApp (Meta Cloud API) ============================
 * Config lives in billing_settings (wa_* keys). Sends are REST-only — no
 * webhook, no SDK. Everything degrades to a logged failure; a broken Meta
 * call must never break billing or onboarding. */

function waCfg(): array {
    $out = ['enabled' => false, 'token' => '', 'phone_id' => '', 'ver' => 'v21.0',
            'lang' => 'en', 'cc' => '91', 'tpl_onboard' => '', 'tpl_paid' => '', 'tpl_alert' => ''];
    try {
        foreach (master()->query("SELECT `key`, `value` FROM billing_settings WHERE `key` LIKE 'wa_%'")->fetchAll() as $r) {
            $k = substr((string) $r['key'], 3);
            if (array_key_exists($k, $out)) { $out[$k] = (string) $r['value']; }
        }
    } catch (Throwable $e) { /* no settings table yet → everything off */ }
    $out['enabled'] = $out['enabled'] === '1';
    return $out;
}

/** 9876543210 / +91 9876543210 / 09876543210 → 919876543210. */
function waNorm(string $raw, string $cc = '91'): string {
    $d = preg_replace('/\D+/', '', $raw) ?? '';
    if ($d === '') { return ''; }
    if (strlen($d) === 10) { return $cc . $d; }
    if (strlen($d) === 11 && $d[0] === '0') { return $cc . substr($d, 1); }
    if (strlen($d) === strlen($cc) + 10 && strpos($d, $cc) === 0) { return $d; }
    if (strlen($d) === strlen($cc) + 11 && $d[strlen($cc)] === '0') { return $cc . substr($d, strlen($cc) + 1); }
    return $d;
}

/** Self-migrate: clients.whatsapp + wa_log. Cheap, once per request. */
function waEnsure(): void {
    static $done = false;
    if ($done) { return; }
    $done = true;
    try { if (!hasColumn('clients', 'whatsapp')) { master()->query('ALTER TABLE clients ADD COLUMN whatsapp VARCHAR(20) NULL'); } } catch (Throwable $e) { }
    try {
        master()->query('CREATE TABLE IF NOT EXISTS wa_log (
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
    } catch (Throwable $e) { }
}

function waPost(string $url, array $payload, string $token): array {
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}", 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = (string) curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Authorization: Bearer {$token}\r\nContent-Type: application/json\r\n",
            'content' => $body, 'timeout' => 15, 'ignore_errors' => true]]);
        $raw = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ((array) ($http_response_header ?? []) as $h) { if (preg_match('#^HTTP/\S+ (\d{3})#', $h, $m)) { $code = (int) $m[1]; break; } }
        $err = $raw === false ? 'transport failed' : '';
    }
    $json = is_string($raw) ? json_decode($raw, true) : null;
    if ($code >= 200 && $code < 300 && is_array($json)) {
        return ['ok' => true, 'api_id' => (string) ($json['messages'][0]['id'] ?? ''), 'payload' => $payload];
    }
    $why = $err !== '' ? $err : (is_array($json) ? (string) ($json['error']['message'] ?? json_encode($json)) : substr((string) $raw, 0, 160));
    return ['ok' => false, 'error' => substr($why . ' (HTTP ' . $code . ')', 0, 240), 'payload' => $payload];
}

/** One entry point for every send. Template kinds: onboard · paid · alert · test. */
function waSend(?int $clientId, string $to, string $kind, array $params = [], string $text = '', string $tplOverride = ''): array {
    $cfg = waCfg();
    if (!$cfg['enabled'] || $cfg['token'] === '' || $cfg['phone_id'] === '') {
        return ['ok' => false, 'skipped' => true, 'error' => 'WhatsApp not configured/enabled'];
    }
    waEnsure();
    $num = waNorm($to, $cfg['cc']);
    if ($num === '') { return ['ok' => false, 'error' => 'No valid WhatsApp number on file']; }
    $tpl = trim($tplOverride) !== '' ? trim($tplOverride) : (string) ($cfg['tpl_' . $kind] ?? '');
    $url = "https://graph.facebook.com/{$cfg['ver']}/{$cfg['phone_id']}/messages";
    if ($tpl !== '') {
        $comp = [];
        $clean = [];
        foreach (array_slice($params, 0, 10) as $pv) { $clean[] = ['type' => 'text', 'text' => mb_substr((string) $pv, 0, 300)]; }
        if ($clean) { $comp[] = ['type' => 'body', 'parameters' => $clean]; }
        $payload = ['messaging_product' => 'whatsapp', 'to' => $num, 'type' => 'template',
                    'template' => ['name' => $tpl, 'language' => ['code' => $cfg['lang']]]];
        if ($comp) { $payload['template']['components'] = $comp; }
        $via = 'template';
    } elseif (trim($text) !== '') {
        $payload = ['messaging_product' => 'whatsapp', 'to' => $num, 'type' => 'text', 'text' => ['body' => mb_substr(trim($text), 0, 1000)]];
        $via = 'session';
    } else {
        return ['ok' => false, 'error' => "No template configured for kind '{$kind}' and no free text given"];
    }
    $res = waPost($url, $payload, $cfg['token']);
    try {
        master()->query('INSERT INTO wa_log (client_id, to_number, kind, via, ok, api_id, error, payload) VALUES ('
            . ($clientId !== null ? (int) $clientId : 'NULL') . ', ' . master()->quote($num) . ', ' . master()->quote($kind) . ', '
            . master()->quote($via) . ', ' . ($res['ok'] ? 1 : 0) . ', '
            . (isset($res['api_id']) && $res['api_id'] !== '' ? master()->quote($res['api_id']) : 'NULL') . ', '
            . (isset($res['error']) && $res['error'] !== '' ? master()->quote(substr($res['error'], 0, 240)) : 'NULL') . ', '
            . master()->quote(mb_substr(json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '', 0, 1200)) . ')');
    } catch (Throwable $e) { /* logging must not sink the send */ }
    return $res;
}

/** One invoice row per client per covered period — UNIQUE(client_id, period_from) makes this idempotent. */
function coverInvoiceExists(int $clientId, string $reffrom): bool {
    try {
        $r = master()->query(
            "SELECT COUNT(*) AS n FROM client_invoices WHERE status <> 'void' AND client_id = " . (int) $clientId
            . " AND period_from <= " . master()->quote($reffrom) . " AND period_to >= " . master()->quote($reffrom)
        )->fetch();
        return $r && (int) $r['n'] > 0;
    } catch (Throwable $e) {
        throw new RuntimeException('Coverage check failed for client #' . $clientId . ': ' . $e->getMessage());
    }
}
