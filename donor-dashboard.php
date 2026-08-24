<?php

session_start();

/*
|--------------------------------------------------------------------------
| CHECK LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {

    header("Location: login.html");
    exit();

}


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/config/database.php";


/*
|--------------------------------------------------------------------------
| LOGGED-IN USER
|--------------------------------------------------------------------------
*/

$user_id = $_SESSION["user_id"];

$user_name = $_SESSION["user_name"] ?? "User";
$user_email = $_SESSION["user_email"] ?? "";


/*
|--------------------------------------------------------------------------
| GREETING
|--------------------------------------------------------------------------
*/

$hour = date("H");

if ($hour < 12) {

    $greeting = "Good morning";

} elseif ($hour < 18) {

    $greeting = "Good afternoon";

} else {

    $greeting = "Good evening";

}


/*
|--------------------------------------------------------------------------
| TOTAL DONATIONS
|--------------------------------------------------------------------------
*/

$sql_total = "
    SELECT COUNT(*) AS total
    FROM donations
    WHERE donor_id = ?
";

$stmt_total = $conn->prepare($sql_total);

$stmt_total->bind_param("i", $user_id);

$stmt_total->execute();

$result_total = $stmt_total->get_result();

$total_donations = $result_total->fetch_assoc()["total"] ?? 0;

$stmt_total->close();


/*
|--------------------------------------------------------------------------
| AVAILABLE DONATIONS
|--------------------------------------------------------------------------
*/

$sql_available = "
    SELECT COUNT(*) AS total
    FROM donations
    WHERE donor_id = ?
    AND status = 'available'
";

$stmt_available = $conn->prepare($sql_available);

$stmt_available->bind_param("i", $user_id);

$stmt_available->execute();

$result_available = $stmt_available->get_result();

$available_donations = $result_available->fetch_assoc()["total"] ?? 0;

$stmt_available->close();


/*
|--------------------------------------------------------------------------
| REQUESTS FOR MY DONATIONS
|--------------------------------------------------------------------------
*/

$sql_requests = "
    SELECT COUNT(*) AS total
    FROM donation_requests dr
    INNER JOIN donations d
        ON dr.donation_id = d.id
    WHERE d.donor_id = ?
";

$stmt_requests = $conn->prepare($sql_requests);

$stmt_requests->bind_param("i", $user_id);

$stmt_requests->execute();

$result_requests = $stmt_requests->get_result();

$total_requests = $result_requests->fetch_assoc()["total"] ?? 0;

$stmt_requests->close();


/*
|--------------------------------------------------------------------------
| COMPLETED DONATIONS
|--------------------------------------------------------------------------
*/

$sql_completed = "
    SELECT COUNT(*) AS total
    FROM donations
    WHERE donor_id = ?
    AND status = 'completed'
";

$stmt_completed = $conn->prepare($sql_completed);

$stmt_completed->bind_param("i", $user_id);

$stmt_completed->execute();

$result_completed = $stmt_completed->get_result();

$completed_donations = $result_completed->fetch_assoc()["total"] ?? 0;

$stmt_completed->close();


/*
|--------------------------------------------------------------------------
| RECENT DONATIONS
|--------------------------------------------------------------------------
*/

$sql_recent = "
    SELECT
        d.id,
        d.title,
       d.quantity,
       d.unit,
       d.status,
        d.created_at,
        c.name AS category_name
    FROM donations d
    LEFT JOIN categories c
        ON d.category_id = c.id
    WHERE d.donor_id = ?
    ORDER BY d.created_at DESC
    LIMIT 5
";

$stmt_recent = $conn->prepare($sql_recent);

$stmt_recent->bind_param("i", $user_id);

$stmt_recent->execute();

$recent_donations = $stmt_recent->get_result();


/*
|--------------------------------------------------------------------------
| STATUS CLASS
|--------------------------------------------------------------------------
*/

