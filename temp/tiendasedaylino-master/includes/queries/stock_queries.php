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
 * PATRÓN DE TRANSACCIONES:
 * Muchas funciones aceptan el parámetro $en_transaccion para permitir operaciones
 * dentro de transacciones existentes. Cuando $en_transaccion = true:
 * - La función NO inicia su propia transacción (asume que ya está dentro de una)
 * - La función NO hace commit ni rollback (deja el control a la transacción externa)
 * - Útil para agrupar múltiples operaciones de stock en una sola transacción atómica
 * 
 * Ejemplo de uso:
 *   require_once __DIR__ . '/includes/queries/stock_queries.php';
 *   $mysqli->begin_transaction();
 *   try {
 *       registrarMovimientoStock($mysqli, $id_variante, 'venta', 5, $id_usuario, $id_pedido, null, true);
 *       registrarMovimientoStock($mysqli, $id_variante2, 'venta', 3, $id_usuario, $id_pedido, null, true);
 *       $mysqli->commit();
 *   } catch (Exception $e) {
 *       $mysqli->rollback();
 *   }
 * 
 * ARCHIVOS QUE INCLUYEN ESTE ARCHIVO:
 * 
 * Inclusión directa:
 * - perfil.php
 * - ventas.php
 * - carrito.php
 * - marketing.php
 * - marketing-editar-producto.php
 * - marketing-confirmar-csv.php
 * 
 * Inclusión indirecta (a través de otros archivos):
 * - includes/queries/pago_queries.php (usa: descontarStockPedido, revertirStockPedido, verificarVentasPreviasPedido)
 * - includes/queries/pedido_queries.php (usa: revertirStockPedido, registrarMovimientoStock)
 * 
 * ÍNDICE DE FUNCIONES:
 * 
 * === VALIDACIONES DE STOCK ===
 * - validarAjusteStock() - Valida que ajustes negativos no causen stock negativo
 * - validarStockDisponibleVenta() - Valida stock suficiente antes de venta
 * - validarStockDisponibleCarrito() - Valida stock para operaciones de carrito (validación previa)
 * - validarStockNoNegativo() - Valida que stock no sea negativo al actualizar directamente
 * - validarVarianteYProductoActivos() - Valida que variante y producto estén activos
 * 
 * === ACTUALIZACIÓN DE STOCK ===
 * - actualizarStockDesdeMovimiento() - Actualiza stock según tipo de movimiento (interna)
 * - actualizarStockVariante() - Actualiza stock directamente con validación
 * - registrarMovimientoStock() - Registra movimiento y actualiza stock automáticamente
 * 
 * === GESTIÓN DE PEDIDOS ===
 * - descontarStockPedido() - Descuenta stock al aprobar un pago (idempotente)
 * - revertirStockPedido() - Revierte stock de pedido cancelado/rechazado (idempotente)
 * - verificarVentasPreviasPedido() - Guardrail para prevenir descuentos duplicados
 * - verificarCoherenciaStockDespuesDescuento() - Sanity check post-condición (solo debug)
 * 
 * === CONSULTAS DE STOCK ===
 * - obtenerStockDisponible() - Obtiene stock disponible de una variante
 * - obtenerMovimientosStockRecientes() - Obtiene movimientos recientes para métricas
 * 
 * === GESTIÓN DE VARIANTES ===
 * - insertarVarianteStock() - Inserta nueva variante de stock
 * - obtenerVariantePorId() - Obtiene datos de una variante por ID
 * - verificarVarianteExistente() - Verifica si existe variante con misma combinación
 * - actualizarVarianteStock() - Actualiza talle y color de una variante
 * - desactivarVarianteStock() - Desactiva una variante (soft delete)
 * - obtenerTodasVariantesProductoPorGrupo() - Obtiene variantes agrupadas por producto
 * 
 * NOTAS IMPORTANTES:
 * - Todas las operaciones de stock deben usar las funciones de este archivo
 * - NO usar queries SQL inline para operaciones de stock (usar funciones centralizadas)
 * - Las funciones de gestión de pedidos (descontarStockPedido, revertirStockPedido) son idempotentes
 * - El sanity check (verificarCoherenciaStockDespuesDescuento) solo se ejecuta en modo debug
 * 
 * ========================================================================
 */

// ============================================================================
// SECCIÓN 1: VALIDACIONES DE STOCK
// ============================================================================

/**
 * Valida que un ajuste no cause stock negativo y que producto/variante estén activos
 * 
 * FUNCIÓN INTERNA: Usada principalmente por registrarMovimientoStock() para validar
 * ajustes (positivos y negativos) antes de aplicarlos. También puede usarse directamente
 * para validaciones previas.
 * 
 * Valida:
 * - Que la variante y el producto estén activos (no permite ajustes en productos inactivos)
 * - Que ajustes negativos no causen stock negativo
 * 
 * Reemplaza la lógica del trigger trg_validar_ajuste_stock
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @param int $cantidad Cantidad del ajuste (puede ser negativa para restar, positiva para sumar)
 * @throws Exception Si el ajuste causaría stock negativo o la variante/producto están inactivos
 */
