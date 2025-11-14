<?php
/**
 * ========================================================================
 * PROCESAR PEDIDO - Tienda Seda y Lino
 * ========================================================================
 * Procesa el formulario de checkout y crea el pedido en la base de datos
 * - Valida datos del formulario y stock disponible
 * - Actualiza datos del usuario si se modificaron
 * - Crea pedido en tabla Pedidos
 * - Crea detalles en Detalle_Pedido
 * - Crea registro de pago en Pagos (estado: pendiente)
 * - Prepara datos para confirmacion-pedido.php
 * - Limpia el carrito de sesión
 * 
 * Funciones principales:
 * - Validar usuario logueado y carrito no vacío
 * - Validar stock disponible antes de procesar
 * - Crear pedido usando transacciones para garantizar atomicidad
 * - NO crear movimientos de stock (se crean al aprobar el pago)
 * 
 * Variables principales:
 * - $id_usuario: Usuario en sesión
 * - $carrito: Productos en el carrito
 * - $id_pedido: ID del pedido creado
 * 
 * Tablas utilizadas: Usuarios, Pedidos, Detalle_Pedido, Pagos, Forma_Pagos, Stock_Variantes, Productos
 * ========================================================================
 */

session_start();

require_once __DIR__ . '/config/database.php';

// Incluir funciones necesarias
require_once __DIR__ . '/includes/queries/pedido_queries.php';
require_once __DIR__ . '/includes/queries/producto_queries.php';
require_once __DIR__ . '/includes/queries/forma_pago_queries.php';
require_once __DIR__ . '/includes/queries/pago_queries.php';
require_once __DIR__ . '/includes/perfil_functions.php';
require_once __DIR__ . '/includes/envio_functions.php';

/**
 * Validaciones iniciales
 */
// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensaje_error'] = "Método de solicitud no válido";
    header('Location: checkout.php');
    exit;
}

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

$id_usuario = $_SESSION['id_usuario'];
$carrito = $_SESSION['carrito'];

/**
 * Limpiar errores de checkout anteriores al intentar procesar nuevamente
 */
if (isset($_SESSION['checkout_errores'])) {
    unset($_SESSION['checkout_errores']);
}
if (isset($_SESSION['checkout_productos_problema'])) {
    unset($_SESSION['checkout_productos_problema']);
}

/**
 * Validar datos del formulario
 */
$campos_requeridos = [
    'nombre' => 'Nombre',
    'apellido' => 'Apellido',
    'telefono' => 'Teléfono',
    'email_contacto' => 'Email de contacto',
    'direccion_calle' => 'Dirección (calle)',
    'direccion_numero' => 'Dirección (número)',
    'provincia' => 'Provincia',
    'localidad' => 'Localidad',
    'codigo_postal' => 'Código postal',
    'id_forma_pago' => 'Método de pago'
];

$campos_faltantes = [];
foreach ($campos_requeridos as $campo => $etiqueta) {
    if (!isset($_POST[$campo]) || trim($_POST[$campo]) === '') {
        $campos_faltantes[] = $etiqueta;
    }
}

if (!empty($campos_faltantes)) {
    $_SESSION['mensaje_error'] = "Faltan campos obligatorios: " . implode(', ', $campos_faltantes);
    header('Location: checkout.php');
    exit;
}

// Validar formato de email
$email_contacto = filter_var(trim($_POST['email_contacto']), FILTER_VALIDATE_EMAIL);
if (!$email_contacto) {
    $_SESSION['mensaje_error'] = "El email de contacto no es válido";
    header('Location: checkout.php');
    exit;
}

// Validar formato de teléfono (solo números y símbolos permitidos)
$telefono = trim($_POST['telefono']);
// Validar longitud según diccionario: 6-20 caracteres
if (strlen($telefono) < 6) {
    $_SESSION['mensaje_error'] = "El teléfono debe tener al menos 6 caracteres";
    header('Location: checkout.php');
    exit;
}
if (strlen($telefono) > 20) {
    $_SESSION['mensaje_error'] = "El teléfono no puede exceder 20 caracteres";
    header('Location: checkout.php');
    exit;
}
if (!preg_match('/^[0-9+\-() ]+$/', $telefono)) {
    $_SESSION['mensaje_error'] = "El teléfono contiene caracteres no permitidos";
    header('Location: checkout.php');
    exit;
}

