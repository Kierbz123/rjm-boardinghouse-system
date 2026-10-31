# RJM Boardinghouse System: Presentation Script

Run in the browser at `http://127.0.0.1:8000` (or the `start-system.bat` address). Sign in as **admin** for Parts 2–6. Lines in *italics* are what you do on screen; plain text is what you say.

---

## Part 1: Landing Page (public, no login)

*Open the landing page.*

"This is the first thing a prospective resident sees. It is the public face of RJM Boardinghouse in Tupi. No account is needed. Its job is to turn a curious visitor into an inquiry."

*Scroll to Rooms.*
"**Rooms:** each room type is shown with a photo. 'Ask about this room' opens an inquiry form already tagged with that room type, so the owner knows what the person wants."

*Open the floor plan, switch floors, zoom.*
"**Floor plan:** an interactive site plan. The visitor picks a floor and a room and sees its details. It works with the keyboard and screen readers too."

*Scroll to Amenities, Numbers, Location.*
"**Amenities** states what every resident gets. **Numbers** shows live figures from the house records, not typed-in text. **Location** gives office hours, phone numbers and directions."

*Click 'Ask about a room', submit a sample inquiry.*
"Every 'Ask about a room' button opens the same inquiry form. This is the one place where the public writes into the system. Whatever they send lands in the admin's **Inquiry Center**, which we will see shortly."

*Sign in.*
"Residents, staff and the admin all sign in from here. The system shows each role only its own pages. Access is enforced on the server, not just hidden in the menu."

---

## Part 2: Overview

**Dashboard**
"The admin's home screen. It brings the whole house onto one page: today's occupancy, collections, open repairs by priority and any active SOS alerts. It reads from the other modules. It holds no data of its own."

**Inquiry Center**
*Open the inquiry just submitted.*
"This is where the landing-page inquiries arrive. The admin can reply and track each one. The purpose is that no prospect is lost. A walk-in or a message becomes a record with a status."

**Occupancy**
"Shows how full the house is and how that changed over time. The admin uses it to spot vacancy patterns and decide on pricing or promotion. One caveat: a daily snapshot is taken only when an admin opens a page, so some days may be missing."

---

## Part 3: Boarding

**Boarders**
"Where a resident enters the system. The admin adds a boarder, which creates the account only, then assigns a bed. The full resident list lives in User Management."

**Rooms & Beds**
"Every room, every bed, and which are free. Occupancy is tracked **per bed**, so two people can never be booked into the same bed. This page is also where the admin enters each room's **electricity bill**. The system splits it equally among the room's residents, and each share falls due with that resident's next rent."

**User Management**
"One directory for everyone. The **Boarders tab** is the Resident Directory, with profiles, editing and archiving. The **Staff tab** is where the admin creates staff accounts and switches their access on or off. Staff get fewer pages than the admin, which is the role-based access in action."

---

## Part 4: Finance

"Billing rules, in one sentence: rent is charged in **30-day periods from the move-in date**, each due 30 days after it starts. Water is free."

**Payments**
"Residents pay by GCash, Maya or bank transfer, then upload their receipt. Every receipt arrives as **pending**. The admin checks it and approves, rejects (a reason is required) or reverses it. **Nothing is ever approved automatically.** If the amount differs from what is owed, or the reference number was used before, the admin sees a flag. On approval the money clears the oldest unpaid month first, then penalties, and any extra becomes credit. Every decision is written to an audit log."

**Record Payment**
"For money taken in person, such as cash. The admin enters it directly and prints the receipt, so cash payments go through the same ledger as online ones."

**Expenses**
"Where the house's spending is recorded: utilities, repairs, supplies. Expenses feed the ledger export and the dashboard, so the owner sees income against cost."

**Penalties**
"Holds three things: the **penalty rules** (amounts the admin can change without touching code), the **penalties issued**, and the **late-fee check**. A late fee starts the day after a period's due date. It stops counting the day the resident **submits** a receipt, so slow admin review is never charged to the resident. The admin can waive a fee, but must give a reason, and the system records who waived it and why."

---

## Part 5: Active Tasks

**Maintenance Queue**
"Residents report a repair with a description, category and a photo. The system scores it using local rules: keywords like 'fire' or 'exposed wire', the category, whether there is a photo, and **how long it has waited**. That score becomes Critical, High, Medium or Low. The queue is sorted most urgent first, so staff handle the dangerous problem before the cosmetic one. The scoring runs on this machine with no outside service."

**Incidents**
"The safety record. Staff log incidents such as noise, theft or fights, and follow them up. Residents can reach it too. It is a history independent of live alerts. Next to it sits the **SOS button** on the resident dashboard. One tap alerts staff and the admin with the resident's name, room and time."

---

## Part 6: Records

**Maintenance History**
"Repairs that are finished. Searchable, with status and priority filters. It is the proof of what was fixed and when."

**Incident History**
"Resolved incidents, with search and a status filter. This is the paper trail if a dispute comes up later."

**Notifications**
"Everything the system has told the signed-in user, newest first: payment approved or rejected, repair updates, penalties, alerts. It is in-app only. No e-mail or SMS is sent."

---

## Closing

"To summarize: a visitor inquires on the landing page, the admin turns them into a resident with a bed, the resident pays and reports problems through their own portal, and the admin and staff review, fix and record. Every step is logged, and it all runs locally on the owner's machine."

---

## Presenter notes
- Say "pending review", not "verified", for payments. The admin always decides.
- Do not claim live CCTV. The landing page still mentions it, but the system has no camera page.
- Demo accounts have random passwords printed once by `php database/seed.php`. Do not read them aloud or paste them into slides.
