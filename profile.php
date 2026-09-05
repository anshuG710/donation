<?php

/*
|--------------------------------------------------------------------------
| PROFILE  (donor + recipient)
|--------------------------------------------------------------------------
|
| A single, role-aware profile page. The logged-in user can edit their
| name and email, and see an animated "profile report" (two donut charts:
| activity by status and by category).
|
| Saving a change requires re-verifying identity: the current password,
| or — if they forgot it — the security question chosen at registration.
|
| Email is UNIQUE in the users table, so a changed email is checked for
| collisions before the row is updated; on success the new email replaces
| the old one and the session is refreshed.
|
*/

session_start();

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/helpers.php";
require_once __DIR__ . "/config/csrf.php";
require_once __DIR__ . "/config/flash.php";

/*
|--------------------------------------------------------------------------
| CHECK LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| LOAD CURRENT USER
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT id, name, email, phone, password, security_question,
            security_answer, role, created_at
     FROM users WHERE id = ? LIMIT 1"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    // Session points at a user that no longer exists — send them out.
    header("Location: logout.php");
    exit();
}

$role = $user["role"];               // donor | recipient | admin
$is_recipient = ($role === "recipient");

/*
|--------------------------------------------------------------------------
| HANDLE SAVE
|--------------------------------------------------------------------------
*/

$errors = [];
// Values to redisplay if the save fails.
$form_name  = $user["name"];
$form_email = $user["email"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_verify()) {
        $errors[] = "Your session expired. Please try again.";
    } else {

        $form_name  = trim($_POST["name"]  ?? "");
        $form_email = trim($_POST["email"] ?? "");

        $current_password = $_POST["current_password"] ?? "";
        $security_answer  = trim($_POST["security_answer"] ?? "");

        // --- validate the new details ---
        if ($form_name === "") {
            $errors[] = "Name cannot be empty.";
        } elseif (mb_strlen($form_name) > 120) {
            $errors[] = "Name is too long (max 120 characters).";
        }

        if ($form_email === "") {
            $errors[] = "Email cannot be empty.";
        } elseif (!filter_var($form_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        }

        // --- verify identity: password OR security answer ---
        $verified = false;
        if ($current_password !== "") {
            $verified = password_verify($current_password, $user["password"]);
            if (!$verified) {
                $errors[] = "Current password is incorrect.";
            }
        } elseif ($security_answer !== "") {
            if (empty($user["security_answer"])) {
                $errors[] = "No security question is set on this account. Please use your password.";
            } else {
                $verified = password_verify($security_answer, $user["security_answer"]);
                if (!$verified) {
                    $errors[] = "Security answer is incorrect.";
                }
            }
        } else {
            $errors[] = "Enter your current password, or answer your security question, to save changes.";
        }

        // --- email uniqueness (only if it actually changed) ---
        if (!$errors && strtolower($form_email) !== strtolower($user["email"])) {
            $chk = $conn->prepare(
                "SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1"
            );
            $chk->bind_param("si", $form_email, $user_id);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                $errors[] = "That email is already in use by another account.";
            }
            $chk->close();
        }

        // --- commit ---
        if (!$errors) {
            $upd = $conn->prepare(
                "UPDATE users SET name = ?, email = ? WHERE id = ?"
            );
            $upd->bind_param("ssi", $form_name, $form_email, $user_id);
            $upd->execute();
            $upd->close();

            // Refresh the session so the rest of the site shows the new details.
            $_SESSION["user_name"]  = $form_name;
            $_SESSION["user_email"] = $form_email;

            set_flash("success", "Your profile has been updated.");
            header("Location: profile.php");
            exit();
        }
    }
}

/*
|--------------------------------------------------------------------------
| CHART DATA
|--------------------------------------------------------------------------
|
| Two donut charts, role-aware:
|   - by status   (donor: donations.status | recipient: requests.status)
|   - by category (the category of the items donated / requested)
|
*/

