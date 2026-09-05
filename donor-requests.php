<?php

session_start();

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/helpers.php";
require_once __DIR__ . "/config/csrf.php";

/*
|--------------------------------------------------------------------------
| Check Login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit;
}

$donor_id = intval($_SESSION["user_id"]);


/*
|--------------------------------------------------------------------------
| Handle Approve / Reject
|--------------------------------------------------------------------------
|
| Approving deducts the requested quantity from what's left. When the
| remaining quantity hits zero the donation is auto-completed (handled
| by recompute_donation_status). A request can't be approved for more
| than is still available.
|
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_verify()) {
        $_SESSION["dr_flash"] = ["type" => "error", "msg" => "Security check failed. Please try again."];
        header("Location: donor-requests.php");
        exit;
    }

    $request_id = intval($_POST["request_id"] ?? 0);
    $action = $_POST["action"] ?? "";

    if ($request_id > 0 && in_array($action, ["approved", "rejected"], true)) {

        // Load the request and confirm this donor owns the donation.
        $lookup = $conn->prepare("
            SELECT dr.id, dr.donation_id, dr.quantity, dr.status,
                   d.quantity AS total_qty
            FROM donation_requests dr
            INNER JOIN donations d ON dr.donation_id = d.id
            WHERE dr.id = ? AND d.donor_id = ?
            LIMIT 1
        ");
        $lookup->bind_param("ii", $request_id, $donor_id);
        $lookup->execute();
        $req = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        if (!$req) {

            $_SESSION["dr_flash"] = ["type" => "error", "msg" => "That request was not found."];

        } elseif ($action === "approved") {

            $donation_id = (int) $req["donation_id"];
            $remaining   = donation_remaining($conn, $donation_id, (int) $req["total_qty"]);

            if ($req["status"] === "approved") {

                $_SESSION["dr_flash"] = ["type" => "error", "msg" => "That request is already approved."];

            } elseif ((int) $req["quantity"] > $remaining) {

                $_SESSION["dr_flash"] = [
                    "type" => "error",
                    "msg"  => "Cannot approve: only " . $remaining . " left, but this request is for "
                              . (int) $req["quantity"] . ".",
                ];

            } else {

                $u = $conn->prepare("UPDATE donation_requests SET status = 'approved' WHERE id = ?");
                $u->bind_param("i", $request_id);
                $u->execute();
                $u->close();

                recompute_donation_status($conn, $donation_id);

                $left = donation_remaining($conn, $donation_id, (int) $req["total_qty"]);

                $_SESSION["dr_flash"] = [
                    "type" => "success",
                    "msg"  => "Request approved. " . $left . " left"
                              . ($left === 0
                                    ? " — fully reserved, now awaiting the recipient(s) to confirm receipt."
                                    : "."),
                ];
            }

        } else { // rejected

            $u = $conn->prepare("UPDATE donation_requests SET status = 'rejected' WHERE id = ?");
            $u->bind_param("i", $request_id);
            $u->execute();
            $u->close();

            // Freeing an approved request's quantity may re-open the donation.
            recompute_donation_status($conn, (int) $req["donation_id"]);

            $_SESSION["dr_flash"] = ["type" => "success", "msg" => "Request rejected."];
        }
    }

    header("Location: donor-requests.php");
    exit;
}


/*
| Pull any one-time notice set above (shown once, then cleared).
*/
$dr_flash = $_SESSION["dr_flash"] ?? null;
unset($_SESSION["dr_flash"]);


