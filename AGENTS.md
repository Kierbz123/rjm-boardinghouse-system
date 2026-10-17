# AGENTS.md — RJM Boardinghouse

Standing instructions for any agent working in this repo. Code lives in
`claude-code-project-files/`; read its `CLAUDE.md` for conventions and its
`README.md` for the full setup, billing rules and demo walkthrough.

**Current work:** the audit fixes tracked in `IMPLEMENTATION-PLAN-2026-10-14.md` (this folder), on branch
`fix/functionality-audit-2026-10`. It lists the work packages (WP-0…WP-20), their order, the rules for
working on them and their status; the evidence behind each is in `FUNCTIONALITY-AUDIT-2026-10-14.md`.
Several sessions may be editing the same working tree, so keep edits small and targeted, and don't
revert changes you did not make. Which document owns which topic: `claude-code-project-files/PROJECT_STRUCTURE.md`.

## Project shape

- **Backend**: PHP 8.2+, no framework, custom PSR-4 autoloader (`src/autoload.php`, which also sets the app time zone), no Composer.
- **Database**: MariaDB/MySQL. Schema in `database/migrations/*.sql`, applied and tracked by `php database/migrate.php` (never edit an applied migration; add a new numbered file). From `0031` each migration also has a `NNNN_name.down.sql`; `migrate.php --status` lists applied/pending and `--rollback[=N]` undoes the last N.
- **Frontend**: server-rendered PHP views, Tailwind CSS + GSAP served locally from `public/assets/`. Node is only needed to recompile `app.css`.
- **No Python service** — repair scoring (`src/Services/ScoringClient.php`) and payment checks are PHP. Optional local Ollama powers the AI helper buttons.
- Non-negotiable: **localhost by default**, no outbound calls from the running app. The only permitted exposure is the temporary tunnel the user starts by hand with `start-online.bat` (see README "Going online for a demo"); never start it unasked.

## Run it

Windows/XAMPP: start MySQL, then double-click `start-system.bat` (repo root). It checks PHP/MySQL, runs migrations and starts
`php -S 127.0.0.1:8000 -t public public/index.php` — the router argument is required so private uploads are served through the app.

Any OS (from `claude-code-project-files/`):

```bash
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=rjm_boardinghouse DB_USER=root DB_PASS=
php database/migrate.php
php database/seed.php      # demo accounts, first time only
php -S 127.0.0.1:8000 -t public public/index.php
```

`src/Database.php` takes `DB_*` settings from real environment variables first, then fills any that are still unset from `claude-code-project-files/.env` (git-ignored; `.env.example` documents it; `tests/run.php` never reads it). With nothing set it logs in as `root` with no password on `127.0.0.1` (XAMPP's default), and a stale `.env` login that the server refuses falls back to that, loudly (logged, flagged on the admin dashboard, and `start-online.bat` refuses to go online). `php bin/setup-db-user.php` creates the dedicated `boardinghouse_app` login and writes it to `.env`; the owner runs it, agents don't create database users. Seeded demo accounts get random passwords printed once by `seed.php` and must change them at first sign-in; never write a password literal into a tracked file (`tests/test_wp01_credentials.php` greps for them).

## Test it

```bash
php tests/run.php
```

Rebuilds a throwaway `<DB_NAME>_test` database, seeds it with fresh random passwords (exported to the tests as `TEST_*_PASSWORD`),
starts two private servers on :8099 and :8098 (the second one is for concurrency tests) and runs every `tests/test_*.php`.
`php tests/run.php wp01` runs only files whose name contains `wp01`; `--with-ai` adds the Ollama test (needs Ollama running).
A defect that is known and not yet fixed prints `[KNOWN]` and does not fail the run.
**Never run a `tests/test_*.php` file directly** — several write data and only refuse to run outside a `_test` database where they check for it.

## Known gotchas

1. MariaDB on Linux may use `unix_socket` auth for `root` (no TCP login) — create a dedicated user there.
2. The repo sits inside XAMPP's `htdocs`; the root `.htaccess` (`Require all denied`) keeps Apache from serving it. Don't remove it.
3. Uploads live in `storage/uploads/` (outside `public/`) and are only served by `Uploads::serve()` after a permission check.
4. Rebuilding CSS after editing views (from `claude-code-project-files/`, needs Node; if `node_modules` is missing run `npm install --no-save tailwindcss@4 @tailwindcss/cli@4` first, and don't commit it): `npx @tailwindcss/cli -i public/assets/css/source/input.css -o public/assets/css/app.css --minify`.
5. On this Windows machine `php` is `C:\xampp\php\php.exe` and is not on the bash PATH; call it by full path.
6. `.claude/launch.json` (repo root, untracked) defines a preview server on :8010 for the Claude desktop app; `:8010` is also the port `start-online.bat` uses, so don't run both.
