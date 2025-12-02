<?php
/**
 * ========================================================================
 * CONSULTAS SQL DE PRODUCTOS - Tienda Seda y Lino
 * ========================================================================
 * Archivo centralizado con todas las consultas relacionadas a productos
 * Facilita mantenimiento, reutilización y organización del código SQL
 * 
 * REEMPLAZO DE TRIGGERS:
 * Este archivo implementa la lógica PHP que reemplaza los siguientes triggers de MySQL:
 * - trg_validar_categoria_activa_producto: crearProducto()
 * 
 * Uso:
 *   require_once __DIR__ . '/includes/queries/producto_queries.php';
 *   $producto = obtenerProductoPorId($mysqli, $id_producto);
 * ========================================================================
 */

/**
 * Obtiene los datos completos de un producto por su ID
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto a buscar
 * @return array|null Array asociativo con datos del producto o null si no existe
 */
function obtenerProductoPorId($mysqli, $id_producto) {
    $sql = "
        SELECT 
            p.id_producto,
            p.nombre_producto,
            p.descripcion_producto,
            p.precio_actual,
            p.genero,
            p.id_categoria,
            c.nombre_categoria,
            c.descripcion_categoria
        FROM Productos p
        JOIN Categorias c ON p.id_categoria = c.id_categoria
        WHERE p.id_producto = ?
        AND p.activo = 1
        AND c.activo = 1
        LIMIT 1
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $id_producto);
    $stmt->execute();
    $result = $stmt->get_result();
    $producto = $result->fetch_assoc();
    $stmt->close();
    
    return $producto;
}

/**
 * Obtiene todas las variantes de stock de un producto (talle, color, stock)
 * Solo devuelve variantes activas (activo = 1)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @return array Array de variantes con talle, color y stock
 */
function obtenerVariantesStock($mysqli, $id_producto) {
    $sql = "
        SELECT id_variante, talle, color, stock
        FROM Stock_Variantes
        WHERE id_producto = ? AND activo = 1
        ORDER BY color, talle
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('i', $id_producto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $variantes = [];
    while ($row = $result->fetch_assoc()) {
        $variantes[] = $row;
    }
    
    $stmt->close();
    return $variantes;
}

/**
 * Obtiene TODAS las variantes activas de un producto (incluyendo las sin stock)
 * Útil para mostrar todas las opciones disponibles en detalle de producto
 * No filtra por stock, solo por activo = 1, por lo que incluye variantes con stock = 0
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @return array Array de variantes con talle, color y stock (puede incluir stock 0)
 */
function obtenerTodasVariantesProducto($mysqli, $id_producto) {
    // Obtener todas las variantes activas sin filtrar por stock
    // Esto permite mostrar todas las combinaciones talle-color disponibles
    $sql = "
        SELECT id_variante, talle, color, stock
        FROM Stock_Variantes
        WHERE id_producto = ? AND activo = 1
        ORDER BY color, talle
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('i', $id_producto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $variantes = [];
    while ($row = $result->fetch_assoc()) {
        $variantes[] = $row;
    }
    
    $stmt->close();
    return $variantes;
}

/**
 * Verifica si un producto tiene pedidos relacionados
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @return int Cantidad de detalles de pedidos relacionados
 */
function contarPedidosRelacionados($mysqli, $id_producto) {
    $sql = "
        SELECT COUNT(*) as total 
        FROM Detalle_Pedido dp
        INNER JOIN Stock_Variantes sv ON sv.id_variante = dp.id_variante
        WHERE sv.id_producto = ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param('i', $id_producto);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row ? (int)$row['total'] : 0;
}

/**
 * Obtiene los IDs de todas las variantes de un producto
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @return array Array de IDs de variantes
 */
function obtenerIdsVariantes($mysqli, $id_producto) {
    $sql = "SELECT id_variante FROM Stock_Variantes WHERE id_producto = ? AND activo = 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('i', $id_producto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = $row['id_variante'];
    }
    
    $stmt->close();
    return $ids;
}

/**
 * NOTA: Movimientos_Stock NO se eliminan (son históricos, no tienen campo activo)
 * Esta función se mantiene por compatibilidad pero no hace nada
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $variantes_ids Array de IDs de variantes
 * @return bool True siempre (no se eliminan movimientos históricos)
 */
function eliminarMovimientosStock($mysqli, $variantes_ids) {
    // Movimientos_Stock son históricos y NO se eliminan según la nueva estructura
    // Esta función se mantiene por compatibilidad pero retorna true sin hacer nada
    return true;
}

/**
 * NOTA: Detalle_Pedido NO se eliminan (son históricos, no tienen campo activo)
 * Esta función se mantiene por compatibilidad pero no hace nada
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $variantes_ids Array de IDs de variantes
 * @return bool True siempre (no se eliminan detalles históricos)
 */
function eliminarDetallesPedido($mysqli, $variantes_ids) {
    // Detalle_Pedido son históricos y NO se eliminan según la nueva estructura
    // Esta función se mantiene por compatibilidad pero retorna true sin hacer nada
    return true;
}

/**
 * Realiza soft delete de las variantes de stock de un producto (marca como inactivas)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @return bool True si se actualizó correctamente, False en caso contrario
 */
function eliminarVariantesStock($mysqli, $id_producto) {
    $sql = "UPDATE Stock_Variantes SET activo = 0, fecha_actualizacion = NOW() WHERE id_producto = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('i', $id_producto);
    $resultado = $stmt->execute();
    $stmt->close();
    
    return $resultado;
}

/**
 * Realiza soft delete de las fotos de un producto (marca como inactivas)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @return bool True si se actualizó correctamente, False en caso contrario
 */
function eliminarFotosProducto($mysqli, $id_producto) {
    $sql = "UPDATE Fotos_Producto SET activo = 0 WHERE id_producto = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('i', $id_producto);
    $resultado = $stmt->execute();
    $stmt->close();
    
    return $resultado;
}

/**
 * Elimina el directorio de imágenes de un producto recursivamente
 * 
 * @param int $id_producto ID del producto
 * @param string|null $ruta_base Ruta base del proyecto (opcional, por defecto calcula desde __DIR__)
 * @return bool True si se eliminó correctamente, False en caso contrario
 */
function eliminarDirectorioImagenes($id_producto, $ruta_base = null) {
    // Calcular ruta base si no se proporciona
    if ($ruta_base === null) {
        // Desde includes/queries/ subir dos niveles para llegar a la raíz
        $ruta_base = dirname(dirname(__DIR__));
    }
    
    $directorio_producto = $ruta_base . '/imagenes/productos/producto_' . $id_producto . '/';
    
    if (!file_exists($directorio_producto) || !is_dir($directorio_producto)) {
        return true;
    }
    
    // Función recursiva para eliminar directorio
    $eliminarDirectorio = function($dir) use (&$eliminarDirectorio) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $archivos = array_diff(scandir($dir), ['.', '..']);
        foreach ($archivos as $archivo) {
            $ruta_completa = $dir . '/' . $archivo;
            if (is_dir($ruta_completa)) {
                $eliminarDirectorio($ruta_completa);
            } else {
                @unlink($ruta_completa);
            }
        }
        
        return @rmdir($dir);
    };
    
    return $eliminarDirectorio($directorio_producto);
}

/**
 * Realiza soft delete de un producto y todas sus relaciones de forma segura
 * Marca como inactivos: Producto, Stock_Variantes, Fotos_Producto
 * NO elimina: Movimientos_Stock, Detalle_Pedido (son históricos)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto a desactivar
 * @param string|null $ruta_base Ruta base del proyecto para eliminar imágenes (opcional)
 * @return array Array con ['success' => bool, 'mensaje' => string, 'pedidos_eliminados' => int]
 */
function eliminarProductoCompleto($mysqli, $id_producto, $ruta_base = null) {
    $resultado = [
        'success' => false,
        'mensaje' => '',
        'pedidos_eliminados' => 0
    ];
    
    // Verificar si hay pedidos relacionados
    $pedidos_relacionados = contarPedidosRelacionados($mysqli, $id_producto);
    $resultado['pedidos_eliminados'] = $pedidos_relacionados;
    
    // Iniciar transacción
    $mysqli->begin_transaction();
    
    try {
        // 1. Obtener IDs de variantes
        $variantes_ids = obtenerIdsVariantes($mysqli, $id_producto);
        
        // 2. NOTA: Movimientos_Stock y Detalle_Pedido NO se eliminan (son históricos)
        // Las funciones retornan true por compatibilidad pero no hacen nada
        
        // 3. Realizar soft delete de Stock_Variantes (marcar como inactivas)
        if (!eliminarVariantesStock($mysqli, $id_producto)) {
            throw new Exception('Error al desactivar variantes de stock');
        }
        
        // 4. Realizar soft delete de Fotos_Producto (marcar como inactivas)
        if (!eliminarFotosProducto($mysqli, $id_producto)) {
            throw new Exception('Error al desactivar fotos del producto');
        }
        
        // 5. Eliminar imágenes del directorio (opcional, para limpiar espacio)
        if (!eliminarDirectorioImagenes($id_producto, $ruta_base)) {
            // No lanzar excepción si falla la eliminación de imágenes, solo registrar
            error_log("Advertencia: No se pudo eliminar completamente el directorio de imágenes del producto $id_producto");
        }
        
        // 6. Realizar soft delete del Producto (marcar como inactivo)
        $sql = "UPDATE Productos SET activo = 0, fecha_actualizacion = NOW() WHERE id_producto = ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error al preparar consulta de eliminación');
        }
        
        $stmt->bind_param('i', $id_producto);
        if (!$stmt->execute()) {
            throw new Exception('Error al desactivar el producto');
        }
        $stmt->close();
        
        // Confirmar transacción
        $mysqli->commit();
        
        $resultado['success'] = true;
        $resultado['mensaje'] = 'Producto desactivado correctamente (soft delete)';
        if ($pedidos_relacionados > 0) {
            $resultado['mensaje'] .= ' (NOTA: Los detalles de pedidos históricos se mantienen en la base de datos)';
        }
        
    } catch (Exception $e) {
        $mysqli->rollback();
        $resultado['success'] = false;
        $resultado['mensaje'] = 'Error al eliminar el producto: ' . $e->getMessage();
    }
    
    return $resultado;
}

