<?php
require 'db.php';
$conn = get_db();
$conn->exec("UPDATE upi_accounts SET short_name = account_name WHERE short_name = '' OR short_name IS NULL");
echo "Fixed empty short_names.\n";
?>
