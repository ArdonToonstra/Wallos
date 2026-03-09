<?php

require_once 'includes/header.php';

// ============================================
// Helper functions
// ============================================

function getPricePerMonthFire($cycle, $frequency, $price) {
    $frequency = floatval($frequency);
    if ($frequency <= 0) return 0;
    switch (intval($cycle)) {
        case 1: return $price * (30 / $frequency);    // daily
        case 2: return $price * (4.35 / $frequency);  // weekly
        case 3: return $price / $frequency;             // monthly
        case 4: return $price / (12 * $frequency);     // yearly
        default: return $price;
    }
}

function getPriceConvertedFire($price, $currencyId, $db, $userId) {
    $query = "SELECT rate FROM currencies WHERE id = :currency AND user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':currency', $currencyId, SQLITE3_INTEGER);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row === false || floatval($row['rate']) == 0) return $price;
    return $price / floatval($row['rate']);
}

// Get main currency symbol
$query = "SELECT c.symbol, c.code FROM currencies c INNER JOIN user u ON c.id = u.main_currency WHERE u.id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$currencySymbol = $row['symbol'] ?? '$';
$currencyCode = $row['code'] ?? 'USD';

// Get net worth settings for default rates
$query = "SELECT * FROM networth_settings WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$nwSettings = $result->fetchArray(SQLITE3_ASSOC);

$defaultReturn = $nwSettings ? $nwSettings['expected_return_rate'] : 7.0;
$defaultInflation = $nwSettings ? $nwSettings['inflation_rate'] : 3.0;

// Get birthdate and calculate current age
$query = "SELECT birthdate FROM user WHERE id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$userRow = $result->fetchArray(SQLITE3_ASSOC);
$birthdate = $userRow['birthdate'] ?? '';
$calculatedAge = 30;
$hasBirthdate = false;
if (!empty($birthdate)) {
    try {
        $birthDt = new DateTime($birthdate);
        $now = new DateTime();
        $age = $now->diff($birthDt)->y;
        if ($age >= 10 && $age <= 100) {
            $calculatedAge = $age;
            $hasBirthdate = true;
        }
    } catch (Exception $e) {
        // Invalid date, use default
    }
}

// Get total savings balance and monthly contributions
$query = "SELECT sa.currency_id,
          (SELECT ss.balance FROM savings_snapshots ss WHERE ss.account_id = sa.id ORDER BY ss.date DESC LIMIT 1) as latest_balance,
          sa.monthly_contribution
          FROM savings_accounts sa WHERE sa.user_id = :userId AND sa.inactive = 0";
$stmt = $db->prepare($query);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$totalSavingsBalance = 0;
$totalMonthlyContributions = 0;
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $balance = floatval($row['latest_balance'] ?? 0);
    $convertedBalance = getPriceConvertedFire($balance, $row['currency_id'], $db, $userId);
    $totalSavingsBalance += $convertedBalance;

    $contrib = floatval($row['monthly_contribution'] ?? 0);
    $convertedContrib = getPriceConvertedFire($contrib, $row['currency_id'], $db, $userId);
    $totalMonthlyContributions += $convertedContrib;
}

// Get monthly income (recurring only, with currency conversion)
$monthlyIncome = 0;
$query = "SELECT amount, cycle, frequency, currency_id, type FROM income WHERE user_id = :userId AND inactive = 0";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    if ($row['type'] === 'recurring') {
        $converted = getPriceConvertedFire(floatval($row['amount']), $row['currency_id'], $db, $userId);
        $monthlyIncome += getPricePerMonthFire($row['cycle'], $row['frequency'], $converted);
    }
}

// Get monthly expenses (recurring only, with currency conversion)
$monthlyExpenses = 0;
$query = "SELECT amount, cycle, frequency, currency_id, type FROM expenses WHERE user_id = :userId AND inactive = 0";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    if ($row['type'] === 'recurring') {
        $converted = getPriceConvertedFire(floatval($row['amount']), $row['currency_id'], $db, $userId);
        $monthlyExpenses += getPricePerMonthFire($row['cycle'], $row['frequency'], $converted);
    }
}

