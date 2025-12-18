<?php
/**
 * ========================================================================
 * HELPERS DE ESTADOS - Tienda Seda y Lino
 * ========================================================================
 * Funciones centralizadas para manejo de estados de pedidos y pagos
 * 
 * FUNCIONES:
 * - obtenerMapeoEstadosPedido(): Retorna array unificado de estados de pedido
 * - obtenerMapeoEstadosPago(): Retorna array unificado de estados de pago
 * - normalizarEstado(): Normaliza estado (trim + strtolower + default)
 * - obtenerInfoEstadoPedido(): Retorna info del estado desde el mapa con fallback
 * - obtenerInfoEstadoPago(): Retorna info del estado desde el mapa con fallback
 * - detectarInconsistenciasEstado(): Detecta inconsistencias entre estados de pedido y pago
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Obtiene el mapeo completo de estados de pedido
 * 
 * Retorna un array asociativo con todos los estados de pedido válidos
 * y su información de visualización (color y nombre).
 * 
 * @return array Array asociativo con formato ['estado' => ['color' => string, 'nombre' => string]]
 */
function obtenerMapeoEstadosPedido() {
    return [
        'pendiente' => ['color' => 'warning', 'nombre' => 'Pendiente'], // Todo pedido en 'pendiente' ya tiene stock validado
        'preparacion' => ['color' => 'info', 'nombre' => 'Preparación'],
        'en_viaje' => ['color' => 'primary', 'nombre' => 'En Viaje'],
        'completado' => ['color' => 'success', 'nombre' => 'Completado'],
        'cancelado' => ['color' => 'secondary', 'nombre' => 'Cancelado']
    ];
}

/**
 * Obtiene el mapeo completo de estados de pago
 * 
 * Retorna un array asociativo con todos los estados de pago válidos
 * y su información de visualización (color y nombre).
 * 
 * @return array Array asociativo con formato ['estado' => ['color' => string, 'nombre' => string]]
 */
function obtenerMapeoEstadosPago() {
    return [
        'pendiente' => ['color' => 'warning', 'nombre' => 'Pendiente'],
        'pendiente_aprobacion' => ['color' => 'info', 'nombre' => 'Pendiente Aprobación'],
        'aprobado' => ['color' => 'success', 'nombre' => 'Aprobado'],
        'rechazado' => ['color' => 'secondary', 'nombre' => 'Rechazado'],
        'cancelado' => ['color' => 'secondary', 'nombre' => 'Cancelado']
    ];
}

/**
 * Normaliza un estado (trim + strtolower + default si está vacío)
 * 
 * Esta función normaliza un estado aplicando trim, strtolower y
 * asignando un valor por defecto si el estado está vacío después de normalizar.
 * 
 * @param string|null $estado Estado a normalizar
 * @param string $default Estado por defecto si está vacío (default: 'pendiente')
 * @return string Estado normalizado
 */
function normalizarEstado($estado, $default = 'pendiente') {
    if ($estado === null || $estado === '') {
        return $default;
    }

    // Usar mb_strtolower para manejar correctamente caracteres UTF-8 con tildes
    $estado_normalizado = mb_strtolower(trim($estado), 'UTF-8');

    if (empty($estado_normalizado)) {
        return $default;
    }

    return $estado_normalizado;
}

/**
 * Obtiene información de visualización para un estado de pedido
 * 
 * Retorna el array con color y nombre del estado desde el mapeo,
 * o un fallback si el estado no existe en el mapeo.
 * 
 * @param string|null $estado Estado del pedido a buscar
 * @return array Array con formato ['color' => string, 'nombre' => string]
 */
function obtenerInfoEstadoPedido($estado) {
    $estado_normalizado = normalizarEstado($estado);
    $mapeo = obtenerMapeoEstadosPedido();
    
    if (isset($mapeo[$estado_normalizado])) {
        return $mapeo[$estado_normalizado];
    }
    
    // Fallback: generar nombre desde el estado normalizado
    return [
        'color' => 'secondary',
        'nombre' => ucfirst(str_replace('_', ' ', $estado_normalizado))
    ];
}

/**
 * Obtiene información de visualización para un estado de pago
 * 
 * Retorna el array con color y nombre del estado desde el mapeo,
 * o un fallback si el estado no existe en el mapeo.
 * 
 * @param string|null $estado Estado del pago a buscar
 * @return array Array con formato ['color' => string, 'nombre' => string]
 */
