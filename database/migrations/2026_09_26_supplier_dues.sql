-- Optms Rx — supplier dues ledger
-- Separate from Due Reports (reports.php?tab=dues).
-- Uses the existing tables only. Does not add columns.
--
--   purchases.balance_due, amount_paid, grand_total, invoice_no, invoice_date, payment_mode, supplier_id
--   purchase_returns.credit_amount, purchase_id
--   payments.party_type = 'supplier', party_id, amount, mode, payment_date, note
--   suppliers.name, phone, gstin, dl_no, address
--
-- MySQL 5.7 / MariaDB. Safe to re-run.
-- Outstanding is calculated in the API so a payment already stored on the bill is not subtracted twice.

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

CREATE OR REPLACE VIEW v_supplier_dues AS
SELECT
  s.id AS supplier_id,
  s.name AS supplier_name,
  COALESCE(s.phone, '') AS phone,
  COALESCE(s.gstin, '') AS gstin,
  COALESCE(s.dl_no, '') AS dl_no,
  COALESCE(s.address, '') AS address,
  COALESCE(b.bill_count, 0) AS bill_count,
  COALESCE(b.open_bills, 0) AS open_bills,
  COALESCE(b.billed, 0) AS billed,
  COALESCE(b.amount_paid, 0) AS amount_paid,
  COALESCE(b.balance_due, 0) AS balance_due,
  COALESCE(b.return_credit, 0) AS return_credit,
  COALESCE(pay.payments, 0) AS payments,
  b.oldest_open AS oldest_open
FROM suppliers s
LEFT JOIN (
  SELECT
    p.supplier_id AS supplier_id,
    COUNT(*) AS bill_count,
    SUM(CASE WHEN p.balance_due > 0 THEN 1 ELSE 0 END) AS open_bills,
    COALESCE(SUM(p.grand_total), 0) AS billed,
    COALESCE(SUM(p.amount_paid), 0) AS amount_paid,
    COALESCE(SUM(p.balance_due), 0) AS balance_due,
    COALESCE(SUM(r.credit_amount), 0) AS return_credit,
    MIN(CASE WHEN p.balance_due > 0 THEN p.invoice_date END) AS oldest_open
  FROM purchases p
  LEFT JOIN (
    SELECT purchase_id, COALESCE(SUM(credit_amount), 0) AS credit_amount
    FROM purchase_returns
    GROUP BY purchase_id
  ) r ON r.purchase_id = p.id
  GROUP BY p.supplier_id
) b ON b.supplier_id = s.id
LEFT JOIN (
  SELECT party_id, COALESCE(SUM(amount), 0) AS payments
  FROM payments
  WHERE party_type = 'supplier'
  GROUP BY party_id
) pay ON pay.party_id = s.id
WHERE b.bill_count > 0 OR pay.payments > 0;

CREATE OR REPLACE VIEW v_supplier_due_bills AS
SELECT
  p.id AS id,
  p.supplier_id AS supplier_id,
  s.name AS supplier_name,
  COALESCE(s.phone, '') AS phone,
  COALESCE(s.gstin, '') AS gstin,
  p.invoice_no AS invoice_no,
  p.invoice_date AS invoice_date,
  p.payment_mode AS payment_mode,
  p.grand_total AS grand_total,
  p.amount_paid AS amount_paid,
  p.balance_due AS balance_due,
  COALESCE(r.credit_amount, 0) AS return_credit
FROM purchases p
JOIN suppliers s ON s.id = p.supplier_id
LEFT JOIN (
  SELECT purchase_id, COALESCE(SUM(credit_amount), 0) AS credit_amount
  FROM purchase_returns
  GROUP BY purchase_id
) r ON r.purchase_id = p.id;
