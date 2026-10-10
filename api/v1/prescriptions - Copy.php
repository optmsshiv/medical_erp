<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Manufacturer.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

/**
 * Recorded prescriptions. Not the sales report.
 * Tables come from database/migrations/2026_09_27_prescriptions.sql.
 */
function queryRows(string $sql): array
{
    $rows = Manufacturer::query($sql);
    return is_array($rows) ? $rows : [];
}

function sqlStr(string $value): string
{
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
}

function sqlNull(string $value): string
{
    $value = trim($value);
    return $value === '' ? 'NULL' : sqlStr($value);
}

function clip(string $value, int $max): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }
    return substr($value, 0, $max);
}

function dateOnly($value): string
{
    $text = substr((string) $value, 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) ? $text : '';
}

function flowStatus(string $status): string
{
    $status = trim($status);
    if ($status === '' || strcasecmp($status, 'Recorded') === 0 || strcasecmp($status, 'Pending') === 0) {
        return 'Pending';
    }
    if (strcasecmp($status, 'Ready') === 0) {
        return 'Ready';
    }
    if (strcasecmp($status, 'Dispensed') === 0 || strcasecmp($status, 'Completed') === 0) {
        return 'Dispensed';
    }
    if (strcasecmp($status, 'Cancelled') === 0) {
        return 'Cancelled';
    }
    return 'Pending';
}

