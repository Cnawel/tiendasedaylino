<?php
/**
 * ========================================================================
 * PANEL DE MARKETING - Tienda Seda y Lino
 * ========================================================================
 * Panel para usuarios con rol Marketing que permite:
 * - Gestionar productos (crear, editar, eliminar)
 * - Subir imágenes de productos
 * - Gestionar stock y variantes (tallas, colores)
 * - Importar productos desde CSV
 * - Descargar template CSV para importación
 * 
 * Funciones principales:
 * - CRUD completo de productos
 * - Gestión de fotos de productos
 * - Gestión de stock por talle y color
 * - Importación masiva desde CSV
 * 
 * Variables principales:
 * - $id_usuario: ID del usuario marketing actual
 * - $usuario_actual: Datos del usuario actual
 * - $mensaje/$mensaje_tipo: Mensajes de feedback
 * 
 * Tablas utilizadas: Productos, Fotos_Producto, Stock_Variantes, Categorias
 * ========================================================================
 */
session_start();

// ============================================================================
// VERIFICACIÓN DE ACCESO - SOLO USUARIOS MARKETING
// ============================================================================

// Cargar sistema de autenticación centralizado
require_once 'includes/auth_check.php';

// Verificar que el usuario esté logueado y tenga rol marketing
requireRole('marketing');

// Obtener información del usuario actual
$id_usuario = getCurrentUserId();
$usuario_actual = getCurrentUser();

// Conectar a la base de datos
require_once 'config/database.php';

// Configurar título de la página
$titulo_pagina = 'Panel de Marketing';

// ============================================================================
// PROCESAMIENTO DE FORMULARIOS
// ============================================================================

$mensaje = '';
$mensaje_tipo = '';

// ============================================================================
// PROCESAR CARGA MASIVA CSV
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['procesar_csv'])) {
    if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] === UPLOAD_ERR_OK) {
        $archivo_temporal = $_FILES['archivo_csv']['tmp_name'];
        $nombre_archivo = $_FILES['archivo_csv']['name'];
        
        // Validar extensión
        $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            $mensaje = 'El archivo debe ser un CSV válido.';
            $mensaje_tipo = 'danger';
        } else {
            // Procesar CSV y redirigir a página de confirmación
            $productos_csv = procesarCSV($archivo_temporal);
            
            if (empty($productos_csv)) {
                $mensaje = 'No se encontraron productos válidos en el CSV.';
                $mensaje_tipo = 'warning';
            } else {
                // Guardar datos en sesión para la página de confirmación
                $_SESSION['productos_csv_pendientes'] = $productos_csv;
                $_SESSION['nombre_archivo_csv'] = $nombre_archivo;
                header('Location: marketing-confirmar-csv.php');
                exit;
            }
        }
    } else {
        $mensaje = 'Error al subir el archivo CSV.';
        $mensaje_tipo = 'danger';
    }
}

