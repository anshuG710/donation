# DONATE+ — Partial Fulfilment & Quantity Tracking

## Goal
When a donor donates e.g. **5 pcs** and a recipient requests **2 pcs**, then after the
donor (or admin) **approves** it:
- the donation shows **3 left**,
- once the remaining reaches **0**, the donation is **auto-marked "completed"**,
  disappears from **Find Donations**, and appears as **Completed** in **My Donations**
  with a "thank you for donating" gesture.

Admin controls stay exactly as they are (admin can still approve requests and edit
status); admin approvals follow the same quantity rules so data stays consistent.

## Approach (no database change)
- `donations.quantity` stays the **original total**.
- **claimed** = SUM of `quantity` from that donation's requests with status
  `approved` or `completed`.
- **remaining** = max(0, total − claimed) — always calculated live, so it's never wrong.
- After any approval/rejection, **recompute** the donation's status:
  - if claimed ≥ total → `completed`
  - else → `available`
  - (never touch a donation the admin set to `cancelled`).
- **Guard:** a request can only be approved if its quantity ≤ current remaining
  (prevents over-allocating more than exists).

## Shared helpers (config/helpers.php)
- `donation_claimed_qty($conn, $id)` — sum of approved+completed request quantities.
- `donation_remaining($conn, $id, $total=null)` — total − claimed (min 0).
- `recompute_donation_status($conn, $id)` — sets available/completed from claimed
  (leaves `cancelled` alone).

## File changes
1. **config/helpers.php** — add the three helpers above.
2. **donor-requests.php** — on approve: guard against over-allocation, then
   `recompute_donation_status`; on reject: recompute (frees the quantity back).
   Show a small success/error notice.
3. **admin-requests.php** — same quantity guard + recompute on the admin's
   approve/reject/complete/reopen actions.
4. **request-donation.php** — "Available quantity" now shows **remaining**, and the
   request is validated against remaining (not the original total).
5. **find-donations.php** — each card shows **"X left"**. (Already only lists
   `available`, so completed ones are hidden automatically.)
6. **donation-details.php** — shows **remaining of total**; the "Request" button
   only appears while remaining > 0.
7. **my-donations.php** — Quantity column shows **remaining / total**, each row shows
   the **date donated**, and completed rows show a green **"Thank you for donating"**
   note.

## Test flow
1. Donor posts a donation of 5 pcs.
2. Recipient A requests 2 → donor approves → donation shows **3 left**, still available.
3. Recipient B requests 3 → donor approves → remaining hits **0** → donation becomes
   **completed**, disappears from Find Donations, shows **Completed + thank-you** in
   My Donations and on the donor dashboard.
4. Try to approve a request bigger than what's left → blocked with a message.
