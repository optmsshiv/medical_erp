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
  <title>Expiry Management · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="expiry-management">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-calendar2-x me-2 text-success"></i>Expiry Management</h1>
            <p class="page-sub">Expired stock quarantine · 30 / 60 / 90-day expiry radar · supplier returns</p>
          </div>
          <div class="ms-auto">
            <button class="btn btn-light-mf" id="emExport"><i class="bi bi-download me-1"></i>Export Report</button>
          </div>
        </div>

        <!-- KPIs -->
        <div class="row g-3 mb-3" id="emKpis"></div>

        <!-- Filter pills -->
        <div class="card-mf p-3 mb-3">
          <div class="mf-segment" id="emFilter">
            <button data-f="all" class="active">All At-Risk</button>
            <button data-f="expired">Expired</button>
            <button data-f="30">≤ 30 Days</button>
            <button data-f="60">≤ 60 Days</button>
            <button data-f="90">≤ 90 Days</button>
          </div>
        </div>

        <!-- Table -->
        <div class="card-mf">
          <div class="card-head">
            <h2 class="card-title"><i class="bi bi-list-ul"></i>Expiry Radar</h2>
            <div class="card-tools"><span class="badge badge-soft-secondary" id="emRowCount"></span></div>
          </div>
          <div class="table-scroll" style="max-height:none">
            <table class="table table-mf">
              <thead>
                <tr>
                  <th>Medicine</th><th>Batch</th><th>Expiry Date</th><th class="text-center">Days Remaining</th>
                  <th class="text-end">Qty</th><th class="text-end">Purchase Value</th><th class="text-end">MRP Value</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody id="emBody"></tbody>
            </table>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Return modal -->
  <div class="modal fade" id="emReturnModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-box-arrow-in-left me-2 text-success"></i>Create Supplier Return</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="emReturnBody"></div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="emReturnSave">Create Return Note</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Adjust modal -->
  <div class="modal fade" id="emAdjustModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-mf-sm">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-sliders me-2 text-success"></i>Stock Adjustment</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="emAdjustBody"></div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="emAdjustSave">Post Adjustment</button>
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
      let filter = 'all', currentBatch = null;

      const atRisk = () => D.batches.filter((b) => MF.daysTo(b.expiry) <= 90).sort((a, b) => a.expiry.localeCompare(b.expiry));

      function bucketValue(maxDays, minDays = -99999) {
        return D.batches.reduce((s, b) => {
          const d = MF.daysTo(b.expiry);
          return (d <= maxDays && d > minDays === false ? 0 : 0) + (d <= maxDays ? b.qty * b.purchaseRate : 0) - 0 + s;
        }, 0);
      }
      // cumulative purchase value of stock expiring within N days (incl. expired)
      const within = (days) => D.batches.filter((b) => MF.daysTo(b.expiry) <= days)
        .reduce((s, b) => s + b.qty * b.purchaseRate, 0);
      const expiredValue = D.batches.filter((b) => MF.daysTo(b.expiry) < 0)
        .reduce((s, b) => s + b.qty * b.purchaseRate, 0);

      function renderKpis() {
        $('#emKpis').innerHTML = [
          ['Expired Stock', expiredValue, 'danger', 'x-circle', 'Quarantine & return'],
          ['Expiring in 30 Days', within(30) - expiredValue, 'danger', 'calendar-x', 'Push sale or return'],
          ['Expiring in 60 Days', within(60) - within(30), 'warning', 'calendar2-week', 'Plan schemes'],
          ['Expiring in 90 Days', within(90) - within(60), 'info', 'calendar3', 'Monitor weekly']
        ].map(([l, v, tone, icon, hint]) => `
          <div class="col-6 col-xl-3"><div class="card-mf kpi-card h-100">
            <div class="kpi-icon tone-${tone}"><i class="bi bi-${icon}"></i></div>
            <div>
              <div class="kpi-label">${l}</div>
              <div class="kpi-value num">${MF.fmt(v)}</div>
              <div class="small-xs text-2">${hint}</div>
            </div>
          </div></div>`).join('');
      }

      function visibleRows() {
        return atRisk().filter((b) => {
          const d = MF.daysTo(b.expiry);
          if (filter === 'expired') return d < 0;
          if (filter === '30') return d >= 0 && d <= 30;
          if (filter === '60') return d > 30 && d <= 60;
          if (filter === '90') return d > 60 && d <= 90;
          return true;
        });
      }

      function render() {
        const rows = visibleRows();
        $('#emRowCount').textContent = rows.length + ' batches · purchase value ' + MF.fmt(rows.reduce((s, b) => s + b.qty * b.purchaseRate, 0));
        $('#emBody').innerHTML = rows.map((b) => {
          const m = MF.med(b.medId), d = MF.daysTo(b.expiry);
          const tone = d < 0 ? 'danger' : d <= 30 ? 'danger' : d <= 60 ? 'warning' : 'info';
          return `<tr>
            <td><div class="td-title">${MF.esc(m.name)}</div><div class="td-sub">${MF.esc(m.manufacturer)}</div></td>
            <td class="num td-title">${b.batchNo}</td>
            <td class="num">${MF.fmtDate(b.expiry)}</td>
            <td class="text-center"><span class="badge badge-soft-${tone}">${d < 0 ? 'Expired ' + Math.abs(d) + 'd ago' : d + ' days'}</span></td>
            <td class="text-end num fw-semibold">${b.qty}</td>
            <td class="text-end num">${MF.fmt(b.qty * b.purchaseRate)}</td>
            <td class="text-end num text-2">${MF.fmt(b.qty * b.mrp)}</td>
            <td class="text-end row-actions" style="white-space:nowrap">
              <button class="btn btn-sm btn-light-mf" data-a="view" data-id="${b.id}"><i class="bi bi-eye me-1"></i>View</button>
              <button class="btn btn-sm btn-mf-soft" data-a="return" data-id="${b.id}"><i class="bi bi-box-arrow-in-left me-1"></i>Return</button>
              <button class="btn btn-sm btn-light-mf" data-a="adjust" data-id="${b.id}"><i class="bi bi-sliders me-1"></i>Adjust</button>
            </td>
          </tr>`;
        }).join('') || `<tr><td colspan="8"><div class="empty-state"><i class="bi bi-emoji-smile"></i>No batches in this expiry window.</div></td></tr>`;
        $('#emBody').querySelectorAll('[data-a]').forEach((btn) => btn.addEventListener('click', () => {
          currentBatch = D.batches.find((x) => x.id === btn.dataset.id);
          if (btn.dataset.a === 'view') viewBatch();
          if (btn.dataset.a === 'return') openReturn();
          if (btn.dataset.a === 'adjust') openAdjust();
        }));
      }

      function viewBatch() {
        const b = currentBatch, m = MF.med(b.medId);
        MF.printHtml(`
          <h6 class="fw-bold mb-3">Batch Card — ${b.batchNo}</h6>
          <div class="row g-3 small">
            ${[['Medicine', m.name], ['Manufacturer', m.manufacturer], ['Expiry', MF.fmtDate(b.expiry)],
               ['Qty', b.qty + ' ' + m.unit], ['Purchase Rate', MF.fmt(b.purchaseRate, 2)], ['MRP', MF.fmt(b.mrp, 2)],
               ['Purchase Value', MF.fmt(b.qty * b.purchaseRate)], ['Status', MF.batchStatus(b)]].map(([k, v]) =>
              `<div class="col-6"><div class="text-2">${k}</div><div class="fw-semibold">${v}</div></div>`).join('')}
          </div>`);
      }

      function openReturn() {
        window.location.href = 'purchase-return.php';
      }

      function openAdjust() {
        const b = currentBatch, m = MF.med(b.medId);
        $('#emAdjustBody').innerHTML = `
          <div class="mb-2"><label class="form-label">Batch</label><input class="form-control" value="${MF.esc(m.name)} · ${b.batchNo} · Qty ${b.qty}" readonly></div>
          <div class="mb-2"><label class="form-label">Adjusted Qty</label><input type="number" class="form-control" id="emAdjQty" value="${b.qty}" min="0" max="${b.qty}"></div>
          <div><label class="form-label">Reason</label>
            <select class="form-select" id="emAdjReason">
              <option>Expired — written off</option><option>Damaged</option><option>Physical count mismatch</option><option>Other</option>
            </select></div>`;
        new bootstrap.Modal($('#emAdjustModal')).show();
      }
      $('#emAdjustSave').addEventListener('click', async () => {
        const qty = Math.max(0, parseInt($('#emAdjQty').value) || 0);
        try {
          await MF.Api.post('batch-adjust.php', {
            batchId: currentBatch.id, newQty: qty, reason: $('#emAdjReason').value,
          });
          bootstrap.Modal.getInstance($('#emAdjustModal')).hide();
          MF.toast(`Batch ${currentBatch.batchNo} adjusted to ${qty} units (${$('#emAdjReason').value}).`, 'success', 'Adjustment posted');
          await MF.rehydrate();
          renderKpis(); render();
        } catch (err) {
          MF.toast(err.message || 'Could not save the adjustment.', 'danger');
        }
      });

      $('#emFilter').querySelectorAll('button').forEach((b) => b.addEventListener('click', () => {
        $('#emFilter').querySelectorAll('button').forEach((x) => x.classList.remove('active'));
        b.classList.add('active'); filter = b.dataset.f; render();
      }));

      $('#emExport').addEventListener('click', () => MF.exportCSV('expiry-report.csv',
        ['Medicine', 'Batch', 'Expiry', 'Days', 'Qty', 'Purchase Value', 'MRP Value'],
        atRisk().map((b) => { const m = MF.med(b.medId); return [m.name, b.batchNo, b.expiry, MF.daysTo(b.expiry), b.qty, Math.round(b.qty * b.purchaseRate), Math.round(b.qty * b.mrp)]; })));

      renderKpis(); render();
    })();
    });
  </script>
</body>
</html>
