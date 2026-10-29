<?php
// TEMP DIAGNOSIS — uncomment the next two lines to print the real error instead of a 500:
// ini_set('display_errors', 1); error_reporting(E_ALL);
require __DIR__ . '/actions/_lib.php';   // includes _guard.php + Database.php, flash plumbing, billing helpers

// Self-diagnosis: if the helpers were not uploaded with this page, say so instead of white-screening.
if (!function_exists('billingSetting') || !function_exists('planPrices')) {
    http_response_code(500);
    echo '<div style="max-width:640px;margin:12vh auto;font-family:sans-serif;padding:24px;border:1px solid #E5E7F0;border-radius:14px">'
       . '<h2 style="margin:0 0 8px">Billing page cannot start</h2>'
       . '<p style="color:#5B6270">It needs the updated helpers file it was shipped with. Please upload<br>'
       . '<code>super-admin/actions/_lib.php</code> (the extended version) to <code>/super-admin/actions/_lib.php</code>,<br>'
       . 'replacing the older file, then reload.</p></div>';
    exit;
}

$billingReady = false;
try { $billingReady = (bool) master()->query("SHOW TABLES LIKE 'client_invoices'")->fetch(); } catch (Throwable $e) { }

$flashOk    = $_SESSION['flash_ok_msg'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_ok_msg'], $_SESSION['flash_error']);

$today   = date('Y-m-d');
$plans   = [];
$invs    = [];
$mrr     = 0.0;
$collected  = 0.0;
$outstanding = 0;
$overdueN    = 0;
$grace    = (int) billingSetting('grace_days', '7');
$upiId    = billingSetting('upi_id', '');
$payNote  = billingSetting('pay_note', '');
$coName   = billingSetting('company_name', '');
$coAddr   = billingSetting('company_addr', '');
$coContact= billingSetting('company_contact', '');
$coGstin  = billingSetting('company_gstin', '');
$coEmail  = billingSetting('company_email', '');
$autoSus  = billingSetting('auto_suspend_overdue', '0') === '1';

if ($billingReady) {
    try { $plans = master()->query('SELECT * FROM billing_plans ORDER BY sort_order ASC, id ASC')->fetchAll(); } catch (Throwable $e) { }
    try {
        $invs = master()->query(
            'SELECT ci.*, cl.name AS client_name, cl.subdomain AS client_sub
               FROM client_invoices ci JOIN clients cl ON cl.id = ci.client_id
              ORDER BY ci.created_at DESC, ci.id DESC LIMIT 120'
        )->fetchAll();
    } catch (Throwable $e) { }
    try {
        $c = master()->query("SELECT COALESCE(SUM(amount), 0) AS s FROM client_invoices WHERE status = 'paid' AND DATE_FORMAT(paid_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')")->fetch();
        $collected = (float) ($c['s'] ?? 0);
    } catch (Throwable $e) { }
    try {
        $c = master()->query("SELECT COUNT(*) n FROM client_invoices WHERE status = 'unpaid'")->fetch();
        $outstanding = (int) ($c['n'] ?? 0);
        $c = master()->query("SELECT COUNT(*) n FROM client_invoices WHERE status = 'unpaid' AND due_date < " . master()->quote($today))->fetch();
        $overdueN = (int) ($c['n'] ?? 0);
    } catch (Throwable $e) { }
    try {
        $prices = planPrices();
        foreach (master()->query('SELECT status, plan FROM clients')->fetchAll() as $cl) {
            if (strtolower((string) $cl['status']) === 'suspended') { continue; }
            $pl = strtolower((string) ($cl['plan'] ?? 'trial'));
            $p  = (float) ($prices[$pl]['price'] ?? 0);
            $mrr += ($pl === 'annual') ? $p / 12 : $p;   // trials price 0 by definition
        }
    } catch (Throwable $e) { }
}
$nav = 'billing';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Billing — OPTMS-RX Admin</title>
<style>
    :root {
        --ink:#1A1D27; --ink-2:#5B6270; --ink-3:#8A8F9A; --line:#E5E7F0; --bg:#F4F5F9;
        --brand:#4338CA; --brand-strong:#3730A3; --brand-soft:#EEF0FE; --ring:rgba(67,56,202,.14);
        --ok:#1B6B3C; --ok-soft:#E8F7EE; --warn:#93650A; --warn-soft:#FFF3DC; --bad:#9A2E24; --bad-soft:#FDECEC;
    }
    * { box-sizing:border-box; }
    body { margin:0; background:var(--bg); color:var(--ink); font-family:-apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", Roboto, sans-serif; font-feature-settings:"cv02","cv03","cv04"; font-size:14px; }
    .bar { position:sticky; top:0; z-index:20; background:rgba(255,255,255,.88); backdrop-filter:blur(10px); border-bottom:1px solid var(--line); }
    .bar-in { max-width:1120px; margin:0 auto; padding:0 22px; height:58px; display:flex; align-items:center; gap:22px; }
    .logo { display:flex; align-items:center; gap:9px; text-decoration:none; }
    .logo-mark { width:28px; height:28px; border-radius:8px; background:linear-gradient(135deg,#4F46E5,#3730A3); display:flex; align-items:center; justify-content:center; color:#fff; }
    .logo-mark svg { width:15px; height:15px; }
    .logo b { font-size:14.5px; letter-spacing:-.01em; background:linear-gradient(120deg,#312E81,#4F46E5); -webkit-background-clip:text; background-clip:text; color:transparent; }
    .logo .cap { font-size:9.5px; font-weight:800; letter-spacing:.14em; color:var(--ink-3); text-transform:uppercase; margin-left:2px; }
    .nav { display:flex; gap:4px; margin-left:8px; }
    .nav a { text-decoration:none; font-size:13px; font-weight:600; color:var(--ink-2); padding:7px 13px; border-radius:999px; transition:color .15s, background .15s; }
    .nav a:hover { color:var(--brand-strong); background:var(--brand-soft); }
    .nav a.on { color:var(--brand-strong); background:var(--brand-soft); }
    .bar-right { margin-left:auto; }
    .session { display:inline-flex; align-items:center; gap:7px; font-size:11.5px; font-weight:600; color:var(--ink-2); border:1px solid var(--line); background:#fff; border-radius:999px; padding:6px 12px; }
    .session::before { content:""; width:6px; height:6px; border-radius:50%; background:#22C55E; }

    .wrap { max-width:1120px; margin:0 auto; padding:30px 22px 46px; }
    .head { display:flex; align-items:end; justify-content:space-between; gap:14px; flex-wrap:wrap; margin-bottom:20px; }
    h1 { font-size:21px; letter-spacing:-.02em; margin:0 0 3px; }
    .sub { color:var(--ink-2); margin:0; font-size:13px; }
    .live-tag { font-size:10px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:var(--ok); background:var(--ok-soft); border:1px solid #BFEBD0; border-radius:999px; padding:5px 12px; }

    .alert { display:flex; gap:12px; border-radius:14px; padding:14px 16px; margin-bottom:22px; font-size:13px; align-items:center; }
    .alert-ok { background:var(--ok-soft); border:1px solid #BFEBD0; color:#14532D; }
    .alert-error { background:var(--bad-soft); border:1px solid #F1C9C4; color:var(--bad); }
    .banner { display:flex; gap:12px; align-items:center; background:var(--brand-soft); border:1px solid #DFE3FB; color:var(--brand-strong);
              border-radius:14px; padding:13px 16px; font-size:12.5px; margin-bottom:24px; line-height:1.55; }
    .banner svg { width:17px; height:17px; flex:none; }

    .stats { display:grid; grid-template-columns:repeat(4, 1fr); gap:12px; margin-bottom:22px; }
    .stat { background:#fff; border:1px solid var(--line); border-radius:15px; padding:14px 16px; box-shadow:0 1px 2px rgba(23,25,40,.04); }
    .stat span { display:block; font-size:10.5px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:var(--ink-3); }
    .stat strong { display:block; margin-top:4px; font-size:22px; font-weight:750; letter-spacing:-.03em; }
    .stat.ok strong { color:var(--ok); } .stat.warn strong { color:var(--warn); } .stat.bad strong { color:var(--bad); }

    .card { background:#fff; border:1px solid var(--line); border-radius:16px; padding:22px; margin-bottom:22px; box-shadow:0 1px 2px rgba(23,25,40,.04), 0 14px 34px rgba(36,30,110,.05); }
    .card-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
    .card-head h2 { font-size:14.5px; letter-spacing:-.01em; margin:0; }
    .cap { font-size:11.5px; color:var(--ink-3); }

    .plans { display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; }
    .plan { border:1px solid var(--line); border-radius:14px; padding:16px; background:#FCFCFE; }
    .plan .nm { font-size:11px; font-weight:800; letter-spacing:.07em; text-transform:uppercase; color:var(--brand-strong); margin-bottom:10px; }
    .plan .row { display:flex; align-items:center; gap:8px; }
    .plan input[type=number] { flex:1; padding:9px 11px; border:1px solid #D8DBE6; border-radius:9px; font-size:14px; font-family:inherit; }
    .plan input[type=number]:focus { outline:none; border-color:var(--brand); box-shadow:0 0 0 4px var(--ring); }
    .plan .cur { font-size:13px; color:var(--ink-3); font-weight:700; }
    .plan .term { font-size:10.5px; color:var(--ink-3); margin-top:7px; }
    .plan label.onoff { display:flex; align-items:center; gap:7px; margin-top:10px; font-size:11.5px; font-weight:650; color:var(--ink-2); text-transform:none; letter-spacing:0; }

    .setrow { display:flex; gap:22px; align-items:end; flex-wrap:wrap; }
    .setrow .field { min-width:170px; }
    label { font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--ink-3); margin-bottom:6px; display:block; }
    .setrow input[type=number] { padding:9px 11px; border:1px solid #D8DBE6; border-radius:9px; font-size:14px; width:110px; font-family:inherit; }
    .co-sec { flex:1 1 100%; border:1px solid #EBEEF6; background:#FBFBFE; border-radius:12px; padding:13px 15px; }
    .co-sec-h { font-size:11px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:var(--ink-3); margin-bottom:10px; }
    .co-sec-h span { font-weight:600; letter-spacing:0; text-transform:none; color:var(--ink-3); margin-left:6px; opacity:.75 }
    .co-grid { display:grid; grid-template-columns:1fr 1fr; gap:9px; }
    .pfx { position:relative; }
    .pfx svg { position:absolute; left:11px; top:50%; transform:translateY(-50%); width:14px; height:14px; color:#A5ABC2; pointer-events:none; }
    .pfx input { width:100%; padding:9px 11px 9px 33px; border:1px solid #D8DBE6; border-radius:9px; font-size:13px; font-family:inherit; background:#fff; }
    .pfx input:focus { outline:none; border-color:var(--brand); box-shadow:0 0 0 4px var(--ring); }
    @media (max-width:700px) { .co-grid { grid-template-columns:1fr; } }
    .sw { position:relative; width:42px; height:24px; flex:none; }
    .sw input { position:absolute; opacity:0; width:0; height:0; }
    .sw i { position:absolute; inset:0; border-radius:999px; background:#DDE0EA; cursor:pointer; transition:background .15s; }
    .sw i:before { content:""; position:absolute; left:3px; top:3px; width:18px; height:18px; border-radius:50%; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.2); transition:transform .15s; }
    .sw input:checked + i { background:var(--brand); }
    .sw input:checked + i:before { transform:translateX(18px); }
    .sw-wrap { display:flex; align-items:center; gap:10px; font-size:12.5px; color:var(--ink-2); padding-bottom:4px; }

    .btn { display:inline-flex; align-items:center; gap:8px; padding:10px 18px; border:0; border-radius:11px; cursor:pointer; background:linear-gradient(135deg,#4F46E5,#3B32C4); color:#fff; font-size:13.5px; font-weight:650; box-shadow:0 6px 16px rgba(67,56,202,.26); transition:transform .15s, box-shadow .15s; font-family:inherit; }
    .btn:hover { transform:translateY(-1px); box-shadow:0 10px 24px rgba(67,56,202,.32); }
    .btn-ghost { border:1px solid var(--line); background:#fff; color:var(--ink-2); font-size:12.5px; font-weight:650; padding:7px 13px; border-radius:9px; cursor:pointer; font-family:inherit; transition:all .15s; }
    .btn-ghost:hover { border-color:var(--brand); color:var(--brand-strong); background:var(--brand-soft); }
    .btn-ghost.ok:hover { border-color:#BFEBD0; color:var(--ok); background:var(--ok-soft); }
    .btn-ghost.danger:hover { border-color:#F1C9C4; color:var(--bad); background:var(--bad-soft); }

    table { width:100%; border-collapse:collapse; }
    th, td { text-align:left; padding:12px 16px; border-bottom:1px solid #EEF0F6; font-size:13px; vertical-align:middle; }
    th { font-size:10.5px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; color:var(--ink-3); background:#FAFBFE; }
    tbody tr:last-child td { border-bottom:0; }
    tbody tr.overdue { background:#FDF7F6; }
    td .mono { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12px; }
    td .mut { color:var(--ink-3); font-size:11.5px; }
    .amt { font-weight:700; font-variant-numeric:tabular-nums; }
    .pill { display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border-radius:999px; font-size:11px; font-weight:700; }
    .pill::before { content:""; width:6px; height:6px; border-radius:50%; }
    .pill.paid { background:var(--ok-soft); color:var(--ok); } .pill.paid::before { background:#16A34A; }
    .pill.unpaid { background:var(--warn-soft); color:var(--warn); } .pill.unpaid::before { background:#D97706; }
    .pill.pill.void { background:#F1F2F6; color:var(--ink-3); } .pill.void::before { background:#B8BCD0; }
    .late { font-size:10px; font-weight:800; color:var(--bad); margin-left:6px; text-transform:uppercase; letter-spacing:.05em; }
    .empty { padding:34px; text-align:center; color:var(--ink-3); }
    .todo { padding:26px; border:2px dashed #D6D9E6; border-radius:14px; background:#FBFBFE; }
    .todo code { background:var(--brand-soft); color:var(--brand-strong); border-radius:5px; padding:1px 7px; font-size:12px; }
    .foot { display:flex; justify-content:space-between; font-size:11px; color:var(--ink-3); }
    @media (max-width: 860px) { .plans, .stats { grid-template-columns:1fr 1fr; } .plans .plan:last-child { grid-column:1/-1; } }
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="wrap">
    <div class="head">
        <div>
            <h1>Billing</h1>
            <p class="sub">Subscription charges and collections — you set plan prices, generate invoices, record payments.</p>
        </div>
        <span class="live-tag">Live ledger</span>
    </div>

    <?php if ($flashError): ?><div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>
    <?php if ($flashOk): ?><div class="alert alert-ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>

    <?php if (!$billingReady): ?>
        <div class="card"><div class="todo">
            <b>Billing tables are not installed yet.</b><br><br>
            Run <code>database/2026_10_20_super_admin_control.sql</code> first, then <code>database/2026_10_20_billing.sql</code>
            against the master database (phpMyAdmin → SQL tab). Until then this page is inert — no charges can exist.
        </div></div>
    <?php else: ?>

    <div class="banner">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
        <span><b>How it works:</b> invoices are generated from the plan prices below, on this cycle (Standard = monthly, Annual = yearly). Collection stays manual — you mark invoices paid when money arrives. Auto-suspend only fires if you explicitly enable it.</span>
    </div>

    <div class="stats">
        <div class="stat"><span>MRR (live)</span><strong>₹<?= number_format($mrr, 0) ?></strong></div>
        <div class="stat ok"><span>Collected this month</span><strong>₹<?= number_format($collected, 0) ?></strong></div>
        <div class="stat warn"><span>Unpaid invoices</span><strong><?= $outstanding ?></strong></div>
        <div class="stat bad"><span>Overdue</span><strong><?= $overdueN ?></strong></div>
    </div>

    <div class="card">
        <div class="card-head">
            <div><h2>Plan prices</h2><span class="cap">Edits apply to invoices generated from now on — existing invoices never change retroactively.</span></div>
        </div>
        <form method="POST" action="/super-admin/actions/billing-action.php">
            <input type="hidden" name="act" value="save_plans">
            <div class="plans">
                <?php foreach ($plans as $p): ?>
                    <div class="plan">
                        <div class="nm"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="row"><span class="cur">₹</span>
                            <input type="number" min="0" step="1" name="plans[<?= (int) $p['id'] ?>][price]" value="<?= htmlspecialchars((string) (float) $p['price']) ?>">
                        </div>
                        <div class="term"><?= $p['code'] === 'trial' ? 'free — never invoiced' : ($p['code'] === 'annual' ? 'charged once per year' : 'charged monthly') ?></div>
                        <label class="onoff"><input type="checkbox" name="plans[<?= (int) $p['id'] ?>][active]" <?= $p['active'] ? 'checked' : '' ?>> Offered to new clients</label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:16px"><button type="submit" class="btn">Save prices</button></div>
        </form>
    </div>

    <div class="card">
        <div class="card-head">
            <div><h2>Run billing cycle</h2><span class="cap">Creates this month's / year's invoice for every billed client that isn't already covered.</span></div>
            <form method="POST" action="/super-admin/actions/billing-action.php" onsubmit="return confirm('Generate invoices for this cycle? Existing covered periods are skipped automatically.');">
                <input type="hidden" name="act" value="generate"><button type="submit" class="btn">Generate invoices</button>
            </form>
            <?php if (!empty($overdueN)): ?>
            <form method="POST" action="/super-admin/actions/billing-action.php" onsubmit="return confirm('Send a WhatsApp reminder for every overdue invoice (max 25, once per invoice per day)?');">
                <input type="hidden" name="act" value="remind_overdue"><button type="submit" class="btn" style="background:var(--warn-soft); color:var(--warn); border-color:#EFD9A9">Remind <?= (int) $overdueN ?> overdue</button>
            </form>
            <?php endif; ?>
        </div>
        <form method="POST" action="/super-admin/actions/billing-action.php">
            <input type="hidden" name="act" value="save_settings">
            <div class="setrow">
                <div class="field">
                    <label for="grace">Grace days before due</label>
                    <input type="number" id="grace" name="grace_days" min="0" max="60" value="<?= $grace ?>">
                </div>
                <div class="field">
                    <label for="upi">Your UPI ID (client pays here)</label>
                    <input type="text" id="upi" name="upi_id" value="<?= htmlspecialchars($upiId) ?>" placeholder="name@bank" style="padding:9px 11px; border:1px solid #D8DBE6; border-radius:9px; font-size:13.5px; width:210px; font-family:inherit">
                </div>
                <div class="field" style="flex:1; min-width:240px">
                    <label for="paynote">Payment instructions shown to clients</label>
                    <input type="text" id="paynote" name="pay_note" value="<?= htmlspecialchars($payNote) ?>" placeholder="e.g. UPI to above ID, mention your store name; receipts verified daily" style="padding:9px 11px; border:1px solid #D8DBE6; border-radius:9px; font-size:13.5px; width:100%; font-family:inherit">
                </div>
                <div class="co-sec">
                    <div class="co-sec-h">Company details <span>printed on every invoice</span></div>
                    <div class="co-grid">
                        <div class="pfx">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>
                            <input type="text" name="company_name" value="<?= htmlspecialchars($coName) ?>" placeholder="Company name" title="Company name">
                        </div>
                        <div class="pfx">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1116 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <input type="text" name="company_addr" value="<?= htmlspecialchars($coAddr) ?>" placeholder="Registered address" title="Registered address">
                        </div>
                        <div class="pfx">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3.1 19.5 19.5 0 01-6-6A19.8 19.8 0 012.1 4.2 2 2 0 014.1 2h3a2 2 0 012 1.7c.1 1 .4 2 .7 2.9a2 2 0 01-.5 2.1L8 10a16 16 0 006 6l1.3-1.3a2 2 0 012.1-.5c.9.3 1.9.6 2.9.7a2 2 0 011.7 2z"/></svg>
                            <input type="text" name="company_contact" value="<?= htmlspecialchars($coContact) ?>" placeholder="Phone" title="Phone">
                        </div>
                        <div class="pfx">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                            <input type="email" name="company_email" value="<?= htmlspecialchars($coEmail) ?>" placeholder="Billing email" title="Billing email">
                        </div>
                        <div class="pfx">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9h16M4 15h16M10 5L8 19M16 5l-2 14"/></svg>
                            <input type="text" name="company_gstin" value="<?= htmlspecialchars($coGstin) ?>" placeholder="GSTIN (optional)" title="GSTIN (optional)">
                        </div>
                    </div>
                </div>
                <div class="sw-wrap">
                    <label class="sw"><input type="checkbox" name="auto_suspend_overdue" <?= $autoSus ? 'checked' : '' ?>><i></i></label>
                    <span><b>Auto-suspend overdue clients</b> — when this cycle's invoice passes its due date unpaid (uses the live suspension gate).</span>
                </div>
                <div class="field"><button type="submit" class="btn-ghost">Save settings</button></div>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h2>Invoice ledger</h2><span class="cap">Newest first · <?= count($invs) ?> shown</span></div>
        <table>
            <thead><tr><th style="width:150px">Invoice</th><th>Client</th><th style="width:150px">Period</th><th style="width:86px" class="amt">Amount</th><th style="width:100px">Due</th><th style="width:122px">Status</th><th style="width:170px;text-align:right">Record</th></tr></thead>
            <tbody>
            <?php if (!$invs): ?>
                <tr><td colspan="7" class="empty">No invoices yet — set plan prices above, then run a billing cycle.</td></tr>
            <?php else: foreach ($invs as $i):
                $late = $i['status'] === 'unpaid' && $i['due_date'] && $i['due_date'] < $today;
                $daysLate = $late ? (int) ((strtotime($today) - strtotime($i['due_date'])) / 86400) : 0;
            ?>
                <tr class="<?= $late ? 'overdue' : '' ?>">
                    <td><span class="mono"><?= htmlspecialchars($i['invoice_no']) ?></span></td>
                    <td><b><?= htmlspecialchars($i['client_name']) ?></b><div class="mut"><?= htmlspecialchars($i['client_sub']) ?></div></td>
                    <td><span class="mut"><?= htmlspecialchars(date('d M', strtotime($i['period_from']))) ?> → <?= htmlspecialchars(date('d M Y', strtotime($i['period_to']))) ?></span></td>
                    <td class="amt">₹<?= number_format((float) $i['amount'], 0) ?></td>
                    <td><?= $i['due_date'] ? htmlspecialchars(date('d M Y', strtotime($i['due_date']))) : '—' ?></td>
                    <td><span class="pill <?= htmlspecialchars($i['status']) ?>"><?= htmlspecialchars($i['status']) ?></span><?= $late ? '<span class="late">' . $daysLate . 'd late</span>' : '' ?></td>
                    <td style="text-align:right;white-space:nowrap">
                        <a class="btn-ghost" href="/super-admin/billing-invoice.php?invoice_id=<?= (int) $i['id'] ?>" target="_blank" title="View document">View</a>
                        <a class="btn-ghost" href="/super-admin/billing-invoice.php?invoice_id=<?= (int) $i['id'] ?>&print=1" target="_blank" title="Print / save PDF">PDF</a>
                        <?php if ($i['status'] === 'unpaid'): ?>
                            <form method="POST" action="/super-admin/actions/billing-action.php" style="display:inline"><input type="hidden" name="act" value="pay"><input type="hidden" name="invoice_id" value="<?= (int) $i['id'] ?>"><button class="btn-ghost ok">Mark paid</button></form>
                            <form method="POST" action="/super-admin/actions/billing-action.php" style="display:inline" onsubmit="return confirm('WhatsApp a dues reminder for <?= htmlspecialchars(addslashes($i['invoice_no'])) ?> to <?= htmlspecialchars(addslashes($i['client_name'])) ?>?');"><input type="hidden" name="act" value="remind"><input type="hidden" name="invoice_id" value="<?= (int) $i['id'] ?>"><button class="btn-ghost" title="WhatsApp reminder — once per invoice per day">Remind</button></form>
                            <form method="POST" action="/super-admin/actions/billing-action.php" style="display:inline" onsubmit="return confirm('Void <?= htmlspecialchars(addslashes($i['invoice_no'])) ?>? Mistake-only — the record stays.');"><input type="hidden" name="act" value="void"><input type="hidden" name="invoice_id" value="<?= (int) $i['id'] ?>"><button class="btn-ghost danger">Void</button></form>
                        <?php elseif ($i['status'] === 'paid'): ?>
                            <span class="mut">paid <?= $i['paid_at'] ? htmlspecialchars(date('d M Y', strtotime($i['paid_at']))) : '' ?></span>
                            <form method="POST" action="/super-admin/actions/billing-action.php" style="display:inline" onsubmit="return confirm('Re-open this paid invoice as unpaid?');"><input type="hidden" name="act" value="unpay"><input type="hidden" name="invoice_id" value="<?= (int) $i['id'] ?>"><button class="btn-ghost">Un-pay</button></form>
                        <?php else: ?>
                            <span class="mut">voided</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="foot"><span>OPTMS·RX — Super Admin Console</span><span>Ledger rows are permanent records — mistakes are voided, never deleted.</span></div>
</div>
</body>
</html>
