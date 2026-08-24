<?php

/*
|--------------------------------------------------------------------------
| ADMIN — CATEGORY MANAGEMENT
|--------------------------------------------------------------------------
|
| List categories with how many donations use each, and add / rename /
| delete them. Deleting a category does NOT delete donations: the
| donations.category_id foreign key is ON DELETE SET NULL, so those
| donations simply become uncategorised.
|
*/

require_once __DIR__ . "/config/admin-guard.php";

$return = "admin-categories.php";


/*
|--------------------------------------------------------------------------
| HANDLE ACTIONS (POST)
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    csrf_require($return);

    $action = $_POST["action"] ?? "";


    /* --- Add category --- */

    if ($action === "add_category") {

        $name = trim($_POST["name"] ?? "");

        if ($name === "") {
            set_flash("error", "Please enter a category name.");
            header("Location: " . $return);
            exit();
        }

        if (mb_strlen($name) > 100) {
            set_flash("error", "Category name is too long (max 100 characters).");
            header("Location: " . $return);
            exit();
        }

        // Reject duplicates (case-insensitive; column is UNIQUE anyway).
        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $dup = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($dup) {
            set_flash("error", "A category called \"" . htmlspecialchars($name) . "\" already exists.");
            header("Location: " . $return);
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            log_activity($conn, $admin_id, "category.add", "Added \"" . $name . "\"");
        }

        set_flash(
            $ok ? "success" : "error",
            $ok ? "Added category \"" . htmlspecialchars($name) . "\"." : "Could not add the category."
        );

        header("Location: " . $return);
        exit();
    }


    /* --- Rename category --- */

    if ($action === "rename_category") {

        $cid  = (int) ($_POST["category_id"] ?? 0);
        $name = trim($_POST["name"] ?? "");

        if ($name === "") {
            set_flash("error", "Please enter a category name.");
            header("Location: " . $return);
            exit();
        }

        if (mb_strlen($name) > 100) {
            set_flash("error", "Category name is too long (max 100 characters).");
            header("Location: " . $return);
            exit();
        }

        // Make sure the target exists.
        $stmt = $conn->prepare("SELECT id FROM categories WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $cid);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$exists) {
            set_flash("error", "That category no longer exists.");
            header("Location: " . $return);
            exit();
        }

        // Reject a duplicate name held by a DIFFERENT category.
        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id <> ? LIMIT 1");
        $stmt->bind_param("si", $name, $cid);
        $stmt->execute();
        $dup = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($dup) {
            set_flash("error", "Another category already uses that name.");
            header("Location: " . $return);
            exit();
        }

        $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $name, $cid);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            log_activity($conn, $admin_id, "category.rename", "Renamed to \"" . $name . "\"");
        }

        set_flash(
            $ok ? "success" : "error",
            $ok ? "Category renamed to \"" . htmlspecialchars($name) . "\"." : "Could not rename the category."
        );

        header("Location: " . $return);
        exit();
    }


    /* --- Delete category --- */

    if ($action === "delete_category") {

        $cid = (int) ($_POST["category_id"] ?? 0);

        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $cid);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            log_activity($conn, $admin_id, "category.delete", "Deleted category #" . $cid);
        }

        set_flash(
            $ok ? "success" : "error",
            $ok
                ? "Category deleted. Any donations in it are now uncategorised."
                : "Could not delete the category."
        );

        header("Location: " . $return);
        exit();
    }

    set_flash("error", "Unknown action.");
    header("Location: " . $return);
    exit();
}


/*
|--------------------------------------------------------------------------
| LOAD CATEGORIES + DONATION COUNTS
|--------------------------------------------------------------------------
*/

$categories = [];

$sql = "
    SELECT c.id, c.name, COUNT(d.id) AS donation_count
    FROM categories c
    LEFT JOIN donations d ON d.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
";

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $categories[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| RENDER
|--------------------------------------------------------------------------
*/

$page_title = "Categories";
$active_nav = "categories";

require_once __DIR__ . "/includes/admin-header.php";
?>


<div class="top-section">
    <div>
        <div class="eyebrow">Administration</div>
        <h2 class="serif">Category Management</h2>
    </div>
</div>


<!-- ADD CATEGORY -->
<div class="card" style="max-width:720px">
    <form method="post" class="toolbar">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="add_category">
        <input type="text" name="name" placeholder="New category name" required
               style="flex:1;min-width:220px">
        <button type="submit" class="btn btn-green btn-sm">Add category</button>
    </form>
</div>


<!-- CATEGORY LIST -->
<div class="table">

    <div class="table-title">
        <span>All Categories</span>
        <span style="font-weight:400;color:#7a8179;font-size:12px"><?= count($categories) ?> total</span>
    </div>

    <?php if (count($categories) === 0): ?>

        <div class="empty">
            <strong>No categories yet</strong>
            <span>Add one above to get started.</span>
        </div>

    <?php else: ?>

        <div class="table-scroll">
            <table>
                <tr>
                    <th>Category</th>
                    <th>Donations</th>
                    <th>Actions</th>
                </tr>

                <?php foreach ($categories as $c): ?>
                    <?php
                    $cid   = (int) $c["id"];
                    $count = (int) $c["donation_count"];
                    ?>
                    <tr>
                        <td>
                            <!-- Rename (inline) -->
                            <form method="post" class="toolbar" style="gap:8px">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="rename_category">
                                <input type="hidden" name="category_id" value="<?= $cid ?>">
                                <input type="text" name="name" value="<?= e($c["name"]) ?>"
                                       style="min-width:200px" required>
                                <button type="submit" class="btn btn-ghost btn-sm">Rename</button>
                            </form>
                        </td>
                        <td><?= $count ?></td>
                        <td>
                            <form method="post" class="inline-form"
                                  onsubmit="return confirm('Delete category &quot;<?= e(addslashes($c["name"])) ?>&quot;?<?= $count > 0 ? " " . $count . " donation(s) will become uncategorised." : "" ?>');">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="category_id" value="<?= $cid ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

            </table>
        </div>

    <?php endif; ?>

</div>


<?php require_once __DIR__ . "/includes/admin-footer.php"; ?>
