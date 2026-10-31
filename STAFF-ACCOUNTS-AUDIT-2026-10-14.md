# Staff Accounts page — audit and fixes

**Date:** 14–15 Oct 2026 · **Branch:** `fix/functionality-audit-2026-10` · **Page:** `/admin/staff`
**Code:** `claude-code-project-files/` (paths below are relative to it). Plain PHP 8.2, no framework; passwords via
`password_hash(PASSWORD_DEFAULT)` = **bcrypt**; users in `users` (`database/migrations/0001_create_users.sql`).
Overlaps WP-14 (accounts) in `IMPLEMENTATION-PLAN-2026-10-14.md`; this pass covers the staff page only.

## 1. Findings

Severity is for this app as deployed: a localhost system with an optional, hand-started tunnel, and no MFA.

| ID | Area | Finding | Sev | Evidence | Recommendation → status |
|---|---|---|---|---|---|
| S-01 | Temp password | A password the admin typed was permanent: neither *create* nor *reset* set `must_change_password`, so the admin knew every staff password indefinitely. | **High** | old `StaffController.php:35`, `PasswordResetService.php:29` | Mark it temporary on create and on every admin reset → **fixed** |
| S-02 | Temp password | Temporary passwords never expired. | **High** | no column existed | `temp_password_expires_at`, 72 h, checked at login → **fixed** |
| S-03 | Policy | Minimum 8 characters. NIST SP 800-63B-4 requires 15 when the password is the only factor, and this app has no MFA. | **High** | old `Support/PasswordPolicy.php:13` | 15 characters (code points) → **fixed, for every account** (one shared rule). **Relaxed to 10 characters on 24 Oct 2026** (owner decision; guessability check unchanged) |
| S-04 | Policy | No check against common or context words (site name, "rjm", own name or e-mail), and no reason given. | Med | same file | Local check of common words, keyboard runs, site name, own name/e-mail, with a reason in the message → **fixed** (no breached-password corpus, see R-2) |
| S-05 | Audit | Create, deactivate and reactivate left no trace; reset went only to the text log. | **High** | old `StaffController.php`; `audit_log` existed (0034) but was unused here | Write `audit_log` rows (actor, target, UTC timestamp, IP, action) and add a per-person history → **fixed** |
| S-06 | Copy | The counter says "bytes" while the hint said "At least 8 characters". | Low | old `Views/admin/staff.php:27` | Hint gives both limits and why → **fixed** |
| S-07 | UX | There was no "show password" on this page (only on Add Boarder). Paste and password managers already worked: no `onpaste` block, and `autocomplete="new-password"`. | Low | view | Native checkbox toggle → **fixed** |
| S-08 | bcrypt | 72-byte truncation was **already handled**: rejected on the server by `PasswordPolicy` and again in `User::create/updatePassword` (`assert`). | — | `PasswordPolicy.php`, `Models/User.php` | Kept: option (a), see §2 |
| S-09 | Data | E-mails were stored as typed. Uniqueness was already case-insensitive (the `UNIQUE` column under a `_ci` collation), deactivated accounts included. | Low | `0001_create_users.sql` | Store lower-cased; migration 0042 normalises old rows → **fixed** |
| S-10 | Data | Inner spaces in names were not collapsed. | Low | old `StaffController.php:20` | → **fixed** |
| S-11 | Deactivate | Already correct: the session dies on its next request (`AuthMiddleware.php:27`); there are no remember-me cookies or API tokens to revoke (grep). Only *deactivate* had a confirmation. | Low | `AuthMiddleware.php`, view | Confirmation on reactivate too; deactivating twice is a no-op → **fixed** |
| S-12 | Deactivate | Open work: the schema assigns nothing to a person (`maintenance_requests`/`incidents` have only `resolved_by`); the only link is `sos_alerts.acknowledged_by`. | Info | migrations 0008, 0016, 0017 | Tell the admin how many acknowledged SOS alerts the person left open (anyone on staff can resolve them) → **added** |
| S-13 | Deactivate | "Last SOS responder" guard: not needed. SOS alerts go to every staff **and admin** account (`Notification.php:97`), so deactivating every staff member still leaves the admin paged. Side effect: deactivated staff still receive the notification rows. | Low | `Models/Notification.php:97` | Guard skipped; test proves the admin is paged. Filter `status='active'` there later (R-6) |
| S-14 | Deactivate | An admin cannot deactivate themselves here: every action requires `role = 'staff'`. | — | `StaffController::setStatus` | Kept; now tested |
| S-15 | Roles | One `staff` role does everything: repairs, incidents, SOS, **issuing penalties** (`index.php:311`), expenses, cash payments. | Med | `public/index.php:278-330` | Matrix proposed in §2. **Not built** (owner decision, WP-14) |
| S-16 | AuthZ | Already correct: all four routes are admin-only (`index.php:157-172`, `RoleMiddleware` → 403) and every POST checks CSRF (`StaffController::requireCsrf` → 400). | — | as cited | Kept; now tested route by route |
| S-17 | Session | Already correct: `httponly`, `SameSite=Strict`, `secure` behind HTTPS, `use_strict_mode`, 30-minute idle timeout, id regenerated at login. | — | `public/index.php:22-28`, `AuthMiddleware.php:9` | — |
| S-18 | Rate limit | Login is limited (5 per e-mail, 20 per IP, 15 min); admin actions are not. | Low | `AuthController.php:13-15` | Accepted: admin-only, behind login and CSRF |
| S-19 | Secrets | No password reaches the logs, flash messages, audit rows or HTML. `Logger` masks e-mails. | — | `Support/Logger.php` | Kept; now tested |
| S-20 | UX | No "Last login", no "Created by", and "Since" had no time zone. | Low | old view | "Last sign-in" column (`last_login_at`); creator shown in history; `<time>` with zone on hover → **fixed** |
| S-21 | UX | No search, sort or pagination. | Low | — | Skipped: one staff account today. WP-18 (paging) covers it |
| S-22 | Retention | No retention rule existed. | Med | — | A statement is on the page (**ASSUMPTION**: kept as long as other records). The owner sets the real period (Q-3) |
| S-23 | Unicode | NFC normalisation before hashing is not possible here: `ext-intl` is disabled in this XAMPP PHP (`class_exists('Normalizer') === false`). | Low | `php -m` | Left open (R-1); enabling it later must be paired with a rehash-on-login plan |
| S-24 | Residents | The same S-01 flaw on **resident** accounts the admin creates (`BoarderController::create`). Resident *resets* are fixed, because they share `PasswordResetService`. | **High** | `BoarderController.php:139` | Left open: that file has another session's uncommitted work. One line: `User::markPasswordTemporary($userId)` after `User::create` |
| S-25 | Race | Two simultaneous creates with one e-mail: the second hits the `UNIQUE` key and gets a 500 instead of a message. | Low | `User::create` | Left open (R-5) |
| S-26 | Tests | Before this pass the staff page had 9 probe checks and no tests for policy, lifecycle or audit. | Med | `tests/test_probe_admin.php` | `tests/test_staff_accounts.php` → **added** |

