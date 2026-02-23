<?php
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$expectedReturnRate = floatval($_POST['expected_return_rate'] ?? 7.0);
$inflationRate = floatval($_POST['inflation_rate'] ?? 2.0);
$salaryGrowthRate = floatval($_POST['salary_growth_rate'] ?? 3.0);
$projectionYears = intval($_POST['projection_years'] ?? 10);

// Check if settings already exist
$query = "SELECT id FROM networth_settings WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$existing = $result->fetchArray(SQLITE3_ASSOC);

if ($existing) {
    $sql = "UPDATE networth_settings SET 
                expected_return_rate = :expectedReturnRate,
                inflation_rate = :inflationRate,
                salary_growth_rate = :salaryGrowthRate,
                projection_years = :projectionYears
            WHERE user_id = :userId";
} else {
    $sql = "INSERT INTO networth_settings (user_id, expected_return_rate, inflation_rate, salary_growth_rate, projection_years) 
            VALUES (:userId, :expectedReturnRate, :inflationRate, :salaryGrowthRate, :projectionYears)";
}

$stmt = $db->prepare($sql);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$stmt->bindParam(':expectedReturnRate', $expectedReturnRate, SQLITE3_FLOAT);
$stmt->bindParam(':inflationRate', $inflationRate, SQLITE3_FLOAT);
$stmt->bindParam(':salaryGrowthRate', $salaryGrowthRate, SQLITE3_FLOAT);
$stmt->bindParam(':projectionYears', $projectionYears, SQLITE3_INTEGER);

if ($stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode([
        "success" => true,
        "message" => "Settings saved successfully"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $db->lastErrorMsg()
    ]);
}
$db->close();
?>
