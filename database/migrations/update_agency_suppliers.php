<?php
try {
    $db = new PDO('sqlite:' . __DIR__ . '/hospital_portal.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $cols = [
        'total_purchase' => "REAL DEFAULT 0.00",
        'payment_status' => "TEXT DEFAULT 'Not Paid'",
        'paid_amount' => "REAL DEFAULT 0.00",
        'pending_balance' => "REAL DEFAULT 0.00",
        'cash_amount' => "REAL DEFAULT 0.00",
        'gpay_amount' => "REAL DEFAULT 0.00"
    ];

    foreach ($cols as $col => $def) {
        try {
            $db->exec("ALTER TABLE agency_suppliers ADD COLUMN $col $def");
            echo "Added $col to agency_suppliers\n";
        } catch (Exception $e) {
            echo "Skipped $col (might exist): " . $e->getMessage() . "\n";
        }
    }

    echo "Database update completed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
