<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Customer.php';
require dirname(__DIR__, 2) . '/models/Medicine.php';
require dirname(__DIR__, 2) . '/models/Sale.php';
require dirname(__DIR__, 2) . '/models/SaleItem.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Json::error('Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$customerId    = (int) ($input['customerId'] ?? 0);
$paymentMode   = $input['paymentMode'] ?? 'cash';
$globalDiscPct = min(100, max(0, (float) ($input['globalDiscPct'] ?? 0)));
$splitCash     = (float) ($input['splitCash'] ?? 0);
$splitUpi      = (float) ($input['splitUpi'] ?? 0);
$items         = $input['items'] ?? [];

if (!$customerId || !Customer::find($customerId)) {
    Json::error('Select a customer.', 422);
}
if (!in_array($paymentMode, ['cash', 'upi', 'card', 'credit', 'split'], true)) {
    $paymentMode = 'cash';
}
if (!is_array($items) || count($items) === 0) {
    Json::error('Cart is empty.', 422);
}

$pdo = Tenant::db();

try {
    $pdo->beginTransaction();

    $lines = [];
    $subtotal = 0.0;
    $discount = 0.0;
    $gstAmount = 0.0;

    foreach ($items as $item) {
        $medId    = (int) ($item['medId'] ?? 0);
        $batchId  = (int) ($item['batchId'] ?? 0);
        $qty      = (int) ($item['qty'] ?? 0);

        if (!$medId || !$batchId || $qty <= 0) {
            continue;
        }

        $medicine = Medicine::find($medId);
        if (!$medicine) {
            continue;
        }

        // Lock the batch row so two simultaneous sales can't both oversell it.
        $stmt = $pdo->prepare('SELECT * FROM batches WHERE id = :id AND medicine_id = :medId FOR UPDATE');
        $stmt->execute(['id' => $batchId, 'medId' => $medId]);
        $batch = $stmt->fetch();

        if (!$batch) {
            throw new RuntimeException("Batch not found for {$medicine['name']}.");
        }

        $available = (int) $batch['quantity'] - (int) $batch['reserved'];
        if ($qty > $available) {
            throw new RuntimeException("Only {$available} unit(s) of {$medicine['name']} (batch {$batch['batch_no']}) available.");
        }

        $rate    = max(0, (float) ($item['rate'] ?? $medicine['mrp']));
        $discPct = min(100, max(0, (float) ($item['discPct'] ?? 0)));
        $gstPct  = (float) $medicine['gst_rate'];

        $gross = $qty * $rate;
        $lineDisc = $gross * ($discPct / 100);
        $net = $gross - $lineDisc;
        // GST is already included in MRP — extract it rather than add it.
        $lineGst = $gstPct > 0 ? $net - $net / (1 + $gstPct / 100) : 0;

        $subtotal += $gross;
        $discount += $lineDisc;
        $gstAmount += $lineGst;

        $lines[] = [
            'medId' => $medId, 'batchId' => $batchId, 'batchNo' => $batch['batch_no'],
            'qty' => $qty, 'rate' => $rate, 'discPct' => $discPct, 'gstPct' => $gstPct, 'amount' => $net,
        ];
    }

    if (count($lines) === 0) {
        throw new RuntimeException('Cart has no valid items.');
    }

    // Bill-level discount applies to (subtotal - line discounts), same as the UI.
    $billDiscount = ($subtotal - $discount) * ($globalDiscPct / 100);
    $discount += $billDiscount;
    $net = $subtotal - $discount;
    $grand = round($net);
    $roundOff = $grand - $net;

    // Credit-sale guard: Walk-in Customer can't run a tab.
    if ($paymentMode === 'credit') {
        $customer = Customer::find($customerId);
        if ($customer['name'] === 'Walk-in Customer') {
            throw new RuntimeException('Credit sales are not allowed for Walk-in Customer.');
        }
    }

    $cashAmount = 0.0;
    $upiAmount = 0.0;
    $amountPaid = 0.0;

    if ($paymentMode === 'split') {
        if (abs($splitCash + $splitUpi - $grand) > 0.5) {
            throw new RuntimeException('Split amounts must equal the Grand Total.');
        }
        $cashAmount = $splitCash;
        $upiAmount = $splitUpi;
        $amountPaid = $splitCash + $splitUpi;
    } elseif ($paymentMode === 'credit') {
        $amountPaid = 0.0;
    } else {
        $amountPaid = $grand;
    }

    $balanceDue = max(0, $grand - $amountPaid);

    $saleId = Sale::create([
        'customer_id'  => $customerId,
        'invoice_no'   => 'PENDING', // filled in right below, once we have the id
        'sale_date'    => date('Y-m-d'),
        'subtotal'     => $subtotal,
        'discount'     => $discount,
        'gst_amount'   => $gstAmount,
        'round_off'    => $roundOff,
        'grand_total'  => $grand,
        'payment_mode' => $paymentMode,
        'cash_amount'  => $cashAmount,
        'upi_amount'   => $upiAmount,
        'amount_paid'  => $amountPaid,
        'balance_due'  => $balanceDue,
    ]);

    $invoiceNo = 'INV-' . date('y') . '-' . str_pad((string) $saleId, 4, '0', STR_PAD_LEFT);
    Sale::update($saleId, ['invoice_no' => $invoiceNo]);

    foreach ($lines as $line) {
        SaleItem::create([
            'sale_id'     => $saleId,
            'medicine_id' => $line['medId'],
            'batch_id'    => $line['batchId'],
            'qty'         => $line['qty'],
            'rate'        => $line['rate'],
            'disc_pct'    => $line['discPct'],
            'gst_pct'     => $line['gstPct'],
            'amount'      => $line['amount'],
        ]);

        // Conditional UPDATE — extra safety net beyond the row lock above.
        $stmt = $pdo->prepare(
            'UPDATE batches SET quantity = quantity - :qty
             WHERE id = :id AND (quantity - reserved) >= :qty2'
        );
        $stmt->execute(['qty' => $line['qty'], 'id' => $line['batchId'], 'qty2' => $line['qty']]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException("Stock changed for batch {$line['batchNo']} — please retry.");
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    Json::error($e->getMessage() ?: 'Could not complete the sale.', 422);
}

Json::ok([
    'id'         => $saleId,
    'invoiceNo'  => $invoiceNo,
    'grandTotal' => $grand,
    'balanceDue' => $balanceDue,
]);
