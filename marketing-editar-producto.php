<?php
/**
 * ========================================================================
 * EDITAR PRODUCTO - Tienda Seda y Lino
 * ========================================================================
 * Página para gestionar colores y talles de un producto
 * - Modificar datos base del producto
 * - Agregar colores al producto
 * - Agregar talles con stock para cada color
 * ========================================================================
 */
session_start();

// Verificación de acceso
require_once __DIR__ . '/includes/auth_check.php';
requireRole('marketing');

$id_usuario = getCurrentUserId();
$usuario_actual = getCurrentUser();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/image_helper.php';
require_once __DIR__ . '/includes/talles_config.php';
require_once __DIR__ . '/includes/queries/stock_queries.php';  // Debe cargarse PRIMERO
require_once __DIR__ . '/includes/queries/producto_queries.php';
require_once __DIR__ . '/includes/queries/categoria_queries.php';
require_once __DIR__ . '/includes/queries/foto_producto_queries.php';
require_once __DIR__ . '/includes/product_image_functions.php';

$titulo_pagina = 'Editar Producto';
$mensaje = '';
$mensaje_tipo = '';

// Capturar mensajes de sesión
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $mensaje_tipo = isset($_SESSION['mensaje_tipo']) ? $_SESSION['mensaje_tipo'] : 'success';
    // Limpiar mensaje de sesión después de leerlo
    unset($_SESSION['mensaje']);
    unset($_SESSION['mensaje_tipo']);
}

/**
 * Copia una foto del directorio temporal (imagenes/) a la carpeta de variante del producto
 * @param string $nombre_archivo_temporal Nombre del archivo en directorio temporal
 * @param int $id_producto ID del producto
 * @param string|null $color Color de la variante (null para fotos generales)
 * @param string $tipo_foto Tipo de foto: 'miniatura', 'foto1', 'foto2', 'foto3'
 * @return string Ruta completa de la imagen copiada
 * @throws Exception Si hay error al copiar el archivo
 */
function copiarFotoTemporalAVariante($nombre_archivo_temporal, $id_producto, $color = null, $tipo_foto = 'foto1') {
    $directorio_temporal = 'imagenes/';
    $ruta_temporal = $directorio_temporal . $nombre_archivo_temporal;
    
    // Verificar que el archivo temporal existe
    if (!file_exists($ruta_temporal)) {
        throw new Exception('Archivo temporal no encontrado: ' . $nombre_archivo_temporal);
    }
    
    // Validar que el archivo esté en el directorio temporal (seguridad)
    $ruta_real = realpath($ruta_temporal);
    $directorio_real = realpath($directorio_temporal);
    
    if ($ruta_real === false || $directorio_real === false) {
        throw new Exception('Ruta de archivo temporal inválida');
    }
    
    if (strpos($ruta_real, $directorio_real) !== 0) {
        throw new Exception('Intento de path traversal detectado');
    }
    
    // Validar extensión del archivo
    $extension = strtolower(pathinfo($nombre_archivo_temporal, PATHINFO_EXTENSION));
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $extensiones_permitidas)) {
        throw new Exception('Formato de archivo no válido: ' . $extension);
    }
    
    // Validar que sea una imagen válida y sus dimensiones
    $info_imagen = @getimagesize($ruta_temporal);
    if ($info_imagen === false) {
        throw new Exception('El archivo no es una imagen válida: ' . $nombre_archivo_temporal);
    }
    
    $ancho = $info_imagen[0];
    $alto = $info_imagen[1];
    $tamaño_archivo = filesize($ruta_temporal);
    $tamaño_maximo_bytes = 5242880; // 5MB
    
    // Validar tamaño del archivo
    if ($tamaño_archivo > $tamaño_maximo_bytes) {
        $tamaño_mb = round($tamaño_maximo_bytes / 1024 / 1024, 1);
        throw new Exception('El archivo es demasiado grande. Máximo ' . $tamaño_mb . 'MB.');
    }
    
    // Validar dimensiones máximas
    $ancho_maximo = 4000;
    $alto_maximo = 4000;
    if ($ancho > $ancho_maximo || $alto > $alto_maximo) {
        throw new Exception('Las dimensiones de la imagen son demasiado grandes. Máximo ' . $ancho_maximo . 'x' . $alto_maximo . ' pixels. La imagen actual es ' . $ancho . 'x' . $alto . ' pixels.');
    }
    
    // Validar dimensiones mínimas
    if ($ancho < 50 || $alto < 50) {
        throw new Exception('Las dimensiones de la imagen son demasiado pequeñas. Mínimo 50x50 pixels.');
    }
    
    // Determinar directorio destino
    if ($color === null) {
        // Fotos generales (miniatura, foto3)
        $directorio_destino = 'imagenes/productos/producto_' . $id_producto . '/';
    } else {
        // Fotos por color (foto1, foto2)
        $color_normalizado = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $color));
        $directorio_destino = 'imagenes/productos/producto_' . $id_producto . '/' . $color_normalizado . '/';
    }
    
    // Crear directorio si no existe
    if (!file_exists($directorio_destino)) {
        if (!mkdir($directorio_destino, 0755, true)) {
            throw new Exception('Error al crear el directorio de destino: ' . $directorio_destino);
        }
    }
    
    // Generar nombre de archivo final
    $nombre_base = pathinfo($nombre_archivo_temporal, PATHINFO_FILENAME);
    
    // Mantener nombre original pero con prefijo del tipo
    $nombre_final = 'imagen_' . $tipo_foto . '_' . time() . '_' . $id_producto . '_' . $nombre_base . '.' . $extension;
    $ruta_destino = $directorio_destino . $nombre_final;
    
    // Copiar archivo (mantener original en temp)
    if (!copy($ruta_temporal, $ruta_destino)) {
        throw new Exception('Error al copiar el archivo: ' . $nombre_archivo_temporal . ' a ' . $ruta_destino);
    }
    
    return $ruta_destino;
}

// Obtener ID del producto
$id_producto = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_producto <= 0) {
    header('Location: marketing.php');
    exit;
}

// Obtener datos del producto ANTES del procesamiento POST (necesario para validaciones)
$producto = obtenerProductoPorId($mysqli, $id_producto);

if (!$producto) {
    header('Location: marketing.php');
    exit;
}

