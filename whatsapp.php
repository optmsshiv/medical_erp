<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php';
if (is_file(__DIR__ . '/core/MenuGate.php')) { require_once __DIR__ . '/core/MenuGate.php'; MenuGate::enforce(); }  // roles matrix + platform kill-switch — typed URLs obey
?>
<!doctype html>
<html lang="en">
<head>
  <!-- build 2026-10-29.02 - tenant WhatsApp management + own-line setup -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>WhatsApp · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .wa-card { border:1px solid #e3ebf4; border-radius:16px; background:#fff; box-shadow:0 1px 2px rgba(22,50,92,.04), 0 10px 28px rgba(22,50,92,.04); overflow:hidden; }
    .wa-card > .wa-head { display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:13px 16px; border-bottom:1px solid #e7eef6; background:#fbfcfe; }
    .wa-card > .wa-head h6 { margin:0; font-size:.8rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#7b8798; }
    .wa-stat { border:1px solid #e3ebf4; border-radius:14px; padding:13px 15px; background:#fff; }
    .wa-stat span { display:block; font-size:.66rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#8b9bb0; }
    .wa-stat strong { display:block; margin-top:4px; font-size:1.02rem; font-weight:800; color:#1b2430; }
    .wa-stat .wa-sub { display:block; margin-top:2px; font-size:.7rem; font-weight:600; color:#8b9bb0; }
    .wa-on { color:#0F766E !important; } .wa-off { color:#B02A37 !important; }
    .wa-tbl thead th { background:#f7f9fc; color:#7b8798; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; border-bottom:1px solid #e7eef6; padding:11px 14px; white-space:nowrap; }
    .wa-tbl tbody td { border-bottom:1px solid #f0f4f8; padding:10px 14px; vertical-align:middle; white-space:nowrap; }
    .wa-tbl { table-layout:auto; width:100%; }
    .wa-tbl tbody tr:last-child td { border-bottom:0; }
    .wa-tbl tbody tr:hover td { background:#f4faf8; }
    .wa-name { font-weight:700; color:#1b2430; max-width:280px; overflow:hidden; text-overflow:ellipsis; }
    .wa-sub2 { display:block; font-size:.72rem; color:#8b9bb0; font-weight:600; }
    .wa-input { max-width:170px; border:1px solid #e3ebf4; border-radius:9px; padding:6px 10px; font-size:.85rem; font-weight:600; letter-spacing:.02em; outline:0; }
    .wa-input:focus { border-color:#25D366; box-shadow:0 0 0 3px rgba(37,211,102,.14); }
    .wa-num-set { color:#0F766E; font-weight:700; } .wa-num-none { color:#9AA3AF; }
    .wa-kchip { display:inline-flex; align-items:center; gap:5px; border-radius:20px; padding:2px 9px; font-size:.68rem; font-weight:800; letter-spacing:.02em; }
    .wa-k-rem { background:#FDF0E7; color:#B45309; } .wa-k-test { background:#EEF0FE; color:#3B32C4; }
    .wa-btn { border:1px solid #e3ebf4; background:#fff; color:#516278; border-radius:9px; padding:5px 11px; font-size:.76rem; font-weight:700; transition:all .15s ease; }
    .wa-btn:hover:not(:disabled) { border-color:#25D366; color:#128C7E; background:#F3FBF8; }
    .wa-btn:disabled { opacity:.55; cursor:default; }
    .wa-search { display:flex; align-items:center; gap:8px; border:1px solid #e3ebf4; border-radius:10px; padding:6px 11px; background:#fff; }
    .wa-search input { border:0; outline:0; background:transparent; width:210px; font-size:.86rem; }
    .wa-log-ok { color:#0F766E; font-weight:800; } .wa-log-fail { color:#B02A37; font-weight:800; }
    .wa-mode { display:flex; gap:10px; align-items:flex-start; border:1px solid #e3ebf4; border-radius:12px; padding:10px 13px; cursor:pointer; transition:border-color .15s, background .15s; }
    .wa-mode:hover { background:#fbfcfe; }
    .wa-mode.sel { border-color:#25D366; background:#F3FBF8; }
    .wa-mode input { margin-top:3px; }
    .wa-mode b { font-size:.86rem; color:#1b2430; }
    .wa-lbl { display:block; font-size:.66rem; font-weight:800; letter-spacing:.07em; text-transform:uppercase; color:#8b9bb0; margin-bottom:3px; }
    .wa-input.w-100 { width:100%; max-width:none; }
    .wa-note { font-size:.78rem; color:#8b9bb0; line-height:1.5; }
    @media (max-width:767.98px) { .wa-search input { width:120px; } }
  </style>
</head>
<body data-page="whatsapp">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-whatsapp me-2" style="color:#25D366"></i>WhatsApp</h1>
            <p class="page-sub">Due-reminder channel for your customers — numbers, status and send history</p>
          </div>
          <div class="ms-auto d-flex align-items-center gap-2">
            <a class="wa-btn" href="customer-dues.php"><i class="bi bi-cash-stack me-1"></i>Customer Dues</a>
          </div>
        </div>

        <div class="row g-3 mb-3" id="waStats"></div>

        <div class="card-mf wa-card mb-3">
          <div class="wa-head">
            <h6><i class="bi bi-sliders me-1"></i>WhatsApp line &amp; setup</h6>
            <span class="wa-note me-auto" id="waSrcBadge">—</span>
          </div>
          <div class="p-3">
            <div class="d-flex flex-column gap-2 mb-3" id="waModes">
              <label class="wa-mode" data-m="platform"><input type="radio" name="waMode" value="platform">
                <span><b>Platform line <span class="wa-note">(recommended)</span></b><div class="wa-note">Send through the shared store line managed by OPTMS-RX. Nothing to configure.</div></span></label>
              <label class="wa-mode" data-m="own"><input type="radio" name="waMode" value="own">
                <span><b>My own WhatsApp Business number</b><div class="wa-note">Your Meta Business account's phone number ID + permanent token. Templates must be approved on <em>your</em> number.</div></span></label>
              <label class="wa-mode" data-m="off"><input type="radio" name="waMode" value="off">
                <span><b>Off</b><div class="wa-note">Pause due-reminder sends from this store. The portal's own billing messages are unaffected.</div></span></label>
            </div>
            <div id="waOwn" hidden>
              <div class="row g-2">
                <div class="col-6 col-lg-4"><label class="wa-lbl">Phone number ID *</label><input class="wa-input w-100" id="waPhoneId" inputmode="numeric" placeholder="e.g. 1055xxxxxxxxxxx"></div>
                <div class="col-6 col-lg-4"><label class="wa-lbl">Permanent token <span id="waTokHint" class="wa-note"></span></label><input class="wa-input w-100" id="waToken" type="password" autocomplete="new-password" placeholder="paste EABA… token"></div>
                <div class="col-6 col-lg-4"><label class="wa-lbl">Alert template (optional)</label><input class="wa-input w-100" id="waTpl" placeholder="due_reminder"></div>
                <div class="col-6 col-lg-4"><label class="wa-lbl">API version</label><input class="wa-input w-100" id="waVer" placeholder="v21.0"></div>
                <div class="col-3 col-lg-2"><label class="wa-lbl">Language</label><input class="wa-input w-100" id="waLang" placeholder="en"></div>
                <div class="col-3 col-lg-2"><label class="wa-lbl">Country code</label><input class="wa-input w-100" id="waCc" placeholder="91"></div>
              </div>
              <label class="wa-note d-inline-flex align-items-center gap-1 mt-2 mb-0"><input type="checkbox" id="waTokClear" class="form-check-input mt-0"> remove saved token</label>
              <p class="wa-note mb-0 mt-2"><i class="bi bi-info-circle me-1"></i>Where to find these: Meta for Developers → your app → WhatsApp → API setup. The token is stored in this store's own database, never on the browser.</p>
            </div>
            <div class="d-flex align-items-center gap-2 mt-3">
              <button class="btn btn-sm btn-mf" id="waCfgSave"><i class="bi bi-check2 me-1"></i>Save setup</button>
              <span class="wa-note" id="waCfgNote"></span>
            </div>
          </div>
        </div>

        <div class="card-mf wa-card mb-3">
          <div class="wa-head">
            <h6><i class="bi bi-person-lines-fill me-1"></i>Customer numbers</h6>
            <span class="wa-note me-auto">Reminders go to the WhatsApp number when set, otherwise the phone number.</span>
            <div class="wa-search"><i class="bi bi-search" style="color:#8b9bb0;font-size:13px"></i>
              <input id="waQ" placeholder="Search name or number…" autocomplete="off">
            </div>
          </div>
          <div class="table-scroll" style="max-height:52vh">
            <table class="table wa-tbl mb-0">
              <thead><tr><th>Customer</th><th>Phone</th><th>WhatsApp number</th><th class="text-end">Actions</th></tr></thead>
              <tbody id="waBody"></tbody>
            </table>
          </div>
        </div>

        <div class="card-mf wa-card mb-3">
          <div class="wa-head">
            <h6><i class="bi bi-clock-history me-1"></i>Recent sends</h6>
            <span class="wa-note me-auto">Last 50 sends from this store via the platform line.</span>
            <button class="wa-btn" id="waReload"><i class="bi bi-arrow-repeat me-1"></i>Refresh</button>
          </div>
          <div class="table-scroll" style="max-height:40vh">
            <table class="table wa-tbl mb-0">
              <thead><tr><th>When</th><th>To</th><th>Type</th><th>Via</th><th>Result</th></tr></thead>
              <tbody id="waLog"></tbody>
            </table>
          </div>
        </div>

        <p class="wa-note mb-4"><i class="bi bi-info-circle me-1"></i>The WhatsApp Business line itself (credentials, templates, on/off) is
        managed centrally on the platform — this page only reads its status and manages <em>your</em> customers&rsquo; numbers and reminders.
        Limits: 3 reminders and 5 tests per number per day.</p>

      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
    (function () {
      'use strict';
      const $ = (s) => document.querySelector(s);
      let D = { customers: [], log: [], status: {}, stats: {}, store: '', hasNumberColumn: true };

      function setSetup(st) {
        if (!st || !st.mode) return;
        const mode = st.mode;
        document.querySelectorAll('input[name="waMode"]').forEach((r) => { r.checked = r.value === mode; });
        document.querySelectorAll('.wa-mode').forEach((l) => l.classList.toggle('sel', l.getAttribute('data-m') === mode));
        $('#waOwn').hidden = mode !== 'own';
        const own = st.own || {};
        const fill = (id, v) => { const el = $(id); if (el && !el.matches(':focus')) el.value = v || ''; };
        fill('#waPhoneId', own.phone_id); fill('#waVer', own.ver); fill('#waLang', own.lang); fill('#waCc', own.cc); fill('#waTpl', own.tpl_alert);
        $('#waTokHint').textContent = own.has_token ? `(saved ${st.token_mask || '••••'})` : '(required)';
        $('#waToken').value = ''; $('#waToken').placeholder = own.has_token ? '••keep•• — leave blank to keep' : 'paste EABA… token';
        $('#waTokClear').checked = false;
      }

      document.querySelectorAll('input[name="waMode"]').forEach((r) => r.addEventListener('change', () => {
        document.querySelectorAll('.wa-mode').forEach((l) => l.classList.toggle('sel', l.querySelector('input').checked && l.getAttribute('data-m') === r.value));
        $('#waOwn').hidden = r.value !== 'own';
      }));

      $('#waCfgSave').addEventListener('click', async () => {
        const mode = (document.querySelector('input[name="waMode"]:checked') || {}).value || 'platform';
        const btn = $('#waCfgSave'); const old = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
        try {
          await MF.Api.post('whatsapp-manage.php', {
            action: 'save_cfg', mode,
            phone_id: $('#waPhoneId').value.trim(), token: $('#waToken').value.trim(),
            clear_token: $('#waTokClear').checked ? 1 : 0,
            ver: $('#waVer').value.trim(), lang: $('#waLang').value.trim(), cc: $('#waCc').value.trim(),
            tpl_alert: $('#waTpl').value.trim(),
          });
          $('#waCfgNote').textContent = 'Saved.';
          MF.toast('WhatsApp setup saved.', 'success', 'WhatsApp');
          load();
        } catch (err) { MF.toast((err && err.message) || 'Could not save setup.', 'danger', 'WhatsApp'); }
        finally { btn.disabled = false; btn.innerHTML = old; setTimeout(() => { $('#waCfgNote').textContent = ''; }, 2500); }
      });

      async function load() {
        if (!MF.Api.live) {
          $('#waStats').innerHTML = '<div class="col-12"><div class="wa-card p-3 wa-note">Demo mode — connect the live server to manage WhatsApp.</div></div>';
          $('#waBody').innerHTML = '<tr><td colspan="4"><div class="empty-state"><i class="bi bi-plug"></i>Live data only</div></td></tr>';
          $('#waLog').innerHTML = '';
          $('#waCfgSave').disabled = true;
          return;
        }
        try {
          const q = $('#waQ').value.trim();
          const res = await MF.Api.get('whatsapp-manage.php' + (q ? '?q=' + encodeURIComponent(q) : ''));
          D = (res && res.data) || D;
          render();
        } catch (err) {
          MF.toast((err && err.message) || 'Could not load WhatsApp data.', 'danger', 'WhatsApp');
        }
      }

      function render() {
        const st = D.status || {};
        const sa = D.stats || {};
        const conn = st.on
          ? `<span class="wa-on"><i class="bi bi-check-circle-fill me-1"></i>On</span>`
          : `<span class="wa-off"><i class="bi bi-slash-circle me-1"></i>Off</span>`;
        const src = st.source === 'own' ? 'Your own line' : (st.source === 'off' ? 'Switched off' : 'Platform line');
        $('#waSrcBadge').innerHTML = `<b>${src}</b> · ${st.on ? 'ready' : 'not ready'}`;
        setSetup(st);
        $('#waStats').innerHTML = `
          <div class="col-6 col-lg-3"><div class="wa-stat"><span>Active line</span><strong>${conn}</strong>
            <span class="wa-sub">${st.on ? src + ' · ' + MF.esc(st.phone_id || '—') + ' · ' + MF.esc(st.ver || '') : MF.esc(st.error || 'Use Setup below')}</span></div></div>
          <div class="col-6 col-lg-3"><div class="wa-stat"><span>Alert template</span><strong>${st.tpl_alert ? MF.esc(st.tpl_alert) : 'Free text'}</strong>
            <span class="wa-sub">${st.tpl_alert ? 'approved Meta template' : 'works within 24h chat window only'}</span></div></div>
          <div class="col-6 col-lg-3"><div class="wa-stat"><span>Reminders today</span><strong class="num">${MF.num(sa.today_reminders || 0)}</strong>
            <span class="wa-sub">max 3 per number / day</span></div></div>
          <div class="col-6 col-lg-3"><div class="wa-stat"><span>Last 7 days</span><strong class="num">${MF.num(sa.sent_7d || 0)}</strong>
            <span class="wa-sub">${MF.num(sa.failed_7d || 0)} failed</span></div></div>`;

        const rows = (D.customers || []);
        const editable = D.canEdit !== false;
        $('#waBody').innerHTML = rows.map((c) => `
          <tr>
            <td><div class="wa-name" title="${MF.esc(c.name)}">${MF.esc(c.name)}</div></td>
            <td class="num">${MF.esc(c.phone || '—')}</td>
            <td>${editable
              ? `<input class="wa-input" data-wa-in="${c.id}" value="${MF.esc(c.whatsapp || '')}" placeholder="${c.phone ? 'same as phone' : '10-digit number'}" maxlength="15">`
              : (c.whatsapp ? `<span class="wa-num-set">${MF.esc(c.whatsapp)}</span>` : '<span class="wa-num-none">—</span>')}</td>
            <td class="text-end">
              ${editable ? `<button class="wa-btn" data-wa-save="${c.id}"><i class="bi bi-check2 me-1"></i>Save</button>` : ''}
              <button class="wa-btn" data-wa-test="${c.id}"><i class="bi bi-send me-1"></i>Test</button>
            </td>
          </tr>`).join('')
          || `<tr><td colspan="4"><div class="empty-state"><i class="bi bi-people"></i>No customers found.</div></td></tr>`;
        if (!D.hasNumberColumn) {
          $('#waBody').insertAdjacentHTML('afterbegin',
            '<tr><td colspan="4" class="wa-note" style="background:#FDF6EC;color:#92400E"><i class="bi bi-exclamation-triangle me-1"></i>The database user needs one-time ALTER rights on <b>customers</b> so the whatsapp column can be added — until then reminders use the phone number.</td></td></tr>');
        }

        $('#waLog').innerHTML = (D.log || []).map((l) => {
          const t = String(l.at || '').replace('T', ' ');
          return `<tr>
            <td class="num">${MF.esc(t.slice(5, 16) || '—')}</td>
            <td class="num">${MF.esc(l.to)}</td>
            <td>${l.kind === 'due_reminder' ? '<span class="wa-kchip wa-k-rem"><i class="bi bi-cash-coin"></i>Reminder</span>' : '<span class="wa-kchip wa-k-test"><i class="bi bi-lightning"></i>Test</span>'}</td>
            <td>${MF.esc(l.via)}</td>
            <td>${l.ok ? '<span class="wa-log-ok"><i class="bi bi-check2 me-1"></i>Sent</span>' : `<span class="wa-log-fail" title="${MF.esc(l.error || '')}"><i class="bi bi-x-circle me-1"></i>Failed</span>`}</td>
          </tr>`;
        }).join('') || '<tr><td colspan="5"><div class="empty-state"><i class="bi bi-send"></i>No sends yet — test one from the table above.</div></td></tr>';

        $('#waBody').querySelectorAll('[data-wa-save]').forEach((b) => b.addEventListener('click', () => save(b)));
        $('#waBody').querySelectorAll('[data-wa-test]').forEach((b) => b.addEventListener('click', () => test(b)));
      }

      async function withBusy(btn, fn) {
        const old = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>…';
        try { await fn(); } finally { btn.disabled = false; btn.innerHTML = old; }
      }

      async function save(btn) {
        const id = btn.getAttribute('data-wa-save');
        const inp = $('#waBody').querySelector(`[data-wa-in="${id}"]`);
        if (!inp) return;
        await withBusy(btn, async () => {
          try {
            const res = await MF.Api.post('whatsapp-manage.php', { action: 'set_number', customerId: Number(id), whatsapp: inp.value.trim() });
            const num = (res && res.data && res.data.whatsapp) || '';
            inp.value = num;
            MF.toast(num ? 'WhatsApp number saved for this customer.' : 'WhatsApp number cleared — reminders will use the phone number.', 'success', 'Saved');
          } catch (err) { MF.toast((err && err.message) || 'Could not save.', 'danger', 'Save'); }
        });
      }

      async function test(btn) {
        const id = btn.getAttribute('data-wa-test');
        await withBusy(btn, async () => {
          try {
            const res = await MF.Api.post('whatsapp-manage.php', { action: 'test', customerId: Number(id) });
            const d = (res && res.data) || {};
            MF.toast(`Test ${MF.esc(d.via || 'message')} sent to ${MF.esc(d.to || '—')}.`, 'success', 'WhatsApp');
            load();
          } catch (err) { MF.toast((err && err.message) || 'Test failed.', 'danger', 'WhatsApp'); }
        });
      }

      let deb = null;
      $('#waQ').addEventListener('input', () => { clearTimeout(deb); deb = setTimeout(load, 350); });
      $('#waReload').addEventListener('click', load);

      (async function () { await MF.boot(); load(); })();
    })();
  </script>
</body>
</html>
