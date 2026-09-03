<?php
/**
 * Database Configuration and Helpers (PHP/PDO Version)
 */

require_once dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

function get_db()
{
    try {
        $conn = new PDO('sqlite:' . DB_PATH);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // WAL mode and Foreign Keys as in Python
        $conn->exec("PRAGMA journal_mode=WAL");
        $conn->exec("PRAGMA foreign_keys=ON");

        return $conn;
    } catch (PDOException $e) {
        die("DB Connection Error: " . $e->getMessage());
    }
}

/**
 * Initialize Database - Ported from app.py init_db()
 */
function init_db()
{
    $conn = get_db();
    $conn->exec("PRAGMA foreign_keys=OFF");

    // Create Tables
    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL,
        doctor_type TEXT,
        display_name TEXT,
        specialization TEXT,
        details TEXT,
        photo_path TEXT,
        token_prefix TEXT,
        is_active INTEGER DEFAULT 1
    )");

    // Migration: Check if specialization column exists, if not, do table rebuild
    try {
        $stmt_users = $conn->query("SELECT specialization, details, photo_path FROM users LIMIT 1");
        $stmt_users->closeCursor();
        $stmt_users = null;
    } catch (Exception $e) {
        $conn->exec("CREATE TABLE users_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL,
            doctor_type TEXT,
            display_name TEXT,
            specialization TEXT,
            details TEXT,
            photo_path TEXT
        )");
        $conn->exec("INSERT INTO users_new (id, username, password, role, doctor_type, display_name) SELECT id, username, password, role, doctor_type, display_name FROM users");
        $conn->exec("DROP TABLE users");
        $conn->exec("ALTER TABLE users_new RENAME TO users");
    }

    // Migration: Check if token_prefix column exists
    $stmt = $conn->query("PRAGMA table_info(users)");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('token_prefix', $cols)) {
        $conn->exec("ALTER TABLE users ADD COLUMN token_prefix TEXT");
        $conn->exec("UPDATE users SET token_prefix = substr(doctor_type, 1, 1) WHERE role='doctor' AND doctor_type IS NOT NULL");
    }

    // Migration: Check if is_active column exists
    $stmt = $conn->query("PRAGMA table_info(users)");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('is_active', $cols)) {
        $conn->exec("ALTER TABLE users ADD COLUMN is_active INTEGER DEFAULT 1");
    }

    $conn->exec("CREATE TABLE IF NOT EXISTS patients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        age INTEGER NOT NULL,
        gender TEXT NOT NULL,
        phone TEXT NOT NULL,
        address TEXT,
        doctor_id INTEGER,
        doctor_type TEXT NOT NULL,
        doctor_name TEXT NOT NULL,
        complaint TEXT,
        bp TEXT,
        temp TEXT,
        pulse TEXT,
        weight TEXT,
        height TEXT,
        token TEXT NOT NULL,
        status TEXT DEFAULT 'waiting' CHECK(status IN ('waiting','prescribed','completed')),
        created_at TIMESTAMP DEFAULT (datetime('now', 'localtime')),
        completed_at TIMESTAMP,
        spo2 INTEGER,
        patient_id TEXT
    )");

    // Migration: Remove check constraint on doctor_type in patients table
    $stmt = $conn->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='patients'");
    $patient_sql = $stmt->fetchColumn();
    $stmt->closeCursor();
    $stmt = null;

    if ($patient_sql && strpos($patient_sql, "CHECK(doctor_type IN ('Gent','Lady'))") !== false) {
        $conn->exec("DROP TABLE IF EXISTS patients_new");
        $conn->exec("CREATE TABLE patients_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            age INTEGER NOT NULL,
            gender TEXT NOT NULL,
            phone TEXT NOT NULL,
            address TEXT,
            doctor_id INTEGER,
            doctor_type TEXT NOT NULL,
            doctor_name TEXT NOT NULL,
            complaint TEXT,
            bp TEXT,
            temp TEXT,
            pulse TEXT,
            weight TEXT,
            height TEXT,
            token TEXT NOT NULL,
            status TEXT DEFAULT 'waiting' CHECK(status IN ('waiting','prescribed','completed')),
            created_at TIMESTAMP DEFAULT (datetime('now', 'localtime')),
            completed_at TIMESTAMP,
            spo2 INTEGER,
            patient_id TEXT
        )");
        $conn->exec("INSERT INTO patients_new SELECT * FROM patients");
        $conn->exec("DROP TABLE patients");
        $conn->exec("ALTER TABLE patients_new RENAME TO patients");
    }

    $conn->exec("CREATE TABLE IF NOT EXISTS prescriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        patient_id INTEGER NOT NULL,
        doctor_id INTEGER,
        doctor_name TEXT NOT NULL,
        doctor_type TEXT,
        consultation_fee REAL DEFAULT 0.00,
        diagnosis TEXT,
        prescription_text TEXT,
        medicines TEXT,
        total_amount REAL DEFAULT 0.00,
        status TEXT DEFAULT 'pending' CHECK(status IN ('pending','dispensed')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        scan_fee REAL DEFAULT 0.00,
        cost_amount REAL DEFAULT 0.00,
        diagnosis_photo TEXT,
        prescription_photo TEXT,
        upt_card BOOLEAN DEFAULT 0,
        injection_details TEXT,
        iv_details TEXT,
        injection_cost REAL DEFAULT 0.00,
        iv_cost REAL DEFAULT 0.00,
        upt_cost REAL DEFAULT 0.00,
        cash_amount REAL DEFAULT 0.00,
        gpay_amount REAL DEFAULT 0.00,
        paid_amount REAL DEFAULT 0.00,
        balance_amount REAL DEFAULT 0.00,
        FOREIGN KEY (patient_id) REFERENCES patients(id)
    )");
    $conn->exec("CREATE TABLE IF NOT EXISTS direct_sales (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_name TEXT NOT NULL,
        mobile_number TEXT NOT NULL,
        medicines TEXT,
        injection_details TEXT,
        iv_details TEXT,
        injection_cost REAL DEFAULT 0.00,
        iv_cost REAL DEFAULT 0.00,
        upt_card BOOLEAN DEFAULT 0,
        upt_cost REAL DEFAULT 0.00,
        total_amount REAL DEFAULT 0.00,
        discount_percent REAL DEFAULT 0.00,
        cash_amount REAL DEFAULT 0.00,
        gpay_amount REAL DEFAULT 0.00,
        paid_amount REAL DEFAULT 0.00,
        balance_amount REAL DEFAULT 0.00,
        cost_amount REAL DEFAULT 0.00,
        status TEXT DEFAULT 'completed' CHECK(status IN ('pending','completed')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $conn->exec("CREATE TABLE IF NOT EXISTS inventory (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        item_code TEXT,
        name TEXT NOT NULL,
        category TEXT DEFAULT 'Tablet',
        hsn_code TEXT,
        batch_number TEXT NOT NULL,
        mfg_date TEXT,
        expiry_date TEXT,
        mrp REAL DEFAULT 0.00,
        purchase_price REAL DEFAULT 0.00,
        selling_price REAL DEFAULT 0.00,
        opening_stock INTEGER DEFAULT 0,
        stock INTEGER DEFAULT 0,
        min_stock INTEGER DEFAULT 0,
        location TEXT,
        tablets_per_strip INTEGER DEFAULT 0,
        UNIQUE(name, batch_number)
    )");

    // Ensure doctor_id exists in patients and prescriptions
    $stmt = $conn->query("PRAGMA table_info(patients)");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('doctor_id', $cols)) {
        $conn->exec("ALTER TABLE patients ADD COLUMN doctor_id INTEGER");
    }

    $stmt = $conn->query("PRAGMA table_info(prescriptions)");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('doctor_id', $cols)) {
        $conn->exec("ALTER TABLE prescriptions ADD COLUMN doctor_id INTEGER");
    }
    if (!in_array('prev_balance_cleared', $cols)) {
        $conn->exec("ALTER TABLE prescriptions ADD COLUMN prev_balance_cleared REAL DEFAULT 0.00");
    }

    if (!in_array('discount_percent', $cols)) {
        $conn->exec("ALTER TABLE prescriptions ADD COLUMN discount_percent REAL DEFAULT 0.00");
    }

    // Migration: Populate doctor_id from existing data if possible
    $conn->exec("UPDATE patients SET doctor_id = (SELECT id FROM users WHERE users.display_name = patients.doctor_name OR users.username = patients.doctor_type LIMIT 1) WHERE doctor_id IS NULL");
    $conn->exec("UPDATE prescriptions SET doctor_id = (SELECT id FROM users WHERE users.display_name = prescriptions.doctor_name OR users.username = prescriptions.doctor_type LIMIT 1) WHERE doctor_id IS NULL");


    // Seed default users if none exist
    $stmt = $conn->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $seed = [
            ['receptionist', 'reception123', 'receptionist', null, 'Receptionist', null],
            ['dr.rasith', 'doctor123', 'doctor', 'Gent', 'Dr. Mohamed Rasith Sir', 'G'],
            ['dr.basheera', 'doctor123', 'doctor', 'Lady', 'Dr. Jannathul Basheera Mam', 'L'],
            ['pharmacist', 'pharma123', 'pharmacist', null, 'Pharmacist', null],
            ['management', 'admin123', 'management', null, 'Management Admin', null],
        ];

        $insert = $conn->prepare("INSERT INTO users (username, password, role, doctor_type, display_name, token_prefix) VALUES (?,?,?,?,?,?)");
        foreach ($seed as $user) {
            $insert->execute($user);
        }
    }

    $conn->exec("CREATE TABLE IF NOT EXISTS staff_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        phone TEXT NOT NULL,
        education TEXT,
        role TEXT,
        salary REAL DEFAULT 0.00,
        status TEXT DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_salary_paid_date TEXT
    )");

    // Migration: Check if last_salary_paid_date column exists
    $stmt = $conn->query("PRAGMA table_info(staff_records)");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('last_salary_paid_date', $cols)) {
        $conn->exec("ALTER TABLE staff_records ADD COLUMN last_salary_paid_date TEXT");
    }

    $conn->exec("CREATE TABLE IF NOT EXISTS staff_payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        staff_id INTEGER NOT NULL,
        payment_type TEXT CHECK(payment_type IN ('Salary', 'Advance', 'Bonus')),
        payment_month TEXT,
        amount REAL DEFAULT 0.00,
        payment_date DATE,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (staff_id) REFERENCES staff_records(id)
    )");

    // Ensure monitor user exists
    $stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role='monitor'");
    if ($stmt->fetchColumn() == 0) {
        $conn->exec("INSERT INTO users (username, password, role, display_name) VALUES ('monitor', 'monitor123', 'monitor', 'TV Monitor')");
    }

    // Create performance indexes
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_patients_phone ON patients(phone)");
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_patients_patient_id ON patients(patient_id)");
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_prescriptions_patient_id ON prescriptions(patient_id)");

    // Create login_attempts table for brute-force protection
    $conn->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        ip_address TEXT PRIMARY KEY,
        attempts INTEGER DEFAULT 1,
        last_attempt TIMESTAMP DEFAULT (datetime('now', 'localtime'))
    )");

    $conn->exec("PRAGMA foreign_keys=ON");
}

