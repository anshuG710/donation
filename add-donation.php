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
require_once __DIR__ . "/config/helpers.php";
require_once __DIR__ . "/config/conditions.php";
require_once __DIR__ . "/config/csrf.php";


/*
|--------------------------------------------------------------------------
| USER INFORMATION
|--------------------------------------------------------------------------
*/

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["user_name"] ?? "Donor";


/*
|--------------------------------------------------------------------------
| GET CATEGORIES FROM DATABASE
|--------------------------------------------------------------------------
*/

$categories = [];

$sql_categories = "
    SELECT id, name
    FROM categories
    ORDER BY name ASC
";

$result_categories = $conn->query($sql_categories);

if ($result_categories) {

    while ($row = $result_categories->fetch_assoc()) {

        $categories[] = $row;

    }

}


/*
|--------------------------------------------------------------------------
| ACTIVE CAMPAIGNS (optional "contribute to a campaign" dropdown)
|--------------------------------------------------------------------------
*/

$active_campaigns = [];

if (table_exists($conn, "campaigns")) {

    $rc = $conn->query("SELECT id, title FROM campaigns WHERE status = 'active' ORDER BY created_at DESC");

    if ($rc) {

        while ($row = $rc->fetch_assoc()) {

            $active_campaigns[] = $row;

        }

    }

}


