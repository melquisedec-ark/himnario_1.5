<?php
/**
 * admin/editar.php - Editar himno existente
 * Precarga datos y usa transacción DELETE+INSERT para actualizar
 */
require_once '../includes/db.php';
require_once '../includes/funciones.php';
verificarSesion();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$error = "";

// Cargar datos actuales del himno
$stmt = $conexion->prepare("SELECT * FROM himnos WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$himno = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$himno) {
    header("Location: index.php");
    exit;
}

// Cargar versiones existentes
$versiones_existentes = obtenerVersiones($conexion, $id);

// Cargar estrofas (de la primera versión activa, o la primera disponible)
$version_principal_id = 0;
if (!empty($versiones_existentes)) {
    // Buscar la primera activa
    foreach ($versiones_existentes as $v) {
        if ((int)$v['activo'] === 1) {
            $version_principal_id = (int)$v['id'];
            break;
        }
    }
    // Si no hay activa, usar la primera
    if ($version_principal_id === 0) {
        $version_principal_id = (int)$versiones_existentes[0]['id'];
    }
}

$estrofas_existentes = [];
if ($version_principal_id > 0) {
    $estrofas_existentes = obtenerEstrofas($conexion, $version_principal_id);
}

// Cargar categorías del himno
$categorias_del_himno = [];
$stmt_cat = $conexion->prepare("SELECT categoria_id FROM himno_categoria WHERE himno_id = ?");
$stmt_cat->bind_param("i", $id);
$stmt_cat->execute();
$res_cat = $stmt_cat->get_result();
while ($row = $res_cat->fetch_assoc()) {
    $categorias_del_himno[] = (int)$row['categoria_id'];
}
$stmt_cat->close();