/**
 * Doctor naming logic
 */
function format_doctor_name($name, $type)
{
    if (!$name || $name === 'Unknown Doctor')
        return $name;
    $suffix = (stripos($type, 'Gent') !== false) ? ' (Sir)' : ' (Madam)';
    if (strpos($name, '(Sir)') !== false || strpos($name, '(Madam)') !== false)
        return $name;
    return $name . $suffix;
}

function get_doctor_name($doctor_id, $formatted = false)
{
    try {
        $conn = get_db();
        $stmt = $conn->prepare("SELECT display_name, doctor_type FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$doctor_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if ($formatted)
                return format_doctor_name($row['display_name'], $row['doctor_type']);
            return $row['display_name'];
        }
    } catch (Exception $e) {
    }

    return 'Unknown Doctor';
}

/**
 * Fetch all active doctors
 */
function get_all_doctors($only_active = true)
{
    try {
        $conn = get_db();
        $sql = "SELECT * FROM users WHERE role='doctor'";
        if ($only_active) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY doctor_type ASC";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Token generation logic
 */
function generate_token($doctor_id)
{
    $conn = get_db();

    // Get doctor info for prefix
    $stmt = $conn->prepare("SELECT token_prefix FROM users WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $prefix = $stmt->fetchColumn() ?: 'D';
    $prefix = strtoupper($prefix);

    $stmt = $conn->prepare("SELECT token FROM patients WHERE doctor_id = ? AND DATE(created_at) = DATE('now', 'localtime')");
    $stmt->execute([$doctor_id]);

    $rows = $stmt->fetchAll();

    $used_numbers = [];
    foreach ($rows as $row) {
        $parts = explode('-', $row['token']);
        if (count($parts) > 1) {
            $used_numbers[] = (int) $parts[1];
        }
    }

    $next_num = 1;
    while (in_array($next_num, $used_numbers)) {
        $next_num++;
    }

    return sprintf("%s-%03d", $prefix, $next_num);
}

function resolve_upi_account($conn, $account_id) { if (!$account_id) return null; $stmt = $conn->prepare('SELECT short_name FROM upi_accounts WHERE id=?'); $stmt->execute([$account_id]); $res = $stmt->fetchColumn(); return $res ?: null; }
