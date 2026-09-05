<?php

/*
|--------------------------------------------------------------------------
| DONATE+ Nepal — Campaigns (public)
|--------------------------------------------------------------------------
|
| Lists active donation drives with their progress, most-needed items and
| the drop-off points where donors can bring items.
|
*/

session_start();

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/helpers.php";

$logged_in = isset($_SESSION["user_id"]);
$has_campaigns = table_exists($conn, "campaigns");

$campaigns = [];
if ($has_campaigns) {
    $q = $conn->query("
        SELECT c.*,
            (SELECT COALESCE(SUM(d.quantity), 0)
               FROM donations d
              WHERE d.campaign_id = c.id AND d.status <> 'cancelled') AS collected
        FROM campaigns c
        WHERE c.status = 'active'
        ORDER BY c.created_at DESC
    ");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $r["points"] = [];
            $campaigns[] = $r;
        }
    }
    // Attach drop-off points.
    foreach ($campaigns as &$c) {
        $pstmt = $conn->prepare("SELECT label, address, city, contact_phone, hours, map_url FROM campaign_points WHERE campaign_id = ?");
        $pstmt->bind_param("i", $c["id"]);
        $pstmt->execute();
        $pr = $pstmt->get_result();
        while ($p = $pr->fetch_assoc()) {
            $c["points"][] = $p;
        }
        $pstmt->close();
    }
    unset($c);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Campaigns | DONATE+</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');
