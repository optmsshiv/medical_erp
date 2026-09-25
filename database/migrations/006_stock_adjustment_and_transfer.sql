-- ============================================================
-- Adds Stock Adjustment (real qty corrections: damage, theft,
-- recount, write-off) and Stock Transfer (bounded — logs stock
-- moved between locations, does NOT split batches.quantity by
-- location yet; that's a bigger retrofit for later).
-- Safe on a client with real data — CREATE TABLE only.
-- ============================================================

-- --- Stock Adjustment --------------------------------------------------------
-- qty_change is signed: positive = stock found/added, negative = stock lost.
-- Unlike a sale, this directly corrects batches.quantity.
CREATE TABLE stock_adjustments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED NOT NULL,
    qty_change INT NOT NULL,
    reason ENUM('Damage', 'Expired Write-off', 'Theft / Loss', 'Stock Recount', 'Other') NOT NULL,
    notes VARCHAR(255) NULL,
    adjusted_by VARCHAR(150) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines (id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- --- Locations + Stock Transfer (bounded) -----------------------------------
CREATE TABLE locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address VARCHAR(255) NULL,
    phone VARCHAR(30) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_location_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Seed one location so today's single store already has somewhere to
-- transfer "from" — rename this later via the page once you add branches.
INSERT INTO locations (name) VALUES ('Main Store');

-- Bounded design: this is a MOVEMENT LOG only. batches.quantity is not
-- touched by a transfer — it still represents one global pool for the
-- batch. qty is validated against that pool at write time as a sanity
-- check, but nothing here enforces per-location stock yet.
CREATE TABLE stock_transfers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED NOT NULL,
    from_location_id INT UNSIGNED NOT NULL,
    to_location_id INT UNSIGNED NOT NULL,
    qty INT NOT NULL,
    status ENUM('completed', 'cancelled') NOT NULL DEFAULT 'completed',
    notes VARCHAR(255) NULL,
    transferred_by VARCHAR(150) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines (id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches (id) ON DELETE CASCADE,
    FOREIGN KEY (from_location_id) REFERENCES locations (id),
    FOREIGN KEY (to_location_id) REFERENCES locations (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
