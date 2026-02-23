<?php
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/inputvalidation.php';

$isEdit = isset($_POST['id']) && $_POST['id'] != "";
$name = validate($_POST["name"]);
$amount = $_POST['amount'];
$currencyId = $_POST["currency_id"];
$type = $_POST["type"] ?? "recurring";
$frequency = $_POST["frequency"] ?? 1;
$cycle = $_POST["cycle"] ?? 3;
$categoryId = $_POST['category_id'];
$paymentMethodId = $_POST['payment_method_id'] ?? null;
$payerUserId = $_POST['payer_user_id'] ?? null;
$date = $_POST["date"];
$nextPayment = $_POST["next_payment"] ?? null;
$notes = validate($_POST["notes"] ?? "");
$inactive = isset($_POST['inactive']) ? 1 : 0;

if ($type === "one-off") {
    $nextPayment = null;
    $cycle = null;
    $frequency = null;
}

if (!$isEdit) {
    $sql = "INSERT INTO expenses (
                name, amount, currency_id, type, cycle, frequency, 
                category_id, payment_method_id, payer_user_id,
                date, next_payment, notes, inactive, user_id
            ) VALUES (
                :name, :amount, :currencyId, :type, :cycle, :frequency, 
                :categoryId, :paymentMethodId, :payerUserId,
                :date, :nextPayment, :notes, :inactive, :userId
            )";
} else {
    $id = $_POST['id'];
    $sql = "UPDATE expenses SET 
                name = :name, 
                amount = :amount, 
                currency_id = :currencyId,
                type = :type,
                cycle = :cycle,
                frequency = :frequency,
                category_id = :categoryId,
                payment_method_id = :paymentMethodId,
                payer_user_id = :payerUserId,
                date = :date,
                next_payment = :nextPayment,
                notes = :notes, 
                inactive = :inactive
            WHERE id = :id AND user_id = :userId";
}

$stmt = $db->prepare($sql);
$stmt->bindParam(':name', $name, SQLITE3_TEXT);
$stmt->bindParam(':amount', $amount, SQLITE3_FLOAT);
$stmt->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
$stmt->bindParam(':type', $type, SQLITE3_TEXT);
$stmt->bindParam(':cycle', $cycle, SQLITE3_INTEGER);
$stmt->bindParam(':frequency', $frequency, SQLITE3_INTEGER);
$stmt->bindParam(':categoryId', $categoryId, SQLITE3_INTEGER);
$stmt->bindParam(':paymentMethodId', $paymentMethodId, SQLITE3_INTEGER);
$stmt->bindParam(':payerUserId', $payerUserId, SQLITE3_INTEGER);
$stmt->bindParam(':date', $date, SQLITE3_TEXT);
$stmt->bindParam(':nextPayment', $nextPayment, SQLITE3_TEXT);
$stmt->bindParam(':notes', $notes, SQLITE3_TEXT);
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
        "message" => "Expense $text successfully"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $db->lastErrorMsg()
    ]);
}
$db->close();
?>
