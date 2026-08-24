<?php

/*
|--------------------------------------------------------------------------
| ADMIN — USER MANAGEMENT
|--------------------------------------------------------------------------
|
| List, search and filter users. Admins can:
|   - switch a user between donor and recipient
|   - enable / disable an account (is_active)
|   - delete a donor/recipient
|
| Admin accounts are READ ONLY here — the project has exactly two
| admins, seeded once via create-admin.php. There is deliberately no
| way to create an admin or promote anyone to admin, and admin rows
| cannot be edited, disabled or deleted from this screen. An admin
| also cannot act on their own account.
|
*/

require_once __DIR__ . "/config/admin-guard.php";

$has_active = column_exists($conn, "users", "is_active");


/*
|--------------------------------------------------------------------------
| HANDLE ACTIONS (POST)
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Where to send the user back to (preserves filters/paging).
    $return = $_POST["return"] ?? "admin-users.php";
    if (!is_string($return) || strpos($return, "admin-users.php") !== 0) {
        $return = "admin-users.php";
    }

    csrf_require($return);

    $action    = $_POST["action"] ?? "";
    $target_id = (int) ($_POST["user_id"] ?? 0);

    // Load the target user.
    $stmt = $conn->prepare("SELECT id, name, role FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $target_id);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$target) {
        set_flash("error", "That user no longer exists.");
        header("Location: " . $return);
        exit();
    }

    // Never allow acting on an admin or on yourself.
    if ($target["role"] === "admin") {
        set_flash("error", "Admin accounts cannot be modified here.");
        header("Location: " . $return);
        exit();
    }

    if ((int) $target["id"] === $admin_id) {
        set_flash("error", "You cannot modify your own account here.");
        header("Location: " . $return);
        exit();
    }

    $target_name = $target["name"];


    /* --- Change role: donor <-> recipient only --- */

    if ($action === "change_role") {

        $new_role = $_POST["new_role"] ?? "";

        if (!in_array($new_role, ["donor", "recipient"], true)) {
            set_flash("error", "Invalid role.");
            header("Location: " . $return);
            exit();
        }

        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $new_role, $target_id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            log_activity($conn, $admin_id, "user.role",
                $target_name . " set to " . $new_role);
        }

        set_flash(
            $ok ? "success" : "error",
            $ok
                ? htmlspecialchars($target_name) . " is now a " . $new_role . "."
                : "Could not update the role."
        );

        header("Location: " . $return);
        exit();
    }


    /* --- Enable / disable account --- */

    if ($action === "toggle_active" && $has_active) {

        $make_active = (int) ($_POST["make_active"] ?? 0) === 1 ? 1 : 0;

        $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $make_active, $target_id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            log_activity($conn, $admin_id, "user.active",
                $target_name . ($make_active ? " enabled" : " disabled"));
        }

        set_flash(
            $ok ? "success" : "error",
            $ok
                ? htmlspecialchars($target_name)
                    . ($make_active ? " has been re-enabled." : " has been disabled.")
                : "Could not update the account."
        );

        header("Location: " . $return);
        exit();
    }


    /* --- Delete user --- */

    if ($action === "delete_user") {

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $target_id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            log_activity($conn, $admin_id, "user.delete",
                "Deleted user " . $target_name);
        }

        set_flash(
            $ok ? "success" : "error",
            $ok
                ? htmlspecialchars($target_name) . " and their records were deleted."
                : "Could not delete the user."
        );

        header("Location: " . $return);
        exit();
    }

    // Unknown action.
    set_flash("error", "Unknown action.");
    header("Location: " . $return);
    exit();
}


/*
|--------------------------------------------------------------------------
| LIST (GET) — filters
|--------------------------------------------------------------------------
*/

$search      = trim($_GET["q"] ?? "");
$role_filter = $_GET["role"] ?? "";

if (!in_array($role_filter, ["donor", "recipient", "admin"], true)) {
    $role_filter = "";
}

// Build WHERE clause + bound params.
$where  = [];
$types  = "";
$params = [];

if ($search !== "") {
    $where[]  = "(name LIKE ? OR email LIKE ?)";
    $like     = "%" . $search . "%";
    $types   .= "ss";
    $params[] = $like;
    $params[] = $like;
}

if ($role_filter !== "") {
    $where[]  = "role = ?";
    $types   .= "s";
    $params[] = $role_filter;
}

$where_sql = $where ? ("WHERE " . implode(" AND ", $where)) : "";


/*
| Count for pagination.
*/

$total = 0;

$count_sql = "SELECT COUNT(*) AS total FROM users " . $where_sql;
$stmt = $conn->prepare($count_sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0);
$stmt->close();

$meta = paginate($total, 15);


/*
| Fetch the page of users.
*/

$active_col = $has_active ? "is_active" : "1 AS is_active";

$list_sql = "
    SELECT id, name, email, phone, role, $active_col, created_at
    FROM users
    $where_sql
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($list_sql);

$list_types  = $types . "ii";
$list_params = array_merge($params, [$meta["per_page"], $meta["offset"]]);
$stmt->bind_param($list_types, ...$list_params);
$stmt->execute();
$res = $stmt->get_result();

