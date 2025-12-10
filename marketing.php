<?php
/**
 * ========================================================================
 * PANEL DE MARKETING - Tienda Seda y Lino
 * ========================================================================
 * Panel simple para gestión de productos
 * - Ver productos en tabla
 * - Modificar productos existentes
 * - Crear productos nuevos
 * - Carga masiva desde CSV
 * ========================================================================
 */
session_start();

// Verificación de acceso
require_once __DIR__ . '/includes/auth_check.php';
requireRole('marketing');
require_once __DIR__ . '/includes/talles_config.php';
require_once __DIR__ . '/includes/queries/producto_queries.php';
require_once __DIR__ . '/includes/queries/categoria_queries.php';
require_once __DIR__ . '/includes/queries/pedido_queries.php';
require_once __DIR__ . '/includes/queries/stock_queries.php';
require_once __DIR__ . '/includes/queries/foto_producto_queries.php';
require_once __DIR__ . '/includes/image_helper.php';
require_once __DIR__ . '/includes/marketing_functions.php';
require_once __DIR__ . '/includes/product_image_functions.php';
require_once __DIR__ . '/includes/sales_functions.php';
require_once __DIR__ . '/includes/admin_functions.php';
require_once __DIR__ . '/includes/dashboard_functions.php';
require_once __DIR__ . '/includes/security_functions.php';

$id_usuario = getCurrentUserId();
$usuario_actual = getCurrentUser();
require_once __DIR__ . '/config/database.php';

$titulo_pagina = 'Panel de Marketing';

// Capturar mensajes de sesión usando función centralizada
$resultado_mensaje = obtenerMensajeSession();
$mensaje = $resultado_mensaje['mensaje'];
$mensaje_tipo = $resultado_mensaje['mensaje_tipo'];

// ============================================================================
// PROCESAR ACTIVACIÓN/DESACTIVACIÓN DE PRODUCTO
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['activar_producto']) || isset($_POST['desactivar_producto']))) {
    $id_producto = isset($_POST['id_producto']) ? intval($_POST['id_producto']) : 0;
    
    if ($id_producto > 0) {
        if (isset($_POST['activar_producto'])) {
            if (reactivarProducto($mysqli, $id_producto)) {
                $_SESSION['mensaje'] = 'Producto reactivado correctamente';
                $_SESSION['mensaje_tipo'] = 'success';
            } else {
                $_SESSION['mensaje'] = 'Error al reactivar el producto';
                $_SESSION['mensaje_tipo'] = 'danger';
            }
        } elseif (isset($_POST['desactivar_producto'])) {
            if (desactivarProducto($mysqli, $id_producto)) {
                $_SESSION['mensaje'] = 'Producto desactivado correctamente';
                $_SESSION['mensaje_tipo'] = 'success';
            } else {
                $_SESSION['mensaje'] = 'Error al desactivar el producto';
                $_SESSION['mensaje_tipo'] = 'danger';
            }
        }
        
        $params_adicionales = ['tab' => 'productos'];
        if (isset($_POST['mostrar_inactivos']) && $_POST['mostrar_inactivos'] == '1') {
            $params_adicionales['mostrar_inactivos'] = '1';
        }
        $redirect_url = construirRedirectUrl('marketing.php', $params_adicionales);
        header('Location: ' . $redirect_url);
        exit;
    }
}

// ============================================================================
// PROCESAR ELIMINACIÓN PERMANENTE DE PRODUCTO
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_producto_permanente'])) {
    $id_producto = isset($_POST['id_producto']) ? intval($_POST['id_producto']) : 0;
    
    if ($id_producto > 0) {
        // Verificar que el producto puede eliminarse
        $verificacion = verificarProductoPuedeEliminarse($mysqli, $id_producto);
        
        if (!$verificacion['puede_eliminarse']) {
            $_SESSION['mensaje'] = $verificacion['razon'];
            $_SESSION['mensaje_tipo'] = 'danger';
            $params_adicionales = ['tab' => 'productos'];
            if (isset($_POST['mostrar_inactivos']) && $_POST['mostrar_inactivos'] == '1') {
                $params_adicionales['mostrar_inactivos'] = '1';
            }
            $redirect_url = construirRedirectUrl('marketing.php', $params_adicionales);
            header('Location: ' . $redirect_url);
            exit;
        }
        
        // Intentar eliminar permanentemente (pasar ruta base para eliminar imágenes)
        $resultado = eliminarProductoPermanentemente($mysqli, $id_producto, __DIR__);
        
        if ($resultado['success']) {
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['mensaje_tipo'] = 'success';
        } else {
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['mensaje_tipo'] = 'danger';
        }
        
        $params_adicionales = ['tab' => 'productos'];
        if (isset($_POST['mostrar_inactivos']) && $_POST['mostrar_inactivos'] == '1') {
            $params_adicionales['mostrar_inactivos'] = '1';
        }
        $redirect_url = construirRedirectUrl('marketing.php', $params_adicionales);
        header('Location: ' . $redirect_url);
        exit;
    }
}

