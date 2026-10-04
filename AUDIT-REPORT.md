# RJM Boardinghouse — Independent QA, Security & Database Audit

> **Which code this covers:** this audit tested the `main` branch (`7b89c46`, 13 Sep 2026). The working system is on the `audit-fixes` branch (folder `claude-code-project-files/`), where batches B0–B7 already fixed most of the critical and high findings below: billing rebuilt with admin-only approval, residents archived instead of erased, private uploads, security headers, a migration runner and tests. See `claude-code-project-files/FINAL-REPORT.md` and `claude-code-project-files/IMPROVEMENT-PLAN.md` for the current state. One finding still open there is **FE-01** (demo passwords in the login page).

**Date:** 2026-10-04  **Commit audited:** `7b89c46` (Initial commit)  **Mode:** review only. No source, schema or real data was modified.
**Environment:** PHP 8.3.6 · MariaDB 10.11.14 · Python 3.11 / FastAPI 0.115 · headless Chromium 141 (Playwright 1.56). All tests ran against throwaway databases (`rjm_audit`, `rjm_pw`) built from the project's own migrations and `seed.php`.

Every result is labelled **EXECUTED** (run and observed), **STATIC** (inferred from reading code) or **NOT TESTED** (with the reason).

---

## 1. Executive summary

### Health scores

| Layer | Score | Justification |
|---|---|---|
| **Front-end** | **6 / 10** | Output escaping is consistently correct: every stored-XSS probe was escaped on every page. Every POST form carries a CSRF token, role-based navigation is correct, and 54 page loads (27 page/role combinations × 2 widths) produced zero console errors or failed requests. Against that: the public login page ships the admin password, timestamps render in UTC instead of Manila time, many inputs lack associated labels, and input is lost after a validation error. The project's own Playwright suite fails 8 of 8 and has never run green. |
| **Back-end** | **3 / 10** | The security plumbing is good. RBAC (20 GET × 4 roles and 15 POST routes), CSRF (36 routes), prepared statements, bcrypt, session regeneration, notification IDOR checks and fail-closed scoring all passed. The business logic around money and occupancy is not safe to go live. A boarder can set their own rent to ₱1 and get auto-approved. The late-fee engine overcharges cumulatively. The ledger adds expenses to income. Deleting a boarder erases their payment history. Moved-out or deleted users keep access. Reassigning a bed leaves "phantom" occupied beds. |
| **Database** | **3 / 10** | Every table has a primary key and declared foreign keys, collation is consistent, and passwords are bcrypt. But none of the project's own invariants are enforced in the DB: there are zero triggers, zero CHECK constraints and no UNIQUE constraint preventing a boarder from occupying several beds. The "append-only" tables are hard-deleted by the app itself. The app user has ALL PRIVILEGES, migrations aren't re-runnable, and there is no soft delete. |

### Findings by severity

| Critical | High | Medium | Low | Info | **Total** |
|---|---|---|---|---|---|
| 2 | 8 | 16 | 19 | 5 | **50** |

### Verification breakdown (test matrix, §3)

| EXECUTED | STATIC | NOT TESTED | Total |
|---|---|---|---|
| 159 (85%) | 19 (10%) | 9 (5%) | 187 |

### Top 5 issues in plain language
1. **Boarders decide how much rent they owe.** The payment form asks the boarder for the "expected amount", and the server trusts it. Submitting ₱1 expected / ₱1 paid is auto-approved, counted as rent in the ledger, and stops the late fee from being applied. (BE-01)
2. **Deleting a boarder erases money records.** Their payments, penalties, SOS alerts, repair requests and status history are permanently deleted. In testing the all-time ledger total fell by ₱7,025 when one boarder was deleted. (BE-02)
3. **Late fees are overcharged.** Each run re-charges every day late so far, and nothing stops repeat runs on the same day. After 3 days a ₱5/day rule had billed ₱30 instead of ₱15; after 25 days it would bill ₱1,625 instead of ₱125. (BE-03)
4. **The financial ledger total is wrong.** Expenses are added to income instead of subtracted, and a date-only "to" date silently drops the last day. (BE-04, BE-27)
5. **Occupancy and access drift from reality.** Moving a boarder to a new bed leaves the old bed "occupied" forever. Staff accounts can be assigned to beds. Moved-out and even deleted boarders can keep logging in and submitting payments and SOS alerts. (BE-05, BE-06, DB-02)

> **Note on prior reports:** `QA-VALIDATION-REPORT.md` and `DEBUGGING-REPORT.md` mark the system READY / PASS. Several of their verified claims don't hold when re-run. For example, "squeaky door hinge → low" is only true for category `other`; it scores **medium** under `structural`. Those reports never exercised the client-supplied amount, the repeated penalty run, ledger arithmetic or boarder deletion.

---

## 2. System map

**Stack.** Server-rendered PHP 8.3 front controller (`public/index.php`, an explicit route table, no framework or Composer). PDO/MariaDB is the single writer. A stateless FastAPI service on `127.0.0.1:5000` provides `/score-repair` and `/verify-payment`. Tailwind CSS (prebuilt `app.css`) and vendored GSAP / QRCode.js. Windows/XAMPP launcher in `start-system.bat`.

**Modules and entry points**

| Module | Routes | Code |
|---|---|---|
| M1 Auth / RBAC / sessions (+ QR cross-device login) | `/login`, `/logout`, `/qr/{token}…` | `AuthController`, `AuthMiddleware`, `RoleMiddleware`, `LoginAttempt`, `QrLoginChallenge` |
| M2 Boarders, rooms, beds, status life | `/admin/boarders…`, `/admin/rooms…`, `/admin/beds…` | `BoarderController`, `RoomController`, `BoarderProfile`, `Room`, `Bed` |
| M3 Payments + proof verifier (F7) | `/portal/payments`, `/admin/payments…` | `PaymentController`, `Payment`, `VerificationClient`, `verification.py` |
| M4 Expenses (F4), penalties (F9), ledger (F5) | `/admin/expenses`, `/admin/penalty-rules…`, `/admin/penalties/run-check`, `/admin/ledger/export` | `ExpenseController`, `PenaltyController`, `PenaltyEngine`, `LedgerBuilder` |
| M5 Maintenance + AI priority (F1) | `/portal/maintenance`, `/staff/maintenance…` | `MaintenanceController`, `ScoringClient`, `scoring.py` |
| M6 SOS + incidents (F2) | `/api/sos`, `/api/sos/active`, `/staff/sos…`, `/staff/incidents…` | `SosController`, `IncidentController` |
| M7 Command center (F3) + occupancy trend (F6) | `/admin/dashboard`, `/staff/dashboard`, `/portal/dashboard`, `/admin/occupancy` | `DashboardController`, `OccupancySnapshot` |
| M8 Notifications (F11) | `/notifications…`, `/api/notifications…` | `NotificationController`, `NotificationDispatcher` |
| M9 Profile | `/profile…` | `ProfileController` |

**Roles.** `admin` gets everything, including the staff pages. `staff` gets maintenance, incidents and SOS. `boarder` gets the portal (own dashboard, repair request, payment, SOS). There is no public sign-up; the admin creates boarders.

**Conventions and rules discovered** (sources: `CLAUDE.md`, `ARCHITECTURE.md`, `PROJECT_STRUCTURE.md`, `.claude/rules/php-conventions.md`)
- Localhost only, no outbound calls, Python bound to 127.0.0.1.
- PDO prepared statements only; bcrypt; CSRF on every state-changing request; session regenerated on login; cookie `httponly` + `samesite=strict`.
- Fail closed: verifier down → payment `pending`; scorer down → `medium` + `scoring_pending`, plus an admin "rescore" action.
- Event records are **append-only, forever**: `payments`, `expenses`, `penalties`, `boarder_status_log`, `login_attempts`, `occupancy_snapshots`, `notifications`, `maintenance_requests`/`sos_alerts` (status transitions only).
- Configuration data is editable. Deletes are guarded in the model layer.
- Uploads: MIME / extension / size allow-list, generated filename, never executable.
- Config via `.env`; logs in `logs/app.log` and `logs/scoring_service.log`; Python errors shaped `{error:{code,message}}`.

**Assumptions I made (please correct if wrong)**
- *Inferred:* the "expected amount" for a payment should be the room's `base_price` per monthly `billing_period`. `ARCHITECTURE.md` §4.4 says "PHP looks up expected_amount for billing period", but there is no billing table.
- *Inferred:* the rent due day is the 5th for everyone (hard-coded in `PenaltyEngine`).
- *Inferred:* display time should be Asia/Manila (Philippine boardinghouse, ₱ currency). No document states a timezone.
- The prompt's generic placeholders (soft delete, masking, magic link) were checked against what this project actually specifies. Where the project specifies nothing (e.g. PII masking), I report it as Info, not a failure.

---

## 3. Test results matrix

