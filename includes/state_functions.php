<?php
/**
 * ========================================================================
 * FUNCIONES DE VALIDACIÓN DE ESTADOS - Tienda Seda y Lino
 * ========================================================================
 * Funciones simples para validación de estados de pedidos y pagos
 *
 * REEMPLAZA A: state_validator.php (clase estática compleja)
 * VERSIÓN SIMPLIFICADA: Nivel semi-senior
 *
 * Funciones principales:
 * - obtenerTransicionesPagoValidas() - Matriz de transiciones de pago
 * - obtenerTransicionesPedidoValidas() - Matriz de transiciones de pedido
 * - puedeTransicionarPago() - Valida transición de pago
 * - puedeTransicionarPedido() - Valida transición de pedido
 * - validarCombinacionPedidoPago() - Valida combinación pedido-pago
 * - estaEnRecorridoActivo() - Verifica si está en proceso
 * - esEstadoInicial() - Verifica si es estado inicial
 * - esEstadoTerminal() - Verifica si es estado terminal
 * - puedeCancelar() - Verifica si puede cancelarse
 *
 * @package TiendaSedaYLino
 * @version 2.0 (Simplificado)
 * ========================================================================
 */

// Cargar helpers de estado si no están cargados
if (!function_exists('normalizarEstado')) {
    require_once __DIR__ . '/estado_helpers.php';
}

// ========================================================================
// MATRICES DE TRANSICIONES
// ========================================================================

/**
 * Obtiene las transiciones válidas para estados de pago
 *
 * REGLAS DE NEGOCIO:
 * - pendiente: Puede ir a pendiente_aprobacion, aprobado, rechazado o cancelado
 * - pendiente_aprobacion: Solo puede ir a aprobado o rechazado (NO cancelado)
 * - aprobado: Solo puede ir a rechazado (en casos extremos)
 * - rechazado: Estado terminal, no admite cambios
 * - cancelado: Estado terminal, no admite cambios
 *
 * @return array Matriz de transiciones [estado_actual => [estados_permitidos]]
 */
function obtenerTransicionesPagoValidas() {
    return [
        'pendiente' => ['pendiente_aprobacion', 'aprobado', 'rechazado', 'cancelado'],
        'pendiente_aprobacion' => ['aprobado', 'rechazado'], // NO puede cancelarse: ya está en recorrido activo
        'aprobado' => ['rechazado'], // Solo rechazo en casos extremos
        'rechazado' => [], // Estado terminal
        'cancelado' => [] // Estado terminal
    ];
}

/**
 * Obtiene las transiciones válidas para estados de pedido
 *
 * REGLAS DE NEGOCIO:
 * - pendiente: Puede ir a preparacion o cancelado
 * - preparacion: Puede ir a en_viaje, completado o cancelado (solo si pago cancelado/rechazado)
 * - en_viaje: Solo puede ir a completado (NO puede cancelarse)
 * - completado: Estado terminal, no admite cambios
 * - devolucion: Solo puede ir a cancelado (NO IMPLEMENTADO EN MVP)
 * - cancelado: Estado terminal, no admite cambios
 *
 * @return array Matriz de transiciones [estado_actual => [estados_permitidos]]
 */
function obtenerTransicionesPedidoValidas() {
    return [
        'pendiente' => ['preparacion', 'cancelado'],
        'preparacion' => ['en_viaje', 'completado', 'cancelado'], // Cancelado solo si pago cancelado/rechazado
        'en_viaje' => ['completado'], // NO puede cancelarse
        'completado' => [], // Estado terminal
        'devolucion' => ['cancelado'], // NO IMPLEMENTADO EN MVP
        'cancelado' => [] // Estado terminal
    ];
}

/**
 * Obtiene estados en recorrido activo (NO pueden cancelarse normalmente)
 *
 * @param string $tipo Tipo: 'pago' o 'pedido'
 * @return array Array de estados en recorrido activo
 */
function obtenerEstadosRecorridoActivo($tipo) {
    $estados = [
        'pago' => ['pendiente_aprobacion', 'aprobado'],
        'pedido' => ['preparacion', 'en_viaje', 'completado', 'devolucion']
    ];

    return $estados[$tipo] ?? [];
}

/**
 * Obtiene estados iniciales (pueden cancelarse)
 *
 * @param string $tipo Tipo: 'pago' o 'pedido'
 * @return array Array de estados iniciales
 */
function obtenerEstadosIniciales($tipo) {
    $estados = [
        'pago' => ['pendiente'],
        'pedido' => ['pendiente']
    ];

    return $estados[$tipo] ?? [];
}

/**
 * Obtiene estados terminales (no admiten cambios)
 *
 * @param string $tipo Tipo: 'pago' o 'pedido'
 * @return array Array de estados terminales
 */
