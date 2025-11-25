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
 * Para consultar stock directamente desde la base de datos, usar obtenerStockDisponible() 
 * de includes/queries/stock_queries.php
 * 
 * DIFERENCIAS:
 * - obtenerStock(): Trabaja con arrays en memoria, busca por talle+color en variantes ya cargadas
 * - obtenerStockDisponible(): Consulta la BD directamente por ID de variante
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