// ============================================================================
// PROCESAR SUBIDA DE FOTOS TEMPORALES
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_foto_temporal'])) {
    $fotos_subidas = 0;
    $errores_subida = [];
    
    // Procesar múltiples archivos
    if (isset($_FILES['foto_temporal']) && is_array($_FILES['foto_temporal']['name'])) {
        // Múltiples archivos
        $total_archivos = count($_FILES['foto_temporal']['name']);
        for ($i = 0; $i < $total_archivos; $i++) {
            if ($_FILES['foto_temporal']['error'][$i] === UPLOAD_ERR_OK) {
                $archivo = [
                    'name' => $_FILES['foto_temporal']['name'][$i],
                    'type' => $_FILES['foto_temporal']['type'][$i],
                    'tmp_name' => $_FILES['foto_temporal']['tmp_name'][$i],
                    'error' => $_FILES['foto_temporal']['error'][$i],
                    'size' => $_FILES['foto_temporal']['size'][$i]
                ];
                try {
                    subirFotoTemporal($archivo);
                    $fotos_subidas++;
                } catch (Exception $e) {
                    $errores_subida[] = $_FILES['foto_temporal']['name'][$i] . ': ' . $e->getMessage();
                }
            }
        }
    } elseif (isset($_FILES['foto_temporal']) && $_FILES['foto_temporal']['error'] === UPLOAD_ERR_OK) {
        // Un solo archivo
        try {
            subirFotoTemporal($_FILES['foto_temporal']);
            $fotos_subidas++;
        } catch (Exception $e) {
            $errores_subida[] = $_FILES['foto_temporal']['name'] . ': ' . $e->getMessage();
        }
    }
    
    // Preparar mensaje
    if ($fotos_subidas > 0) {
        $_SESSION['mensaje'] = $fotos_subidas . ' foto(s) subida(s) correctamente.';
        $_SESSION['mensaje_tipo'] = 'success';
    }
    if (!empty($errores_subida)) {
        $_SESSION['mensaje'] = ($_SESSION['mensaje'] ?? '') . ' Errores: ' . implode('; ', $errores_subida);
        $_SESSION['mensaje_tipo'] = 'warning';
    }
    if ($fotos_subidas === 0 && empty($errores_subida)) {
        $_SESSION['mensaje'] = 'No se seleccionaron archivos para subir.';
        $_SESSION['mensaje_tipo'] = 'warning';
    }
    
    $redirect_url = construirRedirectUrl('marketing.php', ['tab' => 'fotos']);
    header('Location: ' . $redirect_url);
    exit;
}

// ============================================================================
// PROCESAR ELIMINACIÓN DE FOTO TEMPORAL
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_foto_temporal'])) {
    $nombre_archivo = $_POST['nombre_archivo'] ?? '';
    if (!empty($nombre_archivo)) {
        if (eliminarFotoTemporal($nombre_archivo)) {
            $_SESSION['mensaje'] = 'Foto eliminada correctamente';
            $_SESSION['mensaje_tipo'] = 'success';
        } else {
            $_SESSION['mensaje'] = 'Error al eliminar la foto';
            $_SESSION['mensaje_tipo'] = 'danger';
        }
        $redirect_url = construirRedirectUrl('marketing.php', ['tab' => 'fotos']);
        header('Location: ' . $redirect_url);
        exit;
    }
}

// ============================================================================
// PROCESAR ELIMINACIÓN DE CATEGORÍA
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_categoria'])) {
    $id_categoria = isset($_POST['id_categoria']) ? intval($_POST['id_categoria']) : 0;
    
    if ($id_categoria > 0) {
        // Verificar que la categoría puede eliminarse
        $verificacion = verificarCategoriaPuedeEliminarse($mysqli, $id_categoria);
        
        if (!$verificacion['puede_eliminarse']) {
            $_SESSION['mensaje'] = $verificacion['razon'];
            $_SESSION['mensaje_tipo'] = 'danger';
            $redirect_url = construirRedirectUrl('marketing.php', ['tab' => 'categorias']);
            header('Location: ' . $redirect_url);
            exit;
        }
        
        // Intentar eliminar permanentemente la categoría
        if (eliminarCategoriaPermanentemente($mysqli, $id_categoria)) {
            $_SESSION['mensaje'] = 'Categoría eliminada permanentemente';
            $_SESSION['mensaje_tipo'] = 'success';
        } else {
            $_SESSION['mensaje'] = 'Error al eliminar la categoría';
            $_SESSION['mensaje_tipo'] = 'danger';
        }
        
        $redirect_url = construirRedirectUrl('marketing.php', ['tab' => 'categorias']);
        header('Location: ' . $redirect_url);
        exit;
    }
}

// ============================================================================
// PROCESAR CARGA MASIVA CSV
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = procesarCargaCSV($mysqli, $_FILES);
    if ($resultado !== false) {
        if (!empty($resultado['mensaje'])) {
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
        }
        header('Location: ' . $resultado['redirect']);
        exit;
    }
}

// ============================================================================
// CREAR NUEVO PRODUCTO
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = procesarCreacionProducto($mysqli, $_POST, $_FILES);
    if ($resultado !== false) {
        $_SESSION['mensaje'] = $resultado['mensaje'];
        $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
        // Redirigir a la pestaña 'agregar' para mostrar el mensaje en el tab correcto
        $redirect_url = construirRedirectUrl('marketing.php', ['tab' => 'agregar']);
        header('Location: ' . $redirect_url);
        exit;
    }
}

// ============================================================================
// OBTENER DATOS
// ============================================================================
// Obtener categorías usando función centralizada (filtra solo activas)
$categorias_temp = obtenerCategorias($mysqli);
// Eliminar duplicados por nombre de categoría (mantener solo la primera ocurrencia)
// Esto evita mostrar categorías duplicadas con el mismo nombre pero diferentes IDs
$categorias_array = [];
$nombres_categorias_vistos = [];
foreach ($categorias_temp as $cat) {
    // Verificar que la categoría esté activa (doble verificación)
    if (isset($cat['activo']) && $cat['activo'] != 1) {
        continue; // Saltar categorías inactivas
    }
    $nombre_normalizado = strtolower(trim($cat['nombre_categoria']));
    if (!in_array($nombre_normalizado, $nombres_categorias_vistos)) {
        $categorias_array[] = $cat;
        $nombres_categorias_vistos[] = $nombre_normalizado;
    }
}

// Obtener nombres únicos de productos existentes
$nombres_productos = obtenerNombresProductosUnicos($mysqli);

// Obtener límite de productos desde URL (10, 50, o todos)
$limite_productos = isset($_GET['limite']) ? $_GET['limite'] : 'TODOS';
if ($limite_productos !== '10' && $limite_productos !== '50') {
    $limite_productos = 'TODOS';
}

// Obtener estadísticas de productos
$stats_productos = obtenerEstadisticasProductos($mysqli);
$stats_productos['total_productos'] = contarNombresProductosDistintos($mysqli);

// Talles disponibles
$talles_disponibles = obtenerTallesEstandar();
$colores_disponibles = ['Negro', 'Blanco', 'Azul', 'Rojo', 'Verde', 'Amarillo', 'Rosa', 'Gris', 'Marrón', 'Beige', 'Celeste', 'Naranja', 'Violeta', 'Turquesa'];

