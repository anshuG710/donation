<?php

session_start();

require_once __DIR__ . "/config/database.php";


/*
|--------------------------------------------------------------------------
| CHECK LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit();
}

$recipient_id = (int) $_SESSION["user_id"];

$user_name = htmlspecialchars(
    $_SESSION["user_name"] ?? "Recipient"
);


/*
|--------------------------------------------------------------------------
| AVAILABLE DONATIONS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM donations
    WHERE status = 'available'
";

$result = $conn->query($sql);

$available_items = 0;

if ($result) {
    $row = $result->fetch_assoc();
    $available_items = (int) ($row["total"] ?? 0);
}


/*
|--------------------------------------------------------------------------
| MY REQUESTS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM donation_requests
    WHERE recipient_id = ?
";

$stmt = $conn->prepare($sql);

$my_requests = 0;

if ($stmt) {

    $stmt->bind_param("i", $recipient_id);

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $my_requests = (int) ($row["total"] ?? 0);
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| APPROVED REQUESTS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM donation_requests
    WHERE recipient_id = ?
    AND status = 'approved'
";

$stmt = $conn->prepare($sql);

$approved_requests = 0;

if ($stmt) {

    $stmt->bind_param("i", $recipient_id);

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $approved_requests = (int) ($row["total"] ?? 0);
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| COMPLETED REQUESTS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM donation_requests
    WHERE recipient_id = ?
    AND status = 'completed'
";

$stmt = $conn->prepare($sql);

$completed_requests = 0;

if ($stmt) {

    $stmt->bind_param("i", $recipient_id);

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $completed_requests = (int) ($row["total"] ?? 0);
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| RECENT REQUESTS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        dr.id,
        dr.quantity,
        dr.status,
        dr.created_at,
        d.title

    FROM donation_requests dr

    INNER JOIN donations d
        ON dr.donation_id = d.id

    WHERE dr.recipient_id = ?

    ORDER BY dr.created_at DESC

    LIMIT 5
";

$stmt = $conn->prepare($sql);

$recent_requests = [];

if ($stmt) {

    $stmt->bind_param("i", $recipient_id);

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $recent_requests[] = $row;

    }

    $stmt->close();
}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Recipient Dashboard | DONATE+</title>


<style>

@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');


:root {

    --green:#1f5b3a;
    --green2:#2f744a;
    --orange:#f28c18;
    --cream:#fbfaf6;
    --ink:#171915;

}


* {
    box-sizing:border-box;
}


body {

    margin:0;

    font-family:'DM Sans',sans-serif;

    background:#f7f7f3;

    color:var(--ink);

}


a {
    text-decoration:none;
}


.serif {

    font-family:'Playfair Display',serif;

}


/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

.side {

    width:245px;

    background:#173c28;

    color:#fff;

    position:fixed;

    left:0;

    top:0;

    bottom:0;

    padding:25px 18px;

}


.brand {

    display:flex;

    align-items:center;

    gap:10px;

    color:#fff;

    font-weight:800;

    margin-bottom:30px;

}


.brandmark {

    width:38px;

    height:38px;

    border:2px solid #fff;

    border-radius:50%;

    display:grid;

    place-items:center;

    font-size:19px;

}


.brand small {

    display:block;

    font-size:9px;

    color:#b9c9bd;

    letter-spacing:.6px;

    font-weight:600;

}


.side a:not(.brand) {

    display:block;

    color:#cddbd0;

    padding:12px 14px;

    border-radius:10px;

    font-size:13px;

    margin-top:5px;

}


.side a:not(.brand).active,
.side a:not(.brand):hover {

    background:#2d6746;

    color:#fff;

}


/*
|--------------------------------------------------------------------------
| MAIN
|--------------------------------------------------------------------------
*/

.dashmain {

    margin-left:245px;

}


.dashhead {

    height:72px;

    background:#fff;

    border-bottom:1px solid #e8e5de;

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:0 30px;

}


.dashhead small {

    color:#777;

}


.dashhead h1 {

    font-size:22px;

    font-weight:800;

    margin:3px 0 0;

}


.logout {

    color:#697069;

    font-size:12px;

}


.logout:hover {

    color:var(--green);

}


.dashcontent {

    padding:30px;

}


/*
|--------------------------------------------------------------------------
| TOP SECTION
|--------------------------------------------------------------------------
*/

.top-section {

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:20px;

}


.eyebrow {

    color:var(--green);

    font-weight:800;

    letter-spacing:1px;

    font-size:12px;

    text-transform:uppercase;

}


.top-section h2 {

    font-size:34px;

    margin:8px 0;

}


.btn {

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:8px;

    padding:13px 20px;

    border-radius:10px;

    font-weight:700;

    transition:.2s;

}


.btn-green {

    background:var(--green);

    color:#fff;

}


.btn-green:hover {

    background:#17482e;

}


/*
|--------------------------------------------------------------------------
| KPI CARDS
|--------------------------------------------------------------------------
*/

.kpis {

    display:grid;

    grid-template-columns:repeat(4,1fr);

    gap:15px;

    margin-top:25px;

}


.kpi {

    background:#fff;

    border:1px solid #e8e5de;

    border-radius:16px;

    padding:20px;

}


.kpi span {

    font-size:11px;

    color:#7a8179;

}


