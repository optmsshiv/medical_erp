/* OPTMS-RX service worker — offline shell + last-data cache. build 2026-10-31.01
 *
 * Strategy per resource class:
 *   navigations (/page.php)  network-first → cache (opens the app offline)
 *   same-origin /assets/*    network-first → cache (deploy-fresh online, alive offline)
 *   CDN (jsdelivr/fonts)     stale-while-revalidate (versioned bundles; cache is safe)
 *   /api/*.php GET           network + opportunistic cache clone → offline: cached copy
 *                            or an honest JSON 504 (pages already toast failures)
 *   any non-GET              pass-through — writes must reach the server (Phase C
 *                            will add the offline journal; until then no SW trickery).
 *
 * Privacy: app.js posts 'purge' on logout → the api cache (contains customer data)
 * dies with the session. Shell caches are public UI bytes.
 */
const SHELL = 'optms-shell-v2';
const CDN = 'optms-cdn-v2';
const API = 'optms-api-v1';

const CORE = [
  '/retail-pos.php', '/customers.php', '/customer-dues.php', '/payments.php', '/settings.php', '/wholesale-billing.php',
  '/assets/css/style.css', '/assets/js/app.js', '/assets/js/config.js', '/assets/js/data.js', '/assets/js/pos.js',
  '/manifest.json'
];

self.addEventListener('install', (e) => {
  e.waitUntil((async () => {
    const c = await caches.open(SHELL);
    await Promise.allSettled(CORE.map((u) => c.add(u)));
    self.skipWaiting();
  })());
});

self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    for (const k of await caches.keys()) if (k !== SHELL && k !== CDN && k !== API) await caches.delete(k);
    self.clients.claim();
  })());
});

self.addEventListener('message', (e) => {
  if (e.data === 'purge') caches.delete(API);
});

const isCdn = (u) => /(^|\.)((jsdelivr|googleapis|gstatic)\.[a-z]+)|(fonts\.googleapis\.com)|(fonts\.gstatic\.com)/.test(u.hostname);
const apiPath = (u) => u.pathname.indexOf('/api/') !== -1;

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') { return; }
  const u = new URL(req.url);

  if (u.origin === self.location.origin && apiPath(u)) {
    e.respondWith((async () => {
      try {
        const res = await fetch(req);
        if (res.ok) { const c = await caches.open(API); c.put(req, res.clone()); }
        return res;
      } catch (_) {
        const hit = await caches.match(req);
        if (hit) return hit;
        return new Response(JSON.stringify({ ok: false, offline: true, error: 'Offline — no cached data for this screen yet.' }),
          { status: 504, headers: { 'Content-Type': 'application/json' } });
      }
    })());
    return;
  }

  if (req.mode === 'navigate') {
    e.respondWith((async () => {
      try {
        const res = await fetch(req);
        const c = await caches.open(SHELL);
        c.put(new Request(u.pathname), res.clone());
        return res;
      } catch (_) {
        const hit = await caches.match(req) || await caches.match(u.pathname) || await caches.match('/retail-pos.php');
        if (hit) return hit;
        return new Response('<!doctype html><meta charset="utf-8"><title>Offline</title><body style="font:14px system-ui;padding:48px;text-align:center;color:#333"><h2>Offline</h2><p>No cached copy of this page is on this device yet — open the app once with internet first.</p></body>',
          { headers: { 'Content-Type': 'text/html' } });
      }
    })());
    return;
  }

  if (isCdn(u)) {
    e.respondWith((async () => {
      const hit = await caches.match(req);
      const net = fetch(req).then(async (res) => {
        if (res && res.status < 400) { const c = await caches.open(CDN); c.put(req, res.clone()); }
        return res;
      }).catch(() => hit);
      return hit || net;
    })());
    return;
  }

  if (u.origin === self.location.origin && (u.pathname.startsWith('/assets/') || /\.(css|js|svg|woff2?)$/.test(u.pathname))) {
    e.respondWith((async () => {
      try {
        const res = await fetch(req);
        if (res.ok) { const c = await caches.open(SHELL); c.put(req, res.clone()); }
        return res;
      } catch (_) {
        const hit = await caches.match(req);
        if (hit) return hit;
        return Response.error();
      }
    })());
  }
});
