<?php
/**
 * ========================================================================
 * COMPONENTES DE VENTAS - Tienda Seda y Lino
 * ========================================================================
 * Funciones helper para renderizar componentes complejos del panel de ventas
 * Reduce anidación y mejora mantenibilidad del código
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Renderiza el modal de visualización de detalles del pedido
 * 
 * @param array $pedido Datos básicos del pedido
 * @param array $pedido_completo Datos completos del pedido
 * @param array $detalles_pedido Array de productos del pedido
 * @param array|null $pago_detalle Información del pago asociado
 * @return void
 */
function renderModalVerPedido($pedido, $pedido_completo, $detalles_pedido, $pago_detalle) {
    $id_pedido = $pedido['id_pedido'];
    ?>
    <div class="modal fade" id="verPedidoModal<?= $id_pedido ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-shopping-cart me-2"></i>Detalles del Pedido #<?= $id_pedido ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php 
                    renderSeccionCliente($pedido_completo);
                    renderSeccionPedido($pedido_completo, $pago_detalle);
                    renderSeccionProductos($detalles_pedido);
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Renderiza la sección de información del cliente
 * 
 * @param array $pedido_completo Datos completos del pedido
 * @return void
 */
function renderSeccionCliente($pedido_completo) {
    ?>
    <div class="card mb-3">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-user me-2"></i>Información del Cliente</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Nombre:</strong> <?= htmlspecialchars($pedido_completo['nombre'] . ' ' . $pedido_completo['apellido']) ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($pedido_completo['email']) ?></p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1"><strong>Teléfono:</strong> <?= htmlspecialchars($pedido_completo['telefono'] ?: 'N/A') ?></p>
                    <p class="mb-1"><strong>Dirección:</strong> 
                        <?= !empty($pedido_completo['direccion']) ? htmlspecialchars($pedido_completo['direccion']) : 'N/A' ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Renderiza la sección de información del pedido
 * 
 * @param array $pedido_completo Datos completos del pedido
 * @param array|null $pago_detalle Información del pago asociado
 * @return void
 */
function renderSeccionPedido($pedido_completo, $pago_detalle) {
    $info_estado_detalle = obtenerInfoEstadoPedido($pedido_completo['estado_pedido'] ?? '');
    ?>
    <div class="card mb-3">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información del Pedido</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <?php renderInfoPedidoIzquierda($pedido_completo, $info_estado_detalle); ?>
                </div>
                <div class="col-md-6">
                    <?php renderInfoPedidoDerecha($pedido_completo, $pago_detalle); ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Renderiza la columna izquierda de información del pedido
 * 
 * @param array $pedido_completo Datos completos del pedido
 * @param array $info_estado_detalle Información del estado del pedido
 * @return void
 */
function renderInfoPedidoIzquierda($pedido_completo, $info_estado_detalle) {
    ?>
    <p class="mb-1"><strong>Fecha Pedido:</strong> <?= date('d/m/Y H:i', strtotime($pedido_completo['fecha_pedido'])) ?></p>
    <?php if (!empty($pedido_completo['fecha_actualizacion'])): ?>
    <p class="mb-1"><strong>Última Actualización:</strong> <?= date('d/m/Y H:i', strtotime($pedido_completo['fecha_actualizacion'])) ?></p>
    <?php endif; ?>
    <p class="mb-1">
        <strong>Estado:</strong> 
        <span class="badge bg-<?= htmlspecialchars($info_estado_detalle['color']) ?>">
            <?= htmlspecialchars($info_estado_detalle['nombre']) ?>
        </span>
    </p>
    <?php if (!empty($pedido_completo['direccion_entrega'])): ?>
    <p class="mb-1"><strong>Dirección de Entrega:</strong><br>
        <small><?= nl2br(htmlspecialchars($pedido_completo['direccion_entrega'])) ?></small>
    </p>
    <?php endif; ?>
    <?php if (!empty($pedido_completo['telefono_contacto'])): ?>
    <p class="mb-1"><strong>Teléfono de Contacto:</strong> <?= htmlspecialchars($pedido_completo['telefono_contacto']) ?></p>
    <?php endif; ?>
    <?php
}

/**
 * Renderiza la columna derecha de información del pedido
 * 
 * @param array $pedido_completo Datos completos del pedido
 * @param array|null $pago_detalle Información del pago asociado
 * @return void
 */
function renderInfoPedidoDerecha($pedido_completo, $pago_detalle) {
    ?>
    <?php if (!empty($pedido_completo['observaciones'])): ?>
    <p class="mb-1"><strong>Observaciones:</strong><br>
        <small><?= nl2br(htmlspecialchars($pedido_completo['observaciones'])) ?></small>
    </p>
    <?php endif; ?>
    
    <?php if ($pago_detalle): 
        $info_estado_pago_detalle = obtenerInfoEstadoPago($pago_detalle['estado_pago'] ?? '');
    ?>
        <p class="mb-1">
            <strong>Estado Pago:</strong> 
            <span class="badge bg-<?= htmlspecialchars($info_estado_pago_detalle['color']) ?>">
                <?= htmlspecialchars($info_estado_pago_detalle['nombre']) ?>
            </span>
        </p>
        <p class="mb-1"><strong>Monto:</strong> $<?= number_format($pago_detalle['monto'], 2, ',', '.') ?></p>
        <p class="mb-1"><strong>Método de Pago:</strong> <?= htmlspecialchars($pago_detalle['forma_pago_nombre']) ?></p>
        
        <?php if (!empty($pago_detalle['numero_transaccion'])): ?>
        <p class="mb-1"><strong>Número de Transacción:</strong> <?= htmlspecialchars($pago_detalle['numero_transaccion']) ?></p>
        <?php endif; ?>
        
        <?php if (!empty($pago_detalle['fecha_aprobacion'])): ?>
        <p class="mb-1"><strong>Fecha de Aprobación:</strong> <?= date('d/m/Y H:i', strtotime($pago_detalle['fecha_aprobacion'])) ?></p>
        <?php endif; ?>
        
        <?php if (!empty($pago_detalle['motivo_rechazo'])): ?>
        <p class="mb-1"><strong>Motivo de Rechazo:</strong><br>
            <small class="text-danger"><?= nl2br(htmlspecialchars($pago_detalle['motivo_rechazo'])) ?></small>
        </p>
        <?php endif; ?>
        
        <?php if (!empty($pago_detalle['fecha_actualizacion'])): ?>
        <p class="mb-1"><strong>Última Actualización Pago:</strong> <?= date('d/m/Y H:i', strtotime($pago_detalle['fecha_actualizacion'])) ?></p>
        <?php endif; ?>
    <?php endif; ?>
    <?php
}

/**
 * Renderiza la sección de productos del pedido
 * 
 * @param array $detalles_pedido Array de productos del pedido
 * @return void
 */
function renderSeccionProductos($detalles_pedido) {
    ?>
    <div class="card">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-box me-2"></i>Productos del Pedido</h6>
        </div>
        <div class="card-body">
            <?php if (empty($detalles_pedido)): ?>
                <p class="text-muted text-center mb-0">No hay productos en este pedido</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Talla</th>
                            <th>Color</th>
                            <th class="text-end">Cantidad</th>
                            <th class="text-end">Precio Unit.</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_calculado = 0;
                        foreach ($detalles_pedido as $detalle): 
                            $subtotal = $detalle['cantidad'] * $detalle['precio_unitario'];
                            $total_calculado += $subtotal;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($detalle['nombre_producto']) ?></td>
                            <td><?= htmlspecialchars($detalle['talle']) ?></td>
                            <td><?= htmlspecialchars($detalle['color']) ?></td>
                            <td class="text-end"><?= $detalle['cantidad'] ?></td>
                            <td class="text-end">$<?= number_format($detalle['precio_unitario'], 2, ',', '.') ?></td>
                            <td class="text-end"><strong>$<?= number_format($subtotal, 2, ',', '.') ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-info">
                            <th colspan="5" class="text-end">Total del Pedido:</th>
                            <th class="text-end">$<?= number_format($total_calculado, 2, ',', '.') ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Renderiza el modal de edición de estado del pedido
 * 
 * @param array $pedido Datos básicos del pedido
 * @param array $pedido_completo_modal Datos completos del pedido
 * @param array|null $pago_modal Información del pago asociado
 * @param string $estado_actual_modal Estado actual normalizado del pedido
 * @return void
 */
function renderModalEditarEstado($pedido, $pedido_completo_modal, $pago_modal, $estado_actual_modal) {
    $id_pedido = $pedido['id_pedido'];
    ?>
    <div class="modal fade" id="editarEstadoModal<?= $id_pedido ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Estado - Pedido #<?= $id_pedido ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="formEditarEstado<?= $id_pedido ?>">
                    <div class="modal-body">
                        <?php 
                        renderFormularioHiddenFields($id_pedido, $estado_actual_modal);
                        renderInfoClienteCompacta($pedido);
                        
                        // Mostrar formulario de pago primero
                        $estado_pago_para_filtrado = null;
                        if ($pago_modal) {
                            renderFormularioPago($id_pedido, $pago_modal, $estado_actual_modal);
                            // Normalizar estado del pago para filtrado
                            $estado_pago_para_filtrado = normalizarEstado($pago_modal['estado_pago'] ?? null);
                        } else {
                            renderAlertaSinPago();
                        }
                        
                        // Mostrar formulario de pedido después, pasando estado del pago para filtrado
                        renderFormularioEstadoPedido($estado_actual_modal, $estado_pago_para_filtrado);
                        ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="actualizar_estado_pedido" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
                
                <?php if ($pago_modal && $pago_modal['estado_pago'] === 'pendiente'): ?>
                    <?php renderFormulariosAdicionales($id_pedido, $pago_modal); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Renderiza los campos ocultos del formulario
 * 
 * @param int $id_pedido ID del pedido
 * @param string $estado_actual_modal Estado actual normalizado
 * @return void
 */
function renderFormularioHiddenFields($id_pedido, $estado_actual_modal) {
    ?>
    <input type="hidden" name="pedido_id" value="<?= $id_pedido ?>">
    <input type="hidden" name="estado_anterior" value="<?= htmlspecialchars($estado_actual_modal) ?>">
    <?php
}

/**
 * Renderiza información compacta del cliente
 * 
 * @param array $pedido Datos básicos del pedido
 * @return void
 */
function renderInfoClienteCompacta($pedido) {
    ?>
    <div class="mb-3">
        <small class="text-muted">
            <i class="fas fa-user me-1"></i>
            <strong><?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?></strong> | 
            <?= htmlspecialchars($pedido['email']) ?>
        </small>
    </div>
    <?php
}

/**
 * Obtiene las transiciones válidas de estado de pedido según el estado del pago
 * 
 * Filtra las transiciones permitidas según la matriz de estados y las restricciones
 * basadas en el estado del pago. Un pedido solo puede avanzar a estados avanzados
 * (preparacion, en_viaje, completado, devolucion) si el pago está aprobado.
 * 
 * REGLAS:
 * - Pago pendiente/pendiente_aprobacion: Solo permite pendiente y cancelado
 * - Pago aprobado: Permite todas las transiciones válidas según matriz
 * - Pago rechazado/cancelado: Solo permite cancelado (y mantener estado actual si es válido)
 *   EXCEPCIÓN: Permite cancelar desde 'en_viaje' cuando el pago está rechazado/cancelado
 * - Sin pago: Solo permite pendiente y cancelado
 * 
 * @param string $estado_pedido_actual Estado actual normalizado del pedido
 * @param string|null $estado_pago Estado normalizado del pago (null si no hay pago)
 * @return array Array de estados válidos para transición
 */
function obtenerTransicionesValidasPedido($estado_pedido_actual, $estado_pago = null) {
    // Cargar StateValidator si no está disponible
    require_once __DIR__ . '/state_validator.php';
    
    // Normalizar estados
    $estado_pedido_norm = normalizarEstado($estado_pedido_actual);
    $estado_pago_norm = $estado_pago ? normalizarEstado($estado_pago) : null;
    
    // ========================================================================
    // VALIDACIÓN 1: Estados Terminales
    // ========================================================================
    // Verificar si el estado actual es terminal
    if (StateValidator::isTerminal($estado_pedido_norm, 'pedido')) {
        // Estados terminales solo pueden mantenerse
        // completado es completamente terminal (no puede cambiar a devolucion - no implementado en MVP)
        // Para cancelado: solo mantener estado actual (no puede cambiar)
        return [$estado_pedido_norm];
    }
    
    // ========================================================================
    // VALIDACIÓN 1.5: Estados Finales (en_viaje y completado) - BLOQUEAR RETROCESOS
    // ========================================================================
    // en_viaje y completado son estados finales del ciclo, NO pueden retroceder
    if ($estado_pedido_norm === 'en_viaje') {
        // en_viaje solo puede ir a completado (NO retrocesos, NO devolucion - no implementado en MVP)
        $transiciones_base = StateValidator::getTransitionMatrix('pedido');
        $transiciones_permitidas = $transiciones_base[$estado_pedido_norm] ?? [];
        
        // Solo permitir transiciones hacia adelante (completado)
        // NO permitir retrocesos a preparacion o pendiente
        $estados_finales = array_filter($transiciones_permitidas, function($estado) {
            // Solo permitir completado (devolucion no está implementado en MVP)
            return $estado === 'completado';
        });
        
        // Siempre incluir el estado actual
        if (!in_array($estado_pedido_norm, $estados_finales)) {
            $estados_finales[] = $estado_pedido_norm;
        }
        
        // Si pago no está aprobado, solo permitir mantener estado actual o cancelar (para corregir inconsistencia)
        if ($estado_pago_norm !== 'aprobado') {
            // Permitir cancelado para corregir inconsistencias
            if (!in_array('cancelado', $estados_finales)) {
                $estados_finales[] = 'cancelado';
            }
        }
        
        return array_values(array_unique($estados_finales));
    }
    
    // ========================================================================
    // VALIDACIÓN 2: Estados Avanzados Requieren Pago Aprobado
    // ========================================================================
    // Estados avanzados: preparacion, en_viaje
    // NOTA: devolucion no está implementado en MVP
    $estados_avanzados = ['preparacion', 'en_viaje'];
    
    if (in_array($estado_pedido_norm, $estados_avanzados)) {
        // Si pago no está aprobado, solo permitir cancelado (para corregir inconsistencia)
        if ($estado_pago_norm !== 'aprobado') {
            // EXCEPCIÓN: en_viaje puede cancelarse incluso si no está en la matriz base
            // Esto permite corregir inconsistencias críticas donde el pago fue rechazado/cancelado
            // pero el pedido quedó en viaje
            if ($estado_pedido_norm === 'en_viaje') {
                return ['cancelado'];
            }
            
            // Obtener matriz de transiciones base
            $transiciones_base = StateValidator::getTransitionMatrix('pedido');
            $transiciones_permitidas = $transiciones_base[$estado_pedido_norm] ?? [];
            
            // Solo permitir cancelado si está en las transiciones permitidas
            if (in_array('cancelado', $transiciones_permitidas)) {
                return ['cancelado'];
            }
            // Si cancelado no está permitido, mantener estado actual (inconsistencia crítica)
            return [$estado_pedido_norm];
        }
    }
    
    // ========================================================================
    // VALIDACIÓN 3: Obtener Transiciones Base
    // ========================================================================
    // Obtener matriz de transiciones base desde StateValidator
    $transiciones_base = StateValidator::getTransitionMatrix('pedido');
    
    // Obtener transiciones permitidas desde el estado actual del pedido
    $transiciones_permitidas = [];
    if (isset($transiciones_base[$estado_pedido_norm])) {
        $transiciones_permitidas = $transiciones_base[$estado_pedido_norm];
    }
    
    // Siempre incluir el estado actual (para mantener el mismo estado)
    if (!in_array($estado_pedido_norm, $transiciones_permitidas)) {
        $transiciones_permitidas[] = $estado_pedido_norm;
    }
    
    // ========================================================================
    // VALIDACIÓN 4: Filtrar según Estado del Pago
    // ========================================================================
    $estados_finales = [];
    
    // Si no hay pago o pago está pendiente/pendiente_aprobacion
    if (!$estado_pago_norm || 
        $estado_pago_norm === 'pendiente' || 
        $estado_pago_norm === 'pendiente_aprobacion') {
        // Solo permitir pendiente y cancelado
        foreach ($transiciones_permitidas as $estado) {
            if ($estado === 'pendiente' || $estado === 'cancelado') {
                $estados_finales[] = $estado;
            }
        }
        // Asegurar que pendiente esté disponible si el estado actual es pendiente
        if ($estado_pedido_norm === 'pendiente' && !in_array('pendiente', $estados_finales)) {
            $estados_finales[] = 'pendiente';
        }
    }
    // Si pago está aprobado
    elseif ($estado_pago_norm === 'aprobado') {
        // Permitir todas las transiciones válidas según matriz
        $estados_finales = $transiciones_permitidas;
        
        // EXCEPCIÓN: Si el estado actual es pendiente, no incluir pendiente en las opciones
        // (solo preparacion y cancelado)
        if ($estado_pedido_norm === 'pendiente') {
            $estados_finales = array_filter($estados_finales, function($estado) {
                return $estado !== 'pendiente';
            });
            // Re-indexar array
            $estados_finales = array_values($estados_finales);
        }
    }
    // Si pago está rechazado o cancelado
    elseif ($estado_pago_norm === 'rechazado' || $estado_pago_norm === 'cancelado') {
        // Solo permitir cancelado
        if (in_array('cancelado', $transiciones_permitidas)) {
            $estados_finales[] = 'cancelado';
        }
        // EXCEPCIÓN: Permitir cancelar desde 'en_viaje' cuando el pago está cancelado/rechazado
        // Esto permite corregir inconsistencias donde el pago fue cancelado pero el pedido quedó en viaje
        // (en_viaje no tiene cancelado en su matriz base, pero debe permitirse para corregir)
        if ($estado_pedido_norm === 'en_viaje') {
            $estados_finales[] = 'cancelado';
        }
        // Mantener estado actual si es cancelado
        if ($estado_pedido_norm === 'cancelado' && !in_array('cancelado', $estados_finales)) {
            $estados_finales[] = 'cancelado';
        }
    }
    
    // Eliminar duplicados y ordenar
    $estados_finales = array_unique($estados_finales);
    sort($estados_finales);
    
    return $estados_finales;
}

/**
 * Renderiza el formulario de estado del pedido
 * 
 * @param string $estado_actual_modal Estado actual normalizado
 * @param string|null $estado_pago Estado normalizado del pago (opcional, para filtrar opciones)
 * @return void
 */
function renderFormularioEstadoPedido($estado_actual_modal, $estado_pago = null) {
    $info_estado_pedido_actual = obtenerInfoEstadoPedido($estado_actual_modal);
    
    // Obtener transiciones válidas según estado del pago
    $transiciones_validas = obtenerTransicionesValidasPedido($estado_actual_modal, $estado_pago);
    
    // Obtener mapeo de estados para mostrar nombres
    $mapeo_estados = obtenerMapeoEstadosPedido();
    
    // ========================================================================
    // DETECCIÓN DE INCONSISTENCIAS
    // ========================================================================
    $inconsistencias = detectarInconsistenciasEstado($estado_actual_modal, $estado_pago);
    $hay_inconsistencia = $inconsistencias['hay_inconsistencia'];
    
    // ========================================================================
    // DETERMINAR MENSAJES DE RESTRICCIÓN
    // ========================================================================
    $estado_pago_norm = $estado_pago ? normalizarEstado($estado_pago) : null;
    $hay_restricciones = false;
    $mensaje_restriccion = '';
    $tipo_restriccion = 'info'; // info, warning, danger
    
    // Cargar StateValidator para verificar estados terminales
    require_once __DIR__ . '/state_validator.php';
    
    // Verificar si es estado terminal para deshabilitar el SELECT
    $select_pedido_disabled = StateValidator::isTerminal($estado_actual_modal, 'pedido');
    
    // Verificar si es estado terminal
    if ($select_pedido_disabled) {
        $hay_restricciones = true;
        $tipo_restriccion = 'warning';
        // completado es completamente terminal (no admite cambios, devolución no implementada en MVP)
        $mensaje_restriccion = 'Este pedido está en estado final (terminal) y no puede modificarse.';
    }
    // Verificar estados avanzados sin pago aprobado
    // NOTA: devolucion no está implementado en MVP
    elseif (in_array($estado_actual_modal, ['preparacion', 'en_viaje']) && 
            $estado_pago_norm !== 'aprobado') {
        $hay_restricciones = true;
        $tipo_restriccion = $hay_inconsistencia && $inconsistencias['tipo'] === 'danger' ? 'danger' : 'warning';
        $mensaje_restriccion = 'El pedido está en estado avanzado pero el pago no está aprobado. Debe cancelarse para corregir la inconsistencia.';
    }
    // Pago pendiente/pendiente_aprobacion
    elseif (!$estado_pago_norm || 
            $estado_pago_norm === 'pendiente' || 
            $estado_pago_norm === 'pendiente_aprobacion') {
        $hay_restricciones = true;
        $tipo_restriccion = 'info';
        $mensaje_restriccion = 'El pedido solo puede estar en Pendiente o Cancelado mientras el pago no esté aprobado.';
    }
    // Pago rechazado/cancelado
    elseif ($estado_pago_norm === 'rechazado' || $estado_pago_norm === 'cancelado') {
        $hay_restricciones = true;
        $tipo_restriccion = 'warning';
        $mensaje_restriccion = 'El pedido debe estar cancelado cuando el pago está rechazado o cancelado.';
    }
    
    ?>
    <div class="mb-3">
        <label class="form-label"><strong>Estado del Pedido:</strong></label>
        <div class="mb-2">
            <span class="badge bg-<?= htmlspecialchars($info_estado_pedido_actual['color']) ?>">
                <i class="fas fa-info-circle me-1"></i>Estado Actual: <?= htmlspecialchars($info_estado_pedido_actual['nombre']) ?>
            </span>
        </div>
        
        <?php if ($hay_inconsistencia): ?>
        <div class="alert alert-<?= $inconsistencias['tipo'] === 'danger' ? 'danger' : 'warning' ?> mb-2">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong><?= $inconsistencias['severidad'] === 'CRÍTICA' ? 'INCONSISTENCIA CRÍTICA' : 'Advertencia' ?>:</strong>
            <?= htmlspecialchars($inconsistencias['mensaje']) ?>
            <?php if (!empty($inconsistencias['accion_sugerida'])): ?>
            <br><small><i class="fas fa-lightbulb me-1"></i><?= htmlspecialchars($inconsistencias['accion_sugerida']) ?></small>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <select class="form-select" name="nuevo_estado" <?= $select_pedido_disabled ? 'disabled' : 'required' ?>>
            <?php 
            // Ordenar opciones de forma lógica
            // NOTA: devolucion no está implementado en MVP, no se incluye en el orden
            $orden_estados = ['pendiente', 'preparacion', 'en_viaje', 'completado', 'cancelado'];
            $estados_ordenados = [];
            
            // Primero: estado actual
            if (in_array($estado_actual_modal, $transiciones_validas)) {
                $estados_ordenados[] = $estado_actual_modal;
            }
            
            // Segundo: siguientes estados naturales (en orden lógico)
            foreach ($orden_estados as $estado_orden) {
                if (in_array($estado_orden, $transiciones_validas) && 
                    $estado_orden !== $estado_actual_modal && 
                    $estado_orden !== 'cancelado') {
                    $estados_ordenados[] = $estado_orden;
                }
            }
            
            // Tercero: cancelar (si aplica)
            if (in_array('cancelado', $transiciones_validas)) {
                $estados_ordenados[] = 'cancelado';
            }
            
            // Renderizar opciones ordenadas
            foreach ($estados_ordenados as $estado_valido): 
                $info_estado = isset($mapeo_estados[$estado_valido]) 
                    ? $mapeo_estados[$estado_valido] 
                    : ['nombre' => ucfirst(str_replace('_', ' ', $estado_valido))];
            ?>
                <option value="<?= htmlspecialchars($estado_valido) ?>" 
                        <?= $estado_actual_modal === $estado_valido ? 'selected' : '' ?>>
                    <?= htmlspecialchars($info_estado['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <?php if ($hay_restricciones): ?>
        <small class="text-<?= $tipo_restriccion ?> d-block mt-1">
            <i class="fas fa-info-circle me-1"></i>
            <?= htmlspecialchars($mensaje_restriccion) ?>
        </small>
        <?php endif; ?>
        
        <?php if ($estado_actual_modal !== 'cancelado' && in_array('cancelado', $transiciones_validas)): ?>
        <small class="text-muted d-block mt-1">
            <i class="fas fa-info-circle me-1"></i>
            Si cancela el pedido, el stock será restaurado automáticamente.
        </small>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Renderiza el formulario de información del pago
 * 
 * @param int $id_pedido ID del pedido
 * @param array $pago_modal Información del pago
 * @return void
 */
function renderFormularioPago($id_pedido, $pago_modal, $estado_pedido_actual = null) {
    $estado_pago_actual_modal = normalizarEstado($pago_modal['estado_pago'] ?? '');
    $info_estado_pago_actual = obtenerInfoEstadoPago($pago_modal['estado_pago'] ?? '');
    ?>
    <input type="hidden" name="estado_pago_anterior" value="<?= htmlspecialchars($estado_pago_actual_modal) ?>">
    
    <hr class="my-3">
    <h6 class="mb-3"><i class="fas fa-credit-card me-2"></i>Información del Pago</h6>
    
    <?php 
    renderFormularioEstadoPago($id_pedido, $estado_pago_actual_modal, $info_estado_pago_actual, $estado_pedido_actual);
    renderFormularioMontoPago($pago_modal);
    renderFormularioCodigoPago($id_pedido, $pago_modal);
    renderFormularioMotivoRechazo($id_pedido, $pago_modal);
    ?>
    <?php
}

/**
 * Obtiene las transiciones válidas de estado de pago según el estado actual
 * 
 * Filtra las transiciones permitidas según la matriz de estados usando StateValidator.
 * 
 * @param string $estado_pago_actual Estado actual normalizado del pago
 * @return array Array de estados válidos para transición (incluye el estado actual)
 */
function obtenerTransicionesValidasPago($estado_pago_actual) {
    // Cargar StateValidator si no está disponible
    require_once __DIR__ . '/state_validator.php';
    
    // Normalizar estado
    $estado_pago_norm = normalizarEstado($estado_pago_actual);
    
    // Obtener matriz de transiciones base desde StateValidator
    $transiciones_base = StateValidator::getTransitionMatrix('pago');
    
    // Obtener transiciones permitidas desde el estado actual
    $transiciones_permitidas = [];
    if (isset($transiciones_base[$estado_pago_norm])) {
        $transiciones_permitidas = $transiciones_base[$estado_pago_norm];
    }
    
    // Siempre incluir el estado actual (para mantener el mismo estado)
    if (!in_array($estado_pago_norm, $transiciones_permitidas)) {
        $transiciones_permitidas[] = $estado_pago_norm;
    }
    
    // Eliminar duplicados y ordenar
    $transiciones_permitidas = array_unique($transiciones_permitidas);
    sort($transiciones_permitidas);
    
    return $transiciones_permitidas;
}

/**
 * Renderiza el selector de estado del pago
 * 
 * Solo muestra opciones válidas según las transiciones permitidas desde el estado actual.
 * 
 * @param int $id_pedido ID del pedido
 * @param string $estado_pago_actual_modal Estado actual normalizado del pago
 * @param array $info_estado_pago_actual Información del estado del pago
 * @return void
 */
function renderFormularioEstadoPago($id_pedido, $estado_pago_actual_modal, $info_estado_pago_actual, $estado_pedido_actual = null) {
    // Obtener transiciones válidas según el estado actual
    $transiciones_validas = obtenerTransicionesValidasPago($estado_pago_actual_modal);
    
    // Obtener mapeo de estados para mostrar nombres
    $mapeo_estados = obtenerMapeoEstadosPago();
    
    // Cargar StateValidator para verificar estados terminales
    require_once __DIR__ . '/state_validator.php';
    
    // Determinar si el SELECT debe estar deshabilitado
    // Deshabilitar si: pago es terminal, pedido es terminal, pago está aprobado, o pedido está completado
    $estado_pedido_norm = $estado_pedido_actual ? normalizarEstado($estado_pedido_actual) : null;
    $pago_es_terminal = StateValidator::isTerminal($estado_pago_actual_modal, 'pago');
    $pedido_es_terminal = $estado_pedido_norm ? StateValidator::isTerminal($estado_pedido_norm, 'pedido') : false;
    $select_disabled = $pago_es_terminal || $pedido_es_terminal || ($estado_pago_actual_modal === 'aprobado') || ($estado_pedido_norm === 'completado');
    
    ?>
    <div class="mb-3">
        <label class="form-label"><strong>Estado del Pago:</strong></label>
        <div class="mb-2">
            <span class="badge bg-<?= htmlspecialchars($info_estado_pago_actual['color']) ?>">
                <i class="fas fa-info-circle me-1"></i>Estado Actual: <?= htmlspecialchars($info_estado_pago_actual['nombre']) ?>
            </span>
        </div>
        <select class="form-select" name="nuevo_estado_pago" id="nuevo_estado_pago_<?= $id_pedido ?>" <?= $select_disabled ? 'disabled' : '' ?>>
            <option value="">-- Mantener estado actual --</option>
            <?php
            // Ordenar opciones de forma lógica
            $orden_estados = ['pendiente', 'pendiente_aprobacion', 'aprobado', 'rechazado', 'cancelado'];
            $estados_ordenados = [];
            
            // Primero: estado actual
            if (in_array($estado_pago_actual_modal, $transiciones_validas)) {
                $estados_ordenados[] = $estado_pago_actual_modal;
            }
            
            // Segundo: siguientes estados naturales (en orden lógico)
            foreach ($orden_estados as $estado_orden) {
                if (in_array($estado_orden, $transiciones_validas) && 
                    $estado_orden !== $estado_pago_actual_modal) {
                    $estados_ordenados[] = $estado_orden;
                }
            }
            
            // Renderizar opciones ordenadas
            foreach ($estados_ordenados as $estado_valido): 
                $info_estado = isset($mapeo_estados[$estado_valido]) 
                    ? $mapeo_estados[$estado_valido] 
                    : ['nombre' => ucfirst(str_replace('_', ' ', $estado_valido))];
            ?>
                <option value="<?= htmlspecialchars($estado_valido) ?>" 
                        <?= $estado_pago_actual_modal === $estado_valido ? 'selected' : '' ?>>
                    <?= htmlspecialchars($info_estado['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($select_disabled): ?>
        <small class="text-warning d-block mt-1">
            <i class="fas fa-lock me-1"></i>
            <?php if ($pago_es_terminal): ?>
                El pago está en estado terminal (<?= htmlspecialchars($estado_pago_actual_modal) ?>) y no puede modificarse.
            <?php elseif ($pedido_es_terminal): ?>
                El pedido está en estado terminal (<?= htmlspecialchars($estado_pedido_norm) ?>) y no permite cambios en el estado del pago.
            <?php elseif ($estado_pago_actual_modal === 'aprobado'): ?>
                El pago aprobado no puede cambiar de estado.
            <?php elseif ($estado_pedido_norm === 'completado'): ?>
                El pedido completado no permite cambios en el estado del pago.
            <?php endif; ?>
        </small>
        <?php else: ?>
        <small class="text-muted d-block mt-1">
            <i class="fas fa-info-circle me-1"></i>
            Al aprobar el pago, el stock se descontará automáticamente. Al rechazar/cancelar, se restaurará si había sido descontado.
            <?php if ($estado_pago_actual_modal !== 'pendiente'): ?>
            <br><i class="fas fa-exclamation-triangle me-1 text-warning"></i>
            <strong>Importante:</strong> Un pago que sale de "Pendiente" NO puede cancelarse bajo ningún motivo. Solo puede avanzar o ser rechazado.
            <?php endif; ?>
        </small>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Renderiza el campo de monto del pago (solo lectura)
 * 
 * @param array $pago_modal Información del pago
 * @return void
 */
function renderFormularioMontoPago($pago_modal) {
    ?>
    <div class="mb-3">
        <label class="form-label"><strong>Monto del Pago:</strong></label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="text" class="form-control" 
                   value="<?= number_format($pago_modal['monto'] ?? 0, 2, ',', '.') ?>" 
                   readonly style="background-color: #e9ecef;">
        </div>
        <small class="text-muted">Monto ingresado por el cliente.</small>
    </div>
    <?php
}

/**
 * Renderiza el campo de código de pago (si existe y está aprobado)
 * 
 * @param int $id_pedido ID del pedido
 * @param array $pago_modal Información del pago
 * @return void
 */
function renderFormularioCodigoPago($id_pedido, $pago_modal) {
    $estado_pago_actual = normalizarEstado($pago_modal['estado_pago'] ?? '');
    $mostrar_codigo_pago = ($estado_pago_actual === 'aprobado' && !empty($pago_modal['numero_transaccion']));
    
    if (!$mostrar_codigo_pago) {
        return;
    }
    ?>
    <div class="mb-3" id="codigo_pago_container_<?= $id_pedido ?>">
        <label class="form-label"><strong>Código de Pago:</strong></label>
        <input type="text" class="form-control" 
               value="<?= htmlspecialchars($pago_modal['numero_transaccion'] ?? '') ?>" 
               readonly style="background-color: #e9ecef;">
        <small class="text-muted">Código ingresado por el cliente.</small>
    </div>
    <?php
}

/**
 * Renderiza el campo de motivo de rechazo
 * 
 * @param int $id_pedido ID del pedido
 * @param array $pago_modal Información del pago
 * @return void
 */
function renderFormularioMotivoRechazo($id_pedido, $pago_modal) {
    $estado_pago_actual = normalizarEstado($pago_modal['estado_pago'] ?? '');
    $mostrar_motivo_rechazo = ($estado_pago_actual === 'rechazado');
    ?>
    <div class="mb-3" id="motivo_rechazo_container_<?= $id_pedido ?>" style="display: <?= $mostrar_motivo_rechazo ? 'block' : 'none' ?>;">
        <label class="form-label"><strong>Motivo de Rechazo:</strong></label>
        <textarea class="form-control" name="motivo_rechazo" id="motivo_rechazo_<?= $id_pedido ?>" rows="2" maxlength="500" placeholder="Motivo del rechazo"><?= htmlspecialchars($pago_modal['motivo_rechazo'] ?? '') ?></textarea>
        <small class="text-muted">Opcional. Motivo del rechazo del pago. Máximo 500 caracteres.</small>
        <small class="form-text text-muted mt-1" id="contador_motivo_rechazo_<?= $id_pedido ?>">0/500 caracteres</small>
    </div>
    <?php
}

/**
 * Renderiza alerta cuando no hay pago registrado
 * 
 * @return void
 */
function renderAlertaSinPago() {
    ?>
    <hr class="my-3">
    <div class="alert alert-warning mb-3">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>No hay pago registrado para este pedido</strong>
    </div>
    <?php
}

/**
 * Renderiza formularios adicionales (aprobar/rechazar pago)
 * 
 * @param int $id_pedido ID del pedido
 * @param array $pago_modal Información del pago
 * @return void
 */
function renderFormulariosAdicionales($id_pedido, $pago_modal) {
    ?>
    <!-- Formulario oculto para aprobar pago -->
    <form id="aprobar_pago_<?= $id_pedido ?>" method="POST" action="" style="display: none;">
        <input type="hidden" name="pago_id" value="<?= $pago_modal['id_pago'] ?>">
        <input type="hidden" name="aprobar_pago" value="1">
    </form>
    
    <!-- Modal para rechazar pago -->
    <div class="modal fade" id="rechazarPagoModal<?= $id_pedido ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Rechazar Pago - Pedido #<?= $id_pedido ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="pago_id" value="<?= $pago_modal['id_pago'] ?>">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>¿Estás seguro?</strong> Al rechazar el pago, el stock será restaurado si había sido descontado.
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><strong>Motivo del rechazo (opcional):</strong></label>
                            <textarea class="form-control" name="motivo_rechazo" id="motivo_rechazo_modal_<?= $id_pedido ?>" rows="3" maxlength="500" placeholder="Ej: Pago insuficiente, Tarjeta rechazada, etc."></textarea>
                            <small class="form-text text-muted mt-1">Máximo 500 caracteres.</small>
                            <small class="form-text text-muted mt-1" id="contador_motivo_rechazo_modal_<?= $id_pedido ?>">0/500 caracteres</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="rechazar_pago" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i>Rechazar Pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}

