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

/* Profile fields the settings UI edits; each entry = validator. */
const EDITABLE_PROFILE_FIELDS = ['name', 'email', 'mobile', 'role'];
const USER_ROLES = ['Owner / Admin', 'Pharmacist', 'Billing Clerk', 'Purchase Manager', 'staff'];

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
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Json::error('That email address is not valid.', 422);
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

        /* (a) status lifecycle — unchanged behavior from the original deploy */
        if (isset($input['status'])) {
            $newStatus = $input['status'] === 'active' ? 'active' : 'disabled';
            if ((int) $user['id'] === (int) (Auth::user()['id'] ?? 0) && $newStatus === 'disabled') {
                Json::error('You cannot disable your own account while logged in.', 422);
            }
            User::update($id, ['status' => $newStatus]);
            Audit::log('USER_STATUS', "{$user['name']} set to {$newStatus}");
        }

        /* (b) profile fields — name / email / mobile / role */
        $patch = [];
        foreach (EDITABLE_PROFILE_FIELDS as $f) {
            if (array_key_exists($f, $input)) { $patch[$f] = trim((string) $input[$f]); }
        }
        if ($patch) {
            if (isset($patch['name'])) {
                if ($patch['name'] === '') { Json::error('Name cannot be empty.', 422); }
                if (mb_strlen($patch['name']) > 80) { Json::error('Name is too long (80 max).', 422); }
            }
            if (isset($patch['email'])) {
                if (!filter_var($patch['email'], FILTER_VALIDATE_EMAIL)) { Json::error('That email address is not valid.', 422); }
                $taken = User::first('email', '=', $patch['email']);
                if ($taken && (int) $taken['id'] !== $id) { Json::error('A user with this email already exists.', 422); }
            }
            if (isset($patch['role']) && !in_array($patch['role'], USER_ROLES, true)) {
                Json::error('Unknown role: ' . $patch['role'], 422);
            }
            if (isset($patch['mobile']) && mb_strlen($patch['mobile']) > 20) {
                Json::error('Mobile number is too long.', 422);
            }

            // only write fields that actually changed — the audit line stays truthful
            $changed = [];
            foreach ($patch as $f => $v) {
                if ((string) ($user[$f] ?? '') !== $v) { $changed[$f] = $v; }
            }
            if ($changed) {
                User::update($id, $changed);
                Audit::log('USER_UPDATE', $user['name'] . ' — changed: ' . implode(', ', array_keys($changed)));
            }
        }

        /* (c) profile photo — data-URI upload; self-migrates the users.avatar column. */
        if (array_key_exists('avatar', $input)) {
            $av = trim((string) $input['avatar']);
            $pdo = Tenant::db();
            try {
                if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'avatar'")->fetch()) {
                    $pdo->exec('ALTER TABLE users ADD COLUMN avatar VARCHAR(190) NULL AFTER status');
                }
            } catch (Throwable $e) { Json::error('Avatar column could not be prepared: ' . $e->getMessage(), 500); }
            if ($av === '') {
                User::update($id, ['avatar' => null]);
                Audit::log('USER_AVATAR', $user['name'] . ' — photo removed');
                Json::ok(['avatar' => null]);
            }
            if (!preg_match('#^data:image/(png|jpe?g|webp);base64,([A-Za-z0-9+/=\r\n]+)$#', $av, $m)) {
                Json::error('Avatar must be a PNG, JPG or WebP image.', 422);
            }
            $bin = base64_decode($m[2], true);
            if ($bin === false || strlen($bin) > 400000) { Json::error('Avatar image is too large.', 422); }
            $ext = $m[1] === 'png' ? 'png' : ($m[1] === 'webp' ? 'webp' : 'jpg');
            $dir = dirname(__DIR__, 2) . '/assets/uploads/avatars';
            if (!is_dir($dir) && !mkdir($dir, 0775, true)) { Json::error('Uploads folder is not writable on the server.', 500); }
            $rel = 'assets/uploads/avatars/user_' . $id . '.' . $ext;
            if (file_put_contents($dir . '/user_' . $id . '.' . $ext, $bin) === false) { Json::error('Could not save the photo file.', 500); }
            User::update($id, ['avatar' => $rel]);
            Audit::log('USER_AVATAR', $user['name'] . ' — photo updated');
            Json::ok(['avatar' => $rel]);
        }

        Json::ok();
        break;

    default:
        Json::error('Method not allowed.', 405);
}
