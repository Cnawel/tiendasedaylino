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

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la sesión (debe hacerse mientras la sesión está abierta)
session_destroy();

// Cerrar sesión después de destruir
session_write_close();

// Destruir la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Limpiar cualquier buffer de salida para asegurar redirección limpia
if (ob_get_level() > 0) {
    ob_end_clean();
}

// Redireccionar al login con mensaje de logout exitoso (redirección 302)
header('Location: login.php?logout=1', true, 302);
exit;
