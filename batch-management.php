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
  <title>Batch Management · MediFlow ERP</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="batch-management">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-collection me-2 text-success"></i>Batch Management</h1>
            <p class="page-sub" id="bmCount"></p>
          </div>
          <div class="ms-auto d-flex gap-2">
            <button class="btn btn-light-mf" id="bmExport"><i class="bi bi-download me-1"></i>Export CSV</button>
            <a class="btn btn-mf" href="expiry-management.php"><i class="bi bi-calendar2-x me-1"></i>Expiry Center</a>
          </div>
        </div>

        <!-- Summary chips -->
        <div class="row g-3 mb-3" id="bmKpis"></div>

        <!-- Filters -->
        <div class="card-mf p-3 mb-3">
          <div class="row g-2">
            <div class="col-md-3">
              <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span>
              <input class="form-control" id="bmSearch" placeholder="Medicine or batch no…"></div>
            </div>
            <div class="col-6 col-md-3"><select class="form-select" id="bmMfg"><option value="">All Manufacturers</option></select></div>
            <div class="col-6 col-md-2">
              <select class="form-select" id="bmStatus">
                <option value="">All Status</option>
                <option>Active</option><option>Low Stock</option><option>Near Expiry</option><option>Expired</option>
              </select>
            </div>
            <div class="col-6 col-md-2"><input type="month" class="form-control" id="bmExpMonth" title="Filter by expiry month"></div>
            <div class="col-6 col-md-2 d-flex gap-2">
              <select class="form-select" id="bmSort">
                <option value="expiry">Sort: Expiry ↑</option>
                <option value="qty">Sort: Qty ↓</option>
                <option value="value">Sort: Value ↓</option>
              </select>
            </div>
          </div>
        </div>

        <div class="card-mf">
          <div class="table-scroll" style="max-height:none">
            <table class="table table-mf">
              <thead>
                <tr>
                  <th>Medicine</th><th>Batch No</th><th>Manufacturer</th><th>Purchase Date</th><th>Expiry Date</th>
                  <th class="text-end">Purchase Rate</th><th class="text-end">MRP</th>
                  <th class="text-end">Current Qty</th><th class="text-end">Reserved</th><th>Status</th><th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="bmBody"></tbody>
            </table>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2 p-3 border-top">
            <span class="text-2 small" id="bmPageInfo"></span>
            <div class="ms-auto"><ul class="pagination pagination-sm mb-0" id="bmPager"></ul></div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Batch detail modal -->
  <div class="modal fade" id="bmDetailModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Batch Details</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="bmDetailBody"></div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-mf-soft" id="bmDetailReturn"><i class="bi bi-box-arrow-in-left me-1"></i>Create Return</button>
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
      const MF = window.MF, D = window.MF_DATA;
      const $ = (s) => document.querySelector(s);
      const state = { search: '', mfg: '', status: '', expMonth: '', sort: 'expiry', page: 1, per: 12 };

      D.manufacturers.forEach((m) => $('#bmMfg').insertAdjacentHTML('beforeend', `<option>${m}</option>`));

      function statusOf(b) { return MF.batchStatus(b); }

      function filtered() {
        const q = state.search.toLowerCase();
        let list = D.batches.filter((b) => {
          const m = MF.med(b.medId);
          if (q && !(m.name.toLowerCase().includes(q) || b.batchNo.toLowerCase().includes(q))) return false;
          if (state.mfg && m.manufacturer !== state.mfg) return false;
          if (state.status && statusOf(b) !== state.status) return false;
          if (state.expMonth && !b.expiry.startsWith(state.expMonth)) return false;
          return true;
        });
        if (state.sort === 'expiry') list.sort((a, b) => a.expiry.localeCompare(b.expiry));
        if (state.sort === 'qty') list.sort((a, b) => b.qty - a.qty);
        if (state.sort === 'value') list.sort((a, b) => b.qty * b.purchaseRate - a.qty * a.purchaseRate);
        return list;
      }

      function renderKpis() {
        const counts = { Active: 0, 'Low Stock': 0, 'Near Expiry': 0, Expired: 0 };
        D.batches.forEach((b) => counts[statusOf(b)]++);
        $('#bmKpis').innerHTML = [
          ['Active', counts.Active, 'success', 'check-circle'],
          ['Low Stock', counts['Low Stock'], 'warning', 'exclamation-triangle'],
          ['Near Expiry', counts['Near Expiry'], 'warning', 'calendar-x'],
          ['Expired', counts.Expired, 'danger', 'x-circle']
        ].map(([l, v, tone, icon]) => `
          <div class="col-6 col-xl-3"><div class="card-mf kpi-card">
            <div class="kpi-icon tone-${tone}"><i class="bi bi-${icon}"></i></div>
            <div><div class="kpi-label">${l} Batches</div><div class="kpi-value num">${v}</div></div>
          </div></div>`).join('');
      }

      function render() {
        const list = filtered();
        const pages = Math.max(1, Math.ceil(list.length / state.per));
        state.page = Math.min(state.page, pages);
        const slice = list.slice((state.page - 1) * state.per, state.page * state.per);
        $('#bmCount').textContent = `${D.batches.length} batches across ${D.medicines.length} medicines · FEFO selling enabled`;
        $('#bmBody').innerHTML = slice.map((b) => {
          const m = MF.med(b.medId);
          return `<tr>
            <td><div class="td-title">${MF.esc(m.name)}</div><div class="td-sub">${MF.esc(m.composition)}</div></td>
            <td class="num td-title">${b.batchNo}</td>
            <td>${MF.esc(m.manufacturer)}</td>
            <td class="num">${MF.fmtDate(b.purchaseDate)}</td>
            <td class="num">${MF.fmtMonthYear(b.expiry)}</td>
            <td class="text-end num">${MF.fmt(b.purchaseRate, 2)}</td>
            <td class="text-end num">${MF.fmt(b.mrp, 2)}</td>
            <td class="text-end num fw-semibold">${b.qty}</td>
            <td class="text-end num text-2">${b.reserved}</td>
            <td>${MF.statusBadge(statusOf(b))}</td>
            <td class="text-end row-actions">
              <button class="btn btn-icon btn-light-mf" data-view="${b.id}" title="View"><i class="bi bi-eye"></i></button>
            </td>
          </tr>`;
        }).join('') || `<tr><td colspan="11"><div class="empty-state"><i class="bi bi-collection"></i>No batches match the filters.</div></td></tr>`;

        $('#bmPageInfo').textContent = `Showing ${slice.length ? (state.page - 1) * state.per + 1 : 0}–${(state.page - 1) * state.per + slice.length} of ${list.length} batches`;
        $('#bmPager').innerHTML = Array.from({ length: pages }, (_, i) =>
          `<li class="page-item ${i + 1 === state.page ? 'active' : ''}"><button class="page-link" data-pg="${i + 1}">${i + 1}</button></li>`).join('');
        $('#bmPager').querySelectorAll('[data-pg]').forEach((b) => b.addEventListener('click', () => { state.page = +b.dataset.pg; render(); }));
        $('#bmBody').querySelectorAll('[data-view]').forEach((b) => b.addEventListener('click', () => openDetail(b.dataset.view)));
      }

      let detailBatch = null;
      function openDetail(id) {
        const b = D.batches.find((x) => x.id === id); detailBatch = b;
        const m = MF.med(b.medId);
        $('#bmDetailBody').innerHTML = `
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="kpi-icon tone-primary"><i class="bi bi-collection"></i></div>
            <div>
              <h6 class="fw-bold mb-0 num">${b.batchNo}</h6>
              <div class="text-2 small">${MF.esc(m.name)} · ${MF.esc(m.manufacturer)}</div>
            </div>
            <div class="ms-auto">${MF.statusBadge(statusOf(b))}</div>
          </div>
          <div class="row g-3">
            ${[['Purchase Date', MF.fmtDate(b.purchaseDate)], ['Expiry Date', MF.fmtMonthYear(b.expiry)],
               ['Days Remaining', MF.daysTo(b.expiry) < 0 ? 'Expired ' + Math.abs(MF.daysTo(b.expiry)) + ' days ago' : MF.daysTo(b.expiry) + ' days'],
               ['Current Qty', b.qty + ' ' + m.unit], ['Reserved Qty', b.reserved], ['Purchase Rate', MF.fmt(b.purchaseRate, 2)],
               ['MRP', MF.fmt(b.mrp, 2)], ['Batch Value', MF.fmt(b.qty * b.purchaseRate)]].map(([k, v]) =>
              `<div class="col-6"><div class="kpi-label">${k}</div><div class="fw-semibold num">${v}</div></div>`).join('')}
          </div>`;
        new bootstrap.Modal($('#bmDetailModal')).show();
      }
      $('#bmDetailReturn').addEventListener('click', () => {
        bootstrap.Modal.getInstance($('#bmDetailModal')).hide();
        window.location.href = 'purchase-return.php';
      });

      ['bmSearch', 'bmMfg', 'bmStatus', 'bmExpMonth', 'bmSort'].forEach((id) =>
        $('#' + id).addEventListener('input', () => {
          state.search = $('#bmSearch').value; state.mfg = $('#bmMfg').value; state.status = $('#bmStatus').value;
          state.expMonth = $('#bmExpMonth').value; state.sort = $('#bmSort').value;
          state.page = 1; render();
        }));

      $('#bmExport').addEventListener('click', () => MF.exportCSV('batches.csv',
        ['Medicine', 'Batch', 'Manufacturer', 'Purchase Date', 'Expiry', 'Pur. Rate', 'MRP', 'Qty', 'Reserved', 'Status'],
        filtered().map((b) => { const m = MF.med(b.medId); return [m.name, b.batchNo, m.manufacturer, b.purchaseDate, b.expiry, b.purchaseRate, b.mrp, b.qty, b.reserved, statusOf(b)]; })));

      renderKpis(); render();
    })();
    });
  </script>
</body>
</html>
