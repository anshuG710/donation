<?php

/*
|--------------------------------------------------------------------------
| DONATE+ Nepal - Forgot Password (Security Question)
|--------------------------------------------------------------------------
|
| Lets a user reset their own password without any email, by answering the
| security question they picked when they registered. Three steps:
|
|   1. enter email         -> we look up the account and show its question
|   2. answer the question -> if correct, allow a new password to be set
|   3. set a new password  -> update the account and send them to login
|
*/

session_start();

require_once __DIR__ . "/config/database.php";


/*
|--------------------------------------------------------------------------
| Page State
|--------------------------------------------------------------------------
|
| $stage decides which form is shown: "email" | "answer" | "reset".
|
*/

$stage = "email";
$error = "";
$email = "";
$question = "";


/*
|--------------------------------------------------------------------------
| Handle Form Submissions
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $step = $_POST["step"] ?? "";


    /* ---- STEP 1: find the account by email ---- */

    if ($step === "find_email") {

        $email = trim($_POST["email"] ?? "");

        $stmt = $conn->prepare(
            "SELECT security_question, security_answer
             FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {

            $error = "No account found with that email.";

        } elseif (empty($user["security_question"]) || empty($user["security_answer"])) {

            $error = "This account has no security question set. Please contact an admin to reset your password.";

        } else {

            // account is good - show its question
            $stage = "answer";
            $question = $user["security_question"];

        }
    }


    /* ---- STEP 2: check the security answer ---- */

    if ($step === "check_answer") {

        $email = trim($_POST["email"] ?? "");
        $answer = trim($_POST["security_answer"] ?? "");

        $stmt = $conn->prepare(
            "SELECT security_question, security_answer
             FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || empty($user["security_answer"])) {

            $error = "Something went wrong. Please start again.";
            $stage = "email";

        } elseif (password_verify(strtolower($answer), $user["security_answer"])) {

            // correct answer - remember which email is allowed to reset
            $_SESSION["reset_email"] = $email;
            $stage = "reset";

        } else {

            $error = "That answer is not correct. Please try again.";
            $stage = "answer";
            $question = $user["security_question"];

        }
    }


    /* ---- STEP 3: set the new password ---- */

    if ($step === "reset_password") {

        $new_password = $_POST["password"] ?? "";
        $confirm_password = $_POST["confirm_password"] ?? "";
        $reset_email = $_SESSION["reset_email"] ?? "";

        if ($reset_email === "") {

            $error = "Your session expired. Please start again.";
            $stage = "email";

        } elseif (strlen($new_password) < 6) {

            $error = "Password must be at least 6 characters.";
            $stage = "reset";

        } elseif ($new_password !== $confirm_password) {

            $error = "Passwords do not match.";
            $stage = "reset";

        } else {

            $hashed = password_hash($new_password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->bind_param("ss", $hashed, $reset_email);
            $ok = $stmt->execute();
            $stmt->close();

            // clear the reset permission either way
            unset($_SESSION["reset_email"]);

            if ($ok) {

                echo "
                <script>
                    alert('Your password has been reset. Please log in with your new password.');
                    window.location.href = 'login.html';
                </script>
                ";
                exit();

            } else {

                $error = "Could not update the password. Please try again.";
                $stage = "email";

            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Forgot Password | DONATE+</title>

<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');

:root{
    --green:#1f5b3a;
    --green2:#2f744a;
    --orange:#f28c18;
    --cream:#fbfaf6;
    --ink:#171915;
}

*{
    box-sizing:border-box;
}

body{
    margin:0;
    font-family:'DM Sans',sans-serif;
    background:var(--cream);
    color:var(--ink);
}

.serif{
    font-family:'Playfair Display',serif;
}

a{
    text-decoration:none;
}

.btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:13px 20px;
    border-radius:10px;
    font-weight:700;
    border:0;
    cursor:pointer;
}

.btn-green{
    background:var(--green);
    color:#fff;
}

.btn-green:hover{
    background:#17482e;
}

.auth{
    min-height:100vh;
    display:grid;
    place-items:center;
    padding:30px;
    background:
        radial-gradient(circle at 10% 10%,#edf4e9,transparent 25%),
        #fbfaf6;
}

.authbox{
    width:min(460px,100%);
    background:#fff;
    border-radius:24px;
    padding:45px;
    box-shadow:0 20px 70px #20382018;
    border:1px solid #ebe7de;
}

.brand{
    display:flex;
    align-items:center;
    gap:10px;
    color:var(--green);
    font-weight:800;
    margin-bottom:20px;
}

.brandmark{
    width:38px;
    height:38px;
    border:2px solid var(--green);
    border-radius:50% 50% 45% 45%;
    display:grid;
    place-items:center;
    font-size:19px;
}

.brand small{
    display:block;
    font-size:9px;
    color:#7d857d;
    letter-spacing:.6px;
    font-weight:600;
}

.field{
    margin-top:18px;
}

.field label{
    display:block;
    font-size:12px;
    font-weight:700;
    margin-bottom:7px;
}

.field input{
    width:100%;
    padding:13px;
    border:1px solid #ddd9d1;
    border-radius:10px;
    outline:none;
    font-family:'DM Sans',sans-serif;
    font-size:14px;
}

.field input:focus{
    border-color:var(--green);
}

.question-box{
    background:#eef4ea;
    border:1px solid #d5e6d3;
    border-radius:10px;
    padding:12px 14px;
    margin-top:18px;
    font-size:13px;
    color:#33463a;
}

.error-message{
    background:#ffe9e9;
    color:#a12626;
    border:1px solid #f2bcbc;
    padding:10px 12px;
    border-radius:8px;
    font-size:12px;
    margin-top:15px;
}

@media(max-width:600px){

    .auth{
        padding:15px;
    }

    .authbox{
        padding:30px 22px;
    }
}
</style>

</head>

<body>

<div class="auth">

    <div class="authbox">

        <a class="brand" href="index.html">
            <span class="brandmark">♡</span>
            <span>
                DONATE+
                <small>Give Today, Change Tomorrow</small>
            </span>
        </a>

        <h2 class="serif" style="font-size:28px;margin:10px 0 4px">
            Reset your password
        </h2>

        <p style="color:#70776f;font-size:13px;margin:0">
            <?php
            if ($stage === "email") {
                echo "Enter your email to start.";
            } elseif ($stage === "answer") {
                echo "Answer your security question to continue.";
            } else {
                echo "Choose a new password for your account.";
            }
            ?>
        </p>


        <?php if ($error !== ""): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>


        <?php if ($stage === "email"): ?>

            <!-- STEP 1: EMAIL -->

            <form action="forgot-password.php" method="POST">

                <input type="hidden" name="step" value="find_email">

                <div class="field">
                    <label>Email</label>
                    <input
                        type="email"
                        name="email"
                        placeholder="you@example.com"
                        value="<?php echo htmlspecialchars($email); ?>"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-green" style="width:100%;margin-top:22px">
                    Continue
                </button>

            </form>


        <?php elseif ($stage === "answer"): ?>

            <!-- STEP 2: SECURITY ANSWER -->

            <form action="forgot-password.php" method="POST">

                <input type="hidden" name="step" value="check_answer">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

                <div class="question-box">
                    <strong>Security question:</strong><br>
                    <?php echo htmlspecialchars($question); ?>
                </div>

                <div class="field">
                    <label>Your Answer</label>
                    <input
                        type="text"
                        name="security_answer"
                        placeholder="Type your answer"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-green" style="width:100%;margin-top:22px">
                    Verify Answer
                </button>

            </form>


        <?php else: ?>

            <!-- STEP 3: NEW PASSWORD -->

            <form action="forgot-password.php" method="POST">

                <input type="hidden" name="step" value="reset_password">

                <div class="field">
                    <label>New Password</label>
                    <input
                        type="password"
                        name="password"
                        placeholder="At least 6 characters"
                        required
                    >
                </div>

                <div class="field">
                    <label>Confirm New Password</label>
                    <input
                        type="password"
                        name="confirm_password"
                        placeholder="Repeat new password"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-green" style="width:100%;margin-top:22px">
                    Reset Password
                </button>

            </form>

        <?php endif; ?>


        <p style="font-size:13px;color:#777;text-align:center;margin-top:22px">
            Remembered it?
            <a href="login.html" style="color:var(--green);font-weight:700">
                Back to login
            </a>
        </p>

    </div>

</div>

</body>
</html>
