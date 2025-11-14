<?php
/**
 * ========================================================================
 * CONSULTAS SQL DE PEDIDOS - Tienda Seda y Lino
 * ========================================================================
 * Archivo centralizado con todas las consultas relacionadas a pedidos
 * 
 * REEMPLAZO DE TRIGGERS:
 * Este archivo implementa la lógica PHP que reemplaza los siguientes triggers de MySQL:
 * - trg_validar_usuario_activo_pedido: crearPedido()
 * - trg_validar_variante_activa_detalle_pedido: agregarDetallePedido()
 * 
 * Uso:
 *   require_once __DIR__ . '/includes/queries/pedido_queries.php';
 *   $pedidos = obtenerPedidos($mysqli, 10);
 * ========================================================================
 */

/**
 * Obtiene estadísticas de pedidos para el panel de ventas
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array con estadísticas: total_pedidos, pedidos_pendientes, pedidos_preparacion
 * 
 * DEFINICIONES:
 * - pedidos_pendientes: Estado pedido = 'pendiente' PERO el pago NO está aprobado
 * - pedidos_preparacion: Pago aprobado pero falta entregar (no completado/cancelado)
 */
function obtenerEstadisticasPedidos($mysqli) {
    $stats = [];
    
    // Total de pedidos
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total_pedidos FROM Pedidos");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['total_pedidos'] = intval($result['total_pedidos'] ?? 0);
    $stmt->close();
    
    // Pedidos pendientes: estado_pedido = 'pendiente' Y (pago no existe O pago NO está aprobado)
    $sql_pendientes = "
        SELECT COUNT(DISTINCT p.id_pedido) as pedidos_pendientes
        FROM Pedidos p
        LEFT JOIN Pagos pag ON p.id_pedido = pag.id_pedido
        WHERE LOWER(TRIM(p.estado_pedido)) = 'pendiente'
          AND (pag.id_pago IS NULL OR LOWER(TRIM(IFNULL(pag.estado_pago, ''))) != 'aprobado')
    ";
    $stmt = $mysqli->prepare($sql_pendientes);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['pedidos_pendientes'] = intval($result['pedidos_pendientes'] ?? 0);
    $stmt->close();
    
    // Pedidos en preparación: pago aprobado pero falta entregar (no completado/cancelado)
    $sql_preparacion = "
        SELECT COUNT(DISTINCT p.id_pedido) as pedidos_preparacion
        FROM Pedidos p
        INNER JOIN Pagos pag ON p.id_pedido = pag.id_pedido
        WHERE LOWER(TRIM(IFNULL(pag.estado_pago, ''))) = 'aprobado'
          AND LOWER(TRIM(IFNULL(p.estado_pedido, ''))) NOT IN ('completado', 'cancelado')
    ";
    $stmt = $mysqli->prepare($sql_preparacion);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['pedidos_preparacion'] = intval($result['pedidos_preparacion'] ?? 0);
    $stmt->close();
    
    return $stats;
}

/**
 * Función auxiliar: Calcula el total de un pedido desde Detalle_Pedido
 * 
 * Esta función calcula el total sumando cantidad * precio_unitario de todos los detalles.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @return float Total del pedido desde Detalle_Pedido (0 si no hay detalles)
 */
function _calcularTotalPedido($mysqli, $id_pedido) {
    $sql = "
        SELECT COALESCE(SUM(cantidad * precio_unitario), 0) as total
        FROM Detalle_Pedido
        WHERE id_pedido = ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0.0;
    }
    
    $stmt->bind_param('i', $id_pedido);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return floatval($row['total'] ?? 0);
}

