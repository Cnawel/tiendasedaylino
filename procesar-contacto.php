<?php
/**
 * ========================================================================
 * PROCESAR CONTACTO - Tienda Seda y Lino
 * ========================================================================
 * Procesa el formulario de contacto y envía emails mediante Mailgun SMTP
 * 
 * Funcionalidades:
 * - Valida datos del formulario
 * - Envía email usando Mailgun SMTP
 * - Redirige con mensaje de éxito o error
 * ========================================================================
 */

// Activar manejo de errores para debugging (en desarrollo)
// En producción, estos errores se registrarán en el log
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en pantalla (solo en log)
ini_set('log_errors', 1);

session_start();

// Cargar configuración de Mailgun si existe
$mailgun_path = __DIR__ . '/config/mailgun.php';
if (file_exists($mailgun_path)) {
    try {
        require_once $mailgun_path;
    } catch (Exception $e) {
        error_log("Error al cargar mailgun.php: " . $e->getMessage());
        // Continuar con valores por defecto
    }
} else {
    // Definir constantes por defecto si el archivo no existe
    if (!defined('MAILGUN_API_KEY')) define('MAILGUN_API_KEY', '');
    if (!defined('MAILGUN_DOMAIN')) define('MAILGUN_DOMAIN', '');
    if (!defined('MAILGUN_BASE_URL')) define('MAILGUN_BASE_URL', 'https://api.mailgun.net');
    if (!defined('MAILGUN_FROM_EMAIL')) define('MAILGUN_FROM_EMAIL', '');
    if (!defined('MAILGUN_FROM_NAME')) define('MAILGUN_FROM_NAME', '');
    if (!defined('MAILGUN_CONTACT_TO_EMAIL')) define('MAILGUN_CONTACT_TO_EMAIL', '');
    if (!defined('MAILGUN_CONTACT_TO_NAME')) define('MAILGUN_CONTACT_TO_NAME', '');
    if (!defined('MAILGUN_ENABLED')) define('MAILGUN_ENABLED', false);
    if (!defined('MAILGUN_SMTP_HOST')) define('MAILGUN_SMTP_HOST', 'smtp.mailgun.org');
    if (!defined('MAILGUN_SMTP_PORT')) define('MAILGUN_SMTP_PORT', 587);
    if (!defined('MAILGUN_SMTP_USERNAME')) define('MAILGUN_SMTP_USERNAME', '');
    if (!defined('MAILGUN_SMTP_PASSWORD')) define('MAILGUN_SMTP_PASSWORD', '');
    if (!defined('MAILGUN_SMTP_ENCRYPTION')) define('MAILGUN_SMTP_ENCRYPTION', 'tls');
    
    // Definir funciones por defecto si no existen
    if (!function_exists('mailgun_esta_configurado')) {
        function mailgun_esta_configurado() {
            return (
                !empty(MAILGUN_API_KEY) &&
                !empty(MAILGUN_DOMAIN) &&
                !empty(MAILGUN_BASE_URL) &&
                MAILGUN_API_KEY !== 'tu_api_key_aqui' &&
                MAILGUN_DOMAIN !== 'tu_dominio_mailgun_aqui'
            );
        }
    }
    
    if (!function_exists('mailgun_smtp_esta_configurado')) {
        function mailgun_smtp_esta_configurado() {
            return (
                !empty(MAILGUN_SMTP_HOST) &&
                !empty(MAILGUN_SMTP_PORT) &&
                !empty(MAILGUN_SMTP_USERNAME) &&
                !empty(MAILGUN_SMTP_PASSWORD) &&
                MAILGUN_SMTP_USERNAME !== 'tu_usuario_smtp_aqui' &&
                MAILGUN_SMTP_PASSWORD !== 'tu_contraseña_smtp_aqui'
            );
        }
    }
    
    if (!function_exists('mailgun_mensaje_configuracion')) {
        function mailgun_mensaje_configuracion() {
            if (!mailgun_esta_configurado()) {
                return "⚠️ ADVERTENCIA: La configuración de Mailgun no está completa. Edita config/mailgun.php con tus credenciales de Mailgun.";
            }
            return "";
        }
    }
}

// Verificar que las dependencias de Composer estén correctamente instaladas
$autoload_path = __DIR__ . '/vendor/autoload.php';
$vendor_dir = __DIR__ . '/vendor';

