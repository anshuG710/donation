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
    exit;
}

$recipient_id = (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| GET DONATION ID
|--------------------------------------------------------------------------
*/

$donation_id = (int) ($_GET["id"] ?? $_POST["donation_id"] ?? 0);

if ($donation_id <= 0) {
    die("Invalid donation ID.");
}


/*
|--------------------------------------------------------------------------
| GET DONATION DETAILS
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
        d.quantity,
        d.item_condition,
        d.location,
        d.status,
        {$expiry_col}
        {$campaign_col}
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

if ($result->num_rows === 0) {
    die("Donation not found.");
}

$donation = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| SAFE DONATION DATA
|--------------------------------------------------------------------------
*/

$title = htmlspecialchars($donation["title"] ?? "");

$total_quantity = (int) ($donation["quantity"] ?? 0);

// Available = original total minus what's already approved/completed.
$available_quantity = donation_remaining($conn, $donation_id, $total_quantity);

$status = $donation["status"] ?? "available";

// A donor cannot request their own donation.
$is_own_donation = ((int) ($donation["donor_id"] ?? 0) === $recipient_id);

// Items donated to a campaign are collected through the drive, not requested here.
$is_campaign_item = !empty($donation["campaign_id"]);

// Perishable items past their best-before date can no longer be requested.
$today = date("Y-m-d");
$is_expired = !empty($donation["expiry_date"]) && $donation["expiry_date"] < $today;


/*
|--------------------------------------------------------------------------
| PROCESS REQUEST
|--------------------------------------------------------------------------
*/

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $requested_quantity = (int) ($_POST["quantity"] ?? 0);

    $beneficiaries = (int) ($_POST["beneficiaries"] ?? 0);

    $purpose = trim($_POST["purpose"] ?? "");

    $collection_date = trim($_POST["collection_date"] ?? "");

    $message = trim($_POST["message"] ?? "");


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (!csrf_verify()) {

        $error = "Security check failed. Please refresh the page and try again.";

    } elseif ($is_campaign_item) {

        $error = "This item is part of a campaign drive and can't be requested individually.";

    } elseif ($is_own_donation) {

        $error = "You cannot request your own donation.";

    } elseif ($is_expired) {

        $error = "This item has passed its expiry / best-before date and can no longer be requested.";

    } elseif ($requested_quantity <= 0) {

        $error = "Please enter a valid requested quantity.";

    } elseif ($requested_quantity > $available_quantity) {

        $error = "Requested quantity cannot be greater than the available quantity.";

    } elseif ($beneficiaries <= 0) {

        $error = "Please enter the number of beneficiaries.";

    } elseif ($purpose === "") {

        $error = "Please enter the purpose.";

    } elseif ($collection_date === "") {

        $error = "Please select a preferred collection date.";

    } elseif ($collection_date < $today) {

        $error = "The collection date cannot be in the past.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | CHECK IF USER ALREADY REQUESTED THIS DONATION
        |--------------------------------------------------------------------------
        */

        $check_sql = "
            SELECT id
            FROM donation_requests
            WHERE donation_id = ?
            AND recipient_id = ?
            AND status IN ('pending', 'approved')
            LIMIT 1
        ";

        $check_stmt = $conn->prepare($check_sql);

        if (!$check_stmt) {
            die("Sorry, something went wrong loading this page. Please try again.");
        }

        $check_stmt->bind_param(
            "ii",
            $donation_id,
            $recipient_id
        );

        $check_stmt->execute();

        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {

            $error = "You have already requested this donation.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | INSERT REQUEST
            |--------------------------------------------------------------------------
            |
            | The preferred collection date is stored in its own
            | collection_date column, so the message column keeps
            | only what the recipient actually typed.
            |
            */

            $insert_sql = "
                INSERT INTO donation_requests
                (
                    donation_id,
                    recipient_id,
                    quantity,
                    beneficiaries,
                    purpose,
                    message,
                    collection_date,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ";

            $insert_stmt = $conn->prepare($insert_sql);

            if (!$insert_stmt) {
                die("Sorry, we couldn't submit your request. Please try again.");
            }

            $insert_stmt->bind_param(
                "iiiisss",
                $donation_id,
                $recipient_id,
                $requested_quantity,
                $beneficiaries,
                $purpose,
                $message,
                $collection_date
            );


            if ($insert_stmt->execute()) {

                /*
                |--------------------------------------------------------------------------
                | REQUEST SUCCESS
                |--------------------------------------------------------------------------
                */

                $success = true;

            } else {

                $error = "Failed to submit request: "
                       . $insert_stmt->error;
            }

            $insert_stmt->close();
        }

        $check_stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| IF REQUEST SUCCESSFUL
|--------------------------------------------------------------------------
*/

if ($success === true) {

    ?>

    <!DOCTYPE html>

    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta name="viewport"
              content="width=device-width, initial-scale=1.0">

        <title>Request Submitted | DONATE+</title>

        <style>

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background: #fbfaf6;
                color: #171915;
            }

            .container {
                max-width: 700px;
                margin: 100px auto;
                padding: 40px;
                background: white;
                border-radius: 18px;
                text-align: center;
                border: 1px solid #e8e6df;
            }

            .success-icon {
                width: 70px;
                height: 70px;
                margin: 0 auto 20px;
                border-radius: 50%;
                background: #e8f4e8;
                color: #1f5b3a;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 35px;
            }

            h1 {
                color: #1f5b3a;
            }

            p {
                color: #666;
                line-height: 1.7;
            }

            .btn {
                display: inline-block;
                margin-top: 20px;
                padding: 13px 22px;
                background: #1f5b3a;
                color: white;
                text-decoration: none;
                border-radius: 10px;
                font-weight: bold;
            }

            .btn:hover {
                background: #17482e;
            }

        </style>

    </head>

    <body>

        <div class="container">

            <div class="success-icon">
                ✓
            </div>

            <h1>Request Submitted!</h1>

            <p>
                Your donation request has been submitted successfully.
            </p>

            <p>
                The donor can now review your request.
            </p>

            <a href="recipient-dashboard.php" class="btn">
                Back to Dashboard
            </a>

        </div>

    </body>

    </html>

    <?php

    exit;
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Request Donation | DONATE+
</title>


<style>

@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');


:root {
    --green:#1f5b3a;
    --green2:#2f744a;
    --cream:#fbfaf6;
    --ink:#171915;
    --gray:#6f766f;
    --border:#e5e1d8;
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
    max-width:800px;
    margin:70px auto;
    padding:0 25px;
}


.card {
    background:#fff;
    border:1px solid var(--border);
    border-radius:18px;
    padding:40px;
}


h1 {
    font-family:'Playfair Display',serif;
    font-size:42px;
    margin:0 0 10px;
}


.subtitle {
    color:var(--gray);
    margin-bottom:30px;
}


.requesting {
    background:#f4f6f1;
    padding:18px;
    border-radius:10px;
    margin-bottom:25px;
}


.requesting strong {
    color:var(--green);
}


label {
    display:block;
    font-weight:600;
    margin-bottom:8px;
}


.form-group {
    margin-bottom:20px;
}


input,
textarea {
    width:100%;
    padding:13px 14px;
    border:1px solid #d9d6cd;
    border-radius:9px;
    font-family:inherit;
    font-size:14px;
}


input:focus,
textarea:focus {
    outline:none;
    border-color:var(--green);
}


textarea {
    min-height:120px;
    resize:vertical;
}


.row {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}


.btn {
    width:100%;
    border:none;
    padding:15px;
    background:var(--green);
    color:white;
    border-radius:10px;
    font-size:15px;
    font-weight:700;
    cursor:pointer;
}


.btn:hover {
    background:#17482e;
}


.error {
    background:#ffe8e8;
    color:#a32828;
    padding:14px;
    border-radius:9px;
    margin-bottom:20px;
}


.back {
    display:inline-block;
    margin-bottom:20px;
    color:var(--green);
    text-decoration:none;
    font-weight:600;
}


small {
    color:#777;
}


@media(max-width:600px) {

    .container {
        margin:30px auto;
    }

    .card {
        padding:25px;
    }

    h1 {
        font-size:34px;
    }

    .row {
        grid-template-columns:1fr;
        gap:0;
    }

}

</style>

</head>


<body>


<div class="container">


    <a href="donation-details.php?id=<?= $donation_id ?>"
       class="back">

        ← Back to donation

    </a>


    <div class="card">


        <h1>
            Request Donation
        </h1>


        <p class="subtitle">
            Tell the donor how much you need and why.
        </p>


        <div class="requesting">

            Requesting:

            <strong>
                <?= $title ?>
            </strong>

            <br>

            Quantity left:

            <strong>
                <?= $available_quantity ?>
            </strong>
            of <?= $total_quantity ?>

        </div>


        <?php if ($error !== ""): ?>

            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <?php if ($is_campaign_item): ?>

            <div class="error">

                This item was donated to a campaign drive. It's collected and
                distributed through the campaign, so it can't be requested here.

            </div>

        <?php elseif ($is_own_donation): ?>

            <div class="error">

                This is your own donation — you can't request it.

            </div>

        <?php elseif ($is_expired): ?>

            <div class="error">

                This item has passed its expiry / best-before date
                and can no longer be requested.

            </div>

        <?php elseif ($status !== "available" || $available_quantity <= 0): ?>

            <div class="error">

                This donation is no longer available
                for requests.

            </div>

        <?php else: ?>


            <form method="POST"
                  action="request-donation.php?id=<?= $donation_id ?>">

                <?php csrf_field(); ?>

                <input type="hidden"
                       name="donation_id"
                       value="<?= $donation_id ?>">


                <div class="row">


                    <div class="form-group">

                        <label>
                            Requested Quantity *
                        </label>

                        <input
                            type="number"
                            name="quantity"
                            min="1"
                            max="<?= $available_quantity ?>"
                            required
                            value="<?= htmlspecialchars($_POST["quantity"] ?? "") ?>"
                        >

                        <small>
                            Maximum available:
                            <?= $available_quantity ?>
                        </small>

                    </div>


                    <div class="form-group">

                        <label>
                            Number of Beneficiaries *
                        </label>

                        <input
                            type="number"
                            name="beneficiaries"
                            min="1"
                            required
                            value="<?= htmlspecialchars($_POST["beneficiaries"] ?? "") ?>"
                        >

                    </div>


                </div>


                <div class="form-group">

                    <label>
                        Purpose *
                    </label>

                    <input
                        type="text"
                        name="purpose"
                        placeholder="Why do you need this donation?"
                        required
                        value="<?= htmlspecialchars($_POST["purpose"] ?? "") ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Preferred Collection Date *
                    </label>

                    <input
                        type="date"
                        name="collection_date"
                        required
                        min="<?= date('Y-m-d') ?>"
                        value="<?= htmlspecialchars($_POST["collection_date"] ?? "") ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Message
                    </label>

                    <textarea
                        name="message"
                        placeholder="Write a message to the donor..."
                    ><?= htmlspecialchars($_POST["message"] ?? "") ?></textarea>

                </div>


                <button
                    type="submit"
                    class="btn"
                >

                    Submit Request

                </button>


            </form>


        <?php endif; ?>


    </div>


</div>


</body>

</html>