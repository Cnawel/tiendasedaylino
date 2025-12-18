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
 * - trg_validar_stock_antes_update: (validación integrada en actualizarStockDesdeMovimiento())
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
 */

/*
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
 * - validarStockDisponible() - Función unificada para validar stock (preliminar o definitivo)
 * - validarStockDisponibleVenta() - Valida stock suficiente antes de venta
 * - obtenerStockReservado() - Calcula stock reservado por pedidos pendientes
 * 
 * === ACTUALIZACIÓN DE STOCK ===
 * - actualizarStockDesdeMovimiento() - Actualiza stock según tipo de movimiento (interna)
 * - registrarMovimientoStock() - Registra movimiento y actualiza stock automáticamente
 * 
 * === GESTIÓN DE PEDIDOS ===
 * - descontarStockPedido() - Descuenta stock al aprobar un pago (idempotente)
 * - revertirStockPedido() - Revierte stock de pedido cancelado/rechazado (idempotente)
 * - verificarVentasPreviasPedido() - Guardrail para prevenir descuentos duplicados
 * - verificarCoherenciaStockDespuesDescuento() - Sanity check post-condición (solo debug)
 * 
 * === CONSULTAS DE STOCK ===
 * - obtenerMovimientosStockRecientes() - Obtiene movimientos recientes para métricas
 * - obtenerColoresUnicosActivos() - Obtiene todos los colores únicos y activos de Stock_Variantes, normalizados.
 * 
 * === GESTIÓN DE VARIANTES ===
 * - insertarVarianteStock() - Inserta nueva variante de stock
 * - obtenerVariantePorId() - Obtiene datos de una variante por ID
 * - verificarVarianteExistente() - Verifica si existe variante con misma combinación
 * - actualizarVarianteStock() - Actualiza talle y color de una variante
 * - desactivarVarianteStock() - Desactiva una variante (soft delete)
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
 * Obtiene datos de variante y producto para validaciones
 * 
 * @internal FUNCIÓN INTERNA: Usada por validarAjusteStock() y validarStockDisponibleVenta()
 * para obtener datos comunes y evitar código duplicado.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @return array Array con 'stock', 'variante_activa', 'producto_activo'
 * @throws Exception Si la variante no existe o hay error en la consulta
 */
function _obtenerDatosVarianteYProducto($mysqli, $id_variante) {
    $sql = "
        SELECT sv.stock, sv.activo as variante_activa, p.activo as producto_activo 
        FROM Stock_Variantes sv
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        WHERE sv.id_variante = ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error al obtener datos de variante #' . $id_variante . ': ' . $mysqli->error);
    }
    
    $stmt->bind_param('i', $id_variante);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos = $result->fetch_assoc();
    $stmt->close();
    
    if (!$datos) {
        throw new Exception('La variante de stock no existe');
    }
    
    return [
        'stock' => intval($datos['stock']),
        'variante_activa' => intval($datos['variante_activa']),
        'producto_activo' => intval($datos['producto_activo'])
    ];
}

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
    $datos = _obtenerDatosVarianteYProducto($mysqli, $id_variante);
    
    $stock_actual = $datos['stock'];
    $variante_activa = $datos['variante_activa'];
    $producto_activo = $datos['producto_activo'];
    
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
 * Valida stock suficiente antes de venta
 *
 * FIX: Acepta $id_pedido para excluir su propia reserva del cálculo (evita bug de duplicación).
 *
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @param int $cantidad Cantidad a vender
 * @param int|null $id_pedido Pedido cuya reserva se excluye del cálculo
 * @throws Exception Si no hay stock suficiente o la variante/producto están inactivos
 */
function validarStockDisponibleVenta($mysqli, $id_variante, $cantidad, $id_pedido = null) {
    $datos = _obtenerDatosVarianteYProducto($mysqli, $id_variante);

    $stock_actual = $datos['stock'];
    $variante_activa = $datos['variante_activa'];
    $producto_activo = $datos['producto_activo'];

    if ($variante_activa === 0) {
        throw new Exception('No se puede vender una variante inactiva');
    }

    if ($producto_activo === 0) {
        throw new Exception('No se puede vender un producto inactivo');
    }

    // FIX: El stock en BD ya tiene descontadas las reservas (Stock_Variantes.stock es ATP)
    // No restar $stock_reservado nuevamente para evitar doble descuento
    // $stock_reservado = obtenerStockReservado($mysqli, $id_variante, $id_pedido);
    $stock_disponible = $stock_actual; // El stock actual YA ES el disponible

    if ($stock_disponible < $cantidad) {
        throw new Exception("Stock insuficiente para variante #{$id_variante}. Stock disponible: {$stock_disponible}, Cantidad solicitada: {$cantidad}");
    }
}

/**
 * Valida stock disponible de forma unificada (función centralizada)
 * 
 * Esta función centraliza toda la validación de stock, eliminando código duplicado.
 * La lógica de validación es idéntica en ambos modos - solo cambia el contexto de uso.
 * 
 * IMPORTANTE: Esta función NO usa FOR UPDATE. Solo hace SELECT simple.
 * La validación atómica real se hace después en actualizarStockDesdeMovimiento()
 * con UPDATE WHERE para prevenir race conditions sin necesidad de bloquear filas.
 * 
 * Modos de validación:
 * 
 * - 'preliminar': Usar en checkout.php y carrito.php para validación rápida
 *   y mostrar información al usuario. No bloquea filas, solo consulta.
 *   En este modo, valida contra cantidad_total_solicitada (cantidad_actual_carrito + cantidad_solicitada)
 *   para considerar lo que ya está en el carrito.
 *   
 *   Ejemplo de uso en carrito.php:
 *   validarStockDisponible($mysqli, $id_producto, $talla, $color, $cantidad, 'preliminar', $cantidad_actual_carrito);
 * 
 * - 'definitivo': Usar en procesar-pedido.php antes de crear el pedido.
 *   Misma validación, pero en contexto de transacción antes de crear pedido.
 *   En este modo, valida contra cantidad_solicitada directamente (ya es la cantidad total del item).
 *   
 *   Ejemplo de uso en procesar-pedido.php:
 *   validarStockDisponible($mysqli, $id_producto, $talla, $color, $cantidad, 'definitivo', 0);
 * 
 * Diferencia clave: La lógica de validación es la misma, solo cambia qué cantidad se valida:
 * - 'preliminar': cantidad_actual_carrito + cantidad_solicitada (para agregar al carrito)
 * - 'definitivo': cantidad_solicitada (ya es la cantidad total del item en el carrito)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @param string $talle Talle de la variante
 * @param string $color Color de la variante
 * @param int $cantidad_solicitada Cantidad solicitada
 * @param string $modo Modo de validación: 'preliminar' o 'definitivo' (default: 'preliminar')
 * @param int $cantidad_actual_carrito Cantidad que ya está en el carrito (opcional, default: 0)
 * @return array Array con 'stock_disponible', 'variante_activa', 'producto_activo', 'id_variante', 'precio_actual', 'nombre_producto', 'talle', 'color'
 * @throws Exception Si la variante no existe, está inactiva o no hay stock suficiente
 */