### M1 — Auth, RBAC, sessions, QR login
| Test | Layer | Result | Label | Notes |
|---|---|---|---|---|
| Login redirects (admin/staff/boarder) | BE | PASS | EXECUTED | → correct dashboard each |
| Wrong pw vs unknown email: identical message | BE | PASS | EXECUTED | "Invalid email or password." |
| Session id regenerated on login | BE | PASS | EXECUTED | |
| Cookie flags | BE | PARTIAL | EXECUTED | `HttpOnly; SameSite=Strict`; no `Secure` (HTTP localhost, acceptable) |
| GET RBAC: 20 routes × {admin, staff, boarder, anon} | BE | PASS | EXECUTED | 403 / 302→/login exactly as designed |
| POST RBAC: 15 routes × roles | BE | PASS | EXECUTED | |
| Logout requires CSRF; old cookie dead afterwards | BE | PASS | EXECUTED | |
| Lockout after 5 failures (incl. upper-case email) | BE | PASS | EXECUTED | |
| Password spraying (30 emails, 1 IP) throttled | BE | FAIL | EXECUTED | no per-IP limit (BE-17) |
| User-enumeration timing | BE | FAIL | EXECUTED | 63 ms known vs 4 ms unknown (BE-17) |
| Moved-out boarder blocked | BE | FAIL | EXECUTED | logs in, can pay (BE-06) |
| Deleted user's live session revoked | BE | FAIL | EXECUTED | portal 200; SOS → 500 (BE-06) |
| Password change kills other sessions | BE | FAIL | EXECUTED | (BE-19) |
| Password policy | BE | FAIL | EXECUTED | admin-created 1-char passwords accepted; `12345678` accepted (BE-19) |
| Idle timeout / absolute session lifetime | BE | FAIL | STATIC | none in code; relies on PHP GC (BE-19) |
| Demo passwords on login page | FE | FAIL | EXECUTED | (FE-01) |
| QR: claim before approve / other browser / replay / expired / tampered | BE | PASS | EXECUTED | backend logic sound |
| QR: usable from UI | FE | FAIL | EXECUTED | token never rendered; QR encodes plain `/login` (BE-22) |
| QR under XAMPP timezones | BE | FAIL | EXECUTED | always "expired" (BE-14) |
| Anonymous GET /login writes DB row | DB | FAIL | EXECUTED | 25 GETs → +25 rows, no purge (BE-22) |
| Security headers | BE | FAIL | EXECUTED | no CSP / XFO / nosniff; `X-Powered-By: PHP/8.3.6` (BE-24) |
| CSRF token rotation | BE | INFO | STATIC | per-session token; acceptable with SameSite=Strict |

### M2 — Boarders, rooms, beds, status life
| Test | Layer | Result | Label | Notes |
|---|---|---|---|---|
| Assign occupied bed rejected | BE | PASS | EXECUTED | |
| 8 parallel workers → same bed | BE/DB | PASS | EXECUTED | `SELECT … FOR UPDATE`: 1 winner, 7 rejected |
| Reassign boarder frees old bed | BE/DB | FAIL | EXECUTED | boarder 3 held beds 1 and 3 (BE-05) |
| Same boarder, 2 beds in parallel | BE/DB | FAIL | EXECUTED | both succeed (BE-05, DB-02) |
| Assign staff account to bed | BE | FAIL | EXECUTED | bed occupied by `staff` user (BE-05) |
| Assign non-existent boarder | BE | PARTIAL | EXECUTED | blocked by FK, but raw SQL error shown (BE-08) |
| Invalid status value | BE | PARTIAL | EXECUTED | not stored; raw `SQLSTATE[01000]` flashed (BE-08) |
| moved_out → active with no bed | BE | FAIL | EXECUTED | any → any allowed (BE-13) |
| moved_out frees bed + logs who/when/why | BE/DB | PASS | EXECUTED | |
| Create boarder: invalid email / 1-char pw | BE | FAIL | EXECUTED | accepted (BE-19, BE-20) |
| Create boarder: 151-char name | BE | PASS | EXECUTED | rejected |
| Create boarder: unicode name `José Ñoño 李` | BE | PASS | EXECUTED | stored and rendered |
| Create boarder on occupied bed | BE/DB | FAIL | EXECUTED | user created with no profile (BE-10) |
| Room negative price / beds > capacity / price > DECIMAL | BE | FAIL | EXECUTED | -500 stored; 6 beds in cap-1 room; HTTP 500 (BE-09, BE-21) |
| Room update without base_price | BE | FAIL | EXECUTED | price silently set to 0.00 + PHP warning (BE-21, BE-23) |
| `/admin/boarders/{id}/info` on staff id | BE | FAIL | EXECUTED | staff renamed (BE-20) |
| Delete boarder with history | DB | FAIL | EXECUTED | 2 payments, 1 penalty, 1 maint, 1 SOS, 1 log deleted (BE-02) |
| Delete-boarder route on staff id | BE | PASS | EXECUTED | refused |
| Restore deleted boarder | BE | FAIL | STATIC | no soft delete, no route (DB-04) |
| Room delete guarded when beds exist | BE | PASS | STATIC | `Room::delete()` |
| Automatic status transitions (move-in date, severe penalty) | BE | FAIL | STATIC | not implemented (F10) |

### M3 — Payments and proof verifier
| Test | Layer | Result | Label | Notes |
|---|---|---|---|---|
| expected_amount=1, claimed=1 → auto-matched | BE | FAIL | EXECUTED | **Critical** (BE-01) |
| Auto-match with no proof | BE | FAIL | EXECUTED | (BE-01) |
| claimed ≠ expected → flagged | BE | PASS | EXECUTED | |
| Verifier down → pending | BE | PASS | EXECUTED | fail-closed holds |
| Duplicate payment same period (sequential, 5 parallel) | BE/DB | FAIL | EXECUTED | 5/5 auto-matched (BE-07) |
| billing_period `9999-99` | BE | FAIL | EXECUTED | stored (BE-09) |
| billing_period > 20 chars | BE | FAIL | EXECUTED | HTTP 500 (BE-09) |
| Amount 1e12 | BE | FAIL | EXECUTED | HTTP 500 (BE-09) |
| Amount `NaN` / ≤ 0 | BE | PASS | EXECUTED | rejected |
| Rounding 4000.005 vs 4000.004 | BE | INFO | EXECUTED | stored 4000.01 / 4000.00 yet `auto-matched` (BE-09) |
| Stored XSS via billing_period on 3 pages | FE | PASS | EXECUTED | escaped |
| Valid PNG stored with random name | BE | PASS | EXECUTED | |
| PHP with spoofed MIME / SVG | BE | PASS | EXECUTED | rejected |
| JPEG/PHP polyglot | BE | PASS | EXECUTED | stored as `.jpg`, not executed |
| Receipt readable without login | BE | FAIL | EXECUTED | anon 200 (BE-15) |
| 3 MB proof (> `upload_max_filesize` 2M) | BE | FAIL | EXECUTED | silently dropped; payment auto-matched with no proof (BE-16) |
| rejected → admin-approved flip | BE | FAIL | EXECUTED | (BE-13) |
| Approve non-existent id | BE | INFO | EXECUTED | silent no-op |
| Boarder notified of verification result | BE | FAIL | EXECUTED | 0 notifications (BE-18) |
| Moved-out / roomless boarder pays ₱1 → auto-matched | BE | FAIL | EXECUTED | (BE-01, BE-06) |

### M4 — Expenses, penalties, ledger
| Test | Layer | Result | Label | Notes |
|---|---|---|---|---|
| 3 days late × ₱5 = ₱15 (single run) | BE | PASS | EXECUTED | |
| Same-day re-run | BE | FAIL | EXECUTED | ₱30 total (BE-03) |
| Daily runs day 6/7/8 | BE | FAIL | EXECUTED | 5+10+15 = ₱30 (BE-03) |
| Fake ₱1 payment suppresses penalty | BE | FAIL | EXECUTED | boarder skipped (BE-01) |
| Unpaid prior month carried forward | BE | FAIL | EXECUTED | Dec run ignores unpaid Nov (BE-25) |
| Non-`late_per_day` rule fires | BE | FAIL | STATIC | `PenaltyEngine.php:32` (BE-25) |
| Rule amount ≤ 0 rejected | BE | PASS | EXECUTED | |
| Run-check button before due day | BE | PASS | EXECUTED | 0 applied on day 4 |
| Rent-due reminder dedupe | BE | FAIL | EXECUTED | 2 clicks → 2 notifications (BE-18) |
| Ledger TOTAL vs raw SQL | BE | FAIL | EXECUTED | 36,006 + 1,011 + 15 = 37,032 shown; net cash is 34,995 (BE-04) |
| Ledger `to=YYYY-MM-DD` | BE | FAIL | EXECUTED | 0 rows vs 16 (BE-27) |
| Ledger `from[]=x` | BE | FAIL | EXECUTED | HTTP 500 (BE-27) |
| Ledger invalid / reversed dates | BE | INFO | EXECUTED | empty CSV, no error |
| Ledger CSV formula injection | BE | PASS | STATIC | only numeric/system columns exported |
| Negative expense rejected | BE | PASS | EXECUTED | |
| Expense amount 1e11 | BE | FAIL | EXECUTED | HTTP 500 (BE-09) |
| Expense without description (display_errors=On) | BE | FAIL | EXECUTED | path-disclosing warning (BE-23) |
| Expense XSS on expenses + dashboard | FE | PASS | EXECUTED | escaped |
| Staff can log expenses (spec F4) | BE | FAIL | EXECUTED | route is admin-only (403 for staff) |

