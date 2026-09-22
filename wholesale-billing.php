<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php';

$client = Tenant::current();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Wholesale Billing · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="wholesale-billing">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-receipt me-2 text-success"></i>Wholesale Billing</h1>
            <p class="page-sub">B2B / dealer invoicing · PTR-based pricing · CGST / SGST / IGST</p>
          </div>
          <div class="ms-auto">
            <span class="badge badge-soft-info"><i class="bi bi-upc-scan me-1"></i>Invoice series: WS/2026-27</span>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-xxl-8">
            <!-- Dealer details -->
            <div class="card-mf mb-3">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-buildings"></i>Dealer / Customer</h2></div>
              <div class="p-3">
                <div class="row g-2">
                  <div class="col-md-4">
                    <label class="form-label">Customer / Dealer <span class="req">*</span></label>
                    <div class="d-flex gap-2">
                      <select class="form-select" id="wsCustomer"></select>
                      <button class="btn btn-light-mf" type="button" id="wsAddDealer" title="Add new dealer"><i class="bi bi-plus-lg"></i></button>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">GSTIN</label>
                    <input class="form-control" id="wsGstin" readonly placeholder="Auto-filled">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Drug License No.</label>
                    <input class="form-control" id="wsDl" readonly placeholder="Auto-filled">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Billing Address</label>
                    <textarea class="form-control" id="wsBillAddr" rows="2" readonly placeholder="Auto-filled"></textarea>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Shipping Address</label>
                    <textarea class="form-control" id="wsShipAddr" rows="2"></textarea>
                    <div class="form-check mt-1">
                      <input class="form-check-input" type="checkbox" id="wsSameAddr" checked>
                      <label class="form-check-label small text-2" for="wsSameAddr">Same as billing address</label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Items -->
            <div class="card-mf">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-box-seam"></i>Items</h2>
                <div class="card-tools">
                  <span class="badge badge-soft-success"><i class="bi bi-gift me-1"></i>Scheme: 10+1 free auto-applied on eligible lines</span>
                </div>
              </div>
              <div class="table-scroll" style="max-height:none">
                <table class="table table-mf">
                  <thead>
                    <tr>
                      <th style="min-width:210px">Medicine</th><th>Batch</th><th>Expiry</th>
                      <th class="text-center">Qty</th><th class="text-center">Free</th>
                      <th class="text-end">PTR</th><th class="text-end">Rate</th>
                      <th class="text-center">Disc%</th><th class="text-center">GST%</th>
                      <th class="text-end">Amount</th><th></th>
                    </tr>
                  </thead>
                  <tbody id="wsItemsBody"></tbody>
                </table>
              </div>
              <div class="p-3 border-top d-flex flex-wrap gap-2 align-items-center">
                <input class="form-control" id="wsQuickSearch" list="wsMedList" placeholder="Type medicine name to add a line…" style="max-width:340px">
                <datalist id="wsMedList"></datalist>
                <button class="btn btn-mf-soft" id="wsAddFromSearch"><i class="bi bi-plus-lg me-1"></i>Add Line</button>
                <button class="btn btn-light-mf ms-auto" id="wsAddRow"><i class="bi bi-plus-circle me-1"></i>Add Blank Row</button>
              </div>
            </div>
          </div>

          <!-- RIGHT: totals -->
          <div class="col-xxl-4">
            <div class="card-mf mb-3">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-calculator"></i>Bill Summary</h2></div>
              <div class="p-3">
                <div class="row g-2 mb-2">
                  <div class="col-6">
                    <label class="form-label">Scheme Discount (%)</label>
                    <input type="number" class="form-control" id="wsSchemeDisc" value="0" min="0" max="100">
                  </div>
                  <div class="col-6">
                    <label class="form-label">Overall Discount (%)</label>
                    <input type="number" class="form-control" id="wsOverallDisc" value="0" min="0" max="100">
                  </div>
                </div>
                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" id="wsInterstate">
                  <label class="form-check-label small" for="wsInterstate">Interstate supply (charge IGST instead of CGST+SGST)</label>
                </div>
                <div class="divider-dashed mb-2"></div>
                <div id="wsSummary"></div>
              </div>
            </div>

            <div class="card-mf">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-credit-card"></i>Payment</h2></div>
              <div class="p-3">
                <div class="row g-2 row-cols-4 mb-3">
                  <div class="col pay-opt"><input type="radio" name="wsPay" id="wsPayCash" value="Cash" checked><label for="wsPayCash"><i class="bi bi-cash"></i>Cash</label></div>
                  <div class="col pay-opt"><input type="radio" name="wsPay" id="wsPayBank" value="Bank"><label for="wsPayBank"><i class="bi bi-bank"></i>Bank</label></div>
                  <div class="col pay-opt"><input type="radio" name="wsPay" id="wsPayUpi" value="UPI"><label for="wsPayUpi"><i class="bi bi-qr-code-scan"></i>UPI</label></div>
                  <div class="col pay-opt"><input type="radio" name="wsPay" id="wsPayCredit" value="Credit"><label for="wsPayCredit"><i class="bi bi-journal-text"></i>Credit</label></div>
                </div>
                <div class="d-grid gap-2">
                  <button class="btn btn-mf" id="wsSave"><i class="bi bi-check2-circle me-1"></i>Save &amp; Generate Invoice</button>
                  <button class="btn btn-light-mf" id="wsReset"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset Bill</button>
                </div>
              </div>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Add Dealer modal -->
  <div class="modal fade" id="wsAddDealerModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Dealer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Dealer / Business Name <span class="req">*</span></label>
            <input class="form-control" id="wdName" placeholder="e.g. City Medical Distributors">
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">GSTIN</label>
              <input class="form-control" id="wdGstin" placeholder="22AAAAA0000A1Z5">
            </div>
            <div class="col-6">
              <label class="form-label">Drug License No.</label>
              <input class="form-control" id="wdDl">
            </div>
          </div>
          <div class="mb-2 mt-2">
            <label class="form-label">Phone</label>
            <input class="form-control" id="wdPhone">
          </div>
          <div>
            <label class="form-label">Address</label>
            <textarea class="form-control" id="wdAddr" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="wdSave">Save Dealer</button>
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
      let rows = [], seq = 0;

      const dealers = D.customers.filter((c) => c.type === 'wholesale');
      const $ = (s) => document.querySelector(s);

      function fillDatalist() {
        $('#wsMedList').innerHTML = D.medicines.map((m) => `<option value="${m.name} — ${m.manufacturer}">`).join('');
      }

      function addRow(medId) {
        const m = medId ? MF.med(medId) : null;
        const b = medId ? MF.pickBatch(medId) : null;
        rows.push({
          id: ++seq, medId: medId || '',
          batch: b ? b.batchNo : '', expiry: b ? b.expiry : '',
          qty: 1, freeQty: 0, ptr: m ? m.purchaseRate : 0,
          rate: m ? m.wholesaleRate : 0, discPct: 0, gst: m ? m.gst : 12
        });
        render();
      }

      function lineAmount(r) { return r.qty * r.rate * (1 - r.discPct / 100); }

      function totals() {
        const subtotal = rows.reduce((s, r) => s + r.qty * r.rate, 0);
        const lineDisc = rows.reduce((s, r) => s + r.qty * r.rate * (r.discPct / 100), 0);
        const afterLine = subtotal - lineDisc;
        const schemeAmt = afterLine * ((parseFloat($('#wsSchemeDisc').value) || 0) / 100);
        const overallAmt = (afterLine - schemeAmt) * ((parseFloat($('#wsOverallDisc').value) || 0) / 100);
        const discount = lineDisc + schemeAmt + overallAmt;
        const taxable = afterLine - schemeAmt - overallAmt;
        let tax = 0;
        rows.forEach((r) => { tax += lineAmount(r) * r.gst / 100; });
        const taxAfterDisc = tax * (taxable / (afterLine || 1));
        const igst = $('#wsInterstate').checked;
        const grand = Math.round(taxable + taxAfterDisc);
        return { subtotal, discount, taxable, cgst: igst ? 0 : taxAfterDisc / 2, sgst: igst ? 0 : taxAfterDisc / 2, igst: igst ? taxAfterDisc : 0, roundOff: grand - (taxable + taxAfterDisc), grand };
      }

      function render() {
        $('#wsItemsBody').innerHTML = rows.length ? rows.map((r) => `
          <tr data-row="${r.id}">
            <td>
              <select class="form-select form-select-sm ws-med">
                <option value="">— Select medicine —</option>
                ${D.medicines.map((m) => `<option value="${m.id}" ${r.medId === m.id ? 'selected' : ''}>${MF.esc(m.name)}</option>`).join('')}
              </select>
            </td>
            <td><input class="form-control form-control-sm ws-batch num" style="width:92px" value="${r.batch}" placeholder="BATCH"></td>
            <td><input type="month" class="form-control form-control-sm ws-exp" value="${r.expiry.slice(0, 7)}"></td>
            <td class="text-center"><input type="number" class="form-control form-control-sm text-center ws-qty" style="width:64px;display:inline-block" value="${r.qty}" min="1"></td>
            <td class="text-center"><input type="number" class="form-control form-control-sm text-center ws-free" style="width:56px;display:inline-block" value="${r.freeQty}" min="0"></td>
            <td class="text-end num text-2 ws-ptr">${MF.fmt(r.ptr, 2)}</td>
            <td class="text-end"><input type="number" step="0.01" class="form-control form-control-sm text-end ws-rate" style="width:86px;display:inline-block" value="${r.rate}"></td>
            <td class="text-center"><input type="number" class="form-control form-control-sm text-center ws-disc" style="width:60px;display:inline-block" value="${r.discPct}" min="0" max="100"></td>
            <td class="text-center num text-2">${r.gst}%</td>
            <td class="text-end num fw-semibold ws-amt">${MF.fmt(lineAmount(r), 2)}</td>
            <td><button class="btn btn-icon btn-light-mf text-danger ws-del"><i class="bi bi-trash3"></i></button></td>
          </tr>`).join('')
          : `<tr><td colspan="11"><div class="empty-state py-4"><i class="bi bi-box-seam"></i>No lines yet — search a medicine above or add a blank row.</div></td></tr>`;

        $('#wsItemsBody').querySelectorAll('tr[data-row]').forEach((tr) => {
          const r = rows.find((x) => x.id === +tr.dataset.row);
          tr.querySelector('.ws-med').addEventListener('change', (e) => {
            r.medId = Number(e.target.value);
            const m = MF.med(r.medId), b = MF.pickBatch(r.medId);
            if (m) { r.ptr = m.purchaseRate; r.rate = m.wholesaleRate; r.gst = m.gst; }
            if (b) { r.batch = b.batchNo; r.expiry = b.expiry; }
            render();
          });
          tr.querySelector('.ws-batch').addEventListener('change', (e) => r.batch = e.target.value.toUpperCase());
          tr.querySelector('.ws-exp').addEventListener('change', (e) => r.expiry = e.target.value + '-28');
          tr.querySelector('.ws-qty').addEventListener('change', (e) => { r.qty = Math.max(1, parseInt(e.target.value) || 1); render(); });
          tr.querySelector('.ws-free').addEventListener('change', (e) => { r.freeQty = Math.max(0, parseInt(e.target.value) || 0); render(); });
          tr.querySelector('.ws-rate').addEventListener('change', (e) => { r.rate = Math.max(0, parseFloat(e.target.value) || 0); render(); });
          tr.querySelector('.ws-disc').addEventListener('change', (e) => { r.discPct = Math.min(100, Math.max(0, parseFloat(e.target.value) || 0)); render(); });
          tr.querySelector('.ws-del').addEventListener('click', () => { rows = rows.filter((x) => x.id !== r.id); render(); });
        });

        const t = totals();
        $('#wsSummary').innerHTML = `
          <div class="sum-row"><span class="text-2">Subtotal</span><span class="num">${MF.fmt(t.subtotal, 2)}</span></div>
          <div class="sum-row"><span class="text-2">Discount (line + scheme + overall)</span><span class="num text-danger">− ${MF.fmt(t.discount, 2)}</span></div>
          <div class="sum-row"><span class="text-2">Taxable Amount</span><span class="num fw-semibold">${MF.fmt(t.taxable, 2)}</span></div>
          <div class="sum-row"><span class="text-2">CGST</span><span class="num">${MF.fmt(t.cgst, 2)}</span></div>
          <div class="sum-row"><span class="text-2">SGST</span><span class="num">${MF.fmt(t.sgst, 2)}</span></div>
          <div class="sum-row"><span class="text-2">IGST</span><span class="num">${MF.fmt(t.igst, 2)}</span></div>
          <div class="sum-row"><span class="text-2">Round Off</span><span class="num">${t.roundOff >= 0 ? '+' : '−'} ${MF.fmt(Math.abs(t.roundOff), 2)}</span></div>
          <div class="sum-row total"><span>Grand Total</span><span class="num text-primary">${MF.fmt(t.grand)}</span></div>`;
      }

      function fillParty() {
        const c = MF.cust($('#wsCustomer').value);
        $('#wsGstin').value = c ? (c.gstin === '—' ? '' : c.gstin) : '';
        $('#wsDl').value = c ? (c.dlNo || '') : '';
        $('#wsBillAddr').value = c ? c.address : '';
        if ($('#wsSameAddr').checked) $('#wsShipAddr').value = c ? c.address : '';
      }

      function renderDealers() {
        $('#wsCustomer').innerHTML = D.customers.filter((c) => c.type === 'wholesale').map((c) =>
          `<option value="${c.id}">${MF.esc(c.name)}</option>`).join('');
      }
      renderDealers();
      $('#wsCustomer').addEventListener('change', fillParty);
      $('#wsSameAddr').addEventListener('change', fillParty);
      $('#wsAddRow').addEventListener('click', () => addRow(null));
      $('#wsAddFromSearch').addEventListener('click', () => {
        const q = $('#wsQuickSearch').value.toLowerCase();
        const m = D.medicines.find((x) => (x.name + ' — ' + x.manufacturer).toLowerCase() === q) ||
                  D.medicines.find((x) => x.name.toLowerCase().includes(q));
        if (!m) { MF.toast('Medicine not found in master. Add it from Medicine Master first.', 'warn', 'Not found'); return; }
        addRow(m.id);
        $('#wsQuickSearch').value = '';
      });
      $('#wsQuickSearch').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); $('#wsAddFromSearch').click(); } });
      ['#wsSchemeDisc', '#wsOverallDisc', '#wsInterstate'].forEach((s) => $(s).addEventListener('change', render));

      $('#wsAddDealer').addEventListener('click', () => {
        ['wdName', 'wdGstin', 'wdDl', 'wdPhone', 'wdAddr'].forEach((id) => $('#' + id).value = '');
        new bootstrap.Modal('#wsAddDealerModal').show();
      });
      $('#wdSave').addEventListener('click', async () => {
        const name = $('#wdName').value.trim();
        if (!name) { MF.toast('Dealer name is required.', 'warn'); return; }
        try {
          const res = await MF.Api.post('customers.php', {
            name, type: 'wholesale', gstin: $('#wdGstin').value.trim(), dlNo: $('#wdDl').value.trim(),
            phone: $('#wdPhone').value.trim(), address: $('#wdAddr').value.trim(),
          });
          await MF.rehydrate();
          renderDealers();
          $('#wsCustomer').value = res.id;
          fillParty();
          bootstrap.Modal.getInstance(document.getElementById('wsAddDealerModal')).hide();
          MF.toast('Dealer added.', 'success');
        } catch (err) {
          MF.toast(err.message || 'Could not add dealer.', 'danger');
        }
      });

      $('#wsReset').addEventListener('click', async () => {
        if (!rows.length) return;
        const ok = await MF.confirm({ title: 'Reset this wholesale bill?', message: 'All lines and discounts will be cleared.', confirmText: 'Reset', tone: 'danger' });
        if (ok) { rows = []; render(); }
      });

      $('#wsSave').addEventListener('click', async () => {
        const valid = rows.filter((r) => r.medId && r.qty > 0);
        if (!valid.length) { MF.toast('Add at least one item line before saving.', 'warn', 'Empty bill'); return; }
        if (!$('#wsCustomer').value) { MF.toast('Select a dealer.', 'warn'); return; }
        const c = MF.cust($('#wsCustomer').value);
        const t = totals();
        const pay = document.querySelector('input[name="wsPay"]:checked').value;
        const ok = await MF.confirm({
          title: `Generate invoice for ${MF.fmt(t.grand)}?`,
          message: `${c.name} · ${valid.length} line(s) · Payment: ${pay}`,
          confirmText: 'Generate Invoice'
        });
        if (!ok) return;

        $('#wsSave').disabled = true;
        try {
          const res = await MF.Api.post('wholesale.php', {
            customerId: c.id,
            interstate: $('#wsInterstate').checked,
            schemeDiscPct: parseFloat($('#wsSchemeDisc').value) || 0,
            overallDiscPct: parseFloat($('#wsOverallDisc').value) || 0,
            paymentMode: pay.toLowerCase(),
            items: valid.map((r) => ({ medId: r.medId, batch: r.batch, qty: r.qty, freeQty: r.freeQty, rate: r.rate, discPct: r.discPct })),
          });
          MF.toast(`${res.invoiceNo} generated for ${c.name}`, 'success', 'Invoice saved');
          if (res.balanceDue > 0) MF.toast(`${MF.fmt(res.balanceDue)} posted to ${c.name}'s due ledger`, 'info', 'Credit sale');
          MF.printHtml(`
            <div class="text-center mb-3">
              <img src="assets/images/logo.svg" width="42" alt="">
              <h6 class="fw-bold mt-2 mb-0">${MF.esc(D.store.name)} — TAX INVOICE (Wholesale)</h6>
              ${D.store.address ? `<div class="text-2 small-xs">${MF.esc(D.store.address)}${D.store.gstin ? ' · GSTIN ' + D.store.gstin : ''}</div>` : ''}
              ${D.store.dl20b || D.store.dl21b ? `<div class="text-2 small-xs">DL: ${D.store.dl20b || ''} ${D.store.dl21b ? '/ ' + D.store.dl21b : ''}</div>` : ''}
            </div>
            <div class="d-flex justify-content-between small mb-2">
              <span>Invoice: <strong>${res.invoiceNo}</strong></span><span>${MF.fmtDate(MF.today())}</span>
            </div>
            <div class="small mb-1"><strong>${MF.esc(c.name)}</strong> · ${MF.esc(c.address || '')}</div>
            <div class="small mb-2 text-2">GSTIN: ${c.gstin || '—'} · DL: ${c.dlNo || '—'}</div>
            <table class="table table-sm table-bordered small">
              <thead><tr><th>Item</th><th>Batch</th><th class="text-center">Qty</th><th class="text-center">Free</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
              <tbody>${valid.map((r) => { const m = MF.med(r.medId); return `<tr><td>${MF.esc(m.name)}</td><td class="num">${r.batch}</td><td class="text-center">${r.qty}</td><td class="text-center">${r.freeQty}</td><td class="text-end num">${MF.fmt(r.rate, 2)}</td><td class="text-end num">${MF.fmt(lineAmount(r), 2)}</td></tr>`; }).join('')}</tbody>
            </table>
            <div class="ms-auto" style="max-width:280px">
              <div class="sum-row"><span class="text-2">Taxable</span><span class="num">${MF.fmt(t.taxable, 2)}</span></div>
              <div class="sum-row"><span class="text-2">${t.igst ? 'IGST' : 'CGST + SGST'}</span><span class="num">${MF.fmt(t.cgst + t.sgst + t.igst, 2)}</span></div>
              <div class="sum-row total"><span>Grand Total</span><span class="num">${MF.fmt(res.grandTotal)}</span></div>
            </div>`);
          rows = [];
          await MF.rehydrate();
          render();
        } catch (err) {
          MF.toast(err.message || 'Could not save the invoice.', 'danger', 'Save failed');
        } finally {
          $('#wsSave').disabled = false;
        }
      });

      fillDatalist();
      fillParty();
      render();
    })();
    });
  </script>
</body>
</html>