$by_status   = [];   // label => count
$by_category = [];   // label => count

if ($is_recipient) {

    $q = $conn->prepare(
        "SELECT status, COUNT(*) AS c
         FROM donation_requests
         WHERE recipient_id = ?
         GROUP BY status"
    );
    $q->bind_param("i", $user_id);
    $q->execute();
    $r = $q->get_result();
    while ($row = $r->fetch_assoc()) {
        $by_status[ucfirst($row["status"])] = (int) $row["c"];
    }
    $q->close();

    $q = $conn->prepare(
        "SELECT COALESCE(cat.name, 'Uncategorised') AS name, COUNT(*) AS c
         FROM donation_requests req
         JOIN donations d      ON req.donation_id = d.id
         LEFT JOIN categories cat ON d.category_id = cat.id
         WHERE req.recipient_id = ?
         GROUP BY name
         ORDER BY c DESC"
    );
    $q->bind_param("i", $user_id);
    $q->execute();
    $r = $q->get_result();
    while ($row = $r->fetch_assoc()) {
        $by_category[$row["name"]] = (int) $row["c"];
    }
    $q->close();

} else {

    // Donor (default)
    $q = $conn->prepare(
        "SELECT status, COUNT(*) AS c
         FROM donations
         WHERE donor_id = ?
         GROUP BY status"
    );
    $q->bind_param("i", $user_id);
    $q->execute();
    $r = $q->get_result();
    while ($row = $r->fetch_assoc()) {
        $by_status[ucfirst($row["status"])] = (int) $row["c"];
    }
    $q->close();

    $q = $conn->prepare(
        "SELECT COALESCE(cat.name, 'Uncategorised') AS name, COUNT(*) AS c
         FROM donations d
         LEFT JOIN categories cat ON d.category_id = cat.id
         WHERE d.donor_id = ?
         GROUP BY name
         ORDER BY c DESC"
    );
    $q->bind_param("i", $user_id);
    $q->execute();
    $r = $q->get_result();
    while ($row = $r->fetch_assoc()) {
        $by_category[$row["name"]] = (int) $row["c"];
    }
    $q->close();
}

/*
| Build an animated SVG donut from a [label => count] map. Returns HTML:
| the SVG (segments animate their draw-in on load) plus a legend.
*/
function donut_chart($data, $palette)
{
    $total = array_sum($data);

    if ($total === 0) {
        return '<div class="chart-empty">No data yet</div>';
    }

    $r    = 60;                 // radius
    $cx   = 80;
    $cy   = 80;
    $circ = 2 * M_PI * $r;      // circumference

    $segments = "";
    $legend   = "";
    $offset_angle = 0;          // cumulative start angle in degrees
    $i = 0;

    foreach ($data as $label => $count) {
        $frac  = $count / $total;
        $len   = $frac * $circ;
        $color = $palette[$i % count($palette)];
        $pct   = round($frac * 100);

        // Each segment is a stroked arc, rotated to its start angle. It
        // begins hidden (dashoffset = len) and animates to 0 on load.
        $rot = ($offset_angle - 90);
        $segments .=
            '<circle class="seg" cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '"'
            . ' fill="none" stroke="' . $color . '" stroke-width="22"'
            . ' stroke-dasharray="' . round($len, 2) . ' ' . round($circ, 2) . '"'
            . ' stroke-dashoffset="' . round($len, 2) . '"'
            . ' data-target="0"'
            . ' transform="rotate(' . round($rot, 2) . ' ' . $cx . ' ' . $cy . ')"></circle>';

        $legend .=
            '<div class="lg-row">'
            . '<span class="lg-dot" style="background:' . $color . '"></span>'
            . '<span class="lg-label">' . htmlspecialchars($label) . '</span>'
            . '<span class="lg-val">' . $count . ' &middot; ' . $pct . '%</span>'
            . '</div>';

        $offset_angle += $frac * 360;
        $i++;
    }

    return
        '<div class="chart-wrap">'
        . '<svg class="donut" viewBox="0 0 160 160" width="160" height="160">'
        . $segments
        . '<text x="80" y="76" class="donut-total">' . $total . '</text>'
        . '<text x="80" y="94" class="donut-cap">total</text>'
        . '</svg>'
        . '<div class="legend">' . $legend . '</div>'
        . '</div>';
}

