<?php
/**
 * api_search.php — Endpoint AJAX para el Himnario Digital v1.5
 * 
 * Recibe los mismos parámetros GET que index.php (q, categoria, tipo, tonalidad)
 * y devuelve SOLO el fragmento HTML de los resultados (sin <html>, <head>, <body>, navbar, hero).
 * 
 * Uso: fetch('api_search.php?q=amor&categoria=1&tipo=2')
 * 
 * HEADERS DE SEGURIDAD:
 *   Content-Type: text/html; charset=utf-8
 *   X-Content-Type-Options: nosniff
 *   Cache-Control: no-cache (datos dinámicos)
 */

require_once 'includes/db.php';
require_once 'includes/funciones.php';

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, must-revalidate');

// -------------------------------------------------------------------
// 1. LEER PARÁMETROS (idéntico a index.php)
// -------------------------------------------------------------------
$busqueda         = trim($_GET['q'] ?? '');
$categoria_filtro = (int) ($_GET['categoria'] ?? 0);
$tipo_filtro      = (int) ($_GET['tipo'] ?? 0);
$tonalidad_filtro = trim($_GET['tonalidad'] ?? '');

// -------------------------------------------------------------------
// 2. CONSTRUIR CONSULTA (idéntica a index.php)
// -------------------------------------------------------------------
$sql = "SELECT DISTINCT h.id, h.titulo_principal, h.numero_oficial, h.tipo, h.activo,
               vp.tonalidad_original, p.nombre as pais_nombre, p.codigo as pais_codigo,
               GROUP_CONCAT(DISTINCT c.nombre SEPARATOR ', ') as categorias
        FROM himnos h
        LEFT JOIN versiones_pais vp ON vp.himno_id = h.id AND vp.activo = 1
        LEFT JOIN paises p ON p.id = vp.pais_id
        LEFT JOIN himno_categoria hc ON hc.himno_id = h.id
        LEFT JOIN categorias c ON c.id = hc.categoria_id
        LEFT JOIN estrofas e ON e.version_pais_id = vp.id";

$where  = ["h.activo = 1"];
$params = [];
$types  = "";

// Búsqueda por texto (título, número, contenido)
if ($busqueda !== '') {
    $where[] = "(h.titulo_principal LIKE ? OR h.numero_oficial LIKE ? OR e.contenido LIKE ?)";
    $termino = "%" . $busqueda . "%";
    $params[] = $termino;
    $params[] = $termino;
    $params[] = $termino;
    $types   .= "sss";
}

// Filtro por categoría
if ($categoria_filtro > 0) {
    $where[] = "hc.categoria_id = ?";
    $params[] = $categoria_filtro;
    $types   .= "i";
}

// Filtro por tipo (1=Oficial, 2=Inspirada, 3=Convención)
if ($tipo_filtro >= 1 && $tipo_filtro <= 3) {
    $where[] = "h.tipo = ?";
    $params[] = $tipo_filtro;
    $types   .= "i";
}

// Filtro por tonalidad
if ($tonalidad_filtro !== '') {
    $where[] = "vp.tonalidad_original = ?";
    $params[] = $tonalidad_filtro;
    $types   .= "s";
}

$sql .= " WHERE " . implode(" AND ", $where);
$sql .= " GROUP BY h.id";
$sql .= " ORDER BY h.tipo ASC, h.numero_oficial ASC";

$stmt = $conexion->prepare($sql);
$resultados = [];
$total      = 0;

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultados = $stmt->get_result();
    $total      = $resultados->num_rows;
    $stmt->close();
}

// -------------------------------------------------------------------
// 3. OUTPUT — SOLO HTML DE RESULTADOS (sin layout)
// -------------------------------------------------------------------

if ($total > 0): ?>
<div class="d-flex justify-content-between align-items-center mb-3" id="results-header">
    <span class="text-muted small" id="results-count">
        <?php echo $total; ?> himno(s) encontrado(s)
    </span>
    <button type="button" class="view-toggle-btn" id="view-toggle"
            title="Cambiar vista" onclick="Himnario.toggleView()">
        <span id="view-toggle-icon">☰</span>
        <span id="view-toggle-text">Lista</span>
    </button>
</div>
<div class="row g-3" id="results-container">
    <?php while ($fila = $resultados->fetch_assoc()): ?>
    <div class="col-12 col-md-6 col-lg-4">
        <a href="presentacion.php?id=<?php echo (int) $fila['id']; ?>"
           class="himno-card text-decoration-none h-100">
            <span class="himno-numero"><?php echo (int) $fila['numero_oficial']; ?></span>
            <div class="flex-grow-1">
                <h3 class="himno-titulo mb-1">
                    <?php echo sanitizar($fila['titulo_principal']); ?>
                </h3>
                <div class="himno-metadata">
                    <!-- Tipo -->
                    <span class="badge-categoria" data-categoria="<?php
                        $t = (int) $fila['tipo'];
                        echo $t === 1 ? 'himno-oficial' : ($t === 2 ? 'himno-inspirado' : 'convencion');
                    ?>"><?php echo tipoHimnoTexto($fila['tipo']); ?></span>

                    <!-- Tonalidad -->
                    <?php if (!empty($fila['tonalidad_original'])): ?>
                    <span class="badge-tonalidad">
                        <?php echo sanitizar($fila['tonalidad_original']); ?>
                    </span>
                    <?php endif; ?>

                    <!-- País -->
                    <?php if (!empty($fila['pais_nombre'])): ?>
                    <span class="badge-pais">
                        <?php echo sanitizar($fila['pais_nombre']); ?>
                    </span>
                    <?php endif; ?>

                    <!-- Categorías -->
                    <?php if (!empty($fila['categorias'])):
                        $cats = explode(', ', $fila['categorias']);
                        foreach ($cats as $cat): ?>
                        <span class="badge bg-secondary">
                            <?php echo sanitizar($cat); ?>
                        </span>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>
    <?php endwhile; ?>
</div>

<?php elseif ($busqueda !== '' || $categoria_filtro > 0 || $tipo_filtro > 0 || $tonalidad_filtro !== ''): ?>
<div class="empty-state" id="results-container" data-empty="not-found">
    <div class="empty-icon">😕</div>
    <div class="empty-title">No encontramos nada</div>
    <p class="text-muted">
        Intenta buscar con otra palabra o ajusta los filtros.
    </p>
    <a href="index.php" class="btn btn-outline-primary">Limpiar filtros</a>
</div>

<?php else: ?>
<div class="empty-state" id="results-container" data-empty="welcome">
    <div class="empty-icon">📖</div>
    <div class="empty-title">Bienvenido al Himnario Digital</div>
    <p class="text-muted">
        Escribe un término de búsqueda o selecciona filtros para comenzar.
    </p>
</div>
<?php endif; ?>
