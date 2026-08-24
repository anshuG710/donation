<?php

/*
|--------------------------------------------------------------------------
| ADMIN BOOTSTRAP + GUARD
|--------------------------------------------------------------------------
|
| Single include for every admin page. It:
|   1. starts the session
|   2. connects to the database ($conn)
|   3. loads csrf / flash / helper functions
|   4. blocks anyone who is not a logged-in admin
|
| Usage at the very top of an admin page:
|
|     require_once __DIR__ . "/config/admin-guard.php";
|
| (from the project root; adjust the relative path if the page ever
|  lives in a subfolder).
|
*/


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . "/database.php";
require_once __DIR__ . "/csrf.php";
require_once __DIR__ . "/flash.php";
require_once __DIR__ . "/helpers.php";


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
| ADMIN ONLY
|--------------------------------------------------------------------------
|
| Anyone who is logged in but not an admin is sent back to their own
| dashboard instead of seeing admin screens.
|
*/

if (($_SESSION["user_role"] ?? "") !== "admin") {

    if (($_SESSION["user_role"] ?? "") === "recipient") {
        header("Location: recipient-dashboard.php");
    } else {
        header("Location: donor-dashboard.php");
    }

    exit();
}


/*
|--------------------------------------------------------------------------
| CURRENT ADMIN
|--------------------------------------------------------------------------
*/

$admin_id   = (int) $_SESSION["user_id"];
$admin_name = $_SESSION["user_name"] ?? "Admin";
