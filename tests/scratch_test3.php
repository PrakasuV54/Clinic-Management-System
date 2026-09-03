<?php
require 'db.php';
$db = get_db();
$stmt = $db->query('PRAGMA table_info(prescriptions)');
$cols = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cols[] = $r['name'];
}
print_r($cols);
