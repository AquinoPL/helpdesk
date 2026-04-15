<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'config/database.php';

header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($q) < 2) {
    echo json_encode([]);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT * FROM search_office(:name)");
    $stmt->execute(['name' => $q]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
