<?php
/**
 * ========================================================================
 * FUNCIONES DE EMAIL CON GMAIL SMTP - Tienda Seda y Lino
 * ========================================================================
 * Funciones para envío de emails usando PHPMailer con Gmail SMTP
 * 
 * Funciones disponibles:
 * - enviar_email_gmail(): Función base para enviar emails con PHPMailer y Gmail SMTP
 * - enviar_email_bienvenida(): Envía email de bienvenida a nuevos usuarios
 * - enviar_email_confirmacion_pedido_gmail(): Envía confirmación de pedido al cliente
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 */

// Cargar configuración de Gmail si existe
$gmail_path = __DIR__ . '/../config/gmail.php';
if (file_exists($gmail_path)) {
    try {
        require_once $gmail_path;
    } catch (Exception $e) {
        error_log("Error al cargar gmail.php: " . $e->getMessage());
        // Continuar con valores por defecto
    }
} else {
    // Definir constantes por defecto si el archivo no existe
    if (!defined('GMAIL_SMTP_HOST')) define('GMAIL_SMTP_HOST', 'smtp.gmail.com');
    if (!defined('GMAIL_SMTP_PORT')) define('GMAIL_SMTP_PORT', 587);
    if (!defined('GMAIL_SMTP_USERNAME')) define('GMAIL_SMTP_USERNAME', '');
    if (!defined('GMAIL_SMTP_PASSWORD')) define('GMAIL_SMTP_PASSWORD', '');
    if (!defined('GMAIL_SMTP_ENCRYPTION')) define('GMAIL_SMTP_ENCRYPTION', 'tls');
    if (!defined('GMAIL_FROM_EMAIL')) define('GMAIL_FROM_EMAIL', '');
    if (!defined('GMAIL_FROM_NAME')) define('GMAIL_FROM_NAME', '');
    if (!defined('GMAIL_ENABLED')) define('GMAIL_ENABLED', false);
    
    // Definir función por defecto si no existe
    if (!function_exists('gmail_smtp_esta_configurado')) {
        function gmail_smtp_esta_configurado() {
            return (
                !empty(GMAIL_SMTP_HOST) &&
                !empty(GMAIL_SMTP_PORT) &&
                !empty(GMAIL_SMTP_USERNAME) &&
                !empty(GMAIL_SMTP_PASSWORD) &&
                GMAIL_SMTP_USERNAME !== 'tu_email@gmail.com' &&
                GMAIL_SMTP_PASSWORD !== 'tu_contraseña_de_aplicacion_aqui'
            );
        }
    }
}

/**
 * Envía un email usando PHPMailer con Gmail SMTP
 * 
 * @param string $destinatario Email del destinatario
 * @param string $nombre_destinatario Nombre del destinatario
 * @param string $asunto Asunto del email
 * @param string $cuerpo_html Cuerpo del email en formato HTML
 * @param string $cuerpo_texto Cuerpo del email en texto plano (opcional)
 * @return bool True si se envió correctamente, false si hubo error
 */
function enviar_email_gmail($destinatario, $nombre_destinatario, $asunto, $cuerpo_html, $cuerpo_texto = '') {
    // Verificar que Gmail SMTP esté configurado
    if (!gmail_smtp_esta_configurado()) {
        error_log("Gmail SMTP no está configurado correctamente");
        return false;
    }
    
    // Verificar que Gmail esté habilitado
    if (defined('GMAIL_ENABLED') && !GMAIL_ENABLED) {
        error_log("Gmail está deshabilitado en configuración");
        return false;
    }
    
    // Verificar que todas las constantes necesarias estén definidas
    $constantes_requeridas = [
        'GMAIL_SMTP_HOST',
        'GMAIL_SMTP_PORT',
        'GMAIL_SMTP_USERNAME',
        'GMAIL_SMTP_PASSWORD',
        'GMAIL_SMTP_ENCRYPTION',
        'GMAIL_FROM_EMAIL',
        'GMAIL_FROM_NAME'
    ];
    
    foreach ($constantes_requeridas as $constante) {
        if (!defined($constante)) {
            error_log("Constante requerida no definida: $constante");
            return false;
        }
    }
    
    // Verificar que PHPMailer esté disponible
    $autoload_path = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload_path)) {
        error_log("No se encontró vendor/autoload.php");
        return false;
    }
    
    try {
        require_once $autoload_path;
    } catch (Exception $e) {
        error_log("Error al cargar autoload.php: " . $e->getMessage());
        return false;
    }
    
    // Verificar que PHPMailer esté disponible
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer no está disponible");
        return false;
    }
    
    // Configuración de Gmail SMTP desde archivo de config
    $smtp_host = GMAIL_SMTP_HOST;
    $smtp_port = GMAIL_SMTP_PORT;
    $smtp_username = trim(GMAIL_SMTP_USERNAME);
    $smtp_password = trim(GMAIL_SMTP_PASSWORD);
    $smtp_encryption = GMAIL_SMTP_ENCRYPTION;
    $from_email = GMAIL_FROM_EMAIL;
    $from_name = GMAIL_FROM_NAME;
    
    // Intentar enviar email con Gmail usando SMTP
    try {
        // Crear instancia de PHPMailer usando namespace completo
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_username;
        $mail->Password = $smtp_password;
        $mail->SMTPSecure = $smtp_encryption;
        $mail->Port = $smtp_port;
        $mail->SMTPDebug = 0; // Desactivado en producción
        
        // Remitente
        $mail->setFrom($from_email, $from_name);
        
        // Destinatario
        $mail->addAddress($destinatario, $nombre_destinatario);
        
        // Contenido del email
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = $cuerpo_html;
        $mail->CharSet = 'UTF-8';
        
        // Agregar versión texto plano si está disponible
        if (!empty($cuerpo_texto)) {
            $mail->AltBody = $cuerpo_texto;
        }
        
        // Enviar email
        $mail->send();
        
        return true;
        
    } catch (Exception | Error $e) {
        // Capturar tanto Exception como Error (PHP 7+)
        // Log del error para debugging
        error_log("Error al enviar email con Gmail: " . $e->getMessage());
        error_log("Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
        
        if (isset($mail) && !empty($mail->ErrorInfo)) {
            error_log("PHPMailer ErrorInfo: " . $mail->ErrorInfo);
        }
        
        return false;
    }
}

