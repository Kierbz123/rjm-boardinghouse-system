# Live CCTV, motion sensing and people counting — plan

**Status:** plan only, nothing built yet. It needs the owner decisions in §6 before any code is written.
**For the demo:** an ordinary phone stands in for the CCTV camera.

The landing page now says "Live CCTV covers the entire boardinghouse." That describes the real house. This plan adds a camera feature *inside the system*: a live view, motion detection, an hourly people count and face snapshots. It stays within this project's rules: localhost only, no internet calls, and PHP + MariaDB as the system of record.

---

## 1. What it would do

| Capability | What the admin sees |
|---|---|
| **Live view** | A *Camera* page showing the feed, with a "motion now" indicator. |
| **Motion sensor** | Every time movement starts and stops, with the time. Software only: no extra hardware. |
| **People per hour** | People crossing a line drawn across the doorway or hallway, counted *in* and *out*, shown as an hourly chart and table. |
| **Face snapshots** | A small cropped photo of each face detected, with the time, in a private gallery for admins. |
| **Resident vs visitor** *(optional, phase C3)* | Faces matched against residents who **opted in**, so counts split into "residents" and "others". Visitors are never enrolled. |
| **After-hours alert** *(optional, phase C4)* | An in-app notification to staff when there is motion in a common area during hours the admin sets. There is no curfew; this is only an alert. |

## 2. Using a phone instead of CCTV (demo setup)

1. Install a free "phone as webcam" app on the phone and its small driver on the PC: **DroidCam** (Android/iPhone) or **Iriun Webcam**. Connect by USB, or over the same Wi-Fi.
2. Windows then lists the phone as a normal webcam.
3. Open the system's Camera page **on that PC** at `http://127.0.0.1:8000/admin/camera`. The browser asks to use the camera; choose the phone.
4. Mount the phone on a tripod or wall clip facing the entrance or hallway, keep it on its charger, and turn off auto-lock.

Why this route? A browser only allows camera access on a secure page, and `127.0.0.1` counts as one. So the app stays on localhost: the phone never has to reach the web server, and nothing goes to the internet. Streaming apps like *IP Webcam* would need the PC to pull a feed from the phone's IP address, which the browser blocks from being analysed on a canvas, so this plan avoids them.

**Demo limits to state honestly in the capstone:**
- One phone covers one spot, not the entire house.
- Counting only runs while the Camera page is open and the PC is awake.
- Accuracy drops in low light, with people walking in groups, or at steep angles. Check it against a manual count (§5).

## 3. How it works

```
Phone ──(DroidCam/Iriun, USB or Wi-Fi)──▶ PC webcam device
                                              │
             Browser tab: /admin/camera  (127.0.0.1, admin/staff only)
             1. Motion: compare tiny 160×120 grey frames every ~200 ms
             2. On motion: detect people + faces with offline models
             3. Track each person; count when they cross the line (in/out)
             4. Crop each new face (≤100 KB JPEG)
                                              │  POST /api/camera/events (session + CSRF)
                                              ▼
             PHP: validates, saves crops to storage/camera/ (outside the web root),
                  writes rows to MariaDB; pages show live counts and hourly chart
```

- **Motion** needs no model: it is frame-by-frame pixel difference on a canvas.
- **Detection runs in the browser** from model files saved into the project (`public/assets/vendor/vision/`), downloaded once during setup and never fetched at runtime:
  - faces: MediaPipe Face Detector (BlazeFace short-range, about 1 MB) or face-api.js Tiny Face Detector (about 190 KB)
  - people: MediaPipe Object Detector (EfficientDet-Lite0, about 4–5 MB), "person" class only
  - recognition, only if C3 is approved: face-api.js face descriptors (about 6 MB) compared with opted-in residents' stored descriptors
- **Why not a Python/OpenCV service?** `CLAUDE.md` says the system has no Python service; it was removed in B6. Running in the browser keeps the stack unchanged. A real multi-camera install would move detection to a background process (e.g. Python + OpenCV with offline models). That is a stack change for the owner to approve (decision D4).

