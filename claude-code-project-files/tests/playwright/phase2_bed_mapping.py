"""
Phase 2 verification — Detailed Bed Mapping & Status Life System.
Assumes database/seed.php has run: boarder@rjm.test is in Room 101 / Bed A.

    python3 tests/playwright/phase2_bed_mapping.py
"""
import re
from playwright.sync_api import sync_playwright

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

        # Create a second boarder with no bed yet
        page.goto(f"{BASE}/admin/boarders")
        page.fill('input[name="name"]', "Second Boarder")
        page.fill('input[name="email"]', "boarder2@rjm.test")
        page.fill('input[name="password"]', "TempPass123!")
        page.click('button:has-text("Create Boarder")')
        page.wait_for_load_state("networkidle")

        # Read that boarder's ID from the table we just added the ID column to
        row_text = page.locator("tr", has_text="Second Boarder").inner_text()
        boarder2_id = re.search(r"^\s*(\d+)", row_text).group(1)
        print(f"Second boarder created with ID {boarder2_id}")

        # Try to assign them to Bed A, which boarder@rjm.test already occupies
        page.goto(f"{BASE}/admin/rooms")
        # Bed A is occupied, so it won't be in the vacant dropdown — submit
        # via the DOM directly to prove the *server* rejects it, not just the UI.
        page.evaluate(
            """(boarderId) => {
                const form = document.querySelector('form[action="/admin/beds/assign"]');
                const boarderInput = form.querySelector('input[name="boarder_id"]');
                boarderInput.value = boarderId;
                const select = form.querySelector('select[name="bed_id"]');
                const opt = document.createElement('option');
                opt.value = '1'; // Bed A's id from seed.php
                select.appendChild(opt);
                select.value = '1';
            }""",
            boarder2_id,
        )
        page.click('form[action="/admin/beds/assign"] button')
        page.wait_for_load_state("networkidle")
        assert "already occupied" in page.content(), "Expected the occupied-bed error message"
        print("PASS: assigning an already-occupied bed is rejected")

        # Move the original boarder out and confirm the bed frees + status change is visible
        page.goto(f"{BASE}/admin/boarders")
        row = page.locator("tr", has_text="Boarder User")
        row.locator('select[name="status"]').select_option("moved_out")
        row.locator('input[name="reason"]').fill("end of lease")
        row.locator('button:has-text("Update")').click()
        page.wait_for_load_state("networkidle")

        row_after = page.locator("tr", has_text="Boarder User").inner_text()
        assert "moved_out" in row_after, "Expected status to show moved_out"
        print("PASS: boarder status updated to moved_out")

        page.goto(f"{BASE}/admin/rooms")
        assert "vacant" in page.locator("tr", has_text="Bed A").inner_text()
        print("PASS: Bed A is vacant again after move-out")

        browser.close()
        print("\nAll Phase 2 checks passed.")


if __name__ == "__main__":
    run()
