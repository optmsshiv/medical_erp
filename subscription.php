<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php';

/* ---- resolve this tenant's billing record from the master control plane ----
   Same host logic as core/TenantPolicy.php. Every master query is guarded:
   if the master DB is unreachable this page renders a neutral card, never an error. */
$sub = null;
try {
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    $host = preg_replace('/:\d+$/', '', $host);
    $base = strtolower((string) (getenv('APP_DOMAIN') ?: ''));
    if ($base !== '' && $host !== $base && substr($host, -strlen('.' . $base)) === '.' . $base) {
        $sub = substr($host, 0, -strlen('.' . $base));
        if (strpos($sub, '.') !== false) { $sub = null; }          // nested subdomains: not ours
    }
} catch (Throwable $e) { $sub = null; }

$client = null; $bt = false;   // billing tables present?
if ($sub !== null) {
    try {
        $pdo = Database::master();
        $client = $pdo->query('SELECT * FROM clients WHERE subdomain = ' . $pdo->quote($sub) . ' LIMIT 1')->fetch() ?: null;
        $bt = (bool) $pdo->query("SHOW TABLES LIKE 'client_invoices'")->fetch();
    } catch (Throwable $e) { $client = null; $bt = false; }
}

$unpaid = []; $paid = []; $latest = null; $payNote = ''; $upi = '';
if ($client && $bt) {
    try {
        $pdo = Database::master();
        $cid = (int) $client['id'];
        $unpaid = $pdo->query("SELECT * FROM client_invoices WHERE client_id = {$cid} AND status = 'unpaid' ORDER BY due_date ASC, id ASC")->fetchAll();
        $paid   = $pdo->query("SELECT * FROM client_invoices WHERE client_id = {$cid} AND status = 'paid' ORDER BY paid_at DESC LIMIT 6")->fetchAll();
        $latest = $unpaid[0] ?? null;
        if (!$latest) {
            $latest = $pdo->query("SELECT * FROM client_invoices WHERE client_id = {$cid} AND status <> 'void' ORDER BY period_from DESC LIMIT 1")->fetch() ?: null;
        }
    } catch (Throwable $e) { $unpaid = []; $paid = []; $latest = null; }
    try {
        $kv = [];
        foreach (Database::master()->query('SELECT `key`,`value` FROM billing_settings')->fetchAll() as $r) { $kv[$r['key']] = $r['value']; }
        $payNote = (string) ($kv['pay_note'] ?? '');
        $upi     = (string) ($kv['upi_id'] ?? '');
    } catch (Throwable $e) { }
}

$plan   = strtolower((string) ($client['plan'] ?? 'trial'));
$planNames = ['trial' => 'Trial — 14 days', 'standard' => 'Standard — monthly', 'annual' => 'Annual — yearly'];
$planLabel = $planNames[$plan] ?? ucfirst($plan);
$status = strtolower((string) ($client['status'] ?? 'active'));
$today  = date('Y-m-d');
$trialEnd   = (string) ($client['trial_ends_at'] ?? '');
$trialDaysLeft = ($trialEnd !== '' && $trialEnd >= $today) ? (int) ((strtotime($trialEnd) - strtotime($today)) / 86400) : 0;
$outstanding = array_reduce($unpaid, fn($s, $i) => $s + (float) $i['amount'], 0.0);
$overdue = count(array_filter($unpaid, fn($i) => $i['due_date'] && $i['due_date'] < $today));

