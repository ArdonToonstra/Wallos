<?php
/*
This API Endpoint accepts both POST and GET requests.
Parameters:
- api_key: the API key of the user (required).

Returns a JSON object with savings accounts and their latest balances.
*/

require_once '../../includes/connect_endpoint.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER["REQUEST_METHOD"] === "POST" || $_SERVER["REQUEST_METHOD"] === "GET") {
    $apiKey = $_REQUEST['api_key'] ?? $_REQUEST['apiKey'] ?? null;
    if (!$apiKey) {
        echo json_encode(["success" => false, "title" => "Missing parameters"]);
        exit;
    }

    $sql = "SELECT * FROM user WHERE api_key = :apiKey";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':apiKey', $apiKey);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        echo json_encode(["success" => false, "title" => "Invalid API key"]);
        exit;
    }
    $userId = $user['id'];

    // Get accounts with latest balance
    $sql = "SELECT sa.*, cur.name as currency_name, cur.symbol as currency_symbol,
                   (SELECT ss.balance FROM savings_snapshots ss WHERE ss.account_id = sa.id ORDER BY ss.date DESC LIMIT 1) as latest_balance,
                   (SELECT ss.date FROM savings_snapshots ss WHERE ss.account_id = sa.id ORDER BY ss.date DESC LIMIT 1) as latest_date
            FROM savings_accounts sa
            LEFT JOIN currencies cur ON sa.currency_id = cur.id
            WHERE sa.user_id = :userId
            ORDER BY sa.name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $accounts = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $accounts[] = $row;
    }

    // Get all snapshots
    $sql = "SELECT ss.*, sa.name as account_name 
            FROM savings_snapshots ss 
            JOIN savings_accounts sa ON ss.account_id = sa.id 
            WHERE sa.user_id = :userId 
            ORDER BY ss.date ASC";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $snapshots = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $snapshots[] = $row;
    }

    echo json_encode([
        "success" => true,
        "title" => "savings",
        "accounts" => $accounts,
        "snapshots" => $snapshots,
        "notes" => []
    ]);
} else {
    echo json_encode(["success" => false, "title" => "Invalid request method"]);
}
