<?php
require __DIR__ . '/../_guard.php';
require dirname(__DIR__, 2) . '/core/ClientProvisioner.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /super-admin/clients.php');
    exit;
}

try {
    $result = ClientProvisioner::provision([
        'name'           => $_POST['name'] ?? '',
        'subdomain'      => $_POST['subdomain'] ?? '',
        'admin_name'     => $_POST['admin_name'] ?? '',
        'admin_email'    => $_POST['admin_email'] ?? '',
        'admin_password' => $_POST['admin_password'] ?: null, // empty -> auto-generate
    ]);

    $_SESSION['flash_success'] = $result;
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}

header('Location: /super-admin/clients.php');
exit;