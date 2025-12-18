<?php
/**
 * ========================================================================
 * FUNCIONES DE PROCESAMIENTO CSV - Tienda Seda y Lino
 * ========================================================================
 * Funciones para procesar y validar archivos CSV de productos
 * 
 * FUNCIONES:
 * - procesarCSV(): Procesa archivo CSV, valida datos y retorna array de productos
 *   * Valida headers requeridos (case insensitive)
 *   * Valida cada línea (nombre, precio, género, categoría, talle, color, stock)
 *   * Normaliza datos y guarda errores en sesión
 *   * Valida que las categorías existan en la base de datos
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Elimina el BOM (Byte Order Mark) UTF-8 de una cadena
 * El BOM puede aparecer al inicio de archivos CSV generados por Excel
 * 
 * @param string $texto Texto que puede contener BOM
 * @return string Texto sin BOM
 */
function eliminarBOM($texto) {
    // Eliminar BOM UTF-8 (EF BB BF en hexadecimal)
    return preg_replace('/^\xEF\xBB\xBF/', '', $texto);
}

/**
 * Normaliza un header CSV removiendo acentos y convirtiendo a minúsculas
 * Permite comparación case-insensitive y accent-insensitive
 * 
 * @param string $header Header a normalizar
 * @return string Header normalizado (sin acentos, minúsculas)
 */
function normalizarHeaderCSV($header) {
    // Mapeo manual de caracteres acentuados en español
    $acentos = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
        'ñ' => 'n', 'Ñ' => 'n',
        'ü' => 'u', 'Ü' => 'u'
    ];
    
    // Reemplazar acentos
    $header = strtr($header, $acentos);
    
    // Convertir a minúsculas
    $header = strtolower(trim($header));
    
    // Remover espacios extra y reemplazar por guión bajo
    $header = preg_replace('/\s+/', '_', $header);
    
    return $header;
}

/**
 * Mapea headers CSV limpios en español a nombres de campos de base de datos
 * Soporta múltiples variantes (con/sin acentos, mayúsculas/minúsculas)
 * 
 * @param string $header_normalizado Header normalizado (sin acentos, minúsculas)
 * @return string|null Nombre del campo de base de datos o null si no se encuentra
 */
function mapearHeaderCSV($header_normalizado) {
    // Mapeo de headers limpios en español a campos de BD
    // Nota: Los headers ya están normalizados (sin acentos, minúsculas) por normalizarHeaderCSV()
    $mapeo = [
        // Nombre del producto
        'nombre' => 'nombre_producto',
        'nombre_producto' => 'nombre_producto',
        
        // Descripción
        'descripcion' => 'descripcion_producto',
        'descripcion_producto' => 'descripcion_producto',
        
        // Precio
        'precio' => 'precio_actual',
        'precio_actual' => 'precio_actual',
        
        // Categoría (ya normalizado sin acento)
        'categoria' => 'categoria',
        'id_categoria' => 'id_categoria',
        
        // Género (ya normalizado sin acento)
        'genero' => 'genero',
        
        // Talle
        'talle' => 'talle',
        
        // Color
        'color' => 'color',
        
        // Stock
        'stock' => 'stock',
        
        // Fotos (opcionales)
        'foto_miniatura' => 'foto_prod_miniatura',
        'foto_min' => 'foto_prod_miniatura',
        'foto_prod_miniatura' => 'foto_prod_miniatura',
        'foto1' => 'foto1_prod',
        'foto_1' => 'foto1_prod',
        'foto1_prod' => 'foto1_prod',
        'foto2' => 'foto2_prod',
        'foto_2' => 'foto2_prod',
        'foto2_prod' => 'foto2_prod',
        'foto3' => 'foto3_prod',
        'foto_3' => 'foto3_prod',
        'foto3_prod' => 'foto3_prod',
        'foto_grupal' => 'foto3_prod'
    ];
    
    return $mapeo[$header_normalizado] ?? null;
}

/**
 * Busca categorías similares a un nombre dado (para sugerencias de error)
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $nombre_buscado Nombre de categoría buscado
 * @return array Array de nombres de categorías similares
 */
