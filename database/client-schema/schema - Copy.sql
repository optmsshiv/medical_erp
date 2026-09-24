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
    mobile        VARCHAR(20)  NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(50)  NOT NULL DEFAULT 'staff',
    status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_login    TIMESTAMP NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Simple key/value store for Store/Invoice/Tax settings — one row per
-- setting, read/written as a flat object by api/v1/settings.php.
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(80) PRIMARY KEY,
    setting_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Role permission matrix. NOTE: this is configuration storage only —
-- no page or API endpoint currently checks these values before allowing
-- an action. Wiring up real enforcement is a separate, larger task.
CREATE TABLE IF NOT EXISTS role_permissions (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role    VARCHAR(50) NOT NULL,
    module  VARCHAR(50) NOT NULL,
    allowed TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_role_module (role, module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    user_name  VARCHAR(150) NULL,   -- snapshot, survives the user being deleted later
    action     VARCHAR(50) NOT NULL,
    detail     VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
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
    type       ENUM('retail','wholesale') NOT NULL DEFAULT 'retail',
    phone      VARCHAR(20)  NULL,
    gstin      VARCHAR(20)  NULL,
    dl_no      VARCHAR(50)  NULL,
    address    VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sales (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id   INT UNSIGNED NOT NULL,
    channel       ENUM('retail','wholesale') NOT NULL DEFAULT 'retail',
    invoice_no    VARCHAR(30)  NOT NULL,
    sale_date     DATE NOT NULL,
    gstin         VARCHAR(20)  NULL,   -- snapshot of the billed party's GSTIN at invoice time
    dl_no         VARCHAR(50)  NULL,
    subtotal      DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount      DECIMAL(12,2) NOT NULL DEFAULT 0,
    gst_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,  -- cgst+sgst+igst, kept for quick totals
    cgst          DECIMAL(12,2) NOT NULL DEFAULT 0,
    sgst          DECIMAL(12,2) NOT NULL DEFAULT 0,
    igst          DECIMAL(12,2) NOT NULL DEFAULT 0,
    round_off     DECIMAL(10,2) NOT NULL DEFAULT 0,
    grand_total   DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_mode  ENUM('cash','upi','card','bank','credit','split') NOT NULL DEFAULT 'cash',
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
    free_qty     INT NOT NULL DEFAULT 0,
    rate         DECIMAL(10,2) NOT NULL DEFAULT 0,
    disc_pct     DECIMAL(5,2) NOT NULL DEFAULT 0,
    gst_pct      DECIMAL(5,2) NOT NULL DEFAULT 0,
    amount       DECIMAL(12,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every client needs at least one customer to bill against from day one.
INSERT INTO customers (name, type) VALUES ('Walk-in Customer', 'retail');

CREATE TABLE IF NOT EXISTS payments (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    party_type   ENUM('customer','supplier') NOT NULL,
    party_id     INT UNSIGNED NOT NULL,
    amount       DECIMAL(12,2) NOT NULL,
    mode         ENUM('Cash','Bank','UPI','Cheque') NOT NULL DEFAULT 'Cash',
    payment_date DATE NOT NULL,
    note         VARCHAR(255) NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_party (party_type, party_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock_adjustments (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id      INT UNSIGNED NOT NULL,
    batch_id         INT UNSIGNED NOT NULL,
    old_qty          INT NOT NULL,
    new_qty          INT NOT NULL,
    reason           VARCHAR(100) NOT NULL,
    note             VARCHAR(255) NULL,
    adjusted_by_user_id INT UNSIGNED NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (adjusted_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS sales_returns (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id       INT UNSIGNED NOT NULL,
    return_no     VARCHAR(30)  NOT NULL,
    return_date   DATE NOT NULL,
    reason        VARCHAR(100) NOT NULL,
    note          VARCHAR(255) NULL,
    refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sales_return_items (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    return_id     INT UNSIGNED NOT NULL,
    sale_item_id  INT UNSIGNED NOT NULL,
    medicine_id   INT UNSIGNED NOT NULL,
    batch_id      INT UNSIGNED NOT NULL,
    qty           INT NOT NULL DEFAULT 0,
    rate          DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount        DECIMAL(12,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (return_id) REFERENCES sales_returns(id) ON DELETE CASCADE,
    FOREIGN KEY (sale_item_id) REFERENCES sale_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_returns (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_id   INT UNSIGNED NOT NULL,
    return_no     VARCHAR(30)  NOT NULL,
    return_date   DATE NOT NULL,
    reason        VARCHAR(100) NOT NULL,
    note          VARCHAR(255) NULL,
    credit_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_return_items (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    return_id         INT UNSIGNED NOT NULL,
    purchase_item_id  INT UNSIGNED NOT NULL,
    medicine_id       INT UNSIGNED NOT NULL,
    batch_id          INT UNSIGNED NOT NULL,
    qty               INT NOT NULL DEFAULT 0,
    rate              DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount            DECIMAL(12,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (return_id) REFERENCES purchase_returns(id) ON DELETE CASCADE,
    FOREIGN KEY (purchase_item_id) REFERENCES purchase_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE RESTRICT
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

-- Default role permission matrix (display/config only — see note above).
INSERT INTO role_permissions (role, module, allowed) VALUES
    ('Owner / Admin','Retail POS',1),('Owner / Admin','Wholesale Billing',1),('Owner / Admin','Purchase',1),
    ('Owner / Admin','Inventory',1),('Owner / Admin','Expiry',1),('Owner / Admin','Reports',1),
    ('Owner / Admin','Payments',1),('Owner / Admin','Administration',1),
    ('Pharmacist','Retail POS',1),('Pharmacist','Wholesale Billing',1),('Pharmacist','Purchase',0),
    ('Pharmacist','Inventory',1),('Pharmacist','Expiry',1),('Pharmacist','Reports',1),
    ('Pharmacist','Payments',1),('Pharmacist','Administration',0),
    ('Billing Clerk','Retail POS',1),('Billing Clerk','Wholesale Billing',1),('Billing Clerk','Purchase',0),
    ('Billing Clerk','Inventory',0),('Billing Clerk','Expiry',0),('Billing Clerk','Reports',0),
    ('Billing Clerk','Payments',1),('Billing Clerk','Administration',0),
    ('Purchase Manager','Retail POS',0),('Purchase Manager','Wholesale Billing',0),('Purchase Manager','Purchase',1),
    ('Purchase Manager','Inventory',1),('Purchase Manager','Expiry',1),('Purchase Manager','Reports',1),
    ('Purchase Manager','Payments',0),('Purchase Manager','Administration',0);