/*
|--------------------------------------------------------------------------
| FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /*
    |--------------------------------------------------------------------------
    | GET FORM DATA
    |--------------------------------------------------------------------------
    */

    $title = trim($_POST["title"] ?? "");

    $category_id = intval($_POST["category_id"] ?? 0);

    $campaign_id = intval($_POST["campaign_id"] ?? 0);

    $quantity = intval($_POST["quantity"] ?? 0);

    $unit = trim($_POST["unit"] ?? "");

    $item_condition = trim($_POST["item_condition"] ?? "");

    $expiry_date = trim($_POST["expiry_date"] ?? "");

    $location = trim($_POST["location"] ?? "");

    $description = trim($_POST["description"] ?? "");


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    $category_name = category_name_by_id($conn, $category_id);
    $expiry_needed = category_needs_expiry($category_name);
    $today = date("Y-m-d");

    if (!csrf_verify()) {

        $error = "Security check failed. Please refresh the page and try again.";

    } elseif (
        $title === "" ||
        $category_id <= 0 ||
        $quantity <= 0 ||
        $unit === "" ||
        $item_condition === "" ||
        $location === ""
    ) {

        $error = "Please fill in all required fields.";

    } elseif (!is_valid_condition($category_name, $item_condition)) {

        $error = "Please choose a valid condition for the selected category.";

    } elseif ($expiry_needed && $expiry_date === "") {

        $error = "Please enter an expiry / best-before date for this item.";

    } elseif ($expiry_needed && $expiry_date < $today) {

        $error = "The expiry date must be today or a future date.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | IMAGE UPLOAD
        |--------------------------------------------------------------------------
        */

        $image_name = null;


        if (
            isset($_FILES["image"]) &&
            $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {


            if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {

                $error = "There was a problem uploading the image.";

            } else {


                /*
                |--------------------------------------------------------------------------
                | CHECK FILE SIZE
                |--------------------------------------------------------------------------
                */

                if ($_FILES["image"]["size"] > 5 * 1024 * 1024) {

                    $error = "Image must be smaller than 5 MB.";

                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | CHECK IMAGE TYPE
                    |--------------------------------------------------------------------------
                    */

                    $allowed_types = [
                        "image/jpeg",
                        "image/png",
                        "image/webp",
                        "image/jpg"
                    ];

                    $file_type = mime_content_type(
                        $_FILES["image"]["tmp_name"]
                    );


                    if (!in_array($file_type, $allowed_types)) {

                        $error = "Only JPG, JPEG, PNG and WEBP images are allowed.";

                    } else {


                        /*
                        |--------------------------------------------------------------------------
                        | CREATE UPLOAD FOLDER
                        |--------------------------------------------------------------------------
                        */

                        $upload_directory = "uploads/donations/";


                        if (!is_dir($upload_directory)) {

                            mkdir(
                                $upload_directory,
                                0777,
                                true
                            );

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | CREATE UNIQUE IMAGE NAME
                        |--------------------------------------------------------------------------
                        */

                        $extension = strtolower(
                            pathinfo(
                                $_FILES["image"]["name"],
                                PATHINFO_EXTENSION
                            )
                        );


                        $image_name =
                            "donation_" .
                            $user_id .
                            "_" .
                            time() .
                            "_" .
                            uniqid() .
                            "." .
                            $extension;


                        $image_path =
                            $upload_directory .
                            $image_name;


                        /*
                        |--------------------------------------------------------------------------
                        | MOVE IMAGE
                        |--------------------------------------------------------------------------
                        */

                        if (
                            !move_uploaded_file(
                                $_FILES["image"]["tmp_name"],
                                $image_path
                            )
                        ) {

                            $error = "Unable to save the uploaded image.";

                        }

                    }

                }

            }

        }


        /*
        |--------------------------------------------------------------------------
        | INSERT DONATION
        |--------------------------------------------------------------------------
        */

        if (!isset($error)) {


            $status = "available";

            // Only store an expiry date for categories that need one.
            $expiry_for_db = ($expiry_needed && $expiry_date !== "") ? $expiry_date : null;

            // Optional campaign tag — only if it's a real, active campaign.
            $campaign_for_db = null;
            if ($campaign_id > 0 && table_exists($conn, "campaigns")) {
                $ck = $conn->prepare("SELECT id FROM campaigns WHERE id = ? AND status = 'active' LIMIT 1");
                $ck->bind_param("i", $campaign_id);
                $ck->execute();
                if ($ck->get_result()->num_rows > 0) {
                    $campaign_for_db = $campaign_id;
                }
                $ck->close();
            }

            // Build the insert dynamically so optional columns (added by later
            // migrations) are only referenced when they actually exist.
            $cols  = ["donor_id", "category_id", "title", "description", "quantity",
                      "unit", "item_condition", "location", "image", "status"];
            $vals  = [$user_id, $category_id, $title, $description, $quantity,
                      $unit, $item_condition, $location, $image_name, $status];
            $types = "iississsss";

            if (column_exists($conn, "donations", "expiry_date")) {
                $cols[] = "expiry_date";
                $vals[] = $expiry_for_db;
                $types .= "s";
            }
            if (column_exists($conn, "donations", "campaign_id")) {
                $cols[] = "campaign_id";
                $vals[] = $campaign_for_db;
                $types .= "i";
            }

            // A campaign contribution starts pending until an admin approves it.
            if (column_exists($conn, "donations", "campaign_status")) {
                $cols[] = "campaign_status";
                $vals[] = ($campaign_for_db !== null) ? "pending" : null;
                $types .= "s";
            }

            $placeholders = implode(", ", array_fill(0, count($cols), "?"));
            $sql = "INSERT INTO donations (" . implode(", ", $cols) . ") VALUES (" . $placeholders . ")";

            $stmt = $conn->prepare($sql);


            if (!$stmt) {

                $error = "Database error: " . $conn->error;

            } else {

                $stmt->bind_param($types, ...$vals);


                if ($stmt->execute()) {

                    $stmt->close();

                    $conn->close();


                    /*
                    |--------------------------------------------------------------------------
                    | SUCCESS
                    |--------------------------------------------------------------------------
                    */

                    header(
                        "Location: donor-dashboard.php?donation=success"
                    );

                    exit();

                } else {

                    $error = "Donation could not be saved. Please try again.";

                    $stmt->close();

                }

            }

        }

    }

}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Add Donation | DONATE+</title>


<style>

@import url(
'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap'
);


:root {

    --green: #1f5b3a;

    --green2: #2f744a;

    --orange: #f28c18;

    --cream: #fbfaf6;

    --ink: #171915;

}


* {

    box-sizing: border-box;

}


body {

    margin: 0;

    font-family: 'DM Sans', sans-serif;

    background: var(--cream);

    color: var(--ink);

}


.serif {

    font-family: 'Playfair Display', serif;

}


.container {

    max-width: 1180px;

    margin: auto;

    padding: 0 28px;

}


a {

    text-decoration: none;

}


.btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    padding: 13px 20px;

    border-radius: 10px;

    font-weight: 700;

    transition: .2s;

    border: none;

    cursor: pointer;

}


