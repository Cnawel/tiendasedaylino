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
    
    // VALIDACIÓN 2: Longitud mínima (4 caracteres)
    // REGLA DE NEGOCIO: Según diccionario de datos, longitud mínima: 4 caracteres
    if (strlen($codigo_postal) < 4) {
        return ['valido' => false, 'valor' => '', 'error' => 'El código postal debe tener al menos 4 caracteres.'];
    }
    
    // VALIDACIÓN 3: Longitud máxima (10 caracteres)
    // REGLA DE NEGOCIO: Según diccionario de datos, longitud máxima: 10 caracteres
    if (strlen($codigo_postal) > 10) {
        return ['valido' => false, 'valor' => '', 'error' => 'El código postal no puede exceder 10 caracteres.'];
    }
    
    // VALIDACIÓN 4: Formato válido (solo letras y números, sin espacios)
    // Patrón debe coincidir exactamente con JavaScript: /^[A-Za-z0-9]+$/
    // Según diccionario: [0-9, A-Z, a-z] sin espacios
    if (!preg_match('/^[A-Za-z0-9]+$/', $codigo_postal)) {
        return ['valido' => false, 'valor' => '', 'error' => 'El código postal solo puede contener letras y números.'];
    }
    
    // Eliminar espacios del valor antes de retornar (por si acaso quedaron espacios internos)
    $codigo_postal = str_replace(' ', '', $codigo_postal);
    
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

/**
 * Valida observaciones o motivo de rechazo
 * Permite: letras (con acentos), números, espacios, puntos, comas, guiones, dos puntos, punto y coma, apóstrofes y acentos graves
 * Según diccionario: [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, 0-9, espacios, ., -, ,, :, ;, ', `]
 * 
 * VALIDACIÓN SERVIDOR (PHP):
 * - Esta función es la validación definitiva de seguridad
 * - Siempre validar en servidor, JavaScript es solo UX
 * 
 * @param string $valor Valor a validar
 * @param int $longitud_maxima Longitud máxima permitida (default: 500)
 * @param string $campo Nombre del campo para mensajes de error (default: 'campo')
 * @return array ['valido' => bool, 'valor' => string, 'error' => string]
 */
function validarObservaciones($valor, $longitud_maxima = 500, $campo = 'campo') {
    // Normalizar: remover espacios al inicio y final
    $valor = trim($valor);
    
    // Si está vacío, es válido (campo opcional)
    if (empty($valor)) {
        return ['valido' => true, 'valor' => '', 'error' => ''];
    }
    
    // VALIDACIÓN 1: Longitud máxima
    if (strlen($valor) > $longitud_maxima) {
        return ['valido' => false, 'valor' => '', 'error' => "El $campo no puede exceder $longitud_maxima caracteres."];
    }
    
    // VALIDACIÓN 2: Caracteres permitidos según diccionario
    // Permite: letras (con acentos), números, espacios, puntos, comas, guiones, dos puntos, punto y coma, apóstrofes y acentos graves
    if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ0-9\s\.,\-\:\;\'`]+$/", $valor)) {
        return ['valido' => false, 'valor' => '', 'error' => "El $campo contiene caracteres no permitidos. Solo se permiten letras, números, espacios, puntos, comas, guiones, dos puntos, punto y coma, apóstrofes y acentos graves."];
    }
    
    // NOTA: NO sanitizar aquí con htmlspecialchars() - los datos deben guardarse en BD sin sanitizar
    // La sanitización debe hacerse solo al mostrar en HTML usando htmlspecialchars() en los templates
    
    return ['valido' => true, 'valor' => $valor, 'error' => ''];
}

/**
 * Valida número de transacción
 * Permite: letras, números, guiones y guiones bajos según diccionario [A-Z, a-z, 0-9, -, _]
 * 
 * VALIDACIÓN SERVIDOR (PHP):
 * - Esta función es la validación definitiva de seguridad
 * - Siempre validar en servidor, JavaScript es solo UX
 * 
 * @param string $valor Valor a validar
 * @param int $longitud_minima Longitud mínima permitida (default: 0, no hay mínimo)
 * @param int $longitud_maxima Longitud máxima permitida (default: 100)
 * @return array ['valido' => bool, 'valor' => string, 'error' => string]
 */
