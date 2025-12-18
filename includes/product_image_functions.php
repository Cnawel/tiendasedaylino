<?php
/**
 * ========================================================================
 * FUNCIONES DE SUBIDA DE IMÁGENES - Tienda Seda y Lino
 * ========================================================================
 * Funciones para subir y gestionar imágenes de productos
 * 
 * FUNCIONES:
 * - subirImagenIndividual(): Sube una imagen individual para producto nuevo
 * - subirImagenColor(): Sube imagen específica por color
 * - subirImagenGenerica(): Sube imagen genérica (miniatura o grupal)
 * 
 * VALIDACIONES:
 * - Formato: JPG, PNG, GIF, WEBP
 * - Tamaño máximo: 5MB por imagen (configurable)
 * - Dimensiones máximas: 4000x4000 pixels (configurable)
 * - Dimensiones mínimas: 50x50 pixels
 * - Verifica que el archivo sea una imagen válida
 * - Crea directorios automáticamente si no existen
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Valida dimensiones y tamaño de una imagen
 * @param array $archivo Array $_FILES del archivo
 * @param int $ancho_maximo Ancho máximo en pixels (default: 4000)
 * @param int $alto_maximo Alto máximo en pixels (default: 4000)
 * @param int $tamaño_maximo_bytes Tamaño máximo en bytes (default: 5MB)
 * @throws Exception Si la validación falla
 */
function validarImagen($archivo, $ancho_maximo = 4000, $alto_maximo = 4000, $tamaño_maximo_bytes = 5242880) {
    // Validar tamaño del archivo
    if ($archivo['size'] > $tamaño_maximo_bytes) {
        $tamaño_mb = round($tamaño_maximo_bytes / 1024 / 1024, 1);
        throw new Exception('El archivo es demasiado grande. Máximo ' . $tamaño_mb . 'MB.');
    }
    
    // Validar dimensiones de la imagen
    $info_imagen = @getimagesize($archivo['tmp_name']);
    if ($info_imagen === false) {
        throw new Exception('El archivo no es una imagen válida.');
    }
    
    $ancho = $info_imagen[0];
    $alto = $info_imagen[1];
    
    if ($ancho > $ancho_maximo || $alto > $alto_maximo) {
        throw new Exception('Las dimensiones de la imagen son demasiado grandes. Máximo ' . $ancho_maximo . 'x' . $alto_maximo . ' pixels. La imagen actual es ' . $ancho . 'x' . $alto . ' pixels.');
    }
    
    // Validar que tenga dimensiones mínimas razonables
    if ($ancho < 50 || $alto < 50) {
        throw new Exception('Las dimensiones de la imagen son demasiado pequeñas. Mínimo 50x50 pixels.');
    }
}

/**
 * Sube una imagen individual localmente al servidor para producto nuevo
 * @param int $id_producto ID del producto
 * @param array $archivo Array $_FILES del archivo a subir
 * @param int $indice Índice de la imagen (0=miniatura, 1=principal, 2=extra1, 3=extra2)
 * @return string Ruta completa de la imagen guardada
 * @throws Exception Si hay error en validación o subida
 */
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
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $extensiones_permitidas)) {
        throw new Exception('Formato de archivo no válido: ' . $extension);
    }
    
    // Validar tamaño y dimensiones de la imagen
    validarImagen($archivo);
    
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

/**
 * Sube imagen específica por color para producto existente
 * @param int $id_producto ID del producto
 * @param string $color Color de la imagen
 * @param array $archivo Array $_FILES del archivo a subir
 * @param string $slot Slot de imagen ('img1' o 'img2')
 * @return string Ruta completa de la imagen guardada
 * @throws Exception Si hay error en validación o subida
 */
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
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $extensiones_permitidas)) {
        throw new Exception('Formato de archivo no válido: ' . $extension);
    }

    // Validar tamaño y dimensiones de la imagen
    validarImagen($archivo);

    // Generar nombre único por slot
    $slot_norm = in_array($slot, ['img1', 'img2']) ? $slot : 'img';
    $nombre_archivo = 'imagen_' . $slot_norm . '_' . time() . '_' . $id_producto . '.' . $extension;
    $ruta_completa = $directorio_producto_color . $nombre_archivo;

    if (move_uploaded_file($archivo_temporal, $ruta_completa)) {
        return $ruta_completa;
    }

    throw new Exception('Error al subir la imagen: ' . $nombre_original);
}

/**
 * Sube imagen genérica (miniatura o grupal) para producto existente
 * @param int $id_producto ID del producto
 * @param array $archivo Array $_FILES del archivo a subir
 * @param string $tipo Tipo de imagen ('miniatura' o 'grupal')
 * @return string Ruta completa de la imagen guardada
 * @throws Exception Si hay error en validación o subida
 */
