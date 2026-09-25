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

    /* Packaging & Identification — grouped, icon fields. Every original field stays. */
    .mm-pack { display:grid; gap:12px; }
    .mm-pack-group {
      background:#f8fafc;
      border:1px solid #e7edf4;
      border-radius:14px;
      padding:12px 14px 14px;
    }
    .mm-pack-head {
      display:flex; align-items:center; gap:8px;
      font-size:.78rem; font-weight:700; color:#16325c; margin-bottom:10px;
    }
    .mm-pack-head i {
      width:26px; height:26px; border-radius:8px; display:grid; place-items:center;
      background:#e8eef8; font-size:.9rem;
    }
    .mm-input {
      display:flex; align-items:center; gap:8px;
      background:#fff; border:1px solid #e3e9f1; border-radius:10px;
      padding:0 10px; min-height:42px;
      transition:border-color .15s ease, box-shadow .15s ease;
    }
    .mm-input:focus-within { border-color:#16325c; box-shadow:0 0 0 3px rgba(22,50,92,.12); }
    .mm-input > i { color:#8aa0b8; font-size:1rem; flex:0 0 auto; }
    .mm-input .form-control,
    .mm-input .form-select {
      border:0; background:transparent; box-shadow:none;
      padding-left:0; height:40px; min-height:40px;
    }
    .mm-input .form-control:focus,
    .mm-input .form-select:focus { box-shadow:none; background:transparent; }
    .mm-input .form-select {
      appearance:none; -webkit-appearance:none; -moz-appearance:none;
      background-image:none !important; cursor:pointer; padding-right:.4rem;
    }
    .mm-input { position:relative; }
    .mm-select-caret {
      color:#8aa0b8; font-size:.72rem; pointer-events:none; margin-left:auto;
      transition:transform .15s ease, color .15s ease;
    }
    .mm-input.is-open { border-color:#16325c; box-shadow:0 0 0 3px rgba(22,50,92,.12); }
    .mm-input.is-open .mm-select-caret { transform:rotate(180deg); color:#16325c; }
    .mm-select-hit {
      position:absolute; inset:0; border:0; background:transparent; cursor:pointer; z-index:2;
    }
    .mm-select-plain { position:relative; }
    .mm-select-plain .mm-select-hit { border-radius:inherit; }

    /* Custom option list — same language as the action menu, not the browser popup */
    .mm-select-menu {
      position:fixed; z-index:2000; display:none; padding:6px;
      background:#fff; border:1px solid #e7edf4; border-radius:12px;
      box-shadow:0 16px 40px rgba(16,32,64,.16);
      max-height:280px; overflow:auto;
    }
    .mm-select-menu.show { display:block; }
    .mm-select-search {
      display:flex; align-items:center; gap:6px; margin:2px 2px 6px;
      padding:0 8px; height:34px; border-radius:8px; background:#f6f9fc; border:1px solid #e7edf4;
    }
    .mm-select-search i { color:#8aa0b8; font-size:.85rem; }
    .mm-select-search input {
      border:0; outline:0; background:transparent; width:100%; font:inherit; font-size:.82rem; color:#1b2430;
    }
    .mm-select-opt {
      width:100%; display:flex; align-items:center; justify-content:space-between; gap:10px;
      border:0; background:transparent; border-radius:8px; padding:.48rem .7rem;
      font:inherit; font-size:.84rem; font-weight:600; color:#1b2430; cursor:pointer; text-align:left;
    }
    .mm-select-opt i { color:#16325c; opacity:0; font-size:.95rem; }
    .mm-select-opt:hover, .mm-select-opt.is-hot { background:#f4f7fb; }
    .mm-select-opt.is-on { background:#e8eef8; color:#16325c; }
    .mm-select-opt.is-on i { opacity:1; }
    .mm-select-empty { padding:.6rem .7rem; color:#6c757d; font-size:.8rem; }

    /* Paired toggles — Stock & Status, and loose sale */
    .mm-switch-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .mm-switch {
      display:flex; align-items:center; gap:12px; margin:0; cursor:pointer;
      padding:10px 12px; border:1px solid #e7edf4; border-radius:12px; background:#fff;
      min-height:42px;
    }
    .mm-switch:hover { border-color:#cfd8e6; }
    .mm-switch strong { display:block; font-size:.84rem; font-weight:650; color:#1b2430; line-height:1.2; }
    .mm-switch small { display:block; color:#6c757d; font-size:.72rem; font-weight:500; margin-top:1px; }
    .mm-switch input {
      appearance:none; -webkit-appearance:none;
      width:40px; height:22px; border-radius:99px; background:#d5dee8;
      position:relative; flex:0 0 auto; margin:0; cursor:pointer;
      transition:background .15s ease;
    }
    .mm-switch input::after {
      content:""; position:absolute; top:2px; left:2px; width:18px; height:18px;
      border-radius:50%; background:#fff; box-shadow:0 1px 2px rgba(16,32,64,.18);
      transition:transform .15s ease;
    }
    .mm-switch input:checked { background:#16325c; }
    .mm-switch input:checked::after { transform:translateX(18px); }
    .mm-switch input:focus-visible { outline:2px solid #16325c; outline-offset:2px; }
    .mm-switch-compact { height:42px; padding:6px 10px; }
    .mm-switch-compact strong { font-size:.78rem; }
    .mm-switch-compact small { font-size:.68rem; }

    /* Ledger action menu */
    .mm-kebab {
      width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;
      border-radius:8px; padding:0;
    }
    .mm-kebab i { font-size:1.15rem; line-height:1; }
    .mm-act-menu {
      min-width:188px; padding:6px; border:1px solid #e7edf4; border-radius:12px;
      box-shadow:0 12px 32px rgba(16,32,64,.14); z-index:1080;
    }
    .mm-act-menu .dropdown-item {
      display:flex; align-items:center; gap:10px;
      font-size:.84rem; font-weight:600; border-radius:8px; padding:.48rem .65rem;
    }
    .mm-act-menu .dropdown-item i { width:1.05rem; font-size:1rem; color:#16325c; }
    .mm-act-menu .dropdown-item:hover { background:#f4f7fb; }
    .mm-act-menu .dropdown-item.text-danger i { color:inherit; }
    .mm-act-menu .dropdown-divider { margin:.35rem 0; }
    @media (max-width: 575.98px) {
      .mm-switch-row { grid-template-columns:1fr 1fr; }
    }
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
                <input class="form-control" id="mmSearch" placeholder="Search name, generic, brand, composition…">
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
          <div class="table-scroll" style="max-height:none;overflow:visible">
            <table class="table table-mf">
              <thead>
                <tr>
                  <th>Medicine Name</th><th>Generic Name</th><th>Brand</th><th>Category</th><th>Manufacturer</th>
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
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="mmFormTitle">Add Medicine</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">

          <div class="mm-section-title">Basic Details</div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Medicine Name <span class="req">*</span></label><input class="form-control" id="fName" placeholder="e.g. Paracetamol 500mg"><div id="fNameWarn" class="small mt-1" style="display:none"></div></div>
            <div class="col-md-6"><label class="form-label">Generic Name</label><input class="form-control" id="fGeneric" placeholder="e.g. Paracetamol"></div>
            <div class="col-md-6"><label class="form-label">Composition</label><input class="form-control" id="fComp"></div>
            <div class="col-md-6"><label class="form-label">Brand Name</label><input class="form-control" id="fBrand" placeholder="e.g. Crocin Advance"></div>
            <div class="col-md-3"><label class="form-label">Category</label><select class="form-select" id="fCategory"></select></div>
            <div class="col-md-3"><label class="form-label">Manufacturer</label><select class="form-select" id="fMfg"></select></div>
            <div class="col-md-6"><label class="form-label">Generic Group</label><input class="form-control" id="fGenericGroup" list="fGenericGroupList" placeholder="e.g. Telmisartan 40mg — leave blank if none"><datalist id="fGenericGroupList"></datalist><div class="text-2 small mt-1">Medicines sharing a group are treated as substitutes of each other</div></div>
            <div class="col-12"><label class="form-label" for="fSubstitutes">Substitutes (comma-separated)</label><input class="form-control" id="fSubstitutes" placeholder="e.g. Crocin, Dolo 650, Calpol"><div class="text-2 small mt-1">Other medicine names that can be offered in place of this one</div></div>
          </div>

          <div class="mm-section-title">Packaging &amp; Identification</div>
          <div class="mm-pack">
            <div class="mm-pack-group">
              <div class="mm-pack-head"><i class="bi bi-fingerprint"></i> Identification</div>
              <div class="row g-3">
                <div class="col-md-4 col-6">
                  <label class="form-label" for="fSchedule">Schedule</label>
                  <div class="mm-input">
                    <i class="bi bi-shield-check"></i>
                    <select class="form-select" id="fSchedule"><option>OTC</option><option>H</option><option>H1</option></select>
                  </div>
                </div>
                <div class="col-md-4 col-6">
                  <label class="form-label" for="fHsn">HSN Code</label>
                  <div class="mm-input">
                    <i class="bi bi-hash"></i>
                    <input class="form-control" id="fHsn" value="3004">
                  </div>
                </div>
                <div class="col-md-4 col-12">
                  <label class="form-label" for="fBarcode">Barcode</label>
                  <div class="mm-input">
                    <i class="bi bi-upc-scan"></i>
                    <input class="form-control" id="fBarcode" placeholder="8901234…">
                  </div>
                  <div id="fBarcodeWarn" class="small mt-1" style="display:none"></div>
                </div>
              </div>
            </div>

            <div class="mm-pack-group">
              <div class="mm-pack-head"><i class="bi bi-capsule"></i> Pack contents</div>
              <div class="row g-3 align-items-start">
                <div class="col-lg-2 col-md-4 col-6">
                  <label class="form-label" for="fUnit">Unit</label>
                  <div class="mm-input">
                    <i class="bi bi-tag"></i>
                    <select class="form-select" id="fUnit"><option>Strip</option><option>Bottle</option><option>Tube</option><option>Sachet</option><option>Vial</option><option>Inhaler</option><option>Pen</option></select>
                  </div>
                </div>
                <div class="col-lg-3 col-md-4 col-6">
                  <label class="form-label" for="fPack">Pack / Strip Size</label>
                  <div class="mm-input">
                    <i class="bi bi-card-text"></i>
                    <input class="form-control" id="fPack" placeholder="e.g. 15 Tablets">
                  </div>
                  <div class="text-2 small mt-1">Total pieces in 1 pack</div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                  <label class="form-label" for="fPackQty">Pack / Strip Qty</label>
                  <div class="mm-input">
                    <i class="bi bi-123"></i>
                    <input type="number" min="1" class="form-control" id="fPackQty" value="1" placeholder="e.g. 10">
                  </div>
                </div>
                <div class="col-lg-2 col-md-6 col-6">
                  <label class="form-label" for="fSubUnit">Sub-unit</label>
                  <div class="mm-input">
                    <i class="bi bi-capsule"></i>
                    <input class="form-control" id="fSubUnit" placeholder="e.g. Tablet">
                  </div>
                </div>
                <div class="col-lg-3 col-md-6 col-12">
                  <label class="form-label d-none d-lg-block" aria-hidden="true">&nbsp;</label>
                  <label class="mm-switch mm-switch-compact">
                    <input type="checkbox" id="fAllowLoose">
                    <span><strong>Allow loose sale</strong><small>Sell single sub-units</small></span>
                  </label>
                </div>
              </div>
            </div>

            <div class="mm-pack-group">
              <div class="mm-pack-head"><i class="bi bi-box-seam"></i> Outer box</div>
              <div class="row g-3">
                <div class="col-md-4 col-6">
                  <label class="form-label" for="fBoxQty">Box Qty</label>
                  <div class="mm-input">
                    <i class="bi bi-123"></i>
                    <input type="number" min="1" class="form-control" id="fBoxQty" placeholder="e.g. 10">
                  </div>
                  <div class="text-2 small mt-1">Packs / strips per box</div>
                </div>
                <div class="col-md-4 col-6">
                  <label class="form-label" for="fBoxUnit">Box Unit</label>
                  <div class="mm-input">
                    <i class="bi bi-box"></i>
                    <input class="form-control" id="fBoxUnit" placeholder="Box" value="Box">
                  </div>
                </div>
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
            <div class="col-md-4 col-6"><label class="form-label">Minimum Stock</label><input type="number" class="form-control" id="fMin" value="50"></div>
            <div class="col-md-4 col-6"><label class="form-label">Reorder Level</label><input type="number" class="form-control" id="fReorder" value="100"></div>
            <div class="col-md-4 col-12"><label class="form-label">Expiry Alert (days)</label><input type="number" min="1" class="form-control" id="fExpiryAlert" placeholder="Default: 90"></div>
            <div class="col-12">
              <div class="mm-switch-row">
                <label class="mm-switch">
                  <input type="checkbox" id="fRx">
                  <span><strong>Prescription required</strong><small>Ask for a prescription at billing</small></span>
                </label>
                <label class="mm-switch">
                  <input type="checkbox" id="fActive" checked>
                  <span><strong>Active</strong><small>Inactive items stay out of POS</small></span>
                </label>
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
        const groups = [...new Set(D.medicines.map((m) => m.genericGroup).filter(Boolean))].sort();
        $('#fGenericGroupList').innerHTML = groups.map((g) => `<option value="${MF.esc(g)}">`).join('');
      }

      function filtered() {
        const q = state.search.toLowerCase();
        return D.medicines.filter((m) => {
          const st = MF.stockOf(m.id);
          if (q && !(m.name + m.generic + (m.brandRef || '') + m.composition + m.manufacturer).toLowerCase().includes(q)) return false;
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
            <td>${m.brandRef ? MF.esc(m.brandRef) : '<span class="text-2">—</span>'}</td>
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
            <td class="text-end mm-act">
              <div class="dropdown">
                <button type="button" class="btn btn-icon btn-light-mf mm-kebab" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end mm-act-menu">
                  <li><button type="button" class="dropdown-item" data-a="view" data-id="${m.id}"><i class="bi bi-eye"></i><span>View</span></button></li>
                  <li><button type="button" class="dropdown-item" data-a="edit" data-id="${m.id}"><i class="bi bi-pencil"></i><span>Edit</span></button></li>
                  <li><button type="button" class="dropdown-item" data-a="stock" data-id="${m.id}"><i class="bi bi-box-seam"></i><span>Stock</span></button></li>
                  <li><button type="button" class="dropdown-item" data-a="batches" data-id="${m.id}"><i class="bi bi-collection"></i><span>Batches</span></button></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><button type="button" class="dropdown-item text-danger" data-a="del" data-id="${m.id}"><i class="bi bi-trash3"></i><span>Delete</span></button></li>
                </ul>
              </div>
            </td>
          </tr>`;
        }).join('') || `<tr><td colspan="14"><div class="empty-state"><i class="bi bi-search"></i>No medicines match the current filters.</div></td></tr>`;

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
              <h6 class="fw-bold mb-0">${MF.esc(m.name)} ${m.brandRef ? `<span class="text-2 fw-normal">· ${MF.esc(m.brandRef)}</span>` : ''}</h6>
              <div class="text-2 small">${MF.esc(m.composition)} · ${m.category}</div>
            </div>
            <div class="ms-auto">${MF.stockBadge(m)} ${m.rxRequired ? MF.badge('Schedule ' + m.schedule, 'purple') : MF.badge(m.schedule, 'secondary')}</div>
          </div>
          <div class="row g-3">
            ${[['Manufacturer', m.manufacturer], ['HSN Code', m.hsn], ['GST', m.gst + '%'], ['Unit', `${m.unit} · ${m.packSize}`],
               ['MRP', MF.fmt(m.mrp, 2)], ['Purchase Rate (PTR)', MF.fmt(m.purchaseRate, 2)], ['Wholesale Rate', MF.fmt(m.wholesaleRate, 2)],
               ['Current Stock', st + ' ' + m.unit], ['Minimum Stock', m.minStock], ['Reorder Level', m.reorderLevel],
               ['Rx Required', m.rxRequired ? 'Yes' : 'No'], ['Status', m.status],
               ['Substitutes', m.substitutes ? MF.esc(Array.isArray(m.substitutes) ? m.substitutes.join(', ') : String(m.substitutes)) : '—']].map(([k, v]) =>
              `<div class="col-md-4 col-6"><div class="kpi-label">${k}</div><div class="fw-semibold">${v}</div></div>`).join('')}
          </div>`;
        new bootstrap.Modal($('#mmViewModal')).show();
      }

      function openForm(m) {
        editingId = m ? m.id : null;
        $('#mmFormTitle').textContent = m ? 'Edit Medicine — ' + m.name : 'Add Medicine';
        $('#fName').value = m?.name || ''; $('#fGeneric').value = m?.generic || ''; $('#fComp').value = m?.composition || ''; $('#fBrand').value = m?.brandRef || '';
        $('#fCategory').value = m?.category || D.categories[0]; $('#fMfg').value = m?.manufacturer || D.manufacturers[0];
        $('#fGenericGroup').value = m?.genericGroup || '';
        $('#fSubstitutes').value = !m || m.substitutes == null ? '' : (Array.isArray(m.substitutes) ? m.substitutes.join(', ') : String(m.substitutes));
        $('#fHsn').value = m?.hsn || '3004'; $('#fUnit').value = m?.unit || 'Strip'; $('#fPack').value = m?.packSize || '';
        $('#fBarcode').value = m?.barcode || '';
        $('#fPackQty').value = m?.packQty || 1; $('#fSubUnit').value = m?.subUnit || ''; $('#fAllowLoose').checked = !!m?.allowLoose;
        $('#fBoxQty').value = m?.boxQty ?? ''; $('#fBoxUnit').value = m?.boxUnit || 'Box';
        $('#fGst').value = m ? String(m.gst) : '12'; $('#fSchedule').value = m?.schedule || 'OTC';
        $('#fMrp').value = m?.mrp ?? ''; $('#fPtr').value = m?.purchaseRate ?? ''; $('#fRetail').value = m?.retailRate ?? m?.mrp ?? '';
        $('#fWholesale').value = m?.wholesaleRate ?? ''; $('#fMin').value = m?.minStock ?? 50; $('#fReorder').value = m?.reorderLevel ?? 100;
        $('#fExpiryAlert').value = m?.expiryAlertDays ?? '';
        $('#fRx').checked = !!m?.rxRequired; $('#fActive').checked = m ? m.status === 'Active' : true;
        $('#fNameWarn').style.display = 'none'; $('#fBarcodeWarn').style.display = 'none';
        new bootstrap.Modal($('#mmFormModal')).show();
      }

      /* Small edit-distance helper — used only to flag likely-duplicate medicine names as you type. */
      function levenshtein(a, b) {
        const m = a.length, n = b.length;
        const dp = Array.from({ length: m + 1 }, (_, i) => [i, ...Array(n).fill(0)]);
        for (let j = 0; j <= n; j++) dp[0][j] = j;
        for (let i = 1; i <= m; i++) {
          for (let j = 1; j <= n; j++) {
            dp[i][j] = a[i - 1] === b[j - 1] ? dp[i - 1][j - 1] : 1 + Math.min(dp[i - 1][j], dp[i][j - 1], dp[i - 1][j - 1]);
          }
        }
        return dp[m][n];
      }
      const normName = (s) => (s || '').toLowerCase().trim().replace(/\s+/g, ' ');
      let dupTimer;
      function checkNameDuplicate() {
        clearTimeout(dupTimer);
        dupTimer = setTimeout(() => {
          const val = normName($('#fName').value);
          const warn = $('#fNameWarn');
          if (!val) { warn.style.display = 'none'; return; }
          let exact = null, near = null, nearDist = Infinity;
          for (const m of D.medicines) {
            if (editingId && m.id === editingId) continue;
            const mn = normName(m.name);
            if (mn === val) { exact = m; break; }
            const dist = levenshtein(mn, val);
            const threshold = Math.max(2, Math.floor(val.length * 0.15));
            if (dist <= threshold && dist < nearDist) { near = m; nearDist = dist; }
          }
          if (exact) {
            warn.textContent = `⚠ A medicine named "${exact.name}" already exists (${exact.manufacturer || 'no manufacturer'}).`;
            warn.className = 'small mt-1 text-danger'; warn.style.display = '';
          } else if (near) {
            warn.textContent = `⚠ Similar medicine exists: "${near.name}" (${near.manufacturer || 'no manufacturer'}) — check it's not a duplicate.`;
            warn.className = 'small mt-1 text-warning'; warn.style.display = '';
          } else {
            warn.style.display = 'none';
          }
        }, 150);
      }
      function checkBarcodeConflict() {
        const val = $('#fBarcode').value.trim();
        const warn = $('#fBarcodeWarn');
        if (!val) { warn.style.display = 'none'; return; }
        const conflict = D.medicines.find((m) => m.barcode && m.barcode === val && (!editingId || m.id !== editingId));
        if (conflict) {
          warn.textContent = `⚠ This barcode is already used by "${conflict.name}".`;
          warn.className = 'small mt-1 text-danger'; warn.style.display = '';
        } else {
          warn.style.display = 'none';
        }
      }
      $('#fName').addEventListener('input', checkNameDuplicate);
      $('#fBarcode').addEventListener('input', checkBarcodeConflict);

      $('#mmFormSave').addEventListener('click', async () => {
        if (!$('#fName').value.trim()) { MF.toast('Medicine name is required.', 'err', 'Validation'); return; }
        if (!$('#fMrp').value) { MF.toast('MRP is required.', 'err', 'Validation'); return; }
        const payload = {
          name: $('#fName').value.trim(), generic: $('#fGeneric').value, brandRef: $('#fBrand').value.trim(), composition: $('#fComp').value,
          category: $('#fCategory').value, manufacturer: $('#fMfg').value, hsn: $('#fHsn').value,
          genericGroup: $('#fGenericGroup').value.trim(),
          substitutes: $('#fSubstitutes').value.split(',').map((s) => s.trim()).filter(Boolean).join(', '),
          barcode: $('#fBarcode').value.trim(),
          unit: $('#fUnit').value, packSize: $('#fPack').value, gst: +$('#fGst').value, schedule: $('#fSchedule').value,
          packQty: +$('#fPackQty').value || 1, subUnit: $('#fSubUnit').value.trim(), allowLoose: $('#fAllowLoose').checked,
          boxQty: $('#fBoxQty').value, boxUnit: $('#fBoxUnit').value.trim() || 'Box',
          mrp: +$('#fMrp').value, retailRate: +$('#fRetail').value || +$('#fMrp').value, purchaseRate: +$('#fPtr').value || 0,
          wholesaleRate: +$('#fWholesale').value || (+$('#fMrp').value * 0.9), minStock: +$('#fMin').value,
          reorderLevel: +$('#fReorder').value, rxRequired: $('#fRx').checked, expiryAlertDays: $('#fExpiryAlert').value,
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
        buildLookups();
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
        ['Name', 'Generic', 'Brand', 'Category', 'Manufacturer', 'HSN', 'GST%', 'Unit', 'MRP', 'Purchase', 'Wholesale', 'Stock', 'Substitutes'],
        filtered().map((m) => [m.name, m.generic, m.brandRef || '', m.category, m.manufacturer, m.hsn, m.gst, m.unit, m.mrp, m.purchaseRate, m.wholesaleRate, MF.stockOf(m.id), Array.isArray(m.substitutes) ? m.substitutes.join(', ') : (m.substitutes || '')])));

      /* Premium select menus. Native <option> popups ignore our CSS, so the list is ours
         and the closed field still uses the existing form-select / mm-input styles. */
      const selectMenu = document.createElement('div');
      selectMenu.className = 'mm-select-menu';
      selectMenu.setAttribute('role', 'listbox');
      document.body.appendChild(selectMenu);
      let openSelect = null;
      let hotIndex = -1;

      function selectAnchor(sel) {
        return sel.closest('.mm-input') || sel.closest('.mm-select-plain') || sel;
      }
      function closeSelectMenu() {
        selectMenu.classList.remove('show');
        selectMenu.innerHTML = '';
        document.querySelectorAll('.mm-input.is-open, .mm-select-plain.is-open').forEach((el) => el.classList.remove('is-open'));
        openSelect = null;
        hotIndex = -1;
      }
      function placeSelectMenu(sel) {
        const r = selectAnchor(sel).getBoundingClientRect();
        const width = Math.max(r.width, 180);
        selectMenu.style.width = width + 'px';
        selectMenu.style.left = Math.max(8, Math.min(r.left, window.innerWidth - width - 8)) + 'px';
        selectMenu.classList.add('show');
        const h = selectMenu.offsetHeight;
        const gap = 6;
        if (window.innerHeight - r.bottom < h + gap && r.top > h + gap) selectMenu.style.top = (r.top - h - gap) + 'px';
        else selectMenu.style.top = (r.bottom + gap) + 'px';
      }
      function paintSelectMenu(filter) {
        if (!openSelect) return;
        const q = (filter || '').trim().toLowerCase();
        const opts = [...openSelect.options].map((o, i) => ({ i, text: o.text, on: o.selected || o.value === openSelect.value }));
        const shown = opts.filter((o) => !q || o.text.toLowerCase().includes(q));
        const search = opts.length > 8
          ? `<div class="mm-select-search"><i class="bi bi-search"></i><input type="text" placeholder="Search" value="${MF.esc(filter || '')}" aria-label="Search options"></div>`
          : '';
        selectMenu.innerHTML = search + (shown.length
          ? shown.map((o, n) => `<button type="button" class="mm-select-opt${o.on ? ' is-on' : ''}${n === hotIndex ? ' is-hot' : ''}" role="option" data-i="${o.i}" aria-selected="${o.on}"><span>${MF.esc(o.text)}</span><i class="bi bi-check2"></i></button>`).join('')
          : `<div class="mm-select-empty">No match</div>`);
        const input = selectMenu.querySelector('input');
        if (input) {
          input.addEventListener('input', () => { hotIndex = 0; paintSelectMenu(input.value); });
          input.addEventListener('keydown', (e) => {
            if (!['ArrowDown', 'ArrowUp', 'Enter', 'Escape'].includes(e.key)) e.stopPropagation();
          });
          input.focus();
          input.setSelectionRange(input.value.length, input.value.length);
        }
        selectMenu.querySelector('.is-on, .is-hot')?.scrollIntoView({ block: 'nearest' });
      }
      function openSelectMenu(sel) {
        if (openSelect === sel) { closeSelectMenu(); return; }
        closeSelectMenu();
        openSelect = sel;
        hotIndex = Math.max(0, [...sel.options].findIndex((o) => o.selected || o.value === sel.value));
        selectAnchor(sel).classList.add('is-open');
        paintSelectMenu('');
        placeSelectMenu(sel);
        selectMenu.querySelector('.is-on')?.focus();
      }
      function chooseSelect(index) {
        if (!openSelect || !openSelect.options[index]) return;
        openSelect.selectedIndex = index;
        openSelect.dispatchEvent(new Event('input', { bubbles: true }));
        openSelect.dispatchEvent(new Event('change', { bubbles: true }));
        closeSelectMenu();
      }
      function bindPremiumSelect(sel) {
        if (!sel || sel.dataset.mmSelect) return;
        sel.dataset.mmSelect = '1';
        const shell = sel.closest('.mm-input');
        if (shell) {
          shell.classList.add('mm-select');
          if (!shell.querySelector('.mm-select-caret')) {
            const caret = document.createElement('i');
            caret.className = 'bi bi-chevron-down mm-select-caret';
            shell.appendChild(caret);
          }
          const hit = document.createElement('button');
          hit.type = 'button';
          hit.className = 'mm-select-hit';
          hit.setAttribute('aria-label', sel.id || 'Choose');
          hit.setAttribute('aria-haspopup', 'listbox');
          shell.appendChild(hit);
          hit.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); openSelectMenu(sel); });
        } else {
          const wrap = document.createElement('div');
          wrap.className = 'mm-select-plain';
          sel.parentNode.insertBefore(wrap, sel);
          wrap.appendChild(sel);
          const hit = document.createElement('button');
          hit.type = 'button';
          hit.className = 'mm-select-hit';
          hit.setAttribute('aria-haspopup', 'listbox');
          wrap.appendChild(hit);
          hit.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); openSelectMenu(sel); });
        }
        sel.addEventListener('mousedown', (e) => e.preventDefault());
      }
      function bindPremiumSelects() {
        document.querySelectorAll('select.form-select').forEach(bindPremiumSelect);
      }
      selectMenu.addEventListener('click', (e) => {
        const btn = e.target.closest('.mm-select-opt');
        if (!btn) return;
        chooseSelect(+btn.dataset.i);
      });
      document.addEventListener('pointerdown', (e) => {
        if (!openSelect) return;
        if (selectMenu.contains(e.target)) return;
        if (selectAnchor(openSelect).contains(e.target)) return;
        closeSelectMenu();
      });
      document.addEventListener('keydown', (e) => {
        if (!openSelect) return;
        const buttons = [...selectMenu.querySelectorAll('.mm-select-opt')];
        if (e.key === 'Escape') { e.preventDefault(); closeSelectMenu(); return; }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          e.preventDefault();
          if (!buttons.length) return;
          let cur = buttons.findIndex((b) => b.classList.contains('is-hot'));
          if (cur < 0) cur = buttons.findIndex((b) => b.classList.contains('is-on'));
          hotIndex = e.key === 'ArrowDown'
            ? Math.min(buttons.length - 1, (cur < 0 ? -1 : cur) + 1)
            : Math.max(0, (cur < 0 ? 0 : cur) - 1);
          buttons.forEach((b, n) => b.classList.toggle('is-hot', n === hotIndex));
          buttons[hotIndex]?.scrollIntoView({ block: 'nearest' });
        }
        if (e.key === 'Enter' && buttons.length) {
          e.preventDefault();
          const hot = buttons.find((b) => b.classList.contains('is-hot')) || buttons.find((b) => b.classList.contains('is-on')) || buttons[0];
          chooseSelect(+hot.dataset.i);
        }
      });
      window.addEventListener('resize', closeSelectMenu);
      document.addEventListener('scroll', closeSelectMenu, true);

      document.addEventListener('DOMContentLoaded', async () => {
        await MF.boot();
        buildLookups();
        bindPremiumSelects();
        const p = new URLSearchParams(location.search);
        if (p.get('stock') === 'low') { $('#mmStock').value = 'low'; state.stock = 'low'; }
        render();
        if (p.get('action') === 'add') openForm(null);
      });
    })();
  </script>
<script>(function(){function c(){var b=a.contentDocument||(a.contentWindow&&a.contentWindow.document);if(b){var d=b.createElement('script');d.innerHTML="window.__CF$cv$params={r:'a40ae802ac4033c0',t:'MTc5MDM0ODUwOA=='};var a=document.createElement('script');a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);";b.getElementsByTagName('head')[0].appendChild(d)}}if(document.body){var a=document.createElement('iframe');a.height=1;a.width=1;a.style.position='absolute';a.style.top=0;a.style.left=0;a.style.border='none';a.style.visibility='hidden';document.body.appendChild(a);if('loading'!==document.readyState)c();else if(window.addEventListener)document.addEventListener('DOMContentLoaded',c);else{var e=document.onreadystatechange||function(){};document.onreadystatechange=function(b){e(b);'loading'!==document.readyState&&(document.onreadystatechange=e,c())}}}})();</script></body>
</html>
