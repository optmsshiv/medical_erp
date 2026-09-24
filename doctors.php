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
  <title>Doctors · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="doctors">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-heart-pulse me-2 text-success"></i>Doctors</h1>
            <p class="page-sub" id="drCount"></p>
          </div>
          <div class="ms-auto">
            <button class="btn btn-mf" id="drAddBtn"><i class="bi bi-plus-lg me-1"></i>Add Doctor</button>
          </div>
        </div>

        <div class="card-mf mb-3 p-3">
          <div class="input-group" style="max-width:360px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input class="form-control" id="drSearch" placeholder="Search name or specialty…">
          </div>
        </div>

        <div class="card-mf">
          <div class="table-scroll" style="max-height:none">
            <table class="table table-mf">
              <thead><tr><th>Name</th><th>Specialty</th><th>Phone</th><th>Registration No.</th><th class="text-end">Prescriptions</th><th class="text-end">Actions</th></tr></thead>
              <tbody id="drBody"></tbody>
            </table>
          </div>
        </div>

      </main>
    </div>
  </div>

  <div class="modal fade" id="drModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="drModalTitle">Add Doctor</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="drId">
          <div class="mb-2"><label class="form-label">Name <span class="req">*</span></label><input class="form-control" id="drName" placeholder="Dr. …"></div>
          <div class="row g-2">
            <div class="col-6"><label class="form-label">Specialty</label><input class="form-control" id="drSpecialty" placeholder="e.g. General Physician"></div>
            <div class="col-6"><label class="form-label">Registration No.</label><input class="form-control" id="drRegNo"></div>
          </div>
          <div class="mt-2"><label class="form-label">Phone</label><input class="form-control" id="drPhone"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="drSave"><i class="bi bi-check2 me-1"></i>Save Doctor</button>
        </div>
      </div>
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
      let rxCounts = {};

      async function loadRxCounts() {
        try {
          const res = await MF.Api.get('prescriptions.php');
          const rows = res.data ?? res;
          rxCounts = {};
          D.doctors.forEach((d) => {
            rxCounts[d.id] = rows.filter((r) => r.doctor === d.name).length;
          });
        } catch (_) { rxCounts = {}; }
      }

      function filtered() {
        const q = $('#drSearch').value.toLowerCase();
        return D.doctors.filter((d) => !q || (d.name + (d.specialty || '')).toLowerCase().includes(q));
      }

      function render() {
        const rows = filtered();
        $('#drCount').textContent = `${D.doctors.length} doctor(s) on file`;
        $('#drBody').innerHTML = rows.map((d) => `
          <tr>
            <td class="td-title">${MF.esc(d.name)}</td>
            <td>${MF.esc(d.specialty || '—')}</td>
            <td class="num">${MF.esc(d.phone || '—')}</td>
            <td class="num text-2">${MF.esc(d.regNo || '—')}</td>
            <td class="text-end num">${rxCounts[d.id] ?? 0}</td>
            <td class="text-end row-actions">
              <button class="btn btn-icon btn-light-mf" data-edit="${d.id}" title="Edit"><i class="bi bi-pencil"></i></button>
              <button class="btn btn-icon btn-light-mf text-danger" data-del="${d.id}" title="Delete"><i class="bi bi-trash"></i></button>
            </td>
          </tr>`).join('') || `<tr><td colspan="6"><div class="empty-state"><i class="bi bi-heart-pulse"></i>No doctors yet — add the first one.</div></td></tr>`;
        $('#drBody').querySelectorAll('[data-edit]').forEach((b) => b.addEventListener('click', () => openEdit(b.dataset.edit)));
        $('#drBody').querySelectorAll('[data-del]').forEach((b) => b.addEventListener('click', () => remove(b.dataset.del)));
      }

      function openEdit(id) {
        const d = id ? D.doctors.find((x) => x.id == id) : null;
        $('#drModalTitle').textContent = d ? 'Edit Doctor' : 'Add Doctor';
        $('#drId').value = d ? d.id : '';
        $('#drName').value = d ? d.name : '';
        $('#drSpecialty').value = d ? d.specialty : '';
        $('#drRegNo').value = d ? d.regNo : '';
        $('#drPhone').value = d ? d.phone : '';
        new bootstrap.Modal($('#drModal')).show();
      }
      $('#drAddBtn').addEventListener('click', () => openEdit(null));

      $('#drSave').addEventListener('click', async () => {
        const name = $('#drName').value.trim();
        if (!name) { MF.toast('Doctor name is required.', 'err', 'Validation'); return; }
        const payload = { name, specialty: $('#drSpecialty').value.trim(), regNo: $('#drRegNo').value.trim(), phone: $('#drPhone').value.trim() };
        const id = $('#drId').value;
        try {
          if (id) await MF.Api.put('doctors.php', { id, ...payload });
          else await MF.Api.post('doctors.php', payload);
          bootstrap.Modal.getInstance($('#drModal')).hide();
          MF.toast(id ? 'Doctor updated.' : 'Doctor added.', 'success');
          await MF.rehydrate();
          await loadRxCounts();
          render();
        } catch (err) {
          MF.toast(err.message || 'Could not save doctor.', 'danger');
        }
      });

      async function remove(id) {
        const d = D.doctors.find((x) => x.id == id);
        const ok = await MF.confirm({ title: `Remove ${d.name}?`, message: 'Past prescriptions/sales stay on record but will no longer show this doctor.', confirmText: 'Remove', tone: 'danger' });
        if (!ok) return;
        try {
          await MF.Api.del('doctors.php?id=' + encodeURIComponent(id));
          MF.toast('Doctor removed.', 'success');
          await MF.rehydrate();
          await loadRxCounts();
          render();
        } catch (err) {
          MF.toast(err.message || 'Could not remove doctor.', 'danger');
        }
      }

      $('#drSearch').addEventListener('input', render);

      await loadRxCounts();
      render();
    })();
    });
  </script>
</body>
</html>
