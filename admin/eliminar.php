<?php
/**
 * admin/eliminar.php - Eliminar himno (ON DELETE CASCADE se encarga del resto)
 */
require_once '../includes/db.php';
require_once '../includes/funciones.php';
verificarSesion();

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $stmt = $conexion->prepare("DELETE FROM himnos WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: index.php?msg=eliminado");
    } else {
        echo "Error al eliminar: " . sanitizar($conexion->error);
    }
    $stmt->close();
} else {
    header("Location: index.php");
}
exit;
