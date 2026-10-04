-- Owner rules, Oct 2026: rent is due on the 5th of each month, and nothing is due
-- during a new resident's first 30 days (see BillingService::dueDate()).
-- Existing rows get their due date the next time their balance is recalculated.
ALTER TABLE rent_charges
    ADD COLUMN IF NOT EXISTS due_date DATE NULL COMMENT 'the 5th of the month, or move-in + 30 days if later' AFTER amount;

-- How the resident paid (GCash, Maya or bank transfer), the reference printed on the
-- receipt, and the admin's decision: when it was made and, for a rejection, why.
ALTER TABLE payments
    ADD COLUMN IF NOT EXISTS payment_method ENUM('gcash', 'maya', 'bank_transfer') NULL AFTER claimed_amount,
    ADD COLUMN IF NOT EXISTS reference_number VARCHAR(50) NULL AFTER payment_method,
    ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMP NULL AFTER verified_by,
    ADD COLUMN IF NOT EXISTS review_note VARCHAR(255) NULL AFTER reviewed_at;

-- Every receipt now simply waits as "pending" until an admin decides. An amount that
-- differs from what is owed is still shown to the admin beside the amount.
UPDATE payments SET verification_status = 'pending' WHERE verification_status = 'flagged';
