<?php
/**
 * ========================================================================
 * CONSULTAS SQL DE GESTIÓN DE STOCK - Tienda Seda y Lino
 * ========================================================================
 * Archivo centralizado con todas las consultas relacionadas a stock
 * 
 * REEMPLAZO DE TRIGGERS:
 * Este archivo implementa la lógica PHP que reemplaza los siguientes triggers de MySQL:
 * - trg_actualizar_stock_insert: actualizarStockDesdeMovimiento() y registrarMovimientoStock()
 * - trg_validar_ajuste_stock: validarAjusteStock()
 * - trg_validar_stock_antes_update: validarStockNoNegativo() y actualizarStockVariante()
 * - trg_validar_stock_disponible_antes_venta: validarStockDisponibleVenta()
 * 
 * Uso:
 *   require_once __DIR__ . '/includes/queries/stock_queries.php';
 *   revertirStockPedido($mysqli, $id_pedido);
 * ========================================================================
 */

/**
 * Valida que un ajuste negativo no cause stock negativo
 * Reemplaza la lógica del trigger trg_validar_ajuste_stock
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @param int $cantidad Cantidad del ajuste (puede ser negativa)
 * @throws Exception Si el ajuste causaría stock negativo o la variante/producto están inactivos
 */
function validarAjusteStock($mysqli, $id_variante, $cantidad) {
    // MEJORA DE RENDIMIENTO: FOR UPDATE bloquea la fila hasta el commit de la transacción.
    // Solo usar cuando realmente sea necesario para prevenir race conditions en transacciones
    // concurrentes. En este caso es apropiado porque valida y actualiza en la misma transacción.
    // MEJORA: Verificar que la conexión esté en modo transaccional antes de usar FOR UPDATE.
    // Obtener stock actual, variante y producto activos con FOR UPDATE para prevenir race conditions
    $sql = "
        SELECT sv.stock, sv.activo as variante_activa, p.activo as producto_activo 
        FROM Stock_Variantes sv
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        WHERE sv.id_variante = ?
        FOR UPDATE
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error al validar ajuste de stock');
    }
    
    $stmt->bind_param('i', $id_variante);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos = $result->fetch_assoc();
    $stmt->close();
    
    if (!$datos) {
        throw new Exception('La variante de stock no existe');
    }
    
    $stock_actual = intval($datos['stock']);
    $variante_activa = intval($datos['variante_activa']);
    $producto_activo = intval($datos['producto_activo']);
    
    if ($variante_activa === 0) {
        throw new Exception('No se puede ajustar una variante inactiva');
    }
    
    if ($producto_activo === 0) {
        throw new Exception('No se puede ajustar stock de un producto inactivo');
    }
    
    if (($stock_actual + $cantidad) < 0) {
        throw new Exception("El ajuste causaría stock negativo. Stock actual: {$stock_actual}, Ajuste: {$cantidad}, Resultado: " . ($stock_actual + $cantidad));
    }
}

/**
 * Valida que hay stock suficiente antes de crear un movimiento de venta
 * Reemplaza la lógica del trigger trg_validar_stock_disponible_antes_venta
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @param int $cantidad Cantidad a vender (siempre positiva)
 * @throws Exception Si no hay stock suficiente o la variante/producto están inactivos
 */
function validarStockDisponibleVenta($mysqli, $id_variante, $cantidad) {
    // Obtener stock actual, variante y producto activos con FOR UPDATE para prevenir race conditions
    $sql = "
        SELECT sv.stock, sv.activo as variante_activa, p.activo as producto_activo 
        FROM Stock_Variantes sv
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        WHERE sv.id_variante = ?
        FOR UPDATE
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error al validar stock disponible');
    }
    
    $stmt->bind_param('i', $id_variante);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos = $result->fetch_assoc();
    $stmt->close();
    
    if (!$datos) {
        throw new Exception('La variante de stock no existe');
    }
    
    $stock_actual = intval($datos['stock']);
    $variante_activa = intval($datos['variante_activa']);
    $producto_activo = intval($datos['producto_activo']);
    
    if ($variante_activa === 0) {
        throw new Exception('No se puede vender una variante inactiva');
    }
    
    if ($producto_activo === 0) {
        throw new Exception('No se puede vender un producto inactivo');
    }
    
    if ($stock_actual < $cantidad) {
        throw new Exception("Stock insuficiente. Stock disponible: {$stock_actual}, Intento de venta: {$cantidad}");
    }
}

