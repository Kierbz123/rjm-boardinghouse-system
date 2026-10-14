# Implementation Plan — functionality audit fixes

**Date:** 14 Oct 2026  **Branch:** `fix/functionality-audit-2026-10` (baseline commit `e5f6799` = the uncommitted work found on 14 Oct)
**Source of findings:** `FUNCTIONALITY-AUDIT-2026-10-14.md` (F-01…F-31, WP-1…WP-20). **Scope approved:** all of it.
**Live DB backup taken before any work:** `claude-code-project-files/database/backups/rjm_boardinghouse-pre-fixes-20261014-111458.sql` (+ `uploads-pre-fixes-20261014-111458/`). Git-ignored.

## 1. Working rules

| Rule | How |
|---|---|
| One commit per package | `WP-n: …` messages; each commit holds code + migration + rollback + tests for that package only. |
| Red → green | The regression test is written first and run against the unfixed code (output recorded in the package summary), then the fix lands. |
| Migrations | `NNNN_name.sql` + `NNNN_name.down.sql`. `migrate.php` gains `--rollback[=N]`, `--status`, and trigger support. Each is proven on a throwaway DB: up → down → up. |
| Live DB | **Never touched by me.** Migrations run only on `_test` / scratch databases. The live DB migrates when you next run `start-system.bat`; the backup above exists for that. |
| Tunnel | Not started. Tunnel/QR items go on the manual checklist (§5). |
| Tests | Everything runs from `php tests/run.php`. The runner now generates **random** per-run passwords (no fixed credentials anywhere), starts a second server on the same DB for concurrency tests, and (Windows) cannot use `PHP_CLI_SERVER_WORKERS`, so two `php -S` processes are the multi-worker setup. |

## 2. Decisions applied, and things I had to decide

| Item | Applied |
|---|---|
| Opening date / future limit | Setting `property_opening_date` (admin-editable), **default `2026-01-01` — a placeholder; you did not give the real date.** Future limit = today + 12 months. |
| Payment details | Stored in a settings table; **hidden on Pay Rent until you save real details.** I have none to enter. |
| Pending residents | Auto-activate on move-in date; admin notified. |
| Expenses | Staff may record; stay **pending** until an admin approves; only approved expenses count in ledger/dashboard. |
| Cash payments | Admin **and staff** may record. Staff-recorded payments count immediately (same as online once approved) but notify all admins and are flagged "recorded by staff" so an admin can reverse them. *My call — say so if you want staff entries to need approval.* |
| CCTV | Claim removed from the landing page (all 5 places). Page stays in the backlog. |
| Reminders | 3 days before, on the due date, 3 days after; in-app + e-mail. |
| **E-mail** | `CLAUDE.md` forbids e-mail without an explicit change to the localhost-only rule; I am treating your decision as that change and will update the rule. E-mail is queued in an `email_outbox` table and **nothing leaves the machine until an admin enables SMTP in Settings** (off by default). I cannot test against a real mail server; tests use a local fake SMTP server. |
| Database login | `.env` credentials for `boardinghouse_app` **do not authenticate today**, so naive `.env` loading would break the live app. Plan: loader + `bin/setup-db-user.php` (you run it; I do not create users on your MariaDB) + logged fallback to root while unconfigured + tunnel refuses while connected as root-without-password. |
| Legacy demo accounts | Migration marks the three legacy demo e-mails `must_change_password = 1` (I cannot test their hashes without writing the old passwords into the repo). Even if you change them today, you will be asked once more after migrating. |

## 3. Order of work (dependencies respected)

`WP-0` infra → **`WP-1`** credentials → **`WP-2`** input hardening → `WP-3` dates & scheduled move-out → `WP-4` archive-only → `WP-8` atomic approval → `WP-6` cash payments → `WP-5` waive / mark-paid → `WP-7` consistent reports → `WP-10` settings (+ CCTV claim) → `WP-9` housekeeping, reminders, e-mail → `WP-11` repairs → `WP-12` expenses → `WP-13` inquiries → `WP-14` accounts → `WP-15` SOS/incidents → `WP-16` UI honesty → `WP-17` backup/retention → `WP-18` paging → `WP-20` untested items (AI mocked, idle timeout, race, launcher).

