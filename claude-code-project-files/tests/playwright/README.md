# Playwright verification scripts

All 8 scripts exist now — one per phase, plus the combined runner. Written
against this codebase's actual routes/forms/element IDs, but not yet
executed anywhere (this project was built in a sandbox with no browser
binaries on its network allowlist). Run them for real on your machine:

    pip install playwright
    playwright install chromium

Fresh DB + seed each time (several scripts create data that would collide
on a second run against the same database):

    mysql -u root -e "DROP DATABASE IF EXISTS rjm_boardinghouse; CREATE DATABASE rjm_boardinghouse CHARACTER SET utf8mb4;"
    for f in database/migrations/*.sql; do mysql -u root rjm_boardinghouse < "$f"; done
    php database/seed.php

Then, with the Python service and PHP server both running:

    python3 tests/playwright/phase1_auth.py
    python3 tests/playwright/phase2_bed_mapping.py
    python3 tests/playwright/phase3_payments.py
    python3 tests/playwright/phase4_maintenance_scoring.py
    python3 tests/playwright/phase5_sos.py
    python3 tests/playwright/phase6_dashboard.py
    python3 tests/playwright/phase7_notifications.py
    python3 tests/playwright/phase8_security.py

Or run everything in one pass (against a *freshly reset* DB — see the
docstring in the file for the exact reset command):

    python3 tests/playwright/phase8_full_suite.py

## What's still manual / not covered here
- Phase 3's exact penalty amount (5/day × 3 days = 15.00) and the
  fail-closed behavior when the Python service is down are unit-level
  checks against `PenaltyEngine`/`ScoringClient` directly, not
  browser-driven — see `PHASES.md` Phase 3/4 verification for those.
- No login rate-limiting exists yet, so there's nothing to test there.
