<?php
/**
 * ========================================================================
 * FUNCIONES DE VALIDACIÓN CENTRALIZADAS - Tienda Seda y Lino
 * ========================================================================
 * Archivo centralizado con todas las funciones de validación PHP
 * 
 * IMPORTANTE: JavaScript es solo para UX, PHP es la validación definitiva de seguridad.
 * Todas las validaciones deben ejecutarse en el servidor.
 * 
 * FUNCIONES JAVASCRIPT EQUIVALENTES:
 * - validarTelefono() -> common_js_functions.php:validarTelefono()
 * - validarCodigoPostal() -> common_js_functions.php:validarCodigoPostal()
 * - validarDireccion() -> common_js_functions.php:validarDireccion()
 * - validarEmail() -> common_js_functions.php:validateEmail()
 * - validarNombreApellido() -> common_js_functions.php:validarNombreApellido()
 * 
 * NOTA: Las funciones de validación de nombre/apellido y email están en admin_functions.php
 * para mantener compatibilidad. Se referencian aquí pero no se duplican.
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

// Cargar funciones de validación existentes de admin_functions.php
$admin_functions_path = __DIR__ . '/admin_functions.php';
if (!file_exists($admin_functions_path)) {
    error_log("ERROR: No se pudo encontrar admin_functions.php en " . $admin_functions_path);
    die("Error crítico: Archivo de funciones de administración no encontrado. Por favor, contacta al administrador.");
}
require_once $admin_functions_path;

/**
 * Valida un teléfono según reglas de negocio
 * 
 * REGLAS DE VALIDACIÓN:
 * - Longitud: 6-20 caracteres
 * - Formato: Solo números, espacios y símbolos: +, -, (, )
 * - Patrón: /^[0-9+\-() ]+$/
 * 
 * VALIDACIÓN CLIENTE (JavaScript):
 * - common_js_functions.php:validarTelefono() - Mismas reglas
 * - js/checkout.js - Usa función centralizada
 * 
 * VALIDACIÓN SERVIDOR (PHP):
 * - Esta función es la validación definitiva de seguridad
 * - Siempre validar en servidor, JavaScript es solo UX
 * 
 * @param string $telefono Teléfono a validar
 * @param bool $es_opcional Si es true, permite campo vacío (opcional)
 * @return array ['valido' => bool, 'valor' => string, 'error' => string]
 */
function validarTelefono($telefono, $es_opcional = true) {
    // Normalizar: remover espacios al inicio y final
    $telefono = trim($telefono);
    
    // Si es opcional y está vacío, es válido
    if ($es_opcional && empty($telefono)) {
        return ['valido' => true, 'valor' => '', 'error' => ''];
    }
    
    // Si no es opcional y está vacío, es inválido
    if (!$es_opcional && empty($telefono)) {
        return ['valido' => false, 'valor' => '', 'error' => 'El teléfono es requerido.'];
    }
    
    // VALIDACIÓN 1: Longitud mínima (6 caracteres)
    if (strlen($telefono) < 6) {
        return ['valido' => false, 'valor' => '', 'error' => 'El teléfono debe tener al menos 6 caracteres.'];
    }
    
    // VALIDACIÓN 2: Longitud máxima (20 caracteres)
    if (strlen($telefono) > 20) {
        return ['valido' => false, 'valor' => '', 'error' => 'El teléfono no puede exceder 20 caracteres.'];
    }
    
    // VALIDACIÓN 3: Formato válido (solo números, espacios y símbolos: +, -, (, ))
    // Patrón debe coincidir exactamente con JavaScript: /^[0-9+()\- ]+$/
    if (!preg_match('/^[0-9+\-() ]+$/', $telefono)) {
        return ['valido' => false, 'valor' => '', 'error' => 'El teléfono solo puede contener números y símbolos (+, -, paréntesis, espacios).'];
    }
    
    // NOTA: NO sanitizar aquí con htmlspecialchars() - los datos deben guardarse en BD sin sanitizar
    // La sanitización debe hacerse solo al mostrar en HTML usando htmlspecialchars() en los templates
    
    return ['valido' => true, 'valor' => $telefono, 'error' => ''];
}

/**
 * Valida un código postal según reglas de negocio
 * 
 * REGLAS DE VALIDACIÓN:
 * - Formato: Solo letras, números y espacios
 * - Patrón: /^[A-Za-z0-9 ]+$/
 * 
 * VALIDACIÓN CLIENTE (JavaScript):
 * - common_js_functions.php:validarCodigoPostal() - Mismo patrón
 * - js/checkout.js - Usa función centralizada
 * 
 * VALIDACIÓN SERVIDOR (PHP):
 * - Esta función es la validación definitiva de seguridad
 * - Siempre validar en servidor, JavaScript es solo UX
 * 
 * @param string $codigo_postal Código postal a validar
 * @return array ['valido' => bool, 'valor' => string, 'error' => string]
 */
