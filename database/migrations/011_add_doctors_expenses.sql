-- ============================================================
-- Run this ONCE against EACH existing client database
-- (edrppymy_optmspharm, sumati's db, etc.) via phpMyAdmin > SQL tab.
-- ============================================================

CREATE TABLE IF NOT EXISTS doctors (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    specialty  VARCHAR(100) NULL,
    phone      VARCHAR(20)  NULL,
    reg_no     VARCHAR(50)  NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE sales ADD COLUMN doctor_id INT UNSIGNED NULL AFTER customer_id,
    ADD FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS expense_categories (
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS expenses (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id   INT UNSIGNED NOT NULL,
    amount        DECIMAL(12,2) NOT NULL,
    expense_date  DATE NOT NULL,
    payment_mode  ENUM('Cash','Bank','UPI','Cheque') NOT NULL DEFAULT 'Cash',
    note          VARCHAR(255) NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO expense_categories (name) VALUES
    ('Rent'), ('Electricity'), ('Staff Salaries'), ('Transport & Delivery'),
    ('Equipment & Maintenance'), ('Marketing'), ('Licenses & Compliance'), ('Miscellaneous');
