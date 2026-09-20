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
  <title>New Purchase · MediFlow ERP</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="purchase">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-bag-plus-fill me-2 text-success"></i>New Purchase Entry</h1>
            <p class="page-sub">Supplier invoice → GRN · batch &amp; expiry capture · stock posting</p>
          </div>
          <div class="ms-auto"><a class="btn btn-light-mf" href="#" onclick="MF.toast('Reports module is coming soon.', 'info'); return false;"><i class="bi bi-file-earmark-ruled me-1"></i>Purchase Invoices</a></div>
        </div>

        <div class="row g-3">
          <div class="col-xxl-8">
            <!-- Supplier -->
            <div class="card-mf mb-3">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-truck"></i>Supplier &amp; Invoice Details</h2></div>
              <div class="p-3">
                <div class="row g-2">
                  <div class="col-md-4">
                    <label class="form-label">Supplier <span class="req">*</span></label>
                    <div class="d-flex gap-2">
                      <select class="form-select" id="puSupplier"></select>
                      <button class="btn btn-light-mf" type="button" id="puAddSupplier" title="Add new supplier"><i class="bi bi-plus-lg"></i></button>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Supplier Invoice No <span class="req">*</span></label>
                    <input class="form-control" id="puInvNo" placeholder="e.g. ML/2026-27/0462">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Invoice Date</label>
                    <input type="date" class="form-control" id="puInvDate">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">GSTIN</label>
                    <input class="form-control" id="puGstin" readonly>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Drug License No.</label>
                    <input class="form-control" id="puDl" readonly>
                  </div>
                </div>
              </div>
            </div>

            <!-- Items -->
            <div class="card-mf">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-box-seam"></i>Items / Batches Received</h2>
                <div class="card-tools"><span class="badge badge-soft-secondary">Each line creates a stock batch</span></div>
              </div>
              <div class="table-scroll" style="max-height:none">
                <table class="table table-mf">
                  <thead>
                    <tr>
                      <th style="min-width:210px">Medicine</th><th>Batch No</th><th>Expiry</th>
                      <th class="text-center">Qty</th><th class="text-center">Free</th>
                      <th class="text-end">Purchase Rate</th><th class="text-center">Disc%</th>
                      <th class="text-center">GST%</th><th class="text-end">Amount</th><th></th>
                    </tr>
                  </thead>
                  <tbody id="puItemsBody"></tbody>
                </table>
              </div>
              <div class="p-3 border-top d-flex flex-wrap gap-2">
                <input class="form-control" id="puQuick" list="puMedList" placeholder="Type medicine name to add a line…" style="max-width:340px">
                <datalist id="puMedList"></datalist>
                <button class="btn btn-mf-soft" id="puAddQuick"><i class="bi bi-plus-lg me-1"></i>Add Line</button>
                <button class="btn btn-light-mf ms-auto" id="puAddBlank"><i class="bi bi-plus-circle me-1"></i>Add Blank Row</button>
              </div>
            </div>
          </div>

          <!-- Right: totals + payment -->
          <div class="col-xxl-4">
            <div class="card-mf mb-3">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-calculator"></i>Totals</h2></div>
              <div class="p-3" id="puSummary"></div>
            </div>
            <div class="card-mf">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-credit-card"></i>Payment</h2></div>
              <div class="p-3">
                <div class="row g-2 row-cols-4 mb-3">
                  <div class="col pay-opt"><input type="radio" name="puPay" id="puPayCash" value="Cash"><label for="puPayCash"><i class="bi bi-cash"></i>Cash</label></div>
                  <div class="col pay-opt"><input type="radio" name="puPay" id="puPayBank" value="Bank" checked><label for="puPayBank"><i class="bi bi-bank"></i>Bank</label></div>
                  <div class="col pay-opt"><input type="radio" name="puPay" id="puPayUpi" value="UPI"><label for="puPayUpi"><i class="bi bi-qr-code-scan"></i>UPI</label></div>
                  <div class="col pay-opt"><input type="radio" name="puPay" id="puPayCredit" value="Credit"><label for="puPayCredit"><i class="bi bi-journal-text"></i>Credit</label></div>
                </div>
                <div class="mb-3" id="puPaidWrap">
                  <label class="form-label">Amount Paid Now (₹)</label>
                  <input type="number" class="form-control" id="puPaid" min="0">
                  <div class="form-text">Balance will be posted to supplier dues.</div>
                </div>
                <div class="d-grid gap-2">
                  <button class="btn btn-mf" id="puSave"><i class="bi bi-check2-circle me-1"></i>Save Purchase (GRN)</button>
                  <button class="btn btn-light-mf" id="puReset"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
                </div>
              </div>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Add Supplier modal -->
  <div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Supplier</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Name <span class="req">*</span></label>
            <input class="form-control" id="asName" placeholder="e.g. Milton Life Sciences">
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">GSTIN</label>
              <input class="form-control" id="asGstin" placeholder="22AAAAA0000A1Z5">
            </div>
            <div class="col-6">
              <label class="form-label">Drug License No.</label>
              <input class="form-control" id="asDl" placeholder="BR-12345">
            </div>
          </div>
          <div class="mb-2 mt-2">
            <label class="form-label">Phone</label>
            <input class="form-control" id="asPhone">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="asSave">Save Supplier</button>
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
      let rows = [], seq = 0;

      $('#puSupplier').innerHTML = D.suppliers.map((s) => `<option value="${s.id}">${s.name}</option>`).join('');
      $('#puMedList').innerHTML = D.medicines.map((m) => `<option value="${m.name} — ${m.manufacturer}">`).join('');
      $('#puInvDate').value = MF.today();

      function fillSupplier() {
        const s = MF.sup($('#puSupplier').value);
        $('#puGstin').value = s ? s.gstin : '';
        $('#puDl').value = s ? s.dlNo : '';
      }

      function addRow(medId) {
        const m = medId ? MF.med(medId) : null;
        rows.push({
          id: ++seq, medId: medId || '', batch: '', expiry: '',
          qty: 1, freeQty: 0, rate: m ? m.purchaseRate : 0, discPct: 0, gst: m ? m.gst : 12
        });
        render();
      }

      const lineAmount = (r) => r.qty * r.rate * (1 - r.discPct / 100);

      function totals() {
        const subtotal = rows.reduce((s, r) => s + r.qty * r.rate, 0);
        const discount = rows.reduce((s, r) => s + r.qty * r.rate * (r.discPct / 100), 0);
        const taxable = subtotal - discount;
        const igst = false; // inward purchases from local distributors — CGST+SGST
        const tax = rows.reduce((s, r) => s + lineAmount(r) * r.gst / 100, 0);
        const grand = Math.round(taxable + tax);
        return { subtotal, discount, taxable, cgst: tax / 2, sgst: tax / 2, igst: 0, roundOff: grand - (taxable + tax), grand };
      }

      function render() {
        $('#puItemsBody').innerHTML = rows.length ? rows.map((r) => `
          <tr data-row="${r.id}">
            <td>
              <select class="form-select form-select-sm pu-med">
                <option value="">— Select medicine —</option>
                ${D.medicines.map((m) => `<option value="${m.id}" ${r.medId === m.id ? 'selected' : ''}>${MF.esc(m.name)}</option>`).join('')}
              </select>
            </td>
            <td><input class="form-control form-control-sm pu-batch num" style="width:96px" placeholder="BATCH" value="${r.batch}"></td>
            <td><input type="month" class="form-control form-control-sm pu-exp" value="${r.expiry}"></td>
            <td class="text-center"><input type="number" class="form-control form-control-sm text-center pu-qty" style="width:64px;display:inline-block" value="${r.qty}" min="1"></td>
            <td class="text-center"><input type="number" class="form-control form-control-sm text-center pu-free" style="width:56px;display:inline-block" value="${r.freeQty}" min="0"></td>
            <td class="text-end"><input type="number" step="0.01" class="form-control form-control-sm text-end pu-rate" style="width:88px;display:inline-block" value="${r.rate}"></td>
            <td class="text-center"><input type="number" class="form-control form-control-sm text-center pu-disc" style="width:60px;display:inline-block" value="${r.discPct}" min="0" max="100"></td>
            <td class="text-center num text-2">${r.gst}%</td>
            <td class="text-end num fw-semibold">${MF.fmt(lineAmount(r), 2)}</td>
            <td><button class="btn btn-icon btn-light-mf text-danger pu-del"><i class="bi bi-trash3"></i></button></td>
          </tr>`).join('')
          : `<tr><td colspan="10"><div class="empty-state py-4"><i class="bi bi-box-seam"></i>Add medicines received in this supplier invoice.</div></td></tr>`;

        $('#puItemsBody').querySelectorAll('tr[data-row]').forEach((tr) => {
          const r = rows.find((x) => x.id === +tr.dataset.row);
          tr.querySelector('.pu-med').addEventListener('change', (e) => {
            r.medId = e.target.value;
            const m = MF.med(r.medId);
            if (m) { r.rate = m.purchaseRate; r.gst = m.gst; }
            render();
          });
          tr.querySelector('.pu-batch').addEventListener('change', (e) => r.batch = e.target.value.toUpperCase());
          tr.querySelector('.pu-exp').addEventListener('change', (e) => r.expiry = e.target.value);
          tr.querySelector('.pu-qty').addEventListener('change', (e) => { r.qty = Math.max(1, parseInt(e.target.value) || 1); render(); });
          tr.querySelector('.pu-free').addEventListener('change', (e) => { r.freeQty = Math.max(0, parseInt(e.target.value) || 0); render(); });
          tr.querySelector('.pu-rate').addEventListener('change', (e) => { r.rate = Math.max(0, parseFloat(e.target.value) || 0); render(); });
          tr.querySelector('.pu-disc').addEventListener('change', (e) => { r.discPct = Math.min(100, Math.max(0, parseFloat(e.target.value) || 0)); render(); });
          tr.querySelector('.pu-del').addEventListener('click', () => { rows = rows.filter((x) => x.id !== r.id); render(); });
        });

        const t = totals();
        $('#puSummary').innerHTML = `
          <div class="sum-row"><span class="text-2">Subtotal</span><span class="num">${MF.fmt(t.subtotal, 2)}</span></div>
          <div class="sum-row"><span class="text-2">Discount</span><span class="num text-danger">− ${MF.fmt(t.discount, 2)}</span></div>
          <div class="sum-row"><span class="text-2">Taxable Amount</span><span class="num fw-semibold">${MF.fmt(t.taxable, 2)}</span></div>
          <div class="sum-row"><span class="text-2">CGST</span><span class="num">${MF.fmt(t.cgst, 2)}</span></div>
          <div class="sum-row"><span class="text-2">SGST</span><span class="num">${MF.fmt(t.sgst, 2)}</span></div>
          <div class="sum-row"><span class="text-2">IGST</span><span class="num">${MF.fmt(t.igst, 2)}</span></div>
          <div class="sum-row"><span class="text-2">Round Off</span><span class="num">${t.roundOff >= 0 ? '+' : '−'} ${MF.fmt(Math.abs(t.roundOff), 2)}</span></div>
          <div class="sum-row total"><span>Grand Total</span><span class="num text-primary">${MF.fmt(t.grand)}</span></div>`;
        if (document.getElementById('puPaid').value === '') $('#puPaid').value = t.grand;
      }

      document.addEventListener('change', (e) => {
        if (e.target.name === 'puPay') {
          const credit = e.target.value === 'Credit';
          $('#puPaidWrap').style.opacity = credit ? 0.4 : 1;
          $('#puPaid').disabled = credit;
          if (credit) $('#puPaid').value = 0; else $('#puPaid').value = totals().grand;
        }
      });

      $('#puAddBlank').addEventListener('click', () => addRow(null));
      $('#puAddQuick').addEventListener('click', () => {
        const q = $('#puQuick').value.toLowerCase();
        const m = D.medicines.find((x) => (x.name + ' — ' + x.manufacturer).toLowerCase() === q) ||
                  D.medicines.find((x) => x.name.toLowerCase().includes(q));
        if (!m) { MF.toast('Medicine not found. Add it in Medicine Master first.', 'warn', 'Not found'); return; }
        addRow(m.id); $('#puQuick').value = '';
      });
      $('#puQuick').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); $('#puAddQuick').click(); } });
      $('#puSupplier').addEventListener('change', fillSupplier);

      $('#puAddSupplier').addEventListener('click', () => {
        ['asName', 'asGstin', 'asDl', 'asPhone'].forEach((id) => $('#' + id).value = '');
        new bootstrap.Modal('#addSupplierModal').show();
      });
      $('#asSave').addEventListener('click', async () => {
        const name = $('#asName').value.trim();
        if (!name) { MF.toast('Supplier name is required.', 'warn'); return; }
        try {
          const res = await MF.Api.post('suppliers.php', {
            name, gstin: $('#asGstin').value.trim(), dlNo: $('#asDl').value.trim(), phone: $('#asPhone').value.trim(),
          });
          await MF.rehydrate();
          $('#puSupplier').innerHTML = D.suppliers.map((s) => `<option value="${s.id}">${MF.esc(s.name)}</option>`).join('');
          $('#puSupplier').value = res.id;
          fillSupplier();
          bootstrap.Modal.getInstance(document.getElementById('addSupplierModal')).hide();
          MF.toast('Supplier added.', 'success');
        } catch (err) {
          MF.toast(err.message || 'Could not add supplier.', 'danger');
        }
      });

      $('#puReset').addEventListener('click', async () => {
        if (!rows.length) return;
        const ok = await MF.confirm({ title: 'Reset purchase entry?', message: 'All lines will be removed.', confirmText: 'Reset', tone: 'danger' });
        if (ok) { rows = []; $('#puInvNo').value = ''; render(); }
      });

      $('#puSave').addEventListener('click', async () => {
        const valid = rows.filter((r) => r.medId && r.qty > 0);
        if (!$('#puSupplier').value) { MF.toast('Select a supplier.', 'warn'); return; }
        if (!$('#puInvNo').value.trim()) { MF.toast('Supplier invoice number is required.', 'warn', 'Validation'); return; }
        if (!valid.length) { MF.toast('Add at least one item line.', 'warn', 'Empty GRN'); return; }
        const t = totals();
        const s = MF.sup($('#puSupplier').value);
        const ok = await MF.confirm({
          title: `Post purchase of ${MF.fmt(t.grand)}?`,
          message: `${s.name} · Invoice ${$('#puInvNo').value} · ${valid.length} batch(es) will be added to stock.`,
          confirmText: 'Post GRN'
        });
        if (!ok) return;

        const payMode = document.querySelector('input[name="puPay"]:checked').value;
        const paid = parseFloat($('#puPaid').value) || 0;

        $('#puSave').disabled = true;
        try {
          const res = await MF.Api.post('purchases.php', {
            supplierId: s.id,
            invoiceNo: $('#puInvNo').value.trim(),
            invoiceDate: $('#puInvDate').value || MF.today(),
            paymentMode: payMode,
            amountPaid: paid,
            items: valid.map((r) => ({
              medId: r.medId, batch: r.batch, expiry: r.expiry,
              qty: r.qty, freeQty: r.freeQty, rate: r.rate, discPct: r.discPct, gst: r.gst,
            })),
          });
          MF.toast(`Purchase #${res.id} posted · stock updated for ${res.batches} batch(es)`, 'success', 'Purchase saved');
          if (res.balanceDue > 0) MF.toast(`${MF.fmt(res.balanceDue)} added to ${s.name}'s payable dues`, 'info', 'Credit purchase');
          rows = []; $('#puInvNo').value = ''; $('#puPaid').value = '';
          await MF.rehydrate();
          render();
        } catch (err) {
          MF.toast(err.message || 'Could not save the purchase.', 'danger', 'Save failed');
        } finally {
          $('#puSave').disabled = false;
        }
      });

      fillSupplier();
      render();
    })();
    });
  </script>
</body>
</html>
