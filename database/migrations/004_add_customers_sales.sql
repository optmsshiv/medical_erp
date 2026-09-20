-- ============================================================
-- Run this ONCE against EACH existing client database
-- (edrppymy_optmspharm, sumati's db, etc.) via phpMyAdmin > SQL tab.
-- ============================================================

CREATE TABLE IF NOT EXISTS customers (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    phone      VARCHAR(20)  NULL,
    address    VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sales (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id   INT UNSIGNED NOT NULL,
    invoice_no    VARCHAR(30)  NOT NULL,
    sale_date     DATE NOT NULL,
    subtotal      DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount      DECIMAL(12,2) NOT NULL DEFAULT 0,
    gst_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
    round_off     DECIMAL(10,2) NOT NULL DEFAULT 0,
    grand_total   DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_mode  ENUM('cash','upi','card','credit','split') NOT NULL DEFAULT 'cash',
    cash_amount   DECIMAL(12,2) NOT NULL DEFAULT 0,
    upi_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_paid   DECIMAL(12,2) NOT NULL DEFAULT 0,
    balance_due   DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sale_items (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id      INT UNSIGNED NOT NULL,
    medicine_id  INT UNSIGNED NOT NULL,
    batch_id     INT UNSIGNED NOT NULL,
    qty          INT NOT NULL DEFAULT 0,
    rate         DECIMAL(10,2) NOT NULL DEFAULT 0,
    disc_pct     DECIMAL(5,2) NOT NULL DEFAULT 0,
    gst_pct      DECIMAL(5,2) NOT NULL DEFAULT 0,
    amount       DECIMAL(12,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed the default Walk-in Customer if this client doesn't have one yet.
INSERT INTO customers (name)
SELECT 'Walk-in Customer'
WHERE NOT EXISTS (SELECT 1 FROM customers WHERE name = 'Walk-in Customer');