function obtenerEstadosTerminales($tipo) {
    $estados = [
        'pago' => ['rechazado', 'cancelado'],
        'pedido' => ['completado', 'cancelado']
    ];

    return $estados[$tipo] ?? [];
}

// ========================================================================
// FUNCIONES DE VALIDACIÓN DE TRANSICIONES
// ========================================================================

/**
 * Valida si una transición de estado de pago es válida
 *
 * @param string $estado_actual Estado actual del pago
 * @param string $estado_nuevo Estado nuevo deseado
 * @return bool True si la transición es válida
 */
function puedeTransicionarPago($estado_actual, $estado_nuevo) {
    // Normalizar estados
    $actual = normalizarEstado($estado_actual, '');
    $nuevo = normalizarEstado($estado_nuevo, '');

    // Si son iguales, permitir (no hacer nada)
    if ($actual === $nuevo) {
        return true;
    }

    // Obtener transiciones permitidas
    $transiciones = obtenerTransicionesPagoValidas();

    // Verificar si el estado actual existe
    if (!isset($transiciones[$actual])) {
        return false;
    }

    // Verificar si el nuevo estado está permitido
    return in_array($nuevo, $transiciones[$actual]);
}

/**
 * Valida si una transición de estado de pedido es válida
 *
 * @param string $estado_actual Estado actual del pedido
 * @param string $estado_nuevo Estado nuevo deseado
 * @return bool True si la transición es válida
 */
function puedeTransicionarPedido($estado_actual, $estado_nuevo) {
    // Normalizar estados
    $actual = normalizarEstado($estado_actual, '');
    $nuevo = normalizarEstado($estado_nuevo, '');

    // Si son iguales, permitir (no hacer nada)
    if ($actual === $nuevo) {
        return true;
    }

    // Obtener transiciones permitidas
    $transiciones = obtenerTransicionesPedidoValidas();

    // Verificar si el estado actual existe
    if (!isset($transiciones[$actual])) {
        return false;
    }

    // Verificar si el nuevo estado está permitido
    return in_array($nuevo, $transiciones[$actual]);
}

// ========================================================================
// FUNCIONES DE VERIFICACIÓN DE ESTADO
// ========================================================================

/**
 * Verifica si un estado está en recorrido activo (NO puede cancelarse)
 *
 * @param string $estado Estado a verificar
 * @param string $tipo Tipo: 'pago' o 'pedido'
 * @return bool True si está en recorrido activo
 */
function estaEnRecorridoActivo($estado, $tipo) {
    $estado_norm = normalizarEstado($estado, '');
    $estados_activos = obtenerEstadosRecorridoActivo($tipo);

    return in_array($estado_norm, $estados_activos);
}

/**
 * Verifica si un estado es inicial (puede cancelarse)
 *
 * @param string $estado Estado a verificar
 * @param string $tipo Tipo: 'pago' o 'pedido'
 * @return bool True si es estado inicial
 */
function esEstadoInicial($estado, $tipo) {
    $estado_norm = normalizarEstado($estado, '');
    $estados_iniciales = obtenerEstadosIniciales($tipo);

    return in_array($estado_norm, $estados_iniciales);
}

/**
 * Verifica si un estado es terminal (no admite cambios)
 *
 * @param string $estado Estado a verificar
 * @param string $tipo Tipo: 'pago' o 'pedido'
 * @return bool True si es estado terminal
 */
function esEstadoTerminal($estado, $tipo) {
    $estado_norm = normalizarEstado($estado, '');
    $estados_terminales = obtenerEstadosTerminales($tipo);

    return in_array($estado_norm, $estados_terminales);
}

/**
 * Verifica si un estado puede cancelarse
 *
 * REGLA: Solo estados iniciales pueden cancelarse
 *
 * @param string $estado Estado a verificar
 * @param string $tipo Tipo: 'pago' o 'pedido'
 * @return bool True si puede cancelarse
 */
function puedeCancelar($estado, $tipo) {
    $estado_norm = normalizarEstado($estado, '');

    // Solo estados iniciales pueden cancelarse (no están en recorrido activo)
    return esEstadoInicial($estado_norm, $tipo) && !estaEnRecorridoActivo($estado_norm, $tipo);
}

// ========================================================================
// VALIDACIÓN DE COMBINACIÓN PEDIDO-PAGO
// ========================================================================

/**
 * Valida combinación de estado pedido y pago
 *
 * Detecta las 5 inconsistencias críticas principales:
 * 1. Pedido completado con pago no aprobado
 * 2. Pedido en viaje con pago rechazado/cancelado
 * 3. Pedido cancelado con pago aprobado
 * 4. Pedido en preparacion con pago rechazado/cancelado
 * 5. Pedido avanzado sin pago asociado
 *
 * @param string|null $estado_pedido Estado del pedido
 * @param string|null $estado_pago Estado del pago (null si no hay pago)
 * @return array ['valido' => bool, 'tipo' => string|null, 'mensaje' => string, 'severidad' => string|null]
 */
