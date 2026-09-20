<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php'; // redirects to /login.php if not logged in

$client = Tenant::current();
$user   = Auth::user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Retail POS · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="retail-pos">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-cart3 me-2 text-success"></i>Retail POS</h1>
            <p class="page-sub">Counter billing · FEFO batch picking · GST-inclusive MRP pricing</p>
          </div>
          <div class="ms-auto d-flex align-items-center gap-2">
            <span class="badge badge-soft-secondary"><i class="bi bi-person me-1"></i><?= htmlspecialchars($user['name']) ?></span>
            <button class="btn btn-light-mf position-relative" id="posHeldChip">
              <i class="bi bi-hourglass-split me-1"></i>Held Bills
              <span class="badge rounded-pill text-bg-danger position-absolute top-0 start-100 translate-middle" id="posHeldBadge" style="display:none">0</span>
            </button>
          </div>
        </div>

        <div class="pos-grid">
          <!-- LEFT: search -->
          <div class="card-mf p-3">
            <label class="form-label" for="posSearch">Search medicine</label>
            <div class="input-group mb-2">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input id="posSearch" class="form-control" placeholder="Medicine name, generic, composition, barcode or batch…" autocomplete="off">
            </div>
            <div id="posResults"></div>
          </div>

          <!-- RIGHT: current invoice -->
          <div class="card-mf">
            <div class="card-head">
              <h2 class="card-title"><i class="bi bi-receipt"></i>Current Invoice</h2>
              <div class="card-tools">
                <span class="rx-chip" id="posRxChip" style="display:none"><i class="bi bi-file-medical"></i>Rx verification needed</span>
              </div>
            </div>
            <div class="p-3">
              <div class="row g-2 mb-2">
                <div class="col-6">
                  <label class="form-label">Customer</label>
                  <div class="d-flex gap-2">
                    <select class="form-select" id="posCustomer"></select>
                    <button class="btn btn-light-mf" type="button" id="posAddCustomer" title="Add new customer"><i class="bi bi-plus-lg"></i></button>
                  </div>
                </div>
                <div class="col-6">
                  <label class="form-label">Prescribing Doctor</label>
                  <select class="form-select" id="posDoctor">
                    <option value="">— Walk-in / none —</option>
                  </select>
                </div>
              </div>

              <div id="posCartBody"></div>

              <div class="row g-2 align-items-end mt-2">
                <div class="col-6">
                  <label class="form-label">Bill-level discount (%)</label>
                  <input type="number" min="0" max="100" class="form-control" id="posGlobalDisc" value="0" placeholder="0">
                </div>
                <div class="col-6"><div id="posSummary"></div></div>
              </div>

              <div class="sr-group-label mt-3">Payment</div>
              <div class="row g-2 row-cols-5 mb-3">
                <div class="col pay-opt"><input type="radio" name="posPay" id="posPayCash" value="cash" checked><label for="posPayCash"><i class="bi bi-cash"></i>Cash</label></div>
                <div class="col pay-opt"><input type="radio" name="posPay" id="posPayUpi" value="upi"><label for="posPayUpi"><i class="bi bi-qr-code-scan"></i>UPI</label></div>
                <div class="col pay-opt"><input type="radio" name="posPay" id="posPayCard" value="card"><label for="posPayCard"><i class="bi bi-credit-card"></i>Card</label></div>
                <div class="col pay-opt"><input type="radio" name="posPay" id="posPayCredit" value="credit"><label for="posPayCredit"><i class="bi bi-journal-text"></i>Credit</label></div>
                <div class="col pay-opt"><input type="radio" name="posPay" id="posPaySplit" value="split"><label for="posPaySplit"><i class="bi bi-diagram-3"></i>Split</label></div>
              </div>

              <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-light-mf" id="posHold"><i class="bi bi-hourglass-split me-1"></i>Hold Bill</button>
                <button class="btn btn-light-mf" id="posDraft"><i class="bi bi-save me-1"></i>Save Draft</button>
                <button class="btn btn-light-mf" id="posPrint"><i class="bi bi-printer me-1"></i>Print Invoice</button>
                <button class="btn btn-light-mf text-danger ms-auto" id="posClearCart"><i class="bi bi-trash3 me-1"></i>Clear</button>
                <button class="btn btn-mf px-4" id="posComplete"><i class="bi bi-check2-circle me-1"></i>Complete Sale</button>
              </div>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Split payment modal -->
  <div class="modal fade" id="posSplitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-mf-sm">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-diagram-3 me-2 text-success"></i>Split Payment</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Cash amount (₹)</label>
            <input type="number" class="form-control" id="splitCash" value="0" min="0">
          </div>
          <div>
            <label class="form-label">UPI amount (₹)</label>
            <input type="number" class="form-control" id="splitUpi" value="0" min="0">
          </div>
          <p class="text-2 small mt-3 mb-0">Both amounts together must equal the Grand Total.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="posSplitApply">Apply Split</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Held bills modal -->
  <div class="modal fade" id="posHeldModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-hourglass-split me-2 text-success"></i>Held Bills</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="posHeldBody"></div>
      </div>
    </div>
  </div>

  <!-- Add Customer modal -->
  <div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Customer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Name <span class="req">*</span></label>
            <input class="form-control" id="pcName" placeholder="e.g. Ramesh Kumar">
          </div>
          <div class="mb-2">
            <label class="form-label">Phone</label>
            <input class="form-control" id="pcPhone">
          </div>
          <div class="mb-2">
            <label class="form-label">Address</label>
            <input class="form-control" id="pcAddress">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="pcSave">Save Customer</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script>window.MF_STORE = { name: <?= json_encode($client['name']) ?> };</script>
  <script src="assets/js/app.js"></script>
  <script src="assets/js/pos.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', async () => {
      await MF.boot();
      const D = window.MF_DATA;

      function renderCustomers() {
        const custSel = document.getElementById('posCustomer');
        custSel.innerHTML = D.customers.map((c) =>
          `<option value="${c.id}">${MF.esc(c.name)}${c.name === 'Walk-in Customer' ? ' (default)' : ''}</option>`).join('');
      }
      renderCustomers();

      const docSel = document.getElementById('posDoctor');
      D.doctors.forEach((d) => docSel.insertAdjacentHTML('beforeend',
        `<option value="${d.id}">${d.name} — ${d.specialty}</option>`));

      document.getElementById('posAddCustomer').addEventListener('click', () => {
        ['pcName', 'pcPhone', 'pcAddress'].forEach((id) => document.getElementById(id).value = '');
        new bootstrap.Modal('#addCustomerModal').show();
      });
      document.getElementById('pcSave').addEventListener('click', async () => {
        const name = document.getElementById('pcName').value.trim();
        if (!name) { MF.toast('Customer name is required.', 'warn'); return; }
        try {
          const res = await MF.Api.post('customers.php', {
            name,
            phone: document.getElementById('pcPhone').value.trim(),
            address: document.getElementById('pcAddress').value.trim(),
          });
          await MF.rehydrate();
          renderCustomers();
          document.getElementById('posCustomer').value = res.id;
          bootstrap.Modal.getInstance(document.getElementById('addCustomerModal')).hide();
          MF.toast('Customer added.', 'success');
        } catch (err) {
          MF.toast(err.message || 'Could not add customer.', 'danger');
        }
      });
    });
  </script>
</body>
</html>
