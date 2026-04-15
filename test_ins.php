<?php
require 'config/database.php';
try {
	$conn->beginTransaction();
	$dni = "99999998"; $first_name = "Jane"; $last_name = "Doe"; $phone = "333"; $office_id = null;
    $stmtUser = $conn->prepare("INSERT INTO usuarios (dni, first_name, last_name, phone, office_id, password) VALUES (?, ?, ?, ?, ?, ?) RETURNING id");
    $stmtUser->execute([$dni, $first_name, $last_name, $phone, $office_id, $dni]);
    $user_id = $stmtUser->fetchColumn();
	echo "USER INS: " . $user_id . "\n";
	
	$category = "Software"; $title = "X"; $description = "Y";
	$stmt = $conn->prepare("INSERT INTO tickets (user_id, category, title, description, office_id) VALUES (?, ?::ticket_category, ?, ?, ?) RETURNING id");
    $stmt->execute([$user_id, $category, $title, $description, $office_id]);
    $new_ticket_id = $stmt->fetchColumn();
	echo "TKT INS: " . $new_ticket_id . "\n";
	
	$stmtHist = $conn->prepare("INSERT INTO ticket_history (ticket_id, status, comment) VALUES (?, 'Pendiente', 'Ticket creado desde el portal público')");
    $stmtHist->execute([$new_ticket_id]);
	echo "HIST INS OK\n";
	$conn->commit();
} catch (PDOException $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
?>
