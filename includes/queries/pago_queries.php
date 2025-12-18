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
 * - trg_validar_pago_unico_aprobado_update: actualizarEstadoPago() y actualizarEstadoPagoConPedido()
 * - trg_actualizar_pedido_por_pago: actualizarEstadoPago() y actualizarEstadoPagoConPedido()
 *
 * DEPENDENCIAS:
 * - stock_queries.php: Usa obtenerStockReservado() para validaciones
 * - pedido_queries.php: Cargado dinámicamente según necesidad
 *
 * Uso:
 *   require_once __DIR__ . '/includes/queries/pago_queries.php';
 *   $pago = obtenerPagoPorPedido($mysqli, $id_pedido);
 * ========================================================================
 */

    // Carga las dependencias necesarias para el funcionamiento de este archivo.
require_once __DIR__ . '/stock_queries.php';
require_once __DIR__ . '/../estado_helpers.php';
require_once __DIR__ . '/../state_functions.php';

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
    // Valida que el campo proporcionado sea un campo válido para la búsqueda.
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
 * @return int ID del pago creado o 0 en caso de fallo
 */
function crearPago($mysqli, $id_pedido, $id_forma_pago, $monto, $estado_pago = 'pendiente') {
    // Valida los parámetros de entrada para asegurar que son correctos antes de procesar.
    $id_pedido = intval($id_pedido);
    $id_forma_pago = intval($id_forma_pago);
    $monto = floatval($monto);
    $estado_pago = trim($estado_pago);

    // CORRECCIÓN: Valida que los parámetros sean válidos SIEMPRE, no solo cuando el pago está aprobado.
    if ($id_pedido <= 0) {
        error_log("crearPago: ID pedido inválido: $id_pedido");
        return 0;
    }

    if ($id_forma_pago <= 0) {
        error_log("crearPago: ID forma pago inválida: $id_forma_pago");
        return 0;
    }

    // CORRECCIÓN: Valida que el monto sea mayor a 0 SIEMPRE, no solo para el estado de pago aprobado.
    if ($monto <= 0) {
        error_log("crearPago: Monto inválido ($monto) para pedido #{$id_pedido}");
        return 0;
    }

    // CORRECCIÓN: Valida que el monto no exceda el límite máximo permitido por el tipo de dato DECIMAL(10,2) en la base de datos.
    $monto_maximo = 99999999.99;
    if ($monto > $monto_maximo) {
        error_log("crearPago: Monto excede el límite permitido ({$monto_maximo}) para pedido #{$id_pedido}");
        return 0;
    }
    
    // CORRECCIÓN: Evita la creación de pagos duplicados verificando si ya existe CUALQUIER pago para el mismo pedido
    // (sin importar estado). Esto previene la inconsistencia de múltiples pagos por pedido.
    $sql_verificar = "
        SELECT COUNT(*) as pagos_totales
        FROM Pagos
        WHERE id_pedido = ?
    ";

    $stmt_verificar = $mysqli->prepare($sql_verificar);
    if ($stmt_verificar) {
        $stmt_verificar->bind_param('i', $id_pedido);
        if ($stmt_verificar->execute()) {
            $result_verificar = $stmt_verificar->get_result();
            $verificacion = $result_verificar->fetch_assoc();
            $stmt_verificar->close();

            if ($verificacion && intval($verificacion['pagos_totales']) > 0) {
                error_log("crearPago: Ya existe un pago para pedido #{$id_pedido} (total pagos: {$verificacion['pagos_totales']})");
                return 0;
            }
        } else {
            $stmt_verificar->close();
            error_log("crearPago: Error al verificar pagos: " . $mysqli->error);
        }
    }

    // Guarda la información del pago en la base de datos.
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
    
    // Valida que se haya obtenido un ID de pago válido después de la inserción en la base de datos.
    if (!$id_pago || $id_pago <= 0) {
        error_log("crearPago: Error - insert_id no válido después de insertar");
        return 0;
    }
    
    return intval($id_pago);
}

/**
 * Wrapper que delega a actualizarEstadoPagoConPedido()
 * Se proporciona para compatibilidad con código existente.
 * Internamente usa la versión con validación e integración con pedido.
 *
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago
 * @param string $nuevo_estado Nuevo estado del pago
 * @param string|null $motivo_rechazo Motivo del rechazo (opcional)
 * @param string|null $fecha_aprobacion Fecha de aprobación (ignorada, se usa NOW())
 * @return bool True si se actualizó correctamente
 */
