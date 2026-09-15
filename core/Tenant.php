<?php
/**
 * Tenant
 *
 * Resolves WHICH client is making the current request (by subdomain),
 * looks them up in the master db, and exposes their isolated PDO connection.
 *
 * middleware/tenant.php should call Tenant::resolve() at the very start
 * of every request, before any controller/model code runs.
 */
class Tenant
{
    private static ?array $current = null;

    /**
     * Resolve the current client from the request host.
     * e.g. greenpharma.optmsrx.com -> subdomain "greenpharma"
     */
    public static function resolve(): array
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $subdomain = self::extractSubdomain($_SERVER['HTTP_HOST'] ?? '');

        if ($subdomain === null) {
            self::fail('Unable to determine client from request host.');
        }

        $stmt = Database::master()->prepare(
            'SELECT * FROM clients WHERE subdomain = :subdomain LIMIT 1'
        );
        $stmt->execute(['subdomain' => $subdomain]);
        $client = $stmt->fetch();

        if (!$client) {
            self::fail('Unknown client subdomain: ' . $subdomain);
        }

        if ($client['status'] !== 'active' && $client['status'] !== 'trial') {
            self::fail('This account is suspended. Contact support.');
        }

        self::$current = $client;

        return $client;
    }

    /**
     * Get the already-resolved client row. Call resolve() first (middleware does this).
     */
    public static function current(): array
    {
        if (self::$current === null) {
            return self::resolve();
        }

        return self::$current;
    }

    /**
     * Get a PDO connection scoped to the CURRENT client's own database.
     * This is what every Model should use — never Database::master() for app data.
     */
    public static function db(): PDO
    {
        $client = self::current();

        return Database::client(
            $client['db_name'],
            $client['db_user'],
            $client['db_password'],
            $client['db_host'] ?? 'localhost'
        );
    }

    private static function extractSubdomain(string $host): ?string
    {
        // Strip port if present (e.g. localhost:8080 during dev)
        $host = explode(':', $host)[0];
        $parts = explode('.', $host);

        // Expect subdomain.optmsrx.com -> at least 3 parts
        if (count($parts) < 3) {
            // Allow a LOCAL_DEV_SUBDOMAIN override for local testing without real subdomains
            $override = getenv('LOCAL_DEV_SUBDOMAIN');
            return $override ?: null;
        }

        return $parts[0];
    }

    private static function fail(string $message): never
    {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }
}