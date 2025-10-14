<?php
/**
 * ========================================================================
 * PÁGINA CATÁLOGO - Tienda Seda y Lino
 * ========================================================================
 * Muestra productos filtrados por categoría
 * 
 * @author Tienda Seda y Lino
 * @version 1.0
 * ========================================================================
 */
session_start();
require_once 'config/database.php';

// Obtener categoría desde URL
$categoria_nombre = $_GET['categoria'] ?? 'todos';
$categoria_id = null;
$titulo_categoria = 'Todos los Productos';

// Obtener ID de categoría si se especificó
if ($categoria_nombre !== 'todos') {
    $stmt = $mysqli->prepare("SELECT id_categoria, nombre_categoria FROM Categorias WHERE nombre_categoria = ? LIMIT 1");
    $stmt->bind_param('s', $categoria_nombre);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $categoria_id = $row['id_categoria'];
        $titulo_categoria = htmlspecialchars($row['nombre_categoria']);
    }
}

// Construir consulta de productos
if ($categoria_id) {
    $sql = "SELECT p.id_producto, p.nombre_producto, p.descripcion_producto, p.precio_actual, 
                   c.nombre_categoria, fp.foto_prod_miniatura
            FROM Productos p
            LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
            LEFT JOIN Fotos_Producto fp ON p.id_producto = fp.id_producto
            WHERE p.id_categoria = ?
            ORDER BY p.nombre_producto";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $categoria_id);
} else {
    $sql = "SELECT p.id_producto, p.nombre_producto, p.descripcion_producto, p.precio_actual, 
                   c.nombre_categoria, fp.foto_prod_miniatura
            FROM Productos p
            LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
            LEFT JOIN Fotos_Producto fp ON p.id_producto = fp.id_producto
            ORDER BY c.nombre_categoria, p.nombre_producto";
    $stmt = $mysqli->prepare($sql);
}

