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
  <title>Purchase Return · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="purchase-return">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-box-arrow-in-left me-2 text-success"></i>Purchase Return</h1>
            <p class="page-sub">Return near-expiry, damaged or excess stock to supplier · claim credit note</p>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-xxl-8">
            <div class="card-mf mb-3">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-truck"></i>Select GRN / Purchase Invoice</h2></div>
              <div class="p-3">
                <div class="row g-2">
                  <div class="col-md-5">
                    <label class="form-label">Supplier</label>
                    <select class="form-select" id="prSupplier"></select>
                  </div>
                  <div class="col-md-5">
                    <label class="form-label">Purchase Invoice (GRN)</label>
                    <select class="form-select" id="prGrn"></select>
                  </div>
                  <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-mf w-100" id="prLoad"><i class="bi bi-arrow-down-circle me-1"></i>Load</button>
                  </div>
                </div>
              </div>
            </div>

            <div class="card-mf" id="prPanel" style="display:none">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-box-seam"></i><span id="prGrnTitle"></span></h2>
                <div class="card-tools" id="prGrnMeta"></div>
              </div>
              <div class="table-scroll" style="max-height:none">
                <table class="table table-mf">
                  <thead>
                    <tr>
                      <th>Medicine</th><th>Batch</th><th>Expiry</th><th class="text-end">Purchased Qty</th>
                      <th class="text-end">Available to Return</th>
                      <th class="text-center">Return Qty</th><th class="text-end">Rate</th><th class="text-end">Credit Amount</th>
                    </tr>
                  </thead>
                  <tbody id="prItems"></tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="col-xxl-4">
            <div class="card-mf mb-3">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-chat-left-text"></i>Return Reason</h2></div>
              <div class="p-3">
                <select class="form-select mb-3" id="prReason">
                  <option selected>Near Expiry Return</option>
                  <option>Expired Stock</option>
                  <option>Damaged in Transit</option>
                  <option>Excess Supply / Wrong Item</option>
                  <option>Quality Issue</option>
                  <option>Other</option>
                </select>
                <label class="form-label">Remarks (optional)</label>
                <textarea class="form-control" rows="2" id="prNote" placeholder="Credit note reference, transport details…"></textarea>
              </div>
            </div>
            <div class="card-mf">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-calculator"></i>Credit Summary</h2></div>
              <div class="p-3" id="prSummary">
                <div class="empty-state py-3"><i class="bi bi-box-seam"></i>Load a GRN to begin.</div>
              </div>
              <div class="p-3 pt-0 d-grid">
                <button class="btn btn-mf" id="prProcess" disabled><i class="bi bi-check2-circle me-1"></i>Create Return &amp; Claim Credit</button>
              </div>
            </div>
          </div>
        </div>

      </main>
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
      let purchase = null, items = [], returnQty = [];

      $('#prSupplier').innerHTML = D.suppliers.map((s) => `<option value="${s.id}">${MF.esc(s.name)}</option>`).join('');

      async function loadGrns() {
        const sid = $('#prSupplier').value;
        if (!sid) { $('#prGrn').innerHTML = '<option value="">No suppliers yet</option>'; return; }
        $('#prGrn').innerHTML = '<option>Loading…</option>';
        const ledger = await MF.Api.get('party-ledger.php?type=supplier&id=' + sid);
        const { invoices } = ledger.data ?? ledger;
        $('#prGrn').innerHTML = invoices.length
          ? invoices.map((g) => `<option value="${g.no}">${g.no} · ${MF.fmtDate(g.date)} · ${MF.fmt(g.amount)}</option>`).join('')
          : '<option value="">No purchases recorded</option>';
      }

      async function load() {
        const no = $('#prGrn').value;
        if (!no) { MF.toast('No GRN selected for this supplier.', 'warn'); return; }
        try {
          const res = await MF.Api.get('find-grn.php?no=' + encodeURIComponent(no));
          purchase = res.purchase;
          items = res.items;
          returnQty = items.map(() => 0);
          render();
          MF.toast(purchase.no + ' loaded — ' + items.length + ' line(s) available for return', 'info', 'GRN loaded');
        } catch (err) {
          MF.toast(err.message || 'Could not load this GRN.', 'danger');
        }
      }

      const creditOf = (i) => returnQty[i] * items[i].rate;
      const totalCredit = () => items.reduce((s, _, i) => s + creditOf(i), 0);

      function render() {
        $('#prPanel').style.display = '';
        $('#prGrnTitle').textContent = purchase.no;
        $('#prGrnMeta').innerHTML = `
          <span class="badge badge-soft-secondary">${MF.fmtDate(purchase.date)}</span>
          <span class="badge badge-soft-primary">${MF.esc(purchase.supplier)}</span>`;
        $('#prItems').innerHTML = items.map((l, i) => `
          <tr>
            <td><div class="td-title">${MF.esc(l.name)}</div><div class="td-sub">GST ${l.gst}%</div></td>
            <td class="num">${l.batch}</td>
            <td class="num">${MF.fmtMonthYear(l.expiry)}</td>
            <td class="text-end num">${l.purchasedQty}</td>
            <td class="text-end num text-2">${l.remainingQty}</td>
            <td class="text-center">
              <input type="number" class="form-control form-control-sm text-center pr-qty" data-i="${i}"
                style="width:76px;display:inline-block" value="${returnQty[i]}" min="0" max="${l.remainingQty}"
                ${l.remainingQty === 0 ? 'disabled' : ''}>
            </td>
            <td class="text-end num">${MF.fmt(l.rate, 2)}</td>
            <td class="text-end num fw-semibold pr-credit">${MF.fmt(creditOf(i), 2)}</td>
          </tr>`).join('');
        $('#prItems').querySelectorAll('.pr-qty').forEach((inp) => inp.addEventListener('input', () => {
          const i = +inp.dataset.i, max = items[i].remainingQty;
          returnQty[i] = Math.max(0, Math.min(max, parseInt(inp.value) || 0));
          inp.value = returnQty[i];
          inp.closest('tr').querySelector('.pr-credit').textContent = MF.fmt(creditOf(i), 2);
          renderSummary();
        }));
        renderSummary();
      }

      function renderSummary() {
        const total = totalCredit();
        const units = returnQty.reduce((a, b) => a + b, 0);
        $('#prSummary').innerHTML = `
          <div class="sum-row"><span class="text-2">Units returned</span><span class="num">${units}</span></div>
          <div class="sum-row"><span class="text-2">Reason</span><span>${$('#prReason').value}</span></div>
          <div class="sum-row total"><span>Credit Note Value</span><span class="num text-success">${MF.fmt(total, 2)}</span></div>`;
        $('#prProcess').disabled = total <= 0;
      }

      $('#prReason').addEventListener('change', renderSummary);
      $('#prSupplier').addEventListener('change', () => { loadGrns(); $('#prPanel').style.display = 'none'; });
      $('#prLoad').addEventListener('click', load);

      $('#prProcess').addEventListener('click', async () => {
        const total = totalCredit();
        const ok = await MF.confirm({
          title: `Create purchase return of ${MF.fmt(total, 2)}?`,
          message: `Against ${purchase.no} · ${purchase.supplier}. Stock will be deducted and credited against dues.`,
          confirmText: 'Create Return'
        });
        if (!ok) return;

        $('#prProcess').disabled = true;
        try {
          const res = await MF.Api.post('purchase-return.php', {
            purchaseId: purchase.id,
            reason: $('#prReason').value,
            note: $('#prNote').value.trim(),
            items: items.map((it, i) => ({ purchaseItemId: it.purchaseItemId, qty: returnQty[i] })).filter((x) => x.qty > 0),
          });
          MF.toast(`${res.returnNo} created · credit of ${MF.fmt(res.creditAmount)} claimed from ${purchase.supplier}`, 'success', 'Return created');
          await MF.rehydrate();
          $('#prPanel').style.display = 'none';
          $('#prSummary').innerHTML = `<div class="empty-state py-3"><i class="bi bi-check-circle"></i>${res.returnNo} posted.</div>`;
          purchase = null; items = [];
        } catch (err) {
          MF.toast(err.message || 'Could not process the return.', 'danger');
          $('#prProcess').disabled = false;
        }
      });

      loadGrns();
    })();
    });
  </script>
</body>
</html>
