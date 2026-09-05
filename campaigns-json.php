<?php

/*
|--------------------------------------------------------------------------
| ACTIVE CAMPAIGNS (JSON)
|--------------------------------------------------------------------------
|
| Feeds the "Our Ongoing Campaigns" grid on the landing page. Read-only,
| no login. Returns up to 6 active campaigns with live progress.
|
*/

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/helpers.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

$out = [];

if (table_exists($conn, "campaigns")) {

    $q = $conn->query("
        SELECT c.id, c.title, c.description, c.image, c.goal_quantity,
            cat.name AS category_name
        FROM campaigns c
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE c.status = 'active'
        ORDER BY c.created_at DESC
        LIMIT 6
    ");

    if ($q) {
        while ($row = $q->fetch_assoc()) {
            $goal      = (int) $row["goal_quantity"];
            $collected = campaign_collected_qty($conn, (int) $row["id"]);
            $pct       = $goal > 0 ? min(100, (int) round($collected * 100 / $goal)) : 0;

            $out[] = [
                "id"            => (int) $row["id"],
                "title"         => $row["title"],
                "description"   => $row["description"],
                "image"         => !empty($row["image"]) ? "uploads/campaigns/" . $row["image"] : "",
                "category_name" => $row["category_name"],
                "goal"          => $goal,
                "collected"     => $collected,
                "pct"           => $pct,
            ];
        }
    }
}

echo json_encode($out);
