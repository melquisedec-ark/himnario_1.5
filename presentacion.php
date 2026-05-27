<?php
/**
 * presentacion.php - Visor / Proyector de himnos
 * Muestra himno con selector de versión por país.
 * Mantiene funcionalidad existente: modo músico, fondos, temas, pantalla completa.
 */
require_once 'includes/db.php';
require_once 'includes/funciones.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id_himno = (int)$_GET['id'];
$version_pais_id = isset($_GET['version_pais_id']) ? (int)$_GET['version_pais_id'] : 0;

// 1. Cargar himno
$stmt = $conexion->prepare("SELECT * FROM himnos WHERE id = ? AND activo = 1 LIMIT 1");
$stmt->bind_param("i", $id_himno);
$stmt->execute();
$himno = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$himno) {
    die("<div style='text-align:center;padding:50px;font-family:sans-serif;'><h2>Himno no encontrado</h2><a href='index.php'>← Volver</a></div>");
}

// 2. Cargar versiones por país
$versiones = obtenerVersiones($conexion, $id_himno);

// 3. Determinar versión activa
if ($version_pais_id > 0) {
    // Verificar que pertenezca a este himno
    $found = false;
    foreach ($versiones as $v) {
        if ((int)$v['id'] === $version_pais_id) { $found = true; break; }
    }
    if (!$found) $version_pais_id = 0;
}

if ($version_pais_id === 0 && !empty($versiones)) {
    // Usar la primera activa
    foreach ($versiones as $v) {
        if ((int)$v['activo'] === 1) {
            $version_pais_id = (int)$v['id'];
            break;
        }
    }
    if ($version_pais_id === 0) {
        $version_pais_id = (int)$versiones[0]['id'];
    }
}

// 4. Obtener datos de la versión seleccionada
$version_actual = null;
foreach ($versiones as $v) {
    if ((int)$v['id'] === $version_pais_id) {
        $version_actual = $v;
        break;
    }
}

// 5. Cargar estrofas de la versión seleccionada
$estrofas = [];
if ($version_pais_id > 0) {
    $estrofas = obtenerEstrofas($conexion, $version_pais_id);
}

// 6. Cargar categorías
$categorias = obtenerCategorias($conexion, $id_himno);

