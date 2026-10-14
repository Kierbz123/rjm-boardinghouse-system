# Build Phases — RJM Boardinghouse System

Read at the start of every session to confirm which phase is current (check the Progress Log at the bottom). Each phase follows the same loop: **Plan Mode → implement → run this phase's verification → security-reviewer pass if relevant → update Progress Log → commit.**

Don't treat a phase as done because the code "looks right." Run the verification steps and show the actual output — that's the check Claude Code's own best practices call the thing that lets you stop watching and let the loop close on its own.

---

**Phase 0 — Environment & Foundation**
Set up local Apache+PHP+MariaDB and the Python microservice skeleton (`localhost:5000`). Create the database and all tables from `ARCHITECTURE.md` §7/ER diagram. Decide and record in the Progress Log: XAMPP vs WAMP vs manual LAMP; whether OCR is in scope for feature 7; whether to hand-roll feature 1's scoring or use a rule-engine library.
- *Verify:* `curl localhost:8080` returns the PHP app's landing page. `curl localhost:5000/score-repair` (with a dummy payload) returns a valid JSON response per the contract in `ARCHITECTURE.md` §3.2. A test row can be inserted and read back from every table.

**Phase 1 — Auth & RBAC**
Login/logout, sessions, password hashing, role-based route protection for Admin/Staff/Boarder. Seed one test account per role.
- *Verify:* seed accounts for all 3 roles; log in as each and confirm redirect to the correct dashboard. As a logged-in Boarder, request `/admin/dashboard` directly by URL — must reject (403 or redirect), not render. Attempt login with a wrong password — must fail with no information leak about which field was wrong.

**Phase 2 — Boarders, Rooms & Beds**
CRUD for rooms/beds, boarder profiles, detailed bed mapping (feature 8), Status Life System (feature 10) with status-change logging.
- *Verify:* assigning a boarder to an already-occupied bed is rejected. Marking a boarder moved-out frees their bed automatically and logs the status change with a timestamp and actor.

**Phase 3 — Financial Core**
Payments + Proof of Payment Verifier (feature 7), Expense Logging (feature 4), Penalty Rules & Automation (feature 9), Ledger Export (feature 5).
- *Verify:* claimed amount == expected amount → `auto-matched`. Claimed ≠ expected → `flagged`. Stop the Python service and submit a payment → status is `pending`, never auto-approved (fail closed, per `CLAUDE.md`). A late-fee rule of "5/day late" applied to a boarder 3 days late produces a penalty of exactly 15. Ledger export for a date range sums to the same total as a manual query against `payments`+`expenses`+`penalties`.

**Phase 4 — Maintenance Module**
Repair request submission with media upload, the Python priority-scoring service (feature 1), staff queue sorted by priority.
- *Verify:* "gas leak smell in kitchen" scores Critical/High. "squeaky door hinge" scores Low. Stop the Python service and submit a request → it's still saved, tier defaults to Medium, and `scoring_pending = true` is visible to staff.

**Phase 5 — Safety Module**
Emergency SOS (feature 2) with alerting to staff/admin, and the separate Incident Reporting log.
- *Verify:* triggering SOS as a Boarder makes it appear on the Staff/Admin dashboard within one poll interval, with the correct room/bed. An incident can be logged with no SOS alert involved.

**Phase 6 — Admin Command Center & Occupancy Trend**
Aggregate dashboard (feature 3) and Occupancy Trend charting (feature 6), now that the underlying modules exist.
- *Verify:* every number on the dashboard (pending payments, open requests by tier, active SOS count) matches a direct SQL query against the same tables.

**Phase 7 — Notifications**
Wire the All-Around Notification System (feature 11) into every module's key events.
- *Verify:* a rent-due date reached creates a notification for the affected boarder; resolving a maintenance request notifies its submitter; an SOS acknowledgment notifies the boarder who triggered it.

