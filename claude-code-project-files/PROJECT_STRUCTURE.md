# Expected Project Structure — RJM Boardinghouse System

This is the authoritative folder layout. Everything below marked **(scaffolded)** already exists in the starter files provided — Claude Code should extend these, not recreate them from scratch. Everything marked **(Claude Code creates)** doesn't exist yet and is built during the phase named.

```
project-root/
├── CLAUDE.md                          (scaffolded) — always-loaded instructions
├── FEATURES.md                        (scaffolded) — full feature spec, read on demand
├── PHASES.md                          (scaffolded) — build plan + verification criteria
├── ARCHITECTURE.md                    (scaffolded) — connectors, sequence diagrams, ER diagram
├── PROJECT_STRUCTURE.md               (scaffolded) — this file
├── .env.example                       (scaffolded) — copy to .env, fill in locally, never commit .env
├── .gitignore                         (scaffolded)
│
├── .claude/
│   ├── settings.json                  (scaffolded) — wires up the two hooks below
│   ├── hooks/
│   │   ├── protect-files.sh           (scaffolded) — blocks edits to .env, .git/, etc.
│   │   └── lint-after-edit.sh         (scaffolded) — php -l / py_compile after every edit
│   ├── rules/
│   │   ├── php-conventions.md         (scaffolded) — auto-loads on *.php
│   │   └── python-conventions.md      (scaffolded) — auto-loads on scoring_service/*
│   └── agents/
│       └── security-reviewer.md       (scaffolded) — subagent, dispatch before sensitive phases
│
├── public/                             ← Apache web root, the ONLY thing served directly
│   ├── index.php                      (scaffolded stub) — front controller; real routing = Phase 1
│   ├── .htaccess                      (scaffolded) — rewrites all requests through index.php
│   ├── assets/
│   │   ├── css/                       (empty, scaffolded) — Phase 1+
│   │   └── js/                        (empty, scaffolded) — Phase 1+, incl. SOS/notification polling JS
│   └── uploads/                       (empty, scaffolded) — repair media + payment receipts land here
│                                         (Claude Code creates) validated filenames only, never web-executable
│
├── src/                                 ← PHP application code, not web-accessible directly
│   ├── Database.php                   (scaffolded) — the ONE shared PDO connection helper
│   ├── Controllers/                   (empty, scaffolded)
│   │   (Claude Code creates, one per resource, thin — validate → call Model/Service → respond):
│   │   AuthController.php (Ph.1) · BoarderController.php, RoomController.php, BedController.php (Ph.2)
│   │   PaymentController.php, ExpenseController.php, PenaltyController.php, LedgerController.php (Ph.3)
│   │   MaintenanceController.php (Ph.4) · SosController.php, IncidentController.php (Ph.5)
│   │   DashboardController.php, OccupancyController.php (Ph.6) · NotificationController.php (Ph.7)
│   ├── Models/                        (empty, scaffolded)
│   │   (Claude Code creates, one per table — thin DB-access classes, PDO prepared statements only)
│   │   User.php (Ph.1) · Room.php, Bed.php, BoarderProfile.php (Ph.2)
│   │   Payment.php, Expense.php, PenaltyRule.php, Penalty.php (Ph.3) · MaintenanceRequest.php (Ph.4)
│   │   SosAlert.php, Incident.php (Ph.5) · OccupancySnapshot.php (Ph.6) · Notification.php (Ph.7)
│   ├── Services/                      (empty, scaffolded)
│   │   (Claude Code creates — business logic lives here, not in controllers)
│   │   ScoringClient.php (Ph.4, calls Python /score-repair, handles the unreachable-service fallback)
│   │   VerificationClient.php (Ph.3, calls Python /verify-payment, same fallback pattern)
│   │   LedgerBuilder.php (Ph.3) · PenaltyEngine.php (Ph.3) · NotificationDispatcher.php (Ph.7)
│   ├── Middleware/                    (empty, scaffolded)
│   │   (Claude Code creates, Phase 1) AuthMiddleware.php · RoleMiddleware.php (RBAC) · CsrfMiddleware.php
│   └── Views/                         (empty, scaffolded — one subfolder per role + shared partials)
│       ├── shared/                    (scaffolded) layout.php — shared shell wiring in Tailwind + GSAP via CDN
│       ├── admin/                     (Phase 2 onward, per feature)
│       ├── staff/                     (Phase 4-5)
│       └── portal/                    (boarder-facing — Phase 2 onward)
│
├── config/                             ← non-secret app config if needed beyond .env (Claude Code creates as needed)
│
├── database/
│   └── migrations/                    (scaffolded — all 10 files, apply in filename order)
│       0001_create_users.sql                          (Phase 1)
│       0002_create_rooms_and_beds.sql                 (Phase 2)
│       0003_create_boarder_profiles_and_status_log.sql (Phase 2)
│       0004_create_payments.sql                       (Phase 3)
│       0005_create_expenses.sql                       (Phase 3)
│       0006_create_penalty_rules_and_penalties.sql    (Phase 3)
│       0007_create_maintenance_requests.sql           (Phase 4)
│       0008_create_sos_alerts_and_incidents.sql       (Phase 5)
│       0009_create_occupancy_snapshots.sql            (Phase 6)
│       0010_create_notifications.sql                  (Phase 7)
│
├── scoring_service/                    ← Python microservice, localhost:5000, stateless
│   ├── main.py                        (scaffolded, working stub) — FastAPI app, binds 127.0.0.1 only
│   ├── schemas.py                     (scaffolded) — Pydantic request/response models, exact contracts
│   ├── scoring.py                     (scaffolded stub → Phase 4 real logic, FEATURES.md §1)
│   ├── verification.py                (scaffolded stub → Phase 3 real logic, FEATURES.md §7)
│   ├── requirements.txt               (scaffolded)
│   └── venv/                          (Claude Code creates locally — gitignored, never committed)
│
├── logs/                               (scaffolded, empty)
│   (Claude Code creates at runtime) app.log · scoring_service.log — timestamped, leveled
│
└── tests/
    └── playwright/                    (scaffolded, empty — see its README.md for naming convention)
        (Claude Code creates, one script per phase's verification criteria from PHASES.md)
```

