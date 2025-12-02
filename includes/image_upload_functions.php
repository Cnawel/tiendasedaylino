<?php
/**
 * ========================================================================
 * FUNCIONES DE SUBIDA MASIVA DE IMÁGENES - Tienda Seda y Lino
 * ========================================================================
 * Funciones para subir y gestionar imágenes masivas desde formulario web
 * 
 * FUNCIONES:
 * - subirImagenesMasivas(): Procesa múltiples archivos simultáneos
 * - organizarImagenTemporal(): Guarda imagen en carpeta temporal con nombre único
 * - asociarImagenesConProductos(): Asocia imágenes temporales con productos del CSV
 * - limpiarImagenesTemporales(): Limpia imágenes no asociadas después de X días
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

require_once __DIR__ . '/product_image_functions.php';

/**
 * Procesa múltiples archivos de imagen y los guarda en carpeta temporal
 * 
 * @param array $archivos Array $_FILES con múltiples archivos
 * @return array Array con ['exitosas' => [...], 'errores' => [...]]
 */
function subirImagenesMasivas($archivos) {
    $resultado = [
        'exitosas' => [],
        'errores' => []
    ];
    
    // Validar que se hayan subido archivos
    if (!isset($archivos['imagenes']) || !is_array($archivos['imagenes']['name'])) {
        $resultado['errores'][] = 'No se seleccionaron archivos';
        return $resultado;
    }
    
    $cantidad_archivos = count($archivos['imagenes']['name']);
    
    // Validar límite de archivos (máximo 50 por lote)
    if ($cantidad_archivos > 50) {
        $resultado['errores'][] = 'Máximo 50 imágenes por lote. Has seleccionado ' . $cantidad_archivos;
        return $resultado;
    }
    
    // Crear carpeta temporal con timestamp
    $directorio_raiz = dirname(__DIR__);
    $timestamp = date('Ymd_His');
    $directorio_temporal = $directorio_raiz . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'temp_imagenes' . DIRECTORY_SEPARATOR . $timestamp;
    
    // Crear directorio si no existe
    if (!file_exists($directorio_temporal)) {
        if (!mkdir($directorio_temporal, 0755, true)) {
            $resultado['errores'][] = 'Error al crear directorio temporal';
            return $resultado;
        }
    }
    
    // Procesar cada archivo
    for ($i = 0; $i < $cantidad_archivos; $i++) {
        // Verificar que el archivo se subió correctamente
        if ($archivos['imagenes']['error'][$i] !== UPLOAD_ERR_OK) {
            $nombre_archivo = $archivos['imagenes']['name'][$i] ?? 'archivo_desconocido';
            $resultado['errores'][] = "Error al subir '$nombre_archivo': " . obtenerMensajeErrorUpload($archivos['imagenes']['error'][$i]);
            continue;
        }
        
        // Crear array de archivo individual para validación
        $archivo_individual = [
            'name' => $archivos['imagenes']['name'][$i],
            'type' => $archivos['imagenes']['type'][$i],
            'tmp_name' => $archivos['imagenes']['tmp_name'][$i],
            'error' => $archivos['imagenes']['error'][$i],
            'size' => $archivos['imagenes']['size'][$i]
        ];
        
        try {
            // Validar imagen usando función existente
            validarImagen($archivo_individual);
            
            // Organizar y guardar imagen temporal
            $imagen_guardada = organizarImagenTemporal($archivo_individual, $directorio_temporal);
            
            if ($imagen_guardada) {
                $resultado['exitosas'][] = $imagen_guardada;
            } else {
                $resultado['errores'][] = "Error al guardar '{$archivos['imagenes']['name'][$i]}'";
            }
            
        } catch (Exception $e) {
            $resultado['errores'][] = "Error en '{$archivos['imagenes']['name'][$i]}': " . $e->getMessage();
        }
    }
    
    return $resultado;
}

