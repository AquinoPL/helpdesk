<?php require "config/database.php"; $stmt = $conn->query("SELECT * FROM trabajadores WHERE role = 'admin'"); print_r($stmt->fetchAll(PDO::FETCH_ASSOC)); ?>
