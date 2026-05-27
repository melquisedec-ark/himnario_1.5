<?php
/**
 * admin/crear.php - Crear nuevo himno
 * Formulario completo con transacción: himno + versiones_pais + estrofas + categorías
 */
require_once '../includes/db.php';
require_once '../includes/funciones.php';
verificarSesion();

$error = "";
$exito = "";

// Cargar países y categorías para los selectores
$paises = $conexion->query("SELECT id, nombre, codigo FROM paises ORDER BY nombre ASC");
$categorias_list = $conexion->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoger datos del formulario
    $titulo_principal = trim($_POST['titulo_principal'] ?? '');
    $numero_oficial = (int)($_POST['numero_oficial'] ?? 0);
    $tipo = (int)($_POST['tipo'] ?? 1);
    $evento = trim($_POST['evento'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    // Versiones por país
    $paises_version = $_POST['pais_id'] ?? [];
    $tonalidades = $_POST['tonalidad'] ?? [];

    // Categorías seleccionadas
    $categorias_seleccionadas = $_POST['categorias'] ?? [];

    // Estrofas
    $tipos_estrofa = $_POST['tipo_estrofa'] ?? [];
    $contenidos_estrofa = $_POST['contenido_estrofa'] ?? [];

    // Validaciones básicas
    if ($titulo_principal === '') {
        $error = "El título del himno es obligatorio.";
    } elseif ($numero_oficial <= 0) {
        $error = "El número oficial debe ser un valor positivo.";
    } elseif (empty($tipos_estrofa) || count($contenidos_estrofa) === 0) {
        $error = "Debe agregar al menos una estrofa.";
    } elseif (empty($paises_version)) {
        $error = "Debe agregar al menos una versión por país.";
    } else {
        // Verificar que no exista ya el número
        $check = $conexion->prepare("SELECT id FROM himnos WHERE numero_oficial = ? LIMIT 1");
        $check->bind_param("i", $numero_oficial);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "El himno número $numero_oficial ya existe.";
        } else {
            // INICIAR TRANSACCIÓN
            $conexion->begin_transaction();
            try {
                // 1. INSERT himno
                $stmt_himno = $conexion->prepare(
                    "INSERT INTO himnos (titulo_principal, numero_oficial, tipo, evento, activo) VALUES (?, ?, ?, ?, ?)"
                );
                $stmt_himno->bind_param("siisi", $titulo_principal, $numero_oficial, $tipo, $evento, $activo);
                $stmt_himno->execute();
                $himno_id = $conexion->insert_id;
                $stmt_himno->close();

                // 2. INSERT versiones_pais y estrofas
                $stmt_vp = $conexion->prepare(
                    "INSERT INTO versiones_pais (himno_id, pais_id, tonalidad_original, activo) VALUES (?, ?, ?, 1)"
                );
                $stmt_estrofa = $conexion->prepare(
                    "INSERT INTO estrofas (version_pais_id, tipo, orden, contenido) VALUES (?, ?, ?, ?)"
                );

                foreach ($paises_version as $idx => $pais_id) {
                    $pais_id_int = (int)$pais_id;
                    $tonalidad = trim($tonalidades[$idx] ?? '');

                    $stmt_vp->bind_param("iis", $himno_id, $pais_id_int, $tonalidad);
                    $stmt_vp->execute();
                    $version_pais_id = $conexion->insert_id;

                    // Insertar estrofas para esta versión
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

                // 3. INSERT himno_categoria
                if (!empty($categorias_seleccionadas)) {
                    $stmt_cat = $conexion->prepare("INSERT INTO himno_categoria (himno_id, categoria_id) VALUES (?, ?)");
                    foreach ($categorias_seleccionadas as $cat_id) {
                        $cat_id_int = (int)$cat_id;
                        $stmt_cat->bind_param("ii", $himno_id, $cat_id_int);
                        $stmt_cat->execute();
                    }
                    $stmt_cat->close();
                }

                $conexion->commit();
                header("Location: index.php?msg=creado");
                exit;
            } catch (Exception $e) {
                $conexion->rollback();
                $error = "Error al guardar: " . $e->getMessage();
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
    <title>Nuevo Himno - Himnario Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .version-box .btn-remove {
            position: absolute;
            top: 8px;
            right: 8px;
        }
        .categoria-checkbox {
            margin-right: 8px;
        }
        .categoria-label {
            display: inline-block;
            margin-right: 16px;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>

<div class="container mt-4 mb-5">
    <?php echo generarBreadcrumbs(['Inicio' => '../index.php', 'Panel Admin' => 'index.php', 'Nuevo Himno' => null]); ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>✨ Nuevo Himno</h2>
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
                                   value="<?php echo isset($_POST['numero_oficial']) ? (int)$_POST['numero_oficial'] : ''; ?>"
                                   placeholder="Ej: 1">
                        </div>
                        <div>
                            <label class="form-label">Título Principal *</label>
                            <input type="text" name="titulo_principal" class="form-control" required
                                   value="<?php echo isset($_POST['titulo_principal']) ? sanitizar($_POST['titulo_principal']) : ''; ?>"
                                   placeholder="Ej: Sublime Gracia">
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select">
                                <option value="1" <?php echo (isset($_POST['tipo']) && (int)$_POST['tipo'] === 1) ? 'selected' : ''; ?>>Oficial</option>
                                <option value="2" <?php echo (isset($_POST['tipo']) && (int)$_POST['tipo'] === 2) ? 'selected' : ''; ?>>Inspirada</option>
                                <option value="3" <?php echo (isset($_POST['tipo']) && (int)$_POST['tipo'] === 3) ? 'selected' : ''; ?>>Convención</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Evento</label>
                            <input type="text" name="evento" class="form-control"
                                   value="<?php echo isset($_POST['evento']) ? sanitizar($_POST['evento']) : ''; ?>"
                                   placeholder="Ej: Convención Nacional 2024">
                        </div>
                    </div>
                    <div class="form-check mt-2">
                        <input type="checkbox" name="activo" id="activo" class="form-check-input" value="1" checked>
                        <label class="form-check-label" for="activo">Himno activo</label>
                    </div>
                </div>

                <!-- SECCIÓN 2: VERSIONES POR PAÍS -->
                <div class="form-section">
                    <h5 class="mb-3">🌍 Versiones por País</h5>
                    <p class="form-help-text">Agregue al menos una versión. Cada versión tendrá las estrofas que escriba abajo.</p>
                    <div id="versiones-container">
                        <!-- Las versiones se agregan vía JS -->
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="agregarVersion()">+ Agregar Versión</button>
                </div>

                <!-- SECCIÓN 3: CATEGORÍAS -->
                <div class="form-section">
                    <h5 class="mb-3">🏷️ Categorías</h5>
                    <div>
                        <?php if ($categorias_list && $categorias_list->num_rows > 0): ?>
                            <?php while($cat = $categorias_list->fetch_assoc()): ?>
                                <label class="categoria-label">
                                    <input type="checkbox" name="categorias[]" value="<?php echo (int)$cat['id']; ?>"
                                           class="categoria-checkbox"
                                           <?php echo (isset($_POST['categorias']) && in_array($cat['id'], $_POST['categorias'])) ? 'checked' : ''; ?>>
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
                    <p class="form-help-text">Use el botón "Agregar Estrofa" para añadir bloques. Seleccione el tipo (Estrofa, Coro, Puente, Intro, Final).</p>
                    <div id="estrofas-container">
                        <!-- Las estrofas se agregan vía JS con EstrofaManager -->
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button type="button" class="btn btn-outline-primary" onclick="agregarEstrofa('Estrofa')">+ Estrofa</button>
                        <button type="button" class="btn btn-outline-warning" onclick="agregarEstrofa('Coro')">+ Coro</button>
                        <button type="button" class="btn btn-outline-info" onclick="agregarEstrofa('Puente')">+ Puente</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="agregarEstrofa('Intro')">+ Intro</button>
                        <button type="button" class="btn btn-outline-danger" onclick="agregarEstrofa('Final')">+ Final</button>
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-success btn-lg">💾 Guardar Himno</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../js/app.js" defer></script>
<script>
    // Almacén de países para el selector de versiones
    const PAISES = <?php
        $paises_data = [];
        if ($paises) {
            $paises->data_seek(0);
            while($p = $paises->fetch_assoc()) {
                $paises_data[] = ['id' => (int)$p['id'], 'nombre' => $p['nombre']];
            }
        }
        echo json_encode($paises_data);
    ?>;

    let versionCount = 0;

    function agregarVersion(paisId, tonalidad) {
        versionCount++;
        const container = document.getElementById('versiones-container');
        const div = document.createElement('div');
        div.className = 'version-box';
        div.id = 'version-' + versionCount;

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

    // Agregar una versión por defecto al cargar
    document.addEventListener('DOMContentLoaded', function() {
        agregarVersion();
    });

    // Función para agregar estrofas (usa el formato del nuevo schema)
    window.agregarEstrofa = function(tipo) {
        const container = document.getElementById('estrofas-container');
        const count = container.children.length + 1;

        const div = document.createElement('div');
        div.className = 'estrofa-box';

        const etiquetas = {
            'Estrofa': '<span class="badge bg-primary">ESTROFA</span>',
            'Coro': '<span class="badge bg-warning text-dark">CORO</span>',
            'Puente': '<span class="badge bg-info text-dark">PUENTE</span>',
            'Intro': '<span class="badge bg-secondary">INTRO</span>',
            'Final': '<span class="badge bg-danger">FINAL</span>'
        };

        div.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                ${etiquetas[tipo] || etiquetas['Estrofa']}
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="moverEstrofa(this, -1)" title="Subir">↑</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="moverEstrofa(this, 1)" title="Bajar">↓</button>
                    <span class="badge bg-dark me-1">#${count}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.estrofa-box').remove()">✕</button>
                </div>
            </div>
            <input type="hidden" name="tipo_estrofa[]" value="${tipo}">
            <textarea name="contenido_estrofa[]" class="form-control" rows="4"
                      placeholder="Escribe la letra aquí..." required></textarea>
        `;
        container.appendChild(div);
        div.querySelector('textarea').focus();
    };

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
            return; // No se puede mover más
        }

        // Re-indexar números
        const boxes = container.querySelectorAll('.estrofa-box');
        boxes.forEach(function(b, i) {
            var badge = b.querySelector('.badge.bg-dark');
            if (badge) badge.textContent = '#' + (i + 1);
        });
    };

    // Inicializar app
    document.addEventListener('DOMContentLoaded', function() {
        Himnario.initPage('admin-crear');

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
</body>
</html>
