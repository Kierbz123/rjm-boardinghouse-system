# Feature Spec — RJM Boardinghouse System
Read the section(s) relevant to the phase you're on (see `PHASES.md`). Not preloaded every session — only `CLAUDE.md` is.

## 1. Smart AI Repair Priority System
- Boarder submits: description, category (electrical/plumbing/structural/appliance/other), photo and/or video, room/bed reference.
- Local scoring logic (Python service): weighted rule engine —
  - Keyword severity dictionary (e.g. "fire", "flood", "gas", "exposed wire" → high severity; "squeaky", "cosmetic" → low)
  - Category base-severity weight
  - Media presence bonus (a request with photo/video ranks above an identical one without)
  - Time-decay: an unresolved request's priority score ticks up the longer it waits
  - Output: numeric priority score → mapped to **Critical / High / Medium / Low**
- Maintenance Staff queue is sorted by score, not submission time, with the score/tier visibly shown.
- **Optional libraries (offline-compatible, pip-installable — vet stars/last-commit/license before depending on any of them):**
  - Rule/weighted-scoring engine instead of hand-rolling one: **GoRules Zen Engine** (`pip install zen-engine`) or **simple-rule-engine** (`pip install simpleruleengine`).
  - Queue ordering: Python's stdlib `heapq` / `queue.PriorityQueue` — genuinely enough, no package needed.
  - **Skip anything built around an external LLM API call** (e.g. Gemini-based triage tools) — breaks the no-outbound-calls rule in `CLAUDE.md` regardless of how good the triage logic is.
  - Real photo/video damage detection (YOLO/CNN-based) is a legitimate *stretch goal*, not the default: runs fully offline once weights are downloaded, but pulls in PyTorch/YOLO and is a large scope increase. Ship the rule-based version first.

## 2. Emergency SOS & Incident Reporting
- Boarder taps a single SOS button → creates an alert with boarder identity, **room/bed location**, and timestamp; visible (poll or refresh) to Admin/Staff dashboards with highest visual urgency.
- Staff can separately log **incidents** (not just SOS-triggered) with type, description, people involved, and resolution notes — a historical safety record independent of live alerts.
- SOS alerts need a status lifecycle: `active → acknowledged → resolved`, with who and when.

## 3. Cross-Module Command Center (Admin Dashboard)
- One screen aggregating: pending payments, open maintenance requests by priority, active SOS/incidents, occupancy snapshot, recent expenses. Read/aggregate view over the other modules — build it after they exist (Phase 6), not before.

## 4. Expense Logging
- Staff log operating expenses: category (repairs/utilities/supplies/other), amount, date, note, receipt upload (optional). Feeds the Ledger Export and the Command Center.

## 5. Financial Ledger Export
- Compiles rent payments + expenses + penalties into an exportable record (CSV and/or PDF) for a date range. A report *generated from* existing tables, not a separately-entered dataset.

## 6. Occupancy Trend
- Track occupancy over time (snapshot per day/week, or derived from move-in/move-out events) and chart it. Useful output: occupancy % over time, vacancy patterns by room type/season.

## 7. Proof of Payment Verifier
- Boarder uploads a receipt/screenshot plus the amount they claim to have paid.
- Local verification logic (Python service, no external OCR API unless the user opts in): rule-based check — does the claimed amount match the expected rent for their room/bed and billing period? Flag mismatches for admin review rather than silently auto-approving.
- Real text/amount extraction from the image requires a local OCR library (e.g. Tesseract) — optional upgrade, confirm scope in Phase 0, not assumed.
- Verification status: `pending / auto-matched / flagged / admin-approved / rejected`.

## 8. Detailed Bed Mapping
- Rooms contain multiple beds/spaces; track occupancy at the **bed** level so two boarders can't be double-booked into the same bed.
- A simple grid of labeled bed cards is enough — doesn't need to be a literal floor-plan diagram unless asked.

## 9. Penalty and Fee Automation
- Configurable rules (e.g. "X/day late after due date," "flat damage fee for category Y") stored in `penalty_rules` so admins can adjust amounts without a code change.
- A triggered check (on admin login and/or a manual "run penalty check" button — no cron guarantee on localhost) applies rules and logs the resulting penalty.

## 10. Status Life System
- Each boarder has a status: `pending → active → (optional: on-notice) → moved-out`, updated by triggering events (move-in date reached, move-out logged, unresolved severe penalty) plus manual admin override.
- Status changes are logged (who/when/why) — part of the boarder's history, not just a current flag.

## 11. All-Around Notification System
- Cross-cutting: rent due reminders, maintenance status updates, SOS/incident alerts, penalty notices, payment verification results.
- Build **last** (Phase 7) since it depends on events from every other module existing first. In-app notification bell + table is enough; SMS/email is out of scope (and would break localhost-only anyway).
