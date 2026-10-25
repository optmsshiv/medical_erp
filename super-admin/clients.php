<?php
require __DIR__ . '/_guard.php';
require dirname(__DIR__) . '/core/Database.php';

$clients = Database::master()
    ->query('SELECT * FROM clients ORDER BY created_at DESC')
    ->fetchAll();

$appDomain = env('APP_DOMAIN', 'optms.co.in');

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$stats = ['total' => count($clients), 'active' => 0, 'trial' => 0, 'suspended' => 0];
foreach ($clients as $c) {
    $st = strtolower((string) ($c['status'] ?? ''));
    if (isset($stats[$st])) { $stats[$st]++; }
}
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

    /* ---------- top bar ---------- */
    .bar { position:sticky; top:0; z-index:20; background:rgba(255,255,255,.88); backdrop-filter:blur(10px); border-bottom:1px solid var(--line); }
    .bar-in { max-width:1080px; margin:0 auto; padding:0 22px; height:58px; display:flex; align-items:center; gap:22px; }
    .logo { display:flex; align-items:center; gap:9px; text-decoration:none; }
    .logo-mark { width:28px; height:28px; border-radius:8px; background:linear-gradient(135deg,#4F46E5,#3730A3); display:flex; align-items:center; justify-content:center; color:#fff; }
    .logo-mark svg { width:15px; height:15px; }
    .logo b { font-size:14.5px; letter-spacing:-.01em; background:linear-gradient(120deg,#312E81,#4F46E5); -webkit-background-clip:text; background-clip:text; color:transparent; }
    .logo .cap { font-size:9.5px; font-weight:800; letter-spacing:.14em; color:var(--ink-3); text-transform:uppercase; margin-left:2px; }
    .nav { display:flex; gap:4px; margin-left:8px; }
    .nav a {
        text-decoration:none; font-size:13px; font-weight:600; color:var(--ink-2);
        padding:7px 13px; border-radius:999px; transition:color .15s, background .15s;
    }
    .nav a:hover { color:var(--brand-strong); background:var(--brand-soft); }
    .nav a.on { color:var(--brand-strong); background:var(--brand-soft); }
    .bar-right { margin-left:auto; display:flex; align-items:center; gap:8px; }
    .session { display:inline-flex; align-items:center; gap:7px; font-size:11.5px; font-weight:600; color:var(--ink-2); border:1px solid var(--line); background:#fff; border-radius:999px; padding:6px 12px; }
    .session::before { content:""; width:6px; height:6px; border-radius:50%; background:#22C55E; }

    .wrap { max-width:1080px; margin:0 auto; padding:30px 22px 46px; }
    .head { display:flex; align-items:end; justify-content:space-between; gap:14px; flex-wrap:wrap; margin-bottom:20px; }
    h1 { font-size:21px; letter-spacing:-.02em; margin:0 0 3px; }
    .sub { color:var(--ink-2); margin:0; font-size:13px; }
    .count { font-size:12px; font-weight:650; color:var(--brand-strong); background:var(--brand-soft); border:1px solid #DFE3FB; border-radius:999px; padding:5px 12px; }

    /* ---------- stat strip ---------- */
    .stats { display:grid; grid-template-columns:repeat(4, 1fr); gap:12px; margin-bottom:22px; }
    .stat { background:#fff; border:1px solid var(--line); border-radius:15px; padding:14px 16px; box-shadow:0 1px 2px rgba(23,25,40,.04); }
    .stat span { display:block; font-size:10.5px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:var(--ink-3); }
    .stat strong { display:block; margin-top:4px; font-size:22px; font-weight:750; letter-spacing:-.03em; }
    .stat.ok strong { color:var(--ok); } .stat.warn strong { color:var(--warn); } .stat.bad strong { color:var(--bad); }

    /* ---------- panels & form ---------- */
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
    input {
        width:100%; padding:10.5px 12px; border:1px solid #D8DBE6; border-radius:10px; font-size:14px; color:var(--ink);
        background:#FCFCFE; transition:border-color .15s, box-shadow .15s, background .15s;
    }
    input::placeholder { color:#A7ACBA; }
    input:focus { outline:none; border-color:var(--brand); background:#fff; box-shadow:0 0 0 4px var(--ring); }
    .suffix { align-self:stretch; display:flex; align-items:center; gap:7px; margin-top:6px; font-size:11.5px; color:var(--ink-3); }
    .suffix code { background:var(--brand-soft); color:var(--brand-strong); border-radius:5px; padding:1px 6px; font-size:11px; }
    .hint { font-size:11.5px; color:var(--ink-3); margin-top:5px; }

    .actions { margin-top:22px; display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
    .btn {
        display:inline-flex; align-items:center; gap:8px; padding:11px 20px; border:0; border-radius:11px; cursor:pointer;
        background:linear-gradient(135deg,#4F46E5,#3B32C4); color:#fff; font-size:14px; font-weight:650; letter-spacing:.01em;
        box-shadow:0 6px 16px rgba(67,56,202,.26); transition:transform .15s, box-shadow .15s, filter .15s;
    }
    .btn svg { width:15px; height:15px; }
    .btn:hover { transform:translateY(-1px); box-shadow:0 10px 24px rgba(67,56,202,.32); filter:brightness(1.03); }
    .btn:active { transform:translateY(0); }
    .actions .note { font-size:11.5px; color:var(--ink-3); }

    /* ---------- flash alerts ---------- */
    .alert { display:flex; gap:12px; border-radius:14px; padding:16px 18px; margin-bottom:22px; font-size:13px; }
    .alert .ic { width:30px; height:30px; flex:none; border-radius:9px; display:flex; align-items:center; justify-content:center; }
    .alert .ic svg { width:15px; height:15px; }
    .alert-error { background:var(--bad-soft); border:1px solid #F1C9C4; color:var(--bad); }
    .alert-error .ic { background:#FAD9D4; }
    .alert-success { background:var(--ok-soft); border:1px solid #BFEBD0; color:#14532D; }
    .alert-success .ic { background:#CDF0DC; }
    .alert h3 { margin:0 0 3px; font-size:13.5px; }
    .alert p { margin:0 0 12px; font-size:12px; color:#3F6B51; }
    .cred-row { display:flex; align-items:center; gap:10px; margin-bottom:7px; }
    .cred-label { width:96px; flex:none; font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#3F8A5C; }
    .cred-value {
        font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12.5px;
        background:#fff; border:1px solid #BFEBD0; border-radius:7px; padding:5px 10px; color:#14532D;
    }
    .copy { border:1px solid #BFEBD0; background:#fff; color:#14532D; font-size:11px; font-weight:650; padding:5px 11px; border-radius:7px; cursor:pointer; transition:background .15s; }
    .copy:hover { background:#DCF3E6; }
    .cred-warn { margin-top:12px; padding-top:10px; border-top:1px dashed #A9DFC0; font-size:11.5px; color:#3F6B51; line-height:1.55; }
    .cred-warn code { background:#DCF3E6; border-radius:4px; padding:0 5px; }

    /* ---------- table ---------- */
    .tbl-card { background:#fff; border:1px solid var(--line); border-radius:16px; overflow:hidden; box-shadow:0 1px 2px rgba(23,25,40,.04), 0 14px 34px rgba(36,30,110,.05); }
    .tbl-head { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid var(--line); }
    .tbl-head b { font-size:13.5px; letter-spacing:-.01em; }
    .tbl-head span { font-size:11.5px; color:var(--ink-3); }
    table { width:100%; border-collapse:collapse; }
    th, td { text-align:left; padding:13px 18px; border-bottom:1px solid #EEF0F6; font-size:13px; vertical-align:middle; }
    th {
        font-size:10.5px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; color:var(--ink-3);
        background:#FAFBFE; border-bottom:1px solid var(--line);
    }
    tbody tr { transition:background .12s; }
    tbody tr:hover { background:#FAFBFF; }
    tbody tr:last-child td { border-bottom:0; }
    td .biz { font-weight:650; }
    td .mono { font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; color:var(--ink-2); }
    .dom { color:var(--brand-strong); font-weight:600; }
    .status { display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:.03em; }
    .status::before { content:""; width:6px; height:6px; border-radius:50%; }
    .status-trial { background:var(--warn-soft); color:var(--warn); } .status-trial::before { background:#D97706; }
    .status-active { background:var(--ok-soft); color:var(--ok); } .status-active::before { background:#16A34A; }
    .status-suspended { background:var(--bad-soft); color:var(--bad); } .status-suspended::before { background:#DC2626; }
    .empty { padding:52px 24px; text-align:center; color:var(--ink-3); }
    .empty .glyph { width:52px; height:52px; margin:0 auto 12px; border-radius:14px; background:var(--brand-soft); color:var(--brand); display:flex; align-items:center; justify-content:center; }
    .empty .glyph svg { width:24px; height:24px; }
    .empty b { display:block; color:var(--ink); font-size:14px; margin-bottom:3px; }

    .foot { margin-top:26px; display:flex; justify-content:space-between; font-size:11px; color:var(--ink-3); }

    @media (max-width: 760px) {
        .stats { grid-template-columns:repeat(2, 1fr); }
        .grid { grid-template-columns:1fr; }
        .bar-in { gap:12px; } .nav { overflow-x:auto; }
        th:nth-child(3), td:nth-child(3) { display:none; } /* database column collapses on small screens */
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
            <a href="/super-admin/dashboard.php" class="<?= $nav === 'dashboard' ? 'on' : '' ?>">Dashboard</a>
            <a href="/super-admin/clients.php" class="<?= $nav === 'clients' ? 'on' : '' ?>">Clients</a>
            <a href="/super-admin/billing.php" class="<?= $nav === 'billing' ? 'on' : '' ?>">Billing</a>
        </nav>
        <div class="bar-right"><span class="session">Key session</span></div>
    </div>
</div>

<div class="wrap">
    <div class="head">
        <div>
            <h1>Clients</h1>
            <p class="sub">Provision a pharmacy — this creates their database and their first admin login.</p>
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
            <div><h3>Provisioning failed</h3><?= htmlspecialchars($flashError) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success" role="status">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></span>
            <div style="flex:1">
                <h3>Client created — save these credentials now, they won't be shown again</h3>
                <p>Share them with the pharmacy over a private channel.</p>
                <div class="cred-row"><span class="cred-label">Login URL</span><span class="cred-value"><?= htmlspecialchars($flashSuccess['login_url']) ?></span><button type="button" class="copy" data-copy>Copy</button></div>
                <div class="cred-row"><span class="cred-label">Admin email</span><span class="cred-value"><?= htmlspecialchars($flashSuccess['admin_email']) ?></span><button type="button" class="copy" data-copy>Copy</button></div>
                <div class="cred-row"><span class="cred-label">Password</span><span class="cred-value"><?= htmlspecialchars($flashSuccess['admin_password']) ?></span><button type="button" class="copy" data-copy>Copy</button></div>
                <div class="cred-warn">The database <code><?= htmlspecialchars($flashSuccess['db_name']) ?></code> was created and the schema applied. Point <?= htmlspecialchars($flashSuccess['subdomain']) ?>.<?= htmlspecialchars($flashSuccess['domain']) ?> at this app before sharing the login URL.</div>
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
        <div class="tbl-head"><b>Provisioned clients</b><span>Newest first</span></div>
        <table>
            <thead>
                <tr><th style="width:26%">Business</th><th style="width:24%">Portal</th><th>Database</th><th style="width:110px">Status</th><th style="width:100px">Added</th></tr>
            </thead>
            <tbody>
                <?php if (!$clients): ?>
                    <tr><td colspan="5">
                        <div class="empty">
                            <div class="glyph"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg></div>
                            <b>No clients yet</b>
                            Provision your first pharmacy above — their console appears here.
                        </div>
                    </td></tr>
                <?php else: foreach ($clients as $c): ?>
                    <tr>
                        <td><span class="biz"><?= htmlspecialchars($c['name']) ?></span></td>
                        <td><span class="dom"><?= htmlspecialchars($c['subdomain']) ?>.<?= htmlspecialchars($appDomain) ?></span></td>
                        <td><span class="mono"><?= htmlspecialchars($c['db_name']) ?></span></td>
                        <td><span class="status status-<?= htmlspecialchars($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
                        <td><?= htmlspecialchars(date('d M Y', strtotime($c['created_at']))) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="foot"><span>OPTMS·RX — Super Admin Console</span><span>Tenant records live in the master database.</span></div>
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
</script>
</body>
</html>
