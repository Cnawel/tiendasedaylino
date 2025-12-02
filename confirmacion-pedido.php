<?php
/**
 * ========================================================================
 * CONFIRMACIÓN DE PEDIDO - Tienda Seda y Lino
 * ========================================================================
 * Muestra la confirmación después de procesar un pedido exitosamente
 * - Resumen del pedido con todos los productos
 * - Número de pedido para seguimiento
 * - Información de envío y método de pago
 * - Animación de celebración
 * 
 * Funciones principales:
 * - Obtiene datos del pedido desde $_SESSION['pedido_exitoso']
 * - Muestra detalles completos del pedido
 * 
 * Variables principales:
 * - $pedido: Array con datos del pedido creado
 * 
 * Acceso: Solo se muestra si existe $_SESSION['pedido_exitoso']
 * Tablas utilizadas: Ninguna (solo muestra datos de sesión)
 * ========================================================================
 */

session_start();

require_once __DIR__ . '/config/database.php';

// Cargar funciones de perfil para parsear direcciones
require_once __DIR__ . '/includes/perfil_functions.php';

// Configurar título de la página
$titulo_pagina = 'Confirmación de Pedido';

// Verificar que existe información del pedido
if (!isset($_SESSION['pedido_exitoso'])) {
    header('Location: index.php');
    exit;
}

$pedido = $_SESSION['pedido_exitoso'];

?>

<?php include 'includes/header.php'; ?>

<!-- Canvas para animación de confetti -->
<canvas id="confetti-canvas"></canvas>

