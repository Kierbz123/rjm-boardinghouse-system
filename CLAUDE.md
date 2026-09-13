# RJM Boardinghouse Rent, Maintenance & Security Management System

Localhost-only capstone web app. Read this file every session — it's intentionally short. Details live in the linked files below and are read on demand, not preloaded.

## What this is
A boardinghouse management system: rent/payments, maintenance requests with local priority scoring, emergency SOS/incidents, expense tracking, occupancy trends, bed-level room mapping, penalty automation, and notifications. Full functional spec: **`FEATURES.md`**. Architecture, connector contracts, sequence diagrams, DB schema: **`ARCHITECTURE.md`**. Phase-by-phase build plan and how to verify each phase: **`PHASES.md`**.

## Non-negotiable constraints
- **Localhost only.** No cloud hosting, no public deployment.
- **No outbound internet calls from the running app.** Both "AI" features (repair priority scoring, payment verification) are local rule-based logic — never a call to an external LLM/API. See `ARCHITECTURE.md` §3.2 for the exact contracts and what to do if a local service is unreachable (fail closed, never auto-approve).
- **Stack:** HTML/CSS/JS frontend (Tailwind CSS for styling, GSAP for animation — see `UI-LIBRARY-EVALUATION.md`) · PHP backend (system of record, only thing the browser talks to) · a small **stateless** Python microservice for the two scoring/verification features only · MariaDB (PHP is the only writer).

## How we work each session
1. Start each phase in **Plan Mode** (`Shift+Tab` until the status bar shows `⏸ plan mode on`). Read `PHASES.md`, confirm which phase is next, and don't implement until the plan looks right.
2. Implement only that phase's scope. Don't reach ahead into later phases.
3. Run that phase's verification criteria from `PHASES.md` before calling it done — show the actual test output, not just an assertion that it works.
4. For anything touching auth, payments, sessions, or file uploads, dispatch the `security-reviewer` subagent (`.claude/agents/security-reviewer.md`) for a review pass before moving on.
5. Update `PHASES.md`'s Progress Log (status + any assumption you made) in the same turn you finish a phase.
6. Commit with a message naming the phase.

If you're ever unsure whether to keep going or ask: prefer asking when the ambiguity is a constraint (localhost/offline, tech stack) rather than an implementation detail (variable names, file layout) — pick a reasonable default for the latter and note it in the Progress Log.

## Commands (can't be guessed from the code)
- PHP dev server: `php -S localhost:8080 -t public` — requires the **`php-curl`** extension enabled (`ScoringClient`/`VerificationClient` depend on it; a bare `apt install php-cli` does NOT include it — `apt install php-curl` separately, or use a full XAMPP/WAMP bundle which includes it by default).
- Python service: `cd scoring_service && python3 -m venv venv && source venv/bin/activate && pip install -r requirements.txt && python3 main.py` (runs on `localhost:5000`)
- PHP lint a file: `php -l path/to/file.php`
- DB: apply migrations in `database/migrations/` in filename order against a local MariaDB instance (`mysql -u root boardinghouse < database/migrations/0001_....sql`)
- Browser verification: use the `webapp-testing` skill (Playwright) against the running localhost app, not manual eyeballing

## Hard rules (also backstopped by hooks in `.claude/settings.json`)
- PDO prepared statements only. Never string-concatenated SQL.
- `password_hash()` / `password_verify()` only for credentials.
- CSRF token on every state-changing form.
- Python service binds `127.0.0.1` only, never `0.0.0.0`.
- Never auto-approve a payment, or upgrade a repair request's priority, when the scoring/verification service is unreachable — record it as pending/medium and flag for human review instead.

## Path-scoped conventions
Coding conventions for PHP and Python live in `.claude/rules/` and load automatically only when you're touching matching files — see `.claude/rules/php-conventions.md` and `.claude/rules/python-conventions.md` rather than duplicating them here.

## File map
| File | Read it when |
|---|---|
| `PROJECT_STRUCTURE.md` | Creating any new file — confirms where it belongs and what's already scaffolded vs. still to build |
| `UI-LIBRARY-EVALUATION.md` | Building any UI/animation — why Tailwind + GSAP were chosen over the alternatives |
| `FEATURES.md` | Implementing any of the 11 features — full functional spec per feature |
| `ARCHITECTURE.md` | Working on anything crossing PHP↔Python↔MariaDB, or need the DB schema / sequence diagrams |
| `PHASES.md` | Start of every session — confirms current phase and its verification criteria |
| `.claude/rules/php-conventions.md` | Auto-loads when editing `*.php` |
| `.claude/rules/python-conventions.md` | Auto-loads when editing files under `scoring_service/` |
| `.claude/agents/security-reviewer.md` | Dispatch explicitly before closing a phase touching auth/payments/uploads |

## What NOT to do
- Don't reproduce the mcp-builder / canvas-design / web-artifacts-builder skill patterns here — this is a server-rendered local PHP/Python app, not an MCP server, a poster, or a claude.ai artifact.
- Don't add SMS/email notifications, cloud storage, or a real LLM API call anywhere without the user explicitly changing the localhost-only constraint first.