function subirImagenGenerica($id_producto, $archivo, $tipo) {
    $tipo_norm = in_array($tipo, ['miniatura','grupal']) ? $tipo : 'generica';
    $directorio_producto = 'imagenes/productos/producto_' . $id_producto . '/';

    if (!file_exists($directorio_producto)) {
        mkdir($directorio_producto, 0755, true);
    }

    $archivo_temporal = $archivo['tmp_name'];
    $nombre_original = $archivo['name'];
    $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($extension, $extensiones_permitidas)) {
        throw new Exception('Formato de archivo no válido: ' . $extension);
    }
    
    // Validar tamaño y dimensiones de la imagen
    validarImagen($archivo);
    
    $nombre_archivo = 'imagen_generica_' . $tipo_norm . '_' . time() . '_' . $id_producto . '.' . $extension;
    $ruta_completa = $directorio_producto . $nombre_archivo;
    
    if (move_uploaded_file($archivo_temporal, $ruta_completa)) {
        return $ruta_completa;
    }
    
    throw new Exception('Error al subir la imagen: ' . $nombre_original);
}

/**
 * Sube una foto al directorio temporal global para carga masiva CSV
 * @param array $archivo Array $_FILES del archivo a subir
 * @return string Nombre único del archivo guardado
 * @throws Exception Si hay error en validación o subida
 */
function subirFotoTemporal($archivo) {
    // Directorio temporal global
    $directorio_temporal = 'imagenes/';
    
    // Crear directorio si no existe
    if (!file_exists($directorio_temporal)) {
        mkdir($directorio_temporal, 0755, true);
    }
    
    $archivo_temporal = $archivo['tmp_name'];
    $nombre_original = $archivo['name'];
    $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    
    // Validar tipo de archivo
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $extensiones_permitidas)) {
        throw new Exception('Formato de archivo no válido: ' . $extension);
    }
    
    // Validar tamaño y dimensiones de la imagen
    validarImagen($archivo);
    
    // Mantener nombre original del archivo (sanitizado para seguridad)
    // Solo reemplazar caracteres problemáticos, mantener el resto del nombre
    $nombre_base = pathinfo($nombre_original, PATHINFO_FILENAME);
    // Sanitizar: reemplazar espacios y caracteres especiales problemáticos, pero mantener estructura original
    $nombre_sanitizado = preg_replace('/[<>:"|?*\x00-\x1F]/', '_', $nombre_base);
    $nombre_sanitizado = preg_replace('/\s+/', '_', $nombre_sanitizado); // Reemplazar espacios con guión bajo
    $nombre_archivo = $nombre_sanitizado . '.' . $extension;
    
    // Si el archivo ya existe, agregar sufijo numérico
    $ruta_completa = $directorio_temporal . $nombre_archivo;
    $contador = 1;
    while (file_exists($ruta_completa)) {
        $nombre_archivo = $nombre_sanitizado . '_' . $contador . '.' . $extension;
        $ruta_completa = $directorio_temporal . $nombre_archivo;
        $contador++;
    }
    
    // Subir archivo
    if (move_uploaded_file($archivo_temporal, $ruta_completa)) {
        return $nombre_archivo;
    } else {
        throw new Exception('Error al subir la imagen: ' . $nombre_original);
    }
}

/**
 * Obtiene lista de Fotos Disponibles
 * Solo retorna archivos que realmente existen y tienen contenido válido
 * @return array Array con nombres de archivos temporales
 */
function obtenerFotosTemporales() {
    $directorio_temporal = 'imagenes/';
    $fotos = [];

    // Verificar que el directorio existe
    if (!is_dir($directorio_temporal)) {
        return $fotos; // Retornar array vacío si directorio no existe
    }

    $archivos = scandir($directorio_temporal);
    if ($archivos === false) {
        return $fotos; // Error al leer directorio
    }

    foreach ($archivos as $archivo) {
        // Saltar . y ..
        if ($archivo === '.' || $archivo === '..') {
            continue;
        }

        $ruta_completa = $directorio_temporal . $archivo;

        // VALIDACIONES CRÍTICAS:
        // 1. Debe ser un archivo (no directorio)
        // 2. Debe existir físicamente
        // 3. Debe ser legible
        // 4. Debe tener tamaño mayor a 0 bytes
        if (!is_file($ruta_completa) || !file_exists($ruta_completa)) {
            continue;
        }

        // Verificar que el archivo sea legible
        if (!is_readable($ruta_completa)) {
            continue;
        }

        // Verificar que tenga contenido (tamaño > 0)
        $tamaño = @filesize($ruta_completa);
        if ($tamaño === false || $tamaño === 0) {
            continue;
        }

        // Validar extensión de imagen
        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $fotos[] = $archivo;
        }
    }

    return $fotos;
}

/**
 * Elimina una foto temporal
 * @param string $nombre_archivo Nombre del archivo a eliminar
 * @return bool True si se eliminó correctamente
 */
function eliminarFotoTemporal($nombre_archivo) {
    $directorio_temporal = 'imagenes/';
    $ruta_archivo = $directorio_temporal . $nombre_archivo;
    
    // Validar que el archivo esté en el directorio temporal (seguridad)
    $ruta_real = realpath($ruta_archivo);
    $directorio_real = realpath($directorio_temporal);
    
    if ($ruta_real === false || $directorio_real === false) {
        return false;
    }
    
    if (strpos($ruta_real, $directorio_real) !== 0) {
        return false; // Intento de path traversal
    }
    
    if (file_exists($ruta_archivo)) {
        return unlink($ruta_archivo);
    }
    
    return false;
}

