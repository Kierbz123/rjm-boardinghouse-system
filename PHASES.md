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