$users = [];
while ($row = $res->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();


// Current query string (for the return field + preserving filters).
$current_query = $_SERVER["QUERY_STRING"] ?? "";
$return_url    = "admin-users.php" . ($current_query !== "" ? "?" . $current_query : "");

$filter_query = [];
if ($search !== "")      { $filter_query["q"] = $search; }
if ($role_filter !== "") { $filter_query["role"] = $role_filter; }


/*
|--------------------------------------------------------------------------
| RENDER
|--------------------------------------------------------------------------
*/

$page_title = "Users";
$active_nav = "users";

require_once __DIR__ . "/includes/admin-header.php";
?>


<div class="top-section">
    <div>
        <div class="eyebrow">Administration</div>
        <h2 class="serif">User Management</h2>
    </div>
</div>


<?php if (!$has_active): ?>
    <div class="flash flash-error">
        The <b>is_active</b> column is missing, so enable/disable is turned off.
        Run <code>migrations/001_users_is_active.sql</code> to activate it.
    </div>
<?php endif; ?>


<!-- FILTERS -->
<div class="card">
    <form method="get" class="toolbar">
        <input
            type="text"
            name="q"
            value="<?= e($search) ?>"
            placeholder="Search name or email">

        <select name="role">
            <option value="">All roles</option>
            <option value="donor"     <?= $role_filter === "donor" ? "selected" : "" ?>>Donors</option>
            <option value="recipient" <?= $role_filter === "recipient" ? "selected" : "" ?>>Recipients</option>
            <option value="admin"     <?= $role_filter === "admin" ? "selected" : "" ?>>Admins</option>
        </select>

        <button type="submit" class="btn btn-green btn-sm">Filter</button>

        <?php if ($search !== "" || $role_filter !== ""): ?>
            <a class="btn btn-ghost btn-sm" href="admin-users.php">Clear</a>
        <?php endif; ?>
    </form>
</div>


<!-- USERS TABLE -->
<div class="table">

    <div class="table-title">
        <span>All Users</span>
        <span style="font-weight:400;color:#7a8179;font-size:12px">
            <?= $total ?> total
        </span>
    </div>

    <?php if (count($users) === 0): ?>

        <div class="empty">
            <strong>No users found</strong>
            <span>Try clearing the search or filters.</span>
        </div>

    <?php else: ?>

        <div class="table-scroll">
            <table>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>

                <?php foreach ($users as $u): ?>
                    <?php
                    $uid       = (int) $u["id"];
                    $u_role    = $u["role"];
                    $is_admin  = ($u_role === "admin");
                    $is_self   = ($uid === $admin_id);
                    $is_active = (int) $u["is_active"] === 1;

                    $joined = !empty($u["created_at"])
                        ? date("M d, Y", strtotime($u["created_at"]))
                        : "—";

                    // Locked = can't be acted on (admins + your own row).
                    $locked = $is_admin || $is_self;
                    ?>
                    <tr>
                        <td>
                            <?= e($u["name"]) ?>
                            <?php if ($is_self): ?>
                                <span class="status neutral">You</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($u["email"]) ?></td>
                        <td><?= e($u["phone"] !== "" ? $u["phone"] : "—") ?></td>
                        <td><?= render_status_badge($u_role) ?></td>
                        <td>
                            <?= render_status_badge($is_active ? "active" : "inactive") ?>
                        </td>
                        <td><?= e($joined) ?></td>
                        <td>
                            <?php if ($locked): ?>
                                <span style="color:#9aa29a;font-size:11px">
                                    <?= $is_admin ? "Admin — locked" : "—" ?>
                                </span>
                            <?php else: ?>
                                <div class="actions-cell">

                                    <!-- Switch role -->
                                    <form method="post" class="inline-form">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="return" value="<?= e($return_url) ?>">
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                        <input type="hidden" name="new_role"
                                               value="<?= $u_role === "donor" ? "recipient" : "donor" ?>">
                                        <button type="submit" class="btn btn-ghost btn-sm"
                                                title="Switch to <?= $u_role === "donor" ? "recipient" : "donor" ?>">
                                            Make <?= $u_role === "donor" ? "recipient" : "donor" ?>
                                        </button>
                                    </form>

                                    <!-- Enable / disable -->
                                    <?php if ($has_active): ?>
                                        <form method="post" class="inline-form">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="return" value="<?= e($return_url) ?>">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="make_active" value="<?= $is_active ? 0 : 1 ?>">
                                            <button type="submit"
                                                    class="btn btn-sm <?= $is_active ? "btn-ghost" : "btn-green" ?>">
                                                <?= $is_active ? "Disable" : "Enable" ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Delete -->
                                    <form method="post" class="inline-form"
                                          onsubmit="return confirm('Delete <?= e(addslashes($u["name"])) ?>? This also removes their donations and requests. This cannot be undone.');">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="return" value="<?= e($return_url) ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>

                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

            </table>
        </div>

        <?php pagination_links($meta, $filter_query); ?>

    <?php endif; ?>

</div>


<?php require_once __DIR__ . "/includes/admin-footer.php"; ?>
