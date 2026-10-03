# RJM Boardinghouse — audit, fixes and redesign: final report (3 October 2026)

All work is on the git branch **`audit-fixes`**, one commit per batch, so every change can be reviewed or reverted on its own.
Setup, running, testing and deployment are in `README.md`; demo logins are in `DEMO-ACCOUNTS.md`.

## Verification: before and after

| Check | Before (Phase 0) | After (Phase 4) |
|---|---|---|
| PHP lint | 80 files, 0 errors | 84 files, 0 errors |
| Automated tests | 1 script safe to run (the others wrote to the **live** database); Playwright never runnable | `php tests/run.php`: **208 checks in 5 files**, all passing, on a throwaway `_test` database |
| Role walkthrough | not done | admin, staff and boarder logged in through the browser; dashboards, payments and access rules checked |
| Project files reachable through XAMPP Apache | `.env`, logs, `.git`, source, and debug scripts that ran | all return 403 |

Not verified with tooling: a full Lighthouse/contrast audit and real mobile devices; contrast was checked by calculation for the colour tokens.

## What was fixed (by batch)

| Batch | Main fixes |
|---|---|
| B0 | Migration runner with history (`schema_migrations`); test runner on an isolated database; HTTP permission tests |
| B1 | Apache exposure closed; security headers + CSP; hardened sessions (idle timeout, logout on password change/deactivation); login throttling per email and IP; emails masked in logs |
| B2 | Maintenance submit crash (500); PHP vs database clock mismatch; boarders could read everyone's incident reports; SOS notices and duplicate alerts; empty penalty-rule dropdown |
| B3 | Billing rebuilt to the owner's rules: monthly rent from move-in, carried over, prorated; every payment needs an admin; reversals restore balances; late fees never stack; ledger CSV fixed |
| B4 | Residents with payment history are archived, never erased; strict data validation; bed moves are all-or-nothing; room capacity enforced |
| B5 | Inquiries stored durably with spam protection; a request that could freeze the app removed; one notification poll per page; messages always shown; AI endpoints limited |
| B6 | Unused Python service, dead login flow, debug scripts and 121 unused files removed; one shared repair-scoring list; better launcher |
| B7 | Staff Accounts page; private receipts and repair photos; real landing statistics; README rewritten |
| Phase 3 | Lumora-based design system (Onest font served locally, ink/warm palette, pill buttons, tokens); every page re-themed; proper 403/404/500 pages; landing page rebuilt with the loader, cursor reveal, room cards and real numbers |
| Phase 4 | Removed screen copy that still promised automatic payment approval |

## What was removed

The Python scoring service, the "approve login from your phone" flow (and its table), `VerificationClient`, six unused methods, debug and cookie dump files, the never-run Playwright scripts, third-party agent-skill folders, duplicate `AGENTS.md` / `start-system.bat`, the fake-receipt seed script, the landing page's external fonts, CDN videos and map iframe, and three demo "Profile Tester" accounts in the database. Every deletion went through git and can be restored.

## Database changes (all through migrations, live database backed up first)

`0022` rent charges + one-late-fee-per-month key, `0023` inquiries table, `0024` drop the unused QR table. Backups are in `database/backups/` (git-ignored).

## What remains / recommended next steps

1. **Before real use:** change the three demo passwords, give MySQL's app user a password, and remove the "Log in with Admin/Staff/Boarder account" shortcut buttons on the login page (demo only; they also don't fill the form).
2. **Deeper per-page polish (the rest of D4):** many views still carry inline styles, emoji icons and all-caps labels; the sidebar keeps its own style block; tables don't yet share a sort/filter component; skeleton loaders exist in the design system but aren't wired into pages.
3. **CSP** still needs `'unsafe-inline'` because views use inline scripts; moving them into files would allow a strict policy.
4. **No scheduler:** late-fee checks and rent reminders are run by an admin from the Penalties page.
5. **Accessibility:** run an automated audit (e.g. Lighthouse/axe) and a keyboard-only pass on every page; fix any contrast leftovers in older inline styles.
6. **Historical docs** (`ARCHITECTURE.md`, `PHASES.md`, QA/debugging reports) describe the original design, including the removed Python service.
7. The login page's "open on your phone" QR only works if the server is reachable from the phone; `start-system.bat` serves on `127.0.0.1` by design.
