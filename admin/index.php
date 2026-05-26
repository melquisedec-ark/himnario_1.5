<?php
session_start();
if (!isset($_SESSION['usuario_logueado'])) { header("Location: ../login.php"); exit; }
include '../includes/db.php';
$sql = "SELECT * FROM himnos ORDER BY numero ASC";
$resultado = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
    <div class="container">
        <span class="navbar-brand h1 mb-0">⚙️ Panel Admin</span>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" onclick="cambiarTema()">🌗 Tema</button>
            <a href="../index.php" class="btn btn-outline-primary btn-sm" target="_blank">Ver Web</a>
            <a href="logout.php" class="btn btn-danger btn-sm">Salir</a>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Lista de Himnos</h2>
        <a href="crear.php" class="btn btn-success">+ Nuevo Himno</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">#</th>
                        <th>Título</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado->num_rows > 0): ?>
                        <?php while($fila = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?php echo $fila['numero']; ?></td>
                                <td><?php echo $fila['titulo']; ?></td>
                                <td class="text-end pe-4">
                                    <a href="editar.php?id=<?php echo $fila['id']; ?>" class="btn btn-primary btn-sm">Editar</a>
                                    <a href="eliminar.php?id=<?php echo $fila['id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Borrar este himno?')">🗑️</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-center py-4">No hay himnos aún.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Recuperar tema guardado
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('admin_theme') || 'light';
    html.setAttribute('data-bs-theme', savedTheme);

    function cambiarTema() {
        const current = html.getAttribute('data-bs-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-bs-theme', next);
        localStorage.setItem('admin_theme', next);
    }
</script>
</body>
</html>