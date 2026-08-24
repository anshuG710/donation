<?php

/*
|--------------------------------------------------------------------------
| ADMIN — REPORTS & ANALYTICS
|--------------------------------------------------------------------------
|
| Trends over the last 6 months, breakdowns by category / status / role,
| and CSV export of users, donations and requests.
|
| CSV export is handled first (before any HTML output).
|
*/

require_once __DIR__ . "/config/admin-guard.php";


/*
|--------------------------------------------------------------------------
| CSV EXPORT
|--------------------------------------------------------------------------
*/

$export = $_GET["export"] ?? "";

if (in_array($export, ["users", "donations", "requests"], true)) {

    $filename = "donate-" . $export . "-" . date("Ymd") . ".csv";

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=" . $filename);

    $out = fopen("php://output", "w");

    if ($export === "users") {

        $has_active = column_exists($conn, "users", "is_active");

        $cols = ["id", "name", "email", "phone", "role"];
        if ($has_active) { $cols[] = "is_active"; }
        $cols[] = "created_at";
        fputcsv($out, $cols);

        $active_sel = $has_active ? ", is_active" : "";
        $res = $conn->query("SELECT id, name, email, phone, role$active_sel, created_at FROM users ORDER BY id ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $line = [
                    $row["id"], $row["name"], $row["email"],
                    $row["phone"], $row["role"],
                ];
                if ($has_active) { $line[] = $row["is_active"]; }
                $line[] = $row["created_at"];
                fputcsv($out, $line);
            }
        }
    }

    if ($export === "donations") {

        fputcsv($out, ["id", "title", "donor", "category", "quantity", "unit", "status", "created_at"]);

        $sql = "
            SELECT d.id, d.title, u.name AS donor, c.name AS category,
                   d.quantity, d.unit, d.status, d.created_at
            FROM donations d
            LEFT JOIN users u      ON d.donor_id = u.id
            LEFT JOIN categories c ON d.category_id = c.id
            ORDER BY d.id ASC
        ";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                fputcsv($out, [
                    $row["id"], $row["title"], $row["donor"],
                    $row["category"] ?? "Uncategorised", $row["quantity"],
                    $row["unit"], $row["status"], $row["created_at"],
                ]);
            }
        }
    }

    if ($export === "requests") {

        fputcsv($out, ["id", "donation", "recipient", "quantity", "status", "collection_date", "created_at"]);

        $sql = "
            SELECT dr.id, d.title AS donation, u.name AS recipient,
                   dr.quantity, dr.status, dr.collection_date, dr.created_at
            FROM donation_requests dr
            LEFT JOIN donations d ON dr.donation_id = d.id
            LEFT JOIN users u     ON dr.recipient_id = u.id
            ORDER BY dr.id ASC
        ";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                fputcsv($out, [
                    $row["id"], $row["donation"], $row["recipient"],
                    $row["quantity"], $row["status"],
                    $row["collection_date"], $row["created_at"],
                ]);
            }
        }
    }

    fclose($out);
    exit();
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

// Return an assoc map ym => count for a table, keyed 'YYYY-MM'.
function monthly_counts($conn, $table)
{
    $map = [];
    $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
            FROM $table GROUP BY ym";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $map[$row["ym"]] = (int) $row["c"];
        }
    }
    return $map;
}

// Build the last 6 month buckets: [ ['key'=>'2026-08','label'=>'Aug','count'=>N], ... ]
function last_six_months($map)
{
    $buckets = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts    = strtotime("first day of -$i month");
        $key   = date("Y-m", $ts);
        $label = date("M", $ts);
        $buckets[] = [
            "key"   => $key,
            "label" => $label,
            "count" => $map[$key] ?? 0,
        ];
    }
    return $buckets;
}

// Simple grouped counts: returns [ ['label'=>..,'count'=>..], .. ]
function grouped_counts($conn, $sql, $label_key = "label", $count_key = "c")
{
    $rows = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = [
                "label" => $row[$label_key],
                "count" => (int) $row[$count_key],
            ];
        }
    }
    return $rows;
}


