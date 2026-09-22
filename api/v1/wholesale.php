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

$customerId     = (int) ($input['customerId'] ?? 0);
$interstate     = !empty($input['interstate']);
$schemeDiscPct  = min(100, max(0, (float) ($input['schemeDiscPct'] ?? 0)));
$overallDiscPct = min(100, max(0, (float) ($input['overallDiscPct'] ?? 0)));
$paymentMode    = $input['paymentMode'] ?? 'cash';
$items          = $input['items'] ?? [];

$customer = Customer::find($customerId);
if (!$customer) {
    Json::error('Select a valid customer.', 422);
}
if (!in_array($paymentMode, ['cash', 'upi', 'bank', 'credit'], true)) {
    $paymentMode = 'cash';
}
if (!is_array($items) || count($items) === 0) {
    Json::error('Add at least one item line.', 422);
}

$pdo = Tenant::db();

try {
    $pdo->beginTransaction();

    $lines = [];
    $subtotal = 0.0;
    $lineDiscTotal = 0.0;
    $taxAtLineRate = 0.0; // sum of lineAmount * gst%, BEFORE scheme/overall discount is spread across it

    foreach ($items as $item) {
        $medId = (int) ($item['medId'] ?? 0);
        $qty   = (int) ($item['qty'] ?? 0);

        if (!$medId || $qty <= 0) {
            continue;
        }
        $medicine = Medicine::find($medId);
        if (!$medicine) {
            continue;
        }

        $freeQty = max(0, (int) ($item['freeQty'] ?? 0));
        $rate    = max(0, (float) ($item['rate'] ?? $medicine['wholesale_rate']));
        $discPct = min(100, max(0, (float) ($item['discPct'] ?? 0)));
        $gstPct  = (float) $medicine['gst_rate'];

        // Resolve to a REAL batch: try the batch number given, else FEFO-pick
        // the earliest-expiring batch with enough stock. The reference never
        // tied wholesale lines to a real batch at all — this fixes that.
        $batch = null;
        $batchNo = strtoupper(trim($item['batch'] ?? ''));
        $totalQty = $qty + $freeQty;

        if ($batchNo !== '') {
            $stmt = $pdo->prepare(
                'SELECT * FROM batches WHERE medicine_id = :medId AND batch_no = :no LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['medId' => $medId, 'no' => $batchNo]);
            $batch = $stmt->fetch();
        }
        if (!$batch) {
            $stmt = $pdo->prepare(
                'SELECT * FROM batches WHERE medicine_id = :medId AND (quantity - reserved) >= :qty
                 ORDER BY expiry_date ASC LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['medId' => $medId, 'qty' => $totalQty]);
            $batch = $stmt->fetch();
        }
        if (!$batch) {
            throw new RuntimeException("No batch with enough stock for {$medicine['name']}.");
        }

        $available = (int) $batch['quantity'] - (int) $batch['reserved'];
        if ($totalQty > $available) {
            throw new RuntimeException("Only {$available} unit(s) of {$medicine['name']} (batch {$batch['batch_no']}) available.");
        }

        $lineGross = $qty * $rate;
        $thisLineDisc = $lineGross * ($discPct / 100);
        $lineAmount = $lineGross - $thisLineDisc;

        $subtotal += $lineGross;
        $lineDiscTotal += $thisLineDisc;
        $taxAtLineRate += $lineAmount * ($gstPct / 100);

        $lines[] = [
            'medId' => $medId, 'batchId' => $batch['id'], 'batchNo' => $batch['batch_no'],
            'qty' => $qty, 'freeQty' => $freeQty, 'rate' => $rate, 'discPct' => $discPct,
            'gstPct' => $gstPct, 'amount' => $lineAmount, 'totalQty' => $totalQty,
        ];
    }

    if (count($lines) === 0) {
        throw new RuntimeException('Add at least one valid item line.');
    }

    // Two-stage bill-level discount, same formula as the UI: scheme first, then overall.
    $afterLine = $subtotal - $lineDiscTotal;
    $schemeAmt = $afterLine * ($schemeDiscPct / 100);
    $overallAmt = ($afterLine - $schemeAmt) * ($overallDiscPct / 100);
    $discount = $lineDiscTotal + $schemeAmt + $overallAmt;
    $taxable = $afterLine - $schemeAmt - $overallAmt;

    // Spread the bill-level discount proportionally across the tax too.
    $taxAfterDisc = $afterLine > 0 ? $taxAtLineRate * ($taxable / $afterLine) : 0;

    $rawGrand = $taxable + $taxAfterDisc;
    $grand = round($rawGrand);
    $roundOff = $grand - $rawGrand;

    $cgst = $interstate ? 0 : $taxAfterDisc / 2;
    $sgst = $interstate ? 0 : $taxAfterDisc / 2;
    $igst = $interstate ? $taxAfterDisc : 0;

    $amountPaid = $paymentMode === 'credit' ? 0.0 : $grand;
    $balanceDue = max(0, $grand - $amountPaid);

    $saleId = Sale::create([
        'customer_id'  => $customerId,
        'channel'      => 'wholesale',
        'invoice_no'   => 'PENDING',
        'sale_date'    => date('Y-m-d'),
        'gstin'        => $customer['gstin'],   // snapshot from the real customer record, not client input
        'dl_no'        => $customer['dl_no'],
        'subtotal'     => $subtotal,
        'discount'     => $discount,
        'gst_amount'   => $taxAfterDisc,
        'cgst'         => $cgst,
        'sgst'         => $sgst,
        'igst'         => $igst,
        'round_off'    => $roundOff,
        'grand_total'  => $grand,
        'payment_mode' => $paymentMode,
        'amount_paid'  => $amountPaid,
        'balance_due'  => $balanceDue,
    ]);

    $invoiceNo = 'WS-' . date('y') . '-' . str_pad((string) $saleId, 4, '0', STR_PAD_LEFT);
    Sale::update($saleId, ['invoice_no' => $invoiceNo]);

    foreach ($lines as $line) {
        SaleItem::create([
            'sale_id'     => $saleId,
            'medicine_id' => $line['medId'],
            'batch_id'    => $line['batchId'],
            'qty'         => $line['qty'],
            'free_qty'    => $line['freeQty'],
            'rate'        => $line['rate'],
            'disc_pct'    => $line['discPct'],
            'gst_pct'     => $line['gstPct'],
            'amount'      => $line['amount'],
        ]);

        $stmt = $pdo->prepare(
            'UPDATE batches SET quantity = quantity - :qty WHERE id = :id AND (quantity - reserved) >= :qty2'
        );
        $stmt->execute(['qty' => $line['totalQty'], 'id' => $line['batchId'], 'qty2' => $line['totalQty']]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException("Stock changed for batch {$line['batchNo']} — please retry.");
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    Json::error($e->getMessage() ?: 'Could not save the invoice.', 422);
}

require dirname(__DIR__, 2) . '/core/Audit.php';
Audit::log('WHOLESALE_SALE', "{$invoiceNo} · ₹{$grand}");

Json::ok([
    'id'         => $saleId,
    'invoiceNo'  => $invoiceNo,
    'grandTotal' => $grand,
    'balanceDue' => $balanceDue,
]);