/**
 * Valida stock disponible para operaciones de carrito con FOR UPDATE
 * Previene race conditions cuando múltiples usuarios agregan productos simultáneamente
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @param string $talle Talle de la variante
 * @param string $color Color de la variante
 * @param int $cantidad_solicitada Cantidad que se desea agregar al carrito
 * @param int $cantidad_actual_carrito Cantidad que ya está en el carrito (opcional)
 * @return array Array con 'stock_disponible', 'variante_activa', 'producto_activo', 'id_variante', 'precio_actual', 'nombre_producto'
 * @throws Exception Si la variante no existe, está inactiva o no hay stock suficiente
 */
function validarStockDisponibleCarrito($mysqli, $id_producto, $talle, $color, $cantidad_solicitada, $cantidad_actual_carrito = 0) {
    // Validar parámetros
    $id_producto = intval($id_producto);
    $talle = trim(strval($talle));
    $color = trim(strval($color));
    $cantidad_solicitada = intval($cantidad_solicitada);
    $cantidad_actual_carrito = intval($cantidad_actual_carrito);
    
    if ($id_producto <= 0 || empty($talle) || empty($color) || $cantidad_solicitada <= 0) {
        throw new Exception('Parámetros inválidos para validar stock');
    }
    
    // Iniciar transacción para usar FOR UPDATE
    $mysqli->begin_transaction();
    
    try {
        // Obtener stock actual con FOR UPDATE para bloquear la fila y prevenir race conditions
        $sql = "
            SELECT 
                sv.id_variante,
                sv.stock, 
                sv.activo as variante_activa, 
                p.activo as producto_activo,
                p.precio_actual,
                p.nombre_producto
            FROM Stock_Variantes sv
            INNER JOIN Productos p ON sv.id_producto = p.id_producto
            WHERE sv.id_producto = ?
              AND sv.talle = ?
              AND sv.color = ?
            FOR UPDATE
        ";
        
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error al preparar validación de stock: ' . $mysqli->error);
        }
        
        $stmt->bind_param('iss', $id_producto, $talle, $color);
        if (!$stmt->execute()) {
            $error_msg = $stmt->error;
            $stmt->close();
            throw new Exception('Error al ejecutar validación de stock: ' . $error_msg);
        }
        
        $result = $stmt->get_result();
        $datos = $result->fetch_assoc();
        $stmt->close();
        
        // Hacer commit de la transacción (solo bloqueamos para leer, no modificamos)
        $mysqli->commit();
        
        if (!$datos || empty($datos['id_variante'])) {
            throw new Exception('El producto seleccionado no está disponible con esa combinación de talle y color');
        }
        
        $stock_disponible = intval($datos['stock']);
        $variante_activa = intval($datos['variante_activa']);
        $producto_activo = intval($datos['producto_activo']);
        
        // Validar que variante y producto estén activos
        if ($variante_activa === 0) {
            throw new Exception('La variante seleccionada está inactiva');
        }
        
        if ($producto_activo === 0) {
            throw new Exception('El producto seleccionado está inactivo');
        }
        
        // Calcular cantidad total que se tendría en el carrito
        $cantidad_total_solicitada = $cantidad_actual_carrito + $cantidad_solicitada;
        
        // Validar stock disponible
        if ($stock_disponible < $cantidad_total_solicitada) {
            $cantidad_disponible_para_agregar = $stock_disponible - $cantidad_actual_carrito;
            if ($cantidad_disponible_para_agregar <= 0) {
                throw new Exception("Stock insuficiente. Disponible: {$stock_disponible} unidades. Ya tienes {$cantidad_actual_carrito} en el carrito.");
            } else {
                throw new Exception("Stock insuficiente. Disponible: {$stock_disponible} unidades. Puedes agregar hasta {$cantidad_disponible_para_agregar} unidades más.");
            }
        }
        
        // Retornar datos del stock
        return [
            'stock_disponible' => $stock_disponible,
            'variante_activa' => $variante_activa,
            'producto_activo' => $producto_activo,
            'id_variante' => intval($datos['id_variante']),
            'precio_actual' => floatval($datos['precio_actual']),
            'nombre_producto' => $datos['nombre_producto'],
            'talle' => $talle,
            'color' => $color
        ];
        
    } catch (Exception $e) {
        // Rollback en caso de error
        $mysqli->rollback();
        throw $e;
    }
}

/**
 * Actualiza el stock de una variante según el tipo de movimiento
 * Reemplaza la lógica del trigger trg_actualizar_stock_insert
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @param string $tipo_movimiento Tipo de movimiento
 * @param int $cantidad Cantidad del movimiento
 * @throws Exception Si hay error al actualizar
 */
