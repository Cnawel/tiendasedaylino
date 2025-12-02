<?php
/**
 * ========================================================================
 * FUNCIONES AUXILIARES DE PRODUCTOS - Tienda Seda y Lino
 * ========================================================================
 * Funciones auxiliares para procesamiento de datos de productos
 * Utilizadas principalmente en detalle-producto.php
 * 
 * FUNCIONES:
 * - obtenerStock(): Obtiene stock disponible para combinación talle-color
 * - generarArrayStock(): Genera array asociativo de stock indexado por talle-color
 * - generarStockPorTalleYColor(): Genera estructura de stock organizada por talle y color
 * 
 * USO:
 *   require_once __DIR__ . '/includes/producto_functions.php';
 *   $stock = obtenerStock($variantes, $talla, $color);
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Obtiene el stock disponible para una combinación de talla y color desde un array en memoria
 * 
 * NOTA: Esta función trabaja con datos ya cargados en memoria (arrays de variantes).
 * Para consultar stock directamente desde la base de datos, consultar directamente
 * la tabla Stock_Variantes.
 * 
 * @param array $variantes Array de variantes del producto (ya cargadas en memoria)
 * @param string $talla Talla seleccionada
 * @param string $color Color seleccionado
 * @return int Cantidad en stock (0 si no hay)
 */
function obtenerStock($variantes, $talla, $color) {
    // LÓGICA DE NEGOCIO: Obtiene el stock disponible para una combinación específica de talle y color.
    // REGLA DE NEGOCIO: Cada variante (talle + color) tiene su propio stock independiente.
    // LÓGICA: Busca la variante exacta que coincida con la combinación solicitada.
    
    // Iterar sobre todas las variantes del producto
    // REGLA: Buscar coincidencia exacta de talle y color
    foreach ($variantes as $variante) {
        // Verificar si la variante coincide con la combinación solicitada
        // REGLA: Comparación exacta de talle y color (case-sensitive)
        // LÓGICA: Una variante específica tiene stock único, no se suman variantes diferentes
        if ($variante['talle'] === $talla && $variante['color'] === $color) {
            // Retornar stock de la variante encontrada
            return $variante['stock'];
        }
    }
    
    // Si no se encuentra la combinación, retornar 0 (sin stock)
    // REGLA: Si no existe la variante, no hay stock disponible
    return 0;
}

/**
 * Genera un array asociativo de stock indexado por talla-color
 * Facilita la consulta de stock en JavaScript
 * Normaliza colores para consistencia con el formato de los inputs
 * 
 * @param array $variantes Array de variantes desde Stock_Variantes
 * @return array Array con clave 'talle-color' y valor stock (colores normalizados)
 */
function generarArrayStock($variantes) {
    // LÓGICA DE NEGOCIO: Genera estructura de datos optimizada para consultas rápidas de stock en JavaScript.
    // REGLA DE NEGOCIO: El stock se indexa por combinación 'talle-color' para acceso O(1).
    // LÓGICA: Facilita validación en tiempo real de stock disponible sin iterar sobre todas las variantes.
    
    $stockArray = array();
    
    // Procesar cada variante para crear índice de stock
    foreach ($variantes as $variante) {
        // Normalizar color para consistencia con formato de inputs del formulario
        // REGLA: Color debe estar en formato "PrimeraLetraMayuscula" (ej: "Azul", "Blanco")
        // LÓGICA: Normalización asegura que coincida con el formato usado en el frontend
        $color_normalizado = ucfirst(strtolower(trim($variante['color'])));
        
        // Crear clave única para la combinación talle-color
        // REGLA: Formato de clave: "TALLE-Color" (ej: "M-Azul", "L-Blanco")
        // LÓGICA: Formato estandarizado permite búsqueda rápida en JavaScript
        $clave = $variante['talle'] . '-' . $color_normalizado;
        
        // Inicializar stock si no existe la clave
        if (!isset($stockArray[$clave])) {
            $stockArray[$clave] = 0;
        }
        
        // Sumar stock de la variante al total de la combinación
        // REGLA: Si existen múltiples variantes con mismo talle-color, se acumula el stock
        // LÓGICA: Permite manejar casos donde hay múltiples registros de stock para misma combinación
        $stockArray[$clave] += (int)$variante['stock'];
    }
    
    return $stockArray;
}

/**
 * Genera información de stock por color para cada talle
 * Útil para tachar talles/colores sin stock según la selección
 * Normaliza colores para consistencia con el formato de los inputs
 * 
 * @param array $variantes Array de variantes desde Stock_Variantes
 * @return array Array con estructura: [talle][color_normalizado] = stock
 */
