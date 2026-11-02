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
 * Offline-billing control API (tenant scope).
 *
 *   GET  ?d=<deviceId>            → policy (+ fresh ticket for a paired, active device;
 *                                    this is the "renew" that online boots perform)
 *   POST {action:'pair', deviceId, salt, pinHash, label?}
 *                                 → register this counter for offline use (owner/admin only)
 *   POST {action:'set_policy', enabled, maxBill, creditOk, snapMaxH, priceTol}
 *   POST {action:'rotate_pin', deviceId, salt, pinHash}
 *   POST {action:'revoke'|'unpair', deviceId}
 *   POST {action:'approve_hold', clientKey}   → force-apply a held entry
 *   POST {action:'void_hold', clientKey}      → discard a held entry (record kept, voided)
 *   GET  ?admin=1&d=<deviceId>    → devices + hold queue + journal stats for the admin page
 *
 * PINs never reach the server: the client derives PBKDF2 locally and only the
 * hash+salt are stored, inside the signed ticket envelope.
 */

$pdo = Tenant::db();
oeTables($pdo);
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$me = function_exists('Auth::user') ? (array) (Auth::user() ?: []) : [];
$role = strtolower((string) ($me['role'] ?? ''));
$isPriv = ($role === '' || $me['privileged'] === true || preg_match('/owner|admin/', $role));

function oePolicy(PDO $pdo): array
{
    $s = oeSettings($pdo);
    return [
        'enabled' => $s['offline_enabled'] === '1',
        'maxBill' => round((float) $s['offline_max_bill'], 2),
        'creditOk' => $s['offline_credit_ok'] === '1',
        'snapMaxH' => max(6, (int) $s['offline_snap_max_h']),
        'priceTol' => max(0, (float) $s['offline_price_tol']),
    ];
}

function oePinShape(string $salt, string $hash): bool
{
    return preg_match('/^[a-f0-9]{32}$/i', $salt) === 1 && preg_match('/^[a-f0-9]{64}$/i', $hash) === 1;
}

