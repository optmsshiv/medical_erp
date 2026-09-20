<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/core/Auth.php';

// Already logged in? Skip the login form entirely.
if (Auth::check()) {
    header('Location: /index.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    body { min-height: 100vh; display: flex; align-items: center; }
    .login-hero {
      background: linear-gradient(160deg, var(--mf-primary-dark) 0%, var(--mf-primary) 55%, var(--mf-accent) 100%);
      border-radius: 1rem 0 0 1rem; color: #fff; padding: 3rem 2.5rem; display: flex; flex-direction: column;
    }
    @media (max-width: 991.98px) { .login-hero { border-radius: 1rem 1rem 0 0; } }
  </style>
</head>
<body class="bg-light">
  <div class="container py-4" style="max-width:960px">
    <div class="card-mf overflow-hidden shadow-lg">
      <div class="row g-0">
        <div class="col-lg-6">
          <div class="login-hero h-100">
            <div class="d-flex align-items-center gap-2 mb-5">
              <img src="assets/images/logo.svg" width="44" alt="">
              <div>
                <div class="fw-bold fs-5">Optms Rx</div>
                <div class="small" style="color:#BFE3DA">by Optms Tech</div>
              </div>
            </div>
            <h1 class="fw-bold" style="font-size:1.6rem;letter-spacing:-0.02em">
              Complete Retail &amp; Wholesale Pharmacy Management.
            </h1>
            <p class="mt-2 mb-4" style="color:#D7EBE6">
              POS billing, batch &amp; expiry control, GST invoicing, dues and pharmacy reports — one login for your whole store.
            </p>
            <div class="mt-auto">
              <div class="small" style="color:#BFE3DA"><i class="bi bi-shield-lock me-2"></i>Session secured · Roles &amp; permissions enforced</div>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="p-4 p-lg-5">
            <h2 class="fs-5 fw-bold mb-1">Sign in</h2>
            <p class="text-2 small mb-4">Use your staff credentials to continue.</p>
            <div class="alert alert-light border py-2 small d-none" id="loginError"></div>
            <form id="loginForm" novalidate>
              <div class="mb-3">
                <label class="form-label" for="loginUser">Email</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-person"></i></span>
                  <input type="email" class="form-control" id="loginUser" autocomplete="username" required>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label" for="loginPass">Password</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-lock"></i></span>
                  <input type="password" class="form-control" id="loginPass" autocomplete="current-password" required>
                  <button class="btn btn-light-mf" type="button" id="loginPeek" tabindex="-1"><i class="bi bi-eye"></i></button>
                </div>
              </div>
              <button class="btn btn-mf w-100 py-2" id="loginBtn" type="submit">
                <span id="loginBtnLabel"><i class="bi bi-box-arrow-in-right me-1"></i>Sign in</span>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <p class="text-center text-2 small mt-3 mb-0">Optms Rx · Stage 2 build · PHP 8 + PDO backend connected</p>
  </div>

  <script src="assets/js/config.js"></script>
  <script>
    (function () {
      const cfg = window.MF_CONFIG || { apiBase: 'api/v1' };
      const errBox = document.getElementById('loginError');
      const btn = document.getElementById('loginBtn');
      const label = document.getElementById('loginBtnLabel');

      // offline backend → straight in (demo-data mode)
      if (!cfg.backend) { location.replace('index.php'); return; }

      document.getElementById('loginPeek').addEventListener('click', () => {
        const p = document.getElementById('loginPass');
        p.type = p.type === 'password' ? 'text' : 'password';
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
          errBox.innerHTML = '<i class="bi bi-exclamation-triangle me-1 text-danger"></i>' + err.message;
          errBox.classList.remove('d-none');
          btn.disabled = false;
          label.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i>Sign in';
        }
      });
    })();
  </script>
</body>
</html>