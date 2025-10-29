<?php
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

// ============================================================================
// VALIDAR ID DE PRODUCTO
// ============================================================================

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: marketing.php');
    exit;
}

$id_producto = intval($_GET['id']);

// ============================================================================
// EDITAR PRODUCTO EXISTENTE
// ============================================================================

$mensaje = '';
$mensaje_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_producto'])) {
    $nombre_producto = trim($_POST['nombre_producto'] ?? '');
    $descripcion_producto = trim($_POST['descripcion_producto'] ?? '');
    $precio_actual = floatval($_POST['precio_actual'] ?? 0);
    $id_categoria = intval($_POST['id_categoria'] ?? 0);
    $genero = $_POST['genero'] ?? '';
    
    // Validar datos básicos
    $generos_validos = ['hombre', 'mujer', 'unisex'];
    
    if ($nombre_producto === '' || $precio_actual <= 0 || $id_categoria <= 0 || !in_array($genero, $generos_validos)) {
        $mensaje = 'Datos inválidos. Completa todos los campos correctamente.';
        $mensaje_tipo = 'danger';
    } else {
        // Iniciar transacción
        $mysqli->begin_transaction();
        
        try {
            // Actualizar producto principal
            $stmt = $mysqli->prepare("UPDATE Productos SET nombre_producto = ?, descripcion_producto = ?, precio_actual = ?, id_categoria = ?, genero = ? WHERE id_producto = ?");
            $stmt->bind_param('ssdisi', $nombre_producto, $descripcion_producto, $precio_actual, $id_categoria, $genero, $id_producto);
            
            if (!$stmt->execute()) {
                throw new Exception('Error al actualizar el producto');
            }
            
            // Procesar imágenes por color (dos imágenes por color)
            $colores_para_procesar = [];
            if (isset($_POST['variantes']) && is_array($_POST['variantes'])) {
                foreach ($_POST['variantes'] as $variante_post) {
                    $color_post = trim($variante_post['color'] ?? '');
                    if ($color_post !== '') {
                        $colores_para_procesar[$color_post] = true;
                    }
                }
            }
            
            // Procesar imágenes genéricas (miniatura y grupal)
            $generica_miniatura_ruta = null;
            $generica_grupal_ruta = null;
            if (isset($_FILES['imagen_generica_miniatura']) && $_FILES['imagen_generica_miniatura']['error'] === UPLOAD_ERR_OK) {
                $generica_miniatura_ruta = subirImagenGenerica($id_producto, $_FILES['imagen_generica_miniatura'], 'miniatura');
            }
            if (isset($_FILES['imagen_generica_grupal']) && $_FILES['imagen_generica_grupal']['error'] === UPLOAD_ERR_OK) {
                $generica_grupal_ruta = subirImagenGenerica($id_producto, $_FILES['imagen_generica_grupal'], 'grupal');
            }

            if ($generica_miniatura_ruta !== null || $generica_grupal_ruta !== null) {
                $stmt_fg = $mysqli->prepare("SELECT id_foto, foto_prod_miniatura, foto3_prod FROM Fotos_Producto WHERE id_producto = ? AND color IS NULL LIMIT 1");
                $stmt_fg->bind_param('i', $id_producto);
                $stmt_fg->execute();
                $fg_exist = $stmt_fg->get_result()->fetch_assoc();

                $nueva_min = $generica_miniatura_ruta ?? ($fg_exist['foto_prod_miniatura'] ?? null);
                $nueva_grp = $generica_grupal_ruta ?? ($fg_exist['foto3_prod'] ?? null);

                if ($fg_exist) {
                    $stmt_up_fg = $mysqli->prepare("UPDATE Fotos_Producto SET foto_prod_miniatura = ?, foto3_prod = ? WHERE id_foto = ?");
                    $stmt_up_fg->bind_param('ssi', $nueva_min, $nueva_grp, $fg_exist['id_foto']);
                    $stmt_up_fg->execute();
                } else {
                    $stmt_in_fg = $mysqli->prepare("INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto3_prod, color) VALUES (?, ?, ?, NULL)");
                    $stmt_in_fg->bind_param('iss', $id_producto, $nueva_min, $nueva_grp);
                    $stmt_in_fg->execute();
                }
            }

            if (!empty($colores_para_procesar) && isset($_FILES['imagenes_color'])) {
                foreach (array_keys($colores_para_procesar) as $color_proc) {
                    $subidas_color = ['img1' => null, 'img2' => null];
                    // Reconstruir arrays de archivos para cada slot si existe
                    foreach (['img1','img2'] as $slot) {
                        if (
                            isset($_FILES['imagenes_color']['error'][$color_proc][$slot]) &&
                            $_FILES['imagenes_color']['error'][$color_proc][$slot] === UPLOAD_ERR_OK
                        ) {
                            $archivo_slot = [
                                'name' => $_FILES['imagenes_color']['name'][$color_proc][$slot] ?? null,
                                'type' => $_FILES['imagenes_color']['type'][$color_proc][$slot] ?? null,
                                'tmp_name' => $_FILES['imagenes_color']['tmp_name'][$color_proc][$slot] ?? null,
                                'error' => $_FILES['imagenes_color']['error'][$color_proc][$slot] ?? UPLOAD_ERR_NO_FILE,
                                'size' => $_FILES['imagenes_color']['size'][$color_proc][$slot] ?? 0,
                            ];
                            $subidas_color[$slot] = subirImagenColor($id_producto, $color_proc, $archivo_slot, $slot);
                        }
                    }

                    // Leer existente por color
                    $stmt_foto_color = $mysqli->prepare("SELECT id_foto, foto1_prod, foto2_prod FROM Fotos_Producto WHERE id_producto = ? AND color = ? LIMIT 1");
                    $stmt_foto_color->bind_param('is', $id_producto, $color_proc);
                    $stmt_foto_color->execute();
                    $foto_color_existente = $stmt_foto_color->get_result()->fetch_assoc();

                    $nueva_foto1 = $subidas_color['img1'] ?? ($foto_color_existente['foto1_prod'] ?? null);
                    $nueva_foto2 = $subidas_color['img2'] ?? ($foto_color_existente['foto2_prod'] ?? null);

                    if ($foto_color_existente) {
                        $stmt_update_color = $mysqli->prepare("UPDATE Fotos_Producto SET foto1_prod = ?, foto2_prod = ? WHERE id_foto = ?");
                        $stmt_update_color->bind_param('ssi', $nueva_foto1, $nueva_foto2, $foto_color_existente['id_foto']);
                        $stmt_update_color->execute();
                    } else {
                        // Insertar registro nuevo por color
                        $stmt_insert_color = $mysqli->prepare("INSERT INTO Fotos_Producto (id_producto, foto1_prod, foto2_prod, color) VALUES (?, ?, ?, ?)");
                        $stmt_insert_color->bind_param('isss', $id_producto, $nueva_foto1, $nueva_foto2, $color_proc);
                        $stmt_insert_color->execute();
                    }
                }
            }
            
            // Procesar variantes si se proporcionaron
            if (isset($_POST['variantes']) && is_array($_POST['variantes'])) {
                // Obtener variantes existentes para comparar cambios
                $stmt_variantes_existentes = $mysqli->prepare("SELECT id_variante, talle, color, stock FROM Stock_Variantes WHERE id_producto = ?");
                $stmt_variantes_existentes->bind_param('i', $id_producto);
                $stmt_variantes_existentes->execute();
                $variantes_existentes = $stmt_variantes_existentes->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // Crear array de variantes existentes para comparación
                $variantes_existentes_map = [];
                foreach ($variantes_existentes as $variante_existente) {
                    $key = $variante_existente['talle'] . '_' . $variante_existente['color'];
                    $variantes_existentes_map[$key] = $variante_existente;
                }
                
                // Procesar variantes enviadas
                $variantes_procesadas = [];
                foreach ($_POST['variantes'] as $variante) {
                    $talle = trim($variante['talle'] ?? '');
                    $color = trim($variante['color'] ?? '');
                    $stock = intval($variante['stock'] ?? 0);
                    
                    if ($talle !== '' && $color !== '' && $stock >= 0) {
                        $key = $talle . '_' . $color;
                        $variantes_procesadas[] = $key;
                        
                        if (isset($variantes_existentes_map[$key])) {
                            // Actualizar variante existente si el stock cambió
                            $variante_existente = $variantes_existentes_map[$key];
                            if ($variante_existente['stock'] != $stock) {
                                $stmt_update_variante = $mysqli->prepare("UPDATE Stock_Variantes SET stock = ? WHERE id_variante = ?");
                                $stmt_update_variante->bind_param('ii', $stock, $variante_existente['id_variante']);
                                $stmt_update_variante->execute();
                                
                                // Registrar movimiento de stock
                                $diferencia = $stock - $variante_existente['stock'];
                                $tipo_movimiento = $diferencia > 0 ? 'ingreso' : 'ajuste';
                                $cantidad_movimiento = abs($diferencia);
                                $observaciones_movimiento = 'Stock actualizado por marketing';
                                $stmt_movimiento = $mysqli->prepare("INSERT INTO Movimientos_Stock (id_variante, tipo_movimiento, cantidad, id_usuario, observaciones) VALUES (?, ?, ?, ?, ?)");
                                $stmt_movimiento->bind_param('isiis', $variante_existente['id_variante'], $tipo_movimiento, $cantidad_movimiento, $id_usuario, $observaciones_movimiento);
                                $stmt_movimiento->execute();
                            }
                        } else {
                            // Insertar nueva variante
                            $stmt_variante = $mysqli->prepare("INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES (?, ?, ?, ?)");
                            $stmt_variante->bind_param('issi', $id_producto, $talle, $color, $stock);
                            $stmt_variante->execute();
                            
                            // Registrar movimiento de stock inicial
                            $id_variante_nueva = $mysqli->insert_id;
                            $observaciones_nueva = 'Nueva variante creada por marketing';
                            $stmt_movimiento = $mysqli->prepare("INSERT INTO Movimientos_Stock (id_variante, tipo_movimiento, cantidad, id_usuario, observaciones) VALUES (?, 'ingreso', ?, ?, ?)");
                            $stmt_movimiento->bind_param('iiis', $id_variante_nueva, $stock, $id_usuario, $observaciones_nueva);
                            $stmt_movimiento->execute();
                        }
                    }
                }
                
                // Eliminar variantes que ya no están en el formulario
                foreach ($variantes_existentes_map as $key => $variante_existente) {
                    if (!in_array($key, $variantes_procesadas)) {
                        $stmt_delete_variante = $mysqli->prepare("DELETE FROM Stock_Variantes WHERE id_variante = ?");
                        $stmt_delete_variante->bind_param('i', $variante_existente['id_variante']);
                        $stmt_delete_variante->execute();
                        
                        // Registrar movimiento de eliminación
                        $cantidad_eliminacion = 0;
                        $observaciones_eliminacion = 'Variante eliminada por marketing';
                        $stmt_movimiento = $mysqli->prepare("INSERT INTO Movimientos_Stock (id_variante, tipo_movimiento, cantidad, id_usuario, observaciones) VALUES (?, 'ajuste', ?, ?, ?)");
                        $stmt_movimiento->bind_param('iiis', $variante_existente['id_variante'], $cantidad_eliminacion, $id_usuario, $observaciones_eliminacion);
                        $stmt_movimiento->execute();
                    }
                }
            }
            
            $mysqli->commit();
            $mensaje = 'Producto actualizado exitosamente';
            $mensaje_tipo = 'success';
            
        } catch (Exception $e) {
            $mysqli->rollback();
            $mensaje = 'Error al actualizar el producto: ' . $e->getMessage();
            $mensaje_tipo = 'danger';
        }
    }
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
// FUNCIÓN PARA SUBIR IMAGEN POR COLOR (dos slots: img1, img2)
// ============================================================================
function subirImagenColor($id_producto, $color, $archivo, $slot) {
    // Normalizar color para ruta de carpeta
    $color_normalizado = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $color));
    $directorio_producto_color = 'imagenes/productos/producto_' . $id_producto . '/' . $color_normalizado . '/';

    if (!file_exists($directorio_producto_color)) {
        mkdir($directorio_producto_color, 0755, true);
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

    // Generar nombre único por slot
    $slot_norm = in_array($slot, ['img1', 'img2']) ? $slot : 'img';
    $nombre_archivo = 'imagen_' . $slot_norm . '_' . time() . '_' . $id_producto . '.' . $extension;
    $ruta_completa = $directorio_producto_color . $nombre_archivo;

    if (move_uploaded_file($archivo_temporal, $ruta_completa)) {
        return $ruta_completa;
    }

    throw new Exception('Error al subir la imagen: ' . $nombre_original);
}

