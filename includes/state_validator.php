<?php
/**
 * ========================================================================
 * VALIDADOR CENTRALIZADO DE ESTADOS - Tienda Seda y Lino
 * ========================================================================
 * Clase centralizada para todas las validaciones de estados de pedidos y pagos
 * 
 * OBJETIVO: Una sola fuente de verdad para todas las validaciones de estados,
 * eliminando duplicación y mejorando mantenibilidad.
 * 
 * USO:
 *   require_once __DIR__ . '/includes/state_validator.php';
 *   StateValidator::canTransition('pendiente', 'aprobado', 'pago');
 *   StateValidator::canCancel('preparacion', 'pedido');
 * 
 * MÉTODOS PRINCIPALES:
 * - canTransition($from, $to, $type) - Valida si una transición está permitida
 * - isInActiveJourney($state, $type) - Verifica si está en recorrido activo
 * - canCancel($state, $type) - Verifica si puede cancelarse
 * - validateOrderPaymentState($order_state, $payment_state) - Valida combinaciones
 * - isInitialState($state, $type) - Verifica si es estado inicial
 * - isTerminal($state, $type) - Verifica si es estado terminal
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Clase estática para validación centralizada de estados
 */
class StateValidator {
    
    /**
     * Matriz de transiciones permitidas para estados de Pago
     * 
     * Define explícitamente qué transiciones de estado están permitidas desde cada estado actual.
     * 
     * REGLAS DE NEGOCIO:
     * - Estados iniciales (pendiente): Pueden cancelarse o rechazarse
     * - Estados de recorrido activo (pendiente_aprobacion, aprobado): NO pueden cancelarse
     * - Un pago en recorrido activo solo puede avanzar o ser rechazado, nunca cancelado
     * 
     * @var array
     */
    private static $transicionesPago = [
        'pendiente' => ['pendiente_aprobacion', 'aprobado', 'rechazado', 'cancelado'],
        'pendiente_aprobacion' => ['aprobado', 'rechazado'], // NO puede cancelarse: ya está en recorrido activo
        'aprobado' => ['rechazado'], // NO puede cancelarse: ya está en recorrido activo (solo rechazo en casos extremos)
        'rechazado' => [], // estado terminal
        'cancelado' => [] // estado terminal
    ];
    
    /**
     * Matriz de transiciones permitidas para estados de Pedido
     * 
     * Define explícitamente qué transiciones de estado están permitidas desde cada estado actual.
     * 
     * REGLAS DE NEGOCIO:
     * - Estados iniciales (pendiente): Pueden cancelarse
     *   NOTA: Todo pedido en 'pendiente' ya tiene stock validado (se valida antes de crear el pedido)
     * - Estados de recorrido activo (preparacion, en_viaje, completado, devolucion): NO pueden cancelarse normalmente
     * - EXCEPCIÓN: preparacion puede cancelarse SOLO cuando el pago está cancelado/rechazado (validación adicional)
     * - Un pedido en recorrido activo solo puede avanzar hacia estados finales, nunca cancelarse (excepto preparacion con excepción)
     * 
     * @var array
     */
    private static $transicionesPedido = [
        'pendiente' => ['preparacion', 'cancelado'],
        'preparacion' => ['en_viaje', 'completado', 'cancelado'], // Puede cancelarse SOLO cuando el pago está cancelado/rechazado (validación adicional)
        'en_viaje' => ['completado'], // NO puede cancelarse: ya está en recorrido activo. NOTA: 'devolucion' existe en DB pero NO está implementado en MVP
        'completado' => [], // estado terminal en MVP - venta cerrada, no admite cambios
        'devolucion' => ['cancelado'], // NO IMPLEMENTADO EN MVP - solo existe en DB para futuro
        'cancelado' => [] // estado terminal
    ];
    
    /**
     * Estados iniciales (pueden cancelarse)
     * 
     * @var array
     */
    private static $estadosIniciales = [
        'pago' => ['pendiente'],
        'pedido' => ['pendiente'] // Todo pedido en 'pendiente' ya tiene stock validado
    ];
    
    /**
     * Estados en recorrido activo (NO pueden cancelarse)
     * 
     * Recorrido activo = estados que dejaron de estar en estado inicial y están en proceso.
     * 
     * @var array
     */
    private static $estadosRecorridoActivo = [
        'pago' => ['pendiente_aprobacion', 'aprobado'],
        'pedido' => ['preparacion', 'en_viaje', 'completado', 'devolucion']
    ];
    
