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
// NOTA: Esta verificación previene múltiples llamadas a session_start()
// que causarían warnings. Otros archivos (header.php, session_functions.php)
// también verifican session_status() antes de iniciar sesión.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica si el usuario está logueado
 * @return bool True si está logueado, false en caso contrario
 */
function isLoggedIn() {
    // LÓGICA DE NEGOCIO: Verifica si existe una sesión de usuario activa.
    // REGLA DE NEGOCIO: Un usuario está logueado si tiene un ID de usuario válido en la sesión.
    // LÓGICA: La sesión se establece tras un login exitoso con contraseña válida.
    // Verifica tanto que exista la variable de sesión como que no esté vacía.
    return isset($_SESSION['id_usuario']) && !empty($_SESSION['id_usuario']);
}

/**
 * Verifica si el usuario tiene rol de administrador
 * IMPORTANTE: Solo verifica por rol en sesión, no por email
 * La autenticación debe hacerse siempre con contraseña válida en login.php
 * @return bool True si es admin, false en caso contrario
 */
function isAdmin() {
    // LÓGICA DE NEGOCIO: Verifica si el usuario actual tiene rol de administrador.
    // REGLA DE SEGURIDAD: Solo se verifica por rol en sesión, nunca por email (inseguro).
    // LÓGICA: El rol se establece en la sesión tras un login válido con contraseña correcta.
    
    // Primero verificar que el usuario esté logueado
    // REGLA: Si no hay sesión, no puede ser admin
    if (!isLoggedIn()) {
        return false;
    }
    
    // Verificar rol en sesión (normalizado a minúsculas para comparación consistente)
    // REGLA DE SEGURIDAD: NO verificar por email directamente - esto es inseguro
    // LÓGICA: El rol en sesión es establecido por el sistema tras autenticación válida
    $es_admin_por_rol = isset($_SESSION['rol']) && strtolower($_SESSION['rol']) === 'admin';
    
    return $es_admin_por_rol;
}

/**
 * Verifica si el usuario tiene un rol específico
 * IMPORTANTE: Solo verifica por rol en sesión, no por email
 * La autenticación debe hacerse siempre con contraseña válida en login.php
 * @param string $rol Rol a verificar ('cliente', 'ventas', 'marketing', 'admin')
 * @return bool True si tiene el rol, false en caso contrario
 */