Migration numbers are assigned in order of use, starting at `0031`.

## 4. Packages

For every package: **Files** · **Migration** · **UI** · **Tests** (all red first) · **Verify**.

### WP-0 · Test & migration infrastructure
* **Files:** `database/migrate.php` (rollback/status/triggers), `tests/run.php` (random passwords, 2nd server, mock Ollama, glob), `tests/lib.php` (HTTP client, DB, `check()`, `knownDefect()`), `tests/test_probe_*.php` (the four corrected audit probes, self-contained, unique data per run), existing tests switched from literal passwords to `TEST_*_PASSWORD`.
* **Migration:** none. **UI:** none.
* **Tests:** probes become permanent suites; open defects are `knownDefect('F-xx', …)` (prints `[KNOWN]`, does not fail) and are promoted to hard `check()` in the commit that fixes them. Migration runner self-test (up/down/up).
* **Verify:** `php tests/run.php` green on the unfixed code; rollback of a sample migration restores the schema.

### WP-1 · Credentials (F-01, F-02)
* **Files:** `database/seed.php` (random or `SEED_*_PASSWORD`; prints once), `database/check-demo-passwords.php` (flag-based, no literals), `src/Middleware/AuthMiddleware.php` (force change), `src/Controllers/ProfileController.php` + `PasswordResetService` (clear flag, set `password_changed_at`), `src/Database.php` (`.env` loader, fallback, warning), `bin/setup-db-user.php`, `bin/go-online.ps1` (**refuse**), `start-system.bat` (warn), `DEMO-ACCOUNTS.md` / `README.md` / `DEMO-ACCOUNTS.txt` (passwords removed).
* **Migration `0031_user_security_flags`:** `users.must_change_password`, `users.password_changed_at`; flags legacy demo e-mails. Down drops both.
* **UI:** "Choose a new password" banner + locked navigation until changed.
* **Tests:** seeded password is not any published string; seeded login → forced to `/profile`; other pages bounce until changed; after change flag clears; checker lists flagged accounts; launcher exits non-zero (run via PowerShell against the test DB); no published password string remains in tracked files (grep test).
* **Verify:** the suite plus the grep test.

### WP-2 · Input hardening (F-07, F-09, F-10, F-27)
* **Files:** new `src/Support/Input.php` (one helper: scalar enforcement, bounded strings/ints/dates), `public/index.php` (reject non-scalar input once, centrally: clean **400**, HTML or JSON), `src/Support/Csrf.php` (`mixed`), `src/Models/User.php` + new `PasswordPolicy` (8–72 **bytes**, enforced in `create`/`updatePassword`), `src/Support/Flash.php` (log real error, show safe text) replacing the five raw `getMessage()` echoes, `RoomController` (capacity 1–50, update-missing-room), `app.js` + forms (`maxlength`, live byte counter).
* **Migration `0032_room_capacity_check`:** `CHECK (capacity BETWEEN 1 AND 50)`. Down drops it.
* **UI:** password fields show "x / 72" and block submit with a message.
* **Tests:** every POST route × {`csrf_token[]`, array fields, oversized field, NUL byte} → 400/validation, never 5xx; JSON endpoints likewise; 73-byte password refused in create-resident, create-staff, profile, both reset forms; capacity 0/51/10¹¹ refused; updating a missing room says not found; forced DB error shows no SQL.
* **Verify:** fuzz test across the **whole route table** (read from `index.php`), so a new route cannot regress.

