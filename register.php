<?php

/*
|--------------------------------------------------------------------------
| DONATE+ Nepal — User Registration
|--------------------------------------------------------------------------
|
| Processes the sign-up form and, on any problem, re-renders it with an
| inline error while keeping everything the user typed (except passwords).
| On success it shows a confirmation page.
|
*/

require_once __DIR__ . "/config/database.php";


/* A direct GET goes back to the static register page. */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: register.html");
    exit();
}


/* Collect input. */

$name              = trim($_POST["name"] ?? "");
$email             = trim($_POST["email"] ?? "");
$phone             = trim($_POST["phone"] ?? "");
$password          = $_POST["password"] ?? "";
$confirm_password  = $_POST["confirm_password"] ?? "";
$role              = trim($_POST["role"] ?? "");
$address           = trim($_POST["address"] ?? "");
$security_question = trim($_POST["security_question"] ?? "");
$security_answer   = trim($_POST["security_answer"] ?? "");

$error   = "";
$success = false;


/* Validate. */

if ($name === "" || $email === "" || $password === "") {

    $error = "Please fill in all required fields.";

} elseif ($security_question === "" || $security_answer === "") {

    $error = "Please choose a security question and enter an answer.";

} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    $error = "Please enter a valid email address.";

} elseif (strlen($password) < 6) {

    $error = "Password must be at least 6 characters.";

} elseif ($confirm_password !== "" && $password !== $confirm_password) {

    $error = "Passwords do not match.";

} else {

    // Only donor or recipient can be created through public registration.
    if ($role !== "donor" && $role !== "recipient") {
        $role = "donor";
    }

    // Email already used?
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    if (!$check) {
        die("Sorry, something went wrong. Please try again.");
    }
    $check->bind_param("s", $email);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if ($exists) {

        $error = "An account with this email already exists. Try logging in instead.";

    } else {

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $hashed_answer   = password_hash(strtolower($security_answer), PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users
                (name, email, phone, password, role, address, security_question, security_answer)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            die("Sorry, we couldn't create your account. Please try again.");
        }

        $stmt->bind_param(
            "ssssssss",
            $name, $email, $phone, $hashed_password,
            $role, $address, $security_question, $hashed_answer
        );

        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = "Sorry, we couldn't create your account. Please try again.";
        }
        $stmt->close();
    }
}


/* -------------------- SUCCESS PAGE -------------------- */

