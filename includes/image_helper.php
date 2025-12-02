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
 * Verifica si un archivo existe usando ruta relativa al directorio raíz del proyecto
 * Maneja tanto rutas absolutas como relativas, normalizando el formato
 * 
 * @param string $ruta Ruta relativa al directorio raíz (ej: "imagenes/imagen.png") o ruta absoluta
 * @return bool True si el archivo existe, false en caso contrario
 */
function verificarArchivoExiste($ruta) {
    if (empty($ruta)) {
        return false;
    }
    
    // Normalizar ruta: eliminar espacios y barras iniciales duplicadas
    $ruta = trim($ruta);
    $ruta = ltrim($ruta, '/');
    
    // Si la ruta ya es absoluta y el archivo existe, retornar true
    if (is_file($ruta)) {
        return true;
    }
    
    // Si es relativa, construir ruta completa desde el directorio raíz
    // Usar __DIR__ del archivo que incluye este helper (debe ser includes/)
    // Necesitamos ir un nivel arriba para llegar al directorio raíz
    $directorio_raiz = dirname(__DIR__);
    $ruta_completa = $directorio_raiz . '/' . $ruta;
    
    return file_exists($ruta_completa);
}

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
 * Obtiene los colores disponibles para un producto según las carpetas de imágenes en el sistema de archivos
 * 
 * NOTA: Esta función lee colores desde la estructura de carpetas de imágenes.
 * Para obtener colores desde la base de datos (tabla Stock_Variantes), usar 
 * obtenerColoresDisponiblesStock() de includes/queries/producto_queries.php
 * 
 * DIFERENCIAS:
 * - obtenerColoresDisponibles(): Lee del sistema de archivos (carpetas imagenes/productos/)
 * - obtenerColoresDisponiblesStock(): Consulta la BD (tabla Stock_Variantes con stock > 0)
 * 
 * @param string $categoria Nombre de la categoría
 * @param string $genero Genero del producto
 * @return array Array de colores disponibles (basado en carpetas de imágenes)
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

/**
 * Obtiene la imagen de un producto siguiendo la lógica de prioridad de catalogo.php
 * 
 * Prioridad:
 * 1. foto_prod_miniatura de la BD (si existe el archivo)
 * 2. obtenerMiniaturaPorColor() como fallback (si hay categoría, género y color)
 * 3. imagen por defecto 'imagenes/imagen.png'
 * 
 * @param string|null $foto_prod_miniatura Ruta de foto_prod_miniatura desde BD
 * @param string|null $nombre_categoria Nombre de la categoría para fallback
 * @param string|null $genero Género del producto para fallback (default: 'unisex')
 * @param string|null $color Color del producto para fallback
 * @return string Ruta de imagen validada
 */
function obtenerImagenProducto($foto_prod_miniatura = null, $nombre_categoria = null, $genero = null, $color = null) {
    $imagen = null;
    
    // Prioridad 1: Usar foto de la base de datos (ya viene con prioridad aplicada)
    if (!empty($foto_prod_miniatura)) {
        $ruta_foto = trim($foto_prod_miniatura);
        // Normalizar ruta: eliminar barras iniciales duplicadas
        $ruta_foto = ltrim($ruta_foto, '/');
        
        // Verificar existencia usando función centralizada
        if (verificarArchivoExiste($ruta_foto)) {
            $imagen = $ruta_foto;
        }
    }
    
    // Prioridad 2: Fallback - buscar en sistema de archivos si no hay foto en BD
    if (empty($imagen) && !empty($nombre_categoria) && !empty($color)) {
        // Normalizar color para búsqueda (lowercase)
        $color_actual = strtolower(trim($color));
        $genero_actual = !empty($genero) ? strtolower(trim($genero)) : 'unisex';
        $imagen = obtenerMiniaturaPorColor(
            $nombre_categoria,
            $genero_actual,
            $color_actual
        );
    }
    
    // Prioridad 3: Imagen por defecto si no se encuentra ninguna
    if (empty($imagen) || !verificarArchivoExiste($imagen)) {
        $imagen = 'imagenes/imagen.png';
    }
    
    return $imagen;
}

