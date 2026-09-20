<?php
session_start();
require dirname(__DIR__) . '/config/database.php'; // for env() helper

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
<title>Super Admin — OPTMS-RX</title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
        background: #F7F8FA; font-family: -apple-system, "Segoe UI", Roboto, sans-serif;
    }
    .box {
        background: #fff; border: 1px solid #E2E4E9; border-radius: 8px;
        padding: 32px; width: 320px;
    }
    h1 { font-size: 17px; margin: 0 0 20px; color: #1F2430; }
    label { font-size: 13px; color: #5B6270; display: block; margin-bottom: 6px; }
    input[type=password] {
        width: 100%; padding: 10px 12px; border: 1px solid #D7D9DF; border-radius: 6px;
        font-size: 14px; margin-bottom: 16px;
    }
    button {
        width: 100%; padding: 10px; background: #4F46E5; color: #fff; border: none;
        border-radius: 6px; font-size: 14px; cursor: pointer;
    }
    button:hover { background: #4338CA; }
    .error { color: #C0362C; font-size: 13px; margin-bottom: 12px; }
</style>
</head>
<body>
<div class="box">
    <h1>Super Admin Access</h1>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
        <label for="key">Access key</label>
        <input type="password" id="key" name="key" autofocus required>
        <button type="submit">Enter</button>
    </form>
</div>
</body>
</html>