/**
 * Envía email de bienvenida a un nuevo usuario
 * 
 * @param string $nombre Nombre del usuario
 * @param string $apellido Apellido del usuario
 * @param string $email Email del usuario
 * @return bool True si se envió correctamente, false si hubo error
 */
function enviar_email_bienvenida($nombre, $apellido, $email) {
    // Sanitizar datos
    $nombre_sanitizado = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
    $apellido_sanitizado = htmlspecialchars($apellido, ENT_QUOTES, 'UTF-8');
    $email_sanitizado = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $nombre_completo = $nombre_sanitizado . ' ' . $apellido_sanitizado;
    
    // Generar asunto
    $asunto = "¡Bienvenido a Seda y Lino, $nombre_sanitizado!";
    
    // Generar cuerpo HTML del email
    $cuerpo_html = generar_template_bienvenida($nombre_sanitizado, $apellido_sanitizado, $email_sanitizado);
    
    // Generar versión texto plano
    $cuerpo_texto = generar_texto_bienvenida($nombre_sanitizado, $apellido_sanitizado, $email_sanitizado);
    
    // Enviar email
    return enviar_email_gmail($email, $nombre_completo, $asunto, $cuerpo_html, $cuerpo_texto);
}

/**
 * Genera el template HTML para email de bienvenida
 * 
 * @param string $nombre Nombre del usuario
 * @param string $apellido Apellido del usuario
 * @param string $email Email del usuario
 * @return string HTML del email
 */
function generar_template_bienvenida($nombre, $apellido, $email) {
    $nombre_completo = $nombre . ' ' . $apellido;
    
    // Template HTML con paleta sépia/crema
    $html = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Bienvenido a Seda y Lino</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #F5E6D3;'>
        
        <!-- Header -->
        <div style='background: #B8A082; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='margin: 0; font-size: 28px; font-weight: 700;'>SEDA Y LINO</h1>
            <p style='margin: 10px 0 0 0; font-size: 14px; opacity: 0.95;'>Elegancia atemporal en cada prenda</p>
        </div>
        
        <!-- Mensaje de bienvenida -->
        <div style='background: white; padding: 40px 30px; text-align: center;'>
            <div style='background: #E8DDD0; display: inline-block; padding: 20px 40px; border-radius: 50px; margin-bottom: 20px;'>
                <span style='color: #8B7355; font-size: 48px;'>👋</span>
            </div>
            <h2 style='color: #8B7355; margin: 20px 0 15px 0; font-size: 24px; font-weight: 700;'>¡Bienvenido, $nombre!</h2>
            <p style='color: #6B5D47; margin: 0 0 20px 0; font-size: 16px; line-height: 1.8;'>
                Estamos muy contentos de tenerte en nuestra comunidad. En <strong>Seda y Lino</strong> encontrarás prendas elegantes y de calidad que reflejan tu estilo único.
            </p>
        </div>
        
        <!-- Información de la cuenta -->
        <div style='background: white; padding: 30px; border-left: 4px solid #B8A082; margin-top: 20px;'>
            <h3 style='color: #8B7355; margin-top: 0; border-bottom: 2px solid #E8DDD0; padding-bottom: 10px; font-size: 18px;'>
                📋 Información de tu cuenta
            </h3>
            
            <table style='width: 100%; margin-top: 20px;'>
                <tr>
                    <td style='padding: 10px 0; color: #6B5D47;'><strong>Nombre completo:</strong></td>
                    <td style='padding: 10px 0; text-align: right; color: #8B7355; font-weight: 600;'>$nombre_completo</td>
                </tr>
                <tr>
                    <td style='padding: 10px 0; color: #6B5D47;'><strong>Email registrado:</strong></td>
                    <td style='padding: 10px 0; text-align: right; color: #8B7355; font-weight: 600;'>$email</td>
                </tr>
            </table>
        </div>
        
        <!-- Enlaces útiles -->
        <div style='background: #E8DDD0; padding: 30px; border-radius: 5px; margin-top: 20px;'>
            <h3 style='color: #8B7355; margin-top: 0; font-size: 18px;'>🚀 Comienza a explorar</h3>
            <p style='color: #6B5D47; margin-bottom: 20px;'>Ahora que tu cuenta está lista, puedes:</p>
            <ul style='color: #6B5D47; padding-left: 20px; line-height: 2;'>
                <li style='margin-bottom: 10px;'><strong>Explorar nuestro catálogo</strong> de prendas elegantes de seda y lino</li>
                <li style='margin-bottom: 10px;'><strong>Iniciar sesión</strong> para acceder a tu cuenta y realizar compras</li>
                <li style='margin-bottom: 10px;'><strong>Guardar tus productos favoritos</strong> para comprarlos más tarde</li>
                <li><strong>Disfrutar de envíos gratuitos</strong> en compras elegibles</li>
            </ul>
            
            <div style='text-align: center; margin-top: 30px;'>
                <a href='catalogo.php' style='background: #B8A082; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: 600; margin-right: 10px;'>Ver Catálogo</a>
                <a href='login.php' style='background: white; color: #8B7355; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: 600; border: 2px solid #B8A082;'>Iniciar Sesión</a>
            </div>
        </div>
        
        <!-- Información de contacto -->
        <div style='background: white; padding: 20px; text-align: center; margin-top: 20px; border-top: 3px solid #B8A082;'>
            <p style='color: #6B5D47; margin: 10px 0;'>¿Tienes alguna pregunta?</p>
            <p style='margin: 10px 0;'>
                <a href='mailto:" . (defined('GMAIL_FROM_EMAIL') ? GMAIL_FROM_EMAIL : 'info@sedaylino.com') . "' style='color: #8B7355; text-decoration: none; font-weight: 600;'>" . (defined('GMAIL_FROM_EMAIL') ? GMAIL_FROM_EMAIL : 'info@sedaylino.com') . "</a>
            </p>
        </div>
        
        <!-- Footer -->
        <div style='background: #8B7355; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; margin-top: 20px;'>
            <p style='margin: 0; font-size: 14px;'>Gracias por unirte a <strong>Seda y Lino</strong></p>
            <p style='margin: 10px 0 0 0; font-size: 12px; opacity: 0.9;'>© 2025 Seda y Lino. Todos los derechos reservados.</p>
        </div>
        
    </body>
    </html>
    ";
    
    return $html;
}

