<?php
/**
 * ========================================================================
 * DETALLE DE PRODUCTO - Tienda Seda y Lino
 * ========================================================================
 * Muestra información detallada de un producto individual
 * - Datos del producto desde tabla Productos
 * - Fotos desde tabla Fotos_Producto
 * - Variantes de stock desde tabla Stock_Variantes
 * - Productos relacionados de la misma categoría
 * 
 * @author Tienda Seda y Lino
 * @version 1.0
 */

session_start();

require_once 'config/database.php';

// Validar y sanitizar ID de producto desde URL
$id_producto = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validación de ID de producto
if ($id_producto <= 0) {
    http_response_code(400);
    die("ID de producto inválido. Debe ser un número entero positivo.");
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
    WHERE p.id_producto = :id_producto
";

// Ejecutar consulta con prepared statement para seguridad
$stmt_producto = $pdo->prepare($sql_producto);
$stmt_producto->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
$stmt_producto->execute();
$producto = $stmt_producto->fetch(PDO::FETCH_ASSOC);

// Validar existencia del producto
if (!$producto) {
    http_response_code(404);
    die("Producto no encontrado. El ID especificado no existe en la base de datos.");
}

/**
 * Consulta de fotos del producto
 * Tabla: Fotos_Producto (según database_estructura.sql)
 */
$sql_fotos = "
    SELECT foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod
    FROM Fotos_Producto
    WHERE id_producto = :id_producto
";

// Ejecutar consulta de fotos
$stmt_fotos = $pdo->prepare($sql_fotos);
$stmt_fotos->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
$stmt_fotos->execute();
$fotos = $stmt_fotos->fetch(PDO::FETCH_ASSOC);

// Proveer valores por defecto si no hay fotos registradas
if (!$fotos) {
    $fotos = array(
        'foto_prod_miniatura' => null,
        'foto1_prod' => null,
        'foto2_prod' => null,
        'foto3_prod' => null
    );
}

/**
 * Consulta de variantes de stock
 * Tabla: Stock_Variantes (según database_estructura.sql)
 * Obtiene talle, color y stock disponible
 */
$sql_variantes = "
    SELECT talle, color, stock
    FROM Stock_Variantes
    WHERE id_producto = :id_producto
    ORDER BY talle, color
";

// Ejecutar consulta de variantes
$stmt_variantes = $pdo->prepare($sql_variantes);
$stmt_variantes->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
$stmt_variantes->execute();
$variantes = $stmt_variantes->fetchAll(PDO::FETCH_ASSOC);

// Extraer tallas únicas y ordenarlas
$tallas = array_unique(array_column($variantes, 'talle'));
$orden_tallas = ['XS', 'S', 'M', 'L', 'XL']; // Orden estándar de talles
$tallas_ordenadas = [];

// Primero agregar tallas en orden estándar
foreach ($orden_tallas as $talla_orden) {
    if (in_array($talla_orden, $tallas)) {
        $tallas_ordenadas[] = $talla_orden;
    }
}

// Agregar tallas no estándar al final
foreach ($tallas as $talla) {
    if (!in_array($talla, $tallas_ordenadas)) {
        $tallas_ordenadas[] = $talla;
    }
}
$tallas = $tallas_ordenadas;

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
    WHERE p.id_categoria = :id_categoria 
    AND p.id_producto != :id_producto
    LIMIT 3
";

// Ejecutar consulta de productos relacionados
$stmt_relacionados = $pdo->prepare($sql_relacionados);
$stmt_relacionados->bindParam(':id_categoria', $producto['id_categoria'], PDO::PARAM_INT);
$stmt_relacionados->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
$stmt_relacionados->execute();
$productos_relacionados = $stmt_relacionados->fetchAll(PDO::FETCH_ASSOC);

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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($producto['nombre_producto']); ?> - Seda y Lino</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <header>
        <nav class="navbar navbar-expand-lg bg-body-tertiary">
            <div class="container-fluid">
                <a class="navbar-brand nombre-tienda" href="index.php">SEDA Y LINO</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                     <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                    <ul class="navbar-nav lista-nav">
                        <li class="nav-item">
                          <a class="nav-link link-tienda" href="index.php">INICIO</a>
                        </li>
                        <li class="nav-item">
                          <a class="nav-link link-tienda" href="nosotros.php">NOSOTROS</a>
                        </li>
                        <li class="nav-item">
                          <a class="nav-link link-tienda" href="index.php#productos">PRODUCTOS</a>
                        </li>
                        <li class="nav-item">
                          <a class="nav-link link-tienda" href="index.php#contacto">CONTACTO</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="carrito.php" title="Carrito">
                                <i class="fas fa-shopping-cart fa-lg"></i>
                                <?php 
                                $num_items_carrito = isset($_SESSION['carrito']) ? count($_SESSION['carrito']) : 0;
                                if ($num_items_carrito > 0): 
                                ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $num_items_carrito; ?>
                                </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <?php if (isset($_SESSION['id_usuario'])): ?>
                                <a class="nav-link" href="perfil.php" title="Mi Perfil">
                                    <img src="iconos/avatar-usuario.png" alt="icono de avatar de usuario">
                                </a>
                            <?php else: ?>
                            <a class="nav-link" href="login.php" title="Iniciar Sesión">
                                    <img src="iconos/avatar-usuario.png" alt="icono de avatar de usuario">
                            </a>
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="detalle-producto">
        <div class="container mt-4">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="catalogo.php?categoria=<?php echo urlencode($producto['nombre_categoria']); ?>"><?php echo htmlspecialchars($producto['nombre_categoria']); ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($producto['nombre_producto']); ?></li>
                </ol>
            </nav>

            <div class="row g-4 justify-content-center">
                <!-- Galería de imágenes profesional -->
                <div class="col-lg-5 col-xl-5 mb-4">
                    <div class="producto-galeria-profesional h-100">
                        <div class="row g-3">
                            <!-- Thumbnails verticales izquierda -->
                            <div class="col-md-2">
                                <div class="thumbnails-vertical-limpio">
                                    <?php
                                    // Imágenes del producto
                                    $imagenes = array();
                                    
                                    // Verificar que $fotos sea un array válido
                                    if (is_array($fotos)) {
                                        // Agregar imágenes en orden de prioridad
                                        if (!empty($fotos['foto1_prod'])) {
                                            $imagenes[] = $fotos['foto1_prod'];
                                        }
                                        if (!empty($fotos['foto2_prod'])) {
                                            $imagenes[] = $fotos['foto2_prod'];
                                        }
                                        if (!empty($fotos['foto3_prod'])) {
                                            $imagenes[] = $fotos['foto3_prod'];
                                        }
                                        
                                        // Si no hay imágenes específicas, usar la miniatura
                                        if (empty($imagenes) && !empty($fotos['foto_prod_miniatura'])) {
                                            $imagenes[] = $fotos['foto_prod_miniatura'];
                                        }
                                    }
                                    
                                    // Imagen por defecto si no hay ninguna
                                    if (empty($imagenes)) {
                                        $imagenes = ['imagenes/imagen.png'];
                                    }
                                    
                                    foreach ($imagenes as $index => $imagen): 
                                    ?>
                                    <div class="thumbnail-mini <?php echo $index === 0 ? 'active' : ''; ?>" onclick="cambiarImagenPrincipal(<?php echo $index; ?>)">
                                        <img src="<?php echo htmlspecialchars($imagen); ?>" 
                                             class="img-thumbnail-mini" 
                                             alt="<?php echo htmlspecialchars($producto['nombre_producto']); ?> - Imagen <?php echo $index + 1; ?>">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Imagen principal -->
                            <div class="col-md-10">
                                <div class="imagen-principal-limpia">
                                    <img id="imagenPrincipalLimpia" 
                                         src="<?php echo htmlspecialchars($imagenes[0]); ?>" 
                                         class="img-principal" 
                                         alt="<?php echo htmlspecialchars($producto['nombre_producto']); ?>">
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>

                <!-- Información del producto -->
                <div class="col-lg-5 col-xl-5 mb-4">
                    <div class="producto-info h-100 d-flex flex-column">
                        <h1 class="producto-titulo mb-3"><?php echo htmlspecialchars($producto['nombre_producto']); ?></h1>
                        
                        <!-- Precio -->
                        <div class="producto-precio mb-4">
                            <span class="precio-actual">$<?php echo number_format($producto['precio_actual'], 2); ?></span>
                        </div>

                        <!-- Descripción corta -->
                        <p class="producto-descripcion-corta mb-4"><?php echo htmlspecialchars($producto['descripcion_producto']); ?></p>

                        <?php if (!empty($tallas)): ?>
                        <div class="mb-4">
                            <label class="form-label fw-bold">TALLE:</label>
                            <div class="tallas-selector">
                                <?php 
                                // Preseleccionar talle M si existe, o el primer talle disponible
                                $talle_predeterminado = in_array('M', $tallas) ? 'M' : (isset($tallas[0]) ? $tallas[0] : null);
                                foreach ($tallas as $index => $talla): 
                                    $checked = ($talla === $talle_predeterminado) ? 'checked' : '';
                                ?>
                                <input type="radio" class="btn-check" name="talla" id="talla-<?php echo $talla; ?>" value="<?php echo htmlspecialchars($talla); ?>" <?php echo $checked; ?>>
                                <label class="btn btn-outline-secondary me-2 mb-2" for="talla-<?php echo $talla; ?>"><?php echo htmlspecialchars($talla); ?></label>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <a href="#" data-bs-toggle="modal" data-bs-target="#guiaTallasModal">Ver guía de talles</a>
                            </small>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($colores)): ?>
                        <div class="mb-4">
                            <label class="form-label fw-bold">COLOR:</label>
                            <div class="colores-selector">
                                <?php foreach ($colores as $index => $color): ?>
                                <input type="radio" class="btn-check" name="color" id="color-<?php echo strtolower($color); ?>" value="<?php echo htmlspecialchars($color); ?>" <?php echo $index === 0 ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-secondary me-2 mb-2" for="color-<?php echo strtolower($color); ?>"><?php echo strtoupper(htmlspecialchars($color)); ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Cantidad -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Cantidad:</label>
                            <div class="cantidad-selector d-flex align-items-center">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cambiarCantidad(-1)">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" class="form-control mx-2 text-center cantidad-input" id="cantidad" value="1" min="1" max="10">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cambiarCantidad(1)">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>


                        <!-- Formulario para agregar al carrito -->
                        <form method="POST" action="carrito.php" id="formCarrito" class="mt-auto">
                            <input type="hidden" name="accion" value="agregar">
                            <input type="hidden" name="id_producto" value="<?php echo $id_producto; ?>">
                            <input type="hidden" name="talla" id="talla_hidden">
                            <input type="hidden" name="color" id="color_hidden">
                            <input type="hidden" name="cantidad" id="cantidad_hidden">
                            
                            <div class="d-grid gap-2">
                                <button type="button" class="btn boton-tarjeta btn-lg" onclick="agregarAlCarrito()">
                                    <i class="fas fa-shopping-cart me-2"></i>Agregar al Carrito
                                </button>
                                <button type="button" class="btn btn-success btn-lg" onclick="comprarAhora()">
                                    <i class="fas fa-credit-card me-2"></i>Comprar Ahora
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
    
    <script>
        // Solo JavaScript esencial para UX del navegador (no replicable en PHP)
        const stockVariantes = <?php echo json_encode($stockVariantes); ?>;
        
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
            
            // Actualizar indicador de stock - Solo JS (feedback inmediato)
            function actualizarStock() {
                const talla = document.querySelector('input[name="talla"]:checked');
                const color = document.querySelector('input[name="color"]:checked');
                const stockEl = document.getElementById('stock-texto');
                const stockContainer = stockEl ? stockEl.parentElement : null;
                
                if (stockEl && stockContainer) {
                    if (talla && color) {
                        const stock = stockVariantes[talla.value + '-' + color.value] || 0;
                        stockEl.textContent = stock > 0 ? stock + ' unidades disponibles' : 'Sin stock disponible';
                        
                        // Actualizar clase del contenedor para el nuevo estilo
                        if (stock > 0) {
                            stockContainer.className = 'stock-info-mejorado';
                            stockEl.className = 'text-success';
                        } else {
                            stockContainer.className = 'stock-info-mejorado';
                            stockEl.className = 'text-danger';
                        }
                    } else if (talla) {
                        stockEl.textContent = 'Selecciona un color';
                        stockContainer.className = 'stock-info-mejorado';
                        stockEl.className = 'text-warning';
                    } else {
                        stockEl.textContent = 'Selecciona talle y color';
                        stockContainer.className = 'stock-info-mejorado';
                        stockEl.className = 'text-muted';
                    }
                }
            }
            
            document.querySelectorAll('input[name="talla"], input[name="color"]').forEach(function(input) {
                input.addEventListener('change', actualizarStock);
            });
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
        
        function cambiarImagenPrincipal(index) {
            // Actualizar imagen principal
            const imagenPrincipal = document.getElementById('imagenPrincipalLimpia');
            
            if (imagenPrincipal) {
                imagenPrincipal.src = imagenesProducto[index];
                
                // Actualizar thumbnails activos
                document.querySelectorAll('.thumbnail-mini').forEach((item, i) => {
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
</body>
</html>