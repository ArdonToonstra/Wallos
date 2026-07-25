<?php

/*
* This migration adds a birthdate column to the user table
* to support automatic age calculation in the FIRE calculator.
*/

$colExists = $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('user') WHERE name='birthdate'");
if (!$colExists) {
    $db->exec("ALTER TABLE user ADD COLUMN birthdate TEXT DEFAULT ''");
}

?>
