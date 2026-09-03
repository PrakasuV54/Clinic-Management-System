<?php
require 'db.php';
$conn = get_db();
$stmt = $conn->query("SELECT * FROM upi_accounts ORDER BY id ASC");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
?>
