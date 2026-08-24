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

$statuses   = ["available", "requested", "completed", "cancelled"];
$units      = ["pieces", "kg", "grams", "liters", "packets", "boxes", "sets"];
$conditions = ["New", "Like New", "Good", "Used"];


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

    // Collect + validate.
    $title       = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $category_id = (int) ($_POST["category_id"] ?? 0);
    $quantity    = (int) ($_POST["quantity"] ?? 1);
    $unit        = $_POST["unit"] ?? "";
    $condition   = $_POST["item_condition"] ?? "";
    $location    = trim($_POST["location"] ?? "");
    $status      = $_POST["status"] ?? "available";

    $errors = [];

    if ($title === "") {
        $errors[] = "Title is required.";
    }
    if ($quantity < 1) {
        $quantity = 1;
    }
    if (!in_array($status, $statuses, true)) {
        $status = "available";
    }
    if ($unit !== "" && !in_array($unit, $units, true)) {
        $unit = "";
    }
    if ($condition !== "" && !in_array($condition, $conditions, true)) {
        $condition = "";
    }

    // category_id may be NULL (0 -> NULL).
    $cat_value = $category_id > 0 ? $category_id : null;

    if ($errors) {
        set_flash("error", implode(" ", $errors));
        header("Location: admin-donation-edit.php?id=" . $did);
        exit();
    }

    $sql = "
        UPDATE donations
        SET title = ?, description = ?, category_id = ?, quantity = ?,
            unit = ?, item_condition = ?, location = ?, status = ?
        WHERE id = ?
    ";

    $stmt = $conn->prepare($sql);
    // types: s s i i s s s s i
    $stmt->bind_param(
        "ssiissssi",
        $title,
        $description,
        $cat_value,
        $quantity,
        $unit,
        $condition,
        $location,
        $status,
        $did
    );
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        log_activity($conn, $admin_id, "donation.edit",
            "Edited \"" . $title . "\"");
    }

    set_flash(
        $ok ? "success" : "error",
        $ok ? htmlspecialchars($title) . " was updated." : "Could not save changes."
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

$stmt = $conn->prepare("
    SELECT id, title, description, category_id, quantity, unit,
           item_condition, location, status
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

$page_title = "Edit Donation";
$active_nav = "donations";

require_once __DIR__ . "/includes/admin-header.php";
?>


<div class="top-section">
    <div>
        <div class="eyebrow">Administration</div>
        <h2 class="serif">Edit Donation</h2>
    </div>
    <a class="btn btn-ghost btn-sm" href="admin-donations.php">&larr; Back to donations</a>
</div>


<div class="card" style="max-width:720px">
    <form method="post">
        <?php csrf_field(); ?>
        <input type="hidden" name="donation_id" value="<?= (int) $donation["id"] ?>">

        <div class="field">
            <label>Title</label>
            <input type="text" name="title" value="<?= e($donation["title"]) ?>" required>
        </div>

        <div class="field">
            <label>Description</label>
            <textarea name="description" rows="4"><?= e($donation["description"]) ?></textarea>
        </div>

        <div class="field">
            <label>Category</label>
            <select name="category_id">
                <option value="0">— Uncategorized —</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c["id"] ?>"
                        <?= (int) $donation["category_id"] === (int) $c["id"] ? "selected" : "" ?>>
                        <?= e($c["name"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label>Quantity</label>
            <input type="number" name="quantity" min="1"
                   value="<?= (int) $donation["quantity"] ?>">
        </div>

        <div class="field">
            <label>Unit</label>
            <select name="unit">
                <option value="">— None —</option>
                <?php foreach ($units as $u): ?>
                    <option value="<?= e($u) ?>" <?= $donation["unit"] === $u ? "selected" : "" ?>>
                        <?= e($u) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label>Condition</label>
            <select name="item_condition">
                <option value="">— Not specified —</option>
                <?php foreach ($conditions as $cond): ?>
                    <option value="<?= e($cond) ?>"
                        <?= $donation["item_condition"] === $cond ? "selected" : "" ?>>
                        <?= e($cond) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label>Location</label>
            <input type="text" name="location" value="<?= e($donation["location"]) ?>">
        </div>

        <div class="field">
            <label>Status</label>
            <select name="status">
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= $donation["status"] === $s ? "selected" : "" ?>>
                        <?= e(ucfirst($s)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-green">Save changes</button>
        <a class="btn btn-ghost" href="admin-donations.php">Cancel</a>
    </form>
</div>


<?php require_once __DIR__ . "/includes/admin-footer.php"; ?>
