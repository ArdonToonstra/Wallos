<?php

/**
 * Shared view for the Savings and Investments modules.
 * Include it from a page that sets $module to 'savings' or 'investments'.
 */

require_once 'includes/header.php';
require_once 'includes/getdbkeys.php';
require_once 'includes/savings_types.php';

$modules = [
    'savings' => [
        'title' => 'Savings',
        'icon' => 'fa-piggy-bank',
        'empty' => 'No savings accounts added yet',
        'add' => 'Add your first savings account',
        'name_placeholder' => 'e.g. ING Savings, Emergency Fund',
        'institution_placeholder' => 'e.g. ING, Rabobank',
        'types' => [
            'savings' => 'Savings Account',
            'checking' => 'Checking Account',
            'other' => 'Other',
        ],
    ],
    'investments' => [
        'title' => 'Investments',
        'icon' => 'fa-chart-line',
        'empty' => 'No investment accounts added yet',
        'add' => 'Add your first investment account',
        'name_placeholder' => 'e.g. Vanguard S&P500, DeGiro',
        'institution_placeholder' => 'e.g. Vanguard, DeGiro, Binance',
        'types' => [
            'investment' => 'Investment Account',
            'stocks' => 'Stocks',
            'crypto' => 'Cryptocurrency',
            'retirement' => 'Retirement Fund',
        ],
    ],
];

$config = $modules[$module];
$isInvestments = $module === 'investments';

// Investments = the fixed type list; savings = everything else, so no account
// can fall through the cracks when a type is renamed or added.
$typeFilter = $isInvestments ? 'IN' : 'NOT IN';
$placeholders = implode(',', array_fill(0, count(INVESTMENT_TYPES), '?'));

$sql = "SELECT sa.*, cur.code as currency_code, cur.symbol as currency_symbol,
        (SELECT ss.balance FROM savings_snapshots ss WHERE ss.account_id = sa.id ORDER BY ss.date DESC LIMIT 1) as latest_balance,
        (SELECT ss.date FROM savings_snapshots ss WHERE ss.account_id = sa.id ORDER BY ss.date DESC LIMIT 1) as latest_date
        FROM savings_accounts sa
        LEFT JOIN currencies cur ON sa.currency_id = cur.id
        WHERE sa.user_id = ? AND sa.type $typeFilter ($placeholders)
        ORDER BY sa.inactive ASC, sa.name ASC";
$stmt = $db->prepare($sql);
$stmt->bindValue(1, $userId, SQLITE3_INTEGER);
foreach (INVESTMENT_TYPES as $i => $type) {
    $stmt->bindValue($i + 2, $type, SQLITE3_TEXT);
}
$result = $stmt->execute();

$accounts = [];
if ($result) {
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $accounts[] = $row;
    }
}

$totalBalance = 0;
$totalMonthlyContributions = 0;
$activeCount = 0;

foreach ($accounts as $account) {
    if ($account['inactive'] == 0) {
        $activeCount++;
        $totalBalance += floatval($account['latest_balance'] ?? 0);
        $totalMonthlyContributions += floatval($account['monthly_contribution'] ?? 0);
    }
}

$code = $currencies[$main_currency]['code'] ?? 'USD';

// Labels for every type, so an account showing a legacy type still reads well.
$accountTypeLabels = [
    'savings' => 'Savings Account',
    'checking' => 'Checking Account',
    'investment' => 'Investment Account',
    'stocks' => 'Stocks',
    'crypto' => 'Cryptocurrency',
    'retirement' => 'Retirement Fund',
    'other' => 'Other',
];
?>

