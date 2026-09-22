<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$invoiceNo = strtoupper(trim($_GET['no'] ?? ''));
if ($invoiceNo === '') {
    Json::error('Invoice number is required.', 422);
}

$pdo = Tenant::db();

$stmt = $pdo->prepare(
    'SELECT s.*, c.name AS customer_name FROM sales s
     JOIN customers c ON c.id = s.customer_id
     WHERE s.invoice_no = :no LIMIT 1'
);
$stmt->execute(['no' => $invoiceNo]);
$sale = $stmt->fetch();

if (!$sale) {
    Json::error('Invoice not found.', 404);
}

$stmt = $pdo->prepare(
    'SELECT si.*, m.name AS med_name, m.gst_rate, b.batch_no,
            COALESCE((SELECT SUM(sri.qty) FROM sales_return_items sri WHERE sri.sale_item_id = si.id), 0) AS already_returned
     FROM sale_items si
     JOIN medicines m ON m.id = si.medicine_id
     JOIN batches b ON b.id = si.batch_id
     WHERE si.sale_id = :saleId'
);
$stmt->execute(['saleId' => $sale['id']]);
$items = array_map(function ($r) {
    $sold = (int) $r['qty'];
    $returned = (int) $r['already_returned'];
    return [
        'saleItemId'    => (int) $r['id'],
        'medId'         => (int) $r['medicine_id'],
        'name'          => $r['med_name'],
        'batch'         => $r['batch_no'],
        'gst'           => (float) $r['gst_rate'],
        'soldQty'       => $sold,
        'alreadyReturned' => $returned,
        'remainingQty'  => max(0, $sold - $returned),
        'rate'          => (float) $r['rate'],
    ];
}, $stmt->fetchAll());

Json::ok([
    'sale' => [
        'id' => (int) $sale['id'], 'no' => $sale['invoice_no'], 'date' => $sale['sale_date'],
        'customer' => $sale['customer_name'], 'channel' => $sale['channel'],
        'balanceDue' => (float) $sale['balance_due'],
    ],
    'items' => $items,
]);
