<?php
/**
 * ========================================================================
 * DETALLE DE PRODUCTO - Tienda Seda y Lino
 * ========================================================================
 * Muestra información detallada de un producto individual
 * - Visualiza datos completos del producto (nombre, precio, descripción)
 * - Galería de imágenes del producto
 * - Selector de talle y color con validación de stock
 * - Indicador de stock disponible en tiempo real
 * - Botones para agregar al carrito o comprar ahora
 * - Productos relacionados de la misma categoría
 * 
 * Funciones principales:
 * - Carga datos del producto desde BD por ID
 * - Muestra talles y colores disponibles
 * - Valida stock y tacha opciones sin stock
 * - Actualiza indicador de stock según selección (JavaScript)
 * 
 * Variables principales:
 * - $id_producto: ID del producto desde URL (?id=numero)
 * - $producto: Datos del producto (nombre, precio, descripción)
 * - $tallas: Array con talles disponibles
 * - $colores: Array con colores disponibles
 * - $variantes: Stock por combinación talle-color
 * 
 * Tablas utilizadas: Productos, Categorias, Fotos_Producto, Stock_Variantes
 * ========================================================================
 */

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/image_helper.php';
require_once __DIR__ . '/includes/talles_config.php';
require_once __DIR__ . '/includes/queries/stock_queries.php';  // Debe cargarse PRIMERO (producto_queries lo necesita)
require_once __DIR__ . '/includes/queries/producto_queries.php';
require_once __DIR__ . '/includes/producto_functions.php';

// Configurar título de la página
$titulo_pagina = 'Detalle de Producto';

// Validar y sanitizar ID de producto desde URL
$id_producto = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validación de ID de producto
if ($id_producto <= 0) {
    http_response_code(400);
    header('Location: catalogo.php?categoria=todos');
    exit;
}

/**
 * Obtener datos del producto usando función centralizada
 * Consulta desde includes/queries/producto_queries.php
 */
$producto = obtenerProductoPorId($mysqli, $id_producto);

// Validar existencia del producto
if (!$producto) {
    http_response_code(404);
    // Incluir header para mostrar mensaje amigable
    $titulo_pagina = 'Producto no encontrado';
    include 'includes/header.php';
    ?>
    <main class="container mt-5">
        <div class="alert alert-warning text-center">
            <h4><i class="fas fa-exclamation-triangle me-2"></i>Producto no encontrado</h4>
            <p>El producto que buscas no existe o ha sido eliminado.</p>
            <a href="catalogo.php?categoria=todos" class="btn btn-primary">
                <i class="fas fa-shopping-bag me-2"></i>Ver catálogo completo
            </a>
        </div>
    </main>
    <?php
    include 'includes/footer.php';
    render_footer();
    exit;
}

/**
 * Obtener todas las variantes del producto actual para mostrar opciones disponibles
 * y calcular stock. Solo incluye variantes de este producto específico.
 * Consulta desde includes/queries/producto_queries.php
 */
$variantes = obtenerTodasVariantesProducto($mysqli, $id_producto);

// Normalizar colores de las variantes del producto actual para consistencia
foreach ($variantes as &$variante) {
    if (!empty($variante['color'])) {
        $variante['color'] = normalizeColor($variante['color']);
    }
}
unset($variante); // Liberar referencia

/**
 * Obtener variantes del grupo de productos (mismo nombre, categoría, género)
 * para mostrar todas las opciones de colores disponibles en la UI.
 * Solo talles estándar. NO usar para calcular stock.
 * Consulta desde includes/queries/producto_queries.php
 */
// Obtener talles estándar para filtrar
$talles_estandar = obtenerTallesEstandar();

// Obtener todas las variantes del grupo de productos usando función centralizada
// Esto se usa solo para mostrar opciones de colores en la UI, NO para calcular stock
$todas_variantes_completas = obtenerTodasVariantesGrupoProducto(
    $mysqli,
    $producto['nombre_producto'],
    $producto['id_categoria'],
    $producto['genero'],
    $talles_estandar
);

/**
 * Obtener todas las fotos del producto actual usando función centralizada
 * Consulta desde includes/queries/producto_queries.php
 */
$todas_fotos = obtenerTodasFotosProducto($mysqli, $id_producto);