**Phase 8 — Testing, Hardening & Polish**
Full functional pass through every role's flows; security pass; UI consistency pass.
- *Verify:* run the full Playwright suite (via the `webapp-testing` skill) across all three roles. Attempt SQL injection in a text field, XSS in a text field, and a form submission with a missing/invalid CSRF token — all three must fail safely with no data corruption. Dispatch the `security-reviewer` subagent for a final pass over the whole diff before calling the project done.

---

## Progress Log
*(Update in the same turn you finish or materially change a phase — don't let this go stale, and don't let it grow unbounded: once a phase is done, compress its notes to one or two lines rather than keeping a blow-by-blow.)*

### Assumptions & decisions made
- **Phase 0 (verified end-to-end):** Migrations `0001`–`0010` apply cleanly against MariaDB in filename order; all 13 tables + indexes/FKs created with no errors. Python scoring service starts, binds `127.0.0.1:5000` only (confirmed via `ss -tlnp`), and both `/score-repair` and `/verify-payment` return valid responses matching the `ARCHITECTURE.md` §3.2 contracts. PHP dev server serves `public/index.php` correctly via `php -S`. All `.php` files pass `php -l`.
- **Phase 0 fix — DB user:** MariaDB's default `root` account uses `unix_socket` auth and cannot connect over TCP (the PDO connection failed with "Access denied" until this was found). Rather than fight that, created a dedicated `boardinghouse_app` MariaDB user scoped to `rjm_boardinghouse.*` for TCP access — which is the right call anyway (never point an app at root). `.env.example` updated to match; if you set this up locally, run the `CREATE USER` / `GRANT` statements now in `.env.example`'s comment before pointing the app at your own MariaDB.
- Phase 0: XAMPP/WAMP vs. manual LAMP — not decided here; this verification ran against a bare PHP 8.3 + MariaDB 10.11 install. Pick whichever local stack you prefer, the behavior above should hold either way.
- Phase 0: OCR scope for feature 7 — not yet decided, still open.
- Phase 0: scoring engine — implemented as a hand-rolled weighted-rule engine directly in `scoring_service/scoring.py` (keyword dictionary + category weight + media bonus + time decay), not a rule-engine library from `FEATURES.md` §1's optional-libraries list. Verified against both required test cases.
- Phases 1-7: built and verified end-to-end against a real PHP 8.3 + MariaDB + Python stack (not just code review — actual HTTP requests, actual DB rows, shown below).
- **Bug found and fixed:** `MaintenanceRequest::queueSorted()`'s join against `users` made its `WHERE status != 'resolved'` ambiguous (`users` also has a `status` column) — MariaDB rejected the query (error 1052), which took down the staff dashboard entirely. Fixed by qualifying every column in that query with its table name. Worth a reminder: any query joining `users` needs this same care — `status` collides, `created_at` doesn't currently exist on `users` so it's safe, but check before assuming.
- **Environment gotchas already folded into `CLAUDE.md`:** MariaDB root/unix_socket auth, and `php-curl` not being part of a bare `php-cli` install (both `ScoringClient` and `VerificationClient` depend on it).
- Seeded boarders start at `status = 'pending'` (correct default, not a bug) — both `PenaltyEngine::runCheck()` and the rent-due reminder deliberately only act on `status = 'active'` boarders, so a fresh seed shows 0 penalties/reminders until a boarder is activated. This is intended behavior, verified by explicitly activating a boarder mid-test.