function validarAjusteStock($mysqli, $id_variante, $cantidad) {
    // Validación previa sin FOR UPDATE - la validación real se hace en actualizarStockDesdeMovimiento()
    // con actualización atómica que previene race conditions
    $sql = "
        SELECT sv.stock, sv.activo as variante_activa, p.activo as producto_activo 
        FROM Stock_Variantes sv
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        WHERE sv.id_variante = ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error al validar ajuste de stock para variante #' . $id_variante . ': ' . $mysqli->error);
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
    
    // Validación previa (la validación real se hace en actualizarStockDesdeMovimiento con WHERE)
    if (($stock_actual + $cantidad) < 0) {
        $resultado = $stock_actual + $cantidad;
        throw new Exception("El ajuste causaría stock negativo. Stock actual: {$stock_actual}, Ajuste: {$cantidad}, Resultado esperado: {$resultado}");
    }
}

/**
 * Valida que hay stock suficiente antes de crear un movimiento de venta
 * 
 * FUNCIÓN INTERNA: Usada principalmente por registrarMovimientoStock() para validar
 * stock disponible antes de registrar una venta. También puede usarse directamente
 * para validaciones previas.
 * 
 * Reemplaza la lógica del trigger trg_validar_stock_disponible_antes_venta
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @param int $cantidad Cantidad a vender (siempre positiva)
 * @throws Exception Si no hay stock suficiente o la variante/producto están inactivos
 */
function validarStockDisponibleVenta($mysqli, $id_variante, $cantidad) {
    // Validación previa sin FOR UPDATE - la validación real se hace en actualizarStockDesdeMovimiento()
    // con actualización atómica que previene race conditions
    $sql = "
        SELECT sv.stock, sv.activo as variante_activa, p.activo as producto_activo 
        FROM Stock_Variantes sv
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        WHERE sv.id_variante = ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error al validar stock disponible para variante #' . $id_variante . ': ' . $mysqli->error);
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
    
    // Validación previa (la validación real se hace en actualizarStockDesdeMovimiento con WHERE)
    if ($stock_actual < $cantidad) {
        throw new Exception("Stock insuficiente. Stock disponible: {$stock_actual}, Intento de venta: {$cantidad}");
    }
}

/**
 * Valida stock disponible para operaciones de carrito (validación orientativa)
 * 
 * NOTA: Esta es una validación orientativa para mejorar la UX del carrito.
 * La validación final y bloqueo real del stock se realiza en checkout mediante
 * descontarStockPedido() y registrarMovimientoStock(), que sí usan transacciones
 * con validaciones atómicas en WHERE para garantizar coherencia.
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
    
    // Validación orientativa del stock para el carrito
    // La validación final y bloqueo real se hace en checkout (descontarStockPedido)
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
}

// ============================================================================
// SECCIÓN 2: ACTUALIZACIÓN DE STOCK
// ============================================================================

/**
 * Actualiza el stock de una variante según el tipo de movimiento
 * 
 * FUNCIÓN INTERNA: Usada exclusivamente por registrarMovimientoStock() para
 * aplicar el cambio de stock después de registrar el movimiento. No debe
 * llamarse directamente desde fuera de este archivo.
 * 
 * Reemplaza la lógica del trigger trg_actualizar_stock_insert
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @param string $tipo_movimiento Tipo de movimiento ('venta', 'ingreso', 'ajuste')
 * @param int $cantidad Cantidad del movimiento (positiva para venta/ingreso, puede ser negativa para ajuste)
 * @throws Exception Si hay error al actualizar o tipo de movimiento inválido
 */
