<?php
require_once __DIR__ . '/db.php';

$db = get_db();

try {
    $db->beginTransaction();

    // 1. Create upi_accounts table
    $db->exec("CREATE TABLE IF NOT EXISTS upi_accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_name TEXT NOT NULL,
        short_name TEXT UNIQUE NOT NULL,
        bank_name TEXT,
        account_number TEXT,
        upi_id TEXT,
        notes TEXT,
        is_active INTEGER DEFAULT 1
    )");

    // Helper to check if column exists
    function addColumnIfNotExists($db, $table, $column, $definition) {
        $stmt = $db->query("PRAGMA table_info($table)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $exists = false;
        foreach ($columns as $col) {
            if ($col['name'] === $column) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $db->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            echo "Added $column to $table\n";
        } else {
            echo "Column $column already exists in $table\n";
        }
    }

    // 2. Add upi_account column to transaction tables
    addColumnIfNotExists($db, 'prescriptions', 'upi_account', 'TEXT DEFAULT NULL');
    addColumnIfNotExists($db, 'direct_sales', 'upi_account', 'TEXT DEFAULT NULL');
    addColumnIfNotExists($db, 'agency_purchases', 'upi_account', 'TEXT DEFAULT NULL');
    addColumnIfNotExists($db, 'agency_suppliers', 'upi_account', 'TEXT DEFAULT NULL');

    $db->commit();
    echo "Database migration completed successfully.\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
}
