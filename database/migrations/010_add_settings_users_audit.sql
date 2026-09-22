-- ============================================================
-- Run this ONCE against EACH existing client database
-- (edrppymy_optmspharm, sumati's db, etc.) via phpMyAdmin > SQL tab.
-- ============================================================

ALTER TABLE users
    ADD COLUMN mobile VARCHAR(20) NULL AFTER email,
    ADD COLUMN last_login TIMESTAMP NULL AFTER status;

CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(80) PRIMARY KEY,
    setting_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTE: config storage only — no endpoint currently enforces these.
CREATE TABLE IF NOT EXISTS role_permissions (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role    VARCHAR(50) NOT NULL,
    module  VARCHAR(50) NOT NULL,
    allowed TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_role_module (role, module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    user_name  VARCHAR(150) NULL,
    action     VARCHAR(50) NOT NULL,
    detail     VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO role_permissions (role, module, allowed) VALUES
    ('Owner / Admin','Retail POS',1),('Owner / Admin','Wholesale Billing',1),('Owner / Admin','Purchase',1),
    ('Owner / Admin','Inventory',1),('Owner / Admin','Expiry',1),('Owner / Admin','Reports',1),
    ('Owner / Admin','Payments',1),('Owner / Admin','Administration',1),
    ('Pharmacist','Retail POS',1),('Pharmacist','Wholesale Billing',1),('Pharmacist','Purchase',0),
    ('Pharmacist','Inventory',1),('Pharmacist','Expiry',1),('Pharmacist','Reports',1),
    ('Pharmacist','Payments',1),('Pharmacist','Administration',0),
    ('Billing Clerk','Retail POS',1),('Billing Clerk','Wholesale Billing',1),('Billing Clerk','Purchase',0),
    ('Billing Clerk','Inventory',0),('Billing Clerk','Expiry',0),('Billing Clerk','Reports',0),
    ('Billing Clerk','Payments',1),('Billing Clerk','Administration',0),
    ('Purchase Manager','Retail POS',0),('Purchase Manager','Wholesale Billing',0),('Purchase Manager','Purchase',1),
    ('Purchase Manager','Inventory',1),('Purchase Manager','Expiry',1),('Purchase Manager','Reports',1),
    ('Purchase Manager','Payments',0),('Purchase Manager','Administration',0);
