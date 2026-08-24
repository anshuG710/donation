<?php

/*
|--------------------------------------------------------------------------
| ADMIN — REQUEST MANAGEMENT / OVERSIGHT
|--------------------------------------------------------------------------
|
| List every donation request across the platform. Admins can override
| the status regardless of which donor owns the donation:
|   pending   -> approved / rejected
|   approved  -> completed / rejected
|   rejected  -> approved (reopen)
|   completed -> approved (reopen)
|
| Matches the donor-side behaviour (donor-requests.php): changing a
| request status only updates the request row; it does not alter the
| donation itself.
|
*/

require_once __DIR__ . "/config/admin-guard.php";

$statuses = ["pending", "approved", "rejected", "completed"];

// Which target statuses are allowed from a given current status.
$allowed_transitions = [
    "pending"   => ["approved", "rejected"],
    "approved"  => ["completed", "rejected"],
    "rejected"  => ["approved"],
    "completed" => ["approved"],
];

// Button label + style per target status.
$action_meta = [
    "approved"  => ["label" => "Approve",       "class" => "btn-green"],
    "rejected"  => ["label" => "Reject",        "class" => "btn-danger"],
    "completed" => ["label" => "Mark completed", "class" => "btn-green"],
];


/*
|--------------------------------------------------------------------------
| HANDLE ACTIONS (POST)
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $return = $_POST["return"] ?? "admin-requests.php";
    if (!is_string($return) || strpos($return, "admin-requests.php") !== 0) {
        $return = "admin-requests.php";
    }

    csrf_require($return);

    $rid        = (int) ($_POST["request_id"] ?? 0);
    $new_status = $_POST["new_status"] ?? "";

    // Load current status + the donation it belongs to (for quantity checks).
    $stmt = $conn->prepare("
        SELECT dr.id, dr.status, dr.donation_id, dr.quantity,
               d.quantity AS total_qty
        FROM donation_requests dr
        INNER JOIN donations d ON dr.donation_id = d.id
        WHERE dr.id = ? LIMIT 1
    ");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$req) {
        set_flash("error", "That request no longer exists.");
        header("Location: " . $return);
        exit();
    }

    $current = $req["status"];
    $valid   = $allowed_transitions[$current] ?? [];

    if (!in_array($new_status, $valid, true)) {
        set_flash("error", "That status change isn't allowed from '" . $current . "'.");
        header("Location: " . $return);
        exit();
    }

    // Guard: moving INTO approved/completed from a non-counted state must not
    // exceed what's left of the donation.
    $counted_before = in_array($current, ["approved", "completed"], true);
    $counted_after  = in_array($new_status, ["approved", "completed"], true);

    if (!$counted_before && $counted_after) {
        $remaining = donation_remaining($conn, (int) $req["donation_id"], (int) $req["total_qty"]);
        if ((int) $req["quantity"] > $remaining) {
            set_flash("error",
                "Cannot approve: only " . $remaining . " left, but this request is for "
                . (int) $req["quantity"] . ".");
            header("Location: " . $return);
            exit();
        }
    }

    $stmt = $conn->prepare("UPDATE donation_requests SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $rid);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        // Keep the donation's own status/quantity in sync.
        recompute_donation_status($conn, (int) $req["donation_id"]);

        log_activity($conn, $admin_id, "request.status",
            "Request #" . $rid . " (" . $current . " -> " . $new_status . ")");
    }

    set_flash(
        $ok ? "success" : "error",
        $ok ? "Request marked " . $new_status . "." : "Could not update the request."
    );

    header("Location: " . $return);
    exit();
}


/*
|--------------------------------------------------------------------------
| LIST (GET) — filters
|--------------------------------------------------------------------------
*/

$search        = trim($_GET["q"] ?? "");
$status_filter = $_GET["status"] ?? "";

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
    $where[]  = "dr.status = ?";
    $types   .= "s";
    $params[] = $status_filter;
}

$where_sql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$join_sql = "
    FROM donation_requests dr
    INNER JOIN donations d   ON dr.donation_id = d.id
    LEFT JOIN users u        ON dr.recipient_id = u.id
    LEFT JOIN users donr     ON d.donor_id = donr.id
";


/* Count. */

$count_sql = "SELECT COUNT(*) AS total $join_sql $where_sql";
$stmt = $conn->prepare($count_sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0);
$stmt->close();

$meta = paginate($total, 12);


/* Fetch page. */

$list_sql = "
    SELECT
        dr.id, dr.quantity, dr.beneficiaries, dr.purpose, dr.message,
        dr.collection_date, dr.status, dr.created_at,
        d.title AS donation_title,
        u.name  AS recipient_name,
        u.email AS recipient_email,
        u.phone AS recipient_phone,
        donr.name AS donor_name
    $join_sql
    $where_sql
    ORDER BY dr.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($list_sql);
