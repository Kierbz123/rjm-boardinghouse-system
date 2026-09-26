"""
Phase 8 verification — deliberate attack tests, browser-driven.
These mirror the curl-based attacks already run in this project's dev
history (see PHASES.md Phase 8 notes) but exercise the real browser/cookie
flow instead of raw HTTP.

    python3 tests/playwright/phase8_security.py
"""
from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8080"


def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)

        # SQL injection in the login form must not bypass auth
        page = browser.new_page()
        page.goto(f"{BASE}/login")
        page.fill('input[name="email"]', "admin@rjm.test' OR '1'='1")
        page.fill('input[name="password"]', "x' OR '1'='1")
        page.click('button:has-text("Sign in")')
        page.wait_for_load_state("networkidle")
        assert "/login" in page.url, "SQL injection appears to have bypassed login!"
        print("PASS: SQL injection in login form rejected")
        page.close()

        # XSS in a text field must render escaped, not execute
        page = browser.new_page()
        page.goto(f"{BASE}/login")
        page.fill('input[name="email"]', "boarder@rjm.test")
        page.fill('input[name="password"]', "BoarderPass123!")
        page.click('button:has-text("Sign in")')
        page.wait_for_load_state("networkidle")

        page.goto(f"{BASE}/portal/maintenance/new")
        page.select_option('select[name="category"]', "other")
        page.fill('textarea[name="description"]', "<script>window.__xss_fired = true</script>")
        page.click('button:has-text("Submit Request")')
        page.wait_for_load_state("networkidle")

        xss_fired = page.evaluate("window.__xss_fired === true")
        assert not xss_fired, "XSS payload executed — output is not being escaped!"
        print("PASS: XSS payload did not execute")
        page.close()

        browser.close()
        print("\nPhase 8 security checks passed.")


if __name__ == "__main__":
    run()
