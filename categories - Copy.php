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
<title>Medicine Categories · Optms Rx</title>
<link rel="icon" href="assets/images/logo.svg">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;650;700;800&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="categories">
<div class="mf-layout">
  <aside class="mf-sidebar" id="mf-sidebar"></aside>

  <div class="mf-body">
    <header class="mf-topbar" id="mf-topbar"></header>

    <main class="mf-main">
      <div class="page-head">
        <div>
          <h1 class="page-title"><i class="bi bi-tags text-mf-primary me-1"></i>Medicine Categories</h1>
          <p class="page-sub">Organize medicines into categories used across Products, POS filters and reports.</p>
        </div>
        <div class="ms-auto d-flex gap-2">
          <button class="btn btn-mf" id="btnAddCategory"><i class="bi bi-plus-lg me-1"></i>Add Category</button>
        </div>
      </div>

      <div class="card-mf">
        <div class="card-head">
          <h2 class="card-title"><i class="bi bi-list-ul"></i>All Categories</h2>
          <div class="card-tools">
            <div class="input-group input-group-sm" style="width:240px">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" class="form-control" id="catSearch" placeholder="Search categories...">
            </div>
          </div>
        </div>
        <div class="table-scroll">
          <table class="table-mf table align-middle mb-0">
            <thead>
              <tr>
                <th style="width:60px">#</th>
                <th>Category</th>
                <th>Medicines</th>
                <th style="width:110px" class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="catTableBody">
              <tr><td colspan="4"><div class="empty-state"><i class="bi bi-hourglass-split"></i>Loading categories...</div></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Add / Edit modal -->
<div class="modal fade" id="catModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-mf-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="catModalTitle"><i class="bi bi-tags me-2 text-success"></i>Add Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="catId">
        <label class="form-label">Category Name <span class="req">*</span></label>
        <input type="text" class="form-control" id="catName" placeholder="e.g. Antibiotics" maxlength="120" autocomplete="off">
      </div>
      <div class="modal-footer">
        <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-mf" id="btnSaveCategory"><i class="bi bi-check-lg me-1"></i>Save</button>
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
  let categories = [];
  let editing = null;
  let catModal;

  function rowHtml(c, i) {
    const count = Number(c.medicine_count || 0);
    return `
      <tr data-id="${c.id}">
        <td class="text-2">${i + 1}</td>
        <td class="td-title">${MF.esc(c.name)}</td>
        <td>${count > 0
          ? `<span class="badge badge-soft-primary">${MF.num(count)} medicine${count === 1 ? '' : 's'}</span>`
          : `<span class="text-2 small-xs">Unused</span>`}</td>
        <td class="text-end row-actions">
          <button class="btn btn-icon btn-light-mf btn-sm cat-edit" title="Edit"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-icon btn-light-mf btn-sm text-danger cat-del" title="Delete"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`;
  }

  function render(list) {
    const body = document.getElementById('catTableBody');
    if (!list.length) {
      body.innerHTML = `<tr><td colspan="4"><div class="empty-state"><i class="bi bi-tags"></i>No categories yet.<br><span class="small">Click "Add Category" to create the first one.</span></div></td></tr>`;
      return;
    }
    body.innerHTML = list.map(rowHtml).join('');
    body.querySelectorAll('.cat-edit').forEach((b) => b.addEventListener('click', (e) => openEdit(e.target.closest('tr').dataset.id)));
    body.querySelectorAll('.cat-del').forEach((b) => b.addEventListener('click', (e) => handleDelete(e.target.closest('tr').dataset.id)));
  }

  function applySearch() {
    const q = document.getElementById('catSearch').value.trim().toLowerCase();
    render(!q ? categories : categories.filter((c) => c.name.toLowerCase().includes(q)));
  }

  async function load() {
    try {
      const res = await MF.Api.get('categories.php');
      categories = (res.data || []).sort((a, b) => a.name.localeCompare(b.name));
    } catch (e) {
      MF.toast(e.message || 'Could not load categories', 'err', 'Load failed');
      categories = [];
    }
    applySearch();
  }

  function openAdd() {
    editing = null;
    document.getElementById('catModalTitle').innerHTML = '<i class="bi bi-tags me-2 text-success"></i>Add Category';
    document.getElementById('catId').value = '';
    document.getElementById('catName').value = '';
    catModal.show();
  }

  function openEdit(id) {
    const c = categories.find((x) => x.id == id);
    if (!c) return;
    editing = c;
    document.getElementById('catModalTitle').innerHTML = '<i class="bi bi-pencil me-2 text-success"></i>Edit Category';
    document.getElementById('catId').value = c.id;
    document.getElementById('catName').value = c.name;
    catModal.show();
  }

  async function save() {
    const name = document.getElementById('catName').value.trim();
    if (!name) { MF.toast('Category name is required', 'warn'); return; }

    const btn = document.getElementById('btnSaveCategory');
    btn.disabled = true;
    try {
      if (editing) {
        await MF.Api.put('categories.php', { id: editing.id, name });
        MF.toast('Category updated', 'success');
      } else {
        await MF.Api.post('categories.php', { name });
        MF.toast('Category added', 'success');
      }
      catModal.hide();
      await load();
    } catch (e) {
      MF.toast(e.message || 'Could not save category', 'err', 'Save failed');
    } finally {
      btn.disabled = false;
    }
  }

  async function handleDelete(id) {
    const c = categories.find((x) => x.id == id);
    if (!c) return;
    const ok = await MF.confirm({
      title: `Delete "${c.name}"?`,
      message: 'This can\'t be undone.',
      confirmText: 'Delete',
      tone: 'danger',
    });
    if (!ok) return;

    try {
      await MF.Api.del('categories.php?id=' + c.id);
      MF.toast('Category deleted', 'success');
      await load();
    } catch (e) {
      MF.toast(e.message || 'Could not delete category', 'err', 'Delete failed');
    }
  }

  document.addEventListener('DOMContentLoaded', async () => {
    catModal = new bootstrap.Modal(document.getElementById('catModal'));
    document.getElementById('btnAddCategory').addEventListener('click', openAdd);
    document.getElementById('btnSaveCategory').addEventListener('click', save);
    document.getElementById('catSearch').addEventListener('input', applySearch);
    document.getElementById('catName').addEventListener('keydown', (e) => { if (e.key === 'Enter') save(); });
    await MF.boot();
    await load();
  });
})();
</script>
</body>
</html>
