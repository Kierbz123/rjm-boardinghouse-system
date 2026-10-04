# RJM Assistant — master plan

Turn the "Ask AI" chat into an assistant that **knows who is asking, only answers with what that person is allowed to see, takes them straight to the page they need, and still works when the AI model is off.**

Status: proposal, not built. Nothing here changes the system until approved.

---

## 1. Where we are today

| Today | Problem |
|---|---|
| Floating "Ask AI" chat calls a local Ollama model (llama3.2:3b) | If Ollama isn't running, the assistant is useless |
| Chat knows the boarder's room, balance and penalties; staff/admin get almost nothing | Not role-aware for staff and admin |
| Answers are plain text | Can't take you anywhere or do anything |
| Separate AI buttons: improve description, suggest category (boarder), analyze ticket, summarize queue (staff) | Useful, but hidden and unconnected to the chat |
| The model "knows" rules only from one long prompt | It can invent rules, fees or pages that don't exist |

## 2. What the new assistant does

1. **Navigate:** "where do I pay rent?", "open the maintenance queue", "show flagged payments" → answer card with a **Go** button to the exact page (only pages that role can open).
2. **Look up the user's own facts:** "how much do I owe?", "is my sink repair fixed?", "how many SOS alerts are active?", "which payments need review?" — read straight from the database, never guessed by the model.
3. **Explain the house and the system:** curfew, billing rules, how to report a repair — answered from a written help library, with the source shown.
4. **Prepare, never submit:** "report that my faucet is leaking" → opens *Report a Repair* with the description and category filled in; the person reviews and presses Submit themselves.
5. **Work without the AI model:** navigation, lookups and the help library run on plain PHP rules; Ollama only adds free-form conversation on top.

## 3. How it works (architecture)

```
User message ──► Intent router (PHP rules, instant, no AI)
                   │  matched? ──► Tool (role-checked) ──► Answer card {text, actions, sources}
                   │
                   └─ not sure ──► Ollama picks a tool from the user's allowed list (JSON only)
                                     └─► Tool runs in PHP (role-checked again) ──► Ollama phrases the answer
                                                                                    from the tool result only
```

- **One page registry** (`src/Support/NavRegistry.php`): every page with its label, plain-language synonyms ("pay", "bayad", "rent", "receipt"), the roles allowed, and a one-line description. The sidebar (`nav.php`) and the assistant both read it, so they can never disagree.
- **Tools** are small PHP functions in `src/Services/Assistant/`, each declaring which roles may use it. The server, not the model, decides what a person can see; a tool only ever reads the logged-in user's own data unless the role allows more.
- **Help library**: short Markdown articles in `docs/help/` (billing, curfew, repairs, SOS, payments, account). Matched by keywords; the answer quotes the article and links "Read more".
- **One API**: `POST /api/assistant/ask` → `{ reply, actions: [{label, href}], sources: [...], followUps: [...] }`. The existing AI endpoints stay and get folded in.
- **Model output is never trusted for permissions or links**: the model may only name a tool or a page key from the list it was given; the server validates both before anything is shown.

## 4. What each role gets

| Role | Navigate to | Look up | Prepare (pre-filled, user submits) |
|---|---|---|---|
| **Boarder** | Dashboard, Pay rent, Report a repair, Incidents, Notifications, Profile | My balance and what it's made of, unpaid months, my payment statuses, my repair status, my incidents, my room and bed, due date and late-fee rules | Repair report (category + description), payment (month + amount owed), incident report, password change page |
| **Staff** | Dashboard, Maintenance queue/history, Incidents/history, Inquiry Center, Notifications, Profile | Open repairs by priority, oldest open repair, active SOS alerts, unresolved incidents, a ticket's details | Mark a repair "in progress" (confirm button), log an incident, log a walk-in inquiry |
| **Admin** | Everything above plus Boarders, a specific boarder's profile, Rooms & Beds, Payments, Expenses, Penalties, Occupancy, Staff accounts, Ledger export | Payments awaiting review, who owes the most, vacant beds, occupancy, today's inquiries, a boarder's balance by name, expenses this month | Open a boarder's profile by name, run the late-fee check (confirm), export the ledger for a date range |

Every "prepare" action ends on the normal page with the normal form and the normal security checks; the assistant never submits a payment, penalty or status change on its own.

## 5. Accessibility and ease of use

- **Command palette:** `Ctrl + K` (or the assistant button) opens a search box that matches pages and actions as you type — no AI needed, instant, fully keyboard driven.
- **Accessible dialog:** proper dialog role, focus kept inside while open, `Esc` closes, focus returns to where you were; replies announced to screen readers (`aria-live`); every action is a real link or button.
- **Plain language:** short answers first, details on request; no jargon ("you owe ₱3,274.19 for October", not "outstanding balance per allocation").
- **Context-aware suggestions:** the assistant offers 3–4 starter questions that fit the role and the page you're on (on *Pay Rent*: "How much should I pay?", "When is rent due?").
- **Filipino/English:** the router understands common Tagalog/Bisaya words (bayad, utang, sira, tulo, kuryente); the model can answer in the language used.
- **Text size, contrast and reduced motion** follow the design system; answer cards work at 200% zoom and on phones.
- **Voice input is not planned:** browser speech recognition sends audio to cloud services, which breaks the localhost-only rule.

