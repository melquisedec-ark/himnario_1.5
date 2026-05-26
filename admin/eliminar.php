<?php
session_start();
// 1. Seguridad: Solo admins pueden borrar
if (!isset($_SESSION['usuario_logueado'])) {
    header("Location: ../login.php");
    exit;
}

include '../includes/db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // PRECAUCIÓN: Al borrar el himno, MySQL borrará sus estrofas automáticamente (Cascade)
    $stmt = $conexion->prepare("DELETE FROM himnos WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: index.php?msg=eliminado");
    } else {
        echo "Error al eliminar: " . $conexion->error;
    }
} else {
    header("Location: index.php");
}
?>