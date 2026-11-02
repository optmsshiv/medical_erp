<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php';

/* ---- tenant identity + billing data (master control plane) ----
   Same host logic as core/TenantPolicy.php. Every master query is guarded:
   master down → neutral card, never an error inside a paying tenant's app. */
$sub = null;
try {
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    $host = preg_replace('/:\d+$/', '', $host);
    $base = strtolower((string) (getenv('APP_DOMAIN') ?: ''));
    if ($base !== '' && $host !== $base && substr($host, -strlen('.' . $base)) === '.' . $base) {
        $sub = trim(substr($host, 0, -strlen('.' . $base)));
        if ($sub === '' || strpos($sub, '.') !== false) { $sub = null; }
    }
} catch (Throwable $e) { $sub = null; }

$client = null; $ready = false;
if ($sub !== null) {
    try {
        $pdo = Database::master();
        $client = $pdo->query('SELECT * FROM clients WHERE subdomain = ' . $pdo->quote($sub) . ' LIMIT 1')->fetch() ?: null;
        $ready = (bool) $pdo->query("SHOW TABLES LIKE 'client_invoices'")->fetch();
    } catch (Throwable $e) { $client = null; }
}

$unpaid = []; $paid = []; $plans = []; $kv = [];
if ($client && $ready) {
    try {
        $pdo = Database::master(); $cid = (int) $client['id'];
        $unpaid = $pdo->query("SELECT * FROM client_invoices WHERE client_id = {$cid} AND status = 'unpaid' ORDER BY due_date ASC, id ASC")->fetchAll();
        $paid   = $pdo->query("SELECT * FROM client_invoices WHERE client_id = {$cid} AND status = 'paid' ORDER BY paid_at DESC LIMIT 14")->fetchAll();
        $plans  = $pdo->query('SELECT * FROM billing_plans ORDER BY sort_order ASC')->fetchAll();
        foreach ($pdo->query('SELECT `key`,`value` FROM billing_settings')->fetchAll() as $r) { $kv[$r['key']] = $r['value']; }
    } catch (Throwable $e) { $ready = false; }
}

$plan = strtolower((string) ($client['plan'] ?? 'trial'));
$planNames = ['trial' => 'Trial', 'standard' => 'Standard', 'annual' => 'Annual'];
$planLabel = $planNames[$plan] ?? ucfirst($plan);
$status    = strtolower((string) ($client['status'] ?? 'active'));
$today     = date('Y-m-d');
$trialEnd  = substr((string) ($client['trial_ends_at'] ?? ''), 0, 10);
$trialDays = ($trialEnd !== '' && $trialEnd >= $today) ? (int) ((strtotime($trialEnd) - strtotime($today)) / 86400) : 0;
$outstanding = array_reduce($unpaid, fn($s, $i) => $s + (float) $i['amount'], 0.0);
$overdue = array_filter($unpaid, fn($i) => $i['due_date'] && $i['due_date'] < $today);
$paidYear = array_reduce(array_filter($paid, fn($i) => $i['paid_at'] && date('Y', strtotime($i['paid_at'])) === date('Y')),
                          fn($s, $i) => $s + (float) $i['amount'], 0.0);
$upi = (string) ($kv['upi_id'] ?? ''); $payNote = (string) ($kv['pay_note'] ?? '');
$planFeatures = [
    'trial'    => ['14-day full access', 'Up to 2 users', 'Up to 2,000 medicines', 'All POS & reports'],
    'standard' => ['Unlimited sales invoicing', 'Up to 5 users', 'Up to 20,000 medicines', 'Compliance register & GST'],
    'annual'   => ['Everything in Standard', '12 months, one single payment', 'Priority support lane', 'Early feature access'],
];