/**
 * Obtener fotos del grupo de productos (mismo nombre, categoría, género)
 * para mostrar imágenes de todos los colores disponibles.
 * Consulta desde includes/queries/producto_queries.php
 */
// Obtener fotos del grupo de productos usando función centralizada
$fotos_grupo = obtenerFotosGrupoProducto(
    $mysqli,
    $producto['nombre_producto'],
    $producto['id_categoria'],
    $producto['genero'],
    $id_producto,
    $todas_fotos,
    !empty($todas_variantes_completas) ? $todas_variantes_completas : $variantes
);

// Actualizar arrays finales
$fotos_generales = $fotos_grupo['generales'];
$fotos_por_color = $fotos_grupo['por_color'];

// Obtener colores disponibles desde las variantes del GRUPO (para mostrar todas las opciones en UI)
// Normalizar colores para formato consistente (primera letra mayúscula, resto minúscula)
$variantes_para_colores = !empty($todas_variantes_completas) ? $todas_variantes_completas : $variantes;
$colores = array_unique(array_column($variantes_para_colores, 'color'));
// Normalizar todos los colores al mismo formato
$colores = array_unique(array_map('normalizeColor', $colores));
sort($colores);
$primer_color = !empty($colores) ? $colores[0] : null;

// Preparar imágenes iniciales para mostrar (del primer color o generales)
// Verificar que los archivos existan antes de agregarlos
$imagenes = [];
if ($primer_color && !empty($fotos_por_color[$primer_color])) {
    // Usar fotos del primer color disponible - usar el primer elemento del array (el de mayor prioridad)
    // El primer elemento es el producto que realmente tiene este color
    $fotos_color = $fotos_por_color[$primer_color][0];
    if (!empty($fotos_color['foto1_prod']) && verificarArchivoExiste($fotos_color['foto1_prod'])) {
        $imagenes[] = $fotos_color['foto1_prod'];
    }
    if (!empty($fotos_color['foto2_prod']) && verificarArchivoExiste($fotos_color['foto2_prod'])) {
        $imagenes[] = $fotos_color['foto2_prod'];
    }
    
    // Si el producto prioritario no tiene fotos, usar las del siguiente como fallback
    if (empty($imagenes) && count($fotos_por_color[$primer_color]) > 1) {
        for ($i = 1; $i < count($fotos_por_color[$primer_color]); $i++) {
            $foto_fallback = $fotos_por_color[$primer_color][$i];
            if (!empty($foto_fallback['foto1_prod']) && verificarArchivoExiste($foto_fallback['foto1_prod'])) {
                $imagenes[] = $foto_fallback['foto1_prod'];
            }
            if (!empty($foto_fallback['foto2_prod']) && verificarArchivoExiste($foto_fallback['foto2_prod'])) {
                $imagenes[] = $foto_fallback['foto2_prod'];
            }
            // Solo usar el primer fallback que tenga fotos
            if (!empty($imagenes)) {
                break;
            }
        }
    }
}

// Si no hay fotos por color, usar fotos generales
if (empty($imagenes) && $fotos_generales) {
    if (!empty($fotos_generales['foto3_prod']) && verificarArchivoExiste($fotos_generales['foto3_prod'])) {
        $imagenes[] = $fotos_generales['foto3_prod'];
    }
    if (!empty($fotos_generales['foto_prod_miniatura']) && verificarArchivoExiste($fotos_generales['foto_prod_miniatura'])) {
        $imagenes[] = $fotos_generales['foto_prod_miniatura'];
    }
}

// Si aún no hay imágenes, usar imagen por defecto
if (empty($imagenes)) {
    $imagenes = ['imagenes/imagen.png'];
}

// Preparar estructura de fotos para compatibilidad con código existente
$fotos = array(
    'foto_prod_miniatura' => !empty($fotos_generales['foto_prod_miniatura']) ? $fotos_generales['foto_prod_miniatura'] : null,
    'foto1_prod' => !empty($imagenes[0]) ? $imagenes[0] : null,
    'foto2_prod' => !empty($imagenes[1]) ? $imagenes[1] : null,
    'foto3_prod' => !empty($fotos_generales['foto3_prod']) ? $fotos_generales['foto3_prod'] : null
);

