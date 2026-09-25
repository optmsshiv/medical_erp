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
  <title>Medicine Categories · Optms Rx</title>
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
    .mf-cat { display:flex; align-items:center; gap:10px; min-width:180px; }
    .mf-cat-ico { width:32px; height:32px; border-radius:10px; background:#e8eef8; color:#16325c; display:inline-flex; align-items:center; justify-content:center; flex:0 0 32px; font-size:15px; }
    .mf-sub { color:#8b9bb0; font-size:.72rem; font-weight:600; margin-top:1px; }
    .mf-ledger-note { color:#8b9bb0; font-size:.78rem; line-height:1.45; padding:0 1rem 1rem; margin:0; }
  </style>
</head>
<body data-page="categories">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">
        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-tags me-2 text-success"></i>Medicine Categories</h1>
            <p class="page-sub" id="catCount"></p>
          </div>
          <div class="ms-auto d-flex gap-2">
            <button class="btn btn-light-mf" id="catExport" type="button"><i class="bi bi-download me-1"></i>Export CSV</button>
            <button class="btn btn-mf" id="catAdd" type="button"><i class="bi bi-plus-lg me-1"></i>Add Category</button>
          </div>
        </div>

        <div class="row g-3 mb-3" id="catStats"></div>

        <div class="card-mf p-3 mb-3">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input class="form-control" id="catSearch" name="cat-list-filter" placeholder="Search category…" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true">
          </div>
        </div>

        <div class="card-mf">
          <div class="table-scroll" style="max-height:none;overflow:visible">
            <table class="table table-mf">
              <thead>
                <tr>
                  <th>Category</th>
                  <th class="text-end">Medicines</th>
                  <th class="text-end">Batches</th>
                  <th class="text-end">Sales(30D)</th>
                  <th>Manufacturers</th>
                  <th class="text-end">Stock</th>
                  <th class="text-end">MRP value</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="catBody"></tbody>
            </table>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2 p-3 border-top">
            <span class="text-2 small" id="catPageInfo"></span>
            <div class="ms-auto"><ul class="pagination pagination-sm mb-0" id="catPager"></ul></div>
          </div>
          <p class="mf-ledger-note">Categories are configurable — counts, stock value and 30-day sales are computed live from the medicine master and batch ledger.</p>
        </div>
      </main>
    </div>
  </div>

  <div class="modal fade" id="catFormModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="catFormTitle">Add Category</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
        </div>
        <form class="modal-body" id="catForm" autocomplete="off">
          <label class="form-label" for="catName">Category name <span class="req">*</span></label>
          <input class="form-control" id="catName" name="cat-label" placeholder="e.g. Analgesic" autocomplete="off" data-lpignore="true" data-1p-ignore="true">
          <div class="text-2 small mt-2">Renaming updates every medicine in this category. It also updates the category list on Add Medicine.</div>
        </form>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal" type="button">Cancel</button>
          <button class="btn btn-mf" id="catSave" type="button"><i class="bi bi-check2 me-1"></i>Save</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="catViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="catViewTitle">Category</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
        </div>
        <div class="modal-body p-0" id="catViewBody"></div>
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
      const state = { q: '', page: 1, per: 8 };
      let editing = null;
      let searchLock = null;
      function holdSearch() {
        const el = $('#catSearch');
        if (!el || searchLock !== null) return;
        searchLock = el.value;
        el.readOnly = true;
      }
      function releaseSearch() {
        const el = $('#catSearch');
        if (!el || searchLock === null) return;
        if (el.value !== searchLock) el.value = searchLock;
        el.readOnly = false;
        state.q = searchLock;
        searchLock = null;
      }

      const nameOf = (x) => typeof x === 'string' ? x : (x && x.name) || '';
      const clean = (s) => (s || '').trim().replace(/\s+/g, ' ');
      const CAT_RULES = [
        [/analges|pain|nsaid|antipyretic|fever/i, 'bandaid'],
        [/antibiotic|antibacterial/i, 'shield-plus'],
        [/antacid|gastro|digest|ulcer/i, 'capsule'],
        [/cardiac|heart|hyperten|\bbp\b/i, 'heart-pulse'],
        [/diabet|insulin/i, 'droplet'],
        [/respirat|cough|cold|asthma|inhal/i, 'lungs'],
        [/vitamin|supplement|nutra/i, 'sun'],
        [/derma|skin|ointment|cream/i, 'moisture'],
        [/eye|ophthal/i, 'eye'],
        [/ent|ear|nasal|throat/i, 'ear'],
        [/pediatric|paediatric|baby|child/i, 'emoji-smile'],
        [/ayur|herbal|homeo/i, 'flower1'],
        [/vaccin|immun/i, 'syringe'],
        [/neuro|psych|cns/i, 'lightning-charge'],
        [/allerg|histamine/i, 'wind'],
        [/fungal|viral|infection/i, 'virus'],
        [/surg|dressing|consumable/i, 'scissors'],
        [/inject|infusion|\biv\b/i, 'prescription2']
      ];
      const CAT_FALLBACK = ['capsule-pill', 'tags', 'clipboard2-pulse', 'prescription2', 'box-seam'];
      function catIcon(name) {
        const raw = (D.categories || []).find((x) => nameOf(x) === name);
        if (raw && typeof raw === 'object' && raw.icon) return String(raw.icon).replace(/^bi-/, '');
        const hit = CAT_RULES.find(([re]) => re.test(name));
        if (hit) return hit[1];
        let h = 0;
        for (const ch of name) h = (h + ch.charCodeAt(0)) % CAT_FALLBACK.length;
        return CAT_FALLBACK[h];
      }
      function allNames() {
        const stored = (D.categories || []).map(nameOf).filter(Boolean);
        const used = (D.medicines || []).map((m) => m.category).filter(Boolean);
        return [...new Set([...stored, ...used])].sort((a, b) => a.localeCompare(b));
      }
      function medsOf(name) { return (D.medicines || []).filter((m) => m.category === name); }
      function dayOf(v) {
        const m = String(v || '').match(/\d{4}-\d{2}-\d{2}/);
        return m ? m[0] : '';
      }
      function salesWindow() {
        const end = MF.today();
        const start = new Date(end + 'T00:00:00Z');
        start.setUTCDate(start.getUTCDate() - 30);
        return { start: start.toISOString().slice(0, 10), end };
      }
      function lineQty(line) {
        return Number(line.qty ?? line.quantity ?? line.soldQty ?? line.units ?? 0) || 0;
      }
      function lineAmount(line) {
        if (line.amount != null) return Number(line.amount) || 0;
        if (line.net != null) return Number(line.net) || 0;
        if (line.total != null) return Number(line.total) || 0;
        if (line.lineTotal != null) return Number(line.lineTotal) || 0;
        const rate = Number(line.rate ?? line.price ?? line.mrp ?? 0) || 0;
        const disc = Number(line.discPct ?? line.discountPct ?? 0) || 0;
        return lineQty(line) * rate * (1 - disc / 100);
      }
      function lineMedId(line) {
        return line.medId ?? line.medicineId ?? line.medicine_id ?? line.itemId ?? null;
      }
      function invoiceLines(inv) {
        return inv.items || inv.lines || inv.rows || inv.medicines || inv.details || [];
      }
      function salesByCategory() {
        const map = new Map();
        const bump = (medId, qty, amount) => {
          const med = (D.medicines || []).find((m) => m.id == medId);
          if (!med || !med.category) return;
          const cur = map.get(med.category) || { qty: 0, amount: 0 };
          cur.qty += qty;
          cur.amount += amount;
          map.set(med.category, cur);
        };
        const { start, end } = salesWindow();
        const inWindow = (row) => {
          const day = dayOf(row.date || row.invoiceDate || row.createdAt || row.dt);
          return day && day >= start && day <= end;
        };
        const skip = (row) => /cancel|void|draft/i.test(String(row.status || ''));
        const signOf = (row) => /return/i.test(String(row.type || row.docType || '')) ? -1 : 1;
        const invoices = (Array.isArray(D.salesInvoices) && D.salesInvoices.length) ? D.salesInvoices : (Array.isArray(D.sales) ? D.sales : []);
        invoices.forEach((inv) => {
          if (!inv || skip(inv) || !inWindow(inv)) return;
          const sign = signOf(inv);
          invoiceLines(inv).forEach((line) => {
            const id = lineMedId(line);
            if (id == null) return;
            bump(id, sign * lineQty(line), sign * lineAmount(line));
          });
        });
        if (!map.size) {
          [].concat(D.saleLines || [], D.salesLines || [], D.invoiceItems || []).forEach((line) => {
            if (!line || skip(line) || !inWindow(line)) return;
            const id = lineMedId(line);
            if (id == null) return;
            const sign = signOf(line);
            bump(id, sign * lineQty(line), sign * lineAmount(line));
          });
        }
        if (!map.size) {
          (D.medicines || []).forEach((m) => {
            const qty = m.sales30 ?? m.sold30 ?? m.qtySold30 ?? m.salesQty30;
            const amount = m.salesValue30 ?? m.sales30Value ?? m.saleValue30;
            if (qty == null && amount == null) return;
            bump(m.id, Number(qty) || 0, Number(amount) || 0);
          });
        }
        return map;
      }
      function salesCell(sale) {
        const s = sale || { qty: 0, amount: 0 };
        if (!s.qty && !s.amount) return '<span class="text-2">—</span>';
        if (s.amount) {
          return `<div class="num fw-semibold">${MF.fmt(s.amount)}</div>${s.qty ? `<div class="mf-sub">${MF.num(s.qty)} qty</div>` : ''}`;
        }
        return `<div class="num fw-semibold">${MF.num(s.qty)}</div><div class="mf-sub">qty</div>`;
      }
      function salesExport(sale) {
        const s = sale || { qty: 0, amount: 0 };
        if (s.amount) return Math.round(s.amount);
        return s.qty || 0;
      }
      function rowStats(name, salesMap) {
        const meds = medsOf(name);
        const stock = meds.reduce((s, m) => s + MF.stockOf(m.id), 0);
        const batches = meds.reduce((s, m) => s + MF.batchesOf(m.id).length, 0);
        const mfgs = [...new Set(meds.map((m) => m.manufacturer).filter(Boolean))];
        const mrp = meds.reduce((s, m) => s + MF.batchesOf(m.id).reduce((a, b) => a + (Number(b.qty) || 0) * (Number(b.mrp) || 0), 0), 0);
        const sale = (salesMap && salesMap.get(name)) || { qty: 0, amount: 0 };
        return { meds, stock, batches, mfgs, mrp, sale };
      }

      function render() {
        const q = state.q.toLowerCase();
        const salesMap = salesByCategory();
        const list = allNames().filter((n) => !q || n.toLowerCase().includes(q));
        const pages = Math.max(1, Math.ceil(list.length / state.per));
        state.page = Math.min(state.page, pages);
        const slice = list.slice((state.page - 1) * state.per, state.page * state.per);
        const unassigned = (D.medicines || []).filter((m) => !m.category).length;
        $('#catCount').textContent = `${list.length} categor${list.length === 1 ? 'y' : 'ies'}`;
        $('#catStats').innerHTML = `
          <div class="col-6 col-md-3"><div class="mf-stat accent"><span>Categories</span><strong>${MF.num(allNames().length)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="mf-stat"><span>Medicines linked</span><strong>${MF.num((D.medicines || []).length - unassigned)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="mf-stat"><span>Unassigned</span><strong>${MF.num(unassigned)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="mf-stat"><span>Showing</span><strong>${MF.num(list.length)}</strong></div></div>`;
        $('#catBody').innerHTML = slice.map((name) => {
          const s = rowStats(name, salesMap);
          const chips = s.mfgs.slice(0, 3).map((c) => `<span class="mf-chip">${MF.esc(c)}</span>`).join('') + (s.mfgs.length > 3 ? `<span class="text-2 small">+${s.mfgs.length - 3}</span>` : '');
          return `<tr>
            <td><div class="mf-cat"><span class="mf-cat-ico" aria-hidden="true"><i class="bi bi-${catIcon(name)}"></i></span><div class="mf-name">${MF.esc(name)}</div></div></td>
            <td class="text-end num fw-semibold">${MF.num(s.meds.length)}</td>
            <td class="text-end num">${MF.num(s.batches)}</td>
            <td class="text-end">${salesCell(s.sale)}</td>
            <td>${chips || '<span class="text-2">—</span>'}</td>
            <td class="text-end num">${MF.num(s.stock)}</td>
            <td class="text-end num">${MF.fmt(s.mrp)}</td>
            <td class="text-end">
              <div class="dropdown">
                <button type="button" class="btn btn-icon btn-light-mf mf-kebab" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end mf-act-menu">
                  <li><button type="button" class="dropdown-item" data-a="view" data-name="${MF.esc(name)}"><i class="bi bi-eye"></i><span>View medicines</span></button></li>
                  <li><button type="button" class="dropdown-item" data-a="edit" data-name="${MF.esc(name)}"><i class="bi bi-pencil"></i><span>Rename</span></button></li>
                  <li><a class="dropdown-item" href="medicine-master.php?category=${encodeURIComponent(name)}"><i class="bi bi-capsule"></i><span>Open in master</span></a></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><button type="button" class="dropdown-item text-danger" data-a="del" data-name="${MF.esc(name)}"><i class="bi bi-trash3"></i><span>Delete</span></button></li>
                </ul>
              </div>
            </td>
          </tr>`;
        }).join('') || `<tr><td colspan="8"><div class="empty-state"><i class="bi bi-tags"></i>No categories match.</div></td></tr>`;
        $('#catPageInfo').textContent = `Showing ${slice.length ? (state.page - 1) * state.per + 1 : 0}–${(state.page - 1) * state.per + slice.length} of ${list.length}`;
        $('#catPager').innerHTML = Array.from({ length: pages }, (_, i) =>
          `<li class="page-item ${i + 1 === state.page ? 'active' : ''}"><button class="page-link" type="button" data-pg="${i + 1}">${i + 1}</button></li>`).join('');
        $('#catPager').querySelectorAll('[data-pg]').forEach((b) => b.addEventListener('click', () => { state.page = +b.dataset.pg; render(); }));
        $('#catBody').querySelectorAll('[data-a]').forEach((b) => b.addEventListener('click', () => {
          const name = b.dataset.name, a = b.dataset.a;
          if (a === 'view') openView(name);
          if (a === 'edit') openForm(name);
          if (a === 'del') remove(name);
        }));
      }

      function openForm(name) {
        editing = name || null;
        $('#catFormTitle').textContent = editing ? 'Rename category' : 'Add Category';
        $('#catName').value = editing || '';
        holdSearch();
        new bootstrap.Modal($('#catFormModal')).show();
        setTimeout(() => $('#catName').focus(), 200);
      }
      function openView(name) {
        const s = rowStats(name, salesByCategory());
        $('#catViewTitle').textContent = name;
        $('#catViewBody').innerHTML = `
          <div class="p-3 border-bottom d-flex flex-wrap gap-4 align-items-center">
            <span class="mf-cat-ico"><i class="bi bi-${catIcon(name)}"></i></span>
            <div><div class="kpi-label">Medicines</div><div class="fw-bold num">${MF.num(s.meds.length)}</div></div>
            <div><div class="kpi-label">Batches</div><div class="fw-bold num">${MF.num(s.batches)}</div></div>
            <div><div class="kpi-label">Sales(30D)</div><div class="fw-bold num">${s.sale.amount ? MF.fmt(s.sale.amount) : (s.sale.qty ? MF.num(s.sale.qty) + ' qty' : '—')}</div></div>
            <div><div class="kpi-label">Stock</div><div class="fw-bold num">${MF.num(s.stock)}</div></div>
            <div><div class="kpi-label">MRP value</div><div class="fw-bold num">${MF.fmt(s.mrp)}</div></div>
            <div class="ms-auto"><a class="btn btn-mf-soft btn-sm" href="medicine-master.php?category=${encodeURIComponent(name)}"><i class="bi bi-capsule me-1"></i>Open in master</a></div>
          </div>
          <table class="table table-mf mb-0">
            <thead><tr><th>Medicine</th><th>Manufacturer</th><th class="text-end">Stock</th><th class="text-end">MRP</th></tr></thead>
            <tbody>${s.meds.map((m) => `<tr>
              <td><div class="td-title">${MF.esc(m.name)}</div><div class="td-sub">${MF.esc(m.composition || '')}</div></td>
              <td class="text-2">${MF.esc(m.manufacturer || '—')}</td>
              <td class="text-end num">${MF.num(MF.stockOf(m.id))} ${MF.esc(m.unit || '')}</td>
              <td class="text-end num">${MF.fmt(m.mrp, 2)}</td>
            </tr>`).join('') || '<tr><td colspan="4"><div class="empty-state"><i class="bi bi-capsule"></i>No medicines in this category yet.</div></td></tr>'}</tbody>
          </table>`;
        new bootstrap.Modal($('#catViewModal')).show();
      }
      async function save() {
        const next = clean($('#catName').value);
        if (!next) { MF.toast('Category name is required.', 'err', 'Validation'); return; }
        const dup = allNames().some((n) => n.toLowerCase() === next.toLowerCase() && n !== editing);
        if (dup) { MF.toast('A category with that name already exists.', 'warn', 'Duplicate'); return; }
        if (MF.Api.live) {
          try {
            if (editing) await MF.Api.put('categories.php', { from: editing, name: next });
            else await MF.Api.post('categories.php', { name: next });
            await MF.rehydrate();
          } catch (e) { MF.toast(e.message, 'err', 'Save failed'); return; }
        } else {
          if (!Array.isArray(D.categories)) D.categories = [];
          if (editing) {
            D.categories = D.categories.map((x) => nameOf(x) === editing ? next : x);
            (D.medicines || []).forEach((m) => { if (m.category === editing) m.category = next; });
          } else if (!D.categories.some((x) => nameOf(x).toLowerCase() === next.toLowerCase())) {
            D.categories.push(next);
          }
        }
        MF.toast(editing ? `${editing} renamed to ${next}.` : `${next} added.`, 'success', editing ? 'Renamed' : 'Added');
        releaseSearch();
        bootstrap.Modal.getInstance($('#catFormModal'))?.hide();
        render();
      }
      async function remove(name) {
        const n = medsOf(name).length;
        if (n) { MF.toast(`${name} is used by ${n} medicine${n === 1 ? '' : 's'}. Rename it, or move those medicines first.`, 'warn', 'In use'); return; }
        const ok = await MF.confirm({ title: `Delete ${name}?`, message: 'This only removes the unused category from the list.', confirmText: 'Delete', tone: 'danger' });
        if (!ok) return;
        if (MF.Api.live) {
          try { await MF.Api.del('categories.php?name=' + encodeURIComponent(name)); await MF.rehydrate(); }
          catch (e) { MF.toast(e.message, 'err', 'Delete failed'); return; }
        } else {
          D.categories = (D.categories || []).filter((x) => nameOf(x) !== name);
        }
        MF.toast(name + ' removed.', 'success', 'Deleted');
        render();
      }

      $('#catSearch').addEventListener('input', () => {
        if (searchLock !== null) { $('#catSearch').value = searchLock; return; }
        state.q = $('#catSearch').value;
        state.page = 1;
        render();
      });
      $('#catForm').addEventListener('submit', (e) => { e.preventDefault(); save(); });
      $('#catFormModal').addEventListener('show.bs.modal', holdSearch);
      $('#catFormModal').addEventListener('hidden.bs.modal', releaseSearch);
      $('#catAdd').addEventListener('click', () => openForm(null));
      $('#catSave').addEventListener('click', save);
      $('#catName').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); save(); } });
      $('#catExport').addEventListener('click', () => {
        const salesMap = salesByCategory();
        MF.exportCSV('categories.csv',
          ['Category', 'Medicines', 'Batches', 'Sales(30D)', 'Manufacturers', 'Stock', 'MRP value'],
          allNames().filter((n) => !state.q || n.toLowerCase().includes(state.q.toLowerCase())).map((n) => {
            const s = rowStats(n, salesMap);
            return [n, s.meds.length, s.batches, salesExport(s.sale), s.mfgs.join(', '), s.stock, s.mrp];
          }));
      });

      document.addEventListener('DOMContentLoaded', async () => {
        await MF.boot();
        render();
      });
    })();
  </script>
</body>
</html>
