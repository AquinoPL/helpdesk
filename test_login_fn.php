<?php require "config/database.php"; $stmt = $conn->prepare("SELECT * FROM login_user('70000001', '123456')"); $stmt->execute(); print_r($stmt->fetch(PDO::FETCH_ASSOC)); ?>
