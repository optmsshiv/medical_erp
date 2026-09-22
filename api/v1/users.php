<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/core/Audit.php';
require dirname(__DIR__, 2) . '/models/User.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $rows = User::all('name');
        $rows = array_map(function ($u) {
            unset($u['password_hash']);
            return $u;
        }, $rows);
        Json::ok(['data' => $rows]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');

        if ($name === '' || $email === '') {
            Json::error('Name and email are required.', 422);
        }
        if (User::first('email', '=', $email)) {
            Json::error('A user with this email already exists.', 422);
        }

        $tempPassword = substr(bin2hex(random_bytes(6)), 0, 10);

        $id = User::create([
            'name'          => $name,
            'email'         => $email,
            'mobile'        => trim($input['mobile'] ?? ''),
            'password_hash' => password_hash($tempPassword, PASSWORD_DEFAULT),
            'role'          => $input['role'] ?? 'staff',
            'status'        => 'active',
        ]);

        Audit::log('USER_CREATE', "{$name} ({$email}) — role: " . ($input['role'] ?? 'staff'));

        // Temp password is shown ONCE here — it's hashed immediately above
        // and never retrievable again, same principle as client provisioning.
        Json::ok(['id' => $id, 'tempPassword' => $tempPassword]);
        break;

    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($input['id'] ?? 0);
        $user = User::find($id);
        if (!$user) {
            Json::error('User not found.', 404);
        }

        if (isset($input['status'])) {
            $newStatus = $input['status'] === 'active' ? 'active' : 'disabled';
            if ($user['id'] === Auth::user()['id'] && $newStatus === 'disabled') {
                Json::error('You cannot disable your own account while logged in.', 422);
            }
            User::update($id, ['status' => $newStatus]);
            Audit::log('USER_STATUS', "{$user['name']} set to {$newStatus}");
        }

        Json::ok();
        break;

    default:
        Json::error('Method not allowed.', 405);
}
