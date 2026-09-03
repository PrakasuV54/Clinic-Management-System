<?php
require 'api.php';
$conn = get_db();
$stmt = $conn->query('SELECT * FROM upi_accounts');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
