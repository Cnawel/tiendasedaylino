<?php
/**
 * ========================================================================
 * CONSULTAS SQL DE PAGOS - Tienda Seda y Lino
 * ========================================================================
 * Archivo centralizado con todas las consultas relacionadas a pagos
 * 
 * REEMPLAZO DE TRIGGERS:
 * Este archivo implementa la lógica PHP que reemplaza los siguientes triggers de MySQL:
 * - trg_validar_pago_unico_aprobado: crearPago()
 * - trg_validar_pago_unico_aprobado_update: actualizarEstadoPago() y actualizarPagoCompleto()
 * - trg_actualizar_pedido_por_pago: actualizarEstadoPago() y actualizarPagoCompleto()
 * 
 * Uso:
 *   require_once __DIR__ . '/includes/queries/pago_queries.php';
 *   $pago = obtenerPagoPorPedido($mysqli, $id_pedido);
 * ========================================================================
 */

/**
 * Función auxiliar: Obtiene un pago con información completa de forma de pago
 * 
 * Esta función centraliza la lógica común para obtener pagos, evitando duplicación
 * entre obtenerPagoPorPedido() y obtenerPagoPorId().
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $campo Campo por el cual buscar ('id_pedido' o 'id_pago')
 * @param int $valor Valor del campo a buscar
 * @return array|null Array con datos del pago o null si no existe
 */
function _obtenerPagoComun($mysqli, $campo, $valor) {
    // Validar que el campo sea válido
    if (!in_array($campo, ['id_pedido', 'id_pago'])) {
        return null;
    }
    
    $sql = "
        SELECT 
            p.id_pago,
            p.id_pedido,
            p.id_forma_pago,
            p.numero_transaccion,
            p.estado_pago,
            p.monto,
            p.fecha_pago,
            p.fecha_aprobacion,
            p.motivo_rechazo,
            p.fecha_actualizacion,
            fp.nombre as forma_pago_nombre,
            fp.descripcion as forma_pago_descripcion
        FROM Pagos p
        INNER JOIN Forma_Pagos fp ON p.id_forma_pago = fp.id_forma_pago
        WHERE p.{$campo} = ?
        LIMIT 1
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $valor);
    $stmt->execute();
    $result = $stmt->get_result();
    $pago = $result->fetch_assoc();
    $stmt->close();
    
    return $pago;
}

/**
 * Obtiene el pago asociado a un pedido
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @return array|null Array con datos del pago o null si no existe
 */
function obtenerPagoPorPedido($mysqli, $id_pedido) {
    return _obtenerPagoComun($mysqli, 'id_pedido', $id_pedido);
}

/**
 * Obtiene un pago por su ID
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago
 * @return array|null Array con datos del pago o null si no existe
 */
function obtenerPagoPorId($mysqli, $id_pago) {
    return _obtenerPagoComun($mysqli, 'id_pago', $id_pago);
}

/**
 * Obtiene todos los pagos para múltiples pedidos en una sola query
 * Optimización para evitar N+1 query problem
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $pedidos_ids Array de IDs de pedidos
 * @return array Array asociativo [id_pedido => array con datos del pago]
 */
function obtenerPagosPorPedidos($mysqli, $pedidos_ids) {
    if (empty($pedidos_ids)) {
        return [];
    }
    
    // Validar que todos los IDs sean enteros
    $pedidos_ids = array_map('intval', $pedidos_ids);
    $pedidos_ids = array_filter($pedidos_ids, function($id) { return $id > 0; });
    
    if (empty($pedidos_ids)) {
        return [];
    }
    
    // Construir placeholders para la query
    $placeholders = str_repeat('?,', count($pedidos_ids) - 1) . '?';
    
    $sql = "
        SELECT 
            p.id_pago,
            p.id_pedido,
            p.id_forma_pago,
            p.numero_transaccion,
            p.estado_pago,
            p.monto,
            p.fecha_pago,
            p.fecha_aprobacion,
            p.motivo_rechazo,
            p.fecha_actualizacion,
            fp.nombre as forma_pago_nombre,
            fp.descripcion as forma_pago_descripcion
        FROM Pagos p
        INNER JOIN Forma_Pagos fp ON p.id_forma_pago = fp.id_forma_pago
        WHERE p.id_pedido IN ($placeholders)
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    // Bind parameters dinámicamente
    $types = str_repeat('i', count($pedidos_ids));
    $stmt->bind_param($types, ...$pedidos_ids);
    
    if (!$stmt->execute()) {
        error_log("Error en obtenerPagosPorPedidos: " . $stmt->error);
        $stmt->close();
        return [];
    }
    
    $result = $stmt->get_result();
    $pagos_por_pedido = [];
    
    while ($pago = $result->fetch_assoc()) {
        $pagos_por_pedido[intval($pago['id_pedido'])] = $pago;
    }
    
    $stmt->close();
    return $pagos_por_pedido;
}

/**
 * Crea un registro de pago
 * Reemplaza la funcionalidad del trigger trg_validar_pago_unico_aprobado: valida monto > 0 y pago único aprobado
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @param int $id_forma_pago ID de la forma de pago
 * @param float $monto Monto del pago
 * @param string $estado_pago Estado inicial (default: 'pendiente')
 * @return int ID del pago creado o 0 si falló
 */
function crearPago($mysqli, $id_pedido, $id_forma_pago, $monto, $estado_pago = 'pendiente') {
    // Validar parámetros de entrada
    $id_pedido = intval($id_pedido);
    $id_forma_pago = intval($id_forma_pago);
    $monto = floatval($monto);
    $estado_pago = trim($estado_pago);
    
    // Validar que los parámetros sean válidos
    if ($id_pedido <= 0 || $id_forma_pago <= 0 || $monto < 0) {
        error_log("crearPago: Parámetros inválidos - id_pedido: {$id_pedido}, id_forma_pago: {$id_forma_pago}, monto: {$monto}");
        return 0;
    }
    
    // Validar monto > 0 si se está aprobando el pago (reemplaza trg_validar_pago_unico_aprobado)
    if ($estado_pago === 'aprobado' && $monto <= 0) {
        return 0; // No se puede aprobar un pago con monto menor o igual a cero
    }
    
    // Verificar si ya existe un pago aprobado para este pedido (reemplaza trg_validar_pago_unico_aprobado)
    // Solo si el estado es 'aprobado' (para pendiente no hay problema)
    if ($estado_pago === 'aprobado') {
        // Remover FOR UPDATE para compatibilidad (solo necesario en transacciones explícitas)
        $sql_verificar = "SELECT COUNT(*) as pagos_aprobados FROM Pagos WHERE id_pedido = ? AND estado_pago = 'aprobado'";
        
        $stmt_verificar = $mysqli->prepare($sql_verificar);
        if ($stmt_verificar) {
            $stmt_verificar->bind_param('i', $id_pedido);
            if ($stmt_verificar->execute()) {
                $result_verificar = $stmt_verificar->get_result();
                $verificacion = $result_verificar->fetch_assoc();
                $stmt_verificar->close();
                
                if ($verificacion && isset($verificacion['pagos_aprobados']) && intval($verificacion['pagos_aprobados']) > 0) {
                    return 0; // Ya existe un pago aprobado para este pedido
                }
            } else {
                $stmt_verificar->close();
                // Continuar aunque falle la verificación (log de advertencia)
                error_log("crearPago: Error al verificar pagos aprobados: " . $stmt_verificar->error);
            }
        }
    }
    
    // Guardar el monto del pago en la base de datos
    $sql = "INSERT INTO Pagos (id_pedido, id_forma_pago, estado_pago, monto, fecha_pago) VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("crearPago: Error al preparar consulta: " . $mysqli->error);
        return 0;
    }
    
    $stmt->bind_param('iisd', $id_pedido, $id_forma_pago, $estado_pago, $monto);
    
    if (!$stmt->execute()) {
        error_log("crearPago: Error al ejecutar consulta: " . $stmt->error);
        $stmt->close();
        return 0;
    }
    
    $id_pago = $mysqli->insert_id;
    $stmt->close();
    
    // Validar que se obtuvo un ID válido
    if (!$id_pago || $id_pago <= 0) {
        error_log("crearPago: Error - insert_id no válido después de insertar");
        return 0;
    }
    
    return intval($id_pago);
}

