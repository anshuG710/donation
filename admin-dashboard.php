<?php

/*
|--------------------------------------------------------------------------
| ADMIN DASHBOARD
|--------------------------------------------------------------------------
|
| Platform overview: headline counts plus the most recent donations
| and users. Management actions live on the dedicated admin pages
| linked from the sidebar.
|
*/

require_once __DIR__ . "/config/admin-guard.php";


/*
|--------------------------------------------------------------------------
| COUNT HELPER
|--------------------------------------------------------------------------
*/

function countRows($conn, $sql)
{
    $result = $conn->query($sql);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

    return (int) ($row["total"] ?? 0);
}


/*
|--------------------------------------------------------------------------
| TOTALS
|--------------------------------------------------------------------------
*/

$total_users = countRows(
    $conn,
    "SELECT COUNT(*) AS total FROM users"
);

$total_donors = countRows(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role = 'donor'"
);

$total_recipients = countRows(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role = 'recipient'"
);

$total_donations = countRows(
    $conn,
    "SELECT COUNT(*) AS total FROM donations"
);

$available_donations = countRows(
    $conn,
    "SELECT COUNT(*) AS total FROM donations WHERE status = 'available'"
);

$total_requests = countRows(
    $conn,
    "SELECT COUNT(*) AS total FROM donation_requests"
);

$pending_requests = countRows(
    $conn,
    "SELECT COUNT(*) AS total FROM donation_requests WHERE status = 'pending'"
);


/*
|--------------------------------------------------------------------------
| RECENT DONATIONS
|--------------------------------------------------------------------------
*/

$recent_donations = [];

$sql = "
    SELECT
        d.id, d.title, d.quantity, d.unit, d.status, d.created_at,
        u.name AS donor_name,
        c.name AS category_name
    FROM donations d
    LEFT JOIN users u      ON d.donor_id = u.id
    LEFT JOIN categories c ON d.category_id = c.id
    ORDER BY d.created_at DESC
    LIMIT 8
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_donations[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| RECENT USERS
|--------------------------------------------------------------------------
*/

$recent_users = [];

$sql = "
    SELECT id, name, email, role, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 8
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_users[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| RENDER
|--------------------------------------------------------------------------
*/

$page_title   = "Admin Dashboard";
$page_heading = "Platform Overview";
$active_nav   = "dashboard";

require_once __DIR__ . "/includes/admin-header.php";
?>


<!-- TOP -->
<div class="top-section">
    <div>
        <div class="eyebrow">Administration</div>
        <h2 class="serif">Platform Overview</h2>
    </div>
</div>


<!-- KPIs -->
<div class="kpis">

    <div class="kpi">
        <span>Total Users</span>
        <b><?= $total_users ?></b>
    </div>

    <div class="kpi">
        <span>Donors</span>
        <b><?= $total_donors ?></b>
    </div>

    <div class="kpi">
        <span>Recipients</span>
        <b><?= $total_recipients ?></b>
    </div>

    <div class="kpi">
        <span>Total Donations</span>
        <b><?= $total_donations ?></b>
    </div>

    <div class="kpi">
        <span>Available Items</span>
        <b><?= $available_donations ?></b>
    </div>

    <div class="kpi">
        <span>Total Requests</span>
        <b><?= $total_requests ?></b>
    </div>

    <a class="kpi" href="admin-requests.php?status=pending" style="color:inherit">
        <span>Pending Requests</span>
        <b><?= $pending_requests ?></b>
    </a>

</div>


<!-- RECENT DONATIONS -->
<div class="table">

    <div class="table-title">
        <span>Recent Donations</span>
        <a class="btn btn-ghost btn-sm" href="admin-donations.php">View all</a>
    </div>

    <?php if (count($recent_donations) === 0): ?>

        <div class="empty">
            <strong>No donations yet</strong>
            <span>Donations posted by donors will appear here.</span>
        </div>

    <?php else: ?>

        <div class="table-scroll">
            <table>
                <tr>
                    <th>Item</th>
                    <th>Donor</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Posted</th>
                    <th>Status</th>
                </tr>

                <?php foreach ($recent_donations as $donation): ?>
                    <?php
                    $donation_quantity = trim(
                        ($donation["quantity"] ?? "0") . " " . ($donation["unit"] ?? "")
                    );

                    $donation_date = !empty($donation["created_at"])
                        ? date("M d, Y", strtotime($donation["created_at"]))
                        : "Unknown";
                    ?>
                    <tr>
                        <td><?= e($donation["title"] ?? "Donation") ?></td>
                        <td><?= e($donation["donor_name"] ?? "Unknown") ?></td>
                        <td><?= e($donation["category_name"] ?? "Other") ?></td>
                        <td><?= e($donation_quantity) ?></td>
                        <td><?= e($donation_date) ?></td>
                        <td><?= render_status_badge($donation["status"] ?? "available") ?></td>
                    </tr>
                <?php endforeach; ?>

            </table>
        </div>

    <?php endif; ?>

</div>


<!-- RECENT USERS -->
<div class="table">

    <div class="table-title">
        <span>Recent Users</span>
        <a class="btn btn-ghost btn-sm" href="admin-users.php">View all</a>
    </div>

    <?php if (count($recent_users) === 0): ?>

        <div class="empty">
            <strong>No users yet</strong>
            <span>Registered accounts will appear here.</span>
        </div>

    <?php else: ?>

        <div class="table-scroll">
            <table>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                </tr>

                <?php foreach ($recent_users as $account): ?>
                    <?php
                    $account_date = !empty($account["created_at"])
                        ? date("M d, Y", strtotime($account["created_at"]))
                        : "Unknown";
                    ?>
                    <tr>
                        <td><?= e($account["name"] ?? "User") ?></td>
                        <td><?= e($account["email"] ?? "") ?></td>
                        <td><?= render_status_badge($account["role"] ?? "donor") ?></td>
                        <td><?= e($account_date) ?></td>
                    </tr>
                <?php endforeach; ?>

            </table>
        </div>

    <?php endif; ?>

</div>


<?php require_once __DIR__ . "/includes/admin-footer.php"; ?>
