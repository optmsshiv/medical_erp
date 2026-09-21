<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php'; // redirects to /login.php if not logged in

$client = Tenant::current();
$user   = Auth::user();
$pdo    = Tenant::db();

// Real number — computed from actual batches, same as dashboard.php.
$stockValue = (float) $pdo->query('SELECT COALESCE(SUM(quantity * purchase_rate), 0) AS v FROM batches')->fetch()['v'];

// Honest zeros — no sales/purchase/customer/supplier tables exist yet.
// Wire these up for real once those modules are built.
$kpis = [
    'todaySales'    => 0,
    'todayPurchase' => 0,
    'grossProfit'   => 0,
    'stockValue'    => $stockValue,
    'customerDue'   => 0,
    'supplierDue'   => 0,
];

function inr($n) { return '₹' . number_format((float) $n, 0); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OPTMS-RX — <?= htmlspecialchars($client['name']) ?></title>
  <meta name="description" content="OPTMS-RX — retail POS, wholesale billing, batch & expiry tracking, GST invoicing.">
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
  <div class="container py-4 py-lg-5" style="max-width:1100px">

    <!-- Hero -->
    <div class="launch-hero mb-4">
      <div class="position-relative" style="z-index:2">
        <div class="d-flex align-items-center gap-2 mb-4">
          <img src="assets/images/logo.svg" width="46" alt="OPTMS-RX">
          <div>
            <div class="fw-bold fs-5">OPTMS-RX</div>
            <div class="small" style="color:#BFE3DA"><?= htmlspecialchars($client['name']) ?></div>
          </div>
          <div class="ms-auto text-end">
            <div class="small" style="color:#D7EBE6">Signed in as <?= htmlspecialchars($user['name']) ?></div>
            <a href="/logout.php" class="small" style="color:#fff">Log out</a>
          </div>
        </div>
        <h1 class="fw-bold mb-2" style="font-size:clamp(1.5rem,3.5vw,2.3rem);letter-spacing:-0.02em">
          Run your medical store like a modern business.
        </h1>
        <p class="mb-4" style="max-width:620px;color:#D7EBE6">
          Retail POS, wholesale GST billing, batch-wise inventory, expiry control, customer &amp; supplier dues,
          and pharmacy-first reports.
        </p>
        <a href="dashboard.php" class="btn btn-light fw-bold px-4 me-2"><i class="bi bi-grid-1x2-fill me-2"></i>Enter Dashboard</a>
        <a href="retail-pos.php" class="btn btn-outline-light px-4"><i class="bi bi-cart3 me-2"></i>Open Retail POS</a>
      </div>
    </div>

    <!-- Live KPI strip — real data from your database -->
    <div class="card-mf p-3 mb-4">
      <div class="row g-3 text-center">
        <div class="col-6 col-md-2"><div class="kpi-label">Today's Sales</div><div class="fw-bold num"><?= inr($kpis['todaySales']) ?></div></div>
        <div class="col-6 col-md-2"><div class="kpi-label">Today's Purchase</div><div class="fw-bold num"><?= inr($kpis['todayPurchase']) ?></div></div>
        <div class="col-6 col-md-2"><div class="kpi-label">Gross Profit</div><div class="fw-bold num text-success"><?= inr($kpis['grossProfit']) ?></div></div>
        <div class="col-6 col-md-2"><div class="kpi-label">Stock Value</div><div class="fw-bold num"><?= inr($kpis['stockValue']) ?></div></div>
        <div class="col-6 col-md-2"><div class="kpi-label">Customer Due</div><div class="fw-bold num text-danger"><?= inr($kpis['customerDue']) ?></div></div>
        <div class="col-6 col-md-2"><div class="kpi-label">Supplier Due</div><div class="fw-bold num text-warning"><?= inr($kpis['supplierDue']) ?></div></div>
      </div>
    </div>

    <!-- Modules -->
    <h2 class="fs-6 fw-bold text-uppercase text-2 mb-3" style="letter-spacing:.1em">Explore the modules</h2>
    <div class="row g-3 mb-4">
      <div class="col-md-6 col-lg-4">
        <a class="card-mf module-card p-3" href="dashboard.php">
          <div class="module-icon mb-3"><i class="bi bi-grid-1x2-fill"></i></div>
          <div class="fw-bold mb-1">Dashboard</div>
          <div class="text-2 small mb-2">Live KPIs, sales trends, low-stock and expiry alerts at a glance.</div>
          <span class="small fw-semibold" style="color:var(--mf-primary)">Open <i class="bi bi-arrow-right"></i></span>
        </a>
      </div>
      <div class="col-md-6 col-lg-4">
        <a class="card-mf module-card p-3" href="retail-pos.php">
          <div class="module-icon mb-3"><i class="bi bi-cart3"></i></div>
          <div class="fw-bold mb-1">Retail POS</div>
          <div class="text-2 small mb-2">Fast counter billing with FEFO batch picking, GST &amp; split payments.</div>
          <span class="small fw-semibold" style="color:var(--mf-primary)">Open <i class="bi bi-arrow-right"></i></span>
        </a>
      </div>
      <div class="col-md-6 col-lg-4">
        <a class="card-mf module-card p-3" href="#" onclick="return false;" style="opacity:.55;cursor:default">
          <div class="module-icon mb-3"><i class="bi bi-receipt"></i></div>
          <div class="fw-bold mb-1">Wholesale Billing</div>
          <div class="text-2 small mb-2">Dealer invoices with GSTIN, DL no., free qty, schemes &amp; CGST/SGST/IGST.</div>
          <span class="small fw-semibold text-2">Coming soon</span>
        </a>
      </div>
      <div class="col-md-6 col-lg-4">
        <a class="card-mf module-card p-3" href="purchase.php">
          <div class="module-icon mb-3"><i class="bi bi-bag-plus-fill"></i></div>
          <div class="fw-bold mb-1">Purchase &amp; GRN</div>
          <div class="text-2 small mb-2">Supplier invoices, batch entry with expiry, payment tracking.</div>
          <span class="small fw-semibold" style="color:var(--mf-primary)">Open <i class="bi bi-arrow-right"></i></span>
        </a>
      </div>
      <div class="col-md-6 col-lg-4">
        <a class="card-mf module-card p-3" href="expiry-management.php">
          <div class="module-icon mb-3"><i class="bi bi-calendar2-x"></i></div>
          <div class="fw-bold mb-1">Batch &amp; Expiry Control</div>
          <div class="text-2 small mb-2">Expiry dashboard with 30/60/90-day buckets and return-to-supplier flow.</div>
          <span class="small fw-semibold" style="color:var(--mf-primary)">Open <i class="bi bi-arrow-right"></i></span>
        </a>
      </div>
      <div class="col-md-6 col-lg-4">
        <a class="card-mf module-card p-3" href="medicine-master.php">
          <div class="module-icon mb-3"><i class="bi bi-capsule"></i></div>
          <div class="fw-bold mb-1">Medicine Master</div>
          <div class="text-2 small mb-2">Compositions, HSN, schedules, MRP/PTR/wholesale rates, reorder levels.</div>
          <span class="small fw-semibold" style="color:var(--mf-primary)">Open <i class="bi bi-arrow-right"></i></span>
        </a>
      </div>
      <div class="col-md-6 col-lg-4">
        <a class="card-mf module-card p-3" href="customers.php">
          <div class="module-icon mb-3"><i class="bi bi-people"></i></div>
          <div class="fw-bold mb-1">Customers &amp; Suppliers</div>
          <div class="text-2 small mb-2">Party ledgers with dues, payment history and purchase profiles.</div>
          <span class="small fw-semibold" style="color:var(--mf-primary)">Open <i class="bi bi-arrow-right"></i></span>
        </a>
      </div>
      <div class="col-md-6 col-lg-4">
        <a class="card-mf module-card p-3" href="#" onclick="return false;" style="opacity:.55;cursor:default">
          <div class="module-icon mb-3"><i class="bi bi-graph-up"></i></div>
          <div class="fw-bold mb-1">Reports</div>
          <div class="text-2 small mb-2">Sales, purchase, stock, expiry, profit, GST and due reports with CSV export.</div>
          <span class="small fw-semibold text-2">Coming soon</span>
        </a>
      </div>
      <div class="col-md-6 col-lg-4">
        <a class="card-mf module-card p-3" href="#" onclick="return false;" style="opacity:.55;cursor:default">
          <div class="module-icon mb-3"><i class="bi bi-gear"></i></div>
          <div class="fw-bold mb-1">Administration</div>
          <div class="text-2 small mb-2">Store, invoice &amp; tax settings, users, roles and audit logs.</div>
          <span class="small fw-semibold text-2">Coming soon</span>
        </a>
      </div>
    </div>

    <div class="card-mf p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
      <span class="badge badge-soft-primary"><i class="bi bi-shield-check me-1"></i>GST &amp; Drug-License compliant workflows</span>
      <span class="text-2 small ms-auto"><i class="bi bi-info-circle me-1"></i>Modules marked "Coming soon" are still in development.</span>
    </div>

    <p class="text-center text-2 small mb-0">OPTMS-RX · <?= htmlspecialchars($client['name']) ?></p>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