## Why it's laid out this way
- **`public/` is the only web-servable folder.** `src/`, `database/`, `scoring_service/`, `logs/` all sit outside the web root so they're never directly reachable by URL — this is a hard requirement, not a style choice.
- **One connection helper, one microservice entry point.** `src/Database.php` and `scoring_service/main.py` are already scaffolded precisely so no later phase reinvents either — every Model goes through the former, every scoring/verification call goes through the latter's two endpoints.
- **Migrations are numbered in build order, not alphabetically-by-topic**, so `0001`→`0010` doubles as a readable history of which phase introduced which table.
- **The stub scoring service already runs and answers both contracts** (`/score-repair`, `/verify-payment`) with dummy-but-valid responses — Phase 0's "prove the connector works end-to-end" deliverable from `ARCHITECTURE.md` §10 is done before Phase 1 even starts. Phases 3 and 4 replace the stub logic, not the endpoints or schemas.
- **Views are split by role** (`admin/`, `staff/`, `portal/`) sharing one `shared/` layout, matching the frontend page map in `ARCHITECTURE.md` §8 — avoids three unrelated template sets growing independently.

## Data classification: what's editable, and what never is
`QA-VALIDATION-REPORT.md`'s CRUD audit found this was implicit rather than decided. It's now explicit:

- **Event records — append-only, forever. No edit, no delete, not even for admins.** `payments`, `expenses`, `penalties`, `boarder_status_log`, `login_attempts`, `occupancy_snapshots`, `notifications`. These are records of something that *happened* — rewriting history defeats the point of a financial/security audit trail. `maintenance_requests` and `sos_alerts` fall in this category too, but with one exception: their `status` field transitions (open→resolved, active→acknowledged→resolved) since that's the event's own lifecycle, not a rewrite of what was originally reported.
- **Configuration data — editable, because it's current state, not a past event.** `rooms`, `beds`, `penalty_rules`, and a boarder's `name`/`email` on `users`. Getting a room's price or a boarder's email wrong is a data-entry mistake, not history to preserve.
- **Guarded, not blocked, delete** — only where deleting can't silently corrupt something else: a room with zero beds, a bed that's vacant. Checked in the Model layer *before* the query runs (see `Room::delete()`, `Bed::delete()`), never left for a foreign-key constraint to catch — that's the exact lesson from Bug #2 in `QA-VALIDATION-REPORT.md`.
- **Guarded, not deleted, deactivation** for `penalty_rules` — `penalties.rule_id` references it, so a rule that's ever been applied can't be hard-deleted without either violating that FK or orphaning the audit trail. The `active` flag (present since the very first migration, but never wired to anything until this pass) is the correct mechanism: stop new penalties from using it, keep every rule a past penalty pointed to intact.
