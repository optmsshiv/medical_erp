<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

/**
 * Receipts & Payments register — the writer for the ledger the dues pages read:
 *   party_type = 'customer' → money received (reduces customer dues)
 *   party_type = 'supplier' → money paid out (reduces supplier dues)
 *   party_type = 'other'    → free-form receipt/payment (never touches dues)
 *
 * Schema-defensive build: the pre-existing `payments` table keeps working even
 * when the database user lacks ALTER rights — `direction`/`receipt_no` are used
 * only after confirming the columns exist, with live fallbacks otherwise.
 * Every failure answers with a JSON error naming the cause, never a bare 500.
 */

function pDayOk(?string $d): ?string
{
    if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return null;
    [$y, $m, $dd] = array_map('intval', explode('-', $d));
    return checkdate($m, $dd, $y) ? $d : null;
}

function pClip(string $value, int $max): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function pTableExists(PDO $pdo, string $table): bool
{
    try {
        return (bool) $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table))->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function pHasColumn(PDO $pdo, string $table, string $column): bool
{
    try {
        return (bool) $pdo->query("SHOW COLUMNS FROM {$table} LIKE " . $pdo->quote($column))->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

$pdo = Tenant::db();

/* Provision if absent (older databases already have this table from purchase dues). */
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS payments (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        party_type VARCHAR(16) NOT NULL DEFAULT \'customer\',
        party_id INT UNSIGNED NOT NULL DEFAULT 0,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        mode VARCHAR(16) NOT NULL DEFAULT \'Cash\',
        payment_date DATE NOT NULL,
        note VARCHAR(200) NOT NULL DEFAULT \'\',
        direction VARCHAR(8) DEFAULT NULL,
        receipt_no VARCHAR(24) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_payments_date (payment_date),
        INDEX idx_payments_party (party_type, party_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
} catch (Throwable $e) { /* verify below */ }
if (!pTableExists($pdo, 'payments')) {
    Json::error('Could not create the payments table — the database user needs CREATE rights.', 500);
}

/* Baseline columns the dues ledgers already prove exist. */
foreach (['party_type', 'party_id', 'amount', 'mode', 'payment_date', 'note'] as $base) {
    if (!pHasColumn($pdo, 'payments', $base)) {
        Json::error("The payments table is missing its {$base} column — share this message with support.", 500);
    }
}

/* Additive columns: try ALTER, but survive if it can't stick. */
if (!pHasColumn($pdo, 'payments', 'direction')) {
    try { $pdo->exec("ALTER TABLE payments ADD direction VARCHAR(8) DEFAULT NULL AFTER note"); } catch (Throwable $e) { /* optional */ }
}
if (!pHasColumn($pdo, 'payments', 'receipt_no')) {
    try { $pdo->exec("ALTER TABLE payments ADD receipt_no VARCHAR(24) DEFAULT NULL AFTER direction"); } catch (Throwable $e) { /* optional */ }
}
$hasDir = pHasColumn($pdo, 'payments', 'direction');
$hasReceipt = pHasColumn($pdo, 'payments', 'receipt_no');
$hasStamp = pHasColumn($pdo, 'payments', 'created_at');
if ($hasDir) {
    try {
        $pdo->exec("UPDATE payments SET direction = 'in'  WHERE direction IS NULL AND party_type = 'customer'");
        $pdo->exec("UPDATE payments SET direction = 'out' WHERE direction IS NULL AND party_type = 'supplier'");
    } catch (Throwable $e) { /* optional */ }
}

/* SQL fragments that respect whichever columns actually exist. */
$dirExpr = $hasDir
    ? "COALESCE(p.direction, IF(p.party_type = 'supplier', 'out', 'in'))"
    : "IF(p.party_type = 'supplier', 'out', 'in')";
$receiptExpr = $hasReceipt ? 'p.receipt_no' : 'NULL';
$stampExpr = $hasStamp ? 'p.created_at' : 'NULL';

function partyName(PDO $pdo, string $type, int $id): string
{
    if ($type === 'customer' && $id > 0) {
        $s = $pdo->prepare('SELECT name FROM customers WHERE id = ? LIMIT 1');
        $s->execute([$id]);
        $n = $s->fetchColumn();
        return $n !== false ? (string) $n : '';
    }
    if ($type === 'supplier' && $id > 0) {
        $s = $pdo->prepare('SELECT name FROM suppliers WHERE id = ? LIMIT 1');
        $s->execute([$id]);
        $n = $s->fetchColumn();
        return $n !== false ? (string) $n : '';
    }
    return '';
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $to = pDayOk($_GET['to'] ?? '') ?: date('Y-m-d');
    $from = pDayOk($_GET['from'] ?? '') ?: $to;
    try {
        $stmt = $pdo->prepare("SELECT p.id, p.party_type, p.party_id, p.amount, p.mode, p.payment_date, p.note,
                {$dirExpr} AS direction, {$receiptExpr} AS receipt_no, {$stampExpr} AS created_at,
                COALESCE(c.name, s.name, '') AS party_name
            FROM payments p
            LEFT JOIN customers c ON p.party_type = 'customer' AND c.id = p.party_id
            LEFT JOIN suppliers  s ON p.party_type = 'supplier' AND s.id = p.party_id
            WHERE p.payment_date BETWEEN ? AND ?
            ORDER BY p.payment_date DESC, p.id DESC");
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        Json::error('Payments read failed: ' . $e->getMessage(), 500);
    }
    $data = array_map(function ($r) {
        $name = $r['party_name'] !== '' ? $r['party_name']
            : ($r['party_type'] === 'other' && strpos($r['note'], ' — ') !== false ? substr($r['note'], 0, strpos($r['note'], ' — ')) : '');
        return [
            'id' => (int) $r['id'],
            'date' => $r['payment_date'],
            'time' => strlen((string) ($r['created_at'] ?? '')) > 10 ? substr((string) $r['created_at'], 11, 5) : '',
            'direction' => $r['direction'] === 'out' ? 'out' : 'in',
            'partyType' => $r['party_type'],
            'partyId' => (int) $r['party_id'],
            'partyName' => $name,
            'amount' => (float) $r['amount'],
            'mode' => $r['mode'],
            'note' => $r['note'],
            'receiptNo' => !empty($r['receipt_no']) ? $r['receipt_no'] : ('PM-' . str_pad((string) $r['id'], 5, '0', STR_PAD_LEFT)),
        ];
    }, $rows ?: []);
    Json::ok(['data' => $data, 'range' => ['from' => $from, 'to' => $to]]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $partyType = strtolower(pClip((string) ($input['partyType'] ?? ''), 12));
    if (!in_array($partyType, ['customer', 'supplier', 'other'], true)) Json::error('Choose a party type.', 422);
    $partyId = (int) ($input['partyId'] ?? 0);
    $amount = round((float) ($input['amount'] ?? 0), 2);
    $date = pDayOk($input['date'] ?? '') ?: date('Y-m-d');
    $mode = ucfirst(strtolower(pClip((string) ($input['mode'] ?? 'Cash'), 10)));
    if (!in_array($mode, ['Cash', 'Bank', 'Upi', 'Cheque'], true)) $mode = 'Cash';
    if ($mode === 'Upi') $mode = 'UPI';
    $note = pClip((string) ($input['note'] ?? ''), 160);
    $direction = $partyType === 'customer' ? 'in' : ($partyType === 'supplier' ? 'out'
        : (strtolower((string) ($input['direction'] ?? 'in')) === 'out' ? 'out' : 'in'));

    if ($amount <= 0) Json::error('Enter an amount greater than zero.', 422);
    if ($amount > 50000000) Json::error('That amount looks wrong — check the figure.', 422);
    if ($date > date('Y-m-d')) Json::error('Payments cannot be dated in the future.', 422);
    if ($partyType !== 'other' && $partyId <= 0) Json::error('Pick the ' . $partyType . ' this payment belongs to.', 422);

    if ($partyType === 'other') {
        $who = pClip((string) ($input['partyName'] ?? ''), 60);
        if ($who === '') Json::error('Name who this payment is from / to.', 422);
        $note = $who . ($note !== '' ? ' — ' . $note : '');
        $partyId = 0;
    } else {
        try {
            if (partyName($pdo, $partyType, $partyId) === '') Json::error(ucfirst($partyType) . ' not found.', 404);
        } catch (Throwable $e) {
            Json::error(ucfirst($partyType) . ' lookup failed: ' . $e->getMessage(), 500);
        }
    }

    try {
        $cols = 'party_type, party_id, amount, mode, payment_date, note' . ($hasDir ? ', direction' : '');
        $vals = '?, ?, ?, ?, ?, ?' . ($hasDir ? ', ?' : '');
        $params = [$partyType, $partyId, $amount, $mode, $date, $note];
        if ($hasDir) $params[] = $direction;
        $stmt = $pdo->prepare("INSERT INTO payments ({$cols}) VALUES ({$vals})");
        $stmt->execute($params);
        $id = (int) $pdo->lastInsertId();
        $receipt = 'PM-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
        if ($hasReceipt) {
            $pdo->prepare('UPDATE payments SET receipt_no = ? WHERE id = ?')->execute([$receipt, $id]);
        }
    } catch (Throwable $e) {
        Json::error('Could not record the payment: ' . $e->getMessage(), 500);
    }
    Json::ok(['data' => ['id' => $id, 'receiptNo' => $receipt, 'direction' => $direction]]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) Json::error('Missing payment id.', 422);
    try {
        $stmt = $pdo->prepare('DELETE FROM payments WHERE id = ?');
        $stmt->execute([$id]);
    } catch (Throwable $e) {
        Json::error('Could not delete: ' . $e->getMessage(), 500);
    }
    if ($stmt->rowCount() === 0) Json::error('Payment not found.', 404);
    Json::ok(['data' => ['id' => $id]]);
}

Json::error('Method not allowed.', 405);