/**
 * Obtiene pedidos con información del cliente y total calculado
 * 
 * Esta función retorna una lista de pedidos con información completa del cliente,
 * incluyendo el total calculado.
 * 
 * ESTRATEGIA DE MÚLTIPLES QUERIES:
 * - Query 1: Obtener pedidos básicos con datos de usuario
 * - Query 2: Calcular totales desde Detalle_Pedido (por lote de pedidos)
 * 
 * Esta estrategia simplifica las consultas y mejora el rendimiento al evitar
 * múltiples JOINs y subconsultas anidadas.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $limite Cantidad máxima de pedidos a retornar (0 = sin límite, retorna todos)
 * @param bool $mostrar_inactivos Si es true, muestra pedidos de usuarios inactivos también. Si es false, solo muestra pedidos de usuarios activos.
 * @return array Array asociativo de pedidos con información completa del cliente y total calculado
 */
function obtenerPedidos($mysqli, $limite = 0, $mostrar_inactivos = false) {
    // Validar y sanitizar límite para prevenir inyección SQL
    $limite = max(0, intval($limite));
    $limit_clause = $limite > 0 ? "LIMIT $limite" : "";
    
    // Construir filtro WHERE según mostrar_inactivos
    $where_clause = "";
    if (!$mostrar_inactivos) {
        // Solo mostrar pedidos de usuarios activos
        $where_clause = "WHERE u.activo = 1";
    }
    
    // Query 1: Obtener pedidos básicos con datos de usuario
    $sql = "
        SELECT p.id_pedido, p.fecha_pedido, p.estado_pedido, p.direccion_entrega, 
               p.telefono_contacto, p.observaciones, p.total, p.fecha_actualizacion,
               u.nombre, u.apellido, u.email, u.telefono, u.direccion, u.activo
        FROM Pedidos p 
        JOIN Usuarios u ON p.id_usuario = u.id_usuario 
        $where_clause
        ORDER BY p.fecha_pedido DESC 
        $limit_clause
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pedidos = [];
    $pedidos_ids = [];
    while ($row = $result->fetch_assoc()) {
        $pedidos[$row['id_pedido']] = $row;
        $pedidos_ids[] = $row['id_pedido'];
    }
    $stmt->close();
    
    if (empty($pedidos_ids)) {
        return [];
    }
    
    // Query 2: Calcular totales desde Detalle_Pedido (por lote)
    $placeholders = str_repeat('?,', count($pedidos_ids) - 1) . '?';
    $sql_totales = "
        SELECT id_pedido, COALESCE(SUM(cantidad * precio_unitario), 0) as total
        FROM Detalle_Pedido
        WHERE id_pedido IN ($placeholders)
        GROUP BY id_pedido
    ";
    
    $stmt_totales = $mysqli->prepare($sql_totales);
    if ($stmt_totales) {
        $types = str_repeat('i', count($pedidos_ids));
        $stmt_totales->bind_param($types, ...$pedidos_ids);
        $stmt_totales->execute();
        $result_totales = $stmt_totales->get_result();
        
        $totales = [];
        while ($row = $result_totales->fetch_assoc()) {
            $totales[$row['id_pedido']] = floatval($row['total']);
        }
        $stmt_totales->close();
    } else {
        $totales = [];
    }
    
    // Combinar resultados en PHP
    $pedidos_finales = [];
    foreach ($pedidos as $id_pedido => $pedido) {
        // Usar total de la BD si existe, sino calcular desde detalles
        if ($pedido['total'] !== null && $pedido['total'] > 0) {
            $total_pedido = floatval($pedido['total']);
        } else {
            $total_pedido = $totales[$id_pedido] ?? 0.0;
        }
        
        $pedido['total_pedido'] = $total_pedido;
        $pedidos_finales[] = $pedido;
    }
    
    return $pedidos_finales;
}

/**
 * Obtiene un pedido específico por su ID con información completa
 * 
 * ESTRATEGIA DE MÚLTIPLES QUERIES:
 * - Query 1: Obtener pedido básico con datos de usuario
 * - Query 2: Calcular total desde Detalle_Pedido usando función auxiliar
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @return array|null Array con datos del pedido o null si no existe
 */
