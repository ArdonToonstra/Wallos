<?php
require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(["success" => false, "message" => "Not authenticated"]);
    exit;
}

header('Content-Type: application/json');

// Helper function
function getPricePerMonth($cycle, $frequency, $price) {
    if (!$cycle || !$frequency) return 0;
    switch ($cycle) {
        case 1: return $price * (30 / $frequency);
        case 2: return $price * (4.35 / $frequency);
        case 3: return $price * (1 / $frequency);
        case 4: return $price / (12 * $frequency);
        default: return 0;
    }
}

function getPriceConverted($price, $currency, $database, $userId) {
    $query = "SELECT rate FROM currencies WHERE id = :currency AND user_id = :userId";
    $stmt = $database->prepare($query);
    $stmt->bindParam(':currency', $currency, SQLITE3_INTEGER);
    $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $exchangeRate = $result->fetchArray(SQLITE3_ASSOC);
    if ($exchangeRate === false) return $price;
    return $price / $exchangeRate['rate'];
}

// Get net worth settings
$query = "SELECT * FROM networth_settings WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
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

// ========== CALCULATE MONTHLY INCOME ==========
$totalMonthlyIncome = 0;
$query = "SELECT * FROM income WHERE user_id = :userId AND inactive = 0";
$stmt = $db->prepare($query);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$incomeItems = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $convertedAmount = getPriceConverted($row['amount'], $row['currency_id'], $db, $userId);
    if ($row['type'] === 'recurring') {
        $monthly = getPricePerMonth($row['cycle'], $row['frequency'], $convertedAmount);
    } else {
        $monthly = 0; // one-off income doesn't count for monthly projections
    }
    $totalMonthlyIncome += $monthly;
    $incomeItems[] = array_merge($row, ['monthly_amount' => $monthly]);
}

// ========== CALCULATE MONTHLY EXPENSES ==========
$totalMonthlyExpenses = 0;
$query = "SELECT * FROM expenses WHERE user_id = :userId AND inactive = 0";
$stmt = $db->prepare($query);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $convertedAmount = getPriceConverted($row['amount'], $row['currency_id'], $db, $userId);
    if ($row['type'] === 'recurring') {
        $totalMonthlyExpenses += getPricePerMonth($row['cycle'], $row['frequency'], $convertedAmount);
    }
}

// ========== CALCULATE MONTHLY SUBSCRIPTIONS ==========
$totalMonthlySubscriptions = 0;
$query = "SELECT * FROM subscriptions WHERE user_id = :userId AND inactive = 0";
$stmt = $db->prepare($query);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $sharePercentage = isset($row['share_percentage']) ? (int) $row['share_percentage'] : 100;
    $price = floatval($row['price']) * ($sharePercentage / 100);
    $convertedPrice = getPriceConverted($price, $row['currency_id'], $db, $userId);
    $totalMonthlySubscriptions += getPricePerMonth($row['cycle'], $row['frequency'], $convertedPrice);
}

// ========== GET CURRENT SAVINGS/INVESTMENT TOTALS ==========
$totalSavings = 0;
$totalInvestments = 0;
$accountBalances = [];

$query = "SELECT sa.*, 
          (SELECT ss.balance FROM savings_snapshots ss WHERE ss.account_id = sa.id ORDER BY ss.date DESC LIMIT 1) as latest_balance
          FROM savings_accounts sa WHERE sa.user_id = :userId AND sa.inactive = 0";
$stmt = $db->prepare($query);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $balance = floatval($row['latest_balance'] ?? 0);
    $convertedBalance = getPriceConverted($balance, $row['currency_id'], $db, $userId);
    
    if (in_array($row['type'], ['investment', 'stocks', 'crypto', 'retirement'])) {
        $totalInvestments += $convertedBalance;
    } else {
        $totalSavings += $convertedBalance;
    }
    
    $accountBalances[] = [
        'name' => $row['name'],
        'type' => $row['type'],
        'balance' => $convertedBalance,
        'latest_balance' => $convertedBalance,
        'institution' => $row['institution']
    ];
}