// ============================================================================
// FUNCIÓN PARA SUBIR IMAGEN GENÉRICA (miniatura | grupal)
// ============================================================================
function subirImagenGenerica($id_producto, $archivo, $tipo) {
    $tipo_norm = in_array($tipo, ['miniatura','grupal']) ? $tipo : 'generica';
    $directorio_producto = 'imagenes/productos/producto_' . $id_producto . '/';

    if (!file_exists($directorio_producto)) {
        mkdir($directorio_producto, 0755, true);
    }

    $archivo_temporal = $archivo['tmp_name'];
    $nombre_original = $archivo['name'];
    $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($extension, $extensiones_permitidas)) {
        throw new Exception('Formato de archivo no válido: ' . $extension);
    }
    $tamaño_maximo = 5 * 1024 * 1024;
    if ($archivo['size'] > $tamaño_maximo) {
        throw new Exception('El archivo es demasiado grande. Máximo 5MB.');
    }
    $nombre_archivo = 'imagen_generica_' . $tipo_norm . '_' . time() . '_' . $id_producto . '.' . $extension;
    $ruta_completa = $directorio_producto . $nombre_archivo;
    if (move_uploaded_file($archivo_temporal, $ruta_completa)) {
        return $ruta_completa;
    }
    throw new Exception('Error al subir la imagen: ' . $nombre_original);
}

