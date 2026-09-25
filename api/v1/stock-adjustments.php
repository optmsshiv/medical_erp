<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/core/Audit.php';
require dirname(__DIR__, 2) . '/models/Medicine.php';
require dirname(__DIR__, 2) . '/models/StockAdjustment.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$pdo = Tenant::db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query(
        "SELECT sa.*, m.name AS medicine_name, b.batch_no
         FROM stock_adjustments sa
         JOIN medicines m ON m.id = sa.medicine_id
         JOIN batches b ON b.id = sa.batch_id
         ORDER BY sa.created_at DESC, sa.id DESC
         LIMIT 200"
    );
    $rows = array_map(fn($r) => [
        'id' => (int) $r['id'], 'date' => $r['created_at'], 'medicine' => $r['medicine_name'],
        'batch' => $r['batch_no'], 'qtyChange' => (int) $r['qty_change'], 'reason' => $r['reason'],
        'notes' => $r['notes'] ?? '', 'adjustedBy' => $r['adjusted_by'] ?? '',
    ], $stmt->fetchAll());
    Json::ok(['data' => $rows]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $medId    = (int) ($input['medId'] ?? 0);
    $batchId  = (int) ($input['batchId'] ?? 0);
    $qtyChange = (int) ($input['qtyChange'] ?? 0);
    $reason   = $input['reason'] ?? '';
    $notes    = trim($input['notes'] ?? '');

    $validReasons = ['Damage', 'Expired Write-off', 'Theft / Loss', 'Stock Recount', 'Other'];
    if (!$medId || !$batchId) {
        Json::error('Select a medicine and batch.', 422);
    }
    if ($qtyChange === 0) {
        Json::error('Quantity change cannot be zero.', 422);
    }
    if (!in_array($reason, $validReasons, true)) {
        Json::error('Select a valid reason.', 422);
    }
    if (!Medicine::find($medId)) {
        Json::error('Medicine not found.', 404);
    }

    $user = Auth::user();
    $adjustedBy = $user['name'] ?? '';

    try {
        $pdo->beginTransaction();

        // Lock the batch row so a concurrent sale/adjustment can't race this one.
        $stmt = $pdo->prepare('SELECT * FROM batches WHERE id = :id AND medicine_id = :medId FOR UPDATE');
        $stmt->execute(['id' => $batchId, 'medId' => $medId]);
        $batch = $stmt->fetch();
        if (!$batch) {
            throw new RuntimeException('Batch not found for this medicine.');
        }

        // Never let an adjustment drop quantity below what's already reserved for pending sales.
        $stmt = $pdo->prepare(
            'UPDATE batches SET quantity = quantity + :change
             WHERE id = :id AND (quantity + :change2) >= reserved'
        );
        $stmt->execute(['change' => $qtyChange, 'change2' => $qtyChange, 'id' => $batchId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException("That change would drop stock below what's already reserved for pending sales.");
        }

        $adjId = StockAdjustment::create([
            'medicine_id' => $medId, 'batch_id' => $batchId, 'qty_change' => $qtyChange,
            'reason' => $reason, 'notes' => $notes !== '' ? $notes : null, 'adjusted_by' => $adjustedBy,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        Json::error($e->getMessage() ?: 'Could not save the adjustment.', 422);
    }

    Audit::log('STOCK_ADJUSTMENT', "Batch {$batch['batch_no']} · " . ($qtyChange > 0 ? '+' : '') . "{$qtyChange} ({$reason})");
    Json::ok(['id' => $adjId]);
}

Json::error('Method not allowed.', 405);
