-- WP-1: accounts can be required to choose their own password, and we record when they did.
ALTER TABLE users
    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN password_changed_at TIMESTAMP NULL DEFAULT NULL AFTER must_change_password;

-- The three demo accounts every older install was seeded with had passwords that were
-- published in the repository. We cannot tell here whether the owner already changed them
-- (that would mean writing the old passwords into this file), so they are asked once more.
UPDATE users SET must_change_password = 1 WHERE email IN ('admin@rjm.test', 'staff@rjm.test', 'boarder@rjm.test');