function validarCodigoPostal($codigo_postal) {
    // Normalizar: remover espacios al inicio y final
    $codigo_postal = trim($codigo_postal);
    
    // VALIDACIÓN 1: Campo obligatorio
    if (empty($codigo_postal)) {
        return ['valido' => false, 'valor' => '', 'error' => 'El código postal es requerido.'];
    }
    
    // VALIDACIÓN 2: Formato válido (solo letras, números y espacios)
    // Patrón debe coincidir exactamente con JavaScript: /^[A-Za-z0-9 ]+$/
    if (!preg_match('/^[A-Za-z0-9 ]+$/', $codigo_postal)) {
        return ['valido' => false, 'valor' => '', 'error' => 'El código postal solo puede contener letras, números y espacios.'];
    }
    
    // NOTA: NO sanitizar aquí con htmlspecialchars() - los datos deben guardarse en BD sin sanitizar
    // La sanitización debe hacerse solo al mostrar en HTML usando htmlspecialchars() en los templates
    
    return ['valido' => true, 'valor' => $codigo_postal, 'error' => ''];
}

/**
 * Valida un componente de dirección (calle, número o piso/departamento)
 * 
 * REGLAS DE VALIDACIÓN:
 * - Longitud mínima: Variable según tipo (calle: 2, número: 1, piso: 1)
 * - Formato: Letras (con acentos), números, espacios, guiones, apóstrofes y acentos graves
 * - Patrón: /^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]+$/
 * 
 * VALIDACIÓN CLIENTE (JavaScript):
 * - common_js_functions.php:validarDireccion() - Mismas reglas
 * - js/checkout.js - Usa función centralizada
 * 
 * VALIDACIÓN SERVIDOR (PHP):
 * - Esta función es la validación definitiva de seguridad
 * - Siempre validar en servidor, JavaScript es solo UX
 * 
 * @param string $direccion Componente de dirección a validar
 * @param bool $es_requerido Si es true, el campo es obligatorio (default: true)
 * @param int $longitud_minima Longitud mínima (default: 2)
 * @param string $tipo_campo Tipo de campo: 'calle', 'numero', 'piso' (para mensajes personalizados)
 * @return array ['valido' => bool, 'valor' => string, 'error' => string]
 */
function validarDireccion($direccion, $es_requerido = true, $longitud_minima = 2, $tipo_campo = 'dirección') {
    // Normalizar: remover espacios al inicio y final
    $direccion = trim($direccion);
    
    // Si es opcional (piso/depto) y está vacío, es válido
    if (!$es_requerido && empty($direccion)) {
        return ['valido' => true, 'valor' => '', 'error' => ''];
    }
    
    // Si es requerido y está vacío, es inválido
    if ($es_requerido && empty($direccion)) {
        $mensaje = $tipo_campo === 'calle' ? 'La dirección (calle) es requerida' : 
                   ($tipo_campo === 'numero' ? 'El número de dirección es requerido' : 
                   'La dirección es requerida');
        return ['valido' => false, 'valor' => '', 'error' => $mensaje];
    }
    
    // VALIDACIÓN 1: Longitud mínima
    if (strlen($direccion) < $longitud_minima) {
        return ['valido' => false, 'valor' => '', 'error' => "La dirección debe tener al menos {$longitud_minima} caracteres."];
    }
    
    // VALIDACIÓN 2: Formato válido
    // Patrón debe coincidir exactamente con JavaScript: /^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]+$/
    if (!preg_match("/^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-\'`]+$/", $direccion)) {
        return ['valido' => false, 'valor' => '', 'error' => 'La dirección contiene caracteres no permitidos. Solo se permiten letras (incluyendo acentos), números, espacios, guiones, apóstrofes y acentos graves.'];
    }
    
    // NOTA: NO sanitizar aquí con htmlspecialchars() - los datos deben guardarse en BD sin sanitizar
    // La sanitización debe hacerse solo al mostrar en HTML usando htmlspecialchars() en los templates
    
    return ['valido' => true, 'valor' => $direccion, 'error' => ''];
}

/**
 * Valida una dirección completa (calle + número + piso combinados)
 * 
 * REGLAS DE VALIDACIÓN:
 * - Longitud: 5-100 caracteres combinados
 * - Formato: Letras (con acentos), números, espacios, puntos, comas, guiones, apóstrofes y acentos graves
 * - Patrón: /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ0-9\s\.,\-\'`]+$/
 * 
 * VALIDACIÓN CLIENTE (JavaScript):
 * - Se valida por componentes (calle, número, piso) usando validarDireccion()
 * - La validación combinada se hace en servidor
 * 
 * VALIDACIÓN SERVIDOR (PHP):
 * - Esta función es la validación definitiva de seguridad
 * - Siempre validar en servidor, JavaScript es solo UX
 * 
 * @param string $direccion_calle Calle de la dirección
 * @param string $direccion_numero Número de la dirección
 * @param string $direccion_piso Piso/Departamento (opcional)
 * @return array ['valido' => bool, 'direccion_completa' => string, 'error' => string]
 */