function statusClass($status)
{

    switch ($status) {

        case "available":
            return "status-available";

        case "requested":
            return "status-requested";

        case "completed":
            return "status-completed";

        case "cancelled":
            return "status-cancelled";

        default:
            return "status-default";
    }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Donor Dashboard | DONATE+</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family: Arial, Helvetica, sans-serif;

            background: #f7f6f1;

            color: #171717;

        }


        .layout {

            display: flex;

            min-height: 100vh;

        }


        /* SIDEBAR */

        .sidebar {

            width: 272px;

            background: #10452f;

            color: white;

            padding: 45px 20px;

            position: fixed;

            left: 0;

            top: 0;

            bottom: 0;

        }


        .brand {

            padding: 0 15px;

            margin-bottom: 45px;

        }


        .brand-icon {

            width: 42px;

            height: 42px;

            border: 2px solid #48a878;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 22px;

            margin-bottom: 10px;

        }


        .brand h2 {

            margin: 0;

            font-size: 17px;

            letter-spacing: 0.5px;

        }


        .brand p {

            margin: 5px 0 0;

            font-size: 11px;

            color: #b9dfcb;

            letter-spacing: 0.5px;

        }


        .menu a {

            display: block;

            text-decoration: none;

            color: #e6f1eb;

            padding: 14px 15px;

            border-radius: 12px;

            margin-bottom: 5px;

            font-size: 15px;

        }


        .menu a:hover,

        .menu a.active {

            background: #2d7350;

            color: white;

        }


        /* MAIN */

        .main {

            margin-left: 272px;

            width: calc(100% - 272px);

            min-height: 100vh;

        }


        /* TOP BAR */

        .topbar {

            background: white;

            padding: 16px 35px;

            border-bottom: 1px solid #e5e2db;

            display: flex;

            align-items: center;

            justify-content: space-between;

        }


        .topbar small {

            color: #777;

            font-size: 15px;

        }


        .topbar h3 {

            margin: 18px 0 0;

            font-size: 27px;

        }


        .logout {

            text-decoration: none;

            color: #666;

            font-size: 14px;

        }


        .logout:hover {

            color: #10452f;

        }


        /* CONTENT */

        .content {

            padding: 35px 43px;

        }


        .section-label {

            color: #116348;

            font-size: 14px;

            font-weight: bold;

            letter-spacing: 1.5px;

            margin-bottom: 35px;

        }


        .welcome-row {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 45px;

        }


        .welcome-row h1 {

            font-family: Georgia, serif;

            font-size: 42px;

            margin: 0;

        }


        .donate-btn {

            background: #216b45;

            color: white;

            text-decoration: none;

            padding: 16px 25px;

            border-radius: 12px;

            font-weight: bold;

        }


        .donate-btn:hover {

            background: #185637;

        }


        /* STAT CARDS */

        .stats {

            display: grid;

            grid-template-columns: repeat(4, 1fr);

            gap: 16px;

            margin-bottom: 25px;

        }


        .stat-card {

            background: white;

            border: 1px solid #e4e1da;

            border-radius: 18px;

            padding: 28px 22px;

        }


        .stat-title {

            color: #777;

            font-size: 13px;

            margin-bottom: 18px;

        }


        .stat-number {

            font-family: Georgia, serif;

            font-size: 35px;

            font-weight: bold;

        }


        /* RECENT DONATIONS */

        .recent {

            background: white;

            border: 1px solid #e4e1da;

            border-radius: 18px;

            overflow: hidden;

        }


        .recent-header {

            padding: 25px 22px;

            font-size: 20px;

            font-weight: bold;

        }


        table {

            width: 100%;

            border-collapse: collapse;

        }


        th {

            text-align: left;

            padding: 18px;

            font-size: 13px;

            color: #333;

            border-bottom: 1px solid #eee;

        }


        td {

            padding: 18px;

            font-size: 14px;

            border-bottom: 1px solid #eee;

        }


        tr:last-child td {

            border-bottom: none;

        }


        .status {

            display: inline-block;

            padding: 7px 13px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

        }


        .status-available {

            background: #e7f5e9;

            color: #23723b;

        }


        .status-requested {

            background: #fff0dc;

            color: #a15c09;

        }


        .status-completed {

            background: #e4eefb;

            color: #285b96;

        }


        .status-cancelled {

            background: #fde7e7;

            color: #a52a2a;

        }


        .status-default {

            background: #eeeeee;

            color: #555;

        }


        .empty {

            text-align: center;

            padding: 45px 20px;

            color: #777;

        }


        .empty a {

            display: inline-block;

            margin-top: 15px;

            background: #216b45;

            color: white;

            text-decoration: none;

            padding: 11px 20px;

            border-radius: 8px;

        }


        /* MOBILE */

        @media (max-width: 1000px) {

            .sidebar {

                width: 220px;

            }

            .main {

                margin-left: 220px;

                width: calc(100% - 220px);

            }

            .stats {

                grid-template-columns: repeat(2, 1fr);

            }

        }


        @media (max-width: 700px) {

            .sidebar {

                position: relative;

                width: 100%;

                height: auto;

            }

            .layout {

                display: block;

            }

            .main {

                margin-left: 0;

                width: 100%;

            }

            .content {

                padding: 25px 18px;

            }

            .welcome-row {

                display: block;

            }

            .welcome-row h1 {

                font-size: 32px;

                margin-bottom: 20px;

            }

            .stats {

                grid-template-columns: 1fr;

            }

            table {

                min-width: 650px;

            }

            .recent {

                overflow-x: auto;

            }

        }

    </style>

</head>


<body>