function validarStockDisponible($mysqli, $id_producto, $talle, $color, $cantidad_solicitada, $modo = 'preliminar', $cantidad_actual_carrito = 0, $usar_lock = false) {
    // Liberar reservas expiradas antes de validar stock (solo en modo definitivo para no afectar rendimiento)
    if ($modo === 'definitivo') {
        liberarReservasExpiradas($mysqli);
    }
    
    // Validar parámetros
    $id_producto = intval($id_producto);
    $talle = trim(strval($talle));
    $color = trim(strval($color));
    $cantidad_solicitada = intval($cantidad_solicitada);
    $cantidad_actual_carrito = intval($cantidad_actual_carrito);
    $modo = trim(strval($modo));
    
    // Validar parámetros básicos
    if ($id_producto <= 0 || empty($talle) || empty($color)) {
        throw new Exception(
            'Error de Validación',
            'Parámetros inválidos para validar stock. Asegúrate de que el producto, talle y color sean correctos.',
            'PARAMETROS_INVALIDOS'
        );
    }
    
    // Validar rango de cantidad según diccionario: 1-1000 para Detalle_Pedido
    if ($cantidad_solicitada < 1) {
        throw new Exception(
            'Cantidad Inválida',
            'La cantidad debe ser al menos 1 unidad para este producto.',
            'CANTIDAD_INVALIDA'
        );
    }
    
    if ($cantidad_solicitada > 1000) {
        throw new Exception(
            'Cantidad Excesiva',
            'La cantidad no puede exceder las 1000 unidades por artículo.',
            'CANTIDAD_EXCESIVA'
        );
    }
    
    if ($modo !== 'preliminar' && $modo !== 'definitivo') {
        throw new Exception(
            'Modo de Validación Inválido',
            'El modo de validación debe ser "preliminar" o "definitivo".',
            'MODO_INVALIDO'
        );
    }
    
    // Validación sin FOR UPDATE - La protección contra race conditions se hace
    // en el UPDATE atómico con validación en WHERE (ver actualizarStockDesdeMovimiento)
    // SELECT simple para obtener datos y validar disponibilidad
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

    if ($modo === 'definitivo' && $usar_lock) {
        $sql .= " FOR UPDATE";
    }
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception(
            'Error Interno',
            'No se pudo preparar la consulta de validación de stock. Inténtalo de nuevo más tarde.',
            'ERROR_SQL_PREPARE'
        );
    }
    
    $stmt->bind_param('iss', $id_producto, $talle, $color);
    if (!$stmt->execute()) {
        $error_msg = $stmt->error;
        $stmt->close();
        throw new Exception(
            'Error Interno',
            'No se pudo ejecutar la consulta de validación de stock. Por favor, intenta de nuevo.',
            'ERROR_SQL_EXECUTE',
            ['Revisa tu conexión a internet y vuelve a intentarlo.']
        );
    }
    
    $result = $stmt->get_result();
    $datos = $result->fetch_assoc();
    $stmt->close();
    
    if (!$datos || empty($datos['id_variante'])) {
        throw new Exception(
            'Producto No Disponible',
            'El producto seleccionado no está disponible con esa combinación de talle y color. Por favor, elige otra opción.',
            'VARIANTE_NO_ENCONTRADA',
            ['Verifica el talle y color seleccionados.', 'Intenta con una combinación diferente.']
        );
    }
    
    $stock_disponible = intval($datos['stock']);
    $variante_activa = intval($datos['variante_activa']);
    $producto_activo = intval($datos['producto_activo']);
    
    // Validar que variante y producto estén activos
    if ($variante_activa === 0) {
        throw new Exception(
            'Variante Inactiva',
            'La variante seleccionada de este producto está inactiva y no puede ser comprada.',
            'VARIANTE_INACTIVA',
            ['Contacta a soporte si crees que es un error.']
        );
    }
    
    if ($producto_activo === 0) {
        throw new Exception(
            'Producto Inactivo',
            'El producto seleccionado está inactivo y no puede ser comprado en este momento.',
            'PRODUCTO_INACTIVO',
            ['Contacta a soporte si crees que es un error.']
        );
    }
    
    // Considerar reservas activas (pedidos pendientes < 24 horas)
    // FIX: El stock en BD ya tiene descontadas las reservas (Stock_Variantes.stock es ATP)
    // No restar $stock_reservado nuevamente para evitar doble descuento
    // $id_variante = intval($datos['id_variante']);
    // $stock_reservado = obtenerStockReservado($mysqli, $id_variante);
    // $stock_disponible = $stock_disponible - $stock_reservado;
    
    // Asegurar que no sea negativo
    if ($stock_disponible < 0) {
        $stock_disponible = 0;
    }
    
    // Calcular cantidad total que se tendría en el carrito (si aplica)
    $cantidad_total_solicitada = $cantidad_actual_carrito + $cantidad_solicitada;
    
    // Validar stock disponible
    // En modo 'definitivo', validar contra cantidad_solicitada directamente
    // En modo 'preliminar', validar contra cantidad_total_solicitada (incluye lo que ya está en carrito)
    $cantidad_a_validar = ($modo === 'definitivo') ? $cantidad_solicitada : $cantidad_total_solicitada;
    
    if ($stock_disponible < $cantidad_a_validar) {
        if ($modo === 'definitivo') {
            throw new Exception("Stock insuficiente para procesar tu pedido. Disponible: {$stock_disponible} unidades. Intento de venta: {$cantidad_solicitada}.");
        } else {
            // Modo preliminar: mensaje más detallado para UX

            // Caso 1: Stock agotado (0 disponible)
            if ($stock_disponible === 0) {
                 throw new Exception('Este producto se ha agotado. No hay unidades disponibles en este momento.');
            }

            $cantidad_disponible_para_agregar = $stock_disponible - $cantidad_actual_carrito;

            // Caso 2: Ya tiene todo el stock disponible en su carrito
            if ($cantidad_disponible_para_agregar <= 0) {
                throw new Exception("Ya tienes {$cantidad_actual_carrito} unidades de este producto en tu carrito, que es el stock máximo disponible ({$stock_disponible} unidades).");
            } else {
                // Caso 3: Puede agregar más, pero no la cantidad solicitada
                throw new Exception("Solo quedan {$stock_disponible} unidades de este producto. Puedes agregar hasta {$cantidad_disponible_para_agregar} unidades más a tu carrito.");
            }
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

/**
 * Obtiene la cantidad de stock reservado para una variante
 *
 * Calcula reservas activas (pedidos < 24h sin pago aprobado).
 *
 * FIX: Permite excluir la reserva del pedido que se está aprobando para evitar
 * contarla como stock ocupado (bug de duplicación).
 *
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @param int|null $id_pedido_excluir Pedido cuya reserva se excluye del cálculo
 * @return int Cantidad de stock reservado
 */
function obtenerStockReservado($mysqli, $id_variante, $id_pedido_excluir = null) {
    $id_variante = intval($id_variante);

    if ($id_variante <= 0) {
        return 0;
    }

    // ✅ NIVEL 5: Calcular fecha límite en PHP (sin DATE_SUB)
    $fecha_limite = date('Y-m-d H:i:s', strtotime('-24 hours'));

    // ✅ NIVEL 5: Query simple sin DATE_SUB
    $sql = "
        SELECT ms.cantidad, ms.id_pedido
        FROM Movimientos_Stock ms
        INNER JOIN Pedidos p ON ms.id_pedido = p.id_pedido
        WHERE ms.id_variante = ?
          AND ms.tipo_movimiento = 'venta'
          AND ms.observaciones LIKE 'RESERVA: %'
          AND p.estado_pedido IN ('pendiente', 'pendiente_validado_stock')
          AND p.fecha_pedido > ?
    ";

    if ($id_pedido_excluir !== null) {
        $sql .= " AND p.id_pedido != ?";
    }

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR obtenerStockReservado - prepare falló: " . $mysqli->error);
        return 0;
    }

    if ($id_pedido_excluir !== null) {
        $id_pedido_excluir_int = intval($id_pedido_excluir);
        $stmt->bind_param('isi', $id_variante, $fecha_limite, $id_pedido_excluir_int);
        error_log("obtenerStockReservado: Variante #{$id_variante} - Excluyendo reserva del pedido #{$id_pedido_excluir_int}");
    } else {
        $stmt->bind_param('is', $id_variante, $fecha_limite);
    }

    if (!$stmt->execute()) {
        error_log("ERROR obtenerStockReservado - execute falló: " . $stmt->error);
        $stmt->close();
        return 0;
    }

    $result = $stmt->get_result();

    // Calcular suma en PHP y filtrar por estado de pago (más simple que LEFT JOIN complejo)
    $stock_reservado = 0;
    $pedidos_procesados = [];

    while ($row = $result->fetch_assoc()) {
        $id_ped = intval($row['id_pedido']);

        // Evitar contar mismo pedido múltiples veces
        if (in_array($id_ped, $pedidos_procesados)) {
            continue;
        }

        // Verificar estado de pago del pedido en query separada simple
        $sql_pago = "SELECT estado_pago FROM Pagos WHERE id_pedido = ? LIMIT 1";
        $stmt_pago = $mysqli->prepare($sql_pago);

        if ($stmt_pago) {
            $stmt_pago->bind_param('i', $id_ped);
            $stmt_pago->execute();
            $res_pago = $stmt_pago->get_result();
            $pago = $res_pago->fetch_assoc();
            $stmt_pago->close();

            // Contar reserva solo si no tiene pago O pago está pendiente
            if (!$pago || in_array($pago['estado_pago'], ['pendiente', 'pendiente_aprobacion'])) {
                $stock_reservado += intval($row['cantidad']);
            }
        } else {
            // Si no hay pago, contar la reserva
            $stock_reservado += intval($row['cantidad']);
        }

        $pedidos_procesados[] = $id_ped;
    }

    $stmt->close();

    if ($id_pedido_excluir !== null) {
        error_log("obtenerStockReservado: Variante #{$id_variante} - Stock reservado (excluyendo pedido #{$id_pedido_excluir}): {$stock_reservado}");
    }

    return $stock_reservado;
}

// FUNCIÓN ELIMINADA: validarStockDisponibleCarrito()
// Esta función estaba deprecada. Usar validarStockDisponible() en su lugar.
// Para validaciones de carrito, usar: validarStockDisponible($mysqli, $id_producto, $talle, $color, $cantidad, 'preliminar', $cantidad_actual_carrito)

// ============================================================================
// SECCIÓN 2: ACTUALIZACIÓN DE STOCK
// ============================================================================

/**
 * Actualiza el stock de una variante según el tipo de movimiento
 *
 * @internal FUNCIÓN INTERNA: Usada exclusivamente por registrarMovimientoStock() para
 * aplicar el cambio de stock después de registrar el movimiento. No debe
 * llamarse directamente desde fuera de este archivo.
 *
 * Reemplaza la lógica del trigger trg_actualizar_stock_insert
 *
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_variante ID de la variante
 * @param string $tipo_movimiento Tipo de movimiento ('venta', 'ingreso', 'ajuste')
 * @param int $cantidad Cantidad del movimiento (positiva para venta/ingreso, puede ser negativa para ajuste)
 * @param bool $es_reserva Si true, omite validación de affected_rows (para movimientos de reserva)
 * @throws Exception Si hay error al actualizar o tipo de movimiento inválido
 */
function actualizarStockDesdeMovimiento($mysqli, $id_variante, $tipo_movimiento, $cantidad, $es_reserva = false) {
    // Retorna true si la actualización fue exitosa, false si falló (solo para reservas)
    // FIX: Usar actualizaciones atómicas con validación en WHERE para prevenir race conditions
    // La validación en WHERE (stock >= cantidad) asegura atomicidad sin necesidad de FOR UPDATE
    // Se elimina la excepción para RESERVAS, ahora todas las actualizaciones verifican affected_rows.


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
        if ($es_reserva) {
            // Para reservas, affected_rows = 0 significa que no había suficiente stock
            // Retornamos false en lugar de lanzar excepción
            return false;
        } else {
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
                    throw new Exception(
                        "Error al descontar stock: No se pudo descontar stock para variante #{$id_variante}. Stock disponible: {$stock_actual}, Cantidad solicitada: {$cantidad}. Es posible que otro usuario haya comprado el producto."
                    );
                } elseif ($tipo_movimiento === 'ajuste' && $cantidad < 0) {
                    $resultado_esperado = $stock_actual + $cantidad;
                    throw new Exception(
                        'Error al ajustar stock',
                        "El ajuste causaría stock negativo para variante #{$id_variante}. Stock actual: {$stock_actual}, Ajuste: {$cantidad}, Resultado esperado: {$resultado_esperado}.",
                        'AJUSTE_STOCK_NEGATIVO'
                    );
                } else {
                    throw new Exception("No se pudo actualizar stock para variante #{$id_variante}. La variante puede no existir o no se cumplió la condición del WHERE.");
                }
            } else {
                throw new Exception("No se pudo actualizar stock para variante #{$id_variante}. Error interno al obtener stock.");
            }
        }
    }
    
    // Validar que el stock resultante no exceda el límite máximo (10000)
    $sql_stock_final = "SELECT stock FROM Stock_Variantes WHERE id_variante = ?";
    $stmt_stock_final = $mysqli->prepare($sql_stock_final);
    if ($stmt_stock_final) {
        $stmt_stock_final->bind_param('i', $id_variante);
        $stmt_stock_final->execute();
        $result_stock_final = $stmt_stock_final->get_result();
        $datos_stock_final = $result_stock_final->fetch_assoc();
        $stmt_stock_final->close();
        
        if ($datos_stock_final) {
            $stock_final = intval($datos_stock_final['stock']);
            validarRangoStock($stock_final);
        }
    }

    return true; // Actualización exitosa
}

