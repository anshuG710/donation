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


/*
|--------------------------------------------------------------------------
| Search Filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");
$category_id = $_GET["category"] ?? "";
$location = trim($_GET["location"] ?? "");


/*
|--------------------------------------------------------------------------
| Get Categories
|--------------------------------------------------------------------------
*/

$category_sql = "
    SELECT id, name
    FROM categories
    ORDER BY name ASC
";

$category_result = $conn->query($category_sql);


/*
|--------------------------------------------------------------------------
| Get Available Donations
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        donations.id,
        donations.donor_id,
        donations.category_id,
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
        categories.name AS category_name,
        users.name AS donor_name
    FROM donations
    INNER JOIN categories
        ON donations.category_id = categories.id
    INNER JOIN users
        ON donations.donor_id = users.id
    WHERE donations.status = 'available'
";


/*
|--------------------------------------------------------------------------
| Search By Item
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $search_safe = $conn->real_escape_string($search);

    $sql .= "
        AND (
            donations.title LIKE '%$search_safe%'
            OR donations.description LIKE '%$search_safe%'
        )
    ";
}


/*
|--------------------------------------------------------------------------
| Filter By Category
|--------------------------------------------------------------------------
*/

if ($category_id !== "" && is_numeric($category_id)) {

    $category_id_safe = (int)$category_id;

    $sql .= "
        AND donations.category_id = $category_id_safe
    ";
}


/*
|--------------------------------------------------------------------------
| Filter By Location
|--------------------------------------------------------------------------
*/

if ($location !== "") {

    $location_safe = $conn->real_escape_string($location);

    $sql .= "
        AND donations.location LIKE '%$location_safe%'
    ";
}


/*
|--------------------------------------------------------------------------
| Latest Donations First
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY donations.created_at DESC
";


$result = $conn->query($sql);


if (!$result) {
    die("Donation query failed: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Find Donations | DONATE+</title>

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

        /* Header */

        header {
            height: 118px;
            background: white;
            border-bottom: 1px solid #e5e1d9;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 0 20%;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-circle {
            width: 38px;
            height: 38px;

            border: 2px solid #216c47;
            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            color: #216c47;
            font-size: 20px;
        }

        .logo-name {
            font-size: 15px;
            font-weight: bold;
            color: #216c47;
        }

        .logo-tagline {
            font-size: 9px;
            color: #777;
            margin-top: 2px;
        }

        .dashboard-btn {
            background: #216c47;
            color: white;
            text-decoration: none;

            padding: 13px 20px;

            border-radius: 9px;

            font-size: 13px;
            font-weight: bold;
        }

        .dashboard-btn:hover {
            background: #185538;
        }


        /* Main */

        .container {
            width: 1080px;
            max-width: calc(100% - 40px);

            margin: 75px auto;
        }


        .eyebrow {
            color: #216c47;

            font-size: 11px;
            font-weight: bold;

            letter-spacing: 1.5px;

            margin-bottom: 35px;
        }


        h1 {
            font-family: Georgia, serif;

            font-size: 46px;

            margin: 0 0 10px;
        }


        .subtitle {
            color: #777;

            margin: 0 0 32px;

            font-size: 15px;
        }


        /* Search */

        .search-box {
            background: white;

            border: 1px solid #e5e1d9;

            border-radius: 14px;

            padding: 15px;

            display: grid;

            grid-template-columns: 1.5fr 1fr 1fr auto;

            gap: 10px;

            margin-bottom: 28px;
        }


        input,
        select {

            width: 100%;

            height: 40px;

            border: 1px solid #ddd8cf;

            border-radius: 8px;

            padding: 0 12px;

            font-size: 13px;

            background: white;

        }


        input:focus,
        select:focus {

            outline: none;

            border-color: #216c47;

        }


        .search-btn {

            border: none;

            background: #216c47;

            color: white;

            padding: 0 22px;

            border-radius: 8px;

            font-weight: bold;

            cursor: pointer;

        }


        .search-btn:hover {

            background: #185538;

        }


        /* Donation Grid */

        .donation-grid {

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 22px;

        }


        .card {

            background: white;

            border: 1px solid #e5e1d9;

            border-radius: 14px;

            overflow: hidden;

        }


        .card-image {

            height: 165px;

            background: #e9e6de;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #777;

            font-size: 13px;

        }


        .card-image img {

            width: 100%;

            height: 100%;

            object-fit: cover;

        }


        .card-body {

            padding: 18px;

        }


        .category-badge {

            display: inline-block;

            background: #e5f5e9;

            color: #216c47;

            padding: 5px 9px;

            border-radius: 15px;

            font-size: 11px;

            font-weight: bold;

            margin-bottom: 12px;

        }


        .card h2 {

            font-family: Georgia, serif;

            font-size: 21px;

            margin: 0 0 10px;

        }


        .details {

            color: #666;

            font-size: 13px;

            line-height: 1.7;

            margin-bottom: 15px;

        }


        .location {

            color: #555;

            font-size: 12px;

            margin-bottom: 15px;

        }


        .status {

            display: inline-block;

            background: #e5f5e9;

            color: #267044;

            padding: 5px 10px;

            border-radius: 15px;

            font-size: 11px;

            font-weight: bold;

            margin-bottom: 14px;

        }


        .view-btn {

            display: block;

            width: 100%;

            background: #216c47;

            color: white;

            text-align: center;

            text-decoration: none;

            padding: 12px;

            border-radius: 8px;

            font-size: 13px;

            font-weight: bold;

        }


        .view-btn:hover {

            background: #185538;

        }


        /* Empty */

        .empty {

            background: white;

            border: 1px solid #e5e1d9;

            border-radius: 14px;

            padding: 60px 20px;

            text-align: center;

        }


        .empty h2 {

            font-family: Georgia, serif;

            margin-bottom: 8px;

        }


        .empty p {

            color: #777;

        }


        /* Responsive */

        @media (max-width: 900px) {

            header {

                padding: 0 5%;

            }

            .donation-grid {

                grid-template-columns: repeat(2, 1fr);

            }

            .search-box {

                grid-template-columns: 1fr 1fr;

            }

        }


        @media (max-width: 600px) {

            .donation-grid {

                grid-template-columns: 1fr;

            }

            .search-box {

                grid-template-columns: 1fr;

            }

            h1 {

                font-size: 36px;

            }

        }

    </style>

