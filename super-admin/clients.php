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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Clients — OPTMS-RX Admin</title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0; background: #F7F8FA; color: #1F2430;
        font-family: -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 14px;
    }
    .wrap { max-width: 960px; margin: 0 auto; padding: 40px 24px; }
    h1 { font-size: 19px; margin: 0 0 4px; }
    .sub { color: #5B6270; margin: 0 0 32px; font-size: 13px; }

    .panel {
        background: #fff; border: 1px solid #E2E4E9; border-radius: 8px;
        padding: 24px; margin-bottom: 32px;
    }
    .panel h2 { font-size: 14px; margin: 0 0 16px; color: #1F2430; }

    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px; }
    .field { display: flex; flex-direction: column; }
    .field.full { grid-column: 1 / -1; }
    label { font-size: 12px; color: #5B6270; margin-bottom: 5px; }
    input {
        padding: 9px 11px; border: 1px solid #D7D9DF; border-radius: 6px; font-size: 14px;
    }
    input:focus { outline: 2px solid #4F46E5; outline-offset: -1px; border-color: #4F46E5; }
    .hint { font-size: 11px; color: #8A8F9A; margin-top: 4px; }

    .actions { margin-top: 18px; }
    button {
        padding: 9px 18px; background: #4F46E5; color: #fff; border: none;
        border-radius: 6px; font-size: 14px; cursor: pointer;
    }
    button:hover { background: #4338CA; }

    .alert { border-radius: 8px; padding: 16px 18px; margin-bottom: 24px; font-size: 13px; }
    .alert-error { background: #FDECEC; border: 1px solid #F4C6C2; color: #9A2E24; }
    .alert-success { background: #EDFBF3; border: 1px solid #B9EAC9; color: #1B6B3C; }
    .alert-success h3 { margin: 0 0 10px; font-size: 13px; color: #1B6B3C; }
    .cred-row { display: flex; gap: 8px; margin-bottom: 6px; }
    .cred-label { width: 110px; color: #3F8A5C; flex-shrink: 0; }
    .cred-value {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace; background: #fff;
        border: 1px solid #B9EAC9; border-radius: 4px; padding: 2px 8px;
    }
    .cred-warn { margin-top: 10px; font-size: 12px; color: #5B6270; }

    table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #E2E4E9; border-radius: 8px; overflow: hidden; }
    th, td { text-align: left; padding: 11px 14px; border-bottom: 1px solid #EEF0F3; font-size: 13px; }
    th { color: #5B6270; font-weight: 600; background: #FAFBFC; }
    tr:last-child td { border-bottom: none; }
    .status { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    .status-trial { background: #FFF3DC; color: #93650A; }
    .status-active { background: #E4F6EA; color: #1B6B3C; }
    .status-suspended { background: #FDECEC; color: #9A2E24; }
    .empty { padding: 30px; text-align: center; color: #8A8F9A; }
</style>
</head>
<body>
<div class="wrap">
    <h1>Clients</h1>
    <p class="sub">Add a new pharmacy client — this creates their database and their first admin login.</p>

    <?php if ($flashError): ?>
        <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success">
            <h3>Client created — save these credentials now, they won't be shown again</h3>
            <div class="cred-row"><span class="cred-label">Login URL</span><span class="cred-value"><?= htmlspecialchars($flashSuccess['login_url']) ?></span></div>
            <div class="cred-row"><span class="cred-label">Admin email</span><span class="cred-value"><?= htmlspecialchars($flashSuccess['admin_email']) ?></span></div>
            <div class="cred-row"><span class="cred-label">Password</span><span class="cred-value"><?= htmlspecialchars($flashSuccess['admin_password']) ?></span></div>
            <div class="cred-warn">The database (<?= htmlspecialchars($flashSuccess['db_name']) ?>) was created and the schema applied. Point <?= htmlspecialchars($flashSuccess['subdomain']) ?>.<?= htmlspecialchars($flashSuccess['domain']) ?> at this app before sharing the login URL.</div>
        </div>
    <?php endif; ?>

    <div class="panel">
        <h2>Add a client</h2>
        <form method="POST" action="/super-admin/actions/create-client.php">
            <div class="grid">
                <div class="field">
                    <label for="name">Business name</label>
                    <input type="text" id="name" name="name" placeholder="Green Pharma, Madhepura" required>
                </div>
                <div class="field">
                    <label for="subdomain">Subdomain</label>
                    <input type="text" id="subdomain" name="subdomain" placeholder="greenpharma" required>
                    <div class="hint">Becomes greenpharma.<?= htmlspecialchars($appDomain) ?></div>
                </div>
                <div class="field">
                    <label for="admin_name">Admin contact name</label>
                    <input type="text" id="admin_name" name="admin_name" placeholder="Ramesh Kumar">
                </div>
                <div class="field">
                    <label for="admin_email">Admin login email</label>
                    <input type="email" id="admin_email" name="admin_email" placeholder="ramesh@greenpharma.example" required>
                </div>
                <div class="field full">
                    <label for="admin_password">Admin password</label>
                    <input type="text" id="admin_password" name="admin_password" placeholder="Leave blank to auto-generate a secure password">
                </div>
            </div>
            <div class="actions">
                <button type="submit">Create client</button>
            </div>
        </form>
    </div>

    <table>
        <thead>
            <tr><th>Business</th><th>Subdomain</th><th>Database</th><th>Status</th><th>Added</th></tr>
        </thead>
        <tbody>
            <?php if (!$clients): ?>
                <tr><td colspan="5" class="empty">No clients yet — add the first one above.</td></tr>
            <?php else: foreach ($clients as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['name']) ?></td>
                    <td><?= htmlspecialchars($c['subdomain']) ?>.<?= htmlspecialchars($appDomain) ?></td>
                    <td><?= htmlspecialchars($c['db_name']) ?></td>
                    <td><span class="status status-<?= htmlspecialchars($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
                    <td><?= htmlspecialchars(date('d M Y', strtotime($c['created_at']))) ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>