/**
 * Valida que el stock esté dentro del rango permitido (0-10000)
 * 
 * @internal FUNCIÓN INTERNA: Usada internamente para validaciones de rango de stock.
 * 
 * Valida que el valor de stock esté entre 0 y 10000 según las reglas de negocio.
 * Esta validación reemplaza el CHECK constraint de MySQL para mayor portabilidad.
 * 
 * @param int $stock Valor de stock a validar
 * @throws Exception Si el stock está fuera del rango permitido (0-10000)
 */
function validarRangoStock($stock) {
    $stock = intval($stock);
    if ($stock < 0) {
        throw new Exception("El stock no puede ser negativo. Valor recibido: {$stock}");
    }
    if ($stock > 10000) {
        throw new Exception("El stock no puede exceder 10000 unidades. Valor recibido: {$stock}");
    }
}

/**
 * Valida que la cantidad de un movimiento esté dentro del rango permitido (-10000 a 10000)
 * 
 * @internal FUNCIÓN INTERNA: Usada internamente para validaciones de rango de cantidad de movimientos.
 * 
 * Valida que la cantidad de un movimiento de stock esté entre -10000 y 10000 según las reglas de negocio.
 * Esta validación reemplaza el CHECK constraint de MySQL para mayor portabilidad.
 * 
 * @param int $cantidad Cantidad del movimiento a validar
 * @throws Exception Si la cantidad está fuera del rango permitido (-10000 a 10000)
 */
