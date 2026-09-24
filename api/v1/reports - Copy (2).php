<?php
/**
 * api/v1/reports.php
 * Each report section runs on its own: if one query fails (missing column/table) the others still
 * load, and the failure is listed in "errors" (details on localhost only) and in the PHP error log.
 */
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';

// A PHP warning printed into the response would break the JSON and blank every tab.
ini_set('display_errors', '0');

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$from  = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to    = $_GET['to'] ?? date('Y-m-d');
$today = date('Y-m-d');
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

$pdo = Tenant::db();
$errors = [];

/** Run one report section; on failure record it and return the fallback. */
$section = function (string $name, callable $fn, $fallback) use (&$errors, $isLocal) {
    try {
        return $fn();
    } catch (Throwable $e) {
        error_log("reports.php [{$name}]: " . $e->getMessage());
        $errors[$name] = $isLocal ? $e->getMessage() : 'failed';
        return $fallback;
    }
};

// --- Profit expressions (same maths as the dashboard) ---------------------------
// revenue = sale amount without GST (retail amounts are GST-inclusive, wholesale are not),
//           x bill factor so the BILL-LEVEL DISCOUNT is applied too:
//           factor = (sales.subtotal - sales.discount) / SUM(item amounts of that sale)
// cost    = qty (+ free qty) x batch purchase rate (loose units divided by pack size)
// Needs: sales joined as "s", sale_items as "si", batches as "b", and st = per-sale item total.
$ic       = $section('columns', fn() => array_column($pdo->query('SHOW COLUMNS FROM sale_items')->fetchAll(), 'Field'), []);
$freeExpr = in_array('free_qty', $ic, true) ? '+ COALESCE(si.free_qty, 0)' : '';
$perUnit  = in_array('unit_sold', $ic, true) && in_array('pack_qty_at_sale', $ic, true)
    ? "CASE WHEN si.unit_sold = 'loose' THEN GREATEST(si.pack_qty_at_sale, 1) ELSE 1 END" : '1';
$factor   = 'IF(st.tot > 0, (s.subtotal - s.discount) / st.tot, 1)';
$revExpr  = "(CASE WHEN LOWER(s.channel) = 'wholesale' THEN si.amount ELSE si.amount / (1 + si.gst_pct / 100) END) * {$factor}";
$costExpr = "(si.qty {$freeExpr}) * b.purchase_rate / ({$perUnit})";
$stJoin   = 'LEFT JOIN (SELECT sale_id, SUM(amount) AS tot FROM sale_items GROUP BY sale_id) st ON st.sale_id = s.id';

// --- Sales ------------------------------------------------------------------
$sales = $section('sales', function () use ($pdo, $from, $to, $revExpr, $costExpr, $stJoin) {
    $stmt = $pdo->prepare(
        "SELECT s.*, c.name AS customer_name,
                (SELECT COALESCE(SUM({$revExpr} - {$costExpr}), 0)
                 FROM sale_items si JOIN batches b ON b.id = si.batch_id
                 WHERE si.sale_id = s.id) AS profit
         FROM sales s JOIN customers c ON c.id = s.customer_id {$stJoin}
         WHERE s.sale_date BETWEEN :from AND :to
         ORDER BY s.sale_date DESC, s.id DESC"
    );
    $stmt->execute(['from' => $from, 'to' => $to]);
    return array_map(fn($r) => [
        'date' => $r['sale_date'], 'no' => $r['invoice_no'], 'customer' => $r['customer_name'],
        'channel' => $r['channel'], 'amount' => (float) $r['grand_total'], 'tax' => (float) $r['gst_amount'],
        'discount' => (float) $r['discount'], 'profit' => round((float) $r['profit']), 'payment' => $r['payment_mode'],
    ], $stmt->fetchAll());
}, []);

