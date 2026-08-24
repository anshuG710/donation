# DONATE+ — Admin Dashboard Build Workflow

**Goal:** Turn the current view-only admin dashboard (`admin-dashboard.php`) into a full management console — so an admin can manage users, donations, requests, categories, and see real reporting.

**Stack (keep consistent with existing code):** PHP (procedural), MySQL via `mysqli` prepared statements, session-based auth, custom CSS (same green/orange DONATE+ theme), no framework.

**Guiding rules for every new page:**
- Reuse the login + `role === 'admin'` guard block already at the top of `admin-dashboard.php`.
- All queries use **prepared statements** (`$conn->prepare` + `bind_param`) — never string-concatenate user input.
- Escape all output with `htmlspecialchars()`.
- Any state-changing action (delete, approve, role change) must be a **POST** request and protected by a **CSRF token**.
- Keep the shared sidebar/layout consistent across admin pages.

---

## Phase 0 — Foundations (do first, everything else depends on it)

1. **Shared admin layout** — extract the sidebar + header + CSS from `admin-dashboard.php` into reusable includes:
   - `config/admin-guard.php` — session start, login check, admin-role check (single source of truth).
   - `includes/admin-header.php` and `includes/admin-footer.php` — sidebar, header, `<style>`.
   - Expand the sidebar links: Dashboard, Users, Donations, Requests, Categories, Reports.
2. **CSRF helper** — small `config/csrf.php` with `csrf_token()` and `csrf_verify()`; include on every form/action page.
3. **Reusable pieces** — a `render_status_badge()` helper and a simple pagination helper (used everywhere; current pages are hard-capped at 8 rows).
4. **Flash messages** — a tiny session-based "success/error" banner shown after actions.

**Done when:** an empty `users.php` can include the guard + header + footer and render inside the admin shell with a working sidebar.

---

## Phase 1 — User Management (highest value)

New page: `admin-users.php`

1. **List all users** with pagination + search (by name/email) and filter by role (donor / recipient / admin).
2. **View a single user** — profile detail + their donations and requests count.
3. **Change role** — switch a user between **donor and recipient only** (POST + CSRF).
   - **Admins are fixed at two accounts (you + your friend), seeded once via `create-admin.php`.** The UI must NOT be able to create new admins or promote anyone to admin.
   - Safety rules: an admin cannot change/delete their **own** admin account from the UI, and admin rows are read-only in the user table (no role dropdown, no delete) so the two-admin setup can never be broken.
4. **Deactivate / reactivate** account — requires a schema change (see below). Admin accounts excluded.
5. **Delete user** — POST + CSRF + confirmation. Applies to donors/recipients only. Note existing FKs cascade-delete their donations/requests.

**Admin policy (project decision):** exactly two admins, created once through the one-time `create-admin.php` bootstrap (which refuses to create a third), then that file is deleted. No public or in-dashboard admin creation.

**Schema change needed:** add an `is_active TINYINT(1) NOT NULL DEFAULT 1` column to `users` (write a migration `.sql`; also make login.php reject inactive users).

**Done when:** admin can search, view, change roles, activate/deactivate, and delete users safely.

---

## Phase 2 — Donation Management

New page: `admin-donations.php`

1. **List all donations** with pagination, search (title/donor), and filter by status + category.
2. **View full detail** of a donation (reuse/adapt `donation-details.php`).
3. **Moderate** — take down (set status to `cancelled`) or delete a listing (spam/inappropriate), POST + CSRF.
4. **Edit** — let admin correct title/category/quantity/status if needed.
5. Replace the admin sidebar's "All Donations" link (currently points to the public `find-donations.php`) with this admin view.

**Done when:** admin can find any donation and remove/edit/moderate it.

---

## Phase 3 — Request Management & Oversight

New page: `admin-requests.php`

1. **List all donation requests** with pagination + filter by status (pending / approved / rejected / completed).
2. **View request detail** — donation, recipient, quantity, purpose, collection date.
3. **Admin override** — approve / reject / mark completed (POST + CSRF), so admin has oversight beyond the donor-side flow in `donor-requests.php`.
4. Wire the dashboard's "Pending Requests" KPI to link straight into this filtered list.

**Done when:** admin can oversee and act on any request end-to-end.

---

## Phase 4 — Category Management

New page: `admin-categories.php`

1. **List categories** with a count of donations in each.
2. **Add / rename / delete** categories (POST + CSRF).
   - On delete, existing FK is `ON DELETE SET NULL`, so donations keep working (become "Other"/uncategorized).
3. Guard against deleting a category that would break dropdowns — or handle the null case gracefully in add/find pages.

**Done when:** categories are fully manageable from the UI, no DB editing required.

---

## Phase 5 — Reporting & Analytics

Enhance `admin-dashboard.php` + new `admin-reports.php`

1. **Trends over time** — donations and requests per month (simple charts; can use a lightweight JS chart lib or CSS bars).
2. **Breakdowns** — donations by category, requests by status, users by role.
3. **Date-range filter** on the dashboard KPIs.
4. **CSV export** — users, donations, requests (plain PHP `fputcsv`, no library).
5. Fix the KPI grid: it's set for 4 columns but renders 7 cards — tidy the layout.

**Done when:** admin sees trends + can export data.

---

## Phase 6 — Polish & Safety

1. **Audit log** (optional) — new `activity_log` table recording admin actions (who did what, when).
2. **Pagination everywhere** — confirm no page still hard-caps rows.
3. **Consistent empty states + flash messages** across all admin pages.
4. **Security pass** — verify every action page: POST-only, CSRF-checked, prepared statements, output escaped, admin-guarded.
5. **Testing checklist** — walk each feature as admin + confirm non-admins are blocked from every admin URL.

---

## Suggested build order
Phase 0 → 1 → 2 → 3 → 4 → 5 → 6.
Phases 1–4 each ship an independent, usable page, so we can build and test one at a time.

## New / changed files summary
- **New includes:** `config/admin-guard.php`, `config/csrf.php`, `includes/admin-header.php`, `includes/admin-footer.php`
- **New pages:** `admin-users.php`, `admin-donations.php`, `admin-requests.php`, `admin-categories.php`, `admin-reports.php`
- **Schema migrations:** `migrations/001_users_is_active.sql`, (optional) `migrations/002_activity_log.sql`
- **Changed:** `admin-dashboard.php` (new sidebar links, KPI grid fix, date filter), `login.php` (reject inactive users)

---

*Prepared as a plan only — no project files were changed. Ready to start on Phase 0 when you are.*