function obtenerInfoEstadoPago($estado) {
    $estado_normalizado = normalizarEstado($estado);
    $mapeo = obtenerMapeoEstadosPago();
    
    if (isset($mapeo[$estado_normalizado])) {
        return $mapeo[$estado_normalizado];
    }
    
    // Fallback: generar nombre desde el estado normalizado
    return [
        'color' => 'secondary',
        'nombre' => ucfirst(str_replace('_', ' ', $estado_normalizado))
    ];
}

/**
 * Detecta inconsistencias entre estados de pedido y pago
 * 
 * Retorna información completa sobre inconsistencias detectadas según la matriz
 * completa de 35 combinaciones. Detecta todos los casos críticos y de advertencia
 * identificados en la matriz-estados-pedido-pago-warnings.md
 * 
 * NOTA: Esta función ahora delega a validarCombinacionPedidoPago() para
 * centralizar la lógica de validación, pero mantiene el formato de retorno original
 * para compatibilidad.
 * 
 * CASOS VÁLIDOS (NO muestran warning): 20 combinaciones
 * - pendiente + cualquier estado de pago (5 casos)
 * - preparacion/en_viaje/completado + aprobado (3 casos)
 * - cancelado + pendiente/pendiente_aprobacion/rechazado/cancelado (4 casos)
 * - preparacion + pendiente (1 caso - aunque técnicamente debería tener aprobado, no es crítico)
 * 
 * CASOS CON WARNING: 15 combinaciones
 * - 4 críticos (danger): en_viaje/completado + rechazado/cancelado
 * - 11 advertencias (warning): estados avanzados sin aprobado, estados con pago rechazado, etc.
 * 
 * @param string|null $estado_pedido Estado del pedido
 * @param string|null $estado_pago Estado del pago
 * @param string|null $estado_pedido_anterior Estado anterior del pedido (opcional, para mostrar transición)
 * @param string|null $estado_pago_anterior Estado anterior del pago (opcional, para mostrar transición)
 * @return array Array con:
 *   - 'hay_inconsistencia' => bool True si hay inconsistencia detectada
 *   - 'tipo' => 'danger'|'warning'|null Tipo de inconsistencia (null si no hay)
 *   - 'mensaje' => string Mensaje descriptivo de la inconsistencia
 *   - 'severidad' => 'CRÍTICA'|'ADVERTENCIA'|null Severidad de la inconsistencia
 *   - 'grupo' => string Grupo al que pertenece el warning (para agrupar similares)
 *   - 'accion_sugerida' => string Acción sugerida para resolver la inconsistencia
 */
