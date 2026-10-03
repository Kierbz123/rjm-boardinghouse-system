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
4. First time only, create the demo accounts: `C:\xampp\php\php.exe database\seed.php` (run inside `claude-code-project-files`).

Keep the "web server" window open while you use the system; close it to stop.

## Manual setup (any OS)

```bash
# 1. database (use a dedicated user if your root account can't log in over TCP)
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS rjm_boardinghouse CHARACTER SET utf8mb4;"

# 2. connection settings — the app reads real environment variables (it does NOT load .env)
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=rjm_boardinghouse DB_USER=root DB_PASS=
# optional: APP_TIMEZONE (default Asia/Manila)

# 3. schema + demo data
php database/migrate.php
php database/seed.php

# 4. run (index.php doubles as the router so private uploads are served through the app)
php -S 127.0.0.1:8000 -t public public/index.php
```

`database/migrate.php` records what it applied in a `schema_migrations` table, so it is safe to run on every start.

## Demo accounts

Created by `database/seed.php` (change these before real use):

| Role | Email | Password |
|---|---|---|
| Admin | `admin@rjm.test` | `AdminPass123!` |
| Staff | `staff@rjm.test` | `StaffPass123!` |
| Boarder | `boarder@rjm.test` | `BoarderPass123!` |

There is no public sign-up. Admins create boarders (Boarders page) and staff (Staff Accounts page).

## How billing works

- Rent is charged **every month from the move-in date** (to the move-out date). Unpaid months carry over.
- **Partial months are prorated by days** (moving in on the 11th of a 30-day month = 20/30 of the rent).
- Rent is due on the **5th**. *Run Penalty Check* (Admin → Penalties) adds at most **one late fee per boarder per rule per month**; re-running only updates it to today's days late.
- Boarders submit a receipt photo; **every payment waits for an admin** (it is flagged if the amount differs from what is owed). Approved payments are applied oldest-first: rent months, then penalties. Overpayment becomes credit. An admin can **reverse** an approved payment and the balance is recalculated.
- A month keeps the room price it was billed at, even if the room price changes later.

## Walkthrough for a demo

1. **Repair priority** — as the boarder, *Report a Repair*; the live preview scores the text ("gas leak", "sparking outlet" vs "squeaky hinge"). Staff see the queue sorted by priority.
2. **Emergency SOS** — the boarder's SOS button alerts staff with room and bed; acknowledging/resolving notifies the boarder.
3. **Payments** — boarder submits a receipt; admin approves or rejects it on *Payments*; the boarder's balance updates.
4. **Penalties** — admin issues a penalty or runs the late-fee check.
5. **Rooms & beds** — bed-level occupancy; a bed can't be double-booked or exceed room capacity.
6. **Resident lifecycle** — status changes are logged; *Remove* archives residents with payment history (restorable) and deletes only those with none.
7. **Inquiries** — the public landing page form stores inquiries for admins (spam-protected).
8. **Ledger** — *Export Ledger (CSV)* lists payments received, expenses and penalties with separate totals.

## Tests

```bash
php tests/run.php            # add --with-ai to include the Ollama test
```

The runner never touches your real data: it rebuilds a throwaway `<DB_NAME>_test` database from the migrations and seed, starts a private server on port 8099, runs every `tests/test_*.php` (billing rules, scoring, roles/permissions, security headers, end-to-end HTTP flows) and removes any uploads it created. Lint everything with `php -l`.

## Deploying (local network / another PC)

This system is designed for a single machine (localhost). To move it:
1. Copy the repository, create the database, and run `php database/migrate.php` (and `seed.php` only for a demo).
2. Change the demo passwords, and use a database user with a password instead of `root`.
3. Uploaded receipts and repair photos are in `claude-code-project-files/storage/uploads/` (outside the web root) — back that folder up with the database (`mysqldump rjm_boardinghouse`).
4. If you put it behind Apache instead of `php -S`, point the site's DocumentRoot at `claude-code-project-files/public` (never the repository root) and serve it over HTTPS; session cookies then become `Secure` automatically.

## Project layout

```
claude-code-project-files/
  public/            index.php (routes + front controller), assets/
  src/Controllers/   thin HTTP handlers
  src/Models/        database access (PDO, prepared statements only)
  src/Services/      billing, penalties, scoring, notifications, ledger, AI client
  src/Views/         server-rendered pages (admin/, staff/, portal/, shared/)
  database/          migrations/, migrate.php, seed.php, backups/ (git-ignored)
  storage/uploads/   receipts and repair photos (private, git-ignored)
  tests/             run.php + test_*.php
```

`ARCHITECTURE.md`, `FEATURES.md` and `PROJECT_STRUCTURE.md` describe the original design; `PHASES.md`, `QA-VALIDATION-REPORT.md` and `DEBUGGING-REPORT.md` are the build history. `CLAUDE.md` and `.claude/` are instructions for AI-assisted development only.

## Known limitations

- No automatic scheduler: late-fee checks and rent reminders are run from the Admin panel.
- Receipt verification is a human decision; the system only flags amount mismatches (no OCR).
- The login page's "open on your phone" QR code only works if the server is reachable from the phone's network; `start-system.bat` serves on `127.0.0.1` (this machine only) by design.
