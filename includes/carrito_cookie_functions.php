<?php
/**
 * ========================================================================
 * FUNCIONES DE COOKIES PARA CARRITO - Tienda Seda y Lino
 * ========================================================================
 * Funciones helper para persistir el carrito de compras en cookies
 * Permite mantener el carrito entre sesiones y después del logout
 * 
 * Funcionalidades:
 * - Guardar carrito en cookie JSON
 * - Restaurar carrito desde cookie
 * - Limpiar cookie del carrito
 * - Validar y sanitizar datos del carrito
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Nombre base de la cookie donde se guarda el carrito
 * Se añade el ID de usuario o 'guest' para usuarios no logueados
 */
define('CARRITO_COOKIE_NAME_BASE', 'tienda_carrito');

/**
 * Duración de la cookie del carrito (30 días en segundos)
 */
define('CARRITO_COOKIE_EXPIRE', 30 * 24 * 60 * 60);

/**
 * Tamaño máximo de la cookie (4KB)
 */
define('CARRITO_COOKIE_MAX_SIZE', 4096);

/**
 * Obtiene el nombre de la cookie según el usuario
 * 
 * @param int|null $id_usuario ID del usuario (null para usuarios no logueados)
 * @return string Nombre de la cookie
 */
function obtenerNombreCookieCarrito($id_usuario = null) {
    // LÓGICA DE NEGOCIO: Genera nombre único de cookie según el usuario.
    // REGLA DE NEGOCIO: Usuarios logueados tienen cookie personalizada, usuarios no logueados usan 'guest'.
    // LÓGICA: Permite múltiples carritos simultáneos (diferentes usuarios en el mismo navegador).
    
    // Si hay usuario logueado, usar su ID para crear cookie única
    // REGLA: Cookie personalizada por usuario para evitar conflictos entre sesiones
    if ($id_usuario !== null && $id_usuario > 0) {
        return CARRITO_COOKIE_NAME_BASE . '_' . (int)$id_usuario;
    }
    
    // Si no hay usuario (guest), usar cookie genérica
    // REGLA: Usuarios no logueados comparten cookie 'guest' para persistencia básica
    return CARRITO_COOKIE_NAME_BASE . '_guest';
}

/**
 * Limpia la cookie del carrito
 *
 * @param int|null $id_usuario ID del usuario (null para usuarios no logueados)
 * @return bool True si se eliminó correctamente
 */
function limpiarCarritoCookie($id_usuario = null) {
    // Obtener nombre de cookie según usuario
    $nombre_cookie = obtenerNombreCookieCarrito($id_usuario);

    // Eliminar cookie estableciendo expiración en el pasado
    return setcookie(
        $nombre_cookie,
        '',
        time() - 3600,
        '/',
        '',
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        false
    );
}

/**
 * Calcula la cantidad total de items en el carrito
 * Suma todas las cantidades de cada variante (no cuenta tipos únicos)
 * 
 * @param array $carrito Array del carrito con items que tienen campo 'cantidad'
 * @return int Cantidad total de unidades en el carrito (0 si está vacío o inválido)
 */
function calcularCantidadTotalCarrito($carrito) {
    // LÓGICA DE NEGOCIO: Calcula la suma total de todas las cantidades de items.
    // REGLA DE NEGOCIO: El contador debe mostrar unidades totales, no tipos de productos únicos.
    // LÓGICA: Permite mostrar "10 items" cuando hay 1 tipo de producto con cantidad 10.
    
    // VALIDACIÓN 1: Verificar que sea un array
    // REGLA: El carrito debe ser un array para poder procesarlo
    if (!is_array($carrito) || empty($carrito)) {
        return 0;
    }
    
    // Sumar todas las cantidades de los items del carrito
    // REGLA: Cada item debe tener campo 'cantidad' para ser contado
    // LÓGICA: Si un item no tiene cantidad válida, se omite (no suma 0)
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