$palette = [
    "#10452f", "#2d7350", "#48a878", "#8fce9f",
    "#f0a020", "#d97a0f", "#3aa0c9", "#7c5cff", "#e2574c",
];

$status_title   = $is_recipient ? "My requests by status"   : "My donations by status";
$category_title = $is_recipient ? "My requests by category" : "My donations by category";

$dashboard_url = $is_recipient ? "recipient-dashboard.php" : "donor-dashboard.php";
$panel_label   = $is_recipient ? "Recipient Profile" : "Donor Profile";

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile &middot; DONATE+</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f7f6f1;
            color: #171717;
        }

        .layout { display: flex; min-height: 100vh; }

        /* SIDEBAR */
        .sidebar {
            width: 272px;
            background: #10452f;
            color: white;
            padding: 45px 20px;
            position: fixed;
            left: 0; top: 0; bottom: 0;
        }
        .brand { padding: 0 15px; margin-bottom: 45px; }
        .brand-icon {
            width: 42px; height: 42px;
            border: 2px solid #48a878; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; margin-bottom: 10px;
        }
        .brand h2 { margin: 0; font-size: 17px; letter-spacing: .5px; }
        .brand p  { margin: 5px 0 0; font-size: 11px; color: #b9dfcb; letter-spacing: .5px; }

        .menu a {
            display: block; text-decoration: none; color: #e6f1eb;
            padding: 14px 15px; border-radius: 12px; margin-bottom: 5px; font-size: 15px;
        }
        .menu a:hover, .menu a.active { background: #2d7350; color: white; }

        /* MAIN */
        .main { margin-left: 272px; width: calc(100% - 272px); min-height: 100vh; }

        .topbar {
            background: white; padding: 16px 35px;
            border-bottom: 1px solid #e5e2db;
            display: flex; align-items: center; justify-content: space-between;
        }
        .topbar small { color: #777; font-size: 15px; }
        .topbar h3 { margin: 8px 0 0; font-size: 27px; }
        .logout { text-decoration: none; color: #666; font-size: 14px; }
        .logout:hover { color: #10452f; }

        .content { padding: 35px 43px; }
        .section-label {
            color: #116348; font-size: 13px; font-weight: bold;
            letter-spacing: 1px; text-transform: uppercase; margin: 0 0 18px;
        }

        /* FLASH */
        .flash {
            display: flex; align-items: center; justify-content: space-between;
            gap: 14px; padding: 13px 18px; border-radius: 12px;
            margin-bottom: 22px; font-size: 14px;
        }
        .flash-success { background: #e7f6ec; border: 1px solid #b7e0c3; color: #1c6b3f; }
        .flash-error   { background: #fdeceb; border: 1px solid #f4c2be; color: #b23b30; }
        .flash-close {
            background: none; border: none; font-size: 20px; line-height: 1;
            cursor: pointer; color: inherit; opacity: .6;
        }
        .flash-close:hover { opacity: 1; }

        .grid { display: flex; flex-wrap: wrap; gap: 26px; align-items: flex-start; }

        .card {
            background: white; border: 1px solid #e8e5dd; border-radius: 16px;
            padding: 26px 28px;
        }
        .card h4 { margin: 0 0 4px; font-size: 18px; }
        .card .sub { margin: 0 0 20px; color: #7a7a72; font-size: 13px; }

        /* edit form */
        .edit-card { flex: 1 1 360px; max-width: 460px; }
        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: 13px; color: #444; margin-bottom: 6px; }
        .field input {
            width: 100%; padding: 12px 13px; font-size: 15px;
            border: 1px solid #d5d2c8; border-radius: 10px; background: #fbfbf8;
        }
        .field input:focus { outline: none; border-color: #48a878; background: #fff; }

        .verify-box {
            background: #f4faf4; border: 1px solid #d8ecdd; border-radius: 12px;
            padding: 16px 16px 6px; margin: 20px 0 22px;
        }
        .verify-box .vhint { font-size: 12.5px; color: #4b7a5c; margin: 0 0 12px; }
        .toggle-link {
            background: none; border: none; color: #116348; cursor: pointer;
            font-size: 13px; text-decoration: underline; padding: 0; margin-bottom: 12px;
        }

        .btn-save {
            background: #10452f; color: #fff; border: none; cursor: pointer;
            padding: 13px 26px; border-radius: 10px; font-size: 15px; font-weight: bold;
            transition: background .15s;
        }
        .btn-save:hover { background: #17603f; }

        /* charts */
        .report-card { flex: 2 1 460px; }
        .charts { display: flex; flex-wrap: wrap; gap: 34px; }
        .chart-block { flex: 1 1 240px; }
        .chart-block h5 {
            margin: 0 0 14px; font-size: 14px; color: #333;
            text-transform: uppercase; letter-spacing: .5px;
        }
        .chart-wrap { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }

        .donut { flex: 0 0 auto; }
        .donut .seg {
            transition: stroke-dashoffset 1.1s cubic-bezier(.5, 0, .2, 1);
        }
        .donut-total {
            text-anchor: middle; font-size: 26px; font-weight: bold; fill: #10452f;
        }
        .donut-cap {
            text-anchor: middle; font-size: 10px; fill: #8a8a82;
            text-transform: uppercase; letter-spacing: 1px;
        }

        .legend { flex: 1 1 130px; min-width: 130px; }
        .lg-row {
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; padding: 4px 0;
        }
        .lg-dot { width: 11px; height: 11px; border-radius: 3px; flex: 0 0 11px; }
        .lg-label { flex: 1 1 auto; color: #333; }
        .lg-val { color: #7a7a72; white-space: nowrap; }

        .chart-empty {
            padding: 30px 10px; color: #9a9a90; font-size: 14px;
            text-align: center; border: 1px dashed #dcd9cf; border-radius: 12px;
        }

        .meta-line { margin-top: 8px; font-size: 12.5px; color: #8a8a82; }

        @media (max-width: 820px) {
            .sidebar { position: static; width: 100%; height: auto; padding: 25px 20px; }
            .main { margin-left: 0; width: 100%; }
            .content { padding: 24px 18px; }
        }
    </style>

</head>

<body>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="brand">
            <div class="brand-icon">&hearts;</div>
            <h2>DONATE+</h2>
            <p>Give Today, Change Tomorrow</p>
        </div>

        <nav class="menu">
            <?php if ($is_recipient): ?>
                <a href="recipient-dashboard.php">Dashboard</a>
                <a href="find-donations.php">Find Donations</a>
                <a href="recipient-requests.php">My Requests</a>
                <a href="profile.php" class="active">Profile</a>
            <?php else: ?>
                <a href="donor-dashboard.php">Dashboard</a>
                <a href="add-donation.php">Add Donation</a>
                <a href="find-donations.php">Browse Donations</a>
                <a href="my-donations.php">My Donations</a>
                <a href="donor-requests.php">Requests</a>
                <a href="profile.php" class="active">Profile</a>
            <?php endif; ?>
        </nav>

    </aside>

    <!-- MAIN -->
    <main class="main">

        <header class="topbar">
            <div>
                <small><?php echo htmlspecialchars($panel_label); ?></small>
                <h3>My Profile</h3>
            </div>
            <a href="logout.php" class="logout">Logout</a>
        </header>

        <div class="content">

            <p class="section-label">Account &amp; Report</p>

            <?php render_flash(); ?>

            <?php if ($errors): ?>
                <div class="flash flash-error" role="alert">
                    <span><?php echo htmlspecialchars(implode(" ", $errors)); ?></span>
                    <button type="button" class="flash-close"
                            onclick="this.parentNode.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <div class="grid">

                <!-- EDIT DETAILS -->
                <div class="card edit-card">
                    <h4>Edit details</h4>
                    <p class="sub">Update your name and email. Changing your email
                        replaces the old one everywhere you sign in.</p>

                    <form method="post" action="profile.php" autocomplete="off">
                        <?php csrf_field(); ?>

                        <div class="field">
                            <label for="name">Full name</label>
                            <input type="text" id="name" name="name"
                                   value="<?php echo htmlspecialchars($form_name); ?>"
                                   maxlength="120" required>
                        </div>

                        <div class="field">
                            <label for="email">Email address</label>
                            <input type="email" id="email" name="email"
                                   value="<?php echo htmlspecialchars($form_email); ?>"
                                   maxlength="190" required>
                        </div>

                        <div class="verify-box">
                            <p class="vhint">For your security, confirm it&rsquo;s you before saving.</p>

                            <div id="pw-block">
                                <div class="field" style="margin-bottom:10px">
                                    <label for="current_password">Current password</label>
                                    <input type="password" id="current_password"
                                           name="current_password" autocomplete="off">
                                </div>
                                <?php if (!empty($user["security_question"])): ?>
                                    <button type="button" class="toggle-link"
                                            onclick="showSecurity()">Forgot password? Answer your security question instead</button>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($user["security_question"])): ?>
                                <div id="sec-block" style="display:none">
                                    <div class="field" style="margin-bottom:10px">
                                        <label><?php echo htmlspecialchars($user["security_question"]); ?></label>
                                        <input type="text" name="security_answer"
                                               id="security_answer" autocomplete="off">
                                    </div>
                                    <button type="button" class="toggle-link"
                                            onclick="showPassword()">Use my password instead</button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn-save">Save changes</button>
                    </form>

                    <p class="meta-line">
                        Role: <?php echo htmlspecialchars(ucfirst($role)); ?>
                        &middot; Member since
                        <?php echo htmlspecialchars(date("M Y", strtotime($user["created_at"]))); ?>
                    </p>
                </div>

                <!-- PROFILE REPORT -->
                <div class="card report-card">
                    <h4>Profile report</h4>
                    <p class="sub">A quick visual summary of your activity on DONATE+.</p>

                    <div class="charts">
                        <div class="chart-block">
                            <h5><?php echo htmlspecialchars($status_title); ?></h5>
                            <?php echo donut_chart($by_status, $palette); ?>
                        </div>
                        <div class="chart-block">
                            <h5><?php echo htmlspecialchars($category_title); ?></h5>
                            <?php echo donut_chart($by_category, $palette); ?>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </main>

</div>

<script>
    // Toggle between password and security-question verification.
    function showSecurity() {
        var p = document.getElementById('pw-block');
        var s = document.getElementById('sec-block');
        if (p) p.style.display = 'none';
        if (s) s.style.display = 'block';
        var pw = document.getElementById('current_password');
        if (pw) pw.value = '';
        var sa = document.getElementById('security_answer');
        if (sa) sa.focus();
    }
    function showPassword() {
        var p = document.getElementById('pw-block');
        var s = document.getElementById('sec-block');
        if (s) s.style.display = 'none';
        if (p) p.style.display = 'block';
        var sa = document.getElementById('security_answer');
        if (sa) sa.value = '';
        var pw = document.getElementById('current_password');
        if (pw) pw.focus();
    }

    // Animate the donut segments: they start hidden and "draw in" on load.
    window.addEventListener('load', function () {
        requestAnimationFrame(function () {
            document.querySelectorAll('.donut .seg').forEach(function (seg) {
                seg.style.strokeDashoffset = seg.getAttribute('data-target');
            });
        });
    });
</script>

</body>
</html>
