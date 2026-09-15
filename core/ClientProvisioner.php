<?php
/**
 * ClientProvisioner
 *
 * One place that knows how to onboard a client: create their database
 * (via cPanel's UAPI — see core/CpanelApi.php), apply the schema, create
 * their first admin login, and register them in the master db. Used by
 * both scripts/provision-client.php (CLI) and
 * super-admin/actions/create-client.php (web form) so the two never
 * drift out of sync.
 */
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/CpanelApi.php';

class ClientProvisioner
{
    /**
     * @param array $input [
     *   'name'          => 'Green Pharma, Madhepura',
     *   'subdomain'     => 'greenpharma',
     *   'admin_name'    => 'Ramesh Kumar',
     *   'admin_email'   => 'ramesh@greenpharma.example',
     *   'admin_password'=> 'plain text, will be hashed' (optional — generated if omitted),
     * ]
     * @return array Result including generated db + admin credentials (show ONCE to the user).
     * @throws RuntimeException on any failure (subdomain taken, cPanel error, etc.)
     */
    public static function provision(array $input): array
    {
        $name          = trim($input['name'] ?? '');
        $subdomain     = strtolower(preg_replace('/[^a-z0-9]/i', '', $input['subdomain'] ?? ''));
        $adminName     = trim($input['admin_name'] ?? 'Admin');
        $adminEmail    = trim($input['admin_email'] ?? '');
        $adminPassword = $input['admin_password'] ?? self::randomPassword();

        if ($name === '' || $subdomain === '' || $adminEmail === '') {
            throw new RuntimeException('Client name, subdomain, and admin email are required.');
        }

        $master = Database::master();

        // Guard against duplicate subdomains before touching cPanel at all.
        $check = $master->prepare('SELECT id FROM clients WHERE subdomain = :s LIMIT 1');
        $check->execute(['s' => $subdomain]);
        if ($check->fetch()) {
            throw new RuntimeException("Subdomain \"{$subdomain}\" is already taken.");
        }

        // --- Step 1: create the database + db user via cPanel's UAPI ----------
        $cpanel = new CpanelApi();
        $dbCreds = $cpanel->createClientDatabase($subdomain);
        $dbName     = $dbCreds['db_name'];
        $dbUser     = $dbCreds['db_user'];
        $dbPassword = $dbCreds['db_password'];

        // --- Step 2: apply the schema template ---------------------------------
        $clientPdo = Database::client($dbName, $dbUser, $dbPassword);
        $schemaSql = file_get_contents(dirname(__DIR__) . '/database/client-schema/schema.sql');
        $clientPdo->exec($schemaSql);

        // --- Step 3: create the client's first admin login ---------------------
        $stmt = $clientPdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, status)
             VALUES (:name, :email, :hash, \'admin\', \'active\')'
        );
        $stmt->execute([
            'name'  => $adminName,
            'email' => $adminEmail,
            'hash'  => password_hash($adminPassword, PASSWORD_DEFAULT),
        ]);

        // --- Step 4: register the client in the master registry -----------------
        $stmt = $master->prepare(
            'INSERT INTO clients (name, subdomain, db_host, db_name, db_user, db_password, status)
             VALUES (:name, :subdomain, \'localhost\', :db_name, :db_user, :db_password, \'trial\')'
        );
        $stmt->execute([
            'name'        => $name,
            'subdomain'   => $subdomain,
            'db_name'     => $dbName,
            'db_user'     => $dbUser,
            'db_password' => $dbPassword, // see database/master/schema.sql note re: encrypting this at rest
        ]);

        return [
            'subdomain'      => $subdomain,
            'login_url'      => "https://{$subdomain}.optmsrx.com/login.php",
            'admin_email'    => $adminEmail,
            'admin_password' => $adminPassword, // show once — not retrievable after this
            'db_name'        => $dbName,
        ];
    }

    private static function randomPassword(int $length = 12): string
    {
        return substr(bin2hex(random_bytes($length)), 0, $length);
    }
}