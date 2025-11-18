<?php
/**
 * ========================================================================
 * FORMULARIO DE CONTACTO - Tienda Seda y Lino
 * ========================================================================
 * Formulario de contacto reutilizable que puede incluirse en cualquier página
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

// Verificar si hay un mensaje de éxito reciente (para no pre-llenar después de envío exitoso)
// Si hay un mensaje de éxito, no usar parámetros GET para pre-llenar
$ignorar_params_get = isset($_SESSION['mensaje_contacto_enviado']) && $_SESSION['mensaje_contacto_enviado'] === true;

// Asunto desde parámetro GET (solo si no hay envío exitoso reciente)
$asunto_prellenado = '';
if (!$ignorar_params_get && isset($_GET['asunto'])) {
    $asunto_prellenado = htmlspecialchars(trim($_GET['asunto']), ENT_QUOTES, 'UTF-8');
}

// Mensaje pre-llenado desde parámetro GET (para pedidos, solo si no hay envío exitoso reciente)
$mensaje_prellenado = '';
if (!$ignorar_params_get && isset($_GET['pedido']) && !empty($_GET['pedido'])) {
    $numero_pedido = intval($_GET['pedido']);
    if ($numero_pedido > 0) {
        // Trim para eliminar espacios en blanco y asegurar que no haya problemas de validación
        $mensaje_prellenado = trim(htmlspecialchars("Hola, quiero realizar una consulta por el pedido #{$numero_pedido}", ENT_QUOTES, 'UTF-8'));
    }
}
?>

<section id="contacto" class="seccion-contacto-compact section-spaced">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="formulario-container">
                    <h2 class="text-center mb-3">CONTÁCTANOS</h2>
                    <p class="text-center mb-4 texto-contacto-compact">¿Tienes alguna pregunta? Estamos aquí para ayudarte.</p>
                    
                    <form class="formulario" method="POST" action="/procesar-contacto.php" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                               <label for="name" class="form-label label-contacto">Nombre</label>
                               <input type="text" class="form-control input-contacto" id="name" name="name" placeholder="Tu nombre" value="<?= $nombre_prellenado ?>" required minlength="4" title="El nombre debe tener al menos 4 letras">
                            </div>
                            <div class="col-md-6 mb-3">
                               <label for="email" class="form-label label-contacto">Email</label>
                               <input type="email" class="form-control input-contacto" id="email" name="email" placeholder="tu@email.com" value="<?= $email_prellenado ?>" required title="Ingresa un email válido">
                            </div>
                        </div>
                        <div class="mb-3">
                           <label for="asunto" class="form-label label-contacto">Asunto</label>
                           <select class="form-control input-contacto" id="asunto" name="asunto" required title="Por favor, seleccioná un Asunto">
                               <option value="">Selecciona un tema</option>
                               <option value="problema_pagina" <?= $asunto_prellenado === 'problema_pagina' ? 'selected' : '' ?>>Inconveniente técnico sitio web</option>
                               <option value="problema_producto" <?= $asunto_prellenado === 'problema_producto' ? 'selected' : '' ?>>Consulta sobre un producto</option>
                               <option value="problema_pago" <?= $asunto_prellenado === 'problema_pago' ? 'selected' : '' ?>>Duda sobre un pago</option>
                               <option value="problema_cuenta" <?= $asunto_prellenado === 'problema_cuenta' ? 'selected' : '' ?>>Mi Cuenta</option>
                               <option value="problema_pedido" <?= $asunto_prellenado === 'problema_pedido' ? 'selected' : '' ?>>Consulta sobre un pedido</option>
                           </select>
                        </div>
                        <div class="mb-3">
                          <label for="message" class="form-label label-contacto">Mensaje</label>
                          <textarea class="form-control input-contacto" id="message" name="message" rows="6" placeholder="Escribe tu mensaje aquí..." required minlength="20" title="Por favor, contános un poco más del tema para poder ayudarte mejor. (20 caracteres mínimo)"><?= $mensaje_prellenado ?></textarea>
                        </div>
                       <button type="submit" class="btn boton-formulario w-100">
                           <span class="button-text">Enviar Mensaje</span>
                           <span class="button-loading d-none">Enviando...</span>
                       </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Validación del formulario de contacto -->
<script src="includes/formulario-contacto.js"></script>

