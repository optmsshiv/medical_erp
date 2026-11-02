<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
if (!is_file(dirname(__DIR__, 2) . '/core/OfflineEngine.php')) {
    Json::error('Deploy core/OfflineEngine.php alongside this file.', 500);
}
require_once dirname(__DIR__, 2) . '/core/OfflineEngine.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

/**
 * api/v1/offline-sync.php — the ONLY path that turns offline journal entries
 * into real sales. The engine guarantees:
 *
 *   idempotency  every entry has a client_key PRIMARY KEY row claimed BEFORE the
 *                replay starts ('starting'), so a crash/retry can never double-post:
 *                dup rows return their stored verdict; a stale 'starting' flips to
 *                held(needs_review) for a human.
 *   authorization a live, unexpired, un-revoked HMAC ticket for THIS device must
 *                accompany the batch (renewed on every online boot).
 *   single truth  sales are re-created through the store's own /api/v1/sales.php
 *                (internal same-origin replay with the syncing session) — official
 *                invoice numbers, real stock math, real dues. Rejections become
 *                visible holds, never silent losses.
 *
 * → { device, ticket, entries:[{clientKey, kind:'sale', payload, meta}] }  (≤ 50)
 * ← { results:[{clientKey, status:'applied'|'dup'|'held'|'voided', invoiceNo?, reason?}] }
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    Json::error('Method not allowed.', 405);
}

$pdo = Tenant::db();
oeTables($pdo);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) { Json::error('Send the journal batch as JSON.', 422); }

$deviceId = substr(preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($input['device'] ?? '')) ?? '', 0, 32);
$ticket = (string) ($input['ticket'] ?? '');
$entries = (array) ($input['entries'] ?? []);
if ($deviceId === '' || !is_array($entries) || !$entries) { Json::error('Missing device or entries.', 422); }
if (count($entries) > 50) { Json::error('Max 50 entries per batch.', 422); }

$policy = oeSettings($pdo);
if ($policy['offline_enabled'] !== '1') {
    Json::error('Offline billing is disabled for this store — entries were not applied.', 409);
}

$claims = oeVerify(oeSecret($pdo), $ticket, $deviceId);
if ($claims === null) {
    Json::error('Offline ticket invalid or expired — sign in online to renew, entries stay queued.', 403);
}
try {
    $st = $pdo->prepare('SELECT * FROM offline_devices WHERE device_id = ? LIMIT 1');
    $st->execute([$deviceId]);
    $dev = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { Json::error('Device store unavailable: ' . $e->getMessage(), 500); }
if (!$dev || $dev['revoked_at'] !== null) { Json::error('This device is not paired (or was revoked).', 403); }
if ((int) ($claims['v'] ?? 0) !== (int) $dev['ticket_v']) {
    Json::error('Ticket was rotated since this device went offline — sign in online to refresh.', 403);
}

$sessionCookie = session_name() . '=' . session_id();
$results = [];

foreach ($entries as $raw) {
    if (!is_array($raw)) { continue; }
    $key = substr(preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($raw['clientKey'] ?? '')) ?? '', 0, 64);
    if ($key === '') { continue; }
    $kind = (string) ($raw['kind'] ?? 'sale');

    $payloadJson = json_encode(['kind' => $kind, 'meta' => $raw['meta'] ?? null, 'payload' => $raw['payload'] ?? null], JSON_UNESCAPED_UNICODE);

    /* Claim the key atomically. Existing row = replay of a processed entry. */
    try {
        $ins = $pdo->prepare("INSERT IGNORE INTO offline_journal (client_key, device_id, user_id, status, payload) VALUES (?, ?, ?, 'starting', ?)");
        $ins->execute([$key, $deviceId, (int) ($claims['u'] ?? 0), $payloadJson]);
    } catch (Throwable $e) {
        $results[] = ['clientKey' => $key, 'status' => 'held', 'reason' => 'journal write failed: ' . substr($e->getMessage(), 0, 120)];
        continue;
    }

    if ($ins->rowCount() === 0) {
        try {
            $st = $pdo->prepare('SELECT status, invoice_no, hold_reason, applied_at FROM offline_journal WHERE client_key = ?');
            $st->execute([$key]);
            $old = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $old = null; }
        $stt = (string) ($old['status'] ?? 'held');
        if ($stt === 'starting' && strtotime((string) ($old['applied_at'] ?? '')) < time() - 300) {
            $up = $pdo->prepare("UPDATE offline_journal SET status = 'held', hold_reason = 'crashed mid-replay — needs review' WHERE client_key = ?");
            $up->execute([$key]);
            $results[] = ['clientKey' => $key, 'status' => 'held', 'reason' => 'needs review (previous attempt crashed)'];
            continue;
        }
        $results[] = ['clientKey' => $key, 'status' => $stt === 'applied' ? 'dup' : $stt,
                      'invoiceNo' => (string) ($old['invoice_no'] ?? ''), 'reason' => (string) ($old['hold_reason'] ?? '')];
        continue;
    }

    if ($kind !== 'sale') {
        $up = $pdo->prepare("UPDATE offline_journal SET status = 'voided', hold_reason = 'unknown kind' WHERE client_key = ?");
        $up->execute([$key]);
        $results[] = ['clientKey' => $key, 'status' => 'voided'];
        continue;
    }

    $res = oeApply($pdo, ['payload' => (string) json_encode($raw['payload'] ?? null, JSON_UNESCAPED_UNICODE)], false, $sessionCookie);
    if ($res['status'] === 'applied') {
        $up = $pdo->prepare("UPDATE offline_journal SET status = 'applied', invoice_no = ?, hold_reason = NULL WHERE client_key = ?");
        $up->execute([(string) ($res['invoice_no'] ?? ''), $key]);
        $results[] = ['clientKey' => $key, 'status' => 'applied', 'invoiceNo' => (string) ($res['invoice_no'] ?? '')];
    } else {
        $up = $pdo->prepare("UPDATE offline_journal SET status = 'held', hold_reason = ? WHERE client_key = ?");
        $up->execute([substr((string) ($res['reason'] ?? 'held'), 0, 180), $key]);
        $results[] = ['clientKey' => $key, 'status' => 'held', 'reason' => (string) ($res['reason'] ?? 'held')];
    }
}

try {
    $up = $pdo->prepare('UPDATE offline_devices SET last_seen = NOW() WHERE device_id = ?');
    $up->execute([$deviceId]);
    if (class_exists('Audit')) { Audit::log('OFFLINE_SYNC', "{$deviceId}: " . count($results) . ' entries (' . implode(', ', array_count_values(array_column($results, 'status'))) . ')'); }
} catch (Throwable $e) { }

Json::ok(['data' => ['results' => $results, 'serverNow' => date('c')]]);
