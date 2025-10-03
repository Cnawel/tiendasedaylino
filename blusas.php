<?php
/**
 * BLUSAS - SISTEMA E-COMMERCE SEDA Y LINO
 * =======================================
 * 
 * Página dinámica que muestra todas las blusas disponibles
 * en la tienda, obteniendo los datos directamente de la base de datos.
 * 
 * @author      Sistema E-commerce Seda y Lino
 * @version     1.0.0
 * @since       2025-01-27
 * @license     MIT
 */

// ============================================================================
// CONFIGURACIÓN DE BASE DE DATOS
// ============================================================================

$host = 'localhost';
$dbname = 'tiendasedaylino_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// ============================================================================
// CONSULTA DE PRODUCTOS DE BLUSAS
// ============================================================================

$sql = "
    SELECT 
        p.id_producto,
        p.nombre_producto,
        p.descripcion_producto,
        p.precio_actual,
        p.genero,
        fp.foto_prod_miniatura
    FROM Productos p
    LEFT JOIN Fotos_Producto fp ON p.id_producto = fp.id_producto
    WHERE p.id_categoria = 3
    ORDER BY p.genero, p.nombre_producto
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blusas - Seda y Lino</title>
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Bootstrap 5.3.8 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6.0.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <!-- ============================================================================
         HEADER Y NAVEGACIÓN PRINCIPAL
         ============================================================================ -->
    
    <header>
        <nav class="navbar navbar-expand-lg bg-body-tertiary">
            <div class="container-fluid">
                <!-- Logo/Brand de la tienda -->
                <a class="navbar-brand nombre-tienda" href="index.html">SEDA Y LINO</a>
                
                <!-- Botón hamburguesa para dispositivos móviles -->
                <button class="navbar-toggler" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#navbarNav" 
                        aria-controls="navbarNav" 
                        aria-expanded="false" 
                        aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <!-- Menú de navegación colapsable -->
                <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                    <ul class="navbar-nav lista-nav">
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.html">INICIO</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="nosotros.html">NOSOTROS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.html#productos">PRODUCTOS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.html#contacto">CONTACTO</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php" title="Iniciar Sesión">
                                <i class="fas fa-user-circle" style="font-size: 1.5rem; color: #2c3e50;"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- ============================================================================
         CONTENIDO PRINCIPAL
         ============================================================================ -->
    
    <main class="productos">
        <!-- Hero Section -->
        <div class="hero-blusas">
            <div class="container">
                <div class="row align-items-center min-vh-50">
                    <div class="col-lg-6">
                        <h1 class="display-4 fw-bold text-white mb-4">
                            Blusas de Seda y Lino
                        </h1>
                        <p class="lead text-white mb-4">
                            Descubre nuestra colección de blusas de seda y lino de alta calidad, 
                            perfectas para cualquier ocasión. Elegancia y comodidad en cada prenda.
                        </p>
                        <div class="d-flex gap-3">
                            <span class="badge bg-light text-dark fs-6 px-3 py-2">
                                <i class="fas fa-leaf me-2"></i>Seda & Lino
                            </span>
                            <span class="badge bg-light text-dark fs-6 px-3 py-2">
                                <i class="fas fa-cut me-2"></i>Corte Perfecto
                            </span>
                        </div>
                    </div>
                    <div class="col-lg-6 text-center">
                        <img src="imagenes/productos/blusas/blusa_mujer_beige.png" 
                             alt="Blusas de Seda y Lino" 
                             class="img-fluid hero-image">
                    </div>
                </div>
            </div>
        </div>

        <!-- Productos Section -->
        <div class="container py-5">
            <div class="row mb-5 reveal">
                <div class="col-12 text-center">
                    <h2 class="titulo-productos mb-3">Nuestra Colección</h2>
                    <p class="descripcion-productos">Blusas elegantes para mujer</p>
                    <div class="d-flex justify-content-center">
                        <div class="linea-decorativa"></div>
                    </div>
                </div>
            </div>

            <?php if (empty($productos)): ?>
                <!-- Mensaje cuando no hay productos -->
                <div class="row">
                    <div class="col-12 text-center">
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle fa-2x mb-3"></i>
                            <h4>Próximamente</h4>
                            <p>Estamos trabajando en traerte las mejores blusas de seda y lino.</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Grid de productos -->
                <div class="row g-4">
                    <?php foreach ($productos as $index => $producto): ?>
                    <div class="col-lg-4 col-md-6 reveal" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                        <div class="card producto-card h-100 shadow-sm">
                            <!-- Imagen del producto -->
                            <div class="producto-imagen-container">
                                <img src="<?php echo htmlspecialchars($producto['foto_prod_miniatura'] ?: 'imagenes/imagen.png'); ?>" 
                                     class="card-img-top producto-imagen" 
                                     alt="<?php echo htmlspecialchars($producto['nombre_producto']); ?>">
                                <div class="producto-overlay">
                                    <button class="btn btn-light btn-sm" onclick="verDetalle(<?php echo $producto['id_producto']; ?>)">
                                        <i class="fas fa-eye me-1"></i>Ver Detalles
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Contenido de la tarjeta -->
                            <div class="card-body d-flex flex-column">
                                <div class="mb-3">
                                    <span class="badge bg-primary mb-2">
                                        <?php echo ucfirst($producto['genero']); ?>
                                    </span>
                                    <h5 class="card-title titulo-tarjeta mb-2">
                                        <?php echo htmlspecialchars($producto['nombre_producto']); ?>
                                    </h5>
                                    <p class="card-text texto-tarjeta-simple text-muted">
                                        <?php echo htmlspecialchars(substr($producto['descripcion_producto'], 0, 80)) . '...'; ?>
                                    </p>
                                </div>
                                
                                <!-- Precio -->
                                <div class="precio-simple mb-3">
                                    <span class="precio-actual-simple fs-4 fw-bold text-success">
                                        $<?php echo number_format($producto['precio_actual'], 0, ',', '.'); ?>
                                    </span>
                                </div>
                                
                                <!-- Botones de acción -->
                                <div class="mt-auto">
                                    <div class="d-grid gap-2">
                                        <button class="btn boton-comprar-simple" 
                                                onclick="verDetalle(<?php echo $producto['id_producto']; ?>)">
                                            <i class="fas fa-shopping-cart me-2"></i>Ver Detalles
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Características destacadas -->
        <div class="caracteristicas-destacadas py-5">
            <div class="container">
                <div class="row text-center">
                    <div class="col-md-4 mb-4 reveal">
                        <div class="caracteristica-item">
                            <div class="icono-wrapper">
                                <i class="fas fa-leaf fa-3x text-success mb-3"></i>
                            </div>
                            <h5>100% Natural</h5>
                            <p class="text-muted">Seda y lino de la más alta calidad, transpirable y ecológico</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4 reveal" style="animation-delay: 0.2s;">
                        <div class="caracteristica-item">
                            <div class="icono-wrapper">
                                <i class="fas fa-cut fa-3x text-primary mb-3"></i>
                            </div>
                            <h5>Corte Perfecto</h5>
                            <p class="text-muted">Diseños que se adaptan a tu cuerpo con elegancia</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4 reveal" style="animation-delay: 0.4s;">
                        <div class="caracteristica-item">
                            <div class="icono-wrapper">
                                <i class="fas fa-palette fa-3x text-warning mb-3"></i>
                            </div>
                            <h5>Colores Únicos</h5>
                            <p class="text-muted">Tonos seleccionados para combinar con cualquier outfit</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- ============================================================================
         FOOTER
         ============================================================================ -->
    
    <footer>
        <a href="https://www.facebook.com/?locale=es_LA" target="_blank">
            <img class="red-social" src="iconos/facebook.png" alt="icono de facebook">
        </a>
        <a href="https://www.instagram.com/" target="_blank">
            <img class="red-social" src="iconos/instagram.png" alt="icono de instagram">
        </a>
        <a href="https://x.com/?lang=es" target="_blank">
            <img class="red-social" src="iconos/x.png" alt="icono de x">
        </a>
        <h6>2025.Todos los derechos reservados</h6>
    </footer>

    <!-- ============================================================================
         SCRIPTS
         ============================================================================ -->
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript personalizado -->
    <script>
        /**
         * FUNCIÓN: Ver detalles del producto
         * 
         * Redirige a la página de detalle del producto seleccionado
         * 
         * @param {number} id - ID del producto
         */
        function verDetalle(id) {
            window.location.href = `detalle-producto.php?id=${id}`;
        }

        /**
         * FUNCIÓN: Scroll Reveal Animation
         * 
         * Anima elementos cuando entran en el viewport
         */
        function initScrollReveal() {
            const reveals = document.querySelectorAll('.reveal');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('active');
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });

            reveals.forEach(reveal => {
                observer.observe(reveal);
            });
        }

        // Inicializar cuando el DOM esté cargado
        document.addEventListener('DOMContentLoaded', function() {
            initScrollReveal();
        });
    </script>
</body>
</html>