### M5 — Maintenance and priority scoring
| Test | Layer | Result | Label | Notes |
|---|---|---|---|---|
| "gas leak smell in kitchen" → critical | BE | PASS | EXECUTED | but matched `gas leak` + `gas` + `leak` (double count) |
| "squeaky door hinge" [structural] → low | BE | FAIL | EXECUTED | medium (27) (BE-12) |
| "squeaky cabinet… scratch on the carpet" | BE | FAIL | EXECUTED | medium: `car` ⊂ carpet (BE-12) |
| "paint near my fireplace" | BE | FAIL | EXECUTED | high: `fire` ⊂ fireplace (BE-12) |
| "no smoke and no fire, just a dripping tap" | BE | FAIL | EXECUTED | **critical** (87) (BE-12) |
| "squeaky door hinge EMERGENCY" | BE | FAIL | EXECUTED | critical: trivially gamed (BE-12) |
| Scorer down → medium + scoring_pending | BE | PASS | EXECUTED | |
| Rescore action / time decay | BE | FAIL | STATIC | absent; `hours_since_submission` always 0 (BE-12) |
| Invalid category | BE | FAIL | EXECUTED | HTTP 500 (BE-09) |
| Boarder picks arbitrary room_id | BE | FAIL | EXECUTED | (BE-11) |
| 70 KB description | BE | FAIL | EXECUTED | HTTP 500 (BE-09) |
| XSS in description on 5 pages | FE | PASS | EXECUTED | escaped |
| Invalid status | BE | FAIL | EXECUTED | HTTP 500 (BE-09) |
| Resolve twice | BE | FAIL | EXECUTED | 2 notifications (BE-13) |
| resolved → open | BE | INFO | EXECUTED | allowed; resolver wiped (BE-13) |
| Media readable without login | BE | FAIL | EXECUTED | (BE-15) |
| Double submit (HTTP) | BE | FAIL | EXECUTED | +2 rows |
| Double-click in browser | FE | PASS | EXECUTED | +1 row |

### M6 — SOS and incidents
| Test | Layer | Result | Label | Notes |
|---|---|---|---|---|
| SOS → staff feed with room | BE | PASS | EXECUTED | |
| Ack → boarder notified | BE | PASS | EXECUTED | |
| Ack after resolve | BE | FAIL | EXECUTED | re-opens to `acknowledged`, 2nd notification (BE-13) |
| who resolved / when acknowledged stored | DB | FAIL | EXECUTED | columns missing (DB-09) |
| Staff/admin notified of new SOS | BE | FAIL | EXECUTED | 0 rows; 10 s dashboard poll only (BE-18) |
| Staff poll detects change | FE | PARTIAL | STATIC | reload only if *count* changes; resolve-one + new-one in same 10 s is missed |
| SOS spam | BE | INFO | EXECUTED | 15 taps → 15 alerts, no confirm (FE-08) |
| Incident XSS | FE | PASS | EXECUTED | escaped |
| Re-resolve incident | BE | FAIL | EXECUTED | notes overwritten (BE-13) |
| 70 KB incident | BE | FAIL | EXECUTED | HTTP 500 (BE-09) |
| "People involved" field (spec F2) | DB | FAIL | STATIC | no column (DB-09) |

### M7 — Dashboards and occupancy
| Test | Layer | Result | Label | Notes |
|---|---|---|---|---|
| Admin dashboard: pending/flagged payments == SQL | FE/BE | PASS | EXECUTED | |
| Open maintenance == SQL | FE/BE | PASS | EXECUTED | 9 = 9 |
| Active SOS == SQL | FE/BE | PASS | EXECUTED | 17 = 17 |
| Occupancy == SQL | FE/BE | PASS | EXECUTED | 4/11 matches beds table; true occupants were 3 (phantom bed, BE-05) |
| Occupancy trend continuity | BE | FAIL | EXECUTED | snapshot only when an admin opens the page (DB-12) |

### M8 — Notifications
| Test | Layer | Result | Label | Notes |
|---|---|---|---|---|
| Boarder reads/pins/deletes staff notification | BE | PASS | EXECUTED | blocked; API 403 |
| Unread API scoped to user | BE | PASS | EXECUTED | |
| Read-all scoped to user | BE | PASS | EXECUTED | |
| Hard delete of an "append-only" table | DB | FAIL | EXECUTED | row physically deleted (DB-01) |
| Dropdown escapes message (`escapeHtml`) | FE | PASS | STATIC | `nav.php:393,513` |

### M9 — Profile
| Test | Layer | Result | Label | Notes |
|---|---|---|---|---|
| Take another user's email | BE | PASS | EXECUTED | rejected |
| 60-char contact number | BE | FAIL | EXECUTED | HTTP 500 (BE-09) |
| Contact number format | BE | FAIL | EXECUTED | `abc<>!!` stored |
| Wrong current password | BE | PASS | STATIC | message shown, no change |

### Cross-cutting security
| Test | Layer | Result | Label | Notes |
|---|---|---|---|---|
| CSRF: forged token on 36 POST routes | BE | PASS | EXECUTED | all 12 business-table checksums unchanged |
| SQLi: all queries prepared | BE | PASS | STATIC | one interpolated identifier from a hard-coded array (`BoarderProfile.php:143`) |
| SQLi via login form / route ids | BE | PASS | EXECUTED | |
| `.env` / `src/` not served | BE | PASS | EXECUTED | 404; `/.htaccess` served 200 under `php -S` (harmless) |
| Verbose errors (display_errors=Off) | BE | PASS | EXECUTED | generic 500 message |
| Verbose errors (display_errors=On, XAMPP default) | BE | FAIL | EXECUTED | absolute paths leaked (BE-23) |
| Logs free of passwords | BE | PASS | EXECUTED | emails logged on failure (BE-29) |
| Outbound internet calls | FE | FAIL | STATIC | `<noscript>` QR via api.qrserver.com (FE-07) |
| Insecure deserialization / SSRF | BE | PASS | STATIC | no `unserialize`; service URL from env only |
| Dependency vulnerabilities | — | NOT TESTED | — | no lockfile; FastAPI/uvicorn/pydantic pinned only to minor (`0.115.*`); no audit tool run offline |

### Python scoring service
| Test | Layer | Result | Label | Notes |
|---|---|---|---|---|
| Binds 127.0.0.1 only | BE | PASS | EXECUTED | `ss -tlnp` |
| Unknown category accepted | BE | FAIL | EXECUTED | scored as base 5 (BE-26) |
| Negative hours | BE | FAIL | EXECUTED | score −440 (BE-26) |
| Negative amounts auto-match | BE | FAIL | EXECUTED | PHP guards >0; service doesn't (BE-26) |
| Malformed JSON → 4xx | BE | PARTIAL | EXECUTED | 422 `{detail:[…]}`, not the `{error:{…}}` contract |
| `/docs` exposed | BE | INFO | EXECUTED | 200 (localhost only) |
| Service log file | BE | FAIL | EXECUTED | no `logs/scoring_service.log` |

### Database
| Test | Layer | Result | Label | Notes |
|---|---|---|---|---|
| Fresh install, 17 migrations in order | DB | PASS | EXECUTED | |
| Re-run migrations | DB | FAIL | EXECUTED | 16/17 fail; no tracking table (DB-05) |
| Rollback / down migrations | DB | FAIL | STATIC | none exist (DB-05) |
| Fresh vs upgraded install equivalence | DB | NOT TESTED | — | only one schema version exists |
| PK on every table | DB | PASS | EXECUTED | |
| Charset / collation consistent | DB | PASS | EXECUTED | utf8mb4_general_ci everywhere (server default, not pinned) |
| Triggers / CHECK constraints | DB | FAIL | EXECUTED | 0 and 0 (DB-01, DB-03) |
| App user can UPDATE/DELETE audit rows | DB | FAIL | EXECUTED | rewrote a payment, deleted the status log (DB-01) |
| Impossible-state queries (10) | DB | FAIL | EXECUTED | 9 of 10 non-zero (DB-02) |
| Password hashes | DB | PASS | EXECUTED | `$2y$10$` bcrypt |
| Least privilege | DB | FAIL | EXECUTED | ALL PRIVILEGES (DB-06) |
| EXPLAIN ledger / queue | DB | INFO | EXECUTED | full scans + filesort (DB-08) |
| EXPLAIN login lockout / QR lookup | DB | PASS | EXECUTED | index range / const |
| Rollback on mid-transaction failure (status change) | DB | PASS | EXECUTED | implicit (connection close), not explicit |
| Rollback on failure in boarder create | DB | FAIL | EXECUTED | user + occupied bed left, no profile (BE-10) |
| DB down | BE | PASS | EXECUTED | login page degrades; warning logged, no secrets |
| Backup / recovery | DB | FAIL | STATIC | no backup script or docs |

