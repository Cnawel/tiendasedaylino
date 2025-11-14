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
 * Obtiene datos básicos de un producto para mostrar en el carrito
 * Incluye: id_producto, nombre_producto, precio_actual, foto_prod_miniatura
 * Optimizada para uso en carrito (solo datos necesarios)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @return array|null Array con datos básicos del producto o null si no existe o está inactivo
 */
function obtenerProductoParaCarrito($mysqli, $id_producto) {
    $sql = "
        SELECT 
            p.id_producto,
            p.nombre_producto,
            p.precio_actual,
            fp.foto_prod_miniatura
        FROM Productos p
        LEFT JOIN Fotos_Producto fp ON p.id_producto = fp.id_producto AND fp.activo = 1
        WHERE p.id_producto = ?
        AND p.activo = 1
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
 * Obtiene datos básicos de múltiples productos para el carrito (optimizada)
 * Más eficiente que llamar obtenerProductoParaCarrito() múltiples veces
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $ids_productos Array de IDs de productos
 * @return array Array asociativo [id_producto => datos_producto] o array vacío
 */
function obtenerProductosParaCarrito($mysqli, $ids_productos) {
    if (empty($ids_productos) || !is_array($ids_productos)) {
        return [];
    }
    
    // Validar y filtrar IDs
    $ids_validos = array_filter(array_map('intval', $ids_productos), function($id) {
        return $id > 0;
    });
    
    if (empty($ids_validos)) {
        return [];
    }
    
    // Crear placeholders para IN clause
    $placeholders = str_repeat('?,', count($ids_validos) - 1) . '?';
    
    $sql = "
        SELECT 
            p.id_producto,
            p.nombre_producto,
            p.precio_actual,
            p.id_categoria,
            p.genero,
            c.nombre_categoria
        FROM Productos p
        LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
        WHERE p.id_producto IN ($placeholders)
        AND p.activo = 1
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $types = str_repeat('i', count($ids_validos));
    $stmt->bind_param($types, ...$ids_validos);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[$row['id_producto']] = $row;
    }
    
    $stmt->close();
    return $productos;
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
        $talles_config_path = __DIR__ . '/../talles_config.php';
        if (!file_exists($talles_config_path)) {
            error_log("ERROR: No se pudo encontrar talles_config.php en " . $talles_config_path);
            die("Error crítico: Archivo de configuración de talles no encontrado. Por favor, contacta al administrador.");
        }
        require_once $talles_config_path;
    }
    $talles_estandar = obtenerTallesEstandar();
    $placeholders_talles = str_repeat('?,', count($talles_estandar) - 1) . '?';
    
    // ESTRATEGIA DE MÚLTIPLES QUERIES SIMPLES:
    // Query 1: Obtener productos que cumplen condiciones básicas (nombre, categoría, género)
    $sql_productos = "
        SELECT DISTINCT
            p.id_producto,
            p.nombre_producto,
            p.precio_actual,
            p.genero,
            c.nombre_categoria
        FROM Productos p
        INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
        WHERE p.nombre_producto = ?
        AND p.id_categoria = ?
        AND p.genero = ?
        AND p.id_producto != ?
        AND p.activo = 1
        AND c.activo = 1
        ORDER BY p.id_producto
    ";
    
    $stmt_productos = $mysqli->prepare($sql_productos);
    if (!$stmt_productos) {
        return [];
    }
    
    $stmt_productos->bind_param('sisi', $nombre_producto, $id_categoria, $genero, $id_producto);
    $stmt_productos->execute();
    $result_productos = $stmt_productos->get_result();
    
    $productos = [];
    $productos_ids = [];
    while ($row = $result_productos->fetch_assoc()) {
        $productos[$row['id_producto']] = $row;
        $productos_ids[] = $row['id_producto'];
    }
    $stmt_productos->close();
    
    if (empty($productos_ids)) {
        return [];
    }
    
    // Query 2: Calcular color y stock por producto (por lote) desde Stock_Variantes
    $placeholders_productos = str_repeat('?,', count($productos_ids) - 1) . '?';
    $sql_stock = "
        SELECT 
            id_producto,
            MIN(color) as color,
            SUM(stock) as total_stock
        FROM Stock_Variantes
        WHERE id_producto IN ($placeholders_productos)
        AND activo = 1
        AND stock > 0
        AND talle IN ($placeholders_talles)
        GROUP BY id_producto
    ";
    
    $stmt_stock = $mysqli->prepare($sql_stock);
    if (!$stmt_stock) {
        return [];
    }
    
    $types_stock = str_repeat('i', count($productos_ids)) . str_repeat('s', count($talles_estandar));
    $params_stock = array_merge($productos_ids, $talles_estandar);
    $stmt_stock->bind_param($types_stock, ...$params_stock);
    $stmt_stock->execute();
    $result_stock = $stmt_stock->get_result();
    
    $stock_data = [];
    while ($row = $result_stock->fetch_assoc()) {
        $stock_data[$row['id_producto']] = [
            'color' => $row['color'],
            'total_stock' => intval($row['total_stock'])
        ];
    }
    $stmt_stock->close();
    
    // Combinar resultados en PHP usando id_producto como clave
    // Solo incluir productos que tienen stock disponible
    $variantes = [];
    foreach ($productos as $id_prod => $producto) {
        if (isset($stock_data[$id_prod])) {
            $variantes[] = [
                'id_producto' => $producto['id_producto'],
                'nombre_producto' => $producto['nombre_producto'],
                'precio_actual' => $producto['precio_actual'],
                'genero' => $producto['genero'],
                'nombre_categoria' => $producto['nombre_categoria'],
                'color' => $stock_data[$id_prod]['color'],
                'total_stock' => $stock_data[$id_prod]['total_stock']
            ];
        }
    }
    
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
function obtenerProductoConVariante($mysqli, $id_producto, $talle, $color, $usar_lock = false) {
    // Validar parámetros de entrada y convertir tipos
    $id_producto = intval($id_producto);
    $talle = trim(strval($talle));
    $color = trim(strval($color));
    
    if ($id_producto <= 0 || empty($talle) || empty($color)) {
        return null;
    }
    
    // Si se requiere lock, iniciar transacción
    if ($usar_lock) {
        $mysqli->begin_transaction();
    }
    
    try {
        // Primero intentar buscar en el id_producto específico
        // Usar INNER JOIN para Stock_Variantes para asegurar que la variante existe y está activa
        $sql = "
            SELECT 
                p.id_producto,
                p.nombre_producto,
                p.precio_actual,
                p.id_categoria,
                p.genero,
                c.nombre_categoria,
                sv.id_variante,
                sv.stock,
                sv.talle,
                sv.color
            FROM Productos p
            LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
            INNER JOIN Stock_Variantes sv ON p.id_producto = sv.id_producto 
                AND sv.talle = ? 
                AND sv.color = ?
                AND sv.activo = 1
            WHERE p.id_producto = ?
            AND p.activo = 1
        ";
        
        // Agregar FOR UPDATE si se requiere lock
        if ($usar_lock) {
            $sql .= " FOR UPDATE";
        }
        
        $sql .= " LIMIT 1";
    
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            if ($usar_lock) {
                $mysqli->rollback();
            }
            error_log("Error en obtenerProductoConVariante - prepare falló: " . $mysqli->error);
            return null;
        }
        
        $stmt->bind_param('ssi', $talle, $color, $id_producto);
        
        if (!$stmt->execute()) {
            if ($usar_lock) {
                $mysqli->rollback();
            }
            error_log("Error en obtenerProductoConVariante - execute falló: " . $stmt->error);
            $stmt->close();
            return null;
        }
        
        $result = $stmt->get_result();
        $producto = $result->fetch_assoc();
        $stmt->close();
        
        // Si se encontró la variante en el producto dado, normalizar stock y retornarla
        if ($producto && !empty($producto['id_variante'])) {
            // Asegurar que stock siempre sea un int (0 si es NULL)
            $producto['stock'] = isset($producto['stock']) ? intval($producto['stock']) : 0;
            // Si el stock es negativo, normalizar a 0
            if ($producto['stock'] < 0) {
                $producto['stock'] = 0;
            }
            
            // Si se usó lock, hacer commit
            if ($usar_lock) {
                $mysqli->commit();
            }
            
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
            p.id_categoria,
            p.genero,
            c.nombre_categoria,
            sv.id_variante,
            sv.stock,
            sv.talle,
            sv.color
        FROM Productos p
        LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
        INNER JOIN Stock_Variantes sv ON p.id_producto = sv.id_producto 
            AND sv.talle = ? 
            AND sv.color = ?
            AND sv.activo = 1
        WHERE p.nombre_producto = ?
        AND p.id_categoria = ?
        AND p.genero = ?
        AND p.activo = 1
        AND c.activo = 1
        ";
        
        // Agregar FOR UPDATE si se requiere lock
        if ($usar_lock) {
            $sql_alternativo .= " FOR UPDATE";
        }
        
        $sql_alternativo .= " LIMIT 1";
        
        $stmt_alt = $mysqli->prepare($sql_alternativo);
        if (!$stmt_alt) {
            if ($usar_lock) {
                $mysqli->rollback();
            }
            error_log("Error en obtenerProductoConVariante - prepare alternativo falló: " . $mysqli->error);
            return null;
        }
        
        $stmt_alt->bind_param('sssis', $talle, $color, $nombre_producto, $id_categoria, $genero);
        
        if (!$stmt_alt->execute()) {
            if ($usar_lock) {
                $mysqli->rollback();
            }
            error_log("Error en obtenerProductoConVariante - execute alternativo falló: " . $stmt_alt->error);
            $stmt_alt->close();
            return null;
        }
        
        $result_alt = $stmt_alt->get_result();
        $producto_alt = $result_alt->fetch_assoc();
        $stmt_alt->close();
    
        // Normalizar stock antes de retornar (0 si es NULL o negativo)
        if ($producto_alt && !empty($producto_alt['id_variante'])) {
            $producto_alt['stock'] = isset($producto_alt['stock']) ? intval($producto_alt['stock']) : 0;
            if ($producto_alt['stock'] < 0) {
                $producto_alt['stock'] = 0;
            }
            
            // Si se usó lock, hacer commit
            if ($usar_lock) {
                $mysqli->commit();
            }
            
            return $producto_alt;
        }
        
        // Si se usó lock y no se encontró nada, hacer rollback
        if ($usar_lock) {
            $mysqli->rollback();
        }
        
        return null;
        
    } catch (Exception $e) {
        // Si hay excepción y se usó lock, hacer rollback
        if ($usar_lock) {
            $mysqli->rollback();
        }
        error_log("Error en obtenerProductoConVariante: " . $e->getMessage());
        return null;
    }
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
            $talles_config_path = __DIR__ . '/../talles_config.php';
            if (!file_exists($talles_config_path)) {
                error_log("ERROR: No se pudo encontrar talles_config.php en " . $talles_config_path);
                die("Error crítico: Archivo de configuración de talles no encontrado. Por favor, contacta al administrador.");
            }
            require_once $talles_config_path;
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
    // Filtros base que siempre se aplican (desde obtenerProductosFiltradosCatalogo):
    // - sv.stock > 0 (solo productos con stock disponible)
    // - sv.activo = 1 (solo variantes activas)
    // - p.activo = 1 (solo productos activos)
    // - c.activo = 1 (solo categorías activas - agregado aquí)
    $where_parts_ids = array_merge($where_parts, ["c.activo = 1"]);
    
    // Construir consulta SQL para obtener IDs únicos
    // DISTINCT asegura que no haya IDs duplicados
    // JOIN con Stock_Variantes para aplicar filtros de talle/color
    // JOIN con Categorias para filtrar por categoría activa
    // NOTA: Esta función es usada por obtenerProductosFiltradosCatalogo() que requiere stock > 0
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
 * Función auxiliar: Obtiene datos básicos de productos (nombre, precio, género, categoría)
 * 
 * Consulta simple que obtiene información básica de múltiples productos en un solo query.
 * Optimizada con IN clause para obtener múltiples productos a la vez.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $productos_ids Array de IDs de productos (enteros)
 * @return array Array asociativo [id_producto => datos_basicos] o array vacío si falla
 */
function _obtenerDatosBasicosProductos($mysqli, $productos_ids) {
    // Validar entrada
    if ($mysqli === null || !($mysqli instanceof mysqli)) {
        return [];
    }
    
    if (!is_array($productos_ids) || empty($productos_ids)) {
        return [];
    }
    
    // Validar y filtrar IDs
    $productos_ids_validos = [];
    foreach ($productos_ids as $id) {
        $id_validado = filter_var($id, FILTER_VALIDATE_INT);
        if ($id_validado !== false && $id_validado > 0) {
            $productos_ids_validos[] = $id_validado;
        }
    }
    
    if (empty($productos_ids_validos)) {
        return [];
    }
    
    // Crear placeholders para IN clause
    $placeholders = _crearPlaceholdersSQL(count($productos_ids_validos));
    if (empty($placeholders)) {
        return [];
    }
    
    // Consulta simple: datos básicos con JOIN a categorías
    $sql = "SELECT 
                p.id_producto,
                p.nombre_producto,
                p.descripcion_producto,
                p.precio_actual,
                p.genero,
                p.id_categoria,
                c.nombre_categoria
            FROM Productos p
            INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
            WHERE p.id_producto IN ($placeholders)
            AND p.activo = 1
            AND c.activo = 1
            ORDER BY p.nombre_producto";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR _obtenerDatosBasicosProductos - prepare falló: " . $mysqli->error);
        return [];
    }
    
    $types = str_repeat('i', count($productos_ids_validos));
    if (!$stmt->bind_param($types, ...$productos_ids_validos)) {
        error_log("ERROR _obtenerDatosBasicosProductos - bind_param falló: " . $stmt->error);
        $stmt->close();
        return [];
    }
    
    if (!$stmt->execute()) {
        error_log("ERROR _obtenerDatosBasicosProductos - execute falló: " . $stmt->error);
        $stmt->close();
        return [];
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        $stmt->close();
        return [];
    }
    
    // Convertir a array asociativo indexado por id_producto
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        if (isset($row['id_producto'])) {
            $productos[$row['id_producto']] = $row;
        }
    }
    
    $stmt->close();
    return $productos;
}

