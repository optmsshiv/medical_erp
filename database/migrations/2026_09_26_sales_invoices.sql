-- Optms Rx — sales invoices ledger
-- Separate from Sales Reports (reports.php?tab=sales).
-- Uses the existing tables only. Does not add columns and does not use batches.qty.
--
--   sales: invoice_no, sale_date, channel, customer_id, doctor_id, gstin, dl_no,
--          subtotal, discount, gst_amount, cgst, sgst, igst, round_off, grand_total,
--          payment_mode, cash_amount, upi_amount, amount_paid, balance_due
--   sale_items: qty, free_qty, rate, disc_pct, gst_pct, amount, unit_sold, pack_qty_at_sale
--   sales_returns.refund_amount, sales_return_items.qty
--   customers.name/phone, doctors.name, medicines.name, batches.batch_no, batches.expiry_date
--
-- MySQL 5.7 / MariaDB. Safe to re-run. Checkout stays on api/v1/sales.php.

SET @db = DATABASE();

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'sales' AND INDEX_NAME = 'idx_sales_sale_date'
    ),
    'SELECT 1',
    'ALTER TABLE sales ADD INDEX idx_sales_sale_date (sale_date)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'sales' AND INDEX_NAME = 'idx_sales_invoice_no'
    ),
    'SELECT 1',
    'ALTER TABLE sales ADD INDEX idx_sales_invoice_no (invoice_no)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE OR REPLACE VIEW v_sales_invoices AS
SELECT
  s.id AS id,
  s.invoice_no AS invoice_no,
  s.sale_date AS sale_date,
  s.channel AS channel,
  s.customer_id AS customer_id,
  COALESCE(c.name, 'Walk-in') AS customer_name,
  COALESCE(c.phone, '') AS customer_phone,
  COALESCE(c.type, s.channel) AS customer_type,
  s.doctor_id AS doctor_id,
  COALESCE(d.name, '') AS doctor_name,
  COALESCE(s.gstin, '') AS gstin,
  COALESCE(s.dl_no, '') AS dl_no,
  s.subtotal AS subtotal,
  s.discount AS discount,
  s.gst_amount AS gst_amount,
  s.cgst AS cgst,
  s.sgst AS sgst,
  s.igst AS igst,
  s.round_off AS round_off,
  s.grand_total AS grand_total,
  s.payment_mode AS payment_mode,
  s.cash_amount AS cash_amount,
  s.upi_amount AS upi_amount,
  s.amount_paid AS amount_paid,
  s.balance_due AS balance_due,
  s.created_at AS created_at,
  COALESCE(it.item_count, 0) AS item_count,
  COALESCE(it.qty, 0) AS qty,
  COALESCE(it.free_qty, 0) AS free_qty,
  COALESCE(rt.return_count, 0) AS return_count,
  COALESCE(rt.refund_amount, 0) AS refund_amount
FROM sales s
LEFT JOIN customers c ON c.id = s.customer_id
LEFT JOIN doctors d ON d.id = s.doctor_id
LEFT JOIN (
  SELECT sale_id, COUNT(*) AS item_count, COALESCE(SUM(qty), 0) AS qty, COALESCE(SUM(free_qty), 0) AS free_qty
  FROM sale_items
  GROUP BY sale_id
) it ON it.sale_id = s.id
LEFT JOIN (
  SELECT sale_id, COUNT(*) AS return_count, COALESCE(SUM(refund_amount), 0) AS refund_amount
  FROM sales_returns
  GROUP BY sale_id
) rt ON rt.sale_id = s.id;

CREATE OR REPLACE VIEW v_sales_invoice_items AS
SELECT
  si.id AS id,
  si.sale_id AS sale_id,
  si.medicine_id AS medicine_id,
  COALESCE(m.name, 'Removed medicine') AS medicine_name,
  COALESCE(m.unit, '') AS unit,
  si.batch_id AS batch_id,
  COALESCE(b.batch_no, '') AS batch_no,
  b.expiry_date AS expiry_date,
  si.qty AS qty,
  si.free_qty AS free_qty,
  si.rate AS rate,
  si.disc_pct AS disc_pct,
  si.gst_pct AS gst_pct,
  si.amount AS amount,
  si.unit_sold AS unit_sold,
  si.pack_qty_at_sale AS pack_qty_at_sale,
  COALESCE(ri.returned_qty, 0) AS returned_qty
FROM sale_items si
LEFT JOIN medicines m ON m.id = si.medicine_id
LEFT JOIN batches b ON b.id = si.batch_id
LEFT JOIN (
  SELECT sale_item_id, COALESCE(SUM(qty), 0) AS returned_qty
  FROM sales_return_items
  GROUP BY sale_item_id
) ri ON ri.sale_item_id = si.id;
