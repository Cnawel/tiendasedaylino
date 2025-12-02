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

// Mostrar todos los errores (archivo de debug)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Incluir configuración de Mailgun
require_once __DIR__ . '/config/mailgun.php';

// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/vendor/autoload.php';

// Verificar que sea una petición POST (antes de iniciar HTML para evitar "headers already sent")
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensaje_contacto'] = 'Método de solicitud no válido';
    $_SESSION['mensaje_contacto_tipo'] = 'danger';
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>DEBUG - Error</title>";
    echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;}";
    echo ".error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:20px;border-radius:5px;margin:20px 0;}";
    echo "button{background:#007bff;color:#fff;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;font-size:16px;margin-top:20px;}";
    echo "</style></head><body>";
    echo "<div class='error'>";
    echo "<h2>❌ ERROR - Método de Solicitud No Válido</h2>";
    echo "<p>El método de solicitud debe ser POST. Método recibido: " . htmlspecialchars($_SERVER['REQUEST_METHOD']) . "</p>";
    echo "</div>";
    echo "<button onclick='window.location.href=\"index.php#contacto\"'>Volver al Formulario</button>";
    echo "</body></html>";
    exit;
}

// Mostrar información inicial de la petición
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>DEBUG - Procesar Contacto</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;}";
echo "h2{color:#333;border-bottom:2px solid #007bff;padding-bottom:10px;}";
echo "pre{background:#fff;padding:15px;border:1px solid #ddd;border-radius:5px;overflow-x:auto;}";
echo ".debug-section{margin:20px 0;background:#fff;padding:20px;border-radius:5px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";
echo ".success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;}";
echo ".error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;}";
echo ".info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;}";
echo "button{background:#007bff;color:#fff;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;font-size:16px;margin-top:20px;}";
echo "button:hover{background:#0056b3;}</style></head><body>";
echo "<div class='debug-section info'>";
echo "<h2>🔍 DEBUG - Información de la Petición</h2>";
echo "<pre>";
echo "REQUEST_METHOD: " . htmlspecialchars($_SERVER['REQUEST_METHOD']) . "\n";
echo "REQUEST_URI: " . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "SCRIPT_NAME: " . htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "\n";
echo "HTTP_HOST: " . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'N/A') . "\n";
echo "</pre>";
echo "</div>";

// Mostrar $_POST completo
echo "<div class='debug-section info'>";
echo "<h2>📝 Datos POST Recibidos</h2>";
echo "<pre>";
echo "POST completo:\n";
print_r($_POST);
echo "\n\nPOST procesado:\n";
echo "name: " . (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : 'NO DEFINIDO') . "\n";
echo "email: " . (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : 'NO DEFINIDO') . "\n";
echo "asunto: " . (isset($_POST['asunto']) ? htmlspecialchars($_POST['asunto']) : 'NO DEFINIDO') . "\n";
echo "message: " . (isset($_POST['message']) ? htmlspecialchars($_POST['message']) : 'NO DEFINIDO') . "\n";
echo "</pre>";
echo "</div>";

// Obtener valores del formulario
$nombre = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$asunto = isset($_POST['asunto']) ? trim($_POST['asunto']) : '';
$mensaje = isset($_POST['message']) ? trim($_POST['message']) : '';

// Mostrar datos recibidos
echo "<div class='debug-section info'>";
echo "<h2>📋 Datos del Formulario Procesados</h2>";
echo "<pre>";
echo "Nombre: " . htmlspecialchars($nombre) . "\n";
echo "Email: " . htmlspecialchars($email) . "\n";
echo "Asunto: " . htmlspecialchars($asunto) . "\n";
echo "Mensaje: " . htmlspecialchars($mensaje) . "\n";
echo "</pre>";
echo "</div>";

// Sanitizar datos para el email
$nombre_sanitizado = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
$email_sanitizado = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$asunto_sanitizado = htmlspecialchars($asunto, ENT_QUOTES, 'UTF-8');
$mensaje_sanitizado = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');

// Mapear asunto a texto legible
$asuntos_map = [
    'problema_pagina' => 'Problema con Página',
    'problema_producto' => 'Problema con Producto',
    'problema_pago' => 'Problema con Pago',
    'problema_cuenta' => 'Problema con Cuenta (Clientes)',
    'problema_pedido' => 'Problema con Pedido'
];

$asunto_texto = isset($asuntos_map[$asunto_sanitizado]) ? $asuntos_map[$asunto_sanitizado] : (!empty($asunto_sanitizado) ? $asunto_sanitizado : 'Sin asunto');

// Preparar contenido del email
$subject_email = !empty($asunto_texto) ? 'Nuevo Mensaje de Contacto: ' . $asunto_texto : 'Nuevo Mensaje de Contacto';

