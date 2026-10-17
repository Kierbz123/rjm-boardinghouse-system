-- Owner decision (15 Oct 2026): staff accounts carry a phone number. Residents see the on-duty staff's
-- numbers beside the SOS button, as the backup when nobody is watching the dashboard. NULL = none given.
ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL DEFAULT NULL AFTER email;
