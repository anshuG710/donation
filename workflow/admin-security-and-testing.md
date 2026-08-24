# DONATE+ Admin Dashboard — Security Review & Testing Checklist

Covers Phases 0–6 of the admin dashboard build.

---

## Security review

Every admin page follows the same protective pattern by construction:

**Authentication & authorization**
- Each admin page begins with `require_once "config/admin-guard.php"`, which starts the session, requires a logged-in user, and requires `role === 'admin'`. Non-admins are redirected to their own dashboard; logged-out users go to `login.html`.
- The two-admin policy is enforced in the UI: admins cannot be created or promoted anywhere, admin rows are read-only in user management, and an admin cannot act on their own row.

**CSRF protection**
- Every state-changing action is a POST form carrying a CSRF token (`csrf_field()`), verified server-side with `csrf_require()` before anything is written. A bad/missing token is rejected with a flash message.

**SQL injection**
- All queries use mysqli prepared statements with bound parameters. Dynamic filters build a `?` placeholder list with a matching types string — no user input is concatenated into SQL.

**Output escaping (XSS)**
- All dynamic output is escaped with `e()` / `htmlspecialchars()`. Confirmation dialogs escape names with `addslashes()`.

**Passwords**
- Stored as `password_hash()` output; verified with `password_verify()`. The setup script and any created accounts use the same hashing.

**Safe degradation**
- `column_exists()` / `table_exists()` guard the `is_active` and `activity_log` features so pages never break if a migration hasn't been run. Audit logging is best-effort and can never interrupt an action.

**Known limitations (acceptable for a local/student project; revisit before public hosting)**
- Sessions use PHP defaults; for production, set `session.cookie_httponly`, `cookie_secure` (HTTPS), and `SameSite`.
- No rate limiting on login. Consider adding attempt throttling before going public.
- `create-admin.php` must be deleted after first use (the script refuses to create a 3rd admin, but shouldn't remain on a live server).
- Change the default admin passwords from the setup script.

---

## Testing checklist

Log in as an admin, then walk through each page.

### Authentication
- [ ] Visiting `admin-dashboard.php` while logged out redirects to `login.html`.
- [ ] Logging in as a donor/recipient and visiting an `admin-*.php` page redirects away (no admin access).
- [ ] A disabled account (is_active = 0) cannot log in.

### Dashboard
- [ ] KPI counts match reality; "Pending Requests" links to the filtered requests list.

### Users (`admin-users.php`)
- [ ] Search by name and by email works; role filter works.
- [ ] "Make donor/recipient" flips the role.
- [ ] Disable then try logging in as that user → blocked. Enable → allowed again.
- [ ] Delete a test donor/recipient (with confirmation).
- [ ] Both admin rows show "Admin — locked"; your own row shows "You" and no action buttons.

### Donations (`admin-donations.php` / edit)
- [ ] Search + status + category filters work.
- [ ] View opens the public detail page.
- [ ] Edit changes save (try quantity + status).
- [ ] Take down sets status to cancelled; Restore sets it back to available.
- [ ] Delete removes the donation (and its requests) after confirmation.

### Requests (`admin-requests.php`)
- [ ] Search + status filter work; View expander shows purpose/message/contact.
- [ ] Approve / Reject / Mark completed / reopen — buttons match the current status and update it.

### Categories (`admin-categories.php`)
- [ ] Add a category; duplicate name is rejected.
- [ ] Rename a category.
- [ ] Delete a category → its donations become uncategorised (not deleted).
- [ ] Donation counts are correct.

### Reports (`admin-reports.php`)
- [ ] Monthly charts render; breakdown bars render with correct colors.
- [ ] Users / Donations / Requests CSV downloads open correctly in a spreadsheet.

### Activity Log (`admin-activity.php`)
- [ ] After doing a few actions above, they appear here (newest first) with the admin name, action, details, and timestamp.
- [ ] If the `activity_log` table is missing, a friendly banner appears instead of an error.

### Cross-cutting
- [ ] Every action shows a green success (or red error) banner afterward.
- [ ] Pagination works on any list with more than one page.
- [ ] Empty states show a friendly message when a list/filter has no results.
