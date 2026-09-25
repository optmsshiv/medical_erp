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
    $sql = 'SELECT medicine_id, medicine_name, brand_name, category_name, manufacturer_name, unit,
                   qty, available, reserved, damaged, purchase_rate, mrp, batch_count,
                   stock_value, mrp_value, min_stock, next_expiry
            FROM v_stock_overview';
    try {
        return Manufacturer::query($sql);
    } catch (Throwable $e) {
        $fallback = 'SELECT m.id AS medicine_id, m.name AS medicine_name,
                m.brand_name AS brand_name,
                COALESCE(c.name, \'Unassigned\') AS category_name,
                COALESCE(mf.name, \'Unassigned\') AS manufacturer_name,
                m.unit AS unit,
                COALESCE(SUM(b.quantity), 0) AS qty,
                GREATEST(COALESCE(SUM(b.quantity), 0) - COALESCE(SUM(b.reserved), 0), 0) AS available,
                COALESCE(SUM(b.reserved), 0) AS reserved,
                COALESCE((
                  SELECT SUM(ABS(sa.qty_change))
                  FROM stock_adjustments sa
                  WHERE sa.medicine_id = m.id AND sa.reason = \'Damage\'
                ), 0) AS damaged,
                COALESCE(m.purchase_rate, 0) AS purchase_rate,
                COALESCE(m.mrp, 0) AS mrp,
                COUNT(b.id) AS batch_count,
                COALESCE(SUM(b.quantity * b.purchase_rate), 0) AS stock_value,
                COALESCE(SUM(b.quantity * b.mrp), 0) AS mrp_value,
                COALESCE(m.min_stock, 0) AS min_stock,
                MIN(CASE WHEN b.quantity > 0 THEN b.expiry_date END) AS next_expiry
             FROM medicines m
             LEFT JOIN batches b ON b.medicine_id = m.id
             LEFT JOIN categories c ON c.id = m.category_id
             LEFT JOIN manufacturers mf ON mf.id = m.manufacturer_id
             GROUP BY m.id, m.name, m.brand_name, c.name, mf.name, m.unit, m.min_stock, m.purchase_rate, m.mrp';
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

$ledger = array_map(fn ($row) => [
    'medicine_id' => (int) $row['medicine_id'],
    'medicine_name' => $row['medicine_name'],
    'brand_name' => $row['brand_name'] ?? '',
    'category_name' => $row['category_name'],
    'unit' => $row['unit'],
    'qty' => $row['qty'],
    'available' => (float) ($row['available'] ?? $row['qty']),
    'reserved' => (float) ($row['reserved'] ?? 0),
    'damaged' => (float) ($row['damaged'] ?? 0),
    'purchase_rate' => (float) ($row['purchase_rate'] ?? 0),
    'mrp' => (float) ($row['mrp'] ?? 0),
    'next_expiry' => $row['next_expiry'] ?? null,
    'stock_value' => $row['stock_value'],
    'position' => $row['position'],
], $rows);

Json::ok(['data' => [
    'summary' => $summary,
    'by_category' => $group('category_name'),
    'by_manufacturer' => array_slice($group('manufacturer_name'), 0, 6),
    'ledger' => $ledger,
]]);
