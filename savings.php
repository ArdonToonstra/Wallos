<?php

require_once 'includes/header.php';
require_once 'includes/getdbkeys.php';

// Get savings accounts with latest balances
$sql = "SELECT sa.*, cur.code as currency_code, cur.symbol as currency_symbol,
        (SELECT ss.balance FROM savings_snapshots ss WHERE ss.account_id = sa.id ORDER BY ss.date DESC LIMIT 1) as latest_balance,
        (SELECT ss.date FROM savings_snapshots ss WHERE ss.account_id = sa.id ORDER BY ss.date DESC LIMIT 1) as latest_date
        FROM savings_accounts sa
        LEFT JOIN currencies cur ON sa.currency_id = cur.id
        WHERE sa.user_id = :userId 
        ORDER BY sa.inactive ASC, sa.name ASC";
$stmt = $db->prepare($sql);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

$accounts = [];
if ($result) {
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $accounts[] = $row;
    }
}

// Calculate totals
$totalSavings = 0;
$totalInvestments = 0;
$activeCount = 0;

foreach ($accounts as $account) {
    if ($account['inactive'] == 0) {
        $activeCount++;
        $balance = floatval($account['latest_balance'] ?? 0);
        if (in_array($account['type'], ['investment', 'stocks', 'crypto', 'retirement'])) {
            $totalInvestments += $balance;
        } else {
            $totalSavings += $balance;
        }
    }
}

$code = $currencies[$main_currency]['code'] ?? 'USD';

$accountTypes = [
    'savings' => 'Savings Account',
    'checking' => 'Checking Account',
    'investment' => 'Investment Account',
    'stocks' => 'Stocks',
    'crypto' => 'Cryptocurrency',
    'retirement' => 'Retirement Fund',
    'other' => 'Other'
];
?>

<section class="contain">
    <div class="split-header">
        <h2>
            <i class="fa-solid fa-piggy-bank"></i>
            Savings & Investments
        </h2>
    </div>

    <div class="statistics">
        <div class="statistic">
            <span><?= $activeCount ?></span>
            <div class="title">Active Accounts</div>
        </div>
        <div class="statistic">
            <span><?= CurrencyFormatter::format($totalSavings, $code) ?></span>
            <div class="title">Total Savings</div>
        </div>
        <div class="statistic">
            <span><?= CurrencyFormatter::format($totalInvestments, $code) ?></span>
            <div class="title">Total Investments</div>
        </div>
        <div class="statistic">
            <span><?= CurrencyFormatter::format($totalSavings + $totalInvestments, $code) ?></span>
            <div class="title">Total Balance</div>
        </div>
    </div>

    <header class="main-actions" id="main-actions">
        <button class="button" onClick="addAccount()">
            <i class="fa-solid fa-circle-plus"></i>
            Add Account
        </button>
        <button class="button secondary-button" onClick="addSnapshot()">
            <i class="fa-solid fa-camera"></i>
            Record Balance
        </button>
    </header>

    <div class="subscriptions" id="accounts-list">
        <?php
        if (count($accounts) === 0) {
            ?>
            <div class="empty-page">
                <img src="images/siteimages/empty.png" alt="No accounts yet" />
                <p>No savings or investment accounts added yet</p>
                <button class="button" onClick="addAccount()">
                    <i class="fa-solid fa-circle-plus"></i>
                    Add your first account
                </button>
            </div>
            <?php
        } else {
            foreach ($accounts as $account) {
                $inactiveClass = $account['inactive'] ? 'disabled' : '';
                $balance = floatval($account['latest_balance'] ?? 0);
                $typeIcon = in_array($account['type'], ['investment', 'stocks', 'crypto', 'retirement']) 
                    ? 'fa-chart-line' : 'fa-piggy-bank';
                ?>
                <div class="subscription <?= $inactiveClass ?>" data-id="<?= $account['id'] ?>" onClick="editAccount(<?= $account['id'] ?>)">
                    <div class="subscription-main-content">
                        <div class="subscription-icon">
                            <i class="fa-solid <?= $typeIcon ?>"></i>
                        </div>
                        <div class="subscription-info">
                            <div class="subscription-name"><?= htmlspecialchars($account['name']) ?></div>
                            <div class="subscription-cycle">
                                <?= $accountTypes[$account['type']] ?? $account['type'] ?>
                                <?= $account['institution'] ? ' · ' . htmlspecialchars($account['institution']) : '' ?>
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
            <input type="text" id="account-name" name="name" autocomplete="off" placeholder="e.g. ING Savings, Vanguard S&P500" required>
            <input type="hidden" id="account-id" name="id">
        </div>

        <div class="form-group">
            <label for="account-type">Account Type</label>
            <select id="account-type" name="type">
                <?php foreach ($accountTypes as $typeKey => $typeLabel) { ?>
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
            <input type="text" id="account-institution" name="institution" autocomplete="off" placeholder="e.g. ING, Vanguard, Binance">
        </div>

        <div class="form-group">
            <label for="account-notes">Notes</label>
            <input type="text" id="account-notes" name="notes" autocomplete="off" placeholder="Notes">
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
?>
