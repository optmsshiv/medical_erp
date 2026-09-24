<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/core/Audit.php';
require dirname(__DIR__, 2) . '/models/Expense.php';
require dirname(__DIR__, 2) . '/models/ExpenseCategory.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$pdo = Tenant::db();

switch ($method) {
    case 'GET':
        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to   = $_GET['to'] ?? date('Y-m-d');

        $stmt = $pdo->prepare(
            'SELECT e.*, c.name AS category_name FROM expenses e
             JOIN expense_categories c ON c.id = e.category_id
             WHERE e.expense_date BETWEEN :from AND :to
             ORDER BY e.expense_date DESC, e.id DESC'
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $rows = array_map(fn($r) => [
            'id' => (int) $r['id'], 'category' => $r['category_name'], 'amount' => (float) $r['amount'],
            'date' => $r['expense_date'], 'mode' => $r['payment_mode'], 'note' => $r['note'] ?? '',
        ], $stmt->fetchAll());

        $categories = ExpenseCategory::all('name');

        Json::ok(['data' => $rows, 'categories' => $categories, 'range' => ['from' => $from, 'to' => $to]]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $categoryId = (int) ($input['categoryId'] ?? 0);
        $amount = (float) ($input['amount'] ?? 0);
        $date = $input['date'] ?? date('Y-m-d');
        $mode = $input['mode'] ?? 'Cash';

        if (!$categoryId || !ExpenseCategory::find($categoryId)) {
            Json::error('Select a valid category.', 422);
        }
        if ($amount <= 0) {
            Json::error('Enter a valid amount.', 422);
        }
        if (!in_array($mode, ['Cash', 'Bank', 'UPI', 'Cheque'], true)) {
            $mode = 'Cash';
        }

        $id = Expense::create([
            'category_id'  => $categoryId,
            'amount'       => $amount,
            'expense_date' => $date,
            'payment_mode' => $mode,
            'note'         => trim($input['note'] ?? ''),
        ]);

        Audit::log('EXPENSE_ADD', "₹{$amount} · " . ($input['note'] ?? ''));
        Json::ok(['id' => $id]);
        break;

    case 'DELETE':
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id || !Expense::find($id)) {
            Json::error('Expense not found.', 404);
        }
        Expense::delete($id);
        Audit::log('EXPENSE_DELETE', "Expense #{$id}");
        Json::ok();
        break;

    default:
        Json::error('Method not allowed.', 405);
}