/**
 * Actualiza el estado de un pago SIN descontar stock
 * 
 * IMPORTANTE: Esta función NO descuenta stock al aprobar un pago.
 * Solo actualiza el estado del pago y el estado del pedido según corresponda.
 * 
 * DIFERENCIAS CON actualizarEstadoPagoConPedido():
 * - actualizarEstadoPago(): NO descuenta stock al aprobar, solo actualiza estados
 * - actualizarEstadoPagoConPedido(): SÍ descuenta stock al aprobar, valida stock disponible
 * 
 * NOTA: Si el pago estaba aprobado y se rechaza/cancela, esta función SÍ restaura stock
 * porque detecta que el stock fue descontado previamente. Pero NO descuenta stock al aprobar.
 * 
 * CUÁNDO USAR:
 * - Para cambios de estado que NO requieren gestión de stock (cancelar pago cuando pedido ya cancelado)
 * - Para rechazar/cancelar pagos que NO estaban aprobados (no hay stock que revertir)
 * - Para actualizaciones simples de estado sin afectar inventario
 * 
 * CUÁNDO NO USAR:
 * - Para aprobar pagos: usar actualizarEstadoPagoConPedido() que descuenta stock
 * - Para rechazar pagos aprobados: usar actualizarEstadoPagoConPedido() que restaura stock
 * 
 * Reemplaza la funcionalidad de los triggers:
 * - trg_validar_pago_unico_aprobado_update: valida monto > 0 y pago único aprobado
 * - trg_actualizar_pedido_por_pago: actualiza estado del pedido cuando se aprueba/rechaza
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago
 * @param string $nuevo_estado Nuevo estado del pago
 * @param string|null $motivo_rechazo Motivo del rechazo (opcional)
 * @param string|null $fecha_aprobacion Fecha de aprobación (opcional, se establece automáticamente si es null)
 * @return bool True si se actualizó correctamente
 */
function actualizarEstadoPago($mysqli, $id_pago, $nuevo_estado, $motivo_rechazo = null, $fecha_aprobacion = null) {
    $estados_validos = ['pendiente', 'pendiente_aprobacion', 'aprobado', 'rechazado', 'cancelado'];
    
    if (!in_array($nuevo_estado, $estados_validos)) {
        return false;
    }
    
    // Obtener datos actuales del pago para validaciones
    $pago_actual = obtenerPagoPorId($mysqli, $id_pago);
    if (!$pago_actual) {
        return false;
    }
    
    $estado_anterior = $pago_actual['estado_pago'];
    $monto_actual = floatval($pago_actual['monto']);
    $id_pedido = intval($pago_actual['id_pedido']);
    
    // Iniciar transacción para atomicidad
    $mysqli->begin_transaction();
    
    // Validar solo si se está aprobando un pago (reemplaza trg_validar_pago_unico_aprobado_update)
    if ($nuevo_estado === 'aprobado' && $estado_anterior !== 'aprobado') {
        // Validar monto > 0 cuando se aprueba
        if ($monto_actual <= 0) {
            $mysqli->rollback();
            return false; // No se puede aprobar un pago con monto menor o igual a cero
        }
        
        // Verificar si ya existe otro pago aprobado para este pedido (excluyendo el actual)
        // Sin FOR UPDATE - la validación se hace con la transacción y verificaciones atómicas
        $sql_verificar = "
            SELECT COUNT(*) as pagos_aprobados
            FROM Pagos
            WHERE id_pedido = ?
              AND estado_pago = 'aprobado'
              AND id_pago != ?
        ";
        
        $stmt_verificar = $mysqli->prepare($sql_verificar);
        if ($stmt_verificar) {
            $stmt_verificar->bind_param('ii', $id_pedido, $id_pago);
            if ($stmt_verificar->execute()) {
                $result_verificar = $stmt_verificar->get_result();
                $verificacion = $result_verificar->fetch_assoc();
                $stmt_verificar->close();
                
                if ($verificacion && intval($verificacion['pagos_aprobados']) > 0) {
                    $mysqli->rollback();
                    return false; // Ya existe otro pago aprobado para este pedido
                }
            } else {
                $stmt_verificar->close();
                $mysqli->rollback();
                error_log("Error en actualizarEstadoPago - verificación falló: " . $mysqli->error);
                return false;
            }
        } else {
            $mysqli->rollback();
            error_log("Error en actualizarEstadoPago - prepare verificación falló: " . $mysqli->error);
            return false;
        }
    }
    
    // Si se aprueba, establecer fecha_aprobacion si no está establecida
    if ($nuevo_estado === 'aprobado' && $fecha_aprobacion === null) {
        $fecha_aprobacion = date('Y-m-d H:i:s');
    }
    
    // Si no es aprobado, limpiar fecha_aprobacion
    if ($nuevo_estado !== 'aprobado') {
        $fecha_aprobacion = null;
    }
    
    try {
        // Actualizar estado del pago
        $sql = "
            UPDATE Pagos 
            SET estado_pago = ?,
                fecha_actualizacion = NOW(),
                fecha_aprobacion = ?,
                motivo_rechazo = ?
            WHERE id_pago = ?
        ";
        
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error al preparar actualización de pago #' . $id_pago . ': ' . $mysqli->error);
        }
        
        // Preparar valores para bind_param (null es válido para campos NULL en MySQL)
        $fecha_aprobacion_str = $fecha_aprobacion ?? null;
        $motivo_rechazo_str = $motivo_rechazo ?? null;
        
        // bind_param: 'sssi' = 4 parámetros: estado (s), fecha_aprobacion (s), motivo_rechazo (s), id_pago (i)
        $stmt->bind_param('sssi', $nuevo_estado, $fecha_aprobacion_str, $motivo_rechazo_str, $id_pago);
        $resultado = $stmt->execute();
        $stmt->close();
        
        if (!$resultado) {
            throw new Exception('Error al actualizar estado del pago #' . $id_pago . ' a ' . $nuevo_estado . ': ' . $mysqli->error);
        }
        
        // Actualizar estado del pedido según el estado del pago (reemplaza trg_actualizar_pedido_por_pago)
        require_once __DIR__ . '/../queries_helper.php';
        try {
            cargarArchivoQueries('pedido_queries', __DIR__);
        } catch (Exception $e) {
            error_log("ERROR: No se pudo cargar pedido_queries.php - " . $e->getMessage());
            die("Error crítico: Archivo de consultas de pedido no encontrado. Por favor, contacta al administrador.");
        }
        
        // Obtener estado actual del pedido para validaciones
        $pedido_actual = obtenerPedidoPorId($mysqli, $id_pedido);
        $estado_pedido_actual = $pedido_actual ? strtolower(trim($pedido_actual['estado_pedido'] ?? '')) : '';
        
        // VALIDACIÓN CRÍTICA: No permitir cancelar pagos que están en recorrido activo
        // Usar función centralizada para evitar duplicación de lógica
        try {
            _validarCancelacionPagoRecorridoActivo($nuevo_estado, $estado_anterior);
        } catch (Exception $e) {
            $mysqli->rollback();
            throw $e;
        }
        
        // VALIDACIÓN CRÍTICA: No permitir cancelar/rechazar pagos aprobados cuando el pedido está completado
        // Un pedido completado es una venta cerrada y no se puede revertir
        // Usar función centralizada para evitar duplicación
        if (in_array($nuevo_estado, ['rechazado', 'cancelado'])) {
            try {
                _validarRechazoCancelacionPagoAprobado($estado_anterior, $estado_pedido_actual);
            } catch (Exception $e) {
                $mysqli->rollback();
                throw $e;
            }
        }
        
        // Cuando el pago se aprueba, cambiar pedido a preparacion
        if ($nuevo_estado === 'aprobado' && $estado_anterior !== 'aprobado') {
            $sql_pedido = "
                UPDATE Pedidos 
                SET estado_pedido = 'preparacion',
                    fecha_actualizacion = NOW()
                WHERE id_pedido = ? 
                  AND estado_pedido IN ('pendiente', 'preparacion')
            ";
            
            $stmt_pedido = $mysqli->prepare($sql_pedido);
            if ($stmt_pedido) {
                $stmt_pedido->bind_param('i', $id_pedido);
                $stmt_pedido->execute();
                $stmt_pedido->close();
            }
        }
        
        // Cuando el pago se rechaza o cancela, cambiar pedido a cancelado
        // IMPORTANTE: Verificar que el pedido NO esté ya cancelado para evitar actualizaciones duplicadas
        // NOTA: Pedidos completados están protegidos por validación previa (línea 333-340)
        // NOTA: 'preparacion' no es un estado de pago válido, solo 'pendiente' y 'pendiente_aprobacion' pueden cancelarse/rechazarse
        if (in_array($nuevo_estado, ['rechazado', 'cancelado']) 
            && $estado_anterior !== $nuevo_estado 
            && $estado_pedido_actual !== 'cancelado'  // Evitar cancelar pedido si ya está cancelado
            && $estado_pedido_actual !== 'completado'  // Pedidos completados no se pueden cancelar
            && in_array($estado_anterior, ['pendiente', 'pendiente_aprobacion'])) {
            
            error_log("actualizarEstadoPago: Cancelando pedido #{$id_pedido} debido a pago {$nuevo_estado} (estado anterior: {$estado_anterior})");
            
            // Si el pago estaba aprobado, SIEMPRE restaurar stock (sin importar el estado del pedido)
            // porque el stock se descontó cuando se aprobó el pago
            if ($estado_anterior === 'aprobado') {
                error_log("actualizarEstadoPago: Restaurando stock del pedido #{$id_pedido} porque el pago estaba aprobado");
                require_once __DIR__ . '/stock_queries.php';
                revertirStockPedido($mysqli, $id_pedido, null, "Pago " . $nuevo_estado);
            }
            
            // Solo actualizar el pedido si no está ya cancelado y está en un estado que permite cancelación
            // NOTA: Pedidos en recorrido activo (preparacion, en_viaje, completado, devolucion) NO pueden cancelarse
            // según la matriz de estados. Solo estados iniciales pueden cancelarse.
            if ($estado_pedido_actual !== 'cancelado' && puedeCancelarPedido($estado_pedido_actual)) {
                $sql_pedido = "
                    UPDATE Pedidos 
                    SET estado_pedido = 'cancelado',
                        fecha_actualizacion = NOW()
                    WHERE id_pedido = ?
                ";
                
                $stmt_pedido = $mysqli->prepare($sql_pedido);
                if ($stmt_pedido) {
                    $stmt_pedido->bind_param('i', $id_pedido);
                    if ($stmt_pedido->execute()) {
                        error_log("actualizarEstadoPago: Pedido #{$id_pedido} cancelado exitosamente");
                    } else {
                        error_log("actualizarEstadoPago: Error al cancelar pedido #{$id_pedido} - " . $stmt_pedido->error);
                    }
                    $stmt_pedido->close();
                }
            } else {
                error_log("actualizarEstadoPago: Pedido #{$id_pedido} ya está cancelado, omitiendo actualización");
            }
        }
        
        $mysqli->commit();
        return true;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Error en actualizarEstadoPago: " . $e->getMessage());
        return false;
    }
}

