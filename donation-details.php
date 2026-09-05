<?php

session_start();

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/helpers.php";

/*
|--------------------------------------------------------------------------
| Check Donation ID
|--------------------------------------------------------------------------
*/

$donation_id = intval($_GET["id"] ?? 0);

if ($donation_id <= 0) {
    die("Invalid donation ID.");
}


/*
|--------------------------------------------------------------------------
| Get Donation Details
|--------------------------------------------------------------------------
*/

$has_expiry = column_exists($conn, "donations", "expiry_date");
$expiry_col = $has_expiry ? "d.expiry_date," : "";

$has_campaign = column_exists($conn, "donations", "campaign_id");
$campaign_col = $has_campaign ? "d.campaign_id," : "";

$sql = "
    SELECT
        d.id,
        d.donor_id,
        d.title,
        d.description,
        d.quantity,
        d.unit,
        d.item_condition,
        {$expiry_col}
        {$campaign_col}
        d.location,
        d.image,
        d.status,

        c.name AS category_name,

        u.name AS donor_name

    FROM donations d

    LEFT JOIN categories c
        ON d.category_id = c.id

    LEFT JOIN users u
        ON d.donor_id = u.id

    WHERE d.id = ?

    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Sorry, something went wrong loading this page. Please try again.");
}

$stmt->bind_param("i", $donation_id);

$stmt->execute();

$result = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Donation Not Found
|--------------------------------------------------------------------------
*/

if ($result->num_rows === 0) {
    die("Donation not found.");
}

$donation = $result->fetch_assoc();


/*
|--------------------------------------------------------------------------
| Safe Output
|--------------------------------------------------------------------------
*/

$title = htmlspecialchars($donation["title"] ?? "");

$description = htmlspecialchars(
    $donation["description"] ?? "No description provided."
);

$quantity = htmlspecialchars(
    $donation["quantity"] ?? "0"
);

$quantity_unit = htmlspecialchars(
    $donation["unit"] ?? ""
);
$item_condition = htmlspecialchars(
    $donation["item_condition"] ?? "Not specified"
);

$location = htmlspecialchars(
    $donation["location"] ?? "Not specified"
);

$category_name = htmlspecialchars(
    $donation["category_name"] ?? "Other"
);

$donor_name = htmlspecialchars(
    $donation["donor_name"] ?? "Verified Donor"
);

$status = htmlspecialchars(
    $donation["status"] ?? "available"
);

// Is the current viewer the donor of this item?
$viewer_id = (int) ($_SESSION["user_id"] ?? 0);
$is_own_donation = $viewer_id > 0 && (int) ($donation["donor_id"] ?? 0) === $viewer_id;

// Campaign contributions are collected through the drive, not requested here.
$is_campaign_item = !empty($donation["campaign_id"]);

$expiry_display = "";
$is_expired = false;
if (!empty($donation["expiry_date"])) {
    $expiry_ts = strtotime($donation["expiry_date"]);
    if ($expiry_ts) {
        $expiry_display = date("d M Y", $expiry_ts);
    }
    $is_expired = $donation["expiry_date"] < date("Y-m-d");
}

$total_quantity = (int) ($donation["quantity"] ?? 0);
$remaining_quantity = donation_remaining($conn, $donation_id, $total_quantity);


/*
|--------------------------------------------------------------------------
| Image
|--------------------------------------------------------------------------
*/

$image = "";

if (!empty($donation["image"])) {

    $image = "uploads/donations/" . htmlspecialchars(
        $donation["image"]
    );

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    <?= $title ?> | DONATE+
</title>


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
    background:var(--cream);
    color:var(--ink);
}

.container {
    max-width:1180px;
    margin:auto;
    padding:0 28px;
}

a {
    text-decoration:none;
}

.serif {
    font-family:'Playfair Display',serif;
}


/* HEADER */

header {
    height:74px;
    background:rgba(255,255,255,.94);
    border-bottom:1px solid #e8e6df;
}

