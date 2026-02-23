<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$snapshotId = $data["id"];
$deleteQuery = "DELETE FROM savings_snapshots WHERE id = :snapshotId AND user_id = :userId";
$deleteStmt = $db->prepare($deleteQuery);
$deleteStmt->bindParam(':snapshotId', $snapshotId, SQLITE3_INTEGER);
$deleteStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($deleteStmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Snapshot deleted successfully"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Error deleting snapshot"
    ]);
}
$db->close();
?>
