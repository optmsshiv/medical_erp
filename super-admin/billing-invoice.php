<?php
/** Super-admin invoice view/print. */
require __DIR__ . '/_guard.php';
require dirname(__DIR__) . '/core/Database.php';

$fail = function (string $msg): void {
    http_response_code(404);
    echo '<div style="font-family:sans-serif;max-width:560px;margin:14vh auto;text-align:center;color:#5B6270">'
       . '<h2 style="color:#1A1D27">Invoice unavailable</h2><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p></div>';
    exit;
};

$id = (int) ($_GET['invoice_id'] ?? 0);
try {
    $pdo = Database::master();
    $row = $pdo->query('SELECT ci.*, cl.name AS c_name, cl.subdomain AS c_sub FROM client_invoices ci JOIN clients cl ON cl.id = ci.client_id WHERE ci.id = ' . $id . ' LIMIT 1')->fetch();
    if (!$row) { $fail('Invoice not found.'); }
    $kv = [];
    foreach ($pdo->query('SELECT `key`,`value` FROM billing_settings')->fetchAll() as $r) { $kv[$r['key']] = $r['value']; }
} catch (Throwable $e) { $fail('Billing tables unavailable — run the billing migration.'); }

require __DIR__ . '/actions/invoice-doc.php';
echo renderInvoiceDocument([
    'invoice_no'  => $row['invoice_no'], 'plan' => $row['plan'],
    'period_from' => $row['period_from'], 'period_to' => $row['period_to'],
    'amount' => $row['amount'], 'status' => $row['status'],
    'due_date' => $row['due_date'], 'paid_at' => $row['paid_at'], 'created_at' => $row['created_at'],
    'seller' => ['name' => $kv['company_name'] ?? 'OPTMS·RX', 'addr' => $kv['company_addr'] ?? '',
                 'contact' => $kv['company_contact'] ?? '', 'gstin' => $kv['company_gstin'] ?? ''],
    'buyer' => ['name' => $row['c_name'], 'portal' => $row['c_sub'] . '.' . (string) (getenv('APP_DOMAIN') ?: '')],
    'payNote' => $kv['pay_note'] ?? '', 'upiId' => $kv['upi_id'] ?? '',
    'autoPrint' => isset($_GET['print']),
]);
