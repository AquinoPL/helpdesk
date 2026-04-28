<?php require "config/database.php"; $stmt = $conn->query("SELECT * FROM usuarios WHERE dni = '70000001'"); print_r($stmt->fetchAll(PDO::FETCH_ASSOC)); ?>