.btn-green {

    background: var(--green);

    color: #fff;

}


.btn-green:hover {

    background: #17482e;

}


header {

    height: 74px;

    background: rgba(255,255,255,.94);

    border-bottom: 1px solid #e8e6df;

}


.nav {

    height: 100%;

    display: flex;

    align-items: center;

    justify-content: space-between;

}


.brand {

    display: flex;

    align-items: center;

    gap: 10px;

    color: var(--green);

    font-weight: 800;

}


.brandmark {

    width: 38px;

    height: 38px;

    border: 2px solid var(--green);

    border-radius: 50% 50% 45% 45%;

    display: grid;

    place-items: center;

    font-size: 19px;

}


.brand small {

    display: block;

    font-size: 9px;

    color: #7d857d;

    letter-spacing: .6px;

    font-weight: 600;

}


.section {

    padding: 70px 0;

}


.eyebrow {

    color: var(--green);

    font-weight: 800;

    letter-spacing: 1px;

    font-size: 12px;

    text-transform: uppercase;

}


.card {

    background: #fff;

    border: 1px solid #eee9df;

    border-radius: 18px;

    box-shadow: 0 10px 30px #2635260b;

}


.field {

    margin-top: 5px;

}


.field label {

    display: block;

    font-size: 12px;

    font-weight: 700;

    margin-bottom: 7px;

}


.field input,
.field select,
.field textarea {

    width: 100%;

    padding: 13px;

    border: 1px solid #ddd9d1;

    border-radius: 10px;

    outline: none;

    font-family: inherit;

    background: white;

}


.field input:focus,
.field select:focus,
.field textarea:focus {

    border-color: var(--green);

}


.error {

    background: #fde8e8;

    color: #a52a2a;

    border: 1px solid #f2caca;

    padding: 13px 16px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 13px;

}


.help {

    color: #777;

    font-size: 11px;

    margin-top: 6px;

}


@media(max-width:700px) {

    .container {

        padding: 0 18px;

    }

    form {

        grid-template-columns: 1fr !important;

    }

}

</style>

</head>


<body>


<header>

    <div class="container nav">


        <a class="brand" href="index.html">

            <span class="brandmark">♡</span>

            <span>

                DONATE+

                <small>Give Today, Change Tomorrow</small>

            </span>

        </a>


        <a
            href="donor-dashboard.php"
            style="font-size:13px;color:var(--green);font-weight:700"
        >

            ← Dashboard

        </a>


    </div>

</header>



