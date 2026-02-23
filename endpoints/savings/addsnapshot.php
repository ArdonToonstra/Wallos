<?php
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$isEdit = isset($_POST['id']) && $_POST['id'] != "";
$accountId = $_POST['account_id'];
$balance = $_POST['balance'];
$date = $_POST['date'];

// Verify account belongs to user
$checkQuery = "SELECT id FROM savings_accounts WHERE id = :accountId AND user_id = :userId";
$checkStmt = $db->prepare($checkQuery);
$checkStmt->bindParam(':accountId', $accountId, SQLITE3_INTEGER);
$checkStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$checkResult = $checkStmt->execute();
if (!$checkResult->fetchArray(SQLITE3_ASSOC)) {
    echo json_encode(["success" => false, "message" => "Account not found"]);
    $db->close();
    exit();
}

if (!$isEdit) {
    $sql = "INSERT INTO savings_snapshots (account_id, user_id, balance, date) 
            VALUES (:accountId, :userId, :balance, :date)";
} else {
    $id = $_POST['id'];
    $sql = "UPDATE savings_snapshots SET 
                account_id = :accountId,
                balance = :balance,
                date = :date
            WHERE id = :id AND user_id = :userId";
}

$stmt = $db->prepare($sql);
$stmt->bindParam(':accountId', $accountId, SQLITE3_INTEGER);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$stmt->bindParam(':balance', $balance, SQLITE3_FLOAT);
$stmt->bindParam(':date', $date, SQLITE3_TEXT);

if ($isEdit) {
    $stmt->bindParam(':id', $id, SQLITE3_INTEGER);
}

if ($stmt->execute()) {
    $text = $isEdit ? "updated" : "added";
    header('Content-Type: application/json');
    echo json_encode([
        "success" => true,
        "message" => "Snapshot $text successfully"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $db->lastErrorMsg()
    ]);
}
$db->close();
?>