// ============================================================================
// OBTENER DATOS DEL PRODUCTO
// ============================================================================

// Obtener datos del producto
$stmt_producto = $mysqli->prepare("SELECT * FROM Productos WHERE id_producto = ?");
$stmt_producto->bind_param('i', $id_producto);
$stmt_producto->execute();
$producto = $stmt_producto->get_result()->fetch_assoc();

if (!$producto) {
    header('Location: marketing.php');
    exit;
}

// Obtener variantes del producto
$stmt_variantes = $mysqli->prepare("SELECT * FROM Stock_Variantes WHERE id_producto = ? ORDER BY talle, color");
$stmt_variantes->bind_param('i', $id_producto);
$stmt_variantes->execute();
$variantes = $stmt_variantes->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener fotos genéricas (color NULL)
$fotos_genericas = null;
$stmt_fg_load = $mysqli->prepare("SELECT foto_prod_miniatura, foto3_prod FROM Fotos_Producto WHERE id_producto = ? AND color IS NULL LIMIT 1");
$stmt_fg_load->bind_param('i', $id_producto);
$stmt_fg_load->execute();
$fotos_genericas = $stmt_fg_load->get_result()->fetch_assoc();

// Obtener fotos por color para el producto
$fotos_por_color = [];
$stmt_fotos_color = $mysqli->prepare("SELECT color, foto1_prod, foto2_prod FROM Fotos_Producto WHERE id_producto = ? AND color IS NOT NULL");
$stmt_fotos_color->bind_param('i', $id_producto);
$stmt_fotos_color->execute();
$resultado_fotos_color = $stmt_fotos_color->get_result();
while ($row_fc = $resultado_fotos_color->fetch_assoc()) {
    $fotos_por_color[$row_fc['color']] = $row_fc;
}

