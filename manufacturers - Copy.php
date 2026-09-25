<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/core/Auth.php';
if (!Auth::check()) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manufacturers · Optms Rx</title>
<link rel="icon" href="assets/images/logo.svg">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;650;700;800&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="manufacturers">
<div class="mf-layout">
  <aside class="mf-sidebar" id="mf-sidebar"></aside>

  <div class="mf-body">
    <header class="mf-topbar" id="mf-topbar"></header>

    <main class="mf-main">
      <div class="page-head">
        <div>
          <h1 class="page-title"><i class="bi bi-buildings text-mf-primary me-1"></i>Manufacturers</h1>
          <p class="page-sub">Manage the manufacturer/brand list used across Products, Purchase Entry and reports.</p>
        </div>
        <div class="ms-auto d-flex gap-2">
          <button class="btn btn-mf" id="btnAddMfr"><i class="bi bi-plus-lg me-1"></i>Add Manufacturer</button>
        </div>
      </div>

      <div class="card-mf">
        <div class="card-head">
          <h2 class="card-title"><i class="bi bi-list-ul"></i>All Manufacturers</h2>
          <div class="card-tools">
            <div class="input-group input-group-sm" style="width:240px">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" class="form-control" id="mfrSearch" placeholder="Search manufacturers...">
            </div>
          </div>
        </div>
        <div class="table-scroll">
          <table class="table-mf table align-middle mb-0">
            <thead>
              <tr>
                <th style="width:60px">#</th>
                <th>Manufacturer</th>
                <th>Medicines</th>
                <th style="width:110px" class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="mfrTableBody">
              <tr><td colspan="4"><div class="empty-state"><i class="bi bi-hourglass-split"></i>Loading manufacturers...</div></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Add / Edit modal -->
<div class="modal fade" id="mfrModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-mf-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mfrModalTitle"><i class="bi bi-buildings me-2 text-success"></i>Add Manufacturer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="mfrId">
        <label class="form-label">Manufacturer Name <span class="req">*</span></label>
        <input type="text" class="form-control" id="mfrName" placeholder="e.g. Cipla Ltd" maxlength="120" autocomplete="off">
      </div>
      <div class="modal-footer">
        <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-mf" id="btnSaveMfr"><i class="bi bi-check-lg me-1"></i>Save</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/config.js"></script>
<script src="assets/js/data.js"></script>
<script src="assets/js/app.js"></script>
<script>
(function () {
  let manufacturers = [];
  let editing = null;
  let mfrModal;

  function rowHtml(m, i) {
    const count = Number(m.medicine_count || 0);
    return `
      <tr data-id="${m.id}">
        <td class="text-2">${i + 1}</td>
        <td class="td-title">${MF.esc(m.name)}</td>
        <td>${count > 0
          ? `<span class="badge badge-soft-primary">${MF.num(count)} medicine${count === 1 ? '' : 's'}</span>`
          : `<span class="text-2 small-xs">Unused</span>`}</td>
        <td class="text-end row-actions">
          <button class="btn btn-icon btn-light-mf btn-sm mfr-edit" title="Edit"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-icon btn-light-mf btn-sm text-danger mfr-del" title="Delete"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`;
  }

  function render(list) {
    const body = document.getElementById('mfrTableBody');
    if (!list.length) {
      body.innerHTML = `<tr><td colspan="4"><div class="empty-state"><i class="bi bi-buildings"></i>No manufacturers yet.<br><span class="small">Click "Add Manufacturer" to create the first one.</span></div></td></tr>`;
      return;
    }
    body.innerHTML = list.map(rowHtml).join('');
    body.querySelectorAll('.mfr-edit').forEach((b) => b.addEventListener('click', (e) => openEdit(e.target.closest('tr').dataset.id)));
    body.querySelectorAll('.mfr-del').forEach((b) => b.addEventListener('click', (e) => handleDelete(e.target.closest('tr').dataset.id)));
  }

  function applySearch() {
    const q = document.getElementById('mfrSearch').value.trim().toLowerCase();
    render(!q ? manufacturers : manufacturers.filter((c) => c.name.toLowerCase().includes(q)));
  }

  async function load() {
    try {
      const res = await MF.Api.get('manufacturers.php');
      manufacturers = (res.data || []).sort((a, b) => a.name.localeCompare(b.name));
    } catch (e) {
      MF.toast(e.message || 'Could not load manufacturers', 'err', 'Load failed');
      manufacturers = [];
    }
    applySearch();
  }

  function openAdd() {
    editing = null;
    document.getElementById('mfrModalTitle').innerHTML = '<i class="bi bi-buildings me-2 text-success"></i>Add Manufacturer';
    document.getElementById('mfrId').value = '';
    document.getElementById('mfrName').value = '';
    mfrModal.show();
  }

  function openEdit(id) {
    const c = manufacturers.find((x) => x.id == id);
    if (!c) return;
    editing = c;
    document.getElementById('mfrModalTitle').innerHTML = '<i class="bi bi-pencil me-2 text-success"></i>Edit Manufacturer';
    document.getElementById('mfrId').value = c.id;
    document.getElementById('mfrName').value = c.name;
    mfrModal.show();
  }

  async function save() {
    const name = document.getElementById('mfrName').value.trim();
    if (!name) { MF.toast('Manufacturer name is required', 'warn'); return; }

    const btn = document.getElementById('btnSaveMfr');
    btn.disabled = true;
    try {
      if (editing) {
        await MF.Api.put('manufacturers.php', { id: editing.id, name });
        MF.toast('Manufacturer updated', 'success');
      } else {
        await MF.Api.post('manufacturers.php', { name });
        MF.toast('Manufacturer added', 'success');
      }
      mfrModal.hide();
      await load();
    } catch (e) {
      MF.toast(e.message || 'Could not save manufacturer', 'err', 'Save failed');
    } finally {
      btn.disabled = false;
    }
  }

  async function handleDelete(id) {
    const c = manufacturers.find((x) => x.id == id);
    if (!c) return;
    const ok = await MF.confirm({
      title: `Delete "${c.name}"?`,
      message: 'This can\'t be undone.',
      confirmText: 'Delete',
      tone: 'danger',
    });
    if (!ok) return;

    try {
      await MF.Api.del('manufacturers.php?id=' + c.id);
      MF.toast('Manufacturer deleted', 'success');
      await load();
    } catch (e) {
      MF.toast(e.message || 'Could not delete manufacturer', 'err', 'Delete failed');
    }
  }

  document.addEventListener('DOMContentLoaded', async () => {
    mfrModal = new bootstrap.Modal(document.getElementById('mfrModal'));
    document.getElementById('btnAddMfr').addEventListener('click', openAdd);
    document.getElementById('btnSaveMfr').addEventListener('click', save);
    document.getElementById('mfrSearch').addEventListener('input', applySearch);
    document.getElementById('mfrName').addEventListener('keydown', (e) => { if (e.key === 'Enter') save(); });
    await MF.boot();
    await load();
  });
})();
</script>
</body>
</html>
