<?php
/**
 * ========================================================================
 * FUNCIONES AUXILIARES DE VENTAS - Tienda Seda y Lino
 * ========================================================================
 * Funciones auxiliares para el panel de ventas
 * 
 * FUNCIONES:
 * - procesarActualizacionPedidoPago(): Procesa actualización completa de pedido y pago
 * - procesarAprobacionPago(): Procesa aprobación de pago
 * - procesarRechazoPago(): Procesa rechazo de pago
 * - procesarAgregarMetodoPago(): Procesa agregación de método de pago
 * - procesarActualizarMetodoPago(): Procesa actualización de método de pago
 * - procesarEliminarMetodoPago(): Procesa eliminación de método de pago
 * - procesarToggleActivoMetodoPago(): Procesa cambio de estado activo/inactivo de método de pago
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */


/**
 * Procesa errores de actualización de pedido y pago, retornando mensajes amigables
 * 
 * @deprecated Usar procesarErrorStock() de includes/error_handlers.php en su lugar
 * @param string $error_message Mensaje de error original
 * @param int $pedido_id ID del pedido
 * @return array Array con ['mensaje' => string, 'mensaje_tipo' => string]
 */
function _procesarErroresActualizacionPedidoPago($error_message, $pedido_id) {
    require_once __DIR__ . '/error_handlers.php';
    return procesarErrorStock($error_message, ['id_pedido' => $pedido_id]);
}

