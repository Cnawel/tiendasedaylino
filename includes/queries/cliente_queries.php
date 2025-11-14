<?php
/**
 * ========================================================================
 * CONSULTAS SQL DE CLIENTES - Tienda Seda y Lino
 * ========================================================================
 * Archivo centralizado con todas las consultas relacionadas a clientes
 * 
 * Uso:
 *   require_once __DIR__ . '/includes/queries/cliente_queries.php';
 *   $clientes = obtenerClientesConPedidos($mysqli);
 * ========================================================================
 */

/**
 * Obtiene todos los clientes (usuarios con rol 'cliente') con el total de pedidos realizados
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array asociativo con datos de clientes y total de pedidos
 */
function obtenerClientesConPedidos($mysqli) {
    $sql = "
        SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.telefono, u.direccion, u.fecha_registro,
               COUNT(p.id_pedido) as total_pedidos
        FROM Usuarios u
        LEFT JOIN Pedidos p ON u.id_usuario = p.id_usuario
        WHERE u.rol = 'cliente'
        GROUP BY u.id_usuario, u.nombre, u.apellido, u.email, u.telefono, u.direccion, u.fecha_registro
        ORDER BY u.apellido, u.nombre
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $clientes = [];
    while ($row = $resultado->fetch_assoc()) {
        $clientes[] = $row;
    }
    
    $stmt->close();
    return $clientes;
}

