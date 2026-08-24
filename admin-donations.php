<?php

/*
|--------------------------------------------------------------------------
| ADMIN — DONATION MANAGEMENT
|--------------------------------------------------------------------------
|
| List every donation with search + filters. Admins can:
|   - view the public detail page
|   - edit a donation (admin-donation-edit.php)
|   - take a listing down (status -> cancelled) or restore it
|   - delete a listing (also removes its requests via FK cascade)
|
*/

require_once __DIR__ . "/config/admin-guard.php";

$statuses = ["available", "requested", "completed", "cancelled"];


/*
|--------------------------------------------------------------------------
| HANDLE ACTIONS (POST)
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $return = $_POST["return"] ?? "admin-donations.php";
    if (!is_string($return) || strpos($return, "admin-donations.php") !== 0) {
        $return = "admin-donations.php";
    }

    csrf_require($return);

    $action = $_POST["action"] ?? "";
    $did    = (int) ($_POST["donation_id"] ?? 0);

    // Confirm the donation exists.
    $stmt = $conn->prepare("SELECT id, title FROM donations WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $did);
    $stmt->execute();
    $don = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$don) {
        set_flash("error", "That donation no longer exists.");
        header("Location: " . $return);
        exit();
    }

    $title = $don["title"];


    /* --- Change status (take down / restore / etc.) --- */

    if ($action === "set_status") {

        $new_status = $_POST["new_status"] ?? "";

        if (!in_array($new_status, $statuses, true)) {
            set_flash("error", "Invalid status.");
            header("Location: " . $return);
            exit();
        }

        $stmt = $conn->prepare("UPDATE donations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $did);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            log_activity($conn, $admin_id, "donation.status",
                "\"" . $title . "\" set to " . $new_status);
        }

        set_flash(
            $ok ? "success" : "error",
            $ok
                ? htmlspecialchars($title) . " is now marked " . $new_status . "."
                : "Could not update the donation."
        );

        header("Location: " . $return);
        exit();
    }


    /* --- Delete donation --- */

    if ($action === "delete_donation") {

        $stmt = $conn->prepare("DELETE FROM donations WHERE id = ?");
        $stmt->bind_param("i", $did);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            log_activity($conn, $admin_id, "donation.delete",
                "Deleted \"" . $title . "\"");
        }

        set_flash(
            $ok ? "success" : "error",
            $ok
                ? htmlspecialchars($title) . " was deleted."
                : "Could not delete the donation."
        );

        header("Location: " . $return);
        exit();
    }

    set_flash("error", "Unknown action.");
    header("Location: " . $return);
    exit();
}


/*
|--------------------------------------------------------------------------
| CATEGORIES (for the filter dropdown)
|--------------------------------------------------------------------------
*/