function esc($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function inr(float $v): string { return '₹' . number_format($v, 0); }
?>
<!doctype html>
<html lang="en">
<head>
  <!-- build 2026-10-26 - premium tenant subscription centre -->
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
    .sub-hero { position:relative; overflow:hidden; border:1px solid #DDE7E3; border-radius:18px; padding:24px 26px;
                background:linear-gradient(120deg,#0B3B33 0%,#12594E 55%,#176B5B 100%); color:#fff; }
    .sub-hero::after { content:""; position:absolute; right:-70px; top:-70px; width:260px; height:260px; border-radius:50%;
                       background:radial-gradient(circle,rgba(255,255,255,.16),transparent 65%); }
    .sub-hero .eyebrow { font-size:.62rem; font-weight:800; letter-spacing:.16em; text-transform:uppercase; color:#9ED8CB; }
    .sub-hero .pnm { font-size:1.7rem; font-weight:800; letter-spacing:-.02em; margin:2px 0 4px; }
    .sub-hero .psub { color:#C7E7DF; font-size:.82rem; }
    .hchip { display:inline-flex; align-items:center; gap:6px; font-size:.7rem; font-weight:700; padding:5px 13px; border-radius:20px; }
    .hchip.ok   { background:rgba(209,250,229,.18); color:#A7F3D0; border:1px solid rgba(167,243,208,.35); }
    .hchip.warn { background:rgba(254,243,199,.16); color:#FDE68A; border:1px solid rgba(253,230,138,.35); }
    .hchip.bad  { background:rgba(254,226,226,.18); color:#FECACA; border:1px solid rgba(254,202,202,.35); }
    .trial-rail { height:8px; border-radius:8px; background:rgba(255,255,255,.18); overflow:hidden; min-width:170px; }
    .trial-rail i { display:block; height:100%; border-radius:8px; background:linear-gradient(90deg,#FDE68A,#FBBF24); }
    .kv { border:1px solid #E9EDF3; border-radius:14px; padding:13px 16px; background:#fff; height:100%; }
    .kv span { display:block; font-size:.62rem; font-weight:800; letter-spacing:.09em; text-transform:uppercase; color:#98A2B3; }
    .kv b { font-size:1.15rem; font-weight:750; letter-spacing:-.02em; margin-top:3px; display:block; }
    .kv small { color:#98A2B3; font-size:.7rem; }
    .chip { display:inline-flex; align-items:center; gap:5px; font-size:.64rem; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap; }
    .chip.ok   { background:#E8F7EE; color:#1B6B3C; } .chip.warn { background:#FFF3DC; color:#93650A; }
    .chip.bad  { background:#FDECEC; color:#9A2E24; } .chip.mut { background:#EEF0F4; color:#64748B; }
    .ibtn { display:inline-flex; align-items:center; gap:5px; font-size:.7rem; font-weight:650; padding:5px 11px; border-radius:8px;
            border:1px solid #E2E7EF; background:#fff; color:#475569; text-decoration:none; transition:all .15s; white-space:nowrap; }
    .ibtn:hover { border-color:#176B5B; color:#0F4D42; background:#F4FBF9; }
    .planbox { border:1px solid #E9EDF3; border-radius:16px; padding:18px; background:#fff; height:100%; position:relative; }
    .planbox.cur { border-color:#176B5B; box-shadow:0 0 0 3px rgba(23,107,91,.12); }
    .planbox .tag { position:absolute; top:-11px; right:14px; background:#176B5B; color:#fff; font-size:.58rem; font-weight:800;
                    letter-spacing:.1em; text-transform:uppercase; padding:4px 11px; border-radius:20px; }
    .planbox .pn { font-size:.7rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#0F4D42; }
    .planbox .pp { font-size:1.5rem; font-weight:800; letter-spacing:-.02em; margin:6px 0 10px; }
    .planbox .pp small { font-size:.68rem; font-weight:600; color:#98A2B3; }
    .planbox ul { list-style:none; margin:0; padding:0; font-size:.78rem; color:#475569; }
    .planbox li { padding:5px 0 5px 20px; position:relative; border-top:1px solid #F1F4F8; }
    .planbox li i { position:absolute; left:0; top:7px; color:#0F4D42; font-size:.72rem; }
    .paybox { border:2px dashed #BFDED6; background:#F6FBF9; border-radius:14px; padding:16px 18px; }
    .paybox .upi { font-family:ui-monospace, Menlo, monospace; font-weight:700; font-size:1rem; letter-spacing:.01em; color:#0B3B33; }
    .copybtn { border:1px solid #BFDED6; background:#fff; color:#0F4D42; font-size:.66rem; font-weight:700; padding:4px 10px;
               border-radius:8px; cursor:pointer; }
    .copybtn:hover { background:#E9F5F1; }
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
            <p class="page-sub">Plan, invoices and payments — managed by the OPTMS-RX team</p>
          </div>
        </div>

        <?php if (!$client || !$ready): ?>
          <div class="card-mf"><div class="card-body p-4 text-center text-muted">
            <i class="bi bi-cloud-slash fs-3 d-block mb-2"></i>
            Subscription details are temporarily unavailable from the central billing service.
            Your store is unaffected — try again in a few minutes.
          </div></div>
        <?php else: ?>

          <!-- hero -->
          <div class="sub-hero mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 position-relative" style="z-index:1">
              <div>
                <div class="eyebrow">Current plan</div>
                <div class="pnm"><?= esc($planLabel) ?></div>
                <div class="psub"><?= esc($client['name']) ?> · <?= esc($client['subdomain']) ?>.<?= esc(getenv('APP_DOMAIN') ?: '') ?></div>
              </div>
              <div class="d-flex flex-column align-items-end gap-2">
                <div class="d-flex gap-2 flex-wrap">
                  <?php if ($plan === 'trial'): ?>
                    <span class="hchip warn"><i class="bi bi-hourglass-split"></i>Trial<?= $trialDays ? ' · ' . $trialDays . ' day' . ($trialDays === 1 ? '' : 's') . ' left' : '' ?></span>
                  <?php elseif ($status === 'active'): ?>
                    <span class="hchip ok"><i class="bi bi-check-circle-fill"></i>Active</span>
                  <?php elseif ($status === 'suspended'): ?>
                    <span class="hchip bad"><i class="bi bi-pause-circle-fill"></i>Paused</span>
                  <?php endif; ?>
                  <?php if ($overdue): ?><span class="hchip bad"><i class="bi bi-exclamation-triangle-fill"></i><?= count($overdue) ?> overdue</span>
                  <?php elseif ($unpaid): ?><span class="hchip warn"><i class="bi bi-clock"></i><?= count($unpaid) ?> open</span>
                  <?php else: ?><span class="hchip ok"><i class="bi bi-shield-check"></i>Nothing due</span><?php endif; ?>
                </div>
                <?php if ($plan === 'trial' && $trialEnd !== ''): $used = max(0, min(14, 14 - $trialDays)); ?>
                  <div class="d-flex align-items-center gap-2">
                    <div class="trial-rail"><i style="width:<?= (int) ($used / 14 * 100) ?>%"></i></div>
                    <span style="font-size:.66rem;color:#C7E7DF">day <?= $used ?> of 14</span>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- KPIs -->
          <div class="row g-3 mb-3">
            <div class="col-6 col-xl-3"><div class="kv"><span>Outstanding</span><b class="<?= $outstanding > 0 ? 'text-danger' : '' ?>"><?= inr($outstanding) ?></b><small><?= count($unpaid) ?> open invoice<?= count($unpaid) === 1 ? '' : 's' ?></small></div></div>
            <div class="col-6 col-xl-3"><div class="kv"><span>Paid this year</span><b class="text-success"><?= inr($paidYear) ?></b><small><?= count($paid) ?> settled<?= count($paid) < count($paid) + count($unpaid) ? ' +' . count($unpaid) . ' open' : '' ?></small></div></div>
            <div class="col-6 col-xl-3"><div class="kv"><span>Next due</span><b><?= $unpaid && $unpaid[0]['due_date'] ? esc(date('d M Y', strtotime($unpaid[0]['due_date']))) : '—' ?></b><small><?= $unpaid ? esc($unpaid[0]['invoice_no']) : 'no open invoice' ?></small></div></div>
            <div class="col-6 col-xl-3"><div class="kv"><span>Member since</span><b><?= !empty($client['created_at']) ? esc(date('M Y', strtotime($client['created_at']))) : '—' ?></b><small><?= $trialEnd ? 'trial till ' . esc(date('d M', strtotime($trialEnd))) : '' ?></small></div></div>
          </div>

          <div class="row g-3">
            <!-- ledger -->
            <div class="col-xxl-7">
              <div class="card-mf">
                <div class="card-head"><h2 class="card-title"><i class="bi bi-receipt"></i>Invoice &amp; payment ledger</h2>
                  <div class="card-tools"><span class="badge badge-soft-secondary"><?= count($unpaid) + count($paid) ?> record<?= count($unpaid) + count($paid) === 1 ? '' : 's' ?></span></div>
                </div>
                <?php if (!$unpaid && !$paid): ?>
                  <div class="card-body p-4 text-muted">No invoices yet — invoices appear at the start of each billing cycle, with the paid date recorded here the moment your payment is verified.</div>
                <?php else: ?>
                <div class="table-scroll" style="max-height:460px">
                  <table class="table table-mf mb-0">
                    <thead><tr><th>Invoice</th><th>Period</th><th class="text-end">Amount</th><th>Status</th><th>Paid on</th><th class="text-end">Document</th></tr></thead>
                    <tbody>
                      <?php foreach (array_merge($unpaid, $paid) as $i):
                          $lt = $i['status'] === 'unpaid' && $i['due_date'] && $i['due_date'] < $today; ?>
                        <tr>
                          <td class="small fw-semibold"><?= esc($i['invoice_no']) ?><div class="text-muted" style="font-size:.66rem">due <?= $i['due_date'] ? esc(date('d M Y', strtotime($i['due_date']))) : '—' ?></div></td>
                          <td class="small text-muted"><?= esc(date('d M', strtotime($i['period_from']))) ?> → <?= esc(date('d M Y', strtotime($i['period_to']))) ?></td>
                          <td class="small text-end fw-semibold"><?= inr((float) $i['amount']) ?></td>
                          <td><span class="chip <?= $i['status'] === 'paid' ? 'ok' : ($lt ? 'bad' : 'warn') ?>"><?= $i['status'] === 'paid' ? 'Paid' : ($lt ? 'Overdue' : 'Due') ?></span></td>
                          <td class="small"><?= $i['paid_at'] ? esc(date('d M Y', strtotime($i['paid_at']))) : '—' ?></td>
                          <td class="text-end" style="white-space:nowrap">
                            <a class="ibtn" href="subscription-invoice.php?inv=<?= (int) $i['id'] ?>" target="_blank"><i class="bi bi-eye"></i>View</a>
                            <a class="ibtn" href="subscription-invoice.php?inv=<?= (int) $i['id'] ?>&print=1" target="_blank"><i class="bi bi-printer"></i>PDF</a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <?php endif; ?>
              </div>

              <!-- how to pay -->
              <div class="card-mf mt-3">
                <div class="card-head"><h2 class="card-title"><i class="bi bi-qr-code"></i>How to pay</h2></div>
                <div class="card-body p-3">
                  <div class="paybox d-flex align-items-center justify-content-between gap-3 flex-wrap">
                    <div>
                      <?php if ($upi !== ''): ?>
                        <div class="small text-muted text-uppercase fw-bold mb-1" style="letter-spacing:.07em">Pay by UPI</div>
                        <div class="upi" id="upiTxt"><?= esc($upi) ?></div>
                      <?php endif; ?>
                      <div class="small text-muted mt-1"><?= $payNote !== '' ? nl2br(esc($payNote)) : 'Payment instructions are being finalised by the OPTMS-RX team — your subscription stays fully active meanwhile.' ?></div>
                    </div>
                    <?php if ($upi !== ''): ?><button type="button" class="copybtn" id="upiCopy"><i class="bi bi-clipboard"></i> Copy UPI ID</button><?php endif; ?>
                  </div>
                  <div class="small text-muted mt-3"><i class="bi bi-info-circle me-1"></i>After you pay, the OPTMS-RX team verifies and marks the invoice paid — this page updates itself, and any overdue hold lifts automatically.</div>
                </div>
              </div>
            </div>

            <!-- plan compare -->
            <div class="col-xxl-5">
              <div class="card-mf">
                <div class="card-head"><h2 class="card-title"><i class="bi bi-grid-1x2"></i>Plans</h2>
                  <div class="card-tools"><span class="badge badge-soft-secondary">live prices · set by OPTMS-RX</span></div>
                </div>
                <div class="card-body p-3">
                  <div class="row g-3">
                    <?php foreach ($plans as $p):
                        $code = strtolower((string) $p['code']); $cur = $code === $plan; ?>
                      <div class="col-12">
                        <div class="planbox <?= $cur ? 'cur' : '' ?>">
                          <?php if ($cur): ?><span class="tag">Your plan</span><?php endif; ?>
                          <div class="d-flex align-items-baseline justify-content-between gap-2 flex-wrap">
                            <div>
                              <div class="pn"><?= esc($p['name']) ?></div>
                              <div class="pp"><?=(float) $p['price'] > 0 ? inr((float) $p['price']) : 'Free' ?><small> / <?= $code === 'annual' ? 'year' : ($code === 'trial' ? 'trial' : 'month') ?></small></div>
                            </div>
                            <?php if (!$cur && (float) $p['price'] > 0 && $code !== 'trial'): ?>
                              <span class="small text-muted" style="font-size:.7rem"><i class="bi bi-arrow-up-right-circle me-1"></i>Ask the OPTMS-RX team to switch</span>
                            <?php endif; ?>
                          </div>
                          <ul>
                            <?php foreach (($planFeatures[$code] ?? []) as $f): ?><li><i class="bi bi-check2"></i><?= esc($f) ?></li><?php endforeach; ?>
                          </ul>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>

              <div class="card-mf mt-3">
                <div class="card-head"><h2 class="card-title"><i class="bi bi-headset"></i>Questions</h2></div>
                <div class="card-body p-3 small text-muted">
                  Plan changes, billing queries or access issues — contact the OPTMS-RX team from the Help section in Settings. Quote your invoice number for the fastest answer.
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
  <script src="assets/js/app.js?v=2026-10-26.04"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var b = document.getElementById('upiCopy');
      if (b) b.addEventListener('click', function () {
        var t = (document.getElementById('upiTxt') || {}).textContent || '';
        (navigator.clipboard ? navigator.clipboard.writeText(t.trim()) : Promise.reject())
          .then(function () { b.innerHTML = '<i class="bi bi-check2"></i> Copied'; setTimeout(function(){ b.innerHTML = '<i class="bi bi-clipboard"></i> Copy UPI ID'; }, 1600); })
          .catch(function () { window.prompt('Copy your UPI ID:', t.trim()); });
      });
    });
  </script>
</body>
</html>