### Database (new migrations)

| Table | Purpose |
|---|---|
| `cameras` | `id, name, location` (e.g. "Front door phone"), so more phones can be added later. |
| `camera_events` | **Append-only.** `id, camera_id, event_type ENUM('motion_start','motion_end','person_in','person_out','face'), occurred_at, snapshot_path NULL, matched_user_id NULL, confidence NULL, created_at`. |
| `face_enrollments` | Only for C3. `user_id, descriptor, consent_given_at, consent_recorded_by, withdrawn_at NULL`. A resident can withdraw, and their descriptor is then deleted. |

People per hour is a query, not a stored figure: `SELECT DATE_FORMAT(occurred_at, '%Y-%m-%d %H:00') AS hour, SUM(event_type = 'person_in'), SUM(event_type = 'person_out') FROM camera_events GROUP BY hour`.

### Pages and access
- `/admin/camera`: live view, line-drawing tool, live counters. Admin and staff.
- `/admin/camera/stats`: people-per-hour chart (a charting library saved into the project, not a CDN) and a table for any date. Admin.
- `/admin/camera/faces`: face snapshot gallery with date filter and delete. **Admin only.** Snapshots are served through a permission check like receipts are today (`Uploads::serve()`), never as public files.
- Residents never see camera data.

## 4. Privacy and the law

Face images, and especially face-recognition data, are personal information under the **Data Privacy Act of 2012 (RA 10173)**. The **National Privacy Commission** has published guidance on CCTV use. Treat face data as highly sensitive and confirm the details with your adviser and the NPC's current guidance before turning faces on. At minimum:

- **Notice:** "CCTV in operation" signs at entrances, naming who to contact; the same notice in the house rules or lease.
- **Purpose:** safety and headcount only. Not for monitoring residents' private lives.
- **Never** point a camera into rooms, bathrooms or changing areas.
- **Consent for recognition (C3):** residents opt in and can withdraw at any time. Visitors and passers-by are never enrolled; their snapshots are only kept for the retention period.
- **Retention:** face snapshots deleted automatically after a set number of days (proposed **30**). Hourly counts can be kept, since they hold no faces.
- **Access:** admins only, every gallery view logged. No exporting or sharing of faces.
- **Security:** files outside the web root; existing login throttling and session timeout apply.

## 5. Build phases and how each is verified

| Phase | Scope | Verify with real output | Effort |
|---|---|---|---|
| **C0** | Owner decisions D1–D5 (§6); signage text | Decisions written into `PHASES.md` | S |
| **C1** | `cameras` + `camera_events`; Camera page; motion detection; line crossing; hourly chart. **No faces.** | Walk past the phone 10 times in each direction; counts within ±1 of a manual tally; chart shows the hour; the browser's network log shows requests only to `127.0.0.1`; staff and boarders get 403 where they should | M (2–3 days) |
| **C2** | Face detection, snapshot upload, admin gallery, retention purge | 5 people pass, at least 4 face snapshots saved; staff/boarder snapshot URLs return 404; the purge removes items older than the retention days | M |
| **C3** *(optional)* | Opt-in enrollment with consent record; resident vs other counts | 3 consenting residents enrolled; 20 test walks, false matches recorded and reported; withdrawing deletes the descriptor | L |
| **C4** *(optional)* | After-hours motion alert to staff (in-app notification only) | Motion at a test hour creates exactly one staff notification per event window | S |

Hardware for the demo: one spare phone, a tripod or wall mount, a USB cable or stable Wi-Fi, and a charger.

## 6. Decisions needed from the owner

- **D1:** Save face snapshots at all, or only counts? (Counts only is the low-risk option.)
- **D2:** Recognise opted-in residents (C3), or only count anonymous faces?
- **D3:** How many days to keep face snapshots (proposed 30)?
- **D4:** Browser-only detection for the demo (fits the current stack), or a background Python/OpenCV service later (changes `CLAUDE.md`'s stack rule)?
- **D5:** Who may view the face gallery: admins only (proposed), or staff too?