function buscarCategoriasSimilares($mysqli, $nombre_buscado) {
    $categoria_queries_path = __DIR__ . '/queries/categoria_queries.php';
    if (!file_exists($categoria_queries_path)) {
        error_log("ERROR: No se pudo encontrar categoria_queries.php en " . $categoria_queries_path);
        die("Error crítico: Archivo de consultas de categoría no encontrado. Por favor, contacta al administrador.");
    }
    require_once $categoria_queries_path;
    $categorias = obtenerCategorias($mysqli);
    $similares = [];
    $nombre_buscado_lower = strtolower(trim($nombre_buscado));
    
    foreach ($categorias as $cat) {
        $nombre_cat_lower = strtolower(trim($cat['nombre_categoria']));
        // Buscar coincidencias parciales
        if (strpos($nombre_cat_lower, $nombre_buscado_lower) !== false || 
            strpos($nombre_buscado_lower, $nombre_cat_lower) !== false ||
            similar_text($nombre_cat_lower, $nombre_buscado_lower) > 60) {
            $similares[] = $cat['nombre_categoria'];
        }
    }
    
    return array_slice($similares, 0, 5); // Máximo 5 sugerencias
}

/**
 * Procesa archivo CSV con productos y valida todos los datos
 * Aplica principios Poka-yoke: validaciones exhaustivas, mensajes claros, prevención de errores
 * 
 * @param string $archivo_temporal Ruta temporal del archivo CSV
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array de productos validados y normalizados
 */