/**
 * Función auxiliar: Obtiene color representativo y stock total agregado por producto
 * 
 * Consulta que agrega información de color (primer color disponible) y stock total
 * de las variantes activas con stock. Puede filtrar por talles estándar si se requiere.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $productos_ids Array de IDs de productos (enteros)
 * @param bool $hay_filtro_talles Si true, solo considera talles estándar (S, M, L, XL)
 * @return array Array asociativo [id_producto => ['color' => string, 'total_stock' => int]] o array vacío
 */
function _obtenerColorStockProductos($mysqli, $productos_ids, $hay_filtro_talles = false) {
    // Validar entrada
    if ($mysqli === null || !($mysqli instanceof mysqli)) {
        return [];
    }
    
    if (!is_array($productos_ids) || empty($productos_ids)) {
        return [];
    }
    
    // Validar y filtrar IDs
    $productos_ids_validos = [];
    foreach ($productos_ids as $id) {
        $id_validado = filter_var($id, FILTER_VALIDATE_INT);
        if ($id_validado !== false && $id_validado > 0) {
            $productos_ids_validos[] = $id_validado;
        }
    }
    
    if (empty($productos_ids_validos)) {
        return [];
    }
    
    // Crear placeholders para IN clause
    $placeholders = _crearPlaceholdersSQL(count($productos_ids_validos));
    if (empty($placeholders)) {
        return [];
    }
    
    // Construir consulta según si hay filtro de talles
    if ($hay_filtro_talles) {
        // Obtener talles estándar
        if (!function_exists('obtenerTallesEstandar')) {
            $talles_config_path = __DIR__ . '/../talles_config.php';
            if (!file_exists($talles_config_path)) {
                error_log("ERROR: No se pudo encontrar talles_config.php en " . $talles_config_path);
                die("Error crítico: Archivo de configuración de talles no encontrado. Por favor, contacta al administrador.");
            }
            require_once $talles_config_path;
        }
        $talles_estandar = obtenerTallesEstandar();
        
        if (!is_array($talles_estandar) || empty($talles_estandar)) {
            return [];
        }
        
        $placeholders_talles = _crearPlaceholdersSQL(count($talles_estandar));
        if (empty($placeholders_talles)) {
            return [];
        }
        
        // Consulta con filtro de talles estándar
        $sql = "SELECT 
                    id_producto,
                    MIN(color) as color,
                    SUM(stock) as total_stock
                FROM Stock_Variantes
                WHERE id_producto IN ($placeholders)
                AND stock > 0
                AND activo = 1
                AND talle IN ($placeholders_talles)
                GROUP BY id_producto";
        
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            error_log("ERROR _obtenerColorStockProductos - prepare falló: " . $mysqli->error);
            return [];
        }
        
        $types = str_repeat('i', count($productos_ids_validos)) . str_repeat('s', count($talles_estandar));
        $params = array_merge($productos_ids_validos, $talles_estandar);
        
        if (!$stmt->bind_param($types, ...$params)) {
            error_log("ERROR _obtenerColorStockProductos - bind_param falló: " . $stmt->error);
            $stmt->close();
            return [];
        }
    } else {
        // Consulta sin filtro de talles (todos los talles)
        $sql = "SELECT 
                    id_producto,
                    MIN(color) as color,
                    SUM(stock) as total_stock
                FROM Stock_Variantes
                WHERE id_producto IN ($placeholders)
                AND stock > 0
                AND activo = 1
                GROUP BY id_producto";
        
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            error_log("ERROR _obtenerColorStockProductos - prepare falló: " . $mysqli->error);
            return [];
        }
        
        $types = str_repeat('i', count($productos_ids_validos));
        $params = $productos_ids_validos;
        
        if (!$stmt->bind_param($types, ...$params)) {
            error_log("ERROR _obtenerColorStockProductos - bind_param falló: " . $stmt->error);
            $stmt->close();
            return [];
        }
    }
    
    if (!$stmt->execute()) {
        error_log("ERROR _obtenerColorStockProductos - execute falló: " . $stmt->error);
        $stmt->close();
        return [];
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        $stmt->close();
        return [];
    }
    
    // Convertir a array asociativo indexado por id_producto
    $color_stock = [];
    while ($row = $result->fetch_assoc()) {
        if (isset($row['id_producto'])) {
            $color_stock[$row['id_producto']] = [
                'color' => $row['color'],
                'total_stock' => (int)$row['total_stock']
            ];
        }
    }
    
    $stmt->close();
    return $color_stock;
}

