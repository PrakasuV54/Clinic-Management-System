<?php
$db_file = __DIR__ . '/hospital_portal.db';

$sql = "
CREATE TABLE IF NOT EXISTS agency_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS agency_suppliers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    company_name TEXT,
    phone TEXT,
    email TEXT,
    address TEXT,
    gst_number TEXT
);

CREATE TABLE IF NOT EXISTS agency_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_code TEXT,
    item_name TEXT NOT NULL,
    generic_name TEXT,
    brand_name TEXT,
    category TEXT,
    medicine_type TEXT,
    unit TEXT,
    batch_number TEXT NOT NULL,
    purchase_price REAL DEFAULT 0.00,
    selling_price REAL DEFAULT 0.00,
    gst REAL DEFAULT 0.00,
    opening_stock INTEGER DEFAULT 0,
    stock INTEGER DEFAULT 0,
    min_stock INTEGER DEFAULT 0,
    expiry_date TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(item_name, batch_number)
);

CREATE TABLE IF NOT EXISTS agency_purchases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_id INTEGER,
    invoice_number TEXT,
    purchase_date TEXT,
    sub_total REAL DEFAULT 0.00,
    gst_total REAL DEFAULT 0.00,
    grand_total REAL DEFAULT 0.00,
    image_path TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES agency_suppliers(id)
);

CREATE TABLE IF NOT EXISTS agency_purchase_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_id INTEGER,
    item_id INTEGER,
    quantity INTEGER DEFAULT 0,
    unit TEXT,
    purchase_rate REAL DEFAULT 0.00,
    gst REAL DEFAULT 0.00,
    total_amount REAL DEFAULT 0.00,
    FOREIGN KEY (purchase_id) REFERENCES agency_purchases(id),
    FOREIGN KEY (item_id) REFERENCES agency_items(id)
);

CREATE TABLE IF NOT EXISTS agency_stock_adjustments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER,
    quantity INTEGER,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES agency_items(id)
);

CREATE TABLE IF NOT EXISTS agency_stock_transfers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER,
    quantity INTEGER,
    to_location TEXT,
    transfer_date TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES agency_items(id)
);
";

try {
    $conn = new PDO("sqlite:" . $db_file);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec($sql);
    echo "Agency tables created successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