<section class="contain">
    <div class="split-header">
        <h2>
            <i class="fa-solid <?= $config['icon'] ?>"></i>
            <?= $config['title'] ?>
        </h2>
    </div>

    <div class="statistics">
        <div class="statistic">
            <span><?= $activeCount ?></span>
            <div class="title">Active Accounts</div>
        </div>
        <div class="statistic">
            <span><?= CurrencyFormatter::format($totalBalance, $code) ?></span>
            <div class="title">Total Balance</div>
        </div>
        <?php if ($totalMonthlyContributions > 0): ?>
        <div class="statistic">
            <span><?= CurrencyFormatter::format($totalMonthlyContributions, $code) ?></span>
            <div class="title">Monthly Contributions</div>
        </div>
        <?php endif; ?>
    </div>

    <header class="main-actions" id="main-actions">
        <button class="button" onClick="addAccount()">
            <i class="fa-solid fa-circle-plus"></i>
            Add Account
        </button>
        <?php if (count($accounts) > 0): ?>
        <button class="button secondary-button" onClick="addSnapshot()">
            <i class="fa-solid fa-camera"></i>
            Record Balance
        </button>
        <?php endif; ?>
    </header>

    <div class="subscriptions" id="accounts-list">
        <?php
        if (count($accounts) === 0) {
            ?>
            <div class="empty-page">
                <img src="images/siteimages/empty.png" alt="<?= $config['empty'] ?>" />
                <p><?= $config['empty'] ?></p>
                <button class="button" onClick="addAccount()">
                    <i class="fa-solid fa-circle-plus"></i>
                    <?= $config['add'] ?>
                </button>
            </div>
            <?php
        } else {
            foreach ($accounts as $account) {
                $inactiveClass = $account['inactive'] ? 'disabled' : '';
                $balance = floatval($account['latest_balance'] ?? 0);
                ?>
                <div class="subscription <?= $inactiveClass ?>" data-id="<?= $account['id'] ?>" onClick="editAccount(<?= $account['id'] ?>)">
                    <div class="subscription-main-content">
                        <div class="subscription-icon">
                            <i class="fa-solid <?= $config['icon'] ?>"></i>
                        </div>
                        <div class="subscription-info">
                            <div class="subscription-name"><?= htmlspecialchars($account['name']) ?></div>
                            <div class="subscription-cycle">
                                <?= $accountTypeLabels[$account['type']] ?? $account['type'] ?>
                                <?= $account['institution'] ? ' · ' . htmlspecialchars($account['institution']) : '' ?>
                                <?php if (floatval($account['monthly_contribution'] ?? 0) > 0): ?>
                                    · <?= CurrencyFormatter::format($account['monthly_contribution'], $account['currency_code'] ?? $code) ?>/mo
                                <?php endif; ?>
                                <?= $account['latest_date'] ? ' · Updated: ' . $account['latest_date'] : '' ?>
                            </div>
                        </div>
                        <div class="subscription-price">
                            <span class="price"><?= CurrencyFormatter::format($balance, $account['currency_code'] ?? $code) ?></span>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
        ?>
    </div>

    <!-- Balance History Section -->
    <?php if (count($accounts) > 0) { ?>
    <div class="split-header" style="margin-top: 2rem;">
        <h2>
            <i class="fa-solid fa-clock-rotate-left"></i>
            Balance History
        </h2>
    </div>
    <div class="chart-container" style="margin-top: 1rem;">
        <canvas id="savingsChart" height="300"></canvas>
    </div>

    <div id="snapshot-history-list" style="margin-top: 1.5rem;"></div>
    <?php } ?>
</section>

<!-- Add/Edit Account Form -->
<section class="subscription-form" id="account-form">
    <header>
        <h3 id="account-form-title">Add Account</h3>
        <span class="fa-solid fa-xmark close-form" onClick="closeAccountForm()"></span>
    </header>
    <form action="endpoints/savings/addaccount.php" method="post" id="account-form-element">

        <div class="form-group">
            <label for="account-name">Account Name</label>
            <input type="text" id="account-name" name="name" autocomplete="off" placeholder="<?= $config['name_placeholder'] ?>" required>
            <input type="hidden" id="account-id" name="id">
        </div>

        <div class="form-group">
            <label for="account-type">Account Type</label>
            <select id="account-type" name="type">
                <?php foreach ($config['types'] as $typeKey => $typeLabel) { ?>
                    <option value="<?= $typeKey ?>"><?= $typeLabel ?></option>
                <?php } ?>
            </select>
        </div>

        <div class="form-group">
            <label for="account-currency">Currency</label>
            <select id="account-currency" name="currency_id">
                <?php foreach ($currencies as $currency) {
                    $selected = ($currency['id'] == $main_currency) ? 'selected' : '';
                    ?>
                    <option value="<?= $currency['id'] ?>" <?= $selected ?>><?= $currency['name'] ?></option>
                <?php } ?>
            </select>
        </div>

        <div class="form-group">
            <label for="account-institution">Institution / Broker</label>
            <input type="text" id="account-institution" name="institution" autocomplete="off" placeholder="<?= $config['institution_placeholder'] ?>">
        </div>

        <div class="form-group">
            <label for="account-notes">Notes</label>
            <input type="text" id="account-notes" name="notes" autocomplete="off" placeholder="Notes">
        </div>

        <div class="form-group">
            <label for="account-monthly-contribution">Monthly Contribution (<?= $currencies[$main_currency]['symbol'] ?? '$' ?>)</label>
            <input type="number" step="0.01" min="0" id="account-monthly-contribution" name="monthly_contribution" autocomplete="off" placeholder="0.00" value="0">
            <small>Fixed monthly payment or transfer to this account</small>
        </div>

        <div class="form-group">
            <div class="inline grow">
                <input type="checkbox" id="account-inactive" name="inactive">
                <label for="account-inactive" class="grow">Inactive</label>
            </div>
        </div>

        <div class="buttons">
            <input type="button" value="Delete" class="warning-button left thin" id="delete-account" style="display: none">
            <input type="button" value="Cancel" class="secondary-button thin" onClick="closeAccountForm()">
            <input type="submit" value="Save" class="thin" id="save-account-button">
        </div>
    </form>
</section>

<!-- Add Balance Snapshot Form -->
<section class="subscription-form" id="snapshot-form">
    <header>
        <h3 id="snapshot-form-title">Record Balance</h3>
        <span class="fa-solid fa-xmark close-form" onClick="closeSnapshotForm()"></span>
    </header>
    <form action="endpoints/savings/addsnapshot.php" method="post" id="snapshot-form-element">

        <div class="form-group">
            <label for="snapshot-account">Account</label>
            <select id="snapshot-account" name="account_id" required onchange="onSnapshotAccountChange()">
                <?php foreach ($accounts as $account) { ?>
                    <option value="<?= $account['id'] ?>" data-type="<?= $account['type'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                <?php } ?>
            </select>
            <input type="hidden" id="snapshot-id" name="id">
        </div>

        <div class="form-group" id="snapshot-shares-group" style="display: none;">
            <label for="snapshot-shares">Number of Shares</label>
            <input type="number" step="0.000001" id="snapshot-shares" name="shares" autocomplete="off" placeholder="e.g. 10.5" oninput="recalcSnapshotBalance()">
        </div>

        <div class="form-group" id="snapshot-share-price-group" style="display: none;">
            <label for="snapshot-share-price">Price per Share</label>
            <input type="number" step="0.0001" id="snapshot-share-price" name="share_price" autocomplete="off" placeholder="e.g. 150.00" oninput="recalcSnapshotBalance()">
        </div>

        <div class="form-group">
            <label for="snapshot-balance" id="snapshot-balance-label">Current Balance</label>
            <input type="number" step="0.01" id="snapshot-balance" name="balance" autocomplete="off" placeholder="Balance" required>
        </div>

        <div class="form-group">
            <label for="snapshot-date">Date</label>
            <div class="date-wrapper">
                <input type="date" id="snapshot-date" name="date" autocomplete="off" required>
            </div>
        </div>

        <div class="buttons">
            <input type="button" value="Delete" class="warning-button left thin" id="delete-snapshot" style="display: none">
            <input type="button" value="Cancel" class="secondary-button thin" onClick="closeSnapshotForm()">
            <input type="submit" value="Save" class="thin">
        </div>
    </form>
</section>

<script src="scripts/libs/chart.js"></script>
<script src="scripts/savings.js?<?= $version ?>"></script>
<?php
$db->close();
require_once 'includes/footer.php';
