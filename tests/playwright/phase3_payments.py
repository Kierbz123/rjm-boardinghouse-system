"""
Phase 3 verification — Proof of Payment Verifier, Penalty Automation, Ledger Export.
Assumes database/seed.php has run and the Python scoring service is up.

    python3 tests/playwright/phase3_payments.py
"""
from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8080"


def login(page, email, password):
    page.goto(f"{BASE}/login")
    page.fill('input[name="email"]', email)
    page.fill('input[name="password"]', password)
    page.click('button:has-text("Sign in")')
    page.wait_for_load_state("networkidle")


def submit_payment(page, billing_period, expected, claimed):
    page.goto(f"{BASE}/portal/payments/new")
    page.fill('input[name="billing_period"]', billing_period)
    page.fill('input[name="expected_amount"]', str(expected))
    page.fill('input[name="claimed_amount"]', str(claimed))
    page.click('button:has-text("Submit")')
    page.wait_for_load_state("networkidle")


def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        login(page, "boarder@rjm.test", "BoarderPass123!")
        submit_payment(page, "2026-09", 3500, 3500)   # should auto-match
        submit_payment(page, "2026-09", 3500, 3000)   # should flag
        page.close()

        page = browser.new_page()
        login(page, "admin@rjm.test", "AdminPass123!")
        page.goto(f"{BASE}/admin/payments")
        table_text = page.locator("table").inner_text()
        assert "auto-matched" in table_text, "Expected an auto-matched payment"
        assert "flagged" in table_text, "Expected a flagged payment"
        print("PASS: matching payment auto-matched, mismatched payment flagged")

        # Ledger export — fetch it directly and check it's a real CSV with a total row
        response = page.request.get(f"{BASE}/admin/ledger/export")
        assert response.status == 200, f"Expected 200, got {response.status}"
        body = response.text()
        assert body.startswith("type,id,related_user,amount,created_at"), "Unexpected CSV header"
        assert "TOTAL," in body, "Expected a TOTAL row in the ledger export"
        print("PASS: ledger export returns a well-formed CSV with a total row")

        browser.close()
        print("\nAll Phase 3 checks passed.")
        print("(Penalty-engine exact-amount and service-down fail-closed behavior")
        print("are unit-level checks, not browser-driven — see PHASES.md Phase 3.)")


if __name__ == "__main__":
    run()
