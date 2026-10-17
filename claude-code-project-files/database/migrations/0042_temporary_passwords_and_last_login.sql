-- Staff accounts audit (14 Oct): a password an administrator typed is temporary. It must be replaced
-- at first sign-in (must_change_password, 0031) and stops working after temp_password_expires_at.
-- NULL means no expiry (seeded accounts, and every password the person chose themselves).
-- last_login_at feeds the "Last sign-in" column on the Staff Accounts page.
ALTER TABLE users
    ADD COLUMN temp_password_expires_at TIMESTAMP NULL DEFAULT NULL AFTER password_changed_at,
    ADD COLUMN last_login_at TIMESTAMP NULL DEFAULT NULL AFTER temp_password_expires_at;

-- E-mails are stored lower-case from now on. The column's case-insensitive collation already
-- kept "A@x" and "a@x" from both existing, so this cannot collide. Not undone by the down file.
UPDATE users SET email = LOWER(email) WHERE BINARY email <> BINARY LOWER(email);
