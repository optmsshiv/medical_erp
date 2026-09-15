<?php
/**
 * Auth
 *
 * Session-based login for the CURRENT client (resolved by Tenant).
 * Because each client's session cookie is naturally scoped to their own
 * subdomain by the browser, one client's login session can never be
 * read on another client's subdomain — no extra isolation code needed here.
 */
require_once __DIR__ . '/Tenant.php';

class Auth
{
    /**
     * Attempt to log in against the current client's `users` table.
     * Returns true/false — check errors via Auth::lastError() if needed.
     */
    public static function attempt(string $email, string $password): bool
    {
        $stmt = Tenant::db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        if ($user['status'] !== 'active') {
            return false;
        }

        self::login($user);
        return true;
    }

    /**
     * Store the logged-in user in session. Called by attempt() — you
     * normally won't call this directly.
     */
    public static function login(array $user): void
    {
        // Never keep the password hash in session.
        unset($user['password_hash']);
        $_SESSION['auth_user'] = $user;
    }

    public static function logout(): void
    {
        unset($_SESSION['auth_user']);
    }

    public static function check(): bool
    {
        return isset($_SESSION['auth_user']);
    }

    /** @return array|null The logged-in user's row (no password hash), or null if not logged in. */
    public static function user(): ?array
    {
        return $_SESSION['auth_user'] ?? null;
    }
}