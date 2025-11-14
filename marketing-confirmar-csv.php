<?php
session_start();

// ============================================================================
// VERIFICACIÓN DE ACCESO - SOLO USUARIOS MARKETING
// ============================================================================

// Cargar sistema de autenticación centralizado
require_once __DIR__ . '/includes/auth_check.php';

// Verificar que el usuario esté logueado y tenga rol marketing
requireRole('marketing');

// Obtener información del usuario actual
$id_usuario = getCurrentUserId();
$usuario_actual = getCurrentUser();

// Conectar a la base de datos
require_once __DIR__ . '/config/database.php';

// Cargar funciones de procesamiento CSV (necesarias para procesar formularios)
require_once __DIR__ . '/includes/csv_functions.php';

// Cargar funciones de productos y stock (necesarias para procesar formularios)
require_once __DIR__ . '/includes/queries/producto_queries.php';
require_once __DIR__ . '/includes/queries/stock_queries.php';
require_once __DIR__ . '/includes/queries/categoria_queries.php';
require_once __DIR__ . '/includes/queries/foto_producto_queries.php';
require_once __DIR__ . '/includes/marketing_functions.php'; // Para usar funciones de validación

// ============================================================================
// PROCESAMIENTO DE FORMULARIOS
// ============================================================================

// Verificar si hay mensaje en sesión (de actualización)
if (isset($_SESSION['mensaje']) && isset($_SESSION['mensaje_tipo'])) {
    $mensaje = $_SESSION['mensaje'];
    $mensaje_tipo = $_SESSION['mensaje_tipo'];
    // Limpiar mensaje de sesión después de mostrarlo
    unset($_SESSION['mensaje']);
    unset($_SESSION['mensaje_tipo']);
} else {
    $mensaje = '';
    $mensaje_tipo = '';
}

// Verificar que hay datos CSV en sesión
if (!isset($_SESSION['productos_csv_pendientes']) || empty($_SESSION['productos_csv_pendientes'])) {
    header('Location: marketing.php');
    exit;
}

$productos_csv = $_SESSION['productos_csv_pendientes'];
$nombre_archivo = $_SESSION['nombre_archivo_csv'] ?? 'archivo.csv';

/**
 * Verifica si un producto/variante ya tiene fotos y retorna información
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @param string|null $color Color de la variante (NULL para fotos base)
 * @return array Array con información de fotos existentes o null si no hay
 */
function verificarFotosExistentes($mysqli, $id_producto, $color = null) {
    if ($color === null) {
        // Buscar fotos base (sin color)
        $sql = "SELECT foto_prod_miniatura, foto3_prod FROM Fotos_Producto WHERE id_producto = ? AND (color IS NULL OR color = '') AND activo = 1 LIMIT 1";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id_producto);
    } else {
        // Buscar fotos por color
        $sql = "SELECT foto1_prod, foto2_prod FROM Fotos_Producto WHERE id_producto = ? AND color = ? AND activo = 1 LIMIT 1";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('is', $id_producto, $color);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $fotos = $result->fetch_assoc();
    $stmt->close();
    
    if ($fotos && (($color === null && (!empty($fotos['foto_prod_miniatura']) || !empty($fotos['foto3_prod']))) || 
                   ($color !== null && (!empty($fotos['foto1_prod']) || !empty($fotos['foto2_prod']))))) {
        return $fotos;
    }
    
    return null;
}

/**
 * Guarda fotos de un producto/variante si no existen previamente
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @param string|null $foto_miniatura Ruta de foto miniatura
 * @param string|null $foto1 Ruta de foto 1
 * @param string|null $foto2 Ruta de foto 2
 * @param string|null $foto3 Ruta de foto 3 (grupal)
 * @param string|null $color Color de la variante (NULL para fotos base)
 * @return array Array con ['guardado' => bool, 'advertencia' => string|null]
 */
function guardarFotosProducto($mysqli, $id_producto, $foto_miniatura = null, $foto1 = null, $foto2 = null, $foto3 = null, $color = null) {
    $advertencias = [];
    
    // Verificar si ya existen fotos
    $fotos_existentes = verificarFotosExistentes($mysqli, $id_producto, $color);
    
    if ($color === null) {
        // Fotos base (foto_min y foto3)
        if ($fotos_existentes) {
            $fotos_actuales = [];
            if (!empty($fotos_existentes['foto_prod_miniatura'])) {
                $fotos_actuales[] = 'Miniatura: ' . $fotos_existentes['foto_prod_miniatura'];
            }
            if (!empty($fotos_existentes['foto3_prod'])) {
                $fotos_actuales[] = 'Grupal: ' . $fotos_existentes['foto3_prod'];
            }
            if (!empty($fotos_actuales)) {
                $advertencias[] = 'El producto ya tiene fotos base: ' . implode(', ', $fotos_actuales) . '. No se guardaron las nuevas fotos.';
            }
            // No guardar si ya existen
            return ['guardado' => false, 'advertencia' => implode(' ', $advertencias)];
        }
        
        // Guardar fotos base solo si no existen
        if (!empty($foto_miniatura) || !empty($foto3)) {
            $foto_min = !empty($foto_miniatura) ? $foto_miniatura : null;
            $foto3_val = !empty($foto3) ? $foto3 : null;
            
            $id_foto = insertarFotoProducto($mysqli, $id_producto, $foto_min, null, null, $foto3_val, null);
            if ($id_foto) {
                return ['guardado' => true, 'advertencia' => null];
            }
        }
    } else {
        // Fotos por color (foto1 y foto2)
        if ($fotos_existentes) {
            $fotos_actuales = [];
            if (!empty($fotos_existentes['foto1_prod'])) {
                $fotos_actuales[] = 'Foto 1: ' . $fotos_existentes['foto1_prod'];
            }
            if (!empty($fotos_existentes['foto2_prod'])) {
                $fotos_actuales[] = 'Foto 2: ' . $fotos_existentes['foto2_prod'];
            }
            if (!empty($fotos_actuales)) {
                $advertencias[] = "La variante de color '$color' ya tiene fotos: " . implode(', ', $fotos_actuales) . '. No se guardaron las nuevas fotos.';
            }
            // No guardar si ya existen
            return ['guardado' => false, 'advertencia' => implode(' ', $advertencias)];
        }
        
        // Guardar fotos por color solo si no existen
        if (!empty($foto1) || !empty($foto2)) {
            $foto1_val = !empty($foto1) ? $foto1 : null;
            $foto2_val = !empty($foto2) ? $foto2 : null;
            
            $id_foto = insertarFotoProducto($mysqli, $id_producto, null, $foto1_val, $foto2_val, null, $color);
            if ($id_foto) {
                return ['guardado' => true, 'advertencia' => null];
            }
        }
    }
    
    return ['guardado' => false, 'advertencia' => null];
}

/**
 * Extrae solo el nombre del archivo de una ruta completa
 * @param string $ruta_completa Ruta completa (ej: "imagenes/archivo.webp")
 * @return string Solo el nombre del archivo (ej: "archivo.webp")
 */
