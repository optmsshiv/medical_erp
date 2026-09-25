<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/core/Audit.php';
require dirname(__DIR__, 2) . '/models/Medicine.php';
require dirname(__DIR__, 2) . '/models/Location.php';
require dirname(__DIR__, 2) . '/models/StockTransfer.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$pdo = Tenant::db();
$action = $_GET['action'] ?? '';

// --- Locations (list / add) --------------------------------------------------
if ($action === 'locations') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $rows = $pdo->query('SELECT id, name FROM locations WHERE is_active = 1 ORDER BY name')->fetchAll();
        Json::ok(['data' => array_map(fn($r) => ['id' => (int) $r['id'], 'name' => $r['name']], $rows)]);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($input['name'] ?? '');
        if ($name === '') {
            Json::error('Location name is required.', 422);
        }
        $existing = Location::first('name', '=', $name);
        if ($existing) {
            Json::ok(['id' => (int) $existing['id']]);
        }
        $id = Location::create(['name' => $name]);
        Audit::log('LOCATION_CREATE', $name);
        Json::ok(['id' => $id]);
    }
    Json::error('Method not allowed.', 405);
}

// --- Transfers (list / create / cancel) -------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query(
        "SELECT st.*, m.name AS medicine_name, b.batch_no, fl.name AS from_name, tl.name AS to_name
         FROM stock_transfers st
         JOIN medicines m ON m.id = st.medicine_id
         JOIN batches b ON b.id = st.batch_id
         JOIN locations fl ON fl.id = st.from_location_id
         JOIN locations tl ON tl.id = st.to_location_id
         ORDER BY st.created_at DESC, st.id DESC
         LIMIT 200"
    );
    $rows = array_map(fn($r) => [
        'id' => (int) $r['id'], 'date' => $r['created_at'], 'medicine' => $r['medicine_name'], 'batch' => $r['batch_no'],
        'from' => $r['from_name'], 'to' => $r['to_name'], 'qty' => (int) $r['qty'], 'status' => $r['status'],
        'notes' => $r['notes'] ?? '', 'transferredBy' => $r['transferred_by'] ?? '',
    ], $stmt->fetchAll());
    Json::ok(['data' => $rows]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // Cancel an existing transfer (log-only — nothing to physically reverse).
    if (!empty($input['cancelId'])) {
        $id = (int) $input['cancelId'];
        if (!StockTransfer::find($id)) {
            Json::error('Transfer not found.', 404);
        }
        StockTransfer::update($id, ['status' => 'cancelled']);
        Audit::log('STOCK_TRANSFER_CANCEL', "Transfer #{$id}");
        Json::ok();
    }

    $medId    = (int) ($input['medId'] ?? 0);
    $batchId  = (int) ($input['batchId'] ?? 0);
    $fromId   = (int) ($input['fromLocationId'] ?? 0);
    $toId     = (int) ($input['toLocationId'] ?? 0);
    $qty      = (int) ($input['qty'] ?? 0);
    $notes    = trim($input['notes'] ?? '');

    if (!$medId || !$batchId) {
        Json::error('Select a medicine and batch.', 422);
    }
    if ($qty <= 0) {
        Json::error('Quantity must be greater than zero.', 422);
    }
    if (!$fromId || !$toId || $fromId === $toId) {
        Json::error('Select two different locations.', 422);
    }
    if (!Medicine::find($medId)) {
        Json::error('Medicine not found.', 404);
    }

    // Sanity check only — this is a movement log, not a per-location stock
    // split, so we just confirm the batch's total pool can plausibly cover
    // the qty being moved. See the migration's comment on stock_transfers.
    $stmt = $pdo->prepare('SELECT * FROM batches WHERE id = :id AND medicine_id = :medId');
    $stmt->execute(['id' => $batchId, 'medId' => $medId]);
    $batch = $stmt->fetch();
    if (!$batch) {
        Json::error('Batch not found for this medicine.', 404);
    }
    $available = (int) $batch['quantity'] - (int) $batch['reserved'];
    if ($qty > $available) {
        Json::error("Only {$available} unit(s) of this batch exist — can't transfer more than that.", 422);
    }

    $user = Auth::user();
    $id = StockTransfer::create([
        'medicine_id' => $medId, 'batch_id' => $batchId,
        'from_location_id' => $fromId, 'to_location_id' => $toId, 'qty' => $qty,
        'notes' => $notes !== '' ? $notes : null, 'transferred_by' => $user['name'] ?? '',
    ]);

    Audit::log('STOCK_TRANSFER', "Batch {$batch['batch_no']} · {$qty} unit(s)");
    Json::ok(['id' => $id]);
}

Json::error('Method not allowed.', 405);