### WP-3 · Move-in / move-out dates (F-03, F-05, F-17 part, F-31)
* **Files:** `BoarderController`, `BoarderProfile`, `BillingService` (hard cap 120 rent rows per recalculation), new `src/Services/DateRules.php`, new `src/Services/HousekeepingService::applyDueMoveOuts()`, `bin/find-absurd-rent.php` (read-only query/report), views `boarder_profile.php` / `boarders.php` (date inputs `min`/`max`, scheduled-move-out panel with **Cancel**).
* **Migration `0033_date_guards`:** `settings` (seeded with the opening date), `audit_log`; MariaDB triggers rejecting `move_in_date` < `property_opening_date` or > today + 12 months and `move_out_date` < move-in. Down drops triggers/tables.
* **UI:** date pickers bounded; scheduled move-out shows "Moves out on …  [Cancel move-out]"; resident stays **active**, bed stays occupied.
* **Behaviour:** past/today move-out → immediate (as now). Future → scheduled; job flips status + frees bed on the date; cancel clears it and writes the audit log.
* **Tests:** 1900 / 0001 / 2099 refused at **UI-validation, controller and DB-trigger** level (raw SQL attempt hits the trigger); 121-cycle recalculation throws; future move-out keeps active + bed; cancel works; job moves a due resident; read-only finder reports a bad row without modifying anything.
* **Verify:** I run the finder against a copy of the live DB (restored from the backup into a scratch DB) and report results **before** any cleanup.

### WP-4 · Archive-only residents (F-04)
* **Files:** `BoarderProfile::delete()` removed, `BoarderController::delete` → archive, `boarders.php` + `boarder_profile.php` (wording, "Archived residents" tab with archived-on / by / reason).
* **Migration `0034_archive_integrity`:** `users.archived_at/archived_by/archive_reason`; `payment_allocations.boarder_id` → `ON DELETE RESTRICT`. Down restores CASCADE and drops columns.
* **Tests:** archive keeps repairs, incidents, SOS, payments (rows counted before/after); raw `DELETE FROM users` of a resident with any history is refused by the database; restore works; archived tab lists reason.

### WP-8 · Atomic approval & read-only GETs (F-08)
* **Files:** `PaymentReviewService::approve` (one transaction, row lock first), `BillingService::calculateBalance(readOnly)`, `PaymentController::index`, `DashboardController`, `OccupancyController`.
* **Migration:** none. **UI:** none.
* **Tests:** two simultaneous approvals through two servers → exactly one approved, no orphan late fee/waiver; **GET of payments/dashboard/occupancy changes zero rows** (row-count checksum before/after).
* **Verify:** concurrency test repeated 20× without a double effect.

### WP-6 · Cash & walk-in payments (F-13)
* **Files:** new `src/Services/PaymentRecorder.php`, `PaymentController::recordForm/record/receipt`, staff route, views `admin/payment_record.php` + `shared/receipt.php` (print CSS), `Payment::METHODS` (+cash, other).
* **Migration `0035_manual_payments`:** `payment_method` ENUM + `cash`,`other`; `payments.receipt_number` (unique), `received_on`, `received_by`, `recorded_by_role`, `target_penalty_id`. Down reverses.
* **UI:** "Record payment" on Payments and on a resident's profile; printable receipt; "recorded by staff" flag.
* **Tests:** cash payment clears oldest cycle and appears in balance, dashboard collected, ledger and allocation list **identically to an approved online payment** (same helper compares both); receipt number unique; amount bounds; staff allowed, resident refused; admin notified of staff entries; receipt page prints for the owner, admin, staff only.

### WP-5 · Waive vs Mark paid (F-06)
* **Files:** `PenaltyController::waive/markPaid`, `Penalty`, `PaymentRecorder` (targeted penalty payment), `BillingService::allocate` (targeted-first), views `penalty_rules.php`, `boarder_profile.php`.
* **Migration:** none beyond WP-6's `target_penalty_id` and WP-3's `audit_log`.
* **UI:** two buttons: **Waive** (reason required) and **Mark paid** (method, amount, reference, date).
* **Tests:** waive without reason refused; waive stores who/when/why + audit row; mark-paid creates an income row counted in ledger; both write the audit trail; neither double-applies.

### WP-7 · One source of truth for reports (F-11, F-12)
* **Files:** new `src/Services/OccupancyService.php` (single function) used by dashboard, Occupancy page, landing stats and assistant; `OccupancySnapshot`, `LedgerBuilder`, `BillingService::collectionsSummary`.
* **Tests:** dashboard and Occupancy page are parsed and compared to `OccupancyService` on the same data after every bed change; ledger payments total equals dashboard collected for month-boundary data; receivable excludes waived.

