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
 * Customer dues. Not the due report.
 * Reads v_customer_dues from database/migrations/2026_09_26_customer_dues.sql.
 * Falls back to the same joins if the views have not been created yet.
 *
 * Due is the stored sale balance. A payment already saved on the bill
 * (amount_paid) is not subtracted again. Only customer payments above that
 * reduce the outstanding amount.
 */
function queryRows(string $sql): array
{
    $rows = Manufacturer::query($sql);
    return is_array($rows) ? $rows : [];
}

function customerRows(): array
{
    $sql = 'SELECT customer_id, customer_name, customer_type, phone, gstin, dl_no, address,
                   bill_count, open_bills, billed, amount_paid, balance_due,
                   refund_amount, payments, oldest_open
            FROM v_customer_dues';
    try {
        return queryRows($sql);
    } catch (Throwable $e) {
        $fallback = 'SELECT c.id AS customer_id, c.name AS customer_name, c.type AS customer_type,
                COALESCE(c.phone, \'\') AS phone,
                COALESCE(c.gstin, \'\') AS gstin,
                COALESCE(c.dl_no, \'\') AS dl_no,
                COALESCE(c.address, \'\') AS address,
                COALESCE(b.bill_count, 0) AS bill_count,
                COALESCE(b.open_bills, 0) AS open_bills,
                COALESCE(b.billed, 0) AS billed,
                COALESCE(b.amount_paid, 0) AS amount_paid,
                COALESCE(b.balance_due, 0) AS balance_due,
                COALESCE(b.refund_amount, 0) AS refund_amount,
                COALESCE(pay.payments, 0) AS payments,
                b.oldest_open AS oldest_open
             FROM customers c
             LEFT JOIN (
               SELECT s.customer_id AS customer_id,
                      COUNT(*) AS bill_count,
                      SUM(CASE WHEN s.balance_due > 0 THEN 1 ELSE 0 END) AS open_bills,
                      COALESCE(SUM(s.grand_total), 0) AS billed,
                      COALESCE(SUM(s.amount_paid), 0) AS amount_paid,
                      COALESCE(SUM(s.balance_due), 0) AS balance_due,
                      COALESCE(SUM(r.refund_amount), 0) AS refund_amount,
                      MIN(CASE WHEN s.balance_due > 0 THEN s.sale_date END) AS oldest_open
               FROM sales s
               LEFT JOIN (
                 SELECT sale_id, COALESCE(SUM(refund_amount), 0) AS refund_amount
                 FROM sales_returns
                 GROUP BY sale_id
               ) r ON r.sale_id = s.id
               GROUP BY s.customer_id
             ) b ON b.customer_id = c.id
             LEFT JOIN (
               SELECT party_id, COALESCE(SUM(amount), 0) AS payments
               FROM payments
               WHERE party_type = \'customer\'
               GROUP BY party_id
             ) pay ON pay.party_id = c.id
             WHERE b.bill_count > 0 OR pay.payments > 0';
        return queryRows($fallback);
    }
}

function billRows(): array
{
    $sql = 'SELECT id, customer_id, customer_name, customer_type, phone, invoice_no, sale_date,
                   channel, payment_mode, grand_total, amount_paid, balance_due, refund_amount
            FROM v_customer_due_bills';
    try {
        return queryRows($sql);
    } catch (Throwable $e) {
        $fallback = 'SELECT s.id AS id, s.customer_id AS customer_id, c.name AS customer_name,
                c.type AS customer_type, COALESCE(c.phone, \'\') AS phone,
                s.invoice_no AS invoice_no, s.sale_date AS sale_date, s.channel AS channel,
                s.payment_mode AS payment_mode, s.grand_total AS grand_total,
                s.amount_paid AS amount_paid, s.balance_due AS balance_due,
                COALESCE(r.refund_amount, 0) AS refund_amount
             FROM sales s
             JOIN customers c ON c.id = s.customer_id
             LEFT JOIN (
               SELECT sale_id, COALESCE(SUM(refund_amount), 0) AS refund_amount
               FROM sales_returns
               GROUP BY sale_id
             ) r ON r.sale_id = s.id';
        return queryRows($fallback);
    }
}

function paymentRows(): array
{
    return queryRows('SELECT id, party_id, amount, mode, payment_date, note
        FROM payments
        WHERE party_type = \'customer\'');
}

function money(array $row, string $key): float
{
    return (float) ($row[$key] ?? 0);
}

function dateOnly($value): string
{
    $text = substr((string) $value, 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) ? $text : '';
}

function ageDays(string $date): ?int
{
    if ($date === '') {
        return null;
    }
    return (int) floor((strtotime(date('Y-m-d')) - strtotime($date)) / 86400);
}

function outstanding(array $row): float
{
    $balance = money($row, 'balance_due');
    $paidOnBills = money($row, 'amount_paid');
    $payments = money($row, 'payments');
    $extra = max(0, $payments - $paidOnBills);
    return round(max(0, $balance - $extra), 2);
}

function positionOf(array $row): string
{
    $due = (float) ($row['outstanding'] ?? 0);
    if ($due <= 0.009) {
        return 'Settled';
    }
    $days = ageDays((string) ($row['oldest_open'] ?? ''));
    if ($days !== null && $days > 30) {
        return 'Overdue';
    }
    if (money($row, 'amount_paid') > 0.009) {
        return 'Partial';
    }
    return 'Due';
}

function shapeCustomer(array $row): array
{
    $oldest = dateOnly($row['oldest_open'] ?? '');
    $type = strtolower((string) ($row['customer_type'] ?? 'retail')) === 'wholesale' ? 'wholesale' : 'retail';
    $shaped = [
        'customer_id' => (int) ($row['customer_id'] ?? 0),
        'customer_name' => (string) ($row['customer_name'] ?? ''),
        'customer_type' => $type,
        'phone' => (string) ($row['phone'] ?? ''),
        'gstin' => (string) ($row['gstin'] ?? ''),
        'dl_no' => (string) ($row['dl_no'] ?? ''),
        'address' => (string) ($row['address'] ?? ''),
        'bill_count' => (int) ($row['bill_count'] ?? 0),
        'open_bills' => (int) ($row['open_bills'] ?? 0),
        'billed' => money($row, 'billed'),
        'amount_paid' => money($row, 'amount_paid'),
        'balance_due' => money($row, 'balance_due'),
        'refund_amount' => money($row, 'refund_amount'),
        'payments' => money($row, 'payments'),
        'oldest_open' => $oldest,
        'age_days' => ageDays($oldest),
    ];
    $shaped['outstanding'] = outstanding($shaped);
    $shaped['position'] = positionOf($shaped);
    return $shaped;
}

function shapeBill(array $row): array
{
    $date = dateOnly($row['sale_date'] ?? '');
    $channel = strtolower((string) ($row['channel'] ?? 'retail')) === 'wholesale' ? 'wholesale' : 'retail';
    return [
        'id' => (int) ($row['id'] ?? 0),
        'customer_id' => (int) ($row['customer_id'] ?? 0),
        'customer_name' => (string) ($row['customer_name'] ?? ''),
        'invoice_no' => (string) ($row['invoice_no'] ?? ''),
        'sale_date' => $date,
        'age_days' => ageDays($date),
        'channel' => $channel,
        'payment_mode' => (string) ($row['payment_mode'] ?? ''),
        'grand_total' => money($row, 'grand_total'),
        'amount_paid' => money($row, 'amount_paid'),
        'balance_due' => money($row, 'balance_due'),
        'refund_amount' => money($row, 'refund_amount'),
    ];
}

function shapePayment(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'customer_id' => (int) ($row['party_id'] ?? 0),
        'amount' => money($row, 'amount'),
        'mode' => (string) ($row['mode'] ?? ''),
        'payment_date' => dateOnly($row['payment_date'] ?? ''),
        'note' => (string) ($row['note'] ?? ''),
    ];
}

$customers = array_map('shapeCustomer', customerRows());
usort($customers, function (array $a, array $b): int {
    $due = $b['outstanding'] <=> $a['outstanding'];
    if ($due !== 0) {
        return $due;
    }
    return strcasecmp($a['customer_name'], $b['customer_name']);
});

$bills = array_map('shapeBill', billRows());
usort($bills, function (array $a, array $b): int {
    return strcmp($a['sale_date'], $b['sale_date']);
});

$payments = [];
try {
    $payments = array_map('shapePayment', paymentRows());
} catch (Throwable $e) {
    $payments = [];
}
usort($payments, function (array $a, array $b): int {
    return strcmp($b['payment_date'], $a['payment_date']);
});

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $customer = null;
    foreach ($customers as $row) {
        if ((int) $row['customer_id'] === $id) {
            $customer = $row;
            break;
        }
    }
    if (!$customer) {
        Json::error('Customer not found.', 404);
    }
    Json::ok(['data' => [
        'customer' => $customer,
        'bills' => array_values(array_filter($bills, fn ($row) => (int) $row['customer_id'] === $id)),
        'payments' => array_values(array_filter($payments, fn ($row) => (int) $row['customer_id'] === $id)),
    ]]);
}

$summary = [
    'customers' => count($customers),
    'with_due' => 0,
    'overdue' => 0,
    'settled' => 0,
    'outstanding' => 0.0,
    'open_bills' => 0,
    'billed' => 0.0,
    'payments' => 0.0,
];
foreach ($customers as $row) {
    $summary['outstanding'] += $row['outstanding'];
    $summary['open_bills'] += $row['open_bills'];
    $summary['billed'] += $row['billed'];
    $summary['payments'] += $row['payments'];
    if ($row['position'] === 'Settled') {
        $summary['settled']++;
    } else {
        $summary['with_due']++;
    }
    if ($row['position'] === 'Overdue') {
        $summary['overdue']++;
    }
}

Json::ok(['data' => [
    'summary' => $summary,
    'customers' => $customers,
    'bills' => $bills,
    'payments' => $payments,
]]);
