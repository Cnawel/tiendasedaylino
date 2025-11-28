<?php
/**
 * ========================================================================
 * CONFIGURACIÓN DE MAILGUN - EJEMPLO - Tienda Seda y Lino
 * ========================================================================
 * Este es un archivo de EJEMPLO. NO EDITAR este archivo.
 * 
 * INSTRUCCIONES:
 * 1. Copia este archivo como "mailgun.php" en la misma carpeta
 * 2. Edita "mailgun.php" con tus credenciales reales de Mailgun
 * 3. El archivo "mailgun.php" está en .gitignore (no se subirá al repo)
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 */

// ========================================================================
// CONFIGURACIÓN MAILGUN API
// ========================================================================

/**
 * API Key de Mailgun (Sending Key)
 * Obtener desde: https://app.mailgun.com/app/account/security/api_keys
 * Ejemplo: 'key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
 */
define('MAILGUN_API_KEY', 'tu_api_key_aqui');

/**
 * Dominio de Mailgun (Sandbox o dominio verificado)
 * Ejemplo para sandbox: 'sandbox0bb806e4cff04df294135438b2e2bdcf.mailgun.org'
 * Ejemplo para dominio propio: 'mg.tudominio.com'
 */
define('MAILGUN_DOMAIN', 'tu_dominio_mailgun_aqui');

/**
 * Base URL de la API de Mailgun
 * - US: 'https://api.mailgun.net'
 * - EU: 'https://api.eu.mailgun.net'
 */
define('MAILGUN_BASE_URL', 'https://api.mailgun.net');

/**
 * Email del remitente (FROM)
 * Para sandbox debe ser: 'postmaster@tu_dominio_mailgun'
 */
define('MAILGUN_FROM_EMAIL', 'postmaster@tu_dominio_mailgun');

/**
 * Nombre del remitente
 */
define('MAILGUN_FROM_NAME', 'Mailgun Sandbox');

/**
 * Email destinatario para formularios de contacto
 * En sandbox, solo se pueden enviar a emails autorizados
 */
define('MAILGUN_CONTACT_TO_EMAIL', 'tu_email_autorizado@example.com');

/**
 * Nombre del destinatario
 */
define('MAILGUN_CONTACT_TO_NAME', 'Facundo');

/**
 * Habilitar/Deshabilitar envío de emails
 * - true: Enviar emails
 * - false: No enviar (solo simular)
 */
define('MAILGUN_ENABLED', true);

// ========================================================================
// CONFIGURACIÓN MAILGUN SMTP
// ========================================================================

/**
 * Host del servidor SMTP de Mailgun
 * Siempre: 'smtp.mailgun.org'
 */
define('MAILGUN_SMTP_HOST', 'smtp.mailgun.org');

/**
 * Puerto SMTP de Mailgun
 * - 587: TLS (recomendado)
 * - 465: SSL
 * - 25: Sin encriptación (no recomendado)
 * - 2525: Alternativo para algunos ISPs
 */
define('MAILGUN_SMTP_PORT', 587);

/**
 * Usuario SMTP de Mailgun
 * Formato: 'usuario@tu_dominio_mailgun'
 * Obtener desde: https://app.mailgun.com/app/sending/domains
 */
define('MAILGUN_SMTP_USERNAME', 'tu_usuario_smtp_aqui');

/**
 * Contraseña SMTP de Mailgun
 * ⚠️ IMPORTANTE: Este es el valor que debes cambiar en mailgun.php
 * Obtener desde: https://app.mailgun.com/app/sending/domains
 */
define('MAILGUN_SMTP_PASSWORD', 'tu_contraseña_smtp_aqui');

/**
 * Tipo de encriptación SMTP
 * - 'tls': TLS (recomendado para puerto 587)
 * - 'ssl': SSL (para puerto 465)
 */
define('MAILGUN_SMTP_ENCRYPTION', 'tls');

// ========================================================================
// FUNCIONES DE VERIFICACIÓN
// ========================================================================

/**
 * Verifica si Mailgun está configurado correctamente
 * @return bool
 */
function mailgun_esta_configurado() {
    return (
        MAILGUN_API_KEY !== 'tu_api_key_aqui' &&
        MAILGUN_DOMAIN !== 'tu_dominio_mailgun_aqui' &&
        !empty(MAILGUN_API_KEY) &&
        !empty(MAILGUN_DOMAIN) &&
        !empty(MAILGUN_BASE_URL)
    );
}

/**
 * Verifica si Mailgun SMTP está configurado correctamente
 * @return bool
 */
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

/**
 * Retorna un mensaje de advertencia si Mailgun no está configurado
 * @return string
 */
function mailgun_mensaje_configuracion() {
    if (!mailgun_esta_configurado()) {
        return "⚠️ ADVERTENCIA: La configuración de Mailgun no está completa. Edita config/mailgun.php con tus credenciales de Mailgun.";
    }
    return "";
}

?>

