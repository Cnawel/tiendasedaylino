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
// NOTA: Esta verificación previene múltiples llamadas a session_start()
// auth_check.php (incluido más abajo) también verifica antes de iniciar sesión.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Título por defecto si no se definió
if (!isset($titulo_pagina)) {
    $titulo_pagina = 'Seda y Lino';
}

// Detectar si estamos en un panel administrativo para aplicar tema específico
$is_panel_page = false;
$current_page = basename($_SERVER['PHP_SELF']);
$panel_pages = ['admin.php', 'ventas.php', 'marketing.php'];

if (in_array($current_page, $panel_pages)) {
    $is_panel_page = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php 
    include_once 'includes/head.php'; 
    render_head($titulo_pagina); 
    ?>
</head>
<body<?php echo $is_panel_page ? ' class="panel-theme"' : ''; ?>>
    <?php 
    // Verificar que auth_check.php esté disponible para navigation
    if (!function_exists('isLoggedIn')) {
        include_once 'includes/auth_check.php';
    }
    include_once 'includes/navigation.php'; 
    ?>