/**
 * Procesa la actualización completa de pedido y pago
 * 
 * Esta función valida los datos, actualiza el pedido y pago, maneja cambios de stock
 * según los estados, y gestiona transacciones para garantizar integridad.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @param int $id_usuario ID del usuario ventas que realiza la acción
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarActualizacionPedidoPago($mysqli, $post, $id_usuario) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['actualizar_estado_pedido'])) {
        return false;
    }
    
    // Cargar funciones necesarias
    require_once __DIR__ . '/queries_helper.php';
    require_once __DIR__ . '/estado_helpers.php';
    try {
        cargarArchivoQueries('pedido_queries', __DIR__ . '/queries');
        cargarArchivoQueries('pago_queries', __DIR__ . '/queries');
    } catch (Exception $e) {
        error_log("ERROR: No se pudo cargar archivos de queries - " . $e->getMessage());
        die("Error crítico: Archivo de consultas no encontrado. Por favor, contacta al administrador.");
    }
    
    // Extraer y normalizar datos del formulario
    $pedido_id = intval($post['pedido_id'] ?? 0);
    $nuevo_estado = normalizarEstado($post['nuevo_estado'] ?? '');
    
    // IMPORTANTE: No normalizar nuevo_estado_pago si está vacío (opción "-- Mantener estado actual --")
    // Solo normalizar si tiene un valor real
    $nuevo_estado_pago_raw = trim($post['nuevo_estado_pago'] ?? '');
    $nuevo_estado_pago = !empty($nuevo_estado_pago_raw) ? normalizarEstado($nuevo_estado_pago_raw) : '';
    
    $estado_anterior = normalizarEstado($post['estado_anterior'] ?? '');
    $estado_pago_anterior = normalizarEstado($post['estado_pago_anterior'] ?? '');
    
    // Logging para debugging
    error_log("procesarActualizacionPedidoPago: POST recibido - pedido_id: {$pedido_id}, nuevo_estado: {$nuevo_estado}, nuevo_estado_pago: '{$nuevo_estado_pago}' (raw: '{$nuevo_estado_pago_raw}')");
    error_log("procesarActualizacionPedidoPago: POST completo - " . json_encode(array_keys($post)));
    
    // Campos adicionales del pago
    // Nota: monto_pago y numero_transaccion son solo lectura (llenados por el cliente)
    // Solo se procesa el motivo de rechazo si se rechaza el pago
    $motivo_rechazo = trim($post['motivo_rechazo'] ?? '');
    
    // Validar estados permitidos (según ENUM de la base de datos)
    // NOTA: Todo pedido en 'pendiente' ya tiene stock validado (se valida antes de crear el pedido)
    $estados_pedido_validos = ['pendiente', 'preparacion', 'en_viaje', 'completado', 'devolucion', 'cancelado'];
    $estados_pago_validos = ['pendiente', 'pendiente_aprobacion', 'aprobado', 'rechazado', 'cancelado'];
    
    // Validar que el estado del pedido sea válido
    if ($pedido_id <= 0) {
        return ['mensaje' => 'ID de pedido inválido', 'mensaje_tipo' => 'danger'];
    }
    
    if (empty($nuevo_estado) || !in_array($nuevo_estado, $estados_pedido_validos)) {
        return ['mensaje' => 'Estado de pedido inválido. Estado recibido: ' . htmlspecialchars($nuevo_estado), 'mensaje_tipo' => 'danger'];
    }
    
    // Obtener información del pago
    $pago_actual = obtenerPagoPorPedido($mysqli, $pedido_id);
    $estado_pago_anterior_real = $pago_actual ? normalizarEstado($pago_actual['estado_pago']) : '';
    
    // Logging para debugging
    error_log("procesarActualizacionPedidoPago: Estado pago anterior real: '{$estado_pago_anterior_real}', nuevo_estado_pago recibido: '{$nuevo_estado_pago}'");
    
    // Determinar si se está cambiando el estado del pago
    $cambiar_estado_pago = false;
    $estado_pago_final = '';
    if ($pago_actual && !empty($nuevo_estado_pago) && in_array($nuevo_estado_pago, $estados_pago_validos)) {
        if ($nuevo_estado_pago !== $estado_pago_anterior_real) {
            $cambiar_estado_pago = true;
            $estado_pago_final = $nuevo_estado_pago;
            error_log("procesarActualizacionPedidoPago: Se detectó cambio de estado de pago: '{$estado_pago_anterior_real}' -> '{$estado_pago_final}'");
            error_log("procesarActualizacionPedidoPago: Transición de pago detectada - anterior: '{$estado_pago_anterior_real}', nuevo: '{$estado_pago_final}', validación: " . (in_array($nuevo_estado_pago, $estados_pago_validos) ? 'válido' : 'inválido'));
        } else {
            error_log("procesarActualizacionPedidoPago: No hay cambio de estado de pago (mismo estado: '{$estado_pago_anterior_real}')");
        }
    } else {
        $razon = [];
        if (!$pago_actual) $razon[] = 'no existe pago';
        if (empty($nuevo_estado_pago)) $razon[] = 'nuevo_estado_pago vacío';
        if (!in_array($nuevo_estado_pago, $estados_pago_validos)) $razon[] = 'estado inválido';
        error_log("procesarActualizacionPedidoPago: No se puede cambiar estado de pago - " . implode(', ', $razon) . " (pago_actual: " . ($pago_actual ? 'existe' : 'no existe') . ", nuevo_estado_pago: '{$nuevo_estado_pago}', válido: " . (in_array($nuevo_estado_pago, $estados_pago_validos) ? 'sí' : 'no') . ")");
    }
    
    // Preparar motivo de rechazo si se está rechazando el pago
    $motivo_rechazo_final = null;
    if ($cambiar_estado_pago && $estado_pago_final === 'rechazado' && !empty($motivo_rechazo)) {
        $motivo_rechazo_final = $motivo_rechazo;
    }
    
    // Validar transiciones de estado antes de iniciar transacción
    $pedido_actual = obtenerPedidoPorId($mysqli, $pedido_id);
    if (!$pedido_actual) {
        return ['mensaje' => 'Pedido no encontrado', 'mensaje_tipo' => 'danger'];
    }
    
    $estado_pedido_actual = normalizarEstado($pedido_actual['estado_pedido'] ?? '');
    
    // Validar que no se intente cambiar un pedido cancelado a otro estado (excepto mantener cancelado)
    if ($estado_pedido_actual === 'cancelado' && $nuevo_estado !== 'cancelado') {
        return ['mensaje' => 'No se puede cambiar el estado de un pedido cancelado', 'mensaje_tipo' => 'danger'];
    }
    
    // Logging de transición de estado
    error_log("procesarActualizacionPedidoPago: Pedido #{$pedido_id} - Estado actual: {$estado_pedido_actual} -> Nuevo estado: {$nuevo_estado}");
    if ($cambiar_estado_pago) {
        error_log("procesarActualizacionPedidoPago: Pago #{$pago_actual['id_pago']} - Estado actual: {$estado_pago_anterior_real} -> Nuevo estado: {$estado_pago_final}");
    }
    
    // ESTRATEGIA: Si se está cambiando el estado del pago, actualizar el pago primero
    // usando actualizarEstadoPagoConPedido() que maneja automáticamente:
    // - Actualización del estado del pago
    // - Cambios en el estado del pedido (si aplica)
    // - Gestión de stock (descuento al aprobar, reversión al rechazar/cancelar)
    // NOTA: actualizarEstadoPagoConPedido() maneja su propia transacción, no iniciar una aquí
    if ($cambiar_estado_pago && $pago_actual) {
        try {
            // Validar transición de pago antes de ejecutar
            validarTransicionPago($estado_pago_anterior_real, $estado_pago_final);
            
            $resultado_actualizacion = actualizarEstadoPagoConPedido($mysqli, $pago_actual['id_pago'], $estado_pago_final, $motivo_rechazo_final, $id_usuario);
            if (!$resultado_actualizacion) {
                throw new Exception('Error al actualizar el estado del pago: la función retornó false');
            }
        } catch (Exception $e) {
            error_log("procesarActualizacionPedidoPago: Excepción en actualizarEstadoPagoConPedido - " . $e->getMessage());
            // Procesar error y retornar mensaje amigable
            return _procesarErroresActualizacionPedidoPago($e->getMessage(), $pedido_id);
        }
        
        // Obtener el estado actual del pedido después de actualizar el pago
        // porque actualizarEstadoPagoConPedido() puede haber cambiado el estado del pedido
        $pedido_actualizado = obtenerPedidoPorId($mysqli, $pedido_id);
        $estado_pedido_despues_pago = $pedido_actualizado ? normalizarEstado($pedido_actualizado['estado_pedido']) : '';
        
        // Si el estado del pedido cambió por la actualización del pago y es diferente al deseado,
        // actualizar el estado del pedido solo si es necesario
        // Nota: Si el pago se rechazó/canceló, el pedido ya fue cancelado por actualizarEstadoPagoConPedido()
        if ($estado_pedido_despues_pago !== $nuevo_estado) {
            // Solo actualizar si el nuevo estado es válido para el estado actual
            // Por ejemplo, no se puede cambiar de cancelado a otro estado
            if ($estado_pedido_despues_pago !== 'cancelado' || $nuevo_estado === 'cancelado') {
                try {
                    // Validar transición de pedido antes de ejecutar
                    validarTransicionPedido($estado_pedido_despues_pago, $nuevo_estado);
                    
                    if (!actualizarEstadoPedidoConValidaciones($mysqli, $pedido_id, $nuevo_estado, $id_usuario)) {
                        throw new Exception('Error al actualizar el estado del pedido');
                    }
                } catch (Exception $e) {
                    error_log("procesarActualizacionPedidoPago: Error al actualizar estado del pedido después de cambiar pago - " . $e->getMessage());
                    return _procesarErroresActualizacionPedidoPago($e->getMessage(), $pedido_id);
                }
            }
        }
            
            // Logging de éxito
            error_log("procesarActualizacionPedidoPago: Pedido #{$pedido_id} actualizado exitosamente - Estado: {$estado_pedido_actual} → {$nuevo_estado}");
            error_log("procesarActualizacionPedidoPago: Pago #{$pago_actual['id_pago']} actualizado exitosamente - Estado: {$estado_pago_anterior_real} → {$estado_pago_final}");
            
            // Preparar mensaje de éxito
            require_once __DIR__ . '/estado_helpers.php';
            $info_adicional = [];
            if ($estado_pago_final === 'aprobado' && $estado_pago_anterior_real !== 'aprobado') {
                $info_adicional['stock_descontado'] = true;
                $info_adicional['pago_aprobado'] = true;
            }
            if (in_array($estado_pago_final, ['rechazado', 'cancelado']) && $estado_pago_anterior_real === 'aprobado') {
                $info_adicional['stock_restaurado'] = true;
            }
            if ($estado_pago_final === 'rechazado') {
                $info_adicional['pago_rechazado'] = true;
            }
            
            // Si se cambió el estado del pago, formatear mensaje de pago primero
            $mensaje_exito = '';
            if ($cambiar_estado_pago && $estado_pago_anterior_real !== $estado_pago_final) {
                // Formatear mensaje de cambio de estado de pago
                $mensaje_pago = formatearMensajeExito($estado_pago_anterior_real, $estado_pago_final, 'pago', $info_adicional);
                
                // Si también cambió el estado del pedido, combinar ambos mensajes
                if ($estado_pedido_actual !== $nuevo_estado) {
                    $mensaje_pedido = formatearMensajeExito($estado_pedido_actual, $nuevo_estado, 'pedido', []);
                    $mensaje_exito = $mensaje_pago . ' ' . $mensaje_pedido;
                } else {
                    $mensaje_exito = $mensaje_pago;
                }
            } else {
                // Solo cambió el estado del pedido
                $mensaje_exito = formatearMensajeExito($estado_pedido_actual, $nuevo_estado, 'pedido', $info_adicional);
            }
            
            return ['mensaje' => $mensaje_exito, 'mensaje_tipo' => 'success'];
        }
        
        // Si no se está cambiando el estado del pago, solo actualizar el estado del pedido
        // actualizarEstadoPedidoConValidaciones() maneja automáticamente la reversión de stock
        // cuando se cancela el pedido
        // NOTA: actualizarEstadoPedidoConValidaciones() maneja su propia transacción
        error_log("procesarActualizacionPedidoPago: Actualizando solo estado del pedido #{$pedido_id} a {$nuevo_estado}");
        
        try {
            // Validar transición de pedido antes de ejecutar
            validarTransicionPedido($estado_pedido_actual, $nuevo_estado);
            
            if (!actualizarEstadoPedidoConValidaciones($mysqli, $pedido_id, $nuevo_estado, $id_usuario)) {
                throw new Exception('Error al actualizar el pedido');
            }
        } catch (Exception $e) {
            error_log("procesarActualizacionPedidoPago: Error al actualizar estado del pedido - " . $e->getMessage());
            return _procesarErroresActualizacionPedidoPago($e->getMessage(), $pedido_id);
        }
        
        // Si se canceló el pedido y existe un pago, cancelar el pago también si no está cancelado
        // IMPORTANTE: Usar actualizarEstadoPago() en lugar de actualizarEstadoPagoConPedido()
        // porque el pedido ya está cancelado y no queremos que se intente cancelar nuevamente
        // Validar que el pago puede cancelarse según recorrido activo
        if ($nuevo_estado === 'cancelado' && $pago_actual && $estado_pago_anterior_real !== 'cancelado') {
            // Solo cancelar si el estado anterior permite cancelación (no está en recorrido activo)
            if (puedeCancelarPago($estado_pago_anterior_real)) {
                try {
                    // Validar transición de pago antes de ejecutar
                    validarTransicionPago($estado_pago_anterior_real, 'cancelado');
                    
                    error_log("procesarActualizacionPedidoPago: Cancelando pago #{$pago_actual['id_pago']} porque el pedido fue cancelado");
                    // El stock ya fue restaurado por actualizarEstadoPedidoConValidaciones() cuando canceló el pedido
                    // Solo necesitamos actualizar el estado del pago sin afectar el pedido
                    // NOTA: actualizarEstadoPago() maneja su propia transacción
                    if (!actualizarEstadoPago($mysqli, $pago_actual['id_pago'], 'cancelado', null, null)) {
                        throw new Exception('Error al cancelar el pago');
                    }
                    error_log("procesarActualizacionPedidoPago: Pago #{$pago_actual['id_pago']} cancelado exitosamente");
                } catch (Exception $e) {
                    error_log("procesarActualizacionPedidoPago: Error al cancelar pago - " . $e->getMessage());
                    // No fallar la operación completa si solo falla cancelar el pago
                    // El pedido ya fue cancelado exitosamente
                }
            } else {
                error_log("procesarActualizacionPedidoPago: No se puede cancelar pago #{$pago_actual['id_pago']} porque está en recorrido activo (estado: {$estado_pago_anterior_real})");
            }
        }
        
        // Logging de éxito
        error_log("procesarActualizacionPedidoPago: Pedido #{$pedido_id} actualizado exitosamente - Estado: {$estado_pedido_actual} → {$nuevo_estado}");
        
        // Cargar función helper para formatear mensajes de éxito
        require_once __DIR__ . '/estado_helpers.php';
        
        // Preparar información adicional para el mensaje
        $info_adicional = [];
        
        // Determinar si se restauró stock (pedido cancelado)
        if ($nuevo_estado === 'cancelado') {
            $info_adicional['stock_restaurado'] = true;
        }
        
        // Formatear mensaje de éxito con transición de estados
        $mensaje_exito = formatearMensajeExito($estado_pedido_actual, $nuevo_estado, 'pedido', $info_adicional);
        
        return ['mensaje' => $mensaje_exito, 'mensaje_tipo' => 'success'];
}

/**
 * Procesa la aprobación de un pago
 * 
 * Esta función aprueba un pago y descuenta el stock automáticamente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @param int $id_usuario ID del usuario ventas que realiza la acción
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarAprobacionPago($mysqli, $post, $id_usuario) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['aprobar_pago'])) {
        return false;
    }
    
    // Cargar funciones necesarias
    require_once __DIR__ . '/queries_helper.php';
    try {
        cargarArchivoQueries('pago_queries', __DIR__ . '/queries');
    } catch (Exception $e) {
        error_log("ERROR: No se pudo cargar pago_queries.php - " . $e->getMessage());
        die("Error crítico: Archivo de consultas de pago no encontrado. Por favor, contacta al administrador.");
    }
    
    $pago_id = intval($post['pago_id'] ?? 0);
    
    if ($pago_id <= 0) {
        return ['mensaje' => 'ID de pago inválido', 'mensaje_tipo' => 'danger'];
    }
    
    try {
        if (aprobarPago($mysqli, $pago_id, $id_usuario)) {
            return ['mensaje' => 'Pago aprobado correctamente. Stock descontado automáticamente.', 'mensaje_tipo' => 'success'];
        } else {
            return ['mensaje' => 'Error al aprobar el pago. Verifique el stock disponible.', 'mensaje_tipo' => 'danger'];
        }
    } catch (Exception $e) {
        // Verificar si el error es por stock insuficiente
        if (strpos($e->getMessage(), 'STOCK_INSUFICIENTE') !== false) {
            // Extraer información de variantes si está disponible
            $mensaje_error = $e->getMessage();
            if (preg_match('/Variante #(\d+): Tiene (\d+) unidades disponibles pero se necesitan (\d+) unidades/', $mensaje_error, $matches)) {
                $id_variante = $matches[1];
                $stock_disponible = $matches[2];
                $intento_venta = $matches[3];
                return ['mensaje' => "No hay suficiente stock para aprobar este pago. La variante #{$id_variante} tiene {$stock_disponible} unidades disponibles pero se necesitan {$intento_venta} unidades. Sugerencia: Revisa el stock disponible en el panel de productos o contacta al cliente para ajustar la cantidad.", 'mensaje_tipo' => 'warning'];
            } else {
                return ['mensaje' => 'No hay suficiente stock disponible para aprobar este pago. Sugerencia: Verifica el stock de los productos del pedido en el panel de productos y ajusta las cantidades si es necesario, o contacta al cliente para informarle sobre la disponibilidad.', 'mensaje_tipo' => 'warning'];
            }
        } else {
            return ['mensaje' => 'Error al aprobar el pago: ' . $e->getMessage(), 'mensaje_tipo' => 'danger'];
        }
    }
}

/**
 * Procesa el rechazo de un pago
 * 
 * Esta función rechaza un pago y restaura el stock si había sido descontado.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @param int $id_usuario ID del usuario ventas que realiza la acción
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarRechazoPago($mysqli, $post, $id_usuario) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['rechazar_pago'])) {
        return false;
    }
    
    // Cargar funciones necesarias
    require_once __DIR__ . '/queries_helper.php';
    try {
        cargarArchivoQueries('pago_queries', __DIR__ . '/queries');
    } catch (Exception $e) {
        error_log("ERROR: No se pudo cargar pago_queries.php - " . $e->getMessage());
        die("Error crítico: Archivo de consultas de pago no encontrado. Por favor, contacta al administrador.");
    }
    
    $pago_id = intval($post['pago_id'] ?? 0);
    $motivo_rechazo = trim($post['motivo_rechazo'] ?? '');
    
    if ($pago_id <= 0) {
        return ['mensaje' => 'ID de pago inválido', 'mensaje_tipo' => 'danger'];
    }
    
    if (rechazarPago($mysqli, $pago_id, $id_usuario, $motivo_rechazo)) {
        return ['mensaje' => 'Pago rechazado correctamente. Stock restaurado automáticamente si había sido descontado.', 'mensaje_tipo' => 'success'];
    } else {
        return ['mensaje' => 'Error al rechazar el pago', 'mensaje_tipo' => 'danger'];
    }
}

/**
 * Valida el nombre de un método de pago según los requisitos de la base de datos
 * 
 * Requisitos según database_estructura.sql y diccionario_datos_tiendasedaylino.md:
 * - Longitud: 3-100 caracteres
 * - Caracteres permitidos: A-Z, a-z, 0-9, espacios, guiones (-)
 * 
 * @param string $nombre Nombre a validar
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] si hay error, o false si es válido
 */
