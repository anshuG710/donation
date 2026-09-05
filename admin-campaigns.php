<?php

/*
|--------------------------------------------------------------------------
| ADMIN — CAMPAIGNS
|--------------------------------------------------------------------------
|
| Admin-only management of donation drives. Create a campaign (with drop-off
| points), Start / End it, or delete it. Donors and the public only view
| campaigns; the lifecycle lives here.
|
*/

require_once __DIR__ . "/config/admin-guard.php";

$active_nav = "campaigns";
$page_title = "Campaigns";

$has_campaigns = table_exists($conn, "campaigns");


/*
|--------------------------------------------------------------------------
| ACTIONS (POST)
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && $has_campaigns) {

    csrf_require("admin-campaigns.php");

    $action = $_POST["action"] ?? "";


    /* ---- CREATE ---- */

    if ($action === "create") {

        $title           = trim($_POST["title"] ?? "");
        $description     = trim($_POST["description"] ?? "");
        $goal            = max(0, (int) ($_POST["goal_quantity"] ?? 0));
        $category_id     = (int) ($_POST["category_id"] ?? 0);
        $cat             = $category_id > 0 ? $category_id : null;
        $partner_org     = trim($_POST["partner_org"] ?? "");
        $partner_contact = trim($_POST["partner_contact"] ?? "");
        $needed_items    = trim($_POST["needed_items"] ?? "");
        $start_date      = trim($_POST["start_date"] ?? "");
        $end_date        = trim($_POST["end_date"] ?? "");
        $start           = $start_date !== "" ? $start_date : null;
        $end             = $end_date !== "" ? $end_date : null;

        if ($title === "") {
            set_flash("error", "Campaign title is required.");
            header("Location: admin-campaigns.php");
            exit();
        }

        // Optional image upload.
        $image_name = null;
        if (isset($_FILES["image"]) && $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE) {

            if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
                set_flash("error", "There was a problem uploading the image.");
                header("Location: admin-campaigns.php");
                exit();
            }

            if ($_FILES["image"]["size"] > 5 * 1024 * 1024) {
                set_flash("error", "The image must be 5 MB or smaller.");
                header("Location: admin-campaigns.php");
                exit();
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $_FILES["image"]["tmp_name"]);
            finfo_close($finfo);

            $allowed = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp", "image/jpg" => "jpg"];
            if (!isset($allowed[$mime])) {
                set_flash("error", "Only JPG, PNG and WEBP images are allowed.");
                header("Location: admin-campaigns.php");
                exit();
            }

            $dir = __DIR__ . "/uploads/campaigns";
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $image_name = "campaign_" . time() . "_" . bin2hex(random_bytes(5)) . "." . $allowed[$mime];
            if (!move_uploaded_file($_FILES["image"]["tmp_name"], $dir . "/" . $image_name)) {
                $image_name = null;
            }
        }

        $stmt = $conn->prepare(
            "INSERT INTO campaigns
                (title, description, image, goal_quantity, category_id,
                 partner_org, partner_contact, needed_items, status,
                 start_date, end_date, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)"
        );
        $stmt->bind_param(
            "sssiisssssi",
            $title, $description, $image_name, $goal, $cat,
            $partner_org, $partner_contact, $needed_items, $start, $end, $admin_id
        );
        $stmt->execute();
        $cid = $stmt->insert_id;
        $stmt->close();

        // Collection points (parallel arrays; skip blank rows).
        $labels = $_POST["point_label"]   ?? [];
        $addrs  = $_POST["point_address"] ?? [];
        $cities = $_POST["point_city"]    ?? [];
        $phones = $_POST["point_phone"]   ?? [];
        $hoursA = $_POST["point_hours"]   ?? [];
        $maps   = $_POST["point_map"]     ?? [];

        $ps = $conn->prepare(
            "INSERT INTO campaign_points
                (campaign_id, label, address, city, contact_phone, hours, map_url)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $count = is_array($labels) ? count($labels) : 0;
        for ($i = 0; $i < $count; $i++) {
            $l = trim($labels[$i] ?? "");
            $a = trim($addrs[$i] ?? "");
            if ($l === "" && $a === "") {
                continue;
            }
            $c2 = trim($cities[$i] ?? "");
            $ph = trim($phones[$i] ?? "");
            $h  = trim($hoursA[$i] ?? "");
            $m  = trim($maps[$i] ?? "");
            $ps->bind_param("issssss", $cid, $l, $a, $c2, $ph, $h, $m);
            $ps->execute();
        }
        $ps->close();

        log_activity($conn, $admin_id, "campaign.create", "Created \"" . $title . "\"");
        set_flash("success", "Campaign created and started.");
        header("Location: admin-campaigns.php");
        exit();
    }


    /* ---- START / END / DELETE ---- */

    if (in_array($action, ["start", "end", "delete"], true)) {

        $cid = (int) ($_POST["campaign_id"] ?? 0);

        if ($cid > 0 && $action === "delete") {

            $d = $conn->prepare("DELETE FROM campaigns WHERE id = ?");
            $d->bind_param("i", $cid);
            $d->execute();
            $d->close();
            log_activity($conn, $admin_id, "campaign.delete", "Deleted campaign #" . $cid);
            set_flash("success", "Campaign deleted.");

        } elseif ($cid > 0) {

            $new = $action === "start" ? "active" : "ended";
            $u = $conn->prepare("UPDATE campaigns SET status = ? WHERE id = ?");
            $u->bind_param("si", $new, $cid);
            $u->execute();
            $u->close();
            log_activity($conn, $admin_id, "campaign." . $action, "Campaign #" . $cid);
            set_flash("success", $action === "start" ? "Campaign started." : "Campaign ended.");
        }

        header("Location: admin-campaigns.php");
        exit();
    }


    /* ---- APPROVE / REJECT A CAMPAIGN CONTRIBUTION ---- */

    if (in_array($action, ["approve_item", "reject_item"], true)
        && column_exists($conn, "donations", "campaign_status")) {

        $donation_id = (int) ($_POST["donation_id"] ?? 0);
        $new_status  = $action === "approve_item" ? "approved" : "rejected";

        if ($donation_id > 0) {
            $u = $conn->prepare(
                "UPDATE donations SET campaign_status = ?
                 WHERE id = ? AND campaign_id IS NOT NULL"
            );
            $u->bind_param("si", $new_status, $donation_id);
            $u->execute();
            $u->close();
            log_activity($conn, $admin_id, "campaign." . $action, "Donation #" . $donation_id);
            set_flash("success", $action === "approve_item"
                ? "Contribution approved — it now counts toward the campaign."
                : "Contribution rejected.");
        }

        header("Location: admin-campaigns.php");
        exit();
    }
}