function procesarCSV($archivo_temporal, $mysqli) {
    // Cargar funciones auxiliares
    $categoria_queries_path = __DIR__ . '/queries/categoria_queries.php';
    if (!file_exists($categoria_queries_path)) {
        error_log("ERROR: No se pudo encontrar categoria_queries.php en " . $categoria_queries_path);
        die("Error crítico: Archivo de consultas de categoría no encontrado. Por favor, contacta al administrador.");
    }
    require_once $categoria_queries_path;
    
    $talles_config_path = __DIR__ . '/talles_config.php';
    if (!file_exists($talles_config_path)) {
        error_log("ERROR: No se pudo encontrar talles_config.php en " . $talles_config_path);
        die("Error crítico: Archivo de configuración de talles no encontrado. Por favor, contacta al administrador.");
    }
    require_once $talles_config_path;
    
    $marketing_functions_path = __DIR__ . '/marketing_functions.php';
    if (!file_exists($marketing_functions_path)) {
        error_log("ERROR: No se pudo encontrar marketing_functions.php en " . $marketing_functions_path);
        die("Error crítico: Archivo de funciones de marketing no encontrado. Por favor, contacta al administrador.");
    }
    require_once $marketing_functions_path; // Para usar funciones de validación
    
    $productos = [];
    $errores = [];
    $categorias_creadas = []; // Para tracking de categorías creadas automáticamente
    
    if (($handle = fopen($archivo_temporal, "r")) !== FALSE) {
        // Leer primera línea como headers
        $headers_raw = fgetcsv($handle, 1000, ",");
        
        if ($headers_raw === FALSE || empty($headers_raw)) {
            $errores[] = "El archivo CSV está vacío o no tiene formato válido";
            fclose($handle);
            $_SESSION['errores_csv'] = $errores;
            return [];
        }
        
        // Eliminar BOM del primer header si existe (compatibilidad con Excel)
        if (!empty($headers_raw) && isset($headers_raw[0])) {
            $headers_raw[0] = eliminarBOM($headers_raw[0]);
        }
        
        // Normalizar y mapear headers CSV limpios a campos de BD
        $headers_normalizados = [];
        $headers_mapeados = [];
        $headers_originales_para_error = [];
        
        foreach ($headers_raw as $index => $header_original) {
            $header_normalizado = normalizarHeaderCSV($header_original);
            $header_mapeado = mapearHeaderCSV($header_normalizado);
            
            if ($header_mapeado) {
                $headers_mapeados[$header_mapeado] = $index;
                $headers_originales_para_error[$header_mapeado] = $header_original;
            }
            $headers_normalizados[] = $header_normalizado;
        }
        
        // Headers requeridos en BD (después del mapeo)
        // Nota: descripcion_producto es opcional
        $headers_requeridos_bd = [
            'nombre_producto', 'precio_actual', 
            'genero', 'talle', 'color', 'stock'
        ];
        
        // Verificar que tenga "categoria" O "id_categoria" (pero no ambos)
        $tiene_categoria = isset($headers_mapeados['categoria']);
        $tiene_id_categoria = isset($headers_mapeados['id_categoria']);
        
        if (!$tiene_categoria && !$tiene_id_categoria) {
            $headers_faltantes = ['Categoría'];
            $headers_validos = false;
        } else {
            $headers_validos = true;
            $headers_faltantes = [];
        }
        
        // Validar headers requeridos
        foreach ($headers_requeridos_bd as $header_bd) {
            if (!isset($headers_mapeados[$header_bd])) {
                // Convertir nombre de BD a nombre limpio para mostrar en error
                $nombres_limpios = [
                    'nombre_producto' => 'Nombre',
                    'precio_actual' => 'Precio',
                    'genero' => 'Género',
                    'talle' => 'Talle',
                    'color' => 'Color',
                    'stock' => 'Stock'
                ];
                $headers_faltantes[] = $nombres_limpios[$header_bd] ?? $header_bd;
                $headers_validos = false;
            }
        }
        
        if (!$headers_validos) {
            $errores[] = "ERROR: Headers requeridos faltantes: " . implode(', ', $headers_faltantes);
            $errores[] = "Headers encontrados en tu CSV: " . implode(', ', $headers_raw);
            $errores[] = "SOLUCIÓN: Descarga la plantilla CSV desde el botón 'Descargar plantilla CSV' para ver el formato correcto";
            $errores[] = "Headers esperados (obligatorios): Nombre, Descripción, Precio, Categoría, Género, Talle, Color, Stock";
            $errores[] = "Headers opcionales: Foto Miniatura, Foto 1, Foto 2, Foto 3 (Grupal)";
            fclose($handle);
            $_SESSION['errores_csv'] = $errores;
            return [];
        }
        
        // Si llegamos aquí, los headers son válidos - limpiar errores previos de headers si existen
        // (pueden quedar de un intento anterior con headers incorrectos)
        if (isset($_SESSION['errores_csv'])) {
            // Solo limpiar errores de headers, mantener otros errores de validación de datos
            $_SESSION['errores_csv'] = array_filter($_SESSION['errores_csv'], function($error) {
                return strpos($error, 'ERROR: Headers requeridos faltantes') === false;
            });
            // Si quedó vacío, eliminar la variable de sesión
            if (empty($_SESSION['errores_csv'])) {
                unset($_SESSION['errores_csv']);
            }
        }
        
        // Obtener categorías disponibles para validación y sugerencias
        $categorias_disponibles = obtenerCategorias($mysqli);
        $nombres_categorias = array_column($categorias_disponibles, 'nombre_categoria');
        
        // Obtener talles estándar para validación
        $talles_disponibles = obtenerTallesEstandar();
        
        $linea = 1; // Contador de línea (la línea 1 son los headers)
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $linea++;
            
            // Saltar líneas vacías
            if (empty(array_filter($data))) {
                continue;
            }
            
            // Validar que haya suficientes columnas (mínimo requeridas)
            // Calcular el índice máximo necesario para campos requeridos
            $headers_requeridos_bd = [
                'nombre_producto', 'precio_actual', 
                'genero', 'talle', 'color', 'stock'
            ];
            // Agregar categoria o id_categoria (uno de los dos es requerido)
            if (isset($headers_mapeados['categoria'])) {
                $headers_requeridos_bd[] = 'categoria';
            } elseif (isset($headers_mapeados['id_categoria'])) {
                $headers_requeridos_bd[] = 'id_categoria';
            }
            
            $max_indice_requerido = -1;
            foreach ($headers_requeridos_bd as $header_req) {
                if (isset($headers_mapeados[$header_req])) {
                    $indice = $headers_mapeados[$header_req];
                    if ($indice > $max_indice_requerido) {
                        $max_indice_requerido = $indice;
                    }
                }
            }
            
            $min_columnas_requeridas = $max_indice_requerido + 1; // +1 porque los índices son 0-based
            if (count($data) < $min_columnas_requeridas) {
                $errores[] = "Línea $linea: Faltan columnas requeridas. Se esperan al menos " . $min_columnas_requeridas . " columnas, pero se encontraron " . count($data);
                continue;
            }
            
            // Rellenar con strings vacíos si faltan columnas opcionales (para mantener consistencia)
            if (count($data) < count($headers_raw)) {
                $data = array_pad($data, count($headers_raw), '');
            }
            
            // Crear array asociativo usando headers mapeados a campos de BD
            $producto = [];
            foreach ($headers_mapeados as $campo_bd => $indice_columna) {
                if (isset($data[$indice_columna])) {
                    $producto[$campo_bd] = $data[$indice_columna];
                }
            }
            
            // Asegurar que descripcion_producto existe (es opcional, puede estar vacío)
            if (!isset($producto['descripcion_producto'])) {
                $producto['descripcion_producto'] = '';
            }
            
            // Validar nombre de producto usando función de validación
            $nombre_producto_raw = trim($producto['nombre_producto'] ?? '');
            $validacion_nombre = validarNombreProducto($nombre_producto_raw);
            if (!$validacion_nombre['valido']) {
                $errores[] = "Línea $linea: " . $validacion_nombre['error'];
                continue;
            }
            $nombre_producto = $validacion_nombre['valor'];
            
            // Validar precio usando función de validación
            $precio_raw = trim($producto['precio_actual'] ?? '');
            $validacion_precio = validarPrecio($precio_raw);
            if (!$validacion_precio['valido']) {
                $errores[] = "Línea $linea: " . $validacion_precio['error'];
                continue;
            }
            $precio_float = $validacion_precio['valor'];
            
            // Validar género
            $genero = strtolower(trim($producto['genero'] ?? ''));
            $generos_validos = ['hombre', 'mujer', 'unisex'];
            if (!in_array($genero, $generos_validos)) {
                $errores[] = "Línea $linea: Género inválido '$genero'. Valores permitidos: " . implode(', ', $generos_validos);
                continue;
            }
            
            // Validar categoría (por nombre, no ID)
            $nombre_categoria_raw = trim($producto['categoria'] ?? '');
            // Si no tiene "categoria" pero tiene "id_categoria", intentar convertir
            if (empty($nombre_categoria_raw) && isset($producto['id_categoria'])) {
                $id_cat = intval($producto['id_categoria']);
                // Buscar nombre de categoría por ID
                foreach ($categorias_disponibles as $cat) {
                    if ($cat['id_categoria'] == $id_cat) {
                        $nombre_categoria_raw = $cat['nombre_categoria'];
                        break;
                    }
                }
                if (empty($nombre_categoria_raw)) {
                    $errores[] = "Línea $linea: ID de categoría '$id_cat' no existe en la base de datos. Usa el nombre de la categoría en lugar del ID.";
                    continue;
                }
            }

            // Validar categoría usando función de validación
            $validacion_categoria = validarCategoria($nombre_categoria_raw);
            if (!$validacion_categoria['valido']) {
                $errores[] = "Línea $linea: " . $validacion_categoria['error'];
                if (empty($nombre_categoria_raw)) {
                    $errores[] = "Categorías disponibles: " . implode(', ', $nombres_categorias);
                }
                continue;
            }
            $nombre_categoria = $validacion_categoria['valor'];
            
            // Buscar categoría por nombre (case-insensitive)
            $id_categoria = obtenerCategoriaIdPorNombre($mysqli, $nombre_categoria);
            
            if (!$id_categoria) {
                // Categoría no existe, crear automáticamente
                $stmt_crear = $mysqli->prepare("INSERT INTO Categorias (nombre_categoria, activo, fecha_creacion) VALUES (?, 1, NOW())");
                if ($stmt_crear) {
                    $stmt_crear->bind_param('s', $nombre_categoria);
                    if ($stmt_crear->execute()) {
                        $id_categoria = $mysqli->insert_id;
                        $categorias_creadas[] = $nombre_categoria;
                    } else {
                        $errores[] = "Línea $linea: Error al crear categoría '$nombre_categoria': " . $stmt_crear->error;
                        $stmt_crear->close();
                        continue;
                    }
                    $stmt_crear->close();
                } else {
                    $similares = buscarCategoriasSimilares($mysqli, $nombre_categoria);
                    $mensaje = "Línea $linea: Categoría '$nombre_categoria' no existe";
                    if (!empty($similares)) {
                        $mensaje .= ". ¿Quizás quisiste decir: " . implode(', ', $similares) . "?";
                    } else {
                        $mensaje .= ". Categorías disponibles: " . implode(', ', $nombres_categorias);
                    }
                    $errores[] = $mensaje;
                    continue;
                }
            }
            
            // Validar talle usando función de validación
            $talle_raw = trim($producto['talle'] ?? '');
            $validacion_talle = validarTalle($talle_raw);
            if (!$validacion_talle['valido']) {
                $errores[] = "Línea $linea: " . $validacion_talle['error'];
                continue;
            }
            $talle = $validacion_talle['valor'];
            
            // Validar contra talles estándar (advertencia, no error)
            if (!in_array($talle, $talles_disponibles)) {
                // No es error crítico, solo advertencia
                // El talle se acepta aunque no esté en la lista estándar
            }
            
            // Validar color usando función de validación
            $color_raw = trim($producto['color'] ?? '');
            $validacion_color = validarColor($color_raw);
            if (!$validacion_color['valido']) {
                $errores[] = "Línea $linea: " . $validacion_color['error'];
                continue;
            }
            $color = $validacion_color['valor'];
            $color_normalizado = ucfirst(strtolower($color)); // Normalizar color
            
            // Validar stock usando función de validación
            $stock_raw = trim($producto['stock'] ?? '0');
            $validacion_stock = validarStock($stock_raw);
            if (!$validacion_stock['valido']) {
                $errores[] = "Línea $linea: " . $validacion_stock['error'];
                continue;
            }
            $stock_int = $validacion_stock['valor'];
            
            // Validar descripción usando función de validación
            $descripcion_producto_raw = trim($producto['descripcion_producto'] ?? '');
            $validacion_descripcion = validarDescripcionProducto($descripcion_producto_raw);
            if (!$validacion_descripcion['valido']) {
                $errores[] = "Línea $linea: " . $validacion_descripcion['error'];
                continue;
            }
            $descripcion_producto = $validacion_descripcion['valor'];
            
            // Leer campos de fotos opcionales directamente del CSV (sin trim para preservar espacios)
            $foto_prod_miniatura = $producto['foto_prod_miniatura'] ?? '';
            $foto1_prod = $producto['foto1_prod'] ?? '';
            $foto2_prod = $producto['foto2_prod'] ?? '';
            $foto3_prod = $producto['foto3_prod'] ?? '';
            
            // Normalizar rutas de fotos: si tienen valor, agregar prefijo imagenes/ si no lo tienen
            if (!empty($foto_prod_miniatura) && strpos($foto_prod_miniatura, 'imagenes/') !== 0) {
                $foto_prod_miniatura = 'imagenes/' . $foto_prod_miniatura;
            }
            if (!empty($foto1_prod) && strpos($foto1_prod, 'imagenes/') !== 0) {
                $foto1_prod = 'imagenes/' . $foto1_prod;
            }
            if (!empty($foto2_prod) && strpos($foto2_prod, 'imagenes/') !== 0) {
                $foto2_prod = 'imagenes/' . $foto2_prod;
            }
            if (!empty($foto3_prod) && strpos($foto3_prod, 'imagenes/') !== 0) {
                $foto3_prod = 'imagenes/' . $foto3_prod;
            }
            
            // Asignar valores normalizados al array del producto
            $producto['nombre_producto'] = $nombre_producto;
            $producto['descripcion_producto'] = $descripcion_producto;
            $producto['precio_actual'] = $precio_float;
            $producto['id_categoria'] = $id_categoria;
            $producto['genero'] = $genero;
            $producto['talle'] = $talle;
            $producto['color'] = $color_normalizado;
            $producto['stock'] = $stock_int;
            $producto['foto_prod_miniatura'] = $foto_prod_miniatura;
            $producto['foto1_prod'] = $foto1_prod;
            $producto['foto2_prod'] = $foto2_prod;
            $producto['foto3_prod'] = $foto3_prod;
            $producto['linea'] = $linea;
            
            $productos[] = $producto;
        }
        
        fclose($handle);
        
        // Si se crearon categorías nuevas, informar al usuario
        if (!empty($categorias_creadas)) {
            $categorias_unicas = array_unique($categorias_creadas);
            $_SESSION['categorias_creadas_csv'] = $categorias_unicas;
        }
    }
    
    // Guardar errores en sesión si los hay
    if (!empty($errores)) {
        $_SESSION['errores_csv'] = $errores;
    } else {
        // Si no hay errores y se procesaron productos, limpiar errores previos
        if (!empty($productos)) {
            unset($_SESSION['errores_csv']);
        }
    }
    
    return $productos;
}

