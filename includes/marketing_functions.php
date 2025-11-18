<?php
/**
 * ========================================================================
 * FUNCIONES AUXILIARES DE MARKETING - Tienda Seda y Lino
 * ========================================================================
 * Funciones auxiliares para el panel de marketing
 * 
 * FUNCIONES:
 * - procesarCargaCSV(): Procesa carga masiva de productos desde archivo CSV
 * - procesarCreacionProducto(): Procesa creación de nuevo producto base
 * - Funciones de validación de campos
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Valida nombre de producto
 * Permite: letras, números, espacios, acentos, guiones
 * Bloquea: símbolos especiales (< > { } [ ] | \ / & $ % # @ ! ?)
 * 
 * NOTA: Existe versión JavaScript equivalente en marketing_forms.js
 * Ambas versiones deben mantener la misma lógica de validación.
 * 
 * @param string $valor Valor a validar
 * @return array ['valido' => bool, 'valor' => string, 'error' => string]
 */
function validarNombreProducto($valor) {
    $valor = trim($valor);
    
    if (empty($valor)) {
        return ['valido' => false, 'valor' => '', 'error' => 'El nombre del producto es obligatorio.'];
    }
    
    // Validar longitud mínima según diccionario: 3 caracteres
    if (strlen($valor) < 3) {
        return ['valido' => false, 'valor' => $valor, 'error' => 'El nombre del producto debe tener al menos 3 caracteres.'];
    }
    
    // Validar caracteres permitidos: letras, números, espacios, acentos, guiones
    if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ0-9\s\-]+$/', $valor)) {
        return ['valido' => false, 'valor' => $valor, 'error' => 'El nombre del producto contiene caracteres no permitidos. Solo se permiten letras, números, espacios y guiones.'];
    }
    
    // Validar longitud máxima (VARCHAR(100) en BD)
    if (strlen($valor) > 100) {
        return ['valido' => false, 'valor' => $valor, 'error' => 'El nombre del producto no puede exceder 100 caracteres.'];
    }
    
    return ['valido' => true, 'valor' => $valor, 'error' => ''];
}

/**
 * Valida descripción de producto
 * Permite: letras, números, espacios, acentos, guiones, puntos, comas, dos puntos, punto y coma
 * Bloquea: símbolos peligrosos (< > { } [ ] | \ / &)
 * 
 * @param string $valor Valor a validar
 * @return array ['valido' => bool, 'valor' => string, 'error' => string]
 */
function validarDescripcionProducto($valor) {
    $valor = trim($valor);
    
    // La descripción es opcional, si está vacía es válida
    if (empty($valor)) {
        return ['valido' => true, 'valor' => '', 'error' => ''];
    }
    
    // Validar caracteres permitidos: letras, números, espacios, acentos, guiones, puntos, comas, dos puntos, punto y coma
    // Bloquear símbolos peligrosos: < > { } [ ] | \ / &
    if (preg_match('/[<>{}\[\]|\\\\\/&]/', $valor)) {
        return ['valido' => false, 'valor' => $valor, 'error' => 'La descripción contiene caracteres no permitidos. No se permiten los símbolos: < > { } [ ] | \\ / &'];
    }
    
    // Validar longitud máxima (VARCHAR(255) en BD)
    if (strlen($valor) > 255) {
        return ['valido' => false, 'valor' => $valor, 'error' => 'La descripción no puede exceder 255 caracteres.'];
    }
    
    return ['valido' => true, 'valor' => $valor, 'error' => ''];
}

/**
 * Valida precio numérico puro
 * Permite: solo números y punto decimal
 * Bloquea: símbolos de moneda ($, €), comas como separadores, espacios, letras
 * 
 * NOTA: Existe versión JavaScript equivalente en marketing_forms.js
 * Ambas versiones deben mantener la misma lógica de validación.
 * 
 * @param string $valor Valor a validar
 * @return array ['valido' => bool, 'valor' => float, 'error' => string]
 */