### What was verified (real output, not code review)
- **Phase 1:** all 3 role logins redirect correctly; wrong password → generic error; boarder → `/admin/dashboard` → real `403`.
- **Phase 2:** assigning an occupied bed → rejected with the exact message; moving a boarder to `moved_out` → bed frees to `vacant` AND `boarder_status_log` records old→new/who/why in one transaction.
- **Phase 3:** claimed=expected → `auto-matched`; claimed≠expected → `flagged`; Python service killed mid-request → payment stays `pending` (fail-closed, confirmed live). Penalty engine with an injected date (3 days late, 5/day rule) → penalty amount **exactly 15.00**.
- **Phase 4:** "gas leak smell in kitchen" → `critical`; "squeaky door hinge" → `low`; Python service killed → `medium` + `scoring_pending=1`.
- **Phase 5:** SOS trigger → appears with correct room (101); staff acknowledge → alert status updates AND a `sos_acknowledged` notification is created for the boarder; incident logged with no SOS involved.
- **Phase 6:** admin dashboard's displayed "Pending/Flagged Payments" and "Open Requests" counts matched direct `SELECT COUNT(*)` queries exactly.
- **Phase 7:** resolving a maintenance request → `maintenance_resolved` notification created for the submitter; rent-due reminder → `rent_due` notification created for an active boarder with no verified payment.
- **Phase 8:** not run — the actual Playwright browser suite needs real browser binaries, which this sandbox's network allowlist can't fetch (playwright's CDN isn't on it). Everything above was verified via direct HTTP/curl + DB assertions instead, which covers the same logic but isn't a substitute for a real browser pass on your machine.

