-- 0020_add_penalty_tracking_and_balance.sql
-- Adds penalty due dates, settlement status, issuer tracking, payment link,
-- cached balance on boarder profiles, and payment_allocations table.

ALTER TABLE penalties
    ADD COLUMN due_date DATE NULL COMMENT 'Date by which penalty must be paid' AFTER reason,
    ADD COLUMN status ENUM('unpaid', 'paid') NOT NULL DEFAULT 'unpaid' AFTER due_date,
    ADD COLUMN issued_by INT UNSIGNED NULL COMMENT 'Admin/Staff who issued manual penalty - NULL for auto late fees' AFTER status,
    ADD COLUMN paid_at TIMESTAMP NULL COMMENT 'Timestamp when penalty was settled' AFTER issued_by,
    ADD COLUMN paid_payment_id INT UNSIGNED NULL COMMENT 'FK to payments(id) if settled via payment approval' AFTER paid_at,
    ADD INDEX idx_penalties_boarder_status (boarder_id, status),
    ADD CONSTRAINT fk_penalties_issuer FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_penalties_payment FOREIGN KEY (paid_payment_id) REFERENCES payments(id) ON DELETE SET NULL;

ALTER TABLE boarder_profiles
    ADD COLUMN outstanding_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Cached/synchronized balance derived from canonical payment and penalty records';

CREATE TABLE IF NOT EXISTS payment_allocations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id      INT UNSIGNED NOT NULL,
    boarder_id      INT UNSIGNED NOT NULL,
    allocation_type ENUM('rent', 'penalty') NOT NULL,
    reference_id    VARCHAR(50) NOT NULL COMMENT 'billing_period for rent, or penalty_id for penalty',
    amount          DECIMAL(10,2) NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pa_payment (payment_id),
    INDEX idx_pa_boarder (boarder_id),
    INDEX idx_pa_type_ref (allocation_type, reference_id),
    CONSTRAINT fk_pa_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    CONSTRAINT fk_pa_boarder FOREIGN KEY (boarder_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
