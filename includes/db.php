<?php
/**
 * Conexión a MySQL en Docker
 * Container: mysql_himnario_v1
 * DB: himnario_db
 * User: himnario_user
 * Pass: userpassword123
 */
$host     = 'mysql';               // Nombre del servicio MySQL en Docker (misma red)
$port     = 3306;
$user     = 'himnario_user';
$password = 'userpassword123';
$db       = 'himnario_db';

$conexion = new mysqli($host, $user, $password, $db, $port);

if ($conexion->connect_error) {
    error_log("Error de conexión MySQL: " . $conexion->connect_error);
    die("Error de conexión a la base de datos. Contacte al administrador.");
}

$conexion->set_charset("utf8mb4");
$conexion->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
?>
