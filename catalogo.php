<?php
/**
 * ========================================================================
 * CATÁLOGO DE PRODUCTOS - Tienda Seda y Lino
 * ========================================================================
 * Muestra productos filtrados por categoría, talle y color
 * Tablas utilizadas: Productos, Categorias, Stock_Variantes
 * ========================================================================
 */

// Configurar título de la página
$titulo_pagina = 'Catálogo de Productos';

// Incluir header completo (head + navigation)
include 'includes/header.php';

// Conectar a la base de datos y helpers
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/image_helper.php';

// Obtener categoría seleccionada desde URL, por defecto 'todos'
$categoria_nombre = $_GET['categoria'] ?? 'todos';
// ID de categoría para filtrado en BD (se obtiene más abajo si es necesario)
$categoria_id = null;

// Descripciones personalizadas por categoría para SEO y UX
$descripciones = [
    'todos' => 'Descubre nuestra colección completa de productos elegantes',
    'Camisas' => 'Elegantes camisas de seda y lino para ocasiones especiales',
    'Blusas' => 'Hermosas blusas frescas y cómodas, perfectas para el día a día',
    'Pantalones' => 'Pantalones modernos y versátiles en diferentes estilos',
    'Shorts' => 'Shorts casuales ideales para el verano y climas cálidos'
];

// Obtener talles seleccionados desde filtros GET
$talles_seleccionados = [];
if (isset($_GET['talle'])) {
    $talles_seleccionados = is_array($_GET['talle']) ? $_GET['talle'] : [$_GET['talle']];
}

// Obtener colores seleccionados desde filtros GET
$colores_seleccionados = [];
if (isset($_GET['color'])) {
    $colores_seleccionados = is_array($_GET['color']) ? $_GET['color'] : [$_GET['color']];
}

// Si la categoría no es 'todos', obtener su ID desde la base de datos
if ($categoria_nombre !== 'todos') {
    $stmt = $mysqli->prepare("SELECT id_categoria FROM Categorias WHERE nombre_categoria = ? LIMIT 1");
    $stmt->bind_param('s', $categoria_nombre);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $categoria_id = $row['id_categoria'];
    }
}

// Consulta para obtener talles disponibles con stock
// Agrupa por talle y suma el stock total, filtrando solo los que tienen stock > 0
$talles_disponibles = [];
$sql_talles = "SELECT sv.talle, SUM(sv.stock) as total_stock
               FROM Stock_Variantes sv
               INNER JOIN Productos p ON sv.id_producto = p.id_producto
               WHERE sv.stock > 0";
// Agregar filtro de categoría si está seleccionada
if ($categoria_id) {
    $sql_talles .= " AND p.id_categoria = ?";
}
$sql_talles .= " GROUP BY sv.talle HAVING total_stock > 0 ORDER BY sv.talle";

$stmt_talles = $mysqli->prepare($sql_talles);
if ($categoria_id) {
    $stmt_talles->bind_param('i', $categoria_id);
}
$stmt_talles->execute();
$result_talles = $stmt_talles->get_result();
// Almacenar talles con su stock total en un array asociativo
while ($row = $result_talles->fetch_assoc()) {
    $talles_disponibles[$row['talle']] = $row['total_stock'];
}

// Consulta para obtener colores disponibles con stock
// Similar a talles, agrupa por color y suma el stock total
$colores_disponibles = [];
$sql_colores = "SELECT sv.color, SUM(sv.stock) as total_stock
                FROM Stock_Variantes sv
                INNER JOIN Productos p ON sv.id_producto = p.id_producto
                WHERE sv.stock > 0";
// Agregar filtro de categoría si está seleccionada
if ($categoria_id) {
    $sql_colores .= " AND p.id_categoria = ?";
}
$sql_colores .= " GROUP BY sv.color HAVING total_stock > 0 ORDER BY sv.color";

$stmt_colores = $mysqli->prepare($sql_colores);
if ($categoria_id) {
    $stmt_colores->bind_param('i', $categoria_id);
}
$stmt_colores->execute();
$result_colores = $stmt_colores->get_result();
// Almacenar colores con su stock total en un array asociativo
while ($row = $result_colores->fetch_assoc()) {
    $colores_disponibles[$row['color']] = $row['total_stock'];
}

// Construir consulta de productos filtrados dinámicamente
// Array para almacenar condiciones WHERE
$where_parts = ["sv.stock > 0"];
// Array para parámetros de prepared statement
$params = [];
// String con tipos de datos para bind_param (i=integer, s=string)
$types = '';

