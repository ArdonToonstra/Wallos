<?php
/*
This API Endpoint accepts both POST and GET requests.
Parameters:
- api_key: the API key of the user (required).
- state: filter by active/inactive (0=active, 1=inactive) (optional).

Returns a JSON object with expenses for the authenticated user.
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

    $state = isset($_REQUEST['state']) ? intval($_REQUEST['state']) : null;

    $sql = "SELECT e.*, c.name as category_name, cur.name as currency_name, cur.symbol as currency_symbol,
                   pm.name as payment_method_name
            FROM expenses e 
            LEFT JOIN categories c ON e.category_id = c.id 
            LEFT JOIN currencies cur ON e.currency_id = cur.id 
            LEFT JOIN payment_methods pm ON e.payment_method_id = pm.id
            WHERE e.user_id = :userId";
    
    if ($state !== null) {
        $sql .= " AND e.inactive = :state";
    }
    $sql .= " ORDER BY e.name ASC";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    if ($state !== null) {
        $stmt->bindValue(':state', $state, SQLITE3_INTEGER);
    }
    $result = $stmt->execute();

    $expenses = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $expenses[] = $row;
    }

    echo json_encode([
        "success" => true,
        "title" => "expenses",
        "expenses" => $expenses,
        "notes" => []
    ]);
} else {
    echo json_encode(["success" => false, "title" => "Invalid request method"]);
}
