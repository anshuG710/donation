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

    $quantity = intval($_POST["quantity"] ?? 0);

    $unit = trim($_POST["unit"] ?? "");

    $item_condition = trim($_POST["item_condition"] ?? "");

    $location = trim($_POST["location"] ?? "");

    $description = trim($_POST["description"] ?? "");


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $title === "" ||
        $category_id <= 0 ||
        $quantity <= 0 ||
        $unit === "" ||
        $item_condition === "" ||
        $location === ""
    ) {

        $error = "Please fill in all required fields.";

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


            $sql = "
                INSERT INTO donations
                (
                    donor_id,
                    category_id,
                    title,
                    description,
                    quantity,
                    unit,
                    item_condition,
                    location,
                    image,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";


            $stmt = $conn->prepare($sql);


            if (!$stmt) {

                $error = "Database error: " . $conn->error;

            } else {


                $stmt->bind_param(
                    "iississsss",
                    $user_id,
                    $category_id,
                    $title,
                    $description,
                    $quantity,
                    $unit,
                    $item_condition,
                    $location,
                    $image_name,
                    $status
                );


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


                    <select
                        name="item_condition"
                        required
                    >

                        <option value="">

                            Select Condition

                        </option>


                        <option value="New">

                            New

                        </option>


                        <option value="Like New">

                            Like New

                        </option>


                        <option value="Good">

                            Good

                        </option>


                        <option value="Used">

                            Used

                        </option>

                    </select>

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


</body>

</html>