<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Supplier.php';
require dirname(__DIR__, 2) . '/models/Medicine.php';
require dirname(__DIR__, 2) . '/models/Batch.php';
require dirname(__DIR__, 2) . '/models/Purchase.php';
require dirname(__DIR__, 2) . '/models/PurchaseItem.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Json::error('Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$supplierId  = (int) ($input['supplierId'] ?? 0);
$invoiceNo   = trim($input['invoiceNo'] ?? '');
$invoiceDate = trim($input['invoiceDate'] ?? '') ?: date('Y-m-d');
$paymentMode = $input['paymentMode'] ?? 'Credit';
$amountPaid  = (float) ($input['amountPaid'] ?? 0);
$items       = $input['items'] ?? [];

if (!$supplierId || !Supplier::find($supplierId)) {
    Json::error('Select a valid supplier.', 422);
}
if ($invoiceNo === '') {
    Json::error('Supplier invoice number is required.', 422);
}
if (!is_array($items) || count($items) === 0) {
    Json::error('Add at least one item line.', 422);
}
if (!in_array($paymentMode, ['Cash', 'Bank', 'UPI', 'Credit'], true)) {
    $paymentMode = 'Credit';
}

// --- Server-side recompute of every total — never trust client math. ---
$lines = [];
$subtotal = 0.0;
$discount = 0.0;
$taxTotal = 0.0;

foreach ($items as $item) {
    $medId = (int) ($item['medId'] ?? 0);
    $qty   = (int) ($item['qty'] ?? 0);

    if (!$medId || $qty <= 0) {
        continue; // skip incomplete lines, same as the frontend's own "valid" filter
    }

    $medicine = Medicine::find($medId);
    if (!$medicine) {
        continue;
    }

    $freeQty  = max(0, (int) ($item['freeQty'] ?? 0));
    $rate     = max(0, (float) ($item['rate'] ?? 0));
    $discPct  = min(100, max(0, (float) ($item['discPct'] ?? 0)));
    $gstPct   = max(0, (float) ($item['gst'] ?? $medicine['gst_rate']));

    $lineBase   = $qty * $rate;
    $lineDisc   = $lineBase * ($discPct / 100);
    $lineAmount = $lineBase - $lineDisc;
    $lineTax    = $lineAmount * ($gstPct / 100);

    $subtotal += $lineBase;
    $discount += $lineDisc;
    $taxTotal += $lineTax;

    $lines[] = [
        'medicine' => $medicine,
        'medId'    => $medId,
        'batch'    => strtoupper(trim($item['batch'] ?? '')),
        'expiry'   => trim($item['expiry'] ?? ''), // "YYYY-MM" from <input type=month>
        'qty'      => $qty,
        'freeQty'  => $freeQty,
        'rate'     => $rate,
        'discPct'  => $discPct,
        'gstPct'   => $gstPct,
        'amount'   => $lineAmount,
    ];
}

if (count($lines) === 0) {
    Json::error('Add at least one valid item line.', 422);
}

$taxable   = $subtotal - $discount;
$cgst      = $taxTotal / 2;
$sgst      = $taxTotal / 2;
$igst      = 0.0; // local-supplier assumption, same as the reference design
$rawGrand  = $taxable + $taxTotal;
$grand     = round($rawGrand);
$roundOff  = $grand - $rawGrand;

if ($paymentMode === 'Credit') {
    $amountPaid = 0.0;
}
$amountPaid = max(0, min($amountPaid, $grand));
$balanceDue = max(0, $grand - $amountPaid);

$pdo = Tenant::db();

try {
    $pdo->beginTransaction();

    $purchaseId = Purchase::create([
        'supplier_id'  => $supplierId,
        'invoice_no'   => $invoiceNo,
        'invoice_date' => $invoiceDate,
        'subtotal'     => $subtotal,
        'discount'     => $discount,
        'taxable'      => $taxable,
        'cgst'         => $cgst,
        'sgst'         => $sgst,
        'igst'         => $igst,
        'round_off'    => $roundOff,
        'grand_total'  => $grand,
        'payment_mode' => $paymentMode,
        'amount_paid'  => $amountPaid,
        'balance_due'  => $balanceDue,
    ]);

    foreach ($lines as $line) {
        // Expiry: "YYYY-MM" -> last calendar day of that month.
        $expiryDate = $line['expiry'] !== ''
            ? date('Y-m-t', strtotime($line['expiry'] . '-01'))
            : date('Y-m-t', strtotime('+2 years'));

        $batchNo = $line['batch'] !== '' ? $line['batch'] : ('NB' . date('ymd') . random_int(100, 999));

        $batchId = Batch::create([
            'medicine_id'   => $line['medId'],
            'batch_no'      => $batchNo,
            'purchase_date' => $invoiceDate,
            'expiry_date'   => $expiryDate,
            'quantity'      => $line['qty'] + $line['freeQty'],
            'reserved'      => 0,
            'purchase_rate' => $line['rate'],
            'mrp'           => (float) $line['medicine']['mrp'],
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchaseId,
            'medicine_id' => $line['medId'],
            'batch_id'    => $batchId,
            'qty'         => $line['qty'],
            'free_qty'    => $line['freeQty'],
            'rate'        => $line['rate'],
            'disc_pct'    => $line['discPct'],
            'gst_pct'     => $line['gstPct'],
            'amount'      => $line['amount'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('Purchase save failed: ' . $e->getMessage());
    Json::error('Could not save the purchase. Nothing was changed.', 500);
}

require dirname(__DIR__, 2) . '/core/Audit.php';
Audit::log('PURCHASE_CREATE', "{$invoiceNo} · ₹{$grand}");

Json::ok([
    'id'         => $purchaseId,
    'grandTotal' => $grand,
    'balanceDue' => $balanceDue,
    'batches'    => count($lines),
]);