function validarNumeroTransaccion($valor, $longitud_minima = 0, $longitud_maxima = 100) {
    // Normalizar: remover espacios al inicio y final
    $valor = trim($valor);
    
    // Si está vacío, es válido (campo opcional según diccionario)
    if (empty($valor)) {
        return ['valido' => true, 'valor' => '', 'error' => ''];
    }
    
    // VALIDACIÓN 1: Longitud máxima
    if (strlen($valor) > $longitud_maxima) {
        return ['valido' => false, 'valor' => '', 'error' => "El número de transacción no puede exceder $longitud_maxima caracteres."];
    }
    
    // VALIDACIÓN 3: Caracteres permitidos según diccionario: [A-Z, a-z, 0-9, -, _]
    if (!preg_match('/^[A-Za-z0-9\-_]+$/', $valor)) {
        return ['valido' => false, 'valor' => '', 'error' => 'El número de transacción solo puede contener letras, números, guiones y guiones bajos.'];
    }
    
    // NOTA: NO sanitizar aquí con htmlspecialchars() - los datos deben guardarse en BD sin sanitizar
    // La sanitización debe hacerse solo al mostrar en HTML usando htmlspecialchars() en los templates
    
    return ['valido' => true, 'valor' => $valor, 'error' => ''];
}

/**
 * Normaliza un email para búsqueda en base de datos
 * 
 * Realiza las siguientes operaciones:
 * - Convierte a minúsculas
 * - Elimina espacios al inicio y final
 * - Elimina caracteres de control (0x00-0x1F y 0x7F)
 * 
 * VALIDACIÓN SERVIDOR (PHP):
 * - Esta función centraliza la normalización de email usada en múltiples archivos
 * - Usada en: register.php, login.php, recuperar-contrasena.php
 * 
 * @param string $email_raw Email sin normalizar
 * @return string Email normalizado para búsqueda en BD
 */
function normalizarEmail($email_raw) {
    // Convertir a minúsculas y eliminar espacios
    $email = strtolower(trim($email_raw));
    
    // Eliminar caracteres de control (0x00-0x1F y 0x7F)
    $email = preg_replace('/[\x00-\x1F\x7F]/u', '', $email);
    
    return $email;
}

/**
 * Valida respuesta de recupero según reglas de negocio
 * 
 * REGLAS DE VALIDACIÓN:
 * - Campo obligatorio
 * - Longitud: 4-20 caracteres
 * - Formato: Solo letras, números y espacios
 * - Patrón: /^[a-zA-Z0-9 ]+$/
 * 
 * VALIDACIÓN SERVIDOR (PHP):
 * - Esta función centraliza la validación de respuesta de recupero
 * - Usada en: register.php, recuperar-contrasena.php
 * 
 * @param string $respuesta_recupero Respuesta de recupero a validar
 * @return array ['valido' => bool, 'valor' => string, 'error' => string]
 */
function validarRespuestaRecupero($respuesta_recupero) {
    // Normalizar: remover espacios al inicio y final
    $respuesta = trim($respuesta_recupero);
    
    // VALIDACIÓN 1: Campo obligatorio
    if (empty($respuesta)) {
        return ['valido' => false, 'valor' => '', 'error' => 'La respuesta de recupero es obligatoria.'];
    }
    
    // VALIDACIÓN 2: Longitud mínima (4 caracteres)
    if (strlen($respuesta) < 4) {
        return ['valido' => false, 'valor' => '', 'error' => 'La respuesta de recupero debe tener al menos 4 caracteres.'];
    }
    
    // VALIDACIÓN 3: Longitud máxima (20 caracteres)
    if (strlen($respuesta) > 20) {
        return ['valido' => false, 'valor' => '', 'error' => 'La respuesta de recupero no puede exceder 20 caracteres.'];
    }
    
    // VALIDACIÓN 4: Formato válido (solo letras, números y espacios)
    if (!preg_match('/^[a-zA-Z0-9 ]+$/', $respuesta)) {
        return ['valido' => false, 'valor' => '', 'error' => 'La respuesta de recupero solo puede contener letras, números y espacios.'];
    }
    
    // NOTA: NO sanitizar aquí con htmlspecialchars() - los datos deben guardarse en BD sin sanitizar
    // La sanitización debe hacerse solo al mostrar en HTML usando htmlspecialchars() en los templates

    return ['valido' => true, 'valor' => $respuesta, 'error' => ''];
}

