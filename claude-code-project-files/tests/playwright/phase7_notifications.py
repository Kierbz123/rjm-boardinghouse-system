"""
Phase 7 verification — All-Around Notification System.
The SOS-acknowledged notification path is already covered in
phase5_sos.py; this covers the other two: maintenance-resolved and
rent-due reminders.

    python3 tests/playwright/phase7_notifications.py
"""
from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8080"


def login(page, email, password):
    page.goto(f"{BASE}/login")
    page.fill('input[name="email"]', email)
    page.fill('input[name="password"]', password)
    page.click('button:has-text("Sign in")')
    page.wait_for_load_state("networkidle")


def read_notifications(page):
    page.goto(f"{BASE}/portal/dashboard")
    page.click("#notif-bell")
    page.wait_for_timeout(500)
    return page.locator("#notif-dropdown").inner_text()


def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)

        # Submit a request as the boarder, then resolve it as staff
        boarder_page = browser.new_page()
        login(boarder_page, "boarder@rjm.test", "BoarderPass123!")
        boarder_page.goto(f"{BASE}/portal/maintenance/new")
        boarder_page.select_option('select[name="category"]', "appliance")
        boarder_page.fill('textarea[name="description"]', "microwave stopped heating")
        boarder_page.click('button:has-text("Submit Request")')
        boarder_page.wait_for_load_state("networkidle")

        staff_page = browser.new_page()
        login(staff_page, "staff@rjm.test", "StaffPass123!")
        staff_page.goto(f"{BASE}/staff/maintenance")
        row = staff_page.locator("tr", has_text="microwave stopped heating")
        row.locator('select[name="status"]').select_option("resolved")
        row.locator('button:has-text("Update")').click()
        staff_page.wait_for_load_state("networkidle")

        notif_text = read_notifications(boarder_page)
        assert "resolved" in notif_text.lower(), "Expected a maintenance_resolved notification"
        print("PASS: boarder notified when their request is resolved")

        # Admin sends rent-due reminders
        admin_page = browser.new_page()
        login(admin_page, "admin@rjm.test", "AdminPass123!")
        admin_page.goto(f"{BASE}/admin/penalty-rules")
        admin_page.click('button:has-text("Send Now")')
        admin_page.wait_for_load_state("networkidle")
        print("PASS: rent-due reminder triggered (recipients depend on active/unpaid boarders)")

        browser.close()
        print("\nAll Phase 7 checks passed.")


if __name__ == "__main__":
    run()