// Obtener parámetro para mostrar productos inactivos
$mostrar_inactivos = isset($_GET['mostrar_inactivos']) && $_GET['mostrar_inactivos'] == '1';

// Obtener productos con límite seleccionado
$limite_numero = ($limite_productos === 'TODOS') ? 0 : (int)$limite_productos;
$productos_array = obtenerProductosMarketing($mysqli, $limite_numero, $mostrar_inactivos);
$ids_productos_activos = obtenerProductosActivos($mysqli);

// Agrupar productos usando id_producto como clave única
$productos_agrupados = [];
$talles_orden_estandar = $talles_disponibles;

foreach ($productos_array as $producto) {
    $activo_producto = isset($producto['activo']) ? intval($producto['activo']) : 1;
    if (!$mostrar_inactivos && $activo_producto !== 1) {
        continue;
    }
    
    $clave_unica = $producto['id_producto'];
    
    // Si el producto no tiene variantes activas, aún así debe mostrarse si está activo
    // Cada producto se muestra individualmente con su propio ID
    if (!isset($productos_agrupados[$clave_unica])) {
        // Crear entrada para este producto
        $productos_agrupados[$clave_unica] = [
            'id_producto' => $producto['id_producto'],
            'nombre_producto' => $producto['nombre_producto'],
            'descripcion_producto' => $producto['descripcion_producto'],
            'precio_actual' => $producto['precio_actual'],
            'genero' => $producto['genero'],
            'nombre_categoria' => $producto['nombre_categoria'],
            'id_categoria' => $producto['id_categoria'],
            'activo' => isset($producto['activo']) ? intval($producto['activo']) : 1,
            'colores_array' => $producto['colores_array'] ?? [],
            'talles_array' => $producto['talles_array'] ?? [],
            'stock_total' => $producto['stock_total'] ?? 0,
            'total_variantes' => $producto['total_variantes'] ?? 0
        ];
    } else {
        // Si ya existe (no debería pasar con id_producto como clave), combinar datos
        // Combinar colores
        if (!empty($producto['colores_array'])) {
            $productos_agrupados[$clave_unica]['colores_array'] = array_unique(
                array_merge($productos_agrupados[$clave_unica]['colores_array'], $producto['colores_array'])
            );
        }
        
        // Combinar talles
        if (!empty($producto['talles_array'])) {
            $productos_agrupados[$clave_unica]['talles_array'] = array_unique(
                array_merge($productos_agrupados[$clave_unica]['talles_array'], $producto['talles_array'])
            );
        }
        
        // Sumar stock total
        $productos_agrupados[$clave_unica]['stock_total'] += ($producto['stock_total'] ?? 0);
        $productos_agrupados[$clave_unica]['total_variantes'] += ($producto['total_variantes'] ?? 0);
    }
}

// Ordenar talles y colores de cada producto agrupado
foreach ($productos_agrupados as &$producto) {
    if (!empty($producto['talles_array'])) {
        usort($producto['talles_array'], function($a, $b) use ($talles_orden_estandar) {
            $pos_a = array_search($a, $talles_orden_estandar);
            $pos_b = array_search($b, $talles_orden_estandar);
            if ($pos_a === false) $pos_a = 999;
            if ($pos_b === false) $pos_b = 999;
            return $pos_a - $pos_b;
        });
    }
    
    // Ordenar colores alfabéticamente
    if (!empty($producto['colores_array'])) {
        sort($producto['colores_array']);
    }
}
unset($producto); // Liberar referencia

// Filtrar productos agrupados para asegurar que solo se muestren los que tienen id_producto activo
// Esto es una verificación adicional después del agrupamiento (solo si no se muestran inactivos)
// Verificación adicional de productos activos
$productos_agrupados_filtrados = [];
foreach ($productos_agrupados as $nombre_clave => $producto_agrupado) {
    // Si se muestran inactivos, incluir todos los grupos
    // Si no se muestran inactivos, verificar que el estado 'activo' del grupo sea 1
    $activo_grupo = isset($producto_agrupado['activo']) ? intval($producto_agrupado['activo']) : 1;
    
    if ($mostrar_inactivos || $activo_grupo === 1) {
        $productos_agrupados_filtrados[$nombre_clave] = $producto_agrupado;
    }
}

// Convertir array asociativo a array indexado para la vista
$productos_array = array_values($productos_agrupados_filtrados);

