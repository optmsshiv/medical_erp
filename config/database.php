<?php
/**
 * Master database configuration.
 * The MASTER db holds the client registry only (see database/master/schema.sql).
 * Each client's own data lives in a SEPARATE database — see core/Tenant.php.
 */

// Minimal .env loader (no composer dependency needed)
function env(string $key, $default = null)
{
    static $loaded = false;
    static $vars = [];

    if (!$loaded) {
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                if (!str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                $vars[$k] = $v;
                // Also register as a real env var so plain getenv() calls
                // elsewhere (CpanelApi.php, ClientProvisioner.php, Tenant.php)
                // see these values too, not just the env() helper below.
                putenv("{$k}={$v}");
            }
        }
        $loaded = true;
    }

    return $vars[$key] ?? $default;
}

return [
    'host'     => env('MASTER_DB_HOST', 'localhost'),
    'database' => env('MASTER_DB_NAME', 'edrppymy_optms_pharma'),
    'username' => env('MASTER_DB_USER', 'root'),
    'password' => env('MASTER_DB_PASS', ''),
    'charset'  => 'utf8mb4',
];