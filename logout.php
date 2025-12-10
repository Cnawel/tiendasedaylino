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

// Incluir funciones de seguridad
require_once(__DIR__ . '/includes/security_functions.php');

// Usar función centralizada para destruir sesión de manera segura
destruirSesionSegura('login.php?logout=1');