// ============================================================================
// CREAR NUEVO PRODUCTO
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_producto'])) {
    $nombre_producto = trim($_POST['nombre_producto'] ?? '');
    $nuevo_nombre_producto = trim($_POST['nuevo_nombre_producto'] ?? '');
    $descripcion_producto = trim($_POST['descripcion_producto'] ?? '');
    $precio_actual = floatval($_POST['precio_actual'] ?? 0);
    $id_categoria = intval($_POST['id_categoria'] ?? 0);
    $genero = $_POST['genero'] ?? '';
    
    // Determinar el nombre final del producto
    if ($nombre_producto === '__NUEVO__' && $nuevo_nombre_producto !== '') {
        $nombre_producto_final = $nuevo_nombre_producto;
    } elseif ($nombre_producto !== '__NUEVO__' && $nombre_producto !== '') {
        $nombre_producto_final = $nombre_producto;
    } else {
        $nombre_producto_final = '';
    }
    
    // Validar datos básicos
    $generos_validos = ['hombre', 'mujer', 'unisex'];
    
    if ($nombre_producto_final === '' || $precio_actual <= 0 || $id_categoria <= 0 || !in_array($genero, $generos_validos)) {
        $mensaje = 'Datos inválidos. Completa todos los campos correctamente.';
        $mensaje_tipo = 'danger';
    } else {
        // Iniciar transacción
        $mysqli->begin_transaction();
        
        try {
            // Insertar producto
            $stmt = $mysqli->prepare("INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('ssdis', $nombre_producto_final, $descripcion_producto, $precio_actual, $id_categoria, $genero);
            
            if (!$stmt->execute()) {
                throw new Exception('Error al crear el producto');
            }
            
            $id_producto_nuevo = $mysqli->insert_id;
            
            // Procesar imágenes individuales si se subieron
            $imagenes_subidas = [];
            $imagenes_inputs = [
                'imagen_miniatura' => 0,
                'imagen_principal' => 1,
                'imagen_extra1' => 2,
                'imagen_extra2' => 3
            ];
            
            foreach ($imagenes_inputs as $input_name => $index) {
                if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
                    $imagenes_subidas[$index] = subirImagenIndividual($id_producto_nuevo, $_FILES[$input_name], $index);
                }
            }
            
            // Insertar registro de fotos (sin color específico - fotos por defecto)
            $stmt_fotos = $mysqli->prepare("INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color) VALUES (?, ?, ?, ?, ?, NULL)");
            $foto_miniatura = $imagenes_subidas[0] ?? null;  // IMAGEN 0: Miniatura
            $foto1 = $imagenes_subidas[1] ?? null;           // IMAGEN 1: Principal
            $foto2 = $imagenes_subidas[2] ?? null;           // IMAGEN 2: Extra 1
            $foto3 = $imagenes_subidas[3] ?? null;           // IMAGEN 3: Extra 2
            $stmt_fotos->bind_param('issss', $id_producto_nuevo, $foto_miniatura, $foto1, $foto2, $foto3);
            $stmt_fotos->execute();
            
            // Procesar variantes si se proporcionaron
            if (isset($_POST['variantes']) && is_array($_POST['variantes'])) {
                foreach ($_POST['variantes'] as $variante) {
                    $talle = trim($variante['talle'] ?? '');
                    $color = trim($variante['color'] ?? '');
                    $stock = intval($variante['stock'] ?? 0);
                    
                    if ($talle !== '' && $color !== '' && $stock >= 0) {
                        $stmt_variante = $mysqli->prepare("INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES (?, ?, ?, ?)");
                        $stmt_variante->bind_param('issi', $id_producto_nuevo, $talle, $color, $stock);
                        $stmt_variante->execute();
                        
                        // Registrar movimiento de stock inicial
                        $id_variante_nueva = $mysqli->insert_id;
                        $stmt_movimiento = $mysqli->prepare("INSERT INTO Movimientos_Stock (id_variante, tipo_movimiento, cantidad, id_usuario, observaciones) VALUES (?, 'ingreso', ?, ?, 'Stock inicial creado por marketing')");
                        $stmt_movimiento->bind_param('iii', $id_variante_nueva, $stock, $id_usuario);
                        $stmt_movimiento->execute();
                    }
                }
            }
            
            $mysqli->commit();
            $mensaje = 'Producto creado exitosamente con ID: ' . $id_producto_nuevo;
            $mensaje_tipo = 'success';
            
        } catch (Exception $e) {
            $mysqli->rollback();
            $mensaje = 'Error al crear el producto: ' . $e->getMessage();
            $mensaje_tipo = 'danger';
        }
    }
}

