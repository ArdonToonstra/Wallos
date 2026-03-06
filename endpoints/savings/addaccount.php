<?php
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/inputvalidation.php';

$isEdit = isset($_POST['id']) && $_POST['id'] != "";
$name = validate($_POST["name"]);
$type = $_POST["type"] ?? "savings";
$currencyId = $_POST["currency_id"];
$institution = validate($_POST["institution"] ?? "");
$notes = validate($_POST["notes"] ?? "");
$monthlyContribution = floatval($_POST["monthly_contribution"] ?? 0);
$inactive = isset($_POST['inactive']) ? 1 : 0;

if (!$isEdit) {
    $sql = "INSERT INTO savings_accounts (
                name, type, currency_id, institution, notes, monthly_contribution, inactive, user_id
            ) VALUES (
                :name, :type, :currencyId, :institution, :notes, :monthlyContribution, :inactive, :userId
            )";
} else {
    $id = $_POST['id'];
    $sql = "UPDATE savings_accounts SET 
                name = :name, 
                type = :type,
                currency_id = :currencyId,
                institution = :institution,
                notes = :notes, 
                monthly_contribution = :monthlyContribution,
                inactive = :inactive
            WHERE id = :id AND user_id = :userId";
}

$stmt = $db->prepare($sql);
$stmt->bindParam(':name', $name, SQLITE3_TEXT);
$stmt->bindParam(':type', $type, SQLITE3_TEXT);
$stmt->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
$stmt->bindParam(':institution', $institution, SQLITE3_TEXT);
$stmt->bindParam(':notes', $notes, SQLITE3_TEXT);
$stmt->bindParam(':monthlyContribution', $monthlyContribution);
$stmt->bindParam(':inactive', $inactive, SQLITE3_INTEGER);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($isEdit) {
    $stmt->bindParam(':id', $id, SQLITE3_INTEGER);
}

if ($stmt->execute()) {
    $text = $isEdit ? "updated" : "added";
    header('Content-Type: application/json');
    echo json_encode([
        "success" => true,
        "message" => "Account $text successfully"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $db->lastErrorMsg()
    ]);
}
$db->close();
?>