// ========== SAVINGS HISTORY ==========
$query = "SELECT ss.date, SUM(ss.balance) as total_balance 
          FROM savings_snapshots ss 
          JOIN savings_accounts sa ON ss.account_id = sa.id
          WHERE ss.user_id = :userId 
          GROUP BY ss.date 
          ORDER BY ss.date ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$savingsHistory = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $row['total'] = $row['total_balance'];
    $savingsHistory[] = $row;
}

// ========== PROJECTIONS ==========
$currentNetWorth = $totalSavings + $totalInvestments;
$monthlyNetIncome = $totalMonthlyIncome - $totalMonthlyExpenses - $totalMonthlySubscriptions;
$expectedReturnRate = $settings['expected_return_rate'] / 100;
$inflationRate = $settings['inflation_rate'] / 100;
$salaryGrowthRate = $settings['salary_growth_rate'] / 100;
$projectionYears = $settings['projection_years'];

$projections = [];
$projectedSavings = $totalSavings;
$projectedInvestments = $totalInvestments;
$projectedMonthlyIncome = $totalMonthlyIncome;
$projectedMonthlyExpenses = $totalMonthlyExpenses + $totalMonthlySubscriptions;

// Monthly savings rate (what's left after expenses goes to savings/investments)
for ($month = 0; $month <= $projectionYears * 12; $month++) {
    $year = floor($month / 12);
    
    if ($month > 0) {
        // Annual adjustments at start of each year
        if ($month % 12 === 0) {
            $projectedMonthlyIncome *= (1 + $salaryGrowthRate);
            $projectedMonthlyExpenses *= (1 + $inflationRate);
        }
        
        // Monthly investment returns
        $monthlyReturn = $projectedInvestments * ($expectedReturnRate / 12);
        $projectedInvestments += $monthlyReturn;
        
        // Monthly net savings
        $monthlySurplus = $projectedMonthlyIncome - $projectedMonthlyExpenses;
        if ($monthlySurplus > 0) {
            // Split surplus: 50% savings, 50% investments (simplified)
            $projectedSavings += $monthlySurplus * 0.5;
            $projectedInvestments += $monthlySurplus * 0.5;
        } else {
            // Draw from savings if negative
            $projectedSavings += $monthlySurplus;
        }
    }
    
    // Record data point every 3 months
    if ($month % 3 === 0) {
        $projections[] = [
            'month' => $month,
            'year' => round($month / 12, 1),
            'label' => $month == 0 ? 'Now' : '+' . $year . 'y' . ($month % 12 > 0 ? ($month % 12) . 'm' : ''),
            'savings' => round($projectedSavings, 2),
            'investments' => round($projectedInvestments, 2),
            'net_worth' => round($projectedSavings + $projectedInvestments, 2),
            'total' => round($projectedSavings + $projectedInvestments, 2),
            'monthly_income' => round($projectedMonthlyIncome, 2),
            'monthly_expenses' => round($projectedMonthlyExpenses, 2)
        ];
    }
}

echo json_encode([
    "success" => true,
    "current_summary" => [
        "monthly_income" => round($totalMonthlyIncome, 2),
        "monthly_expenses" => round($totalMonthlyExpenses, 2),
        "monthly_subscriptions" => round($totalMonthlySubscriptions, 2),
        "monthly_outflow" => round($totalMonthlyExpenses + $totalMonthlySubscriptions, 2),
        "monthly_net" => round($monthlyNetIncome, 2),
        "total_savings" => round($totalSavings, 2),
        "total_investments" => round($totalInvestments, 2),
        "current_net_worth" => round($currentNetWorth, 2)
    ],
    "account_balances" => $accountBalances,
    "savings_history" => $savingsHistory,
    "projections" => $projections,
    "settings" => $settings
]);

$db->close();
?>
