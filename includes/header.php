<?php
/**
 * ========================================================================
 * HEADER COMPLETO - Tienda Seda y Lino
 * ========================================================================
 * Incluye head completo (meta tags, CSS) + navegación
 * 
 * Uso: <?php include 'includes/header.php'; ?>
 * Antes debes definir: $titulo_pagina (obligatorio)
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

// Verificar que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Título por defecto si no se definió
if (!isset($titulo_pagina)) {
    $titulo_pagina = 'Seda y Lino';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php 
    include 'includes/head.php'; 
    render_head($titulo_pagina); 
    ?>
</head>
<body>
    <?php 
    // Verificar que auth_check.php esté disponible para navigation
    if (!function_exists('isLoggedIn')) {
        include 'includes/auth_check.php';
    }
    include 'includes/navigation.php'; 
    ?>

