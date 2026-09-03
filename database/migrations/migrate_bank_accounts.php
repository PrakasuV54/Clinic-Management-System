<?php
require_once __DIR__ . '/db.php';

$db = get_db();

try {
    $db->beginTransaction();

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

    addColumnIfNotExists($db, 'upi_accounts', 'account_holder_name', 'TEXT DEFAULT NULL');
    addColumnIfNotExists($db, 'upi_accounts', 'ifsc_code', 'TEXT DEFAULT NULL');

    $db->commit();
    echo "Database migration completed successfully.\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
}