/**
 * Agrupa productos CSV por nombre, consolidando variantes
 * @param array $productos_csv Array de productos del CSV
 * @return array Array agrupado por nombre de producto con sus variantes
 */
function agruparProductosCSV($productos_csv) {
    $productos_agrupados = [];
    
    foreach ($productos_csv as $fila) {
        $nombre_producto = $fila['nombre_producto'];
        
        // Si el producto ya existe, buscar si la variante ya existe
        if (isset($productos_agrupados[$nombre_producto])) {
            $variante_existente_index = -1;
            $talle_fila = strtolower(trim($fila['talle']));
            $color_fila = strtolower(trim($fila['color']));
            
            // Buscar variante existente por talle y color
            foreach ($productos_agrupados[$nombre_producto]['variantes'] as $index => $variante) {
                if (strtolower(trim($variante['talle'])) === $talle_fila && 
                    strtolower(trim($variante['color'])) === $color_fila) {
                    $variante_existente_index = $index;
                    break;
                }
            }
            
            if ($variante_existente_index >= 0) {
                // Variante ya existe: fusionar datos (sumar stock)
                $stock_actual = $productos_agrupados[$nombre_producto]['variantes'][$variante_existente_index]['stock'];
                $productos_agrupados[$nombre_producto]['variantes'][$variante_existente_index]['stock'] = $stock_actual + $fila['stock'];
                
                // Completar fotos de variante si faltan
                if (empty($productos_agrupados[$nombre_producto]['variantes'][$variante_existente_index]['foto1_prod']) && !empty($fila['foto1_prod'])) {
                    $productos_agrupados[$nombre_producto]['variantes'][$variante_existente_index]['foto1_prod'] = $fila['foto1_prod'];
                }
                if (empty($productos_agrupados[$nombre_producto]['variantes'][$variante_existente_index]['foto2_prod']) && !empty($fila['foto2_prod'])) {
                    $productos_agrupados[$nombre_producto]['variantes'][$variante_existente_index]['foto2_prod'] = $fila['foto2_prod'];
                }
            } else {
                // Variante no existe, agregar nueva
                $variante = [
                    'talle' => $fila['talle'],
                    'color' => $fila['color'],
                    'stock' => $fila['stock'],
                    'foto1_prod' => !empty($fila['foto1_prod']) ? $fila['foto1_prod'] : '',
                    'foto2_prod' => !empty($fila['foto2_prod']) ? $fila['foto2_prod'] : ''
                ];
                $productos_agrupados[$nombre_producto]['variantes'][] = $variante;
            }
            
            // Actualizar fotos base (foto_min y foto3) solo si no existen y esta fila las tiene
            if (empty($productos_agrupados[$nombre_producto]['foto_prod_miniatura']) && !empty($fila['foto_prod_miniatura'])) {
                $productos_agrupados[$nombre_producto]['foto_prod_miniatura'] = $fila['foto_prod_miniatura'];
            }
            if (empty($productos_agrupados[$nombre_producto]['foto3_prod']) && !empty($fila['foto3_prod'])) {
                $productos_agrupados[$nombre_producto]['foto3_prod'] = $fila['foto3_prod'];
            }
        } else {
            // Crear nuevo producto con fotos base
            $productos_agrupados[$nombre_producto] = [
                'nombre_producto' => $fila['nombre_producto'],
                'descripcion_producto' => $fila['descripcion_producto'],
                'precio_actual' => $fila['precio_actual'],
                'id_categoria' => $fila['id_categoria'],
                'genero' => $fila['genero'],
                'foto_prod_miniatura' => !empty($fila['foto_prod_miniatura']) ? $fila['foto_prod_miniatura'] : '',
                'foto3_prod' => !empty($fila['foto3_prod']) ? $fila['foto3_prod'] : '',
                'variantes' => [
                    [
                        'talle' => $fila['talle'],
                        'color' => $fila['color'],
                        'stock' => $fila['stock'],
                        'foto1_prod' => !empty($fila['foto1_prod']) ? $fila['foto1_prod'] : '',
                        'foto2_prod' => !empty($fila['foto2_prod']) ? $fila['foto2_prod'] : ''
                    ]
                ]
            ];
        }
    }
    
    return $productos_agrupados;
}

