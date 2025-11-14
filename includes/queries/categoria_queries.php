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
    // Usar LOWER() para comparación case-insensitive
    $sql = "SELECT id_categoria FROM Categorias WHERE LOWER(TRIM(nombre_categoria)) = LOWER(TRIM(?)) AND activo = 1 LIMIT 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR obtenerCategoriaIdPorNombre - prepare falló: " . $mysqli->error . " para categoría: " . $nombre_categoria);
        return null;
    }
    
    $stmt->bind_param('s', $nombre_categoria);
    if (!$stmt->execute()) {
        error_log("ERROR obtenerCategoriaIdPorNombre - execute falló: " . $stmt->error . " para categoría: " . $nombre_categoria);
        $stmt->close();
        return null;
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        return (int)$row['id_categoria'];
    } else {
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