## 2. Decisions

- **bcrypt limit: keep option (a)**, reject anything over 72 bytes on the server with a plain message. It was already done and is enforced in the model, so no controller can bypass it, and it needs no hash migration. 72 bytes still allows 64+ ASCII characters (NIST's "at least 64"). With many emoji or CJK characters it falls short; Argon2id (`PASSWORD_ARGON2ID` is available in this PHP) would remove the cap, with rehash on login. Upgrade path: R-3.
- **Invite link vs temporary password: temporary password.** The app may not send e-mail until WP-9 (CLAUDE.md, "What NOT to do"), so an e-mailed single-use link is not possible yet. The admin-typed password is now single-purpose: it only opens "choose your password", expires after **72 hours** (**ASSUMPTION**, `User::TEMP_PASSWORD_HOURS`), and is replaced on first sign-in. When WP-9 lands, the invite link becomes the default and this flow the fallback.
- **MFA: none, so 15 characters minimum**, applied to everyone (residents too), because the codebase has one password rule (WP-2). Existing passwords keep working; the rule applies when a password is set. *24 Oct 2026: the owner relaxed the minimum to 10 characters for every account, knowingly below the NIST figure; the 72-byte cap, the repeated-character check and the guessability check are unchanged.*
- **Role model (proposal, not built):**

  | Permission | Maintenance | Incidents | SOS responder | Supervisor |
  |---|---|---|---|---|
  | Repairs: view / update status | ✓ | | | ✓ |
  | Incidents: view / resolve | | ✓ | | ✓ |
  | SOS: acknowledge / resolve | | | ✓ | ✓ |
  | Issue penalties | | | | ✓ |
  | Record expenses / cash payments | | | | ✓ |

  Implementation: a `staff_scopes` SET column, and `RoleMiddleware::require(['staff:sos', 'admin'])` per route, so checks stay in one place. Do not build it until the owner says staff duties really differ (Q-1).

## 3. Code changes (unified diff: `git diff -- <file>` in `claude-code-project-files/`)

| File | Change |
|---|---|
| `src/Support/PasswordPolicy.php` | 15 characters minimum (10 since 24 Oct 2026) and 72 bytes maximum; guessability check (common words, keyboard runs, site name, own name/e-mail; fewer than 5 distinct characters refused); `problem()/assert()` take the account's name/e-mail as context |
| `src/Models/User.php` | e-mail lower-cased on create; `markPasswordTemporary`, `temporaryPasswordExpired`, `recordLogin`; `markPasswordChosen` clears the expiry |
| `src/Controllers/AuthController.php` | an expired temporary password is refused (said only after the right password, so it reveals no accounts); last sign-in recorded |
| `src/Services/PasswordResetService.php` | admin resets (staff and residents) are temporary and audited (with IP); context-aware policy |
| `src/Controllers/StaffController.php` | name/e-mail normalised; created / deactivated / reactivated audited; no-op on a repeated status; open-SOS notice; clearer messages |
| `src/Services/AuditLog.php` | `forUsersWithRole()` for the history view |
| `src/Models/SosAlert.php` | `openAcknowledgedBy()` |
| `src/Controllers/ProfileController.php` | own name/e-mail rejected as a new password |
| `src/Views/admin/staff.php` | new intro, hint and confirmation text; show-password; "Last sign-in"; temporary-password badges; per-person history; retention note |
| `src/Views/shared/profile.php`, `admin/boarder_profile.php`, `admin/boarders.php`, `src/Services/AssistantService.php` | "8 characters" text changed to the 15-character rule (the profile page no longer recommends symbols); since 24 Oct 2026 it states 10 characters |
| `database/migrations/0042_temporary_passwords_and_last_login.sql` (+ `.down.sql`) | adds `users.temp_password_expires_at`, `users.last_login_at`; lower-cases stored e-mails |
| `tests/test_staff_accounts.php` | new (see §5) |
| `tests/lib.php`, `test_probe_admin`, `test_probe_ops`, `test_http_rbac`, `test_wp01_credentials`, `test_wp02_input_hardening`, `test_billing`, `test_boarder_profile`, `test_z_feature_audit`, `test_inquiry_conversion` | password literals raised to 15+ characters; new staff and reset residents now go through "choose your password" first |

E-mail templates: none. The app may not send mail until WP-9.

## 4. Migration and rollback

- Number `0042`: `0036`–`0041` are reserved in the plan and `0040` is in use.
- **Apply:** `php database/migrate.php` (the live DB migrates when `start-system.bat` next runs; agents never run it on live).
- **Roll back:** `php database/migrate.php --rollback=1` drops the two columns. The e-mail lower-casing is not undone; it is harmless, because logins already compare case-insensitively.
- Proven up → down → up on a scratch database: see §7.

## 5. Tests

`tests/test_staff_accounts.php` covers:
- password boundaries: 14/15 characters (9/10 since 24 Oct 2026), 72/73 bytes, CJK, accented letters, emoji at 72/76 bytes;
- guessable and context passwords refused with a reason;
- e-mail trim, lower-case, and case-insensitive duplicates (also against a deactivated account);
- forced change on first sign-in;
- 72-hour expiry, and an expired temporary password refused with its own message while a wrong password stays generic;
- reset ends open sessions and is temporary;
- the page never shows a password;
- deactivate: the session dies, sign-in is refused, open-SOS notice, a repeated deactivate is a no-op;
- the admin is always paged for SOS;
- reactivate;
- the admin cannot deactivate themselves;
- 403 for staff and residents on all 4 routes, the login redirect for visitors, 400 without a CSRF token;
- audit rows for create, reset, deactivate and reactivate, each with actor and IP, and never a password or hash;
- the history is visible on the page.

Results: see §7.

## 6. Remaining risks and open questions

- **R-1** No NFC normalisation (ext-intl off). The same passphrase typed with decomposed accents on another device would not match. Rare; enable `extension=intl` and add `Normalizer::normalize()` in `PasswordPolicy`/login together.
- **R-2** No breached-password list: the app ships no corpus and makes no outbound calls. A local HIBP-style file could be added if the owner wants one.
- **R-3** bcrypt's 72-byte cap is below NIST's 64 characters for non-ASCII passphrases. Upgrade: `PASSWORD_ARGON2ID` plus `password_needs_rehash` at login.
- **R-4** S-24: resident accounts created by the admin still start with a permanent password.
- **R-5** S-25: concurrent duplicate create gives a 500.
- **R-6** SOS notification rows still go to deactivated staff (harmless; filter in `Notification::broadcastToStaff`).
Owner's answers (15 Oct 2026), now built:
- **Q-1 → one staff role, but money is admin-only.** Staff can no longer issue penalties (`/staff/penalties/issue` gives 403, the dashboard form is gone; staff log violations as incidents), record cash payments (`/admin/payments/record` gives 403, removed from their sidebar), open payment receipts, or record expenses (already admin-only). The per-duty role matrix in §2 stays unbuilt.
- **Q-2 → 72 hours stays.**
- **Q-3 → kept 5 years after the person leaves.** Stated on the Staff Accounts page and on each staff member's Profile page. Automatic deletion is not built yet, so nothing is deleted until it is.
- **Q-4 → staff phone numbers.** Migration `0043_staff_phone` adds `users.phone`. It is set on create or from the list, audited without the number, and shown in the staff list and to residents beside the SOS button as "Call <name>: <number>" (tap-to-call, active staff only). No SMS. The staff dashboard tells whoever is on duty to keep it open at the front desk; the phone is the backup.

## 7. Verification log

- **Red (unfixed code, clean worktree at `8b6b47f`):** `test_staff_accounts.php` gave 7 passed, 11 failed, then a fatal error on the missing `temp_password_expires_at` column.
- **First green attempt (worktree = HEAD + this change only):** 17/20 files passed. The three failures were tests that still assumed the old behaviour:
  - the seeded boarder was used after `test_http_rbac` changes its password;
  - `test_wp01`'s pre-online check now (rightly) flags test accounts left on a temporary password;
  - `test_z_feature_audit` kept using a resident after an admin reset.
  All three were adjusted. The rerun is **pending**: MariaDB stopped (no crash entry in its log) before it could run.
- **Security review** (security-reviewer, read-only): no Critical or High findings. Fixed:
  - *(Med)* the password and its "temporary" mark were written in separate statements, so a failure in between left a permanent admin-known password. Both writes and the audit row are now one transaction, in `StaffController::create` and `PasswordResetService::byAdmin`.
  - *(Low)* a session opened just before the expiry could outlive it. `AuthMiddleware` now ends it.
  - *(Low)* a NUL byte would truncate bcrypt input on PHP 8.2. It is now refused.
  - *(Low)* stripping 3-letter keyboard runs refused ordinary words ("first", "power"). Runs must now be 4+ characters.

  Noted, not fixed:
  - The login's dummy hash is cost 10, so unknown e-mails would answer faster once PHP 8.4 raises the default cost to 12. This predates this change. Fix: `password_hash('x', PASSWORD_DEFAULT)`.
  - The duplicate-create race (R-5) ends in the generic error page, not a duplicate account.

  Confirmed fine: authorization and CSRF on all four routes, escaping in the view, prepared SQL, no secrets in logs, audit or flash text, migration 0042 cannot collide with the UNIQUE key, the time-zone round-trip of the expiry, and the expired-password message is not an account oracle.
