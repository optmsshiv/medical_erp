<?php
/**
 * Entry point — sends the browser to the right page.
 * dashboard.php/login.php now carry a real server-side auth guard
 * (middleware/auth.php), not just the frontend's own 401 handling —
 * this redirect is just a convenience for whoever hits "/" directly.
 */
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/core/Auth.php';

header('Location: ' . (Auth::check() ? '/dashboard.php' : '/login.php'));
exit;