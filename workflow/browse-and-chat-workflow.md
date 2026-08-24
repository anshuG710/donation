# DONATE+ — Browse Donations & Donor–Recipient Chat Workflow

Two improvements found during testing:

1. **Fix:** new users (donors) can donate but can't *see* available donations.
2. **Feature:** let a donor and recipient chat to coordinate location/timing.

Same stack as before: PHP (procedural), MySQL via mysqli prepared statements,
session auth, custom CSS, no framework. Same conventions (prepared statements,
`htmlspecialchars`/`e()` on output, POST + confirmation for actions).

---

## Phase A — Make available donations visible to donors (small, low risk)

**What's wrong now:** `find-donations.php` and `request-donation.php` only check
that a user is logged in — they already work for anyone. But the **donor
dashboard has no link to browse donations**, so donors never reach the page.
Recipients already have the link.

**Plan:**
1. Add a **"Browse Donations"** link to the donor dashboard sidebar
   (`donor-dashboard.php`) pointing to `find-donations.php`, matching the
   existing sidebar style.
2. Add a secondary **"Browse available donations"** button/card on the donor
   dashboard body so it's easy to find.
3. Confirm `find-donations.php` reads well for a donor (it lists available
   items; no role restriction needed).

**Decision to confirm (one choice):** should a **donor** also be able to
*request* an item, or only *view*?
- **Option 1 (simplest, recommended):** anyone logged in can view and request.
  No permission changes — just add the navigation. A person can both give and
  receive.
- **Option 2:** donors can view but not request; the "Request" button is hidden
  for donors and `request-donation.php` rejects non-recipients.

Recommendation: **Option 1** — least code, most flexible, matches how the pages
already behave.

**Done when:** a freshly registered donor can find and open the list of
available donations from their dashboard.

---

## Phase B — Donor–Recipient chat (optional; simple version, no real-time tech)

**Goal:** once a request exists, the recipient and the donor can exchange short
messages to arrange collection (location, timing, etc.) — without exposing phone
numbers publicly.

**Why it's not complex here:** a chat is just messages stored in a table and
shown newest-last. A donation *request* already links exactly one donor (via the
donation) and one recipient, so each request is a natural private thread. No
websockets needed — the page reloads on send, with an optional lightweight
auto-refresh.

**Plan:**
1. **Schema (added to `schema.sql`, idempotent):** new `messages` table —
   `id`, `request_id`, `sender_id`, `body`, `created_at`, indexed by
   `request_id`. Foreign keys to `donation_requests` and `users`
   (`ON DELETE CASCADE`).
2. **New page `messages.php?request_id=X`:**
   - Access control (important): the logged-in user must be **either** the
     recipient of that request **or** the donor of the donation it's for.
     Anyone else is turned away.
   - Shows the item + the other person's name at the top, the message thread
     below, and a text box to send (POST + prepared insert, `e()` on output).
   - After sending, redirect back to the thread (PRG) so a refresh doesn't
     resend.
3. **Entry points:** a **"Message"** link on each row of `donor-requests.php`
   (donor side) and `recipient-requests.php` (recipient side), opening the
   thread for that request.
4. **Optional niceties (only if wanted):**
   - A tiny JS poll (every ~5s) or a `<meta refresh>` so new messages appear
     without a manual reload. Start without it; add if you like.
   - An unread indicator.

**Scope guardrails (to keep it simple):** one thread per request; text only (no
attachments); no editing/deleting messages in v1; no notifications. These can be
added later.

**Done when:** from a request, both the donor and the recipient can open a
private thread and exchange messages; nobody else can read it.

---

## Suggested order

Phase A first (quick win, unblocks the tested issue). Then decide on Phase B —
build it if you want the chat, or stop after A.

## Files touched
- **Phase A:** `donor-dashboard.php` (add nav link + button). Possibly tiny
  guard tweak in `request-donation.php` only if you pick Option 2.
- **Phase B:** `schema.sql` (messages table), new `messages.php`,
  `donor-requests.php` + `recipient-requests.php` (add a "Message" link).

*Prepared as a plan only — no project files changed yet.*
