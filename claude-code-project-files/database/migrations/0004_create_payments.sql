CREATE TABLE payments (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    boarder_id           INT UNSIGNED NOT NULL,
    billing_period       VARCHAR(20) NOT NULL COMMENT 'e.g. 2026-08',
    expected_amount      DECIMAL(10,2) NOT NULL,
    claimed_amount       DECIMAL(10,2) NOT NULL,
    proof_path           VARCHAR(255) NULL,
    verification_status  ENUM('pending','auto-matched','flagged','admin-approved','rejected') NOT NULL DEFAULT 'pending',
    verified_by          INT UNSIGNED NULL,
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payments_status (verification_status),
    CONSTRAINT fk_payments_boarder  FOREIGN KEY (boarder_id) REFERENCES users(id),
    CONSTRAINT fk_payments_verifier FOREIGN KEY (verified_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
