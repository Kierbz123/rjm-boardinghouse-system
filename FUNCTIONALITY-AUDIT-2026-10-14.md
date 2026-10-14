# RJM Boardinghouse — Functionality Audit & Implementation Plan

**Date:** 14 Oct 2026  **Branch:** `claude/intelligent-archimedes-ngimxc`  **Mode:** audit and planning only — **no source file, migration or live record was changed.**
**Stack audited:** PHP 8.2.12 (no framework) · MariaDB (XAMPP) · server-rendered views · Windows 11.

Every statement below carries one of three evidence labels:

| Label | Meaning |
|---|---|
| **EXECUTED** | I ran it and observed the result (HTTP request, database row, browser, or CLI). |
| **STATIC** | Read in the source but not reproducible here (usually race conditions or code I could not trigger). |
| **NOT TESTED** | Could not be tested in this environment; the reason is stated. |

---

## 0. How the audit was done, and its limits

**What I did**

1. **Read the code.** The route table (86 routes), all 18 controllers, 16 models, 10 services, 8 support classes, both middleware, migrations 0001–0030, and the views for every page.
2. **Ran the project's own test suite.** `php tests/run.php` → **8/8 files passed** (EXECUTED). Passing tests only prove what they test, so I did not stop there.
3. **Built a throwaway copy.** Fresh database `rjm_audit` (all 30 migrations + seed) behind a private server on `127.0.0.1:8097`. Four scripted probe suites drove every role through real HTTP: roughly **270 individual assertions**, plus **96 page × role combinations** and **33 wrong-role POST attempts**.
4. **Looked at the live database read-only** (row counts, users, demo-password check) and **drove the UI in a real browser** as admin: login + 15 admin/staff pages checked for console/network errors, the approve-payment dialog, the assistant widget, and 5 pages at 375 px mobile width. Resident pages were exercised over HTTP (not in the browser).
5. **Cleaned up.** `rjm_audit` dropped, server stopped, test uploads deleted, `git status` identical before and after (84 entries). Unavoidable side effects: lines appended to the git-ignored `logs/app.log`, and the project's own runner re-created `rjm_boardinghouse_test`.

**What I could not test (stated plainly)**

| Area | Why |
|---|---|
| Local AI helpers: "Improve description", "Auto-detect category", "Analyze ticket", "Summarize queue", free-text AI chat | **NOT TESTED** — Ollama is not running on this machine (port 11434 closed). I did verify they fail *gracefully* (HTTP 503 / "AI chat is off" fallback) and that the rule-based assistant answers without it. |
| `start-online.bat` tunnel / QR code on the login page when online | **NOT TESTED** — project rule: never start the tunnel unasked. The offline state ("Phone access is off") was verified. |
| `start-system.bat` itself | **NOT TESTED** — I ran the equivalent `php -S … public/index.php` command it uses instead. |
| Race conditions (two admins approving the same receipt, double-submit) | **NOT TESTED** — PHP's built-in server is single-threaded. Row locks were reviewed in code (STATIC). |
| 30-minute idle timeout | **NOT TESTED** — would need a 30-minute wait or session surgery. Code reviewed (STATIC). |
| Accessibility (WCAG), non-Chromium browsers, load/performance at scale | Out of scope for this pass. |

---

## 1. Executive summary

**The core is in good shape; the gaps are around it.** Authentication, role enforcement, CSRF, uploads, billing arithmetic, payment review and the late-fee engine all behaved correctly under attack-style testing. There was **no console error on the 16 pages I opened in the browser** and **no horizontal overflow on mobile**. The weaknesses are (a) a handful of data-integrity bugs, (b) one credential problem that matters the moment the tunnel is used, (c) several features that exist as a page but are only half a workflow, and (d) some content that looks live but is hard-coded.

### Status counts (52 inventory items, §2)

| Status | Count | Meaning here |
|---|---|---|
| ✅ **Fully functional** | 23 | Works end to end, frontend → backend → database, with no defect found. |
| 🟡 **Partially functional** | 18 | Works, but has a defect, a missing half of the workflow, or a gap against the owner's own spec. |
| 📄 **Read-only / Read-me** | 5 | Shows information or claims a capability but has no function behind it. |
| ❌ **Non-functional / not built** | 2 | Does not exist. |
| ⏳ **Not tested** | 4 | Environment limits (§0). |

### Ten things that matter most

| # | Finding | Evidence |
|---|---|---|
| 1 | **All three demo accounts in the live database still use the passwords published in the repo** — including the admin. The tunnel launcher only *warns*. | EXECUTED |
| 2 | **One mistyped move-in date can corrupt billing**: `1900-01-01` generated **1,544 rent rows and a ₱3,088,000 balance**; `0001-01-01` generated **24,664 rows** and froze the request for **9.9 s**. | EXECUTED |
| 3 | **Setting a *future* move-out date instantly marks the resident "moved out" and frees their bed.** | EXECUTED |
| 4 | **"Delete resident" permanently erases repair, incident and SOS history** (only payments/penalties/electricity protect a resident). | EXECUTED |
| 5 | **"Mark paid" on a penalty is really a waiver with no reason recorded** and no income recorded — while the approval flow demands a reason. | EXECUTED |
| 6 | **There is no way to record a cash / over-the-counter payment.** Money can only enter via a resident-uploaded receipt. | EXECUTED + STATIC |
| 7 | **Residents are shown hard-coded payment accounts** (`0917-823-9912`, BDO `0012-3456-7890` — the latter reads like a placeholder) that no admin can change. | STATIC + UI text |
| 8 | **Admin dashboard shows stale occupancy** (2/5 beds on the dashboard while the Occupancy page shows 3/5). | EXECUTED + screenshot |
| 9 | **A malformed `csrf_token[]=x` returns HTTP 500 on every POST route**, and a huge room capacity also 500s. Passwords over 72 bytes are silently truncated (a wrong password differing after byte 72 logs in). | EXECUTED |
| 10 | **Spec features missing:** time-decay in repair priority, automatic status transitions, expense receipts, "people involved" on incidents, staff-logged expenses, the CCTV page the landing page advertises. | STATIC + EXECUTED |

### What is solid (verified, not assumed)