// Verificar que vendor/autoload.php existe
if (!file_exists($autoload_path)) {
    $_SESSION['mensaje_contacto'] = 'Error del sistema. Contacta al administrador.';
    $_SESSION['mensaje_contacto_tipo'] = 'danger';
    header('Location: index.php#contacto', true, 302);
    exit;
}

// Verificar paquetes críticos antes de cargar
$paquetes_criticos = [
    'phpmailer/phpmailer' => 'PHPMailer',
    'composer' => 'Composer autoloader'
];

foreach ($paquetes_criticos as $paquete => $nombre) {
    $ruta_paquete = $vendor_dir . '/' . $paquete;
    if (!file_exists($ruta_paquete) && !is_dir($ruta_paquete)) {
        $_SESSION['mensaje_contacto'] = 'Error de configuración. Contacta al administrador.';
        $_SESSION['mensaje_contacto_tipo'] = 'danger';
        header('Location: index.php#contacto', true, 302);
        exit;
    }
}

// Intentar cargar autoload.php con manejo de errores
try {
    require $autoload_path;
} catch (Error $e) {
    // Capturar errores de dependencias faltantes
    $mensaje_error = $e->getMessage();
    if (strpos($mensaje_error, 'Failed opening required') !== false || 
        strpos($mensaje_error, 'No such file or directory') !== false) {
        // Extraer el nombre del paquete faltante del mensaje de error
        preg_match('/vendor[\/\\\\]([^\/\\\\]+[\/\\\\][^\/\\\\]+)/', $mensaje_error, $matches);
        $paquete_faltante = isset($matches[1]) ? $matches[1] : 'desconocido';
        
        $_SESSION['mensaje_contacto'] = 'Error de configuración. Contacta al administrador.';
        $_SESSION['mensaje_contacto_tipo'] = 'danger';
        header('Location: index.php#contacto', true, 302);
        exit;
    }
    // Re-lanzar otros errores
    throw $e;
} catch (Exception $e) {
    $_SESSION['mensaje_contacto'] = 'Error del sistema. Contacta al administrador.';
    $_SESSION['mensaje_contacto_tipo'] = 'danger';
    header('Location: index.php#contacto', true, 302);
    exit;
}

// Incluir PHPMailer (después de verificar que autoload.php se cargó correctamente)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Incluir funciones de contacto
require_once __DIR__ . '/includes/contacto_functions.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensaje_contacto'] = 'Solicitud inválida. Intenta nuevamente.';
    $_SESSION['mensaje_contacto_tipo'] = 'danger';
    header('Location: index.php#contacto', true, 302);
    exit;
}

// Obtener valores del formulario
$nombre = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$asunto = isset($_POST['asunto']) ? trim($_POST['asunto']) : '';
$mensaje = isset($_POST['message']) ? trim($_POST['message']) : '';

// Sanitizar datos para el email
$nombre_sanitizado = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
$email_sanitizado = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$asunto_sanitizado = htmlspecialchars($asunto, ENT_QUOTES, 'UTF-8');
$mensaje_sanitizado = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');

// Mapear asunto a texto legible
$asuntos_map = [
    'problema_pagina' => 'Inconveniente técnico sitio web',
    'problema_producto' => 'Consulta sobre un producto',
    'problema_pago' => 'Duda sobre un pago',
    'problema_cuenta' => 'Mi Cuenta',
    'problema_pedido' => 'Consulta sobre un pedido'
];

$asunto_texto = isset($asuntos_map[$asunto_sanitizado]) ? $asuntos_map[$asunto_sanitizado] : (!empty($asunto_sanitizado) ? $asunto_sanitizado : 'Sin asunto');

// Preparar contenido del email
$subject_email = !empty($asunto_texto) ? 'Nuevo Mensaje de Contacto: ' . $asunto_texto : 'Nuevo Mensaje de Contacto';

$text_content = "Nuevo Mensaje de Contacto\n\n";
$text_content .= "Nombre: " . (!empty($nombre_sanitizado) ? $nombre_sanitizado : '(no proporcionado)') . "\n";
$text_content .= "Email: " . (!empty($email_sanitizado) ? $email_sanitizado : '(no proporcionado)') . "\n";
$text_content .= "Asunto: " . $asunto_texto . "\n";
$text_content .= "Mensaje:\n" . (!empty($mensaje_sanitizado) ? $mensaje_sanitizado : '(sin mensaje)') . "\n";

