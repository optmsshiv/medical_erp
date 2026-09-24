<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Supplier.php';
require dirname(__DIR__, 2) . '/models/Customer.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$pdo = Tenant::db();

// --- Medicines (real data, joined to category/manufacturer names) ---------
$stmt = $pdo->query(
    'SELECT m.*, c.name AS category_name, mf.name AS manufacturer_name, gg.name AS generic_group_name
     FROM medicines m
     LEFT JOIN categories c ON m.category_id = c.id
     LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.id
     LEFT JOIN generic_groups gg ON m.generic_group_id = gg.id
     ORDER BY m.name'
);
$medicineRows = $stmt->fetchAll();

$medicines = array_map(function ($row) {
    return [
        'id'            => (int) $row['id'],
        'name'          => $row['name'],
        'generic'       => $row['generic_name'] ?? '',
        'composition'   => $row['composition'] ?? '',
        'category'      => $row['category_name'] ?? '',
        'manufacturer'  => $row['manufacturer_name'] ?? '',
        'brandRef'      => $row['brand_ref'] ?? '',
        'hsn'           => $row['hsn_code'] ?? '',
        'gst'           => (float) $row['gst_rate'],
        'unit'          => $row['unit'],
        'packSize'      => $row['pack_size'] ?? '',
        'packQty'       => (int) $row['pack_qty'],
        'subUnit'       => $row['sub_unit'] ?? '',
        'allowLoose'    => (bool) $row['allow_loose_sale'],
        'barcode'       => $row['barcode'] ?? '',
        'genericGroupId' => $row['generic_group_id'] ? (int) $row['generic_group_id'] : null,
        'genericGroup'  => $row['generic_group_name'] ?? '',
        'expiryAlertDays' => $row['expiry_alert_days'] !== null ? (int) $row['expiry_alert_days'] : null,
        'boxQty'        => $row['box_qty'] !== null ? (int) $row['box_qty'] : null,
        'boxUnit'       => $row['box_unit'] ?? 'Box',
        'mrp'           => (float) $row['mrp'],
        'retailRate'    => (float) $row['retail_rate'],
        'purchaseRate'  => (float) $row['purchase_rate'],
        'wholesaleRate' => (float) $row['wholesale_rate'],
        'minStock'      => (int) $row['min_stock'],
        'reorderLevel'  => (int) $row['reorder_level'],
        'schedule'      => $row['schedule_class'] ?? '',
        'rxRequired'    => (bool) $row['rx_required'],
        'coldChain'     => (bool) $row['cold_chain'],
        'status'        => ucfirst($row['status']),
    ];
}, $medicineRows);

// --- Batches (real data) ---------------------------------------------------
$stmt = $pdo->query('SELECT * FROM batches ORDER BY expiry_date ASC');
$batchRows = $stmt->fetchAll();

$batches = array_map(function ($row) {
    return [
        'id'           => (int) $row['id'],
        'medId'        => (int) $row['medicine_id'],
        'batchNo'      => $row['batch_no'],
        'purchaseDate' => $row['purchase_date'],
        'expiry'       => $row['expiry_date'],
        'purchaseRate' => (float) $row['purchase_rate'],
        'mrp'          => (float) $row['mrp'],
        'qty'          => (int) $row['quantity'],
        'looseQty'     => (int) $row['loose_qty'],
        'reserved'     => (int) $row['reserved'],
    ];
}, $batchRows);

// --- Suppliers (real data, with real aggregated purchase stats) -----------
$supplierRows = $pdo->query(
    'SELECT s.*, COALESCE(SUM(p.grand_total), 0) AS total_purchases,
            COALESCE(SUM(p.amount_paid), 0) AS paid,
            COALESCE(SUM(p.balance_due), 0) AS due,
            MAX(p.invoice_date) AS last_purchase
     FROM suppliers s
     LEFT JOIN purchases p ON p.supplier_id = s.id
     GROUP BY s.id
     ORDER BY s.name'
)->fetchAll();

$suppliers = array_map(function ($row) {
    return [
        'id'      => (int) $row['id'],
        'name'    => $row['name'],
        'gstin'   => $row['gstin'] ?? '',
        'dlNo'    => $row['dl_no'] ?? '',
        'phone'   => $row['phone'] ?? '',
        'address' => $row['address'] ?? '',
        'totalPurchases' => (float) $row['total_purchases'],
        'paid'    => (float) $row['paid'],
        'due'     => (float) $row['due'],
        'lastPurchase' => $row['last_purchase'],
    ];
}, $supplierRows);