// ============================================================================
// FUNCIÓN PARA PROCESAR ARCHIVO CSV
// ============================================================================
function procesarCSV($archivo_temporal) {
    $productos = [];
    $errores = [];
    
    if (($handle = fopen($archivo_temporal, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        
        // Validar headers requeridos
        $headers_requeridos = [
            'nombre_producto', 'descripcion_producto', 'precio_actual', 
            'id_categoria', 'genero', 'talle', 'color', 'stock'
        ];
        
        $headers_validos = true;
        foreach ($headers_requeridos as $header) {
            if (!in_array($header, $headers)) {
                $errores[] = "Header requerido faltante: $header";
                $headers_validos = false;
            }
        }
        
        if (!$headers_validos) {
            fclose($handle);
            return [];
        }
        
        $linea = 1;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $linea++;
            
            // Saltar líneas vacías
            if (empty(array_filter($data))) {
                continue;
            }
            
            // Crear array asociativo
            $producto = array_combine($headers, $data);
            
            // Validar datos básicos
            if (empty($producto['nombre_producto']) || empty($producto['precio_actual'])) {
                $errores[] = "Línea $linea: Nombre y precio son obligatorios";
                continue;
            }
            
            // Validar precio
            if (!is_numeric($producto['precio_actual']) || $producto['precio_actual'] <= 0) {
                $errores[] = "Línea $linea: Precio inválido";
                continue;
            }
            
            // Validar género
            $generos_validos = ['hombre', 'mujer', 'unisex'];
            if (!in_array(strtolower($producto['genero']), $generos_validos)) {
                $errores[] = "Línea $linea: Género inválido (debe ser: hombre, mujer, unisex)";
                continue;
            }
            
            // Validar categoría
            if (!is_numeric($producto['id_categoria']) || $producto['id_categoria'] <= 0) {
                $errores[] = "Línea $linea: ID de categoría inválido";
                continue;
            }
            
            // Validar variantes
            if (empty($producto['talle']) || empty($producto['color'])) {
                $errores[] = "Línea $linea: Talle y color son obligatorios";
                continue;
            }
            
            // Validar stock
            if (!is_numeric($producto['stock']) || $producto['stock'] < 0) {
                $errores[] = "Línea $linea: Stock inválido";
                continue;
            }
            
            // Normalizar datos
            $producto['precio_actual'] = floatval($producto['precio_actual']);
            $producto['id_categoria'] = intval($producto['id_categoria']);
            $producto['genero'] = strtolower($producto['genero']);
            $producto['stock'] = intval($producto['stock']);
            $producto['linea'] = $linea;
            
            $productos[] = $producto;
        }
        
        fclose($handle);
    }
    
    // Guardar errores en sesión si los hay
    if (!empty($errores)) {
        $_SESSION['errores_csv'] = $errores;
    }
    
    return $productos;
}

// ============================================================================
// FUNCIÓN PARA SUBIR IMAGEN INDIVIDUAL LOCALMENTE
// ============================================================================
function subirImagenIndividual($id_producto, $archivo, $indice) {
    // Crear directorio del producto si no existe
    $directorio_producto = 'imagenes/productos/producto_' . $id_producto . '/';
    
    if (!file_exists($directorio_producto)) {
        mkdir($directorio_producto, 0755, true);
    }
    
    $archivo_temporal = $archivo['tmp_name'];
    $nombre_original = $archivo['name'];
    $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    
    // Validar tipo de archivo
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($extension, $extensiones_permitidas)) {
        throw new Exception('Formato de archivo no válido: ' . $extension);
    }
    
    // Validar tamaño del archivo (máximo 5MB)
    $tamaño_maximo = 5 * 1024 * 1024; // 5MB en bytes
    if ($archivo['size'] > $tamaño_maximo) {
        throw new Exception('El archivo es demasiado grande. Máximo 5MB.');
    }
    
    // Generar nombre único basado en el índice
    $nombres_imagen = ['miniatura', 'principal', 'extra1', 'extra2'];
    $nombre_archivo = 'imagen_' . $nombres_imagen[$indice] . '_' . time() . '_' . $id_producto . '.' . $extension;
    $ruta_completa = $directorio_producto . $nombre_archivo;
    
    // Subir archivo
    if (move_uploaded_file($archivo_temporal, $ruta_completa)) {
        return $ruta_completa;
    } else {
        throw new Exception('Error al subir la imagen: ' . $nombre_original);
    }
}

