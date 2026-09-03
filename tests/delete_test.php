<?php
require 'db.php';
$conn = get_db();
$conn->exec("DELETE FROM direct_sales WHERE customer_name IN ('Test', 'Test2') AND mobile_number='123'");
echo "Deleted test records.\n";
