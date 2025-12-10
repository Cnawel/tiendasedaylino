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
 * REFACTORIZACIÓN: Esta función usa el patrón de múltiples queries simples
 * en lugar de LEFT JOIN + GROUP BY complejo. Divide la lógica en:
 * - Query 1: Obtener todos los clientes (simple SELECT)
 * - Query 2: Para cada cliente, contar sus pedidos (COUNT simple)
 * - PHP: Combinar resultados en array asociativo
 *
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array asociativo con datos de clientes y total de pedidos
 */
function obtenerClientesConPedidos($mysqli) {
    // Query 1: Obtener todos los clientes (sin JOINs, sin GROUP BY)
    $sql_clientes = "
        SELECT id_usuario, nombre, apellido, email, telefono, direccion, fecha_registro
        FROM Usuarios
        WHERE rol = 'cliente'
        ORDER BY apellido, nombre
    ";

    $stmt_clientes = $mysqli->prepare($sql_clientes);
    if (!$stmt_clientes) {
        error_log("ERROR obtenerClientesConPedidos - prepare clientes falló: " . $mysqli->error);
        return [];
    }

    $stmt_clientes->execute();
    $result_clientes = $stmt_clientes->get_result();

    $clientes = [];
    while ($row = $result_clientes->fetch_assoc()) {
        $clientes[] = $row;
    }
    $stmt_clientes->close();

    // Si no hay clientes, retornar array vacío
    if (empty($clientes)) {
        return [];
    }

    // Query 2: Para cada cliente, contar pedidos (simple COUNT)
    $sql_count = "SELECT COUNT(*) as total FROM Pedidos WHERE id_usuario = ?";

    $stmt_count = $mysqli->prepare($sql_count);
    if (!$stmt_count) {
        error_log("ERROR obtenerClientesConPedidos - prepare count falló: " . $mysqli->error);
        // Retornar clientes sin total_pedidos en caso de error
        return $clientes;
    }

    // PHP: Agregar total_pedidos a cada cliente
    foreach ($clientes as &$cliente) {
        $id_usuario = intval($cliente['id_usuario']);

        $stmt_count->bind_param('i', $id_usuario);

        if (!$stmt_count->execute()) {
            error_log("ERROR obtenerClientesConPedidos - execute count falló para usuario #{$id_usuario}: " . $stmt_count->error);
            $cliente['total_pedidos'] = 0; // Default a 0 en caso de error
            continue;
        }

        $result_count = $stmt_count->get_result();
        $row_count = $result_count->fetch_assoc();

        $cliente['total_pedidos'] = intval($row_count['total'] ?? 0);
    }
    unset($cliente); // Romper referencia del foreach

    $stmt_count->close();

    return $clientes;
}