// Get monthly subscriptions (with share_percentage and currency conversion)
$monthlySubscriptions = 0;
$query = "SELECT price, cycle, frequency, currency_id, share_percentage FROM subscriptions WHERE user_id = :userId AND inactive = 0";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $sharePercentage = isset($row['share_percentage']) ? intval($row['share_percentage']) : 100;
    $price = floatval($row['price']) * ($sharePercentage / 100);
    $converted = getPriceConvertedFire($price, $row['currency_id'], $db, $userId);
    $monthlySubscriptions += getPricePerMonthFire($row['cycle'], $row['frequency'], $converted);
}

$monthlyOutflow = $monthlyExpenses + $monthlySubscriptions;
$annualIncome = round($monthlyIncome * 12);
$annualExpenses = round($monthlyOutflow * 12);
$annualContribution = round($totalMonthlyContributions * 12);
$annualNetContribution = round(($monthlyIncome - $monthlyOutflow) * 12);

$db->close();
?>

<link rel="stylesheet" href="styles/fire.css?<?= $version ?>">

<section class="contain">
    <div class="split-header">
        <h2>
            <i class="fa-solid fa-fire"></i>
            FIRE Calculator
        </h2>
    </div>
    <p class="fire-subtitle">Calculate your path to Financial Independence using the 25x expenses rule.</p>

    <!-- Input Form -->
    <div class="fire-layout">
        <div class="fire-inputs">
            <div class="fire-card">
                <h3><i class="fa-solid fa-user"></i> Your Information</h3>
                <div class="fire-form">
                    <div class="form-group">
                        <label for="fire-current-age">Current Age</label>
                        <input type="number" id="fire-current-age" value="<?= $calculatedAge ?>" min="18" max="80" step="1">
                        <?php if ($hasBirthdate): ?>
                        <small>Calculated from your birthdate &mdash; <a href="profile.php">update in Profile</a></small>
                        <?php else: ?>
                        <small>Set your <a href="profile.php">birthdate in Profile</a> to auto-calculate</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="fire-retirement-age">Target Retirement Age</label>
                        <input type="number" id="fire-retirement-age" value="55" min="25" max="90" step="1">
                    </div>
                    <div class="form-group">
                        <label for="fire-current-savings">Current Savings (<?= htmlspecialchars($currencySymbol) ?>)</label>
                        <input type="number" id="fire-current-savings" value="<?= round($totalSavingsBalance) ?>" min="0" step="1000">
                        <small>From your savings &amp; investment accounts</small>
                    </div>
                    <div class="form-group">
                        <label for="fire-annual-contribution">
                            Annual Contribution (<?= htmlspecialchars($currencySymbol) ?>)
                            <span class="fire-toggle-monthly" id="contribution-toggle" title="Toggle monthly/annual view">
                                <i class="fa-solid fa-arrows-rotate"></i>
                            </span>
                        </label>
                        <div class="fire-source-toggle">
                            <button type="button" class="fire-source-btn active" id="contribution-source-savings" onclick="setContributionSource('savings')">Savings</button>
                            <button type="button" class="fire-source-btn" id="contribution-source-net" onclick="setContributionSource('net')">Net Income</button>
                        </div>
                        <input type="number" id="fire-annual-contribution" value="<?= $annualContribution ?>" min="0" step="500">
                        <small id="contribution-hint" class="fire-hint"></small>
                        <small id="contribution-source-hint">From monthly contributions in Savings &amp; Investments</small>
                    </div>
                    <div class="form-group">
                        <label for="fire-annual-income">Annual Net Income (<?= htmlspecialchars($currencySymbol) ?>)</label>
                        <input type="number" id="fire-annual-income" value="<?= $annualIncome ?>" min="0" step="1000">
                        <small>From your recurring income</small>
                    </div>
                    <div class="form-group">
                        <label for="fire-annual-expenses">
                            Annual Expenses (<?= htmlspecialchars($currencySymbol) ?>)
                            <span class="fire-toggle-monthly" id="expenses-toggle" title="Toggle monthly/annual view">
                                <i class="fa-solid fa-arrows-rotate"></i>
                            </span>
                        </label>
                        <input type="number" id="fire-annual-expenses" value="<?= $annualExpenses ?>" min="0" step="500">
                        <small id="expenses-hint" class="fire-hint"></small>
                        <small>From your subscriptions + recurring expenses</small>
                    </div>
                    <div class="form-group">
                        <label for="fire-expected-return">Expected Return (%/year)</label>
                        <input type="number" id="fire-expected-return" value="<?= $defaultReturn ?>" min="0" max="20" step="0.1">
                        <small>Historical S&amp;P 500 average: ~10%, after inflation: ~7%</small>
                    </div>
                    <div class="form-group">
                        <label for="fire-inflation-rate">Inflation Rate (%/year)</label>
                        <input type="number" id="fire-inflation-rate" value="<?= $defaultInflation ?>" min="0" max="10" step="0.1">
                        <small>Average historical: ~2-3%</small>
                    </div>
                    <div class="form-group">
                        <label for="fire-withdrawal-rate">Safe Withdrawal Rate (%)</label>
                        <input type="number" id="fire-withdrawal-rate" value="4.0" min="2" max="6" step="0.1">
                        <small>The 4% rule &mdash; withdraw 4% of portfolio yearly (Trinity Study)</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="fire-results">
            <!-- Key Metrics -->
            <div class="statistics" id="fire-stats">
                <div class="statistic">
                    <span id="fire-number">-</span>
                    <div class="title">FIRE Number</div>
                    <div class="subtitle">Target portfolio value</div>
                </div>
                <div class="statistic">
                    <span id="fire-years">-</span>
                    <div class="title">Years to FIRE</div>
                    <div class="subtitle" id="fire-age-subtitle"></div>
                </div>
                <div class="statistic">
                    <span id="fire-savings-rate">-</span>
                    <div class="title">Savings Rate</div>
                    <div class="subtitle" id="fire-monthly-subtitle"></div>
                </div>
                <div class="statistic">
                    <span id="fire-coast-number">-</span>
                    <div class="title">Coast FIRE Number</div>
                    <div class="subtitle">Amount needed now to coast</div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="fire-card" id="fire-progress-card">
                <h3><i class="fa-solid fa-battery-half"></i> Progress to FIRE</h3>
                <div class="fire-progress-container">
                    <div class="fire-progress-bar-wrapper">
                        <div class="fire-progress-bar" id="fire-progress-bar">
                            <span id="fire-progress-text">0%</span>
                        </div>
                    </div>
                    <div class="fire-progress-labels">
                        <span id="fire-progress-current"></span>
                        <span id="fire-progress-target"></span>
                    </div>
                </div>
            </div>

            <!-- Projection Chart -->
            <div class="fire-card">
                <h3><i class="fa-solid fa-chart-area"></i> Portfolio Projection</h3>
                <div class="chart-container" style="margin-top: 1rem;">
                    <canvas id="fireProjectionChart" height="350"></canvas>
                </div>
            </div>

            <!-- Understanding Results -->
            <div class="fire-card" id="fire-explanation">
                <h3><i class="fa-solid fa-circle-info"></i> Understanding Your Results</h3>
                <div class="fire-explanation-content">
                    <p id="fire-explain-number"></p>
                    <p id="fire-explain-years"></p>
                    <p class="fire-note">
                        The chart shows your portfolio growth over time. The dashed line represents
                        inflation-adjusted values (purchasing power). The red line is your FIRE target.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="scripts/libs/chart.js"></script>
<script>
    window.currencyCode = "<?= htmlspecialchars($currencyCode) ?>";
    window.currencySymbol = "<?= htmlspecialchars($currencySymbol) ?>";
    window.fireSavingsContribution = <?= $annualContribution ?>;
    window.fireNetContribution = <?= $annualNetContribution ?>;
</script>
<script src="scripts/fire.js?<?= $version ?>"></script>
<?php require_once 'includes/footer.php'; ?>