/*
|--------------------------------------------------------------------------
| DATA
|--------------------------------------------------------------------------
*/

$categories = [];
$rc = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($rc) {
    while ($r = $rc->fetch_assoc()) {
        $categories[] = $r;
    }
}

$campaigns = [];
if ($has_campaigns) {
    $q = $conn->query("
        SELECT c.*,
            (SELECT COALESCE(SUM(d.quantity), 0)
               FROM donations d
              WHERE d.campaign_id = c.id AND d.status <> 'cancelled') AS collected,
            (SELECT COUNT(*) FROM campaign_points p WHERE p.campaign_id = c.id) AS points
        FROM campaigns c
        ORDER BY (c.status = 'active') DESC, c.created_at DESC
    ");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $campaigns[] = $r;
        }
    }
}

require_once __DIR__ . "/includes/admin-header.php";
?>

<div class="top-section">
    <div>
        <div class="eyebrow">Administration</div>
        <h2 class="serif">Campaigns</h2>
    </div>
</div>

<?php if (!$has_campaigns): ?>
    <div class="flash flash-error">
        The campaigns tables don't exist yet. Run the campaigns migration from
        <strong>schema.sql</strong> in phpMyAdmin, then reload this page.
    </div>
<?php else: ?>

<!-- CREATE -->
<div class="card" style="max-width:820px">
    <h3 style="margin-top:0">Start a new campaign</h3>

    <form method="post" enctype="multipart/form-data">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="create">

        <div class="field">
            <label>Title *</label>
            <input type="text" name="title" placeholder="e.g. Karnali Flood Relief" required>
        </div>

        <div class="field">
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="What the campaign is for."></textarea>
        </div>

        <div style="display:flex;gap:14px;flex-wrap:wrap">
            <div class="field" style="flex:1;min-width:180px">
                <label>Goal (number of items)</label>
                <input type="number" name="goal_quantity" min="0" value="0">
            </div>
            <div class="field" style="flex:1;min-width:180px">
                <label>Focus category (optional)</label>
                <select name="category_id">
                    <option value="0">— Any —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c["id"] ?>"><?= e($c["name"]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="field">
            <label>Most-needed items</label>
            <input type="text" name="needed_items" placeholder="e.g. blankets, dry food, warm clothes">
        </div>

        <div style="display:flex;gap:14px;flex-wrap:wrap">
            <div class="field" style="flex:1;min-width:180px">
                <label>Partner organization</label>
                <input type="text" name="partner_org" placeholder="e.g. Nepal Relief Foundation">
            </div>
            <div class="field" style="flex:1;min-width:180px">
                <label>Partner contact</label>
                <input type="text" name="partner_contact" placeholder="phone or email">
            </div>
        </div>

        <div style="display:flex;gap:14px;flex-wrap:wrap">
            <div class="field" style="flex:1;min-width:150px">
                <label>Start date</label>
                <input type="date" name="start_date">
            </div>
            <div class="field" style="flex:1;min-width:150px">
                <label>End date</label>
                <input type="date" name="end_date">
            </div>
        </div>

        <div class="field">
            <label>Cover image (optional)</label>
            <input type="file" name="image" accept="image/*">
        </div>

        <h4 style="margin:22px 0 6px">Drop-off / collection points</h4>
        <p style="color:#777;font-size:12px;margin:0 0 10px">Where donors bring items. Add as many as you need; blank rows are ignored.</p>

        <div id="points">
            <div class="point-row">
                <input type="text" name="point_label[]"   placeholder="Label (e.g. Kathmandu Center)">
                <input type="text" name="point_address[]" placeholder="Address">
                <input type="text" name="point_city[]"    placeholder="City">
                <input type="text" name="point_phone[]"   placeholder="Contact phone">
                <input type="text" name="point_hours[]"   placeholder="Hours (e.g. Sun-Fri 9-5)">
                <input type="text" name="point_map[]"     placeholder="Map link (optional)">
            </div>
        </div>

        <button type="button" class="btn btn-ghost btn-sm" onclick="addPoint()">+ Add another point</button>

        <div style="margin-top:18px">
            <button type="submit" class="btn btn-green">Create &amp; start campaign</button>
        </div>
    </form>
