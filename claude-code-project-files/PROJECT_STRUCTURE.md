# Project Structure — RJM Boardinghouse System

Where everything lives, and where a new file belongs. Paths are relative to the application folder `claude-code-project-files/` unless they start with `../` (the repository root).

```
rjm-boardinghouse-final/                 ← repository root
├── AGENTS.md                            standing instructions for any agent (setup, tests, gotchas)
├── start-system.bat                     Windows launcher: checks PHP/MySQL, migrates, starts the app on :8000
├── start-online.bat                     same checks, then a temporary public tunnel (calls bin\go-online.ps1)
├── .htaccess                            "Require all denied" so XAMPP's Apache never serves the repository
├── AUDIT-REPORT.md, DESIGN.md           see "Documentation map" below
├── FUNCTIONALITY-AUDIT-2026-10-14.md    latest audit (findings F-01…F-31)
├── IMPLEMENTATION-PLAN-2026-10-14.md    the work plan that fixes those findings, with live status
│
└── claude-code-project-files/           ← the application
    ├── CLAUDE.md                        short rules, loaded every session
    ├── README.md                        setup, running, billing rules, demo walkthrough, going online
    ├── ARCHITECTURE.md · FEATURES.md · PROJECT_STRUCTURE.md   design, feature spec, this file
    ├── PHASES.md                        original build phases and the long progress log (history)
    ├── *-PLAN.md, *-REPORT.md, ...      plans and point-in-time reports (see map below)
    ├── .env.example                     documents the DB_* variables; .env (git-ignored) is read by Database.php
    ├── .gitignore
    │
    ├── .claude/                         AI-assisted-development config only (not part of the app)
    │   ├── settings.json                wires the two hooks below
    │   ├── hooks/                       protect-files.sh, lint-after-edit.sh
    │   ├── rules/php-conventions.md     auto-loads when editing *.php / *.sql
    │   └── agents/security-reviewer.md  subagent for changes touching auth, payments, uploads
    │
    ├── public/                          ← the ONLY folder the web server serves
    │   ├── index.php                    front controller, route table (86 routes), security headers
    │   └── assets/
    │       ├── css/app.css              compiled Tailwind (source: css/source/input.css)
    │       ├── js/                      app.js, ai-assistant.js, vendor/ (GSAP, ScrollTrigger, qrcode)
    │       ├── fonts/                   Onest, peso-sign fonts and their LICENSE files
    │       └── images/                  landing-bg.jpg
    │
    ├── src/                             PHP, namespace App\ (PSR-4 autoloader in src/autoload.php)
    │   ├── Database.php                 the single PDO connection helper
    │   ├── Controllers/                 Assistant, Auth, Boarder, Dashboard, Expense, Incident, Inquiry,
    │   │                                Landing, Ledger, Maintenance, Notification, Occupancy, Payment,
    │   │                                Penalty, Profile, Room, Sos, Staff
    │   ├── Models/                      Bed, BoarderProfile, ElectricityBill, Expense, Incident, Inquiry,
    │   │                                LoginAttempt, MaintenanceRequest, Notification, OccupancySnapshot,
    │   │                                Payment, Penalty, PenaltyRule, Room, SosAlert, User
    │   ├── Services/                    Assistant, Billing, Ledger builder, NotificationDispatcher,
    │   │                                OllamaClient, PasswordReset, PaymentAllocation, PaymentReview,
    │   │                                PenaltyEngine, ScoringClient (rule-based repair priority)
    │   ├── Middleware/                  AuthMiddleware, RoleMiddleware
    │   ├── Support/                     Csrf, ErrorPage, Icon (the one SVG icon set), Logger, NavRegistry,
    │   │                                NotificationType, Router, TrustedProxy, Uploads
    │   └── Views/                       admin/, staff/, portal/ (boarder), shared/ (layout, nav, login,
    │                                    landing, profile, notifications, error)
    │
    ├── database/
    │   ├── migrations/                  NNNN_name.sql in order (0001…); from 0031 also NNNN_name.down.sql
    │   ├── migrate.php                  applies pending migrations; --status; --rollback[=N]
    │   ├── seed.php                     demo accounts (random passwords, printed once) + Room 101 with two beds
    │   └── backups/                     mysqldump files from earlier changes (git-ignored)
    │
    ├── storage/uploads/{receipts,maintenance}/   private files, never inside public/ (git-ignored)
    ├── logs/                            app.log, online-*.log (git-ignored)
    ├── bin/                             go-online.ps1 (tunnel launcher), pre-online-check.php (its safety
    │                                    check), setup-db-user.php (creates the dedicated DB login → .env)
    └── tests/                           run.php (runner), lib.php (helpers), helpers/, test_*.php
```

Not present, and what older documents may still say: there is **no `scoring_service/`** (the Python service was removed in B6), **no `public/uploads/`** (uploads moved to `storage/uploads/`), **no `tests/playwright/`** (the never-run browser scripts were removed in B6; coverage is `php tests/run.php`), and **no `config/` contents** (settings come from environment variables).

