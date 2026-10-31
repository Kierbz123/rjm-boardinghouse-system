# Database design diagram (ERD)

Source of truth: the live `rjm_boardinghouse` schema (MariaDB/InnoDB), with all migrations 0001–0046 applied,
read on 2026-10-29. `schema-snapshot.sql` is that schema without data or triggers.

| File | What it is |
|---|---|
| `erd.png` | 5660 × 3080 PNG (2× render) for the thesis/report/slides; no title or legend on the image (the notation is explained below) |
| `erd.svg` | Vector version; every table is a `<g id="tbl_name">`, every relationship a `<g class="rel" data-child=… data-parent=…>` |
| `erd.drawio` | Editable in diagrams.net: each table is a container, each column a text cell, each relationship its own edge attached to the FK column |
| `build_erd.py` | Regenerates the SVG and draw.io from `schema-snapshot.sql` (placement and the 3 app-level links are the only authored parts) |
| `schema-snapshot.sql` | Schema dump the diagram is built from |

Regenerate (from this folder; paths as on this machine):

```bash
/c/xampp/mysql/bin/mysqldump.exe -uroot -h127.0.0.1 --no-data --skip-comments --skip-triggers rjm_boardinghouse | grep -v '^/\*!' | sed 's/ AUTO_INCREMENT=[0-9]*//' > schema-snapshot.sql
python build_erd.py
"/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless --hide-scrollbars --force-device-scale-factor=2 --screenshot="$(pwd -W)\\erd.png" --window-size=2830,1810 "file:///$(pwd -W)/erd.svg"
```

(The window size must equal the canvas size printed by `build_erd.py`.)

## Inventory

24 tables, 40 enforced foreign keys, 3 application-level links.

- **Accounts & system:** users, settings, audit_log, login_attempts, notifications, schema_migrations
- **Housing:** room_types, rooms, beds, boarder_profiles, boarder_status_log, occupancy_snapshots
- **Billing:** rent_charges, electricity_bills, electricity_charges, payments, payment_allocations, penalty_rules, penalties, expenses
- **Service:** maintenance_requests, sos_alerts, incidents, inquiries

## Relationship notation

- Crow's foot = many side; circle = optional; bar = one. The child end is "zero or many", or "zero or one" when the FK column is the PRIMARY KEY or UNIQUE (`boarder_profiles.user_id`, `beds.current_boarder_id`, `boarder_profiles.bed_id`, `inquiries.converted_user_id`).
- Parent end: two bars = FK is NOT NULL (parent required); circle + bar = FK is nullable.
- Solid = a FOREIGN KEY constraint exists. Dashed grey = application logic only.
- Line colour = the referenced table. Lines land on the parent's side at spread-out points, not on the exact `id` row, so several lines into one table stay distinguishable; read the child's FK column for the exact column.

## Application-level links (no FOREIGN KEY; confirmed in `src/Services/PaymentAllocationService.php`)

`payment_allocations.reference_id` (VARCHAR) is joined to `penalties.id` when `allocation_type='penalty'`, to `electricity_charges.id` when `'electricity'`, and holds the billing period for `'rent'` (matches `rent_charges.period`). Also not drawn as lines: `notifications` and `audit_log` carry `entity_type` + `entity_id` (polymorphic), and `login_attempts.email` matches `users.email` by value.

## Observations (documented only; nothing in the database was changed)

1. **Polymorphic `payment_allocations.reference_id`** has no referential integrity: a deleted penalty or charge leaves an orphan allocation. Option: separate nullable FK columns.
2. **Two-way bed link:** `beds.current_boarder_id` and `boarder_profiles.bed_id` both record who sleeps where (each UNIQUE) and can drift apart; migrations 0029/0030 already patch this.
3. **`boarder_profiles.room_id` is derivable** from `bed_id → beds.room_id` but stored redundantly.
4. **`rooms.capacity` / `rooms.base_price` duplicate `room_types.capacity` / `room_types.price`.**
5. **`boarder_profiles.outstanding_balance`** is a cached value derived from payments, charges and penalties (per its column comment).
6. **Mixed period formats:** `payments.billing_period` VARCHAR(20) (`YYYY-MM`) versus `rent_charges.period` and `penalties.billing_period` CHAR(10) (`YYYY-MM-DD`, older rows `YYYY-MM`). Joins rely on app code.
7. **`payments` and `penalties` reference each other** (`payments.target_penalty_id`, `penalties.paid_payment_id`); both are nullable so inserts work, but deletes need care.
8. **Free-text status columns:** `users.status` and `boarder_status_log.old_status/new_status` are VARCHAR while sibling status columns are ENUMs.
9. **`ON DELETE` rules are mixed:** most user FKs use the default (RESTRICT), optional/audit ones SET NULL, and `payment_allocations.payment_id` and `electricity_charges.bill_id` CASCADE. Users are archived rather than deleted, so this holds in practice.
