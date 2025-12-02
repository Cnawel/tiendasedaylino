<?php
/**
 * ========================================================================
 * LIMPIAR SESIÓN PEDIDO - Tienda Seda y Lino
 * ========================================================================
 * Script auxiliar para limpiar datos del pedido de la sesión
 * Se llama desde JavaScript cuando el usuario sale de la página de confirmación
 * 
 * @author Tienda Seda y Lino
 * @version 1.0
 */

session_start();

// Limpiar datos del pedido exitoso
if (isset($_SESSION['pedido_exitoso'])) {
    unset($_SESSION['pedido_exitoso']);
}

// No retornar nada, solo limpiar la sesión
?>

