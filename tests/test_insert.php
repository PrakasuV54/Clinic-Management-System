<?php
require 'db.php';
$conn = get_db();
$conn->exec("INSERT INTO direct_sales (customer_name, mobile_number, total_amount, paid_amount, gpay_amount, status, upi_account) VALUES ('Test', '123', 500, 500, 500, 'completed', 'PHARMA')");
$conn->exec("INSERT INTO direct_sales (customer_name, mobile_number, total_amount, paid_amount, gpay_amount, status, upi_account) VALUES ('Test2', '123', 300, 300, 300, 'completed', NULL)");
echo "Inserted test records.\n";
