-- WP-4: residents are archived, never erased. Record who/when/why, and make the database refuse to
-- delete a resident whose payments have been allocated (it used to cascade through payment_allocations).
ALTER TABLE users
    ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER password_changed_at,
    ADD COLUMN archived_by INT UNSIGNED NULL DEFAULT NULL AFTER archived_at,
    ADD COLUMN archive_reason VARCHAR(255) NULL DEFAULT NULL AFTER archived_by,
    ADD CONSTRAINT fk_users_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL;

ALTER TABLE payment_allocations DROP FOREIGN KEY IF EXISTS fk_pa_boarder;

ALTER TABLE payment_allocations
    ADD CONSTRAINT fk_pa_boarder FOREIGN KEY (boarder_id) REFERENCES users (id) ON DELETE RESTRICT;