/**
 * Función auxiliar: Selecciona foto con prioridad por color
 * 
 * Aplica lógica de prioridad para seleccionar la mejor foto:
 * 1. foto1_prod del color específico del producto
 * 2. foto2_prod del color específico del producto
 * 3. foto3_prod (foto grupal, puede ser sin color o del mismo producto)
 * 4. foto_prod_miniatura (puede ser sin color o del mismo producto)
 * 
 * @param array $fotos_grupo Array de fotos disponibles con estructura: ['id_producto', 'foto1_prod', 'foto2_prod', 'foto3_prod', 'foto_prod_miniatura', 'color']
 * @param int $id_producto ID del producto para el cual se busca la foto
 * @param string|null $color_producto Color del producto (normalizado a lowercase)
 * @return string|null Ruta de la foto seleccionada o null si no se encuentra ninguna
 */
function _seleccionarFotoConPrioridadPorColor($fotos_grupo, $id_producto, $color_producto) {
    // Normalizar color del producto para comparación
    $color_normalizado = null;
    if (!empty($color_producto)) {
        $color_normalizado = strtolower(trim($color_producto));
    }
    
    // Prioridad 1: foto1_prod del color específico del producto
    // 1a: Del mismo producto y color
    if ($color_normalizado !== null) {
        foreach ($fotos_grupo as $foto) {
            $color_foto = !empty($foto['color']) ? strtolower(trim($foto['color'])) : '';
            if ($color_foto === $color_normalizado && $foto['id_producto'] == $id_producto && !empty($foto['foto1_prod'])) {
                return trim($foto['foto1_prod']);
            }
        }
        // 1b: De otro producto del mismo grupo con el mismo color
        foreach ($fotos_grupo as $foto) {
            $color_foto = !empty($foto['color']) ? strtolower(trim($foto['color'])) : '';
            if ($color_foto === $color_normalizado && !empty($foto['foto1_prod'])) {
                return trim($foto['foto1_prod']);
            }
        }
    }
    
    // Prioridad 2: foto2_prod del color específico del producto
    // 2a: Del mismo producto y color
    if ($color_normalizado !== null) {
        foreach ($fotos_grupo as $foto) {
            $color_foto = !empty($foto['color']) ? strtolower(trim($foto['color'])) : '';
            if ($color_foto === $color_normalizado && $foto['id_producto'] == $id_producto && !empty($foto['foto2_prod'])) {
                return trim($foto['foto2_prod']);
            }
        }
        // 2b: De otro producto del mismo grupo con el mismo color
        foreach ($fotos_grupo as $foto) {
            $color_foto = !empty($foto['color']) ? strtolower(trim($foto['color'])) : '';
            if ($color_foto === $color_normalizado && !empty($foto['foto2_prod'])) {
                return trim($foto['foto2_prod']);
            }
        }
    }
    
    // Prioridad 3: foto3_prod (foto grupal) - primero del mismo producto y color, luego sin color, luego cualquier foto3
    // 3a: foto3_prod del mismo producto y color
    if ($color_normalizado !== null) {
        foreach ($fotos_grupo as $foto) {
            $color_foto = !empty($foto['color']) ? strtolower(trim($foto['color'])) : '';
            if ($color_foto === $color_normalizado && $foto['id_producto'] == $id_producto && !empty($foto['foto3_prod'])) {
                return trim($foto['foto3_prod']);
            }
        }
    }
    
    // 3b: foto3_prod sin color del mismo producto
    foreach ($fotos_grupo as $foto) {
        if (empty($foto['color']) && $foto['id_producto'] == $id_producto && !empty($foto['foto3_prod'])) {
            return trim($foto['foto3_prod']);
        }
    }
    
    // 3c: foto3_prod de cualquier producto del grupo
    foreach ($fotos_grupo as $foto) {
        if (!empty($foto['foto3_prod'])) {
            return trim($foto['foto3_prod']);
        }
    }
    
    // Prioridad 4: foto_prod_miniatura - primero del mismo producto y color, luego sin color, luego cualquier miniatura
    // 4a: foto_prod_miniatura del mismo producto y color
    if ($color_normalizado !== null) {
        foreach ($fotos_grupo as $foto) {
            $color_foto = !empty($foto['color']) ? strtolower(trim($foto['color'])) : '';
            if ($color_foto === $color_normalizado && $foto['id_producto'] == $id_producto && !empty($foto['foto_prod_miniatura'])) {
                return trim($foto['foto_prod_miniatura']);
            }
        }
    }
    
    // 4b: foto_prod_miniatura sin color del mismo producto
    foreach ($fotos_grupo as $foto) {
        if (empty($foto['color']) && $foto['id_producto'] == $id_producto && !empty($foto['foto_prod_miniatura'])) {
            return trim($foto['foto_prod_miniatura']);
        }
    }
    
    // 4c: foto_prod_miniatura de cualquier producto del grupo
    foreach ($fotos_grupo as $foto) {
        if (!empty($foto['foto_prod_miniatura'])) {
            return trim($foto['foto_prod_miniatura']);
        }
    }
    
    // No se encontró ninguna foto válida
    return null;
}

/**
 * Función auxiliar: Obtiene fotos miniatura para productos con lógica de priorización en PHP
 * 
 * Esta función obtiene todas las fotos relevantes de los productos y sus grupos (mismo nombre/categoría/género),
 * luego aplica lógica de priorización en PHP para seleccionar la mejor foto miniatura.
 * 
 * Nueva prioridad de selección:
 * 1. foto1_prod del color específico del producto
 * 2. foto2_prod del color específico del producto
 * 3. foto3_prod (foto grupal)
 * 4. foto_prod_miniatura
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $productos_data Array asociativo [id_producto => datos_producto] con al menos nombre_producto, id_categoria, genero
 * @param array $color_stock Array asociativo [id_producto => ['color' => string, 'total_stock' => int]]
 * @return array Array asociativo [id_producto => ruta_foto] o array vacío
 */