<style>
    /* Paleta de colores sépia/crema elegante */
    :root {
        --sepia-dark: #8B7355;
        --sepia-medium: #B8A082;
        --sepia-light: #D4C4A8;
        --sepia-cream: #E8DDD0;
        --sepia-soft: #F5E6D3;
        --sepia-text: #6B5D47;
    }

    /* Estilos para la página de confirmación */
    .success-container {
        min-height: auto;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        align-items: center;
        padding: 1.5rem 1rem;
        background: #F5E6D3;
    }
    
    @media (max-width: 768px) {
        .success-container {
            padding: 1rem 0.5rem;
        }
    }

    /* Check grande animado */
    .check-icon-large {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: #B8A082;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 2rem;
        box-shadow: 0 10px 30px #8B7355;
        animation: checkScale 0.6s ease-out;
        position: relative;
    }

    .check-icon-large::before {
        content: '';
        position: absolute;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: #B8A082;
        opacity: 0;
        animation: checkPulse 2s ease-out infinite;
    }

    .check-icon-large i {
        font-size: 4rem;
        color: white;
        z-index: 1;
        animation: checkDraw 0.8s ease-out 0.3s both;
    }

    @keyframes checkScale {
        0% {
            opacity: 0;
        }
        100% {
            opacity: 1;
        }
    }

    @keyframes checkPulse {
        0% {
            opacity: 0.7;
        }
        100% {
            opacity: 0;
        }
    }

    @keyframes checkDraw {
        0% {
            opacity: 0;
        }
        100% {
            opacity: 1;
        }
    }

    /* Título de éxito */
    .success-title {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--sepia-dark);
        margin-bottom: 2rem;
        animation: fadeInUp 0.6s ease-out 0.4s both;
        text-align: center;
    }
    
    @media (max-width: 768px) {
        .success-title {
            font-size: 2rem;
            margin-bottom: 1.5rem;
        }
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    /* Cards principales con animación */
    .info-card {
        animation: fadeInUp 0.6s ease-out both;
        transition: box-shadow 0.3s ease;
        border: 1px solid var(--sepia-light);
        background: var(--color-bg-white);
        border-radius: 12px;
        overflow: hidden;
    }

    .info-card:nth-child(1) { animation-delay: 0.8s; }
    .info-card:nth-child(2) { animation-delay: 1s; }

    .info-card:hover {
        box-shadow: 0 8px 25px #8B7355 !important;
    }
    
    .card-body {
        padding: 1.5rem;
    }
    
    @media (max-width: 768px) {
        .card-body {
            padding: 1rem;
        }
    }

    /* Headers de cards con color sépia */
    .card-header-sepia {
        background: #B8A082 !important;
        color: white !important;
        border-bottom: 2px solid var(--sepia-medium);
        padding: 1rem 1.5rem;
    }
    
    .card-header-sepia h5 {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0;
    }
    
    @media (max-width: 768px) {
        .card-header-sepia {
            padding: 0.75rem 1rem;
        }
        .card-header-sepia h5 {
            font-size: 1rem;
        }
    }

    /* Canvas de confetti */
    #confetti-canvas {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 9999;
    }

    /* Estilos para datos del pedido */
    .pedido-badge {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--sepia-dark);
        letter-spacing: 2px;
        margin-top: 0.25rem;
    }
    
    @media (max-width: 768px) {
        .pedido-badge {
            font-size: 1.25rem;
        }
    }

    .info-item {
        padding: 1rem 0;
        border-bottom: 1px solid var(--sepia-cream);
    }
    
    .info-item:first-child {
        padding-top: 0;
    }

    .info-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .info-label {
        font-weight: 600;
        color: var(--sepia-text);
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .info-value {
        color: var(--sepia-dark);
        font-size: 1rem;
        line-height: 1.5;
    }
    
    .info-value.fw-bold {
        font-weight: 700;
    }

    /* Productos */
    .producto-item {
        padding: 1.25rem;
        background: var(--sepia-soft);
        border-radius: 10px;
        margin-bottom: 1rem;
        border-left: 4px solid var(--sepia-medium);
        transition: all 0.3s ease;
    }
    
    .producto-item:hover {
        background: #E8DDD0;
    }
    
    .producto-item:last-child {
        margin-bottom: 0;
    }
    
    @media (max-width: 768px) {
        .producto-item {
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
    }

    /* Alerts con color sépia */
    .alert-sepia {
        background-color: var(--sepia-soft);
        border-color: var(--sepia-light);
        color: var(--sepia-text);
    }

    .alert-sepia-info {
        background-color: #E8DDD0;
        border-color: var(--sepia-medium);
        color: var(--sepia-text);
    }

    /* Botones de acción */
    .action-buttons {
        margin-top: 2rem;
        animation: fadeInUp 0.6s ease-out 1.2s both;
    }
    
    @media (max-width: 768px) {
        .action-buttons {
            flex-direction: column;
            width: 100%;
        }
        .action-buttons .btn {
            width: 100%;
        }
    }

    .btn-sepia {
        background: #B8A082;
        border: none;
        color: white;
        transition: all 0.3s ease;
    }

    .btn-sepia:hover {
        background: #A08F6F;
        box-shadow: 0 5px 15px #8B7355;
        color: white;
    }

    .btn-outline-sepia {
        border: 2px solid var(--sepia-medium);
        color: var(--sepia-dark);
        transition: all 0.3s ease;
    }

    .btn-outline-sepia:hover {
        background: var(--sepia-medium);
        color: white;
        border-color: var(--sepia-medium);
    }

    /* Texto con color sépia */
    .text-sepia {
        color: var(--sepia-dark) !important;
    }

    .text-sepia-primary {
        color: var(--sepia-dark) !important;
    }

    /* Estilos destacados para Método de Pago */
    .payment-method-highlight {
        background: #F5E6D3;
        border: 2px solid var(--sepia-medium);
        border-radius: 12px;
        padding: 1.75rem;
        margin-top: 0;
        margin-bottom: 2rem;
        box-shadow: 0 4px 15px #8B7355;
        position: relative;
        overflow: hidden;
    }
    
    @media (max-width: 768px) {
        .payment-method-highlight {
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
    }

    .payment-method-highlight::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--sepia-medium);
    }

    .payment-label {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--sepia-dark) !important;
        margin-bottom: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .payment-label i {
        font-size: 1.3rem;
        color: var(--sepia-dark);
    }

    .payment-method-name {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--sepia-dark) !important;
        margin-top: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        line-height: 1.3;
    }
    
    @media (max-width: 768px) {
        .payment-method-name {
            font-size: 1.25rem;
            letter-spacing: 1px;
        }
    }

    .payment-details-box {
        background: var(--color-bg-white);
        border: 1px solid var(--sepia-light);
        border-left: 4px solid var(--sepia-medium);
        border-radius: 8px;
        padding: 1.25rem;
        margin-top: 1rem;
        box-shadow: 0 2px 8px #8B7355;
    }

    .payment-details-label {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--sepia-text) !important;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .payment-details-text {
        font-size: 1rem;
        font-weight: 600;
        color: var(--sepia-dark) !important;
        line-height: 1.6;
        word-break: break-word;
    }
    
    /* Mensaje de agradecimiento responsive */
    @media (max-width: 768px) {
        .text-center.mt-5 h4 {
            font-size: 1.25rem !important;
        }
        .text-center.mt-5 p {
            font-size: 0.9rem !important;
        }
    }
