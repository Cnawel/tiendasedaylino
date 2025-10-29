<?php
/**
 * ========================================================================
 * CHECKOUT - Tienda Seda y Lino
 * ========================================================================
 * Página de finalización de compra
 * - Verificación de usuario logueado
 * - Confirmación de datos de envío
 * - Selección de método de pago
 * - Resumen del pedido antes de confirmar
 * 
 * @author Tienda Seda y Lino
 * @version 1.0
 */

session_start();

require_once 'config/database.php';

// Configurar título de la página
$titulo_pagina = 'Checkout';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['id_usuario'])) {
    $_SESSION['mensaje_error'] = "Debes iniciar sesión para continuar con la compra";
    header('Location: login.php');
    exit;
}

// Verificar que el carrito tenga productos
if (!isset($_SESSION['carrito']) || empty($_SESSION['carrito'])) {
    $_SESSION['mensaje_error'] = "Tu carrito está vacío";
    header('Location: carrito.php');
    exit;
}

/**
 * Obtener datos del usuario logueado
 */
$id_usuario = $_SESSION['id_usuario'];
$sql_usuario = "SELECT * FROM Usuarios WHERE id_usuario = ? LIMIT 1";
$stmt_usuario = $mysqli->prepare($sql_usuario);
$stmt_usuario->bind_param('i', $id_usuario);
$stmt_usuario->execute();
$result_usuario = $stmt_usuario->get_result();
$usuario = $result_usuario->fetch_assoc();

if (!$usuario) {
    session_destroy();
    header('Location: login.php');
    exit;
}

/**
 * Obtener formas de pago disponibles
 */
$sql_pagos = "SELECT * FROM Forma_Pagos ORDER BY id_forma_pago";
$result_pagos = $mysqli->query($sql_pagos);
$formas_pago = [];
if ($result_pagos) {
    while ($row = $result_pagos->fetch_assoc()) {
        $formas_pago[] = $row;
    }
}

/**
 * Calcular resumen del pedido
 */
$productos_carrito = array();
$total_carrito = 0;
$total_items = 0;

foreach ($_SESSION['carrito'] as $clave => $item) {
    // Consultar datos del producto y verificar stock
    $sql = "
        SELECT 
            p.id_producto,
            p.nombre_producto,
            p.precio_actual,
            fp.foto_prod_miniatura,
            sv.id_variante,
            sv.stock,
            sv.talle,
            sv.color
        FROM Productos p
        LEFT JOIN Fotos_Producto fp ON p.id_producto = fp.id_producto
        LEFT JOIN Stock_Variantes sv ON p.id_producto = sv.id_producto 
            AND sv.talle = ? 
            AND sv.color = ?
        WHERE p.id_producto = ?
        LIMIT 1
    ";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ssi', $item['talla'], $item['color'], $item['id_producto']);
    $stmt->execute();
    $result = $stmt->get_result();
    $producto = $result->fetch_assoc();
    
    if ($producto) {
        $subtotal = $producto['precio_actual'] * $item['cantidad'];
        $total_carrito += $subtotal;
        $total_items += $item['cantidad'];
        
        $productos_carrito[] = array(
            'clave' => $clave,
            'id_producto' => $producto['id_producto'],
            'id_variante' => $producto['id_variante'],
            'nombre_producto' => $producto['nombre_producto'],
            'precio_actual' => $producto['precio_actual'],
            'foto_prod_miniatura' => $producto['foto_prod_miniatura'],
            'talla' => $item['talla'],
            'color' => $item['color'],
            'cantidad' => $item['cantidad'],
            'stock_disponible' => $producto['stock'],
            'subtotal' => $subtotal
        );
    }
}

// Verificar stock disponible
$error_stock = false;
$mensaje_stock = array();

foreach ($productos_carrito as $producto) {
    if ($producto['stock_disponible'] < $producto['cantidad']) {
        $error_stock = true;
        $mensaje_stock[] = "Stock insuficiente para {$producto['nombre_producto']} (Talla: {$producto['talla']}, Color: {$producto['color']}). Disponible: {$producto['stock_disponible']}";
    }
}

?>


<?php include 'includes/header.php'; ?>