$list_types  = $types . "ii";
$list_params = array_merge($params, [$meta["per_page"], $meta["offset"]]);
$stmt->bind_param($list_types, ...$list_params);
$stmt->execute();
$res = $stmt->get_result();

$requests = [];
while ($row = $res->fetch_assoc()) {
    $requests[] = $row;
}
$stmt->close();


$current_query = $_SERVER["QUERY_STRING"] ?? "";
$return_url    = "admin-requests.php" . ($current_query !== "" ? "?" . $current_query : "");

$filter_query = [];
if ($search !== "")        { $filter_query["q"] = $search; }
if ($status_filter !== "") { $filter_query["status"] = $status_filter; }


/*
|--------------------------------------------------------------------------
| RENDER
|--------------------------------------------------------------------------
*/

$page_title = "Requests";
$active_nav = "requests";

require_once __DIR__ . "/includes/admin-header.php";
?>


<div class="top-section">
    <div>
        <div class="eyebrow">Administration</div>
        <h2 class="serif">Request Management</h2>
    </div>
</div>


<!-- FILTERS -->
<div class="card">
    <form method="get" class="toolbar">
        <input type="text" name="q" value="<?= e($search) ?>"
               placeholder="Search item or recipient">

        <select name="status">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= e($s) ?>" <?= $status_filter === $s ? "selected" : "" ?>>
                    <?= e(ucfirst($s)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-green btn-sm">Filter</button>

        <?php if ($search !== "" || $status_filter !== ""): ?>
            <a class="btn btn-ghost btn-sm" href="admin-requests.php">Clear</a>
        <?php endif; ?>
    </form>
</div>


<!-- REQUESTS TABLE -->
<div class="table">

    <div class="table-title">
        <span>All Requests</span>
        <span style="font-weight:400;color:#7a8179;font-size:12px"><?= $total ?> total</span>
    </div>

    <?php if (count($requests) === 0): ?>

        <div class="empty">
            <strong>No requests found</strong>
            <span>Try clearing the search or filters.</span>
        </div>

    <?php else: ?>

        <div class="table-scroll">
            <table>
                <tr>
                    <th>Item</th>
                    <th>Recipient</th>
                    <th>Qty</th>
                    <th>Collection</th>
                    <th>Requested</th>
                    <th>Status</th>
                    <th>Details</th>
                    <th>Actions</th>
                </tr>

                <?php foreach ($requests as $r): ?>
                    <?php
                    $rid     = (int) $r["id"];
                    $status  = $r["status"];
                    $reqdate = !empty($r["created_at"])
                        ? date("M d, Y", strtotime($r["created_at"]))
                        : "—";
                    $coll = !empty($r["collection_date"]) && $r["collection_date"] !== "0000-00-00"
                        ? date("M d, Y", strtotime($r["collection_date"]))
                        : "—";
                    $targets = $allowed_transitions[$status] ?? [];
                    ?>
                    <tr>
                        <td><?= e($r["donation_title"] ?? "—") ?></td>
                        <td><?= e($r["recipient_name"] ?? "Unknown") ?></td>
                        <td><?= e($r["quantity"] ?? "—") ?></td>
                        <td><?= e($coll) ?></td>
                        <td><?= e($reqdate) ?></td>
                        <td><?= render_status_badge($status) ?></td>
                        <td>
                            <details>
                                <summary style="cursor:pointer;color:#1f5b3a;font-weight:600">View</summary>
                                <div style="padding:8px 0;font-size:12px;line-height:1.7;min-width:220px">
                                    <div><b>Donor:</b> <?= e($r["donor_name"] ?? "—") ?></div>
                                    <div><b>Beneficiaries:</b> <?= e($r["beneficiaries"] ?? "—") ?></div>
                                    <div><b>Purpose:</b> <?= e($r["purpose"] ?? "—") ?></div>
                                    <div><b>Message:</b> <?= e($r["message"] ?? "—") ?></div>
                                    <div><b>Email:</b> <?= e($r["recipient_email"] ?? "—") ?></div>
                                    <div><b>Phone:</b> <?= e($r["recipient_phone"] ?? "—") ?></div>
                                </div>
                            </details>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <?php foreach ($targets as $t): ?>
                                    <?php $m = $action_meta[$t] ?? ["label" => ucfirst($t), "class" => "btn-ghost"]; ?>
                                    <form method="post" class="inline-form">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="return" value="<?= e($return_url) ?>">
                                        <input type="hidden" name="request_id" value="<?= $rid ?>">
                                        <input type="hidden" name="new_status" value="<?= e($t) ?>">
                                        <button type="submit" class="btn btn-sm <?= $m["class"] ?>">
                                            <?= e($m["label"]) ?>
                                        </button>
                                    </form>
                                <?php endforeach; ?>
                                <?php if (!$targets): ?>
                                    <span style="color:#9aa29a;font-size:11px">—</span>
                                <?php endif; ?>
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