function detectarInconsistenciasEstado($estado_pedido, $estado_pago, $estado_pedido_anterior = null, $estado_pago_anterior = null) {
    // Normalizar estado del pedido
    $estado_pedido_norm = normalizarEstado($estado_pedido);

    // Pasar null explícitamente si no hay pago, para que validarCombinacionPedidoPago() lo maneje correctamente
    // Si normalizamos null a 'pendiente' aquí, perdemos la distinción entre "sin pago" y "pago pendiente"
    $estado_pago_para_validacion = ($estado_pago === null || $estado_pago === '') ? null : normalizarEstado($estado_pago);

    // Normalizar para uso en mensajes (después de la validación)
    $estado_pago_norm = normalizarEstado($estado_pago);

    $estado_pedido_anterior_norm = $estado_pedido_anterior ? normalizarEstado($estado_pedido_anterior) : null;
    $estado_pago_anterior_norm = $estado_pago_anterior ? normalizarEstado($estado_pago_anterior) : null;

    // Usar validarCombinacionPedidoPago() para validar combinación (pasar null explícitamente)
    $validation = validarCombinacionPedidoPago($estado_pedido_norm, $estado_pago_para_validacion);

    // Si no hay warning (es válido), retornar formato compatible
    if ($validation['valido']) {
        return [
            'hay_inconsistencia' => false,
            'tipo' => null,
            'mensaje' => '',
            'severidad' => null,
            'grupo' => null,
            'accion_sugerida' => ''
        ];
    }
    
    // Hay inconsistencia - procesar información
    // Obtener información de estados para mensaje mejorado con transiciones
    $info_pedido = obtenerInfoEstadoPedido($estado_pedido_norm);
    $info_pago = obtenerInfoEstadoPago($estado_pago_norm);

    // Construir texto de transición si hay estados anteriores Y hubo cambio real
    $transicion_texto = '';
    if ($estado_pedido_anterior_norm || $estado_pago_anterior_norm) {
        $partes_transicion = [];
        // Solo mostrar transición de pedido si hubo cambio
        if ($estado_pedido_anterior_norm && $estado_pedido_anterior_norm !== $estado_pedido_norm) {
            $info_pedido_anterior = obtenerInfoEstadoPedido($estado_pedido_anterior_norm);
            $partes_transicion[] = "Pedido: {$info_pedido_anterior['nombre']} → {$info_pedido['nombre']}";
        }
        // Solo mostrar transición de pago si hubo cambio
        if ($estado_pago_anterior_norm && $estado_pago_anterior_norm !== $estado_pago_norm) {
            $info_pago_anterior = obtenerInfoEstadoPago($estado_pago_anterior_norm);
            $partes_transicion[] = "Pago: {$info_pago_anterior['nombre']} → {$info_pago['nombre']}";
        }
        if (!empty($partes_transicion)) {
            $transicion_texto = ' (' . implode(', ', $partes_transicion) . ')';
        }
    }

    // Mejorar mensaje usando nombres de estados en lugar de valores normalizados
    $mensaje_base = $validation['mensaje'];
    // Reemplazar valores normalizados por nombres legibles en el mensaje
    $mensaje_base = str_replace("'{$estado_pedido_norm}'", "'{$info_pedido['nombre']}'", $mensaje_base);
    $mensaje_base = str_replace("'{$estado_pago_norm}'", "'{$info_pago['nombre']}'", $mensaje_base);
    $mensaje_mejorado = $mensaje_base . $transicion_texto;

    // Determinar grupo según el tipo de warning
    $grupo = null;
    if ($validation['tipo'] === 'danger') {
        $grupo = 'inconsistencias_criticas';
    } elseif (strpos($validation['mensaje'], 'debería tener pago aprobado') !== false) {
        $grupo = 'estados_avanzados_sin_pago_aprobado';
    } elseif (strpos($validation['mensaje'], 'debería estar cancelado') !== false) {
        $grupo = 'estados_con_pago_rechazado';
    } elseif ($estado_pedido_norm === 'cancelado' && $estado_pago_norm === 'aprobado') {
        $grupo = 'cancelado_con_pago_aprobado';
    }

    return [
        'hay_inconsistencia' => true,
        'tipo' => $validation['tipo'],
        'mensaje' => $mensaje_mejorado,
        'severidad' => $validation['severidad'],
        'grupo' => $grupo,
        'accion_sugerida' => $validation['accion']
    ];
}

/**
 * Formatea un mensaje de éxito con transición de estados
 * 
 * Crea un mensaje de éxito que muestra la transición de estado anterior a nuevo,
 * incluyendo información adicional cuando sea relevante (stock, pago, etc.)
 * 
 * @param string $estado_anterior Estado anterior del pedido o pago
 * @param string $estado_actual Estado actual del pedido o pago
 * @param string $tipo Tipo de cambio: 'pedido' o 'pago'
 * @param array $info_adicional Array con información adicional opcional:
 *   - 'stock_descontado' => bool Si el stock fue descontado
 *   - 'stock_restaurado' => bool Si el stock fue restaurado
 *   - 'pago_aprobado' => bool Si el pago fue aprobado
 *   - 'pago_rechazado' => bool Si el pago fue rechazado
 *   - 'mensaje_extra' => string Mensaje adicional personalizado
 * @return string Mensaje formateado
 */
