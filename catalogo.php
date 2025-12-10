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
// Incluir header completo (head + navigation)
include 'includes/header.php';

// Conectar a la base de datos y helpers
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/image_helper.php';
require_once __DIR__ . '/includes/talles_config.php';
require_once __DIR__ . '/includes/queries/producto_queries.php';
require_once __DIR__ . '/includes/queries/categoria_queries.php';
require_once __DIR__ . '/includes/catalogo_helper.php';

// Obtener categorías activas y filtrar las que tienen productos
$categorias_activas = obtenerCategoriasActivasConProductos($mysqli);

// Procesar parámetros de filtros (GET)
$filtros_procesados = procesarParametrosFiltros($_GET);
$categoria_nombre = $filtros_procesados['categoria_nombre'];
$talles_seleccionados = $filtros_procesados['talles'];
$generos_seleccionados = $filtros_procesados['generos'];
$colores_seleccionados = $filtros_procesados['colores'];

// Descripciones personalizadas por categoría para SEO y UX
$descripciones = generarDescripcionesCategorias($categorias_activas);

// Validar categoría solicitada y obtener ID
$categoria_id = validarCategoriaSolicitada($mysqli, $categoria_nombre);

// Obtener talles, géneros y colores disponibles usando funciones centralizadas
// IMPORTANTE: Si se busca una categoría específica y no existe, los filtros deben mostrar (0)
if ($categoria_nombre === 'todos' || $categoria_id !== null) {
    // Solo obtener filtros si es 'todos' o si se encontró la categoría
    $talles_disponibles = obtenerTallesDisponibles($mysqli, $categoria_id);
    $generos_disponibles = obtenerGenerosDisponiblesStock($mysqli, $categoria_id);
    $colores_disponibles = obtenerColoresDisponiblesStock($mysqli, $categoria_id);
} else {
    // Si la categoría no existe, mostrar filtros con valores en 0
    // Obtener talles estándar y géneros válidos para mostrar con (0)
    $talles_estandar = obtenerTallesEstandar();
    $talles_disponibles = [];
    foreach ($talles_estandar as $talle) {
        $talles_disponibles[$talle] = 0; // Mostrar todos los talles con stock 0
    }
    
    $generos_validos = ['hombre', 'mujer', 'unisex'];
    $generos_disponibles = [];
    foreach ($generos_validos as $genero) {
        $generos_disponibles[$genero] = 0; // Mostrar todos los géneros con stock 0
    }
    
    // Colores: no hay lista predefinida, así que dejamos vacío
    $colores_disponibles = [];
}

// Obtener productos filtrados usando función centralizada
// IMPORTANTE: Si se busca una categoría específica y no existe, mostrar vacío (no todos los productos)
$productos = [];
if ($categoria_nombre === 'todos' || $categoria_id !== null) {
    // Solo buscar productos si es 'todos' o si se encontró la categoría
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
}
// Si $categoria_nombre !== 'todos' y $categoria_id === null, $productos ya está vacío []
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
                    <?php 
                    // Comparación case-insensitive para consistencia con la búsqueda en BD
                    $nombre_cat_normalizado = strtolower(trim($categoria['nombre_categoria']));
                    $categoria_nombre_normalizado = ($categoria_nombre !== 'todos') ? strtolower(trim($categoria_nombre)) : 'todos';
                    $es_activa = ($categoria_nombre_normalizado === $nombre_cat_normalizado);
                    ?>
                    <a href="catalogo.php?categoria=<?= urlencode($categoria['nombre_categoria']) ?>" 
                       class="categoria-opcion-bar <?= $es_activa ? 'active' : '' ?>">
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
