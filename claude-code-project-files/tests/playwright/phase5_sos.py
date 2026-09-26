"""
Phase 5 verification — Emergency SOS & Incident Reporting.

    python3 tests/playwright/phase5_sos.py
"""
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

        # Boarder triggers the real SOS button (not a raw API call)
        boarder_page = browser.new_page()
        login(boarder_page, "boarder@rjm.test", "BoarderPass123!")
        boarder_page.click("#sos-button")
        boarder_page.wait_for_selector("text=Alert sent", timeout=5000)
        print("PASS: SOS button click confirms 'Alert sent'")

        # Staff sees it with the correct room
        staff_page = browser.new_page()
        login(staff_page, "staff@rjm.test", "StaffPass123!")
        staff_page.goto(f"{BASE}/staff/dashboard")
        dash_text = staff_page.locator("body").inner_text()
        assert "101" in dash_text, "Expected the boarder's room number (101) on the SOS monitor"
        print("PASS: staff dashboard shows the active alert with room 101")

        # Staff acknowledges it
        staff_page.locator('form[action*="/ack"] button').first.click()
        staff_page.wait_for_load_state("networkidle")
        print("PASS: staff acknowledged the alert")

        # Boarder sees the acknowledgment notification
        boarder_page.goto(f"{BASE}/portal/dashboard")
        boarder_page.click("#notif-bell")
        boarder_page.wait_for_timeout(500)
        dropdown_text = boarder_page.locator("#notif-dropdown").inner_text()
        assert "acknowledged" in dropdown_text.lower(), "Expected an sos_acknowledged notification"
        print("PASS: boarder received the SOS-acknowledged notification")

        # Incident logged independently of any SOS alert
        staff_page.goto(f"{BASE}/staff/incidents")
        staff_page.fill('input[name="type"]', "noise complaint")
        staff_page.fill('textarea[name="description"]', "loud music at 11pm, unrelated to any SOS")
        staff_page.click('button:has-text("Log Incident")')
        staff_page.wait_for_load_state("networkidle")
        assert "noise complaint" in staff_page.content()
        print("PASS: incident logged independently of SOS")

        browser.close()
        print("\nAll Phase 5 checks passed.")


if __name__ == "__main__":
    run()