function validarPrecio($valor) {
    $valor = trim($valor);
    
    if (empty($valor)) {
        return ['valido' => false, 'valor' => 0, 'error' => 'El precio es obligatorio.'];
    }
    
    // Validar que sea numérico puro (solo números y punto decimal)
    // Bloquear símbolos de moneda, comas, espacios, letras
    if (!preg_match('/^[0-9]+\.?[0-9]*$/', $valor)) {
        return ['valido' => false, 'valor' => 0, 'error' => 'El precio debe ser un número válido sin símbolos de moneda (ej: 15000.50)'];
    }
    
    $precio_float = floatval($valor);
    
    if ($precio_float <= 0) {
        return ['valido' => false, 'valor' => 0, 'error' => 'El precio debe ser mayor a cero.'];
    }
    
    return ['valido' => true, 'valor' => $precio_float, 'error' => ''];
}

/**
 * Valida stock entero puro
 * Permite: solo números enteros
 * Bloquea: símbolos, espacios, letras, decimales
 * 
 * @param string $valor Valor a validar
 * @return array ['valido' => bool, 'valor' => int, 'error' => string]
 */
function validarStock($valor) {
    $valor = trim($valor);
    
    // Stock puede ser 0, pero debe ser un número válido
    if ($valor === '') {
        return ['valido' => false, 'valor' => 0, 'error' => 'El stock es obligatorio.'];
    }
    
    // Validar que sea entero puro (solo números, sin decimales, sin símbolos)
    if (!preg_match('/^[0-9]+$/', $valor)) {
        return ['valido' => false, 'valor' => 0, 'error' => 'El stock debe ser un número entero sin símbolos (ej: 10)'];
    }
    
    $stock_int = intval($valor);
    
    if ($stock_int < 0) {
        return ['valido' => false, 'valor' => 0, 'error' => 'El stock no puede ser negativo.'];
    }
    
    return ['valido' => true, 'valor' => $stock_int, 'error' => ''];
}

/**
 * Valida talle
 * Permite: letras, números, guiones (ej: "XS", "S", "M", "L", "XL", "2XL")
 * 
 * @param string $valor Valor a validar
 * @return array ['valido' => bool, 'valor' => string, 'error' => string]
 */
function validarTalle($valor) {
    $valor = trim($valor);
    
    if (empty($valor)) {
        return ['valido' => false, 'valor' => '', 'error' => 'El talle es obligatorio.'];
    }
    
    // Validar caracteres permitidos: letras, números, guiones
    if (!preg_match('/^[a-zA-Z0-9\-]+$/', $valor)) {
        return ['valido' => false, 'valor' => $valor, 'error' => 'El talle contiene caracteres no permitidos. Solo se permiten letras, números y guiones.'];
    }
    
    // Validar longitud máxima (VARCHAR(50) en BD)
    if (strlen($valor) > 50) {
        return ['valido' => false, 'valor' => $valor, 'error' => 'El talle no puede exceder 50 caracteres.'];
    }
    
    return ['valido' => true, 'valor' => $valor, 'error' => ''];
}

/**
 * Valida color
 * Permite: solo letras según diccionario [A-Z, a-z]
 * 
 * @param string $valor Valor a validar
 * @return array ['valido' => bool, 'valor' => string, 'error' => string]
 */
function validarColor($valor) {
    $valor = trim($valor);
    
    if (empty($valor)) {
        return ['valido' => false, 'valor' => '', 'error' => 'El color es obligatorio.'];
    }
    
    // Validar longitud mínima según diccionario: 3 caracteres
    if (strlen($valor) < 3) {
        return ['valido' => false, 'valor' => $valor, 'error' => 'El color debe tener al menos 3 caracteres.'];
    }
    
    // Validar caracteres permitidos según diccionario: solo letras [A-Z, a-z]
    // Permite acentos para español (razonable aunque no explícito en diccionario)
    if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ]+$/', $valor)) {
        return ['valido' => false, 'valor' => $valor, 'error' => 'El color solo puede contener letras.'];
    }
    
    // Validar longitud máxima (VARCHAR(50) en BD)
    if (strlen($valor) > 50) {
        return ['valido' => false, 'valor' => $valor, 'error' => 'El color no puede exceder 50 caracteres.'];
    }
    
    return ['valido' => true, 'valor' => $valor, 'error' => ''];
}

