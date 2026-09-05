<?php

/*
|--------------------------------------------------------------------------
| ADMIN — EDIT DONATION
|--------------------------------------------------------------------------
|
| Edit a single donation's core fields and status. Reached from
| admin-donations.php. POST saves and returns to the list.
|
*/

require_once __DIR__ . "/config/admin-guard.php";
require_once __DIR__ . "/config/conditions.php";

$statuses = ["available", "requested", "completed", "cancelled"];
$units    = ["pieces", "kg", "grams", "liters", "packets", "boxes", "sets"];

// Whether the perishable-expiry column has been migrated in yet.
$has_expiry_col = column_exists($conn, "donations", "expiry_date");


/* Categories for the dropdown. */

$categories = [];
$res = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $categories[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| SAVE (POST)
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    csrf_require("admin-donations.php");

    $did = (int) ($_POST["donation_id"] ?? 0);

    // Ensure it exists.
    $stmt = $conn->prepare("SELECT id FROM donations WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $did);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$exists) {
        set_flash("error", "That donation no longer exists.");
        header("Location: admin-donations.php");
        exit();
    }

    /*
    | MODERATION ONLY.
    | An admin may change a listing's STATUS (take down / restore / cancel /
    | mark completed) but never the donor's own content — title, category,
    | quantity, unit, condition, expiry, location or description. Any of
    | those fields posted are deliberately ignored here, so an admin can
    | never, for example, turn a food donation into furniture.
    */

    $status = $_POST["status"] ?? "available";

    if (!in_array($status, $statuses, true)) {
        $status = "available";
    }

    $stmt = $conn->prepare("UPDATE donations SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $did);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        log_activity($conn, $admin_id, "donation.status",
            "Set donation #" . $did . " status to " . $status);
    }

    set_flash(
        $ok ? "success" : "error",
        $ok
            ? "Donation status updated to " . htmlspecialchars($status) . "."
            : "Could not update the status."
    );

    header("Location: admin-donations.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| LOAD (GET)
|--------------------------------------------------------------------------
*/

$did = (int) ($_GET["id"] ?? 0);

$expiry_select = $has_expiry_col ? ", expiry_date" : "";

$stmt = $conn->prepare("
    SELECT id, title, description, category_id, quantity, unit,
           item_condition, location, status" . $expiry_select . "
    FROM donations
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $did);
$stmt->execute();
$donation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$donation) {
    set_flash("error", "That donation could not be found.");
    header("Location: admin-donations.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| RENDER
|--------------------------------------------------------------------------
*/

$page_title = "Moderate Donation";
$active_nav = "donations";

// Read-only display values — an admin cannot change the donor's content.
$view_cat_name = category_name_by_id($conn, (int) $donation["category_id"]);
if ($view_cat_name === "") {
    $view_cat_name = "Uncategorized";
}
$view_expiry = ($has_expiry_col && !empty($donation["expiry_date"]))
    ? date("d M Y", strtotime($donation["expiry_date"]))
    : "—";

require_once __DIR__ . "/includes/admin-header.php";
?>


<div class="top-section">
    <div>
        <div class="eyebrow">Administration</div>
        <h2 class="serif">Moderate Donation</h2>
    </div>
    <a class="btn btn-ghost btn-sm" href="admin-donations.php">&larr; Back to donations</a>
</div>


<div class="card" style="max-width:720px">
    <p style="color:#6b726b;font-size:13px;margin:0 0 18px">
        The donor owns this listing's details, so they are shown read-only
        here. As an admin you can change its <strong>status</strong> (take it
        down, restore, cancel, or mark completed) or delete it from the
        donations list. To fix wrong details, take the listing down and ask
        the donor to correct it.
    </p>

    <div class="field">
        <label>Title</label>
        <div class="readonly-value"><?= e($donation["title"]) ?></div>
    </div>

    <div class="field">
        <label>Description</label>
        <div class="readonly-value"><?= !empty($donation["description"]) ? nl2br(e($donation["description"])) : "—" ?></div>
    </div>

    <div class="field">
        <label>Category</label>
        <div class="readonly-value"><?= e($view_cat_name) ?></div>
    </div>

    <div class="field">
        <label>Quantity</label>
        <div class="readonly-value"><?= (int) $donation["quantity"] ?> <?= e($donation["unit"] ?? "") ?></div>
    </div>

    <div class="field">
        <label>Condition</label>
        <div class="readonly-value"><?= !empty($donation["item_condition"]) ? e($donation["item_condition"]) : "—" ?></div>
    </div>

    <div class="field">
        <label>Expiry / Best-before</label>
        <div class="readonly-value"><?= e($view_expiry) ?></div>
    </div>

    <div class="field">
        <label>Location</label>
        <div class="readonly-value"><?= !empty($donation["location"]) ? e($donation["location"]) : "—" ?></div>
    </div>

    <hr style="border:none;border-top:1px solid #ececec;margin:20px 0">

    <form method="post">
        <?php csrf_field(); ?>
        <input type="hidden" name="donation_id" value="<?= (int) $donation["id"] ?>">

        <div class="field">
            <label>Status <span style="color:#8a8f8a;font-weight:400">(admin can change this)</span></label>
            <select name="status">
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= $donation["status"] === $s ? "selected" : "" ?>>
                        <?= e(ucfirst($s)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-green">Update status</button>
        <a class="btn btn-ghost" href="admin-donations.php">Cancel</a>
    </form>
</div>


<style>
.readonly-value {
    padding: 11px 12px;
    background: #f6f6f4;
    border: 1px solid #e6e6e0;
    border-radius: 8px;
    color: #333;
    font-size: 14px;
}
</style>


<?php require_once __DIR__ . "/includes/admin-footer.php"; ?>