/**
 * Obtiene las fotos registradas de un producto en la base de datos
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @return array|null Array con fotos del producto o null si no tiene fotos
 */
function obtenerFotosProducto($mysqli, $id_producto) {
    $sql = "
        SELECT foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod
        FROM Fotos_Producto
        WHERE id_producto = ?
        AND activo = 1
        LIMIT 1
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $id_producto);
    $stmt->execute();
    $result = $stmt->get_result();
    $fotos = $result->fetch_assoc();
    $stmt->close();
    
    return $fotos;
}

/**
 * Obtiene todas las fotos de un producto organizadas (generales y por color)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @return array Array con estructura: ['generales' => [...], 'por_color' => [color => [...]]]
 */
function obtenerTodasFotosProducto($mysqli, $id_producto) {
    $sql = "
        SELECT id_foto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color
        FROM Fotos_Producto
        WHERE id_producto = ?
        AND activo = 1
        ORDER BY color IS NULL DESC, color ASC
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return ['generales' => null, 'por_color' => []];
    }
    
    $stmt->bind_param('i', $id_producto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $fotos_generales = null;
    $fotos_por_color = [];
    
    while ($row = $result->fetch_assoc()) {
        if ($row['color'] === null || $row['color'] === '') {
            // Fotos generales (comunes a todas las variantes)
            // Si ya existe un registro, combinar valores válidos (priorizar valores no NULL)
            if ($fotos_generales === null) {
                $fotos_generales = [
                    'id_foto' => $row['id_foto'],
                    'foto_prod_miniatura' => !empty($row['foto_prod_miniatura']) ? $row['foto_prod_miniatura'] : null,
                    'foto3_prod' => !empty($row['foto3_prod']) ? $row['foto3_prod'] : null
                ];
            } else {
                // Combinar: si el campo actual es NULL pero el nuevo tiene valor, actualizar
                if (empty($fotos_generales['foto_prod_miniatura']) && !empty($row['foto_prod_miniatura'])) {
                    $fotos_generales['foto_prod_miniatura'] = $row['foto_prod_miniatura'];
                }
                if (empty($fotos_generales['foto3_prod']) && !empty($row['foto3_prod'])) {
                    $fotos_generales['foto3_prod'] = $row['foto3_prod'];
                }
                // Actualizar id_foto si es necesario
                if (!empty($row['id_foto'])) {
                    $fotos_generales['id_foto'] = $row['id_foto'];
                }
            }
        } else {
            // Fotos por color
            if (!isset($fotos_por_color[$row['color']])) {
                $fotos_por_color[$row['color']] = [];
            }
            $fotos_por_color[$row['color']][] = [
                'id_foto' => $row['id_foto'],
                'foto1_prod' => $row['foto1_prod'],
                'foto2_prod' => $row['foto2_prod']
            ];
        }
    }
    
    $stmt->close();
    
    return [
        'generales' => $fotos_generales,
        'por_color' => $fotos_por_color
    ];
}

/**
 * Obtiene variantes de color del mismo producto base (mismo nombre, categoría y género)
 * Útil para mostrar otras opciones de color en detalle de producto
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto actual
 * @param string $nombre_producto Nombre del producto
 * @param int $id_categoria ID de la categoría
 * @param string $genero Género del producto
 * @return array Array de productos con distintos colores (mismo nombre/categoría/género)
 */
function obtenerVariantesColorMismoProducto($mysqli, $id_producto, $nombre_producto, $id_categoria, $genero) {
    // Obtener talles estándar para filtrar
    if (!function_exists('obtenerTallesEstandar')) {
        require_once __DIR__ . '/../talles_config.php';
    }
    $talles_estandar = obtenerTallesEstandar();
    $placeholders_talles = str_repeat('?,', count($talles_estandar) - 1) . '?';
    
    $sql = "
        SELECT DISTINCT
            p.id_producto,
            p.nombre_producto,
            p.precio_actual,
            p.genero,
            c.nombre_categoria,
            (SELECT MIN(sv_min.color) 
             FROM Stock_Variantes sv_min 
             WHERE sv_min.id_producto = p.id_producto 
             AND sv_min.activo = 1
             AND sv_min.talle IN ($placeholders_talles)
             LIMIT 1) as color,
            (SELECT SUM(sv_sum.stock) 
             FROM Stock_Variantes sv_sum 
             WHERE sv_sum.id_producto = p.id_producto 
             AND sv_sum.stock > 0
             AND sv_sum.activo = 1
             AND sv_sum.talle IN ($placeholders_talles)) as total_stock
        FROM Productos p
        INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
        WHERE p.nombre_producto = ?
        AND p.id_categoria = ?
        AND p.genero = ?
        AND p.id_producto != ?
        AND p.activo = 1
        AND c.activo = 1
        AND EXISTS (
            SELECT 1 
            FROM Stock_Variantes sv 
            WHERE sv.id_producto = p.id_producto 
            AND sv.activo = 1
            AND sv.talle IN ($placeholders_talles)
        )
        ORDER BY p.id_producto
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    // Parámetros: nombre_producto, id_categoria, genero, id_producto_excluir, talles (2 veces para las subconsultas)
    $params = array_merge(
        [$nombre_producto, $id_categoria, $genero, $id_producto],
        $talles_estandar, // Para la subconsulta de color
        $talles_estandar, // Para la subconsulta de stock
        $talles_estandar  // Para el EXISTS
    );
    $types = 'sisi' . str_repeat('s', count($talles_estandar) * 3);
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $variantes = [];
    while ($row = $result->fetch_assoc()) {
        $variantes[] = $row;
    }
    
    $stmt->close();
    return $variantes;
}

/**
 * Obtiene productos relacionados de la misma categoría (excluyendo el producto actual)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_categoria ID de la categoría
 * @param int $id_producto_excluir ID del producto a excluir
 * @param int $limite Cantidad máxima de productos a retornar (default: 3)
 * @return array Array de productos relacionados
 */
function obtenerProductosRelacionados($mysqli, $id_categoria, $id_producto_excluir, $limite = 3) {
    $sql = "
        SELECT 
            p.id_producto,
            p.nombre_producto,
            p.precio_actual,
            fp.foto_prod_miniatura
        FROM Productos p
        LEFT JOIN Fotos_Producto fp ON p.id_producto = fp.id_producto AND fp.activo = 1
        WHERE p.id_categoria = ? 
        AND p.id_producto != ?
        AND p.activo = 1
        LIMIT ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('iii', $id_categoria, $id_producto_excluir, $limite);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
    
    $stmt->close();
    return $productos;
}

/**
 * Obtiene un producto con su variante (usado en checkout y procesamiento de pedidos)
 * Si no encuentra la variante en el id_producto dado, busca en productos con el mismo nombre, categoría y género
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @param string $talle Talle del producto
 * @param string $color Color del producto
 * @return array|null Array con datos del producto y variante o null si no existe
 */
