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

// Agregar BOM para UTF-8 (compatibilidad con Excel)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Escribir headers limpios en español
$headers = [
    'Nombre',
    'Descripción',
    'Precio',
    'Categoría',
    'Género',
    'Talle',
    'Color',
    'Stock',
    'Foto Miniatura',
    'Foto 1',
    'Foto 2',
    'Foto 3 (Grupal)'
];
fputcsv($output, $headers);

// Escribir filas de ejemplo
$ejemplos = [
    [
        'Blusa Mujer Manga Larga',
        'Blusa de lino, manga larga, perfecta para cualquier ocasión',
        '15000.00',
        $nombres_categorias[0] ?? 'Blusas',
        'mujer',
        'M',
        'Azul',
        '10',
        'blusa_miniatura.webp',
        'blusa_azul_1.webp',
        'blusa_azul_2.webp',
        'blusa_grupal.webp'
    ],
    [
        'Blusa Mujer Manga Larga',
        'Blusa de lino, manga larga, perfecta para cualquier ocasión',
        '15000.00',
        $nombres_categorias[0] ?? 'Blusas',
        'mujer',
        'L',
        'Blanco',
        '8',
        '',
        'blusa_blanco_1.webp',
        'blusa_blanco_2.webp',
        ''
    ],
    [
        'Pantalón Hombre Clásico',
        'Pantalón de lino, corte clásico, cómodo y elegante',
        '25000.00',
        $nombres_categorias[1] ?? 'Pantalones',
        'hombre',
        'M',
        'Negro',
        '15',
        'pantalon_miniatura.webp',
        'pantalon_negro_1.webp',
        'pantalon_negro_2.webp',
        'pantalon_grupal.webp'
    ],
    [
        'Camisa Unisex Básica',
        'Camisa de seda, diseño unisex, versátil y moderna',
        '18000.00',
        $nombres_categorias[2] ?? 'Camisas',
        'unisex',
        'L',
        'Beige',
        '12',
        '',
        '',
        '',
        ''
    ]
];

// Escribir ejemplos
foreach ($ejemplos as $ejemplo) {
    fputcsv($output, $ejemplo);
}

// Cerrar stream
fclose($output);
exit;

