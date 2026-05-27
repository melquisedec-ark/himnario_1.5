<?php
/**
 * index.php - Buscador público de himnos
 * Búsqueda por título, número, contenido de estrofas
 * Filtros: categoría, tipo, tonalidad
 */
require_once 'includes/db.php';
require_once 'includes/funciones.php';

// Estadísticas para el hero section
$total_himnos = 0;
$total_categorias = 0;
$total_paises = 0;
$res_h = $conexion->query("SELECT COUNT(*) as total FROM himnos WHERE activo = 1");
if ($res_h) { $total_himnos = (int)$res_h->fetch_assoc()['total']; }
$res_c = $conexion->query("SELECT COUNT(*) as total FROM categorias");
if ($res_c) { $total_categorias = (int)$res_c->fetch_assoc()['total']; }
$res_p = $conexion->query("SELECT COUNT(DISTINCT pais_id) as total FROM versiones_pais");
if ($res_p) { $total_paises = (int)$res_p->fetch_assoc()['total']; }

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
$sql .= " GROUP BY h.id";
$sql .= " ORDER BY h.tipo ASC, h.numero_oficial ASC";

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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Patua+One&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1a5276">
    <script>
        // Aplicar tema guardado antes de renderizar (evita flash)
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

<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">📖 Himnario Seleccionado</a>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-primary d-none d-md-inline">v1.5</span>
            <!-- Theme variant dots -->
            <div class="dropdown d-none d-md-inline-block">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center gap-1" id="theme-variant-btn" data-bs-toggle="dropdown" aria-expanded="false" style="gap:4px;padding:4px 10px;">
                    <span class="theme-dot" data-theme="blue" style="width:14px;height:14px;border-width:1px;"></span>
                    <span style="font-size:0.75rem;">Azul</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end p-2" aria-labelledby="theme-variant-btn" style="min-width:200px;">
                    <li><span class="dropdown-header small text-muted">Tema de Gradiente</span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="#" onclick="setThemeVariant('blue', this.querySelector('.theme-dot'));return false;">
                        <span class="theme-dot active" data-theme="blue"></span> Azul</a></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="#" onclick="setThemeVariant('purple', this.querySelector('.theme-dot'));return false;">
                        <span class="theme-dot" data-theme="purple"></span> Púrpura</a></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="#" onclick="setThemeVariant('green', this.querySelector('.theme-dot'));return false;">
                        <span class="theme-dot" data-theme="green"></span> Verde</a></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="#" onclick="setThemeVariant('orange', this.querySelector('.theme-dot'));return false;">
                        <span class="theme-dot" data-theme="orange"></span> Naranja</a></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="#" onclick="setThemeVariant('pink', this.querySelector('.theme-dot'));return false;">
                        <span class="theme-dot" data-theme="pink"></span> Rosa</a></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="#" onclick="setThemeVariant('cyan', this.querySelector('.theme-dot'));return false;">
                        <span class="theme-dot" data-theme="cyan"></span> Cian</a></li>
                </ul>
            </div>
            <button class="btn btn-outline-secondary btn-sm" onclick="Himnario.toggleTheme()">🌗 Tema</button>
            <a href="admin/index.php" class="btn btn-outline-primary btn-sm">Admin</a>
        </div>
    </div>
</nav>

