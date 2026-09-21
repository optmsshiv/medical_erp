-- ============================================================
-- Run this ONCE against EACH existing client database
-- (edrppymy_optmspharm, sumati's db, etc.) via phpMyAdmin > SQL tab.
-- ============================================================

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
