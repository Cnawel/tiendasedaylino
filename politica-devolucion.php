<?php
/**
 * ========================================================================
 * PÁGINA POLÍTICA DE DEVOLUCIONES - Tienda Seda y Lino
 * ========================================================================
 * Página informativa sobre la política de devoluciones
 * - Explica condiciones y plazos para devoluciones
 * - Detalla el proceso de devolución
 * - Incluye formulario de contacto para gestionar solicitudes
 * 
 * Funciones principales:
 * - Página estática que muestra información sobre devoluciones
 * - Incluye formulario de contacto para solicitar devoluciones
 * 
 * Variables principales:
 * - $titulo_pagina: Título de la página
 * 
 * Tablas utilizadas: Ninguna (contenido estático)
 * ========================================================================
 */
session_start();

// Configurar título de la página
$titulo_pagina = 'Política de Devoluciones';
?>

<?php include 'includes/header.php'; ?>

<!-- Contenido de política de devoluciones -->
<main>
    <section class="secciones">
        <div class="container">
            <h1 class="titulo-productos text-center mb-5">POLÍTICA DE DEVOLUCIONES</h1>
            
            <div class="row justify-content-center">
                <div class="col-lg-10 col-md-12">
                    <p class="text-muted mb-4"><strong>Última actualización:</strong> Octubre 2025</p>
                    
                    <h3>1. Plazo para Devoluciones</h3>
                    <p class="mb-4">Podrás devolver tu compra dentro de los <strong>30 días</strong> de haber recibido el producto. El plazo se cuenta desde la fecha de entrega confirmada.</p>
                    
                    <h3>2. Condiciones para Devoluciones</h3>
                    <p class="mb-4">Para que una devolución sea aceptada, el producto debe cumplir con las siguientes condiciones:</p>
                    <ul class="mb-4">
                        <li>El producto no debe haber sido usado</li>
                        <li>Debe conservar todas las etiquetas originales</li>
                        <li>Debe estar en su empaque original (si aplica)</li>
                        <li>No debe presentar signos de uso, manchas, olores o daños</li>
                        <li>Debe incluir todos los accesorios o elementos que venían con el producto</li>
                    </ul>
                    
                    <h3>3. Productos No Devolubles</h3>
                    <p class="mb-4">No se aceptan devoluciones de productos que:</p>
                    <ul class="mb-4">
                        <li>Hayan sido usados o lavados</li>
                        <li>Presenten daños causados por el cliente</li>
                        <li>No conserven sus etiquetas originales</li>
                        <li>Hayan sido personalizados o modificados</li>
                        <li>Sean productos de oferta o liquidación (a menos que estén defectuosos)</li>
                    </ul>
                    
                    <h3>4. Productos Defectuosos</h3>
                    <p class="mb-4">Si recibes un producto defectuoso o con fallas de fabricación:</p>
                    <ul class="mb-4">
                        <li>Debes notificarnos dentro de un plazo razonable (preferentemente dentro de los 7 días de recibido)</li>
                        <li>Te ofreceremos cambiarlo por el mismo producto en perfecto estado</li>
                        <li>Si no tenemos stock disponible, te devolveremos el importe íntegro</li>
                        <li>Los gastos de envío del cambio o devolución corren por nuestra cuenta</li>
                    </ul>
                    
                    <h3>5. Proceso de Devolución</h3>
                    <p class="mb-4">Para solicitar una devolución, sigue estos pasos:</p>
                    <ol class="mb-4">
                        <li><strong>Contacta con nosotros:</strong> Completa el formulario al final de esta página o escríbenos a <a href="mailto:info.sedaylino@gmail.com">info.sedaylino@gmail.com</a> indicando el número de pedido y el motivo de la devolución.</li>
                        <li><strong>Espera nuestra respuesta:</strong> Revisaremos tu solicitud y te responderemos en un plazo máximo de 48 horas hábiles.</li>
                        <li><strong>Recibe la autorización:</strong> Si tu devolución es aprobada, te enviaremos las instrucciones para el envío del producto.</li>
                        <li><strong>Envía el producto:</strong> Deberás enviar el producto a la dirección que te indiquemos, en las condiciones especificadas.</li>
                        <li><strong>Recibe el reembolso o cambio:</strong> Una vez recibido y verificado el producto, procesaremos el reembolso o el envío del cambio según corresponda.</li>
                    </ol>
                    
                    <h3>6. Reembolsos</h3>
                    <p class="mb-4">Los reembolsos se procesarán de la siguiente manera:</p>
                    <ul class="mb-4">
                        <li>El reembolso se realizará utilizando el mismo método de pago que utilizaste para la compra original</li>
                        <li>El plazo para recibir el reembolso es de 5 a 10 días hábiles después de recibir y verificar el producto devuelto</li>
                        <li>Los costos de envío originales no son reembolsables, excepto en casos de productos defectuosos o errores de nuestra parte</li>
                        <li>Si elegiste cambio por otro producto, el envío del nuevo producto será sin costo adicional</li>
                    </ul>
                    
                    <h3>7. Cambios de Talle o Color</h3>
                    <p class="mb-4">Si deseas cambiar un producto por otro talle o color:</p>
                    <ul class="mb-4">
                        <li>El cambio está sujeto a disponibilidad de stock</li>
                        <li>Debes solicitarlo dentro del plazo de 30 días</li>
                        <li>El producto debe cumplir con todas las condiciones para devolución</li>
                        <li>Los gastos de envío del cambio corren por cuenta del cliente, salvo que el cambio sea por un producto defectuoso</li>
                    </ul>
                    
                    <h3>8. Devoluciones de Pedidos Parciales</h3>
                    <p class="mb-4">Si tu pedido incluía varios productos y deseas devolver solo algunos:</p>
                    <ul class="mb-4">
                        <li>Puedes solicitar la devolución de productos individuales</li>
                        <li>El reembolso se calculará proporcionalmente</li>
                        <li>Los costos de envío se ajustarán según corresponda</li>
                    </ul>
                    
                    <h3>9. Contacto para Devoluciones</h3>
                    <p class="mb-4">Para cualquier consulta sobre devoluciones o para iniciar el proceso, puedes contactarnos:</p>
                    <ul class="mb-4">
                        <li><strong>Email:</strong> <a href="mailto:info.sedaylino@gmail.com">info.sedaylino@gmail.com</a></li>
                        <li><strong>Formulario de contacto:</strong> Completa el formulario a continuación</li>
                    </ul>
                    <p class="mb-4">Incluye en tu solicitud el número de pedido y una descripción clara del motivo de la devolución para agilizar el proceso.</p>
                    
                    <div class="alert alert-warning mt-4">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Importante:</strong> No envíes productos de vuelta sin haber recibido nuestra autorización previa. Las devoluciones no autorizadas no serán procesadas.
                    </div>
                    
                    <div class="text-center mt-4 mb-5">
                        <a href="index.php" class="btn btn-perfil-home">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Inicio
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Formulario de contacto para devoluciones -->
    <section class="seccion-contacto-compact section-spaced">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-md-10">
                    <div class="formulario-container">
                        <h2 class="text-center mb-3">SOLICITAR DEVOLUCIÓN</h2>
                        <p class="text-center mb-4 texto-contacto-compact">Completa el siguiente formulario para iniciar el proceso de devolución. Te responderemos a la brevedad.</p>
                        
                        <?php
                        // Mostrar mensaje de éxito o error si existe
                        if (isset($_SESSION['mensaje_contacto'])) {
                            $tipo_mensaje = $_SESSION['mensaje_contacto_tipo'] ?? 'info';
                            $mensaje = $_SESSION['mensaje_contacto'];
                            echo '<div class="alert alert-' . htmlspecialchars($tipo_mensaje, ENT_QUOTES, 'UTF-8') . ' alert-dismissible fade show" role="alert">';
                            echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');
                            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                            echo '</div>';
                            // Limpiar mensaje después de mostrarlo
                            unset($_SESSION['mensaje_contacto']);
                            unset($_SESSION['mensaje_contacto_tipo']);
                        }
                        ?>
                        
                        <form class="formulario" method="POST" action="procesar-contacto.php" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                   <label for="name" class="form-label label-contacto">Nombre</label>
                                   <input type="text" class="form-control input-contacto" id="name" name="name" placeholder="Tu nombre" 
                                          value="<?php echo isset($_SESSION['nombre']) && isset($_SESSION['apellido']) ? htmlspecialchars(trim($_SESSION['nombre'] . ' ' . $_SESSION['apellido']), ENT_QUOTES, 'UTF-8') : ''; ?>" 
                                          required minlength="4" title="El nombre debe tener al menos 4 letras">
                                </div>
                                <div class="col-md-6 mb-3">
                                   <label for="email" class="form-label label-contacto">Email</label>
                                   <input type="email" class="form-control input-contacto" id="email" name="email" placeholder="tu@email.com" 
                                          value="<?php echo isset($_SESSION['email']) ? htmlspecialchars(trim($_SESSION['email']), ENT_QUOTES, 'UTF-8') : ''; ?>" 
                                          required title="Ingresa un email válido">
                                </div>
                            </div>
                            <div class="mb-3">
                               <label for="asunto" class="form-label label-contacto">Asunto</label>
                               <select class="form-control input-contacto" id="asunto" name="asunto" required title="Por favor, seleccioná un Asunto">
                                   <option value="">Selecciona un tema</option>
                                   <option value="problema_pedido" selected>Consulta sobre un pedido / Devolución</option>
                                   <option value="problema_producto">Consulta sobre un producto</option>
                                   <option value="problema_pago">Duda sobre un pago</option>
                               </select>
                            </div>
                            <div class="mb-3">
                              <label for="message" class="form-label label-contacto">Mensaje</label>
                              <textarea class="form-control input-contacto" id="message" name="message" rows="6" 
                                        placeholder="Por favor, incluye el número de pedido y el motivo de la devolución..." 
                                        required minlength="20" 
                                        title="Por favor, contános un poco más del tema para poder ayudarte mejor. (20 caracteres mínimo)"></textarea>
                            </div>
                           <button type="submit" class="btn boton-formulario w-100">
                               <span class="button-text">Enviar Solicitud</span>
                               <span class="button-loading d-none">Enviando...</span>
                           </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Validación del formulario de contacto -->
<script src="js/formulario-contacto.js"></script>

<?php include 'includes/footer.php'; render_footer(); ?>


