<?php
require __DIR__ . '/_guard.php';
require dirname(__DIR__) . '/core/Database.php';

$clients = Database::master()
    ->query('SELECT * FROM clients ORDER BY created_at DESC')
    ->fetchAll();

$appDomain = env('APP_DOMAIN', 'optms.co.in');

$flashSuccess = $_SESSION['flash_success'] ?? null;   // credential reveal (provision / password reset)
$flashOk      = $_SESSION['flash_ok_msg'] ?? null;    // quiet success line for control actions
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_ok_msg'], $_SESSION['flash_error']);

$stats = ['total' => count($clients), 'active' => 0, 'trial' => 0, 'suspended' => 0];
foreach ($clients as $c) {
    $st = strtolower((string) ($c['status'] ?? ''));
    if (isset($stats[$st])) { $stats[$st]++; }
}

$hasPlan = true; $hasTrial = true;
try { $hasPlan = (bool) Database::master()->query("SHOW COLUMNS FROM clients LIKE 'plan'")->fetch(); } catch (Throwable $e) { $hasPlan = false; }
try { $hasTrial = (bool) Database::master()->query("SHOW COLUMNS FROM clients LIKE 'trial_ends_at'")->fetch(); } catch (Throwable $e) { $hasTrial = false; }

$today = date('Y-m-d');
$auditRows = [];
try { $auditRows = Database::master()->query('SELECT action, detail, created_at FROM admin_audit ORDER BY id DESC LIMIT 8')->fetchAll(); }
catch (Throwable $e) { $auditRows = []; }

