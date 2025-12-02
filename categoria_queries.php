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
        error_log("DEBUG obtenerCategoriaIdPorNombre - Categoría '$nombre_categoria' encontrada con ID: " . $row['id_categoria']);
        return (int)$row['id_categoria'];
    } else {
        // Verificar si existe pero está inactiva
        $sql_check = "SELECT id_categoria, activo FROM Categorias WHERE LOWER(TRIM(nombre_categoria)) = LOWER(TRIM(?)) LIMIT 1";
        $stmt_check = $mysqli->prepare($sql_check);
        if ($stmt_check) {
            $stmt_check->bind_param('s', $nombre_categoria);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $row_check = $result_check->fetch_assoc();
            $stmt_check->close();
            
            if ($row_check) {
                error_log("DEBUG obtenerCategoriaIdPorNombre - Categoría '$nombre_categoria' existe pero está inactiva (activo=" . $row_check['activo'] . ")");
            } else {
                error_log("DEBUG obtenerCategoriaIdPorNombre - Categoría '$nombre_categoria' no existe en BD");
            }
        }
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
 * Crea una nueva categoría si no existe
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $nombre_categoria Nombre de la categoría
 * @return int|null ID de la categoría o null si falló
 */
function crearCategoria($mysqli, $nombre_categoria) {
    $id_existente = obtenerCategoriaIdPorNombre($mysqli, $nombre_categoria);
    if ($id_existente !== null) return $id_existente;
    
    $sql = "INSERT INTO Categorias (nombre_categoria, activo, fecha_creacion) VALUES (?, 1, NOW())";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return null;
    
    $stmt->bind_param('s', $nombre_categoria);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    
    $id = $mysqli->insert_id;
    $stmt->close();
    return $id;
}

