<?php
/**
 * ========================================================================
 * CHECKOUT - Tienda Seda y Lino
 * ========================================================================
 * Página final antes de procesar el pedido
 * - Verifica que el usuario esté logueado
 * - Muestra resumen del pedido (productos, cantidades, precios)
 * - Permite confirmar/editar datos de envío
 * - Selección de método de pago
 * - Validación de datos antes de procesar
 * 
 * Funciones principales:
 * - Validar que el carrito tenga productos
 * - Cargar datos del usuario para envío
 * - Obtener métodos de pago disponibles
 * - Formulario para enviar a procesar-pedido.php
 * 
 * Variables principales:
 * - $id_usuario: Usuario en sesión
 * - $usuario: Datos del usuario de la BD
 * - $carrito: Productos en el carrito
 * - $formas_pago: Métodos de pago disponibles
 * 
 * Tablas utilizadas: Usuarios, Formas_Pago, Productos
 * ========================================================================
 */

session_start();

// ========================================================================
// MODO DEBUG - Desactivado en producción
// ========================================================================
// Los errores se registran en el log del servidor, no se muestran al usuario
// Para activar debug en desarrollo, descomentar las siguientes líneas:
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';

// Configurar título de la página
$titulo_pagina = 'Checkout';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['id_usuario'])) {
    $_SESSION['mensaje_error'] = "Debes iniciar sesión para continuar con la compra";
    header('Location: login.php');
    exit;
}

/**
 * Cargar funciones comunes del carrito
 */
require_once __DIR__ . '/includes/carrito_functions.php';

/**
 * Limpiar mensajes de error obsoletos relacionados con login
 * Si el usuario ya está logueado, cualquier mensaje de error sobre login
 * es obsoleto y no debe mostrarse
 */
limpiarMensajesErrorObsoletos('Debes iniciar sesión');

// Verificar que el carrito tenga productos reales (no solo _meta)
if (!tieneProductosReales($_SESSION['carrito'] ?? [])) {
    $_SESSION['mensaje_error'] = "Tu carrito está vacío";
    header('Location: carrito.php');
    exit;
}

/**
 * Obtener datos del usuario logueado (solo usuarios activos)
 */
require_once __DIR__ . '/includes/queries/usuario_queries.php';

$id_usuario = $_SESSION['id_usuario'];
$usuario = obtenerUsuarioPorId($mysqli, $id_usuario);

if (!$usuario) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Incluir queries de formas de pago y productos
require_once __DIR__ . '/includes/queries/forma_pago_queries.php';
require_once __DIR__ . '/includes/queries/producto_queries.php';

/**
 * Obtener formas de pago disponibles
 */
$formas_pago = obtenerFormasPago($mysqli);
if (!is_array($formas_pago)) {
    $formas_pago = [];
    error_log("Error al obtener formas de pago en checkout.php");
}

/**
 * Cargar funciones de perfil para parsear dirección
 */
require_once __DIR__ . '/includes/perfil_functions.php';

/**
 * Cargar funciones de envío
 */
require_once __DIR__ . '/includes/envio_functions.php';

/**
 * Parsear dirección del usuario en componentes (calle, número, piso) usando función centralizada
 */
$direccion_completa = $usuario['direccion'] ?? '';
$direccion_parseada = parsearDireccion($direccion_completa);

/**
 * Calcular resumen del pedido
 * Procesa todos los productos del carrito, valida stock y elimina productos inválidos
 * 
 * NOTA SOBRE VALIDACIÓN DE STOCK:
 * Esta validación es PRELIMINAR y se usa solo para mostrar información al usuario.
 * La validación DEFINITIVA con FOR UPDATE se realiza en procesar-pedido.php para:
 * - Prevenir race conditions (múltiples usuarios comprando simultáneamente)
 * - Garantizar atomicidad en la transacción
 * - Bloquear filas de stock durante la validación
 * 
 * Esta validación en checkout.php es más rápida y permite mostrar al usuario
 * qué productos están disponibles antes de confirmar el pedido.
 */