function actualizarEstadoPago($mysqli, $id_pago, $nuevo_estado, $motivo_rechazo = null, $fecha_aprobacion = null) {
    try {
        return actualizarEstadoPagoConPedido($mysqli, $id_pago, $nuevo_estado, $motivo_rechazo);
    } catch (Exception $e) {
        error_log("actualizarEstadoPago: Error - " . $e->getMessage());
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

    // Si el pago ya se encuentra en estado 'aprobado', no se realiza ninguna acción adicional.
    if ($pago['estado_pago'] === 'aprobado') {
        return true;
    }

    // Se utiliza la función `actualizarEstadoPagoConPedido()` que es la RECOMENDADA
    // para aprobar pagos, ya que gestiona toda la lógica de negocio de forma integral.
    $resultado = actualizarEstadoPagoConPedido($mysqli, $id_pago, 'aprobado', null, $id_usuario);

    if (!$resultado) {
        throw new Exception("No se pudo actualizar el estado del pago. Por favor, verifica los logs del sistema para más detalles.");
    }

    return $resultado;
}

/**
 * ⚠️ DEPRECATED: Actualiza un pago completo con todos sus campos editables
 *
 * DEPRECADA: Esta función fue reemplazada por operaciones directas en marcarPagoPagadoPorCliente()
 * y sigue siendo usada solo en tests de compatibilidad histórica.
 *
 * Para nuevo código:
 * - Cambios de estado de pago en `marcarPagoPagadoPorCliente()` usan SQL directo
 * - Cambios complejos de estado usam actualizarEstadoPagoConPedido()
 *
 * ⚠️ TODO: Eliminar esta función cuando los tests históricos sean refactorizados.
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
    
    // Valida el número de transacción, asegurando que tenga un máximo de 100 caracteres.
    if ($numero_transaccion !== null) {
        $numero_transaccion = trim($numero_transaccion);
        if (!empty($numero_transaccion)) {
            $longitud = strlen($numero_transaccion);
            if ($longitud > 100) {
                throw new Exception('El número de transacción no puede exceder 100 caracteres.');
            }
            // Valida los caracteres permitidos para el número de transacción según el diccionario definido: [A-Z, a-z, 0-9, -, _].
            if (!preg_match('/^[A-Za-z0-9\-_]+$/', $numero_transaccion)) {
                throw new Exception('El número de transacción solo puede contener letras, números, guiones y guiones bajos.');
            }
        }
    }
    
    // Valida el motivo de rechazo, asegurando que tenga entre 0 y 500 caracteres (es opcional).
    if ($motivo_rechazo !== null) {
        $motivo_rechazo = trim($motivo_rechazo);
        if (!empty($motivo_rechazo)) {
            if (strlen($motivo_rechazo) > 500) {
                throw new Exception('El motivo de rechazo no puede exceder 500 caracteres.');
            }
        }
    }
    
    // Obtiene los datos actuales del pago para realizar validaciones posteriores.
    $pago_actual = obtenerPagoPorId($mysqli, $id_pago);
    if (!$pago_actual) {
        throw new Exception('Pago no encontrado con ID: ' . $id_pago);
    }
    
    $estado_anterior = $pago_actual['estado_pago'];
    $id_pedido = intval($pago_actual['id_pedido']);
    
    // Valida que la transición de estado sea permitida, previniendo cambios de estado inválidos.
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
            
            // Verifica que el estado no haya cambiado desde la última lectura para prevenir condiciones de carrera.
            // Re-lee el estado dentro de la transacción actual para detectar posibles cambios concurrentes en la base de datos.
            // No se utiliza FOR UPDATE aquí; la validación del estado se realizará en la sentencia UPDATE final.
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
            
            // Valida que el monto sea mayor a 0 al aprobar el pago.
            if ($estado_pago === 'aprobado' && $estado_anterior !== 'aprobado') {
                if ($monto <= 0) {
                    $mysqli->rollback();
                    throw new Exception('No se puede aprobar un pago con monto menor o igual a cero');
                }
            }
            
            // Si el pago es aprobado y la fecha de aprobación no está establecida, se asigna la fecha y hora actual.
            if ($estado_pago === 'aprobado') {
                $fecha_aprobacion = date('Y-m-d H:i:s');
            }
            
            // Realiza validaciones específicas solo si el pago está siendo aprobado (reemplaza la lógica del trigger `trg_validar_pago_unico_aprobado_update`).
            if ($estado_pago === 'aprobado' && $estado_anterior !== 'aprobado') {
                // Verifica si ya existe otro pago aprobado para el mismo pedido, excluyendo el pago actual.
                // Se utiliza un SELECT simple sin FOR UPDATE para verificar la existencia de pagos antes de proceder con el UPDATE.
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
            
            // Si el pago está siendo aprobado, se valida el stock disponible ANTES de confirmar la aprobación.
            if ($estado_pago === 'aprobado' && $estado_anterior !== 'aprobado') {
                $stock_queries_path = __DIR__ . '/stock_queries.php';
                if (!file_exists($stock_queries_path)) {
                    error_log("ERROR: No se pudo encontrar stock_queries.php en " . $stock_queries_path);
                    die("Error crítico: Archivo de consultas de stock no encontrado. Por favor, contacta al administrador.");
                }
                require_once $stock_queries_path;
                
                // ESTRATEGIA DE MÚLTIPLES CONSULTAS SIMPLES:
                // Consulta 1: Obtiene los detalles básicos del pedido (solo ID de variante y cantidad).
                // Se utiliza un SELECT simple sin FOR UPDATE para obtener los datos necesarios.
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

                // Verifica si el pedido ya cuenta con reservas de stock.
                // Si existen reservas, el stock ya fue descontado físicamente al momento de crear el pedido.
                $reservas_previas = verificarReservasPreviasPedido($mysqli, $id_pedido);
                $tiene_reservas = ($reservas_previas > 0);

                // Valida el stock por cada variante dentro de un bucle PHP, utilizando consultas simples e independientes.
                // IMPORTANTE: Si el pedido ya tiene reservas, el stock FÍSICO ya fue descontado.
                // Solo se valida el stock si NO hay reservas previas (esto aplica a pedidos antiguos que no utilizaban el sistema de reservas).
                $errores_stock = [];

                if ($tiene_reservas) {
                    // Si el pedido ya tiene reservas, el stock ya fue descontado al momento de su creación.
                    // No es necesario validar el stock disponible, ya que los productos ya están reservados.
                    error_log("actualizarEstadoPagoConPedido: Pedido #{$id_pedido} ya tiene {$reservas_previas} reservas. Stock ya descontado al crear el pedido.");
                } else {
                    // Si NO existen reservas, se valida el stock disponible de manera normal.
                    // (Esto se aplica a pedidos antiguos creados sin el sistema de reserva de stock).
                    foreach ($detalles_pedido as $detalle) {
                        $id_variante = $detalle['id_variante'];
                        $cantidad_solicitada = $detalle['cantidad'];

                        // Consulta 2: Obtiene la información de stock y el estado de la variante/producto.
                        // Se utiliza un SELECT simple sin FOR UPDATE para validar los datos antes de realizar cualquier UPDATE.
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
                            $errores_stock[] = "La variante #{$id_variante} no existe en el sistema.";
                            continue;
                        }

                        $stock_actual = intval($variante_data['stock']);
                        $variante_activa = intval($variante_data['variante_activa']);
                        $producto_activo = intval($variante_data['producto_activo']);
                        $talle = $variante_data['talle'];
                        $color = $variante_data['color'];
                        $nombre_producto = $variante_data['nombre_producto'];

                        // Calcula el stock reservado por otros pedidos, excluyendo el pedido actual.
                        $stock_reservado_otros = obtenerStockReservado($mysqli, $id_variante, $id_pedido);
                        $stock_disponible = $stock_actual - $stock_reservado_otros;

                        // Asegura que el stock disponible no sea un valor negativo.
                        if ($stock_disponible < 0) {
                            $stock_disponible = 0;
                        }

                        // Validar que variante y producto estén activos
                        if ($variante_activa === 0) {
                            $errores_stock[] = "La variante {$talle} {$color} del producto {$nombre_producto} está inactiva";
                        } elseif ($producto_activo === 0) {
                            $errores_stock[] = "El producto {$nombre_producto} está inactivo";
                        } elseif ($stock_disponible < $cantidad_solicitada) {
                            $errores_stock[] = "Stock insuficiente para {$nombre_producto} (Talla: {$talle}, Color: {$color}). Disponible: {$stock_disponible}, Solicitado: {$cantidad_solicitada}";
                        }
                    }
                }
            
            // Si hay errores de stock, lanzar excepción ANTES de aprobar el pago
            if (!empty($errores_stock)) {
                throw new Exception('STOCK_INSUFICIENTE: ' . implode('; ', $errores_stock));
            }
        }


        // Realiza la actualización completa del pago en la base de datos.
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
            throw new Exception('Error al preparar actualización de pago: ' . $mysqli->error);
        }

        $stmt->bind_param('sdsssi', $estado_pago, $monto, $numero_transaccion, $fecha_aprobacion, $motivo_rechazo, $id_pago);
        if (!$stmt->execute()) {
            $error_msg = $stmt->error;
            $stmt->close();
            throw new Exception('Error al actualizar pago: ' . $error_msg);
        }

        $stmt->close();
        
        // Actualiza el estado del pedido basándose en el estado del pago (reemplaza la lógica del trigger `trg_actualizar_pedido_por_pago`).
        $pedido_queries_path = __DIR__ . '/pedido_queries.php';
        if (!file_exists($pedido_queries_path)) {
            error_log("ERROR: No se pudo encontrar pedido_queries.php en " . $pedido_queries_path);
            die("Error crítico: Archivo de consultas de pedido no encontrado. Por favor, contacta al administrador.");
        }
        require_once $pedido_queries_path;
        
        // Si el pago es aprobado, el pedido se cambia automáticamente a estado 'preparacion' y se descuenta el stock.
        if ($estado_pago === 'aprobado' && $estado_anterior !== 'aprobado') {
            // Descuenta el stock del pedido (esta operación ya fue validada previamente dentro de la misma transacción).
            $stock_queries_path = __DIR__ . '/stock_queries.php';
            if (!file_exists($stock_queries_path)) {
                error_log("ERROR: No se pudo encontrar stock_queries.php en " . $stock_queries_path);
                die("Error crítico: Archivo de consultas de stock no encontrado. Por favor, contacta al administrador.");
            }
            require_once $stock_queries_path;
            // Obtiene el ID de usuario del pedido para registrarlo en el movimiento de stock.
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
                $resultado_stock = descontarStockPedido($mysqli, $id_pedido, $id_usuario_pago, true);
                if ($resultado_stock === false || $resultado_stock === null) {
                    throw new Exception('Error al descontar stock del pedido: La función retornó ' .
                        (is_null($resultado_stock) ? 'null' : 'false'));
                }
            } catch (Exception $e) {
                // Re-lanza la excepción para asegurar que toda la transacción sea revertida (rollback).
                throw $e;
            }
            
            // Actualiza el estado del pedido a 'preparacion' utilizando una función centralizada para la gestión de estados.
            // Esta acción solo se realiza si el estado actual del pedido es 'pendiente', 'preparacion' o está vacío (NULL/'').
            $pedido_queries_path = __DIR__ . '/pedido_queries.php';
            if (file_exists($pedido_queries_path)) {
                require_once $pedido_queries_path;
            }

            // Permite estados vacíos, tratándolos como 'pendiente' para compatibilidad con datos heredados.
            $estados_permitidos = ['pendiente', 'preparacion', '', null];
            if (!actualizarEstadoPedidoConValidaciones($mysqli, $id_pedido, 'preparacion', $estados_permitidos)) {
                throw new Exception('Error al actualizar estado del pedido: el estado actual no permite esta actualización');
            }
        }
        
        // Si el pago es rechazado o cancelado, el pedido asociado se cambia automáticamente a estado 'cancelado'.
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
                    // Registra el error en el log, pero no lanza una excepción, ya que esta acción es opcional.
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
        // Actualiza el estado del pago directamente, sin utilizar la función `actualizarEstadoPago` (que contiene validaciones más complejas).
        $sql_actualizar_pago = "
            UPDATE Pagos
            SET estado_pago = 'rechazado',
                motivo_rechazo = ?,
                fecha_actualizacion = NOW()
            WHERE id_pago = ?
        ";

        $stmt_actualizar_pago = $mysqli->prepare($sql_actualizar_pago);
        if (!$stmt_actualizar_pago) {
            throw new Exception('Error al preparar actualización de pago: ' . $mysqli->error);
        }
        $stmt_actualizar_pago->bind_param('si', $motivo, $id_pago);
        if (!$stmt_actualizar_pago->execute()) {
            $error_msg = $stmt_actualizar_pago->error;
            $stmt_actualizar_pago->close();
            throw new Exception('Error al actualizar estado del pago: ' . $error_msg);
        }

        $rows_affected = $stmt_actualizar_pago->affected_rows;
        $stmt_actualizar_pago->close();

        if ($rows_affected === 0) {
            // No lanzar excepción si no hay filas afectadas (el estado ya era 'rechazado')
            error_log("rechazarPago: Pago #{$id_pago} ya estaba en estado rechazado o no hubo cambios.");
        }

        // Revierte el stock ÚNICAMENTE si el pago estaba previamente aprobado (lo que implica que el stock fue descontado).
        if ($pago['estado_pago'] === 'aprobado') {
            $stock_queries_path = __DIR__ . '/stock_queries.php';
            if (!file_exists($stock_queries_path)) {
                error_log("ERROR: No se pudo encontrar stock_queries.php en " . $stock_queries_path);
                die("Error crítico: Archivo de consultas de stock no encontrado. Por favor, contacta al administrador.");
            }
            require_once $stock_queries_path;
            $resultado_reversion = revertirStockPedido($mysqli, $pago['id_pedido'], $id_usuario, $motivo ? "Pago rechazado: {$motivo}" : "Pago rechazado");

            // BUG FIX: Lanzar excepción si la restauración falla
            // Anterior comportamiento: Solo registraba log y continuaba, dejando stock sin restaurar
            // Nuevo comportamiento: Rechaza el cambio de pago si no se puede restaurar el stock
            if ($resultado_reversion === false) {
                throw new Exception("Error crítico al restaurar stock del pago #{$pago['id_pago']} (pedido #{$pago['id_pedido']}). No se puede completar el rechazo del pago.");
            }
        }

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
    
    // Valida que todos los IDs proporcionados sean números enteros.
    $pedidos_ids = array_map('intval', $pedidos_ids);
    $pedidos_ids = array_filter($pedidos_ids, function($id) { return $id > 0; });
    
    if (empty($pedidos_ids)) {
        return [];
    }
    
    // Construye los placeholders necesarios para la consulta SQL dinámica.
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
    
    // Asocia los parámetros a la consulta de forma dinámica.
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
 * Valida si una transición de estado de pago está permitida
 *
 * @param string $estado_actual Estado actual del pago
 * @param string $nuevo_estado Nuevo estado al que se quiere cambiar
 * @return bool True si la transición está permitida
 * @throws Exception Si la transición no está permitida
 */
function validarTransicionPago($estado_actual, $nuevo_estado) {
    require_once __DIR__ . '/../state_functions.php';

    if (!puedeTransicionarPago($estado_actual, $nuevo_estado)) {
        $transiciones = obtenerTransicionesPagoValidas();
        $estados_permitidos = $transiciones[$estado_actual] ?? [];
        $estados_permitidos_str = empty($estados_permitidos) ? 'ninguno (estado terminal)' : implode(', ', $estados_permitidos);
        throw new Exception("Transición no permitida: No se puede cambiar de '$estado_actual' a '$nuevo_estado'. Transiciones permitidas desde '$estado_actual': $estados_permitidos_str");
    }
    return true;
}

/**
 * Valida que no se intente cancelar un pago en recorrido activo
 *
 * @param string $nuevo_estado_pago Nuevo estado del pago
 * @param string $estado_pago_anterior Estado anterior del pago
 * @return void
 * @throws Exception Si se intenta cancelar un pago en recorrido activo
 */
function _validarCancelacionPagoRecorridoActivo($nuevo_estado_pago, $estado_pago_anterior) {
    require_once __DIR__ . '/../state_functions.php';

    $nuevo_estado_norm = strtolower(trim($nuevo_estado_pago));
    $estado_anterior_norm = strtolower(trim($estado_pago_anterior));

    // Si el nuevo estado es 'cancelado' y el estado anterior es 'pendiente_aprobacion',
    // esta transición está permitida según la lógica de negocio (genera cancelación de pedido).
    // Por lo tanto, no se debe lanzar una excepción aquí.
    if ($nuevo_estado_norm === 'cancelado' && $estado_anterior_norm === 'pendiente_aprobacion') {
        return;
    }

    // Para cualquier otra situación donde se intente cancelar un pago en "recorrido activo"
    // (es decir, no es 'pendiente_aprobacion' ni 'pendiente'), se lanza una excepción.
    // La validación para el estado 'aprobado' se maneja por separado en _validarRechazoCancelacionPagoAprobado.
    if ($nuevo_estado_norm === 'cancelado' && estaEnRecorridoActivo($estado_anterior_norm, 'pago')) {
        throw new Exception("No se puede cancelar un pago en recorrido activo (estado: {$estado_anterior_norm}).");
    }
}

/**
 * Valida que no se cancele/rechace un pago aprobado en pedido avanzado
 *
 * @param string $estado_pago_anterior Estado anterior del pago
 * @param string $estado_pedido_actual Estado actual del pedido
 * @return void
 * @throws Exception Si se intenta cancelar/rechazar pago aprobado en pedido avanzado
 */
function _validarRechazoCancelacionPagoAprobado($estado_pago_anterior, $estado_pedido_actual) {
    require_once __DIR__ . '/../state_functions.php';

    $estado_pago_anterior = strtolower(trim($estado_pago_anterior));
    $estado_pedido_actual = strtolower(trim($estado_pedido_actual));

    // CRÍTICO: Si el pago ya fue aprobado, no se permite ninguna otra acción de rechazo o cancelación.
    // Esto aplica estrictamente la regla 'aprobado => []', haciendo que 'aprobado' sea un estado terminal en su flujo.
    if ($estado_pago_anterior === 'aprobado') {
        throw new Exception("No se puede cambiar el estado de un pago ya aprobado. Un pago aprobado es un estado final y no admite transiciones a rechazado o cancelado.");
    }

    // La lógica original para verificar estados de pedido (en_viaje, completado) es ahora redundante para pagos 'aprobado',
    // dado que la verificación anterior ya impide cualquier modificación desde un estado aprobado.
    // Sin embargo, esta función también podría ser llamada en el futuro para validar rechazos/cancelaciones desde otros estados
    // (ej. 'pendiente_aprobacion'), donde la validación del estado del pedido sí sería relevante.
    // Por ahora, su propósito principal es asegurar que un pago *aprobado* no se pueda modificar.
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
 * @return bool True si se aprobó correctamente
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
    
    // Valida que el monto del pago sea mayor a 0.
    $monto = floatval($pago_bloqueado['monto']);
    if ($monto <= 0) {
        throw new Exception('No se puede aprobar un pago con monto menor o igual a cero');
    }

    $estado_pedido_normalizado = normalizarEstado($estado_pedido_actual);

    // CORRECCIÓN: Evita la aprobación de pagos si el pedido asociado se encuentra en un estado terminal.
    if (in_array($estado_pedido_normalizado, ['cancelado', 'completado'])) {
        throw new Exception("No se puede aprobar el pago. El pedido está en estado terminal '{$estado_pedido_actual}'. Los pedidos cancelados, completados o devueltos no pueden modificarse.");
    }

    // CORRECCIÓN: Evita el doble descuento de stock verificando si las reservas ya fueron liberadas.
    $info_reservas = verificarSiPedidoTuvoReservas($mysqli, $id_pedido);

    if ($info_reservas['reservas_liberadas'] > 0 && $info_reservas['reservas_activas'] === 0) {
        // Caso: Las reservas fueron liberadas debido a su expiración (más de 24 horas).
        throw new Exception("No se puede aprobar el pago. El pedido #{$id_pedido} tuvo reservas que fueron liberadas por expiración. El cliente debe crear un nuevo pedido.");
    }

    // Permite el estado 'preparacion' únicamente para la corrección manual de inconsistencias.
    if (!in_array($estado_pedido_normalizado, ['pendiente', 'pendiente_validado_stock', 'preparacion'])) {
        throw new Exception("No se puede aprobar el pago. El pedido está en estado '{$estado_pedido_actual}'. Solo se pueden aprobar pagos de pedidos en estado 'pendiente', 'pendiente_validado_stock' o 'preparacion'.");
    }
    
    // Valida que no exista otro pago aprobado para el mismo pedido.
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
    
    // Obtiene los detalles del pedido desde la tabla Detalle_Pedido.
    // Se utiliza un SELECT simple sin FOR UPDATE para obtener los datos.
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
    
    // Validar stock SOLO si el pedido NO tiene reservas previas
    // Si tiene reservas → el stock ya fue validado y descontado al momento de crear la reserva
    // Si NO tiene reservas → validar ahora antes de descontar
    $errores_stock = [];

    // Primero verificar si este pedido tiene reservas activas
    $info_reservas = verificarSiPedidoTuvoReservas($mysqli, $id_pedido);
    $tiene_reservas_activas = ($info_reservas['reservas_activas'] > 0);

    if ($tiene_reservas_activas) {
        // CAMINO A: El pedido YA tiene reservas
        // Las reservas garantizan que el stock fue validado y descontado al momento de la reserva.
        // No necesitamos validar nuevamente porque:
        // 1. Las reservas solo se crean si hay stock disponible
        // 2. El stock ya fue descontado físicamente en Stock_Variantes
        // 3. Las reservas se mantienen activas durante 24 horas (raramente expiran antes de pago)
        error_log("_aprobarPagoConValidaciones: Pedido #{$id_pedido} - Tiene {$info_reservas['reservas_activas']} reservas activas. Stock ya validado y descontado.");
    } else {
        // CAMINO B: El pedido NO tiene reservas (pedido antiguo o caso especial)
        // Validar stock disponible ahora antes de descontar
        error_log("_aprobarPagoConValidaciones: Pedido #{$id_pedido} - Sin reservas previas. Validando stock disponible.");

        foreach ($detalles as $detalle) {
            $id_variante = intval($detalle['id_variante']);
            $cantidad_requerida = intval($detalle['cantidad']);

            // Obtener stock disponible actual
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
                    $errores_stock[] = "Variante #{$id_variante}: necesita {$cantidad_requerida}, disponible {$stock_disponible}";
                }
            }
        }
    }
    
    // Si se detectan errores de stock, el pago se rechaza automáticamente.
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
    
    // Procede a descontar el stock de los productos del pedido.
    // Nota: descontarStockPedido con $en_transaccion=true lanza excepciones en lugar de retornar false
    try {
        $resultado_descuento = descontarStockPedido($mysqli, $id_pedido, $id_usuario_pedido, true);
        if (!$resultado_descuento) {
            throw new Exception('Error al descontar stock del pedido');
        }
    } catch (Exception $e) {
        // Re-lanzar la excepción para que sea capturada en el bloque catch superior
        throw $e;
    }
    
    // Actualiza el estado del pago a 'aprobado'.
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
        // No lanzar excepción si no hay filas afectadas (el estado ya era 'aprobado')
        error_log("_aprobarPagoConValidaciones: Pago #{$id_pago} ya estaba en estado aprobado o no hubo cambios.");
    }
    
    // Actualiza el estado del pedido a 'preparacion'.
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
    // Verificar que la actualización ocurrió (o que ya estaba en ese estado)
    $rows_affected_pedido = $stmt_actualizar_pedido->affected_rows;
    $stmt_actualizar_pedido->close();
    
    if ($rows_affected_pedido === 0) {
        error_log("_aprobarPagoConValidaciones: Pedido #{$id_pedido} ya estaba en estado preparacion o no hubo cambios.");
    }

    // Retornar true para indicar éxito
    return true;
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
 * @return bool True si se rechazó/canceló correctamente
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
    
    // Normaliza los estados del pago y pedido antes de realizar cualquier validación.
    $estado_pago_anterior = normalizarEstado($estado_pago_anterior);
    $estado_pedido_actual = normalizarEstado($estado_pedido_actual);
    
    // VALIDACIÓN CRÍTICA: Impide la cancelación o el rechazo de pagos que ya han sido aprobados cuando el pedido se encuentra en estados avanzados.
    // Los estados avanzados incluyen: 'completado' (venta cerrada) y 'en_viaje' (pedido ya enviado).
    // Se utiliza una función centralizada para evitar la duplicación de código y lógica.
    try {
        _validarRechazoCancelacionPagoAprobado($estado_pago_anterior, $estado_pedido_actual);
    } catch (Exception $e) {
        // Mejorar mensaje de error para que sea más claro
        throw new Exception('No se puede rechazar/cancelar el pago: ' . $e->getMessage());
    }
    
    // Se procede a actualizar el estado del pago en la base de datos.
    $fecha_aprobacion = null; // Se limpia la fecha de aprobación, ya que el pago no está en estado aprobado.
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
        error_log("_rechazarOCancelarPago: Pago #{$id_pago} ya estaba en el estado deseado o no hubo cambios.");
    }
    
    // La lógica de cancelación se ejecuta solo si el pago ha sido rechazado o cancelado.
    // REGLA: Si el pago es rechazado y el pedido se encuentra en estado 'pendiente' o 'preparacion', se cancela automáticamente el pedido.
    if (in_array($nuevo_estado_pago, ['rechazado', 'cancelado'])
        && $estado_pedido_actual !== 'cancelado'  
        && $estado_pedido_actual !== 'completado') {
    
        error_log("actualizarEstadoPagoConPedido: Cancelando pedido #{$id_pedido} debido a pago {$nuevo_estado_pago} (estado anterior: {$estado_pago_anterior}, estado pedido: {$estado_pedido_actual})");
        
        // Siempre se cancela el pedido si está en estado 'pendiente' o 'preparacion' cuando el pago es rechazado.
        // La lógica de cancelación procede solo si el pedido está en estado 'pendiente' o 'preparacion'.
        if (in_array($estado_pedido_actual, ['pendiente', 'pendiente_validado_stock', 'preparacion'])) {
            // Actualiza el estado del pedido a 'cancelado'.
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
            // Verificar que la actualización ocurrió (o ya estaba en ese estado)
            $rows_affected_pedido = $stmt_actualizar_pedido->affected_rows;
            $stmt_actualizar_pedido->close();
            
            if ($rows_affected_pedido === 0) {
                error_log("_rechazarOCancelarPago: Pedido #{$id_pedido} ya estaba en estado cancelado o no hubo cambios.");
            }
            
            error_log("actualizarEstadoPagoConPedido: Pedido #{$id_pedido} cancelado exitosamente");
        } else {
            error_log("actualizarEstadoPagoConPedido: Pedido #{$id_pedido} en estado '{$estado_pedido_actual}' no puede cancelarse automáticamente al rechazar pago");
        }
        
        // Restaura el stock si previamente fue descontado (es decir, si el pago estaba aprobado, o si había reservas).
        // La función revertirStockPedido es idempotente y manejará si hay o no stock que revertir.
        error_log("actualizarEstadoPagoConPedido: Intentando restaurar stock del pedido #{$id_pedido} debido a pago {$nuevo_estado_pago}");
        $resultado_reversion = revertirStockPedido($mysqli, $id_pedido, $id_usuario, "Pago " . $nuevo_estado_pago);
        if ($resultado_reversion === false) {
            // BUG FIX: Lanzar excepción para detener la transacción si la restauración falla
            // Anterior comportamiento: Solo registraba log y continuaba, dejando stock sin restaurar
            // Nuevo comportamiento: Rechaza el cambio de pago si no se puede restaurar el stock
            throw new Exception("Error crítico al restaurar stock del pedido #{$id_pedido}. No se puede completar la cancelación/rechazo del pago. Verifique los logs para más detalles.");
        }
    }

    // Retornar true para indicar éxito
    return true;
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
 * ✅ RECOMENDADO: Esta es la versión canónica con integración completa con pedido y stock.
 * USAR ESTA para cualquier cambio de estado de pago en nuevo código.
 * NOTA: Existen otras versiones (actualizarEstadoPago) por razones históricas.
 * actualizarPagoCompleto() fue deprecada en favor de operaciones directas en marcarPagoPagadoPorCliente().
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
    // Carga la función de normalización de estados para su uso.
    require_once __DIR__ . '/../estado_helpers.php';

    // Normaliza el estado del pago antes de realizar cualquier validación.
    $nuevo_estado_pago = normalizarEstado($nuevo_estado_pago);

    // Valida que el nuevo estado del pago sea uno de los estados permitidos.
    $estados_validos = ['pendiente', 'pendiente_aprobacion', 'aprobado', 'rechazado', 'cancelado'];
    if (!in_array($nuevo_estado_pago, $estados_validos)) {
        throw new Exception('Estado de pago inválido: ' . $nuevo_estado_pago);
    }
    
    // Obtiene los datos actuales del pago desde la base de datos.
    $pago_actual = obtenerPagoPorId($mysqli, $id_pago);
    if (!$pago_actual) {
        throw new Exception('Pago no encontrado con ID: ' . $id_pago);
    }
    
    // Normaliza el estado anterior del pago para comparaciones.
    $estado_pago_anterior = normalizarEstado($pago_actual['estado_pago']);
    $id_pedido = intval($pago_actual['id_pedido']);
    
    // Si el estado del pago no ha cambiado, no se realiza ninguna acción y se retorna éxito.
    if ($estado_pago_anterior === $nuevo_estado_pago) {
        return true;
    }
    
    // VALIDACIÓN TEMPRANA: Verifica que la transición de estado esté permitida según la matriz de transiciones definida.
    // Esta validación temprana previene transiciones de estado inválidas antes de que se ejecute cualquier lógica de negocio compleja.
    try {
        validarTransicionPago($estado_pago_anterior, $nuevo_estado_pago);
    } catch (Exception $e) {
        // Mejorar mensaje de error para que sea más claro
        throw new Exception('Transición de estado de pago no permitida: ' . $e->getMessage());
    }

    // CRÍTICO: Evitar que un pago aprobado se rechace o cancele.
    if ($estado_pago_anterior === 'aprobado' && in_array($nuevo_estado_pago, ['rechazado', 'cancelado'])) {
        throw new Exception(
            'Inconsistencia Crítica de Pago: Un pago aprobado no puede ser rechazado o cancelado. Revise el flujo de pago o los estados previos.'
        );
    }
    
    // VALIDACIÓN CRÍTICA: Impide la cancelación de pagos que ya se encuentran en un "recorrido activo".
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
            
            // PASO 2: Obtiene los datos del pago (sin usar FOR UPDATE, ya que las validaciones atómicas se manejan en el UPDATE).
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
        
        // Verifica que el estado del pago no haya cambiado desde la lectura inicial para evitar condiciones de carrera.
        if ($pago_bloqueado['estado_pago'] !== $estado_pago_anterior) {
            throw new Exception('El estado del pago ha cambiado durante la transacción');
        }
        
        // Obtiene los datos del pedido asociado (sin usar FOR UPDATE, ya que las validaciones atómicas se manejan en el UPDATE).
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
        // Normaliza el estado del pedido: si está vacío o NULL, se establece 'pendiente' como valor por defecto.
        if (empty($estado_pedido_actual)) {
            $estado_pedido_actual = 'pendiente';
        } else {
            // Normalizar estado para asegurar formato consistente (minúsculas, sin espacios)
            require_once __DIR__ . '/../estado_helpers.php';
            $estado_pedido_actual = normalizarEstado($estado_pedido_actual);
        }
        $id_usuario_pedido = intval($pedido_bloqueado['id_usuario']);
        
        // REGLA 1: Lógica a ejecutar cuando el PAGO es APROBADO.
        if ($nuevo_estado_pago === 'aprobado' && $estado_pago_anterior !== 'aprobado') {
            _aprobarPagoConValidaciones($mysqli, $id_pago, $pago_bloqueado, $id_pedido, $estado_pedido_actual, $id_usuario_pedido);
        }
        // REGLA 2: Lógica a ejecutar cuando el PAGO es RECHAZADO o CANCELADO.
        elseif (in_array($nuevo_estado_pago, ['rechazado', 'cancelado']) 
                && $estado_pago_anterior !== $nuevo_estado_pago) {
            _rechazarOCancelarPago($mysqli, $id_pago, $nuevo_estado_pago, $motivo_rechazo, $id_pedido, $estado_pago_anterior, $estado_pedido_actual, $id_usuario);
        }
        // REGLA 3: Manejo de otros cambios de estado (ej. de 'pendiente' a 'pendiente_aprobacion').
        // Estos cambios de estado no afectan automáticamente el estado del pedido asociado.
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
                error_log("actualizarEstadoPagoConPedido: Pago #{$id_pago} ya estaba en el estado deseado o no hubo cambios.");
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
 * - NO valida stock (eso lo hace ventas al aprobar)
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

    // VALIDACIÓN 3: Número de transacción es OPCIONAL (puede ser NULL)
    // Según diccionario de datos: varchar(100), UNIQUE, NULL permitido
    // Caracteres permitidos: [A-Z, a-z, 0-9, -, _]
    $numero_transaccion_raw = trim($numero_transaccion);
    $numero_transaccion_validado = null;

    // Si el campo no está vacío, validar su formato
    if (!empty($numero_transaccion_raw)) {
        // VALIDACIÓN 4: Validar formato de número de transacción
        $validacion_numero = validarNumeroTransaccion($numero_transaccion_raw, 0, 100);
        if (!$validacion_numero['valido']) {
            // Log detallado para debugging
            error_log("marcarPagoPagadoPorCliente: Validación fallida - " . $validacion_numero['error'] . " | Valor ingresado: " . htmlspecialchars($numero_transaccion_raw) . " | Longitud: " . strlen($numero_transaccion_raw));

            return [
                'exito' => false,
                'mensaje' => 'Código de pago inválido: ' . $validacion_numero['error'],
                'mensaje_tipo' => 'danger'
            ];
        }
        $numero_transaccion_validado = $validacion_numero['valor'];
    } else {
        // Log cuando se omite el código de pago (permitido)
        error_log("marcarPagoPagadoPorCliente: Código de pago omitido (campo opcional) | Pago ID: $id_pago | Usuario ID: $id_usuario");
    }

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

    if (in_array($estado_pedido, ['cancelado', 'completado'])) {
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
        // Generar numero_transaccion único con formato IDPAGO-CODIGOPAGO
        $numero_transaccion_final = !empty($numero_transaccion_validado) ? "{$id_pago}-{$numero_transaccion_validado}" : null;

        // Actualizar directamente: estado_pago a 'pendiente_aprobacion' y numero_transaccion
        $estado_nuevo = 'pendiente_aprobacion';

        $sql_actualizar = "
            UPDATE Pagos
            SET estado_pago = ?,
                numero_transaccion = ?,
                fecha_actualizacion = NOW()
            WHERE id_pago = ?
        ";

        $stmt_actualizar = $mysqli->prepare($sql_actualizar);
        if (!$stmt_actualizar) {
            throw new Exception('Error al preparar actualización de pago: ' . $mysqli->error);
        }

        $stmt_actualizar->bind_param('ssi', $estado_nuevo, $numero_transaccion_final, $id_pago);

        if (!$stmt_actualizar->execute()) {
            $error_msg = $stmt_actualizar->error;
            $stmt_actualizar->close();
            throw new Exception('Error al actualizar pago: ' . $error_msg);
        }

        $stmt_actualizar->close();

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

        // Detectar error específico de duplicado de numero_transaccion
        if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'numero_transaccion') !== false) {
            return [
                'exito' => false,
                'mensaje' => 'Este número de transacción ya está siendo usado por otro pago. Por favor, verifica el código o usa uno diferente.',
                'mensaje_tipo' => 'warning'
            ];
        }

        return [
            'exito' => false,
            'mensaje' => 'Error al marcar el pago como pagado: ' . $e->getMessage(),
            'mensaje_tipo' => 'danger'
        ];
    }
}

