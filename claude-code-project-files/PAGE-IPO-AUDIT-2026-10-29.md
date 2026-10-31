# Page-by-Page IPO and Authorization Audit — 29 Oct 2026

A read-only examination of every page, route and service against the paper's **IPO figure** ("Flow of the Study", Table 1) and its **11-feature list**. It records what the code does today. It changes nothing and fixes nothing: each gap is tagged either **paper overstates** (the paper says more than the code does) or **code incomplete** (the code leaves out something the app's own docs promise), so the owner can decide which side to change.

Method: the route table, middleware, controllers, models, services and views were read; the app was not run. Cited lines were spot-checked against the source on 29 Oct 2026 (96 routes counted; scoring tiers, decay constants, polling interval, sorts and page sizes confirmed). Paths are relative to `claude-code-project-files/`. This is a dated record; current behaviour is owned by `FEATURES.md`, `ARCHITECTURE.md` and `README.md` (see `PROJECT_STRUCTURE.md`).

> **Update, 29 Oct 2026 (WP-21).** Four of the findings below have since been fixed, and are marked **[fixed WP-21]** where they appear: history search and whole-set counts (§7 #5), the idle timeout defeated by polling (§8.1), the missing admin and resident sidebar entries (§8.2, §8.3), and the unaudited payment decisions (part of §8.7 \u2014 approve, reject and reverse only). Everything else still stands as written.

## 1. How to read this

- **Roles:** *public* (no login), *boarder* (`portal/`), *staff*, *admin*. Admin is a superset of staff on staff routes, but staff are refused every `/admin/*` route except Inquiry Center.
- **Source of truth for authorization:** the route table in `public/index.php` (each closure calls `AuthMiddleware::require()` then `RoleMiddleware::require([...])`), `src/Middleware/`, `src/Support/NavRegistry.php`.
- **Feature numbering differs.** The paper's 11 features are not numbered like `FEATURES.md`:

| Paper feature | `FEATURES.md` # |
|---|---|
| 1 Rent and Payment Processing | 7 |
| 2 Maintenance Request Management | 1 |
| 3 SOS Alert and Incident Reporting | 2 |
| 4 Admin Command-Center Dashboard | 3 |
| 5 Expense Logging and Ledger Export | 4, 5 |
| 6 Occupancy Tracking and Bed-Level Room Mapping | 6, 8 |
| 7 Penalty and Fee Automation | 9 |
| 8 Boarder Status Lifecycle | 10 |
| 9 Real-Time Notifications | 11 |
| 10 Maintenance and Incident History | not in `FEATURES.md` |
| 11 AI Assistant | only in `AI-ASSISTANT-PLAN.md` |

## 2. The IPO figure against the code

### INPUT

| Figure item | Where | What the code actually accepts |
|---|---|---|
| Boarder Information | `Controllers/BoarderController.php` (create, `updateInfo`), `Models/BoarderProfile.php`, `Services/DateRules.php` | Name and e-mail required, ≤150, e-mail unique (archived accounts included). Phone 7-15 digits. Status ∈ pending / active / on_notice / moved_out. Move-in between the `property_opening_date` setting and today + 12 months; move-out on or after move-in and within 5 years. The same date limits are enforced again by DB triggers (migration 0034). |
| Room and Bed Assignments | `Models/Bed.php` `assign`, `BoarderController::assignBed`, `RoomController` | The bed row is locked and an occupied bed refused. Unique indexes on `bed_id` and `current_boarder_id` (migration 0029). Moved-out residents are rejected server-side although the picker still lists them. |
| Payment Details | `Controllers/PaymentController.php` `create`, `Models/Payment.php` | Method GCash / Maya / bank transfer. Reference ≤50 of `[A-Za-z0-9 -]`. Claimed amount >0 and ≤1,000,000. Proof image required (jpeg/png/webp, ≤20 MB). Only one open (pending/flagged) submission per boarder, re-checked under a row lock. `expected_amount` is taken from the server balance, never from the form. |
| Maintenance Requests | `Controllers/MaintenanceController.php` `create` | Category ∈ electrical / plumbing / structural / appliance / other, description required ≤5000, optional photo or mp4. The room comes from the boarder's profile, not the form (a posted `room_id` is ignored). |
| SOS Alerts | `Controllers/SosController.php` `trigger`, `Models/SosAlert.php` | CSRF only. A second press within 2 minutes reuses the open alert. |

### PROCESS

| Figure item | Where | What it really does |
|---|---|---|
| Authentication and Validation | `AuthController`, `AuthMiddleware`, `Support/PasswordPolicy.php`, `Services/PasswordResetService.php`, `Models/LoginAttempt.php` | `password_verify`, session ID regenerated on login. Lockout after 5 failures per e-mail in 15 min or 20 per IP, checked before the password. Dummy hash keeps timing constant. 30-minute idle timeout. The user row is re-read on **every** request: a deactivated user, changed role, changed password hash or expired temporary password ends the session. Password: ≥10 characters, ≤72 bytes, ≥5 distinct characters, not common words, not the name or e-mail. Admin resets issue a temporary password (72 h, forced change). |
| Payment Verification | `Services/PaymentReviewService.php`, `Services/PaymentRecorder.php`, `PaymentController::approve/reject` | Every receipt is stored `pending`; **never auto-approved**. Approval locks the row, settles late fees up to the submission date, optionally waives them (reason 3-160), then approves, all in one transaction. Approved amount may not exceed the claimed amount. Reject needs a reason (3-160) and can reverse an approved payment. A reference reused on the same method is warned about in the admin queue and refused by `PaymentRecorder`. |
| Maintenance Prioritization | `Services/ScoringClient.php`, `Models/MaintenanceRequest.php` | Local keyword rules, **not AI**. See feature 2 below. |
| Ledger and Penalty Computation | `Services/BillingService.php`, `PenaltyEngine.php`, `LedgerBuilder.php`, `LedgerWorkbook.php`, `PaymentAllocationService.php` | Owed = rent + electricity + penalties − approved payments. Rent in 30-day cycles from move-in, due 30 days after each cycle starts, prorated on move-out, capped at 120 cycles. Payments are applied oldest cycle first: rent, then electricity, then penalties; surplus becomes credit. |
| Notifications and AI Assistance | `Services/NotificationDispatcher.php`, `Models/Notification.php`, `Services/AssistantService.php`, `OllamaClient.php`, `Controllers/AssistantController.php` | See features 9 and 11. |

### OUTPUT

| Figure item | Where | What is produced |
|---|---|---|
| Payment Results | `NotificationDispatcher::paymentApproved/paymentRejected`, `PaymentController::receipt`, `Views/shared/receipt.php` | Notification listing allocation per cycle, credit kept, balance left and any waived fee; printable receipt `RJM-%06d` (approved payments only, to admin or the paying boarder). |
| Maintenance Queue | `MaintenanceRequest::openDecayed`, `Views/staff/maintenance_queue.php` | Unresolved tickets re-scored with time decay, sorted by tier rank then oldest first, capped at 100. |
| Incident Records | `Models/Incident.php`, `IncidentController`, `Views/staff/incidents.php`, `incident_history.php` | Type, description, reporter, resolution notes (required), `resolved_by`, `resolved_at`. **SOS alerts are not part of this record** (see gaps). |
| Reports and Notifications | `LedgerController::export`, `Models/Notification.php`, `/notifications` | The only report is the ledger export (XLSX by default). Notifications page with read / pin / delete. |
| Admin Dashboard | `DashboardController::admin`, `Views/admin/dashboard.php` | Pending-payment count, active SOS, occupancy %, open maintenance by decayed tier, 5 recent expenses, receivables (total owed, overdue, collected this month, top-5 debtors). Rendered once per load; no refresh. |

## 3. The 11 features against the code

| # | Paper feature | Status | What the code does |
|---|---|---|---|
| 1 | Rent and Payment Processing | Partial wording | Proof upload and admin review work. The "mismatch flag" is only a ⚠ icon computed in the view when `abs(expected − claimed) > 0.01` (`Views/admin/payments.php:241`). "Expected" is the boarder's **total outstanding balance** (`PaymentController.php:98-100`), not the expected rent, so any partial rent payment shows ⚠. No stored flag, no notification, not counted on the dashboard. The `flagged` status is dead: migration 0025 converts every flagged row to pending. |
| 2 | Maintenance Request Management | Implemented | Score = category base (structural 25, electrical 20, plumbing 15, appliance 8, other 5) + matched keyword weights (whole-word; life-safety 32-45, security/medical 15-45, outages ~20, wear ~10, cosmetic 2-4) + 10 if media attached, capped at 100. Tiers: **≥70 Critical, ≥45 High, ≥20 Medium, else Low** (`ScoringClient.php:93-98`). Time decay at read time: +2 per full day waiting, capped +30 (`:77-88`); the stored score never changes. A single life-safety keyword alone does not reach Critical (fire 40 + structural 25 = 65 = High). Live preview via `POST /api/maintenance/score-preview`. |
| 3 | SOS Alert and Incident Reporting | Partial | `sos_alerts`: active → acknowledged → resolved; staff and admin notified at once; staff dashboard polls every 8 s and reloads when the count changes. Incidents are a separate table with required resolution notes. SOS stores `acknowledged_by` but **no resolver and no acknowledged-at time**, and there is no SOS history page. Acknowledging or resolving overwrites the original notification text, losing the room/bed line. The alert stores `room_id` only, not the bed. |
| 4 | Admin Command-Center Dashboard | Implemented | See Output above. Only 5 expenses shown and no monthly expense total. Recomputes every active resident's balance on every load (cost grows with residents, noted in `BillingService.php:155`). |
| 5 | Expense Logging and Ledger Export | Implemented, wording differs | Expenses: admin only, free-text category, amount >0 ≤10,000,000, optional description; **no date field, no receipt (`receipt_path` always null), no edit or void**. Ledger = UNION of approved payments (dated by `reviewed_at`), expenses and penalties. **Default download is `.xlsx`** (Statement sheet + General Ledger sheet); CSV only with `?format=csv` (`LedgerController.php:18-26`). The assistant's reply says "(CSV)" but links to the XLSX. |
| 6 | Occupancy Tracking and Bed-Level Room Mapping | Partial wording | Bed-level assignment with double-booking prevented. Trend is a **30-day table with a progress bar and % badge per day, not a chart** (`Views/admin/occupancy.php`). Snapshots are written only when an admin opens the Dashboard or Occupancy page (`DashboardController.php:35`, `OccupancyController.php:11`), so days nobody opened are missing. No history of which resident held which bed. |
| 7 | Penalty and Fee Automation | Partial | Only rules with `condition_type = 'late_per_day'` are computed (`PenaltyEngine.php:151`): one fee per rule per unpaid cycle, amount = rule amount × days late, days stop on the date the covering receipt was submitted; never backdated before migration 0028. `condition_type` is free text, so a misspelled type never fires. Admin can create a rule, change its amount, toggle active; rules cannot be deleted. Runs **only** when an admin presses "Run penalty check" or when a receipt is approved. Other penalty types are issued by hand (admin only). Waive/mark-paid requires a reason and records `waived_by`. |
| 8 | Boarder Status Lifecycle | Partial | Statuses pending / active / on_notice / moved_out; every change is logged in `boarder_status_log` (old, new, changed_by, reason, time). **No transition rules**: any status can be set from any other by the admin. The only automatic change is a scheduled move-out, applied by `HousekeepingService::applyDueMoveOuts` when an admin or staff page loads (dashboard, resident directory, staff list, rooms), not on a timer. Moving out frees the bed; archiving also logs a move-out. |
| 9 | Real-Time Notifications | Partial wording | Delivery is **polling**: the bell fetches `/api/notifications/unread` every 15 s (`Views/shared/nav.php:726`); the staff dashboard polls SOS every 8 s. No WebSocket or push. Triggers exist for SOS, maintenance, incidents, payment submitted/approved/rejected, penalties, electricity bills, password resets, bed assignment, new inquiry. **Rent-due reminders are not automatic**: they go out only when an admin presses the send button. The late-fee notice fires when the fee is first created; later daily increases are silent. Users can read, unread, pin and delete their own notifications. |
| 10 | Maintenance and Incident History | Partial wording | Both are **paginated (50 per page, newest first) but not searchable or filterable**: the controllers read only `?page` and the views have no inputs. Resolver name and time are shown. The "Resolved" and "Open" KPI cards count only the rows of the current page. Re-opening a repair clears `resolved_by` / `resolved_at` on the row; the earlier resolver survives only in `audit_log`. The re-open form is not reachable from the queue (it lists unresolved tickets only). |
| 11 | AI Assistant | Partial wording | Local Ollama (`llama3.2:3b` at 127.0.0.1:11434). Flow: (1) rule layer with no model, covering greetings, 14 role-gated live lookups and 12 help topics; (2) the model only **picks a numbered option** from the role's own catalogue; (3) free chat with a no-invention prompt. Role privacy: boarders read only their own rows; finance lookups are admin-only; staff get repairs, SOS, incidents, inquiries. Rate limit 10 model calls per minute per session. Boarder helpers: improve description, suggest category (validated against the enum). Staff/admin helpers: analyse a ticket, summarise the queue (max 20 tickets). **Grounding in free chat exists only for boarders**; staff and admin free chat receives just name and role. A boarder's recent repair requests are queried but never put into the prompt. |

## 4. Authorization matrix — all 96 routes

Legend: **A** = `AuthMiddleware` only; **R[...]** = `RoleMiddleware` list. Type: **P** page, **X** action / JSON / download. Every POST checks CSRF inside its handler (the one exception is `POST /inquire`, protected by a honeypot and a per-IP limit).

**Public (no login)**

| Method path | Handler | Type |
|---|---|---|
| GET `/`, GET `/landing` | `LandingController::index` | P |
| GET `/api/blueprint/rooms` | `LandingController::blueprintJson` | X |
| POST `/inquire` | `LandingController::handleInquiry` | X (no CSRF; honeypot, 5 per IP per hour) |
| GET `/login`, POST `/login` | `AuthController::showLogin`, `login` | P, X |
| POST `/logout` | `AuthController::logout` | X (no `AuthMiddleware`) |

**Admin only — R[admin]**

| Method path | Handler | Type |
|---|---|---|
| GET `/admin/dashboard` | `DashboardController::admin` | P |
| GET `/admin/inquiries/{id}`; POST `…/status`, `…/link-boarder` | `InquiryController::show`, `updateStatus`, `linkBoarder` | P, X, X |
| GET `/admin/boarders`; POST `/admin/boarders` | `BoarderController::index`, `create` | P, X |
| GET `/admin/boarders/{id}` | `BoarderController::show` | P |
| POST `/admin/boarders/{id}/info`, `/delete` (archives), `/restore`, `/dates`, `/move-out/cancel`, `/notes`, `/password` | `updateInfo`, `delete`, `restore`, `updateDates`, `cancelMoveOut`, `updateNotes`, `resetPassword` | X |
| POST `/admin/beds/assign` | `BoarderController::assignBed` | X |
| GET `/admin/staff` (views: all, boarders, staff) | `StaffController::index` | P |
| POST `/admin/staff`, `/admin/staff/{id}/status`, `/password`, `/phone` | `StaffController::create`, `setStatus`, `resetPassword`, `setPhone` | X |
| GET `/admin/rooms` | `RoomController::index` | P |
| POST `/admin/rooms`, `/admin/rooms/{id}/update`, `/delete`, `/admin/room-types/{id}/update` | `create`, `update`, `delete`, `updateType` | X |
| POST `/admin/beds`, `/admin/beds/{id}/update`, `/delete` | `createBed`, `updateBed`, `deleteBed` | X |
| POST `/admin/electricity`, `/admin/electricity/{id}/delete` | `createElectricityBill`, `deleteElectricityBill` | X |
| GET `/admin/payments`, GET `/admin/payments/record` | `PaymentController::index`, `recordForm` | P |
| POST `/admin/payments/record`, `/admin/payments/{id}/approve`, `/reject` | `record`, `approve`, `reject` | X |
| GET `/admin/expenses`; POST `/admin/expenses` | `ExpenseController::index`, `create` | P, X |
| GET `/admin/penalty-rules` | `PenaltyController::index` | P |
| POST `/admin/penalty-rules`, `…/{id}/amount`, `…/{id}/toggle` | `createRule`, `updateAmount`, `toggleActive` | X |
| POST `/admin/penalties/run-check`, `/admin/penalties/issue`, `/admin/penalties/{id}/mark-paid` | `runCheck`, `issuePenalty`, `markPaid` | X |
| POST `/staff/penalties/issue` | `PenaltyController::issuePenalty` | X (admin only despite the prefix; duplicate, no form uses it) |
| POST `/admin/notifications/rent-due` | `PenaltyController::sendRentDueReminders` | X |
| GET `/admin/ledger/export` | `LedgerController::export` | X (download) |
| GET `/admin/occupancy` | `OccupancyController::trend` | P (writes today's snapshot) |

**Staff and admin — R[staff, admin]**

| Method path | Handler | Type |
|---|---|---|
| GET `/staff/dashboard` | `DashboardController::staff` | P |
| GET `/staff/maintenance`; POST `/staff/maintenance/{id}/status`; GET `/staff/maintenance/history` | `MaintenanceController::queue`, `updateStatus`, `history` | P, X, P |
| POST `/staff/incidents/{id}/resolve` | `IncidentController::resolve` | X |
| POST `/staff/sos/{id}/ack`, `/staff/sos/{id}/resolve`; GET `/api/sos/active` | `SosController::acknowledge`, `resolve`, `activeJson` | X, X, X (JSON) |
| POST `/api/assistant/analyze-ticket`, `/api/assistant/summarize-queue` | `AssistantController::analyzeTicket`, `summarizeQueue` | X (JSON) |
| GET `/admin/inquiry-center`; POST `/admin/inquiry-center/submit` | `InquiryController::adminCenter`, `handleStaffInquiry` | P, X (staff do not see the recent-inquiry contact list) |

**Staff, admin and boarder — R[staff, admin, boarder]**

| Method path | Handler | Type |
|---|---|---|
| GET `/staff/incidents`; POST `/staff/incidents`; GET `/staff/incidents/history` | `IncidentController::index`, `create`, `history` | P, X, P (a boarder sees only their own reports) |

**Any logged-in user — A only**

| Method path | Handler | Type |
|---|---|---|
| GET `/portal/incidents`, POST `/portal/incidents` | `IncidentController::index`, `create` | P, X (duplicate of `/staff/incidents`, no role guard, not in the nav) |
| GET `/payments/{id}/receipt` | `PaymentController::receipt` | P (handler allows admin, or the boarder for their own approved payment; others get 404) |
| GET `/uploads/{subdir}/{file}` | `Uploads::serve` | X (admin: all; staff: `maintenance` only; boarder: only their own file; every failure is an identical 404) |
| GET `/profile`; POST `/profile/update`, `/profile/password` | `ProfileController::index`, `updateInfo`, `updatePassword` | P, X, X |
| GET `/notifications`; POST `/notifications/read-all`, `/{id}/read`, `/{id}/toggle-read`, `/{id}/toggle-pin`, `/{id}/delete` | `NotificationController::*` | P, X (each checks ownership) |
| GET `/api/notifications/unread`; POST `/api/notifications/{id}/read` | `NotificationController::unreadJson`, `markRead` | X (JSON) |
| GET `/api/assistant/status`; POST `/api/assistant/ask` | `AssistantController::status`, `ask` | X (JSON; answers filtered by role) |

**Boarder only — R[boarder]**

| Method path | Handler | Type |
|---|---|---|
| GET `/portal/dashboard` | `DashboardController::portal` | P |
| GET `/portal/maintenance/new`; POST `/portal/maintenance`; POST `/api/maintenance/score-preview` | `MaintenanceController::newForm`, `create`, `scorePreview` | P, X, X (JSON) |
| GET `/portal/payments/new`; POST `/portal/payments` | `PaymentController::newForm`, `create` | P, X |
| POST `/api/sos` | `SosController::trigger` | X (JSON) |
| POST `/api/assistant/suggest-description`, `/api/assistant/suggest-category` | `AssistantController::suggestDescription`, `suggestCategory` | X (JSON) |

**Session rules (all roles).** Strict session mode; cookie `httponly`, `SameSite=Strict`, `secure` when behind the HTTPS tunnel. 30-minute idle timeout. While `must_change_password` is set only `/profile`, `POST /profile/password` and `POST /logout` are reachable (`/api/*` gets a JSON 403). Login lockout as in section 2.

**Sidebar visibility.**

| Role | Items |
|---|---|
| Admin | Dashboard, Inquiry Center, Occupancy · Boarders, Rooms and Beds, User Management · Payments, Record Payment, Expenses, Penalties · Maintenance History, Incident History |
| Staff | Dashboard, Inquiry Center · Maintenance Queue, Incidents · Maintenance History, Incident History |
| Boarder | Dashboard · Report a Repair, Pay Rent · Incidents |
| All | Notifications bell, profile pill, logout |

## 5. Page by page: input, process, output, sort / group / filter / pagination

Marker: **server** = in SQL or PHP; **client** = browser JavaScript only (not reflected in the URL for the server, and lost if the page is fetched without JavaScript).

### 5.1 Admin pages

| Page and route | Input | Process | Output | Sort / group / filter / page |
|---|---|---|---|---|
| **Dashboard** `GET /admin/dashboard` | none | Applies due move-outs; counts pending/flagged payments and unresolved SOS; re-scores open maintenance with decay; **writes today's occupancy snapshot**; `BillingService::collectionsSummary` computes every active resident's balance | 4 KPIs, receivables tiles, top-5 debtors, tier bars, 5 recent expenses, warning banner if the DB runs as root without a password | server: debtors `usort` overdue DESC then owed DESC, slice 5 (`BillingService.php:214,230`); expenses `ORDER BY created_at DESC LIMIT 5` (`DashboardController.php:32`); collected-this-month bounded by `reviewed_at` |
| **Inquiry Center** `GET /admin/inquiry-center` (admin, staff) | Walk-in form: name (≤150), phone (7-15 digits), e-mail (optional), room type, move-in date (optional), message (≤2000), staff notes | `Inquiry::submit` inserts with `source='staff'`; notifies admins | KPIs, notification feed, recent-inquiries table (**admin only**) | server: `ORDER BY created_at DESC, id DESC LIMIT 10` (`Inquiry.php:109`); client: text search and All/Unread/Pinned tabs. Older inquiries have no list |
| **Inquiry detail** `GET /admin/inquiries/{id}` | status ∈ new / contacted / closed; "convert to boarder" | Marks its notifications read; conversion in a transaction with `FOR UPDATE` | Read-only record, status dropdown, convert button | none |
| **Boarders (add and assign)** `GET /admin/boarders[?inquiry=N]` | Create: name, e-mail, phone, password (policy), inquiry id, optional bed. Assign: boarder, bed | Create is one transaction (user, optional bed, profile `pending`, inquiry converted, audit). Assign locks the bed, frees the old one, recalculates balance, notifies | 5 KPIs, add form, assign-to-bed form with a room-type filter | server: residents `ORDER BY users.name` (`BoarderProfile.php:32`); vacant beds `usort`; client: bed filter by room type. No search or paging |
| **Resident Directory** `GET /admin/staff?view=boarders[&archived=1]` | Edit drawer: name, e-mail, phones, status, note; archive (reason ≤255); restore | `updateInfo` in a transaction, logs status changes; archive = move out today, login disabled, records kept | Status tabs, table with balance owed | server: `ORDER BY users.name`, all rows, archived via `?archived=1`; **client only**: search (name, e-mail, room, bed) and status tab (the `q`/`status` GET params are never read by PHP) |
| **User Management (all)** `GET /admin/staff` | GET `q` (≤100), `type`, `status`, `sort` ∈ name / newest / last_login, `page` | `User::managementList`, one SQL with switchable filters; search matches name, e-mail, phone digits, room number, `#id` | 5 KPI links, filter form, table with Previous/Next | server: `ORDER BY CASE …` then `LIMIT ? OFFSET ?` (`User.php:99-102`), **25 per page** (`StaffController.php:20`); counts `GROUP BY role, status` (`User.php:138`); admins never listed |
| **Staff accounts** `GET /admin/staff?view=staff` | Create: name, e-mail, phone, password; status active/inactive; phone; reset password | Create sets temporary password (forced change, 72 h) and audits; deactivation ends sessions at the next request and the flash reports SOS alerts the person had acknowledged | Staff table, audit history (20 per person), reset dialog | server: `ORDER BY name` (`User.php:154`); audit `ORDER BY id DESC`, trimmed to 20 in PHP. No search or paging |
| **Boarder profile** `GET /admin/boarders/{id}[?payments=all]` | Info, dates (move-in/out), cancel move-out, notes (≤10000), reset password, archive, issue penalty (rule, amount, due date, reason), mark-paid/waive (reason ≤160) | Dates via `updateDatesAndStatus` (a move-out ≤ today moves out immediately and frees the bed; a future date is only scheduled); each change recalculates the balance | Hero, details, bed, dates, financial overview, penalties, payments, repairs, incidents, status timeline, notes, danger zone | server: payments `ORDER BY created_at DESC LIMIT 12` (500 with `?payments=all`); penalties `ORDER BY applied_at DESC` unbounded; repairs and incidents `LIMIT 10`; status log `ORDER BY created_at DESC, id DESC` |
| **Rooms and Beds** `GET /admin/rooms[?sort=&group_by=]` | Add room (number ≤20 unique, floor, type or capacity and price); update room; add beds (label, count 1-50, series naming); rename/delete bed; edit room type (name, capacity, price, price unit, badge, up to 8 features); electricity bill (room, label ≤60, amount 1-1,000,000) | Room-type edits cascade capacity and rent to linked rooms. An electricity bill locks the room's active/on-notice residents, splits equally to the centavo, sets each due date to the next rent due date, recalculates balances and notifies each resident | 4 KPIs, beds-by-room table, configuration editor, room-type cards, electricity form and table | server: `sort` ∈ room, floor, type, capacity, price (asc/desc) by `usort` (`RoomController.php:24-37`), default numeric room DESC (`Room.php:16`); electricity `GROUP BY eb.id ORDER BY eb.id DESC LIMIT 20` (`ElectricityBill.php:102-104`); **group-by** (none / floor / type / capacity) and the live re-sort and bed filter (All/Vacant/Occupied) are **client only**, remembered in the URL and `localStorage` |
| **Occupancy** `GET /admin/occupancy` | none | Upserts today's snapshot, reads the last 30 days | KPIs (current %, occupied, available, 30-day average), capacity distribution, snapshot table with % badge (≥85 error, ≥60 warning) | server: `WHERE snapshot_date >= CURDATE() − INTERVAL 30 DAY ORDER BY snapshot_date` (`OccupancySnapshot.php:23`), shown newest first. Fixed window, no controls |
| **Payments queue** `GET /admin/payments` | Approve: approved amount (>0, ≤ claimed), optional waive late fee with reason (3-160). Reject: reason (3-160) | `PaymentReviewService::approve` in one transaction (late fees, waiver, approval, balance sync); reject can reverse an approved payment | 4 KPIs, table with expected vs claimed and ⚠, "same reference as #…" warning, allocation badges, proof/receipt link, review note, modals with late-fee preview | server: `Payment::all` `ORDER BY created_at DESC`, **no limit** (`Payment.php:124`); allocations loaded for all payments; a balance calculation per pending row; client: All/Pending/Verified/Rejected tabs, search (boarder, period, method, reference), density toggle |
| **Record payment** `GET/POST /admin/payments/record` | Boarder, amount (≤7 digits, ≤2 decimals, ≤1,000,000), method (+ cash, other), received-on date (not future, not before move-in), reference | `PaymentRecorder::record`: refuses archived/pending residents, a reused reference, or an identical payment by the same user within 30 s; inserts pending then approves in the same transaction; audit `payment_recorded` | Redirect to printable receipt; page shows balance panel, last 4 payments for the resident, last 5 manual payments, in-person KPIs | server: residents `ORDER BY u.name`, `LIMIT 4`, `LIMIT 5` |
| **Receipt** `GET /payments/{id}/receipt` | path id | Approved payments only; admin or the owning boarder | Printable HTML with allocations and signature lines | none |
| **Expenses** `GET/POST /admin/expenses` | Category (≤100, free text), amount (>0, ≤10,000,000), description (≤255) | `Expense::create` with the acting admin; no audit row | KPIs (count, total, top category, latest), table, link to the month's ledger | server: `ORDER BY created_at DESC, id DESC`, **unbounded** (`Expense.php:22`); no filter or paging |
| **Penalties and rules** `GET /admin/penalty-rules` | New rule (name ≤150, condition type free text ≤100, amount); edit amount; toggle; issue (resident, rule, amount, due date, reason); waive/mark paid (reason); run check; send rent reminders | `PenaltyEngine::runCheck` for `late_per_day` rules; manual issue and waive run in transactions with balance sync; reminders target the oldest unpaid cycle, once per day per message | 8 KPIs, forms, rules table, penalty history | server: rules `ORDER BY name`; history `ORDER BY applied_at DESC LIMIT 150` (`Penalty.php:184-185`); issue picker lists active residents only. No filter or paging |
| **Ledger export** `GET /admin/ledger/export` | `from`, `to` (real dates; default month start to today), `format=csv` | `LedgerBuilder::rows` UNION of approved payments, expenses, penalties; CSV is BOM-prefixed and formula-injection-safe | `rjm_financial_statement_*.xlsx` (default) or `rjm_ledger_*.csv` | server: `ORDER BY at, type, id` (`LedgerBuilder.php:42`); statement categories `ksort`ed |

### 5.2 Staff pages

| Page and route | Input | Process | Output | Sort / filter / page |
|---|---|---|---|---|
| **Staff dashboard** `GET /staff/dashboard` | ack / resolve buttons | `acknowledge` is a conditional `UPDATE … WHERE status='active'`, and the row count decides whether the boarder is notified; page polls every ~8 s | KPIs, SOS table, top-5 maintenance queue | server: SOS `ORDER BY created_at DESC LIMIT 50`; queue = the decayed queue, first 5 in the view |
| **Maintenance queue** `GET /staff/maintenance` | status ∈ open / in_progress / resolved; reason (1-255) to re-open | `updateStatus` sets or clears `resolved_by` / `resolved_at`, notifies the boarder on in_progress or resolved; **no transition guard**; AI helper buttons | KPIs, ticket table with tier badge, proof link, update form | server: all unresolved rows re-scored, `usort` by tier rank then `created_at` ASC, `array_slice` 100 (`MaintenanceRequest.php:34-58`); client: tier filter buttons only |
| **Maintenance history** `GET /staff/maintenance/history?page=` | `page` (no upper bound) | `allWithDetails` + `countAll` | Table, pager, resolver and resolved time | server: `ORDER BY created_at DESC LIMIT 50 OFFSET ?` (`MaintenanceRequest.php:96-97`); no search; Resolved/Open KPIs count only the current page |
| **Incidents** `GET /staff/incidents` (staff, admin, boarder) | type (free text ≤100), description (≤5000); resolve with notes (≤5000, staff/admin) | Create notifies all staff and admins; resolve refuses an already-resolved incident and notifies staff, admins and the reporter; boarders see only their own | KPIs, records table, report form | server: `ORDER BY incidents.created_at DESC`, **unbounded** (`Incident.php:32`); no filter |
| **Incident history** `GET /staff/incidents/history?page=` | `page` | scoped for boarders | Paged table with resolution notes, resolver, time | server: `ORDER BY created_at DESC LIMIT 50 OFFSET ?` (`Incident.php:56-57`); per-page KPIs |
| **SOS** (no page) `POST /api/sos`, `GET /api/sos/active`, ack/resolve | CSRF only | reuses an unresolved alert from the last 2 minutes; otherwise inserts and broadcasts with room and bed | JSON `{ok, alert_id}`; active list up to 50 | server: `ORDER BY created_at DESC LIMIT 50` |

### 5.3 Boarder portal

| Page and route | Input | Process | Output | Sort / page |
|---|---|---|---|---|
| **Dashboard** `GET /portal/dashboard` | SOS button | Read-only balance calculation; loads active staff phones as a backup contact | SOS button, staff phones, penalty banner, KPIs, repair table, rent ledger, unread notifications | server: payments and repairs `ORDER BY created_at DESC LIMIT 10`, view shows 5; notifications unread only, pinned first, unbounded |
| **Pay rent** `GET/POST /portal/payments` | method, reference, claimed amount, proof image (required) | One open submission rule under a row lock; stored `pending` against the oldest unpaid month; file deleted if the insert fails; admins notified | Owed lines (oldest first, overdue badge), form, last 5 payments | server: `ORDER BY created_at DESC LIMIT 5` |
| **Report a repair** `GET/POST /portal/maintenance` | category, description, optional media | Local scoring (see feature 2), staff and admins notified; live preview endpoint | Form, live urgency card, AI "improve description" and "auto category" buttons, last 5 requests | server: `ORDER BY created_at DESC LIMIT 5` |

### 5.4 Shared pages

| Page and route | Input | Process | Output | Sort / filter / page |
|---|---|---|---|---|
| **Notifications** `GET /notifications` | read / unread / pin / delete / read-all | ownership checked per id | KPI cards and typed rows with action links; bell polls every 15 s | server: `ORDER BY is_pinned DESC, created_at DESC`, **no limit** (`Notification.php:32`); client: All / Unread / Pinned / Read / SOS / Maintenance / Billing / Inquiry (substring of the type) and text search |
| **Profile** `GET /profile` | name, e-mail, boarder phones; current / new / confirm password | password change regenerates the session ID and signs out the account's other sessions; forced-change gate | role-specific stat cards, forms | none |
| **Login** `GET/POST /login` | e-mail (≤150), password (≤1024 bytes) | lockout, dummy-hash timing, generic error, distinct message for an expired temporary password (after the right password) | form and error | none |
| **Landing** `GET /`, `/api/blueprint/rooms`, `POST /inquire` | inquiry: name, phone (server 7-15 digits; the `09…` pattern is browser-only), room type, move-in date (past dates accepted by the server), e-mail, message, honeypot | honeypot returns a fake success; 5 per IP per hour; notifies admins | marketing sections, live stats, room-type cards, SVG blueprint (polls every 20 s), inquiry form, map | server: landmarks by distance; blueprint `GROUP BY r.id ORDER BY floor, room_number`; room types `ORDER BY sort_order, id` |
| **Layout / nav / error** | none | CSRF meta tag, flash toasts, role navigation | shell | none |

## 6. Sorts, group-bys and pagination at a glance

| List | Default sort (server) | Group-by | Filter / search | Page size |
|---|---|---|---|---|
| Admin dashboard debtors | overdue DESC, owed DESC | none | none | top 5 |
| Resident Directory | name | none | client only (search, status tab, archived toggle via URL) | all |
| User Management | name / newest / last login (choice) | counts by role and status | server (`q`, type, status) | 25 |
| Staff accounts | name | none | none | all (audit 20 each) |
| Rooms and Beds | numeric room DESC; 10 server sorts | **client only** (floor / type / capacity) | client bed filter | all rooms; electricity 20 |
| Occupancy | date, last 30 days | none | none | 30 rows |
| Payments queue | created DESC | none | client only (tabs, search) | **unbounded** |
| Expenses | created DESC | top-category tally in PHP | none | **unbounded** |
| Penalty history | applied DESC | none | none | 150 |
| Maintenance queue | tier rank, then oldest | tiers | client tier buttons | 100 |
| Maintenance / incident history | created DESC | none | **none** | 50 |
| Incidents (live) | created DESC | none | none | **unbounded** |
| SOS | created DESC | none | none | 50 |
| Notifications | pinned, then newest | none | client only | **unbounded** |
| Inquiries | created DESC | none | client search | 10 |
| Ledger | by date, type, id | statement by month and category | date range | file |

## 7. Gaps against the paper (listed only, nothing changed)

| # | Paper wording | What the code does | Tag |
|---|---|---|---|
| 1 | "compiles a **CSV** financial ledger" | Default download is a styled `.xlsx`; CSV only with `?format=csv`; the assistant says "(CSV)" but links the XLSX | paper overstates |
| 2 | "system automatically **flags any mismatch** between claimed and expected rent" | View-side ⚠ at tolerance 0.01; expected = total balance, not rent; no stored flag, no notification, not on the dashboard; `flagged` status dead | paper overstates (and code incomplete: flag not persisted) |
| 3 | "**Real-time** notifications for rent dues…" | 15-second polling; rent-due reminders and the late-fee run are manual admin buttons; dashboard never refreshes | paper overstates |
| 4 | "**visualizes occupancy trends** over time" | 30-day table with bars; snapshots only on days an admin opened a page | paper overstates |
| 5 | "**searchable**, paginated archive" | ~~Paginated, newest first; no search or filter; KPI cards are per page~~ **[fixed WP-21]** search plus status/priority filters; counts cover the whole filtered set | was: paper overstates |
| 6 | "including **who resolved each one and when**" | True for maintenance and incidents. SOS keeps no resolver, no acknowledged time, no history page. Re-opening clears the resolver on the row | code incomplete (SOS), paper overstates if SOS is included |
| 7 | "computes penalties automatically based on **Admin-configurable rules**" | Only `late_per_day` rules are automated; condition type is free text; no grace period or cap; runs only on a button or on payment approval; no scheduler | paper overstates |
| 8 | "maintains each boarder's status… with a logged history" | True. Transitions are unrestricted and manual; only scheduled move-outs are automatic and only when certain pages load | code incomplete (no lifecycle rules, which `FEATURES.md` describes) |
| 9 | AI "**grounded in the system's own real-time data**" | True for the rule-based lookups. Free chat is grounded only for boarders; staff and admin get name and role only; boarder repair rows are fetched but unused | paper overstates |
| 10 | "ranks them by rule-based repair-priority scoring" | Accurate. The UI text elsewhere calls it "AI" (`FEATURES.md`); it is keyword rules and should not be described as AI | wording risk |
| 11 | "records boardinghouse **expenses**" | Admin only, free-text category, no date, no receipt, no void; `FEATURES.md` says staff should be able to log | code incomplete |
| 12 | "aggregates… **expenses** in one view" (dashboard) | Shows the 5 most recent expenses, no monthly total | paper overstates |
| 13 | "lets staff log safety incidents" | True; boarders can also report, via `/staff/incidents` or the unguarded `/portal/incidents` | accurate, broader than stated |
| 14 | Penalty issuing "for Admin and Staff" (`issuePenalty` docblock) | Both routes are admin only | code incomplete (docs disagree with routes) |

## 8. Authorization and security observations (listed only)

1. **[fixed WP-21]** ~~Idle timeout is effectively defeated.~~ `last_activity` is refreshed on every authenticated request, and the nav polls `/api/notifications/unread` every 15 s, so an open tab never idles out. PHP's default `gc_maxlifetime` (1440 s) is shorter than the 30-minute limit and nothing overrides it (environment; not verified).
2. **[fixed WP-21]** ~~Admin can reach but not navigate to~~ the Maintenance Queue and Incidents (routes allow admin; `NavRegistry` lists staff only). Admins arrive through dashboard cards.
3. **[fixed WP-21]** ~~Boarders can open Incident History~~ (route allows it, `NavRegistry` lists the role) but the sidebar has no entry for them.
4. **Duplicate or unused routes:** `/portal/incidents` (no role guard) and `POST /staff/penalties/issue` (admin only, no form uses it).
5. **CSRF is checked per handler, not centrally.** Every POST does it today; a new route could omit it. Failure responses differ (bare 400, flash redirect, JSON). `POST /inquire` has none by design. The token is not rotated at login.
6. **`POST /logout` has no `AuthMiddleware`.** After the session expires, logout returns a bare 400 instead of redirecting.
7. **Audit coverage is partial.** Audited: resident create/update/archive/restore, staff create/phone/status, password resets, move-out schedule/cancel/apply, manual payment, re-opened repairs. **Not audited:** ~~payment approve and reject~~ (**[fixed WP-21]**: `payment_approved` / `payment_rejected` / `payment_reversed`), penalty issue/waive/rule edits, expenses, room/bed/room-type edits, electricity bills, inquiry status, SOS ack/resolve, incident resolve, maintenance status changes, profile e-mail or password changes, login and logout.
8. **Profile changes:** changing the login e-mail needs no current password; the current-password check on a password change has no attempt throttle.
9. **GET requests that write:** `/admin/dashboard` and `/admin/occupancy` write the occupancy snapshot; the dashboard, resident directory, staff list and rooms page apply due move-outs.
10. **Staff cannot edit their own phone** from `/profile`; the value is validated and then discarded (`ProfileController.php:143-145`). Only an admin can set it.
11. **Login-attempt rows** for unknown e-mails are pruned only after a successful login, so the table can grow under spraying. Case-variant e-mails may bypass the per-e-mail lockout depending on collation (not verified).

## 9. In the code but missing from the IPO figure and the feature list

Electricity sub-metering (equal split per room, billed with rent) · walk-in/cash payments with printable receipts and receipt numbers · public landing page with live room blueprint and inquiry form · Inquiry Center and inquiry-to-boarder conversion · staff account management · audit log · archive and restore of residents · rent proration and credit carry-forward · late-fee waivers with reasons · bilingual (English/Tagalog) rule-based assistant that pre-fills the repair and incident forms · login lockout and date guards · settings table (`property_opening_date`) · forced password change with temporary passwords. Planned, not built: CCTV people counter, e-mail notifications.

## 10. Large-list and validation flags (listed only)

**Lists with no server-side limit, filter or sort control:** Payments queue (also loads every allocation and runs a balance calculation per pending row), Expenses, live Incidents, Notifications, Resident Directory (search and tabs are client-side; `q` and `status` GET parameters are ignored by PHP), Staff accounts, Rooms (group-by is client-side). There is no list of past inquiries beyond the latest 10. The Maintenance queue is capped at 100 without paging or sort control.

**Misleading counts:** Resolved/Open KPIs on both history pages count only the current page. The live Incidents page counts from the whole unbounded list.

**Validation and robustness:**
- `POST /inquire`: no CSRF; a past `move_in_date` is accepted; the `09…` phone pattern exists only in the browser.
- `createRule`: free-text `condition_type`, no duplicate-name check.
- `issuePenalty`: does not require an active resident; the due date may be in the past; the non-admin amount branch is dead code because the route is admin only.
- `MaintenanceController::updateStatus`: no transition guard, no audit row except re-open; the re-open reason field is unreachable from the queue page.
- `IncidentController::create` and `ExpenseController::create`: free-text type and category, so spelling variants split totals.
- `BoarderController::updateInfo`: `beginTransaction()` without a rollback path; status-change reason is optional in PHP although the form says it is required.
- `?page=` on both history pages has no upper bound (an out-of-range page shows an empty table).
- Several actions answer a CSRF failure with a bare 400 page rather than a flash message.
- The assign-to-bed picker lists moved-out residents that the server then rejects.
- Manual payments store `created_at` as the received date plus current time, so the queue orders them by received date rather than entry time.