function actualizarStockDesdeMovimiento($mysqli, $id_variante, $tipo_movimiento, $cantidad) {
    // Determinar el cambio de stock según el tipo de movimiento
    if ($tipo_movimiento === 'venta') {
        // Restar stock (venta)
        $sql = "
            UPDATE Stock_Variantes 
            SET stock = stock - ?,
                fecha_actualizacion = NOW()
            WHERE id_variante = ?
        ";
    } elseif ($tipo_movimiento === 'ajuste') {
        // Los ajustes pueden tener cantidad positiva (suma stock) o negativa (resta stock)
        // La cantidad se suma directamente: positivo aumenta stock, negativo lo disminuye
        $sql = "
            UPDATE Stock_Variantes 
            SET stock = stock + ?,
                fecha_actualizacion = NOW()
            WHERE id_variante = ?
        ";
    } elseif ($tipo_movimiento === 'ingreso') {
        // Sumar stock (ingreso)
        $sql = "
            UPDATE Stock_Variantes 
            SET stock = stock + ?,
                fecha_actualizacion = NOW()
            WHERE id_variante = ?
        ";
    } else {
        throw new Exception("Tipo de movimiento inválido: {$tipo_movimiento}");
    }
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error al actualizar stock');
    }
    
    $stmt->bind_param('ii', $cantidad, $id_variante);
    $resultado = $stmt->execute();
    $stmt->close();
    
    if (!$resultado) {
        throw new Exception('Error al actualizar stock de la variante');
    }
}

/**
 * Valida que el stock no sea negativo al actualizar Stock_Variantes directamente
 * Reemplaza la lógica del trigger trg_validar_stock_antes_update
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @param int $nuevo_stock Nuevo valor de stock a validar
 * @throws Exception Si el stock sería negativo
 */
function validarStockNoNegativo($mysqli, $id_variante, $nuevo_stock) {
    if ($nuevo_stock < 0) {
        // Obtener stock actual para el mensaje de error
        $sql = "SELECT stock FROM Stock_Variantes WHERE id_variante = ?";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $id_variante);
            $stmt->execute();
            $result = $stmt->get_result();
            $datos = $result->fetch_assoc();
            $stmt->close();
            
            $stock_actual = $datos ? intval($datos['stock']) : 0;
            throw new Exception("No se puede tener stock negativo. Stock actual: {$stock_actual}, Intento: {$nuevo_stock}");
        } else {
            throw new Exception("No se puede tener stock negativo. Intento: {$nuevo_stock}");
        }
    }
}

/**
 * Actualiza el stock de una variante con validación de stock no negativo
 * Reemplaza el trigger trg_validar_stock_antes_update
 * 
 * NOTA: Esta función solo debe usarse para actualizaciones directas de stock.
 * En la mayoría de los casos, el stock debe actualizarse mediante movimientos usando registrarMovimientoStock()
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @param int $nuevo_stock Nuevo valor de stock
 * @return bool True si se actualizó correctamente
 * @throws Exception Si el stock sería negativo o la variante no existe
 */
function actualizarStockVariante($mysqli, $id_variante, $nuevo_stock) {
    // Validar que el stock no sea negativo (reemplaza trg_validar_stock_antes_update)
    validarStockNoNegativo($mysqli, $id_variante, $nuevo_stock);
    
    // Actualizar stock
    $sql = "UPDATE Stock_Variantes SET stock = ?, fecha_actualizacion = NOW() WHERE id_variante = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error al preparar actualización de stock');
    }
    
    $stmt->bind_param('ii', $nuevo_stock, $id_variante);
    $resultado = $stmt->execute();
    $stmt->close();
    
    if (!$resultado) {
        throw new Exception('Error al actualizar stock de la variante');
    }
    
    return true;
}

/**
 * Registra un movimiento de stock y actualiza el stock automáticamente
 * IMPORTANTE: La cantidad siempre debe ser positiva, el tipo_movimiento indica dirección
 * Reemplaza la funcionalidad de los triggers: ahora actualiza stock manualmente en PHP
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @param string $tipo_movimiento 'venta', 'ingreso', 'ajuste'
 * @param int $cantidad Cantidad (SIEMPRE POSITIVA excepto para ajustes que pueden ser negativos)
 * @param int|null $id_usuario ID del usuario que realiza el movimiento
 * @param int|null $id_pedido ID del pedido relacionado (opcional)
 * @param string|null $observaciones Observaciones del movimiento
 * @param bool $en_transaccion Si true, no inicia/commit transacción (ya está dentro de una)
 * @return bool True si se registró correctamente
 */
