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
 * Compliance register read — Schedule H1 & Schedule X (Drugs and Cosmetics
 * Rules, 1945, Rule 65 register).
 *
 * Rows are the sales-ledger truth: every billed unit of a medicine tagged
 * H1/X on the medicine master, with the recorded patient (billing customer),
 * recorded prescriber, batch, and billed quantity. Nothing is inferred or
 * filled in — the page flags the gaps so the shop can self-audit before an
 * inspection does.
 *
 * GET ?from=YYYY-MM-DD&to=YYYY-MM-DD&cls=all|h1|x
 */
function cqr(string $sql, array $params = []): array
{
    $rows = Manufacturer::query($sql, $params);
    return is_array($rows) ? $rows : [];
}

function cDay($value, string $fallback): string
{
    $text = substr((string) $value, 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) ? $text : $fallback;
}

/* Rx-billed walk-ins keep their patient on the bill (patient_name column).
   Older installs lack it — resolve from the billing customer instead. */
$hasPat = false;
try {
    $hasPat = (bool) cqr("SHOW COLUMNS FROM sales LIKE 'patient_name'");
} catch (Throwable $e) { /* customer fallback */ }
$patientSel = $hasPat
    ? "COALESCE(NULLIF(s.patient_name, ''), c.name, '')"
    : "COALESCE(c.name, '')";

$today = date('Y-m-d');
$from = cDay($_GET['from'] ?? '', '1900-01-01');
$to   = cDay($_GET['to'] ?? '', $today);
if ($from > $to) {
    Json::error('From-date cannot be after the to-date.', 422);
}

$cls = strtolower(trim((string) ($_GET['cls'] ?? 'all')));
$codes = $cls === 'h1' ? "'H1'" : ($cls === 'x' ? "'X'" : "'H1','X'");

try {
    $rows = cqr("SELECT s.id AS sale_id, s.invoice_no, s.sale_date, s.created_at,
            {$patientSel} AS patient_name, COALESCE(c.phone, '') AS patient_phone,
            COALESCE(c.address, '') AS patient_address,
            COALESCE(d.name, '') AS doctor_name, COALESCE(d.reg_no, '') AS doctor_reg_no,
            m.name AS medicine_name, COALESCE(m.schedule_class, '') AS schedule_class,
            COALESCE(b.batch_no, '') AS batch_no,
            b.expiry_date AS expiry_date,
            si.qty AS qty, COALESCE(rt.returned, 0) AS returned
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id
        JOIN medicines m ON m.id = si.medicine_id
        LEFT JOIN batches b ON b.id = si.batch_id
        LEFT JOIN customers c ON c.id = s.customer_id
        LEFT JOIN doctors d ON d.id = s.doctor_id
        LEFT JOIN (
            SELECT sale_item_id, COALESCE(SUM(qty), 0) AS returned
            FROM sales_return_items GROUP BY sale_item_id
        ) rt ON rt.sale_item_id = si.id
        WHERE UPPER(COALESCE(m.schedule_class, '')) IN ({$codes})
          AND si.qty > 0
          /* sale_date may carry a time part on some installs — a plain
             <= :to would be midnight of that day and drop bills made
             later, so compare strictly against the day after. */
          AND s.sale_date >= :from AND s.sale_date < DATE_ADD(:to, INTERVAL 1 DAY)
        ORDER BY s.sale_date ASC, s.id ASC, si.id ASC
        LIMIT 5000", ['from' => $from, 'to' => $to]);
} catch (Throwable $e) {
    Json::error('Could not read the register from the sales ledger.', 500);
}

$out = [];
$summary = [
    'entries' => 0,
    'qty' => 0,
    'medicines' => 0,
    'h1_entries' => 0,
    'x_entries' => 0,
    'missing_doctor' => 0,
    'missing_patient' => 0,
];
$medSet = [];

foreach ($rows as $r) {
    $sched = strtoupper(trim((string) ($r['schedule_class'] ?? '')));
    $patient = trim((string) ($r['patient_name'] ?? ''));
    $doctor = trim((string) ($r['doctor_name'] ?? ''));
    $qty = (int) ($r['qty'] ?? 0);

    $summary['entries']++;
    $summary['qty'] += $qty;
    $medSet[(string) ($r['medicine_name'] ?? '')] = true;
    if ($sched === 'H1') { $summary['h1_entries']++; }
    if ($sched === 'X') { $summary['x_entries']++; }
    if ($doctor === '') { $summary['missing_doctor']++; }
    if ($patient === '') { $summary['missing_patient']++; }

    $out[] = [
        'sale_id' => (int) ($r['sale_id'] ?? 0),
        'invoice_no' => (string) ($r['invoice_no'] ?? ''),
        'sale_date' => cDay($r['sale_date'] ?? '', ''),
        'sale_time' => substr((string) ($r['created_at'] ?? ''), 11, 5),
        'patient_name' => $patient,
        'patient_phone' => (string) ($r['patient_phone'] ?? ''),
        'patient_address' => (string) ($r['patient_address'] ?? ''),
        'doctor_name' => $doctor,
        'doctor_reg_no' => (string) ($r['doctor_reg_no'] ?? ''),
        'medicine_name' => (string) ($r['medicine_name'] ?? ''),
        'schedule_class' => $sched,
        'batch_no' => (string) ($r['batch_no'] ?? ''),
        'expiry_date' => cDay($r['expiry_date'] ?? '', ''),
        'qty' => $qty,
        'returned' => (int) ($r['returned'] ?? 0),
    ];
}

$summary['medicines'] = count($medSet);

Json::ok(['data' => [
    'rows' => $out,
    'summary' => $summary,
    'period' => ['from' => $from, 'to' => $to, 'cls' => $cls === 'h1' || $cls === 'x' ? $cls : 'all'],
]]);