// ============================================================================
// PROCESAR ACTUALIZACIÓN DE PRODUCTO BASE
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_producto'])) {
    $nombre_producto_input = trim($_POST['nombre_producto'] ?? '');
    $nombre_producto_nuevo = trim($_POST['nombre_producto_nuevo'] ?? '');
    $descripcion_producto = trim($_POST['descripcion_producto'] ?? '');
    $precio_actual = floatval($_POST['precio_actual'] ?? 0);
    $id_categoria_input = trim($_POST['id_categoria'] ?? '');
    $nombre_categoria_nueva = trim($_POST['nombre_categoria_nueva'] ?? '');
    $genero = $_POST['genero'] ?? '';
    
    $generos_validos = ['hombre', 'mujer', 'unisex'];
    
    // Manejar nombre del producto: puede ser existente o nuevo
    $nombre_producto = '';
    $nombre_producto_valido = false;
    
    if ($nombre_producto_input === '__NUEVO__') {
        // Validar que el nombre nuevo no esté vacío
        if (trim($nombre_producto_nuevo) === '') {
            $mensaje = 'El nombre del producto no puede estar vacío';
            $mensaje_tipo = 'danger';
        } else {
            $nombre_producto = trim($nombre_producto_nuevo);
            // Verificar que el nombre nuevo no exista ya (excluyendo el producto actual)
            $producto_existente = obtenerProductoIdPorNombre($mysqli, $nombre_producto, $id_producto);
            if ($producto_existente) {
                $mensaje = 'Ya existe un producto con el nombre "' . htmlspecialchars($nombre_producto) . '". El nombre debe ser único.';
                $mensaje_tipo = 'danger';
            } else {
                $nombre_producto_valido = true;
            }
        }
    } else {
        $nombre_producto = trim($nombre_producto_input);
        // Verificar que si se cambió el nombre, no duplique otro producto existente
        if ($nombre_producto !== $producto['nombre_producto']) {
            $producto_existente = obtenerProductoIdPorNombre($mysqli, $nombre_producto, $id_producto);
            if ($producto_existente) {
                $mensaje = 'Ya existe otro producto con el nombre "' . htmlspecialchars($nombre_producto) . '". El nombre debe ser único.';
                $mensaje_tipo = 'danger';
            } else {
                $nombre_producto_valido = true;
            }
        } else {
            $nombre_producto_valido = true;
        }
    }
    
    // Manejar categoría: puede ser ID numérico o nombre nuevo
    $id_categoria = 0;
    $categoria_valida = false;
    
    if ($id_categoria_input === '__NUEVO__') {
        // Es un nombre nuevo de categoría
        if (trim($nombre_categoria_nueva) === '') {
            if (!isset($mensaje)) {
                $mensaje = 'El nombre de la categoría no puede estar vacío';
                $mensaje_tipo = 'danger';
            }
        } else {
            // Verificar si ya existe
            $id_categoria = obtenerCategoriaIdPorNombre($mysqli, $nombre_categoria_nueva);
            if (!$id_categoria) {
                // Crear nueva categoría usando función centralizada
                $id_categoria = crearCategoria($mysqli, $nombre_categoria_nueva);
                $categoria_valida = ($id_categoria > 0);
                if (!$categoria_valida && !isset($mensaje)) {
                    $mensaje = 'Error al crear la nueva categoría';
                    $mensaje_tipo = 'danger';
                }
            } else {
                $categoria_valida = true;
            }
        }
    } else {
        // Es un ID existente
        $id_categoria = intval($id_categoria_input);
        $categoria_valida = ($id_categoria > 0);
        if (!$categoria_valida && !isset($mensaje)) {
            $mensaje = 'Categoría inválida';
            $mensaje_tipo = 'danger';
        }
    }
    
    // Validar todos los campos antes de actualizar
    if ($nombre_producto_valido && $categoria_valida && $nombre_producto !== '' && $precio_actual > 0 && $id_categoria > 0 && in_array($genero, $generos_validos)) {
        // Usar función centralizada para actualizar producto
        if (actualizarProducto($mysqli, $id_producto, $nombre_producto, $descripcion_producto, $precio_actual, $id_categoria, $genero)) {
            $mensaje = 'Producto actualizado correctamente';
            $mensaje_tipo = 'success';
            $_SESSION['mensaje'] = $mensaje;
            $_SESSION['mensaje_tipo'] = $mensaje_tipo;
            header('Location: marketing-editar-producto.php?id=' . $id_producto);
            exit;
        } else {
            $mensaje = 'Error al actualizar el producto';
            $mensaje_tipo = 'danger';
        }
    } else {
        $mensaje = 'Datos inválidos';
        $mensaje_tipo = 'danger';
    }
}

// ============================================================================
// ELIMINAR PRODUCTO
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_producto'])) {
    // Usar función centralizada para eliminar producto (pasar ruta base del proyecto)
    $ruta_base = __DIR__;
    $resultado = eliminarProductoCompleto($mysqli, $id_producto, $ruta_base);
    
    if ($resultado['success']) {
        // Redirigir a marketing.php con mensaje de éxito
        $_SESSION['mensaje'] = $resultado['mensaje'];
        $_SESSION['mensaje_tipo'] = 'success';
        header('Location: marketing.php');
        exit;
    } else {
        // Mostrar error
        $mensaje = $resultado['mensaje'];
        $mensaje_tipo = 'danger';
    }
}

