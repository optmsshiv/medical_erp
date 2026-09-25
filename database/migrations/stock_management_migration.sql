-- Stock Management module: Adjustments + Counter Transfers.
-- Run this on every tenant database before deploying stock-adjustment.php
-- and stock-transfer.php, or those endpoints will fail on missing tables.

-- ---------------------------------------------------------------------
-- Stock Adjustments
-- An adjustment CHANGES total quantity owned (damage, loss, expiry
-- write-off, physical recount correction, found stock). It updates
-- batches.quantity directly and keeps a signed audit trail here.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_adjustments (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  medicine_id    INT NOT NULL,
  batch_id       INT NOT NULL,
  -- Positive = stock added back (e.g. recount found more than recorded).
  -- Negative = stock removed (e.g. damaged, expired, lost/theft).
  qty_change     INT NOT NULL,
  qty_before     INT NOT NULL,
  qty_after      INT NOT NULL,
  reason         ENUM('Damaged', 'Expired', 'Lost / Theft', 'Recount Correction', 'Other') NOT NULL,
  notes          VARCHAR(255) NOT NULL DEFAULT '',
  adjusted_by    INT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
  INDEX idx_adj_medicine (medicine_id),
  INDEX idx_adj_created (created_at)
);

-- ---------------------------------------------------------------------
-- Counters
-- Physical sales counters/racks within THIS store. Seeded with two
-- defaults — rename/add more from the Stock > Counter Transfer tab
-- once that UI exists, or edit this table directly for now.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS counters (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(100) NOT NULL UNIQUE
);
INSERT IGNORE INTO counters (id, name) VALUES (1, 'Main Store'), (2, 'Counter 1');

-- ---------------------------------------------------------------------
-- Counter Transfers
-- Moving a batch's stock from one counter to another does NOT change
-- how much the store owns in total — only where it physically sits —
-- so this is a location log only and does NOT touch batches.quantity.
-- (Batches aren't counter-scoped yet; this is an audit trail of intent
-- until/unless per-counter stock is built.)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_transfers (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  medicine_id      INT NOT NULL,
  batch_id         INT NOT NULL,
  qty              INT NOT NULL,
  from_counter_id  INT NOT NULL,
  to_counter_id    INT NOT NULL,
  notes            VARCHAR(255) NOT NULL DEFAULT '',
  transferred_by   INT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
  FOREIGN KEY (from_counter_id) REFERENCES counters(id),
  FOREIGN KEY (to_counter_id) REFERENCES counters(id),
  INDEX idx_trf_medicine (medicine_id),
  INDEX idx_trf_created (created_at)
);
