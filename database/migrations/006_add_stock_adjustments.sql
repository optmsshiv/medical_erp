-- ============================================================
-- Run this ONCE against EACH existing client database
-- (edrppymy_optmspharm, sumati's db, etc.) via phpMyAdmin > SQL tab.
-- ============================================================

CREATE TABLE IF NOT EXISTS stock_adjustments (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id      INT UNSIGNED NOT NULL,
    batch_id         INT UNSIGNED NOT NULL,
    old_qty          INT NOT NULL,
    new_qty          INT NOT NULL,
    reason           VARCHAR(100) NOT NULL,
    note             VARCHAR(255) NULL,
    adjusted_by_user_id INT UNSIGNED NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (adjusted_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
