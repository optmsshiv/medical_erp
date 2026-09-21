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
  <title>Suppliers · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="suppliers">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-truck me-2 text-success"></i>Suppliers</h1>
            <p class="page-sub" id="suCount"></p>
          </div>
          <div class="ms-auto d-flex gap-2">
            <button class="btn btn-mf" id="suAddBtn"><i class="bi bi-plus-lg me-1"></i>Add Supplier</button>
          </div>
        </div>

        <div class="row g-3 mb-3" id="suKpis"></div>

        <div class="card-mf p-3 mb-3">
          <div class="row g-2">
            <div class="col-md-6">
              <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span>
              <input class="form-control" id="suSearch" placeholder="Search supplier, phone, GSTIN…"></div>
            </div>
            <div class="col-md-4">
              <select class="form-select" id="suDue">
                <option value="">All Payables</option>
                <option value="due">Outstanding Payable</option>
                <option value="clear">Fully Paid</option>
              </select>
            </div>
          </div>
        </div>

        <div class="card-mf">
          <div class="table-scroll" style="max-height:none">
            <table class="table table-mf">
              <thead>
                <tr>
                  <th>Supplier Name</th><th>Phone</th><th>GSTIN</th><th>Drug License</th>
                  <th class="text-end">Total Purchase</th><th class="text-end">Paid</th><th class="text-end">Due</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="suBody"></tbody>
            </table>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Add supplier modal -->
  <div class="modal fade" id="suAddModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Add Supplier</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Supplier Name <span class="req">*</span></label><input class="form-control" id="suName"></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">GSTIN</label><input class="form-control" id="suGstin"></div>
            <div class="col-6"><label class="form-label">Drug License No.</label><input class="form-control" id="suDl"></div>
          </div>
          <div class="mb-2"><label class="form-label">Phone</label><input class="form-control" id="suPhone"></div>
          <div><label class="form-label">Address</label><textarea class="form-control" id="suAddr" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="suAddSave"><i class="bi bi-check2 me-1"></i>Save Supplier</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Profile modal -->
  <div class="modal fade" id="suProfileModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="suProfileTitle"></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <ul class="nav nav-pills-mf mb-3">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#suTabOverview">Overview</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#suTabPurchases">Purchase History</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#suTabPayments">Payments</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#suTabOutstanding">Outstanding</button></li>
          </ul>
          <div class="tab-content" id="suProfileBody"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-mf" id="suPayBtn"><i class="bi bi-cash-coin me-1"></i>Make Payment</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Pay supplier modal -->
  <div class="modal fade" id="suPayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-mf-sm">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Supplier Payment</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="alert alert-light border py-2 small" id="suPayDueInfo"></div>
          <div class="mb-2"><label class="form-label">Amount (₹) <span class="req">*</span></label><input type="number" class="form-control" id="suPayAmt" min="1"></div>
          <div><label class="form-label">Mode</label>
            <select class="form-select" id="suPayMode"><option value="Bank">Bank Transfer</option><option value="Cash">Cash</option><option value="UPI">UPI</option><option value="Cheque">Cheque</option></select></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="suPaySave">Record Payment</button>
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
      let current = null;

      function filtered() {
        const q = $('#suSearch').value.toLowerCase(), due = $('#suDue').value;
        return D.suppliers.filter((s) => {
          if (q && !(s.name + (s.phone || '') + (s.gstin || '')).toLowerCase().includes(q)) return false;
          if (due === 'due' && s.due <= 0) return false;
          if (due === 'clear' && s.due > 0) return false;
          return true;
        });
      }

      function renderKpis() {
        $('#suKpis').innerHTML = [
          ['Total Suppliers', D.suppliers.length, 'primary', 'truck'],
          ['Lifetime Purchases', MF.fmt(D.suppliers.reduce((s, x) => s + x.totalPurchases, 0)), 'info', 'bag-check'],
          ['Outstanding Payables', MF.fmt(D.suppliers.reduce((s, x) => s + x.due, 0)), 'warning', 'wallet2'],
          ['Suppliers with Due', D.suppliers.filter((x) => x.due > 0).length, 'danger', 'exclamation-triangle']
        ].map(([l, v, tone, icon]) => `
          <div class="col-6 col-xl-3"><div class="card-mf kpi-card h-100">
            <div class="kpi-icon tone-${tone}"><i class="bi bi-${icon}"></i></div>
            <div><div class="kpi-label">${l}</div><div class="kpi-value num">${v}</div></div>
          </div></div>`).join('');
      }

      function render() {
        const list = filtered();
        $('#suCount').textContent = `${D.suppliers.length} supplier(s)`;
        $('#suBody').innerHTML = list.map((s) => `
          <tr>
            <td><div class="td-title">${MF.esc(s.name)}</div><div class="td-sub">${MF.esc(s.address || '')}</div></td>
            <td class="num">${MF.esc(s.phone || '—')}</td>
            <td class="num text-2">${MF.esc(s.gstin || '—')}</td>
            <td class="num text-2">${MF.esc(s.dlNo || '—')}</td>
            <td class="text-end num">${MF.fmt(s.totalPurchases)}</td>
            <td class="text-end num text-success">${MF.fmt(s.paid)}</td>
            <td class="text-end num fw-semibold ${s.due ? 'text-danger' : ''}">${MF.fmt(s.due)}</td>
            <td class="text-end row-actions"><button class="btn btn-sm btn-mf-soft" data-view="${s.id}">Profile</button></td>
          </tr>`).join('') || `<tr><td colspan="8"><div class="empty-state"><i class="bi bi-truck"></i>No suppliers match the filters.</div></td></tr>`;
        $('#suBody').querySelectorAll('[data-view]').forEach((b) => b.addEventListener('click', () => openProfile(b.dataset.view)));
      }

      async function openProfile(id) {
        current = MF.sup(Number(id));
        const s = current;
        $('#suProfileTitle').textContent = s.name;
        $('#suProfileBody').innerHTML = `<div class="text-center text-2 p-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>`;
        new bootstrap.Modal($('#suProfileModal')).show();

        const ledger = await MF.Api.get('party-ledger.php?type=supplier&id=' + s.id);
        const { invoices, payments } = ledger.data ?? ledger;

        $('#suProfileBody').innerHTML = `
          <div class="tab-pane fade show active" id="suTabOverview">
            <div class="row g-3 mb-3">
              ${[['Phone', s.phone || '—'], ['GSTIN', s.gstin || '—'], ['Drug License', s.dlNo || '—'], ['Address', s.address || '—'],
                 ['Total Purchases', MF.fmt(s.totalPurchases)], ['Paid', MF.fmt(s.paid)],
                 ['Payable Due', MF.fmt(s.due)], ['Last Purchase', s.lastPurchase ? MF.fmtDate(s.lastPurchase) : '—']].map(([k, v]) =>
                `<div class="col-md-3 col-6"><div class="kpi-label">${k}</div><div class="fw-semibold num">${v}</div></div>`).join('')}
            </div>
          </div>
          <div class="tab-pane fade" id="suTabPurchases">
            ${invoices.length ? `<table class="table table-mf border rounded"><thead><tr><th>Invoice</th><th>Date</th><th class="text-end">Amount</th><th>Status</th></tr></thead><tbody>
              ${invoices.map((i) => `<tr><td class="num td-title">${i.no}</td><td class="num">${MF.fmtDate(i.date)}</td><td class="text-end num">${MF.fmt(i.amount)}</td><td>${MF.statusBadge(i.status)}</td></tr>`).join('')}
            </tbody></table>` : `<div class="empty-state"><i class="bi bi-bag"></i>No purchases recorded yet.</div>`}
          </div>
          <div class="tab-pane fade" id="suTabPayments">
            ${payments.length ? `<table class="table table-mf border rounded"><thead><tr><th>Date</th><th>Mode</th><th>Note</th><th class="text-end">Amount</th></tr></thead><tbody>
              ${payments.map((p) => `<tr><td class="num">${MF.fmtDate(p.date)}</td><td>${p.mode}</td><td class="text-2">${MF.esc(p.note || '—')}</td><td class="text-end num">${MF.fmt(p.amount)}</td></tr>`).join('')}
            </tbody></table>` : `<div class="empty-state"><i class="bi bi-cash-coin"></i>No payments recorded yet.</div>`}
          </div>
          <div class="tab-pane fade" id="suTabOutstanding">
            ${s.due > 0 ? `<div class="alert alert-light border d-flex align-items-center gap-2 small">
              <i class="bi bi-wallet2 text-warning"></i>
              <span>Payable of <strong>${MF.fmt(s.due)}</strong> outstanding. Use "Make Payment" to settle.</span></div>` :
              `<div class="empty-state"><i class="bi bi-check-circle"></i>No outstanding payables. Account is settled.</div>`}
          </div>`;
      }

      $('#suPayBtn').addEventListener('click', () => {
        if (!current) return;
        if (current.due <= 0) { MF.toast(current.name + ' has no outstanding payables.', 'info', 'No dues'); return; }
        $('#suPayDueInfo').innerHTML = `<i class="bi bi-info-circle me-1"></i>Payable to <strong>${MF.esc(current.name)}</strong>: ${MF.fmt(current.due)}`;
        $('#suPayAmt').value = current.due;
        new bootstrap.Modal($('#suPayModal')).show();
      });
      $('#suPaySave').addEventListener('click', async () => {
        const amt = parseFloat($('#suPayAmt').value) || 0;
        if (amt <= 0) { MF.toast('Enter a valid amount.', 'warn'); return; }
        try {
          await MF.Api.post('payments.php', { partyType: 'supplier', partyId: current.id, amount: amt, mode: $('#suPayMode').value });
          bootstrap.Modal.getInstance($('#suPayModal')).hide();
          bootstrap.Modal.getInstance($('#suProfileModal')).hide();
          MF.toast(`${MF.fmt(amt)} paid to ${current.name}.`, 'success', 'Payment recorded');
          await MF.rehydrate();
          renderKpis(); render();
        } catch (err) {
          MF.toast(err.message || 'Could not record payment.', 'danger');
        }
      });

      $('#suAddBtn').addEventListener('click', () => {
        ['suName', 'suGstin', 'suDl', 'suPhone', 'suAddr'].forEach((id) => $('#' + id).value = '');
        new bootstrap.Modal($('#suAddModal')).show();
      });
      $('#suAddSave').addEventListener('click', async () => {
        const name = $('#suName').value.trim();
        if (!name) { MF.toast('Supplier name is required.', 'err', 'Validation'); return; }
        try {
          await MF.Api.post('suppliers.php', {
            name, gstin: $('#suGstin').value.trim(), dlNo: $('#suDl').value.trim(),
            phone: $('#suPhone').value.trim(), address: $('#suAddr').value.trim(),
          });
          bootstrap.Modal.getInstance($('#suAddModal')).hide();
          MF.toast(name + ' added to supplier master.', 'success', 'Supplier created');
          await MF.rehydrate();
          renderKpis(); render();
        } catch (err) {
          MF.toast(err.message || 'Could not add supplier.', 'danger');
        }
      });

      ['suSearch', 'suDue'].forEach((id) => $('#' + id).addEventListener('input', render));
      renderKpis(); render();
    })();
    });
  </script>
</body>
</html>