/*
|--------------------------------------------------------------------------
| Get Donor Requests
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        dr.id AS request_id,
        dr.donation_id,
        dr.quantity AS requested_quantity,
        dr.beneficiaries,
        dr.purpose,
        dr.message,
        dr.collection_date,
        dr.status,
        dr.created_at,

        d.title AS donation_title,
        d.quantity AS available_quantity,
        d.location,

        u.name AS recipient_name,
        u.email AS recipient_email,
        u.phone AS recipient_phone,

        c.name AS category_name

    FROM donation_requests dr

    INNER JOIN donations d
        ON dr.donation_id = d.id

    LEFT JOIN users u
        ON dr.recipient_id = u.id

    LEFT JOIN categories c
        ON d.category_id = c.id

    WHERE d.donor_id = ?

    ORDER BY dr.created_at DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Sorry, something went wrong loading this page. Please try again.");
}

$stmt->bind_param("i", $donor_id);

$stmt->execute();

$result = $stmt->get_result();

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Donation Requests | DONATE+</title>


<style>

@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');


:root {

    --green:#1f5b3a;
    --green2:#2f744a;
    --cream:#fbfaf6;
    --ink:#171915;
    --muted:#697069;
    --border:#e8e6df;

}


* {
    box-sizing:border-box;
}


body {

    margin:0;

    font-family:'DM Sans', sans-serif;

    background:var(--cream);

    color:var(--ink);

}


a {
    text-decoration:none;
}


.container {

    max-width:1200px;

    margin:auto;

    padding:0 28px;

}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

header {

    height:74px;

    background:#fff;

    border-bottom:1px solid var(--border);

}


.nav {

    height:100%;

    display:flex;

    align-items:center;

    justify-content:space-between;

}


.brand {

    display:flex;

    align-items:center;

    gap:10px;

    color:var(--green);

    font-weight:800;

}


.brandmark {

    width:38px;

    height:38px;

    border:2px solid var(--green);

    border-radius:50%;

    display:grid;

    place-items:center;

    font-size:19px;

}


.brand small {

    display:block;

    font-size:9px;

    color:#7d857d;

    letter-spacing:.6px;

    font-weight:600;

}


.dashboard-btn {

    background:var(--green);

    color:#fff;

    padding:12px 20px;

    border-radius:10px;

    font-weight:700;

    font-size:14px;

}


/*
|--------------------------------------------------------------------------
| Main
|--------------------------------------------------------------------------
*/

main {

    padding:70px 0;

}


.back {

    color:var(--green);

    font-weight:700;

    font-size:13px;

}


.eyebrow {

    margin-top:35px;

    color:var(--green);

    font-size:12px;

    font-weight:800;

    letter-spacing:2px;

    text-transform:uppercase;

}


h1 {

    font-family:'Playfair Display', serif;

    font-size:48px;

    margin:12px 0 8px;

}


.subtitle {

    color:var(--muted);

    margin-bottom:35px;

}


/*
|--------------------------------------------------------------------------
| Request Card
|--------------------------------------------------------------------------
*/

.request-card {

    background:#fff;

    border:1px solid #ebe7de;

    border-radius:16px;

    margin-bottom:25px;

    overflow:hidden;

    box-shadow:0 4px 15px rgba(0,0,0,.03);

}


.card-header {

    padding:22px 25px;

    border-bottom:1px solid #eeeae2;

    display:flex;

    justify-content:space-between;

    align-items:flex-start;

    gap:20px;

}


.card-title {

    margin:0;

    font-family:'Playfair Display', serif;

    font-size:25px;

}


.category {

    display:inline-block;

    margin-top:8px;

    padding:5px 10px;

    border-radius:20px;

    background:#e8f4e8;

    color:#27713e;

    font-size:11px;

    font-weight:700;

}