/*
|--------------------------------------------------------------------------
| DATA
|--------------------------------------------------------------------------
*/

$don_months = last_six_months(monthly_counts($conn, "donations"));
$req_months = last_six_months(monthly_counts($conn, "donation_requests"));

// Breakdowns
$by_category = grouped_counts($conn, "
    SELECT COALESCE(c.name, 'Uncategorised') AS label, COUNT(d.id) AS c
    FROM donations d
    LEFT JOIN categories c ON d.category_id = c.id
    GROUP BY label
    ORDER BY c DESC
");

$don_by_status = grouped_counts($conn, "
    SELECT status AS label, COUNT(*) AS c
    FROM donations GROUP BY status ORDER BY c DESC
");

$req_by_status = grouped_counts($conn, "
    SELECT status AS label, COUNT(*) AS c
    FROM donation_requests GROUP BY status ORDER BY c DESC
");

$users_by_role = grouped_counts($conn, "
    SELECT role AS label, COUNT(*) AS c
    FROM users GROUP BY role ORDER BY c DESC
");


/*
|--------------------------------------------------------------------------
| RENDER
|--------------------------------------------------------------------------
*/

$page_title = "Reports";
$active_nav = "reports";

require_once __DIR__ . "/includes/admin-header.php";


// Small helper to draw a horizontal bar row.
// $i is the row index within its card — drives the mount-animation stagger.
function bar_row($label, $count, $max, $status_class = null, $i = 0)
{
    $pct = $max > 0 ? round(($count / $max) * 100) : 0;
    $pct = max($pct, $count > 0 ? 4 : 0); // keep a sliver visible for small values
    $cls = $status_class ? (" bar-" . $status_class) : "";
    echo '<div class="bar-row" style="--i:' . (int) $i . '">';
    echo '<div class="bar-label">' . e($label) . '</div>';
    echo '<div class="bar-track"><div class="bar-fill' . $cls . '" style="--w:' . $pct . '%"></div></div>';
    echo '<div class="bar-value">' . (int) $count . '</div>';
    echo '</div>';
}

// Column-chart max helpers
$don_max = 0; foreach ($don_months as $m) { $don_max = max($don_max, $m["count"]); }
$req_max = 0; foreach ($req_months as $m) { $req_max = max($req_max, $m["count"]); }
$cat_max = 0; foreach ($by_category as $r) { $cat_max = max($cat_max, $r["count"]); }
?>


<style>
/* Report-specific chart styles (brand green + semantic status colors) */

/*
 * Chart mount animations — a pure-CSS take on the shadcn/Recharts feel.
 * Recharts grows each mark from its baseline on mount (bars: height/width
 * from 0 -> target) with an "ease" curve and a short duration, and it honours
 * prefers-reduced-motion. We reproduce that here: columns grow up from the
 * axis, horizontal bars wipe in from the left, labels settle in just after,
 * all with a gentle per-mark stagger so a chart reads as one coordinated
 * motion rather than a pop. Timings live in these vars so they're easy to tune.
 */
:root {
    --chart-ease: cubic-bezier(0.22, 1, 0.36, 1); /* smooth ease-out, shadcn-like */
    --chart-grow: 0.72s;   /* bar/column growth */
    --chart-settle: 0.5s;  /* label + card settle */
    --chart-stagger: 70ms; /* delay between successive marks */
}

.report-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px; }
@media(max-width:900px){ .report-grid{ grid-template-columns:1fr; } }

.chart-card {
    background:#fff; border:1px solid #e8e5de; border-radius:16px; padding:22px;
    transition: box-shadow .25s ease, border-color .25s ease, transform .25s ease;
    animation: card-in var(--chart-settle) var(--chart-ease) backwards;
}
.chart-card:hover {
    box-shadow: 0 10px 30px -12px rgba(31,91,58,.22);
    border-color:#d9ddd0;
    transform: translateY(-2px);
}
.chart-card h3 { margin:0 0 4px; font-size:15px; }
.chart-card .sub { color:#7a8179; font-size:12px; margin-bottom:18px; }

/* Column chart (months) */
.columns {
    display:flex; align-items:flex-end; gap:12px; height:170px; padding-top:10px;
    /* faint baseline + evenly spaced gridlines behind the columns */
    border-bottom:1px solid #e8e5de;
    background-image: repeating-linear-gradient(to top, transparent 0, transparent 41px, #f1f2ed 41px, #f1f2ed 42px);
    background-position: bottom;
}
.col { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%; position:relative; }
.col .col-val {
    font-size:12px; font-weight:700; color:#33463a; margin-bottom:6px;
    animation: label-in var(--chart-settle) var(--chart-ease) both;
    animation-delay: calc(var(--i, 0) * var(--chart-stagger) + 0.22s);
}
.col .col-bar {
    width:100%; max-width:46px; background:#1f5b3a; border-radius:6px 6px 0 0;
    min-height:3px;
    height: var(--h, 0);
    transform-origin:bottom;
    animation: col-grow var(--chart-grow) var(--chart-ease) both;
    animation-delay: calc(var(--i, 0) * var(--chart-stagger));
    transition: filter .2s ease;
}
.col .col-bar-alt { background:#5a70a8; }
.col:hover .col-bar { filter:brightness(1.12) saturate(1.05); }
.col .col-label { font-size:11px; color:#7a8179; margin-top:8px; }

/* Horizontal bars (breakdowns) */
.bar-row { display:grid; grid-template-columns:130px 1fr 36px; align-items:center; gap:10px; margin-bottom:11px; }
.bar-label { font-size:12px; color:#4a544b; text-transform:capitalize; }
.bar-track { background:#eef1ec; border-radius:20px; height:14px; overflow:hidden; }
.bar-fill {
    height:100%; background:#1f5b3a; border-radius:20px;
    width: var(--w, 0);
    animation: bar-grow var(--chart-grow) var(--chart-ease) both;
    animation-delay: calc(var(--i, 0) * var(--chart-stagger));
    transition: filter .2s ease;
}
.bar-row:hover .bar-fill { filter:brightness(1.08); }
.bar-value {
    font-size:12px; font-weight:700; color:#33463a; text-align:right;
    animation: label-in var(--chart-settle) var(--chart-ease) both;
    animation-delay: calc(var(--i, 0) * var(--chart-stagger) + 0.18s);
}

/* Semantic status fills reuse the badge palette */
.bar-fill.bar-pending, .bar-fill.bar-requested   { background:#f2a33c; }
.bar-fill.bar-approved, .bar-fill.bar-available,
.bar-fill.bar-donor                              { background:#2f744a; }
.bar-fill.bar-completed, .bar-fill.bar-recipient { background:#5a70a8; }
.bar-fill.bar-rejected, .bar-fill.bar-cancelled  { background:#c65a5a; }
.bar-fill.bar-admin                              { background:#8a63c0; }

.export-row { display:flex; gap:10px; flex-wrap:wrap; margin-top:6px; }

/* Keyframes */
@keyframes col-grow  { from { height:0; }            to { height: var(--h, 0); } }
@keyframes bar-grow  { from { width:0; }             to { width: var(--w, 0); } }
@keyframes label-in  { from { opacity:0; transform: translateY(5px); } to { opacity:1; transform: translateY(0); } }
@keyframes card-in   { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }

/* Respect users who prefer reduced motion — show final state, no animation. */
@media (prefers-reduced-motion: reduce) {
    .chart-card, .col-bar, .bar-fill, .col-val, .bar-value {
        animation: none !important;
    }
    .chart-card { transform:none; }
}
</style>


<div class="top-section">
    <div>
        <div class="eyebrow">Administration</div>
        <h2 class="serif">Reports &amp; Analytics</h2>
    </div>
</div>


<!-- EXPORT -->
<div class="card">
    <div style="font-weight:700;margin-bottom:10px">Export data (CSV)</div>
    <div class="export-row">
        <a class="btn btn-ghost btn-sm" href="admin-reports.php?export=users">Users</a>
        <a class="btn btn-ghost btn-sm" href="admin-reports.php?export=donations">Donations</a>
        <a class="btn btn-ghost btn-sm" href="admin-reports.php?export=requests">Requests</a>
    </div>
</div>


<!-- TRENDS -->
<div class="report-grid">

    <div class="chart-card">
        <h3>Donations posted</h3>
        <div class="sub">Last 6 months</div>
        <div class="columns">
            <?php foreach ($don_months as $i => $m): ?>
                <?php $h = $don_max > 0 ? round(($m["count"] / $don_max) * 130) : 0; ?>
                <div class="col" style="--i:<?= (int) $i ?>">
                    <div class="col-val"><?= (int) $m["count"] ?></div>
                    <div class="col-bar" style="--h:<?= max($h, $m["count"] > 0 ? 4 : 0) ?>px"></div>
                    <div class="col-label"><?= e($m["label"]) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="chart-card">
        <h3>Requests made</h3>
        <div class="sub">Last 6 months</div>
        <div class="columns">
            <?php foreach ($req_months as $i => $m): ?>
                <?php $h = $req_max > 0 ? round(($m["count"] / $req_max) * 130) : 0; ?>
                <div class="col" style="--i:<?= (int) $i ?>">
                    <div class="col-val"><?= (int) $m["count"] ?></div>
                    <div class="col-bar col-bar-alt" style="--h:<?= max($h, $m["count"] > 0 ? 4 : 0) ?>px"></div>
                    <div class="col-label"><?= e($m["label"]) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>


<!-- BREAKDOWNS -->
<div class="report-grid">

    <div class="chart-card">
        <h3>Donations by category</h3>
        <div class="sub"><?= array_sum(array_column($by_category, "count")) ?> total</div>
        <?php if (!$by_category): ?>
            <div class="empty" style="padding:20px 0">No data yet.</div>
        <?php else: foreach ($by_category as $i => $r) bar_row($r["label"], $r["count"], $cat_max, null, $i); endif; ?>
    </div>

    <div class="chart-card">
        <h3>Users by role</h3>
        <div class="sub"><?= array_sum(array_column($users_by_role, "count")) ?> total</div>
        <?php
        $role_max = 0; foreach ($users_by_role as $r) { $role_max = max($role_max, $r["count"]); }
        foreach ($users_by_role as $i => $r) bar_row($r["label"], $r["count"], $role_max, strtolower($r["label"]), $i);
        ?>
    </div>

    <div class="chart-card">
        <h3>Donations by status</h3>
        <div class="sub"><?= array_sum(array_column($don_by_status, "count")) ?> total</div>
        <?php
        $dms = 0; foreach ($don_by_status as $r) { $dms = max($dms, $r["count"]); }
        if (!$don_by_status): ?>
            <div class="empty" style="padding:20px 0">No data yet.</div>
        <?php else: foreach ($don_by_status as $i => $r) bar_row($r["label"], $r["count"], $dms, strtolower($r["label"]), $i); endif; ?>
    </div>

    <div class="chart-card">
        <h3>Requests by status</h3>
        <div class="sub"><?= array_sum(array_column($req_by_status, "count")) ?> total</div>
        <?php
        $rms = 0; foreach ($req_by_status as $r) { $rms = max($rms, $r["count"]); }
        if (!$req_by_status): ?>
            <div class="empty" style="padding:20px 0">No data yet.</div>
        <?php else: foreach ($req_by_status as $i => $r) bar_row($r["label"], $r["count"], $rms, strtolower($r["label"]), $i); endif; ?>
    </div>

</div>


<?php require_once __DIR__ . "/includes/admin-footer.php"; ?>
