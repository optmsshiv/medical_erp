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
  <title>Compliance Register · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    /* ---------- Compliance register (pro pass) ---------- */
    .cr-stat { background:#F8FAFC; border:1px solid #E7EDF4; border-radius:14px; padding:12px 14px; }
    .cr-stat span { display:block; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#6C757D; }
    .cr-stat strong { display:block; margin-top:3px; font-size:1.2rem; font-weight:750; letter-spacing:-.02em; color:#1B2430; }
    .cr-stat.accent { background:var(--mf-primary-soft); border-color:#D7EBE6; }
    .cr-stat.warn { background:#FEF9EF; border-color:#F5E3C2; }
    .cr-stat.warn strong { color:#B45309; }
    .cr-card { border:1px solid #E3EBF4; border-radius:16px; background:#fff; overflow:hidden; box-shadow:0 1px 2px rgba(22,50,92,.04), 0 10px 28px rgba(22,50,92,.04); }
    .cr-filter { display:flex; align-items:end; gap:12px; flex-wrap:wrap; padding:12px 16px; border-bottom:1px solid #E7EEF6; background:#FBFCFE; }
    .cr-field label { display:block; font-size:.68rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#8B9BB0; margin-bottom:4px; }
    .cr-field input[type="date"] { border:1px solid #E3EBF4; border-radius:10px; padding:6px 10px; font-size:.84rem; font-weight:650; color:#1B2430; outline:0; box-shadow:none !important; }
    .cr-chips { display:flex; gap:6px; flex-wrap:wrap; padding-bottom:1px; }
    .cr-chip { border:1px solid #E3EBF4; background:#fff; color:#516278; border-radius:999px; padding:5px 11px; font-size:.74rem; font-weight:700; transition:all .15s ease; cursor:pointer; }
    .cr-chip:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .cr-chip.active { background:var(--mf-primary); border-color:var(--mf-primary); color:#fff; }
    .cr-chip.x.active { background:#6D28D9; border-color:#6D28D9; }
    .cr-sep { width:1px; align-self:stretch; background:#E7EEF6; margin:0 2px; }
    .cr-table th { background:#F7F9FC; color:#7B8798; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; border-bottom:1px solid #E7EEF6; padding:11px 12px; white-space:nowrap; }
    .cr-table td { border-bottom:1px solid #F0F4F8; padding:10px 12px; vertical-align:middle; font-size:.86rem; }
    .cr-table tr:last-child td { border-bottom:0; }
    .cr-table tr:hover td { background:#F4FAF8; }
    .cr-num { font-variant-numeric:tabular-nums; }
    .cr-mut { font-size:.72rem; color:#8B9BB0; font-weight:600; display:block; }
    .cr-name { font-weight:700; color:#1B2430; }
    .cr-sq { display:inline-block; background:#F4F7FB; border:1px solid #E4EBF4; border-radius:6px; padding:1px 7px; font-size:.7rem; font-weight:700; color:#51637B; white-space:nowrap; }
    .cr-sq.h1 { background:#F1EBFC; border-color:#DDD0F5; color:#6D28D9; }
    .cr-sq.x { background:#FDECEA; border-color:#F3C1C8; color:#B02A37; }
    .cr-gap { display:inline-flex; align-items:center; gap:4px; font-size:.66rem; font-weight:800; letter-spacing:.02em; padding:2px 8px; border-radius:999px; white-space:nowrap; }
    .cr-gap.ok { background:#E6F1EE; color:#176B5B; }
    .cr-gap.warn { background:#FEF3E2; color:#B45309; }
    .cr-ok i, .cr-gap i { font-size:.7rem; }
    .cr-note { color:#8B9BB0; font-size:.76rem; line-height:1.5; padding:10px 16px; margin:0; border-top:1px solid #EEF3F8; background:#FBFCFE; }
    .cr-note i { color:var(--mf-primary); }
    @media print { .mf-sidebar, .mf-topbar { display:none !important; } }
  </style>
</head>
<body data-page="compliance-reports">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">
        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-clipboard2-pulse me-2 text-success"></i>Compliance Register</h1>
            <p class="page-sub">Schedule H1 &amp; X drugs register for Drug Inspector visits — Date, Patient, Prescriber, Medicine, Batch, Qty. One click to PDF or Excel.</p>
          </div>
          <div class="ms-auto d-flex gap-2 flex-wrap justify-content-end">
            <button class="btn btn-mf-outline" id="crExcel" type="button" disabled><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</button>
            <button class="btn btn-mf" id="crPdf" type="button" disabled><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</button>
            <button class="btn btn-light-mf" id="crRefresh" type="button"><i class="bi bi-arrow-repeat me-1"></i>Refresh</button>
          </div>
        </div>

        <div class="row g-3 mb-3" id="crStats"></div>

        <div class="cr-card mb-3">
          <div class="cr-filter">
            <div class="cr-field"><label for="crFrom">From</label><input type="date" id="crFrom"></div>
            <div class="cr-field"><label for="crTo">To</label><input type="date" id="crTo"></div>
            <div class="cr-chips" id="crRanges">
              <button type="button" class="cr-chip" data-r="today">Today</button>
              <button type="button" class="cr-chip" data-r="month">This month</button>
              <button type="button" class="cr-chip active" data-r="fy">This FY</button>
              <button type="button" class="cr-chip" data-r="all">All time</button>
            </div>
            <div class="cr-sep"></div>
            <div class="cr-chips" id="crClasses">
              <button type="button" class="cr-chip active" data-c="all">H1 &amp; X</button>
              <button type="button" class="cr-chip" data-c="h1">H1 only</button>
              <button type="button" class="cr-chip x" data-c="x">X only</button>
            </div>
            <span class="ms-auto cr-mut" id="crCount" style="align-self:center"></span>
          </div>
          <div class="table-responsive">
            <table class="table table-mf cr-table mb-0">
              <thead><tr>
                <th style="width:44px">Sr</th>
                <th>Date</th>
                <th>Patient Name</th>
                <th>Prescribing Doctor</th>
                <th>Medicine</th>
                <th>Batch</th>
                <th class="text-end">Qty Sold</th>
                <th>Bill Ref</th>
                <th>Check</th>
              </tr></thead>
              <tbody id="crBody"></tbody>
            </table>
          </div>
          <p class="cr-note"><i class="bi bi-shield-check me-1"></i>Rows are the sales-ledger truth: billed units of medicines tagged H1 / X on the medicine master (tagging lives on the <a href="schedules.php">Schedule / Class</a> page). Patient = recorded billing customer; prescriber = doctor recorded at checkout. Gaps are flagged, never filled in — fix them at the source before an inspection, not on paper.</p>
        </div>
      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    (function () {
      'use strict';
      const D = window.MF_DATA || {};
      const $ = (s, r) => (r || document).querySelector(s);
      const $$ = (s, r) => [...(r || document).querySelectorAll(s)];
      const pad = (n) => String(n).padStart(2, '0');
      const isoDay = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
      const todayStr = () => isoDay(new Date());
      const fyStart = () => { const d = new Date(); const y = d.getMonth() >= 3 ? d.getFullYear() : d.getFullYear() - 1; return y + '-04-01'; };
      const hm = (r) => { const t = String(r.sale_time || '').slice(0, 5); return t && t !== '00:00' ? t : ''; };

      const state = { from: fyStart(), to: todayStr(), cls: 'all', rows: [], summary: {}, busy: false };

      /* ---------------- data ---------------- */
      async function load() {
        if (state.busy) return;
        state.busy = true;
        renderBody();
        try {
          if (!MF.Api.live) throw new Error('demo');
          const res = await MF.Api.get(`compliance.php?from=${encodeURIComponent(state.from)}&to=${encodeURIComponent(state.to)}&cls=${encodeURIComponent(state.cls)}`);
          const data = res.data || {};
          state.rows = data.rows || [];
          state.summary = data.summary || {};
        } catch (e) {
          if (e.message !== 'demo') MF.toast(e.message || 'Could not load the register.', 'danger');
          state.rows = []; state.summary = {};
        } finally {
          state.busy = false;
          renderStats(); renderBody();
          $('#crExcel').disabled = $('#crPdf').disabled = !state.rows.length;
        }
      }

      /* ---------------- render ---------------- */
      function renderStats() {
        const s = state.summary || {};
        $('#crStats').innerHTML = `
          <div class="col-6 col-md"><div class="cr-stat accent"><span>H1 entries</span><strong>${MF.num(s.h1_entries || 0)}</strong></div></div>
          <div class="col-6 col-md"><div class="cr-stat"><span>X entries</span><strong>${MF.num(s.x_entries || 0)}</strong></div></div>
          <div class="col-6 col-md"><div class="cr-stat"><span>Units dispensed</span><strong>${MF.num(s.qty || 0)}</strong></div></div>
          <div class="col-6 col-md"><div class="cr-stat warn"><span>Missing prescriber</span><strong>${MF.num(s.missing_doctor || 0)}</strong></div></div>
          <div class="col-6 col-md"><div class="cr-stat warn"><span>Missing patient</span><strong>${MF.num(s.missing_patient || 0)}</strong></div></div>`;
      }

      function gapChip(r) {
        const gaps = [];
        if (!r.doctor_name) gaps.push('no prescriber');
        if (!r.patient_name) gaps.push('walk-in');
        if (!r.batch_no) gaps.push('no batch');
        if (!gaps.length) return '<span class="cr-gap ok"><i class="bi bi-check-circle-fill"></i>Complete</span>';
        return gaps.map((g) => `<span class="cr-gap warn"><i class="bi bi-exclamation-triangle-fill"></i>${g}</span>`).join(' ');
      }

      function renderBody() {
        const host = $('#crBody');
        if (state.busy && !state.rows.length) {
          host.innerHTML = '<tr><td colspan="9"><div class="empty-state"><i class="bi bi-arrow-repeat"></i>Reading the sales ledger…</div></td></tr>';
          $('#crCount').textContent = '';
          return;
        }
        $('#crCount').textContent = state.rows.length ? state.rows.length + ' register entr' + (state.rows.length === 1 ? 'y' : 'ies') : '';
        host.innerHTML = state.rows.map((r, i) => `
          <tr>
            <td class="cr-num" style="color:#8B9BB0">${i + 1}</td>
            <td class="cr-num"><div>${r.sale_date ? MF.fmtDate(r.sale_date) : '—'}</div>${hm(r) ? `<span class="cr-mut">${hm(r)}</span>` : ''}</td>
            <td>${r.patient_name ? `<span class="cr-name">${MF.esc(r.patient_name)}</span>${r.patient_phone ? `<span class="cr-mut">${MF.esc(r.patient_phone)}</span>` : ''}` : '<span class="text-2">—</span>'}</td>
            <td>${r.doctor_name ? `<span class="cr-name">${MF.esc(r.doctor_name)}</span>${r.doctor_reg_no ? `<span class="cr-mut">Reg ${MF.esc(r.doctor_reg_no)}</span>` : ''}` : '<span class="text-2">—</span>'}</td>
            <td><span class="cr-name">${MF.esc(r.medicine_name)}</span> <span class="cr-sq ${r.schedule_class === 'X' ? 'x' : 'h1'}">${MF.esc(r.schedule_class || '?')}</span></td>
            <td class="cr-num">${r.batch_no ? MF.esc(r.batch_no) : '<span class="text-2">—</span>'}</td>
            <td class="text-end cr-num fw-bold">${MF.num(r.qty)}${r.returned > 0 ? `<span class="cr-mut" style="color:#B45309">−${MF.num(r.returned)} ret</span>` : ''}</td>
            <td><a class="cr-sq" style="text-decoration:none;color:var(--mf-primary-dark)" href="sales-invoices.php?invoice=${encodeURIComponent(r.invoice_no)}" title="Open this bill">${MF.esc(r.invoice_no || '—')}</a></td>
            <td>${gapChip(r)}</td>
          </tr>`).join('') || '<tr><td colspan="9"><div class="empty-state"><i class="bi bi-clipboard2-pulse"></i>No H1 / X sales recorded in this period.</div></td></tr>';
      }

      /* ---------------- range + class chips ---------------- */
      function applyRange(kind) {
        const now = new Date();
        if (kind === 'today') { state.from = state.to = todayStr(); }
        else if (kind === 'month') { state.from = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-01'; state.to = todayStr(); }
        else if (kind === 'fy') { state.from = fyStart(); state.to = todayStr(); }
        else { state.from = '1900-01-01'; state.to = todayStr(); }
        $('#crFrom').value = state.from === '1900-01-01' ? '' : state.from;
        $('#crTo').value = state.to;
        load();
      }
      $$('#crRanges .cr-chip').forEach((c) => c.addEventListener('click', () => {
        $$('#crRanges .cr-chip').forEach((x) => x.classList.remove('active'));
        c.classList.add('active'); applyRange(c.dataset.r);
      }));
      $$('#crClasses .cr-chip').forEach((c) => c.addEventListener('click', () => {
        $$('#crClasses .cr-chip').forEach((x) => x.classList.remove('active'));
        c.classList.add('active'); state.cls = c.dataset.c; load();
      }));
      $('#crFrom').addEventListener('change', (e) => { state.from = e.target.value || fyStart(); load(); });
      $('#crTo').addEventListener('change', (e) => { state.to = e.target.value || todayStr(); load(); });

      /* ---------------- exports ---------------- */
      const schedWord = () => state.cls === 'h1' ? 'H1' : (state.cls === 'x' ? 'X' : 'H1 & X');
      const periodText = () => (state.from === '1900-01-01' ? 'All time' : `${MF.fmtDate(state.from)} to ${MF.fmtDate(state.to)}`);

      $('#crExcel').addEventListener('click', () => {
        const rows = state.rows.map((r, i) => [
          i + 1,
          r.sale_date || '',
          r.patient_name || '—',
          r.doctor_name || '—',
          r.medicine_name,
          r.schedule_class,
          r.batch_no || '—',
          r.qty,
          r.invoice_no || '—',
          ''
        ]);
        MF.exportCSV(`Schedule-${state.cls === 'all' ? 'H1-X' : state.cls.toUpperCase()}-register_${state.from}_to_${state.to}.csv`,
          ['Sr No', 'Date', 'Patient Name', 'Prescribing Doctor', 'Medicine', 'Schedule', 'Batch', 'Quantity Sold', 'Bill Ref', 'Remarks'],
          rows);
        MF.toast('Excel file downloaded — opens in MS Excel / Google Sheets.', 'success', 'Export ready');
      });

      function printIframe(innerHtml) {
        const frame = document.createElement('iframe');
        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
        document.body.appendChild(frame);
        const doc = frame.contentWindow.document;
        doc.open();
        doc.write('<!doctype html><html><head><meta charset="utf-8"><title>H1 X register</title></head><body>' + innerHtml + '</body></html>');
        doc.close();
        const done = () => setTimeout(() => { try { frame.remove(); } catch (e) { } }, 500);
        try {
          frame.contentWindow.addEventListener('afterprint', done);
          frame.contentWindow.focus();
          setTimeout(() => { try { frame.contentWindow.print(); } finally { done(); } }, 90);
        } catch (e) { done(); }
      }

      $('#crPdf').addEventListener('click', () => {
        const store = D.store || {};
        const bodyRows = state.rows.map((r, i) => `
          <tr>
            <td class="c">${i + 1}</td>
            <td class="b">${r.sale_date ? MF.fmtDate(r.sale_date) : '—'}</td>
            <td>${MF.esc(r.patient_name || '—')}</td>
            <td>${MF.esc(r.doctor_name || '—')}${r.doctor_reg_no ? `<div class="s">Reg ${MF.esc(r.doctor_reg_no)}</div>` : ''}</td>
            <td>${MF.esc(r.medicine_name)} <span class="sq">${MF.esc(r.schedule_class)}</span></td>
            <td>${MF.esc(r.batch_no || '—')}</td>
            <td class="r b">${MF.num(r.qty)}</td>
            <td>${MF.esc(r.invoice_no || '—')}</td>
            <td></td>
          </tr>`).join('');
        printIframe(`<style>
          @page { size: A4 landscape; margin: 10mm 12mm 14mm; }
          body { font-family: Arial, Helvetica, sans-serif; color:#111827; font-size:9px; line-height:1.45; }
          .lh { display:flex; gap:10px; align-items:flex-start; }
          .lg { width:38px; height:38px; border-radius:9px; background:#176B5B; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:800; }
          .nm { font-size:16px; font-weight:800; } .sb { font-size:8px; color:#4b5563; margin-top:2px; }
          .rt { margin-left:auto; text-align:right; font-size:8px; color:#4b5563; line-height:1.55; }
          h1 { text-align:center; font-size:12.5px; font-weight:800; letter-spacing:.06em; margin:10px 0 2px; border:1.8px solid #111827; border-radius:6px; padding:6px 8px; text-transform:uppercase; }
          .rule { text-align:center; font-size:8px; color:#4b5563; margin:0 0 8px; }
          table { width:100%; border-collapse:collapse; }
          th, td { border:1px solid #9ca3af; padding:4px 5px; text-align:left; vertical-align:top; }
          th { background:#eef2f7; font-size:7.5px; text-transform:uppercase; letter-spacing:.05em; color:#374151; }
          thead { display:table-header-group; }
          tr { page-break-inside:avoid; }
          .r { text-align:right; } .c { text-align:center; } .b { font-weight:700; }
          .s { font-size:7.5px; color:#6b7280; }
          .sq { display:inline-block; border:1px solid #6b7280; border-radius:4px; padding:0 4px; font-size:7.5px; font-weight:800; }
          .sum { margin-top:6px; font-size:9px; font-weight:700; }
          .sg { margin-top:26px; display:flex; justify-content:space-between; font-size:8.5px; page-break-inside:avoid; }
          .sg div { border-top:1.2px solid #111827; padding-top:4px; min-width:52mm; text-align:center; }
          .rd { border-top:1.2px solid #111827; padding-top:4px; min-width:64mm; text-align:center; }
        </style>
        <div class="lh">
          <div class="lg">Rx</div>
          <div><div class="nm">${MF.esc(store.name || 'Optms Rx')}</div>
            <div class="sb">${MF.esc(store.address || '')}${store.phone ? ' · Ph ' + MF.esc(store.phone) : ''}</div>
            <div class="sb">${store.gstin ? 'GSTIN ' + MF.esc(store.gstin) + ' · ' : ''}${store.dl_no ? 'DL ' + MF.esc(store.dl_no) : ''}</div>
          </div>
          <div class="rt">Generated ${MF.fmtDate(todayStr())} ${new Date().toTimeString().slice(0, 5)}<br>Period: <b>${MF.esc(periodText())}</b><br>Schedule: <b>${MF.esc(schedWord())}</b></div>
        </div>
        <h1>Schedule ${MF.esc(schedWord())} Drugs Register</h1>
        <p class="rule">Register maintained under Rule 65 of the Drugs and Cosmetics Rules, 1945 — produced for inspection.</p>
        <table>
          <thead><tr>
            <th style="width:26px">Sr</th><th style="width:62px">Date</th><th>Patient Name</th><th>Prescribing Doctor</th>
            <th>Medicine</th><th style="width:64px">Batch</th><th style="width:44px" class="r">Qty Sold</th>
            <th style="width:88px">Bill Ref</th><th style="width:70px">Remarks</th>
          </tr></thead>
          <tbody>${bodyRows || '<tr><td colspan="9" class="c" style="padding:14px">No entries in this period.</td></tr>'}</tbody>
        </table>
        <div class="sum">Total register entries: ${MF.num(state.summary.entries || state.rows.length)} · Units dispensed: ${MF.num(state.summary.qty || 0)} · Distinct medicines: ${MF.num(state.summary.medicines || 0)}</div>
        <div class="sg">
          <div>Prepared by — Pharmacist (name &amp; signature)</div>
          <div>Checked by — Drugs Inspector (name, designation, seal &amp; date)</div>
        </div>`);
      });

      $('#crRefresh').addEventListener('click', load);

      /* ---------------- boot ---------------- */
      (async function init() {
        await MF.boot();
        $('#crFrom').value = state.from;
        $('#crTo').value = state.to;
        renderStats(); renderBody();
        if (MF.Api.live) load();
      })();
    })();
  });
  </script>
</body>
</html>
