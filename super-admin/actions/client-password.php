<?php
/** Reset the primary admin login of a tenant — schema-tolerant. */
require __DIR__ . '/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { back(); }

$id     = (int) ($_POST['client_id'] ?? 0);
$client = findClient($id);
if (!$client) { flashErr('Client not found.'); back(); }

$dbName = (string) ($client['db_name'] ?? '');
$name   = (string) ($client['name'] ?? '');
if ($dbName === '') { flashErr('This client has no database on record.'); back(); }

try {
    $tenant = tenantDb($client); // Database::client() — the same path Tenant::db() uses

    // Discover the users table defensively — installs differ.
    $users  = $tenant->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$users) { throw new RuntimeException("No users table in {$dbName}."); }

    $cols = [];
    foreach ($tenant->query("SHOW COLUMNS FROM users")->fetchAll() as $c) { $cols[$c['Field']] = true; }
    $passCol = null;
    foreach (['password_hash', 'password', 'pass_hash'] as $cand) {
        if (isset($cols[$cand])) { $passCol = $cand; break; }
    }
    if (!$passCol) { throw new RuntimeException('users table has no known password column.'); }

    // Primary admin: role-flagged admin first, otherwise the first user ever created.
    $where = isset($cols['role'])
        ? " WHERE LOWER(role) IN ('admin','owner','superadmin','super_admin') "
        : '';
    $order = isset($cols['id']) ? 'id ASC' : '1';
    $admin = $tenant->query('SELECT * FROM users' . $where . " ORDER BY {$order} LIMIT 1")->fetch();
    if (!$admin && $where !== '') {
        $admin = $tenant->query("SELECT * FROM users ORDER BY {$order} LIMIT 1")->fetch();
    }
    if (!$admin) { throw new RuntimeException('No user rows in the tenant users table.'); }

    $newPass = substr(bin2hex(random_bytes(6)) . chr(random_int(97, 122)) . random_int(10, 99), 0, 12);
    $hash    = password_hash($newPass, PASSWORD_DEFAULT);

    $userKey = isset($cols['id']) ? 'id = ' . (int) $admin['id'] : 'email = ' . $tenant->quote((string) ($admin['email'] ?? ''));
    $tenant->query("UPDATE users SET {$passCol} = " . $tenant->quote($hash) . " WHERE {$userKey} LIMIT 1");

    // One-time reveal through the same credential flash card used at provisioning.
    $_SESSION['flash_success'] = [
        'login_url'      => (string) ($client['base_url'] ?? (($client['subdomain'] ?? '') . '.' . env('APP_DOMAIN', 'optms.co.in'))),
        'admin_email'    => (string) ($admin['email'] ?? $admin['username'] ?? ''),
        'admin_password' => $newPass,
        'db_name'        => $dbName,
        'subdomain'      => (string) ($client['subdomain'] ?? ''),
        'domain'         => (string) env('APP_DOMAIN', 'optms.co.in'),
    ];
    audit('PASSWORD_RESET', $id, $name . ' — primary login rotated');
} catch (Throwable $e) {
    flashErr('Password reset failed: ' . $e->getMessage());
}
back();
