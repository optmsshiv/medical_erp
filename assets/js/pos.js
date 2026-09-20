/* ==========================================================================
   MediFlow ERP — Retail POS Engine (Stage 1)
   Search → FEFO batch pick → cart → GST-inclusive extraction → payment.
   ========================================================================== */

(function () {
  const MF = window.MF;
  const D = window.MF_DATA;

  const state = {
    cart: [],            // { medId, batchId, qty, rate, mrp, discPct }
    heldBills: [],
    holdSeq: 1,
    payment: 'cash',
    split: { cash: 0, upi: 0 }
  };

  const $ = (s) => document.querySelector(s);
  const walkInId = () => D.customers.find((c) => c.name === 'Walk-in Customer')?.id ?? (D.customers[0]?.id ?? '');

  /* Attach click → addToCart for BOTH search results and the "Fast moving" shortcuts */
  function bindResultClicks(box) {
    box.querySelectorAll('.pos-result:not([disabled])').forEach((btn) =>
      btn.addEventListener('click', () => addToCart(btn.dataset.med)));
  }

  /* ---------------- Medicine search ---------------- */
  function searchMeds(q) {
    q = q.trim().toLowerCase();
    const box = $('#posResults');
    if (!q) { box.innerHTML = emptySearch(); bindResultClicks(box); return; }
    const hits = D.medicines.filter((m) =>
      (m.name + ' ' + m.generic + ' ' + m.composition + ' ' + m.brandRef).toLowerCase().includes(q) ||
      MF.batchesOf(m.id).some((b) => b.batchNo.toLowerCase().includes(q))
    ).slice(0, 8);
    if (!hits.length) { box.innerHTML = `<div class="empty-state"><i class="bi bi-emoji-neutral"></i>No medicine matches “${MF.esc(q)}”.</div>`; return; }
    box.innerHTML = hits.map((m) => {
      const b = MF.pickBatch(m.id);
      const stock = MF.stockOf(m.id);
      const stockBadge = stock === 0 ? MF.badge('Out of stock', 'danger') : stock <= m.minStock ? MF.badge('Low stock', 'warning') : MF.badge('In stock', 'success');
      return `
      <button class="pos-result" data-med="${m.id}" ${!b ? 'disabled' : ''}>
        <div class="kpi-icon tone-primary" style="width:38px;height:38px;flex-basis:38px;font-size:1rem"><i class="bi bi-capsule"></i></div>
        <div class="flex-grow-1 text-start">
          <div class="pr-name">${MF.esc(m.name)} <span class="text-2 fw-normal">· ${MF.esc(m.brandRef)}</span></div>
          <div class="pr-meta">${MF.esc(m.composition)}</div>
          ${b ? `<div class="pr-meta num">Batch ${b.batchNo} · Exp ${MF.fmtMonthYear(b.expiry)} · Avail ${b.qty - b.reserved} / ${stock}</div>`
              : `<div class="pr-meta text-danger">No sellable batch (expired stock only)</div>`}
        </div>
        <div class="text-end">
          <div class="fw-bold num">${MF.fmt(m.mrp, 2)}</div>
          <div class="small-xs text-2">MRP · GST ${m.gst}%</div>
          <div class="mt-1">${stockBadge}</div>
        </div>
      </button>`;
    }).join('');
    bindResultClicks(box);
  }

  function emptySearch() {
    const picks = D.medicines.slice(0, 5);
    if (!picks.length) return `<div class="empty-state"><i class="bi bi-capsule"></i>No medicines yet — add some in Medicine Master.</div>`;
    return `
      <div class="sr-group-label">Quick picks</div>
      ${picks.map((m) => {
        const id = m.id;
        return `<button class="pos-result" data-med="${id}">
          <div class="kpi-icon tone-primary" style="width:38px;height:38px;flex-basis:38px;font-size:1rem"><i class="bi bi-capsule"></i></div>
          <div class="flex-grow-1 text-start">
            <div class="pr-name">${MF.esc(m.name)}</div>
            <div class="pr-meta">${MF.esc(m.composition)} · Stock ${MF.num(MF.stockOf(id))}</div>
          </div>
          <div class="fw-bold num">${MF.fmt(m.mrp, 2)}</div>
        </button>`;
      }).join('')}
      <p class="text-2 small mt-3 mb-0"><i class="bi bi-lightbulb me-1"></i>Search by medicine name, generic name, composition, batch no or barcode.</p>`;
  }

  /* ---------------- Cart ---------------- */
  function addToCart(medId) {
    const med = MF.med(medId);
    const batch = MF.pickBatch(medId);
    if (!batch) { MF.toast('No sellable batch available for ' + med.name, 'warn', 'Stock'); return; }
    const line = state.cart.find((l) => l.batchId === batch.id);
    const avail = batch.qty - batch.reserved;
    if (line) {
      if (line.qty >= avail) { MF.toast(`Only ${avail} units available in batch ${batch.batchNo}`, 'warn', 'Stock limit'); return; }
      line.qty++;
    } else {
      state.cart.push({ medId, batchId: batch.id, qty: 1, rate: med.mrp, mrp: med.mrp, discPct: 0 });
    }
    if (med.rxRequired) MF.toast(med.name + ' is Schedule ' + med.schedule + ' — verify prescription', 'info', 'Rx item');
    renderCart();
  }

  function calcLine(l) {
    const gross = l.qty * l.rate;
    const disc = gross * (l.discPct / 100);
    const net = gross - disc;
    const med = MF.med(l.medId);
    const gstAmt = net - net / (1 + med.gst / 100);   // GST extracted from MRP (inclusive)
    return { gross, disc, net, gstAmt };
  }

  function calcTotals() {
    let subtotal = 0, discount = 0, gst = 0;
    state.cart.forEach((l) => {
      const c = calcLine(l);
      subtotal += c.gross; discount += c.disc; gst += c.gstAmt;
    });
    const globalDisc = parseFloat($('#posGlobalDisc')?.value) || 0;
    const billDisc = (subtotal - discount) * (globalDisc / 100);
    discount += billDisc;
    const net = subtotal - discount;
    const grand = Math.round(net);
    const roundOff = grand - net;
    return { subtotal, discount, gst, net, grand, roundOff };
  }

  function renderCart() {
    const box = $('#posCartBody');
    if (!state.cart.length) {
      box.innerHTML = `<div class="empty-state py-5"><i class="bi bi-cart3"></i>Cart is empty.<br><span class="small">Search a medicine on the left and click to add.</span></div>`;
    } else {
      box.innerHTML = `
        <div class="table-scroll" style="max-height:330px">
        <table class="table table-mf">
          <thead><tr><th>Medicine</th><th class="text-center">Qty</th><th class="text-end">Rate</th><th class="text-center">Disc%</th><th class="text-end">Amount</th><th></th></tr></thead>
          <tbody>
          ${state.cart.map((l, i) => {
            const med = MF.med(l.medId);
            const b = D.batches.find((x) => x.id === l.batchId);
            const c = calcLine(l);
            return `<tr>
              <td style="min-width:170px">
                <div class="td-title">${MF.esc(med.name)}</div>
                <div class="td-sub num">B: ${b.batchNo} · Exp ${MF.fmtMonthYear(b.expiry)} · GST ${med.gst}%</div>
              </td>
              <td class="text-center">
                <div class="qty-stepper">
                  <button data-a="dec" data-i="${i}" type="button">−</button>
                  <input value="${l.qty}" data-a="qty" data-i="${i}" inputmode="numeric">
                  <button data-a="inc" data-i="${i}" type="button">+</button>
                </div>
              </td>
              <td class="text-end num">${MF.fmt(l.rate, 2)}</td>
              <td class="text-center">
                <input class="form-control form-control-sm text-center" style="width:60px;display:inline-block" value="${l.discPct}" data-a="disc" data-i="${i}" inputmode="decimal">
              </td>
              <td class="text-end num fw-semibold">${MF.fmt(c.net, 2)}</td>
              <td><button class="btn btn-icon btn-light-mf text-danger" data-a="rm" data-i="${i}" title="Remove"><i class="bi bi-trash3"></i></button></td>
            </tr>`;
          }).join('')}
          </tbody>
        </table>
        </div>`;
      box.querySelectorAll('[data-a]').forEach((el) => {
        el.addEventListener(el.tagName === 'INPUT' ? 'change' : 'click', () => {
          const i = +el.dataset.i, l = state.cart[i], a = el.dataset.a;
          const b = D.batches.find((x) => x.id === l.batchId);
          if (a === 'inc') { if (l.qty >= b.qty - b.reserved) { MF.toast(`Batch limit reached (${b.qty - b.reserved} avail)`, 'warn', 'Stock'); } else l.qty++; }
          if (a === 'dec') l.qty = Math.max(1, l.qty - 1);
          if (a === 'qty') l.qty = Math.max(1, Math.min(b.qty - b.reserved, parseInt(el.value) || 1));
          if (a === 'disc') l.discPct = Math.max(0, Math.min(100, parseFloat(el.value) || 0));
          if (a === 'rm') state.cart.splice(i, 1);
          renderCart(); renderSummary();
        });
      });
    }
    renderSummary();
    renderRxChip();
  }

  function renderSummary() {
    const t = calcTotals();
    $('#posSummary').innerHTML = `
      <div class="sum-row"><span class="text-2">Subtotal (MRP)</span><span class="num">${MF.fmt(t.subtotal, 2)}</span></div>
      <div class="sum-row"><span class="text-2">Discount</span><span class="num text-danger">− ${MF.fmt(t.discount, 2)}</span></div>
      <div class="sum-row"><span class="text-2">GST included in MRP</span><span class="num">${MF.fmt(t.gst, 2)}</span></div>
      <div class="sum-row"><span class="text-2">Round off</span><span class="num">${t.roundOff >= 0 ? '+' : '−'} ${MF.fmt(Math.abs(t.roundOff), 2)}</span></div>
      <div class="sum-row total"><span>Grand Total</span><span class="num text-primary">${MF.fmt(t.grand)}</span></div>`;
  }

  function renderRxChip() {
    const hasRx = state.cart.some((l) => MF.med(l.medId).rxRequired);
    $('#posRxChip').style.display = hasRx ? 'inline-flex' : 'none';
  }

  /* ---------------- Payments ---------------- */
  function bindPayments() {
    document.querySelectorAll('input[name="posPay"]').forEach((r) =>
      r.addEventListener('change', () => {
        state.payment = r.value;
        if (r.value === 'credit') {
          const cust = $('#posCustomer').value;
          if (MF.cust(cust)?.name === 'Walk-in Customer') { MF.toast('Credit sales are not allowed for Walk-in Customer', 'err', 'Payment'); $('#posPayCash').checked = true; state.payment = 'cash'; }
          else MF.toast('Due will be posted to the customer ledger', 'info', 'Credit sale');
        }
        if (r.value === 'split') new bootstrap.Modal($('#posSplitModal')).show();
      }));
    $('#posSplitApply').addEventListener('click', () => {
      const t = calcTotals();
      const cash = parseFloat($('#splitCash').value) || 0;
      const upi = parseFloat($('#splitUpi').value) || 0;
      if (Math.abs(cash + upi - t.grand) > 0.5) { MF.toast(`Split amounts must equal Grand Total ${MF.fmt(t.grand)}`, 'warn', 'Split payment'); return; }
      state.split = { cash, upi };
      bootstrap.Modal.getInstance($('#posSplitModal')).hide();
      MF.toast(`Split saved — Cash ${MF.fmt(cash)} + UPI ${MF.fmt(upi)}`, 'success', 'Split payment');
    });
  }

  /* ---------------- Held bills ---------------- */
  function holdBill() {
    if (!state.cart.length) { MF.toast('Cart is empty — nothing to hold', 'warn'); return; }
    state.heldBills.push({ id: state.holdSeq++, customer: $('#posCustomer').value, items: JSON.parse(JSON.stringify(state.cart)) });
    state.cart = []; renderCart();
    updateHoldBadge();
    MF.toast('Bill held. Retrieve it from the Held Bills chip.', 'info', 'Bill held');
  }

  function updateHoldBadge() {
    const b = $('#posHeldBadge');
    b.textContent = state.heldBills.length;
    b.style.display = state.heldBills.length ? 'inline-flex' : 'none';
  }

  function showHeldBills() {
    const body = $('#posHeldBody');
    if (!state.heldBills.length) { body.innerHTML = `<div class="empty-state"><i class="bi bi-hourglass"></i>No held bills.</div>`; }
    else {
      body.innerHTML = `<div class="table-mf border rounded">${state.heldBills.map((h) => `
        <div class="d-flex align-items-center justify-content-between p-2 border-bottom">
          <div>
            <div class="fw-semibold small">Hold #${h.id} · ${MF.esc(MF.cust(h.customer).name)}</div>
            <div class="text-2 small-xs">${h.items.length} item(s)</div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-mf-soft" data-load="${h.id}">Load</button>
            <button class="btn btn-sm btn-light-mf text-danger" data-del="${h.id}"><i class="bi bi-trash3"></i></button>
          </div>
        </div>`).join('')}</div>`;
      body.querySelectorAll('[data-load]').forEach((b) => b.addEventListener('click', () => {
        const h = state.heldBills.find((x) => x.id === +b.dataset.load);
        state.cart = h.items; $('#posCustomer').value = h.customer;
        state.heldBills = state.heldBills.filter((x) => x.id !== h.id);
        updateHoldBadge(); renderCart();
        bootstrap.Modal.getInstance($('#posHeldModal')).hide();
        MF.toast('Held bill loaded into cart', 'success');
      }));
      body.querySelectorAll('[data-del]').forEach((b) => b.addEventListener('click', () => {
        state.heldBills = state.heldBills.filter((x) => x.id !== +b.dataset.del);
        updateHoldBadge(); showHeldBills();
      }));
    }
  }

  /* ---------------- Complete sale ---------------- */
  function receiptHtml(invNo, t) {
    const cust = MF.cust($('#posCustomer').value);
    const payLabel = { cash: 'Cash', upi: 'UPI', card: 'Card', credit: 'Credit', split: `Split (Cash ${MF.fmt(state.split.cash)} + UPI ${MF.fmt(state.split.upi)})` }[state.payment];
    return `
      <div class="text-center mb-3">
        <img src="assets/images/logo.svg" width="42" alt="">
        <h6 class="fw-bold mt-2 mb-0">${MF.esc((window.MF_STORE && window.MF_STORE.name) || 'Your Pharmacy')}</h6>
      </div>
      <div class="d-flex justify-content-between small mb-2">
        <span>Invoice: <strong>${invNo}</strong></span><span>${MF.fmtDate(MF.today())}</span>
      </div>
      <div class="small mb-2">Customer: <strong>${MF.esc(cust.name)}</strong> · Payment: <strong>${payLabel}</strong></div>
      <table class="table table-sm table-bordered small">
        <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Rate</th><th class="text-end">Amt</th></tr></thead>
        <tbody>${state.cart.map((l) => {
          const m = MF.med(l.medId), c = calcLine(l);
          return `<tr><td>${MF.esc(m.name)}</td><td class="text-center">${l.qty}</td><td class="text-end num">${MF.fmt(l.rate, 2)}</td><td class="text-end num">${MF.fmt(c.net, 2)}</td></tr>`;
        }).join('')}</tbody>
      </table>
      <div class="ms-auto" style="max-width:260px">
        <div class="sum-row"><span class="text-2">Subtotal</span><span class="num">${MF.fmt(t.subtotal, 2)}</span></div>
        <div class="sum-row"><span class="text-2">Discount</span><span class="num">− ${MF.fmt(t.discount, 2)}</span></div>
        <div class="sum-row"><span class="text-2">Round off</span><span class="num">${t.roundOff >= 0 ? '+' : '−'} ${MF.fmt(Math.abs(t.roundOff), 2)}</span></div>
        <div class="sum-row total"><span>Total</span><span class="num">${MF.fmt(t.grand)}</span></div>
      </div>
      <p class="text-center text-2 small-xs mt-3 mb-0">Medicines once sold will not be taken back without valid reason · Get well soon!</p>`;
  }

  async function completeSale() {
    if (!state.cart.length) { MF.toast('Cart is empty', 'warn', 'Cannot complete sale'); return; }
    const t = calcTotals();
    const ok = await MF.confirm({ title: `Complete sale for ${MF.fmt(t.grand)}?`, message: `Payment mode: ${state.payment.toUpperCase()}. Stock will be deducted from FEFO batches.`, confirmText: 'Complete Sale' });
    if (!ok) return;

    $('#posComplete').disabled = true;
    try {
      const res = await MF.Api.post('sales.php', {
        customerId: $('#posCustomer').value,
        paymentMode: state.payment,
        globalDiscPct: parseFloat($('#posGlobalDisc').value) || 0,
        splitCash: state.split.cash,
        splitUpi: state.split.upi,
        items: state.cart.map((l) => ({ medId: l.medId, batchId: l.batchId, qty: l.qty, rate: l.rate, discPct: l.discPct })),
      });
      MF.printHtml(receiptHtml(res.invoiceNo, t));
      MF.toast(`${res.invoiceNo} · ${MF.fmt(res.grandTotal)} · ${state.payment.toUpperCase()}`, 'success', 'Sale completed');
      if (res.balanceDue > 0) MF.toast(`${MF.fmt(res.balanceDue)} added to customer dues`, 'info', 'Credit sale');
      state.cart = [];
      await MF.rehydrate(); // refresh D.batches so stock levels are current
      $('#posCustomer').value = walkInId();
      renderCart();
    } catch (err) {
      MF.toast(err.message || 'Could not complete the sale.', 'danger', 'Sale failed');
    } finally {
      $('#posComplete').disabled = false;
    }
  }

  /* ---------------- Init ---------------- */
  document.addEventListener('DOMContentLoaded', async () => {
    if (!document.getElementById('posSearch')) return;
    await MF.boot();

    $('#posSearch').addEventListener('input', (e) => searchMeds(e.target.value));
    searchMeds('');

    $('#posClearCart').addEventListener('click', async () => {
      if (!state.cart.length) return;
      const ok = await MF.confirm({ title: 'Clear current bill?', message: 'All cart items will be removed.', confirmText: 'Clear', tone: 'danger' });
      if (ok) { state.cart = []; renderCart(); }
    });
    $('#posHold').addEventListener('click', holdBill);
    $('#posDraft').addEventListener('click', () => MF.toast('Draft saving isn\'t available yet — use Hold Bill instead for now.', 'info', 'Save Draft'));
    $('#posHeldChip').addEventListener('click', () => { showHeldBills(); new bootstrap.Modal($('#posHeldModal')).show(); });
    $('#posPrint').addEventListener('click', () => {
      if (!state.cart.length) { MF.toast('Cart is empty — nothing to print', 'warn'); return; }
      MF.printHtml(receiptHtml('DRAFT', calcTotals()));
    });
    $('#posComplete').addEventListener('click', completeSale);
    $('#posGlobalDisc').addEventListener('input', renderSummary);
    bindPayments();
    renderCart();
  });
})();