/**
 * Genera versión en texto plano del email de bienvenida
 * 
 * @param string $nombre Nombre del usuario
 * @param string $apellido Apellido del usuario
 * @param string $email Email del usuario
 * @return string Texto plano del email
 */
function generar_texto_bienvenida($nombre, $apellido, $email) {
    $nombre_completo = $nombre . ' ' . $apellido;
    
    $texto = "========================================\n";
    $texto .= "SEDA Y LINO - Bienvenido\n";
    $texto .= "========================================\n\n";
    
    $texto .= "¡Hola $nombre!\n\n";
    $texto .= "Estamos muy contentos de tenerte en nuestra comunidad. En Seda y Lino encontrarás prendas elegantes y de calidad que reflejan tu estilo único.\n\n";
    
    $texto .= "INFORMACIÓN DE TU CUENTA\n";
    $texto .= "-----------------------\n";
    $texto .= "Nombre completo: $nombre_completo\n";
    $texto .= "Email registrado: $email\n\n";
    
    $texto .= "COMENZA A EXPLORAR\n";
    $texto .= "------------------\n";
    $texto .= "- Explora nuestro catálogo de prendas elegantes de seda y lino\n";
    $texto .= "- Inicia sesión para acceder a tu cuenta y realizar compras\n";
    $texto .= "- Guarda tus productos favoritos para comprarlos más tarde\n";
    $texto .= "- Disfruta de envíos gratuitos en compras elegibles\n\n";
    
    $texto .= "¿Dudas? Contáctanos: " . (defined('GMAIL_FROM_EMAIL') ? GMAIL_FROM_EMAIL : 'info@sedaylino.com') . "\n\n";
    $texto .= "Gracias por unirte a Seda y Lino\n";
    $texto .= "© 2025 Seda y Lino\n";
    
    return $texto;
}

/**
 * Envía email de confirmación de pedido al cliente usando PHPMailer con Gmail SMTP
 * 
 * NOTA: Esta es la función principal usada actualmente en el sistema (procesar-pedido.php).
 * Utiliza PHPMailer con Gmail SMTP para envío confiable de emails.
 * 
 * DIFERENCIAS:
 * - enviar_email_confirmacion_pedido_gmail(): Usa PHPMailer/Gmail SMTP, recibe $pedido_exitoso (de $_SESSION) y $datos_usuario (FUNCIÓN PRINCIPAL)
 * - enviar_email_confirmacion_pedido(): Usa mail() nativo, recibe $datos_pedido y $datos_usuario (alternativa)
 * 
 * @param array $pedido_exitoso Datos del pedido (de $_SESSION['pedido_exitoso'])
 * @param array $datos_usuario Datos del usuario (nombre, apellido, email)
 * @return bool True si se envió correctamente, false si hubo error
 */
