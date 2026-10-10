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
  <title>Sales Return · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    /* ---------- Sales return desk (pro pass) ---------- */
    .sr-stat { background:#F8FAFC; border:1px solid #E7EDF4; border-radius:14px; padding:12px 14px; }
    .sr-stat span { display:block; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#6C757D; }
    .sr-stat strong { display:block; margin-top:3px; font-size:1.2rem; font-weight:750; letter-spacing:-.02em; color:#1B2430; }
    .sr-stat.accent { background:var(--mf-primary-soft); border-color:#D7EBE6; }
    .sr-pane { border:1px solid #E3EBF4; border-radius:16px; background:#fff; overflow:hidden; box-shadow:0 1px 2px rgba(22,50,92,.04), 0 10px 28px rgba(22,50,92,.04); display:flex; flex-direction:column; }
    .sr-pane-head { display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid #E7EEF6; font-weight:750; color:#1B2430; font-size:.92rem; background:#FBFCFE; }
    .sr-step { width:24px; height:24px; border-radius:50%; background:var(--mf-primary); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:.78rem; font-weight:800; flex:0 0 24px; }
    .sr-pane-head .ms-auto { font-size:.72rem; font-weight:650; color:#8B9BB0; }
    .sr-tools { padding:12px 16px; border-bottom:1px solid #E7EEF6; display:flex; flex-direction:column; gap:10px; background:#fff; }
    .sr-search { display:flex; align-items:center; gap:10px; }
    .sr-search > i { color:#8B9BB0; }
    .sr-search input { border:0; outline:0; box-shadow:none !important; width:100%; font-size:.92rem; color:#1B2430; background:transparent; }
    .sr-chips { display:flex; gap:6px; flex-wrap:wrap; }
    .sr-chip { border:1px solid #E3EBF4; background:#fff; color:#516278; border-radius:999px; padding:4px 10px; font-size:.74rem; font-weight:700; transition:all .15s ease; cursor:pointer; }
    .sr-chip:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .sr-chip.active { background:var(--mf-primary); border-color:var(--mf-primary); color:#fff; }
    .sr-invlist { overflow:auto; max-height:520px; padding:8px; display:flex; flex-direction:column; gap:6px; }
    .sr-inv { display:flex; align-items:center; gap:12px; padding:10px 12px; border:1px solid #EAF0F6; border-radius:12px; cursor:pointer; transition:all .15s ease; background:#fff; }
    .sr-inv:hover { border-color:#BFE3DA; background:#FAFEFD; }
    .sr-inv.sel { border-color:var(--mf-primary); background:var(--mf-primary-soft); box-shadow:0 0 0 1px var(--mf-primary) inset; }
    .sr-inv-no { font-weight:750; font-size:.86rem; color:#1B2430; font-variant-numeric:tabular-nums; }
    .sr-inv-when { font-size:.72rem; color:#8B9BB0; font-weight:600; }
    .sr-inv-cust { font-size:.8rem; font-weight:600; color:#41546E; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .sr-inv .amt { margin-left:auto; text-align:right; flex:0 0 auto; }
    .sr-inv .amt b { display:block; font-size:.88rem; font-weight:750; color:#1B2430; font-variant-numeric:tabular-nums; }
    .sr-pill { display:inline-block; font-size:.64rem; font-weight:800; letter-spacing:.04em; padding:2px 7px; border-radius:999px; }
    .sr-pill.ret { background:#FEF3E2; color:#B45309; }
    .sr-pill.due { background:#FDECEA; color:#B02A37; }
    .sr-pill.paid { background:#E6F1EE; color:#176B5B; }
    .sr-moreline { text-align:center; font-size:.74rem; color:#8B9BB0; padding:8px 0 4px; }
    /* ---------- desk ---------- */
    .sr-desk-body { padding:12px 16px; display:flex; flex-direction:column; gap:12px; overflow:auto; max-height:520px; }
    .sr-empty { text-align:center; padding:44px 12px; color:#8B9BB0; }
    .sr-empty i { font-size:2rem; display:block; margin-bottom:8px; color:#C7D3E0; }
    .sr-bill { border:1px solid #E7EDF4; border-radius:12px; background:#FBFCFE; padding:10px 14px; display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .sr-bill .no { font-weight:800; color:#1B2430; font-variant-numeric:tabular-nums; }
    .sr-bill .meta { font-size:.74rem; color:#8B9BB0; font-weight:600; }
    .sr-bill .nums { margin-left:auto; display:flex; gap:16px; font-size:.74rem; font-weight:650; color:#6C7A8E; }
    .sr-bill .nums b { display:block; font-size:.9rem; font-weight:750; color:#1B2430; font-variant-numeric:tabular-nums; text-align:right; }
    .sr-bill .nums .val-due b { color:#B02A37; }
    .sr-duenote { border:1px dashed #F3C1C8; background:#FEF6F6; color:#8E2832; font-size:.76rem; font-weight:650; border-radius:10px; padding:8px 12px; }
    .sr-allrow { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .sr-all { border:1.5px solid var(--mf-primary); color:var(--mf-primary-dark); background:var(--mf-primary-soft); border-radius:9px; padding:5px 12px; font-size:.76rem; font-weight:800; cursor:pointer; }
    .sr-all:hover { background:#D7EBE6; }
    .sr-ghost { border:1px solid #E3EBF4; color:#516278; background:#fff; border-radius:9px; padding:5px 12px; font-size:.76rem; font-weight:700; cursor:pointer; }
    .sr-ghost:hover { border-color:#C2D1DE; }
    /* item cards */
    .sr-line { display:flex; gap:12px; align-items:flex-start; border:1px solid #EAF0F6; border-radius:12px; padding:10px 12px; background:#fff; transition:all .15s ease; }
    .sr-line.on { border-color:#9FD8CD; background:#FBFEFD; box-shadow:0 0 0 1px #9FD8CD inset; }
    .sr-line.done { opacity:.55; background:#F8FAFC; }
    .sr-check { width:20px; height:20px; margin-top:3px; accent-color:#176B5B; flex:0 0 20px; }
    .sr-gro { flex:1; min-width:0; }
    .sr-med { font-weight:700; font-size:.88rem; color:#1B2430; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .sr-tags { display:flex; gap:6px; flex-wrap:wrap; margin-top:4px; }
    .sr-tag { display:inline-flex; align-items:center; gap:4px; background:#F4F7FB; border:1px solid #E4EBF4; border-radius:6px; padding:2px 7px; font-size:.68rem; font-weight:700; color:#51637B; white-space:nowrap; }
    .sr-tag.exp { color:#B45309; background:#FEF9EF; border-color:#F5E3C2; }
    .sr-tag.exp.past { color:#B02A37; background:#FDECEA; border-color:#F3C1C8; }
    .sr-tag.left { color:#176B5B; background:#EEF7F4; border-color:#CBE6DE; }
    .sr-tag.retn { color:#B45309; background:#FEF3E2; border-color:#F5DDB3; }
    .sr-qty { display:flex; align-items:center; gap:6px; flex:0 0 auto; }
    .sr-stepper { display:inline-flex; align-items:stretch; border:1px solid #E0E8F0; border-radius:9px; overflow:hidden; background:#fff; }
    .sr-stepper button { width:30px; border:0; background:#F6F9FC; color:#41546E; font-weight:800; font-size:.95rem; cursor:pointer; }
    .sr-stepper button:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); }
    .sr-stepper input { width:52px; border:0; text-align:center; font-weight:750; font-size:.88rem; color:#1B2430; outline:0; box-shadow:none !important; padding:4px 2px; }
    .sr-max { border:0; background:none; color:var(--mf-primary-dark); font-size:.7rem; font-weight:800; cursor:pointer; padding:2px 4px; }
    .sr-ref { flex:0 0 88px; text-align:right; }
    .sr-ref b { display:block; font-size:.92rem; font-weight:750; color:#176B5B; font-variant-numeric:tabular-nums; }
    .sr-ref span { font-size:.66rem; font-weight:600; color:#8B9BB0; }
    /* sticky action bar */
    .sr-bar { border-top:1px solid #E7EEF6; background:#fff; padding:12px 16px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .sr-bar select, .sr-bar input[type="text"] { border:1px solid #E3EBF4; border-radius:10px; font-size:.84rem; font-weight:600; color:#1B2430; padding:6px 10px; outline:0; box-shadow:none !important; }
    .sr-bar select { min-width:200px; }
    .sr-bar input[type="text"] { flex:1; min-width:150px; }
    .sr-modes { display:flex; gap:5px; }
    .sr-mode { border:1px solid #E3EBF4; background:#fff; color:#516278; border-radius:9px; padding:6px 10px; font-size:.76rem; font-weight:800; cursor:pointer; }
    .sr-mode.active { background:#16325C; border-color:#16325C; color:#fff; }
    .sr-total { margin-left:auto; text-align:right; }
    .sr-total span { display:block; font-size:.66rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#8B9BB0; }
    .sr-total b { font-size:1.3rem; font-weight:800; color:#176B5B; letter-spacing:-.02em; }
    /* success panel */
    .sr-win { border:1.5px solid #BFE3DA; background:linear-gradient(160deg,#F2FBF8,#fff); border-radius:16px; padding:22px; text-align:center; }
    .sr-win i.bi { font-size:2.4rem; color:#176B5B; }
    .sr-win h4 { margin:8px 0 2px; font-weight:800; color:#14584C; }
    .sr-win .srn { font-size:1.15rem; font-weight:800; color:#1B2430; font-variant-numeric:tabular-nums; }
    .sr-win .sub { color:#51637B; font-size:.84rem; font-weight:600; }
    /* ledger */
    .sr-table th { background:#F7F9FC; color:#7B8798; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; border-bottom:1px solid #E7EEF6; padding:11px 14px; white-space:nowrap; }
    .sr-table td { border-bottom:1px solid #F0F4F8; padding:11px 14px; vertical-align:middle; font-size:.86rem; }
    .sr-table tr:last-child td { border-bottom:0; }
    .sr-table tr:hover td { background:#F4FAF8; }
    .sr-sq { display:inline-block; background:#F4F7FB; border-radius:6px; padding:1px 7px; font-size:.72rem; font-weight:650; color:#51637B; margin:1px 3px 1px 0; }
    .sr-mut { font-size:.72rem; color:#8B9BB0; font-weight:600; }
    .sr-num { font-variant-numeric:tabular-nums; }
    @media (max-width:991.98px) {
      .sr-invlist, .sr-desk-body { max-height:none; }
      .sr-ref { flex-basis:70px; }
    }
  </style>
</head>
<body data-page="sales-return">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">
        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-arrow-counterclockwise me-2 text-success"></i>Sales Return</h1>
            <p class="page-sub" id="srSub">The easy return desk — find the bill, pick what comes back, done. This is not a refund policy screen.</p>
          </div>
          <div class="ms-auto d-flex gap-2 flex-wrap justify-content-end">
            <button class="btn btn-light-mf" id="srRefresh" type="button"><i class="bi bi-arrow-repeat me-1"></i>Refresh</button>
            <a class="btn btn-mf-outline" href="sales-invoices.php"><i class="bi bi-receipt me-1"></i>All invoices</a>
          </div>
        </div>

        <div class="row g-3 mb-3" id="srStats"></div>

        <div class="row g-3">
          <div class="col-lg-5">
            <div class="card-mf sr-pane">
              <div class="sr-pane-head"><span class="sr-step">1</span>Find the invoice<span class="ms-auto" id="srInvCount"></span></div>
              <div class="sr-tools">
                <div class="sr-search"><i class="bi bi-search"></i>
                  <input id="srSearch" placeholder="Invoice no, customer, phone…" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true">
                </div>
                <div class="sr-chips" id="srScopes">
                  <button type="button" class="sr-chip active" data-scope="today">Today</button>
                  <button type="button" class="sr-chip" data-scope="yday">Yesterday</button>
                  <button type="button" class="sr-chip" data-scope="week">This week</button>
                  <button type="button" class="sr-chip" data-scope="all">All invoices</button>
                </div>
              </div>
              <div class="sr-invlist" id="srInvList"></div>
            </div>
          </div>
          <div class="col-lg-7">
            <div class="card-mf sr-pane">
              <div class="sr-pane-head"><span class="sr-step">2</span>Pick what comes back<span class="ms-auto" id="srDeskMeta"></span></div>
              <div class="sr-desk-body" id="srDeskBody"></div>
              <div class="sr-bar" id="srBar" hidden>
                <select id="srReason" aria-label="Return reason">
                  <option>Damaged / expired stock</option>
                  <option>Wrong medicine sold</option>
                  <option>Customer changed mind</option>
                  <option>Doctor changed prescription</option>
                  <option>Overbilled correction</option>
                  <option>Other — add note</option>
                </select>
                <input type="text" id="srNote" placeholder="Note (optional)" maxlength="255" autocomplete="off">
                <div class="sr-modes" id="srModes">
                  <button type="button" class="sr-mode active" data-mode="cash">CASH</button>
                  <button type="button" class="sr-mode" data-mode="upi">UPI</button>
                  <button type="button" class="sr-mode" data-mode="card">CARD</button>
                  <button type="button" class="sr-mode" data-mode="credit">AGAINST DUES</button>
                </div>
                <div class="sr-total"><span id="srTotalLbl">Refund · 0 items</span><b id="srTotal" class="sr-num">₹0</b></div>
                <button class="btn btn-mf" id="srReview" type="button" disabled><i class="bi bi-check2-circle me-1"></i>Review &amp; process</button>
              </div>
            </div>
          </div>
        </div>

        <div class="card-mf sr-pane mt-3">
          <div class="sr-pane-head"><i class="bi bi-journal-arrow-down" style="color:var(--mf-primary)"></i>Recent returns<span class="ms-auto" id="srRetCount"></span></div>
          <div class="table-responsive">
            <table class="table table-mf sr-table mb-0">
              <thead><tr>
                <th>Return No</th><th>Date</th><th>Invoice</th><th>Customer</th><th>Reason</th><th>Mode</th>
                <th class="text-end">Qty</th><th class="text-end">Refund</th><th class="text-end">View</th>
              </tr></thead>
              <tbody id="srRetBody"></tbody>
            </table>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Confirm modal -->
  <div class="modal fade" id="srConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="border:0;border-radius:16px;overflow:hidden">
        <div class="modal-header" style="border-bottom:1px solid #EEF3F8">
          <h5 class="modal-title fw-bold"><i class="bi bi-shield-check me-1 text-success"></i>Confirm return</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="srConfirmBody"></div>
        <div class="modal-footer" style="border-top:1px solid #EEF3F8">
          <button type="button" class="btn btn-light-mf" data-bs-dismiss="modal">Go back</button>
          <button type="button" class="btn btn-mf" id="srProcess"><i class="bi bi-check-lg me-1"></i>Process return</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Return detail modal -->
  <div class="modal fade" id="srRetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content" style="border:0;border-radius:16px;overflow:hidden">
        <div class="modal-header" style="border-bottom:1px solid #EEF3F8">
          <h5 class="modal-title fw-bold" id="srRetTitle">Return</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="srRetView"></div>
        <div class="modal-footer" style="border-top:1px solid #EEF3F8">
          <button type="button" class="btn btn-light-mf" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-mf" id="srRetPrint"><i class="bi bi-printer me-1"></i>Print note</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    (function () {
      'use strict';
      const D = window.MF_DATA || {};   /* other pages pull the same handle (see pos.js) */
      const $ = (s, r) => (r || document).querySelector(s);
      const $$ = (s, r) => [...(r || document).querySelectorAll(s)];
      const todayStr = () => { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
      const ydayStr = () => { const d = new Date(); d.setDate(d.getDate() - 1); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
      const hm = (iso) => { const t = String(iso || '').slice(11, 16); return t && t !== '00:00' ? t : ''; };
      const modeLabel = { cash: 'CASH', upi: 'UPI', card: 'CARD', credit: 'AGAINST DUES' };

      const state = {
        invoices: [], q: '', scope: 'today',
        row: null, invoice: null, items: [], picks: new Map(),
        mode: 'cash', returns: [], summary: {}, busy: false,
        viewRet: null, viewItems: []
      };

      /* ---------------- data ---------------- */
      async function loadInvoices() {
        if (!MF.Api.live) { state.invoices = []; return; }
        try {
          const res = await MF.Api.get('sales-invoices.php');
          const rows = ((res.data || {}).ledger || []);
          state.invoices = rows.map((r) => ({
            id: r.id, invoice_no: r.invoice_no || '', sale_date: r.sale_date || '',
            created_at: r.created_at || '', customer_name: r.customer_name || 'Walk-in',
            customer_phone: r.customer_phone || '', channel: r.channel || 'retail',
            grand_total: Number(r.grand_total || 0), amount_paid: Number(r.amount_paid || 0),
            balance_due: Number(r.balance_due || 0),
            return_count: Number(r.return_count || 0), refund_amount: Number(r.refund_amount || 0)
          }));
        } catch (e) {
          MF.toast(e.message || 'Could not load invoices.', 'err', 'Load failed');
          state.invoices = [];
        }
      }

      async function loadReturns() {
        if (!MF.Api.live) { state.returns = []; state.summary = {}; return; }
        try {
          const res = await MF.Api.get('sales-return.php');
          state.returns = ((res.data || {}).ledger || []);
          state.summary = ((res.data || {}).summary || {});
        } catch (e) { /* ledger stays empty; the desk still works */ }
      }

      async function pickInvoice(row) {
        if (state.busy) return;
        state.row = row; state.invoice = null; state.items = []; state.picks.clear();
        renderInvList(); renderDesk();
        state.busy = true;
        $('#srDeskMeta').textContent = 'loading…';
        try {
          const res = await MF.Api.get('sales-invoices.php?id=' + encodeURIComponent(row.id));
          const data = res.data || {};
          state.invoice = data.invoice || null;
          const items = Array.isArray(data.items) ? data.items : [];
          state.items = items.map((it) => {
            const sold = Number(it.qty || 0);
            const returned = Number(it.returned_qty || 0);
            const remaining = Math.max(0, sold - returned);
            const per = (Number(it.amount) > 0 && sold > 0) ? Number(it.amount) / sold : Number(it.rate || 0);
            return {
              saleItemId: it.id, medicineName: it.medicine_name || 'Medicine',
              unit: it.unit || '', batchNo: it.batch_no || '', expiry: it.expiry_date || '',
              rate: Number(it.rate || 0), discPct: Number(it.disc_pct || 0),
              sold, returned, remaining, per
            };
          }).filter((it) => it.sold > 0);
          $('#srDeskMeta').textContent = row.invoice_no;
        } catch (e) {
          MF.toast(e.message || 'Could not load the invoice.', 'danger');
          state.row = null;
        } finally {
          state.busy = false;
          renderInvList(); renderDesk();
        }
      }

      /* ---------------- derived ---------------- */
      function inScope(r) {
        if (state.scope === 'all') return true;
        if (state.scope === 'today') return r.sale_date === todayStr() || String(r.created_at).slice(0, 10) === todayStr();
        if (state.scope === 'yday') return r.sale_date === ydayStr() || String(r.created_at).slice(0, 10) === ydayStr();
        const d = new Date(); d.setDate(d.getDate() - 6);
        const from = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        return (r.sale_date || '') >= from;
      }
      function visibleInvoices() {
        const q = state.q.trim().toLowerCase();
        return state.invoices
          .filter((r) => inScope(r) && (!q || [r.invoice_no, r.customer_name, r.customer_phone].join(' ').toLowerCase().includes(q)))
          .sort((a, b) => String(b.created_at || b.sale_date).localeCompare(String(a.created_at || a.sale_date)) || b.id - a.id);
      }
      function picksList() {
        const out = [];
        state.picks.forEach((qty, id) => {
          const it = state.items.find((x) => String(x.saleItemId) === String(id));
          if (it && qty > 0) out.push({ it, qty, refund: Math.round(it.per * qty * 100) / 100 });
        });
        return out;
      }
      const picksTotal = () => Math.round(picksList().reduce((s, p) => s + p.refund, 0) * 100) / 100;

      /* ---------------- render: finder ---------------- */
      function renderInvList() {
        const rows = visibleInvoices();
        const cap = 60;
        $('#srInvCount').textContent = rows.length ? rows.length + ' invoice(s)' : '';
        $('#srInvList').innerHTML = rows.slice(0, cap).map((r) => {
          const sel = state.row && String(state.row.id) === String(r.id) ? ' sel' : '';
          const t = hm(r.created_at);
          const pill = r.return_count > 0 ? `<span class="sr-pill ret">↩ ${r.return_count} return${r.return_count > 1 ? 's' : ''}</span>`
            : (r.balance_due > 0.009 ? '<span class="sr-pill due">DUE</span>' : '<span class="sr-pill paid">PAID</span>');
          return `<div class="sr-inv${sel}" data-inv="${MF.esc(r.id)}" role="button" tabindex="0">
            <div style="min-width:0">
              <div class="sr-inv-no">${MF.esc(r.invoice_no || '—')} ${pill}</div>
              <div class="sr-inv-when">${r.sale_date ? MF.fmtDate(r.sale_date) : ''}${t ? ' · ' + t : ''} · ${MF.esc(r.channel)}</div>
              <div class="sr-inv-cust">${MF.esc(r.customer_name)}${r.customer_phone ? ' · ' + MF.esc(r.customer_phone) : ''}</div>
            </div>
            <div class="amt"><b>${MF.fmt(r.grand_total)}</b><span class="sr-mut">${MF.fmt(r.amount_paid)} paid</span></div>
          </div>`;
        }).join('') || `<div class="sr-empty"><i class="bi bi-receipt"></i>No invoices in this slice. Try a wider chip or search.</div>`;
        if (rows.length > cap) $('#srInvList').insertAdjacentHTML('beforeend', `<div class="sr-moreline">…and ${rows.length - cap} more — refine the search</div>`);
        $$('#srInvList .sr-inv').forEach((el) => {
          el.addEventListener('click', () => openById(el.dataset.inv));
          el.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openById(el.dataset.inv); } });
        });
      }
      function openById(id) {
        const row = state.invoices.find((r) => String(r.id) === String(id));
        if (row) pickInvoice(row);
      }

      /* ---------------- render: desk ---------------- */
      function tagExp(it) {
        if (!it.expiry) return '';
        const past = String(it.expiry).slice(0, 10) < todayStr();
        return `<span class="sr-tag exp${past ? ' past' : ''}"><i class="bi bi-hourglass-split"></i>exp ${MF.fmtDate(it.expiry)}${past ? ' · past' : ''}</span>`;
      }
      function renderDesk() {
        const host = $('#srDeskBody');
        if (!state.row) {
          host.innerHTML = `<div class="sr-empty"><i class="bi bi-hand-index-thumb"></i>Pick an invoice on the left — its lines show up here for return.</div>`;
          $('#srBar').hidden = true;
          return;
        }
        if (state.busy && !state.items.length) {
          host.innerHTML = `<div class="sr-empty"><i class="bi bi-arrow-repeat"></i>Loading invoice lines…</div>`;
          $('#srBar').hidden = true;
          return;
        }
        const inv = state.invoice || {};
        const due = Number(inv.balance_due ?? state.row.balance_due ?? 0);
        const t = hm(inv.created_at || state.row.created_at);
        const allLeft = state.items.reduce((s, it) => s + it.remaining, 0);
        host.innerHTML = `
          <div class="sr-bill">
            <div><div class="no">${MF.esc(state.row.invoice_no)}</div>
            <div class="meta">${inv.sale_date || state.row.sale_date ? MF.fmtDate(inv.sale_date || state.row.sale_date) : ''}${t ? ' · ' + t : ''} · ${MF.esc(state.row.customer_name)}${state.row.customer_phone ? ' · ' + MF.esc(state.row.customer_phone) : ''}</div></div>
            <div class="nums">
              <div><span>Billed</span><b>${MF.fmt(inv.grand_total ?? state.row.grand_total)}</b></div>
              <div><span>Paid</span><b>${MF.fmt(inv.amount_paid ?? state.row.amount_paid)}</b></div>
              <div class="val-due"><span>Due</span><b>${MF.fmt(due)}</b></div>
            </div>
          </div>
          ${due > 0.009 ? `<div class="sr-duenote"><i class="bi bi-info-circle me-1"></i>₹${MF.fmt(due)} is still due on this bill. The return settles the due first; refund cash/UPI only for the rest.</div>` : ''}
          <div class="sr-allrow">
            <button type="button" class="sr-all" id="srAllBtn" ${allLeft ? '' : 'disabled'}><i class="bi bi-check2-all me-1"></i>Return entire bill</button>
            <button type="button" class="sr-ghost" id="srClearBtn"><i class="bi bi-x-lg me-1"></i>Clear picks</button>
            <span class="sr-mut">Refund mirrors the price actually paid per unit — the server re-locks the exact figure at save.</span>
          </div>
          <div id="srLines">${state.items.map((it) => lineHtml(it)).join('')}</div>`;
        $('#srBar').hidden = false;
        wireDesk();
        recalcBar();
      }

      function lineHtml(it) {
        const pick = state.picks.has(String(it.saleItemId));
        const qty = state.picks.get(String(it.saleItemId)) || 1;
        const cls = it.remaining <= 0 ? ' done' : (pick ? ' on' : '');
        return `<div class="sr-line${cls}" data-li="${MF.esc(it.saleItemId)}">
          <input class="sr-check" type="checkbox" data-role="ck" ${pick ? 'checked' : ''} ${it.remaining <= 0 ? 'disabled' : ''} aria-label="Return this line">
          <div class="sr-gro">
            <div class="sr-med">${MF.esc(it.medicineName)}${it.unit ? ` <span class="sr-mut">· ${MF.esc(it.unit)}</span>` : ''}</div>
            <div class="sr-tags">
              ${it.batchNo ? `<span class="sr-tag"><i class="bi bi-upc"></i>${MF.esc(it.batchNo)}</span>` : ''}
              ${tagExp(it)}
              <span class="sr-tag">sold ${MF.num(it.sold)}</span>
              ${it.returned > 0 ? `<span class="sr-tag retn">returned ${MF.num(it.returned)}</span>` : ''}
              <span class="sr-tag left">left ${MF.num(it.remaining)}</span>
              ${it.discPct > 0 ? `<span class="sr-tag">${it.discPct}% off applied</span>` : ''}
            </div>
          </div>
          <div class="sr-qty">
            <div class="sr-stepper">
              <button type="button" data-role="minus" ${!pick ? 'disabled' : ''}>−</button>
              <input type="number" data-role="qty" min="1" max="${it.remaining}" step="1" value="${qty}" ${!pick ? 'disabled' : ''} aria-label="Return qty">
              <button type="button" data-role="plus" ${!pick ? 'disabled' : ''}>+</button>
            </div>
            <button type="button" class="sr-max" data-role="max" ${it.remaining <= 0 ? 'disabled' : ''}>MAX</button>
          </div>
          <div class="sr-ref"><b>${pick ? MF.fmt(it.per * qty) : '—'}</b><span>refund</span></div>
        </div>`;
      }

      function wireDesk() {
        const all = $('#srAllBtn'), clear = $('#srClearBtn');
        if (all) all.addEventListener('click', () => {
          state.items.forEach((it) => { if (it.remaining > 0) state.picks.set(String(it.saleItemId), it.remaining); });
          renderDesk();
        });
        if (clear) clear.addEventListener('click', () => { state.picks.clear(); renderDesk(); });
        $$('#srLines .sr-line').forEach((el) => {
          const id = el.dataset.li;
          const it = state.items.find((x) => String(x.saleItemId) === String(id));
          if (!it) return;
          el.querySelector('[data-role="ck"]').addEventListener('change', (e) => {
            if (e.target.checked) state.picks.set(id, Math.min(state.picks.get(id) || 1, it.remaining));
            else state.picks.delete(id);
            renderDesk();
          });
          const clampSet = (v) => {
            v = Math.round(Number(v) || 1);
            v = Math.max(1, Math.min(it.remaining, v));
            state.picks.set(id, v);
            renderDesk();
          };
          el.querySelector('[data-role="minus"]').addEventListener('click', () => clampSet((state.picks.get(id) || 1) - 1));
          el.querySelector('[data-role="plus"]').addEventListener('click', () => clampSet((state.picks.get(id) || 1) + 1));
          el.querySelector('[data-role="qty"]').addEventListener('change', (e) => clampSet(e.target.value));
          const mx = el.querySelector('[data-role="max"]');
          if (mx) mx.addEventListener('click', () => { if (it.remaining > 0) { state.picks.set(id, it.remaining); renderDesk(); } });
        });
      }

      function recalcBar() {
        const list = picksList();
        const units = list.reduce((s, p) => s + p.qty, 0);
        const total = picksTotal();
        $('#srTotal').textContent = MF.fmt(total);
        $('#srTotalLbl').textContent = `Refund · ${list.length} line${list.length === 1 ? '' : 's'} · ${units} unit${units === 1 ? '' : 's'}`;
        $('#srReview').disabled = !list.length;
      }

      /* ---------------- confirm + process ---------------- */
      function openConfirm() {
        const list = picksList();
        if (!list.length) return;
        const total = picksTotal();
        const reason = $('#srReason').value;
        const note = $('#srNote').value.trim();
        $('#srConfirmBody').innerHTML = `
          <div class="mb-2" style="font-size:.8rem;font-weight:650;color:#51637B">${MF.esc(state.row.invoice_no)} · ${MF.esc(state.row.customer_name)}</div>
          <div style="max-height:220px;overflow:auto">
            ${list.map((p) => `<div class="d-flex justify-content-between py-1" style="border-bottom:1px dashed #EEF3F8">
              <span class="fw-semibold" style="font-size:.86rem">${MF.esc(p.it.medicineName)} <span class="sr-mut">× ${p.qty}</span></span>
              <span class="sr-num fw-semibold">${MF.fmt(p.refund)}</span>
            </div>`).join('')}
          </div>
          <div class="d-flex justify-content-between align-items-center mt-2 p-2 rounded" style="background:#EEF7F4;border:1px solid #CBE6DE">
            <span class="fw-bold" style="color:#14584C">Refund to customer</span>
            <b class="sr-num" style="font-size:1.1rem;color:#176B5B">${MF.fmt(total)}</b>
          </div>
          <div class="mt-2" style="font-size:.78rem;color:#51637B">
            <div><i class="bi bi-tag me-1"></i>${MF.esc(reason)}${note ? ' — <i>' + MF.esc(note) + '</i>' : ''}</div>
            <div><i class="bi bi-wallet2 me-1"></i>Mode: <b>${modeLabel[state.mode]}</b></div>
          </div>
          <div class="sr-mut mt-2">Stock goes back to the exact batches these were sold from. This step cannot be edited afterwards.</div>`;
        bootstrap.Modal.getOrCreateInstance($('#srConfirmModal')).show();
      }

      async function processReturn() {
        const list = picksList();
        if (!list.length || state.busy) return;
        state.busy = true;
        const btn = $('#srProcess');
        const old = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';
        try {
          const res = await MF.Api.post('sales-return.php', {
            saleId: state.row.id,
            reason: $('#srReason').value,
            note: $('#srNote').value.trim(),
            refundMode: state.mode,
            items: list.map((p) => ({ saleItemId: Number(p.it.saleItemId), qty: p.qty }))
          });
          bootstrap.Modal.getInstance($('#srConfirmModal')).hide();
          const out = (res && res.data) ? res.data : res;
          showSuccess(out, list);
          await loadReturns(); renderStats(); renderLedger();
          const idx = state.invoices.findIndex((r) => String(r.id) === String(state.row.id));
          if (idx >= 0) state.invoices[idx].return_count += 1;
          renderInvList();
        } catch (e) {
          MF.toast(e.message || 'The return could not be processed.', 'danger');
        } finally {
          state.busy = false;
          btn.disabled = false; btn.innerHTML = old;
        }
      }

      function showSuccess(out, list) {
        const refund = Number(out.refundAmount || 0);
        const dueNow = out.balanceDue != null ? Number(out.balanceDue) : null;
        $('#srDeskMeta').textContent = 'done';
        $('#srDeskBody').innerHTML = `
          <div class="sr-win">
            <i class="bi bi-check-circle-fill"></i>
            <h4>Return processed</h4>
            <div class="srn">${MF.esc(out.returnNo || '—')}</div>
            <div class="sub">${list.length} line(s) · refund <b>${MF.fmt(refund)}</b> via ${MF.esc(String(out.refundMode || state.mode).toUpperCase())}
              ${dueNow != null ? `<br>That invoice's due is now <b>${MF.fmt(dueNow)}</b>.` : ''}</div>
            <div class="d-flex gap-2 justify-content-center mt-3 flex-wrap">
              <button type="button" class="btn btn-mf" id="srWinPrint"><i class="bi bi-printer me-1"></i>Print return note</button>
              <button type="button" class="btn btn-light-mf" id="srWinNew"><i class="bi bi-arrow-counterclockwise me-1"></i>New return</button>
            </div>
          </div>`;
        $('#srBar').hidden = true;
        $('#srWinNew').addEventListener('click', () => { state.row = null; state.invoice = null; state.items = []; state.picks.clear(); $('#srDeskMeta').textContent = ''; renderDesk(); renderInvList(); });
        $('#srWinPrint').addEventListener('click', () => printNote({
          return_no: out.returnNo, invoice_no: state.row ? state.row.invoice_no : '',
          customer_name: state.row ? state.row.customer_name : '',
          date: todayStr(), time: new Date().toTimeString().slice(0, 5),
          reason: $('#srReason').value, mode: modeLabel[out.refundMode || state.mode] || 'CASH',
          lines: list.map((p) => ({ name: p.it.medicineName, batch: p.it.batchNo, qty: p.qty, amount: p.refund })),
          refund
        }));
        state.picks.clear();
      }

      /* ---------------- print ---------------- */
      /* Direct-to-paper print: the app's preview helper stacks Bootstrap modals,
         which swallows the click when a modal is already open (both print entry
         points here sit on/behind one). A guarded iframe has no modal at all. */
      function srSilentPrint(innerHtml) {
        const frame = document.createElement('iframe');
        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
        document.body.appendChild(frame);
        const doc = frame.contentWindow.document;
        doc.open();
        doc.write('<!doctype html><html><head><meta charset="utf-8"><title>Return note</title></head><body>' + innerHtml + '</body></html>');
        doc.close();
        const done = () => setTimeout(() => { try { frame.remove(); } catch (e) { } }, 500);
        try {
          frame.contentWindow.addEventListener('afterprint', done);
          frame.contentWindow.focus();
          setTimeout(() => {
            try { frame.contentWindow.print(); } finally { done(); }
          }, 90);
        } catch (e) { done(); }
      }

      function printNote(m) {
        const store = D.store || {};
        const html = `<style>
          @page { size: A5; margin: 12mm; }
          body { font-family: Arial, Helvetica, sans-serif; color: #111827; font-size: 10.5px; line-height: 1.5; }
          .sh { display:flex; gap:10px; align-items:flex-start; border-bottom:2.5px solid #B02A37; padding-bottom:8px; }
          .lg { width:38px; height:38px; border-radius:10px; background:#B02A37; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:800; }
          .nm { font-size:15px; font-weight:800; } .sb { font-size:8.5px; color:#4b5563; margin-top:2px; }
          .rt { margin-left:auto; text-align:right; font-size:9px; color:#4b5563; line-height:1.6; }
          .tag { display:inline-block; border:1.5px solid #B02A37; color:#B02A37; font-size:8.5px; font-weight:800; letter-spacing:.12em; padding:2px 8px; border-radius:4px; text-transform:uppercase; }
          table { width:100%; border-collapse:collapse; margin:8px 0; }
          th, td { border-bottom:1px solid #e5e7eb; padding:4px 5px; font-size:9px; text-align:left; }
          th { border-top:1px solid #111827; font-size:7.5px; text-transform:uppercase; letter-spacing:.06em; color:#4b5563; }
          .r { text-align:right; } .b { font-weight:700; }
          .tot { display:flex; justify-content:space-between; align-items:center; border:1.5px solid #111827; border-radius:8px; padding:8px 10px; margin:8px 0 2px; }
          .amt { font-size:16px; font-weight:800; }
          .fs { font-size:8px; color:#6b7280; line-height:1.55; margin-top:6px; }
          .sg { margin-top:24px; display:flex; justify-content:space-between; font-size:8.5px; }
          .sg div { border-top:1px solid #111827; padding-top:3px; min-width:34mm; text-align:center; }
        </style>
        <div class="sh">
          <div class="lg">Rx</div>
          <div><div class="nm">${MF.esc(store.name || 'Optms Rx')}</div><div class="sb">${MF.esc(store.address || '')}${store.gstin ? ' · GSTIN ' + MF.esc(store.gstin) : ''}</div></div>
          <div class="rt"><span class="tag">Sales return</span><br>${MF.esc(m.return_no || '')}<br>${MF.esc(m.date || '')} ${MF.esc(m.time || '')}</div>
        </div>
        <table>
          <tr><td style="border:0;padding-top:6px"><b>Against invoice:</b> ${MF.esc(m.invoice_no || '—')}</td>
              <td class="r" style="border:0;padding-top:6px"><b>Customer:</b> ${MF.esc(m.customer_name || 'Walk-in')}</td></tr>
        </table>
        <table>
          <thead><tr><th>Item returned</th><th>Batch</th><th class="r">Qty</th><th class="r">Refund</th></tr></thead>
          <tbody>${(m.lines || []).map((l) => `<tr><td>${MF.esc(l.name)}</td><td>${MF.esc(l.batch || '—')}</td><td class="r">${MF.num(l.qty)}</td><td class="r">${MF.fmt(l.amount)}</td></tr>`).join('')}</tbody>
        </table>
        <div class="tot"><span><b>REFUND DUE BACK</b><div class="fs" style="margin-top:0">via ${MF.esc(m.mode || 'CASH')} · ${MF.esc(m.reason || '')}</div></span><span class="amt">${MF.fmt(m.refund || 0)}</span></div>
        <p class="fs">Returned stock is restocked to the original batches. This note is system-generated from the sales ledger; refunded money follows the mode above. Reason: ${MF.esc(m.reason || 'Customer return')}.</p>
        <div class="sg"><div>Customer signature</div><div>For ${MF.esc(store.name || 'Optms Rx')}</div></div>`;
        srSilentPrint(html);
      }

      /* ---------------- stats + returns ledger ---------------- */
      function renderStats() {
        const s = state.summary || {};
        $('#srStats').innerHTML = `
          <div class="col-6 col-md-3"><div class="sr-stat accent"><span>Returns today</span><strong>${MF.num(s.today_count || 0)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="sr-stat"><span>Refunded today</span><strong>${MF.fmt(s.today_amount || 0)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="sr-stat"><span>Refunded this month</span><strong>${MF.fmt(s.month_amount || 0)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="sr-stat"><span>Logged returns</span><strong>${MF.num(state.returns.length)}</strong></div></div>`;
      }

      function renderLedger() {
        const rows = state.returns;
        $('#srRetCount').textContent = rows.length ? rows.length + ' recent' : '';
        $('#srRetBody').innerHTML = rows.map((r) => `
          <tr>
            <td class="sr-num fw-bold">${MF.esc(r.return_no || '—')}</td>
            <td class="sr-num">${r.return_date ? MF.fmtDate(r.return_date) : '—'}${hm(r.created_at) ? `<span class="sr-mut"> ${hm(r.created_at)}</span>` : ''}</td>
            <td class="sr-num">${MF.esc(r.invoice_no || '—')}</td>
            <td>${MF.esc(r.customer_name || 'Walk-in')}</td>
            <td><span title="${MF.esc(r.note || '')}">${MF.esc(r.reason || '—')}</span></td>
            <td>${r.refund_mode ? `<span class="sr-sq">${MF.esc(String(r.refund_mode).toUpperCase())}</span>` : '<span class="sr-mut">—</span>'}</td>
            <td class="text-end sr-num">${MF.num(r.qty || 0)}</td>
            <td class="text-end sr-num fw-bold" style="color:#B02A37">${MF.fmt(r.refund_amount || 0)}</td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-mf-soft" data-vr="${MF.esc(r.id)}">View</button></td>
          </tr>`).join('') || '<tr><td colspan="9"><div class="empty-state"><i class="bi bi-journal"></i>No returns logged yet.</div></td></tr>';
        $$('#srRetBody [data-vr]').forEach((b) => b.addEventListener('click', () => openReturn(+b.dataset.vr)));
      }

      async function openReturn(id) {
        try {
          const res = await MF.Api.get('sales-return.php?id=' + encodeURIComponent(id));
          const data = res.data || {};
          state.viewRet = data.return || null;
          state.viewItems = data.items || [];
          if (!state.viewRet) throw new Error('Return not found.');
          const r = state.viewRet;
          $('#srRetTitle').textContent = (r.return_no || 'Return') + ' · ' + (r.invoice_no || '');
          $('#srRetView').innerHTML = `
            <div class="d-flex flex-wrap gap-3 mb-2" style="font-size:.84rem">
              <div><div class="sr-mut">Customer</div><b>${MF.esc(r.customer_name || 'Walk-in')}</b></div>
              <div><div class="sr-mut">Date</div><b>${r.return_date ? MF.fmtDate(r.return_date) : '—'} ${hm(r.created_at)}</b></div>
              <div><div class="sr-mut">Mode</div><b>${MF.esc(String(r.refund_mode || '—').toUpperCase())}</b></div>
              <div><div class="sr-mut">Reason</div><b>${MF.esc(r.reason || '—')}</b></div>
              ${r.note ? `<div style="flex-basis:100%"><div class="sr-mut">Note</div><span>${MF.esc(r.note)}</span></div>` : ''}
            </div>
            <table class="table table-mf mb-0"><thead><tr><th>Item</th><th>Batch</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Refund</th></tr></thead>
            <tbody>${state.viewItems.map((it) => `<tr><td>${MF.esc(it.medicine_name)}</td><td class="sr-num">${MF.esc(it.batch_no || '—')}</td><td class="text-end sr-num">${MF.num(it.qty)}</td><td class="text-end sr-num">${MF.fmt(it.rate)}</td><td class="text-end sr-num fw-semibold">${MF.fmt(it.amount)}</td></tr>`).join('')}</tbody></table>
            <div class="d-flex justify-content-end mt-2"><b style="color:#B02A37">Total refund: ${MF.fmt(r.refund_amount || 0)}</b></div>`;
          bootstrap.Modal.getOrCreateInstance($('#srRetModal')).show();
        } catch (e) {
          MF.toast(e.message || 'Could not open the return.', 'danger');
        }
      }

      /* ---------------- wire ---------------- */
      $('#srSearch').addEventListener('input', (e) => { state.q = e.target.value; renderInvList(); });
      $$('#srScopes .sr-chip').forEach((c) => c.addEventListener('click', () => {
        $$('#srScopes .sr-chip').forEach((x) => x.classList.remove('active'));
        c.classList.add('active');
        state.scope = c.dataset.scope;
        renderInvList();
      }));
      $$('#srModes .sr-mode').forEach((m) => m.addEventListener('click', () => {
        $$('#srModes .sr-mode').forEach((x) => x.classList.remove('active'));
        m.classList.add('active'); state.mode = m.dataset.mode;
      }));
      $('#srReview').addEventListener('click', openConfirm);
      $('#srProcess').addEventListener('click', processReturn);
      $('#srRetPrint').addEventListener('click', () => {
        const r = state.viewRet;
        if (!r) return;
        printNote({
          return_no: r.return_no, invoice_no: r.invoice_no, customer_name: r.customer_name,
          date: r.return_date, time: hm(r.created_at), reason: r.reason,
          mode: String(r.refund_mode || 'CASH').toUpperCase(),
          lines: state.viewItems.map((it) => ({ name: it.medicine_name, batch: it.batch_no, qty: it.qty, amount: it.amount })),
          refund: Number(r.refund_amount || 0)
        });
      });
      $('#srRefresh').addEventListener('click', async () => {
        state.row = null; state.invoice = null; state.items = []; state.picks.clear();
        $('#srDeskMeta').textContent = '';
        renderDesk();
        await loadInvoices(); await loadReturns();
        renderInvList(); renderStats(); renderLedger();
        MF.toast('Desk refreshed.', 'success');
      });

      /* ---------------- boot ---------------- */
      (async function init() {
        await MF.boot();
        renderDesk(); renderStats(); renderLedger();
        await Promise.all([loadInvoices(), loadReturns()]);
        renderInvList(); renderStats(); renderLedger();
        const deep = new URLSearchParams(location.search).get('invoice');
        if (deep) {
          state.scope = 'all';
          $$('#srScopes .sr-chip').forEach((x) => x.classList.toggle('active', x.dataset.scope === 'all'));
          state.q = deep;
          $('#srSearch').value = deep;
          renderInvList();
          const hit = state.invoices.find((r) => String(r.invoice_no || '').toLowerCase() === deep.toLowerCase());
          if (hit) pickInvoice(hit);
        }
      })();
    })();
  });
  </script>
</body>
</html>
