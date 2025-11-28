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

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/envio_functions.php';
require_once __DIR__ . '/includes/queries/producto_queries.php';
require_once __DIR__ . '/includes/queries/stock_queries.php';

// Configurar título de la página
$titulo_pagina = 'Carrito de Compras';

// Inicializar carrito en sesión si no existe
if (!isset($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
    $_SESSION['carrito'] = array();
}

/**
 * Calcula la cantidad total de items en el carrito
 * Suma todas las cantidades de cada variante
 * 
 * @param array $carrito Array del carrito
 * @return int Cantidad total de unidades en el carrito
 */
function calcularCantidadTotalCarrito($carrito) {
    if (!is_array($carrito) || empty($carrito)) {
        return 0;
    }
    
    $cantidad_total = 0;
    foreach ($carrito as $clave => $item) {
        // Saltar metadatos del carrito (_meta no es un producto)
        if ($clave === '_meta') {
            continue;
        }
        
        // Validar que el item tenga campo cantidad
        if (isset($item['cantidad']) && is_numeric($item['cantidad'])) {
            $cantidad_total += (int)$item['cantidad'];
        }
    }
    
    return $cantidad_total;
}

/**
 * Procesar acciones del carrito (agregar, eliminar, actualizar)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // AGREGAR producto al carrito
    if (isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
        $id_producto = isset($_POST['id_producto']) ? (int)$_POST['id_producto'] : 0;
        $talla = isset($_POST['talla']) ? trim($_POST['talla']) : '';
        $color = isset($_POST['color']) ? trim($_POST['color']) : '';
        $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 0;
        
        // Verificar si es una petición AJAX
        $es_ajax = isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        
        // Validación más estricta
        if ($id_producto > 0 && !empty($talla) && !empty($color) && $cantidad > 0) {
            // Clave única para cada variante en el carrito
            $clave_carrito = $id_producto . '-' . $talla . '-' . $color;
            
            // Calcular cantidad actual en el carrito
            $cantidad_actual_carrito = isset($_SESSION['carrito'][$clave_carrito]) ? 
                intval($_SESSION['carrito'][$clave_carrito]['cantidad']) : 0;
            
            // Validar stock disponible con FOR UPDATE para prevenir race conditions
            try {
                $datos_stock = validarStockDisponibleCarrito($mysqli, $id_producto, $talla, $color, $cantidad, $cantidad_actual_carrito);
                
                // Si llegamos aquí, el stock es suficiente
                $stock_disponible = $datos_stock['stock_disponible'];
                $cantidad_total_solicitada = $cantidad_actual_carrito + $cantidad;
                
                // Variable para detectar si se agregó el stock máximo disponible
                $es_stock_maximo = false;
                $cantidad_a_agregar = $cantidad;
                
                // Verificar si es stock máximo
                if ($stock_disponible == $cantidad_total_solicitada) {
                    $es_stock_maximo = true;
                }
                
            } catch (Exception $e) {
                // Manejar error de stock insuficiente
                $mensaje_error = $e->getMessage();
                
                // Intentar extraer cantidad disponible del mensaje si es posible
                $cantidad_disponible_para_agregar = 0;
                if (preg_match('/Puedes agregar hasta (\d+) unidades más/', $mensaje_error, $matches)) {
                    $cantidad_disponible_para_agregar = intval($matches[1]);
                }
                
                if ($es_ajax) {
                    header('Content-Type: application/json; charset=utf-8');
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'mensaje' => $mensaje_error,
                        'stock_disponible' => isset($stock_disponible) ? $stock_disponible : 0,
                        'cantidad_actual' => $cantidad_actual_carrito,
                        'cantidad_disponible_para_agregar' => $cantidad_disponible_para_agregar
                    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                    exit;
                } else {
                    $_SESSION['mensaje_carrito'] = $mensaje_error;
                    header('Location: carrito.php');
                    exit;
                }
            }
            
            // Si ya existe, sumar cantidad; si no, crear nueva entrada
            if (isset($_SESSION['carrito'][$clave_carrito])) {
                $_SESSION['carrito'][$clave_carrito]['cantidad'] += $cantidad_a_agregar;
            } else {
                $_SESSION['carrito'][$clave_carrito] = array(
                    'id_producto' => $id_producto,
                    'talla' => $talla,
                    'color' => $color,
                    'cantidad' => $cantidad_a_agregar
                );
            }
            
            // Determinar mensaje según si se agregó el stock máximo
            if ($es_stock_maximo) {
                $mensaje_exito = "Agregaste la cantidad máxima disponible";
            } else {
                $mensaje_exito = "Producto agregado al carrito";
            }
            
            if ($es_ajax) {
                // Responder con JSON para AJAX
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(200);
                
                // Preparar respuesta base
                $respuesta = [
                    'success' => true,
                    'mensaje' => $mensaje_exito,
                    'cantidad_carrito' => calcularCantidadTotalCarrito($_SESSION['carrito'])
                ];
                
                // Agregar tipo de mensaje si es stock máximo (warning)
                if ($es_stock_maximo) {
                    $respuesta['tipo_mensaje'] = 'warning';
                }
                
                echo json_encode($respuesta, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                $_SESSION['mensaje_carrito'] = $mensaje_exito;
                header('Location: carrito.php');
                exit;
            }
        } else {
            if ($es_ajax) {
                // Responder con JSON para AJAX con código de error
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'Error: Datos inválidos para agregar al carrito. Verifica que hayas seleccionado talle y color.'
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                $_SESSION['mensaje_carrito'] = "Error: Datos inválidos para agregar al carrito. Verifica que hayas seleccionado talle y color.";
                header('Location: carrito.php');
                exit;
            }
        }
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
        
        // Verificar si es una petición AJAX
        $es_ajax = isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        
        // Validar que el producto existe en el carrito
        if (!isset($_SESSION['carrito'][$clave])) {
            $mensaje_error = "El producto no se encuentra en el carrito";
            if ($es_ajax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'mensaje' => $mensaje_error
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                $_SESSION['mensaje_carrito'] = $mensaje_error;
                header('Location: carrito.php');
                exit;
            }
        }
        
        $item_carrito = $_SESSION['carrito'][$clave];
        
        // Validar cantidad: debe ser mayor a 0
        if ($nueva_cantidad > 0) {
            // Validar stock disponible con FOR UPDATE para prevenir race conditions
            try {
                $datos_stock = validarStockDisponibleCarrito($mysqli, $item_carrito['id_producto'], $item_carrito['talla'], $item_carrito['color'], $nueva_cantidad, 0);
                $stock_disponible = $datos_stock['stock_disponible'];
                
                // Validar que el stock disponible sea suficiente para la nueva cantidad
                if ($stock_disponible < $nueva_cantidad) {
                    // Limitar a stock disponible si es menor que lo solicitado
                    $nueva_cantidad = $stock_disponible;
                    if ($nueva_cantidad <= 0) {
                        // Si no hay stock, eliminar del carrito
                        unset($_SESSION['carrito'][$clave]);
                        $mensaje = "El producto ya no tiene stock disponible y fue removido del carrito.";
                        if ($es_ajax) {
                            // Recalcular carrito después de eliminar
                            $total_carrito_actualizado = 0;
                            foreach ($_SESSION['carrito'] as $item) {
                                if (isset($item['id_producto'])) {
                                    $prod = obtenerProductoConVariante($mysqli, $item['id_producto'], $item['talla'], $item['color']);
                                    if ($prod) {
                                        $total_carrito_actualizado += $prod['precio_actual'] * $item['cantidad'];
                                    }
                                }
                            }
                            $info_envio_actualizada = obtenerInfoEnvioCarrito($total_carrito_actualizado);
                            $monto_faltante_actualizado = obtenerMontoFaltanteEnvioGratis($total_carrito_actualizado);
                            
                            header('Content-Type: application/json; charset=utf-8');
                            http_response_code(200);
                            echo json_encode([
                                'success' => true,
                                'mensaje' => $mensaje,
                                'subtotal' => $total_carrito_actualizado,
                                'info_envio' => $info_envio_actualizada,
                                'monto_faltante' => $monto_faltante_actualizado,
                                'total_estimado' => $total_carrito_actualizado + $info_envio_actualizada['costo_caba_gba'],
                                'producto_eliminado' => true,
                                'clave' => $clave
                            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                            exit;
                        } else {
                            $_SESSION['mensaje_carrito'] = $mensaje;
                            header('Location: carrito.php');
                            exit;
                        }
                    } else {
                        $_SESSION['carrito'][$clave]['cantidad'] = $nueva_cantidad;
                        $mensaje = "Cantidad actualizada. Stock disponible: {$stock_disponible} unidades.";
                    }
                } else {
                    $_SESSION['carrito'][$clave]['cantidad'] = $nueva_cantidad;
                    $mensaje = "Cantidad actualizada";
                }
            } catch (Exception $e) {
                // Manejar error de stock insuficiente o producto inactivo
                $mensaje_error = $e->getMessage();
                
                // Si el producto está inactivo o no existe, eliminar del carrito
                if (strpos($mensaje_error, 'inactivo') !== false || strpos($mensaje_error, 'no está disponible') !== false) {
                    unset($_SESSION['carrito'][$clave]);
                    $mensaje = "El producto ya no está disponible y fue removido del carrito.";
                } else {
                    // Stock insuficiente - ajustar a máximo disponible
                    $stock_disponible = 0;
                    if (preg_match('/Disponible: (\d+)/', $mensaje_error, $matches)) {
                        $stock_disponible = intval($matches[1]);
                    }
                    
                    if ($stock_disponible > 0) {
                        $_SESSION['carrito'][$clave]['cantidad'] = $stock_disponible;
                        $mensaje = "Cantidad ajustada a stock disponible: {$stock_disponible} unidades.";
                    } else {
                        unset($_SESSION['carrito'][$clave]);
                        $mensaje = "El producto ya no tiene stock disponible y fue removido del carrito.";
                    }
                }
                
                if ($es_ajax) {
                    // Recalcular carrito después de cambios
                    $total_carrito_actualizado = 0;
                    foreach ($_SESSION['carrito'] as $item) {
                        if (isset($item['id_producto'])) {
                            $prod = obtenerProductoConVariante($mysqli, $item['id_producto'], $item['talla'], $item['color']);
                            if ($prod) {
                                $total_carrito_actualizado += $prod['precio_actual'] * $item['cantidad'];
                            }
                        }
                    }
                    $info_envio_actualizada = obtenerInfoEnvioCarrito($total_carrito_actualizado);
                    $monto_faltante_actualizado = obtenerMontoFaltanteEnvioGratis($total_carrito_actualizado);
                    
                    header('Content-Type: application/json; charset=utf-8');
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'mensaje' => $mensaje,
                        'subtotal' => $total_carrito_actualizado,
                        'info_envio' => $info_envio_actualizada,
                        'monto_faltante' => $monto_faltante_actualizado,
                        'total_estimado' => $total_carrito_actualizado + $info_envio_actualizada['costo_caba_gba'],
                        'producto_eliminado' => !isset($_SESSION['carrito'][$clave]),
                        'clave' => $clave
                    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                    exit;
                } else {
                    $_SESSION['mensaje_carrito'] = $mensaje;
                    header('Location: carrito.php');
                    exit;
                }
            }
            
            // Si es AJAX, calcular y devolver información actualizada
            if ($es_ajax) {
                // Recalcular subtotal del carrito
                $total_carrito_actualizado = 0;
                $subtotal_producto = 0;
                foreach ($_SESSION['carrito'] as $item_clave => $item) {
                    if (isset($item['id_producto'])) {
                        $prod = obtenerProductoConVariante($mysqli, $item['id_producto'], $item['talla'], $item['color']);
                        if ($prod) {
                            $subtotal_item = $prod['precio_actual'] * $item['cantidad'];
                            $total_carrito_actualizado += $subtotal_item;
                            if ($item_clave === $clave) {
                                $subtotal_producto = $subtotal_item;
                            }
                        }
                    }
                }
                
                // Calcular información de envío actualizada
                $info_envio_actualizada = obtenerInfoEnvioCarrito($total_carrito_actualizado);
                $monto_faltante_actualizado = obtenerMontoFaltanteEnvioGratis($total_carrito_actualizado);
                
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'mensaje' => $mensaje,
                    'subtotal' => $total_carrito_actualizado,
                    'subtotal_producto' => $subtotal_producto,
                    'info_envio' => $info_envio_actualizada,
                    'monto_faltante' => $monto_faltante_actualizado,
                    'total_estimado' => $total_carrito_actualizado + $info_envio_actualizada['costo_caba_gba']
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        
        if (!$es_ajax) {
            header('Location: carrito.php');
            exit;
        }
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
    // Extraer IDs únicos de productos del carrito
    $ids_productos = array_unique(array_map(function($item) {
        if (!is_array($item) || !isset($item['id_producto'])) {
            return 0;
        }
        return (int)$item['id_producto'];
    }, $_SESSION['carrito']));
    
    $ids_productos = array_filter($ids_productos, function($id) {
        return $id > 0;
    });
    
    $productos_db = obtenerProductosParaCarrito($mysqli, array_values($ids_productos));
    
    // Procesar cada item del carrito
    foreach ($_SESSION['carrito'] as $clave => $item) {
        // Saltar metadatos del carrito (_meta no es un producto)
        if ($clave === '_meta') {
            continue;
        }
        
        // Validar que el item tenga los datos necesarios
        if (!isset($item['id_producto']) || !isset($item['talla']) || !isset($item['color']) || !isset($item['cantidad'])) {
            continue; // Saltar items inválidos
        }
        
        $id_producto = (int)$item['id_producto'];
        
        // Obtener datos del producto desde el array ya cargado
        if (isset($productos_db[$id_producto])) {
            $producto = $productos_db[$id_producto];
            
            // Calcular subtotal
            $subtotal = $producto['precio_actual'] * $item['cantidad'];
            $total_carrito += $subtotal;
            
            // Agregar datos del carrito
            $productos_carrito[] = array(
                'clave' => $clave,
                'id_producto' => $producto['id_producto'],
                'nombre_producto' => $producto['nombre_producto'],
                'precio_actual' => $producto['precio_actual'],
                'talla' => $item['talla'],
                'color' => $item['color'],
                'cantidad' => $item['cantidad'],
                'subtotal' => $subtotal
            );
        } else {
            // Producto no encontrado o inactivo - loggear para debugging
            error_log("Producto #{$id_producto} no encontrado o inactivo en carrito");
        }
    }
}

// Obtener mensaje si existe y luego eliminarlo
$mensaje = $_SESSION['mensaje_carrito'] ?? '';
if ($mensaje) {
    unset($_SESSION['mensaje_carrito']);
}

// Calcular cantidad total de items en el carrito (suma de todas las cantidades)
$cantidad_total_carrito = calcularCantidadTotalCarrito($_SESSION['carrito'] ?? []);

/**
 * Calcular información de envío para mostrar aviso
 * Nota: Sin provincia/localidad, mostramos información completa de costos
 */
$info_envio_carrito = obtenerInfoEnvioCarrito($total_carrito);
$monto_faltante_carrito = obtenerMontoFaltanteEnvioGratis($total_carrito);
?>


<?php include 'includes/header.php'; ?>

<!-- Contenido del carrito -->
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
                <div class="card shadow-sm mb-4 border-0">
                    <div class="card-header bg-white border-bottom py-2 py-md-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold small">
                                <i class="fas fa-shopping-bag me-2 text-primary"></i>
                                Productos (<?php echo $cantidad_total_carrito; ?>)
                            </h5>
                            <form method="POST" action="carrito.php" class="d-inline">
                                <input type="hidden" name="accion" value="vaciar">
                                <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Estás seguro de vaciar el carrito?')" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                    <i class="fas fa-trash-alt me-1"></i><span class="d-none d-sm-inline">Vaciar Todo</span>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($productos_carrito as $index => $producto): ?>
                        <div class="row g-0 border-bottom p-3 align-items-center product-item" style="transition: background-color 0.2s;">
                            <!-- Información del producto -->
                            <div class="col-12 col-md-6 mb-2 mb-md-0 ps-2 ps-md-3">
                                <h6 class="mb-1 mb-md-2 fw-bold small">
                                    <a href="detalle-producto.php?id=<?php echo $producto['id_producto']; ?>" class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($producto['nombre_producto']); ?>
                                    </a>
                                </h6>
                                <div class="d-flex flex-wrap gap-1 mb-1">
                                    <span class="badge bg-secondary small">
                                        <i class="fas fa-ruler-vertical me-1"></i><?php echo htmlspecialchars($producto['talla']); ?>
                                    </span>
                                    <span class="badge bg-secondary small">
                                        <i class="fas fa-palette me-1"></i><?php echo htmlspecialchars($producto['color']); ?>
                                    </span>
                                </div>
                                <div class="d-md-none small">
                                    <span class="text-muted">Precio: </span>
                                    <strong>$<?php echo number_format($producto['precio_actual'], 2); ?></strong>
                                </div>
                            </div>
                            
                            <!-- Precio unitario (solo desktop) -->
                            <div class="col-md-2 text-center mb-2 mb-md-0 d-none d-md-block">
                                <div class="small text-muted mb-1">Precio</div>
                                <div class="fw-bold text-dark">$<?php echo number_format($producto['precio_actual'], 2); ?></div>
                            </div>
                            
                            <!-- Cantidad -->
                            <div class="col-6 col-md-2 mb-2 mb-md-0">
                                <form method="POST" action="carrito.php" class="d-inline w-100" id="form-cantidad-<?php echo $index; ?>">
                                    <input type="hidden" name="accion" value="actualizar">
                                    <input type="hidden" name="clave" value="<?php echo htmlspecialchars($producto['clave']); ?>">
                                    <label class="small text-muted d-block text-center mb-1 d-md-none">Cantidad</label>
                                    <div class="input-group input-group-sm mx-auto" style="max-width: 110px;">
                                        <button class="btn btn-outline-secondary" type="button" onclick="updateCantidad(<?php echo $index; ?>, -1)" style="min-width: 32px; padding: 0.25rem;">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" 
                                               name="cantidad" 
                                               id="cantidad-<?php echo $index; ?>"
                                               class="form-control text-center border-top border-bottom" 
                                               value="<?php echo $producto['cantidad']; ?>" 
                                               min="1" 
                                               style="font-weight: 600; border-left: 0; border-right: 0; padding: 0.25rem;">
                                        <button class="btn btn-outline-secondary" type="button" onclick="updateCantidad(<?php echo $index; ?>, 1)" style="min-width: 32px; padding: 0.25rem;">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Subtotal y eliminar -->
                            <div class="col-6 col-md-2 text-center text-md-end">
                                <div class="mb-1 mb-md-2">
                                    <div class="small text-muted mb-0 d-none d-md-block">Subtotal</div>
                                    <div class="fw-bold text-primary fs-6" id="subtotal-producto-<?php echo htmlspecialchars($producto['clave']); ?>">$<?php echo number_format($producto['subtotal'], 2); ?></div>
                                </div>
                                <form method="POST" action="carrito.php" class="d-inline">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="clave" value="<?php echo htmlspecialchars($producto['clave']); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar producto" style="padding: 0.25rem 0.5rem;">
                                        <i class="fas fa-trash"></i><span class="d-none d-lg-inline ms-1">Eliminar</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Resumen del pedido -->
            <div class="col-lg-4">
                <div class="card shadow-sm sticky-card border-0">
                    <div class="card-header bg-dark text-white py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-receipt me-2"></i>
                            Resumen del Pedido
                        </h5>
                    </div>
                    <div class="card-body" data-monto-minimo-gratis="80000" data-costo-caba-gba="10000" data-costo-argentina="15000">
                        <!-- Aviso de envío gratis -->
                        <div id="carrito-envio-alert">
                        <?php if (!$info_envio_carrito['es_gratis'] && $monto_faltante_carrito > 0): ?>
                        <div class="alert alert-info mb-3 py-2" style="font-size: 0.8rem; border-left: 4px solid #0dcaf0;">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-truck me-2 mt-1"></i>
                                <div>
                                    <strong class="d-block mb-1">¡Agrega $<?php echo number_format($monto_faltante_carrito, 2); ?> más y obtén envío gratis!</strong>
                                    <small>En compras superiores a $80,000 en CABA y GBA</small>
                                </div>
                            </div>
                        </div>
                        <?php elseif ($info_envio_carrito['es_gratis']): ?>
                        <div class="alert alert-success mb-3 py-2" style="font-size: 0.8rem; border-left: 4px solid #198754;">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-truck me-2 mt-1"></i>
                                <div>
                                    <strong class="d-block mb-1">¡Envío gratis!</strong>
                                    <small>Tu compra supera los $80,000 en CABA y GBA</small>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning mb-3 py-2" style="font-size: 0.8rem; border-left: 4px solid #ffc107;">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-info-circle me-2 mt-1"></i>
                                <div>
                                    <strong class="d-block mb-1">Envío desde $<?php echo number_format($info_envio_carrito['costo_caba_gba'], 2); ?></strong>
                                    <small>Gratis en compras superiores a $80,000 en CABA y GBA</small>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        </div>

                        <!-- Totales -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <span class="text-muted small">Subtotal:</span>
                                <strong class="fs-6" id="carrito-subtotal">$<?php echo number_format($total_carrito, 2); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <span class="text-muted small fw-bold">Envío:</span>
                                <div class="text-end" id="carrito-envio">
                                    <?php if ($info_envio_carrito['es_gratis']): ?>
                                    <span class="text-success fw-bold small">GRATIS</span>
                                    <?php else: ?>
                                    <div class="small" style="font-size: 0.75rem; line-height: 1.5;">
                                        <div class="text-muted mb-1">
                                            CABA/GBA: <strong class="text-dark">$<?php echo number_format($info_envio_carrito['costo_caba_gba'], 2); ?></strong>
                                        </div>
                                        <div class="text-muted">
                                            Todo Argentina: <strong class="text-dark">$<?php echo number_format($info_envio_carrito['costo_argentina'], 2); ?></strong>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold">Total estimado:</h6>
                                <div id="carrito-total">
                                <?php if ($info_envio_carrito['es_gratis']): ?>
                                <h6 class="text-primary mb-0 fw-bold">$<?php echo number_format($total_carrito, 2); ?></h6>
                                <?php else: ?>
                                <div class="text-end">
                                    <h6 class="text-primary mb-0 fw-bold">$<?php echo number_format($total_carrito + $info_envio_carrito['costo_caba_gba'], 2); ?>*</h6>
                                    <small class="text-muted" style="font-size: 0.65rem;">*Incluye envío desde $<?php echo number_format($info_envio_carrito['costo_caba_gba'], 2); ?></small>
                                </div>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="d-grid gap-2">
                            <?php if (isset($_SESSION['id_usuario'])): ?>
                            <!-- Usuario logueado: ir a checkout -->
                            <a href="checkout.php" class="btn btn-success">
                                <i class="fas fa-credit-card me-2"></i>Proceder al Pago
                            </a>
                            <?php else: ?>
                            <!-- Usuario no logueado: sugerir login -->
                            <a href="login.php" class="btn btn-success">
                                <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión para Comprar
                            </a>
                            <?php endif; ?>
                            
                            <a href="index.php#productos" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left me-2"></i>Seguir Comprando
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

<style>
    /* Prevenir overflow horizontal */
    .container {
        overflow-x: hidden;
    }
    
    /* Efectos hover para productos */
    .product-item:hover {
        background-color: #f8f9fa !important;
    }
    
    
    /* Mejora en botones de cantidad */
    .input-group .btn-outline-secondary:hover {
        background-color: #6c757d;
        border-color: #6c757d;
        color: white;
    }
    
    /* Mejora en botones de acción */
    .btn-success:hover {
        box-shadow: 0 4px 8px var(--color-success);
    }
    
    /* Mejora en card de resumen */
    .sticky-card {
        transition: box-shadow 0.3s ease;
    }
    
    .sticky-card:hover {
        box-shadow: 0 0.5rem 1rem var(--color-shadow) !important;
    }
    
    /* Mejora en badges - más compactos */
    .badge {
        font-weight: 500;
        padding: 0.25em 0.5em;
        font-size: 0.75rem;
    }
    
    /* Mejora en alertas */
    .alert {
        border-radius: 0.5rem;
    }
    
    /* Separación visual mejorada */
    .border-bottom:last-child {
        border-bottom: none !important;
    }
    
    /* Optimizar espacios en móvil */
    @media (max-width: 767.98px) {
        .product-item {
            padding: 0.75rem !important;
        }
        
        .card-body {
            font-size: 0.9rem;
        }
    }
    
    /* Asegurar que las imágenes no se desborden */
    img {
        max-width: 100%;
        height: auto;
    }
</style>

<script>
    /**
     * Actualizar cantidad de producto en el carrito usando AJAX
     * @param {number} index - Índice del producto
     * @param {number} cambio - Cambio a aplicar (-1 o 1)
     */
    function updateCantidad(index, cambio) {
        const input = document.getElementById('cantidad-' + index);
        const form = document.getElementById('form-cantidad-' + index);
        let nuevaCantidad = parseInt(input.value) + cambio;
        
        // Validar límites
        if (nuevaCantidad < 1) {
            nuevaCantidad = 1;
        }
        
        // Actualizar valor y enviar vía AJAX
        input.value = nuevaCantidad;
        actualizarCantidadAjax(form);
    }
    
    /**
     * Actualizar cantidad de producto mediante AJAX
     * @param {HTMLElement} form - Formulario con los datos del producto
     */
    function actualizarCantidadAjax(form) {
        const formData = new FormData(form);
        formData.append('ajax', '1');
        
        // Deshabilitar inputs mientras se procesa
        const inputs = form.querySelectorAll('input, button');
        inputs.forEach(input => input.disabled = true);
        
        fetch('carrito.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Actualizar subtotal del producto si existe
                const claveInput = form.querySelector('input[name="clave"]');
                if (claveInput && data.subtotal_producto !== undefined) {
                    const subtotalEl = document.getElementById('subtotal-producto-' + claveInput.value);
                    if (subtotalEl) {
                        subtotalEl.textContent = '$' + data.subtotal_producto.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                }
                
                // Si el producto fue eliminado, recargar la página
                if (data.producto_eliminado) {
                    window.location.reload();
                    return;
                }
                
                // Actualizar resumen del carrito
                actualizarResumenCarrito(data);
            } else {
                alert(data.mensaje || 'Error al actualizar la cantidad');
                // Recargar para mostrar estado actual
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al actualizar la cantidad. Por favor, intenta nuevamente.');
            window.location.reload();
        })
        .finally(() => {
            // Rehabilitar inputs
            inputs.forEach(input => input.disabled = false);
        });
    }
    
    /**
     * Actualizar resumen del carrito con nuevos datos
     * @param {object} datos - Datos de respuesta del servidor
     */
    function actualizarResumenCarrito(datos) {
        const cardBody = document.querySelector('.card-body[data-monto-minimo-gratis]');
        if (!cardBody) return;
        
        const montoMinimoGratis = parseFloat(cardBody.getAttribute('data-monto-minimo-gratis')) || 80000;
        const costoCabaGba = parseFloat(cardBody.getAttribute('data-costo-caba-gba')) || 10000;
        const costoArgentina = parseFloat(cardBody.getAttribute('data-costo-argentina')) || 15000;
        
        // Actualizar subtotal
        const subtotalEl = document.getElementById('carrito-subtotal');
        if (subtotalEl) {
            subtotalEl.textContent = '$' + datos.subtotal.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        
        // Actualizar información de envío
        const envioEl = document.getElementById('carrito-envio');
        if (envioEl && datos.info_envio) {
            if (datos.info_envio.es_gratis) {
                envioEl.innerHTML = '<span class="text-success fw-bold small">GRATIS</span>';
            } else {
                envioEl.innerHTML = '<div class="small" style="font-size: 0.75rem; line-height: 1.5;">' +
                    '<div class="text-muted mb-1">' +
                    'CABA/GBA: <strong class="text-dark">$' + datos.info_envio.costo_caba_gba.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong>' +
                    '</div>' +
                    '<div class="text-muted">' +
                    'Todo Argentina: <strong class="text-dark">$' + datos.info_envio.costo_argentina.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong>' +
                    '</div>' +
                    '</div>';
            }
        }
        
        // Actualizar alertas de envío
        const alertEl = document.getElementById('carrito-envio-alert');
        if (alertEl && datos.info_envio) {
            let alertHTML = '';
            if (datos.info_envio.es_gratis) {
                alertHTML = '<div class="alert alert-success mb-3 py-2" style="font-size: 0.8rem; border-left: 4px solid #198754;">' +
                    '<div class="d-flex align-items-start">' +
                    '<i class="fas fa-truck me-2 mt-1"></i>' +
                    '<div>' +
                    '<strong class="d-block mb-1">¡Envío gratis!</strong>' +
                    '<small>Tu compra supera los $' + montoMinimoGratis.toLocaleString('es-AR') + ' en CABA y GBA</small>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
            } else if (datos.monto_faltante > 0) {
                alertHTML = '<div class="alert alert-info mb-3 py-2" style="font-size: 0.8rem; border-left: 4px solid #0dcaf0;">' +
                    '<div class="d-flex align-items-start">' +
                    '<i class="fas fa-truck me-2 mt-1"></i>' +
                    '<div>' +
                    '<strong class="d-block mb-1">¡Agrega $' + datos.monto_faltante.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' más y obtén envío gratis!</strong>' +
                    '<small>En compras superiores a $' + montoMinimoGratis.toLocaleString('es-AR') + ' en CABA y GBA</small>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
            } else {
                alertHTML = '<div class="alert alert-warning mb-3 py-2" style="font-size: 0.8rem; border-left: 4px solid #ffc107;">' +
                    '<div class="d-flex align-items-start">' +
                    '<i class="fas fa-info-circle me-2 mt-1"></i>' +
                    '<div>' +
                    '<strong class="d-block mb-1">Envío desde $' + costoCabaGba.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong>' +
                    '<small>Gratis en compras superiores a $' + montoMinimoGratis.toLocaleString('es-AR') + ' en CABA y GBA</small>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
            }
            alertEl.innerHTML = alertHTML;
        }
        
        // Actualizar total estimado
        const totalEl = document.getElementById('carrito-total');
        if (totalEl && datos.total_estimado !== undefined) {
            if (datos.info_envio && datos.info_envio.es_gratis) {
                totalEl.innerHTML = '<h6 class="text-primary mb-0 fw-bold">$' + datos.subtotal.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</h6>';
            } else {
                totalEl.innerHTML = '<div class="text-end">' +
                    '<h6 class="text-primary mb-0 fw-bold">$' + datos.total_estimado.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '*</h6>' +
                    '<small class="text-muted" style="font-size: 0.65rem;">*Incluye envío desde $' + costoCabaGba.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</small>' +
                    '</div>';
            }
        }
    }
    
    // Agregar event listener a inputs de cantidad para actualización manual
    document.addEventListener('DOMContentLoaded', function() {
        const cantidadInputs = document.querySelectorAll('input[name="cantidad"]');
        cantidadInputs.forEach(input => {
            // Remover el onchange inline y agregar event listener
            input.removeAttribute('onchange');
            input.addEventListener('change', function() {
                const form = this.closest('form');
                if (form) {
                    actualizarCantidadAjax(form);
                }
            });
        });
    });
</script>

<?php include 'includes/footer.php'; render_footer(); ?>