$productos_carrito = array();
$total_carrito = 0;
$total_items = 0;
$productos_a_eliminar = array();

// Procesar cada producto del carrito usando función helper
foreach ($_SESSION['carrito'] as $clave => $item) {
    // Saltar metadatos del carrito
    if ($clave === '_meta') {
        continue;
    }
    
    // Procesar item usando función helper (modo preliminar)
    $resultado = procesarItemCarrito($mysqli, $item, $clave, 'preliminar');
    
    // Si hay error, agregar a lista de eliminación
    if ($resultado === false) {
        $productos_a_eliminar[] = $clave;
        continue;
    }
    
    // Producto válido: agregar al resumen
    $productos_carrito[] = [
        'clave' => $resultado['clave'],
        'id_producto' => $resultado['id_producto'],
        'id_variante' => $resultado['id_variante'],
        'nombre_producto' => $resultado['nombre_producto'],
        'precio_actual' => $resultado['precio_actual'],
        'talla' => $item['talla'],
        'color' => $item['color'],
        'cantidad' => $resultado['cantidad'],
        'stock_disponible' => $resultado['stock_disponible'],
        'subtotal' => $resultado['subtotal']
    ];
    $total_carrito += $resultado['subtotal'];
    $total_items += $resultado['cantidad'];
}

// Eliminar productos inválidos del carrito
if (!empty($productos_a_eliminar)) {
    foreach ($productos_a_eliminar as $clave_producto) {
        if (isset($_SESSION['carrito'][$clave_producto])) {
            unset($_SESSION['carrito'][$clave_producto]);
        }
    }
    
    // Actualizar timestamp del carrito
    if (isset($_SESSION['carrito']['_meta'])) {
        $_SESSION['carrito']['_meta']['ultima_actualizacion'] = time();
    }
    
    // Si no quedan productos válidos después de eliminar, redirigir
    if (empty($productos_carrito)) {
        $cantidad_eliminados = count($productos_a_eliminar);
        if ($cantidad_eliminados == 1) {
            $_SESSION['mensaje_error'] = "El producto seleccionado ya no está disponible y fue removido del carrito.";
        } else {
            $_SESSION['mensaje_error'] = "Algunos productos ya no están disponibles y fueron removidos del carrito.";
        }
        header('Location: carrito.php');
        exit;
    }
    
    // Si quedan productos válidos, mostrar mensaje informativo
    $cantidad_eliminados = count($productos_a_eliminar);
    if ($cantidad_eliminados == 1) {
        $_SESSION['mensaje_error'] = "Un producto ya no está disponible y fue removido automáticamente de tu carrito.";
    } else {
        $_SESSION['mensaje_error'] = "{$cantidad_eliminados} productos ya no están disponibles y fueron removidos automáticamente de tu carrito.";
    }
}

// Verificar que hay productos válidos después del procesamiento
if (empty($productos_carrito)) {
    $_SESSION['mensaje_error'] = "No hay productos disponibles en tu carrito. Por favor, agrega productos antes de continuar.";
    header('Location: carrito.php');
    exit;
}

/**
 * Leer errores de checkout desde sesión y actualizar datos de productos con problemas
 */
$checkout_errores = isset($_SESSION['checkout_errores']) ? $_SESSION['checkout_errores'] : [];
$checkout_productos_problema = isset($_SESSION['checkout_productos_problema']) ? $_SESSION['checkout_productos_problema'] : [];
$productos_con_error = [];

