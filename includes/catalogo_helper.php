<?php
/**
 * ========================================================================
 * HELPERS PARA CATÁLOGO - Tienda Seda y Lino
 * ========================================================================
 * Funciones auxiliares para procesar filtros y lógica de presentación
 * del catálogo de productos.
 * ========================================================================
 */

require_once __DIR__ . '/talles_config.php';
require_once __DIR__ . '/queries/categoria_queries.php';

/**
 * Procesa y sanitiza los parámetros GET para filtros del catálogo
 * 
 * @param array $params Array $_GET
 * @return array Array con filtros sanitizados (categoria_nombre, talles, generos, colores)
 */
function procesarParametrosFiltros($params) {
    $filtros = [
        'categoria_nombre' => 'todos',
        'talles' => [],
        'generos' => [],
        'colores' => []
    ];
    
    // 1. Categoría
    if (isset($params['categoria']) && $params['categoria'] !== 'todos') {
        $filtros['categoria_nombre'] = trim(urldecode($params['categoria']));
    }
    
    // 2. Talles (Solo estándar S, M, L, XL)
    if (isset($params['talle'])) {
        $talles_temp = is_array($params['talle']) ? $params['talle'] : [$params['talle']];
        $talles_validos = obtenerTallesEstandar();
        
        foreach ($talles_temp as $talle) {
            if (in_array($talle, $talles_validos)) {
                $filtros['talles'][] = $talle;
            }
        }
    }
    
    // 3. Géneros
    if (isset($params['genero'])) {
        $generos_temp = is_array($params['genero']) ? $params['genero'] : [$params['genero']];
        $generos_validos = ['hombre', 'mujer', 'unisex'];
        
        foreach ($generos_temp as $genero) {
            $genero_normalizado = strtolower(trim($genero));
            if (in_array($genero_normalizado, $generos_validos) && !in_array($genero_normalizado, $filtros['generos'])) {
                $filtros['generos'][] = $genero_normalizado;
            }
        }
    }
    
    // 4. Colores
    if (isset($params['color'])) {
        $colores_temp = is_array($params['color']) ? $params['color'] : [$params['color']];
        
        foreach ($colores_temp as $color) {
            $color_normalizado = ucfirst(strtolower(trim($color)));
            if (!in_array($color_normalizado, $filtros['colores'])) {
                $filtros['colores'][] = $color_normalizado;
            }
        }
    }
    
    return $filtros;
}

/**
 * Valida la categoría solicitada y devuelve su ID si existe
 * 
 * @param mysqli $mysqli Conexión a BD
 * @param string $nombre_categoria Nombre de la categoría a buscar
 * @return int|null ID de la categoría o null si no existe o es 'todos'
 */
function validarCategoriaSolicitada($mysqli, $nombre_categoria) {
    if ($nombre_categoria === 'todos') {
        return null;
    }
    
    $categoria_id = obtenerCategoriaIdPorNombre($mysqli, $nombre_categoria);
    
    // Validaciones adicionales y logging
    if (!$categoria_id) {
        error_log("WARNING catalogo.php - No se encontró categoría con nombre: '" . $nombre_categoria . "'");
        return null;
    }
    
    // Verificación de consistencia (opcional, para debug)
    // Se podría mover la lógica de verificación extra aquí si fuera necesaria
    
    return $categoria_id;
}

/**
 * Genera el array de descripciones para SEO/UX basado en categorías activas
 * 
 * @param array $categorias_activas Array de categorías
 * @return array Array asociativo [nombre_categoria => descripcion]
 */
function generarDescripcionesCategorias($categorias_activas) {
    $descripciones = [
        'todos' => 'Descubre nuestra colección completa de productos elegantes'
    ];
    
    foreach ($categorias_activas as $cat) {
        $descripciones[$cat['nombre_categoria']] = !empty($cat['descripcion_categoria']) 
            ? $cat['descripcion_categoria'] 
            : 'Productos de ' . $cat['nombre_categoria'];
    }
    
    return $descripciones;
}
