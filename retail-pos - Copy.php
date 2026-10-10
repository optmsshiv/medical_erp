<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php'; // redirects to /login.php if not logged in

$client = Tenant::current();
$user   = Auth::user();
$cashier = trim((string)($user['name'] ?? ''));
if ($cashier === '' && !empty($user['email'])) $cashier = preg_replace('/@.*$/', '', (string)$user['email']);
if ($cashier === '') $cashier = 'Cashier';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Retail POS · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .pos-held {
      position:relative; border:1px solid #b7ddd4; background:#fff; color:var(--mf-primary-dark);
      border-radius:999px; font-weight:700; font-size:.8rem; padding:6px 12px;
      display:inline-flex; align-items:center; gap:6px;
      transition:background .15s ease, color .15s ease, border-color .15s ease, transform .15s ease, box-shadow .15s ease;
    }
    .pos-held:hover { background:var(--mf-primary-soft); border-color:var(--mf-primary); color:var(--mf-primary-dark); transform:translateY(-1px); box-shadow:0 4px 12px rgba(23,107,91,.16); }
    .pos-held:active { transform:translateY(0); box-shadow:none; }
    .pos-held-count {
      min-width:18px; height:18px; border-radius:999px; background:var(--mf-danger); color:#fff;
      font-size:.68rem; font-weight:750; display:inline-flex; align-items:center; justify-content:center; padding:0 5px;
    }
    .pos-pick-tabs { display:flex; gap:6px; flex-wrap:wrap; margin:2px 0 10px; }
    .pos-pick-tab {
      border:1px solid #d7ebe6; background:#fff; color:#516278; border-radius:999px;
      font-size:.75rem; font-weight:700; padding:5px 12px; cursor:pointer;
      transition:background .15s ease, color .15s ease, border-color .15s ease;
    }
    .pos-pick-tab.is-on { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .pos-pick-tab:hover { border-color:var(--mf-primary); color:var(--mf-primary-dark); }
    .pos-sub-for { font-size:.68rem; font-weight:700; color:#6D28D9; margin:8px 0 2px; }
    .pos-rx-verify {
      display:none; align-items:center; justify-content:space-between; gap:12px;
      margin-top:10px; padding:8px 12px; border-radius:9px; background:#f7f4ff; border:1px solid #e6defa;
      transition:border-color .15s ease, background .15s ease;
    }
    .pos-rx-verify.show { display:flex; }
    .pos-rx-verify:hover { border-color:#c4b5fd; background:#f3edff; }
    .pos-rx-verify .rx-chip { font-size:.78rem; padding:.35rem .7rem; }
    .pos-switch { position:relative; width:42px; height:24px; flex:0 0 42px; margin:0; }
    .pos-switch input { position:absolute; opacity:0; width:0; height:0; }
    .pos-switch span {
      position:absolute; inset:0; background:#ddd6fe; border-radius:999px; cursor:pointer;
      transition:background .15s ease;
    }
    .pos-switch span:before {
      content:""; position:absolute; width:18px; height:18px; left:3px; top:3px; border-radius:50%;
      background:#fff; box-shadow:0 1px 3px rgba(76,29,149,.2); transition:transform .15s ease;
    }
    .pos-switch input:checked + span { background:#7c3aed; }
    .pos-switch input:checked + span:before { transform:translateX(18px); }
    .pos-switch:hover span { background:#c4b5fd; }
    .pos-switch:hover input:checked + span { background:#6d28d9; }
    .pos-rx-panel { margin-top:8px; padding:10px 12px; border:1px solid #d7ebe6; border-radius:12px; background:#f7fbfa; }
    .pos-rx-meta { margin-top:8px; color:#516278; font-size:.82rem; line-height:1.45; }
    .pos-rx-meta strong { color:#1b2430; }

    /* Complete Sale — full-width, sticky at the bottom of the invoice card */
    .pos-complete-bar {
      position:sticky; bottom:0; z-index:30; margin:14px -16px -16px; padding:10px 16px 14px;
      background:rgba(255,255,255,.94); backdrop-filter:blur(6px); border-top:1px solid #E4EBF4;
    }
    .pos-complete-btn {
      width:100%; display:flex; align-items:center; justify-content:center; gap:8px;
      font-size:1.02rem; font-weight:700; padding:12px 16px; border-radius:12px;
      box-shadow:0 8px 20px -8px rgba(23,107,91,.45);
      transition:transform .12s ease, box-shadow .15s ease, filter .15s ease;
    }
    .pos-complete-btn:hover { transform:translateY(-1px); box-shadow:0 12px 26px -10px rgba(23,107,91,.5); filter:brightness(1.04); }
    .pos-complete-btn:active { transform:translateY(0); box-shadow:none; }
    .pos-complete-btn:disabled { opacity:.6; }
    .pos-complete-amt { font-size:1.12rem; font-weight:800; font-variant-numeric:tabular-nums; }

    /* Bill-level discount — % | ₹ segmented toggle (teal active cell) */
    .pos-disc-input { max-width:110px; flex:0 0 auto; }
    .pos-disc-toggle { display:inline-flex; border:1.5px solid #cfd9e4; border-radius:9px; overflow:hidden; flex:0 0 auto; background:#fff; }
    .pos-disc-opt { width:36px; height:36px; border:0; background:#fff; color:#334155; font-weight:800; font-size:.95rem; cursor:pointer; transition:background .12s ease, color .12s ease; }
    .pos-disc-opt + .pos-disc-opt { border-left:1.5px solid #cfd9e4; }
    .pos-disc-opt:hover { background:#eef4f3; }
    .pos-disc-opt.is-on { background:#176B5B; color:#fff; }

    /* Order pad chip pulses softly while an un-ordered re-order is waiting */
    .pos-orderchip.has-pending { border-color:#0d9488; animation:posOrderPulse 2.2s ease-in-out infinite; }
    @keyframes posOrderPulse { 0%,100% { box-shadow:0 0 0 0 rgba(13,148,136,.35); } 50% { box-shadow:0 0 0 6px rgba(13,148,136,0); } }

    /* Bill context strip — lives in the page head, right side, outside the Current Invoice panel */
    .pos-meta-bar { display:flex; flex-wrap:wrap; align-items:center; gap:6px 14px; padding:6px 10px; margin:0; max-width:100%;
                    border:1px solid #e3e9f0; background:#fff; border-radius:9px; font-size:.74rem; color:#64748B;
                    box-shadow:0 1px 2px rgba(15,23,42,.05); }
    .pos-meta-bar strong { color:#1f2a37; font-weight:700; }
    .pos-meta-bar .num { font-variant-numeric:tabular-nums; }
    .pos-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#16A34A; margin-right:4px; vertical-align:1px; }
    .pos-meta-bar.is-off { border-color:#f2c8c8; background:#fdf5f5; }
    .pos-meta-bar.is-off .pos-dot { background:#DC2626; }

    /* Action buttons — shortcut shown on a second line */
    .pos-act { display:inline-flex; flex-direction:column; align-items:center; justify-content:center; gap:3px;
               padding:4px 12px; line-height:1.2; }
    .pos-act-key { font-size:.6rem; font-weight:700; letter-spacing:.04em; color:#5b6b7b;
                   border:1px solid #d9e1e9; background:#f4f6f8; border-radius:5px; padding:0 6px; }
    .btn-success .pos-act-key { color:#fff; background:rgba(255,255,255,.18); border-color:rgba(255,255,255,.4); }

    /* Print split button — caret stays compact and joins the main button as one control */
    .pos-print-group { display:inline-flex; align-items:stretch; }
    .pos-print-caret { min-width:36px; padding:4px 8px !important; display:inline-flex; align-items:center; justify-content:center; }
    .pos-print-caret::after { margin-left:0; }
    .pos-print-menu { min-width:190px; border-radius:9px; }
    .pos-print-menu .dropdown-header { font-size:.62rem; letter-spacing:.06em; }
    .pos-print-menu .dropdown-item { display:flex; align-items:center; }
    .pos-print-menu .dropdown-item .pos-act-key { margin-left:auto; }

    /* Action buttons — wraps to two rows on narrow counters; NO horizontal scroll so the print dropdown never clips */
    .pos-act-row { flex-wrap:wrap; }
    .pos-act-row > * { flex:0 0 auto; }

    /* Order pad chip (re-order list) beside the search label */
    .pos-orderchip {
      border:1px solid #b7ddd4; background:#fff; color:var(--mf-primary-dark); border-radius:999px;
      font-weight:700; font-size:.72rem; padding:3px 10px; display:inline-flex; align-items:center; gap:5px;
      transition:background .15s ease, border-color .15s ease, transform .15s ease;
    }
    .pos-orderchip:hover { background:var(--mf-primary-soft); border-color:var(--mf-primary); transform:translateY(-1px); }
    .pos-orderchip-cnt {
      min-width:17px; height:17px; border-radius:999px; background:var(--mf-primary); color:#fff;
      font-size:.66rem; font-weight:800; display:inline-flex; align-items:center; justify-content:center; padding:0 4px;
    }

    /* Searchable picker — wraps the raw <select> (kept as the data source, hidden). */
    .pos-lookup { position:relative; flex:1; min-width:0; }
    .pos-lookup-input { width:100%; height:38px; font-size:13px; padding:.375rem .75rem; padding-right:1.9rem;
      border:1px solid #d7e0ea; border-radius:.5rem; background:#fff; color:#1b2430; font-weight:600; }
    .pos-lookup-input:focus { border-color:#2E8B78; box-shadow:0 0 0 3px rgba(46,139,120,.12); outline:0; }
    .pos-lookup-caret { position:absolute; right:9px; top:50%; transform:translateY(-50%); color:#8b9bb0; font-size:.8rem; pointer-events:none; }
    .pos-lookup-menu { position:absolute; top:calc(100% + 4px); left:0; right:0; z-index:1080; max-height:264px; overflow:auto;
      background:#fff; border:1px solid #e3ebf4; border-radius:10px; box-shadow:0 14px 36px rgba(16,32,64,.16); padding:5px; }
    .pos-lookup-opt { display:flex; flex-direction:column; align-items:stretch; gap:1px; width:100%; text-align:left;
      border:0; background:#fff; border-radius:8px; padding:7px 10px; cursor:pointer; }
    .pos-lookup-opt:hover, .pos-lookup-opt.is-hot { background:#f2f7f6; }
    .pos-lookup-opt .nm { font-size:.86rem; font-weight:650; color:#1b2430; }
    .pos-lookup-opt .ph { font-size:.7rem; color:#8b9bb0; font-variant-numeric:tabular-nums; }
    .pos-lookup-rec { font-size:.62rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#8b9bb0; padding:5px 10px 3px; }
    .pos-lookup-add { display:flex; align-items:center; gap:8px; width:100%; border:0; background:transparent;
      color:#176B5B; font-weight:700; font-size:.82rem; border-radius:8px; padding:8px 10px; border-top:1px solid #e3ebf4; }
    .pos-lookup-add:hover { background:#eef6f4; }
    .pos-lookup-empty { color:#8b9bb0; font-size:.78rem; text-align:center; padding:10px 6px; }
    select.pos-lookup-ghost { position:absolute !important; width:1px !important; height:1px !important; opacity:0 !important; pointer-events:none !important; }

    /* Last-bill reprint chip in the meta bar */
    .pos-lastbill { border:1px solid #d7ebe6; background:#fff; color:#176B5B; border-radius:999px;
      font-size:.72rem; font-weight:750; padding:2px 10px; display:none; align-items:center; gap:6px; }
    .pos-lastbill.show { display:inline-flex; }
    .pos-lastbill:hover { background:var(--mf-primary-soft); }
    /* Notify chips — exact mf-icon-btn geometry (38×38) so they sit flush beside the
       top-bar actions; count lives in a corner badge; hover tint matches each border;
       .is-idle dims at zero (badge hides); .is-notify = count went up → tinted ring. */
    .pos-notif-chip { width:38px; height:38px; display:none; align-items:center; justify-content:center; position:relative;
      border-radius:var(--mf-radius-sm, 10px); font-size:1.05rem; background:#fff; cursor:pointer;
      transition:background .15s, border-color .15s, opacity .15s; }
    .pos-notif-chip.show { display:inline-flex; }
    .pos-notif-chip.scan { border:1px solid #c9d8f5; color:#23408e; --glowc:#23408e; --glowc-soft:rgba(35,64,142,.5); --glowc-fade:rgba(35,64,142,0); }
    .pos-notif-chip.scan:hover { background:#eaeffb; border-color:#b9cdf3; }
    .pos-notif-chip.pend { border:1px solid #f2ddc2; color:#92600a; --glowc:#B45309; --glowc-soft:rgba(180,83,9,.5); --glowc-fade:rgba(180,83,9,0); }
    .pos-notif-chip.pend:hover { background:#fdf3e6; border-color:#eccfa6; }
    .pos-notif-chip.is-idle { opacity:.5; }
    .pos-notif-chip.is-notify { animation:posNotifyPulse .8s ease-in-out 3; border-color:var(--glowc); }
    .chip-badge { position:absolute; top:-6px; right:-6px; min-width:16px; height:16px; padding:0 4px; border-radius:999px;
      background:var(--glowc); color:#fff; font-size:.62rem; font-weight:800; line-height:1; font-variant-numeric:tabular-nums;
      display:inline-flex; align-items:center; justify-content:center; border:2px solid #fff; }
    .pos-notif-chip.is-idle .chip-badge { display:none; }
    @keyframes posNotifyPulse {
      0%   { box-shadow:0 0 0 0 var(--glowc-soft, rgba(23,107,91,.55)); transform:scale(1); }
      45%  { box-shadow:0 0 0 7px var(--glowc-fade, rgba(23,107,91,0)); transform:scale(1.06); }
      100% { box-shadow:0 0 0 0 var(--glowc-fade, rgba(23,107,91,0)); transform:scale(1); }
    }
    .pos-reprint-menu { position:absolute; top:100%; right:0; margin-top:8px; z-index:1085; min-width:190px;
      background:#fff; border:1px solid #e3ebf4; border-radius:10px; box-shadow:0 14px 36px rgba(16,32,64,.16); padding:6px; }
    .pos-reprint-menu button { display:flex; align-items:center; gap:9px; width:100%; border:0; background:#fff;
      font-size:.82rem; font-weight:650; border-radius:7px; padding:7px 10px; }
    .pos-reprint-menu button:hover { background:#f4f7fb; }

    /* Qty-first entry flag + cart keyboard hint */
    .pos-qtyflag { position:absolute; right:34px; top:50%; transform:translateY(-50%); z-index:5;
      background:#176B5B; color:#fff; border-radius:7px; font-size:.72rem; font-weight:800; padding:2px 8px; pointer-events:none; }
    .pos-kb-hint { color:#8b9bb0; font-size:.7rem; margin-top:6px; }
    .pos-kb-hint b { color:#516278; }
    #posCartBody tr.is-kb td { background:#eef6f4 !important; box-shadow:inset 3px 0 0 #176B5B; }

    /* Rx nudge — scheduled item in cart: the fields that need attention glow purple
       until both are resolved (doctor picked, patient selected). 3 soft breaths. */
    @keyframes posRxPulse {
      0%, 100% { box-shadow:0 0 0 0 rgba(124,58,237,0); }
      50% { box-shadow:0 0 0 7px rgba(124,58,237,.18); }
    }
    .pos-lookup.is-rx .pos-lookup-input { border-color:#7c3aed; animation:posRxPulse 1.4s ease-in-out 3; }
    .pos-lookup.is-rx .pos-lookup-caret { color:#7c3aed; }
    .pos-lookup.is-rx::before {
      content:"Rx"; position:absolute; right:-6px; top:-9px; z-index:6;
      background:#7c3aed; color:#fff; font-size:.6rem; font-weight:800; letter-spacing:.05em;
      padding:1px 7px 2px; border-radius:999px; box-shadow:0 2px 6px rgba(76,29,149,.3);
    }
    /* Red = sale paused here. Distinct from the purple add-time guidance. */
    @keyframes posRxFlagPulse {
      0%, 100% { box-shadow:0 0 0 0 rgba(180,35,24,0); }
      50% { box-shadow:0 0 0 7px rgba(180,35,24,.2); }
    }
    .pos-lookup.is-rx-flag .pos-lookup-input { border-color:#B42318; background:#fff6f5; animation:posRxFlagPulse 1.4s ease-in-out 3; }
    .pos-lookup.is-rx-flag .pos-lookup-caret { color:#B42318; }
    .pos-lookup.is-rx-flag::before {
      content:"Rx !"; position:absolute; right:-6px; top:-9px; z-index:6;
      background:#B42318; color:#fff; font-size:.6rem; font-weight:800; letter-spacing:.05em;
      padding:1px 7px 2px; border-radius:999px; box-shadow:0 2px 6px rgba(180,35,24,.35);
    }
  </style>
</head>
<body data-page="retail-pos">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-cart3 me-2 text-success"></i>Retail POS</h1>
            <p class="page-sub">Counter billing · FEFO batch picking · GST-inclusive MRP pricing</p>
          </div>
          <div class="ms-auto pos-meta-bar" id="posMetaBar" aria-label="Bill context">
            <span class="pos-meta-item">Invoice <strong class="num" id="posMetaInv">— auto —</strong></span>
            <span class="pos-meta-item"><strong id="posMetaDate">—</strong> · <strong id="posMetaTime">—</strong></span>
            <span class="pos-meta-item">Cashier <strong><?= htmlspecialchars($cashier) ?></strong></span>
            <span class="pos-meta-item">Counter <strong id="posMetaCounter" class="num">1</strong></span>
            <span class="pos-meta-item" id="posMetaNet" title="Connection status"><span class="pos-dot"></span><strong id="posMetaNetTxt">Online</strong></span>
            <span style="position:relative">
              <button type="button" class="pos-lastbill" id="posLastBill" title="Reprint the bill just completed — for paper jams"><i class="bi bi-printer"></i><span id="posLastBillTxt"></span></button>
              <div class="pos-reprint-menu" id="posReprintMenu" hidden>
                <button type="button" id="posReprintThermal"><i class="bi bi-receipt"></i>Reprint · Thermal <span data-thermal-mm>80mm</span></button>
                <button type="button" id="posReprintA4"><i class="bi bi-file-earmark-ruled"></i>Reprint · A4</button>
                <button type="button" id="posReprintMm" title="Switch thermal paper width"><i class="bi bi-arrow-left-right"></i>Switch thermal to <span data-thermal-other>58mm</span></button>
              </div>
            </span>
            <button type="button" class="pos-notif-chip pend" id="posRxPend" title="Prescription-controlled sales still waiting for their Rx to be captured"><i class="bi bi-file-medical"></i><span class="chip-badge" id="posRxPendTxt">0</span></button>
            <button type="button" class="pos-notif-chip scan" id="posScanRx" title="Prescription photos customers sent by phone (Scan & Send Rx inbox)"><i class="bi bi-qr-code"></i><span class="chip-badge" id="posScanRxTxt">0</span></button>
          </div>
        </div>

        <div class="pos-grid">
          <!-- LEFT: search -->
          <div class="card-mf p-3">
            <label class="form-label d-flex justify-content-between" for="posSearch">
               <span>Search medicine</span>
               <span class="d-inline-flex align-items-center gap-1">
                 <button type="button" class="pos-orderchip" id="posOrderChip" title="Re-order pad — items queued for the next purchase">
                   <i class="bi bi-cart-plus"></i>Order <span class="pos-orderchip-cnt" id="posOrderCount">0</span>
                 </button>
                 <span class="badge bg-light text-dark border">F2</span>
               </span>
            </label>
            <div class="d-flex gap-2 mb-2">
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <div class="pos-search-wrap">
                  <input id="posSearch" class="form-control" placeholder="Medicine name, barcode or batch…" autocomplete="off" autofocus>
                  <button type="button" class="pos-clear" id="posSearchClear" title="Clear search" aria-label="Clear search" hidden><i class="bi bi-x-lg"></i></button>
                </div>
              </div>
              <button type="button" class="pos-refill-btn" id="posRefillBtn" title="Refill a regular customer's previous prescription in one click">
                <i class="bi bi-arrow-repeat"></i><span>Quick Refill</span>
              </button>
            </div>
            <div class="pos-pick-tabs" id="posPickTabs">
              <button type="button" class="pos-pick-tab is-on" data-pick="quick">Quick picks</button>
              <button type="button" class="pos-pick-tab" data-pick="recent">Recent</button>
              <button type="button" class="pos-pick-tab" data-pick="subs">Substitutes</button>
            </div>
            <div id="posResults"></div>
          </div>

          <!-- RIGHT: current invoice -->
          <div class="card-mf">
            <div class="card-head">
              <h2 class="card-title"><i class="bi bi-receipt"></i>Current Invoice</h2>
              <div class="card-tools">
                <button class="pos-held" id="posHeldChip" type="button">
                  <i class="bi bi-hourglass-split"></i>Held Bills
                  <span class="pos-held-count" id="posHeldBadge" style="display:none">0</span>
                </button>
              </div>
            </div>

            <div class="p-3">
              <div class="row g-2 mb-2">
                <div class="col-md-6">
                  <label class="form-label" for="posCustomer">Customer</label>
                  <div class="d-flex gap-2">
                    <select class="form-select" id="posCustomer"></select>
                    <button class="btn btn-light-mf" type="button" id="posAddCustomer" title="Add new customer"><i class="bi bi-plus-lg"></i></button>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="posDoctor">Prescribing Doctor</label>
                  <div class="d-flex gap-2">
                    <select class="form-select" id="posDoctor">
                      <option value="">— Walk-in / none —</option>
                    </select>
                    <button class="btn btn-light-mf" type="button" id="posAddDoctor" title="Add new doctor"><i class="bi bi-plus-lg"></i></button>
                  </div>
                </div>
              </div>
              <div class="pos-rx-verify" id="posRxToggleWrap">
                <span class="rx-chip" id="posRxChip"><i class="bi bi-file-medical"></i>Rx verification needed</span>
                <label class="pos-switch" for="posRxOn" title="Attach the prescription">
                  <input id="posRxOn" type="checkbox">
                  <span></span>
                </label>
              </div>
              <div class="pos-rx-panel" id="posRxPanel" hidden>
                <label class="form-label" for="posRx">Prescription</label>
                <select class="form-select" id="posRx">
                  <option value="">— select prescription —</option>
                </select>
                <div class="pos-rx-empty" id="posRxEmpty" hidden style="margin-top:8px;padding:8px 10px;border-radius:8px;background:#fff8ed;border:1px solid #f2ddc2;color:#92600a;font-size:.78rem;line-height:1.45">
                  <i class="bi bi-info-circle me-1"></i>No recorded prescription for this customer — the sale still goes through: pick the doctor above and bill normally (paper Rx). It will be queued for later capture.
                </div>
                <div class="pos-rx-meta" id="posRxMeta">Turn the switch on to attach the recorded prescription for this bill.</div>
              </div>

              <div id="posCartBody"></div>
              <div class="pos-kb-hint" id="posKbHint" hidden>Keyboard: <b>↑ ↓</b> pick a line · <b>+ −</b> qty · <b>Del</b> remove · <b>Esc</b> back to search</div>

              <div class="row g-2 align-items-end mt-2">
                <div class="col-6">
                  <label class="form-label">Bill-level discount</label>
                  <div class="d-flex gap-2 align-items-center">
                    <input type="number" min="0" class="form-control pos-disc-input" id="posGlobalDisc" value="0" placeholder="0">
                    <div class="pos-disc-toggle" role="group" aria-label="Discount type">
                      <button type="button" class="pos-disc-opt is-on" id="posDiscPct" title="Percent (%)">%</button>
                      <button type="button" class="pos-disc-opt" id="posDiscRs" title="Rupees (₹)">₹</button>
                    </div>
                  </div>
                </div>
                <div class="col-6"><div id="posSummary"></div></div>
              </div>

              <div class="sr-group-label mt-3">Payment</div>
              <div class="row g-2 row-cols-5 mb-3">
                <div class="col pay-opt"><input type="radio" name="posPay" id="posPayCash" value="cash" checked><label for="posPayCash"><i class="bi bi-cash"></i>Cash <small class="d-block text-muted">F3</small></label></div>
                <div class="col pay-opt"><input type="radio" name="posPay" id="posPayUpi" value="upi"><label for="posPayUpi"><i class="bi bi-qr-code-scan"></i>UPI <small class="d-block text-muted">F4</small></label></div>
                <div class="col pay-opt"><input type="radio" name="posPay" id="posPayCard" value="card"><label for="posPayCard"><i class="bi bi-credit-card"></i>Card <small class="d-block text-muted">F5</small></label></div>
                <div class="col pay-opt"><input type="radio" name="posPay" id="posPayCredit" value="credit"><label for="posPayCredit"><i class="bi bi-journal-text"></i>Credit <small class="d-block text-muted">F6</small></label></div>
                <div class="col pay-opt"><input type="radio" name="posPay" id="posPaySplit" value="split"><label for="posPaySplit"><i class="bi bi-diagram-3"></i>Split <small class="d-block text-muted">F7</small></label></div>
              </div>

              <div class="d-flex gap-2 pos-act-row">
                <button class="btn btn-light-mf pos-act" id="posHold" type="button"><span><i class="bi bi-hourglass-split me-1"></i>Hold Bill</span><span class="pos-act-key">F8</span></button>
                <button class="btn btn-light-mf pos-act" id="posDraft" type="button"><span><i class="bi bi-save me-1"></i>Save Draft</span><span class="pos-act-key">F9</span></button>
                <div class="btn-group pos-print-group">
                  <button class="btn btn-light-mf pos-act" id="posPrint" type="button"><span><i class="bi bi-printer me-1"></i>Print · <span id="posPrintLbl">A4</span></span><span class="pos-act-key">Ctrl+P</span></button>
                  <button class="btn btn-light-mf dropdown-toggle pos-print-caret" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Print options" title="Print options — Thermal 80mm / A4 / Complete & Print"></button>
                  <ul class="dropdown-menu dropdown-menu-end pos-print-menu">
                    <li><h6 class="dropdown-header small-xs text-uppercase">Print invoice</h6></li>
                    <li><button class="dropdown-item" id="posPrintThermal" type="button"><i class="bi bi-receipt me-2"></i>Thermal 80mm</button></li>
                    <li><button class="dropdown-item" id="posPrintA4" type="button"><i class="bi bi-file-earmark-ruled me-2"></i>A4</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item" id="posPrintComplete" type="button"><i class="bi bi-check2-circle me-2"></i>Complete &amp; Print <span class="pos-act-key ms-1">F10</span></button></li>
                  </ul>
                </div>
                <button class="btn btn-light-mf text-danger ms-auto pos-act" id="posClearCart" type="button"><span><i class="bi bi-trash3 me-1"></i>Clear</span><span class="pos-act-key">Alt+C</span></button>
              </div>

              <div class="pos-complete-bar">
                <button class="btn btn-mf pos-complete-btn" id="posComplete" type="button">
                  <i class="bi bi-check2-circle"></i>
                  <span>Complete Sale ·</span>
                  <span class="pos-complete-amt" id="posCompleteAmt">₹0</span>
                  <span class="badge bg-white text-success">F10</span>
                </button>
              </div>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Order / substitute chooser -->
  <div class="modal fade" id="posActionSheet" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-mf-sm">
      <div class="modal-content">
        <div class="modal-body p-3">
          <h6 class="fw-bold mb-0" id="posSheetTitle"></h6>
          <div class="text-2 small mb-3" id="posSheetStock"></div>
          <div class="d-grid gap-2">
            <button type="button" class="btn btn-mf-soft text-start d-flex justify-content-between align-items-center" id="posSheetSub">
              <span><i class="bi bi-arrow-left-right me-2"></i>Substitutes in stock</span>
              <span class="badge badge-soft-primary cnt">0</span>
            </button>
            <button type="button" class="btn btn-mf text-start" id="posSheetOrder">
              <i class="bi bi-cart-plus me-2"></i>Add to order pad — buy in the next purchase
            </button>
          </div>
          <button type="button" class="btn btn-light-mf w-100 mt-2" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Order pad (re-order list) -->
  <div class="modal fade" id="posOrderPadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-cart-plus me-2 text-success"></i>Order pad</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div id="posOrderBody"></div>
          <div class="text-2 small-xs mt-2 border-top pt-2">“Open New Purchase” fills these lines for you — set supplier, batch, expiry and rates there.</div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf text-danger" id="posOrderClear" type="button">Clear</button>
          <button class="btn btn-mf" id="posOrderOpen" type="button"><i class="bi bi-bag-plus me-1"></i>Open New Purchase</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Split payment modal -->
  <div class="modal fade" id="posSplitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-mf-sm">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-diagram-3 me-2 text-success"></i>Split Payment</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Cash amount (₹)</label>
            <input type="number" class="form-control" id="splitCash" value="0" min="0">
          </div>
          <div>
            <label class="form-label">UPI amount (₹)</label>
            <input type="number" class="form-control" id="splitUpi" value="0" min="0">
          </div>
          <p class="text-2 small mt-3 mb-0">Both amounts together must equal the Grand Total.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="posSplitApply">Apply Split</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Held bills modal -->
  <div class="modal fade" id="posHeldModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-hourglass-split me-2 text-success"></i>Held Bills</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="posHeldBody"></div>
      </div>
    </div>
  </div>

  <!-- Rx capture-later queue — details only today (matches the prescriptions register). -->
  <div class="modal fade" id="posRxPendModal" tabindex="-1" data-bs-focus="false">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable" style="max-width:640px">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-file-medical me-2" style="color:#92600a"></i>Rx capture — pending</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
        </div>
        <div class="modal-body">
          <div class="text-2 small mb-3"><i class="bi bi-camera me-1"></i>Idle-time work, not checkout work: for each sale, snap the paper Rx on your phone later from the Prescriptions page, or record its details here. Register-required schedules are flagged red — clear them before closing day-end.</div>
          <div id="rpList"></div>

          <div class="border rounded-3 p-3 mt-3" id="rpFormWrap" hidden>
            <div class="fw-bold mb-2" id="rpFormTitle">Capture — </div>
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label">Patient name <span class="req">*</span></label>
                <input class="form-control" id="rpPatient" placeholder="Name on the paper Rx">
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label">Age</label>
                <input class="form-control" id="rpAge" inputmode="numeric" placeholder="—">
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label">Phone</label>
                <input class="form-control" id="rpPhone" inputmode="numeric" placeholder="—">
              </div>
              <div class="col-md-6">
                <label class="form-label">Doctor <span class="req">*</span></label>
                <div class="d-flex gap-1">
                  <select class="form-select" id="rpDoctor"><option value="">— select doctor —</option></select>
                  <button class="btn btn-light-mf" type="button" id="rpAddDoctor" title="Add new doctor"><i class="bi bi-plus-lg"></i></button>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Rx date</label>
                <input class="form-control" id="rpDate" type="date">
              </div>
              <div class="col-12 d-flex gap-2 mt-2">
                <button class="btn btn-mf btn-sm" id="rpSave" type="button"><i class="bi bi-check2 me-1"></i>Attach & mark captured</button>
                <button class="btn btn-light-mf text-danger btn-sm ms-auto" id="rpManual" type="button" title="Already handled elsewhere — clear it from the queue with an audit note">Mark captured manually</button>
                <button class="btn btn-light-mf btn-sm" id="rpCancel" type="button">Back to list</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Scan & Send Rx inbox — photos customers uploaded from the in-store QR -->
  <div class="modal fade" id="posScanRxModal" tabindex="-1" data-bs-focus="false">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable" style="max-width:660px">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-qr-code me-2" style="color:#23408e"></i>Scan &amp; Send Rx — inbox</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
        </div>
        <div class="modal-body">
          <div class="d-flex align-items-center gap-2 mb-2">
            <div class="text-2 small flex-grow-1">Photos customers sent from their phones in-store. Attach one to the CURRENT bill — it becomes a real register entry and auto-selects in the prescription dropdown.</div>
            <button type="button" class="btn btn-light-mf btn-sm" id="sxQrBtn" title="Print the in-store QR poster customers scan"><i class="bi bi-printer me-1"></i>In-store QR</button>
          </div>
          <div id="sxList"></div>

          <div class="border rounded-3 p-3 mt-3" id="sxFormWrap" hidden>
            <div class="fw-bold mb-2" id="sxFormTitle">Attach — </div>
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label">Patient name <span class="req">*</span></label>
                <input class="form-control" id="sxPatient" placeholder="Name on the Rx">
              </div>
              <div class="col-md-6">
                <label class="form-label">Bill as customer</label>
                <select class="form-select" id="sxCustomer"><option value="">— keep current —</option></select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Doctor <span class="req">*</span></label>
                <div class="d-flex gap-1">
                  <select class="form-select" id="sxDoctor"><option value="">— select doctor —</option></select>
                  <button class="btn btn-light-mf" type="button" id="sxAddDoctor" title="Add new doctor"><i class="bi bi-plus-lg"></i></button>
                </div>
              </div>
              <div class="col-md-3">
                <label class="form-label">Rx date</label>
                <input class="form-control" id="sxDate" type="date">
              </div>
              <div class="col-md-3">
                <label class="form-label">Age <span class="req">*</span></label>
                <input class="form-control" id="sxAge" type="number" min="1" max="120" inputmode="numeric" placeholder="Yrs" required>
              </div>
              <div class="col-12 d-flex gap-2 mt-2">
                <button class="btn btn-mf btn-sm" id="sxAttach" type="button"><i class="bi bi-paperclip me-1"></i>Attach to current bill</button>
                <button class="btn btn-light-mf btn-sm" id="sxBack" type="button">Back to inbox</button>
                <button class="btn btn-light-mf text-danger btn-sm ms-auto" id="sxDismiss" type="button" title="Wrong / junk photo — remove it from the inbox">Dismiss photo</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick add — one modal serves both Customer and Doctor -->
  <div class="modal fade" id="quickAddModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="qaTitle">Add Customer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label"><span id="qaNameLbl">Name</span> <span class="req">*</span></label>
            <input class="form-control" id="qaName" placeholder="e.g. Ramesh Kumar">
          </div>
          <div class="mb-2" id="qaRegRow" hidden>
            <label class="form-label">Registration no. <span class="req">*</span></label>
            <input class="form-control" id="qaReg" placeholder="e.g. BMC-45231">
          </div>
          <div class="mb-2" id="qaSpecRow" hidden>
            <label class="form-label">Speciality</label>
            <input class="form-control" id="qaSpec" placeholder="e.g. General Physician">
          </div>
          <div class="mb-2">
            <label class="form-label">Phone</label>
            <input class="form-control" id="qaPhone" placeholder="e.g. 98765 43210">
          </div>
          <div class="mb-2" id="qaAddrRow">
            <label class="form-label">Address</label>
            <input class="form-control" id="qaAddr" placeholder="e.g. Ward 4, Madhepura">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="qaSave"><i class="bi bi-check2 me-1"></i>Save</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js?v=2026-10-06.16"></script>
  <!-- Quick Refill — slide-in side panel -->
  <div class="pos-refill-overlay" id="posRefillOverlay" hidden></div>
  <aside class="pos-refill-panel" id="posRefillPanel" role="dialog" aria-label="Quick refill" hidden>
    <div class="prf-head">
      <div class="prf-avatar" id="prfAvatar">?</div>
      <div class="prf-who">
        <strong id="prfName">Quick refill</strong>
        <span id="prfSub">Find a regular by mobile number</span>
      </div>
      <button type="button" class="prf-close" id="prfClose" aria-label="Close quick refill"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="prf-body">
      <div id="prfLookup">
        <label class="form-label" for="prfMobile">Customer mobile number</label>
        <div class="d-flex gap-2">
          <input id="prfMobile" class="form-control" inputmode="numeric" maxlength="12" placeholder="e.g. 98765 43210" autocomplete="off">
          <button type="button" class="prf-search" id="prfSearchBtn"><i class="bi bi-search"></i>Search</button>
        </div>
        <div class="prf-msg text-2 small mt-2" id="prfMsg">Type a mobile number to pull up the customer's last prescription.</div>
      </div>
      <div id="prfResult" hidden>
        <div class="prf-facts">
          <div class="prf-chip">
            <span class="prf-k">Last visit</span>
            <b id="prfLast">—</b>
          </div>
          <div class="prf-chip">
            <span class="prf-k">Prescribing doctor</span>
            <b id="prfDoctor">—</b>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-baseline prf-sec-title">
          <span class="fw-semibold">Previous prescription</span>
          <button type="button" class="prf-history" id="prfHistory" title="Full purchase history — coming soon">View all history</button>
        </div>
        <div class="prf-table">
          <div class="prf-thead"><span>Medicine</span><span>Quantity</span></div>
          <div id="prfItems"></div>
        </div>
      </div>
      <div id="prfHist" hidden>
        <div class="d-flex justify-content-between align-items-baseline prf-sec-title" style="margin-top:0">
          <span class="fw-semibold">Full purchase history</span>
          <button type="button" class="prf-history" id="prfHistBack"><i class="bi bi-arrow-left-short"></i>Back to last prescription</button>
        </div>
        <div class="prf-facts">
          <div class="prf-chip"><span class="prf-k">Visits</span><b id="prfHistVisits">—</b></div>
          <div class="prf-chip"><span class="prf-k">Lifetime spend</span><b id="prfHistSpend">—</b></div>
        </div>
        <div id="prfHistList"></div>
      </div>
    </div>
    <div class="prf-foot">
      <button type="button" class="prf-refill" id="prfRefillBtn" disabled>
        <i class="bi bi-arrow-repeat"></i><span>Refill selected (0 items)</span>
      </button>
    </div>
  </aside>

  <!-- BUMP this version on EVERY pos.js change — long-cache browsers must not serve stale billing logic. -->
  <script src="assets/js/pos.js?v=2026-10-06.21"></script>
  <script>
    document.addEventListener('DOMContentLoaded', async () => {
      await MF.boot();
      const D = window.MF_DATA;
      const $ = (s) => document.querySelector(s);

      function renderCustomers() {
        const custSel = document.getElementById('posCustomer');
        custSel.innerHTML = D.customers.map((c) =>
          `<option value="${c.id}">${MF.esc(c.name)}${c.name === 'Walk-in Customer' ? ' (default)' : ''}</option>`).join('');
      }
      renderCustomers();

      /* One quick-add modal serves both Customer (+ beside Customer) and Doctor (+ beside Doctor). */
      const qaModal = new bootstrap.Modal('#quickAddModal');
      let qaType = 'customer';
      function qaSetType(t) {
        qaType = t;
        const doctor = t === 'doctor';
        $('#qaTitle').innerHTML = doctor
          ? '<i class="bi bi-heart-pulse me-1 text-success"></i>Add Doctor'
          : '<i class="bi bi-person-plus me-1 text-success"></i>Add Customer';
        $('#qaNameLbl').textContent = doctor ? 'Doctor name' : 'Name';
        $('#qaRegRow').hidden = !doctor;
        $('#qaSpecRow').hidden = !doctor;
        $('#qaAddrRow').hidden = doctor;
        ['qaName', 'qaReg', 'qaSpec', 'qaPhone', 'qaAddr'].forEach((id) => $('#' + id).value = '');
      }
      $('#posAddCustomer').addEventListener('click', () => {
        qaSetType('customer');
        qaModal.show();
        setTimeout(() => $('#qaName').focus(), 250);
      });
      $('#posAddDoctor').addEventListener('click', () => {
        qaSetType('doctor');
        qaModal.show();
        setTimeout(() => $('#qaName').focus(), 250);
      });

      $('#qaSave').addEventListener('click', async () => {
        const name = $('#qaName').value.trim();
        if (!name) { MF.toast('Name is required.', 'warn'); return; }
        const btn = $('#qaSave');
        const idle = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
        try {
          if (qaType === 'doctor') {
            const reg = $('#qaReg').value.trim();
            if (!reg) { MF.toast('Registration number is required.', 'warn'); return; }
            const payload = { name, reg_no: reg, specialty: $('#qaSpec').value.trim(), phone: $('#qaPhone').value.trim(), status: 'Active' };
            let id;
            const res = await MF.Api.post('doctors.php', payload);
            id = res.id;
            if (!id) id = 'N' + Date.now();            // demo mode — no real id comes back
            (D.doctors = D.doctors || []).push({ id, name, specialty: payload.specialty, phone: payload.phone, reg_no: reg, status: 'Active' });
            await MF.rehydrate().catch(() => {});
            if (MF.refillPosDoctors) await MF.refillPosDoctors();
            // Whichever capture modal is open gets the new doctor selected there;
            // otherwise the normal main-picker flow.
            if ($('#posScanRxModal') && $('#posScanRxModal').classList.contains('show')) {
              window.__sxPickAfterAdd = false;
              if (MF.refillSxDoctor) MF.refillSxDoctor(id);
            } else if ($('#posRxPendModal') && $('#posRxPendModal').classList.contains('show')) {
              window.__sxPickAfterAdd = false;
              if (MF.refillRxDoctor) MF.refillRxDoctor('#rpDoctor', id);
            } else {
              window.__sxPickAfterAdd = false;
              $('#posDoctor').value = id;
            }
            qaModal.hide();
            MF.toast('Doctor added.', 'success');
            return;
          }
          const res = await MF.Api.post('customers.php', {
            name,
            phone: $('#qaPhone').value.trim(),
            address: $('#qaAddr').value.trim(),
          });
          let id = res.id;
          if (!id) {
            id = 'C' + Date.now();                     // demo fallback
            (D.customers = D.customers || []).push({ id, name, phone: $('#qaPhone').value.trim(), address: $('#qaAddr').value.trim() });
          }
          await MF.rehydrate().catch(() => {});
          renderCustomers();
          $('#posCustomer').value = id;
          qaModal.hide();
          MF.toast('Customer added.', 'success');
        } catch (err) {
          MF.toast(err.message || 'Could not save.', 'danger');
        } finally {
          btn.disabled = false;
          btn.innerHTML = idle;
          if (window.POSUI && POSUI.syncPickers) POSUI.syncPickers(); // select .value was set directly — repaint the lookup boxes
        }
      });
    });
  </script>
</body>
</html>
