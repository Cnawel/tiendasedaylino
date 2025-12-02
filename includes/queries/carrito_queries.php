<?php
/**
 * ========================================================================
 * CONSULTAS SQL DE CARRITO - Tienda Seda y Lino
 * ========================================================================
 * NOTA: Este archivo está DESHABILITADO porque la tabla Carrito NO existe
 * en la nueva estructura de base de datos (database_estructura.sql).
 * 
 * El carrito actual usa $_SESSION['carrito'] en lugar de base de datos.
 * Ver: carrito.php (usa $_SESSION, no BD)
 * 
 * Estas funciones se mantienen comentadas por compatibilidad pero NO se usan.
 * ========================================================================
 */

// TODO: Eliminar este archivo o mantenerlo comentado si se planea usar BD para carrito en el futuro

/*

/**
 * Obtiene todos los items del carrito de un usuario
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return array Array de items del carrito con información del producto
 */
function obtenerItemsCarrito($mysqli, $id_usuario) {
    $sql = "
        SELECT 
            c.id_item,
            c.id_producto,
            c.talle,
            c.color,
            c.cantidad,
            p.nombre_producto,
            p.precio_actual,
            p.genero,
            cat.nombre_categoria
        FROM Carrito c
        INNER JOIN Productos p ON c.id_producto = p.id_producto
        INNER JOIN Categorias cat ON p.id_categoria = cat.id_categoria
        WHERE c.id_usuario = ?
        ORDER BY c.id_item DESC
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('i', $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    $stmt->close();
    return $items;
}

/**
 * Obtiene un item específico del carrito por su ID
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_item ID del item del carrito
 * @return array|null Array con datos del item o null si no existe
 */
function obtenerItemCarritoPorId($mysqli, $id_item) {
    $sql = "
        SELECT 
            c.id_item,
            c.id_usuario,
            c.id_producto,
            c.talle,
            c.color,
            c.cantidad,
            p.nombre_producto,
            p.precio_actual
        FROM Carrito c
        INNER JOIN Productos p ON c.id_producto = p.id_producto
        WHERE c.id_item = ?
        LIMIT 1
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $id_item);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
    
    return $item;
}

/**
 * Verifica si un producto ya existe en el carrito del usuario
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @param int $id_producto ID del producto
 * @param string $talle Talle del producto
 * @param string $color Color del producto
 * @return array|null Array con datos del item existente o null si no existe
 */
function verificarItemExistenteCarrito($mysqli, $id_usuario, $id_producto, $talle, $color) {
    $sql = "
        SELECT id_item, cantidad
        FROM Carrito
        WHERE id_usuario = ?
        AND id_producto = ?
        AND talle = ?
        AND color = ?
        LIMIT 1
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('iiss', $id_usuario, $id_producto, $talle, $color);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
    
    return $item;
}

/**
 * Obtiene el total de items en el carrito de un usuario
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return int Cantidad total de items
 */
function contarItemsCarrito($mysqli, $id_usuario) {
    $sql = "
        SELECT COUNT(*) as total
        FROM Carrito
        WHERE id_usuario = ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param('i', $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return (int)($row['total'] ?? 0);
}

/**
 * Calcula el subtotal del carrito de un usuario
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return float Subtotal del carrito
 */
function calcularSubtotalCarrito($mysqli, $id_usuario) {
    $sql = "
        SELECT SUM(p.precio_actual * c.cantidad) as subtotal
        FROM Carrito c
        INNER JOIN Productos p ON c.id_producto = p.id_producto
        WHERE c.id_usuario = ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0.0;
    }
    
    $stmt->bind_param('i', $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return (float)($row['subtotal'] ?? 0.0);
}
*/

