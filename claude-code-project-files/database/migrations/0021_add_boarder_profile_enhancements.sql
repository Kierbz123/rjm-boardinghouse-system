-- 0021_add_boarder_profile_enhancements.sql
-- MariaDB-specific: uses ADD COLUMN IF NOT EXISTS for idempotent re-runs.
-- Adds admin-visible internal notes to boarder_profiles and backfills move_in_date for current residents.

ALTER TABLE boarder_profiles
    ADD COLUMN IF NOT EXISTS notes TEXT NULL
        COMMENT 'Admin-visible internal notes about this boarder'
        AFTER emergency_contact_number;

-- Backfill move_in_date from user creation date for current residents (excluding moved_out)
UPDATE boarder_profiles bp
    JOIN users u ON u.id = bp.user_id
    SET bp.move_in_date = DATE(u.created_at)
    WHERE bp.move_in_date IS NULL
      AND bp.status <> 'moved_out';
