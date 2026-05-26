<?php
include 'includes/db.php';

$busqueda = "";
$resultados = [];

// Lógica de búsqueda (Idéntica a la que ya tenías, solo cambiamos el "traje" visual)
if (isset($_GET['q'])) {
    $busqueda = $_GET['q'];
    // Buscamos en Número, Título o Contenido
    $sql = "SELECT DISTINCT h.id, h.numero, h.titulo 
            FROM himnos h 
            LEFT JOIN estrofas e ON h.id = e.himno_id 
            WHERE h.numero LIKE ? OR h.titulo LIKE ? OR e.contenido LIKE ?
            ORDER BY h.numero ASC";
            
    $stmt = $conexion->prepare($sql);
    $termino = "%" . $busqueda . "%";
    $stmt->bind_param("sss", $termino, $termino, $termino);
    $stmt->execute();
    $resultados = $stmt->get_result();
} else {
    // Mostrar los primeros 20 para no saturar la pantalla al inicio
    $sql = "SELECT * FROM himnos ORDER BY numero ASC LIMIT 20";
    $resultados = $conexion->query($sql);
}
?>

<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Himnario Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Estilos personalizados sutiles */
        body { transition: background-color 0.3s, color 0.3s; }
        .search-container { max-width: 800px; margin: 50px auto; }
        .himno-card { 
            cursor: pointer; transition: transform 0.2s; 
            text-decoration: none; color: inherit;
        }
        .himno-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        
        /* Ajuste para que los links no se vean azules/feos en modo oscuro */
        a.himno-link { text-decoration: none; color: inherit; display: block; }
        
        .badge-numero { font-size: 1.1em; width: 50px; text-align: center; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">📖 Himnario Seleccionado</a>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" onclick="cambiarTema()">🌗 Tema</button>
                <a href="admin/index.php" class="btn btn-outline-primary btn-sm">Admin</a>
            </div>
        </div>
    </nav>

    <div class="container search-container">
        
        <div class="text-center mb-5">
            <h1 class="display-4 fw-bold">Buscar Alabanza</h1>
            <p class="text-muted">Encuentra por número, título o letra</p>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <form action="index.php" method="GET" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control form-control-lg" placeholder="Escribe aquí... (Ej: 'Es mi rey' o '15')" value="<?php echo htmlspecialchars($busqueda); ?>" autofocus>
                    <button type="submit" class="btn btn-primary btn-lg px-4">🔍</button>
                </form>
            </div>
        </div>

        <div class="list-group shadow-sm">
            <?php if ($resultados && $resultados->num_rows > 0): ?>
                <?php while($fila = $resultados->fetch_assoc()): ?>
                    
                    <a href="presentacion.php?id=<?php echo $fila['id']; ?>" class="list-group-item list-group-item-action d-flex align-items-center p-3 himno-link">
                        <span class="badge bg-primary rounded-pill badge-numero me-3"><?php echo $fila['numero']; ?></span>
                        <div class="flex-grow-1">
                            <h5 class="mb-0 fw-bold"><?php echo $fila['titulo']; ?></h5>
                        </div>
                        <span class="text-muted small">Ver ▶</span>
                    </a>

                <?php endwhile; ?>
            <?php else: ?>
                <div class="list-group-item p-5 text-center text-muted">
                    <h4>😕 No encontramos nada</h4>
                    <p>Intenta buscar con otra palabra o verifica el número.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script>
        // Lógica de Modo Oscuro (Independiente del admin para gusto del usuario público)
        const html = document.documentElement;
        const savedTheme = localStorage.getItem('public_theme') || 'light';
        html.setAttribute('data-bs-theme', savedTheme);

        function cambiarTema() {
            const current = html.getAttribute('data-bs-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-bs-theme', next);
            localStorage.setItem('public_theme', next);
        }
    </script>

</body>
</html>