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
 * Stock position, not the stock report.
 * Reads v_stock_overview from database/migrations/2026_09_25_stock_overview.sql.
 * Falls back to the same join if the view has not been created yet.
 */
function overviewRows(): array
{
    $sql = 'SELECT medicine_id, medicine_name, category_name, manufacturer_name, unit,
                   qty, batch_count, stock_value, mrp_value, min_stock, next_expiry
            FROM v_stock_overview';
    try {
        return Manufacturer::query($sql);
    } catch (Throwable $e) {
        $fallback = 'SELECT m.id AS medicine_id, m.name AS medicine_name,
                COALESCE(c.name, \'Unassigned\') AS category_name,
                COALESCE(mf.name, \'Unassigned\') AS manufacturer_name,
                m.unit AS unit,
                COALESCE(SUM(b.qty), 0) AS qty,
                COUNT(b.id) AS batch_count,
                COALESCE(SUM(b.qty * b.purchase_rate), 0) AS stock_value,
                COALESCE(SUM(b.qty * b.mrp), 0) AS mrp_value,
                COALESCE(m.min_stock, 0) AS min_stock,
                MIN(CASE WHEN b.qty > 0 THEN b.expiry END) AS next_expiry
             FROM medicines m
             LEFT JOIN batches b ON b.medicine_id = m.id
             LEFT JOIN categories c ON c.id = m.category_id
             LEFT JOIN manufacturers mf ON mf.id = m.manufacturer_id
             GROUP BY m.id, m.name, c.name, mf.name, m.unit, m.min_stock';
        return Manufacturer::query($fallback);
    }
}

function position(array $row): string
{
    $qty = (float) $row['qty'];
    $min = (float) $row['min_stock'];
    $expiry = $row['next_expiry'] ?? null;
    $days = null;
    if ($expiry) {
        $days = (int) floor((strtotime(substr((string) $expiry, 0, 10)) - strtotime(date('Y-m-d'))) / 86400);
    }
    if ($qty <= 0) {
        return 'Out of stock';
    }
    if ($days !== null && $days < 0) {
        return 'Expired';
    }
    if ($days !== null && $days <= 90) {
        return 'Near expiry';
    }
    if ($qty <= $min) {
        return 'Low stock';
    }
    return 'In stock';
}

$rows = array_map(function (array $row) {
    $row['qty'] = (float) $row['qty'];
    $row['stock_value'] = (float) $row['stock_value'];
    $row['mrp_value'] = (float) $row['mrp_value'];
    $row['batch_count'] = (int) $row['batch_count'];
    $row['position'] = position($row);
    return $row;
}, overviewRows());

$summary = [
    'medicines' => count($rows),
    'batches' => array_sum(array_column($rows, 'batch_count')),
    'qty' => array_sum(array_column($rows, 'qty')),
    'stock_value' => array_sum(array_column($rows, 'stock_value')),
    'mrp_value' => array_sum(array_column($rows, 'mrp_value')),
    'in_stock' => 0,
    'low' => 0,
    'out' => 0,
    'near_expiry' => 0,
    'expired' => 0,
];
$keys = [
    'In stock' => 'in_stock',
    'Low stock' => 'low',
    'Out of stock' => 'out',
    'Near expiry' => 'near_expiry',
    'Expired' => 'expired',
];
foreach ($rows as $row) {
    $key = $keys[$row['position']] ?? null;
    if ($key) {
        $summary[$key]++;
    }
}

$group = function (string $field) use ($rows): array {
    $map = [];
    foreach ($rows as $row) {
        $name = $row[$field] ?: 'Unassigned';
        if (!isset($map[$name])) {
            $map[$name] = ['name' => $name, 'medicines' => 0, 'qty' => 0, 'stock_value' => 0];
        }
        $map[$name]['medicines']++;
        $map[$name]['qty'] += $row['qty'];
        $map[$name]['stock_value'] += $row['stock_value'];
    }
    $list = array_values($map);
    usort($list, fn ($a, $b) => $b['stock_value'] <=> $a['stock_value']);
    return $list;
};

$attention = array_values(array_filter($rows, fn ($row) => $row['position'] !== 'In stock'));
$attention = array_slice($attention, 0, 8);
$attention = array_map(fn ($row) => [
    'medicine_id' => (int) $row['medicine_id'],
    'medicine_name' => $row['medicine_name'],
    'category_name' => $row['category_name'],
    'unit' => $row['unit'],
    'qty' => $row['qty'],
    'stock_value' => $row['stock_value'],
    'position' => $row['position'],
], $attention);

Json::ok(['data' => [
    'summary' => $summary,
    'by_category' => $group('category_name'),
    'by_manufacturer' => array_slice($group('manufacturer_name'), 0, 6),
    'attention' => $attention,
]]);