<div class="layout">


    <!-- SIDEBAR -->

    <aside class="sidebar">


        <div class="brand">

            <div class="brand-icon">♡</div>

            <h2>DONATE+</h2>

            <p>Give Today, Change Tomorrow</p>

        </div>


        <nav class="menu">

            <a href="donor-dashboard.php" class="active">

                Dashboard

            </a>


            <a href="add-donation.php">

                Add Donation

            </a>


            <a href="find-donations.php">

                Browse Donations

            </a>


            <a href="my-donations.php">

                My Donations

            </a>


            <a href="donor-requests.php">
    Requests
</a>


        </nav>


    </aside>



    <!-- MAIN -->

    <main class="main">


        <!-- TOP BAR -->

        <header class="topbar">

            <div>

                <small>Donor Dashboard</small>

                <h3>

                    <?php echo htmlspecialchars($greeting); ?>,

                    <?php echo htmlspecialchars($user_name); ?>

                </h3>

            </div>


            <a href="logout.php" class="logout">

                Logout

            </a>

        </header>



        <!-- CONTENT -->

        <section class="content">


            <div class="section-label">

                DONOR OVERVIEW

            </div>


            <div class="welcome-row">

                <h1>

                    <?php echo htmlspecialchars($greeting); ?>,

                    <?php echo htmlspecialchars($user_name); ?>

                </h1>


                <div style="display:flex;gap:12px;flex-wrap:wrap">

                    <a href="find-donations.php" class="donate-btn"
                       style="background:#fff;color:#216b45;border:2px solid #216b45;padding:14px 23px">

                        Browse Donations

                    </a>

                    <a href="add-donation.php" class="donate-btn">

                        + Donate an Item

                    </a>

                </div>

            </div>


            <?php if ((int) $completed_donations > 0): ?>

                <div style="background:linear-gradient(90deg,#eaf6ec,#f4faf0);border:1px solid #cfe6d2;border-radius:16px;padding:18px 22px;margin-bottom:30px;display:flex;align-items:center;gap:14px">

                    <span style="font-size:28px">&#127881;</span>

                    <div>
                        <b style="color:#216b45;font-size:15px">Thank you for donating, <?php echo htmlspecialchars($user_name); ?>!</b>
                        <div style="color:#5a6b5e;font-size:13px;margin-top:2px">
                            You&rsquo;ve completed <?php echo (int) $completed_donations; ?>
                            donation<?php echo ((int) $completed_donations === 1) ? "" : "s"; ?>
                            &mdash; your generosity is making a real difference in someone&rsquo;s life. &#128153;
                        </div>
                    </div>

                </div>

            <?php endif; ?>



            <!-- STATISTICS -->

            <div class="stats">


                <div class="stat-card">

                    <div class="stat-title">

                        Total Donations

                    </div>

                    <div class="stat-number">

                        <?php echo $total_donations; ?>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-title">

                        Available

                    </div>

                    <div class="stat-number">

                        <?php echo $available_donations; ?>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-title">

                        Requests

                    </div>

                    <div class="stat-number">

                        <?php echo $total_requests; ?>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-title">

                        Completed

                    </div>

                    <div class="stat-number">

                        <?php echo $completed_donations; ?>

                    </div>

                </div>


            </div>



            <!-- RECENT DONATIONS -->

            <div class="recent">


                <div class="recent-header">

                    Recent Donations

                </div>


                <?php if ($recent_donations->num_rows > 0): ?>


                    <table>

                        <thead>

                            <tr>

                                <th>Item</th>

                                <th>Category</th>

                                <th>Quantity</th>

                                <th>Status</th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php while ($donation = $recent_donations->fetch_assoc()): ?>


                            <tr>

                                <td>

                                    <?php echo htmlspecialchars($donation["title"]); ?>

                                </td>


                                <td>

                                    <?php echo htmlspecialchars(
                                        $donation["category_name"] ?? "Uncategorized"
                                    ); ?>

                                </td>


                                <td>

                                    <?php echo htmlspecialchars(
    $donation["quantity"]
); ?>

<?php echo " " . htmlspecialchars(
    $donation["unit"]
); ?>

                                </td>


                                <td>

                                    <span class="status <?php echo statusClass($donation["status"]); ?>">

                                        <?php echo ucfirst(
                                            htmlspecialchars($donation["status"])
                                        ); ?>

                                    </span>

                                    <?php if ($donation["status"] === "completed"): ?>
                                        <div style="font-size:11px;color:#2f8f5b;margin-top:5px;font-weight:600">
                                            &#128153; Thank you!
                                        </div>
                                    <?php endif; ?>

                                </td>

                            </tr>


                        <?php endwhile; ?>


                        </tbody>

                    </table>


                <?php else: ?>


                    <div class="empty">

                        <p>You haven't added any donations yet.</p>

                        <a href="add-donation.php">

                            Add Your First Donation

                        </a>

                    </div>


                <?php endif; ?>


            </div>


        </section>


    </main>


</div>


</body>

</html>


<?php

$stmt_recent->close();

$conn->close();

?>