function obtenerPedidoPorId($mysqli, $id_pedido) {
    // Query 1: Obtener pedido básico con datos de usuario
    $sql = "
        SELECT p.id_pedido, p.fecha_pedido, p.estado_pedido, p.id_usuario,
               p.direccion_entrega, p.telefono_contacto, p.observaciones, 
               p.total, p.fecha_actualizacion,
               u.nombre, u.apellido, u.email, u.telefono, u.direccion
        FROM Pedidos p 
        JOIN Usuarios u ON p.id_usuario = u.id_usuario 
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
    $pedido = $result->fetch_assoc();
    $stmt->close();
    
    if (!$pedido) {
        return null;
    }
    
    // Query 2: Calcular total usando función auxiliar
    // Usar total de la BD si existe, sino calcular desde detalles
    if ($pedido['total'] !== null && $pedido['total'] > 0) {
        $total_pedido = floatval($pedido['total']);
    } else {
        $total_pedido = _calcularTotalPedido($mysqli, $id_pedido);
    }
    
    $pedido['total_pedido'] = $total_pedido;
    
    return $pedido;
}

/**
 * Obtiene los detalles de un pedido (productos incluidos)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @return array Array de detalles del pedido con información del producto
 */
function obtenerDetallesPedido($mysqli, $id_pedido) {
    // Query: Obtener detalles básicos del pedido
    $sql = "
        SELECT 
            dp.id_detalle,
            dp.id_variante,
            dp.cantidad,
            dp.precio_unitario,
            p.nombre_producto,
            sv.talle,
            sv.color
        FROM Detalle_Pedido dp
        INNER JOIN Stock_Variantes sv ON dp.id_variante = sv.id_variante
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        WHERE dp.id_pedido = ?
        ORDER BY dp.id_detalle
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('i', $id_pedido);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $detalles = [];
    while ($row = $result->fetch_assoc()) {
        $detalles[] = $row;
    }
    $stmt->close();
    
    return $detalles;
}

/**
 * Crea un nuevo pedido en la base de datos
 * Reemplaza la funcionalidad del trigger trg_validar_usuario_activo_pedido: valida usuario activo
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario que realiza el pedido
 * @param string $estado_pedido Estado inicial del pedido (default: 'pendiente')
 * @return int ID del pedido creado o 0 si falló
 */
function crearPedido($mysqli, $id_usuario, $estado_pedido = 'pendiente') {
    // Validar parámetros de entrada
    $id_usuario = intval($id_usuario);
    $estado_pedido = trim($estado_pedido);
    
    if ($id_usuario <= 0) {
        return 0;
    }
    
    // Validar que el usuario existe y está activo (reemplaza trg_validar_usuario_activo_pedido)
    $sql_validar = "SELECT activo FROM Usuarios WHERE id_usuario = ? LIMIT 1";
    $stmt_validar = $mysqli->prepare($sql_validar);
    if (!$stmt_validar) {
        error_log("crearPedido: Error al preparar consulta de validación: " . $mysqli->error);
        return 0;
    }
    
    $stmt_validar->bind_param('i', $id_usuario);
    
    if (!$stmt_validar->execute()) {
        error_log("crearPedido: Error al ejecutar consulta de validación: " . $stmt_validar->error);
        $stmt_validar->close();
        return 0;
    }
    
    $result_validar = $stmt_validar->get_result();
    $usuario = $result_validar->fetch_assoc();
    $stmt_validar->close();
    
    if (!$usuario || !is_array($usuario)) {
        return 0; // Usuario no existe
    }
    
    if (!isset($usuario['activo']) || intval($usuario['activo']) === 0) {
        return 0; // Usuario inactivo
    }
    
    // Crear el pedido
    $sql = "INSERT INTO Pedidos (id_usuario, fecha_pedido, estado_pedido) VALUES (?, NOW(), ?)";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("crearPedido: Error al preparar consulta de inserción: " . $mysqli->error);
        return 0;
    }
    
    $stmt->bind_param('is', $id_usuario, $estado_pedido);
    
    if (!$stmt->execute()) {
        error_log("crearPedido: Error al ejecutar consulta de inserción: " . $stmt->error);
        $stmt->close();
        return 0;
    }
    
    $id_pedido = $mysqli->insert_id;
    $stmt->close();
    
    // Validar que se obtuvo un ID válido
    if (!$id_pedido || $id_pedido <= 0) {
        error_log("crearPedido: Error - insert_id no válido después de insertar");
        return 0;
    }
    
    return intval($id_pedido);
}

