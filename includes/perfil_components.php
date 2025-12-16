<?php
/**
 * ========================================================================
 * COMPONENTES REUTILIZABLES DE PERFIL - Tienda Seda y Lino
 * ========================================================================
 * Funciones para renderizar componentes HTML reutilizables del perfil
 * 
 * Funciones:
 * - renderFormularioMarcarPago($pago_pedido): Renderiza formulario para marcar pago como pagado
 * - renderFormularioCancelarPedido($pedido, $pago): Renderiza formulario para cancelar pedido
 * - renderModalEliminarCuenta(): Renderiza modal para eliminar cuenta
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

if (!function_exists('renderFormularioMarcarPago')) {
    /**
     * Renderiza formulario para marcar pago como pagado
     * 
     * @param array $pago_pedido Datos del pago
     * @return void
     */
    function renderFormularioMarcarPago($pago_pedido) {
        $id_pago = intval($pago_pedido['id_pago']);
        ?>
        <form method="POST" action="" class="d-inline-flex align-items-center gap-2 form-marcar-pago" data-id-pago="<?= $id_pago ?>">
            <input type="hidden" name="id_pago" value="<?= $id_pago ?>">
            <input type="text" 
                   class="form-control form-control-sm" 
                   name="numero_transaccion" 
                   placeholder="Código de pago" 
                   maxlength="100"
                   size="15"
                   pattern="[A-Za-z0-9\-_]+"
                   title="Solo letras, números, guiones y guiones bajos"
                   required>
            <button type="submit" name="marcar_pago_pagado" class="btn btn-sm btn-success">
                <i class="fas fa-check-circle me-1"></i>Marcar Pago
            </button>
        </form>
        <?php
    }
}

if (!function_exists('renderFormularioCancelarPedido')) {
    /**
     * Renderiza formulario para cancelar pedido
     * Solo se muestra cuando el pedido y el pago están en estado "Pendiente"
     * 
     * @param array $pedido Datos del pedido
     * @param array|null $pago Datos del pago (opcional)
     * @return void
     */
    function renderFormularioCancelarPedido($pedido, $pago = null) {
        $id_pedido = intval($pedido['id_pedido']);
        ?>
        <form method="POST" action="" class="d-inline">
            <input type="hidden" name="id_pedido" value="<?= $id_pedido ?>">
            <button type="submit" 
                    name="cancelar_pedido_cliente" 
                    class="btn btn-sm btn-secondary"
                    onclick="return confirm('¿Estás seguro de que deseas cancelar este pedido?');">
                <i class="fas fa-times-circle me-1"></i>Cancelar Pedido
            </button>
        </form>
        <?php
    }
}

