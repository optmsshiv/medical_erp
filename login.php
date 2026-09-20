<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/core/Auth.php';

$client = Tenant::current();

// Already logged in? Skip the login form entirely.
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
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="login">
  <div class="login-wrap">
    <!-- Brand panel -->
    <div class="login-hero">
      <div class="lh-brand">
        <div class="brand-mark"><i class="bi bi-capsule"></i></div>
        <div>
          <div class="brand-name">OPTMS-RX</div>
          <div class="brand-sub">Pharmacy Management System</div>
        </div>
      </div>
      <div class="lh-body">
        <h1>Run your entire pharmacy from one screen.</h1>
        <p class="lh-tag">Complete retail &amp; wholesale pharmacy management — batch-wise stock, expiry control, GST billing, dues and every report you need to run a modern medical store.</p>
        <div class="lh-points">
          <div class="pt"><i class="bi bi-lightning-charge-fill"></i><div><b>Fast retail POS &amp; wholesale billing</b><span>Barcode search, batch selection, split payments, hold &amp; resume bills</span></div></div>
          <div class="pt"><i class="bi bi-hourglass-split"></i><div><b>Expiry &amp; batch intelligence</b><span>Never lose money to expired stock again — automated alerts &amp; returns</span></div></div>
          <div class="pt"><i class="bi bi-file-earmark-text"></i><div><b>GST-ready billing &amp; reports</b><span>CGST/SGST/IGST, GSTR-1 summary, e-invoice ready</span></div></div>
        </div>
      </div>
      <div class="sb-footer">© <?= date('Y') ?> OPTMS Tech</div>
    </div>

    <!-- Login panel -->
    <div class="login-panel">
      <div class="login-card">
        <div class="d-lg-none d-flex align-items-center gap-2 mb-4">
          <div class="brand-mark" style="background:var(--mf-primary-soft);color:var(--mf-primary)"><i class="bi bi-capsule"></i></div>
          <div class="brand-name" style="color:var(--mf-text)">OPTMS-RX</div>
        </div>
        <h5 style="font-size:22px;font-weight:800;letter-spacing:-.02em;">Welcome back</h5>
        <p class="text-2 fs-13 mb-4">Sign in to <?= htmlspecialchars($client['name']) ?>.</p>

        <div id="loginError" class="alert alert-danger py-2 px-3 fs-13 d-none" role="alert"></div>

        <form id="loginForm">
          <label class="form-label-erp" for="loginUser">Email</label>
          <div class="input-icon mb-3">
            <i class="bi bi-person"></i>
            <input class="form-control-erp" id="loginUser" type="email" style="padding-left:36px" autocomplete="username" required>
          </div>
          <label class="form-label-erp" for="loginPass">Password</label>
          <div class="input-icon mb-2">
            <i class="bi bi-lock"></i>
            <input class="form-control-erp" id="loginPass" type="password" style="padding-left:36px" autocomplete="current-password" required>
            <button type="button" id="loginPeek" class="btn btn-sm p-0" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);border:none;color:var(--mf-text-2)"><i class="bi bi-eye"></i></button>
          </div>
          <div class="d-flex justify-content-end align-items-center mb-4">
            <a href="#" class="fs-13 fw-600" id="forgotLink">Forgot password?</a>
          </div>
          <button class="btn btn-primary btn-primary-erp btn-lg-erp w-100" type="submit" id="loginBtn">
            <span id="loginBtnLabel"><i class="bi bi-box-arrow-in-right me-1"></i>Sign in</span>
          </button>
        </form>

        <div class="text-center mt-4">
          <img src="assets/images/logo.svg" alt="OPTMS-RX" height="34">
          <div class="fs-12 text-3 mt-2">Complete Retail &amp; Wholesale Pharmacy Management System</div>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
    (function () {
      const cfg = window.MF_CONFIG || { apiBase: 'api/v1' };
      const errBox = document.getElementById('loginError');
      const btn = document.getElementById('loginBtn');
      const label = document.getElementById('loginBtnLabel');

      document.getElementById('loginPeek').addEventListener('click', () => {
        const p = document.getElementById('loginPass');
        p.type = p.type === 'password' ? 'text' : 'password';
      });

      document.getElementById('forgotLink').addEventListener('click', (e) => {
        e.preventDefault();
        MF.toast('Contact your administrator to reset your password.', 'info', 'Forgot password');
      });

      document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        errBox.classList.add('d-none');
        btn.disabled = true;
        label.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing in…';
        try {
          const res = await fetch(cfg.apiBase + '/auth/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
            credentials: 'same-origin',
            body: JSON.stringify({
              username: document.getElementById('loginUser').value.trim(),
              password: document.getElementById('loginPass').value,
            }),
          });
          const json = await res.json();
          if (!res.ok || !json.ok) throw new Error(json.error || 'Login failed');
          location.replace('index.php');
        } catch (err) {
          errBox.textContent = err.message;
          errBox.classList.remove('d-none');
          btn.disabled = false;
          label.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i>Sign in';
        }
      });
    })();
  </script>
</body>
</html>