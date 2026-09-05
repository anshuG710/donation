# DONATE+ — Campaigns (admin-run donation drives)

## Goal
Let admins run themed donation drives (e.g. a flood-relief campaign, or a
clothes-collection collab with a partner organization). Campaigns are DATA:
the admin creates, starts and ends them from a UI — no code changes to run one.
Only admins control the lifecycle; donors/recipients see and contribute.

## Key decision — "where do donors send items?"
Each campaign carries a **partner organization** (optional) and one or more
**drop-off / collection points** (label, address, city, contact phone, hours,
optional map link), plus a **most-needed items** note. The public campaign page
shows these so a donor knows exactly where and what to bring.
- Flood relief → partner = relief org; several collection centers; needed =
  blankets, dry food, clothes.
- Clothes collab → partner = the org; one drop-off point (their office).
Pickup-from-home is a later phase; drop-off points first.

## Data model (Phase 1)
- `campaigns`: id, title, description, image, goal_quantity (target items),
  category_id, partner_org, partner_contact, needed_items, status
  (active/ended/draft), start_date, end_date, created_by, created_at.
- `campaign_points`: id, campaign_id (FK, cascade), label, address, city,
  contact_phone, hours, map_url.
- `donations.campaign_id` (nullable) — tags a donation to a campaign.
Progress = SUM(quantity) of non-cancelled donations tagged to the campaign,
against goal_quantity.

## Phases
1. **Schema** — the two tables + `campaign_id` column (idempotent migration).
2. **Admin management** — `admin-campaigns.php`: list, create (with drop-off
   points, image, goal, partner, dates), **Start / End**, edit, delete. Admin
   guard + CSRF. Sidebar "Campaigns" link.
3. **Public page** — `campaigns.php`: active campaigns with progress bars,
   drop-off points, needed items; a detail view.
4. **Hero** — a JSON endpoint (like stats.php) + AJAX fills the empty landing
   hero with the current featured campaign (progress bar); graceful fallback
   when none is active.
5. **Donor tagging** — optional "Contribute to a campaign" dropdown on Add
   Donation; stores campaign_id.
6. **Polish (later)** — pickup requests, image handling, ended badges.

## Defaults
Goal = number of items · multiple drop-off points · drop-off first (pickup
later) · partner organization named per campaign.

*Prepared as a plan. Build proceeds phase by phase; DB migration must be run
once in phpMyAdmin.*
