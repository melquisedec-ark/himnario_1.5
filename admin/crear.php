<?php
session_start();
if (!isset($_SESSION['usuario_logueado'])) { header("Location: ../login.php"); exit; }
include '../includes/db.php';
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $numero = $_POST['numero'];
    $titulo = $_POST['titulo'];
    $tipos = isset($_POST['tipo']) ? $_POST['tipo'] : [];
    $letras = isset($_POST['contenido']) ? $_POST['contenido'] : [];

    $check = $conexion->query("SELECT id FROM himnos WHERE numero = '$numero'");
    if ($check->num_rows > 0) {
        $error = "¡El himno número $numero ya existe!";
    } else {
        $stmt = $conexion->prepare("INSERT INTO himnos (numero, titulo) VALUES (?, ?)");
        $stmt->bind_param("is", $numero, $titulo);
        if ($stmt->execute()) {
            $himno_id = $conexion->insert_id;
            $stmt_estrofa = $conexion->prepare("INSERT INTO estrofas (himno_id, tipo, orden, contenido) VALUES (?, ?, ?, ?)");
            for ($i = 0; $i < count($tipos); $i++) {
                $orden = $i + 1;
                $stmt_estrofa->bind_param("isis", $himno_id, $tipos[$i], $orden, $letras[$i]);
                $stmt_estrofa->execute();
            }
            header("Location: index.php"); exit;
        } else { $error = "Error SQL: " . $conexion->error; }
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Himno</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* CSS INTELIGENTE: Se adapta al modo oscuro automáticamente */
        .estrofa-box { 
            background-color: var(--bs-tertiary-bg); 
            border: 1px solid var(--bs-border-color);
            padding: 15px; margin-bottom: 15px; border-radius: 8px; position: relative; 
        }
        .btn-eliminar { position: absolute; top: 10px; right: 10px; color: var(--bs-danger); border: none; background: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>✨ Nuevo Himno</h2>
        <div>
            <button class="btn btn-outline-secondary btn-sm me-2" onclick="cambiarTema()">🌗 Tema</button>
            <a href="index.php" class="btn btn-outline-primary btn-sm">Volver</a>
        </div>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Número</label>
                        <input type="number" name="numero" class="form-control" required placeholder="Ej: 50">
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control" required placeholder="Ej: Sublime Gracia">
                    </div>
                </div>

                <hr>
                <div id="contenedor-estrofas"></div>

                <div class="d-flex gap-2 mb-4 mt-3">
                    <button type="button" class="btn btn-outline-primary" onclick="agregarBloque('verso')">+ Verso</button>
                    <button type="button" class="btn btn-outline-warning" onclick="agregarBloque('coro')">+ Coro</button>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-success btn-lg">💾 Guardar Himno</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // --- LÓGICA DE TEMA ---
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('admin_theme') || 'light';
    html.setAttribute('data-bs-theme', savedTheme);

    function cambiarTema() {
        const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-bs-theme', next);
        localStorage.setItem('admin_theme', next);
    }

    // --- LÓGICA DE ESTROFAS ---
    const contenedor = document.getElementById('contenedor-estrofas');
    function agregarBloque(tipo) {
        const div = document.createElement('div');
        div.className = 'estrofa-box';
        const etiqueta = tipo === 'verso' ? '<span class="badge bg-primary">VERSO</span>' : '<span class="badge bg-warning text-dark">CORO</span>';
        div.innerHTML = `
            <div class="d-flex justify-content-between mb-2">${etiqueta}<button type="button" class="btn-eliminar" onclick="this.parentElement.parentElement.remove()">✕</button></div>
            <input type="hidden" name="tipo[]" value="${tipo}">
            <textarea name="contenido[]" class="form-control" rows="4" placeholder="Escribe la letra aquí..." required></textarea>
        `;
        contenedor.appendChild(div);
        div.querySelector('textarea').focus();
    }
</script>
</body>
</html>