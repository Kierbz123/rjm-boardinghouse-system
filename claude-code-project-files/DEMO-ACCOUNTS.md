# Demo accounts

Use these to log in at **http://127.0.0.1:8000/login** after starting the system with `start-system.bat`.

| Role | Email | Name in the system |
|---|---|---|
| Admin | `admin@rjm.test` | Admin User |
| Staff | `staff@rjm.test` | Staff User |
| Boarder | `boarder@rjm.test` | Demo Boarder (Room 1, Bed A) |

> **There are no fixed passwords.** `php database/seed.php` generates a random password for each account
> and prints it once. Each account must choose its own password at first sign-in; until it does, it can only
> open the Profile page. Accounts on a database seeded before this change are asked to choose a new password
> the next time they sign in. `start-online.bat` refuses to open the system to the internet while any account
> still has a default password.

## What each account can do

### Admin — the owner / manager
Sees and controls everything.
- **Dashboard:** pending payments, open repairs by priority, active SOS alerts, recent expenses, occupancy.
- **Boarders:** add residents, assign beds, change status (pending, active, on notice, moved out), edit move-in/move-out dates, add notes. *Remove* archives a resident who has payment history (records kept, restorable) and deletes only residents with no payment history.
- **Rooms & Beds:** add, edit and delete rooms; add, rename and delete beds; a bed can't be double-booked (the database refuses it) and a room can't have more beds than its capacity.
- **Staff Accounts:** add staff, deactivate or reactivate them, reset a forgotten password.
- **Forgotten passwords:** set a new temporary password for a resident (on their page) or a staff member.
- **Payments:** approve or reject boarders' receipts, or *Reverse* an approved payment. Every payment waits for an admin.
- **Penalties:** create penalty rules, issue penalties with any amount, run the late-fee check, mark a penalty paid.
- **Expenses, Occupancy, Ledger export (CSV), Inquiry Center** (room inquiries from the public website).
- Also has every staff screen (maintenance queue, incidents, SOS).

### Staff — caretaker / maintenance
Day-to-day operations, **no access to money pages** (payments, expenses, ledger, boarder accounts).
- **Dashboard:** live SOS alerts (acknowledge / resolve) and the open repair queue.
- **Maintenance queue:** sorted by priority; set requests to in progress or resolved.
- **Incidents:** log and resolve incidents; see the full history.
- **Penalties:** may issue a penalty, but only at the rule's set amount (cannot type a custom amount).
- **Inquiry Center:** log walk-in or phone inquiries.

### Boarder — a resident
Sees only their own information.
- **Dashboard:** room and bed, what they owe (rent + penalties, or credit), recent payments and repairs, the red **SOS** button.
- **Pay rent:** upload a receipt photo and the amount paid; it shows as pending until an admin approves it.
- **Report a repair:** describe the problem (with an optional photo); a live preview shows the priority it will get.
- **Incidents:** report an incident and follow only their own reports.
- **Notifications** and **Profile** (change name, contact numbers, password).

The demo boarder was activated on 3 October 2026, so rent for October 2026 is already owed — useful for
trying the payment flow.

## Other accounts in the database

The live database also has two real boarder accounts that are **not** demo accounts and keep their own
passwords. They are not listed here.

## Forgot a password?

**A resident or a staff member:** sign in as the admin and set a new temporary password for them.
- Resident: **Boarders → open the resident → Reset Sign-in Password**.
- Staff: **Staff Accounts → Reset password** on their row.

They are signed out everywhere, any lockout from wrong attempts is lifted, and they should change the
password under **Profile** after signing in.

**The admin account itself** has no one above it, so it can only be reset from the command line. From the
`claude-code-project-files` folder (change the email and password to suit):

```
C:\xampp\php\php.exe -r "require 'src/autoload.php'; App\Models\User::updatePassword(App\Models\User::findByEmail('admin@rjm.test')['id'], 'TYPE-A-NEW-PASSWORD-HERE'); echo 'done';"
```

Changing a password signs that account out everywhere else.

## Fresh database

On a new, empty database, `php database/migrate.php` followed by `php database/seed.php` creates these three
accounts plus Room 101 with two beds. (`seed.php` stops with an error if the accounts already exist.)
