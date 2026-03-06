<?php

/*
* This migration adds monthly_contribution column to savings_accounts
* to record fixed monthly payments/transfers to savings or investment accounts.
*/

$colExists = $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('savings_accounts') WHERE name='monthly_contribution'");
if (!$colExists) {
    $db->exec("ALTER TABLE savings_accounts ADD COLUMN monthly_contribution REAL DEFAULT 0");
}

?>
