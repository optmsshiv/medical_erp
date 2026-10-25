<?php
/* ---------------------------------------------------------------------------
 * api/v1/settings.php — FINAL settings API (deploy snapshot).
 * Adds on top of the stock settings API:
 *   GET  ?seed=preview                → default taxonomy presence (no writes)
 *   POST { "action": "seed-defaults" } → CoreSeeds::run() + SEED_DEFAULTS audit
 * Plain settings GET/POST behavior is unchanged. The seed library is loaded
 * only if uploaded; everything keeps working even before it lands on the server.
 * ------------------------------------------------------------------------- */
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/core/Audit.php';

$seedsLib = dirname(__DIR__, 2) . '/core/CoreSeeds.php';
if (is_file($seedsLib)) { require $seedsLib; }        // tolerate the seed library not being uploaded yet

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$pdo = Tenant::db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Preview of the default taxonomy (nothing is written) — api/v1/settings.php?seed=preview
    if (($_GET['seed'] ?? '') === 'preview') {
        if (!class_exists('CoreSeeds')) {
            Json::error('Seed library missing on the server. Upload core/CoreSeeds.php and core/seeds/taxonomy.php.', 500);
        }
        $t = CoreSeeds::taxonomy();
        $catPresent = 0;
        foreach ($t['categories'] as $n) {
            if (Category::first('name', '=', (string) $n)) { $catPresent++; }
        }
        $mfgPresent = 0;
        foreach ($t['manufacturers'] as $n) {
            if (Manufacturer::first('name', '=', (string) $n)) { $mfgPresent++; }
        }
        Json::ok(['data' => [
            'categories_total'      => count($t['categories']),
            'categories_present'    => $catPresent,
            'manufacturers_total'   => count($t['manufacturers']),
            'manufacturers_present' => $mfgPresent,
        ]]);
    }

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
    $action = $input['action'] ?? '';       // request an action instead of plain key-value saves
    unset($input['group'], $input['action']);

    // Load the default categories & manufacturers taxonomy into this tenant.
    // Idempotent: existing names are skipped, so it is safe to run any time.
    if ($action === 'seed-defaults') {
        if (!class_exists('CoreSeeds')) {
            Json::error('Seed library missing on the server. Upload core/CoreSeeds.php and core/seeds/taxonomy.php.', 500);
        }
        $result = CoreSeeds::run();
        $c = $result['categories']; $m = $result['manufacturers'];
        Audit::log('SEED_DEFAULTS', "Default taxonomy loaded · categories +{$c['inserted']} ({$c['skipped']} present) · manufacturers +{$m['inserted']} ({$m['skipped']} present)");
        Json::ok(['data' => $result]);
    }

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
