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
  <title>Customer Dues · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .cd-stat { background:#f8fafc; border:1px solid #e7edf4; border-radius:14px; padding:12px 14px; }
    .cd-stat span { display:block; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#6c757d; }
    .cd-stat strong { display:block; margin-top:3px; font-size:1.2rem; font-weight:750; letter-spacing:-.02em; color:#1b2430; }
    .cd-stat.accent { background:var(--mf-primary-soft); border-color:#d7ebe6; }
    .cd-kebab { width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; padding:0; transition:background .15s ease, color .15s ease, border-color .15s ease, transform .15s ease; }
    .btn.cd-kebab:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .cd-act-menu { min-width:196px; padding:6px; border:1px solid #e7edf4; border-radius:12px; box-shadow:0 12px 32px rgba(16,32,64,.14); z-index:1080; }
    .cd-act-menu .dropdown-item { display:flex; align-items:center; gap:10px; font-size:.84rem; font-weight:600; border-radius:8px; padding:.48rem .65rem; transition:background .15s ease, color .15s ease; }
    .cd-act-menu .dropdown-item i { width:1.05rem; color:var(--mf-primary); }
    .cd-act-menu .dropdown-item:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); }
    .cd-name { font-weight:700; color:#1b2430; }
    .cd-muted { display:block; margin-top:2px; color:#8b9bb0; font-size:.75rem; font-weight:600; line-height:1.3; }
    .cd-gstin { font-size:.78rem; font-weight:700; letter-spacing:.03em; color:var(--mf-primary-dark); white-space:nowrap; }
    .cd-due { color:#B02A37; font-weight:700; }
    .cd-wide { min-width:1080px; }
    .cd-ledger { border:1px solid #e3ebf4; border-radius:16px; background:#fff; overflow:hidden; box-shadow:0 1px 2px rgba(22,50,92,.04), 0 10px 28px rgba(22,50,92,.04); }
    .cd-ledger-search { display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid #e7eef6; background:#fff; }
    .cd-ledger-search > i { color:#8b9bb0; font-size:15px; }
    .cd-ledger-search input { border:0; outline:0; box-shadow:none !important; background:transparent; width:100%; padding:4px 0; font-size:.92rem; color:#1b2430; }
    .cd-ledger-search input::placeholder { color:#9aa8b8; }
    .cd-filters { display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:10px 16px; border-bottom:1px solid #e7eef6; background:#fbfcfe; }
    .cd-chip { border:1px solid #e3ebf4; background:#fff; color:#516278; border-radius:999px; padding:4px 10px; font-size:.75rem; font-weight:700; transition:background .15s ease, color .15s ease, border-color .15s ease; }
    .cd-chip:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .cd-chip.active { background:var(--mf-primary); border-color:var(--mf-primary); color:#fff; }
    .cd-chip.active:hover { background:var(--mf-primary-dark); color:#fff; }
    .cd-ledger .table-mf { margin:0; }
    .cd-ledger .table-mf thead th { background:#f7f9fc; color:#7b8798; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; border-bottom:1px solid #e7eef6; padding:12px 14px; white-space:nowrap; }
    .cd-ledger .table-mf tbody td { border-bottom:1px solid #f0f4f8; padding:13px 14px; vertical-align:middle; }
    .cd-ledger .table-mf tbody tr:last-child td { border-bottom:0; }
    .cd-ledger .table-mf tbody tr:hover td { background:#f4faf8; }
    .cd-ledger-foot { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:12px 16px; border-top:1px solid #e3ebf4; background:#fff; }
    .cd-ledger-foot .pagination { gap:4px; }
    .cd-ledger-foot .page-link { border:1px solid #e3ebf4; color:#516278; border-radius:8px; min-width:32px; text-align:center; font-weight:650; padding:.3rem .55rem; transition:background .15s ease, color .15s ease, border-color .15s ease; }
    .cd-ledger-foot .page-item.active .page-link { background:var(--mf-primary); border-color:var(--mf-primary); color:#fff; }
    .cd-ledger-foot .page-link:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .cd-ledger-note { color:#8b9bb0; font-size:.78rem; line-height:1.45; padding:10px 16px 12px; margin:0; border-top:1px solid #eef3f8; background:#fbfcfe; }
  </style>
</head>
<body data-page="customer-dues">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">
        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-cash-stack me-2 text-success"></i>Customer Dues</h1>
            <p class="page-sub" id="cdCount">What customers still owe on sale bills. This is not the due report.</p>
          </div>
          <div class="ms-auto d-flex gap-2 flex-wrap justify-content-end">
            <a class="btn btn-mf-outline" href="reports.php?tab=dues"><i class="bi bi-clock-history me-1"></i>Open due report</a>
            <button class="btn btn-light-mf" id="cdExport" type="button"><i class="bi bi-download me-1"></i>Export CSV</button>
            <a class="btn btn-mf" href="retail-pos.php"><i class="bi bi-cart-plus me-1"></i>New retail sale</a>
          </div>
        </div>

        <div class="row g-3 mb-3" id="cdStats"></div>

        <div class="card-mf cd-ledger">
          <div class="cd-ledger-search">
            <i class="bi bi-search"></i>
            <input id="cdSearch" name="cd-list-filter" placeholder="Search customer, phone, GSTIN…" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true">
          </div>
          <div class="cd-filters" id="cdFilters"></div>
          <div class="table-scroll" style="max-height:none;overflow:visible">
            <table class="table table-mf cd-wide">
              <thead>
                <tr>
                  <th>Customer</th>
                  <th>GSTIN</th>
                  <th class="text-end">Open bills</th>
                  <th class="text-end">Billed</th>
                  <th class="text-end">Paid</th>
                  <th class="text-end">Due</th>
                  <th>Oldest open</th>
                  <th>Position</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="cdBody"></tbody>
            </table>
          </div>
          <div class="cd-ledger-foot">
            <span class="text-2 small" id="cdPageInfo"></span>
            <ul class="pagination pagination-sm mb-0" id="cdPager"></ul>
          </div>
          <p class="cd-ledger-note">Due is the stored sale balance. A payment already saved on the bill stays in Paid and is not subtracted again. Any customer payment above that reduces what is still owed. This page is not the due report.</p>
        </div>
      </main>
    </div>
  </div>

  <div class="modal fade" id="cdViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="cdViewTitle">Customer</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
        </div>
        <div class="modal-body" id="cdViewBody"></div>
        <div class="modal-footer">
          <a class="btn btn-light-mf me-auto" href="customers.php"><i class="bi bi-people me-1"></i>Customers</a>
          <button class="btn btn-light-mf" data-bs-dismiss="modal" type="button">Close</button>
          <a class="btn btn-mf" href="retail-pos.php"><i class="bi bi-cart-plus me-1"></i>New retail sale</a>
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
      const state = { customers: [], bills: [], payments: [], q: '', filter: 'due', page: 1, per: 12, opened: false };

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

      function ageDays(date) {
        if (!date) return null;
        const then = new Date(String(date).slice(0, 10) + 'T00:00:00');
        if (isNaN(then)) return null;
        const now = new Date((MF.today ? MF.today() : new Date().toISOString().slice(0, 10)) + 'T00:00:00');
        return Math.round((now - then) / 86400000);
      }

      function outstanding(row) {
        const balance = Number(row.balance_due) || 0;
        const paidOnBills = Number(row.amount_paid) || 0;
        const payments = Number(row.payments) || 0;
        return Math.max(0, Math.round((balance - Math.max(0, payments - paidOnBills)) * 100) / 100);
      }

      function positionOf(row) {
        const due = Number(row.outstanding) || 0;
        if (due <= 0.009) return 'Settled';
        const days = row.age_days == null ? ageDays(row.oldest_open) : Number(row.age_days);
        if (days != null && days > 30) return 'Overdue';
        if ((Number(row.amount_paid) || 0) > 0.009) return 'Partial';
        return 'Due';
      }

      function statusBadge(s) {
        const tone = { Settled: 'success', Due: 'danger', Partial: 'warning', Overdue: 'danger' }[s] || 'secondary';
        return MF.badge(s || '—', tone);
      }

      function typeBadge(type) {
        const wholesale = String(type || '').toLowerCase() === 'wholesale';
        return MF.badge(wholesale ? 'Wholesale' : 'Retail', wholesale ? 'info' : 'success');
      }

      function shapeCustomer(raw) {
        const oldest = String(raw.oldest_open || raw.oldest || '').slice(0, 10);
        const row = {
          customer_id: raw.customer_id || raw.id,
          customer_name: raw.customer_name || raw.name || '',
          customer_type: String(raw.customer_type || raw.type || 'retail').toLowerCase().includes('whole') ? 'wholesale' : 'retail',
          phone: raw.phone || raw.mobile || '',
          gstin: raw.gstin || '',
          dl_no: raw.dl_no || raw.dl || '',
          address: raw.address || '',
          bill_count: Number(raw.bill_count || 0),
          open_bills: Number(raw.open_bills || 0),
          billed: Number(raw.billed || raw.grand_total || 0),
          amount_paid: Number(raw.amount_paid || raw.paid || 0),
          balance_due: raw.balance_due != null ? Number(raw.balance_due) : Number(raw.due || 0),
          refund_amount: Number(raw.refund_amount || raw.refund || 0),
          payments: Number(raw.payments || 0),
          oldest_open: oldest
        };
        row.age_days = oldest ? ageDays(oldest) : null;
        row.outstanding = raw.outstanding != null ? Number(raw.outstanding) : outstanding(row);
        row.position = raw.position || positionOf(row);
        return row;
      }

      function demoPayload() {
        const invoices = D.salesInvoices || D.sales || [];
        const payments = (D.payments || []).filter((p) => String(p.party_type || p.partyType || '').toLowerCase() === 'customer' || p.customerId || p.customer_id);
        const byId = new Map();
        (D.customers || []).forEach((c) => {
          byId.set(String(c.id), {
            customer_id: c.id, customer_name: c.name, customer_type: c.type || 'retail', phone: c.phone || c.mobile || '', gstin: c.gstin || '',
            dl_no: c.dl_no || c.dl || '', address: c.address || '', bill_count: 0, open_bills: 0,
            billed: 0, amount_paid: 0, balance_due: Number(c.due || c.balance || 0), refund_amount: 0, payments: 0, oldest_open: ''
          });
        });
        invoices.forEach((inv) => {
          const id = inv.customer_id || inv.customerId || inv.customer;
          if (id == null || typeof id === 'string' && !byId.has(id) && !inv.customer_id) {
            const named = [...byId.values()].find((c) => c.customer_name === (inv.customer || inv.customer_name));
            if (!named) return;
            inv = Object.assign({}, inv, { customer_id: named.customer_id });
          }
          const key = String(inv.customer_id || inv.customerId || id);
          const row = byId.get(key) || { customer_id: key, customer_name: inv.customer_name || inv.customer || 'Customer', customer_type: 'retail', phone: '', gstin: '', bill_count: 0, open_bills: 0, billed: 0, amount_paid: 0, balance_due: 0, refund_amount: 0, payments: 0, oldest_open: '' };
          const due = inv.balance_due != null ? Number(inv.balance_due) : (String(inv.status || '').toLowerCase() === 'paid' ? 0 : Number(inv.due || inv.amount || 0));
          const date = String(inv.sale_date || inv.date || '').slice(0, 10);
          row.bill_count += 1;
          if (due > 0) row.open_bills += 1;
          row.billed += Number(inv.grand_total || inv.amount || 0);
          row.amount_paid += Number(inv.amount_paid != null ? inv.amount_paid : (due > 0 ? 0 : Number(inv.amount || 0)));
          row.balance_due += due;
          if (due > 0 && (!row.oldest_open || (date && date < row.oldest_open))) row.oldest_open = date;
          byId.set(key, row);
        });
        payments.forEach((p) => {
          const id = p.party_id || p.customer_id || p.customerId;
          const row = id == null ? null : byId.get(String(id));
          if (row) row.payments += Number(p.amount || 0);
        });
        const customers = [...byId.values()].filter((c) => c.bill_count || c.balance_due || c.payments).map(shapeCustomer);
        const bills = invoices.map((inv, i) => ({
          id: inv.id || i + 1,
          customer_id: inv.customer_id || inv.customerId,
          invoice_no: inv.invoice_no || inv.no || '',
          sale_date: String(inv.sale_date || inv.date || '').slice(0, 10),
          age_days: ageDays(inv.sale_date || inv.date),
          channel: String(inv.channel || inv.type || 'retail').toLowerCase().includes('whole') ? 'wholesale' : 'retail',
          payment_mode: inv.payment_mode || inv.payment || inv.mode || '',
          grand_total: Number(inv.grand_total || inv.amount || 0),
          amount_paid: Number(inv.amount_paid || inv.paid || 0),
          balance_due: inv.balance_due != null ? Number(inv.balance_due) : Number(inv.due || 0),
          refund_amount: Number(inv.refund_amount || 0)
        }));
        return { customers, bills, payments: payments.map((p) => ({ customer_id: p.party_id || p.customer_id || p.customerId, amount: Number(p.amount || 0), mode: p.mode || '', payment_date: String(p.payment_date || p.date || '').slice(0, 10), note: p.note || '' })) };
      }

      function filtered() {
        const q = state.q.toLowerCase();
        return state.customers.filter((r) => {
          if (state.filter === 'due' && !(r.outstanding > 0.009)) return false;
          if (state.filter === 'overdue' && r.position !== 'Overdue') return false;
          if (state.filter === 'settled' && r.position !== 'Settled') return false;
          if (state.filter === 'wholesale' && r.customer_type !== 'wholesale') return false;
          if (state.filter === 'retail' && r.customer_type !== 'retail') return false;
          if (!q) return true;
          return [r.customer_name, r.phone, r.gstin, r.customer_type, r.position].join(' ').toLowerCase().includes(q);
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
        const chip = (value, label) => `<button type="button" class="cd-chip ${state.filter === value ? 'active' : ''}" data-v="${value}">${label}</button>`;
        $('#cdFilters').innerHTML = `<span class="cd-muted" style="margin:0 4px 0 0">Show</span>${chip('due', 'With due')}${chip('overdue', 'Overdue')}${chip('settled', 'Settled')}${chip('retail', 'Retail')}${chip('wholesale', 'Wholesale')}${chip('all', 'All')}`;
        $('#cdFilters').querySelectorAll('[data-v]').forEach((b) => b.addEventListener('click', () => {
          state.filter = b.dataset.v;
          state.page = 1;
          render();
        }));
      }

      function oldestCell(r) {
        if (!r.oldest_open) return '<span class="text-2">—</span>';
        const days = r.age_days == null ? ageDays(r.oldest_open) : r.age_days;
        const cls = days != null && days > 30 ? 'text-danger' : '';
        return `<div class="num ${cls}">${MF.fmtDate(r.oldest_open)}</div>${days != null ? `<span class="cd-muted">${days} days</span>` : ''}`;
      }

      function render() {
        const list = filtered();
        const pages = Math.max(1, Math.ceil(list.length / state.per));
        state.page = Math.min(state.page, pages);
        const slice = list.slice((state.page - 1) * state.per, state.page * state.per);
        const sum = (key) => list.reduce((s, r) => s + (Number(r[key]) || 0), 0);
        const overdue = list.filter((r) => r.position === 'Overdue').length;
        $('#cdCount').textContent = `${list.length} customer${list.length === 1 ? '' : 's'} · open sale balances, not the due report`;
        $('#cdStats').innerHTML = `
          <div class="col-6 col-md"><div class="cd-stat accent"><span>Customers</span><strong>${MF.num(list.length)}</strong></div></div>
          <div class="col-6 col-md"><div class="cd-stat"><span>Open bills</span><strong>${MF.num(sum('open_bills'))}</strong></div></div>
          <div class="col-6 col-md"><div class="cd-stat"><span>Due</span><strong>${MF.fmt(sum('outstanding'))}</strong></div></div>
          <div class="col-6 col-md"><div class="cd-stat"><span>Overdue</span><strong>${MF.num(overdue)}</strong></div></div>`;
        $('#cdBody').innerHTML = slice.map((r) => {
          const phone = formatPhone(r.phone);
          const dueCls = Number(r.outstanding) > 0.009 ? 'cd-due' : 'num';
          return `<tr>
            <td>
              <div class="cd-name">${MF.esc(r.customer_name || '—')}</div>
              ${phone ? `<span class="cd-muted">${MF.esc(phone)}</span>` : ''}
              <div class="mt-1">${typeBadge(r.customer_type)}</div>
            </td>
            <td>${r.gstin ? `<span class="cd-gstin">${MF.esc(r.gstin)}</span>` : '<span class="text-2">—</span>'}</td>
            <td class="text-end num">${MF.num(r.open_bills)}</td>
            <td class="text-end num">${MF.fmt(r.billed)}</td>
            <td class="text-end num">${MF.fmt(r.amount_paid)}</td>
            <td class="text-end ${dueCls}">${MF.fmt(r.outstanding)}</td>
            <td>${oldestCell(r)}</td>
            <td>${statusBadge(r.position)}</td>
            <td class="text-end">
              <div class="dropdown">
                <button type="button" class="btn btn-icon btn-light-mf cd-kebab" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end cd-act-menu">
                  <li><button type="button" class="dropdown-item" data-a="view" data-id="${MF.esc(r.customer_id)}"><i class="bi bi-eye"></i><span>View bills</span></button></li>
                  <li><a class="dropdown-item" href="customers.php"><i class="bi bi-people"></i><span>Customers</span></a></li>
                  <li><a class="dropdown-item" href="retail-pos.php"><i class="bi bi-cart-plus"></i><span>New retail sale</span></a></li>
                </ul>
              </div>
            </td>
          </tr>`;
        }).join('') || '<tr><td colspan="9"><div class="empty-state"><i class="bi bi-cash-stack"></i>No customer dues match.</div></td></tr>';
        const start = slice.length ? (state.page - 1) * state.per + 1 : 0;
        $('#cdPageInfo').textContent = `Showing ${start}–${(state.page - 1) * state.per + slice.length} of ${list.length}`;
        $('#cdPager').innerHTML = pageButtons(pages, state.page);
        $('#cdPager').querySelectorAll('[data-pg]').forEach((b) => b.addEventListener('click', () => { state.page = +b.dataset.pg; render(); }));
        $('#cdBody').querySelectorAll('[data-a="view"]').forEach((b) => b.addEventListener('click', () => {
          const row = state.customers.find((r) => String(r.customer_id) === String(b.dataset.id));
          if (row) openView(row);
        }));
        renderFilters();
      }

      function payLabel(mode) {
        const key = String(mode || '').toLowerCase();
        return { cash: 'Cash', upi: 'UPI', card: 'Card', bank: 'Bank', credit: 'Credit', split: 'Split' }[key] || (mode || '—');
      }

      function openView(row) {
        const bills = state.bills.filter((b) => String(b.customer_id) === String(row.customer_id));
        const pays = state.payments.filter((p) => String(p.customer_id) === String(row.customer_id));
        const open = bills.filter((b) => Number(b.balance_due) > 0.009);
        const phone = formatPhone(row.phone);
        $('#cdViewTitle').textContent = row.customer_name || 'Customer';
        const billTable = open.length ? `<div class="table-responsive"><table class="table table-mf mb-0">
          <thead><tr><th>Invoice</th><th>Date</th><th>Mode</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Due</th></tr></thead>
          <tbody>${open.map((b) => `<tr>
            <td><div class="fw-semibold num">${MF.esc(b.invoice_no || '—')}</div><div class="mt-1">${typeBadge(b.channel)}</div></td>
            <td class="num">${b.sale_date ? MF.fmtDate(b.sale_date) : '—'}${b.age_days != null ? `<span class="cd-muted">${b.age_days} days</span>` : ''}</td>
            <td>${MF.esc(payLabel(b.payment_mode))}</td>
            <td class="text-end num">${MF.fmt(b.grand_total)}</td>
            <td class="text-end num">${MF.fmt(b.amount_paid)}</td>
            <td class="text-end cd-due">${MF.fmt(b.balance_due)}</td>
          </tr>`).join('')}</tbody></table></div>` : '<div class="empty-state py-3"><i class="bi bi-check2-circle"></i>No open sale bills.</div>';
        const payTable = pays.length ? `<div class="table-responsive mt-3"><table class="table table-mf mb-0">
          <thead><tr><th>Payment date</th><th>Mode</th><th>Note</th><th class="text-end">Amount</th></tr></thead>
          <tbody>${pays.map((p) => `<tr>
            <td class="num">${p.payment_date ? MF.fmtDate(p.payment_date) : '—'}</td>
            <td>${MF.esc(p.mode || '—')}</td>
            <td>${p.note ? MF.esc(p.note) : '<span class="text-2">—</span>'}</td>
            <td class="text-end num">${MF.fmt(p.amount)}</td>
          </tr>`).join('')}</tbody></table></div>` : '';
        $('#cdViewBody').innerHTML = `
          <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
            <div>
              <div class="cd-name">${MF.esc(row.customer_name || '—')}</div>
              ${phone ? `<span class="cd-muted">${MF.esc(phone)}</span>` : ''}
              ${row.gstin ? `<span class="cd-muted">GSTIN ${MF.esc(row.gstin)}</span>` : ''}
            </div>
            <div class="d-flex gap-1">${typeBadge(row.customer_type)}${statusBadge(row.position)}</div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-4"><div class="text-2 small-xs">Billed</div><div class="fw-semibold num">${MF.fmt(row.billed)}</div></div>
            <div class="col-4"><div class="text-2 small-xs">Paid on bills</div><div class="fw-semibold num">${MF.fmt(row.amount_paid)}</div></div>
            <div class="col-4"><div class="text-2 small-xs">Still due</div><div class="fw-semibold num">${MF.fmt(row.outstanding)}</div></div>
          </div>
          <div class="cd-name mb-2">Open bills</div>
          ${billTable}
          ${pays.length ? '<div class="cd-name mt-3 mb-2">Payments recorded</div>' + payTable : ''}
          ${Number(row.refund_amount) ? `<p class="cd-muted">Sales-return refund on these bills: ${MF.fmt(row.refund_amount)}. It is shown here and is not subtracted again from the stored balance.</p>` : ''}`;
        bootstrap.Modal.getOrCreateInstance($('#cdViewModal')).show();
      }

      function exportCsv() {
        const list = filtered();
        MF.exportCSV('customer-dues.csv',
          ['Customer', 'Type', 'Phone', 'GSTIN', 'Open bills', 'Billed', 'Paid', 'Due', 'Oldest open', 'Position'],
          list.map((r) => [r.customer_name, r.customer_type, formatPhone(r.phone), r.gstin, r.open_bills, r.billed, r.amount_paid, r.outstanding, r.oldest_open, r.position]));
      }

      document.addEventListener('DOMContentLoaded', async () => {
        await MF.boot();
        const params = new URLSearchParams(location.search);
        const deep = params.get('q') || params.get('customer') || '';
        if (deep && !/^\d+$/.test(deep)) {
          state.q = deep;
          $('#cdSearch').value = deep;
        }
        if (MF.Api.live) {
          try {
            const res = await MF.Api.get('customer-dues.php');
            const data = res.data || {};
            state.customers = (data.customers || []).map(shapeCustomer);
            state.bills = data.bills || [];
            state.payments = data.payments || [];
          } catch (e) {
            MF.toast(e.message, 'err', 'Could not load customer dues');
            const demo = demoPayload();
            state.customers = demo.customers;
            state.bills = demo.bills;
            state.payments = demo.payments;
          }
        } else {
          const demo = demoPayload();
          state.customers = demo.customers;
          state.bills = demo.bills;
          state.payments = demo.payments;
        }
        $('#cdSearch').addEventListener('input', (e) => { state.q = e.target.value; state.page = 1; render(); });
        $('#cdExport').addEventListener('click', exportCsv);
        render();
        if (!state.opened && /^\d+$/.test(deep)) {
          state.opened = true;
          const hit = state.customers.find((r) => String(r.customer_id) === deep);
          if (hit) openView(hit);
        }
      });
    })();
  </script>
</body>
</html>
