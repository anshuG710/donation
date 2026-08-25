<?php

/*
|--------------------------------------------------------------------------
| DONATE+ Nepal - User Registration
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/config/database.php";


/*
|--------------------------------------------------------------------------
| Only allow POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: register.html");
    exit();

}


/*
|--------------------------------------------------------------------------
| Get Form Data
|--------------------------------------------------------------------------
*/

$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$password = $_POST["password"] ?? "";
$confirm_password = $_POST["confirm_password"] ?? "";
$role = trim($_POST["role"] ?? "");
$address = trim($_POST["address"] ?? "");
$security_question = trim($_POST["security_question"] ?? "");
$security_answer = trim($_POST["security_answer"] ?? "");


/*
|--------------------------------------------------------------------------
| Check Required Fields
|--------------------------------------------------------------------------
*/

if ($name === "" || $email === "" || $password === "") {

    echo "
    <script>
        alert('Please fill in all required fields.');
        window.location.href = 'register.html';
    </script>
    ";

    exit();

}


/*
|--------------------------------------------------------------------------
| Check Security Question + Answer
|--------------------------------------------------------------------------
|
| These are needed so the user can recover their password later on
| the forgot-password page.
|
*/

if ($security_question === "" || $security_answer === "") {

    echo "
    <script>
        alert('Please choose a security question and enter an answer.');
        window.location.href = 'register.html';
    </script>
    ";

    exit();

}


/*
|--------------------------------------------------------------------------
| Validate Email
|--------------------------------------------------------------------------
*/

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    echo "
    <script>
        alert('Please enter a valid email address.');
        window.location.href = 'register.html';
    </script>
    ";

    exit();

}


/*
|--------------------------------------------------------------------------
| Check Password Length
|--------------------------------------------------------------------------
*/

if (strlen($password) < 6) {

    echo "
    <script>
        alert('Password must be at least 6 characters.');
        window.location.href = 'register.html';
    </script>
    ";

    exit();

}


/*
|--------------------------------------------------------------------------
| Check Confirm Password
|--------------------------------------------------------------------------
*/

if ($confirm_password !== "" && $password !== $confirm_password) {

    echo "
    <script>
        alert('Passwords do not match.');
        window.location.href = 'register.html';
    </script>
    ";

    exit();

}


/*
|--------------------------------------------------------------------------
| Set Default Role
|--------------------------------------------------------------------------
*/

if ($role === "") {

    $role = "donor";

}


/*
|--------------------------------------------------------------------------
| Only Allow Donor or Recipient Registration
|--------------------------------------------------------------------------
|
| Admin accounts should not be created through public registration.
|
*/

if ($role !== "donor" && $role !== "recipient") {

    $role = "donor";

}


/*
|--------------------------------------------------------------------------
| Check Whether Email Already Exists
|--------------------------------------------------------------------------
*/

$check_sql = "SELECT id FROM users WHERE email = ? LIMIT 1";

$check_stmt = $conn->prepare($check_sql);


if (!$check_stmt) {

    die("Database error: " . $conn->error);

}


$check_stmt->bind_param("s", $email);

$check_stmt->execute();

$check_result = $check_stmt->get_result();


if ($check_result->num_rows > 0) {

    echo "
    <script>
        alert('An account with this email already exists.');
        window.location.href = 'login.html';
    </script>
    ";

    $check_stmt->close();
    $conn->close();

    exit();

}


$check_stmt->close();


/*
|--------------------------------------------------------------------------
| Hash Password
|--------------------------------------------------------------------------
*/

$hashed_password = password_hash(
    $password,
    PASSWORD_DEFAULT
);


/*
|--------------------------------------------------------------------------
| Hash Security Answer
|--------------------------------------------------------------------------
|
| Stored the same way as a password. Lower-cased first so the check on
| the forgot-password page is not case-sensitive.
|
*/

$hashed_answer = password_hash(
    strtolower($security_answer),
    PASSWORD_DEFAULT
);


/*
|--------------------------------------------------------------------------
| Insert New User
|--------------------------------------------------------------------------
*/

$sql = "INSERT INTO users
        (name, email, phone, password, role, address, security_question, security_answer)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    die("Registration failed: " . $conn->error);

}


$stmt->bind_param(
    "ssssssss",
    $name,
    $email,
    $phone,
    $hashed_password,
    $role,
    $address,
    $security_question,
    $hashed_answer
);


/*
|--------------------------------------------------------------------------
| Save User
|--------------------------------------------------------------------------
*/

if ($stmt->execute()) {

    echo "
    <!DOCTYPE html>

    <html>

    <head>

        <meta charset='UTF-8'>

        <meta name='viewport' content='width=device-width, initial-scale=1.0'>

        <title>Registration Successful | DONATE+</title>

        <meta http-equiv='refresh' content='3;url=login.html'>

        <style>

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background: #f7f5ef;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                padding: 20px;
            }

            .box {
                background: white;
                padding: 45px;
                border-radius: 20px;
                text-align: center;
                width: 420px;
                max-width: 100%;
                box-shadow: 0 15px 40px rgba(0,0,0,0.08);
            }

            .icon {
                width: 70px;
                height: 70px;
                margin: 0 auto 20px;
                border-radius: 50%;
                background: #e8f5e9;
                color: #216b45;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 35px;
                font-weight: bold;
            }

            h1 {
                color: #216b45;
                margin-bottom: 10px;
            }

            p {
                color: #666;
                line-height: 1.6;
            }

            .btn {
                display: inline-block;
                margin-top: 15px;
                padding: 12px 25px;
                background: #216b45;
                color: white;
                text-decoration: none;
                border-radius: 8px;
            }

            .btn:hover {
                background: #174d31;
            }

        </style>

    </head>

    <body>

        <div class='box'>

            <div class='icon'>✓</div>

            <h1>Registration Successful!</h1>

            <p>
                Your DONATE+ Nepal account has been created successfully.
            </p>

            <p>
                Redirecting you to the login page...
            </p>

            <a class='btn' href='login.html'>
                Go to Login
            </a>

        </div>

    </body>

    </html>
    ";

} else {

    echo "
    <script>
        alert('Registration failed: " . addslashes($stmt->error) . "');
        window.location.href = 'register.html';
    </script>
    ";

}


$stmt->close();

$conn->close();

?>
