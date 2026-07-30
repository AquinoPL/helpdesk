<?php
require 'config/database.php';
try {
    $conn->exec("UPDATE usuarios SET is_registered = 0 WHERE password = dni");
    echo "Updated public users to is_registered = 0";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
