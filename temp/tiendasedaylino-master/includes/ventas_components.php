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
                        renderFormularioEstadoPedido($estado_actual_modal);
                        
                        if ($pago_modal) {
                            renderFormularioPago($id_pedido, $pago_modal);
                        } else {
                            renderAlertaSinPago();
                        }
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
 * Renderiza el formulario de estado del pedido
 * 
 * @param string $estado_actual_modal Estado actual normalizado
 * @return void
 */
function renderFormularioEstadoPedido($estado_actual_modal) {
    $info_estado_pedido_actual = obtenerInfoEstadoPedido($estado_actual_modal);
    ?>
    <div class="mb-3">
        <label class="form-label"><strong>Estado del Pedido:</strong></label>
        <div class="mb-2">
            <span class="badge bg-<?= htmlspecialchars($info_estado_pedido_actual['color']) ?>">
                <i class="fas fa-info-circle me-1"></i>Estado Actual: <?= htmlspecialchars($info_estado_pedido_actual['nombre']) ?>
            </span>
        </div>
        <select class="form-select" name="nuevo_estado" required>
            <option value="pendiente" <?= $estado_actual_modal === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
            <option value="pendiente_validado_stock" <?= $estado_actual_modal === 'pendiente_validado_stock' ? 'selected' : '' ?>>Pendiente (Stock Validado)</option>
            <option value="preparacion" <?= $estado_actual_modal === 'preparacion' ? 'selected' : '' ?>>Preparación</option>
            <option value="en_viaje" <?= $estado_actual_modal === 'en_viaje' ? 'selected' : '' ?>>En Viaje</option>
            <option value="completado" <?= $estado_actual_modal === 'completado' ? 'selected' : '' ?>>Completado</option>
            <option value="devolucion" <?= $estado_actual_modal === 'devolucion' ? 'selected' : '' ?>>Devolución</option>
            <option value="cancelado" <?= $estado_actual_modal === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
        </select>
        <?php if ($estado_actual_modal !== 'cancelado'): ?>
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
function renderFormularioPago($id_pedido, $pago_modal) {
    $estado_pago_actual_modal = normalizarEstado($pago_modal['estado_pago'] ?? '');
    $info_estado_pago_actual = obtenerInfoEstadoPago($pago_modal['estado_pago'] ?? '');
    ?>
    <input type="hidden" name="estado_pago_anterior" value="<?= htmlspecialchars($estado_pago_actual_modal) ?>">
    
    <hr class="my-3">
    <h6 class="mb-3"><i class="fas fa-credit-card me-2"></i>Información del Pago</h6>
    
    <?php 
    renderFormularioEstadoPago($id_pedido, $estado_pago_actual_modal, $info_estado_pago_actual);
    renderFormularioMontoPago($pago_modal);
    renderFormularioCodigoPago($id_pedido, $pago_modal);
    renderFormularioMotivoRechazo($id_pedido, $pago_modal);
    ?>
    <?php
}

/**
 * Renderiza el selector de estado del pago
 * 
 * @param int $id_pedido ID del pedido
 * @param string $estado_pago_actual_modal Estado actual normalizado del pago
 * @param array $info_estado_pago_actual Información del estado del pago
 * @return void
 */
function renderFormularioEstadoPago($id_pedido, $estado_pago_actual_modal, $info_estado_pago_actual) {
    ?>
    <div class="mb-3">
        <label class="form-label"><strong>Estado del Pago:</strong></label>
        <div class="mb-2">
            <span class="badge bg-<?= htmlspecialchars($info_estado_pago_actual['color']) ?>">
                <i class="fas fa-info-circle me-1"></i>Estado Actual: <?= htmlspecialchars($info_estado_pago_actual['nombre']) ?>
            </span>
        </div>
        <select class="form-select" name="nuevo_estado_pago" id="nuevo_estado_pago_<?= $id_pedido ?>">
            <option value="">-- Mantener estado actual --</option>
            <option value="pendiente" <?= $estado_pago_actual_modal === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
            <option value="pendiente_aprobacion" <?= $estado_pago_actual_modal === 'pendiente_aprobacion' ? 'selected' : '' ?>>Pendiente Aprobación</option>
            <option value="aprobado" <?= $estado_pago_actual_modal === 'aprobado' ? 'selected' : '' ?>>Aprobado</option>
            <option value="rechazado" <?= $estado_pago_actual_modal === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
            <option value="cancelado" <?= $estado_pago_actual_modal === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
        </select>
        <small class="text-muted d-block mt-1">
            <i class="fas fa-info-circle me-1"></i>
            Al aprobar el pago, el stock se descontará automáticamente. Al rechazar/cancelar, se restaurará si había sido descontado.
        </small>
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
        <textarea class="form-control" name="motivo_rechazo" rows="2" placeholder="Motivo del rechazo"><?= htmlspecialchars($pago_modal['motivo_rechazo'] ?? '') ?></textarea>
        <small class="text-muted">Opcional. Motivo del rechazo del pago.</small>
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
                            <textarea class="form-control" name="motivo_rechazo" rows="3" placeholder="Ej: Pago insuficiente, Tarjeta rechazada, etc."></textarea>
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

