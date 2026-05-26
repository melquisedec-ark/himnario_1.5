<?php
// Incluimos el archivo de conexión que creaste en el paso anterior
require 'includes/db.php'; 

echo "<h1>Estado del Sistema:</h1>";

// 1. Verificar si la variable de conexión existe
if ($conexion) {
    echo "<p style='color: green; font-weight: bold;'>✅ Conexión con la Base de Datos: EXITOSA</p>";
} else {
    die("<p style='color: red;'>❌ Error crítico: No se pudo conectar.</p>");
}

// 2. Intentar traer datos reales (El himno de prueba)
$sql = "SELECT * FROM himnos";
$resultado = $conexion->query($sql);

if ($resultado) {
    echo "<p>✅ Consulta SQL: EXITOSA</p>";
    echo "<hr>";
    echo "<h3>Datos encontrados en la tabla 'himnos':</h3>";
    
    if ($resultado->num_rows > 0) {
        // Recorremos los resultados
        while($fila = $resultado->fetch_assoc()) {
            echo "Start ---> <b>Himno #" . $fila["numero"] . ":</b> " . $fila["titulo"] . " <--- End<br>";
        }
    } else {
        echo "La conexión funciona, pero la tabla está vacía (¿Ejecutaste el INSERT en SQL?).";
    }
} else {
    echo "<p style='color: red;'>❌ Error en la consulta: " . $conexion->error . "</p>";
}
?>