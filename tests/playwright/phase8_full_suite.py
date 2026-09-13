"""
Full regression pass — runs every phase's Playwright script in order.
Requires a freshly migrated + seeded database (the scripts create data
that would collide on a second run against the same DB), the PHP app at
localhost:8080, and the Python scoring service at localhost:5000.

    mysql -u root -e "DROP DATABASE IF EXISTS rjm_boardinghouse; CREATE DATABASE rjm_boardinghouse CHARACTER SET utf8mb4;"
    for f in database/migrations/*.sql; do mysql -u root rjm_boardinghouse < "$f"; done
    php database/seed.php
    # start the Python service and PHP server, then:
    python3 tests/playwright/phase8_full_suite.py
"""
import importlib
import sys

PHASES = [
    "phase1_auth",
    "phase2_bed_mapping",
    "phase3_payments",
    "phase4_maintenance_scoring",
    "phase5_sos",
    "phase6_dashboard",
    "phase7_notifications",
    "phase8_security",
]


def run():
    failures = []
    for name in PHASES:
        print(f"\n{'=' * 60}\nRunning {name}\n{'=' * 60}")
        try:
            module = importlib.import_module(name)
            module.run()
        except AssertionError as e:
            print(f"FAILED: {name}: {e}")
            failures.append(name)
        except Exception as e:
            print(f"ERROR in {name}: {e}")
            failures.append(name)

    print(f"\n{'=' * 60}")
    if failures:
        print(f"{len(failures)}/{len(PHASES)} phase script(s) failed: {', '.join(failures)}")
        sys.exit(1)
    print(f"All {len(PHASES)} phase scripts passed.")


if __name__ == "__main__":
    run()
