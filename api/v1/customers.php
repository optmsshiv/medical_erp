<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/core/Audit.php';
require dirname(__DIR__, 2) . '/models/Manufacturer.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

/**
 * Customer master. Saves and edits the customer form, including business name
 * and customer type (retail, wholesale, Hospital, Clinic, Others).
 * business_name comes from database/migrations/2026_09_28_customer_profile.sql.
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

function customerType($value): string
{
    $key = strtolower(trim((string) $value));
    $map = [
        'retail' => 'retail',
        'wholesale' => 'wholesale',
        'hospital' => 'Hospital',
        'clinic' => 'Clinic',
        'others' => 'Others',
        'other' => 'Others',
    ];
    return $map[$key] ?? 'retail';
}

function shapeCustomer(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'business_name' => (string) ($row['business_name'] ?? ''),
        'businessName' => (string) ($row['business_name'] ?? ''),
        'type' => (string) ($row['type'] ?? 'retail'),
        'phone' => (string) ($row['phone'] ?? ''),
        'gstin' => (string) ($row['gstin'] ?? ''),
        'dl_no' => (string) ($row['dl_no'] ?? ''),
        'dlNo' => (string) ($row['dl_no'] ?? ''),
        'address' => (string) ($row['address'] ?? ''),
        'credit_limit' => isset($row['credit_limit']) ? (float) $row['credit_limit'] : null,
        'credit_days' => isset($row['credit_days']) ? (int) $row['credit_days'] : null,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'lastPurchase' => isset($row['last_purchase']) && $row['last_purchase'] !== null ? (string) $row['last_purchase'] : null,
        'lastPurchaseAt' => isset($row['last_purchase_at']) && $row['last_purchase_at'] !== null ? (string) $row['last_purchase_at'] : null,
    ];
}

function ensureCreditColumns(): void
{
    // Best-effort migration: business credit policy fields (credit_limit, credit_days).
    // Runs once per request; silently ignored if the column already exists or the
    // database user lacks ALTER rights — the usual extended-then-fallback flow below
    // keeps reads and writes working either way.
    try {
        $cols = queryRows("SHOW COLUMNS FROM customers LIKE 'credit_limit'");
        if (!$cols) {
            queryRows("ALTER TABLE customers ADD credit_limit DECIMAL(12,2) NULL DEFAULT NULL AFTER address");
        }
    } catch (Throwable $e) { /* optional column */ }
    try {
        $cols = queryRows("SHOW COLUMNS FROM customers LIKE 'credit_days'");
        if (!$cols) {
            queryRows("ALTER TABLE customers ADD credit_days INT NULL DEFAULT NULL AFTER credit_limit");
        }
    } catch (Throwable $e) { /* optional column */ }
}

