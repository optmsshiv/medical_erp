<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$pdo = Tenant::db();

$doctorId = (int) ($_GET['doctorId'] ?? 0);

$sql = "SELECT s.id, s.invoice_no, s.sale_date, s.grand_total, c.name AS customer_name,
               d.name AS doctor_name, d.specialty,
               (SELECT COUNT(*) FROM sale_items si JOIN medicines m ON m.id = si.medicine_id
                WHERE si.sale_id = s.id AND m.rx_required = 1) AS rx_item_count
        FROM sales s
        JOIN customers c ON c.id = s.customer_id
        JOIN doctors d ON d.id = s.doctor_id
        WHERE s.doctor_id IS NOT NULL";
$params = [];
if ($doctorId) {
    $sql .= ' AND s.doctor_id = :doctorId';
    $params['doctorId'] = $doctorId;
}
$sql .= ' ORDER BY s.sale_date DESC, s.id DESC LIMIT 300';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$rows = array_map(fn($r) => [
    'invoiceNo' => $r['invoice_no'], 'date' => $r['sale_date'], 'amount' => (float) $r['grand_total'],
    'customer' => $r['customer_name'], 'doctor' => $r['doctor_name'], 'specialty' => $r['specialty'] ?? '',
    'rxItems' => (int) $r['rx_item_count'],
], $stmt->fetchAll());

Json::ok(['data' => $rows]);