function _obtenerFotosMiniaturaProductos($mysqli, $productos_data, $color_stock) {
    // Validar entrada
    if ($mysqli === null || !($mysqli instanceof mysqli)) {
        return [];
    }
    
    if (!is_array($productos_data) || empty($productos_data)) {
        return [];
    }
    
    // Obtener todos los IDs de productos únicos
    $productos_ids = array_keys($productos_data);
    if (empty($productos_ids)) {
        return [];
    }
    
    // Crear placeholders para IN clause
    $placeholders = _crearPlaceholdersSQL(count($productos_ids));
    if (empty($placeholders)) {
        return [];
    }
    
    // Consulta simple: obtener todas las fotos de los productos y sus grupos
    // Buscamos fotos de productos con el mismo nombre, categoría y género
    // Obtener todas las fotos: foto1_prod, foto2_prod, foto3_prod, foto_prod_miniatura
    // Usamos EXISTS para mayor compatibilidad con versiones antiguas de MySQL
    $sql = "SELECT 
                fp.id_producto,
                fp.foto_prod_miniatura,
                fp.foto1_prod,
                fp.foto2_prod,
                fp.foto3_prod,
                fp.color,
                p.nombre_producto,
                p.id_categoria,
                p.genero
            FROM Fotos_Producto fp
            INNER JOIN Productos p ON fp.id_producto = p.id_producto
            INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
            WHERE fp.activo = 1
            AND p.activo = 1
            AND c.activo = 1
            AND (
                fp.foto_prod_miniatura IS NOT NULL AND fp.foto_prod_miniatura != ''
                OR fp.foto1_prod IS NOT NULL AND fp.foto1_prod != ''
                OR fp.foto2_prod IS NOT NULL AND fp.foto2_prod != ''
                OR fp.foto3_prod IS NOT NULL AND fp.foto3_prod != ''
            )
            AND (
                fp.id_producto IN ($placeholders)
                OR EXISTS (
                    SELECT 1
                    FROM Productos p2
                    WHERE p2.id_producto IN ($placeholders)
                    AND p2.activo = 1
                    AND p2.nombre_producto = p.nombre_producto
                    AND p2.id_categoria = p.id_categoria
                    AND p2.genero = p.genero
                )
            )
            ORDER BY fp.id_producto, fp.color IS NULL DESC, fp.color ASC";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR _obtenerFotosMiniaturaProductos - prepare falló: " . $mysqli->error);
        return [];
    }
    
    // Los parámetros se usan dos veces (una para cada IN clause)
    $types = str_repeat('i', count($productos_ids)) . str_repeat('i', count($productos_ids));
    $params = array_merge($productos_ids, $productos_ids);
    
    if (!$stmt->bind_param($types, ...$params)) {
        error_log("ERROR _obtenerFotosMiniaturaProductos - bind_param falló: " . $stmt->error);
        $stmt->close();
        return [];
    }
    
    if (!$stmt->execute()) {
        error_log("ERROR _obtenerFotosMiniaturaProductos - execute falló: " . $stmt->error);
        $stmt->close();
        return [];
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        $stmt->close();
        return [];
    }
    
    // Organizar fotos por grupo de productos (mismo nombre/categoría/género)
    // Incluir todas las fotos disponibles: foto1_prod, foto2_prod, foto3_prod, foto_prod_miniatura
    $fotos_por_grupo = [];
    while ($row = $result->fetch_assoc()) {
        $grupo_key = $row['nombre_producto'] . '|' . $row['id_categoria'] . '|' . $row['genero'];
        
        if (!isset($fotos_por_grupo[$grupo_key])) {
            $fotos_por_grupo[$grupo_key] = [];
        }
        
        $fotos_por_grupo[$grupo_key][] = [
            'id_producto' => $row['id_producto'],
            'foto_prod_miniatura' => !empty($row['foto_prod_miniatura']) ? trim($row['foto_prod_miniatura']) : null,
            'foto1_prod' => !empty($row['foto1_prod']) ? trim($row['foto1_prod']) : null,
            'foto2_prod' => !empty($row['foto2_prod']) ? trim($row['foto2_prod']) : null,
            'foto3_prod' => !empty($row['foto3_prod']) ? trim($row['foto3_prod']) : null,
            'color' => $row['color']
        ];
    }
    
    $stmt->close();
    
    // Aplicar lógica de priorización en PHP para cada producto usando nueva función auxiliar
    $fotos_seleccionadas = [];
    
    foreach ($productos_data as $id_producto => $producto) {
        // Validar que el producto tenga las claves necesarias antes de acceder
        if (!isset($producto['nombre_producto']) || !isset($producto['id_categoria']) || !isset($producto['genero'])) {
            // Si faltan datos esenciales, saltar este producto
            error_log("ADVERTENCIA _obtenerFotosMiniaturaProductos: Producto ID {$id_producto} no tiene todas las claves necesarias (nombre_producto, id_categoria, genero)");
            continue;
        }
        
        $grupo_key = $producto['nombre_producto'] . '|' . $producto['id_categoria'] . '|' . $producto['genero'];
        $color_representativo = isset($color_stock[$id_producto]) ? $color_stock[$id_producto]['color'] : null;
        
        if (!isset($fotos_por_grupo[$grupo_key])) {
            continue; // No hay fotos para este grupo
        }
        
        $fotos_grupo = $fotos_por_grupo[$grupo_key];
        
        // Usar función auxiliar para seleccionar foto con prioridad por color
        $foto_seleccionada = _seleccionarFotoConPrioridadPorColor($fotos_grupo, $id_producto, $color_representativo);
        
        if ($foto_seleccionada !== null) {
            $fotos_seleccionadas[$id_producto] = $foto_seleccionada;
        }
    }
    
    return $fotos_seleccionadas;
}

/**
 * Función auxiliar: Combina datos de productos desde múltiples fuentes
 * 
 * Combina datos básicos, color/stock y fotos en un solo array de productos
 * con la misma estructura que retornaba la consulta compleja original.
 * 
 * @param array $datos_basicos Array asociativo [id_producto => datos_basicos]
 * @param array $color_stock Array asociativo [id_producto => ['color' => string, 'total_stock' => int]]
 * @param array $fotos Array asociativo [id_producto => foto_prod_miniatura]
 * @return array Array de productos con estructura completa (compatible con consulta original)
 */
function _combinarDatosProductos($datos_basicos, $color_stock, $fotos) {
    $productos_completos = [];
    
    // Iterar sobre datos básicos (estos son los productos que existen)
    foreach ($datos_basicos as $id_producto => $producto) {
        $producto_completo = $producto; // Copiar datos básicos
        
        // Agregar color y stock si existen
        if (isset($color_stock[$id_producto])) {
            $producto_completo['color'] = $color_stock[$id_producto]['color'];
            $producto_completo['total_stock'] = $color_stock[$id_producto]['total_stock'];
        } else {
            $producto_completo['color'] = null;
            $producto_completo['total_stock'] = 0;
        }
        
        // Agregar foto miniatura si existe
        if (isset($fotos[$id_producto])) {
            $producto_completo['foto_prod_miniatura'] = $fotos[$id_producto];
        } else {
            $producto_completo['foto_prod_miniatura'] = null;
        }
        
        $productos_completos[] = $producto_completo;
    }
    
    return $productos_completos;
}

/**
 * Función auxiliar: Construye la consulta SQL final para obtener datos completos de productos
 * 
 * REFACTORIZADA: Esta función ahora retorna null y delega a consultas separadas.
 * Se mantiene por compatibilidad pero ya no construye la consulta compleja.
 * 
 * @deprecated Esta función ya no construye consultas. Usar _obtenerDatosCompletosProductos() directamente.
 * @param array $productos_ids Array de IDs de productos (enteros)
 * @param bool $hay_filtro_talles Si true, los JOINs filtran por talles estándar
 * @return null Siempre retorna null (función deprecada)
 */
function _construirQueryDatosCompletos($productos_ids, $hay_filtro_talles) {
    // Esta función está deprecada. Ya no construye consultas complejas.
    // Se mantiene por compatibilidad pero siempre retorna null.
    // La funcionalidad se ha movido a consultas separadas más simples.
    return null;
}

/**
 * Función auxiliar: Obtiene datos completos de productos usando consultas separadas
 * 
 * REFACTORIZADA: Esta función ahora usa múltiples consultas simples en lugar de una consulta compleja.
 * Ejecuta 3 consultas separadas y combina los resultados en PHP:
 * 1. Datos básicos de productos
 * 2. Color y stock agregado
 * 3. Fotos miniatura con lógica de priorización
 * 
 * Esta estrategia es más fácil de mantener y depurar que la consulta compleja anterior.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $productos_ids Array de IDs de productos (enteros)
 * @param bool $hay_filtro_talles Si true, solo considera talles estándar para color/stock
 * @return array Array de productos (arrays asociativos) con estructura completa o array vacío si falla
 */
function _obtenerDatosCompletosProductos($mysqli, $productos_ids, $hay_filtro_talles = false) {
    // Validación de entrada
    if ($mysqli === null || !($mysqli instanceof mysqli)) {
        return [];
    }
    
    if (!is_array($productos_ids) || empty($productos_ids)) {
        return [];
    }
    
    // Validar y filtrar IDs
    $productos_ids_validos = [];
    foreach ($productos_ids as $id) {
        $id_validado = filter_var($id, FILTER_VALIDATE_INT);
        if ($id_validado !== false && $id_validado > 0) {
            $productos_ids_validos[] = $id_validado;
        }
    }
    
    if (empty($productos_ids_validos)) {
        return [];
    }
    
    // ESTRATEGIA: Consultas separadas por lote
    // 1. Obtener datos básicos de productos
    $datos_basicos = _obtenerDatosBasicosProductos($mysqli, $productos_ids_validos);
    
    if (empty($datos_basicos)) {
        return []; // Si no hay datos básicos, no hay productos
    }
    
    // 2. Obtener color y stock agregado
    $color_stock = _obtenerColorStockProductos($mysqli, $productos_ids_validos, $hay_filtro_talles);
    
    // 3. Obtener fotos miniatura con lógica de priorización
    $fotos = _obtenerFotosMiniaturaProductos($mysqli, $datos_basicos, $color_stock);
    
    // 4. Combinar todos los datos
    $productos_completos = _combinarDatosProductos($datos_basicos, $color_stock, $fotos);
    
    return $productos_completos;
}