function validarCombinacionPedidoPago($estado_pedido, $estado_pago) {
    // Normalizar pedido
    $pedido = normalizarEstado($estado_pedido, '');

    // Manejar caso sin pago (null o vacío)
    if ($estado_pago === null || $estado_pago === '') {
        $estados_avanzados = ['preparacion', 'en_viaje', 'completado', 'devolucion'];
        if (in_array($pedido, $estados_avanzados)) {
            return [
                'valido' => false,
                'tipo' => 'warning',
                'mensaje' => "Pedido en '{$pedido}' sin pago asociado (debería tener pago aprobado)",
                'severidad' => 'ADVERTENCIA',
                'accion' => 'Crear y aprobar pago, o cancelar pedido'
            ];
        }

        // Pedidos pendiente o cancelado pueden no tener pago
        return [
            'valido' => true,
            'tipo' => null,
            'mensaje' => '',
            'severidad' => null,
            'accion' => ''
        ];
    }

    // Normalizar pago
    $pago = normalizarEstado($estado_pago, '');

    // INCONSISTENCIA 1: Pedido completado con pago no aprobado
    if ($pedido === 'completado' && $pago !== 'aprobado') {
        return [
            'valido' => false,
            'tipo' => 'danger',
            'mensaje' => "INCONSISTENCIA CRÍTICA: Pedido completado con pago '{$pago}'",
            'severidad' => 'CRÍTICA',
            'accion' => 'Revisar inmediatamente, contactar cliente, verificar stock'
        ];
    }

    // INCONSISTENCIA 2: Pedido en viaje con pago rechazado/cancelado
    if ($pedido === 'en_viaje' && in_array($pago, ['rechazado', 'cancelado'])) {
        return [
            'valido' => false,
            'tipo' => 'danger',
            'mensaje' => "INCONSISTENCIA CRÍTICA: Pedido en viaje con pago '{$pago}'",
            'severidad' => 'CRÍTICA',
            'accion' => 'Revisar inmediatamente, contactar cliente, verificar stock'
        ];
    }

    // INCONSISTENCIA 3: Pedido cancelado con pago aprobado
    if ($pedido === 'cancelado' && $pago === 'aprobado') {
        return [
            'valido' => false,
            'tipo' => 'warning',
            'mensaje' => "Pedido cancelado con pago aprobado (debería restaurar stock)",
            'severidad' => 'ADVERTENCIA',
            'accion' => 'Verificar stock restaurado, cancelar pago si es necesario'
        ];
    }

    // INCONSISTENCIA 4: Pedido en preparacion con pago rechazado/cancelado
    if ($pedido === 'preparacion' && in_array($pago, ['rechazado', 'cancelado'])) {
        return [
            'valido' => false,
            'tipo' => 'warning',
            'mensaje' => "Pedido en preparacion con pago '{$pago}' (debería estar cancelado)",
            'severidad' => 'ADVERTENCIA',
            'accion' => 'Cancelar pedido y restaurar stock'
        ];
    }

    // INCONSISTENCIA 5: Pedido avanzado con pago pendiente
    $estados_avanzados = ['preparacion', 'en_viaje', 'completado', 'devolucion'];
    $estados_pago_pendientes = ['pendiente', 'pendiente_aprobacion'];

    if (in_array($pedido, $estados_avanzados) && in_array($pago, $estados_pago_pendientes)) {
        return [
            'valido' => false,
            'tipo' => 'warning',
            'mensaje' => "Pedido en '{$pedido}' con pago en '{$pago}' (debería estar aprobado)",
            'severidad' => 'ADVERTENCIA',
            'accion' => 'Revisar y aprobar pago si corresponde'
        ];
    }

    // Combinación válida
    return [
        'valido' => true,
        'tipo' => null,
        'mensaje' => '',
        'severidad' => null,
        'accion' => ''
    ];
}

// ========================================================================
// FUNCIONES AUXILIARES (COMPATIBILIDAD CON CÓDIGO EXISTENTE)
// ========================================================================

/**
 * Alias para compatibilidad: estaEnRecorridoActivo
 * Algunos archivos pueden llamarla con este nombre
 */
function estaEnProceso($estado, $tipo) {
    return estaEnRecorridoActivo($estado, $tipo);
}

/**
 * Obtiene todos los estados de un tipo
 *
 * @param string $tipo Tipo: 'pago' o 'pedido'
 * @return array Array con todos los estados
 */
function obtenerTodosLosEstados($tipo) {
    if ($tipo === 'pago') {
        $transiciones = obtenerTransicionesPagoValidas();
    } else {
        $transiciones = obtenerTransicionesPedidoValidas();
    }

    return array_keys($transiciones);
}
