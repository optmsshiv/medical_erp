<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_GET['to'] ?? date('Y-m-d');

$pdo = Tenant::db();

// --- Sales ------------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT s.*, c.name AS customer_name,
            (SELECT COALESCE(SUM(si.amount - si.qty * b.purchase_rate), 0)
             FROM sale_items si JOIN batches b ON b.id = si.batch_id
             WHERE si.sale_id = s.id) AS profit
     FROM sales s JOIN customers c ON c.id = s.customer_id
     WHERE s.sale_date BETWEEN :from AND :to
     ORDER BY s.sale_date DESC, s.id DESC"
);
$stmt->execute(['from' => $from, 'to' => $to]);
$sales = array_map(fn($r) => [
    'date' => $r['sale_date'], 'no' => $r['invoice_no'], 'customer' => $r['customer_name'],
    'channel' => $r['channel'], 'amount' => (float) $r['grand_total'], 'tax' => (float) $r['gst_amount'],
    'discount' => (float) $r['discount'], 'profit' => round((float) $r['profit']), 'payment' => $r['payment_mode'],
], $stmt->fetchAll());

// --- Purchase -----------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT p.*, s.name AS supplier_name FROM purchases p JOIN suppliers s ON s.id = p.supplier_id
     WHERE p.invoice_date BETWEEN :from AND :to ORDER BY p.invoice_date DESC, p.id DESC"
);
$stmt->execute(['from' => $from, 'to' => $to]);
$purchases = array_map(fn($r) => [
    'date' => $r['invoice_date'], 'supplier' => $r['supplier_name'], 'no' => $r['invoice_no'],
    'amount' => (float) $r['grand_total'], 'tax' => (float) ($r['cgst'] + $r['sgst'] + $r['igst']),
    'payment' => $r['payment_mode'], 'status' => $r['balance_due'] > 0 ? 'Due' : 'Paid',
], $stmt->fetchAll());

// --- Stock (current snapshot, not date-filtered) -----------------------------
$stmt = $pdo->query(
    "SELECT b.*, m.name AS med_name, m.min_stock
     FROM batches b JOIN medicines m ON m.id = b.medicine_id
     ORDER BY m.name"
);
$stock = array_map(function ($r) {
    $qty = (int) $r['quantity'];
    $status = $qty === 0 ? 'Out of Stock' : ($qty <= (int) $r['min_stock'] ? 'Low Stock' : 'In Stock');
    return [
        'name' => $r['med_name'], 'batch' => $r['batch_no'], 'qty' => $qty,
        'purchaseValue' => round($qty * (float) $r['purchase_rate']), 'mrpValue' => round($qty * (float) $r['mrp']),
        'status' => $status,
    ];
}, $stmt->fetchAll());

// --- Expiry (<=90 days including already expired) ----------------------------
$stmt = $pdo->query(
    "SELECT b.*, m.name AS med_name FROM batches b JOIN medicines m ON m.id = b.medicine_id
     WHERE b.quantity > 0 AND DATEDIFF(b.expiry_date, CURDATE()) <= 90
     ORDER BY b.expiry_date ASC"
);
$expiry = array_map(fn($r) => [
    'name' => $r['med_name'], 'batch' => $r['batch_no'], 'expiry' => $r['expiry_date'],
    'days' => (int) floor((strtotime($r['expiry_date']) - strtotime(date('Y-m-d'))) / 86400),
    'qty' => (int) $r['quantity'], 'value' => round((int) $r['quantity'] * (float) $r['purchase_rate']),
], $stmt->fetchAll());

// --- Profit: KPIs + category breakdown (within range) + 7-day trend (fixed window) ---
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(si.amount), 0) AS revenue, COALESCE(SUM(si.qty * b.purchase_rate), 0) AS cost
     FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN batches b ON b.id = si.batch_id
     WHERE s.sale_date BETWEEN :from AND :to"
);
$stmt->execute(['from' => $from, 'to' => $to]);
$pRow = $stmt->fetch();
$profitKpis = [
    'sales' => (float) $pRow['revenue'],
    'cost'  => (float) $pRow['cost'],
    'gp'    => (float) $pRow['revenue'] - (float) $pRow['cost'],
];

$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE expense_date BETWEEN :from AND :to');
$stmt->execute(['from' => $from, 'to' => $to]);
$profitKpis['expenses'] = (float) $stmt->fetch()['total'];
$profitKpis['netProfit'] = $profitKpis['gp'] - $profitKpis['expenses'];

