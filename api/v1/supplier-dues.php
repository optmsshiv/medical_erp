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
 * Supplier dues. Not the due report.
 * Reads v_supplier_dues from database/migrations/2026_09_26_supplier_dues.sql.
 * Falls back to the same joins if the views have not been created yet.
 *
 * Due is the stored purchase balance. A payment already saved on the bill
 * (amount_paid) is not subtracted again. Only supplier payments above that
 * reduce the outstanding amount.
 */
function queryRows(string $sql): array
{
    $rows = Manufacturer::query($sql);
    return is_array($rows) ? $rows : [];
}

function supplierRows(): array
{
    $sql = 'SELECT supplier_id, supplier_name, phone, gstin, dl_no, address,
                   bill_count, open_bills, billed, amount_paid, balance_due,
                   return_credit, payments, oldest_open
            FROM v_supplier_dues';
    try {
        return queryRows($sql);
    } catch (Throwable $e) {
        $fallback = 'SELECT s.id AS supplier_id, s.name AS supplier_name,
                COALESCE(s.phone, \'\') AS phone,
                COALESCE(s.gstin, \'\') AS gstin,
                COALESCE(s.dl_no, \'\') AS dl_no,
                COALESCE(s.address, \'\') AS address,
                COALESCE(b.bill_count, 0) AS bill_count,
                COALESCE(b.open_bills, 0) AS open_bills,
                COALESCE(b.billed, 0) AS billed,
                COALESCE(b.amount_paid, 0) AS amount_paid,
                COALESCE(b.balance_due, 0) AS balance_due,
                COALESCE(b.return_credit, 0) AS return_credit,
                COALESCE(pay.payments, 0) AS payments,
                b.oldest_open AS oldest_open
             FROM suppliers s
             LEFT JOIN (
               SELECT p.supplier_id AS supplier_id,
                      COUNT(*) AS bill_count,
                      SUM(CASE WHEN p.balance_due > 0 THEN 1 ELSE 0 END) AS open_bills,
                      COALESCE(SUM(p.grand_total), 0) AS billed,
                      COALESCE(SUM(p.amount_paid), 0) AS amount_paid,
                      COALESCE(SUM(p.balance_due), 0) AS balance_due,
                      COALESCE(SUM(r.credit_amount), 0) AS return_credit,
                      MIN(CASE WHEN p.balance_due > 0 THEN p.invoice_date END) AS oldest_open
               FROM purchases p
               LEFT JOIN (
                 SELECT purchase_id, COALESCE(SUM(credit_amount), 0) AS credit_amount
                 FROM purchase_returns
                 GROUP BY purchase_id
               ) r ON r.purchase_id = p.id
               GROUP BY p.supplier_id
             ) b ON b.supplier_id = s.id
             LEFT JOIN (
               SELECT party_id, COALESCE(SUM(amount), 0) AS payments
               FROM payments
               WHERE party_type = \'supplier\'
               GROUP BY party_id
             ) pay ON pay.party_id = s.id
             WHERE b.bill_count > 0 OR pay.payments > 0';
        return queryRows($fallback);
    }
}

function billRows(): array
{
    $sql = 'SELECT id, supplier_id, supplier_name, phone, gstin, invoice_no, invoice_date,
                   payment_mode, grand_total, amount_paid, balance_due, return_credit
            FROM v_supplier_due_bills';
    try {
        return queryRows($sql);
    } catch (Throwable $e) {
        $fallback = 'SELECT p.id AS id, p.supplier_id AS supplier_id, s.name AS supplier_name,
                COALESCE(s.phone, \'\') AS phone, COALESCE(s.gstin, \'\') AS gstin,
                p.invoice_no AS invoice_no, p.invoice_date AS invoice_date,
                p.payment_mode AS payment_mode, p.grand_total AS grand_total,
                p.amount_paid AS amount_paid, p.balance_due AS balance_due,
                COALESCE(r.credit_amount, 0) AS return_credit
             FROM purchases p
             JOIN suppliers s ON s.id = p.supplier_id
             LEFT JOIN (
               SELECT purchase_id, COALESCE(SUM(credit_amount), 0) AS credit_amount
               FROM purchase_returns
               GROUP BY purchase_id
             ) r ON r.purchase_id = p.id';
        return queryRows($fallback);
    }
}

function paymentRows(): array
{
    return queryRows('SELECT id, party_id, amount, mode, payment_date, note
        FROM payments
        WHERE party_type = \'supplier\'');
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

function shapeSupplier(array $row): array
{
    $oldest = dateOnly($row['oldest_open'] ?? '');
    $shaped = [
        'supplier_id' => (int) ($row['supplier_id'] ?? 0),
        'supplier_name' => (string) ($row['supplier_name'] ?? ''),
        'phone' => (string) ($row['phone'] ?? ''),
        'gstin' => (string) ($row['gstin'] ?? ''),
        'dl_no' => (string) ($row['dl_no'] ?? ''),
        'address' => (string) ($row['address'] ?? ''),
        'bill_count' => (int) ($row['bill_count'] ?? 0),
        'open_bills' => (int) ($row['open_bills'] ?? 0),
        'billed' => money($row, 'billed'),
        'amount_paid' => money($row, 'amount_paid'),
        'balance_due' => money($row, 'balance_due'),
        'return_credit' => money($row, 'return_credit'),
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
    $date = dateOnly($row['invoice_date'] ?? '');
    return [
        'id' => (int) ($row['id'] ?? 0),
        'supplier_id' => (int) ($row['supplier_id'] ?? 0),
        'supplier_name' => (string) ($row['supplier_name'] ?? ''),
        'invoice_no' => (string) ($row['invoice_no'] ?? ''),
        'invoice_date' => $date,
        'age_days' => ageDays($date),
        'payment_mode' => (string) ($row['payment_mode'] ?? ''),
        'grand_total' => money($row, 'grand_total'),
        'amount_paid' => money($row, 'amount_paid'),
        'balance_due' => money($row, 'balance_due'),
        'return_credit' => money($row, 'return_credit'),
    ];
}

function shapePayment(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'supplier_id' => (int) ($row['party_id'] ?? 0),
        'amount' => money($row, 'amount'),
        'mode' => (string) ($row['mode'] ?? ''),
        'payment_date' => dateOnly($row['payment_date'] ?? ''),
        'note' => (string) ($row['note'] ?? ''),
    ];
}

$suppliers = array_map('shapeSupplier', supplierRows());
usort($suppliers, function (array $a, array $b): int {
    $due = $b['outstanding'] <=> $a['outstanding'];
    if ($due !== 0) {
        return $due;
    }
    return strcasecmp($a['supplier_name'], $b['supplier_name']);
});

$bills = array_map('shapeBill', billRows());
usort($bills, function (array $a, array $b): int {
    return strcmp($a['invoice_date'], $b['invoice_date']);
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
    $supplier = null;
    foreach ($suppliers as $row) {
        if ((int) $row['supplier_id'] === $id) {
            $supplier = $row;
            break;
        }
    }
    if (!$supplier) {
        Json::error('Supplier not found.', 404);
    }
    $supplierBills = array_values(array_filter($bills, fn ($row) => (int) $row['supplier_id'] === $id));
    $supplierPayments = array_values(array_filter($payments, fn ($row) => (int) $row['supplier_id'] === $id));
    Json::ok(['data' => [
        'supplier' => $supplier,
        'bills' => $supplierBills,
        'payments' => $supplierPayments,
    ]]);
}

$summary = [
    'suppliers' => count($suppliers),
    'with_due' => 0,
    'overdue' => 0,
    'settled' => 0,
    'outstanding' => 0.0,
    'open_bills' => 0,
    'billed' => 0.0,
    'payments' => 0.0,
];
foreach ($suppliers as $row) {
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
    'suppliers' => $suppliers,
    'bills' => $bills,
    'payments' => $payments,
]]);