// --- Purchase -----------------------------------------------------------------
$purchases = $section('purchases', function () use ($pdo, $from, $to) {
    $stmt = $pdo->prepare(
        "SELECT p.*, s.name AS supplier_name FROM purchases p JOIN suppliers s ON s.id = p.supplier_id
         WHERE p.invoice_date BETWEEN :from AND :to ORDER BY p.invoice_date DESC, p.id DESC"
    );
    $stmt->execute(['from' => $from, 'to' => $to]);
    return array_map(fn($r) => [
        'date' => $r['invoice_date'], 'supplier' => $r['supplier_name'], 'no' => $r['invoice_no'],
        'amount' => (float) $r['grand_total'], 'tax' => (float) (($r['cgst'] ?? 0) + ($r['sgst'] ?? 0) + ($r['igst'] ?? 0)),
        'payment' => $r['payment_mode'] ?? '', 'status' => $r['balance_due'] > 0 ? 'Due' : 'Paid',
    ], $stmt->fetchAll());
}, []);

// --- Stock (current snapshot, not date-filtered) -----------------------------
// Loose Sale: also pull loose_qty + sub_unit (Reports page shows this as its own
// "Loose Stock" column) and the manufacturer name (shown under the medicine name).
$stock = $section('stock', function () use ($pdo) {
    $stmt = $pdo->query(
        "SELECT b.*, m.name AS med_name, m.min_stock, m.mrp AS med_mrp, m.unit, m.sub_unit, mf.name AS manufacturer
         FROM batches b
         JOIN medicines m ON m.id = b.medicine_id
         LEFT JOIN manufacturers mf ON mf.id = m.manufacturer_id
         ORDER BY m.name"
    );
    return array_map(function ($r) {
        $qty = (int) $r['quantity'];
        $status = $qty === 0 ? 'Out of Stock' : ($qty <= (int) ($r['min_stock'] ?? 0) ? 'Low Stock' : 'In Stock');
        return [
            'name' => $r['med_name'], 'manufacturer' => $r['manufacturer'] ?? '', 'batch' => $r['batch_no'], 'qty' => $qty,
            'unit' => $r['unit'] ?? '', 'looseQty' => (int) ($r['loose_qty'] ?? 0), 'subUnit' => $r['sub_unit'] ?? '',
            'purchaseValue' => round($qty * (float) $r['purchase_rate']),
            'mrpValue' => round($qty * (float) ($r['mrp'] ?? $r['med_mrp'] ?? 0)),
            'status' => $status,
        ];
    }, $stmt->fetchAll());
}, []);

// --- Expiry (<=90 days including already expired) ----------------------------
$expiry = $section('expiry', function () use ($pdo, $today) {
    $stmt = $pdo->prepare(
        "SELECT b.*, m.name AS med_name FROM batches b JOIN medicines m ON m.id = b.medicine_id
         WHERE b.quantity > 0 AND DATEDIFF(b.expiry_date, :today) <= 90
         ORDER BY b.expiry_date ASC"
    );
    $stmt->execute(['today' => $today]);
    return array_map(fn($r) => [
        'name' => $r['med_name'], 'batch' => $r['batch_no'], 'expiry' => $r['expiry_date'],
        'days' => (int) floor((strtotime($r['expiry_date']) - strtotime($today)) / 86400),
        'qty' => (int) $r['quantity'], 'value' => round((int) $r['quantity'] * (float) $r['purchase_rate']),
    ], $stmt->fetchAll());
}, []);

// --- Profit: KPIs + category breakdown (within range) + 7-day trend ------------
$profitKpis = $section('profitKpis', function () use ($pdo, $from, $to, $revExpr, $costExpr, $stJoin) {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM({$revExpr}), 0) AS revenue, COALESCE(SUM({$costExpr}), 0) AS cost
         FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN batches b ON b.id = si.batch_id {$stJoin}
         WHERE s.sale_date BETWEEN :from AND :to"
    );
    $stmt->execute(['from' => $from, 'to' => $to]);
    $p = $stmt->fetch();
    return ['sales' => (float) $p['revenue'], 'cost' => (float) $p['cost'], 'gp' => (float) $p['revenue'] - (float) $p['cost']];
}, ['sales' => 0, 'cost' => 0, 'gp' => 0]);