$text_content = "Nuevo Mensaje de Contacto\n\n";
$text_content .= "Nombre: " . (!empty($nombre_sanitizado) ? $nombre_sanitizado : '(no proporcionado)') . "\n";
$text_content .= "Email: " . (!empty($email_sanitizado) ? $email_sanitizado : '(no proporcionado)') . "\n";
$text_content .= "Asunto: " . $asunto_texto . "\n";
$text_content .= "Mensaje:\n" . (!empty($mensaje_sanitizado) ? $mensaje_sanitizado : '(sin mensaje)') . "\n";

// Verificar estado de configuración de Mailgun
echo "<div class='debug-section info'>";
echo "<h2>⚙️ Estado de Configuración Mailgun</h2>";
echo "<pre>";
echo "mailgun_smtp_esta_configurado(): " . (mailgun_smtp_esta_configurado() ? 'TRUE' : 'FALSE') . "\n";
echo "mailgun_esta_configurado(): " . (mailgun_esta_configurado() ? 'TRUE' : 'FALSE') . "\n";
echo "</pre>";
echo "</div>";

// Verificar que Mailgun SMTP esté configurado
if (!mailgun_smtp_esta_configurado()) {
    echo "<div class='debug-section error'>";
    echo "<h2>❌ ERROR - Mailgun SMTP no está configurado</h2>";
    echo "<p>Edita config/mailgun.php con tus credenciales SMTP.</p>";
    echo "</div>";
    echo "<button onclick='window.location.href=\"index.php#contacto\"'>Volver al Formulario</button>";
    echo "</body></html>";
    exit;
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

// Verificación detallada de credenciales SMTP
echo "<div class='debug-section info'>";
echo "<h2>🔐 Verificación de Credenciales SMTP</h2>";
echo "<pre>";

// Verificar formato del username SMTP
$username_valido = true;
$errores_username = [];
if (empty($smtp_username)) {
    $username_valido = false;
    $errores_username[] = "❌ Username SMTP está VACÍO";
} else {
    echo "✅ Username SMTP no está vacío\n";
    echo "   Valor: " . htmlspecialchars($smtp_username) . "\n";
    
    // Verificar formato email
    if (!filter_var($smtp_username, FILTER_VALIDATE_EMAIL)) {
        $username_valido = false;
        $errores_username[] = "❌ Username SMTP no tiene formato de email válido";
    } else {
        echo "✅ Username SMTP tiene formato de email válido\n";
    }
    
    // Verificar que contenga el dominio de Mailgun
    if (strpos($smtp_username, '@') !== false) {
        $dominio_username = substr($smtp_username, strpos($smtp_username, '@') + 1);
        echo "   Dominio extraído del username: " . htmlspecialchars($dominio_username) . "\n";
        
        // Verificar coherencia con MAILGUN_DOMAIN
        if (strpos($dominio_username, MAILGUN_DOMAIN) === false) {
            $username_valido = false;
            $errores_username[] = "⚠️ ADVERTENCIA: El dominio del username no coincide con MAILGUN_DOMAIN";
            echo "   ⚠️ Dominio del username: " . htmlspecialchars($dominio_username) . "\n";
            echo "   ⚠️ MAILGUN_DOMAIN esperado: " . htmlspecialchars(MAILGUN_DOMAIN) . "\n";
        } else {
            echo "✅ Dominio del username coincide con MAILGUN_DOMAIN\n";
        }
    }
    
    // Verificar valores por defecto
    if ($smtp_username === 'tu_usuario_smtp_aqui' || 
        $smtp_username === 'postmaster@' . MAILGUN_DOMAIN) {
        $username_valido = false;
        $errores_username[] = "❌ Username SMTP parece ser un valor por defecto";
    }
}

// Verificar contraseña SMTP
$password_valido = true;
$errores_password = [];
if (empty($smtp_password)) {
    $password_valido = false;
    $errores_password[] = "❌ Password SMTP está VACÍO";
} else {
    echo "\n✅ Password SMTP no está vacío\n";
    $password_length = strlen($smtp_password);
    echo "   Longitud: " . $password_length . " caracteres\n";
    
    // Verificar longitud mínima (Mailgun generalmente requiere al menos 8 caracteres)
    if ($password_length < 8) {
        $password_valido = false;
        $errores_password[] = "⚠️ ADVERTENCIA: Password muy corto (menos de 8 caracteres)";
    } else {
        echo "✅ Password tiene longitud adecuada (>= 8 caracteres)\n";
    }
    
    // Verificar valores por defecto
    if ($smtp_password === 'tu_contraseña_smtp_aqui' || 
        $smtp_password === 'tu_contraseña_aqui') {
        $password_valido = false;
        $errores_password[] = "❌ Password SMTP parece ser un valor por defecto";
    }
    
    // Mostrar primeros y últimos caracteres (enmascarado)
    if ($password_length > 4) {
        $primeros = substr($smtp_password, 0, 2);
        $ultimos = substr($smtp_password, -2);
        echo "   Primeros 2 caracteres: " . htmlspecialchars($primeros) . "***\n";
        echo "   Últimos 2 caracteres: ***" . htmlspecialchars($ultimos) . "\n";
    }
    
    // Verificar caracteres especiales comunes en contraseñas
    $tiene_mayuscula = preg_match('/[A-Z]/', $smtp_password);
    $tiene_minuscula = preg_match('/[a-z]/', $smtp_password);
    $tiene_numero = preg_match('/[0-9]/', $smtp_password);
    $tiene_especial = preg_match('/[^A-Za-z0-9]/', $smtp_password);
    
    echo "\n   Análisis de caracteres:\n";
    echo "   - Mayúsculas: " . ($tiene_mayuscula ? "✅ Sí" : "❌ No") . "\n";
    echo "   - Minúsculas: " . ($tiene_minuscula ? "✅ Sí" : "❌ No") . "\n";
    echo "   - Números: " . ($tiene_numero ? "✅ Sí" : "❌ No") . "\n";
    echo "   - Especiales: " . ($tiene_especial ? "✅ Sí" : "❌ No") . "\n";
}

// Verificar host y puerto
echo "\n📡 Configuración de conexión:\n";
echo "   Host: " . htmlspecialchars($smtp_host) . "\n";
if ($smtp_host !== 'smtp.mailgun.org') {
    echo "   ⚠️ ADVERTENCIA: Host no es el estándar de Mailgun (smtp.mailgun.org)\n";
} else {
    echo "   ✅ Host es el correcto para Mailgun\n";
}

echo "   Puerto: " . htmlspecialchars($smtp_port) . "\n";
if ($smtp_port == 587 && $smtp_encryption !== 'tls') {
    echo "   ⚠️ ADVERTENCIA: Puerto 587 generalmente requiere TLS, pero encriptación está en: " . htmlspecialchars($smtp_encryption) . "\n";
} elseif ($smtp_port == 465 && $smtp_encryption !== 'ssl') {
    echo "   ⚠️ ADVERTENCIA: Puerto 465 generalmente requiere SSL, pero encriptación está en: " . htmlspecialchars($smtp_encryption) . "\n";
} else {
    echo "   ✅ Puerto y encriptación son coherentes\n";
}

// Resumen de validación
echo "\n📊 RESUMEN DE VALIDACIÓN:\n";
if ($username_valido && $password_valido) {
    echo "   ✅ Credenciales SMTP parecen estar correctamente configuradas\n";
} else {
    echo "   ❌ PROBLEMAS DETECTADOS EN LAS CREDENCIALES:\n";
    if (!$username_valido) {
        foreach ($errores_username as $error) {
            echo "   " . $error . "\n";
        }
    }
    if (!$password_valido) {
        foreach ($errores_password as $error) {
            echo "   " . $error . "\n";
        }
    }
    echo "\n   📖 CÓMO OBTENER LAS CREDENCIALES CORRECTAS:\n";
    echo "   1. Inicia sesión en https://app.mailgun.com\n";
    echo "   2. Ve a 'Sending' > 'Domains'\n";
    echo "   3. Selecciona tu dominio (o sandbox)\n";
    echo "   4. Ve a la pestaña 'SMTP credentials'\n";
    echo "   5. Copia el 'Default SMTP login' (formato: usuario@dominio.mailgun.org)\n";
    echo "   6. Copia el 'Default password' (o crea uno nuevo)\n";
    echo "   7. Actualiza config/mailgun.php con estos valores\n";
}

echo "</pre>";
echo "</div>";

// Si el usuario personalizado no funciona, intentar con postmaster por defecto
// Comentar la siguiente línea si quieres usar solo el usuario personalizado
// $smtp_username = 'postmaster@' . MAILGUN_DOMAIN;

// Intentar enviar email con Mailgun usando SMTP
try {
    // Crear instancia de PHPMailer
    $mail = new PHPMailer(true);
    
    // Mostrar información de configuración SMTP
    echo "<div class='debug-section info'>";
    echo "<h2>📧 Configuración SMTP</h2>";
    echo "<pre>";
    echo "Host: " . htmlspecialchars($smtp_host) . "\n";
    echo "Puerto: " . htmlspecialchars($smtp_port) . "\n";
    echo "Encriptación: " . htmlspecialchars($smtp_encryption) . "\n";
    echo "Usuario: " . htmlspecialchars($smtp_username) . "\n";
    echo "Password: " . (strlen($smtp_password) > 0 ? str_repeat('*', min(strlen($smtp_password), 10)) . ' (' . strlen($smtp_password) . ' caracteres)' : 'VACÍO') . "\n";
    echo "From Email: " . htmlspecialchars($from_email) . "\n";
    echo "From Name: " . htmlspecialchars($from_name) . "\n";
    echo "To Email: " . htmlspecialchars($to_email) . "\n";
    echo "To Name: " . htmlspecialchars($to_name) . "\n";
    echo "Subject: " . htmlspecialchars($subject_email) . "\n";
    echo "</pre>";
    echo "</div>";
    
    // Configuración del servidor SMTP
    $mail->isSMTP();
    $mail->Host = $smtp_host;
    $mail->SMTPAuth = true;
    $mail->Username = $smtp_username;
    $mail->Password = $smtp_password;
    $mail->SMTPSecure = $smtp_encryption;
    $mail->Port = $smtp_port;
    
    // Configuración de debug SMTP
    $mail->SMTPDebug = 2; // 0 = off, 1 = client, 2 = client and server
    $mail->Debugoutput = function($str, $level) {
        echo "<div class='debug-section info' style='margin-top:10px;'>";
        echo "<pre style='background: #f0f0f0; padding: 5px; font-size: 11px; margin:0;'>";
        echo "SMTP Debug: " . htmlspecialchars($str);
        echo "</pre>";
        echo "</div>";
    };
    
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
    
    // Email enviado exitosamente
    echo "<div class='debug-section success'>";
    echo "<h2>✅ Email Enviado Exitosamente</h2>";
    echo "<pre>";
    echo "El email fue enviado correctamente a través de Mailgun SMTP.\n";
    echo "Destinatario: " . htmlspecialchars($to_email) . "\n";
    echo "Asunto: " . htmlspecialchars($subject_email) . "\n";
    echo "PHPMailer ErrorInfo: " . (isset($mail->ErrorInfo) && !empty($mail->ErrorInfo) ? htmlspecialchars($mail->ErrorInfo) : 'Ninguno') . "\n";
    echo "</pre>";
    echo "</div>";
    
    $_SESSION['mensaje_contacto'] = 'Mensaje enviado correctamente. Nos comunicaremos contigo pronto.';
    $_SESSION['mensaje_contacto_tipo'] = 'success';
    
} catch (Exception $e) {
    // Mostrar error completo
    echo "<div class='debug-section error'>";
    echo "<h2>❌ Error al Enviar Email</h2>";
    echo "<pre>";
    echo "Excepción: " . htmlspecialchars($e->getMessage()) . "\n\n";
    if (isset($mail) && !empty($mail->ErrorInfo)) {
        echo "PHPMailer ErrorInfo: " . htmlspecialchars($mail->ErrorInfo) . "\n\n";
    }
    echo "Código de error: " . $e->getCode() . "\n\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n\n";
    echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString());
    echo "</pre>";
    echo "</div>";
    
    // Log del error para debugging
    error_log("Error al enviar email de contacto: " . $e->getMessage());
    if (isset($mail) && !empty($mail->ErrorInfo)) {
        error_log("PHPMailer ErrorInfo: " . $mail->ErrorInfo);
    }
    
    // Error al enviar el email
    $_SESSION['mensaje_contacto'] = 'Error al enviar el mensaje. Por favor, intenta nuevamente más tarde.';
    $_SESSION['mensaje_contacto_tipo'] = 'danger';
}

// Mostrar resumen final
echo "<div class='debug-section info'>";
echo "<h2>📊 Resumen Final</h2>";
echo "<pre>";
echo "Mensaje de sesión configurado: " . (isset($_SESSION['mensaje_contacto']) ? htmlspecialchars($_SESSION['mensaje_contacto']) : 'NO CONFIGURADO') . "\n";
echo "Tipo de mensaje: " . (isset($_SESSION['mensaje_contacto_tipo']) ? htmlspecialchars($_SESSION['mensaje_contacto_tipo']) : 'NO CONFIGURADO') . "\n";
echo "</pre>";
echo "</div>";

// Botón para continuar (redirección manual)
echo "<div style='text-align:center; margin-top:30px;'>";
echo "<button onclick='window.location.href=\"index.php#contacto\"'>Continuar a Index.php</button>";
echo "</div>";

echo "</body></html>";

// Redirigir a index.php (comentado para debug)
// header('Location: index.php#contacto');
// exit;
