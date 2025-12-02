<?php
/**
 * ========================================================================
 * NAVEGACIÓN COMÚN - Tienda Seda y Lino
 * ========================================================================
 * Componente de navegación reutilizable para todas las páginas
 * 
 * Funcionalidades:
 * - Navegación consistente entre páginas
 * - Control de acceso basado en roles (RBAC)
 * - Indicador de carrito con cantidad
 * - Enlaces de perfil y logout según estado de sesión
 * - Responsive design con Bootstrap
 * 
 * IMPORTANTE - RUTAS RELATIVAS:
 * - Este archivo SIEMPRE usa rutas relativas (sin base_path, sin / inicial)
 * - Las rutas son relativas a la raíz del proyecto
 * - Funciona tanto en desarrollo local como en hosting
 * - Ejemplos: 'index', 'catalogo', 'admin' (no '/index', no 'index.php')
 * 
 * Seguridad:
 * - Validación de roles antes de mostrar enlaces
 * - Sanitización de URLs
 * - Verificación de permisos mediante funciones centralizadas
 * 
 * @package TiendaSedaYLino
 * @version 2.0
 * ========================================================================
 */

// Incluir funciones de autenticación
$auth_check_path = __DIR__ . '/auth_check.php';
if (!file_exists($auth_check_path)) {
    error_log("ERROR: No se pudo encontrar auth_check.php en " . $auth_check_path);
    die("Error crítico: Archivo de autenticación no encontrado. Por favor, contacta al administrador.");
}
require_once $auth_check_path;

// ========================================================================
// CONSTANTES DE ROLES Y CONFIGURACIÓN
// ========================================================================

/** Roles válidos del sistema - Definir solo si no existen */
if (!defined('ROL_ADMIN')) {
    define('ROL_ADMIN', 'admin');
    define('ROL_MARKETING', 'marketing');
    define('ROL_VENTAS', 'ventas');
    define('ROL_CLIENTE', 'cliente');
}

/** Mapeo de roles a paneles de acceso */
$PANELES_POR_ROL = [
    ROL_ADMIN => ['admin'],
    ROL_MARKETING => ['marketing'],
    ROL_VENTAS => ['ventas'],
    ROL_CLIENTE => []
];

// ========================================================================
// VARIABLES DE ESTADO
// ========================================================================

// Verificar si el usuario está logueado
$usuario_logueado = isLoggedIn();

// Obtener rol del usuario actual (sanitizado)
$rol_usuario = $usuario_logueado ? strtolower(trim($_SESSION['rol'] ?? ROL_CLIENTE)) : null;

// Calcular cantidad total de items del carrito desde sesión
// Suma todas las cantidades de variantes (no cuenta tipos únicos)
$num_items_carrito = 0;
if (!empty($_SESSION['carrito']) && is_array($_SESSION['carrito'])) {
    foreach ($_SESSION['carrito'] as $item) {
        // Saltar metadatos del carrito (_meta no es un producto)
        if (is_array($item) && isset($item['cantidad']) && is_numeric($item['cantidad'])) {
            $num_items_carrito += (int)$item['cantidad'];
        }
    }
}

// Detectar página actual para marcar enlace activo
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$current_url = $_SERVER['REQUEST_URI'] ?? '';

// ========================================================================
// FUNCIONES DE UTILIDAD
// ========================================================================

/**
 * Verifica si un enlace está activo (página actual)
 * 
 * @param string $url URL del enlace a verificar
 * @param string $current_page Nombre de la página actual
 * @param string $current_url URL completa actual
 * @return bool True si el enlace está activo, false en caso contrario
 */
function isActiveLink($url, $current_page, $current_url) {
    // Si la URL contiene un hash, verificar por hash
    if (strpos($url, '#') !== false) {
        $hash = explode('#', $url)[1];
        if (strpos($current_url, '#' . $hash) !== false) {
            return true;
        }
    }
    
    // Normalizar: remover .php de current_page si existe
    $page_normalized = str_replace('.php', '', $current_page);
    
    // Verificar por nombre de archivo (sin extensión)
    $page_name = basename(explode('?', explode('#', $url)[0])[0]);
    $page_name = str_replace('.php', '', $page_name);
    
    return ($page_name === $page_normalized);
}

/**
 * Sanitiza una URL para prevenir XSS
 * 
 * @param string $url URL a sanitizar
 * @return string URL sanitizada
 */