if ($success) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registration Successful | DONATE+</title>
        <meta http-equiv="refresh" content="3;url=login.html">
        <style>
            *{box-sizing:border-box}
            body{margin:0;font-family:Arial,sans-serif;background:#f7f5ef;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px}
            .box{background:#fff;padding:45px;border-radius:20px;text-align:center;width:420px;max-width:100%;box-shadow:0 15px 40px rgba(0,0,0,0.08)}
            .icon{width:70px;height:70px;margin:0 auto 20px;border-radius:50%;background:#e8f5e9;color:#216b45;display:flex;align-items:center;justify-content:center;font-size:35px;font-weight:bold}
            h1{color:#216b45;margin-bottom:10px}
            p{color:#666;line-height:1.6}
            .btn{display:inline-block;margin-top:15px;padding:12px 25px;background:#216b45;color:#fff;text-decoration:none;border-radius:8px}
            .btn:hover{background:#174d31}
        </style>
    </head>
    <body>
        <div class="box">
            <div class="icon">&#10003;</div>
            <h1>Registration Successful!</h1>
            <p>Your DONATE+ Nepal account has been created successfully.</p>
            <p>Redirecting you to the login page...</p>
            <a class="btn" href="login.html">Go to Login</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}


/* -------------------- FORM (with inline error + preserved values) -------------------- */

function old($v) { return htmlspecialchars((string) $v, ENT_QUOTES, "UTF-8"); }

$questions = [
    "What city were you born in?",
    "What is your mother tongue?",
    "What is your pet's name?",
    "What is your favourite food?",
    "What was the name of your first school?",
];

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Create Your Account | DONATE+</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');
:root{--green:#1f5b3a;--green2:#2f744a;--orange:#f28c18;--cream:#fbfaf6;--ink:#171915}
*{box-sizing:border-box}
body{margin:0;font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--ink)}
.serif{font-family:'Playfair Display',serif}
a{text-decoration:none}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 20px;border-radius:10px;font-weight:700;transition:.2s;border:0;cursor:pointer}
.btn-green{background:var(--green);color:#fff}
.btn-green:hover{background:#17482e}
.auth{min-height:100vh;display:grid;place-items:center;padding:30px;background:radial-gradient(circle at 10% 10%,#edf4e9,transparent 25%),#fbfaf6}
.authbox{width:min(920px,100%);display:grid;grid-template-columns:1fr 1fr;background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 20px 70px #20382018;border:1px solid #ebe7de}
.authvisual{background:var(--green);color:#fff;padding:45px;display:flex;flex-direction:column;justify-content:center}
.authvisual h1{font-size:44px;line-height:1.05}
.authform{padding:45px}
.brand{display:flex;align-items:center;gap:10px;color:var(--green);font-weight:800}
.brandmark{width:38px;height:38px;border:2px solid var(--green);border-radius:50% 50% 45% 45%;display:grid;place-items:center;font-size:19px}
.brand small{display:block;font-size:9px;color:#7d857d;letter-spacing:.6px;font-weight:600}
.eyebrow{color:var(--green);font-weight:800;letter-spacing:1px;font-size:12px;text-transform:uppercase}
.field{margin-top:18px}
.field label{display:block;font-size:12px;font-weight:700;margin-bottom:7px}
.field input,.field select{width:100%;padding:13px;border:1px solid #ddd9d1;border-radius:10px;outline:none;font-family:'DM Sans',sans-serif;font-size:14px}
.field input:focus,.field select:focus{border-color:var(--green)}
.error-message{background:#ffe9e9;color:#a12626;border:1px solid #f2bcbc;padding:10px 12px;border-radius:8px;font-size:12px;margin-top:15px}
@media(max-width:900px){.authbox{grid-template-columns:1fr}.authvisual{display:none}}
@media(max-width:600px){.auth{padding:15px}.authform{padding:30px 22px}}
</style>
</head>
<body>
<div class="auth">
    <div class="authbox">

        <div class="authvisual">
            <a class="brand" style="color:#fff" href="index.html">
                <span class="brandmark" style="border-color:#fff">♡</span>
                <span>DONATE+<small style="color:#b9c9bd">Give Today, Change Tomorrow</small></span>
            </a>
            <div style="margin-top:80px">
                <div class="eyebrow" style="color:#ffae42">Together we can</div>
                <h1 class="serif" style="margin:12px 0">Small acts.<br>Big impact.</h1>
                <p style="color:#d6e2d9;line-height:1.8">
                    Join a community making useful donations available to people and organizations across Nepal.
                </p>
            </div>
        </div>

        <div class="authform">

            <a href="index.html" style="color:var(--green);font-weight:700;font-size:13px">← Back to home</a>
            <h2 class="serif" style="font-size:34px;margin-top:30px">Create Your Account</h2>
            <p style="color:#70776f;font-size:13px">Create an account to start donating or requesting items.</p>

            <?php if ($error !== ""): ?>
                <div class="error-message"><?php echo old($error); ?></div>
            <?php endif; ?>

            <form action="register.php" method="POST" style="margin-top:20px"
                  onsubmit="return validateForm()">

                <div class="field">
                    <label>Full Name</label>
                    <input type="text" name="name" placeholder="Your full name"
                           value="<?php echo old($name); ?>" required>
                </div>

                <div class="field">
                    <label>Phone</label>
                    <input type="text" name="phone" placeholder="98XXXXXXXX"
                           value="<?php echo old($phone); ?>" required>
                </div>

                <div class="field">
                    <label>Address</label>
                    <input type="text" name="address" placeholder="City, District"
                           value="<?php echo old($address); ?>">
                </div>

                <div class="field">
                    <label>Account Type</label>
                    <select name="role" required>
                        <option value="">Select account type</option>
                        <option value="donor" <?php echo $role === "donor" ? "selected" : ""; ?>>Donor</option>
                        <option value="recipient" <?php echo $role === "recipient" ? "selected" : ""; ?>>Recipient / Organization</option>
                    </select>
                </div>

                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="you@example.com"
                           value="<?php echo old($email); ?>" required>
                </div>

                <div class="field">
                    <label>Password</label>
                    <input type="password" name="password" id="password" placeholder="••••••••" required>
                </div>

                <div class="field">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Repeat password" required>
                </div>

                <div class="field">
                    <label>Security Question</label>
                    <select name="security_question" required>
                        <option value="">Select a question</option>
                        <?php foreach ($questions as $q): ?>
                            <option value="<?php echo old($q); ?>" <?php echo $security_question === $q ? "selected" : ""; ?>>
                                <?php echo old($q); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Answer</label>
                    <input type="text" name="security_answer" placeholder="Your answer (remember this!)" required>
                </div>

                <button type="submit" class="btn btn-green" style="width:100%;margin-top:22px">
                    Create Account
                </button>

            </form>

            <p style="font-size:13px;color:#777;text-align:center;margin-top:22px">
                Already have an account?
                <a href="login.html" style="color:var(--green);font-weight:700">Login</a>
            </p>

        </div>

    </div>
</div>

<script>
function validateForm(){
    var p = document.getElementById("password").value;
    var c = document.getElementById("confirm_password").value;
    if (p !== c) { alert("Passwords do not match."); return false; }
    if (p.length < 6) { alert("Password must contain at least 6 characters."); return false; }
    return true;
}
</script>

</body>
</html>
