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

// Consolidated helper: load shared Detalle_Pedido helper
require_once __DIR__ . '/detalle_pedido_queries.php';

/**
 * Crea un nuevo pedido en la base de datos
 * Reemplaza la funcionalidad del trigger trg_validar_usuario_activo_pedido: valida usuario activo
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario que realiza el pedido
 * @param string $estado_pedido Estado inicial del pedido (default: 'pendiente')
 * @param string|null $observaciones Observaciones del pedido (opcional, máximo 500 caracteres)
 * @return int ID del pedido creado o 0 si falló
 */
function crearPedido($mysqli, $id_usuario, $estado_pedido = 'pendiente', $observaciones = null) {
    // Validar parámetros de entrada
    $id_usuario = intval($id_usuario);
    $estado_pedido = trim($estado_pedido);
    
    if ($id_usuario <= 0) {
        return 0;
    }
    
    // Validar observaciones si se proporciona
    if ($observaciones !== null) {
        $observaciones = trim($observaciones);
        // Si está vacío después de trim, convertir a null
        if (empty($observaciones)) {
            $observaciones = null;
        } elseif (strlen($observaciones) > 500) {
            error_log("crearPedido: Observaciones exceden longitud máxima (500 caracteres)");
            return 0;
        }
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
    
    // Crear el pedido con o sin observaciones
    if ($observaciones !== null) {
        $sql = "INSERT INTO Pedidos (id_usuario, fecha_pedido, estado_pedido, observaciones) VALUES (?, NOW(), ?, ?)";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            error_log("crearPedido: Error al preparar consulta de inserción: " . $mysqli->error);
            return 0;
        }
        $stmt->bind_param('iss', $id_usuario, $estado_pedido, $observaciones);
    } else {
        $sql = "INSERT INTO Pedidos (id_usuario, fecha_pedido, estado_pedido) VALUES (?, NOW(), ?)";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            error_log("crearPedido: Error al preparar consulta de inserción: " . $mysqli->error);
            return 0;
        }
        $stmt->bind_param('is', $id_usuario, $estado_pedido);
    }
    
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
 * Actualiza el estado de un pedido con validación de estados permitidos
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @param string $nuevo_estado Nuevo estado del pedido
 * @param array $estados_permitidos Array de estados permitidos para la actualización
 * @return bool True si se actualizó correctamente, false si el estado actual no está permitido
 */