function validarDireccionCompleta($direccion_calle, $direccion_numero, $direccion_piso = '') {
    // Validar componentes individuales primero
    $validacion_calle = validarDireccion($direccion_calle, true, 2, 'calle');
    if (!$validacion_calle['valido']) {
        return ['valido' => false, 'direccion_completa' => '', 'error' => $validacion_calle['error']];
    }
    
    $validacion_numero = validarDireccion($direccion_numero, true, 1, 'numero');
    if (!$validacion_numero['valido']) {
        return ['valido' => false, 'direccion_completa' => '', 'error' => $validacion_numero['error']];
    }
    
    // Piso es opcional
    if (!empty($direccion_piso)) {
        $validacion_piso = validarDireccion($direccion_piso, false, 1, 'piso');
        if (!$validacion_piso['valido']) {
            return ['valido' => false, 'direccion_completa' => '', 'error' => $validacion_piso['error']];
        }
    }
    
    // Combinar dirección completa
    $direccion_completa = trim($validacion_calle['valor']) . ' ' . trim($validacion_numero['valor']);
    if (!empty($direccion_piso)) {
        $direccion_completa .= ' ' . trim($direccion_piso);
    }
    $direccion_completa = trim($direccion_completa);
    
    // VALIDACIÓN 1: Longitud mínima combinada (5 caracteres)
    if (strlen($direccion_completa) < 5) {
        return ['valido' => false, 'direccion_completa' => '', 'error' => 'La dirección debe tener al menos 5 caracteres.'];
    }
    
    // VALIDACIÓN 2: Longitud máxima combinada (100 caracteres)
    if (strlen($direccion_completa) > 100) {
        return ['valido' => false, 'direccion_completa' => '', 'error' => 'La dirección no puede exceder 100 caracteres.'];
    }
    
    // VALIDACIÓN 3: Formato válido de dirección combinada
    // Patrón debe coincidir con validación en procesar-pedido.php: /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ0-9\s\.,\-\'`]+$/
    if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ0-9\s\.,\-\'`]+$/", $direccion_completa)) {
        return ['valido' => false, 'direccion_completa' => '', 'error' => 'La dirección contiene caracteres no permitidos. Solo se permiten letras, números, espacios, puntos, comas, guiones, apóstrofes y acentos graves.'];
    }
    
    // NOTA: NO sanitizar aquí con htmlspecialchars() - los datos deben guardarse en BD sin sanitizar
    // La sanitización debe hacerse solo al mostrar en HTML usando htmlspecialchars() en los templates
    
    return ['valido' => true, 'direccion_completa' => $direccion_completa, 'error' => ''];
}

/**
 * Valida datos completos de usuario (nombre, apellido, email, teléfono)
 * 
 * Esta función centraliza la validación de todos los campos personales del usuario.
 * Usa las funciones de validación centralizadas de admin_functions.php y validation_functions.php.
 * 
 * VALIDACIÓN CLIENTE (JavaScript):
 * - js/register.js - Validación en tiempo real
 * - js/checkout.js - Validación en tiempo real
 * 
 * VALIDACIÓN SERVIDOR (PHP):
 * - Esta función es la validación definitiva de seguridad
 * - Siempre validar en servidor, JavaScript es solo UX
 * 
 * @param string $nombre Nombre del usuario
 * @param string $apellido Apellido del usuario
 * @param string $email Email del usuario
 * @param string $telefono Teléfono del usuario (opcional)
 * @return array ['valido' => bool, 'datos' => array, 'errores' => array]
 */
