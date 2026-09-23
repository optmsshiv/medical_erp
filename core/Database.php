<?php
/**
 * Database
 *
 * Thin PDO wrapper. Holds one connection to the MASTER db (client registry)
 * and caches one connection per CLIENT db, resolved at runtime by Tenant.php.
 *
 * Usage:
 *   Database::master()->query(...)          // client registry only
 *   Database::client('optmsrx_greenpharma')->query(...)   // a specific client's data
 */
class Database
{
    private static ?PDO $masterConnection = null;
    private static array $clientConnections = [];

    public static function master(): PDO
    {
        if (self::$masterConnection === null) {
            $config = require dirname(__DIR__) . '/config/database.php';
            self::$masterConnection = self::connect(
                $config['host'],
                $config['database'],
                $config['username'],
                $config['password'],
                $config['charset']
            );
        }

        return self::$masterConnection;
    }

    /**
     * Connect to a specific client's database.
     * $dbName, $dbUser, $dbPass come from the row Tenant.php looked up in the master db.
     */
    public static function client(string $dbName, string $dbUser, string $dbPass, string $host = 'localhost'): PDO
    {
        $cacheKey = $host . ':' . $dbName;

        if (!isset(self::$clientConnections[$cacheKey])) {
            self::$clientConnections[$cacheKey] = self::connect($host, $dbName, $dbUser, $dbPass, 'utf8mb4');
        }

        return self::$clientConnections[$cacheKey];
    }

    private static function connect(string $host, string $db, string $user, string $pass, string $charset): PDO
    {
        $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

        try {
            return new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+05:30'",
            ]);
        } catch (PDOException $e) {
            // Never leak DB credentials or raw PDO errors to the response.
            error_log('DB connection failed for ' . $db . ': ' . $e->getMessage());
            throw new RuntimeException('Database connection failed.');
        }
    }
}