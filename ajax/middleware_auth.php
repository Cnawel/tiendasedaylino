<?php
/**
 * ========================================================================
 * MIDDLEWARE DE AUTENTICACIÓN PARA AJAX - Tienda Seda y Lino
 * ========================================================================
 * Sistema de validación de autenticación y roles para endpoints AJAX
 * 
 * Funciones principales:
 * - requireRoleAjax(): Requiere rol específico para endpoint AJAX
 * - requireLoginAjax(): Requiere solo estar logueado
 * - requireAdminAjax(): Requiere rol de administrador
 * - errorAjax(): Envía respuesta de error JSON y termina ejecución
 * 
 * USO:
 * Incluir al inicio de cada archivo AJAX que requiera autenticación:
 * 
 * ```php
 * require_once __DIR__ . '/middleware_auth.php';
 * requireRoleAjax('ventas'); // o 'admin', 'marketing', etc.
 * ```
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

// Incluir sistema de autenticación
$auth_check_path = __DIR__ . '/../includes/auth_check.php';
if (!file_exists($auth_check_path)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Sistema de autenticación no disponible'
    ]);
    exit;
}
require_once $auth_check_path;

/**
 * Envía respuesta de error JSON y termina la ejecución
 * 
 * @param string $mensaje - Mensaje de error
 * @param int $codigo_http - Código HTTP (default: 400)
 * @param array $data_adicional - Datos adicionales para incluir en la respuesta (opcional)
 * @return void (termina la ejecución)
 */
function errorAjax($mensaje, $codigo_http = 400, $data_adicional = []) {
    http_response_code($codigo_http);
    header('Content-Type: application/json');
    
    $respuesta = [
        'success' => false,
        'error' => $mensaje
    ];
    
    // Agregar datos adicionales si se proporcionan
    if (!empty($data_adicional)) {
        $respuesta = array_merge($respuesta, $data_adicional);
    }
    
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Envía respuesta de éxito JSON
 * 
 * @param array $data - Datos a enviar
 * @param string $mensaje - Mensaje de éxito (opcional)
 * @return void (termina la ejecución)
 */
function exitoAjax($data = [], $mensaje = null) {
    http_response_code(200);
    header('Content-Type: application/json');
    
    $respuesta = [
        'success' => true
    ];
    
    if ($mensaje !== null) {
        $respuesta['message'] = $mensaje;
    }
    
    if (!empty($data)) {
        $respuesta['data'] = $data;
    }
    
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Requiere que el usuario esté logueado para endpoint AJAX
 * Si no está logueado, retorna 401 Unauthorized y termina
 * 
 * @return void (termina la ejecución si no está logueado)
 */
function requireLoginAjax() {
    if (!isLoggedIn()) {
        errorAjax(
            'No autenticado. Debes iniciar sesión para realizar esta acción.',
            401,
            ['redirect' => 'login.php']
        );
    }
}

/**
 * Requiere que el usuario sea administrador para endpoint AJAX
 * Si no es admin, retorna 403 Forbidden y termina
 * 
 * @return void (termina la ejecución si no es admin)
 */
function requireAdminAjax() {
    // Primero verificar que esté logueado
    requireLoginAjax();
    
    // Verificar que sea admin
    if (!isAdmin()) {
        errorAjax(
            'Acceso denegado. No tienes permisos de administrador para realizar esta acción.',
            403,
            ['required_role' => 'admin']
        );
    }
}

/**
 * Requiere que el usuario tenga un rol específico para endpoint AJAX
 * Si no tiene el rol, retorna 403 Forbidden y termina
 * 
 * REGLA ESPECIAL: Los administradores SOLO pueden acceder a endpoints de admin
 * Si un admin intenta acceder a endpoint de marketing/ventas, se deniega el acceso
 * 
 * @param string $rol_requerido - Rol requerido ('cliente', 'ventas', 'marketing', 'admin')
 * @return void (termina la ejecución si no tiene el rol)
 */
function requireRoleAjax($rol_requerido) {
    // Normalizar rol
    $rol_requerido = strtolower(trim($rol_requerido));
    
    // Verificar que el rol sea válido
    $roles_validos = ['cliente', 'ventas', 'marketing', 'admin'];
    if (!in_array($rol_requerido, $roles_validos)) {
        errorAjax(
            'Rol inválido especificado en la validación.',
            500,
            ['rol_especificado' => $rol_requerido]
        );
    }
    
    // Primero verificar que esté logueado
    requireLoginAjax();
    
    // REGLA ESPECIAL: Los admins SOLO pueden acceder a su panel admin
    // Si un admin intenta acceder a otro panel (marketing o ventas), bloquear acceso
    if (isAdmin() && $rol_requerido !== 'admin') {
        errorAjax(
            'Acceso denegado. Los administradores solo pueden acceder a endpoints de administración.',
            403,
            [
                'required_role' => $rol_requerido,
                'user_role' => 'admin',
                'reason' => 'Los administradores tienen acceso limitado a su panel específico'
            ]
        );
    }
    
    // Verificar que tenga el rol requerido
    if (!hasRole($rol_requerido)) {
        $rol_usuario = getUserRole() ?? 'desconocido';
        errorAjax(
            "Acceso denegado. Se requiere rol '$rol_requerido' para realizar esta acción.",
            403,
            [
                'required_role' => $rol_requerido,
                'user_role' => $rol_usuario
            ]
        );
    }
    
    // Si llegamos aquí, el usuario tiene el rol correcto
    // No hacemos nada, el script AJAX puede continuar
}

/**
 * Valida que la petición sea POST
 * Si no es POST, retorna 405 Method Not Allowed y termina
 * 
 * @return void (termina la ejecución si no es POST)
 */
function requirePostAjax() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorAjax(
            'Método no permitido. Esta acción solo acepta peticiones POST.',
            405,
            ['method_used' => $_SERVER['REQUEST_METHOD']]
        );
    }
}

