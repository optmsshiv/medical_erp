<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php';
require __DIR__ . '/models/Medicine.php';

$client = Tenant::current();
$user   = Auth::user();

// Proves the whole chain works: this count comes from THIS client's own
// database, via Tenant::db() inside Model — nothing else to configure.
$medicineCount = Medicine::count();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — <?= htmlspecialchars($client['name']) ?></title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0; background: #F7F8FA; color: #1F2430;
        font-family: -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 14px;
    }
    .topbar {
        display: flex; align-items: center; justify-content: space-between;
        background: #fff; border-bottom: 1px solid #E2E4E9; padding: 14px 24px;
    }
    .topbar strong { font-size: 14px; }
    .topbar a { color: #5B6270; font-size: 13px; text-decoration: none; }
    .topbar a:hover { color: #1F2430; }
    .wrap { max-width: 720px; margin: 0 auto; padding: 32px 24px; }
    .card {
        background: #fff; border: 1px solid #E2E4E9; border-radius: 8px;
        padding: 22px 24px; margin-bottom: 16px;
    }
    .card .num { font-size: 28px; font-weight: 600; color: #1F2430; }
    .card .label { font-size: 12px; color: #8A8F9A; margin-top: 4px; }
    .greeting { color: #5B6270; margin: 0 0 22px; }
</style>
</head>
<body>
<div class="topbar">
    <strong><?= htmlspecialchars($client['name']) ?></strong>
    <a href="/logout.php">Log out</a>
</div>
<div class="wrap">
    <p class="greeting">Welcome, <?= htmlspecialchars($user['name']) ?>.</p>
    <div class="card">
        <div class="num"><?= (int) $medicineCount ?></div>
        <div class="label">Medicines in catalog</div>
    </div>
</div>
</body>
</html>