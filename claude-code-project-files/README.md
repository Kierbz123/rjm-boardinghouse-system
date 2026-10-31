# RJM Boardinghouse Rent, Maintenance and Security Management System

A localhost web application for running a boardinghouse: rent and payments, maintenance requests with automatic priority scoring, emergency SOS alerts, incidents, expenses, occupancy trends, bed-level room mapping, late fees and penalties, room inquiries and notifications — for three roles (Admin, Staff, Boarder).

Built as a capstone project. Runs entirely on your own machine: no cloud hosting, no external APIs. The only optional extra is a local [Ollama](https://ollama.com) model for the AI helper buttons; everything works without it.

## Tech stack

- **Backend:** PHP 8.2+ (no framework, no Composer) — `public/index.php` is the single entry point and route table.
- **Database:** MariaDB / MySQL (XAMPP's is fine). Schema lives in `database/migrations/`, applied by `database/migrate.php`.
- **Frontend:** server-rendered PHP views, Tailwind CSS (precompiled to `public/assets/css/app.css`) and GSAP, both served locally.

## Quick start on Windows with XAMPP

1. Install [XAMPP](https://www.apachefriends.org) (includes PHP and MySQL) and start **MySQL** in the XAMPP Control Panel.
2. Create the database once:
   ```
   C:\xampp\mysql\bin\mysql -uroot -e "CREATE DATABASE IF NOT EXISTS rjm_boardinghouse CHARACTER SET utf8mb4;"
   ```
3. Double-click **`start-system.bat`** in the repository root. It checks PHP and MySQL, applies any new migrations, starts the app and opens `http://127.0.0.1:8000`.
4. First time only, create the demo accounts: `C:\xampp\php\php.exe database\seed.php` (run inside `claude-code-project-files`). It prints a random password for each account **once**; note them down (see "Demo accounts").

Keep the "web server" window open while you use the system; close it to stop.

## Manual setup (any OS)

```bash
# 1. database (use a dedicated user if your root account can't log in over TCP)
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS rjm_boardinghouse CHARACTER SET utf8mb4;"

# 2. connection settings — real environment variables win; any that are unset are read
#    from the git-ignored .env file in this folder (see .env.example). With neither, the
#    app logs in as root with no password (XAMPP's default) and warns about it.
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=rjm_boardinghouse DB_USER=root DB_PASS=
# optional: APP_TIMEZONE (default Asia/Manila)
# recommended once: php bin/setup-db-user.php creates a dedicated login and writes it to .env

# 3. schema + demo data
php database/migrate.php
php database/seed.php

# 4. run (index.php doubles as the router so private uploads are served through the app)
php -d upload_max_filesize=20M -d post_max_size=25M -S 127.0.0.1:8000 -t public public/index.php
```

`database/migrate.php` records what it applied in a `schema_migrations` table, so it is safe to run on every start. `php database/migrate.php --status` lists applied and pending migrations; `--rollback[=N]` undoes the last N (only migrations with a `.down.sql` file, which starts at `0031`).

## Demo accounts

`database/seed.php` creates three accounts: `admin@rjm.test`, `staff@rjm.test` and `boarder@rjm.test`. **No password is written anywhere in this project.** Each one gets a random password that the seed script prints once, and each must choose its own at first sign-in (the rest of the system stays closed until then). To reset one later, see DEMO-ACCOUNTS.md.

There is no public sign-up. Admins create boarders (Boarders page) and staff (User Management → Staff tab). User Management (`/admin/staff`) also lists every resident and staff member in one searchable, paged table; its Boarders tab (`/admin/staff?view=boarders`) is the Resident Directory, where residents are filtered by status, edited, archived and restored ("Show archived"). When a resident or staff member forgets their password, the admin sets a new temporary one from the same pages (resident: open them, then "Reset Sign-in Password"; staff: "Reset password" on their row).

## How billing works

- Rent is charged **every 30 days, counted from the move-in date** (to the move-out date), and is **due 30 days after each period starts**: move in on 20 Oct, pay on 19 Nov, 19 Dec, 18 Jan, and so on. Unpaid rent carries over.
- Each 30-day period is charged in full. Only a **last period cut short by a move-out is prorated by days** (10 days stayed = 10/30 of the rent).
- **Electricity:** each room has its own submeter. The admin enters the room's bill (Admin → Rooms & Beds → Electricity Bills) and it is **divided equally among the residents staying in the room**; each share is due on that resident's next rent due date.
- *Run Penalty Check* (Admin → Penalties) adds at most **one late fee per boarder per rule per 30-day period**, starting the day after that period's own due date (so never in a resident's first 30 days); re-running only updates it to today's days late. Electricity shares carry no late fee.
- Late days stop counting on the day a receipt is **submitted** (for the periods it pays, oldest first), so review time is never charged; a receipt sent on or before the due date means no fee, and a rejected receipt makes those periods late again. When approving, the admin sees the late fee and may **waive** it with a required reason, which is recorded and shown to the resident.
- Residents pay by GCash, Maya or bank transfer and upload the receipt photo (JPG/PNG/WEBP, up to 20 MB); **every payment waits for an admin** (it is flagged ⚠ if the amount differs from what is owed). When approving, the admin types the amount the receipt actually shows; that is what is credited. Approved payments are applied oldest-first: rent, then electricity, then penalties. Overpayment becomes credit. Rejecting needs a reason, which the resident sees. An admin can **reverse** an approved payment and the balance is recalculated.
- A finished period keeps the room price it was billed at, even if the room price changes later.

## Walkthrough for a demo

1. **Repair priority** — as the boarder, *Report a Repair*; the live preview scores the text ("gas leak", "sparking outlet" vs "squeaky hinge"). Staff see the queue sorted by priority.
2. **Emergency SOS** — the boarder's SOS button alerts staff with room and bed; acknowledging/resolving notifies the boarder.
3. **Payments** — boarder submits a receipt; admin approves or rejects it on *Payments*; the boarder's balance updates.
4. **Penalties** — admin issues a penalty or runs the late-fee check.
5. **Rooms & beds** — bed-level occupancy; a bed can't be double-booked or exceed room capacity.
6. **Resident lifecycle** — status changes are logged; *Remove* archives residents with payment history (restorable) and deletes only those with none.
7. **Inquiries** — the public landing page form stores inquiries for admins (spam-protected).
8. **Ledger** — *Export Ledger* downloads an Excel workbook: a **Statement** sheet (cash received by method, expenses by category, receivables and net cash flow for the last three months, in the green statement layout) and a **General Ledger** sheet listing every payment, expense and penalty with a running balance. Add `&format=csv` to the link for the plain CSV.

## Tests

```bash
php tests/run.php            # add --with-ai to include the Ollama test
php tests/run.php billing    # only files whose name contains "billing"
```

The runner never touches your real data: it rebuilds a throwaway `<DB_NAME>_test` database from the migrations and seed (with fresh random passwords every run), starts two private servers on ports 8099 and 8098 (the second one is for concurrency tests), runs every `tests/test_*.php` (billing rules, scoring, roles/permissions, security headers, end-to-end HTTP flows, and probe suites that drive each role through the app) and removes any uploads it created. A defect that is known and not yet fixed prints `[KNOWN]` and does not fail the run. Lint everything with `php -l`.

`tests/test_z_feature_audit.php` also checks the wiring of the whole app on every run: each form on each page must post to a route that exists, each link must open, and each state-changing route must have a form on some page. `FEATURE_IMPLEMENTATION_AUDIT.md` (10 Oct 2026) traces every feature from screen to route to table to the test that proves it; the later `../FUNCTIONALITY-AUDIT-2026-10-14.md` lists what is still missing or wrong.

## Going online for a demo (any phone, any network)

The online version is not a separate copy. It is the same system on this computer, with a temporary public address that forwards visitors to it.

1. One time: install the tunnel client (free, no account needed), then open a new window:
   ```
   winget install --id Cloudflare.cloudflared
   ```
2. Double-click **`start-online.bat`** in the repository root. It runs the same checks as `start-system.bat`, opens a Cloudflare quick tunnel and starts the app on port 8010.
3. Wait for "The system is online at:" and an `https://....trycloudflare.com` address. The login page that opens on this computer shows that address as a QR code; scan it, or use "Open mobile preview".
4. Keep the window open. Press Ctrl+C or close it to take the site offline.

For that run only, the launcher sets two things on the server it starts: `APP_BASE_URL` (the public address, used by the login page's QR code and link) and `TRUSTED_PROXIES` (so each visitor is counted by their own address and the session cookie is marked `Secure`). Nothing is stored online; all data stays in the database on this computer.

Limits to know:

- The site is online only while that window is open and this computer has internet. The address is new every time, so share it after starting.
- Anyone who has the address can reach the sign-in page. So the launcher **refuses to start** (and tells you what to fix) while any account still has its seeded password, or while the app is logged in to MariaDB as `root` without a password (fix: `php bin\setup-db-user.php`, once, from `claude-code-project-files`). It also refuses when it cannot check, for example if the database is down.
- PHP's built-in server answers one request at a time. A few people browsing is fine; an AI helper reply makes everyone else wait until it finishes.
- People on the same Wi-Fi share one address for the sign-in limit (20 failed attempts in 15 minutes).
- Five wrong passwords for one email lock that account for 15 minutes, whoever typed them. Someone who has the address and knows a demo email could keep it locked; sign in before sharing the address, or use accounts with private emails.
- A free quick tunnel has no uptime guarantee. Fallback with no internet: `start-system.bat`, as before.

Before a presentation: run it once at home and open the address on a phone using mobile data, set Windows not to sleep while plugged in, and check the venue's Wi-Fi allows the tunnel (otherwise use a phone hotspot for the laptop).

## Deploying (local network / another PC)

This system is designed for a single machine (localhost). To move it:
1. Copy the repository, create the database, and run `php database/migrate.php` (and `seed.php` only for a demo).
2. Make sure every demo account has chosen its own password, and use a database user with a password instead of `root` (`php bin/setup-db-user.php` creates one and writes it to `.env`).
3. Uploaded receipts and repair photos are in `claude-code-project-files/storage/uploads/` (outside the web root) — back that folder up with the database (`mysqldump rjm_boardinghouse`).
4. If you put it behind Apache instead of `php -S`, point the site's DocumentRoot at `claude-code-project-files/public` (never the repository root) and serve it over HTTPS; session cookies then become `Secure` automatically.

## Project layout

```
claude-code-project-files/
  public/            index.php (routes + front controller), assets/
  src/Controllers/   thin HTTP handlers
  src/Models/        database access (PDO, prepared statements only)
  src/Services/      billing, penalties, scoring, notifications, ledger, assistant, AI client
  src/Views/         server-rendered pages (admin/, staff/, portal/, shared/)
  database/          migrations/, migrate.php, seed.php, backups/ (git-ignored)
  storage/uploads/   receipts and repair photos (private, git-ignored)
  tests/             run.php, lib.php + test_*.php
  bin/               go-online.ps1 (temporary public tunnel, started by start-online.bat),
                     pre-online-check.php (its safety check), setup-db-user.php (dedicated DB login)
  logs/              app.log, and online-address.log while the online launcher runs (git-ignored)
```

Full tree and where new files go: `PROJECT_STRUCTURE.md`.

## Documentation

| Read | For |
|---|---|
| `README.md` (this file) | Setup, billing rules, demo, going online, deploying |
| `DEMO-ACCOUNTS.md` | The demo accounts, what each role can do, resetting passwords |
| `FEATURES.md` | What each of the 11 features should do, and which parts are built |
| `ARCHITECTURE.md` | How the system is put together: requests, services, schema |
| `PROJECT_STRUCTURE.md` | Folder layout, and the map of every document with its status |
| `../IMPLEMENTATION-PLAN-2026-10-14.md` | The work in progress (audit fixes) and its status |
| `../FUNCTIONALITY-AUDIT-2026-10-14.md` | The latest audit: what works, what does not |
| `AI-ASSISTANT-PLAN.md`, `CCTV-PEOPLE-COUNTER-PLAN.md`, `IMPROVEMENT-PLAN.md` | Plans (the assistant is built; CCTV is not; the improvement list is partly superseded) |
| `PHASES.md`, `FINAL-REPORT.md`, `QA-VALIDATION-REPORT.md`, `DEBUGGING-REPORT.md`, `FEATURE_IMPLEMENTATION_AUDIT.md`, `../AUDIT-REPORT.md` | History: dated reports on earlier states of the system. The early ones describe a Python service that has since been removed |

`CLAUDE.md`, `../AGENTS.md` and `.claude/` are instructions for AI-assisted development only.

## Known limitations

Current as of the 14 Oct 2026 audit; each is tracked with a finding id in `../FUNCTIONALITY-AUDIT-2026-10-14.md` and a work package in `../IMPLEMENTATION-PLAN-2026-10-14.md`.

- No automatic scheduler: late-fee checks and rent reminders are run from the Admin panel, and pressing the reminder button twice sends two reminders (F-15, WP-9).
- Receipt verification is a human decision; the system only flags amount mismatches (no OCR).
- There is no way to record a cash or walk-in payment; money enters only through an uploaded receipt (F-13, WP-6).
- The landing page's prices, contact details and "Live CCTV" claim are fixed text, not read from the database, and the payment account numbers on the Pay Rent page are fixed in the view; there is no camera feature (F-14, F-21, WP-10).
- No date range check on move-in/move-out dates, and a future move-out date moves the resident out immediately (F-03, F-05, WP-3).
- Removing a resident who has no payments also erases their repair, incident and SOS history (F-04, WP-4).
- No backup or restore tool: back up with `mysqldump` and copy `storage/uploads/` by hand (F-30, WP-17).
- The login page's "open on your phone" QR code only works from a phone when the system is started with `start-online.bat` (see "Going online for a demo"); `start-system.bat` serves on `127.0.0.1` (this machine only) by design.