function validarRangoCantidadMovimiento($cantidad) {
    $cantidad = intval($cantidad);
    if ($cantidad < -10000 || $cantidad > 10000) {
        throw new Exception("La cantidad del movimiento debe estar entre -10000 y 10000. Valor recibido: {$cantidad}");
    }
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
    
    // Validar rango de cantidad del movimiento (-10000 a 10000)
    validarRangoCantidadMovimiento($cantidad);
    
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
        if ($tipo_movimiento === 'ajuste') {
            validarAjusteStock($mysqli, $id_variante, $cantidad);
        }

        // FIX: Pasar $id_pedido para excluir reserva propia del cálculo
        // IMPORTANTE: NO validar si es una RESERVA (observaciones empieza con "RESERVA:")
        // Razón: Las reservas ya fueron validadas en procesarItemCarrito() dentro de la misma transacción.
        // Re-validar aquí causaba ERRORES FALSOS POSITIVOS porque:
        //   1. obtenerStockReservado() suma reservas de OTROS pedidos creados simultáneamente
        //   2. El stock disponible aparece como 0 aunque la validación previa pasó correctamente
        //   3. La transacción garantiza atomicidad entre validación y reserva
        // Ejemplo de error previo: "Stock disponible: 0, Cantidad solicitada: 42" al reservar un pedido
        // que segundos antes pasó validación con 42 unidades disponibles.
        // NOTA: strpos() === 0 es correcto (retorna 0 cuando encuentra al inicio, no false)
        $es_reserva = ($observaciones !== null && strpos($observaciones, 'RESERVA:') === 0);


        if ($tipo_movimiento === 'venta' && !$es_reserva) {
            error_log("registrarMovimientoStock: Validando stock para venta NO-RESERVA. Variante #{$id_variante}, Cantidad: {$cantidad}");
            validarStockDisponibleVenta($mysqli, $id_variante, $cantidad, $id_pedido);
        }

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
            $error_msg = $mysqli->error ?: 'Error desconocido en stmt->execute()';
            throw new Exception('Error al insertar movimiento de stock para variante #' . $id_variante . ' (tipo: ' . $tipo_movimiento . ', cantidad: ' . $cantidad . '): ' . $error_msg);
        }

        // Pasar información de reserva a actualizarStockDesdeMovimiento
        $stock_actualizado = actualizarStockDesdeMovimiento($mysqli, $id_variante, $tipo_movimiento, $cantidad, $es_reserva);

        // Para reservas, si no se pudo actualizar el stock (affected_rows = 0),
        // eliminamos el movimiento que acabamos de insertar
        if ($es_reserva && !$stock_actualizado) {
            // Obtener el ID del movimiento que acabamos de insertar (último insertado por esta conexión)
            $sql_delete = "DELETE FROM Movimientos_Stock WHERE id_variante = ? AND tipo_movimiento = ? AND cantidad = ? AND id_pedido = ? ORDER BY id_movimiento DESC LIMIT 1";
            $stmt_delete = $mysqli->prepare($sql_delete);
            if ($stmt_delete) {
                $stmt_delete->bind_param('isii', $id_variante, $tipo_movimiento, $cantidad, $id_pedido);
                $stmt_delete->execute();
                $stmt_delete->close();
            }
            // No lanzamos error, simplemente la reserva no se pudo completar
            return false;
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
    // Verificar ventas reales (no reservas)
    // Las reservas tienen observaciones "RESERVA: " y no se cuentan como ventas
    $sql = "
        SELECT COUNT(*) as cantidad_ventas
        FROM Movimientos_Stock
        WHERE id_pedido = ?
          AND tipo_movimiento = 'venta'
          AND (observaciones IS NULL OR observaciones NOT LIKE 'RESERVA: %')
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

// Consolidated helper: load shared Detalle_Pedido helper
require_once __DIR__ . '/detalle_pedido_queries.php';

/**
 * Verifica la coherencia del stock después de descontar (sanity check)
 * 
 * @internal FUNCIÓN INTERNA: Sanity check solo en modo debug, usado internamente por descontarStockPedido().
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
 * Reserva stock al crear un pedido
 * 
 * IMPORTANTE: Esta función DESCUENTA el stock físicamente (no es una reserva virtual).
 * Crea movimientos de tipo 'venta' con observaciones "RESERVA: Pedido #X" para cada producto.
 * El stock se descuenta inmediatamente para prevenir overselling.
 * 
 * Al aprobar el pago, las reservas se convierten en ventas (solo cambia observaciones, NO descuenta stock nuevamente).
 * Si el pedido se cancela, el stock se restaura mediante movimientos tipo 'ingreso'.
 * 
 * DIFERENCIAS CON descontarStockPedido():
 * - reservarStockPedido(): Descuenta stock físicamente al crear pedido (movimientos con observaciones "RESERVA:")
 * - descontarStockPedido(): Convierte reservas en ventas (solo actualiza observaciones) o crea ventas directas si no hay reservas
 * 
 * IDEMPOTENCIA: Esta función es idempotente. Si se llama múltiples veces para el mismo pedido,
 * solo reservará stock la primera vez. Llamadas subsecuentes retornarán true sin hacer nada.
 * 
 * VALIDACIÓN DE STOCK:
 * NO se re-valida stock aquí porque:
 * 1. Ya se validó en procesarItemCarrito() en modo 'definitivo' dentro de la misma transacción
 * 2. La transacción garantiza atomicidad (nadie puede cambiar stock entre validación y reserva)
 * 3. La re-validación causaba falsos positivos al contar reservas de otros pedidos creados simultáneamente
 * 4. registrarMovimientoStock() detecta observaciones "RESERVA:" y omite la validación para evitar double-check
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @param int $id_usuario ID del usuario del pedido (opcional)
 * @param bool $en_transaccion Si true, no inicia/commit transacción (ya está dentro de una)
 * @return bool True si se reservó correctamente o ya estaba reservado (idempotente)
 */
function reservarStockPedido($mysqli, $id_pedido, $id_usuario = null, $en_transaccion = false) {
    // Validar que el pedido existe
    $sql_verificar_pedido = "SELECT id_pedido, estado_pedido FROM Pedidos WHERE id_pedido = ?";
    $stmt_verificar_pedido = $mysqli->prepare($sql_verificar_pedido);
    if (!$stmt_verificar_pedido) {
        if (!$en_transaccion) {
            return false;
        }
        throw new Exception('Error al preparar verificación de pedido: ' . $mysqli->error);
    }
    $stmt_verificar_pedido->bind_param('i', $id_pedido);
    $stmt_verificar_pedido->execute();
    $result_verificar_pedido = $stmt_verificar_pedido->get_result();
    $pedido_data = $result_verificar_pedido->fetch_assoc();
    $stmt_verificar_pedido->close();
    
    if (!$pedido_data) {
        if (!$en_transaccion) {
            return false;
        }
        throw new Exception('El pedido no existe. ID pedido: ' . $id_pedido);
    }
    
    // Guardrail de idempotencia: verificar si ya hay reservas para este pedido
    $reservas_previas = verificarReservasPreviasPedido($mysqli, $id_pedido);
    if ($reservas_previas > 0) {
        // Ya se reservó stock antes para este pedido, no hacer nada (idempotente)
        error_log("reservarStockPedido: Pedido #{$id_pedido} ya tiene {$reservas_previas} reservas registradas. Operación idempotente.");
        return true;
    }
    
    // Obtener detalles del pedido (helper centralizado)
    $detalles = obtenerDetallesPedido($mysqli, $id_pedido);
    
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
            
            // Registrar movimiento de venta con observaciones que indican que es una reserva
            // El stock se descuenta físicamente, pero se marca como reservado
            $observaciones = "RESERVA: Pedido #{$id_pedido}";
            try {
                registrarMovimientoStock($mysqli, $id_variante, 'venta', $cantidad, $id_usuario, $id_pedido, $observaciones, true);
            } catch (Exception $e) {
                // Re-lanzar con contexto adicional del pedido
                throw new Exception("Error al reservar stock del pedido #{$id_pedido} - Variante #{$id_variante} (cantidad: {$cantidad}): " . $e->getMessage(), 0, $e);
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
        error_log("Error al reservar stock del pedido #{$id_pedido}: " . $e->getMessage());
        throw $e; // Re-lanzar excepción para que la transacción externa la maneje
    }
}

/**
 * Verifica si ya hay reservas previas para un pedido
 * 
 * @internal FUNCIÓN INTERNA: Helper interno usado por reservarStockPedido() y revertirStockPedido().
 * 
 * Las reservas se identifican por movimientos tipo 'venta' con observaciones que empiezan con "RESERVA: "
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @return int Cantidad de reservas encontradas
 */
function verificarReservasPreviasPedido($mysqli, $id_pedido) {
    $sql = "
        SELECT COUNT(*) as cantidad_reservas
        FROM Movimientos_Stock
        WHERE id_pedido = ?
          AND tipo_movimiento = 'venta'
          AND observaciones LIKE 'RESERVA: %'
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("Error al preparar verificación de reservas previas para pedido #{$id_pedido}: " . $mysqli->error);
        return 0;
    }

    $stmt->bind_param('i', $id_pedido);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $cantidad = intval($row['cantidad_reservas'] ?? 0);

    return $cantidad;
}