function obtenerNombreArchivo($ruta_completa) {
    if (empty($ruta_completa)) {
        return '';
    }
    // Remover prefijo imagenes/ si existe
    $ruta = str_replace('imagenes/', '', $ruta_completa);
    // Obtener solo el nombre del archivo (última parte después de /)
    $partes = explode('/', $ruta);
    return end($partes);
}

/**
 * Desagrupa productos agrupados de vuelta al formato plano CSV
 * Convierte el array agrupado (por nombre de producto) a formato plano (una fila por variante)
 * @param array $productos_agrupados Array agrupado por nombre de producto
 * @return array Array plano con una fila por variante
 */
function desagruparProductosCSV($productos_agrupados) {
    $productos_csv = [];
    
    foreach ($productos_agrupados as $producto) {
        $nombre_producto = $producto['nombre_producto'];
        $descripcion_producto = $producto['descripcion_producto'] ?? '';
        $precio_actual = $producto['precio_actual'];
        $id_categoria = $producto['id_categoria'];
        $genero = $producto['genero'];
        
        // Crear una fila por cada variante
        foreach ($producto['variantes'] as $variante) {
            $fila = [
                'nombre_producto' => $nombre_producto,
                'descripcion_producto' => $descripcion_producto,
                'precio_actual' => $precio_actual,
                'id_categoria' => $id_categoria,
                'genero' => $genero,
                'talle' => $variante['talle'],
                'color' => $variante['color'],
                'stock' => $variante['stock'],
                'foto_prod_miniatura' => $producto['foto_prod_miniatura'] ?? '',
                'foto1_prod' => $variante['foto1_prod'] ?? '',
                'foto2_prod' => $variante['foto2_prod'] ?? '',
                'foto3_prod' => $producto['foto3_prod'] ?? ''
            ];
            
            $productos_csv[] = $fila;
        }
    }
    
    return $productos_csv;
}

