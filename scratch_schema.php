<?php
require 'c:\Users\HP-AQUINO\Desktop\SISTEMA\helpdesk\config\database.php';
$stmt = $conn->query('DESCRIBE tickets');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