function hasRole($rol) {
    // LÓGICA DE NEGOCIO: Verifica si el usuario tiene un rol específico.
    // REGLA DE SEGURIDAD: Solo se verifica por rol en sesión, establecido tras login válido.
    // LÓGICA: Permite verificación flexible de cualquier rol del sistema (cliente, ventas, marketing, admin).
    
    // Primero verificar que el usuario esté logueado
    // REGLA: Si no hay sesión, no tiene ningún rol
    if (!isLoggedIn()) {
        return false;
    }
    
    // Normalizar ambos roles a minúsculas para comparación case-insensitive
    // LÓGICA: Evita problemas de comparación por diferencias de mayúsculas/minúsculas
    $rol_sesion = strtolower($_SESSION['rol'] ?? '');
    $rol_solicitado = strtolower($rol);
    
    // Comparar rol de sesión con rol solicitado
    // REGLA: Solo verificar por rol en sesión (que se establece tras login válido)
    // LÓGICA: La sesión contiene el rol real del usuario autenticado
    return $rol_sesion === $rol_solicitado;
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
 * Verifica si el usuario tiene rol de cliente
 * @return bool True si es cliente, false en caso contrario
 */
function isCliente() {
    return hasRole('cliente');
}

/**
 * Requiere que el usuario esté logueado, redirige al login si no lo está
 * @param string $redirect_url URL de redirección en caso de no estar logueado
 */
function requireLogin($redirect_url = 'login.php') {
    // LÓGICA DE NEGOCIO: Control de acceso que requiere sesión activa.
    // REGLA DE SEGURIDAD: Si el usuario no está logueado, se redirige al login.
    // LÓGICA: Protege páginas que requieren autenticación (perfil, paneles, etc.).
    
    // Verificar si el usuario está logueado
    // REGLA: Si no hay sesión activa, redirigir al login
    if (!isLoggedIn()) {
        // Redirección automática para prevenir acceso no autorizado
        // LÓGICA: Usa header() y exit para detener la ejecución inmediatamente
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Requiere que el usuario sea administrador, redirige si no lo es
 * @param string $redirect_url URL de redirección en caso de no ser admin
 */
function requireAdmin($redirect_url = 'index.php') {
    // LÓGICA DE NEGOCIO: Control de acceso que requiere rol de administrador.
    // REGLA DE SEGURIDAD: Solo administradores pueden acceder a páginas protegidas.
    // LÓGICA: Protege el panel de administración y funciones críticas del sistema.
    
    // Primero verificar que el usuario esté logueado
    // REGLA: Debe estar autenticado antes de verificar rol
    requireLogin();
    
    // Verificar si el usuario es administrador
    // REGLA: Si no es admin, redirigir a página principal (o URL especificada)
    if (!isAdmin()) {
        // Redirección automática para prevenir acceso no autorizado
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
    // LÓGICA DE NEGOCIO: Control de acceso que requiere un rol específico.
    // REGLA DE SEGURIDAD: Los administradores solo pueden acceder a su panel admin.
    // REGLA DE NEGOCIO: Cada rol tiene acceso solo a su panel correspondiente.
    // LÓGICA: Previene que admins accedan a paneles de otros roles (marketing, ventas).
    
    // Primero verificar que el usuario esté logueado
    requireLogin();
    
    // REGLA DE SEGURIDAD: Los admins SOLO pueden acceder a su panel admin
    // LÓGICA: Si un admin intenta acceder a otro panel (marketing o ventas), bloquear acceso
    // Esto previene confusión y mantiene la separación de responsabilidades
    if (isAdmin() && $rol !== 'admin') {
        // Redirigir a admin.php si intenta acceder a marketing o ventas
        header("Location: admin.php");
        exit;
    }
    
    // Si no es admin, verificar que tenga el rol requerido
    // REGLA: Solo usuarios con el rol específico pueden acceder
    // LÓGICA: Si es admin y el rol es admin, permitir acceso (ya se validó arriba que isAdmin() es true)
    if (!isAdmin() && !hasRole($rol)) {
        // Redirección automática si no tiene el rol requerido
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Redirige al usuario según su rol
 * 
 * Esta función centraliza la lógica de redirección según rol para evitar duplicación de código.
 * Redirige a la página correspondiente según el rol del usuario.
 * 
 * @param string $rol Rol del usuario ('admin', 'marketing', 'ventas', o cualquier otro para cliente)
 * @return void Esta función termina la ejecución con exit después de redirigir
 */
function redirigirSegunRol($rol) {
    // LÓGICA DE NEGOCIO: Redirige al usuario a su panel correspondiente según su rol.
    // REGLA DE NEGOCIO: Cada rol tiene una página específica (admin.php, marketing.php, ventas.php, index.php para clientes).
    // LÓGICA: Centraliza la lógica de redirección para mantener consistencia y evitar código duplicado.
    
    // Normalizar rol a minúsculas para comparación consistente
    $rol_normalizado = strtolower($rol ?? 'cliente');
    
    // Guardar sesión antes de redirigir para asegurar que los cambios se persistan
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    // Redirigir según rol
    switch ($rol_normalizado) {
        case 'admin':
            header('Location: admin.php', true, 302);
            exit;
        case 'marketing':
            header('Location: marketing.php', true, 302);
            exit;
        case 'ventas':
            header('Location: ventas.php', true, 302);
            exit;
        default:
            // Cliente o cualquier otro rol va al inicio
            header('Location: index.php', true, 302);
            exit;
    }
}

/**
 * Obtiene información del usuario actual
 * @return array|null Array con datos del usuario o null si no está logueado
 */
function getCurrentUser() {
    // LÓGICA DE NEGOCIO: Obtiene información completa del usuario actual desde la sesión.
    // REGLA: Solo retorna datos si el usuario está logueado.
    // LÓGICA: Centraliza el acceso a datos del usuario para evitar accesos directos a $_SESSION.
    
    // Verificar que el usuario esté logueado
    // REGLA: Si no hay sesión, retornar null (no hay usuario)
    if (!isLoggedIn()) {
        return null;
    }
    
    // Retornar array con datos del usuario desde la sesión
    // REGLA: Usar valores por defecto para campos opcionales (nombre, apellido, email, rol)
    // LÓGICA: Los datos se establecen en la sesión tras login exitoso
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
    // LÓGICA DE NEGOCIO: Obtiene el ID del usuario actual desde la sesión.
    // REGLA: Solo retorna ID si el usuario está logueado.
    // LÓGICA: Función helper para obtener rápidamente el ID sin acceder directamente a $_SESSION.
    
    // Verificar si está logueado y retornar ID convertido a entero
    // REGLA: Si no hay sesión, retornar null
    // LÓGICA: El ID se convierte a entero para garantizar tipo correcto
    return isLoggedIn() ? (int)$_SESSION['id_usuario'] : null;
}

/**
 * Obtiene el nombre completo del usuario actual
 * @return string Nombre completo o cadena vacía si no está logueado
 */
function getCurrentUserName() {
    // LÓGICA DE NEGOCIO: Obtiene el nombre completo del usuario actual.
    // REGLA: Solo retorna nombre si el usuario está logueado.
    // LÓGICA: Combina nombre y apellido de la sesión para mostrar nombre completo.
    
    // Verificar que el usuario esté logueado
    // REGLA: Si no hay sesión, retornar cadena vacía
    if (!isLoggedIn()) {
        return '';
    }
    
    // Obtener nombre y apellido de la sesión (con valores por defecto vacíos)
    $nombre = $_SESSION['nombre'] ?? '';
    $apellido = $_SESSION['apellido'] ?? '';
    
    // Combinar y limpiar espacios adicionales
    // LÓGICA: Usa trim() para remover espacios al inicio/final del nombre completo
    return trim($nombre . ' ' . $apellido);
}

/**
 * Verifica si el usuario puede acceder a una funcionalidad específica
 * @param string $funcionalidad Funcionalidad a verificar
 * @return bool True si puede acceder, false en caso contrario
 */
function canAccess($funcionalidad) {
    // LÓGICA DE NEGOCIO: Verifica si el usuario puede acceder a una funcionalidad específica.
    // REGLA DE NEGOCIO: Los administradores tienen acceso a todas las funcionalidades.
    // REGLA DE SEGURIDAD: Cada funcionalidad tiene una lista de roles permitidos.
    // LÓGICA: Sistema de permisos basado en roles (RBAC - Role-Based Access Control).
    
    // Verificar que el usuario esté logueado
    // REGLA: Si no hay sesión, no puede acceder a ninguna funcionalidad
    if (!isLoggedIn()) {
        return false;
    }
    
    // REGLA DE NEGOCIO: Los administradores pueden acceder a todo
    // LÓGICA: Los admins tienen privilegios completos por diseño del sistema
    if (isAdmin()) {
        return true;
    }
    
    // Definir permisos por funcionalidad (matriz de roles permitidos)
    // REGLA: Cada funcionalidad tiene una lista específica de roles que pueden acceder
    // LÓGICA: admin_panel solo para admins, ventas para admin y ventas, etc.
    $permisos = [
        'admin_panel' => ['admin'],  // Solo admin
        'ventas' => ['admin', 'ventas'],  // Admin y ventas
        'marketing' => ['admin', 'marketing'],  // Admin y marketing
        'perfil' => ['cliente', 'admin', 'ventas', 'marketing'],  // Todos los roles
        'carrito' => ['cliente', 'admin', 'ventas', 'marketing']  // Todos los roles
    ];
    
    // Obtener rol del usuario desde sesión (normalizado a minúsculas)
    $rol_usuario = strtolower($_SESSION['rol'] ?? 'cliente');
    
    // Verificar si la funcionalidad existe y si el rol del usuario está en la lista de permitidos
    // REGLA: La funcionalidad debe existir en $permisos y el rol debe estar en su lista
    return isset($permisos[$funcionalidad]) && in_array($rol_usuario, $permisos[$funcionalidad]);
}