<main class="container section">


    <div style="max-width:850px;margin:auto">


        <div class="eyebrow">

            Donor

        </div>


        <h1
            class="serif"
            style="font-size:44px;margin:12px 0 10px"
        >

            Donate an Item

        </h1>


        <p style="color:#6f766f">

            Tell recipients what you would like to share.

        </p>



        <?php if (isset($error)): ?>

            <div class="error">

                <?php echo htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>



        <div
            class="card"
            style="padding:28px;margin-top:28px"
        >


            <form
                method="POST"
                action="add-donation.php"
                enctype="multipart/form-data"
                style="display:grid;grid-template-columns:1fr 1fr;gap:18px"
            >

                <?php csrf_field(); ?>


                <!-- ITEM NAME -->

                <div class="field">

                    <label>

                        Item Name *

                    </label>


                    <input
                        type="text"
                        name="title"
                        placeholder="e.g. Winter Clothes"
                        required
                        value="<?php echo htmlspecialchars($_POST["title"] ?? ""); ?>"
                    >

                </div>



                <!-- CATEGORY -->

                <div class="field">

                    <label>

                        Category *

                    </label>


                    <select
                        id="category_id"
                        name="category_id"
                        required
                    >

                        <option value="">

                            Select Category

                        </option>


                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?php echo $category["id"]; ?>"

                                <?php

                                if (
                                    isset($_POST["category_id"]) &&
                                    $_POST["category_id"] == $category["id"]
                                ) {

                                    echo "selected";

                                }

                                ?>
                            >

                                <?php

                                echo htmlspecialchars(
                                    $category["name"]
                                );

                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>



                <!-- CAMPAIGN (optional) -->

                <?php if (!empty($active_campaigns)): ?>

                <div class="field">

                    <label>
                        Contribute to a campaign (optional)
                    </label>

                    <select name="campaign_id">

                        <option value="0">— None —</option>

                        <?php foreach ($active_campaigns as $camp): ?>

                            <option
                                value="<?php echo (int) $camp["id"]; ?>"
                                <?php echo (($_POST["campaign_id"] ?? $_GET["campaign"] ?? "") == $camp["id"]) ? "selected" : ""; ?>
                            >
                                <?php echo htmlspecialchars($camp["title"]); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <?php endif; ?>



                <!-- QUANTITY -->

                <div class="field">

                    <label>

                        Quantity *

                    </label>


                    <input
                        type="number"
                        name="quantity"
                        min="1"
                        placeholder="e.g. 5"
                        required
                        value="<?php echo htmlspecialchars($_POST["quantity"] ?? ""); ?>"
                    >

                </div>



                <!-- UNIT -->

                <div class="field">

                    <label>

                        Unit *

                    </label>


                    <select
                        name="unit"
                        required
                    >

                        <option value="">

                            Select Unit

                        </option>


                        <option
                            value="pieces"
                            <?php
                            if (($_POST["unit"] ?? "") === "pieces") {
                                echo "selected";
                            }
                            ?>
                        >

                            Pieces

                        </option>


                        <option
                            value="kg"
                            <?php
                            if (($_POST["unit"] ?? "") === "kg") {
                                echo "selected";
                            }
                            ?>
                        >

                            Kilograms (kg)

                        </option>


                        <option
                            value="grams"
                            <?php
                            if (($_POST["unit"] ?? "") === "grams") {
                                echo "selected";
                            }
                            ?>
                        >

                            Grams (g)

                        </option>


                        <option
                            value="liters"
                            <?php
                            if (($_POST["unit"] ?? "") === "liters") {
                                echo "selected";
                            }
                            ?>
                        >

                            Liters (L)

                        </option>


                        <option
                            value="packets"
                            <?php
                            if (($_POST["unit"] ?? "") === "packets") {
                                echo "selected";
                            }
                            ?>
                        >

                            Packets

                        </option>


                        <option
                            value="boxes"
                            <?php
                            if (($_POST["unit"] ?? "") === "boxes") {
                                echo "selected";
                            }
                            ?>
                        >

                            Boxes

                        </option>


                        <option
                            value="sets"
                            <?php
                            if (($_POST["unit"] ?? "") === "sets") {
                                echo "selected";
                            }
                            ?>
                        >

                            Sets

                        </option>


                    </select>

                </div>



                <!-- CONDITION -->

                <div class="field">

                    <label>

                        Condition *

                    </label>


                    <?php
                        $posted_cat_id   = intval($_POST["category_id"] ?? 0);
                        $posted_cat_name = $posted_cat_id > 0
                            ? category_name_by_id($conn, $posted_cat_id)
                            : "";
                        $cond_options    = conditions_for_category($posted_cat_name);
                        $posted_cond     = $_POST["item_condition"] ?? "";
                    ?>

                    <select
                        id="item_condition"
                        name="item_condition"
                        required
                    >

                        <option value="">
                            Select Condition
                        </option>

                        <?php foreach ($cond_options as $cond): ?>

                            <option
                                value="<?php echo htmlspecialchars($cond); ?>"
                                <?php echo ($posted_cond === $cond) ? "selected" : ""; ?>
                            >
                                <?php echo htmlspecialchars($cond); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>



                <!-- EXPIRY / BEST-BEFORE (perishable categories only) -->

                <?php
                    $posted_expiry_needed = category_needs_expiry($posted_cat_name);
                    $posted_expiry        = htmlspecialchars($_POST["expiry_date"] ?? "");
                ?>

                <div
                    class="field"
                    id="expiry_field"
                    style="<?php echo $posted_expiry_needed ? "" : "display:none"; ?>"
                >

                    <label>

                        Expiry / Best-before date *

                    </label>


                    <input
                        type="date"
                        id="expiry_date"
                        name="expiry_date"
                        min="<?php echo date("Y-m-d"); ?>"
                        value="<?php echo $posted_expiry; ?>"
                        <?php echo $posted_expiry_needed ? "required" : ""; ?>
                    >

                </div>



                <!-- LOCATION -->

                <div class="field">

                    <label>

                        Location *

                    </label>


                    <input
                        type="text"
                        name="location"
                        placeholder="Kathmandu, Nepal"
                        required
                        value="<?php echo htmlspecialchars($_POST["location"] ?? ""); ?>"
                    >

                </div>



                <!-- DESCRIPTION -->

                <div
                    class="field"
                    style="grid-column:1/-1"
                >

                    <label>

                        Description

                    </label>


                    <textarea
                        name="description"
                        rows="4"
                        placeholder="Describe the item..."
                    ><?php
                        echo htmlspecialchars(
                            $_POST["description"] ?? ""
                        );
                    ?></textarea>

                </div>



                <!-- IMAGE -->

                <div
                    class="field"
                    style="grid-column:1/-1"
                >

                    <label>

                        Item Image

                    </label>


                    <input
                        type="file"
                        name="image"
                        accept="image/jpeg,image/png,image/webp"
                    >


                    <div class="help">

                        JPG, PNG or WEBP. Maximum 5 MB.

                    </div>

                </div>



                <!-- BUTTON -->

                <div
                    style="grid-column:1/-1;text-align:right"
                >

                    <button
                        type="submit"
                        class="btn btn-green"
                    >

                        Submit Donation

                    </button>

                </div>


            </form>


        </div>


    </div>


