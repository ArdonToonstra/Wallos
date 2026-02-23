<?php

require_once 'includes/header.php';
require_once 'includes/getdbkeys.php';

// Get income entries
$sql = "SELECT i.*, c.name as category_name, cur.code as currency_code, cur.symbol as currency_symbol
        FROM income i
        LEFT JOIN categories c ON i.category_id = c.id
        LEFT JOIN currencies cur ON i.currency_id = cur.id
        WHERE i.user_id = :userId 
        ORDER BY i.inactive ASC, i.date DESC";
$stmt = $db->prepare($sql);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

$incomeEntries = [];
if ($result) {
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $incomeEntries[] = $row;
    }
}

// Calculate totals
$totalMonthlyIncome = 0;
$totalOneOffIncome = 0;
$activeCount = 0;

foreach ($incomeEntries as $entry) {
    if ($entry['inactive'] == 0) {
        $activeCount++;
        if ($entry['type'] === 'recurring') {
            $price = floatval($entry['amount']);
            switch ($entry['cycle']) {
                case 1: $totalMonthlyIncome += $price * (30 / $entry['frequency']); break;
                case 2: $totalMonthlyIncome += $price * (4.35 / $entry['frequency']); break;
                case 3: $totalMonthlyIncome += $price * (1 / $entry['frequency']); break;
                case 4: $totalMonthlyIncome += $price / (12 * $entry['frequency']); break;
            }
        } else {
            $totalOneOffIncome += floatval($entry['amount']);
        }
    }
}

$code = $currencies[$main_currency]['code'] ?? 'USD';
?>

