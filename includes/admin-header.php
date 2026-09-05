<?php

/*
|--------------------------------------------------------------------------
| ADMIN HEADER / LAYOUT OPEN
|--------------------------------------------------------------------------
|
| Include AFTER config/admin-guard.php. Before including, a page may set:
|
|     $page_title   — <title> and header eyebrow      (default "Admin")
|     $page_heading — big serif heading in the content (default $page_title)
|     $active_nav   — which sidebar link is highlighted:
|                     dashboard | users | donations | requests |
|                     categories | reports
|
| Then include includes/admin-footer.php at the end of the page.
|
*/

$page_title   = $page_title   ?? "Admin";
$page_heading = $page_heading ?? $page_title;
$active_nav   = $active_nav   ?? "";

$nav_active = function ($key) use ($active_nav) {
    return $key === $active_nav ? ' class="active"' : "";
};

$header_name = htmlspecialchars($admin_name ?? "Admin");
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= htmlspecialchars($page_title) ?> | DONATE+</title>

<style>

@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');

:root {
    --green:#1f5b3a;
    --green2:#2f744a;
    --orange:#f28c18;
    --cream:#fbfaf6;
    --ink:#171915;
}

* { box-sizing:border-box; }

body {
    margin:0;
    font-family:'DM Sans',sans-serif;
    background:#f7f7f3;
    color:var(--ink);
}

a { text-decoration:none; }

.serif { font-family:'Playfair Display',serif; }


/* SIDEBAR */

.side {
    width:245px;
    background:#173c28;
    color:#fff;
    position:fixed;
    left:0; top:0; bottom:0;
    padding:25px 18px;
    overflow-y:auto;
}

.brand {
    display:flex;
    align-items:center;
    gap:10px;
    color:#fff;
    font-weight:800;
    margin-bottom:30px;
}

.brandmark {
    width:38px; height:38px;
    border:2px solid #fff;
    border-radius:50%;
    display:grid;
    place-items:center;
    font-size:19px;
}

.brand small {
    display:block;
    font-size:9px;
    color:#b9c9bd;
    letter-spacing:.6px;
    font-weight:600;
}

.side a:not(.brand) {
    display:block;
    color:#cddbd0;
    padding:12px 14px;
    border-radius:10px;
    font-size:13px;
    margin-top:5px;
}

.side a:not(.brand).active,
.side a:not(.brand):hover {
    background:#2d6746;
    color:#fff;
}

.side .nav-group {
    font-size:10px;
    letter-spacing:1px;
    text-transform:uppercase;
    color:#7fa088;
    margin:22px 0 4px 14px;
}


/* MAIN */

.dashmain { margin-left:245px; }

.dashhead {
    height:72px;
    background:#fff;
    border-bottom:1px solid #e8e5de;
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:0 30px;
}

