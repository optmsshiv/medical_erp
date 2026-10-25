<?php
require __DIR__ . '/_guard.php';
require dirname(__DIR__) . '/core/Database.php';

$clients = Database::master()
    ->query('SELECT * FROM clients ORDER BY created_at DESC')
    ->fetchAll();

$appDomain = env('APP_DOMAIN', 'optms.co.in');

$stats = ['total' => count($clients), 'active' => 0, 'trial' => 0, 'suspended' => 0];
foreach ($clients as $c) {
    $st = strtolower((string) ($c['status'] ?? ''));
    if (isset($stats[$st])) { $stats[$st]++; }
}
$recent = array_slice($clients, 0, 5);
$nav = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — OPTMS-RX Admin</title>
<style>
    :root {
        --ink:#1A1D27; --ink-2:#5B6270; --ink-3:#8A8F9A; --line:#E5E7F0; --bg:#F4F5F9;
        --brand:#4338CA; --brand-strong:#3730A3; --brand-soft:#EEF0FE; --ring:rgba(67,56,202,.14);
        --ok:#1B6B3C; --ok-soft:#E8F7EE; --warn:#93650A; --warn-soft:#FFF3DC; --bad:#9A2E24; --bad-soft:#FDECEC;
    }
    * { box-sizing:border-box; }
    body { margin:0; background:var(--bg); color:var(--ink); font-family:-apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", Roboto, sans-serif; font-feature-settings:"cv02","cv03","cv04"; font-size:14px; }
    .bar { position:sticky; top:0; z-index:20; background:rgba(255,255,255,.88); backdrop-filter:blur(10px); border-bottom:1px solid var(--line); }
    .bar-in { max-width:1080px; margin:0 auto; padding:0 22px; height:58px; display:flex; align-items:center; gap:22px; }
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

    .wrap { max-width:1080px; margin:0 auto; padding:30px 22px 46px; }
    h1 { font-size:21px; letter-spacing:-.02em; margin:0 0 3px; }
    .sub { color:var(--ink-2); margin:0; font-size:13px; }
    .head { margin-bottom:22px; }

    .stats { display:grid; grid-template-columns:repeat(4, 1fr); gap:12px; margin-bottom:22px; }
    .stat { background:#fff; border:1px solid var(--line); border-radius:15px; padding:14px 16px; box-shadow:0 1px 2px rgba(23,25,40,.04); }
    .stat span { display:block; font-size:10.5px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:var(--ink-3); }
    .stat strong { display:block; margin-top:4px; font-size:22px; font-weight:750; letter-spacing:-.03em; }
    .stat.ok strong { color:var(--ok); } .stat.warn strong { color:var(--warn); } .stat.bad strong { color:var(--bad); }

    .cols { display:grid; grid-template-columns:1.6fr 1fr; gap:16px; align-items:start; }
    .card { background:#fff; border:1px solid var(--line); border-radius:16px; padding:20px; box-shadow:0 1px 2px rgba(23,25,40,.04), 0 14px 34px rgba(36,30,110,.05); }
    .card h2 { font-size:14px; letter-spacing:-.01em; margin:0 0 2px; }
    .card .cap { font-size:11.5px; color:var(--ink-3); margin:0 0 16px; }
    .card-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
    .card-head h2 { margin:0; }
    .preview-tag { font-size:9.5px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:var(--brand-strong); background:var(--brand-soft); border:1px solid #DFE3FB; border-radius:999px; padding:3px 9px; }

    .row { display:flex; align-items:center; gap:12px; padding:11px 0; border-bottom:1px solid #EFF1F6; }
    .row:last-child { border-bottom:0; }
    .av { width:34px; height:34px; flex:none; border-radius:10px; background:var(--brand-soft); color:var(--brand-strong); display:flex; align-items:center; justify-content:center; font-size:12.5px; font-weight:800; letter-spacing:.02em; }
    .row .who { flex:1; min-width:0; }
    .row .who b { display:block; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .row .who span { font-size:11.5px; color:var(--ink-3); }
    .status { display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border-radius:999px; font-size:11px; font-weight:700; }
    .status::before { content:""; width:6px; height:6px; border-radius:50%; }
    .status-trial { background:var(--warn-soft); color:var(--warn); } .status-trial::before { background:#D97706; }
    .status-active { background:var(--ok-soft); color:var(--ok); } .status-active::before { background:#16A34A; }
    .status-suspended { background:var(--bad-soft); color:var(--bad); } .status-suspended::before { background:#DC2626; }

    .mrr { display:flex; align-items:end; gap:8px; margin:2px 0 14px; }
    .mrr strong { font-size:30px; font-weight:750; letter-spacing:-.03em; }
    .mrr span { font-size:12px; color:var(--ink-3); padding-bottom:6px; }
    .bars { display:flex; align-items:flex-end; gap:7px; height:74px; padding-top:8px; border-top:1px solid #EFF1F6; }
    .bars i { flex:1; border-radius:6px 6px 3px 3px; background:linear-gradient(180deg,#A5B4FC,#6366F1); opacity:.9; }
    .bars i:nth-child(1){height:26%} .bars i:nth-child(2){height:34%} .bars i:nth-child(3){height:42%}
    .bars i:nth-child(4){height:50%} .bars i:nth-child(5){height:60%} .bars i:nth-child(6){height:72%}
    .bars i:nth-child(7){height:88%} .bars i:nth-child(8){height:100%}
    .mock-note { margin-top:12px; font-size:11px; color:var(--ink-3); line-height:1.5; }

    .cta { margin-top:16px; }
    .cta a { display:block; text-align:center; text-decoration:none; font-size:13px; font-weight:650; color:var(--brand-strong); background:var(--brand-soft); border:1px solid #DFE3FB; border-radius:11px; padding:11px; transition:background .15s; }
    .cta a:hover { background:#E2E6FD; }
    .linkall { display:inline-block; margin-top:14px; font-size:12px; font-weight:650; color:var(--brand-strong); text-decoration:none; }
    .linkall:hover { text-decoration:underline; }

    .empty-mini { padding:26px 10px; text-align:center; color:var(--ink-3); font-size:12.5px; }
    .foot { margin-top:26px; display:flex; justify-content:space-between; font-size:11px; color:var(--ink-3); }

    @media (max-width: 820px) { .cols { grid-template-columns:1fr; } .stats { grid-template-columns:repeat(2,1fr); } }
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
        <h1>Platform overview</h1>
        <p class="sub">Live tenant counts from the master database. Revenue widgets are layout previews until billing ships.</p>
    </div>

    <div class="stats">
        <div class="stat"><span>Total clients</span><strong><?= $stats['total'] ?></strong></div>
        <div class="stat ok"><span>Active</span><strong><?= $stats['active'] ?></strong></div>
        <div class="stat warn"><span>On trial</span><strong><?= $stats['trial'] ?></strong></div>
        <div class="stat bad"><span>Suspended</span><strong><?= $stats['suspended'] ?></strong></div>
    </div>

    <div class="cols">
        <div class="card">
            <div class="card-head"><h2>Recently provisioned</h2><a class="linkall" style="margin:0" href="/super-admin/clients.php">Open clients →</a></div>
            <?php if (!$recent): ?>
                <div class="empty-mini">Nothing provisioned yet — the newest five tenants will appear here.</div>
            <?php else: foreach ($recent as $c):
                $initials = strtoupper(substr(preg_replace('/[^A-Za-z ]/', '', (string) $c['name']), 0, 1) . substr(strstr(preg_replace('/[^A-Za-z ]/', '', (string) $c['name']), ' ') ?: ' ', 1, 1));
                $initials = trim($initials) ?: 'RX';
            ?>
                <div class="row">
                    <span class="av"><?= htmlspecialchars($initials) ?></span>
                    <div class="who">
                        <b><?= htmlspecialchars($c['name']) ?></b>
                        <span><?= htmlspecialchars($c['subdomain']) ?>.<?= htmlspecialchars($appDomain) ?> · added <?= htmlspecialchars(date('d M Y', strtotime($c['created_at']))) ?></span>
                    </div>
                    <span class="status status-<?= htmlspecialchars($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="card">
            <div class="card-head"><h2>Revenue pulse</h2><span class="preview-tag">Preview</span></div>
            <div class="mrr"><strong>₹0</strong><span>MRR · subscriptions not live yet</span></div>
            <div class="bars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
            <div class="mock-note">Illustrative shape only. When the billing engine ships, this widget charts real monthly recurring revenue — no numbers are stored yet.</div>
            <div class="cta"><a href="/super-admin/billing.php">See planned billing layout →</a></div>
        </div>
    </div>

    <div class="foot"><span>OPTMS·RX — Super Admin Console</span><span>Counts are live; revenue widgets are previews.</span></div>
</div>
</body>
</html>
