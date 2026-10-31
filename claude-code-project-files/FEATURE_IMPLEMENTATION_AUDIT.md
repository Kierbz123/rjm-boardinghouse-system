# Feature Implementation Audit

**Date:** 10 October 2026
**Note (documentation pass, 14 Oct 2026):** a dated record. A later, deeper audit (`../FUNCTIONALITY-AUDIT-2026-10-14.md`) found problems this one marked Verified: for example expenses "append-only by design" (no date, receipt or void), "Mark paid" on a penalty (really a waiver with no reason), a future move-out date, and hard deletion of residents without payments. Route and check counts are from 10 Oct (86 routes, 542 checks); the suite has grown since.
**Scope:** every feature, followed from the screen, through the server, to the row it leaves in the database.
**Result:** 28 features traced. 27 verified end to end by executed tests; 1 (online demo launcher) verified in parts, with one part that only the owner can run. 9 defects found and fixed. 3 items need an owner decision (section 5).

A feature is marked **Verified** only when a test that was actually run exercised it and checked the database afterwards. Nothing is marked from documentation.

---

## 1. How the audit was done

| Step | What was checked | How |
|---|---|---|
| Routes | All 86 routes in `public/index.php`, with their login and role guards | Read and listed; every non-public route has `AuthMiddleware::require()` and, where needed, `RoleMiddleware::require([...])` |
| Session token | Every state-changing handler checks the CSRF token | Scripted scan of each POST handler, then the 9 it could not see were read by hand (they check through a shared helper). Only the public inquiry form has none, by design |
| Screens to routes | Every form and link on every rendered page, for visitor, admin, staff and resident | Automated crawl in `tests/test_z_feature_audit.php`: each form target must match a route with that method, each link must open, and each state-changing route must have a form on some page |
| Routes to database | Each action leaves the right row; bad input is refused; the wrong role is refused | 542 executed checks across 9 test files; 101 of them new in this audit |
| Dead and fake code | Placeholders, TODOs, hardcoded results, unused functions and endpoints | Text search plus a call scan of every public method |
| Database | Tables, foreign keys, indexes, and the live data's consistency | Live database compared column by column with one built fresh from the migrations; orphan and double-booking queries |
| Output escaping | Variables printed without escaping in the views | Search for raw `<?= $var ?>`: every hit is a count, an integer id or a class name set in code |
| Security review | The new password-reset and bed code | Independent reviewer pass; its findings are in section 3 |

**Test run this audit ends on:** `php tests/run.php --with-ai` → 9/9 files, 542 checks passed, 0 failed (local AI model was running, so the model-backed test ran too).

---

## 2. Feature-to-database traceability

Statuses: **Verified** = executed test checked the database. Evidence names the test file; `audit` = `tests/test_z_feature_audit.php`, `rbac` = `tests/test_http_rbac.php`.