:root{--green:#1f5b3a;--green2:#2f744a;--orange:#f28c18;--cream:#fbfaf6;--ink:#171915;--muted:#697069;--border:#e8e6df}
*{box-sizing:border-box}
body{margin:0;font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--ink)}
a{text-decoration:none}
.serif{font-family:'Playfair Display',serif}
.container{max-width:1120px;margin:auto;padding:0 28px}
header{height:74px;background:#fff;border-bottom:1px solid var(--border)}
.nav{height:100%;display:flex;align-items:center;justify-content:space-between}
.brand{display:flex;align-items:center;gap:10px;color:var(--green);font-weight:800}
.brandmark{width:38px;height:38px;border:2px solid var(--green);border-radius:50%;display:grid;place-items:center;font-size:19px}
.brand small{display:block;font-size:9px;color:#7d857d;letter-spacing:.6px;font-weight:600}
.btn{display:inline-flex;align-items:center;gap:8px;padding:11px 18px;border-radius:10px;font-weight:700;font-size:14px;border:0;cursor:pointer}
.btn-green{background:var(--green);color:#fff}
.btn-ghost{background:#fff;border:1px solid var(--border);color:var(--ink)}
main{padding:60px 0}
.eyebrow{color:var(--green);font-weight:800;letter-spacing:2px;font-size:12px;text-transform:uppercase}
h1{font-family:'Playfair Display',serif;font-size:46px;margin:10px 0}
.sub{color:var(--muted);margin-bottom:35px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:26px}
.card{background:#fff;border:1px solid var(--border);border-radius:18px;overflow:hidden;display:flex;flex-direction:column;transition:transform .18s ease, box-shadow .18s ease}
.card:hover{transform:translateY(-4px);box-shadow:0 18px 44px rgba(31,56,32,.12)}
.card-img{cursor:pointer}
.card-img{height:180px;background:#e8efe6 center/cover no-repeat;display:flex;align-items:center;justify-content:center;color:#9bb29e;font-size:34px}
.card-body{padding:22px 24px}
.partner{color:var(--muted);font-size:13px;margin-top:4px}
.needs{background:#fff6e9;border:1px solid #f3e2c4;color:#8a5a12;border-radius:10px;padding:9px 12px;font-size:13px;margin:14px 0}
.cbar{height:10px;background:#eceae3;border-radius:6px;overflow:hidden;margin-top:6px}
.cbar span{display:block;height:100%;background:var(--green);border-radius:6px}
.progress-label{font-size:12px;color:#666;margin-top:6px}
.points{margin-top:16px;border-top:1px solid var(--border);padding-top:14px}
.point{font-size:13px;color:#444;margin-bottom:10px}
.point b{color:var(--ink)}
.point .meta{color:var(--muted)}
.empty{background:#fff;border:1px solid var(--border);border-radius:16px;padding:55px 25px;text-align:center;color:var(--muted)}
@media(max-width:820px){.grid{grid-template-columns:1fr}h1{font-size:36px}}
</style>
</head>
<body>

<header>
    <div class="container nav">
        <a class="brand" href="index.html">
            <span class="brandmark">♡</span>
            <span>DONATE+<small>Give Today, Change Tomorrow</small></span>
        </a>
        <a href="<?= $logged_in ? "add-donation.php" : "login.html" ?>" class="btn btn-green">♡ Donate an Item</a>
    </div>
</header>

<main class="container">

    <div class="eyebrow">Active Drives</div>
    <h1>Campaigns</h1>
    <p class="sub">Join a drive — bring the items that are needed to a drop-off point near you.</p>

    <?php if (empty($campaigns)): ?>

        <div class="empty">
            <p style="font-size:34px;margin:0 0 8px">🕊️</p>
            <h2 class="serif" style="margin:0 0 8px">No active campaigns right now</h2>
            <p>Check back soon — new drives are added when there's a need.</p>
        </div>

    <?php else: ?>

        <div class="grid">
            <?php foreach ($campaigns as $c):
                $goal = (int) $c["goal_quantity"];
                $collected = (int) $c["collected"];
                $pct = $goal > 0 ? min(100, (int) round($collected * 100 / $goal)) : 0;
                $img = !empty($c["image"]) ? "uploads/campaigns/" . htmlspecialchars($c["image"]) : "";
            ?>
                <div class="card">
                    <a href="campaign.php?id=<?= (int) $c["id"] ?>" style="text-decoration:none;color:inherit">
                        <div class="card-img" <?= $img ? 'style="background-image:url(\'' . $img . '\')"' : "" ?>>
                            <?= $img ? "" : "♡" ?>
                        </div>
                    </a>
                    <div class="card-body">
                        <h3 class="serif" style="margin:0;font-size:24px">
                            <a href="campaign.php?id=<?= (int) $c["id"] ?>" style="text-decoration:none;color:inherit"><?= htmlspecialchars($c["title"]) ?></a>
                        </h3>
                        <?php if (!empty($c["partner_org"])): ?>
                            <div class="partner">in partnership with <?= htmlspecialchars($c["partner_org"]) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($c["description"])): ?>
                            <p style="color:var(--muted);font-size:14px;line-height:1.7"><?= nl2br(htmlspecialchars($c["description"])) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($c["needed_items"])): ?>
                            <div class="needs"><strong>Most needed:</strong> <?= htmlspecialchars($c["needed_items"]) ?></div>
                        <?php endif; ?>

                        <?php if ($goal > 0): ?>
                            <div class="cbar"><span style="width:<?= $pct ?>%"></span></div>
                            <div class="progress-label"><strong><?= $collected ?></strong> of <?= $goal ?> items collected (<?= $pct ?>%)</div>
                        <?php else: ?>
                            <div class="progress-label"><strong><?= $collected ?></strong> items collected so far</div>
                        <?php endif; ?>

                        <?php if (!empty($c["points"])): ?>
                            <div class="points">
                                <div style="font-weight:700;font-size:13px;margin-bottom:10px">📍 Where to drop off</div>
                                <?php foreach ($c["points"] as $p): ?>
                                    <div class="point">
                                        <b><?= htmlspecialchars($p["label"] ?: "Collection point") ?></b>
                                        <?php if (!empty($p["address"]) || !empty($p["city"])): ?>
                                            <div class="meta"><?= htmlspecialchars(trim(($p["address"] ?? "") . ($p["city"] ? ", " . $p["city"] : ""), ", ")) ?></div>
                                        <?php endif; ?>
                                        <div class="meta">
                                            <?php if (!empty($p["contact_phone"])): ?>☎ <?= htmlspecialchars($p["contact_phone"]) ?>&nbsp;<?php endif; ?>
                                            <?php if (!empty($p["hours"])): ?>· <?= htmlspecialchars($p["hours"]) ?><?php endif; ?>
                                            <?php if (!empty($p["map_url"])): ?> · <a href="<?= htmlspecialchars($p["map_url"]) ?>" target="_blank" rel="noopener" style="color:var(--green);font-weight:600">Map</a><?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($c["partner_contact"])): ?>
                            <div style="font-size:12px;color:var(--muted);margin-top:10px">Questions? Contact <?= htmlspecialchars($c["partner_contact"]) ?></div>
                        <?php endif; ?>

                        <?php
                            $c_target = "add-donation.php?campaign=" . (int) $c["id"];
                            $c_donate = $logged_in ? $c_target : "login.html?next=" . urlencode($c_target);
                        ?>
                        <a href="<?= htmlspecialchars($c_donate) ?>" class="btn btn-green" style="margin-top:16px">Donate to this drive →</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</main>

</body>
</html>