/**
 * Valida SKU (código de producto)
 * Permite: letras, números, guiones y guiones bajos según diccionario [A-Z, a-z, 0-9, -,_]
 * 
 * @param string $valor Valor a validar
 * @return array ['valido' => bool, 'valor' => string, 'error' => string]
 */
function validarSku($valor) {
    $valor = trim($valor);
    
    if (empty($valor)) {
        return ['valido' => false, 'valor' => '', 'error' => 'El SKU es obligatorio.'];
    }
    
    // Validar longitud mínima según diccionario: 3 caracteres
    if (strlen($valor) < 3) {
        return ['valido' => false, 'valor' => $valor, 'error' => 'El SKU debe tener al menos 3 caracteres.'];
    }
    
    // Validar longitud máxima según diccionario: 50 caracteres
    if (strlen($valor) > 50) {
        return ['valido' => false, 'valor' => $valor, 'error' => 'El SKU no puede exceder 50 caracteres.'];
    }
    
    // Validar caracteres permitidos según diccionario: [A-Z, a-z, 0-9, -,_]
    if (!preg_match('/^[A-Za-z0-9\-_]+$/', $valor)) {
        return ['valido' => false, 'valor' => $valor, 'error' => 'El SKU solo puede contener letras, números, guiones y guiones bajos.'];
    }
    
    return ['valido' => true, 'valor' => $valor, 'error' => ''];
}

/**
 * Valida categoría
 * Permite: letras, números, espacios, acentos, guiones
 * Bloquea: símbolos especiales (< > { } [ ] | \ / & $ % # @ ! ?)
 * 
 * @param string $valor Valor a validar
 * @return array ['valido' => bool, 'valor' => string, 'error' => string]
 */
function validarCategoria($valor) {
    $valor = trim($valor);
    
    if (empty($valor)) {
        return ['valido' => false, 'valor' => '', 'error' => 'La categoría es obligatoria.'];
    }
    
    // Validar caracteres permitidos: letras, números, espacios, acentos, guiones
    if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ0-9\s\-]+$/', $valor)) {
        return ['valido' => false, 'valor' => $valor, 'error' => 'La categoría contiene caracteres no permitidos. Solo se permiten letras, números, espacios y guiones.'];
    }
    
    return ['valido' => true, 'valor' => $valor, 'error' => ''];
}

/**
 * Procesa la carga masiva de productos desde un archivo CSV
 * 
 * Esta función valida el archivo CSV, lo procesa y prepara los datos
 * para confirmación en marketing-confirmar-csv.php.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $files Array $_FILES con el archivo CSV
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string, 'redirect' => string] o false si no hay acción
 */
