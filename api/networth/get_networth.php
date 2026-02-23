<?php
/*
This API Endpoint accepts both POST and GET requests.
Parameters:
- api_key: the API key of the user (required).

Returns a JSON object with net worth calculations, projections and account balances.
*/

require_once '../../includes/connect_endpoint.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER["REQUEST_METHOD"] === "POST" || $_SERVER["REQUEST_METHOD"] === "GET") {
    $apiKey = $_REQUEST['api_key'] ?? $_REQUEST['apiKey'] ?? null;
    if (!$apiKey) {
        echo json_encode(["success" => false, "title" => "Missing parameters"]);
        exit;
    }

    $sql = "SELECT * FROM user WHERE api_key = :apiKey";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':apiKey', $apiKey);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        echo json_encode(["success" => false, "title" => "Invalid API key"]);
        exit;
    }
    $userId = $user['id'];

    // Forward to the calculate endpoint logic
    $_SESSION = ['userId' => $userId];
    
    // Get settings
    $sql = "SELECT * FROM networth_settings WHERE user_id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $settings = $result->fetchArray(SQLITE3_ASSOC);

    if (!$settings) {
        $settings = [
            'expected_return_rate' => 7.0,
            'inflation_rate' => 2.0,
            'salary_growth_rate' => 3.0,
            'projection_years' => 10
        ];
    }

    // Monthly income
    $sql = "SELECT SUM(
                CASE 
                    WHEN type = 'recurring' THEN
                        CASE cycle
                            WHEN 1 THEN amount * frequency * 30
                            WHEN 2 THEN amount * frequency * 4.33
                            WHEN 3 THEN amount * frequency
                            WHEN 4 THEN amount * frequency / 12
                            ELSE amount
                        END
                    ELSE 0
                END
            ) as monthly_income FROM income WHERE user_id = :userId AND inactive = 0";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $monthlyIncome = $row['monthly_income'] ?? 0;

    // Monthly expenses
    $sql = "SELECT SUM(
                CASE 
                    WHEN type = 'recurring' THEN
                        CASE cycle
                            WHEN 1 THEN amount * frequency * 30
                            WHEN 2 THEN amount * frequency * 4.33
                            WHEN 3 THEN amount * frequency
                            WHEN 4 THEN amount * frequency / 12
                            ELSE amount
                        END
                    ELSE 0
                END
            ) as monthly_expenses FROM expenses WHERE user_id = :userId AND inactive = 0";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $monthlyExpenses = $row['monthly_expenses'] ?? 0;

    // Monthly subscriptions
    $sql = "SELECT SUM(
                CASE cycle
                    WHEN 1 THEN price * frequency * 30
                    WHEN 2 THEN price * frequency * 4.33
                    WHEN 3 THEN price * frequency
                    WHEN 4 THEN price * frequency / 12
                    ELSE price
                END
            ) as monthly_subscriptions FROM subscriptions WHERE user_id = :userId AND inactive = 0";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $monthlySubscriptions = $row['monthly_subscriptions'] ?? 0;

    $monthlyOutflow = $monthlyExpenses + $monthlySubscriptions;

    // Savings accounts with latest balances
    $sql = "SELECT sa.*, 
                   (SELECT ss.balance FROM savings_snapshots ss WHERE ss.account_id = sa.id ORDER BY ss.date DESC LIMIT 1) as latest_balance
            FROM savings_accounts sa WHERE sa.user_id = :userId AND sa.inactive = 0";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $savingsTotal = 0;
    $investmentTotal = 0;
    $accountBalances = [];
    while ($account = $result->fetchArray(SQLITE3_ASSOC)) {
        $balance = $account['latest_balance'] ?? 0;
        $accountBalances[] = $account;
        if (in_array($account['type'], ['investment', 'stocks', 'crypto', 'retirement'])) {
            $investmentTotal += $balance;
        } else {
            $savingsTotal += $balance;
        }
    }

    $currentNetWorth = $savingsTotal + $investmentTotal;
    $monthlySurplus = $monthlyIncome - $monthlyOutflow;

    echo json_encode([
        "success" => true,
        "title" => "networth",
        "current_summary" => [
            "monthly_income" => round($monthlyIncome, 2),
            "monthly_expenses" => round($monthlyExpenses, 2),
            "monthly_subscriptions" => round($monthlySubscriptions, 2),
            "monthly_outflow" => round($monthlyOutflow, 2),
            "monthly_surplus" => round($monthlySurplus, 2),
            "current_net_worth" => round($currentNetWorth, 2),
            "savings_total" => round($savingsTotal, 2),
            "investment_total" => round($investmentTotal, 2)
        ],
        "account_balances" => $accountBalances,
        "settings" => $settings,
        "notes" => []
    ]);
} else {
    echo json_encode(["success" => false, "title" => "Invalid request method"]);
}
