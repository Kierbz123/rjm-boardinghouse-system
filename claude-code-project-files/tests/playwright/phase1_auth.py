"""
Phase 1 verification — Auth & RBAC. Run against a live app at localhost:8080
with the seed accounts from database/seed.php already loaded.

    python3 tests/playwright/phase1_auth.py

Requires: pip install playwright && playwright install chromium
"""
from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8080"
ACCOUNTS = {
    "admin": ("admin@rjm.test", "AdminPass123!", "/admin/dashboard"),
    "staff": ("staff@rjm.test", "StaffPass123!", "/staff/dashboard"),
    "boarder": ("boarder@rjm.test", "BoarderPass123!", "/portal/dashboard"),
}


def login(page, email, password):
    page.goto(f"{BASE}/login")
    page.fill('input[name="email"]', email)
    page.fill('input[name="password"]', password)
    page.click('button:has-text("Sign in")')


def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)

        for role, (email, password, expected_path) in ACCOUNTS.items():
            page = browser.new_page()
            login(page, email, password)
            page.wait_for_load_state("networkidle")
            assert expected_path in page.url, f"{role}: expected redirect to {expected_path}, got {page.url}"
            print(f"PASS: {role} login redirects to {expected_path}")
            page.close()

        # Wrong password -> generic error, no info leak
        page = browser.new_page()
        login(page, "admin@rjm.test", "wrong-password")
        page.wait_for_load_state("networkidle")
        assert page.locator("text=Invalid email or password").count() > 0, "Expected generic error message"
        print("PASS: wrong password shows generic error")
        page.close()

        # RBAC: boarder hitting /admin/dashboard directly -> 403, not a redirect to their own dashboard
        page = browser.new_page()
        login(page, "boarder@rjm.test", "BoarderPass123!")
        page.wait_for_load_state("networkidle")
        response = page.goto(f"{BASE}/admin/dashboard")
        assert response.status == 403, f"Expected 403, got {response.status}"
        print("PASS: boarder blocked from /admin/dashboard with 403")
        page.close()

        browser.close()
        print("\nAll Phase 1 checks passed.")


if __name__ == "__main__":
    run()