* **Access control:** 96/96 page × role combinations correct; every sensitive POST refuses the wrong role with 403; anonymous API calls redirect to `/login`; direct URL / path-traversal / dotfile probes return 404 with no leak. (EXECUTED)
* **Sessions:** HttpOnly + SameSite=Strict cookie, id regenerated on login, forged ids replaced, password change signs out other sessions, deactivating staff ends their open session immediately, lockout after 5 failures, no account enumeration. (EXECUTED)
* **Uploads:** PHP-in-`.png`, GIF-as-PNG, SVG and >20 MB all refused; receipts live outside `public/`; another resident (404), staff (404) and anonymous (302) cannot open a receipt; owner and admin can. (EXECUTED)
* **Money rules:** a receipt is always saved *pending*; approving more than claimed is refused; double approval refused; reversing an approved payment removes its allocations and restores the balance; late-fee maths correct (45 days × ₱5 = ₱225; 15 × ₱5 = ₱75); re-running the late-fee check never duplicates a fee. (EXECUTED)
* **Output safety:** stored-XSS payloads in names and incident fields are escaped; CSV formula injection is neutralised (`'=1+1`); the ledger total reconciles with approved payments. (EXECUTED)
* **Isolation:** residents see only their own incidents, repairs, receipts and notifications; notification IDOR attempts refused. (EXECUTED)

---

## 2. System Audit Report — page-by-page inventory

**Status key:** ✅ Fully functional · 🟡 Partially functional · 📄 Read-only / Read-me · ❌ Non-functional / not built · ⏳ Not tested. **Ref** points to the finding in §3.

### Public

| # | Page / feature | Status | What I verified (evidence) | Ref |
|---|---|---|---|---|
| 1 | **Landing page** `/`, `/landing` | 🟡 | Loads, mobile-clean, no console errors. **Prices (₱3,500 / ₱2,200 / ₱1,600), caretaker names, phones, e-mails, Facebook/Messenger links, hours, "Live CCTV · no curfew" are fixed text inside `LandingController`** — changing room 101 to ₱9,999 left the page unchanged. No admin screen edits any of it. The CCTV claim has no feature behind it. Google Map is an external iframe (owner-approved). (EXECUTED, STATIC) | F-21 |
| 2 | **Public inquiry form** `POST /inquire` | ✅ | Saved to `inquiries`; honeypot drops bots silently; 5/hour per IP limit engages; invalid input refused; JSON and form modes both work; admins notified. No CSRF token by design. (EXECUTED) | — |
| 3 | **Login** `/login` | ✅ | Role redirects correct; lockout; no enumeration; CSRF enforced. (EXECUTED) | F-01 |
| 4 | Login page "phone access" / QR block | ⏳ | Offline state ("Phone access is off" + steps) verified. Online QR state not tested (tunnel rule). | — |
| 5 | Error pages 403 / 404 / 500 | ✅ | Real pages for browsers, JSON for `/api/` and `Accept: application/json`. (EXECUTED) | — |

### Admin

| # | Page / feature | Status | What I verified | Ref |
|---|---|---|---|---|
| 6 | **Dashboard** `/admin/dashboard` | 🟡 | KPIs (pending payments, SOS, open repairs, collections, debtors) are live. **Occupancy card is stale** (shows the first snapshot taken today). The green **"System Operational"** pill is unconditional. (EXECUTED, STATIC) | F-12, F-26 |
| 7 | **Inquiry Center** | 📄 | Shows the **latest 10** inquiries to admins (staff see counts only). The table has a `status` column that **nothing reads or writes**: no mark-handled, filter, search, paging, delete, or convert-to-resident. Walk-in logging works. (EXECUTED) | F-20 |
| 8 | **Occupancy trend** | 🟡 | Chart and table render. **Snapshots are only taken when an admin opens the dashboard/this page**, so days with no visit are missing and the "30-day average" is computed from whatever exists (it showed "60% across 1 day"). Green **"Live Telemetry"** pill is unconditional. (EXECUTED, STATIC) | F-12, F-26 |
| 9 | **Boarders — list, add, edit, assign bed** | ✅ | Create with/without bed; duplicate / invalid / short-password / 151-char / XSS names refused; occupied bed refused **and rolled back** (no orphan account); moving beds frees the old one; admin/staff ids refused. | F-07 |
| 10 | **Boarder profile — status, dates, notes** | 🟡 | Status transitions logged (who/when/why); `pending → active` sets move-in = today; 75 days = 3 cycles. **Defects:** no date range check (F-03); a future move-out date instantly evicts (F-05). (EXECUTED) | F-03, F-05 |
| 11 | **Boarder profile — remove / archive / restore** | 🟡 | Residents with payments/penalties/electricity are archived (bed freed, login blocked, restorable) ✅. **Residents without them are hard-deleted along with their repairs, incidents and SOS history.** (EXECUTED) | F-04 |
| 12 | **Boarder profile — reset password** | ✅ | Works for residents and staff; refuses admin ids; ends the target's sessions; clears lockout; enforces 8–72 chars. | — |
| 13 | **Rooms & Beds — rooms / beds CRUD** | ✅ | Duplicate numbers, capacity 0, negative / non-numeric price, label > 20, capacity below bed count, room with beds, rename collisions all handled. Minor defects only. | F-27 |
| 14 | **Electricity bills** | ✅ | Equal split among active residents of the room; shares due with each resident's next rent; duplicate label refused; amount bounds; delete recalculates balances; notification sent. No edit (delete + re-enter). | — |
| 15 | **Staff Accounts** | 🟡 | Create, duplicate e-mail, deactivate (kills open session), reactivate, reset password all work. **No edit name/e-mail, no delete, and no way to create or manage a second admin.** | F-22 |
| 16 | **Payments — review** | ✅ | List with allocation breakdown and duplicate-reference warning; approve dialog (amount, late-fee preview, waive + reason); approve < claimed; reject with reason; reverse an approved payment; resident notified. (Atomicity concern: F-08.) | F-08 |
| 17 | **Payments — record a cash / walk-in payment** | ❌ | **Not built.** No route, no form; `payment_method` is `ENUM('gcash','maya','bank_transfer')`. (EXECUTED/STATIC) | F-13 |
| 18 | **Expenses** | 🟡 | Record + list works with validation. **No edit/void** (404 for `/delete`, `/update`, `/edit`); **no receipt upload** (`receipt_path` always NULL — spec §4); **no date field** (uses created time); category is free text; **admin-only, but spec §4 says staff log expenses**. | F-19 |
| 19 | **Penalties — rules** | 🟡 | Create / edit amount / activate-deactivate work. `condition_type` is free text but **only `late_per_day` triggers automation**; others are labels usable for manual penalties. | F-16 |
| 20 | **Penalties — issue manual penalty** | ✅ | Admin custom amount; staff forced to the rule amount; invalid date / long reason / inactive rule / non-resident refused. | F-09 |
| 21 | **Penalties — run late-fee check** | 🟡 | Correct and idempotent, but **manual only** (spec allows "and/or on admin login"); nothing runs it automatically. | F-16 |
| 22 | **Penalties — "Mark paid"** | 🟡 | Settles the penalty, but it **is a waiver**: no payment, no income, **no reason stored** (`waive_reason = NULL`). | F-06 |
| 23 | **Penalties — "Send rent-due reminders"** | 🟡 | **Pressing it twice sends two notifications to each resident** (1 → 2 verified); it also reminds residents whose rent is not due for weeks. No automatic reminder exists. | F-15 |
| 24 | **Ledger export** `/admin/ledger/export` | 🟡 | CSV with BOM, injection-safe, totals reconcile, staff refused, bad dates fall back safely. **Counts payments by *submission* date while the dashboard counts by *approval* date**, and the "penalties charged (receivable)" total **includes waived/paid penalties**. CSV only (spec: "and/or PDF"). | F-11 |

