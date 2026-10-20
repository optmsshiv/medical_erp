<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Manufacturer.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Json::error('Method not allowed.', 405);
}

/**
 * Prescribing-doctor persistence for posted invoices.
 *
 * Why this exists: checkout (pos.js → sales.php) now also sends doctorId with
 * the sale, but whether that key is persisted depends on a server-side writer
 * this endpoint cannot control. To make doctor capture reliable either way,
 * pos.js calls this right after a successful checkout, and the Sales Invoices
 * page calls it when a doctor is attached/changed on a posted invoice.
 *
 * Targeting is by invoice_no (unique across the sales table), never by
 * trusting a client-supplied row id blindly.
 */
$in = json_decode(file_get_contents('php://input'), true) ?? [];

$invoiceNo = trim((string) ($in['invoiceNo'] ?? ''));
$doctorId  = (int) ($in['doctorId'] ?? 0);

if ($invoiceNo === '' || !preg_match('/^[A-Za-z0-9\/\-]{1,30}$/', $invoiceNo)) {
    Json::error('A valid invoiceNo is required.', 422);
}
if ($doctorId <= 0) {
    Json::error('A valid doctorId is required. Pass nullDoctor to clear instead.', 422);
}

try {
    $doc = Manufacturer::query('SELECT id FROM doctors WHERE id = :id LIMIT 1', ['id' => $doctorId]);
    if (!$doc) {
        Json::error('Doctor not found.', 422);
    }
    $row = Manufacturer::query('SELECT id FROM sales WHERE invoice_no = :n LIMIT 1', ['n' => $invoiceNo]);
    if (!$row) {
        Json::error('Invoice not found.', 404);
    }
    Manufacturer::query('UPDATE sales SET doctor_id = :d WHERE invoice_no = :n', ['d' => $doctorId, 'n' => $invoiceNo]);
    Json::ok(['invoiceNo' => $invoiceNo, 'doctorId' => $doctorId]);
} catch (Throwable $e) {
    Json::error('Could not save the doctor on this invoice.', 500);
}
