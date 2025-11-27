<?php
/**
 * ========================================================================
 * CONFIGURACIÓN DE GMAIL SMTP - EJEMPLO - Tienda Seda y Lino
 * ========================================================================
 * Este es un archivo de EJEMPLO. NO EDITAR este archivo.
 * 
 * INSTRUCCIONES:
 * 1. Copia este archivo como "gmail.php" en la misma carpeta
 * 2. Edita "gmail.php" con tus credenciales reales de Gmail
 * 3. El archivo "gmail.php" está en .gitignore (no se subirá al repo)
 * 
 * IMPORTANTE: Gmail requiere usar una "Contraseña de aplicación" (App Password)
 * NO uses tu contraseña regular de Gmail.
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 */

// ========================================================================
// CONFIGURACIÓN GMAIL SMTP
// ========================================================================

/**
 * Host del servidor SMTP de Gmail
 * Siempre: 'smtp.gmail.com'
 */
define('GMAIL_SMTP_HOST', 'smtp.gmail.com');

/**
 * Puerto SMTP de Gmail
 * - 587: TLS (recomendado)
 * - 465: SSL
 */
define('GMAIL_SMTP_PORT', 587);

/**
 * Tipo de encriptación SMTP
 * - 'tls': TLS (recomendado para puerto 587)
 * - 'ssl': SSL (para puerto 465)
 */
define('GMAIL_SMTP_ENCRYPTION', 'tls');

/**
 * Usuario SMTP de Gmail
 * Debe ser tu dirección de Gmail completa
 * Ejemplo: 'info.sedaylino@gmail.com'
 */
define('GMAIL_SMTP_USERNAME', 'tu_email@gmail.com');

/**
 * Contraseña SMTP de Gmail
 * ⚠️ IMPORTANTE: Debes usar una "Contraseña de aplicación" (App Password)
 * NO uses tu contraseña regular de Gmail.
 * 
 * Cómo obtener una Contraseña de aplicación:
 * 1. Ir a https://myaccount.google.com/security
 * 2. Activar "Verificación en 2 pasos" si no está activada
 * 3. Ir a "Contraseñas de aplicaciones"
 * 4. Generar nueva contraseña para "Correo" y "Otro (personalizado)" - escribir "SMTP"
 * 5. Copiar la contraseña generada (16 caracteres sin espacios)
 */
define('GMAIL_SMTP_PASSWORD', 'tu_contraseña_de_aplicacion_aqui');

/**
 * Email del remitente (FROM)
 * Debe ser la misma dirección de Gmail que usas como usuario SMTP
 */
define('GMAIL_FROM_EMAIL', 'tu_email@gmail.com');

/**
 * Nombre del remitente
 */
define('GMAIL_FROM_NAME', 'Seda y Lino - Tienda Online');

/**
 * Email destinatario para formularios de contacto
 */
define('GMAIL_CONTACT_TO_EMAIL', 'tu_email_destinatario@gmail.com');

/**
 * Nombre del destinatario
 */
define('GMAIL_CONTACT_TO_NAME', 'Facundo');

/**
 * Habilitar/Deshabilitar envío de emails
 * - true: Enviar emails
 * - false: No enviar (solo simular)
 */
define('GMAIL_ENABLED', true);

// ========================================================================
// FUNCIONES DE VERIFICACIÓN
// ========================================================================

/**
 * Verifica si Gmail SMTP está configurado correctamente
 * @return bool
 */
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

/**
 * Retorna un mensaje de advertencia si Gmail no está configurado
 * @return string
 */
function gmail_mensaje_configuracion() {
    if (!gmail_smtp_esta_configurado()) {
        return "⚠️ ADVERTENCIA: La configuración de Gmail SMTP no está completa. Edita config/gmail.php con tus credenciales de Gmail.";
    }
    return "";
}

?>