function validarNombreMetodoPago($nombre) {
    // Validar que no esté vacío
    if (empty($nombre)) {
        return ['mensaje' => 'El nombre del método de pago es obligatorio', 'mensaje_tipo' => 'danger'];
    }
    
    // Validar longitud mínima (3 caracteres)
    if (mb_strlen($nombre) < 3) {
        return ['mensaje' => 'El nombre debe tener al menos 3 caracteres', 'mensaje_tipo' => 'danger'];
    }
    
    // Validar longitud máxima (100 caracteres)
    if (mb_strlen($nombre) > 100) {
        return ['mensaje' => 'El nombre no puede exceder 100 caracteres', 'mensaje_tipo' => 'danger'];
    }
    
    // Validar caracteres permitidos: letras (A-Z, a-z), números (0-9), espacios, guiones (-)
    // Patrón: solo letras, números, espacios y guiones
    if (!preg_match('/^[A-Za-z0-9\s\-]+$/', $nombre)) {
        return ['mensaje' => 'El nombre solo puede contener letras, números, espacios y guiones', 'mensaje_tipo' => 'danger'];
    }
    
    // Rechazar strings que sean solo guiones o caracteres especiales
    // Verificar que tenga al menos una letra o número
    if (!preg_match('/[A-Za-z0-9]/', $nombre)) {
        return ['mensaje' => 'El nombre debe contener al menos una letra o número', 'mensaje_tipo' => 'danger'];
    }
    
    return false; // Válido
}