function actualizarStockDesdeMovimiento($mysqli, $id_variante, $tipo_movimiento, $cantidad) {
    // Determinar el cambio de stock según el tipo de movimiento
    // Usar actualizaciones atómicas con validación en WHERE para evitar race conditions sin FOR UPDATE
    if ($tipo_movimiento === 'venta') {
        // Restar stock (venta) - validar que stock >= cantidad para evitar negativo
        $sql = "
            UPDATE Stock_Variantes 
            SET stock = stock - ?,
                fecha_actualizacion = NOW()
            WHERE id_variante = ?
              AND stock >= ?
        ";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error al preparar actualización de stock para variante #' . $id_variante . ' (tipo: venta): ' . $mysqli->error);
        }
        $stmt->bind_param('iii', $cantidad, $id_variante, $cantidad);
    } elseif ($tipo_movimiento === 'ajuste') {
        // Los ajustes pueden tener cantidad positiva (suma stock) o negativa (resta stock)
        // Para ajustes negativos, validar que stock + cantidad >= 0
        if ($cantidad < 0) {
            $sql = "
                UPDATE Stock_Variantes 
                SET stock = stock + ?,
                    fecha_actualizacion = NOW()
                WHERE id_variante = ?
                  AND stock >= ?
            ";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) {
                throw new Exception('Error al preparar actualización de stock');
            }
            $cantidad_abs = abs($cantidad);
            $stmt->bind_param('iii', $cantidad, $id_variante, $cantidad_abs);
        } else {
            // Ajuste positivo, no necesita validación
            $sql = "
                UPDATE Stock_Variantes 
                SET stock = stock + ?,
                    fecha_actualizacion = NOW()
                WHERE id_variante = ?
            ";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) {
                throw new Exception('Error al preparar actualización de stock');
            }
            $stmt->bind_param('ii', $cantidad, $id_variante);
        }
    } elseif ($tipo_movimiento === 'ingreso') {
        // Sumar stock (ingreso) - siempre positivo, no necesita validación
        $sql = "
            UPDATE Stock_Variantes 
            SET stock = stock + ?,
                fecha_actualizacion = NOW()
            WHERE id_variante = ?
        ";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error al preparar actualización de stock');
        }
        $stmt->bind_param('ii', $cantidad, $id_variante);
    } else {
        throw new Exception("Tipo de movimiento inválido: {$tipo_movimiento}");
    }
    
    $resultado = $stmt->execute();
    $rows_affected = $stmt->affected_rows;
    $stmt->close();
    
    if (!$resultado) {
        throw new Exception('Error al actualizar stock de la variante: ' . $mysqli->error);
    }
    
    // Verificar que se actualizó la fila (si no, puede ser por validación en WHERE)
    if ($rows_affected === 0) {
        // Obtener stock actual para mensaje de error más informativo
        $sql_stock = "SELECT stock FROM Stock_Variantes WHERE id_variante = ?";
        $stmt_stock = $mysqli->prepare($sql_stock);
        if ($stmt_stock) {
            $stmt_stock->bind_param('i', $id_variante);
            $stmt_stock->execute();
            $result_stock = $stmt_stock->get_result();
            $datos_stock = $result_stock->fetch_assoc();
            $stmt_stock->close();
            
            $stock_actual = $datos_stock ? intval($datos_stock['stock']) : 0;
            
            if ($tipo_movimiento === 'venta') {
                throw new Exception("Stock insuficiente para variante #{$id_variante}. Stock disponible: {$stock_actual}, Cantidad solicitada: {$cantidad}");
            } elseif ($tipo_movimiento === 'ajuste' && $cantidad < 0) {
                $resultado_esperado = $stock_actual + $cantidad;
                throw new Exception("El ajuste causaría stock negativo para variante #{$id_variante}. Stock actual: {$stock_actual}, Ajuste: {$cantidad}, Resultado esperado: {$resultado_esperado}");
            } else {
                throw new Exception("No se pudo actualizar stock para variante #{$id_variante}. La variante puede no existir.");
            }
        } else {
            throw new Exception("No se pudo actualizar stock para variante #{$id_variante}");
        }
    }
}

/**
 * Valida que el stock no sea negativo al actualizar Stock_Variantes directamente
 * 
 * FUNCIÓN INTERNA: Usada principalmente por actualizarStockVariante() para validar
 * que una actualización directa de stock no resulte en valores negativos.
 * 
 * Reemplaza la lógica del trigger trg_validar_stock_antes_update
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @param int $nuevo_stock Nuevo valor de stock a validar
 * @throws Exception Si el stock sería negativo
 */
function validarStockNoNegativo($mysqli, $id_variante, $nuevo_stock) {
    if ($nuevo_stock < 0) {
        // Obtener stock actual para el mensaje de error completo
        $sql = "SELECT stock FROM Stock_Variantes WHERE id_variante = ?";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $id_variante);
            $stmt->execute();
            $result = $stmt->get_result();
            $datos = $result->fetch_assoc();
            $stmt->close();
            
            $stock_actual = $datos ? intval($datos['stock']) : 0;
            throw new Exception("No se puede tener stock negativo para variante #{$id_variante}. Stock actual: {$stock_actual}, Intento de actualizar a: {$nuevo_stock}");
        } else {
            throw new Exception("No se puede tener stock negativo para variante #{$id_variante}. Intento de actualizar a: {$nuevo_stock}");
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
        throw new Exception('Error al preparar actualización de stock para variante #' . $id_variante . ': ' . $mysqli->error);
    }
    
    $stmt->bind_param('ii', $nuevo_stock, $id_variante);
    $resultado = $stmt->execute();
    $stmt->close();
    
    if (!$resultado) {
        throw new Exception('Error al actualizar stock de la variante #' . $id_variante . ' a ' . $nuevo_stock . ': ' . $mysqli->error);
    }
    
    return true;
}

