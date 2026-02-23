<?php

require_once 'includes/header.php';
require_once 'includes/getdbkeys.php';

// Get expense entries
$sql = "SELECT e.*, c.name as category_name, cur.code as currency_code, cur.symbol as currency_symbol,
        pm.name as payment_method_name, pm.icon as payment_method_icon
        FROM expenses e
        LEFT JOIN categories c ON e.category_id = c.id
        LEFT JOIN currencies cur ON e.currency_id = cur.id
        LEFT JOIN payment_methods pm ON e.payment_method_id = pm.id
        WHERE e.user_id = :userId 
        ORDER BY e.inactive ASC, e.date DESC";
$stmt = $db->prepare($sql);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

$expenseEntries = [];
if ($result) {
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $expenseEntries[] = $row;
    }
}

// Calculate totals
$totalMonthlyExpenses = 0;
$totalOneOffExpenses = 0;
$activeCount = 0;

foreach ($expenseEntries as $entry) {
    if ($entry['inactive'] == 0) {
        $activeCount++;
        if ($entry['type'] === 'recurring') {
            $price = floatval($entry['amount']);
            switch ($entry['cycle']) {
                case 1: $totalMonthlyExpenses += $price * (30 / $entry['frequency']); break;
                case 2: $totalMonthlyExpenses += $price * (4.35 / $entry['frequency']); break;
                case 3: $totalMonthlyExpenses += $price * (1 / $entry['frequency']); break;
                case 4: $totalMonthlyExpenses += $price / (12 * $entry['frequency']); break;
            }
        } else {
            $totalOneOffExpenses += floatval($entry['amount']);
        }
    }
}

$code = $currencies[$main_currency]['code'] ?? 'USD';
?>

<section class="contain">
    <div class="split-header">
        <h2>
            <i class="fa-solid fa-receipt"></i>
            Expenses
        </h2>
    </div>

    <div class="statistics">
        <div class="statistic">
            <span><?= $activeCount ?></span>
            <div class="title">Active Expenses</div>
        </div>
        <div class="statistic">
            <span><?= CurrencyFormatter::format($totalMonthlyExpenses, $code) ?></span>
            <div class="title">Monthly Expenses</div>
        </div>
        <div class="statistic">
            <span><?= CurrencyFormatter::format($totalMonthlyExpenses * 12, $code) ?></span>
            <div class="title">Yearly Expenses</div>
        </div>
        <div class="statistic">
            <span><?= CurrencyFormatter::format($totalOneOffExpenses, $code) ?></span>
            <div class="title">One-off Expenses</div>
        </div>
    </div>

    <header class="main-actions" id="main-actions">
        <button class="button" onClick="addExpense()">
            <i class="fa-solid fa-circle-plus"></i>
            Add Expense
        </button>
    </header>

    <div class="subscriptions" id="expenses-list">
        <?php
        if (count($expenseEntries) === 0) {
            ?>
            <div class="empty-page">
                <img src="images/siteimages/empty.png" alt="No expenses yet" />
                <p>No expenses added yet</p>
                <button class="button" onClick="addExpense()">
                    <i class="fa-solid fa-circle-plus"></i>
                    Add your first expense
                </button>
            </div>
            <?php
        } else {
            foreach ($expenseEntries as $entry) {
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
                <div class="subscription <?= $inactiveClass ?>" data-id="<?= $entry['id'] ?>" onClick="editExpense(<?= $entry['id'] ?>)">
                    <div class="subscription-main-content">
                        <div class="subscription-icon">
                            <i class="fa-solid fa-receipt"></i>
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

<section class="subscription-form" id="expense-form">
    <header>
        <h3 id="expense-form-title">Add Expense</h3>
        <span class="fa-solid fa-xmark close-form" onClick="closeExpenseForm()"></span>
    </header>
    <form action="endpoints/expenses/add.php" method="post" id="expense-form-element">

        <div class="form-group">
            <label for="expense-name">Expense Name</label>
            <input type="text" id="expense-name" name="name" autocomplete="off" placeholder="e.g. Groceries, Rent" required>
            <input type="hidden" id="expense-id" name="id">
        </div>

        <div class="form-group-inline">
            <div class="split50">
                <label for="expense-amount">Amount</label>
                <input type="number" step="0.01" id="expense-amount" name="amount" autocomplete="off" placeholder="Amount" required>
            </div>
            <div class="split50">
                <label for="expense-currency">Currency</label>
                <select id="expense-currency" name="currency_id">
                    <?php foreach ($currencies as $currency) {
                        $selected = ($currency['id'] == $main_currency) ? 'selected' : '';
                        ?>
                        <option value="<?= $currency['id'] ?>" <?= $selected ?>><?= $currency['name'] ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="expense-type">Type</label>
            <select id="expense-type" name="type" onchange="toggleExpenseRecurring()">
                <option value="recurring" selected>Recurring</option>
                <option value="one-off">One-off</option>
            </select>
        </div>

        <div class="form-group" id="expense-recurring-fields">
            <label for="expense-cycle">Payment Every</label>
            <div class="inline">
                <select id="expense-frequency" name="frequency">
                    <?php for ($i = 1; $i <= 12; $i++) { ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                    <?php } ?>
                </select>
                <select id="expense-cycle" name="cycle">
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
                    <label for="expense-date">Date</label>
                    <div class="date-wrapper">
                        <input type="date" id="expense-date" name="date" autocomplete="off" required>
                    </div>
                </div>
                <div class="split50" id="expense-next-payment-group">
                    <label for="expense-next-payment">Next Payment</label>
                    <div class="date-wrapper">
                        <input type="date" id="expense-next-payment" name="next_payment" autocomplete="off">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="inline">
                <div class="split50">
                    <label for="expense-payment-method">Payment Method</label>
                    <select id="expense-payment-method" name="payment_method_id">
                        <?php foreach ($payment_methods as $payment) { ?>
                            <option value="<?= $payment['id'] ?>"><?= $payment['name'] ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="split50">
                    <label for="expense-payer">Paid By</label>
                    <select id="expense-payer" name="payer_user_id">
                        <?php foreach ($members as $member) { ?>
                            <option value="<?= $member['id'] ?>"><?= $member['name'] ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="expense-category">Category</label>
            <select id="expense-category" name="category_id">
                <?php foreach ($categories as $category) { ?>
                    <option value="<?= $category['id'] ?>"><?= $category['name'] ?></option>
                <?php } ?>
            </select>
        </div>

        <div class="form-group">
            <label for="expense-notes">Notes</label>
            <input type="text" id="expense-notes" name="notes" autocomplete="off" placeholder="Notes">
        </div>

        <div class="form-group">
            <div class="inline grow">
                <input type="checkbox" id="expense-inactive" name="inactive">
                <label for="expense-inactive" class="grow">Inactive</label>
            </div>
        </div>

        <div class="buttons">
            <input type="button" value="Delete" class="warning-button left thin" id="delete-expense" style="display: none">
            <input type="button" value="Cancel" class="secondary-button thin" onClick="closeExpenseForm()">
            <input type="submit" value="Save" class="thin" id="save-expense-button">
        </div>
    </form>
</section>

<script src="scripts/expenses.js?<?= $version ?>"></script>
<?php require_once 'includes/footer.php'; ?>
