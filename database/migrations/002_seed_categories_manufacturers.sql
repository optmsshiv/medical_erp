-- ============================================================
-- Run this ONCE against your existing test client database
-- (edrppymy_optmspharm), via phpMyAdmin > SQL tab. It was
-- provisioned before categories/manufacturers were seeded by
-- default, so its dropdowns are currently empty.
--
-- INSERT IGNORE is used so this is safe to re-run without
-- creating duplicates if some names already exist.
-- ============================================================

-- The original schema had no unique constraint on these — add it first,
-- otherwise INSERT IGNORE below won't actually prevent duplicates.
ALTER TABLE categories ADD UNIQUE KEY uniq_category_name (name);

ALTER TABLE manufacturers
ADD UNIQUE KEY uniq_manufacturer_name (name);

INSERT IGNORE INTO
    manufacturers (name)
VALUES ('Sun Pharma'),
    ('Cipla'),
    ('Dr. Reddy''s'),
    ('Mankind'),
    ('Abbott'),
    ('Alkem'),
    ('Lupin'),
    ('Zydus'),
    ('Glenmark'),
    ('Torrent'),
    ('USV'),
    ('GSK Pharma'),
    ('Alembic'),
    ('Micro Labs'),
    ('Pfizer'),
    ('Bayer'),
    ('Sanofi'),
    ('FDC Ltd'),
    ('J&J');

INSERT IGNORE INTO
    categories (name)
VALUES ('Analgesic & Antipyretic'),
    ('Antibiotic'),
    ('Antacid & PPI'),
    ('Cardiovascular'),
    ('Antidiabetic'),
    ('Antihistamine'),
    ('Respiratory'),
    ('Nutraceutical'),
    ('Rehydration (ORS)'),
    ('Antiemetic'),
    ('Hormones & Insulin'),
    ('Topical & Dermatology');