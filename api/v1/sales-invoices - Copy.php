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
 * Posted sales invoices. Not the sales report.
 * Reads v_sales_invoices from database/migrations/2026_09_26_sales_invoices.sql.
 * Falls back to the same join if the view has not been created yet.
 * Checkout stays on sales.php.
 */
function invoiceRows(): array
{
    $sql = 'SELECT id, invoice_no, sale_date, channel, customer_id, customer_name, customer_phone,
                   customer_type, doctor_id, doctor_name, gstin, dl_no, subtotal, discount,
                   gst_amount, cgst, sgst, igst, round_off, grand_total, payment_mode,
                   cash_amount, upi_amount, amount_paid, balance_due, created_at,
                   item_count, qty, free_qty, return_count, refund_amount
            FROM v_sales_invoices';
    try {
        return queryRows($sql);
    } catch (Throwable $e) {
        $fallback = 'SELECT s.id AS id, s.invoice_no AS invoice_no, s.sale_date AS sale_date,
                s.channel AS channel, s.customer_id AS customer_id,
                COALESCE(c.name, \'Walk-in\') AS customer_name,
                COALESCE(c.phone, \'\') AS customer_phone,
                COALESCE(c.type, s.channel) AS customer_type,
                s.doctor_id AS doctor_id,
                COALESCE(d.name, \'\') AS doctor_name,
                COALESCE(s.gstin, \'\') AS gstin,
                COALESCE(s.dl_no, \'\') AS dl_no,
                s.subtotal AS subtotal, s.discount AS discount, s.gst_amount AS gst_amount,
                s.cgst AS cgst, s.sgst AS sgst, s.igst AS igst, s.round_off AS round_off,
                s.grand_total AS grand_total, s.payment_mode AS payment_mode,
                s.cash_amount AS cash_amount, s.upi_amount AS upi_amount,
                s.amount_paid AS amount_paid, s.balance_due AS balance_due, s.created_at AS created_at,
                COALESCE(it.item_count, 0) AS item_count,
                COALESCE(it.qty, 0) AS qty,
                COALESCE(it.free_qty, 0) AS free_qty,
                COALESCE(rt.return_count, 0) AS return_count,
                COALESCE(rt.refund_amount, 0) AS refund_amount
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             LEFT JOIN doctors d ON d.id = s.doctor_id
             LEFT JOIN (
               SELECT sale_id, COUNT(*) AS item_count, COALESCE(SUM(qty), 0) AS qty, COALESCE(SUM(free_qty), 0) AS free_qty
               FROM sale_items GROUP BY sale_id
             ) it ON it.sale_id = s.id
             LEFT JOIN (
               SELECT sale_id, COUNT(*) AS return_count, COALESCE(SUM(refund_amount), 0) AS refund_amount
               FROM sales_returns GROUP BY sale_id
             ) rt ON rt.sale_id = s.id';
        return queryRows($fallback);
    }
}

function itemRows(int $saleId): array
{
    $id = (int) $saleId;
    $sql = 'SELECT id, sale_id, medicine_id, medicine_name, unit, batch_id, batch_no, expiry_date,
                   qty, free_qty, rate, disc_pct, gst_pct, amount, unit_sold, pack_qty_at_sale, returned_qty
            FROM v_sales_invoice_items WHERE sale_id = ' . $id;
    try {
        return queryRows($sql);
    } catch (Throwable $e) {
        $fallback = 'SELECT si.id AS id, si.sale_id AS sale_id, si.medicine_id AS medicine_id,
                COALESCE(m.name, \'Removed medicine\') AS medicine_name,
                COALESCE(m.unit, \'\') AS unit,
                si.batch_id AS batch_id,
                COALESCE(b.batch_no, \'\') AS batch_no,
                b.expiry_date AS expiry_date,
                si.qty AS qty, si.free_qty AS free_qty, si.rate AS rate,
                si.disc_pct AS disc_pct, si.gst_pct AS gst_pct, si.amount AS amount,
                si.unit_sold AS unit_sold, si.pack_qty_at_sale AS pack_qty_at_sale,
                COALESCE(ri.returned_qty, 0) AS returned_qty
             FROM sale_items si
             LEFT JOIN medicines m ON m.id = si.medicine_id
             LEFT JOIN batches b ON b.id = si.batch_id
             LEFT JOIN (
               SELECT sale_item_id, COALESCE(SUM(qty), 0) AS returned_qty
               FROM sales_return_items GROUP BY sale_item_id
             ) ri ON ri.sale_item_id = si.id
             WHERE si.sale_id = ' . $id;
        return queryRows($fallback);
    }
}

function queryRows(string $sql): array
{
    $rows = Manufacturer::query($sql);
    return is_array($rows) ? $rows : [];
}

function money(array $row, string $key): float
{
    return (float) ($row[$key] ?? 0);
}

function invoiceStatus(array $row): string
{
    $grand = money($row, 'grand_total');
    $refund = money($row, 'refund_amount');
    $due = money($row, 'balance_due');
    $paid = money($row, 'amount_paid');
    if ($refund > 0.009 && $grand > 0 && $refund + 0.05 >= $grand) {
        return 'Returned';
    }
    if ($refund > 0.009) {
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
    $date = substr((string) ($row['sale_date'] ?? ''), 0, 10);
    return [
        'id' => (int) ($row['id'] ?? 0),
        'invoice_no' => (string) ($row['invoice_no'] ?? ''),
        'sale_date' => $date,
        'channel' => strtolower((string) ($row['channel'] ?? 'retail')) === 'wholesale' ? 'wholesale' : 'retail',
        'customer_id' => (int) ($row['customer_id'] ?? 0),
        'customer_name' => (string) ($row['customer_name'] ?? 'Walk-in'),
        'customer_phone' => (string) ($row['customer_phone'] ?? ''),
        'customer_type' => (string) ($row['customer_type'] ?? ''),
        'doctor_id' => isset($row['doctor_id']) && $row['doctor_id'] !== null ? (int) $row['doctor_id'] : null,
        'doctor_name' => (string) ($row['doctor_name'] ?? ''),
        'gstin' => (string) ($row['gstin'] ?? ''),
        'dl_no' => (string) ($row['dl_no'] ?? ''),
        'subtotal' => money($row, 'subtotal'),
        'discount' => money($row, 'discount'),
        'gst_amount' => money($row, 'gst_amount'),
        'cgst' => money($row, 'cgst'),
        'sgst' => money($row, 'sgst'),
        'igst' => money($row, 'igst'),
        'round_off' => money($row, 'round_off'),
        'grand_total' => money($row, 'grand_total'),
        'payment_mode' => strtolower((string) ($row['payment_mode'] ?? 'cash')),
        'cash_amount' => money($row, 'cash_amount'),
        'upi_amount' => money($row, 'upi_amount'),
        'amount_paid' => money($row, 'amount_paid'),
        'balance_due' => money($row, 'balance_due'),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'item_count' => (int) ($row['item_count'] ?? 0),
        'qty' => (float) ($row['qty'] ?? 0),
        'free_qty' => (float) ($row['free_qty'] ?? 0),
        'return_count' => (int) ($row['return_count'] ?? 0),
        'refund_amount' => money($row, 'refund_amount'),
        'status' => invoiceStatus($row),
    ];
}

function shapeItem(array $row): array
{
    $expiry = $row['expiry_date'] ?? null;
    return [
        'id' => (int) ($row['id'] ?? 0),
        'sale_id' => (int) ($row['sale_id'] ?? 0),
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
        'unit_sold' => (string) ($row['unit_sold'] ?? 'pack'),
        'pack_qty_at_sale' => $row['pack_qty_at_sale'] !== null ? (int) $row['pack_qty_at_sale'] : null,
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
    $retail = 0;
    $wholesale = 0;
    foreach ($rows as $row) {
        if (($row['channel'] ?? '') === 'wholesale') {
            $wholesale++;
        } else {
            $retail++;
        }
        if (substr((string) ($row['sale_date'] ?? ''), 0, 10) === $today) {
            $todayCount++;
            $todayTotal += (float) ($row['grand_total'] ?? 0);
        }
    }
    return [
        'invoices' => count($rows),
        'retail' => $retail,
        'wholesale' => $wholesale,
        'grand_total' => $sum('grand_total'),
        'amount_paid' => $sum('amount_paid'),
        'balance_due' => $sum('balance_due'),
        'refund_amount' => $sum('refund_amount'),
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
    $date = strcmp($b['sale_date'], $a['sale_date']);
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
    $items = array_map('shapeItem', itemRows($id));
    Json::ok(['data' => ['invoice' => $invoice, 'items' => $items]]);
}

Json::ok(['data' => [
    'summary' => summarize($rows),
    'ledger' => $rows,
]]);
