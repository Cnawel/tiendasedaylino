<?php
/**
 * Helper compartido: consultas relacionadas a `Detalle_Pedido`
 *
 * Centraliza `obtenerDetallesPedido()` para evitar duplicación entre
 * `pedido_queries.php` y `stock_queries.php`.
 */

/**
 * Obtiene los detalles de un pedido (productos incluidos)
 *
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pedido ID del pedido
 * @return array Array de detalles del pedido con información del producto
 */
function obtenerDetallesPedido($mysqli, $id_pedido) {
    // Query: Obtener detalles básicos del pedido
    $sql = "
        SELECT 
            dp.id_detalle,
            dp.id_variante,
            dp.cantidad,
            dp.precio_unitario,
            p.nombre_producto,
            sv.talle,
            sv.color
        FROM Detalle_Pedido dp
        INNER JOIN Stock_Variantes sv ON dp.id_variante = sv.id_variante
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        WHERE dp.id_pedido = ?
        ORDER BY dp.id_detalle
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('i', $id_pedido);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $detalles = [];
    while ($row = $result->fetch_assoc()) {
        $detalles[] = $row;
    }
    $stmt->close();
    
    return $detalles;
}