    /**
     * Estados terminales (no admiten cambios)
     * 
     * @var array
     */
    private static $estadosTerminales = [
        'pago' => ['rechazado', 'cancelado'],
        'pedido' => ['completado', 'cancelado']
    ];
    
    /**
     * Normaliza un estado (trim + strtolower)
     * 
     * Usa la función normalizarEstado() de estado_helpers.php para mantener consistencia
     * 
     * @param string|null $estado Estado a normalizar
     * @return string Estado normalizado
     */
    private static function normalizeState($estado) {
        // Cargar función de normalización si no está disponible
        if (!function_exists('normalizarEstado')) {
            require_once __DIR__ . '/estado_helpers.php';
        }
        // Usar función centralizada de normalización
        return normalizarEstado($estado, '');
    }
    
    /**
     * Valida si una transición de estado está permitida según la matriz de transiciones
     * 
     * @param string $from Estado actual
     * @param string $to Nuevo estado al que se quiere cambiar
     * @param string $type Tipo de estado: 'pago' o 'pedido'
     * @return bool True si la transición está permitida
     * @throws Exception Si la transición no está permitida o el tipo es inválido
     */
    public static function canTransition($from, $to, $type) {
        // Validar tipo
        if (!in_array($type, ['pago', 'pedido'])) {
            throw new Exception("Tipo de estado inválido: {$type}. Debe ser 'pago' o 'pedido'.");
        }
        
        // Normalizar estados
        $from_norm = self::normalizeState($from);
        $to_norm = self::normalizeState($to);
        
        // Si el estado actual es igual al nuevo estado, permitir (no hacer nada)
        // Esto evita errores cuando se intenta "cambiar" a un estado que ya tiene
        if ($from_norm === $to_norm) {
            return true;
        }
        
        // Obtener matriz de transiciones según tipo
        $transiciones = ($type === 'pago') ? self::$transicionesPago : self::$transicionesPedido;
        
        // Si el estado actual no existe en la matriz, no permitir ninguna transición
        if (!isset($transiciones[$from_norm])) {
            throw new Exception("Estado de {$type} desconocido: {$from_norm}");
        }
        
        // Si el nuevo estado no está en la lista de permitidos para el estado actual, rechazar
        if (!in_array($to_norm, $transiciones[$from_norm])) {
            $estados_permitidos = implode(', ', $transiciones[$from_norm]);
            throw new Exception("Transición no permitida: No se puede cambiar de '{$from_norm}' a '{$to_norm}'. Transiciones permitidas desde '{$from_norm}': {$estados_permitidos}");
        }
        
        return true;
    }
    
    /**
     * Verifica si un estado está en recorrido activo
     * 
     * Recorrido activo = estados que dejaron de estar en estado inicial y están en proceso.
     * Un estado en recorrido activo NO puede cancelarse.
     * 
     * @param string $state Estado a verificar
     * @param string $type Tipo de estado: 'pago' o 'pedido'
     * @return bool True si está en recorrido activo
     * @throws Exception Si el tipo es inválido
     */
    public static function isInActiveJourney($state, $type) {
        // Validar tipo
        if (!in_array($type, ['pago', 'pedido'])) {
            throw new Exception("Tipo de estado inválido: {$type}. Debe ser 'pago' o 'pedido'.");
        }
        
        // Normalizar estado
        $state_norm = self::normalizeState($state);
        
        // Verificar si está en recorrido activo
        return in_array($state_norm, self::$estadosRecorridoActivo[$type]);
    }
    
    /**
     * Verifica si un estado es inicial (puede cancelarse)
     * 
     * Estados iniciales son aquellos que pueden cancelarse porque aún no han iniciado
     * el proceso de cumplimiento.
     * 
     * @param string $state Estado a verificar
     * @param string $type Tipo de estado: 'pago' o 'pedido'
     * @return bool True si es estado inicial
     * @throws Exception Si el tipo es inválido
     */
    public static function isInitialState($state, $type) {
        // Validar tipo
        if (!in_array($type, ['pago', 'pedido'])) {
            throw new Exception("Tipo de estado inválido: {$type}. Debe ser 'pago' o 'pedido'.");
        }
        
        // Normalizar estado
        $state_norm = self::normalizeState($state);
        
        // Verificar si es estado inicial
        return in_array($state_norm, self::$estadosIniciales[$type]);
    }
    
