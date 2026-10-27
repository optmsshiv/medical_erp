<?php
session_start();
require dirname(__DIR__) . '/config/database.php'; // for env() helper

/* Real sign-out for the portal shell (sidebar → Sign out). */
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $c = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $c['path'], $c['domain'], $c['secure'], $c['httponly']);
    }
    session_destroy();
    header('Location: /super-admin/login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = $_POST['key'] ?? '';
    $expected = env('SUPER_ADMIN_KEY', '');

    if ($expected !== '' && hash_equals($expected, $key)) {
        $_SESSION['super_admin'] = true;
        header('Location: /super-admin/clients.php');
        exit;
    }

    $error = 'Incorrect key.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Super Admin — OPTMS-RX</title>
<style>
    :root {
        --ink:#1A1D27; --ink-2:#5B6270; --ink-3:#8A8F9A; --line:#E5E7F0;
        --brand:#4338CA; --brand-strong:#3730A3; --brand-soft:#EEF0FE; --ring:rgba(67,56,202,.16);
        --bad:#9A2E24; --bad-soft:#FDECEC;
    }
    * { box-sizing:border-box; }
    body {
        margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:28px 16px;
        font-family:-apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", Roboto, sans-serif;
        font-feature-settings:"cv02","cv03","cv04"; color:var(--ink);
        background:
            radial-gradient(640px 340px at 12% -10%, rgba(99,91,255,.16), transparent 60%),
            radial-gradient(560px 300px at 96% 6%, rgba(67,56,202,.10), transparent 55%),
            linear-gradient(180deg, #F7F7FB 0%, #F1F2F8 100%);
    }
    .card {
        width:min(420px, 100%); background:#fff; border:1px solid var(--line); border-radius:18px;
        padding:34px 32px 26px;
        box-shadow:0 1px 2px rgba(23,25,40,.05), 0 24px 60px rgba(36,30,110,.10);
    }
    .mark {
        width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center;
        background:linear-gradient(135deg, #4F46E5, #3730A3); box-shadow:0 8px 20px rgba(67,56,202,.35);
        color:#fff;
    }
    .mark svg { width:26px; height:26px; }
    h1 { font-size:20px; letter-spacing:-.015em; margin:18px 0 4px; }
    .sub { margin:0 0 24px; font-size:13px; color:var(--ink-2); }
    label { display:block; font-size:11.5px; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color:var(--ink-3); margin-bottom:7px; }
    .field { position:relative; margin-bottom:18px; }
    .field svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); width:15px; height:15px; color:var(--ink-3); pointer-events:none; }
    input[type=password] {
        width:100%; padding:12px 14px 12px 36px; border:1px solid #D8DBE6; border-radius:11px;
        font-size:14.5px; color:var(--ink); background:#FBFBFE; transition:border-color .15s, box-shadow .15s, background .15s;
    }
    input[type=password]:focus { outline:none; border-color:var(--brand); background:#fff; box-shadow:0 0 0 4px var(--ring); }
    .btn {
        width:100%; padding:12px; border:0; border-radius:11px; cursor:pointer;
        background:linear-gradient(135deg, #4F46E5, #3B32C4); color:#fff; font-size:14.5px; font-weight:650;
        letter-spacing:.01em; box-shadow:0 6px 16px rgba(67,56,202,.28);
        transition:transform .15s, box-shadow .15s, filter .15s;
    }
    .btn:hover { transform:translateY(-1px); box-shadow:0 10px 24px rgba(67,56,202,.34); filter:brightness(1.03); }
    .btn:active { transform:translateY(0); box-shadow:0 4px 12px rgba(67,56,202,.25); }
    .error {
        display:flex; align-items:center; gap:8px; margin:0 0 16px; padding:10px 12px; font-size:13px;
        background:var(--bad-soft); border:1px solid #F1C9C4; color:var(--bad); border-radius:10px;
        animation:shake .4s ease;
    }
    .error svg { width:14px; height:14px; flex:none; }
    @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)} }
    .meta {
        margin-top:22px; padding-top:16px; border-top:1px solid #EFF1F6;
        display:flex; justify-content:space-between; align-items:center; font-size:11px; color:var(--ink-3);
    }
    .meta .dot { display:inline-flex; align-items:center; gap:6px; }
    .meta .dot::before { content:""; width:6px; height:6px; border-radius:50%; background:#22C55E; }
</style>
</head>
<body>
<div class="card">
    <div class="mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3l7 3v5c0 4.4-2.9 8.2-7 9.5-4.1-1.3-7-5.1-7-9.5V6l7-3z"/><path d="M9.2 12.1l2 2 3.9-4.2"/>
        </svg>
    </div>
    <h1>Super Admin Console</h1>
    <p class="sub">OPTMS-RX platform control — key-gated entry</p>

    <?php if ($error): ?>
        <div class="error" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4.5M12 16h.01"/></svg>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <label for="key">Access key</label>
        <div class="field">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/></svg>
            <input type="password" id="key" name="key" placeholder="Enter the master access key" autofocus required>
        </div>
        <button type="submit" class="btn">Enter console</button>
    </form>

    <div class="meta">
        <span class="dot">Console online</span>
        <span>Restricted area — authorised staff only</span>
    </div>
</div>
</body>
</html>
