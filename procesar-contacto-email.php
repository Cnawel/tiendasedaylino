<?php
/**
 * ========================================================================
 * PROCESAR CONTACTO EMAIL (MAILGUN) - Tienda Seda y Lino
 * ========================================================================
 * Procesa el formulario de contacto y envía emails directamente mediante Mailgun
 * 
 * Funcionalidades:
 * - Envía email usando Mailgun API (admite campos vacíos)
 * - Redirige con mensaje de éxito o error
 * ========================================================================
 */

session_start();

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensaje_contacto_email'] = 'Método de solicitud no válido';
    $_SESSION['mensaje_contacto_email_tipo'] = 'danger';
    header('Location: test-formulario-email.php#formulario-email');
    exit;
}

// Obtener valores del formulario (pueden estar vacíos)
$nombre = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$asunto = isset($_POST['asunto']) ? trim($_POST['asunto']) : '';
$mensaje = isset($_POST['message']) ? trim($_POST['message']) : '';

// Sanitizar datos para el email (valores vacíos se mantienen como cadena vacía)
$nombre_sanitizado = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
$email_sanitizado = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$asunto_sanitizado = htmlspecialchars($asunto, ENT_QUOTES, 'UTF-8');
$mensaje_sanitizado = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');

// Intentar enviar email con Mailgun
try {
    // Include the Autoloader (see "Libraries" for install instructions)
    require __DIR__ . '/vendor/autoload.php';
    
    // Use the Mailgun class from mailgun/mailgun-php v4.2
    use Mailgun\Mailgun;
    
    // Instantiate the client.
    $mg = Mailgun::create(getenv('API_KEY') ?: 'API_KEY');
    // When you have an EU-domain, you must specify the endpoint:
    // $mg = Mailgun::create(getenv('API_KEY') ?: 'API_KEY', 'https://api.eu.mailgun.net');
    
    // Preparar contenido del email (puede estar vacío)
    $subject_text = !empty($asunto_sanitizado) ? $asunto_sanitizado : 'Sin asunto';
    $text_content = "Nuevo Mensaje de Contacto\n\n";
    $text_content .= "Nombre: " . (!empty($nombre_sanitizado) ? $nombre_sanitizado : '(vacío)') . "\n";
    $text_content .= "Email: " . (!empty($email_sanitizado) ? $email_sanitizado : '(vacío)') . "\n";
    $text_content .= "Asunto: " . (!empty($asunto_sanitizado) ? $asunto_sanitizado : '(vacío)') . "\n";
    $text_content .= "Mensaje:\n" . (!empty($mensaje_sanitizado) ? $mensaje_sanitizado : '(vacío)') . "\n";
    
    // Compose and send your message.
    $result = $mg->messages()->send(
        'sandbox0bb806e4cff04df294135438b2e2bdcf.mailgun.org',
        [
            'from' => 'Mailgun Sandbox <postmaster@sandbox0bb806e4cff04df294135438b2e2bdcf.mailgun.org>',
            'to' => 'Facundo <info.sedaylino@gmail.com>',
            'subject' => 'Hello Facundo',
            'text' => 'Congratulations Facundo, you just sent an email with Mailgun! You are truly awesome!'
        ]
    );
    
    // Si llegamos aquí, el email se envió correctamente
    $_SESSION['mensaje_contacto_email'] = 'Mensaje enviado correctamente por email. Nos comunicaremos contigo pronto.';
    $_SESSION['mensaje_contacto_email_tipo'] = 'success';
    
} catch (Exception $e) {
    // Error al enviar el email
    $_SESSION['mensaje_contacto_email'] = 'Error al enviar el mensaje: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $_SESSION['mensaje_contacto_email_tipo'] = 'danger';
}

// Redirigir a la página de testing
header('Location: test-formulario-email.php#formulario-email');
exit;

