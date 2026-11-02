<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php';
if (is_file(__DIR__ . '/core/MenuGate.php')) { require_once __DIR__ . '/core/MenuGate.php'; MenuGate::enforce(); }  // roles matrix + platform kill-switch — typed URLs obey
?>
<!doctype html>
<html lang="en">
<head>
  <!-- build 2026-10-31.01 - Phase C offline billing admin: policy, pairing, devices, holds -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Offline Billing · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .ob-card { border:1px solid #e3ebf4; border-radius:16px; background:#fff; box-shadow:0 1px 2px rgba(22,50,92,.04), 0 10px 28px rgba(22,50,92,.04); overflow:hidden; }
    .ob-card > .ob-head { display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:13px 16px; border-bottom:1px solid #e7eef6; background:#fbfcfe; }
    .ob-card > .ob-head h6 { margin:0; font-size:.8rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#7b8798; }
    .ob-note { font-size:.78rem; color:#8b9bb0; line-height:1.5; }
    .ob-lbl { display:block; font-size:.66rem; font-weight:800; letter-spacing:.07em; text-transform:uppercase; color:#8b9bb0; margin-bottom:3px; }
    .ob-in { border:1px solid #e3ebf4; border-radius:10px; padding:8px 11px; font-size:.86rem; font-weight:600; outline:0; width:100%; }
    .ob-in:focus { border-color:#176B5B; box-shadow:0 0 0 3px rgba(23,107,91,.12); }
    .ob-btn { border:1px solid #e3ebf4; background:#fff; color:#516278; border-radius:9px; padding:6px 12px; font-size:.78rem; font-weight:700; transition:all .15s ease; }
    .ob-btn:hover:not(:disabled) { border-color:#176B5B; color:#0F4D42; background:#F3FBF8; }
    .ob-btn:disabled { opacity:.5; cursor:default; }
    .ob-btn.pri { border:0; background:linear-gradient(135deg,#4F46E5,#3B32C4); color:#fff; }
    .ob-btn.danger:hover:not(:disabled) { border-color:#E5484D; color:#B02A37; background:#FEF2F2; }
    .ob-tbl { table-layout:auto; width:100%; }
    .ob-tbl thead th { background:#f7f9fc; color:#7b8798; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; border-bottom:1px solid #e7eef6; padding:10px 14px; white-space:nowrap; }
    .ob-tbl tbody td { border-bottom:1px solid #f0f4f8; padding:10px 14px; white-space:nowrap; vertical-align:middle; }
    .ob-tbl tbody tr:last-child td { border-bottom:0; }
    .ob-mono { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:.78rem; font-weight:650; color:#41546E; }
    .ob-pill { display:inline-block; border-radius:20px; padding:2px 9px; font-size:.66rem; font-weight:800; letter-spacing:.02em; }
    .ob-ok { background:#E6F4F1; color:#0F766E; } .ob-bad { background:#FDECEC; color:#B02A37; } .ob-warn { background:#FFF3DC; color:#93650A; }
    .ob-stat { border:1px solid #e3ebf4; border-radius:14px; padding:13px 15px; background:#fff; }
    .ob-stat span { display:block; font-size:.66rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#8b9bb0; }
    .ob-stat strong { display:block; margin-top:4px; font-size:1.02rem; font-weight:800; color:#1b2430; }
  </style>
</head>
<body data-page="offline-admin">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-wifi-off me-2 text-warning"></i>Offline Billing</h1>
            <p class="page-sub">Let one counter keep selling when the internet drops — queued bills sync themselves when it returns</p>
          </div>
          <div class="ms-auto d-flex align-items-center gap-2">
            <button class="ob-btn" id="obReload"><i class="bi bi-arrow-repeat me-1"></i>Refresh</button>
          </div>
        </div>

        <div class="row g-3 mb-3" id="obStats"></div>

        <div class="row g-3 mb-3">
          <div class="col-12 col-lg-7">
            <div class="ob-card h-100">
              <div class="ob-head"><h6><i class="bi bi-toggles me-1"></i>Policy</h6></div>
              <div class="p-3">
                <div class="d-flex flex-column gap-2">
                  <label class="d-flex align-items-center gap-2 ob-note" style="cursor:pointer"><input type="checkbox" id="obEnabled" class="form-check-input mt-0"> <b>Allow offline billing</b> for this store (off = the till only views offline)</label>
                  <div class="row g-2">
                    <div class="col-6 col-md-3"><label class="ob-lbl">Max offline bill (₹)</label><input id="obMaxBill" class="ob-in" inputmode="decimal" placeholder="5000"></div>
                    <div class="col-6 col-md-3"><label class="ob-lbl">Snapshot max age (h)</label><input id="obSnapH" class="ob-in" inputmode="numeric" placeholder="72"></div>
                    <div class="col-12 col-md-3 d-flex align-items-end"><label class="d-flex align-items-center gap-2 ob-note mb-2" style="cursor:pointer"><input type="checkbox" id="obCredit" class="form-check-input mt-0"> <b>Credit sales</b> allowed offline</label></div>
                    <div class="col-12 col-md-3 d-flex align-items-end justify-content-md-end"><button class="ob-btn pri mb-2" id="obSavePolicy"><i class="bi bi-check2 me-1"></i>Save policy</button></div>
                  </div>
                  <p class="ob-note mb-0"><i class="bi bi-shield-check me-1"></i>Offline bills skip nothing at sync: the real sales engine re-runs them, stock and credit are re-checked live, and anything doubtful waits for you below — never silently applied, never silently lost.</p>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-lg-5">
            <div class="ob-card h-100">
              <div class="ob-head"><h6><i class="bi bi-key me-1"></i>Pair this counter</h6></div>
              <div class="p-3">
                <p class="ob-note mt-0">Device id <span class="ob-mono" id="obDevId">…</span> — a 4–6 digit <b>store PIN</b> (not the password) unlocks offline billing on this machine for 24 h, renewed on every online login.</p>
                <div class="row g-2">
                  <div class="col-6"><label class="ob-lbl">Store PIN</label><input id="obPin1" type="password" inputmode="numeric" maxlength="8" class="ob-in" placeholder="••••"></div>
                  <div class="col-6"><label class="ob-lbl">Confirm PIN</label><input id="obPin2" type="password" inputmode="numeric" maxlength="8" class="ob-in" placeholder="••••"></div>
                </div>
                <div class="d-flex gap-2 mt-2 justify-content-end">
                  <button class="ob-btn" id="obRotate">Update PIN</button>
                  <button class="ob-btn pri" id="obPair"><i class="bi bi-phone me-1"></i>Pair &amp; arm</button>
                </div>
                <p class="ob-note mb-0 mt-2" id="obPairMsg" hidden></p>
              </div>
            </div>
          </div>
        </div>

        <div class="ob-card mb-3">
          <div class="ob-head"><h6><i class="bi bi-hdd-network me-1"></i>Paired counters</h6><span class="ob-note me-auto">Revoke stops offline use at once on next sync / online login.</span></div>
          <div class="table-scroll"><table class="table ob-tbl mb-0">
            <thead><tr><th>Device</th><th>Role</th><th>Ticket v</th><th>Last seen</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody id="obDevices"></tbody>
          </table></div>
        </div>

        <div class="ob-card mb-4">
          <div class="ob-head"><h6><i class="bi bi-hourglass-split me-1"></i>Hold queue</h6><span class="ob-note me-auto">Offline bills the live books refused — approve forces them in, void discards with a record kept.</span></div>
          <div class="table-scroll" style="max-height:46vh"><table class="table ob-tbl mb-0">
            <thead><tr><th>Key</th><th>Device</th><th>Why held</th><th>Since</th><th class="text-end">Actions</th></tr></thead>
            <tbody id="obHolds"></tbody>
          </table></div>
        </div>

      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js?v=2026-10-26.08"></script>
  <script>
    (function () {
      'use strict';
      const $ = (s) => document.querySelector(s);
      let P = null;

      async function load() {
        if (!MF.Api.live) { MF.toast('Offline billing needs the live server.', 'warn', 'Demo mode'); return; }
        const dev = await MF.Off.ensureDeviceId();
        $('#obDevId').textContent = dev;
        try {
          const res = await MF.Api.get('offline.php?admin=1&d=' + encodeURIComponent(dev));
          const d = (res && res.data) || {};
          P = d.policy || {};
          $('#obEnabled').checked = !!P.enabled;
          $('#obCredit').checked = !!P.creditOk;
          $('#obMaxBill').value = P.maxBill != null ? P.maxBill : '';
          $('#obSnapH').value = P.snapMaxH != null ? P.snapMaxH : '';
          const rows = (await MF.Off.rows()).length;
          $('#obStats').innerHTML = `
            <div class="col-6 col-lg-3"><div class="ob-stat"><span>Offline billing</span><strong>${P.enabled ? '<span class="ob-pill ob-ok">armed</span>' : '<span class="ob-pill ob-bad">disabled</span>'}</strong></div></div>
            <div class="col-6 col-lg-3"><div class="ob-stat"><span>This counter</span><strong>${(d.devices || []).some((x) => x.device_id === dev && !x.revoked) ? '<span class="ob-pill ob-ok">paired</span>' : '<span class="ob-pill ob-warn">not paired</span>'}</strong></div></div>
            <div class="col-6 col-lg-3"><div class="ob-stat"><span>Queued here</span><strong>${MF.num(rows)}</strong></div></div>
            <div class="col-6 col-lg-3"><div class="ob-stat"><span>Held for review</span><strong>${MF.num((d.holds || []).length)}</strong></div></div>`;
          $('#obDevices').innerHTML = (d.devices || []).map((x) => `
            <tr><td class="ob-mono">${MF.esc(x.device_id)}${x.device_id === dev ? ' <span class="ob-pill ob-ok">this</span>' : ''}</td>
            <td>${MF.esc(x.role || '')}</td><td class="num">${MF.num(x.ticket_v)}</td>
            <td>${MF.esc(String(x.last_seen || '').replace('T', ' ').slice(0, 16) || '—')}</td>
            <td>${x.revoked ? '<span class="ob-pill ob-bad">revoked</span>' : '<span class="ob-pill ob-ok">active</span>'}</td>
            <td class="text-end">
              ${x.revoked ? '' : `<button class="ob-btn danger" data-rev="${MF.esc(x.device_id)}">Revoke</button>`}
              <button class="ob-btn danger" data-unp="${MF.esc(x.device_id)}">Unpair</button>
            </td></tr>`).join('') || '<tr><td colspan="6"><div class="empty-state"><i class="bi bi-phone"></i>No counters paired yet.</div></td></tr>';
          $('#obHolds').innerHTML = (d.holds || []).map((h) => `
            <tr><td class="ob-mono">${MF.esc(h.client_key)}</td><td class="ob-mono">${MF.esc(h.device_id)}</td>
            <td style="max-width:340px;overflow:hidden;text-overflow:ellipsis" title="${MF.esc(h.hold_reason || '')}">${MF.esc(h.hold_reason || '—')}</td>
            <td>${MF.esc(String(h.applied_at || '').slice(0, 16))}</td>
            <td class="text-end"><button class="ob-btn pri" data-appr="${MF.esc(h.client_key)}">Approve anyway</button> <button class="ob-btn danger" data-void="${MF.esc(h.client_key)}">Void</button></td></tr>`).join('')
            || '<tr><td colspan="5"><div class="empty-state"><i class="bi bi-check2-circle"></i>Nothing on hold — the books are current.</div></td></tr>';
          $('#obDevices').querySelectorAll('[data-rev]').forEach((b) => b.addEventListener('click', () => act({ action: 'revoke', deviceId: b.dataset.rev }, b)));
          $('#obDevices').querySelectorAll('[data-unp]').forEach((b) => b.addEventListener('click', () => act({ action: 'unpair', deviceId: b.dataset.unp }, b)));
          $('#obHolds').querySelectorAll('[data-appr]').forEach((b) => b.addEventListener('click', () => act({ action: 'approve_hold', clientKey: b.dataset.appr }, b)));
          $('#obHolds').querySelectorAll('[data-void]').forEach((b) => b.addEventListener('click', () => act({ action: 'void_hold', clientKey: b.dataset.void }, b)));
        } catch (err) {
          MF.toast((err && err.message) || 'Could not load offline billing data.', 'danger', 'Offline billing');
        }
      }

      async function act(body, btn) {
        const old = btn.innerHTML; btn.disabled = true; btn.innerHTML = '…';
        try { await MF.Api.post('offline.php', body); MF.toast('Done.', 'success', 'Offline billing'); load(); }
        catch (err) { MF.toast((err && err.message) || 'Failed.', 'danger', 'Offline billing'); btn.disabled = false; btn.innerHTML = old; }
      }

      $('#obSavePolicy').addEventListener('click', async () => {
        await act({ action: 'set_policy', enabled: $('#obEnabled').checked ? 1 : 0, creditOk: $('#obCredit').checked ? 1 : 0, maxBill: parseFloat($('#obMaxBill').value) || 0, snapMaxH: parseInt($('#obSnapH').value, 10) || 72 }, $('#obSavePolicy'));
      });

      async function pinPost(pair) {
        const p1 = $('#obPin1').value, p2 = $('#obPin2').value;
        const msg = $('#obPairMsg'); msg.hidden = false;
        if (!/^\d{4,8}$/.test(p1) || p1 !== p2) { msg.textContent = 'PIN must be 4–8 digits and match in both boxes.'; return; }
        if (!(window.crypto && crypto.subtle)) { msg.textContent = 'WebCrypto unavailable — this page needs HTTPS.'; return; }
        msg.textContent = 'Deriving…';
        const saltBytes = new Uint8Array(16); crypto.getRandomValues(saltBytes);
        const salt = Array.from(saltBytes).map((b) => b.toString(16).padStart(2, '0')).join('');
        const hash = await MF.Off.derivePin(salt, p1);
        const dev = await MF.Off.ensureDeviceId();
        if (hash.length !== 64) { msg.textContent = 'This browser could not derive the PIN hash (PBKDF2 failed). Use https:// with Chrome/Edge, not a private window or an old WebView.'; return; }
        if (!dev) { msg.textContent = 'No device id on this browser — IndexedDB is blocked (private window / storage disabled). Use a normal window.'; return; }
        try {
          await MF.Api.post('offline.php', pair ? { action: 'pair', deviceId: dev, salt, pinHash: hash, label: 'Counter' } : { action: 'rotate_pin', deviceId: dev, salt, pinHash: hash });
          msg.textContent = pair ? 'Paired. Offline billing is armed on this machine for 24 h from its next boot.' : 'PIN updated for this counter.';
          $('#obPin1').value = ''; $('#obPin2').value = '';
          load();
        } catch (err) { msg.textContent = (err && err.message) || 'Failed.'; }
      }
      $('#obPair').addEventListener('click', () => pinPost(true));
      $('#obRotate').addEventListener('click', () => pinPost(false));
      $('#obReload').addEventListener('click', load);

      (async function () { await MF.boot(); load(); })();
    })();
  </script>
</body>
</html>
