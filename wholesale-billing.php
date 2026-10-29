<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php';
if (is_file(__DIR__ . '/core/MenuGate.php')) { require_once __DIR__ . '/core/MenuGate.php'; MenuGate::enforce(); }  // roles matrix + platform kill-switch — typed URLs obey
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- build 2026-10-08.22 - park/resume + duplicate-last-order + dealer phone -->
<title>Wholesale Billing · MediFlow ERP</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .ws-flag-warn td { background:#FFF9EB; }
    .ws-flag-danger td { background:#FEF3F2; }
    .ws-hint { font-size:.66rem; color:#9AA3AF; margin-top:2px; white-space:nowrap; }
    .ws-hint b { color:#64748B; font-weight:600; }
    .ws-headroom { border:1px dashed #EDF1F4; border-radius:10px; background:#FCFDFD; padding:.55rem .7rem; font-size:.74rem; }
    .ws-headroom .h-ok { color:#0F4D42; font-weight:700; }
    .ws-headroom .h-warn { color:#B45309; font-weight:700; }
    .ws-headroom .h-over { color:#B42318; font-weight:700; }
    .ws-lastbox { display:flex; flex-wrap:wrap; gap:5px 8px; align-items:center; margin-top:6px; padding:.35rem .55rem; border:1px dashed #E5E9EF; border-radius:8px; background:#FCFDFD; font-size:.7rem; color:#5B6472; }
    .ws-lastbox[hidden] { display:none; }
    .ws-holdrow { display:flex; gap:8px; align-items:center; padding:.5rem .2rem; border-bottom:1px dashed #EFF2F6; }
    .ws-holdrow:last-child { border-bottom:none; }
    .ws-holdrow .ws-hm { flex:1; min-width:0; }
    .ws-holdrow .ws-ht { font-weight:600; font-size:.8rem; }
    .ws-holdrow .ws-hs { font-size:.68rem; color:#8A93A3; }
  </style>
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
            <p class="page-sub">B2B invoicing · FEFO multi-batch batching · GST series WS/FY · credit-cap aware</p>
          </div>
          <div class="ms-auto">
            <span class="badge badge-soft-info"><i class="bi bi-hash me-1"></i>Series auto · contiguous per FY (GST-clean)</span>
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
                    <div class="ws-hint" id="wsTypeHint"></div>
                    <div class="ws-lastbox" id="wsLastBox" hidden></div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">GSTIN</label>
                    <input class="form-control" id="wsGstin" readonly placeholder="Auto-filled">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Drug License No.</label>
                    <input class="form-control" id="wsDl" readonly placeholder="Auto-filled">
                  </div>
                  <div class="col-md-5">
                    <label class="form-label">Billing Address</label>
                    <textarea class="form-control" id="wsBillAddr" rows="2" readonly placeholder="Auto-filled"></textarea>
                  </div>
                  <div class="col-md-5">
                    <label class="form-label">Shipping Address</label>
                    <textarea class="form-control" id="wsShipAddr" rows="2"></textarea>
                    <div class="form-check mt-1">
                      <input class="form-check-input" type="checkbox" id="wsSameAddr" checked>
                      <label class="form-check-label small text-2" for="wsSameAddr">Same as billing address</label>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Phone</label>
                    <input type="tel" class="form-control" id="wsPhone" placeholder="Contact no." title="Auto-filled from the dealer card — edit to print a different number on this invoice only (master is not changed)">
                  </div>
                </div>
              </div>
            </div>

            <!-- Items -->
            <div class="card-mf">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-box-seam"></i>Items</h2>
                <div class="card-tools">
                  <span class="badge badge-soft-success" title="Suggested ⌊qty/10⌋ — edit on the line if the scheme differs"><i class="bi bi-gift me-1"></i>10+1 free suggested at qty ≥ 10</span>
                  <span class="badge badge-soft-secondary">FEFO splits across batches automatically</span>
                </div>
              </div>
              <div class="table-scroll" style="max-height:none">
                <table class="table table-mf">
                  <thead>
                    <tr>
                      <th style="min-width:230px">Medicine</th><th>Batch pref</th>
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
              <div class="p-2 border-top text-2" style="font-size:.7rem">
                <i class="bi bi-info-circle me-1"></i>Leave Batch blank → stock allocates FEFO, oldest expiry first, split across batches if one can't cover. Type a batch to start from it. Expired batches can never be billed.
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
                <div id="wsFlags" class="mt-2"></div>
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
                <div id="wsHeadroom" hidden></div>
                <div class="d-grid gap-2">
                  <button class="btn btn-mf" id="wsSave"><i class="bi bi-check2-circle me-1"></i>Save &amp; Generate Invoice</button>
                  <div class="d-flex gap-2">
                    <button class="btn btn-light-mf flex-fill" id="wsPark" type="button" title="Set this bill aside and serve another dealer — resume it any time from Parked"><i class="bi bi-pause-circle me-1"></i>Park Bill</button>
                    <button class="btn btn-light-mf flex-fill" id="wsHoldsBtn" type="button"><i class="bi bi-collection me-1"></i>Parked <span class="badge badge-soft-secondary ms-1" id="wsHoldsCount">0</span></button>
                  </div>
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
  <div class="modal fade" id="wsAddDealerModal" tabindex="-1" data-bs-focus="false">
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

  <!-- Parked bills modal -->
  <div class="modal fade" id="wsHoldsModal" tabindex="-1" data-bs-focus="false">
    <div class="modal-dialog modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Parked wholesale bills</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="wsHoldsList"></div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js?v=2026-10-26.04"></script>
  <script src="assets/js/qrcode.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', async () => {
      await MF.boot();
      /* Store extras for the PRINTED invoice (bank A/C, email). Cached once at
         open, then refreshed from the settings fetch the auto-print gate already
         performs — so the first invoice after a settings change is correct too. */
      window.wsStore = {};
      window.wsApplyStore = function (s) {
        s = s || {};
        window.wsStore = {
          bankName: s.store_bank_name || '', bankAcct: s.store_bank_account || '',
          bankIfsc: s.store_bank_ifsc || '', bankBranch: s.store_bank_branch || '',
          email: s.store_email || '',
        };
      };
      try { const r0 = await MF.Api.get('settings.php'); window.wsApplyStore((r0 && r0.data) || r0); } catch (_) { /* settings unreachable → invoice prints without the bank block */ }
    (function () {
      const MF = window.MF, D = window.MF_DATA;
      const $ = (s) => document.querySelector(s);
      let rows = [], seq = 0;
      let interstateTouched = false;
      let shopGstin = null;
      let billId = (crypto.randomUUID ? crypto.randomUUID() : 'ws-' + Date.now() + '-' + Math.random().toString(16).slice(2));

      /* Business accounts only — wholesale / Hospital / Clinic / Others, any case.
         Counter retail customers are never a wholesale billing target. */
      const TYPE_LABEL = { wholesale: 'Wholesale', hospital: 'Hospital', clinic: 'Clinic', others: 'Others' };
      const isBiz = (c) => {
        const t = String(c.type || '').toLowerCase();
        return t !== '' && t !== 'retail' && c.name !== 'Walk-in Customer';
      };
      const bizTypeLabel = (c) => TYPE_LABEL[String(c.type || '').toLowerCase()] || (c.type || 'Business');

      function renderDealers() {
        const opts = D.customers.filter(isBiz)
          .map((c) => `<option value="${c.id}">${MF.esc(c.name)}</option>`).join('');
        $('#wsCustomer').innerHTML = '<option value="">— Select dealer —</option>' + opts;
      }

      function fillDatalist() {
        $('#wsMedList').innerHTML = D.medicines.map((m) => `<option value="${m.name} — ${m.manufacturer}">`).join('');
      }

      async function fetchShopGstin() {
        if (shopGstin !== null) return shopGstin;
        try {
          const res = await MF.Api.get('settings.php');
          const data = (res && res.data) || res || {};
          shopGstin = String(data.store_gstin || '').trim().toUpperCase();
        } catch (e) { shopGstin = ''; }
        return shopGstin;
      }
      const gstState = (g) => (/^[0-9]{2}[A-Z0-9]{13}$/.test(g || '') ? String(g).slice(0, 2) : '');

      async function autoDetectInterstate() {
        if (interstateTouched) return;
        const c = MF.cust($('#wsCustomer').value);
        const supState = c ? gstState(String(c.gstin || '').toUpperCase()) : '';
        const shopState = gstState(await fetchShopGstin());
        $('#wsInterstate').checked = !!(supState && shopState && supState !== shopState);
        render();
      }

      /* Stock hint per medicine: packs available across saleable (unexpired) batches. */
      function stockHint(medId) {
        const all = (typeof MF.batchesOf === 'function') ? MF.batchesOf(medId) : [];
        const batches = all.filter((b) => (MF.daysTo ? MF.daysTo(b.expiry) >= 0 : true) &&
          ((Number(b.qty) || 0) - (Number(b.reserved) || 0)) > 0);
        const total = batches.reduce((s, b) => s + Math.max(0, (Number(b.qty) || 0) - (Number(b.reserved) || 0)), 0);
        return total > 0
          ? `<b>${total}</b> in stock${batches.length > 1 ? ` · ${batches.length} batches` : ''}`
          : '<b style="color:#B42318">out of stock</b>';
      }

      function addRow(medId) {
        const m = medId ? MF.med(medId) : null;
        rows.push({
          id: ++seq, medId: medId || '', batch: 'AUTO',
          qty: 1, freeQty: 0, freeTouched: false, ptr: m ? (Number(m.purchaseRate) || 0) : 0,
          rate: m ? (Number(m.wholesaleRate) || Number(m.retailRate) || Number(m.mrp) || 0) : 0,
          discPct: 0, gst: m ? (Number(m.gst) || 12) : 12, hsn: m ? (m.hsn || '') : '',
        });
        render();
        const tr = document.querySelector(`tr[data-row="${seq}"]`);
        if (tr) (tr.querySelector('.ws-qty') || tr).focus();
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
        return { subtotal, discount, taxable, cgst: igst ? 0 : taxAfterDisc / 2, sgst: igst ? 0 : taxAfterDisc / 2, igst: igst ? taxAfterDisc : 0, roundOff: grand - (taxable + taxAfterDisc), grand, taxTotal: taxAfterDisc };
      }

      /* PTR floor flags: uneffective wholesale wish — rate below cost or crossing
         the batch floor after the FULL discount chain. */
      const combinedDisc = () => (1 - (parseFloat($('#wsSchemeDisc').value) || 0) / 100) * (1 - (parseFloat($('#wsOverallDisc').value) || 0) / 100);
      function rowAudit() {
        const errs = [], warns = [];
        rows.forEach((r) => {
          const m = MF.med(r.medId);
          if (!m || !(r.qty > 0)) return;
          if (r.ptr > 0) {
            const eff = r.rate * (1 - r.discPct / 100) * combinedDisc();
            if (eff < r.ptr - 0.004) {
              errs.push(`${m.name}: effective ${MF.fmt(eff, 2)} is below its ${MF.fmt(r.ptr, 2)} landed cost — this batch sells at a loss.`);
            } else if (r.rate < r.ptr - 0.004) {
              warns.push(`${m.name}: rate ${MF.fmt(r.rate, 2)} is under the ${MF.fmt(r.ptr, 2)} PTR (discounts may still land above cost).`);
            }
          }
          if (Number(m.mrp) > 0 && r.rate > Number(m.mrp) + 0.004) {
            errs.push(`${m.name}: rate ${MF.fmt(r.rate, 2)} crosses the ${MF.fmt(m.mrp, 2)} MRP — the server will refuse the bill.`);
          }
        });
        return { errs, warns };
      }
      function rowFlags(r) {
        if (!r.ptr) return '';
        const eff = r.rate * (1 - r.discPct / 100) * combinedDisc();
        if (eff < r.ptr - 0.004) return 'ws-flag-danger';
        if (r.rate < r.ptr - 0.004) return 'ws-flag-warn';
        return '';
      }

      function render() {
        $('#wsItemsBody').innerHTML = rows.length ? rows.map((r) => `
          <tr data-row="${r.id}" class="${rowFlags(r)}">
            <td>
              <select class="form-select form-select-sm ws-med">
                <option value="">— Select medicine —</option>
                ${D.medicines.map((m) => `<option value="${m.id}" ${Number(r.medId) === Number(m.id) ? 'selected' : ''}>${MF.esc(m.name)}</option>`).join('')}
              </select>
              ${r.medId ? `<div class="ws-hint"><i class="bi bi-boxes me-1"></i>${stockHint(r.medId)}</div>` : ''}
            </td>
            <td><input class="form-control form-control-sm ws-batch num" style="width:86px" value="${MF.esc(r.batch)}" placeholder="AUTO" title="Blank or AUTO = FEFO picks; type to start from this batch"></td>
            <td class="text-center"><input type="number" class="form-control form-control-sm text-center ws-qty" style="width:64px;display:inline-block" value="${r.qty}" min="1"></td>
            <td class="text-center"><input type="number" class="form-control form-control-sm text-center ws-free" style="width:56px;display:inline-block" value="${r.freeQty}" min="0"></td>
            <td class="text-end num text-2 ws-ptr">${MF.fmt(r.ptr, 2)}</td>
            <td class="text-end"><input type="number" step="0.01" class="form-control form-control-sm text-end ws-rate" style="width:86px;display:inline-block" value="${r.rate}"></td>
            <td class="text-center"><input type="number" class="form-control form-control-sm text-center ws-disc" style="width:60px;display:inline-block" value="${r.discPct}" min="0" max="100"></td>
            <td class="text-center num text-2">${r.gst}%</td>
            <td class="text-end num fw-semibold ws-amt">${MF.fmt(lineAmount(r), 2)}</td>
            <td><button class="btn btn-icon btn-light-mf text-danger ws-del"><i class="bi bi-trash3"></i></button></td>
          </tr>`).join('')
          : `<tr><td colspan="10"><div class="empty-state py-4"><i class="bi bi-box-seam"></i>No lines yet — search a medicine above or add a blank row.</div></td></tr>`;

        $('#wsItemsBody').querySelectorAll('tr[data-row]').forEach((tr) => {
          const r = rows.find((x) => x.id === +tr.dataset.row);
          tr.querySelector('.ws-med').addEventListener('change', (e) => {
            r.medId = Number(e.target.value);
            const m = MF.med(r.medId);
            if (m) {
              r.ptr = Number(m.purchaseRate) || 0;
              r.rate = Number(m.wholesaleRate) || Number(m.retailRate) || Number(m.mrp) || 0;
              r.gst = Number(m.gst) || 12;
              r.hsn = m.hsn || '';
            }
            render();
          });
          tr.querySelector('.ws-batch').addEventListener('change', (e) => { r.batch = e.target.value.toUpperCase().trim() || 'AUTO'; });
          tr.querySelector('.ws-qty').addEventListener('change', (e) => {
            r.qty = Math.max(1, parseInt(e.target.value) || 1);
            // Scheme suggestion: ⌊qty/10⌋ free — but only until the cashier edits the field.
            if (!r.freeTouched) r.freeQty = r.qty >= 10 ? Math.floor(r.qty / 10) : 0;
            render();
          });
          tr.querySelector('.ws-free').addEventListener('change', (e) => { r.freeTouched = true; r.freeQty = Math.max(0, parseInt(e.target.value) || 0); render(); });
          tr.querySelector('.ws-rate').addEventListener('change', (e) => { r.rate = Math.max(0, parseFloat(e.target.value) || 0); render(); });
          tr.querySelector('.ws-disc').addEventListener('change', (e) => { r.discPct = Math.min(100, Math.max(0, parseFloat(e.target.value) || 0)); render(); });
          tr.querySelector('.ws-del').addEventListener('click', () => { rows = rows.filter((x) => x.id !== r.id); render(); });
        });

        const t = totals();
        $('#wsSummary').innerHTML = `
          <div class="sum-row"><span class="text-2">Subtotal</span><span class="num">${MF.fmt(t.subtotal, 2)}</span></div>
          <div class="sum-row"><span class="text-2">Discount (line + scheme + overall)</span><span class="num text-danger">− ${MF.fmt(t.discount, 2)}</span></div>
          <div class="sum-row"><span class="text-2">Taxable Amount</span><span class="num fw-semibold">${MF.fmt(t.taxable, 2)}</span></div>
          ${t.igst > 0
            ? `<div class="sum-row"><span class="text-2">IGST</span><span class="num">${MF.fmt(t.igst, 2)}</span></div>`
            : `<div class="sum-row"><span class="text-2">CGST</span><span class="num">${MF.fmt(t.cgst, 2)}</span></div>
               <div class="sum-row"><span class="text-2">SGST</span><span class="num">${MF.fmt(t.sgst, 2)}</span></div>`}
          <div class="sum-row"><span class="text-2">Round Off</span><span class="num">${t.roundOff >= 0 ? '+' : '−'} ${MF.fmt(Math.abs(t.roundOff), 2)}</span></div>
          <div class="sum-row total"><span>Grand Total</span><span class="num text-primary">${MF.fmt(t.grand)}</span></div>`;

        const { errs, warns } = rowAudit();
        $('#wsFlags').innerHTML = (errs.length || warns.length)
          ? `<div style="border:1px dashed #EDF1F4;border-radius:10px;padding:.5rem .65rem;background:#FCFDFD;max-height:120px;overflow:auto">
              ${errs.map((e) => `<div style="display:flex;gap:7px;align-items:flex-start;font-size:.74rem;line-height:1.35;padding:.3rem 0;color:#B42318;font-weight:600"><i class="bi bi-x-octagon" style="margin-top:1px"></i><span>${MF.esc(e)}</span></div>`).join('')}
              ${warns.map((w) => `<div style="display:flex;gap:7px;align-items:flex-start;font-size:.74rem;line-height:1.35;padding:.3rem 0;color:#92400E"><i class="bi bi-exclamation-triangle" style="margin-top:1px"></i><span>${MF.esc(w)}</span></div>`).join('')}
            </div>` : '';
      }

      /* Credit headroom panel — visible on credit mode for capped accounts. */
      async function paintHeadroom() {
        const credit = document.querySelector('input[name="wsPay"]:checked').value === 'Credit';
        const c = MF.cust($('#wsCustomer').value);
        const box = $('#wsHeadroom');
        if (!credit || !c || !(Number(c.credit_limit) > 0)) { box.hidden = true; box.innerHTML = ''; return; }
        box.hidden = false;
        box.innerHTML = '<span class="text-2">Checking headroom…</span>';
        try {
          const res = await MF.Api.get('customer-dues.php?id=' + encodeURIComponent(c.id));
          const out = Number(res && res.data && res.data.customer ? res.data.customer.outstanding : 0);
          const limit = Number(c.credit_limit);
          const free = limit - out;
          const cls = free > limit * 0.2 ? 'h-ok' : free > 0 ? 'h-warn' : 'h-over';
          box.innerHTML = `<span class="${cls}">${MF.fmt(Math.max(0, free))} headroom</span>
            <span class="text-2"> of ${MF.fmt(limit)} cap · dues ${MF.fmt(out)}${c.credit_days ? ' · terms ' + c.credit_days + 'd' : ''}</span>`;
        } catch (e) {
          box.innerHTML = `<span class="h-warn">Cap ₹${MF.fmt(Number(c.credit_limit))}</span> <span class="text-2">· headroom could not be read</span>`;
        }
      }

      function fillParty() {
        const c = MF.cust($('#wsCustomer').value);
        $('#wsGstin').value = c ? (c.gstin === '—' ? '' : (c.gstin || '')) : '';
        $('#wsDl').value = c ? (c.dlNo || c.dl_no || '') : '';
        $('#wsBillAddr').value = c ? c.address : '';
        $('#wsPhone').value = c ? (c.phone || '') : '';
        if ($('#wsSameAddr').checked) $('#wsShipAddr').value = c ? c.address : '';
        $('#wsTypeHint').outerHTML = c ? `<div class="ws-hint" id="wsTypeHint"><i class="bi bi-diagram-3 me-1"></i>${MF.esc(bizTypeLabel(c))}</div>` : '<div class="ws-hint" id="wsTypeHint"></div>';
        autoDetectInterstate();
        paintHeadroom();
        updateLastBox();
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
      ['#wsSchemeDisc', '#wsOverallDisc'].forEach((s) => $(s).addEventListener('change', render));
      $('#wsInterstate').addEventListener('change', () => { interstateTouched = true; render(); });
      document.addEventListener('change', (e) => { if (e.target.name === 'wsPay') paintHeadroom(); });

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

      /* ===== Park / resume + duplicate-last-order (local snapshots) ========
         No line-detail GET exists server-side, so the state captured at park /
         save time is the only complete record — kept on this machine: a small
         FIFO stack of parked bills, and one "last order" snapshot per dealer. */
      const HOLD_KEY = 'mf-ws-holds-v1';
      const LAST_KEY = (id) => 'mf-ws-last-' + id;
      const readJson = (k, fb) => { try { const v = JSON.parse(localStorage.getItem(k)); return v == null ? fb : v; } catch (e) { return fb; } };
      const readHolds = () => { const h = readJson(HOLD_KEY, []); return Array.isArray(h) ? h : []; };
      const writeHolds = (h) => { try { localStorage.setItem(HOLD_KEY, JSON.stringify(h)); } catch (e) { /* storage full */ } };

      function partySnapshot() {
        return {
          custId: Number($('#wsCustomer').value) || 0,
          scheme: parseFloat($('#wsSchemeDisc').value) || 0,
          overall: parseFloat($('#wsOverallDisc').value) || 0,
          interstate: $('#wsInterstate').checked,
          pay: (document.querySelector('input[name="wsPay"]:checked') || {}).value || 'Cash',
          phone: $('#wsPhone').value.trim(),
          shipAddr: $('#wsShipAddr').value,
        };
      }
      function applyPartySnapshot(s) {
        if (s.custId && MF.cust(s.custId)) { $('#wsCustomer').value = s.custId; fillParty(); }
        $('#wsSchemeDisc').value = s.scheme || 0;
        $('#wsOverallDisc').value = s.overall || 0;
        $('#wsInterstate').checked = !!s.interstate;
        interstateTouched = true;
        const r = document.querySelector('input[name="wsPay"][value="' + s.pay + '"]');
        if (r) r.checked = true;
        if (s.phone) $('#wsPhone').value = s.phone;
        if (s.shipAddr) { $('#wsSameAddr').checked = false; $('#wsShipAddr').value = s.shipAddr; }
        paintHeadroom();
      }
      function clearBill(keepParty) {
        rows = [];
        billId = crypto.randomUUID ? crypto.randomUUID() : 'ws-' + Date.now() + '-' + Math.random().toString(16).slice(2);
        $('#wsSchemeDisc').value = 0;
        $('#wsOverallDisc').value = 0;
        const cash = document.querySelector('input[name="wsPay"][value="Cash"]');
        if (cash) cash.checked = true;
        interstateTouched = false;
        if (keepParty !== false) fillParty();
        render(); paintHeadroom();
      }

      /* Park / resume */
      function updateHoldsCount() { $('#wsHoldsCount').textContent = readHolds().length; }
      function snapshotRows(src) {
        return src.filter((r) => r.medId && r.qty > 0).map((r) => ({
          medId: r.medId, batch: r.batch, qty: r.qty, freeQty: r.freeQty, rate: r.rate, discPct: r.discPct,
          name: (MF.med(r.medId) || {}).name || ('#' + r.medId),
        }));
      }
      function buildRowsFrom(lines) {
        const skipped = [];
        rows = [];
        (lines || []).forEach((lr) => {
          const m = MF.med(lr.medId);
          if (!m) { skipped.push(lr.name || ('#' + lr.medId)); return; }
          rows.push({
            id: ++seq, medId: m.id,
            batch: lr.batch && String(lr.batch).toUpperCase() !== 'AUTO' ? lr.batch : 'AUTO',
            qty: lr.qty || 1, freeQty: lr.freeQty || 0, freeTouched: true,
            ptr: Number(m.purchaseRate) || 0,
            rate: lr.rate != null ? lr.rate : (Number(m.wholesaleRate) || Number(m.retailRate) || Number(m.mrp) || 0),
            discPct: lr.discPct || 0, gst: Number(m.gst) || 12, hsn: m.hsn || '',
          });
        });
        return skipped;
      }
      function parkBill() {
        const valid = snapshotRows(rows);
        if (!valid.length) { MF.toast('No item lines with a medicine chosen — nothing worth parking.', 'warn', 'Park'); return; }
        const c = MF.cust($('#wsCustomer').value);
        const holds = readHolds();
        holds.unshift({
          id: 'h' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6),
          heldAt: Date.now(),
          label: (c ? c.name : 'No dealer selected') + ' · ' + valid.length + ' item' + (valid.length > 1 ? 's' : '') + ' · ₹' + MF.fmt(totals().grand),
          party: partySnapshot(),
          rows: valid,
        });
        while (holds.length > 20) { holds.pop(); MF.toast('Oldest parked bill dropped — the stack keeps 20.', 'info', 'Park'); }
        writeHolds(holds);
        updateHoldsCount();
        const nm = c ? c.name : 'Bill';
        clearBill();
        MF.toast(MF.esc(nm) + ' parked — pull it back from “Parked” any time.', 'success', 'Parked');
      }
      function resumeHold(id) {
        const holds = readHolds();
        const h = holds.find((x) => x.id === id);
        if (!h) { updateHoldsCount(); return; }
        writeHolds(holds.filter((x) => x.id !== id));
        updateHoldsCount();
        const skipped = buildRowsFrom(h.rows);
        applyPartySnapshot(h.party || {});
        if (skipped.length) MF.toast(skipped.length + ' line(s) skipped — medicine no longer in master.', 'warn', 'Resume');
        render();
      }
      function renderHoldsList() {
        const holds = readHolds();
        $('#wsHoldsList').innerHTML = holds.length ? holds.map((h) => `
          <div class="ws-holdrow" data-h="${h.id}">
            <div class="ws-hm">
              <div class="ws-ht">${MF.esc(h.label)}</div>
              <div class="ws-hs">Parked ${new Date(h.heldAt).toLocaleString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })}</div>
            </div>
            <button type="button" class="btn btn-sm btn-mf ws-resume">Resume</button>
            <button type="button" class="btn btn-sm btn-light-mf text-danger ws-hdel"><i class="bi bi-trash3"></i></button>
          </div>`).join('')
          : '<div class="empty-state py-4"><i class="bi bi-collection"></i>No parked bills. Use “Park Bill” to set one aside mid-entry.</div>';
        $('#wsHoldsList').querySelectorAll('.ws-holdrow').forEach((el) => {
          el.querySelector('.ws-resume').addEventListener('click', async () => {
            if (rows.some((r) => r.medId)) {
              const ok = await MF.confirm({ title: 'Replace the current bill?', message: 'The lines on screen will be cleared and the parked bill loaded in their place.', confirmText: 'Resume parked bill', tone: 'warn' });
              if (!ok) return;
            }
            bootstrap.Modal.getInstance(document.getElementById('wsHoldsModal')).hide();
            resumeHold(el.dataset.h);
            const c = MF.cust($('#wsCustomer').value);
            MF.toast('Parked bill resumed' + (c ? ' for ' + MF.esc(c.name) : '') + '.', 'success');
          });
          el.querySelector('.ws-hdel').addEventListener('click', async () => {
            const ok = await MF.confirm({ title: 'Delete this parked bill?', message: 'It cannot be recovered once deleted.', confirmText: 'Delete', tone: 'danger' });
            if (!ok) return;
            writeHolds(readHolds().filter((x) => x.id !== el.dataset.h));
            updateHoldsCount(); renderHoldsList();
          });
        });
      }

      /* Duplicate last order */
      function saveLastSnap(c, res) {
        try {
          localStorage.setItem(LAST_KEY(c.id), JSON.stringify({
            invoiceNo: res.invoiceNo, savedAt: Date.now(), grand: res.grandTotal,
            party: partySnapshot(),
            lines: snapshotRows(rows),
          }));
        } catch (e) { /* storage full */ }
      }
      function updateLastBox() {
        const box = $('#wsLastBox');
        const c = MF.cust($('#wsCustomer').value);
        const s = c ? readJson(LAST_KEY(c.id), null) : null;
        if (!s || !(s.lines || []).length) { box.hidden = true; box.innerHTML = ''; return; }
        box.hidden = false;
        box.innerHTML = `<i class="bi bi-arrow-repeat"></i><span>Last order <b>${MF.esc(s.invoiceNo)}</b> · ₹${MF.fmt(s.grand)} · ${s.lines.length} items · ${MF.fmtDate(new Date(s.savedAt).toISOString().slice(0, 10))}</span><button type="button" class="btn btn-sm btn-mf-soft" id="wsDupLast">Duplicate into grid</button>`;
        box.querySelector('#wsDupLast').addEventListener('click', async () => {
          if (rows.some((r) => r.medId)) {
            const ok = await MF.confirm({ title: 'Replace current lines with ' + s.invoiceNo + '?', message: s.lines.length + ' lines will be cloned at last time\'s rates and discounts. Current entries are cleared; batches re-allocate FEFO from today\'s stock.', confirmText: 'Duplicate', tone: 'warn' });
            if (!ok) return;
          }
          const skipped = buildRowsFrom(s.lines);
          applyPartySnapshot(s.party || {});
          render();
          MF.toast(rows.length + ' line(s) cloned from ' + s.invoiceNo + (skipped.length ? ' — ' + skipped.length + ' skipped (no longer in master)' : '') + '.', 'success', 'Duplicated');
        });
      }

      $('#wsReset').addEventListener('click', async () => {
        if (!rows.length) return;
        const ok = await MF.confirm({ title: 'Reset this wholesale bill?', message: 'All lines and discounts will be cleared; the dealer stays selected.', confirmText: 'Reset', tone: 'danger' });
        if (ok) clearBill();
      });
      $('#wsPark').addEventListener('click', parkBill);
      $('#wsHoldsBtn').addEventListener('click', () => { renderHoldsList(); new bootstrap.Modal('#wsHoldsModal').show(); });

      /* ===== A4 tax invoice — wholesale, letterhead-grade =====================
         Same design family as the retail A4 engine, advanced for B2B: Ship-to,
         GST slab table for ITC/GSTR, transparent two-stage bill discounts,
         credit terms + due date, and a REAL amount-encoded UPI pay QR when the
         store has a UPI ID saved. Nothing prints that the data can't prove. */
      function numWords(n) {
        n = Math.round(Math.abs(+n || 0));
        if (!n) return 'ZERO';
        const ONES = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN', 'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];
        const TENS = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];
        const two = (x) => (x < 20 ? ONES[x] : TENS[Math.floor(x / 10)] + (x % 10 ? ' ' + ONES[x % 10] : ''));
        const three = (x) => { const h = Math.floor(x / 100), r = x % 100; return (h ? ONES[h] + ' HUNDRED' + (r ? ' ' : '') : '') + (r ? two(r) : ''); };
        const parts = [];
        const cr = Math.floor(n / 1e7); n %= 1e7;
        const lk = Math.floor(n / 1e5); n %= 1e5;
        const th = Math.floor(n / 1e3); n %= 1e3;
        if (cr) parts.push(three(cr) + ' CRORE');
        if (lk) parts.push(two(lk) + ' LAKH');
        if (th) parts.push(two(th) + ' THOUSAND');
        if (n) parts.push(three(n));
        return parts.join(' ');
      }
      function wsPayQrDataUrl(grand) {
        let upi = '';
        try { upi = String(localStorage.getItem('mf-store-upi') || '').trim(); } catch (e) { /* locked */ }
        if (!upi || typeof QRCode === 'undefined') return null;
        const host = document.createElement('div');
        host.style.display = 'none';
        document.body.appendChild(host);
        try {
          new QRCode(host, {
            text: `upi://pay?pa=${encodeURIComponent(upi)}&pn=${encodeURIComponent((D.store && D.store.name) || 'Pharmacy')}&am=${(+grand || 0).toFixed(2)}&cu=INR`,
            width: 120, height: 120, correctLevel: QRCode.CorrectLevel.M,
          });
          const cv = host.querySelector('canvas');
          return cv ? cv.toDataURL('image/png') : null;
        } catch (e) {
          return null;
        } finally {
          host.remove();
        }
      }
      function invoiceHtml(meta, t, res) {
        const store = D.store || {};
        const wsStore = window.wsStore || {};
        const lines = res.lines || [];
        const gstin = store.gstin || store.gstNo || '';
        const dls = [store.dl20b, store.dl21b].filter(Boolean);
        const payQr = wsPayQrDataUrl(res.grandTotal);
        let storeUpi = '';
        try { storeUpi = String(localStorage.getItem('mf-store-upi') || '').trim(); } catch (e) { /* locked */ }
        // Slab-wise GST — the buyer's CA lives inside this table (GSTR/ITC).
        const afterLine = lines.reduce((s, l) => s + l.amount, 0);
        const share = afterLine > 0 ? (t.taxable / afterLine) : 0;
        const slabs = {};
        lines.forEach((l) => {
          const p = String(l.gstPct);
          if (!slabs[p]) slabs[p] = { taxable: 0 };
          slabs[p].taxable += l.amount;
        });
        Object.keys(slabs).forEach((p) => { slabs[p].tax = slabs[p].taxable * (Number(p) / 100) * share; slabs[p].taxable *= share; });
        const slabRows = Object.keys(slabs).map((p) => {
          const half = slabs[p].tax / 2;
          return `<tr><td class="wi-c">${p}%</td><td class="wi-r">${MF.fmt(slabs[p].taxable, 2)}</td>` +
            (meta.interstate
              ? `<td class="wi-r">${MF.fmt(slabs[p].tax, 2)}</td>`
              : `<td class="wi-r">${MF.fmt(half, 2)}</td><td class="wi-r">${MF.fmt(half, 2)}</td>`) + `</tr>`;
        }).join('');
        const schemePct = +meta.schemeDiscPct || 0, overallPct = +meta.overallDiscPct || 0;
        const schemeAmt = t.subtotal * (schemePct / 100);
        const overallAmt = (t.subtotal - schemeAmt) * (overallPct / 100);
        const discRows =
          (schemePct > 0 ? `<div class="wi-sr"><span class="wi-k">Scheme discount (trade ${schemePct}%)</span><span>− ${MF.fmt(schemeAmt, 2)}</span></div>` : '') +
          (overallPct > 0 ? `<div class="wi-sr"><span class="wi-k">Overall discount (${overallPct}%)</span><span>− ${MF.fmt(overallAmt, 2)}</span></div>` : '') ||
          `<div class="wi-sr"><span class="wi-k">Discounts</span><span>− ${MF.fmt(t.discount, 2)}</span></div>`;
        const grouped = lines.reduce((m, l) => {
          const k = l.medId;
          if (!m[k]) m[k] = 0;
          m[k].qty = (m[k].qty || 0) + l.qty;
          m[k].free = (m[k].free || 0) + (l.freeQty || 0);
          return m;
        }, {});
        const totalPacks = Object.values(grouped).reduce((s, g) => s + (g.qty || 0), 0);
        const totalFree = Object.values(grouped).reduce((s, g) => s + (g.free || 0), 0);
        const splits = [...new Set(lines.map((l) => l.medId))].filter((k) => lines.filter((l) => l.medId == k).length > 1).length;
        const itemRows = lines.map((l, i) => `
          <tr>
            <td class="wi-sno">${i + 1}</td>
            <td class="wi-desc"><b>${MF.esc(l.name)}</b>
              <div class="wi-sub">Batch: <b>${MF.esc(l.batchNo)}</b>${l.expiry ? ` &nbsp;·&nbsp; Exp: <b>${MF.esc(MF.fmtMonthYear ? MF.fmtMonthYear(l.expiry) : l.expiry.slice(0, 7))}</b>` : ''}${l.hsn ? ` &nbsp;·&nbsp; HSN ${MF.esc(l.hsn)}` : ''}${(Number(l.discPct) || 0) > 0 ? ` &nbsp;·&nbsp; Line disc −${Number(l.discPct)}%` : ''}</div></td>
            <td class="wi-c">${l.qty}</td>
            <td class="wi-c">${l.freeQty || '—'}</td>
            <td class="wi-r">${MF.fmt(l.rate, 2)}</td>
            <td class="wi-r">${l.discPct || 0}</td>
            <td class="wi-c">${l.gstPct}%</td>
            <td class="wi-r"><b>${MF.fmt(l.amount, 2)}</b></td>
          </tr>`).join('');
        const wsBankBlock = (wsStore.bankName && wsStore.bankAcct)
          ? `<h4 style="margin-top:7px">Payment \u2014 Bank A/C</h4><div>${MF.esc(wsStore.bankName)} \u00b7 A/C No. <b>${MF.esc(wsStore.bankAcct)}</b>${wsStore.bankIfsc ? ` \u00b7 IFSC <b>${MF.esc(wsStore.bankIfsc)}</b>` : ''}${wsStore.bankBranch ? `<br>Branch: ${MF.esc(wsStore.bankBranch)}` : ''}</div>`
          : '';
        const taxCol = meta.interstate
          ? `<div class="wi-sr"><span class="wi-k">IGST (interstate)</span><span>${MF.fmt(t.igst, 2)}</span></div>`
          : (t.cgst > 0 || t.sgst > 0
            ? `<div class="wi-sr"><span class="wi-k">CGST (share)</span><span>${MF.fmt(t.cgst, 2)}</span></div>
               <div class="wi-sr"><span class="wi-k">SGST (share)</span><span>${MF.fmt(t.sgst, 2)}</span></div>`
            : '');
        const duePanel = (res.balanceDue > 0 || String(meta.pay).toLowerCase() === 'credit')
          ? `<div class="wi-sr" style="color:#B42318"><span class="wi-k" style="color:#B42318">Balance due${res.dueBy ? ' · by ' + MF.fmtDate(res.dueBy) : ''}</span><span class="num">₹ ${MF.fmt(res.balanceDue || 0, 2)}</span></div>`
          : '';
        return `<style>
          @page { size: A4; margin: 12mm 12mm 10mm; }
          .wi-rc { width:180mm; margin:0 auto; background:#fff; color:#111827; font-family:Arial, Helvetica, "Segoe UI", sans-serif; font-size:11px; line-height:1.45; }
          .wi-rc .num { font-variant-numeric:tabular-nums; }
          .wi-lh { display:flex; gap:12px; align-items:flex-start; padding-bottom:9px; border-bottom:2.5px solid #176B5B; }
          .wi-logo { width:48px; height:48px; border-radius:11px; background:#176B5B; color:#fff; display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:800; flex-shrink:0; }
          .wi-shop { font-size:19px; font-weight:800; letter-spacing:.02em; }
          .wi-tag { font-size:9px; color:#4b5563; letter-spacing:.16em; text-transform:uppercase; margin-top:1px; }
          .wi-addr { font-size:10px; color:#4b5563; margin-top:4px; line-height:1.5; }
          .wi-lhr { text-align:right; font-size:9.5px; color:#4b5563; line-height:1.6; min-width:58mm; margin-left:auto; }
          .wi-copytag { display:inline-block; border:1px solid #176B5B; color:#176B5B; font-size:8.5px; font-weight:800; letter-spacing:.12em; padding:2.5px 8px; border-radius:4px; text-transform:uppercase; margin-bottom:5px; }
          .wi-title { display:flex; justify-content:space-between; align-items:center; margin:10px 0 8px; }
          .wi-title h2 { font-size:14.5px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; margin:0; }
          .wi-title .wi-meta { font-size:10px; color:#4b5563; text-align:right; line-height:1.55; }
          .wi-grid { display:grid; grid-template-columns:${meta.shipAddr && meta.shipAddr !== meta.billAddr ? '1fr 1fr 1fr' : '1.4fr 0.9fr'}; border:1px solid #111827; margin-bottom:10px; }
          .wi-ibox { padding:7px 9px; border-right:1px solid #d1d5db; font-size:10px; }
          .wi-ibox:last-child { border-right:0; }
          .wi-ibox h4, .wi-panel h4, .wi-words h4 { font-size:8.5px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:#4b5563; margin:0 0 4px; }
          .wi-ibox .wi-kv, .wi-panel .wi-kv { display:flex; justify-content:space-between; gap:8px; }
          .wi-ibox .wi-kv span:first-child, .wi-panel .wi-kv .k { color:#4b5563; }
          .wi-nm { font-weight:800; font-size:12px; }
          .wi-items { width:100%; border-collapse:collapse; margin-bottom:4px; }
          .wi-items th { background:#f3f4f6; font-size:8.5px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; padding:6px 6px; border:1px solid #111827; text-align:left; }
          .wi-items td { padding:6px 6px; border:1px solid #d1d5db; font-size:10.5px; vertical-align:top; }
          .wi-items .wi-r { text-align:right; } .wi-items .wi-c { text-align:center; }
          .wi-sno { width:8mm; text-align:center; font-weight:700; }
          .wi-sub { color:#4b5563; font-size:9px; margin-top:2px; line-height:1.5; }
          .wi-sumrow { display:flex; gap:10px; margin-top:6px; align-items:flex-start; }
          .wi-words { flex:1; border:1px solid #d1d5db; padding:7px 9px; font-size:10px; }
          .wi-words b { font-size:11.5px; }
          .wi-summary { width:72mm; border:1px solid #111827; }
          .wi-sr { display:flex; justify-content:space-between; padding:5px 9px; font-size:10.5px; border-bottom:1px solid #d1d5db; }
          .wi-sr:last-child { border-bottom:0; }
          .wi-sr .wi-k { color:#4b5563; }
          .wi-sr.wi-net { background:#e6f4ef; font-weight:800; font-size:13px; border-top:1.5px solid #111827; }
          .wi-sr.wi-net .wi-k { color:#111827; }
          .wi-gstbox { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; margin-top:8px; }
          .wi-gstbox table { width:100%; border-collapse:collapse; font-size:10px; }
          .wi-gstbox th { color:#4b5563; font-weight:600; }
          .wi-gstbox td, .wi-gstbox th { padding:2px 3px; }
          .wi-payflex { display:flex; gap:9px; align-items:center; margin-top:8px; }
          .wi-upitxt { font-size:9.5px; color:#4b5563; }
          .wi-lower { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:12px; }
          .wi-panel { border:1px solid #d1d5db; padding:8px 10px; font-size:9.5px; line-height:1.6; }
          .wi-panel ol { margin-left:13px; } .wi-panel li { margin:2px 0; }
          .wi-signrow { display:flex; justify-content:space-between; gap:18px; margin-top:20px; padding-top:6px; }
          .wi-sign { width:58mm; text-align:center; font-size:10px; color:#4b5563; }
          .wi-sign .wi-line { border-top:1px solid #111827; margin:28px 0 4px; }
          .wi-sign b { color:#111827; }
          .wi-decl { margin-top:10px; font-size:9px; color:#4b5563; text-align:center; border-top:1px dashed #d1d5db; padding-top:6px; }
          .wi-foot { margin-top:6px; padding-top:6px; text-align:center; font-size:9px; letter-spacing:.08em; color:#9ca3af; text-transform:uppercase; border-top:1px solid #d1d5db; }
          @media print { .wi-rc { width:auto; margin:0; } }
        </style>
        <div class="wi-rc">
          <div class="wi-lh">
            <div class="wi-logo">+</div>
            <div>
              <div class="wi-shop">${MF.esc(String(store.name || 'Pharmacy').toUpperCase())}</div>
              <div class="wi-tag">Wholesale Distribution · Pharmaceuticals</div>
              <div class="wi-addr">
                ${store.address ? `${MF.esc(store.address)}<br>` : ''}
                ${store.phone ? `Ph: ${MF.esc(store.phone)}` : ''}
              </div>
            </div>
            <div class="wi-lhr">
              <div class="wi-copytag">Original · Buyer Copy</div>
              ${gstin ? `<div style="font-weight:700;color:#111827">GSTIN: ${MF.esc(gstin)}</div>` : ''}
              ${dls.length ? `<div>Drug Lic.: ${MF.esc(dls.join(' / '))}</div>` : ''}
            </div>
          </div>
          <div class="wi-title">
            <h2>Tax Invoice · Wholesale</h2>
            <div class="wi-meta num">
              Invoice No: <b>${MF.esc(res.invoiceNo)}</b><br>
              Date: <b>${MF.fmtDate(MF.today())}</b> · ${meta.interstate ? 'IGST (interstate)' : 'CGST + SGST'}
            </div>
          </div>
          <div class="wi-grid">
            <div class="wi-ibox">
              <h4>Bill To (Buyer)</h4>
              <div class="wi-nm">${MF.esc(meta.name)}</div>
              ${meta.billAddr ? `<div>${MF.esc(meta.billAddr)}</div>` : ''}
              ${meta.phone ? `<div>Ph: ${MF.esc(meta.phone)}</div>` : ''}
              <div>${meta.gstin ? 'GSTIN: ' + MF.esc(meta.gstin) : 'GSTIN: Unregistered'}${meta.dl ? ' · DL: ' + MF.esc(meta.dl) : ''}</div>
            </div>
            ${meta.shipAddr && meta.shipAddr !== meta.billAddr ? `<div class="wi-ibox">
              <h4>Ship To</h4>
              <div>${MF.esc(meta.shipAddr)}</div>
            </div>` : ''}
            <div class="wi-ibox">
              <h4>Invoice Details</h4>
              <div class="wi-kv"><span>Invoice No.</span><span class="num">${MF.esc(res.invoiceNo)}</span></div>
              <div class="wi-kv"><span>Date</span><span class="num">${MF.fmtDate(MF.today())}</span></div>
              <div class="wi-kv"><span>Payment</span><span>${MF.esc(meta.pay)}</span></div>
              <div class="wi-kv"><span>Items / Packs</span><span class="num">${lines.length} / ${totalPacks}${totalFree ? ' (+' + totalFree + ' free)' : ''}</span></div>
            </div>
          </div>
          <table class="wi-items num">
            <thead><tr>
              <th class="wi-sno">#</th>
              <th>Description of Goods (Batch · Expiry · HSN)</th>
              <th style="width:10mm" class="wi-c">Qty</th>
              <th style="width:9mm" class="wi-c">Free</th>
              <th style="width:17mm" class="wi-r">Rate</th>
              <th style="width:10mm" class="wi-r">Disc%</th>
              <th style="width:10mm" class="wi-c">GST</th>
              <th style="width:20mm" class="wi-r">Amount</th>
            </tr></thead>
            <tbody>${itemRows}</tbody>
          </table>
          ${splits ? `<div style="font-size:9px;color:#6B7280;margin-top:2px"><i class="bi bi-info-circle"></i> ${splits} medicine(s) shipped from multiple batches (FEFO) — batch-wise rows above.</div>` : ''}
          <div class="wi-sumrow">
            <div class="wi-words">
              <h4>Amount in Words</h4>
              <b>RUPEES ${numWords(res.grandTotal)} ONLY</b>
              ${storeUpi ? `<div class="wi-payflex">
                ${payQr ? `<img src="${payQr}" alt="UPI QR" style="width:58px;height:58px;flex-shrink:0">` : ''}
                <div class="wi-upitxt"><b>Scan to pay · UPI</b><br>${MF.esc(storeUpi)}<br>Amount: <b>₹ ${MF.fmt(res.grandTotal)}</b></div>
              </div>` : ''}
              <div class="wi-gstbox">
                <h4>GST Summary (for Input Tax Credit)</h4>
                <table>
                  <thead><tr><th class="wi-c">Slab</th><th class="wi-r">Taxable</th>${meta.interstate ? '<th class="wi-r">IGST</th>' : '<th class="wi-r">CGST</th><th class="wi-r">SGST</th>'}</tr></thead>
                  <tbody>${slabRows || `<tr><td colspan="4" style="text-align:center;color:#9CA3AF">No GST on this bill</td></tr>`}</tbody>
                </table>
              </div>
            </div>
            <div class="wi-summary num">
              <div class="wi-sr"><span class="wi-k">Subtotal (after line discounts)</span><span>${MF.fmt(t.subtotal, 2)}</span></div>
              ${discRows}
              <div class="wi-sr"><span class="wi-k">Taxable Value</span><span>${MF.fmt(t.taxable, 2)}</span></div>
              ${taxCol}
              <div class="wi-sr"><span class="wi-k">Round Off</span><span>${MF.fmt(t.roundOff, 2)}</span></div>
              <div class="wi-sr wi-net"><span class="wi-k">GRAND TOTAL</span><span>₹ ${MF.fmt(res.grandTotal)}</span></div>
              <div class="wi-sr"><span class="wi-k">Payment</span><span>${MF.esc(meta.pay)}</span></div>
              ${duePanel}
            </div>
          </div>
          <div class="wi-lower">
            <div class="wi-panel">
              <h4>Terms &amp; Conditions</h4>
              <ol>
                <li>Goods are sold strictly FEFO; batch numbers and expiries appear above. Verify at receipt — transit-damage claims only within 48 hours with this invoice.</li>
                <li>Goods once sold will not be taken back except bonafide quality or expiry claims routed through the distributor agreement.</li>
                <li>Prices are as per prevailing stockist price list on the invoice date; bill-level discounts are shown separately and are not adjustable later.</li>
                <li>Credit bills are payable within agreed terms; overdue balances may pause further supply.</li>
                <li>Subject to local jurisdiction. E. &amp; O.E.</li>
              </ol>
            </div>
            <div class="wi-panel">
              <h4>Declaration</h4>
              <div>We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.</div>
              <h4 style="margin-top:7px">For the Buyer</h4>
              <div>Use this invoice for GST input tax credit as reflected in the slab table. Please retain it for your records and any audit.</div>
              <h4 style="margin-top:7px">Help</h4>
              <div>${store.phone ? `Helpline: ${MF.esc(store.phone)}` : 'Contact the counter store for any discrepancy.'}${(store.email || wsStore.email) ? ` \u00b7 ${MF.esc(store.email || wsStore.email)}` : ''}</div>
              ${wsBankBlock}
            </div>
          </div>
          <div class="wi-signrow">
            <div class="wi-sign">
              <div class="wi-line"></div>
              <b>Received in good order &amp; condition</b><br>(${MF.esc(meta.name)} — Authorised signature)
            </div>
            <div class="wi-sign">
              <div class="wi-line"></div>
              <b>For ${MF.esc(String(store.name || 'Pharmacy').toUpperCase())}</b><br>Authorised Signatory
            </div>
          </div>
          <div class="wi-decl">This is a computer-generated invoice${meta.interstate ? ' · Integrated GST charged as declared for interstate supply' : ''}</div>
          <div class="wi-foot">Thank you for your business · Aapka swasthya, hamari zimmedari</div>
        </div>`;
      }

      async function postBill(extra = {}) {
        const valid = rows.filter((r) => r.medId && r.qty > 0);
        const c = MF.cust($('#wsCustomer').value);
        const pay = document.querySelector('input[name="wsPay"]:checked').value;
        return MF.Api.post('wholesale.php', {
          customerId: c.id,
          interstate: $('#wsInterstate').checked,
          schemeDiscPct: parseFloat($('#wsSchemeDisc').value) || 0,
          overallDiscPct: parseFloat($('#wsOverallDisc').value) || 0,
          paymentMode: pay.toLowerCase(),
          idempotencyKey: billId,
          items: valid.map((r) => ({ medId: r.medId, batch: r.batch, qty: r.qty, freeQty: r.freeQty, rate: r.rate, discPct: r.discPct })),
          ...extra,
        });
      }

      $('#wsSave').addEventListener('click', async () => {
        const valid = rows.filter((r) => r.medId && r.qty > 0);
        if (!valid.length) { MF.toast('Add at least one item line before saving.', 'warn', 'Empty bill'); return; }
        const c = MF.cust($('#wsCustomer').value);
        if (!c) { MF.toast('Select a dealer / customer.', 'warn', 'Dealer'); return; }
        const t = totals();
        const pay = document.querySelector('input[name="wsPay"]:checked').value;
        const { errs } = rowAudit();
        const ok = await MF.confirm({
          title: `Generate invoice for ${MF.fmt(t.grand)}?`,
          message: `${MF.esc(c.name)} · ${valid.length} line(s) · ${pay}${t.igst > 0 ? ' · IGST' : ''}${errs.length ? '\n⚠ ' + errs.length + ' line(s) below cost — they will post knowingly.' : ''}`,
          confirmText: errs.length ? 'Generate anyway' : 'Generate Invoice',
          tone: errs.length ? 'danger' : 'success',
        });
        if (!ok) return;

        $('#wsSave').disabled = true;
        try {
          let res;
          try {
            res = await postBill();
          } catch (first) {
            const msg = String(first && first.message || '');
            if (msg.startsWith('CREDIT_CAP|')) {
              const detail = msg.slice('CREDIT_CAP|'.length);
              const ack = await MF.confirm({
                title: 'Credit cap crossed',
                message: detail + ' Proceeding is fully on record in the sale audit.',
                confirmText: 'Generate on credit anyway',
                tone: 'danger',
              });
              if (!ack) throw first;
              res = await postBill({ creditAcknowledged: true });
            } else throw first;
          }

          if (res.duplicate) {
            MF.toast(`${res.invoiceNo} was already generated for this bill — nothing duplicated.`, 'info', 'Safe retry');
            return;
          }

          saveLastSnap(c, res);
          MF.toast(`${res.invoiceNo} generated for ${c.name}`, 'success', 'Invoice saved');
          if (res.balanceDue > 0) MF.toast(`${MF.fmt(res.balanceDue)} posted to ${MF.esc(c.name)}'s due ledger`, 'info', 'Credit sale');
          const meta = {
            name: c.name, billAddr: $('#wsBillAddr').value, shipAddr: $('#wsShipAddr').value,
            gstin: $('#wsGstin').value, dl: $('#wsDl').value,
            pay, interstate: $('#wsInterstate').checked, phone: $('#wsPhone').value.trim(),
            schemeDiscPct: parseFloat($('#wsSchemeDisc').value) || 0,
            overallDiscPct: parseFloat($('#wsOverallDisc').value) || 0,
          };
          try {
            const cfgR = await MF.Api.get('settings.php');
            const cfg = (cfgR && cfgR.data) || cfgR || {};
            window.wsApplyStore(cfg);   // bank block on this very invoice uses the freshest settings
            if (String(cfg.inv_auto_print || '0') === '1') { MF.printHtml(invoiceHtml(meta, t, res)); }
          } catch (_) { MF.printHtml(invoiceHtml(meta, t, res)); }   // settings unreachable → legacy behavior
          rows = [];
          billId = crypto.randomUUID ? crypto.randomUUID() : 'ws-' + Date.now() + '-' + Math.random().toString(16).slice(2);
          await MF.rehydrate();
          render(); paintHeadroom();
        } catch (err) {
          MF.toast(err.message || 'Could not save the invoice.', 'danger', 'Save failed');
        } finally {
          $('#wsSave').disabled = false;
        }
      });

      fillDatalist();
      fillParty();
      updateHoldsCount();
      render();
    })();
    });
  </script>
</body>
</html>
