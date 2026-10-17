-- WP-6: an admin or staff member records a payment taken in person (cash, a walk-in GCash, a bank slip).
-- It is stored in the same table as an online payment, already approved, so every total, balance
-- and ledger that reads `payments` counts it without any special case.
--   receipt_number    issued by the system (RJM-000123), unique, printed on the receipt
--   received_on       the day the money changed hands (late fees stop on this day)
--   received_by       who took it; recorded_by_role tells staff entries apart (NULL = online payment)
--   target_penalty_id lets WP-5 aim a payment at one penalty ("Mark paid") instead of the oldest bill
ALTER TABLE payments
    MODIFY COLUMN payment_method ENUM('gcash', 'maya', 'bank_transfer', 'cash', 'other') NULL,
    ADD COLUMN receipt_number VARCHAR(20) NULL AFTER reference_number,
    ADD COLUMN received_on DATE NULL AFTER receipt_number,
    ADD COLUMN received_by INT UNSIGNED NULL AFTER received_on,
    ADD COLUMN recorded_by_role ENUM('admin', 'staff') NULL AFTER received_by,
    ADD COLUMN target_penalty_id INT UNSIGNED NULL AFTER recorded_by_role,
    ADD UNIQUE KEY uq_payments_receipt_number (receipt_number),
    ADD CONSTRAINT fk_payments_received_by FOREIGN KEY (received_by) REFERENCES users (id),
    ADD CONSTRAINT fk_payments_target_penalty FOREIGN KEY (target_penalty_id) REFERENCES penalties (id);
