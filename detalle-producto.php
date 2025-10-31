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

require_once 'config/database.php';
require_once 'includes/image_helper.php';

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
 * Consulta principal del producto
 * Obtiene datos desde tablas Productos y Categorias (estructura de database_estructura.sql)
 */
$sql_producto = "
    SELECT 
        p.id_producto,
        p.nombre_producto,
        p.descripcion_producto,
        p.precio_actual,
        p.genero,
        p.id_categoria,
        c.nombre_categoria,
        c.descripcion_categoria
    FROM Productos p
    JOIN Categorias c ON p.id_categoria = c.id_categoria
    WHERE p.id_producto = ?
    LIMIT 1
";

// Ejecutar consulta con prepared statement para seguridad (MySQLi)
$stmt_producto = $mysqli->prepare($sql_producto);
$stmt_producto->bind_param('i', $id_producto);
$stmt_producto->execute();
$result_producto = $stmt_producto->get_result();
$producto = $result_producto->fetch_assoc();

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
 * Consulta de variantes de stock
 * Tabla: Stock_Variantes (según database_estructura.sql)
 * Obtiene talle, color y stock disponible
 */
$sql_variantes = "
    SELECT talle, color, stock
    FROM Stock_Variantes
    WHERE id_producto = ?
    ORDER BY talle, color
";

// Ejecutar consulta de variantes (MySQLi)
$stmt_variantes = $mysqli->prepare($sql_variantes);
$stmt_variantes->bind_param('i', $id_producto);
$stmt_variantes->execute();
$result_variantes = $stmt_variantes->get_result();
$variantes = [];
while ($row = $result_variantes->fetch_assoc()) {
    $variantes[] = $row;
}

/**
 * Consulta de fotos del producto
 * Tabla: Fotos_Producto (según database_estructura.sql)
 * NOTA: Ahora las imágenes se obtienen automáticamente según el color usando image_helper.php
 */
$sql_fotos = "
    SELECT foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod
    FROM Fotos_Producto
    WHERE id_producto = ?
    LIMIT 1
";

// Ejecutar consulta de fotos (para compatibilidad) - MySQLi
$stmt_fotos = $mysqli->prepare($sql_fotos);
$stmt_fotos->bind_param('i', $id_producto);
$stmt_fotos->execute();
$result_fotos = $stmt_fotos->get_result();
$fotos = $result_fotos->fetch_assoc();

// Obtener imágenes basadas en la estructura de carpetas según el color
// Primero obtenemos los colores disponibles desde el stock
$colores_stock = array_unique(array_column($variantes, 'color'));
$primer_color = !empty($colores_stock) ? strtolower($colores_stock[0]) : 'blanco';

// Obtener imágenes del primer color disponible
$imagenes_desde_carpeta = obtenerImagenesPorColor(
    $producto['nombre_categoria'],
    $producto['genero'],
    $primer_color
);

// Asignar imágenes obtenidas de las carpetas a la estructura $fotos
if (!empty($imagenes_desde_carpeta)) {
    $fotos = array(
        'foto_prod_miniatura' => !empty($imagenes_desde_carpeta) ? $imagenes_desde_carpeta[0] : null,
        'foto1_prod' => isset($imagenes_desde_carpeta[0]) ? $imagenes_desde_carpeta[0] : null,
        'foto2_prod' => isset($imagenes_desde_carpeta[1]) ? $imagenes_desde_carpeta[1] : null,
        'foto3_prod' => isset($imagenes_desde_carpeta[2]) ? $imagenes_desde_carpeta[2] : null
    );
} else {
    // Proveer valores por defecto si no hay fotos registradas
    if (!$fotos) {
        $fotos = array(
            'foto_prod_miniatura' => null,
            'foto1_prod' => null,
            'foto2_prod' => null,
            'foto3_prod' => null
        );
    }
}

// Definir todos los talles estándar que deben mostrarse siempre
$orden_tallas_estandar = ['XS', 'S', 'M', 'L', 'XL'];

// Extraer tallas que tienen stock en la BD
$tallas_con_stock = array_unique(array_column($variantes, 'talle'));

