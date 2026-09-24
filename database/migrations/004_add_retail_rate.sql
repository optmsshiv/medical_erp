-- ============================================================
-- Adds a real, independently-editable Retail Rate to medicines.
-- (Medicine Master's Retail Rate field existed in the UI but was
-- never actually wired to the database — this closes that gap.)
-- Safe on a client with real data — ADD COLUMN with a default,
-- then a one-time backfill so existing medicines don't suddenly
-- show retail_rate = 0.
-- ============================================================

ALTER TABLE medicines
    ADD COLUMN retail_rate DECIMAL(10, 2) NOT NULL DEFAULT 0
        COMMENT 'Actual counter selling price — may differ from mrp';

UPDATE medicines SET retail_rate = mrp WHERE retail_rate = 0;
