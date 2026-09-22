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
  <title>Sales Return · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
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
            <p class="page-sub">Search the original invoice → select return quantities → process refund</p>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-xxl-8">
            <div class="card-mf mb-3">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-search"></i>Find Original Invoice</h2></div>
              <div class="p-3">
                <div class="input-group" style="max-width:420px">
                  <span class="input-group-text"><i class="bi bi-receipt"></i></span>
                  <input class="form-control" id="srInvNo" placeholder="e.g. INV-26-0001">
                  <button class="btn btn-mf" id="srFind"><i class="bi bi-search me-1"></i>Find</button>
                </div>
              </div>
            </div>

            <div class="card-mf" id="srPanel" style="display:none">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-file-earmark-text"></i><span id="srInvTitle"></span></h2>
                <div class="card-tools" id="srInvMeta"></div>
              </div>
              <div class="table-scroll" style="max-height:none">
                <table class="table table-mf">
                  <thead>
                    <tr>
                      <th>Medicine</th><th>Batch</th><th class="text-end">Sold Qty</th>
                      <th class="text-end">Already Returned</th>
                      <th class="text-center">Return Qty</th><th class="text-end">Rate</th><th class="text-end">Refund Amount</th>
                    </tr>
                  </thead>
                  <tbody id="srItems"></tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="col-xxl-4">
            <div class="card-mf mb-3">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-chat-left-text"></i>Return Reason</h2></div>
              <div class="p-3">
                <select class="form-select mb-3" id="srReason">
                  <option>Damaged</option>
                  <option>Wrong Medicine</option>
                  <option selected>Customer Return</option>
                  <option>Expired</option>
                  <option>Other</option>
                </select>
                <label class="form-label">Remarks (optional)</label>
                <textarea class="form-control" rows="2" id="srNote" placeholder="Any note for the audit trail…"></textarea>
              </div>
            </div>
            <div class="card-mf">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-calculator"></i>Refund Summary</h2></div>
              <div class="p-3" id="srSummary">
                <div class="empty-state py-3"><i class="bi bi-receipt"></i>Find an invoice to begin.</div>
              </div>
              <div class="p-3 pt-0 d-grid">
                <button class="btn btn-mf" id="srProcess" disabled><i class="bi bi-check2-circle me-1"></i>Process Return &amp; Refund</button>
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
      const MF = window.MF;
      const $ = (s) => document.querySelector(s);
      let sale = null, items = [], returnQty = [];

      async function find() {
        const no = $('#srInvNo').value.trim().toUpperCase();
        if (!no) return;
        try {
          const res = await MF.Api.get('find-invoice.php?no=' + encodeURIComponent(no));
          sale = res.sale;
          items = res.items;
          returnQty = items.map(() => 0);
          render();
          MF.toast('Invoice ' + sale.no + ' loaded with ' + items.length + ' item(s).', 'info', 'Invoice found');
        } catch (err) {
          MF.toast(err.message || 'Invoice not found.', 'warn', 'Not found');
          $('#srPanel').style.display = 'none';
          $('#srSummary').innerHTML = `<div class="empty-state py-3"><i class="bi bi-receipt"></i>Find an invoice to begin.</div>`;
          $('#srProcess').disabled = true;
        }
      }

      function refundOf(i) { return returnQty[i] * items[i].rate; }
      function totalRefund() { return items.reduce((s, _, i) => s + refundOf(i), 0); }

      function render() {
        $('#srPanel').style.display = '';
        $('#srInvTitle').textContent = sale.no;
        $('#srInvMeta').innerHTML = `
          <span class="badge badge-soft-secondary">${MF.fmtDate(sale.date)}</span>
          <span class="badge badge-soft-primary">${MF.esc(sale.customer)}</span>
          <span class="badge badge-soft-${sale.channel === 'retail' ? 'info' : 'warning'}">${sale.channel === 'retail' ? 'Retail' : 'Wholesale'}</span>`;
        $('#srItems').innerHTML = items.map((it, i) => `
          <tr>
            <td><div class="td-title">${MF.esc(it.name)}</div><div class="td-sub">GST ${it.gst}%</div></td>
            <td class="num">${it.batch}</td>
            <td class="text-end num">${it.soldQty}</td>
            <td class="text-end num text-2">${it.alreadyReturned}</td>
            <td class="text-center">
              <input type="number" class="form-control form-control-sm text-center sr-qty" data-i="${i}"
                style="width:76px;display:inline-block" value="${returnQty[i]}" min="0" max="${it.remainingQty}"
                ${it.remainingQty === 0 ? 'disabled' : ''}>
            </td>
            <td class="text-end num">${MF.fmt(it.rate, 2)}</td>
            <td class="text-end num fw-semibold sr-refund">${MF.fmt(refundOf(i), 2)}</td>
          </tr>`).join('');
        $('#srItems').querySelectorAll('.sr-qty').forEach((inp) => inp.addEventListener('input', () => {
          const i = +inp.dataset.i, max = items[i].remainingQty;
          returnQty[i] = Math.max(0, Math.min(max, parseInt(inp.value) || 0));
          inp.value = returnQty[i];
          inp.closest('tr').querySelector('.sr-refund').textContent = MF.fmt(refundOf(i), 2);
          renderSummary();
        }));
        renderSummary();
      }

      function renderSummary() {
        const total = totalRefund();
        const units = returnQty.reduce((a, b) => a + b, 0);
        $('#srSummary').innerHTML = `
          <div class="sum-row"><span class="text-2">Units returned</span><span class="num">${units}</span></div>
          <div class="sum-row"><span class="text-2">Reason</span><span>${$('#srReason').value}</span></div>
          <div class="sum-row total"><span>Refund Amount</span><span class="num text-danger">${MF.fmt(total, 2)}</span></div>`;
        $('#srProcess').disabled = total <= 0;
      }

      $('#srReason').addEventListener('change', renderSummary);
      $('#srFind').addEventListener('click', find);
      $('#srInvNo').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); find(); } });

      $('#srProcess').addEventListener('click', async () => {
        const total = totalRefund();
        const ok = await MF.confirm({
          title: `Process return of ${MF.fmt(total, 2)}?`,
          message: `Against ${sale.no} · Reason: ${$('#srReason').value}. Returned stock goes back to batch inventory.`,
          confirmText: 'Process Return', tone: 'danger'
        });
        if (!ok) return;

        $('#srProcess').disabled = true;
        try {
          const res = await MF.Api.post('sales-return.php', {
            saleId: sale.id,
            reason: $('#srReason').value,
            note: $('#srNote').value.trim(),
            items: items.map((it, i) => ({ saleItemId: it.saleItemId, qty: returnQty[i] })).filter((x) => x.qty > 0),
          });
          MF.toast(`${res.returnNo} created · refund ${MF.fmt(res.refundAmount)} issued to ${sale.customer}`, 'success', 'Return processed');
          await MF.rehydrate(); // refresh batch stock levels app-wide
          $('#srPanel').style.display = 'none';
          $('#srSummary').innerHTML = `<div class="empty-state py-3"><i class="bi bi-check-circle"></i>${res.returnNo} posted.</div>`;
          $('#srInvNo').value = ''; sale = null; items = [];
        } catch (err) {
          MF.toast(err.message || 'Could not process the return.', 'danger', 'Failed');
          $('#srProcess').disabled = false;
        }
      });
    })();
    });
  </script>
</body>
</html>
