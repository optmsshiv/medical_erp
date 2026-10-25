<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

/**
 * Who am I — GET /api/v1/whoami.php
 * Tells the front-end the signed-in user's role so it can apply UI policy
 * (e.g. cashier discount caps). Auth stores the user in the session; this
 * reads it defensively, preferring the Auth class when it exposes an accessor.
 */

$user = null;
if (method_exists('Auth', 'user')) {
    try { $user = Auth::user(); } catch (Throwable $e) { $user = null; }
}
if ($user === null) {
    $user = $_SESSION['user'] ?? $_SESSION['auth_user'] ?? null;
}

$role = '';
$name = '';
$id = 0;
if (is_array($user)) {
    $role = strtolower(trim((string) ($user['role'] ?? '')));
    $name = trim((string) ($user['name'] ?? ''));
    $id = (int) ($user['id'] ?? 0);
}
if ($role === '') {
    $role = strtolower(trim((string) ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
}

// Unknown/empty role is treated as admin (fail-open): the cashier-bashing the
// counter today is the owner on a single-user install until roles are used.
$privileged = !in_array($role, ['staff', 'cashier'], true);

Json::ok(['data' => [
    'id' => $id,
    'name' => $name,
    'role' => $role !== '' ? $role : 'admin',
    'privileged' => $privileged,
]]);
