<?php
require 'db.php';

$conn = get_db();

function addColumnIfNotExists($conn, $table, $column, $definition) {
    $stmt = $conn->prepare("PRAGMA table_info($table)");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    
    if (!in_array($column, $columns)) {
        try {
            $conn->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            echo "Added $column to $table.\n";
        } catch (Exception $e) {
            echo "Error adding $column to $table: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Column $column already exists in $table.\n";
    }
}

echo "Starting database schema update for Agency Inventory...\n";

// agency_suppliers
addColumnIfNotExists($conn, 'agency_suppliers', 'city', 'TEXT');
addColumnIfNotExists($conn, 'agency_suppliers', 'state', 'TEXT');
addColumnIfNotExists($conn, 'agency_suppliers', 'pincode', 'TEXT');
addColumnIfNotExists($conn, 'agency_suppliers', 'status', "TEXT DEFAULT 'Active'");
addColumnIfNotExists($conn, 'agency_suppliers', 'outstanding_balance', "REAL DEFAULT 0.00");

// agency_items
addColumnIfNotExists($conn, 'agency_items', 'manufacturer', 'TEXT');
addColumnIfNotExists($conn, 'agency_items', 'supplier_id', 'INTEGER');
addColumnIfNotExists($conn, 'agency_items', 'gst_percentage', 'REAL DEFAULT 0.00');
addColumnIfNotExists($conn, 'agency_items', 'reorder_level', 'INTEGER DEFAULT 0');
addColumnIfNotExists($conn, 'agency_items', 'rack_location', 'TEXT');
addColumnIfNotExists($conn, 'agency_items', 'barcode', 'TEXT');
addColumnIfNotExists($conn, 'agency_items', 'qr_code', 'TEXT');

// agency_purchases
addColumnIfNotExists($conn, 'agency_purchases', 'upi_reference', 'TEXT');
addColumnIfNotExists($conn, 'agency_purchases', 'transaction_id', 'TEXT');
addColumnIfNotExists($conn, 'agency_purchases', 'payment_date', 'TEXT');
addColumnIfNotExists($conn, 'agency_purchases', 'paid_amount', 'REAL DEFAULT 0.00');
addColumnIfNotExists($conn, 'agency_purchases', 'bank_name', 'TEXT');
addColumnIfNotExists($conn, 'agency_purchases', 'due_amount', 'REAL DEFAULT 0.00');
// due_date already exists in schema.sql but let's be safe
addColumnIfNotExists($conn, 'agency_purchases', 'due_date', 'TEXT');
addColumnIfNotExists($conn, 'agency_purchases', 'outstanding_balance', 'REAL DEFAULT 0.00');
addColumnIfNotExists($conn, 'agency_purchases', 'purchase_type', 'TEXT');

// agency_purchase_items
addColumnIfNotExists($conn, 'agency_purchase_items', 'discount_percentage', 'REAL DEFAULT 0.00');
addColumnIfNotExists($conn, 'agency_purchase_items', 'gst_percentage', 'REAL DEFAULT 0.00');
addColumnIfNotExists($conn, 'agency_purchase_items', 'tax_amount', 'REAL DEFAULT 0.00');

// Create new tables
$new_tables = [
    "CREATE TABLE IF NOT EXISTS agency_inventory_movements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        item_id INTEGER,
        date TEXT,
        user_id INTEGER,
        action TEXT,
        quantity_change INTEGER,
        reason TEXT,
        FOREIGN KEY (item_id) REFERENCES agency_items(id)
    )",
    "CREATE TABLE IF NOT EXISTS agency_purchase_returns (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        supplier_id INTEGER,
        purchase_id INTEGER,
        return_date TEXT,
        total_amount REAL DEFAULT 0.00,
        reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS agency_return_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        return_id INTEGER,
        item_id INTEGER,
        quantity INTEGER,
        amount REAL DEFAULT 0.00
    )",
    "CREATE TABLE IF NOT EXISTS agency_audit_trail (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT,
        table_name TEXT,
        record_id INTEGER,
        old_value TEXT,
        new_value TEXT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($new_tables as $sql) {
    try {
        $conn->exec($sql);
        echo "Successfully executed table creation query.\n";
    } catch (Exception $e) {
        echo "Error creating table: " . $e->getMessage() . "\n";
    }
}

echo "Database schema update completed successfully.\n";
?>
