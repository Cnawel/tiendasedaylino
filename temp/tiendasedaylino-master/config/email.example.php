<?php
/**
 * ========================================================================
 * CONFIGURACIÓN DE EMAIL - EJEMPLO - Tienda Seda y Lino
 * ========================================================================
 * Este es un archivo de EJEMPLO. NO EDITAR este archivo.
 * 
 * INSTRUCCIONES:
 * 1. Copia este archivo como "email.php" en la misma carpeta
 * 2. Edita "email.php" con tus credenciales reales
 * 3. El archivo "email.php" está en .gitignore (no se subirá al repo)
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 */

// ========================================================================
// CONFIGURACIÓN SMTP
// ========================================================================

/**
 * Host del servidor SMTP
 * Ejemplos:
 * - cPanel: 'smtp.tudominio.com' o 'mail.tudominio.com'
 * - Gmail: 'smtp.gmail.com'
 * - Outlook: 'smtp-mail.outlook.com'
 */
define('SMTP_HOST', 'smtp.tudominio.com');

/**
 * Puerto SMTP
 * - 587: TLS (recomendado)
 * - 465: SSL
 * - 25: Sin encriptación (no recomendado)
 */
define('SMTP_PORT', 587);

/**
 * Tipo de encriptación
 * - 'tls': TLS (recomendado para puerto 587)
 * - 'ssl': SSL (para puerto 465)
 * - '': Sin encriptación (no recomendado)
 */
define('SMTP_ENCRYPTION', 'tls');

/**
 * Usuario SMTP (generalmente el email completo)
 * Ejemplo: 'info@sedaylino.com'
 */
define('SMTP_USERNAME', 'info@sedaylino.com');

/**
 * Contraseña del email
 * ⚠️ IMPORTANTE: Este es el valor que debes cambiar en email.php
 */
define('SMTP_PASSWORD', 'tu_contraseña_aqui');

/**
 * Email del remitente (FROM)
 */
define('EMAIL_FROM', 'info@sedaylino.com');

/**
 * Nombre del remitente
 */
define('EMAIL_FROM_NAME', 'Seda y Lino - Tienda Online');

/**
 * Email de respuesta (REPLY-TO)
 */
define('EMAIL_REPLY_TO', 'info@sedaylino.com');

/**
 * Email de copia oculta (BCC) para la tienda
 * Dejar vacío ('') si no se desea
 */
define('EMAIL_BCC_ADMIN', 'info@sedaylino.com');

/**
 * Habilitar/Deshabilitar envío de emails
 * - true: Enviar emails
 * - false: No enviar (solo simular)
 */
define('EMAIL_ENABLED', true);

/**
 * Modo debug de emails
 * - 0: Sin debug
 * - 1: Errores y mensajes
 * - 2: Mensajes del cliente
 * - 3: Mensajes del cliente y servidor
 * - 4: Mensajes de bajo nivel
 */
define('EMAIL_DEBUG', 0);

// ========================================================================
// FUNCIONES DE VERIFICACIÓN
// ========================================================================

function email_esta_configurado() {
    return (
        SMTP_HOST !== 'smtp.tudominio.com' &&
        SMTP_USERNAME !== 'info@sedaylino.com' &&
        SMTP_PASSWORD !== 'tu_contraseña_aqui' &&
        !empty(SMTP_HOST) &&
        !empty(SMTP_USERNAME) &&
        !empty(SMTP_PASSWORD)
    );
}

function email_mensaje_configuracion() {
    if (!email_esta_configurado()) {
        return "⚠️ ADVERTENCIA: La configuración de email no está completa. Edita config/email.php con los datos de tu servidor SMTP.";
    }
    return "";
}

?>

