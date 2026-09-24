-- ============================================================
-- Adds: barcode uniqueness, generic/substitute linking,
-- per-medicine expiry alert threshold, and box-level packaging.
-- Safe on a client with real data — ADD COLUMN / CREATE TABLE only.
--
-- Note on barcode: existing medicines have no barcode saved yet
-- (the form field was never wired to the database), so every
-- existing row starts NULL. A UNIQUE index allows multiple NULLs
-- in MySQL, so this won't fail on existing data.
-- ============================================================

ALTER TABLE medicines
    ADD COLUMN barcode VARCHAR(64) NULL,
    ADD UNIQUE KEY uniq_medicine_barcode (barcode);

-- --- Generic / substitute linking -------------------------------------------
-- Medicines sharing a generic_group_id are treated as substitutes of each
-- other (e.g. all brands of "Telmisartan 40mg"). A medicine with no group
-- is simply not linked to anything — nothing else changes for it.
CREATE TABLE generic_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_generic_group_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

ALTER TABLE medicines
    ADD COLUMN generic_group_id INT UNSIGNED NULL,
    ADD FOREIGN KEY (generic_group_id) REFERENCES generic_groups (id) ON DELETE SET NULL;

-- --- Per-medicine expiry alert threshold ------------------------------------
-- NULL = use the report/POS default (90 days). Only set this when a specific
-- medicine genuinely needs a different warning window.
ALTER TABLE medicines
    ADD COLUMN expiry_alert_days SMALLINT UNSIGNED NULL
        COMMENT 'Days-before-expiry warning threshold; NULL = use the 90-day default';

-- --- Two-level packaging (Box -> Strip/Pack -> Tablet) ----------------------
-- box_qty = how many packs/strips come in one purchase box. Purely a
-- purchasing-side convenience for now — Purchase Entry is the file that
-- would actually use this to convert "N boxes" into strips added to a
-- batch; that file hasn't been shared yet, so this only stores the value.
ALTER TABLE medicines
    ADD COLUMN box_qty INT UNSIGNED NULL
        COMMENT 'Packs/strips per purchase box, e.g. 10 strips per box',
    ADD COLUMN box_unit VARCHAR(30) NULL DEFAULT 'Box';
