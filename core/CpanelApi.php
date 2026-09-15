<?php
/**
 * CpanelApi
 *
 * Talks to cPanel's UAPI to create databases/users on shared hosting,
 * where a plain MySQL user can't run CREATE DATABASE / CREATE USER itself.
 *
 * Setup (once): cPanel > Security > Manage API Tokens > create a token,
 * then set CPANEL_HOST, CPANEL_USER, CPANEL_TOKEN in .env.
 *
 * IMPORTANT — cPanel naming rules this class handles for you:
 *  - Every database and database-user name cPanel creates is automatically
 *    prefixed with your cPanel account username, e.g. asking for
 *    "optmsrx_greenpharma" as user "optmstec" actually creates
 *    "optmstec_optmsrx_greenpharma".
 *  - Total length (prefix + underscore + name) is capped — MySQL allows up
 *    to 64 chars for database names, but many cPanel/MySQL setups still
 *    cap the DB USERNAME at 16 chars total for compatibility. This class
 *    truncates generated suffixes to stay safely under that limit.
 */
class CpanelApi
{
    // Conservative default — override via CPANEL_DB_USER_MAX_LEN in .env if your
    // host confirms it supports longer db usernames.
    private const DEFAULT_USER_MAX_LEN = 16;

    private string $host;
    private string $user;
    private string $token;
    private int $userMaxLen;

    public function __construct()
    {
        $this->host = rtrim((string) getenv('CPANEL_HOST'), '/');   // e.g. yourdomain.com:2083
        $this->user = (string) getenv('CPANEL_USER');
        $this->token = (string) getenv('CPANEL_TOKEN');
        $this->userMaxLen = (int) (getenv('CPANEL_DB_USER_MAX_LEN') ?: self::DEFAULT_USER_MAX_LEN);

        if ($this->host === '' || $this->user === '' || $this->token === '') {
            throw new RuntimeException('CPANEL_HOST, CPANEL_USER, and CPANEL_TOKEN must be set in .env.');
        }
    }

    /**
     * Creates a database + a dedicated db user + grants that user full
     * privileges on that database.
     *
     * @param string $subdomain e.g. "greenpharma" — used to derive safe names
     * @return array{db_name: string, db_user: string, db_password: string}
     *   The db_name/db_user returned are the FULL, cPanel-prefixed names —
     *   store exactly these in the master `clients` table.
     */
    public function createClientDatabase(string $subdomain): array
    {
        $suffix     = $this->safeSuffix($subdomain);
        $dbName     = $this->prefixed($suffix, 64);
        $dbUser     = $this->prefixed('u_' . $suffix, $this->userMaxLen);
        $dbPassword = substr(bin2hex(random_bytes(16)), 0, 20);

        // create_database accepts the UNPREFIXED name and adds the account
        // prefix itself on this host.
        $this->call('Mysql', 'create_database', ['name' => $this->unprefixedPart($dbName)]);

        // create_user, on this host, requires the FULL, already-prefixed
        // name — it does NOT add the prefix itself. (Different cPanel/WHM
        // versions disagree on this, which is why the two calls below use
        // the full name while create_database above uses the suffix.)
        $this->call('Mysql', 'create_user', [
            'name'     => $dbUser,
            'password' => $dbPassword,
        ]);
        $this->call('Mysql', 'set_privileges_on_database', [
            'user'       => $dbUser,
            'database'   => $dbName,
            'privileges' => 'ALL PRIVILEGES',
        ]);

        return [
            'db_name'     => $dbName,     // full, prefixed — use this to connect
            'db_user'     => $dbUser,     // full, prefixed — use this to connect
            'db_password' => $dbPassword,
        ];
    }

    /**
     * cPanel's UAPI takes the UNPREFIXED name and adds the account prefix
     * itself — but PDO needs the FULL prefixed name to connect. This keeps
     * both forms straight without recomputing string logic in two places.
     */
    private function unprefixedPart(string $fullName): string
    {
        $prefix = $this->user . '_';
        return str_starts_with($fullName, $prefix) ? substr($fullName, strlen($prefix)) : $fullName;
    }

    private function prefixed(string $name, int $maxTotalLen): string
    {
        $prefix = $this->user . '_';
        $available = max(1, $maxTotalLen - strlen($prefix));
        return $prefix . substr($name, 0, $available);
    }

    private function safeSuffix(string $subdomain): string
    {
        $clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $subdomain));
        // Keep it short — leaves room for the "optmsrx_"/"u_" part + cPanel's own prefix.
        return substr($clean, 0, 10);
    }

    private function call(string $module, string $function, array $params): array
    {
        $url = "https://{$this->host}/execute/{$module}/{$function}?" . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: cpanel {$this->user}:{$this->token}"],
            CURLOPT_TIMEOUT        => 20,
        ]);
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException("cPanel API request failed ({$module}::{$function}): {$curlError}");
        }

        $response = json_decode($raw, true);

        if (!is_array($response) || empty($response['status'])) {
            $errors = $response['errors'] ?? $response['result']['errors'] ?? null;
            $message = is_array($errors) ? implode('; ', $errors) : ($raw ?: 'Unknown cPanel API error');
            throw new RuntimeException("cPanel API error ({$module}::{$function}): {$message}");
        }

        return $response;
    }
}