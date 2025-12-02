<?php
/**
 * ========================================================================
 * FUNCIONES DE CONTACTO - Tienda Seda y Lino
 * ========================================================================
 * Funciones auxiliares para el sistema de contacto
 * - Obtener nombres legibles de asuntos
 * - Guardar registro de formularios de contacto
 * ========================================================================
 */

/**
 * Guarda un registro del formulario de contacto en form/contactos.json
 * @param array $datos Datos del formulario (nombre, email, asunto, mensaje)
 * @return bool true si se guardó correctamente, false en caso de error
 */
function guardarRegistroContacto($datos) {
    // Validar datos requeridos
    $campos_requeridos = ['nombre', 'email', 'asunto', 'mensaje'];
    foreach ($campos_requeridos as $campo) {
        if (empty($datos[$campo])) {
            return false;
        }
    }
    
    // Ruta del archivo de registro
    $ruta_base = dirname(__DIR__) . '/form';
    $ruta_archivo = $ruta_base . '/contactos.json';
    
    // Crear directorio si no existe
    if (!is_dir($ruta_base)) {
        mkdir($ruta_base, 0755, true);
    }
    
    // Cargar registros existentes
    $registros = [];
    if (file_exists($ruta_archivo)) {
        $contenido = file_get_contents($ruta_archivo);
        $registros = json_decode($contenido, true);
        if (!is_array($registros)) {
            $registros = [];
        }
    }
    
    // Crear nuevo registro
    $nuevo_registro = [
        'fecha' => date('Y-m-d H:i:s'),
        'timestamp' => time(),
        'nombre' => trim($datos['nombre']),
        'email' => trim($datos['email']),
        'asunto' => trim($datos['asunto']),
        'mensaje' => trim($datos['mensaje'])
    ];
    
    // Agregar al array
    $registros[] = $nuevo_registro;
    
    // Guardar en archivo
    $json = json_encode($registros, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($ruta_archivo, $json) === false) {
        return false;
    }
    
    return true;
}