### Staff

| # | Page / feature | Status | What I verified | Ref |
|---|---|---|---|---|
| 25 | **Staff dashboard** (SOS list, acknowledge/resolve, issue penalty) | ✅ | Polls `/api/sos/active`; ack and resolve change status and notify the resident. No on-screen confirmation after ack/resolve; resolver and outcome not recorded (F-23). | F-23 |
| 26 | **Maintenance queue** | 🟡 | Sorted most-urgent first; status changes notify the resident; invalid status refused. **No time-decay** (spec §1), **no assignment or staff notes** (`assigned_staff_id` is never used), and a resolved repair can be **silently re-opened**. | F-18 |
| 27 | **Maintenance history** | ✅ | Paged (50/page); huge / negative page numbers handled. | — |
| 28 | **Incidents — log and resolve** | 🟡 | Files, scopes (residents see only their own), resolves with required notes, notifies the reporter, escapes XSS. **No "people involved"** (spec §2). The "Registry Engine: Active" KPI card is static text. | F-23, F-26 |
| 29 | **Incident history** | ✅ | Paged, scoped per role. | — |

### Resident

| # | Page / feature | Status | What I verified | Ref |
|---|---|---|---|---|
| 30 | **Portal dashboard** | ✅ | Balance breakdown (rent / electricity / penalties), payments, repairs, penalty table. | — |
| 31 | **SOS button** | ✅ | Creates an alert with room/bed; repeat presses within 2 minutes reuse it (no spam); staff + admin notified; ack and resolve notify the resident. | F-23 |
| 32 | **Report a repair** | 🟡 | Validation, photo/video upload, room taken from profile, keyword scoring, live preview, staff queue all work. **Labelled "AI"/"AI model" but it is a keyword table** (no AI). Resident **cannot cancel, edit, comment or see staff notes**. AI helper buttons not tested. | F-18 |
| 33 | **Pay rent** | 🟡 | Receipt upload, validation, one-open-receipt rule, amount from server, pending-until-reviewed all work. **Payment accounts shown are hard-coded in the view.** | F-14 |
| 34 | **Resident incidents** | ✅ | See #28. | — |

### Shared

| # | Page / feature | Status | What I verified | Ref |
|---|---|---|---|---|
| 35 | **Profile & password** | ✅ | Update contact info, duplicate e-mail refused, password rules, other sessions signed out. Password > 72 bytes accepted but truncated (F-07). | F-07 |
| 36 | **Notifications page + bell** | ✅ | Read / unread / pin / delete / read-all; IDOR refused; polling every 15 s. No paging or retention (F-28). | F-28 |
| 37 | **Assistant widget (rule-based)** | ✅ | Answers balance / who-owes / navigation questions from PHP rules with Ollama off; role-scoped; CSRF and 10/min limit enforced. | — |
| 38 | **AI chat + helper buttons (Ollama)** | ⏳ | See §0. Graceful when offline (EXECUTED). | — |
| 39 | **Logout** | ✅ | POST + CSRF only; `GET /logout` is 404. | — |
| 40 | **Private upload serving** `/uploads/...` | ✅ | See "What is solid". | — |

### Infrastructure and documentation

