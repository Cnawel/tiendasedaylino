<?php
/**
 * ========================================================================
 * CONSULTAS SQL DE CATEGORÍAS - Tienda Seda y Lino
 * ========================================================================
 * Archivo centralizado con todas las consultas relacionadas a categorías
 * 
 * Uso:
 *   require_once __DIR__ . '/includes/queries/categoria_queries.php';
 *   $categoria_id = obtenerCategoriaIdPorNombre($mysqli, 'Blusas');
 * ========================================================================
 */

/**
 * Obtiene el ID de una categoría por su nombre
 * Comparación case-insensitive para evitar problemas con mayúsculas/minúsculas
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $nombre_categoria Nombre de la categoría
 * @return int|null ID de la categoría o null si no existe
 */
function obtenerCategoriaIdPorNombre($mysqli, $nombre_categoria) {
    // Normalizar nombre de categoría antes de buscar (trim para consistencia)
    $nombre_categoria_normalizado = trim($nombre_categoria);
    
    // Usar LOWER() para comparación case-insensitive
    $sql = "SELECT id_categoria, nombre_categoria FROM Categorias WHERE LOWER(TRIM(nombre_categoria)) = LOWER(TRIM(?)) AND activo = 1 LIMIT 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR obtenerCategoriaIdPorNombre - prepare falló: " . $mysqli->error . " para categoría: '" . $nombre_categoria_normalizado . "'");
        return null;
    }
    
    $stmt->bind_param('s', $nombre_categoria_normalizado);
    if (!$stmt->execute()) {
        error_log("ERROR obtenerCategoriaIdPorNombre - execute falló: " . $stmt->error . " para categoría: '" . $nombre_categoria_normalizado . "'");
        $stmt->close();
        return null;
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        $id_encontrado = (int)$row['id_categoria'];
        $nombre_encontrado = trim($row['nombre_categoria']);
        // Log para debugging: verificar que el nombre encontrado coincide con el buscado
        if (strtolower($nombre_encontrado) !== strtolower($nombre_categoria_normalizado)) {
            error_log("INFO obtenerCategoriaIdPorNombre - Nombre encontrado difiere del buscado. Buscado: '" . $nombre_categoria_normalizado . "', Encontrado: '" . $nombre_encontrado . "' (ID: " . $id_encontrado . ")");
        }
        return $id_encontrado;
    } else {
        // Log cuando no se encuentra la categoría para debugging
        error_log("WARNING obtenerCategoriaIdPorNombre - No se encontró categoría con nombre: '" . $nombre_categoria_normalizado . "'");
        return null;
    }
}

/**
 * Obtiene todas las categorías activas
 * 
 * Esta función retorna un array con todas las categorías que tienen activo = 1,
 * ordenadas alfabéticamente por nombre. Útil para mostrar listas de categorías
 * en formularios, menús de navegación o filtros.
 * Incluye verificación adicional en PHP para asegurar que solo se retornen categorías activas.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array asociativo de categorías con id_categoria, nombre_categoria, descripcion_categoria y activo
 */
function obtenerCategorias($mysqli) {
    $sql = "SELECT id_categoria, nombre_categoria, descripcion_categoria, activo 
            FROM Categorias 
            WHERE activo = 1
            ORDER BY nombre_categoria";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categorias = [];
    while ($row = $result->fetch_assoc()) {
        // Verificación adicional: solo incluir categorías activas (activo = 1)
        if (isset($row['activo']) && $row['activo'] == 1) {
            $categorias[] = $row;
        }
    }
    
    $stmt->close();
    return $categorias;
}

/**
 * Obtiene IDs de categorías que tienen productos activos (incluyendo stock 0)
 * 
 * Esta función retorna un array con los IDs de categorías que tienen al menos
 * un producto activo con variantes que tengan stock >= 0, sin importar si las
 * variantes están activas o inactivas. Útil para mostrar categorías en la barra
 * de navegación aunque tengan productos sin stock o con variantes inactivas.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array con IDs de categorías que tienen productos activos con stock >= 0
 */
