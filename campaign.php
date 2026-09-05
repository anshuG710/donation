<?php

/*
|--------------------------------------------------------------------------
| DONATE+ Nepal — Single Campaign (public detail)
|--------------------------------------------------------------------------
|
| Anyone can view a campaign's details and drop-off points. Donating
| requires logging in.
|
*/

session_start();

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/helpers.php";

$logged_in = isset($_SESSION["user_id"]);
$id = (int) ($_GET["id"] ?? 0);

$campaign = null;

if ($id > 0 && table_exists($conn, "campaigns")) {
    $stmt = $conn->prepare("
        SELECT c.*,
            (SELECT COALESCE(SUM(d.quantity), 0)
               FROM donations d
              WHERE d.campaign_id = c.id AND d.status <> 'cancelled') AS collected,
            cat.name AS category_name
        FROM campaigns c
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $campaign = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$campaign) {
    header("Location: campaigns.php");
    exit();
}

$points = [];
$pstmt = $conn->prepare("SELECT label, address, city, contact_phone, hours, map_url FROM campaign_points WHERE campaign_id = ?");
$pstmt->bind_param("i", $id);
$pstmt->execute();
$pr = $pstmt->get_result();
while ($p = $pr->fetch_assoc()) {
    $points[] = $p;
}
$pstmt->close();

// Approved contributions (list + category breakdown) and the live total.
$items     = campaign_items($conn, $id);
$breakdown = campaign_category_breakdown($conn, $id);

$goal      = (int) $campaign["goal_quantity"];
$collected = campaign_collected_qty($conn, $id);
$pct       = $goal > 0 ? min(100, (int) round($collected * 100 / $goal)) : 0;
$active    = $campaign["status"] === "active";
$img       = !empty($campaign["image"]) ? "uploads/campaigns/" . htmlspecialchars($campaign["image"]) : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($campaign["title"]) ?> | DONATE+</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');
:root{--green:#1f5b3a;--orange:#f28c18;--cream:#fbfaf6;--ink:#171915;--muted:#697069;--border:#e8e6df}
*{box-sizing:border-box}
body{margin:0;font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--ink)}
a{text-decoration:none}
.serif{font-family:'Playfair Display',serif}
.container{max-width:960px;margin:auto;padding:0 28px}
header{height:74px;background:#fff;border-bottom:1px solid var(--border)}
.nav{height:100%;display:flex;align-items:center;justify-content:space-between}
.brand{display:flex;align-items:center;gap:10px;color:var(--green);font-weight:800}
.brandmark{width:38px;height:38px;border:2px solid var(--green);border-radius:50%;display:grid;place-items:center;font-size:19px}
.brand small{display:block;font-size:9px;color:#7d857d;letter-spacing:.6px;font-weight:600}
.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 20px;border-radius:10px;font-weight:700;font-size:14px;border:0;cursor:pointer}
.btn-green{background:var(--green);color:#fff}
.btn-disabled{background:#999;color:#fff;cursor:not-allowed}
main{padding:40px 0 70px}
.back{color:var(--green);font-weight:700;font-size:13px}
.hero-img{height:300px;border-radius:18px;margin-top:20px;background:#e3ece0 center/cover no-repeat;display:flex;align-items:center;justify-content:center;color:#9bb29e;font-size:46px}
.badge{display:inline-block;padding:5px 12px;border-radius:20px;font-size:11px;font-weight:700;background:#e8f4e8;color:#27713e}
.badge.ended{background:#eee;color:#777}
.tag{display:inline-block;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#eef4ea;color:#2f744a;margin-left:8px}
h1{font-family:'Playfair Display',serif;font-size:40px;margin:16px 0 6px}
.partner{color:var(--muted);font-size:14px}
.desc{color:#4b524b;line-height:1.8;margin:18px 0}
.needs{background:#fff6e9;border:1px solid #f3e2c4;color:#8a5a12;border-radius:10px;padding:11px 14px;font-size:14px;margin:14px 0}
.cbar{height:12px;background:#eceae3;border-radius:6px;overflow:hidden;margin-top:8px;max-width:560px}
.cbar span{display:block;height:100%;background:var(--green);border-radius:6px}
.plabel{font-size:13px;color:#666;margin-top:8px}
.points{margin-top:26px}
.point{background:#fff;border:1px solid var(--border);border-radius:12px;padding:16px 18px;margin-bottom:12px}
.point b{font-size:15px}
.point .meta{color:var(--muted);font-size:13px;margin-top:4px}
@media(max-width:640px){h1{font-size:31px}.hero-img{height:210px}}
</style>
</head>
<body>

<header>
    <div class="container nav">
        <a class="brand" href="index.html">
            <span class="brandmark">♡</span>
            <span>DONATE+<small>Give Today, Change Tomorrow</small></span>
        </a>
        <a href="campaigns.php" class="back">← All campaigns</a>
    </div>
</header>

<main class="container">

    <span class="badge <?= $active ? "" : "ended" ?>"><?= $active ? "Active" : "Ended" ?></span>
    <?php if (!empty($campaign["category_name"])): ?>
        <span class="tag"><?= htmlspecialchars($campaign["category_name"]) ?></span>
    <?php endif; ?>

    <h1><?= htmlspecialchars($campaign["title"]) ?></h1>

    <?php if (!empty($campaign["partner_org"])): ?>
        <div class="partner">in partnership with <strong><?= htmlspecialchars($campaign["partner_org"]) ?></strong></div>
    <?php endif; ?>

    <div class="hero-img" <?= $img ? 'style="background-image:url(\'' . $img . '\')"' : "" ?>>
        <?= $img ? "" : "♡" ?>
    </div>

    <?php if ($goal > 0): ?>
        <div class="cbar"><span style="width:<?= $pct ?>%"></span></div>
        <div class="plabel"><strong><?= $collected ?></strong> of <?= $goal ?> items collected (<?= $pct ?>%)</div>
    <?php else: ?>
        <div class="plabel"><strong><?= $collected ?></strong> items collected so far</div>
    <?php endif; ?>

    <?php if (!empty($campaign["description"])): ?>
        <p class="desc"><?= nl2br(htmlspecialchars($campaign["description"])) ?></p>
    <?php endif; ?>

    <?php if (!empty($campaign["needed_items"])): ?>
        <div class="needs"><strong>Most needed:</strong> <?= htmlspecialchars($campaign["needed_items"]) ?></div>
    <?php endif; ?>

    <?php if (!empty($items)): ?>
        <div class="points">
            <h3 class="serif" style="margin-bottom:6px">🎁 Donations received (<?= count($items) ?>)</h3>
            <p style="color:var(--muted);font-size:13px;margin:0 0 12px">
                <strong><?= $collected ?></strong> items donated so far across
                <?= count($items) ?> contribution<?= count($items) === 1 ? "" : "s" ?>.
            </p>

            <?php if (!empty($breakdown)): ?>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 14px">
                    <?php foreach ($breakdown as $cat => $qty): ?>
                        <span style="background:#eef4ea;color:#2f744a;border-radius:20px;padding:5px 12px;font-size:12px;font-weight:700">
                            <?= htmlspecialchars($cat) ?>: <?= (int) $qty ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div style="border:1px solid var(--border);border-radius:12px;overflow:hidden">
                <?php foreach ($items as $it):
                    $when = !empty($it["created_at"]) ? date("d M Y", strtotime($it["created_at"])) : "";
                ?>
                    <div style="display:flex;justify-content:space-between;gap:14px;padding:12px 16px;border-top:1px solid #f0efe9;background:#fff;flex-wrap:wrap">
                        <div>
                            <strong><?= htmlspecialchars($it["title"]) ?></strong>
                            <span style="color:var(--muted);font-size:13px">
                                — <?= (int) $it["quantity"] ?> <?= htmlspecialchars($it["unit"] ?: "pcs") ?><?= !empty($it["category_name"]) ? " · " . htmlspecialchars($it["category_name"]) : "" ?><?= !empty($it["item_condition"]) ? " · " . htmlspecialchars($it["item_condition"]) : "" ?>
                            </span>
                        </div>
                        <div style="color:var(--muted);font-size:12px;text-align:right">
                            <?= htmlspecialchars($it["donor_name"] ?: "Donor") ?><?= $when ? " · " . $when : "" ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($points)): ?>
        <div class="points">
            <h3 class="serif" style="margin-bottom:12px">📍 Where to drop off items</h3>
            <?php foreach ($points as $p): ?>
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

    <?php if (!empty($campaign["partner_contact"])): ?>
        <p style="color:var(--muted);font-size:13px;margin-top:14px">Questions? Contact <?= htmlspecialchars($campaign["partner_contact"]) ?></p>
    <?php endif; ?>

    <div style="margin-top:28px">
        <?php
            $target = "add-donation.php?campaign=" . (int) $campaign["id"];
            $donate_url = $logged_in ? $target : "login.html?next=" . urlencode($target);
        ?>
        <?php if ($active): ?>
            <a class="btn btn-green" href="<?= htmlspecialchars($donate_url) ?>">
                <?= $logged_in ? "Donate to this drive →" : "Log in to donate →" ?>
            </a>
        <?php else: ?>
            <span class="btn btn-disabled">This campaign has ended</span>
        <?php endif; ?>
    </div>

</main>

</body>
</html>
