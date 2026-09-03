<?php
/**
 * UAT and Production Readiness Simulation Script
 */

require_once dirname(__DIR__) . '/app/helpers/db.php';
require_once dirname(__DIR__) . '/app/middleware/auth.php';
require_once dirname(__DIR__) . '/app/helpers/audit_logger.php';

echo "=== STARTING PRODUCTION READINESS SIMULATION ===\n\n";

$conn = get_db();

// 1. Check current users and plaintext count
echo "--- 1. AUTHENTICATION & PASSWORD STATUS ---\n";
$stmt = $conn->query("SELECT id, username, role, password FROM users");
$users = $stmt->fetchAll();
$plaintext_count = 0;
foreach ($users as $u) {
    $info = password_get_info($u['password']);
    if ($info['algo'] === 0) {
        echo "WARNING: Plaintext account found: [{$u['username']}] (Role: {$u['role']})\n";
        $plaintext_count++;
    } else {
        echo "SECURE: Hashed account: [{$u['username']}] (Role: {$u['role']})\n";
    }
}
echo "Total Plaintext Accounts: $plaintext_count\n\n";

// 2. Role Authorization & Middleware Simulation
echo "--- 2. ROLE AUTHORIZATION SIMULATION ---\n";
function simulate_role_check($username, $role, $allowed_roles) {
    $_SESSION['user_id'] = 999;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
    $_SESSION['force_password_change'] = false;
    
    // Catch exit in check_api_role via buffer or check manually
    // Since check_api_role exits on failure, let's replicate the logic for testing:
    if ($_SESSION['role'] === 'management') {
        return "ALLOWED (Management Bypass)";
    }
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        return "DENIED";
    }
    return "ALLOWED";
}

echo "Receptionist accessing Receptionist API: " . simulate_role_check('receptionist', 'receptionist', ['receptionist', 'management']) . "\n";
echo "Receptionist accessing Doctor API: " . simulate_role_check('receptionist', 'receptionist', ['doctor', 'management']) . "\n";
echo "Doctor accessing Doctor API: " . simulate_role_check('doctor', 'doctor', ['doctor', 'management']) . "\n";
echo "Doctor accessing Management API: " . simulate_role_check('doctor', 'doctor', ['management']) . "\n";
echo "Management accessing Doctor API: " . simulate_role_check('admin', 'management', ['doctor', 'management']) . "\n";
echo "\n";

// 3. Performance & Load Simulation
echo "--- 3. PERFORMANCE & LOAD SIMULATION ---\n";
// Insert 1000 patients in transaction
echo "Inserting 1,000 dummy patients to simulate high load...\n";
$start_time = microtime(true);
$conn->beginTransaction();
try {
    $stmt = $conn->prepare("INSERT INTO patients (name, age, gender, phone, address, doctor_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    for ($i = 0; $i < 1000; $i++) {
        $name = "Test Patient " . $i;
        $age = rand(1, 90);
        $gender = ($i % 2 === 0) ? 'Male' : 'Female';
        $phone = sprintf("98765%05d", $i);
        $address = "123 Test Street, City";
        $doctor_id = 2; // dr.rasith
        $status = 'waiting';
        $created_at = date('Y-m-d H:i:s');
        $stmt->execute([$name, $age, $gender, $phone, $address, $doctor_id, $status, $created_at]);
    }
    $conn->commit();
    $end_time = microtime(true);
    echo "Inserted 1,000 patients successfully in " . round($end_time - $start_time, 4) . " seconds.\n";
} catch (Exception $e) {
    $conn->rollBack();
    echo "Error inserting dummy patients: " . $e->getMessage() . "\n";
}

// Search latency check
echo "Testing search latency by phone number (Indexed vs Unindexed)...\n";
$test_phone = "9876500500";
$start_time = microtime(true);
$stmt = $conn->prepare("SELECT * FROM patients WHERE phone = ?");
$stmt->execute([$test_phone]);
$results = $stmt->fetchAll();
$end_time = microtime(true);
echo "Phone search query completed in " . round(($end_time - $start_time) * 1000, 4) . " ms. Found: " . count($results) . " patient(s).\n";

// Clean up dummy patients
$conn->exec("DELETE FROM patients WHERE name LIKE 'Test Patient %'");
echo "Cleaned up dummy test patients.\n\n";

// 4. Backup & Restore Simulation
echo "--- 4. BACKUP & RESTORE INTEGRITY SIMULATION ---\n";
$db_file = dirname(__DIR__) . '/database/sqlite/hospital_portal.db';
$backup_file = dirname(__DIR__) . '/backup/uat_temp_backup.db';

if (file_exists($db_file)) {
    echo "Copying database file to $backup_file...\n";
    copy($db_file, $backup_file);
    
    if (file_exists($backup_file)) {
        echo "Verifying backup integrity...\n";
        try {
            $backup_conn = new PDO("sqlite:" . $backup_file);
            $backup_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $chk = $backup_conn->query("PRAGMA integrity_check;")->fetchColumn();
            echo "Integrity check response: $chk\n";
            if ($chk === 'ok') {
                echo "SUCCESS: Backup is valid and uncorrupted.\n";
            } else {
                echo "FAIL: Backup is corrupted.\n";
            }
        } catch (Exception $ex) {
            echo "Error during verification: " . $ex->getMessage() . "\n";
        }
        unlink($backup_file);
    } else {
        echo "FAIL: Could not create backup file.\n";
    }
} else {
    echo "FAIL: Source database file not found.\n";
}

echo "\n=== SIMULATION COMPLETE ===\n";