</head>


<body>


<!-- Header -->

<header>

    <div class="logo">

        <div class="logo-circle">
            ♡
        </div>

        <div>

            <div class="logo-name">
                DONATE+
            </div>

            <div class="logo-tagline">
                Give Today, Change Tomorrow
            </div>

        </div>

    </div>


    <a href="donor-dashboard.php" class="dashboard-btn">
        My Dashboard
    </a>

</header>



<!-- Main -->

<div class="container">


    <div class="eyebrow">
        RECIPIENT PORTAL
    </div>


    <h1>
        Find Donations
    </h1>


    <p class="subtitle">
        Browse available items across Nepal.
    </p>



    <!-- Search -->

    <form method="GET" action="find-donations.php" class="search-box">

        <input
            type="text"
            name="search"
            placeholder="Search item..."
            value="<?php echo htmlspecialchars($search); ?>"
        >


        <select name="category">

            <option value="">
                All Categories
            </option>


            <?php while ($category = $category_result->fetch_assoc()): ?>

                <option
                    value="<?php echo $category["id"]; ?>"
                    <?php
                    if (
                        (string)$category_id ===
                        (string)$category["id"]
                    ) {
                        echo "selected";
                    }
                    ?>
                >

                    <?php echo htmlspecialchars($category["name"]); ?>

                </option>

            <?php endwhile; ?>

        </select>


        <input
            type="text"
            name="location"
            placeholder="Location"
            value="<?php echo htmlspecialchars($location); ?>"
        >


        <button type="submit" class="search-btn">
            Search
        </button>

    </form>



    <!-- Donations -->

    <?php if ($result->num_rows > 0): ?>

        <div class="donation-grid">


            <?php while ($donation = $result->fetch_assoc()): ?>


                <div class="card">


                    <!-- Image -->

                    <div class="card-image">

                        <?php if (!empty($donation["image"])): ?>

                            <img
                                src="<?php echo htmlspecialchars($donation["image"]); ?>"
                                alt="<?php echo htmlspecialchars($donation["title"]); ?>"
                            >

                        <?php else: ?>

                            No image available

                        <?php endif; ?>

                    </div>



                    <!-- Body -->

                    <div class="card-body">


                        <div class="category-badge">

                            <?php
                            echo htmlspecialchars(
                                $donation["category_name"]
                            );
                            ?>

                        </div>


                        <h2>

                            <?php
                            echo htmlspecialchars(
                                $donation["title"]
                            );
                            ?>

                        </h2>


                        <div class="details">

                            <?php
                            $total_qty = (int) ($donation["quantity"] ?? 0);
                            $claimed_qty = (int) ($donation["claimed"] ?? 0);
                            $left_qty = $total_qty - $claimed_qty;
                            if ($left_qty < 0) { $left_qty = 0; }
                            ?>

                            <strong><?= $left_qty ?></strong>
                            <?= $left_qty === 1 ? "piece" : "pieces" ?> left
                            <span style="color:#9aa29a">of <?= $total_qty ?></span>

                            ·

                            <?php
                            echo htmlspecialchars(
                                $donation["item_condition"] ?? "Good"
                            );
                            ?>

                            condition

                        </div>


                        <div class="location">

                            📍

                            <?php
                            echo htmlspecialchars(
                                $donation["location"] ?? "Nepal"
                            );
                            ?>

                        </div>


                        <div class="status">
                            Available
                        </div>


                        <a
    href="donation-details.php?id=<?php echo $donation["id"]; ?>"
    class="view-btn"
>
    View Details (ID: <?php echo $donation["id"]; ?>)
</a>


                    </div>

                </div>


            <?php endwhile; ?>


        </div>


    <?php else: ?>


        <div class="empty">

            <h2>
                No donations found
            </h2>

            <p>
                There are currently no available donations matching your search.
            </p>

        </div>


    <?php endif; ?>


</div>


</body>

</html>


<?php

$conn->close();

?>