// ============================================================================
// OBTENER DATOS PARA FORMULARIOS
// ============================================================================

// Obtener categorías
$sql_categorias = "SELECT id_categoria, nombre_categoria FROM Categorias ORDER BY nombre_categoria";
$categorias = $mysqli->query($sql_categorias);

// Obtener productos existentes con sus variantes y colores disponibles
$sql_productos = "
    SELECT p.id_producto, p.nombre_producto, p.precio_actual, p.genero, c.nombre_categoria,
           COUNT(DISTINCT sv.id_variante) as total_variantes,
           SUM(sv.stock) as stock_total,
           COUNT(DISTINCT fp.color) as colores_disponibles,
           GROUP_CONCAT(DISTINCT fp.color ORDER BY fp.color SEPARATOR ', ') as lista_colores
    FROM Productos p
    INNER JOIN Categorias c ON c.id_categoria = p.id_categoria
    LEFT JOIN Stock_Variantes sv ON sv.id_producto = p.id_producto
    LEFT JOIN Fotos_Producto fp ON fp.id_producto = p.id_producto AND fp.color IS NOT NULL
    GROUP BY p.id_producto, p.nombre_producto, p.precio_actual, p.genero, c.nombre_categoria
    ORDER BY p.id_producto DESC
    LIMIT 50
";
$productos_existentes = $mysqli->query($sql_productos);

// Obtener nombres únicos de productos para el select
$sql_nombres_productos = "
    SELECT DISTINCT nombre_producto 
    FROM Productos 
    ORDER BY nombre_producto ASC
";
$nombres_productos_result = $mysqli->query($sql_nombres_productos);
$nombres_productos = [];
while ($row = $nombres_productos_result->fetch_assoc()) {
    $nombres_productos[] = $row['nombre_producto'];
}

// Talles disponibles
$talles_disponibles = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '28', '30', '32', '34', '36', '38', '40', '42', '44', '46'];

// Colores disponibles
$colores_disponibles = ['Negro', 'Blanco', 'Azul', 'Rojo', 'Verde', 'Amarillo', 'Rosa', 'Gris', 'Marrón', 'Beige', 'Celeste', 'Naranja', 'Violeta', 'Turquesa'];
?>