</style>

<!-- Contenido de confirmación -->
<main class="container my-4">
    <div class="success-container">
        <!-- Título de éxito -->
        <h1 class="success-title">¡Pedido Confirmado!</h1>

        <div class="row g-4 w-100" style="max-width: 1200px; margin: 0 auto;">
            
            <!-- Cuadro 1: Información del Pedido y Datos de Envío -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100 info-card">
                    <div class="card-header card-header-sepia">
                        <h5 class="mb-0">
                            <i class="fas fa-receipt me-2"></i>
                            Información del Pedido
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Método de Pago - Destacado al inicio -->
                        <div class="payment-method-highlight mb-4">
                            <div class="info-item mb-3">
                                <div class="info-label payment-label">
                                    <i class="fas fa-credit-card me-2"></i>Método de Pago
                                </div>
                                <div class="info-value payment-method-name"><?php echo htmlspecialchars($pedido['metodo_pago']); ?></div>
                            </div>
                            <?php if (!empty($pedido['metodo_pago_descripcion'])): ?>
                            <div class="payment-details-box">
                                <div class="info-label payment-details-label">Detalles del Pago</div>
                                <div class="info-value payment-details-text"><?php echo htmlspecialchars($pedido['metodo_pago_descripcion']); ?></div>
                            </div>
                            
                            
                            
                            <!-- Instrucciones para marcar el pago desde el perfil -->
                            <div class="alert alert-warning mt-2 mb-0" role="alert" style="background-color: #FFF3CD; border-left: 4px solid #FFC107; color: #000; border-radius: 8px;">
                                        <h6 class="mb-2 fw-bold" style="color: #856404;">
                                    <i class="fas fa-check-circle me-2"></i>¿Ya realizaste el pago?
                                        </h6>
                                <p class="mb-2 small">Para agilizar la confirmación del pedido <strong>#<?php echo str_pad($pedido['id_pedido'], 6, '0', STR_PAD_LEFT); ?></strong>, marcá tu pago como realizado:</p>
                                <ol class="mb-2 ps-3 small">
                                    <li>Andá a <a href="perfil.php?tab=pedidos" class="fw-bold">Mi Perfil → Mis Pedidos</a></li>
                                    <li>Buscá el pedido <strong>#<?php echo str_pad($pedido['id_pedido'], 6, '0', STR_PAD_LEFT); ?></strong></li>
                                    <li>Hacé clic en <strong>"Marcar Pago"</strong> e ingresá el código de transacción</li>
                                        </ol>
                                <p class="mb-0 small">
                                    <i class="fas fa-lightbulb me-1"></i><strong>Tip:</strong> Tené a mano el comprobante de pago.
                                        </p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Número de Pedido</div>
                            <div class="pedido-badge">#<?php echo str_pad($pedido['id_pedido'], 6, '0', STR_PAD_LEFT); ?></div>
                        </div>
                        
                        <hr class="my-4" style="border-color: var(--sepia-light);">
                        
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-map-marker-alt me-2"></i>Dirección de Envío
                            </div>
                            <div class="info-value">
                                <?php 
                                // Mostrar dirección completa con todos los datos
                                $direccion_parts = [];
                                
                                if (!empty($pedido['direccion'])) {
                                    $direccion_parts[] = htmlspecialchars($pedido['direccion']);
                                }
                                
                                if (!empty($pedido['localidad'])) {
                                    $direccion_parts[] = htmlspecialchars($pedido['localidad']);
                                }
                                
                                if (!empty($pedido['provincia'])) {
                                    $direccion_parts[] = htmlspecialchars($pedido['provincia']);
                                }
                                
                                if (!empty($pedido['codigo_postal'])) {
                                    $direccion_parts[] = 'CP: ' . htmlspecialchars($pedido['codigo_postal']);
                                }
                                
                                if (!empty($direccion_parts)) {
                                    echo implode(', ', $direccion_parts);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cuadro 2: Productos del Pedido -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100 info-card">
                    <div class="card-header card-header-sepia">
                        <h5 class="mb-0">
                            <i class="fas fa-box me-2"></i>
                            Productos (<?php echo count($pedido['productos']); ?>)
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto; padding-right: 0.5rem;">
                        <?php foreach ($pedido['productos'] as $producto): ?>
                        <div class="producto-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-bold text-sepia"><?php echo htmlspecialchars($producto['nombre_producto']); ?></h6>
                                    <small class="text-muted d-block">
                                        Talla: <?php echo htmlspecialchars($producto['talle']); ?> | 
                                        Color: <?php echo htmlspecialchars($producto['color']); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <strong class="text-sepia">$<?php echo number_format($producto['subtotal'], 2); ?></strong>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">Cantidad: <?php echo $producto['cantidad']; ?></small>
                                <small class="text-muted">$<?php echo number_format($producto['precio_unitario'], 2); ?> c/u</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="mt-4 pt-3 border-top" style="border-color: var(--sepia-light);">
                            <?php 
                            // Calcular subtotal (compatibilidad con pedidos antiguos)
                            $subtotal_pedido = isset($pedido['subtotal']) ? (float)$pedido['subtotal'] : (float)$pedido['total'];
                            
                            // Calcular costo de envío
                            if (isset($pedido['costo_envio'])) {
                                $costo_envio_pedido = (float)$pedido['costo_envio'];
                            } else {
                                // Calcular desde total y subtotal si no está disponible
                                $costo_envio_pedido = (float)$pedido['total'] - $subtotal_pedido;
                            }
                            
                            // Validar flag de envío gratis
                            if (isset($pedido['es_envio_gratis'])) {
                                $es_envio_gratis_pedido = (bool)$pedido['es_envio_gratis'];
                            } else {
                                // Fallback: si el costo es 0, es gratis
                                $es_envio_gratis_pedido = ($costo_envio_pedido == 0);
                            }
                            
                            // Asegurar consistencia: si es gratis, el costo debe ser 0
                            if ($es_envio_gratis_pedido && $costo_envio_pedido > 0) {
                                $costo_envio_pedido = 0;
                            }
                            // Si el costo es 0 pero no está marcado como gratis, mantener el costo en 0
                            if ($costo_envio_pedido == 0 && !$es_envio_gratis_pedido) {
                                $es_envio_gratis_pedido = true;
                            }
                            ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-sepia" style="font-size: 0.95rem;">Subtotal:</span>
                                <strong class="text-sepia" style="font-size: 1rem;">$<?php echo number_format($subtotal_pedido, 2); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-sepia" style="font-size: 0.95rem;">Envío:</span>
                                <?php if ($es_envio_gratis_pedido): ?>
                                <span class="fw-bold text-sepia" style="font-size: 1rem; color: var(--sepia-medium) !important;">GRATIS</span>
                                <?php else: ?>
                                <strong class="text-sepia" style="font-size: 1rem;">$<?php echo number_format($costo_envio_pedido, 2); ?></strong>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top" style="border-color: var(--sepia-medium); border-width: 2px;">
                                <h5 class="mb-0 text-sepia" style="font-size: 1.25rem; font-weight: 700;">Total:</h5>
                                <h5 class="mb-0 text-sepia" style="font-size: 1.5rem; font-weight: 800;">$<?php echo number_format($pedido['total'], 2); ?></h5>
                            </div>
                        </div>
                        
                        <!-- Mensaje informativo movido a la columna derecha -->
                        <div class="alert alert-sepia-info mt-4 mb-0" style="border-radius: 8px; padding: 1rem;">
                            <i class="fas fa-info-circle me-2"></i>
                            <small style="line-height: 1.6; font-size: 0.9rem;">
                                <strong>Importante:</strong> Guarda tu número de pedido para realizar el seguimiento de tu compra.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Botones de acción -->
        <div class="action-buttons d-grid gap-2 d-md-flex justify-content-md-center mt-5">
            <a href="index.php" class="btn btn-sepia btn-lg px-5 py-3">
                <i class="fas fa-home me-2"></i>
                Volver al Inicio
            </a>
            <a href="perfil.php?tab=pedidos" class="btn btn-outline-sepia btn-lg px-5 py-3">
                <i class="fas fa-user me-2"></i>
                Ver Mi Perfil
            </a>
        </div>

        <!-- Mensaje de agradecimiento -->
        <div class="text-center mt-5" style="max-width: 800px; margin-left: auto; margin-right: auto;">
            <div class="border-top border-bottom py-4" style="border-color: var(--sepia-light);">
                <h4 class="mb-3 text-sepia" style="font-size: 1.5rem; font-weight: 600;">¡Gracias por tu compra!</h4>
                <p class="mb-0" style="color: var(--sepia-text); line-height: 1.8; font-size: 1rem;">
                    En <strong>Seda y Lino</strong> trabajamos para ofrecerte la mejor calidad y servicio.<br>
                    Esperamos que disfrutes tu compra.
                </p>
            </div>
        </div>

    </div>
</main>

<!-- Script de animación de confetti -->
<script>
    // Función para crear confetti (solo una vez por pedido)
    function createConfetti() {
        // Obtener ID del pedido desde PHP
        const orderId = '<?php echo $pedido['id_pedido']; ?>';
        const storageKey = 'confettiShown_' + orderId;
        
        // Verificar si el confetti ya se mostró para este pedido usando sessionStorage
        const confettiShown = sessionStorage.getItem(storageKey);
        if (confettiShown === 'true') {
            // Si ya se mostró para este pedido, ocultar el canvas
            const canvas = document.getElementById('confetti-canvas');
            if (canvas) {
                canvas.style.display = 'none';
            }
            return;
        }
        
        // Marcar que el confetti se mostró para este pedido
        sessionStorage.setItem(storageKey, 'true');
        
        const canvas = document.getElementById('confetti-canvas');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        
        // Ajustar tamaño del canvas
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        
        const confetti = [];
        const confettiCount = 150;
        const gravity = 0.5;
        const terminalVelocity = 5;
        const drag = 0.075;
        
        // Colores sépia/crema para el confetti
        const colors = ['#B8A082', '#D4C4A8', '#E8DDD0', '#F5E6D3', '#C9B99B', '#A08F6F', '#8B7355', '#E8D4B8'];
        
        // Crear partículas de confetti
        for (let i = 0; i < confettiCount; i++) {
            confetti.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height - canvas.height,
                w: Math.random() * 10 + 5,
                h: Math.random() * 10 + 5,
                vx: Math.random() * 2 - 1,
                vy: Math.random() * 3 + 2,
                color: colors[Math.floor(Math.random() * colors.length)],
                rotation: Math.random() * 360,
                rotationSpeed: Math.random() * 10 - 5
            });
        }
        
        let animationId;
        let isStopping = false;
        
        // Función de animación
        function animate() {
            if (isStopping) return;
            
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            confetti.forEach((particle, index) => {
                // Actualizar posición
                particle.x += particle.vx;
                particle.y += particle.vy;
                particle.rotation += particle.rotationSpeed;
                
                // Aplicar gravedad y resistencia
                particle.vy += gravity;
                particle.vy = Math.min(particle.vy, terminalVelocity);
                particle.vx *= (1 - drag);
                
                // Si la partícula sale de la pantalla, no reiniciarla (dejar que caiga naturalmente)
                // Esto permite que el efecto se detenga limpiamente después de 3 segundos
                
                // Dibujar confetti
                ctx.save();
                ctx.translate(particle.x + particle.w / 2, particle.y + particle.h / 2);
                ctx.rotate((particle.rotation * Math.PI) / 180);
                ctx.fillStyle = particle.color;
                ctx.fillRect(-particle.w / 2, -particle.h / 2, particle.w, particle.h);
                ctx.restore();
            });
            
            animationId = requestAnimationFrame(animate);
        }
        
        // Iniciar animación
        animate();
        
        // Detener confetti después de 6 segundos y eliminar completamente
        setTimeout(() => {
            isStopping = true;
            if (animationId) {
                cancelAnimationFrame(animationId);
            }
            
            // Limpiar canvas y ocultar completamente
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            canvas.style.display = 'none';
            canvas.style.opacity = '0';
        }, 6000);
    }
    
    // Iniciar confetti cuando la página carga (solo si no se mostró antes)
    window.addEventListener('load', () => {
        setTimeout(createConfetti, 500);
    });
    
    // Ajustar canvas al redimensionar ventana
    window.addEventListener('resize', () => {
        const canvas = document.getElementById('confetti-canvas');
        if (canvas && canvas.style.display !== 'none') {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
    });
</script>

<?php include 'includes/footer.php'; render_footer(); ?>
