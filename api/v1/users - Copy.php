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

        /* ---- Password flows -------------------------------------------------
         * Self-service change (action: password-otp → otp-verify → password-set)
         * and admin force-reset (action: reset-password). Identity always comes
         * from the SESSION — these endpoints can never target another account,
         * except reset-password which is deliberately admin-only. OTPs are
         * stored hashed, live 10 minutes, die after 5 wrong tries, and re-sends
         * are throttled to one per 45 s. The verify step mints a one-shot
         * 64-hex token; password-set consumes it. Everything self-migrates:
         * the challenge table is created on first use, no installer needed. */
        $action = trim((string) ($input['action'] ?? ''));
        if (in_array($action, ['password-otp', 'otp-verify', 'password-set', 'reset-password'], true)) {
            $pdo = Tenant::db();
            try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS user_pass_challenges (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                otp_hash VARCHAR(190) NOT NULL,
                verify_token CHAR(64) NULL,
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY upc_user (user_id),
                KEY upc_token (verify_token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            } catch (Throwable $e) { Json::error('Password challenge table could not be prepared on this database: ' . $e->getMessage(), 500); }

            $me = Auth::user();
            $myId = (int) ($me['id'] ?? 0);
            if ($myId <= 0) { Json::error('Your session has no user id — sign out and back in.', 401); }
            $meRow = User::find($myId);
            if (!$meRow) { Json::error('Account not found.', 404); }

            $maskEmail = function ($email) {
                $email = (string) $email;
                $at = strpos($email, '@');
                if ($at === false) { return '***'; }
                $local = substr($email, 0, $at);
                return ($local === '' ? '' : $local[0]) . str_repeat('*', max(2, strlen($local) - 1)) . substr($email, $at);
            };
            $sendMail = function ($to, $subject, $body) {
                $host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
                $headers  = "From: OPTMS-RX <noreply@{$host}>\r\n";
                $headers .= "Reply-To: noreply@{$host}\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                return @mail($to, $subject, $body, $headers);
            };

            if ($action === 'password-otp') {
                if (!filter_var($meRow['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    Json::error('This account has no valid email to receive a code — ask an owner to force-reset instead.', 422);
                }
                $cur = (string) ($input['current_password'] ?? '');
                if ($cur === '') { Json::error('Enter your current password first.', 422); }
                if (!password_verify($cur, (string) $meRow['password_hash'])) {
                    Audit::log('USER_PWD_DENY', ($meRow['name'] ?? '?') . ' — wrong current password while requesting an OTP');
                    Json::error('That current password is incorrect.', 422);
                }
                $last = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age FROM user_pass_challenges WHERE user_id = ? ORDER BY id DESC LIMIT 1');
                $last->execute([$myId]);
                $lc = $last->fetch();
                if ($lc && (int) $lc['age'] < 45) {
                    Json::error('A code was just sent — try again in ' . (45 - (int) $lc['age']) . 's.', 429);
                }
                $pdo->prepare('DELETE FROM user_pass_challenges WHERE user_id = ? AND (expires_at < NOW() OR attempts >= 5)')->execute([$myId]);
                $otp = (string) random_int(100000, 999999);
                $pdo->prepare('INSERT INTO user_pass_challenges (user_id, otp_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))')
                    ->execute([$myId, password_hash($otp, PASSWORD_DEFAULT)]);
                $body = "Hi {$meRow['name']},\n\nYour OPTMS-RX password change code is:\n\n    {$otp}\n\nIt expires in 10 minutes and works once. If you did not request this, ignore the email — your password stays unchanged.\n";
                if (!$sendMail($meRow['email'], 'Your OPTMS-RX security code', $body)) {
                    Json::error('The server could not send mail (PHP mail is unavailable here). An owner can force-reset your password instead.', 500);
                }
                Audit::log('USER_PWD_OTP', ($meRow['name'] ?? '?') . ' — password-change OTP emailed to ' . $maskEmail($meRow['email']));
                Json::ok(['data' => ['sent' => true, 'masked' => $maskEmail($meRow['email']), 'expiresMin' => 10, 'cooldownSec' => 45]]);
            }

            if ($action === 'otp-verify') {
                $otp = preg_replace('/\D/', '', (string) ($input['otp'] ?? ''));
                if (strlen($otp) !== 6) { Json::error('Enter the complete 6-digit code.', 422); }
                $st = $pdo->prepare('SELECT * FROM user_pass_challenges WHERE user_id = ? AND verify_token IS NULL AND expires_at >= NOW() AND attempts < 5 ORDER BY id DESC LIMIT 1');
                $st->execute([$myId]);
                $row = $st->fetch();
                if (!$row) { Json::error('That request has expired — start the change again.', 422); }
                $pdo->prepare('UPDATE user_pass_challenges SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
                if (!password_verify($otp, (string) $row['otp_hash'])) {
                    $left = max(0, 5 - ((int) $row['attempts'] + 1));
                    Json::error('Incorrect code — ' . $left . ' attempt(s) left before this code dies.', 422);
                }
                $token = bin2hex(random_bytes(32));
                $pdo->prepare('UPDATE user_pass_challenges SET verify_token = ?, expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?')
                    ->execute([$token, $row['id']]);
                Audit::log('USER_PWD_OTP_OK', ($meRow['name'] ?? '?') . ' — OTP verified for password change');
                Json::ok(['data' => ['verified' => true, 'token' => $token, 'expiresInSec' => 600]]);
            }

            if ($action === 'password-set') {
                $token = (string) ($input['token'] ?? '');
                if (!preg_match('/^[a-f0-9]{64}$/', $token)) { Json::error('Verification missing — start the change again.', 422); }
                $st = $pdo->prepare('SELECT id FROM user_pass_challenges WHERE user_id = ? AND verify_token = ? AND expires_at >= NOW() LIMIT 1');
                $st->execute([$myId, $token]);
                if (!$st->fetch()) { Json::error('Verification expired — request a fresh code.', 422); }
                $np = (string) ($input['new_password'] ?? '');
                if (strlen($np) < 8 || strlen($np) > 200 || !preg_match('/[A-Za-z]/', $np) || !preg_match('/\d/', $np)) {
                    Json::error('Password must be at least 8 characters and mix letters with numbers.', 422);
                }
                if (password_verify($np, (string) $meRow['password_hash'])) {
                    Json::error('Choose a password different from your current one.', 422);
                }
                User::update($myId, ['password_hash' => password_hash($np, PASSWORD_DEFAULT)]);
                $pdo->prepare('DELETE FROM user_pass_challenges WHERE user_id = ?')->execute([$myId]);
                $sendMail($meRow['email'], 'Your OPTMS-RX password was changed', "Hi {$meRow['name']},\n\nYour sign-in password was changed just now, after a verified OTP. If this was not you, tell an owner immediately — they can force-reset it in seconds.\n");
                Audit::log('USER_PASSWORD_CHANGE', ($meRow['name'] ?? '?') . ' — password changed after OTP verification');
                Json::ok(['data' => ['changed' => true]]);
            }

            /* admin force-reset: owner-issued temp password, shown once, same
               principle as account creation. Fail-open on empty legacy role,
               matching whoami.php's policy. */
            $tid = (int) ($input['id'] ?? 0);
            $myRole = strtolower(trim((string) ($me['role'] ?? $_SESSION['role'] ?? '')));
            $mayReset = ($myRole === '' || strpos($myRole, 'owner') !== false || strpos($myRole, 'admin') !== false);
            if (!$mayReset) { Json::error("Only an Owner / Admin can force-reset another user's password.", 403); }
            if ($tid === $myId) { Json::error('Use the OTP flow to change your own password.', 422); }
            $target = User::find($tid);
            if (!$target) { Json::error('User not found.', 404); }
            $temp = substr(bin2hex(random_bytes(6)), 0, 10);
            User::update($tid, ['password_hash' => password_hash($temp, PASSWORD_DEFAULT)]);
            $pdo->prepare('DELETE FROM user_pass_challenges WHERE user_id = ?')->execute([$tid]);
            $emailed = false;
            if (filter_var($target['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                $emailed = (bool) $sendMail($target['email'], 'Your OPTMS-RX password was reset', "Hi {$target['name']},\n\nAn administrator reset your OPTMS-RX sign-in password.\n\nTemporary password: {$temp}\n\nSign in with it and change it right away from Settings > Users > Change password (OTP protected).\n");
            }
            Audit::log('USER_PWD_RESET', $target['name'] . ' — temp password force-issued by ' . ($meRow['name'] ?? 'admin') . ($emailed ? ' (also emailed)' : ' (email unavailable)'));
            Json::ok(['data' => ['tempPassword' => $temp, 'emailed' => $emailed, 'email' => $target['email'] ?? '']]);
        }

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