/**
 * Valida todos los datos de recuperación de contraseña en una sola llamada
 * Consolidación de validaciones para reducir nesting en recuperar-contrasena.php
 *
 * @param array $usuario Datos del usuario obtenidos de BD
 * @param string $fecha_nacimiento Fecha de nacimiento proporcionada en formato DD/MM/YYYY
 * @param int $pregunta_id ID de la pregunta de recupero
 * @param string $respuesta Respuesta de la pregunta de recupero
 * @return array Array con: ['valido' => bool, 'error' => string, 'usuario_id' => int]
 */
function validarDatosRecuperacionAvanzada($usuario, $fecha_nacimiento, $pregunta_id, $respuesta) {
    // Validación 1: Verificar que el usuario exista
    if (!$usuario || !isset($usuario['id_usuario'])) {
        return [
            'valido' => false,
            'error' => 'Usuario no encontrado.',
            'usuario_id' => null
        ];
    }

    $usuario_id = $usuario['id_usuario'];

    // Validación 2: Verificar fecha de nacimiento en BD
    $fecha_nac_bd = $usuario['fecha_nacimiento'] ?? null;
    if (empty($fecha_nac_bd)) {
        return [
            'valido' => false,
            'error' => 'No se encontró fecha de nacimiento para este usuario.',
            'usuario_id' => null
        ];
    }

    // Validación 3: Formatear y comparar fechas
    $fecha_nac_formateada = date('d/m/Y', strtotime($fecha_nac_bd));
    if ($fecha_nac_formateada !== $fecha_nacimiento) {
        return [
            'valido' => false,
            'error' => 'La fecha de nacimiento no coincide con nuestros registros.',
            'usuario_id' => null
        ];
    }

    // Validación 4: Verificar pregunta de recupero
    if (empty($pregunta_id) || $pregunta_id <= 0) {
        return [
            'valido' => false,
            'error' => 'Debes seleccionar una pregunta de seguridad.',
            'usuario_id' => null
        ];
    }

    // Validación 5: Verificar respuesta
    if (empty($respuesta)) {
        return [
            'valido' => false,
            'error' => 'Debes proporcionar una respuesta.',
            'usuario_id' => null
        ];
    }

    // Validación 6: Comparar pregunta y respuesta guardadas en BD
    $pregunta_bd = $usuario['pregunta_recupero'] ?? null;
    $respuesta_bd = $usuario['respuesta_recupero'] ?? null;

    if (empty($pregunta_bd) || empty($respuesta_bd)) {
        return [
            'valido' => false,
            'error' => 'No se encontraron los datos de seguridad para este usuario.',
            'usuario_id' => null
        ];
    }

    if ((int)$pregunta_id !== (int)$pregunta_bd) {
        return [
            'valido' => false,
            'error' => 'La pregunta de seguridad no coincide con nuestros registros.',
            'usuario_id' => null
        ];
    }

    // Normalizar y comparar respuestas (case-insensitive, trim)
    $respuesta_usuario = strtolower(trim($respuesta));
    $respuesta_guardada = strtolower(trim($respuesta_bd));

    if ($respuesta_usuario !== $respuesta_guardada) {
        return [
            'valido' => false,
            'error' => 'La respuesta de seguridad es incorrecta.',
            'usuario_id' => null
        ];
    }

    // Todas las validaciones pasaron exitosamente
    return [
        'valido' => true,
        'error' => '',
        'usuario_id' => $usuario_id
    ];
}

/**
 * Valida un correo electrónico según estándares internacionales
 * @param string $valor Correo a validar
 * @return array ['valido' => bool, 'valor' => string, 'error' => string]
 */
