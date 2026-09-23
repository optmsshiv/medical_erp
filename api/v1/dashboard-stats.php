<?php
/**
 * api/v1/dashboard-stats.php
 *
 * Live numbers for dashboard.php (called as MF.Api.get('dashboard-stats.php')).
 * Response: { ok: true, data: { todaySales, ..., salesSplit, salesTrend, topSellers } }
 *
 * Uses only columns already used in bootstrap.php:
 *   sales     (customer_id, sale_date, grand_total, amount_paid, balance_due)
 *   purchases (supplier_id, invoice_date, grand_total, amount_paid, balance_due)
 *   batches   (quantity, purchase_rate)
 *   customers (id, type)   -> type "Wholesale" = wholesale, anything else = retail
 *
 * Also returns lowStock, nearExpiry, recentSales, recentPurchases, topSellers and dues.
 * Optional columns/tables (invoice numbers, payment mode, sale/purchase items) are
 * looked up defensively; anything not found falls back to a safe default.
 * Gross profit stays 0 until cost per sale line is wired in.
 */
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

// DS_APP_TZ = the shop's time zone ("today" is worked out in it).
// DS_DB_TZ  = the time zone sale_date values are stored in. If they differ, "today"
// is converted for comparisons and stored values are shifted back for grouping.
// Your DB reports system_tz Asia/Calcutta and sale_date is a plain DATE, so both
// are India time and no shifting happens.
const DS_APP_TZ = 'Asia/Kolkata';
const DS_DB_TZ  = 'Asia/Kolkata';
date_default_timezone_set(DS_APP_TZ);

function ds_scalar(PDO $pdo, string $sql, array $params = []): float
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float) ($stmt->fetchColumn() ?: 0);
}

function ds_delta(float $now, float $prev): float
{
    if ($prev <= 0) {
        return $now > 0 ? 100.0 : 0.0;
    }
    return round(($now - $prev) / $prev * 100, 1);
}

/** First non-empty value among the given keys of a row (schema-tolerant lookups). */
function ds_first(array $row, array $keys, $default = '')
{
    foreach ($keys as $k) {
        if (isset($row[$k]) && $row[$k] !== '') {
            return $row[$k];
        }
    }
    return $default;
}

/** Table name from the candidate list that exists in this tenant DB, or null. */
function ds_find_table(PDO $pdo, array $candidates): ?string
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    foreach ($candidates as $t) {
        $stmt->execute([$t]);
        if ($stmt->fetchColumn() !== false) {
            return $t;
        }
    }
    return null;
}

/** Column names of a table (table name always comes from our own candidate list). */
function ds_columns(PDO $pdo, string $table): array
{
    return array_column($pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(), 'Field');
}

function ds_pick(array $columns, array $candidates): ?string
{
    foreach ($candidates as $c) {
        if (in_array($c, $columns, true)) {
            return $c;
        }
    }
    return null;
}

/** Paid / Partial / Due, the same labels MF.statusBadge understands. */
function ds_status(array $row): string
{
    if ((float) $row['balance_due'] <= 0) {
        return 'Paid';
    }
    return (float) $row['amount_paid'] > 0 ? 'Partial' : 'Due';
}

$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

