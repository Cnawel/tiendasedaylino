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
 * Inserta un registro básico de foto de producto (solo con id_producto)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @return int|false ID de la foto insertada o false en caso de error
 */
function insertarFotoProductoBasica($mysqli, $id_producto) {
    $sql = "INSERT INTO Fotos_Producto (id_producto) VALUES (?)";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR insertarFotoProductoBasica - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('i', $id_producto);
    if (!$stmt->execute()) {
        error_log("ERROR insertarFotoProductoBasica - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $id_foto = $mysqli->insert_id;
    $stmt->close();
    
    return $id_foto;
}

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
 * @param string $foto_miniatura Ruta de la foto miniatura
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
 * @param string $foto3_prod Ruta de la foto grupal
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
    if ($foto1 !== null && $foto2 !== null) {
        $sql = "UPDATE Fotos_Producto SET foto1_prod = ?, foto2_prod = ? WHERE id_foto = ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            error_log("ERROR actualizarFotosColor - prepare falló: " . $mysqli->error);
            return false;
        }
        $stmt->bind_param('ssi', $foto1, $foto2, $id_foto);
    } elseif ($foto1 !== null) {
        $sql = "UPDATE Fotos_Producto SET foto1_prod = ? WHERE id_foto = ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            error_log("ERROR actualizarFotosColor - prepare falló: " . $mysqli->error);
            return false;
        }
        $stmt->bind_param('si', $foto1, $id_foto);
    } elseif ($foto2 !== null) {
        $sql = "UPDATE Fotos_Producto SET foto2_prod = ? WHERE id_foto = ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            error_log("ERROR actualizarFotosColor - prepare falló: " . $mysqli->error);
            return false;
        }
        $stmt->bind_param('si', $foto2, $id_foto);
    } else {
        // No hay fotos para actualizar
        return false;
    }
    
    if (!$stmt->execute()) {
        error_log("ERROR actualizarFotosColor - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $stmt->close();
    return true;
}

/**
 * Obtiene todas las fotos de productos con el mismo nombre, categoría y género
 * Retorna un array estructurado con fotos generales y fotos por color
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $nombre_producto Nombre del producto
 * @param int $id_categoria ID de la categoría
 * @param string $genero Género del producto
 * @param int $id_producto_actual ID del producto actual (para priorizar sus fotos)
 * @return array Array con estructura ['generales' => [...], 'por_color' => [...]]
 */
function obtenerTodasFotosProductoPorGrupo($mysqli, $nombre_producto, $id_categoria, $genero, $id_producto_actual = null) {
    $sql = "
        SELECT fp.id_foto, fp.id_producto, fp.foto_prod_miniatura, fp.foto1_prod, fp.foto2_prod, fp.foto3_prod, fp.color
        FROM Fotos_Producto fp
        INNER JOIN Productos p ON fp.id_producto = p.id_producto
        WHERE p.nombre_producto = ?
        AND p.id_categoria = ?
        AND p.genero = ?
        AND fp.activo = 1
        AND p.activo = 1
        ORDER BY fp.color IS NULL DESC, fp.color ASC
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return ['generales' => null, 'por_color' => []];
    }
    
    $stmt->bind_param('sis', $nombre_producto, $id_categoria, $genero);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Inicializar arrays de fotos
    $fotos_generales_combinadas = null;
    $fotos_por_color_combinadas = [];
    
    while ($row_foto = $result->fetch_assoc()) {
        if ($row_foto['color'] === null || $row_foto['color'] === '') {
            // Fotos generales - priorizar las del producto actual
            if ($id_producto_actual !== null && $row_foto['id_producto'] == $id_producto_actual) {
                if (!$fotos_generales_combinadas) {
                    $fotos_generales_combinadas = [
                        'id_foto' => $row_foto['id_foto'],
                        'foto_prod_miniatura' => !empty($row_foto['foto_prod_miniatura']) ? $row_foto['foto_prod_miniatura'] : null,
                        'foto3_prod' => !empty($row_foto['foto3_prod']) ? $row_foto['foto3_prod'] : null
                    ];
                } else {
                    if (!empty($row_foto['foto_prod_miniatura'])) {
                        $fotos_generales_combinadas['foto_prod_miniatura'] = $row_foto['foto_prod_miniatura'];
                    }
                    if (!empty($row_foto['foto3_prod'])) {
                        $fotos_generales_combinadas['foto3_prod'] = $row_foto['foto3_prod'];
                    }
                    if (!empty($row_foto['id_foto'])) {
                        $fotos_generales_combinadas['id_foto'] = $row_foto['id_foto'];
                    }
                }
            } elseif (!$fotos_generales_combinadas) {
                if (!empty($row_foto['foto_prod_miniatura']) || !empty($row_foto['foto3_prod'])) {
                    $fotos_generales_combinadas = [
                        'id_foto' => $row_foto['id_foto'],
                        'foto_prod_miniatura' => !empty($row_foto['foto_prod_miniatura']) ? $row_foto['foto_prod_miniatura'] : null,
                        'foto3_prod' => !empty($row_foto['foto3_prod']) ? $row_foto['foto3_prod'] : null
                    ];
                }
            } else {
                if (empty($fotos_generales_combinadas['foto_prod_miniatura']) && !empty($row_foto['foto_prod_miniatura'])) {
                    $fotos_generales_combinadas['foto_prod_miniatura'] = $row_foto['foto_prod_miniatura'];
                }
                if (empty($fotos_generales_combinadas['foto3_prod']) && !empty($row_foto['foto3_prod'])) {
                    $fotos_generales_combinadas['foto3_prod'] = $row_foto['foto3_prod'];
                }
            }
        } else {
            // Fotos por color
            $color_normalizado = trim($row_foto['color']);
            
            if (!isset($fotos_por_color_combinadas[$color_normalizado])) {
                $fotos_por_color_combinadas[$color_normalizado] = [];
            }
            
            // Verificar si ya existe una foto con el mismo id_foto para este color
            $foto_existe = false;
            foreach ($fotos_por_color_combinadas[$color_normalizado] as $foto_existente) {
                if ($foto_existente['id_foto'] == $row_foto['id_foto']) {
                    $foto_existe = true;
                    if ($id_producto_actual !== null && $row_foto['id_producto'] == $id_producto_actual) {
                        $indice = array_search($foto_existente, $fotos_por_color_combinadas[$color_normalizado], true);
                        if ($indice !== false) {
                            $fotos_por_color_combinadas[$color_normalizado][$indice] = [
                                'id_foto' => $row_foto['id_foto'],
                                'id_producto' => $row_foto['id_producto'],
                                'foto1_prod' => $row_foto['foto1_prod'],
                                'foto2_prod' => $row_foto['foto2_prod']
                            ];
                        }
                    }
                    break;
                }
            }
            
            if (!$foto_existe) {
                $foto_item = [
                    'id_foto' => $row_foto['id_foto'],
                    'id_producto' => $row_foto['id_producto'],
                    'foto1_prod' => $row_foto['foto1_prod'],
                    'foto2_prod' => $row_foto['foto2_prod']
                ];
                
                if ($id_producto_actual !== null && $row_foto['id_producto'] == $id_producto_actual) {
                    array_unshift($fotos_por_color_combinadas[$color_normalizado], $foto_item);
                } else {
                    $fotos_por_color_combinadas[$color_normalizado][] = $foto_item;
                }
            }
        }
    }
    
    $stmt->close();
    
    return [
        'generales' => $fotos_generales_combinadas,
        'por_color' => $fotos_por_color_combinadas
    ];
}

