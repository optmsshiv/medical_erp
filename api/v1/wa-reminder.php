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
 * WhatsApp due-reminder — one send, one customer, one open balance.
 * Engine + config + log live with the platform (core/WaSend.php).
 *
 * Safety by construction:
 *  - the number is read from the customers row (whatsapp column when present,
 *    phone otherwise) — the browser never supplies a number;
 *  - dues come from the ledger (bill balances minus receipts — the same math
 *    the dues pages show), so nobody can remind a settled account;
 *  - max 3 reminders per number per day, counted in wa_log.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Json::error('Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) { $input = []; }
$customerId = (int) ($input['customerId'] ?? 0);
if ($customerId <= 0) {
    Json::error('Missing customer.', 422);
}

$pdo = Tenant::db();

try {
    $stmt = $pdo->prepare('SELECT id, name, phone FROM customers WHERE id = ?');
    $stmt->execute([$customerId]);
    $cust = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    Json::error('Customer lookup failed: ' . $e->getMessage(), 500);
}
if (!$cust) {
    Json::error('Customer not found.', 404);
}

/* Dues exactly as the ledger sees them: open bill balances minus all receipts. */
try {
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(balance_due),0) AS bal,
                                  SUM(CASE WHEN balance_due > 0 THEN 1 ELSE 0 END) AS open_bills,
                                  MIN(CASE WHEN balance_due > 0 THEN sale_date END) AS oldest_open
                           FROM sales WHERE customer_id = ?');
    $stmt->execute([$customerId]);
    $b = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['bal' => 0, 'open_bills' => 0, 'oldest_open' => null];
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS pays FROM payments WHERE party_type = 'customer' AND party_id = ?");
    $stmt->execute([$customerId]);
    $paysRow = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    Json::error('Could not read the dues ledger: ' . $e->getMessage(), 500);
}
$outstanding = round(max(0.0, (float) $b['bal'] - (float) ($paysRow['pays'] ?? 0)), 2);
if ($outstanding <= 0.009) {
    Json::error('This account is settled — nothing to remind.', 422);
}

/* Number: the dedicated whatsapp column (managed on whatsapp.php) wins. */
$to = (string) ($cust['phone'] ?? '');
try {
    if ($pdo->query("SHOW COLUMNS FROM customers LIKE 'whatsapp'")->fetch()) {
        $stmt = $pdo->prepare('SELECT whatsapp FROM customers WHERE id = ?');
        $stmt->execute([$customerId]);
        $w = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($w !== '') { $to = $w; }
    }
} catch (Throwable $e) { /* no column, no problem — phone is enough */ }

try {
    $m = Database::master();
} catch (Throwable $e) {
    Json::error('Control plane unreachable — WhatsApp is managed centrally.', 503);
}

$cfg = WaSend::cfg($m, $pdo);
if ($cfg['source'] === 'off') {
    Json::error('Due reminders are switched off for this store (WhatsApp › Setup).', 409);
}
if (!$cfg['on']) {
    Json::error($cfg['source'] === 'own' ? 'Your own WhatsApp line is not ready — add Phone number ID + token in WhatsApp › Setup.' : 'WhatsApp is not configured (or is switched off) on the platform.', 503);
}

$num = WaSend::norm($to, $cfg['cc']);
if ($num === '') {
    Json::error('This customer has no phone number to remind.', 422);
}

if (WaSend::todayCount($m, $num, WaSend::KIND_REMINDER) >= WaSend::REMINDERS_PER_NUM_PER_DAY) {
    Json::error('Reminder limit reached for this number today (max 3 per day).', 429);
}

$store = '';
try {
    $store = trim((string) ($pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'store_name'")->fetchColumn() ?: ''));
} catch (Throwable $e) { }
if ($store === '') {
    $h = preg_replace('/:\d+$/', '', strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? ''))));
    $store = $h !== '' ? explode('.', $h)[0] : 'the store';
}

$oldestTxt = '';
if (!empty($b['oldest_open'])) {
    $ts = strtotime((string) $b['oldest_open']);
    if ($ts) { $oldestTxt = date('d/m/Y', $ts); }
}
$today = date('d/m/Y');
$msg = 'You have unpaid dues of ' . WaSend::inr($outstanding) . ' across ' . max(1, (int) $b['open_bills']) . ' bill(s)'
     . ($oldestTxt !== '' ? ' (oldest dated ' . $oldestTxt . ')' : '')
     . '. Kindly settle at your earliest — we would really appreciate it.';
$name = trim((string) preg_replace('/\s+/', ' ', (string) $cust['name']));
$text = 'Hi ' . $name . '! This is ' . $store . '. ' . $msg;

$res = WaSend::send($m, WaSend::clientId($m), $num, WaSend::KIND_REMINDER, [$store, $msg, $today], $text);
if (empty($res['ok'])) {
    Json::error('Meta rejected the send: ' . (isset($res['error']) ? $res['error'] : 'unknown error'), 502);
}

Json::ok(['data' => [
    'sent' => true,
    'to' => '+' . WaSend::mask($num),
    'amount' => $outstanding,
    'via' => $res['via'],
    'receipt' => isset($res['api_id']) ? $res['api_id'] : '',
]]);