### WP-10 · Settings (F-14, F-21) — and CCTV claim (WP-19)
* **Files:** `src/Models/Setting.php`, `SettingsController`, `admin/settings.php`, `LandingController`, `landing.php`, `portal/payment_new.php`.
* **Migration `0036_settings_columns`:** adds `updated_by/updated_at` and seeds keys. Down reverts.
* **UI:** admin Settings page (payment channels, contact info, social links, hours, opening date, SMTP, landing price overrides); landing prices default from rooms; CCTV text removed from 5 places.
* **Tests:** payment block hidden until saved; shows after save; landing prices follow rooms; no "CCTV" string on the landing page.

### WP-9 · Housekeeping, reminders, e-mail (F-15, F-16, F-17)
* **Files:** `HousekeepingService` (once-daily guarded job from the first admin/staff request; `bin/housekeeping.php` for Task Scheduler), `ReminderService`, `Mailer` + `SmtpTransport`, `NotificationDispatcher`.
* **Migration `0037_housekeeping`:** `system_jobs`, `email_outbox`, notification dedupe key. Down drops.
* **Behaviour:** pending→active on move-in (admin notified); due move-outs; occupancy snapshot; late-fee check; reminders at −3 / 0 / +3 days, **once each**; the manual button reports "N sent, M already sent".
* **Tests:** clock-injected job runs; second run same day does nothing; each reminder stage sent once; outbox row per reminder; SMTP delivery proven against a **local fake SMTP server**; disabled SMTP sends nothing.

### WP-11 Repairs · WP-12 Expenses · WP-13 Inquiries · WP-14 Accounts · WP-15 SOS/incidents
* **WP-11:** time-decay ordering; assignment + notes (`maintenance_notes`, migration `0038`); resident cancel; reopen needs reason; "AI" wording → rule-based.
* **WP-12:** `expenses` gets `expense_date`, `category` list, receipt upload, `status` (pending/approved/rejected/void), approver, reasons (migration `0039`); staff entries pending; reports count approved only.
* **WP-13:** use `inquiries.status`; paged list, filters, status buttons, convert-to-resident prefill (migration `0040` adds `handled_by/handled_at/note`).
  **Part 1 done (14 Oct):** the notification's View Details opens `/admin/inquiries/{id}` (admin only: full message, contacts, move-in date, staff notes, source) with a new/contacted/closed status form; inquiry notifications carry `entity_type='inquiry'` + `entity_id`, and migration `0032_link_inquiry_notifications` relinks the old ones. Still to do: paged list, filters, convert-to-resident, `handled_by/handled_at/note`.
* **WP-14:** edit staff, create admin, last-admin guard, staff delete only if no records.
* **WP-15:** `sos_alerts.resolved_by/resolution_note/is_false_alarm`, SOS history page, toasts, incident `people_involved`/`room_id` (migration `0041`).
* **Tests:** one test file each, red first (ordering, cancel rules, approval gating, status workflow, last-admin refusal, SOS lifecycle fields).

### WP-16 UI honesty · WP-17 Operations · WP-18 Paging
* **WP-16:** real health badges (DB reachable, last snapshot, last housekeeping), admin sidebar links, KPI overflow fix.
* **WP-17:** `bin/backup.ps1` / `bin/restore.ps1`, log rotation, retention in the daily job. Test: backup → restore into scratch DB → row-count equality.
* **WP-18:** 50/page + search on payments, boarders, notifications, inquiries.

### WP-20 · Previously untestable items
* **AI helpers:** `OllamaClient` URL from env (`OLLAMA_URL`); mock Ollama server in the test runner; tests for all five endpoints (success, garbage reply, timeout, down).
* **Idle timeout:** test rewrites the server-side session file's `last_activity` to 31 minutes ago and expects a redirect.
* **Concurrency:** payments, rent generation, bed assignment, receipt submission under two servers.
* **`start-system.bat`:** scripted run against a scratch DB, asserts the port answers and the migration step ran, then cleans up.
* **Manual list for you:** §5.

## 5. Manual checks that stay with you (final list is sent at the end)
Tunnel start/stop and QR scan from a phone · real SMTP delivery with your mail account · entering real GCash/Maya/BDO details · printing a receipt on your printer · real Ollama quality (not mocked) · confirm `property_opening_date` · run `bin/setup-db-user.php` · applying migrations to the live DB after reading the backup path above.