</div>


<!-- LIST -->
<h3 style="margin-top:34px">All campaigns</h3>

<?php if (empty($campaigns)): ?>
    <div class="card"><p style="margin:0;color:#777">No campaigns yet — start one above.</p></div>
<?php else: ?>

    <?php foreach ($campaigns as $c):
        $goal      = (int) $c["goal_quantity"];
        $collected = campaign_collected_qty($conn, (int) $c["id"]);
        $pending   = campaign_pending_items($conn, (int) $c["id"]);
        $pct       = $goal > 0 ? min(100, (int) round($collected * 100 / $goal)) : 0;
    ?>
        <div class="card" style="margin-bottom:16px">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
                <div style="flex:1;min-width:220px">
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <strong style="font-size:17px"><?= e($c["title"]) ?></strong>
                        <?= render_status_badge($c["status"]) ?>
                    </div>
                    <?php if (!empty($c["partner_org"])): ?>
                        <div style="color:#777;font-size:13px;margin-top:4px">with <?= e($c["partner_org"]) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($c["needed_items"])): ?>
                        <div style="color:#555;font-size:13px;margin-top:6px">Needs: <?= e($c["needed_items"]) ?></div>
                    <?php endif; ?>

                    <div style="margin-top:12px;max-width:420px">
                        <div class="cbar"><span style="width:<?= $pct ?>%"></span></div>
                        <div style="font-size:12px;color:#666;margin-top:5px">
                            <?= $collected ?><?= $goal > 0 ? " / " . $goal : "" ?> items collected
                            <?= $goal > 0 ? " (" . $pct . "%)" : "" ?>
                            &nbsp;·&nbsp; <?= (int) $c["points"] ?> drop-off point<?= (int) $c["points"] === 1 ? "" : "s" ?>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start">
                    <?php if ($c["status"] === "active"): ?>
                        <form method="post" style="margin:0">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="end">
                            <input type="hidden" name="campaign_id" value="<?= (int) $c["id"] ?>">
                            <button class="btn btn-ghost btn-sm" onclick="return confirm('End this campaign?');">End</button>
                        </form>
                    <?php else: ?>
                        <form method="post" style="margin:0">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="start">
                            <input type="hidden" name="campaign_id" value="<?= (int) $c["id"] ?>">
                            <button class="btn btn-green btn-sm">Start</button>
                        </form>
                    <?php endif; ?>

                    <form method="post" style="margin:0">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="campaign_id" value="<?= (int) $c["id"] ?>">
                        <button class="btn btn-sm" style="background:#fff;border:1px solid #e0b4b4;color:#a33a3a"
                                onclick="return confirm('Delete this campaign permanently?');">Delete</button>
                    </form>
                </div>
            </div>

            <?php if (!empty($pending)): ?>
                <div style="margin-top:16px;border-top:1px solid #eee;padding-top:14px">
                    <div style="font-weight:700;font-size:13px;margin-bottom:10px">
                        Pending contributions to approve (<?= count($pending) ?>)
                    </div>
                    <?php foreach ($pending as $pi):
                        $pwhen = !empty($pi["created_at"]) ? date("d M Y", strtotime($pi["created_at"])) : "";
                    ?>
                        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;background:#faf9f5;border:1px solid #eee;border-radius:10px;padding:10px 14px;margin-bottom:8px">
                            <div style="font-size:13px">
                                <strong><?= e($pi["title"]) ?></strong>
                                <span style="color:#777">— <?= (int) $pi["quantity"] ?> <?= e($pi["unit"] ?: "pcs") ?><?= !empty($pi["category_name"]) ? " · " . e($pi["category_name"]) : "" ?></span>
                                <span style="color:#999">· <?= e($pi["donor_name"] ?: "Donor") ?><?= $pwhen ? " · " . $pwhen : "" ?></span>
                            </div>
                            <div style="display:flex;gap:6px">
                                <form method="post" style="margin:0">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="approve_item">
                                    <input type="hidden" name="donation_id" value="<?= (int) $pi["id"] ?>">
                                    <button class="btn btn-green btn-sm">Approve</button>
                                </form>
                                <form method="post" style="margin:0">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="reject_item">
                                    <input type="hidden" name="donation_id" value="<?= (int) $pi["id"] ?>">
                                    <button class="btn btn-sm" style="background:#fff;border:1px solid #e0b4b4;color:#a33a3a">Reject</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    <?php endforeach; ?>

<?php endif; ?>

<style>
.cbar{height:9px;background:#eceae3;border-radius:6px;overflow:hidden}
.cbar span{display:block;height:100%;background:#1f5b3a;border-radius:6px}
.point-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px}
.point-row input{flex:1;min-width:130px;padding:9px 10px;border:1px solid #ddd9d1;border-radius:8px;font-size:13px}
</style>

<script>
function addPoint(){
    var first = document.querySelector("#points .point-row");
    var clone = first.cloneNode(true);
    clone.querySelectorAll("input").forEach(function(i){ i.value = ""; });
    document.getElementById("points").appendChild(clone);
}
</script>

<?php endif; ?>

<?php require_once __DIR__ . "/includes/admin-footer.php"; ?>
