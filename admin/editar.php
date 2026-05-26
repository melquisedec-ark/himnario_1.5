<?php
session_start();
if (!isset($_SESSION['usuario_logueado'])) { header("Location: ../login.php"); exit; }
include '../includes/db.php';
$id = $_GET['id'];
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $numero = $_POST['numero'];
    $titulo = $_POST['titulo'];
    $tipos = isset($_POST['tipo']) ? $_POST['tipo'] : [];
    $letras = isset($_POST['contenido']) ? $_POST['contenido'] : [];

    $stmt = $conexion->prepare("UPDATE himnos SET numero = ?, titulo = ? WHERE id = ?");
    $stmt->bind_param("isi", $numero, $titulo, $id);
    if ($stmt->execute()) {
        $conexion->query("DELETE FROM estrofas WHERE himno_id = $id");
        $stmt_ins = $conexion->prepare("INSERT INTO estrofas (himno_id, tipo, orden, contenido) VALUES (?, ?, ?, ?)");
        for ($i = 0; $i < count($tipos); $i++) {
            $orden = $i + 1;
            $stmt_ins->bind_param("isis", $id, $tipos[$i], $orden, $letras[$i]);
            $stmt_ins->execute();
        }
        header("Location: index.php"); exit;
    } else { $error = "Error: " . $conexion->error; }
}

$himno = $conexion->query("SELECT * FROM himnos WHERE id = $id")->fetch_assoc();
$res_estrofas = $conexion->query("SELECT * FROM estrofas WHERE himno_id = $id ORDER BY orden ASC");
$estrofas_existentes = [];
while($row = $res_estrofas->fetch_assoc()) { $estrofas_existentes[] = $row; }
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Editar Himno</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
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
        <h2>✏️ Editar Himno</h2>
        <div>
            <button class="btn btn-outline-secondary btn-sm me-2" onclick="cambiarTema()">🌗 Tema</button>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Cancelar</a>
        </div>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Número</label>
                        <input type="number" name="numero" class="form-control" value="<?php echo $himno['numero']; ?>" required>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control" value="<?php echo $himno['titulo']; ?>" required>
                    </div>
                </div>

                <hr>
                <div id="contenedor-estrofas"></div>

                <div class="d-flex gap-2 mb-4 mt-3">
                    <button type="button" class="btn btn-outline-primary" onclick="agregarBloque('verso')">+ Verso</button>
                    <button type="button" class="btn btn-outline-warning" onclick="agregarBloque('coro')">+ Coro</button>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">💾 Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('admin_theme') || 'light';
    html.setAttribute('data-bs-theme', savedTheme);

    function cambiarTema() {
        const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-bs-theme', next);
        localStorage.setItem('admin_theme', next);
    }

    const contenedor = document.getElementById('contenedor-estrofas');
    function agregarBloque(tipo, contenido = '') {
        const div = document.createElement('div');
        div.className = 'estrofa-box';
        const etiqueta = tipo === 'verso' ? '<span class="badge bg-primary">VERSO</span>' : '<span class="badge bg-warning text-dark">CORO</span>';
        div.innerHTML = `
            <div class="d-flex justify-content-between mb-2">${etiqueta}<button type="button" class="btn-eliminar" onclick="this.parentElement.parentElement.remove()">✕</button></div>
            <input type="hidden" name="tipo[]" value="${tipo}">
            <textarea name="contenido[]" class="form-control" rows="4" required>${contenido}</textarea>
        `;
        contenedor.appendChild(div);
    }

    const datosGuardados = <?php echo json_encode($estrofas_existentes); ?>;
    datosGuardados.forEach(item => agregarBloque(item.tipo, item.contenido));
</script>
</body>
</html>