/**
 * FIX: Verifica si un pedido tuvo reservas (activas o liberadas)
 *
 * Detecta si hubo movimientos de reserva, incluso si fueron liberados.
 * Previene el doble descuento de stock cuando un pedido se paga después de 24h.
 *
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @return array ['reservas_activas' => int, 'reservas_liberadas' => int, 'tuvo_reservas' => bool]
 */
function verificarSiPedidoTuvoReservas($mysqli, $id_pedido) {
    // Buscar reservas activas (tipo 'venta' con 'RESERVA:')
    $sql_activas = "
        SELECT COUNT(*) as cantidad
        FROM Movimientos_Stock
        WHERE id_pedido = ?
          AND tipo_movimiento = 'venta'
          AND observaciones LIKE 'RESERVA: %'
    ";

    $stmt_activas = $mysqli->prepare($sql_activas);
    if (!$stmt_activas) {
        error_log("Error verificarSiPedidoTuvoReservas - activas: " . $mysqli->error);
        return ['reservas_activas' => 0, 'reservas_liberadas' => 0, 'tuvo_reservas' => false];
    }

    $stmt_activas->bind_param('i', $id_pedido);
    $stmt_activas->execute();
    $result_activas = $stmt_activas->get_result();
    $row_activas = $result_activas->fetch_assoc();
    $stmt_activas->close();

    $reservas_activas = intval($row_activas['cantidad'] ?? 0);

    // Buscar reservas liberadas (tipo 'ingreso' con 'Liberación de reserva')
    $sql_liberadas = "
        SELECT COUNT(*) as cantidad
        FROM Movimientos_Stock
        WHERE id_pedido = ?
          AND tipo_movimiento = 'ingreso'
          AND observaciones LIKE 'Liberación de reserva%'
    ";

    $stmt_liberadas = $mysqli->prepare($sql_liberadas);
    if (!$stmt_liberadas) {
        error_log("Error verificarSiPedidoTuvoReservas - liberadas: " . $mysqli->error);
        return ['reservas_activas' => $reservas_activas, 'reservas_liberadas' => 0, 'tuvo_reservas' => ($reservas_activas > 0)];
    }

    $stmt_liberadas->bind_param('i', $id_pedido);
    $stmt_liberadas->execute();
    $result_liberadas = $stmt_liberadas->get_result();
    $row_liberadas = $result_liberadas->fetch_assoc();
    $stmt_liberadas->close();

    $reservas_liberadas = intval($row_liberadas['cantidad'] ?? 0);

    return [
        'reservas_activas' => $reservas_activas,
        'reservas_liberadas' => $reservas_liberadas,
        'tuvo_reservas' => ($reservas_activas > 0 || $reservas_liberadas > 0)
    ];
}

