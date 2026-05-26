<?php
/**
 * index.php - Buscador público de himnos
 * Búsqueda por título, número, contenido de estrofas
 * Filtros: categoría, tipo, tonalidad
 */
require_once 'includes/db.php';
require_once 'includes/funciones.php';

$busqueda = trim($_GET['q'] ?? '');
$categoria_filtro = (int)($_GET['categoria'] ?? 0);
$tipo_filtro = (int)($_GET['tipo'] ?? 0);
$tonalidad_filtro = trim($_GET['tonalidad'] ?? '');

$resultados = [];
$total = 0;

// Cargar listas para filtros
$categorias = $conexion->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
$tonalidades_list = $conexion->query("SELECT DISTINCT tonalidad_original FROM versiones_pais WHERE tonalidad_original != '' AND tonalidad_original IS NOT NULL ORDER BY tonalidad_original ASC");

// Construir consulta
$sql = "SELECT DISTINCT h.id, h.titulo_principal, h.numero_oficial, h.tipo, h.activo,
               vp.tonalidad_original, p.nombre as pais_nombre, p.codigo as pais_codigo,
               GROUP_CONCAT(DISTINCT c.nombre SEPARATOR ', ') as categorias
        FROM himnos h
        LEFT JOIN versiones_pais vp ON vp.himno_id = h.id AND vp.activo = 1
        LEFT JOIN paises p ON p.id = vp.pais_id
        LEFT JOIN himno_categoria hc ON hc.himno_id = h.id
        LEFT JOIN categorias c ON c.id = hc.categoria_id
        LEFT JOIN estrofas e ON e.version_pais_id = vp.id";

$where = ["h.activo = 1"];
$params = [];
$types = "";

// Búsqueda por texto
if ($busqueda !== '') {
    $where[] = "(h.titulo_principal LIKE ? OR h.numero_oficial LIKE ? OR e.contenido LIKE ?)";
    $termino = "%" . $busqueda . "%";
    $params[] = $termino;
    $params[] = $termino;
    $params[] = $termino;
    $types .= "sss";
}

// Filtro por categoría
if ($categoria_filtro > 0) {
    $where[] = "hc.categoria_id = ?";
    $params[] = $categoria_filtro;
    $types .= "i";
}

// Filtro por tipo
if ($tipo_filtro >= 1 && $tipo_filtro <= 3) {
    $where[] = "h.tipo = ?";
    $params[] = $tipo_filtro;
    $types .= "i";
}

// Filtro por tonalidad
if ($tonalidad_filtro !== '') {
    $where[] = "vp.tonalidad_original = ?";
    $params[] = $tonalidad_filtro;
    $types .= "s";
}

$sql .= " WHERE " . implode(" AND ", $where);
$sql .= " GROUP BY h.id ORDER BY h.numero_oficial ASC";

// Si no hay búsqueda ni filtros, limitar a 30 resultados
$limit = ($busqueda === '' && $categoria_filtro === 0 && $tipo_filtro === 0 && $tonalidad_filtro === '') ? 30 : 200;
$sql .= " LIMIT ?";
$params[] = $limit;
$types .= "i";

$stmt = $conexion->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultados = $stmt->get_result();
    $total = $resultados->num_rows;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Himnario Digital - Buscador</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1a5276">
</head>
<body>

<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">📖 Himnario Seleccionado</a>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-primary d-none d-md-inline">v1.5</span>
            <button class="btn btn-outline-secondary btn-sm" onclick="Himnario.toggleTheme()">🌗 Tema</button>
            <a href="admin/index.php" class="btn btn-outline-primary btn-sm">Admin</a>
        </div>
    </div>
</nav>

