<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php';

/** Tenant invoice view/print — an invoice is only ever served to its OWN tenant. */
$fail = function (string $msg): void {
    http_response_code(404);
    echo '<div style="font-family:sans-serif;max-width:560px;margin:14vh auto;text-align:center;color:#5B6270">'
       . '<h2 style="color:#1A1D27">Invoice unavailable</h2><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p></div>';
    exit;
};

$inv = (int) ($_GET['inv'] ?? 0);
if ($inv <= 0) { $fail('Missing invoice reference.'); }

try {
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    $host = preg_replace('/:\d+$/', '', $host);
    $base = strtolower((string) (getenv('APP_DOMAIN') ?: ''));
    if ($base === '' || $host === $base || substr($host, -strlen('.' . $base)) !== '.' . $base) { $fail('Tenant could not be resolved.'); }
    $sub = substr($host, 0, -strlen('.' . $base));
    $pdo = Database::master();
    $client = $pdo->query('SELECT * FROM clients WHERE subdomain = ' . $pdo->quote($sub) . ' LIMIT 1')->fetch();
    if (!$client) { $fail('Tenant not registered.'); }
    $row = $pdo->query('SELECT * FROM client_invoices WHERE id = ' . $inv . ' AND client_id = ' . (int) $client['id'] . " AND status <> 'void' LIMIT 1")->fetch();
    if (!$row) { $fail('This invoice does not belong to your store.'); }   // ownership gate — never leak another tenant's invoice
    $kv = [];
    foreach ($pdo->query('SELECT `key`,`value` FROM billing_settings')->fetchAll() as $r) { $kv[$r['key']] = $r['value']; }
} catch (Throwable $e) { $fail('Billing service is temporarily unavailable.'); }

require __DIR__ . '/super-admin/actions/invoice-doc.php';
echo renderInvoiceDocument([
    'invoice_no'  => $row['invoice_no'], 'plan' => $row['plan'],
    'period_from' => $row['period_from'], 'period_to' => $row['period_to'],
    'amount' => $row['amount'], 'status' => $row['status'],
    'due_date' => $row['due_date'], 'paid_at' => $row['paid_at'], 'created_at' => $row['created_at'],
    'seller' => ['name' => $kv['company_name'] ?? 'OPTMS·RX', 'addr' => $kv['company_addr'] ?? '',
                 'contact' => $kv['company_contact'] ?? '', 'gstin' => $kv['company_gstin'] ?? '', 'email' => $kv['company_email'] ?? ''],
    'buyer' => ['name' => $client['name'], 'portal' => $client['subdomain'] . '.' . (getenv('APP_DOMAIN') ?: '')],
    'payNote' => $kv['pay_note'] ?? '', 'upiId' => $kv['upi_id'] ?? '',
    'autoPrint' => isset($_GET['print']),
]);