/**
 * Guarda una imagen en carpeta temporal con nombre único
 * 
 * @param array $archivo Array $_FILES del archivo individual
 * @param string $directorio_temporal Directorio temporal donde guardar
 * @return array|false Array con ['nombre_original', 'nombre_guardado', 'ruta_relativa', 'ruta_absoluta'] o false si falla
 */
function organizarImagenTemporal($archivo, $directorio_temporal) {
    $nombre_original = $archivo['name'];
    $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    
    // Validar extensión
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $extensiones_permitidas)) {
        throw new Exception('Formato no válido: ' . $extension . '. Formatos permitidos: ' . implode(', ', $extensiones_permitidas));
    }
    
    // Generar nombre único (usar nombre original si es único, sino agregar sufijo)
    $nombre_base = pathinfo($nombre_original, PATHINFO_FILENAME);
    $nombre_base = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombre_base); // Sanitizar nombre
    $nombre_guardado = $nombre_base . '.' . $extension;
    $ruta_absoluta = $directorio_temporal . DIRECTORY_SEPARATOR . $nombre_guardado;
    
    // Si el archivo ya existe, agregar sufijo numérico
    $contador = 1;
    while (file_exists($ruta_absoluta)) {
        $nombre_guardado = $nombre_base . '_' . $contador . '.' . $extension;
        $ruta_absoluta = $directorio_temporal . DIRECTORY_SEPARATOR . $nombre_guardado;
        $contador++;
    }
    
    // Mover archivo subido
    if (!is_uploaded_file($archivo['tmp_name'])) {
        throw new Exception('El archivo no fue subido correctamente mediante POST');
    }
    
    if (move_uploaded_file($archivo['tmp_name'], $ruta_absoluta)) {
        // Verificar que el archivo se guardó correctamente
        if (!file_exists($ruta_absoluta)) {
            throw new Exception('El archivo no se guardó correctamente');
        }
        
        // Construir ruta relativa (para guardar en sesión)
        $directorio_raiz = dirname(__DIR__);
        $ruta_relativa = 'uploads/temp_imagenes/' . basename($directorio_temporal) . '/' . $nombre_guardado;
        
        return [
            'nombre_original' => $nombre_original,
            'nombre_guardado' => $nombre_guardado,
            'ruta_relativa' => $ruta_relativa,
            'ruta_absoluta' => $ruta_absoluta,
            'timestamp' => basename($directorio_temporal)
        ];
    }
    
    return false;
}

/**
 * Busca una imagen temporal por nombre con algoritmo mejorado
 * Prioriza coincidencia exacta, luego parcial, ordenando por fecha más reciente
 * 
 * @param string $nombre_imagen Nombre de la imagen a buscar
 * @return array|false Array con ['ruta_absoluta', 'nombre_archivo', 'timestamp', 'coincidencia_tipo'] o false si no se encuentra
 */
