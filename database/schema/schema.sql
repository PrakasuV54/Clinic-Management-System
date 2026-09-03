-- ═══════════════════════════════════════════════════════
-- Hospital Digital Portal - Complete Database Schema
-- SQLite compatible (used by app.py init_db)
-- ═══════════════════════════════════════════════════════

-- Users Table (Authentication & Roles)
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('receptionist','doctor','pharmacist','management')),
    doctor_type TEXT CHECK(doctor_type IN ('Gent','Lady', NULL)),
    display_name TEXT
);

-- Patients Table
CREATE TABLE IF NOT EXISTS patients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    age INTEGER NOT NULL,
    gender TEXT NOT NULL,
    phone TEXT NOT NULL,
    address TEXT,
    doctor_type TEXT NOT NULL CHECK(doctor_type IN ('Gent','Lady')),
    doctor_name TEXT NOT NULL,
    complaint TEXT,
    bp TEXT,
    temp TEXT,
    pulse TEXT,
    weight TEXT,
    height TEXT,
    spo2 INTEGER,
    token TEXT NOT NULL,
    status TEXT DEFAULT 'waiting' CHECK(status IN ('waiting','prescribed','completed')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Prescriptions Table (Billing, Payment, Treatment)
CREATE TABLE IF NOT EXISTS prescriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    doctor_name TEXT NOT NULL,
    doctor_type TEXT,
    consultation_fee REAL DEFAULT 0.00,
    diagnosis TEXT,
    prescription_text TEXT,
    medicines TEXT,                      -- JSON array of {name, qty, unit_price, amount, batch_id}
    total_amount REAL DEFAULT 0.00,      -- Total medicine selling amount
    cost_amount REAL DEFAULT 0.00,       -- Total medicine purchase cost
    scan_fee REAL DEFAULT 0.00,
    injection_cost REAL DEFAULT 0.00,
    injection_details TEXT,
    iv_cost REAL DEFAULT 0.00,
    iv_details TEXT,
    upt_cost REAL DEFAULT 0.00,
    upt_card BOOLEAN DEFAULT 0,
    cash_amount REAL DEFAULT 0.00,
    gpay_amount REAL DEFAULT 0.00,
    account_id INTEGER DEFAULT NULL,
    paid_amount REAL DEFAULT 0.00,
    balance_amount REAL DEFAULT 0.00,
    diagnosis_photo TEXT,
    prescription_photo TEXT,
    status TEXT DEFAULT 'pending' CHECK(status IN ('pending','dispensed')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);

-- Inventory Table (Advanced Batch Tracking)
CREATE TABLE IF NOT EXISTS inventory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_code TEXT,                      -- Barcode / Item Code
    name TEXT NOT NULL,                  -- Medicine Name
    category TEXT DEFAULT 'Tablet',      -- Tablet / Syrup / Injection / Drops / Others
    hsn_code TEXT,                       -- HSN Code
    batch_number TEXT NOT NULL,          -- Batch Number (IMPORTANT: same name can have multiple batches)
    mfg_date TEXT,                       -- Manufacture Date (DD-MM-YYYY)
    expiry_date TEXT,                    -- Expiry Date (DD-MM-YYYY)
    mrp REAL DEFAULT 0.00,              -- Maximum Retail Price
    purchase_price REAL DEFAULT 0.00,   -- Cost Price (Purchase)
    selling_price REAL DEFAULT 0.00,    -- Sale Price
    opening_stock INTEGER DEFAULT 0,    -- Initial stock when batch was added
    stock INTEGER DEFAULT 0,            -- Current available stock
    min_stock INTEGER DEFAULT 0,        -- Minimum stock alert threshold
    location TEXT,                       -- Storage location (optional)
    UNIQUE(name, batch_number)           -- Same medicine + different batch = separate rows
);

-- ═══════════════════════════════════════════════════════
-- Default Users (Seed Data)
-- ═══════════════════════════════════════════════════════
INSERT INTO users (username, password, role, doctor_type, display_name) VALUES
    ('receptionist', 'reception123', 'receptionist', NULL, 'Receptionist'),
    ('dr.rasith', 'doctor123', 'doctor', 'Gent', 'Dr. Mohamed Rasith'),
    ('dr.basheera', 'doctor123', 'doctor', 'Lady', 'Dr. Jannathul Basheera'),
    ('pharmacist', 'pharma123', 'pharmacist', NULL, 'Pharmacist'),
    ('management', 'admin123', 'management', NULL, 'Management Admin');