.dashhead small { color:#777; }

.dashhead h1 { font-size:22px; font-weight:800; margin:3px 0 0; }

.logout { color:#697069; font-size:12px; }
.logout:hover { color:var(--green); }

.dashcontent { padding:30px; }


/* TOP SECTION */

.top-section {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:20px;
    flex-wrap:wrap;
}

.eyebrow {
    color:var(--green);
    font-weight:800;
    letter-spacing:1px;
    font-size:12px;
    text-transform:uppercase;
}

.top-section h2 { font-size:34px; margin:8px 0; }


/* BUTTONS */

.btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:11px 18px;
    border-radius:10px;
    font-weight:700;
    font-size:13px;
    border:none;
    cursor:pointer;
    transition:.2s;
    font-family:inherit;
}

.btn-green   { background:var(--green); color:#fff; }
.btn-green:hover { background:#17482e; }

.btn-orange  { background:var(--orange); color:#fff; }
.btn-orange:hover { background:#d97b0a; }

.btn-ghost   { background:#eef1ec; color:#33463a; }
.btn-ghost:hover { background:#e2e7df; }

.btn-danger  { background:#fde8e8; color:#a33a3a; }
.btn-danger:hover { background:#f8d5d5; }

.btn-sm { padding:7px 12px; font-size:12px; border-radius:8px; }


/* KPI CARDS */

.kpis {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(170px,1fr));
    gap:15px;
    margin-top:25px;
}

.kpi {
    background:#fff;
    border:1px solid #e8e5de;
    border-radius:16px;
    padding:20px;
}

.kpi span { font-size:11px; color:#7a8179; }

.kpi b {
    font-family:'Playfair Display',serif;
    font-size:30px;
    display:block;
    margin-top:5px;
}


/* TABLE */

.table {
    background:#fff;
    border:1px solid #e8e5de;
    border-radius:16px;
    margin-top:20px;
    overflow:hidden;
}

.table-title {
    padding:20px;
    font-weight:800;
    border-bottom:1px solid #eee;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}

.table-scroll { overflow-x:auto; }

table { width:100%; border-collapse:collapse; font-size:12px; }

th, td { text-align:left; padding:15px; border-bottom:1px solid #eee; vertical-align:middle; }

th { color:#697069; font-size:11px; }

td { color:#303630; }

tr:last-child td { border-bottom:none; }


/* STATUS PILLS */

.status {
    padding:5px 9px;
    border-radius:20px;
    font-size:10px;
    font-weight:700;
    text-transform:capitalize;
    display:inline-block;
    white-space:nowrap;
}

.status.pending,
.status.requested   { background:#fff0dc; color:#b56300; }

.status.approved,
.status.available,
.status.active,
.status.donor       { background:#e8f4e8; color:#27713e; }

.status.completed,
.status.recipient   { background:#e9edf7; color:#40517b; }

.status.rejected,
.status.cancelled,
.status.inactive    { background:#fde8e8; color:#a33a3a; }

.status.admin       { background:#f3e9ff; color:#6b3fa0; }

.status.neutral     { background:#eef1ec; color:#556; }


/* FORMS */

.field { margin-bottom:16px; }

.field label {
    display:block;
    font-size:12px;
    font-weight:600;
    color:#4a544b;
    margin-bottom:6px;
}

.field input,
.field select,
.field textarea {
    width:100%;
    padding:11px 13px;
    border:1px solid #d9ddd4;
    border-radius:10px;
    font-family:inherit;
    font-size:13px;
    background:#fff;
}

.field input:focus,
.field select:focus,
.field textarea:focus {
    outline:none;
    border-color:var(--green2);
}

.card {
    background:#fff;
    border:1px solid #e8e5de;
    border-radius:16px;
    padding:24px;
    margin-top:20px;
}


/* TOOLBAR (search + filters) */

.toolbar {
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
}

.toolbar input,
.toolbar select {
    padding:9px 12px;
    border:1px solid #d9ddd4;
    border-radius:9px;
    font-family:inherit;
    font-size:13px;
    background:#fff;
}


/* FLASH */

.flash {
    padding:13px 16px;
    border-radius:11px;
    font-size:13px;
    font-weight:600;
    margin-top:18px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
}

.flash-success { background:#e8f4e8; color:#27713e; border:1px solid #cfe6d2; }
.flash-error   { background:#fde8e8; color:#a33a3a; border:1px solid #f2cccc; }

.flash-close {
    background:transparent;
    border:0;
    color:inherit;
    font-size:20px;
    line-height:1;
    cursor:pointer;
    opacity:.55;
    padding:0 2px;
    flex:0 0 auto;
}

.flash-close:hover { opacity:1; }


/* PAGINATION */

.pagination {
    display:flex;
    gap:6px;
    padding:16px 20px;
    justify-content:flex-end;
    flex-wrap:wrap;
}

.pagination a,
.pagination span {
    padding:7px 12px;
    border-radius:8px;
    font-size:12px;
    font-weight:600;
    border:1px solid #e2e5dd;
    color:#4a544b;
}

.pagination a:hover { background:#eef1ec; }

.pagination .current { background:var(--green); color:#fff; border-color:var(--green); }


/* EMPTY STATE */

.empty { padding:45px 20px; text-align:center; color:#777; }
.empty strong { display:block; color:#333; margin-bottom:6px; }

.inline-form { display:inline; }

.actions-cell { display:flex; gap:6px; flex-wrap:wrap; }


/* MOBILE */

@media(max-width:900px) {
    .side { display:none; }
    .dashmain { margin-left:0; }
    .top-section { align-items:flex-start; flex-direction:column; }
}

@media(max-width:600px) {
    .dashcontent { padding:18px; }
    .dashhead { padding:0 18px; }
}

</style>

</head>

<body>


<!-- SIDEBAR -->
<aside class="side">

    <a class="brand" href="admin-dashboard.php">
        <span class="brandmark">&#9825;</span>
        <span>
            DONATE+
            <small>Give Today, Change Tomorrow</small>
        </span>
    </a>

    <a<?= $nav_active("dashboard") ?> href="admin-dashboard.php">Dashboard</a>

    <div class="nav-group">Manage</div>
    <a<?= $nav_active("users") ?>      href="admin-users.php">Users</a>
    <a<?= $nav_active("donations") ?>  href="admin-donations.php">Donations</a>
    <a<?= $nav_active("requests") ?>   href="admin-requests.php">Requests</a>
    <a<?= $nav_active("categories") ?> href="admin-categories.php">Categories</a>
    <a<?= $nav_active("campaigns") ?>  href="admin-campaigns.php">Campaigns</a>

    <div class="nav-group">Insights</div>
    <a<?= $nav_active("reports") ?>    href="admin-reports.php">Reports</a>
    <a<?= $nav_active("activity") ?>   href="admin-activity.php">Activity Log</a>

</aside>


<!-- MAIN -->
<main class="dashmain">

    <header class="dashhead">
        <div>
            <small><?= htmlspecialchars($page_title) ?></small>
            <h1>Welcome, <?= $header_name ?></h1>
        </div>
        <a href="logout.php" class="logout">Logout</a>
    </header>

    <div class="dashcontent">

        <?php render_flash(); ?>
