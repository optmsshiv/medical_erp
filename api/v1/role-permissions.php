<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/core/Audit.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$pdo = Tenant::db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo->query('SELECT role, module, allowed FROM role_permissions ORDER BY role, module')->fetchAll();
    Json::ok(['data' => array_map(fn($r) => ['role' => $r['role'], 'module' => $r['module'], 'allowed' => (bool) $r['allowed']], $rows)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $rows = $input['rows'] ?? [];

    $stmt = $pdo->prepare(
        'INSERT INTO role_permissions (role, module, allowed) VALUES (:role, :module, :allowed)
         ON DUPLICATE KEY UPDATE allowed = :allowed2'
    );
    foreach ($rows as $r) {
        $allowed = !empty($r['allowed']) ? 1 : 0;
        $stmt->execute(['role' => $r['role'], 'module' => $r['module'], 'allowed' => $allowed, 'allowed2' => $allowed]);
    }

    Audit::log('PERMISSIONS_UPDATE', count($rows) . ' permission entries saved');
    Json::ok();
}

Json::error('Method not allowed.', 405);
