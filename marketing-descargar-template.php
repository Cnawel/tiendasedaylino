<?php
/**
 * ========================================================================
 * DESCARGA DE PLANTILLA CSV - Tienda Seda y Lino
 * ========================================================================
 * Genera y descarga un archivo CSV de plantilla con headers y ejemplos
 * para facilitar la carga masiva de productos
 * ========================================================================
 */
session_start();

// Verificación de acceso
require_once __DIR__ . '/includes/auth_check.php';
requireRole('marketing');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/queries/categoria_queries.php';

// Obtener categorías disponibles para ejemplos
$categorias_array = obtenerCategorias($mysqli);
$nombres_categorias = array_column($categorias_array, 'nombre_categoria');

// Si no hay categorías, usar ejemplos por defecto
if (empty($nombres_categorias)) {
    $nombres_categorias = ['Blusas', 'Pantalones', 'Camisas', 'Shorts'];
}

// Configurar headers para descarga
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="plantilla_productos.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Abrir output stream
$output = fopen('php://output', 'w');

// Ruta al archivo de ejemplo
$archivo_ejemplo = __DIR__ . '/uploads/ejemplo_productos.csv';

if (file_exists($archivo_ejemplo)) {
    // Si existe el archivo físico, servirlo directamente
    readfile($archivo_ejemplo);
} else {
    // Si no existe, al menos devolver los headers básicos (fallback)
    // Agregar BOM para UTF-8 (compatibilidad con Excel)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers esperados (mismos que en ejemplo_productos.csv)
    $headers = [
        'nombre_producto','descripcion_producto','precio_actual','categoria','genero','talle','color','stock','foto_prod_miniatura','foto1_prod','foto2_prod','foto3_prod'
    ];
    fputcsv($output, $headers);
}



// Cerrar stream
fclose($output);
exit;

