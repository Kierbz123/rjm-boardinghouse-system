-- Rolling back keeps every payment but forgets HOW the in-person ones were taken: the two new methods
-- become "Not recorded" (NULL), because the old column cannot hold them. Receipt numbers are dropped.
UPDATE payments SET payment_method = NULL WHERE payment_method IN ('cash', 'other');

ALTER TABLE payments
    DROP FOREIGN KEY fk_payments_target_penalty,
    DROP FOREIGN KEY fk_payments_received_by,
    DROP KEY uq_payments_receipt_number,
    DROP COLUMN target_penalty_id,
    DROP COLUMN recorded_by_role,
    DROP COLUMN received_by,
    DROP COLUMN received_on,
    DROP COLUMN receipt_number,
    MODIFY COLUMN payment_method ENUM('gcash', 'maya', 'bank_transfer') NULL;