function obtenerProductoConVariante($mysqli, $id_producto, $talle, $color) {
    // Validar parámetros de entrada y convertir tipos
    $id_producto = intval($id_producto);
    $talle = trim(strval($talle));
    $color = trim(strval($color));
    
    if ($id_producto <= 0 || empty($talle) || empty($color)) {
        return null;
    }
    
    // Primero intentar buscar en el id_producto específico
    $sql = "
        SELECT 
            p.id_producto,
            p.nombre_producto,
            p.precio_actual,
            p.id_categoria,
            p.genero,
            fp.foto_prod_miniatura,
            sv.id_variante,
            sv.stock,
            sv.talle,
            sv.color
        FROM Productos p
        LEFT JOIN Fotos_Producto fp ON p.id_producto = fp.id_producto AND fp.activo = 1
        LEFT JOIN Stock_Variantes sv ON p.id_producto = sv.id_producto 
            AND sv.talle = ? 
            AND sv.color = ?
            AND sv.activo = 1
        WHERE p.id_producto = ?
        AND p.activo = 1
        LIMIT 1
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("Error en obtenerProductoConVariante - prepare falló: " . $mysqli->error);
        return null;
    }
    
    $stmt->bind_param('ssi', $talle, $color, $id_producto);
    
    if (!$stmt->execute()) {
        error_log("Error en obtenerProductoConVariante - execute falló: " . $stmt->error);
        $stmt->close();
        return null;
    }
    
    $result = $stmt->get_result();
    $producto = $result->fetch_assoc();
    $stmt->close();
    
    // Si se encontró la variante en el producto dado, retornarla
    if ($producto && !empty($producto['id_variante'])) {
        return $producto;
    }
    
    // Inicializar variables para la búsqueda alternativa
    $nombre_producto = null;
    $id_categoria = null;
    $genero = null;
    
    // Si no se encontró la variante pero tenemos datos del producto, usar esos datos para buscar en otros productos
    if ($producto && !empty($producto['id_producto'])) {
        $nombre_producto = $producto['nombre_producto'];
        $id_categoria = $producto['id_categoria'];
        $genero = $producto['genero'];
    } else {
        // Si no tenemos datos del producto, obtenerlos primero
        $sql_producto = "SELECT nombre_producto, id_categoria, genero FROM Productos WHERE id_producto = ? AND activo = 1 LIMIT 1";
        $stmt_producto = $mysqli->prepare($sql_producto);
        if (!$stmt_producto) {
            error_log("Error en obtenerProductoConVariante - prepare producto falló: " . $mysqli->error);
            return null;
        }
        
        $stmt_producto->bind_param('i', $id_producto);
        
        if (!$stmt_producto->execute()) {
            error_log("Error en obtenerProductoConVariante - execute producto falló: " . $stmt_producto->error);
            $stmt_producto->close();
            return null;
        }
        
        $result_producto = $stmt_producto->get_result();
        $producto_data = $result_producto->fetch_assoc();
        $stmt_producto->close();
        
        if (!$producto_data || empty($producto_data['nombre_producto'])) {
            return null;
        }
        
        $nombre_producto = $producto_data['nombre_producto'];
        $id_categoria = $producto_data['id_categoria'];
        $genero = $producto_data['genero'];
    }
    
    // Validar que tenemos los datos necesarios para la búsqueda alternativa
    if (empty($nombre_producto) || empty($id_categoria) || empty($genero)) {
        return null;
    }
    
    // Si no se encontró la variante en el producto dado, buscar en productos con el mismo nombre, categoría y género
    $sql_alternativo = "
        SELECT 
            p.id_producto,
            p.nombre_producto,
            p.precio_actual,
            fp.foto_prod_miniatura,
            sv.id_variante,
            sv.stock,
            sv.talle,
            sv.color
        FROM Productos p
        LEFT JOIN Fotos_Producto fp ON p.id_producto = fp.id_producto AND fp.activo = 1
        INNER JOIN Stock_Variantes sv ON p.id_producto = sv.id_producto 
            AND sv.talle = ? 
            AND sv.color = ?
            AND sv.activo = 1
        INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
        WHERE p.nombre_producto = ?
        AND p.id_categoria = ?
        AND p.genero = ?
        AND p.activo = 1
        AND c.activo = 1
        LIMIT 1
    ";
    
    $stmt_alt = $mysqli->prepare($sql_alternativo);
    if (!$stmt_alt) {
        error_log("Error en obtenerProductoConVariante - prepare alternativo falló: " . $mysqli->error);
        return null;
    }
    
    $stmt_alt->bind_param('sssis', $talle, $color, $nombre_producto, $id_categoria, $genero);
    
    if (!$stmt_alt->execute()) {
        error_log("Error en obtenerProductoConVariante - execute alternativo falló: " . $stmt_alt->error);
        $stmt_alt->close();
        return null;
    }
    
    $result_alt = $stmt_alt->get_result();
    $producto_alt = $result_alt->fetch_assoc();
    $stmt_alt->close();
    
    return $producto_alt;
}

/**
 * Función auxiliar: Construye condiciones WHERE dinámicas basadas en filtros
 * 
 * Esta función analiza el array de filtros y construye las condiciones WHERE,
 * parámetros y tipos para prepared statements. Solo procesa filtros válidos.
 * 
 * @param array $filtros Array con filtros: categoria_id (int), talles[] (array), generos[] (array), colores[] (array)
 * @param array &$where_parts Array por referencia: se llena con condiciones WHERE (ej: ["sv.stock > 0", "p.id_categoria = ?"])
 * @param array &$params Array por referencia: se llena con valores para bind_param
 * @param string &$types String por referencia: se llena con tipos para bind_param (ej: "iss")
 * @return void Modifica los arrays por referencia
 */
function _construirFiltrosWhere($filtros, &$where_parts, &$params, &$types) {
    // Validar que filtros sea un array
    if (!is_array($filtros)) {
        $filtros = [];
    }
    
    // Inicializar arrays si no están inicializados
    if (!is_array($where_parts)) {
        $where_parts = [];
    }
    if (!is_array($params)) {
        $params = [];
    }
    if (!is_string($types)) {
        $types = '';
    }
    
    // Filtro de categoría: debe ser un entero positivo
    if (!empty($filtros['categoria_id'])) {
        $categoria_id = filter_var($filtros['categoria_id'], FILTER_VALIDATE_INT);
        if ($categoria_id !== false && $categoria_id > 0) {
            $where_parts[] = "p.id_categoria = ?";
            $params[] = $categoria_id;
            $types .= 'i'; // Tipo integer para ID de categoría
        }
    }
    
    // Filtro de talles: solo talles estándar válidos (S, M, L, XL)
    // Si no hay filtro de talles, no se agrega condición (permite mostrar todos los talles)
    if (!empty($filtros['talles']) && is_array($filtros['talles'])) {
        // Obtener talles estándar válidos
        if (!function_exists('obtenerTallesEstandar')) {
            require_once __DIR__ . '/../talles_config.php';
        }
        $talles_validos = obtenerTallesEstandar();
        
        // Filtrar solo talles estándar válidos
        $talles_filtrados = array_intersect($filtros['talles'], $talles_validos);
        if (!empty($talles_filtrados)) {
            // Si hay talles válidos, agregar condición IN
            $placeholders = _crearPlaceholdersSQL(count($talles_filtrados));
            if (!empty($placeholders)) {
                $where_parts[] = "sv.talle IN ($placeholders)";
                $params = array_merge($params, $talles_filtrados);
                $types .= str_repeat('s', count($talles_filtrados)); // Tipo string para cada talle
            }
        } else {
            // Si no hay talles válidos, agregar condición imposible (no mostrar productos)
            $where_parts[] = "1=0";
        }
    }
    
    // Filtro de géneros: solo géneros válidos del enum (hombre, mujer, unisex)
    if (!empty($filtros['generos']) && is_array($filtros['generos'])) {
        $generos_validos = ['hombre', 'mujer', 'unisex'];
        $generos_filtrados = array_intersect($filtros['generos'], $generos_validos);
        
        if (!empty($generos_filtrados)) {
            // Si hay géneros válidos, agregar condición IN
            $placeholders = _crearPlaceholdersSQL(count($generos_filtrados));
            if (!empty($placeholders)) {
                $where_parts[] = "p.genero IN ($placeholders)";
                $params = array_merge($params, $generos_filtrados);
                $types .= str_repeat('s', count($generos_filtrados)); // Tipo string para cada género
            }
        }
    }
    
    // Filtro de colores: normalizar para comparación case-insensitive
    if (!empty($filtros['colores']) && is_array($filtros['colores'])) {
        // Normalizar colores: convertir a minúsculas y eliminar espacios
        $colores_normalizados = array_map(function($color) {
            return strtolower(trim($color));
        }, $filtros['colores']);
        
        // Filtrar colores vacíos después de normalizar
        $colores_normalizados = array_filter($colores_normalizados, function($color) {
            return !empty($color);
        });
        
        if (!empty($colores_normalizados)) {
            // Si hay colores válidos, agregar condición IN con normalización en SQL
            $placeholders = _crearPlaceholdersSQL(count($colores_normalizados));
            if (!empty($placeholders)) {
                $where_parts[] = "LOWER(TRIM(sv.color)) IN ($placeholders)";
                $params = array_merge($params, array_values($colores_normalizados));
                $types .= str_repeat('s', count($colores_normalizados)); // Tipo string para cada color
            }
        }
    }
}

/**
 * Función auxiliar: Obtiene IDs únicos de productos que cumplen los filtros especificados
 * 
 * Esta función ejecuta la primera consulta para obtener solo los IDs de productos que
 * cumplen todos los filtros. Es más eficiente que obtener todos los datos de una vez.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $where_parts Array con condiciones WHERE (ej: ["sv.stock > 0", "p.id_categoria = ?"])
 * @param array $params Array con valores para bind_param
 * @param string $types String con tipos para bind_param (ej: "iss")
 * @return array Array de IDs de productos (enteros) o array vacío si falla o no hay resultados
 */
