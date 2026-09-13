---
paths:
  - "scoring_service/**/*.py"
---

# Python Scoring Service Conventions

- Stateless. No DB driver, no ORM, no session/auth logic — pure input JSON in, computed output JSON out. PHP is the only thing that writes to MariaDB (see `ARCHITECTURE.md` §2).
- Bind to `127.0.0.1` only, never `0.0.0.0` — this service must be unreachable from outside the machine.
- Validate incoming JSON against an explicit schema (Pydantic if FastAPI) and return a clear 400 with a message on malformed input — don't let a bad request 500 silently.
- The two endpoints and their exact request/response shapes are defined in `ARCHITECTURE.md` §3.2 — don't invent extra fields or change field names without updating that file too.
- No calls to any external API or model. If a feature seems to need one, that's a scope question to raise, not a decision to make locally — see `CLAUDE.md`'s non-negotiable constraints.
- One module per concern: `scoring.py` for feature 1's weighted-rule logic, `verification.py` for feature 7's amount-matching — don't merge them into `main.py`.
