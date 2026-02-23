<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$incomeId = $data["id"];
$deleteQuery = "DELETE FROM income WHERE id = :incomeId AND user_id = :userId";
$deleteStmt = $db->prepare($deleteQuery);
$deleteStmt->bindParam(':incomeId', $incomeId, SQLITE3_INTEGER);
$deleteStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($deleteStmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Income deleted successfully"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Error deleting income"
    ]);
}
$db->close();
?>
