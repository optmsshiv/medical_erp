<?php
/** Per-client feature entitlement matrix.
 *  GET  ?client_id=N → JSON {features:{key:bool}}   (used to populate the modal)
 *  POST client_id + matrix → persists, audits only the deltas. */
require __DIR__ . '/_lib.php';

$id     = (int) ($_REQUEST['client_id'] ?? 0);
$client = findClient($id);
if (!$client) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') { header('Content-Type: application/json'); echo json_encode(['error' => 'Client not found.']); }
    else { flashErr('Client not found.'); }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    echo json_encode([
        'client'   => ['id' => $id, 'name' => (string) ($client['name'] ?? '')],
        'features' => clientFeaturesOn($id),
    ]);
    exit;
}

// POST — persist the matrix.
$before = clientFeaturesOn($id);
$after  = [];
$changed = [];
foreach (clientFeatureKeys() as $key => $label) {
    $after[$key] = posted('f_' . $key);                 // unchecked boxes simply don't post; mangle-proof presence check
    $allowed = $after[$key] ? 1 : 0;
    master()->query(
        'INSERT INTO client_features (client_id, feature, allowed) VALUES ('
        . $id . ', ' . master()->quote($key) . ", {$allowed}) "
        . 'ON DUPLICATE KEY UPDATE allowed = ' . $allowed
    );
    if (($before[$key] ?? true) !== $after[$key]) {
        $changed[] = $key . '=' . ($after[$key] ? 'on' : 'off');
    }
}
$msg = 'Feature set saved for “' . ($client['name'] ?? '') . '”.';
if ($changed) {
    audit('FEATURES_SET', $id, ($client['name'] ?? '') . ': ' . implode(', ', $changed));
    $msg .= ' Changes: ' . implode(', ', $changed) . '.';
} else {
    $msg .= ' No changes.';
}
flashOk($msg);
back();