function generarStockPorTalleYColor($variantes) {
    // LÓGICA DE NEGOCIO: Organiza stock en estructura jerárquica por talle y luego por color.
    // REGLA DE NEGOCIO: Permite consultar stock agrupado por talle para facilitar UX (deshabilitar opciones sin stock).
    // LÓGICA: Estructura bidimensional [talle][color] permite deshabilitar talles/colores sin stock dinámicamente.
    
    $stock_por_talle_color = [];
    
    // Procesar cada variante para organizar stock jerárquicamente
    foreach ($variantes as $variante) {
        $talle = $variante['talle'];
        
        // Normalizar color para consistencia con formato de inputs
        // REGLA: Color en formato "PrimeraLetraMayuscula" para coincidir con frontend
        $color_normalizado = ucfirst(strtolower(trim($variante['color'])));
        $stock = (int)$variante['stock'];
        
        // Inicializar array de talle si no existe
        // REGLA: Cada talle tiene su propio array de colores
        if (!isset($stock_por_talle_color[$talle])) {
            $stock_por_talle_color[$talle] = [];
        }
        
        // Inicializar stock del color si no existe
        // REGLA: Si hay múltiples variantes con mismo talle-color, se suman los stocks
        if (!isset($stock_por_talle_color[$talle][$color_normalizado])) {
            $stock_por_talle_color[$talle][$color_normalizado] = 0;
        }
        
        // Sumar stock de la variante al total del talle-color
        // REGLA: Si existen múltiples variantes con mismo talle-color, se acumula el stock
        // LÓGICA: Permite manejar casos donde hay múltiples registros de stock para misma combinación
        $stock_por_talle_color[$talle][$color_normalizado] += $stock;
    }
    
    return $stock_por_talle_color;
}

/**
 * Normaliza un color para uso consistente en PHP y JavaScript
 * Convierte a formato: Primera Letra Mayúscula
 *
 * @param string $color Color a normalizar
 * @return string Color normalizado
 */
function normalizeColor($color) {
    return ucfirst(strtolower(trim($color)));
}

/**
 * Selecciona el talle predeterminado con lógica de prioridad
 *
 * Prioridad:
 * 1. Talle 'M' con stock (si requireStock = true)
 * 2. Primer talle con stock (si requireStock = true)
 * 3. Talle 'M' sin stock
 * 4. Primer talle disponible
 *
 * @param array $tallas_info Array de información de tallas (debe contener 'talle' y 'tiene_stock')
 * @param bool $requireStock Si true, prioriza tallas con stock
 * @return string|null Talle seleccionado o null si no hay tallas
 */
function selectDefaultTalle($tallas_info, $requireStock = false) {
    if (empty($tallas_info)) {
        return null;
    }

    // Prioridad 1: Buscar 'M' con stock (o sin si !requireStock)
    foreach ($tallas_info as $info) {
        if (!$requireStock || $info['tiene_stock']) {
            if ($info['talle'] === 'M') {
                return 'M';
            }
        }
    }

    // Prioridad 2: Buscar primer disponible con/sin stock
    foreach ($tallas_info as $info) {
        if (!$requireStock || $info['tiene_stock']) {
            return $info['talle'];
        }
    }

    return null;
}

/**
 * Extrae imágenes válidas de un registro de fotos
 *
 * @param array $photoRecord Registro con foto1_prod y foto2_prod
 * @return array Array con rutas de imágenes que existen
 */
function extractValidImages($photoRecord) {
    $images = [];

    if (!empty($photoRecord['foto1_prod'])) {
        $images[] = $photoRecord['foto1_prod'];
    }

    if (!empty($photoRecord['foto2_prod'])) {
        $images[] = $photoRecord['foto2_prod'];
    }

    return $images;
}

/**
 * Calcula información de tallas disponibles con stock
 *
 * @param array $variantes Array de variantes del producto
 * @param array $orden_tallas_estandar Orden preferido de tallas
 * @return array Array de información por talle: ['talle', 'tiene_stock', 'stock_total']
 */
function prepareTallesInfo($variantes, $orden_tallas_estandar) {
    $tallas_info = [];
    $tallas_en_variantes = array_unique(array_column($variantes, 'talle'));

    // Procesar tallas en orden estándar
    foreach ($orden_tallas_estandar as $talla_std) {
        if (in_array($talla_std, $tallas_en_variantes)) {
            $tallas_info[] = createTallaInfo($talla_std, $variantes);
        }
    }

    // Agregar tallas no estándar si necesario
    if (empty($tallas_info)) {
        foreach ($tallas_en_variantes as $talla) {
            $tallas_info[] = createTallaInfo($talla, $variantes);
        }
    }

    return $tallas_info;
}

/**
 * Crea información de una talla individual
 *
 * @param string $talla Talla a procesar
 * @param array $variantes Array de variantes
 * @return array Info de talla: ['talle', 'tiene_stock', 'stock_total']
 */
function createTallaInfo($talla, $variantes) {
    $stock_total = 0;

    foreach ($variantes as $variante) {
        if ($variante['talle'] === $talla) {
            $stock_total += (int)$variante['stock'];
        }
    }

    return [
        'talle' => $talla,
        'tiene_stock' => $stock_total > 0,
        'stock_total' => $stock_total
    ];
}

