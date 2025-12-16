<?php
/**
 * ========================================================================
 * AUTO-LIMPIEZA DE RESERVAS EXPIRADAS - Tienda Seda y Lino
 * ========================================================================
 * Limpia automáticamente las reservas de pedidos que tienen más de 24 horas
 * sin pago aprobado.
 *
 * MOMENTO DE EJECUCIÓN:
 * - Se ejecuta AUTOMÁTICAMENTE cada vez que un usuario intenta procesar un pedido
 * - Ubicación: procesar-pedido.php (ANTES de la transacción principal)
 * - Propósito: Liberar stock "congelado" para mantener inventario actualizado
 *
 * LÓGICA DE EJECUCIÓN:
 * 1. Busca pedidos en estado 'pendiente' con fecha_pedido > 24 horas
 * 2. Verifica que NO tengan pago aprobado
 * 3. Para cada pedido expirado:
 *    - Restaura stock mediante movimientos de 'ingreso'
 *    - Cambia estado del pedido a 'cancelado'
 *    - Envía email de notificación al cliente
 * 4. Retorna estadísticas de limpieza
 *
 * IMPLEMENTACIÓN TEMPORAL:
 * - Sistema funcional que se ejecuta por evento (no cron job)
 * - Asegura que el stock esté siempre actualizado
 * - Previene race conditions por stock reservado indefinidamente
 *
 * MÉTRICAS RETORNADAS:
 * - total_liberadas: Número de unidades de stock liberadas
 * - total_cancelados: Número de pedidos cancelados automáticamente
 * ========================================================================
 */

/**
 * Limpia reservas expiradas (pedidos con más de 24 horas sin pago)
 *
 * MEJORAS IMPLEMENTADAS:
 * - Cancelar pedido automáticamente después de 24 horas
 * - Enviar email de notificación al cliente cuando se cancela el pedido
 *   (El email se envía automáticamente desde actualizarEstadoPedido())
 *
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array con información: ['total_liberadas' => int, 'total_cancelados' => int]
 */
function limpiarReservasExpiradas($mysqli) {
    $total_liberadas = 0;
    $total_cancelados = 0;

    try {
        // Cargar funciones necesarias
        require_once __DIR__ . '/queries/pedido_queries.php';

        // ✅ NIVEL 5: Calcular fecha límite en PHP (sin DATE_SUB)
        $fecha_limite = date('Y-m-d H:i:s', strtotime('-24 hours'));

        // ✅ NIVEL 5: Query simple sin TIMESTAMPDIFF ni DATE_SUB
        $sql = "
            SELECT p.id_pedido, p.id_usuario, p.fecha_pedido
            FROM Pedidos p
            LEFT JOIN Pagos pag ON p.id_pedido = pag.id_pedido
            WHERE p.estado_pedido = 'pendiente'
              AND p.fecha_pedido <= ?
              AND (pag.estado_pago IS NULL OR pag.estado_pago != 'aprobado')
        ";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return ['total_liberadas' => 0, 'total_cancelados' => 0];
        }

        $stmt->bind_param('s', $fecha_limite);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result) {
            $stmt->close();
            return ['total_liberadas' => 0, 'total_cancelados' => 0];
        }

        while ($pedido = $result->fetch_assoc()) {
            $id_pedido = intval($pedido['id_pedido']);
            $id_usuario = intval($pedido['id_usuario']);

            // ✅ NIVEL 5: Calcular horas en PHP (sin TIMESTAMPDIFF)
            $fecha_pedido = strtotime($pedido['fecha_pedido']);
            $ahora = time();
            $horas = ($ahora - $fecha_pedido) / 3600;

            // IF: Pedido tiene más de 24 horas?
            if ($horas >= 24) {
                // Obtener detalles del pedido - FIX: Usar prepared statement
                $stmt_det = $mysqli->prepare("SELECT id_variante, cantidad FROM Detalle_Pedido WHERE id_pedido = ?");
                if (!$stmt_det) {
                    continue;
                }

                $stmt_det->bind_param('i', $id_pedido);
                $stmt_det->execute();
                $result_det = $stmt_det->get_result();

                while ($det = $result_det->fetch_assoc()) {
                    $id_var = intval($det['id_variante']);
                    $cant = intval($det['cantidad']);

                    // Validar valores
                    if ($id_var <= 0 || $cant <= 0) {
                        continue;
                    }

                    // Devolver stock - FIX: Usar prepared statement
                    $horas_redondeadas = round($horas, 1);
                    $obs = "Liberación de reserva expirada - Pedido #{$id_pedido} (" . number_format($horas_redondeadas, 1, ',', '.') . " horas)";

                    $stmt_mov = $mysqli->prepare("
                        INSERT INTO Movimientos_Stock
                        (id_variante, tipo_movimiento, cantidad, id_usuario, id_pedido, observaciones, fecha_movimiento)
                        VALUES (?, 'ingreso', ?, ?, ?, ?, NOW())
                    ");

                    if ($stmt_mov) {
                        $stmt_mov->bind_param('iiiis', $id_var, $cant, $id_usuario, $id_pedido, $obs);
                        $stmt_mov->execute();
                        $stmt_mov->close();
                    }

                    // Actualizar stock - FIX: Usar prepared statement
                    $stmt_upd = $mysqli->prepare("UPDATE Stock_Variantes SET stock = stock + ? WHERE id_variante = ?");
                    if ($stmt_upd) {
                        $stmt_upd->bind_param('ii', $cant, $id_var);
                        $stmt_upd->execute();
                        $stmt_upd->close();
                    }

                    $total_liberadas++;
                }

                $stmt_det->close();

                // Actualizar estado del pedido a 'cancelado' usando función validada
                // actualizarEstadoPedidoConValidaciones() incluye envío automático de email y validaciones
                if (actualizarEstadoPedidoConValidaciones($mysqli, $id_pedido, 'cancelado', null)) {
                    $total_cancelados++;
                    error_log("Auto-limpieza: Pedido #{$id_pedido} cancelado automáticamente (más de 24 horas sin pago aprobado)");
                } else {
                    error_log("Auto-limpieza: ERROR al cancelar pedido #{$id_pedido} - validaciones fallaron");
                }
            }
        }

        $stmt->close();

    } catch (Exception $e) {
        error_log("Error en limpiarReservasExpiradas: " . $e->getMessage());
    }

    return [
        'total_liberadas' => $total_liberadas,
        'total_cancelados' => $total_cancelados
    ];
}

