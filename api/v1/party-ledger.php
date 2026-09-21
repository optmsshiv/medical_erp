<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$partyType = $_GET['type'] ?? '';
$partyId   = (int) ($_GET['id'] ?? 0);

if (!in_array($partyType, ['customer', 'supplier'], true) || !$partyId) {
    Json::error('Invalid request.', 422);
}

$pdo = Tenant::db();

if ($partyType === 'customer') {
    $stmt = $pdo->prepare(
        'SELECT id, invoice_no AS no, sale_date AS date, grand_total AS amount, payment_mode AS mode, balance_due
         FROM sales WHERE customer_id = :id ORDER BY sale_date DESC, id DESC'
    );
} else {
    $stmt = $pdo->prepare(
        'SELECT id, invoice_no AS no, invoice_date AS date, grand_total AS amount, payment_mode AS mode, balance_due
         FROM purchases WHERE supplier_id = :id ORDER BY invoice_date DESC, id DESC'
    );
}
$stmt->execute(['id' => $partyId]);
$invoices = array_map(function ($r) {
    return [
        'no' => $r['no'], 'date' => $r['date'], 'amount' => (float) $r['amount'],
        'mode' => $r['mode'], 'due' => (float) $r['balance_due'],
        'status' => $r['balance_due'] <= 0 ? 'Paid' : 'Due',
    ];
}, $stmt->fetchAll());

$stmt = $pdo->prepare(
    'SELECT id, amount, mode, payment_date AS date, note FROM payments
     WHERE party_type = :type AND party_id = :id ORDER BY payment_date DESC, id DESC'
);
$stmt->execute(['type' => $partyType, 'id' => $partyId]);
$payments = array_map(fn($r) => [
    'id' => (int) $r['id'], 'amount' => (float) $r['amount'], 'mode' => $r['mode'],
    'date' => $r['date'], 'note' => $r['note'] ?? '',
], $stmt->fetchAll());

Json::ok(['invoices' => $invoices, 'payments' => $payments]);