// ============================================================================
// PROCESAR SUBIDA DE FOTOS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_fotos'])) {
    $mysqli->begin_transaction();
    
    try {
        // Obtener fotos generales actuales usando función centralizada
        $foto_general_existente = obtenerFotoProductoGeneral($mysqli, $id_producto);
        
        // Procesar foto miniatura (común)
        // Prioridad: archivo subido > selección del SELECT > limpiar foto
        if (isset($_FILES['foto_miniatura']) && $_FILES['foto_miniatura']['error'] === UPLOAD_ERR_OK) {
            $ruta_miniatura = subirImagenGenerica($id_producto, $_FILES['foto_miniatura'], 'miniatura');
            
            if ($foto_general_existente) {
                actualizarFotoMiniatura($mysqli, $foto_general_existente['id_foto'], $ruta_miniatura);
            } else {
                insertarFotoProducto($mysqli, $id_producto, $ruta_miniatura, null, null, null, null);
            }
        } elseif (isset($_POST['foto_miniatura_temp'])) {
            $valor_temp = trim($_POST['foto_miniatura_temp']);
            
            // Si el valor es __CLEAR__, limpiar la foto
            if ($valor_temp === '__CLEAR__') {
                if ($foto_general_existente) {
                    actualizarFotoMiniatura($mysqli, $foto_general_existente['id_foto'], null);
                }
            } elseif (!empty($valor_temp)) {
                // Usar imagen temporal seleccionada
                $nombre_archivo_temp = basename($valor_temp);
                if (empty($nombre_archivo_temp)) {
                    throw new Exception('Nombre de archivo temporal inválido');
                }
                $ruta_miniatura = copiarFotoTemporalAVariante($nombre_archivo_temp, $id_producto, null, 'miniatura');
                
                if ($foto_general_existente) {
                    actualizarFotoMiniatura($mysqli, $foto_general_existente['id_foto'], $ruta_miniatura);
                } else {
                    insertarFotoProducto($mysqli, $id_producto, $ruta_miniatura, null, null, null, null);
                }
            }
        }
        
        // Procesar foto grupal (foto3_prod, común)
        // Prioridad: archivo subido > selección del SELECT > limpiar foto
        if (isset($_FILES['foto_grupal']) && $_FILES['foto_grupal']['error'] === UPLOAD_ERR_OK) {
            $ruta_grupal = subirImagenGenerica($id_producto, $_FILES['foto_grupal'], 'grupal');
            
            if ($foto_general_existente) {
                actualizarFotoGrupal($mysqli, $foto_general_existente['id_foto'], $ruta_grupal);
            } else {
                insertarFotoProducto($mysqli, $id_producto, null, null, null, $ruta_grupal, null);
            }
        } elseif (isset($_POST['foto_grupal_temp'])) {
            $valor_temp = trim($_POST['foto_grupal_temp']);
            
            // Si el valor es __CLEAR__, limpiar la foto
            if ($valor_temp === '__CLEAR__') {
                if ($foto_general_existente) {
                    actualizarFotoGrupal($mysqli, $foto_general_existente['id_foto'], null);
                }
            } elseif (!empty($valor_temp)) {
                // Usar imagen temporal seleccionada
                $nombre_archivo_temp = basename($valor_temp);
                if (empty($nombre_archivo_temp)) {
                    throw new Exception('Nombre de archivo temporal inválido');
                }
                $ruta_grupal = copiarFotoTemporalAVariante($nombre_archivo_temp, $id_producto, null, 'foto3');
                
                if ($foto_general_existente) {
                    actualizarFotoGrupal($mysqli, $foto_general_existente['id_foto'], $ruta_grupal);
                } else {
                    insertarFotoProducto($mysqli, $id_producto, null, null, null, $ruta_grupal, null);
                }
            }
        }
        
        // Procesar fotos por color (foto1_prod y foto2_prod)
        if (isset($_FILES['fotos_colores']) && is_array($_FILES['fotos_colores'])) {
            foreach ($_FILES['fotos_colores']['name'] as $color => $slots) {
                $color_trim = trim($color);
                if ($color_trim === '') continue;
                
                // Verificar si existe registro de fotos para este color usando función centralizada
                $foto_color_existente = obtenerFotoProductoPorProducto($mysqli, $id_producto, $color_trim);
                
                $foto1_ruta = $foto_color_existente['foto1_prod'] ?? null;
                $foto2_ruta = $foto_color_existente['foto2_prod'] ?? null;
                $foto1_limpiar = false;
                $foto2_limpiar = false;
                
                // Procesar foto1_prod
                // Prioridad: archivo subido > selección del SELECT > limpiar foto
                if (isset($_FILES['fotos_colores']['name'][$color]['foto1']) && 
                    $_FILES['fotos_colores']['error'][$color]['foto1'] === UPLOAD_ERR_OK) {
                    $archivo_foto1 = [
                        'name' => $_FILES['fotos_colores']['name'][$color]['foto1'],
                        'type' => $_FILES['fotos_colores']['type'][$color]['foto1'],
                        'tmp_name' => $_FILES['fotos_colores']['tmp_name'][$color]['foto1'],
                        'error' => $_FILES['fotos_colores']['error'][$color]['foto1'],
                        'size' => $_FILES['fotos_colores']['size'][$color]['foto1']
                    ];
                    $foto1_ruta = subirImagenColor($id_producto, $color_trim, $archivo_foto1, 'img1');
                } elseif (isset($_POST['fotos_colores_temp'][$color]['foto1'])) {
                    $valor_temp = trim($_POST['fotos_colores_temp'][$color]['foto1']);
                    
                    // Si el valor es __CLEAR__, limpiar la foto
                    if ($valor_temp === '__CLEAR__') {
                        $foto1_ruta = null;
                        $foto1_limpiar = true;
                    } elseif (!empty($valor_temp)) {
                        // Usar imagen temporal seleccionada
                        $nombre_archivo_temp = basename($valor_temp);
                        if (!empty($nombre_archivo_temp)) {
                            $foto1_ruta = copiarFotoTemporalAVariante($nombre_archivo_temp, $id_producto, $color_trim, 'foto1');
                        }
                    }
                }
                
                // Procesar foto2_prod
                // Prioridad: archivo subido > selección del SELECT > limpiar foto
                if (isset($_FILES['fotos_colores']['name'][$color]['foto2']) && 
                    $_FILES['fotos_colores']['error'][$color]['foto2'] === UPLOAD_ERR_OK) {
                    $archivo_foto2 = [
                        'name' => $_FILES['fotos_colores']['name'][$color]['foto2'],
                        'type' => $_FILES['fotos_colores']['type'][$color]['foto2'],
                        'tmp_name' => $_FILES['fotos_colores']['tmp_name'][$color]['foto2'],
                        'error' => $_FILES['fotos_colores']['error'][$color]['foto2'],
                        'size' => $_FILES['fotos_colores']['size'][$color]['foto2']
                    ];
                    $foto2_ruta = subirImagenColor($id_producto, $color_trim, $archivo_foto2, 'img2');
                } elseif (isset($_POST['fotos_colores_temp'][$color]['foto2'])) {
                    $valor_temp = trim($_POST['fotos_colores_temp'][$color]['foto2']);
                    
                    // Si el valor es __CLEAR__, limpiar la foto
                    if ($valor_temp === '__CLEAR__') {
                        $foto2_ruta = null;
                        $foto2_limpiar = true;
                    } elseif (!empty($valor_temp)) {
                        // Usar imagen temporal seleccionada
                        $nombre_archivo_temp = basename($valor_temp);
                        if (!empty($nombre_archivo_temp)) {
                            $foto2_ruta = copiarFotoTemporalAVariante($nombre_archivo_temp, $id_producto, $color_trim, 'foto2');
                        }
                    }
                }
                
                // Si hay al menos una foto nueva, limpiar, o existe registro, actualizar o insertar
                if ($foto1_ruta || $foto2_ruta || $foto1_limpiar || $foto2_limpiar) {
                    if ($foto_color_existente) {
                        // Actualizar existente usando función centralizada
                        actualizarFotosColor($mysqli, $foto_color_existente['id_foto'], $foto1_ruta, $foto2_ruta);
                    } else {
                        // Insertar nuevo usando función centralizada (solo si hay fotos, no si solo se limpian)
                        if ($foto1_ruta || $foto2_ruta) {
                            insertarFotoProducto($mysqli, $id_producto, null, $foto1_ruta, $foto2_ruta, null, $color_trim);
                        }
                    }
                }
            }
        }
        
        // Procesar fotos por color desde SELECT cuando no hay archivos subidos
        if (isset($_POST['fotos_colores_temp']) && is_array($_POST['fotos_colores_temp'])) {
            foreach ($_POST['fotos_colores_temp'] as $color => $slots) {
                $color_trim = trim($color);
                if ($color_trim === '') continue;
                
                // Verificar si existe registro de fotos para este color usando función centralizada
                $foto_color_existente = obtenerFotoProductoPorProducto($mysqli, $id_producto, $color_trim);
                
                $foto1_ruta = $foto_color_existente['foto1_prod'] ?? null;
                $foto2_ruta = $foto_color_existente['foto2_prod'] ?? null;
                
                // Solo procesar si no se procesó ya desde $_FILES
                $ya_procesado = isset($_FILES['fotos_colores']['name'][$color]);
                
                if (!$ya_procesado) {
                    $foto1_nueva = false;
                    $foto2_nueva = false;
                    $foto1_limpiar = false;
                    $foto2_limpiar = false;
                    
                    // Procesar foto1_prod desde SELECT
                    if (isset($slots['foto1'])) {
                        $valor_temp = trim($slots['foto1']);
                        
                        // Si el valor es __CLEAR__, limpiar la foto
                        if ($valor_temp === '__CLEAR__') {
                            $foto1_ruta = null;
                            $foto1_limpiar = true;
                        } elseif (!empty($valor_temp)) {
                            $nombre_archivo_temp = basename($valor_temp);
                            if (!empty($nombre_archivo_temp)) {
                                $foto1_ruta = copiarFotoTemporalAVariante($nombre_archivo_temp, $id_producto, $color_trim, 'foto1');
                                $foto1_nueva = true;
                            }
                        }
                    }
                    
                    // Procesar foto2_prod desde SELECT
                    if (isset($slots['foto2'])) {
                        $valor_temp = trim($slots['foto2']);
                        
                        // Si el valor es __CLEAR__, limpiar la foto
                        if ($valor_temp === '__CLEAR__') {
                            $foto2_ruta = null;
                            $foto2_limpiar = true;
                        } elseif (!empty($valor_temp)) {
                            $nombre_archivo_temp = basename($valor_temp);
                            if (!empty($nombre_archivo_temp)) {
                                $foto2_ruta = copiarFotoTemporalAVariante($nombre_archivo_temp, $id_producto, $color_trim, 'foto2');
                                $foto2_nueva = true;
                            }
                        }
                    }
                    
                    // Si hay al menos una foto nueva o limpiar, actualizar o insertar
                    if ($foto1_nueva || $foto2_nueva || $foto1_limpiar || $foto2_limpiar) {
                        if ($foto_color_existente) {
                            // Actualizar existente usando función centralizada
                            // Si se limpia, usar null; si es nueva, usar la ruta; si no cambió, mantener existente
                            $foto1_para_actualizar = $foto1_limpiar ? null : ($foto1_nueva ? $foto1_ruta : $foto_color_existente['foto1_prod'] ?? null);
                            $foto2_para_actualizar = $foto2_limpiar ? null : ($foto2_nueva ? $foto2_ruta : $foto_color_existente['foto2_prod'] ?? null);
                            actualizarFotosColor($mysqli, $foto_color_existente['id_foto'], $foto1_para_actualizar, $foto2_para_actualizar);
                        } else {
                            // Insertar nuevo usando función centralizada (solo si hay fotos, no si solo se limpian)
                            if ($foto1_nueva || $foto2_nueva) {
                                insertarFotoProducto($mysqli, $id_producto, null, $foto1_ruta, $foto2_ruta, null, $color_trim);
                            }
                        }
                    }
                }
            }
        }
        
        $mysqli->commit();
        $mensaje = 'Fotos actualizadas correctamente';
        $mensaje_tipo = 'success';
        
        // Recargar página para mostrar cambios
        $_SESSION['mensaje'] = $mensaje;
        $_SESSION['mensaje_tipo'] = $mensaje_tipo;
        header('Location: marketing-editar-producto.php?id=' . $id_producto);
        exit;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        $mensaje = 'Error al actualizar fotos: ' . $e->getMessage();
        $mensaje_tipo = 'danger';
    }
}

