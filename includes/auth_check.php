<?php
/**
 * ========================================================================
 * VERIFICACIÓN DE AUTENTICACIÓN - Tienda Seda y Lino
 * ========================================================================
 * Sistema centralizado de verificación de sesiones y roles
 * 
 * Funcionalidades:
 * - Verificación de usuario logueado
 * - Verificación de roles específicos
 * - Redirección automática según estado
 * - Configuración centralizada de emails admin
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica si el usuario está logueado
 * @return bool True si está logueado, false en caso contrario
 */
function isLoggedIn() {
    return isset($_SESSION['id_usuario']) && !empty($_SESSION['id_usuario']);
}

/**
 * Verifica si el usuario tiene rol de administrador
 * @return bool True si es admin, false en caso contrario
 */
function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Emails de admin permitidos explícitamente
    $emails_admin_permitidos = ['admin@sedaylino.com', 'admin@test.com'];
    
    $es_admin_por_rol = isset($_SESSION['rol']) && strtolower($_SESSION['rol']) === 'admin';
    $es_admin_por_email = isset($_SESSION['email']) && in_array(strtolower($_SESSION['email']), $emails_admin_permitidos, true);
    
    return $es_admin_por_rol || $es_admin_por_email;
}

/**
 * Verifica si el usuario tiene un rol específico
 * @param string $rol Rol a verificar ('cliente', 'ventas', 'marketing', 'admin')
 * @return bool True si tiene el rol, false en caso contrario
 */
function hasRole($rol) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $rol_sesion = strtolower($_SESSION['rol'] ?? '');
    $rol_solicitado = strtolower($rol);
    
    // Verificar rol por sesión
    if ($rol_sesion === $rol_solicitado) {
        return true;
    }
    
    // Verificar emails especiales para marketing
    if ($rol_solicitado === 'marketing') {
        $emails_marketing_permitidos = ['marketing@test.com'];
        $es_marketing_por_email = isset($_SESSION['email']) && in_array(strtolower($_SESSION['email']), $emails_marketing_permitidos, true);
        return $es_marketing_por_email;
    }
    
    // Verificar emails especiales para ventas
    if ($rol_solicitado === 'ventas') {
        $emails_ventas_permitidos = ['ventas@test.com'];
        $es_ventas_por_email = isset($_SESSION['email']) && in_array(strtolower($_SESSION['email']), $emails_ventas_permitidos, true);
        return $es_ventas_por_email;
    }
    
    return false;
}

/**
 * Verifica si el usuario tiene rol de marketing
 * @return bool True si es marketing, false en caso contrario
 */
function isMarketing() {
    return hasRole('marketing');
}

/**
 * Verifica si el usuario tiene rol de ventas
 * @return bool True si es ventas, false en caso contrario
 */
function isVentas() {
    return hasRole('ventas');
}

/**
 * Requiere que el usuario esté logueado, redirige al login si no lo está
 * @param string $redirect_url URL de redirección en caso de no estar logueado
 */
function requireLogin($redirect_url = 'login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Requiere que el usuario sea administrador, redirige si no lo es
 * @param string $redirect_url URL de redirección en caso de no ser admin
 */
function requireAdmin($redirect_url = 'index.php') {
    requireLogin();
    
    if (!isAdmin()) {
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Requiere que el usuario tenga un rol específico
 * @param string $rol Rol requerido
 * @param string $redirect_url URL de redirección en caso de no tener el rol
 */
function requireRole($rol, $redirect_url = 'index.php') {
    requireLogin();
    
    if (!hasRole($rol) && !isAdmin()) { // Los admins pueden acceder a todo
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Obtiene información del usuario actual
 * @return array|null Array con datos del usuario o null si no está logueado
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id_usuario' => $_SESSION['id_usuario'],
        'nombre' => $_SESSION['nombre'] ?? '',
        'apellido' => $_SESSION['apellido'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'rol' => $_SESSION['rol'] ?? 'cliente'
    ];
}

/**
 * Obtiene el ID del usuario actual
 * @return int|null ID del usuario o null si no está logueado
 */
function getCurrentUserId() {
    return isLoggedIn() ? (int)$_SESSION['id_usuario'] : null;
}

/**
 * Obtiene el nombre completo del usuario actual
 * @return string Nombre completo o cadena vacía si no está logueado
 */
function getCurrentUserName() {
    if (!isLoggedIn()) {
        return '';
    }
    
    $nombre = $_SESSION['nombre'] ?? '';
    $apellido = $_SESSION['apellido'] ?? '';
    
    return trim($nombre . ' ' . $apellido);
}

/**
 * Verifica si el usuario puede acceder a una funcionalidad específica
 * @param string $funcionalidad Funcionalidad a verificar
 * @return bool True si puede acceder, false en caso contrario
 */
function canAccess($funcionalidad) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Los administradores pueden acceder a todo
    if (isAdmin()) {
        return true;
    }
    
    // Definir permisos por funcionalidad
    $permisos = [
        'admin_panel' => ['admin'],
        'ventas' => ['admin', 'ventas'],
        'marketing' => ['admin', 'marketing'],
        'perfil' => ['cliente', 'admin', 'ventas', 'marketing'],
        'carrito' => ['cliente', 'admin', 'ventas', 'marketing']
    ];
    
    $rol_usuario = strtolower($_SESSION['rol'] ?? 'cliente');
    
    return isset($permisos[$funcionalidad]) && in_array($rol_usuario, $permisos[$funcionalidad]);
}