function actualizarEstadoPedidoConValidacion($mysqli, $id_pedido, $nuevo_estado, $estados_permitidos = []) {
    // Si hay estados permitidos, validar que el estado actual esté en la lista
    if (!empty($estados_permitidos)) {
        $sql_validar = "SELECT estado_pedido FROM Pedidos WHERE id_pedido = ?";
        $stmt_validar = $mysqli->prepare($sql_validar);
        if (!$stmt_validar) {
            return false;
        }
        
        $stmt_validar->bind_param('i', $id_pedido);
        $stmt_validar->execute();
        $result_validar = $stmt_validar->get_result();
        $pedido_actual = $result_validar->fetch_assoc();
        $stmt_validar->close();
        
        if (!$pedido_actual) {
            return false; // Pedido no existe
        }
        
        $estado_actual = trim(strtolower($pedido_actual['estado_pedido']));
        $estados_permitidos_lower = array_map('strtolower', array_map('trim', $estados_permitidos));
        
        if (!in_array($estado_actual, $estados_permitidos_lower)) {
            return false; // Estado actual no está permitido
        }
    }
    
    // Actualizar estado del pedido
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
    // Validar longitud de dirección de entrega según diccionario: 5-100 caracteres
    if ($direccion_entrega !== null) {
        $direccion_entrega = trim($direccion_entrega);
        if (!empty($direccion_entrega)) {
            $longitud_direccion = strlen($direccion_entrega);
            if ($longitud_direccion < 5 || $longitud_direccion > 100) {
                error_log("actualizarPedidoCompleto: Dirección de entrega fuera de rango (5-100 caracteres). Longitud: {$longitud_direccion}");
                return false;
            }
        }
    }
    
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
            // IMPORTANTE: Cancelación forzada por cancelación de pedido - se cancela SIEMPRE
            // incluso si el pago está en recorrido activo (aprobado, pendiente_aprobacion)
            if ($pago && $pago['estado_pago'] !== 'cancelado' && $pago['estado_pago'] !== 'rechazado') {
                error_log("cancelarPedidosPendientesAntiguos: Cancelando pago #{$pago['id_pago']} forzadamente por cancelación de pedido (estado pago anterior: {$pago['estado_pago']})");
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

/**
 * Valida si una transición de estado de pedido está permitida según la matriz de transiciones
 * 
 * NOTA: Esta función ahora delega a StateValidator para centralizar la lógica de validación.
 * Se mantiene por compatibilidad hacia atrás.
 * 
 * @param string $estado_actual Estado actual del pedido
 * @param string $nuevo_estado Nuevo estado al que se quiere cambiar
 * @return bool True si la transición está permitida
 * @throws Exception Si la transición no está permitida
 */
function validarTransicionPedido($estado_actual, $nuevo_estado) {
    // Cargar StateValidator si no está cargado
    require_once __DIR__ . '/../state_validator.php';
    
    // Delegar validación a StateValidator
    return StateValidator::canTransition($estado_actual, $nuevo_estado, 'pedido');
}

/**
 * Valida que no se intente retroceder estados de pedido ilógicamente
 * 
 * @param string $estado_anterior Estado anterior del pedido
 * @param string $estado_nuevo Nuevo estado del pedido
 * @return void
 * @throws Exception Si se intenta un retroceso ilógico
 */
function _validarRetrocesosIlógicos($estado_anterior, $estado_nuevo) {
    $estado_anterior_norm = strtolower(trim($estado_anterior));
    $estado_nuevo_norm = strtolower(trim($estado_nuevo));
    
    // Bloquear retroceso: preparacion → pendiente
    if ($estado_anterior_norm === 'preparacion' && $estado_nuevo_norm === 'pendiente') {
        throw new Exception('No se puede retroceder un pedido de preparación a pendiente. Si el pago cambió de estado, el pedido debe cancelarse en lugar de retroceder.');
    }
    
    // Bloquear retroceso: en_viaje → preparacion
    if ($estado_anterior_norm === 'en_viaje' && $estado_nuevo_norm === 'preparacion') {
        throw new Exception('No se puede retroceder un pedido en viaje a preparación');
    }
    
    // Bloquear retroceso: completado → preparacion o en_viaje
    if ($estado_anterior_norm === 'completado' && in_array($estado_nuevo_norm, ['preparacion', 'en_viaje'])) {
        throw new Exception('No se puede retroceder un pedido completado');
    }
    
    // Bloquear cambio desde devolucion (estado terminal)
    if ($estado_anterior_norm === 'devolucion') {
        throw new Exception('No se puede cambiar el estado de un pedido devuelto (estado terminal)');
    }
}

/**
 * Valida que un pedido en estado avanzado tenga pago aprobado
 * 
 * Estados avanzados: en_viaje, completado, devolucion
 * 
 * @param array|null $pago_actual Datos del pago actual
 * @param string|null $estado_pago Estado del pago
 * @param string $nuevo_estado_pedido Nuevo estado del pedido
 * @return void
 * @throws Exception Si el pago no está aprobado o está rechazado/cancelado
 */
function _validarEstadoPagoParaEstadosAvanzados($pago_actual, $estado_pago, $nuevo_estado_pedido) {
    // Validar que el pago esté aprobado
    if (!$pago_actual) {
        throw new Exception("No se puede cambiar el pedido a '{$nuevo_estado_pedido}' sin un pago asociado");
    }
    
    $estado_pago_norm = strtolower(trim($estado_pago ?? ''));
    if ($estado_pago_norm !== 'aprobado') {
        throw new Exception("No se puede cambiar el pedido a '{$nuevo_estado_pedido}' sin pago aprobado. Estado actual del pago: {$estado_pago}");
    }
    
    // Validar explícitamente que el pago NO esté rechazado o cancelado
    if (in_array($estado_pago_norm, ['rechazado', 'cancelado'])) {
        throw new Exception("No se puede cambiar el pedido a '{$nuevo_estado_pedido}' con pago rechazado o cancelado");
    }
}

/**
 * Valida que un pedido pueda pasar a preparación con el estado de pago actual
 * 
 * REGLA DE NEGOCIO: Un pedido NO puede estar en preparacion si el pago está en pendiente_aprobacion
 * El pedido solo puede pasar a preparacion cuando el pago está aprobado
 * 
 * @param array|null $pago_actual Datos del pago actual
 * @param string|null $estado_pago Estado del pago
 * @return void
 * @throws Exception Si el pago no está aprobado o está rechazado/cancelado
 */
function _validarPreparacionConPago($pago_actual, $estado_pago) {
    if (!$pago_actual) {
        error_log("_validarPreparacionConPago: No hay pago asociado");
        throw new Exception('No se puede cambiar el pedido a preparación sin un pago asociado');
    }
    
    $estado_pago_norm = strtolower(trim($estado_pago ?? ''));
    error_log("_validarPreparacionConPago: Estado pago recibido: '{$estado_pago}', normalizado: '{$estado_pago_norm}'");
    
    // BLOQUEAR: Solo permitir preparacion si pago está aprobado (NO pendiente_aprobacion)
    if ($estado_pago_norm !== 'aprobado') {
        error_log("_validarPreparacionConPago: Estado pago NO es aprobado. Estado normalizado: '{$estado_pago_norm}'");
        if (in_array($estado_pago_norm, ['rechazado', 'cancelado'])) {
            throw new Exception('No se puede cambiar el pedido a preparación con pago rechazado o cancelado');
        }
        if ($estado_pago_norm === 'pendiente_aprobacion') {
            throw new Exception('No se puede cambiar el pedido a preparación con pago pendiente de aprobación. El pago debe estar aprobado primero. Cuando se apruebe el pago, el pedido pasará automáticamente a preparación.');
        }
        throw new Exception('No se puede cambiar el pedido a preparación. El pago debe estar aprobado. Estado actual del pago: ' . $estado_pago);
    }
    
    error_log("_validarPreparacionConPago: Validación exitosa - pago está aprobado");
}

/**
 * Procesa el cambio de pedido a estado 'en_viaje'
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @param string $estado_pedido_anterior Estado anterior del pedido
 * @param string|null $estado_pago Estado del pago
 * @return void
 * @throws Exception Si hay error en la validación o actualización
 */
function _cambiarPedidoAEnViaje($mysqli, $id_pedido, $estado_pedido_anterior, $estado_pago) {
    require_once __DIR__ . '/../estado_helpers.php';
    
    // Normalizar estados
    $estado_pedido_anterior = normalizarEstado($estado_pedido_anterior);
    $estado_pago = normalizarEstado($estado_pago);
    
    if ($estado_pedido_anterior !== 'preparacion') {
        throw new Exception('Solo se puede enviar un pedido que está en estado de preparación');
    }
    
    // Validar explícitamente que el pago NO esté rechazado o cancelado
    if (in_array($estado_pago, ['rechazado', 'cancelado'])) {
        throw new Exception('No se puede enviar un pedido con pago rechazado o cancelado');
    }
    
    if ($estado_pago !== 'aprobado') {
        throw new Exception('No se puede enviar pedido sin pago aprobado');
    }
    
    if (!actualizarEstadoPedido($mysqli, $id_pedido, 'en_viaje')) {
        throw new Exception('Error al actualizar estado del pedido a en_viaje. Puede que el pedido haya sido modificado por otro proceso.');
    }
}

/**
 * Procesa el cambio de pedido a estado 'completado'
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @param string $estado_pedido_anterior Estado anterior del pedido
 * @param string|null $estado_pago Estado del pago
 * @return void
 * @throws Exception Si hay error en la validación o actualización
 */
function _cambiarPedidoACompletado($mysqli, $id_pedido, $estado_pedido_anterior, $estado_pago) {
    require_once __DIR__ . '/../estado_helpers.php';
    
    // Normalizar estados
    $estado_pedido_anterior = normalizarEstado($estado_pedido_anterior);
    $estado_pago = normalizarEstado($estado_pago);
    
    if (!in_array($estado_pedido_anterior, ['preparacion', 'en_viaje'])) {
        throw new Exception('Solo se puede completar un pedido que está en preparación o en viaje');
    }
    
    // Validar explícitamente que el pago NO esté rechazado o cancelado
    if (in_array($estado_pago, ['rechazado', 'cancelado'])) {
        throw new Exception('No se puede completar un pedido con pago rechazado o cancelado');
    }
    
    if ($estado_pago !== 'aprobado') {
        throw new Exception('No se puede completar pedido sin pago aprobado');
    }
    
    if (!actualizarEstadoPedido($mysqli, $id_pedido, 'completado')) {
        throw new Exception('Error al actualizar estado del pedido a completado. Puede que el pedido haya sido modificado por otro proceso.');
    }
}

/**
 * Procesa el cambio de pedido a estado 'devolucion'
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @param string $estado_pedido_anterior Estado anterior del pedido
 * @param string|null $estado_pago Estado del pago
 * @param int|null $id_usuario ID del usuario que realiza la acción (opcional)
 * @return void
 * @throws Exception Si hay error en la validación o actualización
 */
function _cambiarPedidoADevolucion($mysqli, $id_pedido, $estado_pedido_anterior, $estado_pago, $id_usuario) {
    // Cargar funciones de stock necesarias
    require_once __DIR__ . '/../queries_helper.php';
    require_once __DIR__ . '/../estado_helpers.php';
    cargarArchivoQueries('stock_queries', __DIR__);
    
    // Normalizar estados
    $estado_pedido_anterior = normalizarEstado($estado_pedido_anterior);
    $estado_pago = normalizarEstado($estado_pago);
    
    if ($estado_pedido_anterior !== 'en_viaje') {
        throw new Exception('Solo se puede devolver un pedido que está en viaje. Los pedidos completados son terminales y no admiten cambios.');
    }
    
    // Validar explícitamente que el pago NO esté rechazado o cancelado
    if (in_array($estado_pago, ['rechazado', 'cancelado'])) {
        throw new Exception('No se puede devolver un pedido con pago rechazado o cancelado');
    }
    
    if ($estado_pago !== 'aprobado') {
        throw new Exception('No se puede devolver pedido sin pago aprobado');
    }
    
    // Obtener detalles del pedido para restaurar stock
    $detalles = obtenerDetallesPedido($mysqli, $id_pedido);
    
    if (empty($detalles)) {
        throw new Exception('El pedido no tiene detalles para devolver');
    }
    
    // Restaurar stock mediante movimientos tipo 'devolucion'
    foreach ($detalles as $detalle) {
        $id_variante = intval($detalle['id_variante']);
        $cantidad = intval($detalle['cantidad']);
        
        $observaciones = "Devolución de pedido #{$id_pedido}";
        if (!registrarMovimientoStock($mysqli, $id_variante, 'devolucion', $cantidad, $id_usuario, $id_pedido, $observaciones, true)) {
            throw new Exception("Error al registrar devolución de stock para variante #{$id_variante}");
        }
    }
    
    if (!actualizarEstadoPedido($mysqli, $id_pedido, 'devolucion')) {
        throw new Exception('Error al actualizar estado del pedido a devolucion. Puede que el pedido haya sido modificado por otro proceso.');
    }
}

/**
 * Procesa la cancelación de un pedido con restauración de stock si corresponde
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @param string $estado_pedido_anterior Estado anterior del pedido
 * @param array|null $pago_actual Datos del pago (opcional)
 * @param string|null $estado_pago Estado del pago
 * @param int|null $id_usuario ID del usuario que realiza la acción (opcional)
 * @return void
 * @throws Exception Si hay error en la validación o actualización
 */
function _cancelarPedidoConValidaciones($mysqli, $id_pedido, $estado_pedido_anterior, $pago_actual, $estado_pago, $id_usuario) {
    // Cargar funciones necesarias
    require_once __DIR__ . '/../queries_helper.php';
    require_once __DIR__ . '/../estado_helpers.php';
    cargarArchivoQueries('pago_queries', __DIR__);
    cargarArchivoQueries('stock_queries', __DIR__);
    
    // Normalizar estados para comparación
    $estado_pedido_anterior = normalizarEstado($estado_pedido_anterior);
    $estado_pago_norm = $estado_pago ? normalizarEstado($estado_pago) : null;
    
    // Validación: Solo estados iniciales pueden cancelarse normalmente
    // EXCEPCIÓN: Permitir cancelar desde 'preparacion' cuando el pago está cancelado/rechazado
    // Esto permite corregir inconsistencias donde el pago fue cancelado pero el pedido quedó en preparación
    if (!puedeCancelarPedido($estado_pedido_anterior)) {
        // Verificar si es el caso excepcional: preparación con pago cancelado/rechazado
        if ($estado_pedido_anterior === 'preparacion' && in_array($estado_pago_norm, ['cancelado', 'rechazado'])) {
            // Permitir cancelación: el pago ya está cancelado/rechazado, no hay stock descontado
            error_log("Cancelación pedido #{$id_pedido}: Excepción - Preparación con pago cancelado/rechazado. Estado pago: {$estado_pago}");
        } else {
            // Bloquear cancelación: pedido en recorrido activo con pago aprobado o pendiente
            throw new Exception('Solo se puede cancelar un pedido que está en estado inicial (pendiente o pendiente_validado_stock). Un pedido en recorrido activo no puede cancelarse.');
        }
    }
    
    // CASO A: Cancelar antes de descontar stock
    // Condición: Pago NO está aprobado (pendiente, pendiente_aprobacion, cancelado, rechazado)
    // Acción: Solo actualizar estados, NO revertir stock (no hay stock descontado)
    if ($pago_actual && !in_array($estado_pago_norm, ['aprobado'])) {
        // No hay stock descontado, solo actualizar estados
        error_log("Cancelación pedido #{$id_pedido}: Caso A - Antes de descontar stock. Estado pago: {$estado_pago}");
    }
    // CASO B: Cancelar con pago aprobado (pero no enviado)
    // Condición: Pago está aprobado Y pedido NO está en_viaje
    // Acción: Revertir stock (el stock fue descontado al aprobar el pago)
    elseif ($pago_actual && $estado_pago_norm === 'aprobado' && !in_array($estado_pedido_anterior, ['en_viaje'])) {
        error_log("Cancelación pedido #{$id_pedido}: Caso B - Con pago aprobado pero no enviado. Restaurando stock.");
        if (!revertirStockPedido($mysqli, $id_pedido, $id_usuario, "Pedido cancelado manualmente")) {
            throw new Exception('Error al restaurar stock del pedido');
        }
    }
    // CASO C: Cancelar después de envío real (en_viaje)
    // Condición: Pedido está en_viaje Y pago está aprobado
    // Acción: Revertir stock SOLO si el paquete realmente vuelve al stock físico
    // NOTA: En MVP, se permite pero requiere control físico del retorno
    elseif ($estado_pedido_anterior === 'en_viaje' && $pago_actual && $estado_pago_norm === 'aprobado') {
        error_log("Cancelación pedido #{$id_pedido}: Caso C - Después de envío real (en_viaje). REQUIERE CONTROL FÍSICO DEL RETORNO.");
        // IMPORTANTE: Solo revertir si el paquete realmente vuelve al stock físico
        // En MVP, se permite pero debe verificarse manualmente que el producto regresó
        if (!revertirStockPedido($mysqli, $id_pedido, $id_usuario, "Pedido cancelado después de envío - Requiere verificación física")) {
            throw new Exception('Error al restaurar stock del pedido');
        }
    }
    
    // Actualizar estado del pedido
    if (!actualizarEstadoPedido($mysqli, $id_pedido, 'cancelado')) {
        throw new Exception('Error al actualizar estado del pedido a cancelado. Puede que el pedido haya sido modificado por otro proceso.');
    }
    
    // Cancelar el pago si existe y no está cancelado
    // IMPORTANTE: Cancelación forzada por cancelación de pedido - se cancela SIEMPRE
    // incluso si el pago está en recorrido activo (aprobado, pendiente_aprobacion)
    // Nota: pago_queries.php ya está cargado al inicio de la función
    if ($pago_actual && $estado_pago_norm !== 'cancelado' && $estado_pago_norm !== 'rechazado') {
        // Cancelar el pago SIEMPRE cuando se cancela el pedido (forzar cancelación)
        // Esto asegura consistencia: pedido cancelado → pago cancelado
        error_log("_cancelarPedidoConValidaciones: Cancelando pago #{$pago_actual['id_pago']} forzadamente por cancelación de pedido (estado pago anterior: {$estado_pago_norm})");
        if (!actualizarEstadoPago($mysqli, $pago_actual['id_pago'], 'cancelado')) {
            throw new Exception('Error al cancelar el pago');
        }
    }
}

/**
 * Actualiza el estado de un pedido aplicando validaciones y reglas de negocio
 * 
 * Esta función centraliza la lógica de negocio para transiciones de estado de pedido
 * según las reglas definidas en el plan de lógica de negocio.
 * 
 * REGLAS IMPLEMENTADAS:
 * - Cambiar a 'en_viaje': Requiere pago aprobado y estado 'preparacion'
 * - Cambiar a 'completado': Requiere pago aprobado y estado 'preparacion' o 'en_viaje'
 * - Cambiar a 'devolucion': Requiere pago aprobado y estado 'en_viaje' (MVP: no desde completado)
 * - Cancelar pedido: Solo estados iniciales (pendiente, pendiente_validado_stock) pueden cancelarse
 * - VALIDACIÓN: No permite cancelar pedidos en recorrido activo (preparacion, en_viaje, completado, devolucion)
 * - Estado 'completado': Es terminal en MVP, no admite cambios (venta cerrada)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @param string $nuevo_estado_pedido Nuevo estado del pedido
 * @param int|null $id_usuario ID del usuario que realiza la acción (opcional)
 * @return bool True si se actualizó correctamente
 * @throws Exception Si hay error en la validación o actualización
 */
function actualizarEstadoPedidoConValidaciones($mysqli, $id_pedido, $nuevo_estado_pedido, $id_usuario = null) {
    // Cargar función de normalización
    require_once __DIR__ . '/../estado_helpers.php';
    
    // Normalizar estado antes de validar
    $nuevo_estado_pedido = normalizarEstado($nuevo_estado_pedido);
    
    // Validar estados válidos (incluye pendiente_validado_stock que está en BD)
    $estados_validos = ['pendiente', 'pendiente_validado_stock', 'preparacion', 'en_viaje', 'completado', 'devolucion', 'cancelado'];
    if (!in_array($nuevo_estado_pedido, $estados_validos)) {
        throw new Exception('Estado de pedido inválido: ' . $nuevo_estado_pedido);
    }
    
    // Obtener datos actuales del pedido
    $pedido_actual = obtenerPedidoPorId($mysqli, $id_pedido);
    if (!$pedido_actual) {
        throw new Exception('Pedido no encontrado con ID: ' . $id_pedido);
    }
    
    // Normalizar estado anterior
    $estado_pedido_anterior = normalizarEstado($pedido_actual['estado_pedido']);
    
    // Si no cambió el estado, no hacer nada
    if ($estado_pedido_anterior === $nuevo_estado_pedido) {
        return true;
    }
    
    // VALIDACIÓN TEMPRANA: Verificar que la transición esté permitida según la matriz de transiciones
    // Esta validación previene transiciones inválidas antes de ejecutar cualquier lógica de negocio
    try {
        validarTransicionPedido($estado_pedido_anterior, $nuevo_estado_pedido);
    } catch (Exception $e) {
        // Mejorar mensaje de error para que sea más claro
        throw new Exception('Transición de estado de pedido no permitida: ' . $e->getMessage());
    }
    
    // VALIDACIÓN ESTRICTA: Pedidos completados no admiten cambios
    // Previene bugs de UI o backoffice que conviertan ventas reales en canceladas
    // Evita flujos raros con movimientos de stock que no representan la realidad operativa
    if ($estado_pedido_anterior === 'completado') {
        throw new Exception('PEDIDO_COMPLETADO_NO_ADMITE_CAMBIOS: Un pedido completado no puede cambiar de estado. Es una venta cerrada.');
    }
    
    // Cargar funciones necesarias
    require_once __DIR__ . '/../queries_helper.php';
    cargarArchivoQueries('pago_queries', __DIR__);
    cargarArchivoQueries('stock_queries', __DIR__);
    
    // Obtener información del pago (necesario para validar cancelación desde preparación)
    $pago_actual = obtenerPagoPorPedido($mysqli, $id_pedido);
    $estado_pago = $pago_actual ? $pago_actual['estado_pago'] : null;
    $estado_pago_norm = $estado_pago ? normalizarEstado($estado_pago) : null;
    
    // Logging para debugging
    error_log("actualizarEstadoPedidoConValidaciones: Pedido #{$id_pedido} - Estado anterior: '{$estado_pedido_anterior}', Nuevo estado: '{$nuevo_estado_pedido}'");
    error_log("actualizarEstadoPedidoConValidaciones: Pago - Existe: " . ($pago_actual ? 'sí' : 'no') . ", Estado raw: '{$estado_pago}', Estado norm: '{$estado_pago_norm}'");
    if ($pago_actual) {
        error_log("actualizarEstadoPedidoConValidaciones: Pago completo - " . json_encode($pago_actual));
    }
    
    // VALIDACIÓN CRÍTICA: No permitir cancelar pedidos que están en recorrido activo
    // EXCEPCIÓN: Permitir cancelar desde 'preparacion' cuando el pago está cancelado/rechazado
    // Esto permite corregir inconsistencias donde el pago fue cancelado pero el pedido quedó en preparación
    if ($nuevo_estado_pedido === 'cancelado' && estaEnRecorridoActivoPedido($estado_pedido_anterior)) {
        // Excepción: Permitir cancelar desde preparación si el pago está cancelado o rechazado
        if ($estado_pedido_anterior === 'preparacion' && in_array($estado_pago_norm, ['cancelado', 'rechazado'])) {
            // Permitir cancelación: el pago ya está cancelado/rechazado, no hay stock descontado
            error_log("actualizarEstadoPedidoConValidaciones: Excepción - Preparación con pago cancelado/rechazado. Estado pago: {$estado_pago}");
        } else {
            // Bloquear cancelación: pedido en recorrido activo con pago aprobado o pendiente
            throw new Exception('Solo se puede cancelar un pedido que está en estado inicial (pendiente o pendiente_validado_stock). Un pedido en recorrido activo no puede cancelarse.');
        }
    }
    
    // Iniciar transacción para atomicidad y validación de estado cambiado
    $mysqli->begin_transaction();
    
    try {
        // Verificar que el estado no haya cambiado desde que lo leímos (race condition check)
        // Re-leer el estado dentro de la transacción para detectar cambios concurrentes
        $sql_verificar_estado = "SELECT estado_pedido FROM Pedidos WHERE id_pedido = ? FOR UPDATE";
        $stmt_verificar_estado = $mysqli->prepare($sql_verificar_estado);
        if (!$stmt_verificar_estado) {
            $mysqli->rollback();
            throw new Exception('Error al preparar verificación de estado: ' . $mysqli->error);
        }
        $stmt_verificar_estado->bind_param('i', $id_pedido);
        if (!$stmt_verificar_estado->execute()) {
            $error_msg = $stmt_verificar_estado->error;
            $stmt_verificar_estado->close();
            $mysqli->rollback();
            throw new Exception('Error al verificar estado del pedido: ' . $error_msg);
        }
        $result_verificar_estado = $stmt_verificar_estado->get_result();
        $pedido_verificado = $result_verificar_estado->fetch_assoc();
        $stmt_verificar_estado->close();
        
        if (!$pedido_verificado) {
            $mysqli->rollback();
            throw new Exception('Pedido no encontrado con ID: ' . $id_pedido);
        }
        
        $estado_actual_verificado = normalizarEstado($pedido_verificado['estado_pedido']);
        if ($estado_actual_verificado !== $estado_pedido_anterior) {
            $mysqli->rollback();
            throw new Exception('El estado del pedido ha cambiado durante la transacción. Estado anterior: ' . $estado_pedido_anterior . ', Estado actual: ' . $estado_actual_verificado);
        }
        
        // REGLA 1: Cambiar pedido a 'en_viaje'
        if ($nuevo_estado_pedido === 'en_viaje') {
            _cambiarPedidoAEnViaje($mysqli, $id_pedido, $estado_pedido_anterior, $estado_pago_norm);
        }
        // REGLA 2: Cambiar pedido a 'completado'
        elseif ($nuevo_estado_pedido === 'completado') {
            _cambiarPedidoACompletado($mysqli, $id_pedido, $estado_pedido_anterior, $estado_pago_norm);
        }
        // REGLA 3: Cambiar pedido a 'devolucion'
        elseif ($nuevo_estado_pedido === 'devolucion') {
            _cambiarPedidoADevolucion($mysqli, $id_pedido, $estado_pedido_anterior, $estado_pago_norm, $id_usuario);
        }
        // REGLA 4: Cancelar pedido manualmente
        elseif ($nuevo_estado_pedido === 'cancelado') {
            _cancelarPedidoConValidaciones($mysqli, $id_pedido, $estado_pedido_anterior, $pago_actual, $estado_pago, $id_usuario);
        }
        // REGLA 5: Otros cambios de estado (pendiente, preparacion)
        // Validaciones preventivas: bloquear retrocesos ilógicos y validar estado de pago
        else {
            // VALIDACIÓN 1: Bloquear retrocesos ilógicos
            _validarRetrocesosIlógicos($estado_pedido_anterior, $nuevo_estado_pedido);
            
            // VALIDACIÓN 2: Validar estado de pago para estados avanzados
            if (in_array($nuevo_estado_pedido, ['en_viaje', 'completado', 'devolucion'])) {
                _validarEstadoPagoParaEstadosAvanzados($pago_actual, $estado_pago, $nuevo_estado_pedido);
            }
            
            // VALIDACIÓN 3: Validar preparación con pago
            if ($nuevo_estado_pedido === 'preparacion') {
                _validarPreparacionConPago($pago_actual, $estado_pago);
            }
            
            // Actualizar estado del pedido
            if (!actualizarEstadoPedido($mysqli, $id_pedido, $nuevo_estado_pedido)) {
                $mysqli->rollback();
                throw new Exception('Error al actualizar estado del pedido');
            }
        }
        
        $mysqli->commit();
        return true;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Error en actualizarEstadoPedidoConValidaciones: " . $e->getMessage());
        throw $e;
    }
}