// ============================================================================
// ACTUALIZAR VARIANTES (TABLA UNIFICADA)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_variantes'])) {
    $variantes_actualizar = $_POST['variantes_actualizar'] ?? [];
    $variantes_eliminar = $_POST['variantes_eliminar'] ?? [];
    $nuevas_variantes = $_POST['nuevas_variantes'] ?? [];
    
    // Capturar y validar observaciones opcionales
    $observaciones_ajuste = trim($_POST['observaciones_ajuste'] ?? '');
    if (strlen($observaciones_ajuste) > 500) {
        $observaciones_ajuste = substr($observaciones_ajuste, 0, 500);
    }
    // Si está vacío después de trim, se usará null (mensaje por defecto)
    if ($observaciones_ajuste === '') {
        $observaciones_ajuste = null;
    }
    
    require_once __DIR__ . '/includes/validation_functions.php'; // Para validarObservaciones en ajustes de stock
    
    $mysqli->begin_transaction();
    
    try {
        // Preparar lista de IDs a eliminar (para evitar actualizarlas)
        $ids_a_eliminar = [];
        foreach ($variantes_eliminar as $id_variante_eliminar) {
            $id_variante_eliminar = trim($id_variante_eliminar);
            if ($id_variante_eliminar !== '' && $id_variante_eliminar !== '0') {
                $ids_a_eliminar[] = intval($id_variante_eliminar);
            }
        }
        
        // Actualizar variantes existentes (excluyendo las marcadas para eliminar)
        foreach ($variantes_actualizar as $id_variante => $datos) {
            $id_variante_int = intval($id_variante);
            
            // Saltar si esta variante está marcada para eliminar
            if (in_array($id_variante_int, $ids_a_eliminar)) {
                continue;
            }
            
            $nuevo_talle = trim($datos['talle'] ?? '');
            $nuevo_stock = intval($datos['stock'] ?? 0);
            $nuevo_color = ucfirst(strtolower(trim($datos['color'] ?? '')));
            
            // Validar que talle y color no estén vacíos después de normalizar
            if ($id_variante_int > 0 && $nuevo_talle !== '' && $nuevo_color !== '' && $nuevo_stock >= 0) {
                // Obtener datos actuales de la variante usando función centralizada (incluye activo)
                $variante_actual = obtenerVariantePorId($mysqli, $id_variante_int);
                
                if (!$variante_actual) {
                    throw new Exception('La variante ID ' . $id_variante_int . ' no existe');
                }
                
                // Validar que la variante esté activa antes de actualizar
                $variante_activa = isset($variante_actual['activo']) ? (int)$variante_actual['activo'] === 1 : true;
                if (!$variante_activa) {
                    throw new Exception('No se puede actualizar stock de una variante inactiva (ID: ' . $id_variante_int . ')');
                }
                
                $id_producto_variante = $variante_actual['id_producto'];
                $stock_actual = (int)$variante_actual['stock'];
                
                // Normalizar color actual para comparación
                $color_actual_normalizado = ucfirst(strtolower(trim($variante_actual['color'] ?? '')));
                $talle_actual = trim($variante_actual['talle'] ?? '');
                
                // Variable para rastrear si la variante fue desactivada
                $variante_desactivada = false;
                
                // Verificar si cambió el talle o color (comparar valores normalizados)
                if ($talle_actual !== $nuevo_talle || $color_actual_normalizado !== $nuevo_color) {
                    // Verificar si ya existe otra variante activa con la misma combinación id_producto + talle + color
                    // La clave única uk_variante_producto es (id_producto, talle, color)
                    $sql_verificar = "SELECT id_variante FROM Stock_Variantes 
                                     WHERE id_producto = ? 
                                     AND talle = ? 
                                     AND color = ? 
                                     AND activo = 1 
                                     AND id_variante != ? 
                                     LIMIT 1";
                    $stmt_verificar = $mysqli->prepare($sql_verificar);
                    if ($stmt_verificar) {
                        $stmt_verificar->bind_param('issi', $id_producto_variante, $nuevo_talle, $nuevo_color, $id_variante_int);
                        $stmt_verificar->execute();
                        $result_verificar = $stmt_verificar->get_result();
                        $existe_variante = $result_verificar->num_rows > 0;
                        $stmt_verificar->close();
                        
                        if ($existe_variante) {
                            // Ya existe otra variante activa con esta combinación, marcar la actual como inactiva (soft delete)
                            desactivarVarianteStock($mysqli, $id_variante_int);
                            $variante_desactivada = true;
                        } else {
                            // No existe, actualizar talle y color usando función centralizada
                            if (!actualizarVarianteStock($mysqli, $id_variante_int, $nuevo_talle, $nuevo_color)) {
                                throw new Exception('Error al actualizar talle/color de variante ID: ' . $id_variante_int);
                            }
                        }
                    } else {
                        throw new Exception('Error al preparar verificación de variante existente');
                    }
                }
                
                // Actualizar stock si cambió y la variante no fue desactivada
                // IMPORTANTE: Los ajustes se manejan con cantidad con signo (positivo suma, negativo resta)
                // El stock no cambia cuando se actualiza talle/color, así que usamos el stock de la primera consulta
                if (!$variante_desactivada) {
                    // Calcular diferencia usando el stock de la primera consulta (no cambia con talle/color)
                    $diferencia_stock = $nuevo_stock - $stock_actual;
                    
                    if ($diferencia_stock != 0) {
                        // Validar que stock_queries.php esté incluido (ya está en línea 26, pero por seguridad)
                        require_once __DIR__ . '/includes/queries/stock_queries.php';
                        
                        // Formatear observación con contexto estructurado
                        $nombre_usuario = ($usuario_actual && isset($usuario_actual['nombre']) && isset($usuario_actual['apellido'])) 
                            ? trim($usuario_actual['nombre'] . ' ' . $usuario_actual['apellido']) 
                            : 'Usuario #' . $id_usuario;
                        
                        $fecha_hora = date('Y-m-d H:i:s');
                        $tipo_ajuste = $diferencia_stock > 0 ? 'positivo' : 'negativo';
                        
                        // Construir observación con contexto
                        if ($observaciones_ajuste !== null) {
                            // Validar observaciones del usuario usando función centralizada
                            require_once __DIR__ . '/includes/validation_functions.php';
                            $validacion_obs = validarObservaciones($observaciones_ajuste, 500, 'observaciones');
                            if (!$validacion_obs['valido']) {
                                throw new Exception($validacion_obs['error']);
                            }
                            $observaciones_ajuste_validadas = $validacion_obs['valor'];
                            
                            // Si hay observación del usuario, agregar contexto
                            $observacion = sprintf(
                                "[%s] Ajuste %s\nUsuario: %s\nStock anterior: %d → Stock nuevo: %d\nNotas: %s",
                                $fecha_hora,
                                $tipo_ajuste,
                                $nombre_usuario,
                                $stock_actual,
                                $nuevo_stock,
                                $observaciones_ajuste_validadas
                            );
                            // Truncar si excede 500 caracteres (respetar límite de BD)
                            if (strlen($observacion) > 500) {
                                $observacion = substr($observacion, 0, 497) . '...';
                            }
                        } else {
                            // Mensaje por defecto con contexto
                            $observacion = sprintf(
                                "[%s] Ajuste %s desde edición\nUsuario: %s\nStock anterior: %d → Stock nuevo: %d",
                                $fecha_hora,
                                $tipo_ajuste,
                                $nombre_usuario,
                                $stock_actual,
                                $nuevo_stock
                            );
                        }
                        
                        // registrarMovimientoStock() lanza excepciones, no retorna false en caso de error
                        // Envolver en try-catch para manejo adecuado de errores
                        try {
                            registrarMovimientoStock($mysqli, $id_variante_int, 'ajuste', $diferencia_stock, $id_usuario, null, $observacion, true);
                        } catch (Exception $e_stock) {
                            throw new Exception('Error al actualizar stock de variante ID ' . $id_variante_int . ': ' . $e_stock->getMessage());
                        }
                    }
                }
            }
        }
        
        // Eliminar variantes marcadas
        foreach ($variantes_eliminar as $id_variante_eliminar) {
            // Filtrar valores vacíos o nulos
            $id_variante_eliminar = trim($id_variante_eliminar);
            if ($id_variante_eliminar === '' || $id_variante_eliminar === '0') {
                continue;
            }
            
            $id_variante_eliminar_int = intval($id_variante_eliminar);
            if ($id_variante_eliminar_int > 0) {
                // Eliminación lógica (soft delete) usando función centralizada
                // Esto preserva los datos históricos en Detalle_Pedido y Movimientos_Stock
                desactivarVarianteStock($mysqli, $id_variante_eliminar_int);
            }
        }
        
        // Agregar nuevas variantes
        foreach ($nuevas_variantes as $variante) {
            $talle_trim = trim($variante['talle'] ?? '');
            $color_trim = ucfirst(strtolower(trim($variante['color'] ?? '')));
            $stock = intval($variante['stock'] ?? 0);
            
            if ($talle_trim !== '' && $color_trim !== '' && $stock >= 0) {
                // Verificar si ya existe esta combinación en el producto actual (solo este id_producto)
                // IMPORTANTE: Usar verificarVarianteExistentePorProducto() para verificar solo en el producto actual,
                // no en todo el grupo. Esto permite tener la misma combinación talle-color en diferentes productos.
                $existe = verificarVarianteExistentePorProducto($mysqli, $id_producto, $talle_trim, $color_trim);
                
                if (!$existe) {
                    // Crear nueva variante con stock=0 usando función centralizada (el stock se actualiza mediante movimiento)
                    // Esto reemplaza el trigger trg_actualizar_stock_insert mediante registrarMovimientoStock()
                    $id_variante_nueva = insertarVarianteStock($mysqli, $id_producto, $talle_trim, $color_trim, 0);
                    
                    if (!$id_variante_nueva) {
                        throw new Exception('Error al insertar variante: ' . $talle_trim . ' ' . $color_trim);
                    }
                    
                    // Registrar movimiento inicial (reemplaza trg_actualizar_stock_insert)
                    // Esta función actualiza automáticamente el stock de la variante
                    if ($stock > 0) {
                        // Validar que stock_queries.php esté incluido (ya está en línea 26, pero por seguridad)
                        require_once __DIR__ . '/includes/queries/stock_queries.php';
                        
                        // Formatear observación de ingreso con contexto estructurado
                        $nombre_usuario = ($usuario_actual && isset($usuario_actual['nombre']) && isset($usuario_actual['apellido'])) 
                            ? trim($usuario_actual['nombre'] . ' ' . $usuario_actual['apellido']) 
                            : 'Usuario #' . $id_usuario;
                        
                        $fecha_hora = date('Y-m-d H:i:s');
                        
                        if ($observaciones_ajuste !== null) {
                            // Validar observaciones del usuario usando función centralizada
                            // NOTA: validation_functions.php ya fue requerido arriba para ajustes
                            $validacion_obs_ingreso = validarObservaciones($observaciones_ajuste, 500, 'observaciones');
                            if (!$validacion_obs_ingreso['valido']) {
                                throw new Exception($validacion_obs_ingreso['error']);
                            }
                            $observaciones_ajuste_validadas = $validacion_obs_ingreso['valor'];
                            
                            // Si hay observación del usuario, agregar contexto
                            $observacion_ingreso = sprintf(
                                "[%s] Ingreso stock inicial\nUsuario: %s\nCantidad: %d\nNotas: %s",
                                $fecha_hora,
                                $nombre_usuario,
                                $stock,
                                $observaciones_ajuste_validadas
                            );
                            // Truncar si excede 500 caracteres
                            if (strlen($observacion_ingreso) > 500) {
                                $observacion_ingreso = substr($observacion_ingreso, 0, 497) . '...';
                            }
                        } else {
                            // Mensaje por defecto con contexto
                            $observacion_ingreso = sprintf(
                                "[%s] Stock inicial\nUsuario: %s\nCantidad: %d",
                                $fecha_hora,
                                $nombre_usuario,
                                $stock
                            );
                        }
                        
                        // registrarMovimientoStock() lanza excepciones, no retorna false en caso de error
                        // Envolver en try-catch para manejo adecuado de errores
                        try {
                            registrarMovimientoStock($mysqli, $id_variante_nueva, 'ingreso', $stock, $id_usuario, null, $observacion_ingreso, true);
                        } catch (Exception $e_stock) {
                            throw new Exception('Error al registrar movimiento de stock para variante ' . $talle_trim . ' ' . $color_trim . ': ' . $e_stock->getMessage());
                        }
                    }
                }
            }
        }
        
        $mysqli->commit();
        $mensaje = 'Variantes actualizadas correctamente';
        $mensaje_tipo = 'success';
        
        // Recargar variantes para mostrar cambios
        $_SESSION['mensaje'] = $mensaje;
        $_SESSION['mensaje_tipo'] = $mensaje_tipo;
        header('Location: marketing-editar-producto.php?id=' . $id_producto);
        exit;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        $mensaje = 'Error: ' . $e->getMessage();
        $mensaje_tipo = 'danger';
    }
}

