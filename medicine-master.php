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
  <title>Medicine Master · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .mm-section-title{
      font-size:.75rem;
      font-weight:700;
      text-transform:uppercase;
      letter-spacing:.04em;
      color:#6c757d;
      padding-bottom:.35rem;
      margin-bottom:.9rem;
      border-bottom:1px solid #e9ecef;
    }
    .mm-section-title:not(:first-child){ margin-top:1.5rem; }
  </style>
</head>
<body data-page="medicine-master">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-capsule me-2 text-success"></i>Products / Medicines</h1>
            <p class="page-sub" id="mmCount"></p>
          </div>
          <div class="ms-auto d-flex gap-2">
            <button class="btn btn-light-mf" id="mmExport"><i class="bi bi-download me-1"></i>Export CSV</button>
            <button class="btn btn-mf" id="mmAddBtn"><i class="bi bi-plus-lg me-1"></i>Add Medicine</button>
          </div>
        </div>

        <!-- Filters -->
        <div class="card-mf p-3 mb-3">
          <div class="row g-2">
            <div class="col-md-3">
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input class="form-control" id="mmSearch" placeholder="Search name, generic, composition…">
              </div>
            </div>
            <div class="col-6 col-md-2"><select class="form-select" id="mmCategory"><option value="">All Categories</option></select></div>
            <div class="col-6 col-md-2"><select class="form-select" id="mmMfg"><option value="">All Manufacturers</option></select></div>
            <div class="col-6 col-md-2">
              <select class="form-select" id="mmStock">
                <option value="">All Stock Status</option>
                <option value="low">Low Stock</option>
                <option value="in">In Stock</option>
                <option value="out">Out of Stock</option>
              </select>
            </div>
            <div class="col-6 col-md-2">
              <select class="form-select" id="mmSchedule">
                <option value="">All Schedules</option>
                <option>OTC</option><option>H</option><option>H1</option>
              </select>
            </div>
            <div class="col-md-1"><button class="btn btn-light-mf w-100" id="mmClear"><i class="bi bi-x-lg"></i> Clear</button></div>
          </div>
        </div>

        <!-- Table -->
        <div class="card-mf">
          <div class="table-scroll" style="max-height:none">
            <table class="table table-mf">
              <thead>
                <tr>
                  <th>Medicine Name</th><th>Generic Name</th><th>Category</th><th>Manufacturer</th>
                  <th>HSN</th><th class="text-center">GST</th><th>Unit</th>
                  <th class="text-end">MRP</th><th class="text-end">Retail</th><th class="text-end">Wholesale</th>
                  <th class="text-end">Stock</th><th>Status</th><th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="mmBody"></tbody>
            </table>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2 p-3 border-top">
            <span class="text-2 small" id="mmPageInfo"></span>
            <div class="ms-auto"><ul class="pagination pagination-sm mb-0" id="mmPager"></ul></div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Add / Edit modal -->
  <div class="modal fade" id="mmFormModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="mmFormTitle">Add Medicine</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">

          <div class="mm-section-title">Basic Details</div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Medicine Name <span class="req">*</span></label><input class="form-control" id="fName" placeholder="e.g. Paracetamol 500mg"></div>
            <div class="col-md-6"><label class="form-label">Generic Name</label><input class="form-control" id="fGeneric" placeholder="e.g. Paracetamol"></div>
            <div class="col-md-6"><label class="form-label">Composition</label><input class="form-control" id="fComp"></div>
            <div class="col-md-3"><label class="form-label">Category</label><select class="form-select" id="fCategory"></select></div>
            <div class="col-md-3"><label class="form-label">Manufacturer</label><select class="form-select" id="fMfg"></select></div>
          </div>

          <div class="mm-section-title">Packaging &amp; Identification</div>
          <div class="row g-3">
            <div class="col-md-3 col-6"><label class="form-label">Schedule</label>
              <select class="form-select" id="fSchedule"><option>OTC</option><option>H</option><option>H1</option></select></div>
            <div class="col-md-3 col-6"><label class="form-label">HSN Code</label><input class="form-control" id="fHsn" value="3004"></div>
            <div class="col-md-3 col-6"><label class="form-label">Barcode</label><input class="form-control" id="fBarcode" placeholder="8901234…"></div>
            <div class="col-md-3 col-6"><label class="form-label">Unit</label>
              <select class="form-select" id="fUnit"><option>Strip</option><option>Bottle</option><option>Tube</option><option>Sachet</option><option>Vial</option><option>Inhaler</option><option>Pen</option></select></div>
            <div class="col-md-3 col-6"><label class="form-label">Pack Size</label><input class="form-control" id="fPack" placeholder="e.g. 15 Tablets"></div>
            <div class="col-md-3 col-6"><label class="form-label">Pack Qty</label><input type="number" min="1" class="form-control" id="fPackQty" value="1" placeholder="e.g. 10"></div>
            <div class="col-md-3 col-6"><label class="form-label">Sub-unit</label><input class="form-control" id="fSubUnit" placeholder="e.g. Tablet"></div>
            <div class="col-md-3 col-6 d-flex align-items-center">
              <div class="form-check form-switch mt-4 pt-1">
                <input class="form-check-input" type="checkbox" id="fAllowLoose">
                <label class="form-check-label" for="fAllowLoose">Allow loose sale</label>
              </div>
            </div>
          </div>

          <div class="mm-section-title">Pricing &amp; Tax</div>
          <div class="row g-3">
            <div class="col-md-3 col-6"><label class="form-label">GST %</label>
              <select class="form-select" id="fGst"><option>5</option><option selected>12</option><option>18</option></select></div>
            <div class="col-md-3 col-6"><label class="form-label">MRP (₹)</label><input type="number" step="0.01" class="form-control" id="fMrp"></div>
            <div class="col-md-3 col-6"><label class="form-label">Purchase Rate (₹)</label><input type="number" step="0.01" class="form-control" id="fPtr"></div>
            <div class="col-md-3 col-6"><label class="form-label">Retail Rate (₹)</label><input type="number" step="0.01" class="form-control" id="fRetail"></div>
            <div class="col-md-3 col-6"><label class="form-label">Wholesale Rate (₹)</label><input type="number" step="0.01" class="form-control" id="fWholesale"></div>
          </div>

          <div class="mm-section-title">Stock &amp; Status</div>
          <div class="row g-3">
            <div class="col-md-3 col-6"><label class="form-label">Minimum Stock</label><input type="number" class="form-control" id="fMin" value="50"></div>
            <div class="col-md-3 col-6"><label class="form-label">Reorder Level</label><input type="number" class="form-control" id="fReorder" value="100"></div>
            <div class="col-md-3 col-6 d-flex align-items-center">
              <div class="form-check form-switch mt-4 pt-1">
                <input class="form-check-input" type="checkbox" id="fRx">
                <label class="form-check-label" for="fRx">Prescription required</label>
              </div>
            </div>
            <div class="col-md-3 col-6 d-flex align-items-center">
              <div class="form-check form-switch mt-4 pt-1">
                <input class="form-check-input" type="checkbox" id="fActive" checked>
                <label class="form-check-label" for="fActive">Active</label>
              </div>
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="mmFormSave"><i class="bi bi-check2 me-1"></i>Save Medicine</button>
        </div>
      </div>
    </div>
  </div>

  <!-- View modal -->
  <div class="modal fade" id="mmViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Medicine Details</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="mmViewBody"></div>
      </div>
    </div>
  </div>

  <!-- Stock / Batches modal -->
  <div class="modal fade" id="mmStockModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="mmStockTitle">Batch Stock</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body p-0" id="mmStockBody"></div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
    (function () {
      const MF = window.MF, D = window.MF_DATA;
      const $ = (s) => document.querySelector(s);
      const state = { search: '', category: '', mfg: '', stock: '', schedule: '', page: 1, per: 8 };
      let editingId = null;

      function buildLookups() {
        $('#mmCategory').innerHTML = '<option value="">All Categories</option>' + D.categories.map((c) => `<option>${c}</option>`).join('');
        $('#mmMfg').innerHTML = '<option value="">All Manufacturers</option>' + D.manufacturers.map((m) => `<option>${m}</option>`).join('');
        $('#fCategory').innerHTML = D.categories.map((c) => `<option>${c}</option>`).join('');
        $('#fMfg').innerHTML = D.manufacturers.map((m) => `<option>${m}</option>`).join('');
      }

      function filtered() {
        const q = state.search.toLowerCase();
        return D.medicines.filter((m) => {
          const st = MF.stockOf(m.id);
          if (q && !(m.name + m.generic + m.composition + m.manufacturer).toLowerCase().includes(q)) return false;
          if (state.category && m.category !== state.category) return false;
          if (state.mfg && m.manufacturer !== state.mfg) return false;
          if (state.schedule && m.schedule !== state.schedule) return false;
          if (state.stock === 'low' && st > m.minStock) return false;
          if (state.stock === 'in' && st <= m.minStock) return false;
          if (state.stock === 'out' && st !== 0) return false;
          return true;
        });
      }

      function render() {
        const list = filtered();
        const pages = Math.max(1, Math.ceil(list.length / state.per));
        state.page = Math.min(state.page, pages);
        const slice = list.slice((state.page - 1) * state.per, state.page * state.per);
        $('#mmCount').textContent = `${list.length} of ${D.medicines.length} medicines · batch & expiry linked`;
        $('#mmBody').innerHTML = slice.map((m) => {
          const st = MF.stockOf(m.id);
          return `<tr>
            <td><div class="td-title">${MF.esc(m.name)}</div><div class="td-sub">${MF.esc(m.composition)}${m.rxRequired ? ' · <span class="rx-chip" style="font-size:.6rem">Rx</span>' : ''}</div></td>
            <td>${MF.esc(m.generic)}</td>
            <td class="text-2">${m.category}</td>
            <td>${MF.esc(m.manufacturer)}</td>
            <td class="num text-2">${m.hsn}</td>
            <td class="text-center">${m.gst}%</td>
            <td>${m.unit}</td>
            <td class="text-end num">${MF.fmt(m.mrp, 2)}</td>
            <td class="text-end num">${MF.fmt(m.retailRate ?? m.mrp, 2)}</td>
            <td class="text-end num">${MF.fmt(m.wholesaleRate, 2)}</td>
            <td class="text-end num fw-semibold">${MF.num(st)}</td>
            <td>${MF.stockBadge(m)}</td>
            <td class="text-end row-actions" style="white-space:nowrap">
              <button class="btn btn-icon btn-light-mf" data-a="view" data-id="${m.id}" title="View"><i class="bi bi-eye"></i></button>
              <button class="btn btn-icon btn-light-mf" data-a="edit" data-id="${m.id}" title="Edit"><i class="bi bi-pencil"></i></button>
              <button class="btn btn-icon btn-light-mf" data-a="stock" data-id="${m.id}" title="Stock"><i class="bi bi-box-seam"></i></button>
              <button class="btn btn-icon btn-light-mf" data-a="batches" data-id="${m.id}" title="Batches"><i class="bi bi-collection"></i></button>
              <button class="btn btn-icon btn-light-mf text-danger" data-a="del" data-id="${m.id}" title="Delete"><i class="bi bi-trash3"></i></button>
            </td>
          </tr>`;
        }).join('') || `<tr><td colspan="13"><div class="empty-state"><i class="bi bi-search"></i>No medicines match the current filters.</div></td></tr>`;

        $('#mmPageInfo').textContent = `Showing ${slice.length ? (state.page - 1) * state.per + 1 : 0}–${(state.page - 1) * state.per + slice.length} of ${list.length}`;
        $('#mmPager').innerHTML = Array.from({ length: pages }, (_, i) =>
          `<li class="page-item ${i + 1 === state.page ? 'active' : ''}"><button class="page-link" data-pg="${i + 1}">${i + 1}</button></li>`).join('');
        $('#mmPager').querySelectorAll('[data-pg]').forEach((b) => b.addEventListener('click', () => { state.page = +b.dataset.pg; render(); }));

        $('#mmBody').querySelectorAll('[data-a]').forEach((b) => b.addEventListener('click', () => {
          const m = MF.med(b.dataset.id), a = b.dataset.a;
          if (a === 'view') openView(m);
          if (a === 'edit') openForm(m);
          if (a === 'stock') openStock(m, false);
          if (a === 'batches') openStock(m, true);
          if (a === 'del') removeMed(m);
        }));
      }

      function openView(m) {
        const st = MF.stockOf(m.id);
        $('#mmViewBody').innerHTML = `
          <div class="d-flex align-items-start gap-3 mb-3">
            <div class="kpi-icon tone-primary" style="width:48px;height:48px;flex-basis:48px;font-size:1.3rem"><i class="bi bi-capsule"></i></div>
            <div>
              <h6 class="fw-bold mb-0">${MF.esc(m.name)} <span class="text-2 fw-normal">(${MF.esc(m.brandRef)})</span></h6>
              <div class="text-2 small">${MF.esc(m.composition)} · ${m.category}</div>
            </div>
            <div class="ms-auto">${MF.stockBadge(m)} ${m.rxRequired ? MF.badge('Schedule ' + m.schedule, 'purple') : MF.badge(m.schedule, 'secondary')}</div>
          </div>
          <div class="row g-3">
            ${[['Manufacturer', m.manufacturer], ['HSN Code', m.hsn], ['GST', m.gst + '%'], ['Unit', `${m.unit} · ${m.packSize}`],
               ['MRP', MF.fmt(m.mrp, 2)], ['Purchase Rate (PTR)', MF.fmt(m.purchaseRate, 2)], ['Wholesale Rate', MF.fmt(m.wholesaleRate, 2)],
               ['Current Stock', st + ' ' + m.unit], ['Minimum Stock', m.minStock], ['Reorder Level', m.reorderLevel],
               ['Rx Required', m.rxRequired ? 'Yes' : 'No'], ['Status', m.status]].map(([k, v]) =>
              `<div class="col-md-4 col-6"><div class="kpi-label">${k}</div><div class="fw-semibold">${v}</div></div>`).join('')}
          </div>`;
        new bootstrap.Modal($('#mmViewModal')).show();
      }

      function openForm(m) {
        editingId = m ? m.id : null;
        $('#mmFormTitle').textContent = m ? 'Edit Medicine — ' + m.name : 'Add Medicine';
        $('#fName').value = m?.name || ''; $('#fGeneric').value = m?.generic || ''; $('#fComp').value = m?.composition || '';
        $('#fCategory').value = m?.category || D.categories[0]; $('#fMfg').value = m?.manufacturer || D.manufacturers[0];
        $('#fHsn').value = m?.hsn || '3004'; $('#fUnit').value = m?.unit || 'Strip'; $('#fPack').value = m?.packSize || '';
        $('#fPackQty').value = m?.packQty || 1; $('#fSubUnit').value = m?.subUnit || ''; $('#fAllowLoose').checked = !!m?.allowLoose;
        $('#fGst').value = m ? String(m.gst) : '12'; $('#fSchedule').value = m?.schedule || 'OTC';
        $('#fMrp').value = m?.mrp ?? ''; $('#fPtr').value = m?.purchaseRate ?? ''; $('#fRetail').value = m?.mrp ?? '';
        $('#fWholesale').value = m?.wholesaleRate ?? ''; $('#fMin').value = m?.minStock ?? 50; $('#fReorder').value = m?.reorderLevel ?? 100;
        $('#fRx').checked = !!m?.rxRequired; $('#fActive').checked = m ? m.status === 'Active' : true;
        new bootstrap.Modal($('#mmFormModal')).show();
      }

      $('#mmFormSave').addEventListener('click', async () => {
        if (!$('#fName').value.trim()) { MF.toast('Medicine name is required.', 'err', 'Validation'); return; }
        if (!$('#fMrp').value) { MF.toast('MRP is required.', 'err', 'Validation'); return; }
        const payload = {
          name: $('#fName').value.trim(), generic: $('#fGeneric').value, composition: $('#fComp').value,
          category: $('#fCategory').value, manufacturer: $('#fMfg').value, hsn: $('#fHsn').value,
          unit: $('#fUnit').value, packSize: $('#fPack').value, gst: +$('#fGst').value, schedule: $('#fSchedule').value,
          packQty: +$('#fPackQty').value || 1, subUnit: $('#fSubUnit').value.trim(), allowLoose: $('#fAllowLoose').checked,
          mrp: +$('#fMrp').value, purchaseRate: +$('#fPtr').value || 0,
          wholesaleRate: +$('#fWholesale').value || (+$('#fMrp').value * 0.9), minStock: +$('#fMin').value,
          reorderLevel: +$('#fReorder').value, rxRequired: $('#fRx').checked,
          status: $('#fActive').checked ? 'Active' : 'Inactive'
        };
        if (MF.Api.live) {
          try {
            if (editingId) await MF.Api.put('medicines.php', { id: editingId, ...payload });
            else await MF.Api.post('medicines.php', payload);
            await MF.rehydrate();
          } catch (e) { MF.toast(e.message, 'err', 'Save failed'); return; }
        } else if (editingId) {
          Object.assign(MF.med(editingId), payload);
        } else {
          D.medicines.unshift({ id: 'M' + String(100 + D.medicines.length), brandRef: '', ...payload });
        }
        MF.toast(payload.name + (editingId ? ' updated successfully.' : ' added to the medicine master.'), 'success', editingId ? 'Medicine saved' : 'Medicine created');
        bootstrap.Modal.getInstance($('#mmFormModal')).hide();
        render();
      });

      function openStock(m, detailed) {
        const bs = MF.batchesOf(m.id);
        $('#mmStockTitle').textContent = (detailed ? 'Batches — ' : 'Stock Summary — ') + m.name;
        const total = MF.stockOf(m.id);
        const purchValue = bs.reduce((s, b) => s + b.qty * b.purchaseRate, 0);
        const mrpValue = bs.reduce((s, b) => s + b.qty * b.mrp, 0);
        $('#mmStockBody').innerHTML = `
          <div class="p-3 border-bottom d-flex flex-wrap gap-4">
            <div><div class="kpi-label">Total Qty</div><div class="fw-bold num">${MF.num(total)} ${m.unit}</div></div>
            <div><div class="kpi-label">Purchase Value</div><div class="fw-bold num">${MF.fmt(purchValue)}</div></div>
            <div><div class="kpi-label">MRP Value</div><div class="fw-bold num">${MF.fmt(mrpValue)}</div></div>
            <div class="ms-auto"><button class="btn btn-mf-soft btn-sm" id="mmStockAdjust"><i class="bi bi-sliders me-1"></i>Stock Adjustment</button></div>
          </div>
          <table class="table table-mf">
            <thead><tr><th>Batch No</th><th>Purchase Date</th><th>Expiry</th><th class="text-end">Qty</th><th class="text-end">Reserved</th><th class="text-end">Purchase Rate</th><th class="text-end">MRP</th><th>Status</th></tr></thead>
            <tbody>${bs.map((b) => `<tr>
              <td class="num td-title">${b.batchNo}</td><td class="num">${MF.fmtDate(b.purchaseDate)}</td><td class="num">${MF.fmtMonthYear(b.expiry)}</td>
              <td class="text-end num">${b.qty}</td><td class="text-end num text-2">${b.reserved}</td>
              <td class="text-end num">${MF.fmt(b.purchaseRate, 2)}</td><td class="text-end num">${MF.fmt(b.mrp, 2)}</td>
              <td>${MF.statusBadge(MF.batchStatus(b))}</td></tr>`).join('') || '<tr><td colspan="8"><div class="empty-state"><i class="bi bi-box-seam"></i>No batches recorded.</div></td></tr>'}
            </tbody></table>`;
        const modal = new bootstrap.Modal($('#mmStockModal'));
        modal.show();
        $('#mmStockBody').querySelector('#mmStockAdjust').addEventListener('click', () => {
          MF.toast('Opening adjustment slip for ' + m.name + ' (Stage 2 workflow)', 'info', 'Stock Adjustment');
        });
      }

      async function removeMed(m) {
        const ok = await MF.confirm({ title: `Delete ${m.name}?`, message: 'This removes the item from the master. Sales history is preserved (soft delete in production).', confirmText: 'Delete', tone: 'danger' });
        if (!ok) return;
        if (MF.Api.live) {
          try { await MF.Api.del('medicines.php?id=' + encodeURIComponent(m.id)); await MF.rehydrate(); }
          catch (e) { MF.toast(e.message, 'err', 'Delete failed'); return; }
        } else {
          D.medicines.splice(D.medicines.findIndex((x) => x.id === m.id), 1);
        }
        MF.toast(m.name + ' removed from master.', 'success', 'Deleted');
        render();
      }

      ['mmSearch', 'mmCategory', 'mmMfg', 'mmStock', 'mmSchedule'].forEach((id) =>
        $('#' + id).addEventListener('input', () => {
          state.search = $('#mmSearch').value; state.category = $('#mmCategory').value;
          state.mfg = $('#mmMfg').value; state.stock = $('#mmStock').value; state.schedule = $('#mmSchedule').value;
          state.page = 1; render();
        }));
      $('#mmClear').addEventListener('click', () => {
        ['mmSearch', 'mmCategory', 'mmMfg', 'mmStock', 'mmSchedule'].forEach((id) => $('#' + id).value = '');
        Object.assign(state, { search: '', category: '', mfg: '', stock: '', schedule: '', page: 1 });
        render();
      });
      $('#mmAddBtn').addEventListener('click', () => openForm(null));
      $('#mmExport').addEventListener('click', () => MF.exportCSV('medicines.csv',
        ['Name', 'Generic', 'Category', 'Manufacturer', 'HSN', 'GST%', 'Unit', 'MRP', 'Purchase', 'Wholesale', 'Stock'],
        filtered().map((m) => [m.name, m.generic, m.category, m.manufacturer, m.hsn, m.gst, m.unit, m.mrp, m.purchaseRate, m.wholesaleRate, MF.stockOf(m.id)])));

      document.addEventListener('DOMContentLoaded', async () => {
        await MF.boot();
        buildLookups();
        const p = new URLSearchParams(location.search);
        if (p.get('stock') === 'low') { $('#mmStock').value = 'low'; state.stock = 'low'; }
        render();
        if (p.get('action') === 'add') openForm(null);
      });
    })();
  </script>
</body>
</html>
