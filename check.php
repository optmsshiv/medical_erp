<?php
/**
 * check.php — file-audit script v2: existence AND content signature.
 * Upload to the project root, visit in your browser, then DELETE this
 * file afterward (it lists your server's folder structure).
 */

// path => a short string that MUST appear in the correct version of that file.
// If the file exists but doesn't contain this, it's a stale/wrong version.
$expected = [
    '.htaccess'                                    => 'core|models|middleware',
    'api/v1/auth/login.php'                        => "dirname(__DIR__, 3)",
    'api/v1/auth/logout.php'                       => 'Auth::logout',
    'api/v1/bootstrap.php'                         => 'gst_rate',
    'api/v1/medicines.php'                         => 'findOrCreateId',
    'assets/js/app.js'                             => 'MF_CONFIG',
    'assets/js/config.js'                          => 'MF_CONFIG',
    'batch-management.php'                         => 'middleware/auth.php',
    'config/database.php'                          => 'putenv',
    'core/Auth.php'                                => 'class Auth',
    'core/ClientProvisioner.php'                   => 'class ClientProvisioner',
    'core/CpanelApi.php'                            => 'class CpanelApi',
    'core/Database.php'                            => 'class Database',
    'core/Json.php'                                => 'class Json',
    'core/Model.php'                               => 'abstract class Model',
    'core/Tenant.php'                              => 'class Tenant',
    'dashboard.php'                                => 'middleware/auth.php',
    'index.php'                                    => 'middleware/auth.php',
    'login.php'                                    => 'login-wrap',
    'logout.php'                                   => 'Auth::logout',
    'medicine-master.php'                          => 'middleware/auth.php',
    'middleware/auth.php'                          => 'Auth::check',
    'middleware/tenant.php'                        => 'Tenant::resolve',
    'models/Batch.php'                             => 'class Batch',
    'models/Category.php'                          => 'class Category',
    'models/Manufacturer.php'                      => 'class Manufacturer',
    'models/Medicine.php'                          => 'class Medicine',
    'super-admin/_guard.php'                       => 'super_admin',
    'super-admin/actions/create-client.php'        => 'ClientProvisioner::provision',
    'super-admin/clients.php'                      => 'Add a client',
    'super-admin/login.php'                        => 'SUPER_ADMIN_KEY',
];

header('Content-Type: text/plain');

$missing = 0;
$stale = 0;

foreach ($expected as $path => $signature) {
    $full = __DIR__ . '/' . $path;

    if (!file_exists($full)) {
        echo "MISSING  {$path}\n";
        $missing++;
        continue;
    }

    $content = file_get_contents($full);

    if (str_contains($content, $signature)) {
        echo "OK       {$path}\n";
    } else {
        echo "STALE?   {$path}  (expected to contain: \"{$signature}\")\n";
        $stale++;
    }
}

echo "\n{$missing} missing, {$stale} possibly stale, out of " . count($expected) . " checked.\n";