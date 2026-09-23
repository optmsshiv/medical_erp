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
  <style>
    /* Dashboard KPI cards v2 */
    .kpi-card-v2{ padding:1.1rem 1.25rem; }
    .kpi-label-v2{ font-size:.83rem; font-weight:600; color:#475467; }
    .kpi-icon-v2{
      width:34px; height:34px; border-radius:10px; flex-shrink:0;
      display:flex; align-items:center; justify-content:center; font-size:1rem;
    }
    .kpi-icon-v2.tone-green{ background:#e6f6ec; color:#1a9c53; }
    .kpi-icon-v2.tone-blue{ background:#e7f1fd; color:#1c6fea; }
    .kpi-icon-v2.tone-purple{ background:#f1ecfc; color:#7c4fe0; }
    .kpi-icon-v2.tone-indigo{ background:#eceafd; color:#5b4fe0; }
    .kpi-icon-v2.tone-orange{ background:#fdf1e2; color:#d98c15; }
    .kpi-icon-v2.tone-red{ background:#fdeaea; color:#e0473f; }
    .kpi-value-v2{ font-size:1.5rem; font-weight:800; color:#101828; margin:.4rem 0 .55rem; }
    .kpi-delta-v2{ display:flex; align-items:center; gap:.45rem; flex-wrap:wrap; }
    .delta-pill{
      display:inline-flex; align-items:center; gap:.25rem;
      font-size:.75rem; font-weight:700; padding:.15rem .55rem; border-radius:999px;
    }
    .delta-pill.up{ background:#e6f6ec; color:#1a9c53; }
    .delta-pill.down{ background:#fdeaea; color:#e0473f; }
    .kpi-caption{ font-size:.78rem; color:#8a94a6; }

    /* Quick action buttons */
    .btn-kbd{
      font-size:.68rem; font-weight:700; margin-left:.4rem;
      background:rgba(255,255,255,.2); padding:.05rem .4rem; border-radius:5px;
      vertical-align:1px;
    }
  </style>
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
            <a href="expiry-management.php" class="btn btn-light-mf"><i class="bi bi-hourglass-split me-1"></i>Expiry Review</a>
            <a href="purchase.php" class="btn btn-light-mf"><i class="bi bi-bag-plus me-1"></i>New Purchase</a>
            <a href="retail-pos.php" class="btn btn-mf" id="btnNewSale"><i class="bi bi-cart3 me-1"></i>New Sale<span class="btn-kbd">F2</span></a>
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
              <div class="p-3"><div class="chart-box"><canvas id="salesChart"></canvas></div><div class="text-center text-2 small mt-2 d-none" id="salesEmptyNote"></div></div>
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
                    <div class="kpi-value text-danger" id="dueCust">₹0</div>
                  </div>
                  <div class="kpi-icon tone-danger"><i class="bi bi-people"></i></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <div class="kpi-label mb-1">Total Supplier Dues</div>
                    <div class="kpi-value text-warning" id="dueSup">₹0</div>
                  </div>
                  <div class="kpi-icon tone-warning"><i class="bi bi-truck"></i></div>
                </div>
                <div class="divider-dashed my-3"></div>
                <div class="sr-group-label">Aging (customers)</div>
                <div class="sum-row"><span class="text-2">0–7 days</span><span class="num fw-semibold" id="ageA">₹0</span></div>
                <div class="sum-row"><span class="text-2">8–30 days</span><span class="num fw-semibold" id="ageB">₹0</span></div>
                <div class="sum-row"><span class="text-2">31–60 days</span><span class="num fw-semibold" id="ageC">₹0</span></div>
                <div class="sum-row"><span class="text-2">&gt; 60 days</span><span class="num fw-semibold text-danger" id="ageD">₹0</span></div>
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
      const MF = window.MF;
      await MF.boot();
      
      const D = window.MF_DATA;
      let db = D.dashboard || {};

      // 1. Live stats from api/v1/dashboard-stats.php
      // MF.Api.get returns the full JSON: { ok: true, data: {...} }
      try {
        const res = await MF.Api.get('dashboard-stats.php');
        const live = (res && res.data) ? res.data : res;
        if (live && live.todaySales !== undefined) {
          db = { ...db, ...live };
        }
      } catch (err) {
        console.warn('Live API Error:', err);
      }

      // charts.js reads MF_DATA.dashboard directly, so keep it in sync and
      // make sure the parts the charts need always exist.
      const emptyTrend = { labels: [], retail: [], wholesale: [] };
      db.salesTrend = db.salesTrend || { today: emptyTrend, '7d': emptyTrend, '30d': emptyTrend, month: emptyTrend };
      db.salesSplit = db.salesSplit || { retail: 0, wholesale: 0 };
      D.dashboard = db;

      // Date update
      const dateEl = document.getElementById('dashDate');
      if(dateEl) {
        dateEl.textContent = new Date().toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) + ' · Optms Rx';
      }

      /* F2 → New Sale shortcut */
      document.addEventListener('keydown', (e) => {
        if (e.key !== 'F2') return;
        const tag = document.activeElement && document.activeElement.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
        e.preventDefault();
        document.getElementById('btnNewSale').click();
      });

      /* 2. KPI Cards (With Crash Safeguards || 0) */
      const kpis = [
        { label: "Today's Sales", value: db.todaySales || 0, icon: 'graph-up-arrow', tone: 'green',
          dir: (db.todaySalesDelta || 0) >= 0 ? 'up' : 'down',
          delta: ((db.todaySalesDelta || 0) >= 0 ? '+' : '') + (db.todaySalesDelta || 0) + '%',
          caption: 'vs yesterday' },
          
        { label: "Today's Purchase", value: db.todayPurchase || 0, icon: 'cart3', tone: 'blue',
          dir: (db.todayPurchaseDelta || 0) >= 0 ? 'up' : 'down',
          delta: ((db.todayPurchaseDelta || 0) >= 0 ? '+' : '') + (db.todayPurchaseDelta || 0) + '%',
          caption: 'vs yesterday' },
          
        { label: 'Gross Profit', value: db.grossProfit || 0, icon: 'pie-chart', tone: 'purple',
          dir: (db.grossProfitDelta || 0) >= 0 ? 'up' : 'down',
          delta: ((db.grossProfitDelta || 0) >= 0 ? '+' : '') + (db.grossProfitDelta || 0) + '%',
          caption: db.grossProfitMargin ? db.grossProfitMargin + '% margin' : 'vs yesterday' },
          
        { label: 'Total Stock Value', value: db.stockValue || 0, icon: 'box-seam', tone: 'indigo',
          dir: 'up',
          delta: db.stockUnits ? Math.floor(db.stockUnits) + ' units' : '0 units',
          caption: 'at purchase cost' },
          
        { label: 'Customer Due', value: db.customerDue || 0, icon: 'wallet2', tone: 'orange',
          dir: 'down',
          delta: db.customerDueParties ? db.customerDueParties + ' parties' : '0 parties',
          caption: 'outstanding' },
          
        { label: 'Supplier Due', value: db.supplierDue || 0, icon: 'arrow-left-right', tone: 'red',
          dir: 'down',
          delta: db.supplierDueParties ? db.supplierDueParties + ' suppliers' : '0 suppliers',
          caption: 'payable' }
      ];
      
      const kpiContainer = document.getElementById('kpiRow');
      if (kpiContainer) {
        kpiContainer.innerHTML = kpis.map((k) => `
          <div class="col-6 col-md-4 col-xxl-2">
            <div class="card-mf kpi-card-v2 h-100">
              <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-label-v2">${k.label}</div>
                <div class="kpi-icon-v2 tone-${k.tone}"><i class="bi bi-${k.icon}"></i></div>
              </div>
              <div class="kpi-value-v2 num">${MF.fmt(k.value)}</div>
              <div class="kpi-delta-v2">
                <span class="delta-pill ${k.dir}"><i class="bi bi-arrow-${k.dir}-right"></i>${k.delta}</span>
                <span class="kpi-caption">${k.caption}</span>
              </div>
            </div>
          </div>`).join('');
      }

      /* Empty-state note under the sales chart when the selected period has no sales */
      const updateSalesNote = (p) => {
        const el = document.getElementById('salesEmptyNote');
        if (!el) return;
        const t = (db.salesTrend || {})[p] || {};
        const sum = [...(t.retail || []), ...(t.wholesale || [])].reduce((a, v) => a + Number(v || 0), 0);
        el.textContent = p === 'today' ? 'No sales recorded today yet. Try 7 Days.' : 'No sales recorded in this period.';
        el.classList.toggle('d-none', sum > 0);
      };

      /* 3. Charts & Graphics */
      if (window.MFCharts) {
        try {
          MFCharts.salesOverview('salesChart', 'today');
          MFCharts.salesSplit('splitChart');
        } catch (e) { console.warn('Chart error:', e); }
      }
      
      updateSalesNote('today');
      const split = db.salesSplit || { retail: 0, wholesale: 0 };
      const tot = (split.retail + split.wholesale) || 1;
      
      const legendContainer = document.getElementById('splitLegend');
      if (legendContainer) {
        legendContainer.innerHTML = (split.retail + split.wholesale) === 0 ? '<div class="text-center text-2 small">No sales recorded today yet</div>' : `
          <div class="sum-row"><span><span class="badge badge-soft-primary me-2">Retail</span></span><span class="num fw-semibold">${MF.fmt(split.retail)} · ${(split.retail / tot * 100).toFixed(0)}%</span></div>
          <div class="sum-row"><span><span class="badge badge-soft-warning me-2">Wholesale</span></span><span class="num fw-semibold">${MF.fmt(split.wholesale)} · ${(split.wholesale / tot * 100).toFixed(0)}%</span></div>`;
      }
        
      document.querySelectorAll('#salesPeriod button').forEach((b) => b.addEventListener('click', () => {
        document.querySelectorAll('#salesPeriod button').forEach((x) => x.classList.remove('active'));
        b.classList.add('active');
        if(window.MFCharts) MFCharts.salesOverview('salesChart', b.dataset.p);
        updateSalesNote(b.dataset.p);
      }));

      /* ---- Data sections: live arrays from dashboard-stats.php, demo fallback offline ---- */
      const emptyRow = (cols, msg) => `<tr><td colspan="${cols}" class="text-center text-2 py-4">${msg}</td></tr>`;
      const liveList = (k) => (Array.isArray(db[k]) ? db[k] : null);

      /* Low stock */
      const lowStockBody = document.getElementById('lowStockBody');
      if (lowStockBody) {
        let list = liveList('lowStock');
        if (!list && D.medicines) {
          list = D.medicines.filter((m) => MF.stockOf(m.id) <= m.minStock).map((m) => {
            const b = MF.pickBatch(m.id) || MF.batchesOf(m.id)[0];
            return { id: m.id, name: m.name, manufacturer: m.manufacturer, batchNo: b ? b.batchNo : '', stock: MF.stockOf(m.id), minStock: m.minStock };
          });
        }
        list = (list || []).slice().sort((a, b) => a.stock / (a.minStock || 1) - b.stock / (b.minStock || 1));
        lowStockBody.innerHTML = list.length ? list.map((m) => `
          <tr>
            <td><div class="td-title">${MF.esc(m.name)}</div><div class="td-sub">${MF.esc(m.manufacturer)}</div></td>
            <td class="num">${MF.esc(m.batchNo || '—')}</td>
            <td class="text-end num fw-semibold">${MF.num(m.stock)}</td>
            <td class="text-end num text-2">${MF.num(m.minStock)}</td>
            <td>${m.stock / (m.minStock || 1) < 0.5 ? MF.badge('Critical', 'danger') : MF.badge('Low', 'warning')}</td>
            <td class="text-end"><button class="btn btn-sm btn-mf-soft" data-order="${m.id}">Order</button></td>
          </tr>`).join('') : emptyRow(6, 'No low stock medicines');
      }

      /* Near expiry */
      const nearExpiryBody = document.getElementById('nearExpiryBody');
      if (nearExpiryBody) {
        let list = liveList('nearExpiry');
        if (!list && D.batches) {
          list = D.batches.filter((b) => MF.daysTo(b.expiry) <= 90).sort((a, b) => a.expiry.localeCompare(b.expiry)).map((b) => {
            const m = MF.med(b.medId) || {};
            return { name: m.name || '', manufacturer: m.manufacturer || '', batchNo: b.batchNo, expiry: b.expiry, qty: b.qty, stockValue: b.qty * b.purchaseRate, daysLeft: MF.daysTo(b.expiry) };
          });
        }
        list = list || [];
        nearExpiryBody.innerHTML = list.length ? list.map((b) => {
          const days = b.daysLeft;
          const tone = days <= 30 ? 'danger' : days <= 60 ? 'warning' : 'info';
          const label = days < 0 ? 'Expired' : days <= 30 ? 'Critical' : days <= 60 ? 'Expiring Soon' : 'Normal';
          return `<tr>
            <td><div class="td-title">${MF.esc(b.name)}</div><div class="td-sub">${MF.esc(b.manufacturer)}</div></td>
            <td class="num">${MF.esc(b.batchNo)}</td>
            <td class="num">${MF.fmtMonthYear(b.expiry)}</td>
            <td class="text-end num">${MF.num(b.qty)}</td>
            <td class="text-end num">${MF.fmt(b.stockValue)}</td>
            <td class="text-center"><span class="badge badge-soft-${tone}">${days < 0 ? Math.abs(days) + ' d ago' : days + ' days'} · ${label}</span></td>
          </tr>`;
        }).join('') : emptyRow(6, 'No batches expiring in the next 90 days');
      }

      /* Recent sales */
      const recentSalesBody = document.getElementById('recentSalesBody');
      if (recentSalesBody) {
        const list = (liveList('recentSales') || (D.salesInvoices || []).slice(0, 6));
        recentSalesBody.innerHTML = list.length ? list.map((i) => `
          <tr>
            <td><div class="td-title num">${MF.esc(i.no)}</div><div class="td-sub">${MF.fmtDate(i.date)}${i.time ? ' · ' + i.time : ''}</div></td>
            <td>${MF.esc(i.customer)}</td>
            <td>${i.type === 'Retail' ? MF.badge('Retail', 'primary') : MF.badge('Wholesale', 'warning')}</td>
            <td class="text-end num fw-semibold">${MF.fmt(i.amount)}</td>
            <td>${MF.esc(i.payment)}</td>
            <td>${MF.statusBadge(i.status)}</td>
          </tr>`).join('') : emptyRow(6, 'No sales yet');
      }

      /* Recent purchases */
      const recentPurchaseBody = document.getElementById('recentPurchaseBody');
      if (recentPurchaseBody) {
        const list = (liveList('recentPurchases') || (D.purchaseInvoices || []).slice(0, 6));
        recentPurchaseBody.innerHTML = list.length ? list.map((p) => `
          <tr>
            <td><div class="td-title num">${MF.esc(p.no)}</div><div class="td-sub num">${MF.esc(p.supplierInv || '')}</div></td>
            <td>${MF.esc(p.supplier)}</td>
            <td class="text-end num">${p.items}</td>
            <td class="text-end num fw-semibold">${MF.fmt(p.amount)}</td>
            <td>${MF.esc(p.payment)}</td>
            <td>${MF.statusBadge(p.status)}</td>
          </tr>`).join('') : emptyRow(6, 'No purchases yet');
      }

      /* Top sellers */
      const topSellersBody = document.getElementById('topSellersBody');
      if (topSellersBody) {
        const medals = ['🥇', '🥈', '🥉'];
        const list = Array.isArray(db.topSellers) ? db.topSellers : [];
        topSellersBody.innerHTML = list.length ? list.map((t) => `
          <tr>
            <td class="fw-bold">${medals[t.rank - 1] || '#' + t.rank}</td>
            <td><div class="td-title">${MF.esc(t.name)}</div><div class="td-sub">${MF.esc(t.manufacturer || (MF.med(t.medId) || {}).manufacturer || '')}</div></td>
            <td class="text-end num">${MF.num(t.qty)}</td>
            <td class="text-end num fw-semibold">${MF.fmt(t.revenue)}</td>
          </tr>`).join('') : emptyRow(4, 'No sales in the last 7 days');
      }

      /* Dues snapshot */
      const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
      setText('dueCust', MF.fmt(db.customerDue));
      setText('dueSup', MF.fmt(db.supplierDue));
      if (db.dues && db.dues.aging) {
        setText('ageA', MF.fmt(db.dues.aging.d0_7));
        setText('ageB', MF.fmt(db.dues.aging.d8_30));
        setText('ageC', MF.fmt(db.dues.aging.d31_60));
        setText('ageD', MF.fmt(db.dues.aging.d60p));
      }
    });
  </script>


</body>
</html>