// Validar código postal (solo letras, números y espacios)
$codigo_postal = trim($_POST['codigo_postal']);
if (!preg_match('/^[A-Za-z0-9 ]+$/', $codigo_postal)) {
    $_SESSION['mensaje_error'] = "El código postal contiene caracteres no permitidos";
    header('Location: checkout.php');
    exit;
}

// Validar ID de forma de pago
$id_forma_pago = intval($_POST['id_forma_pago']);
if ($id_forma_pago <= 0) {
    $_SESSION['mensaje_error'] = "Debes seleccionar un método de pago válido";
    header('Location: checkout.php');
    exit;
}

/**
 * Validar stock disponible para todos los productos del carrito
 * Usar FOR UPDATE para evitar race conditions
 */
$productos_validos = [];
$total_pedido = 0;
$errores_stock = [];
$checkout_errores = [];
$checkout_productos_problema = [];

// Iniciar transacción para validar stock con FOR UPDATE (evita race conditions)
$mysqli->begin_transaction();

try {
    foreach ($carrito as $clave => $item) {
        // Saltar metadatos del carrito
        if ($clave === '_meta') {
            continue;
        }
        
        // Validar que el item tenga los datos necesarios
        if (!isset($item['id_producto']) || !isset($item['talla']) || !isset($item['color']) || !isset($item['cantidad'])) {
            continue;
        }
        
        // Obtener datos del producto y verificar stock
        $producto = obtenerProductoConVariante($mysqli, $item['id_producto'], $item['talla'], $item['color']);
        
        if (!$producto || empty($producto['id_variante'])) {
            $mensaje_error = "El producto seleccionado ya no está disponible";
            $errores_stock[] = $mensaje_error;
            $checkout_errores[] = [
                'clave' => $clave,
                'mensaje' => $mensaje_error,
                'tipo' => 'producto_no_disponible'
            ];
            $checkout_productos_problema[$clave] = [
                'clave' => $clave,
                'id_producto' => $item['id_producto'],
                'talla' => $item['talla'],
                'color' => $item['color'],
                'cantidad_solicitada' => $item['cantidad'],
                'stock_disponible' => 0,
                'precio_actual' => 0,
                'disponible' => false
            ];
            continue;
        }
        
        $id_variante = intval($producto['id_variante']);
        $cantidad_solicitada = intval($item['cantidad']);
        
        // Validar stock con FOR UPDATE para bloquear la fila y evitar race conditions
        $sql_stock = "
            SELECT 
                sv.stock, 
                sv.activo as variante_activa, 
                p.activo as producto_activo,
                p.nombre_producto,
                p.precio_actual,
                sv.talle,
                sv.color
            FROM Stock_Variantes sv
            INNER JOIN Productos p ON sv.id_producto = p.id_producto
            WHERE sv.id_variante = ?
            FOR UPDATE
        ";
        
        $stmt_stock = $mysqli->prepare($sql_stock);
        if (!$stmt_stock) {
            throw new Exception('Error al validar stock disponible');
        }
        
        $stmt_stock->bind_param('i', $id_variante);
        $stmt_stock->execute();
        $result_stock = $stmt_stock->get_result();
        $datos_stock = $result_stock->fetch_assoc();
        $stmt_stock->close();
        
        if (!$datos_stock) {
            $mensaje_error = "El producto seleccionado ya no está disponible";
            $errores_stock[] = $mensaje_error;
            $checkout_errores[] = [
                'clave' => $clave,
                'mensaje' => $mensaje_error,
                'tipo' => 'producto_no_encontrado'
            ];
            $checkout_productos_problema[$clave] = [
                'clave' => $clave,
                'id_producto' => $item['id_producto'],
                'talla' => $item['talla'],
                'color' => $item['color'],
                'cantidad_solicitada' => $cantidad_solicitada,
                'stock_disponible' => 0,
                'precio_actual' => floatval($producto['precio_actual'] ?? 0),
                'disponible' => false
            ];
            continue;
        }
        
        $stock_disponible = intval($datos_stock['stock']);
        $variante_activa = intval($datos_stock['variante_activa']);
        $producto_activo = intval($datos_stock['producto_activo']);
        $precio_actual = floatval($datos_stock['precio_actual']);
        
        // Validar que variante y producto estén activos
        if ($variante_activa === 0 || $producto_activo === 0) {
            if ($variante_activa === 0) {
                $mensaje_error = "La variante {$datos_stock['talle']} {$datos_stock['color']} del producto {$datos_stock['nombre_producto']} está inactiva";
                $errores_stock[] = $mensaje_error;
                $checkout_errores[] = [
                    'clave' => $clave,
                    'mensaje' => $mensaje_error,
                    'tipo' => 'variante_inactiva'
                ];
            }
            if ($producto_activo === 0) {
                $mensaje_error = "El producto {$datos_stock['nombre_producto']} está inactivo";
                $errores_stock[] = $mensaje_error;
                $checkout_errores[] = [
                    'clave' => $clave,
                    'mensaje' => $mensaje_error,
                    'tipo' => 'producto_inactivo'
                ];
            }
            $checkout_productos_problema[$clave] = [
                'clave' => $clave,
                'id_producto' => $item['id_producto'],
                'talla' => $datos_stock['talle'],
                'color' => $datos_stock['color'],
                'cantidad_solicitada' => $cantidad_solicitada,
                'stock_disponible' => $stock_disponible,
                'precio_actual' => $precio_actual,
                'disponible' => false,
                'nombre_producto' => $datos_stock['nombre_producto']
            ];
            continue;
        }
        
        // Verificar stock disponible
        if ($stock_disponible < $cantidad_solicitada) {
            $mensaje_error = "Stock insuficiente para {$datos_stock['nombre_producto']} (Talla: {$datos_stock['talle']}, Color: {$datos_stock['color']}). Disponible: {$stock_disponible}, Solicitado: {$cantidad_solicitada}";
            $errores_stock[] = $mensaje_error;
            $checkout_errores[] = [
                'clave' => $clave,
                'mensaje' => $mensaje_error,
                'tipo' => 'stock_insuficiente',
                'stock_disponible' => $stock_disponible,
                'cantidad_solicitada' => $cantidad_solicitada
            ];
            $checkout_productos_problema[$clave] = [
                'clave' => $clave,
                'id_producto' => $item['id_producto'],
                'talla' => $datos_stock['talle'],
                'color' => $datos_stock['color'],
                'cantidad_solicitada' => $cantidad_solicitada,
                'stock_disponible' => $stock_disponible,
                'precio_actual' => $precio_actual,
                'disponible' => true,
                'nombre_producto' => $datos_stock['nombre_producto']
            ];
            continue;
        }
        
        // Calcular subtotal
        $precio_unitario = floatval($producto['precio_actual']);
        $subtotal = $precio_unitario * $cantidad_solicitada;
        $total_pedido += $subtotal;
        
        // Guardar producto válido con todos los datos necesarios
        // Usar talle de la BD (con 'e') para consistencia
        $productos_validos[] = [
            'clave' => $clave,
            'id_producto' => $producto['id_producto'],
            'id_variante' => $producto['id_variante'],
            'nombre_producto' => $producto['nombre_producto'],
            'precio_unitario' => $precio_unitario,
            'talle' => $producto['talle'], // Usar talle de BD (con 'e')
            'color' => $producto['color'],
            'cantidad' => $cantidad_solicitada,
            'subtotal' => $subtotal
        ];
    }
    
    // Si hay errores de stock, hacer rollback y redirigir a checkout con información detallada
    if (!empty($errores_stock)) {
        $mysqli->rollback();
        $_SESSION['mensaje_error'] = "Hay problemas con algunos productos en tu carrito. Por favor, revisa los detalles a continuación.";
        $_SESSION['checkout_errores'] = $checkout_errores;
        $_SESSION['checkout_productos_problema'] = $checkout_productos_problema;
        header('Location: checkout.php');
        exit;
    }
    
    // Si todo está bien, hacer commit de la validación (pero aún no creamos el pedido)
    $mysqli->commit();
    
} catch (Exception $e) {
    $mysqli->rollback();
    $error_message = $e->getMessage();
    $error_file = $e->getFile();
    $error_line = $e->getLine();
    error_log("Error al validar stock en procesar-pedido.php: " . $error_message);
    error_log("Archivo: " . $error_file . " Línea: " . $error_line);
    
    // Mensaje más descriptivo según el tipo de error
    if (strpos($error_message, 'STOCK_INSUFICIENTE') !== false) {
        $_SESSION['mensaje_error'] = "Algunos productos ya no tienen stock disponible. Por favor, revisa tu carrito.";
    } else {
        $_SESSION['mensaje_error'] = "Error al validar stock disponible. Por favor, intenta nuevamente. Si el problema persiste, contacta al administrador.";
    }
    header('Location: checkout.php');
    exit;
}