$profitByCategory = $section('profitByCategory', function () use ($pdo, $from, $to, $revExpr, $costExpr, $stJoin) {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(c.name, 'Uncategorized') AS category,
                COALESCE(SUM({$revExpr}), 0) AS revenue, COALESCE(SUM({$costExpr}), 0) AS cost
         FROM sale_items si
         JOIN sales s ON s.id = si.sale_id
         JOIN medicines m ON m.id = si.medicine_id
         JOIN batches b ON b.id = si.batch_id
         LEFT JOIN categories c ON c.id = m.category_id {$stJoin}
         WHERE s.sale_date BETWEEN :from AND :to
         GROUP BY c.id, c.name ORDER BY revenue DESC"
    );
    $stmt->execute(['from' => $from, 'to' => $to]);
    return array_map(function ($r) {
        $rev = (float) $r['revenue']; $cost = (float) $r['cost']; $gp = $rev - $cost;
        return ['category' => $r['category'], 'sales' => $rev, 'cost' => $cost, 'gp' => $gp, 'margin' => $rev > 0 ? round($gp / $rev * 100, 1) : 0];
    }, $stmt->fetchAll());
}, []);

$profitTrend = $section('profitTrend', function () use ($pdo, $today, $revExpr, $costExpr, $stJoin) {
    $stmt = $pdo->prepare(
        "SELECT s.sale_date AS d, COALESCE(SUM({$revExpr} - {$costExpr}), 0) AS gp
         FROM sales s
         JOIN sale_items si ON si.sale_id = s.id
         JOIN batches b ON b.id = si.batch_id {$stJoin}
         WHERE s.sale_date >= DATE_SUB(:today, INTERVAL 6 DAY)
         GROUP BY s.sale_date"
    );
    $stmt->execute(['today' => $today]);
    $map = [];
    foreach ($stmt->fetchAll() as $r) {
        $map[substr((string) $r['d'], 0, 10)] = round((float) $r['gp']);
    }
    $out = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $out[] = ['label' => date('D', strtotime($d)), 'value' => $map[$d] ?? 0];
    }
    return $out;
}, []);

// --- GST: KPIs (within range) + wholesale invoice table + In/Out chart data ---
$gstOut = $section('gstOut', function () use ($pdo, $from, $to) {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(subtotal - discount), 0) AS taxable, COALESCE(SUM(cgst), 0) AS cgst,
                COALESCE(SUM(sgst), 0) AS sgst, COALESCE(SUM(igst), 0) AS igst
         FROM sales WHERE sale_date BETWEEN :from AND :to"
    );
    $stmt->execute(['from' => $from, 'to' => $to]);
    return $stmt->fetch();
}, ['taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0]);

$gstIn = $section('gstIn', function () use ($pdo, $from, $to) {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(cgst), 0) AS cgst, COALESCE(SUM(sgst), 0) AS sgst, COALESCE(SUM(igst), 0) AS igst
         FROM purchases WHERE invoice_date BETWEEN :from AND :to"
    );
    $stmt->execute(['from' => $from, 'to' => $to]);
    return $stmt->fetch();
}, ['cgst' => 0, 'sgst' => 0, 'igst' => 0]);

$gstInvoices = $section('gstInvoices', function () use ($pdo, $from, $to) {
    $stmt = $pdo->prepare(
        "SELECT s.invoice_no AS no, c.name AS customer, s.gstin, s.subtotal, s.discount, s.cgst, s.sgst, s.igst, s.grand_total
         FROM sales s JOIN customers c ON c.id = s.customer_id
         WHERE s.channel = 'wholesale' AND s.sale_date BETWEEN :from AND :to
         ORDER BY s.sale_date DESC"
    );
    $stmt->execute(['from' => $from, 'to' => $to]);
    return array_map(fn($r) => [
        'no' => $r['no'], 'party' => $r['customer'], 'gstin' => $r['gstin'] ?? '—',
        'taxable' => (float) ($r['subtotal'] - $r['discount']), 'cgst' => (float) $r['cgst'],
        'sgst' => (float) $r['sgst'], 'igst' => (float) $r['igst'], 'total' => (float) $r['grand_total'],
    ], $stmt->fetchAll());
}, []);