// Obtener métricas analíticas para marketing
$top_productos_vendidos_marketing = obtenerTopProductosVendidos($mysqli, 10);
$productos_sin_movimiento = obtenerProductosSinMovimiento($mysqli, 30);
$movimientos_stock = obtenerMovimientosStockRecientes($mysqli, 50);
?>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <!-- Header del Dashboard -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">Dashboard Marketing</h1>
                    <p class="text-muted mb-0">Bienvenido, <?= htmlspecialchars($usuario_actual['nombre'] . ' ' . $usuario_actual['apellido']) ?></p>
                </div>
                <div>
                    <a href="perfil.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-user"></i> Mi Perfil
                    </a>
                    <a href="logout.php" class="btn btn-outline-secondary" onclick="return confirmLogout()">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-box fa-2x text-primary mb-2"></i>
                    <h5 class="card-title">Total Productos</h5>
                    <h3 class="text-primary"><?= number_format($stats_productos['total_productos'], 0, ',', '.') ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-cubes fa-2x text-success mb-2"></i>
                    <h5 class="card-title">Stock Total</h5>
                    <h3 class="text-success"><?= number_format($stats_productos['stock_total'] ?? 0, 0, ',', '.') ?></h3>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Detectar pestaña activa desde parámetro URL
    $tab_activo = isset($_GET['tab']) ? $_GET['tab'] : 'productos';
    $tabs_validos = ['productos', 'csv', 'agregar', 'categorias', 'metricas', 'fotos'];
    if (!in_array($tab_activo, $tabs_validos)) {
        $tab_activo = 'productos';
    }
    ?>

    <!-- Navegación por pestañas -->
    <ul class="nav nav-tabs mb-4" id="marketingTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab_activo === 'productos' ? 'active' : '' ?>" id="productos-tab" data-bs-toggle="tab" data-bs-target="#productos" type="button" role="tab">
                <i class="fas fa-edit me-2"></i>Editar Productos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab_activo === 'csv' ? 'active' : '' ?>" id="csv-tab" data-bs-toggle="tab" data-bs-target="#csv" type="button" role="tab">
                <i class="fas fa-upload me-2"></i>Carga CSV
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab_activo === 'agregar' ? 'active' : '' ?>" id="agregar-tab" data-bs-toggle="tab" data-bs-target="#agregar" type="button" role="tab">
                <i class="fas fa-plus me-2"></i>Agregar Producto
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab_activo === 'categorias' ? 'active' : '' ?>" id="categorias-tab" data-bs-toggle="tab" data-bs-target="#categorias" type="button" role="tab">
                <i class="fas fa-tags me-2"></i>Categorías
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab_activo === 'fotos' ? 'active' : '' ?>" id="fotos-tab" data-bs-toggle="tab" data-bs-target="#fotos" type="button" role="tab">
                <i class="fas fa-images me-2"></i>Cargar Fotos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab_activo === 'metricas' ? 'active' : '' ?>" id="metricas-tab" data-bs-toggle="tab" data-bs-target="#metricas" type="button" role="tab">
                <i class="fas fa-chart-line me-2"></i>Métricas
            </button>
        </li>
    </ul>

    <div class="tab-content" id="marketingTabsContent">
        <!-- Pestaña 1: Editar Productos -->
        <div class="tab-pane fade <?= $tab_activo === 'productos' ? 'show active' : '' ?>" id="productos" role="tabpanel">
            <!-- Mensajes -->
            <?php if ($mensaje): ?>
            <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Warnings de fotos si los hay -->
            <?php if (!empty($_SESSION['warnings_fotos']) && is_array($_SESSION['warnings_fotos']) && $tab_activo === 'productos'): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Advertencias - Productos con fotos existentes:</h5>
                <ul class="mb-0">
                    <?php foreach ($_SESSION['warnings_fotos'] as $warning): ?>
                        <li><?= htmlspecialchars($warning) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php 
            // Limpiar warnings después de mostrarlos
            unset($_SESSION['warnings_fotos']);
            endif; ?>
            
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list me-2"></i>Productos
                    </h5>
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="mostrarInactivos" 
                                   <?= $mostrar_inactivos ? 'checked' : '' ?>
                                   onchange="toggleProductosInactivos(this.checked)">
                            <label class="form-check-label" for="mostrarInactivos">
                                <small>Mostrar productos inactivos</small>
                            </label>
                        </div>
                        <label class="mb-0"><small>Mostrar:</small></label>
                        <select class="form-select form-select-sm" style="width: auto;" onchange="cambiarLimiteProductos(this.value)">
                            <option value="TODOS" <?= $limite_productos == 'TODOS' ? 'selected' : '' ?>>Todos</option>
                            <option value="50" <?= $limite_productos == '50' ? 'selected' : '' ?>>Últimos 50</option>
                            <option value="10" <?= $limite_productos == '10' ? 'selected' : '' ?>>Últimos 10</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($productos_array)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No hay productos registrados</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table sortable-table">
                            <thead class="table-dark">
                                <tr>
                                    <th class="sortable">ID</th>
                                    <th class="sortable text-center" style="width: 60px;">A/I</th>
                                    <th class="sortable">Producto</th>
                                    <th class="sortable">Categoría</th>
                                    <th class="sortable">Género</th>
                                    <th class="sortable">Precio</th>
                                    <th class="sortable">Talles</th>
                                    <th class="sortable">Colores</th>
                                    <th class="sortable">Stock Total</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos_array as $producto): ?>
                                <tr>
                                    <td>#<?= $producto['id_producto'] ?></td>
                                    <td class="text-center">
                                        <?php 
                                        $activo = intval($producto['activo'] ?? 1);
                                        if ($activo === 1): ?>
                                            <span class="badge bg-success" title="Activo">A</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary" title="Inactivo">I</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($producto['nombre_producto']) ?></strong>
                                        <?php if ($producto['descripcion_producto']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars(substr($producto['descripcion_producto'], 0, 50)) ?><?= strlen($producto['descripcion_producto']) > 50 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($producto['nombre_categoria']) ?></td>
                                    <td><?= ucfirst($producto['genero']) ?></td>
                                    <td>$<?= number_format($producto['precio_actual'], 2, ',', '.') ?></td>
                                    <td>
                                        <?php if (!empty($producto['talles_array'])): ?>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($producto['talles_array'] as $talle): ?>
                                                    <span class="badge bg-secondary badge-talles">
                                                        <?= htmlspecialchars($talle) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted"><em>Sin talles</em></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($producto['colores_array'])): ?>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($producto['colores_array'] as $color): ?>
                                                    <span class="badge rounded-pill bg-secondary badge-colores">
                                                        <?= htmlspecialchars($color) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted"><em>Sin colores</em></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($producto['stock_total'] ?? 0) > 0): ?>
                                            <span class="badge bg-success"><?= number_format($producto['stock_total'], 0, ',', '.') ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Sin stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="marketing-editar-producto.php?id=<?= $producto['id_producto'] ?>" 
                                               class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit me-1"></i>Gestionar
                                            </a>
                                            <?php 
                                            // Calcular condiciones una sola vez para simplificar
                                            $activo = intval($producto['activo'] ?? 1);
                                            $es_activo = $activo === 1;
                                            $input_mostrar_inactivos = $mostrar_inactivos ? '<input type="hidden" name="mostrar_inactivos" value="1">' : '';
                                            
                                            // Botón activar/desactivar
                                            if ($es_activo): ?>
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('¿Está seguro de desactivar este producto?');">
                                                    <input type="hidden" name="id_producto" value="<?= $producto['id_producto'] ?>">
                                                    <input type="hidden" name="desactivar_producto" value="1">
                                                    <?= $input_mostrar_inactivos ?>
                                                    <button type="submit" class="btn btn-sm btn-secondary" title="Desactivar producto">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="id_producto" value="<?= $producto['id_producto'] ?>">
                                                    <input type="hidden" name="activar_producto" value="1">
                                                    <?= $input_mostrar_inactivos ?>
                                                    <button type="submit" class="btn btn-sm btn-success" title="Activar producto">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                
                                                <?php
                                                // Verificar si el producto puede eliminarse permanentemente
                                                $verificacion_eliminar = verificarProductoPuedeEliminarse($mysqli, $producto['id_producto']);
                                                $puede_eliminarse = $verificacion_eliminar['puede_eliminarse'];
                                                
                                                if ($puede_eliminarse): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-danger" 
                                                            title="Eliminar permanentemente"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#eliminarProductoModal<?= $producto['id_producto'] ?>">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                    
                                                    <!-- Modal de confirmación de eliminación -->
                                                    <div class="modal fade" id="eliminarProductoModal<?= $producto['id_producto'] ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-danger text-white">
                                                                    <h5 class="modal-title">
                                                                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación Permanente
                                                                    </h5>
                                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>
                                                                        <strong>Producto:</strong> <?= htmlspecialchars($producto['nombre_producto']) ?> (ID: #<?= $producto['id_producto'] ?>)<br>
                                                                        <strong>Categoría:</strong> <?= htmlspecialchars($producto['nombre_categoria']) ?>
                                                                    </p>
                                                                    <p class="mb-0">
                                                                        <strong>¿Está seguro de que desea eliminar este producto permanentemente?</strong><br>
                                                                        <small class="text-muted">Esta acción eliminará todas las variantes, fotos, movimientos de stock e imágenes físicas del producto. Esta acción es IRREVERSIBLE.</small>
                                                                    </p>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                    <form method="POST" style="display: inline;">
                                                                        <input type="hidden" name="eliminar_producto_permanente" value="1">
                                                                        <input type="hidden" name="id_producto" value="<?= $producto['id_producto'] ?>">
                                                                        <?= $input_mostrar_inactivos ?>
                                                                        <button type="submit" class="btn btn-danger">
                                                                            <i class="fas fa-trash-alt me-1"></i>Eliminar Permanentemente
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-danger" 
                                                            disabled
                                                            title="No se puede eliminar: <?= htmlspecialchars($verificacion_eliminar['razon']) ?>">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            Mostrando <?= count($productos_array) ?> producto(s)
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pestaña 2: Carga CSV -->
        <div class="tab-pane fade <?= $tab_activo === 'csv' ? 'show active' : '' ?>" id="csv" role="tabpanel">
            <!-- Mensajes -->
            <?php if ($mensaje && $tab_activo === 'csv'): ?>
            <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Errores CSV si los hay -->
            <?php if (!empty($_SESSION['errores_csv']) && is_array($_SESSION['errores_csv']) && $tab_activo === 'csv'): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Errores encontrados en el CSV:</h5>
                <ul class="mb-0">
                    <?php foreach ($_SESSION['errores_csv'] as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-upload me-2"></i>Carga Masiva desde CSV
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="procesar_csv" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Seleccionar archivo CSV</label>
                            <input type="file" class="form-control" name="archivo_csv" accept=".csv" required>
                            <small class="text-muted">
                                <a href="marketing-descargar-template.php" target="_blank">Descargar plantilla CSV</a>
                            </small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" data-auto-lock="true" data-lock-time="3000" data-lock-text="Procesando CSV...">
                            <i class="fas fa-upload me-1"></i>Procesar CSV
                        </button>
                    </form>
                    
                    <div class="alert alert-info mt-4">
                        <h6><i class="fas fa-info-circle me-2"></i>Cómo subir productos en CSV</h6>
                        <p class="mb-2"><strong>IMPORTANTE:</strong> Descarga la plantilla CSV para ver el formato exacto. Tu archivo CSV debe tener exactamente estas columnas en la primera fila (headers):</p>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Columna</th>
                                        <th>Descripción</th>
                                        <th>Ejemplo</th>
                                        <th>Valores Permitidos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Nombre</strong></td>
                                        <td>Nombre del producto</td>
                                        <td>Blusa Mujer Manga Larga</td>
                                        <td><small class="text-muted">Cualquier texto</small></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Descripción</strong></td>
                                        <td>Descripción del producto</td>
                                        <td>Blusa de lino, manga larga</td>
                                        <td><small class="text-muted">Opcional</small></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Precio</strong></td>
                                        <td>Precio en decimales</td>
                                        <td>15000.00</td>
                                        <td><small class="text-muted">Número > 0</small></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Categoría</strong></td>
                                        <td><strong>Nombre de la categoría</strong> (NO usar ID)</td>
                                        <td><?= !empty($categorias_array) ? htmlspecialchars($categorias_array[0]['nombre_categoria']) : 'Blusas' ?></td>
                                        <td><small class="text-muted">Ver categorías disponibles abajo</small></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Género</strong></td>
                                        <td>Género del producto</td>
                                        <td>mujer</td>
                                        <td><small class="text-muted">hombre, mujer, unisex</small></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Talle</strong></td>
                                        <td>Talle de la variante</td>
                                        <td>M</td>
                                        <td><small class="text-muted">S, M, L, XL</small></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Color</strong></td>
                                        <td>Color de la variante</td>
                                        <td>Azul</td>
                                        <td><small class="text-muted">Cualquier color</small></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Stock</strong></td>
                                        <td>Cantidad de stock inicial</td>
                                        <td>10</td>
                                        <td><small class="text-muted">Número entero ≥ 0</small></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (!empty($categorias_array)): ?>
                        <div class="alert alert-light mt-3 mb-2">
                            <strong><i class="fas fa-list me-2"></i>Categorías disponibles en el sistema:</strong>
                            <div class="mt-2">
                                <?php foreach ($categorias_array as $cat): ?>
                                    <span class="badge bg-secondary me-1 mb-1"><?= htmlspecialchars($cat['nombre_categoria']) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-lightbulb me-1"></i>
                                <strong>Tip:</strong> Si usas una categoría que no existe, se creará automáticamente.
                            </small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info border-info alert-panel mt-3">
                            <h6 class="text-dark mb-3"><i class="fas fa-info-circle me-2"></i>Recomendaciones para una carga exitosa:</h6>
                            <ul class="mb-0 small text-dark">
                                <li class="mb-2"><strong>Usa nombres de categorías, NO IDs:</strong> Escribe "Blusas" en lugar de "1"</li>
                                <li class="mb-2"><strong>Género debe ser exacto:</strong> Solo acepta: <code class="bg-white px-1 rounded">hombre</code>, <code class="bg-white px-1 rounded">mujer</code> o <code class="bg-white px-1 rounded">unisex</code> (minúsculas)</li>
                                <li class="mb-2"><strong>Precio debe ser numérico:</strong> Usa formato decimal como <code class="bg-white px-1 rounded">15000.00</code></li>
                                <li class="mb-2"><strong>Stock debe ser entero:</strong> Usa números enteros como <code class="bg-white px-1 rounded">10</code>, no decimales</li>
                                <li class="mb-2"><strong>Cada fila = una variante:</strong> Los productos con el mismo nombre se agrupan automáticamente</li>
                                <li class="mb-0"><strong>No dejes campos obligatorios vacíos:</strong> Nombre, Precio, Categoría, Género, Talle, Color son obligatorios</li>
                            </ul>
                        </div>
                        
                        <p class="mt-3 mb-0"><strong>Nota:</strong> Cada fila representa una variante de producto (talle + color). Los productos con el mismo nombre se agruparán automáticamente.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pestaña 3: Agregar Producto -->
        <div class="tab-pane fade <?= $tab_activo === 'agregar' ? 'show active' : '' ?>" id="agregar" role="tabpanel">
            <!-- Mensajes -->
            <?php if ($mensaje && $tab_activo === 'agregar'): ?>
            <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-plus me-2"></i>Agregar Nuevo Producto
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="form_crear_producto">
                        <input type="hidden" name="crear_producto" value="1">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre del Producto *</label>
                                <select class="form-select" name="nombre_producto" id="select_nombre_producto" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($nombres_productos as $nombre): ?>
                                    <option value="<?= htmlspecialchars($nombre) ?>"><?= htmlspecialchars($nombre) ?></option>
                                    <?php endforeach; ?>
                                    <option value="__NUEVO__" class="text-primary fw-bold">+ Agregar Producto Nuevo</option>
                                </select>
                                <input type="text" class="form-control mt-2 d-none" id="input_nombre_producto_nuevo" placeholder="Ingrese el nombre del nuevo producto">
                                <div class="invalid-feedback" id="error_nombre_producto"></div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Precio *</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="precio_actual" id="input_precio_actual" required>
                                <div class="invalid-feedback" id="error_precio"></div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Género *</label>
                                <select class="form-select" name="genero" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="hombre">Hombre</option>
                                    <option value="mujer">Mujer</option>
                                    <option value="unisex">Unisex</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Categoría *</label>
                                <select class="form-select" name="id_categoria" id="select_categoria" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($categorias_array as $cat): ?>
                                    <option value="<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nombre_categoria']) ?></option>
                                    <?php endforeach; ?>
                                    <option value="__NUEVO__" class="text-primary fw-bold">+ Agregar Nueva Categoría</option>
                                </select>
                                <input type="text" class="form-control mt-2 d-none" id="input_categoria_nueva" placeholder="Ingrese el nombre de la nueva categoría">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" name="descripcion_producto" id="input_descripcion_producto" rows="2"></textarea>
                                <div class="invalid-feedback" id="error_descripcion"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Imagen Miniatura (opcional)</label>
                            <input type="file" class="form-control" name="imagen_miniatura" accept="image/*">
                            <small class="text-muted">Las imágenes por color se agregan en la edición del producto</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Nota:</strong> Este formulario crea el producto BASE. Después de crear, podrás agregar colores y talles desde la página de edición.
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" data-auto-lock="true" data-lock-time="2000" data-lock-text="Creando producto...">Crear Producto</button>
                            <button type="reset" class="btn btn-outline-secondary">Limpiar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Pestaña 4: Categorías -->
        <div class="tab-pane fade <?= $tab_activo === 'categorias' ? 'show active' : '' ?>" id="categorias" role="tabpanel">
            <!-- Mensajes -->
            <?php if ($mensaje && $tab_activo === 'categorias'): ?>
            <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tags me-2"></i>Gestión de Categorías
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($categorias_array)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No hay categorías registradas</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table sortable-table">
                            <thead class="table-dark">
                                <tr>
                                    <th class="sortable">ID</th>
                                    <th class="sortable">Nombre</th>
                                    <th class="sortable">Descripción</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categorias_array as $cat): ?>
                                <tr>
                                    <td>#<?= $cat['id_categoria'] ?></td>
                                    <td><strong><?= htmlspecialchars($cat['nombre_categoria']) ?></strong></td>
                                    <td>
                                        <?php if (!empty($cat['descripcion_categoria'])): ?>
                                            <?= htmlspecialchars($cat['descripcion_categoria']) ?>
                                        <?php else: ?>
                                            <span class="text-muted"><em>Sin descripción</em></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        // Verificar si la categoría puede eliminarse
                                        $verificacion_cat = verificarCategoriaPuedeEliminarse($mysqli, $cat['id_categoria']);
                                        if ($verificacion_cat['puede_eliminarse']): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    title="Eliminar categoría"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#eliminarCategoriaModal<?= $cat['id_categoria'] ?>">
                                                <i class="fas fa-trash-alt"></i> Eliminar
                                            </button>
                                            
                                            <!-- Modal de confirmación de eliminación -->
                                            <div class="modal fade" id="eliminarCategoriaModal<?= $cat['id_categoria'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title">
                                                                <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación Permanente
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>
                                                                <strong>Categoría:</strong> <?= htmlspecialchars($cat['nombre_categoria']) ?> (ID: #<?= $cat['id_categoria'] ?>)
                                                            </p>
                                                            <p class="mb-0">
                                                                <strong>¿Está seguro de que desea eliminar permanentemente esta categoría?</strong><br>
                                                                <small class="text-muted">Esta acción eliminará la categoría de la base de datos de forma permanente. Esta acción es IRREVERSIBLE.</small>
                                                            </p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="eliminar_categoria" value="1">
                                                                <input type="hidden" name="id_categoria" value="<?= $cat['id_categoria'] ?>">
                                                                <button type="submit" class="btn btn-danger">
                                                                    <i class="fas fa-trash-alt me-1"></i>Eliminar Categoría
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    disabled
                                                    title="No se puede eliminar: <?= htmlspecialchars($verificacion_cat['razon']) ?>">
                                                <i class="fas fa-trash-alt"></i> Eliminar
                                            </button>
                                            <small class="text-muted d-block mt-1">
                                                <?= htmlspecialchars($verificacion_cat['razon']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            Mostrando <?= count($categorias_array) ?> categoría(s)
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pestaña 5: Cargar Fotos -->
        <div class="tab-pane fade <?= $tab_activo === 'fotos' ? 'show active' : '' ?>" id="fotos" role="tabpanel">
            <!-- Mensajes -->
            <?php if ($mensaje && $tab_activo === 'fotos'): ?>
            <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-images me-2"></i>Cargar Fotos Temporales para CSV
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Formulario de subida -->
                    <form method="POST" enctype="multipart/form-data" class="mb-4">
                        <input type="hidden" name="subir_foto_temporal" value="1">
                        <div class="row">
                            <div class="col-md-8">
                                <label class="form-label">Seleccionar foto(s)</label>
                                <input type="file" class="form-control" name="foto_temporal[]" accept="image/*" multiple required>
                                <small class="text-muted">Formatos permitidos: JPG, PNG, GIF, WEBP. Tamaño máximo: 5MB por archivo</small>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100" data-auto-lock="true" data-lock-time="3000" data-lock-text="Subiendo fotos...">
                                    <i class="fas fa-upload me-2"></i>Subir Fotos
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Lista de fotos temporales -->
                    <?php 
                    $fotos_temporales = obtenerFotosTemporales();
                    ?>
                    <div class="mt-4">
                        <h6 class="mb-3">
                            <i class="fas fa-list me-2"></i>Fotos Temporales Disponibles 
                            <span class="badge bg-secondary"><?= count($fotos_temporales) ?></span>
                        </h6>
                        
                        <?php if (empty($fotos_temporales)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No hay fotos temporales disponibles. Sube fotos para usarlas en tu CSV.
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nombre del Archivo</th>
                                        <th>Vista Previa</th>
                                        <th>Tamaño</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fotos_temporales as $foto):
                                        $ruta_completa = 'imagenes/' . $foto;
                                        $tamaño = file_exists($ruta_completa) ? filesize($ruta_completa) : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <code id="nombre-<?= md5($foto) ?>"><?= htmlspecialchars($foto) ?></code>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-secondary" 
                                                        onclick="copiarNombre('<?= htmlspecialchars($foto, ENT_QUOTES) ?>', this)"
                                                        title="Copiar nombre">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                            <br>
                                            <small class="text-muted">Usa este nombre en tu CSV</small>
                                        </td>
                                        <td>
                                            <?php if (file_exists($ruta_completa)): ?>
                                            <img src="<?= htmlspecialchars($ruta_completa) ?>" 
                                                 alt="Preview" 
                                                 class="img-thumbnail img-preview-thumbnail">
                                            <?php else: ?>
                                            <span class="text-muted">No disponible</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= number_format($tamaño / 1024, 2) ?> KB
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('¿Está seguro de eliminar esta foto?');">
                                                <input type="hidden" name="eliminar_foto_temporal" value="1">
                                                <input type="hidden" name="nombre_archivo" value="<?= htmlspecialchars($foto) ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash-alt me-1"></i>Eliminar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pestaña 5: Métricas -->
        <div class="tab-pane fade <?= $tab_activo === 'metricas' ? 'show active' : '' ?>" id="metricas" role="tabpanel">
            <div class="row mb-4">
                <!-- Top 10 Productos Vendidos -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-trophy me-2"></i>Top 10 Productos Más Vendidos
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($top_productos_vendidos_marketing)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm sortable-table">
                                        <thead class="table-dark">
                                            <tr>
                                                <th class="sortable">#</th>
                                                <th class="sortable">Producto</th>
                                                <th class="sortable">Talle</th>
                                                <th class="sortable">Color</th>
                                                <th class="sortable text-end">Vendidos</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $posicion = 1; ?>
                                            <?php foreach ($top_productos_vendidos_marketing as $producto): ?>
                                                <tr>
                                                    <td><strong><?= $posicion++ ?></strong></td>
                                                    <td><small><?= htmlspecialchars($producto['nombre_producto']) ?></small></td>
                                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($producto['talle']) ?></span></td>
                                                    <td><span class="badge bg-info"><?= htmlspecialchars($producto['color']) ?></span></td>
                                                    <td class="text-end"><strong><?= number_format($producto['unidades_vendidas'], 0, ',', '.') ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center mb-0">No hay productos vendidos aún</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Productos Sin Movimiento -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-box-open me-2"></i>Productos Sin Movimiento (Últimos 30 días)
                                <span class="badge bg-dark text-light ms-2"><?= count($productos_sin_movimiento) ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($productos_sin_movimiento)): ?>
                                <div class="alert alert-info border-info alert-panel mb-3">
                                    <i class="fas fa-info-circle me-2 text-primary"></i>
                                    <strong class="text-dark">Productos con stock pero sin ventas recientes.</strong> 
                                    <span class="text-dark">Considera promociones o descuentos para reactivar las ventas.</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table sortable-table">
                                        <thead class="table-dark">
                                            <tr>
                                                <th class="sortable">Producto</th>
                                                <th class="sortable">Categoría</th>
                                                <th class="sortable text-end">Stock Total</th>
                                                <th class="sortable text-end">Variantes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($productos_sin_movimiento as $producto): ?>
                                                <tr>
                                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($producto['nombre_producto']) ?></td>
                                                    <td class="text-dark"><?= htmlspecialchars($producto['nombre_categoria']) ?></td>
                                                    <td class="text-end">
                                                        <span class="badge bg-secondary text-white"><?= number_format($producto['stock_total'], 0, ',', '.') ?></span>
                                                    </td>
                                                    <td class="text-end fw-semibold text-dark"><?= $producto['cantidad_variantes'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center mb-0">Todos los productos con stock tienen movimiento reciente</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Movimientos de Stock -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-exchange-alt me-2"></i>Movimientos de Stock Recientes
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($movimientos_stock)): ?>
                                <div class="table-responsive">
                                    <table class="table sortable-table">
                                        <thead class="table-dark">
                                            <tr>
                                                <th class="sortable">Fecha/Hora</th>
                                                <th class="sortable">Tipo</th>
                                                <th class="sortable">Producto</th>
                                                <th class="sortable">Variante</th>
                                                <th class="sortable">Categoría</th>
                                                <th class="sortable text-end">Cantidad</th>
                                                <th class="sortable">Pedido</th>
                                                <th class="sortable">Usuario</th>
                                                <th>Observaciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($movimientos_stock as $movimiento): ?>
                                                <?php
                                                // Obtener mapeo de tipos de movimiento
                                                $tipos_movimiento_map = obtenerTiposMovimientoMap();
                                                $tipo_mov = strtolower(trim($movimiento['tipo_movimiento'] ?? ''));
                                                $info_tipo = $tipos_movimiento_map[$tipo_mov] ?? ['color' => 'secondary', 'nombre' => ucfirst($tipo_mov), 'signo' => ''];
                                                
                                                // Formatear cantidad con signo
                                                $cantidad = intval($movimiento['cantidad'] ?? 0);
                                                $signo = $info_tipo['signo'];
                                                if ($tipo_mov === 'ajuste' && $cantidad < 0) {
                                                    $signo = '';
                                                } elseif ($tipo_mov === 'ajuste' && $cantidad > 0) {
                                                    $signo = '+';
                                                }
                                                $cantidad_formato = $signo . number_format(abs($cantidad), 0, ',', '.');
                                                
                                                // Clase de color para cantidad
                                                $clase_cantidad = '';
                                                if ($tipo_mov === 'venta') {
                                                    $clase_cantidad = 'text-danger';
                                                } elseif (in_array($tipo_mov, ['ingreso', 'devolucion']) || ($tipo_mov === 'ajuste' && $cantidad > 0)) {
                                                    $clase_cantidad = 'text-success';
                                                }
                                                
                                                // Truncar observaciones si son muy largas
                                                // Truncar observaciones si son muy largas (aumentado a 120 caracteres)
                                                $observaciones = $movimiento['observaciones'] ?? '';
                                                $observaciones_truncadas = '';
                                                $observaciones_completas = '';
                                                if (!empty($observaciones)) {
                                                    $observaciones_escaped = htmlspecialchars($observaciones);
                                                    if (strlen($observaciones) > 120) {
                                                        $observaciones_truncadas = substr($observaciones_escaped, 0, 120) . '...';
                                                        $observaciones_completas = nl2br($observaciones_escaped); // Preservar saltos de línea
                                                    } else {
                                                        $observaciones_truncadas = $observaciones_escaped;
                                                        $observaciones_completas = '';
                                                    }
                                                }
                                                ?>
                                                <tr>
                                                    <td><small><?= date('d/m/Y H:i', strtotime($movimiento['fecha_movimiento'])) ?></small></td>
                                                    <td>
                                                        <span class="badge bg-<?= htmlspecialchars($info_tipo['color']) ?>">
                                                            <?= htmlspecialchars($info_tipo['nombre']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($movimiento['nombre_producto']) ?></td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?= htmlspecialchars($movimiento['talle']) ?></span>
                                                        <span class="badge bg-info"><?= htmlspecialchars($movimiento['color']) ?></span>
                                                    </td>
                                                    <td><?= htmlspecialchars($movimiento['nombre_categoria']) ?></td>
                                                    <td class="text-end">
                                                        <strong class="<?= $clase_cantidad ?>"><?= $cantidad_formato ?></strong>
                                                    </td>
                                                    <td>
                                                        <?= !empty($movimiento['id_pedido']) 
                                                            ? '<span class="badge bg-primary">#' . $movimiento['id_pedido'] . '</span>' 
                                                            : '<span class="text-muted"><small>N/A</small></span>' ?>
                                                    </td>
                                                    <td>
                                                        <?= !empty($movimiento['nombre_usuario']) 
                                                            ? '<small>' . htmlspecialchars($movimiento['nombre_usuario']) . '</small>' 
                                                            : '<span class="text-muted"><small>N/A</small></span>' ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($observaciones_truncadas)): ?>
                                                            <small><?= $observaciones_truncadas ?></small>
                                                            <?php if (!empty($observaciones_completas)): ?>
                                                                <button type="button" class="btn btn-sm btn-link p-0 ms-1" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#modalObservaciones<?= $movimiento['id_movimiento'] ?>"
                                                                        title="Ver observaciones completas">
                                                                    <i class="fas fa-eye text-primary"></i>
                                                                </button>
                                                                <!-- Modal para observaciones completas -->
                                                                <div class="modal fade" id="modalObservaciones<?= $movimiento['id_movimiento'] ?>" tabindex="-1" aria-hidden="true">
                                                                    <div class="modal-dialog modal-dialog-centered">
                                                                        <div class="modal-content">
                                                                            <div class="modal-header">
                                                                                <h5 class="modal-title">Observaciones del Movimiento</h5>
                                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                            </div>
                                                                            <div class="modal-body">
                                                                                <p class="text-muted mb-2"><small>Movimiento #<?= $movimiento['id_movimiento'] ?> - <?= htmlspecialchars($movimiento['nombre_producto']) ?> (<?= htmlspecialchars($movimiento['talle']) ?>, <?= htmlspecialchars($movimiento['color']) ?>)</small></p>
                                                                                <div class="border rounded p-3 bg-light" style="white-space: pre-wrap; font-family: monospace; font-size: 0.9em;"><?= $observaciones_completas ?></div>
                                                                            </div>
                                                                            <div class="modal-footer">
                                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted"><small>-</small></span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center mb-0">No hay movimientos de stock registrados</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; render_footer(); ?>