// Verificar que Mailgun SMTP esté configurado
if (!mailgun_smtp_esta_configurado()) {
    $_SESSION['mensaje_contacto'] = 'Error del servidor. Intenta más tarde.';
    $_SESSION['mensaje_contacto_tipo'] = 'danger';
    header('Location: index.php#contacto', true, 302);
    exit;
}

// Verificar que todas las constantes necesarias estén definidas
$constantes_requeridas = [
    'MAILGUN_SMTP_HOST',
    'MAILGUN_SMTP_PORT',
    'MAILGUN_SMTP_USERNAME',
    'MAILGUN_SMTP_PASSWORD',
    'MAILGUN_SMTP_ENCRYPTION',
    'MAILGUN_FROM_EMAIL',
    'MAILGUN_FROM_NAME',
    'MAILGUN_CONTACT_TO_EMAIL',
    'MAILGUN_CONTACT_TO_NAME'
];

foreach ($constantes_requeridas as $constante) {
    if (!defined($constante)) {
        $_SESSION['mensaje_contacto'] = 'Error de configuración. Contacta al administrador.';
        $_SESSION['mensaje_contacto_tipo'] = 'danger';
        header('Location: index.php#contacto', true, 302);
        exit;
    }
}

// Configuración de Mailgun SMTP desde archivo de config
$smtp_host = MAILGUN_SMTP_HOST;
$smtp_port = MAILGUN_SMTP_PORT;
$smtp_username = trim(MAILGUN_SMTP_USERNAME); // Eliminar espacios
$smtp_password = trim(MAILGUN_SMTP_PASSWORD); // Eliminar espacios
$smtp_encryption = MAILGUN_SMTP_ENCRYPTION;
$from_email = MAILGUN_FROM_EMAIL;
$from_name = MAILGUN_FROM_NAME;
$to_email = MAILGUN_CONTACT_TO_EMAIL;
$to_name = MAILGUN_CONTACT_TO_NAME;


// Intentar enviar email con Mailgun usando SMTP
try {
    // Crear instancia de PHPMailer
    $mail = new PHPMailer(true);
    
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
    $mail->addAddress($to_email, $to_name);
    
    // Contenido del email
    $mail->isHTML(false); // Email en texto plano
    $mail->Subject = $subject_email;
    $mail->Body = $text_content;
    $mail->CharSet = 'UTF-8';
    
    // Enviar email
    $mail->send();
    
    // Si llegamos aquí, el email se envió correctamente
    // Guardar registro del formulario
    $datos_registro = [
        'nombre' => $nombre,
        'email' => $email,
        'asunto' => $asunto,
        'mensaje' => $mensaje
    ];
    guardarRegistroContacto($datos_registro);
    
    $_SESSION['mensaje_contacto'] = '¡Mensaje enviado! Te responderemos pronto.';
    $_SESSION['mensaje_contacto_tipo'] = 'success';
    // Marcar que se envió un mensaje exitosamente (para evitar pre-llenar formulario)
    $_SESSION['mensaje_contacto_enviado'] = true;
    
} catch (Exception | Error $e) {
    // Capturar tanto Exception como Error (PHP 7+)
    // Log del error para debugging
    error_log("Error al enviar email de contacto: " . $e->getMessage());
    error_log("Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
    
    if (isset($mail) && !empty($mail->ErrorInfo)) {
        error_log("PHPMailer ErrorInfo: " . $mail->ErrorInfo);
    }
    
    // Error al enviar el email
    $_SESSION['mensaje_contacto'] = 'Error al enviar. Intenta más tarde.';
    $_SESSION['mensaje_contacto_tipo'] = 'danger';
}

// Redirigir a index.php (siempre, sin excepciones)
// Verificar que no se haya enviado output antes
if (!headers_sent()) {
    header('Location: index.php#contacto', true, 302);
    exit;
} else {
    // Si ya se envió output, intentar redirección con JavaScript
    echo '<script>window.location.href = "index.php#contacto";</script>';
    exit;
}
