-- One rent charge per boarder per month occupied, so unpaid months carry over
-- and a later room-price change never rewrites what past months cost.
CREATE TABLE IF NOT EXISTS rent_charges (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    boarder_id    INT UNSIGNED NOT NULL,
    period        CHAR(7) NOT NULL COMMENT 'YYYY-MM',
    monthly_rate  DECIMAL(10,2) NOT NULL COMMENT 'room price when the month was billed',
    amount        DECIMAL(10,2) NOT NULL COMMENT 'monthly_rate prorated by days occupied',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_rent_charge (boarder_id, period),
    CONSTRAINT fk_rc_boarder FOREIGN KEY (boarder_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Automatic late fees: at most one per boarder, rule and month (manual penalties leave it NULL).
ALTER TABLE penalties ADD COLUMN billing_period CHAR(7) NULL COMMENT 'YYYY-MM, set on automatic late fees' AFTER reason;
ALTER TABLE penalties ADD UNIQUE KEY uniq_late_fee (boarder_id, rule_id, billing_period);

-- Rent accrues from move-in; current residents without one start from account creation (same rule as 0021).
UPDATE boarder_profiles bp
    JOIN users u ON u.id = bp.user_id
    SET bp.move_in_date = DATE(u.created_at)
    WHERE bp.move_in_date IS NULL
      AND bp.status IN ('active', 'on_notice');
