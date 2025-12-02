<?php
/**
 * ========================================================================
 * FUNCIONES COMUNES DEL CARRITO - Tienda Seda y Lino
 * ========================================================================
 * Funciones helper reutilizables para operaciones del carrito de compras
 *
 * FUNCIONES:
 * - tieneProductosReales(): Verifica si el carrito tiene productos reales (excluyendo _meta)
 * - calcularCantidadTotalCarrito(): Definida en carrito_cookie_functions.php (centralizada)
 * - limpiarMensajesErrorObsoletos(): Limpia mensajes de error obsoletos de la sesión
 *
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

// Cargar funciones de carrito con cookies (incluye calcularCantidadTotalCarrito)
require_once __DIR__ . '/carrito_cookie_functions.php';

// Requerir seguridad para redirigirConMensaje centralizada
require_once __DIR__ . '/security_functions.php';

/**
 * Verifica si el carrito tiene productos reales (excluyendo metadatos _meta)
 * 
 * @param array $carrito Array del carrito de sesión
 * @return bool True si tiene productos reales, false si está vacío
 */
function tieneProductosReales($carrito) {
    if (!isset($carrito) || !is_array($carrito) || empty($carrito)) {
        return false;
    }
    
    // Contar productos reales (excluyendo _meta)
    foreach ($carrito as $clave => $item) {
        if ($clave === '_meta') {
            continue;
        }
        // Verificar que el item tenga estructura válida
        if (isset($item['id_producto']) && isset($item['talla']) && 
            isset($item['color']) && isset($item['cantidad'])) {
            return true;
        }
    }
    
    return false;
}

/**
 * calcularCantidadTotalCarrito() - Definida en carrito_cookie_functions.php
 *
 * Función centralizada para calcular cantidad total de items en el carrito.
 * Se carga automáticamente al inicio de este archivo.
 *
 * @see carrito_cookie_functions.php::calcularCantidadTotalCarrito()
 */

/**
 * Limpia mensajes de error obsoletos de la sesión
 * Útil para limpiar mensajes relacionados con login cuando el usuario ya está logueado
 * 
 * @param string $patron_buscar Patrón de texto a buscar en el mensaje de error
 * @return void
 */
function limpiarMensajesErrorObsoletos($patron_buscar = 'Debes iniciar sesión') {
    if (isset($_SESSION['mensaje_error']) && 
        strpos($_SESSION['mensaje_error'], $patron_buscar) !== false) {
        unset($_SESSION['mensaje_error']);
    }
}

/**
 * Valida la estructura de un item del carrito
 * 
 * @param array $item Item del carrito a validar
 * @return bool True si la estructura es válida, false en caso contrario
 */
function validarEstructuraItemCarrito($item) {
    return isset($item['id_producto']) && 
           isset($item['talla']) && 
           isset($item['color']) && 
           isset($item['cantidad']);
}

/**
 * Procesa un item del carrito y valida su disponibilidad
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $item Item del carrito a procesar
 * @param string $clave Clave del item en el carrito
 * @param string $modo Modo de procesamiento: 'preliminar' (checkout) o 'definitivo' (procesar-pedido)
 * @return array|false Array con datos del producto procesado o false si hay error, o array con 'error' => true si hay error de validación
 */
function procesarItemCarrito($mysqli, $item, $clave, $modo = 'preliminar') {
    // Validar estructura del item
    if (!validarEstructuraItemCarrito($item)) {
        return false;
    }
    
    // Incluir función de validación de stock si no está incluida
    require_once __DIR__ . '/queries/stock_queries.php';
    
    $cantidad_solicitada = max(1, intval($item['cantidad']));
    
    // Usar función unificada de validación en ambos modos
    try {
        $datos_stock = validarStockDisponible(
            $mysqli, 
            $item['id_producto'], 
            $item['talla'], 
            $item['color'], 
            $cantidad_solicitada, 
            $modo,
            0 // cantidad_actual_carrito = 0 porque ya estamos procesando el item completo
        );
    } catch (Exception $e) {
        // En modo definitivo, retornar error estructurado
        if ($modo === 'definitivo') {
            // Obtener datos del producto para el mensaje de error
            require_once __DIR__ . '/queries/producto_queries.php';
            $producto = obtenerProductoConVariante($mysqli, $item['id_producto'], $item['talla'], $item['color']);
            
            return [
                'error' => true,
                'mensaje' => $e->getMessage(),
                'tipo' => determinarTipoError($e->getMessage()),
                'stock_disponible' => $producto ? intval($producto['stock'] ?? 0) : 0,
                'cantidad_solicitada' => $cantidad_solicitada,
                'nombre_producto' => $producto ? $producto['nombre_producto'] : 'producto desconocido',
                'talle' => $producto ? $producto['talle'] : $item['talla'],
                'color' => $producto ? $producto['color'] : $item['color'],
                'precio_actual' => $producto ? floatval($producto['precio_actual']) : 0
            ];
        } else {
            // En modo preliminar, retornar false (comportamiento original)
            return false;
        }
    }
    
    // Obtener datos completos del producto para el retorno
    require_once __DIR__ . '/queries/producto_queries.php';
    $producto = obtenerProductoConVariante($mysqli, $item['id_producto'], $item['talla'], $item['color']);
    
    // Si no se pudo obtener el producto, retornar false
    if (!$producto || empty($producto['id_variante'])) {
        return false;
    }
    
    // Retornar datos del producto procesado
    return [
        'error' => false,
        'clave' => $clave,
        'id_producto' => $producto['id_producto'],
        'id_variante' => $producto['id_variante'],
        'nombre_producto' => $producto['nombre_producto'],
        'precio_actual' => floatval($producto['precio_actual']),
        'talle' => $producto['talle'],
        'color' => $producto['color'],
        'cantidad' => $cantidad_solicitada,
        'stock_disponible' => $datos_stock['stock_disponible'],
        'subtotal' => floatval($producto['precio_actual']) * $cantidad_solicitada
    ];
}