-- ═══════════════════════════════════════════════════════
-- Agency Inventory Module Tables
-- ═══════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS agency_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS agency_suppliers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    company_name TEXT,
    phone TEXT,
    whatsapp TEXT,
    email TEXT,
    address TEXT,
    gst_number TEXT,
    dl_number TEXT,
    payment_type TEXT,
    payment_status TEXT DEFAULT 'Not Paid',
    total_purchase REAL DEFAULT 0.00,
    paid_amount REAL DEFAULT 0.00,
    pending_balance REAL DEFAULT 0.00,
    cash_amount REAL DEFAULT 0.00,
    gpay_amount REAL DEFAULT 0.00,
    account_id INTEGER DEFAULT NULL,
    phonepe_amount REAL DEFAULT 0.00,
    bank_amount REAL DEFAULT 0.00,
    status TEXT DEFAULT 'Active',
    outstanding_balance REAL DEFAULT 0.00,
    city TEXT,
    state TEXT,
    pincode TEXT
);

CREATE TABLE IF NOT EXISTS agency_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_code TEXT,
    item_name TEXT NOT NULL,
    generic_name TEXT,
    brand_name TEXT,
    category TEXT,
    medicine_type TEXT,
    hsn_code TEXT,
    unit TEXT,
    batch_number TEXT NOT NULL,
    mfg_date TEXT,
    expiry_date TEXT,
    mrp REAL DEFAULT 0.00,
    purchase_price REAL DEFAULT 0.00,
    selling_price REAL DEFAULT 0.00,
    discount REAL DEFAULT 0.00,
    gst REAL DEFAULT 0.00,
    opening_stock INTEGER DEFAULT 0,
    stock INTEGER DEFAULT 0,
    min_stock INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(item_name, batch_number)
);

CREATE TABLE IF NOT EXISTS agency_purchases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_id INTEGER,
    invoice_number TEXT,
    purchase_date TEXT,
    payment_mode TEXT,
    credit_days INTEGER DEFAULT 0,
    due_date TEXT,
    transport_name TEXT,
    vehicle_number TEXT,
    lr_number TEXT,
    transport_date TEXT,
    doctor_name TEXT,
    clinic_name TEXT,
    doctor_reg_no TEXT,
    contact_number TEXT,
    address TEXT,
    sub_total REAL DEFAULT 0.00,
    discount_total REAL DEFAULT 0.00,
    cgst_total REAL DEFAULT 0.00,
    sgst_total REAL DEFAULT 0.00,
    gst_total REAL DEFAULT 0.00,
    grand_total REAL DEFAULT 0.00,
    image_path TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_status TEXT DEFAULT 'Not Paid',
    paid_amount REAL DEFAULT 0.00,
    balance_amount REAL DEFAULT 0.00,
    cash_amount REAL DEFAULT 0.00,
    gpay_amount REAL DEFAULT 0.00,
    account_id INTEGER DEFAULT NULL,
    phonepe_amount REAL DEFAULT 0.00,
    bank_amount REAL DEFAULT 0.00,
    payment_date TEXT,
    upi_reference TEXT,
    transaction_id TEXT,
    bank_name TEXT,
    due_amount REAL DEFAULT 0.00,
    outstanding_balance REAL DEFAULT 0.00,
    purchase_type TEXT,
    FOREIGN KEY (supplier_id) REFERENCES agency_suppliers(id)
);

CREATE TABLE IF NOT EXISTS agency_purchase_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_id INTEGER,
    item_id INTEGER,
    hsn_code TEXT,
    generic_name TEXT,
    category TEXT,
    batch_number TEXT,
    mfg_date TEXT,
    expiry_date TEXT,
    quantity INTEGER DEFAULT 0,
    free_qty INTEGER DEFAULT 0,
    unit TEXT,
    purchase_rate REAL DEFAULT 0.00,
    mrp REAL DEFAULT 0.00,
    discount REAL DEFAULT 0.00,
    taxable_amount REAL DEFAULT 0.00,
    cgst REAL DEFAULT 0.00,
    sgst REAL DEFAULT 0.00,
    gst REAL DEFAULT 0.00,
    total_amount REAL DEFAULT 0.00,
    FOREIGN KEY (purchase_id) REFERENCES agency_purchases(id),
    FOREIGN KEY (item_id) REFERENCES agency_items(id)
);

CREATE TABLE IF NOT EXISTS agency_stock_adjustments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER,
    quantity INTEGER, -- Positive or negative
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

