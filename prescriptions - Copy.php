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
  <title>Prescriptions · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .rx-stat { background:#f8fafc; border:1px solid #e7edf4; border-radius:14px; padding:12px 14px; }
    .rx-stat span { display:block; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#6c757d; }
    .rx-stat strong { display:block; margin-top:3px; font-size:1.2rem; font-weight:750; letter-spacing:-.02em; color:#1b2430; }
    .rx-stat.accent { background:var(--mf-primary-soft); border-color:#d7ebe6; }
    .rx-ledger { border:1px solid #e3ebf4; border-radius:16px; background:#fff; overflow:hidden; box-shadow:0 1px 2px rgba(22,50,92,.04), 0 10px 28px rgba(22,50,92,.04); }
    .rx-ledger-search { display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid #e7eef6; background:#f7f8fa; flex-wrap:wrap; }
    .rx-search-box { display:flex; align-items:center; gap:8px; flex:1; min-width:220px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:0 12px; min-height:40px; transition:border-color .15s ease, box-shadow .15s ease; }
    .rx-search-box:focus-within { border-color:var(--mf-primary); box-shadow:0 0 0 3px rgba(23,107,91,.16); }
    .rx-search-box i { color:#8b9bb0; font-size:15px; }
    .rx-search-box input[type="search"] { border:0; outline:0; box-shadow:none !important; background:transparent; width:100%; padding:8px 0; font-size:.92rem; color:#1b2430; }
    .rx-search-box input::placeholder { color:#9aa8b8; }
    .rx-status { width:148px; min-height:40px; border:1px solid #e5e7eb; border-radius:10px; color:#374151; font-size:.88rem; font-weight:600; background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 16 16'%3E%3Cpath fill='%236b7280' d='M3.2 5.5 8 10.3 12.8 5.5'/%3E%3C/svg%3E") no-repeat right 12px center; padding:0 32px 0 12px; appearance:none; transition:border-color .15s ease, box-shadow .15s ease; }
    .rx-status:hover, .rx-status:focus { border-color:var(--mf-primary); box-shadow:0 0 0 3px rgba(23,107,91,.12); outline:0; }
    .rx-muted { color:#8b9bb0; font-size:.75rem; font-weight:600; }
    .rx-name { font-weight:700; color:#1b2430; }
    .rx-ledger .table-mf { margin:0; }
    .rx-ledger .table-mf thead th { background:#f7f9fc; color:#7b8798; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; border-bottom:1px solid #e7eef6; padding:12px 14px; white-space:nowrap; }
    .rx-ledger .table-mf tbody td { border-bottom:1px solid #f0f4f8; padding:13px 14px; vertical-align:middle; }
    .rx-ledger .table-mf tbody tr:hover td { background:#f4faf8; }
    .rx-ledger-foot { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:12px 16px; border-top:1px solid #e3ebf4; }
    .rx-ledger-foot .pagination { gap:4px; }
    .rx-ledger-foot .page-link { border:1px solid #e3ebf4; color:#516278; border-radius:8px; min-width:32px; text-align:center; font-weight:650; padding:.3rem .55rem; transition:background .15s ease, color .15s ease, border-color .15s ease; }
    .rx-ledger-foot .page-item.active .page-link { background:var(--mf-primary); border-color:var(--mf-primary); color:#fff; }
    .rx-ledger-foot .page-link:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .rx-ledger-note { color:#8b9bb0; font-size:.78rem; line-height:1.45; padding:10px 16px 12px; margin:0; border-top:1px solid #eef3f8; background:#fbfcfe; }
    .rx-kebab { width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; padding:0; transition:background .15s ease, color .15s ease, border-color .15s ease; }
    .btn.rx-kebab:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .rx-act-menu { min-width:196px; padding:6px; border:1px solid #e7edf4; border-radius:12px; box-shadow:0 12px 32px rgba(16,32,64,.14); }
    .rx-act-menu .dropdown-item { display:flex; align-items:center; gap:10px; font-size:.84rem; font-weight:600; border-radius:8px; padding:.48rem .65rem; transition:background .15s ease, color .15s ease; }
    .rx-act-menu .dropdown-item i { width:1.05rem; color:var(--mf-primary); }
    .rx-act-menu .dropdown-item:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); }
    .rx-modal .modal-content { border:0; border-radius:16px; }
    .rx-modal .modal-header { padding:16px 20px; border-bottom:1px solid #eef2f6; }
    .rx-modal .modal-title { font-size:1.05rem; font-weight:700; }
    .rx-modal .modal-body { padding:18px 20px 8px; }
    .rx-modal .modal-footer { border-top:1px solid #eef2f6; padding:12px 20px; }
    .rx-label { display:block; font-size:.78rem; font-weight:600; color:#374151; margin-bottom:6px; }
    .rx-label .req { color:#dc3545; }
    .rx-modal .form-control, .rx-pick-btn {
      border:1px solid #e5e7eb; border-radius:10px; min-height:40px; font-size:.9rem; color:#1b2430; background:#fff;
      transition:border-color .15s ease, box-shadow .15s ease;
    }
    .rx-modal .form-control:focus { border-color:var(--mf-primary); box-shadow:0 0 0 3px rgba(23,107,91,.16); }
    .rx-date { position:relative; }
    .rx-date i { position:absolute; right:12px; top:50%; transform:translateY(-50%); color:#6b7280; pointer-events:none; }
    .rx-date input[type="text"] { padding-right:36px; }
    .rx-date-native { position:absolute; right:4px; top:4px; width:32px; height:32px; opacity:0; cursor:pointer; }
    .rx-select { width:100%; min-height:40px; border:1px solid #e5e7eb; border-radius:10px; color:#1b2430; font-size:.9rem; background-color:#fff; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 16 16'%3E%3Cpath fill='%236b7280' d='M3.2 5.5 8 10.3 12.8 5.5'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; padding:0 32px 0 12px; appearance:none; }
    .rx-select.placeholder { color:#9aa3af; }
    .rx-select option { color:#1b2430; background:#fff; }
    .rx-select:hover, .rx-select:focus { border-color:var(--mf-primary); box-shadow:0 0 0 3px rgba(23,107,91,.16); outline:0; color:#1b2430; }
    .rx-line .rx-select { min-height:38px; font-size:.86rem; }
    .rx-lines-head { display:flex; align-items:center; justify-content:space-between; margin:18px 0 8px; }
    .rx-lines-head strong { font-size:.95rem; }
    .rx-add { border:0; background:transparent; color:#6b7280; font-weight:650; font-size:.86rem; border-radius:8px; padding:4px 8px; transition:background .15s ease, color .15s ease; }
    .rx-add:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); }
    .rx-grid { width:100%; border:1px solid #eef2f6; border-radius:12px; overflow:visible; }
    .rx-grid-head, .rx-line { display:grid; grid-template-columns:minmax(160px,1.4fr) 1fr 1fr 1fr 72px 1.1fr 36px; gap:8px; align-items:center; }
    .rx-grid-head { background:#f7f9fc; color:#8b93a0; font-size:11px; font-weight:700; letter-spacing:.06em; padding:10px 12px; border-radius:12px 12px 0 0; }
    .rx-line { padding:10px 12px; border-top:1px solid #f0f4f8; position:relative; }
    .rx-line .form-control { min-height:38px; font-size:.86rem; }
    .rx-suggest { position:absolute; z-index:30; left:0; right:0; top:calc(100% + 4px); background:#fff; border:1px solid #e7edf4; border-radius:12px; box-shadow:0 12px 32px rgba(16,32,64,.14); padding:6px; max-height:180px; overflow:auto; }
    .rx-suggest button { width:100%; border:0; background:transparent; text-align:left; border-radius:8px; padding:7px 8px; font-size:.82rem; font-weight:600; }
    .rx-suggest button:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); }
    .rx-remove { width:32px; height:32px; border:0; background:transparent; color:#9aa3af; border-radius:8px; transition:background .15s ease, color .15s ease; }
    .rx-remove:hover { background:#fdecec; color:#b02a37; }
    .rx-cancel { border:0; background:transparent; color:#374151; font-weight:650; padding:8px 12px; border-radius:8px; transition:background .15s ease, color .15s ease; }
    .rx-cancel:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); }
    .rx-save { border-radius:10px; padding:8px 14px; }
    .rx-save:hover { transform:translateY(-1px); }
    .rx-legend { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; background:#fff; border:1px solid #e7edf4; border-radius:14px; padding:12px 16px; margin-bottom:14px; }
    .rx-legend p { margin:0; color:#6b7280; font-size:.9rem; }
    .rx-legend strong { color:#1b2430; }
    .rx-legend-pills { display:flex; gap:8px; flex-wrap:wrap; }
    .rx-when { display:block; color:#8b9bb0; font-size:.75rem; font-weight:600; margin-top:2px; }
    .rx-sq { display:inline-flex; align-items:center; border:1px solid #d7ebe6; background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-radius:4px; padding:1px 6px; font-size:.72rem; font-weight:700; margin:4px 4px 0 0; }
    .rx-flow { display:inline-flex; align-items:center; border-radius:999px; padding:3px 10px; font-size:.75rem; font-weight:700; }
    .rx-flow.pending { background:#fff4e8; color:#c2410c; }
    .rx-flow.ready { background:#e8f3fb; color:#1d6fbf; }
    .rx-flow.dispensed { background:#e7f6ee; color:#178a45; }
    .rx-flow.cancelled { background:#f3f4f6; color:#6b7280; }
    .rx-acts { display:inline-flex; align-items:center; justify-content:flex-end; gap:8px; flex-wrap:wrap; }
    .rx-flow-btn { white-space:nowrap; }
    .rx-flow-btn, .rx-eye {
      border:1px solid #b7ddd4; background:#fff; color:var(--mf-primary-dark); border-radius:999px;
      font-weight:700; font-size:.8rem; padding:5px 12px; transition:background .15s ease, color .15s ease, border-color .15s ease;
    }
    .rx-flow-btn:hover { background:var(--mf-primary-soft); border-color:var(--mf-primary); color:var(--mf-primary-dark); }
    .rx-eye { width:32px; height:32px; padding:0; display:inline-flex; align-items:center; justify-content:center; color:#6b7280; border-color:#e5e7eb; }
    .rx-eye:hover { background:var(--mf-primary-soft); border-color:var(--mf-primary); color:var(--mf-primary-dark); }
    .rx-bill { border-radius:10px; padding:8px 14px; }
    .rx-bill:hover { transform:translateY(-1px); }
    .rx-check { display:flex; align-items:flex-start; gap:10px; border:1px solid #eef2f6; border-radius:10px; padding:10px 12px; margin-bottom:8px; }
    .rx-check input { width:1.05rem; height:1.05rem; margin-top:2px; accent-color:var(--mf-primary); }
    .rx-chip-row { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
    .rx-mini { display:inline-flex; align-items:center; border-radius:999px; padding:2px 8px; font-size:.72rem; font-weight:700; background:#f3f4f6; color:#4b5563; }
    .rx-mini.qty { background:var(--mf-primary-soft); color:var(--mf-primary-dark); }
    .rx-mini.in { background:#e7f6ee; color:#178a45; }
    .rx-mini.low { background:#fff4e8; color:#c2410c; }
    .rx-mini.out { background:#fdecec; color:#b42318; }
    .rx-dispense-meta { display:grid; grid-template-columns:140px 1fr; gap:8px 12px; margin-bottom:14px; }
    .rx-dispense-meta .k { color:#6b7280; }
    .rx-dispense-meta .v { font-weight:700; color:#1b2430; }
    .rx-view .modal-content { border:0; border-radius:16px; }
    .rx-view .modal-header, .rx-view .modal-footer { border-color:#eef2f6; }
    .rx-view .modal-title { font-size:1.05rem; font-weight:750; }
    .rx-facts { display:grid; grid-template-columns:158px minmax(0,1fr) 158px minmax(0,1fr); column-gap:18px; row-gap:12px; align-items:center; margin-bottom:16px; }
    .rx-facts .k { color:#6b7280; font-size:.9rem; }
    .rx-facts .v { font-weight:700; color:#1b2430; }
    .rx-facts .alone { grid-column:1 / -1; display:grid; grid-template-columns:158px minmax(0,1fr); column-gap:18px; align-items:start; }
    .rx-pill { display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:2px 10px; font-size:.78rem; font-weight:700; }
    .rx-pill i { font-size:.55rem; }
    .rx-pill.live { background:#e7f6ee; color:#178a45; }
    .rx-pill.done { background:var(--mf-primary-soft); color:var(--mf-primary-dark); }
    .rx-pill.stop { background:#fdecec; color:#b42318; }
    .rx-meds { border:1px solid #eef2f6; border-radius:12px; overflow:hidden; }
    .rx-meds table { width:100%; margin:0; border-collapse:collapse; }
    .rx-meds th { background:#f7f8fa; color:#8b93a0; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; padding:10px 12px; text-align:left; }
    .rx-meds td { padding:12px; border-top:1px solid #f0f3f6; vertical-align:middle; }
    .rx-meds .name { font-weight:750; color:#1b2430; }
    .rx-view-foot { display:flex; align-items:center; justify-content:space-between; gap:12px; width:100%; }
    .rx-view-acts { display:flex; align-items:center; gap:8px; }
    .rx-print, .rx-done, .rx-stop, .rx-view-close { border-radius:10px; font-weight:650; padding:7px 12px; transition:background .15s ease, color .15s ease, border-color .15s ease; }
    .rx-print { border:0; background:transparent; color:#6b7280; }
    .rx-print:hover { background:#f3f4f6; color:#374151; }
    .rx-done { border:1px solid #178a45; background:#fff; color:#178a45; }
    .rx-done:hover { background:#e7f6ee; color:#0f6b34; }
    .rx-stop { border:1px solid #dc3545; background:#fff; color:#dc3545; }
    .rx-stop:hover { background:#fdecec; color:#b42318; }
    .rx-view-close { border:0; background:transparent; color:#374151; }
    .rx-view-close:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); }
    @media (max-width: 760px) {
      .rx-facts, .rx-facts .alone { grid-template-columns:128px minmax(0,1fr); }
    }
    @media (max-width: 900px) {
      .rx-grid-head { display:none; }
      .rx-grid-head, .rx-line { grid-template-columns:1fr 1fr; }
    }
  </style>
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
            <p class="page-sub" id="rxCount">Recorded prescriptions. This is not a sale.</p>
          </div>
          <div class="ms-auto">
            <button class="btn btn-mf" id="rxRecord" type="button"><i class="bi bi-plus-lg me-1"></i>Record Prescription</button>
          </div>
        </div>

        <div class="row g-3 mb-3" id="rxStats"></div>

        <div class="rx-legend">
          <p><i class="bi bi-info-circle me-1"></i>Mark prescriptions <strong>Ready</strong> after packing, then <strong>Dispense</strong> at the counter</p>
          <div class="rx-legend-pills">
            <span class="rx-flow pending">Pending</span>
            <span class="rx-flow ready">Ready</span>
            <span class="rx-flow dispensed">Dispensed</span>
          </div>
        </div>

        <div class="card-mf rx-ledger">
          <div class="rx-ledger-search">
            <label class="rx-search-box">
              <i class="bi bi-search"></i>
              <input id="rxSearch" type="search" placeholder="Search Rx no, patient, mobile, doctor, diagnosis…" autocomplete="off">
            </label>
            <select class="rx-status" id="rxStatus" aria-label="All status">
              <option value="all">All status</option>
              <option value="pending">Pending</option>
              <option value="ready">Ready</option>
              <option value="dispensed">Dispensed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="table-scroll" style="max-height:none;overflow-x:auto">
            <table class="table table-mf">
              <thead>
                <tr>
                  <th>Rx no</th>
                  <th>Patient</th>
                  <th>Doctor</th>
                  <th>Diagnosis</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="rxBody"></tbody>
            </table>
          </div>
          <div class="rx-ledger-foot">
            <span class="text-2 small" id="rxPageInfo"></span>
            <ul class="pagination pagination-sm mb-0" id="rxPager"></ul>
          </div>
          <p class="rx-ledger-note">A recorded prescription is the doctor’s order. It does not deduct stock until the medicines are sold at the counter.</p>
        </div>
      </main>
    </div>
  </div>

  <div class="modal fade rx-modal" id="rxModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="rxFormTitle">Record Prescription</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="rx-label" for="rxDateText">Date</label>
              <div class="rx-date">
                <input class="form-control" id="rxDateText" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off">
                <input class="rx-date-native" id="rxDate" type="date" aria-label="Pick date">
                <i class="bi bi-calendar3"></i>
              </div>
            </div>
            <div class="col-md-4">
              <label class="rx-label" for="rxPatient">Customer / Patient <span class="req">*</span></label>
              <div class="d-flex gap-2">
                <input class="form-control" id="rxPatient" placeholder="Patient name" autocomplete="off">
                <input class="form-control" id="rxAge" inputmode="numeric" placeholder="Age" style="max-width:84px" autocomplete="off">
              </div>
            </div>
            <div class="col-md-4">
              <label class="rx-label" for="rxMobile">Mobile</label>
              <input class="form-control" id="rxMobile" inputmode="tel" placeholder="+91 ..." autocomplete="off">
            </div>
            <div class="col-md-4">
              <label class="rx-label" for="rxDoctor">Doctor</label>
              <select class="rx-select" id="rxDoctor">
                <option value="">— select doctor —</option>
              </select>
            </div>
            <div class="col-12">
              <label class="rx-label" for="rxNotes">Diagnosis / Notes</label>
              <input class="form-control" id="rxNotes" placeholder="Optional — recorded as given (demo)" autocomplete="off">
            </div>
          </div>

          <div class="rx-lines-head">
            <strong>Medicines on the prescription</strong>
            <button class="rx-add" id="rxAddLine" type="button"><i class="bi bi-plus"></i> Add line</button>
          </div>
          <div class="rx-grid">
            <div class="rx-grid-head">
              <span>Medicine</span><span>Dosage</span><span>Frequency</span><span>Duration</span><span>Qty</span><span>Instructions</span><span></span>
            </div>
            <div id="rxLines"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="rx-cancel" data-bs-dismiss="modal" type="button">Cancel</button>
          <button class="btn btn-mf rx-save" id="rxSave" type="button"><i class="bi bi-check-lg me-1"></i>Save Prescription</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade rx-modal" id="rxDispense" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Dispense</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="rxDispenseBody"></div>
        <div class="modal-footer">
          <button class="rx-cancel" data-bs-dismiss="modal" type="button">Cancel</button>
          <button class="btn btn-mf rx-bill" id="rxBillPos" type="button"><i class="bi bi-cart3 me-1"></i>Bill at POS</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade rx-view" id="rxViewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="rxViewTitle">Prescription</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="rxViewBody"></div>
        <div class="modal-footer">
          <div class="rx-view-foot">
            <button class="rx-print" id="rxPrint" type="button"><i class="bi bi-printer me-1"></i>Print Record</button>
            <div class="rx-view-acts">
              <button class="rx-flow-btn" id="rxEditView" type="button" hidden><i class="bi bi-pencil me-1"></i>Edit</button>
              <button class="rx-flow-btn" id="rxMarkReady" type="button" hidden><i class="bi bi-clipboard-check me-1"></i>Mark Ready</button>
              <button class="rx-done" id="rxComplete" type="button"><i class="bi bi-check-circle me-1"></i>Mark Completed</button>
              <button class="rx-stop" id="rxCancelRx" type="button"><i class="bi bi-x-circle me-1"></i>Cancel</button>
              <button class="rx-view-close" data-bs-dismiss="modal" type="button">Close</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
    (function () {
      const MF = window.MF, D = window.MF_DATA || {};
      const $ = (s) => document.querySelector(s);
      const state = { rows: [], q: '', status: 'all', page: 1, per: 12, doctorId: '', seq: 1, doctors: [], medicines: [], viewId: null, viewItems: [] };

      function today() {
        return MF.today ? MF.today() : new Date().toISOString().slice(0, 10);
      }

      function flowStatus(s) {
        if (!s || s === 'Recorded' || s === 'Pending') return 'Pending';
        if (s === 'Ready') return 'Ready';
        if (s === 'Dispensed' || s === 'Completed') return 'Dispensed';
        if (s === 'Cancelled') return 'Cancelled';
        return 'Pending';
      }

      function statusBadge(s) {
        const label = flowStatus(s);
        return `<span class="rx-flow ${label.toLowerCase()}">${MF.esc(label)}</span>`;
      }

      function clock(row) {
        const date = row.rx_date ? MF.fmtDate(row.rx_date) : '';
        const created = String(row.created_at || '');
        const m = created.match(/(\d{2}):(\d{2})/);
        let time = '';
        if (m) {
          let h = +m[1];
          const ap = h >= 12 ? 'pm' : 'am';
          h = h % 12 || 12;
          time = h + ':' + m[2] + ap;
        }
        if (date && time) return date + ' · ' + time;
        return date || '—';
      }

      function medNames(row) {
        const list = row.medicines || row.items || [];
        return list.map((m) => m.medicine_name || m.name).filter(Boolean);
      }

      function liveData() { return window.MF_DATA || D || {}; }

      function asList(res) {
        const data = res && res.data;
        if (Array.isArray(data)) return data;
        if (data && Array.isArray(data.doctors)) return data.doctors;
        if (data && Array.isArray(data.medicines)) return data.medicines;
        if (data && Array.isArray(data.ledger)) return data.ledger;
        return [];
      }

      function normalizeDoctor(d) {
        return { id: d.id, name: d.name || '', specialty: d.specialty || '', status: d.status || 'Active' };
      }

      function normalizeMedicine(m) {
        return {
          id: m.id,
          name: m.name || m.medicine_name || '',
          generic: m.generic || m.generic_name || '',
          unit: m.unit || m.pack_unit || 'pack',
          status: m.status || 'Active',
          minStock: Number(m.minStock ?? m.min_stock ?? m.reorderLevel ?? 0) || 0,
          stock: m.stock ?? m.qty_on_hand ?? m.quantity ?? null
        };
      }

      function doctors() { return state.doctors.length ? state.doctors : (liveData().doctors || []).map(normalizeDoctor).filter((d) => d.name); }
      function medicines() { return state.medicines.length ? state.medicines : (liveData().medicines || []).map(normalizeMedicine).filter((m) => m.name); }

      async function loadCatalogs() {
        const bag = liveData();
        let doctorRows = (bag.doctors || []).map(normalizeDoctor);
        let medicineRows = (bag.medicines || []).map(normalizeMedicine);
        if (MF.Api && MF.Api.live) {
          try {
            const res = await MF.Api.get('doctors.php');
            const rows = asList(res);
            if (rows.length) doctorRows = rows.map(normalizeDoctor);
          } catch (e) { /* keep bootstrap doctors */ }
          try {
            const res = await MF.Api.get('medicines.php');
            const rows = asList(res);
            if (rows.length) medicineRows = rows.map(normalizeMedicine);
          } catch (e) { /* keep bootstrap medicines */ }
        }
        doctorRows = doctorRows.filter((d) => d.name);
        const active = doctorRows.filter((d) => d.status !== 'Inactive');
        state.doctors = (active.length ? active : doctorRows).sort((a, b) => a.name.localeCompare(b.name));
        state.medicines = medicineRows.filter((m) => m.name).sort((a, b) => a.name.localeCompare(b.name));
        fillDoctorMenu();
        fillMedicineSelects();
      }

      function filtered() {
        const q = state.q.toLowerCase();
        return state.rows.filter((r) => {
          if (state.status !== 'all' && flowStatus(r.status).toLowerCase() !== state.status) return false;
          if (!q) return true;
          return [r.rx_no, r.patient_name, r.patient_phone, r.doctor_name, r.diagnosis, r.specialty].join(' ').toLowerCase().includes(q);
        });
      }

      function pageButtons(pages, current) {
        const want = new Set([1, pages, current - 1, current, current + 1]);
        const nums = [...want].filter((n) => n >= 1 && n <= pages).sort((a, b) => a - b);
        let html = '';
        let prev = 0;
        nums.forEach((n) => {
          if (n - prev > 1) html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
          html += `<li class="page-item ${n === current ? 'active' : ''}"><button class="page-link" type="button" data-pg="${n}">${n}</button></li>`;
          prev = n;
        });
        return html;
      }

      function render() {
        const list = filtered();
        const pages = Math.max(1, Math.ceil(list.length / state.per));
        state.page = Math.min(state.page, pages);
        const slice = list.slice((state.page - 1) * state.per, state.page * state.per);
        const pending = state.rows.filter((r) => flowStatus(r.status) === 'Pending').length;
        const ready = state.rows.filter((r) => flowStatus(r.status) === 'Ready').length;
        $('#rxCount').textContent = `${state.rows.length} prescription${state.rows.length === 1 ? '' : 's'} · pack, then dispense at the counter`;
        $('#rxStats').innerHTML = `
          <div class="col-6 col-md-3"><div class="rx-stat accent"><span>Prescriptions</span><strong>${MF.num(state.rows.length)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="rx-stat"><span>Pending</span><strong>${MF.num(pending)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="rx-stat"><span>Ready</span><strong>${MF.num(ready)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="rx-stat"><span>Dispensed</span><strong>${MF.num(state.rows.filter((r) => flowStatus(r.status) === 'Dispensed').length)}</strong></div></div>`;
        $('#rxBody').innerHTML = slice.map((r) => {
          const flow = flowStatus(r.status);
          const names = medNames(r);
          const age = r.patient_age ? `<span class="rx-when">${MF.esc(r.patient_age)} yrs</span>` : '<span class="rx-when">Age —</span>';
          const phone = r.patient_phone ? `<span class="rx-when">${MF.esc(r.patient_phone)}</span>` : '';
          const edit = flow === 'Pending'
            ? `<button type="button" class="rx-flow-btn" data-a="edit" data-id="${MF.esc(r.id)}"><i class="bi bi-pencil me-1"></i>Edit</button>`
            : '';
          const action = flow === 'Pending'
            ? `<button type="button" class="rx-flow-btn" data-a="ready" data-id="${MF.esc(r.id)}"><i class="bi bi-clipboard-check me-1"></i>Mark Ready</button>`
            : flow === 'Ready'
              ? `<button type="button" class="rx-flow-btn" data-a="dispense" data-id="${MF.esc(r.id)}"><i class="bi bi-check2 me-1"></i>Dispense</button>`
              : '';
          return `<tr>
          <td><span class="rx-name num">${MF.esc(r.rx_no || '—')}</span><span class="rx-when">${MF.esc(clock(r))}</span></td>
          <td><span class="rx-name">${MF.esc(r.patient_name || '—')}</span>${age}${phone}</td>
          <td>${r.doctor_name ? MF.esc(r.doctor_name) : '<span class="text-2">—</span>'}${r.specialty ? `<span class="rx-when">${MF.esc(r.specialty)}</span>` : ''}</td>
          <td>${r.diagnosis ? MF.esc(r.diagnosis) : '<span class="text-2">—</span>'}${names.length ? `<div>${names.map((n) => `<span class="rx-sq">${MF.esc(n)}</span>`).join('')}</div>` : ''}</td>
          <td>${statusBadge(r.status)}</td>
          <td class="text-end"><div class="rx-acts">${edit}${action}<button type="button" class="rx-eye" data-a="view" data-id="${MF.esc(r.id)}" aria-label="View"><i class="bi bi-eye"></i></button></div></td>
        </tr>`;
        }).join('') || '<tr><td colspan="6"><div class="empty-state"><i class="bi bi-file-medical"></i>No prescriptions yet. Record one to start the ledger.</div></td></tr>';
        const start = slice.length ? (state.page - 1) * state.per + 1 : 0;
        $('#rxPageInfo').textContent = `Showing ${start}–${(state.page - 1) * state.per + slice.length} of ${list.length}`;
        $('#rxPager').innerHTML = pageButtons(pages, state.page);
        $('#rxPager').querySelectorAll('[data-pg]').forEach((b) => b.addEventListener('click', () => { state.page = +b.dataset.pg; render(); }));
        $('#rxBody').querySelectorAll('[data-a]').forEach((b) => b.addEventListener('click', () => onAction(b.dataset.a, b.dataset.id)));
      }

      function medicineOptions(selected) {
        const list = medicines();
        const empty = list.length ? '— select medicine —' : 'No medicines found';
        return `<option value="">${empty}</option>` + list.map((m) => `<option value="${MF.esc(m.id)}" data-name="${MF.esc(m.name)}"${String(selected || '') === String(m.id) ? ' selected' : ''}>${MF.esc(m.name)}${m.generic ? ' — ' + MF.esc(m.generic) : ''}</option>`).join('');
      }

      function lineHtml() {
        return `<div class="rx-line">
          <select class="rx-select rx-med placeholder" aria-label="Medicine">${medicineOptions()}</select>
          <input class="form-control rx-dose" placeholder="e.g. 1 tablet" autocomplete="off">
          <input class="form-control rx-freq" placeholder="e.g. Twice daily" autocomplete="off">
          <input class="form-control rx-dur" placeholder="e.g. 5 days" autocomplete="off">
          <input class="form-control rx-qty text-center" value="1" inputmode="numeric" autocomplete="off">
          <input class="form-control rx-note" placeholder="e.g. After food" autocomplete="off">
          <button class="rx-remove" type="button" aria-label="Remove line"><i class="bi bi-x-lg"></i></button>
        </div>`;
      }

      function bindLine(line) {
        const sel = line.querySelector('.rx-med');
        sel.addEventListener('change', () => sel.classList.toggle('placeholder', !sel.value));
        line.querySelector('.rx-remove').addEventListener('click', () => {
          const wrap = $('#rxLines');
          if (wrap.children.length === 1) {
            sel.value = '';
            sel.classList.add('placeholder');
            line.querySelectorAll('.rx-dose,.rx-freq,.rx-dur,.rx-note').forEach((el) => { el.value = ''; });
            line.querySelector('.rx-qty').value = '1';
            return;
          }
          line.remove();
        });
      }

      function fillMedicineSelects() {
        document.querySelectorAll('#rxLines .rx-med').forEach((sel) => {
          const current = sel.value;
          sel.innerHTML = medicineOptions(current);
          sel.classList.toggle('placeholder', !sel.value);
        });
      }

      function addLine() {
        const wrap = $('#rxLines');
        wrap.insertAdjacentHTML('beforeend', lineHtml());
        bindLine(wrap.lastElementChild);
      }

      function isoToDisplay(iso) {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
        return m ? m[3] + '-' + m[2] + '-' + m[1] : '';
      }

      function displayToIso(text) {
        const m = /^(\d{2})-(\d{2})-(\d{4})$/.exec((text || '').trim());
        if (!m) return '';
        const iso = m[3] + '-' + m[2] + '-' + m[1];
        const d = new Date(iso + 'T00:00:00');
        if (Number.isNaN(d.getTime()) || d.getMonth() + 1 !== +m[2]) return '';
        return iso;
      }

      function setDate(iso) {
        $('#rxDate').value = iso || '';
        $('#rxDateText').value = isoToDisplay(iso);
      }

      function resetForm() {
        state.editId = null;
        $('#rxFormTitle').textContent = 'Record Prescription';
        $('#rxSave').innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Prescription';
        setDate(today());
        $('#rxPatient').value = '';
        $('#rxAge').value = '';
        $('#rxMobile').value = '';
        $('#rxNotes').value = '';
        state.doctorId = '';
        $('#rxDoctor').value = '';
        $('#rxDoctor').classList.add('placeholder');
        $('#rxLines').innerHTML = '';
        addLine();
      }

      function fillDoctorMenu() {
        const sel = $('#rxDoctor');
        const current = sel.value;
        const list = doctors();
        const empty = list.length ? '— select doctor —' : 'No doctors found';
        sel.innerHTML = `<option value="">${empty}</option>` + list.map((d) => `<option value="${MF.esc(d.id)}">${MF.esc(d.name)}${d.specialty ? ' — ' + MF.esc(d.specialty) : ''}</option>`).join('');
        if (current && [...sel.options].some((o) => o.value === current)) sel.value = current;
        sel.classList.toggle('placeholder', !sel.value);
      }

      function collectItems() {
        return [...$('#rxLines').querySelectorAll('.rx-line')].map((line) => {
          const sel = line.querySelector('.rx-med');
          const opt = sel.options[sel.selectedIndex];
          return {
          medicine_id: sel.value && !String(sel.value).startsWith('kept:') ? sel.value : null,
          medicine_name: opt ? (opt.getAttribute('data-name') || '') : '',
          dosage: line.querySelector('.rx-dose').value.trim(),
          frequency: line.querySelector('.rx-freq').value.trim(),
          duration: line.querySelector('.rx-dur').value.trim(),
          qty: Math.max(1, parseInt(line.querySelector('.rx-qty').value, 10) || 1),
          instructions: line.querySelector('.rx-note').value.trim()
        };
        }).filter((l) => l.medicine_name);
      }

      function customerIdFor(name) {
        const hit = (liveData().customers || []).find((c) => String(c.name).toLowerCase() === name.toLowerCase());
        return hit ? hit.id : null;
      }

      async function save() {
        const patient = $('#rxPatient').value.trim();
        const items = collectItems();
        if (!patient) { MF.toast('Patient name is required.', 'warn', 'Prescription'); $('#rxPatient').focus(); return; }
        if (!items.length) { MF.toast('Add at least one medicine.', 'warn', 'Prescription'); return; }
        const date = displayToIso($('#rxDateText').value) || $('#rxDate').value || today();
        if (!displayToIso($('#rxDateText').value) && !$('#rxDate').value) {
          MF.toast('Enter the date as DD-MM-YYYY.', 'warn', 'Prescription');
          $('#rxDateText').focus();
          return;
        }
        const payload = {
          date: date,
          patient_name: patient,
          patient_age: parseInt($('#rxAge').value, 10) || null,
          patient_phone: $('#rxMobile').value.trim(),
          customer_id: customerIdFor(patient),
          doctor_id: $('#rxDoctor').value || null,
          diagnosis: $('#rxNotes').value.trim(),
          items
        };
        if (MF.Api.live) {
          if (state.editId) {
            payload.id = state.editId;
            await MF.Api.put('prescriptions.php', payload);
            MF.toast('Prescription updated', 'success', 'Saved');
          } else {
            const res = await MF.Api.post('prescriptions.php', payload);
            MF.toast(`${res.rx_no || 'Prescription'} recorded`, 'success', 'Saved');
          }
          bootstrap.Modal.getInstance($('#rxModal')).hide();
          await load();
          return;
        }
        const doctor = doctors().find((d) => String(d.id) === String($('#rxDoctor').value));
        const existing = state.editId ? state.rows.find((r) => String(r.id) === String(state.editId)) : null;
        if (existing) {
          existing.rx_date = payload.date;
          existing.patient_name = patient;
          existing.patient_age = payload.patient_age;
          existing.patient_phone = payload.patient_phone;
          existing.doctor_id = payload.doctor_id;
          existing.doctor_name = doctor ? doctor.name : '';
          existing.specialty = doctor ? (doctor.specialty || '') : '';
          existing.diagnosis = payload.diagnosis;
          existing.item_count = items.length;
          existing.medicines = items;
          existing.items = items;
          MF.toast('Prescription updated', 'success', 'Saved');
        } else {
          state.rows.unshift({
            id: 'demo-' + state.seq,
            rx_no: 'RX-' + String(state.seq).padStart(5, '0'),
            rx_date: payload.date,
            patient_name: patient,
            patient_age: payload.patient_age,
            patient_phone: payload.patient_phone,
            doctor_id: payload.doctor_id,
            doctor_name: doctor ? doctor.name : '',
            specialty: doctor ? (doctor.specialty || '') : '',
            diagnosis: payload.diagnosis,
            status: 'Pending',
            created_at: new Date().toISOString().slice(0, 16).replace('T', ' '),
            item_count: items.length,
            medicines: items,
            items
          });
          state.seq += 1;
          MF.toast('Saved in this browser only. Run the migration to store it.', 'info', 'Demo');
        }
        bootstrap.Modal.getInstance($('#rxModal')).hide();
        render();
      }

      async function setStatus(row, status) {
        if (MF.Api.live && !String(row.id).startsWith('demo-')) {
          await MF.Api.put('prescriptions.php', { id: row.id, status });
          await load();
        } else {
          row.status = status;
          render();
        }
        const fresh = state.rows.find((r) => String(r.id) === String(row.id)) || row;
        fresh.status = status;
        return fresh;
      }

      function findMedicine(item) {
        const id = item.medicine_id || item.medicineId;
        const name = String(item.medicine_name || item.name || '').toLowerCase();
        const lists = [medicines(), liveData().medicines || []];
        for (const list of lists) {
          const hit = list.find((m) => (id && String(m.id) === String(id)) || (name && String(m.name || m.medicine_name || '').toLowerCase() === name));
          if (hit) return hit;
        }
        return null;
      }

      function stockDetail(item) {
        const med = findMedicine(item);
        const qty = Math.max(0, Number(item.qty) || 0);
        const unit = (med && (med.unit || med.pack_unit)) || 'pack';
        const id = (med && med.id) || item.medicine_id || item.medicineId;
        let stock = null;
        if (id && MF.stockOf) {
          try { stock = MF.stockOf(id); } catch (e) { stock = null; }
        }
        if ((stock == null || Number.isNaN(stock)) && med && med.stock != null && med.stock !== '') stock = Number(med.stock);
        const min = med ? Number(med.minStock || med.reorderLevel || 0) : 0;
        let label = 'Stock unavailable';
        let tone = 'unk';
        if (med && String(med.status || '').toLowerCase() === 'inactive') {
          label = 'Inactive';
          tone = 'out';
        } else if (stock != null && !Number.isNaN(stock)) {
          if (stock <= 0) { label = 'Out of stock'; tone = 'out'; }
          else if (stock < qty || (min && stock <= min)) { label = stock < qty ? 'Short stock' : 'Low stock'; tone = 'low'; }
          else { label = 'In stock'; tone = 'in'; }
        }
        const stockText = stock == null || Number.isNaN(stock) ? '—' : MF.num(stock) + ' ' + unit;
        return `<span class="rx-chip-row"><span class="rx-mini qty">Qty ${MF.num(qty || 1)}</span><span class="rx-mini">Stock ${MF.esc(stockText)}</span><span class="rx-mini ${tone}">${MF.esc(label)}</span></span>`;
      }

      async function openDispense(row) {
        let items = row.medicines && row.medicines.length ? row.medicines : (row.items || []);
        if (MF.Api.live && !items.length && !String(row.id).startsWith('demo-')) {
          try {
            const res = await MF.Api.get('prescriptions.php?id=' + encodeURIComponent(row.id));
            items = ((res.data || {}).items) || row.medicines || [];
          } catch (e) { MF.toast(e.message, 'err', 'Could not load medicines'); }
        }
        try { await loadCatalogs(); } catch (e) { /* stock still uses bootstrap batches */ }
        state.dispenseId = row.id;
        $('#rxDispenseBody').innerHTML = `
          <div class="rx-dispense-meta">
            <div class="k">Patient</div><div class="v">${MF.esc(row.patient_name || '—')}${row.patient_age ? ' · ' + MF.esc(row.patient_age) + ' yrs' : ''}${row.patient_phone ? ' · ' + MF.esc(row.patient_phone) : ''}</div>
            <div class="k">Doctor</div><div class="v">${MF.esc(row.doctor_name || '—')}</div>
            <div class="k">Date and time</div><div class="v">${MF.esc(clock(row))}</div>
            <div class="k">Diagnosis</div><div class="v">${MF.esc(row.diagnosis || '—')}</div>
          </div>
          <div class="rx-label">Medicines</div>
          ${(items.length ? items : [{ medicine_name: 'No medicines recorded', qty: 0 }]).map((l, i) => `<label class="rx-check">
            <input type="checkbox" class="rx-pick" data-i="${i}" ${l.medicine_name && l.qty !== 0 ? 'checked' : 'disabled'}>
            <span><strong>${MF.esc(l.medicine_name || l.name || '—')}</strong><span class="rx-when">${[l.dosage, l.frequency, l.duration].filter(Boolean).map((bit) => MF.esc(bit)).join(' · ')}</span>${stockDetail(l)}</span>
          </label>`).join('')}`;
        $('#rxDispenseBody').dataset.items = JSON.stringify(items);
        bootstrap.Modal.getOrCreateInstance($('#rxDispense')).show();
      }

      async function billAtPos() {
        const row = state.rows.find((r) => String(r.id) === String(state.dispenseId));
        if (!row) return;
        let items = [];
        try { items = JSON.parse($('#rxDispenseBody').dataset.items || '[]'); } catch (e) { items = []; }
        const picked = [...document.querySelectorAll('#rxDispenseBody .rx-pick:checked')].map((el) => items[+el.dataset.i]).filter(Boolean);
        if (!picked.length) { MF.toast('Select at least one medicine to bill.', 'warn', 'Dispense'); return; }
        const bill = {
          rxId: row.id,
          rxNo: row.rx_no,
          customerId: row.customer_id || null,
          doctorId: row.doctor_id || null,
          doctorName: row.doctor_name || '',
          date: row.rx_date || '',
          patient: row.patient_name || '',
          items: picked.map((l) => ({
            medicineId: l.medicine_id || l.medicineId || null,
            name: l.medicine_name || l.name || '',
            qty: Math.max(1, Number(l.qty) || 1)
          }))
        };
        sessionStorage.setItem('mf-rx-bill', JSON.stringify(bill));
        await setStatus(row, 'Dispensed');
        location.href = 'retail-pos.php';
      }

      async function openEdit(row) {
        if (flowStatus(row.status) !== 'Pending') return;
        const view = bootstrap.Modal.getInstance($('#rxViewModal'));
        const viewOpen = $('#rxViewModal').classList.contains('show');
        if (view) view.hide();
        let items = row.items && row.items.length ? row.items : [];
        if (MF.Api.live && !String(row.id).startsWith('demo-')) {
          try {
            const res = await MF.Api.get('prescriptions.php?id=' + encodeURIComponent(row.id));
            const data = res.data || {};
            if (data.prescription) Object.assign(row, data.prescription);
            items = data.items || items;
          } catch (e) { MF.toast(e.message, 'err', 'Could not load prescription'); }
        }
        if (!items.length) items = row.medicines || [];
        resetForm();
        state.editId = row.id;
        $('#rxFormTitle').textContent = 'Edit Prescription';
        $('#rxSave').innerHTML = '<i class="bi bi-check-lg me-1"></i>Update Prescription';
        await loadCatalogs();
        setDate(row.rx_date || today());
        $('#rxPatient').value = row.patient_name || '';
        $('#rxAge').value = row.patient_age || '';
        $('#rxMobile').value = row.patient_phone || '';
        $('#rxNotes').value = row.diagnosis || '';
        fillDoctorMenu();
        if (row.doctor_id) {
          $('#rxDoctor').value = String(row.doctor_id);
          $('#rxDoctor').classList.remove('placeholder');
          state.doctorId = String(row.doctor_id);
        }
        $('#rxLines').innerHTML = '';
        (items.length ? items : [{}]).forEach((item) => {
          addLine();
          const line = $('#rxLines').lastElementChild;
          const sel = line.querySelector('.rx-med');
          const id = item.medicine_id || item.medicineId || '';
          const name = item.medicine_name || item.name || '';
          if (id && ![...sel.options].some((o) => o.value === String(id))) {
            sel.insertAdjacentHTML('beforeend', `<option value="${MF.esc(id)}" data-name="${MF.esc(name)}">${MF.esc(name || 'Medicine')}</option>`);
          }
          if (id) sel.value = String(id);
          else if (name) {
            sel.insertAdjacentHTML('beforeend', `<option value="${MF.esc('kept:' + name)}" data-name="${MF.esc(name)}" selected>${MF.esc(name)}</option>`);
            sel.value = 'kept:' + name;
          }
          sel.classList.toggle('placeholder', !sel.value);
          line.querySelector('.rx-dose').value = item.dosage || '';
          line.querySelector('.rx-freq').value = item.frequency || '';
          line.querySelector('.rx-dur').value = item.duration || '';
          line.querySelector('.rx-qty').value = item.qty || 1;
          line.querySelector('.rx-note').value = item.instructions || '';
        });
        const open = () => bootstrap.Modal.getOrCreateInstance($('#rxModal')).show();
        if (viewOpen) setTimeout(open, 220);
        else open();
      }

      async function onAction(action, id) {
        const row = state.rows.find((r) => String(r.id) === String(id));
        if (!row) return;
        if (action === 'view') return openView(row);
        if (action === 'edit') return openEdit(row);
        if (action === 'dispense') return openDispense(row);
        if (action === 'ready') {
          await setStatus(row, 'Ready');
          MF.toast(row.rx_no + ' marked ready', 'success', 'Packed');
          return;
        }
        const status = action === 'dispensed' ? 'Dispensed' : 'Cancelled';
        if (MF.Api.live && !String(id).startsWith('demo-')) {
          await MF.Api.put('prescriptions.php', { id, status });
          await load();
        } else {
          row.status = status;
          render();
        }
        MF.toast(row.rx_no + ' marked ' + status.toLowerCase(), 'success', 'Updated');
        if (String(state.viewId) === String(id) && $('#rxViewModal').classList.contains('show')) {
          const fresh = state.rows.find((r) => String(r.id) === String(id)) || row;
          fresh.status = status;
          await openView(fresh);
        }
      }

      function viewStatus(status) {
        const label = flowStatus(status);
        if (label === 'Dispensed') return { label: 'Dispensed', tone: 'done' };
        if (label === 'Cancelled') return { label: 'Cancelled', tone: 'stop' };
        if (label === 'Ready') return { label: 'Ready', tone: 'live' };
        return { label: 'Pending', tone: 'live' };
      }

      function statusPill(status) {
        const s = viewStatus(status);
        return `<span class="rx-pill ${s.tone}"><i class="bi bi-circle-fill"></i>${MF.esc(s.label)}</span>`;
      }

      function fact(label, value) {
        return `<div class="k">${label}</div><div class="v">${value}</div>`;
      }

      function medicineTable(items) {
        const rows = (items || []).map((l, i) => `<tr>
          <td class="num">${i + 1}</td>
          <td class="name">${MF.esc(l.medicine_name || l.name || '—')}</td>
          <td>${MF.esc(l.dosage || '—')}</td>
          <td>${MF.esc(l.frequency || '—')}</td>
          <td>${MF.esc(l.duration || '—')}</td>
          <td class="num">${MF.num(l.qty || 1)}</td>
          <td>${MF.esc(l.instructions || '—')}</td>
        </tr>`).join('');
        return `<div class="rx-meds"><table>
          <thead><tr><th>#</th><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Qty</th><th>Instructions</th></tr></thead>
          <tbody>${rows || '<tr><td colspan="7"><div class="empty-state"><i class="bi bi-capsule"></i>No medicines on this prescription.</div></td></tr>'}</tbody>
        </table></div>`;
      }

      function storeInfo() {
        const s = (window.MF_DATA && window.MF_DATA.store) || {};
        return {
          name: s.name || 'Optms Rx',
          address: s.address || 'Madhepura, Bihar',
          gstin: s.gstin || ''
        };
      }

      function printRecord(row, items) {
        const shop = storeInfo();
        const shown = viewStatus(row.status);
        const gst = shop.gstin ? ' • GSTIN ' + shop.gstin : '';
        const lines = (items || []).map((l, i) => `<tr>
          <td>${i + 1}</td>
          <td>${MF.esc(l.medicine_name || l.name || '—')}</td>
          <td>${MF.esc(l.dosage || '—')}</td>
          <td>${MF.esc(l.frequency || '—')}</td>
          <td>${MF.esc(l.duration || '—')}</td>
          <td>${MF.num(l.qty || 1)}</td>
          <td>${MF.esc(l.instructions || '—')}</td>
        </tr>`).join('');
        const html = `<style>
          .rx-sheet { color:#111; font-size:13px; }
          .rx-sheet-top { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:16px; }
          .rx-sheet-top h2 { font-size:18px; margin:0 0 2px; font-weight:750; }
          .rx-sheet-top .sub { color:#4b5563; font-size:12px; }
          .rx-sheet-top .kind { font-size:14px; font-weight:800; letter-spacing:.04em; }
          .rx-sheet-meta { display:grid; grid-template-columns:110px 1fr 90px 1fr; gap:8px 12px; align-items:baseline; }
          .rx-sheet-meta .k { color:#374151; }
          .rx-sheet-meta .v { font-weight:700; }
          .rx-sheet-notes { grid-column:1 / -1; display:grid; grid-template-columns:140px 1fr; gap:12px; border-bottom:1px solid #111; padding:6px 0 8px; margin-top:2px; }
          .rx-sheet table { width:100%; border-collapse:collapse; margin-top:12px; }
          .rx-sheet th, .rx-sheet td { border-bottom:1px solid #d1d5db; padding:7px 8px; text-align:left; font-size:12.5px; }
          .rx-sheet th { font-weight:750; }
          .rx-sheet .fine { color:#4b5563; font-size:12px; margin:14px 0 6px; }
          .rx-sheet .printed { font-size:12.5px; }
        </style>
        <div class="rx-sheet">
          <div class="rx-sheet-top">
            <div>
              <h2>${MF.esc(shop.name)}</h2>
              <div class="sub">${MF.esc(shop.address)}${MF.esc(gst)}</div>
            </div>
            <div class="kind">PRESCRIPTION RECORD</div>
          </div>
          <div class="rx-sheet-meta">
            <div class="k">Rx No.</div><div class="v">${MF.esc(row.rx_no || '—')}</div>
            <div class="k">Date</div><div class="v">${row.rx_date ? MF.fmtDate(row.rx_date) : '—'}</div>
            <div class="k">Patient</div><div class="v">${MF.esc(row.patient_name || '—')}${row.patient_phone ? '<div style="font-weight:500">' + MF.esc(row.patient_phone) + '</div>' : ''}</div>
            <div class="k">Doctor</div><div class="v">${MF.esc(row.doctor_name || '—')}</div>
            <div class="rx-sheet-notes"><div class="k">Diagnosis / Notes</div><div class="v">${MF.esc(row.diagnosis || '—')}</div></div>
          </div>
          <table>
            <thead><tr><th>#</th><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Qty</th><th>Instructions</th></tr></thead>
            <tbody>${lines || '<tr><td colspan="7">No medicines recorded.</td></tr>'}</tbody>
          </table>
          <p class="fine">This is a pharmacy record of prescription information. It is not a substitute for professional medical advice.</p>
          <div class="printed">Status: ${MF.esc(shown.label)} • Printed ${MF.fmtDate(today())}</div>
        </div>`;
        showPrint(html);
      }

      function showPrint(html) {
        if (MF.printFrame && MF.printHtml) {
          MF.printHtml(html);
          return;
        }
        let host = document.getElementById('mf-print-root');
        if (!host) {
          host = document.createElement('div');
          host.id = 'mf-print-root';
          document.body.appendChild(host);
        }
        host.innerHTML = `<div class="modal fade" tabindex="-1" style="z-index:1085">
          <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header"><h5 class="modal-title">Print Preview</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
              <div class="modal-body bg-light"><div class="bg-white border rounded p-4">${html}</div></div>
              <div class="modal-footer">
                <button class="btn btn-light-mf" data-bs-dismiss="modal" type="button">Close</button>
                <button class="btn btn-mf" type="button" id="rxDoPrint">Print</button>
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
        host.querySelector('#rxDoPrint').addEventListener('click', () => printFrame(html));
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
      }

      function printFrame(html) {
        if (MF.printFrame) { MF.printFrame(html); return; }
        let frame = document.getElementById('mf-print-frame');
        if (!frame) {
          frame = document.createElement('iframe');
          frame.id = 'mf-print-frame';
          frame.style.cssText = 'position:fixed;width:0;height:0;border:0;left:0;top:0';
          document.body.appendChild(frame);
        }
        const doc = frame.contentDocument || frame.contentWindow.document;
        doc.open();
        doc.write('<!doctype html><html><head><meta charset="utf-8"><title>Prescription</title><style>body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:18px;}table{width:100%;border-collapse:collapse;}@page{margin:14mm;}</style></head><body>' + html + '</body></html>');
        doc.close();
        setTimeout(() => { frame.contentWindow.focus(); frame.contentWindow.print(); }, 60);
      }

      async function openView(row) {
        let items = row.items || [];
        if (MF.Api.live && !items.length && !String(row.id).startsWith('demo-')) {
          try {
            const res = await MF.Api.get('prescriptions.php?id=' + encodeURIComponent(row.id));
            items = ((res.data || {}).items) || [];
          } catch (e) { MF.toast(e.message, 'err', 'Could not load prescription'); }
        }
        state.viewId = row.id;
        state.viewItems = items;
        const pending = flowStatus(row.status) === 'Pending';
        $('#rxViewTitle').textContent = 'Prescription — ' + (row.rx_no || '—');
        $('#rxViewBody').innerHTML = `
          <div class="rx-facts">
            ${fact('Prescription No.', MF.esc(row.rx_no || '—'))}
            ${fact('Date', row.rx_date ? MF.fmtDate(row.rx_date) : '—')}
            ${fact('Customer / Patient', MF.esc(row.patient_name || '—') + (row.patient_age ? '<div class="text-2 small">' + MF.esc(row.patient_age) + ' yrs</div>' : '') + (row.patient_phone ? '<div class="text-2 small">' + MF.esc(row.patient_phone) + '</div>' : ''))}
            ${fact('Doctor', MF.esc(row.doctor_name || '—'))}
            ${fact('Status', statusPill(row.status))}
            <div class="alone"><div class="k">Diagnosis / Notes</div><div class="v">${MF.esc(row.diagnosis || '—')}</div></div>
          </div>
          ${medicineTable(items)}`;
        $('#rxEditView').hidden = !pending;
        $('#rxMarkReady').hidden = !pending;
        $('#rxComplete').hidden = true;
        $('#rxCancelRx').hidden = row.status === 'Cancelled';
        bootstrap.Modal.getOrCreateInstance($('#rxViewModal')).show();
      }

      async function load() {
        if (!MF.Api.live) { render(); return; }
        try {
          const res = await MF.Api.get('prescriptions.php');
          state.rows = ((res.data || {}).ledger || []).map((r) => r);
        } catch (e) {
          MF.toast(e.message, 'err', 'Could not load prescriptions');
          state.rows = [];
        }
        render();
      }

      document.addEventListener('DOMContentLoaded', async () => {
        await MF.boot();
        resetForm();
        await loadCatalogs();
        $('#rxRecord').addEventListener('click', () => {
          resetForm();
          loadCatalogs().then(() => bootstrap.Modal.getOrCreateInstance($('#rxModal')).show()).catch(() => bootstrap.Modal.getOrCreateInstance($('#rxModal')).show());
        });
        $('#rxDoctor').addEventListener('change', () => {
          state.doctorId = $('#rxDoctor').value;
          $('#rxDoctor').classList.toggle('placeholder', !state.doctorId);
        });
        $('#rxDate').addEventListener('change', () => setDate($('#rxDate').value));
        $('#rxDateText').addEventListener('change', () => {
          const iso = displayToIso($('#rxDateText').value);
          if (iso) setDate(iso);
        });
        $('#rxAddLine').addEventListener('click', addLine);
        $('#rxSave').addEventListener('click', () => save().catch((e) => MF.toast(e.message, 'err', 'Could not save')));
        $('#rxSearch').addEventListener('input', (e) => { state.q = e.target.value; state.page = 1; render(); });
        $('#rxStatus').addEventListener('change', (e) => { state.status = e.target.value; state.page = 1; render(); });
        $('#rxPrint').addEventListener('click', () => {
          const row = state.rows.find((r) => String(r.id) === String(state.viewId));
          if (row) printRecord(row, state.viewItems);
        });
        $('#rxEditView').addEventListener('click', () => {
          const row = state.rows.find((r) => String(r.id) === String(state.viewId));
          if (row) openEdit(row).catch((e) => MF.toast(e.message, 'err', 'Could not edit'));
        });
        $('#rxMarkReady').addEventListener('click', () => {
          const row = state.rows.find((r) => String(r.id) === String(state.viewId));
          if (!row) return;
          setStatus(row, 'Ready').then(() => {
            const modal = bootstrap.Modal.getInstance($('#rxViewModal'));
            if (modal) modal.hide();
            MF.toast(row.rx_no + ' marked ready', 'success', 'Packed');
          }).catch((e) => MF.toast(e.message, 'err', 'Could not update'));
        });
        $('#rxComplete').addEventListener('click', () => onAction('dispensed', state.viewId).catch((e) => MF.toast(e.message, 'err', 'Could not update')));
        $('#rxCancelRx').addEventListener('click', () => onAction('cancel', state.viewId).catch((e) => MF.toast(e.message, 'err', 'Could not update')));
        $('#rxBillPos').addEventListener('click', () => billAtPos().catch((e) => MF.toast(e.message, 'err', 'Could not open POS')));
        await load();
      });
    })();
  </script>
</body>
</html>