// ============================================================================
// OBTENER DATOS DEL PRODUCTO
// ============================================================================
$producto = obtenerProductoPorId($mysqli, $id_producto);

if (!$producto) {
    header('Location: marketing.php');
    exit;
}

// Obtener categorías usando función centralizada (filtra solo activas)
$categorias_temp = obtenerCategorias($mysqli);
// Eliminar duplicados por nombre de categoría (mantener solo la primera ocurrencia)
// Esto evita mostrar categorías duplicadas con el mismo nombre pero diferentes IDs
$categorias = [];
$nombres_categorias_vistos = [];
foreach ($categorias_temp as $cat) {
    // Verificar que la categoría esté activa (doble verificación)
    if (isset($cat['activo']) && $cat['activo'] != 1) {
        continue; // Saltar categorías inactivas
    }
    $nombre_normalizado = strtolower(trim($cat['nombre_categoria']));
    if (!in_array($nombre_normalizado, $nombres_categorias_vistos)) {
        $categorias[] = $cat;
        $nombres_categorias_vistos[] = $nombre_normalizado;
    }
}

// Obtener TODAS las variantes del producto actual (solo de este id_producto específico)
// IMPORTANTE: Esto asegura que solo se editen las variantes del producto actual,
// manteniendo consistencia con marketing.php y detalle-producto.php que calculan stock por id_producto
// Usar función centralizada
$todas_variantes = obtenerTodasVariantesProducto($mysqli, $id_producto);

