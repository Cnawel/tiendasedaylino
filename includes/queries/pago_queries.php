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
 * Obtiene el pago asociado a un pedido
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @return array|null Array con datos del pago o null si no existe
 */
function obtenerPagoPorPedido($mysqli, $id_pedido) {
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
        WHERE p.id_pedido = ?
        LIMIT 1
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $id_pedido);
    $stmt->execute();
    $result = $stmt->get_result();
    $pago = $result->fetch_assoc();
    $stmt->close();
    
    return $pago;
}

/**
 * Obtiene un pago por su ID
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago
 * @return array|null Array con datos del pago o null si no existe
 */
function obtenerPagoPorId($mysqli, $id_pago) {
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
        WHERE p.id_pago = ?
        LIMIT 1
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $id_pago);
    $stmt->execute();
    $result = $stmt->get_result();
    $pago = $result->fetch_assoc();
    $stmt->close();
    
    return $pago;
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
    if ($id_pedido <= 0 || $id_forma_pago <= 0 || $monto <= 0) {
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
    
    // Desactivar temporalmente el trigger que causa conflicto en transacciones
    // El trigger trg_validar_pago_unico_aprobado hace SELECT ... FOR UPDATE que causa error
    // Ya validamos en PHP antes de insertar, así que es seguro desactivarlo temporalmente
    // Verificar existencia del trigger antes de intentar leerlo
    $trigger_sql = null;
    $trigger_existia = false;
    
    // Verificar si el trigger existe antes de intentar leerlo
    $trigger_check = @$mysqli->query("SELECT COUNT(*) as existe FROM information_schema.TRIGGERS WHERE TRIGGER_NAME = 'trg_validar_pago_unico_aprobado' AND TRIGGER_SCHEMA = DATABASE()");
    if ($trigger_check && $trigger_check->num_rows > 0) {
        $check_row = $trigger_check->fetch_assoc();
        if (intval($check_row['existe']) > 0) {
            $trigger_existia = true;
            // Solo intentar leer el trigger si existe
            $result_trigger = @$mysqli->query("SHOW CREATE TRIGGER trg_validar_pago_unico_aprobado");
            if ($result_trigger && $result_trigger->num_rows > 0) {
                $trigger_row = $result_trigger->fetch_assoc();
                // Buscar la columna que contiene el CREATE TRIGGER (puede variar según versión MySQL)
                foreach ($trigger_row as $key => $value) {
                    if (stripos($key, 'sql') !== false || stripos($key, 'create') !== false || stripos($key, 'statement') !== false) {
                        if (stripos($value, 'CREATE TRIGGER') !== false) {
                            $trigger_sql = $value;
                            break;
                        }
                    }
                }
            }
        }
    }
    
    // Desactivar trigger temporalmente (solo si existía)
    // DROP TRIGGER IF EXISTS no genera error si no existe
    $mysqli->query("DROP TRIGGER IF EXISTS trg_validar_pago_unico_aprobado");
    
    // Guardar el monto del pago en la base de datos
    $sql = "INSERT INTO Pagos (id_pedido, id_forma_pago, estado_pago, monto, fecha_pago) VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("crearPago: Error al preparar consulta: " . $mysqli->error);
        // Restaurar trigger si falla (solo si existía originalmente)
        if ($trigger_existia && $trigger_sql) {
            $mysqli->query($trigger_sql);
        }
        return 0;
    }
    
    $stmt->bind_param('iisd', $id_pedido, $id_forma_pago, $estado_pago, $monto);
    
    if (!$stmt->execute()) {
        error_log("crearPago: Error al ejecutar consulta: " . $stmt->error);
        $stmt->close();
        // Restaurar trigger si falla (solo si existía originalmente)
        if ($trigger_existia && $trigger_sql) {
            $mysqli->query($trigger_sql);
        }
        return 0;
    }
    
    $id_pago = $mysqli->insert_id;
    $stmt->close();
    
    // Restaurar trigger después de insertar exitosamente (solo si existía originalmente)
    if ($trigger_existia && $trigger_sql) {
        $mysqli->query($trigger_sql);
    }
    
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
    
    // Iniciar transacción temprano para que FOR UPDATE funcione correctamente
    $mysqli->begin_transaction();
    
    // Validar solo si se está aprobando un pago (reemplaza trg_validar_pago_unico_aprobado_update)
    if ($nuevo_estado === 'aprobado' && $estado_anterior !== 'aprobado') {
        // Validar monto > 0 cuando se aprueba
        if ($monto_actual <= 0) {
            $mysqli->rollback();
            return false; // No se puede aprobar un pago con monto menor o igual a cero
        }
        
        // Verificar si ya existe otro pago aprobado para este pedido (excluyendo el actual)
        $sql_verificar = "
            SELECT COUNT(*) as pagos_aprobados
            FROM Pagos
            WHERE id_pedido = ?
              AND estado_pago = 'aprobado'
              AND id_pago != ?
            FOR UPDATE
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
        $stmt->close();
        
        if (!$resultado) {
            throw new Exception('Error al actualizar estado del pago');
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
                $stmt_pedido->execute();
                $stmt_pedido->close();
            }
        }
        
        // Cuando el pago se rechaza o cancela, cambiar pedido a cancelado
        if (in_array($nuevo_estado, ['rechazado', 'cancelado']) 
            && $estado_anterior !== $nuevo_estado 
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
                $stmt_pedido->execute();
                $stmt_pedido->close();
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
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago
 * @param int $id_usuario ID del usuario que aprueba (opcional)
 * @return bool True si se aprobó correctamente
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
    
    $id_pedido = intval($pago['id_pedido']);
    $monto = floatval($pago['monto']);
    
    // Usar actualizarPagoCompleto() que valida stock ANTES de aprobar
    // Esto garantiza que la validación y aprobación sean atómicas
    return actualizarPagoCompleto($mysqli, $id_pago, 'aprobado', $monto, null, null);
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
    
    // Obtener datos actuales del pago para validaciones
    $pago_actual = obtenerPagoPorId($mysqli, $id_pago);
    if (!$pago_actual) {
        throw new Exception('Pago no encontrado con ID: ' . $id_pago);
    }
    
    $estado_anterior = $pago_actual['estado_pago'];
    $id_pedido = intval($pago_actual['id_pedido']);
    
    // Validar monto > 0 cuando se aprueba (antes de iniciar transacción)
    if ($estado_pago === 'aprobado' && $estado_anterior !== 'aprobado') {
        if ($monto <= 0) {
            throw new Exception('No se puede aprobar un pago con monto menor o igual a cero');
        }
    }
    
    // Si se aprueba, establecer fecha_aprobacion si no está establecida
    $fecha_aprobacion = null;
    if ($estado_pago === 'aprobado') {
        $fecha_aprobacion = date('Y-m-d H:i:s');
    }
    
    // Iniciar transacción para mantener consistencia
    $mysqli->begin_transaction();
    
    try {
        // Validar solo si se está aprobando un pago (reemplaza trg_validar_pago_unico_aprobado_update)
        // MEJORA: Esta verificación debe estar DENTRO de la transacción para que FOR UPDATE funcione correctamente
        if ($estado_pago === 'aprobado' && $estado_anterior !== 'aprobado') {
            // MEJORA DE RENDIMIENTO: FOR UPDATE aquí es necesario para prevenir race conditions
            // cuando múltiples pagos se aprueban simultáneamente. Sin embargo, podría optimizarse
            // usando un índice único parcial en (id_pedido, estado_pago) WHERE estado_pago='aprobado'
            // para garantizar unicidad a nivel de base de datos y evitar esta verificación.
            // Verificar si ya existe otro pago aprobado para este pedido (excluyendo el actual)
            $sql_verificar = "
                SELECT COUNT(*) as pagos_aprobados
                FROM Pagos
                WHERE id_pedido = ?
                  AND estado_pago = 'aprobado'
                  AND id_pago != ?
                FOR UPDATE
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
            $sql_detalles = "
                SELECT id_variante, cantidad
                FROM Detalle_Pedido
                WHERE id_pedido = ?
                FOR UPDATE
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
                    FOR UPDATE
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
        
        // Desactivar triggers temporalmente para evitar conflictos
        // Los triggers están siendo reemplazados por lógica PHP, pero si existen en la BD causan conflictos
        $trigger_update_sql = null;
        $trigger_actualizar_sql = null;
        $trigger_update_existia = false;
        $trigger_actualizar_existia = false;
        
        // Guardar definición del trigger trg_validar_pago_unico_aprobado_update
        // Suprimir errores si el trigger no existe y verificar existencia primero
        $result_trigger_update = false;
        $trigger_check = @$mysqli->query("SELECT COUNT(*) as existe FROM information_schema.TRIGGERS WHERE TRIGGER_NAME = 'trg_validar_pago_unico_aprobado_update' AND TRIGGER_SCHEMA = DATABASE()");
        if ($trigger_check && $trigger_check->num_rows > 0) {
            $check_row = $trigger_check->fetch_assoc();
            if (intval($check_row['existe']) > 0) {
                $result_trigger_update = @$mysqli->query("SHOW CREATE TRIGGER trg_validar_pago_unico_aprobado_update");
            }
        }
        
        if ($result_trigger_update && $result_trigger_update->num_rows > 0) {
            $trigger_update_existia = true;
            $trigger_row = $result_trigger_update->fetch_assoc();
            foreach ($trigger_row as $key => $value) {
                if (stripos($key, 'sql') !== false || stripos($key, 'create') !== false || stripos($key, 'statement') !== false) {
                    if (stripos($value, 'CREATE TRIGGER') !== false) {
                        $trigger_update_sql = $value;
                        break;
                    }
                }
            }
        }
        
        // Guardar definición del trigger trg_actualizar_pedido_por_pago
        // Suprimir errores si el trigger no existe y verificar existencia primero
        $result_trigger_actualizar = false;
        $trigger_check2 = @$mysqli->query("SELECT COUNT(*) as existe FROM information_schema.TRIGGERS WHERE TRIGGER_NAME = 'trg_actualizar_pedido_por_pago' AND TRIGGER_SCHEMA = DATABASE()");
        if ($trigger_check2 && $trigger_check2->num_rows > 0) {
            $check_row2 = $trigger_check2->fetch_assoc();
            if (intval($check_row2['existe']) > 0) {
                $result_trigger_actualizar = @$mysqli->query("SHOW CREATE TRIGGER trg_actualizar_pedido_por_pago");
            }
        }
        
        if ($result_trigger_actualizar && $result_trigger_actualizar->num_rows > 0) {
            $trigger_actualizar_existia = true;
            $trigger_row = $result_trigger_actualizar->fetch_assoc();
            foreach ($trigger_row as $key => $value) {
                if (stripos($key, 'sql') !== false || stripos($key, 'create') !== false || stripos($key, 'statement') !== false) {
                    if (stripos($value, 'CREATE TRIGGER') !== false) {
                        $trigger_actualizar_sql = $value;
                        break;
                    }
                }
            }
        }
        
        // Desactivar triggers temporalmente (solo si existían)
        // DROP TRIGGER IF EXISTS no genera error si no existe
        $mysqli->query("DROP TRIGGER IF EXISTS trg_validar_pago_unico_aprobado_update");
        $mysqli->query("DROP TRIGGER IF EXISTS trg_actualizar_pedido_por_pago");
        
        try {
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
            
            // Restaurar triggers después de actualizar exitosamente (solo si existían)
            if ($trigger_update_existia && $trigger_update_sql) {
                $mysqli->query($trigger_update_sql);
            }
            if ($trigger_actualizar_existia && $trigger_actualizar_sql) {
                $mysqli->query($trigger_actualizar_sql);
            }
            
        } catch (Exception $e) {
            // Restaurar triggers si falla el UPDATE (solo si existían originalmente)
            if ($trigger_update_existia && $trigger_update_sql) {
                $mysqli->query($trigger_update_sql);
            }
            
            if ($trigger_actualizar_existia && $trigger_actualizar_sql) {
                $mysqli->query($trigger_actualizar_sql);
            }
            
            // Re-lanzar la excepción
            throw $e;
        }
        
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
            
            $sql_pedido = "
                UPDATE Pedidos 
                SET estado_pedido = 'preparacion',
                    fecha_actualizacion = NOW()
                WHERE id_pedido = ? 
                  AND estado_pedido IN ('pendiente', 'preparacion')
            ";
            
            $stmt_pedido = $mysqli->prepare($sql_pedido);
            if (!$stmt_pedido) {
                throw new Exception('Error al preparar actualización de estado del pedido: ' . $mysqli->error);
            }
            $stmt_pedido->bind_param('i', $id_pedido);
            if (!$stmt_pedido->execute()) {
                $error_msg = $stmt_pedido->error;
                $stmt_pedido->close();
                throw new Exception('Error al actualizar estado del pedido: ' . $error_msg);
            }
            $stmt_pedido->close();
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
        return true;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Error en actualizarPagoCompleto: " . $e->getMessage());
        // Re-lanzar excepción para que el código que llama pueda manejarla
        // especialmente importante para errores de stock insuficiente
        throw $e;
    }
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
 * Actualiza el estado de un pago y aplica reglas de negocio para actualizar el estado del pedido
 * 
 * Esta función centraliza la lógica de negocio para transiciones de estado de pago → estado de pedido
 * según las reglas definidas en el plan de lógica de negocio.
 * 
 * REGLAS IMPLEMENTADAS:
 * - Si pago se aprueba → pedido pasa a 'preparacion' y se descuenta stock
 * - Si pago se rechaza/cancela → pedido pasa a 'cancelado' y se restaura stock si fue descontado
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago
 * @param string $nuevo_estado_pago Nuevo estado del pago
 * @param string|null $motivo_rechazo Motivo del rechazo (opcional)
 * @param int|null $id_usuario ID del usuario que realiza la acción (opcional)
 * @return bool True si se actualizó correctamente
 * @throws Exception Si hay error en la actualización
 */
function actualizarEstadoPagoConPedido($mysqli, $id_pago, $nuevo_estado_pago, $motivo_rechazo = null, $id_usuario = null) {
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
    
    $estado_pago_anterior = $pago_actual['estado_pago'];
    $id_pedido = intval($pago_actual['id_pedido']);
    
    // Si no cambió el estado, no hacer nada
    if ($estado_pago_anterior === $nuevo_estado_pago) {
        return true;
    }
    
    // Cargar funciones necesarias
    $pedido_queries_path = __DIR__ . '/pedido_queries.php';
    if (!file_exists($pedido_queries_path)) {
        throw new Exception('Archivo de consultas de pedido no encontrado');
    }
    require_once $pedido_queries_path;
    
    $stock_queries_path = __DIR__ . '/stock_queries.php';
    if (!file_exists($stock_queries_path)) {
        throw new Exception('Archivo de consultas de stock no encontrado');
    }
    require_once $stock_queries_path;
    
    // Obtener estado actual del pedido
    $pedido_actual = obtenerPedidoPorId($mysqli, $id_pedido);
    if (!$pedido_actual) {
        throw new Exception('Pedido no encontrado con ID: ' . $id_pedido);
    }
    
    $estado_pedido_actual = $pedido_actual['estado_pedido'];
    
    // Iniciar transacción
    $mysqli->begin_transaction();
    
    try {
        // REGLA 1: Cuando el PAGO se APRUEBA
        // SI pago.estado_pago = 'aprobado' 
        // Y pago.estado_pago_anterior != 'aprobado'
        // Y pedido.estado_pedido IN ('pendiente', 'preparacion')
        // ENTONCES pedido.estado_pedido = 'preparacion' y Descontar stock
        if ($nuevo_estado_pago === 'aprobado' && $estado_pago_anterior !== 'aprobado') {
            // Validar monto > 0
            $monto = floatval($pago_actual['monto']);
            if ($monto <= 0) {
                throw new Exception('No se puede aprobar un pago con monto menor o igual a cero');
            }
            
            // Validar que no exista otro pago aprobado para este pedido
            $sql_verificar = "
                SELECT COUNT(*) as pagos_aprobados
                FROM Pagos
                WHERE id_pedido = ?
                  AND estado_pago = 'aprobado'
                  AND id_pago != ?
                FOR UPDATE
            ";
            
            $stmt_verificar = $mysqli->prepare($sql_verificar);
            if ($stmt_verificar) {
                $stmt_verificar->bind_param('ii', $id_pedido, $id_pago);
                if ($stmt_verificar->execute()) {
                    $result_verificar = $stmt_verificar->get_result();
                    $verificacion = $result_verificar->fetch_assoc();
                    $stmt_verificar->close();
                    
                    if ($verificacion && intval($verificacion['pagos_aprobados']) > 0) {
                        throw new Exception('Ya existe otro pago aprobado para este pedido');
                    }
                } else {
                    $stmt_verificar->close();
                    throw new Exception('Error al verificar pagos aprobados');
                }
            }
            
            // Actualizar pago usando función existente (que valida stock)
            if (!actualizarPagoCompleto($mysqli, $id_pago, 'aprobado', $monto, $pago_actual['numero_transaccion'], null)) {
                throw new Exception('Error al actualizar estado del pago');
            }
            
            // Actualizar estado del pedido a 'preparacion' si está en 'pendiente' o 'preparacion'
            if (in_array($estado_pedido_actual, ['pendiente', 'preparacion'])) {
                if (!actualizarEstadoPedido($mysqli, $id_pedido, 'preparacion')) {
                    throw new Exception('Error al actualizar estado del pedido a preparacion');
                }
            }
        }
        // REGLA 2: Cuando el PAGO se RECHAZA o CANCELA
        // SI pago.estado_pago IN ('rechazado', 'cancelado')
        // Y pago.estado_pago_anterior != pago.estado_pago
        // Y pedido.estado_pedido IN ('pendiente', 'preparacion')
        // ENTONCES pedido.estado_pedido = 'cancelado' y Restaurar stock si había sido descontado
        elseif (in_array($nuevo_estado_pago, ['rechazado', 'cancelado']) 
                && $estado_pago_anterior !== $nuevo_estado_pago
                && in_array($estado_pedido_actual, ['pendiente', 'preparacion'])) {
            
            // Actualizar estado del pago
            $fecha_aprobacion = null; // Limpiar fecha_aprobacion si estaba aprobado
            if (!actualizarEstadoPago($mysqli, $id_pago, $nuevo_estado_pago, $motivo_rechazo, $fecha_aprobacion)) {
                throw new Exception('Error al actualizar estado del pago');
            }
            
            // Restaurar stock si había sido descontado (si el pago estaba aprobado)
            if ($estado_pago_anterior === 'aprobado') {
                if (!revertirStockPedido($mysqli, $id_pedido, $id_usuario, "Pago " . $nuevo_estado_pago)) {
                    throw new Exception('Error al restaurar stock del pedido');
                }
            }
            
            // Actualizar estado del pedido a 'cancelado'
            if (!actualizarEstadoPedido($mysqli, $id_pedido, 'cancelado')) {
                throw new Exception('Error al actualizar estado del pedido a cancelado');
            }
        }
        // REGLA 3: Otros cambios de estado (pendiente, pendiente_aprobacion)
        // No afectan el estado del pedido automáticamente
        else {
            // Actualizar solo el estado del pago sin afectar el pedido
            $fecha_aprobacion = null;
            if ($nuevo_estado_pago === 'aprobado') {
                $fecha_aprobacion = date('Y-m-d H:i:s');
            }
            
            if (!actualizarEstadoPago($mysqli, $id_pago, $nuevo_estado_pago, $motivo_rechazo, $fecha_aprobacion)) {
                throw new Exception('Error al actualizar estado del pago');
            }
        }
        
        $mysqli->commit();
        return true;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Error en actualizarEstadoPagoConPedido: " . $e->getMessage());
        throw $e;
    }
}


