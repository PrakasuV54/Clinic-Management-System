<?php
try {
    $db = new PDO('sqlite:' . __DIR__ . '/hospital_portal.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check and add columns
    $cols = [
        'payment_status' => "TEXT DEFAULT 'Pending'",
        'paid_amount' => "REAL DEFAULT 0.00",
        'balance_amount' => "REAL DEFAULT 0.00",
        'cash_amount' => "REAL DEFAULT 0.00",
        'gpay_amount' => "REAL DEFAULT 0.00"
    ];

    foreach ($cols as $col => $def) {
        try {
            $db->exec("ALTER TABLE agency_purchases ADD COLUMN $col $def");
            echo "Added $col\n";
        } catch (Exception $e) {
            echo "Skipped $col (might exist): " . $e->getMessage() . "\n";
        }
    }

    echo "Done.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
