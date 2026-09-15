-- ============================================================
-- MASTER DATABASE
-- Holds ONLY the client registry. No pharmacy/sales/stock data
-- ever lives here — that all lives in each client's own database.
-- ============================================================

CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL, -- e.g. "Green Pharma, Madhepura"
    subdomain VARCHAR(100) NOT NULL UNIQUE, -- e.g. "greenpharma"
    db_host VARCHAR(100) NOT NULL DEFAULT 'localhost',
    db_name VARCHAR(64) NOT NULL UNIQUE,
    db_user VARCHAR(64) NOT NULL,
    db_password VARCHAR(255) NOT NULL, -- consider encrypting at rest, see note below
    plan VARCHAR(50) NOT NULL DEFAULT 'standard',
    status ENUM(
        'trial',
        'active',
        'suspended'
    ) NOT NULL DEFAULT 'trial',
    contact_email VARCHAR(150) NULL,
    contact_phone VARCHAR(20) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Optional: track migration versions applied per client, so migrate-all.php
-- knows what's already been run against each client database.
CREATE TABLE IF NOT EXISTS client_migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    migration VARCHAR(150) NOT NULL, -- e.g. "003_create_batches.sql"
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_client_migration (client_id, migration),
    FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- NOTE on db_password:
-- Storing it in plain text is the simplest starting point but not ideal.
-- Once this is working, consider encrypting it (e.g. openssl_encrypt with a
-- key kept in .env, never in this database) and decrypting only in Tenant::db().