function registrarMovimientoStock($mysqli, $id_variante, $tipo_movimiento, $cantidad, $id_usuario = null, $id_pedido = null, $observaciones = null, $en_transaccion = false) {
    // Para ajustes, permitir cantidad negativa (para restar stock)
    // Para otros tipos (venta, ingreso), siempre positiva
    if ($tipo_movimiento !== 'ajuste') {
        $cantidad = abs($cantidad);
    }
    
    // Validar tipo de movimiento
    $tipos_validos = ['venta', 'ingreso', 'ajuste'];
    if (!in_array($tipo_movimiento, $tipos_validos)) {
        return false;
    }
    
    // Solo iniciar transacción si no estamos dentro de una existente
    if (!$en_transaccion) {
        $mysqli->begin_transaction();
    }
    
    try {
        // Validar ajustes negativos antes de insertar (reemplaza trg_validar_ajuste_stock)
        if ($tipo_movimiento === 'ajuste' && $cantidad < 0) {
            validarAjusteStock($mysqli, $id_variante, $cantidad);
        }
        
        // Validar stock disponible antes de venta (reemplaza trg_validar_stock_disponible_antes_venta)
        if ($tipo_movimiento === 'venta') {
            validarStockDisponibleVenta($mysqli, $id_variante, $cantidad);
        }
        
        // Insertar movimiento
        $sql = "
            INSERT INTO Movimientos_Stock (id_variante, tipo_movimiento, cantidad, fecha_movimiento, id_usuario, id_pedido, observaciones)
            VALUES (?, ?, ?, NOW(), ?, ?, ?)
        ";
        
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error al preparar inserción de movimiento');
        }
        
        $stmt->bind_param('isiiss', $id_variante, $tipo_movimiento, $cantidad, $id_usuario, $id_pedido, $observaciones);
        $resultado = $stmt->execute();
        $stmt->close();
        
        if (!$resultado) {
            throw new Exception('Error al insertar movimiento de stock');
        }
        
        // Actualizar stock manualmente (reemplaza trg_actualizar_stock_insert)
        actualizarStockDesdeMovimiento($mysqli, $id_variante, $tipo_movimiento, $cantidad);
        
        // Solo commit si iniciamos la transacción
        if (!$en_transaccion) {
            $mysqli->commit();
        }
        return true;
        
    } catch (Exception $e) {
        // Solo rollback si iniciamos la transacción
        if (!$en_transaccion) {
            $mysqli->rollback();
        }
        error_log("Error en registrarMovimientoStock: " . $e->getMessage());
        return false;
    }
}

/**
 * Descuenta stock cuando se aprueba un pago
 * Crea movimientos de tipo 'venta' para cada producto del pedido
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @param int $id_usuario ID del usuario que aprueba (opcional)
 * @param bool $en_transaccion Si true, no inicia/commit transacción (ya está dentro de una)
 * @return bool True si se descontó correctamente
 */