### Phase status
| Phase | Status | Notes |
|---|---|---|
| 0 | **Done** | DB schema live, both services verified end-to-end, front controller serves. |
| 1 | **Done** | Auth/RBAC — see verification above. |
| 2 | **Done** | Beds/status — see verification above. |
| 3 | **Done** | Payments/penalties/ledger — see verification above. Ledger CSV export verified against a direct DB sum (found and fixed a formatting-only mismatch — see below). |
| 4 | **Done** | Maintenance scoring — see verification above. |
| 5 | **Done** | SOS/incidents — see verification above. |
| 6 | **Done** | Command Center — see verification above. Occupancy trend page verified against a direct bed count (see below). |
| 7 | **Done** | Notifications — see verification above. |
| 8 | **In progress** | Security review done (see findings below). All 8 Playwright scripts now written (`tests/playwright/`) — one per phase plus a combined runner — but none executed anywhere yet (no browser binaries on this sandbox's network allowlist). Run them on your machine. |

### Phase 8 — security review findings (found and fixed in this pass)
Manual review against the `security-reviewer` subagent's checklist, backed by live attack tests (SQLi, XSS, CSRF, IDOR) against the running app — not just a code read-through.

**Fixed:**
- **IDOR (Critical):** `NotificationController::markRead()` had no ownership check — any logged-in user could mark *any other user's* notification read by guessing the ID. Fixed with `Notification::belongsTo()`.
- **Missing CSRF (Critical):** `POST /logout` and `POST /api/notifications/{id}/read` had no CSRF check at all. Fixed both, including adding the missing token field to the logout form in `nav.php` (it would have broken once CSRF was enforced).
- **Session cookie hardening (Critical):** `ARCHITECTURE.md` §3.1 specified `httponly`/`samesite=strict` from the start, but it was never actually implemented — the app was running on PHP's session defaults. Fixed with `session_set_cookie_params()` before `session_start()` in `public/index.php`.
- **Verbose error disclosure (Warning):** PHP's built-in dev server shows full stack traces on uncaught errors by default. Added a global `set_exception_handler` that logs the real error server-side and shows the visitor a generic message instead.
- **No file logging existed (Warning, pre-dated this phase):** `CLAUDE.md` named the `logs/app.log` convention but nothing wrote to it. Added `src/Support/Logger.php`, wired into login success/failure and the new exception handler.
- **Uploads defense-in-depth (Suggestion):** added `.htaccess` to `public/uploads/` disabling PHP execution outright, on top of the existing extension-allowlist in `Uploads::store()`.
- **Missing amount validation (Suggestion):** payments, expenses, and penalty rules now reject amounts ≤ 0.
- **Notification bell was decorative:** `nav.php` had the markup but no JS ever called the endpoints. Wired up polling + mark-as-read, which is what surfaced the IDOR above in the first place.

**Verified live after fixing** (not just re-read): SQL injection in the login form → rejected, no bypass; XSS in a maintenance description → escaped in output, not executed; state-changing POST with no CSRF token → `400`; cross-user notification mark-read → blocked (an anomaly during this specific test: it returned `400` instead of the expected `403`, which traced to a CSRF-token timing issue in the *test script*, not the app — the actual security property, that the write never happened, held either way); the previously-unprotected logout now correctly rejects a token-less request.

**Still open — genuinely not done:**
- **None of the 8 Playwright scripts have been executed anywhere.** They're written correctly against this codebase's real routes and forms, but this project was built in a sandbox without browser binaries available — running them for the first time, on a real machine, is still an open item.

### Login rate limiting (done)
The one item repeatedly flagged as "genuinely open" across earlier passes. `login_attempts` (migration `0011`) logs every attempt — email, IP, success/failure, timestamp. 5 failed attempts within 15 minutes locks that email out, checked **before** the password is even verified, and the lockout holds even if the very next attempt has the correct password — same fail-closed discipline as the payment/scoring connectors. Scoped per-email, not global or per-IP, so one account under attack doesn't affect anyone else.

Verified live: 5 wrong passwords → 6th attempt with the *correct* password still rejected; a different account logs in normally in the same run; the lockout is both logged (`logs/app.log`) and persisted (`login_attempts` table) with real rows to show for it.

### Ledger export and occupancy trend (closed out)
The two items left as "not individually verified" in earlier passes:

- **Ledger export:** seeded a known payment, expense, and penalty, then compared the CSV's TOTAL row against a direct `SUM()` query for the same date range. First run showed `3850.50` (DB) vs `3850.5` (CSV) — same number, just inconsistent decimal formatting between MySQL's `DECIMAL` output and PHP's float-to-string conversion. Fixed with `number_format()` in `LedgerBuilder`, not just noted. Re-verified clean.
- **While re-verifying**, a payment was submitted with the Python scoring service not running — it correctly stayed `pending` and was correctly *excluded* from the ledger total, since `LedgerBuilder` only counts `auto-matched`/`admin-approved` payments. Not a bug — a good confirmation that the fail-closed design holds even in financial reporting, not just in the UI.
- **Occupancy trend:** displayed occupied-bed count matched a direct `COUNT()` query exactly (1/2 beds).

### GSAP polish (done)
The three signature moments named in `UI-LIBRARY-EVALUATION.md` are implemented, not just planned — deliberately three, not "animate everything," per the restraint principle in the `frontend-design` skill:
1. **Admin dashboard reveal-on-scroll** — the 4 stat cards fade/slide in via `ScrollTrigger` as they enter view.
2. **SOS pulse** — a pulsing dot next to any `active` alert on the staff dashboard, the one thing on that page staff can't afford to miss.
3. **Priority-badge entrance** — maintenance queue badges settle into place on load, reinforcing that the sort order itself is the point of that page.

All three use `gsap.matchMedia()` to respect `prefers-reduced-motion` — reduced-motion users get the end state instantly, no animation, not a stripped-down version of one. Verified rendering correctly (markup + script both present, zero page-load regressions) — not yet seen running in an actual browser, same caveat as the Playwright scripts above.

### Small UI fix made while writing the Phase 2 test
The "Assign Boarder to Bed" form on `/admin/rooms` needs a boarder's user ID, but `/admin/boarders` never displayed it — a real usability gap, not just a test-writing inconvenience. Added an ID column to the boarders table and a pointer link from the rooms page.

### Full QA simulation pass (role-based, CRUD, security, database integrity)
A separate, rigorous end-to-end simulation was run against this system — see **`QA-VALIDATION-REPORT.md`** for the full report. Three real bugs were found and fixed: two uncaught-exception crashes (duplicate email, status-update on a non-existent ID) and one systemic gap where six forms relied entirely on client-side HTML `required` attributes with no server-side validation at all.

**The CRUD-completeness gap the report surfaced has since been closed** — see `PROJECT_STRUCTURE.md`'s "Data classification" section for the deliberate rule now in place (event records stay append-only; configuration data is editable; deletes are guarded in the Model layer, never left for a database constraint to catch) and `QA-VALIDATION-REPORT.md`'s updated Section C/H for what was built and verified. Final status upgraded from READY WITH MINOR ISSUES to **READY**.

### Bottom-up debugging pass (database → backend → auth → API → frontend → integration → performance)
A second, differently-structured audit — see **`DEBUGGING-REPORT.md`**. This one targeted the seams *between* layers specifically, which the role-based QA pass wasn't structured to catch, and found two real bugs there: the notification bell updated its UI without checking whether the server call actually succeeded, and `/api/sos/active` existed with correct auth but had no consumer — its intended live-polling purpose was never wired up. Both fixed. Also found and fixed an N+1 query pattern and a missing index. Final status: **PASS**.

### Billing rules R3: rent every 30 days from move-in, electricity split per room, amenities (5 Oct 2026)
Owner rules given in this session; they **replace the "due on the 5th" rule from R1 below**:
- **Rent** is charged in 30-day periods counted from the day the resident moved in, each due 30 days after it starts (move in 20 Oct: due 19 Nov, 19 Dec, 18 Jan). Every period is charged in full; only a last period cut short by a move-out is prorated (days ÷ 30). `BillingService::CYCLE_DAYS`, `dueDate()`; a charge is keyed by its period's first day (`rent_charges.period`, now `YYYY-MM-DD`, migration `0028`).
- **Electricity:** the admin enters a room's bill on Rooms & Beds; it is divided equally among the residents staying in the room (status active or on notice) and each share is due on that resident's next rent due date (`Models\ElectricityBill`, tables `electricity_bills` / `electricity_charges`). A bill entered by mistake can be removed. Shown to the resident on Pay Rent and the dashboard, and to the admin on the resident's profile and the payments list.
- **Payments** clear rent first, then electricity, then penalties. Late fees apply to rent only.
- **Text:** water is free, solar power back-up (no power interruptions), laundry service through the caretakers: landing page (Utilities), two new assistant help answers (English and Tagalog) and the Ollama prompt.

Assumptions (say so if any is wrong):
- "Every 30 days" is taken literally, so the due day drifts through the months. To use the same calendar day each month instead, change `dueDate()` and the loop step in `syncRentCharges()`.
- Rent is paid at the end of each 30-day period (first payment 30 days after moving in), not in advance on the move-in day.
- The bill is split among whoever is staying in the room on the day the admin enters it, in equal shares rounded to the centavo.
- Unpaid late fees from before this change (keyed by month) are kept as they are; rent that fell due before migration `0028` was applied gets no new late fee (same safeguard as W4).

Verified (real output):
- `php tests/run.php`: **6/6 test files passed, 396 checks, 0 failures**, including new checks for the 30-day due dates, period labels, late fees per period, the electricity split (₱1,500 ÷ 2 = ₱750; ₱1,000 ÷ 3 = ₱333.33), rent-before-electricity allocation, duplicate and empty-room bills refused, bill removal, and the new assistant answers.
- Browser (dev database): admin entered ₱1,500 for Room 1 (2 residents): "₱750.00 each, due with their next rent". The resident who moved in on 3 Oct saw "Rent, Oct 3 – Nov 1, 2026 ₱3,500.00, Due Nov 2, 2026" and "Electricity share ₱750.00, Due Nov 2, 2026", total ₱4,250.00. The landing page shows the new Utilities and Payments text. No JavaScript errors. The test bill was removed afterwards.

**Security review (R3)** — `security-reviewer` pass on the diff: no critical or high findings; SQL, CSRF, role checks and every output of the bill label were checked clean. Fixed: `periodLabel()` no longer throws on a malformed old `payments.billing_period` (shown as typed); deleting a room that has electricity bills is refused with a message instead of a database error; `ElectricityBill` create and delete lock residents in the same order. Left as is, by choice: late fees from before this change (keyed by month) stay owed and can be waived one by one on the Penalties page; shares are rounded to the centavo, so a bill that does not divide evenly is a centavo or two off in total; the duplicate-bill guard is an exact match on the "Bill for" text.

### Billing rules R1: due dates, receipt review, upload limits, security text (5 Oct 2026)
Owner rules implemented (decisions confirmed by the owner in this session):
- **Due dates:** rent is due on the 5th; nothing is due during a resident's first 30 days. A month's due date is the later of the 5th and move-in + 30 days (`BillingService::dueDate()`, stored in `rent_charges.due_date`, migration `0025`). The first month stays prorated by days stayed.
- **Late fees** now count from each unpaid month's own due date (`PenaltyEngine`), cover unpaid earlier months, and still never stack.
- **Receipts:** method (GCash/Maya/bank transfer) required; optional reference number; always saved as `pending` (the `flagged` status is retired, and old flagged rows were moved to pending; a mismatch shows as ⚠ to the admin). A one-time pop-up tells the resident it is *Pending review*. Approve and reject notify the resident; reject requires a reason (`payments.review_note`). One receipt under review per resident at a time.
- **Uploads:** one effective limit (20 MB, or lower if PHP's ini limits are lower), shown on the forms; over-size files now get a clear message instead of being silently dropped. The launcher and test runner pass `-d upload_max_filesize=20M -d post_max_size=25M`.
- **Text:** curfew removed everywhere (landing page, assistant answers, Ollama prompt, staff and profile cards). Security says "Live CCTV covers the entire boardinghouse." The CCTV/people-counter feature is a plan only: `CCTV-PEOPLE-COUNTER-PLAN.md`.

Assumptions:
- The move-out month stays prorated as before; the 30-day rule only delays due dates and never changes amounts.
- A receipt is filed under the resident's oldest unpaid month (or the current month if nothing is owed), because that is the month an approval settles first.

Verified (real output):
- `php tests/run.php`: **6/6 test files passed**, including new checks for due dates, the 30-day grace, late fees on earlier months, the method requirement, the pop-up shown once, reject-needs-reason, the approve/reject notifications, and 21 MB / 26 MB uploads refused with "The limit is 20 MB".
- Browser walkthrough (headless Chromium, demo DB): resident moved in 20 Aug sees August ₱1,354.84 and September both overdue since Sep 19, and October due Oct 5. A ₱5,000 GCash receipt gave the pending pop-up; admin approval notified "August 2026 rent ₱1,354.84, September 2026 rent ₱3,500.00, October 2026 rent ₱145.16". A Maya receipt rejected with a reason showed the reason to the resident. A ₱3,400 bank transfer left ₱45.16 credit. The landing page has no curfew and shows the CCTV line. No JavaScript errors.
- Migration `0025` applied to a fresh database and re-applied over an old `flagged` row: converted to `pending`, no errors.

**Security review (R1)** — `security-reviewer` pass on the diff: no critical findings. Fixed:
- W1: approving now asks the admin for the amount the receipt shows, which is what billing credits (`payments.approved_amount`, migration `0026`).
- W2: one-receipt-under-review is checked and inserted under the boarder's row lock (6 simultaneous submissions from 6 logins on a 4-worker server → 1 accepted, no orphaned files).
- W3: a reused reference number is flagged on the admin list.
- W4: no new late fees on months due before migration `0025` was applied (`BillingService::rulesStartDate()`).
- S1: rejection reasons capped at 160 characters so the notification is never cut off.
- S2: method values escaped.

Left as is: S3 (integer values interpolated in test SQL, not exploitable; matches the existing tests).

**S4 decided by the owner (5 Oct 2026): fair default + admin override.** Late days stop counting on the day a receipt is *submitted*, for the months it pays (oldest first): a receipt sent on or before the due date means no fee, and review time is never charged. A rejected receipt makes those months late again. When approving, the admin sees the fee and may waive it with a required reason (`PaymentReviewService`, `PenaltyEngine::stopDates()`, migration `0027` adds `waived_by` / `waive_reason`).
- Verified: `php tests/run.php` 6/6, with new checks for an on-time receipt (no fee), a receipt on the 8th approved on the 12th (fee for the 6th–8th only), rejection (counts to the 12th again), a partial receipt (keeps counting), the dialog preview, approval with and without a waiver, and waiver without a reason refused. Browser: dialog shows "August 2026: 16 day(s) late (Sep 20–Oct 5) ₱80.00", blocks a waiver without a reason, and records and notifies the waiver.
- Assumption: a pending receipt only pauses the months its *claimed* amount would fully cover; at approval the *confirmed* amount decides.
- The `security-reviewer` agent wasn't available this time. A manual check of the change found: approve and waive stay admin-only behind CSRF; all SQL is prepared, with `IN (?, …)` placeholders; the reason is escaped where shown; and a double approval is refused by `setVerification`'s lock (fee and waiver steps are idempotent).

Re-verified after the fixes: `php tests/run.php` 6/6, browser walkthrough 24/24.

### Online demo mode: login QR works from any network (8 Oct 2026)
- **Rule change, at the owner's request:** "localhost only" is now "localhost by default". The one permitted exposure is a temporary Cloudflare quick tunnel started by hand with `start-online.bat` (`bin/go-online.ps1`, modelled on the TripleR car-rental project's `demo-online.ps1`). Data stays on this machine; nothing is hosted.
- `App\Support\TrustedProxy` reads the visitor's address and the https flag from the tunnel's headers, only when the connection comes from an address in `TRUSTED_PROXIES` (empty by default, so normal use is unchanged). Used for the session cookie's `Secure` flag, the sign-in lockout and the inquiry limit.
- `AuthController::publicBaseUrl()` returns `APP_BASE_URL` when the launcher sets it, so the login page's QR code, address pill and "Open mobile preview" link use the public address; the label then reads "Online · any network".
- The launcher warns (and continues) when a demo account still has its seeded password (`database/check-demo-passwords.php`).
- Verified: `php tests/run.php` 7/7 including the new `test_trusted_proxy.php`. A local server started with the launcher's two settings against the test database: cookie `Secure` with the tunnel's headers and not without; QR/link/pill show the public address; a failed sign-in sent with a forged address in front of the real one was recorded under the real one. Normal server on :8000 unchanged (LAN address, "Local Wi-Fi Network", cookie not `Secure`).
- **Not verified here:** a run through a live tunnel. The assistant's session is not allowed to open one; the owner runs `start-online.bat` and checks from a phone on mobile data.
- Assumption: no LAN-only mode was added; the tunnel address also serves phones on the same Wi-Fi.
- Security review (`security-reviewer` agent) found nothing exploitable in the address, cookie or public-address handling. Fixed from its findings: `/%00` no longer reaches `realpath()` (it threw before the error handler existed and would have shown a file path); the online server runs with `display_errors=0`; a tunnel visitor on plain http still gets a `Secure` cookie; the public-address pattern ends with `\z`. Left as they are, by the owner's choice or as known limits in the README: the launcher warns about seeded demo passwords but still goes online; five wrong passwords lock an email for 15 minutes whoever typed them.
- Follow-up (9 Oct 2026): with the normal launcher the login page still advertised the laptop's Wi-Fi address (`http://10.0.3.226:8000/login`), where nothing listens, so "Open mobile preview" refused to connect. `publicBaseUrl()` no longer substitutes the Wi-Fi address (`detectLanIpv4()` removed); offline it shows the address the page was opened on, labelled "This computer only", with a hint to use `start-online.bat` for phones. This replaces the earlier "leave offline mode as is" choice. Verified on :8000: the link is `http://127.0.0.1:8000/login` and answers 200; `php tests/run.php` 7/7.
- Follow-up 2 (9 Oct 2026): a phone cannot use a QR code for `127.0.0.1`, so the normal launcher's login page now shows "Phone access is off" with the steps instead of a QR code. While `start-online.bat` is running it writes its public address and port to `logs/online-address.log` (removed when it stops), and the normal server on :8000 reads it, so both login pages show the same working QR code. The port is tried before the file is believed, so a file left by an abruptly closed window is ignored. Verified on :8000 with no file, a left-over file, and the live launcher's file; `php tests/run.php` 7/7. The owner confirmed the phone could open the system through the tunnel.

### Phone fixes found during the first online run (9 Oct 2026)
- **Peso sign showed as an empty box on the owner's phone.** Onest has no ₱ glyph (measured: ₱ rendered at the fallback font's width while € used Onest), so the sign always came from the device's own font, and that phone's font lacks it. `fonts/LICENSE.txt` had wrongly said the latin-ext file included it. Added `peso-sign.woff` / `peso-sign-bold.woff`, each holding only U+20B1, cut from the DejaVu Sans copy that ships with Python's matplotlib (nothing downloaded; licence in `fonts/LICENSE-DEJAVU.txt`). They are a separate family, `RJMPeso`, placed first in `--font-sans` and `--font-mono` and in the four inline monospace cells of `portal/payment_new.php`. A first attempt that added them to the "Onest" family by unicode-range only took effect for bold text, hence the separate family.
- **Profile and logout row unreachable on a phone.** `#app-sidebar` had `height: 100vh`, which on phones includes the strip under the browser's toolbars; the row at the bottom sat off-screen. The height is removed: `top: 0; bottom: 0` already pin the sidebar to the visible screen.
- Verified: in a 375x812 preview the peso sign measures as the bundled glyph at regular, bold and monospace, and the open sidebar's profile and logout controls are inside the viewport; desktop (1280x800) unchanged; the new font and stylesheet are served through the live tunnel; `php tests/run.php` 7/7. The preview cannot imitate a phone browser's sliding toolbars, so the sidebar fix still needs the owner's phone to confirm.