.status {

    display:inline-block;

    padding:7px 12px;

    border-radius:20px;

    font-size:11px;

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


.status.rejected {

    background:#fde8e8;

    color:#a33a3a;

}


.status.completed {

    background:#e9edf7;

    color:#40517b;

}


/*
|--------------------------------------------------------------------------
| Body
|--------------------------------------------------------------------------
*/

.card-body {

    padding:25px;

}


.info-grid {

    display:grid;

    grid-template-columns:repeat(3, 1fr);

    gap:20px;

    margin-bottom:25px;

}


.info-box {

    background:#faf9f5;

    border-radius:10px;

    padding:15px;

}


.info-label {

    font-size:11px;

    color:#7a817a;

    margin-bottom:6px;

}


.info-value {

    font-size:15px;

    font-weight:700;

}


.section-title {

    font-size:14px;

    font-weight:800;

    margin-bottom:8px;

}


.text {

    color:#697069;

    line-height:1.7;

    font-size:14px;

    margin-bottom:20px;

}


/*
|--------------------------------------------------------------------------
| Recipient
|--------------------------------------------------------------------------
*/

.recipient {

    border-top:1px solid #eeeae2;

    padding-top:20px;

    margin-top:10px;

}


.recipient-name {

    font-weight:700;

    font-size:16px;

    margin-bottom:5px;

}


.recipient-contact {

    color:#697069;

    font-size:13px;

    line-height:1.7;

}


/*
|--------------------------------------------------------------------------
| Buttons
|--------------------------------------------------------------------------
*/

.actions {

    display:flex;

    gap:12px;

    margin-top:25px;

}


.action-btn {

    border:0;

    padding:12px 20px;

    border-radius:9px;

    font-family:'DM Sans',sans-serif;

    font-weight:700;

    cursor:pointer;

    font-size:13px;

}


.approve-btn {

    background:var(--green);

    color:#fff;

}


.approve-btn:hover {

    background:#17482e;

}


.reject-btn {

    background:#f8e5e5;

    color:#a33a3a;

}


.reject-btn:hover {

    background:#f3d5d5;

}


/*
|--------------------------------------------------------------------------
| Empty
|--------------------------------------------------------------------------
*/

.empty {

    background:#fff;

    border:1px solid #ebe7de;

    border-radius:16px;

    padding:60px 30px;

    text-align:center;

}


.empty-icon {

    font-size:40px;

    margin-bottom:15px;

}


.empty h2 {

    font-family:'Playfair Display',serif;

    margin:0 0 10px;

}


.empty p {

    color:var(--muted);

}


/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media(max-width:800px) {

    h1 {

        font-size:38px;

    }


    .info-grid {

        grid-template-columns:1fr;

    }


    .card-header {

        flex-direction:column;

    }


    .actions {

        flex-direction:column;

    }


    .action-btn {

        width:100%;

    }

}


</style>

</head>


<body>


<!-- HEADER -->

<header>

    <div class="container nav">


        <a href="donor-dashboard.php"
           class="brand">

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


        <a href="donor-dashboard.php"
           class="dashboard-btn">

            My Dashboard

        </a>


    </div>

</header>



<!-- MAIN -->

<main class="container">


    <div class="eyebrow">

        DONOR PORTAL

    </div>


    <h1>

        Donation Requests

    </h1>


    <p class="subtitle">

        Review requests from people who need your donated items.

    </p>


    <?php if ($dr_flash): ?>
        <div class="ud-flash" style="margin:0 0 22px;padding:13px 16px;border-radius:11px;font-size:14px;font-weight:600;display:flex;align-items:center;justify-content:space-between;gap:14px;<?= $dr_flash["type"] === "success" ? "background:#e8f4e8;color:#27713e;border:1px solid #cfe6d2" : "background:#fde8e8;color:#a33a3a;border:1px solid #f2cccc" ?>">
            <span><?= htmlspecialchars($dr_flash["msg"]) ?></span>
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



<?php if ($result->num_rows === 0): ?>


    <!-- EMPTY -->

    <div class="empty">

        <div class="empty-icon">
            ♡
        </div>

        <h2>
            No Requests Yet
        </h2>

        <p>
            You don't have any requests for your donations yet.
        </p>

    </div>


<?php else: ?>


    <?php while ($request = $result->fetch_assoc()): ?>


        <?php

        $request_id =
            intval($request["request_id"] ?? 0);

        $status =
            $request["status"] ?? "pending";

        $donation_title =
            htmlspecialchars(
                $request["donation_title"] ?? "Donation"
            );

        $category =
            htmlspecialchars(
                $request["category_name"] ?? "Other"
            );

        $requested_quantity =
            htmlspecialchars(
                $request["requested_quantity"] ?? "0"
            );

        $available_quantity =
            htmlspecialchars(
                $request["available_quantity"] ?? "0"
            );

        $remaining_quantity = donation_remaining(
            $conn,
            (int) ($request["donation_id"] ?? 0),
            (int) ($request["available_quantity"] ?? 0)
        );

        $quantity_unit =
            htmlspecialchars(
                $request["quantity_unit"] ?? "pieces"
            );

        $beneficiaries =
            htmlspecialchars(
                $request["beneficiaries"] ?? "Not specified"
            );

        $purpose =
            htmlspecialchars(
                $request["purpose"] ?? "Not specified"
            );

        $message =
            htmlspecialchars(
                $request["message"] ?? "No message provided."
            );

        $collection_date =
            htmlspecialchars(
                $request["collection_date"] ?? ""
            );

        $recipient_name =
            htmlspecialchars(
                $request["recipient_name"] ?? "Recipient"
            );

        $recipient_email =
            htmlspecialchars(
                $request["recipient_email"] ?? "Not provided"
            );

        $recipient_phone =
            htmlspecialchars(
                $request["recipient_phone"] ?? "Not provided"
            );

        $location =
            htmlspecialchars(
                $request["location"] ?? "Not specified"
            );

        ?>


        <!-- REQUEST CARD -->

        <div class="request-card">


            <!-- CARD HEADER -->

            <div class="card-header">


                <div>

                    <h2 class="card-title">

                        <?= $donation_title ?>

                    </h2>


                    <span class="category">

                        <?= $category ?>

                    </span>

                </div>


                <span class="status <?= htmlspecialchars($status) ?>">

                    <?= ucfirst(htmlspecialchars($status)) ?>

                </span>


            </div>



            <!-- CARD BODY -->

            <div class="card-body">


                <!-- INFORMATION -->

                <div class="info-grid">


                    <div class="info-box">

                        <div class="info-label">

                            Requested Quantity

                        </div>

                        <div class="info-value">

                            <?= $requested_quantity ?>
                            <?= $quantity_unit ?>

                        </div>

                    </div>



                    <div class="info-box">

                        <div class="info-label">

                            Quantity Left

                        </div>

                        <div class="info-value">

                            <?= (int) $remaining_quantity ?>
                            <?= $quantity_unit ?>
                            <span style="color:#9aa29a;font-weight:400">
                                of <?= $available_quantity ?>
                            </span>

                        </div>

                    </div>



                    <div class="info-box">

                        <div class="info-label">

                            Beneficiaries

                        </div>

                        <div class="info-value">

                            <?= $beneficiaries ?>

                        </div>

                    </div>


                </div>



                <!-- PURPOSE -->

                <div class="section-title">

                    Purpose

                </div>


                <div class="text">

                    <?= $purpose ?>

                </div>



                <!-- PREFERRED COLLECTION DATE -->

                <div class="section-title">

                    Preferred Collection Date

                </div>


                <div class="text">

                    <?= $collection_date !== "" ? $collection_date : "Not specified" ?>

                </div>



                <!-- MESSAGE -->

                <div class="section-title">

                    Message

                </div>


                <div class="text">

                    <?= nl2br($message) ?>

                </div>



                <!-- LOCATION -->

                <div class="section-title">

                    Collection Location

                </div>


                <div class="text">

                    📍 <?= $location ?>

                </div>



                <!-- RECIPIENT -->

                <div class="recipient">


                    <div class="section-title">

                        Requested By

                    </div>


                    <div class="recipient-name">

                        <?= $recipient_name ?>

                    </div>


                    <div class="recipient-contact">

                        Email:
                        <?= $recipient_email ?>

                        <br>

                        Phone:
                        <?= $recipient_phone ?>

                    </div>


                </div>



                <!-- ACTIONS -->

                <?php if ($status === "pending"): ?>


                    <div class="actions">


                        <!-- APPROVE -->

                        <form method="POST">

                            <?php csrf_field(); ?>

                            <input
                                type="hidden"
                                name="request_id"
                                value="<?= $request_id ?>"
                            >


                            <input
                                type="hidden"
                                name="action"
                                value="approved"
                            >


                            <button
                                type="submit"
                                class="action-btn approve-btn"
                            >

                                ✓ Approve Request

                            </button>

                        </form>



                        <!-- REJECT -->

                        <form method="POST">

                            <?php csrf_field(); ?>

                            <input
                                type="hidden"
                                name="request_id"
                                value="<?= $request_id ?>"
                            >


                            <input
                                type="hidden"
                                name="action"
                                value="rejected"
                            >


                            <button
                                type="submit"
                                class="action-btn reject-btn"
                            >

                                ✕ Reject Request

                            </button>

                        </form>


                    </div>


                <?php elseif ($status === "approved"): ?>


                    <div class="actions">

                        <span class="status approved">

                            ✓ You approved this request

                        </span>

                    </div>


                <?php elseif ($status === "rejected"): ?>


                    <div class="actions">

                        <span class="status rejected">

                            ✕ You rejected this request

                        </span>

                    </div>


                <?php endif; ?>


            </div>


        </div>


    <?php endwhile; ?>


<?php endif; ?>


</main>


</body>

</html>