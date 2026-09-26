<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Manufacturer.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Json::error('Method not allowed.', 405);
}

/**
 * Posted purchase invoices. Not the purchase report.
 * Reads v_purchase_invoices from database/migrations/2026_09_26_purchase_invoices.sql.
 * Falls back to the same join if the view has not been created yet.
 * New purchase entry stays on purchase.php.
 */
function queryRows(string $sql): array
{
    $rows = Manufacturer::query($sql);
    return is_array($rows) ? $rows : [];
}

function invoiceRows(): array
{
    $sql = 'SELECT id, invoice_no, invoice_date, supplier_id, supplier_name, supplier_phone, gstin, dl_no,
                   subtotal, discount, taxable, cgst, sgst, igst, round_off, grand_total,
                   payment_mode, amount_paid, balance_due, created_at,
                   item_count, qty, free_qty, return_count, credit_amount
            FROM v_purchase_invoices';
    try {
        return queryRows($sql);
    } catch (Throwable $e) {
        $fallback = 'SELECT p.id AS id, p.invoice_no AS invoice_no, p.invoice_date AS invoice_date,
                p.supplier_id AS supplier_id,
                COALESCE(s.name, \'Supplier\') AS supplier_name,
                COALESCE(s.phone, \'\') AS supplier_phone,
                COALESCE(s.gstin, \'\') AS gstin,
                COALESCE(s.dl_no, \'\') AS dl_no,
                p.subtotal AS subtotal, p.discount AS discount, p.taxable AS taxable,
                p.cgst AS cgst, p.sgst AS sgst, p.igst AS igst, p.round_off AS round_off,
                p.grand_total AS grand_total, p.payment_mode AS payment_mode,
                p.amount_paid AS amount_paid, p.balance_due AS balance_due, p.created_at AS created_at,
                COALESCE(it.item_count, 0) AS item_count,
                COALESCE(it.qty, 0) AS qty,
                COALESCE(it.free_qty, 0) AS free_qty,
                COALESCE(rt.return_count, 0) AS return_count,
                COALESCE(rt.credit_amount, 0) AS credit_amount
             FROM purchases p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             LEFT JOIN (
               SELECT purchase_id, COUNT(*) AS item_count, COALESCE(SUM(qty), 0) AS qty, COALESCE(SUM(free_qty), 0) AS free_qty
               FROM purchase_items GROUP BY purchase_id
             ) it ON it.purchase_id = p.id
             LEFT JOIN (
               SELECT purchase_id, COUNT(*) AS return_count, COALESCE(SUM(credit_amount), 0) AS credit_amount
               FROM purchase_returns GROUP BY purchase_id
             ) rt ON rt.purchase_id = p.id';
        return queryRows($fallback);
    }
}

function itemRows(int $purchaseId): array
{
    $id = (int) $purchaseId;
    $sql = 'SELECT id, purchase_id, medicine_id, medicine_name, unit, batch_id, batch_no, expiry_date,
                   qty, free_qty, rate, disc_pct, gst_pct, amount, returned_qty
            FROM v_purchase_invoice_items WHERE purchase_id = ' . $id;
    try {
        return queryRows($sql);
    } catch (Throwable $e) {
        $fallback = 'SELECT pi.id AS id, pi.purchase_id AS purchase_id, pi.medicine_id AS medicine_id,
                COALESCE(m.name, \'Removed medicine\') AS medicine_name,
                COALESCE(m.unit, \'\') AS unit,
                pi.batch_id AS batch_id,
                COALESCE(b.batch_no, \'\') AS batch_no,
                b.expiry_date AS expiry_date,
                pi.qty AS qty, pi.free_qty AS free_qty, pi.rate AS rate,
                pi.disc_pct AS disc_pct, pi.gst_pct AS gst_pct, pi.amount AS amount,
                COALESCE(ri.returned_qty, 0) AS returned_qty
             FROM purchase_items pi
             LEFT JOIN medicines m ON m.id = pi.medicine_id
             LEFT JOIN batches b ON b.id = pi.batch_id
             LEFT JOIN (
               SELECT purchase_item_id, COALESCE(SUM(qty), 0) AS returned_qty
               FROM purchase_return_items GROUP BY purchase_item_id
             ) ri ON ri.purchase_item_id = pi.id
             WHERE pi.purchase_id = ' . $id;
        return queryRows($fallback);
    }
}

function money(array $row, string $key): float
{
    return (float) ($row[$key] ?? 0);
}

function invoiceStatus(array $row): string
{
    $grand = money($row, 'grand_total');
    $credit = money($row, 'credit_amount');
    $due = money($row, 'balance_due');
    $paid = money($row, 'amount_paid');
    if ($credit > 0.009 && $grand > 0 && $credit + 0.05 >= $grand) {
        return 'Returned';
    }
    if ($credit > 0.009) {
        return 'Part returned';
    }
    if ($due <= 0.009) {
        return 'Paid';
    }
    if ($paid > 0.009) {
        return 'Partial';
    }
    return 'Due';
}

function shapeInvoice(array $row): array
{
    $date = substr((string) ($row['invoice_date'] ?? ''), 0, 10);
    return [
        'id' => (int) ($row['id'] ?? 0),
        'invoice_no' => (string) ($row['invoice_no'] ?? ''),
        'invoice_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '',
        'supplier_id' => (int) ($row['supplier_id'] ?? 0),
        'supplier_name' => (string) ($row['supplier_name'] ?? 'Supplier'),
        'supplier_phone' => (string) ($row['supplier_phone'] ?? ''),
        'gstin' => (string) ($row['gstin'] ?? ''),
        'dl_no' => (string) ($row['dl_no'] ?? ''),
        'subtotal' => money($row, 'subtotal'),
        'discount' => money($row, 'discount'),
        'taxable' => money($row, 'taxable'),
        'cgst' => money($row, 'cgst'),
        'sgst' => money($row, 'sgst'),
        'igst' => money($row, 'igst'),
        'round_off' => money($row, 'round_off'),
        'grand_total' => money($row, 'grand_total'),
        'payment_mode' => (string) ($row['payment_mode'] ?? 'Credit'),
        'amount_paid' => money($row, 'amount_paid'),
        'balance_due' => money($row, 'balance_due'),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'item_count' => (int) ($row['item_count'] ?? 0),
        'qty' => (float) ($row['qty'] ?? 0),
        'free_qty' => (float) ($row['free_qty'] ?? 0),
        'return_count' => (int) ($row['return_count'] ?? 0),
        'credit_amount' => money($row, 'credit_amount'),
        'status' => invoiceStatus($row),
    ];
}

function shapeItem(array $row): array
{
    $expiry = $row['expiry_date'] ?? null;
    return [
        'id' => (int) ($row['id'] ?? 0),
        'purchase_id' => (int) ($row['purchase_id'] ?? 0),
        'medicine_id' => (int) ($row['medicine_id'] ?? 0),
        'medicine_name' => (string) ($row['medicine_name'] ?? ''),
        'unit' => (string) ($row['unit'] ?? ''),
        'batch_id' => (int) ($row['batch_id'] ?? 0),
        'batch_no' => (string) ($row['batch_no'] ?? ''),
        'expiry_date' => $expiry ? substr((string) $expiry, 0, 10) : null,
        'qty' => (float) ($row['qty'] ?? 0),
        'free_qty' => (float) ($row['free_qty'] ?? 0),
        'rate' => money($row, 'rate'),
        'disc_pct' => (float) ($row['disc_pct'] ?? 0),
        'gst_pct' => (float) ($row['gst_pct'] ?? 0),
        'amount' => money($row, 'amount'),
        'returned_qty' => (float) ($row['returned_qty'] ?? 0),
    ];
}

function summarize(array $rows): array
{
    $today = date('Y-m-d');
    $sum = function (string $key) use ($rows): float {
        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float) ($row[$key] ?? 0);
        }
        return $total;
    };
    $count = function (string $status) use ($rows): int {
        $n = 0;
        foreach ($rows as $row) {
            if (($row['status'] ?? '') === $status) {
                $n++;
            }
        }
        return $n;
    };
    $todayTotal = 0.0;
    $todayCount = 0;
    foreach ($rows as $row) {
        if (substr((string) ($row['invoice_date'] ?? ''), 0, 10) === $today) {
            $todayCount++;
            $todayTotal += (float) ($row['grand_total'] ?? 0);
        }
    }
    return [
        'invoices' => count($rows),
        'grand_total' => $sum('grand_total'),
        'amount_paid' => $sum('amount_paid'),
        'balance_due' => $sum('balance_due'),
        'credit_amount' => $sum('credit_amount'),
        'paid' => $count('Paid'),
        'due' => $count('Due'),
        'partial' => $count('Partial'),
        'returned' => $count('Returned'),
        'part_returned' => $count('Part returned'),
        'today_count' => $todayCount,
        'today_total' => $todayTotal,
    ];
}

$rows = array_map('shapeInvoice', invoiceRows());
usort($rows, function (array $a, array $b): int {
    $date = strcmp($b['invoice_date'], $a['invoice_date']);
    if ($date !== 0) {
        return $date;
    }
    return $b['id'] <=> $a['id'];
});

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $invoice = null;
    foreach ($rows as $row) {
        if ((int) $row['id'] === $id) {
            $invoice = $row;
            break;
        }
    }
    if (!$invoice) {
        Json::error('Invoice not found.', 404);
    }
    Json::ok(['data' => [
        'invoice' => $invoice,
        'items' => array_map('shapeItem', itemRows($id)),
    ]]);
}

Json::ok(['data' => [
    'summary' => summarize($rows),
    'ledger' => $rows,
]]);
