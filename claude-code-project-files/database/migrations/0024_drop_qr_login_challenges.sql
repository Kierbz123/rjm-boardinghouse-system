-- The "approve login from your phone" flow (0015) was never reachable from the
-- login page and has been removed from the code; its short-lived challenges go too.
DROP TABLE IF EXISTS qr_login_challenges;
