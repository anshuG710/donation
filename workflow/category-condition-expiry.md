# DONATE+ — Category-Aware Condition & Food/Medical Expiry Date

## Problem found during testing
The **Condition** dropdown in `add-donation.php` is a fixed list — New, Like New,
Good, Used — shown for **every** category. These only fit durable goods. For
**Food & Groceries** they are meaningless ("Like New" food), and for **Medical
Supplies** "Used" is unsafe. Condition should depend on the chosen category.

Separately, **food (and medical) items have an expiry / best-before date**, but the
`donations` table has no place to store one, so it is never captured.

## Decisions (agreed)
- Expiry date applies to **Food & Groceries + Medical Supplies**.
- **"Near expiry" is dropped** as a condition — the real date handles that.
- For now we **store + display** the expiry date. Auto-hiding expired items is an
  optional later phase.

## Condition sets (per category)
| Category group | Categories | Conditions |
|---|---|---|
| Durable goods | Clothing, Books & Stationery, Furniture, Electronics, Toys & Games, Household Items | New, Like New, Good, Used |
| Food | Food & Groceries | Fresh, Packaged & Sealed, Home-cooked / Leftover |
| Medical | Medical Supplies | Sealed / Unopened, Unused |
| Fallback | Other, or no category | New, Usable, Used |

Categories that need an expiry date: **Food & Groceries, Medical Supplies**.

## Approach
Both features share one idea: **the form reacts to the selected category**. The
category-to-conditions (and needs-expiry) mapping lives in **one shared file** so the
add form, the admin edit page, and the server-side validation all read the same source
of truth and can never drift apart. UI show/hide is progressive enhancement (JS), but
the **server still validates** so bad data can't get in even if the form is bypassed.

---

## Phase 0 — One source of truth (foundation, no visible change)
New file `config/conditions.php` defining, per category name: its allowed condition
list and whether it needs an expiry date, plus a fallback set for unknown/blank
categories. Everything else includes this.
**Done when:** the mapping exists and can be included anywhere.

## Phase 1 — Schema change (expiry column)
Add `expiry_date DATE NULL` to `donations` as an idempotent `ALTER TABLE` in
`schema.sql`, matching the existing migration style. Additive and safe; existing rows
get NULL.
**Done when:** the column exists and re-running `schema.sql` is harmless.

## Phase 2 — Add-donation form becomes category-aware (UI)
`add-donation.php`: feed the Phase 0 mapping into the page. On category change,
JavaScript rebuilds the Condition dropdown to the right set and shows/hides an
"Expiry / Best-before date" field. The server renders a correct default so it still
behaves with JS off.
**Done when:** Food shows food conditions + an expiry field; Furniture shows the
durable set and no expiry field.

## Phase 3 — Server validation & save
`add-donation.php`, before saving: confirm the submitted condition is in the allowed
set for that category; if the category needs expiry, require it and enforce it is
today-or-later (otherwise force NULL). Include `expiry_date` in the INSERT.
**Done when:** a mismatched condition or a past/blank food expiry is rejected with a
clear message.

## Phase 4 — Same rules in the admin edit page
`admin-donation-edit.php` uses the identical mapping + validation so the admin edit
form behaves exactly like the add form.
**Done when:** admin edit matches the add form.

## Phase 5 — Show it where people look
Display the expiry date (and correct condition) on `donation-details.php`,
`find-donations.php` cards, and `my-donations.php` — e.g. "Best before: 15 Sep 2026".
**Done when:** recipients can see freshness before requesting.

## Phase 6 — (Optional, later) Auto-flag expired items
Treat food/medical past expiry as no longer available — hide from Browse or show an
"Expired" badge.
**Done when:** expired items no longer show as requestable.

---

## Files touched
- **New:** `config/conditions.php`
- **Schema:** `schema.sql` (add `expiry_date` column)
- **Changed:** `add-donation.php`, `admin-donation-edit.php`
- **Display only:** `donation-details.php`, `find-donations.php`, `my-donations.php`

## Suggested order
0 → 1 → 2 → 3 → 4 → 5, then decide on 6. Each of Phases 1–5 leaves the app working.

No existing data needs migrating — old rows keep their values and the admin can
correct them.

*Prepared as a plan only — no project files changed yet.*
