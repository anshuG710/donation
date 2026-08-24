<?php

session_start();

/*
|--------------------------------------------------------------------------
| Check Login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit();
}


/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/helpers.php";


/*
|--------------------------------------------------------------------------
| Logged-in User
|--------------------------------------------------------------------------
*/

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["user_name"] ?? "User";


/*
|--------------------------------------------------------------------------
| Get Donor's Donations
|--------------------------------------------------------------------------
|
| donations.donor_id = logged-in user's ID
| categories.id = donations.category_id
|
*/

$sql = "
    SELECT 
        donations.id,
        donations.title,
        donations.description,
        donations.quantity,
        donations.item_condition,
        donations.location,
        donations.image,
        donations.status,
        donations.created_at,
        (SELECT COALESCE(SUM(dr.quantity), 0)
           FROM donation_requests dr
          WHERE dr.donation_id = donations.id
            AND dr.status IN ('approved','completed')) AS claimed,
        categories.name AS category_name
    FROM donations
    LEFT JOIN categories 
        ON donations.category_id = categories.id
    WHERE donations.donor_id = ?
    ORDER BY donations.created_at DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database query failed: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Count Donations
|--------------------------------------------------------------------------
*/

$total_donations = $result->num_rows;


/*
|--------------------------------------------------------------------------
| Helper Function For Status
|--------------------------------------------------------------------------
*/

