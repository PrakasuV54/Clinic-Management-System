<?php
require 'db.php';
$conn = get_db();
$stmt = $conn->query("SELECT id, name, token, doctor_id, doctor_type, status, created_at FROM patients");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
