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
    split: { cash: 0, upi: 0 },
    tender: null,        // { cashReceived, changeReturned } for the open bill
    tenderOpen: false
  };

  const $ = (s) => document.querySelector(s);

  /* Button styles that need :hover / :active (can't be done with inline styles) */
  if (!document.getElementById('pos-btn-styles')) {
    const st = document.createElement('style');
    st.id = 'pos-btn-styles';
    st.textContent = `
      .pos-loose-add {
        background:#e7edf6; color:#16325c; border:1px solid transparent; border-radius:8px;
        padding:6px 14px; font-weight:500; display:inline-flex; align-items:center; gap:4px;
        transition:background-color .15s ease, color .15s ease, transform .15s ease, box-shadow .15s ease;
      }
      .pos-loose-add:hover {
        background:#16325c; color:#fff;
        transform:translateY(-1px); box-shadow:0 4px 10px rgba(22,50,92,.25);
      }
      .pos-loose-add:active { transform:translateY(0); box-shadow:none; background:#0f2444; color:#fff; }
      .pos-loose-add:focus-visible { outline:2px solid #16325c; outline-offset:2px; }

      /* Order / substitute (out of stock) */
      .pos-order-sub {
        border:1.5px solid #8b5cf6; border-radius:10px; background:#f5f3ff; color:#6d28d9;
        padding:8px 12px; display:inline-flex; flex-direction:row; align-items:center; justify-content:center;
        gap:6px; white-space:nowrap;
        transition:background-color .15s ease, color .15s ease, border-color .15s ease, transform .15s ease, box-shadow .15s ease;
      }
      .pos-order-sub i { font-size:1rem; transition:transform .35s ease; }
      .pos-order-sub:hover {
        background:#7c3aed; border-color:#7c3aed; color:#fff;
        transform:translateY(-1px); box-shadow:0 4px 12px rgba(124,58,237,.30);
      }
      .pos-order-sub:hover i { transform:rotate(180deg); }
      .pos-order-sub:active { transform:translateY(0); box-shadow:none; background:#6d28d9; border-color:#6d28d9; color:#fff; }
      .pos-order-sub:focus-visible { outline:2px solid #7c3aed; outline-offset:2px; }

      /* Stock status badge (next to MRP) */
      .pos-stock-badge {
        display:inline-flex; align-items:center; margin-left:8px; padding:1px 8px;
        font-size:.68rem; font-weight:600; line-height:1.5; border-radius:3px; vertical-align:middle;
      }
      .pos-stock-badge.in  { background:#e6f6ec; color:#157347; }
      .pos-stock-badge.low { background:#fff4dc; color:#a86400; }
      .pos-stock-badge.out { background:#fdeaea; color:#c62828; }

      /* Cash tender on Complete sale */
      .pos-tender-dialog { max-width: 540px; }
      .pos-tender .modal-header { border-bottom: 1px solid #e4ebf3; align-items: flex-start; }
      .pos-tender .modal-body { padding: 16px 18px 8px; }
      .pos-tender .modal-footer { border-top: 0; padding: 4px 18px 16px; }
      .pos-tender-collect {
        display:flex; align-items:flex-end; justify-content:space-between; gap:12px;
        padding:12px 14px; border-radius:14px; background:#f6f9fc; border:1px solid #e4ebf3;
      }
      .pos-tender-k { font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#66758a; }
      .pos-tender-due { font-size:28px; font-weight:750; letter-spacing:-.03em; font-variant-numeric:tabular-nums; line-height:1.1; color:#1b2430; }
      .pos-tender-meta { color:#66758a; font-size:12px; text-align:right; line-height:1.45; }
      .pos-tender-label { font-size:13px; font-weight:700; margin:14px 0 6px; color:#1b2430; }
      .pos-tender-money {
        display:flex; align-items:center; gap:8px; border:1.5px solid #d5deea;
        border-radius:12px; padding:0 12px; height:52px; background:#fff;
      }
      .pos-tender-money:focus-within { border-color:#16325c; box-shadow:0 0 0 4px rgba(22,50,92,.12); }
      .pos-tender-money span { color:#66758a; font-size:20px; font-weight:700; }
      .pos-tender-money input {
        border:0; outline:0; width:100%; font-size:26px; font-weight:700; color:#1b2430;
        letter-spacing:-.03em; font-variant-numeric:tabular-nums; background:transparent;
      }
      .pos-tender-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
      .pos-tender-chip {
        border:1px solid #d7e0ea; background:#fff; border-radius:999px; padding:6px 11px;
        font-size:13px; font-weight:700; color:#16325c; cursor:pointer;
      }
      .pos-tender-chip.on { background:#16325c; border-color:#16325c; color:#fff; }
      .pos-tender-work { display:grid; grid-template-columns:minmax(0,1fr) 156px; gap:12px; margin-top:14px; }
      .pos-tender-change {
        border-radius:14px; padding:12px 14px; min-height:132px;
        border:1px solid #b7e4c7; background:#e8f7ee;
      }
      .pos-tender-change.short { background:#fdeeee; border-color:#f5c2c2; }
      .pos-tender-change.wait { background:#f6f9fc; border-color:#e4ebf3; }
      .pos-tender-change.warn { background:#fff6e4; border-color:#f0ddb0; }
      .pos-tender-change.warn .k, .pos-tender-change.warn .amt { color:#8a5a00; }
      .pos-tender-change .k { color:#157347; }
      .pos-tender-change.short .k, .pos-tender-change.short .amt { color:#c62828; }
      .pos-tender-change.wait .k, .pos-tender-change.wait .amt { color:#66758a; }
      .pos-tender-change .amt {
        margin-top:2px; font-size:36px; line-height:1.05; font-weight:760;
        letter-spacing:-.04em; font-variant-numeric:tabular-nums; color:#157347;
      }
      .pos-tender-change.wait .amt { font-size:18px; padding-top:6px; }
      .pos-tender-hint { margin-top:4px; font-size:12px; color:#66758a; }
      .pos-tender-give { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
      .pos-tender-give span {
        display:inline-flex; align-items:center; gap:6px; background:#fff; border-radius:999px;
        padding:3px 8px 3px 4px; font-size:12px; font-weight:700; color:#16325c;
        border:1px solid rgba(21,115,71,.18);
      }
      .pos-tender-sw { width:8px; height:16px; border-radius:3px; display:inline-block; }
      .pos-tender-pad { display:grid; grid-template-columns:repeat(3,1fr); gap:6px; }
      .pos-tender-pad button {
        height:42px; border:1px solid #e4ebf3; background:#f8fafc; border-radius:10px;
        font-size:16px; font-weight:700; color:#16325c; cursor:pointer;
      }
      .pos-tender-pad button:hover { background:#e7eef8; }
      .pos-tender-pad button.ghost { color:#66758a; font-size:13px; }
      .pos-tender-note { padding:18px 8px 6px; text-align:center; color:#66758a; font-size:14px; line-height:1.5; }
      .pos-tender-note strong { display:block; color:#1b2430; font-size:16px; margin-bottom:4px; }
      .pos-tender-fine { font-size:12px; color:#66758a; margin:0 18px; }
      @media (max-width: 540px) {
        .pos-tender-work { grid-template-columns:1fr; }
        .pos-tender-pad { grid-template-columns:repeat(6,1fr); }
      }
    `;
    document.head.appendChild(st);
  }
  const walkInId = () => D.customers.find((c) => c.name === 'Walk-in Customer')?.id ?? (D.customers[0]?.id ?? '');

  /* dd Mon yyyy, e.g. "08 Oct 2026" */
  function fmtExpiryDate(dateVal) {
    const d = new Date(dateVal);
    if (isNaN(d)) return '—';
    const day = String(d.getDate()).padStart(2, '0');
    const month = d.toLocaleString('en-US', { month: 'short' });
    return `${day} ${month} ${d.getFullYear()}`;
  }

  /* 'danger' once expired, 'warning' inside 90 days, else 'success' */
  function expiryTone(dateVal, alertDays = 90) {
    const exp = new Date(dateVal);
    const today = new Date(MF.today ? MF.today() : Date.now());
    if (isNaN(exp)) return 'success';
    const days = Math.ceil((exp - today) / 86400000);
    if (days < 0) return 'danger';
    if (days <= (alertDays || 90)) return 'warning';
    return 'success';
  }

  /* Pack label (Strip, Bottle, …). subUnit / packQty describe the pieces inside it. */
  function unitLabel(m) {
    return m && m.unit ? m.unit : 'units';
  }

  /* Piece label (Tab, Capsule, …). Falls back to "Tab" when a pack holds more than one piece. */
  function pieceLabel(m) {
    if (m && m.subUnit) return m.subUnit;
    if (m && Number(m.packQty) > 1) return 'Tab';
    return unitLabel(m);
  }

  function packSize(m) {
    const n = Number(m && m.packQty);
    return n > 0 ? n : 1;
  }

  function withCount(n, label) {
    const name = String(label || 'units');
    if (Number(n) === 1) return name.replace(/s$/i, '') || name;
    if (/s$/i.test(name)) return name;
    return name + 's';
  }

  /* Strips + loose tablets already sitting in this cart for one medicine. */
  function cartUsage(medId) {
    let packs = 0, loose = 0;
    state.cart.forEach((l) => {
      if (l.medId != medId) return;
      if (l.unit === 'loose') loose += Number(l.qty) || 0;
      else packs += Number(l.qty) || 0;
    });
    return { packs, loose };
  }

  /* Sellable stock after the current cart, walked FEFO the same way a sale would.
     strips  = sealed packs still closed
     loose   = opened pieces not yet in the cart
     tablets = strips × pack size + loose  (what we show next to the expiry date) */
  function fefoState(medOrId) {
    const med = medOrId && typeof medOrId === 'object' ? medOrId : MF.med(medOrId);
    const packQty = packSize(med);
    const usage = med ? cartUsage(med.id) : { packs: 0, loose: 0 };
    const batches = (med ? MF.batchesOf(med.id) : [])
      .filter((b) => MF.daysTo(b.expiry) >= 0)
      .sort((a, b) => String(a.expiry).localeCompare(String(b.expiry)))
      .map((b) => ({
        id: b.id,
        batch: b,
        strips: Math.max(0, (Number(b.qty) || 0) - (Number(b.reserved) || 0)),
        loose: Math.max(0, Number(b.looseQty) || 0)
      }));

    let packsLeft = usage.packs;
    let looseLeft = usage.loose;
    for (const b of batches) {
      if (packsLeft <= 0) break;
      const take = Math.min(b.strips, packsLeft);
      b.strips -= take;
      packsLeft -= take;
    }
    for (const b of batches) {
      if (looseLeft <= 0) break;
      const take = Math.min(b.loose, looseLeft);
      b.loose -= take;
      looseLeft -= take;
    }
    for (const b of batches) {
      if (looseLeft <= 0) break;
      if (b.strips <= 0) continue;
      const take = Math.min(b.strips * packQty, looseLeft);
      const open = Math.ceil(take / packQty);
      b.strips -= open;
      b.loose += open * packQty - take;
      looseLeft -= take;
    }

    let strips = 0, loose = 0;
    batches.forEach((b) => { strips += b.strips; loose += b.loose; });
    return {
      med, packQty, strips, loose,
      tablets: strips * packQty + loose,
      batches,
      inCartPacks: usage.packs,
      inCartLoose: usage.loose,
      nextStrip: batches.find((b) => b.strips > 0) || null,
      nextLoose: batches.find((b) => b.loose > 0 || b.strips > 0) || null
    };
  }

  /* Extra qty this cart line can still take from its own batch (other lines on that batch already removed). */
  function lineRoom(l) {
    const med = MF.med(l.medId);
    const packQty = packSize(med);
    const raw = (D.batches || []).find((x) => x.id == l.batchId);
    if (!raw || MF.daysTo(raw.expiry) < 0) return { strips: 0, tablets: 0 };
    let strips = Math.max(0, (Number(raw.qty) || 0) - (Number(raw.reserved) || 0));
    let loose = Math.max(0, Number(raw.looseQty) || 0);
    state.cart.forEach((line) => {
      if (line === l || line.batchId != l.batchId) return;
      if (line.unit === 'loose') {
        let need = Number(line.qty) || 0;
        const take = Math.min(loose, need);
        loose -= take;
        need -= take;
        if (need > 0 && strips > 0) {
          const open = Math.min(strips, Math.ceil(need / packQty));
          strips -= open;
          loose += open * packQty - need;
          if (loose < 0) loose = 0;
        }
      } else {
        strips = Math.max(0, strips - (Number(line.qty) || 0));
      }
    });
    if (l.unit === 'loose') {
      return { strips: Math.max(0, strips), tablets: Math.max(0, strips * packQty + loose - (Number(l.qty) || 0)) };
    }
    return { strips: Math.max(0, strips - (Number(l.qty) || 0)), tablets: 0 };
  }

  /* In stock / Low stock / Out of stock — judged on strips still left after the cart. */
  function stockBadge(m, live) {
    const stock = live ? live.strips : 0;
    const tablets = live ? live.tablets : 0;
    const low = Number(m.minStock ?? m.reorderLevel ?? 10);
    const st = tablets <= 0 ? ['out', 'Out of stock'] : stock <= low ? ['low', 'Low stock'] : ['in', 'In stock'];
    return `<span class="pos-stock-badge ${st[0]}">${st[1]}</span>`;
  }

  /* "Medicine name · Brand" — brand shown muted after a centre dot */
  function nameLine(m) {
    const brand = m.brandRef ? ` <span class="text-2 fw-normal">· ${MF.esc(m.brandRef)}</span>` : '';
    return `<div class="pr-name">${MF.esc(m.name)}${brand}</div>`;
  }

  /* Batch + expiry only. Strip and tablet counts live on the stock line. */
  function batchExpiryPills(b, m) {
    if (!b) return '';
    const tone = expiryTone(b.expiry, m && m.expiryAlertDays);
    return `
      <div class="d-flex align-items-center flex-wrap gap-1 mt-1">
        <span class="badge rounded-pill bg-light text-dark" title="Batch ${MF.esc(b.batchNo)}"><i class="bi bi-upc-scan"></i> ${MF.esc(b.batchNo)}</span>
        <span class="badge rounded-pill bg-${tone}-subtle text-${tone}-emphasis" title="Next batch to sell (FEFO)">Exp : ${fmtExpiryDate(b.expiry)}</span>
      </div>`;
  }

  /* Generic/substitute linking: shown only when a medicine has no sellable batch. */
  function subsLine(m) {
    const subs = MF.substitutesOf(m.id);
    if (!subs.length) return '';
    return `<div class="pr-meta text-2 mt-1"><i class="bi bi-arrow-left-right"></i> Try instead: ${subs.map((s) => MF.esc(s.name)).join(', ')}</div>`;
  }

  /* Attach click → addToCart for BOTH search results and the "Fast moving" shortcuts */
  function bindResultClicks(box) {
    box.querySelectorAll('.pos-result:not([disabled])').forEach((btn) =>
      btn.addEventListener('click', () => addToCart(btn.dataset.med)));
    box.querySelectorAll('.pos-loose-add').forEach((btn) =>
      btn.addEventListener('click', (e) => { e.stopPropagation(); addToCart(btn.dataset.med, 'loose'); }));
    box.querySelectorAll('.pos-order-sub').forEach((btn) =>
      btn.addEventListener('click', (e) => { e.stopPropagation(); MF.toast('Order / substitute isn\'t available yet.', 'info', 'Stock'); }));
  }

  /* Left-column MRP line: shown as its own row right under the batch/expiry pills. */
  function mrpLine(m, live) {
    return `<div class="small-xs text-2 mt-1">MRP : ${MF.fmt(m.mrp, 2)}/${MF.esc(unitLabel(m))}${stockBadge(m, live)}</div>`;
  }

  /* Strip + tablet totals, both net of the cart. Hidden tablet half when a pack is a single piece. */
  function stockText(m, live) {
    const strips = `${MF.num(live.strips)} ${MF.esc(withCount(live.strips, unitLabel(m)))}`;
    if (pieceLabel(m) === unitLabel(m) && live.tablets === live.strips) return `Stock : ${strips}`;
    const tabs = `${MF.num(live.tablets)} ${MF.esc(withCount(live.tablets, pieceLabel(m)))}`;
    return `Stock : ${strips} · ${tabs}`;
  }

  /* Right-side block: live strip stock, and either the add button or a purple
     order/substitute tile (styled like the payment-method tiles) when out of stock. */
  function priceBlock(m, live) {
    const outOfStock = live.tablets <= 0;
    const heldInCart = outOfStock && (live.inCartPacks > 0 || live.inCartLoose > 0);
    const sellPrice = Number(m.retailRate ?? m.mrp);
    const mrpNote = sellPrice !== Number(m.mrp)
      ? `<div class="small-xs text-2" style="text-decoration:line-through;">MRP ${MF.fmt(m.mrp, 2)}</div>` : '';
    const action = outOfStock
      ? (heldInCart
        ? `<div class="small-xs mt-1" style="color:#a86400;font-weight:600;">All remaining in cart</div>`
        : `<button type="button" class="btn pos-order-sub mt-1" data-med="${m.id}">
           <i class="bi bi-arrow-repeat"></i>
           <span class="fw-semibold" style="font-size:.75rem;">Order / substitute</span>
         </button>`)
      : (m.allowLoose ? `<button type="button" class="btn btn-sm mt-1 pos-loose-add" data-med="${m.id}">
           <i class="bi bi-plus-circle"></i> Add ${MF.esc(m.subUnit || 'Loose')}
         </button>` : '');
    return `
        ${mrpNote}
        <div class="fw-bold num">${MF.fmt(sellPrice, 2)}</div>
        <div class="small-xs text-2 mt-1" title="Sellable strips and tablets left after this cart">${stockText(m, live)}</div>
        ${action}`;
  }

  /* One quick-pick / search card. Stock (strips and tablets) is the only quantity shown. */
  function resultCard(m) {
    const live = fefoState(m);
    // Earliest batch that still has anything to sell (loose pieces before a later sealed strip).
    const shown = live.nextLoose || live.nextStrip;
    const b = shown ? shown.batch : MF.pickBatch(m.id);
    const noPack = live.strips <= 0;
    return `
      <div class="pos-result" role="button" tabindex="0" data-med="${m.id}" ${noPack ? 'disabled' : ''}>
        <div class="kpi-icon tone-primary" style="width:38px;height:38px;flex-basis:38px;font-size:1rem"><i class="bi bi-capsule"></i></div>
        <div class="flex-grow-1 text-start">
          ${nameLine(m)}
          <div class="pr-meta">${MF.esc(m.composition)}</div>
          ${b ? batchExpiryPills(b, m) : `<div class="pr-meta text-danger mt-1">No sellable batch (expired stock only)</div>${subsLine(m)}`}
          ${mrpLine(m, live)}
        </div>
        <div class="text-end">
          ${priceBlock(m, live)}
        </div>
      </div>`;
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
    box.innerHTML = hits.map(resultCard).join('');
    bindResultClicks(box);
  }

  function emptySearch() {
    const picks = D.medicines.slice(0, 5);
    if (!picks.length) return `<div class="empty-state"><i class="bi bi-capsule"></i>No medicines yet — add some in Medicine Master.</div>`;
    return `
      <div class="sr-group-label">Quick picks</div>
      ${picks.map(resultCard).join('')}
      <p class="text-2 small mt-3 mb-0"><i class="bi bi-lightbulb me-1"></i>Search by medicine name, generic name, composition, batch no or barcode.</p>`;
  }

  /* ---------------- Cart ---------------- */
  function addToCart(medId, unit = 'pack') {
    const med = MF.med(medId);
    if (!med) return;
    const pack = unit !== 'loose';
    if (!pack && !med.allowLoose) { MF.toast('Loose sale is not enabled for ' + med.name, 'warn', 'Stock'); return; }
    const live = fefoState(med);
    if (pack && live.strips <= 0) {
      MF.toast(`No ${withCount(2, unitLabel(med))} available for ${med.name}`, 'warn', 'Stock');
      return;
    }
    if (!pack && live.tablets <= 0) {
      MF.toast(`No ${withCount(2, pieceLabel(med))} available for ${med.name}`, 'warn', 'Stock');
      return;
    }
    const slot = pack ? live.nextStrip : live.nextLoose;
    if (!slot) { MF.toast('No sellable batch available for ' + med.name, 'warn', 'Stock'); return; }
    const sell = Number(med.retailRate ?? med.mrp);
    const rate = pack ? sell : sell / packSize(med);
    const line = state.cart.find((l) => l.batchId == slot.id && (pack ? l.unit !== 'loose' : l.unit === 'loose'));
    if (line) line.qty++;
    else state.cart.push({ medId, batchId: slot.id, qty: 1, rate, mrp: med.mrp, discPct: 0, unit: pack ? 'pack' : 'loose' });
    if (med.rxRequired) MF.toast(med.name + ' is Schedule ' + med.schedule + ' — verify prescription', 'info', 'Rx item');
    renderCart();
  }

  /* "2 Strips · 20 Tabs" (or just tablets, for a loose line) — recomputed from the stepper qty. */
  function lineMeasure(l, med) {
    if (l.unit === 'loose') return `${MF.num(l.qty)} ${MF.esc(withCount(l.qty, pieceLabel(med)))}`;
    const tabs = l.qty * packSize(med);
    return `${MF.num(l.qty)} ${MF.esc(withCount(l.qty, unitLabel(med)))} · ${MF.num(tabs)} ${MF.esc(withCount(tabs, pieceLabel(med)))}`;
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
      box.innerHTML = `<div class="empty-state py-5"><i class="bi bi-cart3"></i>Cart is empty.<br><span class="small">Search a medicine on the left and click to add strips or press <strong>+ Add Tablet </strong> for loose.</span></div>`;
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
                <div class="td-sub num">B: ${b.batchNo} · Exp ${MF.fmtMonthYear(b.expiry)} · GST ${med.gst}%${l.unit === 'loose' ? ` · Loose` : ''}</div>
                <div class="td-sub num">${lineMeasure(l, med)}</div>
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
          const med = MF.med(l.medId);
          const room = lineRoom(l);
          if (a === 'inc') {
            const left = l.unit === 'loose' ? room.tablets : room.strips;
            if (left <= 0) {
              const word = l.unit === 'loose' ? withCount(l.qty, pieceLabel(med)) : withCount(l.qty, unitLabel(med));
              MF.toast(`Only ${l.qty} ${word} left in this batch`, 'warn', 'Stock');
            } else l.qty++;
          }
          if (a === 'dec') l.qty = Math.max(1, l.qty - 1);
          if (a === 'qty') {
            const extra = l.unit === 'loose' ? room.tablets : room.strips;
            l.qty = Math.max(1, Math.min(l.qty + extra, parseInt(el.value) || 1));
          }
          if (a === 'disc') l.discPct = Math.max(0, Math.min(100, parseFloat(el.value) || 0));
          if (a === 'rm') state.cart.splice(i, 1);
          renderCart(); renderSummary();
        });
      });
    }
    renderSummary();
    renderRxChip();
    refreshPicks();
  }

  /* Redraw Quick picks / search cards so strip + tablet counts follow the cart. */
  function refreshPicks() {
    const input = $('#posSearch');
    if (!input || !$('#posResults')) return;
    searchMeds(input.value || '');
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
            <button class="btn btn-sm btn-light-mf" data-load="${h.id}">Load</button>
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
  const TENDER_NOTES = [2000, 500, 200, 100, 50, 20, 10, 5, 2, 1];

  function tenderSuggestions(total) {
    const vals = new Set([total]);
    [10, 50, 100].forEach((step) => {
      const up = Math.ceil(total / step) * step;
      if (up > total && up - total <= 100) vals.add(up);
    });
    [200, 500, 2000].forEach((n) => { if (n > total) vals.add(n); });
    return [...vals].sort((a, b) => a - b).slice(0, 6);
  }

  function changeBreakdown(change) {
    let left = Math.max(0, Math.round(change));
    const out = [];
    TENDER_NOTES.forEach((n) => {
      const count = Math.floor(left / n);
      if (count > 0) { out.push([n, count]); left -= count * n; }
    });
    return out;
  }

  function noteSwatch(n) {
    return { 2000: '#c45c93', 500: '#7d8f78', 200: '#e07a3d', 100: '#7d72b8', 50: '#3f9a4a', 20: '#c4a035', 10: '#c47a45' }[n] || '#8d97a3';
  }

  /* Cash: amount received, change, and the notes to hand back.
     Other modes just confirm the amount — there is no change to calculate. */
  function openTender(t) {
    return new Promise((resolve) => {
      let host = document.getElementById('pos-tender-root');
      if (!host) {
        host = document.createElement('div');
        host.id = 'pos-tender-root';
        document.body.appendChild(host);
      }
      const due = Math.round(t.grand);
      const cash = state.payment === 'cash';
      const cust = MF.cust($('#posCustomer').value);
      const who = MF.esc(cust ? cust.name : 'Customer');
      const payLabel = { cash: 'Cash', upi: 'UPI', card: 'Card', credit: 'Credit', split: 'Split' }[state.payment] || state.payment;
      const roundSign = t.roundOff >= 0 ? '+' : '−';
      let paid = cash ? String(due) : '';
      let result = null;

      const otherCopy = {
        upi: ['Collect on UPI', `Show the customer ${MF.fmt(due)} and wait for the success screen. No change on a UPI sale.`],
        card: ['Charge the card', `Swipe or tap for ${MF.fmt(due)}. Change is not calculated for card payments.`],
        credit: ['Post to customer dues', `${MF.fmt(due)} will be added to the customer ledger.`],
        split: ['Split already balanced', `Cash ${MF.fmt(state.split.cash)} + UPI ${MF.fmt(state.split.upi)}. No change to return.`]
      }[state.payment] || ['Confirm sale', `Collect ${MF.fmt(due)}.`];

      host.innerHTML = `
        <div class="modal fade pos-tender" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered pos-tender-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title mb-0"><i class="bi bi-cash-stack me-2 text-success"></i>Complete sale</h5>
                  <div class="text-2 small">${payLabel} · ${who} · ${state.cart.length} item(s)</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="pos-tender-collect">
                  <div>
                    <div class="pos-tender-k">Amount to collect</div>
                    <div class="pos-tender-due">${MF.fmt(due)}</div>
                  </div>
                  <div class="pos-tender-meta">Subtotal ${MF.fmt(t.subtotal, 2)}<br>Round off ${roundSign} ${MF.fmt(Math.abs(t.roundOff), 2)}</div>
                </div>
                ${cash ? `
                <div class="pos-tender-label" id="posTenderPaidLabel">Cash received</div>
                <div class="pos-tender-money">
                  <span>₹</span>
                  <input id="posTenderPaid" inputmode="numeric" autocomplete="off" aria-labelledby="posTenderPaidLabel" value="${paid}">
                </div>
                <div class="pos-tender-chips" id="posTenderChips"></div>
                <div class="pos-tender-work">
                  <div id="posTenderChange"></div>
                  <div class="pos-tender-pad" aria-label="Keypad">
                    ${[1, 2, 3, 4, 5, 6, 7, 8, 9].map((n) => `<button type="button" data-key="${n}">${n}</button>`).join('')}
                    <button type="button" class="ghost" data-key="c">Clear</button>
                    <button type="button" data-key="0">0</button>
                    <button type="button" class="ghost" data-key="b" aria-label="Backspace">⌫</button>
                  </div>
                </div>` : `
                <div class="pos-tender-note"><strong>${MF.esc(otherCopy[0])}</strong>${MF.esc(otherCopy[1])}</div>`}
              </div>
              <p class="pos-tender-fine">Stock will be deducted from the earliest expiry batch.</p>
              <div class="modal-footer">
                <button type="button" class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-mf" id="posTenderOk">Complete sale</button>
              </div>
            </div>
          </div>
        </div>`;

      const modalEl = host.querySelector('.modal');
      const modal = new bootstrap.Modal(modalEl);
      state.tenderOpen = true;

      function refresh() {
        if (!cash) return;
        const input = host.querySelector('#posTenderPaid');
        const box = host.querySelector('#posTenderChange');
        const ok = host.querySelector('#posTenderOk');
        const amount = paid === '' ? null : (parseInt(paid, 10) || 0);
        host.querySelectorAll('.pos-tender-chip').forEach((chip) => {
          chip.classList.toggle('on', amount !== null && amount === +chip.dataset.set);
        });
        if (amount === null) {
          box.innerHTML = `<div class="pos-tender-change wait"><div class="k">Change to return</div><div class="amt">Enter cash received</div></div>`;
          ok.disabled = true;
          ok.textContent = 'Complete sale';
        } else if (amount < due) {
          box.innerHTML = `<div class="pos-tender-change short"><div class="k">Still short</div><div class="amt">${MF.fmt(due - amount)}</div><div class="pos-tender-hint">Complete stays off until the bill is covered.</div></div>`;
          ok.disabled = true;
          ok.textContent = `Need ${MF.fmt(due - amount)}`;
        } else if (amount === due) {
          box.innerHTML = `<div class="pos-tender-change"><div class="k">Change to return</div><div class="amt">${MF.fmt(0)}</div><div class="pos-tender-hint">Exact amount. Nothing to hand back.</div></div>`;
          ok.disabled = false;
          ok.textContent = 'Complete sale';
        } else {
          const change = amount - due;
          const notes = changeBreakdown(change).map(([n, c]) =>
            `<span><i class="pos-tender-sw" style="background:${noteSwatch(n)}"></i>${MF.fmt(n)} × ${c}</span>`).join('');
          const warn = change > 5000;
          box.innerHTML = `
            <div class="pos-tender-change${warn ? ' warn' : ''}">
              <div class="k">${warn ? 'Large change — recheck' : 'Change to return'}</div>
              <div class="amt">${MF.fmt(change)}</div>
              <div class="pos-tender-hint">Give back</div>
              <div class="pos-tender-give">${notes}</div>
            </div>`;
          ok.disabled = false;
          ok.textContent = 'Complete sale';
        }
        if (input && input.value !== paid) input.value = paid;
      }

      function paintChips() {
        const wrap = host.querySelector('#posTenderChips');
        if (!wrap) return;
        wrap.innerHTML = tenderSuggestions(due).map((n) => {
          const label = n === due ? `Exact ${MF.fmt(n)}` : MF.fmt(n);
          return `<button type="button" class="pos-tender-chip" data-set="${n}">${label}</button>`;
        }).join('');
        wrap.querySelectorAll('.pos-tender-chip').forEach((chip) => {
          chip.addEventListener('click', () => {
            paid = chip.dataset.set;
            const input = host.querySelector('#posTenderPaid');
            if (input) { input.value = paid; input.focus(); input.select(); }
            refresh();
          });
        });
      }

      paintChips();
      refresh();

      host.querySelector('#posTenderPaid')?.addEventListener('input', (e) => {
        paid = e.target.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 6);
        if (e.target.value !== paid) e.target.value = paid;
        refresh();
      });
      host.querySelectorAll('[data-key]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const input = host.querySelector('#posTenderPaid');
          const all = input && input.selectionStart === 0 && input.selectionEnd === input.value.length && input.value.length > 0;
          const key = btn.dataset.key;
          if (key === 'c') paid = '';
          else if (key === 'b') paid = paid.slice(0, -1);
          else {
            const base = all ? '' : paid;
            paid = (base + key).replace(/^0+(?=\d)/, '').slice(0, 6);
          }
          if (input) {
            input.value = paid;
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
          }
          refresh();
        });
      });
      host.querySelector('#posTenderOk').addEventListener('click', () => {
        const ok = host.querySelector('#posTenderOk');
        if (ok.disabled) return;
        if (cash) {
          const received = parseInt(paid, 10) || 0;
          if (paid === '' || received < due) return;
          result = { cashReceived: received, changeReturned: received - due };
        } else {
          result = { cashReceived: 0, changeReturned: 0 };
        }
        modal.hide();
      });
      modalEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          host.querySelector('#posTenderOk')?.click();
        }
      });
      modalEl.addEventListener('shown.bs.modal', () => {
        const input = host.querySelector('#posTenderPaid');
        if (input) { input.focus(); input.select(); }
      }, { once: true });
      modalEl.addEventListener('hidden.bs.modal', () => {
        state.tenderOpen = false;
        resolve(result);
      }, { once: true });
      modal.show();
    });
  }

  function receiptHtml(invNo, t) {
    const cust = MF.cust($('#posCustomer').value);
    const payLabel = { cash: 'Cash', upi: 'UPI', card: 'Card', credit: 'Credit', split: `Split (Cash ${MF.fmt(state.split.cash)} + UPI ${MF.fmt(state.split.upi)})` }[state.payment];
    const tenderRows = state.payment === 'cash' && state.tender
      ? `<div class="sum-row"><span class="text-2">Cash paid</span><span class="num">${MF.fmt(state.tender.cashReceived)}</span></div>
         <div class="sum-row"><span class="text-2">Change</span><span class="num">${MF.fmt(state.tender.changeReturned)}</span></div>`
      : '';
    return `
      <div class="text-center mb-3">
        <img src="assets/images/logo.svg" width="42" alt="">
        <h6 class="fw-bold mt-2 mb-0">${MF.esc(D.store.name)}</h6>
        ${D.store.address ? `<div class="text-2 small-xs">${MF.esc(D.store.address)}${D.store.gstin ? ' · GSTIN ' + D.store.gstin : ''}</div>` : ''}
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
        ${tenderRows}
      </div>
      <p class="text-center text-2 small-xs mt-3 mb-0">Medicines once sold will not be taken back without valid reason · Get well soon!</p>`;
  }

  async function completeSale() {
    if (state.tenderOpen) { document.getElementById('posTenderOk')?.click(); return; }
    if (!state.cart.length) { MF.toast('Cart is empty', 'warn', 'Cannot complete sale'); return; }
    const t = calcTotals();
    const tender = await openTender(t);
    if (!tender) return;
    state.tender = tender;

    $('#posComplete').disabled = true;
    try {
      const res = await MF.Api.post('sales.php', {
        customerId: $('#posCustomer').value,
        paymentMode: state.payment,
        globalDiscPct: parseFloat($('#posGlobalDisc').value) || 0,
        splitCash: state.split.cash,
        splitUpi: state.split.upi,
        cashReceived: tender.cashReceived,
        changeReturned: tender.changeReturned,
        items: state.cart.map((l) => ({ medId: l.medId, batchId: l.batchId, qty: l.qty, rate: l.rate, discPct: l.discPct, unit: l.unit || 'pack' })),
      });
      MF.printHtml(receiptHtml(res.invoiceNo, t));
      MF.toast(`${res.invoiceNo} · ${MF.fmt(res.grandTotal)} · ${state.payment.toUpperCase()}`, 'success', 'Sale completed');
      if (res.balanceDue > 0) MF.toast(`${MF.fmt(res.balanceDue)} added to customer dues`, 'info', 'Credit sale');
      state.cart = [];
      state.tender = null;
      await MF.rehydrate(); // refresh D.batches so stock levels are current
      $('#posCustomer').value = walkInId();
      renderCart();
      searchMeds($('#posSearch').value); // redraw Quick picks / search cards with the new stock
    } catch (err) {
      state.tender = null;
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

    document.addEventListener('keydown', (e) => {
      if (e.key === 'F2') { e.preventDefault(); $('#posSearch').focus(); }
      else if (e.key === 'F3') { e.preventDefault(); $('#posPayCash').checked = true; $('#posPayCash').dispatchEvent(new Event('change')); }
      else if (e.key === 'F4') { e.preventDefault(); $('#posPayUpi').checked = true; $('#posPayUpi').dispatchEvent(new Event('change')); }
      else if (e.key === 'F10') { e.preventDefault(); completeSale(); }
    });
  });
})();