function descontarStockPedido($mysqli, $id_pedido, $id_usuario = null, $en_transaccion = false) {
    // MEJORA DE RENDIMIENTO: Esta query usa el índice idx_detalle_pedido_pedido que ya existe.
    // MEJORA DE SEGURIDAD: Considerar agregar validación de que el pedido existe y está en estado válido
    // antes de descontar stock, para evitar descuentos en pedidos cancelados o ya procesados.
    
    // Obtener detalles del pedido con id_variante directamente
    $sql = "
        SELECT 
            dp.id_detalle,
            dp.id_variante,
            dp.cantidad,
            dp.precio_unitario
        FROM Detalle_Pedido dp
        WHERE dp.id_pedido = ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('i', $id_pedido);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $detalles = [];
    while ($row = $result->fetch_assoc()) {
        $detalles[] = $row;
    }
    $stmt->close();
    
    if (empty($detalles)) {
        return false;
    }
    
    // Solo iniciar transacción si no estamos dentro de una existente
    if (!$en_transaccion) {
        $mysqli->begin_transaction();
    }
    
    try {
        foreach ($detalles as $detalle) {
            $id_variante = intval($detalle['id_variante']);
            $cantidad = intval($detalle['cantidad']);
            
            // Verificar stock disponible antes de descontar (ya validado en actualizarPagoCompleto, pero por seguridad)
            $sql_stock = "SELECT stock FROM Stock_Variantes WHERE id_variante = ? FOR UPDATE";
            $stmt_stock = $mysqli->prepare($sql_stock);
            $stmt_stock->bind_param('i', $id_variante);
            $stmt_stock->execute();
            $result_stock = $stmt_stock->get_result();
            $stock_actual = $result_stock->fetch_assoc();
            $stmt_stock->close();
            
            if (!$stock_actual || $stock_actual['stock'] < $cantidad) {
                throw new Exception("Stock insuficiente para variante #{$id_variante}. Disponible: " . ($stock_actual['stock'] ?? 0) . ", Solicitado: {$cantidad}");
            }
            
            // Registrar movimiento de venta (cantidad siempre positiva)
            $observaciones = "Venta confirmada - Pedido #{$id_pedido}";
            if (!registrarMovimientoStock($mysqli, $id_variante, 'venta', $cantidad, $id_usuario, $id_pedido, $observaciones, true)) {
                throw new Exception("Error al registrar movimiento de stock para variante #{$id_variante}");
            }
        }
        
        // Solo commit si iniciamos la transacción
        if (!$en_transaccion) {
            $mysqli->commit();
        }
        return true;
        
    } catch (Exception $e) {
        // Solo rollback si iniciamos la transacción
        if (!$en_transaccion) {
            $mysqli->rollback();
        }
        error_log("Error al descontar stock del pedido #{$id_pedido}: " . $e->getMessage());
        throw $e; // Re-lanzar excepción para que la transacción externa la maneje
    }
}

/**
 * Revierte el stock de un pedido cancelado o con pago rechazado
 * Crea movimientos de tipo 'ingreso' para restaurar el stock
 * 
 * MEJORAS IMPLEMENTADAS:
 * - Valida estado del pedido/pago antes de revertir (solo revierte si pedido cancelado o pago rechazado/cancelado)
 * - Verifica idempotencia para evitar reversiones duplicadas
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido a revertir
 * @param int $id_usuario ID del usuario que realiza la reversión (opcional)
 * @param string $motivo Motivo de la reversión (opcional)
 * @return bool True si se revirtió correctamente o ya estaba revertido
 */