if ($method === 'GET') {
    $policy = oePolicy($pdo);
    $deviceId = substr(preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($_GET['d'] ?? '')) ?? '', 0, 32);

    if (!empty($_GET['admin'])) {
        if (!$isPriv) { Json::error('Owner or admin only.', 403); }
        $devices = []; $holds = []; $stats = ['applied' => 0, 'held' => 0, 'voided' => 0];
        try {
            foreach ($pdo->query('SELECT device_id, user_id, role, ticket_v, revoked_at, last_seen, created_at FROM offline_devices ORDER BY last_seen DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC) as $d) {
                $devices[] = array_merge($d, ['revoked' => $d['revoked_at'] !== null]);
            }
        } catch (Throwable $e) { }
        try {
            foreach ($pdo->query("SELECT client_key, device_id, hold_reason, applied_at FROM offline_journal WHERE status = 'held' ORDER BY applied_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC) as $h) {
                $holds[] = $h;
            }
            foreach ($pdo->query('SELECT status, COUNT(*) n FROM offline_journal GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $srow) {
                $stats[$srow['status']] = (int) $srow['n'];
            }
        } catch (Throwable $e) { }
        Json::ok(['data' => ['policy' => $policy, 'devices' => $devices, 'holds' => $holds, 'stats' => $stats]]);
    }

    $ticket = null;
    if ($deviceId !== '' && $policy['enabled']) {
        try {
            $st = $pdo->prepare('SELECT * FROM offline_devices WHERE device_id = ? LIMIT 1');
            $st->execute([$deviceId]);
            $dev = $st->fetch(PDO::FETCH_ASSOC);
            if ($dev && $dev['revoked_at'] === null && $dev['pin_hash'] !== '') {
                $exp = time() + 86400;
                $v = (int) $dev['ticket_v'] + 1;
                $ticket = oeSign(oeSecret($pdo), [
                    'd' => $deviceId, 'u' => (int) $dev['user_id'], 'r' => (string) $dev['role'],
                    's' => (string) $dev['salt'], 'h' => (string) $dev['pin_hash'], 'v' => $v,
                    'exp' => $exp, 'mb' => $policy['maxBill'], 'cc' => $policy['creditOk'] ? 1 : 0,
                ]);
                $up = $pdo->prepare('UPDATE offline_devices SET ticket_v = ?, last_seen = NOW() WHERE device_id = ?');
                $up->execute([$v, $deviceId]);
            }
        } catch (Throwable $e) { }
    }
    Json::ok(['data' => ['policy' => $policy, 'ticket' => $ticket, 'serverNow' => date('c')]]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) { $input = []; }
    $action = (string) ($input['action'] ?? '');

    if (!in_array($action, ['approve_hold', 'void_hold'], true) && !$isPriv) {
        Json::error('Owner or admin only.', 403);
    }

    if ($action === 'pair' || $action === 'rotate_pin') {
        $deviceId = substr(preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($input['deviceId'] ?? '')) ?? '', 0, 32);
        $salt = strtolower(trim((string) ($input['salt'] ?? '')));
        $hash = strtolower(trim((string) ($input['pinHash'] ?? '')));
        if ($deviceId === '') {
            Json::error('No device id received \u2014 the till page could not create one (IndexedDB blocked? Use a normal browser window, not private/incognito).', 422);
        }
        if (!preg_match('/^[a-f0-9]{32}$/i', $salt)) {
            Json::error('Malformed pairing salt \u2014 browser WebCrypto problem on this device.', 422);
        }
        if (!preg_match('/^[a-f0-9]{64}$/i', $hash)) {
            Json::error('PIN hash is empty or malformed \u2014 PBKDF2 failed in this browser (needs https:// + Chrome/Edge/Firefox; private windows and old Android WebViews can block WebCrypto).', 422);
        }
        if ($action === 'pair') {
            $label = substr(preg_replace('/[^\p{L}\p{N} \-_.]/u', '', (string) ($input['label'] ?? 'Counter')) ?? '', 0, 40);
            $st = $pdo->prepare('INSERT INTO offline_devices (device_id, user_id, role, salt, pin_hash, created_at)
                                 VALUES (?, ?, ?, ?, ?, NOW())
                                 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), role = VALUES(role),
                                     salt = VALUES(salt), pin_hash = VALUES(pin_hash), revoked_at = NULL');
            $st->execute([$deviceId, (int) ($me['id'] ?? 0), $role !== '' ? substr($role, 0, 24) : 'owner', $salt, $hash]);
            oeAudit('OFFLINE_PAIR', 'Device paired: ' . $label . ' (' . $deviceId . ')');
            Json::ok(['data' => ['paired' => true, 'deviceId' => $deviceId]]);
        }
        $st = $pdo->prepare('UPDATE offline_devices SET salt = ?, pin_hash = ?, ticket_v = ticket_v + 1, revoked_at = NULL WHERE device_id = ?');
        $st->execute([$salt, $hash, $deviceId]);
        if ($st->rowCount() === 0) { Json::error('Device not paired yet.', 404); }
        oeAudit('OFFLINE_ROTATE_PIN', 'PIN rotated for ' . $deviceId);
        Json::ok(['data' => ['rotated' => true]]);
    }

    if ($action === 'set_policy') {
        $vals = [
            'offline_enabled' => !empty($input['enabled']) ? '1' : '0',
            'offline_max_bill' => (string) max(0, round((float) ($input['maxBill'] ?? 5000), 2)),
            'offline_credit_ok' => !empty($input['creditOk']) ? '1' : '0',
            'offline_snap_max_h' => (string) min(336, max(6, (int) ($input['snapMaxH'] ?? 72))),
            'offline_price_tol' => (string) min(50, max(0, (float) ($input['priceTol'] ?? 2))),
        ];
        $st = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2');
        foreach ($vals as $k => $v) { $st->execute(['k' => $k, 'v' => $v, 'v2' => $v]); }
        oeAudit('OFFLINE_POLICY', 'enabled=' . $vals['offline_enabled'] . ' max=' . $vals['offline_max_bill'] . ' credit=' . $vals['offline_credit_ok']);
        Json::ok(['data' => ['saved' => true, 'policy' => oePolicy($pdo)]]);
    }

    if ($action === 'revoke' || $action === 'unpair') {
        $deviceId = substr(preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($input['deviceId'] ?? '')) ?? '', 0, 32);
        if ($deviceId === '') { Json::error('Missing device.', 422); }
        if ($action === 'unpair') {
            $st = $pdo->prepare('DELETE FROM offline_devices WHERE device_id = ?');
            $st->execute([$deviceId]);
        } else {
            $st = $pdo->prepare('UPDATE offline_devices SET revoked_at = NOW(), ticket_v = ticket_v + 1 WHERE device_id = ?');
            $st->execute([$deviceId]);
        }
        oeAudit('OFFLINE_' . strtoupper($action), $deviceId);
        Json::ok(['data' => ['done' => true]]);
    }

    if ($action === 'approve_hold' || $action === 'void_hold') {
        $key = substr(preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($input['clientKey'] ?? '')) ?? '', 0, 64);
        $st = $pdo->prepare("SELECT * FROM offline_journal WHERE client_key = ? AND status = 'held' LIMIT 1");
        $st->execute([$key]);
        $entry = $st->fetch(PDO::FETCH_ASSOC);
        if (!$entry) { Json::error('That hold is gone — it may have been cleared from another tab.', 404); }
        if ($action === 'void_hold') {
            $up = $pdo->prepare("UPDATE offline_journal SET status = 'voided', hold_reason = CONCAT('voided by ', ?) WHERE client_key = ?");
            $up->execute(['owner', $key]);
            oeAudit('OFFLINE_HOLD_VOID', $key);
            Json::ok(['data' => ['voided' => true]]);
        }
        $cookie = session_name() . '=' . session_id();
        $res = oeApply($pdo, $entry, true, $cookie);
        if ($res['status'] === 'applied') {
            $up = $pdo->prepare("UPDATE offline_journal SET status = 'applied', invoice_no = ?, hold_reason = NULL WHERE client_key = ?");
            $up->execute([(string) ($res['invoice_no'] ?? ''), $key]);
            oeAudit('OFFLINE_HOLD_APPROVED', $key . ' → ' . (string) ($res['invoice_no'] ?? '?'));
            Json::ok(['data' => ['applied' => true, 'invoiceNo' => (string) ($res['invoice_no'] ?? '')]]);
        }
        $up = $pdo->prepare('UPDATE offline_journal SET hold_reason = ? WHERE client_key = ?');
        $up->execute([(string) ($res['reason'] ?? 'still held'), $key]);
        Json::error('Still held: ' . (string) ($res['reason'] ?? 'engine said no'), 409);
    }

    Json::error('Unknown action.', 422);
}

function oeAudit(string $action, string $msg): void
{
    try { if (class_exists('Audit')) { Audit::log($action, $msg); } } catch (Throwable $e) { }
}

Json::error('Method not allowed.', 405);
