-- ============================================================================
-- OPTMS-RX · Billing engine (super-admin → tenants)
-- Run ONCE against the MASTER database, AFTER 2026_10_20_super_admin_control.sql
--   mysql -u root -p optms_master < database/2026_10_20_billing.sql
-- Idempotent — CREATE IF NOT EXISTS / guarded seeds only.
-- ============================================================================

CREATE TABLE IF NOT EXISTS billing_plans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(24) NOT NULL UNIQUE,              -- trial | standard | annual
    name VARCHAR(48) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,        -- trial/monthly/annual per its term
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO billing_plans (code, name, price, sort_order) VALUES
    ('trial',    'Trial — 14 days',        0,     1),
    ('standard', 'Standard — monthly',     0,     2),
    ('annual',   'Annual — yearly commit', 0,     3)
ON DUPLICATE KEY UPDATE code = code;               -- price edits happen in the portal, not here

CREATE TABLE IF NOT EXISTS client_invoices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    invoice_no VARCHAR(32) NOT NULL UNIQUE,
    period_from DATE NOT NULL,
    period_to DATE NOT NULL,
    plan VARCHAR(24) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('unpaid','paid','void') NOT NULL DEFAULT 'unpaid',
    due_date DATE NULL,
    paid_at DATETIME NULL,
    note VARCHAR(190) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_period (client_id, period_from),
    KEY idx_inv_status (status, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS billing_settings (
    `key` VARCHAR(48) NOT NULL PRIMARY KEY,
    `value` VARCHAR(190) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO billing_settings (`key`, `value`) VALUES
    ('grace_days', '7'),            -- due_date = period_from + grace
    ('auto_suspend_overdue', '0'),  -- 1 = suspend client when invoice passes due date unpaid
    ('currency', 'INR')
ON DUPLICATE KEY UPDATE `key` = `key`;
