<?php
/**
 * ========================================================================
 * FUNCIONES COMUNES DEL CARRITO - Tienda Seda y Lino
 * ========================================================================
 * Funciones helper reutilizables para operaciones del carrito de compras
 * 
 * FUNCIONES:
 * - tieneProductosReales(): Verifica si el carrito tiene productos reales (excluyendo _meta)
 * - calcularCantidadTotalCarrito(): Calcula la cantidad total de items en el carrito
 * - limpiarMensajesErrorObsoletos(): Limpia mensajes de error obsoletos de la sesión
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Verifica si el carrito tiene productos reales (excluyendo metadatos _meta)
 * 
 * @param array $carrito Array del carrito de sesión
 * @return bool True si tiene productos reales, false si está vacío
 */
function tieneProductosReales($carrito) {
    if (!isset($carrito) || !is_array($carrito) || empty($carrito)) {
        return false;
    }
    
    // Contar productos reales (excluyendo _meta)
    foreach ($carrito as $clave => $item) {
        if ($clave === '_meta') {
            continue;
        }
        // Verificar que el item tenga estructura válida
        if (isset($item['id_producto']) && isset($item['talla']) && 
            isset($item['color']) && isset($item['cantidad'])) {
            return true;
        }
    }
    
    return false;
}

/**
 * Calcula la cantidad total de items en el carrito
 * Suma todas las cantidades de cada variante
 * 
 * @param array $carrito Array del carrito
 * @return int Cantidad total de unidades en el carrito
 */
function calcularCantidadTotalCarrito($carrito) {
    if (!is_array($carrito) || empty($carrito)) {
        return 0;
    }
    
    $cantidad_total = 0;
    foreach ($carrito as $clave => $item) {
        // Saltar metadatos del carrito (_meta no es un producto)
        if ($clave === '_meta') {
            continue;
        }
        
        // Validar que el item tenga campo cantidad
        if (isset($item['cantidad']) && is_numeric($item['cantidad'])) {
            $cantidad_total += (int)$item['cantidad'];
        }
    }
    
    return $cantidad_total;
}

/**
 * Limpia mensajes de error obsoletos de la sesión
 * Útil para limpiar mensajes relacionados con login cuando el usuario ya está logueado
 * 
 * @param string $patron_buscar Patrón de texto a buscar en el mensaje de error
 * @return void
 */
function limpiarMensajesErrorObsoletos($patron_buscar = 'Debes iniciar sesión') {
    if (isset($_SESSION['mensaje_error']) && 
        strpos($_SESSION['mensaje_error'], $patron_buscar) !== false) {
        unset($_SESSION['mensaje_error']);
    }
}