function _obtenerIdsProductosFiltrados($mysqli, $where_parts, $params, $types) {
    // Validación de entrada: verificar que la conexión a la base de datos no sea null
    if ($mysqli === null || !($mysqli instanceof mysqli)) {
        return [];
    }
    
    // Validar que where_parts sea un array y no esté vacío
    if (!is_array($where_parts) || empty($where_parts)) {
        return [];
    }
    
    // Agregar condición adicional: solo categorías activas
    $where_parts_ids = array_merge($where_parts, ["c.activo = 1"]);
    
    // Construir consulta SQL para obtener IDs únicos
    // DISTINCT asegura que no haya IDs duplicados
    // JOIN con Stock_Variantes para aplicar filtros de talle/color
    // JOIN con Categorias para filtrar por categoría activa
    $sql_ids = "SELECT DISTINCT p.id_producto
                FROM Productos p
                INNER JOIN Stock_Variantes sv ON p.id_producto = sv.id_producto AND sv.activo = 1
                INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
                WHERE " . implode(' AND ', $where_parts_ids);
    
    // Preparar statement SQL
    $stmt_ids = $mysqli->prepare($sql_ids);
    if (!$stmt_ids) {
        // Si falla la preparación, loggear error y retornar array vacío
        error_log("ERROR _obtenerIdsProductosFiltrados - prepare falló: " . $mysqli->error);
        error_log("SQL: " . $sql_ids);
        return [];
    }
    
    // Bindear parámetros si hay alguno
    if (!empty($params) && !empty($types)) {
        if (!$stmt_ids->bind_param($types, ...$params)) {
            // Si falla el bind_param, cerrar statement y retornar array vacío
            error_log("ERROR _obtenerIdsProductosFiltrados - bind_param falló: " . $stmt_ids->error);
            $stmt_ids->close();
            return [];
        }
    }
    
    // Ejecutar consulta
    if (!$stmt_ids->execute()) {
        // Si falla la ejecución, loggear error y retornar array vacío
        error_log("ERROR _obtenerIdsProductosFiltrados - execute falló: " . $stmt_ids->error);
        $stmt_ids->close();
        return [];
    }
    
    // Obtener resultados y extraer IDs de productos
    $result_ids = $stmt_ids->get_result();
    if (!$result_ids) {
        // Si no se puede obtener el resultado, cerrar statement y retornar array vacío
        $stmt_ids->close();
        return [];
    }
    
    $productos_ids = [];
    while ($row = $result_ids->fetch_assoc()) {
        // Validar que el ID sea un entero válido
        if (isset($row['id_producto'])) {
            $id_producto = filter_var($row['id_producto'], FILTER_VALIDATE_INT);
            if ($id_producto !== false && $id_producto > 0) {
                $productos_ids[] = $id_producto;
            }
        }
    }
    
    // Cerrar statement y retornar IDs
    $stmt_ids->close();
    return $productos_ids;
}

/**
 * Función auxiliar: Construye la consulta SQL final para obtener datos completos de productos
 * 
 * Esta función construye la consulta SQL compleja con subconsultas para obtener:
 * - Datos básicos del producto (nombre, precio, género, categoría)
 * - Color del producto (primer color disponible)
 * - Stock total del producto
 * - Foto miniatura (con estrategia de fallback)
 * 
 * Tiene dos variantes: una con filtro de talles (solo talles estándar) y otra sin filtro (todos los talles).
 * 
 * @param array $productos_ids Array de IDs de productos (enteros)
 * @param bool $hay_filtro_talles Si true, las subconsultas filtran por talles estándar
 * @return array Array con 'sql' (string), 'types' (string), 'params' (array) o null si falla
 */
function _construirQueryDatosCompletos($productos_ids, $hay_filtro_talles) {
    // Validar que productos_ids sea un array no vacío
    if (!is_array($productos_ids) || empty($productos_ids)) {
        return null;
    }
    
    // Validar que todos los IDs sean enteros válidos
    $productos_ids_validos = [];
    foreach ($productos_ids as $id) {
        $id_validado = filter_var($id, FILTER_VALIDATE_INT);
        if ($id_validado !== false && $id_validado > 0) {
            $productos_ids_validos[] = $id_validado;
        }
    }
    
    if (empty($productos_ids_validos)) {
        return null;
    }
    
    // Crear placeholders para la cláusula IN con los IDs de productos
    $placeholders = _crearPlaceholdersSQL(count($productos_ids_validos));
    if (empty($placeholders)) {
        return null;
    }
    
    // Construir consulta base que es común para ambas variantes
    $sql_base = "SELECT DISTINCT
                        p.id_producto, 
                        p.nombre_producto, 
                        p.descripcion_producto, 
                        p.precio_actual, 
                        p.genero, 
                        c.nombre_categoria";
    
    // Construir subconsultas de color y stock según si hay filtro de talles
    if ($hay_filtro_talles) {
        // Obtener talles estándar para usar en las subconsultas
        if (!function_exists('obtenerTallesEstandar')) {
            require_once __DIR__ . '/../talles_config.php';
        }
        $talles_estandar = obtenerTallesEstandar();
        
        if (!is_array($talles_estandar) || empty($talles_estandar)) {
            return null;
        }
        
        $placeholders_talles = _crearPlaceholdersSQL(count($talles_estandar));
        if (empty($placeholders_talles)) {
            return null;
        }
        
        // Subconsultas con filtro de talles estándar
        $subquery_color = "(SELECT MIN(sv_min.color) 
                           FROM Stock_Variantes sv_min 
                           WHERE sv_min.id_producto = p.id_producto 
                           AND sv_min.stock > 0
                           AND sv_min.activo = 1
                           AND sv_min.talle IN ($placeholders_talles))";
        
        $subquery_stock = "(SELECT SUM(sv_sum.stock) 
                           FROM Stock_Variantes sv_sum 
                           WHERE sv_sum.id_producto = p.id_producto 
                           AND sv_sum.stock > 0
                           AND sv_sum.activo = 1
                           AND sv_sum.talle IN ($placeholders_talles))";
        
        // Construir SQL completo con filtro de talles
        // Nota: La subconsulta dentro de COALESCE debe incluirse directamente en el string SQL
        $sql = $sql_base . ",
                        $subquery_color as color,
                        $subquery_stock as total_stock,
                        COALESCE(
                            -- 1. Buscar foto del color específico que coincide con el color mostrado
                            (SELECT fp1.foto_prod_miniatura 
                             FROM Fotos_Producto fp1 
                             INNER JOIN Productos p_foto1 ON fp1.id_producto = p_foto1.id_producto
                             WHERE p_foto1.nombre_producto = p.nombre_producto
                             AND p_foto1.id_categoria = p.id_categoria
                             AND p_foto1.genero = p.genero
                             AND p_foto1.activo = 1
                             AND fp1.activo = 1
                             AND LOWER(TRIM(COALESCE(fp1.color, ''))) = LOWER(TRIM((SELECT MIN(sv_color.color) 
                                                      FROM Stock_Variantes sv_color 
                                                      WHERE sv_color.id_producto = p.id_producto 
                                                      AND sv_color.stock > 0
                                                      AND sv_color.activo = 1
                                                      AND sv_color.talle IN ($placeholders_talles))))
                             AND fp1.foto_prod_miniatura IS NOT NULL
                             AND fp1.foto_prod_miniatura != ''
                             ORDER BY fp1.id_producto = p.id_producto DESC
                             LIMIT 1),
                            -- 2. Buscar foto general (color NULL) en productos del mismo grupo
                            (SELECT fp2.foto_prod_miniatura 
                             FROM Fotos_Producto fp2 
                             INNER JOIN Productos p_foto2 ON fp2.id_producto = p_foto2.id_producto
                             WHERE p_foto2.nombre_producto = p.nombre_producto
                             AND p_foto2.id_categoria = p.id_categoria
                             AND p_foto2.genero = p.genero
                             AND p_foto2.activo = 1
                             AND fp2.activo = 1
                             AND (fp2.color IS NULL OR fp2.color = '')
                             AND fp2.foto_prod_miniatura IS NOT NULL
                             AND fp2.foto_prod_miniatura != ''
                             ORDER BY fp2.id_producto = p.id_producto DESC
                             LIMIT 1),
                            -- 3. Buscar cualquier foto del mismo producto como último recurso
                            (SELECT fp3.foto_prod_miniatura 
                             FROM Fotos_Producto fp3 
                             WHERE fp3.id_producto = p.id_producto 
                             AND fp3.activo = 1
                             AND fp3.foto_prod_miniatura IS NOT NULL
                             AND fp3.foto_prod_miniatura != ''
                             ORDER BY fp3.color IS NULL DESC
                             LIMIT 1)
                        ) as foto_prod_miniatura
                  FROM Productos p
                  INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
                  WHERE p.id_producto IN ($placeholders)
                  AND p.activo = 1
                  AND c.activo = 1
                  GROUP BY p.id_producto, p.nombre_producto, p.descripcion_producto, p.precio_actual, p.genero, c.nombre_categoria
                  ORDER BY p.nombre_producto";
        
        // Construir tipos y parámetros: talles (3 veces) + IDs de productos
        $types = str_repeat('s', count($talles_estandar)) . str_repeat('s', count($talles_estandar)) . str_repeat('s', count($talles_estandar)) . str_repeat('i', count($productos_ids_validos));
        $params = array_merge($talles_estandar, $talles_estandar, $talles_estandar, $productos_ids_validos);
    } else {
        // Subconsultas sin filtro de talles (todos los talles, incluyendo numéricos)
        $subquery_color = "(SELECT MIN(sv_min.color) 
                           FROM Stock_Variantes sv_min 
                           WHERE sv_min.id_producto = p.id_producto 
                           AND sv_min.stock > 0
                           AND sv_min.activo = 1)";
        
        $subquery_stock = "(SELECT SUM(sv_sum.stock) 
                           FROM Stock_Variantes sv_sum 
                           WHERE sv_sum.id_producto = p.id_producto 
                           AND sv_sum.stock > 0
                           AND sv_sum.activo = 1)";
        
        // Construir SQL completo sin filtro de talles
        // Nota: La subconsulta dentro de COALESCE debe incluirse directamente en el string SQL
        $sql = $sql_base . ",
                        $subquery_color as color,
                        $subquery_stock as total_stock,
                        COALESCE(
                            -- 1. Buscar foto del color específico que coincide con el color mostrado
                            (SELECT fp1.foto_prod_miniatura 
                             FROM Fotos_Producto fp1 
                             INNER JOIN Productos p_foto1 ON fp1.id_producto = p_foto1.id_producto
                             WHERE p_foto1.nombre_producto = p.nombre_producto
                             AND p_foto1.id_categoria = p.id_categoria
                             AND p_foto1.genero = p.genero
                             AND p_foto1.activo = 1
                             AND fp1.activo = 1
                             AND LOWER(TRIM(COALESCE(fp1.color, ''))) = LOWER(TRIM((SELECT MIN(sv_color.color) 
                                                      FROM Stock_Variantes sv_color 
                                                      WHERE sv_color.id_producto = p.id_producto 
                                                      AND sv_color.stock > 0
                                                      AND sv_color.activo = 1)))
                             AND fp1.foto_prod_miniatura IS NOT NULL
                             AND fp1.foto_prod_miniatura != ''
                             ORDER BY fp1.id_producto = p.id_producto DESC
                             LIMIT 1),
                            -- 2. Buscar foto general (color NULL) en productos del mismo grupo
                            (SELECT fp2.foto_prod_miniatura 
                             FROM Fotos_Producto fp2 
                             INNER JOIN Productos p_foto2 ON fp2.id_producto = p_foto2.id_producto
                             WHERE p_foto2.nombre_producto = p.nombre_producto
                             AND p_foto2.id_categoria = p.id_categoria
                             AND p_foto2.genero = p.genero
                             AND p_foto2.activo = 1
                             AND fp2.activo = 1
                             AND (fp2.color IS NULL OR fp2.color = '')
                             AND fp2.foto_prod_miniatura IS NOT NULL
                             AND fp2.foto_prod_miniatura != ''
                             ORDER BY fp2.id_producto = p.id_producto DESC
                             LIMIT 1),
                            -- 3. Buscar cualquier foto del mismo producto como último recurso
                            (SELECT fp3.foto_prod_miniatura 
                             FROM Fotos_Producto fp3 
                             WHERE fp3.id_producto = p.id_producto 
                             AND fp3.activo = 1
                             AND fp3.foto_prod_miniatura IS NOT NULL
                             AND fp3.foto_prod_miniatura != ''
                             ORDER BY fp3.color IS NULL DESC
                             LIMIT 1)
                        ) as foto_prod_miniatura
                  FROM Productos p
                  INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
                  WHERE p.id_producto IN ($placeholders)
                  AND p.activo = 1
                  AND c.activo = 1
                  GROUP BY p.id_producto, p.nombre_producto, p.descripcion_producto, p.precio_actual, p.genero, c.nombre_categoria
                  ORDER BY p.nombre_producto";
        
        // Solo IDs de productos (sin talles)
        $types = str_repeat('i', count($productos_ids_validos));
        $params = $productos_ids_validos;
    }
    
    return [
        'sql' => $sql,
        'types' => $types,
        'params' => $params
    ];
}