// Definir todos los talles estándar que deben mostrarse siempre - Origen centralizado
$orden_tallas_estandar = obtenerTallesEstandar();

// Extraer TODAS las tallas que existen en las variantes (incluso sin stock)
$tallas_en_variantes = array_unique(array_column($variantes, 'talle'));

// Calcular información de tallas disponibles con stock
// Utiliza función refactorizada que elimina complejidad O(n*m)
$tallas_info = prepareTallesInfo($variantes, $orden_tallas_estandar);



/**
 * Obtener variantes de color del mismo producto base (mismo nombre, categoría, género)
 * Consulta desde includes/queries/producto_queries.php
 */
$variantes_color = [];
if ($producto) {
    $variantes_color = obtenerVariantesColorMismoProducto(
        $mysqli,
        $id_producto,
        $producto['nombre_producto'],
        $producto['id_categoria'],
        $producto['genero']
    );
}

/**
 * Obtener productos relacionados usando función centralizada
 * Consulta desde includes/queries/producto_queries.php
 * Muestra productos de la misma categoría (máximo 3)
 */
$productos_relacionados = [];
if ($producto) {
    $productos_relacionados = obtenerProductosRelacionados($mysqli, $producto['id_categoria'], $id_producto, 3);
}

// Procesar variantes para calcular stock disponible (restando reservas)
// Esto asegura que el frontend muestre el mismo stock que valida el backend
$variantes_con_stock_disponible = [];
foreach ($variantes as $variante) {
    $stock_bruto = (int)$variante['stock'];
    $id_variante = (int)$variante['id_variante'];
    
    // Calcular stock disponible
    // NOTA: Stock_Variantes.stock ya incluye el descuento de reservas (es ATP),
    // por lo que no debemos restar las reservas nuevamente.
    $stock_disponible = max(0, $stock_bruto);
    
    // Crear nueva variante con stock disponible
    $variante_con_stock_disponible = $variante;
    $variante_con_stock_disponible['stock'] = $stock_disponible;
    $variantes_con_stock_disponible[] = $variante_con_stock_disponible;
}

// Generar array de stock para uso en JavaScript (usando stock disponible, no bruto)
$stockVariantes = generarArrayStock($variantes_con_stock_disponible);

// Generar información de stock por talle y color (usando stock disponible, no bruto)
$stockPorTalleColor = generarStockPorTalleYColor($variantes_con_stock_disponible);
?>

<?php include 'includes/header.php'; ?>