try {
    $pdo = Tenant::db();

    $appTz = new DateTimeZone(DS_APP_TZ);
    $dbTz  = new DateTimeZone(DS_DB_TZ);

    $today     = new DateTimeImmutable('today', $appTz);
    $tomorrow  = $today->modify('+1 day');
    $yesterday = $today->modify('-1 day');
    $d = fn(DateTimeImmutable $x) => $x->format('Y-m-d');

    // Local (India) midnight of 'Y-m-d' -> the same instant as a DB-timezone datetime string.
    $dbStart = fn(string $localDate) => (new DateTimeImmutable($localDate . ' 00:00:00', $appTz))
        ->setTimezone($dbTz)->format('Y-m-d H:i:s');
    // Minutes to add to a stored sale_date to get India time (330 for UTC -> IST).
    $shiftMin = intdiv($appTz->getOffset($today) - $dbTz->getOffset($today), 60);
    $localSaleDt = "DATE_ADD(s.sale_date, INTERVAL {$shiftMin} MINUTE)";

    // Retail vs wholesale comes from sales.channel; fall back to customers.type if there is no channel column.
    $saleCols      = ds_columns($pdo, 'sales');
    $wholesaleCond = in_array('channel', $saleCols, true) ? "LOWER(s.channel) = 'wholesale'" : "c.type = 'Wholesale'";

    // Hour-of-day for the Today chart: sale_date is a DATE (no time), so use created_at when available.
    $saleDateType = ($pdo->query("SHOW COLUMNS FROM sales LIKE 'sale_date'")->fetch() ?: [])['Type'] ?? '';
    if (strtolower($saleDateType) === 'date') {
        $hourExpr = in_array('created_at', $saleCols, true)
            ? "HOUR(DATE_ADD(s.created_at, INTERVAL {$shiftMin} MINUTE))"
            : null;
    } else {
        $hourExpr = "HOUR({$localSaleDt})";
    }

    // --- Today vs yesterday: sales & purchases -----------------------------
    $salesBetween = fn($from, $to) => ds_scalar(
        $pdo,
        'SELECT COALESCE(SUM(grand_total), 0) FROM sales WHERE sale_date >= :f AND sale_date < :t',
        ['f' => $dbStart($from), 't' => $dbStart($to)]
    );
    $purchasesBetween = fn($from, $to) => ds_scalar(
        $pdo,
        'SELECT COALESCE(SUM(grand_total), 0) FROM purchases WHERE invoice_date >= :f AND invoice_date < :t',
        ['f' => $from, 't' => $to]
    );

    $todaySales     = $salesBetween($d($today), $d($tomorrow));
    $yesterdaySales = $salesBetween($d($yesterday), $d($today));
    $todayPurchase     = $purchasesBetween($d($today), $d($tomorrow));
    $yesterdayPurchase = $purchasesBetween($d($yesterday), $d($today));

    // --- Retail / wholesale split (today) ----------------------------------
    $splitStmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN {$wholesaleCond} THEN 0 ELSE s.grand_total END), 0) AS retail,
            COALESCE(SUM(CASE WHEN {$wholesaleCond} THEN s.grand_total ELSE 0 END), 0) AS wholesale
         FROM sales s
         LEFT JOIN customers c ON c.id = s.customer_id
         WHERE s.sale_date >= :f AND s.sale_date < :t"
    );
    $splitStmt->execute(['f' => $dbStart($d($today)), 't' => $dbStart($d($tomorrow))]);
    $split = $splitStmt->fetch();

    // --- Stock value / units -----------------------------------------------
    $stockRow = $pdo->query(
        'SELECT COALESCE(SUM(quantity * purchase_rate), 0) AS value, COALESCE(SUM(quantity), 0) AS units FROM batches'
    )->fetch();

    // --- Dues ---------------------------------------------------------------
    $custDue = $pdo->query(
        'SELECT COALESCE(SUM(balance_due), 0) AS due, COUNT(DISTINCT customer_id) AS parties
         FROM sales WHERE balance_due > 0'
    )->fetch();
    $supDue = $pdo->query(
        'SELECT COALESCE(SUM(balance_due), 0) AS due, COUNT(DISTINCT supplier_id) AS parties
         FROM purchases WHERE balance_due > 0'
    )->fetch();

    // --- Sales trend (all four periods the chart switcher needs) -----------
    // Hourly buckets for "today" (needs a time source; otherwise a single "Today" bar is used below)
    $todayTrend = null;
    if ($hourExpr !== null) {
        $hourStmt = $pdo->prepare(
            "SELECT {$hourExpr} AS h,
                SUM(CASE WHEN {$wholesaleCond} THEN 0 ELSE s.grand_total END) AS retail,
                SUM(CASE WHEN {$wholesaleCond} THEN s.grand_total ELSE 0 END) AS wholesale
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE s.sale_date >= :f AND s.sale_date < :t
             GROUP BY {$hourExpr}"
        );
        $hourStmt->execute(['f' => $dbStart($d($today)), 't' => $dbStart($d($tomorrow))]);
        $hourRows = [];
        foreach ($hourStmt->fetchAll() as $r) {
            $hourRows[(int) $r['h']] = $r;
        }
        $hours  = array_keys($hourRows);
        $startH = min([9, ...$hours]);
        $endH   = max([9, (int) date('G'), ...$hours]);
        $todayTrend = ['labels' => [], 'retail' => [], 'wholesale' => []];
        for ($h = $startH; $h <= $endH; $h++) {
            $todayTrend['labels'][]    = (new DateTimeImmutable('today'))->setTime($h, 0)->format('g A');
            $todayTrend['retail'][]    = round((float) ($hourRows[$h]['retail'] ?? 0), 2);
            $todayTrend['wholesale'][] = round((float) ($hourRows[$h]['wholesale'] ?? 0), 2);
        }
    }

    // Daily rows covering the widest range needed (last 30 days or this month)
    $monthStart = $today->modify('first day of this month');
    $rangeFrom  = min($today->modify('-29 days'), $monthStart);
    $dayStmt = $pdo->prepare(
        "SELECT DATE({$localSaleDt}) AS d,
            SUM(CASE WHEN {$wholesaleCond} THEN 0 ELSE s.grand_total END) AS retail,
            SUM(CASE WHEN {$wholesaleCond} THEN s.grand_total ELSE 0 END) AS wholesale
         FROM sales s
         LEFT JOIN customers c ON c.id = s.customer_id
         WHERE s.sale_date >= :f AND s.sale_date < :t
         GROUP BY DATE({$localSaleDt})"
    );
    $dayStmt->execute(['f' => $dbStart($d($rangeFrom)), 't' => $dbStart($d($tomorrow))]);
    $dayRows = [];
    foreach ($dayStmt->fetchAll() as $r) {
        $dayRows[$r['d']] = ['retail' => (float) $r['retail'], 'wholesale' => (float) $r['wholesale']];
    }
    $dayVal = fn(string $date, string $k) => $dayRows[$date][$k] ?? 0.0;

    // No time source (DATE-only sale_date and no created_at): one bar for today.
    if ($todayTrend === null) {
        $todayTrend = [
            'labels'    => ['Today'],
            'retail'    => [round($dayVal($d($today), 'retail'), 2)],
            'wholesale' => [round($dayVal($d($today), 'wholesale'), 2)],
        ];
    }

    // 7 days: one bar per day
    $trend7 = ['labels' => [], 'retail' => [], 'wholesale' => []];
    for ($i = 6; $i >= 0; $i--) {
        $day = $today->modify("-{$i} days");
        $trend7['labels'][]    = $day->format('D');
        $trend7['retail'][]    = round($dayVal($d($day), 'retail'), 2);
        $trend7['wholesale'][] = round($dayVal($d($day), 'wholesale'), 2);
    }

    // 30 days: four weekly buckets, W4 = most recent 7 days
    $trend30 = ['labels' => ['W1', 'W2', 'W3', 'W4'], 'retail' => [0, 0, 0, 0], 'wholesale' => [0, 0, 0, 0]];
    for ($i = 0; $i < 30; $i++) {
        $day = $today->modify("-{$i} days");
        $idx = 3 - min(3, intdiv($i, 7));
        $trend30['retail'][$idx]    += $dayVal($d($day), 'retail');
        $trend30['wholesale'][$idx] += $dayVal($d($day), 'wholesale');
    }
    $trend30['retail']    = array_map(fn($v) => round($v, 2), $trend30['retail']);
    $trend30['wholesale'] = array_map(fn($v) => round($v, 2), $trend30['wholesale']);

    // This month: 3-day buckets up to today
    $trendMonth = ['labels' => [], 'retail' => [], 'wholesale' => []];
    $todayNo = (int) $today->format('j');
    $mon = $today->format('M');
    for ($start = 1; $start <= $todayNo; $start += 3) {
        $end = min($start + 2, $todayNo);
        $r = 0.0;
        $w = 0.0;
        for ($n = $start; $n <= $end; $n++) {
            $date = $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('n'), $n)->format('Y-m-d');
            $r += $dayVal($date, 'retail');
            $w += $dayVal($date, 'wholesale');
        }
        $trendMonth['labels'][]    = $start === $end ? "{$start} {$mon}" : "{$start}-{$end} {$mon}";
        $trendMonth['retail'][]    = round($r, 2);
        $trendMonth['wholesale'][] = round($w, 2);
    }

    // --- Low stock medicines ------------------------------------------------
    $lowStock = array_map(fn($r) => [
        'id'           => (int) $r['id'],
        'name'         => $r['name'],
        'manufacturer' => $r['manufacturer'] ?? '',
        'batchNo'      => $r['batch_no'] ?? '',
        'stock'        => (int) $r['stock'],
        'minStock'     => (int) $r['min_stock'],
    ], $pdo->query(
        'SELECT m.id, m.name, mf.name AS manufacturer, m.min_stock,
                COALESCE(SUM(b.quantity), 0) AS stock,
                (SELECT b2.batch_no FROM batches b2 WHERE b2.medicine_id = m.id
                 ORDER BY b2.expiry_date ASC LIMIT 1) AS batch_no
         FROM medicines m
         LEFT JOIN manufacturers mf ON mf.id = m.manufacturer_id
         LEFT JOIN batches b ON b.medicine_id = m.id
         GROUP BY m.id, m.name, m.min_stock, mf.name
         HAVING m.min_stock > 0 AND COALESCE(SUM(b.quantity), 0) <= m.min_stock
         ORDER BY COALESCE(SUM(b.quantity), 0) / m.min_stock ASC
         LIMIT 50'
    )->fetchAll());

    // --- Near expiry batches (expired + next 90 days, stock on hand) --------
    $nearStmt = $pdo->prepare(
        'SELECT b.id, b.medicine_id, m.name, mf.name AS manufacturer, b.batch_no,
                b.expiry_date, b.quantity, b.purchase_rate
         FROM batches b
         JOIN medicines m ON m.id = b.medicine_id
         LEFT JOIN manufacturers mf ON mf.id = m.manufacturer_id
         WHERE b.quantity > 0 AND b.expiry_date <= :lim
         ORDER BY b.expiry_date ASC
         LIMIT 50'
    );
    $nearStmt->execute(['lim' => $d($today->modify('+90 days'))]);
    $nearExpiry = array_map(function ($r) use ($today) {
        $days = (int) $today->diff(new DateTimeImmutable($r['expiry_date']))->format('%r%a');
        return [
            'id'           => (int) $r['id'],
            'medId'        => (int) $r['medicine_id'],
            'name'         => $r['name'],
            'manufacturer' => $r['manufacturer'] ?? '',
            'batchNo'      => $r['batch_no'],
            'expiry'       => $r['expiry_date'],
            'qty'          => (int) $r['quantity'],
            'stockValue'   => round((float) $r['quantity'] * (float) $r['purchase_rate'], 2),
            'daysLeft'     => $days,
        ];
    }, $nearStmt->fetchAll());

    // --- Recent sales (last 6) ------------------------------------------------
    $recentSales = array_map(function ($r) use ($dbTz, $appTz) {
        $when  = (string) $r['sale_date'];
        // Only datetimes carry a time; a plain DATE is shown as stored.
        $local = strlen($when) > 10 ? (new DateTimeImmutable($when, $dbTz))->setTimezone($appTz) : null;
        return [
            'id'       => (int) $r['id'],
            'no'       => (string) ds_first($r, ['invoice_no', 'invoice_number', 'bill_no', 'sale_no'], 'INV-' . $r['id']),
            'customer' => $r['customer_name'] ?? 'Walk-in Customer',
            'type'     => strcasecmp((string) ($r['channel'] ?? $r['customer_type'] ?? ''), 'Wholesale') === 0 ? 'Wholesale' : 'Retail',
            'date'     => $local ? $local->format('Y-m-d') : substr($when, 0, 10),
            'time'     => $local ? $local->format('h:i A') : '',
            'amount'   => (float) $r['grand_total'],
            'payment'  => (string) ds_first($r, ['payment_mode', 'payment_method', 'payment_type'], (float) $r['balance_due'] > 0 ? 'Credit' : '—'),
            'status'   => ds_status($r),
        ];
    }, $pdo->query(
        'SELECT s.*, c.name AS customer_name, c.type AS customer_type
         FROM sales s LEFT JOIN customers c ON c.id = s.customer_id
         ORDER BY s.sale_date DESC, s.id DESC LIMIT 6'
    )->fetchAll());

    // --- Recent purchases (last 6) ---------------------------------------------
    $purchaseRows = $pdo->query(
        'SELECT p.*, sp.name AS supplier_name
         FROM purchases p LEFT JOIN suppliers sp ON sp.id = p.supplier_id
         ORDER BY p.invoice_date DESC, p.id DESC LIMIT 6'
    )->fetchAll();

    // Item counts per purchase (only if a purchase-items table is found; non-fatal)
    $itemCounts = [];
    try {
        $pit = ds_find_table($pdo, ['purchase_items', 'purchase_details', 'purchase_lines']);
        $ids = array_map(fn($r) => (int) $r['id'], $purchaseRows);
        if ($pit && $ids) {
            $fk = ds_pick(ds_columns($pdo, $pit), ['purchase_id']);
            if ($fk) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $st = $pdo->prepare("SELECT `{$fk}` AS pid, COUNT(*) AS n FROM `{$pit}` WHERE `{$fk}` IN ({$in}) GROUP BY `{$fk}`");
                $st->execute($ids);
                foreach ($st->fetchAll() as $r) {
                    $itemCounts[(int) $r['pid']] = (int) $r['n'];
                }
            }
        }
    } catch (Throwable $e) {
        error_log('dashboard-stats purchase items: ' . $e->getMessage());
    }

    $recentPurchases = array_map(fn($r) => [
        'id'          => (int) $r['id'],
        'no'          => (string) ds_first($r, ['grn_no', 'purchase_no', 'invoice_no', 'bill_no'], 'PUR-' . $r['id']),
        'supplierInv' => (string) ds_first($r, ['supplier_invoice_no', 'supplier_inv_no', 'supplier_bill_no', 'invoice_no', 'bill_no'], ''),
        'supplier'    => $r['supplier_name'] ?? '',
        'items'       => $itemCounts[(int) $r['id']] ?? (int) ds_first($r, ['item_count', 'items_count', 'total_items'], 0),
        'amount'      => (float) $r['grand_total'],
        'payment'     => (string) ds_first($r, ['payment_mode', 'payment_method', 'payment_type'], (float) $r['balance_due'] > 0 ? 'Credit' : '—'),
        'status'      => ds_status($r),
    ], $purchaseRows);

    // --- Top selling medicines, last 7 days (needs the sale-items table) ------
    $topSellers = [];
    try {
        $sit = ds_find_table($pdo, ['sale_items', 'sales_items', 'sale_details', 'sale_lines']);
        if ($sit) {
            $cols = ds_columns($pdo, $sit);
            $fk   = ds_pick($cols, ['sale_id', 'invoice_id']);
            $mid  = ds_pick($cols, ['medicine_id', 'med_id']);
            $qty  = ds_pick($cols, ['quantity', 'qty']);
            $amt  = ds_pick($cols, ['line_total', 'total', 'amount', 'total_amount', 'net_amount']);
            if ($fk && $mid && $qty) {
                $rev = $amt ? "COALESCE(SUM(si.`{$amt}`), 0)" : '0';
                $st = $pdo->prepare(
                    "SELECT si.`{$mid}` AS med_id, m.name, mf.name AS manufacturer,
                            SUM(si.`{$qty}`) AS sold_qty, {$rev} AS sold_revenue
                     FROM `{$sit}` si
                     JOIN sales s ON s.id = si.`{$fk}`
                     JOIN medicines m ON m.id = si.`{$mid}`
                     LEFT JOIN manufacturers mf ON mf.id = m.manufacturer_id
                     WHERE s.sale_date >= :f AND s.sale_date < :t
                     GROUP BY si.`{$mid}`, m.name, mf.name
                     ORDER BY sold_qty DESC
                     LIMIT 6"
                );
                $st->execute(['f' => $dbStart($d($today->modify('-6 days'))), 't' => $dbStart($d($tomorrow))]);
                $rank = 1;
                foreach ($st->fetchAll() as $r) {
                    $topSellers[] = [
                        'rank'         => $rank++,
                        'medId'        => (int) $r['med_id'],
                        'name'         => $r['name'],
                        'manufacturer' => $r['manufacturer'] ?? '',
                        'qty'          => (float) $r['sold_qty'],
                        'revenue'      => round((float) $r['sold_revenue'], 2),
                    ];
                }
            }
        }
    } catch (Throwable $e) {
        error_log('dashboard-stats top sellers: ' . $e->getMessage());
    }

    // --- Dues aging (customers, by age of the invoice with balance due) --------
    $localAgeDt = "DATE_ADD(sale_date, INTERVAL {$shiftMin} MINUTE)";
    $ageStmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN DATEDIFF(:t1, DATE({$localAgeDt})) <= 7 THEN balance_due ELSE 0 END), 0) AS a1,
            COALESCE(SUM(CASE WHEN DATEDIFF(:t2, DATE({$localAgeDt})) BETWEEN 8 AND 30 THEN balance_due ELSE 0 END), 0) AS a2,
            COALESCE(SUM(CASE WHEN DATEDIFF(:t3, DATE({$localAgeDt})) BETWEEN 31 AND 60 THEN balance_due ELSE 0 END), 0) AS a3,
            COALESCE(SUM(CASE WHEN DATEDIFF(:t4, DATE({$localAgeDt})) > 60 THEN balance_due ELSE 0 END), 0) AS a4
         FROM sales WHERE balance_due > 0"
    );
    $todayStr = $d($today);
    $ageStmt->execute(['t1' => $todayStr, 't2' => $todayStr, 't3' => $todayStr, 't4' => $todayStr]);
    $age = $ageStmt->fetch();

    // --- Diagnostics: open dashboard-stats.php?debug=1 on localhost --------------
    $debug = null;
    if ($isLocal && isset($_GET['debug'])) {
        $debug = [
            'php_now'          => (new DateTimeImmutable('now', $appTz))->format('Y-m-d H:i:s T'),
            'db_now'           => $pdo->query('SELECT NOW()')->fetchColumn(),
            'db_utc_now'       => $pdo->query('SELECT UTC_TIMESTAMP()')->fetchColumn(),
            'db_time_zone'     => $pdo->query('SELECT @@session.time_zone AS session_tz, @@system_time_zone AS system_tz')->fetch(),
            'sale_date_type'   => $saleDateType,
            'sales_total_rows' => (int) $pdo->query('SELECT COUNT(*) FROM sales')->fetchColumn(),
            'latest_sales'     => $pdo->query('SELECT id, sale_date, grand_total FROM sales ORDER BY sale_date DESC, id DESC LIMIT 5')->fetchAll(),
            // Every column of the newest rows (created_at etc.) to compare with sale_date
            'channel_breakdown' => in_array('channel', $saleCols, true)
                ? $pdo->query('SELECT channel, COUNT(*) AS n, SUM(grand_total) AS total FROM sales GROUP BY channel')->fetchAll()
                : 'no channel column',
            'latest_sales_raw' => $pdo->query('SELECT * FROM sales ORDER BY id DESC LIMIT 3')->fetchAll(),
            'latest_purchases_raw' => $pdo->query('SELECT * FROM purchases ORDER BY id DESC LIMIT 2')->fetchAll(),
            'today_range_used' => [$dbStart($d($today)), $dbStart($d($tomorrow))],
            'shift_minutes'    => $shiftMin,
            'rows_in_range'    => (int) ds_scalar(
                $pdo,
                'SELECT COUNT(*) FROM sales WHERE sale_date >= :f AND sale_date < :t',
                ['f' => $dbStart($d($today)), 't' => $dbStart($d($tomorrow))]
            ),
        ];
    }

    Json::ok([
        'data' => [
            'todaySales'         => round($todaySales, 2),
            'todaySalesDelta'    => ds_delta($todaySales, $yesterdaySales),
            'todayPurchase'      => round($todayPurchase, 2),
            'todayPurchaseDelta' => ds_delta($todayPurchase, $yesterdayPurchase),
            'grossProfit'        => 0,
            'grossProfitDelta'   => 0,
            'grossProfitMargin'  => 0,
            'stockValue'         => round((float) $stockRow['value'], 2),
            'stockUnits'         => (int) $stockRow['units'],
            'customerDue'        => round((float) $custDue['due'], 2),
            'customerDueParties' => (int) $custDue['parties'],
            'supplierDue'        => round((float) $supDue['due'], 2),
            'supplierDueParties' => (int) $supDue['parties'],
            'salesSplit'         => [
                'retail'    => round((float) $split['retail'], 2),
                'wholesale' => round((float) $split['wholesale'], 2),
            ],
            'salesTrend' => [
                'today' => $todayTrend,
                '7d'    => $trend7,
                '30d'   => $trend30,
                'month' => $trendMonth,
            ],
            'topSellers'      => $topSellers,
            'debug'           => $debug,
            'lowStock'        => $lowStock,
            'nearExpiry'      => $nearExpiry,
            'recentSales'     => $recentSales,
            'recentPurchases' => $recentPurchases,
            'dues' => [
                'customer' => round((float) $custDue['due'], 2),
                'supplier' => round((float) $supDue['due'], 2),
                'aging'    => [
                    'd0_7'   => round((float) $age['a1'], 2),
                    'd8_30'  => round((float) $age['a2'], 2),
                    'd31_60' => round((float) $age['a3'], 2),
                    'd60p'   => round((float) $age['a4'], 2),
                ],
            ],
        ],
    ]);
} catch (Throwable $e) {
    error_log('dashboard-stats failed: ' . $e->getMessage());
    // On localhost only, include the real error so it is visible in DevTools.
    Json::error($isLocal ? 'Could not load dashboard stats: ' . $e->getMessage() : 'Could not load dashboard stats.', 500);
}
