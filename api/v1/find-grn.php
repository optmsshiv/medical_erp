<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$invoiceNo = trim($_GET['no'] ?? '');
if ($invoiceNo === '') {
    Json::error('GRN / invoice number is required.', 422);
}

$pdo = Tenant::db();

$stmt = $pdo->prepare(
    'SELECT p.*, s.name AS supplier_name FROM purchases p
     JOIN suppliers s ON s.id = p.supplier_id
     WHERE p.invoice_no = :no LIMIT 1'
);
$stmt->execute(['no' => $invoiceNo]);
$purchase = $stmt->fetch();

if (!$purchase) {
    Json::error('GRN / purchase invoice not found.', 404);
}

$stmt = $pdo->prepare(
    'SELECT pi.*, m.name AS med_name, m.gst_rate, b.batch_no, b.expiry_date, b.quantity AS batch_qty, b.reserved,
            COALESCE((SELECT SUM(pri.qty) FROM purchase_return_items pri WHERE pri.purchase_item_id = pi.id), 0) AS already_returned
     FROM purchase_items pi
     JOIN medicines m ON m.id = pi.medicine_id
     JOIN batches b ON b.id = pi.batch_id
     WHERE pi.purchase_id = :purchaseId'
);
$stmt->execute(['purchaseId' => $purchase['id']]);
$items = array_map(function ($r) {
    $purchased = (int) $r['qty'] + (int) $r['free_qty'];
    $returned = (int) $r['already_returned'];
    $availableInBatch = (int) $r['batch_qty'] - (int) $r['reserved'];
    // Can't return more than what's left to return, AND can't return more
    // than what's currently physically in the batch (some may have sold already).
    $remaining = max(0, min($purchased - $returned, $availableInBatch));
    return [
        'purchaseItemId' => (int) $r['id'],
        'medId'          => (int) $r['medicine_id'],
        'name'           => $r['med_name'],
        'batch'          => $r['batch_no'],
        'expiry'         => $r['expiry_date'],
        'gst'            => (float) $r['gst_rate'],
        'purchasedQty'   => $purchased,
        'alreadyReturned' => $returned,
        'remainingQty'   => $remaining,
        'rate'           => (float) $r['rate'],
    ];
}, $stmt->fetchAll());

Json::ok([
    'purchase' => [
        'id' => (int) $purchase['id'], 'no' => $purchase['invoice_no'], 'date' => $purchase['invoice_date'],
        'supplier' => $purchase['supplier_name'], 'balanceDue' => (float) $purchase['balance_due'],
    ],
    'items' => $items,
]);