function enviar_email_confirmacion_pedido_gmail($pedido_exitoso, $datos_usuario) {
    // Sanitizar datos del usuario
    $nombre_sanitizado = htmlspecialchars($datos_usuario['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
    $apellido_sanitizado = htmlspecialchars($datos_usuario['apellido'] ?? '', ENT_QUOTES, 'UTF-8');
    $email_sanitizado = htmlspecialchars($datos_usuario['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $nombre_completo = trim($nombre_sanitizado . ' ' . $apellido_sanitizado);
    
    // Generar asunto
    $id_pedido_formateado = str_pad($pedido_exitoso['id_pedido'], 6, '0', STR_PAD_LEFT);
    $asunto = "Confirmación de Pedido #$id_pedido_formateado - Seda y Lino";
    
    // Generar cuerpo HTML del email
    $cuerpo_html = generar_template_confirmacion_pedido_gmail($pedido_exitoso, $nombre_completo);
    
    // Generar versión texto plano
    $cuerpo_texto = generar_texto_confirmacion_pedido_gmail($pedido_exitoso, $nombre_completo);
    
    // Enviar email
    return enviar_email_gmail($email_sanitizado, $nombre_completo, $asunto, $cuerpo_html, $cuerpo_texto);
}

/**
 * Genera el template HTML para email de confirmación de pedido
 * 
 * @param array $pedido Datos del pedido
 * @param string $nombre_completo Nombre completo del usuario
 * @return string HTML del email
 */
function generar_template_confirmacion_pedido_gmail($pedido, $nombre_completo) {
    $id_pedido_formateado = str_pad($pedido['id_pedido'], 6, '0', STR_PAD_LEFT);
    
    // Construir dirección completa
    $direccion_parts = [];
    if (!empty($pedido['direccion'])) {
        $direccion_parts[] = htmlspecialchars($pedido['direccion'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($pedido['localidad'])) {
        $direccion_parts[] = htmlspecialchars($pedido['localidad'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($pedido['provincia'])) {
        $direccion_parts[] = htmlspecialchars($pedido['provincia'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($pedido['codigo_postal'])) {
        $direccion_parts[] = 'CP: ' . htmlspecialchars($pedido['codigo_postal'], ENT_QUOTES, 'UTF-8');
    }
    $direccion_completa = !empty($direccion_parts) ? implode(', ', $direccion_parts) : 'N/A';
    
    // Calcular totales
    $subtotal_pedido = isset($pedido['subtotal']) ? (float)$pedido['subtotal'] : (float)$pedido['total'];
    $costo_envio_pedido = isset($pedido['costo_envio']) ? (float)$pedido['costo_envio'] : ((float)$pedido['total'] - $subtotal_pedido);
    $es_envio_gratis = isset($pedido['es_envio_gratis']) ? (bool)$pedido['es_envio_gratis'] : ($costo_envio_pedido == 0);
    if ($es_envio_gratis && $costo_envio_pedido > 0) {
        $costo_envio_pedido = 0;
    }
    if ($costo_envio_pedido == 0 && !$es_envio_gratis) {
        $es_envio_gratis = true;
    }
    
    // Método de pago
    $metodo_pago = htmlspecialchars($pedido['metodo_pago'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $metodo_pago_descripcion = !empty($pedido['metodo_pago_descripcion']) ? htmlspecialchars($pedido['metodo_pago_descripcion'], ENT_QUOTES, 'UTF-8') : '';
    
    // Detectar warnings según método de pago
    $nombre_metodo_lower = strtolower($metodo_pago);
    $mostrar_warning_aprobacion = false;
    $mensaje_warning = '';
    if (strpos($nombre_metodo_lower, 'transferencia') !== false || 
        strpos($nombre_metodo_lower, 'depósito') !== false || 
        strpos($nombre_metodo_lower, 'efectivo') !== false ||
        strpos($nombre_metodo_lower, 'manual') !== false) {
        $mostrar_warning_aprobacion = true;
        $mensaje_warning = 'Tu pago será revisado manualmente. Recibirás confirmación por email en 24-48 horas una vez que se procese el pago.';
    } elseif (strpos($nombre_metodo_lower, 'transferencia') !== false || 
              strpos($nombre_metodo_lower, 'depósito') !== false) {
        $mensaje_warning = 'Los pagos por transferencia pueden tardar 24-48hs en procesarse. Te notificaremos por email cuando se confirme el pago.';
    }
    
    // Generar filas de productos
    $filas_productos = '';
    foreach ($pedido['productos'] as $producto) {
        $nombre_producto = htmlspecialchars($producto['nombre_producto'], ENT_QUOTES, 'UTF-8');
        $talle = htmlspecialchars($producto['talle'], ENT_QUOTES, 'UTF-8');
        $color = htmlspecialchars($producto['color'], ENT_QUOTES, 'UTF-8');
        $cantidad = (int)$producto['cantidad'];
        $precio_unitario = number_format((float)$producto['precio_unitario'], 2);
        $subtotal = number_format((float)$producto['subtotal'], 2);
        
        $filas_productos .= "
        <tr>
            <td style='padding: 15px; border-bottom: 1px solid #E8DDD0;'>
                <strong style='color: #8B7355; font-size: 16px;'>$nombre_producto</strong><br>
                <small style='color: #6B5D47;'>Talla: $talle | Color: $color</small>
            </td>
            <td style='padding: 15px; border-bottom: 1px solid #E8DDD0; text-align: center; color: #6B5D47;'>$cantidad</td>
            <td style='padding: 15px; border-bottom: 1px solid #E8DDD0; text-align: right; color: #6B5D47;'>\$$precio_unitario</td>
            <td style='padding: 15px; border-bottom: 1px solid #E8DDD0; text-align: right;'><strong style='color: #8B7355;'>\$$subtotal</strong></td>
        </tr>";
    }
    
    // Template HTML con paleta sépia/crema
    $html = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Confirmación de Pedido</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px; background-color: #F5E6D3;'>
        
        <!-- Header -->
        <div style='background: #B8A082; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='margin: 0; font-size: 28px; font-weight: 700;'>SEDA Y LINO</h1>
            <p style='margin: 10px 0 0 0; font-size: 14px; opacity: 0.95;'>Elegancia atemporal en cada prenda</p>
        </div>
        
        <!-- Confirmación -->
        <div style='background: white; padding: 40px 30px; text-align: center;'>
            <div style='background: #E8DDD0; display: inline-block; padding: 20px 30px; border-radius: 50px; margin-bottom: 20px;'>
                <span style='color: #8B7355; font-size: 48px;'>✓</span>
            </div>
            <h2 style='color: #8B7355; margin: 20px 0 15px 0; font-size: 26px; font-weight: 700;'>¡Pedido Confirmado!</h2>
            <p style='color: #6B5D47; margin: 0; font-size: 16px; line-height: 1.8;'>
                Hola <strong>$nombre_completo</strong>, recibimos tu pedido correctamente.
            </p>
        </div>
        
        <!-- Método de Pago Destacado -->
        <div style='background: #F5E6D3; border: 2px solid #B8A082; border-radius: 12px; padding: 25px; margin: 20px 0;'>
            <div style='margin-bottom: 15px;'>
                <div style='font-size: 14px; font-weight: 700; color: #8B7355; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;'>
                    💳 Método de Pago
                </div>
                <div style='font-size: 22px; font-weight: 800; color: #8B7355; text-transform: uppercase; letter-spacing: 1.5px;'>$metodo_pago</div>
            </div>";
    
    if (!empty($metodo_pago_descripcion)) {
        $html .= "
            <div style='background: white; border: 1px solid #D4C4A8; border-left: 4px solid #B8A082; border-radius: 8px; padding: 15px; margin-top: 15px;'>
                <div style='font-size: 12px; font-weight: 700; color: #6B5D47; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;'>Detalles del Pago</div>
                <div style='font-size: 14px; font-weight: 600; color: #8B7355; line-height: 1.6; word-break: break-word;'>$metodo_pago_descripcion</div>
            </div>";
    }
    
    if ($mostrar_warning_aprobacion && !empty($mensaje_warning)) {
        $html .= "
            <div style='background: #E3F2FD; border-left: 4px solid #0dcaf0; border-radius: 5px; padding: 15px; margin-top: 15px;'>
                <div style='font-weight: 700; color: #1976d2; margin-bottom: 5px;'>📌 Próximos pasos:</div>
                <div style='font-size: 13px; color: #495057; line-height: 1.6;'>" . htmlspecialchars($mensaje_warning, ENT_QUOTES, 'UTF-8') . "</div>
            </div>";
    }
    
    $html .= "
        </div>
        
        <!-- Detalles del Pedido -->
        <div style='background: white; padding: 30px; border-left: 4px solid #B8A082; margin-top: 20px;'>
            <h3 style='color: #8B7355; margin-top: 0; border-bottom: 2px solid #E8DDD0; padding-bottom: 10px; font-size: 20px;'>
                📋 Detalles del Pedido
            </h3>
            
            <table style='width: 100%; margin-top: 20px;'>
                <tr>
                    <td style='padding: 12px 0; color: #6B5D47;'><strong>Número de Pedido:</strong></td>
                    <td style='padding: 12px 0; text-align: right; color: #8B7355; font-size: 20px; font-weight: 700; letter-spacing: 2px;'>#$id_pedido_formateado</td>
                </tr>
            </table>
            
            <hr style='border: none; border-top: 1px solid #E8DDD0; margin: 25px 0;'>
            
            <h4 style='color: #8B7355; margin-top: 30px; margin-bottom: 15px; font-size: 18px;'>📦 Productos (" . count($pedido['productos']) . ")</h4>
            <table style='width: 100%; border-collapse: collapse; margin-bottom: 25px;'>
                <thead>
                    <tr style='background: #F5E6D3;'>
                        <th style='padding: 12px; text-align: left; border-bottom: 2px solid #D4C4A8; color: #8B7355; font-size: 14px;'>Producto</th>
                        <th style='padding: 12px; text-align: center; border-bottom: 2px solid #D4C4A8; color: #8B7355; font-size: 14px;'>Cant.</th>
                        <th style='padding: 12px; text-align: right; border-bottom: 2px solid #D4C4A8; color: #8B7355; font-size: 14px;'>Precio</th>
                        <th style='padding: 12px; text-align: right; border-bottom: 2px solid #D4C4A8; color: #8B7355; font-size: 14px;'>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    $filas_productos
                </tbody>
                <tfoot>
                    <tr style='background: #F5E6D3;'>
                        <td colspan='3' style='padding: 15px; text-align: right; border-top: 2px solid #D4C4A8; color: #6B5D47;'><strong>Subtotal:</strong></td>
                        <td style='padding: 15px; text-align: right; border-top: 2px solid #D4C4A8;'><strong style='color: #8B7355;'>\$" . number_format($subtotal_pedido, 2) . "</strong></td>
                    </tr>
                    <tr style='background: #F5E6D3;'>
                        <td colspan='3' style='padding: 15px; text-align: right; color: #6B5D47;'><strong>Envío:</strong></td>
                        <td style='padding: 15px; text-align: right;'>";
    
    if ($es_envio_gratis) {
        $html .= "<span style='font-weight: 700; color: #8B7355; font-size: 16px;'>GRATIS</span>";
    } else {
        $html .= "<strong style='color: #8B7355;'>\$" . number_format($costo_envio_pedido, 2) . "</strong>";
    }
    
    $html .= "
                        </td>
                    </tr>
                    <tr style='background: #B8A082; color: white;'>
                        <td colspan='3' style='padding: 18px; text-align: right; font-size: 18px;'><strong>TOTAL:</strong></td>
                        <td style='padding: 18px; text-align: right; font-size: 20px; font-weight: 800;'>\$" . number_format($pedido['total'], 2) . "</td>
                    </tr>
                </tfoot>
            </table>
            
            <h4 style='color: #8B7355; margin-top: 30px; margin-bottom: 15px; font-size: 18px;'>🚚 Dirección de Envío</h4>
            <div style='background: #F5E6D3; padding: 15px; border-radius: 5px; border-left: 4px solid #B8A082;'>
                <p style='margin: 0; color: #6B5D47; line-height: 1.8;'>$direccion_completa</p>
            </div>
        </div>
        
        <!-- Próximos Pasos -->
        <div style='background: #E8DDD0; padding: 30px; border-radius: 5px; margin-top: 20px;'>
            <h3 style='color: #8B7355; margin-top: 0; font-size: 18px;'>📌 Próximos Pasos</h3>
            <ul style='color: #6B5D47; padding-left: 20px; line-height: 2;'>
                <li style='margin-bottom: 10px;'><strong>Prepararemos tu pedido</strong> en las próximas 24-48 horas.</li>
                <li style='margin-bottom: 10px;'><strong>Envío</strong> - Recibirás tu pedido en 3-5 días hábiles" . ($es_envio_gratis ? ' (GRATIS)' : '') . ".</li>
                <li style='margin-bottom: 10px;'><strong>Te avisaremos</strong> cuando tu pedido sea despachado.</li>
                <li><strong>Guarda este email</strong> como comprobante de tu compra.</li>
            </ul>
        </div>
        
        <!-- Información de Contacto -->
        <div style='background: white; padding: 20px; text-align: center; margin-top: 20px; border-top: 3px solid #B8A082;'>
            <p style='color: #6B5D47; margin: 10px 0;'>¿Tienes alguna duda?</p>
            <p style='margin: 10px 0;'>
                <a href='mailto:" . (defined('GMAIL_FROM_EMAIL') ? GMAIL_FROM_EMAIL : 'info@sedaylino.com') . "' style='color: #8B7355; text-decoration: none; font-weight: 600;'>" . (defined('GMAIL_FROM_EMAIL') ? GMAIL_FROM_EMAIL : 'info@sedaylino.com') . "</a>
            </p>
        </div>
        
        <!-- Footer -->
        <div style='background: #8B7355; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; margin-top: 20px;'>
            <p style='margin: 0; font-size: 14px;'>Gracias por tu compra en <strong>Seda y Lino</strong></p>
            <p style='margin: 10px 0 0 0; font-size: 12px; opacity: 0.9;'>© 2025 Seda y Lino. Todos los derechos reservados.</p>
        </div>
        
    </body>
    </html>
    ";
    
    return $html;
}

/**
 * Genera versión en texto plano del email de confirmación de pedido
 * 
 * @param array $pedido Datos del pedido
 * @param string $nombre_completo Nombre completo del usuario
 * @return string Texto plano del email
 */
function generar_texto_confirmacion_pedido_gmail($pedido, $nombre_completo) {
    $id_pedido_formateado = str_pad($pedido['id_pedido'], 6, '0', STR_PAD_LEFT);
    
    // Construir dirección completa
    $direccion_parts = [];
    if (!empty($pedido['direccion'])) {
        $direccion_parts[] = $pedido['direccion'];
    }
    if (!empty($pedido['localidad'])) {
        $direccion_parts[] = $pedido['localidad'];
    }
    if (!empty($pedido['provincia'])) {
        $direccion_parts[] = $pedido['provincia'];
    }
    if (!empty($pedido['codigo_postal'])) {
        $direccion_parts[] = 'CP: ' . $pedido['codigo_postal'];
    }
    $direccion_completa = !empty($direccion_parts) ? implode(', ', $direccion_parts) : 'N/A';
    
    // Calcular totales
    $subtotal_pedido = isset($pedido['subtotal']) ? (float)$pedido['subtotal'] : (float)$pedido['total'];
    $costo_envio_pedido = isset($pedido['costo_envio']) ? (float)$pedido['costo_envio'] : ((float)$pedido['total'] - $subtotal_pedido);
    $es_envio_gratis = isset($pedido['es_envio_gratis']) ? (bool)$pedido['es_envio_gratis'] : ($costo_envio_pedido == 0);
    if ($es_envio_gratis && $costo_envio_pedido > 0) {
        $costo_envio_pedido = 0;
    }
    
    $texto = "========================================\n";
    $texto .= "SEDA Y LINO - Confirmación de Pedido\n";
    $texto .= "========================================\n\n";
    
    $texto .= "¡Hola $nombre_completo!\n\n";
    $texto .= "Tu pedido ha sido confirmado exitosamente.\n\n";
    
    $texto .= "DETALLES DEL PEDIDO\n";
    $texto .= "-------------------\n";
    $texto .= "Número de Pedido: #$id_pedido_formateado\n";
    $texto .= "Método de Pago: " . ($pedido['metodo_pago'] ?? 'N/A') . "\n";
    if (!empty($pedido['metodo_pago_descripcion'])) {
        $texto .= "Detalles del Pago: " . $pedido['metodo_pago_descripcion'] . "\n";
    }
    $texto .= "\n";
    
    $texto .= "PRODUCTOS\n";
    $texto .= "---------\n";
    foreach ($pedido['productos'] as $producto) {
        $texto .= "- {$producto['nombre_producto']}\n";
        $texto .= "  Talla: {$producto['talle']} | Color: {$producto['color']}\n";
        $texto .= "  Cantidad: {$producto['cantidad']} x \$" . number_format($producto['precio_unitario'], 2) . "\n";
        $texto .= "  Subtotal: \$" . number_format($producto['subtotal'], 2) . "\n\n";
    }
    
    $texto .= "RESUMEN\n";
    $texto .= "-------\n";
    $texto .= "Subtotal: \$" . number_format($subtotal_pedido, 2) . "\n";
    if ($es_envio_gratis) {
        $texto .= "Envío: GRATIS\n";
    } else {
        $texto .= "Envío: \$" . number_format($costo_envio_pedido, 2) . "\n";
    }
    $texto .= "TOTAL: \$" . number_format($pedido['total'], 2) . "\n\n";
    
    $texto .= "DIRECCIÓN DE ENVÍO\n";
    $texto .= "------------------\n";
    $texto .= "$direccion_completa\n\n";
    
    $texto .= "PRÓXIMOS PASOS\n";
    $texto .= "--------------\n";
    $texto .= "- Prepararemos tu pedido en 24-48 horas\n";
    $texto .= "- Envío en 3-5 días hábiles" . ($es_envio_gratis ? ' (GRATIS)' : '') . "\n";
    $texto .= "- Te avisaremos cuando sea despachado\n\n";
    
    $texto .= "¿Dudas? Contáctanos: " . (defined('GMAIL_FROM_EMAIL') ? GMAIL_FROM_EMAIL : 'info@sedaylino.com') . "\n\n";
    $texto .= "Gracias por tu compra en Seda y Lino\n";
    $texto .= "© 2025 Seda y Lino\n";
    
    return $texto;
}

/**
 * Envía email de notificación cuando un pedido es cancelado o un pago es rechazado
 *
 * CASOS DE USO:
 * - Pedido cancelado automáticamente (auto_cleanup_reservas.php)
 * - Pedido cancelado manualmente por ventas
 * - Pedido cancelado por el cliente
 * - Pago rechazado por ventas
 *
 * @param int $id_pedido ID del pedido
 * @param int $id_usuario ID del usuario
 * @param string $tipo Tipo de notificación: 'cancelado' o 'rechazado'
 * @param string|null $motivo Motivo del rechazo/cancelación (opcional)
 * @param mysqli $mysqli Conexión a la base de datos
 * @return bool True si se envió correctamente, false si hubo error
 */
function enviar_email_pedido_cancelado_o_rechazado($id_pedido, $id_usuario, $tipo, $motivo = null, $mysqli = null) {
    try {
        // Si no se pasó mysqli, intentar obtener la conexión global
        if ($mysqli === null) {
            global $mysqli;
            if (!$mysqli) {
                error_log("enviar_email_pedido_cancelado_o_rechazado: No se pudo obtener conexión mysqli");
                return false;
            }
        }

        // Cargar función de usuario si no está cargada
        if (!function_exists('obtenerUsuarioPorId')) {
            require_once __DIR__ . '/../queries/usuario_queries.php';
        }

        // Obtener datos del usuario
        $usuario = obtenerUsuarioPorId($mysqli, $id_usuario);
        if (!$usuario || empty($usuario['email'])) {
            error_log("enviar_email_pedido_cancelado_o_rechazado: Usuario no encontrado o sin email. ID: $id_usuario");
            return false;
        }

        // Sanitizar datos
        $nombre_sanitizado = htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8');
        $apellido_sanitizado = htmlspecialchars($usuario['apellido'], ENT_QUOTES, 'UTF-8');
        $email_sanitizado = htmlspecialchars($usuario['email'], ENT_QUOTES, 'UTF-8');
        $nombre_completo = trim($nombre_sanitizado . ' ' . $apellido_sanitizado);

        // Formatear ID de pedido
        $id_pedido_formateado = str_pad($id_pedido, 6, '0', STR_PAD_LEFT);

        // Determinar asunto y contenido según tipo
        if ($tipo === 'rechazado') {
            $asunto = "Pago Rechazado - Pedido #$id_pedido_formateado - Seda y Lino";
            $cuerpo_html = generar_template_pedido_cancelado_rechazado($id_pedido_formateado, $nombre_sanitizado, 'rechazado', $motivo);
            $cuerpo_texto = generar_texto_pedido_cancelado_rechazado($id_pedido_formateado, $nombre_sanitizado, 'rechazado', $motivo);
        } else {
            $asunto = "Pedido Cancelado - #$id_pedido_formateado - Seda y Lino";
            $cuerpo_html = generar_template_pedido_cancelado_rechazado($id_pedido_formateado, $nombre_sanitizado, 'cancelado', $motivo);
            $cuerpo_texto = generar_texto_pedido_cancelado_rechazado($id_pedido_formateado, $nombre_sanitizado, 'cancelado', $motivo);
        }

        // Enviar email
        $resultado = enviar_email_gmail($email_sanitizado, $nombre_completo, $asunto, $cuerpo_html, $cuerpo_texto);

        if ($resultado) {
            error_log("Email enviado exitosamente a $email_sanitizado - Pedido #$id_pedido - Tipo: $tipo");
        } else {
            error_log("Error al enviar email a $email_sanitizado - Pedido #$id_pedido - Tipo: $tipo");
        }

        return $resultado;

    } catch (Exception $e) {
        error_log("Error en enviar_email_pedido_cancelado_o_rechazado: " . $e->getMessage());
        return false;
    }
}

/**
 * Genera el template HTML para email de pedido cancelado o pago rechazado
 *
 * @param string $id_pedido_formateado ID del pedido formateado
 * @param string $nombre Nombre del usuario
 * @param string $tipo 'cancelado' o 'rechazado'
 * @param string|null $motivo Motivo del rechazo/cancelación (opcional)
 * @return string HTML del email
 */
function generar_template_pedido_cancelado_rechazado($id_pedido_formateado, $nombre, $tipo, $motivo = null) {
    $email_contacto = defined('GMAIL_FROM_EMAIL') ? GMAIL_FROM_EMAIL : 'info.sedaylino@gmail.com';

    // Configuración según tipo
    if ($tipo === 'rechazado') {
        $titulo = "Pago Rechazado";
        $estado_badge = "PAGO RECHAZADO";
        $titulo_razon = "⚠️ ¿Por qué se rechazó tu pago?";
        $razon_texto = $motivo
            ? "<p style='color: #6B5D47; margin: 10px 0; line-height: 1.8;'><strong>Motivo:</strong> " . htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8') . "</p>"
            : "<p style='color: #6B5D47; margin: 10px 0; line-height: 1.8;'>Tu pago no pudo ser verificado. Por favor, contacta con nosotros para más detalles.</p>";
        $mensaje_inicial = "lamentamos informarte que el pago de tu pedido ha sido rechazado";
    } else {
        $titulo = "Pedido Cancelado";
        $estado_badge = "PEDIDO CANCELADO";
        $titulo_razon = "⚠️ ¿Por qué se canceló tu pedido?";
        $razon_texto = $motivo
            ? "<p style='color: #6B5D47; margin: 10px 0; line-height: 1.8;'><strong>Motivo:</strong> " . htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8') . "</p>"
            : "<p style='color: #6B5D47; margin: 10px 0; line-height: 1.8;'>Tu pedido fue cancelado automáticamente porque <strong>han transcurrido más de 24 horas sin recibir confirmación del pago</strong>.</p>";
    }

    $html = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>$titulo</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #F5E6D3;'>

        <!-- Header -->
        <div style='background: #B8A082; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='margin: 0; font-size: 28px; font-weight: 700;'>SEDA Y LINO</h1>
            <p style='margin: 10px 0 0 0; font-size: 14px; opacity: 0.95;'>Elegancia atemporal en cada prenda</p>
        </div>

        <!-- Notificación -->
        <div style='background: white; padding: 40px 30px; text-align: center;'>
            <div style='background: #FFE5E5; display: inline-block; padding: 20px 30px; border-radius: 50px; margin-bottom: 20px;'>
                <span style='color: #D32F2F; font-size: 48px;'>✕</span>
            </div>
            <h2 style='color: #8B7355; margin: 20px 0 15px 0; font-size: 26px; font-weight: 700;'>$titulo</h2>
            <p style='color: #6B5D47; margin: 0; font-size: 16px; line-height: 1.8;'>
                Hola <strong>$nombre</strong>, $mensaje_inicial.
            </p>
        </div>

        <!-- Detalles del Pedido -->
        <div style='background: white; padding: 30px; border-left: 4px solid #D32F2F; margin-top: 20px;'>
            <h3 style='color: #8B7355; margin-top: 0; border-bottom: 2px solid #E8DDD0; padding-bottom: 10px; font-size: 20px;'>
                📋 Información del Pedido
            </h3>

            <table style='width: 100%; margin-top: 20px;'>
                <tr>
                    <td style='padding: 12px 0; color: #6B5D47;'><strong>Número de Pedido:</strong></td>
                    <td style='padding: 12px 0; text-align: right; color: #8B7355; font-size: 20px; font-weight: 700; letter-spacing: 2px;'>#$id_pedido_formateado</td>
                </tr>
                <tr>
                    <td style='padding: 12px 0; color: #6B5D47;'><strong>Estado:</strong></td>
                    <td style='padding: 12px 0; text-align: right;'><span style='background: #FFE5E5; color: #D32F2F; padding: 5px 15px; border-radius: 15px; font-weight: 700;'>$estado_badge</span></td>
                </tr>
            </table>
        </div>

        <!-- Razón -->
        <div style='background: #FFF9E6; border-left: 4px solid #FFA726; padding: 25px; margin-top: 20px; border-radius: 5px;'>
            <h3 style='color: #E65100; margin-top: 0; font-size: 18px;'>$titulo_razon</h3>
            $razon_texto
            <p style='color: #6B5D47; margin: 10px 0; line-height: 1.8;'>
                El stock reservado ha sido liberado y está nuevamente disponible para otros clientes.
            </p>
        </div>

        <!-- Próximos Pasos -->
        <div style='background: #E8DDD0; padding: 30px; border-radius: 5px; margin-top: 20px;'>
            <h3 style='color: #8B7355; margin-top: 0; font-size: 18px;'>💡 ¿Qué puedes hacer ahora?</h3>
            <ul style='color: #6B5D47; padding-left: 20px; line-height: 2;'>
                <li style='margin-bottom: 10px;'><strong>Realizar un nuevo pedido</strong> si aún te interesan los productos.</li>
                <li style='margin-bottom: 10px;'><strong>Verificar disponibilidad</strong> de los productos en nuestro catálogo.</li>
                <li><strong>Contactarnos</strong> si crees que esto es un error o necesitas ayuda.</li>
            </ul>
        </div>

        <!-- Información de Contacto -->
        <div style='background: white; padding: 25px; text-align: center; margin-top: 20px; border-top: 3px solid #B8A082; border-radius: 5px;'>
            <h4 style='color: #8B7355; margin-top: 0;'>¿Necesitas ayuda?</h4>
            <p style='color: #6B5D47; margin: 10px 0;'>
                Para más información o si tienes alguna consulta, contáctanos:
            </p>
            <p style='margin: 15px 0;'>
                <a href='mailto:$email_contacto' style='background: #B8A082; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: 600;'>
                    📧 $email_contacto
                </a>
            </p>
        </div>

        <!-- Footer -->
        <div style='background: #8B7355; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; margin-top: 20px;'>
            <p style='margin: 0; font-size: 14px;'>Sentimos las molestias ocasionadas</p>
            <p style='margin: 10px 0 0 0; font-size: 12px; opacity: 0.9;'>© 2025 Seda y Lino. Todos los derechos reservados.</p>
        </div>

    </body>
    </html>
    ";

    return $html;
}

/**
 * Genera versión en texto plano del email de pedido cancelado o pago rechazado
 *
 * @param string $id_pedido_formateado ID del pedido formateado
 * @param string $nombre Nombre del usuario
 * @param string $tipo 'cancelado' o 'rechazado'
 * @param string|null $motivo Motivo del rechazo/cancelación (opcional)
 * @return string Texto plano del email
 */
function generar_texto_pedido_cancelado_rechazado($id_pedido_formateado, $nombre, $tipo, $motivo = null) {
    $email_contacto = defined('GMAIL_FROM_EMAIL') ? GMAIL_FROM_EMAIL : 'info.sedaylino@gmail.com';

    // Configuración según tipo
    if ($tipo === 'rechazado') {
        $titulo = "Pago Rechazado";
        $estado = "PAGO RECHAZADO";
        $titulo_razon = "¿POR QUÉ SE RECHAZÓ TU PAGO?";
        $razon_texto = $motivo
            ? "Motivo: $motivo\n\n"
            : "Tu pago no pudo ser verificado. Por favor, contacta con nosotros para más detalles.\n\n";
        $mensaje_inicial = "lamentamos informarte que el pago de tu pedido ha sido rechazado.";
    } else {
        $titulo = "Pedido Cancelado";
        $estado = "CANCELADO";
        $titulo_razon = "¿POR QUÉ SE CANCELÓ TU PEDIDO?";
        $razon_texto = $motivo
            ? "Motivo: $motivo\n\n"
            : "Tu pedido fue cancelado automáticamente porque han transcurrido\nmás de 24 horas sin recibir confirmación del pago.\n\n";
        $mensaje_inicial = "lamentamos informarte que tu pedido ha sido cancelado.";
    }

    $texto = "========================================\n";
    $texto .= "SEDA Y LINO - $titulo\n";
    $texto .= "========================================\n\n";

    $texto .= "Hola $nombre,\n\n";
    $texto .= ucfirst($mensaje_inicial) . "\n\n";

    $texto .= "INFORMACIÓN DEL PEDIDO\n";
    $texto .= "----------------------\n";
    $texto .= "Número de Pedido: #$id_pedido_formateado\n";
    $texto .= "Estado: $estado\n\n";

    $texto .= "$titulo_razon\n";
    $texto .= "------------------------------\n";
    $texto .= $razon_texto;
    $texto .= "El stock reservado ha sido liberado y está nuevamente disponible\n";
    $texto .= "para otros clientes.\n\n";

    $texto .= "¿QUÉ PUEDES HACER AHORA?\n";
    $texto .= "------------------------\n";
    $texto .= "- Realizar un nuevo pedido si aún te interesan los productos\n";
    $texto .= "- Verificar disponibilidad de los productos en nuestro catálogo\n";
    $texto .= "- Contactarnos si crees que esto es un error o necesitas ayuda\n\n";

    $texto .= "¿NECESITAS AYUDA?\n";
    $texto .= "----------------\n";
    $texto .= "Para más información, contáctanos: $email_contacto\n\n";

    $texto .= "Sentimos las molestias ocasionadas.\n";
    $texto .= "© 2025 Seda y Lino\n";

    return $texto;
}

?>

