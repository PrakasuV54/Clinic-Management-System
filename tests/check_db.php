<?php require 'db.php'; $conn = get_db(); $stmt = $conn->query('PRAGMA table_info(upi_accounts)'); print_r($stmt->fetchAll()); ?>
