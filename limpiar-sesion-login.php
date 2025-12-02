<?php
/**
 * ========================================================================
 * LIMPIAR SESIÓN LOGIN - Tienda Seda y Lino
 * ========================================================================
 * Script para limpiar datos corruptos en la sesión de login
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */
session_start();

// Limpiar datos de rate limiting corruptos
if (isset($_SESSION['login_attempts'])) {
    unset($_SESSION['login_attempts']);
}

echo "Sesión de login limpiada correctamente. <a href='login.php'>Volver al login</a>";
?>
