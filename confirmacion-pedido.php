<?php
/**
 * ========================================================================
 * CONFIRMACIÓN DE PEDIDO - Tienda Seda y Lino
 * ========================================================================
 * Página de confirmación después de procesar un pedido exitosamente
 * - Muestra resumen del pedido
 * - Información de envío
 * - Número de pedido para seguimiento
 * 
 * @author Tienda Seda y Lino
 * @version 1.0
 */

session_start();

// Configurar título de la página
$titulo_pagina = 'Confirmación de Pedido';

// Verificar que existe información del pedido
if (!isset($_SESSION['pedido_exitoso'])) {
    header('Location: index.php');
    exit;
}

$pedido = $_SESSION['pedido_exitoso'];
$email_enviado = isset($_SESSION['email_enviado']) ? $_SESSION['email_enviado'] : false;

// Limpiar datos del pedido de la sesión después de mostrarlos
// (comentado para permitir refrescar la página, se limpiará al navegar a otra página)
// unset($_SESSION['pedido_exitoso']);

?>

<?php include 'includes/header.php'; ?>

<!-- Contenido de confirmación -->
<main class="container my-5">
        <!-- Mensaje de éxito con animación -->
        <div class="text-center mb-5 success-animation">
            <i class="fas fa-check-circle check-icon"></i>
            <h1 class="mt-4 mb-3">¡Pedido Confirmado!</h1>
            <p class="lead text-muted">Tu pedido ha sido procesado exitosamente</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <!-- Alerta de Email -->
                <?php if ($email_enviado): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-envelope me-2"></i>
                    <strong>Email de confirmación enviado</strong><br>
                    Hemos enviado una copia de tu pedido a tu correo electrónico. 
                    Por favor, revisa tu bandeja de entrada y la carpeta de spam.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php else: ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>No se pudo enviar el email de confirmación</strong><br>
                    Tu pedido se procesó correctamente, pero hubo un problema al enviar el email. 
                    Puedes imprimir o guardar esta página como comprobante.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Información del pedido -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-receipt me-2"></i>
                            Detalles del Pedido
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <p class="mb-2">
                                    <strong>Número de Pedido:</strong><br>
                                    <span class="text-primary fs-4">#<?php echo str_pad($pedido['id_pedido'], 6, '0', STR_PAD_LEFT); ?></span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-2">
                                    <strong>Fecha:</strong><br>
                                    <?php echo htmlspecialchars($pedido['fecha']); ?>
                                </p>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Guarda tu número de pedido</strong> para realizar el seguimiento de tu compra.
                        </div>

                        <!-- Dirección de envío -->
                        <h6 class="mb-3 mt-4">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            Dirección de Envío
                        </h6>
                        <p class="text-muted mb-4">
                            <?php echo htmlspecialchars($pedido['direccion']); ?>
                        </p>

                        <!-- Productos del pedido -->
                        <h6 class="mb-3">
                            <i class="fas fa-box me-2"></i>
                            Productos (<?php echo count($pedido['productos']); ?>)
                        </h6>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th>Variante</th>
                                        <th class="text-center">Cantidad</th>
                                        <th class="text-end">Precio Unit.</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pedido['productos'] as $producto): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($producto['nombre_producto']); ?></td>
                                        <td>
                                            <small class="text-muted">
                                                Talla: <?php echo htmlspecialchars($producto['talle']); ?><br>
                                                Color: <?php echo htmlspecialchars($producto['color']); ?>
                                            </small>
                                        </td>
                                        <td class="text-center"><?php echo $producto['cantidad']; ?></td>
                                        <td class="text-end">$<?php echo number_format($producto['precio_unitario'], 2); ?></td>
                                        <td class="text-end fw-bold">$<?php echo number_format($producto['subtotal'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                        <td class="text-end"><strong>$<?php echo number_format($pedido['total'], 2); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Envío:</strong></td>
                                        <td class="text-end text-success"><strong>GRATIS</strong></td>
                                    </tr>
                                    <tr class="table-success">
                                        <td colspan="4" class="text-end"><h5 class="mb-0">Total:</h5></td>
                                        <td class="text-end"><h5 class="mb-0 text-primary">$<?php echo number_format($pedido['total'], 2); ?></h5></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Información adicional -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="mb-3">
                            <i class="fas fa-truck me-2"></i>
                            ¿Qué sigue?
                        </h5>
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <i class="fas fa-envelope text-primary me-2"></i>
                                <strong>Recibirás un email de confirmación</strong> con todos los detalles de tu pedido.
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-box text-primary me-2"></i>
                                <strong>Prepararemos tu pedido</strong> en las próximas 24-48 horas.
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-shipping-fast text-primary me-2"></i>
                                <strong>Envío gratis</strong> - Recibirás tu pedido en 3-5 días hábiles.
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-phone text-primary me-2"></i>
                                <strong>¿Dudas?</strong> Contáctanos en cualquier momento.
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-center mb-5">
                    <a href="index.php" class="btn btn-primary btn-lg px-5">
                        <i class="fas fa-home me-2"></i>
                        Volver al Inicio
                    </a>
                    <a href="perfil.php" class="btn btn-outline-secondary btn-lg px-5">
                        <i class="fas fa-user me-2"></i>
                        Ver Mi Perfil
                    </a>
                </div>

                <!-- Mensaje de agradecimiento -->
                <div class="text-center mb-5">
                    <div class="border-top border-bottom py-4">
                        <h4 class="mb-3">¡Gracias por tu compra!</h4>
                        <p class="text-muted mb-0">
                            En <strong>Seda y Lino</strong> trabajamos para ofrecerte la mejor calidad y servicio.<br>
                            Esperamos que disfrutes tu compra.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <footer class="footer-completo mt-5">
        <div class="container">
            <div class="row py-5">
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">SEDA Y LINO</h5>
                    <p class="footer-texto">Elegancia que viste tus momentos. Prendas únicas de seda y lino con calidad artesanal.</p>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Navegación</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-2">
                            <a href="index.php" class="footer-link">
                                <i class="fas fa-home me-2"></i>Inicio
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="nosotros.php" class="footer-link">
                                <i class="fas fa-users me-2"></i>Nosotros
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Productos</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Camisas" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Camisas
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Pantalones" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Pantalones
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Contacto</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-3">
                            <i class="fas fa-envelope me-2"></i>
                            <a href="mailto:info@sedaylino.com" class="footer-link">info@sedaylino.com</a>
                        </li>
                    </ul>
                </div>
            </div>

            <hr class="footer-divider">

            <div class="row py-3">
                <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                    <p class="footer-copyright mb-0">
                        <i class="fas fa-copyright me-1"></i> 2025 Seda y Lino. Todos los derechos reservados
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="terminos.php" class="footer-link-small me-3">Términos y Condiciones</a>
                    <a href="privacidad.php" class="footer-link-small">Política de Privacidad</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Limpiar datos del pedido de la sesión al navegar a otra página
        window.addEventListener('beforeunload', function() {
            // Solo si el usuario está navegando a otra página (no refrescando)
            if (performance.navigation.type !== 1) {
                fetch('limpiar-sesion-pedido.php', { method: 'POST' });
            }
        });
    </script>

<?php include 'includes/footer.php'; render_footer(); ?>

