<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Medicine.php';
require dirname(__DIR__, 2) . '/models/Batch.php';
require dirname(__DIR__, 2) . '/models/StockAdjustment.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$allowedReasons = ['Damaged', 'Expired', 'Lost / Theft', 'Recount Correction', 'Other'];

switch ($method) {
    case 'GET':
        // Recent adjustments, newest first (capped at 200), optionally filtered to one medicine.
        $medId = (int) ($_GET['medicineId'] ?? 0);
        $rows = $medId
            ? StockAdjustment::query('SELECT * FROM stock_adjustments WHERE medicine_id = :id ORDER BY created_at DESC LIMIT 200', ['id' => $medId])
            : StockAdjustment::query('SELECT * FROM stock_adjustments ORDER BY created_at DESC LIMIT 200');
        Json::ok(['data' => $rows]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $medicineId = (int) ($input['medicineId'] ?? 0);
        $batchId    = (int) ($input['batchId'] ?? 0);
        $qtyChange  = (int) ($input['qtyChange'] ?? 0);
        $reason     = trim($input['reason'] ?? '');
        $notes      = trim($input['notes'] ?? '');

        if (!$medicineId || !Medicine::find($medicineId)) {
            Json::error('Medicine not found.', 404);
        }
        $batch = Batch::find($batchId);
        if (!$batch || (int) $batch['medicine_id'] !== $medicineId) {
            Json::error('Batch not found for this medicine.', 404);
        }
        if ($qtyChange === 0) {
            Json::error('Quantity change cannot be zero.', 422);
        }
        if (!in_array($reason, $allowedReasons, true)) {
            Json::error('Invalid reason.', 422);
        }

        $qtyBefore = (int) $batch['quantity'];
        $qtyAfter  = $qtyBefore + $qtyChange;
        if ($qtyAfter < 0) {
            Json::error("Cannot remove {$qtyChange} units — only {$qtyBefore} in this batch.", 422);
        }

        // Update the batch's real stock, then log the change. Not wrapped in an
        // explicit transaction here because Model's methods each open their own
        // statement; if your PDO connection supports it, wrap both calls in
        // Tenant::db()->beginTransaction()/commit() for atomicity.
        Batch::update($batchId, ['quantity' => $qtyAfter]);

        $id = StockAdjustment::create([
            'medicine_id' => $medicineId,
            'batch_id'    => $batchId,
            'qty_change'  => $qtyChange,
            'qty_before'  => $qtyBefore,
            'qty_after'   => $qtyAfter,
            'reason'      => $reason,
            'notes'       => $notes,
            'adjusted_by' => null, // TODO: set the logged-in user's id once Auth.php's API is confirmed
                                   //       (other endpoints here don't capture a user id yet either).
        ]);

        Json::ok(['id' => $id, 'qtyAfter' => $qtyAfter]);
        break;

    default:
        Json::error('Method not allowed.', 405);
}
