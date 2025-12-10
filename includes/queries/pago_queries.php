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

    // FIX: Validar que los parámetros sean válidos - SIEMPRE, no solo para aprobado
    if ($id_pedido <= 0) {
        error_log("crearPago: ID pedido inválido: $id_pedido");
        return 0;
    }

    if ($id_forma_pago <= 0) {
        error_log("crearPago: ID forma pago inválida: $id_forma_pago");
        return 0;
    }

    // FIX: Validar monto > 0 SIEMPRE (no solo para estado aprobado)
    if ($monto <= 0) {
        error_log("crearPago: Monto inválido ($monto) para pedido #{$id_pedido}");
        return 0;
    }

    // FIX: Validar que monto no sea excesivo (posible overflow o error)
    if ($monto > 999999.99) {
        error_log("crearPago: Monto excesivo ($monto) para pedido #{$id_pedido}");
        return 0;
    }
    
    // FIX: Prevenir pagos duplicados - verificar si ya existe pago activo
    $sql_verificar = "
        SELECT COUNT(*) as pagos_activos
        FROM Pagos
        WHERE id_pedido = ?
          AND estado_pago IN ('pendiente', 'pendiente_aprobacion', 'aprobado')
    ";

    $stmt_verificar = $mysqli->prepare($sql_verificar);
    if ($stmt_verificar) {
        $stmt_verificar->bind_param('i', $id_pedido);
        if ($stmt_verificar->execute()) {
            $result_verificar = $stmt_verificar->get_result();
            $verificacion = $result_verificar->fetch_assoc();
            $stmt_verificar->close();

            if ($verificacion && intval($verificacion['pagos_activos']) > 0) {
                error_log("crearPago: Ya existe un pago activo para pedido #{$id_pedido}");
                return 0;
            }
        } else {
            $stmt_verificar->close();
            error_log("crearPago: Error al verificar pagos activos: " . $mysqli->error);
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
 * Actualiza el estado de un pago
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
    
    // Iniciar transacción para atomicidad de operaciones
    $mysqli->begin_transaction();

    // Validar solo si se está aprobando un pago (reemplaza trg_validar_pago_unico_aprobado_update)
    if ($nuevo_estado === 'aprobado' && $estado_anterior !== 'aprobado') {
        // Validar monto > 0 cuando se aprueba
        if ($monto_actual <= 0) {
            $mysqli->rollback();
            return false; // No se puede aprobar un pago con monto menor o igual a cero
        }

        // Verificar si ya existe otro pago aprobado para este pedido (excluyendo el actual)
        // Sin FOR UPDATE - la validación de unicidad se hará en el UPDATE con WHERE
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
            throw new Exception('Error al preparar actualización de pago');
        }
        
        // Preparar valores para bind_param (null es válido para campos NULL en MySQL)
        $fecha_aprobacion_str = $fecha_aprobacion ?? null;
        $motivo_rechazo_str = $motivo_rechazo ?? null;
        
        // bind_param: 'sssi' = 4 parámetros: estado (s), fecha_aprobacion (s), motivo_rechazo (s), id_pago (i)
        $stmt->bind_param('sssi', $nuevo_estado, $fecha_aprobacion_str, $motivo_rechazo_str, $id_pago);
        $resultado = $stmt->execute();
        
        if (!$resultado) {
            $stmt->close();
            throw new Exception('Error al actualizar estado del pago');
        }
        
        // Verificar que la actualización realmente ocurrió
        $rows_affected = $stmt->affected_rows;
        $stmt->close();
        
        if ($rows_affected === 0) {
            throw new Exception('Error: No se pudo actualizar el estado del pago. El pago puede haber sido modificado por otro proceso o no existe.');
        }
        
        // Actualizar estado del pedido según el estado del pago (reemplaza trg_actualizar_pedido_por_pago)
        $pedido_queries_path = __DIR__ . '/pedido_queries.php';
        if (!file_exists($pedido_queries_path)) {
            error_log("ERROR: No se pudo encontrar pedido_queries.php en " . $pedido_queries_path);
            die("Error crítico: Archivo de consultas de pedido no encontrado. Por favor, contacta al administrador.");
        }
        require_once $pedido_queries_path;
        
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
                if (!$stmt_pedido->execute()) {
                    $stmt_pedido->close();
                    // No lanzar excepción aquí, solo loggear, ya que el pago ya se actualizó
                    error_log("Error al actualizar pedido a preparacion en actualizarEstadoPago: " . $stmt_pedido->error);
                } else {
                    // Verificar que la actualización realmente ocurrió
                    $rows_affected_pedido = $stmt_pedido->affected_rows;
                    $stmt_pedido->close();
                    if ($rows_affected_pedido === 0) {
                        error_log("Warning: No se pudo actualizar pedido #{$id_pedido} a preparacion. Puede que el pedido ya esté en otro estado.");
                    }
                }
            }
        }
        
        // Cuando el pago se rechaza o cancela, cambiar pedido a cancelado
        // REGLA: Si el pago se rechaza y el pedido está en pendiente o preparacion, cancelar automáticamente
        $email_enviado = false;
        if (in_array($nuevo_estado, ['rechazado', 'cancelado'])
            && $estado_anterior !== $nuevo_estado) {

            // Obtener estado actual del pedido
            $pedido_actual = obtenerPedidoPorId($mysqli, $id_pedido);
            $estado_pedido_actual = $pedido_actual ? strtolower(trim($pedido_actual['estado_pedido'] ?? '')) : '';
            $id_usuario_pedido = $pedido_actual ? intval($pedido_actual['id_usuario']) : null;

            // Cancelar el pedido SIEMPRE si está en pendiente o preparacion cuando el pago se rechaza
            // Seguir con la lógica de cuándo puede cancelar: solo pendiente y preparacion
            if ($estado_pedido_actual !== 'cancelado'
                && $estado_pedido_actual !== 'completado'
                && in_array($estado_pedido_actual, ['pendiente', 'pendiente_validado_stock', 'preparacion'])) {
                $sql_pedido = "
                    UPDATE Pedidos
                    SET estado_pedido = 'cancelado',
                        fecha_actualizacion = NOW()
                    WHERE id_pedido = ?
                ";

                $stmt_pedido = $mysqli->prepare($sql_pedido);
                if ($stmt_pedido) {
                    $stmt_pedido->bind_param('i', $id_pedido);
                    $stmt_pedido->execute();
                    $stmt_pedido->close();

                    // Enviar email si se canceló el pedido por pago rechazado
                    if ($nuevo_estado === 'rechazado' && $id_usuario_pedido) {
                        // Cargar función de email si no está cargada
                        if (!function_exists('enviar_email_pedido_cancelado_o_rechazado')) {
                            require_once __DIR__ . '/../email_gmail_functions.php';
                        }

                        // Enviar email de notificación de pago rechazado
                        try {
                            enviar_email_pedido_cancelado_o_rechazado($id_pedido, $id_usuario_pedido, 'rechazado', $motivo_rechazo, $mysqli);
                            $email_enviado = true;
                        } catch (Exception $e) {
                            error_log("Error al enviar email de rechazo para pedido #$id_pedido: " . $e->getMessage());
                        }
                    }
                }
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
 * Aprueba un pago y descuenta el stock del pedido asociado
 *
 * Esta función usa actualizarEstadoPagoConPedido() que es la función RECOMENDADA
 * para aprobar pagos porque maneja toda la lógica de negocio completa:
 * - Valida stock disponible antes de aprobar
 * - Descuenta stock automáticamente
 * - Actualiza estado del pedido a 'preparacion'
 * - Maneja transacciones y rollback en caso de error
 *
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago
 * @param int $id_usuario ID del usuario que aprueba (opcional)
 * @return bool True si se aprobó correctamente
 */
function aprobarPago($mysqli, $id_pago, $id_usuario = null) {
    // Obtener datos del pago
    $pago = obtenerPagoPorId($mysqli, $id_pago);

    if (!$pago) {
        throw new Exception("No se encontró el pago con ID: $id_pago");
    }

    // Si ya está aprobado, no hacer nada
    if ($pago['estado_pago'] === 'aprobado') {
        return true;
    }

    // Usar actualizarEstadoPagoConPedido() que es la función RECOMENDADA
    // para aprobar pagos porque maneja toda la lógica de negocio completa
    $resultado = actualizarEstadoPagoConPedido($mysqli, $id_pago, 'aprobado', null, $id_usuario);

    if (!$resultado) {
        throw new Exception("No se pudo actualizar el estado del pago. Por favor, verifica los logs del sistema para más detalles.");
    }

    return $resultado;
}

/**
 * Actualiza un pago completo con todos sus campos editables
 * Reemplaza la funcionalidad de los triggers:
 * - trg_validar_pago_unico_aprobado_update: valida monto > 0 y pago único aprobado
 * - trg_actualizar_pedido_por_pago: actualiza estado del pedido cuando se aprueba/rechaza
 * 
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
    
    // Validar número de transacción: máximo 100 caracteres (sin mínimo)
    if ($numero_transaccion !== null) {
        $numero_transaccion = trim($numero_transaccion);
        if (!empty($numero_transaccion)) {
            $longitud = strlen($numero_transaccion);
            if ($longitud > 100) {
                throw new Exception('El número de transacción no puede exceder 100 caracteres.');
            }
            // Validar caracteres permitidos según diccionario: [A-Z, a-z, 0-9, -, _]
            if (!preg_match('/^[A-Za-z0-9\-_]+$/', $numero_transaccion)) {
                throw new Exception('El número de transacción solo puede contener letras, números, guiones y guiones bajos.');
            }
        }
    }
    
    // Validar motivo de rechazo según diccionario: 0-500 caracteres (opcional)
    if ($motivo_rechazo !== null) {
        $motivo_rechazo = trim($motivo_rechazo);
        if (!empty($motivo_rechazo)) {
            if (strlen($motivo_rechazo) > 500) {
                throw new Exception('El motivo de rechazo no puede exceder 500 caracteres.');
            }
        }
    }
    
    // Obtener datos actuales del pago para validaciones
    $pago_actual = obtenerPagoPorId($mysqli, $id_pago);
    if (!$pago_actual) {
        throw new Exception('Pago no encontrado con ID: ' . $id_pago);
    }
    
    $estado_anterior = $pago_actual['estado_pago'];
    $id_pedido = intval($pago_actual['id_pedido']);
    
    // Validar transición de estado permitida (previene cambios inválidos)
    if ($estado_pago !== $estado_anterior) {
        validarTransicionPago($estado_anterior, $estado_pago);
    }
    
    // Iniciar transacción para atomicidad
    // Manejo de deadlocks: máximo 3 intentos
    $max_intentos = 3;
    $intento = 0;
    $exito = false;
    
    while ($intento < $max_intentos && !$exito) {
        $intento++;
        try {
            $mysqli->begin_transaction();
            
            // Verificar que el estado no haya cambiado desde que lo leímos (race condition check)
            // Re-leer el estado dentro de la transacción para detectar cambios concurrentes
            // Sin FOR UPDATE - el UPDATE final validará el estado
            $sql_verificar_estado = "SELECT estado_pago FROM Pagos WHERE id_pago = ?";
            $stmt_verificar_estado = $mysqli->prepare($sql_verificar_estado);
            if (!$stmt_verificar_estado) {
                throw new Exception('Error al preparar verificación de estado: ' . $mysqli->error);
            }
            $stmt_verificar_estado->bind_param('i', $id_pago);
            if (!$stmt_verificar_estado->execute()) {
                $error_msg = $stmt_verificar_estado->error;
                $stmt_verificar_estado->close();
                throw new Exception('Error al verificar estado del pago: ' . $error_msg);
            }
            $result_verificar_estado = $stmt_verificar_estado->get_result();
            $pago_verificado = $result_verificar_estado->fetch_assoc();
            $stmt_verificar_estado->close();
            
            if (!$pago_verificado) {
                $mysqli->rollback();
                throw new Exception('Pago no encontrado con ID: ' . $id_pago);
            }
            
            $estado_actual_verificado = normalizarEstado($pago_verificado['estado_pago']);
            if ($estado_actual_verificado !== $estado_anterior) {
                $mysqli->rollback();
                throw new Exception('El estado del pago ha cambiado durante la transacción. Estado anterior: ' . $estado_anterior . ', Estado actual: ' . $estado_actual_verificado);
            }
            
            // Validar monto > 0 cuando se aprueba
            if ($estado_pago === 'aprobado' && $estado_anterior !== 'aprobado') {
                if ($monto <= 0) {
                    $mysqli->rollback();
                    throw new Exception('No se puede aprobar un pago con monto menor o igual a cero');
                }
            }
            
            // Si se aprueba, establecer fecha_aprobacion si no está establecida
            if ($estado_pago === 'aprobado') {
                $fecha_aprobacion = date('Y-m-d H:i:s');
            }
            
            // Validar solo si se está aprobando un pago (reemplaza trg_validar_pago_unico_aprobado_update)
            if ($estado_pago === 'aprobado' && $estado_anterior !== 'aprobado') {
                // Verificar si ya existe otro pago aprobado para este pedido (excluyendo el actual)
                // Sin FOR UPDATE - SELECT simple para verificar antes del UPDATE
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
                throw new Exception('Error al ejecutar verificación de pagos aprobados: ' . $error_msg);
            }
            
            $result_verificar = $stmt_verificar->get_result();
            $verificacion = $result_verificar->fetch_assoc();
            $stmt_verificar->close();
            
                if ($verificacion && intval($verificacion['pagos_aprobados']) > 0) {
                    throw new Exception('Ya existe otro pago aprobado para este pedido');
                }
            }
            
            // Si se está aprobando el pago, validar stock ANTES de aprobar
            if ($estado_pago === 'aprobado' && $estado_anterior !== 'aprobado') {
                $stock_queries_path = __DIR__ . '/stock_queries.php';
                if (!file_exists($stock_queries_path)) {
                    error_log("ERROR: No se pudo encontrar stock_queries.php en " . $stock_queries_path);
                    die("Error crítico: Archivo de consultas de stock no encontrado. Por favor, contacta al administrador.");
                }
                require_once $stock_queries_path;
                
                // ESTRATEGIA DE MÚLTIPLES QUERIES SIMPLES:
                // Query 1: Obtener detalles básicos del pedido (solo id_variante y cantidad)
                // Sin FOR UPDATE - SELECT simple para obtener datos
                $sql_detalles = "
                    SELECT id_variante, cantidad
                    FROM Detalle_Pedido
                    WHERE id_pedido = ?
                ";
                
                $stmt_detalles = $mysqli->prepare($sql_detalles);
                if (!$stmt_detalles) {
                    throw new Exception('Error al preparar validación de stock: ' . $mysqli->error);
                }
                
                $stmt_detalles->bind_param('i', $id_pedido);
                if (!$stmt_detalles->execute()) {
                    $error_msg = $stmt_detalles->error;
                    $stmt_detalles->close();
                    throw new Exception('Error al ejecutar validación de stock: ' . $error_msg);
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
                
                // Validar stock por variante en loop PHP (queries simples separadas)
                $errores_stock = [];
                foreach ($detalles_pedido as $detalle) {
                $id_variante = $detalle['id_variante'];
                $cantidad_solicitada = $detalle['cantidad'];
                
                // Query 2: Obtener información de stock y estado de variante/producto
                // Sin FOR UPDATE - SELECT simple para validar antes del UPDATE
                $sql_variante = "
                    SELECT
                        sv.stock,
                        sv.activo as variante_activa,
                        sv.talle,
                        sv.color,
                        p.activo as producto_activo,
                        p.nombre_producto
                    FROM Stock_Variantes sv
                    INNER JOIN Productos p ON sv.id_producto = p.id_producto
                    WHERE sv.id_variante = ?
                ";
                
                $stmt_variante = $mysqli->prepare($sql_variante);
                if (!$stmt_variante) {
                    $errores_stock[] = "Error al validar variante #{$id_variante}";
                    continue;
                }
                
                $stmt_variante->bind_param('i', $id_variante);
                if (!$stmt_variante->execute()) {
                    $stmt_variante->close();
                    $errores_stock[] = "Error al validar variante #{$id_variante}";
                    continue;
                }
                
                $result_variante = $stmt_variante->get_result();
                $variante_data = $result_variante->fetch_assoc();
                $stmt_variante->close();
                
                if (!$variante_data) {
                    $errores_stock[] = "La variante #{$id_variante} no existe";
                    continue;
                }
                
                $stock_disponible = intval($variante_data['stock']);
                $variante_activa = intval($variante_data['variante_activa']);
                $producto_activo = intval($variante_data['producto_activo']);
                $talle = $variante_data['talle'];
                $color = $variante_data['color'];
                $nombre_producto = $variante_data['nombre_producto'];
                
                // Validar que variante y producto estén activos
                if ($variante_activa === 0) {
                    $errores_stock[] = "La variante {$talle} {$color} del producto {$nombre_producto} está inactiva";
                } elseif ($producto_activo === 0) {
                    $errores_stock[] = "El producto {$nombre_producto} está inactivo";
                } elseif ($stock_disponible < $cantidad_solicitada) {
                    $errores_stock[] = "Stock insuficiente para {$nombre_producto} (Talla: {$talle}, Color: {$color}). Disponible: {$stock_disponible}, Solicitado: {$cantidad_solicitada}";
                }
            }
            
            // Si hay errores de stock, lanzar excepción ANTES de aprobar el pago
            if (!empty($errores_stock)) {
                throw new Exception('STOCK_INSUFICIENTE: ' . implode('; ', $errores_stock));
            }
        }


        // Actualizar pago completo
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
            throw new Exception('Error al preparar actualización de pago');
        }

        $stmt->bind_param('sdsssi', $estado_pago, $monto, $numero_transaccion, $fecha_aprobacion, $motivo_rechazo, $id_pago);
        if (!$stmt->execute()) {
            $error_msg = $stmt->error;
            $stmt->close();
            throw new Exception('Error al actualizar pago: ' . $error_msg);
        }
        $stmt->close();
        
        // Actualizar estado del pedido según el estado del pago (reemplaza trg_actualizar_pedido_por_pago)
        $pedido_queries_path = __DIR__ . '/pedido_queries.php';
        if (!file_exists($pedido_queries_path)) {
            error_log("ERROR: No se pudo encontrar pedido_queries.php en " . $pedido_queries_path);
            die("Error crítico: Archivo de consultas de pedido no encontrado. Por favor, contacta al administrador.");
        }
        require_once $pedido_queries_path;
        
        // Cuando el pago se aprueba, cambiar pedido a preparacion y descontar stock
        if ($estado_pago === 'aprobado' && $estado_anterior !== 'aprobado') {
            // Descontar stock del pedido (ya validado anteriormente, dentro de la misma transacción)
            $stock_queries_path = __DIR__ . '/stock_queries.php';
            if (!file_exists($stock_queries_path)) {
                error_log("ERROR: No se pudo encontrar stock_queries.php en " . $stock_queries_path);
                die("Error crítico: Archivo de consultas de stock no encontrado. Por favor, contacta al administrador.");
            }
            require_once $stock_queries_path;
            // Obtener id_usuario del pedido para registrar en el movimiento de stock
            $sql_pedido_usuario = "SELECT id_usuario FROM Pedidos WHERE id_pedido = ?";
            $stmt_pedido_usuario = $mysqli->prepare($sql_pedido_usuario);
            $id_usuario_pago = null;
            if (!$stmt_pedido_usuario) {
                throw new Exception('Error al preparar consulta de usuario del pedido: ' . $mysqli->error);
            }
            $stmt_pedido_usuario->bind_param('i', $id_pedido);
            if (!$stmt_pedido_usuario->execute()) {
                $error_msg = $stmt_pedido_usuario->error;
                $stmt_pedido_usuario->close();
                throw new Exception('Error al obtener usuario del pedido: ' . $error_msg);
            }
            $result_pedido_usuario = $stmt_pedido_usuario->get_result();
            $pedido_data = $result_pedido_usuario->fetch_assoc();
            $stmt_pedido_usuario->close();
            if ($pedido_data) {
                $id_usuario_pago = intval($pedido_data['id_usuario']);
            }
            try {
                if (!descontarStockPedido($mysqli, $id_pedido, $id_usuario_pago, true)) {
                    throw new Exception('Error al descontar stock del pedido');
                }
            } catch (Exception $e) {
                // Re-lanzar excepción para que se haga rollback de toda la transacción
                throw $e;
            }
            
            // Actualizar estado del pedido a 'preparacion' usando función centralizada
            // Solo si el estado actual es 'pendiente', 'preparacion' o vacío (NULL/'')
            $pedido_queries_path = __DIR__ . '/pedido_queries.php';
            if (file_exists($pedido_queries_path)) {
                require_once $pedido_queries_path;
            }

            // Permitir estado vacío tratándolo como 'pendiente' (datos legacy)
            $estados_permitidos = ['pendiente', 'preparacion', '', null];
            if (!actualizarEstadoPedidoConValidacion($mysqli, $id_pedido, 'preparacion', $estados_permitidos)) {
                throw new Exception('Error al actualizar estado del pedido: el estado actual no permite esta actualización');
            }
        }
        
        // Cuando el pago se rechaza o cancela, cambiar pedido a cancelado
        if (in_array($estado_pago, ['rechazado', 'cancelado']) 
            && $estado_anterior !== $estado_pago 
            && in_array($estado_anterior, ['pendiente', 'pendiente_aprobacion', 'preparacion'])) {
            $sql_pedido = "
                UPDATE Pedidos 
                SET estado_pedido = 'cancelado',
                    fecha_actualizacion = NOW()
                WHERE id_pedido = ?
                  AND estado_pedido IN ('pendiente', 'preparacion')
            ";
            
            $stmt_pedido = $mysqli->prepare($sql_pedido);
            if ($stmt_pedido) {
                $stmt_pedido->bind_param('i', $id_pedido);
                if (!$stmt_pedido->execute()) {
                    // Loggear error pero no lanzar excepción ya que es opcional
                    error_log("Error al actualizar estado del pedido a cancelado: " . $stmt_pedido->error);
                }
                $stmt_pedido->close();
            }
        }
        
        $mysqli->commit();
        $exito = true;
        return true;
        
        } catch (Exception $e) {
            $mysqli->rollback();
            
            // Detectar deadlock (código de error 1213 en MySQL)
            $es_deadlock = ($mysqli->errno === 1213 || strpos($e->getMessage(), 'Deadlock') !== false || strpos($e->getMessage(), 'Lock wait timeout') !== false);
            
            if ($es_deadlock && $intento < $max_intentos) {
                // Esperar un tiempo aleatorio antes de reintentar (backoff exponencial)
                $espera = pow(2, $intento) * 100000; // microsegundos: 200ms, 400ms, 800ms
                usleep($espera);
                error_log("Deadlock detectado en actualizarPagoCompleto, reintento {$intento}/{$max_intentos}");
                continue; // Reintentar
            }
            
            // Si no es deadlock o se agotaron los intentos, lanzar excepción
            error_log("Error en actualizarPagoCompleto: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Si llegamos aquí sin éxito, lanzar excepción
    if (!$exito) {
        throw new Exception('Error al actualizar pago después de ' . $max_intentos . ' intentos. Puede haber un deadlock persistente.');
    }
    
    return true;
}

/**
 * Rechaza un pago y revierte el stock si ya había sido descontado
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago
 * @param int $id_usuario ID del usuario que rechaza (opcional)
 * @param string $motivo Motivo del rechazo (opcional)
 * @return bool True si se rechazó correctamente
 */
function rechazarPago($mysqli, $id_pago, $id_usuario = null, $motivo = null) {
    // Obtener datos del pago
    $pago = obtenerPagoPorId($mysqli, $id_pago);
    
    if (!$pago) {
        return false;
    }
    
    $mysqli->begin_transaction();
    
    try {
        // Actualizar estado del pago
        if (!actualizarEstadoPago($mysqli, $id_pago, 'rechazado')) {
            throw new Exception('Error al actualizar estado del pago');
        }
        
        // Revertir stock si ya había sido descontado
        $stock_queries_path = __DIR__ . '/stock_queries.php';
        if (!file_exists($stock_queries_path)) {
            error_log("ERROR: No se pudo encontrar stock_queries.php en " . $stock_queries_path);
            die("Error crítico: Archivo de consultas de stock no encontrado. Por favor, contacta al administrador.");
        }
        require_once $stock_queries_path;
        revertirStockPedido($mysqli, $pago['id_pedido'], $id_usuario, $motivo ? "Pago rechazado: {$motivo}" : "Pago rechazado");
        
        $mysqli->commit();
        return true;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Error al rechazar pago #{$id_pago}: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene pagos asociados a múltiples pedidos
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $pedidos_ids Array de IDs de pedidos
 * @return array Array asociativo donde la clave es id_pedido y el valor es el pago
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
    // Cargar funciones de estado si no están cargadas
    require_once __DIR__ . '/../state_functions.php';

    // Validar transición de pago
    $puede_transicionar = puedeTransicionarPago($estado_actual, $nuevo_estado);

    // Si no puede transicionar, lanzar excepción
    if (!$puede_transicionar) {
        $transiciones = obtenerTransicionesPagoValidas();
        $estados_permitidos = $transiciones[$estado_actual] ?? [];
        $estados_permitidos_str = empty($estados_permitidos) ? 'ninguno (estado terminal)' : implode(', ', $estados_permitidos);

        throw new Exception("Transición no permitida: No se puede cambiar de '$estado_actual' a '$nuevo_estado'. Transiciones permitidas desde '$estado_actual': $estados_permitidos_str");
    }

    return true;
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
    // Cargar funciones de estado si no están cargadas
    require_once __DIR__ . '/../state_functions.php';

    // Normalizar estados
    $nuevo_estado_norm = strtolower(trim($nuevo_estado_pago));
    $estado_anterior_norm = strtolower(trim($estado_pago_anterior));

    // Si se intenta cancelar y está en recorrido activo, lanzar excepción
    if ($nuevo_estado_norm === 'cancelado' && estaEnRecorridoActivo($estado_anterior_norm, 'pago')) {
        throw new Exception("No se puede cancelar un pago que está en recorrido activo. Estado actual: {$estado_anterior_norm}. Un pago en recorrido activo no puede cancelarse.");
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
    
    // Cargar funciones de estado si no están cargadas
    require_once __DIR__ . '/../state_functions.php';

    // Solo validar si el pago estaba aprobado
    if ($estado_pago_anterior !== 'aprobado') {
        return;
    }

    // Validar estados terminales del pedido
    if (esEstadoTerminal($estado_pedido_actual, 'pedido')) {
        $info_pedido = obtenerInfoEstadoPedido($estado_pedido_actual);
        throw new Exception("No se puede cancelar o rechazar un pago aprobado cuando el pedido está en estado '{$info_pedido['nombre']}'. Un pedido en estado terminal es una venta cerrada.");
    }
    
    // Validar estados avanzados (en_viaje es un caso especial)
    if ($estado_pedido_actual === 'en_viaje') {
        throw new Exception("No se puede cancelar/rechazar un pago aprobado cuando el pedido está en estado 'en viaje'. El pedido ya fue procesado.");
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
    
    // Validar que el pedido existe (integridad referencial)
    $sql_verificar_pedido = "SELECT id_pedido FROM Pedidos WHERE id_pedido = ?";
    $stmt_verificar_pedido = $mysqli->prepare($sql_verificar_pedido);
    if (!$stmt_verificar_pedido) {
        throw new Exception('Error al preparar verificación de pedido: ' . $mysqli->error);
    }
    $stmt_verificar_pedido->bind_param('i', $id_pedido);
    if (!$stmt_verificar_pedido->execute()) {
        $error_msg = $stmt_verificar_pedido->error;
        $stmt_verificar_pedido->close();
        throw new Exception('Error al verificar existencia del pedido: ' . $error_msg);
    }
    $result_verificar_pedido = $stmt_verificar_pedido->get_result();
    $pedido_existe = $result_verificar_pedido->fetch_assoc();
    $stmt_verificar_pedido->close();
    
    if (!$pedido_existe) {
        throw new Exception('El pedido asociado al pago no existe. ID pedido: ' . $id_pedido);
    }
    
    // Validar monto > 0
    $monto = floatval($pago_bloqueado['monto']);
    if ($monto <= 0) {
        throw new Exception('No se puede aprobar un pago con monto menor o igual a cero');
    }

    require_once __DIR__ . '/../estado_helpers.php';
    $estado_pedido_normalizado = normalizarEstado($estado_pedido_actual);

    // FIX: Prevenir aprobación de pagos en estados terminales
    if (in_array($estado_pedido_normalizado, ['cancelado', 'completado', 'devolucion'])) {
        throw new Exception("No se puede aprobar el pago. El pedido está en estado terminal '{$estado_pedido_actual}'. Los pedidos cancelados, completados o devueltos no pueden modificarse.");
    }

    // FIX: Prevenir doble descuento - verificar si reservas fueron liberadas
    $info_reservas = verificarSiPedidoTuvoReservas($mysqli, $id_pedido);

    if ($info_reservas['reservas_liberadas'] > 0 && $info_reservas['reservas_activas'] === 0) {
        // Caso: Reservas fueron liberadas por expiración (> 24h)
        throw new Exception("No se puede aprobar el pago. El pedido #{$id_pedido} tuvo reservas que fueron liberadas por expiración. El cliente debe crear un nuevo pedido.");
    }

    // Permitir 'preparacion' solo para corrección manual de inconsistencias
    if (!in_array($estado_pedido_normalizado, ['pendiente', 'pendiente_validado_stock', 'preparacion'])) {
        throw new Exception("No se puede aprobar el pago. El pedido está en estado '{$estado_pedido_actual}'. Solo se pueden aprobar pagos de pedidos en estado 'pendiente', 'pendiente_validado_stock' o 'preparacion'.");
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
        throw new Exception('Ya existe otro pago aprobado para este pedido');
    }
    
    // Obtener Detalle_Pedido del pedido
    // Sin FOR UPDATE - SELECT simple para obtener datos
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
    $detalles = [];
    while ($row = $result_detalles->fetch_assoc()) {
        $detalles[] = $row;
    }
    $stmt_detalles->close();
    
    if (empty($detalles)) {
        throw new Exception('El pedido no tiene detalles para procesar. No se puede aprobar un pago de un pedido sin productos.');
    }
    
    // Validar stock disponible para cada item
    $errores_stock = [];
    foreach ($detalles as $detalle) {
        $id_variante = intval($detalle['id_variante']);
        $cantidad_requerida = intval($detalle['cantidad']);
        
        // Obtener stock disponible
        // Sin FOR UPDATE - SELECT simple para validar antes del UPDATE atómico
        $sql_stock = "
            SELECT stock
            FROM Stock_Variantes
            WHERE id_variante = ?
        ";
        
        $stmt_stock = $mysqli->prepare($sql_stock);
        if ($stmt_stock) {
            $stmt_stock->bind_param('i', $id_variante);
            $stmt_stock->execute();
            $result_stock = $stmt_stock->get_result();
            $stock_data = $result_stock->fetch_assoc();
            $stmt_stock->close();
            
            $stock_disponible = intval($stock_data['stock'] ?? 0);
            
            if ($stock_disponible < $cantidad_requerida) {
                $errores_stock[] = "Variante #{$id_variante}: requiere {$cantidad_requerida}, disponible {$stock_disponible}";
            }
        }
    }
    
    // Si hay errores de stock, rechazar el pago automáticamente
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
        
        throw new Exception('STOCK_INSUFICIENTE: ' . implode('; ', $errores_stock));
    }
    
    // Verificar que no haya ventas previas para este pedido (guardrail)
    $hay_ventas_previas = verificarVentasPreviasPedido($mysqli, $id_pedido);
    if ($hay_ventas_previas > 0) {
        error_log("Intento de descontar stock duplicado para pedido #{$id_pedido}. Ya existen {$hay_ventas_previas} movimientos tipo 'venta'.");
        throw new Exception('VENTA_YA_DESCONTADA_PARA_PEDIDO: Ya se descontó stock para este pedido. No se puede descontar nuevamente.');
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
    // Verificar que la actualización realmente ocurrió
    $rows_affected_pago = $stmt_actualizar_pago->affected_rows;
    $stmt_actualizar_pago->close();
    
    if ($rows_affected_pago === 0) {
        throw new Exception('Error: No se pudo actualizar el estado del pago. El pago puede haber sido modificado por otro proceso.');
    }
    
    // Actualizar pedido a preparacion
    $sql_actualizar_pedido = "
        UPDATE Pedidos 
        SET estado_pedido = 'preparacion',
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
    // Verificar que la actualización realmente ocurrió
    $rows_affected_pedido = $stmt_actualizar_pedido->affected_rows;
    $stmt_actualizar_pedido->close();
    
    if ($rows_affected_pedido === 0) {
        throw new Exception('Error: No se pudo actualizar el estado del pedido a preparacion. El pedido puede haber sido modificado por otro proceso.');
    }
}

/**
 * Procesa el rechazo o cancelación de un pago con validaciones y restauración de stock
 * 
 * Esta función extrae la lógica de rechazo/cancelación de actualizarEstadoPagoConPedido()
 * para reducir anidación y mejorar mantenibilidad.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago
 * @param string $nuevo_estado_pago Nuevo estado del pago (rechazado o cancelado)
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
    
    // Validar que el pedido existe (integridad referencial)
    $sql_verificar_pedido = "SELECT id_pedido FROM Pedidos WHERE id_pedido = ?";
    $stmt_verificar_pedido = $mysqli->prepare($sql_verificar_pedido);
    if (!$stmt_verificar_pedido) {
        throw new Exception('Error al preparar verificación de pedido: ' . $mysqli->error);
    }
    $stmt_verificar_pedido->bind_param('i', $id_pedido);
    if (!$stmt_verificar_pedido->execute()) {
        $error_msg = $stmt_verificar_pedido->error;
        $stmt_verificar_pedido->close();
        throw new Exception('Error al verificar existencia del pedido: ' . $error_msg);
    }
    $result_verificar_pedido = $stmt_verificar_pedido->get_result();
    $pedido_existe = $result_verificar_pedido->fetch_assoc();
    $stmt_verificar_pedido->close();
    
    if (!$pedido_existe) {
        throw new Exception('El pedido asociado al pago no existe. ID pedido: ' . $id_pedido);
    }
    
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
    // Verificar que la actualización realmente ocurrió
    $rows_affected_pago = $stmt_actualizar_pago->affected_rows;
    $stmt_actualizar_pago->close();
    
    if ($rows_affected_pago === 0) {
        throw new Exception('Error: No se pudo actualizar el estado del pago. El pago puede haber sido modificado por otro proceso.');
    }
    
    // Continuar con la lógica de cancelación solo si el pago se rechaza/cancela
    // REGLA: Si el pago se rechaza y el pedido está en pendiente o preparacion, cancelar automáticamente
    if (in_array($nuevo_estado_pago, ['rechazado', 'cancelado'])
        && $estado_pedido_actual !== 'cancelado'  
        && $estado_pedido_actual !== 'completado') {
    
        error_log("actualizarEstadoPagoConPedido: Cancelando pedido #{$id_pedido} debido a pago {$nuevo_estado_pago} (estado anterior: {$estado_pago_anterior}, estado pedido: {$estado_pedido_actual})");
        
        // Restaurar stock si había sido descontado (si el pago estaba aprobado)
        if ($estado_pago_anterior === 'aprobado') {
            error_log("actualizarEstadoPagoConPedido: Restaurando stock del pedido #{$id_pedido} porque el pago estaba aprobado");
            if (!revertirStockPedido($mysqli, $id_pedido, $id_usuario, "Pago " . $nuevo_estado_pago)) {
                throw new Exception('Error al restaurar stock del pedido');
            }
        }
        
        // Cancelar el pedido SIEMPRE si está en pendiente o preparacion cuando el pago se rechaza
        // Seguir con la lógica de cuándo puede cancelar: solo pendiente y preparacion
        if (in_array($estado_pedido_actual, ['pendiente', 'pendiente_validado_stock', 'preparacion'])) {
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
            // Verificar que la actualización realmente ocurrió
            $rows_affected_pedido = $stmt_actualizar_pedido->affected_rows;
            $stmt_actualizar_pedido->close();
            
            if ($rows_affected_pedido === 0) {
                error_log("actualizarEstadoPagoConPedido: No se pudo actualizar el pedido #{$id_pedido} a cancelado. El pedido puede haber sido modificado por otro proceso.");
                throw new Exception('Error: No se pudo actualizar el estado del pedido a cancelado. El pedido puede haber sido modificado por otro proceso.');
            }
            
            error_log("actualizarEstadoPagoConPedido: Pedido #{$id_pedido} cancelado exitosamente");
        } else {
            error_log("actualizarEstadoPagoConPedido: Pedido #{$id_pedido} en estado '{$estado_pedido_actual}' no puede cancelarse automáticamente al rechazar pago");
        }
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
    // Manejo de deadlocks: máximo 3 intentos
    $max_intentos = 3;
    $intento = 0;
    $exito = false;
    
    while ($intento < $max_intentos && !$exito) {
        $intento++;
        try {
            $mysqli->begin_transaction();
            
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
        } else {
            // Normalizar estado para asegurar formato consistente (minúsculas, sin espacios)
            require_once __DIR__ . '/../estado_helpers.php';
            $estado_pedido_actual = normalizarEstado($estado_pedido_actual);
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
            // Verificar que la actualización realmente ocurrió
            $rows_affected_pago = $stmt_actualizar_pago->affected_rows;
            $stmt_actualizar_pago->close();
            
            if ($rows_affected_pago === 0) {
                throw new Exception('Error: No se pudo actualizar el estado del pago. El pago puede haber sido modificado por otro proceso.');
            }
        }
        
        $mysqli->commit();
        $exito = true;
        return true;
        
        } catch (Exception $e) {
            $mysqli->rollback();
            
            // Detectar deadlock (código de error 1213 en MySQL)
            $es_deadlock = ($mysqli->errno === 1213 || strpos($e->getMessage(), 'Deadlock') !== false || strpos($e->getMessage(), 'Lock wait timeout') !== false);
            
            if ($es_deadlock && $intento < $max_intentos) {
                // Esperar un tiempo aleatorio antes de reintentar (backoff exponencial)
                $espera = pow(2, $intento) * 100000; // microsegundos: 200ms, 400ms, 800ms
                usleep($espera);
                error_log("Deadlock detectado en actualizarEstadoPagoConPedido, reintento {$intento}/{$max_intentos}");
                continue; // Reintentar
            }
            
            // Si no es deadlock o se agotaron los intentos, lanzar excepción
            error_log("Error en actualizarEstadoPagoConPedido: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Si llegamos aquí sin éxito, lanzar excepción
    if (!$exito) {
        throw new Exception('Error al actualizar estado de pago después de ' . $max_intentos . ' intentos. Puede haber un deadlock persistente.');
    }

    return true;
}

/**
 * Marca un pago como pagado por el cliente
 *
 * Esta función centraliza la lógica de cuando un cliente marca que ya pagó:
 * - Valida que el pago existe y pertenece al cliente
 * - Valida que el pago esté en estado "pendiente"
 * - Actualiza el pago a "pendiente_aprobacion" con número de transacción
 * - NO descuenta stock (eso lo hace ventas al aprobar)
 *
 * DIFERENCIA CON actualizarEstadoPagoConPedido():
 * - Esta función es para CLIENTES marcando que pagaron
 * - actualizarEstadoPagoConPedido() es para VENTAS aprobando/rechazando
 *
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago
 * @param int $id_usuario ID del cliente que marca el pago
 * @param string $numero_transaccion Código/número de transacción del pago
 * @return array Array con 'exito' => bool, 'mensaje' => string, 'mensaje_tipo' => string
 */
function marcarPagoPagadoPorCliente($mysqli, $id_pago, $id_usuario, $numero_transaccion) {
    // Cargar funciones necesarias
    if (!function_exists('normalizarEstado')) {
        require_once __DIR__ . '/../estado_helpers.php';
    }
    if (!function_exists('validarNumeroTransaccion')) {
        require_once __DIR__ . '/../validation_functions.php';
    }

    // VALIDACIÓN 1: ID de pago válido
    $id_pago = intval($id_pago);
    if ($id_pago <= 0) {
        return [
            'exito' => false,
            'mensaje' => 'ID de pago inválido',
            'mensaje_tipo' => 'danger'
        ];
    }

    // VALIDACIÓN 2: ID de usuario válido
    $id_usuario = intval($id_usuario);
    if ($id_usuario <= 0) {
        return [
            'exito' => false,
            'mensaje' => 'ID de usuario inválido',
            'mensaje_tipo' => 'danger'
        ];
    }

    // VALIDACIÓN 3: Número de transacción no vacío
    $numero_transaccion_raw = trim($numero_transaccion);
    if (empty($numero_transaccion_raw)) {
        return [
            'exito' => false,
            'mensaje' => 'El código de pago es requerido',
            'mensaje_tipo' => 'danger'
        ];
    }

    // VALIDACIÓN 4: Validar formato de número de transacción
    $validacion_numero = validarNumeroTransaccion($numero_transaccion_raw, 0, 100);
    if (!$validacion_numero['valido']) {
        return [
            'exito' => false,
            'mensaje' => $validacion_numero['error'],
            'mensaje_tipo' => 'danger'
        ];
    }
    $numero_transaccion_validado = $validacion_numero['valor'];

    // VALIDACIÓN 5: Obtener pago y verificar que existe
    $pago = obtenerPagoPorId($mysqli, $id_pago);
    if (!$pago) {
        return [
            'exito' => false,
            'mensaje' => 'Pago no encontrado',
            'mensaje_tipo' => 'danger'
        ];
    }

    // VALIDACIÓN 6: Verificar que el pago pertenece al usuario
    require_once __DIR__ . '/pedido_queries.php';
    $pedido = obtenerPedidoPorId($mysqli, $pago['id_pedido']);
    if (!$pedido || intval($pedido['id_usuario']) !== $id_usuario) {
        return [
            'exito' => false,
            'mensaje' => 'No tienes permiso para modificar este pago',
            'mensaje_tipo' => 'danger'
        ];
    }

    // VALIDACIÓN 7: Verificar que el pago esté en estado "pendiente"
    $estado_actual = normalizarEstado($pago['estado_pago']);
    if ($estado_actual !== 'pendiente') {
        // Si ya está en pendiente_aprobacion o aprobado, mostrar mensaje apropiado
        if ($estado_actual === 'pendiente_aprobacion') {
            return [
                'exito' => false,
                'mensaje' => 'Este pago ya fue marcado como pagado y está pendiente de aprobación',
                'mensaje_tipo' => 'warning'
            ];
        } elseif ($estado_actual === 'aprobado') {
            return [
                'exito' => false,
                'mensaje' => 'Este pago ya fue aprobado',
                'mensaje_tipo' => 'info'
            ];
        } elseif ($estado_actual === 'rechazado') {
            return [
                'exito' => false,
                'mensaje' => 'Este pago fue rechazado. Por favor, contacta con ventas',
                'mensaje_tipo' => 'warning'
            ];
        } elseif ($estado_actual === 'cancelado') {
            return [
                'exito' => false,
                'mensaje' => 'Este pago fue cancelado',
                'mensaje_tipo' => 'warning'
            ];
        } else {
            return [
                'exito' => false,
                'mensaje' => 'Solo se pueden marcar como pagados los pagos en estado pendiente',
                'mensaje_tipo' => 'danger'
            ];
        }
    }

    // FIX: VALIDACIÓN 7.5: Validar que el pedido NO esté en estado terminal
    require_once __DIR__ . '/../estado_helpers.php';
    $estado_pedido = normalizarEstado($pedido['estado_pedido']);

    if (in_array($estado_pedido, ['cancelado', 'completado', 'devolucion'])) {
        return [
            'exito' => false,
            'mensaje' => "No se puede marcar el pago como pagado. El pedido está {$estado_pedido}.",
            'mensaje_tipo' => 'danger'
        ];
    }

    // FIX: Validar monto > 0
    $monto = floatval($pago['monto']);
    if ($monto <= 0) {
        return [
            'exito' => false,
            'mensaje' => 'No se puede marcar como pagado un pago con monto inválido',
            'mensaje_tipo' => 'danger'
        ];
    }

    // VALIDACIÓN 8: Validar transición usando StateValidator
    try {
        validarTransicionPago($estado_actual, 'pendiente_aprobacion');
    } catch (Exception $e) {
        return [
            'exito' => false,
            'mensaje' => 'Transición de estado no permitida: ' . $e->getMessage(),
            'mensaje_tipo' => 'danger'
        ];
    }

    // ACTUALIZACIÓN: Marcar pago como pendiente_aprobacion con número de transacción
    // NOTA: NO se descuenta stock aquí, solo cuando ventas apruebe el pago
    try {
        $numero_transaccion_final = !empty($numero_transaccion_validado) ? $numero_transaccion_validado : null;

        // Usar actualizarPagoCompleto() que ya existe
        $resultado = actualizarPagoCompleto($mysqli, $id_pago, 'pendiente_aprobacion', $monto, $numero_transaccion_final);

        if (!$resultado) {
            throw new Exception('Error al actualizar el pago');
        }

        // Logging para auditoría
        $id_pedido = intval($pago['id_pedido']);
        error_log("marcarPagoPagadoPorCliente: Pago #{$id_pago} del pedido #{$id_pedido} marcado como pagado por usuario #{$id_usuario}");

        return [
            'exito' => true,
            'mensaje' => "Pedido #{$id_pedido} con pago confirmado. Está pendiente de aprobación por el equipo de ventas.",
            'mensaje_tipo' => 'success'
        ];
    } catch (Exception $e) {
        error_log("marcarPagoPagadoPorCliente: Error - " . $e->getMessage());
        return [
            'exito' => false,
            'mensaje' => 'Error al marcar el pago como pagado: ' . $e->getMessage(),
            'mensaje_tipo' => 'danger'
        ];
    }
}