function listRows(): array
{
    $extended = "SELECT c.id, c.name, c.business_name, c.type, c.phone, c.gstin, c.dl_no, c.address, c.credit_limit, c.credit_days, c.created_at,
            (SELECT MAX(s.sale_date) FROM sales s WHERE s.customer_id = c.id) AS last_purchase,
            (SELECT MAX(s.created_at) FROM sales s WHERE s.customer_id = c.id) AS last_purchase_at
        FROM customers c ORDER BY c.name ASC, c.id ASC";
    try {
        ensureCreditColumns();
        return queryRows($extended);
    } catch (Throwable $e) {
        try {
            return queryRows('SELECT id, name, business_name, type, phone, gstin, dl_no, address, created_at
                FROM customers ORDER BY name ASC, id ASC');
        } catch (Throwable $e2) {
            return queryRows('SELECT id, name, type, phone, gstin, dl_no, address, created_at
            FROM customers ORDER BY name ASC, id ASC');
        }
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $rows = array_map('shapeCustomer', listRows());
    } catch (Throwable $e) {
        Json::error('Customers table is not available.', 503);
    }
    Json::ok(['data' => $rows]);
}

if ($method === 'POST') {
    ensureCreditColumns();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = clip((string) ($input['name'] ?? ''), 150);
    $business = clip((string) ($input['business_name'] ?? $input['businessName'] ?? ''), 150);
    $type = customerType($input['type'] ?? 'retail');
    $phone = clip((string) ($input['phone'] ?? ''), 20);
    $gstin = clip((string) ($input['gstin'] ?? ''), 20);
    $dl = clip((string) ($input['dl_no'] ?? $input['dlNo'] ?? ''), 50);
    $address = clip((string) ($input['address'] ?? ''), 255);
    // Credit policy applies to business accounts only; retail rows stay NULL.
    $isRetail = strtolower($type) === 'retail';
    $creditLimit = $isRetail ? null : (float) max(0, (float) ($input['credit_limit'] ?? $input['creditLimit'] ?? 0));
    $creditDays = $isRetail ? null : (int) max(0, (int) ($input['credit_days'] ?? $input['creditDays'] ?? 0));
    $limitSql = $creditLimit && $creditLimit > 0 ? number_format($creditLimit, 2, '.', '') : 'NULL';
    $daysSql = $creditDays && $creditDays > 0 ? (string) $creditDays : 'NULL';
    if ($name === '') {
        Json::error('Customer name is required.', 422);
    }
    try {
        try {
            queryRows('INSERT INTO customers (name, business_name, type, phone, gstin, dl_no, address, credit_limit, credit_days)
                VALUES (' . sqlStr($name) . ', ' . sqlNull($business) . ', ' . sqlStr($type) . ', '
                . sqlNull($phone) . ', ' . sqlNull($gstin) . ', '
                . sqlNull($dl) . ', ' . sqlNull($address) . ', ' . $limitSql . ', ' . $daysSql . ')');
        } catch (Throwable $e) {
            try {
                queryRows('INSERT INTO customers (name, business_name, type, phone, gstin, dl_no, address)
                    VALUES (' . sqlStr($name) . ', ' . sqlNull($business) . ', ' . sqlStr($type) . ', '
                    . sqlNull($phone) . ', ' . sqlNull($gstin) . ', '
                    . sqlNull($dl) . ', ' . sqlNull($address) . ')');
            } catch (Throwable $e2) {
                queryRows('INSERT INTO customers (name, type, phone, gstin, dl_no, address)
                    VALUES (' . sqlStr($name) . ', ' . sqlStr($type) . ', ' . sqlNull($phone) . ', '
                    . sqlNull($gstin) . ', ' . sqlNull($dl) . ', ' . sqlNull($address) . ')');
            }
        }
        $found = queryRows('SELECT id FROM customers WHERE name = ' . sqlStr($name) . ' ORDER BY id DESC LIMIT 1');
        $id = (int) ($found[0]['id'] ?? 0);
        if (!$id) {
            Json::error('Could not save the customer.', 500);
        }
    } catch (Throwable $e) {
        Json::error('Could not save the customer.', 500);
    }
    if (class_exists('Audit')) {
        Audit::log('CUSTOMER_CREATE', $name);
    }
    Json::ok([
        'id' => $id,
        'name' => $name,
        'business_name' => $business,
        'address' => $address,
        'phone' => $phone,
        'type' => $type,
        'credit_limit' => $creditLimit ?: null,
        'credit_days' => $creditDays ?: null,
    ]);
}

if ($method === 'PUT') {
    ensureCreditColumns();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    $name = clip((string) ($input['name'] ?? ''), 150);
    $business = clip((string) ($input['business_name'] ?? $input['businessName'] ?? ''), 150);
    $type = customerType($input['type'] ?? 'retail');
    $phone = clip((string) ($input['phone'] ?? ''), 20);
    $gstin = clip((string) ($input['gstin'] ?? ''), 20);
    $dl = clip((string) ($input['dl_no'] ?? $input['dlNo'] ?? ''), 50);
    $address = clip((string) ($input['address'] ?? ''), 255);
    $isRetail = strtolower($type) === 'retail';
    $creditLimit = $isRetail ? null : (float) max(0, (float) ($input['credit_limit'] ?? $input['creditLimit'] ?? 0));
    $creditDays = $isRetail ? null : (int) max(0, (int) ($input['credit_days'] ?? $input['creditDays'] ?? 0));
    $limitSql = $creditLimit && $creditLimit > 0 ? number_format($creditLimit, 2, '.', '') : 'NULL';
    $daysSql = $creditDays && $creditDays > 0 ? (string) $creditDays : 'NULL';
    if (!$id) {
        Json::error('Customer not found.', 404);
    }
    if ($name === '') {
        Json::error('Customer name is required.', 422);
    }
    $found = queryRows('SELECT id FROM customers WHERE id = ' . $id . ' LIMIT 1');
    if (!$found) {
        Json::error('Customer not found.', 404);
    }
    try {
        try {
            queryRows('UPDATE customers SET
                name = ' . sqlStr($name) . ',
                business_name = ' . sqlNull($business) . ',
                type = ' . sqlStr($type) . ',
                phone = ' . sqlNull($phone) . ',
                gstin = ' . sqlNull($gstin) . ',
                dl_no = ' . sqlNull($dl) . ',
                address = ' . sqlNull($address) . ',
                credit_limit = ' . $limitSql . ',
                credit_days = ' . $daysSql . '
                WHERE id = ' . $id);
        } catch (Throwable $e) {
            try {
                queryRows('UPDATE customers SET
                    name = ' . sqlStr($name) . ',
                    business_name = ' . sqlNull($business) . ',
                    type = ' . sqlStr($type) . ',
                    phone = ' . sqlNull($phone) . ',
                    gstin = ' . sqlNull($gstin) . ',
                    dl_no = ' . sqlNull($dl) . ',
                    address = ' . sqlNull($address) . '
                    WHERE id = ' . $id);
            } catch (Throwable $e2) {
                queryRows('UPDATE customers SET
                    name = ' . sqlStr($name) . ',
                    type = ' . sqlStr($type) . ',
                    phone = ' . sqlNull($phone) . ',
                    gstin = ' . sqlNull($gstin) . ',
                    dl_no = ' . sqlNull($dl) . ',
                    address = ' . sqlNull($address) . '
                    WHERE id = ' . $id);
            }
        }
    } catch (Throwable $e) {
        Json::error('Could not update the customer. Run database/migrations/2026_09_28_customer_profile.sql if the type could not be saved.', 500);
    }
    if (class_exists('Audit')) {
        Audit::log('CUSTOMER_UPDATE', $name);
    }
    Json::ok([
        'id' => $id,
        'name' => $name,
        'business_name' => $business,
        'address' => $address,
        'phone' => $phone,
        'gstin' => $gstin,
        'dl_no' => $dl,
        'type' => $type,
        'credit_limit' => $creditLimit ?: null,
        'credit_days' => $creditDays ?: null,
    ]);
}

Json::error('Method not allowed.', 405);