</main>


<!--
|--------------------------------------------------------------------------
| CATEGORY-AWARE CONDITION + EXPIRY
|--------------------------------------------------------------------------
| When the donor changes the category, rebuild the Condition dropdown to
| the options valid for that category and show/hide the expiry date field.
| The map comes from config/conditions.php (the single source of truth),
| and the server re-validates on submit, so this is only convenience.
-->
<script>
(function () {

    var MAP  = <?php echo condition_map_json($conn); ?>;
    var cat  = document.getElementById("category_id");
    var cond = document.getElementById("item_condition");
    var expField = document.getElementById("expiry_field");
    var expInput = document.getElementById("expiry_date");

    if (!cat || !cond) {
        return;
    }

    function currentEntry() {
        var v = cat.value || "";
        return MAP[v] || MAP[""];
    }

    function refresh(keepSelected) {

        var entry = currentEntry();
        var prev  = keepSelected ? cond.value : "";

        cond.innerHTML = '<option value="">Select Condition</option>';

        entry.conditions.forEach(function (c) {
            var o = document.createElement("option");
            o.value = c;
            o.textContent = c;
            if (c === prev) {
                o.selected = true;
            }
            cond.appendChild(o);
        });

        if (expField && expInput) {
            if (entry.expiry) {
                expField.style.display = "";
                expInput.setAttribute("required", "required");
            } else {
                expField.style.display = "none";
                expInput.removeAttribute("required");
                expInput.value = "";
            }
        }
    }

    cat.addEventListener("change", function () { refresh(false); });

    // First load: keep whatever the server already rendered (e.g. after a
    // validation error) and align the expiry field with the current category.
    refresh(true);

})();
</script>


</body>

</html>