| # | Module | Screen (frontend) | Server (route → controller) | Database | Evidence | Status |
|---|---|---|---|---|---|---|
| 1 | Sign-in, sign-out, lockout | `shared/login.php`, logout button in `shared/nav.php` | `POST /login`, `POST /logout` → `AuthController` | `users`, `login_attempts` | rbac (wrong password, token required), audit (5 failures lock the account) | Verified |
| 2 | Sessions | all pages | `AuthMiddleware` (30-minute idle limit; ends when the account is deleted, deactivated, changes role or password) | `users.password_hash`, `users.status` | rbac (password change ends other sessions), audit (admin reset ends the session) | Verified |
| 3 | Roles | sidebar per role (`NavRegistry`) | `RoleMiddleware` on every admin, staff and resident route | `users.role` | rbac (every role refused from the others' areas with 403), audit (staff and resident refused on each new or previously untested route) | Verified |
| 4 | Landing page and public inquiry | `shared/landing.php` | `GET /`, `GET /landing`, `POST /inquire` → `LandingController` | `inquiries` (rate limit by visitor address), `notifications` | rbac (inquiries), audit (`/landing` opens, links open) | Verified. Room prices on this page are fixed text: see section 5 |
| 5 | Inquiry center | `admin/inquiry-center.php` | `GET /admin/inquiry-center`, `POST /admin/inquiry-center/submit`, `GET /admin/inquiries/{id}` (admin only: full inquiry, opened by the notification's View Details), `POST /admin/inquiries/{id}/status` → `InquiryController` | `inquiries`, `notifications` | rbac, audit (page and forms) | Verified |
| 6 | Residents: add, edit, status, dates, notes | `admin/boarders.php`, `admin/boarder_profile.php` | `POST /admin/boarders`, `/{id}/info`, `/{id}/dates`, `/{id}/notes` → `BoarderController` | `users`, `boarder_profiles`, `boarder_status_log`, `beds` | audit (details, duplicate email, impossible dates, move-out before move-in, note, status logged with who and why) | Verified |
| 7 | Residents: archive, delete, restore | `admin/boarder_profile.php` (Danger Zone), `admin/boarders.php?archived=1` | `POST /admin/boarders/{id}/delete`, `/{id}/restore` | `users.status`, `boarder_profiles`, child rows | rbac (data integrity), audit (resident with a penalty is archived not erased; restore; restore of a wrong id is refused) | Verified |
| 8 | **Forgotten password (new)** | "Reset Sign-in Password" card on `admin/boarder_profile.php`; "Reset password" on each row of `admin/staff.php` | `POST /admin/boarders/{id}/password`, `POST /admin/staff/{id}/password` → `PasswordResetService::byAdmin` | `users.password_hash`, `login_attempts` (lockout lifted), `notifications` | audit (14 checks: admin only, token required, length limits, old password dies, session ends, new one works at once, each route refuses the other account types) | Verified |
| 9 | Staff accounts | `admin/staff.php` | `GET/POST /admin/staff`, `POST /admin/staff/{id}/status` → `StaffController` | `users` | rbac (staff accounts page), audit | Verified |
| 10 | Rooms | `admin/rooms.php` | `POST /admin/rooms`, `/{id}/update`, `/{id}/delete` → `RoomController` | `rooms` | audit (edit saved; capacity below bed count refused; duplicate number refused; room with beds cannot be deleted) | Verified |
| 11 | Beds: add, rename, delete | `admin/rooms.php` (the rename form was missing and is now there) | `POST /admin/beds`, `/{id}/update`, `/{id}/delete` | `beds` | audit (three beds from one action; rename; duplicate label refused; occupied bed cannot be deleted) | Verified |
| 12 | Bed assignment, one resident per bed | assign forms on `admin/rooms.php` and `admin/boarders.php` | `POST /admin/beds/assign` → `BoarderController::assignBed` | `beds.current_boarder_id`, `boarder_profiles.bed_id`, both now unique | audit (occupied bed refused; move frees the old bed; database refuses a second profile on a bed; a resident with a lost pointer can still be moved) | Verified |
| 13 | Electricity bills | `admin/rooms.php` | `POST /admin/electricity`, `/{id}/delete` | `electricity_bills`, `electricity_charges` | rbac (admin only, resident sees their share), `test_billing.php` | Verified |
| 14 | Payments: resident uploads a receipt | `portal/payment_new.php` | `POST /portal/payments` → `PaymentController::create` | `payments` (always `pending`), file in `storage/uploads/receipts` | rbac (no receipt, no method, unknown method, oversize file, one open receipt at a time) | Verified |
| 15 | Payments: admin review | `admin/payments.php` | `POST /admin/payments/{id}/approve`, `/reject` → `PaymentReviewService` | `payments`, `payment_allocations`, `penalties`, `boarder_profiles.outstanding_balance` | rbac, `test_billing.php` (amount credited is the admin's figure; waiver needs a reason; rejection reverses) | Verified |
| 16 | Billing rules | resident dashboard, admin dashboard "Rent & Collections" | `BillingService` | `rent_charges`, balances | `test_billing.php` | Verified |
| 17 | Expenses | `admin/expenses.php` | `GET/POST /admin/expenses` | `expenses` (append-only by design: no edit or delete) | audit (saved with who recorded it; no category and negative amount refused) | Verified |
| 18 | Penalty rules | `admin/penalty_rules.php` | `POST /admin/penalty-rules`, `/{id}/amount`, `/{id}/toggle` | `penalty_rules` | audit (amount edit; zero, negative, non-numeric and oversized refused; unknown rule refused; toggle) | Verified |
| 19 | Penalties: issue, late-fee run, mark paid, rent reminders | `admin/penalty_rules.php`, `admin/boarder_profile.php`, `staff/dashboard.php` | `POST /admin/penalties/issue`, `/staff/penalties/issue`, `/admin/penalties/run-check`, `/{id}/mark-paid`, `/admin/notifications/rent-due` | `penalties`, balances, `notifications` | rbac (four action cards, staff use the rule's amount), audit (mark paid with who; balance drops; twice refused; oversized refused; deactivated rule refused) | Verified |
| 20 | Ledger export | export links on `admin/expenses.php`, `admin/payments.php`, `admin/penalty_rules.php` | `GET /admin/ledger/export` → `LedgerWorkbook` (Excel) or `LedgerBuilder` (`format=csv`) | reads `payments`, `expenses`, `penalties` | audit (CSV content type, header row, contains the expense just recorded; staff refused); probe_money (workbook is valid XML, two sheets, escaped text, totals match the dashboard) | Verified |
| 21 | Occupancy trend | `admin/occupancy.php` | `GET /admin/occupancy` → `OccupancySnapshot` | `occupancy_snapshots` | audit (today's snapshot equals the beds table) | Verified |
| 22 | Repairs | `portal/maintenance_new.php`, `staff/maintenance_queue.php`, `staff/maintenance_history.php` | `POST /portal/maintenance`, `/api/maintenance/score-preview`, `/staff/maintenance/{id}/status` → `ScoringClient` | `maintenance_requests`, `notifications`, file in `storage/uploads/maintenance` | rbac (submission, room taken from the profile), `test_scoring.php`, audit (status saved, resident notified, bad status refused, resolved with who and when) | Verified |
| 23 | Incidents and SOS | `staff/incidents.php`, `staff/incident_history.php`, SOS button on `portal/dashboard.php`, live panel on `staff/dashboard.php` | `POST /staff/incidents`, `/{id}/resolve`, `POST /api/sos`, `GET /api/sos/active`, `/staff/sos/{id}/ack`, `/resolve` | `incidents`, `sos_alerts`, `notifications` | rbac, `test_boarder_incidents.php`, audit (notes required, twice changes nothing, SOS in and out of the live list, residents refused) | Verified |
| 24 | Notifications | bell in `shared/nav.php`, `shared/notifications.php` | `GET /notifications`, `/api/notifications/unread`, read, toggle-read, toggle-pin, delete, read-all | `notifications` | audit (own notifications only; each action; another person's cannot be read or deleted) | Verified |
| 25 | Own profile | `shared/profile.php` | `POST /profile/update`, `/profile/password` | `users`, `boarder_profiles` | rbac (password change), audit (edit saved; 60-character number refused cleanly; another account's email refused) | Verified |
| 26 | Private uploads | receipt and photo links | `GET /uploads/{subdir}/{file}` → `Uploads::serve` | files outside `public/` | rbac (receipts are private) | Verified |
| 27 | AI assistant | chat panel (`ai-assistant.js`), helper buttons on repair pages | `GET /api/assistant/status`, `POST /api/assistant/*` → `AssistantService`, `OllamaClient` | reads the caller's own records | `test_assistant.php` (page routing per role without the model), `test_ai_assistant.php` (with the local model, ran this time), audit (status endpoint) | Verified. Needs the optional local Ollama for model answers; without it the buttons say "offline" |
| 28 | Online demo (phone access) | QR code and "Phone access is off" panel on `shared/login.php` | `start-online.bat` → `bin/go-online.ps1`; `TrustedProxy`; `AuthController::publicBaseUrl()` | `logs/online-address.log` heartbeat file | `test_trusted_proxy.php`; login page checked with a fresh, an old and no heartbeat file; owner confirmed a phone opened the system on 9 Oct | **Partly verified**: see section 4 |

---

## 3. Defects found and fixed

| # | Kind | What was wrong | Fix | Proof |
|---|---|---|---|---|
| 1 | Feature existed only as an instruction | The login page says "Forgot your password? Contact House Administrator", but an administrator had no way to reset a password. `DEMO-ACCOUNTS.md` gave a command-line command instead. | New admin-only action for residents and staff (`PasswordResetService`, two routes, two forms). Ends the user's sessions, lifts their lockout, tells them inside the system. | audit, "Forgotten password" section |
| 2 | Live data fault | Two resident profiles pointed at Room 1 / Bed A. Nothing in the database prevented it. The most likely cause (not proven) is an old test fixture that borrowed a bed a seeded resident held, run against the live database before the test runner existed; that fixture produced exactly this state. | Migration `0029` cleared the stale pointer (resident #5, a real account; the bed belongs to the demo boarder) and added unique indexes: one profile per bed, one bed per resident. The test fixture now makes its own room. | Before/after rows in section 6; audit, "One resident per bed" |
| 3 | Latent data-loss bug | Freeing a bed followed the pointer on the profile. With a stale pointer, moving or removing that resident would have emptied another resident's bed. | `Bed::vacateHeldBy()` frees only beds the beds table says that resident holds. Used by all four places that free a bed. | audit (freeing for someone who holds none changes nothing; lost-pointer resident ends on exactly one bed) |
| 4 | Server feature with no screen | Renaming a bed worked on the server (`POST /admin/beds/{id}/update`) but no page had a form for it. | Rename form on each bed row of the Rooms page, with a confirmation message. | audit wiring check; rename and duplicate-label checks |
| 5 | Unused endpoint | `POST /admin/boarders/{id}/status` had no form anywhere; status is changed through the details form. Only tests called it. | Route and controller method removed; the two tests go through the details form. | audit wiring check |
| 6 | Error page on bad input | A resident typing a contact number over 50 characters on their own profile got the "Something went wrong" page (the database is in strict mode). | Length check with a normal message. | audit, "Resident's own profile" |
| 7 | Error page on bad input | Penalty rule amount, rule edit and manual penalty accepted any size; above 99,999,999.99 the database refused and the error page showed. A missing form field raised PHP warnings. | Limit of ₱1,000,000, missing fields handled, unknown rule id reported, deactivated rules cannot be used. | audit, "Penalty rules and penalties" |
| 8 | Loose check | The admin "edit resident" route accepted any user id, so it could rename a staff or admin account. "Restore" reported success for any id. | Both confirm the target is a resident (restore: an archived one). | audit (id 1 not renamed; wrong restore says so) |
| 9 | False statement on screen | The login page offered "Log in with QR code — Scan this with our App to log in instantly". There is no app and no QR login (that feature was dropped in migration 0024). | Reworded to "Open on your phone". With the normal launcher there is no QR code at all, since a phone could not use it. | Page checked in the browser |

Also changed:

- **Schema drift closed.** Two indexes existed in the live database but in no migration, and `penalties.waived_by` was the one user reference without a foreign key. Migration `0029` adds all three. Live and freshly migrated databases now match exactly (0 differences).
- **Dead code removed.** `Penalty::create()` had no callers anywhere.
- **Online-status check corrected.** The login page decided "the online launcher is running" by testing that something answered on port 8010. Any other program on that port plus a left-over address file showed a QR code for a dead address. The launcher now refreshes the file every 2 seconds and the page ignores one older than 15 seconds.

### From the security review of the new code

No way was found for anyone but an admin to reset a password; the reset ends the target's sessions; the password is hashed, never logged and never shown.

| Finding | Severity | Action |
|---|---|---|
| Migration `0029` repaired only one direction of bed/profile mismatch; the other direction would leave a resident who could not be moved | Medium | Migration `0030` frees such beds (the live data had none), and `assignBed` now frees by what the resident actually holds |
| An unexpected database error while assigning a bed or adding a resident showed the generic error page | Low | Both now roll back, log the detail and show a plain message |
| The reset service relied on its callers for the role check | Low | It checks the role itself; an admin account is refused |
| Passwords longer than 72 bytes are silently cut by the hash | Low | Reset limited to 8–72 characters |
| Staff could issue a penalty from a deactivated rule by crafting the request | Low | Refused on the server |
| Not changed: the temporary password is not forced to be changed at next sign-in | Low | The user is told to change it; a forced change is a separate feature |

---

## 4. Not fully verified

| Item | What is verified | What is not | Why |
|---|---|---|---|
| Online launcher `bin/go-online.ps1` | Syntax; every server-side effect it triggers (visitor address, Secure cookie, public address on the login page, heartbeat ages) on a local server with the same settings; a phone opened the system through it on 9 Oct | The launcher itself after the heartbeat change of 10 Oct; shutdown by Ctrl+C and by closing the window | The assistant's session is not permitted to open a public tunnel. **Owner:** run `start-online.bat`, confirm the QR code appears on `http://127.0.0.1:8000/login` and disappears within about 15 seconds of closing the launcher window |
| Sidebar on a phone | Profile and logout row inside the screen in a 375×812 preview | A real phone browser with sliding toolbars | Cannot be imitated in the preview |

---

## 5. Needs an owner decision (not changed)

1. **Landing-page room prices are fixed text and do not match the rooms in the system.** The page advertises Solo ₱3,500, Twin ₱2,200 and Quad ₱1,600 "from ₱1,600"; the three real rooms all hold 4 and cost ₱3,500, ₱1,000 and ₱1,000 per bed. The occupancy numbers on the same page do come from the database. Options: keep as a brochure, or show each tier's price from the rooms table.
2. **Resident #5 has a room but no bed** after the double booking was cleared. Assign them a bed on the Rooms page (Room 1 / Bed B is free).
3. **An administrator who forgets their own password** can only be reset from the command line (`DEMO-ACCOUNTS.md`); there is one admin and no email. A second admin account would be the usual answer.

Known and accepted, already in the README: the public inquiry form has no session token (it has a hidden-field bot trap and an hourly limit per visitor instead); five wrong passwords lock an email for 15 minutes whoever typed them; demo passwords are published.

---

## 6. Database changes

| Migration | What it does | Effect on the live database |
|---|---|---|
| `0029_bed_integrity_and_schema_catchup.sql` | Clears profile pointers at beds held by someone else; unique indexes on `boarder_profiles.bed_id` and `beds.current_boarder_id`; indexes on `incidents.created_at` and `maintenance_requests.created_at`; foreign key on `penalties.waived_by` | One row changed: resident #5 `bed_id` 1 → NULL (room unchanged, balance unchanged). No rows deleted |
| `0030_free_beds_without_matching_profile.sql` | Frees beds marked as held by a resident whose profile does not point at them | No rows matched |

**Backup taken before migrating:** `database/backups/rjm_boardinghouse_20261010_093227_before_0029.sql` (21 tables).

**Roll back:**

```
C:\xampp\mysql\bin\mysql -uroot rjm_boardinghouse < database\backups\rjm_boardinghouse_20261010_093227_before_0029.sql
```

That restores the data and the schema to before `0029`. The current code needs `0029`'s indexes only for its guarantees, not to run, but restore the earlier code with it if you roll back for long.

---

## 7. To continue this audit later

- Run `php tests/run.php` (add `--with-ai` when Ollama is running). The wiring check in `tests/test_z_feature_audit.php` fails by itself when a new route has no form, a form points at no route, or a link is dead.
- When adding a route that is called only by script, add it to the `$scripted` list in that test with a reason.
- Open items are sections 4 and 5.
