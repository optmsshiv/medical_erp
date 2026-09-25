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
  <title>Stock Management · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="stock-overview">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-clipboard-data me-2 text-success"></i>Stock Management</h1>
            <p class="page-sub" id="stCount"></p>
          </div>
          <div class="ms-auto d-flex gap-2">
            <button class="btn btn-light-mf" id="stExport"><i class="bi bi-download me-1"></i>Export CSV</button>
          </div>
        </div>

        <ul class="nav nav-pills mb-3" id="stockTabs">
          <li class="nav-item"><button class="nav-link active" data-tab="overview" type="button"><i class="bi bi-grid me-1"></i>Overview</button></li>
          <li class="nav-item"><button class="nav-link" data-tab="low" type="button"><i class="bi bi-exclamation-triangle me-1"></i>Low Stock</button></li>
          <li class="nav-item"><button class="nav-link" data-tab="adjustment" type="button"><i class="bi bi-sliders me-1"></i>Adjustments</button></li>
          <li class="nav-item"><button class="nav-link" data-tab="transfer" type="button"><i class="bi bi-arrow-left-right me-1"></i>Counter Transfer</button></li>
        </ul>

        <div id="stockBody"></div>

      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', async () => {
      await MF.boot();
    (function () {
      const MF = window.MF, D = window.MF_DATA;
      const $ = (s) => document.querySelector(s);

      /* Fallback counters shown when there's no live backend yet (demo mode),
         matching the two rows the migration seeds. */
      const DEMO_COUNTERS = [{ id: 1, name: 'Main Store' }, { id: 2, name: 'Counter 1' }];
      const REASONS = ['Damaged', 'Expired', 'Lost / Theft', 'Recount Correction', 'Other'];

      const state = { tab: 'overview', counters: DEMO_COUNTERS, adjustments: [], transfers: [] };

      /* ---------------- Shared stock helpers ---------------- */
      function allStockRows() {
        return D.medicines.map((m) => ({ m, stock: MF.stockOf(m.id) }));
      }
      function lowStockRows() {
        return allStockRows().filter((x) => x.stock <= (x.m.minStock || 0)).sort((a, b) => a.stock - b.stock);
      }
      function stockValue() {
        return D.batches.reduce((s, b) => s + b.qty * b.purchaseRate, 0);
      }

      /* ---------------- Tab bar ---------------- */
      function renderTabs() {
        $$('#stockTabs [data-tab]').forEach((b) => b.classList.toggle('active', b.dataset.tab === state.tab));
        $('#stExport').style.display = (state.tab === 'overview' || state.tab === 'low') ? '' : 'none';
      }
      function $$(sel) { return Array.from(document.querySelectorAll(sel)); }

      /* ---------------- Overview tab ---------------- */
      function renderOverview() {
        const rows = allStockRows();
        const counts = { in: 0, low: 0, out: 0 };
        rows.forEach((x) => {
          if (x.stock <= 0) counts.out++;
          else if (x.stock <= (x.m.minStock || 0)) counts.low++;
          else counts.in++;
        });
        const kpis = [
          ['Stock Value', MF.fmt(stockValue()), 'primary', 'box-seam'],
          ['In Stock', counts.in, 'success', 'check-circle'],
          ['Low Stock', counts.low, 'warning', 'exclamation-triangle'],
          ['Out of Stock', counts.out, 'danger', 'x-circle']
        ].map(([l, v, tone, icon]) => `
          <div class="col-6 col-xl-3"><div class="card-mf kpi-card">
            <div class="kpi-icon tone-${tone}"><i class="bi bi-${icon}"></i></div>
            <div><div class="kpi-label">${l}</div><div class="kpi-value num">${typeof v === 'number' ? MF.num(v) : v}</div></div>
          </div></div>`).join('');

        $('#stockBody').innerHTML = `
          <div class="row g-3 mb-3">${kpis}</div>
          <div class="card-mf">
            <div class="table-responsive"><table class="table align-middle mb-0">
              <thead><tr><th>Medicine</th><th>Unit</th><th class="text-end">MRP</th><th class="text-end">Stock</th><th class="text-end">Min. Stock</th><th>Status</th></tr></thead>
              <tbody id="stOverviewBody"></tbody>
            </table></div>
          </div>`;
        $('#stOverviewBody').innerHTML = rows.map(({ m, stock }) => `
          <tr>
            <td><div class="td-title">${MF.esc(m.name)}</div><div class="td-sub">${MF.esc(m.composition)}</div></td>
            <td>${MF.esc(m.unit)}</td>
            <td class="text-end num">${MF.fmt(m.mrp, 2)}</td>
            <td class="text-end num fw-semibold">${MF.num(stock)}</td>
            <td class="text-end num text-2">${MF.num(m.minStock || 0)}</td>
            <td>${MF.stockBadge(m)}</td>
          </tr>`).join('') || `<tr><td colspan="6"><div class="empty-state"><i class="bi bi-capsule"></i>No medicines yet.</div></td></tr>`;
        $('#stCount').textContent = `${D.medicines.length} medicines · ${MF.fmt(stockValue())} in stock at purchase cost`;
      }

      /* ---------------- Low Stock tab ---------------- */
      function renderLow() {
        const rows = lowStockRows();
        $('#stockBody').innerHTML = `
          <div class="card-mf">
            <div class="table-responsive"><table class="table align-middle mb-0">
              <thead><tr><th>Medicine</th><th class="text-end">Stock</th><th class="text-end">Min. Stock</th><th>Status</th><th class="text-end">Action</th></tr></thead>
              <tbody id="stLowBody"></tbody>
            </table></div>
          </div>`;
        $('#stLowBody').innerHTML = rows.map(({ m, stock }) => `
          <tr>
            <td><div class="td-title">${MF.esc(m.name)}</div><div class="td-sub">${MF.esc(m.composition)}</div></td>
            <td class="text-end num fw-semibold">${MF.num(stock)}</td>
            <td class="text-end num text-2">${MF.num(m.minStock || 0)}</td>
            <td>${MF.stockBadge(m)}</td>
            <td class="text-end"><a class="btn btn-sm-erp btn-soft" href="purchase.php?med=${m.id}"><i class="bi bi-cart-plus"></i> Reorder</a></td>
          </tr>`).join('') || `<tr><td colspan="5"><div class="empty-state"><i class="bi bi-emoji-smile"></i>All stock levels are healthy.</div></td></tr>`;
        $('#stCount').textContent = `${rows.length} medicine(s) at or below Minimum Stock`;
      }

      /* ---------------- Batch dropdown (shared by both forms) ---------------- */
      function fillBatchSelect(sel, medId, { includeQty = true } = {}) {
        const batches = MF.batchesOf(medId).filter((b) => b.qty > 0);
        sel.innerHTML = batches.length
          ? batches.map((b) => `<option value="${b.id}">${MF.esc(b.batchNo)} — ${b.qty} in stock — Exp ${MF.fmtMonthYear(b.expiry)}</option>`).join('')
          : `<option value="">No sellable batches</option>`;
      }

      /* ---------------- Adjustments tab ---------------- */
      function renderAdjustment() {
        $('#stCount').textContent = 'Correct stock for damage, loss, expiry write-off, or a physical recount';
        $('#stockBody').innerHTML = `
          <div class="row g-3">
            <div class="col-lg-5">
              <div class="card-mf p-3">
                <h6 class="fw-bold mb-3">New Adjustment</h6>
                <div class="mb-2"><label class="form-label">Medicine</label>
                  <select class="form-select" id="adjMed"></select></div>
                <div class="mb-2"><label class="form-label">Batch</label>
                  <select class="form-select" id="adjBatch"></select></div>
                <div class="row g-2 mb-2">
                  <div class="col-6"><label class="form-label">Type</label>
                    <select class="form-select" id="adjType"><option value="1">Add stock (found / recount)</option><option value="-1">Remove stock (damage / loss)</option></select></div>
                  <div class="col-6"><label class="form-label">Quantity</label>
                    <input type="number" min="1" class="form-control" id="adjQty" value="1"></div>
                </div>
                <div class="mb-2"><label class="form-label">Reason</label>
                  <select class="form-select" id="adjReason">${REASONS.map((r) => `<option>${r}</option>`).join('')}</select></div>
                <div class="mb-3"><label class="form-label">Notes (optional)</label>
                  <textarea class="form-control" id="adjNotes" rows="2"></textarea></div>
                <button class="btn btn-mf w-100" id="adjSubmit"><i class="bi bi-check2 me-1"></i>Save Adjustment</button>
              </div>
            </div>
            <div class="col-lg-7">
              <div class="card-mf">
                <div class="table-responsive"><table class="table align-middle mb-0">
                  <thead><tr><th>Date</th><th>Medicine</th><th>Batch</th><th class="text-end">Change</th><th>Reason</th></tr></thead>
                  <tbody id="adjHistBody"></tbody>
                </table></div>
              </div>
            </div>
          </div>`;

        const medSel = $('#adjMed');
        medSel.innerHTML = D.medicines.map((m) => `<option value="${m.id}">${MF.esc(m.name)}</option>`).join('');
        fillBatchSelect($('#adjBatch'), medSel.value);
        medSel.addEventListener('change', () => fillBatchSelect($('#adjBatch'), medSel.value));

        $('#adjSubmit').addEventListener('click', async () => {
          if (!MF.Api.live) { MF.toast('Stock adjustments need the live backend — this is demo mode.', 'info', 'Demo mode'); return; }
          const medicineId = +medSel.value, batchId = +$('#adjBatch').value;
          const qty = Math.abs(+$('#adjQty').value || 0) * (+$('#adjType').value);
          if (!batchId) { MF.toast('No sellable batch for this medicine.', 'warn', 'Adjustment'); return; }
          if (!qty) { MF.toast('Enter a quantity greater than zero.', 'warn', 'Adjustment'); return; }
          const btn = $('#adjSubmit'); btn.disabled = true;
          try {
            await MF.Api.post('stock-adjustment.php', { medicineId, batchId, qtyChange: qty, reason: $('#adjReason').value, notes: $('#adjNotes').value });
            MF.toast('Stock adjustment saved.', 'success', 'Adjustments');
            await MF.rehydrate();
            await loadHistory();
            renderAdjustment();
          } catch (err) {
            MF.toast(err.message || 'Could not save the adjustment.', 'danger', 'Adjustments');
          } finally {
            btn.disabled = false;
          }
        });

        $('#adjHistBody').innerHTML = state.adjustments.length ? state.adjustments.map((a) => {
          const m = MF.med(a.medicine_id);
          return `<tr>
            <td class="num">${MF.fmtDate(a.created_at ? a.created_at.slice(0, 10) : '')}</td>
            <td>${MF.esc(m ? m.name : '#' + a.medicine_id)}</td>
            <td class="num">${MF.esc(a.batch_id)}</td>
            <td class="text-end num ${a.qty_change < 0 ? 'text-danger' : 'text-success'}">${a.qty_change > 0 ? '+' : ''}${a.qty_change}</td>
            <td>${MF.esc(a.reason)}</td>
          </tr>`;
        }).join('') : `<tr><td colspan="5"><div class="empty-state"><i class="bi bi-sliders"></i>${MF.Api.live ? 'No adjustments recorded yet.' : 'Adjustment history needs the live backend.'}</div></td></tr>`;
      }

      /* ---------------- Counter Transfer tab ---------------- */
      function renderTransfer() {
        $('#stCount').textContent = 'Move stock between counters at this store — total stock owned doesn\'t change';
        const counterOpts = state.counters.map((c) => `<option value="${c.id}">${MF.esc(c.name)}</option>`).join('');
        $('#stockBody').innerHTML = `
          <div class="row g-3">
            <div class="col-lg-5">
              <div class="card-mf p-3">
                <h6 class="fw-bold mb-3">New Transfer</h6>
                <div class="mb-2"><label class="form-label">Medicine</label>
                  <select class="form-select" id="trfMed"></select></div>
                <div class="mb-2"><label class="form-label">Batch</label>
                  <select class="form-select" id="trfBatch"></select></div>
                <div class="mb-2"><label class="form-label">Quantity</label>
                  <input type="number" min="1" class="form-control" id="trfQty" value="1"></div>
                <div class="row g-2 mb-3">
                  <div class="col-6"><label class="form-label">From</label><select class="form-select" id="trfFrom">${counterOpts}</select></div>
                  <div class="col-6"><label class="form-label">To</label><select class="form-select" id="trfTo">${counterOpts}</select></div>
                </div>
                <div class="mb-3"><label class="form-label">Notes (optional)</label>
                  <textarea class="form-control" id="trfNotes" rows="2"></textarea></div>
                <button class="btn btn-mf w-100" id="trfSubmit"><i class="bi bi-arrow-left-right me-1"></i>Save Transfer</button>
              </div>
            </div>
            <div class="col-lg-7">
              <div class="card-mf">
                <div class="table-responsive"><table class="table align-middle mb-0">
                  <thead><tr><th>Date</th><th>Medicine</th><th>Batch</th><th class="text-end">Qty</th><th>From → To</th></tr></thead>
                  <tbody id="trfHistBody"></tbody>
                </table></div>
              </div>
            </div>
          </div>`;

        const medSel = $('#trfMed');
        medSel.innerHTML = D.medicines.map((m) => `<option value="${m.id}">${MF.esc(m.name)}</option>`).join('');
        fillBatchSelect($('#trfBatch'), medSel.value);
        medSel.addEventListener('change', () => fillBatchSelect($('#trfBatch'), medSel.value));
        if (state.counters[1]) $('#trfTo').value = state.counters[1].id;

        $('#trfSubmit').addEventListener('click', async () => {
          if (!MF.Api.live) { MF.toast('Counter transfers need the live backend — this is demo mode.', 'info', 'Demo mode'); return; }
          const medicineId = +medSel.value, batchId = +$('#trfBatch').value, qty = +$('#trfQty').value || 0;
          const fromCounterId = +$('#trfFrom').value, toCounterId = +$('#trfTo').value;
          if (!batchId) { MF.toast('No sellable batch for this medicine.', 'warn', 'Transfer'); return; }
          if (!qty) { MF.toast('Enter a quantity greater than zero.', 'warn', 'Transfer'); return; }
          if (fromCounterId === toCounterId) { MF.toast('Pick two different counters.', 'warn', 'Transfer'); return; }
          const btn = $('#trfSubmit'); btn.disabled = true;
          try {
            await MF.Api.post('stock-transfer.php', { medicineId, batchId, qty, fromCounterId, toCounterId, notes: $('#trfNotes').value });
            MF.toast('Counter transfer saved.', 'success', 'Counter Transfer');
            await loadHistory();
            renderTransfer();
          } catch (err) {
            MF.toast(err.message || 'Could not save the transfer.', 'danger', 'Counter Transfer');
          } finally {
            btn.disabled = false;
          }
        });

        $('#trfHistBody').innerHTML = state.transfers.length ? state.transfers.map((t) => {
          const m = MF.med(t.medicine_id);
          const from = state.counters.find((c) => c.id == t.from_counter_id);
          const to = state.counters.find((c) => c.id == t.to_counter_id);
          return `<tr>
            <td class="num">${MF.fmtDate(t.created_at ? t.created_at.slice(0, 10) : '')}</td>
            <td>${MF.esc(m ? m.name : '#' + t.medicine_id)}</td>
            <td class="num">${MF.esc(t.batch_id)}</td>
            <td class="text-end num">${MF.num(t.qty)}</td>
            <td>${MF.esc(from ? from.name : t.from_counter_id)} → ${MF.esc(to ? to.name : t.to_counter_id)}</td>
          </tr>`;
        }).join('') : `<tr><td colspan="5"><div class="empty-state"><i class="bi bi-arrow-left-right"></i>${MF.Api.live ? 'No transfers recorded yet.' : 'Transfer history needs the live backend.'}</div></td></tr>`;
      }

      /* ---------------- Router ---------------- */
      function render() {
        renderTabs();
        if (state.tab === 'overview') renderOverview();
        else if (state.tab === 'low') renderLow();
        else if (state.tab === 'adjustment') renderAdjustment();
        else renderTransfer();
      }

      $$('#stockTabs [data-tab]').forEach((b) => b.addEventListener('click', () => {
        state.tab = b.dataset.tab;
        const url = new URL(location.href); url.searchParams.set('tab', state.tab);
        history.replaceState(null, '', url);
        render();
      }));

      $('#stExport').addEventListener('click', () => {
        const rows = (state.tab === 'low' ? lowStockRows() : allStockRows());
        MF.exportCSV('stock.csv', ['Medicine', 'Unit', 'MRP', 'Stock', 'Min. Stock'],
          rows.map(({ m, stock }) => [m.name, m.unit, m.mrp, stock, m.minStock || 0]));
      });

      /* ---------------- Load adjustment/transfer history + counters once ---------------- */
      async function loadHistory() {
        if (!MF.Api.live) { state.adjustments = []; state.transfers = []; return; }
        try {
          const [adjRes, trfRes] = await Promise.all([MF.Api.get('stock-adjustment.php'), MF.Api.get('stock-transfer.php')]);
          state.adjustments = adjRes.data || [];
          state.transfers = trfRes.data || [];
        } catch (err) {
          MF.toast('Could not load stock history.', 'danger', 'Stock');
        }
      }
      async function loadCounters() {
        if (!MF.Api.live) { state.counters = DEMO_COUNTERS; return; }
        try {
          const res = await MF.Api.get('stock-transfer.php?list=counters');
          state.counters = (res.data && res.data.length) ? res.data : DEMO_COUNTERS;
        } catch (err) {
          state.counters = DEMO_COUNTERS;
        }
      }

      (async function init() {
        const t = new URLSearchParams(location.search).get('tab');
        if (t && ['overview', 'low', 'adjustment', 'transfer'].includes(t)) state.tab = t;
        await Promise.all([loadHistory(), loadCounters()]);
        render();
      })();
    })();
    });
  </script>
</body>
</html>