/**
 * Valida la descripción de un método de pago según los requisitos de la base de datos
 * 
 * Requisitos según database_estructura.sql y diccionario_datos_tiendasedaylino.md:
 * - Longitud: 0-255 caracteres (opcional)
 * - Caracteres permitidos: A-Z, a-z (con tildes y diéresis: á, é, í, ó, ú, ñ, ü), 0-9, espacios, puntos (.), guiones (-), comas (,), dos puntos (:), comillas simples (')
 * 
 * @param string $descripcion Descripción a validar
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] si hay error, o false si es válido
 */
function validarDescripcionMetodoPago($descripcion) {
    // Si está vacía, es válida (es opcional)
    if (empty($descripcion)) {
        return false; // Válido (opcional)
    }
    
    // Validar longitud máxima (255 caracteres)
    if (mb_strlen($descripcion) > 255) {
        return ['mensaje' => 'La descripción no puede exceder 255 caracteres', 'mensaje_tipo' => 'danger'];
    }
    
    // Validar caracteres permitidos: letras (A-Z, a-z con tildes y diéresis), números (0-9), espacios, puntos (.), guiones (-), comas (,), dos puntos (:), comillas simples (')
    if (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9\s\.,\:\-\']+$/u', $descripcion)) {
        return ['mensaje' => 'La descripción solo puede contener letras (incluyendo tildes y diéresis), números, espacios, puntos, comas, dos puntos, guiones y comillas simples', 'mensaje_tipo' => 'danger'];
    }
    
    return false; // Válido
}