if (!function_exists('renderModalVerPedido')) {
    /**
     * Renderiza modal para ver detalles completos de un pedido (para clientes)
     * 
     * @param mysqli $mysqli Conexión a la base de datos
     * @param array $pedido Datos básicos del pedido
     * @return void
     */
    function renderModalVerPedido($mysqli, $pedido) {
        // Obtener información completa del pedido
        // NOTA: Verificaciones function_exists() son necesarias porque esta función puede llamarse
        // desde diferentes contextos donde los archivos de queries pueden no estar cargados
        if (!function_exists('obtenerPedidoPorId')) {
            $pedido_queries_path = __DIR__ . '/queries/pedido_queries.php';
            if (!file_exists($pedido_queries_path)) {
                error_log("ERROR: No se pudo encontrar pedido_queries.php en " . $pedido_queries_path);
                die("Error crítico: Archivo de consultas de pedido no encontrado. Por favor, contacta al administrador.");
            }
            require_once $pedido_queries_path;
        }
        if (!function_exists('obtenerPagoPorPedido')) {
            $pago_queries_path = __DIR__ . '/queries/pago_queries.php';
            if (!file_exists($pago_queries_path)) {
                error_log("ERROR: No se pudo encontrar pago_queries.php en " . $pago_queries_path);
                die("Error crítico: Archivo de consultas de pago no encontrado. Por favor, contacta al administrador.");
            }
            require_once $pago_queries_path;
        }
        
        // Cargar helpers de estados si no están cargados
        if (!function_exists('obtenerInfoEstadoPedido')) {
            $estado_helpers_path = __DIR__ . '/estado_helpers.php';
            if (file_exists($estado_helpers_path)) {
                require_once $estado_helpers_path;
            }
        }
        
        $pedido_completo = obtenerPedidoPorId($mysqli, $pedido['id_pedido']);
        $detalles_pedido = obtenerDetallesPedido($mysqli, $pedido['id_pedido']);
        $pago_detalle = obtenerPagoPorPedido($mysqli, $pedido['id_pedido']);
        
        if (!$pedido_completo) {
            return; // No mostrar modal si no se encuentra el pedido
        }
        
        $id_pedido = intval($pedido['id_pedido']);
        
        // Obtener información del estado usando función centralizada
        $info_estado_detalle = obtenerInfoEstadoPedido($pedido_completo['estado_pedido'] ?? '');
        ?>
        <div class="modal fade" id="verPedidoModal<?= $id_pedido ?>" tabindex="-1" aria-labelledby="verPedidoModalLabel<?= $id_pedido ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="verPedidoModalLabel<?= $id_pedido ?>">
                            <i class="fas fa-shopping-cart me-2"></i>Detalles del Pedido #<?= $id_pedido ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Información del Pedido -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información del Pedido</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
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
                                    </div>
                                    <div class="col-md-6">
                                        <?php if (!empty($pedido_completo['observaciones'])): ?>
                                        <p class="mb-1"><strong>Observaciones:</strong><br>
                                            <small><?= nl2br(htmlspecialchars($pedido_completo['observaciones'])) ?></small>
                                        </p>
                                        <?php endif; ?>
                                        <?php if ($pago_detalle): ?>
                                        <p class="mb-1">
                                            <strong>Estado Pago:</strong> 
                                            <?php
                                            $info_estado_pago_detalle = obtenerInfoEstadoPago($pago_detalle['estado_pago'] ?? '');
                                            ?>
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
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Productos del Pedido -->
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
                                                <tr>
                                                    <td colspan="5" class="text-end"><strong>Total:</strong></td>
                                                    <td class="text-end"><strong class="text-primary">$<?= number_format($pedido_completo['total_pedido'] ?? $total_calculado, 2, ',', '.') ?></strong></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('renderModalEliminarCuenta')) {
    /**
     * Renderiza modal para eliminar cuenta
     * 
     * @return void
     */
    function renderModalEliminarCuenta() {
        ?>
        <div class="modal fade" id="modalEliminarCuenta" tabindex="-1" aria-labelledby="modalEliminarCuentaLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="modalEliminarCuentaLabel">
                            <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación de Cuenta
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <div class="alert alert-danger">
                                <strong>ESTE PROCESO NO TIENE VUELTA ATRAS</strong>
                            </div>
                            <p class="mb-3">Para confirmar la eliminación de tu cuenta, ingresa tu correo electrónico:</p>
                            <div class="mb-3">
                                <label for="email_confirmacion" class="form-label">
                                    <i class="fas fa-envelope me-1"></i>Correo Electrónico <span class="text-danger">*</span>
                                </label>
                                <input type="email" 
                                       class="form-control" 
                                       id="email_confirmacion" 
                                       name="email_confirmacion" 
                                       placeholder="tu@email.com" 
                                       required
                                       autocomplete="email">
                                <small class="text-muted">Debe coincidir con el correo electrónico de tu cuenta</small>
                            </div>
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Atención:</strong> Tu cuenta será desactivada inmediatamente. Tienes 30 días para reactivarla iniciando sesión. Después de ese período, será eliminada permanentemente.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </button>
                            <button type="submit" name="eliminar_cuenta" class="btn btn-danger">
                                <i class="fas fa-trash-alt me-2"></i>Eliminar Cuenta
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}

