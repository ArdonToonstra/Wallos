<?php
require_once '../../includes/connect_endpoint.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $query = "SELECT sa.*, 
              (SELECT ss.balance FROM savings_snapshots ss WHERE ss.account_id = sa.id ORDER BY ss.date DESC LIMIT 1) as latest_balance,
              (SELECT ss.date FROM savings_snapshots ss WHERE ss.account_id = sa.id ORDER BY ss.date DESC LIMIT 1) as latest_date
              FROM savings_accounts sa WHERE sa.user_id = :userId ORDER BY sa.name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $accounts = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['name'] = htmlspecialchars_decode($row['name'] ?? "");
        $row['institution'] = htmlspecialchars_decode($row['institution'] ?? "");
        $row['notes'] = htmlspecialchars_decode($row['notes'] ?? "");
        $accounts[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($accounts);
}
$db->close();
?>
