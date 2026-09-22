-- ============================================================
-- Run this ONCE against EACH existing client database
-- (edrppymy_optmspharm, sumati's db, etc.) via phpMyAdmin > SQL tab.
-- Adds wholesale-billing support: customer type/GSTIN, tax split,
-- free quantity tracking.
-- ============================================================

ALTER TABLE customers
    ADD COLUMN type ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER name,
    ADD COLUMN gstin VARCHAR(20) NULL AFTER phone,
    ADD COLUMN dl_no VARCHAR(50) NULL AFTER gstin;

ALTER TABLE sales
    ADD COLUMN channel ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER customer_id,
    ADD COLUMN gstin VARCHAR(20) NULL AFTER sale_date,
    ADD COLUMN dl_no VARCHAR(50) NULL AFTER gstin,
    ADD COLUMN cgst DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER gst_amount,
    ADD COLUMN sgst DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER cgst,
    ADD COLUMN igst DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER sgst,
    MODIFY COLUMN payment_mode ENUM('cash','upi','card','bank','credit','split') NOT NULL DEFAULT 'cash';

ALTER TABLE sale_items
    ADD COLUMN free_qty INT NOT NULL DEFAULT 0 AFTER qty;
