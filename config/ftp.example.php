<?php
/**
 * Configuración de conexión FTP
 * 
 * Copiar este archivo a ftp.php y configurar con los datos de tu servidor FTP
 * 
 * @package TiendaSedayLino
 * @subpackage Config
 */

// Servidor FTP
define('FTP_HOST', 'ftp.tudominio.com');
define('FTP_PORT', 21);

// Credenciales
define('FTP_USERNAME', 'tu_usuario_ftp');
define('FTP_PASSWORD', 'tu_contraseña_ftp');

// Rutas
define('FTP_ROOT_PATH', '/public_html/');
define('FTP_UPLOAD_PATH', '/public_html/imagenes/productos/');

// Configuración
define('FTP_PASSIVE_MODE', true);  // true para modo pasivo, false para modo activo
define('FTP_SSL', false);           // true para FTPS (FTP sobre SSL)
define('FTP_TIMEOUT', 90);          // Timeout en segundos

?>

