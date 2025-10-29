<?php
/**
 * ========================================================================
 * CARRITO DE COMPRAS - Tienda Seda y Lino
 * ========================================================================
 * Gestiona el carrito de compras del usuario
 * - Agregar productos al carrito
 * - Visualizar productos en el carrito
 * - Modificar cantidades de productos
 * - Eliminación de productos del carrito
 * - Calcular totales (subtotal, envío, total)
 * 
 * Funciones principales:
 * - agregarAlCarrito(): Agrega producto con talle, color y cantidad
 * - actualizarCantidad(): Modifica cantidad de un producto
 * - eliminarProducto(): Elimina un producto del carrito
 * - calcularTotal(): Calcula el total del pedido
 * 
 * Variables principales:
 * - $_SESSION['carrito']: Array con productos en el carrito
 * - $total: Total calculado del pedido
 * 
 * Sistema: Basado en $_SESSION (no se guarda en BD hasta checkout)
 * Tablas utilizadas: Productos (solo lectura para mostrar datos)
 * ========================================================================
 */

session_start();

require_once 'config/database.php';

// Configurar título de la página
$titulo_pagina = 'Carrito de Compras';

// Inicializar carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = array();
}

/**
 * Procesar acciones del carrito (agregar, eliminar, actualizar)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // AGREGAR producto al carrito
    if (isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
        $id_producto = (int)$_POST['id_producto'];
        $talla = $_POST['talla'] ?? '';
        $color = $_POST['color'] ?? '';
        $cantidad = (int)$_POST['cantidad'];
        
        if ($id_producto > 0 && $talla && $color && $cantidad > 0) {
            // Clave única para cada variante en el carrito
            $clave_carrito = $id_producto . '-' . $talla . '-' . $color;
            
            // Si ya existe, sumar cantidad; si no, crear nueva entrada
            if (isset($_SESSION['carrito'][$clave_carrito])) {
                $_SESSION['carrito'][$clave_carrito]['cantidad'] += $cantidad;
            } else {
                $_SESSION['carrito'][$clave_carrito] = array(
                    'id_producto' => $id_producto,
                    'talla' => $talla,
                    'color' => $color,
                    'cantidad' => $cantidad
                );
            }
            
            $_SESSION['mensaje_carrito'] = "Producto agregado al carrito";
        } else {
            $_SESSION['mensaje_carrito'] = "Error: Datos inválidos para agregar al carrito";
        }
        
        header('Location: carrito.php');
        exit;
    }
    
    // ELIMINAR producto del carrito
    if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
        $clave = $_POST['clave'] ?? '';
        if (isset($_SESSION['carrito'][$clave])) {
            unset($_SESSION['carrito'][$clave]);
            $_SESSION['mensaje_carrito'] = "Producto eliminado del carrito";
        }
        
        header('Location: carrito.php');
        exit;
    }
    
    // ACTUALIZAR cantidad de un producto
    if (isset($_POST['accion']) && $_POST['accion'] === 'actualizar') {
        $clave = $_POST['clave'] ?? '';
        $nueva_cantidad = (int)$_POST['cantidad'];
        
        if (isset($_SESSION['carrito'][$clave]) && $nueva_cantidad > 0) {
            $_SESSION['carrito'][$clave]['cantidad'] = $nueva_cantidad;
            $_SESSION['mensaje_carrito'] = "Cantidad actualizada";
        }
        
        header('Location: carrito.php');
        exit;
    }
    
    // VACIAR todo el carrito
    if (isset($_POST['accion']) && $_POST['accion'] === 'vaciar') {
        $_SESSION['carrito'] = array();
        $_SESSION['mensaje_carrito'] = "Carrito vaciado";
        
        header('Location: carrito.php');
        exit;
    }
}

/**
 * Obtener información completa de los productos en el carrito
 */
$productos_carrito = array();
$total_carrito = 0;

if (!empty($_SESSION['carrito'])) {
    foreach ($_SESSION['carrito'] as $clave => $item) {
        // Consultar datos del producto desde la base de datos
        $sql = "
            SELECT 
                p.id_producto,
                p.nombre_producto,
                p.precio_actual,
                fp.foto_prod_miniatura
            FROM Productos p
            LEFT JOIN Fotos_Producto fp ON p.id_producto = fp.id_producto
            WHERE p.id_producto = :id_producto
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_producto', $item['id_producto'], PDO::PARAM_INT);
        $stmt->execute();
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($producto) {
            // Calcular subtotal
            $subtotal = $producto['precio_actual'] * $item['cantidad'];
            $total_carrito += $subtotal;
            
            // Agregar datos del carrito
            $productos_carrito[] = array(
                'clave' => $clave,
                'id_producto' => $producto['id_producto'],
                'nombre_producto' => $producto['nombre_producto'],
                'precio_actual' => $producto['precio_actual'],
                'foto_prod_miniatura' => $producto['foto_prod_miniatura'],
                'talla' => $item['talla'],
                'color' => $item['color'],
                'cantidad' => $item['cantidad'],
                'subtotal' => $subtotal
            );
        }
    }
}

// Obtener mensaje si existe y luego eliminarlo
$mensaje = $_SESSION['mensaje_carrito'] ?? '';
if ($mensaje) {
    unset($_SESSION['mensaje_carrito']);
}
?>


<?php include 'includes/header.php'; ?>