function validarEmail($valor) {
    // LÓGICA DE NEGOCIO: Valida y sanitiza emails según estándares internacionales.
    // REGLAS: Obligatorio, máximo 150 caracteres, formato válido según RFC, prevenir XSS.

    // Normalizar: remover espacios al inicio y final
    // LÓGICA: Los espacios en emails son inválidos y pueden causar errores de autenticación
    $valor = trim($valor);

    // VALIDACIÓN 1: Campo obligatorio
    // REGLA DE NEGOCIO: El email es el identificador único del usuario y es obligatorio
    if (empty($valor)) {
        return ['valido' => false, 'valor' => '', 'error' => 'El correo electrónico es obligatorio.'];
    }

    // VALIDACIÓN 2: Longitud mínima (6 caracteres)
    // REGLA DE NEGOCIO: Según diccionario de datos, longitud mínima: 6 caracteres
    // LÓGICA: Previene emails demasiado cortos que no son válidos
    if (strlen($valor) < 6) {
        return ['valido' => false, 'valor' => '', 'error' => 'El correo electrónico debe tener al menos 6 caracteres.'];
    }

    // VALIDACIÓN 3: Longitud máxima (100 caracteres)
    // REGLA DE NEGOCIO: Según diccionario de datos, longitud máxima: 100 caracteres
    // LÓGICA: Previene emails excesivamente largos que pueden causar problemas de almacenamiento
    if (strlen($valor) > 100) {
        return ['valido' => false, 'valor' => '', 'error' => 'El correo electrónico no puede exceder 100 caracteres.'];
    }

    // VALIDACIÓN 4: Formato válido según RFC
    // REGLA DE NEGOCIO: El email debe cumplir con el formato estándar (usuario@dominio.extensión)
    // LÓGICA: Usa filter_var() de PHP que valida según RFC 5322, el estándar internacional para emails
    // Valida: estructura básica (usuario@dominio), caracteres permitidos, extensión válida
    if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
        return ['valido' => false, 'valor' => '', 'error' => 'El formato del correo electrónico no es válido.'];
    }

    // VALIDACIÓN 5: Caracteres permitidos según diccionario de datos
    // REGLA DE NEGOCIO: Según diccionario, solo se permiten: [A-Z, a-z, 0-9, @, _, -, ., +]
    // LÓGICA: Restringir caracteres especiales no permitidos como %, $, #, etc.
    // Validar parte local (antes del @) y dominio (después del @) por separado
    $partes = explode('@', $valor);
    if (count($partes) !== 2) {
        return ['valido' => false, 'valor' => '', 'error' => 'El formato del correo electrónico no es válido.'];
    }

    $parte_local = $partes[0];
    $dominio = $partes[1];

    // Validar parte local: solo [A-Z, a-z, 0-9, _, -, ., +]
    if (!preg_match('/^[A-Za-z0-9_\-\.+]+$/', $parte_local)) {
        return ['valido' => false, 'valor' => '', 'error' => 'El correo electrónico contiene caracteres no permitidos. Solo se permiten letras, números, guion bajo, guion, punto y signo más.'];
    }

    // Validar dominio: solo [A-Z, a-z, 0-9, _, -, .]
    // Nota: El dominio no puede tener + según estándares, pero permitimos los mismos caracteres básicos
    if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $dominio)) {
        return ['valido' => false, 'valor' => '', 'error' => 'El dominio del correo electrónico contiene caracteres no permitidos.'];
    }

    // SANITIZACIÓN: Prevenir ataques XSS
    // REGLA DE SEGURIDAD: Escapar caracteres HTML especiales para prevenir inyección de código
    // LÓGICA: Aunque el email es validado por formato, se sanitiza para mostrar en HTML de forma segura
    $valor = htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');

    return ['valido' => true, 'valor' => $valor, 'error' => ''];
}

/**
 * Valida una contraseña según reglas de seguridad y complejidad
 * @param string $password Contraseña a validar
 * @param bool $requiere_complejidad Si requiere complejidad (minúscula, mayúscula, número, especial)
 * @return array ['valido' => bool, 'error' => string]
 */