<!-- Contenido del detalle de producto -->
<main class="detalle-producto">
        <div class="container mt-2">
            <nav aria-label="breadcrumb" class="mb-3 font-size-085">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                    <?php if (!empty($producto['nombre_categoria'])): ?>
                    <li class="breadcrumb-item"><a href="catalogo.php?categoria=<?php echo urlencode($producto['nombre_categoria']); ?>"><?php echo htmlspecialchars($producto['nombre_categoria']); ?></a></li>
                    <?php else: ?>
                    <li class="breadcrumb-item"><a href="catalogo.php?categoria=todos">Productos</a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($producto['nombre_producto'] ?? 'Producto'); ?></li>
                </ol>
            </nav>

            <div class="row g-2 justify-content-center align-items-start detalle-producto-row">
                <!-- Galería de imágenes compacta -->
                <div class="col-lg-5 col-xl-5">
                    <div class="producto-galeria-compacta">
                        <!-- Imagen principal compacta -->
                        <div class="imagen-principal-compacta">
                            <?php
                            // Las imágenes iniciales ya están preparadas arriba en $imagenes
                            // Usar esas imágenes que corresponden al primer color disponible
                            ?>
                            <img id="imagenPrincipalLimpia" 
                                 src="<?php echo htmlspecialchars($imagenes[0]); ?>" 
                                 class="img-producto-principal" 
                                 alt="<?php echo htmlspecialchars($producto['nombre_producto']); ?>"
                                 title="Clic para ver siguiente imagen"
                                 style="cursor: pointer;">
                        </div>
                        
                        <!-- Miniaturas compactas debajo -->
                        <div id="thumbnailsContainer" class="thumbnails-compactos mt-2">
                            <?php foreach ($imagenes as $index => $imagen): ?>
                            <div class="thumbnail-compacto <?php echo $index === 0 ? 'active' : ''; ?>" data-image-index="<?php echo $index; ?>">
                                <img src="<?php echo htmlspecialchars($imagen); ?>" 
                                     alt="Miniatura <?php echo $index + 1; ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Información del producto compacta -->
                <div class="col-lg-5 col-xl-5">
                    <div class="producto-info-compacta">
                        <h1 class="producto-titulo-compacto mb-2"><?php echo htmlspecialchars($producto['nombre_producto']); ?></h1>
                        
                        <!-- Precio destacado -->
                        <div class="producto-precio-compacto mb-3">
                            <span class="precio-actual-compacto">$<?php echo number_format($producto['precio_actual'], 2); ?></span>
                        </div>

                        <!-- TALLE y COLOR en la misma fila -->
                        <div class="row g-2 mb-2">
                            <!-- TALLE -->
                            <div class="col-auto">
                                <label class="form-label-compacto fw-bold mb-1">TALLE:</label>
                                <div class="tallas-selector-compacto">
                                    <?php
                                    // Preseleccionar talle: con stock si hay, sin stock si no hay opción
                                    // Utiliza función refactorizada que implementa la lógica de prioridad
                                    $talle_predeterminado = selectDefaultTalle($tallas_info, true)
                                        ?? selectDefaultTalle($tallas_info, false);

                                    foreach ($tallas_info as $index => $info_talla): 
                                        $talla = $info_talla['talle'];
                                        $tiene_stock = $info_talla['tiene_stock'];
                                        $checked = ($talla === $talle_predeterminado) ? 'checked' : '';
                                    ?>
                                    <input type="radio" 
                                           class="btn-check talla-radio" 
                                           name="talla" 
                                           id="talla-<?php echo $talla; ?>" 
                                           value="<?php echo htmlspecialchars($talla); ?>" 
                                           <?php echo $checked; ?>
                                           data-talle="<?php echo htmlspecialchars($talla); ?>"
                                           data-tiene-stock="<?php echo $tiene_stock ? '1' : '0'; ?>">
                                    <label class="btn-talla-compacto talla-label" 
                                           for="talla-<?php echo $talla; ?>"
                                           data-talle="<?php echo htmlspecialchars($talla); ?>">
                                        <?php echo htmlspecialchars($talla); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                <small class="text-muted d-block mb-0">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#guiaTallasModal">Ver guía de talles</a>
                                </small>
                            </div>
                            
                            <!-- COLOR -->
                            <?php if (!empty($colores)): ?>
                            <div class="col-auto">
                                <label class="form-label-compacto fw-bold mb-1">COLOR:</label>
                                <div class="colores-selector-compacto">
                                    <?php foreach ($colores as $index => $color): ?>
                                    <?php
                                    // Asegurar que el color esté normalizado (primera letra mayúscula, resto minúscula)
                                    $color_normalizado_input = normalizeColor($color);
                                    ?>
                                    <input type="radio" 
                                           class="btn-check color-radio" 
                                           name="color" 
                                           id="color-<?php echo strtolower($color_normalizado_input); ?>" 
                                           value="<?php echo htmlspecialchars($color_normalizado_input); ?>" 
                                           <?php echo $index === 0 ? 'checked' : ''; ?>
                                           data-color="<?php echo htmlspecialchars($color_normalizado_input); ?>">
                                    <label class="btn-color-compacto color-label" 
                                           for="color-<?php echo strtolower($color_normalizado_input); ?>"
                                           data-color="<?php echo htmlspecialchars($color_normalizado_input); ?>">
                                        <?php echo strtoupper(htmlspecialchars($color_normalizado_input)); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                        </div>
                        
                        <!-- Indicador de stock - debajo de ambos selectores -->
                        <div id="stock-indicador" class="stock-indicador-compacto mb-2">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                <span id="stock-texto">Selecciona talle y color</span>
                            </small>
                        </div>

                        <!-- Cantidad -->
                        <div class="mb-3">
                            <label class="form-label-compacto fw-bold mb-1">Cantidad:</label>
                            <div class="cantidad-selector-compacto d-flex align-items-center">
                                <button type="button" class="btn-cantidad-compacto" data-action="decrement">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" class="form-control-cantidad-compacto" id="cantidad" value="1" min="1">
                                <button type="button" class="btn-cantidad-compacto" data-action="increment">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Formulario para agregar al carrito -->
                        <form method="POST" action="carrito.php" id="formCarrito" class="mb-2">
                            <input type="hidden" name="accion" value="agregar">
                            <input type="hidden" name="id_producto" value="<?php echo $id_producto; ?>">
                            <input type="hidden" name="talla" id="talla_hidden">
                            <input type="hidden" name="color" id="color_hidden">
                            <input type="hidden" name="cantidad" id="cantidad_hidden">
                            
                            <div class="d-grid gap-2">
                                <button type="button" id="btn-comprar-ahora" class="btn btn-comprar-compacto">
                                    <i class="fas fa-credit-card me-2"></i>Comprar Ahora
                                </button>
                                <button type="button" id="btn-agregar-carrito" class="btn btn-carrito-compacto">
                                    <i class="fas fa-shopping-cart me-2"></i>Agregar al Carrito
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>

            <!-- Banner de información detallada - Ancho completo -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="producto-info-adicional-banner">
                        <div class="accordion" id="accordionProducto">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDescripcion">
                                        <i class="fas fa-info-circle me-2"></i>Descripción Detallada
                                    </button>
                                </h2>
                                <div id="collapseDescripcion" class="accordion-collapse collapse show" data-bs-parent="#accordionProducto">
                                    <div class="accordion-body">
                                        <p><?php echo htmlspecialchars($producto['descripcion_producto']); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCaracteristicas">
                                        <i class="fas fa-star me-2"></i>Características
                                    </button>
                                </h2>
                                <div id="collapseCaracteristicas" class="accordion-collapse collapse" data-bs-parent="#accordionProducto">
                                    <div class="accordion-body">
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success me-2"></i>Transpirable y fresco</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Corte elegante</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Acabados de alta calidad</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Resistente y duradero</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Ecológico y sostenible</li>
                                        </ul>
                                        <p><strong>Material:</strong> Lino 100% Natural</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCuidado">
                                        <i class="fas fa-tint me-2"></i>Cuidado y Mantenimiento
                                    </button>
                                </h2>
                                <div id="collapseCuidado" class="accordion-collapse collapse" data-bs-parent="#accordionProducto">
                                    <div class="accordion-body">
                                        <p><strong>Recomendaciones de lavado:</strong></p>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-hand-paper text-info me-2"></i>Lavado a mano recomendado</li>
                                            <li><i class="fas fa-thermometer-half text-info me-2"></i>Agua fría (máximo 30°C)</li>
                                            <li><i class="fas fa-iron text-warning me-2"></i>Planchar a temperatura media</li>
                                            <li><i class="fas fa-ban text-danger me-2"></i>No usar secadora</li>
                                            <li><i class="fas fa-cloud-sun text-info me-2"></i>Secar a la sombra</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEnvio">
                                        <i class="fas fa-shipping-fast me-2"></i>Envío y Devoluciones
                                    </button>
                                </h2>
                                <div id="collapseEnvio" class="accordion-collapse collapse" data-bs-parent="#accordionProducto">
                                    <div class="accordion-body">
                                        <!-- Información de envío gratis -->
                                        <div class="alert alert-info alert-info-custom mb-3">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-truck fa-2x text-primary me-3"></i>
                                                <div>
                                                    <h6 class="mb-1 fw-bold text-primary">ENVIOS GRATIS</h6>
                                                    <p class="mb-0 small">En compras superiores a $80,000 en CABA y GBA</p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6><i class="fas fa-shipping-fast text-primary me-2"></i>Envío</h6>
                                                <ul class="list-unstyled small">
                                                    <li>• Entrega en 2-5 días hábiles</li>
                                                    <li>• Seguimiento incluido</li>
                                                    <li>• Envío gratis en compras superiores a $80,000 en CABA y GBA</li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <h6><i class="fas fa-undo text-success me-2"></i>Devoluciones</h6>
                                                <ul class="list-unstyled small">
                                                    <li>• 30 días para devolver</li>
                                                    <li>• Producto sin usar</li>
                                                    <li>• Etiquetas originales</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Variantes de color del mismo producto -->
            <?php if (!empty($variantes_color)): ?>
            <div class="variantes-color-producto mt-5">
                <h3 class="mb-4">
                    <i class="fas fa-palette me-2"></i>Otros colores disponibles
                </h3>
                <div class="row g-3">
                    <?php foreach ($variantes_color as $variante): ?>
                        <?php 
                        // Obtener imagen del producto según su color
                        $color_variante = strtolower($variante['color'] ?? 'blanco');
                        $imagen_variante = obtenerMiniaturaPorColor(
                            $variante['nombre_categoria'],
                            $variante['genero'] ?? 'unisex',
                            $color_variante
                        );
                        ?>
                        <div class="col-md-6 col-lg-4 col-xl-3">
                            <div class="card tarjeta h-100 shadow-sm">
                                <a href="detalle-producto.php?id=<?= $variante['id_producto'] ?>" class="text-decoration-none">
                                    <div class="card-img-container card-img-container-variante position-relative">
                                        <img src="<?= htmlspecialchars($imagen_variante) ?>" 
                                             class="card-img-top w-100 h-100 img-hover" 
                                             alt="<?= htmlspecialchars($variante['nombre_producto']) ?> - <?= htmlspecialchars($variante['color']) ?>">
                                    </div>
                                    <div class="card-body card-body-white text-center p-2">
                                        <div class="precio-catalogo mb-1">
                                            <span class="fw-bold font-size-105 text-very-dark">$<?= number_format($variante['precio_actual'], 2) ?></span>
                                        </div>
                                        <div class="nombre-producto-catalogo">
                                            <strong class="text-dark d-block font-size-095"><?= htmlspecialchars($variante['nombre_producto']) ?></strong>
                                            <small class="text-muted font-size-08"><?= strtoupper(htmlspecialchars($variante['color'])) ?></small>
                                        </div>
                                        <?php if ($variante['total_stock'] > 0): ?>
                                            <small class="text-success d-block mt-1">
                                                <i class="fas fa-check-circle me-1"></i>En stock
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted d-block mt-1">
                                                <i class="fas fa-info-circle me-1"></i>Ver disponibilidad
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            

        </div>
    </main>

    <!-- Modal Guía de Tallas -->
    <div class="modal fade" id="guiaTallasModal" tabindex="-1" aria-labelledby="guiaTallasModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="guiaTallasModalLabel">Guía de TALLES - <?php echo htmlspecialchars($producto['nombre_categoria']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>TALLE</th>
                                    <th>Busto (cm)</th>
                                    <th>Cintura (cm)</th>
                                    <th>Largo (cm)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Guía de talles según configuración centralizada
                                $talles_guia = [
                                    'S' => ['busto' => '86-90', 'cintura' => '66-70', 'largo' => '64'],
                                    'M' => ['busto' => '90-94', 'cintura' => '70-74', 'largo' => '66'],
                                    'L' => ['busto' => '94-98', 'cintura' => '74-78', 'largo' => '68'],
                                    'XL' => ['busto' => '98-102', 'cintura' => '78-82', 'largo' => '70']
                                ];
                                
                                foreach (obtenerTallesEstandar() as $talle): 
                                    if (isset($talles_guia[$talle])):
                                ?>
                                <tr>
                                    <td><strong><?= $talle ?></strong></td>
                                    <td><?= $talles_guia[$talle]['busto'] ?></td>
                                    <td><?= $talles_guia[$talle]['cintura'] ?></td>
                                    <td><?= $talles_guia[$talle]['largo'] ?></td>
                                </tr>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Las medidas son aproximadas y pueden variar según el modelo. Para mayor precisión, recomendamos contactar a nuestro equipo de atención al cliente.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <a href="index.php#contacto" class="btn boton-tarjeta">Contactar Asesor</a>
                </div>
            </div>
        </div>
    </div>

<script src="js/detalle-producto-utils.js"></script>
<script src="js/detalle-producto.js"></script>
<?php include 'includes/detalle_producto_data.php'; ?>

<?php include 'includes/footer.php'; render_footer(); ?>