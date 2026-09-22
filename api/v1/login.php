<?php
session_start();
require dirname(__DIR__, 3) . '/middleware/tenant.php';
require dirname(__DIR__, 3) . '/core/Auth.php';
require dirname(__DIR__, 3) . '/core/Json.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Json::error('Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// The frontend's field is labelled "Email" but the JS still calls the
// key "username" in its JSON body — we treat it as the user's email,
// since that's what our `users` table authenticates against.
$email    = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if ($email === '' || $password === '') {
    Json::error('Email and password are required.', 422);
}

if (!Auth::attempt($email, $password)) {
    Json::error('Invalid email or password.', 401);
}

require dirname(__DIR__, 3) . '/core/Audit.php';
Audit::log('LOGIN', $email);

Json::ok();
