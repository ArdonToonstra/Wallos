<?php

/* 
* This migration adds a share_percentage column to subscriptions and inserts new default categories
*/

$columnExists = $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('subscriptions') WHERE name='share_percentage'");
if (!$columnExists) {
    $db->exec("ALTER TABLE subscriptions ADD COLUMN share_percentage INTEGER DEFAULT 100");
}

$usersQuery = $db->query("SELECT id FROM user");
if ($usersQuery) {
    while ($user = $usersQuery->fetchArray(SQLITE3_ASSOC)) {
        $userId = $user['id'];
        
        $investingExists = $db->querySingle("SELECT COUNT(*) FROM categories WHERE name='Investing' AND user_id=$userId");
        if (!$investingExists) {
            $stmt = $db->prepare('INSERT INTO categories (name, "order", user_id) VALUES ("Investing", 18, :userId)');
            $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
            $stmt->execute();
        }

        $housingExists = $db->querySingle("SELECT COUNT(*) FROM categories WHERE name='Housing' AND user_id=$userId");
        if (!$housingExists) {
            $stmt = $db->prepare('INSERT INTO categories (name, "order", user_id) VALUES ("Housing", 19, :userId)');
            $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
            $stmt->execute();
        }
    }
}

?>
