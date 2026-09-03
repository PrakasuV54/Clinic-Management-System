<?php require 'db.php'; $conn = get_db(); $stmt = $conn->query('PRAGMA table_info(prescriptions)'); print_r($stmt->fetchAll(PDO::FETCH_ASSOC)); ?>