<section class="contain">
    <div class="split-header">
        <h2>
            <i class="fa-solid fa-money-bill-trend-up"></i>
            <?= translate('income', $i18n) ?? 'Income' ?>
        </h2>
    </div>

    <div class="statistics">
        <div class="statistic">
            <span><?= $activeCount ?></span>
            <div class="title">Active Sources</div>
        </div>
        <div class="statistic">
            <span><?= CurrencyFormatter::format($totalMonthlyIncome, $code) ?></span>
            <div class="title">Monthly Income</div>
        </div>
        <div class="statistic">
            <span><?= CurrencyFormatter::format($totalMonthlyIncome * 12, $code) ?></span>
            <div class="title">Yearly Income</div>
        </div>
        <div class="statistic">
            <span><?= CurrencyFormatter::format($totalOneOffIncome, $code) ?></span>
            <div class="title">One-off Income</div>
        </div>
    </div>

    <header class="main-actions" id="main-actions">
        <button class="button" onClick="addIncome()">
            <i class="fa-solid fa-circle-plus"></i>
            Add Income
        </button>
    </header>

    <div class="subscriptions" id="income-list">
        <?php
        if (count($incomeEntries) === 0) {
            ?>
            <div class="empty-page">
                <img src="images/siteimages/empty.png" alt="No income yet" />
                <p>No income sources added yet</p>
                <button class="button" onClick="addIncome()">
                    <i class="fa-solid fa-circle-plus"></i>
                    Add your first income
                </button>
            </div>
            <?php
        } else {
            foreach ($incomeEntries as $entry) {
                $inactiveClass = $entry['inactive'] ? 'disabled' : '';
                $typeLabel = $entry['type'] === 'recurring' ? 'Recurring' : 'One-off';
                $cycleName = '';
                if ($entry['type'] === 'recurring') {
                    switch ($entry['cycle']) {
                        case 1: $cycleName = 'Daily'; break;
                        case 2: $cycleName = 'Weekly'; break;
                        case 3: $cycleName = 'Monthly'; break;
                        case 4: $cycleName = 'Yearly'; break;
                    }
                    if ($entry['frequency'] > 1) {
                        $cycleName = "Every " . $entry['frequency'] . " " . $cycleName;
                    }
                }
                ?>
                <div class="subscription <?= $inactiveClass ?>" data-id="<?= $entry['id'] ?>" onClick="editIncome(<?= $entry['id'] ?>)">
                    <div class="subscription-main-content">
                        <div class="subscription-icon">
                            <i class="fa-solid fa-money-bill-trend-up"></i>
                        </div>
                        <div class="subscription-info">
                            <div class="subscription-name"><?= htmlspecialchars($entry['name']) ?></div>
                            <div class="subscription-cycle">
                                <?= $typeLabel ?><?= $cycleName ? ' · ' . $cycleName : '' ?>
                                <?= $entry['category_name'] ? ' · ' . htmlspecialchars($entry['category_name']) : '' ?>
                            </div>
                        </div>
                        <div class="subscription-price">
                            <span class="price"><?= CurrencyFormatter::format($entry['amount'], $entry['currency_code']) ?></span>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
        $db->close();
        ?>
    </div>
</section>

<section class="subscription-form" id="income-form">
    <header>
        <h3 id="income-form-title">Add Income</h3>
        <span class="fa-solid fa-xmark close-form" onClick="closeIncomeForm()"></span>
    </header>
    <form action="endpoints/income/add.php" method="post" id="income-form-element">

        <div class="form-group">
            <label for="income-name">Source Name</label>
            <input type="text" id="income-name" name="name" autocomplete="off" placeholder="e.g. Salary, Freelance" required>
            <input type="hidden" id="income-id" name="id">
        </div>

        <div class="form-group-inline">
            <div class="split50">
                <label for="income-amount">Amount</label>
                <input type="number" step="0.01" id="income-amount" name="amount" autocomplete="off" placeholder="Amount" required>
            </div>
            <div class="split50">
                <label for="income-currency">Currency</label>
                <select id="income-currency" name="currency_id">
                    <?php foreach ($currencies as $currency) {
                        $selected = ($currency['id'] == $main_currency) ? 'selected' : '';
                        ?>
                        <option value="<?= $currency['id'] ?>" <?= $selected ?>><?= $currency['name'] ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="income-type">Type</label>
            <select id="income-type" name="type" onchange="toggleIncomeRecurring()">
                <option value="recurring" selected>Recurring</option>
                <option value="one-off">One-off</option>
            </select>
        </div>

        <div class="form-group" id="income-recurring-fields">
            <label for="income-cycle">Payment Every</label>
            <div class="inline">
                <select id="income-frequency" name="frequency">
                    <?php for ($i = 1; $i <= 12; $i++) { ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                    <?php } ?>
                </select>
                <select id="income-cycle" name="cycle">
                    <?php foreach ($cycles as $cycle) { ?>
                        <option value="<?= $cycle['id'] ?>" <?= $cycle['id'] == 3 ? "selected" : "" ?>>
                            <?= translate(strtolower($cycle['name']), $i18n) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <div class="inline">
                <div class="split50">
                    <label for="income-date">Date</label>
                    <div class="date-wrapper">
                        <input type="date" id="income-date" name="date" autocomplete="off" required>
                    </div>
                </div>
                <div class="split50" id="income-next-payment-group">
                    <label for="income-next-payment">Next Payment</label>
                    <div class="date-wrapper">
                        <input type="date" id="income-next-payment" name="next_payment" autocomplete="off">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="income-category">Category</label>
            <select id="income-category" name="category_id">
                <?php foreach ($categories as $category) { ?>
                    <option value="<?= $category['id'] ?>"><?= $category['name'] ?></option>
                <?php } ?>
            </select>
        </div>

        <div class="form-group">
            <label for="income-notes">Notes</label>
            <input type="text" id="income-notes" name="notes" autocomplete="off" placeholder="Notes">
        </div>

        <div class="form-group">
            <div class="inline grow">
                <input type="checkbox" id="income-inactive" name="inactive">
                <label for="income-inactive" class="grow">Inactive</label>
            </div>
        </div>

        <div class="buttons">
            <input type="button" value="Delete" class="warning-button left thin" id="delete-income" style="display: none">
            <input type="button" value="Cancel" class="secondary-button thin" onClick="closeIncomeForm()">
            <input type="submit" value="Save" class="thin" id="save-income-button">
        </div>
    </form>
</section>

<script src="scripts/income.js?<?= $version ?>"></script>
<?php require_once 'includes/footer.php'; ?>
