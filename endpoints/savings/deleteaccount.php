<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$accountId = $data["id"];

// Delete all snapshots for this account first
$deleteSnapshotsQuery = "DELETE FROM savings_snapshots WHERE account_id = :accountId AND user_id = :userId";
$deleteSnapshotsStmt = $db->prepare($deleteSnapshotsQuery);
$deleteSnapshotsStmt->bindParam(':accountId', $accountId, SQLITE3_INTEGER);
$deleteSnapshotsStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$deleteSnapshotsStmt->execute();

// Delete the account
$deleteQuery = "DELETE FROM savings_accounts WHERE id = :accountId AND user_id = :userId";
$deleteStmt = $db->prepare($deleteQuery);
$deleteStmt->bindParam(':accountId', $accountId, SQLITE3_INTEGER);
$deleteStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($deleteStmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Account deleted successfully"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Error deleting account"
    ]);
}
$db->close();
?>
