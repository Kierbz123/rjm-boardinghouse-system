---
paths:
  - "public/**/*.php"
  - "src/**/*.php"
  - "database/**/*.sql"
---

# PHP Conventions

- PDO with prepared statements only. Never build SQL with string concatenation or interpolation, including for "trusted" internal values.
- One shared connection helper (`src/Database.php`), reused everywhere — not a `new PDO(...)` per file.
- Controllers stay thin: validate input → call a Model or Service → return a view or JSON. Business logic (penalty rules, ledger assembly, notification dispatch) lives in `src/Services/`, not inline in controllers.
- RBAC is enforced once in `src/Middleware/`, keyed off the session role — not re-implemented per page.
- Every state-changing form includes a CSRF token, checked in middleware before the controller runs.
- Passwords: `password_hash()` / `password_verify()` only. Regenerate the session ID on login (`session_regenerate_id(true)`).
- File uploads (repair photos/videos, payment receipts): go through `App\Support\Uploads`: validate mime type, extension, and size (≤ 20 MB) against an allow-list before saving; store under `storage/uploads/<subdir>/` (outside `public/`) with a generated filename, never the user-supplied one; serve only through `GET /uploads/{subdir}/{file}` (`Uploads::serve()`), which checks who is asking.
- Never write a password, API key or token literal into a tracked file. Database settings come from the environment / the git-ignored `.env` through `Database::getConnection()`.
- Snake_case table/column names, matching `ARCHITECTURE.md`'s schema. Foreign keys named `<singular_table>_id`.