$nav = 'clients';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Clients — OPTMS-RX Admin</title>
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
    .count { font-size:12px; font-weight:650; color:var(--brand-strong); background:var(--brand-soft); border:1px solid #DFE3FB; border-radius:999px; padding:5px 12px; }

    .stats { display:grid; grid-template-columns:repeat(4, 1fr); gap:12px; margin-bottom:22px; }
    .stat { background:#fff; border:1px solid var(--line); border-radius:15px; padding:14px 16px; box-shadow:0 1px 2px rgba(23,25,40,.04); }
    .stat span { display:block; font-size:10.5px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:var(--ink-3); }
    .stat strong { display:block; margin-top:4px; font-size:22px; font-weight:750; letter-spacing:-.03em; }
    .stat.ok strong { color:var(--ok); } .stat.warn strong { color:var(--warn); } .stat.bad strong { color:var(--bad); }

    .panel { background:#fff; border:1px solid var(--line); border-radius:16px; padding:26px; margin-bottom:26px; box-shadow:0 1px 2px rgba(23,25,40,.04), 0 14px 34px rgba(36,30,110,.05); }
    .panel-head { display:flex; align-items:center; gap:11px; margin:0 0 20px; }
    .panel-head .ic { width:34px; height:34px; border-radius:10px; background:var(--brand-soft); color:var(--brand); display:flex; align-items:center; justify-content:center; }
    .panel-head .ic svg { width:16px; height:16px; }
    .panel-head h2 { font-size:15px; letter-spacing:-.01em; margin:0; }
    .panel-head p { margin:1px 0 0; font-size:12px; color:var(--ink-2); }
    .grid { display:grid; grid-template-columns:1fr 1fr; gap:15px 16px; }
    .field { display:flex; flex-direction:column; }
    .field.full { grid-column:1 / -1; }
    label { font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--ink-3); margin-bottom:6px; }
    .control { position:relative; display:flex; align-items:center; }
    .control svg { position:absolute; left:12px; width:14px; height:14px; color:var(--ink-3); pointer-events:none; }
    .control input { padding-left:34px; }
    input, select {
        width:100%; padding:10.5px 12px; border:1px solid #D8DBE6; border-radius:10px; font-size:14px; color:var(--ink);
        background:#FCFCFE; transition:border-color .15s, box-shadow .15s, background .15s; font-family:inherit;
    }
    input::placeholder { color:#A7ACBA; }
    input:focus, select:focus { outline:none; border-color:var(--brand); background:#fff; box-shadow:0 0 0 4px var(--ring); }
    .suffix { align-self:stretch; display:flex; align-items:center; gap:7px; margin-top:6px; font-size:11.5px; color:var(--ink-3); }
    .suffix code { background:var(--brand-soft); color:var(--brand-strong); border-radius:5px; padding:1px 6px; font-size:11px; }
    .hint { font-size:11.5px; color:var(--ink-3); margin-top:5px; }
    .actions { margin-top:22px; display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
    .btn {
        display:inline-flex; align-items:center; gap:8px; padding:11px 20px; border:0; border-radius:11px; cursor:pointer;
        background:linear-gradient(135deg,#4F46E5,#3B32C4); color:#fff; font-size:14px; font-weight:650; letter-spacing:.01em;
        box-shadow:0 6px 16px rgba(67,56,202,.26); transition:transform .15s, box-shadow .15s, filter .15s; font-family:inherit;
    }
    .btn svg { width:15px; height:15px; }
    .btn:hover { transform:translateY(-1px); box-shadow:0 10px 24px rgba(67,56,202,.32); filter:brightness(1.03); }
    .btn:active { transform:translateY(0); }
    .actions .note { font-size:11.5px; color:var(--ink-3); }

    .alert { display:flex; gap:12px; border-radius:14px; padding:16px 18px; margin-bottom:22px; font-size:13px; }
    .alert .ic { width:30px; height:30px; flex:none; border-radius:9px; display:flex; align-items:center; justify-content:center; }
    .alert .ic svg { width:15px; height:15px; }
    .alert-error { background:var(--bad-soft); border:1px solid #F1C9C4; color:var(--bad); }
    .alert-error .ic { background:#FAD9D4; }
    .alert-ok { background:var(--ok-soft); border:1px solid #BFEBD0; color:#14532D; align-items:center; }
    .alert-ok .ic { background:#CDF0DC; }
    .alert-success { background:var(--ok-soft); border:1px solid #BFEBD0; color:#14532D; }
    .alert-success .ic { background:#CDF0DC; }
    .alert h3 { margin:0 0 3px; font-size:13.5px; }
    .alert p { margin:0 0 12px; font-size:12px; color:#3F6B51; }
    .cred-row { display:flex; align-items:center; gap:10px; margin-bottom:7px; }
    .cred-label { width:96px; flex:none; font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#3F8A5C; }
    .cred-value { font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12.5px; background:#fff; border:1px solid #BFEBD0; border-radius:7px; padding:5px 10px; color:#14532D; }
    .copy { border:1px solid #BFEBD0; background:#fff; color:#14532D; font-size:11px; font-weight:650; padding:5px 11px; border-radius:7px; cursor:pointer; transition:background .15s; }
    .copy:hover { background:#DCF3E6; }
    .cred-warn { margin-top:12px; padding-top:10px; border-top:1px dashed #A9DFC0; font-size:11.5px; color:#3F6B51; line-height:1.55; }
    .cred-warn code { background:#DCF3E6; border-radius:4px; padding:0 5px; }

    .tbl-card { background:#fff; border:1px solid var(--line); border-radius:16px; overflow:hidden; box-shadow:0 1px 2px rgba(23,25,40,.04), 0 14px 34px rgba(36,30,110,.05); margin-bottom:26px; }
    .tbl-head { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid var(--line); }
    .tbl-head b { font-size:13.5px; letter-spacing:-.01em; }
    .tbl-head span { font-size:11.5px; color:var(--ink-3); }
    table { width:100%; border-collapse:collapse; }
    th, td { text-align:left; padding:13px 16px; border-bottom:1px solid #EEF0F6; font-size:13px; vertical-align:middle; }
    th { font-size:10.5px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; color:var(--ink-3); background:#FAFBFE; border-bottom:1px solid var(--line); }
    tbody tr { transition:background .12s; }
    tbody tr:hover { background:#FAFBFF; }
    tbody tr:last-child td { border-bottom:0; }
    tr.is-suspended { background:#FCF6F6; }
    td .biz { font-weight:650; }
    td .mono { font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; color:var(--ink-2); }
    .dom { color:var(--brand-strong); font-weight:600; }
    .subchips { display:flex; gap:6px; margin-top:5px; flex-wrap:wrap; }
    .planchip { font-size:10px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; border-radius:6px; padding:2px 8px; background:var(--brand-soft); color:var(--brand-strong); border:1px solid #DFE3FB; }
    .trialchip { font-size:10px; font-weight:750; border-radius:6px; padding:2px 8px; background:var(--warn-soft); color:var(--warn); border:1px solid #F0DCB0; }
    .trialchip.over { background:var(--bad-soft); color:var(--bad); border-color:#F1C9C4; }
    .status { display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:.03em; }
    .status::before { content:""; width:6px; height:6px; border-radius:50%; }
    .status-trial { background:var(--warn-soft); color:var(--warn); } .status-trial::before { background:#D97706; }
    .status-active { background:var(--ok-soft); color:var(--ok); } .status-active::before { background:#16A34A; }
    .status-suspended { background:var(--bad-soft); color:var(--bad); } .status-suspended::before { background:#DC2626; }

    .acts { display:flex; gap:5px; justify-content:flex-end; }
    .act {
        width:30px; height:30px; border:1px solid var(--line); background:#fff; border-radius:9px; cursor:pointer;
        display:inline-flex; align-items:center; justify-content:center; color:var(--ink-2); transition:all .15s;
    }
    .act svg { width:14px; height:14px; }
    .act:hover { border-color:var(--brand); color:var(--brand-strong); background:var(--brand-soft); }
    .act.danger:hover { border-color:#F1C9C4; color:var(--bad); background:var(--bad-soft); }
    .act.ok:hover { border-color:#BFEBD0; color:var(--ok); background:var(--ok-soft); }
    .act.w30 { width:auto; padding:0 10px; font-size:11px; font-weight:700; gap:6px; }

    .empty { padding:52px 24px; text-align:center; color:var(--ink-3); }
    .empty .glyph { width:52px; height:52px; margin:0 auto 12px; border-radius:14px; background:var(--brand-soft); color:var(--brand); display:flex; align-items:center; justify-content:center; }
    .empty .glyph svg { width:24px; height:24px; }
    .empty b { display:block; color:var(--ink); font-size:14px; margin-bottom:3px; }

    /* ---- audit strip ---- */
    .audit li { display:flex; gap:12px; padding:8px 0; border-bottom:1px solid #EFF1F6; font-size:12.5px; }
    .audit li:last-child { border-bottom:0; }
    .audit time { flex:none; width:118px; color:var(--ink-3); font-size:11.5px; padding-top:1px; }
    .audit code { font-size:10px; font-weight:800; letter-spacing:.06em; background:var(--brand-soft); color:var(--brand-strong); border-radius:5px; padding:2px 7px; flex:none; height:fit-content; }
    .audit .d { color:var(--ink-2); }
    .audit-empty { padding:16px 4px; color:var(--ink-3); font-size:12.5px; }

    /* ---- modals ---- */
    .ov { position:fixed; inset:0; background:rgba(26,29,39,.44); backdrop-filter:blur(3px); display:none; align-items:center; justify-content:center; z-index:50; padding:20px; }
    .ov.show { display:flex; }
    .modal { width:min(500px, 100%); background:#fff; border-radius:18px; box-shadow:0 30px 80px rgba(23,25,40,.35); padding:26px; max-height:86vh; overflow:auto; }
    .modal h3 { font-size:16px; margin:0 0 2px; letter-spacing:-.015em; }
    .modal .msub { font-size:12.5px; color:var(--ink-2); margin:0 0 18px; }
    .modal .row2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .modal .macts { display:flex; gap:10px; justify-content:flex-end; margin-top:22px; }
    .btn-ghost { border:1px solid var(--line); background:#fff; color:var(--ink-2); font-size:13px; font-weight:650; padding:10px 16px; border-radius:11px; cursor:pointer; }
    .btn-ghost:hover { background:#F5F6FA; }
    .frow { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 0; border-bottom:1px solid #EFF1F6; }
    .frow:last-child { border-bottom:0; }
    .frow .fl { font-size:13px; font-weight:600; }
    .frow .fl span { display:block; font-size:11px; font-weight:500; color:var(--ink-3); margin-top:1px; }
    .sw { position:relative; width:40px; height:22px; flex:none; }
    .sw input { position:absolute; opacity:0; width:0; height:0; }
    .sw i { position:absolute; inset:0; border-radius:999px; background:#DDE0EA; cursor:pointer; transition:background .15s; }
    .sw i:before { content:""; position:absolute; left:3px; top:3px; width:16px; height:16px; border-radius:50%; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.2); transition:transform .15s; }
    .sw input:checked + i { background:var(--brand); }
    .sw input:checked + i:before { transform:translateX(18px); }
    .foot { margin-top:8px; display:flex; justify-content:space-between; font-size:11px; color:var(--ink-3); }

    @media (max-width: 820px) {
        .stats { grid-template-columns:repeat(2, 1fr); }
        .grid, .modal .row2 { grid-template-columns:1fr; }
        th:nth-child(3), td:nth-child(3) { display:none; }
        .audit time { width:auto; }
    }
</style>
</head>
<body>
<div class="bar">
    <div class="bar-in">
        <a class="logo" href="/super-admin/clients.php">
            <span class="logo-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.4-2.9 8.2-7 9.5-4.1-1.3-7-5.1-7-9.5V6l7-3z"/><path d="M9.2 12.1l2 2 3.9-4.2"/></svg></span>
            <span><b>OPTMS·RX</b><span class="cap">Super Admin</span></span>
        </a>
        <nav class="nav">
            <a href="/super-admin/dashboard.php">Dashboard</a>
            <a href="/super-admin/clients.php" class="on">Clients</a>
            <a href="/super-admin/billing.php">Billing</a>
        </nav>
        <div class="bar-right"><span class="session">Key session</span></div>
    </div>
</div>

<div class="wrap">
    <div class="head">
        <div>
            <h1>Clients</h1>
            <p class="sub">Provision, control and support pharmacies on the platform.</p>
        </div>
        <span class="count"><?= $stats['total'] ?> client<?= $stats['total'] === 1 ? '' : 's' ?></span>
    </div>

    <div class="stats">
        <div class="stat"><span>Total clients</span><strong><?= $stats['total'] ?></strong></div>
        <div class="stat ok"><span>Active</span><strong><?= $stats['active'] ?></strong></div>
        <div class="stat warn"><span>On trial</span><strong><?= $stats['trial'] ?></strong></div>
        <div class="stat bad"><span>Suspended</span><strong><?= $stats['suspended'] ?></strong></div>
    </div>

    <?php if ($flashError): ?>
        <div class="alert alert-error" role="alert">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4.5M12 16h.01"/></svg></span>
            <div><h3>Action failed</h3><?= htmlspecialchars($flashError) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($flashOk): ?>
        <div class="alert alert-ok" role="status">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></span>
            <div><?= htmlspecialchars($flashOk) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success" role="status">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></span>
            <div style="flex:1">
                <h3>Credentials — save these now, they won't be shown again</h3>
                <p>Share them with the pharmacy over a private channel.</p>
                <div class="cred-row"><span class="cred-label">Login URL</span><span class="cred-value"><?= htmlspecialchars($flashSuccess['login_url']) ?></span><button type="button" class="copy" data-copy>Copy</button></div>
                <div class="cred-row"><span class="cred-label">Admin email</span><span class="cred-value"><?= htmlspecialchars($flashSuccess['admin_email']) ?></span><button type="button" class="copy" data-copy>Copy</button></div>
                <div class="cred-row"><span class="cred-label">Password</span><span class="cred-value"><?= htmlspecialchars($flashSuccess['admin_password']) ?></span><button type="button" class="copy" data-copy>Copy</button></div>
                <?php if (!empty($flashSuccess['db_name'])): ?>
                <div class="cred-warn">Database <code><?= htmlspecialchars($flashSuccess['db_name']) ?></code> · portal <?= htmlspecialchars($flashSuccess['subdomain']) ?>.<?= htmlspecialchars($flashSuccess['domain']) ?> — point the subdomain at this app before sharing the login URL.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel-head">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg></span>
            <div><h2>Provision a new client</h2><p>Tenant database, schema and first admin login are created in one step.</p></div>
        </div>
        <form method="POST" action="/super-admin/actions/create-client.php">
            <div class="grid">
                <div class="field">
                    <label for="name">Business name</label>
                    <div class="control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M15 9h.01M9 13h.01M15 13h.01M9 17h6"/></svg>
                        <input type="text" id="name" name="name" placeholder="Green Pharma, Madhepura" required>
                    </div>
                </div>
                <div class="field">
                    <label for="subdomain">Subdomain</label>
                    <div class="control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a13.4 13.4 0 010 18M12 3a13.4 13.4 0 000 18"/></svg>
                        <input type="text" id="subdomain" name="subdomain" placeholder="greenpharma" required>
                    </div>
                    <div class="suffix">Becomes <code>greenpharma.<?= htmlspecialchars($appDomain) ?></code></div>
                </div>
                <div class="field">
                    <label for="admin_name">Admin contact name</label>
                    <div class="control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-3.3 3.6-5 8-5s8 1.7 8 5"/></svg>
                        <input type="text" id="admin_name" name="admin_name" placeholder="Ramesh Kumar">
                    </div>
                </div>
                <div class="field">
                    <label for="admin_email">Admin login email</label>
                    <div class="control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                        <input type="email" id="admin_email" name="admin_email" placeholder="ramesh@greenpharma.example" required>
                    </div>
                </div>
                <div class="field full">
                    <label for="admin_password">Admin password</label>
                    <div class="control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/></svg>
                        <input type="text" id="admin_password" name="admin_password" placeholder="Leave blank to auto-generate a secure password">
                    </div>
                    <div class="hint">Recommended blank — a strong random password is generated and shown once, after creation.</div>
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                    Provision client
                </button>
                <span class="note">Provisioning is all-or-nothing — failed attempts roll back cleanly.</span>
            </div>
        </form>
    </div>

    <div class="tbl-card">
        <div class="tbl-head"><b>Provisioned clients</b><span><?= ($hasPlan && $hasTrial) ? 'Full control enabled' : 'Run the control-plane migration to unlock plans &amp; trials' ?></span></div>
        <table>
            <thead>
                <tr><th style="width:24%">Business</th><th style="width:20%">Portal</th><th>Database</th><th style="width:118px">Status</th><th style="width:92px">Added</th><th style="width:150px;text-align:right">Controls</th></tr>
            </thead>
            <tbody>
                <?php if (!$clients): ?>
                    <tr><td colspan="6">
                        <div class="empty">
                            <div class="glyph"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg></div>
                            <b>No clients yet</b>
                            Provision your first pharmacy above — their console appears here.
                        </div>
                    </td></tr>
                <?php else: foreach ($clients as $c):
                    $cStatus  = strtolower((string) ($c['status'] ?? 'trial'));
                    $cPlan    = (string) ($c['plan'] ?? 'trial');
                    $cTrial   = (string) ($c['trial_ends_at'] ?? '');
                    $trialTxt = ''; $trialCls = 'trialchip';
                    if ($cTrial !== '') {
                        $days = (int) ((strtotime($cTrial) - strtotime($today)) / 86400);
                        if ($days < 0) { $trialTxt = 'trial ended ' . abs($days) . 'd ago'; $trialCls .= ' over'; }
                        else { $trialTxt = 'trial · ' . $days . 'd left'; }
                    }
                ?>
                    <tr class="<?= $cStatus === 'suspended' ? 'is-suspended' : '' ?>">
                        <td>
                            <span class="biz"><?= htmlspecialchars($c['name']) ?></span>
                            <span class="subchips">
                                <?php if ($hasPlan): ?><span class="planchip"><?= htmlspecialchars($cPlan) ?></span><?php endif; ?>
                                <?php if ($hasTrial && $trialTxt !== ''): ?><span class="<?= $trialCls ?>"><?= htmlspecialchars($trialTxt) ?></span><?php endif; ?>
                            </span>
                        </td>
                        <td><span class="dom"><?= htmlspecialchars($c['subdomain']) ?>.<?= htmlspecialchars($appDomain) ?></span></td>
                        <td><span class="mono"><?= htmlspecialchars($c['db_name']) ?></span></td>
                        <td><span class="status status-<?= htmlspecialchars($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
                        <td><?= htmlspecialchars(date('d M Y', strtotime($c['created_at']))) ?></td>
                        <td>
                            <div class="acts">
                                <?php if ($cStatus === 'suspended'): ?>
                                    <form method="POST" action="/super-admin/actions/client-action.php" style="display:inline"><input type="hidden" name="client_id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="act" value="resume">
                                        <button type="submit" class="act ok" title="Resume this client"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4l12 8-12 8z"/></svg></button></form>
                                <?php else: ?>
                                    <form method="POST" action="/super-admin/actions/client-action.php" style="display:inline" onsubmit="return confirm('Suspend <?= htmlspecialchars(addslashes($c['name'])) ?>? Their console stops at the gate until you resume it.');"><input type="hidden" name="client_id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="act" value="suspend">
                                        <button type="submit" class="act danger" title="Suspend this client"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M9 12h6"/></svg></button></form>
                                <?php endif; ?>
                                <form method="POST" action="/super-admin/actions/client-password.php" style="display:inline" onsubmit="return confirm('Reset the primary admin password for <?= htmlspecialchars(addslashes($c['name'])) ?>? The new password shows once.');"><input type="hidden" name="client_id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="act" title="Reset admin password"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 018 0v3M12 15v2"/></svg></button></form>
                                <button type="button" class="act" title="Trial &amp; plan" data-trial data-id="<?= (int) $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>" data-trialdate="<?= htmlspecialchars($cTrial) ?>" data-plan="<?= htmlspecialchars($cPlan) ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg></button>
                                <button type="button" class="act" title="Feature control" data-feat data-id="<?= (int) $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h10M18 8h2M4 16h2M10 16h10"/><circle cx="16" cy="8" r="2.5"/><circle cx="7" cy="16" r="2.5"/></svg></button>
                                <a class="act w30" href="/super-admin/actions/client-backup.php?client_id=<?= (int) $c['id'] ?>" title="Download database backup">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M6 11l6 6 6-6M4 21h16"/></svg></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <div class="panel-head">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12a8 8 0 1116 0 8 8 0 01-16 0z"/><path d="M12 8v4l2.5 2.5"/></svg></span>
            <div><h2>Recent control actions</h2><p>Every suspend, reset, plan and feature change lands here.</p></div>
        </div>
        <?php if (!$auditRows): ?>
            <div class="audit-empty">No control actions recorded yet.</div>
        <?php else: ?>
            <ul class="audit" style="list-style:none;margin:0;padding:0">
                <?php foreach ($auditRows as $a): ?>
                    <li><time><?= htmlspecialchars(date('d M · H:i', strtotime($a['created_at']))) ?></time><code><?= htmlspecialchars($a['action']) ?></code><span class="d"><?= htmlspecialchars($a['detail']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="foot"><span>OPTMS·RX — Super Admin Console</span><span>Suspension is enforced at the tenant gate once you apply TENANT-ENFORCEMENT.md.</span></div>
</div>

<!-- Trial & plan modal -->
<div class="ov" id="trialOv">
    <div class="modal">
        <h3>Trial &amp; plan</h3>
        <p class="msub" id="trialWho"></p>
        <form method="POST" action="/super-admin/actions/client-action.php">
            <input type="hidden" name="client_id" id="trialId">
            <div class="row2">
                <div class="field">
                    <label for="trialDate">Trial ends on</label>
                    <input type="date" id="trialDate" name="trial_ends_at">
                    <div class="hint">Setting a date puts the client back on trial.</div>
                </div>
                <div class="field">
                    <label for="planSel">Plan</label>
                    <select id="planSel" name="plan">
                        <option value="trial">Trial</option>
                        <option value="standard">Standard</option>
                        <option value="annual">Annual</option>
                    </select>
                    <div class="hint">Plan change keeps the current status.</div>
                </div>
            </div>
            <div class="macts">
                <button type="button" class="btn-ghost" data-close>Cancel</button>
                <button type="submit" name="act" value="save_plan_trial" class="btn">Save changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Feature matrix modal -->
<div class="ov" id="featOv">
    <div class="modal">
        <h3>Feature control</h3>
        <p class="msub" id="featWho"></p>
        <form method="POST" action="/super-admin/actions/client-features.php">
            <input type="hidden" name="client_id" id="featId">
            <div id="featRows"><p class="msub">Loading the current matrix…</p></div>
            <div class="macts">
                <button type="button" class="btn-ghost" data-close>Cancel</button>
                <button type="submit" class="btn">Save feature set</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
        var value = b.parentElement.querySelector('.cred-value').textContent;
        var done = function () { var t = b.textContent; b.textContent = 'Copied'; setTimeout(function () { b.textContent = t; }, 1400); };
        if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(value).then(done).catch(done); }
        else { var r = document.createRange(); r.selectNodeContents(b.parentElement.querySelector('.cred-value')); var s = getSelection(); s.removeAllRanges(); s.addRange(r); done(); }
    });
});

const LABELS = {
    wholesale: ['Wholesale billing', 'B2B counter & credit notes'],
    quick_refill: ['Quick Refill', 'Repeat a regular customer\u2019s previous prescription'],
    rx_inbox: ['Rx inbox', 'Scan & send prescriptions from the counter'],
    compliance_export: ['Compliance exports', 'H1/X register PDF & Excel'],
    customer_dues: ['Customer dues', 'Credit book & due reminders'],
    expenses: ['Expense tracking', 'Shop expense ledger'],
    supplier_dues: ['Supplier dues', 'Outstanding payables'],
    price_bulk: ['Bulk price update', 'Batch price corrections']
};

function openOv(id) { document.getElementById(id).classList.add('show'); }
document.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', function () { b.closest('.ov').classList.remove('show'); }); });
document.querySelectorAll('.ov').forEach(function (o) { o.addEventListener('click', function (e) { if (e.target === o) o.classList.remove('show'); }); });

document.querySelectorAll('[data-trial]').forEach(function (b) {
    b.addEventListener('click', function () {
        document.getElementById('trialId').value = b.dataset.id;
        document.getElementById('trialWho').textContent = b.dataset.name;
        document.getElementById('trialDate').value = b.dataset.trialdate || '';
        document.getElementById('planSel').value = b.dataset.plan || 'trial';
        openOv('trialOv');
    });
});

document.querySelectorAll('[data-feat]').forEach(function (b) {
    b.addEventListener('click', function () {
        document.getElementById('featId').value = b.dataset.id;
        document.getElementById('featWho').textContent = b.dataset.name;
        const host = document.getElementById('featRows');
        host.innerHTML = '<p class="msub">Loading the current matrix…</p>';
        openOv('featOv');
        fetch('actions/client-features.php?client_id=' + encodeURIComponent(b.dataset.id), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.features) throw new Error('bad');
                host.innerHTML = '';
                Object.keys(LABELS).forEach(function (key) {
                    const on = d.features[key] !== false;
                    const row = document.createElement('div');
                    row.className = 'frow';
                    row.innerHTML = '<div class="fl">' + LABELS[key][0] + '<span>' + LABELS[key][1] + '</span></div>' +
                        '<label class="sw"><input type="checkbox" name="f_' + key + '"' + (on ? ' checked' : '') + '><i></i></label>';
                    host.appendChild(row);
                });
            })
            .catch(function () {
                host.innerHTML = '<p class="msub">Could not load the current matrix — the switches below show defaults (everything on). Saving persists your choices.</p>';
                Object.keys(LABELS).forEach(function (key) {
                    const row = document.createElement('div');
                    row.className = 'frow';
                    row.innerHTML = '<div class="fl">' + LABELS[key][0] + '<span>' + LABELS[key][1] + '</span></div>' +
                        '<label class="sw"><input type="checkbox" name="f_' + key + '" checked><i></i></label>';
                    host.appendChild(row);
                });
            });
    });
});
</script>
</body>
</html>