$categories = [];
$res = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $categories[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| LIST (GET) — filters
|--------------------------------------------------------------------------
*/

$search        = trim($_GET["q"] ?? "");
$status_filter = $_GET["status"] ?? "";
$cat_filter    = (int) ($_GET["category"] ?? 0);

if (!in_array($status_filter, $statuses, true)) {
    $status_filter = "";
}

$where  = [];
$types  = "";
$params = [];

if ($search !== "") {
    $where[]  = "(d.title LIKE ? OR u.name LIKE ?)";
    $like     = "%" . $search . "%";
    $types   .= "ss";
    $params[] = $like;
    $params[] = $like;
}

if ($status_filter !== "") {
    $where[]  = "d.status = ?";
    $types   .= "s";
    $params[] = $status_filter;
}

if ($cat_filter > 0) {
    $where[]  = "d.category_id = ?";
    $types   .= "i";
    $params[] = $cat_filter;
}

$where_sql = $where ? ("WHERE " . implode(" AND ", $where)) : "";


/* Count for pagination. */

$count_sql = "
    SELECT COUNT(*) AS total
    FROM donations d
    LEFT JOIN users u ON d.donor_id = u.id
    $where_sql
";
$stmt = $conn->prepare($count_sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0);
$stmt->close();

$meta = paginate($total, 15);


/* Fetch the page. */

$list_sql = "
    SELECT
        d.id, d.title, d.quantity, d.unit, d.status, d.created_at,
        u.name AS donor_name,
        c.name AS category_name
    FROM donations d
    LEFT JOIN users u      ON d.donor_id = u.id
    LEFT JOIN categories c ON d.category_id = c.id
    $where_sql
    ORDER BY d.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($list_sql);
$list_types  = $types . "ii";
$list_params = array_merge($params, [$meta["per_page"], $meta["offset"]]);
$stmt->bind_param($list_types, ...$list_params);
$stmt->execute();
$res = $stmt->get_result();

$donations = [];
while ($row = $res->fetch_assoc()) {
    $donations[] = $row;
}
$stmt->close();


$current_query = $_SERVER["QUERY_STRING"] ?? "";
$return_url    = "admin-donations.php" . ($current_query !== "" ? "?" . $current_query : "");

$filter_query = [];
if ($search !== "")        { $filter_query["q"] = $search; }
if ($status_filter !== "") { $filter_query["status"] = $status_filter; }
if ($cat_filter > 0)       { $filter_query["category"] = $cat_filter; }


/*
|--------------------------------------------------------------------------
| RENDER
|--------------------------------------------------------------------------
*/

$page_title = "Donations";
$active_nav = "donations";

require_once __DIR__ . "/includes/admin-header.php";
?>


<div class="top-section">
    <div>
        <div class="eyebrow">Administration</div>
        <h2 class="serif">Donation Management</h2>
    </div>
</div>


<!-- FILTERS -->
<div class="card">
    <form method="get" class="toolbar">
        <input
            type="text"
            name="q"
            value="<?= e($search) ?>"
            placeholder="Search item or donor">

        <select name="status">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= e($s) ?>" <?= $status_filter === $s ? "selected" : "" ?>>
                    <?= e(ucfirst($s)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="category">
            <option value="0">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c["id"] ?>" <?= $cat_filter === (int) $c["id"] ? "selected" : "" ?>>
                    <?= e($c["name"]) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-green btn-sm">Filter</button>

        <?php if ($search !== "" || $status_filter !== "" || $cat_filter > 0): ?>
            <a class="btn btn-ghost btn-sm" href="admin-donations.php">Clear</a>
        <?php endif; ?>
    </form>
</div>


<!-- DONATIONS TABLE -->
<div class="table">

    <div class="table-title">
        <span>All Donations</span>
        <span style="font-weight:400;color:#7a8179;font-size:12px"><?= $total ?> total</span>
    </div>

    <?php if (count($donations) === 0): ?>

        <div class="empty">
            <strong>No donations found</strong>
            <span>Try clearing the search or filters.</span>
        </div>

    <?php else: ?>

        <div class="table-scroll">
            <table>
                <tr>
                    <th>Item</th>
                    <th>Donor</th>
                    <th>Category</th>
                    <th>Qty</th>
                    <th>Status</th>
                    <th>Posted</th>
                    <th>Actions</th>
                </tr>

                <?php foreach ($donations as $d): ?>
                    <?php
                    $did    = (int) $d["id"];
                    $status = $d["status"];
                    $qty    = trim(($d["quantity"] ?? "0") . " " . ($d["unit"] ?? ""));
                    $posted = !empty($d["created_at"])
                        ? date("M d, Y", strtotime($d["created_at"]))
                        : "—";
                    $is_down = ($status === "cancelled");
                    ?>
                    <tr>
                        <td><?= e($d["title"] ?? "Donation") ?></td>
                        <td><?= e($d["donor_name"] ?? "Unknown") ?></td>
                        <td><?= e($d["category_name"] ?? "Other") ?></td>
                        <td><?= e($qty) ?></td>
                        <td><?= render_status_badge($status) ?></td>
                        <td><?= e($posted) ?></td>
                        <td>
                            <div class="actions-cell">

                                <a class="btn btn-ghost btn-sm"
                                   href="donation-details.php?id=<?= $did ?>"
                                   target="_blank" rel="noopener">View</a>

                                <a class="btn btn-ghost btn-sm"
                                   href="admin-donation-edit.php?id=<?= $did ?>">Edit</a>

                                <!-- Take down / Restore -->
                                <form method="post" class="inline-form">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="return" value="<?= e($return_url) ?>">
                                    <input type="hidden" name="action" value="set_status">
                                    <input type="hidden" name="donation_id" value="<?= $did ?>">
                                    <input type="hidden" name="new_status"
                                           value="<?= $is_down ? "available" : "cancelled" ?>">
                                    <button type="submit"
                                            class="btn btn-sm <?= $is_down ? "btn-green" : "btn-orange" ?>">
                                        <?= $is_down ? "Restore" : "Take down" ?>
                                    </button>
                                </form>

                                <!-- Delete -->
                                <form method="post" class="inline-form"
                                      onsubmit="return confirm('Delete &quot;<?= e(addslashes($d["title"] ?? "")) ?>&quot;? This also removes any requests for it. This cannot be undone.');">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="return" value="<?= e($return_url) ?>">
                                    <input type="hidden" name="action" value="delete_donation">
                                    <input type="hidden" name="donation_id" value="<?= $did ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>

                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

            </table>
        </div>

        <?php pagination_links($meta, $filter_query); ?>

    <?php endif; ?>

</div>


<?php require_once __DIR__ . "/includes/admin-footer.php"; ?>