// Obtener fotos del producto actual (solo de este id_producto específico)
// IMPORTANTE: Esto asegura que solo se editen las fotos del producto actual
// Usar función centralizada
$todas_fotos = obtenerTodasFotosProducto($mysqli, $id_producto);

// Obtener colores únicos de las variantes ya obtenidas (evita consulta duplicada)
$colores_variantes = [];
if (!empty($todas_variantes)) {
    $colores_temp = array_column($todas_variantes, 'color');
    $colores_temp = array_filter($colores_temp, function($color) {
        return $color !== null && $color !== '';
    });
    // CORRECCIÓN: Normalizar colores al obtenerlos para el array de variantes existentes
    $colores_variantes = array_unique(array_map(function($color) {
        return ucfirst(strtolower(trim($color)));
    }, $colores_temp));
    sort($colores_variantes);
}

// Talles disponibles - Origen centralizado
$talles_disponibles = obtenerTallesEstandar();

    // Colores disponibles - Generar dinámicamente incluyendo colores existentes en DB
    $colores_base = ['Negro', 'Blanco', 'Azul', 'Rojo', 'Verde', 'Amarillo', 'Rosa', 'Gris', 'Marrón', 'Beige', 'Celeste', 'Naranja', 'Violeta', 'Turquesa', 'Natural', 'Crema'];
    $colores_db = obtenerColoresUnicosActivos($mysqli); // Nueva función
    $colores_disponibles = array_unique(array_merge($colores_base, $colores_db));
    sort($colores_disponibles);

// Obtener nombres únicos de productos para el SELECT
$nombres_productos = obtenerNombresProductosUnicos($mysqli);

