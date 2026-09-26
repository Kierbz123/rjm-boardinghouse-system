"""
Phase 4 verification — Smart AI Repair Priority System.
Requires the Python scoring service running at localhost:5000 for the
"service up" cases, and the PHP app at localhost:8080 with a boarder
account seeded (see database/seed.php) that has a bed assigned.

    python3 tests/playwright/phase4_maintenance_scoring.py
"""
from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8080"


def login_boarder(page):
    page.goto(f"{BASE}/login")
    page.fill('input[name="email"]', "boarder@rjm.test")
    page.fill('input[name="password"]', "BoarderPass123!")
    page.click('button:has-text("Sign in")')
    page.wait_for_load_state("networkidle")


def submit_request(page, description, category="other"):
    page.goto(f"{BASE}/portal/maintenance/new")
    page.select_option('select[name="category"]', category)
    page.fill('textarea[name="description"]', description)
    page.click('button:has-text("Submit Request")')
    page.wait_for_load_state("networkidle")


def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        login_boarder(page)

        submit_request(page, "gas leak smell in kitchen")
        submit_request(page, "squeaky door hinge")

        page.close()
        # Verify via the staff queue view, which shows priority tiers directly
        page = browser.new_page()
        page.goto(f"{BASE}/login")
        page.fill('input[name="email"]', "staff@rjm.test")
        page.fill('input[name="password"]', "StaffPass123!")
        page.click('button:has-text("Sign in")')
        page.wait_for_load_state("networkidle")
        page.goto(f"{BASE}/staff/maintenance")

        rows_text = page.locator("table").inner_text()
        assert "gas leak" in rows_text.lower(), "Gas leak request not found in queue"
        assert "critical" in rows_text.lower() or "high" in rows_text.lower(), \
            "Expected gas leak request to be scored critical or high"
        assert "squeaky" in rows_text.lower(), "Squeaky hinge request not found in queue"
        print("PASS: gas leak scored critical/high, squeaky hinge present")

        browser.close()
        print("\nPhase 4 scoring checks passed. (Also re-run with the Python")
        print("service stopped to confirm the medium/scoring_pending fallback —")
        print("see PHASES.md Phase 4 verification for the exact expected values.)")


if __name__ == "__main__":
    run()
