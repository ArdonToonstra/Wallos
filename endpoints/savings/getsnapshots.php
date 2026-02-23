<?php
require_once '../../includes/connect_endpoint.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $accountId = isset($_GET['account_id']) ? intval($_GET['account_id']) : null;
    
    if ($accountId) {
        $query = "SELECT ss.*, sa.name as account_name FROM savings_snapshots ss
                  JOIN savings_accounts sa ON ss.account_id = sa.id
                  WHERE ss.account_id = :accountId AND ss.user_id = :userId 
                  ORDER BY ss.date ASC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':accountId', $accountId, SQLITE3_INTEGER);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    } else {
        $query = "SELECT ss.*, sa.name as account_name FROM savings_snapshots ss
                  JOIN savings_accounts sa ON ss.account_id = sa.id
                  WHERE ss.user_id = :userId 
                  ORDER BY ss.date ASC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    }

    $result = $stmt->execute();
    $snapshots = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $snapshots[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($snapshots);
}
$db->close();
?>
