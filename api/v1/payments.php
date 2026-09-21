<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Customer.php';
require dirname(__DIR__, 2) . '/models/Supplier.php';
require dirname(__DIR__, 2) . '/models/Payment.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Json::error('Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$partyType = $input['partyType'] ?? '';
$partyId   = (int) ($input['partyId'] ?? 0);
$amount    = (float) ($input['amount'] ?? 0);
$mode      = $input['mode'] ?? 'Cash';
$note      = trim($input['note'] ?? '');

if (!in_array($partyType, ['customer', 'supplier'], true)) {
    Json::error('Invalid party type.', 422);
}
if (!in_array($mode, ['Cash', 'Bank', 'UPI', 'Cheque'], true)) {
    $mode = 'Cash';
}
if ($amount <= 0) {
    Json::error('Enter a valid amount.', 422);
}

$partyModel = $partyType === 'customer' ? Customer::class : Supplier::class;
$party = $partyModel::find($partyId);
if (!$party) {
    Json::error('Party not found.', 404);
}

$invoiceTable = $partyType === 'customer' ? 'sales' : 'purchases';
$partyColumn  = $partyType === 'customer' ? 'customer_id' : 'supplier_id';
$dateColumn   = $partyType === 'customer' ? 'sale_date' : 'invoice_date';

$pdo = Tenant::db();

try {
    $pdo->beginTransaction();

    // Lock every outstanding invoice for this party, oldest first.
    $stmt = $pdo->prepare(
        "SELECT * FROM {$invoiceTable} WHERE {$partyColumn} = :id AND balance_due > 0
         ORDER BY {$dateColumn} ASC, id ASC FOR UPDATE"
    );
    $stmt->execute(['id' => $partyId]);
    $outstanding = $stmt->fetchAll();

    $totalDue = array_sum(array_column($outstanding, 'balance_due'));

    if ($amount > $totalDue + 0.5) {
        throw new RuntimeException('Amount exceeds outstanding due of ' . number_format($totalDue, 2) . '.');
    }

    $paymentId = Payment::create([
        'party_type'   => $partyType,
        'party_id'     => $partyId,
        'amount'       => $amount,
        'mode'         => $mode,
        'payment_date' => date('Y-m-d'),
        'note'         => $note,
    ]);

    // Apply FIFO against the oldest outstanding invoices until exhausted.
    $remaining = $amount;
    foreach ($outstanding as $inv) {
        if ($remaining <= 0) {
            break;
        }
        $applied = min($remaining, (float) $inv['balance_due']);
        $stmt = $pdo->prepare(
            "UPDATE {$invoiceTable} SET amount_paid = amount_paid + :applied, balance_due = balance_due - :applied2
             WHERE id = :id"
        );
        $stmt->execute(['applied' => $applied, 'applied2' => $applied, 'id' => $inv['id']]);
        $remaining -= $applied;
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    Json::error($e->getMessage() ?: 'Could not record the payment.', 422);
}

Json::ok([
    'id'          => $paymentId,
    'newTotalDue' => max(0, $totalDue - $amount),
]);
