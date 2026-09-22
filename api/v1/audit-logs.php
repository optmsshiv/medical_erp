<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$pdo = Tenant::db();
$stmt = $pdo->query('SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 500');
$rows = array_map(fn($r) => [
    'ts' => $r['created_at'], 'user' => $r['user_name'] ?? 'System', 'action' => $r['action'], 'detail' => $r['detail'] ?? '',
], $stmt->fetchAll());

Json::ok(['data' => $rows]);