function shapeRx(array $row): array
{
    $age = $row['patient_age'] ?? null;
    return [
        'id' => (int) ($row['id'] ?? 0),
        'rx_no' => (string) ($row['rx_no'] ?? ''),
        'rx_date' => dateOnly($row['rx_date'] ?? ''),
        'customer_id' => isset($row['customer_id']) && $row['customer_id'] !== null ? (int) $row['customer_id'] : null,
        'patient_name' => (string) ($row['patient_name'] ?? ''),
        'patient_age' => $age === null || $age === '' ? null : (int) $age,
        'patient_phone' => (string) ($row['patient_phone'] ?? ''),
        'doctor_id' => isset($row['doctor_id']) && $row['doctor_id'] !== null ? (int) $row['doctor_id'] : null,
        'doctor_name' => (string) ($row['doctor_name'] ?? ''),
        'specialty' => (string) ($row['specialty'] ?? ''),
        'diagnosis' => (string) ($row['diagnosis'] ?? ''),
        'status' => flowStatus((string) ($row['status'] ?? 'Pending')),
        'item_count' => (int) ($row['item_count'] ?? 0),
        'medicines' => is_array($row['medicines'] ?? null) ? $row['medicines'] : [],
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}

function medicineMap(): array
{
    try {
        $rows = queryRows('SELECT prescription_id, medicine_id, medicine_name, qty
            FROM prescription_items ORDER BY id ASC');
    } catch (Throwable $e) {
        return [];
    }
    $map = [];
    foreach ($rows as $row) {
        $id = (int) ($row['prescription_id'] ?? 0);
        if (!$id) {
            continue;
        }
        $map[$id][] = [
            'medicine_id' => isset($row['medicine_id']) && $row['medicine_id'] !== null ? (int) $row['medicine_id'] : null,
            'medicine_name' => (string) ($row['medicine_name'] ?? ''),
            'qty' => (int) ($row['qty'] ?? 1),
        ];
    }
    return $map;
}

function shapeItem(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'prescription_id' => (int) ($row['prescription_id'] ?? 0),
        'medicine_id' => isset($row['medicine_id']) && $row['medicine_id'] !== null ? (int) $row['medicine_id'] : null,
        'medicine_name' => (string) ($row['medicine_name'] ?? ''),
        'dosage' => (string) ($row['dosage'] ?? ''),
        'frequency' => (string) ($row['frequency'] ?? ''),
        'duration' => (string) ($row['duration'] ?? ''),
        'qty' => (int) ($row['qty'] ?? 1),
        'instructions' => (string) ($row['instructions'] ?? ''),
    ];
}

function listRows(): array
{
    $withAge = 'SELECT p.id AS id, p.rx_no AS rx_no, p.rx_date AS rx_date, p.customer_id AS customer_id,
            p.patient_name AS patient_name, p.patient_age AS patient_age, p.patient_phone AS patient_phone, p.doctor_id AS doctor_id,
            COALESCE(d.name, \'\') AS doctor_name, COALESCE(d.specialty, \'\') AS specialty,
            COALESCE(p.diagnosis, \'\') AS diagnosis, p.status AS status, p.created_at AS created_at,
            COALESCE(it.item_count, 0) AS item_count
         FROM prescriptions p
         LEFT JOIN doctors d ON d.id = p.doctor_id
         LEFT JOIN (
           SELECT prescription_id, COUNT(*) AS item_count
           FROM prescription_items GROUP BY prescription_id
         ) it ON it.prescription_id = p.id';
    $ageOnly = 'SELECT p.id AS id, p.rx_no AS rx_no, p.rx_date AS rx_date, p.customer_id AS customer_id,
            p.patient_name AS patient_name, p.patient_age AS patient_age, p.doctor_id AS doctor_id,
            COALESCE(d.name, \'\') AS doctor_name, COALESCE(d.specialty, \'\') AS specialty,
            COALESCE(p.diagnosis, \'\') AS diagnosis, p.status AS status, p.created_at AS created_at,
            COALESCE(it.item_count, 0) AS item_count
         FROM prescriptions p
         LEFT JOIN doctors d ON d.id = p.doctor_id
         LEFT JOIN (
           SELECT prescription_id, COUNT(*) AS item_count
           FROM prescription_items GROUP BY prescription_id
         ) it ON it.prescription_id = p.id';
    try {
        return queryRows($withAge);
    } catch (Throwable $e) {
        try {
            return queryRows($ageOnly);
        } catch (Throwable $eAge) {
        }
        $fallback = 'SELECT id, rx_no, rx_date, customer_id, patient_name, doctor_id, doctor_name, specialty,
                   diagnosis, status, item_count, created_at
            FROM v_prescriptions';
        try {
            return queryRows($fallback);
        } catch (Throwable $e2) {
            return queryRows('SELECT p.id AS id, p.rx_no AS rx_no, p.rx_date AS rx_date, p.customer_id AS customer_id,
                p.patient_name AS patient_name, p.doctor_id AS doctor_id,
                COALESCE(d.name, \'\') AS doctor_name, COALESCE(d.specialty, \'\') AS specialty,
                COALESCE(p.diagnosis, \'\') AS diagnosis, p.status AS status, p.created_at AS created_at,
                COALESCE(it.item_count, 0) AS item_count
             FROM prescriptions p
             LEFT JOIN doctors d ON d.id = p.doctor_id
             LEFT JOIN (
               SELECT prescription_id, COUNT(*) AS item_count
               FROM prescription_items GROUP BY prescription_id
             ) it ON it.prescription_id = p.id');
        }
    }
}

function itemRows(int $id): array
{
    return queryRows('SELECT id, prescription_id, medicine_id, medicine_name, dosage, frequency, duration, qty, instructions
        FROM prescription_items WHERE prescription_id = ' . $id . ' ORDER BY id ASC');
}

function nextRxNo(): string
{
    $rows = queryRows('SELECT rx_no FROM prescriptions ORDER BY id DESC LIMIT 1');
    $n = 1;
    if ($rows && preg_match('/(\d+)$/', (string) ($rows[0]['rx_no'] ?? ''), $m)) {
        $n = ((int) $m[1]) + 1;
    }
    return 'RX-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $names = medicineMap();
        $rows = array_map(function (array $row) use ($names): array {
            $id = (int) ($row['id'] ?? 0);
            $meds = $names[$id] ?? [];
            $row['medicines'] = $meds;
            if (!$row['item_count'] && $meds) {
                $row['item_count'] = count($meds);
            }
            return shapeRx($row);
        }, listRows());
    } catch (Throwable $e) {
        Json::error('Prescription tables are not installed. Run database/migrations/2026_09_27_prescriptions.sql.', 503);
    }
    usort($rows, function (array $a, array $b): int {
        $date = strcmp($b['rx_date'], $a['rx_date']);
        return $date !== 0 ? $date : ($b['id'] <=> $a['id']);
    });
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $rx = null;
        foreach ($rows as $row) {
            if ((int) $row['id'] === $id) {
                $rx = $row;
                break;
            }
        }
        if (!$rx) {
            Json::error('Prescription not found.', 404);
        }
        try {
            $items = array_map('shapeItem', itemRows($id));
        } catch (Throwable $e) {
            $items = [];
        }
        Json::ok(['data' => ['prescription' => $rx, 'items' => $items]]);
    }
    Json::ok(['data' => ['ledger' => $rows]]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $patient = clip((string) ($input['patient_name'] ?? $input['patientName'] ?? ''), 150);
    $age = (int) ($input['patient_age'] ?? $input['age'] ?? 0);
    if ($age < 0 || $age > 120) {
        $age = 0;
    }
    $phone = clip((string) ($input['patient_phone'] ?? $input['phone'] ?? $input['mobile'] ?? ''), 32);
    $date = dateOnly($input['rx_date'] ?? $input['date'] ?? '');
    $diagnosis = clip((string) ($input['diagnosis'] ?? $input['notes'] ?? ''), 255);
    $doctorId = (int) ($input['doctor_id'] ?? $input['doctorId'] ?? 0);
    $customerId = (int) ($input['customer_id'] ?? $input['customerId'] ?? 0);
    $items = $input['items'] ?? [];
    if (!is_array($items)) {
        $items = [];
    }

    if ($patient === '') {
        Json::error('Patient name is required.', 422);
    }
    if ($date === '') {
        Json::error('Prescription date is required.', 422);
    }

    $lines = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = clip((string) ($item['medicine_name'] ?? $item['medicineName'] ?? $item['name'] ?? ''), 200);
        if ($name === '') {
            continue;
        }
        $qty = (int) ($item['qty'] ?? 1);
        if ($qty < 1) {
            $qty = 1;
        }
        if ($qty > 9999) {
            $qty = 9999;
        }
        $lines[] = [
            'medicine_id' => (int) ($item['medicine_id'] ?? $item['medicineId'] ?? 0),
            'medicine_name' => $name,
            'dosage' => clip((string) ($item['dosage'] ?? ''), 80),
            'frequency' => clip((string) ($item['frequency'] ?? ''), 80),
            'duration' => clip((string) ($item['duration'] ?? ''), 80),
            'qty' => $qty,
            'instructions' => clip((string) ($item['instructions'] ?? ''), 255),
        ];
    }
    if (!$lines) {
        Json::error('Add at least one medicine.', 422);
    }

    try {
        $rxNo = nextRxNo();
        $ageSql = $age > 0 ? (string) $age : 'NULL';
        try {
            queryRows('INSERT INTO prescriptions (rx_no, rx_date, customer_id, patient_name, patient_age, patient_phone, doctor_id, diagnosis, status)
                VALUES (' . sqlStr($rxNo) . ', ' . sqlStr($date) . ', '
                . ($customerId > 0 ? $customerId : 'NULL') . ', '
                . sqlStr($patient) . ', ' . $ageSql . ', ' . sqlNull($phone) . ', '
                . ($doctorId > 0 ? $doctorId : 'NULL') . ', '
                . sqlNull($diagnosis) . ", 'Pending')");
        } catch (Throwable $e) {
            try {
                queryRows('INSERT INTO prescriptions (rx_no, rx_date, customer_id, patient_name, patient_age, doctor_id, diagnosis, status)
                    VALUES (' . sqlStr($rxNo) . ', ' . sqlStr($date) . ', '
                    . ($customerId > 0 ? $customerId : 'NULL') . ', '
                    . sqlStr($patient) . ', ' . $ageSql . ', '
                    . ($doctorId > 0 ? $doctorId : 'NULL') . ', '
                    . sqlNull($diagnosis) . ", 'Pending')");
            } catch (Throwable $eAge) {
                queryRows('INSERT INTO prescriptions (rx_no, rx_date, customer_id, patient_name, doctor_id, diagnosis, status)
                    VALUES (' . sqlStr($rxNo) . ', ' . sqlStr($date) . ', '
                    . ($customerId > 0 ? $customerId : 'NULL') . ', '
                    . sqlStr($patient) . ', '
                    . ($doctorId > 0 ? $doctorId : 'NULL') . ', '
                    . sqlNull($diagnosis) . ", 'Pending')");
            }
        }
        $found = queryRows('SELECT id FROM prescriptions WHERE rx_no = ' . sqlStr($rxNo) . ' LIMIT 1');
        $id = (int) ($found[0]['id'] ?? 0);
        if (!$id) {
            Json::error('Could not save the prescription.', 500);
        }
        // Rx photo support (Scan & Send): column self-heals, the write is best-effort —
        // a legacy table that refuses it still keeps the photo in the inbox store.
        $imagePath = substr(trim((string) ($input['image_path'] ?? '')), 0, 190);
        if ($imagePath !== '' && preg_match('#^rx-inbox-store/[A-Za-z0-9._-]+$#', $imagePath)) {
            try { queryRows("ALTER TABLE prescriptions ADD COLUMN image_path VARCHAR(255) NULL"); }
            catch (Throwable $e) { /* already there */ }
            try { queryRows('UPDATE prescriptions SET image_path = ' . sqlStr($imagePath) . ' WHERE id = ' . $id); }
            catch (Throwable $e) { /* refused — non-blocking */ }
        }
        foreach ($lines as $line) {
            queryRows('INSERT INTO prescription_items (prescription_id, medicine_id, medicine_name, dosage, frequency, duration, qty, instructions)
                VALUES (' . $id . ', '
                . ($line['medicine_id'] > 0 ? $line['medicine_id'] : 'NULL') . ', '
                . sqlStr($line['medicine_name']) . ', '
                . sqlNull($line['dosage']) . ', '
                . sqlNull($line['frequency']) . ', '
                . sqlNull($line['duration']) . ', '
                . $line['qty'] . ', '
                . sqlNull($line['instructions']) . ')');
        }
    } catch (Throwable $e) {
        Json::error('Prescription tables are not installed. Run database/migrations/2026_09_27_prescriptions.sql.', 503);
    }

    Json::ok(['id' => $id, 'rx_no' => $rxNo]);
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    if (!$id) {
        Json::error('Prescription not found.', 404);
    }
    $current = null;
    foreach (listRows() as $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            $current = $row;
            break;
        }
    }
    if (!$current) {
        Json::error('Prescription not found.', 404);
    }
    $editing = array_key_exists('patient_name', $input) || array_key_exists('items', $input);
    if ($editing && flowStatus((string) ($current['status'] ?? '')) !== 'Pending') {
        Json::error('Only a pending prescription can be edited.', 409);
    }
    if ($editing) {
        $patient = clip((string) ($input['patient_name'] ?? ''), 150);
        $age = (int) ($input['patient_age'] ?? $input['age'] ?? 0);
        if ($age < 0 || $age > 120) {
            $age = 0;
        }
        $phone = clip((string) ($input['patient_phone'] ?? $input['phone'] ?? $input['mobile'] ?? ''), 32);
        $date = dateOnly($input['rx_date'] ?? $input['date'] ?? '');
        $diagnosis = clip((string) ($input['diagnosis'] ?? $input['notes'] ?? ''), 255);
        $doctorId = (int) ($input['doctor_id'] ?? $input['doctorId'] ?? 0);
        $customerId = (int) ($input['customer_id'] ?? $input['customerId'] ?? 0);
        $items = is_array($input['items'] ?? null) ? $input['items'] : [];
        if ($patient === '' || $date === '') {
            Json::error('Patient name and date are required.', 422);
        }
        $lines = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = clip((string) ($item['medicine_name'] ?? $item['name'] ?? ''), 200);
            if ($name === '') {
                continue;
            }
            $qty = (int) ($item['qty'] ?? 1);
            if ($qty < 1) {
                $qty = 1;
            }
            $lines[] = [
                'medicine_id' => (int) ($item['medicine_id'] ?? $item['medicineId'] ?? 0),
                'medicine_name' => $name,
                'dosage' => clip((string) ($item['dosage'] ?? ''), 80),
                'frequency' => clip((string) ($item['frequency'] ?? ''), 80),
                'duration' => clip((string) ($item['duration'] ?? ''), 80),
                'qty' => $qty,
                'instructions' => clip((string) ($item['instructions'] ?? ''), 255),
            ];
        }
        if (!$lines) {
            Json::error('Add at least one medicine.', 422);
        }
        $ageSql = $age > 0 ? (string) $age : 'NULL';
        try {
            queryRows('UPDATE prescriptions SET
                rx_date = ' . sqlStr($date) . ',
                customer_id = ' . ($customerId > 0 ? $customerId : 'NULL') . ',
                patient_name = ' . sqlStr($patient) . ',
                patient_age = ' . $ageSql . ',
                patient_phone = ' . sqlNull($phone) . ',
                doctor_id = ' . ($doctorId > 0 ? $doctorId : 'NULL') . ',
                diagnosis = ' . sqlNull($diagnosis) . '
                WHERE id = ' . $id);
        } catch (Throwable $e) {
            try {
                queryRows('UPDATE prescriptions SET
                    rx_date = ' . sqlStr($date) . ',
                    customer_id = ' . ($customerId > 0 ? $customerId : 'NULL') . ',
                    patient_name = ' . sqlStr($patient) . ',
                    patient_age = ' . $ageSql . ',
                    doctor_id = ' . ($doctorId > 0 ? $doctorId : 'NULL') . ',
                    diagnosis = ' . sqlNull($diagnosis) . '
                    WHERE id = ' . $id);
            } catch (Throwable $eAge) {
                queryRows('UPDATE prescriptions SET
                    rx_date = ' . sqlStr($date) . ',
                    customer_id = ' . ($customerId > 0 ? $customerId : 'NULL') . ',
                    patient_name = ' . sqlStr($patient) . ',
                    doctor_id = ' . ($doctorId > 0 ? $doctorId : 'NULL') . ',
                    diagnosis = ' . sqlNull($diagnosis) . '
                    WHERE id = ' . $id);
            }
        }
        queryRows('DELETE FROM prescription_items WHERE prescription_id = ' . $id);
        foreach ($lines as $line) {
            queryRows('INSERT INTO prescription_items (prescription_id, medicine_id, medicine_name, dosage, frequency, duration, qty, instructions)
                VALUES (' . $id . ', '
                . ($line['medicine_id'] > 0 ? $line['medicine_id'] : 'NULL') . ', '
                . sqlStr($line['medicine_name']) . ', '
                . sqlNull($line['dosage']) . ', '
                . sqlNull($line['frequency']) . ', '
                . sqlNull($line['duration']) . ', '
                . $line['qty'] . ', '
                . sqlNull($line['instructions']) . ')');
        }
    }
    if (array_key_exists('status', $input) && trim((string) $input['status']) !== '') {
        $raw = trim((string) $input['status']);
        $known = ['Pending', 'Ready', 'Dispensed', 'Cancelled', 'Recorded', 'Completed'];
        if (!in_array($raw, $known, true)) {
            Json::error('Status must be Pending, Ready, Dispensed, or Cancelled.', 422);
        }
        $status = flowStatus($raw);
        queryRows('UPDATE prescriptions SET status = ' . sqlStr($status) . ' WHERE id = ' . $id);
        Json::ok(['id' => $id, 'status' => $status]);
    }
    Json::ok(['id' => $id]);
}

Json::error('Method not allowed.', 405);