function obtenerCategoriasConProductosStock($mysqli) {
    $sql = "
        SELECT DISTINCT c.id_categoria
        FROM Categorias c
        INNER JOIN Productos p ON c.id_categoria = p.id_categoria
        INNER JOIN Stock_Variantes sv ON p.id_producto = sv.id_producto
        WHERE c.activo = 1
        AND p.activo = 1
        AND sv.stock >= 0
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categorias_ids = [];
    while ($row = $result->fetch_assoc()) {
        $categorias_ids[] = (int)$row['id_categoria'];
    }
    
    $stmt->close();
    return $categorias_ids;
}

/**
 * Crea una nueva categoría en la base de datos
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $nombre_categoria Nombre de la categoría a crear
 * @return int|false ID de la categoría creada o false en caso de error
 */
function crearCategoria($mysqli, $nombre_categoria) {
    $sql = "INSERT INTO Categorias (nombre_categoria, activo, fecha_creacion) VALUES (?, 1, NOW())";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR crearCategoria - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('s', $nombre_categoria);
    if (!$stmt->execute()) {
        error_log("ERROR crearCategoria - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $id_categoria = $mysqli->insert_id;
    $stmt->close();
    
    return $id_categoria;
}

/**
 * Verifica si una categoría puede eliminarse permanentemente
 * 
 * Una categoría puede eliminarse solo si:
 * - NO tiene productos asociados (activos o inactivos)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_categoria ID de la categoría a verificar
 * @return array Array con ['puede_eliminarse' => bool, 'razon' => string, 'productos_count' => int]
 */
function verificarCategoriaPuedeEliminarse($mysqli, $id_categoria) {
    $resultado = [
        'puede_eliminarse' => false,
        'razon' => '',
        'productos_count' => 0
    ];
    
    // Verificar que la categoría existe
    $sql = "SELECT id_categoria, nombre_categoria, activo FROM Categorias WHERE id_categoria = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        $resultado['razon'] = 'Error al verificar la categoría';
        return $resultado;
    }
    
    $stmt->bind_param('i', $id_categoria);
    $stmt->execute();
    $result = $stmt->get_result();
    $categoria = $result->fetch_assoc();
    $stmt->close();
    
    if (!$categoria) {
        $resultado['razon'] = 'La categoría no existe';
        return $resultado;
    }
    
    // Contar TODOS los productos asociados a esta categoría (activos e inactivos)
    $sql = "SELECT COUNT(*) as total FROM Productos WHERE id_categoria = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        $resultado['razon'] = 'Error al contar productos asociados';
        return $resultado;
    }
    
    $stmt->bind_param('i', $id_categoria);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    $productos_count = isset($row['total']) ? intval($row['total']) : 0;
    $resultado['productos_count'] = $productos_count;
    
    // Si tiene productos asociados, no se puede eliminar
    if ($productos_count > 0) {
        $resultado['razon'] = "La categoría tiene $productos_count producto(s) asociado(s). Debe eliminar los productos primero.";
        return $resultado;
    }
    
    // Si llegamos aquí, la categoría puede eliminarse
    $resultado['puede_eliminarse'] = true;
    $resultado['razon'] = 'La categoría puede eliminarse';
    
    return $resultado;
}

/**
 * Elimina permanentemente una categoría de la base de datos
 * 
 * Esta función realiza un DELETE directo de la categoría.
 * IMPORTANTE: Solo debe usarse después de verificar que la categoría
 * no tiene productos asociados usando verificarCategoriaPuedeEliminarse().
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_categoria ID de la categoría a eliminar
 * @return bool True si se eliminó correctamente, false en caso contrario
 */
function eliminarCategoriaPermanentemente($mysqli, $id_categoria) {
    $sql = "DELETE FROM Categorias WHERE id_categoria = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR eliminarCategoriaPermanentemente - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('i', $id_categoria);
    $resultado = $stmt->execute();
    
    if (!$resultado) {
        error_log("ERROR eliminarCategoriaPermanentemente - execute falló: " . $stmt->error);
    }
    
    $stmt->close();
    
    return $resultado;
}