// Crear array de tallas con información de disponibilidad
$tallas_info = [];
foreach ($orden_tallas_estandar as $talla_estandar) {
    $tiene_stock = in_array($talla_estandar, $tallas_con_stock);
    
    // Verificar stock total (sumar todos los colores para este talle)
    $stock_total_talle = 0;
    if ($tiene_stock) {
        foreach ($variantes as $variante) {
            if ($variante['talle'] === $talla_estandar) {
                $stock_total_talle += (int)$variante['stock'];
            }
        }
    }
    
    $tallas_info[] = [
        'talle' => $talla_estandar,
        'tiene_stock' => $tiene_stock && $stock_total_talle > 0,
        'stock_total' => $stock_total_talle
    ];
}

// Agregar tallas no estándar que tengan stock al final
foreach ($tallas_con_stock as $talla_stock) {
    if (!in_array($talla_stock, $orden_tallas_estandar)) {
        $stock_total = 0;
        foreach ($variantes as $variante) {
            if ($variante['talle'] === $talla_stock) {
                $stock_total += (int)$variante['stock'];
            }
        }
        
        $tallas_info[] = [
            'talle' => $talla_stock,
            'tiene_stock' => $stock_total > 0,
            'stock_total' => $stock_total
        ];
    }
}

// Para compatibilidad, mantener array simple de talles disponibles
$tallas = array_column($tallas_info, 'talle');

// Extraer colores únicos disponibles
$colores = array_unique(array_column($variantes, 'color'));

/**
 * Consulta de productos relacionados
 * Muestra productos de la misma categoría (máximo 3)
 */
$sql_relacionados = "
    SELECT 
        p.id_producto,
        p.nombre_producto,
        p.precio_actual,
        fp.foto_prod_miniatura
    FROM Productos p
    LEFT JOIN Fotos_Producto fp ON p.id_producto = fp.id_producto
    WHERE p.id_categoria = ? 
    AND p.id_producto != ?
    LIMIT 3
";

// Ejecutar consulta de productos relacionados (MySQLi)
$stmt_relacionados = $mysqli->prepare($sql_relacionados);
$stmt_relacionados->bind_param('ii', $producto['id_categoria'], $id_producto);
$stmt_relacionados->execute();
$result_relacionados = $stmt_relacionados->get_result();
$productos_relacionados = [];
while ($row = $result_relacionados->fetch_assoc()) {
    $productos_relacionados[] = $row;
}

/**
 * Obtiene el stock disponible para una combinación de talla y color
 * 
 * @param array $variantes Array de variantes del producto
 * @param string $talla Talla seleccionada
 * @param string $color Color seleccionado
 * @return int Cantidad en stock (0 si no hay)
 */
function obtenerStock($variantes, $talla, $color) {
    foreach ($variantes as $variante) {
        if ($variante['talle'] === $talla && $variante['color'] === $color) {
            return $variante['stock'];
        }
    }
    return 0;
}

/**
 * Genera un array asociativo de stock indexado por talla-color
 * Facilita la consulta de stock en JavaScript
 * 
 * @param array $variantes Array de variantes desde Stock_Variantes
 * @return array Array con clave 'talle-color' y valor stock
 */
function generarArrayStock($variantes) {
    $stockArray = array();
    foreach ($variantes as $variante) {
        $clave = $variante['talle'] . '-' . $variante['color'];
        $stockArray[$clave] = $variante['stock'];
    }
    return $stockArray;
}

// Generar array de stock para uso en JavaScript
$stockVariantes = generarArrayStock($variantes);

/**
 * Genera información de stock por color para cada talle
 * Útil para tachar talles/colores sin stock según la selección
 * 
 * @param array $variantes Array de variantes desde Stock_Variantes
 * @return array Array con estructura: [talle][color] = stock
 */
function generarStockPorTalleYColor($variantes) {
    $stock_por_talle_color = [];
    foreach ($variantes as $variante) {
        $talle = $variante['talle'];
        $color = $variante['color'];
        $stock = (int)$variante['stock'];
        
        if (!isset($stock_por_talle_color[$talle])) {
            $stock_por_talle_color[$talle] = [];
        }
        
        if (!isset($stock_por_talle_color[$talle][$color])) {
            $stock_por_talle_color[$talle][$color] = 0;
        }
        
        $stock_por_talle_color[$talle][$color] += $stock;
    }
    return $stock_por_talle_color;
}

// Generar información de stock por talle y color
$stockPorTalleColor = generarStockPorTalleYColor($variantes);
?>

<?php include 'includes/header.php'; ?>

