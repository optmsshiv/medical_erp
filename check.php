<?php
/**
 * check.php — one-time file-audit script.
 * Upload to the project root, visit in your browser, then DELETE this
 * file afterward (it lists your server's folder structure, which
 * shouldn't stay publicly reachable once you're done checking).
 */

$expected = [
    '.env', '.env.example', '.htaccess',
    'api/v1/auth/login.php', 'api/v1/auth/logout.php', 'api/v1/bootstrap.php', 'api/v1/medicines.php',
    'assets/css/style.css', 'assets/images/logo.svg', 'assets/js/app.js', 'assets/js/charts.js',
    'assets/js/config.js', 'assets/js/data.js',
    'batch-management.php', 'config/database.php',
    'core/Auth.php', 'core/ClientProvisioner.php', 'core/CpanelApi.php', 'core/Database.php',
    'core/Json.php', 'core/Model.php', 'core/Tenant.php',
    'dashboard.php',
    'database/client-schema/schema.sql', 'database/master/schema.sql',
    'database/migrations/001_expand_medicine_batch_schema.sql',
    'database/migrations/002_seed_categories_manufacturers.sql',
    'index.php', 'login.php', 'logout.php', 'medicine-master.php',
    'middleware/auth.php', 'middleware/tenant.php',
    'models/Batch.php', 'models/Category.php', 'models/Manufacturer.php', 'models/Medicine.php',
    'scripts/provision-client.php',
    'super-admin/_guard.php', 'super-admin/actions/create-client.php',
    'super-admin/clients.php', 'super-admin/login.php',
];

header('Content-Type: text/plain');

$missingCount = 0;
foreach ($expected as $path) {
    $exists = file_exists(__DIR__ . '/' . $path);
    if (!$exists) {
        $missingCount++;
    }
    echo ($exists ? 'OK      ' : 'MISSING ') . $path . "\n";
}

echo "\n{$missingCount} missing out of " . count($expected) . " total.\n";