.kpi b {

    font-family:'Playfair Display',serif;

    font-size:30px;

    display:block;

    margin-top:5px;

}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table {

    background:#fff;

    border:1px solid #e8e5de;

    border-radius:16px;

    margin-top:20px;

    overflow:hidden;

}


.table-title {

    padding:20px;

    font-weight:800;

    border-bottom:1px solid #eee;

}


.table-scroll {

    overflow-x:auto;

}


table {

    width:100%;

    border-collapse:collapse;

    font-size:12px;

}


th,
td {

    text-align:left;

    padding:15px;

    border-bottom:1px solid #eee;

}


th {

    color:#697069;

    font-size:11px;

}


td {

    color:#303630;

}


.status {

    padding:5px 9px;

    border-radius:20px;

    font-size:10px;

    font-weight:700;

    text-transform:capitalize;

}


.status.pending {

    background:#fff0dc;

    color:#b56300;

}


.status.approved {

    background:#e8f4e8;

    color:#27713e;

}


.status.completed {

    background:#e9edf7;

    color:#40517b;

}


.status.rejected {

    background:#fde8e8;

    color:#a33a3a;

}


/*
|--------------------------------------------------------------------------
| EMPTY TABLE
|--------------------------------------------------------------------------
*/

.empty {

    padding:45px 20px;

    text-align:center;

    color:#777;

}


.empty strong {

    display:block;

    color:#333;

    margin-bottom:6px;

}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media(max-width:900px) {

    .side {

        display:none;

    }


    .dashmain {

        margin-left:0;

    }


    .kpis {

        grid-template-columns:1fr 1fr;

    }


    .top-section {

        align-items:flex-start;

        flex-direction:column;

    }

}


@media(max-width:600px) {

    .kpis {

        grid-template-columns:1fr;

    }


    .dashcontent {

        padding:18px;

    }


    .dashhead {

        padding:0 18px;

    }

}

</style>

</head>


<body>


<!-- SIDEBAR -->

<aside class="side">


    <a class="brand"
       href="recipient-dashboard.php">


        <span class="brandmark">
            ♡
        </span>


        <span>

            DONATE+

            <small>
                Give Today, Change Tomorrow
            </small>

        </span>


    </a>


    <a class="active"
       href="recipient-dashboard.php">

        Dashboard

    </a>


    <a href="find-donations.php">

        Find Donations

    </a>


    <a href="recipient-requests.php">

        My Requests

    </a>


    <a href="#">

        History

    </a>


</aside>



<!-- MAIN -->

<main class="dashmain">


    <!-- HEADER -->

    <header class="dashhead">


        <div>

            <small>
                Recipient Dashboard
            </small>


            <h1>

                Welcome, <?= $user_name ?>

            </h1>

        </div>


        <a href="logout.php"
           class="logout">

            Logout

        </a>


    </header>



    <!-- CONTENT -->

    <div class="dashcontent">


        <!-- TOP -->

        <div class="top-section">


            <div>

                <div class="eyebrow">

                    Recipient Portal

                </div>


                <h2 class="serif">

                    Welcome, <?= $user_name ?>

                </h2>

            </div>


            <a class="btn btn-green"
               href="find-donations.php">

                Find Donations

            </a>


        </div>



        <!-- KPIs -->

        <div class="kpis">


            <div class="kpi">

                <span>
                    Available Items
                </span>

                <b>
                    <?= $available_items ?>
                </b>

            </div>



            <div class="kpi">

                <span>
                    My Requests
                </span>

                <b>
                    <?= $my_requests ?>
                </b>

            </div>



            <div class="kpi">

                <span>
                    Approved
                </span>

                <b>
                    <?= $approved_requests ?>
                </b>

            </div>



            <div class="kpi">

                <span>
                    Completed
                </span>

                <b>
                    <?= $completed_requests ?>
                </b>

            </div>


        </div>



        <!-- RECENT REQUESTS -->

        <div class="table">


            <div class="table-title">

                Recent Requests

            </div>


            <?php if (count($recent_requests) === 0): ?>


                <div class="empty">

                    <strong>
                        No requests yet
                    </strong>

                    <span>
                        Find a donation and submit your first request.
                    </span>

                </div>


            <?php else: ?>


                <div class="table-scroll">

                    <table>


                        <tr>

                            <th>
                                Item
                            </th>

                            <th>
                                Quantity
                            </th>

                            <th>
                                Requested
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>


                        <?php foreach ($recent_requests as $request): ?>


                            <?php

                            $request_title =
                                htmlspecialchars(
                                    $request["title"] ?? "Donation"
                                );

                            $request_quantity =
                                htmlspecialchars(
                                    $request["quantity"] ?? "0"
                                );

                            $request_status =
                                $request["status"] ?? "pending";

                            $request_date =
                                !empty($request["created_at"])
                                ? date(
                                    "M d, Y",
                                    strtotime($request["created_at"])
                                )
                                : "Unknown";

                            ?>


                            <tr>


                                <td>

                                    <?= $request_title ?>

                                </td>


                                <td>

                                    <?= $request_quantity ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars($request_date) ?>

                                </td>


                                <td>

                                    <span
                                        class="status <?= htmlspecialchars($request_status) ?>"
                                    >

                                        <?= ucfirst(
                                            htmlspecialchars($request_status)
                                        ) ?>

                                    </span>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </table>

                </div>


            <?php endif; ?>


        </div>


    </div>


</main>


</body>

</html>