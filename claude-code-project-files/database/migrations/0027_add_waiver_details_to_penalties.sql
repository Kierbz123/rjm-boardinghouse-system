-- Who waived a penalty and why. A waived penalty is one marked paid with no payment
-- (paid_payment_id NULL); before this, the reason and the admin were not recorded.
ALTER TABLE penalties
    ADD COLUMN IF NOT EXISTS waived_by INT UNSIGNED NULL COMMENT 'admin who waived it (NULL if paid by a payment)' AFTER paid_payment_id,
    ADD COLUMN IF NOT EXISTS waive_reason VARCHAR(160) NULL AFTER waived_by;
