-- Optms Rx — customer dues ledger
-- Separate from Due Reports (reports.php?tab=dues).
-- Uses the existing tables only. Does not add columns.
--
--   sales.balance_due, amount_paid, grand_total, invoice_no, sale_date, payment_mode, channel, customer_id
--   sales_returns.refund_amount, sale_id
--   payments.party_type = 'customer', party_id, amount, mode, payment_date, note
--   customers.name, type, phone, gstin, dl_no, address
--
-- MySQL 5.7 / MariaDB. Safe to re-run.
-- Outstanding is calculated in the API so a payment already stored on the bill is not subtracted twice.

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

CREATE OR REPLACE VIEW v_customer_dues AS
SELECT
  c.id AS customer_id,
  c.name AS customer_name,
  c.type AS customer_type,
  COALESCE(c.phone, '') AS phone,
  COALESCE(c.gstin, '') AS gstin,
  COALESCE(c.dl_no, '') AS dl_no,
  COALESCE(c.address, '') AS address,
  COALESCE(b.bill_count, 0) AS bill_count,
  COALESCE(b.open_bills, 0) AS open_bills,
  COALESCE(b.billed, 0) AS billed,
  COALESCE(b.amount_paid, 0) AS amount_paid,
  COALESCE(b.balance_due, 0) AS balance_due,
  COALESCE(b.refund_amount, 0) AS refund_amount,
  COALESCE(pay.payments, 0) AS payments,
  b.oldest_open AS oldest_open
FROM customers c
LEFT JOIN (
  SELECT
    s.customer_id AS customer_id,
    COUNT(*) AS bill_count,
    SUM(CASE WHEN s.balance_due > 0 THEN 1 ELSE 0 END) AS open_bills,
    COALESCE(SUM(s.grand_total), 0) AS billed,
    COALESCE(SUM(s.amount_paid), 0) AS amount_paid,
    COALESCE(SUM(s.balance_due), 0) AS balance_due,
    COALESCE(SUM(r.refund_amount), 0) AS refund_amount,
    MIN(CASE WHEN s.balance_due > 0 THEN s.sale_date END) AS oldest_open
  FROM sales s
  LEFT JOIN (
    SELECT sale_id, COALESCE(SUM(refund_amount), 0) AS refund_amount
    FROM sales_returns
    GROUP BY sale_id
  ) r ON r.sale_id = s.id
  GROUP BY s.customer_id
) b ON b.customer_id = c.id
LEFT JOIN (
  SELECT party_id, COALESCE(SUM(amount), 0) AS payments
  FROM payments
  WHERE party_type = 'customer'
  GROUP BY party_id
) pay ON pay.party_id = c.id
WHERE b.bill_count > 0 OR pay.payments > 0;

CREATE OR REPLACE VIEW v_customer_due_bills AS
SELECT
  s.id AS id,
  s.customer_id AS customer_id,
  c.name AS customer_name,
  c.type AS customer_type,
  COALESCE(c.phone, '') AS phone,
  s.invoice_no AS invoice_no,
  s.sale_date AS sale_date,
  s.channel AS channel,
  s.payment_mode AS payment_mode,
  s.grand_total AS grand_total,
  s.amount_paid AS amount_paid,
  s.balance_due AS balance_due,
  COALESCE(r.refund_amount, 0) AS refund_amount
FROM sales s
JOIN customers c ON c.id = s.customer_id
LEFT JOIN (
  SELECT sale_id, COALESCE(SUM(refund_amount), 0) AS refund_amount
  FROM sales_returns
  GROUP BY sale_id
) r ON r.sale_id = s.id;