/**
 * Aprueba un pago y descuenta stock automáticamente
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago
 * @param int|null $id_usuario ID del usuario que aprueba (opcional)
 * @return bool True si se aprobó correctamente
 * @throws Exception Si hay error en la aprobación
 */
function aprobarPago($mysqli, $id_pago, $id_usuario = null) {
    // Obtener datos del pago
    $pago = obtenerPagoPorId($mysqli, $id_pago);
    
    if (!$pago) {
        return false;
    }
    
    // Si ya está aprobado, no hacer nada
    if ($pago['estado_pago'] === 'aprobado') {
        return true;
    }
    
    // Validar monto > 0 antes de aprobar
    $monto = floatval($pago['monto']);
    if ($monto <= 0) {
        throw new Exception('No se puede aprobar un pago con monto menor o igual a cero');
    }
    
    // Usar actualizarEstadoPagoConPedido() que valida stock ANTES de aprobar
    // Esto garantiza que la validación y aprobación sean atómicas
    // Esta función reemplaza a actualizarPagoCompleto() para evitar duplicación de lógica
    return actualizarEstadoPagoConPedido($mysqli, $id_pago, 'aprobado', null, $id_usuario);
}

/**
 * Actualiza un pago completo con todos sus campos editables (monto, numero_transaccion, estado)
 * 
 * IMPORTANTE: Esta función está DEPRECADA SOLO para aprobar pagos (estado 'aprobado').
 *             Para aprobar pagos, usar actualizarEstadoPagoConPedido() que maneja validación de stock y descuento correctamente.
 *             Esta función sigue siendo VÁLIDA y RECOMENDADA para actualizar monto/numero_transaccion con estados que NO requieren gestión de stock.
 * 
 * CUÁNDO USAR ESTA FUNCIÓN:
 * - Para actualizar monto y numero_transaccion junto con estados que NO requieren gestión de stock
 *   (pendiente_aprobacion, pendiente, etc.)
 * - Ejemplo válido: perfil.php actualiza a 'pendiente_aprobacion' con monto y numero_transaccion
 * 
 * CUÁNDO NO USAR ESTA FUNCIÓN:
 * - Para aprobar pagos: usar actualizarEstadoPagoConPedido() que valida y descuenta stock
 * - Para cambios de estado simples sin actualizar monto/numero_transaccion: usar actualizarEstadoPago()
 * 
 * NOTA: Si solo cambia el estado sin cambios en monto/numero_transaccion, esta función delega
 * automáticamente a actualizarEstadoPago() para evitar duplicación de lógica.
 * 
 * EJEMPLOS DE USO VÁLIDO:
 * - perfil.php: actualizarPagoCompleto($mysqli, $id_pago, 'pendiente_aprobacion', $monto, $numero_transaccion)
 *   → Cliente marca pago como pagado, actualiza monto y numero_transaccion, cambia estado a pendiente_aprobacion
 *   → NO descuenta stock (se descuenta cuando ventas aprueba el pago)
 * 
 * EJEMPLOS DE USO INVÁLIDO:
 * - actualizarPagoCompleto($mysqli, $id_pago, 'aprobado', $monto, $numero_transaccion)
 *   → INCORRECTO: Para aprobar pagos, usar actualizarEstadoPagoConPedido() que valida y descuenta stock
 * 
 * @deprecated SOLO para aprobar pagos. Para aprobar pagos, usar actualizarEstadoPagoConPedido() en su lugar.
 *             Esta función sigue siendo válida para actualizar monto/numero_transaccion con estados que no requieren gestión de stock.
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago
 * @param string $estado_pago Estado del pago
 * @param float $monto Monto del pago
 * @param string|null $numero_transaccion Número de transacción
 * @param string|null $motivo_rechazo Motivo del rechazo (si aplica)
 * @return bool True si se actualizó correctamente
 */