    /**
     * Verifica si un estado es terminal (no admite cambios)
     * 
     * Estados terminales representan el final de un flujo de negocio y no pueden modificarse.
     * 
     * @param string $state Estado a verificar
     * @param string $type Tipo de estado: 'pago' o 'pedido'
     * @return bool True si es estado terminal
     * @throws Exception Si el tipo es inválido
     */
    public static function isTerminal($state, $type) {
        // Validar tipo
        if (!in_array($type, ['pago', 'pedido'])) {
            throw new Exception("Tipo de estado inválido: {$type}. Debe ser 'pago' o 'pedido'.");
        }
        
        // Normalizar estado
        $state_norm = self::normalizeState($state);
        
        // Verificar si es estado terminal
        return in_array($state_norm, self::$estadosTerminales[$type]);
    }
    
    /**
     * Verifica si un estado puede ser cancelado
     * 
     * REGLA DE NEGOCIO: Solo estados iniciales pueden cancelarse.
     * Estados en recorrido activo NO pueden cancelarse.
     * 
     * @param string $state Estado a verificar
     * @param string $type Tipo de estado: 'pago' o 'pedido'
     * @return bool True si puede cancelarse, false en caso contrario
     * @throws Exception Si el tipo es inválido
     */
    public static function canCancel($state, $type) {
        // Validar tipo
        if (!in_array($type, ['pago', 'pedido'])) {
            throw new Exception("Tipo de estado inválido: {$type}. Debe ser 'pago' o 'pedido'.");
        }
        
        // Normalizar estado
        $state_norm = self::normalizeState($state);
        
        // Solo estados iniciales pueden cancelarse (no están en recorrido activo)
        return self::isInitialState($state_norm, $type) && !self::isInActiveJourney($state_norm, $type);
    }
    
    /**
     * Verifica si un estado está en proceso (sinónimo de isInActiveJourney para claridad)
     * 
     * @param string $state Estado a verificar
     * @param string $type Tipo de estado: 'pago' o 'pedido'
     * @return bool True si está en proceso
     * @throws Exception Si el tipo es inválido
     */
    public static function isInProgress($state, $type) {
        return self::isInActiveJourney($state, $type);
    }
    
