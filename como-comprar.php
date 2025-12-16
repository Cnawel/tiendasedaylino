<?php
/**
 * ========================================================================
 * PÁGINA CÓMO COMPRAR - Tienda Seda y Lino
 * ========================================================================
 * Página informativa sobre el proceso de compra
 * - Explica los pasos para realizar una compra
 * - Detalla métodos de pago disponibles
 * - Informa sobre envíos y entregas
 * 
 * Funciones principales:
 * - Página estática que muestra información sobre el proceso de compra
 * - No requiere procesamiento de datos
 * 
 * Variables principales:
 * - $titulo_pagina: Título de la página
 * 
 * Tablas utilizadas: Ninguna (contenido estático)
 * ========================================================================
 */
session_start();

// Configurar título de la página
$titulo_pagina = 'Cómo Comprar';
?>

<?php include 'includes/header.php'; ?>

<!-- Contenido de cómo comprar -->
<main>
    <section class="secciones">
        <div class="container">
            <h1 class="titulo-productos text-center mb-5">CÓMO COMPRAR</h1>
            
            <div class="row justify-content-center">
                <div class="col-lg-10 col-md-12">
                    <p class="text-muted mb-4"><strong>Última actualización:</strong> Octubre 2025</p>
                    
                    <h3>1. Navegar el Catálogo</h3>
                    <p class="mb-4">Explora nuestro catálogo de productos desde la página principal o usando los filtros por categoría, talle, género y color. Haz clic en cualquier producto para ver sus detalles completos, incluyendo todas las variantes disponibles (talles y colores).</p>
                    
                    <h3>2. Agregar Productos al Carrito</h3>
                    <p class="mb-4">Una vez que encuentres el producto que deseas:</p>
                    <ul class="mb-4">
                        <li>Selecciona el talle y color que prefieras</li>
                        <li>Elige la cantidad (máximo 10 unidades por variante)</li>
                        <li>Haz clic en "Agregar al Carrito"</li>
                        <li>Puedes seguir navegando y agregar más productos</li>
                    </ul>
                    
                    <h3>3. Revisar el Carrito</h3>
                    <p class="mb-4">Antes de finalizar tu compra, revisa los productos en tu carrito:</p>
                    <ul class="mb-4">
                        <li>Verifica que los productos, talles, colores y cantidades sean correctos</li>
                        <li>Puedes modificar las cantidades o eliminar productos si lo deseas</li>
                        <li>Revisa el total de tu compra</li>
                        <li>Cuando estés listo, haz clic en "Proceder al Checkout"</li>
                    </ul>
                    
                    <h3>4. Iniciar Sesión o Registrarse</h3>
                    <p class="mb-4">Para realizar una compra necesitas tener una cuenta en <strong>Seda y Lino</strong>:</p>
                    <ul class="mb-4">
                        <li>Si ya tienes cuenta, inicia sesión con tu email y contraseña</li>
                        <li>Si eres nuevo, regístrate completando el formulario con tus datos personales</li>
                        <li>El registro es rápido y seguro</li>
                    </ul>
                    
                    <h3>5. Confirmar Datos de Envío</h3>
                    <p class="mb-4">En el checkout, verifica o completa tus datos de envío:</p>
                    <ul class="mb-4">
                        <li>Dirección completa (calle, número, piso, departamento)</li>
                        <li>Código postal</li>
                        <li>Localidad y provincia</li>
                        <li>Teléfono de contacto</li>
                    </ul>
                    <p class="mb-4">El sistema calculará automáticamente el costo de envío según tu ubicación y el monto de tu compra.</p>
                    
                    <h3>6. Seleccionar Método de Pago</h3>
                    <p class="mb-4">Elige el método de pago que prefieras entre las opciones disponibles. Los métodos de pago aceptados se muestran en la página de checkout. No almacenamos datos de tu tarjeta en nuestro sitio.</p>
                    
                    <h3>7. Confirmar el Pedido</h3>
                    <p class="mb-4">Revisa una última vez todos los detalles de tu pedido:</p>
                    <ul class="mb-4">
                        <li>Productos y cantidades</li>
                        <li>Datos de envío</li>
                        <li>Método de pago seleccionado</li>
                        <li>Total a pagar (incluyendo envío)</li>
                    </ul>
                    <p class="mb-4">Si todo está correcto, haz clic en "Confirmar Pedido". Recibirás una confirmación por email con los detalles de tu compra.</p>
                    
                    <h3>8. Realizar el Pago</h3>
                    <p class="mb-4">Una vez confirmado el pedido, deberás realizar el pago según el método seleccionado. Puedes marcar tu pago como realizado desde tu perfil cuando hayas completado la transacción.</p>
                    
                    <h3>9. Seguimiento del Pedido</h3>
                    <p class="mb-4">Después de confirmar tu pedido y realizar el pago:</p>
                    <ul class="mb-4">
                        <li>Recibirás actualizaciones por email sobre el estado de tu pedido</li>
                        <li>Puedes ver el estado de todos tus pedidos desde tu perfil</li>
                        <li>El pedido pasará por diferentes estados: pendiente, preparación, en viaje, completado</li>
                    </ul>
                    
                    <h3>10. Recibir tu Compra</h3>
                    <p class="mb-4">Una vez que tu pedido esté en camino, recibirás información de seguimiento. Los plazos de entrega varían según tu ubicación y se te informará el tiempo estimado antes de finalizar tu compra.</p>
                    
                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>¿Necesitas ayuda?</strong> Si tienes alguna duda durante el proceso de compra, puedes contactarnos a través del <a href="index.php#contacto">formulario de contacto</a> o escribiéndonos a <a href="mailto:info.sedaylino@gmail.com">info.sedaylino@gmail.com</a>.
                    </div>
                    
                    <div class="text-center mt-5">
                        <a href="index.php" class="btn btn-perfil-home">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Inicio
                        </a>
                        <a href="catalogo.php?categoria=todos" class="btn btn-perfil-home ms-2">
                            <i class="fas fa-shopping-bag me-2"></i>Ver Catálogo
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; render_footer(); ?>