/**
 * Obtiene productos filtrados para el catálogo público (con filtros de talle, color, categoría)
 * 
 * IMPORTANTE: Esta función está diseñada para el CATÁLOGO PÚBLICO y aplica filtros estrictos:
 * - Solo muestra productos con STOCK DISPONIBLE (sv.stock > 0)
 * - Solo muestra variantes ACTIVAS (sv.activo = 1)
 * - Solo muestra productos ACTIVOS (p.activo = 1)
 * - Solo muestra categorías ACTIVAS (c.activo = 1)
 * 
 * DIFERENCIA CON MARKETING: A diferencia de obtenerProductosMarketing(), esta función
 * NO muestra productos sin stock, ya que está diseñada para clientes que solo pueden
 * comprar productos disponibles.
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
    // REFACTORIZADO: Esta función usa estrategia de múltiples consultas simples:
    // 1. Primero obtiene IDs de productos que cumplen filtros
    // 2. Luego ejecuta 3 consultas simples separadas (datos básicos, color/stock, fotos)
    // 3. Combina resultados en PHP
    // Esta estrategia es más fácil de mantener y depurar que la consulta compleja anterior.
    
    // ===================================================================
    // SECCIÓN 1: INICIALIZACIÓN Y VALIDACIÓN DE DEPENDENCIAS
    // ===================================================================
    // Verificar que la función obtenerTallesEstandar() esté disponible
    // (debería estar incluida desde talles_config.php, pero incluimos por si acaso)
    if (!function_exists('obtenerTallesEstandar')) {
        $talles_config_path = __DIR__ . '/../talles_config.php';
        if (!file_exists($talles_config_path)) {
            error_log("ERROR: No se pudo encontrar talles_config.php en " . $talles_config_path);
            die("Error crítico: Archivo de configuración de talles no encontrado. Por favor, contacta al administrador.");
        }
        require_once $talles_config_path;
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
    // REFACTORIZADO: Ahora usa consultas separadas simples en lugar de una consulta compleja
    if (!empty($productos_ids)) {
        // Determinar si hay filtro de talles para decidir qué subconsultas usar
        // Esto afecta cómo se calcula el color y stock: si hay filtro, solo usar talles estándar
        $hay_filtro_talles = !empty($filtros['talles']) && is_array($filtros['talles']);
        
        // Usar función auxiliar que ejecuta consultas separadas y combina resultados
        // Esta función ahora usa múltiples consultas simples en lugar de una consulta compleja
        $productos = _obtenerDatosCompletosProductos($mysqli, $productos_ids, $hay_filtro_talles);
        
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
        $talles_config_path = __DIR__ . '/../talles_config.php';
        if (!file_exists($talles_config_path)) {
            error_log("ERROR: No se pudo encontrar talles_config.php en " . $talles_config_path);
            die("Error crítico: Archivo de configuración de talles no encontrado. Por favor, contacta al administrador.");
        }
        require_once $talles_config_path;
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
        SELECT IFNULL(SUM(stock), 0) as stock_total
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
 * IMPORTANTE: Esta función está diseñada para el PANEL DE MARKETING y aplica filtros diferentes
 * al catálogo público:
 * - Por defecto muestra solo productos ACTIVOS (p.activo = 1), INCLUSO SIN STOCK
 * - Con $incluir_inactivos = true, muestra TODOS los productos (activos e inactivos)
 * - Solo muestra variantes ACTIVAS (sv.activo = 1) en queries secundarias
 * - Solo muestra categorías ACTIVAS (c.activo = 1)
 * - NO filtra por stock disponible (permite ver productos sin stock para gestión)
 * 
 * DIFERENCIA CON CATÁLOGO: A diferencia de obtenerProductosFiltradosCatalogo(), esta función
 * muestra productos sin stock porque el panel de marketing necesita gestionar TODOS los productos
 * activos, independientemente de su disponibilidad de stock.
 * 
 * ESTRATEGIA DE MÚLTIPLES QUERIES:
 * 1. Query 1: Obtener productos básicos (sin filtro de stock)
 * 2. Query 2-5: Obtener variantes, stock, colores y talles por lote
 * 3. Combinar resultados en PHP
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $limite Cantidad de productos a retornar (0 = sin límite)
 * @param bool $incluir_inactivos Si es true, incluye productos inactivos (activo = 0). Por defecto false (solo activos)
 * @return array Array de productos con información de variantes y stock (incluye productos sin stock)
 */