// Obtener Fotos Disponibles
$fotos_temporales = obtenerFotosTemporales();
?>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h3 mb-1">Editar Producto</h2>
                            <p class="text-secondary mb-0"><?= htmlspecialchars($producto['nombre_producto']) ?></p>
                </div>
                <div>
                    <a href="marketing.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Volver
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Mensajes -->
    <?php if ($mensaje): ?>
    <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($mensaje) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Información Base del Producto -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-info-circle me-2"></i>Información Base del Producto
            </h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="actualizar_producto" value="1">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Nombre del Producto *</label>
                        <select class="form-select" name="nombre_producto" id="select_nombre_producto" required>
                            <?php foreach ($nombres_productos as $nombre): ?>
                            <option value="<?= htmlspecialchars($nombre) ?>" <?= $producto['nombre_producto'] === $nombre ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nombre) ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="__NUEVO__" class="text-primary fw-bold">+ Agregar Nuevo Nombre</option>
                        </select>
                        <input type="text" class="form-control mt-2 d-none" name="nombre_producto_nuevo" id="input_nombre_producto_nuevo" placeholder="Ingrese el nombre del nuevo producto">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Precio *</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="precio_actual" value="<?= $producto['precio_actual'] ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Género *</label>
                        <select class="form-select" name="genero" required>
                            <option value="hombre" <?= $producto['genero'] === 'hombre' ? 'selected' : '' ?>>Hombre</option>
                            <option value="mujer" <?= $producto['genero'] === 'mujer' ? 'selected' : '' ?>>Mujer</option>
                            <option value="unisex" <?= $producto['genero'] === 'unisex' ? 'selected' : '' ?>>Unisex</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Categoría *</label>
                        <select class="form-select" name="id_categoria" id="select_categoria" required>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id_categoria'] ?>" <?= $producto['id_categoria'] == $cat['id_categoria'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nombre_categoria']) ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="__NUEVO__" class="text-primary fw-bold">+ Agregar Nueva Categoría</option>
                        </select>
                        <input type="text" class="form-control mt-2 d-none" name="nombre_categoria_nueva" id="input_categoria_nueva" placeholder="Ingrese el nombre de la nueva categoría">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion_producto" id="input_descripcion_producto_editar" rows="2"><?= htmlspecialchars($producto['descripcion_producto'] ?? '') ?></textarea>
                        <div class="invalid-feedback" id="error_descripcion_editar"></div>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Guardar Cambios
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalEliminarProducto">
                        <i class="fas fa-trash me-1"></i>Eliminar Producto
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Confirmación para Eliminar Producto -->
    <div class="modal fade" id="modalEliminarProducto" tabindex="-1" aria-labelledby="modalEliminarProductoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalEliminarProductoLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        <strong>¿Está seguro que desea eliminar el producto?</strong>
                    </p>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Atención:</strong> Esta acción eliminará permanentemente:
                        <ul class="mb-0 mt-2">
                            <li>El producto y toda su información</li>
                            <li>Todas las variantes de stock</li>
                            <li>Todas las imágenes asociadas</li>
                            <li>Los detalles de pedidos relacionados (los pedidos permanecerán en el historial)</li>
                        </ul>
                        <strong class="mt-2 d-block">Esta acción no se puede deshacer.</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="eliminar_producto" value="1">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>Sí, Eliminar Producto
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- EDITAR VARIANTES (Tabla Unificada) -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-edit me-2"></i>EDITAR VARIANTES
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" id="formVariantesUnificado" onsubmit="return prepararEnvioFormulario()">
                <input type="hidden" name="actualizar_variantes" value="1">
                
                <!-- Variantes existentes en tabla unificada -->
                <?php if (!empty($todas_variantes)): ?>
                <div class="mb-4">
                    <h6 class="mb-3">
                        <i class="fas fa-edit me-2"></i>Editar Variantes Existentes:
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Talle</th>
                                    <th>Color</th>
                                    <th>Stock</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todas_variantes as $variante): ?>
                                <tr id="fila_<?= $variante['id_variante'] ?>">
                                    <td>
                                        <select class="form-select form-select-sm" name="variantes_actualizar[<?= $variante['id_variante'] ?>][talle]" required>
                                            <?php foreach ($talles_disponibles as $talle_disp): ?>
                                            <option value="<?= htmlspecialchars($talle_disp) ?>" <?= $variante['talle'] === $talle_disp ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($talle_disp) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="variantes_actualizar[<?= $variante['id_variante'] ?>][color]" required>
                                            <?php foreach ($colores_disponibles as $color_disp): ?>
                                            <option value="<?= htmlspecialchars($color_disp) ?>" <?= strtolower($variante['color']) === strtolower($color_disp) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($color_disp) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" min="0" max="10000" class="form-control form-control-sm stock-input" 
                                               name="variantes_actualizar[<?= $variante['id_variante'] ?>][stock]" 
                                               value="<?= $variante['stock'] ?>" 
                                               data-variante-id="<?= $variante['id_variante'] ?>" required>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="marcarEliminar(<?= $variante['id_variante'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <input type="hidden" name="variantes_eliminar[]" id="eliminar_<?= $variante['id_variante'] ?>" value="" data-variante-id="<?= $variante['id_variante'] ?>">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    No hay variantes agregadas aún. Agrega variantes abajo.
                </div>
                <?php endif; ?>

                <!-- Agregar nuevas variantes -->
                <div class="mb-3">
                    <h6 class="mb-3">
                        <i class="fas fa-plus me-2"></i>Agregar Nuevas Variantes:
                    </h6>
                    <div id="variantesContainer"></div>
                    <button type="button" class="btn btn-sm btn-outline-success mb-3" onclick="agregarVariante()">
                        <i class="fas fa-plus me-1"></i>Agregar Variante
                    </button>
                </div>
                
                <!-- Campo de observaciones opcional -->
                <div class="mb-3">
                    <label class="form-label">Observaciones (opcional)</label>
                    <textarea class="form-control" name="observaciones_ajuste" id="observaciones_ajuste" 
                              rows="4" maxlength="500" 
                              placeholder="Ejemplo: Corrección de inventario físico, Producto dañado, Reubicación de almacén, etc.&#10;&#10;Se aplicará a todos los ajustes de stock realizados en este formulario."></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <small class="form-text text-muted">
                            Se aplicará a todos los movimientos de stock de este formulario. Puedes agregar múltiples líneas.
                        </small>
                        <small class="text-muted">
                            <span id="contador_observaciones">0</span>/500 caracteres
                        </small>
                    </div>
                </div>
                
                <script>
                // Contador de caracteres para observaciones
                document.addEventListener('DOMContentLoaded', function() {
                    const textarea = document.getElementById('observaciones_ajuste');
                    const contador = document.getElementById('contador_observaciones');
                    
                    if (textarea && contador) {
                        function actualizarContador() {
                            const longitud = textarea.value.length;
                            contador.textContent = longitud;
                            
                            // Cambiar color si se acerca al límite
                            if (longitud > 450) {
                                contador.classList.add('text-danger');
                                contador.classList.remove('text-warning', 'text-muted');
                            } else if (longitud > 400) {
                                contador.classList.add('text-warning');
                                contador.classList.remove('text-danger', 'text-muted');
                            } else {
                                contador.classList.remove('text-danger', 'text-warning');
                                contador.classList.add('text-muted');
                            }
                        }
                        
                        textarea.addEventListener('input', actualizarContador);
                        actualizarContador(); // Inicializar contador
                    }
                });
                </script>
                
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDITAR FOTOS DEL PRODUCTO -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-images me-2"></i>EDITAR FOTOS DEL PRODUCTO
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" id="formFotos">
                <input type="hidden" name="actualizar_fotos" value="1">
                
                <!-- Fotos Generales (Comunes a todas las variantes) -->
                <div class="mb-4">
                    <h6 class="mb-3">
                        <i class="fas fa-image me-2"></i>Fotos Generales (Comunes a todas las variantes):
                    </h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Foto Miniatura</label>
                            <?php if (!empty($todas_fotos['generales']['foto_prod_miniatura'])): ?>
                            <div class="mb-2">
                                <img src="<?= htmlspecialchars($todas_fotos['generales']['foto_prod_miniatura']) ?>" 
                                     alt="Miniatura actual" 
                                     class="img-thumbnail" 
                                     style="max-width: 200px; max-height: 200px;">
                                <br><small class="text-secondary">Imagen actual</small>
                            </div>
                            <?php endif; ?>
                            <label class="form-label small">Seleccionar de imágenes locales:</label>
                            <select class="form-select mb-2" name="foto_miniatura_temp">
                                <option value="__CLEAR__">-- Seleccionar imagen local --</option>
                                <?php foreach ($fotos_temporales as $foto_temp): ?>
                                <option value="<?= htmlspecialchars($foto_temp) ?>"><?= htmlspecialchars($foto_temp) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="form-label small">O subir archivo nuevo:</label>
                            <input type="file" class="form-control" name="foto_miniatura" accept="image/*">
                            <small class="text-secondary">
                                Se usa como imagen principal del producto en listados.<br>
                                <strong>Requisitos:</strong> Máximo 5MB, dimensiones máx. 4000x4000px, mín. 50x50px. Formatos permitidos: JPG, PNG, GIF, WEBP.
                            </small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Foto Grupal</label>
                            <?php if (!empty($todas_fotos['generales']['foto3_prod'])): ?>
                            <div class="mb-2">
                                <img src="<?= htmlspecialchars($todas_fotos['generales']['foto3_prod']) ?>" 
                                     alt="Foto grupal actual" 
                                     class="img-thumbnail" 
                                     style="max-width: 200px; max-height: 200px;">
                                <br><small class="text-secondary">Imagen actual</small>
                            </div>
                            <?php endif; ?>
                            <label class="form-label small">Seleccionar de imágenes locales:</label>
                            <select class="form-select mb-2" name="foto_grupal_temp">
                                <option value="__CLEAR__">-- Seleccionar imagen local --</option>
                                <?php foreach ($fotos_temporales as $foto_temp): ?>
                                <option value="<?= htmlspecialchars($foto_temp) ?>"><?= htmlspecialchars($foto_temp) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="form-label small">O subir archivo nuevo:</label>
                            <input type="file" class="form-control" name="foto_grupal" accept="image/*">
                            <small class="text-secondary">
                                Imagen grupal del producto.<br>
                                <strong>Requisitos:</strong> Máximo 5MB, dimensiones máx. 4000x4000px, mín. 50x50px. Formatos permitidos: JPG, PNG, GIF, WEBP.
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Fotos por Color -->
                <div class="mb-4">
                    <h6 class="mb-3">
                        <i class="fas fa-palette me-2"></i>Fotos por Color (Foto 1 y Foto 2):
                    </h6>
                    <?php if (empty($colores_variantes)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Agrega variantes de stock con colores para poder subir fotos por color.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Color</th>
                                    <th>Foto 1</th>
                                    <th>Foto 2</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($colores_variantes as $color_variante): ?>
                                <?php 
                                $fotos_color = $todas_fotos['por_color'][$color_variante] ?? [];
                                $foto_color_actual = !empty($fotos_color) ? $fotos_color[0] : null;
                                ?>
                                <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($color_variante) ?></strong>
                                        </td>
                                    <td>
                                        <?php if (!empty($foto_color_actual['foto1_prod'])): ?>
                                        <div class="mb-2">
                                            <img src="<?= htmlspecialchars($foto_color_actual['foto1_prod']) ?>" 
                                                 alt="Foto 1 <?= htmlspecialchars($color_variante) ?>" 
                                                 class="img-thumbnail" 
                                                 style="max-width: 150px; max-height: 150px;">
                                            <br><small class="text-secondary">Actual</small>
                                        </div>
                                        <?php endif; ?>
                                        <label class="form-label small">Local:</label>
                                        <select class="form-select form-select-sm mb-2" name="fotos_colores_temp[<?= htmlspecialchars($color_variante) ?>][foto1]">
                                            <option value="__CLEAR__">-- Seleccionar --</option>
                                            <?php foreach ($fotos_temporales as $foto_temp): ?>
                                            <option value="<?= htmlspecialchars($foto_temp) ?>"><?= htmlspecialchars($foto_temp) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label class="form-label small">O subir:</label>
                                        <input type="file" 
                                               class="form-control form-control-sm" 
                                               name="fotos_colores[<?= htmlspecialchars($color_variante) ?>][foto1]" 
                                               accept="image/*">
                                    </td>
                                    <td>
                                        <?php if (!empty($foto_color_actual['foto2_prod'])): ?>
                                        <div class="mb-2">
                                            <img src="<?= htmlspecialchars($foto_color_actual['foto2_prod']) ?>" 
                                                 alt="Foto 2 <?= htmlspecialchars($color_variante) ?>" 
                                                 class="img-thumbnail" 
                                                 style="max-width: 150px; max-height: 150px;">
                                            <br><small class="text-secondary">Actual</small>
                                        </div>
                                        <?php endif; ?>
                                        <label class="form-label small">Local:</label>
                                        <select class="form-select form-select-sm mb-2" name="fotos_colores_temp[<?= htmlspecialchars($color_variante) ?>][foto2]">
                                            <option value="__CLEAR__">-- Seleccionar --</option>
                                            <?php foreach ($fotos_temporales as $foto_temp): ?>
                                            <option value="<?= htmlspecialchars($foto_temp) ?>"><?= htmlspecialchars($foto_temp) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label class="form-label small">O subir:</label>
                                        <input type="file" 
                                               class="form-control form-control-sm" 
                                               name="fotos_colores[<?= htmlspecialchars($color_variante) ?>][foto2]" 
                                               accept="image/*">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Nota:</strong> Solo se actualizarán las fotos que selecciones. Las fotos existentes se mantendrán si no subes una nueva.<br>
                        <strong>Requisitos de imágenes:</strong> Máximo 5MB por archivo, dimensiones máximas 4000x4000px, mínimas 50x50px. Formatos permitidos: JPG, PNG, GIF, WEBP.
                    </div>
                    <?php endif; ?>
                </div>
                
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Guardar Fotos
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Variables globales necesarias para marketing_editar_producto.js
window.tallesDisponibles = <?= json_encode($talles_disponibles) ?>;
window.coloresDisponibles = <?= json_encode($colores_disponibles) ?>;
</script>

<?php include 'includes/footer.php'; render_footer(); ?>