// Agregar filtro de categoría si está seleccionada
if ($categoria_id) {
    $where_parts[] = "p.id_categoria = ?";
    $params[] = $categoria_id;
    $types .= 'i';
}

// Agregar filtro de talles si hay selecciones
if (!empty($talles_seleccionados)) {
    $placeholders = str_repeat('?,', count($talles_seleccionados) - 1) . '?';
    $where_parts[] = "sv.talle IN ($placeholders)";
    $params = array_merge($params, $talles_seleccionados);
    $types .= str_repeat('s', count($talles_seleccionados));
}

// Agregar filtro de colores si hay selecciones
if (!empty($colores_seleccionados)) {
    $placeholders = str_repeat('?,', count($colores_seleccionados) - 1) . '?';
    $where_parts[] = "sv.color IN ($placeholders)";
    $params = array_merge($params, $colores_seleccionados);
    $types .= str_repeat('s', count($colores_seleccionados));
}

// Ejecutar consulta final de productos con todos los filtros aplicados
// SOLUCIÓN PARA EVITAR DUPLICADOS: primero obtener IDs únicos de productos que cumplen los filtros
// Luego obtener los datos completos de esos productos usando subconsultas
$sql_ids = "SELECT DISTINCT p.id_producto
            FROM Productos p
            INNER JOIN Stock_Variantes sv ON p.id_producto = sv.id_producto
            INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
            WHERE " . implode(' AND ', $where_parts);

$stmt_ids = $mysqli->prepare($sql_ids);
if (!empty($params)) {
    $stmt_ids->bind_param($types, ...$params);
}
$stmt_ids->execute();
$result_ids = $stmt_ids->get_result();
$productos_ids = [];
while ($row = $result_ids->fetch_assoc()) {
    $productos_ids[] = $row['id_producto'];
}

// Si hay productos que cumplen filtros, obtener sus datos completos sin duplicados
// Usar GROUP BY correctamente para evitar duplicados
if (!empty($productos_ids)) {
    $placeholders = str_repeat('?,', count($productos_ids) - 1) . '?';
    
    // Consulta simplificada: obtener datos del producto y usar subconsultas simples para color y stock
    // Las subconsultas filtran internamente pero no necesitan parámetros adicionales ya que el filtro principal ya se aplicó
    $sql_final = "SELECT DISTINCT
                         p.id_producto, 
                         p.nombre_producto, 
                         p.descripcion_producto, 
                         p.precio_actual, 
                         p.genero, 
                         c.nombre_categoria,
                         (SELECT MIN(sv_min.color) 
                          FROM Stock_Variantes sv_min 
                          WHERE sv_min.id_producto = p.id_producto 
                          AND sv_min.stock > 0) as color,
                         (SELECT SUM(sv_sum.stock) 
                          FROM Stock_Variantes sv_sum 
                          WHERE sv_sum.id_producto = p.id_producto 
                          AND sv_sum.stock > 0) as total_stock
                   FROM Productos p
                   INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
                   WHERE p.id_producto IN ($placeholders)
                   GROUP BY p.id_producto, p.nombre_producto, p.descripcion_producto, p.precio_actual, p.genero, c.nombre_categoria
                   ORDER BY p.nombre_producto";
    
    $stmt = $mysqli->prepare($sql_final);
    $types_final = str_repeat('i', count($productos_ids));
    $stmt->bind_param($types_final, ...$productos_ids);
    $stmt->execute();
    $productos = $stmt->get_result();
} else {
    // Si no hay productos, ejecutar consulta vacía para obtener estructura de resultado
    $sql_final = "SELECT p.id_producto, p.nombre_producto, p.descripcion_producto, p.precio_actual, 
                          p.genero, c.nombre_categoria, '' as color, 0 as total_stock
                   FROM Productos p
                   INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
                   WHERE 1=0";
    $stmt = $mysqli->prepare($sql_final);
    $stmt->execute();
    $productos = $stmt->get_result();
}
?>