<!-- Contenido del detalle de producto -->
<main class="detalle-producto">
        <div class="container mt-2">
            <nav aria-label="breadcrumb" class="mb-2" style="font-size: 0.85rem;">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="catalogo.php?categoria=<?php echo urlencode($producto['nombre_categoria']); ?>"><?php echo htmlspecialchars($producto['nombre_categoria']); ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($producto['nombre_producto']); ?></li>
                </ol>
            </nav>

            <div class="row g-2 justify-content-center align-items-center" style="min-height: calc(100vh - 200px);">
                <!-- Galería de imágenes compacta -->
                <div class="col-lg-5 col-xl-5">
                    <div class="producto-galeria-compacta">
                        <!-- Imagen principal compacta -->
                        <div class="imagen-principal-compacta">
                            <?php
                            // Imágenes del producto
                            $imagenes = array();
                            
                            if (is_array($fotos)) {
                                if (!empty($fotos['foto1_prod'])) {
                                    $imagenes[] = $fotos['foto1_prod'];
                                }
                                if (!empty($fotos['foto2_prod'])) {
                                    $imagenes[] = $fotos['foto2_prod'];
                                }
                                if (!empty($fotos['foto3_prod'])) {
                                    $imagenes[] = $fotos['foto3_prod'];
                                }
                                if (empty($imagenes) && !empty($fotos['foto_prod_miniatura'])) {
                                    $imagenes[] = $fotos['foto_prod_miniatura'];
                                }
                            }
                            
                            if (empty($imagenes)) {
                                $imagenes = ['imagenes/imagen.png'];
                            }
                            ?>
                            <img id="imagenPrincipalLimpia" 
                                 src="<?php echo htmlspecialchars($imagenes[0]); ?>" 
                                 class="img-producto-principal" 
                                 alt="<?php echo htmlspecialchars($producto['nombre_producto']); ?>">
                            
                            <!-- Miniaturas compactas debajo -->
                            <?php if (count($imagenes) > 1): ?>
                            <div class="thumbnails-compactos mt-2">
                                <?php foreach ($imagenes as $index => $imagen): ?>
                                <div class="thumbnail-compacto <?php echo $index === 0 ? 'active' : ''; ?>" onclick="cambiarImagenPrincipal(<?php echo $index; ?>)">
                                    <img src="<?php echo htmlspecialchars($imagen); ?>" 
                                         alt="Miniatura <?php echo $index + 1; ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
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

                        <div class="mb-2">
                            <label class="form-label-compacto fw-bold mb-1">TALLE:</label>
                            <div class="tallas-selector-compacto">
                                <?php 
                                // Preseleccionar primer talle con stock, o M si existe y tiene stock
                                $talle_predeterminado = null;
                                foreach ($tallas_info as $info) {
                                    if ($info['tiene_stock']) {
                                        if ($info['talle'] === 'M') {
                                            $talle_predeterminado = 'M';
                                            break;
                                        }
                                        if ($talle_predeterminado === null) {
                                            $talle_predeterminado = $info['talle'];
                                        }
                                    }
                                }
                                
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
                            <small class="text-muted d-block mb-2">
                                <i class="fas fa-info-circle me-1"></i>
                                <a href="#" data-bs-toggle="modal" data-bs-target="#guiaTallasModal">Ver guía de talles</a>
                            </small>
                            <!-- Indicador de stock -->
                            <div id="stock-indicador" class="stock-indicador-compacto mb-2">
                                <small class="text-muted">
                                    <i class="fas fa-warehouse me-1"></i>
                                    <span id="stock-texto">Selecciona talle y color para ver disponibilidad</span>
                                </small>
                            </div>
                        </div>

                        <?php if (!empty($colores)): ?>
                        <div class="mb-2">
                            <label class="form-label-compacto fw-bold mb-1">COLOR:</label>
                            <div class="colores-selector-compacto">
                                <?php foreach ($colores as $index => $color): ?>
                                <input type="radio" 
                                       class="btn-check color-radio" 
                                       name="color" 
                                       id="color-<?php echo strtolower($color); ?>" 
                                       value="<?php echo htmlspecialchars($color); ?>" 
                                       <?php echo $index === 0 ? 'checked' : ''; ?>
                                       data-color="<?php echo htmlspecialchars($color); ?>">
                                <label class="btn-color-compacto color-label" 
                                       for="color-<?php echo strtolower($color); ?>"
                                       data-color="<?php echo htmlspecialchars($color); ?>">
                                    <?php echo strtoupper(htmlspecialchars($color)); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Cantidad compacta -->
                        <div class="mb-3">
                            <label class="form-label-compacto fw-bold mb-1">Cantidad:</label>
                            <div class="cantidad-selector-compacto d-flex align-items-center">
                                <button type="button" class="btn-cantidad-compacto" onclick="cambiarCantidad(-1)">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" class="form-control-cantidad-compacto" id="cantidad" value="1" min="1" max="10">
                                <button type="button" class="btn-cantidad-compacto" onclick="cambiarCantidad(1)">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Formulario para agregar al carrito -->
                        <form method="POST" action="carrito.php" id="formCarrito" class="mt-2">
                            <input type="hidden" name="accion" value="agregar">
                            <input type="hidden" name="id_producto" value="<?php echo $id_producto; ?>">
                            <input type="hidden" name="talla" id="talla_hidden">
                            <input type="hidden" name="color" id="color_hidden">
                            <input type="hidden" name="cantidad" id="cantidad_hidden">
                            
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-comprar-compacto" onclick="comprarAhora()">
                                    <i class="fas fa-credit-card me-2"></i>Comprar Ahora
                                </button>
                                <button type="button" class="btn btn-carrito-compacto" onclick="agregarAlCarrito()">
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
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6><i class="fas fa-shipping-fast text-primary me-2"></i>Envío</h6>
                                                <ul class="list-unstyled small">
                                                    <li>• Entrega en 2-5 días hábiles</li>
                                                    <li>• Seguimiento incluido</li>
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

            <!-- Productos relacionados -->
            <?php if (!empty($productos_relacionados)): ?>
            <div class="productos-relacionados mt-5">
                <h3 class="mb-4">También te puede interesar</h3>
                <div class="row">
                    <?php foreach ($productos_relacionados as $relacionado): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card tarjeta h-100">
                            <img src="<?php echo htmlspecialchars($relacionado['foto_prod_miniatura'] ?: 'imagenes/imagen.png'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($relacionado['nombre_producto']); ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($relacionado['nombre_producto']); ?></h5>
                                <p class="precio-actual">$<?php echo number_format($relacionado['precio_actual'], 2); ?></p>
                                <a href="detalle-producto.php?id=<?php echo $relacionado['id_producto']; ?>" class="btn boton-tarjeta btn-sm">Ver Detalles</a>
                            </div>
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
                                <tr>
                                    <td><strong>XS</strong></td>
                                    <td>82-86</td>
                                    <td>62-66</td>
                                    <td>62</td>
                                </tr>
                                <tr>
                                    <td><strong>S</strong></td>
                                    <td>86-90</td>
                                    <td>66-70</td>
                                    <td>64</td>
                                </tr>
                                <tr>
                                    <td><strong>M</strong></td>
                                    <td>90-94</td>
                                    <td>70-74</td>
                                    <td>66</td>
                                </tr>
                                <tr>
                                    <td><strong>L</strong></td>
                                    <td>94-98</td>
                                    <td>74-78</td>
                                    <td>68</td>
                                </tr>
                                <tr>
                                    <td><strong>XL</strong></td>
                                    <td>98-102</td>
                                    <td>78-82</td>
                                    <td>70</td>
                                </tr>
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

    <footer class="footer-completo">
                <div class="container">
            <div class="row py-5">
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">SEDA Y LINO</h5>
                    <p class="footer-texto">Elegancia que viste tus momentos. Prendas únicas de seda y lino con calidad artesanal.</p>
                    <div class="footer-redes mt-3">
                        <a href="https://www.facebook.com/?locale=es_LA" target="_blank" rel="noopener noreferrer" class="footer-red-social me-2" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" class="footer-red-social me-2" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://x.com/?lang=es" target="_blank" rel="noopener noreferrer" class="footer-red-social" title="X (Twitter)">
                            <i class="fab fa-x-twitter"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Navegación</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-2">
                            <a href="index.php" class="footer-link">
                                <i class="fas fa-home me-2"></i>Inicio
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="nosotros.php" class="footer-link">
                                <i class="fas fa-users me-2"></i>Nosotros
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=todos" class="footer-link">
                                <i class="fas fa-shopping-bag me-2"></i>Catálogo
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="index.php#contacto" class="footer-link">
                                <i class="fas fa-envelope me-2"></i>Contacto
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Productos</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Camisas" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Camisas
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Blusas" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Blusas
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Pantalones" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Pantalones
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Shorts" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Shorts
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Contacto</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-3">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            <span class="footer-texto-small">Buenos Aires, Argentina</span>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-phone me-2"></i>
                            <a href="tel:+541112345678" class="footer-link">+54 11 1234-5678</a>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-envelope me-2"></i>
                            <a href="mailto:info@sedaylino.com" class="footer-link">info@sedaylino.com</a>
                        </li>
                    </ul>
                </div>
            </div>

            <hr class="footer-divider">

            <div class="row py-3">
                <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                    <p class="footer-copyright mb-0">
                        <i class="fas fa-copyright me-1"></i> 2025 Seda y Lino. Todos los derechos reservados
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="terminos.php" class="footer-link-small me-3">Términos y Condiciones</a>
                    <a href="privacidad.php" class="footer-link-small">Política de Privacidad</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        /* ========================================================================
         * ESTILOS COMPACTOS PARA DETALLE DE PRODUCTO - UX/UI Mejorada
         * ======================================================================== */
        
        /* Contenedor principal compacto */
        .producto-galeria-compacta {
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        /* Imagen principal compacta */
        .imagen-principal-compacta {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
            justify-content: center;
        }
        
        .img-producto-principal {
            width: 100%;
            max-height: 60vh;
            min-height: 300px;
            object-fit: contain;
            border-radius: 8px;
            background: #f8f9fa;
            padding: 10px;
        }
        
        /* Miniaturas compactas */
        .thumbnails-compactos {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .thumbnail-compacto {
            width: 60px;
            height: 60px;
            border: 2px solid transparent;
            border-radius: 6px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #fff;
        }
        
        .thumbnail-compacto:hover {
            border-color: #333;
            transform: scale(1.05);
        }
        
        .thumbnail-compacto.active {
            border-color: #333;
            box-shadow: 0 0 0 2px rgba(0,0,0,0.1);
        }
        
        .thumbnail-compacto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Información del producto compacta */
        .producto-info-compacta {
            height: 100%;
            display: flex;
            flex-direction: column;
            padding: 15px;
            background: #fff;
            border-radius: 8px;
        }
        
        .producto-titulo-compacto {
            font-size: 1.75rem;
            font-weight: 600;
            color: #333;
            line-height: 1.3;
            margin-bottom: 10px;
        }
        
        .producto-precio-compacto {
            margin-bottom: 15px;
        }
        
        .precio-actual-compacto {
            font-size: 2rem;
            font-weight: 700;
            color: #4A9FD6;
        }
        
        /* Selectores compactos */
        .form-label-compacto {
            font-size: 0.9rem;
            margin-bottom: 6px;
            display: block;
        }
        
        .tallas-selector-compacto,
        .colores-selector-compacto {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 8px;
        }
        
        .btn-talla-compacto,
        .btn-color-compacto {
            padding: 6px 14px;
            font-size: 0.85rem;
            border: 1.5px solid #dee2e6;
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-talla-compacto:hover,
        .btn-color-compacto:hover {
            border-color: #333;
            background: #f8f9fa;
        }
        
        input[type="radio"]:checked + .btn-talla-compacto,
        input[type="radio"]:checked + .btn-color-compacto {
            background: #333;
            color: #fff;
            border-color: #333;
        }
        
        /* Talle sin stock - tachado */
        .btn-talla-compacto.talla-sin-stock {
            position: relative;
            opacity: 0.7;
            text-decoration: line-through;
            text-decoration-color: #000;
        }
        
        /* Mantener estilo cuando está seleccionado incluso si no tiene stock */
        input[type="radio"]:checked + .btn-talla-compacto.talla-sin-stock {
            background: #333;
            color: #fff;
            border-color: #333;
            opacity: 1;
            text-decoration: line-through;
            text-decoration-color: #fff;
        }
        
        /* Color sin stock - tachado */
        .btn-color-compacto.color-sin-stock {
            position: relative;
            opacity: 0.7;
            text-decoration: line-through;
            text-decoration-color: #000;
        }
        
        /* Mantener estilo cuando está seleccionado incluso si no tiene stock */
        input[type="radio"]:checked + .btn-color-compacto.color-sin-stock {
            background: #333;
            color: #fff;
            border-color: #333;
            opacity: 1;
            text-decoration: line-through;
            text-decoration-color: #fff;
        }
        
        /* Indicador de stock compacto */
        .stock-indicador-compacto {
            padding: 8px 12px;
            border-radius: 4px;
            background: #f8f9fa;
            border-left: 3px solid #6c757d;
            transition: all 0.2s ease;
        }
        
        .stock-indicador-compacto .text-success {
            color: #4A9FD6 !important;
        }
        
        .stock-indicador-compacto .text-danger {
            color: #dc3545 !important;
        }
        
        .stock-indicador-compacto .text-warning {
            color: #ffc107 !important;
        }
        
        /* Selector de cantidad compacto */
        .cantidad-selector-compacto {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-cantidad-compacto {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1.5px solid #dee2e6;
            background: #fff;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-cantidad-compacto:hover {
            background: #f8f9fa;
            border-color: #333;
        }
        
        .form-control-cantidad-compacto {
            width: 60px;
            text-align: center;
            border: 1.5px solid #dee2e6;
            border-radius: 4px;
            padding: 6px;
            font-size: 0.9rem;
        }
        
        /* Botones de acción compactos */
        .btn-comprar-compacto {
            background: #4A9FD6;
            color: #fff;
            border: none;
            padding: 12px 20px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
        }
        
        .btn-comprar-compacto:hover {
            background: #3A8FC6;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(74, 159, 214, 0.3);
        }
        
        .btn-carrito-compacto {
            background: #333;
            color: #fff;
            border: none;
            padding: 12px 20px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
        }
        
        .btn-carrito-compacto:hover {
            background: #000;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        
        /* Responsive para pantallas pequeñas */
        @media (max-width: 768px) {
            .img-producto-principal {
                max-height: 50vh;
            }
            
            .producto-titulo-compacto {
                font-size: 1.4rem;
            }
            
            .precio-actual-compacto {
                font-size: 1.6rem;
            }
            
            .detalle-producto .container {
                padding: 10px;
            }
        }
        
        /* Asegurar que todo quepa en viewport */
        @media (max-height: 900px) {
            .img-producto-principal {
                max-height: 45vh;
            }
            
            .producto-info-compacta {
                padding: 10px;
            }
            
            .producto-titulo-compacto {
                font-size: 1.5rem;
                margin-bottom: 8px;
            }
            
            .precio-actual-compacto {
                font-size: 1.75rem;
            }
            
            .breadcrumb {
                padding: 0.35rem 0;
                margin-bottom: 8px;
            }
        }
        
        /* Ocultar breadcrumb en pantallas muy pequeñas para optimizar espacio */
        @media (max-height: 750px) {
            .breadcrumb {
                display: none;
            }
            
            .detalle-producto .container {
                margin-top: 0 !important;
            }
            
            .img-producto-principal {
                max-height: 40vh;
            }
        }
        
        /* Optimización para tablets y pantallas medianas */
        @media (min-width: 769px) and (max-width: 1024px) {
            .img-producto-principal {
                max-height: 55vh;
            }
        }
    </style>
    
    <script>
        // Solo JavaScript esencial para UX del navegador (no replicable en PHP)
        const stockVariantes = <?php echo json_encode($stockVariantes); ?>;
        const stockPorTalleColor = <?php echo json_encode($stockPorTalleColor); ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            const formCarrito = document.getElementById('formCarrito');
            
            // Thumbnails del carrusel - Solo JS (Bootstrap)
            document.querySelectorAll('.producto-thumbnail').forEach(function(thumb, i) {
                thumb.style.cursor = 'pointer'; // Asegurar cursor de mano
                thumb.addEventListener('click', function() {
                    const carousel = bootstrap.Carousel.getOrCreateInstance(document.getElementById('carouselProducto'));
                    carousel.to(i);
                    document.querySelectorAll('.producto-thumbnail').forEach(function(t, idx) {
                        t.classList.toggle('active', idx === i);
                    });
                });
            });
            
            // Controles de cantidad - Solo JS (UX inmediata)
            document.querySelectorAll('.btn[onclick*="cambiarCantidad"]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const input = document.getElementById('cantidad');
                    const delta = this.querySelector('i').classList.contains('fa-plus') ? 1 : -1;
                    let val = parseInt(input.value) + delta;
                    if (val < 1) val = 1;
                    if (val > 10) val = 10;
                    input.value = val;
                });
            });
            
            // Actualizar talles tachados según el color seleccionado
            function actualizarTallesPorColor() {
                const color = document.querySelector('input[name="color"]:checked');
                
                if (!color) {
                    // Si no hay color seleccionado, mostrar todos los talles normales
                    document.querySelectorAll('.talla-label').forEach(function(label) {
                        label.classList.remove('talla-sin-stock');
                    });
                    return;
                }
                
                const colorSeleccionado = color.getAttribute('data-color');
                
                // Actualizar cada talle según el stock disponible para este color
                document.querySelectorAll('.talla-label').forEach(function(label) {
                    const talleValue = label.getAttribute('data-talle');
                    
                    // Verificar stock para esta combinación talle-color
                    if (stockPorTalleColor[talleValue] && stockPorTalleColor[talleValue][colorSeleccionado]) {
                        const stock = stockPorTalleColor[talleValue][colorSeleccionado];
                        if (stock > 0) {
                            label.classList.remove('talla-sin-stock');
                        } else {
                            label.classList.add('talla-sin-stock');
                        }
                    } else {
                        // No hay stock para esta combinación
                        label.classList.add('talla-sin-stock');
                    }
                });
            }
            
            // Actualizar colores tachados según el talle seleccionado
            function actualizarColoresPorTalle() {
                const talla = document.querySelector('input[name="talla"]:checked');
                
                if (!talla) {
                    // Si no hay talle seleccionado, mostrar todos los colores normales
                    document.querySelectorAll('.color-label').forEach(function(label) {
                        label.classList.remove('color-sin-stock');
                    });
                    return;
                }
                
                const talleSeleccionado = talla.getAttribute('data-talle');
                
                // Actualizar cada color según el stock disponible para este talle
                document.querySelectorAll('.color-label').forEach(function(label) {
                    const colorValue = label.getAttribute('data-color');
                    
                    // Verificar stock para esta combinación talle-color
                    if (stockPorTalleColor[talleSeleccionado] && stockPorTalleColor[talleSeleccionado][colorValue]) {
                        const stock = stockPorTalleColor[talleSeleccionado][colorValue];
                        if (stock > 0) {
                            label.classList.remove('color-sin-stock');
                        } else {
                            label.classList.add('color-sin-stock');
                        }
                    } else {
                        // No hay stock para esta combinación
                        label.classList.add('color-sin-stock');
                    }
                });
            }
            
            // Actualizar indicador de stock - Solo JS (feedback inmediato)
            function actualizarStock() {
                const talla = document.querySelector('input[name="talla"]:checked');
                const color = document.querySelector('input[name="color"]:checked');
                const stockEl = document.getElementById('stock-texto');
                const stockContainer = document.getElementById('stock-indicador');
                
                // Actualizar opciones tachadas según la selección
                if (color) {
                    actualizarTallesPorColor();
                }
                if (talla) {
                    actualizarColoresPorTalle();
                }
                
                if (stockEl && stockContainer) {
                    if (talla && color) {
                        const stock = stockVariantes[talla.value + '-' + color.value] || 0;
                        
                        if (stock > 0) {
                            stockEl.textContent = stock + ' unidades disponibles';
                            stockEl.className = 'text-success';
                            stockContainer.style.borderLeftColor = '#4A9FD6';
                            stockContainer.style.background = '#E3F2F8';
                        } else {
                            stockEl.textContent = 'Sin stock disponible para esta combinación';
                            stockEl.className = 'text-danger';
                            stockContainer.style.borderLeftColor = '#dc3545';
                            stockContainer.style.background = '#f8d7da';
                        }
                    } else if (talla) {
                        stockEl.textContent = 'Selecciona un color para ver disponibilidad';
                        stockEl.className = 'text-warning';
                        stockContainer.style.borderLeftColor = '#ffc107';
                        stockContainer.style.background = '#fff3cd';
                    } else if (color) {
                        stockEl.textContent = 'Selecciona un talle para ver disponibilidad';
                        stockEl.className = 'text-warning';
                        stockContainer.style.borderLeftColor = '#ffc107';
                        stockContainer.style.background = '#fff3cd';
                    } else {
                        stockEl.textContent = 'Selecciona talle y color para ver disponibilidad';
                        stockEl.className = 'text-muted';
                        stockContainer.style.borderLeftColor = '#6c757d';
                        stockContainer.style.background = '#f8f9fa';
                    }
                }
            }
            
            // Escuchar cambios en color para actualizar talles tachados
            document.querySelectorAll('input[name="color"]').forEach(function(input) {
                input.addEventListener('change', function() {
                    actualizarTallesPorColor();
                    actualizarStock();
                });
            });
            
            // Escuchar cambios en talle para actualizar colores tachados
            document.querySelectorAll('input[name="talla"]').forEach(function(input) {
                input.addEventListener('change', function() {
                    actualizarColoresPorTalle();
                    actualizarStock();
                });
            });
            
            
            document.querySelectorAll('input[name="color"]').forEach(function(radio) {
                radio.addEventListener('click', function(e) {
                    const talla = document.querySelector('input[name="talla"]:checked');
                    if (talla) {
                        const talleValue = talla.getAttribute('data-talle');
                        const colorValue = this.getAttribute('data-color');
                        const stock = stockPorTalleColor[talleValue] && stockPorTalleColor[talleValue][colorValue] 
                            ? stockPorTalleColor[talleValue][colorValue] 
                            : 0;
                        
                        if (stock <= 0) {
                            e.preventDefault();
                            this.checked = false;
                            return false;
                        }
                    }
                });
            });
            
            // Inicializar al cargar la página
            actualizarStock();
            
            // Validación de formulario carrito - Solo JS (UX antes de enviar)
            if (formCarrito) {
                formCarrito.addEventListener('submit', function(e) {
                    const talla = document.querySelector('input[name="talla"]:checked');
                    if (!talla) {
                        e.preventDefault();
                        alert('Por favor selecciona un talle');
                        return false;
                    }
                    
                    const color = document.querySelector('input[name="color"]:checked');
                    document.getElementById('talla_hidden').value = talla.value;
                    document.getElementById('color_hidden').value = color ? color.value : '';
                    document.getElementById('cantidad_hidden').value = document.getElementById('cantidad').value;
                });
            }
        });
        
        // Funciones globales para UX mejorada
        const imagenesProducto = <?php echo json_encode($imagenes); ?>;
        
        /**
         * Cambia la imagen principal cuando se hace clic en una miniatura
         * @param {number} index - Índice de la imagen a mostrar
         */
        function cambiarImagenPrincipal(index) {
            // Actualizar imagen principal
            const imagenPrincipal = document.getElementById('imagenPrincipalLimpia');
            
            if (imagenPrincipal && imagenesProducto[index]) {
                imagenPrincipal.src = imagenesProducto[index];
                
                // Actualizar thumbnails activos (compatible con ambos estilos)
                document.querySelectorAll('.thumbnail-compacto, .thumbnail-mini').forEach((item, i) => {
                    item.classList.toggle('active', i === index);
                });
            }
        }
        
        function mostrarZoom() {
            const detalleZoom = document.getElementById('detalleZoom');
            if (detalleZoom) {
                detalleZoom.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    detalleZoom.style.transform = 'scale(1)';
                }, 200);
            }
        }
        
        function cambiarImagen(i) { cambiarImagenPrincipal(i); }
        function cambiarCantidad(d) { 
            const input = document.getElementById('cantidad');
            let val = parseInt(input.value) + d;
            input.value = val < 1 ? 1 : (val > 10 ? 10 : val);
        }
        
        /**
         * Agrega producto al carrito
         * Valida talla/color y llena campos hidden antes de enviar
         */
        function agregarAlCarrito() {
            const talla = document.querySelector('input[name="talla"]:checked');
            const color = document.querySelector('input[name="color"]:checked');
            
            if (!talla) {
                alert('Por favor selecciona una talla');
                return false;
            }
            
            if (!color) {
                alert('Por favor selecciona un color');
                return false;
            }
            
            // Llenar campos hidden
            document.getElementById('talla_hidden').value = talla.value;
            document.getElementById('color_hidden').value = color.value;
            document.getElementById('cantidad_hidden').value = document.getElementById('cantidad').value;
            
            // Enviar formulario
            document.getElementById('formCarrito').submit();
        }
        
        function comprarAhora() { 
            agregarAlCarrito();
        }
    </script>

<?php include 'includes/footer.php'; render_footer(); ?>