// Actualizar datos de productos con problemas usando información de sesión
if (!empty($checkout_productos_problema)) {
    foreach ($productos_carrito as $index => $producto) {
        $clave = $producto['clave'];
        if (isset($checkout_productos_problema[$clave])) {
            $datos_problema = $checkout_productos_problema[$clave];
            
            // Actualizar datos del producto con información actualizada
            $productos_carrito[$index]['stock_disponible'] = $datos_problema['stock_disponible'] ?? $producto['stock_disponible'];
            $productos_carrito[$index]['precio_actual'] = $datos_problema['precio_actual'] ?? $producto['precio_actual'];
            $productos_carrito[$index]['tiene_error'] = true;
            $productos_carrito[$index]['error_mensaje'] = '';
            
            // Buscar mensaje de error y tipo correspondiente
            foreach ($checkout_errores as $error) {
                if ($error['clave'] === $clave) {
                    $productos_carrito[$index]['error_mensaje'] = $error['mensaje'];
                    $productos_carrito[$index]['error_tipo'] = $error['tipo'] ?? 'desconocido';
                    break;
                }
            }
            
            // Recalcular subtotal si el precio cambió
            if (isset($datos_problema['precio_actual']) && $datos_problema['precio_actual'] > 0) {
                $productos_carrito[$index]['subtotal'] = $datos_problema['precio_actual'] * $producto['cantidad'];
            }
            
            $productos_con_error[$clave] = $productos_carrito[$index];
        }
    }
    
    // Recalcular totales después de actualizar precios
    $total_carrito = 0;
    $total_items = 0;
    foreach ($productos_carrito as $producto) {
        $total_carrito += $producto['subtotal'];
        $total_items += $producto['cantidad'];
    }
}

/**
 * Verificar stock disponible
 * Nota: Los productos en $productos_carrito ya fueron validados con stock suficiente.
 * Esta validación solo aplica para productos con errores de checkout (vienen de procesar-pedido.php)
 */
$error_stock = false;
$mensaje_stock = array();

// Si hay errores de checkout desde procesar-pedido.php, marcar como error de stock
// Los productos con errores ya están marcados con 'tiene_error' en la sección anterior
if (!empty($checkout_errores)) {
    $error_stock = true;
    // Los mensajes de error ya están en checkout_errores y se muestran en el template
}

/**
 * Calcular costo de envío
 * NOTA: Este cálculo se repite en procesar-pedido.php por diseño:
 * - Aquí (checkout.php): Se calcula para MOSTRAR al usuario el costo estimado antes de confirmar
 * - En procesar-pedido.php: Se calcula para GUARDAR el costo real en el pedido
 * Ambos cálculos son necesarios porque el usuario puede cambiar la dirección en checkout,
 * y el cálculo final debe reflejar la dirección seleccionada al procesar el pedido.
 */
$provincia_usuario = $usuario['provincia'] ?? '';
$localidad_usuario = $usuario['localidad'] ?? '';
$info_envio = calcularCostoEnvio($total_carrito, $provincia_usuario, $localidad_usuario);
$costo_envio = $info_envio['costo'];
$total_con_envio = calcularTotalConEnvio($total_carrito, $costo_envio);
$monto_faltante = obtenerMontoFaltanteEnvioGratis($total_carrito);

?>


<?php include 'includes/header.php'; ?>