<main class="productos">
    <!-- Barra de categorías debajo del navbar -->
    <div class="categorias-bar">
        <div class="container-fluid">
            <div class="categoria-opciones-bar">
                <a href="catalogo.php?categoria=todos" class="categoria-opcion-bar <?= $categoria_nombre === 'todos' ? 'active' : '' ?>">Todos</a>
                <a href="catalogo.php?categoria=Camisas" class="categoria-opcion-bar <?= $categoria_nombre === 'Camisas' ? 'active' : '' ?>">Camisas</a>
                <a href="catalogo.php?categoria=Blusas" class="categoria-opcion-bar <?= $categoria_nombre === 'Blusas' ? 'active' : '' ?>">Blusas</a>
                <a href="catalogo.php?categoria=Pantalones" class="categoria-opcion-bar <?= $categoria_nombre === 'Pantalones' ? 'active' : '' ?>">Pantalones</a>
                <a href="catalogo.php?categoria=Shorts" class="categoria-opcion-bar <?= $categoria_nombre === 'Shorts' ? 'active' : '' ?>">Shorts</a>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-2">
        <div class="row g-3">
            <!-- Sidebar de filtros -->
            <aside class="col-lg-2 col-md-3 col-12">
                <div class="catalogo-sidebar">
                    <!-- Filtros avanzados -->
                    <?php if (!empty($talles_disponibles) || !empty($colores_disponibles)): ?>
                    
                    <?php
                    /**
                     * Función para obtener código hexadecimal de color según nombre
                     * @param string $color_nombre Nombre del color (ej: "negro", "blanco", "azul")
                     * @return string Código hexadecimal del color
                     */
                    function obtenerColorHex($color_nombre) {
                        $colores_map = array(
                            'negro' => '#000000',
                            'blanco' => '#FFFFFF',
                            'azul' => '#0066CC',
                            'rojo' => '#CC0000',
                            'verde' => '#00CC00',
                            'amarillo' => '#FFCC00',
                            'rosa' => '#FF99CC',
                            'gris' => '#808080',
                            'beige' => '#F5F5DC',
                            'marron' => '#8B4513',
                            'marrón' => '#8B4513',
                            'naranja' => '#FF6600',
                            'violeta' => '#9966CC',
                            'morado' => '#9933CC',
                            'turquesa' => '#40E0D0',
                            'coral' => '#FF7F50',
                            'ocre' => '#CC7722',
                            'lila' => '#C8A2C8',
                            'bordo' => '#800020',
                            'burgundy' => '#800020',
                            'verde oliva' => '#808000',
                            'verde oliva' => '#808000'
                        );
                        
                        $color_lower = strtolower(trim($color_nombre));
                        return isset($colores_map[$color_lower]) ? $colores_map[$color_lower] : '#CCCCCC';
                    }
                    ?>
                    
                    <form method="GET" action="catalogo.php" id="filtros-form" class="filtros-sidebar">
                        <input type="hidden" name="categoria" value="<?= htmlspecialchars($categoria_nombre) ?>">
                        
                        <!-- Título del filtro -->
                        <div class="filtro-header">
                            <h5 class="filtro-header-titulo">
                                <i class="fas fa-filter me-2"></i>Filtrar por
                            </h5>
                        </div>
                        
                        <?php if (!empty($talles_disponibles)): ?>
                        <div class="filtro-grupo-compacto">
                            <div class="filtro-titulo">
                                <i class="fas fa-ruler-vertical me-1"></i>TALLE
                            </div>
                            <div class="filtro-opciones-compactas">
                                <?php 
                                // Ordenar talles según orden estándar (XS, S, M, L, XL, etc.)
                                $orden_talles = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
                                $talles_ordenados = [];
                                foreach ($orden_talles as $t) {
                                    if (isset($talles_disponibles[$t])) {
                                        $talles_ordenados[$t] = $talles_disponibles[$t];
                                    }
                                }
                                // Luego agregar talles no estándar que puedan existir
                                foreach ($talles_disponibles as $t => $stock) {
                                    if (!isset($talles_ordenados[$t])) {
                                        $talles_ordenados[$t] = $stock;
                                    }
                                }
                                // Renderizar cada opción de talle con su stock
                                foreach ($talles_ordenados as $talle => $stock): 
                                    $checked = in_array($talle, $talles_seleccionados) ? 'checked' : '';
                                    $active = in_array($talle, $talles_seleccionados) ? 'active' : '';
                                ?>
                                    <label class="filtro-option-checkbox <?= $active ?>">
                                        <input type="checkbox" name="talle[]" value="<?= htmlspecialchars($talle) ?>" 
                                               <?= $checked ?> 
                                               onchange="document.getElementById('filtros-form').submit();"
                                               class="filtro-checkbox-input">
                                        <span class="filtro-checkbox-custom"></span>
                                        <span class="filtro-texto-checkbox">
                                            <?= htmlspecialchars($talle) ?>
                                            <span class="filtro-count-checkbox">(<?= $stock ?>)</span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <hr class="filtro-divider">
                        <?php endif; ?>

                        <?php if (!empty($colores_disponibles)): ?>
                        <div class="filtro-grupo-compacto">
                            <div class="filtro-titulo">
                                <i class="fas fa-palette me-1"></i>COLOR
                            </div>
                            <div class="filtro-opciones-compactas">
                                <?php 
                                // Renderizar cada color disponible con su stock y círculo de color
                                foreach ($colores_disponibles as $color => $stock): 
                                    $checked = in_array($color, $colores_seleccionados) ? 'checked' : '';
                                    $active = in_array($color, $colores_seleccionados) ? 'active' : '';
                                    $color_hex = obtenerColorHex($color);
                                ?>
                                    <label class="filtro-option-checkbox color-option-checkbox <?= $active ?>">
                                        <input type="checkbox" name="color[]" value="<?= htmlspecialchars($color) ?>" 
                                               <?= $checked ?> 
                                               onchange="document.getElementById('filtros-form').submit();"
                                               class="filtro-checkbox-input">
                                        <span class="filtro-checkbox-custom"></span>
                                        <span class="filtro-texto-checkbox">
                                            <?= htmlspecialchars(ucfirst($color)) ?>
                                            <span class="filtro-count-checkbox">(<?= $stock ?>)</span>
                                        </span>
                                        <span class="filtro-color-circle" style="background-color: <?= htmlspecialchars($color_hex) ?>; border: 1px solid <?= $color_hex === '#FFFFFF' ? '#E8E8E5' : 'transparent' ?>;"></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($talles_seleccionados) || !empty($colores_seleccionados)): ?>
                        <div class="mb-2">
                            <a href="catalogo.php?categoria=<?= htmlspecialchars($categoria_nombre) ?>" class="btn-limpiar-compact">
                                <i class="fas fa-times me-1"></i>Limpiar filtros
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>
                    <?php endif; ?>
                </div>
            </aside>

            <!-- Contenido principal de productos -->
            <div class="col-lg-10 col-md-9 col-12">
                <div class="row g-3">
                    <?php if ($productos->num_rows > 0): ?>
                        <?php while ($producto = $productos->fetch_assoc()): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-6">
                                <div class="card tarjeta h-100 shadow-sm overflow-hidden position-relative">
                                    <a href="detalle-producto.php?id=<?= $producto['id_producto'] ?>" class="text-decoration-none">
                                        <?php 
                                        // Obtener imagen del producto según su color y categoría
                                        // Usar 'blanco' como color por defecto si no hay color disponible
                                        $color_actual = strtolower($producto['color'] ?? 'blanco');
                                        $imagen = obtenerMiniaturaPorColor(
                                            $producto['nombre_categoria'],
                                            $producto['genero'] ?? 'unisex',
                                            $color_actual
                                        );
                                        ?>
                                        <div class="card-img-container position-relative" style="height: 280px; overflow: hidden; background-color: #f8f9fa;">
                                            <img src="<?= htmlspecialchars($imagen) ?>" class="card-img-top w-100 h-100 img-hover" alt="<?= htmlspecialchars($producto['nombre_producto']) ?> - <?= htmlspecialchars($producto['color']) ?>" style="object-fit: contain; padding: 10px; transition: transform 0.3s ease;">
                                        </div>
                                        
                                        <div class="card-body text-center p-2" style="background-color: #ffffff;">
                                            <div class="precio-catalogo mb-1">
                                                <span class="fw-bold" style="font-size: 1.05rem; color: #212529;">$<?= number_format($producto['precio_actual'], 2) ?></span>
                                            </div>
                                            <div class="nombre-producto-catalogo">
                                                <strong class="text-dark d-block" style="font-size: 0.95rem;"><?= htmlspecialchars($producto['nombre_producto']) ?></strong>
                                                <small class="text-muted" style="font-size: 0.8rem;"><?= strtoupper(htmlspecialchars($producto['color'])) ?></small>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-5">
                            <p class="text-muted">No hay productos disponibles en esta categoría.</p>
                            <a href="catalogo.php?categoria=todos" class="btn btn-dark">Ver todos los productos</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
// Incluir footer completo (con scripts)
include 'includes/footer.php';
render_footer();
?>
