# AGENTS.md — RJM Boardinghouse

Standing instructions for any agent working in this repo. Code lives in
`claude-code-project-files/`; read its `CLAUDE.md` for conventions and its
`README.md` for the full setup, billing rules and demo walkthrough.

## Project shape

- **Backend**: PHP 8.2+, no framework, custom PSR-4 autoloader (`src/autoload.php`, which also sets the app time zone), no Composer.
- **Database**: MariaDB/MySQL. Schema in `database/migrations/*.sql`, applied and tracked by `php database/migrate.php` (never edit an applied migration; add a new numbered file).
- **Frontend**: server-rendered PHP views, Tailwind CSS + GSAP served locally from `public/assets/`. Node is only needed to recompile `app.css`.
- **No Python service** — repair scoring (`src/Services/ScoringClient.php`) and payment checks are PHP. Optional local Ollama powers the AI helper buttons.
- Non-negotiable: **localhost only**, no outbound calls from the running app.

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

`.env` is **not** loaded by the app; it only documents the variables. `src/Database.php` reads real environment variables and defaults to `root` with no password on `127.0.0.1` (XAMPP's default).

## Test it

```bash
php tests/run.php
```

Rebuilds a throwaway `<DB_NAME>_test` database, starts a private server on :8099 and runs every `tests/test_*.php`.
**Never run a `tests/test_*.php` file directly** — several write data and only refuse to run outside a `_test` database where they check for it.

## Known gotchas

1. MariaDB on Linux may use `unix_socket` auth for `root` (no TCP login) — create a dedicated user there.
2. The repo sits inside XAMPP's `htdocs`; the root `.htaccess` (`Require all denied`) keeps Apache from serving it. Don't remove it.
3. Uploads live in `storage/uploads/` (outside `public/`) and are only served by `Uploads::serve()` after a permission check.
4. Rebuilding CSS after editing views: `npx @tailwindcss/cli -i public/assets/css/source/input.css -o public/assets/css/app.css --minify`.
