<?php
/**
 * Funciones auxiliares para Himnario Digital v1.5
 * Proporciona helpers para toda la aplicación.
 */

/**
 * Obtiene las categorías de un himno.
 * @param mysqli $conexion Conexión a la BD
 * @param int $himno_id ID del himno
 * @return array Array de strings con nombres de categorías
 */
function obtenerCategorias($conexion, $himno_id) {
    $categorias = [];
    $stmt = $conexion->prepare(
        "SELECT c.nombre 
         FROM categorias c 
         INNER JOIN himno_categoria hc ON hc.categoria_id = c.id 
         WHERE hc.himno_id = ? 
         ORDER BY c.nombre"
    );
    if (!$stmt) return $categorias;
    $stmt->bind_param("i", $himno_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $categorias[] = $row['nombre'];
    }
    $stmt->close();
    return $categorias;
}

/**
 * Obtiene las versiones por país de un himno.
 * @param mysqli $conexion Conexión a la BD
 * @param int $himno_id ID del himno
 * @return array Array de versiones con datos de país
 */
function obtenerVersiones($conexion, $himno_id) {
    $versiones = [];
    $stmt = $conexion->prepare(
        "SELECT vp.id, vp.tonalidad_original, vp.activo, 
                p.id as pais_id, p.nombre as pais_nombre, p.codigo as pais_codigo
         FROM versiones_pais vp 
         INNER JOIN paises p ON p.id = vp.pais_id 
         WHERE vp.himno_id = ? 
         ORDER BY p.nombre"
    );
    if (!$stmt) return $versiones;
    $stmt->bind_param("i", $himno_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $versiones[] = $row;
    }
    $stmt->close();
    return $versiones;
}

/**
 * Obtiene las estrofas ordenadas de una versión por país.
 * @param mysqli $conexion Conexión a la BD
 * @param int $version_pais_id ID de la versión por país
 * @return array Array de estrofas
 */
function obtenerEstrofas($conexion, $version_pais_id) {
    $estrofas = [];
    $stmt = $conexion->prepare(
        "SELECT id, version_pais_id, tipo, orden, contenido 
         FROM estrofas 
         WHERE version_pais_id = ? 
         ORDER BY orden ASC"
    );
    if (!$stmt) return $estrofas;
    $stmt->bind_param("i", $version_pais_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $estrofas[] = $row;
    }
    $stmt->close();
    return $estrofas;
}

/**
 * Traduce el tipo numérico de himno a texto legible.
 * @param int $tipo 1=Oficial, 2=Inspirada, 3=Convención
 * @return string Texto del tipo
 */
function tipoHimnoTexto($tipo) {
    switch ((int)$tipo) {
        case 1: return 'Oficial';
        case 2: return 'Inspirada';
        case 3: return 'Convención';
        default: return 'Desconocido';
    }
}

/**
 * Verifica si hay sesión activa; si no, redirige al login.
 */
/**
 * Genera navegación de breadcrumbs (migas de pan).
 * @param array $items Array asociativo donde key=etiqueta, value=URL (null si es activo)
 * @return string HTML del breadcrumb
 */
function generarBreadcrumbs($items) {
    $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    foreach ($items as $label => $url) {
        if ($url) {
            $html .= '<li class="breadcrumb-item"><a href="' . sanitizar($url) . '">' . sanitizar($label) . '</a></li>';
        } else {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . sanitizar($label) . '</li>';
        }
    }
    $html .= '</ol></nav>';
    return $html;
}

function verificarSesion() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['usuario_logueado'])) {
        header("Location: ../login.php");
        exit;
    }
}

/**
 * Sanitiza un string para salida HTML segura.
 * @param string $input Texto a sanitizar
 * @return string Texto sanitizado
 */
function sanitizar($input) {
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}