/**
 * Valida que la petición sea GET
 * Si no es GET, retorna 405 Method Not Allowed y termina
 * 
 * @return void (termina la ejecución si no es GET)
 */
function requireGetAjax() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        errorAjax(
            'Método no permitido. Esta acción solo acepta peticiones GET.',
            405,
            ['method_used' => $_SERVER['REQUEST_METHOD']]
        );
    }
}

/**
 * Valida que la petición venga de un AJAX (verifica headers)
 * Si no es AJAX, retorna 400 Bad Request y termina
 * 
 * @return void (termina la ejecución si no es AJAX)
 */
function requireAjaxRequest() {
    $es_ajax = 
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if (!$es_ajax) {
        errorAjax(
            'Esta acción solo acepta peticiones AJAX.',
            400,
            ['reason' => 'Missing X-Requested-With header']
        );
    }
}

/**
 * Obtiene parámetro POST de forma segura
 * Valida que exista y no esté vacío
 * 
 * @param string $nombre - Nombre del parámetro
 * @param bool $requerido - Si es requerido (default: true)
 * @param mixed $default - Valor por defecto si no existe (default: null)
 * @return mixed - Valor del parámetro o default
 */
function getPostParam($nombre, $requerido = true, $default = null) {
    if (!isset($_POST[$nombre]) || trim($_POST[$nombre]) === '') {
        if ($requerido) {
            errorAjax(
                "Parámetro requerido faltante: $nombre",
                400,
                ['missing_param' => $nombre]
            );
        }
        return $default;
    }
    
    return $_POST[$nombre];
}

/**
 * Obtiene parámetro GET de forma segura
 * Valida que exista y no esté vacío
 * 
 * @param string $nombre - Nombre del parámetro
 * @param bool $requerido - Si es requerido (default: true)
 * @param mixed $default - Valor por defecto si no existe (default: null)
 * @return mixed - Valor del parámetro o default
 */
function getGetParam($nombre, $requerido = true, $default = null) {
    if (!isset($_GET[$nombre]) || trim($_GET[$nombre]) === '') {
        if ($requerido) {
            errorAjax(
                "Parámetro requerido faltante: $nombre",
                400,
                ['missing_param' => $nombre]
            );
        }
        return $default;
    }
    
    return $_GET[$nombre];
}

/**
 * Valida token CSRF (si el sistema lo implementa)
 * Por ahora es un placeholder para futuras mejoras de seguridad
 * 
 * @return void
 */
function validarCsrfToken() {
    // Placeholder para validación CSRF futura
    // Por ahora no hace nada pero está lista para implementarse
    return true;
}

// Establecer header de respuesta JSON por defecto
header('Content-Type: application/json; charset=utf-8');

// Deshabilitar cualquier output previo que pueda romper JSON
if (ob_get_level()) {
    ob_clean();
}