function revertirStockPedido($mysqli, $id_pedido, $id_usuario = null, $motivo = null) {
    // MEJORA: Validar estado del pedido y pago antes de revertir stock
    // Solo se debe revertir si el pedido está cancelado o el pago está rechazado/cancelado
    $sql_estado = "
        SELECT 
            p.estado_pedido, 
            pg.estado_pago
        FROM Pedidos p
        LEFT JOIN Pagos pg ON p.id_pedido = pg.id_pedido
        WHERE p.id_pedido = ?
    ";
    
    $stmt_estado = $mysqli->prepare($sql_estado);
    if (!$stmt_estado) {
        error_log("Error al preparar validación de estado en revertirStockPedido: " . $mysqli->error);
        return false;
    }
    
    $stmt_estado->bind_param('i', $id_pedido);
    if (!$stmt_estado->execute()) {
        error_log("Error al ejecutar validación de estado en revertirStockPedido: " . $stmt_estado->error);
        $stmt_estado->close();
        return false;
    }
    
    $result_estado = $stmt_estado->get_result();
    $estado_data = $result_estado->fetch_assoc();
    $stmt_estado->close();
    
    if (!$estado_data) {
        error_log("Pedido #{$id_pedido} no encontrado en revertirStockPedido");
        return false;
    }
    
    $estado_pedido = strtolower(trim($estado_data['estado_pedido'] ?? ''));
    $estado_pago = strtolower(trim($estado_data['estado_pago'] ?? ''));
    
    // Validar que el pedido esté cancelado o el pago esté rechazado/cancelado/aprobado
    // Solo revertir en estos casos para evitar reversiones en estados inválidos
    // Nota: Se permite revertir cuando el pago está 'aprobado' porque el stock fue descontado
    // al aprobar el pago, así que debe restaurarse al cancelar el pedido
    $puede_revertir = false;
    if ($estado_pedido === 'cancelado') {
        $puede_revertir = true;
    } elseif (in_array($estado_pago, ['rechazado', 'cancelado', 'aprobado'])) {
        $puede_revertir = true;
    }
    
    if (!$puede_revertir) {
        error_log("No se puede revertir stock del pedido #{$id_pedido}: Estado pedido='{$estado_pedido}', Estado pago='{$estado_pago}'");
        return false; // No revertir si el estado no lo permite
    }
    
    // MEJORA: Verificar idempotencia - si ya se revirtió el stock, no revertir nuevamente
    // Verificar si ya existen movimientos de 'ingreso' (reversión) para este pedido
    $sql_verificar_reversion = "
        SELECT COUNT(*) as reversiones_previas 
        FROM Movimientos_Stock 
        WHERE id_pedido = ? 
          AND tipo_movimiento = 'ingreso'
          AND observaciones LIKE 'Reversión%'
    ";
    
    $stmt_reversion = $mysqli->prepare($sql_verificar_reversion);
    if ($stmt_reversion) {
        $stmt_reversion->bind_param('i', $id_pedido);
        $stmt_reversion->execute();
        $result_reversion = $stmt_reversion->get_result();
        $reversion_data = $result_reversion->fetch_assoc();
        $stmt_reversion->close();
        
        // Verificar si ya hay reversiones y si coinciden con las ventas
        if ($reversion_data && intval($reversion_data['reversiones_previas']) > 0) {
            // Verificar si hay movimientos de venta para comparar
            $sql_verificar_ventas = "
                SELECT COUNT(*) as total_ventas 
                FROM Movimientos_Stock 
                WHERE id_pedido = ? AND tipo_movimiento = 'venta'
            ";
            $stmt_ventas = $mysqli->prepare($sql_verificar_ventas);
            if ($stmt_ventas) {
                $stmt_ventas->bind_param('i', $id_pedido);
                $stmt_ventas->execute();
                $result_ventas = $stmt_ventas->get_result();
                $ventas_data = $result_ventas->fetch_assoc();
                $stmt_ventas->close();
                
                // Si ya hay reversiones y hay ventas, verificar que coincidan
                if ($ventas_data && intval($ventas_data['total_ventas']) > 0) {
                    // Si ya se revirtió, no revertir nuevamente (idempotencia)
                    error_log("Stock del pedido #{$id_pedido} ya fue revertido anteriormente. Evitando reversión duplicada.");
                    return true; // Ya se revirtió, retornar éxito sin hacer nada
                }
            }
        }
    }
    
    // Obtener detalles del pedido con id_variante directamente
    $sql = "
        SELECT 
            dp.id_detalle,
            dp.id_variante,
            dp.cantidad,
            dp.precio_unitario
        FROM Detalle_Pedido dp
        WHERE dp.id_pedido = ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('i', $id_pedido);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $detalles = [];
    while ($row = $result->fetch_assoc()) {
        $detalles[] = $row;
    }
    $stmt->close();
    
    if (empty($detalles)) {
        return false;
    }
    
    // Verificar si ya hay movimientos de venta para este pedido
    $sql_verificar = "
        SELECT COUNT(*) as total_movimientos 
        FROM Movimientos_Stock 
        WHERE id_pedido = ? AND tipo_movimiento = 'venta'
    ";
    $stmt_verificar = $mysqli->prepare($sql_verificar);
    $stmt_verificar->bind_param('i', $id_pedido);
    $stmt_verificar->execute();
    $result_verificar = $stmt_verificar->get_result();
    $verificacion = $result_verificar->fetch_assoc();
    $stmt_verificar->close();
    
    // Si no hay movimientos de venta, no hay nada que revertir
    if ($verificacion['total_movimientos'] == 0) {
        return true; // No hay stock que revertir (ya estaba en estado pendiente)
    }
    
    $mysqli->begin_transaction();
    
    try {
        foreach ($detalles as $detalle) {
            $id_variante = intval($detalle['id_variante']);
            $cantidad = intval($detalle['cantidad']);
            
            // Registrar movimiento de ingreso para restaurar stock (cantidad siempre positiva)
            $observaciones = "Reversión - Pedido #{$id_pedido} cancelado";
            if ($motivo) {
                $observaciones .= " - Motivo: {$motivo}";
            }
            
            if (!registrarMovimientoStock($mysqli, $id_variante, 'ingreso', $cantidad, $id_usuario, $id_pedido, $observaciones, true)) {
                throw new Exception("Error al registrar reversión de stock para variante #{$id_variante}");
            }
        }
        
        $mysqli->commit();
        return true;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Error al revertir stock del pedido #{$id_pedido}: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene el stock disponible de una variante
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @return int Stock disponible
 */
function obtenerStockDisponible($mysqli, $id_variante) {
    $sql = "SELECT stock FROM Stock_Variantes WHERE id_variante = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param('i', $id_variante);
    $stmt->execute();
    $result = $stmt->get_result();
    $stock = $result->fetch_assoc();
    $stmt->close();
    
    return $stock ? intval($stock['stock']) : 0;
}

/**
 * Verifica si hay stock suficiente para una cantidad específica
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @param int $cantidad_necesaria Cantidad necesaria
 * @return bool True si hay stock suficiente
 */
function verificarStockDisponible($mysqli, $id_variante, $cantidad_necesaria) {
    $stock_disponible = obtenerStockDisponible($mysqli, $id_variante);
    return $stock_disponible >= $cantidad_necesaria;
}

/**
 * Obtiene los movimientos de stock de un pedido
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @return array Array de movimientos de stock
 */
function obtenerMovimientosStockPorPedido($mysqli, $id_pedido) {
    $sql = "
        SELECT 
            ms.id_movimiento,
            ms.id_variante,
            ms.tipo_movimiento,
            ms.cantidad,
            ms.fecha_movimiento,
            ms.observaciones,
            sv.talle,
            sv.color,
            p.nombre_producto
        FROM Movimientos_Stock ms
        INNER JOIN Stock_Variantes sv ON ms.id_variante = sv.id_variante
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        WHERE ms.id_pedido = ?
        ORDER BY ms.fecha_movimiento DESC
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('i', $id_pedido);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $movimientos = [];
    while ($row = $result->fetch_assoc()) {
        $movimientos[] = $row;
    }
    
    $stmt->close();
    return $movimientos;
}

/**
 * Obtiene los movimientos de stock más recientes para métricas
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $limite Número máximo de movimientos a retornar (default 50)
 * @return array Array de movimientos de stock con información completa
 */
function obtenerMovimientosStockRecientes($mysqli, $limite = 50) {
    $sql = "
        SELECT 
            ms.id_movimiento,
            ms.fecha_movimiento,
            ms.tipo_movimiento,
            ms.cantidad,
            ms.id_pedido,
            ms.observaciones,
            p.nombre_producto,
            c.nombre_categoria,
            sv.talle,
            sv.color,
            CONCAT(u.nombre, ' ', u.apellido) as nombre_usuario
        FROM Movimientos_Stock ms
        INNER JOIN Stock_Variantes sv ON ms.id_variante = sv.id_variante
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
        LEFT JOIN Usuarios u ON ms.id_usuario = u.id_usuario
        ORDER BY ms.fecha_movimiento DESC
        LIMIT ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('i', $limite);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $movimientos = [];
    while ($row = $result->fetch_assoc()) {
        $movimientos[] = $row;
    }
    
    $stmt->close();
    return $movimientos;
}

/**
 * Inserta una nueva variante de stock
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @param string $talle Talle de la variante
 * @param string $color Color de la variante
 * @param int $stock_inicial Stock inicial (por defecto 0, se actualiza mediante movimiento)
 * @return int|false ID de la variante insertada o false en caso de error
 */
function insertarVarianteStock($mysqli, $id_producto, $talle, $color, $stock_inicial = 0) {
    $sql = "INSERT INTO Stock_Variantes (id_producto, talle, color, stock, activo) VALUES (?, ?, ?, ?, 1)";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR insertarVarianteStock - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('issi', $id_producto, $talle, $color, $stock_inicial);
    if (!$stmt->execute()) {
        error_log("ERROR insertarVarianteStock - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $id_variante = $mysqli->insert_id;
    $stmt->close();
    
    return $id_variante;
}

/**
 * Obtiene los datos de una variante de stock por su ID
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @return array|null Array con datos de la variante o null si no existe
 */
function obtenerVariantePorId($mysqli, $id_variante) {
    $sql = "SELECT id_producto, talle, color, stock FROM Stock_Variantes WHERE id_variante = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $id_variante);
    $stmt->execute();
    $result = $stmt->get_result();
    $variante = $result->fetch_assoc();
    $stmt->close();
    
    return $variante;
}

/**
 * Verifica si ya existe una variante con la misma combinación de talle y color
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $nombre_producto Nombre del producto
 * @param int $id_categoria ID de la categoría
 * @param string $genero Género del producto
 * @param string $talle Talle a verificar
 * @param string $color Color a verificar
 * @param int|null $excluir_id_variante ID de variante a excluir de la verificación
 * @return bool True si existe, false si no existe
 */
function verificarVarianteExistente($mysqli, $nombre_producto, $id_categoria, $genero, $talle, $color, $excluir_id_variante = null) {
    $sql = "
        SELECT sv.id_variante 
        FROM Stock_Variantes sv
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        WHERE p.nombre_producto = ?
        AND p.id_categoria = ?
        AND p.genero = ?
        AND sv.talle = ?
        AND sv.color = ?
        AND sv.activo = 1
        AND p.activo = 1
    ";
    
    if ($excluir_id_variante !== null) {
        $sql .= " AND sv.id_variante != ?";
    }
    
    $sql .= " LIMIT 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR verificarVarianteExistente - prepare falló: " . $mysqli->error);
        return false;
    }
    
    if ($excluir_id_variante !== null) {
        // bind_param: 'sissii' = 6 parámetros: nombre_producto(s), id_categoria(i), genero(s), talle(s), color(s), excluir_id_variante(i)
        $stmt->bind_param('sissii', $nombre_producto, $id_categoria, $genero, $talle, $color, $excluir_id_variante);
    } else {
        // bind_param: 'sissi' = 5 parámetros: nombre_producto(s), id_categoria(i), genero(s), talle(s), color(s)
        $stmt->bind_param('sissi', $nombre_producto, $id_categoria, $genero, $talle, $color);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $existe = $result->num_rows > 0;
    $stmt->close();
    
    return $existe;
}

/**
 * Actualiza el talle y color de una variante de stock
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @param string $talle Nuevo talle
 * @param string $color Nuevo color
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function actualizarVarianteStock($mysqli, $id_variante, $talle, $color) {
    $sql = "UPDATE Stock_Variantes SET talle = ?, color = ?, fecha_actualizacion = NOW() WHERE id_variante = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR actualizarVarianteStock - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('ssi', $talle, $color, $id_variante);
    if (!$stmt->execute()) {
        error_log("ERROR actualizarVarianteStock - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $stmt->close();
    return true;
}

/**
 * Desactiva una variante de stock (soft delete)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @return bool True si se desactivó correctamente, false en caso contrario
 */
function desactivarVarianteStock($mysqli, $id_variante) {
    $sql = "UPDATE Stock_Variantes SET activo = 0, fecha_actualizacion = NOW() WHERE id_variante = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR desactivarVarianteStock - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('i', $id_variante);
    if (!$stmt->execute()) {
        error_log("ERROR desactivarVarianteStock - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $stmt->close();
    return true;
}

/**
 * Obtiene todas las variantes activas de productos con el mismo nombre, categoría y género
 * Retorna un array con las variantes, priorizando las del producto actual
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $nombre_producto Nombre del producto
 * @param int $id_categoria ID de la categoría
 * @param string $genero Género del producto
 * @param int|null $id_producto_actual ID del producto actual (para priorizar sus variantes)
 * @return array Array de variantes con id_variante, id_producto, talle, color, stock
 */
function obtenerTodasVariantesProductoPorGrupo($mysqli, $nombre_producto, $id_categoria, $genero, $id_producto_actual = null) {
    $sql = "
        SELECT sv.id_variante, sv.id_producto, sv.talle, sv.color, sv.stock
        FROM Stock_Variantes sv
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        WHERE p.nombre_producto = ?
        AND p.id_categoria = ?
        AND p.genero = ?
        AND sv.activo = 1
        AND p.activo = 1
        ORDER BY sv.color, sv.talle
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('sis', $nombre_producto, $id_categoria, $genero);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $todas_variantes_completas = [];
    $variantes_unicas = []; // Clave: talle+color normalizado para evitar duplicados
    
    while ($row_variante = $result->fetch_assoc()) {
        // Crear clave única normalizada (talle+color en minúsculas)
        $clave_variante = strtolower(trim($row_variante['talle'] . '_' . $row_variante['color']));
        
        // Priorizar variantes del producto actual
        if (!isset($variantes_unicas[$clave_variante])) {
            $variantes_unicas[$clave_variante] = $row_variante;
            $todas_variantes_completas[] = $row_variante;
        } elseif ($id_producto_actual !== null && $row_variante['id_producto'] == $id_producto_actual && $variantes_unicas[$clave_variante]['id_producto'] != $id_producto_actual) {
            // Esta variante es del producto actual y la anterior no lo era, reemplazar
            $indice_anterior = array_search($variantes_unicas[$clave_variante], $todas_variantes_completas, true);
            if ($indice_anterior !== false) {
                $todas_variantes_completas[$indice_anterior] = $row_variante;
            }
            $variantes_unicas[$clave_variante] = $row_variante;
        }
    }
    
    $stmt->close();
    
    return $todas_variantes_completas;
}

