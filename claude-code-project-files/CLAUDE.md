# RJM Boardinghouse Rent, Maintenance & Security Management System

Localhost-by-default capstone web app. Read this file every session — it's intentionally short. Details live in the linked files below and are read on demand, not preloaded.

## What this is
A boardinghouse management system: rent/payments, maintenance requests with local priority scoring, emergency SOS/incidents, expense tracking, occupancy trends, bed-level room mapping, penalty automation, and notifications. Full functional spec and per-feature status: **`FEATURES.md`**. Architecture, request flows, DB schema: **`ARCHITECTURE.md`**. Where files live and which document owns which topic: **`PROJECT_STRUCTURE.md`**. The current work plan (audit fixes, packages WP-0…WP-20, with status): **`../IMPLEMENTATION-PLAN-2026-10-14.md`**. `PHASES.md` is the original build history, not a to-do list.

## Non-negotiable constraints
- **Localhost by default.** No cloud hosting, no permanent public deployment. The one permitted exposure is the temporary tunnel the user starts by hand with `start-online.bat` (`bin/go-online.ps1`): the app still runs and stores everything on this machine, and it must behave exactly as before when `TRUSTED_PROXIES` / `APP_BASE_URL` are unset.
- **No outbound internet calls from the running app.** Repair priority scoring and payment checks are local rule-based PHP — never a call to an external LLM/API. The only network call the app makes is to the optional local Ollama server on `localhost:11434`. Payments are never auto-approved (see Hard rules). See `ARCHITECTURE.md` §3.2.
- **Stack:** HTML/CSS/JS frontend (Tailwind CSS for styling, GSAP for animation — see `UI-LIBRARY-EVALUATION.md`) · PHP backend (system of record, only thing the browser talks to) · MariaDB. Repair-priority scoring and payment checks are local PHP rules (`src/Services/ScoringClient.php`, `BillingService`); there is no Python service.

## How we work each session
The audit-fix work follows `../IMPLEMENTATION-PLAN-2026-10-14.md` §1 (working rules) — read it first; it names the package that is next and what is already done.
1. Plan before you build: confirm which work package is next, and don't reach ahead into later ones.
2. One commit per package, `WP-n: …`, holding that package's code, migration (+ `.down.sql`) and tests.
3. Write the regression test first and see it fail on the unfixed code, then fix. Show the actual `php tests/run.php` output, not an assertion that it works.
4. For anything touching auth, payments, sessions, or file uploads, dispatch the `security-reviewer` subagent (`.claude/agents/security-reviewer.md`) for a review pass before moving on.
5. Never run migrations against the live database (`rjm_boardinghouse`) from an agent session; they run on `_test` or scratch databases, and the live one migrates when the owner starts `start-system.bat`.
6. Update the docs in the same change: the plan's status table, plus `FEATURES.md` / `ARCHITECTURE.md` / `README.md` if behaviour changed. Add a dated entry to `PHASES.md`'s Progress Log only for rules or decisions worth keeping as history.

If you're ever unsure whether to keep going or ask: prefer asking when the ambiguity is a constraint (localhost/offline, tech stack) rather than an implementation detail (variable names, file layout) — pick a reasonable default for the latter and write it down in the plan.

## Commands (can't be guessed from the code)
- PHP dev server: `php -d upload_max_filesize=20M -d post_max_size=25M -S 127.0.0.1:8000 -t public public/index.php` (the router argument is required; the two `-d` flags let 20 MB receipts/repair videos through on any PHP install; on Windows just run `start-system.bat`). `php-curl` is only needed for the optional Ollama assistant. On this Windows machine `php` is `C:\xampp\php\php.exe` and is not on the bash PATH.
- Online demo: `start-online.bat` (repo root) — same checks, then a Cloudflare quick tunnel + server on port 8010. Never start it without the user asking; it makes the sign-in page reachable from the internet. It refuses to start (`bin/pre-online-check.php`) while a seeded account still has its default password or the app uses MariaDB `root` without a password.
- DB login: `Database.php` reads `DB_*` from the environment, then from the git-ignored `.env`; `php bin/setup-db-user.php` (run by the owner, not by an agent) creates the dedicated `boardinghouse_app` login and writes `.env`.
- PHP lint a file: `php -l path/to/file.php`
- DB: `php database/migrate.php` applies new migrations and records them in `schema_migrations`; `--status` lists applied/pending; `--rollback[=N]` undoes the last N (needs a `.down.sql`, present from migration `0031`). Never edit an applied migration.
- Tests: `php tests/run.php` (throwaway `_test` database + two private servers on :8099/:8098, random per-run passwords; never run test files directly). `php tests/run.php wp01` runs only files whose name contains `wp01`; add `--with-ai` to include the Ollama test. A known, not-yet-fixed defect is printed `[KNOWN]` and does not fail the run.
- Browser verification: drive the running localhost app in a browser, not manual eyeballing

## Hard rules (also backstopped by hooks in `.claude/settings.json`)
- PDO prepared statements only. Never string-concatenated SQL.
- `password_hash()` / `password_verify()` only for credentials.
- CSRF token on every state-changing form.
- Never auto-approve a payment: every boarder payment waits for an admin (amount mismatches are only flagged).
- Never write a real or default password, API key or token into a tracked file, including docs (`tests/test_wp01_credentials.php` greps for the old published ones). Seeded passwords are random and printed once.

## Path-scoped conventions
Coding conventions for PHP live in `.claude/rules/php-conventions.md` and load automatically when you touch `*.php` files.

## File map
| File | Read it when |
|---|---|
| `../IMPLEMENTATION-PLAN-2026-10-14.md` | Start of every session — which work package is next, decisions already made, status |
| `../FUNCTIONALITY-AUDIT-2026-10-14.md` | You need the evidence or test idea behind a finding (F-xx, T-xx) |
| `PROJECT_STRUCTURE.md` | Creating any new file, or finding which document owns a topic |
| `FEATURES.md` | Implementing or changing any of the 11 features — spec plus current status |
| `ARCHITECTURE.md` | Request flows, connectors, schema overview |
| `README.md` | Billing rules (the single authoritative description), setup, going online |
| `UI-LIBRARY-EVALUATION.md` | Building any UI/animation — why Tailwind + GSAP were chosen over the alternatives |
| `AI-ASSISTANT-PLAN.md` | Touching the assistant (Ctrl+K drawer, `/api/assistant/*`) |
| `PHASES.md`, `FINAL-REPORT.md`, `*-REPORT.md` | History only; they describe the original stack (with a Python service) and are not current behaviour |
| `.claude/rules/php-conventions.md` | Auto-loads when editing `*.php` |
| `.claude/agents/security-reviewer.md` | Dispatch explicitly before closing a package touching auth/payments/uploads |

## What NOT to do
- Don't reproduce the mcp-builder / canvas-design / web-artifacts-builder skill patterns here — this is a server-rendered local PHP app, not an MCP server, a poster, or a claude.ai artifact.
- Don't add SMS, cloud storage, or a real LLM API call anywhere without the user explicitly changing the localhost-only constraint first. E-mail is the one approved exception in principle (owner decision, 14 Oct 2026: an outbox queue that sends nothing until an admin enables SMTP in Settings, WP-9). It is not built yet; until it is, don't add any mail sending, and when it lands this rule is updated with it.
