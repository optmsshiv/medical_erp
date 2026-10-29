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
  <style>
    .cu-modal .form-control, .cu-modal .form-select { border-radius:10px; min-height:40px; border-color:#e5e7eb; transition:border-color .15s ease, box-shadow .15s ease; }
    .cu-modal .form-control:hover, .cu-modal .form-select:hover { border-color:var(--mf-primary); }
    .cu-modal .form-control:focus, .cu-modal .form-select:focus { border-color:var(--mf-primary); box-shadow:0 0 0 3px rgba(23,107,91,.16); }
    .cu-save, .cu-edit { border-radius:10px; transition:background .15s ease, color .15s ease, border-color .15s ease, transform .15s ease; }
    .cu-save:hover, .cu-edit:hover { transform:translateY(-1px); }
    .cu-edit:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .cu-addr { display:block; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#516278; }
    .cu-type { display:inline-flex; align-items:center; margin-top:4px; border:1px solid transparent; border-radius:4px; padding:1px 6px; font-size:.72rem; font-weight:700; letter-spacing:.01em; }
    .cu-type.retail { background:#E6F1EE; color:#0F4D42; border-color:#b7ddd4; }
    .cu-type.wholesale { background:#E0F2FE; color:#0369A1; border-color:#bae6fd; }
    .cu-type.hospital { background:#F1EBFC; color:#6D28D9; border-color:#ddd6fe; }
    .cu-type.clinic { background:#FEF3E2; color:#B45309; border-color:#f6d7a2; }
    .cu-type.others { background:#F3F4F6; color:#4B5563; border-color:#e5e7eb; }
    /* Segmented ledger filter */
    .cu-seg { display:inline-flex; background:#F1F4F6; border-radius:10px; padding:3px; gap:2px; }
    .cu-seg button {
      border:0; background:transparent; padding:.38rem .95rem; border-radius:8px;
      font-size:.78rem; font-weight:600; color:#6B7280; transition:all .18s;
    }
    .cu-seg button:hover { color:#374151; }
    .cu-seg button.active { background:#fff; color:var(--mf-primary); box-shadow:0 1px 3px rgba(15,23,42,.12); }
    .cu-seg button .cu-seg-n { opacity:.65; font-weight:500; font-size:.72rem; margin-left:2px; }
    /* Ageing strip */
    .cu-age { display:flex; gap:.4rem; flex-wrap:wrap; align-items:stretch; }
    .cu-age-cell { flex:1; min-width:88px; background:#FAFBFC; border:1px solid #EDF1F4; border-radius:10px; padding:.38rem .6rem; }
    .cu-age-cell .a-lbl { font-size:.62rem; text-transform:uppercase; letter-spacing:.05em; color:#9AA3AF; font-weight:700; }
    .cu-age-cell .a-val { font-size:.82rem; font-weight:700; font-variant-numeric:tabular-nums; margin-top:1px; }
    .cu-age-cell.w1 .a-val { color:#0F4D42; } .cu-age-cell.w2 .a-val { color:#B45309; }
    .cu-age-cell.w3 .a-val { color:#B91C1C; } .cu-age-cell.w4 .a-val { color:#7F1D1D; }
    .cu-profile-header { align-items:flex-start; }
    .cu-profile-head { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:0; }
    .cu-profile-head .name { font-weight:750; }
    .cu-name-icon { width:32px; height:32px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:1rem; flex:0 0 32px; }
    .cu-name-icon.retail { background:#E6F1EE; color:#0F4D42; }
    .cu-name-icon.wholesale { background:#E0F2FE; color:#0369A1; }
    .cu-name-icon.hospital { background:#F1EBFC; color:#6D28D9; }
    .cu-name-icon.clinic { background:#FEF3E2; color:#B45309; }
    .cu-name-icon.others { background:#F3F4F6; color:#4B5563; }
    .cu-type.head { margin-top:0; }
    .cu-badges { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
    .cu-chip { display:inline-flex; align-items:center; gap:5px; border-radius:4px; padding:3px 8px; font-size:.75rem; font-weight:700; border:1px solid transparent; }
    .cu-chip i { font-size:.85rem; }
    .cu-chip.gst { background:#E7F6EE; color:#178A45; border-color:#b7e4c7; }
    .cu-chip.dl { background:#F1EBFC; color:#6D28D9; border-color:#ddd6fe; }
    .cu-chip.city { background:#E0F2FE; color:#0369A1; border-color:#bae6fd; }
    .cu-kpi { border-radius:14px; }
    .cu-kpi.sales { background:#f3faf8; border-color:#d7ebe6; }
    .cu-kpi.paid { background:#f3fbf6; border-color:#cdeed8; }
    .cu-kpi.due { background:#fdf4f4; border-color:#f5d0d0; }
    .cu-kpi.visit { background:#fff8ee; border-color:#f6d7a2; }
    .cu-busy { position:relative; overflow:hidden; pointer-events:none; }
    .cu-busy::after { content:""; position:absolute; left:0; bottom:0; height:3px; width:38%; background:var(--mf-primary); animation:cu-slide .8s ease-in-out infinite; }
    .btn-mf.cu-busy::after { background:#fff; }
    @keyframes cu-slide { from { transform:translateX(-120%); } to { transform:translateX(320%); } }
    /* ------- Customer ledger premium pass ------- */
    .cu-table { table-layout: fixed; width: 100%; }
    .cu-minw0 { min-width: 0; display: flex; flex-direction: column; gap: 1px; }
    .cu-ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; }
    .cu-person { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .cu-av { width: 34px; height: 34px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: .76rem; font-weight: 800; letter-spacing: .02em; flex: 0 0 34px; }
    .cu-av.retail { background: #E6F1EE; color: #0F4D42; }
    .cu-av.wholesale, .cu-av.business { background: #E0F2FE; color: #0369A1; }
    .cu-av.hospital { background: #F1EBFC; color: #6D28D9; }
    .cu-av.clinic { background: #FEF3E2; color: #B45309; }
    .cu-av.others, .cu-av.other { background: #F3F4F6; color: #4B5563; }
    .cu-nm { font-weight: 650; color: #1B2430; font-size: .88rem; }
    .cu-sub { font-size: .72rem; color: #8B9BB0; font-weight: 550; display: block; }
    .cu-num { font-variant-numeric: tabular-nums; }
    .cu-mono { font-size: .8rem; font-weight: 650; letter-spacing: .02em; color: #41546E; white-space: nowrap; }
    .cu-time { font-size: .7rem; color: #8B9BB0; font-weight: 600; white-space: nowrap; margin-left: 4px; }
    .cu-adv { font-size: .68rem; font-weight: 700; color: #0369A1; white-space: nowrap; }
    .cu-bar { height: 4px; width: 84px; max-width: 100%; border-radius: 4px; background: #EDF1F4; margin: 5px 0 0 auto; overflow: hidden; }
    .cu-bar i { display: block; height: 100%; border-radius: 4px; background: linear-gradient(90deg, #34B399, #176B5B); }
    @media (max-width: 1199.98px) { .cu-h-lg { display: none; } }
  </style>
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
            <button class="btn btn-mf-outline" id="cuAgeOpen"><i class="bi bi-calendar2-week me-1"></i>Ageing</button>
            <button class="btn btn-mf-outline" id="cuRefillOpen"><i class="bi bi-arrow-repeat me-1"></i>Refill log</button>
            <button class="btn btn-mf" id="cuAddBtn"><i class="bi bi-person-plus me-1"></i>Add Customer</button>
          </div>
        </div>

        <div class="row g-3 mb-3" id="cuKpis"></div>

        <div class="card-mf p-3 mb-3">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <div class="cu-seg" id="cuSeg">
              <button type="button" data-seg="all" class="active">All</button>
              <button type="button" data-seg="retail">Retail<span class="cu-seg-n" id="cuSegRetail"></span></button>
              <button type="button" data-seg="business">Business<span class="cu-seg-n" id="cuSegBusiness"></span></button>
            </div>
            <span class="text-2" style="font-size:.72rem">Business = wholesale, hospital, clinic accounts — credit receivables tracked separately from retail udhar.</span>
          </div>
          <div class="row g-2">
            <div class="col-md-6">
              <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span>
              <input class="form-control" id="cuSearch" placeholder="Search name, business, phone, GSTIN…"></div>
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
            <table class="table table-mf cu-table">
              <thead>
                <tr>
                  <th style="width:20%">Customer Name</th><th style="width:12%">Phone</th><th class="cu-h-lg" style="width:11%">GSTIN</th><th class="cu-h-lg" style="width:16%">Address</th>
                  <th class="text-end" style="width:9%">Total Sales</th><th class="text-end" style="width:9%">Paid</th><th class="text-end" style="width:8%">Due</th>
                  <th style="width:9%">Last Purchase</th><th class="text-end" style="width:6%">Actions</th>
                </tr>
              </thead>
              <tbody id="cuBody"></tbody>
            </table>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Receivables ageing modal -->
  <div class="modal fade cu-modal" id="cuAgeModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-calendar2-week me-2" style="color:var(--mf-primary)"></i>Receivables ageing</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <div class="cu-age flex-grow-1" id="cuAgeStrip"></div>
            <span class="text-2" style="font-size:.72rem">Tap a bucket to filter the bills below · oldest first</span>
          </div>
          <div class="table-scroll" style="max-height:46vh">
            <table class="table table-mf">
              <thead><tr><th>Invoice</th><th>Customer</th><th>Channel</th><th>Bill date</th><th class="text-center">Age</th><th class="text-end">Due</th></tr></thead>
              <tbody id="cuAgeBody"></tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between align-items-baseline mt-2" style="font-size:.74rem;color:#6B7280">
            <span id="cuAgeFoot"></span>
            <span>Settled &amp; payments-over-bill are netted per bill; account-level extras show in the profile ledger.</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Refill log modal -->
  <div class="modal fade cu-modal" id="cuRefillModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2" style="color:var(--mf-primary)"></i>Refill log</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-0">
          <div class="table-scroll" style="max-height:none">
            <table class="table-mf">
              <thead><tr><th>When</th><th>Customer</th><th>Items</th><th>Prescription</th></tr></thead>
              <tbody id="cuRefillBody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add customer modal -->
  <div class="modal fade cu-modal" id="cuAddModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="cuFormTitle">Add Customer</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Customer Name <span class="req">*</span></label><input class="form-control" id="cuName" placeholder="Contact person"></div>
          <div class="mb-2 cu-biz-field"><label class="form-label" for="cuBiz">Business name</label><input class="form-control" id="cuBiz" placeholder="Hospital, clinic or shop"></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">Phone</label><input class="form-control" id="cuPhone" placeholder="98xxx xxxxx"></div>
            <div class="col-6"><label class="form-label" for="cuType">Customer type</label>
              <select class="form-select" id="cuType">
                <option value="retail">Retail Customer</option>
                <option value="wholesale">Wholesale Dealer</option>
                <option value="Hospital">Hospital</option>
                <option value="Clinic">Clinic</option>
                <option value="Others">Others</option>
              </select>
            </div>
          </div>
          <div class="row g-2 mb-2 cu-biz-field">
            <div class="col-6"><label class="form-label">GSTIN</label><input class="form-control" id="cuGstin" placeholder="Optional for retail"></div>
            <div class="col-6"><label class="form-label">Drug License No.</label><input class="form-control" id="cuDl" placeholder="Optional"></div>
          </div>
          <div class="row g-2 mb-2 cu-biz-field">
            <div class="col-6"><label class="form-label" for="cuCreditLimit">Credit limit (₹)</label><input class="form-control" id="cuCreditLimit" type="number" min="0" step="0.01" placeholder="0 = no cap"></div>
            <div class="col-6"><label class="form-label" for="cuCreditDays">Credit days</label><input class="form-control" id="cuCreditDays" type="number" min="0" step="1" placeholder="e.g. 30"></div>
            <div class="col-12" style="font-size:.7rem;color:#8A94A0;margin-top:-2px">POS warns when a credit bill would push the outstanding past the limit. Terms show on the customer record.</div>
          </div>
          <div><label class="form-label">Address</label><textarea class="form-control" id="cuAddr" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf cu-save" id="cuAddSave"><i class="bi bi-check2 me-1"></i>Save Customer</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Profile modal -->
  <div class="modal fade" id="cuProfileModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header cu-profile-header">
          <div>
            <h5 class="modal-title cu-profile-head" id="cuProfileTitle"></h5>
            <div class="cu-badges" id="cuProfileMeta"></div>
          </div>
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
          <button class="btn btn-mf-outline cu-edit" id="cuEditBtn" type="button"><i class="bi bi-pencil me-1"></i>Edit</button>
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
      let editId = null;
      let reopenProfile = false;
      const savedRows = {};

      function remember(id, fields) {
        if (id == null || id === '') return;
        savedRows[String(id)] = Object.assign({}, savedRows[String(id)] || {}, fields);
      }

      function applySaved() {
        (D.customers || []).forEach((c) => {
          const saved = savedRows[String(c.id)];
          if (saved) Object.assign(c, saved);
        });
      }

      function typeKey(value) {
        const key = String(value || '').toLowerCase();
        if (key === 'wholesale') return 'wholesale';
        if (key === 'hospital') return 'hospital';
        if (key === 'clinic') return 'clinic';
        if (key === 'others' || key === 'other') return 'others';
        return 'retail';
      }

      function typeIcon(value) {
        return { retail: 'bi-person', wholesale: 'bi-shop', hospital: 'bi-hospital', clinic: 'bi-heart-pulse', others: 'bi-building' }[typeKey(value)];
      }

      function cityName(address) {
        const parts = String(address || '').split(',').map((s) => s.trim()).filter(Boolean);
        const pin = (s) => /^\d{6}$/.test(s.replace(/\s/g, '')) || /^\d{3}\s?\d{3}$/.test(s);
        const bits = parts.map((p) => p.replace(/\b\d{6}\b/g, '').trim()).filter((p) => p && !pin(p));
        if (!bits.length) return '';
        if (bits.length > 2) return bits[bits.length - 2];
        return bits[bits.length - 1];
      }

      function setBusy(btn, on) {
        if (!btn) return;
        if (on) {
          if (!btn.dataset.idle) btn.dataset.idle = btn.innerHTML;
          btn.disabled = true;
          btn.classList.add('cu-busy');
        } else {
          btn.disabled = false;
          btn.classList.remove('cu-busy');
          if (btn.dataset.idle) btn.innerHTML = btn.dataset.idle;
          delete btn.dataset.idle;
        }
      }
      function typeLabel(value) {
        return { retail: 'Retail Customer', wholesale: 'Wholesale Dealer', hospital: 'Hospital', clinic: 'Clinic', others: 'Others' }[typeKey(value)] || value || '—';
      }

      function typeValue(value) {
        const key = String(value || '').toLowerCase();
        return { retail: 'retail', wholesale: 'wholesale', hospital: 'Hospital', clinic: 'Clinic', others: 'Others', other: 'Others' }[key] || 'retail';
      }

      function list() { return D.customers.filter((c) => c.name !== 'Walk-in Customer'); }

      function filtered() {
        const q = $('#cuSearch').value.toLowerCase();
        const due = $('#cuDue').value;
        const seg = document.querySelector('#cuSeg .active')?.dataset.seg || 'all';
        return list().filter((c) => {
          const biz = c.business_name || c.businessName || '';
          const isRetail = typeKey(c.type) === 'retail';
          if (seg === 'retail' && !isRetail) return false;
          if (seg === 'business' && isRetail) return false;
          if (q && !(c.name + biz + (c.phone || '') + (c.gstin || '') + (c.address || '') + typeLabel(c.type)).toLowerCase().includes(q)) return false;
          if (due === 'due' && c.due <= 0) return false;
          if (due === 'clear' && c.due > 0) return false;
          return true;
        });
      }

      function renderKpis() {
        const l = list();
        const bizDue = l.filter((c) => typeKey(c.type) !== 'retail').reduce((s, c) => s + c.due, 0);
        const retDue = l.filter((c) => typeKey(c.type) === 'retail').reduce((s, c) => s + c.due, 0);
        $('#cuKpis').innerHTML = [
          ['Total Customers', l.length, 'primary', 'people'],
          ['Lifetime Sales', MF.fmt(l.reduce((s, c) => s + c.totalSales, 0)), 'success', 'graph-up-arrow'],
          ['Outstanding Dues', MF.fmt(bizDue + retDue), 'danger', 'cash-stack'],
          ['Advance parked', MF.fmt(l.reduce((s, c) => s + (c.advance || 0), 0)), 'info', 'safe'],
          ['Business receivable', MF.fmt(bizDue), 'primary', 'briefcase'],
          ['Retail receivable', MF.fmt(retDue), 'warning', 'person-check']
        ].map(([lbl, v, tone, icon]) => `
          <div class="col-6 col-md-4 col-xl-2"><div class="card-mf kpi-card h-100">
            <div class="kpi-icon tone-${tone}"><i class="bi bi-${icon}"></i></div>
            <div><div class="kpi-label">${lbl}</div><div class="kpi-value num">${v}</div></div>
          </div></div>`).join('');
        $('#cuSegRetail').textContent = '· ' + l.filter((c) => typeKey(c.type) === 'retail').length;
        $('#cuSegBusiness').textContent = '· ' + l.filter((c) => typeKey(c.type) !== 'retail').length;
      }

      function render() {
        const l = filtered();
        $('#cuCount').textContent = `${list().length} customer(s)`;
        $('#cuBody').innerHTML = l.map((c) => {
          const biz = c.business_name || c.businessName || '';
          const addr = String(c.address || '').replace(/\s+/g, ' ').trim();
          const kind = typeLabel(c.type);
          const tone = typeKey(c.type);
          const gstin = c.gstin || '';
          const city = typeof cityName === 'function' ? cityName(addr) : '';
          const initials = String(c.name || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
          const paidBase = Math.max(0, (c.totalSales || 0) - (c.due || 0));
          const settlePct = c.totalSales > 0 ? Math.min(100, Math.round((Math.min(c.totalSales, Math.max(c.paid || 0, paidBase)) / c.totalSales) * 100)) : null;
          const lpAt = c.lastPurchaseAt || c.lastPurchase || '';
          const lpDate = lpAt ? MF.fmtDate(lpAt) : '';
          const lpHm = String(lpAt).slice(11, 16);
          const lpTime = (String(lpAt).length > 10 && lpHm !== '00:00') ? lpHm : '';
          return `
          <tr>
            <td><div class="cu-person"><span class="cu-av ${tone}">${MF.esc(initials)}</span><span class="cu-minw0"><span class="cu-nm cu-ellipsis" title="${MF.esc(c.name)}">${MF.esc(c.name)}</span>${biz ? `<span class="cu-sub cu-ellipsis" title="${MF.esc(biz)}">${MF.esc(biz)}</span>` : ''}</span></div></td>
            <td><div class="num">${MF.esc(c.phone || '—')}</div>${kind && kind !== '—' ? `<span class="cu-type ${tone}">${MF.esc(kind)}</span>` : ''}</td>
            <td class="cu-h-lg">${gstin ? `<span class="cu-mono">${MF.esc(gstin)}</span>` : '<span class="text-2">—</span>'}</td>
            <td class="cu-h-lg">${addr ? `<span class="cu-addr" title="${MF.esc(addr)}">${MF.esc(addr)}</span>` : '<span class="text-2">—</span>'}${city ? `<div class="cu-sub">${MF.esc(city)}</div>` : ''}</td>
            <td class="text-end cu-num">${MF.fmt(c.totalSales)}</td>
            <td class="text-end cu-num text-success">${MF.fmt(c.paid)}${settlePct != null ? `<div class="cu-bar"><i style="width:${settlePct}%"></i></div>` : ''}</td>
            <td class="text-end cu-num fw-semibold ${c.due > 0 ? 'text-danger' : ''}">${MF.fmt(c.due)}${!(c.due > 0) && (c.advance || 0) > 0 ? `<div class="cu-adv">ADV +₹${MF.fmt(c.advance)}</div>` : ''}</td>
            <td class="num">${lpDate ? `${lpDate}${lpTime ? `<span class="cu-time">${lpTime}</span>` : ''}` : '<span class="text-2">—</span>'}</td>
            <td class="text-end row-actions">
              <button class="btn btn-sm btn-mf-soft" data-view="${c.id}">Profile</button>
            </td>
          </tr>`;
        }).join('') || `<tr><td colspan="9"><div class="empty-state"><i class="bi bi-people"></i>No customers match the filters.</div></td></tr>`;
        $('#cuBody').querySelectorAll('[data-view]').forEach((b) => b.addEventListener('click', () => {
          setBusy(b, true);
          openProfile(b.dataset.view).finally(() => setBusy(b, false));
        }));
      }

      async function openProfile(id) {
        current = MF.cust(Number(id));
        const c = current;
        const tone = typeKey(c.type);
        const kind = typeLabel(c.type);
        const city = cityName(c.address);
        const gstin = c.gstin || '';
        const dl = c.dl_no || c.dlNo || '';
        $('#cuProfileTitle').innerHTML = `<i class="bi ${typeIcon(c.type)} cu-name-icon ${tone}"></i><span class="name">${MF.esc(c.name || 'Customer')}</span>${kind && kind !== '—' ? `<span class="cu-type head ${tone}">${MF.esc(kind)}</span>` : ''}`;
        $('#cuProfileMeta').innerHTML = `
          <span class="cu-chip gst"><i class="bi bi-receipt"></i>${gstin ? 'GST ' + MF.esc(gstin) : 'GST —'}</span>
          <span class="cu-chip dl"><i class="bi bi-card-checklist"></i>${dl ? 'DL ' + MF.esc(dl) : 'DL —'}</span>
          <span class="cu-chip city"><i class="bi bi-geo-alt"></i>${city ? MF.esc(city) : 'City —'}</span>`;
        $('#cuProfileBody').innerHTML = `<div class="text-center text-2 p-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>`;
        bootstrap.Modal.getOrCreateInstance($('#cuProfileModal')).show();

        let invoices = [];
        let payments = [];
        try {
          const ledger = await MF.Api.get('party-ledger.php?type=customer&id=' + c.id);
          const data = ledger.data ?? ledger;
          invoices = data.invoices || [];
          payments = data.payments || [];
        } catch (e) {
          MF.toast(e.message || 'Could not load the ledger.', 'err', 'Profile');
        }

        $('#cuProfileBody').innerHTML = `
          <div class="tab-pane fade show active" id="cuTabOverview">
            <div class="row g-3 mb-3">
              <div class="col-6 col-md-3"><div class="card-mf kpi-card h-100 cu-kpi sales">
                <div class="kpi-icon tone-primary"><i class="bi bi-graph-up-arrow"></i></div>
                <div><div class="kpi-label">Sales</div><div class="kpi-value num">${MF.fmt(c.totalSales)}</div></div>
              </div></div>
              <div class="col-6 col-md-3"><div class="card-mf kpi-card h-100 cu-kpi paid">
                <div class="kpi-icon tone-success"><i class="bi bi-check2-circle"></i></div>
                <div><div class="kpi-label">Paid</div><div class="kpi-value num">${MF.fmt(c.paid)}</div></div>
              </div></div>
              <div class="col-6 col-md-3"><div class="card-mf kpi-card h-100 cu-kpi due">
                <div class="kpi-icon tone-danger"><i class="bi bi-cash-stack"></i></div>
                <div><div class="kpi-label">Outstanding</div><div class="kpi-value num">${MF.fmt(c.due)}</div></div>
              </div></div>
              <div class="col-6 col-md-3"><div class="card-mf kpi-card h-100 cu-kpi visit">
                <div class="kpi-icon tone-warning"><i class="bi bi-calendar-event"></i></div>
                <div><div class="kpi-label">Last purchase</div><div class="kpi-value num">${c.lastPurchase ? MF.fmtDate(c.lastPurchase) : '—'}</div></div>
              </div></div>
            </div>
            <div class="row g-3 mb-3">
              ${[...([['Phone', MF.esc(c.phone || '—')], ['Business', MF.esc(c.business_name || c.businessName || '—')],
                 ['Customer type', MF.esc(kind)], ['Address', MF.esc(c.address || '—')],
                 ['GST number', MF.esc(gstin || '—')], ['Drug licence', MF.esc(dl || '—')]]),
                 ...(typeKey(c.type) !== 'retail' ? [[
                   'Credit policy',
                   c.credit_limit > 0
                     ? MF.fmt(c.credit_limit) + ' cap · ' + (c.credit_days ? c.credit_days + 'd terms' : 'no terms set')
                     : 'No cap set'
                 ], [
                   'Credit headroom',
                   c.credit_limit > 0
                     ? '<span class="' + (c.due >= c.credit_limit ? 'text-danger' : 'text-success') + ' fw-bold">' + MF.fmt(Math.max(0, c.credit_limit - c.due)) + '</span> available'
                     : '—'
                 ]] : [])].map(([k, v]) =>
                `<div class="col-md-4 col-6"><div class="kpi-label">${k}</div><div class="fw-semibold">${v}</div></div>`).join('')}
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
        const btn = $('#cuPaySave');
        setBusy(btn, true);
        try {
          await MF.Api.post('payments.php', { partyType: 'customer', partyId: current.id, amount: amt, mode: $('#cuPayMode').value });
          bootstrap.Modal.getInstance($('#cuPayModal')).hide();
          bootstrap.Modal.getInstance($('#cuProfileModal')).hide();
          MF.toast(`${MF.fmt(amt)} received from ${current.name}.`, 'success', 'Payment recorded');
          await MF.rehydrate();
          applySaved();
          await syncDues();
        } catch (err) {
          MF.toast(err.message || 'Could not record payment.', 'danger');
        } finally {
          setBusy(btn, false);
        }
      });

      /* Retail customers are walk-ins — business name, GSTIN and drug license only matter
         for wholesale / Hospital / Clinic / Others. Hide + skip them for Retail. */
      function syncTypeFields() {
        const retail = String($('#cuType').value || 'retail').toLowerCase() === 'retail';
        document.querySelectorAll('.cu-biz-field').forEach((el) => { el.hidden = retail; });
      }
      $('#cuType').addEventListener('change', syncTypeFields);
      syncTypeFields();

      function openForm(row) {
        editId = row ? row.id : null;
        $('#cuFormTitle').textContent = row ? 'Edit Customer' : 'Add Customer';
        $('#cuAddSave').innerHTML = row
          ? '<i class="bi bi-check2 me-1"></i>Update Customer'
          : '<i class="bi bi-check2 me-1"></i>Save Customer';
        $('#cuName').value = row ? (row.name || '') : '';
        $('#cuBiz').value = row ? (row.business_name || row.businessName || '') : '';
        $('#cuPhone').value = row ? (row.phone || '') : '';
        $('#cuType').value = row ? typeValue(row.type) : 'retail';
        $('#cuGstin').value = row ? (row.gstin || '') : '';
        $('#cuDl').value = row ? (row.dl_no || row.dlNo || '') : '';
        $('#cuCreditLimit').value = typeValue(row ? row.type : 'retail') === 'retail' || !row || !row.credit_limit ? '' : row.credit_limit;
        $('#cuCreditDays').value = row && row.credit_days ? row.credit_days : '';
        $('#cuAddr').value = row ? (row.address || '') : '';
        syncTypeFields();
        const profileEl = $('#cuProfileModal');
        const profileOpen = profileEl.classList.contains('show');
        if (row && profileOpen) {
          reopenProfile = true;
          profileEl.addEventListener('hidden.bs.modal', function showEdit() {
            profileEl.removeEventListener('hidden.bs.modal', showEdit);
            bootstrap.Modal.getOrCreateInstance($('#cuAddModal')).show();
          });
          bootstrap.Modal.getOrCreateInstance(profileEl).hide();
          return;
        }
        reopenProfile = false;
        bootstrap.Modal.getOrCreateInstance($('#cuAddModal')).show();
      }

      $('#cuAddModal').addEventListener('hidden.bs.modal', () => {
        if (!reopenProfile || !current) return;
        reopenProfile = false;
        openProfile(current.id);
      });

      /* Segment pills — persisted so the ledger view survives navigation */
      document.querySelectorAll('#cuSeg button').forEach((b) => b.addEventListener('click', () => {
        document.querySelectorAll('#cuSeg button').forEach((x) => x.classList.toggle('active', x === b));
        try { localStorage.setItem('cu_seg', b.dataset.seg); } catch (e) {}
        render();
      }));
      try {
        const savedSeg = localStorage.getItem('cu_seg');
        if (savedSeg) {
          document.querySelectorAll('#cuSeg button').forEach((x) => x.classList.toggle('active', x.dataset.seg === savedSeg));
        }
      } catch (e) {}

      $('#cuAddBtn').addEventListener('click', () => openForm(null));

      /* Refill log — reads the fast-refill events recorded from the POS Quick Refill panel */
      async function paintRefillLog() {
        const body = $('#cuRefillBody');
        body.innerHTML = '<tr><td colspan="4" class="text-center text-2" style="padding:1.4rem">Loading…</td></tr>';
        try {
          const res = await MF.Api.get('refill-logs.php');
          const rows = Array.isArray(res.data) ? res.data : [];
          if (!rows.length) {
            body.innerHTML = '<tr><td colspan="4" class="empty-state" style="padding:2.4rem"><i class="bi bi-arrow-repeat" style="font-size:1.6rem;display:block;margin-bottom:.4rem"></i>No refills yet — the POS Quick Refill panel records here automatically.</td></tr>';
            return;
          }
          body.innerHTML = rows.map((r) => `
            <tr>
              <td class="num" style="white-space:nowrap">${MF.esc((MF.fmtDateTime || MF.fmtDate)(r.ts))}</td>
              <td><div class="td-title">${MF.esc(r.customer || '—')}</div><div class="td-sub num">${MF.esc(r.phone || '')}</div></td>
              <td><span class="badge badge-soft-primary">${r.itemCount} item${r.itemCount === 1 ? '' : 's'}</span></td>
              <td><div style="max-width:280px;font-size:.76rem;color:#4B5563">${MF.esc(r.preview || '—')}</div></td>
            </tr>`).join('');
        } catch (e) {
          body.innerHTML = '<tr><td colspan="4" class="text-center" style="padding:1.4rem;color:#B4352F">' + MF.esc(e.message || 'Could not load the refill log.') + '</td></tr>';
        }
      }
      $('#cuRefillOpen').addEventListener('click', () => {
        bootstrap.Modal.getOrCreateInstance($('#cuRefillModal')).show();
        paintRefillLog();
      });

      /* Receivables ageing — buckets the live dues by number of days open.
         Bill-level view (oldest first); tapping a bucket isolates that slice. */
      let ageRows = [];
      let ageFilter = null;                    // 0..3, or null = all buckets
      const AGE_BUCKET = (age) => (age == null || age < 0) ? 0 : age <= 30 ? 0 : age <= 60 ? 1 : age <= 90 ? 2 : 3;
      const AGE_LABELS = ['0–30 days', '31–60 days', '61–90 days', '90+ days'];
      function ageBadge(age) {
        const b = AGE_BUCKET(age);
        const cls = ['badge-soft-success', 'badge-soft-warning', 'badge-soft-danger', 'badge-soft-danger'][b];
        return `<span class="badge ${cls}">${age == null ? '—' : age + 'd'}</span>`;
      }
      function paintAgeing() {
        const strip = $('#cuAgeStrip');
        const sums = [0, 0, 0, 0];
        const counts = [0, 0, 0, 0];
        ageRows.forEach((r) => { const b = AGE_BUCKET(r.age_days); sums[b] += r.balance_due; counts[b]++; });
        strip.innerHTML = AGE_LABELS.map((lbl, i) => `
          <div class="cu-age-cell w${i + 1}" data-b="${i}" role="button" tabindex="0" title="${counts[i]} open bill(s)"
               style="cursor:pointer;${ageFilter === i ? 'outline:2px solid var(--mf-primary);outline-offset:1px' : ''}">
            <div class="a-lbl">${lbl}</div><div class="a-val num">${MF.fmt(sums[i])}</div>
          </div>`).join('');
        strip.querySelectorAll('.cu-age-cell').forEach((cell) => {
          cell.addEventListener('click', () => { const b = Number(cell.dataset.b); ageFilter = ageFilter === b ? null : b; paintAgeing(); });
          cell.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); cell.click(); } });
        });
        const rows = ageFilter == null ? ageRows : ageRows.filter((r) => AGE_BUCKET(r.age_days) === ageFilter);
        const body = $('#cuAgeBody');
        if (!rows.length) {
          body.innerHTML = '<tr><td colspan="6" class="empty-state" style="padding:2rem"><i class="bi bi-check-circle" style="font-size:1.5rem;display:block;margin-bottom:.35rem"></i>Nothing pending in this slice — all clear.</td></tr>';
        } else {
          body.innerHTML = rows.map((r) => `
            <tr>
              <td class="num td-title">${MF.esc(r.invoice_no || ('S-' + r.id))}</td>
              <td><div class="td-title">${MF.esc(r.customer_name || '—')}</div></td>
              <td><span class="cu-type ${r.channel === 'wholesale' ? 'wholesale' : 'retail'}" style="margin-top:0">${r.channel === 'wholesale' ? 'Wholesale' : 'Retail'}</span></td>
              <td class="num">${MF.esc(r.sale_date ? (MF.fmtDate ? MF.fmtDate(r.sale_date) : r.sale_date) : '—')}</td>
              <td class="text-center">${ageBadge(r.age_days)}</td>
              <td class="text-end num fw-semibold text-danger">${MF.fmt(r.balance_due)}</td>
            </tr>`).join('');
        }
        const total = rows.reduce((s, r) => s + r.balance_due, 0);
        $('#cuAgeFoot').textContent = ageFilter == null
          ? `${rows.length} open bill(s) · ${MF.fmt(total)} outstanding across all buckets`
          : `${AGE_LABELS[ageFilter]}: ${rows.length} bill(s) · ${MF.fmt(total)} — tap the bucket again to see everything`;
      }
      $('#cuAgeOpen').addEventListener('click', async () => {
        bootstrap.Modal.getOrCreateInstance($('#cuAgeModal')).show();
        $('#cuAgeBody').innerHTML = '<tr><td colspan="6" class="text-center text-2" style="padding:1.6rem">Reading the dues ledger…</td></tr>';
        try {
          const res = await MF.Api.get('customer-dues.php');
          const bills = (res.data && res.data.bills) || [];
          ageRows = bills.filter((b) => Number(b.balance_due) > 0)
            .sort((a, b) => String(a.sale_date || '').localeCompare(String(b.sale_date || '')));
          ageFilter = null;
          paintAgeing();
        } catch (e) {
          $('#cuAgeBody').innerHTML = '<tr><td colspan="6" class="text-center" style="padding:1.6rem;color:#B4352F">' + MF.esc(e.message || 'Could not load ageing.') + '</td></tr>';
        }
      });
      $('#cuEditBtn').addEventListener('click', () => { if (current) openForm(current); });
      $('#cuAddSave').addEventListener('click', async () => {
        const name = $('#cuName').value.trim();
        const savingId = editId;
        if (!name) { MF.toast('Customer name is required.', 'err', 'Validation'); return; }
        const body = {
          name, type: $('#cuType').value, phone: $('#cuPhone').value.trim(),
          business_name: $('#cuBiz').value.trim(),
          gstin: $('#cuGstin').value.trim(), dlNo: $('#cuDl').value.trim(), address: $('#cuAddr').value.trim(),
          credit_limit: $('#cuCreditLimit').value === '' ? 0 : Math.max(0, Number($('#cuCreditLimit').value) || 0),
          credit_days: $('#cuCreditDays').value === '' ? 0 : Math.max(0, parseInt($('#cuCreditDays').value, 10) || 0),
        };
        const saveBtn = $('#cuAddSave');
        setBusy(saveBtn, true);
        try {
          const saved = savingId
            ? await MF.Api.put('customers.php', Object.assign({ id: savingId }, body))
            : await MF.Api.post('customers.php', body);
          const id = saved.id || savingId;
          const extra = {
            name,
            type: body.type,
            business_name: body.business_name,
            businessName: body.business_name,
            gstin: body.gstin,
            dl_no: body.dlNo,
            dlNo: body.dlNo,
            address: body.address,
            phone: body.phone,
            credit_limit: body.credit_limit || null,
            credit_days: body.credit_days || null
          };
          remember(id, extra);
          const backToProfile = reopenProfile;
          reopenProfile = false;
          const editEl = $('#cuAddModal');
          const closed = new Promise((resolve) => {
            editEl.addEventListener('hidden.bs.modal', function once() {
              editEl.removeEventListener('hidden.bs.modal', once);
              resolve();
            });
          });
          bootstrap.Modal.getInstance(editEl).hide();
          await closed;
          MF.toast(savingId ? 'Customer updated' : name + ' added to customer master.', 'success', savingId ? 'Saved' : 'Customer created');
          if (MF.rehydrate) await MF.rehydrate();
          await loadProfiles();
          applySaved();
          const hit = (D.customers || []).find((c) => String(c.id) === String(id));
          if (hit) Object.assign(hit, extra);
          else if (id) D.customers.push(Object.assign({ id, due: 0, paid: 0, totalSales: 0, lastPurchase: null }, extra));
          if (current && String(current.id) === String(id)) Object.assign(current, extra);
          renderKpis(); render();
          if (backToProfile) openProfile(id);
        } catch (err) {
          MF.toast(err.message || 'Could not save the customer.', 'danger');
        } finally {
          setBusy(saveBtn, false);
        }
      });

      /* Ledger truth — same principle as the suppliers page: due/advance
         recomputed by customer-dues.php from the shared payments table. */
      async function syncDues() {
        if (!MF.Api.live) return;
        try {
          const res = await MF.Api.get('customer-dues.php');
          const rows = (res && res.data && res.data.customers) || [];
          const byId = new Map(rows.map((r) => [String(r.customer_id), r]));
          (D.customers || []).forEach((c) => {
            const r = byId.get(String(c.id));
            if (!r) { c.due = 0; c.advance = 0; return; }
            c.due = Math.round((+r.outstanding || 0) * 100) / 100;
            c.advance = Math.round((+r.advance || 0) * 100) / 100;
            if (r.billed != null) c.totalSales = Math.round((+r.billed || 0) * 100) / 100;
            c.paid = Math.round(Math.max(+r.amount_paid || 0, +r.payments || 0) * 100) / 100;
          });
          renderKpis(); render();
        } catch (e) { /* local figures remain */ }
      }

      ['cuSearch', 'cuDue'].forEach((id) => $('#' + id).addEventListener('input', render));

      async function loadProfiles() {
        if (!MF.Api.live) return;
        try {
          const res = await MF.Api.get('customers.php');
          const rows = Array.isArray(res.data) ? res.data : [];
          rows.forEach((row) => {
            const hit = (D.customers || []).find((c) => String(c.id) === String(row.id));
            if (!hit) return;
            ['name', 'phone', 'address', 'type', 'gstin', 'business_name', 'dl_no', 'credit_limit', 'credit_days', 'lastPurchase', 'lastPurchaseAt'].forEach((key) => {
              if (Object.prototype.hasOwnProperty.call(row, key) && row[key] != null && row[key] !== '') hit[key] = row[key];
            });
            if (row.businessName && !hit.business_name) hit.business_name = row.businessName;
            if (row.dlNo && !hit.dl_no) hit.dl_no = row.dlNo;
          });
          applySaved();
        } catch (e) { /* ledger still uses the bootstrapped customers */ }
      }
      loadProfiles().then(() => { applySaved(); renderKpis(); render(); });
      syncDues();
      renderKpis(); render();
    })();
    });
  </script>
</body>
</html>
