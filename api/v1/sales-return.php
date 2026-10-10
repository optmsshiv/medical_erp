<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Sale.php';
require dirname(__DIR__, 2) . '/models/SalesReturn.php';
require dirname(__DIR__, 2) . '/models/SalesReturnItem.php';
require dirname(__DIR__, 2) . '/models/Manufacturer.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

/**
 * Sales returns desk.
 *   GET            → recent returns ledger + summary tiles (used by sales-return.php page)
 *   GET  ?id=N     → one return with its returned lines
 *   POST {saleId, reason, note, refundMode, items:[{saleItemId, qty}]}
 *        → rows are locked (FOR UPDATE), qty is capped at sold − already-returned,
 *          refund mirrors the line's recorded money (amount ∝ qty, so line
 *          discounts carry honestly), stock goes back to the exact batch,
 *          and any open due on the invoice settles first.
 */
function qr(string $sql, array $params = []): array
{
    $rows = Manufacturer::query($sql, $params);
    return is_array($rows) ? $rows : [];
}

function clipReturn(string $value, int $max): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

/* refund_mode column (2026-10-10) — self-heal; older installs fall back to noting the mode in text. */
try {
    $hadMode = (bool) qr("SHOW COLUMNS FROM sales_returns LIKE 'refund_mode'");
    if (!$hadMode) {
        try { qr("ALTER TABLE sales_returns ADD COLUMN refund_mode VARCHAR(20) NULL"); } catch (Throwable $e) { /* keep serving */ }
    }
    $hasModeCol = (bool) qr("SHOW COLUMNS FROM sales_returns LIKE 'refund_mode'");
} catch (Throwable $e) {
    $hasModeCol = false;
}
$modeSel = $hasModeCol ? "COALESCE(r.refund_mode, '') AS refund_mode" : "'' AS refund_mode";

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $ret = qr("SELECT r.id, r.sale_id, r.return_no, r.return_date, r.reason, COALESCE(r.note, '') AS note,
                          r.refund_amount, {$modeSel}, r.created_at,
                          s.invoice_no, COALESCE(c.name, 'Walk-in') AS customer_name
                   FROM sales_returns r
                   JOIN sales s ON s.id = r.sale_id
                   LEFT JOIN customers c ON c.id = s.customer_id
                   WHERE r.id = " . $id . " LIMIT 1");
        if (!$ret) {
            Json::error('Return not found.', 404);
        }
        $items = qr('SELECT ri.sale_item_id, ri.medicine_id, ri.batch_id, ri.qty, ri.rate, ri.amount,
                            COALESCE(m.name, \'Removed medicine\') AS medicine_name, COALESCE(b.batch_no, \'\') AS batch_no
                     FROM sales_return_items ri
                     LEFT JOIN medicines m ON m.id = ri.medicine_id
                     LEFT JOIN batches b ON b.id = ri.batch_id
                     WHERE ri.return_id = ' . $id . ' ORDER BY ri.id ASC');
        Json::ok(['data' => ['return' => $ret[0], 'items' => $items]]);
    }

    $rows = qr("SELECT r.id, r.sale_id, r.return_no, r.return_date, r.reason, COALESCE(r.note, '') AS note,
                       r.refund_amount, {$modeSel}, r.created_at,
                       s.invoice_no, COALESCE(c.name, 'Walk-in') AS customer_name,
                       COALESCE(it.cnt, 0) AS item_count, COALESCE(it.qty, 0) AS qty
                FROM sales_returns r
                JOIN sales s ON s.id = r.sale_id
                LEFT JOIN customers c ON c.id = s.customer_id
                LEFT JOIN (
                    SELECT return_id, COUNT(*) AS cnt, COALESCE(SUM(qty), 0) AS qty
                    FROM sales_return_items GROUP BY return_id
                ) it ON it.return_id = r.id
                ORDER BY r.id DESC LIMIT 200");

    $sumRow = qr("SELECT
            SUM(CASE WHEN r.return_date = CURDATE() THEN 1 ELSE 0 END) AS today_count,
            COALESCE(SUM(CASE WHEN r.return_date = CURDATE() THEN r.refund_amount ELSE 0 END), 0) AS today_amount,
            COALESCE(SUM(CASE WHEN DATE_FORMAT(r.return_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN r.refund_amount ELSE 0 END), 0) AS month_amount
        FROM sales_returns r");
    $summary = $sumRow ? $sumRow[0] : ['today_count' => 0, 'today_amount' => 0, 'month_amount' => 0];

    Json::ok(['data' => ['ledger' => $rows, 'summary' => $summary, 'has_mode' => $hasModeCol]]);
}

if ($method !== 'POST') {
    Json::error('Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$saleId = (int) ($input['saleId'] ?? 0);
$reason = clipReturn((string) ($input['reason'] ?? 'Customer Return'), 100) ?: 'Customer Return';
$note   = clipReturn((string) ($input['note'] ?? ''), 255);
$mode   = strtolower(trim((string) ($input['refundMode'] ?? 'cash')));
if (!in_array($mode, ['cash', 'upi', 'card', 'credit'], true)) {
    $mode = 'cash';
}
$items  = $input['items'] ?? [];

$sale = Sale::find($saleId);
if (!$sale) {
    Json::error('Invoice not found.', 404);
}
if (!is_array($items) || count($items) === 0) {
    Json::error('Select at least one item to return.', 422);
}

$pdo = Tenant::db();

try {
    $pdo->beginTransaction();

    $lines = [];
    $refundTotal = 0.0;

    foreach ($items as $item) {
        $saleItemId = (int) ($item['saleItemId'] ?? 0);
        $qty = (int) ($item['qty'] ?? 0);
        if (!$saleItemId || $qty <= 0) {
            continue;
        }

        $stmt = $pdo->prepare('SELECT * FROM sale_items WHERE id = :id AND sale_id = :saleId FOR UPDATE');
        $stmt->execute(['id' => $saleItemId, 'saleId' => $saleId]);
        $saleItem = $stmt->fetch();
        if (!$saleItem) {
            throw new RuntimeException('Invalid item line.');
        }

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(qty), 0) AS r FROM sales_return_items WHERE sale_item_id = :id');
        $stmt->execute(['id' => $saleItemId]);
        $alreadyReturned = (int) $stmt->fetch()['r'];
        $remaining = (int) $saleItem['qty'] - $alreadyReturned;

        if ($qty > $remaining) {
            throw new RuntimeException("Cannot return {$qty} — only {$remaining} unit(s) remain returnable for this line.");
        }

        /* Money honesty: mirror what the customer actually paid per unit on THIS
           line (amount carries any line discount), never a bare rate recompute. */
        $per = ((float) $saleItem['amount'] > 0 && (int) $saleItem['qty'] > 0)
            ? (float) $saleItem['amount'] / (int) $saleItem['qty']
            : (float) $saleItem['rate'];
        $amount = round($per * $qty, 2);
        $refundTotal += $amount;

        $lines[] = [
            'saleItemId' => $saleItemId, 'medicineId' => $saleItem['medicine_id'],
            'batchId' => $saleItem['batch_id'], 'qty' => $qty, 'rate' => (float) $saleItem['rate'], 'amount' => $amount,
        ];
    }

    if (count($lines) === 0) {
        throw new RuntimeException('No valid items to return.');
    }

    $refundTotal = round($refundTotal, 2);

    $tuple = [
        'sale_id'       => $saleId,
        'return_no'     => 'PENDING',
        'return_date'   => date('Y-m-d'),
        'reason'        => $reason,
        'note'          => $note !== '' ? $note : null,
        'refund_amount' => $refundTotal,
    ];
    if ($hasModeCol) {
        $tuple['refund_mode'] = $mode;
    } elseif ($note === '' || strpos($note, 'Refund:') === false) {
        $tuple['note'] = clipReturn(trim(($note !== '' ? $note . ' · ' : '') . 'Refund: ' . strtoupper($mode)), 255);
    }
    if ($tuple['note'] === null) {
        unset($tuple['note']);
    }

    $returnId = SalesReturn::create($tuple);
    $returnNo = 'SRN-' . date('y') . '-' . str_pad((string) $returnId, 4, '0', STR_PAD_LEFT);
    SalesReturn::update($returnId, ['return_no' => $returnNo]);

    foreach ($lines as $line) {
        SalesReturnItem::create([
            'return_id'    => $returnId,
            'sale_item_id' => $line['saleItemId'],
            'medicine_id'  => $line['medicineId'],
            'batch_id'     => $line['batchId'],
            'qty'          => $line['qty'],
            'rate'         => $line['rate'],
            'amount'       => $line['amount'],
        ]);

        // Restock — returned stock goes back to the exact batch it was sold from.
        $pdo->prepare('UPDATE batches SET quantity = quantity + :qty WHERE id = :id')
            ->execute(['qty' => $line['qty'], 'id' => $line['batchId']]);
    }

    // If this invoice still had an outstanding due, the return settles it first.
    $newBalance = max(0, (float) $sale['balance_due'] - $refundTotal);
    Sale::update($saleId, ['balance_due' => $newBalance]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    Json::error($e->getMessage() ?: 'Could not process the return.', 422);
}

require dirname(__DIR__, 2) . '/core/Audit.php';
Audit::log('SALES_RETURN', "{$returnNo} · ₹{$refundTotal} against sale #{$saleId} via {$mode}");

Json::ok([
    'returnNo'     => $returnNo,
    'returnId'     => $returnId,
    'refundAmount' => $refundTotal,
    'refundMode'   => $mode,
    'lines'        => count($lines),
    'balanceDue'   => $newBalance,
]);
