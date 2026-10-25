-- ============================================================================
-- OPTMS-RX · Super-admin control plane (Phase 1)
-- Run ONCE against the MASTER database (the one holding the `clients` table):
--   mysql -u root -p optms_master < database/2026_10_20_super_admin_control.sql
-- Idempotent: ALTERs are attempted only when the column is missing.
-- ============================================================================

-- ---- clients: lifecycle fields ----
SET @db := DATABASE();

SET @has_plan := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'plan');
SET @sql := IF(@has_plan = 0,
  'ALTER TABLE clients ADD COLUMN plan VARCHAR(24) NOT NULL DEFAULT ''trial'' AFTER status',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_te := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'trial_ends_at');
SET @sql := IF(@has_te = 0,
  'ALTER TABLE clients ADD COLUMN trial_ends_at DATE NULL AFTER plan',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_sa := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'suspended_at');
SET @sql := IF(@has_sa = 0,
  'ALTER TABLE clients ADD COLUMN suspended_at DATETIME NULL AFTER trial_ends_at',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---- per-client feature entitlements (default allow=1 → nothing locks for
--      existing clients until you deliberately switch a feature off) ----
CREATE TABLE IF NOT EXISTS client_features (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    feature VARCHAR(40) NOT NULL,
    allowed TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_client_feature (client_id, feature)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- every super-admin action, recorded ----
CREATE TABLE IF NOT EXISTS admin_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor VARCHAR(80) NOT NULL DEFAULT 'key-session',
    client_id INT NULL,
    action VARCHAR(48) NOT NULL,
    detail TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
