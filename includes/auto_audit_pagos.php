<?php
/**
 * ========================================================================
 * AUTO-AUDIT: Detección de Inconsistencias de Pagos
 * ========================================================================
 * Función que realiza auditoría automática de inconsistencias en pagos
 *
 * Se puede ejecutar:
 * - Manualmente: php test/run_audit_pagos.php
 * - Automáticamente: Vía cron job o scheduled task
 *
 * Inconsistencias detectadas:
 * 1. Pedidos sin pago asociado
 * 2. Pagos múltiples por pedido (solo debe haber uno)
 * 3. Pagos con monto = 0 (inválido)
 * 4. Pagos con estado inválido
 * ========================================================================
 */

/**
 * Ejecuta auditoría de inconsistencias de pagos
 *
 * @param mysqli $mysqli Conexión a la base de datos
 * @param bool $auto_fix Si es true, intenta auto-corregir inconsistencias
 * @return array Array con 'inconsistencias' => [], 'arregladas' => []
 */
function auditarInconsistenciasPagos($mysqli, $auto_fix = false) {
    $inconsistencias = [];
    $arregladas = [];

    // TIPO 1: Pedidos sin pago asociado
    $sql_sin_pago = "SELECT
                        p.id_pedido,
                        p.id_usuario,
                        p.estado_pedido,
                        p.fecha_pedido,
                        p.total
                    FROM Pedidos p
                    LEFT JOIN Pagos pg ON p.id_pedido = pg.id_pedido
                    WHERE pg.id_pago IS NULL
                    GROUP BY p.id_pedido";

    $result = $mysqli->query($sql_sin_pago);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['total'] > 0) {
                $inconsistencias[] = [
                    'tipo' => 'PEDIDO_SIN_PAGO',
                    'severidad' => 'CRÍTICA',
                    'id_pedido' => $row['id_pedido'],
                    'total' => $row['total'],
                    'estado' => $row['estado_pedido'],
                    'mensaje' => "Pedido #{$row['id_pedido']} sin pago (Total: \${$row['total']})"
                ];

                // Auto-fix: Crear pago pendiente
                if ($auto_fix && $row['total'] > 0) {
                    require_once __DIR__ . '/queries/pago_queries.php';
                    $id_pago = crearPago($mysqli, $row['id_pedido'], 1, $row['total'], 'pendiente');
                    if ($id_pago && $id_pago > 0) {
                        $arregladas[] = [
                            'tipo' => 'PEDIDO_SIN_PAGO',
                            'id_pedido' => $row['id_pedido'],
                            'id_pago_creado' => $id_pago,
                            'mensaje' => "Auto-creado pago #{$id_pago} para pedido #{$row['id_pedido']}"
                        ];
                    }
                }
            }
        }
    }

    // TIPO 2: Pagos múltiples por pedido
    $sql_multiples = "SELECT
                        id_pedido,
                        COUNT(*) as total_pagos,
                        GROUP_CONCAT(id_pago) as ids_pagos
                    FROM Pagos
                    GROUP BY id_pedido
                    HAVING COUNT(*) > 1";

    $result = $mysqli->query($sql_multiples);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $inconsistencias[] = [
                'tipo' => 'PAGOS_MULTIPLES',
                'severidad' => 'ALTA',
                'id_pedido' => $row['id_pedido'],
                'total_pagos' => $row['total_pagos'],
                'ids_pagos' => $row['ids_pagos'],
                'mensaje' => "Pedido #{$row['id_pedido']} tiene {$row['total_pagos']} pagos (IDs: {$row['ids_pagos']})"
            ];
        }
    }

    // TIPO 3: Pagos con monto = 0
    $sql_monto_cero = "SELECT
                        id_pago,
                        id_pedido,
                        estado_pago,
                        monto
                    FROM Pagos
                    WHERE monto <= 0";

    $result = $mysqli->query($sql_monto_cero);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $inconsistencias[] = [
                'tipo' => 'PAGO_MONTO_INVALIDO',
                'severidad' => 'MEDIA',
                'id_pago' => $row['id_pago'],
                'id_pedido' => $row['id_pedido'],
                'monto' => $row['monto'],
                'estado' => $row['estado_pago'],
                'mensaje' => "Pago #{$row['id_pago']} con monto inválido: \${$row['monto']}"
            ];
        }
    }

    // TIPO 4: Pagos con estado inválido
    $estados_validos = ['pendiente', 'pendiente_aprobacion', 'aprobado', 'rechazado', 'cancelado'];
    $placeholders = implode("','", $estados_validos);

    $sql_estado_invalido = "SELECT
                            id_pago,
                            id_pedido,
                            estado_pago,
                            monto
                        FROM Pagos
                        WHERE LOWER(TRIM(estado_pago)) NOT IN ('" . implode("','", $estados_validos) . "')";

    $result = $mysqli->query($sql_estado_invalido);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $inconsistencias[] = [
                'tipo' => 'PAGO_ESTADO_INVALIDO',
                'severidad' => 'MEDIA',
                'id_pago' => $row['id_pago'],
                'id_pedido' => $row['id_pedido'],
                'estado' => $row['estado_pago'],
                'mensaje' => "Pago #{$row['id_pago']} con estado inválido: '{$row['estado_pago']}'"
            ];
        }
    }

    return [
        'inconsistencias' => $inconsistencias,
        'arregladas' => $arregladas,
        'total_inconsistencias' => count($inconsistencias),
        'total_arregladas' => count($arregladas)
    ];
}

/**
 * Obtiene resumen de inconsistencias agrupadas por severidad
 *
 * @param array $resultado Resultado de auditarInconsistenciasPagos()
 * @return array Array con resumen por severidad
 */
function obtenerResumenInconsistencias($resultado) {
    $resumen = [
        'CRÍTICA' => 0,
        'ALTA' => 0,
        'MEDIA' => 0
    ];

    foreach ($resultado['inconsistencias'] as $incon) {
        $severidad = $incon['severidad'] ?? 'MEDIA';
        if (isset($resumen[$severidad])) {
            $resumen[$severidad]++;
        }
    }

    return $resumen;
}
?>