$stmt->execute();
$productos = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_categoria ?> | Seda y Lino</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css?v=2.0">
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg bg-body-tertiary">
            <div class="container-fluid">
                <a class="navbar-brand nombre-tienda" href="index.php">SEDA Y LINO</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                    <ul class="navbar-nav lista-nav">
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.php">INICIO</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="nosotros.php">NOSOTROS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.php#productos">PRODUCTOS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.php#contacto">CONTACTO</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="carrito.php" title="Carrito">
                                <i class="fas fa-shopping-cart fa-lg"></i>
                                <?php 
                                $num_items_carrito = isset($_SESSION['carrito']) ? count($_SESSION['carrito']) : 0;
                                if ($num_items_carrito > 0): 
                                ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $num_items_carrito; ?>
                                </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <?php if (isset($_SESSION['id_usuario'])): ?>
                                <a class="nav-link" href="perfil.php" title="Mi Perfil">
                                    <img src="iconos/avatar-usuario.png" alt="icono de avatar de usuario">
                                </a>
                            <?php else: ?>
                                <a class="nav-link" href="login.php" title="Iniciar Sesión">
                                    <img src="iconos/avatar-usuario.png" alt="icono de avatar de usuario">
                                </a>
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="productos">
        <div class="container mt-4">
            <h1 class="titulo-productos text-center mb-4"><?= strtoupper($titulo_categoria) ?></h1>
            <p class="text-center mb-5 descripcion-productos">Descubre nuestra colección de productos elegantes</p>
            
            <!-- Filtros por categoría -->
            <div class="mb-4 text-center">
                <a href="catalogo.php?categoria=todos" class="btn btn-outline-dark btn-sm me-2 mb-2 filtro-categoria <?= $categoria_nombre === 'todos' ? 'active' : '' ?>">Todos</a>
                <a href="catalogo.php?categoria=Camisas" class="btn btn-outline-dark btn-sm me-2 mb-2 filtro-categoria <?= $categoria_nombre === 'Camisas' ? 'active' : '' ?>">Camisas</a>
                <a href="catalogo.php?categoria=Blusas" class="btn btn-outline-dark btn-sm me-2 mb-2 filtro-categoria <?= $categoria_nombre === 'Blusas' ? 'active' : '' ?>">Blusas</a>
                <a href="catalogo.php?categoria=Pantalones" class="btn btn-outline-dark btn-sm me-2 mb-2 filtro-categoria <?= $categoria_nombre === 'Pantalones' ? 'active' : '' ?>">Pantalones</a>
                <a href="catalogo.php?categoria=Shorts" class="btn btn-outline-dark btn-sm me-2 mb-2 filtro-categoria <?= $categoria_nombre === 'Shorts' ? 'active' : '' ?>">Shorts</a>
            </div>

            <div class="row">
                <?php if ($productos->num_rows > 0): ?>
                    <?php while ($producto = $productos->fetch_assoc()): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                            <div class="card tarjeta h-100 shadow-sm">
                                <a href="detalle-producto.php?id=<?= $producto['id_producto'] ?>" class="text-decoration-none">
                                    <?php 
                                    $imagen = $producto['foto_prod_miniatura'] ?? 'imagenes/imagen.png';
                                    ?>
                                    <img src="<?= htmlspecialchars($imagen) ?>" class="card-img-top" alt="<?= htmlspecialchars($producto['nombre_producto']) ?>">
                                    <div class="card-body text-center">
                                        <h5 class="card-title titulo-tarjeta"><?= htmlspecialchars($producto['nombre_producto']) ?></h5>
                                        <p class="card-text texto-tarjeta"><?= htmlspecialchars(substr($producto['descripcion_producto'], 0, 80)) ?>...</p>
                                        <div class="precio-simple mb-3">
                                            <span class="precio-actual-simple">$<?= number_format($producto['precio_actual'], 2) ?></span>
                                        </div>
                                        <span class="btn boton-tarjeta btn-sm">Ver Detalles</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <p class="text-muted">No hay productos disponibles en esta categoría.</p>
                        <a href="catalogo.php?categoria=todos" class="btn btn-dark">Ver todos los productos</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="footer-completo">
        <div class="container">
            <div class="row py-5">
                <!-- Columna 1: Sobre Nosotros -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">SEDA Y LINO</h5>
                    <p class="footer-texto">Elegancia que viste tus momentos. Prendas únicas de seda y lino con calidad artesanal.</p>
                    <div class="footer-redes mt-3">
                        <a href="https://www.facebook.com/?locale=es_LA" target="_blank" rel="noopener noreferrer" class="footer-red-social me-2" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" class="footer-red-social me-2" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://x.com/?lang=es" target="_blank" rel="noopener noreferrer" class="footer-red-social" title="X (Twitter)">
                            <i class="fab fa-x-twitter"></i>
                        </a>
                    </div>
                </div>

                <!-- Columna 2: Navegación -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Navegación</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-2">
                            <a href="index.php" class="footer-link">
                                <i class="fas fa-home me-2"></i>Inicio
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="nosotros.php" class="footer-link">
                                <i class="fas fa-users me-2"></i>Nosotros
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=todos" class="footer-link">
                                <i class="fas fa-shopping-bag me-2"></i>Catálogo
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="index.php#contacto" class="footer-link">
                                <i class="fas fa-envelope me-2"></i>Contacto
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Columna 3: Categorías -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Productos</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Camisas" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Camisas
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Blusas" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Blusas
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Pantalones" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Pantalones
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Shorts" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Shorts
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Columna 4: Información de Contacto -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Contacto</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-3">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            <span class="footer-texto-small">Buenos Aires, Argentina</span>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-phone me-2"></i>
                            <a href="tel:+541112345678" class="footer-link">+54 11 1234-5678</a>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-envelope me-2"></i>
                            <a href="mailto:info@sedaylino.com" class="footer-link">info@sedaylino.com</a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Línea divisoria -->
            <hr class="footer-divider">

            <!-- Footer inferior -->
            <div class="row py-3">
                <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                    <p class="footer-copyright mb-0">
                        <i class="fas fa-copyright me-1"></i> 2025 Seda y Lino. Todos los derechos reservados
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="terminos.php" class="footer-link-small me-3">Términos y Condiciones</a>
                    <a href="privacidad.php" class="footer-link-small">Política de Privacidad</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- UX Mejoras Simples -->
    <script src="js/ux-mejoras.js"></script>
</body>
</html>

