<?php
/**
 * PÁGINA DE LOGOUT
 * Cierra la sesión del usuario y redirige al login
 */

session_start();

// Destruir la sesión
session_destroy();

// Redirigir al login con mensaje
header('Location: login.php?mensaje=logout');
exit;
?>