function obtenerProductosMarketing($mysqli, $limite = 0, $incluir_inactivos = false) {
    // ESTRATEGIA DE MÚLTIPLES QUERIES SIMPLES:
    // Query 1: Obtener productos básicos con categoría
    // NOTA: Esta query NO filtra por stock - muestra todos los productos activos (incluso sin stock)
    // Filtros aplicados:
    // - p.activo = 1 (solo productos activos) - O p.activo IN (0,1) si $incluir_inactivos = true
    // - LEFT JOIN con Categorias (incluye productos incluso si su categoría está inactiva o no existe)
    // - NO filtra sv.stock > 0 (permite productos sin stock para gestión)
    // Query simple (menos de 10 líneas, sin COALESCE según rules.md)
    // Usar LEFT JOIN para incluir productos incluso si su categoría está inactiva
    $sql_productos = "
        SELECT p.id_producto, p.nombre_producto, p.descripcion_producto, 
               p.precio_actual, p.genero, c.nombre_categoria, c.id_categoria, p.activo
        FROM Productos p
        LEFT JOIN Categorias c ON c.id_categoria = p.id_categoria
        WHERE " . ($incluir_inactivos ? "p.activo IN (0, 1)" : "p.activo = 1") . "
        ORDER BY p.id_producto DESC
    ";
    
    // Agregar límite si es necesario
    if ($limite > 0) {
        $sql_productos .= " LIMIT ?";
        $stmt_productos = $mysqli->prepare($sql_productos);
        if (!$stmt_productos) {
            return [];
        }
        $stmt_productos->bind_param('i', $limite);
    } else {
        $stmt_productos = $mysqli->prepare($sql_productos);
        if (!$stmt_productos) {
            return [];
        }
    }
    
    $stmt_productos->execute();
    $result_productos = $stmt_productos->get_result();
    
    $productos = [];
    $productos_ids = [];
    while ($row = $result_productos->fetch_assoc()) {
        // Manejar valores NULL de categoría en PHP (no usar COALESCE según rules.md)
        if (empty($row['nombre_categoria'])) {
            $row['nombre_categoria'] = 'Sin categoría';
        }
        if (empty($row['id_categoria'])) {
            $row['id_categoria'] = 0;
        }
        $productos[$row['id_producto']] = $row;
        $productos_ids[] = $row['id_producto'];
    }
    $stmt_productos->close();
    
    if (empty($productos_ids)) {
        return [];
    }
    
    // Query 2: Calcular total_variantes por producto (por lote)
    // Filtros aplicados: sv.activo = 1 (solo variantes activas)
    // NOTA: NO filtra por stock - incluye variantes sin stock
    $placeholders = str_repeat('?,', count($productos_ids) - 1) . '?';
    $sql_variantes = "
        SELECT id_producto, COUNT(DISTINCT id_variante) as total_variantes
        FROM Stock_Variantes
        WHERE id_producto IN ($placeholders)
        AND activo = 1
        GROUP BY id_producto
    ";
    
    $stmt_variantes = $mysqli->prepare($sql_variantes);
    if ($stmt_variantes) {
        $types = str_repeat('i', count($productos_ids));
        $stmt_variantes->bind_param($types, ...$productos_ids);
        $stmt_variantes->execute();
        $result_variantes = $stmt_variantes->get_result();
        
        $variantes_data = [];
        while ($row = $result_variantes->fetch_assoc()) {
            $variantes_data[$row['id_producto']] = intval($row['total_variantes']);
        }
        $stmt_variantes->close();
    } else {
        $variantes_data = [];
    }
    
    // Query 3: Calcular stock_total por producto (por lote)
    // Filtros aplicados: sv.activo = 1 (solo variantes activas)
    // NOTA: NO filtra por stock > 0 - suma todo el stock (puede ser 0)
    $sql_stock = "
        SELECT id_producto, SUM(stock) as stock_total
        FROM Stock_Variantes
        WHERE id_producto IN ($placeholders)
        AND activo = 1
        GROUP BY id_producto
    ";
    
    $stmt_stock = $mysqli->prepare($sql_stock);
    if ($stmt_stock) {
        $types = str_repeat('i', count($productos_ids));
        $stmt_stock->bind_param($types, ...$productos_ids);
        $stmt_stock->execute();
        $result_stock = $stmt_stock->get_result();
        
        $stock_data = [];
        while ($row = $result_stock->fetch_assoc()) {
            $stock_data[$row['id_producto']] = intval($row['stock_total'] ?? 0);
        }
        $stmt_stock->close();
    } else {
        $stock_data = [];
    }
    
    // Query 4: Obtener colores únicos por producto (por lote)
    // Filtros aplicados: sv.activo = 1 (solo variantes activas)
    // NOTA: NO filtra por stock - incluye colores de variantes sin stock
    $sql_colores = "
        SELECT DISTINCT id_producto, color
        FROM Stock_Variantes
        WHERE id_producto IN ($placeholders)
        AND activo = 1
        AND color IS NOT NULL
        AND color != ''
        ORDER BY id_producto, color
    ";
    
    $stmt_colores = $mysqli->prepare($sql_colores);
    if ($stmt_colores) {
        $types = str_repeat('i', count($productos_ids));
        $stmt_colores->bind_param($types, ...$productos_ids);
        $stmt_colores->execute();
        $result_colores = $stmt_colores->get_result();
        
        $colores_data = [];
        while ($row = $result_colores->fetch_assoc()) {
            if (!isset($colores_data[$row['id_producto']])) {
                $colores_data[$row['id_producto']] = [];
            }
            if (!in_array($row['color'], $colores_data[$row['id_producto']])) {
                $colores_data[$row['id_producto']][] = $row['color'];
            }
        }
        $stmt_colores->close();
    } else {
        $colores_data = [];
    }
    
    // Query 5: Obtener talles únicos por producto (por lote)
    // Filtros aplicados: sv.activo = 1 (solo variantes activas)
    // NOTA: NO filtra por stock - incluye talles de variantes sin stock
    $sql_talles = "
        SELECT DISTINCT id_producto, talle
        FROM Stock_Variantes
        WHERE id_producto IN ($placeholders)
        AND activo = 1
        AND talle IS NOT NULL
        AND talle != ''
        ORDER BY id_producto, talle
    ";
    
    $stmt_talles = $mysqli->prepare($sql_talles);
    if ($stmt_talles) {
        $types = str_repeat('i', count($productos_ids));
        $stmt_talles->bind_param($types, ...$productos_ids);
        $stmt_talles->execute();
        $result_talles = $stmt_talles->get_result();
        
        $talles_data = [];
        while ($row = $result_talles->fetch_assoc()) {
            if (!isset($talles_data[$row['id_producto']])) {
                $talles_data[$row['id_producto']] = [];
            }
            if (!in_array($row['talle'], $talles_data[$row['id_producto']])) {
                $talles_data[$row['id_producto']][] = $row['talle'];
            }
        }
        $stmt_talles->close();
    } else {
        $talles_data = [];
    }
    
    // Combinar resultados en PHP
    $productos_finales = [];
    foreach ($productos as $id_prod => $producto) {
        $producto['total_variantes'] = $variantes_data[$id_prod] ?? 0;
        $producto['stock_total'] = $stock_data[$id_prod] ?? 0;
        
        // Convertir colores a string y array
        $colores = $colores_data[$id_prod] ?? [];
        sort($colores);
        $producto['colores'] = implode(',', $colores);
        $producto['colores_array'] = $colores;
        
        // Convertir talles a string y array
        $talles = $talles_data[$id_prod] ?? [];
        sort($talles);
        $producto['talles'] = implode(',', $talles);
        $producto['talles_array'] = $talles;
        
        // Asegurar que el campo 'activo' esté presente (ya viene en la query principal)
        $producto['activo'] = isset($producto['activo']) ? intval($producto['activo']) : 1;
        
        $productos_finales[] = $producto;
    }
    
    return $productos_finales;
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
 * ⚠️ IMPORTANTE: Esta función SOLO inserta en la tabla Productos.
 * NO crea variantes en Stock_Variantes. NO crea fotos en Fotos_Producto.
 * Las variantes y fotos se agregan posteriormente desde otras funciones.
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
    
    // ⚠️ IMPORTANTE: Insertar SOLO en tabla Productos
    // NO insertar en Stock_Variantes ni en Fotos_Producto
    // Las variantes y fotos se agregan posteriormente desde otras funciones
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
        error_log("ERROR actualizarProducto - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('ssdisi', $nombre_producto, $descripcion_producto, $precio_actual, $id_categoria, $genero, $id_producto);
    if (!$stmt->execute()) {
        error_log("ERROR actualizarProducto - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $stmt->close();
    return true;
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

/**
 * Obtiene todos los nombres únicos de productos activos
 * Útil para mostrar en SELECT con opción de agregar nuevo
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array de nombres de productos únicos ordenados alfabéticamente
 */
function obtenerNombresProductosUnicos($mysqli) {
    $sql = "SELECT DISTINCT nombre_producto FROM Productos WHERE activo = 1 ORDER BY nombre_producto";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $nombres = [];
    while ($row = $result->fetch_assoc()) {
        $nombres[] = $row['nombre_producto'];
    }
    
    $stmt->close();
    return $nombres;
}

/**
 * Obtiene el ID de un producto por su nombre (case-insensitive)
 * Útil para verificar si un nombre de producto ya existe antes de crear o actualizar
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $nombre_producto Nombre del producto
 * @param int|null $id_producto_excluir ID del producto a excluir de la búsqueda (útil para actualizaciones)
 * @return int|null ID del producto o null si no existe
 */
function obtenerProductoIdPorNombre($mysqli, $nombre_producto, $id_producto_excluir = null) {
    // Usar LOWER() para comparación case-insensitive
    if ($id_producto_excluir !== null) {
        $sql = "SELECT id_producto FROM Productos WHERE LOWER(TRIM(nombre_producto)) = LOWER(TRIM(?)) AND activo = 1 AND id_producto != ? LIMIT 1";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            error_log("ERROR obtenerProductoIdPorNombre - prepare falló: " . $mysqli->error . " para producto: " . $nombre_producto);
            return null;
        }
        $stmt->bind_param('si', $nombre_producto, $id_producto_excluir);
    } else {
        $sql = "SELECT id_producto FROM Productos WHERE LOWER(TRIM(nombre_producto)) = LOWER(TRIM(?)) AND activo = 1 LIMIT 1";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            error_log("ERROR obtenerProductoIdPorNombre - prepare falló: " . $mysqli->error . " para producto: " . $nombre_producto);
            return null;
        }
        $stmt->bind_param('s', $nombre_producto);
    }
    
    if (!$stmt->execute()) {
        error_log("ERROR obtenerProductoIdPorNombre - execute falló: " . $stmt->error . " para producto: " . $nombre_producto);
        $stmt->close();
        return null;
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        return (int)$row['id_producto'];
    } else {
        return null;
    }
}

/**
 * Obtiene TODAS las variantes de productos con el mismo nombre_producto, categoría y género
 * Esto permite mostrar todos los talles y colores disponibles del grupo de productos
 * Solo incluye talles estándar (S, M, L, XL) para mantener consistencia con el catálogo
 * Normaliza colores para formato consistente (primera letra mayúscula, resto minúscula)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $nombre_producto Nombre del producto
 * @param int $id_categoria ID de la categoría
 * @param string $genero Género del producto
 * @param array $talles_estandar Array de talles estándar (ej: ['S', 'M', 'L', 'XL'])
 * @return array Array de variantes con colores normalizados, o array vacío si no hay variantes
 */
function obtenerTodasVariantesGrupoProducto($mysqli, $nombre_producto, $id_categoria, $genero, $talles_estandar) {
    // Validar parámetros
    if (empty($talles_estandar) || !is_array($talles_estandar)) {
        return [];
    }
    
    // Crear placeholders para talles estándar
    $placeholders_talles_estandar = str_repeat('?,', count($talles_estandar) - 1) . '?';
    
    $sql = "
        SELECT sv.id_variante, sv.id_producto, sv.talle, sv.color, sv.stock
        FROM Stock_Variantes sv
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
        WHERE p.nombre_producto = ?
        AND p.id_categoria = ?
        AND p.genero = ?
        AND sv.activo = 1
        AND p.activo = 1
        AND c.activo = 1
        AND sv.talle IN ($placeholders_talles_estandar)
        ORDER BY sv.color, sv.talle
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    // Parámetros: nombre_producto, id_categoria, genero, talles estándar
    $params = array_merge(
        [$nombre_producto, $id_categoria, $genero],
        $talles_estandar
    );
    $types = 'sis' . str_repeat('s', count($talles_estandar));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $todas_variantes_completas = [];
    while ($row = $result->fetch_assoc()) {
        $todas_variantes_completas[] = $row;
    }
    $stmt->close();
    
    // Normalizar colores de todas las variantes para consistencia
    foreach ($todas_variantes_completas as &$variante) {
        if (!empty($variante['color'])) {
            $variante['color'] = ucfirst(strtolower(trim($variante['color'])));
        }
    }
    unset($variante); // Liberar referencia
    
    return $todas_variantes_completas;
}

/**
 * Obtiene fotos de TODOS los productos con el mismo nombre_producto, categoría y género
 * Esto permite mostrar las imágenes de todos los colores disponibles del grupo de productos
 * Solo incluye fotos de productos con categorías activas
 * 
 * La función procesa y combina fotos generales y por color, dando prioridad a las fotos del producto actual (id_producto)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $nombre_producto Nombre del producto
 * @param int $id_categoria ID de la categoría
 * @param string $genero Género del producto
 * @param int $id_producto ID del producto actual (para priorizar sus fotos)
 * @param array $todas_fotos Array con fotos del producto actual: ['generales' => [...], 'por_color' => [...]]
 * @param array $variantes Array de variantes para verificar que las fotos pertenezcan al producto correcto del color
 * @return array Array con estructura: ['generales' => [...], 'por_color' => [color => [...]]]
 */
function obtenerFotosGrupoProducto($mysqli, $nombre_producto, $id_categoria, $genero, $id_producto, $todas_fotos, $variantes) {
    $sql = "
        SELECT fp.id_foto, fp.id_producto, fp.foto_prod_miniatura, fp.foto1_prod, fp.foto2_prod, fp.foto3_prod, fp.color
        FROM Fotos_Producto fp
        INNER JOIN Productos p ON fp.id_producto = p.id_producto
        INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
        WHERE p.nombre_producto = ?
        AND p.id_categoria = ?
        AND p.genero = ?
        AND fp.activo = 1
        AND p.activo = 1
        AND c.activo = 1
        ORDER BY fp.color IS NULL DESC, fp.color ASC
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [
            'generales' => $todas_fotos['generales'] ?? null,
            'por_color' => $todas_fotos['por_color'] ?? []
        ];
    }
    
    $stmt->bind_param('sis', $nombre_producto, $id_categoria, $genero);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Combinar fotos de todos los productos con el mismo nombre, categoría y género
    $fotos_generales_combinadas = $todas_fotos['generales'] ?? null;
    $fotos_por_color_combinadas = $todas_fotos['por_color'] ?? [];
    
    while ($row_foto = $result->fetch_assoc()) {
        if ($row_foto['color'] === null || $row_foto['color'] === '') {
            // Fotos generales - priorizar las del producto actual (id_producto)
            if ($row_foto['id_producto'] == $id_producto) {
                // Si es del producto actual, usar estas fotos (tienen prioridad)
                // Solo actualizar campos que tienen valor (no NULL ni vacío)
                if (!$fotos_generales_combinadas) {
                    $fotos_generales_combinadas = [
                        'id_foto' => $row_foto['id_foto'],
                        'foto_prod_miniatura' => !empty($row_foto['foto_prod_miniatura']) ? $row_foto['foto_prod_miniatura'] : null,
                        'foto3_prod' => !empty($row_foto['foto3_prod']) ? $row_foto['foto3_prod'] : null
                    ];
                } else {
                    // Actualizar solo los campos que tienen valor
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
                // Si no hay fotos del producto actual y aún no hay fotos generales, usar estas
                // Solo si tienen valores válidos
                if (!empty($row_foto['foto_prod_miniatura']) || !empty($row_foto['foto3_prod'])) {
                    $fotos_generales_combinadas = [
                        'id_foto' => $row_foto['id_foto'],
                        'foto_prod_miniatura' => !empty($row_foto['foto_prod_miniatura']) ? $row_foto['foto_prod_miniatura'] : null,
                        'foto3_prod' => !empty($row_foto['foto3_prod']) ? $row_foto['foto3_prod'] : null
                    ];
                }
            } else {
                // Ya hay fotos generales, solo actualizar si el nuevo valor es válido y no existe el anterior
                if (empty($fotos_generales_combinadas['foto_prod_miniatura']) && !empty($row_foto['foto_prod_miniatura'])) {
                    $fotos_generales_combinadas['foto_prod_miniatura'] = $row_foto['foto_prod_miniatura'];
                }
                if (empty($fotos_generales_combinadas['foto3_prod']) && !empty($row_foto['foto3_prod'])) {
                    $fotos_generales_combinadas['foto3_prod'] = $row_foto['foto3_prod'];
                }
            }
        } else {
            // Fotos por color - agregar todas las fotos de este color
            // Normalizar color para que coincida con el formato de las variantes (primera letra mayúscula, resto minúscula)
            $color_normalizado = trim($row_foto['color']);
            // Normalizar: primera letra mayúscula, resto minúscula (formato estándar)
            $color_normalizado = ucfirst(strtolower($color_normalizado));
            
            // Verificar que este id_producto realmente tiene este color en sus variantes
            // Esto asegura que las fotos pertenezcan al producto correcto del color
            $producto_tiene_color = false;
            foreach ($variantes as $variante) {
                $color_variante = ucfirst(strtolower(trim($variante['color'])));
                if ($color_variante === $color_normalizado && $variante['id_producto'] == $row_foto['id_producto']) {
                    $producto_tiene_color = true;
                    break;
                }
            }
            
            // Inicializar array si no existe
            if (!isset($fotos_por_color_combinadas[$color_normalizado])) {
                $fotos_por_color_combinadas[$color_normalizado] = [];
            }
            
            // Solo agregar fotos si el producto tiene este color (prioridad)
            // O si no hay fotos aún para este color (usar cualquier foto disponible como fallback)
            if ($producto_tiene_color || empty($fotos_por_color_combinadas[$color_normalizado])) {
                // Verificar si ya hay fotos de este id_producto para este color
                $foto_existe = false;
                $indice_existente = -1;
                
                foreach ($fotos_por_color_combinadas[$color_normalizado] as $index => $foto_exist) {
                    // Verificar que id_producto existe antes de comparar (compatibilidad con estructuras antiguas)
                    if (isset($foto_exist['id_producto']) && $foto_exist['id_producto'] == $row_foto['id_producto']) {
                        $foto_existe = true;
                        $indice_existente = $index;
                        break;
                    }
                }
                
                // Solo agregar si tiene al menos una foto válida
                if (!empty($row_foto['foto1_prod']) || !empty($row_foto['foto2_prod'])) {
                    $foto_item = [
                        'id_foto' => $row_foto['id_foto'],
                        'id_producto' => $row_foto['id_producto'],
                        'foto1_prod' => !empty($row_foto['foto1_prod']) ? $row_foto['foto1_prod'] : null,
                        'foto2_prod' => !empty($row_foto['foto2_prod']) ? $row_foto['foto2_prod'] : null
                    ];
                    
                    if ($foto_existe) {
                        // Actualizar fotos existentes del mismo producto
                        $fotos_por_color_combinadas[$color_normalizado][$indice_existente] = $foto_item;
                    } else {
                        // Agregar nuevas fotos
                        // Si el producto tiene este color, agregar al inicio (prioridad)
                        if ($producto_tiene_color) {
                            array_unshift($fotos_por_color_combinadas[$color_normalizado], $foto_item);
                        } else {
                            // Solo agregar si no hay fotos aún para este color (fallback)
                            if (empty($fotos_por_color_combinadas[$color_normalizado])) {
                                $fotos_por_color_combinadas[$color_normalizado][] = $foto_item;
                            }
                        }
                    }
                }
            }
        }
    }
    $stmt->close();
    
    // Retornar arrays finales
    return [
        'generales' => $fotos_generales_combinadas,
        'por_color' => $fotos_por_color_combinadas
    ];
}

/**
 * Obtiene todos los IDs de productos activos
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array con IDs de productos activos
 */
function obtenerProductosActivos($mysqli) {
    $sql = "SELECT id_producto FROM Productos WHERE activo = 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productos_activos = [];
    while ($row = $result->fetch_assoc()) {
        $productos_activos[$row['id_producto']] = true;
    }
    
    $stmt->close();
    return $productos_activos;
}

/**
 * Desactiva un producto (soft delete)
 * 
 * Esta función marca un producto como inactivo (activo = 0) en lugar de eliminarlo.
 * Preserva el historial del producto en el sistema (pedidos, movimientos, etc.).
 * Actualiza fecha_actualizacion automáticamente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto a desactivar
 * @return bool True si se desactivó correctamente, false en caso contrario
 */
function desactivarProducto($mysqli, $id_producto) {
    $sql = "UPDATE Productos SET activo = 0, fecha_actualizacion = NOW() WHERE id_producto = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("Error preparando query para desactivar producto ID: " . $id_producto . " - " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('i', $id_producto);
    $resultado = $stmt->execute();
    
    if (!$resultado) {
        error_log("Error ejecutando query para desactivar producto ID: " . $id_producto . " - " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    // Verificar que realmente se actualizó al menos una fila
    $filas_afectadas = $stmt->affected_rows;
    $stmt->close();
    
    if ($filas_afectadas > 0) {
        return true;
    } else {
        error_log("Advertencia: desactivarProducto() no afectó ninguna fila para producto ID: " . $id_producto);
        return false;
    }
}

/**
 * Reactiva un producto (marca como activo)
 * 
 * Esta función marca un producto como activo (activo = 1) permitiéndole
 * volver a aparecer en el catálogo público.
 * Actualiza fecha_actualizacion automáticamente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto a reactivar
 * @return bool True si se reactivó correctamente, false en caso contrario
 */
function reactivarProducto($mysqli, $id_producto) {
    $sql = "UPDATE Productos SET activo = 1, fecha_actualizacion = NOW() WHERE id_producto = ?";
    
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
 * Verifica si un producto puede eliminarse permanentemente
 * 
 * Un producto puede eliminarse solo si:
 * - Está inactivo (activo = 0)
 * - NO tiene pedidos relacionados (Detalle_Pedido)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto a verificar
 * @return array Array con ['puede_eliminarse' => bool, 'razon' => string, 'pedidos_count' => int]
 */
function verificarProductoPuedeEliminarse($mysqli, $id_producto) {
    $resultado = [
        'puede_eliminarse' => false,
        'razon' => '',
        'pedidos_count' => 0
    ];
    
    // Verificar que el producto existe y obtener su estado
    $sql = "SELECT activo FROM Productos WHERE id_producto = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        $resultado['razon'] = 'Error al verificar el producto';
        return $resultado;
    }
    
    $stmt->bind_param('i', $id_producto);
    $stmt->execute();
    $result = $stmt->get_result();
    $producto = $result->fetch_assoc();
    $stmt->close();
    
    if (!$producto) {
        $resultado['razon'] = 'El producto no existe';
        return $resultado;
    }
    
    // Verificar que esté inactivo
    $activo = isset($producto['activo']) ? intval($producto['activo']) : 1;
    if ($activo === 1) {
        $resultado['razon'] = 'Solo se pueden eliminar productos inactivos';
        return $resultado;
    }
    
    // Contar pedidos relacionados
    $pedidos_count = contarPedidosRelacionados($mysqli, $id_producto);
    $resultado['pedidos_count'] = $pedidos_count;
    
    // Si tiene pedidos, no se puede eliminar
    if ($pedidos_count > 0) {
        $resultado['razon'] = 'El producto tiene pedidos relacionados';
        return $resultado;
    }
    
    // Si llegamos aquí, el producto puede eliminarse
    $resultado['puede_eliminarse'] = true;
    $resultado['razon'] = 'El producto puede eliminarse';
    
    return $resultado;
}

/**
 * Obtiene los IDs de todas las variantes de un producto (activas e inactivas)
 * 
 * Esta función es similar a obtenerIdsVariantes() pero incluye también las variantes inactivas.
 * Útil para eliminación permanente donde necesitamos eliminar todas las variantes.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @return array Array de IDs de variantes (activas e inactivas)
 */
function obtenerIdsVariantesTodas($mysqli, $id_producto) {
    $sql = "SELECT id_variante FROM Stock_Variantes WHERE id_producto = ?";
    
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
 * Elimina físicamente los movimientos de stock relacionados a variantes específicas
 * 
 * Esta función elimina permanentemente los registros de Movimientos_Stock
 * relacionados a las variantes especificadas. Solo se usa en eliminación permanente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $variantes_ids Array de IDs de variantes
 * @return bool True si se eliminaron correctamente, false en caso contrario
 */
function eliminarMovimientosStockVariantes($mysqli, $variantes_ids) {
    if (empty($variantes_ids) || !is_array($variantes_ids)) {
        return true; // No hay nada que eliminar
    }
    
    // Filtrar y validar IDs
    $variantes_ids = array_filter(array_map('intval', $variantes_ids));
    if (empty($variantes_ids)) {
        return true;
    }
    
    // Crear placeholders para la consulta IN
    $placeholders = str_repeat('?,', count($variantes_ids) - 1) . '?';
    $sql = "DELETE FROM Movimientos_Stock WHERE id_variante IN ($placeholders)";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    // Bind parameters
    $types = str_repeat('i', count($variantes_ids));
    $stmt->bind_param($types, ...$variantes_ids);
    $resultado = $stmt->execute();
    $stmt->close();
    
    return $resultado;
}

/**
 * Elimina físicamente todas las variantes de stock de un producto
 * 
 * Esta función elimina permanentemente todos los registros de Stock_Variantes
 * relacionados a un producto. Solo se usa en eliminación permanente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @return bool True si se eliminaron correctamente, false en caso contrario
 */
function eliminarVariantesStockFisicamente($mysqli, $id_producto) {
    $sql = "DELETE FROM Stock_Variantes WHERE id_producto = ?";
    
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
 * Elimina físicamente todas las fotos de un producto
 * 
 * Esta función elimina permanentemente todos los registros de Fotos_Producto
 * relacionados a un producto. Solo se usa en eliminación permanente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @return bool True si se eliminaron correctamente, false en caso contrario
 */
function eliminarFotosProductoFisicamente($mysqli, $id_producto) {
    $sql = "DELETE FROM Fotos_Producto WHERE id_producto = ?";
    
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
 * Elimina físicamente un producto de la base de datos
 * 
 * Esta función elimina permanentemente el registro del producto de la tabla Productos.
 * Solo se usa en eliminación permanente después de eliminar todas las relaciones.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto a eliminar
 * @return bool True si se eliminó correctamente, false en caso contrario
 */
function eliminarProductoFisicamente($mysqli, $id_producto) {
    $sql = "DELETE FROM Productos WHERE id_producto = ?";
    
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
 * Elimina permanentemente un producto y todas sus relaciones
 * 
 * Esta función realiza una eliminación física (hard delete) de un producto inactivo
 * y todas sus variantes, fotos, movimientos de stock e imágenes físicas.
 * 
 * IMPORTANTE: Solo elimina productos inactivos que NO tienen pedidos relacionados.
 * Si el producto tiene pedidos, la eliminación será bloqueada.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto a eliminar
 * @param string|null $ruta_base Ruta base del proyecto para eliminar imágenes (opcional)
 * @return array Array con ['success' => bool, 'mensaje' => string]
 */
function eliminarProductoPermanentemente($mysqli, $id_producto, $ruta_base = null) {
    $resultado = [
        'success' => false,
        'mensaje' => ''
    ];
    
    // Validar que el producto puede eliminarse
    $verificacion = verificarProductoPuedeEliminarse($mysqli, $id_producto);
    if (!$verificacion['puede_eliminarse']) {
        $resultado['mensaje'] = $verificacion['razon'];
        return $resultado;
    }
    
    // Iniciar transacción
    $mysqli->begin_transaction();
    
    try {
        // 1. Obtener IDs de todas las variantes (activas e inactivas)
        $variantes_ids = obtenerIdsVariantesTodas($mysqli, $id_producto);
        
        // 2. Eliminar físicamente Movimientos_Stock relacionados a esas variantes
        if (!empty($variantes_ids)) {
            if (!eliminarMovimientosStockVariantes($mysqli, $variantes_ids)) {
                throw new Exception('Error al eliminar movimientos de stock');
            }
        }
        
        // 3. Eliminar físicamente Stock_Variantes del producto
        if (!eliminarVariantesStockFisicamente($mysqli, $id_producto)) {
            throw new Exception('Error al eliminar variantes de stock');
        }
        
        // 4. Eliminar físicamente Fotos_Producto del producto
        if (!eliminarFotosProductoFisicamente($mysqli, $id_producto)) {
            throw new Exception('Error al eliminar fotos del producto');
        }
        
        // 5. Eliminar físicamente el Producto
        if (!eliminarProductoFisicamente($mysqli, $id_producto)) {
            throw new Exception('Error al eliminar el producto');
        }
        
        // 6. Eliminar directorio de imágenes físicas (opcional, no crítico)
        if (!eliminarDirectorioImagenes($id_producto, $ruta_base)) {
            // No lanzar excepción si falla la eliminación de imágenes, solo registrar
            error_log("Advertencia: No se pudo eliminar completamente el directorio de imágenes del producto $id_producto");
        }
        
        // Confirmar transacción
        $mysqli->commit();
        
        $resultado['success'] = true;
        $resultado['mensaje'] = 'Producto eliminado permanentemente del sistema';
        
    } catch (Exception $e) {
        $mysqli->rollback();
        $resultado['success'] = false;
        $resultado['mensaje'] = 'Error al eliminar el producto: ' . $e->getMessage();
    }
    
    return $resultado;
}