/**
 * Procesa la agregación de un nuevo método de pago
 * 
 * Esta función valida los datos y crea un nuevo método de pago.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarAgregarMetodoPago($mysqli, $post) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['agregar_metodo_pago'])) {
        return false;
    }
    
    // Cargar funciones necesarias
    require_once __DIR__ . '/queries_helper.php';
    try {
        cargarArchivoQueries('forma_pago_queries', __DIR__ . '/queries');
    } catch (Exception $e) {
        error_log("ERROR: No se pudo cargar forma_pago_queries.php - " . $e->getMessage());
        die("Error crítico: Archivo de consultas de forma de pago no encontrado. Por favor, contacta al administrador.");
    }
    
    $nombre = trim($post['nombre_metodo'] ?? '');
    $descripcion = trim($post['descripcion_metodo'] ?? '');
    
    // Validar nombre
    $error_nombre = validarNombreMetodoPago($nombre);
    if ($error_nombre !== false) {
        return $error_nombre;
    }
    
    // Validar descripción
    $error_descripcion = validarDescripcionMetodoPago($descripcion);
    if ($error_descripcion !== false) {
        return $error_descripcion;
    }
    
    // Convertir descripción vacía a null
    $descripcion = empty($descripcion) ? null : $descripcion;
    
    // Usar función centralizada para crear forma de pago
    $id_forma_pago = crearFormaPago($mysqli, $nombre, $descripcion);
    
    if ($id_forma_pago) {
        return ['mensaje' => 'Método de pago agregado correctamente', 'mensaje_tipo' => 'success'];
    } else {
        return ['mensaje' => 'Error al agregar el método de pago', 'mensaje_tipo' => 'danger'];
    }
}

/**
 * Procesa la actualización de un método de pago
 * 
 * Esta función valida los datos y actualiza un método de pago existente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarActualizarMetodoPago($mysqli, $post) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['actualizar_metodo_pago'])) {
        return false;
    }
    
    // Cargar funciones necesarias
    require_once __DIR__ . '/queries_helper.php';
    try {
        cargarArchivoQueries('forma_pago_queries', __DIR__ . '/queries');
    } catch (Exception $e) {
        error_log("ERROR: No se pudo cargar forma_pago_queries.php - " . $e->getMessage());
        die("Error crítico: Archivo de consultas de forma de pago no encontrado. Por favor, contacta al administrador.");
    }
    
    $id_forma_pago = intval($post['id_forma_pago'] ?? 0);
    $nombre = trim($post['nombre_metodo'] ?? '');
    $descripcion = trim($post['descripcion_metodo'] ?? '');
    
    // Validar ID
    if ($id_forma_pago <= 0) {
        return ['mensaje' => 'ID de método de pago inválido', 'mensaje_tipo' => 'danger'];
    }
    
    // Validar nombre
    $error_nombre = validarNombreMetodoPago($nombre);
    if ($error_nombre !== false) {
        return $error_nombre;
    }
    
    // Validar descripción
    $error_descripcion = validarDescripcionMetodoPago($descripcion);
    if ($error_descripcion !== false) {
        return $error_descripcion;
    }
    
    // Convertir descripción vacía a null
    $descripcion = empty($descripcion) ? null : $descripcion;
    
    // Usar función centralizada para actualizar forma de pago
    if (actualizarFormaPago($mysqli, $id_forma_pago, $nombre, $descripcion)) {
        return ['mensaje' => 'Método de pago actualizado correctamente', 'mensaje_tipo' => 'success'];
    } else {
        return ['mensaje' => 'Error al actualizar el método de pago', 'mensaje_tipo' => 'danger'];
    }
}

/**
 * Procesa la eliminación física de un método de pago
 * 
 * Esta función valida que el método no esté en uso y lo elimina físicamente
 * de la base de datos. Solo se elimina si no tiene pagos asociados.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarEliminarMetodoPago($mysqli, $post) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['eliminar_metodo_pago'])) {
        return false;
    }
    
    // Cargar funciones necesarias
    require_once __DIR__ . '/queries_helper.php';
    try {
        cargarArchivoQueries('forma_pago_queries', __DIR__ . '/queries');
    } catch (Exception $e) {
        error_log("ERROR: No se pudo cargar forma_pago_queries.php - " . $e->getMessage());
        die("Error crítico: Archivo de consultas de forma de pago no encontrado. Por favor, contacta al administrador.");
    }
    
    $id_forma_pago = intval($post['id_forma_pago'] ?? 0);
    
    if ($id_forma_pago <= 0) {
        return ['mensaje' => 'ID de método de pago inválido', 'mensaje_tipo' => 'danger'];
    }
    
    // Verificar si el método de pago está en uso usando función centralizada
    $total_usos = contarUsosFormaPago($mysqli, $id_forma_pago);
    
    if ($total_usos > 0) {
        return ['mensaje' => 'No se puede eliminar el método de pago porque está siendo utilizado en ' . $total_usos . ' pago(s)', 'mensaje_tipo' => 'danger'];
    }
    
    // Eliminación física usando función centralizada
    if (eliminarFormaPago($mysqli, $id_forma_pago)) {
        return ['mensaje' => 'Método de pago eliminado correctamente', 'mensaje_tipo' => 'success'];
    } else {
        return ['mensaje' => 'Error al eliminar el método de pago', 'mensaje_tipo' => 'danger'];
    }
}

/**
 * Procesa el cambio de estado activo/inactivo de un método de pago
 * 
 * Esta función alterna el estado activo de un método de pago, permitiendo
 * activarlo o desactivarlo sin eliminarlo de la base de datos.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarToggleActivoMetodoPago($mysqli, $post) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['toggle_activo_metodo_pago'])) {
        return false;
    }
    
    // Cargar funciones necesarias
    require_once __DIR__ . '/queries_helper.php';
    try {
        cargarArchivoQueries('forma_pago_queries', __DIR__ . '/queries');
    } catch (Exception $e) {
        error_log("ERROR: No se pudo cargar forma_pago_queries.php - " . $e->getMessage());
        die("Error crítico: Archivo de consultas de forma de pago no encontrado. Por favor, contacta al administrador.");
    }
    
    $id_forma_pago = intval($post['id_forma_pago'] ?? 0);
    
    if ($id_forma_pago <= 0) {
        return ['mensaje' => 'ID de método de pago inválido', 'mensaje_tipo' => 'danger'];
    }
    
    // Alternar estado usando función centralizada
    $nuevo_estado = toggleActivoFormaPago($mysqli, $id_forma_pago);
    
    if ($nuevo_estado !== false) {
        $estado_texto = $nuevo_estado === 1 ? 'activado' : 'desactivado';
        return ['mensaje' => 'Método de pago ' . $estado_texto . ' correctamente', 'mensaje_tipo' => 'success'];
    } else {
        return ['mensaje' => 'Error al cambiar el estado del método de pago', 'mensaje_tipo' => 'danger'];
    }
}

/**
 * Construye una URL de redirección con parámetros de consulta preservados
 * 
 * Esta función construye una URL de redirección preservando los parámetros
 * de consulta relevantes como 'mostrar_inactivos' y 'limite' de la petición actual.
 * 
 * @param string $base_url URL base para la redirección (ej: 'ventas.php')
 * @param array|null $params_adicionales Parámetros adicionales opcionales a incluir
 * @return string URL completa con parámetros de consulta
 */
function construirRedirectUrl($base_url, $params_adicionales = null) {
    $params = [];
    
    // Preservar parámetro mostrar_inactivos si está presente
    if (isset($_GET['mostrar_inactivos']) && $_GET['mostrar_inactivos'] == '1') {
        $params[] = 'mostrar_inactivos=1';
    }
    
    // Preservar parámetro limite si está presente y es válido
    if (isset($_GET['limite']) && in_array($_GET['limite'], ['10', '50', 'TODOS'])) {
        $params[] = 'limite=' . urlencode($_GET['limite']);
    }
    
    // Agregar parámetros adicionales si se proporcionan
    if (is_array($params_adicionales) && !empty($params_adicionales)) {
        foreach ($params_adicionales as $key => $value) {
            $params[] = urlencode($key) . '=' . urlencode($value);
        }
    }
    
    // Construir URL final
    $redirect_url = $base_url;
    if (!empty($params)) {
        $redirect_url .= '?' . implode('&', $params);
    }
    
    return $redirect_url;
}