/**
 * Obtiene productos existentes completos con sus datos y variantes
 * Retorna un array indexado por nombre de producto (case-insensitive) con:
 * - ID del producto
 * - Datos del producto (precio, descripción, categoría, género)
 * - Array de variantes existentes indexado por "talle-color"
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $nombres_productos Array de nombres de productos a buscar
 * @return array Array de productos existentes con estructura completa
 */
function obtenerProductosExistentesCompletos($mysqli, $nombres_productos) {
    $producto_queries_path = __DIR__ . '/queries/producto_queries.php';
    if (!file_exists($producto_queries_path)) {
        error_log("ERROR: No se pudo encontrar producto_queries.php en " . $producto_queries_path);
        die("Error crítico: Archivo de consultas de producto no encontrado. Por favor, contacta al administrador.");
    }
    require_once $producto_queries_path;
    
    $productos_existentes = [];
    
    foreach ($nombres_productos as $nombre_producto) {
        // Obtener ID del producto por nombre
        $id_producto = obtenerProductoIdPorNombre($mysqli, $nombre_producto);
        
        if ($id_producto) {
            // Obtener datos completos del producto
            $producto = obtenerProductoPorId($mysqli, $id_producto);
            
            if ($producto) {
                // Obtener todas las variantes del producto
                $variantes = obtenerTodasVariantesProducto($mysqli, $id_producto);
                
                // Indexar variantes por "talle-color" para fácil búsqueda
                $variantes_indexadas = [];
                foreach ($variantes as $variante) {
                    $clave = strtolower(trim($variante['talle'])) . '-' . strtolower(trim($variante['color']));
                    $variantes_indexadas[$clave] = [
                        'id_variante' => $variante['id_variante'],
                        'talle' => $variante['talle'],
                        'color' => $variante['color'],
                        'stock' => $variante['stock']
                    ];
                }
                
                // Usar nombre normalizado como clave (case-insensitive)
                $nombre_normalizado = strtolower(trim($nombre_producto));
                $productos_existentes[$nombre_normalizado] = [
                    'id_producto' => $id_producto,
                    'nombre_producto' => $producto['nombre_producto'],
                    'precio_actual' => floatval($producto['precio_actual']),
                    'descripcion_producto' => $producto['descripcion_producto'],
                    'id_categoria' => intval($producto['id_categoria']),
                    'genero' => $producto['genero'],
                    'variantes' => $variantes_indexadas
                ];
            }
        }
    }
    
    return $productos_existentes;
}