function validarDatosUsuario($nombre, $apellido, $email, $telefono = '') {
    $errores = [];
    $datos_validos = [];
    
    // Validar nombre usando función centralizada de admin_functions.php
    $validacion_nombre = validarNombreApellido($nombre, 'nombre');
    if (!$validacion_nombre['valido']) {
        $errores['nombre'] = $validacion_nombre['error'];
    } else {
        $datos_validos['nombre'] = $validacion_nombre['valor'];
    }
    
    // Validar apellido usando función centralizada de admin_functions.php
    $validacion_apellido = validarNombreApellido($apellido, 'apellido');
    if (!$validacion_apellido['valido']) {
        $errores['apellido'] = $validacion_apellido['error'];
    } else {
        $datos_validos['apellido'] = $validacion_apellido['valor'];
    }
    
    // Validar email usando función centralizada de admin_functions.php
    $validacion_email = validarEmail($email);
    if (!$validacion_email['valido']) {
        $errores['email'] = $validacion_email['error'];
    } else {
        // NOTA: validarEmail() retorna valor sanitizado, pero para guardar en BD necesitamos el original
        // Usar el email original sin sanitizar para guardar en BD
        $datos_validos['email'] = strtolower(trim($email));
    }
    
    // Validar teléfono (opcional) usando función centralizada
    if (!empty($telefono)) {
        $validacion_telefono = validarTelefono($telefono, true);
        if (!$validacion_telefono['valido']) {
            $errores['telefono'] = $validacion_telefono['error'];
        } else {
            $datos_validos['telefono'] = $validacion_telefono['valor'];
        }
    } else {
        $datos_validos['telefono'] = '';
    }
    
    return [
        'valido' => empty($errores),
        'datos' => $datos_validos,
        'errores' => $errores
    ];
}

/**
 * Valida fecha de nacimiento
 * 
 * REGLAS DE VALIDACIÓN:
 * - Formato: YYYY-MM-DD o dd/mm/yyyy
 * - Rango de años: 1925-2012
 * - Edad mínima: 13 años
 * - No puede ser fecha futura
 * 
 * VALIDACIÓN CLIENTE (JavaScript):
 * - js/register.js - Validación de rango y edad mínima
 * 
 * VALIDACIÓN SERVIDOR (PHP):
 * - Esta función es la validación definitiva de seguridad
 * - Siempre validar en servidor, JavaScript es solo UX
 * 
 * @param string $fecha_nacimiento Fecha de nacimiento a validar (formato YYYY-MM-DD o dd/mm/yyyy)
 * @return array ['valido' => bool, 'valor' => string|null, 'error' => string]
 */
function validarFechaNacimiento($fecha_nacimiento) {
    // Normalizar: remover espacios al inicio y final
    $fecha_nacimiento = trim($fecha_nacimiento);
    
    // Si está vacío, es válido (campo opcional)
    if (empty($fecha_nacimiento)) {
        return ['valido' => true, 'valor' => null, 'error' => ''];
    }
    
    $fecha_procesada = null;
    
    // Intentar parsear formato dd/mm/yyyy
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha_nacimiento, $matches)) {
        $dia = intval($matches[1]);
        $mes = intval($matches[2]);
        $ano = intval($matches[3]);
        if (checkdate($mes, $dia, $ano)) {
            $fecha_procesada = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
        }
    }
    // Si no es dd/mm/yyyy, intentar formato YYYY-MM-DD
    elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_nacimiento)) {
        $fecha_parts = explode('-', $fecha_nacimiento);
        if (count($fecha_parts) === 3 && checkdate(intval($fecha_parts[1]), intval($fecha_parts[2]), intval($fecha_parts[0]))) {
            $fecha_procesada = $fecha_nacimiento;
        }
    }
    
    // Si no se pudo procesar, formato inválido
    if (!$fecha_procesada) {
        return ['valido' => false, 'valor' => null, 'error' => 'El formato de fecha de nacimiento no es válido. Use formato dd/mm/aaaa o YYYY-MM-DD.'];
    }
    
    try {
        $fecha_nac = new DateTime($fecha_procesada);
        $hoy = new DateTime();
        $hoy->setTime(0, 0, 0);
        $fecha_nac->setTime(0, 0, 0);
        
        // Extraer año de la fecha procesada
        $año = intval($fecha_nac->format('Y'));
        
        // VALIDACIÓN 1: Rango de año permitido (1925-2012)
        if ($año < 1925 || $año > 2012) {
            return ['valido' => false, 'valor' => null, 'error' => 'La fecha de nacimiento debe estar entre 1925 y 2012.'];
        }
        
        // VALIDACIÓN 2: No puede ser futura
        if ($fecha_nac > $hoy) {
            return ['valido' => false, 'valor' => null, 'error' => 'La fecha de nacimiento no puede ser futura.'];
        }
        
        // VALIDACIÓN 3: Edad mínima (13 años)
        $edad_minima = new DateTime();
        $edad_minima->setTime(0, 0, 0);
        $edad_minima->modify('-13 years');
        
        if ($fecha_nac > $edad_minima) {
            return ['valido' => false, 'valor' => null, 'error' => 'Debes tener al menos 13 años para registrarte.'];
        }
        
        // Guardar en formato YYYY-MM-DD para MySQL DATE
        return ['valido' => true, 'valor' => $fecha_nac->format('Y-m-d'), 'error' => ''];
        
    } catch (Exception $e) {
        return ['valido' => false, 'valor' => null, 'error' => 'La fecha de nacimiento no es válida.'];
    }
}

