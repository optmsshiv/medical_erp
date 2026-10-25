<?php
require __DIR__ . '/_guard.php';
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
        --ok:#1B6B3C; --ok-soft:#E8F7EE; --warn:#93650A; --warn-soft:#FFF3DC;
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
    .head { display:flex; align-items:end; justify-content:space-between; gap:14px; flex-wrap:wrap; margin-bottom:20px; }
    h1 { font-size:21px; letter-spacing:-.02em; margin:0 0 3px; }
    .sub { color:var(--ink-2); margin:0; font-size:13px; }
    .preview-tag { font-size:10px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:var(--brand-strong); background:var(--brand-soft); border:1px solid #DFE3FB; border-radius:999px; padding:5px 12px; }

    .banner {
        display:flex; gap:12px; align-items:center; background:var(--warn-soft); border:1px solid #F0DCB0; color:var(--warn);
        border-radius:14px; padding:13px 16px; font-size:12.5px; margin-bottom:24px; line-height:1.5;
    }
    .banner svg { width:17px; height:17px; flex:none; }

    h2.sec { font-size:14px; letter-spacing:-.01em; margin:26px 0 12px; }
    .plans { display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; }
    .plan { background:#fff; border:1px solid var(--line); border-radius:16px; padding:22px; box-shadow:0 1px 2px rgba(23,25,40,.04), 0 14px 34px rgba(36,30,110,.05); display:flex; flex-direction:column; }
    .plan.hot { border:1.5px solid var(--brand); box-shadow:0 10px 30px rgba(67,56,202,.14); position:relative; }
    .plan .hot-chip { position:absolute; top:-10px; left:18px; font-size:9.5px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; background:linear-gradient(135deg,#4F46E5,#3730A3); color:#fff; padding:3px 10px; border-radius:999px; }
    .plan .nm { font-size:12px; font-weight:800; letter-spacing:.07em; text-transform:uppercase; color:var(--brand-strong); }
    .plan .pr { margin:10px 0 4px; font-size:26px; font-weight:750; letter-spacing:-.03em; }
    .plan .pr small { font-size:12px; color:var(--ink-3); font-weight:600; letter-spacing:0; }
    .plan .tag { font-size:11.5px; color:var(--ink-2); margin-bottom:14px; }
    .plan ul { list-style:none; margin:0 0 18px; padding:0; display:grid; gap:8px; font-size:12.5px; color:var(--ink-2); }
    .plan li { display:flex; gap:8px; align-items:center; }
    .plan li svg { width:13px; height:13px; color:var(--brand); flex:none; }
    .plan .ghost { margin-top:auto; text-align:center; font-size:12.5px; font-weight:650; color:var(--ink-3); border:1px dashed #D6D9E6; border-radius:10px; padding:10px; }
    .plans-note { margin-top:10px; font-size:11px; color:var(--ink-3); }

    .tbl-card { background:#fff; border:1px solid var(--line); border-radius:16px; overflow:hidden; box-shadow:0 1px 2px rgba(23,25,40,.04), 0 14px 34px rgba(36,30,110,.05); }
    .tbl-head { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid var(--line); }
    .tbl-head b { font-size:13.5px; }
    table { width:100%; border-collapse:collapse; }
    th, td { text-align:left; padding:12px 18px; border-bottom:1px solid #EEF0F6; font-size:13px; }
    th { font-size:10.5px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; color:var(--ink-3); background:#FAFBFE; }
    tbody tr:last-child td { border-bottom:0; }
    td .amt { font-weight:700; }
    .pill { display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; }
    .pill.paid { background:var(--ok-soft); color:var(--ok); }
    .pill.pend { background:var(--warn-soft); color:var(--warn); }
    .sample { opacity:.62; }

    .foot { margin-top:26px; display:flex; justify-content:space-between; font-size:11px; color:var(--ink-3); }
    @media (max-width: 820px) { .plans { grid-template-columns:1fr; } }
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
            <a href="/super-admin/clients.php">Clients</a>
            <a href="/super-admin/billing.php" class="on">Billing</a>
        </nav>
        <div class="bar-right"><span class="session">Key session</span></div>
    </div>
</div>

<div class="wrap">
    <div class="head">
        <div>
            <h1>Billing</h1>
            <p class="sub">Subscription plans and collections — layout preview, nothing here is live yet.</p>
        </div>
        <span class="preview-tag">Preview</span>
    </div>

    <div class="banner">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v13"/><path d="M8.5 6.5L12 3l3.5 3.5"/><path d="M5 15v4a2 2 0 002 2h10a2 2 0 002-2v-4"/></svg>
        <span><b>This page is a design preview.</b> Prices, invoices and statuses below are illustrative samples for layout approval — no billing data is stored or charged yet.</span>
    </div>

    <h2 class="sec">Planned subscription plans</h2>
    <div class="plans">
        <div class="plan">
            <span class="nm">Trial</span>
            <div class="pr">₹0 <small>/ 14 days</small></div>
            <div class="tag">Full product, time-boxed. Converts or suspends automatically.</div>
            <ul>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Retail POS &amp; inventory</li>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Single user login</li>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Chat onboarding support</li>
            </ul>
            <div class="ghost">Assigned at provisioning</div>
        </div>
        <div class="plan hot">
            <span class="hot-chip">Recommended</span>
            <span class="nm">Standard</span>
            <div class="pr">₹— <small>/ month</small></div>
            <div class="tag">Everyday running for an independent retail pharmacy.</div>
            <ul>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Everything in Trial</li>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Compliance exports &amp; H1/X register</li>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Priority support window</li>
            </ul>
            <div class="ghost">Pricing to be finalised</div>
        </div>
        <div class="plan">
            <span class="nm">Annual</span>
            <div class="pr">₹— <small>/ year</small></div>
            <div class="tag">Two months free on yearly commitment, migration help included.</div>
            <ul>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Everything in Standard</li>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Priority callback support</li>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Data migration assistance</li>
            </ul>
            <div class="ghost">Pricing to be finalised</div>
        </div>
    </div>
    <div class="plans-note">Amounts shown as “—” are deliberate: final pricing gets published before the billing engine goes live.</div>

    <h2 class="sec">Invoice ledger — sample rows</h2>
    <div class="tbl-card">
        <div class="tbl-head"><b>Collections ledger</b><span class="preview-tag">Preview</span></div>
        <table>
            <thead>
                <tr><th>Invoice</th><th>Client</th><th>Period</th><th class="amt">Amount</th><th>Status</th></tr>
            </thead>
            <tbody class="sample">
                <tr><td>INV-0001</td><td>Sample Pharmacy A</td><td>MOCK 01–30</td><td class="amt">₹—</td><td><span class="pill paid">Sample — paid</span></td></tr>
                <tr><td>INV-0002</td><td>Sample Pharmacy B</td><td>MOCK 01–30</td><td class="amt">₹—</td><td><span class="pill pend">Sample — pending</span></td></tr>
                <tr><td>INV-0003</td><td>Sample Pharmacy C</td><td>MOCK 01–30</td><td class="amt">₹—</td><td><span class="pill pend">Sample — pending</span></td></tr>
            </tbody>
        </table>
    </div>

    <div class="foot"><span>OPTMS·RX — Super Admin Console</span><span>Preview build — no charges or records exist.</span></div>
</div>
</body>
</html>
