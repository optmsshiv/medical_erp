<?php
/**
 * super-admin/whatsapp.php — WhatsApp alerts (Meta Cloud API).
 * Credentials, per-client numbers, broadcast composer, delivery log.
 * Auto-events are wired in the engine itself: onboarding welcome fires when a
 * client is provisioned, payment receipt when an invoice is marked paid.
 */
require __DIR__ . '/actions/_lib.php';

$cfg = waCfg();
$tokenSet = $cfg['token'] !== '';
waEnsure();

$rows = [];
try {
    $rows = master()->query('SELECT id, name, subdomain, status, whatsapp FROM clients ORDER BY FIELD(status,\'active\',\'trial\',\'suspended\',\'disabled\'), name ASC')->fetchAll();
} catch (Throwable $e) { }
$withNum = count(array_filter($rows, static fn ($r) => (string) ($r['whatsapp'] ?? '') !== ''));

$log = [];
try {
    $log = master()->query('SELECT w.*, c.name AS client FROM wa_log w LEFT JOIN clients c ON c.id = w.client_id ORDER BY w.id DESC LIMIT 60')->fetchAll();
} catch (Throwable $e) { }

$okFlash = $_SESSION['flash_ok_msg'] ?? null;
$errFlash = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_ok_msg'], $_SESSION['flash_error']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>WhatsApp alerts · OPTMS-RX control</title>
<style>
  :root { --bg:#F4F5F9; --ink:#1A1D27; --mut:#5B6270; --line:#E5E7F0; --brand:#176B5B; --bad:#C0392F; }
  * { box-sizing:border-box; } body { margin:0; background:var(--bg); color:var(--ink); font-family:-apple-system,BlinkMacSystemFont,"Inter","Segoe UI",Roboto,sans-serif; font-size:14px; }
  header { display:flex; align-items:center; justify-content:space-between; padding:18px 28px; background:#fff; border-bottom:1px solid var(--line); position:sticky; top:0; z-index:5; }
  header .brand { font-weight:800; letter-spacing:.02em; } header .brand span { color:var(--brand); }
  header nav a { color:var(--mut); text-decoration:none; font-weight:600; margin-left:18px; } header nav a:hover, header nav a.on { color:var(--ink); }
  main { max-width:1180px; margin:26px auto; padding:0 24px 60px; display:grid; grid-template-columns:1fr 1fr; gap:18px; }
  .card { background:#fff; border:1px solid var(--line); border-radius:16px; padding:20px 22px; box-shadow:0 10px 30px rgba(36,30,110,.05); }
  .card.wide { grid-column:1 / -1; }
  h2 { margin:0 0 2px; font-size:15.5px; } .sub { margin:0 0 14px; color:var(--mut); font-size:12.5px; line-height:1.5; }
  label { display:block; font-size:11.5px; font-weight:700; color:var(--mut); text-transform:uppercase; letter-spacing:.05em; margin:12px 0 4px; }
  input[type=text], input[type=password], input[type=number], select, textarea { width:100%; padding:9px 11px; border:1px solid #D9DEEA; border-radius:10px; font:inherit; background:#FBFCFE; }
  textarea { min-height:88px; resize:vertical; }
  .row2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; } .row3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
  .btn { display:inline-block; padding:10px 18px; border-radius:10px; border:0; background:var(--brand); color:#fff; font-weight:700; font-size:13px; cursor:pointer; }
  .btn.ghost { background:#EEF0F6; color:var(--ink); }
  .flash { grid-column:1/-1; padding:12px 16px; border-radius:12px; font-weight:600; font-size:13px; }
  .flash.ok { background:#E7F6EF; color:#0C6C4B; border:1px solid #BCE7D4; }
  .flash.err { background:#FBECEA; color:var(--bad); border:1px solid #F2C6C0; }
  .pill { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:99px; font-size:11.5px; font-weight:700; }
  .pill.on { background:#E7F6EF; color:#0C6C4B; } .pill.off { background:#F2F3F8; color:#7A8093; } .pill.bad { background:#FBECEA; color:var(--bad); }
  table { width:100%; border-collapse:collapse; } th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:var(--mut); padding:8px 10px; border-bottom:1px solid var(--line); }
  td { padding:8px 10px; border-bottom:1px solid #F0F2F7; font-size:13px; vertical-align:middle; }
  td input { padding:7px 9px; }
  code { background:#F2F3F8; border-radius:6px; padding:1px 6px; font-size:12px; }
  .note { background:#F7F8FC; border:1px dashed #D4D9E8; border-radius:12px; padding:12px 14px; font-size:12.5px; color:var(--mut); line-height:1.55; }
</style>
</head>
<body>
<header>
  <div class="brand">OPTMS<span>·</span>RX <span>control</span></div>
  <nav><a href="/super-admin/dashboard.php">Dashboard</a><a href="/super-admin/clients.php">Clients</a><a href="/super-admin/billing.php">Billing</a><a class="on" href="/super-admin/whatsapp.php">WhatsApp</a><a href="/super-admin/login.php">Logout</a></nav>
</header>
<main>
  <?php if ($okFlash): ?><div class="flash ok"><?= htmlspecialchars($okFlash) ?></div><?php endif; ?>
  <?php if ($errFlash): ?><div class="flash err"><?= htmlspecialchars($errFlash) ?></div><?php endif; ?>

  <div class="card">
    <h2>Meta Cloud API <span class="pill <?= $cfg['enabled'] ? 'on' : 'off' ?>"><?= $cfg['enabled'] ? 'enabled' : 'disabled' ?></span></h2>
    <p class="sub">Create a WhatsApp Business app on <code>developers.facebook.com</code> → link the phone number → paste the permanent token here. No SDK, no server callbacks: this is the plain REST send flow.</p>
    <form method="POST" action="/super-admin/actions/whatsapp-action.php">
      <input type="hidden" name="act" value="save_cfg">
      <div class="row2">
        <div><label>Phone number ID (WABA)</label><input type="text" name="wa_phone_id" value="<?= htmlspecialchars($cfg['phone_id']) ?>" placeholder="123456789012345"></div>
        <div><label>Graph API version</label><select name="wa_version"><?php foreach (['v23.0', 'v22.0', 'v21.0', 'v20.0'] as $v): ?><option <?= $cfg['ver'] === $v ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
      </div>
      <label>Permanent access token <?= $tokenSet ? '<span class="pill on" style="text-transform:none">stored — leave blank to keep</span>' : '<span class="pill bad" style="text-transform:none">not set</span>' ?></label>
      <input type="password" name="wa_token" placeholder="<?= $tokenSet ? '••keep••' : 'EAAG…' ?>" autocomplete="off">
      <div class="row3">
        <div><label>Template language</label><input type="text" name="wa_lang" value="<?= htmlspecialchars($cfg['lang']) ?>" placeholder="en"></div>
        <div><label>Country code</label><input type="text" name="wa_cc" value="<?= htmlspecialchars($cfg['cc']) ?>" placeholder="91"></div>
        <div><label>Delivery</label><select name="wa_enabled"><option value="1" <?= $cfg['enabled'] ? 'selected' : '' ?>>Enabled</option><option value="0" <?= !$cfg['enabled'] ? 'selected' : '' ?>>Paused</option></select></div>
      </div>
      <div class="row3">
        <div><label>Template — onboarding</label><input type="text" name="wa_tpl_onboard" value="<?= htmlspecialchars($cfg['tpl_onboard']) ?>" placeholder="welcome_v1"></div>
        <div><label>Template — payment</label><input type="text" name="wa_tpl_paid" value="<?= htmlspecialchars($cfg['tpl_paid']) ?>" placeholder="receipt_v1"></div>
        <div><label>Template — alert</label><input type="text" name="wa_tpl_alert" value="<?= htmlspecialchars($cfg['tpl_alert']) ?>" placeholder="notice_v1"></div>
      </div>
      <p class="sub" style="margin:10px 0 0">Template variables expected: <b>onboarding</b> {1}=store name {2}=login URL {3}=admin email · <b>payment</b> {1}=store name {2}=invoice no. {3}=amount {4}=period · <b>alert</b> {1}=store name {2}=message {3}=date. Leave a template blank to fall back to a free-text session message (only delivered inside Meta's 24-hour window — for cold clients, approved templates are the reliable path).</p>
      <div style="margin-top:14px"><button class="btn">Save settings</button></div>
    </form>
  </div>

  <div class="card">
    <h2>Test &amp; free-text ping</h2>
    <p class="sub">Sends a plain session message — useful the day a client replies to you (then the 24 h window is open). For cold numbers use a template broadcast instead.</p>
    <form method="POST" action="/super-admin/actions/whatsapp-action.php">
      <input type="hidden" name="act" value="test">
      <label>Destination number</label><input type="text" name="test_to" placeholder="+91 98XXXXXXXX or 10 digits">
      <label>Message</label><textarea name="test_text" placeholder="Test — OPTMS-RX alerts are wired up ✔"></textarea>
      <div style="margin-top:12px"><button class="btn">Send test</button></div>
    </form>
  </div>

  <div class="card wide">
    <h2>Broadcast an alert <span class="pill <?= $withNum ? 'on' : 'off' ?>"><?= $withNum ?> client(s) reachable</span></h2>
    <p class="sub">Every alert lands in the client's audit trail here and is logged below — no phone, no send.</p>
    <form method="POST" action="/super-admin/actions/whatsapp-action.php">
      <input type="hidden" name="act" value="send_alert">
      <div class="row3">
        <div><label>Audience</label>
          <select name="recipients">
            <option value="active">Active clients only</option>
            <option value="paid">Active + trial</option>
            <option value="all">All with numbers</option>
            <option value="suspended">Suspended (overdue) only</option>
          </select></div>
        <div><label>Template override (optional)</label><input type="text" name="tpl_override" placeholder="e.g. urgent_notice_v2 — blank uses the alert template"></div>
        <div><label>&nbsp;</label><button class="btn" style="width:100%">Send alert</button></div>
      </div>
      <label>Message</label><textarea name="message" placeholder="Scheduled maintenance tonight 11 PM–1 AM — billing keeps working, the console may blink twice."></textarea>
    </form>
  </div>

  <div class="card wide">
    <h2>Client numbers</h2>
    <p class="sub">Where a client's WhatsApp lands on onboarding, set it here (or in the create form). Numbers normalize to country code + 10 digits.</p>
    <form method="POST" action="/super-admin/actions/whatsapp-action.php">
      <input type="hidden" name="act" value="save_numbers">
      <table>
        <thead><tr><th style="width:34%">Client</th><th>Subdomain</th><th>Status</th><th style="width:220px">WhatsApp number</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $c): ?>
          <tr>
            <td><b><?= htmlspecialchars((string) $c['name']) ?></b></td>
            <td><code><?= htmlspecialchars((string) $c['subdomain']) ?></code></td>
            <td><?= htmlspecialchars((string) $c['status']) ?></td>
            <td><input type="text" name="wa[<?= (int) $c['id'] ?>]" value="<?= htmlspecialchars((string) ($c['whatsapp'] ?? '')) ?>" placeholder="98XXXXXXXX"></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="4" class="sub">No clients yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
      <div style="margin-top:12px"><button class="btn">Save numbers</button></div>
    </form>
  </div>

  <div class="card wide">
    <h2>Delivery log</h2>
    <p class="sub">Last <?= count($log) ?: 0 ?> sends — onboarding, receipts, alerts and tests, whatever Meta said back.</p>
    <table>
      <thead><tr><th>When</th><th>Client</th><th>To</th><th>Kind</th><th>Via</th><th>Result</th></tr></thead>
      <tbody>
      <?php foreach ($log as $w): ?>
        <tr>
          <td><?= htmlspecialchars((string) $w['created_at']) ?></td>
          <td><?= htmlspecialchars((string) ($w['client'] ?? '—')) ?></td>
          <td><code><?= htmlspecialchars((string) $w['to_number']) ?></code></td>
          <td><?= htmlspecialchars((string) $w['kind']) ?></td>
          <td><?= htmlspecialchars((string) $w['via']) ?></td>
          <td><?= (int) $w['ok'] === 1
                ? '<span class="pill on">sent</span> ' . htmlspecialchars((string) ($w['api_id'] ?? ''))
                : '<span class="pill bad">' . htmlspecialchars(substr((string) ($w['error'] ?? 'failed'), 0, 120)) . '</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$log): ?><tr><td colspan="6" class="sub">Nothing sent yet — configure the token, then use the test ping.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