// Colores presentes en variantes actuales
$colores_variantes = [];
foreach ($variantes as $v_tmp) {
    if (!in_array($v_tmp['color'], $colores_variantes, true)) {
        $colores_variantes[] = $v_tmp['color'];
    }
}

// Obtener categorías
$sql_categorias = "SELECT id_categoria, nombre_categoria FROM Categorias ORDER BY nombre_categoria";
$categorias = $mysqli->query($sql_categorias);

// Talles y colores disponibles
$talles_disponibles = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '28', '30', '32', '34', '36', '38', '40', '42', '44', '46'];
$colores_disponibles = ['Negro', 'Blanco', 'Azul', 'Rojo', 'Verde', 'Amarillo', 'Rosa', 'Gris', 'Marrón', 'Beige', 'Celeste', 'Naranja', 'Violeta', 'Turquesa'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto | Seda y Lino</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .edit-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .edit-header {
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
        
        .variante-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }
        
        .image-preview {
            border-radius: 8px;
            margin: 0.5rem;
        }
        
        .btn, .btn-sm, .btn-lg, 
        .btn-outline-primary, .btn-outline-secondary, .btn-outline-info, .btn-outline-danger,
        .btn-primary, .btn-success, .btn-warning, .btn-danger,
        a, [onclick], [data-bs-toggle], [data-bs-target] {
            cursor: pointer !important;
        }
        
        .image-preview {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <main class="edit-page">
        <div class="container">
            <!-- Header -->
            <div class="edit-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="fas fa-edit me-3"></i>Editar Producto</h1>
                        <p class="mb-0">Modificando: <strong><?= htmlspecialchars($producto['nombre_producto']) ?></strong></p>
                    </div>
                    <div>
                        <span class="badge bg-primary me-2">ID: <?= $producto['id_producto'] ?></span>
                        <span class="badge bg-success">Usuario: <?= htmlspecialchars($usuario_actual['nombre'] . ' ' . $usuario_actual['apellido']) ?></span>
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
            
            <!-- Información del producto actual -->
            <div class="form-section">
                <h4 class="mb-3"><i class="fas fa-info-circle me-2"></i>Información del Producto</h4>
                <div class="row">
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <i class="fas fa-hashtag fa-2x text-primary mb-2"></i>
                                <h5 class="card-title">ID Producto</h5>
                                <p class="card-text fs-4">#<?= $producto['id_producto'] ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <i class="fas fa-layer-group fa-2x text-warning mb-2"></i>
                                <h5 class="card-title">Variantes</h5>
                                <p class="card-text fs-4"><?= count($variantes) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <i class="fas fa-warehouse fa-2x text-success mb-2"></i>
                                <h5 class="card-title">Stock Total</h5>
                                <p class="card-text fs-4"><?= array_sum(array_column($variantes, 'stock')) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-alt fa-2x text-info mb-2"></i>
                                <h5 class="card-title">Última Modificación</h5>
                                <p class="card-text small"><?= date('d/m/Y', strtotime($producto['fecha_creacion'] ?? 'now')) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formulario de edición -->
            <div class="form-section">
                <h4 class="mb-4"><i class="fas fa-edit me-2"></i>Editar Producto: <span class="text-primary"><?= htmlspecialchars($producto['nombre_producto']) ?></span></h4>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Modo de edición:</strong> Estás editando un solo producto. Las variantes se pueden agregar, modificar o eliminar individualmente.
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="actualizar_producto" value="1">
                    
                    <!-- Información básica del producto -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label"><strong>Nombre del Producto</strong></label>
                            <input type="text" class="form-control" name="nombre_producto" required 
                                   value="<?= htmlspecialchars($producto['nombre_producto']) ?>" placeholder="Nombre del producto">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><strong>Precio</strong></label>
                            <input type="number" step="0.01" min="0" class="form-control" name="precio_actual" required 
                                   value="<?= $producto['precio_actual'] ?>" placeholder="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><strong>Género</strong></label>
                            <select class="form-select" name="genero" required>
                                <option value="">Seleccionar...</option>
                                <option value="hombre" <?= $producto['genero'] === 'hombre' ? 'selected' : '' ?>>Hombre</option>
                                <option value="mujer" <?= $producto['genero'] === 'mujer' ? 'selected' : '' ?>>Mujer</option>
                                <option value="unisex" <?= $producto['genero'] === 'unisex' ? 'selected' : '' ?>>Unisex</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label"><strong>Categoría</strong></label>
                            <select class="form-select" name="id_categoria" required>
                                <option value="">Seleccionar categoría...</option>
                                <?php while ($categoria = $categorias->fetch_assoc()): ?>
                                    <option value="<?= $categoria['id_categoria'] ?>" <?= $categoria['id_categoria'] == $producto['id_categoria'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($categoria['nombre_categoria']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>Descripción</strong></label>
                            <textarea class="form-control" name="descripcion_producto" rows="3" 
                                      placeholder="Describe las características del producto..."><?= htmlspecialchars($producto['descripcion_producto']) ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Imágenes genéricas del producto -->
                    <div class="mb-4">
                        <label class="form-label"><strong>Imágenes genéricas</strong></label>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label text-info"><i class="fas fa-image me-1"></i><strong>Miniatura</strong></label>
                            </div>
                            <div class="col-md-9">
                                <?php if (!empty($fotos_genericas['foto_prod_miniatura'])): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Actual:</small><br>
                                        <img src="<?= htmlspecialchars($fotos_genericas['foto_prod_miniatura']) ?>" class="image-preview">
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" name="imagen_generica_miniatura" accept="image/*" onchange="previewImage(this, 'previewGenericaMiniatura')">
                                <div id="previewGenericaMiniatura" class="mt-2"></div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label text-primary"><i class="fas fa-users me-1"></i><strong>Grupal</strong></label>
                            </div>
                            <div class="col-md-9">
                                <?php if (!empty($fotos_genericas['foto3_prod'])): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Actual:</small><br>
                                        <img src="<?= htmlspecialchars($fotos_genericas['foto3_prod']) ?>" class="image-preview">
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" name="imagen_generica_grupal" accept="image/*" onchange="previewImage(this, 'previewGenericaGrupal')">
                                <div id="previewGenericaGrupal" class="mt-2"></div>
                            </div>
                        </div>
                        <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Estos archivos aplican a todo el producto (no dependen del color).</small>
                    </div>

                    <!-- Imágenes por color -->
                    <div class="mb-4">
                        <label class="form-label"><strong>Imágenes por Color</strong></label>
                        <div class="alert alert-secondary">
                            <i class="fas fa-palette me-2"></i>
                            Sube hasta 2 imágenes para cada color existente. Deja vacío para mantener las actuales.
                        </div>
                        <?php if (!empty($colores_variantes)): ?>
                            <?php foreach ($colores_variantes as $color_loop): ?>
                                <?php 
                                    $color_key = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $color_loop));
                                    $foto1_exist = $fotos_por_color[$color_loop]['foto1_prod'] ?? null;
                                    $foto2_exist = $fotos_por_color[$color_loop]['foto2_prod'] ?? null;
                                ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0"><i class="fas fa-tint me-2"></i>Color: <strong><?= htmlspecialchars($color_loop) ?></strong></h6>
                                        </div>
                                        <input type="hidden" name="colores_presentes[]" value="<?= htmlspecialchars($color_loop) ?>">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Imagen 1</label>
                                                <?php if ($foto1_exist): ?>
                                                    <div class="mb-2">
                                                        <small class="text-muted">Actual:</small><br>
                                                        <img src="<?= htmlspecialchars($foto1_exist) ?>" class="image-preview">
                                                    </div>
                                                <?php endif; ?>
                                                <input type="file" class="form-control" name="imagenes_color[<?= htmlspecialchars($color_loop) ?>][img1]" accept="image/*" onchange="previewImage(this, 'preview_<?= $color_key ?>_img1')">
                                                <div id="preview_<?= $color_key ?>_img1" class="mt-2"></div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Imagen 2</label>
                                                <?php if ($foto2_exist): ?>
                                                    <div class="mb-2">
                                                        <small class="text-muted">Actual:</small><br>
                                                        <img src="<?= htmlspecialchars($foto2_exist) ?>" class="image-preview">
                                                    </div>
                                                <?php endif; ?>
                                                <input type="file" class="form-control" name="imagenes_color[<?= htmlspecialchars($color_loop) ?>][img2]" accept="image/*" onchange="previewImage(this, 'preview_<?= $color_key ?>_img2')">
                                                <div id="preview_<?= $color_key ?>_img2" class="mt-2"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                No hay colores definidos aún. Agrega variantes y guarda para cargar imágenes por color.
                            </div>
                        <?php endif; ?>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 5MB por imagen.
                        </small>
                    </div>
                    
                    <!-- Variantes del producto -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label mb-0"><strong>Variantes del Producto</strong></label>
                            <div>
                                <button type="button" class="btn btn-sm btn-success me-2" id="agregarVariante">
                                    <i class="fas fa-plus me-1"></i>Agregar Variante
                                </button>
                                <span class="badge bg-info"><?= count($variantes) ?> variantes actuales</span>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Importante:</strong> Cada variante se edita por separado. Puedes modificar el stock, agregar nuevas variantes o eliminar las existentes.
                        </div>
                        
                        <div id="variantesContainer">
                            <!-- Las variantes existentes se cargarán aquí -->
                            <?php foreach ($variantes as $index => $variante): ?>
                            <div class="variante-item">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0 text-primary">
                                        <i class="fas fa-layer-group me-1"></i>Variante #<?= $index + 1 ?>
                                    </h6>
                                    <small class="text-muted">ID: <?= $variante['id_variante'] ?></small>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label">Talle</label>
                                        <select class="form-select" name="variantes[<?= $index ?>][talle]" required>
                                            <option value="">Seleccionar...</option>
                                            <?php foreach ($talles_disponibles as $talle): ?>
                                                <option value="<?= $talle ?>" <?= $talle === $variante['talle'] ? 'selected' : '' ?>><?= $talle ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Color</label>
                                        <select class="form-select" name="variantes[<?= $index ?>][color]" required>
                                            <option value="">Seleccionar...</option>
                                            <?php foreach ($colores_disponibles as $color): ?>
                                                <option value="<?= $color ?>" <?= $color === $variante['color'] ? 'selected' : '' ?>><?= $color ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Stock</label>
                                        <input type="number" min="0" class="form-control" name="variantes[<?= $index ?>][stock]" 
                                               value="<?= $variante['stock'] ?>" required>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarVariante(this)">
                                            <i class="fas fa-trash me-1"></i>Eliminar
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Define los diferentes talles y colores disponibles para este producto.
                        </small>
                    </div>
                    
                    <!-- Botones de acción -->
                    <div class="text-center">
                        <button type="submit" class="btn btn-warning btn-lg me-3">
                            <i class="fas fa-save me-2"></i>Actualizar Producto
                        </button>
                        <a href="marketing.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-arrow-left me-2"></i>Volver a Marketing
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    
    <script>
        // Función para previsualizar imágenes
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
        
        // Gestión de variantes
        let varianteCount = <?= count($variantes) ?>;
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
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-success">
                        <i class="fas fa-plus-circle me-1"></i>Nueva Variante #${varianteCount + 1}
                    </h6>
                    <small class="text-muted">Nueva</small>
                </div>
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
                        <label class="form-label">Stock</label>
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
    </script>
</body>
</html>
