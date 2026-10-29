<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
if (!is_file(dirname(__DIR__, 2) . '/core/WaSend.php')) {
    Json::error('Deploy core/WaSend.php alongside this file (WhatsApp shared sender).', 500);
}
require_once dirname(__DIR__, 2) . '/core/WaSend.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

/**
 * Tenant WhatsApp management — the data behind whatsapp.php.
 *
 *   GET                                  → platform status + customer numbers + send log
 *   POST {action:'set_number', customerId, whatsapp}
 *                                        → store/clear a customer's WhatsApp number
 *                                          (the reminder sends to THIS when set)
 *   POST {action:'test', customerId}     → sample send to the stored number (kind wa_test)
 *
 * The connection itself (token/phone_id/templates/on-off) is platform-level and
 * read-only here by design — the portal's WhatsApp page is the single switchboard.
 * Test sends are capped at 5 per number per day so a curious clerk can't burn the
 * business number with Meta.
 */

$pdo = Tenant::db();
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

/** customers.whatsapp must exist to be useful — provision it when DDL allows. */
function wmEnsureNumberColumn(PDO $pdo): bool
{
    try {
        if ($pdo->query("SHOW COLUMNS FROM customers LIKE 'whatsapp'")->fetch()) { return true; }
        $pdo->exec('ALTER TABLE customers ADD COLUMN whatsapp VARCHAR(20) NULL');
        return (bool) $pdo->query("SHOW COLUMNS FROM customers LIKE 'whatsapp'")->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

$master = null; $masterErr = '';
try {
    $master = Database::master();
} catch (Throwable $e) {
    $masterErr = 'Control plane unreachable — status and send log unavailable.';
}

if ($method === 'GET') {
    $hasWa = wmEnsureNumberColumn($pdo);

    $q = trim((string) ($_GET['q'] ?? ''));
    $q = substr(preg_replace('/[^\p{L}\p{N} +.\-@()&\/]/u', '', $q) ?? '', 0, 60);
    $like = '%' . $q . '%';
    try {
        if ($hasWa) {
            $sql = "SELECT id, name, COALESCE(phone,'') AS phone, COALESCE(whatsapp,'') AS whatsapp
                    FROM customers WHERE name LIKE ? OR phone LIKE ? OR whatsapp LIKE ?
                    ORDER BY name ASC LIMIT 400";
        } else {
            $sql = "SELECT id, name, COALESCE(phone,'') AS phone, '' AS whatsapp
                    FROM customers WHERE name LIKE ? OR phone LIKE ?
                    ORDER BY name ASC LIMIT 400";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($hasWa ? [$like, $like, $like] : [$like, $like]);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        Json::error('Customer read failed: ' . $e->getMessage(), 500);
    }

    $status = ['configured' => false, 'on' => false, 'phone_id' => '', 'ver' => '', 'lang' => '', 'cc' => '91',
               'tpl_alert' => '', 'token_mask' => '', 'error' => $masterErr];
    $log = []; $stats = ['today_reminders' => 0, 'sent_7d' => 0, 'failed_7d' => 0];
    $client = null;
    if ($master !== null) {
        $cfg = WaSend::cfg($master);
        $status = [
            'configured' => $cfg['enabled'] === '1' && ($cfg['token'] !== '' || $cfg['phone_id'] !== ''),
            'on' => (bool) $cfg['on'],
            'phone_id' => $cfg['phone_id'],
            'ver' => $cfg['ver'],
            'lang' => $cfg['lang'],
            'cc' => $cfg['cc'],
            'tpl_alert' => $cfg['tpl_alert'],
            'token_mask' => $cfg['token'] !== '' ? '••••' . substr($cfg['token'], -4) : '',
            'error' => $cfg['on'] ? '' : 'Turn WhatsApp on (and save credentials) on the portal first.',
        ];
        $client = WaSend::clientId($master);
        if ($client !== null) {
            try {
                WaSend::ensure($master);
                $stmt = $master->prepare('SELECT to_number, kind, via, ok, error, created_at
                                          FROM wa_log WHERE client_id = ? ORDER BY id DESC LIMIT 50');
                $stmt->execute([$client]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $log[] = [
                        'to' => '+' . WaSend::mask((string) $r['to_number']),
                        'kind' => (string) $r['kind'],
                        'via' => (string) $r['via'],
                        'ok' => (int) $r['ok'] === 1,
                        'error' => (string) ($r['error'] ?? ''),
                        'at' => (string) ($r['created_at'] ?? ''),
                    ];
                }
                $stmt = $master->prepare("SELECT
                        SUM(kind = 'due_reminder' AND created_at >= CURDATE()) AS today_rem,
                        SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS sent7,
                        SUM(ok = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS fail7
                    FROM wa_log WHERE client_id = ?");
                $stmt->execute([$client]);
                $s = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $stats = ['today_reminders' => (int) ($s['today_rem'] ?? 0),
                          'sent_7d' => (int) ($s['sent7'] ?? 0), 'failed_7d' => (int) ($s['fail7'] ?? 0)];
            } catch (Throwable $e) { /* log is a bonus — never sink the page */ }
        }
    }

    $store = '';
    try {
        $store = trim((string) ($pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'store_name'")->fetchColumn() ?: ''));
    } catch (Throwable $e) { }

    Json::ok(['data' => [
        'store' => $store,
        'hasNumberColumn' => $hasWa,
        'canEdit' => $hasWa,
        'status' => $status,
        'stats' => $stats,
        'log' => $log,
        'logScoped' => true,
        'customers' => array_map(function ($r) {
            return ['id' => (int) $r['id'], 'name' => (string) $r['name'],
                    'phone' => (string) $r['phone'], 'whatsapp' => (string) $r['whatsapp']];
        }, $customers),
    ]]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) { $input = []; }
    $action = (string) ($input['action'] ?? '');
    $customerId = (int) ($input['customerId'] ?? 0);
    if ($customerId <= 0) { Json::error('Missing customer.', 422); }

    try {
        $stmt = $pdo->prepare('SELECT id, name, phone FROM customers WHERE id = ?');
        $stmt->execute([$customerId]);
        $cust = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        Json::error('Customer lookup failed: ' . $e->getMessage(), 500);
    }
    if (!$cust) { Json::error('Customer not found.', 404); }

    if ($action === 'set_number') {
        if (!wmEnsureNumberColumn($pdo)) {
            Json::error('The database user needs ALTER rights on `customers` once (to add the whatsapp column).', 500);
        }
        $raw = trim((string) ($input['whatsapp'] ?? ''));
        $cc = '91';
        if ($master !== null) { $cc = (string) (WaSend::cfg($master)['cc'] ?: '91'); }
        $num = $raw === '' ? '' : WaSend::norm($raw, $cc);
        if ($raw !== '' && (strlen($num) < 10 || strlen($num) > 15 || !ctype_digit($num))) {
            Json::error('That does not look like a WhatsApp number (10 digits, with or without +91).', 422);
        }
        try {
            $stmt = $pdo->prepare('UPDATE customers SET whatsapp = ? WHERE id = ?');
            $stmt->execute([$num !== '' ? $num : null, $customerId]);
        } catch (Throwable $e) {
            Json::error('Could not save the number: ' . $e->getMessage(), 500);
        }
        Json::ok(['data' => ['saved' => true, 'whatsapp' => $num]]);
    }

    if ($action === 'test') {
        if ($master === null) { Json::error($masterErr, 503); }
        $cfg = WaSend::cfg($master);
        if (!$cfg['on']) { Json::error('WhatsApp is not configured (or is switched off) on the platform.', 503); }
        $cc = $cfg['cc'] !== '' ? $cfg['cc'] : '91';
        $to = (string) ($cust['phone'] ?? '');
        try {
            if ($pdo->query("SHOW COLUMNS FROM customers LIKE 'whatsapp'")->fetch()) {
                $stmt = $pdo->prepare('SELECT whatsapp FROM customers WHERE id = ?');
                $stmt->execute([$customerId]);
                $w = trim((string) ($stmt->fetchColumn() ?: ''));
                if ($w !== '') { $to = $w; }
            }
        } catch (Throwable $e) { }
        $num = WaSend::norm($to, $cc);
        if ($num === '') { Json::error('This customer has no phone number to test with.', 422); }
        if (WaSend::todayCount($master, $num, WaSend::KIND_TEST) >= WaSend::TESTS_PER_NUM_PER_DAY) {
            Json::error('Test limit reached for this number today (max 5 per day).', 429);
        }
        $store = 'your pharmacy';
        try {
            $v = trim((string) ($pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'store_name'")->fetchColumn() ?: ''));
            if ($v !== '') { $store = $v; }
        } catch (Throwable $e) { }
        $msg = 'WhatsApp due reminders are live for ' . $store . '. This is a test — real reminders will show your outstanding dues here.';
        $res = WaSend::send($master, WaSend::clientId($master), $num, WaSend::KIND_TEST,
                            [$store, 'Test message: due reminders are set up correctly. No dues attached.', date('d/m/Y')],
                            'Hi ' . trim((string) preg_replace('/\s+/', ' ', (string) $cust['name'])) . '! ' . $msg);
        if (empty($res['ok'])) {
            Json::error('Meta rejected the test: ' . (isset($res['error']) ? $res['error'] : 'unknown error'), 502);
        }
        Json::ok(['data' => ['sent' => true, 'to' => '+' . WaSend::mask($num), 'via' => $res['via']]]);
    }

    Json::error('Unknown action.', 422);
}

Json::error('Method not allowed.', 405);
