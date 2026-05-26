<?php
// presentacion.php - FASE 13: YOUNG SERIF & MODO CLARO INTELIGENTE
include 'includes/db.php';

if (!isset($_GET['id'])) { header("Location: index.php"); exit; }

$id_himno = $_GET['id'];

$stmt = $conexion->prepare("SELECT * FROM himnos WHERE id = ?");
$stmt->bind_param("i", $id_himno);
$stmt->execute();
$himno = $stmt->get_result()->fetch_assoc();
if (!$himno) die("Himno no encontrado.");

$stmt_e = $conexion->prepare("SELECT * FROM estrofas WHERE himno_id = ? ORDER BY orden ASC");
$stmt_e->bind_param("i", $id_himno);
$stmt_e->execute();
$res = $stmt_e->get_result();
$estrofas = [];
while ($row = $res->fetch_assoc()) { $estrofas[] = $row; }
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

    <title><?php echo $himno['titulo']; ?></title>
    <style>
        :root {
            --text-color: #ffffff;
            --acorde-color: #ff9f43;
            --accent-color: #ffd700; /* Dorado por defecto */
            --base-size: 4.0vw;
            --font-scale: 1; 
            /* Fuente por defecto ahora es Young Serif */
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

        /* --- FONDOS --- */
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

        /* --- CONTENIDO --- */
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
            /* Young Serif ya tiene peso, no necesitamos bold forzado a menos que se quiera muy grueso */
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
            font-family: 'Segoe UI', sans-serif; /* Acordes mejor en sans-serif */
        }

        /* --- TÍTULO DIAPOSITIVA --- */
        .titulo-slide {
            font-size: 1.6em; 
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 4px; /* Un poco menos de tracking para esta fuente */
            line-height: 1.3;
            color: var(--accent-color); /* Hereda el color (negro en claro, dorado en oscuro) */
            text-shadow: var(--text-shadow); /* Hereda la sombra del tema */
            margin-bottom: 20px;
        }

        /* BOTONES UI FLOTANTES */
        .btn-ui {
            position: absolute; top: 20px; z-index: 20;
            background: rgba(0, 0, 0, 0.5); 
            color: #fff; padding: 8px 15px; border-radius: 4px; text-decoration: none;
            font-size: 0.9rem; border: 1px solid rgba(255,255,255,0.2); cursor: pointer;
            backdrop-filter: blur(5px); transition: 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            font-family: 'Segoe UI', sans-serif; /* UI mantiene fuente sistema */
        }
        .btn-ui:hover { background: rgba(50, 50, 50, 0.8); border-color: rgba(255,255,255,0.5); }
        .btn-salir { right: 25px; background: rgba(200, 40, 50, 0.7); }
        .btn-config { right: 110px; }

        /* ETIQUETAS */
        .etiqueta-tipo {
            font-size: 0.55em; font-weight: 800;
            text-transform: uppercase; letter-spacing: 4px;
            color: var(--accent-color); display: block; margin-bottom: 1.2em;
            text-shadow: var(--text-shadow);
            opacity: 1;
            font-family: 'Segoe UI', sans-serif; /* Etiquetas técnicas en sans */
        }

        /* ESTILO ESPECIAL PARA EL AMÉN */
        .amen-style {
            font-style: italic;
            font-family: 'Times New Roman', serif; /* Amén clásico */
            letter-spacing: 5px;
        }

        /* PANEL CONFIG */
        #panel-config {
            position: absolute; top: 65px; right: 25px; z-index: 100;
            background: rgba(20, 20, 20, 0.98); padding: 25px; 
            border-radius: 12px; width: 280px; display: none;
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
            text-align: center; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 5px;
        }
        .btn-tema:hover { background: #555; border-color: #fff; color: white; }
        
        .switch-container { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #444; }
        .switch-label { font-size: 0.95rem; font-weight: bold; color: var(--acorde-color); display: flex; align-items: center; gap: 8px;}
        .switch-input { transform: scale(1.3); cursor: pointer; accent-color: var(--acorde-color); }

        /* ========================================= */
        /* MODO LECTURA PARA CELULARES (RESPONSIVE) */
        /* ========================================= */
        @media (max-width: 768px) {
            body { 
                overflow-y: auto !important;
                cursor: auto !important;
            }
            
            #slide-container {
                display: block !important;
                height: auto !important;
                padding: 60px 20px 60px 20px !important; 
            }

            /* Botones flotantes en móvil */
            .btn-ui { position: fixed; top: 15px; z-index: 60 !important; font-size: 0.8rem; padding: 6px 10px; }
            .btn-salir { right: 15px; }
            .btn-config { right: 85px; } 

            .contenido-letra {
                font-size: 1.4rem !important;
                text-align: left !important;
                opacity: 1 !important;
                transform: none !important;
                text-shadow: 1px 1px 2px #000;
            }
            
            .etiqueta-tipo { 
                text-align: left; 
                margin-top: 15px;
                color: var(--accent-color);
                font-size: 0.85rem !important;
            }

            .amen-style {
                display: block; text-align: center; margin-top: 30px; font-size: 1.5rem;
                padding-bottom: 50px;
            }

            #panel-config { position: fixed; top: 70px; right: 10px; width: 90%; max-height: 80vh; overflow-y: auto; }
        }
    </style>
</head>
<body>

    <div id="bg-layer">
        <video id="video-bg" loop muted playsinline></video>
    </div>
    <div id="overlay-layer"></div>

    <button class="btn-ui btn-config" onclick="toggleConfig()">⚙️ Ajustes</button>
    <a href="index.php" class="btn-ui btn-salir">✖ Salir</a>

    <div id="panel-config">
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
        // Actualizamos las fuentes de los temas. Todos "Young Serif" excepto Vintage.
        const TEMAS = [
            { nombre: "🌑 Clásico", tipo: "color", valor: "#000000", texto: "#ffffff", font: "'Patua One', serif", shadow: "0 0 5px #000, 0 0 10px #000, 0 0 15px #000" },
            { nombre: "☁️ Claro", tipo: "color", valor: "#ffffff", texto: "#000000", font: "'Patua One', serif", shadow: "none" },
            { nombre: "🔵 Azul", tipo: "color", valor: "#001f3f", texto: "#dceeff", font: "'Patua One', serif", shadow: "2px 2px 4px rgba(0,0,0,0.6)" },
            { nombre: "📜 Vintage", tipo: "imagen", valor: "assets/fondos/pergamino.png", texto: "#3d2b1f", font: "'Times New Roman', serif", shadow: "1px 1px 2px rgba(255,255,255,0.5), 0 0 5px rgba(0,0,0,0.2)" },
            { nombre: "⛰️ Paisaje", tipo: "imagen", valor: "assets/fondos/fondo1.jpg", texto: "#ffffff", font: "'Patua One', serif", shadow: "0 0 5px #000, 0 0 10px #000" },
            { nombre: "🌊 Video", tipo: "video", valor: "assets/fondos/video1.mp4", texto: "#ffffff", font: "'Patua One', serif", shadow: "0 0 10px #000, 0 0 20px #000" }
        ];

        // DATOS PHP
        const estrofas = <?php echo json_encode($estrofas); ?>;
        const himnoNumero = <?php echo $himno['numero']; ?>;
        const himnoTitulo = <?php echo json_encode(strtoupper($himno['titulo'])); ?>;

        const contenedor = document.getElementById('texto-pantalla');
        let indiceActual = 0;
        let enTransicion = false;
        let modoMusico = false;
        let mouseTimer = null;
        
        const esMovil = window.innerWidth < 768;

        // --- 2. LÓGICA DE INYECCIÓN DE DIAPOSITIVAS ---
        estrofas.unshift({ 
            tipo: 'titulo', 
            contenido: `HIMNO #${himnoNumero}\n${himnoTitulo}` 
        });

        estrofas.push({ tipo: 'final', contenido: 'Amén' });

        // --- 3. REGISTRO PWA ---
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js')
            .then(() => console.log("PWA Ready"))
            .catch(err => console.log("Error PWA", err));
        }

        document.onmousemove = function() {
            document.body.classList.add('mouse-activo');
            clearTimeout(mouseTimer);
            mouseTimer = setTimeout(() => { document.body.classList.remove('mouse-activo'); }, 3000);
        };

        window.onload = function() {
            cargarTemasEnMenu();
            if(localStorage.getItem('h_tema_idx')) aplicarTema(localStorage.getItem('h_tema_idx'));
            if(localStorage.getItem('h_size')) { document.getElementById('input-size').value = localStorage.getItem('h_size'); ajustarLetra(); }
            if(localStorage.getItem('h_overlay')) { document.getElementById('input-overlay').value = localStorage.getItem('h_overlay'); ajustarOverlay(); }
            if(localStorage.getItem('h_musico') === 'true') { modoMusico = true; document.getElementById('check-musico').checked = true; }
            
            if (esMovil) {
                renderizarModoLectura();
            } else {
                renderizarTexto();
            }
            document.onmousemove(); 
        };

        // --- RENDERIZADO MÓVIL ---
        function renderizarModoLectura() {
            let htmlCompleto = "";
            estrofas.forEach((datos, index) => {
                let contenido = "";
                
                if (datos.tipo === 'titulo') {
                    contenido = `
                        <div class="titulo-slide" style="text-align: center; margin-bottom: 50px; padding-top: 20px;">
                            ${datos.contenido}
                        </div>`;
                } else if (datos.tipo === 'final') {
                    contenido = `<div class="amen-style">Amén</div>`;
                } else {
                    let etiqueta = obtenerEtiqueta(datos, index);
                    let letra = procesarTexto(datos.contenido);
                    
                    contenido = `
                        <div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px dashed rgba(255,255,255,0.1);">
                            ${etiqueta}
                            <div style="line-height: 1.4;">${letra}</div>
                        </div>
                    `;
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
            if(esMovil) renderizarModoLectura(); else renderizarTexto();
        }

        function procesarTexto(textoBruto) {
            if (modoMusico) return textoBruto.replace(/\[(.*?)\]/g, '<span class="acorde">$1</span>');
            else return textoBruto.replace(/\[.*?\]/g, '');
        }

        function obtenerEtiqueta(datos, indice) {
            if (datos.tipo === 'titulo') return ''; 
            if (datos.tipo === 'coro') return '<span class="etiqueta-tipo">CORO</span>';
            if (datos.tipo === 'final') return '';
            if (datos.tipo === 'verso') {
                let contadorVersos = 0;
                for(let i = 0; i <= indice; i++) { if (estrofas[i].tipo === 'verso') contadorVersos++; }
                return `<span class="etiqueta-tipo">ESTROFA ${contadorVersos}</span>`;
            }
            if (datos.tipo === 'puente') return '<span class="etiqueta-tipo">PUENTE</span>';
            return '';
        }

        // --- RENDERIZADO PC ---
        function renderizarTexto() {
            if (estrofas.length === 0) return;
            const datos = estrofas[indiceActual];
            
            let html = '';
            
            if (datos.tipo === 'titulo') {
                html += `<div class="titulo-slide">${datos.contenido}</div>`;
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
            const temaActualIdx = localStorage.getItem('h_tema_idx') || 0;
            const nombreTema = TEMAS[temaActualIdx].nombre;

            if(datos.tipo === 'coro' && nombreTema !== "📜 Vintage" && nombreTema !== "☁️ Claro") {
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
            let nuevoIndice = indiceActual + direccion;

            if (nuevoIndice >= estrofas.length) {
                window.location.href = 'index.php'; // AUTO SALIDA
                return;
            }
            if (nuevoIndice < 0) return;

            enTransicion = true;
            contenedor.classList.add('oculto');
            setTimeout(() => {
                indiceActual = nuevoIndice;
                renderizarTexto(); 
                contenedor.classList.remove('oculto');
                setTimeout(() => { enTransicion = false; }, 300);
            }, 300);
        }

        // --- GESTIÓN DE TEMAS (Aquí está la lógica del color Negro en Claro) ---
        function cargarTemasEnMenu() {
            const lista = document.getElementById('lista-temas');
            TEMAS.forEach((tema, index) => {
                const btn = document.createElement('button');
                btn.className = 'btn-tema';
                btn.innerHTML = tema.nombre;
                btn.onclick = () => aplicarTema(index);
                lista.appendChild(btn);
            });
        }

        function aplicarTema(index) {
            const tema = TEMAS[index];
            const bgLayer = document.getElementById('bg-layer');
            const videoBg = document.getElementById('video-bg');
            videoBg.style.display = 'none'; videoBg.pause();
            bgLayer.style.backgroundImage = 'none'; bgLayer.style.backgroundColor = '#000';
            if (tema.tipo === 'color') bgLayer.style.backgroundColor = tema.valor;
            else if (tema.tipo === 'imagen') bgLayer.style.backgroundImage = `url('${tema.valor}')`;
            else if (tema.tipo === 'video') { videoBg.src = tema.valor; videoBg.style.display = 'block'; videoBg.play().catch(e => console.log(e)); }
            
            document.documentElement.style.setProperty('--text-color', tema.texto);
            document.documentElement.style.setProperty('--font-family', tema.font);
            document.documentElement.style.setProperty('--text-shadow', tema.shadow);
            
            // LOGICA INTELIGENTE DE ACENTOS
            if(tema.nombre === "☁️ Claro" || tema.nombre === "📜 Vintage") {
                // En modo claro, el Título y etiquetas pasan a NEGRO para contraste
                document.documentElement.style.setProperty('--accent-color', '#000000');
            } else {
                // En fondos oscuros, usamos Dorado
                document.documentElement.style.setProperty('--accent-color', '#ffd700');
            }

            localStorage.setItem('h_tema_idx', index);
            if(esMovil) renderizarModoLectura(); else renderizarTexto();
        }

        function ajustarOverlay() {
            const val = document.getElementById('input-overlay').value;
            document.documentElement.style.setProperty('--overlay-opacity', val);
            localStorage.setItem('h_overlay', val);
        }
        function ajustarLetra() {
            const val = document.getElementById('input-size').value;
            document.documentElement.style.setProperty('--font-scale', val);
            localStorage.setItem('h_size', val);
        }
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'Enter') cambiarSlide(1);
            if (e.key === 'ArrowLeft') cambiarSlide(-1);
            if (e.key === 'Escape') toggleConfig(false);
        });
        document.getElementById('slide-container').addEventListener('click', (e) => {
             const panel = document.getElementById('panel-config');
             if (panel.classList.contains('activo')) { toggleConfig(false); } 
             else { if (e.clientX < window.innerWidth * 0.2) cambiarSlide(-1); else cambiarSlide(1); }
        });
        function toggleConfig(estado) {
            const panel = document.getElementById('panel-config');
            if (estado === undefined) panel.classList.toggle('activo');
            else estado ? panel.classList.add('activo') : panel.classList.remove('activo');
        }
    </script>
</body>
</html>