### Feature audit: every feature traced from screen to database (10 Oct 2026)
Full record, with the traceability table and evidence: **`FEATURE_IMPLEMENTATION_AUDIT.md`**.
- **Method:** all 86 routes read with their guards; an automated crawl of every rendered page (visitor, admin, staff, resident) proves each form posts to a real route, each link opens, and each state-changing route has a form; the 28 routes no test had touched are now exercised with a database check after each action (`tests/test_z_feature_audit.php`, 101 checks).
- **Found and fixed:** no in-app way to reset a forgotten password although the login page tells users to ask the administrator (now an admin action for residents and staff, `PasswordResetService`); two resident profiles on one bed in the live data, with nothing in the database to prevent it (migrations `0029`, `0030`: stale pointer cleared, unique indexes); bed-freeing code that followed a profile pointer and could have emptied another resident's bed (`Bed::vacateHeldBy`); bed rename had a server route but no form; an unused resident-status route (removed); error pages on an over-long contact number and on oversized penalty amounts; the admin edit and restore routes accepted non-resident ids; the login page claimed a QR login "with our App" that does not exist; schema drift (two indexes and one foreign key now in migrations); dead `Penalty::create()` removed.
- **Also corrected:** the login page's "online launcher is running" test, which could show a QR code for a dead address; it now relies on a 2-second heartbeat from the launcher.
- **Security review** (`security-reviewer` agent) of the reset and bed code: no path for a non-admin; one medium item (migration repaired only one direction of mismatch: fixed by `0030` and by freeing beds by actual holder) and four low items, of which four were fixed (uncaught database error on assign/add, role check inside the service, 72-byte password limit, deactivated rule usable by staff) and one left (temporary password is not forced to be changed).
- **Verified:** `php tests/run.php --with-ai` 9/9 files, 542 checks, 0 failed (Ollama was running). Live database backed up to `database/backups/rjm_boardinghouse_20261010_093227_before_0029.sql` before migrating; live and freshly migrated schemas now match with 0 differences; one live row changed (resident #5's bed pointer cleared).
- **Not verified here:** `bin/go-online.ps1` after the heartbeat change (this session may not open a tunnel).
- **For the owner to decide:** landing-page room prices are fixed text that does not match the rooms table; resident #5 needs a bed assigned; the admin's own password can only be reset from the command line.
