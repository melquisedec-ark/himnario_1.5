<?php
/**
 * admin/index.php - Panel de control / Lista de himnos
 */
require_once '../includes/db.php';
require_once '../includes/funciones.php';
verificarSesion();

// Búsqueda
$busqueda = trim($_GET['q'] ?? '');

// Consulta base con joins para mostrar datos completos
$sql = "SELECT h.*, 
               GROUP_CONCAT(DISTINCT c.nombre SEPARATOR ', ') as categorias,
               vp.tonalidad_original
        FROM himnos h 
        LEFT JOIN himno_categoria hc ON hc.himno_id = h.id 
        LEFT JOIN categorias c ON c.id = hc.categoria_id 
        LEFT JOIN versiones_pais vp ON vp.himno_id = h.id AND vp.activo = 1 ";

$params = [];
$types = "";

if ($busqueda !== '') {
    $sql .= "LEFT JOIN estrofas e ON e.version_pais_id = vp.id ";
}

$sql .= "GROUP BY h.id ";

if ($busqueda !== '') {
    $sql .= "HAVING (h.titulo_principal LIKE ? OR h.numero_oficial LIKE ? OR GROUP_CONCAT(DISTINCT e.contenido SEPARATOR ' ') LIKE ?) ";
    $like = "%{$busqueda}%";
    $params = [$like, $like, $like];
    $types = "sss";
}

$sql .= "ORDER BY h.numero_oficial ASC";

$stmt = $conexion->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$resultado = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - Himnario Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <script>
        (function() {
            try {
                var t = localStorage.getItem('himnario_theme');
                if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme:dark)').matches)) {
                    document.documentElement.setAttribute('data-bs-theme', 'dark');
                }
            } catch(e) {}
        })();
    </script>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
    <div class="container">
        <span class="navbar-brand h1 mb-0 fw-bold">⚙️ Panel Admin</span>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-primary"><?php echo sanitizar($_SESSION['usuario_nombre'] ?? 'Admin'); ?></span>
            <button class="btn btn-outline-secondary btn-sm" onclick="Himnario.toggleTheme()">🌗 Tema</button>
            <a href="../index.php" class="btn btn-outline-primary btn-sm" target="_blank">Ver Web</a>
            <a href="logout.php" class="btn btn-danger btn-sm">Salir</a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0">Lista de Himnos</h2>
        <a href="crear.php" class="btn btn-success">+ Nuevo Himno</a>
    </div>

    <!-- Barra de búsqueda -->
    <div class="search-filters mb-4">
        <form method="GET" action="index.php" class="row g-2 align-items-end">
            <div class="col-md-8 col-lg-9">
                <div class="input-group">
                    <input type="text" name="q" class="form-control form-control-lg" 
                           placeholder="🔍 Buscar por número, título o letra del himno..."
                           value="<?php echo sanitizar($busqueda); ?>"
                           autofocus>
                    <button type="submit" class="btn btn-primary px-4">Buscar</button>
                    <?php if ($busqueda !== ''): ?>
                        <a href="index.php" class="btn btn-outline-secondary px-3">✕ Limpiar</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4 col-lg-3 text-md-end">
                <small class="text-muted">
                    <?php if ($busqueda !== ''): ?>
                        Resultados para "<strong><?php echo sanitizar($busqueda); ?></strong>":
                    <?php endif; ?>
                    <strong><?php echo $resultado->num_rows; ?></strong> himno(s)
                </small>
            </div>
        </form>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'eliminado'): ?>
            <div class="alert alert-success alert-dismissible fade show">Himno eliminado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($_GET['msg'] === 'creado'): ?>
            <div class="alert alert-success alert-dismissible fade show">Himno creado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($_GET['msg'] === 'actualizado'): ?>
            <div class="alert alert-success alert-dismissible fade show">Himno actualizado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($resultado && $resultado->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="admin-table table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Título</th>
                        <th>Tipo</th>
                        <th>Tonalidad</th>
                        <th>Categorías</th>
                        <th>Activo</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($fila = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td><span class="admin-table-numero"><?php echo (int)$fila['numero_oficial']; ?></span></td>
                            <td class="fw-semibold"><?php echo sanitizar($fila['titulo_principal']); ?></td>
                            <td>
                                <span class="badge-categoria" data-categoria="<?php 
                                    $t = (int)$fila['tipo'];
                                    echo $t === 1 ? 'himno-oficial' : ($t === 2 ? 'himno-inspirado' : 'convencion');
                                ?>"><?php echo tipoHimnoTexto($fila['tipo']); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($fila['tonalidad_original'])): ?>
                                    <span class="badge-tonalidad"><?php echo sanitizar($fila['tonalidad_original']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($fila['categorias'])): ?>
                                    <?php foreach (explode(', ', $fila['categorias']) as $cat): ?>
                                        <span class="badge bg-secondary me-1"><?php echo sanitizar($cat); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$fila['activo'] === 1): ?>
                                    <span class="badge bg-success">Sí</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">No</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="acciones">
                                    <a href="editar.php?id=<?php echo (int)$fila['id']; ?>" class="btn btn-primary btn-sm">Editar</a>
                                    <a href="eliminar.php?id=<?php echo (int)$fila['id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Está seguro de eliminar este himno?\nEsta acción no se puede deshacer.')">🗑️</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small mt-2">Total: <strong><?php echo $resultado->num_rows; ?></strong> himnos</p>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📖</div>
            <div class="empty-title">No hay himnos registrados</div>
            <p class="text-muted">Comience creando un nuevo himno.</p>
            <a href="crear.php" class="btn btn-success">+ Nuevo Himno</a>
        </div>
    <?php endif; ?>
</div>

<script src="../js/app.js" defer></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Himnario.initPage('admin-index');
    });
</script>
</body>
</html>
