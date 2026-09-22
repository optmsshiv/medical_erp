-- ============================================================
-- Run this ONCE against EACH existing client database
-- (edrppymy_optmspharm, sumati's db, etc.) via phpMyAdmin > SQL tab.
-- ============================================================

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
