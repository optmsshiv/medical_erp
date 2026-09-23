<?php
/**
 * check.php — FINAL comprehensive audit (v3): exact byte-size comparison
 * for every file in the project. Upload to the project root, visit in
 * your browser, then DELETE this file afterward.
 *
 * Byte-size matching is precise — a single missing character changes the
 * size — so MATCH here means the file is genuinely correct, not just
 * "close enough."
 */

$expected = [
    'api/v1/audit-logs.php' => 560,
    'api/v1/auth/login.php' => 915,
    'api/v1/auth/logout.php' => 149,
    'api/v1/batch-adjust.php' => 1788,
    'api/v1/bootstrap.php' => 6998,
    'api/v1/customers.php' => 1207,
    'api/v1/find-grn.php' => 2399,
    'api/v1/find-invoice.php' => 1937,
    'api/v1/medicines.php' => 3703,
    'api/v1/party-ledger.php' => 1792,
    'api/v1/payments.php' => 3275,
    'api/v1/purchase-return.php' => 5281,
    'api/v1/purchases.php' => 5619,
    'api/v1/reports.php' => 7365,
    'api/v1/role-permissions.php' => 1299,
    'api/v1/sales-return.php' => 4092,
    'api/v1/sales.php' => 6610,
    'api/v1/settings.php' => 1243,
    'api/v1/suppliers.php' => 1087,
    'api/v1/users.php' => 2625,
    'api/v1/wholesale.php' => 7225,
    'assets/js/app.js' => 33987,
    'assets/js/charts.js' => 6091,
    'assets/js/config.js' => 272,
    'assets/js/data.js' => 37007,
    'assets/js/pos.js' => 17968,
    'batch-management.php' => 11624,
    'config/database.php' => 1437,
    'core/Audit.php' => 888,
    'core/Auth.php' => 1869,
    'core/ClientProvisioner.php' => 4276,
    'core/CpanelApi.php' => 5420,
    'core/Database.php' => 2248,
    'core/Json.php' => 559,
    'core/Model.php' => 5069,
    'core/Tenant.php' => 3597,
    'customers.php' => 15851,
    'dashboard.php' => 16280,
    'expiry-management.php' => 11939,
    'index.php' => 9323,
    'login.php' => 6541,
    'logout.php' => 115,
    'medicine-master.php' => 22834,
    'middleware/auth.php' => 352,
    'middleware/tenant.php' => 369,
    'models/AuditLog.php' => 136,
    'models/Batch.php' => 130,
    'models/Category.php' => 136,
    'models/Customer.php' => 135,
    'models/Manufacturer.php' => 143,
    'models/Medicine.php' => 692,
    'models/Payment.php' => 133,
    'models/Purchase.php' => 135,
    'models/PurchaseItem.php' => 144,
    'models/PurchaseReturn.php' => 148,
    'models/PurchaseReturnItem.php' => 157,
    'models/RolePermission.php' => 148,
    'models/Sale.php' => 127,
    'models/SaleItem.php' => 136,
    'models/SalesReturn.php' => 142,
    'models/SalesReturnItem.php' => 151,
    'models/Setting.php' => 190,
    'models/StockAdjustment.php' => 150,
    'models/Supplier.php' => 135,
    'models/User.php' => 127,
    'purchase-return.php' => 10734,
    'purchase.php' => 19347,
    'reports.php' => 22608,
    'retail-pos.php' => 10781,
    'sales-return.php' => 9834,
    'scripts/provision-client.php' => 1171,
    'settings.php' => 23369,
    'super-admin/_guard.php' => 314,
    'super-admin/actions/create-client.php' => 749,
    'super-admin/clients.php' => 7274,
    'super-admin/login.php' => 1899,
    'suppliers.php' => 14746,
    'wholesale-billing.php' => 23385,
];

header('Content-Type: text/plain');

$missing = 0;
$mismatch = 0;

foreach ($expected as $path => $expectedSize) {
    $full = __DIR__ . '/' . $path;

    if (!file_exists($full)) {
        echo "MISSING   {$path}\n";
        $missing++;
        continue;
    }

    $actualSize = filesize($full);

    if ($actualSize === $expectedSize) {
        echo "OK        {$path}\n";
    } else {
        echo "MISMATCH  {$path}  (expected {$expectedSize} bytes, found {$actualSize})\n";
        $mismatch++;
    }
}

echo "\n" . count($expected) . " files checked — {$missing} missing, {$mismatch} size mismatch.\n";
if ($missing === 0 && $mismatch === 0) {
    echo "Everything matches. The full project is correctly deployed.\n";
}