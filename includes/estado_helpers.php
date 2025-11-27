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
        'devolucion' => ['color' => 'secondary', 'nombre' => 'Devolución'],
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
    if ($estado === null) {
        return $default;
    }
    
    $estado_normalizado = strtolower(trim($estado));
    
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
 * NOTA: Esta función ahora delega a StateValidator::validateOrderPaymentState() para
 * centralizar la lógica de validación, pero mantiene el formato de retorno original
 * para compatibilidad.
 * 
 * CASOS VÁLIDOS (NO muestran warning): 20 combinaciones
 * - pendiente + cualquier estado de pago (5 casos)
 * - preparacion/en_viaje/completado/devolucion + aprobado (4 casos)
 * - cancelado + pendiente/pendiente_aprobacion/rechazado/cancelado (4 casos)
 * - preparacion + pendiente (1 caso - aunque técnicamente debería tener aprobado, no es crítico)
 * - devolucion + aprobado (1 caso)
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
    // Cargar StateValidator si no está cargado
    require_once __DIR__ . '/state_validator.php';
    
    // Normalizar estado del pedido
    $estado_pedido_norm = normalizarEstado($estado_pedido);
    
    // Pasar null explícitamente si no hay pago, para que validateOrderPaymentState() lo maneje correctamente
    // Si normalizamos null a 'pendiente' aquí, perdemos la distinción entre "sin pago" y "pago pendiente"
    $estado_pago_para_validacion = ($estado_pago === null || $estado_pago === '') ? null : normalizarEstado($estado_pago);
    
    // Normalizar para uso en mensajes (después de la validación)
    $estado_pago_norm = normalizarEstado($estado_pago);
    
    $estado_pedido_anterior_norm = $estado_pedido_anterior ? normalizarEstado($estado_pedido_anterior) : null;
    $estado_pago_anterior_norm = $estado_pago_anterior ? normalizarEstado($estado_pago_anterior) : null;
    
    // Usar StateValidator para validar combinación (pasar null explícitamente)
    $validation = StateValidator::validateOrderPaymentState($estado_pedido_norm, $estado_pago_para_validacion);
    
    // Si no hay warning, retornar formato compatible
    if ($validation['warning'] === null) {
        return [
            'hay_inconsistencia' => false,
            'tipo' => null,
            'mensaje' => '',
            'severidad' => null,
            'grupo' => null,
            'accion_sugerida' => ''
        ];
    }
    
    // Convertir warning de StateValidator al formato esperado
    $warning = $validation['warning'];
    
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
    $mensaje_base = $warning['message'];
    // Reemplazar valores normalizados por nombres legibles en el mensaje
    $mensaje_base = str_replace("'{$estado_pedido_norm}'", "'{$info_pedido['nombre']}'", $mensaje_base);
    $mensaje_base = str_replace("'{$estado_pago_norm}'", "'{$info_pago['nombre']}'", $mensaje_base);
    $mensaje_mejorado = $mensaje_base . $transicion_texto;
    
    // Determinar grupo según el tipo de warning
    $grupo = null;
    if ($warning['type'] === 'danger') {
        $grupo = 'inconsistencias_criticas';
    } elseif (strpos($warning['message'], 'debería tener pago aprobado') !== false) {
        $grupo = 'estados_avanzados_sin_pago_aprobado';
    } elseif (strpos($warning['message'], 'debería estar cancelado') !== false) {
        $grupo = 'estados_con_pago_rechazado';
    } elseif ($estado_pedido_norm === 'devolucion') {
        $grupo = 'devolucion_con_pago_rechazado';
    } elseif ($estado_pedido_norm === 'cancelado' && $estado_pago_norm === 'aprobado') {
        $grupo = 'cancelado_con_pago_aprobado';
    }
    
    return [
        'hay_inconsistencia' => true,
        'tipo' => $warning['type'],
        'mensaje' => $mensaje_mejorado,
        'severidad' => $warning['severity'],
        'grupo' => $grupo,
        'accion_sugerida' => $warning['action_suggested']
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
function formatearMensajeExito($estado_anterior, $estado_actual, $tipo = 'pedido', $info_adicional = []) {
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
        $mensaje = "Se ha realizado correctamente el cambio de estado: {$info_anterior['nombre']} → {$info_actual['nombre']}";
    } else {
        $mensaje = "Estado actual: {$info_actual['nombre']}";
    }
    
    // Agregar información adicional con contexto más claro
    $partes_adicionales = [];
    
    if (!empty($info_adicional['stock_descontado']) && $info_adicional['stock_descontado']) {
        $partes_adicionales[] = 'Stock descontado automáticamente del inventario';
    }
    
    if (!empty($info_adicional['stock_restaurado']) && $info_adicional['stock_restaurado']) {
        $partes_adicionales[] = 'Stock restaurado automáticamente al inventario';
    }
    
    if (!empty($info_adicional['pago_aprobado']) && $info_adicional['pago_aprobado']) {
        $partes_adicionales[] = 'Pago aprobado correctamente';
    }
    
    if (!empty($info_adicional['pago_rechazado']) && $info_adicional['pago_rechazado']) {
        $partes_adicionales[] = 'Pago rechazado';
    }
    
    if (!empty($info_adicional['mensaje_extra'])) {
        $partes_adicionales[] = $info_adicional['mensaje_extra'];
    }
    
    // Combinar mensaje base con información adicional de forma más clara
    if (!empty($partes_adicionales)) {
        $mensaje .= '. ' . implode(', ', $partes_adicionales) . '.';
    }
    
    return $mensaje;
}

