<?php
/**
 * ========================================================================
 * AUTO-LIMPIEZA DE RESERVAS EXPIRADAS - Tienda Seda y Lino
 * ========================================================================
 * Limpia automáticamente las reservas de pedidos que tienen más de 24 horas
 * sin pago aprobado.
 *
 * LÓGICA SIMPLE:
 * 1. Lee fecha_pedido de la tabla Pedidos
 * 2. IF pedido > 24 horas Y sin pago aprobado → Liberar reserva
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
        require_once __DIR__ . '/../queries/pedido_queries.php';
        // FIX: Procesar TODOS los pedidos expirados (sin LIMIT)
        // Anteriormente LIMIT 50 dejaba reservas sin liberar si había > 50 pedidos
        $sql = "
            SELECT p.id_pedido, p.id_usuario,
                   TIMESTAMPDIFF(HOUR, p.fecha_pedido, NOW()) as horas
            FROM Pedidos p
            LEFT JOIN Pagos pag ON p.id_pedido = pag.id_pedido
            WHERE p.estado_pedido = 'pendiente'
              AND p.fecha_pedido <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
              AND (pag.estado_pago IS NULL OR pag.estado_pago != 'aprobado')
        ";

        $result = $mysqli->query($sql);
        if (!$result) {
            return ['total_liberadas' => 0, 'total_cancelados' => 0];
        }

        while ($pedido = $result->fetch_assoc()) {
            $id_pedido = intval($pedido['id_pedido']);
            $id_usuario = intval($pedido['id_usuario']);
            $horas = intval($pedido['horas']);

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
                    $obs = "Liberación de reserva expirada - Pedido #{$id_pedido} ({$horas} horas)";

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

                // Actualizar estado del pedido a 'cancelado'
                // NOTA: actualizarEstadoPedido() envía automáticamente el email de notificación
                if (actualizarEstadoPedido($mysqli, $id_pedido, 'cancelado')) {
                    $total_cancelados++;
                    error_log("Auto-limpieza: Pedido #{$id_pedido} cancelado automáticamente (más de 24 horas sin pago aprobado)");
                } else {
                    error_log("Auto-limpieza: ERROR al cancelar pedido #{$id_pedido}");
                }
            }
        }

    } catch (Exception $e) {
        error_log("Error en limpiarReservasExpiradas: " . $e->getMessage());
    }

    return [
        'total_liberadas' => $total_liberadas,
        'total_cancelados' => $total_cancelados
    ];
}