/**
 * Función auxiliar: Ejecuta la consulta SQL final y retorna los productos completos
 * 
 * Esta función ejecuta la consulta SQL compleja que obtiene todos los datos de los productos,
 * incluyendo color, stock y foto. Maneja errores y valida resultados.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $sql Consulta SQL completa con subconsultas
 * @param string $types String con tipos para bind_param (ej: "ssssiiii")
 * @param array $params Array con valores para bind_param
 * @return array Array de productos (arrays asociativos) o array vacío si falla
 */
function _obtenerDatosCompletosProductos($mysqli, $sql, $types, $params) {
    // Validación de entrada: verificar que la conexión a la base de datos no sea null
    if ($mysqli === null || !($mysqli instanceof mysqli)) {
        return [];
    }
    
    // Validar que SQL sea un string no vacío
    if (empty($sql) || !is_string($sql)) {
        return [];
    }
    
    // Validar que types sea un string y params sea un array
    if (!is_string($types) || !is_array($params)) {
        return [];
    }
    
    // Preparar statement SQL
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        // Si falla la preparación, loggear error y retornar array vacío
        error_log("ERROR _obtenerDatosCompletosProductos - prepare falló: " . $mysqli->error);
        error_log("SQL: " . substr($sql, 0, 500) . "...");
        return [];
    }
    
    // Bindear parámetros si hay alguno
    if (!empty($params) && !empty($types)) {
        if (!$stmt->bind_param($types, ...$params)) {
            // Si falla el bind_param, cerrar statement y retornar array vacío
            error_log("ERROR _obtenerDatosCompletosProductos - bind_param falló: " . $stmt->error);
            $stmt->close();
            return [];
        }
    }
    
    // Ejecutar consulta
    if (!$stmt->execute()) {
        // Si falla la ejecución, loggear error y retornar array vacío
        error_log("ERROR _obtenerDatosCompletosProductos - execute falló: " . $stmt->error);
        $stmt->close();
        return [];
    }
    
    // Obtener resultados
    $result = $stmt->get_result();
    if (!$result) {
        // Si no se puede obtener el resultado, cerrar statement y retornar array vacío
        $stmt->close();
        return [];
    }
    
    // Convertir resultado a array asociativo
    // Cada elemento del array contiene todos los datos del producto
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        // Validar que la fila tenga al menos el ID del producto
        if (isset($row['id_producto'])) {
            $productos[] = $row;
        }
    }
    
    // Cerrar statement y retornar productos
    $stmt->close();
    return $productos;
}

/**
 * Obtiene productos filtrados para el catálogo (con filtros de talle, color, categoría)
 * 
 * Esta función retorna un array con todos los productos que cumplen los filtros especificados.
 * Solo muestra productos con stock disponible y variantes activas.
 * 
 * ESTRATEGIA DE DOS CONSULTAS:
 * 1. Primera consulta: Obtener IDs únicos de productos que cumplen los filtros
 * 2. Segunda consulta: Obtener datos completos de esos productos (con color, stock, foto)
 * 
 * Esta estrategia permite aplicar filtros complejos primero y luego obtener datos completos
 * solo de los productos que realmente cumplen los criterios.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $filtros Array con filtros: categoria_id (int), talles[] (array), generos[] (array), colores[] (array)
 * @return array Array asociativo de productos con: id_producto, nombre_producto, descripcion_producto, precio_actual, genero, nombre_categoria, color, total_stock, foto_prod_miniatura
 */
function obtenerProductosFiltradosCatalogo($mysqli, $filtros = []) {
    // ===================================================================
    // SECCIÓN 1: INICIALIZACIÓN Y VALIDACIÓN DE DEPENDENCIAS
    // ===================================================================
    // Verificar que la función obtenerTallesEstandar() esté disponible
    // (debería estar incluida desde talles_config.php, pero incluimos por si acaso)
    if (!function_exists('obtenerTallesEstandar')) {
        require_once __DIR__ . '/../talles_config.php';
    }
    
    // ===================================================================
    // SECCIÓN 2: CONSTRUCCIÓN DE FILTROS WHERE DINÁMICOS
    // ===================================================================
    // Construir condiciones WHERE base que siempre se aplican:
    // - Solo variantes con stock disponible (stock > 0)
    // - Solo variantes activas (activo = 1)
    // - Solo productos activos (activo = 1)
    $where_parts = [
        "sv.stock > 0",
        "sv.activo = 1",
        "p.activo = 1"
    ];
    // Arrays para almacenar parámetros y tipos para prepared statements
    $params = [];
    $types = '';
    
    // Usar función auxiliar para construir filtros WHERE dinámicos
    // Esta función procesa los filtros y agrega condiciones WHERE, parámetros y tipos
    _construirFiltrosWhere($filtros, $where_parts, $params, $types);
    
    // ===================================================================
    // SECCIÓN 3: PRIMERA CONSULTA - OBTENER IDs DE PRODUCTOS QUE CUMPLEN FILTROS
    // ===================================================================
    // Usar función auxiliar para obtener IDs de productos que cumplen los filtros
    // Esta consulta es más eficiente que obtener todos los datos de una vez
    $productos_ids = _obtenerIdsProductosFiltrados($mysqli, $where_parts, $params, $types);
    
    // ===================================================================
    // SECCIÓN 4: SEGUNDA CONSULTA - OBTENER DATOS COMPLETOS DE PRODUCTOS
    // ===================================================================
    // Solo si hay productos que cumplen los filtros, obtener sus datos completos
    // Esto incluye: nombre, precio, género, categoría, color, stock total, y foto miniatura
    if (!empty($productos_ids)) {
        // Determinar si hay filtro de talles para decidir qué subconsultas usar
        // Esto afecta cómo se calcula el color y stock: si hay filtro, solo usar talles estándar
        $hay_filtro_talles = !empty($filtros['talles']) && is_array($filtros['talles']);
        
        // Usar función auxiliar para construir la consulta SQL final
        $query_data = _construirQueryDatosCompletos($productos_ids, $hay_filtro_talles);
        
        if ($query_data === null) {
            // Si falla la construcción de la consulta, retornar array vacío
            return [];
        }
        
        // Usar función auxiliar para ejecutar la consulta y obtener productos
        $productos = _obtenerDatosCompletosProductos($mysqli, $query_data['sql'], $query_data['types'], $query_data['params']);
        
        return $productos;
    } else {
        // ===================================================================
        // CASO ESPECIAL: NO HAY PRODUCTOS QUE CUMPLAN LOS FILTROS
        // ===================================================================
        // Si no hay productos que cumplan los filtros, retornar array vacío
        return [];
    }
}

