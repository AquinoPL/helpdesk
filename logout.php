<?php
session_start();
session_unset();
session_destroy();
// Usa ruta relativa o base generica
header("Location: index.php");
exit();
?>
