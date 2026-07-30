<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'config/database.php';

header('Content-Type: application/json');

$dni = isset($_GET['dni']) ? trim($_GET['dni']) : '';

if (empty($dni)) {
    echo json_encode(['error' => 'DNI no proporcionado']);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT dni, first_name, last_name, phone, office_id FROM usuarios WHERE dni = :dni LIMIT 1");
    $stmt->execute(['dni' => $dni]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode(['success' => true, 'data' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado. Se creará uno nuevo al enviar.']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error de base de datos']);
}
?>
