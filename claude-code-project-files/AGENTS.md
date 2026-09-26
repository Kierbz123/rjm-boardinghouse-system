# AGENTS.md — RJM Boardinghouse

Standing instructions for any agent working in this repo. For coding conventions,
architectural constraints, and the "why" behind this stack, read `CLAUDE.md` first —
this file is specifically about getting the app **running** locally.

Every command sequence below was actually executed and verified working end-to-end
(migrations → seed → live HTTP requests → DB checks) during development, not copied
from documentation. The gotchas called out in **Known Issues** are real bugs this
project's own `.env`/README combination has — read that section before debugging
a connection failure yourself.

## Project shape

- **Backend**: PHP 8.3, no framework, custom PSR-4 autoloader (`src/autoload.php`), no Composer.
- **Database**: MariaDB. Schema lives in `database/migrations/*.sql`, applied in filename order.
- **AI scoring microservice**: Python/FastAPI in `scoring_service/`, stateless, called over HTTP by the PHP app. The app must keep working if this is down (see Known Issues → graceful degradation).
- **Frontend**: server-rendered PHP views, Tailwind CSS v4 + GSAP, both **vendored locally** under `public/assets/` — no CDN, no Node build step at runtime. Node is only needed if you're editing `public/assets/css/source/input.css` and need to recompile.
- Non-negotiable per `CLAUDE.md`: **localhost only**, no outbound calls from the running app.

## Prerequisites

- PHP 8.3+ with `pdo_mysql`, `mysqli`, `mbstring`, `json` extensions
- MariaDB (10.x)
- Python 3.12+
- Node.js — **only** if you need to rebuild `app.css` after editing views or `input.css`; not required to run the app

## 1. Start MariaDB

```bash
service mariadb start   # or: mysql.server start / systemctl start mariadb, depending on OS
```

## 2. Create the database and a dedicated app user

**Do not point the app at `root`.** MariaDB's default `root` account uses
`unix_socket` auth and cannot log in over TCP even with a blank password — the
app will fail to connect with "Access denied for user 'root'@'localhost'" if you
try. Create a real user instead:

```sql
CREATE DATABASE IF NOT EXISTS rjm_boardinghouse;
CREATE USER 'boardinghouse_app'@'127.0.0.1' IDENTIFIED BY 'change_me';
GRANT ALL PRIVILEGES ON rjm_boardinghouse.* TO 'boardinghouse_app'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Run this via `mysql -uroot` (root works fine over the local socket, just not over TCP).

## 3. Apply migrations, in order

```bash
for f in database/migrations/*.sql; do mysql -uroot rjm_boardinghouse < "$f"; done
```

## 4. Configure environment variables

**Critical gotcha**: `.env` exists but **nothing in the codebase reads it** —
`src/Database.php` only reads real OS environment variables via `getenv()`.
Editing `.env` alone does nothing. Export the same values in your shell before
running anything PHP-related:

```bash
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=rjm_boardinghouse
export DB_USER=boardinghouse_app
export DB_PASS=change_me
export SCORING_SERVICE_URL=http://127.0.0.1:5000
```

`.env` is kept only as a reference for what to export — copy its values, don't
expect the app to load the file.

## 5. Seed test data

```bash
php database/seed.php
```

Creates three test accounts (see `database/seed.php` for exact values):
- `admin@rjm.test` / `AdminPass123!`
- `staff@rjm.test` / `StaffPass123!`
- `boarder@rjm.test` / `BoarderPass123!`

## 6. Start the scoring microservice

**Must be run from inside `scoring_service/`** — it imports sibling modules
(`schemas`, `scoring`, `verification`) by bare name, so launching uvicorn from
the repo root with `--app-dir` will fail to resolve them.

```bash
cd scoring_service
pip install -r requirements.txt   # fastapi, uvicorn, pydantic
python3 main.py                   # runs on 127.0.0.1:5000
cd ..
```

If this service is down, the app is designed to degrade gracefully (maintenance
requests still submit, fall back to a `medium` priority tier, and get flagged
`scoring_pending=1` for re-scoring) rather than fail — verified by killing the
service mid-session and re-testing.

## 7. Start the PHP app

```bash
php -S 127.0.0.1:8000 -t public
```

Visit `http://127.0.0.1:8000/login`.

## One-shot copy/paste (fresh setup)

```bash
service mariadb start
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS rjm_boardinghouse; \
  CREATE USER IF NOT EXISTS 'boardinghouse_app'@'127.0.0.1' IDENTIFIED BY 'change_me'; \
  GRANT ALL PRIVILEGES ON rjm_boardinghouse.* TO 'boardinghouse_app'@'127.0.0.1'; FLUSH PRIVILEGES;"
for f in database/migrations/*.sql; do mysql -uroot rjm_boardinghouse < "$f"; done
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=rjm_boardinghouse DB_USER=boardinghouse_app DB_PASS=change_me SCORING_SERVICE_URL=http://127.0.0.1:5000
php database/seed.php
(cd scoring_service && pip install -r requirements.txt && nohup python3 main.py > /tmp/scoring.log 2>&1 &)
php -S 127.0.0.1:8000 -t public
```

## Known Issues (verified, not theoretical)

1. **MariaDB as a background process may not survive between separate agent
   tool calls/sessions in sandboxed environments.** If you get "Can't connect
   through socket" after it worked a moment ago, just re-run `service mariadb
   start` — the database files persist, only the daemon process died.
2. **`root` over TCP will always fail** — see step 2. This is a MariaDB default,
   not a bug in this project, but the shipped `.env` originally had this wrong;
   if you're working from a copy older than this file, fix `.env`'s `DB_USER`
   before assuming something else is broken.
3. **Rebuilding CSS** (only needed if you edit views or
   `public/assets/css/source/input.css`):
   ```bash
   npm install --no-save tailwindcss@4 @tailwindcss/cli@4   # from repo root
   npx @tailwindcss/cli -i public/assets/css/source/input.css -o public/assets/css/app.css --minify
   rm -rf node_modules   # don't commit or ship this
   ```
4. **No browser is available for GSAP/visual verification in most sandboxed
   agent environments.** Everything in the CSS/JS layer can be verified by
   checking compiled output and rendered HTML, but actual animation playback
   has never been confirmed in a real browser across this project's history —
   worth doing manually at least once.

## Quick smoke test after setup

```bash
curl -s -o /dev/null -w "PHP: %{http_code}\n" http://127.0.0.1:8000/login
curl -s -o /dev/null -w "Scoring: %{http_code}\n" http://127.0.0.1:5000/docs
```
Both should return `200`.
