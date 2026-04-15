<?php
require 'config/database.php';
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE status NOT IN ('Atendido', 'Rechazado')");
    $stmt->execute();
    echo 'SUCCESS: ' . $stmt->fetchColumn() . "\n";
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE status NOT IN ('Atendido'::ticket_status, 'Rechazado'::ticket_status)");
    $stmt->execute();
    echo 'SUCCESS2: ' . $stmt->fetchColumn() . "\n";
	
	$stmtUser = $conn->prepare("INSERT INTO usuarios (dni, first_name, last_name, phone, office_id, password) VALUES ('99999999', 'Test', 'Test', '123', NULL, '99999999') RETURNING id");
	$stmtUser->execute();
	echo 'SUCCESS3: User ID ' . $stmtUser->fetchColumn() . "\n";

} catch (PDOException $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
?>
