<?php

/*
* This migration adds tables for Income, Expenses, Savings, and Net Worth modules
*/

// ===================== INCOME TABLE =====================
$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='income'");
$tableExists = $tableQuery->fetchArray(SQLITE3_ASSOC);

if (!$tableExists) {
    $db->exec('CREATE TABLE income (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        name TEXT NOT NULL,
        amount REAL NOT NULL,
        currency_id INTEGER,
        type TEXT DEFAULT "recurring",
        cycle INTEGER DEFAULT 3,
        frequency INTEGER DEFAULT 1,
        category_id INTEGER,
        date DATE,
        next_payment DATE,
        notes TEXT DEFAULT "",
        inactive INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES user(id),
        FOREIGN KEY (currency_id) REFERENCES currencies(id),
        FOREIGN KEY (category_id) REFERENCES categories(id)
    )');
}

// ===================== EXPENSES TABLE =====================
$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='expenses'");
$tableExists = $tableQuery->fetchArray(SQLITE3_ASSOC);

if (!$tableExists) {
    $db->exec('CREATE TABLE expenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        name TEXT NOT NULL,
        amount REAL NOT NULL,
        currency_id INTEGER,
        type TEXT DEFAULT "recurring",
        cycle INTEGER DEFAULT 3,
        frequency INTEGER DEFAULT 1,
        category_id INTEGER,
        payment_method_id INTEGER,
        payer_user_id INTEGER,
        date DATE,
        next_payment DATE,
        notes TEXT DEFAULT "",
        inactive INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES user(id),
        FOREIGN KEY (currency_id) REFERENCES currencies(id),
        FOREIGN KEY (category_id) REFERENCES categories(id),
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id),
        FOREIGN KEY (payer_user_id) REFERENCES household(id)
    )');
}

// ===================== SAVINGS ACCOUNTS TABLE =====================
$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='savings_accounts'");
$tableExists = $tableQuery->fetchArray(SQLITE3_ASSOC);

if (!$tableExists) {
    $db->exec('CREATE TABLE savings_accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        name TEXT NOT NULL,
        type TEXT DEFAULT "savings",
        currency_id INTEGER,
        institution TEXT DEFAULT "",
        notes TEXT DEFAULT "",
        inactive INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES user(id),
        FOREIGN KEY (currency_id) REFERENCES currencies(id)
    )');
}

// ===================== SAVINGS SNAPSHOTS TABLE =====================
$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='savings_snapshots'");
$tableExists = $tableQuery->fetchArray(SQLITE3_ASSOC);

if (!$tableExists) {
    $db->exec('CREATE TABLE savings_snapshots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER,
        user_id INTEGER,
        balance REAL NOT NULL,
        date DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (account_id) REFERENCES savings_accounts(id),
        FOREIGN KEY (user_id) REFERENCES user(id)
    )');
}

// ===================== NET WORTH SETTINGS TABLE =====================
$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='networth_settings'");
$tableExists = $tableQuery->fetchArray(SQLITE3_ASSOC);

if (!$tableExists) {
    $db->exec('CREATE TABLE networth_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER UNIQUE,
        expected_return_rate REAL DEFAULT 7.0,
        inflation_rate REAL DEFAULT 2.0,
        salary_growth_rate REAL DEFAULT 3.0,
        projection_years INTEGER DEFAULT 10,
        FOREIGN KEY (user_id) REFERENCES user(id)
    )');
}

// Add new default categories for Income and Expenses
$usersQuery = $db->query("SELECT id FROM user");
if ($usersQuery) {
    while ($user = $usersQuery->fetchArray(SQLITE3_ASSOC)) {
        $uid = $user['id'];

        $newCategories = ['Salary', 'Freelance', 'Groceries', 'Dining Out', 'Rent', 'Savings'];
        foreach ($newCategories as $index => $catName) {
            $exists = $db->querySingle("SELECT COUNT(*) FROM categories WHERE name='$catName' AND user_id=$uid");
            if (!$exists) {
                $order = 20 + $index;
                $stmt = $db->prepare('INSERT INTO categories (name, "order", user_id) VALUES (:name, :order, :userId)');
                $stmt->bindParam(':name', $catName, SQLITE3_TEXT);
                $stmt->bindParam(':order', $order, SQLITE3_INTEGER);
                $stmt->bindParam(':userId', $uid, SQLITE3_INTEGER);
                $stmt->execute();
            }
        }
    }
}

?>
