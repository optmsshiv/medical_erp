<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php'; // redirects to /login.php if not logged in
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="dashboard">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <!-- Page head -->
        <div class="page-head">
          <div>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-sub" id="dashDate">Overview · Optms Rx, Madhepura</p>
          </div>
          <div class="ms-auto d-flex gap-2">
            <a href="purchase.php" class="btn btn-light-mf"><i class="bi bi-bag-plus me-1"></i>New Purchase</a>
            <a href="retail-pos.php" class="btn btn-mf"><i class="bi bi-cart3 me-1"></i>New Sale</a>
          </div>
        </div>

        <!-- KPI cards -->
        <div class="row g-3 mb-3" id="kpiRow"></div>

        <!-- Charts row -->
        <div class="row g-3 mb-3">
          <div class="col-xl-8">
            <div class="card-mf h-100">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-graph-up"></i>Sales Overview</h2>
                <div class="card-tools">
                  <div class="mf-segment" id="salesPeriod">
                    <button data-p="today" class="active">Today</button>
                    <button data-p="7d">7 Days</button>
                    <button data-p="30d">30 Days</button>
                    <button data-p="month">This Month</button>
                  </div>
                </div>
              </div>
              <div class="p-3"><div class="chart-box"><canvas id="salesChart"></canvas></div></div>
            </div>
          </div>
          <div class="col-xl-4">
            <div class="card-mf h-100">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-pie-chart"></i>Sales Split</h2>
                <div class="card-tools"><span class="badge badge-soft-secondary">Today</span></div>
              </div>
              <div class="p-3">
                <div class="chart-box" style="height:200px"><canvas id="splitChart"></canvas></div>
                <div class="mt-2" id="splitLegend"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Low stock + Near expiry -->
        <div class="row g-3 mb-3">
          <div class="col-xl-6">
            <div class="card-mf h-100">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-exclamation-triangle"></i>Low Stock Medicines</h2>
                <div class="card-tools"><a class="btn btn-sm btn-mf-soft" href="medicine-master.php?stock=low">View all</a></div>
              </div>
              <div class="table-scroll" style="max-height:330px">
                <table class="table table-mf">
                  <thead><tr><th>Medicine</th><th>Batch</th><th class="text-end">Available</th><th class="text-end">Minimum</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                  <tbody id="lowStockBody"></tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-xl-6">
            <div class="card-mf h-100">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-calendar2-x"></i>Near Expiry Medicines</h2>
                <div class="card-tools"><a class="btn btn-sm btn-mf-soft" href="expiry-management.php">Expiry center</a></div>
              </div>
              <div class="table-scroll" style="max-height:330px">
                <table class="table table-mf">
                  <thead><tr><th>Medicine</th><th>Batch</th><th>Expiry</th><th class="text-end">Qty</th><th class="text-end">Stock Value</th><th class="text-center">Days Left</th></tr></thead>
                  <tbody id="nearExpiryBody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent sales + purchases -->
        <div class="row g-3 mb-3">
          <div class="col-xl-6">
            <div class="card-mf h-100">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-receipt"></i>Recent Sales</h2>
                <div class="card-tools"><a class="btn btn-sm btn-mf-soft" href="reports.php?tab=sales">All invoices</a></div>
              </div>
              <div class="table-scroll" style="max-height:330px">
                <table class="table table-mf">
                  <thead><tr><th>Invoice</th><th>Customer</th><th>Type</th><th class="text-end">Amount</th><th>Payment</th><th>Status</th></tr></thead>
                  <tbody id="recentSalesBody"></tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-xl-6">
            <div class="card-mf h-100">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-truck"></i>Recent Purchases</h2>
                <div class="card-tools"><a class="btn btn-sm btn-mf-soft" href="reports.php?tab=purchase">All purchases</a></div>
              </div>
              <div class="table-scroll" style="max-height:330px">
                <table class="table table-mf">
                  <thead><tr><th>Invoice</th><th>Supplier</th><th class="text-end">Items</th><th class="text-end">Amount</th><th>Payment</th><th>Status</th></tr></thead>
                  <tbody id="recentPurchaseBody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Top sellers + receivables snapshot -->
        <div class="row g-3">
          <div class="col-xl-7">
            <div class="card-mf h-100">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-trophy"></i>Top Selling Medicines</h2>
                <div class="card-tools"><span class="badge badge-soft-secondary">Last 7 days</span></div>
              </div>
              <table class="table table-mf">
                <thead><tr><th style="width:50px">Rank</th><th>Medicine</th><th class="text-end">Qty Sold</th><th class="text-end">Revenue</th></tr></thead>
                <tbody id="topSellersBody"></tbody>
              </table>
            </div>
          </div>
          <div class="col-xl-5">
            <div class="card-mf h-100">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-wallet2"></i>Dues Snapshot</h2>
                <div class="card-tools"><a class="btn btn-sm btn-mf-soft" href="reports.php?tab=dues">Due report</a></div>
              </div>
              <div class="p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <div class="kpi-label mb-1">Total Customer Dues</div>
                    <div class="kpi-value text-danger">₹3,84,250</div>
                  </div>
                  <div class="kpi-icon tone-danger"><i class="bi bi-people"></i></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <div class="kpi-label mb-1">Total Supplier Dues</div>
                    <div class="kpi-value text-warning">₹2,76,800</div>
                  </div>
                  <div class="kpi-icon tone-warning"><i class="bi bi-truck"></i></div>
                </div>
                <div class="divider-dashed my-3"></div>
                <div class="sr-group-label">Aging (customers)</div>
                <div class="sum-row"><span class="text-2">0–7 days</span><span class="num fw-semibold">₹1,42,300</span></div>
                <div class="sum-row"><span class="text-2">8–30 days</span><span class="num fw-semibold">₹1,28,450</span></div>
                <div class="sum-row"><span class="text-2">31–60 days</span><span class="num fw-semibold">₹78,200</span></div>
                <div class="sum-row"><span class="text-2">&gt; 60 days</span><span class="num fw-semibold text-danger">₹35,300</span></div>
              </div>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js"></script>
  <script src="assets/js/charts.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', async () => {
      await MF.boot();
      const MF = window.MF, D = window.MF_DATA, db = D.dashboard;

      document.getElementById('dashDate').textContent =
        new Date().toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) + ' · Optms Rx, Madhepura';

      /* KPI cards */
      const kpis = [
        { label: "Today's Sales", value: db.todaySales, delta: db.todaySalesDelta, icon: 'cash-stack', tone: 'primary' },
        { label: "Today's Purchase", value: db.todayPurchase, delta: db.todayPurchaseDelta, icon: 'bag-check', tone: 'info' },
        { label: 'Gross Profit', value: db.grossProfit, delta: db.grossProfitDelta, icon: 'currency-rupee', tone: 'success' },
        { label: 'Total Stock Value', value: db.stockValue, delta: db.stockValueDelta, icon: 'boxes', tone: 'primary' },
        { label: 'Customer Due', value: db.customerDue, delta: db.customerDueDelta, icon: 'people', tone: 'danger', invert: true },
        { label: 'Supplier Due', value: db.supplierDue, delta: db.supplierDueDelta, icon: 'truck', tone: 'warning', invert: true }
      ];
      document.getElementById('kpiRow').innerHTML = kpis.map((k) => {
        const good = k.invert ? k.delta < 0 : k.delta >= 0;
        return `<div class="col-6 col-md-4 col-xxl-2">
          <div class="card-mf kpi-card h-100">
            <div class="kpi-icon tone-${k.tone}"><i class="bi bi-${k.icon}"></i></div>
            <div>
              <div class="kpi-label">${k.label}</div>
              <div class="kpi-value num">${MF.fmt(k.value)}</div>
              <div class="kpi-delta ${good ? 'up' : 'down'}">
                <i class="bi bi-arrow-${k.delta >= 0 ? 'up' : 'down'}-right"></i>${Math.abs(k.delta)}%<span class="vs">vs yesterday</span>
              </div>
            </div>
          </div>
        </div>`;
      }).join('');

      /* Charts */
      MFCharts.salesOverview('salesChart', 'today');
      MFCharts.salesSplit('splitChart');
      const split = db.salesSplit, tot = (split.retail + split.wholesale) || 1;
      document.getElementById('splitLegend').innerHTML = `
        <div class="sum-row"><span><span class="badge badge-soft-primary me-2">Retail</span></span><span class="num fw-semibold">${MF.fmt(split.retail)} · ${(split.retail / tot * 100).toFixed(0)}%</span></div>
        <div class="sum-row"><span><span class="badge badge-soft-warning me-2">Wholesale</span></span><span class="num fw-semibold">${MF.fmt(split.wholesale)} · ${(split.wholesale / tot * 100).toFixed(0)}%</span></div>`;
      document.querySelectorAll('#salesPeriod button').forEach((b) => b.addEventListener('click', () => {
        document.querySelectorAll('#salesPeriod button').forEach((x) => x.classList.remove('active'));
        b.classList.add('active');
        MFCharts.salesOverview('salesChart', b.dataset.p);
      }));

      /* Low stock */
      const lowMeds = D.medicines.filter((m) => MF.stockOf(m.id) <= m.minStock)
        .sort((a, b) => MF.stockOf(a.id) / a.minStock - MF.stockOf(b.id) / b.minStock);
      document.getElementById('lowStockBody').innerHTML = lowMeds.map((m) => {
        const st = MF.stockOf(m.id);
        const ratio = st / m.minStock;
        const b = MF.pickBatch(m.id) || MF.batchesOf(m.id)[0];
        return `<tr>
          <td><div class="td-title">${MF.esc(m.name)}</div><div class="td-sub">${MF.esc(m.manufacturer)}</div></td>
          <td class="num">${b ? b.batchNo : '—'}</td>
          <td class="text-end num fw-semibold">${MF.num(st)}</td>
          <td class="text-end num text-2">${MF.num(m.minStock)}</td>
          <td>${ratio < 0.5 ? MF.badge('Critical', 'danger') : MF.badge('Low', 'warning')}</td>
          <td class="text-end"><button class="btn btn-sm btn-mf-soft" data-order="${m.id}">Order</button></td>
        </tr>`;
      }).join('');
      document.querySelectorAll('[data-order]').forEach((b) => b.addEventListener('click', () =>
        MF.toast('Purchase draft created for ' + MF.med(b.dataset.order).name + ' (Stage 2 links to PO)', 'info', 'Reorder')));

      /* Near expiry */
      const nearBatches = D.batches.filter((b) => MF.daysTo(b.expiry) <= 90)
        .sort((a, b) => a.expiry.localeCompare(b.expiry));
      document.getElementById('nearExpiryBody').innerHTML = nearBatches.map((b) => {
        const m = MF.med(b.medId), days = MF.daysTo(b.expiry);
        const tone = days < 0 ? 'danger' : days <= 30 ? 'danger' : days <= 60 ? 'warning' : 'info';
        const label = days < 0 ? 'Expired' : days <= 30 ? 'Critical' : days <= 60 ? 'Expiring Soon' : 'Normal';
        return `<tr>
          <td><div class="td-title">${MF.esc(m.name)}</div><div class="td-sub">${MF.esc(m.manufacturer)}</div></td>
          <td class="num">${b.batchNo}</td>
          <td class="num">${MF.fmtMonthYear(b.expiry)}</td>
          <td class="text-end num">${b.qty}</td>
          <td class="text-end num">${MF.fmt(b.qty * b.purchaseRate)}</td>
          <td class="text-center"><span class="badge badge-soft-${tone}">${days < 0 ? Math.abs(days) + ' d ago' : days + ' days'} · ${label}</span></td>
        </tr>`;
      }).join('');

      /* Recent sales */
      document.getElementById('recentSalesBody').innerHTML = D.salesInvoices.slice(0, 6).map((i) => `
        <tr>
          <td><div class="td-title num">${i.no}</div><div class="td-sub">${MF.fmtDate(i.date)} · ${i.time}</div></td>
          <td>${MF.esc(i.customer)}</td>
          <td>${i.type === 'Retail' ? MF.badge('Retail', 'primary') : MF.badge('Wholesale', 'warning')}</td>
          <td class="text-end num fw-semibold">${MF.fmt(i.amount)}</td>
          <td>${i.payment}</td>
          <td>${MF.statusBadge(i.status)}</td>
        </tr>`).join('');

      /* Recent purchases */
      document.getElementById('recentPurchaseBody').innerHTML = D.purchaseInvoices.slice(0, 6).map((p) => `
        <tr>
          <td><div class="td-title num">${p.no}</div><div class="td-sub num">${p.supplierInv}</div></td>
          <td>${MF.esc(p.supplier)}</td>
          <td class="text-end num">${p.items}</td>
          <td class="text-end num fw-semibold">${MF.fmt(p.amount)}</td>
          <td>${p.payment}</td>
          <td>${MF.statusBadge(p.status)}</td>
        </tr>`).join('');

      /* Top sellers */
      const medals = ['🥇', '🥈', '🥉'];
      document.getElementById('topSellersBody').innerHTML = db.topSellers.map((t) => `
        <tr>
          <td class="fw-bold">${medals[t.rank - 1] || '#' + t.rank}</td>
          <td><div class="td-title">${MF.esc(t.name)}</div><div class="td-sub">${MF.esc(MF.med(t.medId).manufacturer)}</div></td>
          <td class="text-end num">${MF.num(t.qty)}</td>
          <td class="text-end num fw-semibold">${MF.fmt(t.revenue)}</td>
        </tr>`).join('');
    });
  </script>
</body>
</html>