/**
 * Función auxiliar: Crea placeholders SQL para usar en cláusulas IN
 * 
 * Genera una cadena de placeholders (?) separados por comas para usar en consultas SQL
 * con prepared statements. Ejemplo: para 4 valores genera "?,?,?,?"
 * 
 * @param int $cantidad Cantidad de placeholders a generar
 * @return string Cadena de placeholders SQL (ej: "?,?,?")
 */
function _crearPlaceholdersSQL($cantidad) {
    // Validar que la cantidad sea un entero positivo
    if (!is_int($cantidad) || $cantidad <= 0) {
        return '';
    }
    
    // Si hay solo un placeholder, retornar solo "?"
    if ($cantidad === 1) {
        return '?';
    }
    
    // Generar placeholders: str_repeat('?,', count-1) genera "?,?,?" y luego agregamos "?" al final
    return str_repeat('?,', $cantidad - 1) . '?';
}

/**
 * Obtiene talles disponibles con stock, opcionalmente filtrados por categoría
 * Solo retorna talles estándar (S, M, L, XL)
 * 
 * Esta función consulta la base de datos para obtener todos los talles estándar que tienen
 * productos con stock disponible. Filtra solo talles estándar (no incluye talles numéricos como 28, 30, etc.).
 * Solo considera variantes y productos activos.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int|null $categoria_id ID de categoría para filtrar (null = todas las categorías)
 * @return array Array asociativo [talle => stock_total] - Solo talles estándar (S, M, L, XL)
 */
function obtenerTallesDisponibles($mysqli, $categoria_id = null) {
    // Validación de entrada: verificar que la conexión a la base de datos no sea null
    if ($mysqli === null || !($mysqli instanceof mysqli)) {
        return [];
    }
    
    // Validación de categoria_id: debe ser un entero positivo o null
    if ($categoria_id !== null) {
        $categoria_id = filter_var($categoria_id, FILTER_VALIDATE_INT);
        if ($categoria_id === false || $categoria_id <= 0) {
            // Si el ID de categoría no es válido, retornar array vacío
            return [];
        }
    }
    
    // Verificar si la función obtenerTallesEstandar() existe (debería estar incluida desde talles_config.php)
    // Si no existe, incluir el archivo que la contiene
    if (!function_exists('obtenerTallesEstandar')) {
        require_once __DIR__ . '/../talles_config.php';
    }
    
    // Obtener array de talles estándar (ej: ['S', 'M', 'L', 'XL'])
    $talles_estandar = obtenerTallesEstandar();
    
    // Validar que obtenerTallesEstandar() retorne un array válido y no vacío
    if (!is_array($talles_estandar) || empty($talles_estandar)) {
        // Si no hay talles estándar definidos, retornar array vacío
        return [];
    }
    
    // Construir placeholders SQL usando función auxiliar para usar en IN clause
    // Ejemplo: si hay 4 talles ['S', 'M', 'L', 'XL'], genera "?,?,?,?"
    $placeholders = _crearPlaceholdersSQL(count($talles_estandar));
    if (empty($placeholders)) {
        // Si falla la construcción de placeholders, retornar array vacío
        return [];
    }
    
    // Construir consulta SQL base: seleccionar talle y sumar stock total por talle
    // JOIN con Productos para poder filtrar por categoría si es necesario
    // WHERE: solo variantes con stock > 0, activas, productos activos, y talles estándar
    $sql = "SELECT sv.talle, SUM(sv.stock) as total_stock
            FROM Stock_Variantes sv
            INNER JOIN Productos p ON sv.id_producto = p.id_producto
            WHERE sv.stock > 0 
            AND sv.activo = 1
            AND p.activo = 1
            AND sv.talle IN ($placeholders)";
    
    // Si se especifica una categoría, agregar filtro por categoría
    // Esto permite obtener talles solo para productos de una categoría específica
    if ($categoria_id !== null) {
        $sql .= " AND p.id_categoria = ?";
    }
    
    // Agrupar por talle y filtrar solo talles con stock total > 0
    // Ordenar alfabéticamente por talle para consistencia
    $sql .= " GROUP BY sv.talle HAVING total_stock > 0 ORDER BY sv.talle";
    
    // Preparar statement SQL para evitar inyección SQL
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        // Si falla la preparación, retornar array vacío para evitar errores
        return [];
    }
    
    // Construir string de tipos para bind_param
    // 's' = string para cada talle (ej: "ssss" para 4 talles)
    $types = str_repeat('s', count($talles_estandar));
    // Inicializar array de parámetros con los talles estándar
    $params = $talles_estandar;
    
    // Si hay filtro de categoría, agregar tipo 'i' (integer) y el ID de categoría a los parámetros
    if ($categoria_id !== null) {
        $types .= 'i'; // Agregar tipo integer al final
        $params[] = $categoria_id; // Agregar ID de categoría al final del array de parámetros
    }
    
    // Bindear parámetros al statement y verificar si fue exitoso
    // El spread operator (...) expande el array $params como argumentos individuales
    if (!$stmt->bind_param($types, ...$params)) {
        // Si falla el bind_param, cerrar statement y retornar array vacío
        $stmt->close();
        return [];
    }
    
    // Ejecutar la consulta y verificar si fue exitosa
    if (!$stmt->execute()) {
        // Si falla la ejecución, cerrar statement y retornar array vacío
        $stmt->close();
        return [];
    }
    
    // Obtener resultado de la consulta
    $result = $stmt->get_result();
    if (!$result) {
        // Si no se puede obtener el resultado, cerrar statement y retornar array vacío
        $stmt->close();
        return [];
    }
    
    // Inicializar array para almacenar talles con su stock total
    $talles = [];
    // Iterar sobre cada fila del resultado
    while ($row = $result->fetch_assoc()) {
        // Validar que la fila tenga los campos necesarios
        if (isset($row['talle']) && isset($row['total_stock'])) {
            // Guardar talle como clave y stock total como valor (convertido a int)
            // El talle viene del array de talles estándar (S, M, L, XL)
            $talles[$row['talle']] = (int)$row['total_stock'];
        }
    }
    
    // Cerrar statement para liberar recursos
    $stmt->close();
    // Retornar array asociativo [talle => stock_total]
    return $talles;
}

/**
 * Obtiene géneros disponibles con stock desde la base de datos, opcionalmente filtrados por categoría
 * 
 * Esta función consulta la base de datos para obtener todos los géneros (hombre, mujer, unisex)
 * que tienen productos con stock disponible. Solo considera variantes y productos activos.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int|null $categoria_id ID de categoría para filtrar (null = todas las categorías)
 * @return array Array asociativo [genero => stock_total] donde stock_total es la suma de stock de todas las variantes de ese género
 */
