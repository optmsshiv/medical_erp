<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php';

$client = Tenant::current();
$user   = Auth::user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stock Transfer · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="stock-transfer">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-arrow-left-right me-2 text-success"></i>Stock Transfer</h1>
            <p class="page-sub">Log stock moved between locations</p>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-lg-4">
            <div class="card-mf p-3">
              <h2 class="card-title mb-3"><i class="bi bi-truck me-1"></i>Record Transfer</h2>

              <label class="form-label">Medicine</label>
              <select class="form-select mb-2" id="stMed"><option value="">Select medicine…</option></select>

              <label class="form-label">Batch</label>
              <select class="form-select mb-2" id="stBatch" disabled><option value="">Select medicine first…</option></select>

              <div class="row g-2 mb-2">
                <div class="col-6">
                  <label class="form-label">From</label>
                  <select class="form-select" id="stFrom"></select>
                </div>
                <div class="col-6">
                  <label class="form-label">To</label>
                  <select class="form-select" id="stTo"></select>
                </div>
              </div>
              <button class="btn btn-light-mf btn-sm mb-2" id="stAddLoc"><i class="bi bi-plus-lg"></i> Add location</button>

              <label class="form-label">Quantity</label>
              <input type="number" min="1" class="form-control mb-2" id="stQty" placeholder="Quantity">

              <label class="form-label">Notes <span class="text-2">(optional)</span></label>
              <textarea class="form-control mb-3" id="stNotes" rows="2" placeholder="Any additional context…"></textarea>

              <button class="btn btn-mf w-100" id="stSave"><i class="bi bi-check2-circle me-1"></i>Save Transfer</button>
              <p class="text-2 small mt-2 mb-0">This logs the movement — it doesn't yet split stock per location, so total batch quantity is unchanged.</p>
            </div>
          </div>

          <div class="col-lg-8">
            <div class="card-mf">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-clock-history"></i>Recent Transfers</h2></div>
              <div class="table-responsive">
                <table class="table-mf">
                  <thead><tr><th>Date</th><th>Medicine</th><th>Batch</th><th>From</th><th>To</th><th class="text-end">Qty</th><th>Status</th><th>By</th><th></th></tr></thead>
                  <tbody id="stLogBody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Add Location modal -->
  <div class="modal fade" id="addLocationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-mf-sm">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Add Location</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <label class="form-label">Location name</label>
          <input class="form-control" id="locName" placeholder="e.g. Branch — Patna">
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="locSave">Save Location</button>
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
      const D = window.MF_DATA;
      const $ = (s) => document.querySelector(s);
      let locations = [];

      $('#stMed').innerHTML += D.medicines.slice().sort((a, b) => a.name.localeCompare(b.name))
        .map((m) => `<option value="${m.id}">${MF.esc(m.name)}</option>`).join('');

      $('#stMed').addEventListener('change', () => {
        const med = MF.med($('#stMed').value);
        const batches = med ? MF.batchesOf(med.id) : [];
        const sel = $('#stBatch');
        if (!batches.length) {
          sel.innerHTML = '<option value="">No batches for this medicine</option>';
          sel.disabled = true;
          return;
        }
        sel.disabled = false;
        sel.innerHTML = batches.map((b) =>
          `<option value="${b.id}">${b.batchNo} — Qty ${b.qty} · Exp ${MF.fmtMonthYear(b.expiry)}</option>`).join('');
      });

      async function loadLocations() {
        const res = await MF.Api.get('stock-transfers.php?action=locations');
        locations = res.data || [];
        const opts = locations.map((l) => `<option value="${l.id}">${MF.esc(l.name)}</option>`).join('');
        $('#stFrom').innerHTML = opts;
        $('#stTo').innerHTML = opts;
        if (locations.length > 1) $('#stTo').selectedIndex = 1;
      }

      $('#stAddLoc').addEventListener('click', () => {
        $('#locName').value = '';
        new bootstrap.Modal('#addLocationModal').show();
      });
      $('#locSave').addEventListener('click', async () => {
        const name = $('#locName').value.trim();
        if (!name) { MF.toast('Location name is required.', 'warn'); return; }
        try {
          await MF.Api.post('stock-transfers.php?action=locations', { name });
          await loadLocations();
          bootstrap.Modal.getInstance($('#addLocationModal')).hide();
          MF.toast('Location added.', 'success');
        } catch (err) {
          MF.toast(err.message || 'Could not add location.', 'danger');
        }
      });

      async function loadLog() {
        try {
          const res = await MF.Api.get('stock-transfers.php');
          const rows = res.data || [];
          $('#stLogBody').innerHTML = rows.length ? rows.map((r) => `
            <tr>
              <td class="num">${MF.fmtDate(r.date)}</td>
              <td>${MF.esc(r.medicine)}</td>
              <td class="num">${MF.esc(r.batch)}</td>
              <td>${MF.esc(r.from)}</td>
              <td>${MF.esc(r.to)}</td>
              <td class="text-end num">${r.qty}</td>
              <td>${MF.statusBadge ? MF.statusBadge(r.status === 'completed' ? 'In Stock' : 'Out of Stock') : MF.esc(r.status)}</td>
              <td class="text-2">${MF.esc(r.transferredBy || '—')}</td>
              <td class="text-end">${r.status === 'completed' ? `<button class="btn btn-icon btn-light-mf text-danger" data-cancel="${r.id}" title="Cancel"><i class="bi bi-x-lg"></i></button>` : ''}</td>
            </tr>`).join('') : `<tr><td colspan="9"><div class="empty-state"><i class="bi bi-arrow-left-right"></i>No transfers recorded yet.</div></td></tr>`;

          $('#stLogBody').querySelectorAll('[data-cancel]').forEach((btn) =>
            btn.addEventListener('click', async () => {
              try {
                await MF.Api.post('stock-transfers.php', { cancelId: btn.dataset.cancel });
                MF.toast('Transfer cancelled.', 'success');
                loadLog();
              } catch (err) {
                MF.toast(err.message || 'Could not cancel.', 'danger');
              }
            }));
        } catch (err) {
          MF.toast(err.message || 'Could not load transfers.', 'danger');
        }
      }

      $('#stSave').addEventListener('click', async () => {
        const medId = $('#stMed').value, batchId = $('#stBatch').value;
        const qty = +$('#stQty').value || 0;
        const fromLocationId = $('#stFrom').value, toLocationId = $('#stTo').value;

        if (!medId) { MF.toast('Select a medicine.', 'warn'); return; }
        if (!batchId) { MF.toast('Select a batch.', 'warn'); return; }
        if (qty <= 0) { MF.toast('Enter a quantity greater than zero.', 'warn'); return; }
        if (!fromLocationId || !toLocationId || fromLocationId === toLocationId) { MF.toast('Select two different locations.', 'warn'); return; }

        try {
          await MF.Api.post('stock-transfers.php', {
            medId, batchId, fromLocationId, toLocationId, qty, notes: $('#stNotes').value.trim(),
          });
          MF.toast('Transfer logged.', 'success');
          $('#stQty').value = ''; $('#stNotes').value = '';
          loadLog();
        } catch (err) {
          MF.toast(err.message || 'Could not save the transfer.', 'danger');
        }
      });

      await loadLocations();
      loadLog();
    });
  </script>
</body>
</html>