// --- Dues (current outstanding, not date-filtered) ---------------------------
$dues = $section('dues', function () use ($pdo, $today) {
    $rows = function (string $table, string $party, string $partyTable, string $dateCol, string $totalKey) use ($pdo) {
        $stmt = $pdo->query(
            "SELECT p.id, p.name, SUM(t.grand_total) AS total, SUM(t.amount_paid) AS paid,
                    SUM(t.balance_due) AS due, MAX(t.{$dateCol}) AS last_date
             FROM {$table} t JOIN {$partyTable} p ON p.id = t.{$party}
             GROUP BY p.id, p.name HAVING SUM(t.balance_due) > 0
             ORDER BY due DESC"
        );
        return array_map(fn($r) => [
            'id' => (int) $r['id'], 'name' => $r['name'], $totalKey => round((float) $r['total'], 2),
            'paid' => round((float) $r['paid'], 2), 'due' => round((float) $r['due'], 2),
            'lastPurchase' => substr((string) $r['last_date'], 0, 10),
        ], $stmt->fetchAll());
    };
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE WHEN DATEDIFF(:t1, sale_date) <= 7 THEN balance_due ELSE 0 END), 0) AS a1,
                COALESCE(SUM(CASE WHEN DATEDIFF(:t2, sale_date) BETWEEN 8 AND 30 THEN balance_due ELSE 0 END), 0) AS a2,
                COALESCE(SUM(CASE WHEN DATEDIFF(:t3, sale_date) BETWEEN 31 AND 60 THEN balance_due ELSE 0 END), 0) AS a3,
                COALESCE(SUM(CASE WHEN DATEDIFF(:t4, sale_date) > 60 THEN balance_due ELSE 0 END), 0) AS a4
         FROM sales WHERE balance_due > 0"
    );
    $stmt->execute(['t1' => $today, 't2' => $today, 't3' => $today, 't4' => $today]);
    $a = $stmt->fetch();
    return [
        'customers' => $rows('sales', 'customer_id', 'customers', 'sale_date', 'totalSales'),
        'suppliers' => $rows('purchases', 'supplier_id', 'suppliers', 'invoice_date', 'totalPurchases'),
        'aging'     => ['d0_7' => (float) $a['a1'], 'd8_30' => (float) $a['a2'], 'd31_60' => (float) $a['a3'], 'd60p' => (float) $a['a4']],
    ];
}, ['customers' => [], 'suppliers' => [], 'aging' => ['d0_7' => 0, 'd8_30' => 0, 'd31_60' => 0, 'd60p' => 0]]);

Json::ok([
    'range'      => ['from' => $from, 'to' => $to],
    'sales'      => $sales,
    'purchases'  => $purchases,
    'stock'      => $stock,
    'expiry'     => $expiry,
    'profitKpis' => $profitKpis,
    'profitByCategory' => $profitByCategory,
    'profitTrend' => $profitTrend,
    'gstKpis'    => [
        'taxable' => (float) $gstOut['taxable'], 'cgst' => (float) $gstOut['cgst'],
        'sgst' => (float) $gstOut['sgst'], 'igst' => (float) $gstOut['igst'],
    ],
    'gstIn'      => ['cgst' => (float) $gstIn['cgst'], 'sgst' => (float) $gstIn['sgst'], 'igst' => (float) $gstIn['igst']],
    'gstInvoices' => $gstInvoices,
    'dues'       => $dues,
    'errors'     => $errors,
]);