/**
 * Libera reservas expiradas (más de 24 horas sin aprobación de pago)
 * 
 * @internal FUNCIÓN INTERNA: Invocada indirectamente desde validarStockDisponible() en modo definitivo,
 * o puede llamarse mediante cron para mantenimiento periódico.
 * 
 * Crea movimientos tipo 'ingreso' para restaurar el stock reservado.
 * 
 * Esta función debe llamarse periódicamente (ej: en cada validación de stock o mediante cron)
 * para liberar automáticamente reservas que expiraron
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array con 'total_liberadas' y 'pedidos_liberados'
 */
function liberarReservasExpiradas($mysqli) {
    $resultado = [
        'total_liberadas' => 0,
        'pedidos_liberados' => []
    ];
    
    // ✅ NIVEL 5: Calcular fecha límite en PHP (sin DATE_SUB)
    $fecha_limite = date('Y-m-d H:i:s', strtotime('-24 hours'));

    // ✅ NIVEL 5: Query simple sin DATE_SUB
    $sql = "
        SELECT DISTINCT p.id_pedido, p.fecha_pedido, p.id_usuario
        FROM Pedidos p
        INNER JOIN Movimientos_Stock ms ON p.id_pedido = ms.id_pedido
        LEFT JOIN Pagos pag ON p.id_pedido = pag.id_pedido
        WHERE p.estado_pedido IN ('pendiente', 'pendiente_validado_stock')
          AND ms.tipo_movimiento = 'venta'
          AND ms.observaciones LIKE 'RESERVA: %'
          AND p.fecha_pedido <= ?
          AND (pag.id_pago IS NULL OR pag.estado_pago NOT IN ('aprobado'))
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("Error al preparar consulta de reservas expiradas: " . $mysqli->error);
        return $resultado;
    }

    $stmt->bind_param('s', $fecha_limite);
    $stmt->execute();
    $result = $stmt->get_result();
    $pedidos_expirados = [];
    while ($row = $result->fetch_assoc()) {
        $pedidos_expirados[] = $row;
    }
    $stmt->close();
    
    if (empty($pedidos_expirados)) {
        return $resultado;
    }
    
    // Procesar cada pedido con reservas expiradas
    foreach ($pedidos_expirados as $pedido) {
        $id_pedido = intval($pedido['id_pedido']);
        $id_usuario = intval($pedido['id_usuario']);
        
        $mysqli->begin_transaction();
        
        try {
            // Obtener detalles del pedido para revertir reservas (helper centralizado)
            $detalles_reserva = obtenerDetallesPedido($mysqli, $id_pedido);
            
            // Crear movimientos tipo 'ingreso' para revertir cada reserva expirada
            foreach ($detalles_reserva as $detalle) {
                $id_variante = intval($detalle['id_variante']);
                $cantidad = intval($detalle['cantidad']);
                
                $observaciones = "Liberación automática reserva expirada - Pedido #{$id_pedido}";
                try {
                    registrarMovimientoStock($mysqli, $id_variante, 'ingreso', $cantidad, $id_usuario, $id_pedido, $observaciones, true);
                } catch (Exception $e) {
                    throw new Exception("Error al liberar reserva expirada del pedido #{$id_pedido} - Variante #{$id_variante}: " . $e->getMessage(), 0, $e);
                }
            }
            
            $mysqli->commit();
            
            $resultado['total_liberadas'] += count($detalles_reserva);
            $resultado['pedidos_liberados'][] = $id_pedido;
            
            error_log("Reservas expiradas liberadas para pedido #{$id_pedido} (más de 24 horas sin aprobación)");
            
        } catch (Exception $e) {
            $mysqli->rollback();
            error_log("Error al liberar reservas expiradas del pedido #{$id_pedido}: " . $e->getMessage());
        }
    }
    
    return $resultado;
}

