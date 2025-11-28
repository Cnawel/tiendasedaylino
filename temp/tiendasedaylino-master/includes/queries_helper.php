<?php
/**
 * ========================================================================
 * HELPER PARA CARGAR ARCHIVOS DE QUERIES - Tienda Seda y Lino
 * ========================================================================
 * Funciones helper para cargar archivos de queries de forma centralizada
 * y eliminar código duplicado de verificaciones file_exists/require_once
 * 
 * FUNCIONES:
 * - cargarArchivoQueries(): Carga un archivo de queries con validación
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Carga un archivo de queries desde el directorio includes/queries/
 * 
 * Esta función centraliza la lógica de carga de archivos de queries,
 * eliminando código duplicado de verificaciones file_exists/require_once
 * en múltiples archivos.
 * 
 * @param string $nombre_archivo Nombre del archivo sin extensión (ej: 'pago_queries')
 * @param string|null $directorio Directorio base (por defecto: __DIR__ . '/queries/')
 * @return void
 * @throws Exception Si el archivo no existe o no se puede cargar
 */
function cargarArchivoQueries($nombre_archivo, $directorio = null) {
    // Validar nombre de archivo
    if (empty($nombre_archivo) || !is_string($nombre_archivo)) {
        throw new Exception('Nombre de archivo de queries inválido');
    }
    
    // Sanitizar nombre de archivo para prevenir path traversal
    $nombre_archivo = basename($nombre_archivo);
    
    // Determinar directorio base
    if ($directorio === null) {
        $directorio = __DIR__ . '/queries/';
    }
    
    // Construir ruta completa
    $ruta_archivo = rtrim($directorio, '/') . '/' . $nombre_archivo . '.php';
    
    // Verificar que el archivo existe
    if (!file_exists($ruta_archivo)) {
        error_log("ERROR: No se pudo encontrar {$nombre_archivo}.php en " . $ruta_archivo);
        throw new Exception("Archivo de consultas no encontrado: {$nombre_archivo}.php");
    }
    
    // Cargar archivo
    require_once $ruta_archivo;
}