| # | Item | Status | Finding | Ref |
|---|---|---|---|---|
| 41 | Migrations + seed | ✅ | Fresh database built cleanly from all 30 migrations. Seed creates the three demo accounts with published passwords (F-01). | F-01 |
| 42 | Project test suite | ✅ | 8/8 files pass. | — |
| 43 | DB configuration / `.env` | 📄 | `.env` is **not read by the app** (documented). The app connects as **`root` with an empty password** unless real environment variables are set; the live server accepted exactly that. | F-02 |
| 44 | `start-system.bat` | ⏳ | Not executed; equivalent command verified. | — |
| 45 | `start-online.bat` + tunnel | ⏳ | Not started (rule). Launcher warns, does not block, on unchanged demo passwords. | F-01 |
| 46 | **Backup / restore** | ❌ | No script. README tells the owner to run `mysqldump` and copy `storage/uploads/` by hand. | F-30 |
| 47 | **CCTV / people counter** | 📄 | Planned in `CCTV-PEOPLE-COUNTER-PLAN.md` ("pending owner decisions"); not built; **landing page advertises "Live CCTV"**. | F-21 |
| 48 | Decorative status badges | 📄 | "System Operational", "Live Telemetry", "Audit Active" pills and the "Registry Engine: Active" card are always-green static text. | F-26 |
| 49 | Unused schema | 📄 | `maintenance_requests.assigned_staff_id`, `maintenance_requests.scoring_pending` (always 0), `expenses.receipt_path` (always NULL), `inquiries.status`; payment statuses `flagged` / `auto-matched` exist only for pre-2026-10 rows. | F-29 |
| 50 | Logging / data retention | 🟡 | `logs/app.log` grows without rotation (~136 KB so far); notifications never pruned; `login_attempts` pruned only on a successful login. | F-30 |
| 51 | Admin navigation | 🟡 | Sidebar has **no entry** for Maintenance Queue, Incidents or the SOS staff dashboard; they are reachable only through the dashboard's "View details" cards or direct URL. | F-25 |
| 52 | Live database consistency | ✅ | Live DB (read-only look): 3 residents, 7 beds, no double-booked beds. One resident (#5) is `active` in Room 1 with **no bed** — the system allows it but nothing validates it (F-31). | F-31 |

---

## 3. Non-Functional Feature Register

Priority: **P1** security / data integrity · **P2** core functionality · **P3** incomplete features · **P4** minor / polish. IDs are referenced in §2, §4 and §5.

| ID | Pri | Area | Status | Problem in one line | Evidence | Plan |
|---|---|---|---|---|---|---|
| **F-01** | P1 | Login, tunnel | 🟡 | Seeded admin/staff/boarder passwords are public and **unchanged in the live DB**; `start-online.bat` only warns. | EXECUTED | WP-1 |
| **F-02** | P1 | DB config | 📄 | App defaults to DB `root` / empty password; `.env` is never loaded. | EXECUTED | WP-1 |
| **F-03** | P1 | Boarder dates / billing | 🟡 | No date sanity range: `1900-01-01` → 1,544 charges / ₱3,088,000; `0001-01-01` → 24,664 rows, 9.9 s. | EXECUTED | WP-3 |
| **F-04** | P1 | Remove resident | 🟡 | Hard delete erases repairs, incidents and SOS history. | EXECUTED | WP-4 |
| **F-05** | P1 | Move-out | 🟡 | Future move-out date immediately sets `moved_out` and frees the bed. | EXECUTED | WP-3 |
| **F-06** | P1 | Penalty "Mark paid" | 🟡 | Waiver with no reason, no payment record; mislabelled. | EXECUTED | WP-5 |
| **F-07** | P1 | Passwords | 🟡 | >72-byte passwords accepted then truncated (create resident, create staff, profile change). The admin reset service already enforces 72. | EXECUTED | WP-2 |
| **F-08** | P1 | Payment approval | 🟡 | Late fees and waivers are written in separate transactions *before* the approval commits; a failed/raced approval leaves them behind. Several `GET` pages also write (balance recalculation, snapshot). | STATIC | WP-8 |
| **F-09** | P1 | Error handling | 🟡 | Raw exception text is shown to users in `PenaltyController::issuePenalty` (**including staff**), `RoomController::createBed`, `BoarderController::updateDates` / `delete` / status change. | STATIC | WP-2 |
| **F-10** | P1 | Input hardening | 🟡 | `csrf_token[]=x` → HTTP 500 on **every** POST route and JSON endpoint; capacity `99999999999` → 500. | EXECUTED | WP-2 |
| **F-11** | P1 | Ledger vs dashboard | 🟡 | Different date basis (submission vs approval); receivable total counts waived penalties; description says "rent/penalty" for electricity too. | EXECUTED + STATIC | WP-7 |
| **F-12** | P2 | Occupancy | 🟡 | Dashboard shows first snapshot of the day (2/5 vs real 3/5); trend has gaps on days nobody opens a page. | EXECUTED | WP-7 |
| **F-13** | P2 | Payments | ❌ | No cash / over-the-counter / walk-in payment entry. | EXECUTED + STATIC | WP-6 |
| **F-14** | P2 | Pay Rent page | 📄 | Payment account numbers hard-coded in the view; look like placeholders. | STATIC | WP-10 |
| **F-15** | P2 | Rent reminders | 🟡 | Duplicates on each click; not date-aware; no automatic reminders. | EXECUTED | WP-9 |
| **F-16** | P2 | Late-fee / rules | 🟡 | Late-fee check is manual only; non-`late_per_day` rule types are labels only. | STATIC | WP-9 |
| **F-17** | P2 | Status Life System | 🟡 | No automatic transitions (pending→active on move-in, move-out reached, on-notice) — spec §10. | STATIC | WP-3, WP-9 |
| **F-18** | P3 | Repairs | 🟡 | No time-decay (spec §1); no assignment/notes/cancel; silent reopen; "AI" wording for a keyword table. | STATIC + EXECUTED | WP-11 |
| **F-19** | P3 | Expenses | 🟡 | No edit/void, receipt upload, date field; free-text category; admin-only vs spec "staff log". | EXECUTED | WP-12 |
| **F-20** | P3 | Inquiry Center | 📄 | Latest-10 read-only list; `status` column unused. | EXECUTED | WP-13 |
| **F-21** | P3 | Landing content | 📄 | Prices/contacts/claims hard-coded; "Live CCTV" has no feature. | EXECUTED + STATIC | WP-10, WP-19 |
| **F-22** | P3 | Account management | 🟡 | No staff edit/delete; no admin creation/management. | STATIC | WP-14 |
| **F-23** | P3 | SOS / incidents | 🟡 | No resolver/outcome/false-alarm, no SOS history page, no on-screen confirmation; incidents lack "people involved". | EXECUTED | WP-15 |
| **F-25** | P4 | Admin navigation | 🟡 | No sidebar link for Maintenance Queue / Incidents / SOS dashboard. | EXECUTED | WP-16 |
| **F-26** | P4 | Status badges | 📄 | Four always-green decorative badges/cards. | STATIC | WP-16 |
| **F-27** | P4 | Rooms | 🟡 | Updating a non-existent room says "Room X updated"; unbounded capacity. | EXECUTED | WP-2 |
| **F-28** | P4 | Lists | 🟡 | No paging/server search on payments, boarders, notifications, inquiries. | STATIC | WP-18 |
| **F-29** | P4 | Schema | 📄 | Unused columns / legacy statuses. | STATIC | WP-11, WP-13 |
| **F-30** | P3 | Operations | ❌ | No backup/restore tool; logs and notifications never pruned. | STATIC | WP-17 |
| **F-31** | P4 | Data | 🟡 | An `active` resident may have a room but no bed (live resident #5). Not validated. | EXECUTED (live read) | WP-3 |

*(F-24 intentionally unused — merged into F-18.)*

---

## 4. Implementation Plan

Work is grouped by root cause so each fix lands once. Every package lists: **where**, **current behaviour**, **what is missing**, **changes**, **tasks**, **tests**, and **dependencies / risks / security**. Test IDs (`T-xx`) are in §5.

### WP-1 · Credential hygiene  *(F-01, F-02 — P1)*

* **Where:** `database/seed.php:14-16`, `database/check-demo-passwords.php`, `bin/go-online.ps1:54-65`, `src/Database.php:18-22`, `.env`.
* **Current:** demo passwords are public; launcher warns and continues; DB defaults to `root`/blank; `.env` unused.
* **Missing:** a forced password change, a hard stop before exposure, real configuration loading.
* **Changes:**
  1. **DB:** add a 10-line `.env` reader in `Database.php` (only fills variables not already set). Create the `boardinghouse_app` user per `.env.example`; remove the `root` fallback (fail with a clear message instead).
  2. **Seed:** generate random passwords for the three accounts at seed time and print them once, **or** add `users.must_change_password` (migration `0031`) set to 1 for seeded accounts; `AuthMiddleware::require()` redirects to `/profile` until changed.
  3. **Launcher:** change the warning to a **refusal** (non-zero exit, tunnel not started) while `check-demo-passwords.php` reports any account.
* **Tasks:** write migration → middleware gate → reader → launcher change → update README / `DEMO-ACCOUNTS.md` → run `check-demo-passwords.php` after changing live passwords.
* **Tests:** T-01 … T-03.
* **Risks / security:** the live admin currently signs in with a published password — **change it first, before any code work**. Forcing a change must not lock the owner out: keep a documented CLI reset path. Do not log passwords.

### WP-2 · Input hardening & error hygiene  *(F-07, F-09, F-10, F-27 — P1)*

* **Where:** `src/Support/Csrf.php:20`; `RoomController.php:96-101`, `:120-156`; password handling in `BoarderController.php:55`, `StaffController.php:26`, `ProfileController.php:183`; the five `getMessage()` echoes.
* **Current / missing:** `Csrf::verify(?string)` throws a `TypeError` for arrays; capacity has no upper bound; `User::create/updatePassword` accept any length; exception text is flashed to users; room update never checks the id exists.
* **Changes (root-cause, one place each):**
  1. `Csrf::verify(mixed $token)` → `is_string($token) && …`. Fixes all ~40 POST routes and the JSON endpoints at once.
  2. Put the 8–72-byte rule **inside** `User::create()` / `User::updatePassword()` (throw a user-safe `RuntimeException`) so every caller inherits it; delete the duplicated checks.
  3. Capacity `1–50` (and `base_price` already bounded); `RoomController::update` returns "Room not found" when `Room::find()` is null.
  4. Use the existing `BoarderController::messageFor()` pattern (log the real error, show a generic message) in `PenaltyController::issuePenalty`, `RoomController::createBed`, `BoarderController::updateDates`, `::delete`, and the status-change catch. Move the helper to a shared `App\Support` class so all controllers use it.
* **Tests:** T-04 … T-07.
* **Risks:** the generic message must remain actionable for expected cases (keep `RuntimeException` text that we wrote ourselves).

### WP-3 · Billing date safety & move-out semantics  *(F-03, F-05, F-17 part, F-31 — P1/P2)*

* **Where:** `BoarderController.php:351-392`, `BoarderProfile.php:84-202`, `BillingService.php:227-268`.
* **Current:** any `YYYY-MM-DD` accepted; a move-out date of any value flips status to `moved_out` immediately.
* **Missing:** range validation, a notice period, defensive loop cap.
* **Changes:**
  1. Validate move-in within `[today − 3 years, today + 1 year]` and move-out within `[move-in, move-in + 5 years]` (shared helper beside `isValidCalendarDate`).
  2. **Defence in depth:** cap `syncRentCharges` at e.g. 120 cycles and throw a clear error beyond it.
  3. **Move-out semantics:** a move-out date `> today` sets status `on_notice` and keeps the bed until the date; a date `≤ today` behaves as now. A daily "due move-outs" step (see WP-9) moves them to `moved_out` and frees the bed on the date.
  4. Optional: warn (not block) when activating a resident with no bed (F-31).
* **Tasks:** helper → controller validation → loop cap → `updateDatesAndStatus` branch → tests → WP-9 hook.
* **Tests:** T-08 … T-11. **Risks:** existing rows with extreme dates need a one-off check (`SELECT … WHERE move_in_date < '2020-01-01'`); billing recalculation is destructive on rent rows, so test on a copy.

### WP-4 · Resident record integrity  *(F-04 — P1)*

* **Where:** `BoarderProfile.php:35-43` (`hasFinancialHistory`), `:233-273` (`delete`), `BoarderController::delete`.
* **Current:** only payments / penalties / electricity block deletion; repairs, incidents, SOS and the status log are deleted with the user.
* **Changes:** rename to `hasAnyHistory()` and include `maintenance_requests`, `incidents`, `sos_alerts`, and `boarder_status_log` rows beyond the initial one; keep hard delete **only** for accounts with none. Migration `0032`: change the child-table FKs to `ON DELETE RESTRICT` so the database refuses accidental erasure too. Update the confirm-dialog text.
* **Tests:** T-12. **Risks:** a legitimate "added by mistake" resident who then filed one repair now becomes archive-only — acceptable and consistent with the stated owner decision ("records kept").

### WP-5 · Waivers and "Mark paid"  *(F-06 — P1)*

* **Where:** `PenaltyController::markPaid` (`:169-200`), `Penalty::markPaid` (`:85-145`), views `admin/penalty_rules.php:489`, `admin/boarder_profile.php:909`.
* **Current:** one click, no reason, label implies money was received.
* **Changes:** relabel to **"Waive"**; require a 3–160-character reason (same rule as approval); store `waived_by` / `waive_reason` / `paid_at`; show waived penalties distinctly in lists and the ledger; notification text says "waived by administrator: reason". Cash settlement belongs to WP-6, not here.
* **Tests:** T-13. **Dependencies:** WP-6 (so the admin has a proper path for "they paid cash"). **Risks:** existing waived rows have NULL reason — display "no reason recorded (legacy)".

### WP-6 · Manual / cash payment entry  *(F-13 — P2)*

* **Where (new):** `POST /admin/boarders/{id}/payments` (or on `/admin/payments`), `PaymentController::recordManual()`, migration `0033`.
* **Current:** impossible.
* **Changes:** migration adds `cash` (and optionally `other`) to `payments.payment_method`; admin form: resident, amount, method, received-on date, note, optional photo. Creates the row as `admin-approved` with `verified_by`, `reviewed_at`, `approved_amount`, then runs the **same** `BillingService::calculateBalance` allocation. Notifies the resident. Appears in the ledger as "Payment received (cash)". Duplicate-submission guard.
* **Tests:** T-14. **Dependencies:** WP-8 (use the single-transaction approval path). **Security:** admin-only, CSRF, amount bounded like receipts, audit trail (`verified_by`).

### WP-7 · Report consistency  *(F-11, F-12 — P1/P2)*

* **Where:** `DashboardController.php:31-38`, `OccupancySnapshot.php`, `LedgerBuilder.php:17,24-28,55`, `BillingService::collectionsSummary:205`.
* **Changes:**
  1. Dashboard reads **live** `Bed::counts()`; the snapshot is only for the trend. Always `takeToday()` (upsert) rather than "only if none today".
  2. Take a snapshot from the daily housekeeping step (WP-9) so the trend has one point per day even when nobody opens a page; backfill is impossible, so show gaps honestly ("no data") instead of averaging them.
  3. Ledger: select payments by `COALESCE(reviewed_at, created_at)` (same basis as the dashboard); exclude waived penalties from "receivable" and list them as a separate line; describe electricity allocations correctly.
* **Tests:** T-15, T-16. **Risks:** changing the date basis changes historical exports — note it in the export footer.

### WP-8 · Atomic payment approval & read-only GETs  *(F-08 — P1)*

* **Where:** `PaymentReviewService::approve` (`:55-91`), `PaymentController::index` (`:130-158`), `DashboardController::admin`.
* **Changes:** open **one** transaction in `approve()`: `SELECT … FOR UPDATE` the payment, apply late fees, apply waivers, flip status, commit — or roll everything back. Give `BillingService::calculateBalance()` a `readOnly` mode (compute without `INSERT/DELETE/UPDATE`) and use it from `lateFeePreview`, dashboard and list pages so a `GET` never writes.
* **Tests:** T-17 (needs a multi-process harness — see §5 note). **Risks:** nested-transaction handling already exists (`inTransaction()` checks); re-run the whole billing test file.

### WP-9 · Daily housekeeping, reminders and automatic transitions  *(F-15, F-16, F-17 — P2)*

* **Idea:** localhost has no cron, so run a **once-per-day guarded job** from the first authenticated admin/staff request of the day (guard row in a new `system_jobs(job, last_run_date)` table, migration `0034`).
* **Job steps:** (1) due move-outs → `moved_out` + free bed; (2) `pending → active` when the move-in date is reached (if the owner wants it — **confirm**); (3) occupancy snapshot; (4) late-fee check; (5) rent-due reminders **3 days before** and **on** the due date and weekly while overdue.
* **Reminder dedupe:** use `notifications.entity_type/entity_id` = `rent:{boarder}:{period}:{stage}`; the manual button then reports "N sent, M already sent".
* **Tests:** T-18 … T-20. **Risks:** the job runs inside a web request on a single-threaded server — keep it under ~1 s; wrap in try/catch so a failure never blocks the page; log results.

### WP-10 · House settings instead of hard-coded content  *(F-14, F-21 — P2/P3)*

* **Where (new):** table `settings(key, value)` (migration `0035`), admin page `/admin/settings`, read by `portal/payment_new.php`, `LandingController`.
* **Changes:** payment channels (label, account name, number), contact block, social links, office hours, and landing room-tier prices. Prices default to *derived from the rooms table* (lowest `base_price` per type) with an optional override. Validation (numbers, URLs), escaping, admin-only.
* **Tests:** T-21, T-22. **Owner decision:** supply the **real** GCash/Maya/bank details and confirm the e-mail/Facebook addresses; until then, hide the block rather than show placeholders.

### WP-11 · Repair workflow completeness  *(F-18, F-29 part — P3)*

* **Changes:** (1) effective priority = `score + min(waitingDays × k, cap)`, implemented in the `queueSorted()` ORDER BY (spec §1); (2) staff **assign** (uses the dormant `assigned_staff_id`) and **note** on a repair — new `maintenance_notes` table, shown to the resident; (3) resident can **cancel** while `open`; (4) re-opening a resolved repair requires a reason and notifies the resident; (5) rename "AI" wording to "Smart priority scoring (rule-based)" unless Ollama is actually involved; drop or use `scoring_pending`.
* **Tests:** T-23 … T-26. **Dependencies:** none. **Risk:** keep the displayed tier stable for existing rows (store base score, compute effective score on read).

### WP-12 · Expense completeness  *(F-19 — P3)*

* **Changes:** `expense_date` column; category `<select>` (repairs / utilities / supplies / other) with free "other"; **receipt upload** through `Uploads` (new `expenses` sub-folder, admin-only `mayView`); **void** (not delete): `voided_at/voided_by/void_reason`, excluded from totals and shown struck-through; edit within the same day. **Owner decision:** should staff record expenses (spec) or admin only (current)?
* **Tests:** T-27, T-28.

### WP-13 · Inquiry workflow  *(F-20 — P3)*

* **Changes:** use the existing `inquiries.status` (`new → contacted → converted | closed`); paged list with filter/search; status buttons (admin); "Convert to resident" pre-fills the Add Resident form; keep staff blind to contact details.
* **Tests:** T-29, T-30.

### WP-14 · Account management  *(F-22 — P3)*

* **Changes:** edit staff name/e-mail; allow creating another admin with confirmation; refuse to deactivate/demote the **last active admin**; delete a staff account only if it has no records (else deactivate).
* **Tests:** T-31. **Security:** admin-only, CSRF, audit log line for each change.

### WP-15 · SOS & incident completeness  *(F-23 — P3)*

* **Changes:** `sos_alerts.resolved_by`, `resolution_note`, `is_false_alarm`; SOS history page (staff/admin); toast after acknowledge/resolve; incidents get `people_involved` and optional `room_id`; admin sidebar links (see WP-16).
* **Tests:** T-32, T-33.

### WP-16 · UI honesty & navigation  *(F-25, F-26 — P4)*

* Make the three status pills and the "Registry Engine" card **real** (database reachable / last snapshot time / last audit event) or remove them; add Maintenance Queue, Incidents and SOS Monitor to the admin sidebar via `NavRegistry` (one registry, per its own design); fix the ₱ KPI value touching the card edge at 375 px.
* **Tests:** T-34.

### WP-17 · Operations  *(F-30 — P3)*

* `bin/backup.ps1`: `mysqldump` + zip of `storage/uploads/` into a dated, git-ignored folder, keep the last 14; `bin/restore.ps1` with a confirmation prompt; log rotation (rename at 5 MB); prune read notifications older than 180 days and `login_attempts` older than 30 days from the daily job.
* **Tests:** T-35. **Risk:** backups contain personal data — keep them out of the repo and the web root (already git-ignored).

### WP-18 · Lists at scale  *(F-28 — P4)*

* Server-side paging (50/page) and search on payments, boarders, notifications and inquiries (the history pages already do this — reuse that pattern). **Tests:** T-36.

### WP-19 · CCTV / people counter  *(F-21 — owner decision)*

* **Decision needed:** build it per `CCTV-PEOPLE-COUNTER-PLAN.md`, or **remove "Live CCTV" from the landing page** until it exists. Do not advertise a capability that is not in the system.

### WP-20 · Test what could not be tested

* With Ollama running: run `php tests/run.php --with-ai`, then exercise the five AI endpoints by hand. With the owner present: dry-run `start-online.bat` after WP-1 (confirm it now refuses with demo passwords, and shows the QR once passwords are changed). Run a two-process race test for WP-8. Wait out (or shorten via a test env var) the 30-minute idle timeout.

---

## 5. Testing checklist

Each row: **steps → expected result**. "New test" rows should become cases in the existing `tests/` suite so they run with `php tests/run.php`.

| ID | For | Steps | Expected |
|---|---|---|---|
| T-01 | WP-1 | Run `php database/check-demo-passwords.php` after seeding | Seed prints random passwords (or flags must-change); published passwords no longer log in. |
| T-02 | WP-1 | Sign in with a seeded account | Redirected to `/profile` until the password is changed; other pages blocked. |
| T-03 | WP-1 | Run the tunnel launcher with a demo password unchanged | Refuses to start, names the account, exit code ≠ 0. |
| T-04 | WP-2 | `POST /login` with `csrf_token[]=x`; repeat on 5 other POST routes and a JSON endpoint | Normal "session expired" / 403 — **never 500**. |
| T-05 | WP-2 | Create resident, create staff, change own password with a 73+ byte password | Refused with "8 to 72 characters" in all three places. |
| T-06 | WP-2 | Add room with capacity `0`, `51`, `99999999999`; update room id `999999` | Friendly refusal each time; "Room not found" for the missing id; no 500. |
| T-07 | WP-2 | Force a DB error on penalty issue / bed create (e.g. temporarily rename a table in a test DB) | User sees a generic message; `logs/app.log` has the real error; no SQL text on screen. |
| T-08 | WP-3 | Set move-in `1900-01-01`, `0001-01-01`, `2099-01-01` | Refused with a clear range message; no rent rows created. |
| T-09 | WP-3 | Unit-test `syncRentCharges` with a 200-cycle input | Throws the cap error; transaction rolled back. |
| T-10 | WP-3 | Set move-out 30 days ahead | Status `on_notice`, bed **kept**, balance unchanged until that date. |
| T-11 | WP-3/9 | Advance the test clock past the move-out date, run the daily job | Status `moved_out`, bed vacant, last cycle prorated by days. |
| T-12 | WP-4 | Delete a resident that has one repair / incident / SOS and nothing else | Archived (not erased); all three records still present; hard delete only for an empty account. |
| T-13 | WP-5 | Waive a penalty without a reason; then with one | First refused; second stores `waived_by`, reason, time; resident notified with the reason; ledger shows it as waived. |
| T-14 | WP-6 | Record ₱3,500 cash for a resident with 2 unpaid cycles | Payment `admin-approved`, oldest cycle cleared, balance −₱3,500, notification sent, ledger row "cash". Amount 0 / negative / > bound refused. |
| T-15 | WP-7 | Assign a bed after opening the dashboard | Dashboard occupancy changes immediately (live count). |
| T-16 | WP-7 | Approve a receipt submitted last month; export this month's ledger and view dashboard "collected" | The two figures match. |
| T-17 | WP-8 | Fire two simultaneous approvals of one receipt (two processes) | One succeeds; **no** extra late fee or waiver is left by the loser. |
| T-18 | WP-9 | Press "send reminders" twice | Second press reports "already sent"; resident has one notification per stage. |
| T-19 | WP-9 | Open any admin page twice in a day | Job runs once (guard row); late-fee check, snapshot and reminders executed once. |
| T-20 | WP-9 | Resident with move-in = today, status `pending` | Becomes `active` per the owner's decision (or stays `pending` if declined). |
| T-21 | WP-10 | Change payment channel and a landing price in Settings | Pay Rent page and landing page change; HTML is escaped. |
| T-22 | WP-10 | Clear the payment channels | Pay Rent page shows "ask the administrator", not placeholder numbers. |
| T-23 | WP-11 | Two "low" repairs, one 20 days older | The older one ranks higher; tier labels unchanged. |
| T-24 | WP-11 | Staff assigns and adds a note | Resident sees assignee and note; notified. |
| T-25 | WP-11 | Resident cancels an `open` repair; tries on an `in_progress` one | First works; second refused. |
| T-26 | WP-11 | Reopen a resolved repair without / with a reason | Refused / allowed + resident notified. |
| T-27 | WP-12 | Add expense with a receipt photo; void it | Receipt viewable by admin only; voided row excluded from totals and ledger, kept in history. |
| T-28 | WP-12 | Staff opens Expenses | Allowed or refused exactly as the owner decides. |
| T-29 | WP-13 | Move an inquiry through new → contacted → converted | Status persists; filter works; list shows more than 10. |
| T-30 | WP-13 | Staff opens Inquiry Center | Sees counts/names only — no phone or e-mail. |
| T-31 | WP-14 | Deactivate the only admin | Refused ("last active admin"). Edit staff e-mail to an existing one → refused. |
| T-32 | WP-15 | Resolve an SOS marking false alarm with a note | `resolved_by`, note and flag stored; appears in SOS history. |
| T-33 | WP-15 | File an incident with people involved | Stored and shown to staff only. |
| T-34 | WP-16 | Stop the DB; open dashboard | Status badge no longer says "System Operational". Admin sidebar lists Maintenance Queue, Incidents, SOS. |
| T-35 | WP-17 | Run backup then restore into a scratch DB | Row counts equal; uploads present; old backups pruned to 14. |
| T-36 | WP-18 | Seed 500 payments; open the page | 50 per page, search works, no page > 1 s. |
| T-37 | Regression | `php tests/run.php --with-ai` after each work package | All files pass; no new 500 lines in `logs/app.log`. |

**Note on T-17:** the project's test server is single-threaded, so a real race test needs two PHP workers (e.g. `php -S` on two ports against the same DB, or a script that calls `PaymentReviewService::approve` from two CLI processes with a barrier).

---

## 6. Implementation roadmap

Order is by risk first, then by dependency. Effort is a rough guide for one developer.

```
Phase 0  — before anything else (hours)
  ├─ Change the three live demo passwords by hand            ← F-01, no code needed
  └─ WP-1  Credential hygiene (DB user, forced change, launcher refuses)

Phase 1  — stop data damage (≈ 2–3 days)
  ├─ WP-2  Input hardening & error hygiene        (independent, do first: one line fixes most 500s)
  ├─ WP-3  Date safety + move-out semantics       (feeds WP-9)
  ├─ WP-4  Resident record integrity              (independent)
  └─ WP-5  Waivers / "Mark paid"                  (UI ready; cash path comes in WP-6)

Phase 2  — money path (≈ 3–4 days)
  ├─ WP-8  Atomic approval + read-only GETs       (must precede WP-6)
  ├─ WP-6  Cash / manual payment entry            (needs WP-8; completes WP-5)
  ├─ WP-7  Dashboard / ledger / occupancy consistency
  └─ WP-10 House settings (payment channels, landing content)   ← needs owner's real details

Phase 3  — automation (≈ 2 days)
  └─ WP-9  Daily housekeeping, reminders, automatic transitions  (needs WP-3 + WP-7)

Phase 4  — workflow completeness (≈ 1 week, any order)
  ├─ WP-11 Repairs      ├─ WP-12 Expenses     ├─ WP-13 Inquiries
  ├─ WP-14 Accounts     └─ WP-15 SOS / incidents

Phase 5  — polish and operations (≈ 2–3 days)
  ├─ WP-16 UI honesty & navigation   ├─ WP-17 Backup / retention   └─ WP-18 Paging

Anytime / owner decisions
  ├─ WP-19 CCTV: build it or remove the landing-page claim
  └─ WP-20 Run the not-yet-tested items (Ollama, tunnel, race, idle timeout)
```

**Dependency summary**

| Package | Needs first | Why |
|---|---|---|
| WP-6 | WP-8 | Cash entry should use the single-transaction path. |
| WP-5 | WP-6 (soft) | Admins need a correct way to record "they paid cash" before "Mark paid" is relabelled a waiver. |
| WP-9 | WP-3, WP-7 | Reuses the move-out rule and the snapshot fix. |
| WP-10 | Owner's real payment details | Otherwise it just moves placeholders into the database. |
| WP-17 | WP-9 | Retention pruning runs in the daily job. |

**Decisions needed from the owner (blocking WP-9, WP-10, WP-12, WP-19)**

1. Real GCash / Maya / bank account details, and the correct contact e-mail / Facebook addresses.
2. Should `pending` residents become `active` automatically when their move-in date arrives?
3. Should **staff** be able to record expenses (spec) or admin only (current)?
4. Build the CCTV page, or remove "Live CCTV" from the landing page?
5. Should reminders go out before the due date (suggested: 3 days before, on the day, weekly while overdue)?

---

## Appendix A — Raw evidence index

| Evidence | Where |
|---|---|
| Project suite | `php tests/run.php` → "8/8 test files passed". |
| Demo passwords unchanged in live DB | `php database/check-demo-passwords.php` → lists `admin@rjm.test`, `staff@rjm.test`, `boarder@rjm.test`. |
| Root / blank DB access | PDO `root` with empty password connected to the live server and listed 14 databases. |
| Move-in extremes | Probe `DTE-06` (1,544 rows, balance ₱3,088,000, 0.6 s) and `DTE-07` (24,664 rows, 9.9 s). |
| Future move-out | CLI call of `BoarderProfile::updateDatesAndStatus()` with a date 30 days ahead → `status=moved_out`, `bed_id=NULL`, bed `vacant`. |
| Delete erases history | Probe `DEL-01`. |
| Mark-paid has no reason | Probe `PEN-14` (`waive_reason = NULL`); balance 8,173.45 → 8,050.00 with no payment row. |
| Duplicate reminders | Probe `PEN-17` (1 → 2). |
| 500 on array token | `logs/app.log`: "`Csrf::verify(): Argument #1 ($token) must be of type ?string, array given`". |
| Capacity overflow | `logs/app.log`: "Out of range value for column 'capacity'". |
| Password truncation | Probe `BRD-08b` (101-char password; a different suffix still logs in). |
| Stale occupancy | Probe `OCC-01` (3 occupied beds vs 2 shown) and the dashboard screenshot (2/5) next to the Occupancy page (3/5). |
| Landing prices fixed | Probe `LND-11`; `LandingController.php:66-118`. |
| Hard-coded payment accounts | `src/Views/portal/payment_new.php:273-290`. |
| Decorative badges | `src/Views/admin/dashboard.php:11-16`, `admin/occupancy.php:28-31`, `admin/payments.php:25-30`, `staff/incidents.php:60-61`. |
| No time-decay | `grep` of `ScoringClient`, `MaintenanceRequest`, `MaintenanceController` for decay/aging/age terms → none; `queueSorted()` orders by tier then created time only. |

*The probe scripts were kept outside the repository (session scratchpad) so the project stayed untouched. They can be moved into `tests/` as permanent regression cases when the fixes are implemented — see T-37.*