function statusClass($status)
{
    switch ($status) {

        case "available":
            return "available";

        case "requested":
            return "requested";

        case "completed":
            return "completed";

        case "cancelled":
            return "cancelled";

        default:
            return "available";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Donations | DONATE+</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f7f6f1;
            color: #171717;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }


        /* Sidebar */

        .sidebar {
            width: 242px;
            background: #10452f;
            color: white;
            padding: 38px 17px;
            flex-shrink: 0;
        }


        .logo {
            padding: 0 13px;
            margin-bottom: 42px;
        }

        .logo-circle {
            width: 40px;
            height: 40px;
            border: 2px solid #7ed6a8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 10px;
        }

        .logo-name {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .logo-text {
            font-size: 10px;
            color: #a8dfc0;
            margin-top: 4px;
        }


        /* Navigation */

        .nav a {
            display: block;
            text-decoration: none;
            color: white;
            padding: 13px;
            border-radius: 10px;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .nav a:hover {
            background: #286d4c;
        }

        .nav a.active {
            background: #2d7653;
        }


        /* Main */

        .main {
            flex: 1;
            padding: 0 38px 60px;
        }


        /* Top Bar */

        .topbar {
            height: 88px;
            border-bottom: 1px solid #e3e0d8;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .top-title {
            color: #777;
            font-size: 14px;
        }

        .logout {
            text-decoration: none;
            color: #666;
            font-size: 14px;
        }

        .logout:hover {
            color: #10452f;
        }


        /* Page Header */

        .page-header {
            margin-top: 38px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-family: Georgia, serif;
            font-size: 42px;
            margin: 0;
        }

        .page-header p {
            color: #777;
            margin-top: 8px;
        }


        .donate-button {
            background: #216c47;
            color: white;
            text-decoration: none;
            padding: 14px 22px;
            border-radius: 10px;
            font-weight: bold;
        }

        .donate-button:hover {
            background: #185538;
        }


        /* Summary */

        .summary {
            margin-top: 30px;
            background: white;
            border: 1px solid #e5e1d9;
            border-radius: 14px;
            padding: 22px 25px;
        }

        .summary-number {
            font-family: Georgia, serif;
            font-size: 32px;
            font-weight: bold;
        }

        .summary-text {
            color: #777;
            font-size: 14px;
            margin-top: 5px;
        }


        /* Donations Container */

        .donations-container {
            margin-top: 25px;
            background: white;
            border: 1px solid #e5e1d9;
            border-radius: 14px;
            overflow: hidden;
        }

        .container-header {
            padding: 24px;
            border-bottom: 1px solid #eee;
        }

        .container-header h2 {
            margin: 0;
            font-size: 20px;
        }


        /* Table */

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 17px 20px;
            font-size: 13px;
            color: #555;
            background: #fafafa;
        }

        td {
            padding: 18px 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
        }

        tr:hover td {
            background: #fcfcfa;
        }


        .item-name {
            font-weight: bold;
            color: #222;
        }

        .category {
            color: #555;
        }

        .quantity {
            color: #444;
        }


        /* Status */

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status.available {
            background: #e5f5e9;
            color: #267044;
        }

        .status.requested {
            background: #fff0d8;
            color: #a86612;
        }

        .status.completed {
            background: #e3eefb;
            color: #2d5e91;
        }

        .status.cancelled {
            background: #f8e2e2;
            color: #a33b3b;
        }


        /* Empty State */

        .empty {
            padding: 60px 20px;
            text-align: center;
        }

        .empty-icon {
            font-size: 45px;
            margin-bottom: 15px;
        }

        .empty h3 {
            margin: 0 0 8px;
        }

        .empty p {
            color: #777;
            margin-bottom: 25px;
        }


        /* Responsive */

        @media (max-width: 800px) {

            .sidebar {
                width: 190px;
            }

            .main {
                padding: 0 20px 40px;
            }

            .page-header {
                align-items: flex-start;
                gap: 20px;
                flex-direction: column;
            }

            .page-header h1 {
                font-size: 34px;
            }

        }

    </style>

</head>


<body>


<div class="layout">


    <!-- Sidebar -->

    <aside class="sidebar">

        <div class="logo">

            <div class="logo-circle">
                ♡
            </div>

            <div class="logo-name">
                DONATE+
            </div>

            <div class="logo-text">
                Give Today, Change Tomorrow
            </div>

        </div>


        <nav class="nav">

            <a href="donor-dashboard.php">
                Dashboard
            </a>

            <a href="add-donation.php">
                Add Donation
            </a>

            <a href="my-donations.php" class="active">
                My Donations
            </a>

            <a href="donor-requests.php">
                Requests
            </a>

        </nav>

    </aside>



    <!-- Main Content -->

    <main class="main">


        <!-- Top Bar -->

        <div class="topbar">

            <div class="top-title">
                My Donations
            </div>

            <a href="logout.php" class="logout">
                Logout
            </a>

        </div>



        <!-- Page Header -->

        <div class="page-header">

            <div>

                <h1>
                    My Donations
                </h1>

                <p>
                    View and manage the items you have donated.
                </p>

            </div>


            <a href="add-donation.php" class="donate-button">
                + Donate an Item
            </a>

        </div>



        <!-- Summary -->

        <div class="summary">

            <div class="summary-number">
                <?php echo $total_donations; ?>
            </div>

            <div class="summary-text">
                Total Donations
            </div>

        </div>



        <!-- Donations -->

        <div class="donations-container">

            <div class="container-header">

                <h2>
                    Donation History
                </h2>

            </div>


            <?php if ($result->num_rows > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Item
                                </th>

                                <th>
                                    Category
                                </th>

                                <th>
                                    Quantity
                                </th>

                                <th>
                                    Condition
                                </th>

                                <th>
                                    Location
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php while ($donation = $result->fetch_assoc()): ?>

                            <tr>

                                <td>

                                    <div class="item-name">
                                        <?php echo htmlspecialchars($donation["title"]); ?>
                                    </div>

                                    <div style="font-size:12px;color:#999;margin-top:4px">
                                        Donated on
                                        <?php
                                        echo !empty($donation["created_at"])
                                            ? date("M d, Y", strtotime($donation["created_at"]))
                                            : "—";
                                        ?>
                                    </div>

                                </td>


                                <td>

                                    <div class="category">

                                        <?php
                                        echo htmlspecialchars(
                                            $donation["category_name"] ?? "Uncategorized"
                                        );
                                        ?>

                                    </div>

                                </td>


                                <td>

                                    <div class="quantity">

                                        <?php
                                        $md_total = (int) ($donation["quantity"] ?? 0);
                                        $md_left  = $md_total - (int) ($donation["claimed"] ?? 0);
                                        if ($md_left < 0) { $md_left = 0; }
                                        ?>

                                        <strong><?php echo $md_left; ?></strong>
                                        <span style="color:#999">of <?php echo $md_total; ?> left</span>

                                    </div>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $donation["item_condition"] ?? "Not specified"
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $donation["location"] ?? "Not specified"
                                    );
                                    ?>

                                </td>


                                <td>

                                    <span class="status <?php echo statusClass($donation["status"]); ?>">

                                        <?php
                                        echo ucfirst(
                                            htmlspecialchars($donation["status"])
                                        );
                                        ?>

                                    </span>

                                    <?php if ($donation["status"] === "completed"): ?>
                                        <div style="font-size:11px;color:#2f8f5b;margin-top:6px;font-weight:600">
                                            &#128153; Thank you for donating!
                                        </div>
                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>


                <!-- No Donations -->

                <div class="empty">

                    <div class="empty-icon">
                        ♡
                    </div>

                    <h3>
                        No donations yet
                    </h3>

                    <p>
                        You haven't added any donations yet.
                    </p>

                    <a href="add-donation.php" class="donate-button">
                        + Donate Your First Item
                    </a>

                </div>


            <?php endif; ?>

        </div>


    </main>

</div>


</body>

</html>

<?php

$stmt->close();
$conn->close();

?>