// --- Customers (real data, with real aggregated sales stats) --------------
$customers = $pdo->query(
    'SELECT c.id, c.name, c.type, c.phone, c.gstin, c.dl_no, c.address,
            COALESCE(SUM(s.grand_total), 0) AS total_sales,
            COALESCE(SUM(s.amount_paid), 0) AS paid,
            COALESCE(SUM(s.balance_due), 0) AS due,
            MAX(s.sale_date) AS last_purchase
     FROM customers c
     LEFT JOIN sales s ON s.customer_id = c.id
     GROUP BY c.id
     ORDER BY (c.name = "Walk-in Customer") DESC, c.name'
)->fetchAll();
$customers = array_map(fn($r) => [
    'id' => (int) $r['id'], 'name' => $r['name'], 'type' => $r['type'], 'phone' => $r['phone'] ?? '',
    'gstin' => $r['gstin'] ?? '', 'dlNo' => $r['dl_no'] ?? '', 'address' => $r['address'] ?? '',
    'totalSales' => (float) $r['total_sales'], 'paid' => (float) $r['paid'], 'due' => (float) $r['due'],
    'lastPurchase' => $r['last_purchase'],
], $customers);

// --- Store settings (real, with sensible fallback to the client's name) ---
$settingsRows = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
$settingsFlat = [];
foreach ($settingsRows as $r) {
    $settingsFlat[$r['setting_key']] = $r['setting_value'];
}
$store = [
    'name'    => $settingsFlat['store_name'] ?? Tenant::current()['name'],
    'address' => $settingsFlat['store_address'] ?? '',
    'phone'   => $settingsFlat['store_phone'] ?? '',
    'gstin'   => $settingsFlat['store_gstin'] ?? '',
    'pan'     => $settingsFlat['store_pan'] ?? '',
    'dl20b'   => $settingsFlat['store_dl20b'] ?? '',
    'dl21b'   => $settingsFlat['store_dl21b'] ?? '',
    'email'   => $settingsFlat['store_email'] ?? '',
];

// --- Doctors (real data) -----------------------------------------------------
$doctors = $pdo->query('SELECT id, name, specialty, phone, reg_no FROM doctors ORDER BY name')->fetchAll();
$doctors = array_map(fn($r) => [
    'id' => (int) $r['id'], 'name' => $r['name'], 'specialty' => $r['specialty'] ?? '',
    'phone' => $r['phone'] ?? '', 'regNo' => $r['reg_no'] ?? '',
], $doctors);


// --- Categories / manufacturers (plain name lists, for dropdowns etc.) ----
$categories    = $pdo->query('SELECT name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$manufacturers = $pdo->query('SELECT name FROM manufacturers ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);

// --- Stock value (real — computed from real batches) -----------------------
$stockValue = 0.0;
foreach ($batchRows as $b) {
    $stockValue += $b['quantity'] * $b['purchase_rate'];
}

// --- Sales/purchase/dues — no sales, purchase, customer, or supplier
// tables exist yet, so these are honest zeros, not fabricated numbers.
// Wire these up for real once those modules are built.
$dashboard = [
    'todaySales'         => 0,
    'todaySalesDelta'    => 0,
    'todayPurchase'      => 0,
    'todayPurchaseDelta' => 0,
    'grossProfit'        => 0,
    'grossProfitDelta'   => 0,
    'stockValue'         => $stockValue,
    'stockValueDelta'    => 0,
    'customerDue'        => 0,
    'customerDueDelta'   => 0,
    'supplierDue'        => 0,
    'supplierDueDelta'   => 0,
    'salesSplit'         => ['retail' => 0, 'wholesale' => 0],
    'topSellers'         => [],
];

Json::ok([
    'data' => [
        'manufacturers'    => $manufacturers,
        'categories'       => $categories,
        'medicines'        => $medicines,
        'batches'          => $batches,
        'doctors'          => [],
        'customers'        => $customers,
        'suppliers'        => $suppliers,
        'salesInvoices'    => [],
        'purchaseInvoices' => [],
        'notifications'    => [],
        'store'            => $store,
        'users'            => [],
        'auditLogs'        => [],
        'dashboard'        => $dashboard,
    ],
]);