// ============================================================================
// CONFIRMAR CARGA MASIVA
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_carga'])) {
    $productos_agrupados = agruparProductosCSV($productos_csv);
    
    // Actualizar productos con valores editados del formulario
    if (isset($_POST['productos_editados']) && is_array($_POST['productos_editados'])) {
        foreach ($_POST['productos_editados'] as $producto_key => $datos_editados) {
            $nombre_original = base64_decode($producto_key);
            if (isset($productos_agrupados[$nombre_original])) {
                $producto_editado = $productos_agrupados[$nombre_original];
                
                // Actualizar campos del producto
                if (isset($datos_editados['nombre_producto'])) {
                    $producto_editado['nombre_producto'] = trim($datos_editados['nombre_producto']);
                }
                if (isset($datos_editados['descripcion_producto'])) {
                    $producto_editado['descripcion_producto'] = trim($datos_editados['descripcion_producto']);
                }
                if (isset($datos_editados['precio_actual'])) {
                    $producto_editado['precio_actual'] = floatval($datos_editados['precio_actual']);
                }
                if (isset($datos_editados['id_categoria'])) {
                    $producto_editado['id_categoria'] = intval($datos_editados['id_categoria']);
                }
                if (isset($datos_editados['genero'])) {
                    $producto_editado['genero'] = trim($datos_editados['genero']);
                }
                
                // Actualizar fotos base (normalizar rutas: agregar imagenes/ si no lo tiene)
                if (isset($datos_editados['foto_prod_miniatura'])) {
                    $foto_min = trim($datos_editados['foto_prod_miniatura']);
                    if (!empty($foto_min) && strpos($foto_min, 'imagenes/') !== 0) {
                        $foto_min = 'imagenes/' . $foto_min;
                    }
                    $producto_editado['foto_prod_miniatura'] = $foto_min;
                }
                if (isset($datos_editados['foto3_prod'])) {
                    $foto3 = trim($datos_editados['foto3_prod']);
                    if (!empty($foto3) && strpos($foto3, 'imagenes/') !== 0) {
                        $foto3 = 'imagenes/' . $foto3;
                    }
                    $producto_editado['foto3_prod'] = $foto3;
                }
                
                // Actualizar variantes
                if (isset($datos_editados['variantes']) && is_array($datos_editados['variantes'])) {
                    foreach ($datos_editados['variantes'] as $idx => $variante_editada) {
                        if (isset($producto_editado['variantes'][$idx])) {
                            if (isset($variante_editada['talle'])) {
                                $producto_editado['variantes'][$idx]['talle'] = trim($variante_editada['talle']);
                            }
                            if (isset($variante_editada['color'])) {
                                $producto_editado['variantes'][$idx]['color'] = trim($variante_editada['color']);
                            }
                            if (isset($variante_editada['stock'])) {
                                $producto_editado['variantes'][$idx]['stock'] = intval($variante_editada['stock']);
                            }
                            // Normalizar rutas de fotos de variantes
                            if (isset($variante_editada['foto1_prod'])) {
                                $foto1 = trim($variante_editada['foto1_prod']);
                                if (!empty($foto1) && strpos($foto1, 'imagenes/') !== 0) {
                                    $foto1 = 'imagenes/' . $foto1;
                                }
                                $producto_editado['variantes'][$idx]['foto1_prod'] = $foto1;
                            }
                            if (isset($variante_editada['foto2_prod'])) {
                                $foto2 = trim($variante_editada['foto2_prod']);
                                if (!empty($foto2) && strpos($foto2, 'imagenes/') !== 0) {
                                    $foto2 = 'imagenes/' . $foto2;
                                }
                                $producto_editado['variantes'][$idx]['foto2_prod'] = $foto2;
                            }
                        }
                    }
                }
                
                // Si el nombre cambió, usar el nuevo nombre como clave
                $nuevo_nombre = $producto_editado['nombre_producto'];
                if ($nuevo_nombre !== $nombre_original) {
                    // Eliminar el producto con el nombre antiguo
                    unset($productos_agrupados[$nombre_original]);
                }
                // Agregar/actualizar con el nuevo nombre
                $productos_agrupados[$nuevo_nombre] = $producto_editado;
            }
        }
    }
    
    // Validación final de todos los datos antes de procesar
    $errores_validacion = [];
    foreach ($productos_agrupados as $nombre_producto => $producto) {
        // Validar nombre de producto
        $validacion_nombre = validarNombreProducto($nombre_producto);
        if (!$validacion_nombre['valido']) {
            $errores_validacion[] = "Producto '$nombre_producto': " . $validacion_nombre['error'];
            continue;
        }
        
        // Validar descripción
        $validacion_descripcion = validarDescripcionProducto($producto['descripcion_producto'] ?? '');
        if (!$validacion_descripcion['valido']) {
            $errores_validacion[] = "Producto '$nombre_producto': " . $validacion_descripcion['error'];
            continue;
        }
        
        // Validar precio
        $validacion_precio = validarPrecio((string)$producto['precio_actual']);
        if (!$validacion_precio['valido']) {
            $errores_validacion[] = "Producto '$nombre_producto': " . $validacion_precio['error'];
            continue;
        }
        
        // Validar variantes
        foreach ($producto['variantes'] as $variante) {
            // Validar talle
            $validacion_talle = validarTalle($variante['talle'] ?? '');
            if (!$validacion_talle['valido']) {
                $errores_validacion[] = "Producto '$nombre_producto' (variante): " . $validacion_talle['error'];
                continue;
            }
            
            // Validar color
            $validacion_color = validarColor($variante['color'] ?? '');
            if (!$validacion_color['valido']) {
                $errores_validacion[] = "Producto '$nombre_producto' (variante): " . $validacion_color['error'];
                continue;
            }
            
            // Validar stock
            $validacion_stock = validarStock((string)($variante['stock'] ?? '0'));
            if (!$validacion_stock['valido']) {
                $errores_validacion[] = "Producto '$nombre_producto' (variante): " . $validacion_stock['error'];
                continue;
            }
        }
    }
    
    // Si hay errores de validación, no proceder
    if (!empty($errores_validacion)) {
        $mensaje = 'Error de validación: Los datos contienen caracteres no permitidos. ' . implode(' ', $errores_validacion);
        $mensaje_tipo = 'danger';
    } else {
        // Si no hay errores, proceder con la carga
        $productos_insertados = 0;
        $productos_actualizados = 0;
        $variantes_insertadas = 0;
        $variantes_actualizadas = 0;
        $errores = [];
        
        // Mejorar manejo de transacción: desactivar autocommit explícitamente
        $mysqli->autocommit(false);
        
        // Iniciar transacción global
        $mysqli->begin_transaction();
        
        // Array para recopilar advertencias de fotos
        $advertencias_fotos = [];
        
        try {
            foreach ($productos_agrupados as $nombre_producto => $producto) {
                $nombre_normalizado = strtolower(trim($nombre_producto));
                
                // Verificar si el producto existe
                $id_producto_existente = obtenerProductoIdPorNombre($mysqli, $nombre_producto);
                
                if ($id_producto_existente) {
                    // Producto existe - verificar si está marcado para actualizar
                    $producto_key = base64_encode($nombre_producto);
                    $actualizar_producto = isset($_POST['actualizar_producto'][$producto_key]) && $_POST['actualizar_producto'][$producto_key] === '1';
                    
                    if (!$actualizar_producto) {
                        // Producto existente no marcado para actualizar, saltar
                        continue;
                    }
                    
                    // Obtener datos del producto existente
                    $producto_existente = obtenerProductoPorId($mysqli, $id_producto_existente);
                    if (!$producto_existente) {
                        throw new Exception('Error: Producto existente no encontrado: ' . $nombre_producto);
                    }
                    
                    // Verificar que la categoría siga activa
                    require_once __DIR__ . '/includes/queries/categoria_queries.php';
                    $sql_cat = "SELECT activo FROM Categorias WHERE id_categoria = ? LIMIT 1";
                    $stmt_cat = $mysqli->prepare($sql_cat);
                    if ($stmt_cat) {
                        $stmt_cat->bind_param('i', $producto_existente['id_categoria']);
                        $stmt_cat->execute();
                        $result_cat = $stmt_cat->get_result();
                        $categoria = $result_cat->fetch_assoc();
                        $stmt_cat->close();
                        if (!$categoria || !$categoria['activo']) {
                            throw new Exception('Error: La categoría del producto ' . $nombre_producto . ' no está activa.');
                        }
                    }
                    
                    // Actualizar campos del producto si está marcado
                    $actualizar_campos = isset($_POST['actualizar_campos'][$producto_key]) && $_POST['actualizar_campos'][$producto_key] === '1';
                    if ($actualizar_campos) {
                        $sql_update = "UPDATE Productos SET precio_actual = ?, descripcion_producto = ? WHERE id_producto = ?";
                        $stmt_update = $mysqli->prepare($sql_update);
                        if (!$stmt_update) {
                            throw new Exception('Error al preparar actualización de producto: ' . $mysqli->error);
                        }
                        $stmt_update->bind_param('dsi', $producto['precio_actual'], $producto['descripcion_producto'], $id_producto_existente);
                        if (!$stmt_update->execute()) {
                            $stmt_update->close();
                            throw new Exception('Error al actualizar producto: ' . $nombre_producto);
                        }
                        $stmt_update->close();
                    }
                    
                    // Obtener variantes existentes
                    $variantes_existentes = obtenerTodasVariantesProducto($mysqli, $id_producto_existente);
                    $variantes_existentes_indexadas = [];
                    foreach ($variantes_existentes as $v) {
                        $clave = strtolower(trim($v['talle'])) . '-' . strtolower(trim($v['color']));
                        $variantes_existentes_indexadas[$clave] = $v;
                    }
                    
                    // Comparar variantes CSV vs existentes
                    $comparacion = compararVariantesCSV($producto['variantes'], $variantes_existentes_indexadas);
                    
                    // Tipo de actualización de stock
                    $tipo_stock = isset($_POST['tipo_stock'][$producto_key]) ? $_POST['tipo_stock'][$producto_key] : 'reemplazar';
                    
                    // Actualizar variantes existentes
                    foreach ($comparacion['actualizar'] as $variante_actualizar) {
                        $id_variante = $variante_actualizar['id_variante'];
                        $stock_csv = $variante_actualizar['stock_csv'];
                        $stock_actual = $variante_actualizar['stock_actual'];
                        
                        if ($tipo_stock === 'sumar') {
                            // Sumar stock
                            $nuevo_stock = $stock_actual + $stock_csv;
                            if ($nuevo_stock < 0) {
                                throw new Exception('Error: El stock resultante sería negativo para variante ' . $variante_actualizar['talle'] . '/' . $variante_actualizar['color']);
                            }
                            $diferencia = $stock_csv;
                        } else {
                            // Reemplazar stock
                            $nuevo_stock = $stock_csv;
                            $diferencia = $stock_csv - $stock_actual;
                        }
                        
                        if ($diferencia != 0) {
                            // Actualizar stock usando movimiento
                            if ($diferencia > 0) {
                                $tipo_movimiento = 'ingreso';
                            } else {
                                $tipo_movimiento = 'ajuste';
                            }
                            
                            if (!registrarMovimientoStock($mysqli, $id_variante, $tipo_movimiento, abs($diferencia), $id_usuario, null, 'Actualización desde CSV', true)) {
                                throw new Exception('Error al actualizar stock de variante: ' . $variante_actualizar['talle'] . ' ' . $variante_actualizar['color']);
                            }
                            $variantes_actualizadas++;
                        }
                    }
                    
                    // Crear variantes nuevas si está marcado
                    $crear_variantes_nuevas = isset($_POST['crear_variantes'][$producto_key]) && $_POST['crear_variantes'][$producto_key] === '1';
                    if ($crear_variantes_nuevas) {
                        foreach ($comparacion['crear'] as $variante_nueva) {
                            // Verificar si ya existe la variante (por si acaso)
                            $existe = verificarVarianteExistente($mysqli, $nombre_producto, $producto_existente['id_categoria'], $producto_existente['genero'], $variante_nueva['talle'], $variante_nueva['color']);
                            
                            if (!$existe) {
                                // Insertar variante con stock=0
                                $id_variante_nueva = insertarVarianteStock($mysqli, $id_producto_existente, $variante_nueva['talle'], $variante_nueva['color'], 0);
                                
                                if (!$id_variante_nueva) {
                                    throw new Exception('Error al insertar variante: ' . $variante_nueva['talle'] . ' ' . $variante_nueva['color']);
                                }
                                
                                $variantes_insertadas++;
                                
                                // Registrar movimiento de stock inicial
                                if ($variante_nueva['stock'] > 0) {
                                    if (!registrarMovimientoStock($mysqli, $id_variante_nueva, 'ingreso', $variante_nueva['stock'], $id_usuario, null, 'Stock inicial - Carga masiva CSV', true)) {
                                        throw new Exception('Error al registrar movimiento de stock para variante: ' . $variante_nueva['talle'] . ' ' . $variante_nueva['color']);
                                    }
                                }
                            }
                        }
                    }
                    
                    // Guardar fotos base del producto existente (si no existen)
                    if (!empty($producto['foto_prod_miniatura']) || !empty($producto['foto3_prod'])) {
                        $resultado_fotos = guardarFotosProducto($mysqli, $id_producto_existente, 
                            $producto['foto_prod_miniatura'] ?? null, 
                            null, null, 
                            $producto['foto3_prod'] ?? null, 
                            null);
                        if (!empty($resultado_fotos['advertencia'])) {
                            $advertencias_fotos[] = $producto['nombre_producto'] . ': ' . $resultado_fotos['advertencia'];
                        }
                    }
                    
                    // Guardar fotos por color de variantes nuevas creadas
                    if ($crear_variantes_nuevas) {
                        foreach ($comparacion['crear'] as $variante_nueva) {
                            // Buscar la variante en el array de variantes del producto para obtener fotos
                            foreach ($producto['variantes'] as $v) {
                                if (strtolower(trim($v['talle'])) === strtolower(trim($variante_nueva['talle'])) && 
                                    strtolower(trim($v['color'])) === strtolower(trim($variante_nueva['color']))) {
                                    if (!empty($v['foto1_prod']) || !empty($v['foto2_prod'])) {
                                        $resultado_fotos = guardarFotosProducto($mysqli, $id_producto_existente, 
                                            null, 
                                            $v['foto1_prod'] ?? null, 
                                            $v['foto2_prod'] ?? null, 
                                            null, 
                                            $variante_nueva['color']);
                                        if (!empty($resultado_fotos['advertencia'])) {
                                            $advertencias_fotos[] = $producto['nombre_producto'] . ' (' . $variante_nueva['talle'] . '/' . $variante_nueva['color'] . '): ' . $resultado_fotos['advertencia'];
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                    }
                    
                    $productos_actualizados++;
                    
                } else {
                    // Producto nuevo - crear normalmente
                    $id_producto_nuevo = crearProducto($mysqli, 
                        $producto['nombre_producto'], 
                        $producto['descripcion_producto'], 
                        $producto['precio_actual'], 
                        $producto['id_categoria'], 
                        $producto['genero'], 
                        null // SKU opcional
                    );
                    
                    if ($id_producto_nuevo <= 0) {
                        throw new Exception('Error al crear producto: ' . $producto['nombre_producto'] . '. Verifica que la categoría esté activa.');
                    }
                    
                    $productos_insertados++;
                    
                    // Guardar fotos base del producto nuevo (una sola vez)
                    if (!empty($producto['foto_prod_miniatura']) || !empty($producto['foto3_prod'])) {
                        $resultado_fotos = guardarFotosProducto($mysqli, $id_producto_nuevo, 
                            $producto['foto_prod_miniatura'] ?? null, 
                            null, null, 
                            $producto['foto3_prod'] ?? null, 
                            null);
                        if (!empty($resultado_fotos['advertencia'])) {
                            $advertencias_fotos[] = $producto['nombre_producto'] . ': ' . $resultado_fotos['advertencia'];
                        }
                    }
                    
                    // Insertar variantes
                    foreach ($producto['variantes'] as $variante) {
                        $id_variante_nueva = insertarVarianteStock($mysqli, $id_producto_nuevo, $variante['talle'], $variante['color'], 0);
                        
                        if (!$id_variante_nueva) {
                            throw new Exception('Error al insertar variante: ' . $variante['talle'] . ' ' . $variante['color']);
                        }
                        
                        $variantes_insertadas++;
                        
                        // Registrar movimiento de stock inicial
                        if ($variante['stock'] > 0) {
                            if (!registrarMovimientoStock($mysqli, $id_variante_nueva, 'ingreso', $variante['stock'], $id_usuario, null, 'Stock inicial - Carga masiva CSV', true)) {
                                throw new Exception('Error al registrar movimiento de stock para variante: ' . $variante['talle'] . ' ' . $variante['color']);
                            }
                        }
                        
                        // Guardar fotos por color de la variante
                        if (!empty($variante['foto1_prod']) || !empty($variante['foto2_prod'])) {
                            $resultado_fotos = guardarFotosProducto($mysqli, $id_producto_nuevo, 
                                null, 
                                $variante['foto1_prod'] ?? null, 
                                $variante['foto2_prod'] ?? null, 
                                null, 
                                $variante['color']);
                            if (!empty($resultado_fotos['advertencia'])) {
                                $advertencias_fotos[] = $producto['nombre_producto'] . ' (' . $variante['talle'] . '/' . $variante['color'] . '): ' . $resultado_fotos['advertencia'];
                            }
                        }
                    }
                    
                }
            }
            
            // Validar que se procesó al menos un producto o variante antes de hacer commit
            $total_procesado = $productos_insertados + $productos_actualizados + $variantes_insertadas + $variantes_actualizadas;
            
            if ($total_procesado === 0) {
                // No se procesó ningún producto, hacer rollback y mostrar error
                $mysqli->rollback();
                $mysqli->autocommit(true); // Restaurar autocommit
                $mensaje = 'Error: No se procesó ningún producto. Verifica que hayas seleccionado productos existentes para actualizar o que haya productos nuevos en el CSV.';
                $mensaje_tipo = 'warning';
            } else {
                // Verificar que el commit sea exitoso
                if (!$mysqli->commit()) {
                    throw new Exception('Error al confirmar los cambios en la base de datos: ' . $mysqli->error);
                }
                
                // Restaurar autocommit
                $mysqli->autocommit(true);
                
                // Limpiar datos de sesión
                unset($_SESSION['productos_csv_pendientes']);
                unset($_SESSION['nombre_archivo_csv']);
                unset($_SESSION['errores_csv']);
                
                $mensaje_parts = [];
                if ($productos_insertados > 0) {
                    $mensaje_parts[] = "$productos_insertados producto(s) nuevo(s) creado(s)";
                }
                if ($productos_actualizados > 0) {
                    $mensaje_parts[] = "$productos_actualizados producto(s) actualizado(s)";
                }
                if ($variantes_insertadas > 0) {
                    $mensaje_parts[] = "$variantes_insertadas variante(s) nueva(s)";
                }
                if ($variantes_actualizadas > 0) {
                    $mensaje_parts[] = "$variantes_actualizadas variante(s) actualizada(s)";
                }
                
                // Solo mostrar mensaje de éxito si hay algo que reportar
                if (!empty($mensaje_parts)) {
                    $mensaje = "Carga masiva completada exitosamente: " . implode(', ', $mensaje_parts) . ".";
                    $mensaje_tipo = 'success';
                } else {
                    // Esto no debería pasar si la validación anterior funciona, pero por seguridad
                    $mensaje = 'Advertencia: No se detectaron cambios para guardar.';
                    $mensaje_tipo = 'warning';
                }
                
                // Agregar advertencias de fotos si las hay
                if (!empty($advertencias_fotos)) {
                    $_SESSION['advertencias_fotos_csv'] = $advertencias_fotos;
                }
                
                // Guardar mensaje en sesión y redirigir a marketing.php con pestaña de productos activa
                $_SESSION['mensaje'] = $mensaje;
                $_SESSION['mensaje_tipo'] = $mensaje_tipo;
                header('Location: marketing.php?tab=productos');
                exit;
            }
            
        } catch (Exception $e) {
            // Hacer rollback en caso de error
            $mysqli->rollback();
            $mysqli->autocommit(true); // Restaurar autocommit
            
            $mensaje = 'Error durante la carga masiva: ' . $e->getMessage();
            $mensaje_tipo = 'danger';
        }
    }
}

// ============================================================================
// ACTUALIZAR CARGA MASIVA (Recargar página con datos editados)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_carga'])) {
    $productos_agrupados = agruparProductosCSV($productos_csv);
    $hubo_cambios = false;
    
    // Actualizar productos con valores editados del formulario
    if (isset($_POST['productos_editados']) && is_array($_POST['productos_editados'])) {
        foreach ($_POST['productos_editados'] as $producto_key => $datos_editados) {
            $nombre_original = base64_decode($producto_key);
            if (isset($productos_agrupados[$nombre_original])) {
                $producto_editado = $productos_agrupados[$nombre_original];
                $producto_cambio = false;
                
                // Actualizar campos del producto
                if (isset($datos_editados['nombre_producto'])) {
                    $nuevo_nombre = trim($datos_editados['nombre_producto']);
                    if ($nuevo_nombre !== $producto_editado['nombre_producto']) {
                        $producto_editado['nombre_producto'] = $nuevo_nombre;
                        $producto_cambio = true;
                    }
                }
                if (isset($datos_editados['descripcion_producto'])) {
                    $nueva_desc = trim($datos_editados['descripcion_producto']);
                    if ($nueva_desc !== ($producto_editado['descripcion_producto'] ?? '')) {
                        $producto_editado['descripcion_producto'] = $nueva_desc;
                        $producto_cambio = true;
                    }
                }
                if (isset($datos_editados['precio_actual'])) {
                    $nuevo_precio = floatval($datos_editados['precio_actual']);
                    if ($nuevo_precio != $producto_editado['precio_actual']) {
                        $producto_editado['precio_actual'] = $nuevo_precio;
                        $producto_cambio = true;
                    }
                }
                if (isset($datos_editados['id_categoria'])) {
                    $nueva_cat = intval($datos_editados['id_categoria']);
                    if ($nueva_cat != $producto_editado['id_categoria']) {
                        $producto_editado['id_categoria'] = $nueva_cat;
                        $producto_cambio = true;
                    }
                }
                if (isset($datos_editados['genero'])) {
                    $nuevo_genero = trim($datos_editados['genero']);
                    if ($nuevo_genero !== $producto_editado['genero']) {
                        $producto_editado['genero'] = $nuevo_genero;
                        $producto_cambio = true;
                    }
                }
                
                // Actualizar fotos base (normalizar rutas: agregar imagenes/ si no lo tiene)
                if (isset($datos_editados['foto_prod_miniatura'])) {
                    $nueva_foto_min = trim($datos_editados['foto_prod_miniatura']);
                    if (!empty($nueva_foto_min) && strpos($nueva_foto_min, 'imagenes/') !== 0) {
                        $nueva_foto_min = 'imagenes/' . $nueva_foto_min;
                    }
                    if ($nueva_foto_min !== ($producto_editado['foto_prod_miniatura'] ?? '')) {
                        $producto_editado['foto_prod_miniatura'] = $nueva_foto_min;
                        $producto_cambio = true;
                    }
                }
                if (isset($datos_editados['foto3_prod'])) {
                    $nueva_foto3 = trim($datos_editados['foto3_prod']);
                    if (!empty($nueva_foto3) && strpos($nueva_foto3, 'imagenes/') !== 0) {
                        $nueva_foto3 = 'imagenes/' . $nueva_foto3;
                    }
                    if ($nueva_foto3 !== ($producto_editado['foto3_prod'] ?? '')) {
                        $producto_editado['foto3_prod'] = $nueva_foto3;
                        $producto_cambio = true;
                    }
                }
                
                // Actualizar variantes
                if (isset($datos_editados['variantes']) && is_array($datos_editados['variantes'])) {
                    foreach ($datos_editados['variantes'] as $idx => $variante_editada) {
                        if (isset($producto_editado['variantes'][$idx])) {
                            if (isset($variante_editada['talle'])) {
                                $nuevo_talle = trim($variante_editada['talle']);
                                if ($nuevo_talle !== $producto_editado['variantes'][$idx]['talle']) {
                                    $producto_editado['variantes'][$idx]['talle'] = $nuevo_talle;
                                    $producto_cambio = true;
                                }
                            }
                            if (isset($variante_editada['color'])) {
                                $nuevo_color = trim($variante_editada['color']);
                                if ($nuevo_color !== $producto_editado['variantes'][$idx]['color']) {
                                    $producto_editado['variantes'][$idx]['color'] = $nuevo_color;
                                    $producto_cambio = true;
                                }
                            }
                            if (isset($variante_editada['stock'])) {
                                $nuevo_stock = intval($variante_editada['stock']);
                                if ($nuevo_stock != $producto_editado['variantes'][$idx]['stock']) {
                                    $producto_editado['variantes'][$idx]['stock'] = $nuevo_stock;
                                    $producto_cambio = true;
                                }
                            }
                            // Normalizar rutas de fotos de variantes
                            if (isset($variante_editada['foto1_prod'])) {
                                $nueva_foto1 = trim($variante_editada['foto1_prod']);
                                if (!empty($nueva_foto1) && strpos($nueva_foto1, 'imagenes/') !== 0) {
                                    $nueva_foto1 = 'imagenes/' . $nueva_foto1;
                                }
                                if ($nueva_foto1 !== ($producto_editado['variantes'][$idx]['foto1_prod'] ?? '')) {
                                    $producto_editado['variantes'][$idx]['foto1_prod'] = $nueva_foto1;
                                    $producto_cambio = true;
                                }
                            }
                            if (isset($variante_editada['foto2_prod'])) {
                                $nueva_foto2 = trim($variante_editada['foto2_prod']);
                                if (!empty($nueva_foto2) && strpos($nueva_foto2, 'imagenes/') !== 0) {
                                    $nueva_foto2 = 'imagenes/' . $nueva_foto2;
                                }
                                if ($nueva_foto2 !== ($producto_editado['variantes'][$idx]['foto2_prod'] ?? '')) {
                                    $producto_editado['variantes'][$idx]['foto2_prod'] = $nueva_foto2;
                                    $producto_cambio = true;
                                }
                            }
                        }
                    }
                }
                
                if ($producto_cambio) {
                    $hubo_cambios = true;
                }
                
                // Si el nombre cambió, usar el nuevo nombre como clave
                $nuevo_nombre = $producto_editado['nombre_producto'];
                if ($nuevo_nombre !== $nombre_original) {
                    // Eliminar el producto con el nombre antiguo
                    unset($productos_agrupados[$nombre_original]);
                }
                // Agregar/actualizar con el nuevo nombre
                $productos_agrupados[$nuevo_nombre] = $producto_editado;
            }
        }
    }
    
    // Convertir productos agrupados de vuelta al formato plano CSV
    $productos_csv_actualizados = desagruparProductosCSV($productos_agrupados);
    
    // Actualizar sesión con los datos editados
    $_SESSION['productos_csv_pendientes'] = $productos_csv_actualizados;
    
    // Si hubo cambios, guardar mensaje de éxito
    if ($hubo_cambios) {
        $_SESSION['mensaje'] = 'PRODUCTOS MODIFICADOS CORRECTAMENTE';
        $_SESSION['mensaje_tipo'] = 'success';
    }
    
    // Redirigir a la misma página para recargar con datos actualizados
    header('Location: marketing-confirmar-csv.php');
    exit;
}

// ============================================================================
// CANCELAR CARGA MASIVA
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_carga'])) {
    // Limpiar datos de sesión
    unset($_SESSION['productos_csv_pendientes']);
    unset($_SESSION['nombre_archivo_csv']);
    unset($_SESSION['errores_csv']);
    
    header('Location: marketing.php');
    exit;
}

// ============================================================================
// CARGAR FUNCIONES AUXILIARES
// ============================================================================

// Nota: Las funciones necesarias ya se cargaron arriba antes del procesamiento de formularios
// (producto_queries.php, stock_queries.php, categoria_queries.php)

// Obtener productos agrupados para mostrar
$productos_agrupados = agruparProductosCSV($productos_csv);

// Obtener nombres de categorías para mostrar (solo categorías activas)
$categorias_array = obtenerCategorias($mysqli);
$categorias = [];
foreach ($categorias_array as $cat) {
    $categorias[$cat['id_categoria']] = $cat['nombre_categoria'];
}

// Obtener productos existentes completos con sus datos y variantes
$nombres_productos_csv = array_keys($productos_agrupados);
$productos_existentes_completos = obtenerProductosExistentesCompletos($mysqli, $nombres_productos_csv);

// Crear array simple para compatibilidad con código existente
$productos_existentes = [];
foreach ($productos_existentes_completos as $nombre => $datos) {
    $productos_existentes[$nombre] = true;
}

// Contar productos nuevos vs existentes
$productos_nuevos = 0;
$productos_duplicados = 0;
foreach ($productos_agrupados as $nombre => $producto) {
    $nombre_lower = strtolower(trim($nombre));
    if (isset($productos_existentes[$nombre_lower])) {
        $productos_duplicados++;
    } else {
        $productos_nuevos++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Carga Masiva | Seda y Lino</title>
    <link rel="stylesheet" href="/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/css/marketing-confirmar.css">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <main class="confirm-page">
        <div class="container">
            <!-- Header -->
            <div class="confirm-header">
                <h1><i class="fas fa-check-circle me-3"></i>Confirmar Carga Masiva</h1>
                <p class="mb-0">Revisa los productos antes de confirmar la carga</p>
                <div class="mt-3">
                    <span class="badge bg-primary me-2">Archivo: <?= htmlspecialchars($nombre_archivo) ?></span>
                    <span class="badge bg-info">Usuario: <?= htmlspecialchars($usuario_actual['nombre'] . ' ' . $usuario_actual['apellido']) ?></span>
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
            
            <!-- Errores CSV si los hay (solo mostrar si hay productos procesados, no errores de headers) -->
            <?php if (!empty($_SESSION['errores_csv']) && !empty($productos_agrupados)): 
                // Separar errores reales de advertencias
                $errores_reales = [];
                $advertencias = [];
                foreach ($_SESSION['errores_csv'] as $error) {
                    // No mostrar errores de headers si hay productos procesados (headers eran válidos)
                    if (strpos($error, 'ERROR: Headers requeridos faltantes') !== false) {
                        continue; // Omitir errores de headers
                    }
                    // Separar advertencias de errores
                    if (strpos($error, 'ADVERTENCIA') === 0) {
                        $advertencias[] = str_replace('ADVERTENCIA ', '', $error);
                    } else {
                        $errores_reales[] = $error;
                    }
                }
            ?>
                <?php if (!empty($errores_reales)): ?>
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-circle me-2"></i>Errores encontrados en el CSV:</h5>
                    <ul class="mb-0">
                        <?php foreach ($errores_reales as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php if (!empty($advertencias)): ?>
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle me-2"></i>Advertencias encontradas en el CSV:</h5>
                    <ul class="mb-0">
                        <?php foreach ($advertencias as $advertencia): ?>
                            <li><?= htmlspecialchars($advertencia) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <small class="d-block mt-2">Puedes continuar con la carga. Revisa las advertencias antes de confirmar.</small>
                </div>
                <?php endif; ?>
            <?php elseif (!empty($_SESSION['errores_csv']) && empty($productos_agrupados)): ?>
            <!-- Si no hay productos, mostrar todos los errores (incluyendo headers) -->
            <div class="alert alert-danger">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Errores encontrados en el CSV:</h5>
                <ul class="mb-0">
                    <?php foreach ($_SESSION['errores_csv'] as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <!-- Resumen estadístico simplificado -->
            <div class="stats-summary">
                <div class="row text-center">
                    <div class="col-md-2">
                        <h3 class="text-primary mb-1"><?= count($productos_agrupados) ?></h3>
                        <small class="text-muted">Productos</small>
                    </div>
                    <div class="col-md-2">
                        <h3 class="text-success mb-1"><?= count($productos_csv) ?></h3>
                        <small class="text-muted">Variantes</small>
                    </div>
                    <div class="col-md-2">
                        <h3 class="text-info mb-1"><?= array_sum(array_column($productos_csv, 'stock')) ?></h3>
                        <small class="text-muted">Stock Total</small>
                    </div>
                    <div class="col-md-2">
                        <h3 class="text-warning mb-1"><?= $productos_nuevos ?></h3>
                        <small class="text-muted">Nuevos</small>
                    </div>
                    <div class="col-md-2">
                        <h3 class="text-secondary mb-1"><?= $productos_duplicados ?></h3>
                        <small class="text-muted">Existentes</small>
                    </div>
                    <div class="col-md-2">
                        <h3 class="text-dark mb-1">$<?= number_format(array_sum(array_column($productos_agrupados, 'precio_actual')), 0, ',', '.') ?></h3>
                        <small class="text-muted">Valor Total</small>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($_SESSION['categorias_creadas_csv'])): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Categorías creadas automáticamente:</strong> 
                <?= implode(', ', array_map('htmlspecialchars', $_SESSION['categorias_creadas_csv'])) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($productos_duplicados > 0): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Productos existentes detectados:</strong> Se encontraron <?= $productos_duplicados ?> producto(s) que ya existen en el sistema. 
                Puedes seleccionarlos para actualizar su stock y variantes, o dejarlos sin marcar para crear productos nuevos con el mismo nombre.
            </div>
            <?php endif; ?>
            
            <!-- Vista previa de productos - Tabla con checkboxes para productos existentes -->
            <form method="POST" id="formConfirmarCarga">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Vista Previa de Productos</h5>
                        <small class="text-muted">Puedes editar los campos antes de confirmar. Selecciona los productos existentes que deseas actualizar.</small>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;">
                                            <?php if ($productos_duplicados > 0): ?>
                                            <input type="checkbox" id="selectAllExistentes" title="Seleccionar todos los productos existentes">
                                            <?php endif; ?>
                                        </th>
                                        <th>Producto</th>
                                        <th>Fotos Base</th>
                                        <th>Categoría</th>
                                        <th>Género</th>
                                        <th>Precio</th>
                                        <th>Variantes</th>
                                        <th>Stock</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos_agrupados as $nombre => $producto): 
                                        $nombre_lower = strtolower(trim($nombre));
                                        $es_nuevo = !isset($productos_existentes[$nombre_lower]);
                                        $stock_total = array_sum(array_column($producto['variantes'], 'stock'));
                                        $producto_key = base64_encode($nombre);
                                        
                                        // Si es existente, obtener datos completos
                                        $producto_existente_data = null;
                                        $comparacion_variantes = null;
                                        if (!$es_nuevo && isset($productos_existentes_completos[$nombre_lower])) {
                                            $producto_existente_data = $productos_existentes_completos[$nombre_lower];
                                            $comparacion_variantes = compararVariantesCSV($producto['variantes'], $producto_existente_data['variantes']);
                                        }
                                    ?>
                                    <tr class="<?= $es_nuevo ? 'table-success' : 'table-warning' ?>">
                                        <td>
                                            <?php if (!$es_nuevo): ?>
                                            <input type="checkbox" 
                                                   name="actualizar_producto[<?= htmlspecialchars($producto_key) ?>]" 
                                                   value="1" 
                                                   class="checkbox-existente"
                                                   data-producto="<?= htmlspecialchars($producto_key) ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   class="form-control form-control-sm" 
                                                   name="productos_editados[<?= htmlspecialchars($producto_key) ?>][nombre_producto]" 
                                                   value="<?= htmlspecialchars($producto['nombre_producto']) ?>"
                                                   required
                                                   pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ0-9\s\-]{3,100}"
                                                   title="Solo letras, números, espacios y guiones. Mínimo 3 caracteres, máximo 100."
                                                   style="min-width: 200px;">
                                            <textarea class="form-control form-control-sm mt-1" 
                                                      name="productos_editados[<?= htmlspecialchars($producto_key) ?>][descripcion_producto]" 
                                                      rows="2"
                                                      maxlength="255"
                                                      title="Máximo 255 caracteres. No se permiten: < > { } [ ] | \ / &"
                                                      style="min-width: 200px; font-size: 0.85em;"><?= htmlspecialchars($producto['descripcion_producto'] ?? '') ?></textarea>
                                            <?php if (!$es_nuevo && $producto_existente_data): ?>
                                            <br><small class="text-info">
                                                <i class="fas fa-info-circle"></i> 
                                                Precio actual: $<?= number_format($producto_existente_data['precio_actual'], 0, ',', '.') ?>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fotos-base">
                                                <label class="form-label small mb-1 fw-semibold">Foto Miniatura:</label>
                                                <input type="text" 
                                                       class="form-control form-control-sm" 
                                                       name="productos_editados[<?= htmlspecialchars($producto_key) ?>][foto_prod_miniatura]" 
                                                       value="<?= htmlspecialchars(obtenerNombreArchivo($producto['foto_prod_miniatura'] ?? '')) ?>"
                                                       placeholder="archivo.webp"
                                                       style="font-size: 0.9em; font-family: monospace;">
                                                <label class="form-label small mb-1 mt-2 fw-semibold">Foto 3 (Grupal):</label>
                                                <input type="text" 
                                                       class="form-control form-control-sm" 
                                                       name="productos_editados[<?= htmlspecialchars($producto_key) ?>][foto3_prod]" 
                                                       value="<?= htmlspecialchars(obtenerNombreArchivo($producto['foto3_prod'] ?? '')) ?>"
                                                       placeholder="archivo.webp"
                                                       style="font-size: 0.9em; font-family: monospace;">
                                            </div>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" 
                                                    name="productos_editados[<?= htmlspecialchars($producto_key) ?>][id_categoria]"
                                                    required>
                                                <?php foreach ($categorias_array as $cat): ?>
                                                <option value="<?= $cat['id_categoria'] ?>" 
                                                        <?= $producto['id_categoria'] == $cat['id_categoria'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cat['nombre_categoria']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" 
                                                    name="productos_editados[<?= htmlspecialchars($producto_key) ?>][genero]"
                                                    required>
                                                <option value="hombre" <?= $producto['genero'] === 'hombre' ? 'selected' : '' ?>>Hombre</option>
                                                <option value="mujer" <?= $producto['genero'] === 'mujer' ? 'selected' : '' ?>>Mujer</option>
                                                <option value="unisex" <?= $producto['genero'] === 'unisex' ? 'selected' : '' ?>>Unisex</option>
                                            </select>
                                        </td>
                                        <td class="text-end">
                                            <input type="number" 
                                                   class="form-control form-control-sm text-end" 
                                                   name="productos_editados[<?= htmlspecialchars($producto_key) ?>][precio_actual]" 
                                                   value="<?= number_format($producto['precio_actual'], 2, '.', '') ?>"
                                                   step="0.01"
                                                   min="0.01"
                                                   required
                                                   title="Precio debe ser mayor a 0"
                                                   style="min-width: 120px;">
                                            <?php if (!$es_nuevo && $producto_existente_data && $producto_existente_data['precio_actual'] != $producto['precio_actual']): ?>
                                            <br><small class="text-warning">
                                                <i class="fas fa-arrow-right"></i> 
                                                $<?= number_format($producto_existente_data['precio_actual'], 0, ',', '.') ?>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="variantes-editables" style="max-height: 150px; overflow-y: auto;">
                                                <?php foreach ($producto['variantes'] as $idx => $variante): ?>
                                                <div class="mb-2 p-2 border rounded">
                                                    <div class="d-flex gap-1 mb-1 align-items-center">
                                                        <input type="text" 
                                                               class="form-control form-control-sm" 
                                                               name="productos_editados[<?= htmlspecialchars($producto_key) ?>][variantes][<?= $idx ?>][talle]" 
                                                               value="<?= htmlspecialchars($variante['talle']) ?>"
                                                               required
                                                               pattern="[a-zA-Z0-9\-]{1,50}"
                                                               title="Solo letras, números y guiones. Máximo 50 caracteres."
                                                               placeholder="Talle"
                                                               style="width: 60px;">
                                                        <input type="text" 
                                                               class="form-control form-control-sm" 
                                                               name="productos_editados[<?= htmlspecialchars($producto_key) ?>][variantes][<?= $idx ?>][color]" 
                                                               value="<?= htmlspecialchars($variante['color']) ?>"
                                                               required
                                                               pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ]{3,50}"
                                                               title="Solo letras. Mínimo 3 caracteres, máximo 50."
                                                               placeholder="Color"
                                                               style="width: 80px;">
                                                        <input type="number" 
                                                               class="form-control form-control-sm text-end" 
                                                               name="productos_editados[<?= htmlspecialchars($producto_key) ?>][variantes][<?= $idx ?>][stock]" 
                                                               value="<?= intval($variante['stock']) ?>"
                                                               min="0"
                                                               required
                                                               title="Stock debe ser 0 o mayor"
                                                               style="width: 70px;">
                                                    </div>
                                                    <div class="d-flex gap-1">
                                                        <input type="text" 
                                                               class="form-control form-control-sm" 
                                                               name="productos_editados[<?= htmlspecialchars($producto_key) ?>][variantes][<?= $idx ?>][foto1_prod]" 
                                                               value="<?= htmlspecialchars(obtenerNombreArchivo($variante['foto1_prod'] ?? '')) ?>"
                                                               placeholder="archivo.webp"
                                                               style="width: 120px; font-size: 0.9em; font-family: monospace;">
                                                        <input type="text" 
                                                               class="form-control form-control-sm" 
                                                               name="productos_editados[<?= htmlspecialchars($producto_key) ?>][variantes][<?= $idx ?>][foto2_prod]" 
                                                               value="<?= htmlspecialchars(obtenerNombreArchivo($variante['foto2_prod'] ?? '')) ?>"
                                                               placeholder="archivo.webp"
                                                               style="width: 120px; font-size: 0.9em; font-family: monospace;">
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <small class="text-muted">Total: <?= count($producto['variantes']) ?> variante(s)</small>
                                            <?php if (!$es_nuevo && $comparacion_variantes): ?>
                                            <br><small class="text-info">
                                                <i class="fas fa-sync"></i> 
                                                Actualizar: <?= count($comparacion_variantes['actualizar']) ?>, 
                                                Nuevas: <?= count($comparacion_variantes['crear']) ?>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge <?= $stock_total > 0 ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= number_format($stock_total, 0, ',', '.') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($es_nuevo): ?>
                                                <span class="badge bg-success"><i class="fas fa-plus-circle me-1"></i>Nuevo</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i>Existente</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    
                                    <?php if (!$es_nuevo): ?>
                                    <!-- Opciones de actualización para productos existentes -->
                                    <tr class="opciones-actualizacion" id="opciones-<?= htmlspecialchars($producto_key) ?>" style="display: none;">
                                        <td colspan="8" class="bg-light">
                                            <div class="row p-3">
                                                <div class="col-md-12 mb-3">
                                                    <h6><i class="fas fa-cog me-2"></i>Opciones de actualización para: <strong><?= htmlspecialchars($producto['nombre_producto']) ?></strong></h6>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" 
                                                               type="checkbox" 
                                                               name="actualizar_campos[<?= htmlspecialchars($producto_key) ?>]" 
                                                               value="1" 
                                                               id="actualizar_campos_<?= htmlspecialchars($producto_key) ?>">
                                                        <label class="form-check-label" for="actualizar_campos_<?= htmlspecialchars($producto_key) ?>">
                                                            <strong>Actualizar precio y descripción</strong>
                                                            <br><small class="text-muted">Actualizará el precio y descripción del producto con los valores del CSV</small>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" 
                                                               type="checkbox" 
                                                               name="crear_variantes[<?= htmlspecialchars($producto_key) ?>]" 
                                                               value="1" 
                                                               id="crear_variantes_<?= htmlspecialchars($producto_key) ?>"
                                                               <?= $comparacion_variantes && count($comparacion_variantes['crear']) > 0 ? '' : 'disabled' ?>>
                                                        <label class="form-check-label" for="crear_variantes_<?= htmlspecialchars($producto_key) ?>">
                                                            <strong>Crear variantes nuevas</strong>
                                                            <br><small class="text-muted">
                                                                <?php if ($comparacion_variantes && count($comparacion_variantes['crear']) > 0): ?>
                                                                    Creará <?= count($comparacion_variantes['crear']) ?> variante(s) nueva(s)
                                                                <?php else: ?>
                                                                    No hay variantes nuevas para crear
                                                                <?php endif; ?>
                                                            </small>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label"><strong>Tipo de actualización de stock:</strong></label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" 
                                                               type="radio" 
                                                               name="tipo_stock[<?= htmlspecialchars($producto_key) ?>]" 
                                                               value="reemplazar" 
                                                               id="tipo_stock_reemplazar_<?= htmlspecialchars($producto_key) ?>"
                                                               checked>
                                                        <label class="form-check-label" for="tipo_stock_reemplazar_<?= htmlspecialchars($producto_key) ?>">
                                                            Reemplazar stock
                                                            <br><small class="text-muted">El stock del CSV reemplazará el stock actual</small>
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" 
                                                               type="radio" 
                                                               name="tipo_stock[<?= htmlspecialchars($producto_key) ?>]" 
                                                               value="sumar" 
                                                               id="tipo_stock_sumar_<?= htmlspecialchars($producto_key) ?>">
                                                        <label class="form-check-label" for="tipo_stock_sumar_<?= htmlspecialchars($producto_key) ?>">
                                                            Sumar stock
                                                            <br><small class="text-muted">El stock del CSV se sumará al stock actual</small>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            
            <!-- Botones de acción -->
            <div class="text-center mt-4">
                <button type="submit" name="confirmar_carga" form="formConfirmarCarga" class="btn btn-success btn-lg me-3">
                    <i class="fas fa-check me-2"></i>Confirmar Carga
                </button>
                
                <button type="submit" name="actualizar_carga" form="formConfirmarCarga" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-sync me-2"></i>Actualizar
                </button>
                
                <form method="POST" class="d-inline me-3">
                    <button type="submit" name="cancelar_carga" class="btn btn-outline-danger btn-lg">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                </form>
                
                <a href="marketing.php" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Volver a Marketing
                </a>
            </div>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script>
        // Mostrar/ocultar opciones de actualización al seleccionar checkbox
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.checkbox-existente');
            checkboxes.forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    const productoKey = this.getAttribute('data-producto');
                    const opcionesRow = document.getElementById('opciones-' + productoKey);
                    if (opcionesRow) {
                        opcionesRow.style.display = this.checked ? 'table-row' : 'none';
                    }
                });
            });
            
            // Seleccionar todos los productos existentes
            const selectAll = document.getElementById('selectAllExistentes');
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(function(checkbox) {
                        checkbox.checked = selectAll.checked;
                        const productoKey = checkbox.getAttribute('data-producto');
                        const opcionesRow = document.getElementById('opciones-' + productoKey);
                        if (opcionesRow) {
                            opcionesRow.style.display = selectAll.checked ? 'table-row' : 'none';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>