function buscarImagenTemporal($nombre_imagen) {
    if (empty($nombre_imagen)) {
        return false;
    }
    
    $directorio_raiz = dirname(__DIR__);
    $directorio_temp_base = $directorio_raiz . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'temp_imagenes';
    
    // Si no existe el directorio temporal, no hay imágenes para buscar
    if (!is_dir($directorio_temp_base)) {
        return false;
    }
    
    // Obtener todas las carpetas temporales y ordenarlas por fecha (más recientes primero)
    $carpetas_temporales = glob($directorio_temp_base . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
    usort($carpetas_temporales, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    // Normalizar nombre de búsqueda
    $nombre_busqueda = strtolower(trim($nombre_imagen));
    $nombre_busqueda_limpio = preg_replace('/[^a-zA-Z0-9._-]/', '', $nombre_busqueda);
    $nombre_busqueda_sin_ext = pathinfo($nombre_busqueda_limpio, PATHINFO_FILENAME);
    
    // Extensiones permitidas para validar
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    // Coincidencias encontradas (para manejar múltiples)
    $coincidencias_exactas = [];
    $coincidencias_parciales = [];
    
    foreach ($carpetas_temporales as $carpeta_temp) {
        $archivos = glob($carpeta_temp . DIRECTORY_SEPARATOR . '*');
        
        foreach ($archivos as $archivo_temp) {
            if (!is_file($archivo_temp)) {
                continue;
            }
            
            $nombre_archivo = basename($archivo_temp);
            $nombre_archivo_normalizado = strtolower($nombre_archivo);
            $nombre_archivo_sin_ext = pathinfo($nombre_archivo_normalizado, PATHINFO_FILENAME);
            $extension_archivo = strtolower(pathinfo($nombre_archivo_normalizado, PATHINFO_EXTENSION));
            
            // Validar que sea una imagen válida
            if (!in_array($extension_archivo, $extensiones_permitidas)) {
                continue;
            }
            
            // Validar que el archivo sea realmente una imagen usando getimagesize
            $info_imagen = @getimagesize($archivo_temp);
            if ($info_imagen === false) {
                continue; // No es una imagen válida
            }
            
            // Prioridad 1: Coincidencia exacta (nombre completo)
            if ($nombre_archivo_normalizado === $nombre_busqueda_limpio) {
                $coincidencias_exactas[] = [
                    'ruta_absoluta' => $archivo_temp,
                    'nombre_archivo' => $nombre_archivo,
                    'timestamp' => basename($carpeta_temp),
                    'coincidencia_tipo' => 'exacta',
                    'fecha' => filemtime($carpeta_temp)
                ];
            }
            // Prioridad 2: Coincidencia exacta sin extensión
            elseif ($nombre_archivo_sin_ext === $nombre_busqueda_sin_ext) {
                $coincidencias_exactas[] = [
                    'ruta_absoluta' => $archivo_temp,
                    'nombre_archivo' => $nombre_archivo,
                    'timestamp' => basename($carpeta_temp),
                    'coincidencia_tipo' => 'exacta_sin_ext',
                    'fecha' => filemtime($carpeta_temp)
                ];
            }
            // Prioridad 3: Coincidencia parcial (el nombre buscado está contenido en el archivo)
            elseif (strpos($nombre_archivo_normalizado, $nombre_busqueda_limpio) !== false) {
                $coincidencias_parciales[] = [
                    'ruta_absoluta' => $archivo_temp,
                    'nombre_archivo' => $nombre_archivo,
                    'timestamp' => basename($carpeta_temp),
                    'coincidencia_tipo' => 'parcial',
                    'fecha' => filemtime($carpeta_temp)
                ];
            }
            // Prioridad 4: Coincidencia inversa (el nombre del archivo está contenido en la búsqueda)
            elseif (strpos($nombre_busqueda_limpio, $nombre_archivo_sin_ext) !== false) {
                $coincidencias_parciales[] = [
                    'ruta_absoluta' => $archivo_temp,
                    'nombre_archivo' => $nombre_archivo,
                    'timestamp' => basename($carpeta_temp),
                    'coincidencia_tipo' => 'parcial_inversa',
                    'fecha' => filemtime($carpeta_temp)
                ];
            }
        }
    }
    
    // Si hay coincidencias exactas, retornar la más reciente
    if (!empty($coincidencias_exactas)) {
        // Ordenar por fecha (más reciente primero)
        usort($coincidencias_exactas, function($a, $b) {
            return $b['fecha'] - $a['fecha'];
        });
        return $coincidencias_exactas[0];
    }
    
    // Si hay coincidencias parciales, retornar la más reciente
    if (!empty($coincidencias_parciales)) {
        // Ordenar por fecha (más reciente primero)
        usort($coincidencias_parciales, function($a, $b) {
            return $b['fecha'] - $a['fecha'];
        });
        return $coincidencias_parciales[0];
    }
    
    return false;
}

/**
 * Busca y asocia imágenes temporales con productos del CSV
 * Versión mejorada con búsqueda priorizada y validación de tipos
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_producto ID del producto
 * @param string $nombre_imagen Nombre de la imagen a buscar (puede ser nombre completo o parcial)
 * @param string $tipo_imagen Tipo: 'miniatura', 'foto1', 'foto2', 'foto3'
 * @return string|false Ruta relativa de la imagen movida o false si no se encuentra
 */
function asociarImagenesConProductos($mysqli, $id_producto, $nombre_imagen, $tipo_imagen = 'miniatura') {
    if (empty($nombre_imagen)) {
        return false;
    }
    
    // Buscar imagen usando función mejorada
    $imagen_encontrada = buscarImagenTemporal($nombre_imagen);
    
    if (!$imagen_encontrada) {
        return false;
    }
    
    $archivo_temp = $imagen_encontrada['ruta_absoluta'];
    $nombre_archivo = $imagen_encontrada['nombre_archivo'];
    
    // Validar que el archivo existe y es válido
    if (!file_exists($archivo_temp) || !is_file($archivo_temp)) {
        return false;
    }
    
    // Validar que sea una imagen válida usando getimagesize
    $info_imagen = @getimagesize($archivo_temp);
    if ($info_imagen === false) {
        error_log("Archivo '$nombre_archivo' no es una imagen válida");
        return false;
    }
    
    try {
        // Obtener directorio raíz y crear directorio del producto
        $directorio_raiz = dirname(__DIR__);
        $directorio_producto_relativo = 'imagenes/productos/producto_' . $id_producto . '/';
        $directorio_producto_absoluto = $directorio_raiz . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directorio_producto_relativo);
        
        // Crear directorio del producto si no existe
        if (!file_exists($directorio_producto_absoluto)) {
            if (!mkdir($directorio_producto_absoluto, 0755, true)) {
                throw new Exception('Error al crear directorio del producto');
            }
        }
        
        // Determinar nombre de archivo según tipo
        $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
        $tipo_norm = in_array($tipo_imagen, ['miniatura','grupal']) ? $tipo_imagen : 'generica';
        $nombre_final = 'imagen_generica_' . $tipo_norm . '_' . time() . '_' . $id_producto . '.' . $extension;
        
        $ruta_absoluta_final = $directorio_producto_absoluto . $nombre_final;
        $ruta_relativa_final = $directorio_producto_relativo . $nombre_final;
        
        // Copiar archivo temporal a ubicación final
        if (copy($archivo_temp, $ruta_absoluta_final)) {
            // Verificar que se copió correctamente
            if (!file_exists($ruta_absoluta_final)) {
                throw new Exception('El archivo no se copió correctamente');
            }
            
            // Verificar que el archivo copiado es una imagen válida
            $info_imagen_final = @getimagesize($ruta_absoluta_final);
            if ($info_imagen_final === false) {
                @unlink($ruta_absoluta_final); // Eliminar archivo inválido
                throw new Exception('El archivo copiado no es una imagen válida');
            }
            
            // Eliminar archivo temporal después de copiar exitosamente
            @unlink($archivo_temp);
            
            return $ruta_relativa_final;
        } else {
            throw new Exception('Error al copiar el archivo');
        }
        
    } catch (Exception $e) {
        error_log("Error al asociar imagen '$nombre_imagen' con producto $id_producto: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene lista de nombres de imágenes temporales disponibles para sugerencias
 * 
 * @return array Array de nombres de imágenes disponibles
 */
function obtenerNombresImagenesTemporales() {
    $nombres = [];
    $directorio_raiz = dirname(__DIR__);
    $directorio_temp_base = $directorio_raiz . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'temp_imagenes';
    
    if (!is_dir($directorio_temp_base)) {
        return $nombres;
    }
    
    $carpetas_temporales = glob($directorio_temp_base . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
    
    foreach ($carpetas_temporales as $carpeta_temp) {
        $archivos = glob($carpeta_temp . DIRECTORY_SEPARATOR . '*');
        foreach ($archivos as $archivo) {
            if (is_file($archivo)) {
                $nombre = basename($archivo);
                $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
                $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                // Solo incluir si es una imagen válida
                if (in_array($extension, $extensiones_permitidas)) {
                    $info_imagen = @getimagesize($archivo);
                    if ($info_imagen !== false) {
                        $nombres[] = $nombre;
                    }
                }
            }
        }
    }
    
    return array_unique($nombres);
}

/**
 * Busca nombres de imágenes similares a un nombre dado (para sugerencias)
 * 
 * @param string $nombre_buscado Nombre de imagen buscado
 * @param array $nombres_disponibles Array de nombres de imágenes disponibles
 * @param int $limite Máximo de sugerencias a retornar
 * @return array Array de nombres similares
 */
function buscarImagenesSimilares($nombre_buscado, $nombres_disponibles, $limite = 5) {
    if (empty($nombre_buscado) || empty($nombres_disponibles)) {
        return [];
    }
    
    $nombre_buscado_normalizado = strtolower(trim($nombre_buscado));
    $nombre_buscado_sin_ext = pathinfo($nombre_buscado_normalizado, PATHINFO_FILENAME);
    
    $similares = [];
    
    foreach ($nombres_disponibles as $nombre_disponible) {
        $nombre_disponible_normalizado = strtolower($nombre_disponible);
        $nombre_disponible_sin_ext = pathinfo($nombre_disponible_normalizado, PATHINFO_FILENAME);
        
        // Calcular similitud
        $similitud = 0;
        
        // Coincidencia exacta (sin extensión)
        if ($nombre_buscado_sin_ext === $nombre_disponible_sin_ext) {
            $similitud = 100;
        }
        // Coincidencia parcial (el nombre buscado está en el disponible)
        elseif (strpos($nombre_disponible_sin_ext, $nombre_buscado_sin_ext) !== false) {
            $similitud = 80;
        }
        // Coincidencia inversa (el nombre disponible está en el buscado)
        elseif (strpos($nombre_buscado_sin_ext, $nombre_disponible_sin_ext) !== false) {
            $similitud = 70;
        }
        // Usar similar_text para calcular similitud
        else {
            similar_text($nombre_buscado_sin_ext, $nombre_disponible_sin_ext, $similitud);
        }
        
        // Solo incluir si la similitud es mayor a 50%
        if ($similitud > 50) {
            $similares[] = [
                'nombre' => $nombre_disponible,
                'similitud' => $similitud
            ];
        }
    }
    
    // Ordenar por similitud (mayor primero)
    usort($similares, function($a, $b) {
        return $b['similitud'] - $a['similitud'];
    });
    
    // Retornar solo los nombres (sin similitud) limitados
    $resultado = [];
    foreach (array_slice($similares, 0, $limite) as $similar) {
        $resultado[] = $similar['nombre'];
    }
    
    return $resultado;
}

/**
 * Limpia imágenes temporales no asociadas después de X días
 * 
 * @param int $dias Días de antigüedad para eliminar (default: 7)
 * @return array Array con ['eliminadas' => int, 'errores' => array]
 */
function limpiarImagenesTemporales($dias = 7) {
    $resultado = [
        'eliminadas' => 0,
        'errores' => []
    ];
    
    $directorio_raiz = dirname(__DIR__);
    $directorio_temp_base = $directorio_raiz . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'temp_imagenes';
    
    if (!is_dir($directorio_temp_base)) {
        return $resultado;
    }
    
    $tiempo_limite = time() - ($dias * 24 * 60 * 60);
    
    // Obtener todas las carpetas temporales
    $carpetas_temporales = glob($directorio_temp_base . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
    
    foreach ($carpetas_temporales as $carpeta_temp) {
        // Verificar fecha de la carpeta (extraer timestamp del nombre)
        $nombre_carpeta = basename($carpeta_temp);
        $fecha_carpeta = filemtime($carpeta_temp);
        
        // Si la carpeta es más antigua que el límite, eliminar
        if ($fecha_carpeta < $tiempo_limite) {
            // Eliminar todos los archivos de la carpeta
            $archivos = glob($carpeta_temp . DIRECTORY_SEPARATOR . '*');
            foreach ($archivos as $archivo) {
                if (is_file($archivo)) {
                    if (@unlink($archivo)) {
                        $resultado['eliminadas']++;
                    } else {
                        $resultado['errores'][] = "Error al eliminar: $archivo";
                    }
                }
            }
            
            // Eliminar carpeta vacía
            @rmdir($carpeta_temp);
        }
    }
    
    return $resultado;
}

/**
 * Obtiene mensaje de error de upload en español
 * 
 * @param int $codigo_error Código de error de UPLOAD_ERR_*
 * @return string Mensaje de error en español
 */
function obtenerMensajeErrorUpload($codigo_error) {
    $mensajes = [
        UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por PHP',
        UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido por el formulario',
        UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
        UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
        UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
        UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida del archivo'
    ];
    
    return $mensajes[$codigo_error] ?? 'Error desconocido al subir el archivo';
}

/**
 * Elimina una imagen temporal específica
 * Valida que la ruta esté dentro del directorio temporal para seguridad
 * 
 * @param string $ruta_absoluta Ruta absoluta de la imagen a eliminar
 * @return array Array con ['success' => bool, 'mensaje' => string]
 */
function eliminarImagenTemporal($ruta_absoluta) {
    $resultado = [
        'success' => false,
        'mensaje' => ''
    ];
    
    // Validar que la ruta no esté vacía
    if (empty($ruta_absoluta)) {
        $resultado['mensaje'] = 'Ruta de imagen no especificada';
        return $resultado;
    }
    
    // Obtener directorio raíz y directorio temporal base
    $directorio_raiz = dirname(__DIR__);
    $directorio_temp_base = $directorio_raiz . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'temp_imagenes';
    
    // Normalizar rutas para comparación (resolver rutas relativas y eliminar ..)
    $ruta_absoluta_normalizada = realpath($ruta_absoluta);
    $directorio_temp_base_normalizado = realpath($directorio_temp_base);
    
    // Validar que el directorio temporal existe
    if (!$directorio_temp_base_normalizado || !is_dir($directorio_temp_base_normalizado)) {
        $resultado['mensaje'] = 'Directorio temporal no existe';
        return $resultado;
    }
    
    // Validar que la ruta del archivo existe
    if (!$ruta_absoluta_normalizada || !file_exists($ruta_absoluta_normalizada)) {
        $resultado['mensaje'] = 'La imagen no existe';
        return $resultado;
    }
    
    // Validar que el archivo está dentro del directorio temporal (seguridad)
    // Verificar que la ruta del archivo comienza con la ruta del directorio temporal
    $ruta_temp_base_len = strlen($directorio_temp_base_normalizado);
    if (substr($ruta_absoluta_normalizada, 0, $ruta_temp_base_len) !== $directorio_temp_base_normalizado) {
        $resultado['mensaje'] = 'Ruta de imagen no válida (fuera del directorio temporal)';
        return $resultado;
    }
    
    // Validar que es un archivo (no un directorio)
    if (!is_file($ruta_absoluta_normalizada)) {
        $resultado['mensaje'] = 'La ruta especificada no es un archivo';
        return $resultado;
    }
    
    // Intentar eliminar el archivo
    if (@unlink($ruta_absoluta_normalizada)) {
        $resultado['success'] = true;
        $resultado['mensaje'] = 'Imagen eliminada correctamente';
        
        // Opcional: Eliminar carpeta temporal si está vacía
        $carpeta_temporal = dirname($ruta_absoluta_normalizada);
        if (is_dir($carpeta_temporal)) {
            $archivos_restantes = glob($carpeta_temporal . DIRECTORY_SEPARATOR . '*');
            // Si no hay más archivos, eliminar la carpeta
            if (empty($archivos_restantes)) {
                @rmdir($carpeta_temporal);
            }
        }
    } else {
        $resultado['mensaje'] = 'Error al eliminar la imagen. Verifica permisos del archivo.';
    }
    
    return $resultado;
}

