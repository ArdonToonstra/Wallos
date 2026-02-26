<?php

/*
* This migration adds shares and share_price columns to savings_snapshots
* to support recording stock position details when capturing a balance snapshot.
*/

$sharesExists = $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('savings_snapshots') WHERE name='shares'");
if (!$sharesExists) {
    $db->exec("ALTER TABLE savings_snapshots ADD COLUMN shares REAL DEFAULT NULL");
}

$sharePriceExists = $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('savings_snapshots') WHERE name='share_price'");
if (!$sharePriceExists) {
    $db->exec("ALTER TABLE savings_snapshots ADD COLUMN share_price REAL DEFAULT NULL");
}

?>
