<?php
try {
    $db = new PDO('sqlite:' . __DIR__ . '/hospital_portal.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("UPDATE agency_purchases SET balance_amount = grand_total WHERE payment_status = 'Pending' OR payment_status IS NULL OR payment_status = 'Not Paid'");
    echo "Updated existing balances\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