function formatearMensajeExito($estado_anterior, $estado_actual, $tipo = 'pedido', $info_adicional = [], $id_pedido = null) {
    // Normalizar estados para comparación
    $estado_anterior_norm = normalizarEstado($estado_anterior);
    $estado_actual_norm = normalizarEstado($estado_actual);
    
    // Obtener información de estados
    if ($tipo === 'pedido') {
        $info_anterior = obtenerInfoEstadoPedido($estado_anterior);
        $info_actual = obtenerInfoEstadoPedido($estado_actual);
    } else {
        $info_anterior = obtenerInfoEstadoPago($estado_anterior);
        $info_actual = obtenerInfoEstadoPago($estado_actual);
    }
    
    // Construir mensaje base con transición (solo si hubo cambio)
    $hubo_cambio = $estado_anterior_norm !== $estado_actual_norm;
    if ($hubo_cambio) {
        $tipo_capitalizado = ucfirst($tipo); // Pago o Pedido
        $mensaje = "Cambio de estado correcto: {$info_anterior['nombre']} → {$info_actual['nombre']} ({$tipo_capitalizado})";
    } else {
        $mensaje = "Estado actual: {$info_actual['nombre']}";
    }

    // Construir mensaje final con ID del pedido al inicio si es pedido
    $partes_mensaje = [];

    // Agregar ID del pedido al inicio si es pedido
    if ($tipo === 'pedido' && $id_pedido) {
        $partes_mensaje[] = "Pedido #{$id_pedido}";
    }

    // Agregar información adicional
    if (!empty($info_adicional['stock_restaurado']) && $info_adicional['stock_restaurado']) {
        $partes_mensaje[] = 'Stock restaurado automáticamente al inventario';
    }

    if (!empty($info_adicional['pago_aprobado']) && $info_adicional['pago_aprobado']) {
        $partes_mensaje[] = 'Pago aprobado correctamente';
    }

    if (!empty($info_adicional['pago_rechazado']) && $info_adicional['pago_rechazado']) {
        $partes_mensaje[] = 'Pago rechazado';
    }

    if (!empty($info_adicional['mensaje_extra'])) {
        $partes_mensaje[] = $info_adicional['mensaje_extra'];
    }

    // Agregar el mensaje de cambio de estado
    if ($hubo_cambio) {
        $partes_mensaje[] = $mensaje;
    }

    // Combinar con " - " como separador
    return implode(' - ', $partes_mensaje);
    
    return $mensaje;
}

/**
 * Verifica si un pedido está en recorrido activo (no se puede cancelar directamente)
 *
 * Estados de recorrido activo:
 * - preparacion: Pedido está siendo empaquetado
 * - en_viaje: Pedido fue despachado y está en tránsito
 *
 * Estos estados NO permiten cancelación directa porque:
 * - Ya se descontó stock
 * - Ya se aprobó el pago
 * - Ya se incurrieron en costos de logística
 *
 * Para cancelar, se debe usar el flujo de DEVOLUCIÓN.
 *
 * @param string $estado_pedido Estado del pedido (sin normalizar)
 * @return bool true si está en recorrido activo, false en caso contrario
 */
function estaEnRecorridoActivoPedido($estado_pedido) {
    $estado_normalizado = normalizarEstado($estado_pedido);
    $estados_recorrido_activo = ['preparacion', 'en_viaje'];
    return in_array($estado_normalizado, $estados_recorrido_activo);
}

/**
 * Obtiene los estados desde los cuales se puede cancelar directamente un pedido
 *
 * @return array Lista de estados desde los cuales se puede cancelar
 */
function obtenerEstadosCancelablesDirectamente() {
    return ['pendiente', 'pendiente_validado_stock'];
}
/* pendiente_validado_stock >> ESTE ESTADO NO EXISTE, SE DEJÓ COMO EJEMPLO DE CÓMO AGREGAR OTROS ESTADOS SI FUERA NECESARIO */

/**
 * Verifica si un pedido se puede cancelar directamente (sin devolución)
 *
 * @param string $estado_pedido Estado actual del pedido
 * @param string|null $estado_pago Estado actual del pago (opcional)
 * @return array ['puede_cancelar' => bool, 'razon' => string]
 */
function puedeCancelarPedidoDirectamente($estado_pedido, $estado_pago = null) {
    $estado_normalizado = normalizarEstado($estado_pedido);

    // Validar que NO esté en estado terminal
    if (in_array($estado_normalizado, ['cancelado', 'completado'])) {
        return [
            'puede_cancelar' => false,
            'razon' => 'El pedido ya está en estado terminal'
        ];
    }

    // Validar que NO esté en recorrido activo
    if (estaEnRecorridoActivoPedido($estado_pedido)) {
        return [
            'puede_cancelar' => false,
            'razon' => 'El pedido está en recorrido activo. Use el flujo de devolución.'
        ];
    }

    // Validar que pago NO esté aprobado (si se provee estado de pago)
    if ($estado_pago !== null) {
        $estado_pago_normalizado = normalizarEstado($estado_pago);

        if ($estado_pago_normalizado === 'aprobado') {
            return [
                'puede_cancelar' => false,
                'razon' => 'El pago ya fue aprobado. Use el flujo de devolución.'
            ];
        }
    }

    // Puede cancelar directamente
    return [
        'puede_cancelar' => true,
        'razon' => 'El pedido se puede cancelar directamente'
    ];
}

