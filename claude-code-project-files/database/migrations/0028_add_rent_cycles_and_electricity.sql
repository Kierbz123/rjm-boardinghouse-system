-- Owner rules, 5 Oct 2026: rent runs in 30-day cycles counted from the day the
-- resident moved in, and each cycle is due 30 days after it starts (see
-- BillingService::dueDate()). A charge is now keyed by the first day of its cycle.
-- The old per-month rows are replaced the next time a balance is recalculated.
ALTER TABLE rent_charges
    MODIFY period CHAR(10) NOT NULL COMMENT 'first day of the 30-day cycle, YYYY-MM-DD';

ALTER TABLE penalties
    MODIFY billing_period CHAR(10) NULL COMMENT 'rent cycle (YYYY-MM-DD) of an automatic late fee, or YYYY-MM on fees from before 0028';

-- Electricity: every room has its own submeter. The admin enters the bill for the room
-- and it is split equally among the residents staying in that room on that day.
CREATE TABLE IF NOT EXISTS electricity_bills (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id     INT UNSIGNED NOT NULL,
    label       VARCHAR(60) NOT NULL COMMENT 'what the bill covers, e.g. September 2026',
    amount      DECIMAL(10,2) NOT NULL COMMENT 'the bill for the whole room',
    created_by  INT UNSIGNED NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_eb_room FOREIGN KEY (room_id) REFERENCES rooms(id),
    CONSTRAINT fk_eb_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS electricity_charges (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bill_id     INT UNSIGNED NOT NULL,
    boarder_id  INT UNSIGNED NOT NULL,
    amount      DECIMAL(10,2) NOT NULL COMMENT 'equal share of the room bill for this resident',
    due_date    DATE NOT NULL COMMENT 'next rent due date of the resident when the bill was entered',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_bill_boarder (bill_id, boarder_id),
    CONSTRAINT fk_ec_bill FOREIGN KEY (bill_id) REFERENCES electricity_bills(id) ON DELETE CASCADE,
    CONSTRAINT fk_ec_boarder FOREIGN KEY (boarder_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE payment_allocations
    MODIFY allocation_type ENUM('rent', 'electricity', 'penalty') NOT NULL;
