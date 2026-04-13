<?php

$host = "localhost";
$port = "5432";
$dbname = "db_support";
$user = "postgres";
$password = "123456789";

/*
$host = "aws-1-us-east-1.pooler.supabase.com";
$port = "6543";
$dbname = "postgres";
$user = "postgres.wxanqkmfafgcmbwroxzg";
$password = "soporte*2026";
*/



try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>