<?php
/**
 * ========================================================================
 * DATOS PARA DETALLE DE PRODUCTO - Tienda Seda y Lino
 * ========================================================================
 * Genera los datos necesarios para el JavaScript de detalle de producto
 * 
 * REQUIERE VARIABLES PHP:
 * - $stockVariantes: Array de stock indexado por talle-color
 * - $stockPorTalleColor: Array de stock organizado por talle y color
 * - $fotos_por_color: Array de fotos organizadas por color
 * - $fotos_generales: Array con fotos generales del producto
 * - $imagenes: Array de imágenes iniciales del producto
 * - $producto: Array con datos del producto (nombre_producto)
 * - $id_producto: ID del producto
 * 
 * USO:
 *   include 'includes/detalle_producto_data.php';
 *   // Luego incluir: <script src="js/detalle-producto.js"></script>
 * 
 * @package TiendaSedaYLino
 * @version 2.0
 * ========================================================================
 */

// Verificar que las variables necesarias existan y tengan valores válidos
if (!isset($stockVariantes) || !is_array($stockVariantes)) {
    $stockVariantes = [];
}
if (!isset($stockPorTalleColor) || !is_array($stockPorTalleColor)) {
    $stockPorTalleColor = [];
}
if (!isset($fotos_por_color) || !is_array($fotos_por_color)) {
    $fotos_por_color = [];
}
if (!isset($fotos_generales)) {
    $fotos_generales = null;
}
if (!isset($imagenes) || !is_array($imagenes)) {
    $imagenes = ['imagenes/imagen.png'];
}
if (!isset($producto) || !is_array($producto)) {
    error_log('detalle_producto_data.php: Variable $producto no está definida o es inválida');
    $producto = ['nombre_producto' => 'Producto'];
}
if (!isset($id_producto) || !is_numeric($id_producto) || $id_producto <= 0) {
    error_log('detalle_producto_data.php: Variable $id_producto no está definida o es inválida');
    $id_producto = 0;
}

/**
 * Verifica si un archivo existe usando ruta relativa al directorio raíz del proyecto
 * @param string $ruta Ruta relativa al directorio raíz (ej: "imagenes/imagen.png")
 * @return bool True si el archivo existe, false en caso contrario
 */
function verificarArchivoExisteData($ruta) {
    if (empty($ruta)) {
        return false;
    }
    // Si la ruta ya es absoluta, usar directamente
    if (is_file($ruta)) {
        return true;
    }
    // Si es relativa, construir ruta completa desde el directorio raíz
    // Usar __DIR__ para obtener el directorio includes, luego subir un nivel
    $directorio_raiz = dirname(__DIR__);
    $ruta_completa = $directorio_raiz . '/' . ltrim($ruta, '/');
    return file_exists($ruta_completa);
}

// Preparar estructura de fotos por color para JavaScript
// Normalizar colores para que coincidan con el formato de los inputs
$fotos_js = [];
foreach ($fotos_por_color as $color => $fotos_array) {
    if (!empty($fotos_array)) {
        // Normalizar color para coincidencia exacta
        $color_normalizado_js = ucfirst(strtolower(trim($color)));
        $fotos_color_array = [];
        
        // Usar solo el primer elemento del array (el de mayor prioridad)
        // El primer elemento es el producto que realmente tiene este color
        $foto_prioritaria = $fotos_array[0];
        
        // Solo agregar fotos que existen físicamente del producto prioritario
        if (!empty($foto_prioritaria['foto1_prod']) && verificarArchivoExisteData($foto_prioritaria['foto1_prod'])) {
            $fotos_color_array[] = $foto_prioritaria['foto1_prod'];
        }
        if (!empty($foto_prioritaria['foto2_prod']) && verificarArchivoExisteData($foto_prioritaria['foto2_prod'])) {
            $fotos_color_array[] = $foto_prioritaria['foto2_prod'];
        }
        if (!empty($foto_prioritaria['foto3_prod']) && verificarArchivoExisteData($foto_prioritaria['foto3_prod'])) {
            $fotos_color_array[] = $foto_prioritaria['foto3_prod'];
        }
        
        // Si no tiene foto3 específica de color, intentar usar la grupal general
        if (empty($foto_prioritaria['foto3_prod']) && $fotos_generales && !empty($fotos_generales['foto3_prod'])) {
            if (verificarArchivoExisteData($fotos_generales['foto3_prod'])) {
                $fotos_color_array[] = $fotos_generales['foto3_prod'];
            }
        }
        
        // Si el producto prioritario no tiene fotos (foto1 ni foto2), usar las del siguiente como fallback
        if (empty($fotos_color_array) && count($fotos_array) > 1) {
            foreach ($fotos_array as $foto_item) {
                if (!empty($foto_item['foto1_prod']) && verificarArchivoExisteData($foto_item['foto1_prod'])) {
                    $fotos_color_array[] = $foto_item['foto1_prod'];
                }
                if (!empty($foto_item['foto2_prod']) && verificarArchivoExisteData($foto_item['foto2_prod'])) {
                    $fotos_color_array[] = $foto_item['foto2_prod'];
                }
                if (!empty($foto_item['foto3_prod']) && verificarArchivoExisteData($foto_item['foto3_prod'])) {
                    $fotos_color_array[] = $foto_item['foto3_prod'];
                }
                // Solo usar el primer fallback que tenga fotos
                if (!empty($fotos_color_array)) {
                    break;
                }
            }
        }
        
        if (!empty($fotos_color_array)) {
            $fotos_js[$color_normalizado_js] = $fotos_color_array;
        }
    }
}

// Agregar fotos generales como fallback
if ($fotos_generales) {
    $fotos_generales_array = [];
    if (!empty($fotos_generales['foto3_prod']) && verificarArchivoExisteData($fotos_generales['foto3_prod'])) {
        $fotos_generales_array[] = $fotos_generales['foto3_prod'];
    }
    if (!empty($fotos_generales['foto_prod_miniatura']) && verificarArchivoExisteData($fotos_generales['foto_prod_miniatura'])) {
        $fotos_generales_array[] = $fotos_generales['foto_prod_miniatura'];
    }
    if (!empty($fotos_generales_array)) {
        $fotos_js['_generales'] = $fotos_generales_array;
    }
}

// Asegurar que $imagenes sea un array válido
$imagenes_js = isset($imagenes) && is_array($imagenes) ? $imagenes : ['imagenes/imagen.png'];

// Preparar datos para JavaScript
$producto_data = [
    'stockVariantes' => $stockVariantes,
    'stockPorTalleColor' => $stockPorTalleColor,
    'fotosPorColor' => $fotos_js,
    'imagenes' => $imagenes_js,
    'nombreProducto' => htmlspecialchars($producto['nombre_producto'], ENT_QUOTES, 'UTF-8'),
    'idProducto' => (int)$id_producto
];

// Generar script con los datos
?>
<script>
    // Datos del producto para JavaScript
    window.productoData = <?php echo json_encode($producto_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

