<?php
/**
 * Live health pulse for ONE tenant (JSON) — the dashboard health card fetches
 * this per client, so the portal page renders instantly and each row fills in
 * independently. Every metric is gathered defensively: an unreadable piece
 * reports null, never a fatal, and the client row degrades to "offline".
 */
require __DIR__ . '/_lib.php';
header('Content-Type: application/json');

$id     = (int) ($_GET['client_id'] ?? 0);
$client = findClient($id);
if (!$client) { echo json_encode(['error' => 'Client not found.']); exit; }

$out = [
    'client_id'       => $id,
    'db_size_mb'      => null,
    'invoices_total'  => null,
    'invoices_month'  => null,
    'last_sale'       => null,   // YYYY-MM-DD
    'medicines'       => null,
    'customers'       => null,
    'last_login'      => null,   // datetime string, if the tenant schema exposes it
    'cap_invoices'    => null,   // cap for their plan; null = uncapped
    'cap_pct'         => null,   // 0..100, null when uncapped/unknown
    'online'          => false,
    'offline_reason'  => null,     // real connection error, for the super-admin only
];

$dbName = (string) ($client['db_name'] ?? '');
if ($dbName === '') { echo json_encode($out); exit; }

/* DB size — from information_schema, no tenant connection needed. */
try {
    $row = master()->query(
        'SELECT ROUND(SUM(data_length + index_length) / 1048576, 1) AS mb
           FROM information_schema.TABLES WHERE table_schema = ' . master()->quote($dbName)
    )->fetch();
    $out['db_size_mb'] = $row && $row['mb'] !== null ? (float) $row['mb'] : null;
} catch (Throwable $e) { /* leave null */ }

/* Tenant-side vitals — each gathered independently. */
try {
    $tenant = Database::tenant($client);
    $out['online'] = true;

    try {
        $r = $tenant->query(
            "SELECT COUNT(*) AS n, MAX(sale_date) AS last_date FROM sales"
        )->fetch();
        if ($r) { $out['invoices_total'] = (int) $r['n']; $out['last_sale'] = substr((string) $r['last_date'], 0, 10) ?: null; }
    } catch (Throwable $e) { }

    try {
        $r = $tenant->query(
            "SELECT COUNT(*) AS n FROM sales
              WHERE sale_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                AND sale_date < DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')"
        )->fetch();
        if ($r) { $out['invoices_month'] = (int) $r['n']; }
    } catch (Throwable $e) { }

    try { $out['medicines']  = (int) ($tenant->query('SELECT COUNT(*) n FROM medicines')->fetch()['n'] ?? 0); } catch (Throwable $e) { }
    try { $out['customers']  = (int) ($tenant->query('SELECT COUNT(*) n FROM customers')->fetch()['n'] ?? 0); } catch (Throwable $e) { }

    try {
        $cols = [];
        foreach ($tenant->query('SHOW COLUMNS FROM users')->fetchAll() as $c) { $cols[$c['Field']] = true; }
        $loginCol = null;
        foreach (['last_login_at', 'last_login', 'logged_at'] as $cand) {
            if (isset($cols[$cand])) { $loginCol = $cand; break; }
        }
        if ($loginCol) {
            $r = $tenant->query("SELECT MAX({$loginCol}) AS t FROM users")->fetch();
            $out['last_login'] = $r && $r['t'] ? (string) $r['t'] : null;
        }
    } catch (Throwable $e) { }
} catch (Throwable $e) { $out['offline_reason'] = basename(get_class($e)) . ': ' . substr($e->getMessage(), 0, 180); }

/* Cap metering against the plan (display/metering only — no enforcement here). */
$caps = planCaps((string) ($client['plan'] ?? 'trial'));
if ($caps['invoices_month'] > 0 && $out['invoices_month'] !== null) {
    $out['cap_invoices'] = $caps['invoices_month'];
    $out['cap_pct']      = min(100, (int) round($out['invoices_month'] * 100 / $caps['invoices_month']));
}
echo json_encode($out);
