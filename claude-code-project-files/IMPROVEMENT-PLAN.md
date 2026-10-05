# Improvement plan — features and pages (front-end, back-end, database)

*Updated 5 October 2026, against the `audit-fixes` branch.*

These suggestions add to the fixes already made in batches B0–B7 (see `FINAL-REPORT.md`) and to the audit in `../AUDIT-REPORT.md`. That audit was run on the older `main` branch, so many of its findings are already fixed here. Everything stays within the project's rules: localhost only, no internet calls, no cloud LLM.

**Legend:** **Done** = already on `audit-fixes` · **Done now** = added in this change (billing rules, receipts, uploads, security text) · **To do** = still a suggestion · finding IDs such as BE-01 refer to `AUDIT-REPORT.md`.

---

## The billing rules the system now follows (Done now)

- **Rent is due on the 5th of each month.** A new resident's **first month is charged only for the days they stay**, and **nothing is due during their first 30 days**. Example, moving in on 20 Oct at ₱3,500: October is ₱1,354.84 (12 of 31 days) and is due 19 Nov; November is also due 19 Nov; from December rent is due on the 5th.
- **Pay by GCash, Maya or bank transfer, then upload the receipt** in the resident portal. The method, an optional reference number and the receipt image are required on the form, apart from the reference number.
- **An administrator checks every receipt.** After submitting, the resident sees a **pop-up: "Receipt submitted — Status: Pending review"**. The payment stays *Pending review* until an admin decides, and the resident is **notified either way**:
  - Approved: what it paid, month by month, any credit, and the new balance.
  - Rejected: the admin's reason, which is now required.
- **Approved payments clear the oldest unpaid month first; anything extra becomes credit** for the next bill.
- **Late fees** start the day after each month's own due date (so never in a resident's first 30 days). Unpaid earlier months keep their own fee, and re-running the check never stacks fees.
- **Late fees are fair to the resident, and the admin has the final say** (owner decision): late days stop on the day a receipt is **submitted**, not when it is approved, so review time is never charged. A receipt sent on or before the due date means no fee; a rejected receipt makes the month late again. When approving, the admin sees the fee ("August 2026: 16 day(s) late (Sep 20–Oct 5) ₱80.00") and can **waive** it with a required reason, which is recorded and shown to the resident.
- **Uploads:** up to **20 MB per file**, on receipts and repair photos/videos alike. That fits any phone photo, or about 10–20 seconds of 1080p phone video. Gigabyte uploads are not practical for this localhost PHP app. Too-large files now get a clear message instead of being silently dropped. `start-system.bat` passes PHP the matching limits.

---

## Two database changes that improve several features at once

1. **Rent charges with due dates.** **Done**, plus due dates **Done now**. `rent_charges` holds one row per boarder per month (`period`, `monthly_rate`, `amount`, `due_date`). `payment_allocations` records which months each approved payment cleared. The rent amount comes from the system, never from the boarder's form (BE-01). Late fees are one per month per rule (BE-03). Unpaid months stay visible as arrears. **Done now:** one receipt under review per resident, checked under a row lock (6 simultaneous submissions → 1 accepted). **Done now:** late days stop at the receipt's submission date; the admin can waive with a reason.
2. **A `bed_assignments` history table.** **To do.** Who was in which bed, from when to when (`boarder_id, bed_id, start_date, end_date`; the open row is the current bed; one open row per boarder and per bed). The occupancy trend can then be calculated from move-in/move-out history instead of daily snapshots (DB-12), and each resident gets a move history. Bed moves are already all-or-nothing (B4).

## Per feature and page

### Login & Profile
- **Front-end:** **To do, high priority:** remove the demo quick-login buttons. The admin, staff and boarder passwords are still in the login page's HTML (FE-01). **To do:** a password-strength hint; a "last login" line on the profile.
- **Back-end:** **Done:** idle timeout, logout of other sessions after a password change, login throttling per email and IP (B1).
- **Database:** **To do:** `users.last_login_at`. **Done:** `users.status` is used to archive residents.

