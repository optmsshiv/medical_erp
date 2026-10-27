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
$auditRows = [];
try { $auditRows = Database::master()->query('SELECT action, detail, created_at FROM admin_audit ORDER BY id DESC LIMIT 6')->fetchAll(); }
catch (Throwable $e) { $auditRows = []; }

/* ---- live revenue (billing tables; graceful before the billing migration) ---- */
$billingReady = false;
$mrr = null; $collected = null; $openInv = null; $overdue = null;
try { $billingReady = (bool) Database::master()->query("SHOW TABLES LIKE 'client_invoices'")->fetch(); } catch (Throwable $e) { }
if ($billingReady) {
    try {
        $prices = [];
        foreach (Database::master()->query('SELECT code, price FROM billing_plans WHERE active = 1')->fetchAll() as $p) { $prices[strtolower((string) $p['code'])] = (float) $p['price']; }
        $sum = 0.0;
        foreach ($clients as $cl) {
            if (strtolower((string) $cl['status']) === 'suspended') { continue; }
            $pl = strtolower((string) ($cl['plan'] ?? 'trial'));
            $sum += ($pl === 'annual') ? ($prices[$pl] ?? 0) / 12 : ($prices[$pl] ?? 0);
        }
        $mrr = $sum;
        $q = Database::master()->query("SELECT COALESCE(SUM(amount),0) s FROM client_invoices WHERE status = 'paid' AND DATE_FORMAT(paid_at,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')")->fetch();
        $collected = (float) ($q['s'] ?? 0);
        $q = Database::master()->query("SELECT COUNT(*) n FROM client_invoices WHERE status = 'unpaid'")->fetch();
        $openInv = (int) ($q['n'] ?? 0);
        $q = Database::master()->query("SELECT COUNT(*) n FROM client_invoices WHERE status = 'unpaid' AND due_date < CURDATE()")->fetch();
        $overdue = (int) ($q['n'] ?? 0);
    } catch (Throwable $e) { $billingReady = false; }
}
/* ---- plan mix, invoice feed, overdue radar (billing-gated) ---- */
$planMix = ['trial' => 0, 'standard' => 0, 'annual' => 0];
foreach ($clients as $cl) { $pl = strtolower((string) ($cl['plan'] ?? 'trial')); if (isset($planMix[$pl])) { $planMix[$pl]++; } }
$recentInv = []; $overdueList = []; $monthly = [];
if ($billingReady) {
    try { $recentInv = Database::master()->query('SELECT ci.invoice_no, ci.amount, ci.status, ci.due_date, ci.paid_at, ci.period_from, cl.name AS cname FROM client_invoices ci JOIN clients cl ON cl.id = ci.client_id ORDER BY ci.id DESC LIMIT 6')->fetchAll(); } catch (Throwable $e) { }
    try { $overdueList = Database::master()->query("SELECT ci.invoice_no, ci.amount, ci.due_date, cl.name AS cname FROM client_invoices ci JOIN clients cl ON cl.id = ci.client_id WHERE ci.status = 'unpaid' AND ci.due_date < CURDATE() ORDER BY ci.due_date ASC LIMIT 5")->fetchAll(); } catch (Throwable $e) { }
    try {
        foreach (Database::master()->query(
            "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS m, SUM(amount) AS s FROM client_invoices
              WHERE status = 'paid' AND paid_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 7 MONTH), '%Y-%m-01')
              GROUP BY m"
        )->fetchAll() as $r) { $monthly[$r['m']] = (float) $r['s']; }
    } catch (Throwable $e) { }
}
/* last-8-months bar heights — real collections only, zero months are honest zeroes */
$revSeries = []; $revMax = 0.0;
for ($k = 7; $k >= 0; $k--) {
    $key = date('Y-m', strtotime(date('Y-m-01') . " -{$k} months"));
    $v = $monthly[$key] ?? 0.0;
    $revSeries[] = ['label' => date('M Y', strtotime($key . '-01')), 'v' => $v];
    if ($v > $revMax) { $revMax = $v; }
}
foreach ($revSeries as &$rm) { $rm['pct'] = $revMax > 0 ? max(6, (int) round($rm['v'] / $revMax * 94) + 6) : 6; }
unset($rm);
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

    .stats { display:grid; grid-template-columns:repeat(6, minmax(0,1fr)); gap:12px; margin-bottom:22px; }
    .stat em { display:block; font-style:normal; font-size:10px; color:var(--ink-3); margin-top:2px; }
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

    .ld { color:#B8BCD0; }
    .mut { color:var(--ink-3); font-size:12px; }
    .quiet { display:inline-block; font-size:9.5px; font-weight:800; letter-spacing:.07em; text-transform:uppercase; background:var(--warn-soft); color:var(--warn); padding:2px 7px; border-radius:6px; margin-left:6px; vertical-align:middle; }
    .pulsebar { height:5px; width:110px; border-radius:4px; background:#EFF1F6; margin-top:5px; overflow:hidden; }
    .pulsebar i { display:block; height:100%; background:linear-gradient(90deg,#34D399,#16A34A); }
    .pulsebar.warn i { background:linear-gradient(90deg,#FBBF24,#D97706); }
    .pulsebar.hot i { background:linear-gradient(90deg,#F87171,#DC2626); }
    .offl { font-size:10px; font-weight:800; letter-spacing:.07em; text-transform:uppercase; background:var(--warn-soft); color:var(--warn); padding:3px 9px; border-radius:999px; }
    .live-tag { font-size:9.5px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:#1B6B3C; background:#E8F7EE; border:1px solid #BFEBD0; border-radius:999px; padding:3px 9px; }
    .minirow { display:flex; justify-content:space-between; align-items:center; padding:7px 0; border-top:1px solid #EFF1F6; font-size:12.5px; }
    .minirow span { color:var(--ink-3); }
    .minirow:first-of-type { margin-top:8px; }
    .stack { display:flex; flex-direction:column; gap:16px; align-self:start; }
    .segm { display:flex; height:10px; border-radius:6px; overflow:hidden; background:#EFF1F6; margin:4px 0 12px; }
    .segm i { display:block; height:100%; }
    .segm .t { background:#FBBF24; } .segm .s { background:#6366F1; } .segm .a { background:#0F766E; }
    .legrow { display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-top:1px solid #F2F4F9; font-size:12.5px; }
    .legrow span { display:flex; align-items:center; gap:8px; color:var(--ink-2); }
    .legrow.mut span { color:var(--ink-3); }
    .dot { width:8px; height:8px; border-radius:50%; display:inline-block; }
    .dot.t { background:#FBBF24; } .dot.s { background:#6366F1; } .dot.a { background:#0F766E; } .dot.x { background:#C4C9D8; }
    .ip-void { background:#F1F2F6; color:var(--ink-3); } .ip-void::before { background:#B8BCD0 !important; }
    .clear { display:flex; flex-direction:column; align-items:center; gap:4px; padding:26px 10px 22px; color:var(--ok); }
    .clear svg { width:26px; height:26px; }
    .bars.rv { height:64px; margin-top:10px; }
    .bars.rv i { cursor:default; transition:filter .15s; }
    .bars.rv i:hover { filter:brightness(1.12); }
    /* health ledger: compact client column, everything centred — headers and data */
    table.health { width:100%; border-collapse:collapse; }
    .health th, .health td { padding:10px 12px; border-bottom:1px solid #EFF1F6; vertical-align:middle; text-align:center; font-variant-numeric:tabular-nums; }
    .health tbody tr:last-child td { border-bottom:0; }
    .health th { font-size:10px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; color:var(--ink-3); background:#FAFBFE; }
    .health td { font-size:12.5px; }
    .health td:first-child b { display:block; }
    .health td:nth-child(3), .health td:nth-child(7) { white-space:nowrap; }
    .health .pulsebar { margin-left:auto; margin-right:auto; }
    .clear b { font-size:13.5px; } .clear span { font-size:11.5px; color:var(--ink-3); }
    @media (max-width: 1020px) { .stats { grid-template-columns:repeat(3,1fr); } }
    @media (max-width: 820px) { .cols { grid-template-columns:1fr; } .stats { grid-template-columns:repeat(2,1fr); } .health th:nth-child(5), .health td:nth-child(5), .health th:nth-child(6), .health td:nth-child(6) { display:none; } }
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
            <a href="/super-admin/whatsapp.php" class="<?= $nav === 'whatsapp' ? 'on' : '' ?>">WhatsApp</a>
        </nav>
        <div class="bar-right"><span class="session">Key session</span></div>
    </div>
</div>

<div class="wrap">
    <div class="head">
        <h1>Platform overview</h1>
        <p class="sub">Live platform telemetry — tenants, subscription revenue and tenant health, straight from the master database.</p>
    </div>

    <div class="stats">
        <div class="stat"><span>Total clients</span><strong><?= $stats['total'] ?></strong></div>
        <div class="stat ok"><span>Active</span><strong><?= $stats['active'] ?></strong></div>
        <div class="stat warn"><span>On trial</span><strong><?= $stats['trial'] ?></strong></div>
        <div class="stat bad"><span>Suspended</span><strong><?= $stats['suspended'] ?></strong></div>
        <div class="stat"><span>MRR</span><strong style="color:var(--brand-strong)"><?= $billingReady ? '₹' . number_format((float) $mrr, 0) : '—' ?></strong><em><?= $billingReady ? 'live plan roster' : 'billing off' ?></em></div>
        <div class="stat bad"><span>Overdue invoices</span><strong><?= $billingReady ? $overdue : '—' ?></strong><em><?= $billingReady && $overdue ? 'attention needed' : ($billingReady ? 'all clear' : 'billing off') ?></em></div>
    </div>

    <div class="cols">
        <div class="stack">
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
            <div class="card-head" style="margin-bottom:10px"><h2>Plan mix</h2><span class="cap" style="margin:0"><?= $stats['total'] ?> tenant<?= $stats['total'] === 1 ? '' : 's' ?> on roster</span></div>
            <?php $mixTotal = max(1, array_sum($planMix)); if (array_sum($planMix) === 0): ?>
                <div class="empty-mini">Plans are assigned per client from the Clients page.</div>
            <?php else: ?>
                <div class="segm">
                    <i class="t" style="width:<?= round($planMix['trial'] / $mixTotal * 100, 1) ?>%"></i>
                    <i class="s" style="width:<?= round($planMix['standard'] / $mixTotal * 100, 1) ?>%"></i>
                    <i class="a" style="width:<?= round($planMix['annual'] / $mixTotal * 100, 1) ?>%"></i>
                </div>
                <div class="legrow"><span><b class="dot t"></b>Trial</span><b><?= $planMix['trial'] ?></b></div>
                <div class="legrow"><span><b class="dot s"></b>Standard · monthly</span><b><?= $planMix['standard'] ?></b></div>
                <div class="legrow"><span><b class="dot a"></b>Annual · yearly</span><b><?= $planMix['annual'] ?></b></div>
                <?php if ($stats['suspended']): ?><div class="legrow mut"><span><b class="dot x"></b>Suspended (excluded from MRR)</span><b><?= $stats['suspended'] ?></b></div><?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-head"><h2>Recent invoices</h2><a class="linkall" style="margin:0" href="/super-admin/billing.php">Billing ledger →</a></div>
            <?php if (!$recentInv): ?>
                <div class="empty-mini"><?= $billingReady ? 'No invoices yet — run a billing cycle from Billing.' : 'Run the billing migration to light this up.' ?></div>
            <?php else: foreach ($recentInv as $i):
                $isLate = $i['status'] === 'unpaid' && $i['due_date'] && $i['due_date'] < date('Y-m-d');
                $pillCls = $i['status'] === 'paid' ? 'status-active' : ($isLate ? 'status-suspended' : ($i['status'] === 'void' ? 'ip-void' : 'status-trial'));
                $pillTxt = $i['status'] === 'paid' ? 'Paid' : ($isLate ? 'Overdue' : ucfirst($i['status']));
                $meta = $i['status'] === 'paid' && $i['paid_at'] ? 'paid ' . date('d M', strtotime($i['paid_at']))
                      : ($i['due_date'] ? 'due ' . date('d M', strtotime($i['due_date'])) : date('d M', strtotime($i['period_from'])));
            ?>
                <div class="row">
                    <div class="who">
                        <b><?= htmlspecialchars($i['cname']) ?></b>
                        <span><code style="font-size:10px;background:var(--brand-soft);color:var(--brand-strong);border-radius:4px;padding:1px 6px"><?= htmlspecialchars($i['invoice_no']) ?></code> · <?= htmlspecialchars($meta) ?></span>
                    </div>
                    <b style="font-size:13px;font-variant-numeric:tabular-nums">₹<?= number_format((float) $i['amount'], 0) ?></b>
                    <span class="status <?= $pillCls ?>"><?= $pillTxt ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>
        </div>

        <div class="stack">
        <div class="card">
            <div class="card-head"><h2>Revenue pulse</h2><span class="live-tag">Live</span></div>
            <?php if ($billingReady): ?>
                <div class="mrr"><strong>₹<?= number_format((float) $mrr, 0) ?></strong><span>MRR from the live plan roster</span></div>
                <div class="minirow"><span>Collected this month</span><b>₹<?= number_format((float) $collected, 0) ?></b></div>
                <div class="minirow"><span>Unpaid invoices</span><b><?= $openInv ?></b></div>
                <div class="minirow"><span>Overdue</span><b style="color:<?= $overdue ? 'var(--bad)' : 'var(--ok)' ?>"><?= $overdue ?></b></div>
                <div class="bars rv">
                    <?php foreach ($revSeries as $m): ?>
                        <i style="height:<?= (int) $m['pct'] ?>%" title="<?= htmlspecialchars($m['label'] . ' · ₹' . number_format($m['v'], 0)) ?>"></i>
                    <?php endforeach; ?>
                </div>
                <div class="mock-note">Collected per month — paid invoices, last 8 months. Hover a bar for the figure.</div>
            <?php else: ?>
                <div class="mrr"><strong>₹0</strong><span>Billing tables not installed yet</span></div>
                <div class="mock-note">Run <b>database/2026_10_20_billing.sql</b> on the master DB — this card turns live the moment invoices exist.</div>
            <?php endif; ?>
            <div class="cta"><a href="/super-admin/billing.php">Open billing ledger →</a></div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Attention needed</h2><span class="live-tag">Live</span></div>
            <?php if (!$billingReady): ?>
                <div class="empty-mini">Installs itself with the billing migration.</div>
            <?php elseif (!$overdueList): ?>
                <div class="clear"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg><b>All clear.</b><span>No overdue invoices across the fleet.</span></div>
            <?php else: foreach ($overdueList as $o):
                $lateDays = (int) ((strtotime(date('Y-m-d')) - strtotime($o['due_date'])) / 86400);
            ?>
                <div class="row">
                    <div class="who">
                        <b><?= htmlspecialchars($o['cname']) ?></b>
                        <span><?= htmlspecialchars($o['invoice_no']) ?> · due <?= htmlspecialchars(date('d M', strtotime($o['due_date']))) ?></span>
                    </div>
                    <b style="font-size:12.5px">₹<?= number_format((float) $o['amount'], 0) ?></b>
                    <span class="offl"><?= $lateDays ?>d late</span>
                </div>
            <?php endforeach; ?>
                <div class="cta"><a href="/super-admin/billing.php">Collect in Billing →</a></div>
            <?php endif; ?>
        </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <div class="card-head"><h2>Client health — live pulse</h2><span class="cap" style="margin:0">Vitals stream in per client; caps meter against plan.</span></div>
        <table class="health">
            <thead><tr><th>Client</th><th style="width:100px">Status</th><th style="width:130px">Last sale</th><th style="width:150px">Invoices this month</th><th style="width:90px">Medicines</th><th style="width:86px">DB size</th><th style="width:110px">Last login</th></tr></thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
                <tr data-pulse="<?= (int) $c['id'] ?>">
                    <td><b style="font-size:13px"><?= htmlspecialchars($c['name']) ?></b><div class="mut"><?= htmlspecialchars($c['subdomain']) ?>.<?= htmlspecialchars($appDomain) ?></div></td>
                    <td><span class="status status-<?= htmlspecialchars($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
                    <td data-slot="lastsale"><span class="ld">…</span></td>
                    <td data-slot="month"><span class="ld">…</span></td>
                    <td data-slot="meds"><span class="ld">…</span></td>
                    <td data-slot="db"><span class="ld">…</span></td>
                    <td data-slot="login"><span class="ld">…</span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$clients): ?><tr><td colspan="7"><div class="empty-mini">No clients yet.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-top:16px">
        <div class="card-head"><h2>Recent control actions</h2><a class="linkall" style="margin:0" href="/super-admin/clients.php">Full log on Clients →</a></div>
        <?php if (!$auditRows): ?>
            <div class="empty-mini">No control actions recorded yet — suspends, resets, plan and feature changes appear here.</div>
        <?php else: ?>
            <ul style="list-style:none;margin:0;padding:0">
                <?php foreach ($auditRows as $a): ?>
                    <li class="row" style="padding:8px 0">
                        <code style="font-size:10px;font-weight:800;letter-spacing:.06em;background:var(--brand-soft);color:var(--brand-strong);border-radius:5px;padding:2px 7px"><?= htmlspecialchars($a['action']) ?></code>
                        <span style="flex:1;font-size:12.5px;color:var(--ink-2)"><?= htmlspecialchars($a['detail']) ?></span>
                        <span style="font-size:11px;color:var(--ink-3)"><?= htmlspecialchars(date('d M · H:i', strtotime($a['created_at']))) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="foot"><span>OPTMS·RX — Super Admin Console</span><span>Every figure on this page is live from the master database.</span></div>
</div>
<script>
(function () {
    var MON = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var today = new Date(); today.setHours(0,0,0,0);
    function rel(dstr) {
        if (!dstr) return '';
        var d = new Date(String(dstr).replace(' ', 'T'));
        if (isNaN(d)) return dstr;
        var days = Math.round((today - new Date(d.getFullYear(), d.getMonth(), d.getDate())) / 86400000);
        var abs = d.getDate() + ' ' + MON[d.getMonth()] + ' ' + d.getFullYear();
        return days <= 0 ? 'today' : days === 1 ? 'yesterday' : days + 'd ago · ' + (days > 30 ? abs : MON[d.getMonth()] + ' ' + d.getDate());
    }
    document.querySelectorAll('tr[data-pulse]').forEach(function (row) {
        var set = function (slot, html) { var c = row.querySelector('[data-slot="' + slot + '"]'); if (c) c.innerHTML = html; };
        fetch('actions/client-pulse.php?client_id=' + encodeURIComponent(row.dataset.pulse), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (p) {
                if (!p || p.error) { throw 0; }
                if (!p.online) {
                    var tip = p.offline_reason ? ' title="' + String(p.offline_reason).replace(/"/g, '') + '"' : '';
                    set('lastsale', '<span class="offl"' + tip + '>Tenant DB unreachable</span>');
                    ['month','meds','db','login'].forEach(function (k) { set(k, '<span class="mut">—</span>'); });
                    if (p.db_size_mb !== null) set('db', p.db_size_mb + ' MB'); return; }
                var ls = p.last_sale ? rel(p.last_sale) : 'no sales yet';
                var q = '';
                if (p.last_sale) {
                    var d = new Date(String(p.last_sale).slice(0, 10).replace(' ', 'T'));
                    var days = Math.round((today - new Date(d.getFullYear(), d.getMonth(), d.getDate())) / 86400000);
                    if (days > 14) q = '<span class="quiet">quiet</span>';
                }
                set('lastsale', ls + q);
                if (p.cap_invoices) {
                    var cls = p.cap_pct > 85 ? 'hot' : (p.cap_pct >= 60 ? 'warn' : '');
                    set('month', (p.invoices_month || 0) + ' / ' + p.cap_invoices + '<div class="pulsebar ' + cls + '"><i style="width:' + p.cap_pct + '%"></i></div>');
                } else {
                    set('month', (p.invoices_month === null ? '<span class="mut">—</span>' : p.invoices_month + ' <span class="mut">(uncapped)</span>'));
                }
                set('meds', p.medicines === null ? '<span class="mut">—</span>' : p.medicines);
                set('db', p.db_size_mb === null ? '<span class="mut">—</span>' : p.db_size_mb + ' MB');
                set('login', p.last_login ? rel(p.last_login) : '<span class="mut">n/a</span>');
            })
            .catch(function () { ['lastsale','month','meds','db','login'].forEach(function (k) { set(k, '<span class="mut">—</span>'); }); });
    });
})();
</script>
</body>
</html>
