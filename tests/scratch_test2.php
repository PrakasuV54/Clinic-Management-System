<?php
require 'db.php';
$conn = get_db();
$stmt = $conn->query("SELECT date('now', 'localtime') as sqlite_date, date('now') as utc_date, date('2026-06-23 12:55:45') as d1");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