/**
 * Descuenta stock cuando se aprueba un pago
 * Si hay reservas previas, las convierte en ventas. Si no hay reservas, crea ventas nuevas.
 * 
 * DIFERENCIAS CON revertirStockPedido():
 * - descontarStockPedido(): Descuenta stock al aprobar pago (convierte reservas o crea ventas)
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
    // Validar que el pedido existe (integridad referencial)
    $sql_verificar_pedido = "SELECT id_pedido, estado_pedido FROM Pedidos WHERE id_pedido = ?";
    $stmt_verificar_pedido = $mysqli->prepare($sql_verificar_pedido);
    if (!$stmt_verificar_pedido) {
        if (!$en_transaccion) {
            return false;
        }
        throw new Exception('Error al preparar verificación de pedido: ' . $mysqli->error);
    }
    $stmt_verificar_pedido->bind_param('i', $id_pedido);
    if (!$stmt_verificar_pedido->execute()) {
        $error_msg = $stmt_verificar_pedido->error;
        $stmt_verificar_pedido->close();
        if (!$en_transaccion) {
            return false;
        }
        throw new Exception('Error al verificar existencia del pedido: ' . $error_msg);
    }
    $result_verificar_pedido = $stmt_verificar_pedido->get_result();
    $pedido_data = $result_verificar_pedido->fetch_assoc();
    $stmt_verificar_pedido->close();
    
    if (!$pedido_data) {
        if (!$en_transaccion) {
            return false;
        }
        throw new Exception('El pedido no existe. ID pedido: ' . $id_pedido);
    }
    
    // Guardrail de idempotencia: verificar si ya se descontó stock para este pedido
    // Verificar ventas (no reservas) - si ya hay ventas, no hacer nada
    $ventas_previas = verificarVentasPreviasPedido($mysqli, $id_pedido);
    if ($ventas_previas > 0) {
        // Ya se descontó stock antes para este pedido, no hacer nada (idempotente)
        error_log("descontarStockPedido: Pedido #{$id_pedido} ya tiene {$ventas_previas} ventas registradas. Operación idempotente.");
        return true;
    }
    
    // Verificar si hay reservas previas para este pedido
    $reservas_previas = verificarReservasPreviasPedido($mysqli, $id_pedido);
    $tiene_reservas = ($reservas_previas > 0);
    
    // Obtener detalles del pedido (helper centralizado)
    $detalles = obtenerDetallesPedido($mysqli, $id_pedido);
    
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
        
        if ($tiene_reservas) {
            // CAMINO A: Convertir reservas existentes en ventas confirmadas
            // Solo actualiza observaciones, NO descuenta stock (ya fue descontado al reservar)
            error_log("descontarStockPedido: Pedido #{$id_pedido} - CONVIRTIENDO {$reservas_previas} reservas en ventas (stock ya descontado)");

            foreach ($detalles as $detalle) {
                $id_variante = intval($detalle['id_variante']);
                $cantidad = intval($detalle['cantidad']);

                $sql_actualizar_reserva = "
                    UPDATE Movimientos_Stock
                    SET observaciones = ?
                    WHERE id_pedido = ?
                      AND id_variante = ?
                      AND tipo_movimiento = 'venta'
                      AND observaciones LIKE 'RESERVA: %'
                ";

                $stmt_actualizar = $mysqli->prepare($sql_actualizar_reserva);
                if (!$stmt_actualizar) {
                    throw new Exception('Error al preparar actualización de reserva a venta: ' . $mysqli->error);
                }

                $observaciones_venta = "Venta confirmada - Pedido #{$id_pedido}";
                $stmt_actualizar->bind_param('sii', $observaciones_venta, $id_pedido, $id_variante);
                if (!$stmt_actualizar->execute()) {
                    $error_msg = $stmt_actualizar->error;
                    $stmt_actualizar->close();
                    throw new Exception("Error al convertir reserva en venta para pedido #{$id_pedido} - Variante #{$id_variante}: " . $error_msg);
                }

                $rows_affected = $stmt_actualizar->affected_rows;
                $stmt_actualizar->close();

                error_log("descontarStockPedido: Pedido #{$id_pedido} - Variante #{$id_variante} - Actualizadas {$rows_affected} reservas → ventas confirmadas");

                // NUEVO: Generar un movimiento de stock con cantidad 0 para trazabilidad de la aprobación.
                // Se registra un movimiento tipo 'venta' con cantidad 0 y una observación específica
                // para indicar que la reserva fue liberada tras la aprobación del pago.
                $observaciones_aprobacion = "Pago aprobado: libera reserva - Pedido #{$id_pedido}";
                registrarMovimientoStock($mysqli, $id_variante, 'venta', 0, $id_usuario, $id_pedido, $observaciones_aprobacion, true);
            }

            error_log("descontarStockPedido: Pedido #{$id_pedido} - ✓ Conversión de reservas completada (sin descuento adicional)");
        } else {
            // CAMINO B: NO hay reservas previas - Crear movimientos de venta nuevos y descontar stock
            // Este camino se ejecuta cuando:
            // - El pedido fue creado sin reservar stock (ej: pedido antiguo, flujo alternativo)
            // - Es la primera vez que se procesa este pedido
            error_log("descontarStockPedido: Pedido #{$id_pedido} - NO hay reservas previas. Descontando stock directamente.");

            foreach ($detalles as $detalle) {
                $id_variante = intval($detalle['id_variante']);
                $cantidad = intval($detalle['cantidad']);

                // IMPORTANTE: $id_pedido excluye su propia reserva del cálculo
                $observaciones = "Venta confirmada - Pedido #{$id_pedido}";
                try {
                    registrarMovimientoStock($mysqli, $id_variante, 'venta', $cantidad, $id_usuario, $id_pedido, $observaciones, true);
                    error_log("descontarStockPedido: Pedido #{$id_pedido} - Variante #{$id_variante} - ✓ Stock descontado ({$cantidad} unidades)");
                } catch (Exception $e) {
                    throw new Exception("Error al descontar stock del pedido #{$id_pedido} - Variante #{$id_variante} (cantidad: {$cantidad}): " . $e->getMessage(), 0, $e);
                }
            }

            error_log("descontarStockPedido: Pedido #{$id_pedido} - ✓ Descuento de stock completado");
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
    
    // Validar que se pueda revertir stock
    // Se permite revertir stock si:
    // 1. El pedido está cancelado (caso normal)
    // 2. El pedido está siendo cancelado desde un estado válido (pendiente, pendiente_validado_stock, preparacion)
    $puede_revertir = false;

    // Caso 1: Pedido ya cancelado
    if ($estado_pedido === 'cancelado') {
        $puede_revertir = true;
    }
    // Caso 2: Pedido siendo cancelado desde estado válido (durante la transacción de cancelación)
    elseif (in_array($estado_pedido, ['pendiente', 'pendiente_validado_stock', 'preparacion', 'en_viaje'])) {
        $puede_revertir = true;
        error_log("revertirStockPedido: Revirtiendo stock durante cancelación del pedido #{$id_pedido} en estado '{$estado_pedido}'");
    }

    if (!$puede_revertir) {
        error_log("No se puede revertir stock del pedido #{$id_pedido}: Estado pedido='{$estado_pedido}', Estado pago='{$estado_pago}'. Estados permitidos: cancelado, pendiente, pendiente_validado_stock, preparacion, en_viaje.");
        return false; // No revertir si el estado no lo permite
    }
    
    // Verificar si hay reservas para este pedido
    $reservas_previas = verificarReservasPreviasPedido($mysqli, $id_pedido);
    $tiene_reservas = ($reservas_previas > 0);
    
    // Iniciar transacción
    $mysqli->begin_transaction();
    
    try {
        if ($tiene_reservas) {
            // Revertir reservas: crear movimientos tipo 'ingreso' para restaurar stock reservado
            // Obtener detalles del pedido para revertir reservas (helper centralizado)
            $detalles_reserva = obtenerDetallesPedido($mysqli, $id_pedido);
            
            // Crear movimientos tipo 'ingreso' para revertir cada reserva
            foreach ($detalles_reserva as $detalle) {
                $id_variante = intval($detalle['id_variante']);
                $cantidad = intval($detalle['cantidad']);
                
                $observaciones = "Reversión reserva - Pedido #{$id_pedido}" . ($motivo ? " - {$motivo}" : "");
                try {
                    registrarMovimientoStock($mysqli, $id_variante, 'ingreso', $cantidad, $id_usuario, $id_pedido, $observaciones, true);
                } catch (Exception $e) {
                    throw new Exception("Error al revertir reserva del pedido #{$id_pedido} - Variante #{$id_variante}: " . $e->getMessage(), 0, $e);
                }
            }
            
            error_log("revertirStockPedido: Pedido #{$id_pedido} - Revertidas {$reservas_previas} reservas.");
            $mysqli->commit();
            return true;
        }
        
        // PASO 2: Calcular neto de movimientos por variante (solo si no hay reservas)
        // ESTRATEGIA SIMPLIFICADA: Dividir en 2 queries simples en lugar de una query compleja con CASE
        // Query 1: Obtener ventas (salidas) por variante
        // Query 2: Obtener ingresos (entradas) por variante
        // Calcular neto en PHP: neto = ventas - entradas
        
        // Query 1: Obtener ventas (salidas) agrupadas por variante
        // Excluir reservas (observaciones "RESERVA: ") - solo contar ventas reales
        $sql_ventas = "
            SELECT id_variante, SUM(cantidad) as total_ventas
            FROM Movimientos_Stock
            WHERE id_pedido = ? 
              AND tipo_movimiento = 'venta'
              AND (observaciones IS NULL OR observaciones NOT LIKE 'RESERVA: %')
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
        
        // Query 2: Obtener ingresos (entradas) agrupadas por variante
        $sql_entradas = "
            SELECT id_variante, SUM(cantidad) as total_entradas
            FROM Movimientos_Stock
            WHERE id_pedido = ? AND tipo_movimiento IN ('ingreso')
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

/**
 * Obtiene todos los colores únicos y activos de Stock_Variantes, normalizados.
 *
 * @param mysqli $mysqli Objeto de conexión a la base de datos.
 * @return array Array de strings con los colores únicos y normalizados.
 */