function obtenerGenerosDisponiblesStock($mysqli, $categoria_id = null) {
    // Validación de entrada: verificar que la conexión a la base de datos no sea null
    if ($mysqli === null || !($mysqli instanceof mysqli)) {
        return [];
    }
    
    // Validación de categoria_id: debe ser un entero positivo o null
    if ($categoria_id !== null) {
        $categoria_id = filter_var($categoria_id, FILTER_VALIDATE_INT);
        if ($categoria_id === false || $categoria_id <= 0) {
            // Si el ID de categoría no es válido, retornar array vacío
            return [];
        }
    }
    
    // Construir consulta SQL base: seleccionar género y sumar stock total por género
    // JOIN con Productos para obtener el género del producto
    // WHERE: solo variantes con stock > 0, activas, y productos activos
    $sql = "SELECT p.genero, SUM(sv.stock) as total_stock
            FROM Stock_Variantes sv
            INNER JOIN Productos p ON sv.id_producto = p.id_producto
            WHERE sv.stock > 0
            AND sv.activo = 1
            AND p.activo = 1";
    
    // Si se especifica una categoría, agregar filtro por categoría
    // Esto permite obtener géneros solo para productos de una categoría específica
    if ($categoria_id !== null) {
        $sql .= " AND p.id_categoria = ?";
    }
    
    // Agrupar por género y filtrar solo géneros con stock total > 0
    // Ordenar alfabéticamente por género para consistencia
    $sql .= " GROUP BY p.genero HAVING total_stock > 0 ORDER BY p.genero";
    
    // Preparar statement SQL para evitar inyección SQL
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        // Si falla la preparación, retornar array vacío para evitar errores
        return [];
    }
    
    // Si hay filtro de categoría, bindear el parámetro
    // Tipo 'i' = integer para el ID de categoría
    if ($categoria_id !== null) {
        if (!$stmt->bind_param('i', $categoria_id)) {
            // Si falla el bind_param, cerrar statement y retornar array vacío
            $stmt->close();
            return [];
        }
    }
    
    // Ejecutar la consulta y verificar si fue exitosa
    if (!$stmt->execute()) {
        // Si falla la ejecución, cerrar statement y retornar array vacío
        $stmt->close();
        return [];
    }
    
    // Obtener resultado de la consulta
    $result = $stmt->get_result();
    if (!$result) {
        // Si no se puede obtener el resultado, cerrar statement y retornar array vacío
        $stmt->close();
        return [];
    }
    
    // Inicializar array para almacenar géneros con su stock total
    $generos = [];
    // Iterar sobre cada fila del resultado
    while ($row = $result->fetch_assoc()) {
        // Validar que la fila tenga los campos necesarios
        if (isset($row['genero']) && isset($row['total_stock'])) {
            // Guardar género como clave y stock total como valor (convertido a int)
            // El género viene del enum: 'hombre', 'mujer', 'unisex'
            $generos[$row['genero']] = (int)$row['total_stock'];
        }
    }
    
    // Cerrar statement para liberar recursos
    $stmt->close();
    // Retornar array asociativo [genero => stock_total]
    return $generos;
}

/**
 * Función auxiliar: Normaliza un color a formato estándar (primera letra mayúscula, resto minúscula)
 * 
 * Esta función asegura que todos los colores tengan el mismo formato para evitar duplicados
 * por diferencias de mayúsculas/minúsculas o espacios.
 * 
 * @param string $color Color a normalizar (puede tener cualquier formato)
 * @return string Color normalizado (primera letra mayúscula, resto minúscula, sin espacios al inicio/final)
 */
function _normalizarColor($color) {
    // Validar que el color no sea null o vacío
    if (empty($color) || !is_string($color)) {
        return '';
    }
    
    // Normalizar: trim() elimina espacios, strtolower() convierte a minúsculas, ucfirst() pone primera letra mayúscula
    return ucfirst(strtolower(trim($color)));
}

/**
 * Obtiene colores disponibles con stock desde la base de datos, opcionalmente filtrados por categoría
 * 
 * Esta función consulta la base de datos para obtener todos los colores que tienen productos
 * con stock disponible. Normaliza los colores para evitar duplicados por formato (ej: "Negro", "negro", "NEGRO").
 * Solo considera variantes y productos activos.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int|null $categoria_id ID de categoría para filtrar (null = todas las categorías)
 * @return array Array asociativo [color => stock_total] donde color está normalizado (primera letra mayúscula)
 */
function obtenerColoresDisponiblesStock($mysqli, $categoria_id = null) {
    // Validación de entrada: verificar que la conexión a la base de datos no sea null
    if ($mysqli === null || !($mysqli instanceof mysqli)) {
        return [];
    }
    
    // Validación de categoria_id: debe ser un entero positivo o null
    if ($categoria_id !== null) {
        $categoria_id = filter_var($categoria_id, FILTER_VALIDATE_INT);
        if ($categoria_id === false || $categoria_id <= 0) {
            // Si el ID de categoría no es válido, retornar array vacío
            return [];
        }
    }
    // Construir consulta SQL base: seleccionar color y sumar stock total por color
    // JOIN con Productos para poder filtrar por categoría si es necesario
    // WHERE: solo variantes con stock > 0, activas, y productos activos
    $sql = "SELECT sv.color, SUM(sv.stock) as total_stock
            FROM Stock_Variantes sv
            INNER JOIN Productos p ON sv.id_producto = p.id_producto
            WHERE sv.stock > 0
            AND sv.activo = 1
            AND p.activo = 1";
    
    // Si se especifica una categoría, agregar filtro por categoría
    // Esto permite obtener colores solo para productos de una categoría específica
    if ($categoria_id) {
        $sql .= " AND p.id_categoria = ?";
    }
    
    // Agrupar por color normalizado para evitar duplicados por formato
    // LOWER(TRIM()) agrupa colores que son iguales ignorando mayúsculas/minúsculas y espacios
    // Usar MIN() en ORDER BY para obtener el formato original del color (mantiene orden alfabético)
    // HAVING filtra solo colores con stock total > 0
    $sql .= " GROUP BY LOWER(TRIM(sv.color)) HAVING total_stock > 0 ORDER BY MIN(sv.color)";
    
    // Preparar statement SQL para evitar inyección SQL
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        // Si falla la preparación, retornar array vacío para evitar errores
        return [];
    }
    
    // Si hay filtro de categoría, bindear el parámetro
    // Tipo 'i' = integer para el ID de categoría
    if ($categoria_id !== null) {
        if (!$stmt->bind_param('i', $categoria_id)) {
            // Si falla el bind_param, cerrar statement y retornar array vacío
            $stmt->close();
            return [];
        }
    }
    
    // Ejecutar la consulta y verificar si fue exitosa
    if (!$stmt->execute()) {
        // Si falla la ejecución, cerrar statement y retornar array vacío
        $stmt->close();
        return [];
    }
    
    // Obtener resultado de la consulta
    $result = $stmt->get_result();
    if (!$result) {
        // Si no se puede obtener el resultado, cerrar statement y retornar array vacío
        $stmt->close();
        return [];
    }
    
    // Inicializar array para almacenar colores con su stock total
    $colores = [];
    // Iterar sobre cada fila del resultado
    while ($row = $result->fetch_assoc()) {
        // Validar que la fila tenga los campos necesarios
        if (!isset($row['color']) || !isset($row['total_stock'])) {
            continue; // Saltar filas incompletas
        }
        
        // Normalizar color usando función auxiliar para consistencia
        // Esto asegura que "Negro", "negro", "NEGRO" se conviertan todos en "Negro"
        $color_normalizado = _normalizarColor($row['color']);
        
        // Si el color normalizado está vacío, saltar esta fila
        if (empty($color_normalizado)) {
            continue;
        }
        
        // Acumular stock si hay múltiples formatos del mismo color
        // Aunque agrupamos en SQL, puede haber variaciones mínimas que se normalizan igual
        if (isset($colores[$color_normalizado])) {
            // Si el color ya existe, sumar el stock al existente
            $colores[$color_normalizado] += (int)$row['total_stock'];
        } else {
            // Si es un color nuevo, asignar el stock directamente
            $colores[$color_normalizado] = (int)$row['total_stock'];
        }
    }
    
    // Cerrar statement para liberar recursos
    $stmt->close();
    // Retornar array asociativo [color_normalizado => stock_total]
    return $colores;
}

/**
 * Obtiene estadísticas de productos para el panel de marketing
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array con estadísticas: total_productos, stock_total, productos_sin_stock
 */
