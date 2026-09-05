<?php

/*
|--------------------------------------------------------------------------
| FEATURED CAMPAIGN (JSON)
|--------------------------------------------------------------------------
|
| Returns the current featured (most recent active) campaign for the
| landing page hero. Read-only, no login. Mirrors stats.php.
|
*/

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/helpers.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

$out = ["active" => false];

if (table_exists($conn, "campaigns")) {

    $q = $conn->query("
        SELECT c.*
        FROM campaigns c
        WHERE c.status = 'active'
        ORDER BY c.created_at DESC
        LIMIT 1
    ");

    if ($q && ($row = $q->fetch_assoc())) {

        $goal      = (int) $row["goal_quantity"];
        $collected = campaign_collected_qty($conn, (int) $row["id"]);
        $pct       = $goal > 0 ? min(100, (int) round($collected * 100 / $goal)) : 0;

        $out = [
            "active"       => true,
            "title"        => $row["title"],
            "needed_items" => $row["needed_items"],
            "partner_org"  => $row["partner_org"],
            "image"        => !empty($row["image"]) ? "uploads/campaigns/" . $row["image"] : "",
            "goal"         => $goal,
            "collected"    => $collected,
            "pct"          => $pct,
        ];
    }
}

echo json_encode($out);
