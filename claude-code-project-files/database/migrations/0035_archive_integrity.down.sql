ALTER TABLE payment_allocations DROP FOREIGN KEY IF EXISTS fk_pa_boarder;

ALTER TABLE payment_allocations
    ADD CONSTRAINT fk_pa_boarder FOREIGN KEY (boarder_id) REFERENCES users (id) ON DELETE CASCADE;

ALTER TABLE users DROP FOREIGN KEY IF EXISTS fk_users_archived_by;

ALTER TABLE users
    DROP COLUMN IF EXISTS archive_reason,
    DROP COLUMN IF EXISTS archived_by,
    DROP COLUMN IF EXISTS archived_at;