function actualizarPagoCompleto($mysqli, $id_pago, $estado_pago, $monto, $numero_transaccion = null, $motivo_rechazo = null) {
    $estados_validos = ['pendiente', 'pendiente_aprobacion', 'aprobado', 'rechazado', 'cancelado'];
    
    if (!in_array($estado_pago, $estados_validos)) {
        throw new Exception('Estado de pago inválido: ' . $estado_pago);
    }
    
    // Obtener datos actuales del pago para validaciones
    $pago_actual = obtenerPagoPorId($mysqli, $id_pago);
    if (!$pago_actual) {
        throw new Exception('Pago no encontrado con ID: ' . $id_pago);
    }
    
    $estado_anterior = $pago_actual['estado_pago'];
    $id_pedido = intval($pago_actual['id_pedido']);
    
    // Si se está aprobando, delegar a actualizarEstadoPagoConPedido() para evitar duplicación
    // Primero actualizar monto y numero_transaccion si es necesario
    if ($estado_pago === 'aprobado' && $estado_anterior !== 'aprobado') {
        // Validar monto > 0 antes de aprobar
        if ($monto <= 0) {
            throw new Exception('No se puede aprobar un pago con monto menor o igual a cero');
        }
        
        // Si hay cambios en monto o numero_transaccion, actualizarlos primero
        if ($monto != floatval($pago_actual['monto']) || $numero_transaccion != $pago_actual['numero_transaccion']) {
            $mysqli->begin_transaction();
            try {
                $sql_update = "
                    UPDATE Pagos 
                    SET monto = ?,
                        numero_transaccion = ?,
                        fecha_actualizacion = NOW()
                    WHERE id_pago = ?
                ";
                $stmt_update = $mysqli->prepare($sql_update);
                if (!$stmt_update) {
                    throw new Exception('Error al preparar actualización de monto: ' . $mysqli->error);
                }
                $stmt_update->bind_param('dsi', $monto, $numero_transaccion, $id_pago);
                if (!$stmt_update->execute()) {
                    $error_msg = $stmt_update->error;
                    $stmt_update->close();
                    throw new Exception('Error al actualizar monto: ' . $error_msg);
                }
                $stmt_update->close();
                $mysqli->commit();
            } catch (Exception $e) {
                $mysqli->rollback();
                throw $e;
            }
        }
        
        // Delegar aprobación a actualizarEstadoPagoConPedido() que maneja validación de stock,
        // descuento y actualización de estados correctamente
        return actualizarEstadoPagoConPedido($mysqli, $id_pago, 'aprobado', null, null);
    }
    
    // Para otros estados: si solo cambia el estado y no hay cambios en monto/numero_transaccion,
    // delegar a actualizarEstadoPago() para evitar duplicación de lógica
    $monto_cambia = ($monto != floatval($pago_actual['monto']));
    $numero_transaccion_cambia = ($numero_transaccion != $pago_actual['numero_transaccion']);
    $estado_cambia = ($estado_pago !== $estado_anterior);
    
    // Si solo cambia el estado y no hay cambios en monto/numero_transaccion, delegar
    if ($estado_cambia && !$monto_cambia && !$numero_transaccion_cambia) {
        // Delegar cambio de estado a actualizarEstadoPago() que maneja pedido y stock correctamente
        return actualizarEstadoPago($mysqli, $id_pago, $estado_pago, $motivo_rechazo);
    }
    
    // Si hay cambios en monto/numero_transaccion o el estado no cambia, actualizar directamente
    // Iniciar transacción para mantener consistencia
    $mysqli->begin_transaction();
    
    try {
        // Preparar fecha_aprobacion
        $fecha_aprobacion = null;
        if ($estado_pago === 'aprobado') {
            $fecha_aprobacion = date('Y-m-d H:i:s');
        }
        
        // Actualizar pago completo (monto, numero_transaccion, estado)
        $sql = "
            UPDATE Pagos 
            SET estado_pago = ?,
                monto = ?,
                numero_transaccion = ?,
                fecha_aprobacion = ?,
                motivo_rechazo = ?,
                fecha_actualizacion = NOW()
            WHERE id_pago = ?
        ";
        
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error al preparar actualización de pago #' . $id_pago . ': ' . $mysqli->error);
        }
        
        $stmt->bind_param('sdsssi', $estado_pago, $monto, $numero_transaccion, $fecha_aprobacion, $motivo_rechazo, $id_pago);
        if (!$stmt->execute()) {
            $error_msg = $stmt->error;
            $stmt->close();
            throw new Exception('Error al actualizar pago #' . $id_pago . ' (estado: ' . $estado_pago . '): ' . $error_msg);
        }
        $stmt->close();
        
            // Si el estado cambió a rechazado/cancelado, delegar manejo de pedido y stock a actualizarEstadoPago()
            // para evitar duplicación de lógica
            if ($estado_cambia && in_array($estado_pago, ['rechazado', 'cancelado'])) {
                // actualizarEstadoPago() maneja cancelación de pedido y reversión de stock correctamente
                // No necesita transacción propia porque ya estamos en una
                require_once __DIR__ . '/pedido_queries.php';
                $pedido_actual = obtenerPedidoPorId($mysqli, $id_pedido);
                $estado_pedido_actual = $pedido_actual ? strtolower(trim($pedido_actual['estado_pedido'] ?? '')) : '';
                
                // VALIDACIÓN: No cancelar pedidos completados
                // Usar función centralizada para evitar duplicación
                _validarRechazoCancelacionPagoAprobado($estado_anterior, $estado_pedido_actual);
                
                if ($pedido_actual && $pedido_actual['estado_pedido'] !== 'cancelado' && $estado_pedido_actual !== 'completado') {
                    // Solo actualizar pedido si no está ya cancelado ni completado
                    $sql_pedido = "
                        UPDATE Pedidos 
                        SET estado_pedido = 'cancelado',
                            fecha_actualizacion = NOW()
                        WHERE id_pedido = ?
                          AND estado_pedido IN ('pendiente', 'preparacion', 'en_viaje')
                    ";
                
                $stmt_pedido = $mysqli->prepare($sql_pedido);
                if ($stmt_pedido) {
                    $stmt_pedido->bind_param('i', $id_pedido);
                    $stmt_pedido->execute();
                    $stmt_pedido->close();
                }
            }
            
            // Si el pago estaba aprobado, restaurar stock
            if ($estado_anterior === 'aprobado') {
                require_once __DIR__ . '/stock_queries.php';
                revertirStockPedido($mysqli, $id_pedido, null, "Pago " . $estado_pago);
            }
        }
        
        $mysqli->commit();
        return true;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Error en actualizarPagoCompleto: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Rechaza un pago y revierte el stock si ya había sido descontado
 * 
 * NOTA: Esta función delega a actualizarEstadoPagoConPedido() que ya maneja
 * la reversión de stock y cancelación de pedido correctamente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago
 * @param int $id_usuario ID del usuario que rechaza (opcional, no usado actualmente)
 * @param string $motivo Motivo del rechazo (opcional)
 * @return bool True si se rechazó correctamente
 */
function rechazarPago($mysqli, $id_pago, $id_usuario = null, $motivo = null) {
    // Delegar a actualizarEstadoPagoConPedido() que maneja:
    // - Actualización de estado a 'rechazado'
    // - Reversión de stock si el pago estaba aprobado
    // - Cancelación del pedido si corresponde
    // - Manejo de transacciones
    return actualizarEstadoPagoConPedido($mysqli, $id_pago, 'rechazado', $motivo, $id_usuario);
}


/**
 * Obtiene todos los pagos con filtros opcionales
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string|null $estado Filtro por estado (opcional)
 * @param int $limite Cantidad de registros a retornar (0 = sin límite)
 * @return array Array de pagos
 */
function obtenerPagos($mysqli, $estado = null, $limite = 0) {
    // MEJORA DE SEGURIDAD: LIMIT dinámico - ver comentario en obtenerPedidos()
    // sobre limitaciones de placeholder en LIMIT. Validación actual es suficiente
    // pero podría mejorarse con alternativa de dos queries.
    $where_clause = '';
    $params = [];
    $types = '';
    
    if ($estado) {
        $where_clause = "WHERE p.estado_pago = ?";
        $params[] = $estado;
        $types .= 's';
    }
    
    $limit_clause = $limite > 0 ? "LIMIT $limite" : "";
    
    $sql = "
        SELECT 
            p.id_pago,
            p.id_pedido,
            p.id_forma_pago,
            p.estado_pago,
            p.monto,
            p.fecha_pago,
            p.fecha_actualizacion,
            fp.nombre as forma_pago_nombre,
            ped.estado_pedido
        FROM Pagos p
        INNER JOIN Forma_Pagos fp ON p.id_forma_pago = fp.id_forma_pago
        INNER JOIN Pedidos ped ON p.id_pedido = ped.id_pedido
        $where_clause
        ORDER BY p.fecha_pago DESC
        $limit_clause
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pagos = [];
    while ($row = $result->fetch_assoc()) {
        $pagos[] = $row;
    }
    
    $stmt->close();
    return $pagos;
}

/**
 * Verifica si un estado de pago está en recorrido activo
 * 
 * Recorrido activo = estados que dejaron de estar en estado inicial y están en proceso.
 * Un pago en recorrido activo NO puede cancelarse.
 * 
 * NOTA: Esta función ahora delega a StateValidator para centralizar la lógica de validación.
 * Se mantiene por compatibilidad hacia atrás.
 * 
 * @param string $estado_pago Estado del pago a verificar
 * @return bool True si está en recorrido activo
 */
function estaEnRecorridoActivoPago($estado_pago) {
    // Cargar StateValidator si no está cargado
    require_once __DIR__ . '/../state_validator.php';
    
    // Delegar validación a StateValidator
    return StateValidator::isInActiveJourney($estado_pago, 'pago');
}

/**
 * Verifica si un pago puede ser cancelado según su estado actual
 * 
 * REGLA DE NEGOCIO: Solo pagos en estado inicial (pendiente) pueden cancelarse.
 * Pagos en recorrido activo (pendiente_aprobacion, aprobado) NO pueden cancelarse.
 * 
 * NOTA: Esta función ahora delega a StateValidator para centralizar la lógica de validación.
 * Se mantiene por compatibilidad hacia atrás.
 * 
 * @param string $estado_pago Estado actual del pago
 * @return bool True si puede cancelarse, false en caso contrario
 */
function puedeCancelarPago($estado_pago) {
    // Cargar StateValidator si no está cargado
    require_once __DIR__ . '/../state_validator.php';
    
    // Delegar validación a StateValidator
    return StateValidator::canCancel($estado_pago, 'pago');
}

/**
 * Matriz de transiciones permitidas para estados de Pago
 * 
 * Define explícitamente qué transiciones de estado están permitidas desde cada estado actual.
 * Esta matriz previene transiciones inválidas antes de ejecutar lógica de negocio.
 * 
 * REGLAS DE NEGOCIO:
 * - Estados iniciales (pendiente): Pueden cancelarse o rechazarse
 * - Estados de recorrido activo (pendiente_aprobacion, aprobado): NO pueden cancelarse
 * - Un pago en recorrido activo solo puede avanzar o ser rechazado, nunca cancelado
 * 
 * @var array
 */
$transicionesPago = [
    'pendiente' => ['pendiente_aprobacion', 'aprobado', 'rechazado', 'cancelado'],
    'pendiente_aprobacion' => ['aprobado', 'rechazado'], // NO puede cancelarse: ya está en recorrido activo
    'aprobado' => ['rechazado'], // NO puede cancelarse: ya está en recorrido activo (solo rechazo en casos extremos)
    'rechazado' => [], // estado terminal
    'cancelado' => [] // estado terminal
];

// Asegurar que $transicionesPago esté disponible globalmente
$GLOBALS['transicionesPago'] = $transicionesPago;

/**
 * Valida si una transición de estado de pago está permitida según la matriz de transiciones
 * 
 * NOTA: Esta función ahora delega a StateValidator para centralizar la lógica de validación.
 * Se mantiene por compatibilidad hacia atrás.
 * 
 * @param string $estado_actual Estado actual del pago
 * @param string $nuevo_estado Nuevo estado al que se quiere cambiar
 * @return bool True si la transición está permitida
 * @throws Exception Si la transición no está permitida
 */
function validarTransicionPago($estado_actual, $nuevo_estado) {
    // Cargar StateValidator si no está cargado
    require_once __DIR__ . '/../state_validator.php';
    
    // Delegar validación a StateValidator
    return StateValidator::canTransition($estado_actual, $nuevo_estado, 'pago');
}

/**
 * Valida que no se intente cancelar un pago que está en recorrido activo
 * 
 * NOTA: Esta función ahora delega a StateValidator para centralizar la lógica de validación.
 * 
 * @param string $nuevo_estado_pago Nuevo estado del pago
 * @param string $estado_pago_anterior Estado anterior del pago
 * @return void
 * @throws Exception Si se intenta cancelar un pago en recorrido activo
 */
function _validarCancelacionPagoRecorridoActivo($nuevo_estado_pago, $estado_pago_anterior) {
    // Cargar StateValidator y estado_helpers si no están cargados
    require_once __DIR__ . '/../state_validator.php';
    require_once __DIR__ . '/../estado_helpers.php';
    
    // Normalizar estados
    $nuevo_estado_norm = strtolower(trim($nuevo_estado_pago));
    $estado_anterior_norm = strtolower(trim($estado_pago_anterior));
    
    // Si se intenta cancelar y está en recorrido activo, lanzar excepción
    if ($nuevo_estado_norm === 'cancelado' && StateValidator::isInActiveJourney($estado_anterior_norm, 'pago')) {
        $info_pago = obtenerInfoEstadoPago($estado_anterior_norm);
        throw new Exception("No se puede cancelar el pago porque está en proceso (estado: '{$info_pago['nombre']}'). Los pagos en proceso no pueden cancelarse. Si necesitas revertir un pago aprobado, debes rechazarlo en lugar de cancelarlo.");
    }
}

/**
 * Valida que no se intente cancelar/rechazar un pago aprobado cuando el pedido está en estados avanzados
 * 
 * Esta función centraliza la validación que se repite en múltiples lugares,
 * eliminando código duplicado.
 * 
 * NOTA: Esta función ahora usa StateValidator para validar estados terminales.
 * 
 * @param string $estado_pago_anterior Estado anterior del pago
 * @param string $estado_pedido_actual Estado actual del pedido
 * @return void
 * @throws Exception Si se intenta cancelar/rechazar pago aprobado en pedido avanzado
 */
function _validarRechazoCancelacionPagoAprobado($estado_pago_anterior, $estado_pedido_actual) {
    // Cargar StateValidator y estado_helpers si no están cargados
    require_once __DIR__ . '/../state_validator.php';
    require_once __DIR__ . '/../estado_helpers.php';
    
    // Normalizar estados
    $estado_pago_anterior = strtolower(trim($estado_pago_anterior));
    $estado_pedido_actual = strtolower(trim($estado_pedido_actual));
    
    // Solo validar si el pago estaba aprobado
    if ($estado_pago_anterior !== 'aprobado') {
        return;
    }
    
    // Validar estados terminales del pedido usando StateValidator
    if (StateValidator::isTerminal($estado_pedido_actual, 'pedido')) {
        $info_pedido = obtenerInfoEstadoPedido($estado_pedido_actual);
        throw new Exception("No se puede cancelar o rechazar el pago porque el pedido está en estado '{$info_pedido['nombre']}' (venta cerrada). Los pedidos completados son ventas finalizadas y no admiten modificaciones. Si necesitas hacer cambios, contacta al administrador del sistema.");
    }
    
    // Validar estados avanzados (en_viaje es un caso especial)
    if ($estado_pedido_actual === 'en_viaje') {
        throw new Exception("No se puede cancelar o rechazar el pago porque el pedido ya está en viaje. El pedido fue procesado y enviado. Si necesitas revertir esta operación, primero debes gestionar el retorno físico del pedido.");
    }
}

/**
 * Procesa la aprobación de un pago con validaciones y descuento de stock
 * 
 * Esta función extrae la lógica de aprobación de actualizarEstadoPagoConPedido()
 * para reducir anidación y mejorar mantenibilidad.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago
 * @param array $pago_bloqueado Datos del pago obtenidos dentro de la transacción
 * @param int $id_pedido ID del pedido
 * @param string $estado_pedido_actual Estado actual del pedido
 * @param int $id_usuario_pedido ID del usuario del pedido
 * @return void
 * @throws Exception Si hay error en la aprobación o stock insuficiente
 */
function _aprobarPagoConValidaciones($mysqli, $id_pago, $pago_bloqueado, $id_pedido, $estado_pedido_actual, $id_usuario_pedido) {
    // Cargar funciones de stock necesarias
    require_once __DIR__ . '/../queries_helper.php';
    cargarArchivoQueries('stock_queries', __DIR__);
    
    // Validar monto > 0
    $monto = floatval($pago_bloqueado['monto']);
    if ($monto <= 0) {
        throw new Exception('No se puede aprobar un pago con monto menor o igual a cero');
    }
    
    // Validar coherencia de negocio: El pedido debe estar en estado pendiente o preparacion
    // Permitir preparacion para corregir inconsistencias donde el pedido fue cambiado manualmente
    // pero el pago aún no está aprobado
    // NOTA: Todo pedido en 'pendiente' ya tiene stock validado (se valida antes de crear el pedido)
    if (!in_array($estado_pedido_actual, ['pendiente', 'preparacion'])) {
        require_once __DIR__ . '/../estado_helpers.php';
        $info_pedido = obtenerInfoEstadoPedido($estado_pedido_actual);
        throw new Exception("El pago no puede aprobarse porque el pedido está en estado '{$info_pedido['nombre']}'. Solo se pueden aprobar pagos de pedidos en estado inicial (pendiente) o en preparación. Si el pedido ya avanzó más, verifica el estado actual y ajusta según corresponda.");
    }
    
    // Validar que no exista otro pago aprobado para este pedido
    $sql_verificar = "
        SELECT COUNT(*) as pagos_aprobados
        FROM Pagos
        WHERE id_pedido = ?
          AND estado_pago = 'aprobado'
          AND id_pago != ?
    ";
    
    $stmt_verificar = $mysqli->prepare($sql_verificar);
    if (!$stmt_verificar) {
        throw new Exception('Error al preparar verificación de pagos aprobados: ' . $mysqli->error);
    }
    $stmt_verificar->bind_param('ii', $id_pedido, $id_pago);
    if (!$stmt_verificar->execute()) {
        $error_msg = $stmt_verificar->error;
        $stmt_verificar->close();
        throw new Exception('Error al verificar pagos aprobados: ' . $error_msg);
    }
    $result_verificar = $stmt_verificar->get_result();
    $verificacion = $result_verificar->fetch_assoc();
    $stmt_verificar->close();
    
    if ($verificacion && intval($verificacion['pagos_aprobados']) > 0) {
        throw new Exception('Ya existe un pago aprobado para este pedido. No se puede aprobar otro pago para el mismo pedido. Sugerencia: Revisa el historial de pagos del pedido para verificar que no haya duplicados o contacta al administrador si necesitas corregir esta situación.');
    }
    
    // Obtener Detalle_Pedido del pedido
    $sql_detalles = "
        SELECT id_variante, cantidad
        FROM Detalle_Pedido
        WHERE id_pedido = ?
    ";
    
    $stmt_detalles = $mysqli->prepare($sql_detalles);
    if (!$stmt_detalles) {
        throw new Exception('Error al preparar consulta de detalles: ' . $mysqli->error);
    }
    $stmt_detalles->bind_param('i', $id_pedido);
    if (!$stmt_detalles->execute()) {
        $error_msg = $stmt_detalles->error;
        $stmt_detalles->close();
        throw new Exception('Error al obtener detalles del pedido: ' . $error_msg);
    }
    $result_detalles = $stmt_detalles->get_result();
    
    $detalles_pedido = [];
    while ($row = $result_detalles->fetch_assoc()) {
        $detalles_pedido[] = [
            'id_variante' => intval($row['id_variante']),
            'cantidad' => intval($row['cantidad'])
        ];
    }
    $stmt_detalles->close();
    
    if (empty($detalles_pedido)) {
        throw new Exception('El pedido no tiene detalles');
    }
    
    // Validar stock por variante antes de aprobar
    $errores_stock = [];
    foreach ($detalles_pedido as $detalle) {
        $id_variante = $detalle['id_variante'];
        $cantidad_solicitada = $detalle['cantidad'];
        
        try {
            validarStockDisponibleVenta($mysqli, $id_variante, $cantidad_solicitada);
        } catch (Exception $e) {
            // Mejorar mensaje de error para ser más informativo
            $mensaje_error = $e->getMessage();
            if (preg_match('/Stock disponible: (\d+), Intento de venta: (\d+)/', $mensaje_error, $matches)) {
                $stock_disponible = $matches[1];
                $intento_venta = $matches[2];
                $errores_stock[] = "Variante #{$id_variante}: Tiene {$stock_disponible} unidades disponibles pero se necesitan {$intento_venta} unidades";
            } else {
                $errores_stock[] = "Variante #{$id_variante}: " . $mensaje_error;
            }
        }
    }
    
    // Si hay errores de stock, rechazar pago con motivo
    if (!empty($errores_stock)) {
        $motivo_sin_stock = 'Sin stock: ' . implode('; ', $errores_stock);
        
        $sql_rechazar = "
            UPDATE Pagos 
            SET estado_pago = 'rechazado',
                motivo_rechazo = ?,
                fecha_actualizacion = NOW()
            WHERE id_pago = ?
        ";
        
        $stmt_rechazar = $mysqli->prepare($sql_rechazar);
        if (!$stmt_rechazar) {
            throw new Exception('Error al preparar rechazo de pago: ' . $mysqli->error);
        }
        $stmt_rechazar->bind_param('si', $motivo_sin_stock, $id_pago);
        if (!$stmt_rechazar->execute()) {
            $error_msg = $stmt_rechazar->error;
            $stmt_rechazar->close();
            throw new Exception('Error al rechazar pago por falta de stock: ' . $error_msg);
        }
        $stmt_rechazar->close();
        
        $mysqli->commit();
        throw new Exception('STOCK_INSUFICIENTE: ' . implode('; ', $errores_stock));
    }
    
    // Verificar que no haya ventas previas para este pedido (guardrail)
    $hay_ventas_previas = verificarVentasPreviasPedido($mysqli, $id_pedido);
    if ($hay_ventas_previas > 0) {
        error_log("Intento de descontar stock duplicado para pedido #{$id_pedido}. Ya existen {$hay_ventas_previas} movimientos tipo 'venta'.");
        throw new Exception('VENTA_YA_DESCONTADA_PARA_PEDIDO: El stock de este pedido ya fue descontado previamente. No se puede descontar nuevamente. Esto es normal si el pago ya fue aprobado anteriormente.');
    }
    
    // Descontar stock
    if (!descontarStockPedido($mysqli, $id_pedido, $id_usuario_pedido, true)) {
        throw new Exception('Error al descontar stock del pedido');
    }
    
    // Actualizar pago a aprobado
    $fecha_aprobacion = date('Y-m-d H:i:s');
    $sql_actualizar_pago = "
        UPDATE Pagos 
        SET estado_pago = 'aprobado',
            fecha_aprobacion = ?,
            fecha_actualizacion = NOW()
        WHERE id_pago = ?
    ";
    
    $stmt_actualizar_pago = $mysqli->prepare($sql_actualizar_pago);
    if (!$stmt_actualizar_pago) {
        throw new Exception('Error al preparar actualización de pago: ' . $mysqli->error);
    }
    $stmt_actualizar_pago->bind_param('si', $fecha_aprobacion, $id_pago);
    if (!$stmt_actualizar_pago->execute()) {
        $error_msg = $stmt_actualizar_pago->error;
        $stmt_actualizar_pago->close();
        throw new Exception('Error al actualizar estado del pago: ' . $error_msg);
    }
    $stmt_actualizar_pago->close();
    
    // Actualizar pedido a preparacion (solo si no está ya en preparacion)
    // Si ya está en preparacion, solo actualizar fecha_actualizacion
    $sql_actualizar_pedido = "
        UPDATE Pedidos 
        SET estado_pedido = 'preparacion',
            fecha_actualizacion = NOW()
        WHERE id_pedido = ?
          AND estado_pedido IN ('pendiente', 'preparacion')
    ";
    
    $stmt_actualizar_pedido = $mysqli->prepare($sql_actualizar_pedido);
    if (!$stmt_actualizar_pedido) {
        throw new Exception('Error al preparar actualización de pedido: ' . $mysqli->error);
    }
    $stmt_actualizar_pedido->bind_param('i', $id_pedido);
    if (!$stmt_actualizar_pedido->execute()) {
        $error_msg = $stmt_actualizar_pedido->error;
        $stmt_actualizar_pedido->close();
        throw new Exception('Error al actualizar estado del pedido: ' . $error_msg);
    }
    $stmt_actualizar_pedido->close();
}

/**
 * Procesa el rechazo o cancelación de un pago con restauración de stock
 * 
 * Esta función extrae la lógica de rechazo/cancelación de actualizarEstadoPagoConPedido()
 * para reducir anidación y mejorar mantenibilidad.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago
 * @param string $nuevo_estado_pago Nuevo estado ('rechazado' o 'cancelado')
 * @param string|null $motivo_rechazo Motivo del rechazo (opcional)
 * @param int $id_pedido ID del pedido
 * @param string $estado_pago_anterior Estado anterior del pago
 * @param string $estado_pedido_actual Estado actual del pedido
 * @param int|null $id_usuario ID del usuario que realiza la acción (opcional)
 * @return void
 * @throws Exception Si hay error en el rechazo/cancelación
 */
function _rechazarOCancelarPago($mysqli, $id_pago, $nuevo_estado_pago, $motivo_rechazo, $id_pedido, $estado_pago_anterior, $estado_pedido_actual, $id_usuario) {
    // Cargar funciones de stock necesarias
    require_once __DIR__ . '/../queries_helper.php';
    require_once __DIR__ . '/../estado_helpers.php';
    cargarArchivoQueries('stock_queries', __DIR__);
    
    // Normalizar estados antes de validar
    $estado_pago_anterior = normalizarEstado($estado_pago_anterior);
    $estado_pedido_actual = normalizarEstado($estado_pedido_actual);
    
    // VALIDACIÓN CRÍTICA: No permitir cancelar/rechazar pagos aprobados cuando el pedido está en estados avanzados
    // Estados avanzados: completado (venta cerrada) y en_viaje (pedido ya enviado)
    // Usar función centralizada para evitar duplicación
    try {
        _validarRechazoCancelacionPagoAprobado($estado_pago_anterior, $estado_pedido_actual);
    } catch (Exception $e) {
        // Mejorar mensaje de error para que sea más claro
        throw new Exception('No se puede rechazar/cancelar el pago: ' . $e->getMessage());
    }
    
    // Actualizar estado del pago primero
    $fecha_aprobacion = null; // Limpiar fecha_aprobacion si estaba aprobado
    $sql_actualizar_pago = "
        UPDATE Pagos 
        SET estado_pago = ?,
            motivo_rechazo = ?,
            fecha_aprobacion = ?,
            fecha_actualizacion = NOW()
        WHERE id_pago = ?
    ";
    
    $stmt_actualizar_pago = $mysqli->prepare($sql_actualizar_pago);
    if (!$stmt_actualizar_pago) {
        throw new Exception('Error al preparar actualización de pago: ' . $mysqli->error);
    }
    $stmt_actualizar_pago->bind_param('sssi', $nuevo_estado_pago, $motivo_rechazo, $fecha_aprobacion, $id_pago);
    if (!$stmt_actualizar_pago->execute()) {
        $error_msg = $stmt_actualizar_pago->error;
        $stmt_actualizar_pago->close();
        throw new Exception('Error al actualizar estado del pago: ' . $error_msg);
    }
    $stmt_actualizar_pago->close();
    
    // Continuar con la lógica de cancelación solo si el pedido no está cancelado ni completado
    if ($estado_pedido_actual !== 'cancelado'  
        && $estado_pedido_actual !== 'completado'
        && in_array($estado_pedido_actual, ['pendiente', 'preparacion', 'en_viaje'])) {
    
        error_log("actualizarEstadoPagoConPedido: Cancelando pedido #{$id_pedido} debido a pago {$nuevo_estado_pago} (estado anterior: {$estado_pago_anterior})");
        
        // Restaurar stock si había sido descontado (si el pago estaba aprobado)
        if ($estado_pago_anterior === 'aprobado') {
            error_log("actualizarEstadoPagoConPedido: Restaurando stock del pedido #{$id_pedido} porque el pago estaba aprobado");
            if (!revertirStockPedido($mysqli, $id_pedido, $id_usuario, "Pago " . $nuevo_estado_pago)) {
                throw new Exception('Error al restaurar stock del pedido');
            }
        }
        
        // Actualizar estado del pedido a 'cancelado'
        $sql_actualizar_pedido = "
            UPDATE Pedidos 
            SET estado_pedido = 'cancelado',
                fecha_actualizacion = NOW()
            WHERE id_pedido = ?
        ";
        
        $stmt_actualizar_pedido = $mysqli->prepare($sql_actualizar_pedido);
        if (!$stmt_actualizar_pedido) {
            throw new Exception('Error al preparar actualización de pedido: ' . $mysqli->error);
        }
        $stmt_actualizar_pedido->bind_param('i', $id_pedido);
        if (!$stmt_actualizar_pedido->execute()) {
            $error_msg = $stmt_actualizar_pedido->error;
            $stmt_actualizar_pedido->close();
            throw new Exception('Error al actualizar estado del pedido: ' . $error_msg);
        }
        $stmt_actualizar_pedido->close();
        error_log("actualizarEstadoPagoConPedido: Pedido #{$id_pedido} cancelado exitosamente");
    } else {
        error_log("actualizarEstadoPagoConPedido: Pedido #{$id_pedido} ya está cancelado o no está en estado cancelable, omitiendo cancelación de pedido");
    }
}

/**
 * Actualiza el estado de un pago Y gestiona stock y pedido automáticamente
 * 
 * IMPORTANTE: Esta función SÍ descuenta stock al aprobar un pago y restaura stock al rechazar/cancelar.
 * Es la función RECOMENDADA para aprobar pagos porque maneja toda la lógica de negocio completa.
 * 
 * CUÁNDO USAR:
 * - Para aprobar pagos: descuenta stock automáticamente y valida disponibilidad
 * - Para rechazar/cancelar pagos aprobados: restaura stock automáticamente
 * - Cuando necesitas gestión completa de stock y estados de pedido
 * 
 * CUÁNDO NO USAR:
 * - Para cambios de estado simples sin afectar stock (usar actualizarEstadoPago() en su lugar)
 * - Cuando el pedido ya está cancelado y solo necesitas actualizar el pago (usar actualizarEstadoPago())
 * 
 * DIFERENCIAS CON actualizarEstadoPago():
 * - actualizarEstadoPagoConPedido(): Descuenta stock al aprobar, valida stock disponible, gestiona estados completos
 * - actualizarEstadoPago(): NO descuenta stock al aprobar, solo actualiza estados
 * 
 * Esta función centraliza la lógica de negocio para transiciones de estado de pago → estado de pedido
 * según las reglas definidas en el plan de lógica de negocio.
 * 
 * REGLAS IMPLEMENTADAS:
 * - Si pago se aprueba → valida stock, descuenta stock, pedido pasa a 'preparacion'
 * - Si pago se rechaza/cancela → restaura stock si fue descontado, pedido pasa a 'cancelado'
 * - VALIDACIÓN: No permite cancelar pagos en recorrido activo (pendiente_aprobacion, aprobado)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago
 * @param string $nuevo_estado_pago Nuevo estado del pago
 * @param string|null $motivo_rechazo Motivo del rechazo (opcional)
 * @param int|null $id_usuario ID del usuario que realiza la acción (opcional)
 * @return bool True si se actualizó correctamente
 * @throws Exception Si hay error en la actualización o stock insuficiente
 */
function actualizarEstadoPagoConPedido($mysqli, $id_pago, $nuevo_estado_pago, $motivo_rechazo = null, $id_usuario = null) {
    // Cargar función de normalización
    require_once __DIR__ . '/../estado_helpers.php';
    
    // Normalizar estado antes de validar
    $nuevo_estado_pago = normalizarEstado($nuevo_estado_pago);
    
    // Validar estados válidos
    $estados_validos = ['pendiente', 'pendiente_aprobacion', 'aprobado', 'rechazado', 'cancelado'];
    if (!in_array($nuevo_estado_pago, $estados_validos)) {
        throw new Exception('Estado de pago inválido: ' . $nuevo_estado_pago);
    }
    
    // Obtener datos actuales del pago
    $pago_actual = obtenerPagoPorId($mysqli, $id_pago);
    if (!$pago_actual) {
        throw new Exception('Pago no encontrado con ID: ' . $id_pago);
    }
    
    // Normalizar estado anterior
    $estado_pago_anterior = normalizarEstado($pago_actual['estado_pago']);
    $id_pedido = intval($pago_actual['id_pedido']);
    
    // Si no cambió el estado, no hacer nada
    if ($estado_pago_anterior === $nuevo_estado_pago) {
        return true;
    }
    
    // VALIDACIÓN TEMPRANA: Verificar que la transición esté permitida según la matriz de transiciones
    // Esta validación previene transiciones inválidas antes de ejecutar cualquier lógica de negocio
    try {
        validarTransicionPago($estado_pago_anterior, $nuevo_estado_pago);
    } catch (Exception $e) {
        // Mejorar mensaje de error para que sea más claro
        throw new Exception('Transición de estado de pago no permitida: ' . $e->getMessage());
    }
    
    // VALIDACIÓN CRÍTICA: No permitir cancelar pagos que están en recorrido activo
    try {
        _validarCancelacionPagoRecorridoActivo($nuevo_estado_pago, $estado_pago_anterior);
    } catch (Exception $e) {
        // Mejorar mensaje de error para que sea más claro
        throw new Exception('No se puede cancelar el pago: ' . $e->getMessage());
    }
    
    // Cargar funciones necesarias
    require_once __DIR__ . '/../queries_helper.php';
    cargarArchivoQueries('pedido_queries', __DIR__);
    cargarArchivoQueries('stock_queries', __DIR__);
    
    // Iniciar transacción para atomicidad
    $mysqli->begin_transaction();
    
    try {
        // PASO 2: Obtener datos del pago (sin FOR UPDATE - usamos validaciones atómicas en UPDATE)
        $sql_pago = "
            SELECT 
                p.id_pago,
                p.id_pedido,
                p.id_forma_pago,
                p.numero_transaccion,
                p.estado_pago,
                p.monto,
                p.fecha_pago,
                p.fecha_aprobacion,
                p.motivo_rechazo,
                p.fecha_actualizacion
            FROM Pagos p
            WHERE p.id_pago = ?
        ";
        
        $stmt_pago = $mysqli->prepare($sql_pago);
        if (!$stmt_pago) {
            throw new Exception('Error al preparar consulta de pago: ' . $mysqli->error);
        }
        $stmt_pago->bind_param('i', $id_pago);
        if (!$stmt_pago->execute()) {
            $error_msg = $stmt_pago->error;
            $stmt_pago->close();
            throw new Exception('Error al obtener pago: ' . $error_msg);
        }
        $result_pago = $stmt_pago->get_result();
        $pago_bloqueado = $result_pago->fetch_assoc();
        $stmt_pago->close();
        
        if (!$pago_bloqueado) {
            throw new Exception('Pago no encontrado con ID: ' . $id_pago);
        }
        
        // Verificar que el estado no haya cambiado desde que lo leímos (race condition check)
        if ($pago_bloqueado['estado_pago'] !== $estado_pago_anterior) {
            throw new Exception('El estado del pago ha cambiado durante la transacción');
        }
        
        // Obtener datos del pedido (sin FOR UPDATE - usamos validaciones atómicas en UPDATE)
        $sql_pedido = "
            SELECT 
                p.id_pedido,
                p.fecha_pedido,
                p.estado_pedido,
                p.id_usuario,
                p.direccion_entrega,
                p.telefono_contacto,
                p.observaciones,
                p.total,
                p.fecha_actualizacion
            FROM Pedidos p
            WHERE p.id_pedido = ?
        ";
        
        $stmt_pedido = $mysqli->prepare($sql_pedido);
        if (!$stmt_pedido) {
            throw new Exception('Error al preparar consulta de pedido: ' . $mysqli->error);
        }
        $stmt_pedido->bind_param('i', $id_pedido);
        if (!$stmt_pedido->execute()) {
            $error_msg = $stmt_pedido->error;
            $stmt_pedido->close();
            throw new Exception('Error al obtener pedido: ' . $error_msg);
        }
        $result_pedido = $stmt_pedido->get_result();
        $pedido_bloqueado = $result_pedido->fetch_assoc();
        $stmt_pedido->close();
        
        if (!$pedido_bloqueado) {
            throw new Exception('Pedido no encontrado con ID: ' . $id_pedido);
        }
        
        $estado_pedido_actual = $pedido_bloqueado['estado_pedido'];
        // Normalizar estado: si está vacío o NULL, usar 'pendiente' como valor por defecto
        if (empty($estado_pedido_actual)) {
            $estado_pedido_actual = 'pendiente';
        }
        $id_usuario_pedido = intval($pedido_bloqueado['id_usuario']);
        
        // REGLA 1: Cuando el PAGO se APRUEBA
        if ($nuevo_estado_pago === 'aprobado' && $estado_pago_anterior !== 'aprobado') {
            _aprobarPagoConValidaciones($mysqli, $id_pago, $pago_bloqueado, $id_pedido, $estado_pedido_actual, $id_usuario_pedido);
        }
        // REGLA 2: Cuando el PAGO se RECHAZA o CANCELA
        elseif (in_array($nuevo_estado_pago, ['rechazado', 'cancelado']) 
                && $estado_pago_anterior !== $nuevo_estado_pago) {
            _rechazarOCancelarPago($mysqli, $id_pago, $nuevo_estado_pago, $motivo_rechazo, $id_pedido, $estado_pago_anterior, $estado_pedido_actual, $id_usuario);
        }
        // REGLA 3: Otros cambios de estado (pendiente, pendiente_aprobacion)
        // No afectan el estado del pedido automáticamente
        else {
            // Actualizar solo el estado del pago sin afectar el pedido
            $fecha_aprobacion = null;
            if ($nuevo_estado_pago === 'aprobado') {
                $fecha_aprobacion = date('Y-m-d H:i:s');
            }
            
            $sql_actualizar_pago = "
                UPDATE Pagos 
                SET estado_pago = ?,
                    motivo_rechazo = ?,
                    fecha_aprobacion = ?,
                    fecha_actualizacion = NOW()
                WHERE id_pago = ?
            ";
            
            $stmt_actualizar_pago = $mysqli->prepare($sql_actualizar_pago);
            if (!$stmt_actualizar_pago) {
                throw new Exception('Error al preparar actualización de pago: ' . $mysqli->error);
            }
            $stmt_actualizar_pago->bind_param('sssi', $nuevo_estado_pago, $motivo_rechazo, $fecha_aprobacion, $id_pago);
            if (!$stmt_actualizar_pago->execute()) {
                $error_msg = $stmt_actualizar_pago->error;
                $stmt_actualizar_pago->close();
                throw new Exception('Error al actualizar estado del pago: ' . $error_msg);
            }
            $stmt_actualizar_pago->close();
        }
        
        $mysqli->commit();
        return true;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Error en actualizarEstadoPagoConPedido: " . $e->getMessage());
        throw $e;
    }
}



