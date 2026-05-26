<?php
session_start();
$password_secreta = "admin123"; // Tu contraseña
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($_POST['password'] === $password_secreta) {
        $_SESSION['usuario_logueado'] = true;
        header("Location: admin/index.php");
        exit;
    } else {
        $error = "Contraseña incorrecta.";
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { height: 100vh; display: flex; align-items: center; justify-content: center; background-color: var(--bs-body-bg); }
        .login-box { max-width: 350px; width: 100%; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); background-color: var(--bs-body-bg); border: 1px solid var(--bs-border-color); }
        .btn-theme { position: absolute; top: 20px; right: 20px; }
    </style>
</head>
<body>

    <button class="btn btn-outline-secondary btn-sm btn-theme" id="btnTema" onclick="cambiarTema()">🌗 Tema</button>

    <div class="login-box text-center">
        <h2 class="mb-4">🔒 Zona Admin</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger btn-sm"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <input type="password" name="password" class="form-control form-control-lg" placeholder="Contraseña..." required autofocus>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">Ingresar</button>
            </div>
        </form>
        <div class="mt-4">
            <a href="index.php" class="text-decoration-none text-muted">← Volver al Buscador</a>
        </div>
    </div>

    <script>
        // Lógica Global de Tema (Se repite en los otros archivos para mantener consistencia)
        const html = document.documentElement;
        const savedTheme = localStorage.getItem('admin_theme') || 'light';
        html.setAttribute('data-bs-theme', savedTheme);

        function cambiarTema() {
            const currentTheme = html.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('admin_theme', newTheme);
        }
    </script>
</body>
</html>