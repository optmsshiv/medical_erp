-- ============================================================
-- Adds Loose Sale / Cut Sale support (e.g. 1 Strip = 10 Tabs).
-- Safe to run on a client with real inventory — only ADD COLUMN
-- with defaults, no existing data is touched, dropped, or
-- reinterpreted. medicines.quantity keeps meaning "sealed packs"
-- everywhere it already does today.
-- ============================================================

ALTER TABLE medicines
    ADD COLUMN pack_qty INT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Sub-units per pack, e.g. 10 tablets per strip',
    ADD COLUMN sub_unit VARCHAR(30) NULL
        COMMENT 'Loose-sale unit label, e.g. Tablet, Capsule, Ml',
    ADD COLUMN allow_loose_sale TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE batches
    ADD COLUMN loose_qty INT NOT NULL DEFAULT 0
        COMMENT 'Sub-units currently loose from an opened pack of this batch';

ALTER TABLE sale_items
    ADD COLUMN unit_sold ENUM('pack','loose') NOT NULL DEFAULT 'pack',
    ADD COLUMN pack_qty_at_sale INT UNSIGNED NULL
        COMMENT 'Snapshot of medicines.pack_qty at sale time — keeps old invoices correct if pack_qty is edited later';