<?php include 'includes/header.php'; ?>
    <style>
        .marketing-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .marketing-header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .form-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .product-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        .variante-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }
        
        .upload-area {
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .upload-area:hover {
            background: #e9ecef;
            border-color: #764ba2;
        }
        
        .upload-area.dragover {
            background: #e3f2fd;
            border-color: #2196f3;
        }
        
        .image-preview {
            border-radius: 8px;
            margin: 0.5rem;
        }
        
        .stats-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .stats-card-hover {
            cursor: pointer;
        }
        
        .stats-card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            background: rgba(255, 255, 255, 1);
        }
        
        .stats-card-hover:hover .stats-icon {
            transform: scale(1.1);
        }
        
        .stats-card-hover:hover h3 {
            transform: scale(1.05);
        }
        
        .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        /* Cursor pointer para elementos clickeables */
        .btn, .btn-sm, .btn-lg, 
        .btn-outline-primary, .btn-outline-secondary, .btn-outline-info, .btn-outline-danger,
        .btn-primary, .btn-success, .btn-warning, .btn-danger,
        .card, .product-card,
        a, [onclick], [data-bs-toggle], [data-bs-target] {
            cursor: pointer !important;
        }
        
        
        .image-preview {
            cursor: pointer;
        }
    </style>

    <main class="marketing-page">
        <div class="container">
            <!-- Header -->
            <div class="marketing-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1><i class="fas fa-bullhorn me-3"></i>Panel de Marketing</h1>
                        <p class="mb-0">Gestión de productos y contenido</p>
                        <div class="mt-3">
                            <span class="badge bg-primary me-2">Usuario: <?= htmlspecialchars($usuario_actual['nombre'] . ' ' . $usuario_actual['apellido']) ?></span>
                            <span class="badge bg-success">Rol: <?= strtoupper($usuario_actual['rol']) ?></span>
                        </div>
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
            
            <!-- Mensajes -->
            <?php if ($mensaje): ?>
            <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
                <?php if ($mensaje_tipo === 'success'): ?>
                    <i class="fas fa-check-circle me-2"></i>
                <?php elseif ($mensaje_tipo === 'warning'): ?>
                    <i class="fas fa-exclamation-triangle me-2"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle me-2"></i>
                <?php endif; ?>
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Estadísticas rápidas -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-3">
                    <a href="#productos-existentes" class="text-decoration-none">
                        <div class="stats-card stats-card-hover">
                            <div class="stats-icon text-primary">
                                <i class="fas fa-boxes-stacked"></i>
                            </div>
                            <h3><?= $productos_existentes->num_rows ?></h3>
                            <p class="text-muted mb-0">Productos Totales</p>
                            <small class="text-primary">
                                <i class="fas fa-arrow-down me-1"></i>Ver todos los productos
                            </small>
                        </div>
                    </a>
                </div>
                <div class="col-lg-4 col-md-6 mb-3">
                    <a href="#crear-producto" class="text-decoration-none">
                        <div class="stats-card stats-card-hover">
                            <div class="stats-icon text-success">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <h3>+</h3>
                            <p class="text-muted mb-0">Crear Nuevo</p>
                            <small class="text-success">
                                <i class="fas fa-arrow-down me-1"></i>Crear producto individual
                            </small>
                        </div>
                    </a>
                </div>
                <div class="col-lg-4 col-md-6 mb-3">
                    <a href="#carga-masiva" class="text-decoration-none">
                        <div class="stats-card stats-card-hover">
                            <div class="stats-icon text-warning">
                                <i class="fas fa-upload"></i>
                            </div>
                            <h3>📤</h3>
                            <p class="text-muted mb-0">Carga Masiva</p>
                            <small class="text-warning">
                                <i class="fas fa-arrow-down me-1"></i>Carga masiva de productos
                            </small>
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- Formulario de creación de productos -->
            <div class="form-section" id="crear-producto">
                <h3 class="mb-4"><i class="fas fa-plus-circle me-2"></i>Crear Producto Individual</h3>
                
                <form method="POST" enctype="multipart/form-data" id="productoForm">
                    <input type="hidden" name="crear_producto" value="1">
                    
                    <!-- Información básica del producto -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label"><strong>Nombre del Producto</strong></label>
                            <select class="form-select" name="nombre_producto" id="nombreProductoSelect" required onchange="toggleNuevoProducto()">
                                <option value="">Seleccionar producto...</option>
                                <?php foreach ($nombres_productos as $nombre): ?>
                                    <option value="<?= htmlspecialchars($nombre) ?>">
                                        <?= htmlspecialchars($nombre) ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="__NUEVO__">+ Crear nuevo producto</option>
                            </select>
                            <div id="nuevoProductoInput" style="display: none;" class="mt-2">
                                <input type="text" class="form-control" name="nuevo_nombre_producto" 
                                       placeholder="Escribir nombre del nuevo producto...">
                                <small class="text-muted">Escribe el nombre del nuevo producto</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><strong>Precio</strong></label>
                            <input type="number" step="0.01" min="0" class="form-control" name="precio_actual" required 
                                   placeholder="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><strong>Género</strong></label>
                            <select class="form-select" name="genero" required>
                                <option value="">Seleccionar...</option>
                                <option value="hombre">Hombre</option>
                                <option value="mujer">Mujer</option>
                                <option value="unisex">Unisex</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label"><strong>Categoría</strong></label>
                            <select class="form-select" name="id_categoria" required>
                                <option value="">Seleccionar categoría...</option>
                                <?php while ($categoria = $categorias->fetch_assoc()): ?>
                                    <option value="<?= $categoria['id_categoria'] ?>">
                                        <?= htmlspecialchars($categoria['nombre_categoria']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>Descripción</strong></label>
                            <textarea class="form-control" name="descripcion_producto" rows="3" 
                                      placeholder="Describe las características del producto..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Subida de imágenes -->
                    <div class="mb-4">
                        <label class="form-label"><strong>Imágenes del Producto</strong></label>
                        
                        <!-- IMAGEN 0: Miniatura -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label text-info">
                                    <i class="fas fa-image me-1"></i><strong>IMAGEN 0:</strong> Miniatura
                                </label>
                            </div>
                            <div class="col-md-9">
                                <input type="file" class="form-control" name="imagen_miniatura" accept="image/*" 
                                       id="imagenMiniatura" onchange="previewImage(this, 'previewMiniatura')">
                                <div id="previewMiniatura" class="mt-2"></div>
                            </div>
                        </div>
                        
                        <!-- IMAGEN 1: Principal -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label text-primary">
                                    <i class="fas fa-image me-1"></i><strong>IMAGEN 1:</strong> Principal
                                </label>
                            </div>
                            <div class="col-md-9">
                                <input type="file" class="form-control" name="imagen_principal" accept="image/*" 
                                       id="imagenPrincipal" onchange="previewImage(this, 'previewPrincipal')">
                                <div id="previewPrincipal" class="mt-2"></div>
                            </div>
                        </div>
                        
                        <!-- IMAGEN 2: Extra 1 -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label text-success">
                                    <i class="fas fa-image me-1"></i><strong>IMAGEN 2:</strong> Extra 1
                                </label>
                            </div>
                            <div class="col-md-9">
                                <input type="file" class="form-control" name="imagen_extra1" accept="image/*" 
                                       id="imagenExtra1" onchange="previewImage(this, 'previewExtra1')">
                                <div id="previewExtra1" class="mt-2"></div>
                            </div>
                        </div>
                        
                        <!-- IMAGEN 3: Extra 2 -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label text-warning">
                                    <i class="fas fa-image me-1"></i><strong>IMAGEN 3:</strong> Extra 2
                                </label>
                            </div>
                            <div class="col-md-9">
                                <input type="file" class="form-control" name="imagen_extra2" accept="image/*" 
                                       id="imagenExtra2" onchange="previewImage(this, 'previewExtra2')">
                                <div id="previewExtra2" class="mt-2"></div>
                            </div>
                        </div>
                        
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 5MB por imagen.
                        </small>
                        
                        <div class="alert alert-info mt-3">
                            <h6><i class="fas fa-palette me-2"></i>Información sobre Fotos por Color:</h6>
                            <ul class="mb-0">
                                <li><strong>IMAGEN 0 (Miniatura):</strong> Se mantiene fija para todos los colores</li>
                                <li><strong>IMAGEN 1 (Principal):</strong> Cambia dinámicamente según el color seleccionado</li>
                                <li><strong>IMAGEN 2 (Extra 1):</strong> Cambia dinámicamente según el color seleccionado</li>
                                <li><strong>IMAGEN 3 (Extra 2):</strong> Se mantiene fija para todos los colores</li>
                            </ul>
                            <small class="text-muted">
                                <i class="fas fa-lightbulb me-1"></i>
                                Para agregar fotos por color específico, usa el editor de productos después de crear el producto base.
                            </small>
                        </div>
                    </div>
                    
                    <!-- Variantes del producto -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label mb-0"><strong>Variantes del Producto</strong></label>
                            <button type="button" class="btn btn-sm btn-success" id="agregarVariante">
                                <i class="fas fa-plus me-1"></i>Agregar Variante
                            </button>
                        </div>
                        <div id="variantesContainer">
                            <!-- Las variantes se agregarán dinámicamente aquí -->
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Define los diferentes talles y colores disponibles para este producto.
                        </small>
                    </div>
                    
                    <!-- Botones de acción -->
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-save me-2"></i>Crear Producto
                        </button>
                        <button type="reset" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-undo me-2"></i>Limpiar Formulario
                        </button>
                    </div>
                </form>
            </div>
            
            
            <!-- Lista de productos existentes -->
            <div class="form-section" id="productos-existentes">
                <h3 class="mb-4"><i class="fas fa-list me-2"></i>Productos Existentes</h3>
                
                <?php if ($productos_existentes && $productos_existentes->num_rows): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th><i class="fas fa-hashtag me-1"></i>ID</th>
                                    <th><i class="fas fa-box me-1"></i>Producto</th>
                                    <th><i class="fas fa-tag me-1"></i>Categoría</th>
                                    <th><i class="fas fa-venus-mars me-1"></i>Género</th>
                                    <th><i class="fas fa-dollar-sign me-1"></i>Precio</th>
                                    <th><i class="fas fa-layer-group me-1"></i>Variantes</th>
                                    <th><i class="fas fa-warehouse me-1"></i>Stock Total</th>
                                    <th><i class="fas fa-cogs me-1"></i>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($producto = $productos_existentes->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary">#<?= $producto['id_producto'] ?></span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($producto['nombre_producto']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= htmlspecialchars($producto['nombre_categoria']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= ucfirst($producto['genero']) ?></span>
                                    </td>
                                    <td>
                                        <strong class="text-success">$<?= number_format($producto['precio_actual'], 2, ',', '.') ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning"><?= $producto['total_variantes'] ?></span>
                                    </td>
                                    
                                    <td>
                                        <span class="badge bg-success"><?= $producto['stock_total'] ?? 0 ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="marketing-editar-producto.php?id=<?= $producto['id_producto'] ?>" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="Editar producto">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="detalle-producto.php?id=<?= $producto['id_producto'] ?>" 
                                               class="btn btn-sm btn-outline-info" 
                                               title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Información adicional -->
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Total de productos:</strong> <?= $productos_existentes->num_rows ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-success">
                                <i class="fas fa-chart-bar me-2"></i>
                                <strong>Stock total:</strong> <?php 
                                    $productos_existentes->data_seek(0); // Reset pointer
                                    $total_stock = 0;
                                    while ($prod = $productos_existentes->fetch_assoc()) {
                                        $total_stock += $prod['stock_total'] ?? 0;
                                    }
                                    echo $total_stock;
                                ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-box-open fa-4x mb-3"></i>
                        <h4>No hay productos creados aún</h4>
                        <p>Comienza creando tu primer producto usando el formulario de arriba.</p>
                        <a href="#crear-producto" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Crear Primer Producto
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Carga masiva CSV -->
            <div class="form-section" id="carga-masiva">
                <h3 class="mb-4"><i class="fas fa-upload me-2"></i>Carga Masiva de Productos</h3>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle me-2"></i>Instrucciones para carga masiva:</h5>
                            <ul class="mb-0">
                                <li>Sube un archivo CSV con las columnas requeridas</li>
                                <li>Se mostrará una vista previa antes de confirmar la carga</li>
                                <li>Cada fila representa una variante de producto</li>
                                <li>Los productos con el mismo nombre se agruparán automáticamente</li>
                            </ul>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="procesar_csv" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label"><strong>Archivo CSV</strong></label>
                                <input type="file" class="form-control" name="archivo_csv" accept=".csv" required>
                                <small class="text-muted">
                                    <i class="fas fa-download me-1"></i>
                                    <a href="marketing-descargar-template.php" class="text-decoration-none">
                                        Descargar plantilla CSV
                                    </a>
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Procesar CSV
                            </button>
                        </form>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-table me-2"></i>Columnas Requeridas</h6>
                            </div>
                            <div class="card-body">
                                <small>
                                    <strong>nombre_producto</strong><br>
                                    <strong>descripcion_producto</strong><br>
                                    <strong>precio_actual</strong><br>
                                    <strong>id_categoria</strong><br>
                                    <strong>genero</strong> (hombre/mujer/unisex)<br>
                                    <strong>talle</strong><br>
                                    <strong>color</strong><br>
                                    <strong>stock</strong>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Botones de navegación -->
            <div class="text-center mt-4">
                <a href="perfil.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-user me-2"></i>Mi Perfil
                </a>
                <a href="catalogo.php" class="btn btn-outline-info me-2">
                    <i class="fas fa-shopping-bag me-2"></i>Ver Catálogo
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-2"></i>Volver al Inicio
                </a>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    
    <script>
        // Función para previsualizar imágenes individuales
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                
                if (validTypes.includes(file.type)) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'image-preview';
                        img.title = file.name;
                        img.style.maxWidth = '150px';
                        img.style.maxHeight = '150px';
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.innerHTML = '<small class="text-danger">Formato no válido. Use JPG, PNG o GIF.</small>';
                }
            }
        }
        
        // Función para hacer scroll suave a secciones
        function scrollToSection(sectionId) {
            const element = document.getElementById(sectionId);
            if (element) {
                element.scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }
        
        // Función para hacer scroll al formulario (mantener compatibilidad)
        function scrollToForm() {
            scrollToSection('crear-producto');
        }
        
        // Agregar event listeners para scroll suave
        document.addEventListener('DOMContentLoaded', function() {
            // Manejar clicks en enlaces de ancla
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href').substring(1);
                    scrollToSection(targetId);
                });
            });
        });
        
        // Función para editar producto (placeholder)
        function editarProducto(id) {
            alert(`Función de edición para producto ID: ${id}\n\nEsta funcionalidad se implementará próximamente.`);
        }
        
        // Función para mostrar/ocultar campo de nuevo producto
        function toggleNuevoProducto() {
            const select = document.getElementById('nombreProductoSelect');
            const nuevoInput = document.getElementById('nuevoProductoInput');
            
            if (select.value === '__NUEVO__') {
                nuevoInput.style.display = 'block';
                nuevoInput.querySelector('input').required = true;
            } else {
                nuevoInput.style.display = 'none';
                nuevoInput.querySelector('input').required = false;
                nuevoInput.querySelector('input').value = '';
            }
        }
        
        // Gestión de variantes
        let varianteCount = 0;
        const variantesContainer = document.getElementById('variantesContainer');
        const agregarVarianteBtn = document.getElementById('agregarVariante');
        
        const tallesDisponibles = <?= json_encode($talles_disponibles) ?>;
        const coloresDisponibles = <?= json_encode($colores_disponibles) ?>;
        
        agregarVarianteBtn.addEventListener('click', () => {
            agregarVariante();
        });
        
        function agregarVariante() {
            const varianteDiv = document.createElement('div');
            varianteDiv.className = 'variante-item';
            varianteDiv.innerHTML = `
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Talle</label>
                        <select class="form-select" name="variantes[${varianteCount}][talle]" required>
                            <option value="">Seleccionar...</option>
                            ${tallesDisponibles.map(talle => `<option value="${talle}">${talle}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Color</label>
                        <select class="form-select" name="variantes[${varianteCount}][color]" required>
                            <option value="">Seleccionar...</option>
                            ${coloresDisponibles.map(color => `<option value="${color}">${color}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Stock Inicial</label>
                        <input type="number" min="0" class="form-control" name="variantes[${varianteCount}][stock]" value="0" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarVariante(this)">
                            <i class="fas fa-trash me-1"></i>Eliminar
                        </button>
                    </div>
                </div>
            `;
            
            variantesContainer.appendChild(varianteDiv);
            varianteCount++;
        }
        
        function eliminarVariante(button) {
            button.closest('.variante-item').remove();
        }
        
        // Agregar una variante inicial
        agregarVariante();
    </script>
    
    <script>
    /**
     * Confirmar logout y asegurar redirección
     */
    function confirmLogout() {
        if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
            // Forzar redirección después de un breve delay para asegurar que el logout se procese
            setTimeout(function() {
                window.location.href = 'login.php?logout=1';
            }, 100);
            return true;
        }
        return false;
    }
    </script>

<?php include 'includes/footer.php'; render_footer(); ?>