### Admin Command Center (`/admin/dashboard`)
- **Front-end:** **To do:** clickable metric cards opening the filtered list; a "Needs attention today" panel (active SOS, critical repairs, receipts waiting for review, overdue rent); "Collected vs expected this month", which can now use `rent_charges`.
- **Back-end:** **To do:** one dashboard service returning all the figures.
- **Database:** **To do:** indexes for these counts (DB-08).

### Boarders (`/admin/boarders`) and Status Life
- **Front-end:** **Done:** a boarder profile page (`/admin/boarders/{id}`). **To do:** search, status filter and pagination on the list; a confirmation dialog asking for a reason when the status changes.
- **Back-end:** **Done:** residents with payment history are archived, never erased (B4). **To do:** enforce allowed status changes (pending → active → on-notice → moved-out, admin override only with a reason) (BE-13); automatic changes where the spec says so (activate on the move-in date; on-notice when a serious penalty stays unpaid).
- **Database:** **Done:** `move_in_date`/`move_out_date` drive rent. **To do:** `bed_assignments` (above).

### Rooms & Beds (`/admin/rooms`)
- **Front-end:** **To do:** each room as a grid of bed cards (vacant/occupied), as the spec describes. Clicking a vacant bed opens "Assign resident" with names, not typed IDs. Show "2 of 3 beds".
- **Back-end:** **Done:** atomic bed moves and room capacity enforced (B4).
- **Database:** **To do:** `bed_assignments` plus UNIQUE/CHECK constraints so the database itself rejects double-booking (DB-02).

### Payments
- **Resident (`/portal/payments/new`):** **Done now:**
  - unpaid months listed oldest first, each with its due date and an "Overdue since…" badge
  - read-only amount due
  - GCash / Maya / Bank transfer choice and an optional reference number
  - receipt preview, size check and the real limit shown before upload
  - submit button disabled after one click
  - the *Pending review* pop-up
  - statuses shown as Pending review / Approved / Rejected, with the reason
  - credit shown
- **Admin (`/admin/payments`):** **Done now:** method and reference beside the amount; ⚠ when the amount differs from what was owed; approving asks for the amount on the receipt, and billing credits that rather than what the resident typed; a warning when a reference number was already used on another payment; rejecting requires a reason (up to 160 characters), which the resident sees. **To do:** a larger receipt preview beside the amounts; filter by month.
- **Back-end:** **Done:** never auto-approved. **Done now:** always "pending" (no separate "flagged" status); notifications on approve and reject; clear upload errors (BE-16).
- **Database:** **Done now:** `payment_method`, `reference_number`, `reviewed_at`, `review_note`; older "flagged" receipts moved to "pending".

### Penalty Rules
- **Front-end:** **Done now:** the approve dialog previews the late fee for the months a receipt pays and has a "Waive this late fee" box that requires a reason. **To do:** a preview of who the penalty check would charge, before running it.
- **Back-end:** **Done now:** late fees follow each month's own due date, cover unpaid earlier months, and never stack. **To do:** move the due day (5) and the 30-day first-payment period into a settings screen; today they are constants in `BillingService`.
- **Database:** **Done now:** waivers record who and why (`penalties.waived_by`, `waive_reason`). **To do:** a `settings` table.

### Expenses
- **Front-end:** **To do:** an expense date field, receipt upload, and monthly totals per category.
- **Back-end:** **To do:** let staff log expenses, as the spec says; the route is admin-only today.
- **Database:** **To do:** `expense_date`, plus an optional `maintenance_request_id` to report what each repair cost.

### Ledger Export
- **Back-end:** **Done:** separate totals for payments received, expenses, net, and penalties charged as receivable (B3).
- **Front-end:** **To do:** a date-range form with an on-screen preview before download; a print-friendly page the browser can save as PDF.

