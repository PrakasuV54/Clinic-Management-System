<?php
require_once __DIR__ . '/../db.php';
$conn = get_db();

echo "=== ALL TABLES ===\n";
$r = $conn->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
foreach($r->fetchAll() as $row) echo $row['name'] . "\n";

echo "\n=== UPI_ACCOUNTS ===\n";
try {
    $r = $conn->query("SELECT sql FROM sqlite_master WHERE name='upi_accounts'");
    $row = $r->fetch();
    if ($row) {
        echo "Schema: " . $row['sql'] . "\n";
        $r2 = $conn->query("SELECT * FROM upi_accounts");
        $data = $r2->fetchAll();
        echo "Rows: " . count($data) . "\n";
        foreach($data as $d) echo json_encode($d) . "\n";
    } else {
        echo "Table does NOT exist.\n";
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
