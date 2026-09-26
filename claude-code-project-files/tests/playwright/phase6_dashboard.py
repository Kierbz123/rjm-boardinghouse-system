"""
Phase 6 verification — Cross-Module Command Center & Occupancy Trend.
A browser-only test can't run raw SQL like the curl+mysql checks in this
project's dev history did, so this cross-checks the dashboard's numbers
against the same data shown on the underlying pages instead — same idea
(the aggregate must match the source of truth), different mechanism.

    python3 tests/playwright/phase6_dashboard.py
"""
from playwright.sync_api import sync_playwright
import re

BASE = "http://127.0.0.1:8080"


def login(page, email, password):
    page.goto(f"{BASE}/login")
    page.fill('input[name="email"]', email)
    page.fill('input[name="password"]', password)
    page.click('button:has-text("Sign in")')
    page.wait_for_load_state("networkidle")


def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        login(page, "admin@rjm.test", "AdminPass123!")

        page.goto(f"{BASE}/admin/dashboard")
        dashboard_text = page.locator("body").inner_text()
        open_requests_match = re.search(r"Open Requests\s*\n?\s*(\d+)", dashboard_text)
        assert open_requests_match, "Could not find 'Open Requests' figure on dashboard"
        dashboard_open_count = int(open_requests_match.group(1))

        page.goto(f"{BASE}/staff/maintenance")
        # count table rows minus the header row
        row_count = page.locator("table tr").count() - 1
        assert row_count == dashboard_open_count, (
            f"Dashboard says {dashboard_open_count} open requests, "
            f"but the maintenance queue table has {row_count} rows"
        )
        print(f"PASS: dashboard's Open Requests ({dashboard_open_count}) matches the queue table ({row_count})")

        page.goto(f"{BASE}/admin/occupancy")
        assert page.locator("table").count() > 0, "Occupancy trend table did not render"
        print("PASS: occupancy trend page renders a table")

        browser.close()
        print("\nAll Phase 6 checks passed.")


if __name__ == "__main__":
    run()