<!-- Contenido del checkout -->
<div style="display:none">
        <nav class="navbar">
            <div class="container-fluid">
                <a class="navbar-brand" href="index.php">SEDA Y LINO</a>
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
                            <a class="nav-link" href="perfil.php" title="Mi Perfil">
                                <img src="iconos/avatar-usuario.png" alt="icono de avatar de usuario">
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-5">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item"><a href="carrito.php">Carrito</a></li>
                <li class="breadcrumb-item active" aria-current="page">Checkout</li>
            </ol>
        </nav>

        <h1 class="mb-4">
            <i class="fas fa-credit-card me-2"></i>
            Finalizar Compra
        </h1>

        <?php if ($error_stock): ?>
        <!-- Alerta de error de stock -->
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Stock Insuficiente</h5>
            <hr>
            <ul class="mb-0">
                <?php foreach ($mensaje_stock as $msg): ?>
                    <li><?php echo htmlspecialchars($msg); ?></li>
                <?php endforeach; ?>
            </ul>
            <hr>
            <p class="mb-0">
                <a href="carrito.php" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-arrow-left me-2"></i>Volver al Carrito
                </a>
            </p>
        </div>
        <?php else: ?>
        
        <!-- Formulario de Checkout -->
        <form method="POST" action="procesar-pedido.php" id="formCheckout">
            <div class="row">
                <!-- Columna izquierda: Datos de envío y pago -->
                <div class="col-lg-8">
                    
                    <!-- Datos de Envío -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-shipping-fast me-2"></i>
                                Datos de Envío
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nombre" class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" 
                                           value="<?php echo htmlspecialchars($usuario['nombre']); ?>" 
                                           required readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="apellido" class="form-label">Apellido *</label>
                                    <input type="text" class="form-control" id="apellido" name="apellido" 
                                           value="<?php echo htmlspecialchars($usuario['apellido']); ?>" 
                                           required readonly>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($usuario['email']); ?>" 
                                           required readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="telefono" class="form-label">Teléfono *</label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono" 
                                           value="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="direccion" class="form-label">Dirección *</label>
                                <input type="text" class="form-control" id="direccion" name="direccion" 
                                       value="<?php echo htmlspecialchars($usuario['direccion'] ?? ''); ?>" 
                                       placeholder="Calle, número, piso, depto."
                                       required>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="localidad" class="form-label">Localidad *</label>
                                    <input type="text" class="form-control" id="localidad" name="localidad" 
                                           value="<?php echo htmlspecialchars($usuario['localidad'] ?? ''); ?>" 
                                           required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="provincia" class="form-label">Provincia *</label>
                                    <input type="text" class="form-control" id="provincia" name="provincia" 
                                           value="<?php echo htmlspecialchars($usuario['provincia'] ?? ''); ?>" 
                                           required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="codigo_postal" class="form-label">Código Postal *</label>
                                    <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" 
                                           value="<?php echo htmlspecialchars($usuario['codigo_postal'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>

                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>Los datos de nombre, apellido y email no pueden modificarse. Si necesitas cambiarlos, actualízalos en tu <a href="perfil.php">perfil</a>.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Método de Pago -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-credit-card me-2"></i>
                                Método de Pago
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($formas_pago)): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    No hay formas de pago disponibles. Contacta al administrador.
                                </div>
                            <?php else: ?>
                                <?php foreach ($formas_pago as $forma): ?>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="id_forma_pago" 
                                           id="pago_<?php echo $forma['id_forma_pago']; ?>" 
                                           value="<?php echo $forma['id_forma_pago']; ?>"
                                           <?php echo ($forma['id_forma_pago'] == 1) ? 'checked' : ''; ?>
                                           required>
                                    <label class="form-check-label" for="pago_<?php echo $forma['id_forma_pago']; ?>">
                                        <strong><?php echo htmlspecialchars($forma['nombre']); ?></strong>
                                        <?php if ($forma['descripcion']): ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($forma['descripcion']); ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- Columna derecha: Resumen del pedido -->
                <div class="col-lg-4">
                    <div class="card shadow-sm sticky-card">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-receipt me-2"></i>
                                Resumen del Pedido
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Lista de productos -->
                            <h6 class="mb-3">Productos (<?php echo count($productos_carrito); ?>)</h6>
                            <div class="mb-3 carrito-scroll">
                                <?php foreach ($productos_carrito as $producto): ?>
                                <div class="d-flex mb-3 pb-3 border-bottom">
                                    <img src="<?php echo htmlspecialchars($producto['foto_prod_miniatura'] ?: 'imagenes/imagen.png'); ?>" 
                                         class="rounded me-2 producto-imagen-carrito"
                                         alt="<?php echo htmlspecialchars($producto['nombre_producto']); ?>">
                                    <div class="flex-grow-1">
                                        <small class="d-block fw-bold">
                                            <?php echo htmlspecialchars($producto['nombre_producto']); ?>
                                        </small>
                                        <small class="text-muted d-block">
                                            Talla: <?php echo htmlspecialchars($producto['talla']); ?> | 
                                            Color: <?php echo htmlspecialchars($producto['color']); ?>
                                        </small>
                                        <small class="text-muted d-block">
                                            Cantidad: <?php echo $producto['cantidad']; ?> x $<?php echo number_format($producto['precio_actual'], 2); ?>
                                        </small>
                                        <small class="text-primary fw-bold">
                                            $<?php echo number_format($producto['subtotal'], 2); ?>
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Totales -->
                            <hr>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal (<?php echo $total_items; ?> items):</span>
                                <strong>$<?php echo number_format($total_carrito, 2); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Envío:</span>
                                <span class="text-success fw-bold">GRATIS</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <h5 class="mb-0">Total:</h5>
                                <h5 class="text-primary mb-0">$<?php echo number_format($total_carrito, 2); ?></h5>
                            </div>

                            <!-- Botón de confirmar -->
                            <button type="submit" class="btn btn-success w-100 mb-2" <?php echo empty($formas_pago) ? 'disabled' : ''; ?>>
                                <i class="fas fa-check-circle me-2"></i>
                                Confirmar Pedido
                            </button>
                            
                            <a href="carrito.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-arrow-left me-2"></i>
                                Volver al Carrito
                            </a>
                        </div>
                    </div>

                    <!-- Información de seguridad -->
                    <div class="card shadow-sm mt-3">
                        <div class="card-body">
                            <h6 class="mb-3">
                                <i class="fas fa-shield-alt text-success me-2"></i>
                                Compra Segura
                            </h6>
                            <ul class="list-unstyled small mb-0">
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Datos protegidos
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Envío gratis
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    30 días para devoluciones
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </form>

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


<?php include 'includes/footer.php'; render_footer(); ?>

