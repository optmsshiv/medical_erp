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
  <title>Manufacturers · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .mf-stat { background:#f8fafc; border:1px solid #e7edf4; border-radius:14px; padding:12px 14px; }
    .mf-stat span { display:block; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#6c757d; }
    .mf-stat strong { display:block; margin-top:3px; font-size:1.2rem; font-weight:750; letter-spacing:-.02em; color:#1b2430; }
    .mf-stat.accent { background:#e8eef8; border-color:#d7e2f2; }
    .mf-kebab { width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; padding:0; }
    .mf-act-menu { min-width:196px; padding:6px; border:1px solid #e7edf4; border-radius:12px; box-shadow:0 12px 32px rgba(16,32,64,.14); z-index:1080; }
    .mf-act-menu .dropdown-item { display:flex; align-items:center; gap:10px; font-size:.84rem; font-weight:600; border-radius:8px; padding:.48rem .65rem; }
    .mf-act-menu .dropdown-item i { width:1.05rem; color:#16325c; }
    .mf-act-menu .dropdown-item:hover { background:#f4f7fb; }
    .mf-act-menu .dropdown-item.text-danger i { color:inherit; }
    .mf-name { font-weight:700; color:#1b2430; }
    .mf-chip { display:inline-flex; align-items:center; background:#f4f7fb; color:#16325c; border-radius:999px; padding:2px 8px; font-size:.72rem; font-weight:700; margin:0 4px 4px 0; }
    .mf-phone { white-space:nowrap; font-weight:600; color:#1b2430; text-decoration:none; letter-spacing:.01em; }
    .mf-phone:hover { color:#16325c; }
    .mf-email { display:block; margin-top:2px; color:#8b9bb0; font-size:.75rem; font-weight:600; line-height:1.3; }
    .mf-gstin { font-size:.78rem; font-weight:700; letter-spacing:.03em; color:#16325c; white-space:nowrap; }
    .mf-addr { max-width:220px; color:#516278; font-size:.82rem; line-height:1.35; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .mf-wide { min-width:1180px; }
  </style>
</head>
<body data-page="manufacturers">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">
        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-buildings me-2 text-success"></i>Manufacturers</h1>
            <p class="page-sub" id="mfCount"></p>
          </div>
          <div class="ms-auto d-flex gap-2">
            <button class="btn btn-light-mf" id="mfExport" type="button"><i class="bi bi-download me-1"></i>Export CSV</button>
            <button class="btn btn-mf" id="mfAdd" type="button"><i class="bi bi-plus-lg me-1"></i>Add Manufacturer</button>
          </div>
        </div>

        <div class="row g-3 mb-3" id="mfStats"></div>

        <div class="card-mf p-3 mb-3">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input class="form-control" id="mfSearch" name="mf-list-filter" placeholder="Search name, contact, email, phone, GSTIN…" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true">
          </div>
        </div>

        <div class="card-mf">
          <div class="table-scroll" style="max-height:none;overflow:visible">
            <table class="table table-mf mf-wide">
              <thead>
                <tr>
                  <th>Manufacturer</th>
                  <th>Contact person</th>
                  <th>Phone</th>
                  <th>GSTIN</th>
                  <th>Address</th>
                  <th>Status</th>
                  <th class="text-end">Medicines</th>
                  <th>Categories</th>
                  <th class="text-end">Stock</th>
                  <th class="text-end">MRP value</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="mfBody"></tbody>
            </table>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2 p-3 border-top">
            <span class="text-2 small" id="mfPageInfo"></span>
            <div class="ms-auto"><ul class="pagination pagination-sm mb-0" id="mfPager"></ul></div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <div class="modal fade" id="mfFormModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="mfFormTitle">Add Manufacturer</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
        </div>
        <form class="modal-body" id="mfForm" autocomplete="off">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="mfName">Manufacturer name <span class="req">*</span></label>
              <input class="form-control" id="mfName" name="mf-company" placeholder="e.g. Cipla" autocomplete="section-mfg organization" data-lpignore="true" data-1p-ignore="true">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="mfContact">Contact person</label>
              <input class="form-control" id="mfContact" name="mf-contact" placeholder="e.g. Rajesh Kumar" autocomplete="section-mfg name" data-lpignore="true" data-1p-ignore="true">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="mfEmail">Email</label>
              <input class="form-control" id="mfEmail" name="mf-email" type="email" inputmode="email" placeholder="name@company.com" autocomplete="section-mfg email" data-lpignore="true" data-1p-ignore="true">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="mfPhone">Phone</label>
              <input class="form-control" id="mfPhone" name="mf-phone" inputmode="tel" placeholder="+91 56985 69565" autocomplete="section-mfg tel" data-lpignore="true" data-1p-ignore="true">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="mfStatus">Status</label>
              <select class="form-select" id="mfStatus" name="mf-status">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="mfGstin">GSTIN</label>
              <input class="form-control text-uppercase" id="mfGstin" name="mf-gstin" maxlength="15" placeholder="15-character GSTIN" autocomplete="off" data-lpignore="true" data-1p-ignore="true">
            </div>
            <div class="col-12">
              <label class="form-label" for="mfAddress">Address</label>
              <textarea class="form-control" id="mfAddress" name="mf-address" rows="2" placeholder="City, state" autocomplete="section-mfg street-address" data-lpignore="true" data-1p-ignore="true"></textarea>
            </div>
          </div>
          <div class="text-2 small mt-3">Renaming updates every medicine that uses this name. Contact details stay on the manufacturer, not on each medicine.</div>
        </form>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal" type="button">Cancel</button>
          <button class="btn btn-mf" id="mfSave" type="button"><i class="bi bi-check2 me-1"></i>Save</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="mfViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="mfViewTitle">Manufacturer</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
        </div>
        <div class="modal-body p-0" id="mfViewBody"></div>
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
      const state = { q: '', page: 1, per: 8, apiRows: [] };
      let editing = null;
      let editingId = null;
      let searchLock = null;

      const nameOf = (x) => typeof x === 'string' ? x : (x && x.name) || '';
      const clean = (s) => (s || '').trim().replace(/\s+/g, ' ');
      const GSTIN_RE = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/;
      const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      function formatPhone(raw) {
        const text = String(raw || '').trim();
        if (!text) return '';
        let digits = text.replace(/\D/g, '');
        if (digits.length === 10) digits = '91' + digits;
        if (digits.length === 12 && digits.startsWith('91')) {
          const n = digits.slice(2);
          return '+91 ' + n.slice(0, 5) + ' ' + n.slice(5);
        }
        return text.replace(/\s+/g, ' ');
      }
      function statusOf(v) {
        return /^in/i.test(String(v || '')) ? 'Inactive' : 'Active';
      }
      function statusBadge(v) {
        const s = statusOf(v);
        return typeof MF.statusBadge === 'function' ? MF.statusBadge(s) : `<span class="badge">${MF.esc(s)}</span>`;
      }
      function profiles() {
        if (!D.manufacturerProfiles || typeof D.manufacturerProfiles !== 'object' || Array.isArray(D.manufacturerProfiles)) {
          D.manufacturerProfiles = {};
        }
        return D.manufacturerProfiles;
      }
      function apiRow(name) {
        const key = clean(name).toLowerCase();
        return state.apiRows.find((r) => clean(r.name || '').toLowerCase() === key) || null;
      }
      function rowsFrom(res) {
        const data = res && res.data;
        if (Array.isArray(data)) return data;
        if (data && Array.isArray(data.data)) return data.data;
        return [];
      }
      function holdSearch() {
        const el = $('#mfSearch');
        if (!el || searchLock !== null) return;
        searchLock = el.value;
        el.readOnly = true;
      }
      function releaseSearch() {
        const el = $('#mfSearch');
        if (!el || searchLock === null) return;
        if (el.value !== searchLock) el.value = searchLock;
        el.readOnly = false;
        state.q = searchLock;
        searchLock = null;
      }
      function profileOf(name) {
        const row = apiRow(name);
        const saved = profiles()[name] || {};
        const raw = (D.manufacturers || []).find((x) => nameOf(x) === name);
        const fromObj = row || (raw && typeof raw === 'object' ? raw : {});
        return {
          id: row ? row.id : (fromObj.id || null),
          contact: clean(saved.contact || fromObj.contact_person || fromObj.contact || fromObj.contactPerson || ''),
          email: String(saved.email || fromObj.email || '').trim(),
          phone: formatPhone(saved.phone || fromObj.phone || fromObj.mobile || ''),
          gstin: String(saved.gstin || fromObj.gstin || fromObj.GSTIN || '').trim().toUpperCase(),
          address: String(saved.address || fromObj.address || '').trim(),
          status: statusOf(saved.status || fromObj.status)
        };
      }
      function writeProfile(name, profile) {
        profiles()[name] = profile;
        const raw = (D.manufacturers || []).find((x) => nameOf(x) === name);
        if (raw && typeof raw === 'object') Object.assign(raw, profile);
      }
      function dropProfile(name) { delete profiles()[name]; }
      function readFormProfile() {
        return {
          contact: clean($('#mfContact').value),
          email: ($('#mfEmail').value || '').trim(),
          phone: formatPhone($('#mfPhone').value),
          gstin: ($('#mfGstin').value || '').trim().toUpperCase(),
          address: ($('#mfAddress').value || '').trim(),
          status: statusOf($('#mfStatus').value)
        };
      }
      function dash(v) { return v ? MF.esc(v) : '<span class="text-2">—</span>'; }
      function phoneCell(phone) {
        const shown = formatPhone(phone);
        if (!shown) return '<span class="text-2">—</span>';
        const href = shown.replace(/[^\d+]/g, '');
        return `<a class="mf-phone" href="tel:${href}">${MF.esc(shown)}</a>`;
      }
      function contactCell(p) {
        const name = p.contact ? `<div class="fw-semibold">${MF.esc(p.contact)}</div>` : '<span class="text-2">—</span>';
        const email = p.email ? `<div class="mf-email">${MF.esc(p.email)}</div>` : '';
        return name + email;
      }
      function allNames() {
        const stored = [...(D.manufacturers || []).map(nameOf), ...state.apiRows.map((r) => r.name)].filter(Boolean);
        const used = (D.medicines || []).map((m) => m.manufacturer).filter(Boolean);
        return [...new Set([...stored, ...used])].sort((a, b) => a.localeCompare(b));
      }
      async function loadManufacturers() {
        if (!MF.Api.live) { state.apiRows = []; return; }
        const res = await MF.Api.get('manufacturers.php');
        state.apiRows = rowsFrom(res);
      }
      function medsOf(name) { return (D.medicines || []).filter((m) => m.manufacturer === name); }
      function rowStats(name) {
        const meds = medsOf(name);
        const stock = meds.reduce((s, m) => s + MF.stockOf(m.id), 0);
        const cats = [...new Set(meds.map((m) => m.category).filter(Boolean))];
        const mrp = meds.reduce((s, m) => s + MF.batchesOf(m.id).reduce((a, b) => a + (Number(b.qty) || 0) * (Number(b.mrp) || 0), 0), 0);
        return { meds, stock, cats, mrp };
      }

      function render() {
        const q = state.q.toLowerCase();
        const matches = (n) => {
          if (!q) return true;
          const p = profileOf(n);
          return [n, p.contact, p.email, p.phone, p.gstin, p.address, p.status].join(' ').toLowerCase().includes(q);
        };
        const list = allNames().filter(matches);
        const pages = Math.max(1, Math.ceil(list.length / state.per));
        state.page = Math.min(state.page, pages);
        const slice = list.slice((state.page - 1) * state.per, state.page * state.per);
        const unassigned = (D.medicines || []).filter((m) => !m.manufacturer).length;
        $('#mfCount').textContent = `${list.length} manufacturer${list.length === 1 ? '' : 's'}`;
        $('#mfStats').innerHTML = `
          <div class="col-6 col-md-3"><div class="mf-stat accent"><span>Manufacturers</span><strong>${MF.num(allNames().length)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="mf-stat"><span>Medicines linked</span><strong>${MF.num((D.medicines || []).length - unassigned)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="mf-stat"><span>Unassigned</span><strong>${MF.num(unassigned)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="mf-stat"><span>Showing</span><strong>${MF.num(list.length)}</strong></div></div>`;
        $('#mfBody').innerHTML = slice.map((name) => {
          const s = rowStats(name);
          const chips = s.cats.slice(0, 3).map((c) => `<span class="mf-chip">${MF.esc(c)}</span>`).join('') + (s.cats.length > 3 ? `<span class="text-2 small">+${s.cats.length - 3}</span>` : '');
          const p = profileOf(name);
          return `<tr>
            <td><div class="mf-name">${MF.esc(name)}</div></td>
            <td>${contactCell(p)}</td>
            <td>${phoneCell(p.phone)}</td>
            <td>${p.gstin ? `<span class="mf-gstin">${MF.esc(p.gstin)}</span>` : '<span class="text-2">—</span>'}</td>
            <td>${p.address ? `<div class="mf-addr" title="${MF.esc(p.address)}">${MF.esc(p.address)}</div>` : '<span class="text-2">—</span>'}</td>
            <td>${statusBadge(p.status)}</td>
            <td class="text-end num fw-semibold">${MF.num(s.meds.length)}</td>
            <td>${chips || '<span class="text-2">—</span>'}</td>
            <td class="text-end num">${MF.num(s.stock)}</td>
            <td class="text-end num">${MF.fmt(s.mrp)}</td>
            <td class="text-end">
              <div class="dropdown">
                <button type="button" class="btn btn-icon btn-light-mf mf-kebab" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end mf-act-menu">
                  <li><button type="button" class="dropdown-item" data-a="view" data-name="${MF.esc(name)}"><i class="bi bi-eye"></i><span>View medicines</span></button></li>
                  <li><button type="button" class="dropdown-item" data-a="edit" data-name="${MF.esc(name)}" data-id="${p.id || ''}"><i class="bi bi-pencil"></i><span>Edit</span></button></li>
                  <li><a class="dropdown-item" href="medicine-master.php?mfg=${encodeURIComponent(name)}"><i class="bi bi-capsule"></i><span>Open in master</span></a></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><button type="button" class="dropdown-item text-danger" data-a="del" data-name="${MF.esc(name)}" data-id="${p.id || ''}"><i class="bi bi-trash3"></i><span>Delete</span></button></li>
                </ul>
              </div>
            </td>
          </tr>`;
        }).join('') || `<tr><td colspan="11"><div class="empty-state"><i class="bi bi-buildings"></i>No manufacturers match.</div></td></tr>`;
        $('#mfPageInfo').textContent = `Showing ${slice.length ? (state.page - 1) * state.per + 1 : 0}–${(state.page - 1) * state.per + slice.length} of ${list.length}`;
        $('#mfPager').innerHTML = Array.from({ length: pages }, (_, i) =>
          `<li class="page-item ${i + 1 === state.page ? 'active' : ''}"><button class="page-link" type="button" data-pg="${i + 1}">${i + 1}</button></li>`).join('');
        $('#mfPager').querySelectorAll('[data-pg]').forEach((b) => b.addEventListener('click', () => { state.page = +b.dataset.pg; render(); }));
        $('#mfBody').querySelectorAll('[data-a]').forEach((b) => b.addEventListener('click', () => {
          const name = b.dataset.name, a = b.dataset.a;
          const id = b.dataset.id ? +b.dataset.id : null;
          if (a === 'view') openView(name);
          if (a === 'edit') openForm(name, id);
          if (a === 'del') remove(name, id);
        }));
      }

      function openForm(name, id) {
        editing = name || null;
        const p = editing ? profileOf(editing) : { id: null, contact: '', email: '', phone: '', gstin: '', address: '', status: 'Active' };
        editingId = id || p.id || null;
        $('#mfFormTitle').textContent = editing ? 'Edit manufacturer' : 'Add Manufacturer';
        $('#mfName').value = editing || '';
        $('#mfContact').value = p.contact;
        $('#mfEmail').value = p.email || '';
        $('#mfPhone').value = p.phone ? formatPhone(p.phone) : '';
        $('#mfGstin').value = p.gstin;
        $('#mfAddress').value = p.address;
        $('#mfStatus').value = statusOf(p.status);
        holdSearch();
        new bootstrap.Modal($('#mfFormModal')).show();
        setTimeout(() => $('#mfName').focus(), 200);
      }
      function openView(name) {
        const s = rowStats(name);
        const p = profileOf(name);
        $('#mfViewTitle').textContent = name;
        $('#mfViewBody').innerHTML = `
          <div class="p-3 border-bottom">
            <div class="row g-3">
              <div class="col-md-3"><div class="kpi-label">Contact person</div><div class="fw-semibold">${contactCell(p)}</div></div>
              <div class="col-md-3"><div class="kpi-label">Phone</div><div class="fw-semibold">${phoneCell(p.phone)}</div></div>
              <div class="col-md-3"><div class="kpi-label">GSTIN</div><div class="fw-semibold">${p.gstin ? `<span class="mf-gstin">${MF.esc(p.gstin)}</span>` : '<span class="text-2">—</span>'}</div></div>
              <div class="col-md-3"><div class="kpi-label">Status</div><div class="fw-semibold">${statusBadge(p.status)}</div></div>
              <div class="col-12"><div class="kpi-label">Address</div><div class="fw-semibold">${dash(p.address)}</div></div>
            </div>
          </div>
          <div class="p-3 border-bottom d-flex flex-wrap gap-4">
            <div><div class="kpi-label">Medicines</div><div class="fw-bold num">${MF.num(s.meds.length)}</div></div>
            <div><div class="kpi-label">Stock</div><div class="fw-bold num">${MF.num(s.stock)}</div></div>
            <div><div class="kpi-label">MRP value</div><div class="fw-bold num">${MF.fmt(s.mrp)}</div></div>
            <div class="ms-auto"><a class="btn btn-mf-soft btn-sm" href="medicine-master.php?mfg=${encodeURIComponent(name)}"><i class="bi bi-capsule me-1"></i>Open in master</a></div>
          </div>
          <table class="table table-mf mb-0">
            <thead><tr><th>Medicine</th><th>Category</th><th class="text-end">Stock</th><th class="text-end">MRP</th></tr></thead>
            <tbody>${s.meds.map((m) => `<tr>
              <td><div class="td-title">${MF.esc(m.name)}</div><div class="td-sub">${MF.esc(m.composition || '')}</div></td>
              <td class="text-2">${MF.esc(m.category || '—')}</td>
              <td class="text-end num">${MF.num(MF.stockOf(m.id))} ${MF.esc(m.unit || '')}</td>
              <td class="text-end num">${MF.fmt(m.mrp, 2)}</td>
            </tr>`).join('') || '<tr><td colspan="4"><div class="empty-state"><i class="bi bi-capsule"></i>No medicines use this manufacturer yet.</div></td></tr>'}</tbody>
          </table>`;
        new bootstrap.Modal($('#mfViewModal')).show();
      }
      async function save() {
        const next = clean($('#mfName').value);
        const profile = readFormProfile();
        if (!next) { MF.toast('Manufacturer name is required.', 'err', 'Validation'); return; }
        if (profile.gstin && !GSTIN_RE.test(profile.gstin)) {
          MF.toast('GSTIN must be 15 characters, like 27AABCU9603R1ZM.', 'err', 'Validation');
          return;
        }
        if (profile.email && !EMAIL_RE.test(profile.email)) {
          MF.toast('Enter a valid email address.', 'err', 'Validation');
          return;
        }
        if (profile.phone && profile.phone.replace(/\D/g, '').length < 6) {
          MF.toast('Enter a valid phone number.', 'err', 'Validation');
          return;
        }
        $('#mfPhone').value = profile.phone;
        const dup = allNames().some((n) => n.toLowerCase() === next.toLowerCase() && n !== editing);
        if (dup) { MF.toast('A manufacturer with that name already exists.', 'warn', 'Duplicate'); return; }
        const renamed = editing && editing !== next;
        if (MF.Api.live) {
          try {
            const row = editing ? apiRow(editing) : null;
            const id = editingId || (row && row.id) || null;
            const body = {
              name: next,
              contact_person: profile.contact,
              email: profile.email,
              phone: profile.phone,
              gstin: profile.gstin,
              address: profile.address,
              status: profile.status
            };
            if (editing) await MF.Api.put('manufacturers.php', { id: id || 0, from: editing, ...body });
            else await MF.Api.post('manufacturers.php', body);
            await MF.rehydrate();
            await loadManufacturers();
          } catch (e) { MF.toast(e.message, 'err', 'Save failed'); return; }
        } else if (!Array.isArray(D.manufacturers)) {
          D.manufacturers = [];
        }
        if (editing) {
          if (!MF.Api.live) {
            D.manufacturers = D.manufacturers.map((x) => nameOf(x) === editing ? (typeof x === 'string' ? next : Object.assign(x, { name: next })) : x);
            (D.medicines || []).forEach((m) => { if (m.manufacturer === editing) m.manufacturer = next; });
          }
          if (renamed) dropProfile(editing);
        } else if (!MF.Api.live && !D.manufacturers.some((x) => nameOf(x).toLowerCase() === next.toLowerCase())) {
          D.manufacturers.push(next);
        }
        writeProfile(next, profile);
        const verb = !editing ? 'added' : (renamed ? 'renamed' : 'updated');
        MF.toast(!editing ? `${next} added.` : (renamed ? `${editing} renamed to ${next}.` : `${next} updated.`), 'success', verb[0].toUpperCase() + verb.slice(1));
        releaseSearch();
        bootstrap.Modal.getInstance($('#mfFormModal'))?.hide();
        render();
      }
      async function remove(name, id) {
        const n = medsOf(name).length;
        if (n) { MF.toast(`${name} is used by ${n} medicine${n === 1 ? '' : 's'}. Rename it, or move those medicines first.`, 'warn', 'In use'); return; }
        const ok = await MF.confirm({ title: `Delete ${name}?`, message: 'This only removes the unused manufacturer from the list.', confirmText: 'Delete', tone: 'danger' });
        if (!ok) return;
        if (MF.Api.live) {
          const row = apiRow(name);
          const delId = id || (row && row.id) || 0;
          try {
            const qs = delId
              ? 'id=' + encodeURIComponent(delId)
              : 'name=' + encodeURIComponent(name);
            await MF.Api.del('manufacturers.php?' + qs);
            await MF.rehydrate();
            await loadManufacturers();
          } catch (e) { MF.toast(e.message, 'err', 'Delete failed'); return; }
        } else {
          D.manufacturers = (D.manufacturers || []).filter((x) => nameOf(x) !== name);
        }
        dropProfile(name);
        MF.toast(name + ' removed.', 'success', 'Deleted');
        render();
      }

      $('#mfSearch').addEventListener('input', () => {
        if (searchLock !== null) { $('#mfSearch').value = searchLock; return; }
        state.q = $('#mfSearch').value;
        state.page = 1;
        render();
      });
      $('#mfSearch').addEventListener('focus', () => { if (searchLock === null) $('#mfSearch').readOnly = false; });
      $('#mfForm').addEventListener('submit', (e) => { e.preventDefault(); save(); });
      $('#mfFormModal').addEventListener('show.bs.modal', holdSearch);
      $('#mfFormModal').addEventListener('hidden.bs.modal', releaseSearch);
      $('#mfAdd').addEventListener('click', () => openForm(null));
      $('#mfSave').addEventListener('click', save);
      $('#mfName').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); save(); } });
      $('#mfPhone').addEventListener('blur', () => { $('#mfPhone').value = formatPhone($('#mfPhone').value); });
      $('#mfExport').addEventListener('click', () => {
        const q = state.q.toLowerCase();
        MF.exportCSV('manufacturers.csv',
          ['Manufacturer', 'Contact person', 'Email', 'Phone', 'GSTIN', 'Address', 'Status', 'Medicines', 'Categories', 'Stock', 'MRP value'],
          allNames().filter((n) => {
            if (!q) return true;
            const p = profileOf(n);
            return [n, p.contact, p.email, p.phone, p.gstin, p.address, p.status].join(' ').toLowerCase().includes(q);
          }).map((n) => {
            const s = rowStats(n);
            const p = profileOf(n);
            return [n, p.contact, p.email, p.phone, p.gstin, p.address, p.status, s.meds.length, s.cats.join(', '), s.stock, s.mrp];
          }));
      });

      document.addEventListener('DOMContentLoaded', async () => {
        await MF.boot();
        try { await loadManufacturers(); }
        catch (e) { MF.toast(e.message, 'err', 'Could not load manufacturers'); }
        render();
      });
    })();
  </script>
</body>
</html>
