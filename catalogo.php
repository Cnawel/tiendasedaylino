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
require_once __DIR__ . '/includes/talles_config.php';
require_once __DIR__ . '/includes/queries/producto_queries.php';
require_once __DIR__ . '/includes/queries/categoria_queries.php';

// Obtener categorías activas desde la base de datos y eliminar duplicados
$categorias_activas_temp = obtenerCategorias($mysqli);
// Eliminar duplicados por nombre de categoría (mantener solo la primera ocurrencia)
// Verificación adicional: solo incluir categorías con activo = 1
$categorias_activas = [];
$nombres_vistos = [];
foreach ($categorias_activas_temp as $cat) {
    // Verificar que la categoría esté activa (doble verificación)
    if (isset($cat['activo']) && $cat['activo'] != 1) {
        continue; // Saltar categorías inactivas
    }
    $nombre_normalizado = strtolower(trim($cat['nombre_categoria']));
    if (!in_array($nombre_normalizado, $nombres_vistos)) {
        $categorias_activas[] = $cat;
        $nombres_vistos[] = $nombre_normalizado;
    }
}

// Filtrar categorías: mostrar las que tienen productos activos (incluyendo stock 0)
if (!empty($categorias_activas)) {
    // Obtener IDs de categorías que tienen productos activos (incluyendo stock 0)
    $categorias_con_productos_ids = obtenerCategoriasConProductosStock($mysqli);
    
    // Filtrar categorías activas: mantener las que tienen productos activos (stock >= 0)
    $categorias_activas = array_filter($categorias_activas, function($cat) use ($categorias_con_productos_ids) {
        return in_array((int)$cat['id_categoria'], $categorias_con_productos_ids);
    });
    
    // Re-indexar el array después de filtrar
    $categorias_activas = array_values($categorias_activas);
}

// Obtener categoría seleccionada desde URL, por defecto 'todos'
// Limpiar y normalizar el nombre de la categoría
$categoria_nombre = isset($_GET['categoria']) ? trim($_GET['categoria']) : 'todos';
// ID de categoría para filtrado en BD (se obtiene más abajo si es necesario)
$categoria_id = null;

// Descripciones personalizadas por categoría para SEO y UX
// Construir dinámicamente desde las categorías activas
$descripciones = [
    'todos' => 'Descubre nuestra colección completa de productos elegantes'
];
foreach ($categorias_activas as $cat) {
    $descripciones[$cat['nombre_categoria']] = !empty($cat['descripcion_categoria']) 
        ? $cat['descripcion_categoria'] 
        : 'Productos de ' . $cat['nombre_categoria'];
}

// Obtener talles seleccionados desde filtros GET - Solo talles estándar (S, M, L, XL)
$talles_seleccionados = [];
if (isset($_GET['talle'])) {
    $talles_temp = is_array($_GET['talle']) ? $_GET['talle'] : [$_GET['talle']];
    $talles_validos = obtenerTallesEstandar();
    // Filtrar solo talles estándar válidos
    foreach ($talles_temp as $talle) {
        if (in_array($talle, $talles_validos)) {
            $talles_seleccionados[] = $talle;
        }
    }
}

// Obtener géneros seleccionados desde filtros GET - Solo géneros válidos
$generos_seleccionados = [];
if (isset($_GET['genero'])) {
    $generos_temp = is_array($_GET['genero']) ? $_GET['genero'] : [$_GET['genero']];
    $generos_validos = ['hombre', 'mujer', 'unisex'];
    // Filtrar solo géneros válidos
    foreach ($generos_temp as $genero) {
        $genero_normalizado = strtolower(trim($genero));
        if (in_array($genero_normalizado, $generos_validos) && !in_array($genero_normalizado, $generos_seleccionados)) {
            $generos_seleccionados[] = $genero_normalizado;
        }
    }
}

// Obtener colores seleccionados desde filtros GET - Normalizar para consistencia
$colores_seleccionados = [];
if (isset($_GET['color'])) {
    $colores_temp = is_array($_GET['color']) ? $_GET['color'] : [$_GET['color']];
    // Normalizar colores (primera letra mayúscula, resto minúscula)
    foreach ($colores_temp as $color) {
        $color_normalizado = ucfirst(strtolower(trim($color)));
        if (!in_array($color_normalizado, $colores_seleccionados)) {
            $colores_seleccionados[] = $color_normalizado;
        }
    }
}

// Si la categoría no es 'todos', obtener su ID desde la base de datos usando función centralizada
$categoria_id = null;
if ($categoria_nombre !== 'todos') {
    $categoria_id = obtenerCategoriaIdPorNombre($mysqli, $categoria_nombre);
    // Si la categoría no existe, establecer como null para mostrar todos
    if (!$categoria_id) {
        $categoria_id = null;
    }
}

// Obtener talles, géneros y colores disponibles usando funciones centralizadas
$talles_disponibles = obtenerTallesDisponibles($mysqli, $categoria_id);
$generos_disponibles = obtenerGenerosDisponiblesStock($mysqli, $categoria_id);
$colores_disponibles = obtenerColoresDisponiblesStock($mysqli, $categoria_id);

// Obtener productos filtrados usando función centralizada
$filtros = [];
if ($categoria_id !== null) {
    $filtros['categoria_id'] = $categoria_id;
}
if (!empty($talles_seleccionados)) {
    $filtros['talles'] = $talles_seleccionados;
}
if (!empty($generos_seleccionados)) {
    $filtros['generos'] = $generos_seleccionados;
}
if (!empty($colores_seleccionados)) {
    $filtros['colores'] = $colores_seleccionados;
}

$productos = obtenerProductosFiltradosCatalogo($mysqli, $filtros);
?>

