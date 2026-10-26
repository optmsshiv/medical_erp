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
  <title>Settings · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .mf-avatar { overflow:hidden; }
    .mf-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
    .st-grp td { background:#F6F8FA; font-weight:700; font-size:.7rem; text-transform:uppercase; letter-spacing:.07em; color:#64748B; }
    .perm-check { transform:scale(1.15); cursor:pointer; accent-color:#176B5B; }
    .perm-check:disabled { cursor:not-allowed; opacity:.55; }
    .avatar-ring { position:relative; display:inline-block; }
    .avatar-cam { position:absolute; right:-2px; bottom:-2px; width:24px; height:24px; border-radius:50%; background:#fff; border:1px solid #E2E7EF; color:#176B5B; font-size:.62rem; display:flex; align-items:center; justify-content:center; cursor:pointer; }
    .avatar-cam:hover { background:#E9F5F1; }
  </style>
</head>
<body data-page="settings">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-gear me-2 text-success"></i>Administration &amp; Settings</h1>
            <p class="page-sub">Store profile · invoice &amp; tax configuration · users, roles and audit trail</p>
          </div>
        </div>

        <ul class="nav nav-pills-mf mb-3" id="setTabs">
          <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#st-store" data-tab="store"><i class="bi bi-shop"></i>Store Settings</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#st-invoice" data-tab="invoice"><i class="bi bi-file-earmark-sliders"></i>Invoice Settings</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#st-tax" data-tab="tax"><i class="bi bi-calculator"></i>Tax Settings</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#st-users" data-tab="users"><i class="bi bi-person-badge"></i>Users</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#st-roles" data-tab="roles"><i class="bi bi-shield-lock"></i>Roles &amp; Permissions</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#st-audit" data-tab="audit"><i class="bi bi-journal-check"></i>Audit Logs</button></li>
        </ul>

        <div class="tab-content">

          <!-- STORE -->
          <div class="tab-pane fade show active" id="st-store">
            <div class="card-mf" style="max-width:860px">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-shop"></i>Store Profile</h2></div>
              <div class="p-3">
                <div class="row g-3">
                  <div class="col-md-8"><label class="form-label">Store Name</label><input class="form-control" id="setStoreName"></div>
                  <div class="col-md-4"><label class="form-label">Store Code</label><input class="form-control" id="setStoreCode" readonly></div>
                  <div class="col-md-8"><label class="form-label">Address</label><input class="form-control" id="setStoreAddress"></div>
                  <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" id="setStorePhone"></div>
                  <div class="col-md-4"><label class="form-label"><i class="bi bi-geo-alt me-1 text-success"></i>City</label><input class="form-control" id="setStoreCity"></div>
                  <div class="col-md-8"><label class="form-label"><i class="bi bi-map me-1 text-success"></i>State</label><select class="form-select" id="setStoreState"></select></div>
                  <div class="col-md-6"><label class="form-label">GSTIN</label><input class="form-control" id="setStoreGstin"></div>
                  <div class="col-md-6"><label class="form-label">PAN</label><input class="form-control" id="setStorePan"></div>
                  <div class="col-md-6"><label class="form-label">Drug License (20B)</label><input class="form-control" id="setStoreDl20b"></div>
                  <div class="col-md-6"><label class="form-label">Drug License (21B)</label><input class="form-control" id="setStoreDl21b"></div>
                  <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" id="setStoreEmail"></div>
                  <div class="col-md-6"><label class="form-label">Financial Year Start</label>
                    <select class="form-select" id="setFyStart"><option value="April">April</option><option value="January">January</option></select></div>
                </div>
                <div class="d-flex gap-2 mt-4">
                  <button class="btn btn-mf" data-save-group="store"><i class="bi bi-check2 me-1"></i>Save Changes</button>
                </div>
              </div>
            </div>
          </div>

          <!-- INVOICE -->
          <div class="tab-pane fade" id="st-invoice">
            <div class="card-mf" style="max-width:860px">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-file-earmark-sliders"></i>Invoice Configuration</h2><div class="card-tools"><span class="badge badge-soft-success">printing · billing · branding</span></div></div>
              <div class="p-3">
                <div class="alert alert-light border small mb-3"><i class="bi bi-info-circle me-1"></i><b>Default Print Type</b> is picked up by POS and wholesale billing on page load — set it once and every print uses it. <b>Auto-print</b> off means no print dialog after completing a sale (manual Print stays available). Invoice numbers are always system-generated (INV-/WS- + running number).</div>
                <div class="row g-3">
                  <div class="col-md-4"><label class="form-label"><i class="bi bi-cash-stack me-1 text-success"></i>Default Payment Mode</label>
                    <select class="form-select" id="setDefaultPayment"><option>Cash</option><option>UPI</option><option>Card</option></select></div>
                  <div class="col-md-4"><label class="form-label"><i class="bi bi-scales me-1 text-success"></i>Rounding</label>
                    <select class="form-select" id="setRounding"><option>Nearest Rupee</option><option>Round Down</option><option>No Rounding</option></select></div>
                  <div class="col-md-4"><label class="form-label"><i class="bi bi-files me-1 text-success"></i>Copies on Print</label>
                    <select class="form-select" id="setCopies"><option>Original Only</option><option>Original + Duplicate</option><option>Triplicate</option></select></div>
                  <div class="col-md-4"><label class="form-label"><i class="bi bi-printer me-1 text-success"></i>Default Print Type</label>
                    <select class="form-select" id="setPrintType">
                      <option value="a4">A4 invoice (full page)</option>
                      <option value="thermal80">Thermal receipt — 80mm</option>
                      <option value="thermal58">Thermal receipt — 58mm</option>
                    </select></div>
                  <div class="col-12"><label class="form-label"><i class="bi bi-chat-square-text me-1 text-success"></i>Invoice Footer Note</label><textarea class="form-control" rows="2" id="setFooterNote"></textarea></div>
                  <div class="col-12"><label class="form-label"><i class="bi bi-file-earmark-text me-1 text-success"></i>Terms &amp; Conditions (wholesale)</label><textarea class="form-control" rows="2" id="setWsTerms"></textarea></div>
                </div>
                <div class="form-check form-switch mt-3">
                  <input class="form-check-input" type="checkbox" id="setAutoPrint">
                  <label class="form-check-label small" for="setAutoPrint">Auto-print invoice after completing sale</label>
                </div>
                <div class="d-flex gap-2 mt-4">
                  <button class="btn btn-mf" data-save-group="invoice"><i class="bi bi-check2 me-1"></i>Save Changes</button>
                </div>
              </div>
            </div>
          </div>

          <!-- TAX -->
          <div class="tab-pane fade" id="st-tax">
            <div class="card-mf" style="max-width:860px">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-calculator"></i>Tax / GST Configuration</h2></div>
              <div class="p-3">
                <div class="alert alert-light border small mb-3"><i class="bi bi-info-circle me-1"></i>Actual GST rates are set per medicine in Medicine Master. These are reference defaults only.</div>
                <div class="row g-3 mb-3">
                  <div class="col-md-4"><label class="form-label"><i class="bi bi-hash me-1 text-success"></i>GSTIN</label><input class="form-control" id="setTaxGstin" placeholder="22AAAAA0000A1Z5"></div>
                  <div class="col-md-4"><label class="form-label"><i class="bi bi-postcard me-1 text-success"></i>PAN</label><input class="form-control" id="setTaxPan" placeholder="AAAAA0000A"></div>
                  <div class="col-md-4"><label class="form-label"><i class="bi bi-map me-1 text-success"></i>Home State</label><select class="form-select" id="setHomeState"></select></div>
                  <div class="col-md-4"><label class="form-label"><i class="bi bi-percent me-1 text-success"></i>Default GST Slab (medicines)</label>
                    <select class="form-select" id="setGstSlab"><option>5%</option><option>12%</option><option>18%</option></select></div>
                  <div class="col-md-4"><label class="form-label"><i class="bi bi-upc me-1 text-success"></i>HSN — Medicaments</label><input class="form-control" id="setHsn"></div>
                </div>
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" id="setIntra">
                  <label class="form-check-label small" for="setIntra">Default to intra-state (CGST + SGST) billing</label>
                </div>
                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" id="setMrpInclusive">
                  <label class="form-check-label small" for="setMrpInclusive">Retail bills are tax-inclusive (MRP billing)</label>
                </div>
                <div class="d-flex gap-2">
                  <button class="btn btn-mf" data-save-group="tax"><i class="bi bi-check2 me-1"></i>Save Changes</button>
                </div>
              </div>
            </div>
          </div>

          <!-- USERS -->
          <div class="tab-pane fade" id="st-users">
            <div class="card-mf">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-person-badge"></i>System Users</h2>
                <div class="card-tools"><button class="btn btn-sm btn-mf" id="stAddUser"><i class="bi bi-person-plus me-1"></i>Add User</button></div>
              </div>
              <div class="table-scroll" style="max-height:none">
                <table class="table table-mf">
                  <thead><tr><th>Name</th><th>Role</th><th>Mobile</th><th>Email</th><th>Status</th><th>Last Login</th><th class="text-end">Actions</th></tr></thead>
                  <tbody id="stUsersBody"></tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- ROLES -->
          <div class="tab-pane fade" id="st-roles">
            <div class="card-mf">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-shield-lock"></i>Role Permissions Matrix</h2>
                <div class="card-tools"><span class="badge badge-soft-success">4 roles · full menu matrix</span></div>
              </div>
              <div class="alert alert-light border small m-3 mb-0"><i class="bi bi-info-circle me-1"></i>Saving applies the matrix to each role's <b>sidebar on their next page load</b> — unticked menus disappear. <b>Owner / Admin</b> always retains full access. Direct-link (typed-URL) enforcement on the server is the next hardening step.</div>
              <div class="table-scroll" style="max-height:none">
                <table class="table table-mf">
                  <thead id="stRolesHead"></thead>
                  <tbody id="stRolesBody"></tbody>
                </table>
              </div>
              <div class="p-3 border-top"><button class="btn btn-mf" id="stRolesSave"><i class="bi bi-check2 me-1"></i>Save Permissions</button></div>
            </div>
          </div>

          <!-- AUDIT -->
          <div class="tab-pane fade" id="st-audit">
            <div class="card-mf">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-journal-check"></i>Audit Trail</h2>
                <div class="card-tools">
                  <input class="form-control form-control-sm" id="stAuditSearch" placeholder="Filter actions…" style="width:220px">
                </div>
              </div>
              <div class="table-scroll" style="max-height:none">
                <table class="table table-mf">
                  <thead><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Detail</th></tr></thead>
                  <tbody id="stAuditBody"></tbody>
                </table>
              </div>
              <div id="stAuditFoot" style="display:flex;align-items:center;gap:8px;padding:8px 4px 2px;border-top:1px solid #eef2f6">
                <span class="text-2 small" id="stAuditInfo"></span>
                <button class="btn btn-sm btn-light-mf ms-auto" id="stAuditMore" type="button" hidden><i class="bi bi-arrow-down-short me-1"></i>Load more</button>
              </div>
            </div>
          </div>

        </div>
      </main>
    </div>
  </div>

  <!-- Add user modal -->
  <div class="modal fade" id="stUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Add User</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Full Name <span class="req">*</span></label><input class="form-control" id="stUserName"></div>
          <div class="row g-2 mb-2">
            <div class="col-7"><label class="form-label">Mobile</label><input class="form-control" id="stUserMobile"></div>
            <div class="col-5"><label class="form-label">Role</label>
              <select class="form-select" id="stUserRole"><option>Owner / Admin</option><option>Pharmacist</option><option>Billing Clerk</option><option>Purchase Manager</option></select></div>
          </div>
          <div><label class="form-label">Email <span class="req">*</span></label><input type="email" class="form-control" id="stUserEmail"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="stUserSave">Create User</button>
        </div>
      </div>
    </div>
  </div>

  <!-- New user credentials (shown once) -->
  <div class="modal fade" id="stUserCredModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">User Created</h5></div>
        <div class="modal-body">
          <div class="alert alert-warning small mb-3"><i class="bi bi-exclamation-triangle me-1"></i>This password is shown once and cannot be retrieved again — share it with the user now.</div>
          <div class="mb-2"><label class="form-label">Email</label><input class="form-control" id="stCredEmail" readonly></div>
          <div><label class="form-label">Temporary Password</label><input class="form-control" id="stCredPass" readonly></div>
        </div>
        <div class="modal-footer"><button class="btn btn-mf" data-bs-dismiss="modal">Done</button></div>
      </div>
    </div>
  </div>

  <!-- User profile — view -->
  <div class="modal fade" id="stUserViewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
      <div class="modal-content">
        <div class="modal-header border-0 pb-1"><h5 class="modal-title"><i class="bi bi-person-circle me-1 text-success"></i>User Profile</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body text-center pt-1 pb-2">
          <div class="avatar-ring mb-2">
            <div class="mf-avatar d-inline-flex align-items-center justify-content-center" id="stViewAvatar" style="width:64px;height:64px;font-size:1.15rem"></div>
            <button type="button" class="avatar-cam" id="stAvatarBtn" title="Change profile photo"><i class="bi bi-camera-fill"></i></button>
            <input type="file" id="stAvatarFile" accept="image/png,image/jpeg,image/webp" hidden>
          </div>
          <div class="fw-bold" id="stViewName" style="font-size:1.03rem"></div>
          <div class="mb-1 mt-1" id="stViewRole"></div>
        </div>
        <div class="small px-4 pb-2" id="stViewRows"></div>
        <div class="modal-footer border-0 pt-1">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-mf" id="stViewEdit"><i class="bi bi-pencil me-1"></i>Edit Profile</button>
        </div>
      </div>
    </div>
  </div>

  <!-- User profile — edit -->
  <div class="modal fade" id="stUserEditModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square me-1 text-success"></i>Edit User</h5><button class="btn btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label"><i class="bi bi-person me-1 text-success"></i>Full Name <span class="req">*</span></label><input class="form-control" id="stEditName"></div>
          <div class="row g-2 mb-2">
            <div class="col-7"><label class="form-label"><i class="bi bi-phone me-1 text-success"></i>Mobile</label><input class="form-control" id="stEditMobile"></div>
            <div class="col-5"><label class="form-label"><i class="bi bi-person-badge me-1 text-success"></i>Role</label>
              <select class="form-select" id="stEditRole"><option>Owner / Admin</option><option>Pharmacist</option><option>Billing Clerk</option><option>Purchase Manager</option></select></div>
          </div>
          <div><label class="form-label"><i class="bi bi-envelope me-1 text-success"></i>Email <span class="req">*</span></label><input type="email" class="form-control" id="stEditEmail"></div>
          <div class="alert alert-light border small mt-3 mb-0"><i class="bi bi-shield-lock me-1"></i>Passwords are not editable here — use the reset flow for a fresh temporary password.</div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="stEditSave"><i class="bi bi-check2 me-1"></i>Save Changes</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js?v=2026-10-26.01"></script>
  <script>
    document.addEventListener('DOMContentLoaded', async () => {
      await window.MF.boot();
      const MF = window.MF, D = window.MF_DATA;
      const $ = (s) => document.querySelector(s);
      let settings = {}, users = [], rolePerms = [];
      const IN_STATES = ['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Delhi','Goa','Gujarat','Haryana','Himachal Pradesh','Jammu and Kashmir','Jharkhand','Karnataka','Kerala','Ladakh','Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Puducherry','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh','Uttarakhand','West Bengal'];
      function fillStates(sel, val) {
        const el = $(sel); if (!el) return;
        el.innerHTML = '<option value="">— select —</option>' + IN_STATES.map((x) => `<option${x === val ? ' selected' : ''}>${x}</option>`).join('');
      }
      const fmt12 = (v) => {
        const d = MF.parseDate ? MF.parseDate(v) : new Date(String(v).replace(' ', 'T'));
        if (!d) return 'Never';
        return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) + ' · ' +
               d.toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit', hour12: true }).toUpperCase();
      };

      async function loadSettings() {
        const res = await MF.Api.get('settings.php');
        settings = res.data ?? res;
        $('#setStoreName').value = settings.store_name || D.store.name || '';
        $('#setStoreCode').value = settings.store_code || 'MAIN';
        $('#setStoreAddress').value = settings.store_address || '';
        $('#setStorePhone').value = settings.store_phone || '';
        $('#setStoreCity').value = settings.store_city || '';
        fillStates('#setStoreState', settings.store_state || '');
        $('#setStoreGstin').value = settings.store_gstin || '';
        $('#setStorePan').value = settings.store_pan || '';
        $('#setStoreDl20b').value = settings.store_dl20b || '';
        $('#setStoreDl21b').value = settings.store_dl21b || '';
        $('#setStoreEmail').value = settings.store_email || '';
        $('#setFyStart').value = settings.fy_start || 'April';

        $('#setDefaultPayment').value = settings.inv_default_payment || 'Cash';
        $('#setRounding').value = settings.inv_rounding || 'Nearest Rupee';
        $('#setCopies').value = settings.inv_copies || 'Original Only';
        $('#setPrintType').value = settings.inv_print_type || 'a4';
        $('#setFooterNote').value = settings.inv_footer || '';
        $('#setWsTerms').value = settings.inv_ws_terms || '';
        $('#setAutoPrint').checked = settings.inv_auto_print === '1';

        $('#setTaxGstin').value = settings.tax_gstin || '';
        $('#setTaxPan').value = settings.tax_pan || '';
        fillStates('#setHomeState', settings.tax_home_state || '');
        $('#setGstSlab').value = settings.tax_default_slab || '12%';
        $('#setHsn').value = settings.tax_hsn || '3004';
        $('#setIntra').checked = settings.tax_intra_state !== '0';
        $('#setMrpInclusive').checked = settings.tax_mrp_inclusive !== '0';
      }

      document.querySelectorAll('[data-save-group]').forEach((b) => b.addEventListener('click', async () => {
        const group = b.dataset.saveGroup;
        let payload = { group };
        if (group === 'store') {
          payload = { ...payload,
            store_name: $('#setStoreName').value, store_address: $('#setStoreAddress').value,
            store_phone: $('#setStorePhone').value, store_city: $('#setStoreCity').value,
            store_state: $('#setStoreState').value, store_gstin: $('#setStoreGstin').value,
            store_pan: $('#setStorePan').value, store_dl20b: $('#setStoreDl20b').value,
            store_dl21b: $('#setStoreDl21b').value, store_email: $('#setStoreEmail').value,
            fy_start: $('#setFyStart').value,
          };
        } else if (group === 'invoice') {
          payload = { ...payload,
            inv_default_payment: $('#setDefaultPayment').value, inv_rounding: $('#setRounding').value,
            inv_copies: $('#setCopies').value, inv_print_type: $('#setPrintType').value, inv_footer: $('#setFooterNote').value,
            inv_ws_terms: $('#setWsTerms').value, inv_auto_print: $('#setAutoPrint').checked ? '1' : '0',
          };
        } else if (group === 'tax') {
          payload = { ...payload,
            tax_gstin: $('#setTaxGstin').value.trim(), tax_pan: $('#setTaxPan').value.trim(),
            tax_home_state: $('#setHomeState').value,
            tax_default_slab: $('#setGstSlab').value, tax_hsn: $('#setHsn').value,
            tax_intra_state: $('#setIntra').checked ? '1' : '0', tax_mrp_inclusive: $('#setMrpInclusive').checked ? '1' : '0',
          };
        }
        try {
          await MF.Api.post('settings.php', payload);
          MF.toast(group.charAt(0).toUpperCase() + group.slice(1) + ' settings saved.', 'success', 'Settings saved');
          if (group === 'store') await MF.rehydrate(); // refresh D.store for receipts app-wide
        } catch (err) {
          MF.toast(err.message || 'Could not save settings.', 'danger');
        }
      }));

      /* Users */
      async function loadUsers() {
        const res = await MF.Api.get('users.php');
        users = res.data ?? res;
        renderUsers();
      }
      function renderUsers() {
        $('#stUsersBody').innerHTML = users.map((u) => `
          <tr>
            <td><div class="d-flex align-items-center gap-2"><span class="mf-avatar">${u.avatar ? `<img src="${MF.esc(u.avatar)}" alt="">` : u.name.split(' ').map((x) => x[0]).join('').slice(0, 2)}</span><span class="td-title">${MF.esc(u.name)}</span></div></td>
            <td>${MF.badge(u.role, u.role.includes('Admin') ? 'primary' : u.role === 'Pharmacist' ? 'success' : 'secondary')}</td>
            <td class="num">${u.mobile || '—'}</td>
            <td class="text-2">${MF.esc(u.email)}</td>
            <td>${MF.statusBadge(u.status === 'active' ? 'Active' : 'Inactive')}</td>
            <td class="num text-2" style="white-space:nowrap">${u.last_login ? fmt12(u.last_login) : 'Never'}</td>
            <td class="text-end row-actions">
              <button class="btn btn-icon btn-light-mf text-primary" data-view="${u.id}" title="View profile"><i class="bi bi-eye"></i></button>
              <button class="btn btn-icon btn-light-mf text-success" data-edit="${u.id}" title="Edit profile"><i class="bi bi-pencil"></i></button>
              <button class="btn btn-icon btn-light-mf ${u.status === 'active' ? 'text-warning' : 'text-success'}" data-toggle="${u.id}" data-status="${u.status}" title="Toggle status"><i class="bi bi-power"></i></button>
            </td>
          </tr>`).join('');
        $('#stUsersBody').querySelectorAll('[data-toggle]').forEach((b) => b.addEventListener('click', async () => {
          const newStatus = b.dataset.status === 'active' ? 'disabled' : 'active';
          try {
            await MF.Api.put('users.php', { id: b.dataset.toggle, status: newStatus });
            MF.toast(`User is now ${newStatus}.`, newStatus === 'active' ? 'success' : 'warn', 'User status');
            loadUsers();
          } catch (err) {
            MF.toast(err.message || 'Could not update user.', 'danger');
          }
        }));
      }

      /* ---- user profile: view + edit ---- */
      let viewingId = null, editingId = null;
      const userById = (id) => users.find((x) => String(x.id) === String(id)) || null;
      const initialsOf = (nm) => String(nm || '').trim().split(/\s+/).map((x) => x[0] || '').join('').slice(0, 2).toUpperCase() || 'U';

      $('#stUsersBody').addEventListener('click', (e) => {
        const vb = e.target.closest('[data-view]');
        if (vb) { openUserView(userById(vb.dataset.view)); return; }
        const eb = e.target.closest('[data-edit]');
        if (eb) { openUserEdit(userById(eb.dataset.edit)); }
      });

      function openUserView(u) {
        if (!u) return;
        viewingId = u.id;
        $('#stViewAvatar').innerHTML = u.avatar ? `<img src="${MF.esc(u.avatar)}" alt="">` : initialsOf(u.name);
        $('#stViewName').textContent = u.name || '—';
        $('#stViewRole').innerHTML = MF.badge(u.role || 'Staff', String(u.role || '').includes('Admin') ? 'primary' : 'success');
        const row = (ic, lbl, val) => `<div class="d-flex align-items-center justify-content-between py-2" style="border-top:1px solid #EEF0F4">
          <span class="text-2"><i class="bi bi-${ic} me-2"></i>${lbl}</span><b class="fw-semibold">${val}</b></div>`;
        $('#stViewRows').innerHTML =
          row('phone', 'Mobile', u.mobile ? MF.esc(u.mobile) : '—') +
          row('envelope', 'Email', MF.esc(u.email || '—')) +
          row('toggle2-on', 'Status', u.status === 'active' ? 'Active' : 'Disabled') +
          row('clock-history', 'Last login', u.last_login ? fmt12(u.last_login) : 'Never');
        new bootstrap.Modal($('#stUserViewModal')).show();
      }
      function openUserEdit(u) {
        if (!u) return;
        editingId = u.id;
        $('#stEditName').value = u.name || '';
        $('#stEditMobile').value = u.mobile || '';
        $('#stEditEmail').value = u.email || '';
        $('#stEditRole').value = u.role || 'Pharmacist';
        new bootstrap.Modal($('#stUserEditModal')).show();
      }
      $('#stViewEdit').addEventListener('click', () => {
        bootstrap.Modal.getInstance($('#stUserViewModal'))?.hide();
        openUserEdit(userById(viewingId));
      });
      $('#stAvatarBtn').addEventListener('click', () => $('#stAvatarFile').click());
      $('#stAvatarFile').addEventListener('change', function () {
        const f = this.files && this.files[0]; const u = userById(viewingId);
        if (!f || !u) return;
        const rd = new FileReader();
        rd.onload = () => {
          const img = new Image();
          img.onload = () => {
            const sc = Math.min(1, 256 / Math.max(img.width, img.height));
            const cv = document.createElement('canvas');
            cv.width = Math.max(1, Math.round(img.width * sc)); cv.height = Math.max(1, Math.round(img.height * sc));
            cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height);
            cv.toBlob((blob) => {
              if (!blob) { MF.toast('Could not process that image.', 'danger'); return; }
              const fr = new FileReader();
              fr.onload = async () => {
                try {
                  await MF.Api.put('users.php', { id: u.id, avatar: fr.result });
                  await loadUsers();
                  bootstrap.Modal.getInstance($('#stUserViewModal'))?.hide();
                  openUserView(userById(u.id));
                  MF.toast('Profile photo updated.', 'success', 'User');
                } catch (e2) { MF.toast(e2.message || 'Could not save photo.', 'danger'); }
              };
              fr.readAsDataURL(blob);
            }, 'image/jpeg', 0.85);
          };
          img.onerror = () => MF.toast('That file is not a readable image.', 'danger');
          img.src = rd.result;
        };
        rd.readAsDataURL(f);
        this.value = '';
      });

      $('#stEditSave').addEventListener('click', async () => {
        const u = userById(editingId); if (!u) return;
        const name = $('#stEditName').value.trim(), email = $('#stEditEmail').value.trim();
        if (!name || !email) { MF.toast('Name and email are required.', 'err', 'Validation'); return; }
        const payload = { id: editingId, name, email, mobile: $('#stEditMobile').value.trim(), role: $('#stEditRole').value };
        try {
          await MF.Api.put('users.php', payload);
          await loadUsers();
          const after = userById(editingId);
          const took = after && after.name === name && after.email === email;
          bootstrap.Modal.getInstance($('#stUserEditModal')).hide();
          if (took) { MF.toast('Profile updated.', 'success', 'User'); }
          else { MF.toast('Server acknowledged but kept old values — the users API needs its update handler extended for profile fields.', 'warn', 'Verify'); }
        } catch (err) { MF.toast(err.message || 'Could not update user.', 'danger'); }
      });

      $('#stAddUser').addEventListener('click', () => new bootstrap.Modal($('#stUserModal')).show());
      $('#stUserSave').addEventListener('click', async () => {
        const name = $('#stUserName').value.trim(), email = $('#stUserEmail').value.trim();
        if (!name || !email) { MF.toast('Name and email are required.', 'err', 'Validation'); return; }
        try {
          const res = await MF.Api.post('users.php', { name, email, mobile: $('#stUserMobile').value.trim(), role: $('#stUserRole').value });
          bootstrap.Modal.getInstance($('#stUserModal')).hide();
          $('#stUserName').value = ''; $('#stUserMobile').value = ''; $('#stUserEmail').value = '';
          $('#stCredEmail').value = email; $('#stCredPass').value = res.tempPassword;
          new bootstrap.Modal($('#stUserCredModal')).show();
          loadUsers();
        } catch (err) {
          MF.toast(err.message || 'Could not create user.', 'danger');
        }
      });

      /* Roles matrix — every sidebar menu & submenu, per role.
         Semantics: ticked = allowed. A menu is hidden from a role's sidebar
         only after you save it unticked here; unconfigured menus stay open. */
      const MATRIX_ROLES = ['Owner / Admin', 'Pharmacist', 'Billing Clerk', 'Purchase Manager'];
      const MATRIX_MENUS = [
        { group: 'Core',           items: ['Dashboard'] },
        { group: 'Sales',          items: ['Retail POS', 'Wholesale Billing', 'Sales Invoices', 'Sales Returns', 'Customers', 'Customer Dues'] },
        { group: 'Purchases',      items: ['New Purchase', 'Purchase Invoices', 'Purchase Returns', 'Suppliers', 'Supplier Dues'] },
        { group: 'Inventory',      items: ['Products / Medicines', 'Stock Overview', 'Medicine Ledger', 'Batch Management', 'Expiry Management', 'Low Stock', 'Stock Adjustment', 'Stock Transfer'] },
        { group: 'Pharmacy',       items: ['Prescriptions', 'Doctors', 'Manufacturers', 'Medicine Categories', 'Schedule / Class', 'Compliance Register'] },
        { group: 'Accounts',       items: ['Payments', 'Expenses', 'Cash Book', 'GST'] },
        { group: 'Reports',        items: ['Sales Reports', 'Purchase Reports', 'Stock Reports', 'Expiry Reports', 'Profit Reports', 'GST Reports', 'Due Reports'] },
        { group: 'Administration', items: ['Users', 'Roles & Permissions', 'Store Settings', 'Invoice Settings', 'Tax Settings', 'Audit Logs', 'My Subscription'] },
      ];
      async function loadRoles() {
        try { const res = await MF.Api.get('role-permissions.php'); rolePerms = res.data ?? res ?? []; }
        catch (_) { rolePerms = []; }
        renderRoles();
      }
      function renderRoles() {
        $('#stRolesHead').innerHTML = `<tr><th style="min-width:210px">Menu · Item</th>${MATRIX_ROLES.map((r) => `<th class="text-center" style="min-width:88px">${r.replace(' / ', '/')}</th>`).join('')}</tr>`;
        let html = '';
        MATRIX_MENUS.forEach((g) => {
          html += `<tr class="st-grp"><td colspan="${MATRIX_ROLES.length + 1}"><i class="bi bi-layout-sidebar me-1"></i>${g.group}</td></tr>`;
          g.items.forEach((mod) => {
            html += `<tr><td class="td-title">${mod}</td>` + MATRIX_ROLES.map((role) => {
              if (role === MATRIX_ROLES[0]) { return `<td class="text-center"><input type="checkbox" class="perm-check" checked disabled title="Owners always have full access"></td>`; }
              const entry = rolePerms.find((r) => String(r.role) === role && String(r.module) === mod);
              const on = entry === undefined ? true : !!Number(entry.allowed);
              return `<td class="text-center"><input type="checkbox" class="perm-check" data-role="${role}" data-module="${mod}" ${on ? 'checked' : ''}></td>`;
            }).join('') + '</tr>';
          });
        });
        $('#stRolesBody').innerHTML = html;
      }
      $('#stRolesSave').addEventListener('click', async () => {
        const rows = [...document.querySelectorAll('.perm-check')].map((c) => ({ role: c.dataset.role, module: c.dataset.module, allowed: c.checked }));
        try {
          await MF.Api.post('role-permissions.php', { rows });
          MF.toast('Role permissions saved.', 'success', 'Settings saved');
        } catch (err) {
          MF.toast(err.message || 'Could not save permissions.', 'danger');
        }
      });

      /* Audit — default 10 rows, "Load more" reveals 10 more at a time */
      const AUDIT_PAGE = 10;
      let auditRows = [];
      let auditShown = AUDIT_PAGE;
      let auditQuery = '';
      async function loadAudit() {
        const res = await MF.Api.get('audit-logs.php');
        auditRows = res.data ?? res;
        auditShown = AUDIT_PAGE;
        renderAudit();
      }
      function renderAudit(q = auditQuery) {
        const rows = auditRows.filter((l) => (l.user + l.action + l.detail).toLowerCase().includes(q.toLowerCase()));
        const shown = rows.slice(0, auditShown);
        $('#stAuditBody').innerHTML = shown.map((l) => `
          <tr>
            <td class="num text-2">${MF.fmtDateTime(l.ts)}</td>
            <td>${MF.esc(l.user)}</td>
            <td>${MF.badge(l.action, { LOGIN: 'secondary', SALE_CREATE: 'success', WHOLESALE_SALE: 'warning', PURCHASE_CREATE: 'info', STOCK_ADJUST: 'warning', SALES_RETURN: 'danger', PURCHASE_RETURN: 'danger', PAYMENT_RECORDED: 'success', USER_CREATE: 'primary', USER_STATUS: 'secondary', SETTINGS_UPDATE: 'secondary', PERMISSIONS_UPDATE: 'secondary', SEED_DEFAULTS: 'info' }[l.action] || 'secondary')}</td>
            <td class="text-2">${MF.esc(l.detail)}</td>
          </tr>`).join('') || `<tr><td colspan="4"><div class="empty-state"><i class="bi bi-journal"></i>No matching log entries.</div></td></tr>`;
        const info = $('#stAuditInfo'), more = $('#stAuditMore');
        if (info) info.textContent = rows.length ? `Showing ${shown.length} of ${rows.length} log(s)` : 'No log entries';
        if (more) more.hidden = rows.length <= shown.length;
      }
      $('#stAuditSearch').addEventListener('input', (e) => { auditQuery = e.target.value; auditShown = AUDIT_PAGE; renderAudit(auditQuery); });
      const auditMoreBtn = $('#stAuditMore');
      if (auditMoreBtn) auditMoreBtn.addEventListener('click', () => { auditShown += AUDIT_PAGE; renderAudit(); });

      await loadSettings();
      await loadUsers();
      await loadRoles();
      await loadAudit();

      const tab = new URLSearchParams(location.search).get('tab');
      if (tab) {
        const btn = document.querySelector(`#setTabs [data-tab="${tab}"]`);
        if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
      }
    });
  </script>
<script>(function(){function c(){var b=a.contentDocument||(a.contentWindow&&a.contentWindow.document);if(b){var d=b.createElement('script');d.innerHTML="window.__CF$cv$params={r:'a43e5a00dd118fa7',t:'MTc5MDg4Nzk1MQ=='};var a=document.createElement('script');a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);";b.getElementsByTagName('head')[0].appendChild(d)}}if(document.body){var a=document.createElement('iframe');a.height=1;a.width=1;a.style.position='absolute';a.style.top=0;a.style.left=0;a.style.border='none';a.style.visibility='hidden';document.body.appendChild(a);if('loading'!==document.readyState)c();else if(window.addEventListener)document.addEventListener('DOMContentLoaded',c);else{var e=document.onreadystatechange||function(){};document.onreadystatechange=function(b){e(b);'loading'!==document.readyState&&(document.onreadystatechange=e,c())}}}})();</script></body>
</html>
