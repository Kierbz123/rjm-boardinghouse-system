-- The amount the admin confirmed on the receipt when approving it. Billing credits
-- this, not the amount the resident typed (security review W1, Oct 2026).
-- NULL on payments approved before this column existed: those keep their claimed amount.
ALTER TABLE payments
    ADD COLUMN IF NOT EXISTS approved_amount DECIMAL(10,2) NULL AFTER claimed_amount;