$stmt = $pdo->prepare(
    "SELECT COALESCE(c.name, 'Uncategorized') AS category,
            COALESCE(SUM(si.amount), 0) AS revenue, COALESCE(SUM(si.qty * b.purchase_rate), 0) AS cost
     FROM sale_items si
     JOIN sales s ON s.id = si.sale_id
     JOIN medicines m ON m.id = si.medicine_id
     JOIN batches b ON b.id = si.batch_id
     LEFT JOIN categories c ON c.id = m.category_id
     WHERE s.sale_date BETWEEN :from AND :to
     GROUP BY c.id ORDER BY revenue DESC"
);
$stmt->execute(['from' => $from, 'to' => $to]);
$profitByCategory = array_map(function ($r) {
    $rev = (float) $r['revenue'];
    $cost = (float) $r['cost'];
    $gp = $rev - $cost;
    return ['category' => $r['category'], 'sales' => $rev, 'cost' => $cost, 'gp' => $gp, 'margin' => $rev > 0 ? round($gp / $rev * 100, 1) : 0];
}, $stmt->fetchAll());

$stmt = $pdo->query(
    "SELECT s.sale_date AS d, COALESCE(SUM(si.amount - si.qty * b.purchase_rate), 0) AS gp
     FROM sales s
     LEFT JOIN sale_items si ON si.sale_id = s.id
     LEFT JOIN batches b ON b.id = si.batch_id
     WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY s.sale_date ORDER BY s.sale_date ASC"
);
$trendMap = [];
foreach ($stmt->fetchAll() as $r) {
    $trendMap[$r['d']] = round((float) $r['gp']);
}
$profitTrend = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $profitTrend[] = ['label' => date('D', strtotime($d)), 'value' => $trendMap[$d] ?? 0];
}

// --- GST: KPIs (within range) + wholesale invoice table + In/Out chart data ---
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(subtotal - discount), 0) AS taxable, COALESCE(SUM(cgst), 0) AS cgst,
            COALESCE(SUM(sgst), 0) AS sgst, COALESCE(SUM(igst), 0) AS igst
     FROM sales WHERE sale_date BETWEEN :from AND :to"
);
$stmt->execute(['from' => $from, 'to' => $to]);
$gstOut = $stmt->fetch();

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(cgst), 0) AS cgst, COALESCE(SUM(sgst), 0) AS sgst, COALESCE(SUM(igst), 0) AS igst
     FROM purchases WHERE invoice_date BETWEEN :from AND :to"
);
$stmt->execute(['from' => $from, 'to' => $to]);
$gstIn = $stmt->fetch();

$stmt = $pdo->prepare(
    "SELECT s.invoice_no AS no, c.name AS customer, s.gstin, s.subtotal, s.discount, s.cgst, s.sgst, s.igst, s.grand_total
     FROM sales s JOIN customers c ON c.id = s.customer_id
     WHERE s.channel = 'wholesale' AND s.sale_date BETWEEN :from AND :to
     ORDER BY s.sale_date DESC"
);
$stmt->execute(['from' => $from, 'to' => $to]);
$gstInvoices = array_map(fn($r) => [
    'no' => $r['no'], 'party' => $r['customer'], 'gstin' => $r['gstin'] ?? '—',
    'taxable' => (float) ($r['subtotal'] - $r['discount']), 'cgst' => (float) $r['cgst'],
    'sgst' => (float) $r['sgst'], 'igst' => (float) $r['igst'], 'total' => (float) $r['grand_total'],
], $stmt->fetchAll());

Json::ok([
    'range'      => ['from' => $from, 'to' => $to],
    'sales'      => $sales,
    'purchases'  => $purchases,
    'stock'      => $stock,
    'expiry'     => $expiry,
    'profitKpis' => $profitKpis,
    'profitByCategory' => $profitByCategory,
    'profitTrend' => $profitTrend,
    'gstKpis'    => [
        'taxable' => (float) $gstOut['taxable'], 'cgst' => (float) $gstOut['cgst'],
        'sgst' => (float) $gstOut['sgst'], 'igst' => (float) $gstOut['igst'],
    ],
    'gstIn'      => ['cgst' => (float) $gstIn['cgst'], 'sgst' => (float) $gstIn['sgst'], 'igst' => (float) $gstIn['igst']],
    'gstInvoices' => $gstInvoices,
]);
