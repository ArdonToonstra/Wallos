<?php
require_once '../../includes/connect_endpoint.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_GET['id']) && $_GET['id'] != "") {
        $incomeId = intval($_GET['id']);
        $query = "SELECT * FROM income WHERE id = :incomeId AND user_id = :userId";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':incomeId', $incomeId, SQLITE3_INTEGER);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['name'] = htmlspecialchars_decode($row['name'] ?? "");
            $row['notes'] = htmlspecialchars_decode($row['notes'] ?? "");
            header('Content-Type: application/json');
            echo json_encode($row);
        } else {
            echo json_encode(["success" => false, "message" => "Income not found"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Missing ID"]);
    }
}
$db->close();
?>
