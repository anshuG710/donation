<?php

session_start();

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/helpers.php";


/*
|--------------------------------------------------------------------------
| ONLY ALLOW POST REQUESTS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: login.html");
    exit();

}


/*
|--------------------------------------------------------------------------
| GET EMAIL AND PASSWORD
|--------------------------------------------------------------------------
*/

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";


/*
|--------------------------------------------------------------------------
| CHECK EMPTY FIELDS
|--------------------------------------------------------------------------
*/

if ($email === "" || $password === "") {

    echo "
    <script>
        alert('Please enter your email and password.');
        window.location.href = 'login.html';
    </script>
    ";

    exit();

}


/*
|--------------------------------------------------------------------------
| FIND USER
|--------------------------------------------------------------------------
*/

$sql = "SELECT id, name, email, phone, password, role
        FROM users
        WHERE email = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);


/*
|--------------------------------------------------------------------------
| CHECK QUERY
|--------------------------------------------------------------------------
*/

if (!$stmt) {

    die("Database query failed: " . $conn->error);

}


$stmt->bind_param("s", $email);

$stmt->execute();

$result = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| CHECK ACCOUNT
|--------------------------------------------------------------------------
*/

if ($result->num_rows === 0) {

    echo "
    <script>
        alert('No account found with this email.');
        window.location.href = 'login.html';
    </script>
    ";

    exit();

}


$user = $result->fetch_assoc();


/*
|--------------------------------------------------------------------------
| VERIFY PASSWORD
|--------------------------------------------------------------------------
*/

if (!password_verify($password, $user["password"])) {

    echo "
    <script>
        alert('Incorrect password.');
        window.location.href = 'login.html';
    </script>
    ";

    exit();

}


/*
|--------------------------------------------------------------------------
| CHECK ACCOUNT IS ACTIVE
|--------------------------------------------------------------------------
|
| An admin can disable an account (is_active = 0) from the user
| management page. Guarded by a column check so login still works
| if the is_active migration hasn't been run yet.
|
*/

if (column_exists($conn, "users", "is_active")) {

    $check = $conn->prepare(
        "SELECT is_active FROM users WHERE id = ? LIMIT 1"
    );

    $check->bind_param("i", $user["id"]);
    $check->execute();
    $active_row = $check->get_result()->fetch_assoc();
    $check->close();

    if ($active_row && (int) $active_row["is_active"] === 0) {

        echo "
        <script>
            alert('This account has been disabled. Please contact an administrator.');
            window.location.href = 'login.html';
        </script>
        ";

        exit();
    }
}


/*
|--------------------------------------------------------------------------
| LOGIN SUCCESSFUL
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);


/*
|--------------------------------------------------------------------------
| STORE USER INFORMATION IN SESSION
|--------------------------------------------------------------------------
*/

$_SESSION["user_id"] = $user["id"];

$_SESSION["user_name"] = $user["name"];

$_SESSION["user_email"] = $user["email"];

$_SESSION["user_phone"] = $user["phone"];

$_SESSION["user_role"] = $user["role"];


/*
|--------------------------------------------------------------------------
| REDIRECT ACCORDING TO ROLE
|--------------------------------------------------------------------------
*/

/*
| ADMIN
*/

if ($user["role"] === "admin") {

    header("Location: admin-dashboard.php");
    exit();

}


/*
| RECIPIENT
*/

if ($user["role"] === "recipient") {

    header("Location: recipient-dashboard.php");
    exit();

}


/*
| DONOR
*/

header("Location: donor-dashboard.php");

exit();

?>
