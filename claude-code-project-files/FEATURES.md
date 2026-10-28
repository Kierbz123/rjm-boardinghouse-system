# Feature Spec — RJM Boardinghouse System
What each feature should do. `CLAUDE.md` is the only file loaded every session; read the section you need.

## Implementation status (as of the 14 Oct 2026 audit)

Source: `../FUNCTIONALITY-AUDIT-2026-10-14.md`. Finding ids (F-xx) and work packages (WP-n) are defined there and in `../IMPLEMENTATION-PLAN-2026-10-14.md`. "Built" means it works end to end in the running system and is covered by `php tests/run.php`; the gaps listed are what the spec below asks for and the system does not yet do.

| # | Feature | Status | Gaps against this spec |
|---|---|---|---|
| 1 | Repair priority | Built (rule-based PHP) | Time-decay built 28 Oct (+2 per full day waiting, max +30, worked out when read); re-opening a resolved request needs a reason, kept in `audit_log` (F-18, 28 Oct). No staff assignment or notes; resident cannot cancel; labelled "AI" although it is a keyword table (F-18, WP-11) |
| 2 | SOS and incidents | Built | No resolver / false-alarm record, no SOS history page, no "people involved" on incidents (F-23, WP-15). No in-system CCTV page (see §2) |
| 3 | Command Center | Built | Status pills are static text (F-26). Occupancy card refreshes on every visit and repair counts use the decayed tier (fixed 28 Oct) |
| 4 | Expense logging | Partly | Admin-only (spec says staff); no date field, no receipt upload, no edit or void (F-19, WP-12) |
| 5 | Ledger export | Built (CSV only) | No PDF. Fixed 28 Oct: payments dated by approval (agrees with the dashboard), "receivable" counts unpaid penalties only, last CSV line no longer cut off (F-11, F-32) |
| 6 | Occupancy trend | Partly | A snapshot is taken only when an admin opens a page, so days are missing (F-12) |
| 7 | Payment proof and review | Built | No cash / walk-in payment entry (F-13, WP-6); payment account numbers on the Pay Rent page are fixed text (F-14, WP-10) |
| 8 | Bed mapping | Built | A list, not a card grid; no `bed_assignments` history |
| 9 | Penalties | Built | Late-fee check and reminders are manual (no scheduler); "Mark paid" (a waiver) now requires a reason and records who and why (F-06, fixed 28 Oct; a separate "paid in cash" path is the Record Payment form). Reminders: the same one goes out at most once a day (F-15, fixed 28 Oct) |
| 10 | Status life | Partly | No automatic transitions (pending → active, scheduled move-out); a future move-out date moves the resident out at once (F-05, F-17, WP-3, WP-9) |
| 11 | Notifications | Built | In-app only; e-mail is planned behind an off-by-default queue (WP-9); no paging or pruning (F-28) |

