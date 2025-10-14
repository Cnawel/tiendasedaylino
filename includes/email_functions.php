<?php
/**
 * ========================================================================
 * FUNCIONES DE EMAIL - Tienda Seda y Lino
 * ========================================================================
 * Funciones para envío de emails con PHPMailer
 * 
 * Funciones disponibles:
 * - enviar_email_confirmacion_pedido(): Envía confirmación de pedido al cliente
 * - enviar_email_simple(): Envía email simple (uso general)
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 */

// Cargar configuración de email
require_once __DIR__ . '/../config/email.php';

// Intentar cargar PHPMailer (con autoloader de Composer o manual)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    define('PHPMAILER_DISPONIBLE', true);
} else {
    define('PHPMAILER_DISPONIBLE', false);
}

// Declaraciones use para PHPMailer (deben estar al inicio, fuera de funciones)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envía email de confirmación de pedido al cliente
 * 
 * @param array $datos_pedido Datos del pedido (del procesamiento)
 * @param array $datos_usuario Datos del usuario
 * @return bool True si se envió correctamente, false si hubo error
 */
function enviar_email_confirmacion_pedido($datos_pedido, $datos_usuario) {
    // Verificar que el email esté habilitado
    if (!EMAIL_ENABLED) {
        error_log("Email deshabilitado en configuración");
        return false;
    }
    
    // Verificar que esté configurado
    if (!email_esta_configurado()) {
        error_log("Email no configurado correctamente");
        return false;
    }
    
    // Generar asunto
    $asunto = "Confirmación de Pedido #{$datos_pedido['id_pedido']} - Seda y Lino";
    
    // Generar cuerpo HTML del email
    $cuerpo_html = generar_template_confirmacion_pedido($datos_pedido, $datos_usuario);
    
    // Generar versión texto plano (alternativa)
    $cuerpo_texto = generar_texto_confirmacion_pedido($datos_pedido, $datos_usuario);
    
    // Enviar email
    return enviar_email(
        $datos_usuario['email'],
        $datos_usuario['nombre'] . ' ' . $datos_usuario['apellido'],
        $asunto,
        $cuerpo_html,
        $cuerpo_texto
    );
}

/**
 * Envía un email usando PHPMailer o función mail() nativa
 * 
 * @param string $destinatario Email del destinatario
 * @param string $nombre_destinatario Nombre del destinatario
 * @param string $asunto Asunto del email
 * @param string $cuerpo_html Cuerpo en formato HTML
 * @param string $cuerpo_texto Cuerpo en texto plano (opcional)
 * @return bool True si se envió correctamente, false si hubo error
 */
function enviar_email($destinatario, $nombre_destinatario, $asunto, $cuerpo_html, $cuerpo_texto = '') {
    
    // Si PHPMailer está disponible, usar PHPMailer
    if (PHPMAILER_DISPONIBLE && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return enviar_email_phpmailer($destinatario, $nombre_destinatario, $asunto, $cuerpo_html, $cuerpo_texto);
    } 
    // Si no, usar función mail() nativa de PHP
    else {
        return enviar_email_nativo($destinatario, $nombre_destinatario, $asunto, $cuerpo_html, $cuerpo_texto);
    }
}

/**
 * Envía email usando PHPMailer (recomendado)
 * 
 * @param string $destinatario Email del destinatario
 * @param string $nombre_destinatario Nombre del destinatario
 * @param string $asunto Asunto del email
 * @param string $cuerpo_html Cuerpo en formato HTML
 * @param string $cuerpo_texto Cuerpo en texto plano
 * @return bool True si se envió correctamente, false si hubo error
 */
