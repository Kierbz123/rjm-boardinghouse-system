# RJM Boardinghouse Rent, Maintenance and Security Management System

A localhost web application for managing a boardinghouse: rent and payments, maintenance requests with automatic priority scoring, emergency SOS alerts, expense tracking, occupancy trends, bed-level room mapping, penalty automation, and notifications — across three roles (Admin, Maintenance Staff, Boarder).

Built as a capstone project. Runs entirely on your own machine — no cloud hosting, no external APIs, no internet dependency once set up.

## Tech Stack

- **Frontend:** HTML, CSS (Tailwind), JavaScript (GSAP for animation)
- **Backend:** PHP 8.3 (main application) + Python (FastAPI microservice for repair-priority scoring and payment verification)
- **Database:** MariaDB

## Prerequisites

Install these on your machine first:
- PHP 8.3+ with the **`php-curl`**, `php-mysql`, `php-mbstring`, and `php-xml` extensions (a bare `apt install php-cli` is missing `php-curl` — the app needs it to talk to the Python service)
- MariaDB (or MySQL 8+)
- Python 3.10+
- (Optional, for automated testing) `pip install playwright && playwright install chromium`

## Setup

**1. Extract this project** into a folder and open a terminal there.

**2. Create the database:**
```bash
mysql -u root -e "CREATE DATABASE rjm_boardinghouse CHARACTER SET utf8mb4;"
```
If your MariaDB's `root` account is set to `unix_socket` auth (common on Debian/Ubuntu), TCP connections with a blank password will fail. Create a dedicated app user instead:
```bash
mysql -u root -e "CREATE USER 'boardinghouse_app'@'127.0.0.1' IDENTIFIED BY 'your_password_here'; GRANT ALL PRIVILEGES ON rjm_boardinghouse.* TO 'boardinghouse_app'@'127.0.0.1'; FLUSH PRIVILEGES;"
```

**3. Apply the migrations, in order:**
```bash
for f in database/migrations/*.sql; do mysql -u root rjm_boardinghouse < "$f"; done
```

**4. Configure your environment:**
```bash
cp .env.example .env
```
Edit `.env` with your DB credentials from step 2.

**5. Seed demo accounts and sample data:**
```bash
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=rjm_boardinghouse DB_USER=boardinghouse_app DB_PASS=your_password_here
php database/seed.php
```

**6. Start the Python scoring service** (in its own terminal):
```bash
cd scoring_service
python3 -m venv venv
source venv/bin/activate        # Windows: venv\Scripts\activate
pip install -r requirements.txt
python3 main.py
```
This runs on `http://127.0.0.1:5000` and must stay running for repair-priority scoring and payment verification to work — but the app is designed to degrade safely if it's down (see "What happens if the Python service isn't running" below), not crash.

**7. Start the PHP app** (in another terminal, from the project root):
```bash
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=rjm_boardinghouse DB_USER=boardinghouse_app DB_PASS=your_password_here SCORING_SERVICE_URL=http://127.0.0.1:5000
php -S localhost:8080 -t public
```

**8. Open `http://localhost:8080`** in your browser.

## Demo Accounts

Created by `database/seed.php`:

| Role | Email | Password |
|---|---|---|
| Admin | `admin@rjm.test` | `AdminPass123!` |
| Staff | `staff@rjm.test` | `StaffPass123!` |
| Boarder | `boarder@rjm.test` | `BoarderPass123!` |

The seeded boarder is already assigned to Room 101 / Bed A. There's no public sign-up — new accounts (staff or boarders) are created by an Admin from the Boarders page, which is the intended model for this kind of system.

## A Walkthrough for Demoing Each Feature

1. **Smart AI Repair Priority System** — log in as the boarder, go to *Report a Repair*, submit something containing a word like "gas leak" or "fire" and something mundane like "squeaky hinge." Log in as staff and check the *Maintenance Queue* — the urgent one should sort to the top, tagged Critical/High.
2. **Emergency SOS** — as the boarder, hit the red SOS button on the dashboard. As staff, the alert appears on the dashboard with the boarder's room number; acknowledging it notifies the boarder back.
3. **Command Center** — the Admin dashboard aggregates pending payments, open requests by priority, active SOS count, and recent expenses in one view.
4. **Expense Logging** / **5. Ledger Export** — log an expense as Admin, then use *Export Ledger (CSV)* on the Payments page to download a combined record of payments, expenses, and penalties.
5. **Occupancy Trend** — Admin → Occupancy shows bed occupancy over time.
6. **Proof of Payment Verifier** — as the boarder, submit a payment with the claimed amount matching vs. not matching the expected rent, and watch the verification status differ (auto-matched vs. flagged) on the Admin Payments page.
7. **Detailed Bed Mapping** — Admin → Rooms & Beds shows occupancy at the individual bed level, and rejects assigning an already-occupied bed.
8. **Penalty Automation** — Admin → Penalty Rules, add a rule, then use *Run Penalty Check* (no cron on localhost, so this is a manual/on-login trigger by design).
9. **Status Life System** — change a boarder's status on the Boarders page; the bed frees automatically on "moved_out," and the change is logged with who/when/why.
10. **Notifications** — the bell icon in the top nav polls for updates; resolving a maintenance request or acknowledging an SOS notifies the relevant boarder.

## What happens if the Python service isn't running

The app is deliberately built to **fail closed**, not crash, if `scoring_service` is down:
- A submitted maintenance request still saves, defaulting to **Medium** priority with a visible "not yet AI-scored" flag for staff.
- A submitted payment stays in **Pending** status for manual admin review — it is never auto-approved just because the checker was unreachable.

## Automated Tests

Real Playwright scripts exist for every phase in `tests/playwright/` — see that folder's `README.md` for how to run them. They haven't been run yet in a real browser as of this build; that's the natural next step once you have the app running locally.

## Project Documentation

This README covers running the app. For anything about *how* and *why* it's built the way it is:
- `PROJECT_STRUCTURE.md` — full annotated folder layout
- `ARCHITECTURE.md` — component/connector diagrams, database schema, sequence diagrams
- `FEATURES.md` — full functional spec per feature
- `PHASES.md` — build history and exactly what has and hasn't been verified
- `UI-LIBRARY-EVALUATION.md` — why Tailwind + GSAP were chosen
- `Design-Decisions-Rationale.md` — methodology write-up, suitable for a report's system-design chapter

`CLAUDE.md` and the `.claude/` folder are project instructions for Claude Code specifically — not needed to just run the app, only relevant if you continue developing it with Claude Code's help.

## Known Limitations

- No self-service account registration (by design — see `FEATURES.md`).
- OCR/text-extraction from payment receipt images is out of scope; verification is amount-matching only.
- No automated task scheduler — penalty checks and rent-due reminders are manually triggered from the Admin panel, since a real cron daemon isn't guaranteed on a localhost dev setup.