/**
 * Agrega un detalle (producto) a un pedido
 * Reemplaza la funcionalidad del trigger trg_validar_variante_activa_detalle_pedido: valida variante y producto activos
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @param int $id_variante ID de la variante del producto
 * @param int $cantidad Cantidad del producto
 * @param float $precio_unitario Precio unitario del producto
 * @return bool True si se insertó correctamente
 */
function agregarDetallePedido($mysqli, $id_pedido, $id_variante, $cantidad, $precio_unitario) {
    // Validar parámetros de entrada
    $id_pedido = intval($id_pedido);
    $id_variante = intval($id_variante);
    $cantidad = intval($cantidad);
    $precio_unitario = floatval($precio_unitario);
    
    if ($id_pedido <= 0 || $id_variante <= 0 || $cantidad <= 0 || $precio_unitario <= 0) {
        return false;
    }
    
    // Validar que la variante y producto estén activos (reemplaza trg_validar_variante_activa_detalle_pedido)
    $sql_validar = "SELECT sv.activo as variante_activa, p.activo as producto_activo FROM Stock_Variantes sv INNER JOIN Productos p ON sv.id_producto = p.id_producto WHERE sv.id_variante = ? LIMIT 1";
    
    $stmt_validar = $mysqli->prepare($sql_validar);
    if (!$stmt_validar) {
        error_log("agregarDetallePedido: Error al preparar consulta de validación: " . $mysqli->error);
        return false;
    }
    
    $stmt_validar->bind_param('i', $id_variante);
    
    if (!$stmt_validar->execute()) {
        error_log("agregarDetallePedido: Error al ejecutar consulta de validación: " . $stmt_validar->error);
        $stmt_validar->close();
        return false;
    }
    
    $result_validar = $stmt_validar->get_result();
    $datos = $result_validar->fetch_assoc();
    $stmt_validar->close();
    
    if (!$datos || !is_array($datos)) {
        return false; // La variante o producto no existe
    }
    
    if (!isset($datos['variante_activa']) || intval($datos['variante_activa']) === 0) {
        return false; // Variante inactiva
    }
    
    if (!isset($datos['producto_activo']) || intval($datos['producto_activo']) === 0) {
        return false; // Producto inactivo
    }
    
    // Insertar detalle del pedido
    $sql = "INSERT INTO Detalle_Pedido (id_pedido, id_variante, cantidad, precio_unitario) VALUES (?, ?, ?, ?)";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("agregarDetallePedido: Error al preparar consulta de inserción: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('iiid', $id_pedido, $id_variante, $cantidad, $precio_unitario);
    
    if (!$stmt->execute()) {
        error_log("agregarDetallePedido: Error al ejecutar consulta de inserción: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $stmt->close();
    return true;
}

/**
 * Actualiza el estado de un pedido
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @param string $nuevo_estado Nuevo estado del pedido
 * @return bool True si se actualizó correctamente
 */
function actualizarEstadoPedido($mysqli, $id_pedido, $nuevo_estado) {
    $sql = "
        UPDATE Pedidos 
        SET estado_pedido = ?,
            fecha_actualizacion = NOW()
        WHERE id_pedido = ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('si', $nuevo_estado, $id_pedido);
    $resultado = $stmt->execute();
    $stmt->close();
    
    return $resultado;
}

/**
 * Actualiza un pedido completo con todos sus campos editables
 * Solo actualiza fecha_actualizacion cuando cambia el estado del pedido
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @param string $estado_pedido Estado del pedido
 * @param string|null $direccion_entrega Dirección de entrega
 * @param string|null $telefono_contacto Teléfono de contacto
 * @param string|null $observaciones Observaciones del pedido
 * @param float|null $total Total del pedido (opcional, se calcula si es null)
 * @return bool True si se actualizó correctamente
 */
function actualizarPedidoCompleto($mysqli, $id_pedido, $estado_pedido, $direccion_entrega = null, $telefono_contacto = null, $observaciones = null, $total = null) {
    // Obtener el estado actual del pedido para comparar
    $sql_estado_actual = "SELECT estado_pedido FROM Pedidos WHERE id_pedido = ?";
    $stmt_estado = $mysqli->prepare($sql_estado_actual);
    if (!$stmt_estado) {
        return false;
    }
    
    $stmt_estado->bind_param('i', $id_pedido);
    $stmt_estado->execute();
    $result_estado = $stmt_estado->get_result();
    $row_estado = $result_estado->fetch_assoc();
    $estado_anterior = $row_estado ? trim(strtolower($row_estado['estado_pedido'])) : '';
    $estado_nuevo = trim(strtolower($estado_pedido));
    $stmt_estado->close();
    
    // Solo actualizar fecha_actualizacion si cambió el estado
    $cambio_estado = ($estado_anterior !== $estado_nuevo);
    
    if ($cambio_estado) {
        // Si cambió el estado, actualizar fecha_actualizacion
        $sql = "
            UPDATE Pedidos 
            SET estado_pedido = ?,
                direccion_entrega = ?,
                telefono_contacto = ?,
                observaciones = ?,
                total = ?,
                fecha_actualizacion = NOW()
            WHERE id_pedido = ?
        ";
    } else {
        // Si no cambió el estado, NO actualizar fecha_actualizacion
        $sql = "
            UPDATE Pedidos 
            SET estado_pedido = ?,
                direccion_entrega = ?,
                telefono_contacto = ?,
                observaciones = ?,
                total = ?
            WHERE id_pedido = ?
        ";
    }
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('ssssdi', $estado_pedido, $direccion_entrega, $telefono_contacto, $observaciones, $total, $id_pedido);
    $resultado = $stmt->execute();
    $stmt->close();
    
    return $resultado;
}

/**
 * Obtiene el tiempo promedio de procesamiento de pedidos
 * 
 * Esta función calcula el tiempo promedio que tarda un pedido en ser procesado,
 * desde fecha_pedido hasta fecha_actualizacion, para pedidos que están en estado
 * 'en_viaje' o 'completado'. Útil para métricas y reportes de rendimiento.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array asociativo con 'tiempo_promedio_horas' (float) y 'tiempo_promedio_dias' (float)
 */
function obtenerTiempoPromedioProcesamiento($mysqli) {
    $sql = "
        SELECT 
            AVG(TIMESTAMPDIFF(HOUR, fecha_pedido, fecha_actualizacion)) as tiempo_promedio_horas,
            AVG(TIMESTAMPDIFF(DAY, fecha_pedido, fecha_actualizacion)) as tiempo_promedio_dias
        FROM Pedidos
        WHERE estado_pedido IN ('en_viaje', 'completado')
        AND fecha_actualizacion IS NOT NULL
        AND fecha_pedido IS NOT NULL
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [
            'tiempo_promedio_horas' => 0.0,
            'tiempo_promedio_dias' => 0.0
        ];
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return [
        'tiempo_promedio_horas' => floatval($row['tiempo_promedio_horas'] ?? 0),
        'tiempo_promedio_dias' => floatval($row['tiempo_promedio_dias'] ?? 0)
    ];
}

/**
 * Obtiene pedidos con más tiempo en su estado actual
 * Calcula la diferencia entre fecha_actualizacion (o fecha_pedido si no hay actualización) y ahora
 * 
 * IMPORTANTE: fecha_actualizacion solo se actualiza cuando cambia el estado del pedido,
 * gracias a la lógica en actualizarPedidoCompleto(). Esto asegura que el tiempo calculado
 * refleje correctamente cuánto tiempo lleva el pedido en su estado actual.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $limite Cantidad de pedidos a retornar (default: 10)
 * @return array Array de pedidos con información de tiempo en estado
 */
function obtenerPedidosTiempoEstado($mysqli, $limite = 10) {
    // ESTRATEGIA DE MÚLTIPLES QUERIES SIMPLES:
    // Query 1: Obtener pedidos básicos con información de usuario
    $sql_pedidos = "
        SELECT 
            p.id_pedido,
            p.fecha_pedido,
            p.estado_pedido,
            p.fecha_actualizacion,
            p.total,
            u.nombre,
            u.apellido,
            u.email
        FROM Pedidos p
        INNER JOIN Usuarios u ON p.id_usuario = u.id_usuario
        WHERE p.estado_pedido NOT IN ('completado', 'cancelado')
        ORDER BY p.id_pedido DESC
        LIMIT ?
    ";
    
    $stmt_pedidos = $mysqli->prepare($sql_pedidos);
    if (!$stmt_pedidos) {
        return [];
    }
    
    $stmt_pedidos->bind_param('i', $limite);
    $stmt_pedidos->execute();
    $result_pedidos = $stmt_pedidos->get_result();
    
    $pedidos = [];
    $pedidos_ids = [];
    while ($row = $result_pedidos->fetch_assoc()) {
        $pedidos[$row['id_pedido']] = $row;
        $pedidos_ids[] = $row['id_pedido'];
    }
    $stmt_pedidos->close();
    
    if (empty($pedidos_ids)) {
        return [];
    }
    
    // Query 2: Calcular totales desde Detalle_Pedido (por lote)
    $placeholders = str_repeat('?,', count($pedidos_ids) - 1) . '?';
    $sql_totales = "
        SELECT id_pedido, SUM(cantidad * precio_unitario) as total_detalle
        FROM Detalle_Pedido
        WHERE id_pedido IN ($placeholders)
        GROUP BY id_pedido
    ";
    
    $stmt_totales = $mysqli->prepare($sql_totales);
    if ($stmt_totales) {
        $types = str_repeat('i', count($pedidos_ids));
        $stmt_totales->bind_param($types, ...$pedidos_ids);
        $stmt_totales->execute();
        $result_totales = $stmt_totales->get_result();
        
        $totales = [];
        while ($row = $result_totales->fetch_assoc()) {
            $totales[$row['id_pedido']] = floatval($row['total_detalle'] ?? 0);
        }
        $stmt_totales->close();
    } else {
        $totales = [];
    }
    
    // Combinar resultados en PHP y calcular tiempos
    $pedidos_finales = [];
    foreach ($pedidos as $id_pedido => $pedido) {
        // Calcular total: usar total de BD si existe, sino calcular desde detalles
        if ($pedido['total'] !== null && $pedido['total'] > 0) {
            $total_pedido = floatval($pedido['total']);
        } else {
            $total_pedido = $totales[$id_pedido] ?? 0.0;
        }
        
        // Calcular fecha base para tiempo en estado
        $fecha_base = $pedido['fecha_actualizacion'] ?? $pedido['fecha_pedido'];
        
        // Calcular horas y días en estado (en PHP usando DateTime)
        $fecha_base_obj = new DateTime($fecha_base);
        $ahora = new DateTime();
        $diferencia = $ahora->diff($fecha_base_obj);
        $horas_en_estado = ($diferencia->days * 24) + $diferencia->h;
        $dias_en_estado = $diferencia->days;
        
        // Solo incluir si tiene horas en estado > 0
        if ($horas_en_estado > 0) {
            $pedidos_finales[] = [
                'id_pedido' => $pedido['id_pedido'],
                'fecha_pedido' => $pedido['fecha_pedido'],
                'estado_pedido' => $pedido['estado_pedido'],
                'fecha_actualizacion' => $pedido['fecha_actualizacion'],
                'nombre' => $pedido['nombre'],
                'apellido' => $pedido['apellido'],
                'email' => $pedido['email'],
                'total_pedido' => $total_pedido,
                'horas_en_estado' => $horas_en_estado,
                'dias_en_estado' => $dias_en_estado
            ];
        }
    }
    
    // Ordenar por horas_en_estado DESC
    usort($pedidos_finales, function($a, $b) {
        return $b['horas_en_estado'] <=> $a['horas_en_estado'];
    });
    
    return $pedidos_finales;
}

/**
 * Obtiene los top productos más vendidos por variante (talle/color)
 * Suma las cantidades vendidas desde Detalle_Pedido, excluyendo pedidos cancelados o devueltos
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $limite Cantidad de productos a retornar (default: 10)
 * @return array Array de productos con información de ventas por variante
 */
function obtenerTopProductosVendidos($mysqli, $limite = 10) {
    $sql = "
        SELECT 
            p.id_producto,
            p.nombre_producto,
            sv.talle,
            sv.color,
            SUM(dp.cantidad) as unidades_vendidas,
            c.nombre_categoria
        FROM Detalle_Pedido dp
        INNER JOIN Stock_Variantes sv ON dp.id_variante = sv.id_variante
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
        INNER JOIN Pedidos ped ON dp.id_pedido = ped.id_pedido
        WHERE ped.estado_pedido NOT IN ('cancelado')
        AND p.activo = 1
        GROUP BY p.id_producto, p.nombre_producto, sv.talle, sv.color, c.nombre_categoria
        ORDER BY unidades_vendidas DESC
        LIMIT ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('i', $limite);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
    
    $stmt->close();
    return $productos;
}

/**
 * Verifica que un detalle de pedido pertenece a un usuario específico
 * 
 * Esta función verifica que un detalle de pedido (id_detalle) pertenece a un pedido
 * del usuario especificado. Útil para validar permisos antes de permitir acciones
 * como modificaciones.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_detalle_pedido ID del detalle de pedido a verificar
 * @param int $id_usuario ID del usuario propietario del pedido
 * @return array|null Array con datos del pedido (id_pedido, id_usuario) si pertenece al usuario, null en caso contrario
 */
function verificarDetallePedidoUsuario($mysqli, $id_detalle_pedido, $id_usuario) {
    $sql = "
        SELECT dp.id_pedido, p.id_usuario
        FROM Detalle_Pedido dp
        INNER JOIN Pedidos p ON dp.id_pedido = p.id_pedido
        WHERE dp.id_detalle = ? AND p.id_usuario = ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('ii', $id_detalle_pedido, $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $verificacion = $result->fetch_assoc();
    $stmt->close();
    
    return $verificacion;
}

/**
 * Cancela automáticamente pedidos pendientes que tienen más de X días sin pago aprobado
 * 
 * Esta función busca pedidos en estado 'pendiente' sin pago aprobado que tienen más
 * de $dias_limite días desde su creación y los cancela automáticamente.
 * Restaura stock si había sido descontado y actualiza el estado del pago a 'cancelado'.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $dias_limite Número de días límite antes de cancelar (default: 60)
 * @return array Array con información de cancelaciones: ['total' => int, 'pedidos' => array]
 */
function cancelarPedidosPendientesAntiguos($mysqli, $dias_limite = 60) {
    $stock_queries_path = __DIR__ . '/stock_queries.php';
    if (!file_exists($stock_queries_path)) {
        error_log("ERROR: No se pudo encontrar stock_queries.php en " . $stock_queries_path);
        die("Error crítico: Archivo de consultas de stock no encontrado. Por favor, contacta al administrador.");
    }
    require_once $stock_queries_path;
    
    $pago_queries_path = __DIR__ . '/pago_queries.php';
    if (!file_exists($pago_queries_path)) {
        error_log("ERROR: No se pudo encontrar pago_queries.php en " . $pago_queries_path);
        die("Error crítico: Archivo de consultas de pago no encontrado. Por favor, contacta al administrador.");
    }
    require_once $pago_queries_path;
    
    $dias_limite = max(1, intval($dias_limite));
    $resultado = [
        'total' => 0,
        'pedidos' => []
    ];
    
    // Buscar pedidos pendientes sin pago aprobado con más de X días
    $sql = "
        SELECT DISTINCT p.id_pedido, p.fecha_pedido, p.id_usuario, p.estado_pedido
        FROM Pedidos p
        LEFT JOIN Pagos pag ON p.id_pedido = pag.id_pedido
        WHERE LOWER(TRIM(p.estado_pedido)) = 'pendiente'
          AND (pag.id_pago IS NULL OR LOWER(TRIM(IFNULL(pag.estado_pago, ''))) != 'aprobado')
          AND DATEDIFF(NOW(), p.fecha_pedido) > ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("cancelarPedidosPendientesAntiguos: Error al preparar consulta: " . $mysqli->error);
        return $resultado;
    }
    
    $stmt->bind_param('i', $dias_limite);
    if (!$stmt->execute()) {
        error_log("cancelarPedidosPendientesAntiguos: Error al ejecutar consulta: " . $stmt->error);
        $stmt->close();
        return $resultado;
    }
    
    $result = $stmt->get_result();
    $pedidos = [];
    while ($row = $result->fetch_assoc()) {
        $pedidos[] = $row;
    }
    $stmt->close();
    
    if (empty($pedidos)) {
        return $resultado;
    }
    
    // Procesar cada pedido
    foreach ($pedidos as $pedido) {
        $id_pedido = intval($pedido['id_pedido']);
        $id_usuario = intval($pedido['id_usuario']);
        
        $mysqli->begin_transaction();
        
        try {
            // Obtener información del pago si existe
            $pago = obtenerPagoPorPedido($mysqli, $id_pedido);
            
            // Si el pago estaba aprobado, restaurar stock (no debería pasar para pendientes, pero por seguridad)
            if ($pago && $pago['estado_pago'] === 'aprobado') {
                if (!revertirStockPedido($mysqli, $id_pedido, $id_usuario, "Cancelación automática: pedido pendiente más de {$dias_limite} días")) {
                    throw new Exception("Error al restaurar stock del pedido #{$id_pedido}");
                }
            }
            
            // Actualizar estado del pedido a cancelado
            if (!actualizarEstadoPedido($mysqli, $id_pedido, 'cancelado')) {
                throw new Exception("Error al cancelar el pedido #{$id_pedido}");
            }
            
            // Cancelar el pago si existe y no está cancelado
            if ($pago && $pago['estado_pago'] !== 'cancelado') {
                if (!actualizarEstadoPago($mysqli, $pago['id_pago'], 'cancelado')) {
                    throw new Exception("Error al cancelar el pago del pedido #{$id_pedido}");
                }
            }
            
            $mysqli->commit();
            
            // Calcular días de antigüedad
            $fecha_pedido = new DateTime($pedido['fecha_pedido']);
            $fecha_actual = new DateTime();
            $dias_antiguedad = $fecha_actual->diff($fecha_pedido)->days;
            
            $resultado['total']++;
            $resultado['pedidos'][] = [
                'id_pedido' => $id_pedido,
                'fecha_pedido' => $pedido['fecha_pedido'],
                'dias_antiguedad' => $dias_antiguedad
            ];
            
            error_log("Pedido #{$id_pedido} cancelado automáticamente por antigüedad (más de {$dias_limite} días)");
            
        } catch (Exception $e) {
            $mysqli->rollback();
            error_log("Error al cancelar pedido #{$id_pedido} automáticamente: " . $e->getMessage());
        }
    }
    
    return $resultado;
}

