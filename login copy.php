<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/core/Auth.php';

$client = Tenant::current();

if (Auth::check()) {
    header('Location: /index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (Auth::attempt($email, $password)) {
        header('Location: /index.php');
        exit;
    }

    $error = 'Incorrect email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — <?= htmlspecialchars($client['name']) ?></title>
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
    h1 { font-size: 17px; margin: 0 0 2px; color: #1F2430; }
    .sub { font-size: 12px; color: #8A8F9A; margin: 0 0 22px; }
    label { font-size: 13px; color: #5B6270; display: block; margin-bottom: 6px; }
    input {
        width: 100%; padding: 10px 12px; border: 1px solid #D7D9DF; border-radius: 6px;
        font-size: 14px; margin-bottom: 16px;
    }
    input:focus { outline: 2px solid #4F46E5; outline-offset: -1px; border-color: #4F46E5; }
    button {
        width: 100%; padding: 10px; background: #4F46E5; color: #fff; border: none;
        border-radius: 6px; font-size: 14px; cursor: pointer;
    }
    button:hover { background: #4338CA; }
    .error {
        background: #FDECEC; border: 1px solid #F4C6C2; color: #9A2E24;
        border-radius: 6px; padding: 9px 12px; font-size: 13px; margin-bottom: 16px;
    }
</style>
</head>
<body>
<div class="box">
    <h1><?= htmlspecialchars($client['name']) ?></h1>
    <p class="sub">OPTMS-RX</p>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" autofocus required>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <button type="submit">Log in</button>
    </form>
</div>
</body>
</html>