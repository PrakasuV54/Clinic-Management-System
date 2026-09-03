<?php require 'db.php'; $conn = get_db(); $conn->exec('ALTER TABLE direct_sales ADD COLUMN account_id INTEGER DEFAULT NULL'); echo 'Added to direct_sales\n'; ?>
