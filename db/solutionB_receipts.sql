-- Solution B (receipts + allocations) — DDL helper
-- NOTE: Align INT/BIGINT (and UNSIGNED) with your current `payment.id` and `receipt.id` types before running.

CREATE TABLE IF NOT EXISTS receipt (
  id INT PRIMARY KEY AUTO_INCREMENT,
  patient_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method ENUM('cash','card','bank','other') NULL,
  note VARCHAR(255) NULL,
  received_at DATETIME NOT NULL DEFAULT NOW(),
  created_at  DATETIME NOT NULL DEFAULT NOW(),
  INDEX idx_receipt_patient (patient_id, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS receipt_allocation (
  id INT PRIMARY KEY AUTO_INCREMENT,
  receipt_id INT NOT NULL,
  charge_id  INT NOT NULL,
  amount_applied DECIMAL(10,2) NOT NULL,
  UNIQUE KEY uq_receipt_charge (receipt_id, charge_id),
  INDEX idx_alloc_receipt (receipt_id),
  INDEX idx_alloc_charge  (charge_id),
  CONSTRAINT fk_ra_receipt FOREIGN KEY (receipt_id) REFERENCES receipt(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ra_charge  FOREIGN KEY (charge_id)  REFERENCES payment(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
