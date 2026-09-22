<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Purchase.php';
require dirname(__DIR__, 2) . '/models/PurchaseReturn.php';
require dirname(__DIR__, 2) . '/models/PurchaseReturnItem.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Json::error('Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$purchaseId = (int) ($input['purchaseId'] ?? 0);
$reason     = trim($input['reason'] ?? 'Other');
$note       = trim($input['note'] ?? '');
$items      = $input['items'] ?? [];

$purchase = Purchase::find($purchaseId);
if (!$purchase) {
    Json::error('GRN not found.', 404);
}
if (!is_array($items) || count($items) === 0) {
    Json::error('Select at least one item to return.', 422);
}

$pdo = Tenant::db();

try {
    $pdo->beginTransaction();

    $lines = [];
    $creditTotal = 0.0;

    foreach ($items as $item) {
        $purchaseItemId = (int) ($item['purchaseItemId'] ?? 0);
        $qty = (int) ($item['qty'] ?? 0);
        if (!$purchaseItemId || $qty <= 0) {
            continue;
        }

        $stmt = $pdo->prepare('SELECT * FROM purchase_items WHERE id = :id AND purchase_id = :purchaseId FOR UPDATE');
        $stmt->execute(['id' => $purchaseItemId, 'purchaseId' => $purchaseId]);
        $purchaseItem = $stmt->fetch();
        if (!$purchaseItem) {
            throw new RuntimeException('Invalid item line.');
        }

        // Lock the batch too — we need its CURRENT stock, which may be lower
        // than what was originally purchased if some has already been sold.
        $stmt = $pdo->prepare('SELECT * FROM batches WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $purchaseItem['batch_id']]);
        $batch = $stmt->fetch();
        if (!$batch) {
            throw new RuntimeException('Batch no longer exists.');
        }

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(qty), 0) AS r FROM purchase_return_items WHERE purchase_item_id = :id');
        $stmt->execute(['id' => $purchaseItemId]);
        $alreadyReturned = (int) $stmt->fetch()['r'];

        $purchased = (int) $purchaseItem['qty'] + (int) $purchaseItem['free_qty'];
        $remainingToReturn = $purchased - $alreadyReturned;
        $availableInBatch = (int) $batch['quantity'] - (int) $batch['reserved'];
        $maxReturnable = min($remainingToReturn, $availableInBatch);

        if ($qty > $maxReturnable) {
            throw new RuntimeException(
                "Cannot return {$qty} — only {$maxReturnable} unit(s) returnable " .
                "(remaining from purchase: {$remainingToReturn}, currently in stock: {$availableInBatch})."
            );
        }

        $amount = $qty * (float) $purchaseItem['rate'];
        $creditTotal += $amount;

        $lines[] = [
            'purchaseItemId' => $purchaseItemId, 'medicineId' => $purchaseItem['medicine_id'],
            'batchId' => $purchaseItem['batch_id'], 'qty' => $qty, 'rate' => $purchaseItem['rate'], 'amount' => $amount,
        ];
    }

    if (count($lines) === 0) {
        throw new RuntimeException('No valid items to return.');
    }

    $returnId = PurchaseReturn::create([
        'purchase_id'   => $purchaseId,
        'return_no'     => 'PENDING',
        'return_date'   => date('Y-m-d'),
        'reason'        => $reason,
        'note'          => $note,
        'credit_amount' => $creditTotal,
    ]);
    $returnNo = 'PRN-' . date('y') . '-' . str_pad((string) $returnId, 4, '0', STR_PAD_LEFT);
    PurchaseReturn::update($returnId, ['return_no' => $returnNo]);

    foreach ($lines as $line) {
        PurchaseReturnItem::create([
            'return_id'        => $returnId,
            'purchase_item_id' => $line['purchaseItemId'],
            'medicine_id'      => $line['medicineId'],
            'batch_id'         => $line['batchId'],
            'qty'              => $line['qty'],
            'rate'             => $line['rate'],
            'amount'           => $line['amount'],
        ]);

        // Stock goes back to the supplier — deduct from the batch.
        $stmt = $pdo->prepare(
            'UPDATE batches SET quantity = quantity - :qty WHERE id = :id AND (quantity - reserved) >= :qty2'
        );
        $stmt->execute(['qty' => $line['qty'], 'id' => $line['batchId'], 'qty2' => $line['qty']]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Stock changed for one of the batches — please retry.');
        }
    }

    // Credit note reduces what's owed to the supplier first, if anything is.
    $newBalance = max(0, (float) $purchase['balance_due'] - $creditTotal);
    Purchase::update($purchaseId, ['balance_due' => $newBalance]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    Json::error($e->getMessage() ?: 'Could not process the return.', 422);
}

require dirname(__DIR__, 2) . '/core/Audit.php';
Audit::log('PURCHASE_RETURN', "{$returnNo} · ₹{$creditTotal} against purchase #{$purchaseId}");

Json::ok(['returnNo' => $returnNo, 'creditAmount' => $creditTotal]);