### Occupancy Trend (`/admin/occupancy`)
- **Front-end:** **To do:** a real line chart using a chart library saved into the project (not a CDN); breakdown by floor or room; a "vacant beds now" list.
- **Back-end/Database:** **To do:** calculate from `bed_assignments`, so days when no admin opened the page aren't missing.

### Maintenance (resident form, staff queue, history)
- **Resident:** **Done now:** the form states the real upload limit (it wrongly said 10 MB). **To do:** show the assigned priority right after submitting; a status timeline per request.
- **Staff:** **To do:**
  - show *why* a request got its priority. The matched keywords are already computed in `ScoringClient` but not shown.
  - a "Rescore" button
  - assign to a staff member, with a "My jobs" filter
  - photo/video thumbnails with click-to-enlarge
- **Back-end:** **Done:** whole-word keyword matching (no more "car" in "carpet"); the room comes from the resident's profile (BE-11, BE-12). **Done now:** oversized videos are refused with a message instead of being dropped. **To do:** simple negation ("no smoke"); time-based priority increase.
- **Database:** **To do:** store `matched_keywords`; use `assigned_staff_id`; a `maintenance_status_log` table.

### Security, SOS & Incidents
- **Landing page:** **Done now:** the curfew line ("10:00 PM Daily, Smart QR Gate Pass Access") is removed everywhere, including the assistant's answers. **There is no curfew.** Security now says **"Live CCTV covers the entire boardinghouse."**
- **CCTV inside the system:** **Planned:** a live camera page, a software motion sensor, people counted per hour (in/out) and face snapshots, using a **phone as the camera** for the demo. See **`CCTV-PEOPLE-COUNTER-PLAN.md`**: phases C0–C4, privacy and Data Privacy Act safeguards, and five owner decisions needed first.
- **Resident:** **Done:** pressing SOS again reuses the open alert (B2). **To do:** press-and-hold to send; a live "Acknowledged by …" status.
- **Staff:** **Done:** staff and admins are notified of a new SOS, with the room (B2). **To do:** an audible alert using the browser's own sound; a "Convert to incident" button; a "people involved" field on incidents.
- **Database:** **To do:** `acknowledged_at` and `resolved_by` on SOS alerts; `sos_alert_id` and `people_involved` on incidents.

### Notifications
- **Done:** each notification links to its record (`action_url`). **Done now:** payment approved/rejected (with reason) and rent-due reminders that name the month, amount and real due date.
- **To do:** filter by type; "Archive" instead of "Delete"; stop duplicate rent-due reminders when the button is pressed twice.

## Across all pages
- **Front-end:**
  - **Done:** private uploads; Asia/Manila time everywhere.
  - **To do:**
    - forms keep what you typed after an error
    - every input linked to its label
    - tables scroll sideways on phones
    - clear empty-list messages
    - `data-testid` hooks on more pages (started on Pay Rent)
- **Back-end:**
  - **Done:** security headers; migration runner; isolated test runner (`php tests/run.php`, all passing).
  - **To do:** one shared validation helper; pagination on every list; move inline scripts into files so the CSP can drop `'unsafe-inline'`.
- **Database:**
  - **Done:** migration history in `schema_migrations`.
  - **To do:** triggers that block edits and deletes on history tables; a least-privilege database user; a "Backup now" admin button that runs `mysqldump` to a local folder.

## Suggested order
1. **Before real use:** remove the demo password buttons (FE-01) and change the demo passwords.
2. Add `bed_assignments`, then the bed-card grid and the occupancy chart built on it.
3. Make the dashboard actionable: clickable cards, "Needs attention", collected vs expected.
4. Maintenance and SOS improvements: score explanation, rescore, assign, convert to incident.
5. CCTV phase C1 (counts only, no faces) once the owner has made decisions D1–D5.
6. Settings screen (due day, first-payment period, late fee) and a preview of the penalty check.
7. Finish with shared validation, accessibility and moving inline scripts into files.