### Front-end (real browser)
| Test | Layer | Result | Label | Notes |
|---|---|---|---|---|
| 27 page/role combos × 2 widths (54 loads): console errors | FE | PASS | EXECUTED | 0 |
| Failed network requests / broken assets | FE | PASS | EXECUTED | 0 |
| Internal links resolve | FE | PASS | EXECUTED | 21 distinct links, all valid routes |
| Every POST form has CSRF field | FE | PASS | EXECUTED | |
| Role-based nav hides other roles | FE | PASS | EXECUTED | |
| Label association | FE | FAIL | EXECUTED | (FE-04) |
| Duplicate ids | FE | FAIL | EXECUTED | `#notif-dropdown` (FE-04) |
| Image alt | FE | PARTIAL | EXECUTED | QR `<img>` has no alt |
| `lang` attribute, single `<h1>` per page | FE | PASS | EXECUTED | |
| 375 px horizontal overflow | FE | FAIL | EXECUTED | payments 27 px, expenses 6 px (FE-05) |
| Keyboard tab order on login | FE | PASS | EXECUTED | email → password → toggle → submit → chips |
| Input preserved after server error | FE | FAIL | EXECUTED | (FE-03) |
| Expired session mid-form | FE | FAIL | EXECUTED | text lost, no return-to (FE-03) |
| Oversized upload message | FE | FAIL | EXECUTED | "Invalid session, please retry." (FE-03) |
| Times in Asia/Manila | FE | FAIL | EXECUTED | 3:31 pm shown for 11:31 pm Manila (BE-14) |
| Currency formatting | FE | PASS | STATIC | consistent `₱` + `number_format` |
| PII masking | FE | INFO | EXECUTED | staff see boarder emails; no policy defined (FE-09) |
| Page load time | FE | PASS | EXECUTED | ~550 ms incl. 500 ms network-idle wait |
| Colour contrast | FE | NOT TESTED | — | no axe-core offline; visual review needed |
| Screen-reader pass | FE | NOT TESTED | — | no assistive tech in sandbox |
| Visual layout / GSAP animations | FE | NOT TESTED | — | headless only; no visual baseline |

### Existing automated tests
| Test | Layer | Result | Label | Notes |
|---|---|---|---|---|
| `tests/playwright/phase8_full_suite.py` as shipped | FE | FAIL | EXECUTED | **0/8 pass**: all click `button:has-text("Sign in")`, but the button reads "Log in" (FE-02) |
| Same suite, scratch copy with only login selector fixed | FE | PARTIAL | EXECUTED | 3/8 pass; others fail on stale selectors, duplicate id, missing "Open Requests" label |
| Unit tests (PHP/Python) | — | FAIL | STATIC | none exist |

### Not tested (environment limits)
| Item | Reason |
|---|---|
| Apache + `.htaccess` rewrite / XAMPP on Windows | Linux sandbox; used `php -S` as documented |
| MySQL 8 compatibility | README claims MySQL 8+; only MariaDB 10.11 available |
| Concurrency through a multi-worker web server | `php -S` serialises requests; races were instead run with 8 parallel PHP CLI workers calling the app's models |
| Session GC expiry | time-based; not practical to wait out |

---

## 4. Findings

Effort: **S** ≤ ½ day · **M** 1–3 days · **L** > 3 days.

### BACK-END

**BE-01 — Boarder supplies the expected rent amount; ₱1 payments auto-approve** · **Critical** · M3/F7
- **Location:** `src/Controllers/PaymentController.php:45-69`; `src/Views/portal/payment_new.php:117-130` (editable `expected_amount` input)
- **Repro [EXECUTED]:** As `boarder@rjm.test`, POST `/portal/payments` with `billing_period=2026-10&expected_amount=1&claimed_amount=1` and no file. The row is stored with `expected 1.00 / claimed 1.00 / auto-matched / proof NULL`. It appears in the ledger as rent, and `PenaltyEngine` skips the boarder for 2026-10.
- **Expected:** expected amount computed server-side from the boarder's room/bed and period; no auto-approval without proof. **Actual:** the client value is trusted, and proof is optional.
- **Why it matters:** direct revenue loss; it defeats late fees and makes the ledger untrustworthy.
- **Fix:** Ignore `$_POST['expected_amount']`. Load `rooms.base_price` via `boarder_profiles.room_id`, and reject boarders without an active profile/room. Require `proof` for any status other than `pending`. Validate `billing_period` with `/^\d{4}-(0[1-9]|1[0-2])$/`. Make the form field read-only display text. Long-term, introduce a `charges` (invoice) table so the expected amount is a stored fact. **M**

**BE-02 — "Delete boarder" hard-deletes financial and safety history** · **Critical** · M2
- **Location:** `src/Models/BoarderProfile.php:111-156`
- **Repro [EXECUTED]:** Boarder with 2 payments, 1 penalty, 1 repair, 1 SOS and 1 status-log row → POST `/admin/boarders/{id}/delete`. Counts go `2 1 1 1 1 → 0 0 0 0 0`, the user row is deleted, and the all-time ledger TOTAL falls ₱44,057 → ₱37,032.
- **Expected:** `PROJECT_STRUCTURE.md`: payments, penalties and status log are "append-only, forever. No edit, no delete, not even for admins." **Actual:** a single click erases them, and also deletes `incidents WHERE reported_by = boarder`.
- **Why it matters:** unrecoverable loss of money records and the safety audit trail; past ledgers change retroactively.
- **Fix:** Replace with deactivation: `users.deleted_at DATETIME NULL` (or reuse the unused `users.status`), vacate the bed, set the profile to `moved_out`, block login. Never delete child rows. Add a restore action. If true erasure is ever needed, gate it behind "no financial rows exist". **M**

**BE-03 — Late-fee engine is non-idempotent and charges cumulatively** · **High** · M4/F9
- **Location:** `src/Services/PenaltyEngine.php:41-51`
- **Repro [EXECUTED]:** Run `PenaltyEngine::runCheck(2026-10-08)` twice → two ₱15 rows (₱30). Run daily on 11-06, 11-07, 11-08 → rows ₱5, ₱10, ₱15 = ₱30 for 3 days late (correct: ₱15). After 25 days late that is ₱1,625 instead of ₱125.
- **Why it matters:** overbilling tenants; disputes; the ledger includes inflated penalties.
- **Fix:** Add `billing_period` to `penalties` and `UNIQUE(boarder_id, rule_id, billing_period)`. Use `INSERT … ON DUPLICATE KEY UPDATE amount = VALUES(amount), reason = VALUES(reason)` so the row always reflects `rate × days_late`. Wrap per-boarder writes + notification in a transaction. Notify only when the amount changes. **S–M**

**BE-04 — Ledger TOTAL adds expenses (and penalties) to income** · **High** · M4/F5
- **Location:** `src/Services/LedgerBuilder.php:26-32`
- **Repro [EXECUTED]:** Payments 36,006 + expenses 1,011 + penalties 15 → CSV `TOTAL 37,032`. Net cash is 34,995 (35,010 if the ₱15 penalty is treated as income).
- **Fix:** Emit a signed `direction` column (in/out) and separate subtotals: `Rent received`, `Penalties assessed` (receivable, not cash), `Expenses`, `Net`. Compute with `SUM(CASE …)` in SQL, not floats. **S**

**BE-05 — Bed reassignment leaves phantom occupied beds; non-boarders assignable** · **High** · M2/F8
- **Location:** `src/Controllers/BoarderController.php:94-113`, `src/Models/Bed.php:76-97`
- **Repro [EXECUTED]:**
  1. Boarder 3 occupies bed 1. POST `/admin/beds/assign boarder_id=3&bed_id=3` → beds 1 *and* 3 are both `occupied/3`.
  2. `boarder_id=2` (staff) → bed occupied by staff.
  3. Two parallel assigns of one boarder to two beds both succeed.
- **Why it matters:** occupancy KPIs, the vacancy list and the trend chart are inflated. A vacant bed can't be rented, and the stale bed can't be deleted ("occupied").
- **Fix:** In one transaction: `SELECT … FROM boarder_profiles WHERE user_id=? FOR UPDATE`; verify `users.role='boarder'` and status in (`pending`,`active`,`on_notice`); lock the target bed; vacate the old bed; assign the new one; update the profile. Back it with DB-02 constraints. **M**