<!-- Contenido del carrito -->
    <div style="display:none">
        <nav class="navbar navbar-expand-lg bg-body-tertiary">
            <div class="container-fluid">
                <a class="navbar-brand nombre-tienda" href="index.php">SEDA Y LINO</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
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
                                <?php if (count($_SESSION['carrito']) > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo count($_SESSION['carrito']); ?>
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

    <main class="container my-5">
        <h1 class="mb-4">
            <i class="fas fa-shopping-cart me-2"></i>
            Carrito de Compras
        </h1>

        <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensaje); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (empty($productos_carrito)): ?>
        <!-- Carrito vacío -->
        <div class="text-center py-5">
            <i class="fas fa-shopping-cart fa-5x text-muted mb-4"></i>
            <h3 class="text-muted mb-4">Tu carrito está vacío</h3>
            <p class="mb-4">¡Agrega productos para comenzar a comprar!</p>
            <a href="index.php#productos" class="btn boton-tarjeta btn-lg">
                <i class="fas fa-shopping-bag me-2"></i>Ver Productos
            </a>
        </div>
        <?php else: ?>
        <!-- Productos en el carrito -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Productos (<?php echo count($productos_carrito); ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($productos_carrito as $producto): ?>
                        <div class="row g-0 border-bottom p-3 align-items-center text-center text-md-start">
                            <!-- Información del producto -->
                            <div class="col-md-5 d-flex flex-column align-items-center align-items-md-start">
                                <h6 class="mb-2">
                                    <a href="detalle-producto.php?id=<?php echo $producto['id_producto']; ?>" class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($producto['nombre_producto']); ?>
                                    </a>
                                </h6>
                                <p class="text-muted mb-1 small">
                                    <strong>Talla:</strong> <?php echo htmlspecialchars($producto['talla']); ?>
                                </p>
                                <p class="text-muted mb-0 small">
                                    <strong>Color:</strong> <?php echo htmlspecialchars($producto['color']); ?>
                                </p>
                            </div>
                            
                            <!-- Precio unitario -->
                            <div class="col-md-2 d-flex flex-column align-items-center justify-content-center">
                                <small class="text-muted">Precio</small>
                                <strong>$<?php echo number_format($producto['precio_actual'], 2); ?></strong>
                            </div>
                            
                            <!-- Cantidad -->
                            <div class="col-md-2 d-flex align-items-center justify-content-center">
                                <form method="POST" action="carrito.php" class="d-inline">
                                    <input type="hidden" name="accion" value="actualizar">
                                    <input type="hidden" name="clave" value="<?php echo htmlspecialchars($producto['clave']); ?>">
                                    <div class="input-group input-group-sm">
                                        <input type="number" 
                                               name="cantidad" 
                                               class="form-control text-center" 
                                               value="<?php echo $producto['cantidad']; ?>" 
                                               min="1" 
                                               max="10"
                                               onchange="this.form.submit()">
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Subtotal -->
                            <div class="col-md-3">
                                <div class="d-flex flex-column flex-md-row align-items-center justify-content-center gap-2">
                                    <div class="text-center">
                                        <small class="text-muted d-block">Subtotal</small>
                                        <strong class="text-primary">$<?php echo number_format($producto['subtotal'], 2); ?></strong>
                                    </div>

                                    <!-- Botón eliminar -->
                                    <form method="POST" action="carrito.php" class="d-inline">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="clave" value="<?php echo htmlspecialchars($producto['clave']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger d-flex align-items-center justify-content-center" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Botón vaciar carrito -->
                <form method="POST" action="carrito.php" class="mb-4">
                    <input type="hidden" name="accion" value="vaciar">
                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('¿Estás seguro de vaciar el carrito?')">
                        <i class="fas fa-trash-alt me-2"></i>Vaciar Carrito
                    </button>
                </form>
            </div>

            <!-- Resumen del pedido -->
            <div class="col-lg-4">
                <div class="card shadow-sm sticky-card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Resumen del Pedido</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <strong>$<?php echo number_format($total_carrito, 2); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Envío:</span>
                            <span class="text-success">GRATIS</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <h5>Total:</h5>
                            <h5 class="text-primary">$<?php echo number_format($total_carrito, 2); ?></h5>
                        </div>

                        <?php if (isset($_SESSION['id_usuario'])): ?>
                        <!-- Usuario logueado: ir a checkout -->
                        <a href="checkout.php" class="btn btn-success w-100 mb-2">
                            <i class="fas fa-credit-card me-2"></i>Proceder al Pago
                        </a>
                        <?php else: ?>
                        <!-- Usuario no logueado: sugerir login -->
                        <a href="login.php" class="btn btn-success w-100 mb-2">
                            <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión para Comprar
                        </a>
                        <?php endif; ?>
                        
                        <a href="index.php#productos" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-arrow-left me-2"></i>Seguir Comprando
                        </a>
                    </div>
                </div>

                <!-- Información adicional -->
                <div class="card shadow-sm mt-3">
                    <div class="card-body">
                        <h6 class="mb-3">
                            <i class="fas fa-shield-alt text-success me-2"></i>
                            Compra Segura
                        </h6>
                        <ul class="list-unstyled small mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Envío gratis en todas las compras
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                30 días para devoluciones
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Pago seguro
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <footer class="footer-completo mt-5">
        <div class="container">
            <div class="row py-5">
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">SEDA Y LINO</h5>
                    <p class="footer-texto">Elegancia que viste tus momentos. Prendas únicas de seda y lino con calidad artesanal.</p>
                </div>

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
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Productos</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Camisas" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Camisas
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Pantalones" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Pantalones
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Contacto</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-3">
                            <i class="fas fa-envelope me-2"></i>
                            <a href="mailto:info@sedaylino.com" class="footer-link">info@sedaylino.com</a>
                        </li>
                    </ul>
                </div>
            </div>

            <hr class="footer-divider">

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<?php include 'includes/footer.php'; render_footer(); ?>

