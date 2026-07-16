<?php
// ── Conexión MySQL local ──────────────────────────────────────
$host   = "localhost";
$port   = "3306";       // Puerto por defecto de MySQL
$dbname = "helpdesk";   // Nombre de la base de datos creada en MySQL
$user   = "root";       // Usuario de MySQL (cámbialo si usas otro)
$password = "";         // Contraseña de MySQL (cámbiala si tienes una)

try {
    $conn = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $password
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE,        PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Usar prepared statements reales
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