<main class="productos">
    <!-- Barra de categorías debajo del navbar -->
    <div class="categorias-bar">
        <div class="container-fluid">
            <div class="categoria-opciones-bar">
                <!-- Opción "Todos" siempre visible -->
                <a href="catalogo.php?categoria=todos" class="categoria-opcion-bar <?= $categoria_nombre === 'todos' ? 'active' : '' ?>">Todos</a>
                
                <!-- Mostrar solo categorías activas (activo = 1) -->
                <?php foreach ($categorias_activas as $categoria): ?>
                    <a href="catalogo.php?categoria=<?= urlencode($categoria['nombre_categoria']) ?>" 
                       class="categoria-opcion-bar <?= $categoria_nombre === $categoria['nombre_categoria'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($categoria['nombre_categoria']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-2">
        <div class="row g-3">
            <!-- Sidebar de filtros -->
            <aside class="col-lg-2 col-md-3 col-12">
                <div class="catalogo-sidebar">
                    <!-- Filtros avanzados -->
                    <?php if (!empty($talles_disponibles) || !empty($generos_disponibles) || !empty($colores_disponibles)): ?>
                    
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
                                // Ordenar talles según orden estándar - Solo S, M, L, XL
                                $orden_talles = obtenerTallesEstandar();
                                $talles_ordenados = [];
                                foreach ($orden_talles as $t) {
                                    if (isset($talles_disponibles[$t])) {
                                        $talles_ordenados[$t] = $talles_disponibles[$t];
                                    }
                                }
                                // Renderizar cada opción de talle con su stock (solo estándar)
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

                        <?php if (!empty($generos_disponibles)): ?>
                        <div class="filtro-grupo-compacto">
                            <div class="filtro-titulo">
                                <i class="fas fa-venus-mars me-1"></i>GÉNERO
                            </div>
                            <div class="filtro-opciones-compactas">
                                <?php 
                                // Mapeo de géneros para mostrar
                                $generos_nombres = [
                                    'hombre' => 'Hombre',
                                    'mujer' => 'Mujer',
                                    'unisex' => 'Unisex'
                                ];
                                
                                // Orden predefinido: Hombre, Mujer, Unisex
                                $orden_generos = ['hombre', 'mujer', 'unisex'];
                                
                                // Renderizar cada género disponible con su stock
                                foreach ($orden_generos as $genero_key): 
                                    if (!isset($generos_disponibles[$genero_key])) {
                                        continue;
                                    }
                                    $stock = $generos_disponibles[$genero_key];
                                    $checked = in_array($genero_key, $generos_seleccionados) ? 'checked' : '';
                                    $active = in_array($genero_key, $generos_seleccionados) ? 'active' : '';
                                ?>
                                    <label class="filtro-option-checkbox <?= $active ?>">
                                        <input type="checkbox" name="genero[]" value="<?= htmlspecialchars($genero_key) ?>" 
                                               <?= $checked ?> 
                                               onchange="document.getElementById('filtros-form').submit();"
                                               class="filtro-checkbox-input">
                                        <span class="filtro-checkbox-custom"></span>
                                        <span class="filtro-texto-checkbox">
                                            <?= htmlspecialchars($generos_nombres[$genero_key]) ?>
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
                                        <span class="filtro-color-circle" style="background-color: #CCCCCC; border: 1px solid #E8E8E5;"></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($talles_seleccionados) || !empty($generos_seleccionados) || !empty($colores_seleccionados)): ?>
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
                    <?php if (!empty($productos)): ?>
                        <?php foreach ($productos as $producto): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-6">
                                <div class="card tarjeta h-100 shadow-sm overflow-hidden position-relative">
                                    <a href="detalle-producto.php?id=<?= $producto['id_producto'] ?>" class="text-decoration-none">
                                        <?php 
                                        // Obtener imagen del producto usando función centralizada
                                        // La función obtenerImagenProducto() aplica la lógica de prioridad:
                                        // 1. foto_prod_miniatura de la BD (si existe el archivo)
                                        // 2. obtenerMiniaturaPorColor() como fallback (si hay categoría, género y color)
                                        // 3. imagen por defecto 'imagenes/imagen.png'
                                        $imagen = obtenerImagenProducto(
                                            $producto['foto_prod_miniatura'] ?? null,
                                            $producto['nombre_categoria'] ?? null,
                                            $producto['genero'] ?? null,
                                            $producto['color'] ?? null
                                        );
                                        ?>
                                        <div class="card-img-container position-relative catalogo-card-img-container">
                                            <img src="<?= htmlspecialchars($imagen) ?>" class="card-img-top w-100 h-100 img-hover catalogo-card-img" alt="<?= htmlspecialchars($producto['nombre_producto']) ?> - <?= htmlspecialchars($producto['color']) ?>">
                                        </div>
                                        
                                        <div class="card-body text-center p-2 catalogo-card-body">
                                            <div class="precio-catalogo mb-1">
                                                <span class="fw-bold catalogo-precio">$<?= number_format($producto['precio_actual'], 2) ?></span>
                                            </div>
                                            <div class="nombre-producto-catalogo">
                                                <strong class="text-dark d-block catalogo-nombre-producto"><?= htmlspecialchars($producto['nombre_producto']) ?></strong>
                                                <?php 
                                                // Normalizar color para mostrar (primera letra mayúscula, resto minúscula)
                                                $color_mostrar = !empty($producto['color']) ? ucfirst(strtolower(trim($producto['color']))) : '';
                                                ?>
                                                <small class="text-muted catalogo-color-texto"><?= strtoupper(htmlspecialchars($color_mostrar)) ?></small>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