function sanitizeUrl($url) {
    // Remover caracteres peligrosos
    $url = filter_var($url, FILTER_SANITIZE_URL);
    
    // Asegurar que sea una ruta relativa válida
    if (empty($url) || strpos($url, 'http') === 0 || strpos($url, '//') === 0) {
        return 'index';
    }
    
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

/**
 * Obtiene los enlaces de panel disponibles según el rol del usuario
 * 
 * @param string|null $rol_usuario Rol del usuario actual
 * @return array Array con información de los paneles disponibles
 */
function obtenerPanelesDisponibles($rol_usuario = null) {
    global $PANELES_POR_ROL;
    
    $paneles = [];
    
    if ($rol_usuario === null) {
        return $paneles;
    }
    
    // Admin solo ve su panel
    if ($rol_usuario === ROL_ADMIN) {
        $paneles[] = [
            'url' => 'admin',
            'titulo' => 'Panel ADMIN',
            'icono' => 'fa-shield-alt',
            'panel' => 'admin'
        ];
        return $paneles;
    }
    
    // Otros roles pueden tener múltiples paneles
    $paneles_permitidos = $PANELES_POR_ROL[$rol_usuario] ?? [];
    
    foreach ($paneles_permitidos as $panel) {
        switch ($panel) {
            case 'marketing':
                $paneles[] = [
                    'url' => 'marketing',
                    'titulo' => 'Panel MARKETING',
                    'icono' => 'fa-bullhorn',
                    'panel' => 'marketing'
                ];
                break;
            case 'ventas':
                $paneles[] = [
                    'url' => 'ventas',
                    'titulo' => 'Panel VENTAS',
                    'icono' => 'fa-briefcase',
                    'panel' => 'ventas'
                ];
                break;
        }
    }
    
    return $paneles;
}
?>

<header>
    <nav class="navbar navbar-expand-lg bg-body-tertiary">
        <div class="container-fluid">
            <a class="navbar-brand nombre-tienda" href="<?= sanitizeUrl('index') ?>">SEDA Y LINO</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav lista-nav">
                    <li class="nav-item">
                        <a class="nav-link link-tienda <?= isActiveLink('index', $current_page, $current_url) ? 'active-page' : '' ?>" href="<?= sanitizeUrl('index') ?>">INICIO</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link link-tienda <?= isActiveLink('nosotros', $current_page, $current_url) ? 'active-page' : '' ?>" href="<?= sanitizeUrl('nosotros') ?>">NOSOTROS</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link link-tienda <?= isActiveLink('catalogo', $current_page, $current_url) ? 'active-page' : '' ?>" href="<?= sanitizeUrl('catalogo') ?>">PRODUCTOS</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link link-tienda <?= (strpos($current_url, '#contacto') !== false || ($current_page === 'index.php' && strpos($current_url, '#contacto') !== false)) ? 'active-page' : '' ?>" href="<?= sanitizeUrl('index') ?>#contacto">CONTACTO</a>
                    </li>
                    
                    <!-- Carrito: disponible para todos los usuarios (logueados y no logueados) -->
                    <li class="nav-item">
                        <a class="nav-link position-relative <?= isActiveLink('carrito', $current_page, $current_url) ? 'active-page' : '' ?>" href="<?= sanitizeUrl('carrito') ?>" title="Carrito">
                            <i class="fas fa-shopping-cart fa-lg"></i>
                            <?php if ($num_items_carrito > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= (int)$num_items_carrito ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <?php if ($usuario_logueado): ?>
                    <!-- Usuario logueado: mostrar acceso a dashboard según rol, perfil y logout -->
                    <?php
                    // Obtener paneles disponibles según el rol del usuario
                    $paneles_disponibles = obtenerPanelesDisponibles($rol_usuario);
                    
                    // Mostrar enlaces de paneles solo si el usuario tiene acceso
                    foreach ($paneles_disponibles as $panel_info):
                        $url_panel = sanitizeUrl($panel_info['url']);
                        $es_activo = isActiveLink($url_panel, $current_page, $current_url);
                    ?>
                    <li class="nav-item">
                        <a class="nav-link link-tienda <?= $es_activo ? 'active-page' : '' ?>" 
                           href="<?= $url_panel ?>" 
                           title="Ir al <?= htmlspecialchars($panel_info['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fas <?= htmlspecialchars($panel_info['icono'], ENT_QUOTES, 'UTF-8') ?> me-1"></i><?= htmlspecialchars($panel_info['titulo'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    
                    <!-- Perfil: disponible para todos los usuarios logueados -->
                    <?php if (canAccess('perfil')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= sanitizeUrl('perfil') ?>" title="Mi Perfil">
                            <img src="iconos/avatar-usuario.png" alt="icono de avatar de usuario">
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Logout: disponible para todos los usuarios logueados -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= sanitizeUrl('logout') ?>" title="Cerrar Sesión">
                            <i class="fas fa-sign-out-alt fa-lg"></i>
                        </a>
                    </li>
                    <?php else: ?>
                    <!-- Usuario no logueado: mostrar solo login -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= sanitizeUrl('login') ?>" title="Iniciar Sesión">
                            <img src="iconos/avatar-usuario.png" alt="icono de avatar de usuario">
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>
