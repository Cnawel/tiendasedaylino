<?php
/**
 * ========================================================================
 * CONSULTAS SQL DE FOTOS DE PRODUCTOS - Tienda Seda y Lino
 * ========================================================================
 * Archivo centralizado con todas las consultas relacionadas a fotos de productos
 * 
 * Uso:
 *   require_once __DIR__ . '/includes/queries/foto_producto_queries.php';
 *   $id_foto = insertarFotoProductoBasica($mysqli, $id_producto);
 * ========================================================================
 */

/**
 * Inserta un registro completo de foto de producto
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @param string|null $foto_miniatura Ruta de la foto miniatura
 * @param string|null $foto1 Ruta de la foto 1
 * @param string|null $foto2 Ruta de la foto 2
 * @param string|null $foto3 Ruta de la foto 3 (grupal)
 * @param string|null $color Color asociado a las fotos
 * @return int|false ID de la foto insertada o false en caso de error
 */
function insertarFotoProducto($mysqli, $id_producto, $foto_miniatura = null, $foto1 = null, $foto2 = null, $foto3 = null, $color = null) {
    $sql = "INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color) VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR insertarFotoProducto - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('isssss', $id_producto, $foto_miniatura, $foto1, $foto2, $foto3, $color);
    if (!$stmt->execute()) {
        error_log("ERROR insertarFotoProducto - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $id_foto = $mysqli->insert_id;
    $stmt->close();
    
    return $id_foto;
}

/**
 * Obtiene la foto general de un producto (donde color IS NULL)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @return array|null Array con id_foto o null si no existe
 */
function obtenerFotoProductoGeneral($mysqli, $id_producto) {
    $sql = "SELECT id_foto FROM Fotos_Producto WHERE id_producto = ? AND (color IS NULL OR color = '') LIMIT 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $id_producto);
    $stmt->execute();
    $result = $stmt->get_result();
    $foto = $result->fetch_assoc();
    $stmt->close();
    
    return $foto;
}

/**
 * Obtiene las fotos de un producto por color
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @param string $color Color de las fotos
 * @return array|null Array con id_foto, foto1_prod, foto2_prod o null si no existe
 */
function obtenerFotoProductoPorProducto($mysqli, $id_producto, $color) {
    $sql = "SELECT id_foto, foto1_prod, foto2_prod FROM Fotos_Producto WHERE id_producto = ? AND color = ? LIMIT 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('is', $id_producto, $color);
    $stmt->execute();
    $result = $stmt->get_result();
    $foto = $result->fetch_assoc();
    $stmt->close();
    
    return $foto;
}

/**
 * Actualiza la foto miniatura de un producto
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_foto ID de la foto
 * @param string|null $foto_miniatura Ruta de la foto miniatura (null para limpiar)
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function actualizarFotoMiniatura($mysqli, $id_foto, $foto_miniatura) {
    $sql = "UPDATE Fotos_Producto SET foto_prod_miniatura = ? WHERE id_foto = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR actualizarFotoMiniatura - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('si', $foto_miniatura, $id_foto);
    if (!$stmt->execute()) {
        error_log("ERROR actualizarFotoMiniatura - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $stmt->close();
    return true;
}

/**
 * Actualiza la foto grupal (foto3_prod) de un producto
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_foto ID de la foto
 * @param string|null $foto3_prod Ruta de la foto grupal (null para limpiar)
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function actualizarFotoGrupal($mysqli, $id_foto, $foto3_prod) {
    $sql = "UPDATE Fotos_Producto SET foto3_prod = ? WHERE id_foto = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR actualizarFotoGrupal - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('si', $foto3_prod, $id_foto);
    if (!$stmt->execute()) {
        error_log("ERROR actualizarFotoGrupal - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $stmt->close();
    return true;
}

/**
 * Actualiza las fotos por color (foto1_prod y foto2_prod)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_foto ID de la foto
 * @param string|null $foto1 Ruta de la foto 1 (opcional)
 * @param string|null $foto2 Ruta de la foto 2 (opcional)
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function actualizarFotosColor($mysqli, $id_foto, $foto1 = null, $foto2 = null) {
    // Construir query dinámicamente según qué fotos se proporcionan
    // Nota: null explícito significa limpiar la foto (establecer a NULL en BD)
    // Desde marketing-editar-producto.php siempre pasamos ambos parámetros:
    // - null = limpiar la foto
    // - string = establecer nueva ruta
    // - valor existente = mantener sin cambios
    
    // Determinar qué campos actualizar (siempre actualizamos ambos cuando se llama desde marketing-editar-producto.php)
    // Actualizar ambas fotos (pueden ser null para limpiar, o strings para establecer)
    $sql = "UPDATE Fotos_Producto SET foto1_prod = ?, foto2_prod = ? WHERE id_foto = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR actualizarFotosColor - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('ssi', $foto1, $foto2, $id_foto);
    
    if (!$stmt->execute()) {
        error_log("ERROR actualizarFotosColor - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $stmt->close();
    return true;
}

