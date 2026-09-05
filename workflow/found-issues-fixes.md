# DONATE+ — Review Findings & Phased Fixes

Issues found in a review pass (same kinds as reported by testing). Grouped by
priority. Each phase is small, independent, and leaves the app working.

## High — correctness / data integrity
- **Phase 1 — Browse hides NULL-category donations.** find-donations.php uses
  INNER JOIN categories; a donation whose category was deleted (category_id
  NULL) disappears from Browse though it still exists. Fix: LEFT JOIN.
- **Phase 2 — A donor can request their own donation.** request-donation.php has
  no self-request guard. Fix: block when recipient_id == donor_id, and hide the
  Request button on your own item.
- **Phase 3 — "Available Quantity" shown to recipients is the original total,
  not what's left.** recipient-requests.php labels d.quantity as available.
  Fix: show live remaining.
- **Phase 4 — A donor can't fix or withdraw their own donation.** No donor-side
  cancel/edit exists. Fix: add a "Cancel / Withdraw" action on My Donations
  (sets status = cancelled; own listing only; not if completed).

## Medium — privacy / validation / UX
- **Phase 5 — Donor contact leaks too early.** recipient-requests.php shows the
  donor's email/phone for every request, incl. pending/rejected. Fix: reveal
  contact only after the request is approved.
- **Phase 6 — Collection date not validated as future.** request-donation.php
  only checks non-empty. Fix: require today-or-later + min on the date input.
- **Phase 7 — Login/registration errors use alert() popups and wipe the form.**
  Fix: inline errors that keep what was typed (like forgot-password.php).
- **Phase 8 — No pagination on Browse / My Donations / My Requests.** Fix: apply
  the existing paginate() helper.

## Low — consistency / hardening
- **Phase 9 — No CSRF on donor/recipient/request/add forms.** Admin pages have
  it; extend to these.
- **Phase 10 — Browse search uses string concatenation** (real_escape_string +
  LIKE) instead of prepared statements. Fix: parameterize.
- **Phase 11 — Raw DB errors echoed to the page** (die with $conn->error). Fix:
  generic message.
- **Phase 12 — Admin manual status vs auto-recompute can fight.** An admin
  "completed" can be reverted by the next request action. Note / guard only.

## Order
High (1-4) → Medium (5-8) → Low (9-12). Each phase ships independently.

*Prepared as a plan. Fixes built phase by phase.*
