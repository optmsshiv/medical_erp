-- ============================================================
-- Run this ONCE against EACH existing client database
-- (edrppymy_optmspharm, sumati's db, etc.) via phpMyAdmin > SQL tab.
-- Safe to run even if these tables already exist (IF NOT EXISTS).
-- ============================================================

CREATE TABLE IF NOT EXISTS suppliers (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(200) NOT NULL,
    gstin      VARCHAR(20)  NULL,
    dl_no      VARCHAR(50)  NULL,
    phone      VARCHAR(20)  NULL,
    address    VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchases (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id   INT UNSIGNED NOT NULL,
    invoice_no    VARCHAR(100) NOT NULL,
    invoice_date  DATE NOT NULL,
    subtotal      DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount      DECIMAL(12,2) NOT NULL DEFAULT 0,
    taxable       DECIMAL(12,2) NOT NULL DEFAULT 0,
    cgst          DECIMAL(12,2) NOT NULL DEFAULT 0,
    sgst          DECIMAL(12,2) NOT NULL DEFAULT 0,
    igst          DECIMAL(12,2) NOT NULL DEFAULT 0,
    round_off     DECIMAL(10,2) NOT NULL DEFAULT 0,
    grand_total   DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_mode  ENUM('Cash','Bank','UPI','Credit') NOT NULL DEFAULT 'Credit',
    amount_paid   DECIMAL(12,2) NOT NULL DEFAULT 0,
    balance_due   DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_items (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_id   INT UNSIGNED NOT NULL,
    medicine_id   INT UNSIGNED NOT NULL,
    batch_id      INT UNSIGNED NULL,
    qty           INT NOT NULL DEFAULT 0,
    free_qty      INT NOT NULL DEFAULT 0,
    rate          DECIMAL(10,2) NOT NULL DEFAULT 0,
    disc_pct      DECIMAL(5,2) NOT NULL DEFAULT 0,
    gst_pct       DECIMAL(5,2) NOT NULL DEFAULT 0,
    amount        DECIMAL(12,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
