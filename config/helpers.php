<?php

/*
|--------------------------------------------------------------------------
| SHARED HELPERS
|--------------------------------------------------------------------------
|
| Small view/query utilities used across the admin pages.
|
*/


/*
| Escape shortcut for echoing user data into HTML.
*/

function e($value)
{
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}


/*
| Check whether a table exists in the current database. Cached per
| request. Used so audit logging degrades gracefully when the
| activity_log table hasn't been created yet.
*/

function table_exists($conn, $table)
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $stmt = $conn->prepare(
        "SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name   = ?
         LIMIT 1"
    );

    if (!$stmt) {
        return $cache[$table] = false;
    }

    $stmt->bind_param("s", $table);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $cache[$table] = $exists;
}


/*
| Record an admin action in the activity_log. Completely best-effort:
| if the table is missing or anything fails, it silently does nothing
| so it can never break the action that triggered it.
*/

function log_activity($conn, $admin_id, $action, $detail = "")
{
    if (!table_exists($conn, "activity_log")) {
        return;
    }

    $stmt = $conn->prepare(
        "INSERT INTO activity_log (admin_id, action, detail) VALUES (?, ?, ?)"
    );

    if (!$stmt) {
        return;
    }

    $admin_id = (int) $admin_id;
    $action   = mb_substr((string) $action, 0, 80);
    $detail   = mb_substr((string) $detail, 0, 255);

    $stmt->bind_param("iss", $admin_id, $action, $detail);
    @$stmt->execute();
    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| DONATION QUANTITY HELPERS
|--------------------------------------------------------------------------
|
| "remaining" is computed live: the donation's total quantity minus
| everything already approved or completed. No schema change needed.
|
*/

/*
| Sum of quantities already claimed (approved + completed requests).
*/

function donation_claimed_qty($conn, $donation_id)
{
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(quantity), 0) AS c
         FROM donation_requests
         WHERE donation_id = ? AND status IN ('approved','completed')"
    );

    if (!$stmt) {
        return 0;
    }

    $donation_id = (int) $donation_id;
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $c = (int) ($stmt->get_result()->fetch_assoc()["c"] ?? 0);
    $stmt->close();

    return $c;
}


/*
| Remaining quantity = total - claimed (never below 0). Pass $total to
| avoid an extra lookup if you already have the donation's quantity.
*/

function donation_remaining($conn, $donation_id, $total = null)
{
    $donation_id = (int) $donation_id;

    if ($total === null) {
        $stmt = $conn->prepare("SELECT quantity FROM donations WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("i", $donation_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return 0;
        }
        $total = (int) $row["quantity"];
    }

    $remaining = (int) $total - donation_claimed_qty($conn, $donation_id);

    return $remaining < 0 ? 0 : $remaining;
}


/*
| Recompute a donation's status from how much has been claimed:
|   claimed >= total  -> completed
|   otherwise         -> available
| A donation the admin set to 'cancelled' is left untouched.
*/

function recompute_donation_status($conn, $donation_id)
{
    $donation_id = (int) $donation_id;

    $stmt = $conn->prepare("SELECT quantity, status FROM donations WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $d = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$d || $d["status"] === "cancelled") {
        return;
    }

    $claimed = donation_claimed_qty($conn, $donation_id);
    $new = ($claimed >= (int) $d["quantity"]) ? "completed" : "available";

    if ($new !== $d["status"]) {
        $u = $conn->prepare("UPDATE donations SET status = ? WHERE id = ?");
        $u->bind_param("si", $new, $donation_id);
        $u->execute();
        $u->close();
    }
}


/*
| Check whether a column exists on a table. Used so pages degrade
| gracefully when a migration hasn't been run yet (e.g. is_active).
| Result is cached per request.
*/

function column_exists($conn, $table, $column)
{
    static $cache = [];

    $key = $table . "." . $column;

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = $conn->prepare(
        "SELECT 1
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name   = ?
           AND column_name  = ?
         LIMIT 1"
    );

    if (!$stmt) {
        return $cache[$key] = false;
    }

    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $cache[$key] = $exists;
}


/*
| Render a coloured status pill. Works for donation and request
| statuses (available, requested, completed, cancelled, pending,
| approved, rejected). Unknown values fall back to a neutral style.
*/

function render_status_badge($status)
{
    $status = strtolower(trim((string) $status));

    $known = [
        "available", "requested", "completed", "cancelled",
        "pending",   "approved",  "rejected",
        "donor",     "recipient", "admin",
        "active",    "inactive",
    ];

    $class = in_array($status, $known, true) ? $status : "neutral";

    return '<span class="status ' . e($class) . '">'
        . e(ucfirst($status))
        . '</span>';
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
|
| paginate() reads the ?page= param and returns the LIMIT/OFFSET plus
| the meta needed to draw links. Pass in the total row count.
|
*/

function paginate($total_rows, $per_page = 15)
{
    $per_page = max(1, (int) $per_page);

    $total_pages = max(1, (int) ceil($total_rows / $per_page));

    $current = (int) ($_GET["page"] ?? 1);

    if ($current < 1) {
        $current = 1;
    }

    if ($current > $total_pages) {
        $current = $total_pages;
    }

    $offset = ($current - 1) * $per_page;

    return [
        "per_page"    => $per_page,
        "offset"      => $offset,
        "current"     => $current,
        "total_pages" => $total_pages,
        "total_rows"  => (int) $total_rows,
    ];
}


/*
| Render prev / numbered / next links. $base_query is an associative
| array of the current filters to preserve (e.g. ["role" => "donor"]);
| the page number is added automatically.
*/

function pagination_links($meta, $base_query = [])
{
    if ($meta["total_pages"] <= 1) {
        return;
    }

    $make_url = function ($page) use ($base_query) {
        $base_query["page"] = $page;
        return "?" . http_build_query($base_query);
    };

    echo '<div class="pagination">';

    // Prev
    if ($meta["current"] > 1) {
        echo '<a href="' . e($make_url($meta["current"] - 1)) . '">&larr; Prev</a>';
    }

    // Numbered (windowed around current)
    $start = max(1, $meta["current"] - 2);
    $end   = min($meta["total_pages"], $meta["current"] + 2);

    for ($p = $start; $p <= $end; $p++) {

        if ($p === $meta["current"]) {
            echo '<span class="current">' . $p . '</span>';
        } else {
            echo '<a href="' . e($make_url($p)) . '">' . $p . '</a>';
        }
    }

    // Next
    if ($meta["current"] < $meta["total_pages"]) {
        echo '<a href="' . e($make_url($meta["current"] + 1)) . '">Next &rarr;</a>';
    }

    echo '</div>';
}