**BE-06 — Moved-out and deleted users keep access** · **High** · M1/F10
- **Location:** `src/Controllers/AuthController.php:186-207`, `src/Middleware/AuthMiddleware.php:8-14`
- **Repro [EXECUTED]:**
  1. Set `moved@test.ph` to `moved_out` → login succeeds → `/portal/payments/new` 200 → a ₱1 payment auto-matched.
  2. Delete `del2@test.ph` while their session is open → `/portal/dashboard` still 200; `POST /api/sos` → 500 (FK error) with a plain-text body on a JSON endpoint.
- **Fix:** Reject login for deactivated users and `moved_out` boarders. In `AuthMiddleware`, reload the user (one indexed PK lookup), destroy the session if missing or deactivated, and refresh `$_SESSION['role']`. **S**

**BE-07 — No duplicate-payment protection** · **High** · M3
- **Repro [EXECUTED]:** Two sequential identical payments for 2026-10 both auto-matched. 5 parallel workers → 5 auto-matched rows for one boarder/period.
- **Fix:** Before insert, reject when a `pending`/`auto-matched`/`admin-approved` payment exists for (boarder, period), under `SELECT … FOR UPDATE` on the profile row. Add a DB guard: generated column `active_key = IF(verification_status IN ('pending','auto-matched','admin-approved'), CONCAT(boarder_id,'|',billing_period), NULL)` with a UNIQUE index. **S**

**BE-08 — Raw SQL error text shown to users** · **Medium** · M2
- **Location:** `BoarderController.php:84-88, 103-109`. `catch (RuntimeException)` also catches `PDOException`, which extends `RuntimeException`.
- **Repro [EXECUTED]:** invalid status → flash `SQLSTATE[01000]: Warning: 1265 Data truncated for column 'status'`; non-existent boarder → full FK constraint text including the schema name.
- **Fix:** Throw a domain exception (`App\Support\ValidationException`) for user-facing errors. Catch only that. Let PDO errors reach the global handler (logged, generic message). **S**