## 6. Safety, privacy and reliability

- Role checks inside every tool (same rules as the page routes) — tested so a boarder can never get another boarder's data or an admin page, however the question is worded.
- Prompt-injection guard: text from the database (repair descriptions, notes) is passed to the model only as quoted data, never as instructions.
- No silent actions: anything that changes data needs the person to press a button on the real page.
- Keeps the existing limits (10 AI requests/minute, 4,000 characters) and adds a timeout fallback: if the model is slow, the router's answer is shown immediately.
- Nothing is logged: no record of questions, intents or pages opened (owner decision).
- Conversation memory: last few turns kept in the user's session only, cleared on logout.

## 7. Build phases

| Phase | Delivers | Needs Ollama? | Effort |
|---|---|---|---|
| **A0 Foundations** | Page registry shared with the sidebar; `/api/assistant/ask` returning answer cards; tests | No | M |
| **A1 Navigate + command palette** | "Take me to…" for every role; `Ctrl+K` palette; Go buttons | No | M |
| **A2 Role lookups** | Balance, payments, repairs, SOS, occupancy… as answer cards with links | No | M |
| **A3 Help library** | `docs/help/*.md` articles; answers with source and "Read more" | No | S |
| **A4 AI layer** | Ollama picks tools (JSON-only output) for questions the router can't place; phrases answers from tool results only; follow-up questions | Yes | L |
| **A5 Prepare actions** | Pre-filled repair/payment/incident forms (no confirm-button actions, see decision 3) | No | M |
| **A6 Accessibility & language** | Dialog/focus polish, starter questions per page, Tagalog/Bisaya keywords | No | S |
| **A7 Evaluation** | ~60 test questions per role with expected page/tool/permission, plus "try to break it" questions; offline test with Ollama stopped | Optional | M |

A0–A3 alone already give a fast, reliable assistant that navigates and answers real questions without any AI model. A4 adds natural conversation on top.

## 8. How we'll know it works

- ≥ 90% of the test questions land on the right page or tool for each role.
- 0 permission leaks in the "try to break it" set.
- Router answers in under 0.1 s; AI answers in under ~8 s on the current machine.
- With Ollama stopped: navigation, lookups and help still work; the chat says AI conversation is offline.

## Progress

| Phase | Status | Notes |
|---|---|---|
| A0 Foundations | Done (3 Oct 2026) | `src/Support/NavRegistry.php` feeds the sidebar and the assistant; `POST /api/assistant/ask` returns answer cards; the old `/api/assistant/chat` route was folded into it. |
| A1 Navigate + palette | Done (3 Oct 2026) | `Ctrl+K` opens the drawer; live page matches while typing; one "Open …" button per answer; English/Tagalog switch. Works with Ollama stopped. |
| A2 Role lookups | Done (3 Oct 2026) | Boarder: balance, payment status, repair status, room. Staff: open repairs, SOS, incidents, inquiries. Admin: payments waiting, vacant beds, occupancy, who owes, expenses this month, a boarder by name. Figures come from the same models the pages use. |
| A5 Prepare actions | Done (4 Oct 2026) | A described problem opens Report a Repair or the incident form already filled in (description plus a guessed category/type); the person reviews and submits. Admin gets a ledger download link (this month or last month). Pay Rent needs no pre-fill: the page already works out the amount. |
| A3, A4, A6, A7 | Not started | Help library, AI tool-picking, accessibility pass, evaluation set. |

Differences from the plan as written: the rules live in one file, `src/Services/AssistantService.php`, until there are enough tools to split; the palette is the same drawer rather than a second search box; the drawer does not trap focus because it is a side panel, not a blocking dialog (`Esc` closes it and returns focus).

## 9. Owner decisions (3 October 2026)

1. **Go button, not automatic jump.** The assistant shows the page as a button; the person presses it.
2. **Language is the user's choice.** A language switch in the assistant (English / Tagalog), remembered per browser. The router understands Tagalog and Bisaya keywords in either setting; the switch sets the language of the replies.
3. **Navigation only (confirmed 4 October 2026).** The assistant never changes data, for any role; it opens the page, with the form filled in where that helps, and the person submits it there.
4. **Model: keep llama3.2:3b.** Measured on this PC (Ryzen 5 6600H, 15.3 GB RAM, RTX 2050 4 GB): loads at 2.9 GB, 80% on the GPU, picks a tool in 0.6–1.3 s. qwen2.5:3b is the same size and would run equally well, but needs a 1.9 GB download and its 3b build has a research-only licence. Revisit only if llama3.2 misses the 90% target in A7.
5. **No activity log.** No `assistant_log` table, no migration.
