<?php
/**
 * ========================================================================
 * LOGOUT - Tienda Seda y Lino
 * ========================================================================
 * Sistema de cierre de sesión seguro
 * 
 * Funcionalidades:
 * - Limpieza completa de la sesión
 * - Destrucción de cookies de sesión
 * - Redirección segura al login
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

// Evitar cualquier salida antes del redirect
ob_start();

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Limpiar buffer de salida
ob_end_clean();

// Redireccionar al login con mensaje de logout exitoso
header('Location: login.php?logout=1');
exit;
?>