## Where does a new file go?

| Adding… | Put it in | Notes |
|---|---|---|
| A page or action | route in `public/index.php`, handler in `src/Controllers/`, view in `src/Views/<role>/` | A form or link must exist for every route (`tests/test_z_feature_audit.php` enforces it) |
| Business rule | `src/Services/` | Controllers stay thin |
| Table access | `src/Models/` | Prepared statements only |
| Schema change | `database/migrations/NNNN_name.sql` + `NNNN_name.down.sql` | Never edit an applied migration |
| Test | `tests/test_<topic>.php` using `tests/lib.php` | Run through `php tests/run.php`, never directly |
| Script run by hand | `bin/` (launchers) or `database/` (data tools) | |
| User upload | `storage/uploads/<subdir>/`, add the subdir to `Uploads::SUBDIRS` | Served only through `Uploads::serve()` |

## Documentation map

One authoritative document per topic. Everything else is either a plan or a dated record; do not treat records as current behaviour.

| Document | Kind | Authoritative for |
|---|---|---|
| `../AGENTS.md` | current | Setup, running, testing, gotchas for any agent |
| `README.md` | current | Setup for people, **billing rules**, demo walkthrough, going online, deploying, known limitations |
| `CLAUDE.md` | current | Non-negotiable constraints, hard rules, commands |
| `ARCHITECTURE.md` | current | Components, request flows, schema overview |
| `FEATURES.md` | current spec | What each of the 11 features should do, with an implementation-status table |
| `PROJECT_STRUCTURE.md` | current | This file |
| `UI-LIBRARY-EVALUATION.md` | decision record | Why Tailwind CSS + GSAP; how to rebuild the CSS |
| `../IMPLEMENTATION-PLAN-2026-10-14.md` | **active plan** | The work packages WP-0…WP-20, their order, decisions and status |
| `../FUNCTIONALITY-AUDIT-2026-10-14.md` | latest audit | Findings F-01…F-31 and test ideas T-01…T-37 |
| `AI-ASSISTANT-PLAN.md` | plan (mostly done) | Role-aware assistant: design, phases A0–A7, owner decisions |
| `CCTV-PEOPLE-COUNTER-PLAN.md` | plan (not started) | Optional camera feature and its privacy rules |
| `IMPROVEMENT-PLAN.md` | plan (partly superseded) | Older per-page suggestion list; open items now tracked by the work packages |
| `PHASES.md` | history | Original phases 0–8 and the progress log of every later change |
| `FINAL-REPORT.md` | history | Audit-fix batches B0–B7 (3 Oct 2026) |
| `FEATURE_IMPLEMENTATION_AUDIT.md` | history | Screen → route → table trace of all features (10 Oct 2026) |
| `../AUDIT-REPORT.md` | history | Independent audit of the original `main` branch (4 Oct 2026) |
| `QA-VALIDATION-REPORT.md`, `DEBUGGING-REPORT.md` | history | Early QA and debugging passes on the original stack (with the Python service) |
| `DEMO-ACCOUNTS.md` | current | Demo accounts, what each role can do, resetting passwords |
| `../DESIGN.md` | uncertain | A design-token analysis of another site's UI (Airbnb); not referenced by the app. Purpose unconfirmed, kept |
| `public/assets/fonts/LICENSE*.txt` | licences | Third-party font licences — keep |

## Data classification: what's editable, and what never is

Decided after the first QA pass (`QA-VALIDATION-REPORT.md`) and still the rule.

- **Event records — no edit and no delete in the UI, even for admins:** `payments`, `expenses`, `penalties`, `boarder_status_log`, `occupancy_snapshots`, `incidents`, `maintenance_requests`, `sos_alerts`. They record something that *happened*. Only their own lifecycle changes: a payment is approved, rejected or reversed; a penalty is paid or waived (with who and why); a repair or SOS alert moves through its statuses.
  - `expenses` have no edit or void yet (audit F-19, WP-12 will add void rather than delete).
  - `login_attempts` is pruned by the app on a successful login; `notifications` belong to the recipient, who may mark them read, pin them or delete them. Neither is an audit trail.
- **Configuration data — editable, because it's current state:** `rooms`, `beds` (rename), `penalty_rules` (amount, active flag), a resident's name, e-mail and contact details.
- **Guarded delete** only where deleting cannot corrupt anything, checked in the Model layer *before* the query (never left for a foreign-key error): a room with no beds and no electricity bills, a bed that is vacant, a resident with no payments, penalties or electricity charges. A resident who has any of those is **archived** (login blocked, bed freed, restorable), never erased. Known gap: a resident with only repairs, incidents or SOS history is still hard-deleted together with that history (F-04, WP-4).
- **Deactivate, don't delete:** `penalty_rules` — `penalties.rule_id` references them, so a rule that has ever been applied is only switched off.