/**
 * Determina el tipo de error basado en el mensaje de excepción
 * 
 * @param string $mensaje_error Mensaje de error de la excepción
 * @return string Tipo de error ('variante_inactiva', 'producto_inactivo', 'stock_insuficiente', 'error_validacion')
 */
function determinarTipoError($mensaje_error) {
    if (strpos($mensaje_error, 'variante inactiva') !== false) {
        return 'variante_inactiva';
    } elseif (strpos($mensaje_error, 'producto inactivo') !== false) {
        return 'producto_inactivo';
    } elseif (strpos($mensaje_error, 'Stock insuficiente') !== false) {
        return 'stock_insuficiente';
    } else {
        return 'error_validacion';
    }
}

/**
 * Construye estructura de error para checkout a partir del resultado de procesarItemCarrito
 * 
 * @param array|false $resultado Resultado de procesarItemCarrito (puede ser false o array con error)
 * @param array $item Item del carrito original
 * @param string $clave Clave del item en el carrito
 * @return array|null Array con estructura de error para checkout o null si no hay error
 */
function construirErrorCheckout($resultado, $item, $clave) {
    if ($resultado === false) {
        // Producto no encontrado o estructura inválida
        return [
            'clave' => $clave,
            'mensaje' => "El producto seleccionado ya no está disponible",
            'tipo' => 'producto_no_disponible',
            'stock_disponible' => 0,
            'cantidad_solicitada' => intval($item['cantidad'] ?? 0),
            'precio_actual' => 0,
            'talla' => $item['talla'] ?? '',
            'color' => $item['color'] ?? '',
            'nombre_producto' => 'producto desconocido'
        ];
    }
    
    if (is_array($resultado) && isset($resultado['error']) && $resultado['error']) {
        // Error estructurado de validación de stock
        $datos_producto = [
            'nombre_producto' => $resultado['nombre_producto'] ?? 'producto desconocido',
            'talle' => $resultado['talle'] ?? $item['talla'],
            'color' => $resultado['color'] ?? $item['color']
        ];
        $mensaje_error = construirMensajeErrorStock($resultado, $datos_producto);
        $tipo_error = $resultado['tipo'] ?? 'error_validacion';
        
        return [
            'clave' => $clave,
            'mensaje' => $mensaje_error,
            'tipo' => $tipo_error,
            'stock_disponible' => $resultado['stock_disponible'] ?? 0,
            'cantidad_solicitada' => $resultado['cantidad_solicitada'] ?? intval($item['cantidad']),
            'precio_actual' => $resultado['precio_actual'] ?? 0,
            'talla' => $resultado['talle'] ?? $item['talla'],
            'color' => $resultado['color'] ?? $item['color'],
            'nombre_producto' => $resultado['nombre_producto'] ?? 'producto desconocido',
            'disponible' => ($tipo_error === 'stock_insuficiente' && ($resultado['stock_disponible'] ?? 0) > 0)
        ];
    }
    
    return null; // No hay error
}

/**
 * Construye mensaje de error detallado para el usuario
 * 
 * @param array $error Array con información del error
 * @param array $datos_producto Datos adicionales del producto (nombre, talle, color)
 * @return string Mensaje de error formateado
 */
function construirMensajeErrorStock($error, $datos_producto = []) {
    $tipo_error = $error['tipo'] ?? 'error_validacion';
    $stock_disponible = $error['stock_disponible'] ?? 0;
    $cantidad_solicitada = $error['cantidad_solicitada'] ?? 0;
    $nombre_producto = $datos_producto['nombre_producto'] ?? 'el producto';
    $talle = $datos_producto['talle'] ?? '';
    $color = $datos_producto['color'] ?? '';
    
    switch ($tipo_error) {
        case 'variante_inactiva':
            return "La variante {$talle} {$color} del producto {$nombre_producto} ya no está disponible. Este producto fue removido de tu carrito.";
        case 'producto_inactivo':
            return "El producto {$nombre_producto} ya no está disponible. Este producto fue removido de tu carrito.";
        case 'stock_insuficiente':
            if ($stock_disponible > 0) {
                return "Stock insuficiente para {$nombre_producto} (Talla: {$talle}, Color: {$color}). Disponible: {$stock_disponible} unidades, Solicitado: {$cantidad_solicitada}. Acción sugerida: Ajusta la cantidad a {$stock_disponible} unidades o menos, o remueve el producto de tu carrito.";
            } else {
                return "Stock agotado para {$nombre_producto} (Talla: {$talle}, Color: {$color}). Este producto fue removido automáticamente de tu carrito. Acción sugerida: Revisa otros productos disponibles o contacta al administrador si necesitas este producto específico.";
            }
        default:
            return "Error al validar el producto {$nombre_producto}. Acción sugerida: Intenta nuevamente o contacta al administrador con el nombre del producto si el problema persiste.";
    }
}