/**
 * Obtiene mensaje de warning agrupado por tipo
 * 
 * Retorna un mensaje agrupado que puede ser usado para mostrar múltiples
 * warnings del mismo tipo de manera más compacta
 * 
 * @param string $grupo Grupo de warnings (ver StateValidator::validateOrderPaymentState)
 * @param array $warnings Array de warnings del mismo grupo
 * @return string Mensaje agrupado
 */
function obtenerMensajeWarningAgrupado($grupo, $warnings) {
    if (empty($warnings)) {
        return '';
    }
    
    // Contar warnings por tipo
    $tipos = array_count_values(array_column($warnings, 'tipo'));
    $total = count($warnings);
    
    // Construir mensaje según el grupo
    switch ($grupo) {
        case 'inconsistencias_criticas':
            return "INCONSISTENCIA CRÍTICA: Se detectaron {$total} pedido(s) en estado avanzado con pago rechazado/cancelado. Acción sugerida: Revisar inmediatamente, contactar a los clientes, verificar stock.";
            
        case 'estados_avanzados_sin_pago_aprobado':
            return "Se detectaron {$total} pedido(s) en estado avanzado sin pago aprobado. Acción sugerida: Revisar estado de los pagos y aprobar si corresponde.";
            
        case 'estados_con_pago_rechazado':
            return "Se detectaron {$total} pedido(s) en preparación con pago rechazado/cancelado. Acción sugerida: Cancelar los pedidos y restaurar stock si corresponde.";
            
        case 'devolucion_con_pago_rechazado':
            return "Se detectaron {$total} pedido(s) en devolución con pago rechazado/cancelado. Acción sugerida: Revisar estado de los pedidos y pagos, verificar consistencia.";
            
        case 'cancelado_con_pago_aprobado':
            return "Se detectaron {$total} pedido(s) cancelados con pago aprobado. Acción sugerida: Verificar si el stock fue restaurado, cancelar pagos si es necesario.";
            
        default:
            return "Se detectaron {$total} inconsistencia(s) que requieren atención.";
    }
}

