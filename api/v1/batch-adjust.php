<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Batch.php';
require dirname(__DIR__, 2) . '/models/StockAdjustment.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Json::error('Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$batchId = (int) ($input['batchId'] ?? 0);
$newQty  = (int) ($input['newQty'] ?? -1);
$reason  = trim($input['reason'] ?? '');
$note    = trim($input['note'] ?? '');

$batch = Batch::find($batchId);
if (!$batch) {
    Json::error('Batch not found.', 404);
}
if ($newQty < 0) {
    Json::error('Adjusted quantity cannot be negative.', 422);
}
if ($reason === '') {
    Json::error('A reason is required.', 422);
}

$oldQty = (int) $batch['quantity'];
$user = Auth::user();

$pdo = Tenant::db();
try {
    $pdo->beginTransaction();

    Batch::update($batchId, ['quantity' => $newQty]);

    StockAdjustment::create([
        'medicine_id'         => $batch['medicine_id'],
        'batch_id'            => $batchId,
        'old_qty'             => $oldQty,
        'new_qty'             => $newQty,
        'reason'              => $reason,
        'note'                => $note,
        'adjusted_by_user_id' => $user['id'] ?? null,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    Json::error('Could not save the adjustment.', 500);
}

require dirname(__DIR__, 2) . '/core/Audit.php';
Audit::log('STOCK_ADJUST', "Batch {$batch['batch_no']}: {$oldQty} → {$newQty} ({$reason})");

Json::ok(['oldQty' => $oldQty, 'newQty' => $newQty]);
