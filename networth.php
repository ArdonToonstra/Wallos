<?php

require_once 'includes/header.php';

// Get main currency code
$query = "SELECT c.code FROM currencies c INNER JOIN user u ON c.id = u.main_currency WHERE u.id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$code = $row['code'] ?? 'USD';

// Get net worth settings
$query = "SELECT * FROM networth_settings WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$nwSettings = $result->fetchArray(SQLITE3_ASSOC);

if (!$nwSettings) {
    $nwSettings = [
        'expected_return_rate' => 7.0,
        'inflation_rate' => 2.0,
        'salary_growth_rate' => 3.0,
        'projection_years' => 10
    ];
}

$db->close();
?>

<section class="contain">
    <div class="split-header">
        <h2>
            <i class="fa-solid fa-chart-line"></i>
            Net Worth
        </h2>
        <button class="button secondary-button" onClick="toggleSettings()">
            <i class="fa-solid fa-gear"></i>
            Projection Settings
        </button>
    </div>

    <!-- Current Financial Summary -->
    <div class="statistics" id="networth-stats">
        <div class="statistic">
            <span id="nw-monthly-income">-</span>
            <div class="title">Monthly Income</div>
        </div>
        <div class="statistic">
            <span id="nw-monthly-outflow">-</span>
            <div class="title">Monthly Outflow</div>
        </div>
        <div class="statistic">
            <span id="nw-monthly-net">-</span>
            <div class="title">Monthly Net</div>
        </div>
        <div class="statistic">
            <span id="nw-current-networth">-</span>
            <div class="title">Current Net Worth</div>
        </div>
    </div>

    <!-- Account Breakdown -->
    <div class="split-header" style="margin-top: 2rem;">
        <h2><i class="fa-solid fa-wallet"></i> Account Breakdown</h2>
    </div>
    <div class="subscriptions" id="nw-accounts-breakdown">
        <div class="loading-message">Loading...</div>
    </div>

    <!-- Net Worth Projection Chart -->
    <div class="split-header" style="margin-top: 2rem;">
        <h2><i class="fa-solid fa-chart-area"></i> Net Worth Projection</h2>
    </div>
    <div class="chart-container" style="margin-top: 1rem;">
        <canvas id="networthProjectionChart" height="400"></canvas>
    </div>

    <!-- Income vs Expenses Projection -->
    <div class="split-header" style="margin-top: 2rem;">
        <h2><i class="fa-solid fa-scale-balanced"></i> Income vs Expenses Over Time</h2>
    </div>
    <div class="chart-container" style="margin-top: 1rem;">
        <canvas id="incomeVsExpensesChart" height="300"></canvas>
    </div>

    <!-- Savings History -->
    <div class="split-header" style="margin-top: 2rem;">
        <h2><i class="fa-solid fa-clock-rotate-left"></i> Historical Net Worth</h2>
    </div>
    <div class="chart-container" style="margin-top: 1rem;">
        <canvas id="savingsHistoryChart" height="300"></canvas>
    </div>
</section>

<!-- Settings Panel -->
<section class="subscription-form" id="nw-settings-panel">
    <header>
        <h3>Projection Settings</h3>
        <span class="fa-solid fa-xmark close-form" onClick="closeSettings()"></span>
    </header>
    <form action="endpoints/networth/settings.php" method="post" id="nw-settings-form">

        <div class="form-group">
            <label for="nw-return-rate">Expected Investment Return (%/year)</label>
            <input type="number" step="0.1" id="nw-return-rate" name="expected_return_rate" 
                   value="<?= $nwSettings['expected_return_rate'] ?>" autocomplete="off" required>
            <small>Historical S&P 500 average: ~10%, after inflation: ~7%</small>
        </div>

        <div class="form-group">
            <label for="nw-inflation-rate">Inflation Rate (%/year)</label>
            <input type="number" step="0.1" id="nw-inflation-rate" name="inflation_rate" 
                   value="<?= $nwSettings['inflation_rate'] ?>" autocomplete="off" required>
            <small>Average historical: ~2-3%</small>
        </div>

        <div class="form-group">
            <label for="nw-salary-growth">Salary Growth Rate (%/year)</label>
            <input type="number" step="0.1" id="nw-salary-growth" name="salary_growth_rate" 
                   value="<?= $nwSettings['salary_growth_rate'] ?>" autocomplete="off" required>
            <small>Average annual raise: ~3-5%</small>
        </div>

        <div class="form-group">
            <label for="nw-projection-years">Projection Period (years)</label>
            <input type="number" step="1" min="1" max="50" id="nw-projection-years" name="projection_years" 
                   value="<?= $nwSettings['projection_years'] ?>" autocomplete="off" required>
        </div>

        <div class="buttons">
            <input type="button" value="Cancel" class="secondary-button thin" onClick="closeSettings()">
            <input type="submit" value="Save & Recalculate" class="thin">
        </div>
    </form>
</section>

<script src="scripts/libs/chart.js"></script>
<script>
    window.currencyCode = "<?= $code ?>";
</script>
<script src="scripts/networth.js?<?= $version ?>"></script>
<?php require_once 'includes/footer.php'; ?>