## 1. Smart Repair Priority System
- Boarder submits: description, category (electrical/plumbing/structural/appliance/other), photo and/or video, room/bed reference (the room comes from the resident's profile).
- Local scoring logic (`src/Services/ScoringClient.php`, plain PHP, no external call): weighted rule engine —
  - Keyword severity dictionary matched as whole words (e.g. "fire", "flood", "gas leak", "exposed wire" → high severity; "squeaky", "cosmetic" → low)
  - Category base-severity weight
  - Media presence bonus (a request with photo/video ranks above an identical one without)
  - Time-decay: an unresolved request's priority score ticks up the longer it waits: +2 per full day, at most +30 (`ScoringClient::withDecay`, applied when the queue is read by `MaintenanceRequest::openDecayed`; the stored score stays the submitted one)
  - Output: numeric priority score → mapped to **Critical / High / Medium / Low**
- Maintenance Staff queue is sorted by tier then oldest first, with the score/tier visibly shown.
- Never call an external LLM API for scoring (breaks the no-outbound-calls rule in `CLAUDE.md`). The optional local Ollama model only powers separate helper buttons (improve description, suggest category, analyse ticket, summarise queue). Real photo/video damage detection is a stretch goal, not planned.

## 2. Emergency SOS & Incident Reporting
- Boarder taps a single SOS button → creates an alert with boarder identity, **room/bed location**, and timestamp; visible (poll or refresh) to Admin/Staff dashboards with highest visual urgency.
- Staff can separately log **incidents** (not just SOS-triggered) with type, description, people involved, and resolution notes — a historical safety record independent of live alerts.
- SOS alerts need a status lifecycle: `active → acknowledged → resolved`, with who and when.
- House security as advertised on the landing page: **live CCTV covers the entire boardinghouse; there is no curfew.** The camera is a physical installation; the system has no camera page. An in-system page with motion sensing, people-per-hour counts and face snapshots (a phone as the camera for the demo) is only a plan (`CCTV-PEOPLE-COUNTER-PLAN.md`), pending owner decisions. The 14 Oct plan removes the "Live CCTV" claim from the landing page until such a feature exists (WP-10 / WP-19); at the time of writing the claim is still there.

## 3. Cross-Module Command Center (Admin Dashboard)
- One screen aggregating: pending payments, open maintenance requests by priority, active SOS/incidents, occupancy snapshot, recent expenses. Read/aggregate view over the other modules — build it after they exist (Phase 6), not before.

## 4. Expense Logging
- Staff log operating expenses: category (repairs/utilities/supplies/other), amount, date, note, receipt upload (optional). Feeds the Ledger Export and the Command Center. *Today only an admin can record one, with a free-text category and no date or receipt field; the 14 Oct plan adds staff entry that waits for admin approval, a date, receipts and voiding (WP-12).*

## 5. Financial Ledger Export
- Compiles rent payments + expenses + penalties into an exportable record (CSV and/or PDF) for a date range. A report *generated from* existing tables, not a separately-entered dataset.

## 6. Occupancy Trend
- Track occupancy over time (snapshot per day/week, or derived from move-in/move-out events) and chart it. Useful output: occupancy % over time, vacancy patterns by room type/season.

## 7. Proof of Payment Verifier
*Owner rules, Oct 2026 — this replaces the original automatic-verifier design.*
- **Billing (owner rule, 5 Oct 2026):** rent is charged in **30-day periods counted from the move-in date**, each **due 30 days after it starts** (move in 20 Oct: due 19 Nov, 19 Dec, 18 Jan…). Every period is charged in full; only a last period cut short by a move-out is prorated by days. See `BillingService::dueDate()`. This replaces the earlier "due on the 5th" rule.
- **Electricity:** each room has its own submeter. The admin enters the room's bill on Rooms & Beds and it is **divided equally among the residents staying in the room**; each share is due on that resident's next rent due date (`Models\ElectricityBill`, migration `0028`). Water is free.
- **Paying:** the resident pays by **GCash, Maya or bank transfer**, then uploads the receipt (JPG/PNG/WEBP, up to 20 MB) with the method, the amount paid and an optional reference number. The amount owed always comes from the system, never from the form.
- **Review:** every receipt is saved as **pending** and a pop-up tells the resident it is *Pending review*. **An administrator checks every receipt**; nothing is ever approved automatically. An amount that differs from what is owed is marked ⚠ for the admin, not given a different status.
- **Confirming the amount:** when approving, the admin types the amount the receipt actually shows (pre-filled with what the resident entered, never more). Billing credits that confirmed amount. A reference number already used on another non-rejected payment is flagged to the admin.
- **Outcome:** on approval the payment clears the **oldest unpaid month first**, then penalties; **anything extra becomes credit** for later months. The resident is notified either way: approval lists what was paid and any credit; rejection carries the admin's (required) reason.
- Statuses: `pending → admin-approved | rejected` (an approval can be reversed by rejecting it). `auto-matched` and `flagged` only exist on rows from before these rules.
- Real text/amount extraction from the image (local OCR such as Tesseract) remains an optional upgrade, not assumed.

## 8. Detailed Bed Mapping
- Rooms contain multiple beds/spaces; track occupancy at the **bed** level so two boarders can't be double-booked into the same bed.
- A simple grid of labeled bed cards is enough — doesn't need to be a literal floor-plan diagram unless asked.

## 9. Penalty and Fee Automation
- Configurable rules (e.g. "X/day late after due date," "flat damage fee for category Y") stored in `penalty_rules` so admins can adjust amounts without a code change.
- A late fee starts the day after a rent period's own due date (§7), so never during a new resident's first 30 days. Each unpaid period has at most one fee per rule, growing with the days late until that rent is paid; re-running the check never stacks fees. Rent that fell due before these rules went live (migration `0028`) is never given a new backdated fee. Electricity shares carry no late fee.
- **Owner decision (Oct 2026): fair default + admin override.** Late days stop counting on the day a receipt is **submitted**, for the months that receipt pays (oldest first), so admin review time is never charged; a receipt sent on or before the due date means no fee. If the receipt is rejected, those months count as late again from their due date. When approving, the admin sees the late fee and may **waive** it with a required reason; the waiver records who and why (`penalties.waived_by`, `waive_reason`, migration `0027`) and the resident's notification says so.
- A triggered check (on admin login and/or a manual "run penalty check" button — no cron guarantee on localhost) applies rules and logs the resulting penalty.

## 10. Status Life System
- Each boarder has a status: `pending → active → (optional: on-notice) → moved-out`, updated by triggering events (move-in date reached, move-out logged, unresolved severe penalty) plus manual admin override. *Today the admin changes status by hand; the automatic transitions are not built (F-17, WP-3, WP-9).*
- Status changes are logged (who/when/why) — part of the boarder's history, not just a current flag.

## 11. All-Around Notification System
- Cross-cutting: rent due reminders, maintenance status updates, SOS/incident alerts, penalty notices, payment verification results.
- Built last (original Phase 7) since it depends on events from every other module. The in-app bell + table is what exists today. SMS is out of scope. **E-mail:** the owner approved it on 14 Oct 2026 as a queue that sends nothing until an admin enables SMTP in Settings (WP-9, not built); until that lands `CLAUDE.md`'s no-e-mail rule still stands.
