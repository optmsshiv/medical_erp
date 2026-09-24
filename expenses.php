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
  <title>Expenses · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="expenses">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-cash-coin me-2 text-success"></i>Expenses</h1>
            <p class="page-sub">Track rent, salaries, utilities and other running costs</p>
          </div>
          <div class="ms-auto d-flex align-items-center gap-2">
            <input type="date" class="form-control form-control-sm" id="exFrom" style="width:150px">
            <span class="text-2 small">to</span>
            <input type="date" class="form-control form-control-sm" id="exTo" style="width:150px">
            <button class="btn btn-sm btn-light-mf" id="exApply"><i class="bi bi-funnel me-1"></i>Apply</button>
            <button class="btn btn-sm btn-mf" id="exAddBtn"><i class="bi bi-plus-lg me-1"></i>Add Expense</button>
          </div>
        </div>

        <div class="row g-3 mb-3" id="exKpis"></div>

        <div class="row g-3">
          <div class="col-lg-8">
            <div class="card-mf">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-list-ul"></i>Expense Log</h2></div>
              <div class="table-scroll" style="max-height:none">
                <table class="table table-mf">
                  <thead><tr><th>Date</th><th>Category</th><th>Note</th><th>Mode</th><th class="text-end">Amount</th><th class="text-end">Actions</th></tr></thead>
                  <tbody id="exBody"></tbody>
                  <tfoot id="exFoot"></tfoot>
                </table>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="card-mf">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-pie-chart"></i>By Category</h2></div>
              <div class="p-3" id="exByCategory"></div>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <div class="modal fade" id="exModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Add Expense</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">Category <span class="req">*</span></label><select class="form-select" id="exCategory"></select></div>
            <div class="col-6"><label class="form-label">Amount (₹) <span class="req">*</span></label><input type="number" class="form-control" id="exAmount" min="1"></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">Date</label><input type="date" class="form-control" id="exDate"></div>
            <div class="col-6"><label class="form-label">Payment Mode</label>
              <select class="form-select" id="exMode"><option>Cash</option><option>Bank</option><option>UPI</option><option>Cheque</option></select></div>
          </div>
          <div><label class="form-label">Note</label><input class="form-control" id="exNote" placeholder="Optional"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="exSave">Save Expense</button>
        </div>
      </div>
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
      const MF = window.MF;
      const $ = (s) => document.querySelector(s);
      let rows = [], categories = [];

      $('#exFrom').value = MF.today().slice(0, 8) + '01';
      $('#exTo').value = MF.today();
      $('#exDate').value = MF.today();

      async function load() {
        const res = await MF.Api.get(`expenses.php?from=${$('#exFrom').value}&to=${$('#exTo').value}`);
        rows = res.data; categories = res.categories;
        $('#exCategory').innerHTML = categories.map((c) => `<option value="${c.id}">${MF.esc(c.name)}</option>`).join('');
        render();
      }

      function render() {
        const total = rows.reduce((s, e) => s + e.amount, 0);
        $('#exKpis').innerHTML = [
          ['Total Expenses (period)', MF.fmt(total), 'danger', 'cash-stack'],
          ['Entries', rows.length, 'primary', 'list-ul'],
          ['Average / Entry', rows.length ? MF.fmt(total / rows.length) : MF.fmt(0), 'info', 'calculator'],
        ].map(([l, v, tone, icon]) => `
          <div class="col-md-4"><div class="card-mf kpi-card h-100"><div class="kpi-icon tone-${tone}"><i class="bi bi-${icon}"></i></div>
            <div><div class="kpi-label">${l}</div><div class="kpi-value num">${v}</div></div></div></div>`).join('');

        $('#exBody').innerHTML = rows.map((e) => `
          <tr>
            <td class="num">${MF.fmtDate(e.date)}</td>
            <td class="td-title">${MF.esc(e.category)}</td>
            <td class="text-2">${MF.esc(e.note || '—')}</td>
            <td>${e.mode}</td>
            <td class="text-end num fw-semibold">${MF.fmt(e.amount)}</td>
            <td class="text-end row-actions"><button class="btn btn-icon btn-light-mf text-danger" data-del="${e.id}"><i class="bi bi-trash"></i></button></td>
          </tr>`).join('') || `<tr><td colspan="6"><div class="empty-state"><i class="bi bi-cash-coin"></i>No expenses in this period.</div></td></tr>`;
        $('#exFoot').innerHTML = rows.length ? `<tr><td colspan="4">Total</td><td class="text-end num">${MF.fmt(total)}</td><td></td></tr>` : '';
        $('#exBody').querySelectorAll('[data-del]').forEach((b) => b.addEventListener('click', () => remove(b.dataset.del)));

        const byCat = {};
        rows.forEach((e) => { byCat[e.category] = (byCat[e.category] || 0) + e.amount; });
        const entries = Object.entries(byCat).sort((a, b) => b[1] - a[1]);
        $('#exByCategory').innerHTML = entries.length ? entries.map(([cat, amt]) => `
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small">${MF.esc(cat)}</span><span class="num fw-semibold">${MF.fmt(amt)}</span>
          </div>
          <div class="progress mb-3" style="height:6px"><div class="progress-bar bg-success" style="width:${total ? (amt / total * 100) : 0}%"></div></div>`).join('')
          : `<div class="empty-state py-3"><i class="bi bi-pie-chart"></i>No data.</div>`;
      }

      async function remove(id) {
        const ok = await MF.confirm({ title: 'Delete this expense entry?', confirmText: 'Delete', tone: 'danger' });
        if (!ok) return;
        try {
          await MF.Api.del('expenses.php?id=' + id);
          MF.toast('Expense removed.', 'success');
          load();
        } catch (err) {
          MF.toast(err.message || 'Could not remove expense.', 'danger');
        }
      }

      $('#exAddBtn').addEventListener('click', () => {
        $('#exAmount').value = ''; $('#exNote').value = ''; $('#exDate').value = MF.today();
        new bootstrap.Modal($('#exModal')).show();
      });
      $('#exSave').addEventListener('click', async () => {
        const amount = parseFloat($('#exAmount').value) || 0;
        if (amount <= 0) { MF.toast('Enter a valid amount.', 'warn'); return; }
        try {
          await MF.Api.post('expenses.php', {
            categoryId: $('#exCategory').value, amount, date: $('#exDate').value,
            mode: $('#exMode').value, note: $('#exNote').value.trim(),
          });
          bootstrap.Modal.getInstance($('#exModal')).hide();
          MF.toast('Expense recorded.', 'success');
          load();
        } catch (err) {
          MF.toast(err.message || 'Could not save expense.', 'danger');
        }
      });

      $('#exApply').addEventListener('click', load);
      await load();
    })();
    });
  </script>
</body>
</html>