function validarPassword($password, $requiere_complejidad = true) {
    // LÓGICA DE NEGOCIO: Valida contraseñas según reglas de seguridad y complejidad.
    // REGLAS: Obligatoria, mínimo 6 caracteres, máximo 20, complejidad opcional (minúscula, mayúscula, número, especial).
    // IMPORTANTE: NO usar trim() en password - puede cambiar la contraseña (los espacios son válidos en algunos casos).

    // VALIDACIÓN 1: Campo obligatorio
    // REGLA DE NEGOCIO: Las contraseñas son obligatorias para proteger cuentas de usuario
    // LÓGICA: Verifica que la contraseña no esté vacía (sin usar trim para no modificar espacios)
    if ($password === '' || strlen($password) === 0) {
        return ['valido' => false, 'error' => 'La contraseña es obligatoria.'];
    }

    // VALIDACIÓN 2: Longitud mínima (6 caracteres)
    // REGLA DE SEGURIDAD: Mínimo 6 caracteres para seguridad básica
    // LÓGICA: Contraseñas más cortas son vulnerables a ataques de fuerza bruta
    if (strlen($password) < 6) {
        return ['valido' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres.'];
    }

    // VALIDACIÓN 3: Longitud máxima (20 caracteres)
    // REGLA DE NEGOCIO: Límite de 20 caracteres según diccionario de datos
    // LÓGICA: Previene contraseñas excesivamente largas que pueden causar problemas de rendimiento
    if (strlen($password) > 20) {
        return ['valido' => false, 'error' => 'La contraseña no puede exceder 20 caracteres.'];
    }

    // VALIDACIÓN 4: Caracteres permitidos - símbolos típicos comunes en contraseñas
    // REGLA DE NEGOCIO: Permitir símbolos típicos comunes para mayor seguridad, pero no son requeridos
    // LÓGICA: Permitir caracteres comunes en contraseñas: letras, números, y símbolos típicos (@, _, -, ., !, $, %, *, ?, &, #, etc.)
    // No se restringen caracteres especiales comunes para permitir mayor seguridad
    // Solo se bloquean caracteres de control y caracteres que puedan causar problemas de seguridad
    // Permitir: letras, números, espacios, y símbolos comunes: @_\-\.!$%*?&#^~|\\\/{}\[\]()<>:;"'=+
    if (!preg_match('/^[A-Za-z0-9@_\-\.!$%*?&#^~|\\\\\/\{\}\[\]()<>:;"\'=+\s]+$/', $password)) {
        return ['valido' => false, 'error' => 'La contraseña contiene caracteres no permitidos.'];
    }

    // VALIDACIÓN 5: Complejidad (solo si se requiere)
    // REGLA DE SEGURIDAD: Requiere combinación de tipos de caracteres para mayor seguridad
    // LÓGICA: Contraseñas con múltiples tipos de caracteres son más resistentes a ataques
    // Complexity: A strong password includes a mix of uppercase (A-Z), lowercase (a-z), numbers (0-9), and special characters (! @#$%^&*)
    if ($requiere_complejidad) {
        // Requiere: 1 minúscula, 1 mayúscula, 1 número, 1 carácter especial (cualquier símbolo permitido)
        // REGLA: (?=.*[a-z]) = al menos una minúscula
        //        (?=.*[A-Z]) = al menos una mayúscula
        //        (?=.*\d) = al menos un número
        //        (?=.*[^A-Za-z0-9\s]) = al menos un carácter especial (cualquier carácter que no sea letra, número o espacio)
        // LÓGICA: Permite cualquier carácter especial que esté en la lista de caracteres permitidos
        // Nota: El regex de caracteres permitidos ya valida que solo contenga caracteres seguros
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9\s])/', $password)) {
            return ['valido' => false, 'error' => 'La contraseña no cumple con los requisitos de seguridad. Debe contener al menos una minúscula, una mayúscula, un número y un carácter especial (! @#$%^&*).'];
        }
    }

    // Si pasa todas las validaciones, la contraseña es válida
    return ['valido' => true, 'error' => ''];
}