/**
 * Registra un movimiento de stock y actualiza el stock automáticamente
 * 
 * FUNCIÓN PRINCIPAL: Esta es la función central para registrar cualquier movimiento
 * de stock. Inserta el registro en Movimientos_Stock y actualiza el stock de la
 * variante automáticamente.
 * 
 * IMPORTANTE: La cantidad siempre debe ser positiva, el tipo_movimiento indica dirección
 * Reemplaza la funcionalidad de los triggers: ahora actualiza stock manualmente en PHP
 * 
 * PATRÓN DE TRANSACCIONES:
 * - Si $en_transaccion = false: La función inicia su propia transacción y hace commit/rollback
 * - Si $en_transaccion = true: Asume que ya está dentro de una transacción y NO hace commit/rollback
 * 
 * VALIDACIONES AUTOMÁTICAS:
 * - Para tipo 'ajuste': valida que no cause stock negativo y que producto/variante estén activos
 * - Para tipo 'venta': valida que haya stock suficiente disponible
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
        // Validar ajustes (positivos y negativos) antes de insertar (reemplaza trg_validar_ajuste_stock)
        // Valida que no se ajuste stock de productos/variantes inactivos y que ajustes negativos no causen stock negativo
        if ($tipo_movimiento === 'ajuste') {
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
            throw new Exception('Error al preparar inserción de movimiento de stock para variante #' . $id_variante . ' (tipo: ' . $tipo_movimiento . '): ' . $mysqli->error);
        }
        
        $stmt->bind_param('isiiss', $id_variante, $tipo_movimiento, $cantidad, $id_usuario, $id_pedido, $observaciones);
        $resultado = $stmt->execute();
        $stmt->close();
        
        if (!$resultado) {
            throw new Exception('Error al insertar movimiento de stock para variante #' . $id_variante . ' (tipo: ' . $tipo_movimiento . ', cantidad: ' . $cantidad . '): ' . $mysqli->error);
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
        error_log("Error en registrarMovimientoStock (variante #{$id_variante}, tipo: {$tipo_movimiento}, cantidad: {$cantidad}): " . $e->getMessage());
        
        // Propagar excepción con contexto adicional
        $mensaje_contexto = "Error al registrar movimiento de stock para variante #{$id_variante}";
        if ($tipo_movimiento === 'venta') {
            $mensaje_contexto .= " (cantidad: {$cantidad})";
        }
        if ($id_pedido) {
            $mensaje_contexto .= " - Pedido #{$id_pedido}";
        }
        $mensaje_contexto .= ". " . $e->getMessage();
        
        throw new Exception($mensaje_contexto, 0, $e);
    }
}

// ============================================================================
// SECCIÓN 3: GESTIÓN DE PEDIDOS Y STOCK
// ============================================================================

/**
 * Verifica si ya existen movimientos tipo 'venta' para un pedido
 * 
 * GUARDRAIL: Previene descuentos duplicados por bugs o doble click.
 * Debe llamarse ANTES de descontarStockPedido() para verificar que no
 * se haya descontado stock previamente para el mismo pedido.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @return int Número de movimientos tipo 'venta' para el pedido
 */
function verificarVentasPreviasPedido($mysqli, $id_pedido) {
    $sql = "
        SELECT COUNT(*) as cantidad_ventas
        FROM Movimientos_Stock
        WHERE id_pedido = ?
          AND tipo_movimiento = 'venta'
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("Error al preparar verificación de ventas previas para pedido #{$id_pedido}: " . $mysqli->error);
        return 0; // En caso de error, retornar 0 para no bloquear (pero loguear)
    }
    
    $stmt->bind_param('i', $id_pedido);
    if (!$stmt->execute()) {
        error_log("Error al ejecutar verificación de ventas previas para pedido #{$id_pedido}: " . $stmt->error);
        $stmt->close();
        return 0;
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return intval($row['cantidad_ventas'] ?? 0);
}

/**
 * Verifica la coherencia del stock después de descontar (sanity check)
 * 
 * SANITY CHECK: Compara el stock teórico (stock_antes - cantidad) con el stock
 * real en BD para detectar inconsistencias. Solo se ejecuta en modo debug para
 * no afectar rendimiento en producción.
 * 
 * MODO DEBUG: Se ejecuta solo si:
 * - ini_get('display_errors') == 1, o
 * - Se define la constante DEBUG_STOCK_CHECK = true
 * 
 * PROPÓSITO: Detectar movimientos de stock hechos por fuera de las funciones
 * centrales, o errores en la lógica de actualización de stock.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @param array $stock_antes Array asociativo [id_variante => stock_antes] capturado antes del descuento
 * @return bool True si la coherencia es correcta, false si hay inconsistencia
 */