<!-- Contenido del checkout -->
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

        <?php if (isset($_SESSION['mensaje_error'])): ?>
        <!-- Alerta de error general -->
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Error</h5>
            <p class="mb-0"><?php echo $_SESSION['mensaje_error']; ?></p>
            <?php if (!empty($checkout_errores)): ?>
            <hr>
            <p class="mb-1"><strong>Detalles de los errores:</strong></p>
            <ul class="mb-0">
                <?php foreach ($checkout_errores as $error): ?>
                <li><?php echo htmlspecialchars($error['mensaje']); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php 
        unset($_SESSION['mensaje_error']);
        // Limpiar errores de checkout después de mostrarlos (se mantendrán hasta que se corrija el problema)
        // Los errores se limpiarán cuando el usuario vuelva a intentar procesar el pedido
        ?>
        <?php endif; ?>


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
                                           required minlength="2">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="apellido" class="form-label">Apellido *</label>
                                    <input type="text" class="form-control" id="apellido" name="apellido" 
                                           value="<?php echo htmlspecialchars($usuario['apellido']); ?>" 
                                           required minlength="2">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="telefono" class="form-label">Teléfono *</label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono" 
                                           value="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>" 
                                           pattern="[0-9+\-() ]+" 
                                           title="Solo se permiten números y símbolos (+, -, paréntesis, espacios)"
                                           required>
                                    <small class="form-text text-muted">Solo números y símbolos (+, -, paréntesis)</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email_contacto" class="form-label">Mail de Contacto *</label>
                                    <input type="email" class="form-control" id="email_contacto" name="email_contacto" 
                                           value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>

                            <!-- Dirección separada en campos -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="direccion_calle" class="form-label">Dirección *</label>
                                    <input type="text" class="form-control" id="direccion_calle" name="direccion_calle" 
                                           value="<?php echo htmlspecialchars($direccion_parseada['calle']); ?>" 
                                           placeholder="Nombre de la calle"
                                           pattern="[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]+"
                                           title="Solo se permiten letras (incluyendo acentos), números, espacios, guiones, apóstrofes y acentos graves"
                                           required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="direccion_numero" class="form-label">Número *</label>
                                    <input type="text" class="form-control" id="direccion_numero" name="direccion_numero" 
                                           value="<?php echo htmlspecialchars($direccion_parseada['numero']); ?>" 
                                           placeholder="Número"
                                           pattern="[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]+"
                                           title="Solo se permiten letras (incluyendo acentos), números, espacios, guiones, apóstrofes y acentos graves"
                                           required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="direccion_piso" class="form-label">Piso / Depto.</label>
                                    <input type="text" class="form-control" id="direccion_piso" name="direccion_piso" 
                                           value="<?php echo htmlspecialchars($direccion_parseada['piso']); ?>" 
                                           placeholder="Opcional"
                                           pattern="[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]+"
                                           title="Solo se permiten letras (incluyendo acentos), números, espacios, guiones, apóstrofes y acentos graves">
                                </div>
                            </div>

                            <!-- Orden: Provincia - Localidad - Código Postal -->
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="provincia" class="form-label">Provincia *</label>
                                    <select class="form-select" id="provincia" name="provincia" required>
                                        <option value="">Seleccionar provincia</option>
                                        <option value="CABA" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'CABA') ? 'selected' : ''; ?>>CABA</option>
                                        <option value="Buenos Aires" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Buenos Aires') ? 'selected' : ''; ?>>Buenos Aires</option>
                                        <option value="Catamarca" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Catamarca') ? 'selected' : ''; ?>>Catamarca</option>
                                        <option value="Chaco" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Chaco') ? 'selected' : ''; ?>>Chaco</option>
                                        <option value="Chubut" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Chubut') ? 'selected' : ''; ?>>Chubut</option>
                                        <option value="Córdoba" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Córdoba') ? 'selected' : ''; ?>>Córdoba</option>
                                        <option value="Corrientes" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Corrientes') ? 'selected' : ''; ?>>Corrientes</option>
                                        <option value="Entre Ríos" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Entre Ríos') ? 'selected' : ''; ?>>Entre Ríos</option>
                                        <option value="Formosa" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Formosa') ? 'selected' : ''; ?>>Formosa</option>
                                        <option value="Jujuy" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Jujuy') ? 'selected' : ''; ?>>Jujuy</option>
                                        <option value="La Pampa" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'La Pampa') ? 'selected' : ''; ?>>La Pampa</option>
                                        <option value="La Rioja" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'La Rioja') ? 'selected' : ''; ?>>La Rioja</option>
                                        <option value="Mendoza" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Mendoza') ? 'selected' : ''; ?>>Mendoza</option>
                                        <option value="Misiones" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Misiones') ? 'selected' : ''; ?>>Misiones</option>
                                        <option value="Neuquén" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Neuquén') ? 'selected' : ''; ?>>Neuquén</option>
                                        <option value="Río Negro" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Río Negro') ? 'selected' : ''; ?>>Río Negro</option>
                                        <option value="Salta" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Salta') ? 'selected' : ''; ?>>Salta</option>
                                        <option value="San Juan" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'San Juan') ? 'selected' : ''; ?>>San Juan</option>
                                        <option value="San Luis" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'San Luis') ? 'selected' : ''; ?>>San Luis</option>
                                        <option value="Santa Cruz" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Santa Cruz') ? 'selected' : ''; ?>>Santa Cruz</option>
                                        <option value="Santa Fe" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Santa Fe') ? 'selected' : ''; ?>>Santa Fe</option>
                                        <option value="Santiago del Estero" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Santiago del Estero') ? 'selected' : ''; ?>>Santiago del Estero</option>
                                        <option value="Tierra del Fuego" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Tierra del Fuego') ? 'selected' : ''; ?>>Tierra del Fuego</option>
                                        <option value="Tucumán" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Tucumán') ? 'selected' : ''; ?>>Tucumán</option>
                                        <?php if (!empty($usuario['provincia'])): 
                                            $provincias_lista = ['CABA', 'Buenos Aires', 'Catamarca', 'Chaco', 'Chubut', 'Córdoba', 'Corrientes', 'Entre Ríos', 'Formosa', 'Jujuy', 'La Pampa', 'La Rioja', 'Mendoza', 'Misiones', 'Neuquén', 'Río Negro', 'Salta', 'San Juan', 'San Luis', 'Santa Cruz', 'Santa Fe', 'Santiago del Estero', 'Tierra del Fuego', 'Tucumán'];
                                            if (!in_array($usuario['provincia'], $provincias_lista)): ?>
                                                <option value="<?php echo htmlspecialchars($usuario['provincia']); ?>" selected><?php echo htmlspecialchars($usuario['provincia']); ?></option>
                                            <?php endif; 
                                        endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="localidad" class="form-label">Localidad *</label>
                                    <input type="text" class="form-control" id="localidad" name="localidad" 
                                           value="<?php echo htmlspecialchars($usuario['localidad'] ?? ''); ?>" 
                                           required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="codigo_postal" class="form-label">Código Postal *</label>
                                    <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" 
                                           value="<?php echo htmlspecialchars($usuario['codigo_postal'] ?? ''); ?>" 
                                           pattern="[A-Za-z0-9 ]+" 
                                           title="Solo se permiten letras, números y espacios"
                                           required>
                                    <small class="form-text text-muted">Permite números y letras</small>
                                </div>
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
                                           required
                                           data-forma-pago-id="<?php echo $forma['id_forma_pago']; ?>"
                                           data-forma-pago-nombre="<?php echo htmlspecialchars($forma['nombre']); ?>">
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
                        <div class="card-body" data-subtotal="<?php echo $total_carrito; ?>" data-monto-minimo-gratis="80000" data-costo-caba-gba="10000" data-costo-argentina="15000">
                            <!-- Lista de productos -->
                            <h6 class="mb-3">Productos (<?php echo count($productos_carrito); ?>)</h6>
                            <div class="mb-3 carrito-scroll">
                                <?php foreach ($productos_carrito as $producto): ?>
                                <?php 
                                $tiene_error = isset($producto['tiene_error']) && $producto['tiene_error'];
                                $clase_error = $tiene_error ? 'producto-error border-danger border-2' : '';
                                ?>
                                <div class="d-flex mb-3 pb-3 border-bottom <?php echo $clase_error; ?>" 
                                     style="<?php echo $tiene_error ? 'background-color: #fff5f5; border-radius: 8px; padding: 0.75rem;' : ''; ?>">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-start justify-content-between">
                                            <small class="d-block fw-bold">
                                                <?php echo htmlspecialchars($producto['nombre_producto']); ?>
                                            </small>
                                            <?php if ($tiene_error): ?>
                                            <i class="fas fa-exclamation-triangle text-danger ms-2" title="Este producto tiene un problema"></i>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted d-block">
                                            Talla: <?php echo htmlspecialchars($producto['talla']); ?> | 
                                            Color: <?php echo htmlspecialchars($producto['color']); ?>
                                        </small>
                                        <small class="text-muted d-block">
                                            Cantidad: <?php echo $producto['cantidad']; ?> x $<?php echo number_format($producto['precio_actual'], 2); ?>
                                        </small>
                                        <?php 
                                        // Warning de stock bajo (menos de 5 unidades disponibles)
                                        $stock_disponible = $producto['stock_disponible'] ?? 0;
                                        $mostrar_warning_stock_bajo = !$tiene_error && $stock_disponible > 0 && $stock_disponible < 5;
                                        ?>
                                        <?php if ($mostrar_warning_stock_bajo): ?>
                                        <div class="alert alert-warning alert-sm py-1 px-2 mt-2 mb-1" style="font-size: 0.75rem; line-height: 1.3;">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            <strong>Stock limitado:</strong> Quedan <?php echo $stock_disponible; ?> unidades disponibles
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($tiene_error && isset($producto['error_mensaje'])): ?>
                                        <div class="alert alert-danger alert-sm py-1 px-2 mt-2 mb-1" style="font-size: 0.75rem; line-height: 1.3;">
                                            <i class="fas fa-exclamation-circle me-1"></i>
                                            <strong>Error:</strong> <?php echo htmlspecialchars($producto['error_mensaje']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <small class="text-primary fw-bold">
                                            $<?php echo number_format($producto['subtotal'], 2); ?>
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Aviso de envío gratis -->
                            <div id="envio-alert">
                            <?php if (!$info_envio['es_gratis'] && $monto_faltante > 0): ?>
                            <div class="alert alert-info mb-3 alert-compact" style="color: #000;">
                                <i class="fas fa-truck me-2"></i>
                                <strong>¡Agrega $<?php echo number_format($monto_faltante, 2); ?> más y obtén envío gratis!</strong>
                                <br>
                                <small>En compras superiores a $80,000 en CABA y GBA</small>
                            </div>
                            <?php elseif ($info_envio['es_gratis']): ?>
                            <div class="alert alert-success mb-3 alert-compact">
                                <i class="fas fa-truck me-2"></i>
                                <strong>¡Envío gratis!</strong>
                                <br>
                                <small>Tu compra supera los $80,000 en CABA y GBA</small>
                            </div>
                            <?php endif; ?>
                            </div>

                            <!-- Totales -->
                            <hr>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal (<?php echo $total_items; ?> items):</span>
                                <strong>$<?php echo number_format($total_carrito, 2); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Envío:</span>
                                <span id="envio-costo">
                                <?php if ($info_envio['es_gratis']): ?>
                                <span class="text-success fw-bold">GRATIS</span>
                                <?php else: ?>
                                <strong>$<?php echo number_format($costo_envio, 2); ?></strong>
                                <?php endif; ?>
                                </span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <h5 class="mb-0">Total:</h5>
                                <h5 class="text-primary mb-0" id="total-pedido">$<?php echo number_format($total_con_envio, 2); ?></h5>
                            </div>

                            <!-- Botón de confirmar -->
                            <button type="submit" class="btn btn-success w-100 mb-2" data-auto-lock="true" data-lock-time="3000" data-lock-text="Procesando pedido..." <?php echo empty($formas_pago) ? 'disabled' : ''; ?>>
                                <i class="fas fa-check-circle me-2"></i>
                                Confirmar Pedido
                            </button>
                            
                            <a href="carrito.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-arrow-left me-2"></i>
                                Volver al Carrito
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <?php endif; ?>
    </main>

<style>
    /* Estilos para productos con errores */
    .producto-error {
        border-left: 4px solid #dc3545 !important;
        animation: shake 0.5s ease-in-out;
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
    
    .producto-error .alert-sm {
        margin-top: 0.5rem;
        margin-bottom: 0.5rem;
    }
    
    .producto-error img {
        filter: grayscale(20%);
    }
    
    /* Estilos para alertas pequeñas */
    .alert-sm {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        margin-bottom: 0.25rem;
    }
</style>

<script src="js/checkout.js"></script>

<?php include 'includes/footer.php'; render_footer(); ?>