<div class="container search-container">

    <!-- Hero Section -->
    <div class="hero-section text-center py-5 mb-4">
        <div class="container position-relative">
            <h1 class="display-4 fw-bold">📖 Himnario Seleccionado</h1>
            <p class="lead mb-4">Una colección de himnos para la alabanza y adoración</p>
            <div class="d-flex justify-content-center gap-4 gap-md-5 flex-wrap">
                <div class="hero-stat">
                    <span class="hero-stat-value"><?php echo $total_himnos; ?></span>
                    <span class="hero-stat-label">Himnos</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-value"><?php echo $total_categorias; ?></span>
                    <span class="hero-stat-label">Categorías</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-value"><?php echo $total_paises; ?></span>
                    <span class="hero-stat-label">Países</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros de búsqueda con pills -->
    <form action="index.php" method="GET" class="search-filters mb-4" id="filtros-form">
        <div class="filter-group" style="grid-column: 1 / -1;">
            <label for="q">Buscar</label>
            <input type="text" name="q" id="q" class="form-control"
                   placeholder="Escribe aquí... (Ej: 'Es mi rey' o '15')"
                   value="<?php echo sanitizar($busqueda); ?>" autofocus>
        </div>

        <!-- Hidden inputs para mantener valores de filtros -->
        <input type="hidden" name="categoria" id="input-categoria" value="<?php echo $categoria_filtro; ?>">
        <input type="hidden" name="tipo" id="input-tipo" value="<?php echo $tipo_filtro; ?>">
        <input type="hidden" name="tonalidad" id="input-tonalidad" value="<?php echo sanitizar($tonalidad_filtro); ?>">

        <div class="filter-group">
            <label>Categoría</label>
            <div class="filter-pills-group" data-filter="categoria">
                <button type="button" class="filter-pill <?php echo $categoria_filtro === 0 ? 'active' : ''; ?>" data-value="0">Todas</button>
                <?php if ($categorias): $categorias->data_seek(0); while($cat = $categorias->fetch_assoc()): ?>
                    <button type="button" class="filter-pill <?php echo $categoria_filtro === (int)$cat['id'] ? 'active' : ''; ?>" data-value="<?php echo (int)$cat['id']; ?>">
                        <?php echo sanitizar($cat['nombre']); ?>
                    </button>
                <?php endwhile; endif; ?>
            </div>
        </div>

        <div class="filter-group">
            <label>Tipo de Himno</label>
            <div class="filter-pills-group" data-filter="tipo">
                <button type="button" class="filter-pill <?php echo $tipo_filtro === 0 ? 'active' : ''; ?>" data-value="0">Todos</button>
                <button type="button" class="filter-pill <?php echo $tipo_filtro === 1 ? 'active' : ''; ?>" data-value="1">Oficial</button>
                <button type="button" class="filter-pill <?php echo $tipo_filtro === 2 ? 'active' : ''; ?>" data-value="2">Inspirada</button>
                <button type="button" class="filter-pill <?php echo $tipo_filtro === 3 ? 'active' : ''; ?>" data-value="3">Convención</button>
            </div>
        </div>

        <div class="filter-group">
            <label>Tonalidad</label>
            <div class="filter-pills-group" data-filter="tonalidad">
                <button type="button" class="filter-pill <?php echo $tonalidad_filtro === '' ? 'active' : ''; ?>" data-value="">Todas</button>
                <?php if ($tonalidades_list): $tonalidades_list->data_seek(0); while($ton = $tonalidades_list->fetch_assoc()): ?>
                    <button type="button" class="filter-pill <?php echo $tonalidad_filtro === $ton['tonalidad_original'] ? 'active' : ''; ?>" data-value="<?php echo sanitizar($ton['tonalidad_original']); ?>">
                        <?php echo sanitizar($ton['tonalidad_original']); ?>
                    </button>
                <?php endwhile; endif; ?>
            </div>
        </div>

        <div class="filter-group d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">🔍 Buscar</button>
        </div>
    </form>

    <!-- Resultados -->
    <?php if ($total > 0): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted small"><?php echo $total; ?> himno(s) encontrado(s)</span>
            <button type="button" class="view-toggle-btn" id="view-toggle" title="Cambiar vista" onclick="toggleView()">
                <span id="view-toggle-icon">☰</span>
                <span id="view-toggle-text">Lista</span>
            </button>
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

        // Inicializar tema visual guardado
        var savedTheme = localStorage.getItem('himnario_theme_variant');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
            var dot = document.querySelector('.theme-dot[data-theme="' + savedTheme + '"]');
            if (dot) dot.classList.add('active');
        }

        // Pills de filtro: toggle y submit
        document.querySelectorAll('.filter-pills-group').forEach(function(group) {
            var filterName = group.dataset.filter;
            group.querySelectorAll('.filter-pill').forEach(function(pill) {
                pill.addEventListener('click', function() {
                    // Desactivar todos los pills de este grupo
                    group.querySelectorAll('.filter-pill').forEach(function(p) {
                        p.classList.remove('active');
                    });
                    // Activar el pill seleccionado
                    this.classList.add('active');
                    // Actualizar hidden input
                    var hiddenInput = document.getElementById('input-' + filterName);
                    if (hiddenInput) {
                        hiddenInput.value = this.dataset.value;
                    }
                    // Auto-submit del formulario
                    document.getElementById('filtros-form').submit();
                });
            });
        });
    });

    // Toggle vista grid/lista
    function toggleView() {
        var container = document.getElementById('results-container');
        var btn = document.getElementById('view-toggle');
        var icon = document.getElementById('view-toggle-icon');
        var text = document.getElementById('view-toggle-text');
        container.classList.toggle('view-list');
        var isList = container.classList.contains('view-list');
        icon.textContent = isList ? '⊞' : '☰';
        text.textContent = isList ? 'Grid' : 'Lista';
        btn.classList.toggle('active', isList);
        localStorage.setItem('himnario_view_mode', isList ? 'list' : 'grid');
    }

    // Restaurar vista guardada
    document.addEventListener('DOMContentLoaded', function() {
        var savedView = localStorage.getItem('himnario_view_mode');
        if (savedView === 'list') {
            toggleView();
        }
    });

    // Theme variant selector
    var THEME_NAMES = { blue: 'Azul', purple: 'Púrpura', green: 'Verde', orange: 'Naranja', pink: 'Rosa', cyan: 'Cian' };
    function setThemeVariant(theme, el) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('himnario_theme_variant', theme);
        document.querySelectorAll('.theme-dot').forEach(function(d) { d.classList.remove('active'); });
        if (el) el.classList.add('active');
        // Feedback visual
        var btn = document.getElementById('theme-variant-btn');
        if (btn) {
            var spans = btn.querySelectorAll('span');
            if (spans.length >= 2) {
                spans[1].textContent = THEME_NAMES[theme] || theme;
            }
        }
        if (window.Himnario && Himnario.UIUtils) {
            Himnario.UIUtils.showToast('🎨 Tema: ' + (THEME_NAMES[theme] || theme), 'success', 1500);
        }
    }

    // Service Worker (PWA)
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js')
            .then(function() { console.log('PWA Ready'); })
            .catch(function(err) { console.log('PWA Error', err); });
    }
</script>
</body>
</html>
