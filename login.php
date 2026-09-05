<?php

session_start();

/*
|--------------------------------------------------------------------------
| DONATE+ Nepal — Login
|--------------------------------------------------------------------------
|
| Processes the login form AND re-renders it with an inline error (keeping
| the typed email) when something is wrong — no more alert() popups or lost
| input. On success it redirects to the correct dashboard.
|
*/

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/helpers.php";


/*
| A direct GET goes back to the static login page.
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.html");
    exit();
}


$error = "";
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";


if ($email === "" || $password === "") {

    $error = "Please enter your email and password.";

} else {

    $stmt = $conn->prepare(
        "SELECT id, name, email, phone, password, role
         FROM users
         WHERE email = ?
         LIMIT 1"
    );

    if (!$stmt) {
        die("Sorry, something went wrong. Please try again.");
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        $error = "No account found with this email.";

    } else {

        $user = $result->fetch_assoc();

        // Check the account is active (guarded so it works pre-migration).
        $disabled = false;
        if (column_exists($conn, "users", "is_active")) {
            $check = $conn->prepare("SELECT is_active FROM users WHERE id = ? LIMIT 1");
            $check->bind_param("i", $user["id"]);
            $check->execute();
            $active_row = $check->get_result()->fetch_assoc();
            $check->close();
            $disabled = $active_row && (int) $active_row["is_active"] === 0;
        }

        if (!password_verify($password, $user["password"])) {

            $error = "Incorrect password.";

        } elseif ($disabled) {

            $error = "This account has been disabled. Please contact an administrator.";

        } else {

            /* SUCCESS */
            session_regenerate_id(true);

            $_SESSION["user_id"]    = $user["id"];
            $_SESSION["user_name"]  = $user["name"];
            $_SESSION["user_email"] = $user["email"];
            $_SESSION["user_phone"] = $user["phone"];
            $_SESSION["user_role"]  = $user["role"];

            // Return the user to where they were headed (e.g. donating to a
            // campaign), if it's a safe local target. Whitelisted, so this
            // can't be used as an open redirect.
            $next = $_POST["next"] ?? "";
            if ($next !== "" && preg_match('#^add-donation\.php(\?campaign=\d+)?$#', $next)) {
                header("Location: " . $next);
                exit();
            }

            if ($user["role"] === "admin") {
                header("Location: admin-dashboard.php");
            } elseif ($user["role"] === "recipient") {
                header("Location: recipient-dashboard.php");
            } else {
                header("Location: donor-dashboard.php");
            }
            exit();
        }
    }
}

// If we get here, login failed — re-show the form with the error + email.
$email_safe = htmlspecialchars($email, ENT_QUOTES, "UTF-8");
$error_safe = htmlspecialchars($error, ENT_QUOTES, "UTF-8");

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Welcome Back | DONATE+</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');
:root{--green:#1f5b3a;--green2:#2f744a;--orange:#f28c18;--cream:#fbfaf6;--ink:#171915}
*{box-sizing:border-box}
body{margin:0;font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--ink)}
.serif{font-family:'Playfair Display',serif}
a{text-decoration:none}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 20px;border-radius:10px;font-weight:700;transition:.2s;border:none;cursor:pointer}
.btn-green{background:var(--green);color:#fff}
.btn-green:hover{background:#17482e}
.auth{min-height:100vh;display:grid;place-items:center;padding:30px;background:radial-gradient(circle at 10% 10%,#edf4e9,transparent 25%),#fbfaf6}
.authbox{width:min(920px,100%);display:grid;grid-template-columns:1fr 1fr;background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 20px 70px #20382018;border:1px solid #ebe7de}
.authvisual{background:var(--green);color:#fff;padding:45px;display:flex;flex-direction:column;justify-content:center}
.brand{display:flex;align-items:center;gap:10px;color:var(--green);font-weight:800}
.brandmark{width:38px;height:38px;border:2px solid currentColor;border-radius:50% 50% 45% 45%;display:grid;place-items:center;font-size:19px}
.brand small{display:block;font-size:9px;color:#7d857d;letter-spacing:.6px;font-weight:600}
.authvisual h1{font-size:44px;line-height:1.05}
.eyebrow{color:var(--green);font-weight:800;letter-spacing:1px;font-size:12px;text-transform:uppercase}
.authform{padding:45px}
.field{margin-top:18px}
.field label{display:block;font-size:12px;font-weight:700;margin-bottom:7px}
.field input{width:100%;padding:13px;border:1px solid #ddd9d1;border-radius:10px;outline:none;font-family:'DM Sans',sans-serif;font-size:14px}
.field input:focus{border-color:var(--green);box-shadow:0 0 0 3px #1f5b3a12}
.message{margin-top:15px;padding:12px;border-radius:10px;font-size:13px}
.error{background:#fff0f0;color:#a32b2b;border:1px solid #f0caca}
@media(max-width:900px){.authbox{grid-template-columns:1fr}.authvisual{display:none}}
@media(max-width:600px){.auth{padding:15px}.authform{padding:30px 22px}}
</style>
</head>
<body>
<div class="auth">
    <div class="authbox">

        <div class="authvisual">
            <a class="brand" style="color:#fff" href="index.html">
                <span class="brandmark">♡</span>
                <span>DONATE+<small style="color:#b9c9bd">Give Today, Change Tomorrow</small></span>
            </a>
            <div style="margin-top:80px">
                <div class="eyebrow" style="color:#ffae42">Together we can</div>
                <h1 class="serif" style="margin:12px 0">Small acts.<br>Big impact.</h1>
                <p style="color:#d6e2d9;line-height:1.8">
                    Join a community making useful donations available
                    to people and organizations across Nepal.
                </p>
            </div>
        </div>

        <div class="authform">

            <a href="index.html" style="color:var(--green);font-weight:700;font-size:13px">← Back to home</a>

            <h2 class="serif" style="font-size:34px;margin-top:30px">Welcome Back</h2>
            <p style="color:#70776f;font-size:13px">Welcome back. Sign in to continue.</p>

            <?php if ($error_safe !== ""): ?>
                <div class="message error"><?php echo $error_safe; ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST" style="margin-top:20px">

                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="you@example.com"
                           value="<?php echo $email_safe; ?>" required>
                </div>

                <div class="field">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>

                <div style="text-align:right;margin-top:10px">
                    <a href="forgot-password.php" style="color:var(--green);font-weight:600;font-size:12px">
                        Forgot password?
                    </a>
                </div>

                <button type="submit" class="btn btn-green" style="width:100%;margin-top:22px">
                    Login
                </button>

            </form>

            <p style="font-size:13px;color:#777;text-align:center;margin-top:22px">
                Don't have an account?
                <a href="register.html" style="color:var(--green);font-weight:700">Create one</a>
            </p>

        </div>

    </div>
</div>
</body>
</html>
