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
require_once __DIR__ . '/includes/queries/stock_queries.php'; // Necesario para validarStockDisponibleVenta()
require_once __DIR__ . '/includes/perfil_functions.php';
require_once __DIR__ . '/includes/envio_functions.php';
require_once __DIR__ . '/includes/carrito_functions.php';
require_once __DIR__ . '/includes/email_gmail_functions.php';
require_once __DIR__ . '/includes/queries/usuario_queries.php'; // Para obtenerUsuarioPorId() y actualizarDatosUsuarioCompleto()
require_once __DIR__ . '/includes/validation_functions.php'; // Funciones de validación centralizadas

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

// Verificar que el carrito tenga productos reales (excluyendo _meta)
if (!tieneProductosReales($_SESSION['carrito'] ?? [])) {
    $_SESSION['mensaje_error'] = "Tu carrito está vacío";
    header('Location: carrito.php');
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$carrito = $_SESSION['carrito'];

/**
 * Limpiar errores de checkout anteriores al intentar procesar nuevamente
 * Esto permite que el usuario intente procesar el pedido nuevamente después de corregir problemas
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

// Validar formato de email usando función centralizada
$validacion_email = validarEmail($_POST['email_contacto']);
if (!$validacion_email['valido']) {
    $_SESSION['mensaje_error'] = $validacion_email['error'];
    header('Location: checkout.php');
    exit;
}
$email_contacto = strtolower(trim($_POST['email_contacto'])); // Usar original sin sanitizar para BD

// Validar formato de teléfono usando función centralizada
$validacion_telefono = validarTelefono($_POST['telefono'], false);
if (!$validacion_telefono['valido']) {
    $_SESSION['mensaje_error'] = $validacion_telefono['error'];
    header('Location: checkout.php');
    exit;
}
$telefono = $validacion_telefono['valor'];

// Validar código postal usando función centralizada
$validacion_codigo_postal = validarCodigoPostal($_POST['codigo_postal']);
if (!$validacion_codigo_postal['valido']) {
    $_SESSION['mensaje_error'] = $validacion_codigo_postal['error'];
    header('Location: checkout.php');
    exit;
}
$codigo_postal = $validacion_codigo_postal['valor'];

// Validar ID de forma de pago
$id_forma_pago = intval($_POST['id_forma_pago']);
if ($id_forma_pago <= 0) {
    $_SESSION['mensaje_error'] = "Debes seleccionar un método de pago válido";
    header('Location: checkout.php');
    exit;
}

/**
 * Validar stock disponible para todos los productos del carrito
 * 
 * NOTA SOBRE VALIDACIÓN DE STOCK:
 * Esta es la validación DEFINITIVA que se ejecuta antes de crear el pedido.
 * Usa transacciones para garantizar atomicidad en la validación.
 * 
 * DIFERENCIA CON checkout.php:
 * - checkout.php: Validación preliminar rápida (sin bloqueo) para mostrar al usuario
 * - procesar-pedido.php: Validación definitiva con transacción
 * 
 * Esta validación es necesaria porque:
 * 1. Múltiples usuarios pueden intentar comprar el mismo producto simultáneamente
 * 2. El stock puede cambiar entre checkout y procesamiento
 * 3. Garantiza que solo se procesen pedidos con stock realmente disponible
 * 
 * NOTA: El stock se valida nuevamente al aprobar el pago antes de descontarlo.
 */
$productos_validos = [];
$total_pedido = 0;
$errores_stock = [];
$checkout_errores = [];
$checkout_productos_problema = [];

// FIX: Limpiar reservas expiradas ANTES de iniciar transacción
// Esto libera stock de pedidos viejos (>24h sin pago) para que esté disponible
// Se ejecuta FUERA de transacción para evitar bloqueos
try {
    require_once __DIR__ . '/includes/auto_cleanup_reservas.php';
    limpiarReservasExpiradas($mysqli);
} catch (Exception $e) {
    error_log("Error al limpiar reservas expiradas: " . $e->getMessage());
    // Continuar - no es crítico
}

// Iniciar transacción ÚNICA para todo el flujo
$mysqli->begin_transaction();

try {
    /**
     * Procesar cada producto del carrito usando función helper centralizada
     * NOTA: Este procesamiento es similar al de checkout.php pero con validación definitiva:
     * - checkout.php: Validación rápida para mostrar información al usuario
     * - procesar-pedido.php: Validación definitiva antes de crear el pedido
     * 
     * Ambos procesamientos son necesarios porque:
     * 1. checkout.php muestra información preliminar al usuario
     * 2. procesar-pedido.php valida definitivamente antes de crear el pedido
     * 3. El stock puede cambiar entre ambas validaciones
     */
    foreach ($carrito as $clave => $item) {
        // Saltar metadatos del carrito
        if ($clave === '_meta') {
            continue;
        }
        
        // Procesar item usando función helper (modo definitivo)
        $resultado = procesarItemCarrito($mysqli, $item, $clave, 'definitivo');
        
        // Si hay error, construir estructura de error usando función helper
        $error_checkout = construirErrorCheckout($resultado, $item, $clave);
        if ($error_checkout !== null) {
            $errores_stock[] = $error_checkout['mensaje'];
            $checkout_errores[] = [
                'clave' => $error_checkout['clave'],
                'mensaje' => $error_checkout['mensaje'],
                'tipo' => $error_checkout['tipo'],
                'stock_disponible' => $error_checkout['stock_disponible'],
                'cantidad_solicitada' => $error_checkout['cantidad_solicitada']
            ];
            $checkout_productos_problema[$clave] = [
                'clave' => $error_checkout['clave'],
                'id_producto' => $item['id_producto'],
                'talla' => $error_checkout['talla'],
                'color' => $error_checkout['color'],
                'cantidad_solicitada' => $error_checkout['cantidad_solicitada'],
                'stock_disponible' => $error_checkout['stock_disponible'],
                'precio_actual' => $error_checkout['precio_actual'],
                'disponible' => $error_checkout['disponible'],
                'nombre_producto' => $error_checkout['nombre_producto']
            ];
            continue;
        }
        
        // Producto válido: agregar a lista y actualizar total
        $productos_validos[] = [
            'clave' => $resultado['clave'],
            'id_producto' => $resultado['id_producto'],
            'id_variante' => $resultado['id_variante'],
            'nombre_producto' => $resultado['nombre_producto'],
            'precio_unitario' => $resultado['precio_actual'],
            'talle' => $resultado['talle'],
            'color' => $resultado['color'],
            'cantidad' => $resultado['cantidad'],
            'subtotal' => $resultado['subtotal']
        ];
        $total_pedido += $resultado['subtotal'];
    }
    
    // Si hay errores de stock, hacer rollback y redirigir a checkout con información detallada
    if (!empty($errores_stock)) {
        $mysqli->rollback();
        
        // Construir mensaje de error usando función helper
        $_SESSION['mensaje_error'] = construirMensajeErrorGeneral($checkout_errores);
        $_SESSION['checkout_errores'] = $checkout_errores;
        $_SESSION['checkout_productos_problema'] = $checkout_productos_problema;
        header('Location: checkout.php');
        exit;
    }
    
    // Verificar que haya productos válidos (dentro de la transacción)
    if (empty($productos_validos)) {
        $mysqli->rollback();
        $_SESSION['mensaje_error'] = "No hay productos disponibles en tu carrito. Acción sugerida: Agrega productos disponibles al carrito desde la tienda antes de continuar con el checkout.";
        header('Location: checkout.php');
        exit;
    }
    
    /**
     * Verificar que la forma de pago existe y está activa (dentro de la transacción)
     */
    $forma_pago = obtenerFormaPagoPorId($mysqli, $id_forma_pago);
    if (!$forma_pago) {
        $mysqli->rollback();
        $_SESSION['mensaje_error'] = "El método de pago seleccionado no es válido";
        header('Location: checkout.php');
        exit;
    }
    
    // NO hacer commit aquí - continuar con la misma transacción para crear el pedido y reservar stock
    // Esto previene race conditions entre validación y reserva de stock
    
} catch (Exception $e) {
    $mysqli->rollback();
    $error_message = $e->getMessage();
    $error_file = $e->getFile();
    $error_line = $e->getLine();
    error_log("Error al validar stock en procesar-pedido.php: " . $error_message);
    error_log("Archivo: " . $error_file . " Línea: " . $error_line);
    
    // Mensaje más descriptivo según el tipo de error con acciones sugeridas
    if (strpos($error_message, 'STOCK_INSUFICIENTE') !== false) {
        $_SESSION['mensaje_error'] = "El stock disponible ha cambiado mientras procesábamos tu pedido. Esto puede ocurrir cuando varios clientes compran simultáneamente. Acción sugerida: Revisa tu carrito, ajusta las cantidades según el stock disponible, o remueve los productos sin stock y continúa con los productos disponibles.";
    } elseif (strpos($error_message, 'inactivo') !== false) {
        $_SESSION['mensaje_error'] = "Algunos productos ya no están disponibles. Estos fueron removidos automáticamente de tu carrito. Acción sugerida: Revisa tu carrito y continúa con los productos disponibles, o agrega otros productos a tu carrito.";
    } else {
        $_SESSION['mensaje_error'] = "Ocurrió un error al validar el stock disponible. Acción sugerida: Intenta nuevamente. Si el problema persiste, contacta al administrador con el número de pedido si tienes uno, o describe el problema que estás experimentando.";
    }
    header('Location: checkout.php');
    exit;
}

// Continuar con la misma transacción iniciada en la línea 185
// NO iniciar nueva transacción aquí - ya estamos dentro de una
// Esta transacción única garantiza atomicidad entre validación de stock, creación de pedido y reserva de stock

try {
    /**
     * Actualizar datos del usuario usando función centralizada
     * NOTA: Seguimos dentro de la misma transacción iniciada en la línea 174
     */
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $direccion_calle = trim($_POST['direccion_calle']);
    $direccion_numero = trim($_POST['direccion_numero']);
    $direccion_piso = trim($_POST['direccion_piso'] ?? '');
    $provincia = trim($_POST['provincia']);
    $localidad = trim($_POST['localidad']);
    
    // Actualizar datos del usuario usando función centralizada con validaciones
    $resultado_actualizacion = actualizarDatosUsuarioCompleto(
        $mysqli,
        $id_usuario,
        $nombre,
        $apellido,
        $email_contacto,
        $telefono,
        $direccion_calle,
        $direccion_numero,
        $direccion_piso,
        $localidad,
        $provincia,
        $codigo_postal
    );
    
    if (!$resultado_actualizacion['exito']) {
        throw new Exception($resultado_actualizacion['error']);
    }
    
    // Obtener dirección completa para usar en el pedido (ya validada por la función)
    $validacion_direccion = validarDireccionCompleta($direccion_calle, $direccion_numero, $direccion_piso);
    $direccion_completa = $validacion_direccion['direccion_completa'];
    
    /**
     * Capturar y validar observaciones del pedido
     */
    $observaciones = null;
    if (isset($_POST['observaciones']) && !empty(trim($_POST['observaciones']))) {
        // Validar observaciones usando función centralizada
        $validacion_observaciones = validarObservaciones($_POST['observaciones'], 500, 'observaciones');
        if (!$validacion_observaciones['valido']) {
            throw new Exception($validacion_observaciones['error']);
        }
        $observaciones = $validacion_observaciones['valor'];
        // NOTA: NO sanitizar aquí con htmlspecialchars() - los datos deben guardarse en BD sin sanitizar
        // La sanitización debe hacerse solo al mostrar en HTML usando htmlspecialchars() en los templates
    }
    
    /**
     * Crear pedido con estado 'pendiente'
     * El pedido espera aprobación del pago. El stock se validará nuevamente
     * al aprobar el pago antes de descontarlo.
     */
    $id_pedido = crearPedido($mysqli, $id_usuario, 'pendiente', $observaciones);
    
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
     * Reservar stock inmediatamente al crear el pedido
     * Esto previene que múltiples clientes compren el mismo producto simultáneamente
     * El stock se descuenta físicamente pero se marca como reservado (no vendido aún)
     * Al aprobar el pago, la reserva se convierte en venta
     * Si se cancela el pedido, la reserva se libera
     */
    if (!reservarStockPedido($mysqli, $id_pedido, $id_usuario, true)) {
        throw new Exception("Error al reservar stock del pedido");
    }
    
    /**
     * Calcular costo de envío
     * NOTA: Este cálculo también se realiza en checkout.php, pero aquí es el cálculo FINAL
     * que se guarda en la base de datos. Se calcula nuevamente porque:
     * 1. El usuario puede haber modificado la dirección en el formulario de checkout
     * 2. El stock puede haber cambiado entre checkout y procesamiento
     * 3. Garantiza que el costo guardado corresponde exactamente a los datos del pedido procesado
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
     * Actualizar total, dirección de entrega y teléfono de contacto del pedido
     * Usa función centralizada para evitar código duplicado
     */
    $resultado_actualizacion = actualizarPedidoCompleto(
        $mysqli,
        $id_pedido,
        'pendiente', // Mantener estado actual
        $direccion_completa,
        $telefono,
        $observaciones,
        $total_pedido_con_envio
    );
    
    if (!$resultado_actualizacion) {
        throw new Exception("Error al actualizar datos del pedido");
    }
    
    /**
     * Preparar datos para confirmacion-pedido.php
     */
    $pedido_exitoso = [
        'id_pedido' => $id_pedido,
        'metodo_pago' => $forma_pago['nombre'],
        'metodo_pago_descripcion' => $forma_pago['descripcion'] ?? null,
        'direccion' => $direccion_completa,
        'localidad' => $localidad,
        'provincia' => $provincia,
        'codigo_postal' => $codigo_postal,
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
     * Enviar email de confirmación de pedido (no bloquear flujo si falla)
     */
    try {
        // Obtener datos del usuario desde la base de datos
        $usuario = obtenerUsuarioPorId($mysqli, $id_usuario);
        if ($usuario) {
            $datos_usuario = [
                'nombre' => $usuario['nombre'],
                'apellido' => $usuario['apellido'],
                'email' => $usuario['email']
            ];
            enviar_email_confirmacion_pedido_gmail($pedido_exitoso, $datos_usuario);
        } else {
            error_log("No se pudo obtener datos del usuario ID: $id_usuario para enviar email de confirmación");
        }
    } catch (Exception $e) {
        // Solo loggear error, no interrumpir el flujo
        error_log("Error al enviar email de confirmación de pedido ID: {$pedido_exitoso['id_pedido']}. Error: " . $e->getMessage());
    }
    
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
        // Mensaje mejorado con acciones sugeridas
        $_SESSION['mensaje_error'] = "Ocurrió un error al procesar tu pedido. Por favor, verifica que todos los datos estén correctos e intenta nuevamente. Si el problema persiste, contacta al administrador con los detalles de tu pedido.";
    }
    
    header('Location: checkout.php');
    exit;
}

