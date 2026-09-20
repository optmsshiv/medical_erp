-- ============================================================
-- CLIENT DATABASE SCHEMA TEMPLATE
-- Applied once per new client by scripts/provision-client.php,
-- then kept in sync via database/migrations/ + scripts/migrate-all.php.
--
-- medicines/batches match the fields the frontend (dashboard.html,
-- medicine-master.html etc.) actually expects. Still missing:
-- customers, suppliers, sales, sale_items, purchases, purchase_items,
-- payments, expenses — add these as those modules get built.
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(50)  NOT NULL DEFAULT 'staff',
    status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name  VARCHAR(100) NOT NULL,
    UNIQUE KEY uniq_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS manufacturers (
    id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name  VARCHAR(150) NOT NULL,
    UNIQUE KEY uniq_manufacturer_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS medicines (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,
    generic_name    VARCHAR(200) NULL,
    composition     VARCHAR(255) NULL,
    category_id     INT UNSIGNED NULL,
    manufacturer_id INT UNSIGNED NULL,
    brand_ref       VARCHAR(150) NULL,          -- e.g. "Crocin Advance"
    hsn_code        VARCHAR(20)  NULL,
    gst_rate        DECIMAL(5,2) NOT NULL DEFAULT 0,
    unit            VARCHAR(30)  NOT NULL DEFAULT 'Strip',
    pack_size       VARCHAR(50)  NULL,           -- e.g. "15 Tablets"
    mrp             DECIMAL(10,2) NOT NULL DEFAULT 0,
    purchase_rate   DECIMAL(10,2) NOT NULL DEFAULT 0,
    wholesale_rate  DECIMAL(10,2) NOT NULL DEFAULT 0,
    min_stock       INT NOT NULL DEFAULT 0,
    reorder_level   INT NOT NULL DEFAULT 0,
    schedule_class  VARCHAR(10)  NULL,            -- OTC / H / H1 / X etc.
    rx_required     TINYINT(1) NOT NULL DEFAULT 0,
    cold_chain      TINYINT(1) NOT NULL DEFAULT 0,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS batches (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id    INT UNSIGNED NOT NULL,
    batch_no       VARCHAR(50) NOT NULL,
    purchase_date  DATE NULL,
    expiry_date    DATE NOT NULL,
    quantity       INT NOT NULL DEFAULT 0,
    reserved       INT NOT NULL DEFAULT 0,       -- held against unconfirmed sales, if used later
    purchase_rate  DECIMAL(10,2) NOT NULL DEFAULT 0,
    mrp            DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add: doctors, prescriptions, sales_returns, payments, expenses,
-- audit_log, etc. as those modules get built.

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
    gst_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,  -- extracted from MRP, informational only
    round_off     DECIMAL(10,2) NOT NULL DEFAULT 0,
    grand_total   DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_mode  ENUM('cash','upi','card','credit','split') NOT NULL DEFAULT 'cash',
    cash_amount   DECIMAL(12,2) NOT NULL DEFAULT 0,   -- only meaningful for split
    upi_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,   -- only meaningful for split
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

-- Every client needs at least one customer to bill against from day one.
INSERT INTO customers (name) VALUES ('Walk-in Customer');

CREATE TABLE IF NOT EXISTS suppliers (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(200) NOT NULL,
    gstin      VARCHAR(20)  NULL,
    dl_no      VARCHAR(50)  NULL,       -- Drug License number
    phone      VARCHAR(20)  NULL,
    address    VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchases (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id   INT UNSIGNED NOT NULL,
    invoice_no    VARCHAR(100) NOT NULL,   -- the SUPPLIER's own invoice number
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
    batch_id      INT UNSIGNED NULL,      -- the batch this line created
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

-- ------------------------------------------------------------
-- Starter categories/manufacturers so the Add Medicine dropdowns
-- aren't empty on day one. Every client can edit/expand this list
-- later — these are just a sensible common Indian-pharmacy default.
-- ------------------------------------------------------------
INSERT INTO manufacturers (name) VALUES
    ('Sun Pharma'), ('Cipla'), ('Dr. Reddy''s'), ('Mankind'), ('Abbott'),
    ('Alkem'), ('Lupin'), ('Zydus'), ('Glenmark'), ('Torrent'), ('USV'),
    ('GSK Pharma'), ('Alembic'), ('Micro Labs'), ('Pfizer'), ('Bayer'),
    ('Sanofi'), ('FDC Ltd'), ('J&J');

INSERT INTO categories (name) VALUES
    ('Analgesic & Antipyretic'), ('Antibiotic'), ('Antacid & PPI'),
    ('Cardiovascular'), ('Antidiabetic'), ('Antihistamine'), ('Respiratory'),
    ('Nutraceutical'), ('Rehydration (ORS)'), ('Antiemetic'),
    ('Hormones & Insulin'), ('Topical & Dermatology');