function verificarCoherenciaStockDespuesDescuento($mysqli, $id_pedido, $stock_antes) {
    // Solo ejecutar en modo debug
    $modo_debug = (ini_get('display_errors') == 1) || defined('DEBUG_STOCK_CHECK');
    if (!$modo_debug) {
        return true; // En producción, no ejecutar para no afectar rendimiento
    }
    
    // Obtener detalles del pedido
    $sql_detalles = "
        SELECT id_variante, cantidad
        FROM Detalle_Pedido
        WHERE id_pedido = ?
    ";
    
    $stmt_detalles = $mysqli->prepare($sql_detalles);
    if (!$stmt_detalles) {
        error_log("Error al preparar consulta de detalles para sanity check: " . $mysqli->error);
        return true; // En caso de error, no bloquear
    }
    
    $stmt_detalles->bind_param('i', $id_pedido);
    if (!$stmt_detalles->execute()) {
        error_log("Error al ejecutar consulta de detalles para sanity check: " . $stmt_detalles->error);
        $stmt_detalles->close();
        return true;
    }
    
    $result_detalles = $stmt_detalles->get_result();
    $detalles = [];
    while ($row = $result_detalles->fetch_assoc()) {
        $detalles[] = [
            'id_variante' => intval($row['id_variante']),
            'cantidad' => intval($row['cantidad'])
        ];
    }
    $stmt_detalles->close();
    
    $inconsistencias = [];
    
    // Verificar cada variante
    foreach ($detalles as $detalle) {
        $id_variante = $detalle['id_variante'];
        $cantidad_pedido = $detalle['cantidad'];
        $stock_antes_variante = isset($stock_antes[$id_variante]) ? intval($stock_antes[$id_variante]) : null;
        
        if ($stock_antes_variante === null) {
            continue; // No se capturó stock antes, saltar esta variante
        }
        
        // Calcular stock teórico
        $stock_teorico = $stock_antes_variante - $cantidad_pedido;
        
        // Obtener stock real de BD
        $sql_stock = "SELECT stock FROM Stock_Variantes WHERE id_variante = ?";
        $stmt_stock = $mysqli->prepare($sql_stock);
        if (!$stmt_stock) {
            continue; // Error al preparar, saltar
        }
        
        $stmt_stock->bind_param('i', $id_variante);
        if (!$stmt_stock->execute()) {
            $stmt_stock->close();
            continue;
        }
        
        $result_stock = $stmt_stock->get_result();
        $stock_real_data = $result_stock->fetch_assoc();
        $stmt_stock->close();
        
        if (!$stock_real_data) {
            continue; // Variante no encontrada, saltar
        }
        
        $stock_real = intval($stock_real_data['stock']);
        
        // Comparar stock teórico vs real
        if ($stock_real !== $stock_teorico) {
            $inconsistencias[] = [
                'id_variante' => $id_variante,
                'stock_antes' => $stock_antes_variante,
                'cantidad_pedido' => $cantidad_pedido,
                'stock_teorico' => $stock_teorico,
                'stock_real' => $stock_real,
                'diferencia' => $stock_real - $stock_teorico
            ];
        }
    }
    
    // Si hay inconsistencias, loguear o lanzar excepción
    if (!empty($inconsistencias)) {
        $mensaje = "INCONSISTENCIA_STOCK_DETECTADA en pedido #{$id_pedido}: ";
        foreach ($inconsistencias as $inc) {
            $mensaje .= "Variante #{$inc['id_variante']}: Teórico={$inc['stock_teorico']}, Real={$inc['stock_real']}, Diferencia={$inc['diferencia']}; ";
        }
        
        error_log($mensaje);
        
        // En modo debug estricto, lanzar excepción
        if (defined('DEBUG_STOCK_CHECK') && DEBUG_STOCK_CHECK === true) {
            throw new Exception($mensaje);
        }
        
        return false;
    }
    
    return true;
}

/**
 * Descuenta stock cuando se aprueba un pago
 * Crea movimientos de tipo 'venta' para cada producto del pedido
 * 
 * DIFERENCIAS CON revertirStockPedido():
 * - descontarStockPedido(): Descuenta stock al aprobar pago (crea movimientos tipo 'venta')
 * - revertirStockPedido(): Restaura stock al cancelar/rechazar pedido (crea movimientos tipo 'ingreso')
 * 
 * IDEMPOTENCIA: Esta función es idempotente. Si se llama múltiples veces para el mismo pedido,
 * solo descontará stock la primera vez. Llamadas subsecuentes retornarán true sin hacer nada.
 * Esto previene descuentos duplicados por doble click, callbacks duplicados del proveedor de pago,
 * o bugs en la capa de negocio.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @param int $id_usuario ID del usuario que aprueba (opcional)
 * @param bool $en_transaccion Si true, no inicia/commit transacción (ya está dentro de una)
 * @return bool True si se descontó correctamente o ya estaba descontado (idempotente)
 */
