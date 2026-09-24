-- Adds a Brand Name column to medicines (e.g. "Crocin Advance").
-- Run this BEFORE deploying the updated medicines.php, otherwise inserts/updates will fail.
-- If your app uses one database per tenant, run it on every tenant database.

ALTER TABLE medicines
  ADD COLUMN brand_name VARCHAR(150) NOT NULL DEFAULT '' AFTER generic_name;
