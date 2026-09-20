-- ============================================================
-- Run this ONCE against each client database created BEFORE this
-- migration existed (e.g. your test client "optmspharma").
--
-- Your test client has no real inventory yet, so this simply drops
-- and recreates medicines/batches with the fuller schema. If a
-- client ever has real data at migration time, this needs to be a
-- proper ALTER TABLE instead — don't run this version against a
-- database with real medicines/batches you want to keep.
-- ============================================================

DROP TABLE IF EXISTS batches;

DROP TABLE IF EXISTS medicines;

CREATE TABLE medicines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    generic_name VARCHAR(200) NULL,
    composition VARCHAR(255) NULL,
    category_id INT UNSIGNED NULL,
    manufacturer_id INT UNSIGNED NULL,
    brand_ref VARCHAR(150) NULL,
    hsn_code VARCHAR(20) NULL,
    gst_rate DECIMAL(5, 2) NOT NULL DEFAULT 0,
    unit VARCHAR(30) NOT NULL DEFAULT 'Strip',
    pack_size VARCHAR(50) NULL,
    mrp DECIMAL(10, 2) NOT NULL DEFAULT 0,
    purchase_rate DECIMAL(10, 2) NOT NULL DEFAULT 0,
    wholesale_rate DECIMAL(10, 2) NOT NULL DEFAULT 0,
    min_stock INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 0,
    schedule_class VARCHAR(10) NULL,
    rx_required TINYINT(1) NOT NULL DEFAULT 0,
    cold_chain TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT UNSIGNED NOT NULL,
    batch_no VARCHAR(50) NOT NULL,
    purchase_date DATE NULL,
    expiry_date DATE NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    reserved INT NOT NULL DEFAULT 0,
    purchase_rate DECIMAL(10, 2) NOT NULL DEFAULT 0,
    mrp DECIMAL(10, 2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;