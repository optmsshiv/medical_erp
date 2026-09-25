<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Medicine.php';
require dirname(__DIR__, 2) . '/models/Batch.php';
require dirname(__DIR__, 2) . '/models/Counter.php';
require dirname(__DIR__, 2) . '/models/StockTransfer.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // ?list=counters returns the counter dropdown options.
        // Default: recent transfers, newest first.
        if (($_GET['list'] ?? '') === 'counters') {
            Json::ok(['data' => Counter::all('name')]);
            break;
        }
        $rows = StockTransfer::query('SELECT * FROM stock_transfers ORDER BY created_at DESC LIMIT 200');
        Json::ok(['data' => $rows]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $medicineId  = (int) ($input['medicineId'] ?? 0);
        $batchId     = (int) ($input['batchId'] ?? 0);
        $qty         = (int) ($input['qty'] ?? 0);
        $fromCounter = (int) ($input['fromCounterId'] ?? 0);
        $toCounter   = (int) ($input['toCounterId'] ?? 0);
        $notes       = trim($input['notes'] ?? '');

        if (!$medicineId || !Medicine::find($medicineId)) {
            Json::error('Medicine not found.', 404);
        }
        $batch = Batch::find($batchId);
        if (!$batch || (int) $batch['medicine_id'] !== $medicineId) {
            Json::error('Batch not found for this medicine.', 404);
        }
        if ($qty <= 0) {
            Json::error('Quantity must be greater than zero.', 422);
        }
        if ($qty > (int) $batch['quantity']) {
            Json::error("Cannot transfer {$qty} units — only {$batch['quantity']} in this batch.", 422);
        }
        if (!$fromCounter || !Counter::find($fromCounter)) {
            Json::error('"From" counter not found.', 404);
        }
        if (!$toCounter || !Counter::find($toCounter)) {
            Json::error('"To" counter not found.', 404);
        }
        if ($fromCounter === $toCounter) {
            Json::error('"From" and "To" counters must be different.', 422);
        }

        // A transfer moves stock between counters at the SAME store — it does
        // NOT change how much the store owns, so batches.quantity is untouched.
        // This is a location log only (see migration notes).
        $id = StockTransfer::create([
            'medicine_id'     => $medicineId,
            'batch_id'        => $batchId,
            'qty'             => $qty,
            'from_counter_id' => $fromCounter,
            'to_counter_id'   => $toCounter,
            'notes'           => $notes,
            'transferred_by'  => null, // TODO: set the logged-in user's id once Auth.php's API is confirmed
                                        //       (other endpoints here don't capture a user id yet either).
        ]);

        Json::ok(['id' => $id]);
        break;

    default:
        Json::error('Method not allowed.', 405);
}
