<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reports · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="reports">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-graph-up me-2 text-success"></i>Reports</h1>
            <p class="page-sub">Sales · purchase · stock · expiry · profit · GST · dues — exportable &amp; print-ready</p>
          </div>
          <div class="ms-auto d-flex align-items-center gap-2">
            <select class="form-select form-select-sm" id="rpPeriod" style="width:auto;min-width:150px">
              <option value="7">Last 7 days</option>
              <option value="30" selected>Last 30 days</option>
              <option value="90">Last 90 days</option>
              <option value="fy" id="rpFyOpt">This financial year</option>
              <option value="custom">Custom range</option>
            </select>
            <input type="date" class="form-control form-control-sm" id="rpFrom" style="width:150px">
            <span class="text-2 small">to</span>
            <input type="date" class="form-control form-control-sm" id="rpTo" style="width:150px">
            <button class="btn btn-sm btn-light-mf" id="rpApply"><i class="bi bi-funnel me-1"></i>Apply</button>
            <button class="btn btn-sm btn-mf" id="rpExport"><i class="bi bi-download me-1"></i>Export CSV</button>
            <button class="btn btn-sm btn-light-mf" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
          </div>
        </div>

        <div class="alert alert-warning py-2 small d-none" id="rpErrors"></div>

        <ul class="nav nav-pills-mf mb-3" id="rpTabs">
          <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#rp-sales" data-tab="sales"><i class="bi bi-receipt"></i>Sales</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#rp-purchase" data-tab="purchase"><i class="bi bi-bag-check"></i>Purchase</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#rp-stock" data-tab="stock"><i class="bi bi-box-seam"></i>Stock</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#rp-expiry" data-tab="expiry"><i class="bi bi-calendar-x"></i>Expiry</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#rp-profit" data-tab="profit"><i class="bi bi-currency-rupee"></i>Profit</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#rp-gst" data-tab="gst"><i class="bi bi-percent"></i>GST</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#rp-dues" data-tab="dues"><i class="bi bi-clock-history"></i>Dues</button></li>
        </ul>

        <div class="tab-content">

          <div class="tab-pane fade show active" id="rp-sales">
            <div class="card-mf">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-receipt"></i>Sales Report</h2>
                <div class="card-tools"><span class="badge badge-soft-secondary" id="rpSalesCount"></span></div>
              </div>
              <div class="table-scroll" style="max-height:none">
                <table class="table table-mf">
                  <thead><tr><th>Date</th><th>Invoice</th><th>Customer</th><th>Type</th><th class="text-end">Amount</th><th class="text-end">Tax</th><th class="text-end">Discount</th><th class="text-end">Profit</th><th>Payment</th></tr></thead>
                  <tbody id="rpSalesBody"></tbody>
                  <tfoot id="rpSalesFoot"></tfoot>
                </table>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="rp-purchase">
            <div class="card-mf">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-bag-check"></i>Purchase Report</h2></div>
              <div class="table-scroll" style="max-height:none">
                <table class="table table-mf">
                  <thead><tr><th>Date</th><th>Supplier</th><th>Invoice</th><th class="text-end">Amount</th><th class="text-end">Tax</th><th>Payment</th><th>Status</th></tr></thead>
                  <tbody id="rpPurchaseBody"></tbody>
                  <tfoot id="rpPurchaseFoot"></tfoot>
                </table>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="rp-stock">
            <div class="card-mf">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-box-seam"></i>Stock Report (batch-wise)</h2></div>
              <div class="table-scroll" style="max-height:none">
                <table class="table table-mf">
                  <thead><tr><th>Medicine</th><th>Batch</th><th class="text-end">Qty</th><th class="text-end">Purchase Value</th><th class="text-end">MRP Value</th><th>Status</th></tr></thead>
                  <tbody id="rpStockBody"></tbody>
                  <tfoot id="rpStockFoot"></tfoot>
                </table>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="rp-expiry">
            <div class="card-mf">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-calendar-x"></i>Expiry Report (≤ 90 days incl. expired)</h2></div>
              <div class="table-scroll" style="max-height:none">
                <table class="table table-mf">
                  <thead><tr><th>Medicine</th><th>Batch</th><th>Expiry</th><th class="text-end">Days</th><th class="text-end">Qty</th><th class="text-end">Value</th></tr></thead>
                  <tbody id="rpExpiryBody"></tbody>
                  <tfoot id="rpExpiryFoot"></tfoot>
                </table>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="rp-profit">
            <div class="row g-3 mb-3" id="rpProfitKpis"></div>
            <div class="row g-3">
              <div class="col-lg-5">
                <div class="card-mf h-100">
                  <div class="card-head"><h2 class="card-title"><i class="bi bi-graph-up"></i>GP Trend — Last 7 Days</h2></div>
                  <div class="p-3"><div class="chart-box sm"><canvas id="profitChart"></canvas></div></div>
                </div>
              </div>
              <div class="col-lg-7">
                <div class="card-mf h-100">
                  <div class="card-head"><h2 class="card-title"><i class="bi bi-table"></i>Category-wise Profitability</h2></div>
                  <div class="table-scroll" style="max-height:300px">
                    <table class="table table-mf">
                      <thead><tr><th>Category</th><th class="text-end">Sales</th><th class="text-end">Cost</th><th class="text-end">Gross Profit</th><th class="text-end">Margin %</th></tr></thead>
                      <tbody id="rpProfitBody"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="rp-gst">
            <div class="row g-3 mb-3" id="rpGstKpis"></div>
            <div class="row g-3">
              <div class="col-lg-5">
                <div class="card-mf h-100">
                  <div class="card-head"><h2 class="card-title"><i class="bi bi-bar-chart"></i>Output vs Input Tax</h2></div>
                  <div class="p-3"><div class="chart-box sm"><canvas id="gstChart"></canvas></div></div>
                </div>
              </div>
              <div class="col-lg-7">
                <div class="card-mf h-100">
                  <div class="card-head"><h2 class="card-title"><i class="bi bi-table"></i>B2B Invoice Tax Break-up</h2></div>
                  <div class="table-scroll" style="max-height:300px">
                    <table class="table table-mf">
                      <thead><tr><th>Invoice</th><th>Party / GSTIN</th><th class="text-end">Taxable</th><th class="text-end">CGST</th><th class="text-end">SGST</th><th class="text-end">IGST</th><th class="text-end">Total</th></tr></thead>
                      <tbody id="rpGstBody"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="rp-dues">
            <div class="row g-3">
              <div class="col-lg-7">
                <div class="card-mf h-100">
                  <div class="card-head"><h2 class="card-title"><i class="bi bi-people"></i>Customer Dues</h2></div>
                  <div class="table-scroll" style="max-height:430px">
                    <table class="table table-mf">
                      <thead><tr><th>Customer</th><th class="text-end">Total Sales</th><th class="text-end">Paid</th><th class="text-end">Due</th><th>Last Purchase</th></tr></thead>
                      <tbody id="rpDueCustBody"></tbody>
                      <tfoot id="rpDueCustFoot"></tfoot>
                    </table>
                  </div>
                </div>
              </div>
              <div class="col-lg-5">
                <div class="card-mf h-100">
                  <div class="card-head"><h2 class="card-title"><i class="bi bi-truck"></i>Supplier Dues</h2></div>
                  <div class="table-scroll" style="max-height:430px">
                    <table class="table table-mf">
                      <thead><tr><th>Supplier</th><th class="text-end">Due</th><th>Last Purchase</th></tr></thead>
                      <tbody id="rpDueSupBody"></tbody>
                      <tfoot id="rpDueSupFoot"></tfoot>
                    </table>
                  </div>
                </div>
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
    await (async function () {
      const MF = window.MF, D = window.MF_DATA;
      const $ = (s) => document.querySelector(s);
      let activeTab = 'sales';
      let data = null; // last response from reports.php

      const ymd = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
      const now = new Date();
      const fyYear = now.getMonth() >= 3 ? now.getFullYear() : now.getFullYear() - 1;
      $('#rpFyOpt').textContent = `FY ${fyYear}-${String(fyYear + 1).slice(2)}`;

      function applyPeriod() {
        const v = $('#rpPeriod').value;
        if (v === 'custom') return;
        const from = v === 'fy' ? new Date(fyYear, 3, 1) : new Date(now.getFullYear(), now.getMonth(), now.getDate() - (Number(v) - 1));
        $('#rpFrom').value = ymd(from);
        $('#rpTo').value = ymd(now);
      }
      applyPeriod();
      $('#rpPeriod').addEventListener('change', () => { applyPeriod(); if ($('#rpPeriod').value !== 'custom') load(); });
      ['#rpFrom', '#rpTo'].forEach((id) => $(id).addEventListener('change', () => { $('#rpPeriod').value = 'custom'; }));

      async function load() {
        const from = $('#rpFrom').value, to = $('#rpTo').value;
        if (from > to) { MF.toast('"From" date must be before "To" date.', 'warn'); return; }
        try {
          data = await MF.Api.get(`reports.php?from=${from}&to=${to}`);
        } catch (err) {
          MF.toast(err.message || 'Could not load reports.', 'danger');
          return;
        }
        if (!data || !data.sales) { MF.toast('Reports need the live backend (api/v1/reports.php).', 'warn'); return; }
        // Sections that failed on the server are listed here; the other tabs still work.
        const errs = data.errors || {};
        const bad = Object.keys(errs);
        $('#rpErrors').classList.toggle('d-none', !bad.length);
        $('#rpErrors').innerHTML = bad.length ? '<i class="bi bi-exclamation-triangle me-1"></i>Some reports could not load: ' + bad.map((k) => `<strong>${MF.esc(k)}</strong>${errs[k] && errs[k] !== 'failed' ? ' (' + MF.esc(errs[k]) + ')' : ''}`).join(', ') : '';
        [renderSales, renderPurchase, renderStock, renderExpiry, renderProfit, renderGst, renderDues].forEach((fn) => {
          try { fn(); } catch (e) { console.error(fn.name, e); } // one broken tab must not blank the rest
        });
      }

      function renderSales() {
        const rows = data.sales;
        $('#rpSalesCount').textContent = `${rows.length} invoice(s) · ${MF.fmtDate(data.range.from)} – ${MF.fmtDate(data.range.to)}`;
        $('#rpSalesBody').innerHTML = rows.map((i) => `
          <tr>
            <td class="num">${MF.fmtDate(i.date)}</td>
            <td class="num td-title">${i.no}</td>
            <td>${MF.esc(i.customer)}</td>
            <td>${i.channel === 'retail' ? MF.badge('Retail', 'primary') : MF.badge('Wholesale', 'warning')}</td>
            <td class="text-end num fw-semibold">${MF.fmt(i.amount)}</td>
            <td class="text-end num">${MF.fmt(i.tax)}</td>
            <td class="text-end num text-danger">${i.discount ? '−' + MF.fmt(i.discount) : '—'}</td>
            <td class="text-end num ${i.profit >= 0 ? 'text-success' : 'text-danger'}">${MF.fmt(i.profit)}</td>
            <td>${i.payment}</td>
          </tr>`).join('') || `<tr><td colspan="9"><div class="empty-state"><i class="bi bi-receipt"></i>No sales in this period.</div></td></tr>`;
        $('#rpSalesFoot').innerHTML = rows.length ? `<tr><td colspan="4">Total</td><td class="text-end num">${MF.fmt(rows.reduce((s, i) => s + i.amount, 0))}</td><td class="text-end num">${MF.fmt(rows.reduce((s, i) => s + i.tax, 0))}</td><td colspan="3"></td></tr>` : '';
      }

      function renderPurchase() {
        const rows = data.purchases;
        $('#rpPurchaseBody').innerHTML = rows.map((p) => `
          <tr>
            <td class="num">${MF.fmtDate(p.date)}</td>
            <td>${MF.esc(p.supplier)}</td>
            <td class="num td-title">${p.no}</td>
            <td class="text-end num fw-semibold">${MF.fmt(p.amount)}</td>
            <td class="text-end num">${MF.fmt(p.tax)}</td>
            <td>${p.payment}</td>
            <td>${MF.statusBadge(p.status)}</td>
          </tr>`).join('') || `<tr><td colspan="7"><div class="empty-state"><i class="bi bi-bag"></i>No purchases in this period.</div></td></tr>`;
        $('#rpPurchaseFoot').innerHTML = rows.length ? `<tr><td colspan="3">Total</td><td class="text-end num">${MF.fmt(rows.reduce((s, p) => s + p.amount, 0))}</td><td class="text-end num">${MF.fmt(rows.reduce((s, p) => s + p.tax, 0))}</td><td colspan="2"></td></tr>` : '';
      }

      function renderStock() {
        const rows = data.stock.filter((b) => b.qty > 0);
        $('#rpStockBody').innerHTML = rows.map((b) => `
          <tr>
            <td class="td-title">${MF.esc(b.name)}</td>
            <td class="num">${b.batch}</td>
            <td class="text-end num">${b.qty}</td>
            <td class="text-end num">${MF.fmt(b.purchaseValue)}</td>
            <td class="text-end num">${MF.fmt(b.mrpValue)}</td>
            <td>${MF.statusBadge(b.status)}</td>
          </tr>`).join('') || `<tr><td colspan="6"><div class="empty-state"><i class="bi bi-box-seam"></i>No stock recorded.</div></td></tr>`;
        $('#rpStockFoot').innerHTML = rows.length ? `<tr><td colspan="2">Total</td><td class="text-end num">${MF.num(rows.reduce((s, b) => s + b.qty, 0))}</td><td class="text-end num">${MF.fmt(rows.reduce((s, b) => s + b.purchaseValue, 0))}</td><td class="text-end num">${MF.fmt(rows.reduce((s, b) => s + b.mrpValue, 0))}</td><td></td></tr>` : '';
      }

      function renderExpiry() {
        const rows = data.expiry;
        $('#rpExpiryBody').innerHTML = rows.map((b) => `
          <tr>
            <td class="td-title">${MF.esc(b.name)}</td>
            <td class="num">${b.batch}</td>
            <td class="num">${MF.fmtMonthYear(b.expiry)}</td>
            <td class="text-end num ${b.days < 0 ? 'text-danger fw-semibold' : ''}">${b.days}</td>
            <td class="text-end num">${b.qty}</td>
            <td class="text-end num">${MF.fmt(b.value)}</td>
          </tr>`).join('') || `<tr><td colspan="6"><div class="empty-state"><i class="bi bi-calendar-check"></i>Nothing expiring soon.</div></td></tr>`;
        $('#rpExpiryFoot').innerHTML = rows.length ? `<tr><td colspan="4">Total at-risk value</td><td class="text-end num">${MF.num(rows.reduce((s, b) => s + b.qty, 0))}</td><td class="text-end num">${MF.fmt(rows.reduce((s, b) => s + b.value, 0))}</td></tr>` : '';
      }

      function renderProfit() {
        const k = data.profitKpis;
        const margin = k.sales > 0 ? (k.gp / k.sales * 100).toFixed(1) : '0.0';
        $('#rpProfitKpis').innerHTML = [
          ['Sales (selected period)', MF.fmt(k.sales), 'primary', 'graph-up-arrow'],
          ['Cost of Goods', MF.fmt(k.cost), 'info', 'box-seam'],
          ['Gross Profit', MF.fmt(k.gp), 'success', 'currency-rupee', margin + '% margin'],
        ].map(([l, v, tone, icon, delta]) => `
          <div class="col-lg-4"><div class="card-mf kpi-card h-100"><div class="kpi-icon tone-${tone}"><i class="bi bi-${icon}"></i></div>
            <div><div class="kpi-label">${l}</div><div class="kpi-value num">${v}</div>${delta ? `<span class="kpi-delta up"><i class="bi bi-arrow-up-right"></i>${delta}</span>` : ''}</div></div></div>`).join('');
        $('#rpProfitBody').innerHTML = data.profitByCategory.map((c) => `
          <tr><td>${MF.esc(c.category)}</td><td class="text-end num">${MF.fmt(c.sales)}</td><td class="text-end num text-2">${MF.fmt(c.cost)}</td>
            <td class="text-end num text-success fw-semibold">${MF.fmt(c.gp)}</td><td class="text-end num">${c.margin}%</td></tr>`).join('')
          || `<tr><td colspan="5"><div class="empty-state"><i class="bi bi-graph-up"></i>No sales in this period.</div></td></tr>`;
        if (window._profitChart) window._profitChart.destroy();
        window._profitChart = new Chart($('#profitChart'), {
          type: 'line',
          data: { labels: data.profitTrend.map((x) => x.label), datasets: [{ label: 'Gross profit', data: data.profitTrend.map((x) => x.value), borderColor: '#2f8f77', backgroundColor: 'rgba(47,143,119,.15)', fill: true, tension: 0.4, borderWidth: 2.5, pointRadius: 3 }] },
          options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => 'Profit: ' + MF.fmt(c.parsed.y) } } }, scales: { x: { grid: { display: false } }, y: { ticks: { callback: (v) => '₹' + (Math.abs(v) >= 1000 ? v / 1000 + 'k' : v) } } } }
        });
      }

      function renderGst() {
        const k = data.gstKpis;
        $('#rpGstKpis').innerHTML = [
          ['Taxable Sales', k.taxable, 'primary', 'receipt'],
          ['CGST Collected', k.cgst, 'success', 'percent'],
          ['SGST Collected', k.sgst, 'success', 'percent'],
          ['IGST Collected', k.igst, 'info', 'globe-asia']
        ].map(([l, v, tone, icon]) => `
          <div class="col-6 col-xl-3"><div class="card-mf kpi-card h-100">
            <div class="kpi-icon tone-${tone}"><i class="bi bi-${icon}"></i></div>
            <div><div class="kpi-label">${l}</div><div class="kpi-value num">${MF.fmt(v)}</div></div>
          </div></div>`).join('');
        $('#rpGstBody').innerHTML = data.gstInvoices.map((i) => `
          <tr>
            <td class="num td-title">${i.no}</td>
            <td><div>${MF.esc(i.party)}</div><div class="td-sub num">${i.gstin}</div></td>
            <td class="text-end num">${MF.fmt(i.taxable)}</td>
            <td class="text-end num">${MF.fmt(i.cgst)}</td>
            <td class="text-end num">${MF.fmt(i.sgst)}</td>
            <td class="text-end num">${MF.fmt(i.igst)}</td>
            <td class="text-end num fw-semibold">${MF.fmt(i.total)}</td>
          </tr>`).join('') || `<tr><td colspan="7"><div class="empty-state"><i class="bi bi-receipt"></i>No wholesale invoices in this period.</div></td></tr>`;
        if (window._gstChart) window._gstChart.destroy();
        window._gstChart = new Chart($('#gstChart'), {
          type: 'bar',
          data: { labels: ['CGST', 'SGST', 'IGST'], datasets: [
            { label: 'Output (sales)', data: [k.cgst, k.sgst, k.igst], backgroundColor: '#2f8f77', borderRadius: 6 },
            { label: 'Input (purchases)', data: [data.gstIn.cgst, data.gstIn.sgst, data.gstIn.igst], backgroundColor: '#f59e0b', borderRadius: 6 } ] },
          options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + MF.fmt(c.parsed.y) } } }, scales: { y: { ticks: { callback: (v) => MF.fmt(v) } } } }
        });
      }

      function renderDues() {
        const custRows = ((data.dues || {}).customers || []).filter((c) => c.due > 0);
        $('#rpDueCustBody').innerHTML = custRows.map((c) => `
          <tr>
            <td class="td-title">${MF.esc(c.name)}</td>
            <td class="text-end num">${MF.fmt(c.totalSales)}</td>
            <td class="text-end num text-success">${MF.fmt(c.paid)}</td>
            <td class="text-end num text-danger fw-semibold">${MF.fmt(c.due)}</td>
            <td class="num text-2">${c.lastPurchase ? MF.fmtDate(c.lastPurchase) : '—'}</td>
          </tr>`).join('') || `<tr><td colspan="5"><div class="empty-state"><i class="bi bi-check-circle"></i>No customer dues.</div></td></tr>`;
        $('#rpDueCustFoot').innerHTML = custRows.length ? `<tr><td>Total</td><td colspan="2"></td><td class="text-end num text-danger">${MF.fmt(custRows.reduce((s, c) => s + c.due, 0))}</td><td></td></tr>` : '';

        const supRows = ((data.dues || {}).suppliers || []).filter((s) => s.due > 0);
        $('#rpDueSupBody').innerHTML = supRows.map((s) => `
          <tr>
            <td>${MF.esc(s.name)}</td>
            <td class="text-end num text-warning fw-semibold">${MF.fmt(s.due)}</td>
            <td class="num text-2">${s.lastPurchase ? MF.fmtDate(s.lastPurchase) : '—'}</td>
          </tr>`).join('') || `<tr><td colspan="3"><div class="empty-state"><i class="bi bi-check-circle"></i>No supplier dues.</div></td></tr>`;
        $('#rpDueSupFoot').innerHTML = supRows.length ? `<tr><td>Total</td><td class="text-end num text-warning">${MF.fmt(supRows.reduce((s, x) => s + x.due, 0))}</td><td></td></tr>` : '';
      }

      $('#rpExport').addEventListener('click', () => {
        const map = {
          sales: ['Sales', ['Date', 'Invoice', 'Customer', 'Type', 'Amount', 'Tax', 'Discount', 'Profit', 'Payment'],
            () => data.sales.map((i) => [i.date, i.no, i.customer, i.channel, i.amount, i.tax, i.discount, i.profit, i.payment])],
          purchase: ['Purchase', ['Date', 'Supplier', 'Invoice', 'Amount', 'Tax', 'Payment', 'Status'],
            () => data.purchases.map((p) => [p.date, p.supplier, p.no, p.amount, p.tax, p.payment, p.status])],
          stock: ['Stock', ['Medicine', 'Batch', 'Qty', 'Purchase Value', 'MRP Value', 'Status'],
            () => data.stock.filter((b) => b.qty > 0).map((b) => [b.name, b.batch, b.qty, b.purchaseValue, b.mrpValue, b.status])],
          expiry: ['Expiry', ['Medicine', 'Batch', 'Expiry', 'Days', 'Qty', 'Value'],
            () => data.expiry.map((b) => [b.name, b.batch, b.expiry, b.days, b.qty, b.value])],
          profit: ['Profit', ['Category', 'Sales', 'Cost', 'GP', 'Margin%'],
            () => data.profitByCategory.map((c) => [c.category, c.sales, c.cost, c.gp, c.margin])],
          gst: ['GST', ['Invoice', 'Party', 'GSTIN', 'Taxable', 'CGST', 'SGST', 'IGST', 'Total'],
            () => data.gstInvoices.map((i) => [i.no, i.party, i.gstin, i.taxable, i.cgst, i.sgst, i.igst, i.total])],
          dues: ['Dues', ['Party', 'Type', 'Total Sales', 'Paid', 'Due', 'Last Purchase'],
            () => [
              ...((data.dues || {}).customers || []).map((c) => ['Customer: ' + c.name, 'Customer', c.totalSales, c.paid, c.due, c.lastPurchase || '']),
              ...((data.dues || {}).suppliers || []).map((s) => ['Supplier: ' + s.name, 'Supplier', s.totalPurchases, s.paid, s.due, s.lastPurchase || '']),
            ]]
        };
        if (!data) { MF.toast('Nothing to export yet.', 'warn'); return; }
        const [name, headers, rowsFn] = map[activeTab];
        MF.exportCSV(name.toLowerCase() + '-report.csv', headers, rowsFn());
      });

      $('#rpApply').addEventListener('click', load);

      document.querySelectorAll('#rpTabs [data-tab]').forEach((b) => b.addEventListener('shown.bs.tab', () => {
        activeTab = b.dataset.tab;
      }));

      await load();

      const tab = new URLSearchParams(location.search).get('tab');
      if (tab) {
        const btn = document.querySelector(`#rpTabs [data-tab="${tab}"]`);
        if (btn) { bootstrap.Tab.getOrCreateInstance(btn).show(); activeTab = tab; }
      }
    })();
    });
  </script>
</body>
</html>