function obtenerColoresUnicosActivos($mysqli) {
    $sql = "SELECT DISTINCT color FROM Stock_Variantes WHERE activo = 1 AND color IS NOT NULL AND color != ''";
    $result = $mysqli->query($sql);

    $colores = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $colores[] = ucfirst(strtolower(trim($row['color'])));
        }
    }

    // Asegurar unicidad y ordenar alfabéticamente
    $colores = array_unique($colores);
    sort($colores);

    return $colores;
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

    // CORRECCIÓN: Normalizar el talle y color antes de insertar para asegurar consistencia
    // Esto previene problemas donde 'blanco' se guarde como 'negro' por errores de normalización
    $talle = strtoupper(trim($talle));
    $color = ucfirst(strtolower(trim($color)));

    // Validar rango de stock (0-10000)
    validarRangoStock($stock_inicial);
    
    // fecha_creacion tiene DEFAULT CURRENT_TIMESTAMP en la tabla, no es necesario especificarlo explícitamente
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
 * @param bool $solo_activas Si true, solo retorna variantes activas (activo = 1)
 * @return array|null Array con datos de la variante (incluye 'activo') o null si no existe
 */
function obtenerVariantePorId($mysqli, $id_variante, $solo_activas = false) {
    $sql = "SELECT id_producto, talle, color, stock, activo FROM Stock_Variantes WHERE id_variante = ?";
    
    if ($solo_activas) {
        $sql .= " AND activo = 1";
    }
    
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
    
    $stmt->bind_param('sissii', $nombre_producto, $id_categoria, $genero, $talle, $color, $excluir_id_variante);
    if (!$stmt->execute()) {
        error_log("ERROR verificarVarianteExistente - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
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
    // CORRECCIÓN: Normalizar el talle y color antes de actualizar para asegurar consistencia
    $talle = strtoupper(trim($talle));
    $color = ucfirst(strtolower(trim($color)));

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
