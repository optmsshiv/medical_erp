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
  <title>Purchase Invoices · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .pi-stat { background:#f8fafc; border:1px solid #e7edf4; border-radius:14px; padding:12px 14px; }
    .pi-stat span { display:block; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#6c757d; }
    .pi-stat strong { display:block; margin-top:3px; font-size:1.2rem; font-weight:750; letter-spacing:-.02em; color:#1b2430; }
    .pi-stat.accent { background:var(--mf-primary-soft); border-color:#d7ebe6; }
    .pi-kebab { width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; padding:0; transition:background .15s ease, color .15s ease, border-color .15s ease; }
    .btn.pi-kebab:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .pi-act-menu { min-width:196px; padding:6px; border:1px solid #e7edf4; border-radius:12px; box-shadow:0 12px 32px rgba(16,32,64,.14); z-index:1080; }
    .pi-act-menu .dropdown-item { display:flex; align-items:center; gap:10px; font-size:.84rem; font-weight:600; border-radius:8px; padding:.48rem .65rem; transition:background .15s ease, color .15s ease; }
    .pi-act-menu .dropdown-item i { width:1.05rem; color:var(--mf-primary); }
    .pi-act-menu .dropdown-item:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); }
    .pi-name { font-weight:700; color:#1b2430; }
    .pi-muted { display:block; margin-top:2px; color:#8b9bb0; font-size:.75rem; font-weight:600; line-height:1.3; }
    .pi-batch { display:inline-flex; align-items:center; gap:4px; margin-top:4px; background:var(--mf-primary-soft); color:var(--mf-primary-dark); border:1px solid #d7ebe6; border-radius:4px; padding:2px 6px; font-size:11px; font-weight:700; }
    .pi-batch i { font-size:12px; }
    .pi-due { color:#B02A37; font-weight:700; }
    .pi-wide { min-width:1180px; }
    .pi-ledger { border:1px solid #e3ebf4; border-radius:16px; background:#fff; overflow:hidden; box-shadow:0 1px 2px rgba(22,50,92,.04), 0 10px 28px rgba(22,50,92,.04); }
    .pi-ledger-search { display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid #e7eef6; background:#fff; flex-wrap:wrap; }
    .pi-ledger-search > i { color:#8b9bb0; font-size:15px; }
    .pi-ledger-search input[type="search"], .pi-ledger-search input[type="text"] { border:0; outline:0; box-shadow:none !important; background:transparent; width:100%; min-width:180px; flex:1; padding:4px 0; font-size:.92rem; color:#1b2430; }
    .pi-ledger-search input::placeholder { color:#9aa8b8; }
    .pi-date { width:auto !important; flex:0 0 auto !important; border:1px solid #e3ebf4 !important; border-radius:8px !important; padding:4px 8px !important; font-size:.8rem !important; color:#516278 !important; background:#fff !important; }
    .pi-filters { display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:10px 16px; border-bottom:1px solid #e7eef6; background:#fbfcfe; }
    .pi-chip { border:1px solid #e3ebf4; background:#fff; color:#516278; border-radius:999px; padding:4px 10px; font-size:.75rem; font-weight:700; transition:background .15s ease, color .15s ease, border-color .15s ease; }
    .pi-chip:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .pi-chip.active { background:var(--mf-primary); border-color:var(--mf-primary); color:#fff; }
    .pi-chip.active:hover { background:var(--mf-primary-dark); color:#fff; }
    .pi-ledger .table-mf { margin:0; }
    .pi-ledger .table-mf thead th { background:#f7f9fc; color:#7b8798; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; border-bottom:1px solid #e7eef6; padding:12px 14px; white-space:nowrap; }
    .pi-ledger .table-mf tbody td { border-bottom:1px solid #f0f4f8; padding:13px 14px; vertical-align:middle; }
    .pi-ledger .table-mf tbody tr:last-child td { border-bottom:0; }
    .pi-ledger .table-mf tbody tr:hover td { background:#f4faf8; }
    .pi-ledger-foot { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:12px 16px; border-top:1px solid #e3ebf4; background:#fff; }
    .pi-ledger-foot .pagination { gap:4px; }
    .pi-ledger-foot .page-link { border:1px solid #e3ebf4; color:#516278; border-radius:8px; min-width:32px; text-align:center; font-weight:650; padding:.3rem .55rem; transition:background .15s ease, color .15s ease, border-color .15s ease; }
    .pi-ledger-foot .page-item.active .page-link { background:var(--mf-primary); border-color:var(--mf-primary); color:#fff; }
    .pi-ledger-foot .page-link:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .pi-ledger-note { color:#8b9bb0; font-size:.78rem; line-height:1.45; padding:10px 16px 12px; margin:0; border-top:1px solid #eef3f8; background:#fbfcfe; }
  </style>
</head>
<body data-page="purchase-invoices">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">
        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-file-earmark-ruled me-2 text-success"></i>Purchase Invoices</h1>
            <p class="page-sub" id="piCount">Posted supplier bills. This is not the purchase report.</p>
          </div>
          <div class="ms-auto d-flex gap-2 flex-wrap justify-content-end">
            <a class="btn btn-mf-outline" href="reports.php?tab=purchase"><i class="bi bi-receipt-cutoff me-1"></i>Open purchase report</a>
            <button class="btn btn-light-mf" id="piExport" type="button"><i class="bi bi-download me-1"></i>Export CSV</button>
            <a class="btn btn-mf" href="purchase.php"><i class="bi bi-bag-plus me-1"></i>New purchase</a>
          </div>
        </div>

        <div class="row g-3 mb-3" id="piStats"></div>

        <div class="card-mf pi-ledger">
          <div class="pi-ledger-search">
            <i class="bi bi-search"></i>
            <input id="piSearch" type="search" name="pi-list-filter" placeholder="Search invoice, supplier, phone, GSTIN…" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true">
            <input class="pi-date" id="piFrom" type="date" aria-label="From date">
            <input class="pi-date" id="piTo" type="date" aria-label="To date">
          </div>
          <div class="pi-filters" id="piFilters"></div>
          <div class="table-scroll" style="max-height:none;overflow:visible">
            <table class="table table-mf pi-wide">
              <thead>
                <tr>
                  <th>Invoice</th>
                  <th>Date</th>
                  <th>Supplier</th>
                  <th class="text-end">Items</th>
                  <th>Payment</th>
                  <th class="text-end">Total</th>
                  <th class="text-end">Paid</th>
                  <th class="text-end">Due</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="piBody"></tbody>
            </table>
          </div>
          <div class="pi-ledger-foot">
            <span class="text-2 small" id="piPageInfo"></span>
            <ul class="pagination pagination-sm mb-0" id="piPager"></ul>
          </div>
          <p class="pi-ledger-note">Invoice totals, paid and due are stored on the purchase. Item count and return credit are computed live from purchase items and purchase returns. This page is not the purchase report.</p>
        </div>
      </main>
    </div>
  </div>

  <div class="modal fade" id="piViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="piViewTitle">Invoice</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
        </div>
        <div class="modal-body" id="piViewBody"></div>
        <div class="modal-footer">
          <a class="btn btn-light-mf me-auto" href="purchase-return.php"><i class="bi bi-box-arrow-in-left me-1"></i>Purchase return</a>
          <button class="btn btn-light-mf" data-bs-dismiss="modal" type="button">Close</button>
          <button class="btn btn-mf" id="piPrint" type="button"><i class="bi bi-printer me-1"></i>Print</button>
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
      const state = { rows: [], q: '', status: 'all', from: '', to: '', page: 1, per: 12, current: null, opened: false };

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
        const credit = Number(row.credit_amount) || 0;
        const due = row.balance_due == null || row.balance_due === '' ? null : Number(row.balance_due);
        const paid = row.amount_paid == null || row.amount_paid === '' ? null : Number(row.amount_paid);
        if (credit > 0.009 && grand > 0 && credit + 0.05 >= grand) return 'Returned';
        if (credit > 0.009) return 'Part returned';
        if (due != null && due <= 0.009) return 'Paid';
        if (paid != null && paid > 0.009 && due != null && due > 0.009) return 'Partial';
        if (due != null && due > 0.009) return 'Due';
        const known = String(row.status || '');
        if (/part/i.test(known) && /return/i.test(known)) return 'Part returned';
        if (/return/i.test(known)) return 'Returned';
        if (/partial/i.test(known)) return 'Partial';
        if (/due|credit/i.test(known)) return 'Due';
        if (/paid/i.test(known)) return 'Paid';
        return 'Due';
      }

      function shape(raw, idx) {
        const amount = Number(raw.grand_total ?? raw.amount ?? 0);
        const paid = raw.amount_paid != null ? Number(raw.amount_paid) : (raw.paid != null ? Number(raw.paid) : null);
        const due = raw.balance_due != null ? Number(raw.balance_due) : (raw.due != null ? Number(raw.due) : null);
        const lines = Array.isArray(raw.items) ? raw.items : (Array.isArray(raw.lines) ? raw.lines : []);
        const row = {
          id: raw.id || idx + 1,
          invoice_no: raw.invoice_no || raw.no || '',
          invoice_date: String(raw.invoice_date || raw.date || '').slice(0, 10),
          supplier_id: raw.supplier_id || raw.supplierId || '',
          supplier_name: raw.supplier_name || raw.supplier || 'Supplier',
          supplier_phone: raw.supplier_phone || raw.phone || raw.mobile || '',
          gstin: raw.gstin || '',
          dl_no: raw.dl_no || raw.dl || '',
          subtotal: Number(raw.subtotal ?? amount),
          discount: Number(raw.discount || 0),
          taxable: Number(raw.taxable || 0),
          cgst: Number(raw.cgst || 0),
          sgst: Number(raw.sgst || 0),
          igst: Number(raw.igst || 0),
          round_off: Number(raw.round_off || raw.roundOff || 0),
          grand_total: amount,
          payment_mode: raw.payment_mode || raw.payment || raw.mode || 'Credit',
          amount_paid: paid,
          balance_due: due,
          item_count: Number(raw.item_count || lines.length || 0),
          qty: Number(raw.qty || 0),
          free_qty: Number(raw.free_qty || 0),
          return_count: Number(raw.return_count || 0),
          credit_amount: Number(raw.credit_amount || raw.credit || 0),
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
          amount: Number(l.amount || 0),
          returned_qty: Number(l.returned_qty || 0)
        }));
        return row;
      }

      function demoPayload() {
        return (D.purchases || D.purchaseInvoices || []).map(shape);
      }

      function statusBadge(s) {
        const tone = { Paid: 'success', Due: 'danger', Partial: 'warning', Returned: 'danger', 'Part returned': 'warning' }[s] || 'secondary';
        return MF.badge(s || '—', tone);
      }

      function filtered() {
        const q = state.q.toLowerCase();
        return state.rows.filter((r) => {
          if (state.status === 'returned' && r.status !== 'Returned' && r.status !== 'Part returned') return false;
          if (state.status !== 'all' && state.status !== 'returned' && r.status.toLowerCase() !== state.status) return false;
          const date = String(r.invoice_date || '').slice(0, 10);
          if (state.from && date && date < state.from) return false;
          if (state.to && date && date > state.to) return false;
          if (!q) return true;
          return [r.invoice_no, r.supplier_name, r.supplier_phone, r.gstin, r.payment_mode, r.status].join(' ').toLowerCase().includes(q);
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
        const chip = (value, label) => `<button type="button" class="pi-chip ${state.status === value ? 'active' : ''}" data-v="${value}">${label}</button>`;
        $('#piFilters').innerHTML = `<span class="pi-muted" style="margin:0 4px 0 0">Status</span>${chip('all', 'All')}${chip('paid', 'Paid')}${chip('due', 'Due')}${chip('partial', 'Partial')}${chip('returned', 'Returned')}`;
        $('#piFilters').querySelectorAll('[data-v]').forEach((b) => b.addEventListener('click', () => {
          state.status = b.dataset.v;
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
        $('#piCount').textContent = `${list.length} invoice${list.length === 1 ? '' : 's'} · posted supplier bills, not the purchase report`;
        $('#piStats').innerHTML = `
          <div class="col-6 col-md"><div class="pi-stat accent"><span>Invoices</span><strong>${MF.num(list.length)}</strong></div></div>
          <div class="col-6 col-md"><div class="pi-stat"><span>Billed</span><strong>${MF.fmt(sum('grand_total'))}</strong></div></div>
          <div class="col-6 col-md"><div class="pi-stat"><span>Paid</span><strong>${MF.fmt(sum('amount_paid'))}</strong></div></div>
          <div class="col-6 col-md"><div class="pi-stat"><span>Due</span><strong>${MF.fmt(sum('balance_due'))}</strong></div></div>
          <div class="col-6 col-md"><div class="pi-stat"><span>Return credit</span><strong>${MF.fmt(sum('credit_amount'))}</strong></div></div>`;
        $('#piBody').innerHTML = slice.map((r) => {
          const phone = formatPhone(r.supplier_phone);
          const dueCls = Number(r.balance_due) > 0.009 ? 'pi-due' : 'num';
          return `<tr>
            <td><div class="pi-name num">${MF.esc(r.invoice_no || '—')}</div></td>
            <td class="num">${r.invoice_date ? MF.fmtDate(r.invoice_date) : '—'}</td>
            <td>
              <div class="fw-semibold">${MF.esc(r.supplier_name || 'Supplier')}</div>
              ${phone ? `<span class="pi-muted">${MF.esc(phone)}</span>` : ''}
            </td>
            <td class="text-end"><div class="num fw-semibold">${MF.num(r.item_count)}</div><div class="text-2 small-xs">${MF.num(r.qty)} qty</div></td>
            <td class="fw-semibold">${MF.esc(r.payment_mode || '—')}</td>
            <td class="text-end num fw-semibold">${MF.fmt(r.grand_total)}</td>
            <td class="text-end num">${MF.fmt(r.amount_paid)}</td>
            <td class="text-end ${dueCls}">${MF.fmt(r.balance_due)}</td>
            <td>${statusBadge(r.status)}</td>
            <td class="text-end">
              <div class="dropdown">
                <button type="button" class="btn btn-icon btn-light-mf pi-kebab" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end pi-act-menu">
                  <li><button type="button" class="dropdown-item" data-a="view" data-id="${MF.esc(r.id)}"><i class="bi bi-eye"></i><span>View</span></button></li>
                  <li><button type="button" class="dropdown-item" data-a="print" data-id="${MF.esc(r.id)}"><i class="bi bi-printer"></i><span>Print</span></button></li>
                  <li><a class="dropdown-item" href="purchase-return.php"><i class="bi bi-box-arrow-in-left"></i><span>Purchase return</span></a></li>
                </ul>
              </div>
            </td>
          </tr>`;
        }).join('') || '<tr><td colspan="10"><div class="empty-state"><i class="bi bi-file-earmark-ruled"></i>No purchase invoices match.</div></td></tr>';
        const start = slice.length ? (state.page - 1) * state.per + 1 : 0;
        $('#piPageInfo').textContent = `Showing ${start}–${(state.page - 1) * state.per + slice.length} of ${list.length}`;
        $('#piPager').innerHTML = pageButtons(pages, state.page);
        $('#piPager').querySelectorAll('[data-pg]').forEach((b) => b.addEventListener('click', () => { state.page = +b.dataset.pg; render(); }));
        $('#piBody').querySelectorAll('[data-a]').forEach((b) => b.addEventListener('click', () => {
          const row = state.rows.find((r) => String(r.id) === String(b.dataset.id));
          if (!row) return;
          if (b.dataset.a === 'print') printInvoice(row);
          else openView(row);
        }));
        renderFilters();
      }

      function gstRows(r) {
        const parts = [];
        if (Number(r.cgst)) parts.push(['CGST', r.cgst]);
        if (Number(r.sgst)) parts.push(['SGST', r.sgst]);
        if (Number(r.igst)) parts.push(['IGST', r.igst]);
        return parts;
      }

      function batchCell(item) {
        if (!item.batch_no && !item.expiry_date) return '<span class="text-2">—</span>';
        const date = item.expiry_date ? `<div class="text-2 small-xs">${MF.fmtDate(String(item.expiry_date).slice(0, 10))}</div>` : '';
        const badge = item.batch_no ? `<span class="pi-batch"><i class="bi bi-upc"></i>${MF.esc(item.batch_no)}</span>` : '';
        return date + badge;
      }

      function itemsTable(items) {
        if (!items.length) return '<div class="empty-state py-3"><i class="bi bi-list-ul"></i>Line items load from the purchase invoice API.</div>';
        return `<div class="table-responsive"><table class="table table-mf mb-0">
          <thead><tr><th>Medicine</th><th>Batch</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
          <tbody>${items.map((l) => `<tr>
            <td><div class="fw-semibold">${MF.esc(l.medicine_name || '—')}</div>${l.returned_qty ? `<span class="pi-muted">Returned ${MF.num(l.returned_qty)}</span>` : ''}</td>
            <td>${batchCell(l)}</td>
            <td class="text-end num">${MF.num(l.qty)}${l.free_qty ? `<div class="text-2 small-xs">+${MF.num(l.free_qty)} free</div>` : ''}</td>
            <td class="text-end num">${MF.fmt(l.rate, 2)}</td>
            <td class="text-end num">${MF.fmt(l.amount)}</td>
          </tr>`).join('')}</tbody>
        </table></div>`;
      }

      function totalsHtml(r) {
        const rows = [['Subtotal', r.subtotal], ['Discount', r.discount], ['Taxable', r.taxable]].concat(gstRows(r), [['Round off', r.round_off]]);
        return `<div class="ms-auto mt-3" style="max-width:280px">
          ${rows.map(([label, value]) => `<div class="d-flex justify-content-between py-1"><span class="text-2">${label}</span><span class="num">${MF.fmt(value, 2)}</span></div>`).join('')}
          <div class="d-flex justify-content-between py-1 border-top mt-1"><strong>Total</strong><strong class="num">${MF.fmt(r.grand_total)}</strong></div>
          <div class="d-flex justify-content-between py-1"><span class="text-2">Paid</span><span class="num">${MF.fmt(r.amount_paid)}</span></div>
          <div class="d-flex justify-content-between py-1"><span class="text-2">Due</span><span class="num">${MF.fmt(r.balance_due)}</span></div>
          ${Number(r.credit_amount) ? `<div class="d-flex justify-content-between py-1"><span class="text-2">Return credit</span><span class="num">${MF.fmt(r.credit_amount)}</span></div>` : ''}
        </div>`;
      }

      function receiptHtml(r) {
        const store = D.store || {};
        const phone = formatPhone(r.supplier_phone);
        return `
          <div class="text-center mb-3">
            <h6 class="fw-bold mt-2 mb-0">${MF.esc(store.name || 'Optms Rx')}</h6>
            <div class="text-2 small-xs">Purchase invoice</div>
          </div>
          <div class="d-flex justify-content-between small mb-2">
            <span>Invoice: <strong>${MF.esc(r.invoice_no)}</strong></span><span>${r.invoice_date ? MF.fmtDate(r.invoice_date) : ''}</span>
          </div>
          <div class="small mb-2">Supplier: <strong>${MF.esc(r.supplier_name || 'Supplier')}</strong>${phone ? ' · ' + MF.esc(phone) : ''} · Payment: <strong>${MF.esc(r.payment_mode || '')}</strong></div>
          ${itemsTable(r.items || [])}
          ${totalsHtml(r)}`;
      }

      async function withItems(row) {
        if ((row.items && row.items.length) || !MF.Api.live || !row.id) return row;
        const res = await MF.Api.get('purchase-invoices.php?id=' + encodeURIComponent(row.id));
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
        const phone = formatPhone(current.supplier_phone);
        $('#piViewTitle').textContent = current.invoice_no || 'Invoice';
        $('#piViewBody').innerHTML = `
          <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
            <div>
              <div class="pi-name">${MF.esc(current.invoice_no || '—')}</div>
              <div class="text-2 small">${current.invoice_date ? MF.fmtDate(current.invoice_date) : '—'} · ${MF.esc(current.payment_mode || '')}</div>
            </div>
            <div>${statusBadge(current.status)}</div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6"><div class="text-2 small-xs">Supplier</div><div class="fw-semibold">${MF.esc(current.supplier_name || 'Supplier')}</div>${phone ? `<span class="pi-muted">${MF.esc(phone)}</span>` : ''}</div>
            <div class="col-md-6"><div class="text-2 small-xs">Tax</div>${current.gstin ? `<span class="pi-muted">GSTIN ${MF.esc(current.gstin)}</span>` : '<span class="text-2">—</span>'}${current.dl_no ? `<span class="pi-muted">DL ${MF.esc(current.dl_no)}</span>` : ''}</div>
          </div>
          ${itemsTable(current.items || [])}
          ${totalsHtml(current)}`;
        bootstrap.Modal.getOrCreateInstance($('#piViewModal')).show();
      }

      async function printInvoice(row) {
        try {
          const current = await withItems(row);
          state.current = current;
          MF.printHtml(receiptHtml(current));
        } catch (e) {
          MF.toast(e.message, 'err', 'Could not print invoice');
        }
      }

      function exportCsv() {
        const list = filtered();
        MF.exportCSV('purchase-invoices.csv',
          ['Invoice', 'Date', 'Supplier', 'Phone', 'GSTIN', 'Items', 'Payment', 'Total', 'Paid', 'Due', 'Status'],
          list.map((r) => [r.invoice_no, r.invoice_date, r.supplier_name, formatPhone(r.supplier_phone), r.gstin, r.item_count, r.payment_mode, r.grand_total, r.amount_paid, r.balance_due, r.status]));
      }

      document.addEventListener('DOMContentLoaded', async () => {
        await MF.boot();
        const params = new URLSearchParams(location.search);
        const deepInvoice = params.get('invoice') || params.get('no') || '';
        const deepId = params.get('id') || '';
        if (deepInvoice) {
          state.q = deepInvoice;
          $('#piSearch').value = deepInvoice;
        }
        if (MF.Api.live) {
          try {
            const res = await MF.Api.get('purchase-invoices.php');
            state.rows = ((res.data || {}).ledger || []).map(shape);
          } catch (e) {
            MF.toast(e.message, 'err', 'Could not load purchase invoices');
            state.rows = demoPayload();
          }
        } else {
          state.rows = demoPayload();
        }
        $('#piSearch').addEventListener('input', (e) => { state.q = e.target.value; state.page = 1; render(); });
        $('#piFrom').addEventListener('change', (e) => { state.from = e.target.value; state.page = 1; render(); });
        $('#piTo').addEventListener('change', (e) => { state.to = e.target.value; state.page = 1; render(); });
        $('#piExport').addEventListener('click', exportCsv);
        $('#piPrint').addEventListener('click', () => { if (state.current) printInvoice(state.current); });
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
