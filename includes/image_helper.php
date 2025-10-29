<?php
/**
 * ========================================================================
 * HELPER DE IMÁGENES - Tienda Seda y Lino
 * ========================================================================
 * Funciones para manejar las imágenes de productos según color y categoría
 * Basado en la estructura de carpetas: imagenes/productos/{categoria}/{genero}/{color}/
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Construye la ruta completa de imagen para un producto según su color
 * 
 * Estructura esperada:
 * imagenes/productos/{categoria}/{genero}/{color}/{archivos}
 * 
 * @param string $categoria Nombre de la categoría (ej: "Camisas", "Blusas")
 * @param string $genero Genero del producto (ej: "hombre", "mujer", "unisex")
 * @param string $color Nombre del color (ej: "azul", "blanco", "gris")
 * @param string $nombre_producto Nombre base del producto
 * @return array Array con rutas de imágenes encontradas
 */
function obtenerImagenesPorColor($categoria, $genero, $color, $nombre_producto = '') {
    // Normalizar nombres para coincidir con carpetas
    $categoria = strtolower($categoria);
    $genero = strtolower($genero);
    $color = strtolower($color);
    
    // Mapeo de nombres de base de datos a nombres de carpetas
    $mapeo_colores = [
        'blanco' => 'blanca',
        'azul' => 'azul',
        'gris' => 'gris',
        'negro' => 'negro',
        'crema' => 'crema',
        'marron' => 'marron',
        'celeste' => 'celeste',
        'natural' => 'natural'
    ];
    
    // Usar nombre de carpeta si existe en el mapeo
    $color_carpeta = $mapeo_colores[$color] ?? $color;
    
    // Construir ruta base
    $ruta_base = "imagenes/productos/{$categoria}";
    
    // Si el producto tiene genero específico
    if ($genero && $genero !== 'unisex') {
        $ruta_base .= "/{$genero}";
    }
    
    // Agregar color
    $ruta_color = $ruta_base . "/{$color_carpeta}";
    
    // Buscar imágenes en la carpeta del color
    $imagenes_encontradas = array();
    
    if (is_dir($ruta_color)) {
        $archivos = scandir($ruta_color);
        
        foreach ($archivos as $archivo) {
            // Solo archivos de imagen
            if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $archivo)) {
                $imagenes_encontradas[] = $ruta_color . '/' . $archivo;
            }
        }
    }
    
    // Si no se encontraron imágenes en la carpeta del color, buscar en la carpeta genérica
    if (empty($imagenes_encontradas) && is_dir($ruta_base)) {
        $archivos = scandir($ruta_base);
        
        foreach ($archivos as $archivo) {
            if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $archivo)) {
                $imagenes_encontradas[] = $ruta_base . '/' . $archivo;
            }
        }
    }
    
    // Ordenar imágenes: primero las que tienen "modelo" en el nombre, luego por nombre
    usort($imagenes_encontradas, function($a, $b) {
        $prioridad_a = (strpos(strtolower(basename($a)), 'modelo') !== false) ? 0 : 1;
        $prioridad_b = (strpos(strtolower(basename($b)), 'modelo') !== false) ? 0 : 1;
        
        if ($prioridad_a !== $prioridad_b) {
            return $prioridad_a - $prioridad_b;
        }
        
        return strcmp($a, $b);
    });
    
    return $imagenes_encontradas;
}

/**
 * Obtiene la miniatura del producto según color
 * Busca específicamente archivos con "mini" en el nombre
 * 
 * @param string $categoria Nombre de la categoría
 * @param string $genero Genero del producto
 * @param string $color Color del producto
 * @return string Ruta de la miniatura o imagen por defecto
 */
function obtenerMiniaturaPorColor($categoria, $genero, $color) {
    $imagenes = obtenerImagenesPorColor($categoria, $genero, $color);
    
    // Buscar miniatura específica
    foreach ($imagenes as $imagen) {
        if (preg_match('/mini/i', basename($imagen))) {
            return $imagen;
        }
    }
    
    // Devolver primera imagen si no hay miniatura
    if (!empty($imagenes)) {
        return $imagenes[0];
    }
    
    // Imagen por defecto
    return 'imagenes/imagen.png';
}

/**
 * Obtiene los colores disponibles para un producto según las carpetas existentes
 * 
 * @param string $categoria Nombre de la categoría
 * @param string $genero Genero del producto
 * @return array Array de colores disponibles
 */
function obtenerColoresDisponibles($categoria, $genero) {
    $categoria = strtolower($categoria);
    $genero = strtolower($genero);
    
    $ruta_base = "imagenes/productos/{$categoria}";
    
    if ($genero && $genero !== 'unisex') {
        $ruta_base .= "/{$genero}";
    }
    
    $colores = array();
    
    if (is_dir($ruta_base)) {
        $items = scandir($ruta_base);
        
        foreach ($items as $item) {
            $ruta_item = $ruta_base . '/' . $item;
            
            // Si es un directorio (color)
            if (is_dir($ruta_item) && !in_array($item, ['.', '..'])) {
                // Verificar que tenga imágenes
                $archivos = scandir($ruta_item);
                $tiene_imagenes = false;
                
                foreach ($archivos as $archivo) {
                    if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $archivo)) {
                        $tiene_imagenes = true;
                        break;
                    }
                }
                
                if ($tiene_imagenes) {
                    // Mapeo inverso: nombre de carpeta a nombre de color
                    $mapeo_inverso = [
                        'blanca' => 'Blanco',
                        'azul' => 'Azul',
                        'gris' => 'Gris',
                        'negro' => 'Negro',
                        'crema' => 'Crema',
                        'marron' => 'Marrón',
                        'celeste' => 'Celeste',
                        'natural' => 'Natural'
                    ];
                    
                    $nombre_color = $mapeo_inverso[$item] ?? ucfirst($item);
                    $colores[] = $nombre_color;
                }
            }
        }
    }
    
    // Ordenar colores
    sort($colores);
    
    return $colores;
}

?>

