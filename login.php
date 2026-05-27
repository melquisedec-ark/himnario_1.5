<?php
/**
 * login.php - Acceso al panel administrativo
 * Verifica credenciales contra la tabla usuarios usando password_verify()
 */
session_start();

// Si ya está logueado, redirigir al panel
if (isset($_SESSION['usuario_logueado'])) {
    header("Location: admin/index.php");
    exit;
}

require_once 'includes/db.php';
require_once 'includes/funciones.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Por favor, complete todos los campos.";
    } else {
        // Buscar usuario por username
        $stmt = $conexion->prepare("SELECT id, username, password_hash, nombre, rol FROM usuarios WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado && $resultado->num_rows === 1) {
            $usuario = $resultado->fetch_assoc();

            if (password_verify($password, $usuario['password_hash'])) {
                // Credenciales correctas
                $_SESSION['usuario_logueado'] = true;
                $_SESSION['usuario_id'] = (int)$usuario['id'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_rol'] = $usuario['rol'];

                header("Location: admin/index.php");
                exit;
            } else {
                $error = "Credenciales incorrectas.";
            }
        } else {
            $error = "Credenciales incorrectas.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Admin - Himnario Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Patua+One&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
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
    <style>
        body { height: 100vh; display: flex; align-items: center; justify-content: center; background-color: var(--bs-body-bg); }
        .login-box { max-width: 380px; width: 100%; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); background-color: var(--bs-body-bg); border: 1px solid var(--bs-border-color); }
        .btn-theme { position: absolute; top: 20px; right: 20px; }
    </style>
</head>
<body>

    <button class="btn btn-outline-secondary btn-sm btn-theme" onclick="Himnario.toggleTheme()">🌗 Tema</button>

    <div class="login-box text-center animate-fadeIn">
        <h2 class="mb-1 fw-bold">Himnario Digital</h2>
        <p class="text-muted mb-4">Acceso al panel administrativo</p>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?php echo sanitizar($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3 text-start">
                <label class="form-label small fw-bold">Usuario</label>
                <input type="text" name="username" class="form-control form-control-lg" placeholder="Nombre de usuario..." required autofocus>
            </div>
            <div class="mb-3 text-start">
                <label class="form-label small fw-bold">Contraseña</label>
                <input type="password" name="password" class="form-control form-control-lg" placeholder="Contraseña..." required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">Ingresar</button>
            </div>
        </form>
        <div class="mt-4">
            <a href="index.php" class="text-decoration-none text-muted small">← Volver al Buscador</a>
        </div>
    </div>

    <script src="js/app.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Himnario.initPage('login');
        });
    </script>
</body>
</html>
