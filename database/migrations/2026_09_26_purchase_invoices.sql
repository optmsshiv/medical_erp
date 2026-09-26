-- Optms Rx — purchase invoices ledger
-- Separate from Purchase Reports (reports.php?tab=purchase).
-- Uses the existing tables only. Does not add columns.
-- Does not use batches.qty or batches.expiry. Batch identity is batch_no and expiry_date.
--
--   purchases: invoice_no, invoice_date, supplier_id, subtotal, discount, taxable,
--              cgst, sgst, igst, round_off, grand_total, payment_mode, amount_paid, balance_due
--   purchase_items: qty, free_qty, rate, disc_pct, gst_pct, amount, medicine_id, batch_id
--   purchase_returns.credit_amount, purchase_return_items.qty
--   suppliers.name/phone/gstin, medicines.name, batches.batch_no, batches.expiry_date
--
-- MySQL 5.7 / MariaDB. Safe to re-run. New purchase entry stays on purchase.php.

SET @db = DATABASE();

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'purchases' AND INDEX_NAME = 'idx_purchases_invoice_date'
    ),
    'SELECT 1',
    'ALTER TABLE purchases ADD INDEX idx_purchases_invoice_date (invoice_date)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'purchases' AND INDEX_NAME = 'idx_purchases_invoice_no'
    ),
    'SELECT 1',
    'ALTER TABLE purchases ADD INDEX idx_purchases_invoice_no (invoice_no)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE OR REPLACE VIEW v_purchase_invoices AS
SELECT
  p.id AS id,
  p.invoice_no AS invoice_no,
  p.invoice_date AS invoice_date,
  p.supplier_id AS supplier_id,
  COALESCE(s.name, 'Supplier') AS supplier_name,
  COALESCE(s.phone, '') AS supplier_phone,
  COALESCE(s.gstin, '') AS gstin,
  COALESCE(s.dl_no, '') AS dl_no,
  p.subtotal AS subtotal,
  p.discount AS discount,
  p.taxable AS taxable,
  p.cgst AS cgst,
  p.sgst AS sgst,
  p.igst AS igst,
  p.round_off AS round_off,
  p.grand_total AS grand_total,
  p.payment_mode AS payment_mode,
  p.amount_paid AS amount_paid,
  p.balance_due AS balance_due,
  p.created_at AS created_at,
  COALESCE(it.item_count, 0) AS item_count,
  COALESCE(it.qty, 0) AS qty,
  COALESCE(it.free_qty, 0) AS free_qty,
  COALESCE(rt.return_count, 0) AS return_count,
  COALESCE(rt.credit_amount, 0) AS credit_amount
FROM purchases p
LEFT JOIN suppliers s ON s.id = p.supplier_id
LEFT JOIN (
  SELECT purchase_id, COUNT(*) AS item_count, COALESCE(SUM(qty), 0) AS qty, COALESCE(SUM(free_qty), 0) AS free_qty
  FROM purchase_items
  GROUP BY purchase_id
) it ON it.purchase_id = p.id
LEFT JOIN (
  SELECT purchase_id, COUNT(*) AS return_count, COALESCE(SUM(credit_amount), 0) AS credit_amount
  FROM purchase_returns
  GROUP BY purchase_id
) rt ON rt.purchase_id = p.id;

CREATE OR REPLACE VIEW v_purchase_invoice_items AS
SELECT
  pi.id AS id,
  pi.purchase_id AS purchase_id,
  pi.medicine_id AS medicine_id,
  COALESCE(m.name, 'Removed medicine') AS medicine_name,
  COALESCE(m.unit, '') AS unit,
  pi.batch_id AS batch_id,
  COALESCE(b.batch_no, '') AS batch_no,
  b.expiry_date AS expiry_date,
  pi.qty AS qty,
  pi.free_qty AS free_qty,
  pi.rate AS rate,
  pi.disc_pct AS disc_pct,
  pi.gst_pct AS gst_pct,
  pi.amount AS amount,
  COALESCE(ri.returned_qty, 0) AS returned_qty
FROM purchase_items pi
LEFT JOIN medicines m ON m.id = pi.medicine_id
LEFT JOIN batches b ON b.id = pi.batch_id
LEFT JOIN (
  SELECT purchase_item_id, COALESCE(SUM(qty), 0) AS returned_qty
  FROM purchase_return_items
  GROUP BY purchase_item_id
) ri ON ri.purchase_item_id = pi.id;
