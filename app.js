/* ==========================================================================
   Optms Rx — Core Application Shell (Stage 1)
   --------------------------------------------------------------------------
   Responsibilities:
   - Sidebar / topbar rendering from a single NAV registry
   - Global search (Ctrl+K), notifications, quick actions, profile menu
   - Toasts, confirm dialogs, printable receipts, CSV export
   - Pharmacy domain helpers (stock, batch status, expiry buckets)
   - MF.Api — backend-ready stub mapped to future PHP REST endpoints
   ========================================================================== */

window.MF = window.MF || {};

(function () {
  const MF = window.MF;
  const D = window.MF_DATA;

  /* ----------------------------------------------------------------------
     API LAYER — Stage 2 live.
     MF.Api.live = true  → fetch() against PHP REST endpoints (assets/js/config.js)
     MF.Api.live = false → offline demo mode (all data from assets/js/data.js)
     Endpoints: auth/login · bootstrap · medicines · batches · customers ·
     suppliers · payments · pos/checkout · wholesale/invoice · sales-invoices · purchase-invoices · customer-dues · supplier-dues · purchases ·
     returns/sales · returns/purchase · users · settings
     ---------------------------------------------------------------------- */
  MF.Config = window.MF_CONFIG || { backend: false, apiBase: 'api/v1' };

  /* ---------------- Offline layer (Phase A+B) ----------------
     The last live boot is mirrored into IndexedDB, so with zero internet the app
     still OPENS (service worker) and shows the REAL last-synced masters — not the
     demo catalog. Writes stay hard-blocked until Phase C (offline sale journal +
     sync) lands; the design sits in OFFLINE-POS-DESIGN.md. */
  MF.offline = false; MF.snapAt = 0;
  const SNAP_DB = 'optms-offline', SNAP_ST = 'snap';
  function snapOpen() {
    return new Promise((res, rej) => {
      if (!('indexedDB' in window)) { rej(new Error('no idb')); return; }
      const r = indexedDB.open(SNAP_DB, 1);
      r.onupgradeneeded = () => { r.result.createObjectStore(SNAP_ST); };
      r.onsuccess = () => res(r.result);
      r.onerror = () => rej(r.error || new Error('idb open failed'));
    });
  }
  MF.snapSave = async (obj) => {
    try {
      const db = await snapOpen();
      await new Promise((res, rej) => {
        const tx = db.transaction(SNAP_ST, 'readwrite');
        tx.objectStore(SNAP_ST).put(Object.assign({ at: Date.now() }, obj), 'boot');
        tx.oncomplete = res; tx.onerror = () => rej(tx.error); tx.onabort = () => rej(tx.error);
      });
    } catch (_) { /* private mode / quota — offline viewing just won't be rich */ }
  };
  MF.snapLoad = async () => {
    try {
      const db = await snapOpen();
      return await new Promise((res, rej) => {
        const q = db.transaction(SNAP_ST).objectStore(SNAP_ST).get('boot');
        q.onsuccess = () => res(q.result || null); q.onerror = () => rej(q.error);
      });
    } catch (_) { return null; }
  };
  MF.snapClear = async () => {
    try {
      const db = await snapOpen();
      await new Promise((res, rej) => {
        const tx = db.transaction(SNAP_ST, 'readwrite');
        tx.objectStore(SNAP_ST).delete('boot');
        tx.oncomplete = res; tx.onerror = () => rej(tx.error);
      });
    } catch (_) { }
    try { if (navigator.serviceWorker && navigator.serviceWorker.controller) navigator.serviceWorker.controller.postMessage('purge'); } catch (_) { }
  };
  MF._offlineBanner = function () {
    let b = document.getElementById('mf-offline');
    if (!b) {
      b = document.createElement('div');
      b.id = 'mf-offline';
      b.setAttribute('style', 'position:fixed;left:50%;transform:translateX(-50%);bottom:14px;z-index:1200;background:#12291F;color:#DFF3EC;border:1px solid #295243;border-radius:12px;padding:9px 16px;font:700 12.5px/1.45 Inter,system-ui,sans-serif;box-shadow:0 12px 30px rgba(8,20,14,.35);display:flex;gap:9px;align-items:center;max-width:92vw');
      document.body.appendChild(b);
    }
    const t = MF.snapAt ? new Date(MF.snapAt).toLocaleString() : 'an unknown time';
    b.innerHTML = '<span style="width:8px;height:8px;border-radius:50%;background:#F0B429;flex:none"></span>'
      + '<span>OFFLINE \u00b7 data synced ' + t + ' \u00b7 billing &amp; saves disabled until the connection returns</span>';
  };
  if ('serviceWorker' in navigator && location.protocol === 'https:') {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => { /* not deployed yet — fine */ });
      if (!document.querySelector('link[rel=manifest]')) {
        const l = document.createElement('link'); l.rel = 'manifest'; l.href = 'manifest.json';
        document.head.appendChild(l);
      }
    });
  }
  window.addEventListener('online', async () => {
    try { await MF.Off.sync(); } catch (_) { }
    if (MF.offline) location.reload();
  });

  /* ---------------- Phase C — offline billing engine ----------------
     While the store is offline AND an owner-armed ticket is valid, retail sales
     are journalled in IndexedDB (never silently lost, never half-posted) and the
     till keeps trading. Sync replays them through the store's own sales API.
     PINs are verified locally via PBKDF2 against a hash the server only ever
     saw as a hash. Full contract: OFFLINE-POS-DESIGN.md §3.5 + §5. */
  MF.Off = {
    armed: false, unlocked: false, syncing: false, ticket: null, claims: null,
    deviceId: '', attempt: 0, timer: null, db: null,
    async open() {
      if (this.db) return this.db;
      this.db = await new Promise((res, rej) => {
        if (!('indexedDB' in window)) { rej(new Error('no idb')); return; }
        const r = indexedDB.open(SNAP_DB, 2);
        r.onupgradeneeded = () => { const dbs = r.result; if (!dbs.objectStoreNames.contains('snap')) dbs.createObjectStore('snap'); ['journal', 'meta'].forEach((n) => { if (!dbs.objectStoreNames.contains(n)) dbs.createObjectStore(n); }); };
        r.onsuccess = () => res(r.result); r.onerror = () => rej(r.error || new Error('idb'));
      });
      return this.db;
    },
    async tx(store, mode, fn) {
      const db = await this.open();
      return new Promise((res, rej) => {
        const t = db.transaction(store, mode);
        const r = fn(t.objectStore(store));
        t.oncomplete = () => res(r && 'result' in r ? r.result : undefined);
        t.onerror = t.onabort = () => rej(t.error);
      });
    },
    getMeta(k) { return this.tx('meta', 'readonly', (s) => s.get(k)).catch(() => undefined); },
    setMeta(k, v) { return this.tx('meta', 'readwrite', (s) => s.put(v, k)).catch(() => { }); },
    async ensureDeviceId() { // NB: name must not clash with the deviceId property (object-literal dup key bug fixed 2026-11-02)
      if (this.deviceId) return this.deviceId;
      let id = await this.getMeta('deviceId');
      if (!id) {
        const buf = new Uint8Array(5);
        (window.crypto && crypto.getRandomValues ? crypto.getRandomValues(buf) : buf.forEach((_, i) => { buf[i] = Math.floor(Math.random() * 256); }));
        id = 'DEV-' + Array.from(buf).map((b) => b.toString(16).padStart(2, '0')).join('').toUpperCase();
        await this.setMeta('deviceId', id);
      }
      return (this.deviceId = id);
    },
    rows() { return this.tx('journal', 'readonly', (s) => s.getAll()).catch(() => []); },
    parseTicket(t) {
      try {
        const b64 = String(t).split('.')[0].replace(/-/g, '+').replace(/_/g, '/');
        return JSON.parse(decodeURIComponent(escape(atob(b64))));
      } catch (_) { return null; }
    },
    async derivePin(saltHex, pin) {
      if (!(window.crypto && crypto.subtle)) return '';
      try {
        const salt = Uint8Array.from(String(saltHex).match(/../g) || [], (h) => parseInt(h, 16));
        const k = await crypto.subtle.importKey('raw', new TextEncoder().encode(String(pin)), 'PBKDF2', false, ['deriveBits']);
        const bits = await crypto.subtle.deriveBits({ name: 'PBKDF2', salt, iterations: 120000, hash: 'SHA-256' }, k, 256);
        return Array.from(new Uint8Array(bits)).map((b) => b.toString(16).padStart(2, '0')).join('');
      } catch (_) { return ''; }
    },
    async bootOnline() {
      try {
        const d = await this.ensureDeviceId();
        const res = await MF.Api.get('offline.php?d=' + encodeURIComponent(d));
        const data = (res && res.data) || {};
        if (data.policy) { D.offlinePolicy = data.policy; await this.setMeta('offlinePolicy', data.policy); }
        this.ticket = data.ticket || null;
        await this.setMeta('ticket', this.ticket || '');
        const rows = await this.rows();
        if (rows.length) { await this.sync(); }
      } catch (_) { /* old deploy without the endpoint — offline billing stays off */ }
    },
    async bootOffline() {
      let pol = D.offlinePolicy || (await this.getMeta('offlinePolicy'));
      if (pol && !pol.enabled) return;
      let t = this.ticket;
      if (!t) { t = await this.getMeta('ticket'); }
      if (!t) return;
      const cl = this.parseTicket(t);
      if (!cl) return;
      const d = await this.ensureDeviceId().catch(() => '');
      if (!d || cl.d !== d) return;
      if (Number(cl.exp) * 1000 < Date.now()) return;
      const ageH = MF.snapAt ? (Date.now() - MF.snapAt) / 36e5 : Infinity;
      if (ageH > (pol ? (Number(pol.snapMaxH) || 72) : 72)) return;
      this.ticket = t; this.claims = cl; this.armed = true;
      this.unlocked = sessionStorage.getItem('mf-off-ok') === '1';
    },
    async replayJournal() {
      const rows = await this.rows();
      rows.forEach((e) => { try { this.applyDelta(e); } catch (_) { } });
    },
    applyDelta(e) {
      const p = (e && e.payload) || {};
      (p.items || []).forEach((it) => {
        const b = (D.batches || []).find((x) => String(x.id) === String(it.batchId));
        if (b) b.qty = Math.max(0, (Number(b.qty) || 0) - (Number(it.qty) || 0));
      });
      const due = Number((e.meta && e.meta.due) || 0);
      if (due > 0 && p.customerId) {
        const c = (D.customers || []).find((x) => String(x.id) === String(p.customerId));
        if (c) c.due = Math.round(((Number(c.due) || 0) + due) * 100) / 100;
      }
    },
    async capture(payload) {
      if (!this.armed) return null;
      if (!this.unlocked && sessionStorage.getItem('mf-off-ok') !== '1') return null;
      let rows, seq, inv;
      try {
        rows = await this.rows();
        seq = (await this.getMeta('seq')) || 0;
        seq += 1; await this.setMeta('seq', seq);
        const short = (this.deviceId || '').replace(/^DEV-?/, '');
        inv = 'OFF-' + short + '-' + new Date().toISOString().slice(2, 10).replace(/-/g, '') + '-' + String(seq).padStart(4, '0');
      } catch (_) { return null; }
      const meta = (payload && payload._meta) || {};
      const grand = Math.round((Number(meta.grandTotal) || 0) * 100) / 100;
      const cap = Number((this.claims || {}).mb || 0);
      if (cap > 0 && grand > cap) { throw new Error('Offline bills are capped at ' + MF.fmt(cap) + ' per policy — finish this one online.'); }
      if ((payload || {}).paymentMode === 'credit' && !(Number((this.claims || {}).cc) === 1)) { throw new Error('Credit sales are switched off for offline billing by the owner.'); }
      const entry = { clientKey: (this.deviceId || 'DEV') + '-' + seq, at: Date.now(), kind: 'sale', payload, meta: { grandTotal: grand, due: Math.round((Number(meta.due) || 0) * 100) / 100, invoiceNo: inv } };
      try { await this.tx('journal', 'readwrite', (s) => s.put(entry, entry.clientKey)); } catch (_) { return null; }
      try { this.applyDelta(entry); } catch (_) { }
      return { ok: true, offline: true, invoiceNo: inv, grandTotal: grand, balanceDue: entry.meta.due };
    },
    async sync() {
      if (this.syncing || navigator.onLine === false) return;
      const rows = await this.rows();
      if (!rows.length) return;
      this.syncing = true;
      try {
        const d = await this.ensureDeviceId();
        const t = await this.getMeta('ticket') || this.ticket || '';
        const res = await MF.Api.post('offline-sync.php', { device: d, ticket: t, entries: rows.map((e) => ({ clientKey: e.clientKey, kind: e.kind, payload: e.payload, meta: e.meta })) });
        const rs = ((res && res.data) || {}).results || [];
        let applied = 0, held = 0;
        for (const r of rs) {
          if (r.status === 'applied' || r.status === 'dup' || r.status === 'voided') {
            try { await this.tx('journal', 'readwrite', (s) => s.delete(r.clientKey)); } catch (_) { }
            if (r.status === 'applied') applied += 1;
          } else { held += 1; }
        }
        const left = rows.length - applied - held;
        if (applied) { MF.toast(applied + ' offline sale(s) synced' + (held ? ' · ' + held + ' waiting for owner approval on Offline Billing' : ''), held ? 'warn' : 'success', 'Offline sync'); this.attempt = 0; }
        const gone = rows.length;
        const still = (await this.rows()).length;
        if (still && navigator.onLine !== false) { this.scheduleRetry(); }
        else if (applied) { setTimeout(() => location.reload(), 600); }
        else if (left > 0 && still < gone) { this.scheduleRetry(); }
      } catch (err) {
        this.scheduleRetry();
      } finally { this.syncing = false; }
    },
    scheduleRetry() {
      clearTimeout(this.timer);
      this.attempt = (this.attempt || 0) + 1;
      this.timer = setTimeout(() => { this.sync(); }, Math.min(900000, 30000 * Math.pow(2, this.attempt)));
    },
    async countLabel() {
      const rows = await this.rows();
      return rows.length ? ' · ' + rows.length + ' queued' : '';
    },
    async paint() {
      if (navigator.onLine !== false) return;
      const label = 'OFFLINE';
      let b = document.getElementById('mf-offline');
      if (!b) { b = document.createElement('div'); b.id = 'mf-offline'; b.setAttribute('style', 'position:fixed;left:50%;transform:translateX(-50%);bottom:14px;z-index:1200;background:#12291F;color:#DFF3EC;border:1px solid #295243;border-radius:12px;padding:9px 16px;font:700 12.5px/1.45 Inter,system-ui,sans-serif;box-shadow:0 12px 30px rgba(8,20,14,.35);display:flex;gap:9px;align-items:center;max-width:92vw'); document.body.appendChild(b); }
      const n = (await this.rows()).length;
      if (!this.armed) {
        b.innerHTML = '<span style="width:8px;height:8px;border-radius:50%;background:#F0B429;flex:none"></span><span>OFFLINE · data synced ' + (MF.snapAt ? new Date(MF.snapAt).toLocaleString() : 'an unknown time') + ' · billing &amp; saves disabled — one online sign-in on this till arms offline mode</span>';
        return;
      }
      if (!this.unlocked) {
        b.innerHTML = '<span style="width:8px;height:8px;border-radius:50%;background:#34B399;flex:none"></span><span>OFFLINE BILLING READY · sign in to this till to start' + (n ? ' · ' + n + ' queued' : '') + '</span><button id="mfOffBtn" style="border:1px solid #34B399;background:transparent;color:#9FE8D7;border-radius:8px;padding:4px 10px;font:inherit;cursor:pointer">Unlock</button>';
      } else {
        b.innerHTML = '<span style="width:8px;height:8px;border-radius:50%;background:#34B399;flex:none"></span><span>OFFLINE BILLING ON · saved on device' + (n ? ' · ' + n + ' queued for sync' : '') + '</span>';
      }
      const btn = document.getElementById('mfOffBtn');
      if (btn) btn.addEventListener('click', () => this.gate());
    },
    gate() {
      if (document.getElementById('mf-offgate')) return;
      const card = document.createElement('div');
      card.id = 'mf-offgate';
      card.setAttribute('style', 'position:fixed;inset:0;z-index:1210;background:rgba(8,20,14,.55);display:flex;align-items:center;justify-content:center');
      const cl = this.claims || {};
      const who = (D.users || []).find((u) => String(u.id) === String(cl.u || ''));
      card.innerHTML = '<div style="background:#fff;border-radius:16px;padding:22px 24px;min-width:300px;box-shadow:0 24px 60px rgba(8,20,14,.45);font-family:Inter,system-ui,sans-serif">'
        + '<h6 style="margin:0 0 2px;font-size:.95rem;font-weight:800;color:#12291F">Continue offline as ' + MF.esc((who && who.name) || 'this till') + '</h6>'
        + '<p style="margin:0 0 12px;font-size:.78rem;color:#8b9bb0">Store PIN unlocks offline billing · expires ' + new Date((Number(cl.exp) || 0) * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + '</p>'
        + '<input id="mfOffPin" type="password" inputmode="numeric" autocomplete="off" maxlength="8" placeholder="••••" style="width:100%;border:1px solid #e3ebf4;border-radius:10px;padding:10px 12px;font-size:1rem;letter-spacing:.3em;text-align:center;outline:0">'
        + '<div style="display:flex;gap:8px;margin-top:12px;justify-content:flex-end"><button id="mfOffNo" style="border:1px solid #e3ebf4;background:#fff;border-radius:9px;padding:7px 14px;font-weight:700;font-size:.8rem;cursor:pointer;color:#516278">Later</button>'
        + '<button id="mfOffYes" style="border:0;background:#176B5B;color:#fff;border-radius:9px;padding:7px 16px;font-weight:800;font-size:.8rem;cursor:pointer">Unlock</button></div>'
        + '<p id="mfOffMsg" style="margin:8px 0 0;font-size:.72rem;color:#B02A37;min-height:1em"></p></div>';
      document.body.appendChild(card);
      const inp = card.querySelector('#mfOffPin');
      inp.focus();
      const done = () => card.remove();
      card.querySelector('#mfOffNo').addEventListener('click', done);
      card.addEventListener('click', (e) => { if (e.target === card) done(); });
      const submit = async () => {
        const fails = Number(sessionStorage.getItem('mf-off-fail') || 0);
        if (fails >= 5) { card.querySelector('#mfOffMsg').textContent = 'Too many tries — offline unlock locked until the next online sign-in.'; return; }
        const ok = await this.unlock(inp.value);
        if (ok) { sessionStorage.removeItem('mf-off-fail'); done(); MF.toast('Offline billing unlocked — bills will queue and sync automatically.', 'success', 'Offline'); this.paint(); return; }
        sessionStorage.setItem('mf-off-fail', String(fails + 1));
        card.querySelector('#mfOffMsg').textContent = 'Wrong PIN (' + (fails + 1) + '/5).';
        inp.value = ''; inp.focus();
      };
      card.querySelector('#mfOffYes').addEventListener('click', submit);
      inp.addEventListener('keydown', (e) => { if (e.key === 'Enter') submit(); });
    },
    async unlock(pin) {
      const cl = this.claims;
      if (!cl || !cl.s || !cl.h) return false;
      const got = await this.derivePin(cl.s, pin);
      const ok = got && got === String(cl.h).toLowerCase();
      if (ok) { this.unlocked = true; sessionStorage.setItem('mf-off-ok', '1'); }
      return !!ok;
    },
  };

  MF.Api = {
    live: !!MF.Config.backend,
    base: MF.Config.apiBase || 'api/v1',
    async request(path, method = 'GET', payload = null) {
      if (!this.live) return { ok: true, demo: true, data: null };
      if (method !== 'GET' && navigator.onLine === false) {
        if (path === 'sales.php' && MF.Off) {
          const jr = await MF.Off.capture(payload);
          if (jr) return jr;
        }
        throw new Error('Offline \u2014 this change needs the internet. Nothing was saved.');
      }
      const res = await fetch(this.base + '/' + path, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
        credentials: 'same-origin',
        body: payload ? JSON.stringify(payload) : undefined,
      });
      if (res.status === 401) {
        if (!location.pathname.endsWith('login.php')) location.replace('login.php');
        throw new Error('Session expired');
      }
      let json = {};
      try { json = await res.json(); } catch (_) { /* non-JSON response */ }
      if (!res.ok || !json.ok) throw new Error(json.error || 'Request failed (HTTP ' + res.status + ')');
      return json;
    },
    get(p) { return this.request(p, 'GET'); },
    post(p, b) { return this.request(p, 'POST', b); },
    put(p, b) { return this.request(p, 'PUT', b); },
    del(p) { return this.request(p, 'DELETE'); },
  };

  /* Fill medicine fields bootstrap may omit. medicines.php is the save contract. */
  const MEDICINE_FIELDS = ['name', 'generic', 'brandRef', 'composition', 'category', 'manufacturer', 'hsn', 'gst', 'unit', 'form', 'packSize', 'packQty', 'subUnit', 'allowLoose', 'mrp', 'purchaseRate', 'retailRate', 'wholesaleRate', 'minStock', 'reorderLevel', 'schedule', 'rxRequired', 'barcode', 'genericGroup', 'genericGroupId', 'substitutes', 'expiryAlertDays', 'boxQty', 'boxUnit', 'rack', 'defaultDiscount', 'discountType', 'batchNo', 'openingQty', 'expiry', 'status'];
  function mergeMedicineCatalog(data, json) {
    const rows = json && Array.isArray(json.data) ? json.data : null;
    if (!rows || !Array.isArray(data.medicines)) return;
    const byId = new Map(rows.filter((m) => m && m.id != null && m.name != null).map((m) => [String(m.id), m]));
    if (!byId.size) return;
    data.medicines.forEach((m) => {
      const row = byId.get(String(m.id));
      if (!row) return;
      MEDICINE_FIELDS.forEach((k) => { if (Object.prototype.hasOwnProperty.call(row, k)) m[k] = row[k]; });
    });
    rows.forEach((m) => {
      if (m && m.id != null && m.name != null && !data.medicines.some((x) => String(x.id) === String(m.id))) data.medicines.unshift(m);
    });
    const batches = Array.isArray(json.batches) ? json.batches : [];
    if (batches.length && Array.isArray(data.batches)) {
      batches.forEach((b) => {
        if (!b || b.medId == null || b.batchNo == null) return;
        const idx = data.batches.findIndex((x) => String(x.medId) === String(b.medId) && String(x.batchNo) === String(b.batchNo));
        if (idx >= 0) Object.assign(data.batches[idx], b);
        else data.batches.push(b);
      });
    }
  }
  MF.mergeMedicineCatalog = mergeMedicineCatalog;

  /* Hydrate MF_DATA from the backend once (shape-compatible with data.js).
     Every page awaits MF.boot() before rendering; call MF.rehydrate() after
     any mutation to pull fresh server state. */
  MF.boot = function () {
    if (!MF.Api.live) return Promise.resolve(window.MF_DATA);
    if (!MF._bootP) {
      MF._bootP = MF.Api.get('bootstrap.php')
        .then(async (res) => {
          Object.assign(window.MF_DATA, res.data);
          try { const wr = await MF.Api.get('whoami.php'); MF._me = (wr && wr.data) || {}; } catch (_) { MF._me = null; }
          try { const pr = await MF.Api.get('role-permissions.php'); MF._prms = (pr && pr.data) || []; } catch (_) { MF._prms = null; }
          try { const nr = await MF.Api.get('nav-state.php'); MF._feats = ((nr && nr.data) || {}).features || null; } catch (_) { MF._feats = null; }
          try {
            const extra = await MF.Api.get('medicines.php');
            mergeMedicineCatalog(window.MF_DATA, extra);
          } catch (_) { /* bootstrap remains usable if the medicine endpoint is not deployed yet */ }
          MF.snapSave({ data: window.MF_DATA, feats: MF._feats, prms: MF._prms, me: MF._me });
          MF.Off.bootOnline();
          return window.MF_DATA;
        })
        .catch(async (e) => {
          MF._bootP = null;
          if (e.message === 'Session expired') throw e; // redirect already issued
          const snap = await MF.snapLoad();
          if (snap && snap.data) {
            MF.offline = true; MF.snapAt = Number(snap.at) || 0;
            Object.assign(window.MF_DATA, snap.data);
            if (snap.feats !== undefined) MF._feats = snap.feats;
            if (snap.prms) MF._prms = snap.prms;
            if (snap.me) MF._me = snap.me;
            MF._offlineBanner();
            Promise.resolve(MF.Off.bootOffline()).then(() => MF.Off.replayJournal()).then(() => MF.Off.paint()).catch(() => { });
          }
          console.warn('[Optms Rx] Backend unreachable —', (snap && snap.data) ? 'serving last synced data (offline).' : 'offline with no snapshot; demo data only.', e.message);
          return window.MF_DATA;
        });
    }
    return MF._bootP;
  };
  MF.rehydrate = function () { MF._bootP = null; return MF.boot(); };

  /* Platform feature key for a sidebar label — must equal MenuGate::featKey()
     and the CLIENT_FEATURE_GROUPS slugs: lowercase, non-alphanumerics → '_'. */
  MF._featKey = (l) => 'menu_' + String(l).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');

  /* Menu permission gate — matrix lives in Settings → Roles & Permissions.
     Owner/Admin or privileged roles: full access. Unconfigured menu: allowed
     (fail-open) — a menu is only hidden once an admin has explicitly unticked it. */
  MF.menuAllowed = function (label) {
    if (MF._feats && MF._feats[MF._featKey(label)] === false) return false; // super-admin kill-switch — binds owners too
    const me = MF._me;
    if (!me) return true;
    const role = String(me.role || '');
    if (me.privileged === true || /owner|admin/i.test(role)) return true;
    if (!Array.isArray(MF._prms)) return true;
    const row = MF._prms.find((r) => String(r.role).toLowerCase() === role.toLowerCase()
      && String(r.module).toLowerCase() === String(label).toLowerCase());
    if (!row) return true;
    return !!Number(row.allowed);
  };

  /* ---------------- Formatting & date helpers ---------------- */
  MF.fmt = (n, dec = 0) =>
    '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: dec, maximumFractionDigits: dec });
  MF.num = (n) => Number(n || 0).toLocaleString('en-IN');
  MF.today = () => new Date().toISOString().slice(0, 10);
  // Accepts 'YYYY-MM-DD', 'YYYY-MM-DD HH:MM:SS' (MySQL timestamps), ISO strings,
  // Date objects and epoch numbers — and NEVER returns "Invalid Date".
  MF.parseDate = (v) => {
    if (v === null || v === undefined || v === '') return null;
    if (v instanceof Date) return isNaN(v) ? null : v;
    let d;
    if (typeof v === 'number') { d = new Date(v < 1e12 ? v * 1000 : v); }
    else {
      const s = String(v).trim();
      const iso = s.includes('T') ? s : (s.includes(' ') ? s.replace(' ', 'T') : s + 'T00:00:00');
      d = new Date(iso);
    }
    return isNaN(d) ? null : d;
  };
  MF.fmtDate = (v) => {
    const d = MF.parseDate(v);
    return d ? d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
  };
  MF.fmtDateTime = (v) => {
    const d = MF.parseDate(v);
    return d ? d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) + ' · '
             + d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) : '—';
  };
  MF.fmtMonthYear = (v) => {
    const d = MF.parseDate(v);
    return d ? d.toLocaleDateString('en-IN', { month: 'short', year: 'numeric' }) : '—';
  };
  MF.daysTo = (iso) => {
    const now = new Date(MF.today() + 'T00:00:00');
    return Math.round((new Date(iso + 'T00:00:00') - now) / 86400000);
  };
  MF.esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* ---------------- Domain lookups ---------------- */
  // Loose equality (==) is deliberate here: IDs from <select> values or
  // dataset attributes are always strings ("5"), but real IDs from the
  // database are numbers (5). Strict equality would silently fail to
  // match them, breaking every page that reads an ID off the DOM.
  MF.med = (id) => D.medicines.find((m) => m.id == id);
  MF.cust = (id) => D.customers.find((c) => c.id == id);
  MF.sup = (id) => D.suppliers.find((s) => s.id == id);
  MF.batchesOf = (medId) => D.batches.filter((b) => b.medId == medId);
  MF.stockOf = (medId) => MF.batchesOf(medId).reduce((s, b) => s + b.qty, 0);

  /* Loose Sale: total sub-units sellable right now — loose stock already broken
     out of a pack, plus what every sealed pack could still yield if opened. */
  MF.looseAvailable = (medId) => {
    const med = MF.med(medId);
    const packQty = med && med.packQty ? med.packQty : 1;
    return MF.batchesOf(medId).reduce((s, b) => s + (b.looseQty || 0) + (b.qty - (b.reserved || 0)) * packQty, 0);
  };

  /* Generic/substitute linking: other active medicines sharing the same generic
     group that currently have sellable stock — useful when the searched item is out. */
  function groupKey(med) {
    if (!med) return '';
    const parts = String(med.genericGroup || '').split(/[,;+]|\s+\/\s+/).map((s) => s.trim().toLowerCase()).filter(Boolean).sort();
    if (parts.length) return 'g:' + parts.join('|');
    if (med.genericGroupId) return 'id:' + med.genericGroupId;
    return '';
  }
  MF.substitutesOf = (medId) => {
    const med = MF.med(medId);
    const key = groupKey(med);
    if (!key) return [];
    return D.medicines.filter((m) => m.id != medId && groupKey(m) === key && MF.stockOf(m.id) > 0);
  };

  MF.batchStatus = (b) => {
    const days = MF.daysTo(b.expiry);
    if (days < 0) return 'Expired';
    if (days <= 90) return 'Near Expiry';
    const med = MF.med(b.medId);
    if (med && MF.stockOf(med.id) <= Number(med.reorderLevel ?? med.minStock ?? 0)) return 'Low Stock';
    return 'Active';
  };

  MF.badge = (label, tone) => `<span class="badge badge-soft-${tone}">${label}</span>`;
  MF.statusBadge = (s) =>
    MF.badge(s, { Paid: 'success', Active: 'success', Due: 'danger', Expired: 'danger', Partial: 'warning', 'Low Stock': 'warning', 'Near Expiry': 'warning', Credit: 'warning', Inactive: 'secondary', Returned: 'danger', 'Part returned': 'warning' }[s] || 'secondary');

  MF.stockBadge = (med) => {
    const st = MF.stockOf(med.id);
    if (st === 0) return MF.badge('Out of Stock', 'danger');
    if (st <= Number(med.reorderLevel ?? med.minStock ?? 0)) return MF.badge('Low Stock', 'warning');
    return MF.badge('In Stock', 'success');
  };

  /* FEFO pick: earliest non-expired batch with stock */
  MF.pickBatch = (medId) => {
    return MF.batchesOf(medId)
      .filter((b) => b.qty > 0 && MF.daysTo(b.expiry) >= 0)
      .sort((a, b) => a.expiry.localeCompare(b.expiry))[0] || null;
  };

  /* ---------------- Toasts ---------------- */
  MF.toast = function (message, type = 'success', title) {
    let wrap = document.getElementById('mf-toasts');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.id = 'mf-toasts';
      wrap.className = 'toast-container position-fixed top-0 end-0 p-3';
      document.body.appendChild(wrap);
    }
    const icons = { success: 'check-circle', warn: 'exclamation-triangle', err: 'x-circle', info: 'info-circle' };
    const cls = type === 'success' ? '' : type;
    const titles = { success: 'Success', warn: 'Attention', err: 'Error', info: 'Info' };
    const el = document.createElement('div');
    el.className = `toast toast-mf ${cls}`;
    el.innerHTML = `
      <div class="d-flex align-items-start gap-2 p-3">
        <i class="bi bi-${icons[type] || 'check-circle'} fs-6 ${type === 'err' ? 'text-danger' : type === 'warn' ? 'text-warning' : type === 'info' ? 'text-info' : 'text-success'}"></i>
        <div class="flex-grow-1">
          <div class="fw-bold">${MF.esc(title || titles[type] || 'Success')}</div>
          <div class="text-2">${MF.esc(message)}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
      </div>`;
    wrap.appendChild(el);
    const t = new bootstrap.Toast(el, { delay: 3400 });
    t.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
  };

  /* ---------------- Confirm dialog (promise-based) ---------------- */
  MF.confirm = function ({ title = 'Are you sure?', message = '', confirmText = 'Confirm', cancelText = 'Cancel', tone = 'primary' } = {}) {
    return new Promise((resolve) => {
      let host = document.getElementById('mf-confirm-root');
      if (!host) { host = document.createElement('div'); host.id = 'mf-confirm-root'; document.body.appendChild(host); }
      host.innerHTML = `
        <div class="modal fade" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered modal-mf-sm">
            <div class="modal-content">
              <div class="modal-body p-4 text-center">
                <div class="kpi-icon tone-${tone === 'danger' ? 'danger' : 'primary'} mx-auto mb-3" style="width:52px;height:52px;flex-basis:52px">
                  <i class="bi bi-${tone === 'danger' ? 'question-lg' : 'shield-check'}"></i>
                </div>
                <h6 class="fw-bold mb-1">${MF.esc(title)}</h6>
                <p class="text-2 mb-0 small">${MF.esc(message)}</p>
              </div>
              <div class="modal-footer justify-content-center border-0 pt-0 flex-wrap">
                <button type="button" class="btn btn-light-mf px-4" data-act="cancel" title="Esc">${MF.esc(cancelText)}</button>
                <button type="button" class="btn btn-mf px-4" data-act="ok" title="Enter">${MF.esc(confirmText)}</button>
                <div class="w-100 text-center mt-1" style="font-size:.68rem;color:#8B9BB0;font-weight:600"><kbd>Enter</kbd> ${MF.esc(confirmText)} &nbsp;·&nbsp; <kbd>Esc</kbd> ${MF.esc(cancelText)}</div>
              </div>
            </div>
          </div>
        </div>`;
      const modalEl = host.querySelector('.modal');
      const modal = new bootstrap.Modal(modalEl);
      const okBtn = host.querySelector('[data-act="ok"]');
      const cancelBtn = host.querySelector('[data-act="cancel"]');
      okBtn.onclick = () => { modal.hide(); resolve(true); };
      cancelBtn.onclick = () => { modal.hide(); resolve(false); };
      /* Keyboard control: Enter confirms (unless Cancel itself is focused),
         Esc dismisses (Bootstrap hide → resolves false below). */
      modalEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && document.activeElement !== cancelBtn) { e.preventDefault(); okBtn.click(); }
      });
      modalEl.addEventListener('shown.bs.modal', () => okBtn.focus(), { once: true });
      modalEl.addEventListener('hidden.bs.modal', () => resolve(false), { once: true });
      modal.show();
    });
  };

  /* ---------------- Stage-2 module modal ---------------- */
  MF.openStage2 = function (module) {
    let host = document.getElementById('mf-stage-root');
    if (!host) { host = document.createElement('div'); host.id = 'mf-stage-root'; document.body.appendChild(host); }
    host.innerHTML = `
      <div class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-body p-4 text-center">
              <div class="kpi-icon tone-info mx-auto mb-3" style="width:52px;height:52px;flex-basis:52px"><i class="bi bi-rocket-takeoff"></i></div>
              <h6 class="fw-bold mb-2">${MF.esc(module)} — scoped for Stage 2</h6>
              <p class="text-2 small mb-1">This module is part of the production roadmap and will be built on the same</p>
              <p class="text-2 small mb-3">PHP 8 + MySQL + PDO backend as the modules in this demo.</p>
              <span class="badge badge-soft-primary">Backend-ready</span>
              <span class="badge badge-soft-secondary">UI kit shared</span>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
              <button type="button" class="btn btn-light-mf px-4" data-bs-dismiss="modal">Close</button>
              <button type="button" class="btn btn-mf px-4" id="mfStageRoadmap">View roadmap</button>
            </div>
          </div>
        </div>
      </div>`;
    host.querySelector('#mfStageRoadmap').onclick = () => {
      bootstrap.Modal.getInstance(host.querySelector('.modal')).hide();
      MF.toast('Stage 2: Prescriptions, Accounts, Stock Transfer, Audit trails, GSTR export', 'info', 'Roadmap');
    };
    new bootstrap.Modal(host.querySelector('.modal')).show();
  };

  /* ---------------- Print helper ---------------- */
  MF.printFrame = function (innerHtml) {
    let frame = document.getElementById('mf-print-frame');
    if (!frame) {
      frame = document.createElement('iframe');
      frame.id = 'mf-print-frame';
      frame.setAttribute('aria-hidden', 'true');
      frame.style.cssText = 'position:fixed;width:0;height:0;border:0;left:0;top:0';
      document.body.appendChild(frame);
    }
    const doc = frame.contentDocument || frame.contentWindow.document;
    doc.open();
    doc.write('<!doctype html><html><head><meta charset="utf-8"><title>Prescription</title><style>body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:18px;}table{width:100%;border-collapse:collapse;}@page{margin:14mm;}</style></head><body>' + innerHtml + '</body></html>');
    doc.close();
    const win = frame.contentWindow;
    setTimeout(() => { win.focus(); win.print(); }, 60);
  };

  MF.printHtml = function (innerHtml) {
    let host = document.getElementById('mf-print-root');
    if (!host) { host = document.createElement('div'); host.id = 'mf-print-root'; document.body.appendChild(host); }
    host.innerHTML = `
      <div class="modal fade" tabindex="-1" style="z-index:1085">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title"><i class="bi bi-printer me-2 text-success"></i>Print Preview</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light"><div class="mf-print-area bg-white border rounded p-4">${innerHtml}</div></div>
            <div class="modal-footer">
              <button class="btn btn-light-mf" data-bs-dismiss="modal" type="button">Close</button>
              <button class="btn btn-mf" type="button" id="mf-do-print"><i class="bi bi-printer me-1"></i>Print</button>
            </div>
          </div>
        </div>
      </div>`;
    const modalEl = host.querySelector('.modal');
    modalEl.addEventListener('shown.bs.modal', () => {
      document.querySelectorAll('.modal-backdrop').forEach((el, i, all) => {
        if (i === all.length - 1) el.style.zIndex = '1080';
      });
    });
    host.querySelector('#mf-do-print').addEventListener('click', () => MF.printFrame(innerHtml));
    new bootstrap.Modal(modalEl).show();
  };

  /* ---------------- CSV export ---------------- */
  MF.exportCSV = function (filename, headers, rows) {
    const esc = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const csv = [headers.map(esc).join(','), ...rows.map((r) => r.map(esc).join(','))].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.click();
    URL.revokeObjectURL(a.href);
    MF.toast(`${rows.length} rows exported to ${filename}`, 'success', 'Export complete');
  };

  /* ---------------- Navigation registry ---------------- */
  /* Collapsible navigation — top-level groups open as submenus.
     The group containing the active page auto-expands. */
  const NAV = [
    { label: 'Dashboard', icon: 'grid-1x2-fill', page: 'dashboard', href: 'dashboard.php' },
    {
      label: 'Sales', icon: 'cart-check', items: [
        { label: 'Retail POS', icon: 'cart3', page: 'retail-pos', href: 'retail-pos.php' },
        { label: 'Wholesale Billing', icon: 'receipt', page: 'wholesale-billing', href: 'wholesale-billing.php' },
        { label: 'Sales Invoices', icon: 'file-earmark-text', page: 'sales-invoices', href: 'sales-invoices.php' },
        { label: 'Sales Returns', icon: 'arrow-counterclockwise', page: 'sales-return', href: 'sales-return.php' },
        { label: 'Customers', icon: 'people', page: 'customers', href: 'customers.php' },
        { label: 'Customer Dues', icon: 'cash-stack', page: 'customer-dues', href: 'customer-dues.php' },
      ]
    },
    {
      label: 'Purchase', icon: 'bag-plus', items: [
        { label: 'New Purchase', icon: 'bag-plus-fill', page: 'purchase', href: 'purchase.php' },
        { label: 'Purchase Invoices', icon: 'file-earmark-ruled', page: 'purchase-invoices', href: 'purchase-invoices.php' },
        { label: 'Purchase Returns', icon: 'box-arrow-in-left', page: 'purchase-return', href: 'purchase-return.php' },
        { label: 'Suppliers', icon: 'truck', page: 'suppliers', href: 'suppliers.php' },
        { label: 'Supplier Dues', icon: 'wallet2', page: 'supplier-dues', href: 'supplier-dues.php' },
      ]
    },
    {
      label: 'Inventory', icon: 'boxes', items: [
        { label: 'Products / Medicines', icon: 'capsule', page: 'medicine-master', href: 'medicine-master.php' },
        { label: 'Stock Overview', icon: 'box-seam', page: 'stock-overview', href: 'stock-overview.php' },
        { label: 'Medicine Ledger', icon: 'journal-text', page: 'medicine-ledger', href: 'medicine-ledger.php' },
        { label: 'Batch Management', icon: 'collection', page: 'batch-management', href: 'batch-management.php' },
        { label: 'Expiry Management', icon: 'calendar2-x', page: 'expiry-management', href: 'expiry-management.php' },
        { label: 'Low Stock', icon: 'exclamation-triangle', href: 'medicine-master.php?stock=low' },
        { label: 'Stock Adjustment', icon: 'sliders', page: 'stock-adjustment', href: 'stock-adjustment.php' },
        { label: 'Stock Transfer', icon: 'arrow-left-right', page: 'stock-transfer', href: 'stock-transfer.php' }
      ]
    },
    {
      label: 'Pharmacy', icon: 'heart-pulse', items: [
        { label: 'Prescriptions', icon: 'file-medical', page: 'prescriptions', href: 'prescriptions.php' },
        { label: 'Doctors', icon: 'heart-pulse', page: 'doctors', href: 'doctors.php' },
        { label: 'Manufacturers', icon: 'buildings', page: 'manufacturers', href: 'manufacturers.php' },
        { label: 'Medicine Categories', icon: 'tags', page: 'categories', href: 'categories.php' },
        { label: 'Schedule / Class', icon: 'shield-check', page: 'schedules', href: 'schedules.php' },
        { label: 'Compliance Register', icon: 'clipboard2-pulse', page: 'compliance-reports', href: 'compliance-reports.php' }
      ]
    },
    {
      label: 'Accounts', icon: 'wallet2', items: [
        { label: 'Payments', icon: 'credit-card-2-front', page: 'payments', href: 'payments.php' },
        { label: 'Expenses', icon: 'cash-coin', page: 'expenses', href: 'expenses.php' },
        { label: 'Cash Book', icon: 'journal-text', page: 'cash-book', href: 'cash-book.php' },
        { label: 'GST', icon: 'percent', href: 'reports.php?tab=gst&view=summary' }
      ]
    },
    {
      label: 'Reports', icon: 'graph-up', items: [
        { label: 'Sales Reports', icon: 'graph-up', href: 'reports.php?tab=sales' },
        { label: 'Purchase Reports', icon: 'receipt-cutoff', href: 'reports.php?tab=purchase' },
        { label: 'Stock Reports', icon: 'box-seam', href: 'reports.php?tab=stock' },
        { label: 'Expiry Reports', icon: 'calendar-x', href: 'reports.php?tab=expiry' },
        { label: 'Profit Reports', icon: 'currency-rupee', href: 'reports.php?tab=profit' },
        { label: 'GST Reports', icon: 'percent', href: 'reports.php?tab=gst' },
        { label: 'Due Reports', icon: 'clock-history', href: 'reports.php?tab=dues' }
      ]
    },
    {
      label: 'Administration', icon: 'gear', items: [
        { label: 'Users', icon: 'person-badge', href: 'settings.php?tab=users' },
        { label: 'Roles & Permissions', icon: 'shield-lock', href: 'settings.php?tab=roles' },
        { label: 'Store Settings', icon: 'shop', href: 'settings.php?tab=store' },
        { label: 'WhatsApp', icon: 'whatsapp', page: 'whatsapp', href: 'whatsapp.php' },
        { label: 'Offline Billing', icon: 'wifi-off', page: 'offline-admin', href: 'offline-admin.php' },
        { label: 'Invoice Settings', icon: 'file-earmark-text', href: 'settings.php?tab=invoice' },
        { label: 'Tax Settings', icon: 'calculator', href: 'settings.php?tab=tax' },
        { label: 'Audit Logs', icon: 'journal-check', href: 'settings.php?tab=audit' },
        { label: 'My Subscription', icon: 'credit-card-2-front', href: 'subscription.php' }
      ]
    }
  ];
  const allNavItems = NAV.flatMap((s) => s.items || [s]);

  /* ---------------- Sidebar active-state matching ----------------
     An item is active when its target matches the current URL:
     - items with `page`  → match body[data-page]
     - items with `href`  → same file AND identical query params
       (default tabs applied, so ?tab= is implied on reports/settings) */
  const DEFAULT_TAB = { 'reports.php': 'sales', 'settings.php': 'store' };

  function hrefMatches(href) {
    const curPath = location.pathname.split('/').pop() || 'index.php';
    const [itemPath, qs] = href.split('?');
    if (itemPath !== curPath) return false;
    const curParams = new URLSearchParams(location.search);
    if (DEFAULT_TAB[curPath] && !curParams.has('tab')) curParams.set('tab', DEFAULT_TAB[curPath]);
    const a = [...new URLSearchParams(qs || '').entries()].sort();
    const b = [...curParams.entries()].sort();
    return a.length === b.length && a.every(([k, v], i) => b[i][0] === k && b[i][1] === v);
  }

  /* ---------------- Sidebar ---------------- */
  function itemActive(it, page, hrefActiveHere) {
    if (it.href && hrefMatches(it.href)) return true;
    if (it.page && it.page === page && !hrefActiveHere) return true;
    return false;
  }

  function navLinkHtml(it, page, hrefActiveHere) {
    const active = itemActive(it, page, hrefActiveHere) ? ' active' : '';
    const href = it.stub ? '#' : it.href;
    return `<a class="mf-nav-link${active}" href="${href}" ${it.stub ? `data-stub="${MF.esc(it.label)}"` : ''}>
      <i class="bi bi-${it.icon}"></i>${it.label}</a>`;
  }

  function renderSidebar() {
    const el = document.getElementById('mf-sidebar');
    if (!el) return;
    const page = document.body.dataset.page || '';
    /* If a deep-link item matches on this page, it wins over the generic page item
       (e.g. "Low Stock" instead of "Products / Medicines") */
    const hrefActiveHere = allNavItems.some((it) => it.href && hrefMatches(it.href));
    let html = `
      <div class="mf-brand">
        <img src="assets/images/logo.svg" alt="Optms Rx">
        <div class="mf-brand-name">Optms Rx<span>by Optms Tech</span></div>
      </div>
      <nav class="mf-sidebar-nav">`;
    NAV.forEach((sec) => {
      if (!sec.items) {
        if (!MF.menuAllowed(sec.label)) return;
        html += navLinkHtml(sec, page, hrefActiveHere);
        return;
      }
      const visItems = sec.items.filter((it) => MF.menuAllowed(it.label));
      if (!visItems.length) return;
      const hasActive = visItems.some((it) => itemActive(it, page, hrefActiveHere));
      html += `<div class="mf-nav-group${hasActive ? ' open' : ''}">
        <button type="button" class="mf-nav-parent${hasActive ? ' has-active' : ''}" aria-expanded="${hasActive}">
          <i class="bi bi-${sec.icon}"></i><span>${sec.label}</span><i class="bi bi-chevron-down mf-caret"></i>
        </button>
        <div class="mf-nav-sub"><div class="mf-nav-sub-inner">`;
      visItems.forEach((it) => { html += navLinkHtml(it, page, hrefActiveHere); });
      html += `</div></div></div>`;
    });
    html += `</nav>
      <div class="mf-sidebar-foot d-flex align-items-center justify-content-between">
        <span><i class="bi bi-shop me-1"></i>Main Store · Madhepura</span>
        <span class="badge badge-soft-primary">v1.0</span>
      </div>`;
    el.innerHTML = html;

    el.querySelectorAll('.mf-nav-parent').forEach((btn) => btn.addEventListener('click', () => {
      const g = btn.closest('.mf-nav-group');
      g.classList.toggle('open');
      btn.setAttribute('aria-expanded', g.classList.contains('open'));
    }));
    el.querySelectorAll('[data-stub]').forEach((a) => {
      a.addEventListener('click', (e) => { e.preventDefault(); MF.openStage2(a.dataset.stub); });
    });
    el.querySelectorAll('.mf-nav-link').forEach((a) => {
      a.addEventListener('click', () => document.querySelector('.mf-layout')?.classList.remove('sidebar-open'));
    });
  }

  /* ---------------- Topbar ---------------- */
  function renderTopbar() {
    const el = document.getElementById('mf-topbar');
    if (!el) return;
    const notifHtml = D.notifications.map((n) => `
      <a href="#" class="mf-notif mf-notif-link">
        <div class="mf-notif-icon kpi-icon tone-${n.tone === 'primary' ? 'primary' : n.tone}"><i class="bi bi-${n.icon}"></i></div>
        <div class="flex-grow-1">
          <div class="fw-semibold" style="font-size:.78rem;color:var(--mf-text)">${MF.esc(n.title)}</div>
          <div class="text-2 small-xs">${MF.esc(n.body)}</div>
          <div class="text-2 small-xs mt-1"><i class="bi bi-clock me-1"></i>${n.time}</div>
        </div>
      </a>`).join('');

    el.innerHTML = `
      <div class="mf-topbar-inner">
        <button class="mf-icon-btn d-lg-none" id="mfSidebarToggle" aria-label="Menu"><i class="bi bi-list"></i></button>
        <div class="mf-search-trigger" id="mfSearchTrigger" role="button" tabindex="0">
          <i class="bi bi-search"></i>
          <span>Search medicine, batch, invoice, customer...</span>
          <kbd>Ctrl K</kbd>
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
          <span id="mfRxChips" class="d-flex align-items-center gap-2"></span>
          <div class="dropdown">
            <button class="mf-icon-btn" data-bs-toggle="dropdown" aria-label="Quick actions"><i class="bi bi-lightning-charge"></i></button>
            <div class="dropdown-menu dropdown-menu-end shadow" style="min-width:230px">
              <h6 class="dropdown-header small-xs text-uppercase">Quick Actions</h6>
              <a class="dropdown-item" href="retail-pos.php"><i class="bi bi-cart-plus"></i>New Retail Sale</a>
              <a class="dropdown-item" href="wholesale-billing.php"><i class="bi bi-receipt"></i>New Wholesale Bill</a>
              <a class="dropdown-item" href="purchase.php"><i class="bi bi-bag-plus"></i>New Purchase Entry</a>
              <a class="dropdown-item" href="medicine-master.php?action=add"><i class="bi bi-capsule"></i>Add Medicine</a>
              <a class="dropdown-item" href="customers.php?action=add"><i class="bi bi-person-plus"></i>Add Customer</a>
            </div>
          </div>
          <div class="dropdown">
            <button class="mf-icon-btn" data-bs-toggle="dropdown" aria-label="Notifications">
              <i class="bi bi-bell"></i><span class="mf-dot"></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow p-0" style="width:330px">
              <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                <span class="fw-bold small">Notifications</span>
                <button class="btn btn-link btn-sm text-decoration-none p-0" id="mfNotifRead" style="font-size:.7rem">Mark all read</button>
              </div>
              <div style="max-height:340px;overflow:auto">${notifHtml}</div>
              <div class="text-center border-top py-2">
                <button class="btn btn-link btn-sm text-decoration-none" id="mfNotifAll" style="font-size:.72rem">View all activity</button>
              </div>
            </div>
          </div>
          <div class="mf-vr d-none d-sm-block"></div>
          <div class="dropdown">
            <button class="btn btn-light-mf d-flex align-items-center gap-2 px-2 py-1" data-bs-toggle="dropdown">
              <span class="mf-avatar" id="mfUserIni">··</span>
              <span class="d-none d-md-block text-start" style="line-height:1.15">
                <span class="d-block fw-semibold" style="font-size:.78rem" id="mfUserName"></span>
                <span class="d-block text-2 small-xs" id="mfUserRole"></span>
              </span>
              <i class="bi bi-chevron-down text-2 small d-none d-md-block"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow" style="min-width:200px">
              <a class="dropdown-item" href="settings.php?tab=users"><i class="bi bi-person"></i>My Profile</a>
              <a class="dropdown-item" href="settings.php?tab=store"><i class="bi bi-gear"></i>Store Settings</a>
              <a class="dropdown-item" href="settings.php?tab=audit"><i class="bi bi-journal-check"></i>Audit Logs</a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item text-danger" href="#" id="mfLogout"><i class="bi bi-box-arrow-right text-danger"></i>Sign out</a>
            </div>
          </div>
        </div>
      </div>`;

    el.querySelector('#mfSidebarToggle').addEventListener('click', () =>
      document.querySelector('.mf-layout')?.classList.toggle('sidebar-open'));
    el.querySelector('#mfSearchTrigger').addEventListener('click', openSearch);
    el.querySelector('#mfSearchTrigger').addEventListener('keydown', (e) => { if (e.key === 'Enter') openSearch(); });
    el.querySelector('#mfNotifRead').addEventListener('click', (e) => {
      e.preventDefault(); e.stopPropagation();
      document.querySelector('#mf-topbar .mf-dot')?.remove();
      MF.toast('All notifications marked as read', 'info');
    });
    el.querySelector('#mfNotifAll').addEventListener('click', () => MF.toast('Full activity center arrives with Stage 2', 'info', 'Notifications'));
    el.querySelectorAll('.mf-notif-link').forEach((a) => a.addEventListener('click', (e) => { e.preventDefault(); MF.toast('Opening related record is wired in Stage 2', 'info', 'Notification'); }));
    el.querySelector('#mfLogout').addEventListener('click', async (e) => {
      e.preventDefault();
      const ok = await MF.confirm({ title: 'Sign out of Optms Rx?', message: 'Any unsaved bills will be lost.', confirmText: 'Sign out', tone: 'danger' });
      if (!ok) return;
      if (MF.Api.live) {
        try { await MF.Api.post('auth/logout.php'); } catch (_) { /* session already gone */ }
        MF.snapClear();
        location.replace('login.php');
      } else {
        MF.toast('Signed out (demo). Session management ships with the PHP backend.', 'info', 'Demo mode');
      }
    });

    hydrateUserChip();
  }

  /* Top-right identity — real signed-in user from the session (whoami.php), never hardcoded. */
  function hydrateUserChip() {
    try {
      if (!MF.Api.live) return;
      MF.Api.get('whoami.php').then((r) => {
        const u = (r && r.data) || {};
        const nm = String(u.name || '').trim();
        const role = String(u.role || '').trim();
        if (!nm) return;   // session silent → leave placeholders
        const ini = nm.split(/\s+/).map((x) => x[0] || '').join('').slice(0, 2).toUpperCase();
        const i = document.getElementById('mfUserIni'); if (i) i.textContent = ini;
        const n = document.getElementById('mfUserName'); if (n) n.textContent = nm;
        const rl = document.getElementById('mfUserRole'); if (rl && role) rl.textContent = role.charAt(0).toUpperCase() + role.slice(1);
      }).catch(() => {});
    } catch (e) { }
  }

  /* ---------------- Global search ---------------- */
  function openSearch() {
    let host = document.getElementById('mf-search-root');
    if (!host) {
      host = document.createElement('div'); host.id = 'mf-search-root'; document.body.appendChild(host);
      host.innerHTML = `
        <div class="modal fade" id="mfSearchModal" tabindex="-1">
          <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content overflow-hidden">
              <div class="mf-search-head">
                <i class="bi bi-search text-2"></i>
                <input type="text" class="form-control" id="mfSearchInput" placeholder="Search medicine, batch, invoice, customer..." autocomplete="off">
                <kbd class="text-2 small-xs">Esc</kbd>
              </div>
              <div id="mfSearchResults" style="max-height:60vh;overflow:auto;padding:.85rem 1rem 1rem"></div>
            </div>
          </div>
        </div>`;
      host.querySelector('.modal').addEventListener('shown.bs.modal', () => host.querySelector('#mfSearchInput').focus());
      host.querySelector('#mfSearchInput').addEventListener('input', (e) => renderSearchResults(e.target.value));
    }
    host.querySelector('#mfSearchInput').value = '';
    renderSearchResults('');
    new bootstrap.Modal(host.querySelector('#mfSearchModal')).show();
  }

  function renderSearchResults(qRaw) {
    const q = qRaw.trim().toLowerCase();
    const box = document.getElementById('mfSearchResults');
    const thumb = (icon, tone) => `<div class="sr-thumb kpi-icon tone-${tone}" style="width:34px;height:34px;flex-basis:34px"><i class="bi bi-${icon}"></i></div>`;

    if (!q) {
      box.innerHTML = `
        <div class="sr-group-label">Try searching</div>
        <div class="d-flex flex-wrap gap-2">
          ${['Paracetamol', 'AZI25', 'INV-26-0124', 'MediMart', 'Cipla'].map((s) =>
        `<button class="btn btn-light-mf btn-sm mf-srch-suggest">${s}</button>`).join('')}
        </div>
        <div class="empty-state pb-0 pt-4"><i class="bi bi-search"></i>Search across medicines, batches, invoices, customers and suppliers.</div>`;
      box.querySelectorAll('.mf-srch-suggest').forEach((b) => b.addEventListener('click', () => {
        const inp = document.getElementById('mfSearchInput'); inp.value = b.textContent; renderSearchResults(b.textContent);
      }));
      return;
    }

    const meds = D.medicines.filter((m) =>
      (m.name + ' ' + m.generic + ' ' + m.composition + ' ' + m.brandRef).toLowerCase().includes(q)).slice(0, 5);
    const bats = D.batches.filter((b) => b.batchNo.toLowerCase().includes(q)).slice(0, 5);
    const invs = D.salesInvoices.filter((i) => i.no.toLowerCase().includes(q) || i.customer.toLowerCase().includes(q)).slice(0, 4);
    const custs = D.customers.filter((c) => c.id !== 'C01' && c.name.toLowerCase().includes(q)).slice(0, 4);
    const sups = D.suppliers.filter((s) => s.name.toLowerCase().includes(q)).slice(0, 4);
    const labelOf = (x) => typeof x === 'string' ? x : (x && x.name) || '';
    const mfgs = [...new Set([...(D.manufacturers || []).map(labelOf), ...(D.medicines || []).map((m) => m.manufacturer)])]
      .filter((n) => n && n.toLowerCase().includes(q)).slice(0, 4);
    const cats = [...new Set([...(D.categories || []).map(labelOf), ...(D.medicines || []).map((m) => m.category)])]
      .filter((n) => n && n.toLowerCase().includes(q)).slice(0, 4);

    let html = '';
    if (meds.length) {
      html += `<div class="sr-group-label">Medicines</div>` + meds.map((m) => `
        <div class="sr-item" data-go="medicine-master.php">
          ${thumb('capsule', 'primary')}
          <div class="flex-grow-1">
            <div class="fw-semibold" style="font-size:.8rem">${MF.esc(m.name)} <span class="text-2 small-xs">· ${MF.esc(m.manufacturer)}</span></div>
            <div class="text-2 small-xs">Stock: ${MF.num(MF.stockOf(m.id))} ${m.unit} · MRP: ${MF.fmt(m.mrp, 2)} · GST ${m.gst}%</div>
          </div>
          ${MF.stockBadge(m)}
        </div>`).join('');
    }
    if (bats.length) {
      html += `<div class="sr-group-label">Batches</div>` + bats.map((b) => {
        const m = MF.med(b.medId); const st = MF.batchStatus(b);
        return `<div class="sr-item" data-go="batch-management.php">
          ${thumb('collection', 'info')}
          <div class="flex-grow-1">
            <div class="fw-semibold" style="font-size:.8rem">${MF.esc(m.name)} <span class="text-2">·</span> <span class="num">${MF.esc(b.batchNo)}</span></div>
            <div class="text-2 small-xs">Expiry ${MF.fmtMonthYear(b.expiry)} · Qty ${b.qty} · MRP ${MF.fmt(b.mrp, 2)}</div>
          </div>
          ${MF.statusBadge(st)}
        </div>`;
      }).join('');
    }
    if (invs.length) {
      html += `<div class="sr-group-label">Invoices</div>` + invs.map((i) => `
        <div class="sr-item" data-go="sales-invoices.php?invoice=${encodeURIComponent(i.no)}">
          ${thumb('file-earmark-text', 'success')}
          <div class="flex-grow-1">
            <div class="fw-semibold" style="font-size:.8rem">${i.no} <span class="text-2 small-xs">· ${MF.esc(i.customer)}</span></div>
            <div class="text-2 small-xs">${MF.fmtDate(i.date)} · ${i.type} · ${MF.fmt(i.amount)}</div>
          </div>
          ${MF.statusBadge(i.status)}
        </div>`).join('');
    }
    if (custs.length) {
      html += `<div class="sr-group-label">Customers</div>` + custs.map((c) => `
        <div class="sr-item" data-go="customers.php">
          ${thumb('person', 'warning')}
          <div class="flex-grow-1">
            <div class="fw-semibold" style="font-size:.8rem">${MF.esc(c.name)}</div>
            <div class="text-2 small-xs">${c.type} · ${c.mobile} · Due ${MF.fmt(c.due)}</div>
          </div>
          <i class="bi bi-chevron-right text-2"></i>
        </div>`).join('');
    }
    if (sups.length) {
      html += `<div class="sr-group-label">Suppliers</div>` + sups.map((s) => `
        <div class="sr-item" data-go="suppliers.php">
          ${thumb('truck', 'danger')}
          <div class="flex-grow-1">
            <div class="fw-semibold" style="font-size:.8rem">${MF.esc(s.name)}</div>
            <div class="text-2 small-xs">GSTIN ${s.gstin} · Due ${MF.fmt(s.due)}</div>
          </div>
          <i class="bi bi-chevron-right text-2"></i>
        </div>`).join('');
    }
    if (mfgs.length) {
      html += `<div class="sr-group-label">Manufacturers</div>` + mfgs.map((name) => `
        <div class="sr-item" data-go="manufacturers.php">
          ${thumb('buildings', 'info')}
          <div class="flex-grow-1">
            <div class="fw-semibold" style="font-size:.8rem">${MF.esc(name)}</div>
            <div class="text-2 small-xs">${(D.medicines || []).filter((m) => m.manufacturer === name).length} medicine(s)</div>
          </div>
          <i class="bi bi-chevron-right text-2"></i>
        </div>`).join('');
    }
    if (cats.length) {
      html += `<div class="sr-group-label">Categories</div>` + cats.map((name) => `
        <div class="sr-item" data-go="categories.php">
          ${thumb('tags', 'primary')}
          <div class="flex-grow-1">
            <div class="fw-semibold" style="font-size:.8rem">${MF.esc(name)}</div>
            <div class="text-2 small-xs">${(D.medicines || []).filter((m) => m.category === name).length} medicine(s)</div>
          </div>
          <i class="bi bi-chevron-right text-2"></i>
        </div>`).join('');
    }
    if (!html) html = `<div class="empty-state"><i class="bi bi-emoji-neutral"></i>No results for “${MF.esc(qRaw)}”.<br><span class="small">Try a medicine name, batch no, invoice no or party name.</span></div>`;
    box.innerHTML = html;
    box.querySelectorAll('.sr-item').forEach((it) => it.addEventListener('click', () => {
      window.location.href = it.dataset.go;
    }));
  }

  /* ---------------- Keyboard & backdrop ---------------- */
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openSearch(); }
  });

  /* ---------------- Rx notification chips (top bar, before Quick actions) ----------------
   Two always-on chips: Scan & Send photo inbox + Rx-capture queue. On the POS page
   the REAL pill buttons (with their full engines) are relocated into this slot —
   one source of truth, zero duplication. Everywhere else a lightweight poller
   (30 s, paused on hidden tabs) keeps counts honest and glows on increase. */
  function wireRxChips() {
    const slot = document.getElementById('mfRxChips');
    if (!slot) return;
    const paintBellDot = () => {
      const dot = document.querySelector('#mf-topbar .mf-dot');
      if (!dot) return;
      const read = (id) => { const el = document.getElementById(id); return el ? (+el.textContent || 0) : 0; };
      const scan = read('posScanRxTxt') || read('glbScanRxTxt');
      const pend = read('posRxPendTxt') || read('glbRxPendTxt');
      dot.style.display = (scan + pend) > 0 ? '' : 'none';
    };
    document.querySelector('#mf-topbar .mf-dot')?.style.setProperty('display', 'none');

    if (document.getElementById('posScanRx')) {
      // POS page: adopt the native pills — engines untouched, single source of truth.
      ['posRxPend', 'posScanRx'].forEach((pid) => { const el = document.getElementById(pid); if (el) slot.appendChild(el); });
      setInterval(paintBellDot, 3000);
      paintBellDot();
      return;
    }

    // ---- Non-POS pages: standalone lightweight chips ----
    const styleId = 'mfRxChipCss';
    if (!document.getElementById(styleId)) {
      const st = document.createElement('style'); st.id = styleId;
      st.textContent = `
        .mf-rxchip { width:38px; height:38px; display:inline-flex; align-items:center; justify-content:center; position:relative;
          border:1px solid #e3ebf4; background:#fff; border-radius:var(--mf-radius-sm, 10px); font-size:1.05rem; cursor:pointer;
          transition:background .15s, border-color .15s, opacity .15s; }
        .mf-rxchip.is-idle { opacity:.5; }
        .mf-rxchip.is-idle .chip-badge { display:none; }
        .mf-rxchip.is-notify { animation:mfChipPulse .8s ease-in-out 3; border-color:var(--glowc); }
        .mf-rxchip.scan { border-color:#c9d8f5; color:#23408e; --glowc:#23408e; --glowc-soft:rgba(35,64,142,.5); --glowc-fade:rgba(35,64,142,0); }
        .mf-rxchip.scan:hover { background:#eaeffb; border-color:#b9cdf3; }
        .mf-rxchip.pend { border-color:#f2ddc2; color:#92600a; --glowc:#B45309; --glowc-soft:rgba(180,83,9,.5); --glowc-fade:rgba(180,83,9,0); }
        .mf-rxchip.pend:hover { background:#fdf3e6; border-color:#eccfa6; }
        .chip-badge { position:absolute; top:-6px; right:-6px; min-width:16px; height:16px; padding:0 4px; border-radius:999px;
          background:var(--glowc); color:#fff; font-size:.62rem; font-weight:800; line-height:1; font-variant-numeric:tabular-nums;
          display:inline-flex; align-items:center; justify-content:center; border:2px solid #fff; }
        @keyframes mfChipPulse {
          0% { box-shadow:0 0 0 0 var(--glowc-soft); transform:scale(1); }
          45% { box-shadow:0 0 0 7px var(--glowc-fade); transform:scale(1.06); }
          100% { box-shadow:0 0 0 0 var(--glowc-fade); transform:scale(1); }
        }`;
      document.head.appendChild(st);
    }
    slot.innerHTML = `
      <button type="button" class="mf-rxchip scan is-idle" id="glbScanRx" title="Scan & Send Rx — photos customers sent from the in-store QR"><i class="bi bi-qr-code"></i><span class="chip-badge" id="glbScanRxTxt">0</span></button>
      <button type="button" class="mf-rxchip pend is-idle" id="glbRxPend" title="Rx capture queue — prescription details still to record"><i class="bi bi-file-medical"></i><span class="chip-badge" id="glbRxPendTxt">0</span></button>`;
    document.getElementById('glbScanRx').addEventListener('click', () => { location.href = 'retail-pos.php#scanrx'; });
    document.getElementById('glbRxPend').addEventListener('click', () => { location.href = 'retail-pos.php#rxpend'; });

    let prevScan, prevPend;
    const glow = (el) => { if (!el) return; el.classList.remove('is-notify'); void el.offsetWidth; el.classList.add('is-notify'); setTimeout(() => el.classList.remove('is-notify'), 2600); };
    async function pollChips() {
      if (!(MF.Api && MF.Api.live)) return;
      try {
        const a = await MF.Api.get('rx-inbox.php?action=pending');
        const n = ((a.data && a.data.inbox) || []).length;
        const t = document.getElementById('glbScanRxTxt'); if (t) t.textContent = n;
        const c = document.getElementById('glbScanRx');
        if (c) { c.classList.toggle('is-idle', n === 0); if (prevScan !== undefined && n > prevScan) glow(c); prevScan = n; }
      } catch (e) { /* network blip — next poll */ }
      try {
        const b = await MF.Api.get('sale-audit.php?event=PENDING_RX');
        const n2 = ((b.data && b.data.pending) || []).length;
        const t2 = document.getElementById('glbRxPendTxt'); if (t2) t2.textContent = n2;
        const c2 = document.getElementById('glbRxPend');
        if (c2) { c2.classList.toggle('is-idle', n2 === 0); if (prevPend !== undefined && n2 > prevPend) glow(c2); prevPend = n2; }
      } catch (e) { /* network blip — next poll */ }
      paintBellDot();
    }
    pollChips();
    setInterval(() => { if (!document.hidden) pollChips(); }, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) pollChips(); });
  }

  /* ---------------- Init ---------------- */
  document.addEventListener('DOMContentLoaded', async () => {
    try { await MF.boot(); } catch (_) { /* 401 → login redirect already issued */ }
    renderSidebar();
    renderTopbar();
    wireRxChips();
    const layout = document.querySelector('.mf-layout');
    if (layout) {
      const bd = document.createElement('div');
      bd.className = 'mf-backdrop';
      bd.addEventListener('click', () => layout.classList.remove('sidebar-open'));
      layout.appendChild(bd);
    }
  });
})();