function esc($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function inr(float $v): string { return '₹' . number_format($v, 0); }
?>
<!doctype html>
<html lang="en">
<head>
  <!-- build 2026-10-25 - tenant subscription view (read-only, master-resilient) -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Subscription · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .sub-hero { border:1px solid #E2E8F0; border-radius:16px; padding:20px 22px; background:linear-gradient(135deg,#F4FBF9 0%,#EEF7F4 55%,#FDFDFE 100%); }
    .sub-plan { font-size:1.35rem; font-weight:800; letter-spacing:-.02em; }
    .sub-chip { display:inline-flex; align-items:center; gap:6px; font-size:.7rem; font-weight:700; padding:4px 12px; border-radius:20px; }
    .sub-chip.ok   { background:#E8F7EE; color:#1B6B3C; }
    .sub-chip.warn { background:#FFF3DC; color:#93650A; }
    .sub-chip.bad  { background:#FDECEC; color:#9A2E24; }
    .sub-chip.mut  { background:#EEF0F4; color:#64748B; }
    .sub-kv { border:1px solid #EEF0F4; border-radius:12px; padding:12px 14px; background:#fff; height:100%; }
    .sub-kv span { display:block; font-size:.62rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#94A3B8; }
    .sub-kv b { font-size:1.05rem; font-weight:700; letter-spacing:-.02em; margin-top:2px; display:block; }
    .pay-box { border:2px dashed #CBE3DC; background:#F7FCFB; border-radius:14px; padding:16px 18px; }
    .pay-box .upi { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-weight:700; font-size:1.05rem; letter-spacing:.01em; }
    .badge-soft-secondary { font-weight:600; }
    .table-mf td, .table-mf th { vertical-align:middle; }
  </style>
</head>
<body data-page="subscription">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-credit-card-2-front me-2 text-success"></i>My Subscription</h1>
            <p class="page-sub">Plan, invoices and payment — managed by the OPTMS-RX team</p>
          </div>
        </div>

        <?php if (!$client || !$bt): ?>
          <div class="card-mf">
            <div class="card-body p-4 text-center text-muted">
              <i class="bi bi-cloud-slash fs-3 d-block mb-2"></i>
              Subscription details are temporarily unavailable from the central billing service.
              Your store is unaffected — try again in a few minutes.
            </div>
          </div>
        <?php else: ?>

          <!-- status strip -->
          <div class="sub-hero mb-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
              <div class="text-muted small text-uppercase fw-bold" style="letter-spacing:.08em">Current plan</div>
              <div class="sub-plan"><?= esc($planLabel) ?></div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <?php if ($status === 'trial'): ?>
                <span class="sub-chip warn"><i class="bi bi-hourglass-split"></i>Trial<?= $trialDaysLeft ? ' · ' . $trialDaysLeft . ' day' . ($trialDaysLeft === 1 ? '' : 's') . ' left' : '' ?></span>
              <?php elseif ($status === 'active'): ?>
                <span class="sub-chip ok"><i class="bi bi-check-circle-fill"></i>Active</span>
              <?php elseif ($status === 'suspended'): ?>
                <span class="sub-chip bad"><i class="bi bi-pause-circle-fill"></i>Paused</span>
              <?php else: ?>
                <span class="sub-chip mut"><?= esc(ucfirst($status)) ?></span>
              <?php endif; ?>
              <?php if ($overdue > 0): ?><span class="sub-chip bad"><i class="bi bi-exclamation-triangle-fill"></i><?= $overdue ?> overdue</span>
              <?php elseif ($unpaid): ?><span class="sub-chip warn"><i class="bi bi-clock"></i><?= count($unpaid) ?> open</span>
              <?php else: ?><span class="sub-chip ok"><i class="bi bi-shield-check"></i>Nothing due</span><?php endif; ?>
            </div>
          </div>

          <!-- key figures -->
          <div class="row g-3 mb-3">
            <div class="col-6 col-xl-3"><div class="sub-kv"><span>Outstanding</span><b class="<?= $outstanding > 0 ? 'text-danger' : '' ?>"><?= inr($outstanding) ?></b></div></div>
            <div class="col-6 col-xl-3"><div class="sub-kv"><span>Next due date</span><b><?= $latest && $latest['due_date'] ? esc(date('d M Y', strtotime($latest['due_date']))) : '—' ?></b></div></div>
            <div class="col-6 col-xl-3"><div class="sub-kv"><span>Started</span><b><?= !empty($client['created_at']) ? esc(date('d M Y', strtotime($client['created_at']))) : '—' ?></b></div></div>
            <div class="col-6 col-xl-3"><div class="sub-kv"><span>Trial ends</span><b><?= $trialEnd ? esc(date('d M Y', strtotime($trialEnd))) : '—' ?></b></div></div>
          </div>

          <div class="row g-3">
            <!-- current invoice -->
            <div class="col-xl-7">
              <div class="card-mf">
                <div class="card-head"><h2 class="card-title"><i class="bi bi-receipt"></i><?= $latest ? ($latest['status'] === 'unpaid' ? 'Invoice to pay' : 'Latest invoice') : 'Invoices' ?></h2>
                  <div class="card-tools"><?php if ($latest): ?><span class="badge badge-soft-secondary"><?= esc($latest['invoice_no']) ?></span><?php endif; ?></div>
                </div>
                <div class="card-body p-3">
                  <?php if (!$latest): ?>
                    <div class="text-muted">No invoices yet — invoices appear here at the start of each billing cycle.</div>
                  <?php else:
                      $l = $latest;
                      $late = $l['status'] === 'unpaid' && $l['due_date'] && $l['due_date'] < $today;
                  ?>
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                      <span class="fs-4 fw-bold <?= $l['status'] === 'unpaid' ? 'text-danger' : '' ?>"><?= inr((float) $l['amount']) ?></span>
                      <span class="sub-chip <?= $l['status'] === 'paid' ? 'ok' : ($late ? 'bad' : 'warn') ?>">
                        <i class="bi bi-<?= $l['status'] === 'paid' ? 'check-circle-fill' : ($late ? 'exclamation-triangle-fill' : 'clock') ?>"></i>
                        <?= $l['status'] === 'paid' ? 'Paid ' . esc(date('d M Y', strtotime((string) $l['paid_at']))) : ($late ? 'Overdue' : 'Awaiting payment') ?>
                      </span>
                    </div>
                    <div class="text-muted small">
                      Billing period <?= esc(date('d M Y', strtotime($l['period_from']))) ?> → <?= esc(date('d M Y', strtotime($l['period_to']))) ?>
                      · due <?= $l['due_date'] ? esc(date('d M Y', strtotime($l['due_date']))) : '—' ?>
                    </div>
                  <?php endif; ?>
                </div>
                <?php if ($paid || $unpaid): ?>
                <div class="table-scroll" style="max-height:280px">
                  <table class="table table-mf mb-0">
                    <thead><tr><th>Invoice</th><th>Period</th><th>Due</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                    <tbody>
                      <?php foreach (array_merge($unpaid, $paid) as $i): $lt = $i['status'] === 'unpaid' && $i['due_date'] && $i['due_date'] < $today; ?>
                        <tr>
                          <td class="small fw-semibold"><?= esc($i['invoice_no']) ?></td>
                          <td class="small text-muted"><?= esc(date('d M', strtotime($i['period_from']))) ?> → <?= esc(date('d M Y', strtotime($i['period_to']))) ?></td>
                          <td class="small"><?= $i['due_date'] ? esc(date('d M', strtotime($i['due_date']))) : '—' ?></td>
                          <td class="small text-end fw-semibold"><?= inr((float) $i['amount']) ?></td>
                          <td><span class="sub-chip <?= $i['status'] === 'paid' ? 'ok' : ($lt ? 'bad' : 'warn') ?>" style="font-size:.62rem"><?= $i['status'] === 'paid' ? 'Paid' : ($lt ? 'Overdue' : 'Due') ?></span></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- how to pay -->
            <div class="col-xl-5">
              <div class="card-mf mb-3">
                <div class="card-head"><h2 class="card-title"><i class="bi bi-qr-code"></i>How to pay</h2></div>
                <div class="card-body p-3">
                  <div class="pay-box">
                    <?php if ($upi !== ''): ?>
                      <div class="small text-muted text-uppercase fw-bold mb-1" style="letter-spacing:.06em">Pay by UPI</div>
                      <div class="upi mb-1"><?= esc($upi) ?></div>
                    <?php endif; ?>
                    <div class="small text-muted"><?= $payNote !== '' ? nl2br(esc($payNote)) : 'Payment instructions are being finalised by the OPTMS-RX team — your subscription stays fully active meanwhile.' ?></div>
                  </div>
                  <div class="small text-muted mt-3"><i class="bi bi-info-circle me-1"></i>After paying, the OPTMS-RX team marks the invoice paid — this page and your access update automatically.</div>
                </div>
              </div>

              <div class="card-mf">
                <div class="card-head"><h2 class="card-title"><i class="bi bi-headset"></i>Questions</h2></div>
                <div class="card-body p-3 small text-muted">
                  Plan changes, billing queries or access issues — reply in your onboarding thread or contact the OPTMS-RX team from the Help section in Settings.
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

      </main>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js"></script>
</body>
</html>