// Verificar que haya productos válidos
if (empty($productos_validos)) {
    $_SESSION['mensaje_error'] = "No hay productos disponibles en tu carrito. Por favor, agrega productos antes de continuar.";
    header('Location: checkout.php');
    exit;
}

/**
 * Verificar que la forma de pago existe y está activa
 */
$forma_pago = obtenerFormaPagoPorId($mysqli, $id_forma_pago);
if (!$forma_pago) {
    $_SESSION['mensaje_error'] = "El método de pago seleccionado no es válido";
    header('Location: checkout.php');
    exit;
}

/**
 * Iniciar transacción para garantizar atomicidad
 */
$mysqli->begin_transaction();

try {
    /**
     * Actualizar datos del usuario si se modificaron
     */
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $telefono = trim($_POST['telefono']);
    $email_contacto = trim($_POST['email_contacto']);
    $direccion_calle = trim($_POST['direccion_calle']);
    $direccion_numero = trim($_POST['direccion_numero']);
    $direccion_piso = trim($_POST['direccion_piso'] ?? '');
    $provincia = trim($_POST['provincia']);
    $localidad = trim($_POST['localidad']);
    $codigo_postal = trim($_POST['codigo_postal']);
    
    // Combinar dirección completa
    $direccion_completa = trim($direccion_calle) . ' ' . trim($direccion_numero);
    if (!empty($direccion_piso)) {
        $direccion_completa .= ' ' . trim($direccion_piso);
    }
    $direccion_completa = trim($direccion_completa);
    
    // Actualizar datos del usuario
    $sql_update_usuario = "UPDATE Usuarios SET 
        nombre = ?,
        apellido = ?,
        telefono = ?,
        email = ?,
        direccion = ?,
        localidad = ?,
        provincia = ?,
        codigo_postal = ?,
        fecha_actualizacion = NOW()
        WHERE id_usuario = ? AND activo = 1";
    
    $stmt_update_usuario = $mysqli->prepare($sql_update_usuario);
    if (!$stmt_update_usuario) {
        throw new Exception("Error al preparar actualización de usuario: " . $mysqli->error);
    }
    
    $stmt_update_usuario->bind_param('ssssssssi', 
        $nombre, 
        $apellido, 
        $telefono, 
        $email_contacto, 
        $direccion_completa, 
        $localidad, 
        $provincia, 
        $codigo_postal, 
        $id_usuario
    );
    
    if (!$stmt_update_usuario->execute()) {
        throw new Exception("Error al actualizar datos del usuario: " . $stmt_update_usuario->error);
    }
    $stmt_update_usuario->close();
    
    /**
     * Crear pedido
     */
    $id_pedido = crearPedido($mysqli, $id_usuario, 'pendiente');
    
    if (!$id_pedido || $id_pedido <= 0) {
        throw new Exception("Error al crear el pedido");
    }
    
    /**
     * Crear detalles del pedido
     */
    foreach ($productos_validos as $producto) {
        $resultado = agregarDetallePedido(
            $mysqli, 
            $id_pedido, 
            $producto['id_variante'], 
            $producto['cantidad'], 
            $producto['precio_unitario']
        );
        
        if (!$resultado) {
            throw new Exception("Error al agregar detalle del pedido para el producto: {$producto['nombre_producto']}");
        }
    }
    
    /**
     * Calcular costo de envío
     */
    $info_envio = calcularCostoEnvio($total_pedido, $provincia, $localidad);
    $costo_envio = $info_envio['costo'];
    $total_pedido_con_envio = calcularTotalConEnvio($total_pedido, $costo_envio);
    
    /**
     * Crear registro de pago (incluye costo de envío)
     */
    $id_pago = crearPago($mysqli, $id_pedido, $id_forma_pago, $total_pedido_con_envio, 'pendiente');
    
    if (!$id_pago || $id_pago <= 0) {
        throw new Exception("Error al crear el registro de pago");
    }
    
    /**
     * Actualizar total del pedido (incluye costo de envío)
     */
    $sql_update_total = "UPDATE Pedidos SET total = ? WHERE id_pedido = ?";
    $stmt_update_total = $mysqli->prepare($sql_update_total);
    if (!$stmt_update_total) {
        throw new Exception("Error al preparar actualización de total: " . $mysqli->error);
    }
    
    $stmt_update_total->bind_param('di', $total_pedido_con_envio, $id_pedido);
    if (!$stmt_update_total->execute()) {
        throw new Exception("Error al actualizar total del pedido: " . $stmt_update_total->error);
    }
    $stmt_update_total->close();
    
    /**
     * Actualizar dirección de entrega y teléfono de contacto en el pedido
     */
    $sql_update_pedido = "UPDATE Pedidos SET 
        direccion_entrega = ?,
        telefono_contacto = ?
        WHERE id_pedido = ?";
    
    $stmt_update_pedido = $mysqli->prepare($sql_update_pedido);
    if (!$stmt_update_pedido) {
        throw new Exception("Error al preparar actualización de pedido: " . $mysqli->error);
    }
    
    $stmt_update_pedido->bind_param('ssi', $direccion_completa, $telefono, $id_pedido);
    if (!$stmt_update_pedido->execute()) {
        throw new Exception("Error al actualizar datos de envío del pedido: " . $stmt_update_pedido->error);
    }
    $stmt_update_pedido->close();
    
    /**
     * Preparar datos para confirmacion-pedido.php
     */
    $pedido_exitoso = [
        'id_pedido' => $id_pedido,
        'metodo_pago' => $forma_pago['nombre'],
        'metodo_pago_descripcion' => $forma_pago['descripcion'] ?? null,
        'direccion' => $direccion_completa,
        'subtotal' => $total_pedido,
        'costo_envio' => $costo_envio,
        'es_envio_gratis' => $info_envio['es_gratis'],
        'total' => $total_pedido_con_envio,
        'productos' => []
    ];
    
    // Agregar productos al array de confirmación
    foreach ($productos_validos as $producto) {
        $pedido_exitoso['productos'][] = [
            'nombre_producto' => $producto['nombre_producto'],
            'talle' => $producto['talle'], // Ya viene de la BD con 'talle' (con 'e')
            'color' => $producto['color'],
            'cantidad' => $producto['cantidad'],
            'precio_unitario' => $producto['precio_unitario'],
            'subtotal' => $producto['subtotal']
        ];
    }
    
    /**
     * Confirmar transacción
     */
    $mysqli->commit();
    
    /**
     * Guardar datos en sesión para confirmacion-pedido.php
     */
    $_SESSION['pedido_exitoso'] = $pedido_exitoso;
    
    /**
     * Limpiar carrito de sesión
     */
    unset($_SESSION['carrito']);
    
    /**
     * Redirigir a página de confirmación
     */
    header('Location: confirmacion-pedido.php');
    exit;
    
} catch (Exception $e) {
    /**
     * Revertir transacción en caso de error
     */
    $mysqli->rollback();
    
    /**
     * Registrar error para debugging
     */
    $error_message = $e->getMessage();
    $error_file = $e->getFile();
    $error_line = $e->getLine();
    $error_trace = $e->getTraceAsString();
    
    error_log("Error en procesar-pedido.php: " . $error_message);
    error_log("Archivo: " . $error_file . " Línea: " . $error_line);
    error_log("Trace: " . $error_trace);
    
    /**
     * Redirigir con mensaje de error
     * En modo debug, mostrar más detalles del error
     */
    $debug_mode = (ini_get('display_errors') == 1);
    
    if ($debug_mode) {
        $_SESSION['mensaje_error'] = "Ocurrió un error al procesar tu pedido.<br><br>" .
            "<strong>Error:</strong> " . htmlspecialchars($error_message) . "<br>" .
            "<strong>Archivo:</strong> " . htmlspecialchars($error_file) . "<br>" .
            "<strong>Línea:</strong> " . $error_line . "<br>" .
            "<strong>Trace:</strong><pre style='font-size: 0.85rem; max-height: 200px; overflow-y: auto;'>" . htmlspecialchars($error_trace) . "</pre>";
    } else {
        $_SESSION['mensaje_error'] = "Ocurrió un error al procesar tu pedido. Por favor, intenta nuevamente. Si el problema persiste, contacta al administrador.";
    }
    
    header('Location: checkout.php');
    exit;
}

