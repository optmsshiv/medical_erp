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
  <title>Sales Invoices · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .si-stat { background:#f8fafc; border:1px solid #e7edf4; border-radius:14px; padding:12px 14px; }
    .si-stat span { display:block; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#6c757d; }
    .si-stat strong { display:block; margin-top:3px; font-size:1.2rem; font-weight:750; letter-spacing:-.02em; color:#1b2430; }
    .si-stat.accent { background:#e8eef8; border-color:#d7e2f2; }
    .si-kebab { width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; padding:0; }
    .si-act-menu { min-width:196px; padding:6px; border:1px solid #e7edf4; border-radius:12px; box-shadow:0 12px 32px rgba(16,32,64,.14); z-index:1080; }
    .si-act-menu .dropdown-item { display:flex; align-items:center; gap:10px; font-size:.84rem; font-weight:600; border-radius:8px; padding:.48rem .65rem; }
    .si-act-menu .dropdown-item i { width:1.05rem; color:#16325c; }
    .si-act-menu .dropdown-item:hover { background:#f4f7fb; }
    .si-name { font-weight:700; color:#1b2430; }
    .si-muted { display:block; margin-top:2px; color:#8b9bb0; font-size:.75rem; font-weight:600; line-height:1.3; }
    .si-batch { display:inline-flex; align-items:center; gap:4px; margin-top:4px; background:#f4f7fb; color:#16325c; border:1px solid #e4ebf4; border-radius:4px; padding:2px 6px; font-size:11px; font-weight:700; }
    .si-batch i { font-size:12px; }
    .si-wide { table-layout:fixed; width:100%; }
    .si-ledger { border:1px solid #e3ebf4; border-radius:16px; background:#fff; overflow:hidden; box-shadow:0 1px 2px rgba(22,50,92,.04), 0 10px 28px rgba(22,50,92,.04); }
    .si-ledger-search { display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid #e7eef6; background:#fff; flex-wrap:wrap; }
    .si-ledger-search > i { color:#8b9bb0; font-size:15px; }
    .si-ledger-search input[type="text"], .si-ledger-search input:not([type]) { border:0; outline:0; box-shadow:none !important; background:transparent; width:100%; min-width:180px; flex:1; padding:4px 0; font-size:.92rem; color:#1b2430; }
    .si-ledger-search input::placeholder { color:#9aa8b8; }
    .si-date { width:auto !important; flex:0 0 auto !important; border:1px solid #e3ebf4 !important; border-radius:8px !important; padding:4px 8px !important; font-size:.8rem !important; color:#516278 !important; background:#fff !important; }
    .si-filters { display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:10px 16px; border-bottom:1px solid #e7eef6; background:#fbfcfe; }
    .si-filters .si-label { font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#8b9bb0; margin-right:2px; }
    .si-chip { border:1px solid #e3ebf4; background:#fff; color:#516278; border-radius:999px; padding:4px 10px; font-size:.75rem; font-weight:700; }
    .si-chip.active { background:#16325c; border-color:#16325c; color:#fff; }
    .si-ledger .table-mf { margin:0; }
    .si-ledger .table-mf thead th { background:#f7f9fc; color:#7b8798; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; border-bottom:1px solid #e7eef6; padding:12px 14px; white-space:nowrap; }
    .si-ledger .table-mf tbody td { border-bottom:1px solid #f0f4f8; padding:13px 14px; vertical-align:middle; }
    .si-ledger .table-mf tbody tr:last-child td { border-bottom:0; }
    .si-ledger .table-mf tbody tr:hover td { background:#f8fbfe; }
    .si-ledger-foot { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:12px 16px; border-top:1px solid #e3ebf4; background:#fff; }
    .si-ledger-foot .pagination { gap:4px; }
    .si-ledger-foot .page-link { border:1px solid #e3ebf4; color:#516278; border-radius:8px; min-width:32px; text-align:center; font-weight:650; padding:.3rem .55rem; }
    .si-ledger-foot .page-item.active .page-link { background:#16325c; border-color:#16325c; color:#fff; }
    .si-ledger-foot .page-link:hover { background:#f4f7fb; color:#16325c; }
    .si-ledger-note { color:#8b9bb0; font-size:.78rem; line-height:1.45; padding:10px 16px 12px; margin:0; border-top:1px solid #eef3f8; background:#fbfcfe; }
    .si-due { color:#B02A37; font-weight:700; }
    /* ------- Premium invoice ledger pass ------- */
    .si-ellipsis { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .si-time { display:block; font-size:.68rem; font-weight:650; color:#8B9BB0; white-space:nowrap; }
    .si-doc { display:inline-block; max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:bottom; font-weight:650; color:#1B2430; }
    .si-doc-add { border:1px dashed #C7D3E0; background:#F8FAFC; color:#516278; border-radius:7px; font-size:.7rem; font-weight:700; padding:2px 8px; display:inline-flex; align-items:center; gap:4px; cursor:pointer; transition:all .15s ease; }
    .si-doc-add i { font-size:.72rem; color:#8B9BB0; }
    .si-doc-add:hover { border-color:var(--mf-primary); color:var(--mf-primary-dark); background:var(--mf-primary-soft); }
    .si-num { font-variant-numeric:tabular-nums; }
    .si-bar { height:4px; width:78px; max-width:100%; border-radius:4px; background:#EDF1F4; margin:5px 0 0 auto; overflow:hidden; }
    .si-bar i { display:block; height:100%; border-radius:4px; background:linear-gradient(90deg,#34B399,#176B5B); }
    .si-row-due > td:first-child { box-shadow:inset 3px 0 0 #E5484D; }
    .si-ledger .table-mf tbody tr.si-row-due:hover td { background:#FEF6F6; }
    @media (max-width:1199.98px) { .si-h-lg { display:none; } }
    @media (max-width:767.98px) { .si-h-md { display:none; } }
    #siDocDialog { position:fixed; inset:0; margin:auto; width:min(430px,94vw); max-height:86dvh; border:0; border-radius:16px; padding:16px; box-shadow:0 30px 90px rgba(16,32,64,.35); background:#fff; z-index:1085; font-family:system-ui,-apple-system,'Segoe UI',sans-serif; }
    #siDocDialog::backdrop { background:rgba(15,23,42,.38); backdrop-filter:blur(6px) saturate(1.03); }
  </style>
</head>
<body data-page="sales-invoices">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">
        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-file-earmark-text me-2 text-success"></i>Sales Invoices</h1>
            <p class="page-sub" id="siCount">Posted bills from the sales ledger. This is not the sales report.</p>
          </div>
          <div class="ms-auto d-flex gap-2 flex-wrap justify-content-end">
            <a class="btn btn-mf-outline" href="reports.php?tab=sales"><i class="bi bi-graph-up me-1"></i>Open sales report</a>
            <button class="btn btn-light-mf" id="siExport" type="button"><i class="bi bi-download me-1"></i>Export CSV</button>
            <a class="btn btn-mf-soft" href="wholesale-billing.php"><i class="bi bi-receipt me-1"></i>Wholesale</a>
            <a class="btn btn-mf" href="retail-pos.php"><i class="bi bi-cart-plus me-1"></i>New retail sale</a>
          </div>
        </div>

        <div class="row g-3 mb-3" id="siStats"></div>

        <div class="card-mf si-ledger">
          <div class="si-ledger-search">
            <i class="bi bi-search"></i>
            <input id="siSearch" name="si-list-filter" placeholder="Search invoice, customer, phone, doctor…" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true">
            <input class="si-date" id="siFrom" type="date" aria-label="From date">
            <input class="si-date" id="siTo" type="date" aria-label="To date">
          </div>
          <div class="si-filters" id="siFilters"></div>
          <div class="table-scroll" style="max-height:none;overflow:visible">
            <table class="table table-mf si-wide">
              <thead>
                <tr>
                  <th style="width:11%">Invoice</th>
                  <th style="width:9%">Date</th>
                  <th style="width:15%">Customer</th>
                  <th style="width:11%">Doctor</th>
                  <th class="text-end si-h-md" style="width:6%">Items</th>
                  <th class="si-h-lg" style="width:9%">Payment</th>
                  <th class="text-end" style="width:8%">Total</th>
                  <th class="text-end" style="width:8%">Paid</th>
                  <th class="text-end" style="width:8%">Due</th>
                  <th style="width:7%">Status</th>
                  <th class="text-end" style="width:8%">Actions</th>
                </tr>
              </thead>
              <tbody id="siBody"></tbody>
            </table>
          </div>
          <div class="si-ledger-foot">
            <span class="text-2 small" id="siPageInfo"></span>
            <ul class="pagination pagination-sm mb-0" id="siPager"></ul>
          </div>
          <p class="si-ledger-note">Invoice totals, paid and due are stored on the sale. Item count and returns are computed live from sale items and sales returns. This page is not the sales report.</p>
        </div>
      </main>
    </div>
  </div>

  <div class="modal fade" id="siViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="siViewTitle">Invoice</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
        </div>
        <div class="modal-body" id="siViewBody"></div>
        <div class="modal-footer">
          <a class="btn btn-light-mf me-auto" href="sales-return.php"><i class="bi bi-arrow-counterclockwise me-1"></i>Sales return</a>
          <button class="btn btn-light-mf" data-bs-dismiss="modal" type="button">Close</button>
          <button class="btn btn-mf" id="siPrint" type="button"><i class="bi bi-printer me-1"></i>Print</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
    (function () {
      const MF = window.MF, D = window.MF_DATA || {};
      const $ = (s) => document.querySelector(s);
      const PAY = { cash: 'Cash', upi: 'UPI', card: 'Card', bank: 'Bank', credit: 'Credit', split: 'Split' };
      const state = { rows: [], q: '', channel: 'all', status: 'all', from: '', to: '', page: 1, per: 12, current: null, opened: false };

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

      function statusOf(row) {
        const grand = Number(row.grand_total) || 0;
        const refund = Number(row.refund_amount) || 0;
        const due = row.balance_due == null || row.balance_due === '' ? null : Number(row.balance_due);
        const paid = row.amount_paid == null || row.amount_paid === '' ? null : Number(row.amount_paid);
        if (refund > 0.009 && grand > 0 && refund + 0.05 >= grand) return 'Returned';
        if (refund > 0.009) return 'Part returned';
        if (due != null && due <= 0.009) return 'Paid';
        if (paid != null && paid > 0.009 && due != null && due > 0.009) return 'Partial';
        if (due != null && due > 0.009) return 'Due';
        const known = String(row.status || '');
        if (/part/i.test(known) && /return/i.test(known)) return 'Part returned';
        if (/return/i.test(known)) return 'Returned';
        if (/partial|credit/i.test(known)) return 'Partial';
        if (/due/i.test(known)) return 'Due';
        if (/paid/i.test(known)) return 'Paid';
        return due == null ? 'Paid' : 'Due';
      }

      function shape(raw, idx) {
        const amount = Number(raw.amount ?? raw.grand_total ?? raw.grandTotal ?? 0);
        const paid = raw.amount_paid != null ? Number(raw.amount_paid) : (raw.paid != null ? Number(raw.paid) : null);
        const due = raw.balance_due != null ? Number(raw.balance_due) : (raw.due != null ? Number(raw.due) : null);
        const channel = String(raw.channel || raw.type || 'retail').toLowerCase().includes('whole') ? 'wholesale' : 'retail';
        const lines = Array.isArray(raw.items) ? raw.items : (Array.isArray(raw.lines) ? raw.lines : []);
        const row = {
          id: raw.id || idx + 1,
          invoice_no: raw.invoice_no || raw.no || '',
          sale_date: String(raw.sale_date || raw.date || '').slice(0, 10),
          sale_at: String(raw.created_at || raw.sale_at || ''),
          sale_time: (() => { const t = String(raw.created_at || raw.sale_at || '').slice(11, 16); return t && t !== '00:00' ? t : ''; })(),
          doctor_id: raw.doctor_id != null && raw.doctor_id !== '' ? Number(raw.doctor_id) : null,
          channel,
          customer_name: raw.customer_name || raw.customer || 'Walk-in',
          customer_phone: raw.customer_phone || raw.phone || raw.mobile || '',
          doctor_name: raw.doctor_name || raw.doctor || '',
          doctor_reg_no: raw.doctor_reg_no || '',
          doctor_specialty: raw.doctor_specialty || '',
          gstin: raw.gstin || '',
          dl_no: raw.dl_no || raw.dl || '',
          subtotal: Number(raw.subtotal ?? amount),
          discount: Number(raw.discount || 0),
          gst_amount: Number(raw.gst_amount || raw.gst || 0),
          cgst: Number(raw.cgst || 0),
          sgst: Number(raw.sgst || 0),
          igst: Number(raw.igst || 0),
          round_off: Number(raw.round_off || raw.roundOff || 0),
          grand_total: amount,
          payment_mode: String(raw.payment_mode || raw.payment || raw.mode || 'cash').toLowerCase(),
          cash_amount: Number(raw.cash_amount || 0),
          upi_amount: Number(raw.upi_amount || 0),
          amount_paid: paid,
          balance_due: due,
          item_count: Number(raw.item_count || lines.length || 0),
          qty: Number(raw.qty || 0),
          free_qty: Number(raw.free_qty || 0),
          return_count: Number(raw.return_count || 0),
          refund_amount: Number(raw.refund_amount || raw.refund || 0),
          status: raw.status || ''
        };
        row.status = statusOf(row);
        if (paid == null) row.amount_paid = row.status === 'Paid' ? amount : 0;
        if (due == null) row.balance_due = Math.max(amount - row.amount_paid, 0);
        row.status = statusOf(row);
        row.items = lines.map((l, i) => ({
          id: l.id || i + 1,
          medicine_name: l.medicine_name || l.name || l.medicine || '',
          batch_no: l.batch_no || l.batchNo || l.batch || '',
          expiry_date: l.expiry_date || l.expiry || '',
          qty: Number(l.qty || 0),
          free_qty: Number(l.free_qty || l.freeQty || 0),
          rate: Number(l.rate || 0),
          disc_pct: Number(l.disc_pct || l.discPct || 0),
          gst_pct: Number(l.gst_pct || l.gstPct || 0),
          amount: Number(l.amount || l.net || 0),
          unit_sold: l.unit_sold || l.unit || 'pack',
          returned_qty: Number(l.returned_qty || 0)
        }));
        return row;
      }

      function demoPayload() {
        return ((D.salesInvoices || [])).map(shape);
      }

      function payLabel(mode) { return PAY[mode] || (mode ? mode.charAt(0).toUpperCase() + mode.slice(1) : '—'); }

      function statusBadge(s) {
        const tone = { Paid: 'success', Due: 'danger', Partial: 'warning', Returned: 'danger', 'Part returned': 'warning' }[s] || 'secondary';
        return MF.badge(s || '—', tone);
      }

      function channelBadge(ch) {
        return MF.badge(ch === 'wholesale' ? 'Wholesale' : 'Retail', ch === 'wholesale' ? 'info' : 'success');
      }

      function filtered() {
        const q = state.q.toLowerCase();
        return state.rows.filter((r) => {
          if (state.channel !== 'all' && r.channel !== state.channel) return false;
          if (state.status === 'returned' && r.status !== 'Returned' && r.status !== 'Part returned') return false;
          if (state.status !== 'all' && state.status !== 'returned' && r.status.toLowerCase() !== state.status) return false;
          const date = String(r.sale_date || '').slice(0, 10);
          if (state.from && date && date < state.from) return false;
          if (state.to && date && date > state.to) return false;
          if (!q) return true;
          return [r.invoice_no, r.customer_name, r.customer_phone, r.doctor_name, r.payment_mode, r.channel, r.status].join(' ').toLowerCase().includes(q);
        });
      }

      function pageButtons(pages, current) {
        const want = new Set([1, pages, current - 1, current, current + 1]);
        const nums = [...want].filter((n) => n >= 1 && n <= pages).sort((a, b) => a - b);
        let html = '';
        let prev = 0;
        nums.forEach((n) => {
          if (n - prev > 1) html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
          html += `<li class="page-item ${n === current ? 'active' : ''}"><button class="page-link" type="button" data-pg="${n}">${n}</button></li>`;
          prev = n;
        });
        return html;
      }

      function renderFilters() {
        const chip = (group, value, label) => `<button type="button" class="si-chip ${state[group] === value ? 'active' : ''}" data-g="${group}" data-v="${value}">${label}</button>`;
        $('#siFilters').innerHTML = `
          <span class="si-label">Channel</span>
          ${chip('channel', 'all', 'All')}${chip('channel', 'retail', 'Retail')}${chip('channel', 'wholesale', 'Wholesale')}
          <span class="si-label ms-2">Status</span>
          ${chip('status', 'all', 'All')}${chip('status', 'paid', 'Paid')}${chip('status', 'due', 'Due')}${chip('status', 'partial', 'Partial')}${chip('status', 'returned', 'Returned')}`;
        $('#siFilters').querySelectorAll('[data-g]').forEach((b) => b.addEventListener('click', () => {
          state[b.dataset.g] = b.dataset.v;
          state.page = 1;
          render();
        }));
      }

      function render() {
        const list = filtered();
        const pages = Math.max(1, Math.ceil(list.length / state.per));
        state.page = Math.min(state.page, pages);
        const slice = list.slice((state.page - 1) * state.per, state.page * state.per);
        const sum = (key) => list.reduce((s, r) => s + (Number(r[key]) || 0), 0);
        $('#siCount').textContent = `${list.length} invoice${list.length === 1 ? '' : 's'} · posted bills, not the sales report`;
        $('#siStats').innerHTML = `
          <div class="col-6 col-md"><div class="si-stat accent"><span>Invoices</span><strong>${MF.num(list.length)}</strong></div></div>
          <div class="col-6 col-md"><div class="si-stat"><span>Billed</span><strong>${MF.fmt(sum('grand_total'))}</strong></div></div>
          <div class="col-6 col-md"><div class="si-stat"><span>Collected</span><strong>${MF.fmt(sum('amount_paid'))}</strong></div></div>
          <div class="col-6 col-md"><div class="si-stat"><span>Due</span><strong>${MF.fmt(sum('balance_due'))}</strong></div></div>
          <div class="col-6 col-md"><div class="si-stat"><span>Returned</span><strong>${MF.fmt(sum('refund_amount'))}</strong></div></div>`;
        $('#siBody').innerHTML = slice.map((r) => {
          const phone = formatPhone(r.customer_phone);
          const dueCls = Number(r.balance_due) > 0.009 ? 'si-due' : 'num';
          const split = r.payment_mode === 'split' ? `<span class="si-muted">Cash ${MF.fmt(r.cash_amount)} · UPI ${MF.fmt(r.upi_amount)}</span>` : '';
          const rowCls = Number(r.balance_due) > 0.009 ? 'si-row-due' : '';
          const payPct = Number(r.grand_total) > 0 ? Math.min(100, Math.round(Math.min(Number(r.grand_total), Number(r.amount_paid) || 0) / Number(r.grand_total) * 100)) : null;
          return `<tr${rowCls ? ` class="${rowCls}"` : ''}>
            <td>
              <div class="si-name num">${MF.esc(r.invoice_no || '—')}</div>
              <div class="mt-1">${channelBadge(r.channel)}</div>
            </td>
            <td class="num"><div>${r.sale_date ? MF.fmtDate(r.sale_date) : '—'}</div>${r.sale_time ? `<span class="si-time">${r.sale_time}</span>` : ''}</td>
            <td>
              <div class="fw-semibold si-ellipsis" title="${MF.esc(r.customer_name || 'Walk-in')}">${MF.esc(r.customer_name || 'Walk-in')}</div>
              ${phone ? `<span class="si-muted">${MF.esc(phone)}</span>` : ''}
            </td>
            <td class="si-doccell">${r.doctor_name ? `<span class="si-doc" title="${MF.esc(r.doctor_name)}">${MF.esc(r.doctor_name)}</span>` : `<button type="button" class="si-doc-add" data-a="doctor" data-id="${MF.esc(r.id)}" data-inv="${MF.esc(r.invoice_no)}"><i class="bi bi-person-plus"></i>Attach</button>`}</td>
            <td class="text-end si-h-md"><div class="num fw-semibold">${MF.num(r.item_count)}</div><div class="text-2 small-xs">${MF.num(r.qty)} qty</div></td>
            <td class="si-h-lg"><div class="fw-semibold">${MF.esc(payLabel(r.payment_mode))}</div>${split}</td>
            <td class="text-end num fw-semibold">${MF.fmt(r.grand_total)}</td>
            <td class="text-end num si-num">${MF.fmt(r.amount_paid)}${payPct != null ? `<div class="si-bar"><i style="width:${payPct}%"></i></div>` : ''}</td>
            <td class="text-end ${dueCls}">${MF.fmt(r.balance_due)}</td>
            <td>${statusBadge(r.status)}</td>
            <td class="text-end">
              <div class="dropdown">
                <button type="button" class="btn btn-icon btn-light-mf si-kebab" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end si-act-menu">
                  <li><button type="button" class="dropdown-item" data-a="view" data-id="${MF.esc(r.id)}"><i class="bi bi-eye"></i><span>View</span></button></li>
                  <li><button type="button" class="dropdown-item" data-a="print" data-id="${MF.esc(r.id)}"><i class="bi bi-printer"></i><span>Print</span></button></li>
                  <li><button type="button" class="dropdown-item" data-a="doctor" data-id="${MF.esc(r.id)}" data-inv="${MF.esc(r.invoice_no)}"><i class="bi bi-person-badge"></i><span>${r.doctor_name ? 'Change doctor' : 'Attach doctor'}</span></button></li>
                  <li><a class="dropdown-item" href="sales-return.php"><i class="bi bi-arrow-counterclockwise"></i><span>Sales return</span></a></li>
                </ul>
              </div>
            </td>
          </tr>`;
        }).join('') || '<tr><td colspan="11"><div class="empty-state"><i class="bi bi-file-earmark-text"></i>No invoices match.</div></td></tr>';
        const start = slice.length ? (state.page - 1) * state.per + 1 : 0;
        $('#siPageInfo').textContent = `Showing ${start}–${(state.page - 1) * state.per + slice.length} of ${list.length}`;
        $('#siPager').innerHTML = pageButtons(pages, state.page);
        $('#siPager').querySelectorAll('[data-pg]').forEach((b) => b.addEventListener('click', () => { state.page = +b.dataset.pg; render(); }));
        $('#siBody').querySelectorAll('[data-a]').forEach((b) => b.addEventListener('click', () => {
          const row = state.rows.find((r) => String(r.id) === String(b.dataset.id));
          if (!row) return;
          if (b.dataset.a === 'doctor') attachDoctor(row, b.dataset.inv || row.invoice_no);
          else if (b.dataset.a === 'print') printInvoice(row);
          else openView(row);
        }));
        renderFilters();
      }

      function gstRows(r) {
        const parts = [];
        if (Number(r.cgst)) parts.push(['CGST', r.cgst]);
        if (Number(r.sgst)) parts.push(['SGST', r.sgst]);
        if (Number(r.igst)) parts.push(['IGST', r.igst]);
        if (!parts.length) parts.push(['GST', r.gst_amount || 0]);
        return parts;
      }

      function batchCell(item) {
        if (!item.batch_no && !item.expiry_date) return '<span class="text-2">—</span>';
        const date = item.expiry_date ? `<div class="text-2 small-xs">${MF.fmtDate(String(item.expiry_date).slice(0, 10))}</div>` : '';
        const badge = item.batch_no ? `<span class="si-batch"><i class="bi bi-upc"></i>${MF.esc(item.batch_no)}</span>` : '';
        return date + badge;
      }

      function itemsTable(items) {
        if (!items.length) return '<div class="empty-state py-3"><i class="bi bi-list-ul"></i>Line items load from the sales invoice API.</div>';
        return `<div class="table-responsive"><table class="table table-mf mb-0">
          <thead><tr><th>Medicine</th><th>Batch</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Disc%</th><th class="text-end">GST%</th><th class="text-end">Amount</th></tr></thead>
          <tbody>${items.map((l) => `<tr>
            <td><div class="fw-semibold">${MF.esc(l.medicine_name || '—')}</div>${l.returned_qty ? `<span class="si-muted">Returned ${MF.num(l.returned_qty)}</span>` : ''}</td>
            <td>${batchCell(l)}</td>
            <td class="text-end num">${MF.num(l.qty)}${l.free_qty ? `<div class="text-2 small-xs">+${MF.num(l.free_qty)} free</div>` : ''}${l.unit_sold === 'loose' ? '<div class="text-2 small-xs">Loose</div>' : ''}</td>
            <td class="text-end num">${MF.fmt(l.rate, 2)}</td>
            <td class="text-end num">${Number(l.disc_pct) ? MF.fmt(l.disc_pct, 1) : '<span class="text-2">—</span>'}</td>
            <td class="text-end num">${Number(l.gst_pct) ? MF.fmt(l.gst_pct, 1) : '<span class="text-2">—</span>'}</td>
            <td class="text-end num fw-semibold">${MF.fmt(l.amount)}</td>
          </tr>`).join('')}</tbody>
        </table></div>`;
      }

      function totalsHtml(r) {
        const rows = [['Subtotal', r.subtotal], ['Discount', r.discount]].concat(gstRows(r), [['Round off', r.round_off]]);
        return `<div class="ms-auto mt-3" style="max-width:280px">
          ${rows.map(([label, value]) => `<div class="d-flex justify-content-between py-1"><span class="text-2">${label}</span><span class="num">${MF.fmt(value, 2)}</span></div>`).join('')}
          <div class="d-flex justify-content-between py-1 border-top mt-1"><strong>Total</strong><strong class="num">${MF.fmt(r.grand_total)}</strong></div>
          <div class="d-flex justify-content-between py-1"><span class="text-2">Paid</span><span class="num">${MF.fmt(r.amount_paid)}</span></div>
          <div class="d-flex justify-content-between py-1"><span class="text-2">Due</span><span class="num">${MF.fmt(r.balance_due)}</span></div>
          ${Number(r.refund_amount) ? `<div class="d-flex justify-content-between py-1"><span class="text-2">Refunded</span><span class="num">${MF.fmt(r.refund_amount)}</span></div>` : ''}
        </div>`;
      }

      function invoiceHtml(r) {
        const store = D.store || {};
        const phone = formatPhone(r.customer_phone);
        const split = r.payment_mode === 'split' ? ` — Cash ${MF.fmt(r.cash_amount, 2)} + UPI ${MF.fmt(r.upi_amount, 2)}` : '';
        const when = r.sale_time || '';
        const rows = (r.items || []).map((l, i) => `
          <tr>
            <td class="c">${i + 1}</td>
            <td>${MF.esc(l.medicine_name || '—')}${l.returned_qty ? `<div class="s">returned ${MF.num(l.returned_qty)}</div>` : ''}</td>
            <td>${MF.esc(l.batch_no || '—')}</td>
            <td>${l.expiry_date ? MF.fmtDate(String(l.expiry_date).slice(0, 10)) : '—'}</td>
            <td class="r">${MF.num(l.qty)}${l.free_qty ? ' +' + MF.num(l.free_qty) : ''}${l.unit_sold === 'loose' ? '<div class="s">loose</div>' : ''}</td>
            <td class="r">${MF.fmt(l.rate, 2)}</td>
            <td class="r">${Number(l.disc_pct) ? MF.fmt(l.disc_pct, 1) : '—'}</td>
            <td class="r">${Number(l.gst_pct) ? MF.fmt(l.gst_pct, 1) : '—'}</td>
            <td class="r b">${MF.fmt(l.amount)}</td>
          </tr>`).join('');
        const gstLines = gstRows(r).map(([k, v]) => `<div class="tl"><span>${k}</span><span>${MF.fmt(v, 2)}</span></div>`).join('');
        return `<style>
          @page { size: A5; margin: 10mm; }
          body { font-family: Arial, Helvetica, sans-serif; color:#111827; font-size:9.5px; line-height:1.45; }
          .band { border:1.5px solid #176B5B; border-radius:8px; padding:7px 9px; display:flex; gap:9px; align-items:center; }
          .lg { width:36px; height:36px; border-radius:9px; background:#176B5B; color:#fff; display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:800; }
          .nm { font-size:14.5px; font-weight:800; } .sb { font-size:7.6px; color:#4b5563; margin-top:2px; line-height:1.5; }
          .rt { margin-left:auto; text-align:right; font-size:7.8px; color:#4b5563; line-height:1.55; }
          .tag { display:inline-block; border:1.4px solid #176B5B; color:#176B5B; font-size:7.5px; font-weight:800; letter-spacing:.12em; padding:1px 7px; border-radius:4px; text-transform:uppercase; }
          .meta { display:flex; gap:6px; margin:8px 0; }
          .meta > div { flex:1; border:1px solid #d1d5db; border-radius:6px; padding:5px 7px; font-size:8px; }
          .lbl { font-size:6.8px; text-transform:uppercase; letter-spacing:.07em; color:#6b7280; font-weight:800; margin-bottom:2px; }
          .who { font-weight:700; font-size:9.5px; } .s { font-size:7.5px; color:#6b7280; }
          table { width:100%; border-collapse:collapse; margin-top:4px; }
          th, td { border:1px solid #c9d2dd; padding:3.5px 4px; text-align:left; vertical-align:top; }
          th { background:#eef2f7; font-size:7px; text-transform:uppercase; letter-spacing:.05em; color:#1f2937; border-bottom:1.4px solid #111827; }
          tbody tr:nth-child(even) { background:#f8fafc; }
          thead { display:table-header-group; } tr { page-break-inside:avoid; }
          .r { text-align:right; } .c { text-align:center; } .b { font-weight:700; }
          .tw { display:flex; justify-content:flex-end; margin-top:6px; page-break-inside:avoid; }
          .tot { width:62mm; border:1px solid #c9d2dd; border-radius:6px; padding:5px 8px; }
          .tl { display:flex; justify-content:space-between; padding:1px 0; font-size:8.5px; color:#374151; }
          .grand { display:flex; justify-content:space-between; border-top:1.4px solid #111827; margin-top:4px; padding-top:4px; font-size:11px; font-weight:800; }
          .pay { margin-top:6px; border:1px dashed #9ca3af; border-radius:6px; padding:5px 8px; font-size:8.5px; display:flex; justify-content:space-between; page-break-inside:avoid; }
          .decl { margin-top:7px; font-size:7px; color:#6b7280; line-height:1.55; }
          .sg { margin-top:20px; display:flex; justify-content:space-between; font-size:8px; page-break-inside:avoid; }
          .sg div { border-top:1.1px solid #111827; padding-top:3px; min-width:34mm; text-align:center; }
        </style>
        <div class="band">
          <div class="lg">Rx</div>
          <div><div class="nm">${MF.esc(store.name || 'Optms Rx')}</div>
            <div class="sb">${MF.esc(store.address || '')}${store.phone ? ' · Ph: ' + MF.esc(store.phone) : ''}</div>
            <div class="sb">${store.gstin ? 'GSTIN: <b>' + MF.esc(store.gstin) + '</b>' : ''}${store.dl_no ? (store.gstin ? ' · ' : '') + 'Drug Licence: <b>' + MF.esc(store.dl_no) + '</b>' : ''}</div>
          </div>
          <div class="rt"><span class="tag">${MF.esc(r.channel === 'wholesale' ? 'Wholesale' : 'Retail')} Invoice</span><br>
            <b style="font-size:10px;color:#111827">${MF.esc(r.invoice_no || '—')}</b><br>
            ${r.sale_date ? MF.fmtDate(r.sale_date) : ''}${when ? ' · ' + when : ''}</div>
        </div>
        <div class="meta">
          <div><div class="lbl">Billed to</div><div class="who">${MF.esc(r.customer_name || 'Walk-in')}</div>
            ${phone ? `<div class="s">${MF.esc(phone)}</div>` : ''}
            ${r.gstin ? `<div class="s">GSTIN ${MF.esc(r.gstin)}</div>` : ''}${r.dl_no ? `<div class="s">DL ${MF.esc(r.dl_no)}</div>` : ''}
          </div>
          <div><div class="lbl">Prescriber</div><div class="who">${r.doctor_name ? MF.esc(r.doctor_name) : '—'}</div>
            ${r.doctor_specialty ? `<div class="s">${MF.esc(r.doctor_specialty)}</div>` : ''}
            ${r.doctor_reg_no ? `<div class="s"><b>Reg. No. ${MF.esc(r.doctor_reg_no)}</b></div>` : ''}
          </div>
        </div>
        <table>
          <thead><tr><th style="width:16px">Sr</th><th>Item</th><th style="width:52px">Batch</th><th style="width:52px">Expiry</th>
            <th class="r" style="width:34px">Qty</th><th class="r" style="width:44px">Rate</th><th class="r" style="width:30px">Disc%</th><th class="r" style="width:30px">GST%</th><th class="r" style="width:56px">Amount</th></tr></thead>
          <tbody>${rows || '<tr><td colspan="9" class="c" style="padding:10px">Line items not loaded.</td></tr>'}</tbody>
        </table>
        <div class="tw"><div class="tot">
          <div class="tl"><span>Subtotal</span><span>${MF.fmt(r.subtotal, 2)}</span></div>
          ${Number(r.discount) ? `<div class="tl"><span>Discount</span><span>− ${MF.fmt(r.discount, 2)}</span></div>` : ''}
          ${gstLines}
          ${Number(r.round_off) ? `<div class="tl"><span>Round off</span><span>${MF.fmt(r.round_off, 2)}</span></div>` : ''}
          <div class="grand"><span>GRAND TOTAL</span><span>${MF.fmt(r.grand_total, 2)}</span></div>
        </div></div>
        <div class="pay">
          <span>Payment: <b>${MF.esc(payLabel(r.payment_mode))}</b>${split}</span>
          <span>Paid <b>${MF.fmt(r.amount_paid, 2)}</b> · Balance due <b>${MF.fmt(r.balance_due, 2)}</b></span>
        </div>
        ${Number(r.refund_amount) > 0.009 ? `<div class="decl" style="color:#B45309"><b>Note:</b> ${MF.fmt(r.refund_amount, 2)} returned against this invoice${r.return_count ? ` (${r.return_count} return${r.return_count > 1 ? 's' : ''})` : ''}; see the returns register.</div>` : ''}
        <div class="decl">E&amp;OE. Medicines once sold will not be taken back without valid reason. This is a system-generated invoice from Optms Rx — amounts, batches and parties are as recorded at billing. Prescription medicines are dispensed only against a valid prescription.</div>
        <div class="sg"><div>Customer signature</div><div>For ${MF.esc(store.name || 'Optms Rx')}</div></div>`;
      }

      function siSilentPrint(innerHtml) {
        const frame = document.createElement('iframe');
        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
        document.body.appendChild(frame);
        const doc = frame.contentWindow.document;
        doc.open();
        doc.write('<!doctype html><html><head><meta charset="utf-8"><title>Invoice</title></head><body>' + innerHtml + '</body></html>');
        doc.close();
        const done = () => setTimeout(() => { try { frame.remove(); } catch (e) { } }, 500);
        try {
          frame.contentWindow.addEventListener('afterprint', done);
          frame.contentWindow.focus();
          setTimeout(() => { try { frame.contentWindow.print(); } finally { done(); } }, 90);
        } catch (e) { done(); }
      }

      async function withItems(row) {
        if ((row.items && row.items.length) || !MF.Api.live || !row.id) return row;
        const res = await MF.Api.get('sales-invoices.php?id=' + encodeURIComponent(row.id));
        const data = res.data || {};
        const invoice = Object.assign({}, row, data.invoice || {}, { items: data.items || [] });
        const idx = state.rows.findIndex((r) => String(r.id) === String(row.id));
        if (idx >= 0) state.rows[idx] = invoice;
        return invoice;
      }

      async function openView(row) {
        let current = row;
        try { current = await withItems(row); } catch (e) { MF.toast(e.message, 'err', 'Could not load invoice'); }
        state.current = current;
        $('#siViewTitle').textContent = current.invoice_no || 'Invoice';
        const phone = formatPhone(current.customer_phone);
        const split = current.payment_mode === 'split' ? `<div class="si-muted">Cash ${MF.fmt(current.cash_amount)} · UPI ${MF.fmt(current.upi_amount)}</div>` : '';
        const settlePct = Number(current.grand_total) > 0 ? Math.min(100, Math.round(Number(current.amount_paid || 0) / Number(current.grand_total) * 100)) : null;
        const when = current.sale_time || '';
        $('#siViewBody').innerHTML = `
          <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
            <div>
              <div class="si-name">${MF.esc(current.invoice_no || '—')}</div>
              <div class="text-2 small">${current.sale_date ? MF.fmtDate(current.sale_date) : '—'}${when ? ' · ' + when : ''}</div>
            </div>
            <div class="d-flex gap-1 align-items-start">${channelBadge(current.channel)}${statusBadge(current.status)}</div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-4"><div class="p-2 px-3 rounded-3 border h-100" style="background:#FBFCFE">
              <div class="text-2 small-xs">Billed to</div>
              <div class="fw-semibold">${MF.esc(current.customer_name || 'Walk-in')}</div>
              ${phone ? `<div class="si-muted">${MF.esc(phone)}</div>` : ''}
              <div class="d-flex gap-1 flex-wrap mt-1">${current.gstin ? `<span class="si-batch"><i class="bi bi-building"></i>GSTIN ${MF.esc(current.gstin)}</span>` : ''}${current.dl_no ? `<span class="si-batch"><i class="bi bi-file-medical"></i>DL ${MF.esc(current.dl_no)}</span>` : ''}</div>
            </div></div>
            <div class="col-md-4"><div class="p-2 px-3 rounded-3 border h-100" style="background:#FBFCFE">
              <div class="text-2 small-xs">Prescriber</div>
              <div class="fw-semibold">${current.doctor_name ? MF.esc(current.doctor_name) : '—'}</div>
              ${current.doctor_specialty ? `<div class="si-muted">${MF.esc(current.doctor_specialty)}</div>` : ''}
              ${current.doctor_reg_no ? `<div class="mt-1"><span class="si-batch"><i class="bi bi-person-badge"></i>Reg. No. ${MF.esc(current.doctor_reg_no)}</span></div>` : ''}
            </div></div>
            <div class="col-md-4"><div class="p-2 px-3 rounded-3 border h-100" style="background:#FBFCFE">
              <div class="text-2 small-xs">Payment</div>
              <div class="fw-semibold">${MF.esc(payLabel(current.payment_mode))}</div>
              ${split}
              <div class="d-flex justify-content-between small mt-1"><span class="text-2">Paid</span><b class="num text-success">${MF.fmt(current.amount_paid)}</b></div>
              <div class="d-flex justify-content-between small"><span class="text-2">Due</span><b class="num ${Number(current.balance_due) > 0.009 ? 'text-danger' : ''}">${MF.fmt(current.balance_due)}</b></div>
              ${settlePct != null ? `<div class="si-bar" style="max-width:none"><i style="width:${settlePct}%"></i></div>` : ''}
            </div></div>
          </div>
          ${Number(current.refund_amount) > 0.009 ? `<div class="mb-2 px-3 py-2 rounded-3" style="background:#FEF3E2;border:1px dashed #F5DDB3;color:#B45309;font-size:.8rem;font-weight:650"><i class="bi bi-arrow-counterclockwise me-1"></i>${MF.fmt(current.refund_amount)} returned${current.return_count ? ` (${current.return_count} return${current.return_count > 1 ? 's' : ''})` : ''} on this bill — see the returns ledger.</div>` : ''}
          ${itemsTable(current.items || [])}
          ${totalsHtml(current)}`;
        bootstrap.Modal.getOrCreateInstance($('#siViewModal')).show();
      }

      async function printInvoice(row) {
        try {
          const current = await withItems(row);
          state.current = current;
          siSilentPrint(invoiceHtml(current));
        } catch (e) {
          MF.toast(e.message, 'err', 'Could not print invoice');
        }
      }

      function exportCsv() {
        const list = filtered();
        MF.exportCSV('sales-invoices.csv',
          ['Invoice', 'Date', 'Channel', 'Customer', 'Phone', 'Doctor', 'Items', 'Payment', 'Total', 'Paid', 'Due', 'Status'],
          list.map((r) => [r.invoice_no, r.sale_time ? r.sale_date + ' ' + r.sale_time : r.sale_date, r.channel, r.customer_name, formatPhone(r.customer_phone), r.doctor_name, r.item_count, payLabel(r.payment_mode), r.grand_total, r.amount_paid, r.balance_due, r.status]));
      }

      /* ------- Prescribing doctor — attach / change on posted invoices -------
         New bills carry the doctor from checkout (pos.js → sale-doctor.php);
         this dialog fixes the past ones by invoice number. */
      const siDocDlg = document.createElement('dialog');
      siDocDlg.id = 'siDocDialog';
      siDocDlg.setAttribute('aria-label', 'Change prescribing doctor');
      siDocDlg.innerHTML = `
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
          <span style="width:30px;height:30px;border-radius:50%;background:#176B5B;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem">Rx</span>
          <div style="min-width:0"><div style="font-weight:700;color:#1B2430">Prescribing doctor</div><div id="siDocSub" style="font-size:.74rem;color:#8B9BB0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:290px"></div></div>
          <button type="button" data-x aria-label="Close" style="margin-left:auto;border:0;background:transparent;color:#8B9BB0;cursor:pointer;font-size:1.05rem;padding:4px;line-height:1">✕</button>
        </div>
        <div id="siDocList"></div>`;
      document.body.appendChild(siDocDlg);
      let siDocInv = '';

      function docRowHtml(d, curId) {
        const on = String(d.id) === String(curId || '');
        return `<label style="display:flex;align-items:center;gap:9px;padding:8px 9px;border-radius:9px;cursor:pointer;${on ? 'background:#E6F1EE;' : ''}">
          <input type="radio" name="si-doc" value="${MF.esc(d.id)}" ${on ? 'checked' : ''} style="accent-color:#176B5B">
          <span style="min-width:0"><span style="display:block;font-weight:650;font-size:.86rem;color:#1B2430;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${MF.esc(d.name)}</span>${d.specialty || d.clinic ? `<span style="display:block;font-size:.7rem;color:#8B9BB0">${MF.esc([d.specialty, d.clinic].filter(Boolean).join(' · '))}</span>` : ''}</span>
          ${on ? '<span style="margin-left:auto;font-size:.64rem;font-weight:800;letter-spacing:.05em;color:#176B5B;white-space:nowrap">CURRENT</span>' : ''}
        </label>`;
      }

      async function attachDoctor(row, inv) {
        siDocInv = String(inv || row.invoice_no || '');
        $('#siDocSub').textContent = siDocInv + (row.customer_name ? ' · ' + row.customer_name : '');
        const list = $('#siDocList');
        list.innerHTML = '<div style="padding:14px;text-align:center;color:#8B9BB0;font-size:.85rem">Loading doctors…</div>';
        siDocDlg.showModal ? siDocDlg.showModal() : siDocDlg.setAttribute('open', '');
        try {
          let rows = Array.isArray(D.doctors) && D.doctors.length ? D.doctors : [];
          if (!rows.length && MF.Api.live) {
            const res = await MF.Api.get('doctors.php');
            rows = Array.isArray(res.data) ? res.data : ((res.data && res.data.doctors) || []);
            D.doctors = rows;
          }
          rows = rows.filter((d) => d && d.name && String(d.status || 'Active') !== 'Inactive').sort((a, b) => String(a.name).localeCompare(String(b.name)));
          list.innerHTML = rows.length ? rows.map((d) => docRowHtml(d, row.doctor_id)).join('') : '<div style="padding:14px;text-align:center;color:#8B9BB0;font-size:.85rem">No doctors yet — add them on the Doctors page.</div>';
        } catch (err) {
          list.innerHTML = '<div style="padding:14px;text-align:center;color:#B02A37;font-size:.85rem">Could not load doctors.</div>';
        }
      }

      siDocDlg.addEventListener('click', (e) => { if (e.target.closest('[data-x]')) siDocDlg.close(); });
      siDocDlg.addEventListener('change', async (e) => {
        const inp = e.target.closest('input[name="si-doc"]');
        if (!inp) return;
        const docId = inp.value;
        const row = (state.rows || []).find((r) => String(r.invoice_no) === siDocInv);
        try {
          if (MF.Api.live) {
            await MF.Api.post('sale-doctor.php', { invoiceNo: siDocInv, doctorId: docId });
            const res = await MF.Api.get('sales-invoices.php');
            const fresh = (res.data && (res.data.ledger || res.data.invoices)) || (Array.isArray(res.data) ? res.data : []);
            if (fresh.length) { state.rows = fresh.map((raw, i) => shape(raw, i)); }
          } else if (row) {
            const d = (D.doctors || []).find((x) => String(x.id) === String(docId));
            row.doctor_id = Number(docId);
            row.doctor_name = d ? d.name : '';
          }
          render();
          siDocDlg.close();
          MF.toast('Doctor saved on ' + siDocInv + '.', 'success', 'Invoice updated');
        } catch (err) {
          MF.toast(err.message || 'Could not save the doctor.', 'danger');
        }
      });

      document.addEventListener('DOMContentLoaded', async () => {
        await MF.boot();
        const params = new URLSearchParams(location.search);
        const deepInvoice = params.get('invoice') || params.get('no') || '';
        const deepId = params.get('id') || '';
        if (deepInvoice) {
          state.q = deepInvoice;
          $('#siSearch').value = deepInvoice;
        }
        if (MF.Api.live) {
          try {
            const res = await MF.Api.get('sales-invoices.php');
            state.rows = ((res.data || {}).ledger || []).map(shape);
          } catch (e) {
            MF.toast(e.message, 'err', 'Could not load sales invoices');
            state.rows = demoPayload();
          }
        } else {
          state.rows = demoPayload();
        }
        $('#siSearch').addEventListener('input', (e) => { state.q = e.target.value; state.page = 1; render(); });
        $('#siFrom').addEventListener('change', (e) => { state.from = e.target.value; state.page = 1; render(); });
        $('#siTo').addEventListener('change', (e) => { state.to = e.target.value; state.page = 1; render(); });
        $('#siExport').addEventListener('click', exportCsv);
        $('#siPrint').addEventListener('click', () => { if (state.current) printInvoice(state.current); });
        render();
        if (!state.opened && (deepInvoice || deepId)) {
          state.opened = true;
          const hit = state.rows.find((r) => (deepId && String(r.id) === String(deepId)) || (deepInvoice && String(r.invoice_no).toLowerCase() === deepInvoice.toLowerCase()));
          if (hit) openView(hit);
        }
      });
    })();
  </script>
</body>
</html>