// 7. Preparar datos para JS (mapear tipos DB a lowercase)
$estrofas_js = [];
foreach ($estrofas as $e) {
    $estrofas_js[] = [
        'tipo' => strtolower($e['tipo']), // 'Estrofa' -> 'estrofa', 'Coro' -> 'coro', etc.
        'contenido' => $e['contenido'],
        'orden' => (int)$e['orden'],
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#000000">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Patua+One&family=Young+Serif&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
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

    <title><?php echo sanitizar($himno['titulo_principal']); ?> - Himnario Digital</title>
    <style>
        :root {
            --text-color: #ffffff;
            --acorde-color: #ff9f43;
            --accent-color: #ffd700;
            --base-size: 4.0vw;
            --font-scale: 1;
            --font-family: 'Patua One', serif;
            --overlay-opacity: 0.5;
            --text-shadow: 0 0 5px rgba(0,0,0,0.8), 0 0 10px rgba(0,0,0,0.5);
            --bg-color: #000;
        }

        body, html {
            margin: 0; padding: 0; height: 100%; width: 100%;
            overflow: hidden; background-color: var(--bg-color);
            font-family: var(--font-family);
            cursor: none;
        }
        body.mouse-activo { cursor: default; }

        #bg-layer {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 1; background-color: #000; background-size: cover; background-position: center;
            transition: background 0.5s ease;
        }
        #video-bg {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            object-fit: cover; display: none;
        }
        #overlay-layer {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 2; background-color: black; opacity: var(--overlay-opacity);
            transition: opacity 0.3s;
        }

        #slide-container {
            position: relative; z-index: 3;
            display: flex; justify-content: center; align-items: center;
            height: 100vh; width: 100vw;
            padding: 20px 60px;
            box-sizing: border-box;
            user-select: none;
        }

        .contenido-letra {
            color: var(--text-color);
            font-size: calc(var(--base-size) * var(--font-scale));
            font-weight: 500;
            line-height: 1.25;
            text-align: center;
            white-space: pre-wrap;
            max-width: 100%;
            text-shadow: var(--text-shadow);
            opacity: 1; transform: scale(1);
            transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
        }
        .contenido-letra.oculto { opacity: 0; transform: scale(0.95); }

        .acorde {
            color: var(--acorde-color); font-size: 0.8em; font-weight: 900;
            background-color: rgba(0,0,0,0.3); padding: 0 4px; border-radius: 4px;
            vertical-align: super; margin-right: 2px; display: inline-block;
            text-shadow: none;
            font-family: 'Segoe UI', sans-serif;
        }

        .titulo-slide {
            font-size: 1.6em;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 4px;
            line-height: 1.3;
            color: var(--accent-color);
            text-shadow: var(--text-shadow);
            margin-bottom: 20px;
        }

        .btn-ui {
            position: absolute; top: 20px; z-index: 20;
            background: rgba(0, 0, 0, 0.5);
            color: #fff; padding: 8px 15px; border-radius: 4px; text-decoration: none;
            font-size: 0.9rem; border: 1px solid rgba(255,255,255,0.2); cursor: pointer;
            backdrop-filter: blur(5px); transition: 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            font-family: 'Segoe UI', sans-serif;
        }
        .btn-ui:hover { background: rgba(50, 50, 50, 0.8); border-color: rgba(255,255,255,0.5); }
        .btn-salir { right: 25px; background: rgba(200, 40, 50, 0.7); }
        .btn-config { right: 110px; }
        .btn-versiones { right: 220px; display: none; }

        .etiqueta-tipo {
            font-size: 0.55em; font-weight: 800;
            text-transform: uppercase; letter-spacing: 4px;
            color: var(--accent-color); display: block; margin-bottom: 1.2em;
            text-shadow: var(--text-shadow);
            opacity: 1;
            font-family: 'Segoe UI', sans-serif;
        }

        .amen-style {
            font-style: italic;
            font-family: 'Times New Roman', serif;
            letter-spacing: 5px;
        }

        #panel-config {
            position: absolute; top: 65px; right: 25px; z-index: 100;
            background: rgba(20, 20, 20, 0.98); padding: 25px;
            border-radius: 12px; width: 300px; display: none;
            border: 1px solid #444; color: #eee;
            box-shadow: 0 15px 40px rgba(0,0,0,0.9);
            font-family: 'Segoe UI', sans-serif;
        }
        #panel-config.activo { display: block; animation: fadeIn 0.2s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .control-group { margin-bottom: 20px; }
        .control-group label { display: block; margin-bottom: 8px; font-size: 0.85rem; color: #bbb; font-weight: 600; letter-spacing: 0.5px; }
        input[type="range"] { width: 100%; cursor: pointer; accent-color: var(--accent-color); }

        .grid-temas { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }
        .btn-tema {
            background: #3a3a3a; border: 1px solid #555; color: #ddd;
            padding: 10px 5px; font-size: 0.85rem; cursor: pointer; border-radius: 6px;
            text-align: center; transition: 0.2s;
        }
        .btn-tema:hover { background: #555; border-color: #fff; color: white; }

        .switch-container { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #444; }
        .switch-label { font-size: 0.95rem; font-weight: bold; color: var(--acorde-color); display: flex; align-items: center; gap: 8px;}
        .switch-input { transform: scale(1.3); cursor: pointer; accent-color: var(--acorde-color); }

        /* Metadatos del himno en el panel */
        .meta-info { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #444; font-size: 0.85rem; }
        .meta-info .meta-label { color: #888; display: block; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; }
        .meta-info .meta-value { color: #fff; font-weight: 600; }

        @media (max-width: 768px) {
            body { overflow-y: auto !important; cursor: auto !important; }
            #slide-container { display: block !important; height: auto !important; padding: 60px 20px 60px 20px !important; }
            .btn-ui { position: fixed; top: 15px; z-index: 60 !important; font-size: 0.8rem; padding: 6px 10px; }
            .btn-salir { right: 15px; }
            .btn-config { right: 85px; }
            .btn-versiones { right: 170px; }
            .contenido-letra { font-size: 1.4rem !important; text-align: left !important; opacity: 1 !important; transform: none !important; text-shadow: 1px 1px 2px #000; }
            .etiqueta-tipo { text-align: left; margin-top: 15px; color: var(--accent-color); font-size: 0.85rem !important; }
            .amen-style { display: block; text-align: center; margin-top: 30px; font-size: 1.5rem; padding-bottom: 50px; }
            #panel-config { position: fixed; top: 70px; right: 10px; width: 90%; max-height: 80vh; overflow-y: auto; }
        }
    </style>
</head>
<body>

    <div id="bg-layer">
        <video id="video-bg" loop muted playsinline></video>
    </div>
    <div id="overlay-layer"></div>

    <button class="btn-ui btn-versiones" id="btn-versiones" onclick="toggleSelectorVersiones()">🌍 Versiones</button>
    <button class="btn-ui btn-config" onclick="toggleConfig()">⚙️ Ajustes</button>
    <a href="index.php" class="btn-ui btn-salir">✖ Salir</a>

    <!-- Selector rápido de versiones -->
    <div id="panel-versiones" style="display:none;position:absolute;top:65px;right:220px;z-index:100;background:rgba(20,20,20,0.98);padding:15px;border-radius:12px;border:1px solid #444;color:#eee;font-family:'Segoe UI',sans-serif;min-width:200px;">
        <label style="font-size:0.85rem;color:#bbb;display:block;margin-bottom:8px;font-weight:600;">VERSIÓN POR PAÍS</label>
        <select id="version-selector" class="form-select form-select-sm" style="background:#2a2a2a;color:#fff;border:1px solid #555;" onchange="cambiarVersion(this.value)">
            <?php foreach ($versiones as $v): ?>
                <option value="<?php echo (int)$v['id']; ?>" <?php echo (int)$v['id'] === $version_pais_id ? 'selected' : ''; ?>>
                    <?php echo sanitizar($v['pais_nombre']); ?>
                    <?php echo !empty($v['tonalidad_original']) ? ' (' . sanitizar($v['tonalidad_original']) . ')' : ''; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="panel-config">
        <!-- Info del himno -->
        <div class="meta-info">
            <span class="meta-value" style="font-size:1.1rem;">#<?php echo (int)$himno['numero_oficial']; ?> - <?php echo sanitizar($himno['titulo_principal']); ?></span>
            <span style="display:block;margin-top:5px;">
                <span class="badge-categoria"><?php echo tipoHimnoTexto($himno['tipo']); ?></span>
                <?php if ($version_actual && !empty($version_actual['tonalidad_original'])): ?>
                    <span class="badge-tonalidad" style="color:#eee;border-color:#666;"><?php echo sanitizar($version_actual['tonalidad_original']); ?></span>
                <?php endif; ?>
                <?php if ($version_actual): ?>
                    <span class="badge-pais" style="color:#eee;border-color:#666;"><?php echo sanitizar($version_actual['pais_nombre']); ?></span>
                <?php endif; ?>
            </span>
            <?php if (!empty($categorias)): ?>
                <span style="display:block;margin-top:5px;">
                    <?php foreach ($categorias as $cat): ?>
                        <span class="badge bg-secondary"><?php echo sanitizar($cat); ?></span>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="switch-container">
            <span class="switch-label">🎸 Modo Músico</span>
            <input type="checkbox" id="check-musico" class="switch-input" onchange="toggleModoMusico()">
        </div>

        <div class="control-group">
            <label>TEMA VISUAL</label>
            <div class="grid-temas" id="lista-temas"></div>
        </div>
        <div class="control-group">
            <label>Oscuridad del Fondo</label>
            <input type="range" id="input-overlay" min="0" max="0.9" step="0.1" value="0.5" oninput="ajustarOverlay()">
        </div>
        <div class="control-group">
            <label>Tamaño de Letra (PC)</label>
            <input type="range" id="input-size" min="0.6" max="1.4" step="0.1" value="1" oninput="ajustarLetra()">
        </div>
    </div>

    <div id="slide-container">
        <div id="texto-pantalla" class="contenido-letra"></div>
    </div>

    <script>
        // --- 1. CONFIGURACIÓN Y DATOS ---
        const TEMAS = [
            { nombre: "🌑 Clásico", tipo: "color", valor: "#000000", texto: "#ffffff", font: "'Patua One', serif", shadow: "0 0 5px #000, 0 0 10px #000, 0 0 15px #000" },
            { nombre: "☁️ Claro", tipo: "color", valor: "#ffffff", texto: "#000000", font: "'Patua One', serif", shadow: "none" },
            { nombre: "🔵 Azul", tipo: "color", valor: "#001f3f", texto: "#dceeff", font: "'Patua One', serif", shadow: "2px 2px 4px rgba(0,0,0,0.6)" },
            { nombre: "📜 Vintage", tipo: "imagen", valor: "assets/fondos/pergamino.png", texto: "#3d2b1f", font: "'Times New Roman', serif", shadow: "1px 1px 2px rgba(255,255,255,0.5), 0 0 5px rgba(0,0,0,0.2)" },
            { nombre: "⛰️ Paisaje", tipo: "imagen", valor: "assets/fondos/fondo1.jpg", texto: "#ffffff", font: "'Patua One', serif", shadow: "0 0 5px #000, 0 0 10px #000" },
            { nombre: "🌊 Video", tipo: "video", valor: "assets/fondos/video1.mp4", texto: "#ffffff", font: "'Patua One', serif", shadow: "0 0 10px #000, 0 0 20px #000" }
        ];

        // DATOS PHP para JS
        const estrofas = <?php echo json_encode($estrofas_js); ?>;
        const himnoNumero = <?php echo (int)$himno['numero_oficial']; ?>;
        const himnoTitulo = <?php echo json_encode(strtoupper($himno['titulo_principal'])); ?>;
        const versionesDisponibles = <?php echo json_encode($versiones); ?>;
        const himnoId = <?php echo (int)$id_himno; ?>;

        const contenedor = document.getElementById('texto-pantalla');
        let indiceActual = 0;
        let enTransicion = false;
        let modoMusico = false;
        let mouseTimer = null;

        const esMovil = window.innerWidth < 768;

        // Mostrar botón de versiones si hay más de una
        if (versionesDisponibles.length > 1) {
            document.getElementById('btn-versiones').style.display = 'block';
        }

        // --- 2. LÓGICA DE INYECCIÓN DE DIAPOSITIVAS ---
        estrofas.unshift({
            tipo: 'titulo',
            contenido: 'HIMNO #' + himnoNumero + '\n' + himnoTitulo
        });

        estrofas.push({ tipo: 'final', contenido: 'Amén' });

        // --- 3. REGISTRO PWA ---
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js')
                .then(function() { console.log("PWA Ready"); })
                .catch(function(err) { console.log("Error PWA", err); });
        }

        document.onmousemove = function() {
            document.body.classList.add('mouse-activo');
            clearTimeout(mouseTimer);
            mouseTimer = setTimeout(function() { document.body.classList.remove('mouse-activo'); }, 3000);
        };

        window.onload = function() {
            cargarTemasEnMenu();
            if (localStorage.getItem('h_tema_idx')) aplicarTema(localStorage.getItem('h_tema_idx'));
            if (localStorage.getItem('h_size')) { document.getElementById('input-size').value = localStorage.getItem('h_size'); ajustarLetra(); }
            if (localStorage.getItem('h_overlay')) { document.getElementById('input-overlay').value = localStorage.getItem('h_overlay'); ajustarOverlay(); }
            if (localStorage.getItem('h_musico') === 'true') { modoMusico = true; document.getElementById('check-musico').checked = true; }

            if (esMovil) {
                renderizarModoLectura();
            } else {
                renderizarTexto();
            }
            document.onmousemove();
        };

        // --- RENDERIZADO MÓVIL ---
        function renderizarModoLectura() {
            var htmlCompleto = "";
            estrofas.forEach(function(datos, index) {
                var contenido = "";
                if (datos.tipo === 'titulo') {
                    contenido = '<div class="titulo-slide" style="text-align: center; margin-bottom: 50px; padding-top: 20px;">' +
                        datos.contenido + '</div>';
                } else if (datos.tipo === 'final') {
                    contenido = '<div class="amen-style">Amén</div>';
                } else {
                    var etiqueta = obtenerEtiqueta(datos, index);
                    var letra = procesarTexto(datos.contenido);
                    contenido = '<div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px dashed rgba(255,255,255,0.1);">' +
                        etiqueta + '<div style="line-height: 1.4;">' + letra + '</div></div>';
                }
                htmlCompleto += contenido;
            });
            htmlCompleto += "<div style='height: 120px;'></div>";
            contenedor.innerHTML = htmlCompleto;
        }

        // --- FUNCIONES COMUNES ---
        function toggleModoMusico() {
            modoMusico = document.getElementById('check-musico').checked;
            localStorage.setItem('h_musico', modoMusico);
            if (esMovil) renderizarModoLectura(); else renderizarTexto();
        }

        function procesarTexto(textoBruto) {
            if (modoMusico) return textoBruto.replace(/\[(.*?)\]/g, '<span class="acorde">$1</span>');
            else return textoBruto.replace(/\[.*?\]/g, '');
        }

        function obtenerEtiqueta(datos, indice) {
            if (datos.tipo === 'titulo') return '';
            if (datos.tipo === 'coro') return '<span class="etiqueta-tipo">CORO</span>';
            if (datos.tipo === 'final') return '';
            if (datos.tipo === 'intro') return '<span class="etiqueta-tipo">INTRO</span>';
            if (datos.tipo === 'estrofa' || datos.tipo === 'verso') {
                var contador = 0;
                for (var i = 0; i <= indice; i++) {
                    if (estrofas[i].tipo === 'estrofa' || estrofas[i].tipo === 'verso') contador++;
                }
                return '<span class="etiqueta-tipo">ESTROFA ' + contador + '</span>';
            }
            if (datos.tipo === 'puente') return '<span class="etiqueta-tipo">PUENTE</span>';
            return '';
        }

        // --- RENDERIZADO PC ---
        function renderizarTexto() {
            if (estrofas.length === 0) return;
            var datos = estrofas[indiceActual];
            var html = '';

            if (datos.tipo === 'titulo') {
                html += '<div class="titulo-slide">' + datos.contenido + '</div>';
            } else {
                html += obtenerEtiqueta(datos, indiceActual);
                if (datos.tipo === 'final') {
                    html += '<span class="amen-style">' + datos.contenido + '</span>';
                } else {
                    html += procesarTexto(datos.contenido);
                }
            }

            contenedor.innerHTML = html;
            aplicarColoresSegunTema(datos);
        }

        function aplicarColoresSegunTema(datos) {
            var temaActualIdx = localStorage.getItem('h_tema_idx') || 0;
            var nombreTema = TEMAS[temaActualIdx].nombre;

            if (datos.tipo === 'coro' && nombreTema !== "📜 Vintage" && nombreTema !== "☁️ Claro") {
                contenedor.style.color = '#ffffcc';
            } else if (datos.tipo === 'coro' && nombreTema === "☁️ Claro") {
                contenedor.style.color = '#000000';
            } else {
                contenedor.style.color = 'var(--text-color)';
            }
        }

        // --- NAVEGACIÓN PC ---
        function cambiarSlide(direccion) {
            if (enTransicion || esMovil) return;
            var nuevoIndice = indiceActual + direccion;

            if (nuevoIndice >= estrofas.length) {
                window.location.href = 'index.php';
                return;
            }
            if (nuevoIndice < 0) return;

            enTransicion = true;
            contenedor.classList.add('oculto');
            setTimeout(function() {
                indiceActual = nuevoIndice;
                renderizarTexto();
                contenedor.classList.remove('oculto');
                setTimeout(function() { enTransicion = false; }, 300);
            }, 300);
        }

        // --- GESTIÓN DE TEMAS ---
        function cargarTemasEnMenu() {
            var lista = document.getElementById('lista-temas');
            TEMAS.forEach(function(tema, index) {
                var btn = document.createElement('button');
                btn.className = 'btn-tema';
                btn.innerHTML = tema.nombre;
                btn.onclick = function() { aplicarTema(index); };
                lista.appendChild(btn);
            });
        }

        function aplicarTema(index) {
            var tema = TEMAS[index];
            var bgLayer = document.getElementById('bg-layer');
            var videoBg = document.getElementById('video-bg');
            videoBg.style.display = 'none'; videoBg.pause();
            bgLayer.style.backgroundImage = 'none'; bgLayer.style.backgroundColor = '#000';
            if (tema.tipo === 'color') bgLayer.style.backgroundColor = tema.valor;
            else if (tema.tipo === 'imagen') bgLayer.style.backgroundImage = "url('" + tema.valor + "')";
            else if (tema.tipo === 'video') { videoBg.src = tema.valor; videoBg.style.display = 'block'; videoBg.play()["catch"](function(e) { console.log(e); }); }

            document.documentElement.style.setProperty('--text-color', tema.texto);
            document.documentElement.style.setProperty('--font-family', tema.font);
            document.documentElement.style.setProperty('--text-shadow', tema.shadow);

            if (tema.nombre === "☁️ Claro" || tema.nombre === "📜 Vintage") {
                document.documentElement.style.setProperty('--accent-color', '#000000');
            } else {
                document.documentElement.style.setProperty('--accent-color', '#ffd700');
            }

            localStorage.setItem('h_tema_idx', index);
            if (esMovil) renderizarModoLectura(); else renderizarTexto();
        }

        function ajustarOverlay() {
            var val = document.getElementById('input-overlay').value;
            document.documentElement.style.setProperty('--overlay-opacity', val);
            localStorage.setItem('h_overlay', val);
        }
        function ajustarLetra() {
            var val = document.getElementById('input-size').value;
            document.documentElement.style.setProperty('--font-scale', val);
            localStorage.setItem('h_size', val);
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'Enter') cambiarSlide(1);
            if (e.key === 'ArrowLeft') cambiarSlide(-1);
            if (e.key === 'Escape') toggleConfig(false);
        });

        document.getElementById('slide-container').addEventListener('click', function(e) {
            var panel = document.getElementById('panel-config');
            if (panel.classList.contains('activo')) { toggleConfig(false); }
            else { if (e.clientX < window.innerWidth * 0.2) cambiarSlide(-1); else cambiarSlide(1); }
        });

        function toggleConfig(estado) {
            var panel = document.getElementById('panel-config');
            if (estado === undefined) panel.classList.toggle('activo');
            else if (estado) panel.classList.add('activo'); else panel.classList.remove('activo');
        }

        function toggleSelectorVersiones() {
            var panel = document.getElementById('panel-versiones');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }

        function cambiarVersion(versionPaisId) {
            // Redirigir con la nueva versión
            window.location.href = 'presentacion.php?id=' + himnoId + '&version_pais_id=' + versionPaisId;
        }
    </script>
</body>
</html>