/**
 * Formatea mensajes de error de estados de manera consistente y accionable
 * 
 * Esta función centraliza el formato de mensajes de error relacionados con estados,
 * proporcionando mensajes consistentes que incluyen:
 * - Qué pasó (descripción del error)
 * - Por qué no se puede hacer (razón técnica)
 * - Qué hacer para resolverlo (acción sugerida)
 * 
 * CÓDIGOS DE ERROR:
 * - STATE_ERR_001: Transición no permitida
 * - STATE_ERR_002: Estado en recorrido activo (no puede cancelarse)
 * - STATE_ERR_003: Estado terminal (no admite cambios)
 * - STATE_ERR_004: Estado desconocido
 * - STATE_ERR_005: Combinación de estados inválida
 * 
 * @param string $error_type Tipo de error (código o descripción)
 * @param string $current_state Estado actual
 * @param string|null $target_state Estado objetivo (opcional)
 * @param array $context Contexto adicional del error:
 *   - 'type' => 'pago'|'pedido' Tipo de estado
 *   - 'id_pedido' => int ID del pedido (opcional)
 *   - 'id_pago' => int ID del pago (opcional)
 *   - 'reason' => string Razón adicional del error (opcional)
 * @return array Array con:
 *   - 'code' => string Código de error único
 *   - 'message' => string Mensaje formateado para el usuario
 *   - 'technical' => string Mensaje técnico para logs
 *   - 'action' => string Acción sugerida para resolver
 */
