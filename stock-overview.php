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
  <title>Stock Overview · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="stock-overview">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">
        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-box-seam me-2 text-success"></i>Stock Overview</h1>
            <p class="page-sub">Live position from the batch ledger. This is not the stock report.</p>
          </div>
          <div class="ms-auto d-flex gap-2">
            <a class="btn btn-mf-outline" href="reports.php?tab=stock"><i class="bi bi-file-earmark-bar-graph me-1"></i>Open stock report</a>
            <a class="btn btn-mf" href="medicine-master.php?stock=low"><i class="bi bi-exclamation-triangle me-1"></i>Low stock</a>
          </div>
        </div>

        <div class="row g-3 mb-3" id="soKpis"></div>

        <div class="row g-3">
          <div class="col-lg-7">
            <div class="card-mf h-100">
              <div class="card-head">
                <strong>Stock value by category</strong>
                <span class="text-2 small ms-auto">Purchase rate × on-hand qty</span>
              </div>
              <div id="soCats"></div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="card-mf h-100">
              <div class="card-head">
                <strong>By manufacturer</strong>
                <span class="text-2 small ms-auto">Top value</span>
              </div>
              <div id="soMfgs"></div>
            </div>
          </div>
          <div class="col-12">
            <div class="card-mf">
              <div class="card-head">
                <strong>Stock ledger</strong>
                <span class="text-2 small">Available is sellable strips. Damaged is recorded damage write-off.</span>
                <a class="btn btn-mf-soft btn-sm ms-auto" href="reports.php?tab=stock">Stock report</a>
              </div>
              <div class="table-responsive">
                <table class="table table-mf">
                  <thead>
                    <tr>
                      <th>Medicine</th>
                      <th>Expiry</th>
                      <th class="text-end">Available</th>
                      <th class="text-end">Reserved</th>
                      <th class="text-end">Damaged</th>
                      <th class="text-end">Purchase rate</th>
                      <th class="text-end">MRP</th>
                      <th>Position</th>
                    </tr>
                  </thead>
                  <tbody id="soLedger"></tbody>
                </table>
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
    (function () {
      const MF = window.MF, D = window.MF_DATA;
      const $ = (s) => document.querySelector(s);

      function demoPayload() {
        const meds = D.medicines || [];
        const lines = meds.map((m) => {
          const batches = MF.batchesOf(m.id);
          const qty = batches.reduce((s, b) => s + (Number(b.qty) || Number(b.quantity) || 0), 0);
          const reserved = batches.reduce((s, b) => s + (Number(b.reserved) || 0), 0);
          const stockValue = batches.reduce((s, b) => s + (Number(b.qty) || Number(b.quantity) || 0) * (Number(b.purchaseRate) || Number(b.purchase_rate) || 0), 0);
          const mrpValue = batches.reduce((s, b) => s + (Number(b.qty) || Number(b.quantity) || 0) * (Number(b.mrp) || 0), 0);
          const next = batches.filter((b) => ((Number(b.qty) || Number(b.quantity) || 0) > 0) && (b.expiry || b.expiry_date)).map((b) => b.expiry || b.expiry_date).sort()[0] || null;
          const days = next ? MF.daysTo(next) : null;
          let position = 'In stock';
          if (qty <= 0) position = 'Out of stock';
          else if (days != null && days < 0) position = 'Expired';
          else if (days != null && days <= 90) position = 'Near expiry';
          else if (qty <= (Number(m.minStock) || 0)) position = 'Low stock';
          return {
            medicine_id: m.id, medicine_name: m.name, brand_name: m.brand_name || m.brandName || m.brandRef || '',
            category_name: m.category || 'Unassigned', manufacturer_name: m.manufacturer || 'Unassigned', unit: m.unit || 'Strip',
            qty, available: Math.max(qty - reserved, 0), reserved, damaged: Number(m.damaged) || 0,
            purchase_rate: Number(m.purchaseRate) || Number(m.purchase_rate) || 0, mrp: Number(m.mrp) || 0,
            next_expiry: next, stock_value: stockValue, mrp_value: mrpValue, batch_count: batches.length, position
          };
        });
        const sum = (key) => lines.reduce((s, r) => s + (Number(r[key]) || 0), 0);
        const count = (pos) => lines.filter((r) => r.position === pos).length;
        const group = (key) => {
          const map = new Map();
          lines.forEach((r) => {
            const name = r[key] || 'Unassigned';
            const cur = map.get(name) || { name, medicines: 0, qty: 0, stock_value: 0 };
            cur.medicines += 1;
            cur.qty += r.qty;
            cur.stock_value += r.stock_value;
            map.set(name, cur);
          });
          return [...map.values()].sort((a, b) => b.stock_value - a.stock_value);
        };
        return {
          summary: {
            medicines: lines.length,
            batches: lines.reduce((s, r) => s + r.batch_count, 0),
            qty: sum('qty'),
            stock_value: sum('stock_value'),
            mrp_value: sum('mrp_value'),
            in_stock: count('In stock'),
            low: count('Low stock'),
            out: count('Out of stock'),
            near_expiry: count('Near expiry'),
            expired: count('Expired')
          },
          by_category: group('category_name'),
          by_manufacturer: group('manufacturer_name').slice(0, 6),
          ledger: lines
        };
      }

      function tone(position) {
        return { 'Out of stock': 'danger', Expired: 'danger', 'Low stock': 'warning', 'Near expiry': 'warning', 'In stock': 'success' }[position] || 'secondary';
      }

      function render(data) {
        const s = data.summary || {};
        const cards = [
          ['Medicines', MF.num(s.medicines), 'capsule', 'primary'],
          ['On hand', MF.num(s.qty), 'box-seam', 'info'],
          ['Stock value', MF.fmt(s.stock_value), 'currency-rupee', 'success'],
          ['MRP value', MF.fmt(s.mrp_value), 'tag', 'primary'],
          ['Low', MF.num(s.low), 'exclamation-triangle', 'warning'],
          ['Out', MF.num(s.out), 'x-circle', 'danger'],
          ['Near expiry', MF.num(s.near_expiry), 'calendar2-x', 'warning'],
          ['Expired', MF.num(s.expired), 'calendar-x', 'danger']
        ];
        $('#soKpis').innerHTML = cards.map(([label, value, icon, toneName]) => `
          <div class="col-6 col-md-3">
            <div class="card-mf kpi-card">
              <div class="kpi-icon tone-${toneName}"><i class="bi bi-${icon}"></i></div>
              <div><div class="kpi-label">${label}</div><div class="kpi-value num">${value}</div></div>
            </div>
          </div>`).join('');

        const cats = data.by_category || [];
        const max = Math.max(1, ...cats.map((c) => Number(c.stock_value) || 0));
        $('#soCats').innerHTML = cats.length ? cats.map((c) => {
          const pct = Math.round(((Number(c.stock_value) || 0) / max) * 100);
          return `<div class="px-3 py-2 border-bottom">
            <div class="d-flex justify-content-between gap-2 mb-1">
              <span class="fw-semibold">${MF.esc(c.name)}</span>
              <span class="num">${MF.fmt(c.stock_value)}</span>
            </div>
            <div class="progress" style="height:6px"><div class="progress-bar" style="width:${pct}%;background:var(--mf-primary)"></div></div>
            <div class="text-2 small-xs mt-1">${MF.num(c.medicines)} medicines · ${MF.num(c.qty)} on hand</div>
          </div>`;
        }).join('') : '<div class="empty-state"><i class="bi bi-tags"></i>No category stock yet.</div>';

        const mfgs = data.by_manufacturer || [];
        $('#soMfgs').innerHTML = mfgs.length ? `<table class="table table-mf mb-0"><tbody>${mfgs.map((m) => `
          <tr><td>${MF.esc(m.name)}<div class="text-2 small-xs">${MF.num(m.medicines)} medicines</div></td>
          <td class="text-end num">${MF.fmt(m.stock_value)}</td></tr>`).join('')}</tbody></table>`
          : '<div class="empty-state"><i class="bi bi-buildings"></i>No manufacturer stock yet.</div>';

        const ledger = data.ledger || [];
        const meta = (r) => [r.category_name, r.brand_name].filter(Boolean).join(' · ');
        const expiryCell = (iso) => {
          if (!iso) return '<span class="text-2">—</span>';
          const days = MF.daysTo(String(iso).slice(0, 10));
          const cls = days < 0 ? 'text-danger' : days <= 90 ? 'text-warning' : '';
          return `<span class="num ${cls}">${MF.fmtDate(String(iso).slice(0, 10))}</span>`;
        };
        $('#soLedger').innerHTML = ledger.length ? ledger.map((r) => `<tr>
          <td>
            <div class="fw-semibold">${MF.esc(r.medicine_name)}</div>
            ${meta(r) ? `<div class="text-2 small-xs">${MF.esc(meta(r))}</div>` : ''}
          </td>
          <td>${expiryCell(r.next_expiry)}</td>
          <td class="text-end"><div class="num fw-semibold">${MF.num(r.available)}</div><div class="text-2 small-xs">strips</div></td>
          <td class="text-end num">${MF.num(r.reserved)}</td>
          <td class="text-end num">${MF.num(r.damaged)}</td>
          <td class="text-end num">${MF.fmt(r.purchase_rate, 2)}</td>
          <td class="text-end num">${MF.fmt(r.mrp, 2)}</td>
          <td>${MF.badge(r.position, tone(r.position))}</td>
        </tr>`).join('') : `<tr><td colspan="8"><div class="empty-state"><i class="bi bi-box-seam"></i>No stock yet.</div></td></tr>`;
      }

      document.addEventListener('DOMContentLoaded', async () => {
        await MF.boot();
        if (MF.Api.live) {
          try {
            const res = await MF.Api.get('stock-overview.php');
            render(res.data || {});
            return;
          } catch (e) {
            MF.toast(e.message, 'err', 'Could not load stock overview');
          }
        }
        render(demoPayload());
      });
    })();
  </script>
</body>
</html>