function enviar_email_phpmailer($destinatario, $nombre_destinatario, $asunto, $cuerpo_html, $cuerpo_texto) {
    
    try {
        $mail = new PHPMailer(true);
        
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        // Debug (solo si está habilitado)
        $mail->SMTPDebug = EMAIL_DEBUG;
        
        // Remitente
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addReplyTo(EMAIL_REPLY_TO, EMAIL_FROM_NAME);
        
        // Destinatario
        $mail->addAddress($destinatario, $nombre_destinatario);
        
        // Copia oculta al admin (si está configurado)
        if (!empty(EMAIL_BCC_ADMIN)) {
            $mail->addBCC(EMAIL_BCC_ADMIN);
        }
        
        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = $cuerpo_html;
        $mail->AltBody = $cuerpo_texto ?: strip_tags($cuerpo_html);
        
        // Enviar
        $mail->send();
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error al enviar email con PHPMailer: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Envía email usando función mail() nativa de PHP (alternativa)
 * 
 * @param string $destinatario Email del destinatario
 * @param string $nombre_destinatario Nombre del destinatario
 * @param string $asunto Asunto del email
 * @param string $cuerpo_html Cuerpo en formato HTML
 * @param string $cuerpo_texto Cuerpo en texto plano (no usado en mail())
 * @return bool True si se envió correctamente, false si hubo error
 */
function enviar_email_nativo($destinatario, $nombre_destinatario, $asunto, $cuerpo_html, $cuerpo_texto) {
    
    // Headers
    $headers = array(
        'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM . '>',
        'Reply-To: ' . EMAIL_REPLY_TO,
        'Content-Type: text/html; charset=UTF-8',
        'MIME-Version: 1.0'
    );
    
    // BCC al admin si está configurado
    if (!empty(EMAIL_BCC_ADMIN)) {
        $headers[] = 'Bcc: ' . EMAIL_BCC_ADMIN;
    }
    
    // Enviar email
    $resultado = mail(
        $destinatario,
        $asunto,
        $cuerpo_html,
        implode("\r\n", $headers)
    );
    
    if (!$resultado) {
        error_log("Error al enviar email nativo a: $destinatario");
    }
    
    return $resultado;
}

/**
 * Genera el template HTML para email de confirmación de pedido
 * 
 * @param array $datos_pedido Datos del pedido
 * @param array $datos_usuario Datos del usuario
 * @return string HTML del email
 */
function generar_template_confirmacion_pedido($datos_pedido, $datos_usuario) {
    
    $id_pedido = str_pad($datos_pedido['id_pedido'], 6, '0', STR_PAD_LEFT);
    $fecha = $datos_pedido['fecha'];
    $direccion = $datos_pedido['direccion'];
    $total = number_format($datos_pedido['total'], 2);
    $productos = $datos_pedido['productos'];
    
    $nombre_completo = htmlspecialchars($datos_usuario['nombre'] . ' ' . $datos_usuario['apellido']);
    
    // Generar filas de productos
    $filas_productos = '';
    foreach ($productos as $producto) {
        $nombre = htmlspecialchars($producto['nombre_producto']);
        $talle = htmlspecialchars($producto['talle']);
        $color = htmlspecialchars($producto['color']);
        $cantidad = $producto['cantidad'];
        $precio = number_format($producto['precio_unitario'], 2);
        $subtotal = number_format($producto['subtotal'], 2);
        
        $filas_productos .= "
        <tr>
            <td style='padding: 12px; border-bottom: 1px solid #eee;'>
                <strong>$nombre</strong><br>
                <small style='color: #666;'>Talla: $talle | Color: $color</small>
            </td>
            <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: center;'>$cantidad</td>
            <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: right;'>\$${precio}</td>
            <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: right;'><strong>\$${subtotal}</strong></td>
        </tr>";
    }
    
    // Template HTML
    $html = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Confirmación de Pedido</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
        
        <!-- Header -->
        <div style='background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='margin: 0; font-size: 28px;'>SEDA Y LINO</h1>
            <p style='margin: 10px 0 0 0; font-size: 14px; opacity: 0.9;'>Elegancia que viste tus momentos</p>
        </div>
        
        <!-- Confirmación -->
        <div style='background: #f8f9fa; padding: 30px; text-align: center;'>
            <div style='background: white; display: inline-block; padding: 15px 30px; border-radius: 50px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>
                <span style='color: #28a745; font-size: 40px;'>✓</span>
            </div>
            <h2 style='color: #28a745; margin: 20px 0 10px 0;'>¡Pedido Confirmado!</h2>
            <p style='color: #666; margin: 0;'>Hola <strong>$nombre_completo</strong>, recibimos tu pedido correctamente.</p>
        </div>
        
        <!-- Detalles del Pedido -->
        <div style='background: white; padding: 30px; border-left: 4px solid #2c3e50;'>
            <h3 style='color: #2c3e50; margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px;'>
                📋 Detalles del Pedido
            </h3>
            
            <table style='width: 100%; margin-bottom: 20px;'>
                <tr>
                    <td style='padding: 8px 0;'><strong>Número de Pedido:</strong></td>
                    <td style='padding: 8px 0; text-align: right; color: #007bff; font-size: 18px;'><strong>#$id_pedido</strong></td>
                </tr>
                <tr>
                    <td style='padding: 8px 0;'><strong>Fecha:</strong></td>
                    <td style='padding: 8px 0; text-align: right;'>$fecha</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0;'><strong>Estado:</strong></td>
                    <td style='padding: 8px 0; text-align: right;'><span style='background: #ffc107; color: #856404; padding: 4px 12px; border-radius: 12px; font-size: 12px;'>PENDIENTE</span></td>
                </tr>
            </table>
            
            <h4 style='color: #2c3e50; margin-top: 30px; margin-bottom: 15px;'>📦 Productos</h4>
            <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                <thead>
                    <tr style='background: #f8f9fa;'>
                        <th style='padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;'>Producto</th>
                        <th style='padding: 12px; text-align: center; border-bottom: 2px solid #dee2e6;'>Cant.</th>
                        <th style='padding: 12px; text-align: right; border-bottom: 2px solid #dee2e6;'>Precio</th>
                        <th style='padding: 12px; text-align: right; border-bottom: 2px solid #dee2e6;'>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    $filas_productos
                </tbody>
                <tfoot>
                    <tr style='background: #f8f9fa;'>
                        <td colspan='3' style='padding: 15px; text-align: right; border-top: 2px solid #dee2e6;'><strong>Subtotal:</strong></td>
                        <td style='padding: 15px; text-align: right; border-top: 2px solid #dee2e6;'><strong>\$${total}</strong></td>
                    </tr>
                    <tr style='background: #f8f9fa;'>
                        <td colspan='3' style='padding: 15px; text-align: right;'><strong>Envío:</strong></td>
                        <td style='padding: 15px; text-align: right; color: #28a745;'><strong>GRATIS</strong></td>
                    </tr>
                    <tr style='background: #28a745; color: white;'>
                        <td colspan='3' style='padding: 15px; text-align: right; font-size: 18px;'><strong>TOTAL:</strong></td>
                        <td style='padding: 15px; text-align: right; font-size: 18px;'><strong>\$${total}</strong></td>
                    </tr>
                </tfoot>
            </table>
            
            <h4 style='color: #2c3e50; margin-top: 30px; margin-bottom: 15px;'>🚚 Dirección de Envío</h4>
            <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;'>
                <p style='margin: 0; color: #495057;'>$direccion</p>
            </div>
        </div>
        
        <!-- Próximos Pasos -->
        <div style='background: #e3f2fd; padding: 30px; border-radius: 5px; margin-top: 20px;'>
            <h3 style='color: #1976d2; margin-top: 0;'>📌 Próximos Pasos</h3>
            <ul style='color: #495057; padding-left: 20px;'>
                <li style='margin-bottom: 10px;'><strong>Prepararemos tu pedido</strong> en las próximas 24-48 horas.</li>
                <li style='margin-bottom: 10px;'><strong>Envío gratis</strong> - Recibirás tu pedido en 3-5 días hábiles.</li>
                <li style='margin-bottom: 10px;'><strong>Te avisaremos</strong> cuando tu pedido sea despachado.</li>
                <li><strong>Guarda este email</strong> como comprobante de tu compra.</li>
            </ul>
        </div>
        
        <!-- Información de Contacto -->
        <div style='background: white; padding: 20px; text-align: center; margin-top: 20px; border-top: 3px solid #2c3e50;'>
            <p style='color: #666; margin: 10px 0;'>¿Tienes alguna duda?</p>
            <p style='margin: 10px 0;'>
                <a href='mailto:info@sedaylino.com' style='color: #007bff; text-decoration: none;'>info@sedaylino.com</a>
            </p>
        </div>
        
        <!-- Footer -->
        <div style='background: #2c3e50; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; margin-top: 30px;'>
            <p style='margin: 0; font-size: 14px;'>Gracias por tu compra en <strong>Seda y Lino</strong></p>
            <p style='margin: 10px 0 0 0; font-size: 12px; opacity: 0.8;'>© 2025 Seda y Lino. Todos los derechos reservados.</p>
        </div>
        
    </body>
    </html>
    ";
    
    return $html;
}

/**
 * Genera versión en texto plano del email de confirmación
 * 
 * @param array $datos_pedido Datos del pedido
 * @param array $datos_usuario Datos del usuario
 * @return string Texto plano del email
 */
function generar_texto_confirmacion_pedido($datos_pedido, $datos_usuario) {
    
    $id_pedido = str_pad($datos_pedido['id_pedido'], 6, '0', STR_PAD_LEFT);
    $fecha = $datos_pedido['fecha'];
    $direccion = $datos_pedido['direccion'];
    $total = number_format($datos_pedido['total'], 2);
    $productos = $datos_pedido['productos'];
    
    $nombre_completo = $datos_usuario['nombre'] . ' ' . $datos_usuario['apellido'];
    
    $texto = "========================================\n";
    $texto .= "SEDA Y LINO - Confirmación de Pedido\n";
    $texto .= "========================================\n\n";
    
    $texto .= "¡Hola $nombre_completo!\n\n";
    $texto .= "Tu pedido ha sido confirmado exitosamente.\n\n";
    
    $texto .= "DETALLES DEL PEDIDO\n";
    $texto .= "-------------------\n";
    $texto .= "Número de Pedido: #$id_pedido\n";
    $texto .= "Fecha: $fecha\n";
    $texto .= "Estado: PENDIENTE\n\n";
    
    $texto .= "PRODUCTOS\n";
    $texto .= "---------\n";
    foreach ($productos as $producto) {
        $texto .= "- {$producto['nombre_producto']}\n";
        $texto .= "  Talla: {$producto['talle']} | Color: {$producto['color']}\n";
        $texto .= "  Cantidad: {$producto['cantidad']} x \$" . number_format($producto['precio_unitario'], 2) . "\n";
        $texto .= "  Subtotal: \$" . number_format($producto['subtotal'], 2) . "\n\n";
    }
    
    $texto .= "TOTAL: \$$total\n";
    $texto .= "Envío: GRATIS\n\n";
    
    $texto .= "DIRECCIÓN DE ENVÍO\n";
    $texto .= "------------------\n";
    $texto .= "$direccion\n\n";
    
    $texto .= "PRÓXIMOS PASOS\n";
    $texto .= "--------------\n";
    $texto .= "- Prepararemos tu pedido en 24-48 horas\n";
    $texto .= "- Envío gratis en 3-5 días hábiles\n";
    $texto .= "- Te avisaremos cuando sea despachado\n\n";
    
    $texto .= "¿Dudas? Contáctanos: info@sedaylino.com\n\n";
    $texto .= "Gracias por tu compra en Seda y Lino\n";
    $texto .= "© 2025 Seda y Lino\n";
    
    return $texto;
}

?>