    /**
     * Valida la combinación de estados de pedido y pago
     * 
     * Detecta inconsistencias entre estados de pedido y pago según la matriz completa
     * de 35 combinaciones (7 estados pedido × 5 estados pago).
     * 
     * @param string|null $order_state Estado del pedido
     * @param string|null $payment_state Estado del pago
     * @return array Array con:
     *   - 'valid' => bool True si la combinación es válida
     *   - 'warning' => array|null Información del warning si hay inconsistencia (null si es válida)
     *   - 'warning.type' => 'danger'|'warning'|null Tipo de inconsistencia
     *   - 'warning.message' => string Mensaje descriptivo de la inconsistencia
     *   - 'warning.severity' => 'CRÍTICA'|'ADVERTENCIA'|null Severidad
     *   - 'warning.action_suggested' => string Acción sugerida para resolver
     */
    public static function validateOrderPaymentState($order_state, $payment_state) {
        // Normalizar estado del pedido
        $order_norm = self::normalizeState($order_state);
        
        // ========================================================================
        // MANEJO ESPECIAL: Null (sin pago asociado)
        // ========================================================================
        // Verificar null ANTES de normalizar para distinguir entre:
        // - null = no hay pago asociado
        // - 'pendiente' = pago existe pero está pendiente
        if ($payment_state === null || $payment_state === '') {
            // Estados avanzados requieren pago aprobado
            // Si no hay pago, es inconsistente
            $estados_avanzados = ['preparacion', 'en_viaje', 'completado', 'devolucion'];
            if (in_array($order_norm, $estados_avanzados)) {
                return [
                    'valid' => false,
                    'warning' => [
                        'type' => 'warning',
                        'message' => "Pedido en '{$order_norm}' debería tener pago aprobado, pero no hay pago asociado",
                        'severity' => 'ADVERTENCIA',
                        'action_suggested' => 'Crear y aprobar un pago para este pedido o cancelarlo'
                    ]
                ];
            }
            // Estados no avanzados (pendiente, cancelado) pueden no tener pago
            return [
                'valid' => true,
                'warning' => null
            ];
        }
        
        // Normalizar estado del pago solo si no es null
        $payment_norm = self::normalizeState($payment_state);
        
        // GRUPO 1: INCONSISTENCIAS CRÍTICAS (danger)
        // Pedido en viaje o completado con pago rechazado o cancelado
        if (($order_norm === 'en_viaje' || $order_norm === 'completado') 
            && ($payment_norm === 'rechazado' || $payment_norm === 'cancelado')) {
            return [
                'valid' => false,
                'warning' => [
                    'type' => 'danger',
                    'message' => "INCONSISTENCIA CRÍTICA: Pedido en estado '{$order_norm}' con pago '{$payment_norm}'",
                    'severity' => 'CRÍTICA',
                    'action_suggested' => 'Revisar inmediatamente, contactar al cliente, verificar stock'
                ]
            ];
        }
        
        // GRUPO 2: Estados avanzados sin pago aprobado (warning)
        // Pedido avanzado (preparacion, en_viaje, completado, devolucion) con pago pendiente
        if (($order_norm === 'preparacion' || $order_norm === 'en_viaje' || $order_norm === 'completado' || $order_norm === 'devolucion')
            && ($payment_norm === 'pendiente' || $payment_norm === 'pendiente_aprobacion')) {
            return [
                'valid' => false,
                'warning' => [
                    'type' => 'warning',
                    'message' => "Pedido en '{$order_norm}' debería tener pago aprobado, pero está en '{$payment_norm}'",
                    'severity' => 'ADVERTENCIA',
                    'action_suggested' => 'Revisar estado del pago y aprobar si corresponde'
                ]
            ];
        }
        
        // GRUPO 3: Preparación con pago rechazado o cancelado (warning)
        if ($order_norm === 'preparacion' 
            && ($payment_norm === 'rechazado' || $payment_norm === 'cancelado')) {
            return [
                'valid' => false,
                'warning' => [
                    'type' => 'warning',
                    'message' => "Pedido en '{$order_norm}' con pago '{$payment_norm}' (debería estar cancelado)",
                    'severity' => 'ADVERTENCIA',
                    'action_suggested' => 'Cancelar el pedido y restaurar stock si corresponde'
                ]
            ];
        }
        
        // GRUPO 4: Devolución con pago rechazado o cancelado (warning)
        if ($order_norm === 'devolucion' 
            && ($payment_norm === 'rechazado' || $payment_norm === 'cancelado')) {
            return [
                'valid' => false,
                'warning' => [
                    'type' => 'warning',
                    'message' => "Pedido en '{$order_norm}' con pago '{$payment_norm}' (inconsistente)",
                    'severity' => 'ADVERTENCIA',
                    'action_suggested' => 'Revisar estado del pedido y pago, verificar consistencia'
                ]
            ];
        }
        
        // GRUPO 5: Pedido cancelado con pago aprobado (warning)
        if ($order_norm === 'cancelado' && $payment_norm === 'aprobado') {
            return [
                'valid' => false,
                'warning' => [
                    'type' => 'warning',
                    'message' => "Pedido cancelado con pago '{$payment_norm}' (inconsistente - debería restaurar stock)",
                    'severity' => 'ADVERTENCIA',
                    'action_suggested' => 'Verificar si el stock fue restaurado, cancelar pago si es necesario'
                ]
            ];
        }
        
        // Combinación válida (no hay warning)
        return [
            'valid' => true,
            'warning' => null
        ];
    }
    
    /**
     * Obtiene la matriz de transiciones para un tipo de estado
     * 
     * Útil para debugging o para obtener todas las transiciones permitidas.
     * 
     * @param string $type Tipo de estado: 'pago' o 'pedido'
     * @return array Matriz de transiciones
     * @throws Exception Si el tipo es inválido
     */
    public static function getTransitionMatrix($type) {
        // Validar tipo
        if (!in_array($type, ['pago', 'pedido'])) {
            throw new Exception("Tipo de estado inválido: {$type}. Debe ser 'pago' o 'pedido'.");
        }
        
        return ($type === 'pago') ? self::$transicionesPago : self::$transicionesPedido;
    }
    
    /**
     * Obtiene todos los estados permitidos para un tipo
     * 
     * @param string $type Tipo de estado: 'pago' o 'pedido'
     * @return array Array con todos los estados permitidos
     * @throws Exception Si el tipo es inválido
     */
    public static function getAllStates($type) {
        // Validar tipo
        if (!in_array($type, ['pago', 'pedido'])) {
            throw new Exception("Tipo de estado inválido: {$type}. Debe ser 'pago' o 'pedido'.");
        }
        
        $matrix = self::getTransitionMatrix($type);
        return array_keys($matrix);
    }
}
