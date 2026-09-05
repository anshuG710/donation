<?php

session_start();

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/helpers.php";
require_once __DIR__ . "/config/csrf.php";


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


/*
|--------------------------------------------------------------------------
| HANDLE "MARK AS RECEIVED"
|--------------------------------------------------------------------------
|
| The recipient confirms they actually received an approved donation. Only
| the recipient who owns the request can do this, and only while it is still
| 'approved'. Confirming moves it to 'completed' and recomputes the
| donation's status (the donation completes once everything is received).
|
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_verify()) {
        $_SESSION["rr_flash"] = ["type" => "error", "msg" => "Security check failed. Please try again."];
        header("Location: recipient-requests.php");
        exit();
    }

    $post_request_id = (int) ($_POST["request_id"] ?? 0);
    $post_action     = $_POST["action"] ?? "";

    if ($post_request_id > 0 && $post_action === "received") {

        $lookup = $conn->prepare("
            SELECT id, donation_id, status
            FROM donation_requests
            WHERE id = ? AND recipient_id = ?
            LIMIT 1
        ");
        $lookup->bind_param("ii", $post_request_id, $recipient_id);
        $lookup->execute();
        $req = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        if (!$req) {
            $_SESSION["rr_flash"] = ["type" => "error", "msg" => "That request was not found."];
        } elseif ($req["status"] !== "approved") {
            $_SESSION["rr_flash"] = ["type" => "error", "msg" => "Only an approved request can be marked as received."];
        } else {
            $upd = $conn->prepare("UPDATE donation_requests SET status = 'completed' WHERE id = ?");
            $upd->bind_param("i", $post_request_id);
            $upd->execute();
            $upd->close();

            recompute_donation_status($conn, (int) $req["donation_id"]);

            $_SESSION["rr_flash"] = ["type" => "success", "msg" => "Thank you! Marked as received — this request is now complete."];
        }
    }

    header("Location: recipient-requests.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| GET RECIPIENT REQUESTS
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

        u.name AS donor_name,
        u.email AS donor_email,
        u.phone AS donor_phone,

        c.name AS category_name

    FROM donation_requests dr

    INNER JOIN donations d
        ON dr.donation_id = d.id

    LEFT JOIN users u
        ON d.donor_id = u.id

    LEFT JOIN categories c
        ON d.category_id = c.id

    WHERE dr.recipient_id = ?

    ORDER BY dr.created_at DESC

    LIMIT ?, ?
";

// Total for pagination.
$rc = $conn->prepare("SELECT COUNT(*) AS c FROM donation_requests WHERE recipient_id = ?");
$rc->bind_param("i", $recipient_id);
$rc->execute();
$rr_total = (int) ($rc->get_result()->fetch_assoc()["c"] ?? 0);
$rc->close();

$pg = paginate($rr_total, 8);

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Sorry, something went wrong loading this page. Please try again.");
}

$stmt->bind_param("iii", $recipient_id, $pg["offset"], $pg["per_page"]);

$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>My Requests | DONATE+</title>

<style>

@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');

:root {
    --green:#1f5b3a;
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
    font-family:'DM Sans',sans-serif;
    background:var(--cream);
    color:var(--ink);
}

.container {
    max-width:1100px;
    margin:auto;
    padding:0 28px;
}

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
    text-decoration:none;
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
}

.dashboard-btn {
    background:var(--green);
    color:#fff;
    padding:11px 18px;
    border-radius:9px;
    text-decoration:none;
    font-weight:700;
    font-size:13px;
}

main {
    padding:65px 0;
}

.back {
    color:var(--green);
    text-decoration:none;
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
    font-family:'Playfair Display',serif;
    font-size:48px;
    margin:10px 0;
}

.subtitle {
    color:var(--muted);
    margin-bottom:35px;
}

.card {
    background:#fff;
    border:1px solid var(--border);
    border-radius:16px;
    margin-bottom:25px;
    overflow:hidden;
}

.card-header {
    padding:22px 25px;
    border-bottom:1px solid var(--border);
    display:flex;
    justify-content:space-between;
    gap:20px;
}

.title {
    font-family:'Playfair Display',serif;
    font-size:26px;
    margin:0;
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
    height:max-content;
    padding:7px 12px;
    border-radius:20px;
    font-size:11px;
    font-weight:700;
}

.pending {
    background:#fff0dc;
    color:#b56300;
}

.approved {
    background:#e8f4e8;
    color:#27713e;
}

.rejected {
    background:#fde8e8;
    color:#a33a3a;
}

.completed {
    background:#e9edf7;
    color:#40517b;
}

.card-body {
    padding:25px;
}

.grid {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:18px;
    margin-bottom:25px;
}

.box {
    background:#faf9f5;
    border-radius:10px;
    padding:15px;
}

.label {
    color:#7a817a;
    font-size:11px;
    margin-bottom:6px;
}

.value {
    font-weight:700;
    font-size:15px;
}

.section-title {
    font-size:14px;
    font-weight:800;
    margin-bottom:7px;
}

.text {
    color:var(--muted);
    font-size:14px;
    line-height:1.7;
    margin-bottom:20px;
}

.donor {
    border-top:1px solid var(--border);
    padding-top:20px;
}

.donor-name {
    font-weight:700;
    margin-bottom:5px;
}

.empty {
    background:#fff;
    border:1px solid var(--border);
    border-radius:16px;
    padding:60px 25px;
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

@media(max-width:750px) {

    h1 {
        font-size:38px;
    }

    .grid {
        grid-template-columns:1fr;
    }

    .card-header {
        flex-direction:column;
    }

}

</style>

</head>

<body>


<header>

    <div class="container nav">

        <a href="index.html" class="brand">

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

        <a href="recipient-dashboard.php"
           class="dashboard-btn">

            My Dashboard

        </a>

    </div>

</header>


<main class="container">


    <div class="eyebrow">
        RECIPIENT PORTAL
    </div>


    <h1>
        My Requests
    </h1>


    <p class="subtitle">
        Track the donations you have requested.
    </p>


    <?php if (!empty($_SESSION["rr_flash"])):
        $fl = $_SESSION["rr_flash"];
        unset($_SESSION["rr_flash"]);
        $fl_bg = $fl["type"] === "success" ? "#e8f4e8" : "#fde8e8";
        $fl_fg = $fl["type"] === "success" ? "#27713e" : "#a33a3a";
    ?>
        <div class="ud-flash" style="background:<?= $fl_bg ?>;color:<?= $fl_fg ?>;padding:14px 18px;border-radius:10px;margin-bottom:22px;font-weight:600;font-size:14px;display:flex;align-items:center;justify-content:space-between;gap:14px">
            <span><?= htmlspecialchars($fl["msg"]) ?></span>
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

    <div class="empty">

        <div class="empty-icon">
            ♡
        </div>

        <h2>
            No Requests Yet
        </h2>

        <p>
            You haven't requested any donations yet.
        </p>

    </div>

<?php else: ?>


    <?php while ($request = $result->fetch_assoc()): ?>

        <?php

        $title = htmlspecialchars(
            $request["donation_title"] ?? "Donation"
        );

        $category = htmlspecialchars(
            $request["category_name"] ?? "Other"
        );

        $requested_quantity = htmlspecialchars(
            $request["requested_quantity"] ?? "0"
        );

        // What is actually still left on the donation now (not the original total).
        $available_quantity = (int) donation_remaining(
            $conn,
            (int) $request["donation_id"],
            (int) ($request["available_quantity"] ?? 0)
        );

        $beneficiaries = htmlspecialchars(
            $request["beneficiaries"] ?? "0"
        );

        $purpose = htmlspecialchars(
            $request["purpose"] ?? ""
        );

        $message = htmlspecialchars(
            $request["message"] ?? ""
        );

        $collection_date = htmlspecialchars(
            $request["collection_date"] ?? ""
        );

        $location = htmlspecialchars(
            $request["location"] ?? "Not specified"
        );

        $donor_name = htmlspecialchars(
            $request["donor_name"] ?? "Donor"
        );

        $donor_email = htmlspecialchars(
            $request["donor_email"] ?? "Not provided"
        );

        $donor_phone = htmlspecialchars(
            $request["donor_phone"] ?? "Not provided"
        );

        $status = $request["status"] ?? "pending";

        // The donor's contact details are only revealed once they approve.
        $contact_visible = in_array($status, ["approved", "completed"], true);

        ?>


        <div class="card">


            <div class="card-header">

                <div>

                    <h2 class="title">
                        <?= $title ?>
                    </h2>

                    <span class="category">
                        <?= $category ?>
                    </span>

                </div>


                <span class="status <?= htmlspecialchars($status) ?>">

                    <?= ucfirst(htmlspecialchars($status)) ?>

                </span>

            </div>


            <div class="card-body">


                <div class="grid">


                    <div class="box">

                        <div class="label">
                            Requested Quantity
                        </div>

                        <div class="value">
                            <?= $requested_quantity ?>
                        </div>

                    </div>


                    <div class="box">

                        <div class="label">
                            Remaining Available
                        </div>

                        <div class="value">
                            <?= $available_quantity ?>
                        </div>

                    </div>


                    <div class="box">

                        <div class="label">
                            Beneficiaries
                        </div>

                        <div class="value">
                            <?= $beneficiaries ?>
                        </div>

                    </div>


                </div>


                <div class="section-title">
                    Purpose
                </div>

                <div class="text">
                    <?= $purpose ?>
                </div>


                <div class="section-title">
                    Preferred Collection Date
                </div>

                <div class="text">
                    <?= $collection_date !== "" ? $collection_date : "Not specified" ?>
                </div>


                <div class="section-title">
                    Message
                </div>

                <div class="text">
                    <?= nl2br($message) ?>
                </div>


                <div class="section-title">
                    Collection Location
                </div>

                <div class="text">
                    📍 <?= $location ?>
                </div>


                <div class="donor">

                    <div class="section-title">
                        Donor
                    </div>

                    <div class="donor-name">
                        <?= $donor_name ?>
                    </div>

                    <?php if ($contact_visible): ?>

                    <div class="text">

                        Email:
                        <?= $donor_email ?>

                        <br>

                        Phone:
                        <?= $donor_phone ?>

                    </div>

                    <?php else: ?>

                    <div class="text" style="color:#8a8f8a">
                        📞 Contact details are shared once the donor approves your request.
                    </div>

                    <?php endif; ?>

                </div>


                <?php if ($status === "approved"): ?>

                    <form method="post" style="margin-top:22px">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="request_id" value="<?= (int) $request["request_id"] ?>">
                        <input type="hidden" name="action" value="received">
                        <button type="submit"
                            style="background:#1f5b3a;color:#fff;border:0;padding:12px 22px;border-radius:10px;font-weight:700;cursor:pointer"
                            onclick="return confirm('Confirm that you have received this donation?');">
                            ✓ Mark as Received
                        </button>
                    </form>

                <?php elseif ($status === "completed"): ?>

                    <div style="margin-top:22px;color:#40517b;font-weight:700;font-size:14px">
                        ✓ You confirmed receipt — thank you!
                    </div>

                <?php endif; ?>


            </div>

        </div>


    <?php endwhile; ?>

    <?php pagination_links($pg); ?>

    <style>
    .pagination{display:flex;gap:8px;justify-content:center;margin:26px 0;flex-wrap:wrap}
    .pagination a,.pagination span{padding:8px 13px;border-radius:8px;border:1px solid #e0ddd4;color:#1f5b3a;font-weight:600;font-size:13px;text-decoration:none}
    .pagination .current{background:#1f5b3a;color:#fff;border-color:#1f5b3a}
    .pagination a:hover{background:#eef4ea}
    </style>


<?php endif; ?>


</main>

</body>

</html>