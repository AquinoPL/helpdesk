<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    // Detectar si el proyecto está en la carpeta Soporte-Alianza (típico en XAMPP)
    if (strpos($_SERVER['SCRIPT_NAME'], '/Soporte-Alianza') !== false) {
        define('BASE_URL', '/Soporte-Alianza');
    } else {
        define('BASE_URL', '');
    }
}

if (!isset($_SESSION["user"])) {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

function restrict_access($allowed_roles) {
    if (!isset($_SESSION["user"])) return;
    if (!in_array($_SESSION["user"]["role"], $allowed_roles)) {
        header("Location: " . BASE_URL . "/index.php");
        exit();
    }
}
?>
