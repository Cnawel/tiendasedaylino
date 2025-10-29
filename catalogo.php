<?php
/**
 * ========================================================================
 * PÁGINA CATÁLOGO - Tienda Seda y Lino
 * ========================================================================
 * Muestra productos filtrados por categoría
 * 
 * @author Tienda Seda y Lino
 * @version 2.0
 * ========================================================================
 */

// Configurar título de la página
$titulo_pagina = 'Catálogo de Productos';

// Incluir header completo (head + navigation)
include 'includes/header.php';

// Conectar a la base de datos y helpers
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/image_helper.php';

// Obtener categoría desde URL
$categoria_nombre = $_GET['categoria'] ?? 'todos';
$categoria_id = null;
$descripcion_categoria = 'Descubre nuestra colección de productos elegantes';

// Desripciones personalizadas por categoría
$descripciones = [
    'todos' => 'Descubre nuestra colección completa de productos elegantes',
    'Camisas' => 'Elegantes camisas de seda y lino para ocasiones especiales',
    'Blusas' => 'Hermosas blusas frescas y cómodas, perfectas para el día a día',
    'Pantalones' => 'Pantalones modernos y versátiles en diferentes estilos',
    'Shorts' => 'Shorts casuales ideales para el verano y climas cálidos'
];

// Obtener ID de categoría si se especificó
if ($categoria_nombre !== 'todos') {
    $stmt = $mysqli->prepare("SELECT id_categoria, nombre_categoria FROM Categorias WHERE nombre_categoria = ? LIMIT 1");
    $stmt->bind_param('s', $categoria_nombre);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $categoria_id = $row['id_categoria'];
        $descripcion_categoria = $descripciones[$categoria_nombre] ?? $descripciones['todos'];
    }
} else {
    $descripcion_categoria = $descripciones['todos'];
}

// Construir consulta para obtener productos CON SUS COLORES (una fila por color)
// Se une con Stock_Variantes para obtener todos los colores disponibles
if ($categoria_id) {
    $sql = "SELECT p.id_producto, p.nombre_producto, p.descripcion_producto, p.precio_actual, 
                   p.genero, c.nombre_categoria, sv.color, SUM(sv.stock) as total_stock
            FROM Productos p
            LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
            LEFT JOIN Stock_Variantes sv ON p.id_producto = sv.id_producto
            WHERE p.id_categoria = ?
            GROUP BY p.id_producto, sv.color
            ORDER BY p.nombre_producto, sv.color";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $categoria_id);
} else {
    $sql = "SELECT p.id_producto, p.nombre_producto, p.descripcion_producto, p.precio_actual, 
                   p.genero, c.nombre_categoria, sv.color, SUM(sv.stock) as total_stock
            FROM Productos p
            LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
            LEFT JOIN Stock_Variantes sv ON p.id_producto = sv.id_producto
            GROUP BY p.id_producto, sv.color
            ORDER BY c.nombre_categoria, p.nombre_producto, sv.color";
    $stmt = $mysqli->prepare($sql);
}

$stmt->execute();
$productos = $stmt->get_result();
?>

<!-- Contenido principal -->
<main class="productos">
        <div class="container mt-4">
            <!-- Filtros por categoría - Compactos y formales -->
            <div class="mb-3 d-flex justify-content-center flex-wrap gap-2">
                <a href="catalogo.php?categoria=todos" class="filtro-categoria-small <?= $categoria_nombre === 'todos' ? 'active' : '' ?>">Todos</a>
                <a href="catalogo.php?categoria=Camisas" class="filtro-categoria-small <?= $categoria_nombre === 'Camisas' ? 'active' : '' ?>">Camisas</a>
                <a href="catalogo.php?categoria=Blusas" class="filtro-categoria-small <?= $categoria_nombre === 'Blusas' ? 'active' : '' ?>">Blusas</a>
                <a href="catalogo.php?categoria=Pantalones" class="filtro-categoria-small <?= $categoria_nombre === 'Pantalones' ? 'active' : '' ?>">Pantalones</a>
                <a href="catalogo.php?categoria=Shorts" class="filtro-categoria-small <?= $categoria_nombre === 'Shorts' ? 'active' : '' ?>">Shorts</a>
            </div>

            <div class="row g-3">
                <?php if ($productos->num_rows > 0): ?>
                    <?php while ($producto = $productos->fetch_assoc()): ?>
                        <div class="col-xl-3 col-lg-4 col-md-4 col-sm-6 col-6">
                            <div class="card tarjeta h-100 shadow-sm overflow-hidden position-relative">
                                <!-- Badge de stock -->
                                <?php if ($producto['total_stock'] <= 0): ?>
                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2" style="z-index: 10;">SIN STOCK</span>
                                <?php endif; ?>
                                
                                <a href="detalle-producto.php?id=<?= $producto['id_producto'] ?>" class="text-decoration-none">
                                    <?php 
                                    // Usar el color de esta fila específica
                                    $color_actual = strtolower($producto['color'] ?? 'blanco');
                                    
                                    // Obtener miniatura según color actual
                                    $imagen = obtenerMiniaturaPorColor(
                                        $producto['nombre_categoria'],
                                        $producto['genero'] ?? 'unisex',
                                        $color_actual
                                    );
                                    ?>
                                    <div class="card-img-container position-relative" style="height: 280px; overflow: hidden; background-color: #f8f9fa;">
                                        <img src="<?= htmlspecialchars($imagen) ?>" class="card-img-top w-100 h-100 img-hover" alt="<?= htmlspecialchars($producto['nombre_producto']) ?> - <?= htmlspecialchars($producto['color']) ?>" style="object-fit: contain; padding: 10px; transition: transform 0.3s ease;">
                                    </div>
                                    
                                    <!-- Info del producto y color -->
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
    </main>

<?php
// Incluir footer completo (con scripts)
include 'includes/footer.php';
render_footer();
?>