<div class="container search-container">

    <div class="text-center mb-4">
        <h1 class="display-5 fw-bold">Buscar Alabanza</h1>
        <p class="text-muted">Encuentra por número, título o letra</p>
    </div>

    <!-- Filtros de búsqueda -->
    <form action="index.php" method="GET" class="search-filters mb-4">
        <div class="filter-group" style="grid-column: 1 / -1;">
            <label for="q">Buscar</label>
            <input type="text" name="q" id="q" class="form-control"
                   placeholder="Escribe aquí... (Ej: 'Es mi rey' o '15')"
                   value="<?php echo sanitizar($busqueda); ?>" autofocus>
        </div>
        <div class="filter-group">
            <label for="categoria">Categoría</label>
            <select name="categoria" id="categoria" class="form-select">
                <option value="0">Todas</option>
                <?php if ($categorias): $categorias->data_seek(0); while($cat = $categorias->fetch_assoc()): ?>
                    <option value="<?php echo (int)$cat['id']; ?>" <?php echo $categoria_filtro === (int)$cat['id'] ? 'selected' : ''; ?>>
                        <?php echo sanitizar($cat['nombre']); ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="tipo">Tipo de Himno</label>
            <select name="tipo" id="tipo" class="form-select">
                <option value="0">Todos</option>
                <option value="1" <?php echo $tipo_filtro === 1 ? 'selected' : ''; ?>>Oficial</option>
                <option value="2" <?php echo $tipo_filtro === 2 ? 'selected' : ''; ?>>Inspirada</option>
                <option value="3" <?php echo $tipo_filtro === 3 ? 'selected' : ''; ?>>Convención</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="tonalidad">Tonalidad</label>
            <select name="tonalidad" id="tonalidad" class="form-select">
                <option value="">Todas</option>
                <?php if ($tonalidades_list): $tonalidades_list->data_seek(0); while($ton = $tonalidades_list->fetch_assoc()): ?>
                    <option value="<?php echo sanitizar($ton['tonalidad_original']); ?>" <?php echo $tonalidad_filtro === $ton['tonalidad_original'] ? 'selected' : ''; ?>>
                        <?php echo sanitizar($ton['tonalidad_original']); ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>
        <div class="filter-group d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">🔍 Buscar</button>
        </div>
    </form>

    <!-- Resultados -->
    <?php if ($total > 0): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted small"><?php echo $total; ?> himno(s) encontrado(s)</span>
        </div>
        <div class="row g-3" id="results-container">
            <?php while($fila = $resultados->fetch_assoc()): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="presentacion.php?id=<?php echo (int)$fila['id']; ?>" class="himno-card text-decoration-none h-100">
                        <span class="himno-numero"><?php echo (int)$fila['numero_oficial']; ?></span>
                        <div class="flex-grow-1">
                            <h3 class="himno-titulo mb-1"><?php echo sanitizar($fila['titulo_principal']); ?></h3>
                            <div class="himno-metadata">
                                <!-- Tipo -->
                                <span class="badge-categoria" data-categoria="<?php
                                    $t = (int)$fila['tipo'];
                                    echo $t === 1 ? 'himno-oficial' : ($t === 2 ? 'himno-inspirado' : 'convencion');
                                ?>"><?php echo tipoHimnoTexto($fila['tipo']); ?></span>

                                <!-- Tonalidad -->
                                <?php if (!empty($fila['tonalidad_original'])): ?>
                                    <span class="badge-tonalidad"><?php echo sanitizar($fila['tonalidad_original']); ?></span>
                                <?php endif; ?>

                                <!-- País -->
                                <?php if (!empty($fila['pais_nombre'])): ?>
                                    <span class="badge-pais"><?php echo sanitizar($fila['pais_nombre']); ?></span>
                                <?php endif; ?>

                                <!-- Categorías -->
                                <?php if (!empty($fila['categorias'])):
                                    $cats = explode(', ', $fila['categorias']);
                                    foreach ($cats as $cat): ?>
                                        <span class="badge bg-secondary"><?php echo sanitizar($cat); ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>
    <?php elseif ($busqueda !== '' || $categoria_filtro > 0 || $tipo_filtro > 0 || $tonalidad_filtro !== ''): ?>
        <div class="empty-state">
            <div class="empty-icon">😕</div>
            <div class="empty-title">No encontramos nada</div>
            <p class="text-muted">Intenta buscar con otra palabra o ajusta los filtros.</p>
            <a href="index.php" class="btn btn-outline-primary">Limpiar filtros</a>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📖</div>
            <div class="empty-title">Bienvenido al Himnario Digital</div>
            <p class="text-muted">Escribe un término de búsqueda o selecciona filtros para comenzar.</p>
        </div>
    <?php endif; ?>
</div>

<script src="js/app.js" defer></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Himnario.initPage('index');

        // Auto-submit en filtros al cambiar
        document.querySelectorAll('#categoria, #tipo, #tonalidad').forEach(function(el) {
            el.addEventListener('change', function() {
                this.closest('form').submit();
            });
        });
    });

    // Service Worker (PWA)
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js')
            .then(function() { console.log('PWA Ready'); })
            .catch(function(err) { console.log('PWA Error', err); });
    }
</script>
</body>
</html>
