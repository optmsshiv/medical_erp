<?php
/**
 * renderInvoiceDocument(array $d): printable subscription-invoice HTML document.
 * PURE RENDERER — no auth, no DB. Included by both the tenant app and super-admin;
 * callers resolve access + fetch data themselves. Never invent values: a key that
 * isn't provided renders as '—', not as a guess.
 *
 * $d keys:
 *   invoice_no, plan, period_from, period_to, amount, status, due_date, paid_at, created_at
 *   seller { name, addr, contact, gstin }   buyer { name, portal, contact? }
 *   payNote, upiId, autoPrint (bool)
 */
function renderInvoiceDocument(array $d): string
{
    function iv(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
    function ivd(?string $v): string { return $v ? iv(date('d M Y', strtotime($v))) : '—'; }
    function inr2($v): string { return '₹' . number_format((float) $v, 2); }

    $status = strtolower((string) ($d['status'] ?? 'unpaid'));
    $late = $status === 'unpaid' && !empty($d['due_date']) && $d['due_date'] < date('Y-m-d');
    $stampCls = $status === 'paid' ? 'paid' : ($late ? 'overdue' : 'due');
    $stampTxt = $status === 'paid' ? 'PAID' : ($late ? 'OVERDUE' : 'PAYMENT DUE');
    $seller = (array) ($d['seller'] ?? []);
    $buyer  = (array) ($d['buyer'] ?? []);
    $plan   = iv((string) ($d['plan'] ?? ''));
    $planLabel = ['trial' => 'Trial', 'standard' => 'Standard', 'annual' => 'Annual'][$plan] ?? ucfirst($plan);
    $period = (ivd($d['period_from'] ?? null)) . ' → ' . (ivd($d['period_to'] ?? null));
    ob_start(); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= iv((string) $d['invoice_no']) ?> · OPTMS·RX Subscription Invoice</title>
<style>
    :root { --ink:#171A26; --mut:#70798C; --line:#E3E6F0; --brand:#4338CA; --ok:#15803D; --warn:#B45309; --bad:#B42318; }
    * { box-sizing:border-box; }
    body { margin:0; background:#ECEEF4; color:var(--ink); font-family:Inter, -apple-system, "Segoe UI", Roboto, sans-serif; font-size:13px; }
    .sheet { max-width:800px; margin:26px auto; background:#fff; border:1px solid var(--line); border-radius:14px; padding:44px 48px 36px; position:relative; overflow:hidden; box-shadow:0 10px 34px rgba(23,25,50,.08); }
    .band { position:absolute; top:0; left:0; right:0; height:6px; background:linear-gradient(90deg,#4F46E5,#3730A3 60%,#176B5B); }
    .head { display:flex; justify-content:space-between; align-items:start; gap:16px; }
    .brand { font-size:21px; font-weight:800; letter-spacing:-.02em; background:linear-gradient(120deg,#312E81,#4F46E5); -webkit-background-clip:text; background-clip:text; color:transparent; }
    .brand small { display:block; font-size:10px; font-weight:800; letter-spacing:.16em; text-transform:uppercase; color:var(--mut); -webkit-text-fill-color:var(--mut); margin-top:3px; }
    .doct { text-align:right; }
    .doct b { font-size:15px; letter-spacing:.02em; }
    .doct div { color:var(--mut); font-size:11.5px; margin-top:3px; font-variant-numeric:tabular-nums; }
    .stamp { position:absolute; top:120px; right:44px; transform:rotate(9deg); border:2.5px solid; border-radius:10px; padding:6px 16px; font-size:17px; font-weight:800; letter-spacing:.18em; opacity:.85; }
    .stamp.paid { color:var(--ok); border-color:var(--ok); background:#F2FBF5; }
    .stamp.due { color:var(--warn); border-color:var(--warn); background:#FFFBF0; }
    .stamp.overdue { color:var(--bad); border-color:var(--bad); background:#FEF6F5; }
    .parties { display:grid; grid-template-columns:1fr 1fr; gap:26px; margin:30px 0 6px; }
    .party h3 { font-size:10px; text-transform:uppercase; letter-spacing:.12em; color:var(--mut); margin:0 0 7px; }
    .party b { font-size:14px; }
    .party div { color:#3A4152; line-height:1.6; font-size:12.5px; }
    table.items { width:100%; border-collapse:collapse; margin-top:22px; }
    .items th { text-align:left; background:#F6F7FB; font-size:10px; text-transform:uppercase; letter-spacing:.1em; color:var(--mut); padding:10px 12px; border-top:1px solid var(--line); border-bottom:1px solid var(--line); }
    .items td { padding:14px 12px; border-bottom:1px solid #EFF1F7; vertical-align:top; }
    .items .amt { text-align:right; font-weight:700; font-variant-numeric:tabular-nums; font-size:14px; }
    .descr { font-weight:600; }
    .descr small { display:block; font-weight:400; color:var(--mut); margin-top:3px; }
    .totals { display:flex; justify-content:flex-end; margin-top:16px; }
    .totals table { width:270px; border-collapse:collapse; }
    .totals td { padding:7px 12px 7px 0; font-size:12.5px; color:#3A4152; }
    .totals td:last-child { text-align:right; font-variant-numeric:tabular-nums; padding-right:0; }
    .totals .grand td { font-size:16px; font-weight:800; color:var(--ink); border-top:2px solid var(--ink); padding-top:10px; }
    .meta { display:flex; gap:34px; margin-top:22px; padding:13px 16px; background:#F9FAFD; border:1px solid #EEF1F7; border-radius:10px; font-size:12px; flex-wrap:wrap; }
    .meta span { color:var(--mut); display:block; font-size:9.5px; text-transform:uppercase; letter-spacing:.1em; font-weight:800; margin-bottom:2px; }
    .meta b { font-size:12.5px; }
    .pay { margin-top:20px; border:1.5px dashed #C9CDDF; border-radius:10px; padding:13px 16px; font-size:12.5px; color:#3A4152; }
    .pay .upi { font-family:ui-monospace, Menlo, monospace; font-weight:700; font-size:13.5px; }
    .foot { margin-top:26px; display:flex; justify-content:space-between; gap:14px; color:var(--mut); font-size:10.5px; line-height:1.5; }
    .printbar { max-width:800px; margin:18px auto 0; display:flex; gap:10px; justify-content:flex-end; }
    .printbar button { border:0; border-radius:10px; padding:10px 18px; font-family:inherit; font-size:13px; font-weight:700; cursor:pointer; background:linear-gradient(135deg,#4F46E5,#3730A3); color:#fff; box-shadow:0 6px 16px rgba(67,56,202,.25); }
    .printbar .ghost { background:#fff; color:#4F46E5; border:1px solid #D9DCF0; box-shadow:none; }
    @media print {
        body { background:#fff; }
        .printbar { display:none; }
        .sheet { margin:0; border:0; border-radius:0; box-shadow:none; max-width:none; padding:28px 32px; }
        @page { size:A4; margin:12mm; }
    }
</style>
</head>
<body>
<div class="printbar">
    <button class="ghost" onclick="history.length > 1 ? history.back() : window.close()">← Back</button>
    <button onclick="window.print()">Print · Save as PDF</button>
</div>
<div class="sheet">
    <div class="band"></div>
    <div class="stamp <?= $stampCls ?>"><?= $stampTxt ?></div>
    <div class="head">
        <div class="brand">OPTMS·RX<small>Pharmacy OS — subscription services</small></div>
        <div class="doct">
            <b>SUBSCRIPTION INVOICE</b>
            <div><?= iv((string) $d['invoice_no']) ?></div>
            <div>Issued: <?= ivd($d['created_at'] ?? null) ?></div>
        </div>
    </div>

    <div class="parties">
        <div class="party">
            <h3>From</h3>
            <b><?= iv((string) ($seller['name'] ?? 'OPTMS·RX')) ?></b>
            <div><?= nl2br(iv((string) ($seller['addr'] ?? ''))) ?></div>
            <div><?= iv((string) ($seller['contact'] ?? '')) ?></div>
            <?php if (!empty($seller['gstin'])): ?><div>GSTIN: <?= iv((string) $seller['gstin']) ?></div><?php endif; ?>
        </div>
        <div class="party">
            <h3>Billed to</h3>
            <b><?= iv((string) ($buyer['name'] ?? '—')) ?></b>
            <div><?= iv((string) ($buyer['portal'] ?? '')) ?></div>
            <?php if (!empty($buyer['contact'])): ?><div><?= iv((string) $buyer['contact']) ?></div><?php endif; ?>
        </div>
    </div>

    <table class="items">
        <thead><tr><th>Description</th><th style="width:190px">Billing period</th><th style="width:110px;text-align:right">Amount</th></tr></thead>
        <tbody><tr>
            <td class="descr">OPTMS·RX <?= $planLabel ?> plan — software subscription<small>Platform access, updates and support for the period shown.</small></td>
            <td><?= $period ?></td>
            <td class="amt"><?= inr2($d['amount'] ?? 0) ?></td>
        </tr></tbody>
    </table>

    <div class="totals"><table>
        <tr><td>Subtotal</td><td><?= inr2($d['amount'] ?? 0) ?></td></tr>
        <tr class="grand"><td>Total due</td><td><?= inr2($d['amount'] ?? 0) ?></td></tr>
    </table></div>

    <div class="meta">
        <div><span>Due date</span><b><?= ivd($d['due_date'] ?? null) ?></b></div>
        <div><span>Paid on</span><b><?= $status === 'paid' ? ivd($d['paid_at'] ?? null) : '—' ?></b></div>
        <div><span>Plan</span><b><?= $planLabel ?></b></div>
        <div><span>Status</span><b><?= $stampTxt === 'PAYMENT DUE' ? 'Unpaid' : iv(ucwords(strtolower($stampTxt))) ?></b></div>
    </div>

    <?php if ($status !== 'paid' && (!empty($d['upiId']) || !empty($d['payNote']))): ?>
        <div class="pay">
            <?php if (!empty($d['upiId'])): ?>Pay by UPI: <span class="upi"><?= iv((string) $d['upiId']) ?></span><br><?php endif; ?>
            <?php if (!empty($d['payNote'])): ?><?= nl2br(iv((string) $d['payNote'])) ?><br><?php endif; ?>
            Quote invoice <b><?= iv((string) $d['invoice_no']) ?></b> in your payment reference.
        </div>
    <?php elseif ($status === 'paid'): ?>
        <div class="pay" style="border-style:solid;border-color:#BFEBD0;background:#F7FCF9;color:#166534">Payment received — thank you. This invoice is settled in full.</div>
    <?php endif; ?>

    <div class="foot">
        <div>This is a system-generated invoice for OPTMS·RX subscription services.<br>Generated electronically — valid without signature.</div>
        <div style="text-align:right">OPTMS·RX · <?= iv((string) ($buyer['portal'] ?? '')) ?></div>
    </div>
</div>
<?php if (!empty($d['autoPrint'])): ?><script>window.addEventListener('load', function () { window.print(); });</script><?php endif; ?>
</body>
</html>
<?php return ob_get_clean();
}