function descontarStockPedido($mysqli, $id_pedido, $id_usuario = null, $en_transaccion = false) {
    // Guardrail de idempotencia: verificar si ya se descontó stock para este pedido
    $ventas_previas = verificarVentasPreviasPedido($mysqli, $id_pedido);
    if ($ventas_previas > 0) {
        // Ya se descontó stock antes para este pedido, no hacer nada (idempotente)
        error_log("descontarStockPedido: Pedido #{$id_pedido} ya tiene {$ventas_previas} ventas registradas. Operación idempotente.");
        return true;
    }
    
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
        // Capturar stock antes del descuento para sanity check (solo en modo debug)
        $modo_debug = (ini_get('display_errors') == 1) || defined('DEBUG_STOCK_CHECK');
        $stock_antes = [];
        
        if ($modo_debug) {
            // Obtener stock antes del descuento para cada variante
            foreach ($detalles as $detalle) {
                $id_variante = intval($detalle['id_variante']);
                $sql_stock_antes = "SELECT stock FROM Stock_Variantes WHERE id_variante = ?";
                $stmt_stock_antes = $mysqli->prepare($sql_stock_antes);
                if ($stmt_stock_antes) {
                    $stmt_stock_antes->bind_param('i', $id_variante);
                    if ($stmt_stock_antes->execute()) {
                        $result_stock_antes = $stmt_stock_antes->get_result();
                        $stock_data = $result_stock_antes->fetch_assoc();
                        if ($stock_data) {
                            $stock_antes[$id_variante] = intval($stock_data['stock']);
                        }
                    }
                    $stmt_stock_antes->close();
                }
            }
        }
        
        foreach ($detalles as $detalle) {
            $id_variante = intval($detalle['id_variante']);
            $cantidad = intval($detalle['cantidad']);
            
            // Registrar movimiento de venta (cantidad siempre positiva)
            // registrarMovimientoStock() valida stock disponible y actualiza con validación atómica
            $observaciones = "Venta confirmada - Pedido #{$id_pedido}";
            try {
                registrarMovimientoStock($mysqli, $id_variante, 'venta', $cantidad, $id_usuario, $id_pedido, $observaciones, true);
            } catch (Exception $e) {
                // Re-lanzar con contexto adicional del pedido
                throw new Exception("Error al descontar stock del pedido #{$id_pedido} - Variante #{$id_variante} (cantidad: {$cantidad}): " . $e->getMessage(), 0, $e);
            }
        }
        
        // Sanity check post-condición (solo en modo debug)
        if ($modo_debug && !empty($stock_antes)) {
            verificarCoherenciaStockDespuesDescuento($mysqli, $id_pedido, $stock_antes);
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
 * Revierte el stock de un pedido cancelado de forma idempotente
 * Calcula el neto de movimientos de stock por variante y revierte solo lo que realmente falta
 * 
 * DIFERENCIAS CON descontarStockPedido():
 * - revertirStockPedido(): Restaura stock al cancelar/rechazar pedido (crea movimientos tipo 'ingreso')
 * - descontarStockPedido(): Descuenta stock al aprobar pago (crea movimientos tipo 'venta')
 * 
 * MEJORAS IMPLEMENTADAS:
 * - Valida estado del pedido antes de revertir (solo revierte si pedido está cancelado)
 * - Idempotencia: Calcula el neto de movimientos y solo revierte si neto > 0
 * - No infla stock en llamadas múltiples: si neto <= 0, no hace nada
 * - Previene reversiones en pedidos activos (preparacion, en_viaje) para evitar inconsistencias de stock
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido a revertir
 * @param int $id_usuario ID del usuario que realiza la reversión (opcional)
 * @param string $motivo Motivo de la reversión (opcional)
 * @return bool True si se revirtió correctamente o ya estaba revertido
 */
function revertirStockPedido($mysqli, $id_pedido, $id_usuario = null, $motivo = null) {
    // PASO 1: Validar estado del pedido antes de revertir stock
    // Solo se debe revertir si el pedido está cancelado
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
    
    // Validar que el pedido esté cancelado antes de revertir stock
    // Solo revertir si el pedido está cancelado para evitar reversiones en pedidos activos
    // El estado del pago se usa solo para logging, no como condición de validación
    $puede_revertir = false;
    
    // Solo revertir si el pedido está cancelado
    if ($estado_pedido === 'cancelado') {
        $puede_revertir = true;
    }
    
    if (!$puede_revertir) {
        error_log("No se puede revertir stock del pedido #{$id_pedido}: Estado pedido='{$estado_pedido}', Estado pago='{$estado_pago}'. Solo se permite revertir si el pedido está cancelado.");
        return false; // No revertir si el estado no lo permite
    }
    
    // Iniciar transacción
    $mysqli->begin_transaction();
    
    try {
        // PASO 2: Calcular neto de movimientos por variante
        // ESTRATEGIA SIMPLIFICADA: Dividir en 2 queries simples en lugar de una query compleja con CASE
        // Query 1: Obtener ventas (salidas) por variante
        // Query 2: Obtener ingresos/devoluciones (entradas) por variante
        // Calcular neto en PHP: neto = ventas - entradas
        
        // Query 1: Obtener ventas (salidas) agrupadas por variante
        $sql_ventas = "
            SELECT id_variante, SUM(cantidad) as total_ventas
            FROM Movimientos_Stock
            WHERE id_pedido = ? AND tipo_movimiento = 'venta'
            GROUP BY id_variante
        ";
        
        $stmt_ventas = $mysqli->prepare($sql_ventas);
        if (!$stmt_ventas) {
            throw new Exception('Error al preparar consulta de ventas: ' . $mysqli->error);
        }
        
        $stmt_ventas->bind_param('i', $id_pedido);
        if (!$stmt_ventas->execute()) {
            $error_msg = $stmt_ventas->error;
            $stmt_ventas->close();
            throw new Exception('Error al obtener ventas: ' . $error_msg);
        }
        
        $result_ventas = $stmt_ventas->get_result();
        $ventas_por_variante = [];
        while ($row = $result_ventas->fetch_assoc()) {
            $ventas_por_variante[intval($row['id_variante'])] = intval($row['total_ventas']);
        }
        $stmt_ventas->close();
        
        // Query 2: Obtener ingresos/devoluciones (entradas) agrupadas por variante
        $sql_entradas = "
            SELECT id_variante, SUM(cantidad) as total_entradas
            FROM Movimientos_Stock
            WHERE id_pedido = ? AND tipo_movimiento IN ('ingreso', 'devolucion')
            GROUP BY id_variante
        ";
        
        $stmt_entradas = $mysqli->prepare($sql_entradas);
        if (!$stmt_entradas) {
            throw new Exception('Error al preparar consulta de entradas: ' . $mysqli->error);
        }
        
        $stmt_entradas->bind_param('i', $id_pedido);
        if (!$stmt_entradas->execute()) {
            $error_msg = $stmt_entradas->error;
            $stmt_entradas->close();
            throw new Exception('Error al obtener entradas: ' . $error_msg);
        }
        
        $result_entradas = $stmt_entradas->get_result();
        $entradas_por_variante = [];
        while ($row = $result_entradas->fetch_assoc()) {
            $entradas_por_variante[intval($row['id_variante'])] = intval($row['total_entradas']);
        }
        $stmt_entradas->close();
        
        // Calcular neto en PHP: neto = ventas - entradas
        // Solo incluir variantes con neto > 0 (hay stock descontado que falta revertir)
        $variantes_con_neto = [];
        foreach ($ventas_por_variante as $id_variante => $total_ventas) {
            $total_entradas = $entradas_por_variante[$id_variante] ?? 0;
            $neto = $total_ventas - $total_entradas;
            if ($neto > 0) {
                $variantes_con_neto[] = [
                    'id_variante' => $id_variante,
                    'neto' => $neto
                ];
            }
        }
        
        // Si no hay variantes con neto > 0, no hay nada que revertir (idempotencia)
        if (empty($variantes_con_neto)) {
            $mysqli->commit();
            error_log("Stock del pedido #{$id_pedido} ya está revertido o nunca fue descontado. Neto = 0 o negativo.");
            return true; // Ya está revertido, retornar éxito sin hacer nada
        }
        
        // PASO 3: Revertir solo variantes con neto > 0
        foreach ($variantes_con_neto as $variante) {
            $id_variante = $variante['id_variante'];
            $neto = $variante['neto'];
            
            // Crear movimiento de ingreso con cantidad = neto
            $observaciones = "Reversión venta Pedido #{$id_pedido}";
            if ($motivo) {
                $observaciones .= " - Motivo: {$motivo}";
            }
            
            // Registrar movimiento de ingreso para restaurar stock
            if (!registrarMovimientoStock($mysqli, $id_variante, 'ingreso', $neto, $id_usuario, $id_pedido, $observaciones, true)) {
                throw new Exception("Error al registrar reversión de stock para variante #{$id_variante}");
            }
        }
        
        $mysqli->commit();
        error_log("Stock del pedido #{$id_pedido} revertido exitosamente. Variantes revertidas: " . count($variantes_con_neto));
        return true;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Error al revertir stock del pedido #{$id_pedido}: " . $e->getMessage());
        return false;
    }
}

// ============================================================================
// SECCIÓN 4: CONSULTAS DE STOCK
// ============================================================================

/**
 * Obtiene el stock disponible de una variante consultando directamente la base de datos
 * 
 * NOTA: Esta función consulta la BD directamente por ID de variante.
 * Para trabajar con arrays de variantes ya cargados en memoria, usar obtenerStock() 
 * de includes/producto_functions.php
 * 
 * DIFERENCIAS:
 * - obtenerStockDisponible(): Consulta la BD directamente por ID de variante (requiere $mysqli)
 * - obtenerStock(): Trabaja con arrays en memoria, busca por talle+color en variantes ya cargadas
 * 
 * Función simple para consultar el stock actual de una variante.
 * No realiza bloqueos (FOR UPDATE), útil para consultas de solo lectura.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @return int Stock disponible (0 si la variante no existe)
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
 * Obtiene los movimientos de stock más recientes para métricas
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $limite Número máximo de movimientos a retornar (default 50)
 * @return array Array de movimientos de stock con información completa
 */
function obtenerMovimientosStockRecientes($mysqli, $limite = 50) {
    // NOTA: Esta consulta usa 4 JOINs pero es eficiente porque:
    // - Todos los JOINs son necesarios para obtener información completa (variante, producto, categoría, usuario)
    // - Los índices de Foreign Keys optimizan automáticamente estos JOINs
    // - LEFT JOIN para Usuarios es correcto (id_usuario puede ser NULL)
    // - La consulta es simple (SELECT, JOINs, ORDER BY, LIMIT) sin agregaciones complejas
    // - No requiere división en múltiples queries porque todos los datos son necesarios en un solo resultado
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

// ============================================================================
// SECCIÓN 5: GESTIÓN DE VARIANTES DE STOCK
// ============================================================================

/**
 * Inserta una nueva variante de stock
 * 
 * Crea una nueva variante de stock para un producto con talle y color específicos.
 * El stock inicial se establece al crear la variante, pero se recomienda
 * actualizar el stock mediante movimientos usando registrarMovimientoStock().
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @param string $talle Talle de la variante
 * @param string $color Color de la variante
 * @param int $stock_inicial Stock inicial (por defecto 0, se actualiza mediante movimiento)
 * @return int|false ID de la variante insertada o false en caso de error
 */
function insertarVarianteStock($mysqli, $id_producto, $talle, $color, $stock_inicial = 0) {
    // IMPORTANTE: Siempre insertar con stock=0, independientemente del parámetro recibido
    // El stock debe manejarse únicamente a través de movimientos (registrarMovimientoStock)
    // para mantener la trazabilidad y consistencia del sistema
    $stock_inicial = 0;
    
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
 * Verifica si ya existe una variante con la misma combinación de talle y color en un producto específico
 * 
 * DIFERENCIA CON verificarVarianteExistente():
 * - Esta función verifica solo en el producto específico (id_producto)
 * - verificarVarianteExistente() verifica en todo el grupo de productos (mismo nombre, categoría, género)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto específico
 * @param string $talle Talle a verificar
 * @param string $color Color a verificar
 * @param int|null $excluir_id_variante ID de variante a excluir de la verificación (opcional)
 * @return bool True si existe, false si no existe
 */
function verificarVarianteExistentePorProducto($mysqli, $id_producto, $talle, $color, $excluir_id_variante = null) {
    $sql = "
        SELECT id_variante 
        FROM Stock_Variantes
        WHERE id_producto = ?
        AND talle = ?
        AND color = ?
        AND activo = 1
    ";
    
    if ($excluir_id_variante !== null) {
        $sql .= " AND id_variante != ?";
    }
    
    $sql .= " LIMIT 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR verificarVarianteExistentePorProducto - prepare falló: " . $mysqli->error);
        return false;
    }
    
    if ($excluir_id_variante !== null) {
        $stmt->bind_param('issi', $id_producto, $talle, $color, $excluir_id_variante);
    } else {
        $stmt->bind_param('iss', $id_producto, $talle, $color);
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

/**
 * Valida que una variante y su producto estén activos
 * 
 * Función de validación reutilizable para verificar el estado activo de una variante
 * y su producto asociado. Útil para validaciones previas antes de operaciones que
 * requieren que ambos estén activos.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante a validar
 * @param bool $usar_for_update Si true, usa FOR UPDATE para bloqueo de fila (requiere transacción)
 * @return array Array con 'variante_activa' => bool, 'producto_activo' => bool
 * @throws Exception Si la variante no existe o si está inactiva o su producto está inactivo
 */
function validarVarianteYProductoActivos($mysqli, $id_variante, $usar_for_update = false) {
    $sql = "
        SELECT sv.activo as variante_activa, p.activo as producto_activo 
        FROM Stock_Variantes sv
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        WHERE sv.id_variante = ?
    ";
    
    if ($usar_for_update) {
        $sql .= " FOR UPDATE";
    }
    
    $sql .= " LIMIT 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error al validar estado de variante y producto: ' . $mysqli->error);
    }
    
    $stmt->bind_param('i', $id_variante);
    if (!$stmt->execute()) {
        $error_msg = $stmt->error;
        $stmt->close();
        throw new Exception('Error al ejecutar validación de variante y producto: ' . $error_msg);
    }
    
    $result = $stmt->get_result();
    $datos = $result->fetch_assoc();
    $stmt->close();
    
    if (!$datos || !is_array($datos)) {
        throw new Exception('La variante de stock no existe');
    }
    
    $variante_activa = intval($datos['variante_activa']) === 1;
    $producto_activo = intval($datos['producto_activo']) === 1;
    
    if (!$variante_activa) {
        throw new Exception('No se puede operar con una variante inactiva');
    }
    
    if (!$producto_activo) {
        throw new Exception('No se puede operar con un producto inactivo');
    }
    
    return [
        'variante_activa' => $variante_activa,
        'producto_activo' => $producto_activo
    ];
}

