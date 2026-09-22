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
    $rows = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
    $flat = [];
    foreach ($rows as $r) {
        $flat[$r['setting_key']] = $r['setting_value'];
    }
    Json::ok(['data' => $flat]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $group = $input['group'] ?? 'settings'; // just for the audit log label
    unset($input['group']);

    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = :v2'
    );
    foreach ($input as $key => $value) {
        $stmt->execute(['k' => $key, 'v' => (string) $value, 'v2' => (string) $value]);
    }

    Audit::log('SETTINGS_UPDATE', ucfirst($group) . ' settings saved');
    Json::ok();
}

Json::error('Method not allowed.', 405);
