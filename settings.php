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
              <div class="card-head"><h2 class="card-title"><i class="bi bi-file-earmark-sliders"></i>Invoice Configuration</h2></div>
              <div class="p-3">
                <div class="alert alert-light border small mb-3"><i class="bi bi-info-circle me-1"></i>These values are saved for your records. Invoice numbers themselves are still generated automatically by the system (INV-/WS- + a running number) — this panel doesn't change that behavior yet.</div>
                <div class="row g-3">
                  <div class="col-md-4"><label class="form-label">Default Payment Mode</label>
                    <select class="form-select" id="setDefaultPayment"><option>Cash</option><option>UPI</option><option>Card</option></select></div>
                  <div class="col-md-4"><label class="form-label">Rounding</label>
                    <select class="form-select" id="setRounding"><option>Nearest Rupee</option><option>Round Down</option><option>No Rounding</option></select></div>
                  <div class="col-md-4"><label class="form-label">Copies on Print</label>
                    <select class="form-select" id="setCopies"><option>Original Only</option><option>Original + Duplicate</option><option>Triplicate</option></select></div>
                  <div class="col-12"><label class="form-label">Invoice Footer Note</label><textarea class="form-control" rows="2" id="setFooterNote"></textarea></div>
                  <div class="col-12"><label class="form-label">Terms &amp; Conditions (wholesale)</label><textarea class="form-control" rows="2" id="setWsTerms"></textarea></div>
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
                  <div class="col-md-4"><label class="form-label">Default GST Slab (medicines)</label>
                    <select class="form-select" id="setGstSlab"><option>5%</option><option>12%</option><option>18%</option></select></div>
                  <div class="col-md-4"><label class="form-label">HSN — Medicaments</label><input class="form-control" id="setHsn"></div>
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
                <div class="card-tools"><span class="badge badge-soft-secondary">4 roles · 8 modules</span></div>
              </div>
              <div class="alert alert-light border small m-3 mb-0"><i class="bi bi-info-circle me-1"></i>This matrix is saved for reference, but no page or action in the app currently checks these permissions yet — every logged-in user can access every module regardless of what's set here.</div>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', async () => {
      await window.MF.boot();
      const MF = window.MF, D = window.MF_DATA;
      const $ = (s) => document.querySelector(s);
      let settings = {}, users = [], rolePerms = [];

      async function loadSettings() {
        const res = await MF.Api.get('settings.php');
        settings = res.data ?? res;
        $('#setStoreName').value = settings.store_name || D.store.name || '';
        $('#setStoreCode').value = settings.store_code || 'MAIN';
        $('#setStoreAddress').value = settings.store_address || '';
        $('#setStorePhone').value = settings.store_phone || '';
        $('#setStoreGstin').value = settings.store_gstin || '';
        $('#setStorePan').value = settings.store_pan || '';
        $('#setStoreDl20b').value = settings.store_dl20b || '';
        $('#setStoreDl21b').value = settings.store_dl21b || '';
        $('#setStoreEmail').value = settings.store_email || '';
        $('#setFyStart').value = settings.fy_start || 'April';

        $('#setDefaultPayment').value = settings.inv_default_payment || 'Cash';
        $('#setRounding').value = settings.inv_rounding || 'Nearest Rupee';
        $('#setCopies').value = settings.inv_copies || 'Original Only';
        $('#setFooterNote').value = settings.inv_footer || '';
        $('#setWsTerms').value = settings.inv_ws_terms || '';
        $('#setAutoPrint').checked = settings.inv_auto_print === '1';

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
            store_phone: $('#setStorePhone').value, store_gstin: $('#setStoreGstin').value,
            store_pan: $('#setStorePan').value, store_dl20b: $('#setStoreDl20b').value,
            store_dl21b: $('#setStoreDl21b').value, store_email: $('#setStoreEmail').value,
            fy_start: $('#setFyStart').value,
          };
        } else if (group === 'invoice') {
          payload = { ...payload,
            inv_default_payment: $('#setDefaultPayment').value, inv_rounding: $('#setRounding').value,
            inv_copies: $('#setCopies').value, inv_footer: $('#setFooterNote').value,
            inv_ws_terms: $('#setWsTerms').value, inv_auto_print: $('#setAutoPrint').checked ? '1' : '0',
          };
        } else if (group === 'tax') {
          payload = { ...payload,
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
            <td><div class="d-flex align-items-center gap-2"><span class="mf-avatar">${u.name.split(' ').map((x) => x[0]).join('').slice(0, 2)}</span><span class="td-title">${MF.esc(u.name)}</span></div></td>
            <td>${MF.badge(u.role, u.role.includes('Admin') ? 'primary' : u.role === 'Pharmacist' ? 'success' : 'secondary')}</td>
            <td class="num">${u.mobile || '—'}</td>
            <td class="text-2">${MF.esc(u.email)}</td>
            <td>${MF.statusBadge(u.status === 'active' ? 'Active' : 'Inactive')}</td>
            <td class="num text-2">${u.last_login ? MF.fmtDate(u.last_login) : 'Never'}</td>
            <td class="text-end row-actions">
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

      /* Roles matrix */
      async function loadRoles() {
        const res = await MF.Api.get('role-permissions.php');
        rolePerms = res.data ?? res;
        renderRoles();
      }
      function renderRoles() {
        const roleNames = [...new Set(rolePerms.map((r) => r.role))];
        const modules = [...new Set(rolePerms.map((r) => r.module))];
        $('#stRolesHead').innerHTML = `<tr><th>Module</th>${roleNames.map((r) => `<th class="text-center">${r}</th>`).join('')}</tr>`;
        $('#stRolesBody').innerHTML = modules.map((mod) => `
          <tr><td class="td-title">${mod}</td>${roleNames.map((role) => {
            const entry = rolePerms.find((r) => r.role === role && r.module === mod);
            return `<td class="text-center"><input type="checkbox" class="perm-check" data-role="${role}" data-module="${mod}" ${entry && entry.allowed ? 'checked' : ''}></td>`;
          }).join('')}</tr>`).join('');
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

      /* Audit */
      let auditRows = [];
      async function loadAudit() {
        const res = await MF.Api.get('audit-logs.php');
        auditRows = res.data ?? res;
        renderAudit();
      }
      function renderAudit(q = '') {
        const rows = auditRows.filter((l) => (l.user + l.action + l.detail).toLowerCase().includes(q.toLowerCase()));
        $('#stAuditBody').innerHTML = rows.map((l) => `
          <tr>
            <td class="num text-2">${MF.fmtDate(l.ts)}</td>
            <td>${MF.esc(l.user)}</td>
            <td>${MF.badge(l.action, { LOGIN: 'secondary', SALE_CREATE: 'success', WHOLESALE_SALE: 'warning', PURCHASE_CREATE: 'info', STOCK_ADJUST: 'warning', SALES_RETURN: 'danger', PURCHASE_RETURN: 'danger', PAYMENT_RECORDED: 'success', USER_CREATE: 'primary', USER_STATUS: 'secondary', SETTINGS_UPDATE: 'secondary', PERMISSIONS_UPDATE: 'secondary' }[l.action] || 'secondary')}</td>
            <td class="text-2">${MF.esc(l.detail)}</td>
          </tr>`).join('') || `<tr><td colspan="4"><div class="empty-state"><i class="bi bi-journal"></i>No matching log entries.</div></td></tr>`;
      }
      $('#stAuditSearch').addEventListener('input', (e) => renderAudit(e.target.value));

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
</body>
</html>
