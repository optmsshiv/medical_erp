<?php
/**
 * provision-client.php
 *
 * CLI alternative to the /super-admin/clients.php web form — same
 * underlying logic (core/ClientProvisioner.php), useful for scripted/bulk
 * onboarding.
 *
 * Usage:
 *   php scripts/provision-client.php "Green Pharma" greenpharma ramesh@greenpharma.example "Ramesh Kumar"
 */

require dirname(__DIR__) . '/core/ClientProvisioner.php';

if ($argc < 4) {
    fwrite(STDERR, "Usage: php provision-client.php \"Client Name\" subdomain admin@email.com [\"Admin Name\"]\n");
    exit(1);
}

try {
    $result = ClientProvisioner::provision([
        'name'        => $argv[1],
        'subdomain'   => $argv[2],
        'admin_email' => $argv[3],
        'admin_name'  => $argv[4] ?? 'Admin',
    ]);

    echo "Client created.\n";
    echo "  Login URL : {$result['login_url']}\n";
    echo "  Email     : {$result['admin_email']}\n";
    echo "  Password  : {$result['admin_password']}\n";
    echo "  Database  : {$result['db_name']}\n";
    echo "\nSave the password now — it is not stored anywhere retrievable.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Provisioning failed: " . $e->getMessage() . "\n");
    exit(1);
}