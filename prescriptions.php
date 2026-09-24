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
  <title>Prescriptions · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="prescriptions">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-file-medical me-2 text-success"></i>Prescriptions</h1>
            <p class="page-sub">Retail sales billed against a doctor's prescription</p>
          </div>
        </div>

        <div class="card-mf mb-3 p-3">
          <div class="row g-2">
            <div class="col-md-4">
              <select class="form-select" id="rxDoctor"><option value="">All Doctors</option></select>
            </div>
          </div>
        </div>

        <div class="card-mf">
          <div class="card-head"><h2 class="card-title"><i class="bi bi-receipt"></i>Invoices</h2><div class="card-tools" id="rxCount"></div></div>
          <div class="table-scroll" style="max-height:none">
            <table class="table table-mf">
              <thead><tr><th>Date</th><th>Invoice</th><th>Customer</th><th>Doctor</th><th>Specialty</th><th class="text-end">Rx Items</th><th class="text-end">Amount</th></tr></thead>
              <tbody id="rxBody"></tbody>
            </table>
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
      const MF = window.MF, D = window.MF_DATA;
      const $ = (s) => document.querySelector(s);
      let rows = [];

      $('#rxDoctor').innerHTML += D.doctors.map((d) => `<option value="${d.id}">${MF.esc(d.name)}</option>`).join('');

      async function load() {
        const doctorId = $('#rxDoctor').value;
        const res = await MF.Api.get('prescriptions.php' + (doctorId ? '?doctorId=' + doctorId : ''));
        rows = res.data ?? res;
        render();
      }

      function render() {
        $('#rxCount').innerHTML = `<span class="badge badge-soft-secondary">${rows.length} invoice(s)</span>`;
        $('#rxBody').innerHTML = rows.map((r) => `
          <tr>
            <td class="num">${MF.fmtDate(r.date)}</td>
            <td class="num td-title">${r.invoiceNo}</td>
            <td>${MF.esc(r.customer)}</td>
            <td>${MF.esc(r.doctor)}</td>
            <td class="text-2">${MF.esc(r.specialty || '—')}</td>
            <td class="text-end num">${r.rxItems}</td>
            <td class="text-end num fw-semibold">${MF.fmt(r.amount)}</td>
          </tr>`).join('') || `<tr><td colspan="7"><div class="empty-state"><i class="bi bi-file-medical"></i>No prescription sales recorded yet — attach a doctor at checkout in Retail POS.</div></td></tr>`;
      }

      $('#rxDoctor').addEventListener('change', load);
      await load();
    })();
    });
  </script>
</body>
</html>