**BE-09 — Missing server-side validation causes HTTP 500s and bad data** · **Medium** · M2–M9
- **Repro [EXECUTED]** (all HTTP 500 unless noted):
  - Maintenance `category=hacker`; maintenance `status=bogus`; 70 KB description (TEXT overflow).
  - Payment amount `1e12`; expense `1e11`; room `base_price=99999999999`.
  - `billing_period` > 20 chars.
  - Profile contact number 60 chars (the admin route checks ≤ 50, the profile route doesn't).
  - Stored but wrong: `billing_period=9999-99`.
  - Rounding: 4000.005 vs 4000.004 → auto-matched although the stored values differ.
- **Fix:** A small `Validator` helper used by every controller: enum whitelists (category, status), lengths matching column sizes, numeric ranges `0 < x ≤ 99,999,999.99`, round to 2 dp **before** verification, period regex. Mirror these with DB CHECKs (DB-03). **M**

**BE-10 — Multi-step writes not atomic** · **Medium** · M2
- **Location:** `BoarderController::create` (`:55-70`), `BoarderController::assignBed` (`:104-106`), `Bed::assign` and `BoarderProfile::updateStatus` (no `rollBack()` on exception)
- **Repro [EXECUTED]:**
  1. Create a boarder onto an occupied bed → user row created, no profile (orphan account that can log in).
  2. Inject a failure into the `boarder_profiles` insert (temporary test trigger) → HTTP 500, user created, bed 2 `occupied/13`, profile rows = 0.
- **Fix:** One `try { beginTransaction … commit } catch { rollBack; throw }` per use case, at the service level (`BoarderService::register/assignBed/changeStatus`), with models not opening their own transactions. **M**

**BE-11 — Boarder chooses the room on repair requests** · **Medium** · M5
- **Location:** `MaintenanceController.php:74`
- **Repro [EXECUTED]:** boarder in room 201 posts `room_id=4` → the request is attributed to room 203.
- **Fix:** Derive `room_id` from `boarder_profiles`; ignore POST. **S**

**BE-12 — Priority scoring is easily wrong or gamed; time decay and rescore are missing** · **Medium** · M5/F1
- **Location:** `scoring_service/scoring.py:113-115`; `MaintenanceController.php:70`; `MaintenanceRequest::queueSorted()`
- **Repro [EXECUTED]:**
  - "no smoke and no fire, just a dripping tap" → **critical 87**.
  - "paint near my fireplace" → high (`fire`).
  - "scratch on the carpet" → `car` (+25).
  - "…EMERGENCY" → critical.
  - "gas leak" matched 3 overlapping keywords.
  - "squeaky door hinge" [structural] → medium.
  - Queue sorts by tier then age; `severity_score` is unused, and `hours_since_submission` is always 0, so time decay never applies.
  - `scoring_pending` items are never rescored (ARCHITECTURE §3.2 requires a manual rescore).
- **Fix:** Word-boundary regex (`\bfire\b`); remove overlapping matches (longest-first); cap the keyword contribution; simple negation window ("no", "not" within 3 tokens). Treat self-declared urgency words as weak signals. Compute decay at read time (`score + LEAST(TIMESTAMPDIFF(HOUR, created_at, NOW())*0.5, 20)`) and sort by it. Add `POST /staff/maintenance/{id}/rescore`. **M**

**BE-13 — Status lifecycles are not enforced** · **Medium** · M3, M5, M6, M2
- **Repro [EXECUTED]:**
  - Payment `rejected → admin-approved`.
  - SOS `resolved → acknowledged` (re-opened, duplicate notification).
  - Maintenance resolved twice (2 notifications), and `resolved → open` wipes `resolved_by`.
  - Incident re-resolved (notes overwritten).
  - Boarder `moved_out → active` with no bed.
- **Fix:** Conditional updates, e.g. `UPDATE sos_alerts SET status='acknowledged', acknowledged_by=? WHERE id=? AND status='active'`, then check `rowCount()===1`, else flash "already handled". Define allowed transition maps for boarder status (FEATURES §10). **S–M**

**BE-14 — No timezone handling: UI shows UTC, day boundaries wrong, QR breaks on XAMPP** · **Medium** · cross-cutting
- **Location:** no `date_default_timezone_set` and no `SET time_zone` anywhere. `QrLoginChallenge.php:14` writes the PHP-time `expires_at` and compares it with DB `NOW()` (`:55`).
- **Repro [EXECUTED]:**
  1. A notification created at 15:31 UTC is displayed as "3:31 pm"; Manila time was 11:31 pm.
  2. PHP `Europe/Berlin` (XAMPP default) + DB `+08:00`: `expires_at=17:34`, `NOW()=23:32`. The status page says "pending" but approval always fails.
  3. [STATIC] `PenaltyEngine` and `date('Y-m')` (rent-due, ledger defaults) use the PHP zone, so in Manila, 00:00–07:59 on the 6th still counts as the 5th.
- **Fix:** In the front controller, `date_default_timezone_set('Asia/Manila')` for display and business dates. On connect, `SET time_zone = '+00:00'` and store UTC. Format via a single `Fmt::dt()` helper. Compute QR expiry in SQL (`NOW() + INTERVAL 120 SECOND`). **S**

**BE-15 — Receipts and repair photos are public** · **Medium** · M3/M5
- **Location:** `src/Support/Uploads.php:39` (stores under `public/uploads/`)
- **Repro [EXECUTED]:** `GET /uploads/receipts/<name>.png` without a session → 200. Names are 128-bit random (hard to guess), but they leak via browser history, logs and shared screenshots, and there is no revocation. The `uploads/.htaccess` mentioned in PHASES.md is not in the repo (gitignored).
- **Fix:** Store under `storage/uploads/` outside `public/`. Serve via `GET /files/{id}`, which checks owner/admin/staff, then `readfile()` with `Content-Type` from DB and `X-Content-Type-Options: nosniff`. **M**

**BE-16 — Upload errors silently ignored** · **Medium** · M3/M5
- **Location:** `Uploads.php:22-24` (returns null for any `UPLOAD_ERR_*`)
- **Repro [EXECUTED]:**
  - A 3 MB proof with stock PHP `upload_max_filesize=2M` → silently dropped; payment **auto-matched with no proof**.
  - A 9 MB upload (> `post_max_size`) → `$_POST` empty → "Invalid session, please retry." and the form is lost.
- **Fix:** Map `UPLOAD_ERR_INI_SIZE`, `FORM_SIZE` and `PARTIAL` to a user-facing error. Detect `CONTENT_LENGTH > post_max_size` before the CSRF check. Align the app's 20 MB limit with ini values (document `php.ini`). **S**

**BE-17 — Brute-force protection is per-email only; timing reveals valid accounts** · **Medium** · M1
- **Repro [EXECUTED]:** 30 failed logins against 30 different emails from one IP → no throttle. Median response is 63 ms for an existing email vs 4 ms for an unknown one (no `password_verify` call).
- **Fix:** Add a per-IP window (e.g. 20 failures / 15 min). On unknown email, run `password_verify($pw, DUMMY_HASH)` for constant time. Purge old `login_attempts`. **S**

**BE-18 — Notification coverage gaps and duplicates** · **Medium** · M8/F11
- **Repro [EXECUTED]:** payment verification and admin approve/reject → 0 notifications. New SOS → 0 notifications to staff/admin (only a 10 s dashboard poll, visible only while that page is open). Rent-due reminder clicked twice → 2 reminders.
- **Fix:** Add `paymentVerified/Rejected`, `sosTriggered` (fan-out to staff+admin), `maintenanceStatusChanged`. Dedupe rent-due with a `(user_id, type, ref)` unique key. **S**

**BE-19 — Weak password and session policy** · **Low** · M1
- **Repro [EXECUTED]:** admin-created accounts accept a 1-char password. The profile minimum is 8 chars (`12345678` accepted). Changing the password leaves other sessions logged in. No idle or absolute timeout [STATIC].
- **Fix:** Shared policy (≥ 10 chars, not in a small deny-list) for both paths. Store `password_changed_at` and compare in middleware. Add `$_SESSION['last_seen']` with a 30-min idle and 12-h absolute limit. **S**

**BE-20 — Admin "boarder" endpoints act on any account; no email validation** · **Low** · M2
- **Repro [EXECUTED]:** `/admin/boarders/2/info` renamed the staff account. `not-an-email` accepted on create.
- **Fix:** Guard `role='boarder'` in `updateInfo`; `FILTER_VALIDATE_EMAIL` on admin create/update. **S**

**BE-21 — Room and bed configuration not validated** · **Low** · M2
- **Repro [EXECUTED]:** `base_price=-500` stored. Six beds created in a capacity-1 room. A room update without `base_price` sets the price to 0.00.
- **Fix:** `base_price ≥ 0` and required; bed count ≤ capacity (or drop `capacity` and derive it from beds). **S**

**BE-22 — QR login feature is unreachable but still writes on every anonymous page view** · **Low** · M1
- **Location:** `AuthController::showLogin` (`:24-30`), `login.php:437, 488-500`
- **Repro [EXECUTED]:** the login HTML contains no challenge token (the QR encodes plain `/login`), so the flow can't be completed from the UI. Each anonymous `GET /login` inserts a `qr_login_challenges` row (25 GETs → 25 rows), never purged.
- **Fix:** Remove the feature, or finish it: create the challenge via an XHR when the QR panel is shown, render the token-URL QR, poll `/qr/{t}/status`, and purge expired rows. Show the approving device which account it is about to log in, to avoid QR-login hijacking. **S**

**BE-23 — PHP warnings disclose server paths under XAMPP defaults** · **Low** · M2/M4
- **Repro [EXECUTED]** (`display_errors=On`): `Warning: Undefined array key "base_price" in /home/user/…/RoomController.php on line 75`; the same for `ExpenseController.php:39`.
- **Fix:** `ini_set('display_errors','0')` in `public/index.php`, plus a `set_error_handler` that logs. Use `?? ''` for optional fields. **S**

**BE-24 — Missing security headers; version disclosure** · **Low** · cross-cutting
- **Repro [EXECUTED]:** no `Content-Security-Policy`, `X-Frame-Options`/`frame-ancestors`, `X-Content-Type-Options` or `Referrer-Policy`; `X-Powered-By: PHP/8.3.6`. FastAPI `/docs` is served.
- **Fix:** Send headers once in `public/index.php` (CSP `default-src 'self'`; inline scripts need nonces or should move to files). `expose_php=Off`. `FastAPI(docs_url=None, redoc_url=None)`. **S–M**

**BE-25 — Penalty rules: only one type works; arrears ignored** · **Low** · M4/F9
- **Repro:** [STATIC] `PenaltyEngine.php:32` only handles `late_per_day`; a `flat_damage` rule saves fine and never fires. [EXECUTED] A Dec-6 run penalised December only; unpaid November produced nothing. `DUE_DAY=5` is hard-coded.
- **Fix:** Make `condition_type` an enum with implemented handlers, or reject unknown types in the UI. Iterate all unpaid periods since `move_in_date`. Move the due day to config. **M**

**BE-26 — Scoring service accepts out-of-contract input** · **Low** · Python
- **Repro [EXECUTED]:** `category:"hacker"` → 200. `hours_since_submission:-1000` → score −440. Negative amounts → `auto-matched`. Errors return FastAPI's `{detail}`, not ARCHITECTURE §9's `{error:{code,message}}`. No `logs/scoring_service.log`.
- **Fix:** `category: Literal[...]`, `hours_since_submission: confloat(ge=0)`, `condecimal(gt=0, max_digits=10, decimal_places=2)` for amounts; a custom exception handler; a `logging` FileHandler. **S**

**BE-27 — Ledger export input handling** · **Low** · M4/F5
- **Location:** `LedgerController.php:11-12`
- **Repro [EXECUTED]:** `to=2026-10-04` → 0 rows (BETWEEN stops at 00:00:00) vs 16 rows with `23:59:59`. `from[]=x` → 500. Invalid or reversed dates → an empty CSV with no message. The UI offers no date-range picker (spec F5).
- **Fix:** Parse with `DateTimeImmutable::createFromFormat('Y-m-d')`, use `>= from AND < to + 1 day`, reject invalid input, and add from/to inputs. **S**

**BE-28 — `.env` is never loaded** · **Info**
- `Database.php:18-22` reads only `getenv()` and falls back to `root` with an empty password. `ARCHITECTURE.md` §9 and `.env.example` imply `.env` is read. Add a 10-line loader in `public/index.php` and `seed.php`, and remove the root fallback. **S**

**BE-29 — Logs contain attempted emails** · **Info**
- `AuthController.php:191, 201` log the raw email on every failure (48 lines in this run). A password typed into the email field would be logged. There is no rotation. Consider hashing or truncating, and add size-based rotation. **S**

### FRONT-END

**FE-01 — Real passwords (including admin's) embedded in the public login page** · **High**
- **Location:** `src/Views/shared/login.php:383-405` (`data-pass="AdminPass123!"` etc.)
- **Repro [EXECUTED]:** `curl /login | grep data-pass` shows all three seeded passwords. They are the live seed credentials, so anyone who can reach the login page is admin unless the password was changed.
- **Fix:** Remove the chips, or render them only when `APP_ENV=demo` **and** never with real passwords. Force a password change on first admin login. **S**

**FE-02 — Project's Playwright suite is stale and has never passed** · **Medium**
- **Repro [EXECUTED]:** `python3 tests/playwright/phase8_full_suite.py` on a fresh DB → **0/8**. Every script clicks `button:has-text("Sign in")`; the button reads "Log in" (`login.php:370`). A scratch copy with only that selector changed: 3/8 pass. The rest fail on a hidden create-boarder form, the missing "Open Requests" label and a duplicate `#notif-dropdown` (strict-mode violation). Both prior reports describe these tests as written but unexecuted, while also declaring the system READY.
- **Fix:** Use stable `data-testid` hooks; run the suite in a pre-commit or CI script; add PHPUnit tests for `PenaltyEngine`, `LedgerBuilder` and payment verification. **M**

**FE-03 — Input loss and misleading errors** · **Low**
- **Repro [EXECUTED]:** room create with a duplicate number → error shown, but the typed values are gone (redirect without old input). Session expires mid-form → redirect to `/login`, typed text discarded, no return-to. Upload > 8 MB → "Invalid session, please retry."
- **Fix:** Flash `$_SESSION['old']` and re-populate inputs; store an intended URL on auth redirect; see BE-16. **S–M**

**FE-04 — Accessibility basics** · **Low**
- **Repro [EXECUTED]:** visible inputs with no programmatic label on `/admin/boarders` (name, email, boarder_id, bed_id), `/admin/rooms` (8 fields), `/admin/expenses`, `/admin/penalty-rules`, `/staff/maintenance` (every row's status select), `/staff/incidents`, and the search boxes. The duplicate id `notif-dropdown` on `/notifications` (`nav.php:272` and `notifications.php:118`). The QR `<img>` has no `alt`.
- **Fix:** Add `for`/`id` pairs or `aria-label`. Rename the page-level container id. Set `alt` after QRCode.js renders. **S**

**FE-05 — Horizontal overflow on phones** · **Low**
- **Repro [EXECUTED]** at 375 px: `/admin/payments` +27 px, `/admin/expenses` +6 px.
- **Fix:** Wrap tables in `overflow-x-auto` and let filter button rows wrap. **S**

**FE-06 — Client-side constraints don't match server rules** · **Low**
- **Repro [EXECUTED]:** payment `billing_period` is `type=text` with no pattern; amounts have no `min`/`max`; the description has no `maxlength` (the server limit is TEXT 65,535 bytes → 500). The server must still validate (BE-09).
- **Fix:** `type="month"`, `min="0.01" max="99999999.99" step="0.01"`, `maxlength` equal to the column sizes. **S**

**FE-07 — `<noscript>` QR fallback calls a third-party internet API** · **Low**
- **Location:** `login.php:437` (`https://api.qrserver.com/…?data=<LAN URL>`)
- **Why it matters:** violates CLAUDE.md "No outbound internet calls" and leaks the LAN address. Only triggers with JS disabled.
- **Fix:** Drop the fallback image and show the URL text. **S**

**FE-08 — SOS fires on a single tap with no confirm or undo** · **Low**
- **Repro [EXECUTED]:** one click → alert; 15 rapid posts → 15 active alerts.
- **Fix:** A press-and-hold or confirm step, plus server-side dedupe ("you already have an active alert", returning the existing id). **S**

**FE-09 — No PII masking policy** · **Info**
- Staff see boarder emails in maintenance and incident history [EXECUTED]; contact numbers appear on profile pages. The project defines no masking rule, so this is a decision to make, not a defect.

### DATABASE

**DB-01 — "Append-only" is not enforced; the app itself deletes audit rows** · **High**
- **Repro [EXECUTED]:** `information_schema.triggers` = 0. As the app user, `UPDATE payments SET claimed_amount=999999` and `DELETE FROM boarder_status_log` both succeed. The app hard-deletes `notifications` (`NotificationController::delete`) and every child table in BE-02.
- **Fix (example):**
  ```sql
  CREATE TRIGGER bsl_no_update BEFORE UPDATE ON boarder_status_log FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'boarder_status_log is append-only';
  CREATE TRIGGER bsl_no_delete BEFORE DELETE ON boarder_status_log FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'boarder_status_log is append-only';
  -- payments: allow only status/verifier changes
  CREATE TRIGGER pay_guard BEFORE UPDATE ON payments FOR EACH ROW
    IF NEW.boarder_id<>OLD.boarder_id OR NEW.billing_period<>OLD.billing_period
       OR NEW.expected_amount<>OLD.expected_amount OR NEW.claimed_amount<>OLD.claimed_amount
       OR NOT (NEW.proof_path <=> OLD.proof_path) OR NEW.created_at<>OLD.created_at THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='payments are append-only';
    END IF;
  ```
  Repeat for `penalties`, `expenses` and `login_attempts`, plus `BEFORE DELETE` on each. Revoke `DELETE` on these tables from the app user (DB-06). Note that triggers don't fire on `TRUNCATE` or `DROP`; only privilege separation stops those. Turn notification "delete" into an `is_archived` flag. **M**

**DB-02 — Core occupancy invariants unenforced; 9 classes of corrupt rows found** · **High**
- **Repro [EXECUTED]** (counts in the test DB): boarder in > 1 bed (1); bed occupied by a non-boarder (1); bed occupied but the profile points elsewhere (2); boarder user without a profile (1); room beds > capacity (1); negative room price (1); duplicate verified payments per period (1); payment expected ≠ room price (11); maintenance room ≠ boarder's room (1).
- **Fix:**
  ```sql
  ALTER TABLE beds ADD UNIQUE KEY uq_beds_current_boarder (current_boarder_id),
    ADD CONSTRAINT chk_bed_occupancy CHECK ((status='occupied') = (current_boarder_id IS NOT NULL));
  ALTER TABLE boarder_profiles ADD UNIQUE KEY uq_profile_bed (bed_id);
  ```
  Clean existing rows first with the audit queries. **S**

**DB-03 — No CHECK constraints anywhere** · **Medium**
- **Repro [EXECUTED]:** `information_schema.check_constraints` = 0. Validation exists only in PHP and is incomplete (BE-09).
- **Fix:** `CHECK (amount > 0)` on payments, expenses, penalties and penalty_rules; `CHECK (base_price >= 0)`, `CHECK (capacity > 0)`; `CHECK (billing_period REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$')`; `CHECK (hours…)`. MariaDB ≥ 10.2 enforces these. **S**

**DB-04 — No soft delete or restore; `users.status` is dead** · **Medium**
- `users.status` (migration 0001) is never read or written [STATIC]. `boarder_profiles.move_in_date`/`move_out_date` and `maintenance_requests.assigned_staff_id` are never written either. Add `deleted_at DATETIME(6) NULL` to `users` (and `rooms`/`beds` if archival is wanted), filter lists with `deleted_at IS NULL`, and set `move_out_date` on move-out. **M**

**DB-05 — Migrations are not repeatable and have no history or rollback** · **Medium**
- **Repro [EXECUTED]:** re-applying `0001`–`0017`: 16/17 error (only `0013` uses `IF NOT EXISTS`). There's no `schema_migrations` table and no down scripts, and `seed.php` fails on a second run (duplicate email).
- **Fix:** A tiny `database/migrate.php` that records applied filenames in `schema_migrations` and runs each file in a transaction where DDL allows; `IF NOT EXISTS` guards; an idempotent seed (`INSERT IGNORE`/upsert). **S–M**

**DB-06 — Over-privileged DB account; root fallback** · **Medium**
- **Repro [EXECUTED]:** README/`.env.example` instruct `GRANT ALL PRIVILEGES` (includes DROP, ALTER, TRIGGER, which would let the app remove the DB-01 triggers). `Database.php:21-22` falls back to `root`/empty.
- **Fix:** A runtime user with `SELECT, INSERT, UPDATE` (plus `DELETE` only on `qr_login_challenges` and `notifications` if kept), and a separate migration user. Fail fast if `DB_USER` is unset. **S**

**DB-07 — Temporal types inconsistent** · **Low**
- 19 `TIMESTAMP` columns (second precision, 2038 limit, implicitly converted by session time_zone) vs 2 `DATETIME` columns (QR, no conversion). Mixed with BE-14 this gives the QR failure. Prefer `DATETIME(6)` in UTC with the session `time_zone='+00:00'`. **M**

**DB-08 — Missing indexes for date-range and queue queries** · **Low**
- **Repro [EXECUTED]:** EXPLAIN of the ledger UNION shows `type=ALL` on payments, expenses and penalties; the maintenance queue shows `ALL` + filesort. This is irrelevant at current volume but grows linearly.
- **Fix:** `payments(verification_status, created_at)`, `expenses(created_at)`, `penalties(applied_at)`, `maintenance_requests(status, priority_tier, created_at)`. `idx_maintenance_priority_status` leads with tier, so it isn't used by `status != 'resolved'`. **S**

**DB-09 — Schema gaps vs spec** · **Low**
- `sos_alerts` lacks `acknowledged_at` and `resolved_by` (spec: "who and when").
- `incidents` lacks "people involved" (spec F2).
- `payments` lacks `verified_at` and the verifier `reason`.
- `maintenance_requests` doesn't store `matched_keywords` (ARCHITECTURE §3.2: "so staff can see why").
- `boarder_profiles` has no `created_at` (convention §7). **S**

**DB-10 — Unbounded tables** · **Low**
- `qr_login_challenges` gets a row per anonymous login page view; `login_attempts` grows forever. Add a purge on admin login or in the penalty run (`DELETE … WHERE expires_at < NOW() - INTERVAL 1 DAY`). **S**

**DB-11 — Collation not pinned** · **Info**
- Tables inherit the server default (`utf8mb4_general_ci` on MariaDB). README claims MySQL 8 support, where the default is `utf8mb4_0900_ai_ci`, which gives different comparison semantics and FK collation-mismatch risk on mixed installs. Pin `COLLATE utf8mb4_unicode_ci` in migrations. **S**

**DB-12 — Occupancy trend has holes** · **Info**
- A snapshot is only written when an admin opens the dashboard or trend page (`OccupancySnapshot::takeToday()` on GET). Days with no admin visit have no data point. Backfill from move-in/move-out events, or trigger from any login. **S**

---

## 5. Improvement opportunities (not bugs), ranked by value ÷ effort

| # | Suggestion | Value | Effort |
|---|---|---|---|
| 1 | **Introduce a `charges` (invoice) table**, one row per boarder per period, generated from room price. Payments settle charges; penalties attach to charges; the ledger reads charges + payments + expenses. This removes BE-01, BE-03, BE-07 and BE-25 as a class. | Very high | L |
| 2 | **Shared `Validator` + `ValidationException`** used by every controller; one error-to-flash mapping (BE-08, BE-09, FE-06). | High | M |
| 3 | **Service-layer transactions** (`BoarderService`, `PaymentService`) owning `begin/commit/rollBack`; models never open transactions (BE-05, BE-10). | High | M |
| 4 | **Fix and automate tests**: `data-testid` selectors, PHPUnit for PenaltyEngine/LedgerBuilder/verification, a `composer test`-style script (FE-02). | High | M |
| 5 | **Per-request user reload in `AuthMiddleware`** + idle timeout (BE-06, BE-19). | High | S |
| 6 | **Config loader**: load `.env`; move `DUE_DAY`, lockout limits, QR TTL and upload limits into config (BE-28). | Medium | S |
| 7 | **Remove SQL from views and dedupe the "boarder profile + room + bed" query**, currently copy-pasted in `PaymentController`, `MaintenanceController`, `DashboardController`, `ProfileController` and `Views/portal/dashboard.php:19-32`. | Medium | S |
| 8 | **Pagination** for `Payment::all`, `Expense::all`, maintenance/incident history and `Notification::allFor` (currently unbounded). | Medium | S |
| 9 | **Windows Task Scheduler entries** (in `start-system.bat` docs) for a daily penalty check and occupancy snapshot, instead of relying on admin page views. | Medium | S |
| 10 | **Backup script** (`mysqldump --single-transaction` to a dated file) + documented restore; there is currently none. | Medium | S |
| 11 | Decide the QR-login feature: finish or delete (BE-22). | Low | S |
| 12 | Add a `CHANGELOG`/decision log and retire the two "READY" reports, or mark them superseded by this audit. | Low | S |

---

## 6. Convention and spec compliance

### Project conventions
| # | Convention (source) | Result | Evidence |
|---|---|---|---|
| 1 | Localhost only / no outbound calls (CLAUDE.md) | **PARTIAL** | PHP and Python bind 127.0.0.1 [EXECUTED]; `<noscript>` QR calls api.qrserver.com [STATIC] (FE-07) |
| 2 | PDO prepared statements only (CLAUDE.md) | **PASS** | grep: only a hard-coded identifier interpolated; SQLi probes rejected [EXECUTED] |
| 3 | `password_hash`/`password_verify` only | **PASS** | `$2y$10$` hashes [EXECUTED] |
| 4 | CSRF token on every state-changing form | **PASS** | 36 routes, forged token, no table changed [EXECUTED] |
| 5 | Python binds 127.0.0.1 | **PASS** | `ss -tlnp` [EXECUTED] |
| 6 | Never auto-approve when the verifier is unreachable | **PASS (letter) / FAIL (intent)** | Down → `pending` [EXECUTED]; but up → auto-approves forged amounts (BE-01) |
| 7 | Scorer down → medium + `scoring_pending` + manual rescore (ARCH §3.2) | **PARTIAL** | Fallback works [EXECUTED]; no rescore action [STATIC] |
| 8 | Event records append-only forever (PROJECT_STRUCTURE) | **FAIL** | BE-02, DB-01, notification delete [EXECUTED] |
| 9 | Guarded deletes checked in model (PROJECT_STRUCTURE) | **PARTIAL** | Room/bed guarded [STATIC]; boarder delete cascades [EXECUTED] |
| 10 | RBAC once in middleware | **PASS** | matrix [EXECUTED] |
| 11 | Session cookie httponly + samesite=strict; regenerate on login | **PASS** | [EXECUTED] |
| 12 | Upload allow-list, generated name, never executable | **PASS** | finfo MIME, random name, polyglot not executed [EXECUTED]; but publicly readable (BE-15) |
| 13 | Config via `.env`, loaded once (ARCH §9) | **FAIL** | never loaded (BE-28) |
| 14 | Logs in `logs/app.log` and `logs/scoring_service.log` | **PARTIAL** | PHP yes; Python no [EXECUTED] |
| 15 | Python error shape `{error:{code,message}}` | **FAIL** | returns `{detail:[…]}` [EXECUTED] |
| 16 | Every table: `id` PK + `created_at`; FKs declared (ARCH §7) | **PARTIAL** | FKs present; `boarder_profiles` lacks `created_at` [EXECUTED] |
| 17 | Index queue/dashboard columns | **PASS** | named indexes exist [EXECUTED]; more recommended (DB-08) |
| 18 | Thin controllers, logic in Services, SQL in Models | **PARTIAL** | SQL in views and controllers (Improvement #7) [STATIC] |

### Feature spec (FEATURES.md)
| Feature | Result | Evidence |
|---|---|---|
| F1 Repair priority | **PARTIAL** | Works for clear cases; false positives, gaming, no decay or rescore (BE-12) |
| F2 SOS & incidents | **PARTIAL** | Lifecycle not enforced; no "who resolved"; no people-involved (BE-13, DB-09) |
| F3 Command center | **PASS** | Figures equal SQL [EXECUTED] |
| F4 Expense logging | **PARTIAL** | Admin-only (spec: staff); no date or receipt fields |
| F5 Ledger export | **FAIL** | Wrong total; no date-range UI (BE-04, BE-27) |
| F6 Occupancy trend | **PARTIAL** | Gaps; inflated by phantom beds (DB-12, BE-05) |
| F7 Proof-of-payment verifier | **FAIL** | Client-supplied expected amount (BE-01) |
| F8 Bed mapping / no double-booking | **PARTIAL** | Same-bed race safe; same-boarder multi-bed not (BE-05, DB-02) |
| F9 Penalty automation | **FAIL** | Overcharges; single rule type (BE-03, BE-25) |
| F10 Status life system | **PARTIAL** | Logged correctly; no automatic transitions; any→any; moved-out keeps access (BE-06, BE-13) |
| F11 Notifications | **PARTIAL** | 4 event types; missing payment and SOS-to-staff (BE-18) |
| PHASES Phase 8: full Playwright suite run | **FAIL** | 0/8 (FE-02) |

---

## 7. Prioritized action plan

### (a) Must fix before go-live
| Commit | Contents | Effort |
|---|---|---|
| **1. Payments integrity** | BE-01 (server-side expected amount, proof required, period regex), BE-07 (one active payment per period + unique key), BE-16 (upload error codes), read-only amount on the payment form | M |
| **2. Penalties & ledger math** | BE-03 (period column + unique upsert), BE-04 (signed subtotals), BE-27 (date parsing, range UI) | S–M |
| **3. Boarder lifecycle & occupancy** | BE-02 (soft delete, no child deletes, restore), BE-06 (login + middleware status checks), BE-05 + BE-10 (transactional reassignment/creation), DB-02 (unique + check constraints, after cleaning data), DB-04 | M |
| **4. Remove demo credentials** | FE-01; force admin password change | S |
| **5. Database guard rails** | DB-01 (append-only triggers), DB-06 (least-privilege user, no root fallback) | M |

### (b) Should fix soon
| Commit | Contents | Effort |
|---|---|---|
| 6. Validation & errors | BE-08, BE-09, DB-03, FE-06, BE-23 | M |
| 7. Lifecycles | BE-13 (conditional updates for payments, SOS, maintenance, incidents, boarder status) | S–M |
| 8. Time handling | BE-14, DB-07 | S–M |
| 9. Private uploads | BE-15 | M |
| 10. Auth hardening | BE-17, BE-19, BE-20, BE-24 | S |
| 11. Scoring | BE-11, BE-12, BE-26 | M |
| 12. Notifications | BE-18 | S |
| 13. Tests & migrations | FE-02, DB-05 | M |

### (c) Nice to have
FE-03, FE-04, FE-05, FE-07, FE-08, BE-21, BE-22, BE-25, BE-28, BE-29, DB-08, DB-09, DB-10, DB-11, DB-12, FE-09 (decide the policy), and the improvement list in §5. Each is S unless noted.

---

## Appendix — How to reproduce

All probes were HTTP requests (Python `requests`) or headless Chromium (Playwright 1.56), plus direct SQL against the throwaway DBs. Environment:
```bash
mysql -e "CREATE DATABASE rjm_audit CHARACTER SET utf8mb4"
for f in database/migrations/*.sql; do mysql rjm_audit < "$f"; done
DB_NAME=rjm_audit DB_USER=… DB_PASS=… php database/seed.php
DB_NAME=rjm_audit … php -S localhost:8080 -t public                         # app
DB_NAME=rjm_audit … php -d display_errors=On -S localhost:8081 -t public    # XAMPP-like errors
cd scoring_service && python main.py                                         # 127.0.0.1:5000
```
Fastest single repro of the top issue:
```bash
# log in as boarder, grab csrf_token from /portal/payments/new, then:
curl -b cookies -X POST localhost:8080/portal/payments \
  -d "csrf_token=$T&billing_period=2026-10&expected_amount=1&claimed_amount=1"
mysql -e "SELECT expected_amount, claimed_amount, verification_status FROM payments ORDER BY id DESC LIMIT 1"
# -> 1.00  1.00  auto-matched
```
Penalty engine with an injected date (same method `PHASES.md` used):
```php
<?php require 'src/autoload.php';
echo json_encode(App\Services\PenaltyEngine::runCheck(new DateTimeImmutable('2026-10-08')));
```
