<?php

/*
|--------------------------------------------------------------------------
| ADMIN — ACTIVITY LOG
|--------------------------------------------------------------------------
|
| Read-only record of admin actions (who did what, when). Populated by
| log_activity() from the other admin pages. Shows the most recent
| first, paginated.
|
*/

require_once __DIR__ . "/config/admin-guard.php";

$page_title = "Activity Log";
$active_nav = "activity";

$has_log = table_exists($conn, "activity_log");

$entries = [];
$total   = 0;
$meta    = paginate(0, 20);

if ($has_log) {

    $total = (int) ($conn->query("SELECT COUNT(*) AS c FROM activity_log")
        ->fetch_assoc()["c"] ?? 0);

    $meta = paginate($total, 20);

    $stmt = $conn->prepare("
        SELECT a.id, a.admin_id, a.action, a.detail, a.created_at,
               u.name AS admin_name
        FROM activity_log a
        LEFT JOIN users u ON a.admin_id = u.id
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii", $meta["per_page"], $meta["offset"]);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $entries[] = $row;
    }
    $stmt->close();
}

require_once __DIR__ . "/includes/admin-header.php";
?>


<div class="top-section">
    <div>
        <div class="eyebrow">Administration</div>
        <h2 class="serif">Activity Log</h2>
    </div>
</div>


<?php if (!$has_log): ?>

    <div class="flash flash-error">
        The <b>activity_log</b> table doesn't exist yet, so nothing is being
        recorded. Re-run <code>schema.sql</code> in phpMyAdmin to create it,
        then admin actions will start appearing here.
    </div>

<?php else: ?>

    <div class="table">

        <div class="table-title">
            <span>Recent Admin Activity</span>
            <span style="font-weight:400;color:#7a8179;font-size:12px"><?= $total ?> total</span>
        </div>

        <?php if (count($entries) === 0): ?>

            <div class="empty">
                <strong>No activity yet</strong>
                <span>Admin actions (approvals, edits, deletions) will show up here.</span>
            </div>

        <?php else: ?>

            <div class="table-scroll">
                <table>
                    <tr>
                        <th>When</th>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Details</th>
                    </tr>

                    <?php foreach ($entries as $en): ?>
                        <?php
                        $when = !empty($en["created_at"])
                            ? date("M d, Y · g:i A", strtotime($en["created_at"]))
                            : "—";
                        ?>
                        <tr>
                            <td style="white-space:nowrap"><?= e($when) ?></td>
                            <td><?= e($en["admin_name"] ?? ("#" . $en["admin_id"])) ?></td>
                            <td><span class="status neutral"><?= e($en["action"]) ?></span></td>
                            <td><?= e($en["detail"] ?? "") ?></td>
                        </tr>
                    <?php endforeach; ?>

                </table>
            </div>

            <?php pagination_links($meta, []); ?>

        <?php endif; ?>

    </div>

<?php endif; ?>


<?php require_once __DIR__ . "/includes/admin-footer.php"; ?>