function obtenerEstadisticasProductos($mysqli) {
    $stats = [];
    
    // Total de productos activos
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total_productos FROM Productos WHERE activo = 1");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['total_productos'] = $result['total_productos'];
    $stmt->close();
    
    // Stock total (suma de todo el stock de variantes activas)
    $stmt = $mysqli->prepare("
        SELECT COALESCE(SUM(stock), 0) as stock_total
        FROM Stock_Variantes
        WHERE activo = 1
    ");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['stock_total'] = $result['stock_total'];
    $stmt->close();
    
    // Productos sin stock (solo productos activos)
    $stmt = $mysqli->prepare("
        SELECT COUNT(DISTINCT p.id_producto) as productos_sin_stock
        FROM Productos p
        LEFT JOIN Stock_Variantes sv ON sv.id_producto = p.id_producto AND sv.activo = 1
        WHERE p.activo = 1
        AND (sv.id_variante IS NULL 
           OR p.id_producto NOT IN (
               SELECT DISTINCT id_producto 
               FROM Stock_Variantes 
               WHERE stock > 0 AND activo = 1
           ))
    ");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['productos_sin_stock'] = $result['productos_sin_stock'];
    $stmt->close();
    
    return $stats;
}

/**
 * Obtiene productos para el panel de marketing con estadísticas de variantes y stock
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $limite Cantidad de productos a retornar (0 = sin límite)
 * @return array Array de productos con información de variantes y stock
 */
function obtenerProductosMarketing($mysqli, $limite = 0) {
    // Usar prepared statement para mayor seguridad y evitar problemas de caché
    $sql = "
        SELECT p.id_producto, p.nombre_producto, p.descripcion_producto, p.precio_actual, p.genero, 
               c.nombre_categoria, c.id_categoria,
               COUNT(DISTINCT sv.id_variante) as total_variantes,
               COALESCE(SUM(sv.stock), 0) as stock_total,
               GROUP_CONCAT(DISTINCT sv.color ORDER BY sv.color SEPARATOR ',') as colores,
               GROUP_CONCAT(DISTINCT sv.talle ORDER BY sv.talle SEPARATOR ',') as talles
        FROM Productos p
        INNER JOIN Categorias c ON c.id_categoria = p.id_categoria AND c.activo = 1
        LEFT JOIN Stock_Variantes sv ON sv.id_producto = p.id_producto AND sv.activo = 1
        WHERE p.activo = 1 AND p.activo IS NOT NULL
        GROUP BY p.id_producto, p.nombre_producto, p.descripcion_producto, p.precio_actual, p.genero, c.nombre_categoria, c.id_categoria
        ORDER BY p.id_producto DESC
    ";
    
    // Agregar límite si es necesario
    if ($limite > 0) {
        $sql .= " LIMIT ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $limite);
    } else {
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return [];
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        // Verificar adicionalmente que el producto esté activo (doble verificación)
        // Convertir colores de string a array
        if (!empty($row['colores'])) {
            $row['colores_array'] = array_unique(array_filter(explode(',', $row['colores'])));
        } else {
            $row['colores_array'] = [];
        }
        
        // Convertir talles de string a array
        if (!empty($row['talles'])) {
            $row['talles_array'] = array_unique(array_filter(explode(',', $row['talles'])));
        } else {
            $row['talles_array'] = [];
        }
        
        $productos[] = $row;
    }
    
    $stmt->close();
    return $productos;
}

/**
 * Obtiene productos con stock bajo (menor o igual al umbral especificado)
 * Útil para alertas de reposición de inventario
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $umbral Umbral de stock mínimo (default: 10)
 * @return array Array de productos con stock total bajo el umbral
 */
function obtenerProductosBajoStock($mysqli, $umbral = 10) {
    $sql = "
        SELECT 
            p.id_producto,
            p.nombre_producto,
            c.nombre_categoria,
            SUM(sv.stock) as stock_total,
            COUNT(DISTINCT sv.id_variante) as cantidad_variantes
        FROM Productos p
        INNER JOIN Stock_Variantes sv ON p.id_producto = sv.id_producto
        INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
        WHERE p.activo = 1 
        AND sv.activo = 1
        GROUP BY p.id_producto, p.nombre_producto, c.nombre_categoria
        HAVING stock_total <= ?
        ORDER BY stock_total ASC
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('i', $umbral);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
    
    $stmt->close();
    return $productos;
}

/**
 * Obtiene productos con stock pero sin ventas en los últimos N días
 * Útil para identificar productos con stock estancado que necesitan promoción
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $dias_sin_ventas Días sin ventas para considerar (default: 30)
 * @return array Array de productos con stock pero sin movimiento reciente
 */
function obtenerProductosSinMovimiento($mysqli, $dias_sin_ventas = 30) {
    $sql = "
        SELECT 
            p.id_producto,
            p.nombre_producto,
            c.nombre_categoria,
            SUM(sv.stock) as stock_total,
            COUNT(DISTINCT sv.id_variante) as cantidad_variantes
        FROM Productos p
        INNER JOIN Stock_Variantes sv ON p.id_producto = sv.id_producto
        INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
        WHERE p.activo = 1 
        AND sv.activo = 1
        AND p.id_producto NOT IN (
            SELECT DISTINCT sv2.id_producto
            FROM Movimientos_Stock ms
            INNER JOIN Stock_Variantes sv2 ON ms.id_variante = sv2.id_variante
            WHERE ms.tipo_movimiento = 'venta'
            AND ms.fecha_movimiento >= DATE_SUB(NOW(), INTERVAL ? DAY)
        )
        GROUP BY p.id_producto, p.nombre_producto, c.nombre_categoria
        HAVING stock_total > 0
        ORDER BY stock_total DESC
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('i', $dias_sin_ventas);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
    
    $stmt->close();
    return $productos;
}

/**
 * NOTA: La función obtenerTopProductosVendidos() está definida en pedido_queries.php
 * para evitar duplicación. Si necesitas esta función, incluye pedido_queries.php
 */

/**
 * Crea un nuevo producto en la base de datos
 * 
 * Esta función inserta un nuevo producto con todos sus datos básicos.
 * El SKU es opcional (puede ser NULL). La fecha_creacion se establece automáticamente.
 * El producto se crea con activo = 1 por defecto.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $nombre_producto Nombre del producto
 * @param string $descripcion_producto Descripción del producto (puede ser vacío)
 * @param float $precio_actual Precio del producto (debe ser > 0)
 * @param int $id_categoria ID de la categoría del producto
 * @param string $genero Género del producto ('hombre', 'mujer', 'unisex')
 * @param string|null $sku Código SKU del producto (opcional)
 * @return int ID del producto creado o 0 si falló
 */
function crearProducto($mysqli, $nombre_producto, $descripcion_producto, $precio_actual, $id_categoria, $genero, $sku = null) {
    // Validar que la categoría existe y está activa (reemplaza trg_validar_categoria_activa_producto)
    $sql_validar = "SELECT activo FROM Categorias WHERE id_categoria = ?";
    $stmt_validar = $mysqli->prepare($sql_validar);
    if (!$stmt_validar) {
        return 0;
    }
    
    $stmt_validar->bind_param('i', $id_categoria);
    $stmt_validar->execute();
    $result_validar = $stmt_validar->get_result();
    $categoria = $result_validar->fetch_assoc();
    $stmt_validar->close();
    
    if (!$categoria) {
        return 0; // La categoría no existe
    }
    
    if (intval($categoria['activo']) === 0) {
        return 0; // Categoría inactiva
    }
    
    // Crear el producto
    $sql = "INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero, sku, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param('ssdiss', $nombre_producto, $descripcion_producto, $precio_actual, $id_categoria, $genero, $sku);
    $resultado = $stmt->execute();
    
    if ($resultado) {
        $id_producto = $mysqli->insert_id;
        $stmt->close();
        return $id_producto;
    } else {
        $stmt->close();
        return 0;
    }
}

/**
 * Actualiza los datos básicos de un producto existente
 * 
 * Esta función actualiza nombre, descripción, precio, categoría y género de un producto.
 * No actualiza SKU ni fotos. Actualiza fecha_actualizacion automáticamente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto a actualizar
 * @param string $nombre_producto Nuevo nombre del producto
 * @param string $descripcion_producto Nueva descripción del producto
 * @param float $precio_actual Nuevo precio del producto
 * @param int $id_categoria Nueva categoría del producto
 * @param string $genero Nuevo género del producto ('hombre', 'mujer', 'unisex')
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function actualizarProducto($mysqli, $id_producto, $nombre_producto, $descripcion_producto, $precio_actual, $id_categoria, $genero) {
    $sql = "UPDATE Productos SET nombre_producto = ?, descripcion_producto = ?, precio_actual = ?, id_categoria = ?, genero = ?, fecha_actualizacion = NOW() WHERE id_producto = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('ssdissi', $nombre_producto, $descripcion_producto, $precio_actual, $id_categoria, $genero, $id_producto);
    $resultado = $stmt->execute();
    $stmt->close();
    
    return $resultado;
}

/**
 * Crea un registro de foto para un producto
 * 
 * Esta función inserta un registro en Fotos_Producto con las rutas de las imágenes.
 * Los campos de foto que no se proporcionen se establecerán como NULL.
 * El color es opcional y puede ser NULL.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @param string|null $foto_miniatura Ruta de la foto miniatura (opcional)
 * @param string|null $foto1 Ruta de la foto 1 (opcional)
 * @param string|null $foto2 Ruta de la foto 2 (opcional)
 * @param string|null $foto3 Ruta de la foto 3 (opcional)
 * @param string|null $color Color asociado a estas fotos (opcional)
 * @return int ID del registro de foto creado o 0 si falló
 */
function crearFotoProducto($mysqli, $id_producto, $foto_miniatura = null, $foto1 = null, $foto2 = null, $foto3 = null, $color = null) {
    $sql = "INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color) VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param('isssss', $id_producto, $foto_miniatura, $foto1, $foto2, $foto3, $color);
    $resultado = $stmt->execute();
    
    if ($resultado) {
        $id_foto = $mysqli->insert_id;
        $stmt->close();
        return $id_foto;
    } else {
        $stmt->close();
        return 0;
    }
}

/**
 * Cuenta la cantidad de nombres de productos distintos activos
 * 
 * Esta función cuenta cuántos nombres únicos de productos activos existen.
 * Útil para métricas cuando se agrupan productos por nombre (variantes del mismo producto).
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @return int Cantidad de nombres de productos distintos activos
 */
function contarNombresProductosDistintos($mysqli) {
    $sql = "SELECT COUNT(DISTINCT nombre_producto) as total_nombres FROM Productos WHERE activo = 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return intval($row['total_nombres'] ?? 0);
}