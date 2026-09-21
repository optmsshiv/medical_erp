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
  <title>Customers · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="customers">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-people me-2 text-success"></i>Customers</h1>
            <p class="page-sub" id="cuCount"></p>
          </div>
          <div class="ms-auto d-flex gap-2">
            <button class="btn btn-mf" id="cuAddBtn"><i class="bi bi-person-plus me-1"></i>Add Customer</button>
          </div>
        </div>

        <div class="row g-3 mb-3" id="cuKpis"></div>

        <div class="card-mf p-3 mb-3">
          <div class="row g-2">
            <div class="col-md-6">
              <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span>
              <input class="form-control" id="cuSearch" placeholder="Search name or phone…"></div>
            </div>
            <div class="col-md-4">
              <select class="form-select" id="cuDue">
                <option value="">All Dues</option>
                <option value="due">Has Due</option>
                <option value="clear">Dues Cleared</option>
              </select>
            </div>
          </div>
        </div>

        <div class="card-mf">
          <div class="table-scroll" style="max-height:none">
            <table class="table table-mf">
              <thead>
                <tr>
                  <th>Customer Name</th><th>Phone</th>
                  <th class="text-end">Total Sales</th><th class="text-end">Paid</th><th class="text-end">Due</th>
                  <th>Last Purchase</th><th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="cuBody"></tbody>
            </table>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Add customer modal -->
  <div class="modal fade" id="cuAddModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Add Customer</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Customer Name <span class="req">*</span></label><input class="form-control" id="cuName"></div>
          <div class="mb-2"><label class="form-label">Phone</label><input class="form-control" id="cuPhone" placeholder="98xxx xxxxx"></div>
          <div><label class="form-label">Address</label><textarea class="form-control" id="cuAddr" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="cuAddSave"><i class="bi bi-check2 me-1"></i>Save Customer</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Profile modal -->
  <div class="modal fade" id="cuProfileModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="cuProfileTitle"></h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <ul class="nav nav-pills-mf mb-3" id="cuProfileTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#cuTabOverview">Overview</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#cuTabSales">Sales History</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#cuTabPayments">Payments</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#cuTabDues">Dues</button></li>
          </ul>
          <div class="tab-content" id="cuProfileBody"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-mf" id="cuReceiveBtn"><i class="bi bi-cash-coin me-1"></i>Receive Payment</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Receive payment modal -->
  <div class="modal fade" id="cuPayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-mf-sm">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Receive Payment</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="alert alert-light border py-2 small" id="cuPayDueInfo"></div>
          <div class="mb-2"><label class="form-label">Amount (₹) <span class="req">*</span></label><input type="number" class="form-control" id="cuPayAmt" min="1"></div>
          <div><label class="form-label">Mode</label>
            <select class="form-select" id="cuPayMode"><option value="Cash">Cash</option><option value="UPI">UPI</option><option value="Bank">Bank Transfer</option><option value="Cheque">Cheque</option></select></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="cuPaySave">Record Payment</button>
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

      function list() { return D.customers.filter((c) => c.name !== 'Walk-in Customer'); }

      function filtered() {
        const q = $('#cuSearch').value.toLowerCase();
        const due = $('#cuDue').value;
        return list().filter((c) => {
          if (q && !(c.name + (c.phone || '')).toLowerCase().includes(q)) return false;
          if (due === 'due' && c.due <= 0) return false;
          if (due === 'clear' && c.due > 0) return false;
          return true;
        });
      }

      function renderKpis() {
        const l = list();
        const totalDue = l.reduce((s, c) => s + c.due, 0);
        const withDue = l.filter((c) => c.due > 0).length;
        $('#cuKpis').innerHTML = [
          ['Total Customers', l.length, 'primary', 'people'],
          ['Lifetime Sales', MF.fmt(l.reduce((s, c) => s + c.totalSales, 0)), 'success', 'graph-up-arrow'],
          ['Outstanding Dues', MF.fmt(totalDue), 'danger', 'cash-stack'],
          ['Customers with Due', withDue, 'warning', 'exclamation-triangle']
        ].map(([lbl, v, tone, icon]) => `
          <div class="col-6 col-xl-3"><div class="card-mf kpi-card h-100">
            <div class="kpi-icon tone-${tone}"><i class="bi bi-${icon}"></i></div>
            <div><div class="kpi-label">${lbl}</div><div class="kpi-value num">${v}</div></div>
          </div></div>`).join('');
      }

      function render() {
        const l = filtered();
        $('#cuCount').textContent = `${list().length} customer(s)`;
        $('#cuBody').innerHTML = l.map((c) => `
          <tr>
            <td><div class="td-title">${MF.esc(c.name)}</div><div class="td-sub">${MF.esc(c.address || '')}</div></td>
            <td class="num">${MF.esc(c.phone || '—')}</td>
            <td class="text-end num">${MF.fmt(c.totalSales)}</td>
            <td class="text-end num text-success">${MF.fmt(c.paid)}</td>
            <td class="text-end num fw-semibold ${c.due ? 'text-danger' : ''}">${MF.fmt(c.due)}</td>
            <td class="num text-2">${c.lastPurchase ? MF.fmtDate(c.lastPurchase) : '—'}</td>
            <td class="text-end row-actions">
              <button class="btn btn-sm btn-mf-soft" data-view="${c.id}">Profile</button>
            </td>
          </tr>`).join('') || `<tr><td colspan="7"><div class="empty-state"><i class="bi bi-people"></i>No customers match the filters.</div></td></tr>`;
        $('#cuBody').querySelectorAll('[data-view]').forEach((b) => b.addEventListener('click', () => openProfile(b.dataset.view)));
      }

      async function openProfile(id) {
        current = MF.cust(Number(id));
        const c = current;
        $('#cuProfileTitle').textContent = c.name;
        $('#cuProfileBody').innerHTML = `<div class="text-center text-2 p-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>`;
        new bootstrap.Modal($('#cuProfileModal')).show();

        const ledger = await MF.Api.get('party-ledger.php?type=customer&id=' + c.id);
        const { invoices, payments } = ledger.data ?? ledger; // tolerate either shape

        $('#cuProfileBody').innerHTML = `
          <div class="tab-pane fade show active" id="cuTabOverview">
            <div class="row g-3 mb-3">
              ${[['Phone', c.phone || '—'], ['Address', c.address || '—'],
                 ['Total Sales', MF.fmt(c.totalSales)], ['Received', MF.fmt(c.paid)],
                 ['Outstanding Due', MF.fmt(c.due)], ['Last Purchase', c.lastPurchase ? MF.fmtDate(c.lastPurchase) : '—']].map(([k, v]) =>
                `<div class="col-md-4 col-6"><div class="kpi-label">${k}</div><div class="fw-semibold num">${v}</div></div>`).join('')}
            </div>
            ${c.due > 0 ? `<div class="alert alert-light border d-flex align-items-center gap-2 small mb-0">
              <i class="bi bi-exclamation-triangle text-warning"></i>
              <span>Outstanding <strong>${MF.fmt(c.due)}</strong> — use "Receive Payment" to post a receipt.</span></div>` : ''}
          </div>
          <div class="tab-pane fade" id="cuTabSales">
            ${invoices.length ? `<table class="table table-mf border rounded"><thead><tr><th>Invoice</th><th>Date</th><th class="text-end">Amount</th><th>Payment</th><th>Status</th></tr></thead><tbody>
              ${invoices.map((i) => `<tr><td class="num td-title">${i.no}</td><td class="num">${MF.fmtDate(i.date)}</td><td class="text-end num">${MF.fmt(i.amount)}</td><td>${i.mode}</td><td>${MF.statusBadge(i.status)}</td></tr>`).join('')}
            </tbody></table>` : `<div class="empty-state"><i class="bi bi-receipt"></i>No sales recorded yet.</div>`}
          </div>
          <div class="tab-pane fade" id="cuTabPayments">
            ${payments.length ? `<table class="table table-mf border rounded"><thead><tr><th>Date</th><th>Mode</th><th>Note</th><th class="text-end">Amount</th></tr></thead><tbody>
              ${payments.map((p) => `<tr><td class="num">${MF.fmtDate(p.date)}</td><td>${p.mode}</td><td class="text-2">${MF.esc(p.note || '—')}</td><td class="text-end num">${MF.fmt(p.amount)}</td></tr>`).join('')}
            </tbody></table>` : `<div class="empty-state"><i class="bi bi-cash-coin"></i>No payments recorded yet.</div>`}
          </div>
          <div class="tab-pane fade" id="cuTabDues">
            ${invoices.filter((i) => i.due > 0).length ? `<table class="table table-mf border rounded"><thead><tr><th>Invoice</th><th>Date</th><th class="text-end">Total</th><th class="text-end">Due</th></tr></thead><tbody>
              ${invoices.filter((i) => i.due > 0).map((i) => `<tr><td class="num td-title">${i.no}</td><td class="num">${MF.fmtDate(i.date)}</td><td class="text-end num">${MF.fmt(i.amount)}</td><td class="text-end num text-danger fw-semibold">${MF.fmt(i.due)}</td></tr>`).join('')}
            </tbody></table>` : `<div class="empty-state"><i class="bi bi-check-circle"></i>No outstanding dues. Account is clear.</div>`}
          </div>`;
      }

      $('#cuReceiveBtn').addEventListener('click', () => {
        if (!current) return;
        if (current.due <= 0) { MF.toast(current.name + ' has no outstanding dues.', 'info', 'No dues'); return; }
        $('#cuPayDueInfo').innerHTML = `<i class="bi bi-info-circle me-1"></i>${MF.esc(current.name)} owes <strong>${MF.fmt(current.due)}</strong>`;
        $('#cuPayAmt').value = current.due;
        new bootstrap.Modal($('#cuPayModal')).show();
      });
      $('#cuPaySave').addEventListener('click', async () => {
        const amt = parseFloat($('#cuPayAmt').value) || 0;
        if (amt <= 0) { MF.toast('Enter a valid amount.', 'warn'); return; }
        try {
          await MF.Api.post('payments.php', { partyType: 'customer', partyId: current.id, amount: amt, mode: $('#cuPayMode').value });
          bootstrap.Modal.getInstance($('#cuPayModal')).hide();
          bootstrap.Modal.getInstance($('#cuProfileModal')).hide();
          MF.toast(`${MF.fmt(amt)} received from ${current.name}.`, 'success', 'Payment recorded');
          await MF.rehydrate();
          renderKpis(); render();
        } catch (err) {
          MF.toast(err.message || 'Could not record payment.', 'danger');
        }
      });

      $('#cuAddBtn').addEventListener('click', () => {
        ['cuName', 'cuPhone', 'cuAddr'].forEach((id) => $('#' + id).value = '');
        new bootstrap.Modal($('#cuAddModal')).show();
      });
      $('#cuAddSave').addEventListener('click', async () => {
        const name = $('#cuName').value.trim();
        if (!name) { MF.toast('Customer name is required.', 'err', 'Validation'); return; }
        try {
          await MF.Api.post('customers.php', { name, phone: $('#cuPhone').value.trim(), address: $('#cuAddr').value.trim() });
          bootstrap.Modal.getInstance($('#cuAddModal')).hide();
          MF.toast(name + ' added to customer master.', 'success', 'Customer created');
          await MF.rehydrate();
          renderKpis(); render();
        } catch (err) {
          MF.toast(err.message || 'Could not add customer.', 'danger');
        }
      });

      ['cuSearch', 'cuDue'].forEach((id) => $('#' + id).addEventListener('input', render));

      renderKpis(); render();
    })();
    });
  </script>
</body>
</html>