.nav {
    height:100%;
    display:flex;
    align-items:center;
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


/* MAIN */

.section {
    padding:85px 0;
}

.back {
    color:var(--green);
    font-weight:700;
    font-size:13px;
}

.details {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:75px;
    align-items:center;
    margin-top:30px;
}


/* IMAGE */

.image-card {
    background:#fff;
    border:1px solid #eee9df;
    border-radius:18px;
    overflow:hidden;
}

.image-box {
    height:420px;
    background:#ebe9e1;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#777;
}

.image-box img {
    width:100%;
    height:100%;
    object-fit:cover;
}


/* INFORMATION */

.status {
    display:inline-block;
    padding:6px 10px;
    border-radius:20px;
    font-size:11px;
    font-weight:700;
}

.available {
    background:#e8f4e8;
    color:#27713e;
}

.requested {
    background:#fff0dc;
    color:#b56300;
}

.completed {
    background:#e9edf7;
    color:#40517b;
}

h1 {
    font-size:48px;
    line-height:1.1;
    margin:15px 0;
}

.summary {
    color:#6f766f;
    line-height:1.8;
}

.checks {
    margin:25px 0;
    display:grid;
    gap:12px;
    font-size:13px;
}

.check {
    display:flex;
    gap:10px;
    align-items:center;
}

.check i {
    width:22px;
    height:22px;
    border-radius:50%;
    background:#e9f2e7;
    color:var(--green);
    display:grid;
    place-items:center;
    font-style:normal;
}

.description {
    font-size:14px;
    color:#697069;
    line-height:1.8;
}


/* BUTTON */

.btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:13px 20px;
    border-radius:10px;
    font-weight:700;
    margin-top:20px;
}

.btn-green {
    background:var(--green);
    color:#fff;
}

.btn-green:hover {
    background:#17482e;
}

.btn-disabled {
    background:#999;
    color:#fff;
    cursor:not-allowed;
}


/* MOBILE */

@media(max-width:900px) {

    .details {
        grid-template-columns:1fr;
        gap:35px;
    }

    h1 {
        font-size:40px;
    }

}

@media(max-width:600px) {

    .container {
        padding:0 18px;
    }

    .section {
        padding:50px 0;
    }

    .image-box {
        height:300px;
    }

    h1 {
        font-size:34px;
    }

}

</style>

</head>


<body>


<header>

    <div class="container nav">

        <a class="brand" href="index.html">

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

    </div>

</header>



<main class="container section">


    <!-- BACK -->

    <a href="find-donations.php"
       class="back">

        ← Back to donations

    </a>



    <div class="details">


        <!-- IMAGE -->

        <div class="image-card">

            <div class="image-box">

                <?php if (!empty($image)): ?>

                    <img
                        src="<?= $image ?>"
                        alt="<?= $title ?>"
                    >

                <?php else: ?>

                    <span>
                        No image available
                    </span>

                <?php endif; ?>

            </div>

        </div>



        <!-- DETAILS -->

        <div>


            <!-- STATUS -->

            <?php

            $status_class = "available";

            if ($status === "requested") {
                $status_class = "requested";
            }

            if ($status === "completed") {
                $status_class = "completed";
            }

            ?>

            <span class="status <?= $status_class ?>">

                <?= ucfirst($status) ?>

            </span>



            <!-- TITLE -->

            <h1 class="serif">

                <?= $title ?>

            </h1>



            <!-- SUMMARY -->

            <p class="summary">

                <strong><?= (int) $remaining_quantity ?></strong>
                <?= $quantity_unit ?> left
                (of <?= $total_quantity ?>)

                ·

                <?= $item_condition ?>

                ·

                <?= $location ?>

            </p>



            <!-- CHECKS -->

            <div class="checks">


                <div class="check">

                    <i>✓</i>

                    Category:
                    <?= $category_name ?>

                </div>


                <?php if ($expiry_display !== ""): ?>

                <div class="check">

                    <i>⏳</i>

                    Best before:
                    <?= htmlspecialchars($expiry_display) ?>
                    <?php if ($is_expired): ?>
                        <strong style="color:#b23b3b">(expired)</strong>
                    <?php endif; ?>

                </div>

                <?php endif; ?>



                <div class="check">

                    <i>✓</i>

                    Donor:
                    <?= $donor_name ?>

                </div>



                <div class="check">

                    <i>✓</i>

                    Available for collection

                </div>


            </div>



            <!-- DESCRIPTION -->

            <p class="description">

                <?= nl2br($description) ?>

            </p>



            <!-- REQUEST BUTTON -->

            <?php if ($is_campaign_item): ?>

                <span class="btn btn-disabled">

                    Part of a campaign drive

                </span>

                <div style="margin-top:10px">
                    <a href="campaign.php?id=<?= (int) $donation["campaign_id"] ?>"
                       style="color:#1f5b3a;font-weight:700">View the campaign →</a>
                </div>

            <?php elseif ($is_own_donation): ?>

                <span class="btn btn-disabled">

                    This is your donation

                </span>

            <?php elseif ($status === "available" && $remaining_quantity > 0 && !$is_expired): ?>

                <a
                    class="btn btn-green"
                    href="request-donation.php?id=<?= $donation_id ?>"
                >

                    Request This Donation →

                </a>

            <?php elseif ($is_expired): ?>

                <span class="btn btn-disabled">

                    Expired — Not Available

                </span>

            <?php else: ?>

                <span class="btn btn-disabled">

                    Donation Not Available

                </span>

            <?php endif; ?>


        </div>


    </div>


</main>


</body>

</html>