<?php
/**
 * Shared wiring for super-admin action endpoints:
 * guard, master-DB handle, audit writer, flash+redirect back to clients.php.
 */
require __DIR__ . '/../_guard.php';
require dirname(__DIR__, 2) . '/core/Database.php';

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
    // WhatsApp notifications stay out: gated on Meta API approval (deferred roadmap).
];

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
    foreach (CLIENT_FEATURES as $k => $_) { $on[$k] = true; }           // fail-open default
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

/** One invoice row per client per covered period — UNIQUE(client_id, period_from) makes this idempotent. */
function coverInvoiceExists(int $clientId, string $reffrom): bool {
    try {
        $r = master()->query(
            "SELECT COUNT(*) AS n FROM client_invoices WHERE status <> 'void' AND client_id = " . (int) $clientId
            . " AND period_from <= " . master()->quote($reffrom) . " AND period_to >= " . master()->quote($reffrom)
        )->fetch();
        return $r && (int) $r['n'] > 0;
    } catch (Throwable $e) { return true; }   // on doubt, don't double-bill
}