function procesarCargaCSV($mysqli, $files) {
    // Verificar que se está procesando la acción correcta
    if (!isset($_POST['procesar_csv'])) {
        return false;
    }
    
    // Verificar que se subió un archivo
    if (!isset($files['archivo_csv']) || $files['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
        return ['mensaje' => 'Error al subir el archivo CSV.', 'mensaje_tipo' => 'danger', 'redirect' => 'marketing.php?tab=csv'];
    }
    
    $archivo_temporal = $files['archivo_csv']['tmp_name'];
    $nombre_archivo = $files['archivo_csv']['name'];
    
    // Validar extensión del archivo
    $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
    if ($extension !== 'csv') {
        return ['mensaje' => 'El archivo debe ser un CSV válido.', 'mensaje_tipo' => 'danger', 'redirect' => 'marketing.php?tab=csv'];
    }
    
    // Cargar funciones de CSV
    $csv_functions_path = __DIR__ . '/csv_functions.php';
    if (!file_exists($csv_functions_path)) {
        error_log("ERROR: No se pudo encontrar csv_functions.php en " . $csv_functions_path);
        die("Error crítico: Archivo de funciones CSV no encontrado. Por favor, contacta al administrador.");
    }
    require_once $csv_functions_path;
    
    // Procesar CSV
    $productos_csv = procesarCSV($archivo_temporal, $mysqli);
    
    if (empty($productos_csv)) {
        // Verificar si hay errores de validación en la sesión
        if (!empty($_SESSION['errores_csv']) && is_array($_SESSION['errores_csv'])) {
            // Hay errores de validación, mostrar mensaje más específico
            $cantidad_errores = count($_SESSION['errores_csv']);
            $mensaje = "No se pudieron procesar productos del CSV. Se encontraron $cantidad_errores error(es) de validación. ";
            $mensaje .= "Revisa los errores mostrados en la página para corregir el archivo.";
            return ['mensaje' => $mensaje, 'mensaje_tipo' => 'danger', 'redirect' => 'marketing.php?tab=csv'];
        } else {
            // CSV vacío o sin productos válidos (sin errores específicos)
            return ['mensaje' => 'No se encontraron productos válidos en el CSV. Verifica que el archivo tenga el formato correcto y datos válidos.', 'mensaje_tipo' => 'warning', 'redirect' => 'marketing.php?tab=csv'];
        }
    }
    
    // Guardar productos en sesión para confirmación
    $_SESSION['productos_csv_pendientes'] = $productos_csv;
    $_SESSION['nombre_archivo_csv'] = $nombre_archivo;
    
    // Redirigir a página de confirmación
    return ['mensaje' => '', 'mensaje_tipo' => '', 'redirect' => 'marketing-confirmar-csv.php'];
}

/**
 * Procesa la creación de un nuevo producto base
 * 
 * ⚠️ IMPORTANTE: Esta función crea SOLO el producto BASE sin variantes.
 * Las variantes (talles y colores) deben agregarse posteriormente desde la página de edición.
 * 
 * ⚠️ ADVERTENCIA: Esta función NO debe crear variantes automáticamente.
 * Si se detectan variantes después de crear el producto, se considerará un error y se eliminarán.
 * 
 * Esta función valida los datos, maneja categorías (existentes o nuevas),
 * crea el producto y opcionalmente sube una imagen miniatura.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @param array $files Array $_FILES con las imágenes
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarCreacionProducto($mysqli, $post, $files) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['crear_producto'])) {
        return false;
    }
    
    // Cargar funciones necesarias
    $producto_queries_path = __DIR__ . '/queries/producto_queries.php';
    if (!file_exists($producto_queries_path)) {
        error_log("ERROR: No se pudo encontrar producto_queries.php en " . $producto_queries_path);
        die("Error crítico: Archivo de consultas de producto no encontrado. Por favor, contacta al administrador.");
    }
    require_once $producto_queries_path;
    
    $categoria_queries_path = __DIR__ . '/queries/categoria_queries.php';
    if (!file_exists($categoria_queries_path)) {
        error_log("ERROR: No se pudo encontrar categoria_queries.php en " . $categoria_queries_path);
        die("Error crítico: Archivo de consultas de categoría no encontrado. Por favor, contacta al administrador.");
    }
    require_once $categoria_queries_path;
    
    $product_image_functions_path = __DIR__ . '/product_image_functions.php';
    if (!file_exists($product_image_functions_path)) {
        error_log("ERROR: No se pudo encontrar product_image_functions.php en " . $product_image_functions_path);
        die("Error crítico: Archivo de funciones de imagen de producto no encontrado. Por favor, contacta al administrador.");
    }
    require_once $product_image_functions_path;
    
    $foto_producto_queries_path = __DIR__ . '/queries/foto_producto_queries.php';
    if (!file_exists($foto_producto_queries_path)) {
        error_log("ERROR: No se pudo encontrar foto_producto_queries.php en " . $foto_producto_queries_path);
        die("Error crítico: Archivo de consultas de foto de producto no encontrado. Por favor, contacta al administrador.");
    }
    require_once $foto_producto_queries_path;
    
    // Extraer datos del formulario
    $nombre_producto_input = trim($post['nombre_producto'] ?? '');
    $nombre_producto_nuevo = trim($post['nombre_producto_nuevo'] ?? '');
    $descripcion_producto_input = trim($post['descripcion_producto'] ?? '');
    $precio_actual_input = trim($post['precio_actual'] ?? '');
    $id_categoria_input = trim($post['id_categoria'] ?? '');
    $genero = $post['genero'] ?? '';
    
    $generos_validos = ['hombre', 'mujer', 'unisex'];
    
    // Validar precio usando función de validación
    $validacion_precio = validarPrecio($precio_actual_input);
    if (!$validacion_precio['valido']) {
        return ['mensaje' => $validacion_precio['error'], 'mensaje_tipo' => 'danger'];
    }
    $precio_actual = $validacion_precio['valor'];
    
    // Validar descripción usando función de validación
    $validacion_descripcion = validarDescripcionProducto($descripcion_producto_input);
    if (!$validacion_descripcion['valido']) {
        return ['mensaje' => $validacion_descripcion['error'], 'mensaje_tipo' => 'danger'];
    }
    $descripcion_producto = $validacion_descripcion['valor'];
    
    // Manejar nombre del producto: puede ser existente o nuevo
    $nombre_producto = '';
    $nombre_producto_valido = false;
    
    if ($nombre_producto_input === '__NUEVO__') {
        // Validar nombre nuevo usando función de validación
        $validacion_nombre = validarNombreProducto($nombre_producto_nuevo);
        if (!$validacion_nombre['valido']) {
            return ['mensaje' => $validacion_nombre['error'], 'mensaje_tipo' => 'danger'];
        }
        
        $nombre_producto = $validacion_nombre['valor'];
        
        // Validar que el nombre no sea inválido
        if ($nombre_producto === '__NUEVO__' || $nombre_producto === '') {
            return ['mensaje' => 'El nombre del producto no puede ser "__NUEVO__" o estar vacío', 'mensaje_tipo' => 'danger'];
        }
        
        // Verificar que el nombre nuevo no exista ya
        $producto_existente = obtenerProductoIdPorNombre($mysqli, $nombre_producto);
        if ($producto_existente) {
            return ['mensaje' => 'Ya existe un producto con el nombre "' . htmlspecialchars($nombre_producto) . '". El nombre debe ser único.', 'mensaje_tipo' => 'danger'];
        }
        
        $nombre_producto_valido = true;
    } else {
        $nombre_producto = trim($nombre_producto_input);
        
        // Validar que el nombre no sea inválido
        if ($nombre_producto === '__NUEVO__' || $nombre_producto === '') {
            return ['mensaje' => 'Selecciona un producto existente o ingresa un nombre nuevo válido', 'mensaje_tipo' => 'danger'];
        }
        
        // Validar nombre existente usando función de validación
        $validacion_nombre = validarNombreProducto($nombre_producto);
        if (!$validacion_nombre['valido']) {
            return ['mensaje' => $validacion_nombre['error'], 'mensaje_tipo' => 'danger'];
        }
        
        $nombre_producto = $validacion_nombre['valor'];
        $nombre_producto_valido = true;
    }
    
    // Manejar categoría: puede ser ID numérico o nombre nuevo
    $id_categoria = 0;
    if (is_numeric($id_categoria_input)) {
        // Es un ID existente
        $id_categoria = intval($id_categoria_input);
    } else {
        // Es un nombre nuevo de categoría
        $nombre_categoria_nueva = trim($id_categoria_input);
        if ($nombre_categoria_nueva !== '') {
            // Validar categoría usando función de validación
            $validacion_categoria = validarCategoria($nombre_categoria_nueva);
            if (!$validacion_categoria['valido']) {
                return ['mensaje' => $validacion_categoria['error'], 'mensaje_tipo' => 'danger'];
            }
            
            $nombre_categoria_nueva = $validacion_categoria['valor'];
            
            // Verificar si ya existe
            $id_categoria = obtenerCategoriaIdPorNombre($mysqli, $nombre_categoria_nueva);
            if (!$id_categoria) {
                // Crear nueva categoría usando función centralizada
                $id_categoria = crearCategoria($mysqli, $nombre_categoria_nueva);
            }
        }
    }
    
    // Validar datos básicos
    if (!$nombre_producto_valido || $nombre_producto === '' || $precio_actual <= 0 || $id_categoria <= 0 || !in_array($genero, $generos_validos, true)) {
        return ['mensaje' => 'Completa todos los campos correctamente', 'mensaje_tipo' => 'danger'];
    }
    
    // Iniciar transacción
    $mysqli->begin_transaction();
    
    try {
        // ⚠️ IMPORTANTE: Usar función centralizada crearProducto() que SOLO crea el producto base
        // Esta función NO debe crear variantes automáticamente
        // Valida que la categoría existe y está activa antes de crear el producto
        $id_producto_nuevo = crearProducto($mysqli, $nombre_producto, $descripcion_producto, $precio_actual, $id_categoria, $genero, null);
        
        if ($id_producto_nuevo <= 0) {
            throw new Exception('Error al crear el producto. Verifica que la categoría esté activa.');
        }
        
        // ⚠️ VERIFICACIÓN CRÍTICA: Verificar que NO se hayan creado variantes por error
        // El producto debe crearse SIN variantes. Si se encuentran variantes, es un error.
        $sql_verificar_variantes = "SELECT COUNT(*) as total FROM Stock_Variantes WHERE id_producto = ?";
        $stmt_verificar = $mysqli->prepare($sql_verificar_variantes);
        if ($stmt_verificar) {
            $stmt_verificar->bind_param('i', $id_producto_nuevo);
            $stmt_verificar->execute();
            $result_verificar = $stmt_verificar->get_result();
            $row_verificar = $result_verificar->fetch_assoc();
            $stmt_verificar->close();
            
            $total_variantes = intval($row_verificar['total'] ?? 0);
            
            if ($total_variantes > 0) {
                // ERROR CRÍTICO: Se crearon variantes cuando no deberían existir
                // Eliminar las variantes creadas por error y registrar el incidente
                error_log("ERROR CRÍTICO: Se crearon {$total_variantes} variantes no esperadas para producto ID {$id_producto_nuevo} al crear producto base. Eliminando variantes...");
                
                // Eliminar todas las variantes creadas por error
                $sql_eliminar_variantes = "DELETE FROM Stock_Variantes WHERE id_producto = ?";
                $stmt_eliminar = $mysqli->prepare($sql_eliminar_variantes);
                if ($stmt_eliminar) {
                    $stmt_eliminar->bind_param('i', $id_producto_nuevo);
                    $stmt_eliminar->execute();
                    $stmt_eliminar->close();
                    error_log("Variantes eliminadas correctamente para producto ID {$id_producto_nuevo}");
                }
                
                // Continuar con la creación del producto (las variantes ya fueron eliminadas)
                // No lanzar excepción para no interrumpir el flujo, pero registrar el error
            }
        }
        
        // Procesar imagen miniatura base si se subió (opcional)
        // ⚠️ NOTA: Esto NO crea variantes, solo inserta una foto del producto base
        if (isset($files['imagen_miniatura']) && $files['imagen_miniatura']['error'] === UPLOAD_ERR_OK) {
            $foto_miniatura = subirImagenIndividual($id_producto_nuevo, $files['imagen_miniatura'], 0);
            // Usar función centralizada para insertar foto (NO crea variantes)
            insertarFotoProducto($mysqli, $id_producto_nuevo, $foto_miniatura, null, null, null, null);
        }
        
        // ⚠️ VERIFICACIÓN FINAL: Confirmar que el producto NO tiene variantes antes de commit
        $sql_verificar_final = "SELECT COUNT(*) as total FROM Stock_Variantes WHERE id_producto = ?";
        $stmt_verificar_final = $mysqli->prepare($sql_verificar_final);
        if ($stmt_verificar_final) {
            $stmt_verificar_final->bind_param('i', $id_producto_nuevo);
            $stmt_verificar_final->execute();
            $result_verificar_final = $stmt_verificar_final->get_result();
            $row_verificar_final = $result_verificar_final->fetch_assoc();
            $stmt_verificar_final->close();
            
            $total_variantes_final = intval($row_verificar_final['total'] ?? 0);
            
            if ($total_variantes_final > 0) {
                // Si aún hay variantes después de intentar eliminarlas, es un error crítico
                error_log("ERROR CRÍTICO: No se pudieron eliminar todas las variantes para producto ID {$id_producto_nuevo}. Total restante: {$total_variantes_final}");
                throw new Exception('Error crítico: Se detectaron variantes no esperadas al crear el producto. Por favor, contacta al administrador.');
            }
        }
        
        $mysqli->commit();
        return ['mensaje' => 'Producto BASE creado exitosamente. Ahora puedes agregar colores y talles desde la edición.', 'mensaje_tipo' => 'success'];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        return ['mensaje' => 'Error: ' . $e->getMessage(), 'mensaje_tipo' => 'danger'];
    }
}

