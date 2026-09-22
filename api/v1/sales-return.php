<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Sale.php';
require dirname(__DIR__, 2) . '/models/SalesReturn.php';
require dirname(__DIR__, 2) . '/models/SalesReturnItem.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Json::error('Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$saleId = (int) ($input['saleId'] ?? 0);
$reason = trim($input['reason'] ?? 'Customer Return');
$note   = trim($input['note'] ?? '');
$items  = $input['items'] ?? [];

$sale = Sale::find($saleId);
if (!$sale) {
    Json::error('Invoice not found.', 404);
}
if (!is_array($items) || count($items) === 0) {
    Json::error('Select at least one item to return.', 422);
}

$pdo = Tenant::db();

try {
    $pdo->beginTransaction();

    $lines = [];
    $refundTotal = 0.0;

    foreach ($items as $item) {
        $saleItemId = (int) ($item['saleItemId'] ?? 0);
        $qty = (int) ($item['qty'] ?? 0);
        if (!$saleItemId || $qty <= 0) {
            continue;
        }

        $stmt = $pdo->prepare('SELECT * FROM sale_items WHERE id = :id AND sale_id = :saleId FOR UPDATE');
        $stmt->execute(['id' => $saleItemId, 'saleId' => $saleId]);
        $saleItem = $stmt->fetch();
        if (!$saleItem) {
            throw new RuntimeException('Invalid item line.');
        }

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(qty), 0) AS r FROM sales_return_items WHERE sale_item_id = :id');
        $stmt->execute(['id' => $saleItemId]);
        $alreadyReturned = (int) $stmt->fetch()['r'];
        $remaining = (int) $saleItem['qty'] - $alreadyReturned;

        if ($qty > $remaining) {
            throw new RuntimeException("Cannot return {$qty} — only {$remaining} unit(s) remain returnable for this line.");
        }

        $amount = $qty * (float) $saleItem['rate'];
        $refundTotal += $amount;

        $lines[] = [
            'saleItemId' => $saleItemId, 'medicineId' => $saleItem['medicine_id'],
            'batchId' => $saleItem['batch_id'], 'qty' => $qty, 'rate' => $saleItem['rate'], 'amount' => $amount,
        ];
    }

    if (count($lines) === 0) {
        throw new RuntimeException('No valid items to return.');
    }

    $returnId = SalesReturn::create([
        'sale_id'       => $saleId,
        'return_no'     => 'PENDING',
        'return_date'   => date('Y-m-d'),
        'reason'        => $reason,
        'note'          => $note,
        'refund_amount' => $refundTotal,
    ]);
    $returnNo = 'SRN-' . date('y') . '-' . str_pad((string) $returnId, 4, '0', STR_PAD_LEFT);
    SalesReturn::update($returnId, ['return_no' => $returnNo]);

    foreach ($lines as $line) {
        SalesReturnItem::create([
            'return_id'    => $returnId,
            'sale_item_id' => $line['saleItemId'],
            'medicine_id'  => $line['medicineId'],
            'batch_id'     => $line['batchId'],
            'qty'          => $line['qty'],
            'rate'         => $line['rate'],
            'amount'       => $line['amount'],
        ]);

        // Restock — returned stock goes back to the exact batch it was sold from.
        $pdo->prepare('UPDATE batches SET quantity = quantity + :qty WHERE id = :id')
            ->execute(['qty' => $line['qty'], 'id' => $line['batchId']]);
    }

    // If this invoice still had an outstanding due, the return reduces it first.
    $newBalance = max(0, (float) $sale['balance_due'] - $refundTotal);
    Sale::update($saleId, ['balance_due' => $newBalance]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    Json::error($e->getMessage() ?: 'Could not process the return.', 422);
}

require dirname(__DIR__, 2) . '/core/Audit.php';
Audit::log('SALES_RETURN', "{$returnNo} · ₹{$refundTotal} against sale #{$saleId}");

Json::ok(['returnNo' => $returnNo, 'refundAmount' => $refundTotal]);