// Cargar listas para formulario
$paises = $conexion->query("SELECT id, nombre, codigo FROM paises ORDER BY nombre ASC");
$categorias_list = $conexion->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo_principal = trim($_POST['titulo_principal'] ?? '');
    $numero_oficial = (int)($_POST['numero_oficial'] ?? 0);
    $tipo = (int)($_POST['tipo'] ?? 1);
    $evento = trim($_POST['evento'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    $paises_version = $_POST['pais_id'] ?? [];
    $tonalidades = $_POST['tonalidad'] ?? [];
    $categorias_seleccionadas = $_POST['categorias'] ?? [];
    $tipos_estrofa = $_POST['tipo_estrofa'] ?? [];
    $contenidos_estrofa = $_POST['contenido_estrofa'] ?? [];

    if ($titulo_principal === '') {
        $error = "El título del himno es obligatorio.";
    } elseif ($numero_oficial <= 0) {
        $error = "El número oficial debe ser un valor positivo.";
    } elseif (empty($tipos_estrofa) || count($contenidos_estrofa) === 0) {
        $error = "Debe agregar al menos una estrofa.";
    } elseif (empty($paises_version)) {
        $error = "Debe agregar al menos una versión por país.";
    } else {
        // Verificar que no haya duplicado de número (excluyendo este himno)
        $check = $conexion->prepare("SELECT id FROM himnos WHERE numero_oficial = ? AND id != ? LIMIT 1");
        $check->bind_param("ii", $numero_oficial, $id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "El himno número $numero_oficial ya existe en otro registro.";
        } else {
            // INICIAR TRANSACCIÓN
            $conexion->begin_transaction();
            try {
                // 1. UPDATE himno
                $stmt_himno = $conexion->prepare(
                    "UPDATE himnos SET titulo_principal = ?, numero_oficial = ?, tipo = ?, evento = ?, activo = ? WHERE id = ?"
                );
                $stmt_himno->bind_param("siisii", $titulo_principal, $numero_oficial, $tipo, $evento, $activo, $id);
                $stmt_himno->execute();
                $stmt_himno->close();

                // 2. DELETE + INSERT versiones_pais y estrofas
                // Obtener versiones actuales para borrar estrofas en cascada
                $vp_ids = [];
                $res_vp = $conexion->query("SELECT id FROM versiones_pais WHERE himno_id = $id");
                while ($row = $res_vp->fetch_assoc()) {
                    $vp_ids[] = (int)$row['id'];
                }

                // Borrar estrofas de todas las versiones
                if (!empty($vp_ids)) {
                    $vp_list = implode(',', $vp_ids);
                    $conexion->query("DELETE FROM estrofas WHERE version_pais_id IN ($vp_list)");
                }

                // Borrar versiones_pais
                $conexion->query("DELETE FROM versiones_pais WHERE himno_id = $id");

                // Insertar nuevas versiones y estrofas
                $stmt_vp = $conexion->prepare(
                    "INSERT INTO versiones_pais (himno_id, pais_id, tonalidad_original, activo) VALUES (?, ?, ?, 1)"
                );
                $stmt_estrofa = $conexion->prepare(
                    "INSERT INTO estrofas (version_pais_id, tipo, orden, contenido) VALUES (?, ?, ?, ?)"
                );

                foreach ($paises_version as $idx => $pais_id) {
                    $pais_id_int = (int)$pais_id;
                    $tonalidad = trim($tonalidades[$idx] ?? '');

                    $stmt_vp->bind_param("iis", $id, $pais_id_int, $tonalidad);
                    $stmt_vp->execute();
                    $version_pais_id = $conexion->insert_id;

                    foreach ($tipos_estrofa as $orden => $tipo_estrofa) {
                        $contenido = $contenidos_estrofa[$orden] ?? '';
                        if (trim($contenido) === '') continue;
                        $orden_num = $orden + 1;
                        $stmt_estrofa->bind_param("isis", $version_pais_id, $tipo_estrofa, $orden_num, $contenido);
                        $stmt_estrofa->execute();
                    }
                }
                $stmt_vp->close();
                $stmt_estrofa->close();

                // 3. DELETE + INSERT categorías
                $conexion->query("DELETE FROM himno_categoria WHERE himno_id = $id");
                if (!empty($categorias_seleccionadas)) {
                    $stmt_cat = $conexion->prepare("INSERT INTO himno_categoria (himno_id, categoria_id) VALUES (?, ?)");
                    foreach ($categorias_seleccionadas as $cat_id) {
                        $cat_id_int = (int)$cat_id;
                        $stmt_cat->bind_param("ii", $id, $cat_id_int);
                        $stmt_cat->execute();
                    }
                    $stmt_cat->close();
                }

                $conexion->commit();
                header("Location: index.php?msg=actualizado");
                exit;
            } catch (Exception $e) {
                $conexion->rollback();
                $error = "Error al actualizar: " . $e->getMessage();
            }
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Himno - Himnario Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Patua+One&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
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
    <style>
        .estrofa-box {
            background-color: var(--bs-tertiary-bg);
            border: 1px solid var(--bs-border-color);
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            position: relative;
        }
        .version-box {
            background-color: var(--bs-tertiary-bg);
            border: 1px solid var(--bs-border-color);
            padding: 12px 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            position: relative;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .categoria-checkbox { margin-right: 8px; }
        .categoria-label { display: inline-block; margin-right: 16px; margin-bottom: 8px; }
    </style>
</head>
<body>

<div class="container mt-4 mb-5">
    <?php echo generarBreadcrumbs(['Inicio' => '../index.php', 'Panel Admin' => 'index.php', 'Editar: ' . $himno['titulo_principal'] => null]); ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>✏️ Editar: <?php echo sanitizar($himno['titulo_principal']); ?></h2>
        <div class="d-flex gap-2 align-items-center">
            <!-- Theme variant dots -->
            <div class="dropdown d-none d-md-inline-block">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center gap-1" id="theme-variant-btn" data-bs-toggle="dropdown" aria-expanded="false" style="gap:4px;padding:4px 10px;">
                    <span class="theme-dot" data-theme="blue" style="width:14px;height:14px;border-width:1px;"></span>
                    <span style="font-size:0.75rem;">Azul</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end p-2" aria-labelledby="theme-variant-btn" style="min-width:200px;">
                    <li><span class="dropdown-header small text-muted">Tema de Gradiente</span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="#" onclick="setThemeVariant('blue', this.querySelector('.theme-dot'));return false;"><span class="theme-dot active" data-theme="blue"></span> Azul</a></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="#" onclick="setThemeVariant('purple', this.querySelector('.theme-dot'));return false;"><span class="theme-dot" data-theme="purple"></span> Púrpura</a></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="#" onclick="setThemeVariant('green', this.querySelector('.theme-dot'));return false;"><span class="theme-dot" data-theme="green"></span> Verde</a></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="#" onclick="setThemeVariant('orange', this.querySelector('.theme-dot'));return false;"><span class="theme-dot" data-theme="orange"></span> Naranja</a></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="#" onclick="setThemeVariant('pink', this.querySelector('.theme-dot'));return false;"><span class="theme-dot" data-theme="pink"></span> Rosa</a></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="#" onclick="setThemeVariant('cyan', this.querySelector('.theme-dot'));return false;"><span class="theme-dot" data-theme="cyan"></span> Cian</a></li>
                </ul>
            </div>
            <button class="btn btn-outline-secondary btn-sm" onclick="Himnario.toggleTheme()">🌗 Tema</button>
            <a href="index.php" class="btn btn-outline-primary btn-sm">Volver</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo sanitizar($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" class="admin-form" id="form-himno">
                <!-- SECCIÓN 1: DATOS DEL HIMNO -->
                <div class="form-section">
                    <h5 class="mb-3">📄 Datos del Himno</h5>
                    <div class="form-row">
                        <div>
                            <label class="form-label">Número Oficial *</label>
                            <input type="number" name="numero_oficial" class="form-control" required
                                   value="<?php echo (int)$himno['numero_oficial']; ?>">
                        </div>
                        <div>
                            <label class="form-label">Título Principal *</label>
                            <input type="text" name="titulo_principal" class="form-control" required
                                   value="<?php echo sanitizar($himno['titulo_principal']); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select">
                                <option value="1" <?php echo (int)$himno['tipo'] === 1 ? 'selected' : ''; ?>>Oficial</option>
                                <option value="2" <?php echo (int)$himno['tipo'] === 2 ? 'selected' : ''; ?>>Inspirada</option>
                                <option value="3" <?php echo (int)$himno['tipo'] === 3 ? 'selected' : ''; ?>>Convención</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Evento</label>
                            <input type="text" name="evento" class="form-control"
                                   value="<?php echo sanitizar($himno['evento'] ?? ''); ?>"
                                   placeholder="Ej: Convención Nacional 2024">
                        </div>
                    </div>
                    <div class="form-check mt-2">
                        <input type="checkbox" name="activo" id="activo" class="form-check-input" value="1"
                               <?php echo (int)$himno['activo'] === 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="activo">Himno activo</label>
                    </div>
                </div>

                <!-- SECCIÓN 2: VERSIONES POR PAÍS -->
                <div class="form-section">
                    <h5 class="mb-3">🌍 Versiones por País</h5>
                    <p class="form-help-text">Las estrofas se aplicarán a todas las versiones.</p>
                    <div id="versiones-container">
                        <!-- Las versiones existentes se cargan vía JS -->
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="agregarVersion()">+ Agregar Versión</button>
                </div>

                <!-- SECCIÓN 3: CATEGORÍAS -->
                <div class="form-section">
                    <h5 class="mb-3">🏷️ Categorías</h5>
                    <div>
                        <?php if ($categorias_list && $categorias_list->num_rows > 0):
                            $categorias_list->data_seek(0);
                            while($cat = $categorias_list->fetch_assoc()): ?>
                                <label class="categoria-label">
                                    <input type="checkbox" name="categorias[]" value="<?php echo (int)$cat['id']; ?>"
                                           class="categoria-checkbox"
                                           <?php echo in_array((int)$cat['id'], $categorias_del_himno) ? 'checked' : ''; ?>>
                                    <?php echo sanitizar($cat['nombre']); ?>
                                </label>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-muted small">No hay categorías disponibles.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SECCIÓN 4: ESTROFAS -->
                <div class="form-section">
                    <h5 class="mb-3">📝 Estrofas</h5>
                    <p class="form-help-text">Use los botones para agregar/quitar estrofas.</p>
                    <div id="estrofas-container">
                        <!-- Las estrofas existentes se cargan vía JS -->
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button type="button" class="btn btn-outline-primary" onclick="Himnario.EstrofaManager.add('estrofa')">+ Estrofa</button>
                        <button type="button" class="btn btn-outline-warning" onclick="Himnario.EstrofaManager.add('coro')">+ Coro</button>
                        <button type="button" class="btn btn-outline-info" onclick="Himnario.EstrofaManager.add('puente')">+ Puente</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="Himnario.EstrofaManager.add('intro')">+ Intro</button>
                        <button type="button" class="btn btn-outline-danger" onclick="Himnario.EstrofaManager.add('final')">+ Final</button>
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">💾 Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Datos para precarga
    const PAISES = <?php
        $paises->data_seek(0);
        $paises_data = [];
        while($p = $paises->fetch_assoc()) {
            $paises_data[] = ['id' => (int)$p['id'], 'nombre' => $p['nombre']];
        }
        echo json_encode($paises_data);
    ?>;

    const VERSIONES_EXISTENTES = <?php
        // Re-cargar versiones por si POST cambió algo (aunque en GET estamos bien)
        $versiones = obtenerVersiones($conexion, $id);
        echo json_encode($versiones);
    ?>;

    const ESTROFAS_EXISTENTES = <?php
        $estrofas = [];
        if ($version_principal_id > 0) {
            $estrofas = obtenerEstrofas($conexion, $version_principal_id);
        }
        echo json_encode($estrofas);
    ?>;

    let versionCount = 0;

    // Datos para EstrofaManager (evita duplicado con app.js)
    var dataEl = document.getElementById('estrofas-data');
    if (!dataEl) {
        dataEl = document.createElement('script');
        dataEl.id = 'estrofas-data';
        dataEl.type = 'application/json';
        dataEl.textContent = JSON.stringify(ESTROFAS_EXISTENTES);
        document.body.appendChild(dataEl);
    }

    function agregarVersion(paisId, tonalidad) {
        versionCount++;
        const container = document.getElementById('versiones-container');
        const div = document.createElement('div');
        div.className = 'version-box';

        let options = '<option value="">Seleccionar país...</option>';
        PAISES.forEach(function(p) {
            const selected = (paisId && parseInt(paisId) === p.id) ? 'selected' : '';
            options += '<option value="' + p.id + '" ' + selected + '>' + p.nombre + '</option>';
        });

        div.innerHTML = `
            <select name="pais_id[]" class="form-select form-select-sm" style="flex:1;min-width:180px" required>
                ${options}
            </select>
            <input type="text" name="tonalidad[]" class="form-control form-control-sm" style="width:120px"
                   placeholder="Tonalidad" value="${tonalidad || ''}">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.version-box').remove()">✕</button>
        `;
        container.appendChild(div);
    }

    // Cargar versiones existentes
    if (VERSIONES_EXISTENTES && VERSIONES_EXISTENTES.length > 0) {
        VERSIONES_EXISTENTES.forEach(function(v) {
            agregarVersion(v.pais_id, v.tonalidad_original || '');
        });
    } else {
        agregarVersion();
    }

    // Función para mover estrofas arriba/abajo
    window.moverEstrofa = function(btn, direccion) {
        const box = btn.closest('.estrofa-box');
        const container = document.getElementById('estrofas-container');
        if (!box || !container) return;

        if (direccion === -1 && box.previousElementSibling) {
            container.insertBefore(box, box.previousElementSibling);
        } else if (direccion === 1 && box.nextElementSibling) {
            container.insertBefore(box.nextElementSibling, box);
        } else {
            return;
        }

        const boxes = container.querySelectorAll('.estrofa-box');
        boxes.forEach(function(b, i) {
            var badge = b.querySelector('.badge.bg-dark');
            if (badge) badge.textContent = '#' + (i + 1);
        });
    };

    document.addEventListener('DOMContentLoaded', function() {
        Himnario.initPage('admin-editar');

        // Restaurar tema visual
        var savedTheme = localStorage.getItem('himnario_theme_variant');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
            var dot = document.querySelector('.theme-dot[data-theme="' + savedTheme + '"]');
            if (dot) dot.classList.add('active');
        }
    });

    var THEME_NAMES = { blue: 'Azul', purple: 'Púrpura', green: 'Verde', orange: 'Naranja', pink: 'Rosa', cyan: 'Cian' };
    function setThemeVariant(theme, el) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('himnario_theme_variant', theme);
        document.querySelectorAll('.theme-dot').forEach(function(d) { d.classList.remove('active'); });
        if (el) el.classList.add('active');
        var btn = document.getElementById('theme-variant-btn');
        if (btn) {
            var spans = btn.querySelectorAll('span');
            if (spans.length >= 2) spans[1].textContent = THEME_NAMES[theme] || theme;
        }
    }
</script>
<script src="../js/app.js" defer></script>
</body>
</html>
