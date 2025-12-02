<?php
/**
 * ========================================================================
 * FORMULARIO DE CONTACTO EMAIL (MAILGUN) - Tienda Seda y Lino
 * ========================================================================
 * Formulario de contacto que envía emails directamente mediante Mailgun
 * - Validación JavaScript en tiempo real
 * - Validación de caracteres permitidos
 * - Feedback visual al usuario
 * - Pre-llenado desde parámetros GET y sesión de usuario
 * 
 * NOTA: Requiere que la sesión esté iniciada (normalmente se incluye después de header.php
 * que ya incluye auth_check.php con inicialización de sesión)
 * ========================================================================
 */

// Obtener valores para pre-llenar el formulario
// Nombre y email desde sesión si el usuario está logueado
$nombre_prellenado = '';
$email_prellenado = '';
if (isset($_SESSION['nombre']) && isset($_SESSION['apellido'])) {
    $nombre_prellenado = htmlspecialchars(trim($_SESSION['nombre'] . ' ' . $_SESSION['apellido']), ENT_QUOTES, 'UTF-8');
}
if (isset($_SESSION['email'])) {
    $email_prellenado = htmlspecialchars(trim($_SESSION['email']), ENT_QUOTES, 'UTF-8');
}

// Asunto desde parámetro GET
$asunto_prellenado = isset($_GET['asunto']) ? htmlspecialchars(trim($_GET['asunto']), ENT_QUOTES, 'UTF-8') : '';

// Mensaje pre-llenado desde parámetro GET (para pedidos)
$mensaje_prellenado = '';
if (isset($_GET['pedido']) && !empty($_GET['pedido'])) {
    $numero_pedido = intval($_GET['pedido']);
    if ($numero_pedido > 0) {
        // Trim para eliminar espacios en blanco y asegurar que no haya problemas de validación
        $mensaje_prellenado = trim(htmlspecialchars("Hola, quiero realizar una consulta por el pedido #{$numero_pedido}", ENT_QUOTES, 'UTF-8'));
    }
}
?>

<section id="formulario-email" class="seccion-contacto-compact section-spaced">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="formulario-container border-success">
                    <h2 class="text-center mb-3 titulo-formulario-success">CONTÁCTANOS (Mailgun)</h2>
                    <p class="text-center mb-4 texto-contacto-compact">Formulario de prueba con envío directo por email.</p>
                    
                    <form class="formulario-email" method="POST" action="/procesar-contacto-email.php" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                               <label for="name-email" class="form-label label-contacto">Nombre</label>
                               <input type="text" class="form-control input-contacto" id="name-email" name="name" placeholder="Tu nombre" value="<?= $nombre_prellenado ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                               <label for="email-email" class="form-label label-contacto">Email</label>
                               <input type="email" class="form-control input-contacto" id="email-email" name="email" placeholder="tu@email.com" value="<?= $email_prellenado ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                           <label for="asunto-email" class="form-label label-contacto">Asunto</label>
                           <select class="form-control input-contacto" id="asunto-email" name="asunto">
                               <option value="">Selecciona un tema</option>
                               <option value="problema_pagina" <?= $asunto_prellenado === 'problema_pagina' ? 'selected' : '' ?>>Problema con Página</option>
                               <option value="problema_producto" <?= $asunto_prellenado === 'problema_producto' ? 'selected' : '' ?>>Problema con Producto</option>
                               <option value="problema_pago" <?= $asunto_prellenado === 'problema_pago' ? 'selected' : '' ?>>Problema con Pago</option>
                               <option value="problema_cuenta" <?= $asunto_prellenado === 'problema_cuenta' ? 'selected' : '' ?>>Problema con Cuenta (Clientes)</option>
                               <option value="problema_pedido" <?= $asunto_prellenado === 'problema_pedido' ? 'selected' : '' ?>>Problema con Pedido</option>
                           </select>
                        </div>
                        <div class="mb-3">
                          <label for="message-email" class="form-label label-contacto">Mensaje</label>
                          <textarea class="form-control input-contacto" id="message-email" name="message" rows="6" placeholder="Escribe tu mensaje aquí..."><?= $mensaje_prellenado ?></textarea>
                        </div>
                       <button type="submit" class="btn boton-formulario btn-success w-100">
                           <span class="button-text">Enviar Mensaje (Mailgun)</span>
                           <span class="button-loading d-none">Enviando...</span>
                       </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Validación del formulario email -->
<script src="includes/formulario-email.js"></script>

