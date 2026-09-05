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
require_once __DIR__ . "/config/csrf.php";

$has_expiry = column_exists($conn, "donations", "expiry_date");
$expiry_col = $has_expiry ? "donations.expiry_date," : "";


/*
|--------------------------------------------------------------------------
| Logged-in User
|--------------------------------------------------------------------------
*/

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["user_name"] ?? "User";


/*
|--------------------------------------------------------------------------
| Cancel / Withdraw a Donation (donor's own only)
|--------------------------------------------------------------------------
|
| The donor can withdraw a listing they posted. It sets the status to
| 'cancelled' (kept for history, hidden from Browse). Only the owner can
| do it, and a donation that is already completed or cancelled is left
| alone.
|
*/

if ($_SERVER["REQUEST_METHOD"] === "POST"
    && ($_POST["action"] ?? "") === "cancel") {

    if (!csrf_verify()) {
        $_SESSION["md_flash"] = ["type" => "error", "msg" => "Security check failed. Please try again."];
        header("Location: my-donations.php");
        exit();
    }

    $cancel_id = (int) ($_POST["donation_id"] ?? 0);

    if ($cancel_id > 0) {

        $c = $conn->prepare("
            UPDATE donations
            SET status = 'cancelled'
            WHERE id = ?
              AND donor_id = ?
              AND status NOT IN ('completed', 'cancelled')
        ");
        $c->bind_param("ii", $cancel_id, $user_id);
        $c->execute();
        $affected = $c->affected_rows;
        $c->close();

        $_SESSION["md_flash"] = $affected > 0
            ? ["type" => "success", "msg" => "Donation withdrawn. It no longer appears in Browse."]
            : ["type" => "error", "msg" => "That donation could not be withdrawn."];
    }

    header("Location: my-donations.php");
    exit();
}


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
        {$expiry_col}
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
    LIMIT ?, ?
";

// True total for the summary + pagination.
$count_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM donations WHERE donor_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$total_donations = (int) ($count_stmt->get_result()->fetch_assoc()["c"] ?? 0);
$count_stmt->close();

$pg = paginate($total_donations, 10);

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Sorry, something went wrong loading this page. Please try again.");
}

$stmt->bind_param("iii", $user_id, $pg["offset"], $pg["per_page"]);
$stmt->execute();

$result = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Count Donations
|--------------------------------------------------------------------------
*/

// $total_donations was computed above (the true total, not just this page).


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



        <?php if (!empty($_SESSION["md_flash"])):
            $mf = $_SESSION["md_flash"];
            unset($_SESSION["md_flash"]);
            $mf_bg = $mf["type"] === "success" ? "#e8f4e8" : "#fde8e8";
            $mf_fg = $mf["type"] === "success" ? "#27713e" : "#a33a3a";
        ?>
            <div class="ud-flash" style="background:<?= $mf_bg ?>;color:<?= $mf_fg ?>;padding:14px 18px;border-radius:10px;margin-bottom:22px;font-weight:600;font-size:14px;display:flex;align-items:center;justify-content:space-between;gap:14px">
                <span><?= htmlspecialchars($mf["msg"]) ?></span>
                <button type="button" aria-label="Dismiss" onclick="this.parentNode.remove()" style="background:transparent;border:0;color:inherit;font-size:20px;line-height:1;cursor:pointer;opacity:.6;padding:0 2px">&times;</button>
            </div>
            <script>
            (function(){
                document.querySelectorAll(".ud-flash").forEach(function(el){
                    setTimeout(function(){ el.style.transition="opacity .4s"; el.style.opacity="0"; setTimeout(function(){ el.remove(); },400); },5000);
                });
            })();
            </script>
        <?php endif; ?>


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

                                <th>
                                    Actions
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

                                    <?php if (!empty($donation["expiry_date"])):
                                        $md_exp = strtotime($donation["expiry_date"]);
                                        $md_expired = $donation["expiry_date"] < date("Y-m-d"); ?>
                                        <br>
                                        <small style="color:<?= $md_expired ? "#b23b3b" : "#8a7a3a" ?>">
                                            ⏳ Best before
                                            <?= htmlspecialchars($md_exp ? date("d M Y", $md_exp) : $donation["expiry_date"]) ?>
                                            <?= $md_expired ? " (expired)" : "" ?>
                                        </small>
                                    <?php endif; ?>

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

                                <td>
                                    <?php if (!in_array($donation["status"], ["completed", "cancelled"], true)): ?>
                                        <form method="post"
                                              onsubmit="return confirm('Withdraw this donation? It will be removed from Browse.');"
                                              style="margin:0">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="donation_id" value="<?php echo (int) $donation["id"]; ?>">
                                            <button type="submit"
                                                style="background:#fff;border:1px solid #e0b4b4;color:#a33a3a;padding:7px 12px;border-radius:8px;font-weight:600;font-size:12px;cursor:pointer">
                                                Withdraw
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:#b9beb9;font-size:12px">—</span>
                                    <?php endif; ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                        </tbody>

                    </table>

                </div>

                <?php pagination_links($pg); ?>

                <style>
                .pagination{display:flex;gap:8px;justify-content:center;margin:26px 0;flex-wrap:wrap}
                .pagination a,.pagination span{padding:8px 13px;border-radius:8px;border:1px solid #e0ddd4;color:#1f5b3a;font-weight:600;font-size:13px;text-decoration:none}
                .pagination .current{background:#1f5b3a;color:#fff;border-color:#1f5b3a}
                .pagination a:hover{background:#eef4ea}
                </style>

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