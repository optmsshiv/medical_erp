-- Optms Rx — stock overview
-- Live position used by api/v1/stock-overview.php and stock-overview.php.
-- This is not the stock report. The report stays on reports.php?tab=stock.
-- MySQL / MariaDB. Safe to re-run.
--
-- Expected columns:
--   medicines.id, name, unit, min_stock, category_id, manufacturer_id
--   batches.medicine_id, qty, purchase_rate, mrp, expiry
--   categories.id, name
--   manufacturers.id, name

SET @db = DATABASE();

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'batches' AND INDEX_NAME = 'idx_batches_medicine_qty'
    ),
    'SELECT 1',
    'ALTER TABLE batches ADD INDEX idx_batches_medicine_qty (medicine_id, qty)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'batches' AND INDEX_NAME = 'idx_batches_expiry'
    ),
    'SELECT 1',
    'ALTER TABLE batches ADD INDEX idx_batches_expiry (expiry)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE OR REPLACE VIEW v_stock_overview AS
SELECT
  m.id AS medicine_id,
  m.name AS medicine_name,
  COALESCE(c.name, 'Unassigned') AS category_name,
  COALESCE(mf.name, 'Unassigned') AS manufacturer_name,
  m.unit AS unit,
  COALESCE(m.min_stock, 0) AS min_stock,
  COALESCE(SUM(b.qty), 0) AS qty,
  COUNT(b.id) AS batch_count,
  COALESCE(SUM(b.qty * b.purchase_rate), 0) AS stock_value,
  COALESCE(SUM(b.qty * b.mrp), 0) AS mrp_value,
  MIN(CASE WHEN b.qty > 0 THEN b.expiry END) AS next_expiry
FROM medicines m
LEFT JOIN batches b ON b.medicine_id = m.id
LEFT JOIN categories c ON c.id = m.category_id
LEFT JOIN manufacturers mf ON mf.id = m.manufacturer_id
GROUP BY m.id, m.name, c.name, mf.name, m.unit, m.min_stock;
