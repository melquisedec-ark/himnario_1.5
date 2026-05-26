<?php
// includes/db.php
$host = 'localhost';
$user = 'root';      // Usuario por defecto en XAMPP
$password = '';      // Contraseña por defecto vacía en XAMPP
$db = 'himnario_web';

$conexion = new mysqli($host, $user, $password, $db);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

// Forzar caracteres UTF-8 para evitar problemas con tildes y ñ
$conexion->set_charset("utf8mb4");
?>