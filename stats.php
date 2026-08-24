<?php

/*
|--------------------------------------------------------------------------
| PUBLIC IMPACT STATS (JSON)
|--------------------------------------------------------------------------
|
| Returns live counts for the landing page "Our Impact" section.
| Read-only, no login required. index.html fetches this on load.
|
*/

require_once __DIR__ . "/config/database.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function count_rows($conn, $sql)
{
    $res = $conn->query($sql);
    if (!$res) {
        return 0;
    }
    return (int) ($res->fetch_assoc()["c"] ?? 0);
}

$data = [
    // Total donations posted.
    "items" => count_rows(
        $conn,
        "SELECT COUNT(*) AS c FROM donations"
    ),

    // Requests that were approved or completed = people helped.
    "beneficiaries" => count_rows(
        $conn,
        "SELECT COUNT(*) AS c FROM donation_requests
         WHERE status IN ('approved','completed')"
    ),

    // Registered donors.
    "donors" => count_rows(
        $conn,
        "SELECT COUNT(*) AS c FROM users WHERE role = 'donor'"
    ),

    // Registered recipients.
    "recipients" => count_rows(
        $conn,
        "SELECT COUNT(*) AS c FROM users WHERE role = 'recipient'"
    ),
];

echo json_encode($data);