/**
 * Compara variantes del CSV con variantes existentes y clasifica las acciones
 * Retorna un array con variantes a actualizar y variantes nuevas a crear
 * 
 * @param array $variantes_csv Array de variantes del CSV
 * @param array $variantes_existentes Array de variantes existentes indexado por "talle-color"
 * @return array Array con 'actualizar' (variantes existentes) y 'crear' (variantes nuevas)
 */
function compararVariantesCSV($variantes_csv, $variantes_existentes) {
    $variantes_actualizar = [];
    $variantes_crear = [];
    
    foreach ($variantes_csv as $variante_csv) {
        $talle = strtolower(trim($variante_csv['talle']));
        $color = strtolower(trim($variante_csv['color']));
        $clave = $talle . '-' . $color;
        
        if (isset($variantes_existentes[$clave])) {
            // Variante existe, agregar a lista de actualización
            $variantes_actualizar[] = [
                'id_variante' => $variantes_existentes[$clave]['id_variante'],
                'talle' => $variante_csv['talle'],
                'color' => $variante_csv['color'],
                'stock_csv' => intval($variante_csv['stock']),
                'stock_actual' => intval($variantes_existentes[$clave]['stock'])
            ];
        } else {
            // Variante nueva, agregar a lista de creación
            $variantes_crear[] = [
                'talle' => $variante_csv['talle'],
                'color' => $variante_csv['color'],
                'stock' => intval($variante_csv['stock'])
            ];
        }
    }
    
    return [
        'actualizar' => $variantes_actualizar,
        'crear' => $variantes_crear
    ];
}

