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

<div class="container search-container py-3">

    <!-- Filtros simplificados: search + tipo pills compacto -->
    <form action="index.php" method="GET" class="search-filters-simple mb-3" id="filtros-form">
        <div class="d-flex flex-column gap-2">
            <input type="text" name="q" id="q" class="form-control form-control-lg"
                   placeholder="Busca himno por título o número..."
                   value="<?php echo sanitizar($busqueda); ?>" autofocus>

            <input type="hidden" name="categoria" id="input-categoria" value="<?php echo $categoria_filtro; ?>">
            <input type="hidden" name="tipo" id="input-tipo" value="<?php echo $tipo_filtro; ?>">
            <input type="hidden" name="tonalidad" id="input-tonalidad" value="<?php echo sanitizar($tonalidad_filtro); ?>">

            <div class="filter-pills-group justify-content-center" data-filter="tipo">
                <button type="button" class="filter-pill <?php echo $tipo_filtro === 0 ? 'active' : ''; ?>" data-value="0">Todos</button>
                <button type="button" class="filter-pill <?php echo $tipo_filtro === 1 ? 'active' : ''; ?>" data-value="1">Oficial</button>
                <button type="button" class="filter-pill <?php echo $tipo_filtro === 2 ? 'active' : ''; ?>" data-value="2">Inspirada</button>
                <button type="button" class="filter-pill <?php echo $tipo_filtro === 3 ? 'active' : ''; ?>" data-value="3">Convención</button>
            </div>
        </div>
    </form>

    <!-- Resultados (wrapper para reemplazo AJAX) -->
    <div id="search-results-wrapper" class="results-section"
         style="transition: opacity 0.3s ease, transform 0.3s ease;">
    <?php if ($total > 0): ?>
        <div class="d-flex justify-content-between align-items-center mb-3" id="results-header">
            <span class="text-muted small" id="results-count"><?php echo $total; ?> himno(s) encontrado(s)</span>
            <button type="button" class="view-toggle-btn" id="view-toggle" title="Cambiar vista" onclick="Himnario.toggleView()">
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
        <div class="empty-state" id="results-container" data-empty="not-found">
            <div class="empty-icon">😕</div>
            <div class="empty-title">No encontramos nada</div>
            <p class="text-muted">Intenta buscar con otra palabra o ajusta los filtros.</p>
            <a href="index.php" class="btn btn-outline-primary">Limpiar filtros</a>
        </div>
    <?php else: ?>
        <div class="empty-state" id="results-container" data-empty="welcome">
            <div class="empty-icon">📖</div>
            <div class="empty-title">Bienvenido al Himnario Digital</div>
            <p class="text-muted">Escribe un término de búsqueda o selecciona filtros para comenzar.</p>
        </div>
    <?php endif; ?>
    </div>
</div>

<script src="js/app.js" defer></script>
<script>
    // ====================================================================
    // Toggle vista grid/lista (función GLOBAL expuesta para onclick en HTML)
    // ====================================================================
    window.toggleViewLegacy = function() {
        var container = document.getElementById('results-container');
        if (!container) return;
        var btn = document.getElementById('view-toggle');
        var icon = document.getElementById('view-toggle-icon');
        var text = document.getElementById('view-toggle-text');
        if (!btn || !icon || !text) return;
        container.classList.toggle('view-list');
        var isList = container.classList.contains('view-list');
        icon.textContent = isList ? '⊞' : '☰';
        text.textContent = isList ? 'Grid' : 'Lista';
        btn.classList.toggle('active', isList);
        localStorage.setItem('himnario_view_mode', isList ? 'list' : 'grid');
    };

    // Theme variant names
    var THEME_NAMES = { blue: 'Azul', purple: 'Púrpura', green: 'Verde', orange: 'Naranja', pink: 'Rosa', cyan: 'Cian' };

    // Theme variant selector (global para onclick)
    window.setThemeVariant = function(theme, el) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('himnario_theme_variant', theme);
        document.querySelectorAll('.theme-dot').forEach(function(d) { d.classList.remove('active'); });
        if (el) el.classList.add('active');
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
    };

    // ====================================================================
    // Módulo de búsqueda AJAX (se integra con Himnario.LiveSearch)
    // ====================================================================
    document.addEventListener('DOMContentLoaded', function() {
        Himnario.initPage('index');

        // Inicializar tema visual guardado
        var savedTheme = localStorage.getItem('himnario_theme_variant');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
            var dot = document.querySelector('.theme-dot[data-theme="' + savedTheme + '"]');
            if (dot) dot.classList.add('active');
        }

        // ---- Pills de filtro: AJAX en lugar de submit ----
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
                    // Búsqueda AJAX en lugar de submit
                    if (window.Himnario && Himnario.LiveSearch) {
                        Himnario.LiveSearch.triggerSearch();
                    } else {
                        // Fallback si LiveSearch no está cargado
                        document.getElementById('filtros-form').submit();
                    }
                });
            });
        });

        // ---- Interceptar submit del formulario ----
        var filtrosForm = document.getElementById('filtros-form');
        if (filtrosForm) {
            filtrosForm.addEventListener('submit', function(e) {
                e.preventDefault();
                if (window.Himnario && Himnario.LiveSearch) {
                    var qInput = document.querySelector('input[name="q"]');
                    if (qInput) {
                        Himnario.LiveSearch.search(qInput.value.trim());
                    } else {
                        Himnario.LiveSearch.triggerSearch();
                    }
                } else {
                    // Fallback: submit normal
                    this.submit();
                }
            });
        }

        // ---- Restaurar vista guardada ----
        var savedView = localStorage.getItem('himnario_view_mode');
        if (savedView === 'list') {
            // Pequeño retardo para asegurar que el DOM de resultados existe
            setTimeout(function() {
                window.toggleViewLegacy();
            }, 50);
        }
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