function formatStateError($error_type, $current_state, $target_state = null, $context = []) {
    // Cargar StateValidator si no está cargado
    require_once __DIR__ . '/state_validator.php';
    
    // Normalizar estados
    $current_norm = normalizarEstado($current_state);
    $target_norm = $target_state ? normalizarEstado($target_state) : null;
    $type = $context['type'] ?? 'pedido';
    
    // Obtener información de estados para mensajes más claros
    if ($type === 'pedido') {
        $info_current = obtenerInfoEstadoPedido($current_norm);
        $info_target = $target_norm ? obtenerInfoEstadoPedido($target_norm) : null;
    } else {
        $info_current = obtenerInfoEstadoPago($current_norm);
        $info_target = $target_norm ? obtenerInfoEstadoPago($target_norm) : null;
    }
    
    $nombre_current = $info_current['nombre'];
    $nombre_target = $info_target ? $info_target['nombre'] : '';
    
    // Determinar código y mensaje según tipo de error
    $code = 'STATE_ERR_UNKNOWN';
    $message = '';
    $technical = '';
    $action = '';
    
    // Detectar tipo de error por contenido del mensaje o código
    if (strpos($error_type, 'Transición no permitida') !== false || strpos($error_type, 'STATE_ERR_001') !== false) {
        $code = 'STATE_ERR_001';
        $message = "No se puede cambiar el estado de {$type} de '{$nombre_current}' a '{$nombre_target}'.";
        $technical = "Transición no permitida: {$current_norm} → {$target_norm} (tipo: {$type})";
        
        // Obtener estados permitidos
        try {
            $matrix = StateValidator::getTransitionMatrix($type);
            $allowed = isset($matrix[$current_norm]) ? $matrix[$current_norm] : [];
            if (!empty($allowed)) {
                $allowed_names = [];
                foreach ($allowed as $estado) {
                    if ($type === 'pedido') {
                        $allowed_names[] = obtenerInfoEstadoPedido($estado)['nombre'];
                    } else {
                        $allowed_names[] = obtenerInfoEstadoPago($estado)['nombre'];
                    }
                }
                $message .= " Transiciones permitidas desde '{$nombre_current}': " . implode(', ', $allowed_names) . ".";
            }
        } catch (Exception $e) {
            // Si falla, usar mensaje genérico
        }
        
        $action = "Revisa el estado actual del {$type} y verifica que la transición sea válida según el flujo de estados permitidos. Consulta la documentación de transiciones de estados si necesitas más información.";
        
    } elseif (strpos($error_type, 'recorrido activo') !== false || strpos($error_type, 'STATE_ERR_002') !== false || strpos($error_type, 'no puede cancelarse') !== false) {
        $code = 'STATE_ERR_002';
        $message = "No se puede cancelar el {$type} porque está en estado '{$nombre_current}' (en proceso).";
        $technical = "Intento de cancelar {$type} en recorrido activo: {$current_norm}";
        
        if ($type === 'pago') {
            $action = "Los pagos en proceso (pendiente de aprobación o aprobados) no pueden cancelarse. Si necesitas revertir un pago aprobado, debes rechazarlo en lugar de cancelarlo.";
        } else {
            $action = "Los pedidos en proceso (preparación, en viaje, completado, devolución) no pueden cancelarse. Si el pedido aún no fue enviado, puedes cancelarlo antes de que pase a 'en viaje'.";
        }
        
    } elseif (strpos($error_type, 'terminal') !== false || strpos($error_type, 'no admite cambios') !== false || strpos($error_type, 'STATE_ERR_003') !== false || strpos($error_type, 'completado') !== false) {
        $code = 'STATE_ERR_003';
        $message = "No se puede cambiar el estado del {$type} porque está en estado '{$nombre_current}' (estado final).";
        $technical = "Intento de cambiar {$type} en estado terminal: {$current_norm}";
        
        if ($current_norm === 'completado') {
            $action = "Los pedidos completados son ventas cerradas y no admiten modificaciones de estado. Si necesitas hacer cambios, contacta al administrador del sistema.";
        } else {
            $action = "Este estado es final y no admite cambios. Si necesitas hacer modificaciones, contacta al administrador del sistema.";
        }
        
    } elseif (strpos($error_type, 'desconocido') !== false || strpos($error_type, 'STATE_ERR_004') !== false) {
        $code = 'STATE_ERR_004';
        $message = "El estado '{$current_norm}' no es válido para un {$type}.";
        $technical = "Estado desconocido: {$current_norm} (tipo: {$type})";
        $action = "Recarga la página para obtener la información actualizada del {$type} e intenta nuevamente. Si el problema persiste, contacta al administrador.";
        
    } elseif (strpos($error_type, 'combinación') !== false || strpos($error_type, 'inconsistencia') !== false || strpos($error_type, 'STATE_ERR_005') !== false) {
        $code = 'STATE_ERR_005';
        $message = "La combinación de estados no es válida: {$type} en '{$nombre_current}'.";
        $technical = "Combinación inválida: {$current_norm}";
        
        if (isset($context['payment_state'])) {
            $payment_norm = normalizarEstado($context['payment_state']);
            $info_payment = obtenerInfoEstadoPago($payment_norm);
            $message .= " con pago en '{$info_payment['nombre']}'.";
        }
        
        $action = "Revisa el estado del {$type} y su pago asociado. Corrige la inconsistencia según las reglas de negocio del sistema.";
        
    } else {
        // Error genérico
        $code = 'STATE_ERR_UNKNOWN';
        $message = "Error al procesar el cambio de estado del {$type}.";
        $technical = "Error de estado: {$error_type} (estado actual: {$current_norm}" . ($target_norm ? ", estado objetivo: {$target_norm}" : "") . ")";
        $action = "Verifica que todos los datos sean correctos e intenta nuevamente. Si el problema persiste, contacta al administrador.";
    }
    
    // Agregar contexto adicional si está disponible
    $context_info = [];
    if (isset($context['id_pedido'])) {
        $context_info[] = "Pedido #{$context['id_pedido']}";
    }
    if (isset($context['id_pago'])) {
        $context_info[] = "Pago #{$context['id_pago']}";
    }
    if (!empty($context_info)) {
        $message = implode(' - ', $context_info) . ": " . $message;
    }
    
    // Agregar razón adicional si está disponible
    if (isset($context['reason']) && !empty($context['reason'])) {
        $message .= " " . $context['reason'];
    }
    
    return [
        'code' => $code,
        'message' => $message,
        'technical' => $technical,
        'action' => $action
    ];
}