/**
 * Construye mensaje de error general basado en los errores de stock
 * 
 * @param array $checkout_errores Array de errores de checkout
 * @return string Mensaje de error general
 */
function construirMensajeErrorGeneral($checkout_errores) {
    $productos_removidos = 0;
    $productos_ajustables = 0;
    
    foreach ($checkout_errores as $error) {
        if (in_array($error['tipo'] ?? '', ['variante_inactiva', 'producto_inactivo']) || 
            ($error['tipo'] === 'stock_insuficiente' && ($error['stock_disponible'] ?? 0) == 0)) {
            $productos_removidos++;
        } elseif ($error['tipo'] === 'stock_insuficiente' && ($error['stock_disponible'] ?? 0) > 0) {
            $productos_ajustables++;
        }
    }
    
    if ($productos_removidos > 0 && $productos_ajustables == 0) {
        return "Algunos productos ya no están disponibles y fueron removidos automáticamente de tu carrito. Acción sugerida: Revisa los detalles a continuación y continúa con los productos disponibles o agrega otros productos a tu carrito.";
    } elseif ($productos_ajustables > 0 && $productos_removidos == 0) {
        return "El stock disponible ha cambiado. Acción sugerida: Revisa los detalles a continuación, ajusta las cantidades según el stock disponible, o continúa con los productos disponibles.";
    } else {
        return "Hay problemas con algunos productos en tu carrito. Acción sugerida: Revisa los detalles a continuación. Algunos productos fueron removidos automáticamente y otros puedes ajustar según el stock disponible.";
    }
}

/**
 * Envía respuesta AJAX de error
 * 
 * @param string $mensaje Mensaje de error
 * @param array $datos_adicionales Datos adicionales para la respuesta
 * @return void
 */
function enviarRespuestaAjaxError($mensaje, $datos_adicionales = []) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    $respuesta = array_merge([
        'success' => false,
        'mensaje' => $mensaje
    ], $datos_adicionales);
    echo json_encode($respuesta, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Envía respuesta AJAX de éxito
 * 
 * @param string $mensaje Mensaje de éxito
 * @param array $datos_adicionales Datos adicionales para la respuesta
 * @return void
 */
function enviarRespuestaAjaxExito($mensaje, $datos_adicionales = []) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    $respuesta = array_merge([
        'success' => true,
        'mensaje' => $mensaje
    ], $datos_adicionales);
    echo json_encode($respuesta, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Redirige con mensaje de sesión
 * Función centralizada en security_functions.php
 *
 * @param string $url URL de destino
 * @param string $mensaje Mensaje a guardar en sesión
 * @param string $tipo Tipo de mensaje (success, error, warning, info)
 * @return void
 *
 * NOTA: Esta función se define en security_functions.php para evitar duplicación.
 * Verificar que security_functions.php esté incluido antes de usar.
 */
// Función removida - usar la versión centralizada de security_functions.php

/**
 * Calcula el total del carrito actualizado para respuestas AJAX
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $carrito Carrito de sesión
 * @return array Array con subtotal, info_envio, monto_faltante y total_estimado
 */
function calcularTotalCarritoActualizado($mysqli, $carrito) {
    require_once __DIR__ . '/envio_functions.php';
    require_once __DIR__ . '/queries/producto_queries.php';
    
    $total_carrito = 0;
    foreach ($carrito as $item) {
        if (isset($item['id_producto'])) {
            $prod = obtenerProductoConVariante($mysqli, $item['id_producto'], $item['talla'], $item['color']);
            if ($prod) {
                $total_carrito += $prod['precio_actual'] * $item['cantidad'];
            }
        }
    }
    
    $info_envio = obtenerInfoEnvioCarrito($total_carrito);
    $monto_faltante = obtenerMontoFaltanteEnvioGratis($total_carrito);
    
    return [
        'subtotal' => $total_carrito,
        'info_envio' => $info_envio,
        'monto_faltante' => $monto_faltante,
        'total_estimado' => $total_carrito + $info_envio['costo_caba_gba']
    ];
}

/**
 * Verifica si la petición es AJAX
 * 
 * @return bool True si es AJAX, false en caso contrario
 */
function esPeticionAjax() {
    return isset($_POST['ajax']) || 
           (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
}


