<?php
/**
 * ========================================================================
 * PANEL DE ADMINISTRACIÓN - Tienda Seda y Lino
 * ========================================================================
 * Panel exclusivo para administradores con acceso total al sistema
 * 
 * FUNCIONALIDADES PRINCIPALES:
 * 1. GESTIÓN DE USUARIOS:
 *    - Crear usuarios de staff (Ventas/Marketing) con contraseña automática
 *    - Modificar datos de usuarios (nombre, apellido, email, rol, contraseña)
 *    - Cambiar roles de usuarios (cliente, ventas, marketing, admin)
 *    - Eliminar usuarios (si no tienen pedidos asociados)
 * 
 * 2. ESTADÍSTICAS:
 *    - Total de usuarios por rol
 * 
 * FUNCIONES DEL ARCHIVO:
 * - crear_usuario_staff(): Crea usuario con rol ventas/marketing y genera contraseña aleatoria
 * - cambiar_rol(): Cambia el rol de un usuario (con validación)
 * - actualizar_usuario(): Actualiza datos personales y/o contraseña de usuario
 * - eliminar_usuario(): Elimina usuario (valida referencias con pedidos)
 * 
 * FUNCIONES JavaScript:
 * - confirmLogout(): Confirma cierre de sesión
 * - validarContrasenaUsuario(): Valida que las contraseñas coincidan antes de enviar
 * 
 * VARIABLES PRINCIPALES:
 * - $id_usuario: ID del administrador actual
 * - $usuario_actual: Datos del usuario administrador
 * - $usuarios: ResultSet con todos los usuarios ordenados por rol
 * - $stats: Estadísticas de usuarios por rol (total, admins, ventas, marketing, clientes)
 * - $mensaje/$mensaje_tipo: Mensajes de feedback
 * 
 * VALIDACIONES IMPORTANTES:
 * - No permite que admin se quite su propio rol
 * - No permite eliminar usuarios con pedidos
 * - Valida emails únicos antes de crear/actualizar usuarios
 * - Valida que exista al menos un administrador en el sistema
 * 
 * TABLAS UTILIZADAS: Usuarios, Movimientos_Stock
 * ACCESO: Solo usuarios con rol 'admin' (mediante requireAdmin())
 * ========================================================================
 */
session_start();

// ============================================================================
// VERIFICACIÓN DE ACCESO - SOLO ADMINISTRADORES
// ============================================================================

// Cargar sistema de autenticación centralizado
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/password_functions.php';
require_once __DIR__ . '/includes/queries/usuario_queries.php';
require_once __DIR__ . '/includes/queries/pedido_queries.php';
require_once __DIR__ . '/includes/admin_functions.php';
require_once __DIR__ . '/includes/sales_functions.php';

// Verificar que el usuario esté logueado y sea admin
requireAdmin();

// Conectar a la base de datos
require_once __DIR__ . '/config/database.php';

// Verificación adicional: validar que el rol en sesión coincide con el rol en BD
// Esto previene manipulación de sesiones - solo usuarios activos pueden acceder
$id_usuario = getCurrentUserId();
$rol_en_bd = obtenerRolUsuario($mysqli, $id_usuario);

if ($rol_en_bd !== null) {
    $rol_en_bd = strtolower(trim($rol_en_bd));
    
    // Si el rol en BD no es admin, cerrar sesión y redirigir
    if ($rol_en_bd !== 'admin') {
        session_destroy();
        header('Location: login.php?error=acceso_denegado');
        exit;
    }
    
    // Actualizar sesión con rol correcto desde BD (por seguridad)
    $_SESSION['rol'] = $rol_en_bd;
} else {
    // Usuario no existe en BD, cerrar sesión
    session_destroy();
    header('Location: login.php?error=usuario_no_valido');
    exit;
}

// Obtener información del usuario actual
$usuario_actual = getCurrentUser();

// Configurar título de la página
$titulo_pagina = 'Panel de Administración';

// ============================================================================
// PROCESAMIENTO DE FORMULARIOS (MENSAJES COMPARTIDOS)
// ============================================================================

// Obtener mensajes de sesión (si hay redirección)
$mensaje = '';
$mensaje_tipo = '';
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $mensaje_tipo = isset($_SESSION['mensaje_tipo']) ? $_SESSION['mensaje_tipo'] : 'success';
    // Limpiar mensaje de sesión después de leerlo
    unset($_SESSION['mensaje']);
    unset($_SESSION['mensaje_tipo']);
}

// ============================================================================
// PROCESAR FORMULARIOS POST
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = false;
    
    // Determinar acción basada en parámetros POST
    if (isset($_POST['crear_usuario_staff'])) {
        $resultado = procesarCreacionUsuarioStaff($mysqli, $_POST);
    } elseif (isset($_POST['cambiar_rol'])) {
        $resultado = procesarCambioRol($mysqli, $_POST, $id_usuario);
    } elseif (isset($_POST['actualizar_usuario'])) {
        $resultado = procesarActualizacionUsuario($mysqli, $_POST, $id_usuario);
    } elseif (isset($_POST['eliminar_usuario_fisico']) || isset($_POST['desactivar_usuario']) || isset($_POST['reactivar_usuario'])) {
        $resultado = procesarEliminacionUsuario($mysqli, $_POST, $id_usuario);
    }
    
    // Si hay resultado, procesar redirección
    if ($resultado !== false) {
        $_SESSION['mensaje'] = $resultado['mensaje'];
        $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
        $redirect_url = construirRedirectUrl('admin.php');
        header('Location: ' . $redirect_url);
        exit;
    }
}


// ============================================================================
// OBTENER TODOS LOS USUARIOS
// ============================================================================

// Obtener filtro de rol desde GET (si existe)
$filtro_rol = isset($_GET['filtro_rol']) && $_GET['filtro_rol'] !== '' ? $_GET['filtro_rol'] : null;

// Validar que el filtro sea un rol válido
$roles_validos = ['cliente', 'ventas', 'marketing', 'admin'];
if ($filtro_rol !== null && !in_array($filtro_rol, $roles_validos, true)) {
    $filtro_rol = null;
}

// Obtener parámetro para mostrar usuarios inactivos
$mostrar_inactivos = isset($_GET['mostrar_inactivos']) && $_GET['mostrar_inactivos'] == '1';

// Obtener usuarios usando función centralizada (con filtro opcional)
$usuarios = obtenerTodosUsuarios($mysqli, $filtro_rol);

// Filtrar usuarios inactivos si el toggle está desactivado
// Por defecto solo mostrar usuarios activos (activo = 1)
if (!$mostrar_inactivos) {
    $usuarios = array_filter($usuarios, function($usuario) {
        $activo = isset($usuario['activo']) ? intval($usuario['activo']) : 1;
        return $activo === 1;
    });
    // Reindexar el array después del filtro
    $usuarios = array_values($usuarios);
}

// Obtener estadísticas de usuarios usando función centralizada
$stats = obtenerEstadisticasUsuarios($mysqli);

// Obtener historial de usuarios (creación y modificación)
$historial_usuarios = obtenerHistorialUsuarios($mysqli);

// ============================================================================
// MANTENIMIENTO: Cancelar pedidos pendientes antiguos (60 días)
// ============================================================================
// Ejecutar cancelación automática de pedidos pendientes con más de 60 días
// Solo se ejecuta cuando no hay POST activo (para evitar ejecución en cada acción)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $cancelaciones = cancelarPedidosPendientesAntiguos($mysqli, 60);
}

?>

<?php include 'includes/header.php'; ?>

    <main>
        <div class="container">
            <!-- Header -->
            <div class="card bg-dark text-white mb-4">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <h1><i class="fas fa-shield-alt me-3"></i>Panel de Administración</h1>
                        <p class="mb-0">Gestión de usuarios, pedidos y productos</p>
                    </div>
                    <div>
                        <a href="perfil.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-user"></i> Mi Perfil
                        </a>
                        <a href="logout.php" class="btn btn-outline-secondary btn-logout">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <a href="#usuarios" class="text-decoration-none">
                        <div class="card shadow-sm h-100 text-center">
                            <div class="card-body">
                                <div class="text-primary mb-2">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                                <h3><?= $stats['total'] ?></h3>
                                <p class="text-muted mb-0">Total Usuarios</p>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <a href="#usuarios" class="text-decoration-none">
                        <div class="card shadow-sm h-100 text-center">
                            <div class="card-body">
                                <div class="text-danger mb-2">
                                    <i class="fas fa-shield-alt fa-2x"></i>
                                </div>
                                <h3><?= $stats['admins'] ?></h3>
                                <p class="text-muted mb-0">Administradores</p>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <a href="#usuarios" class="text-decoration-none">
                        <div class="card shadow-sm h-100 text-center">
                            <div class="card-body">
                                <div class="text-info mb-2">
                                    <i class="fas fa-briefcase fa-2x"></i>
                                </div>
                                <h3><?= $stats['ventas'] + $stats['marketing'] ?></h3>
                                <p class="text-muted mb-0">Staff (Ventas + Marketing)</p>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <a href="#usuarios" class="text-decoration-none">
                        <div class="card shadow-sm h-100 text-center">
                            <div class="card-body">
                                <div class="text-success mb-2">
                                    <i class="fas fa-user-check fa-2x"></i>
                                </div>
                                <h3><?= $stats['clientes'] ?></h3>
                                <p class="text-muted mb-0">Clientes</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- Navegación por pestañas -->
            <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios" type="button" role="tab">
                        <i class="fas fa-users me-2"></i>Gestión de Usuarios
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="crear-usuario-tab" data-bs-toggle="tab" data-bs-target="#crear-usuario" type="button" role="tab">
                        <i class="fas fa-user-plus me-2"></i>Crear Usuario
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="historial-usuarios-tab" data-bs-toggle="tab" data-bs-target="#historial-usuarios" type="button" role="tab">
                        <i class="fas fa-history me-2"></i>Historial de Usuarios
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" href="db-tablas.php" role="tab">
                        <i class="fas fa-database me-2"></i>DB-TABLAS
                    </a>
                </li>
            </ul>

            <div class="tab-content" id="adminTabsContent">
                <!-- Pestaña 1: Gestión de Usuarios -->
                <div class="tab-pane fade show active" id="usuarios" role="tabpanel">
                    <!-- Mensajes -->
                    <?php if ($mensaje): ?>
                    <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show animate-in" role="alert">
                        <?php if ($mensaje_tipo === 'success'): ?>
                            <i class="fas fa-check-circle me-2"></i>
                        <?php elseif ($mensaje_tipo === 'warning'): ?>
                            <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php else: ?>
                            <i class="fas fa-exclamation-circle me-2"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($mensaje) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Filtro por Rol -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <form method="GET" action="admin.php" class="row g-3 align-items-end">
                                <?php if ($mostrar_inactivos): ?>
                                <input type="hidden" name="mostrar_inactivos" value="1">
                                <?php endif; ?>
                                <div class="col-md-4">
                                    <label for="filtro_rol" class="form-label">
                                        <i class="fas fa-filter me-2"></i>Filtrar por Rol
                                    </label>
                                    <select class="form-select" name="filtro_rol" id="filtro_rol">
                                        <option value="">Todos los usuarios</option>
                                        <option value="cliente" <?= $filtro_rol === 'cliente' ? 'selected' : '' ?>>Solo Clientes</option>
                                        <option value="ventas" <?= $filtro_rol === 'ventas' ? 'selected' : '' ?>>Solo Ventas</option>
                                        <option value="marketing" <?= $filtro_rol === 'marketing' ? 'selected' : '' ?>>Solo Marketing</option>
                                        <option value="admin" <?= $filtro_rol === 'admin' ? 'selected' : '' ?>>Solo Administradores</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <?php if ($filtro_rol !== null): ?>
                                    <a href="admin.php<?= $mostrar_inactivos ? '?mostrar_inactivos=1' : '' ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>Limpiar Filtro
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 text-end">
                                    <small class="text-muted">
                                        <?php if ($filtro_rol !== null): ?>
                                            Mostrando: <strong><?= count($usuarios) ?> usuario(s)</strong> con rol <strong><?= strtoupper($filtro_rol) ?></strong>
                                        <?php else: ?>
                                            Total: <strong><?= count($usuarios) ?> usuario(s)</strong>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Tabla de Usuarios -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="mb-0"><i class="fas fa-list me-2"></i>Gestión de Usuarios</h3>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="mostrarInactivos" 
                                       <?= $mostrar_inactivos ? 'checked' : '' ?>
                                       data-toggle-usuarios-inactivos>
                                <label class="form-check-label" for="mostrarInactivos">
                                    <small>Mostrar usuarios inactivos</small>
                                </label>
                            </div>
                        </div>
                        <div class="card-body">
                        <div class="table-responsive">
                            <table class="table sortable-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="sortable">ID</th>
                                        <th class="sortable">Usuario</th>
                                        <th class="sortable">Email</th>
                                        <th class="sortable">Rol Actual</th>
                                        <th class="sortable">A/I</th>
                                        <th class="sortable">Fecha Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                            <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-users me-2"></i>No hay usuarios registrados
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($usuarios as $user): ?>
                            <tr>
                                <td><?= $user['id_usuario'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        
                                        <div>
                                            <strong><?= htmlspecialchars(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? '')) ?></strong>
                                            <?php if (($user['nombre'] ?? null) === null && ($user['apellido'] ?? null) === null): ?>
                                                <span class="badge bg-secondary ms-2">Usuario Eliminado</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($user['email'] ?? 'Usuario eliminado') ?></td>
                                <td>
                                    <?php 
                                    $rol_lower = strtolower($user['rol']);
                                    $badge_class = 'bg-secondary';
                                    if ($rol_lower === 'admin') $badge_class = 'bg-danger';
                                    elseif ($rol_lower === 'ventas') $badge_class = 'bg-info';
                                    elseif ($rol_lower === 'marketing') $badge_class = 'bg-warning';
                                    ?>
                                    <span class="badge <?= $badge_class ?>">
                                        <?= strtoupper($rol_lower) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $activo = intval($user['activo'] ?? 1);
                                    if ($activo === 1): ?>
                                        <span class="badge bg-success" title="Activo">A</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary" title="Inactivo">I</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y', strtotime($user['fecha_registro'])) ?></td>
                                <td>
                                    <button type="button" 
                                            class="btn btn-primary btn-sm me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#cambiarRolModal<?= $user['id_usuario'] ?>"
                                            <?= ($user['id_usuario'] == $id_usuario) ? 'title="No puedes cambiar tu propio rol"' : '' ?>>
                                        <i class="fas fa-pen me-1"></i>Modificar
                                    </button>
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#eliminarUsuarioModal<?= $user['id_usuario'] ?>"
                                            title="Eliminar permanentemente">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    
                                    <!-- Modal para modificar usuario/rol -->
                                    <div class="modal fade" id="cambiarRolModal<?= $user['id_usuario'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Modificar Usuario</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="" onsubmit="return validarContrasenaUsuario(<?= $user['id_usuario'] ?>)">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="usuario_id" value="<?= $user['id_usuario'] ?>">
                                                        <input type="hidden" name="edit_user_id" value="<?= $user['id_usuario'] ?>">
                                                        
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Nombre</label>
                                                                <input type="text" 
                                                                       class="form-control edit-field" 
                                                                       name="edit_nombre" 
                                                                       id="edit_nombre_<?= $user['id_usuario'] ?>"
                                                                       value="<?= htmlspecialchars($user['nombre'] ?? '') ?>" 
                                                                       <?= ($user['nombre'] ?? null) === null ? '' : 'required' ?>
                                                                       minlength="2"
                                                                       maxlength="100"
                                                                       pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]+">
                                                                <div class="invalid-feedback">El nombre debe tener al menos 2 caracteres. Solo se permiten letras, espacios, apóstrofe (') y acento agudo (´).</div>
                                                                <div class="valid-feedback">¡Nombre válido!</div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Apellido</label>
                                                                <input type="text" 
                                                                       class="form-control edit-field" 
                                                                       name="edit_apellido" 
                                                                       id="edit_apellido_<?= $user['id_usuario'] ?>"
                                                                       value="<?= htmlspecialchars($user['apellido'] ?? '') ?>" 
                                                                       <?= ($user['apellido'] ?? null) === null ? '' : 'required' ?>
                                                                       minlength="2"
                                                                       maxlength="100"
                                                                       pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]+">
                                                                <div class="invalid-feedback">El apellido debe tener al menos 2 caracteres. Solo se permiten letras, espacios, apóstrofe (') y acento agudo (´).</div>
                                                                <div class="valid-feedback">¡Apellido válido!</div>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Email</label>
                                                            <input type="email" 
                                                                   class="form-control edit-field" 
                                                                   name="edit_email" 
                                                                   id="edit_email_<?= $user['id_usuario'] ?>"
                                                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                                                                   <?= ($user['email'] ?? null) === null ? '' : 'required' ?>
                                                                   maxlength="150">
                                                            <div class="invalid-feedback">Ingresa un email válido.</div>
                                                            <div class="valid-feedback">¡Email válido!</div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label"><strong>Rol Actual:</strong></label>
                                                            <p>
                                                                <?php 
                                                                $badge_class_modal = 'bg-secondary';
                                                                if ($rol_lower === 'admin') $badge_class_modal = 'bg-danger';
                                                                elseif ($rol_lower === 'ventas') $badge_class_modal = 'bg-info';
                                                                elseif ($rol_lower === 'marketing') $badge_class_modal = 'bg-warning';
                                                                ?>
                                                                <span class="badge <?= $badge_class_modal ?>"><?= strtoupper($rol_lower) ?></span>
                                                            </p>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="nuevo_rol<?= $user['id_usuario'] ?>" class="form-label"><strong>Nuevo Rol:</strong></label>
                                                            <select class="form-select" name="nuevo_rol" id="nuevo_rol<?= $user['id_usuario'] ?>" required>
                                                                <option value="cliente" <?= $rol_lower == 'cliente' ? 'selected' : '' ?>>CLIENTE</option>
                                                                <option value="ventas" <?= $rol_lower == 'ventas' ? 'selected' : '' ?>>VENTAS</option>
                                                                <option value="marketing" <?= $rol_lower == 'marketing' ? 'selected' : '' ?>>MARKETING</option>
                                                                <option value="admin" <?= $rol_lower == 'admin' ? 'selected' : '' ?>>ADMIN</option>
                                                            </select>
                                                            <small class="text-muted">
                                                                <i class="fas fa-info-circle me-1"></i>
                                                                <?php if ($user['id_usuario'] == $id_usuario): ?>
                                                                    <strong class="text-warning">No puedes cambiar tu propio rol de ADMIN</strong>
                                                                <?php else: ?>
                                                                    Selecciona el nuevo rol para este usuario
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label"><strong>Cambiar Contraseña:</strong></label>
                                                            <div class="row g-2">
                                                                <div class="col-md-6">
                                                                    <input type="password" 
                                                                           class="form-control edit-field" 
                                                                           name="nueva_contrasena" 
                                                                           id="nueva_contrasena_<?= $user['id_usuario'] ?>"
                                                                           placeholder="Nueva contraseña" 
                                                                           minlength="6"
                                                                           maxlength="32">
                                                                    <div class="invalid-feedback">La contraseña debe tener entre 6 y 32 caracteres.</div>
                                                                    <div class="valid-feedback">¡Contraseña válida!</div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <input type="password" 
                                                                           class="form-control edit-field" 
                                                                           name="confirmar_contrasena" 
                                                                           id="confirmar_contrasena_<?= $user['id_usuario'] ?>"
                                                                           placeholder="Confirmar contraseña" 
                                                                           minlength="6"
                                                                           maxlength="32">
                                                                    <div class="invalid-feedback" id="password-match-feedback-<?= $user['id_usuario'] ?>" style="display: none;">Las contraseñas no coinciden.</div>
                                                                    <div class="valid-feedback" id="password-match-success-<?= $user['id_usuario'] ?>" style="display: none;">¡Las contraseñas coinciden!</div>
                                                                </div>
                                                            </div>
                                                            <small class="text-muted">
                                                                <i class="fas fa-info-circle me-1"></i>
                                                                Deja en blanco si no quieres cambiar la contraseña. Mínimo 6 caracteres, máximo 32 caracteres.
                                                            </small>
                                                        </div>
                                                        
                                                        <div class="alert alert-info">
                                                            <small>
                                                                <strong>Descripción de roles:</strong><br>
                                                                <strong>CLIENTE:</strong> Usuario estándar con acceso básico<br>
                                                                <strong>VENTAS:</strong> Acceso a gestión de ventas<br>
                                                                <strong>MARKETING:</strong> Acceso a herramientas de marketing<br>
                                                                <strong>ADMIN:</strong> Acceso total al sistema
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <?php 
                                                        // Mostrar botón Desactivar si el usuario está activo
                                                        $activo_usuario = intval($user['activo'] ?? 1);
                                                        if ($activo_usuario === 1 && $user['id_usuario'] != $id_usuario):
                                                        ?>
                                                        <form method="POST" action="" style="display: inline;" onsubmit="return confirm('¿Estás seguro de desactivar esta cuenta? El usuario no podrá iniciar sesión.')">
                                                            <input type="hidden" name="del_user_id" value="<?= $user['id_usuario'] ?>">
                                                            <button type="submit" name="desactivar_usuario" class="btn btn-warning">
                                                                <i class="fas fa-user-slash me-1"></i>Desactivar Cuenta
                                                            </button>
                                                        </form>
                                                        <?php 
                                                        // Mostrar botón Reactivar si el usuario está inactivo
                                                        elseif ($activo_usuario === 0):
                                                        ?>
                                                        <form method="POST" action="" style="display: inline;">
                                                            <input type="hidden" name="del_user_id" value="<?= $user['id_usuario'] ?>">
                                                            <button type="submit" name="reactivar_usuario" class="btn btn-success">
                                                                <i class="fas fa-user-check me-1"></i>Reactivar Cuenta
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>
                                                        <button type="submit" name="actualizar_usuario" class="btn btn-primary"><i class="fas fa-save me-1"></i>Guardar Cambios</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modal eliminar usuario permanentemente -->
                                    <?php 
                                    // Obtener conteo de pedidos del usuario para mostrar advertencia
                                    $total_pedidos_usuario = contarPedidosUsuario($mysqli, $user['id_usuario']);
                                    ?>
                                    <div class="modal fade" id="eliminarUsuarioModal<?= $user['id_usuario'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title">
                                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                                        <?php if ($total_pedidos_usuario > 0): ?>
                                                            Eliminar Usuario
                                                        <?php else: ?>
                                                            Eliminar Usuario Permanentemente
                                                        <?php endif; ?>
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="" id="formEliminarFisico<?= $user['id_usuario'] ?>">
                                                    <div class="modal-body">
                                                        <div class="alert alert-danger" role="alert">
                                                            <h6 class="alert-heading">
                                                                <i class="fas fa-exclamation-triangle me-2"></i><strong>¡ADVERTENCIA!</strong>
                                                            </h6>
                                                            <p class="mb-2">
                                                                <?php if ($total_pedidos_usuario > 0): ?>
                                                                    Estás a punto de <strong>eliminar</strong> al usuario 
                                                                    <strong><?= htmlspecialchars(($user['nombre'] ?? 'Usuario') . ' ' . ($user['apellido'] ?? 'Eliminado')) ?></strong>.
                                                                <?php else: ?>
                                                                    Estás a punto de <strong>eliminar permanentemente</strong> al usuario 
                                                                    <strong><?= htmlspecialchars(($user['nombre'] ?? 'Usuario') . ' ' . ($user['apellido'] ?? 'Eliminado')) ?></strong>.
                                                                <?php endif; ?>
                                                            </p>
                                                            <hr>
                                                            <p class="mb-0">
                                                                <strong>Esta acción es IRREVERSIBLE.</strong>
                                                                <?php if ($total_pedidos_usuario > 0): ?>
                                                                    Todos los datos personales serán eliminados permanentemente. Los pedidos y pagos se conservarán desvinculados para contabilidad.
                                                                <?php else: ?>
                                                                    El usuario será borrado completamente de la base de datos.
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                        
                                                        <?php if ($total_pedidos_usuario > 0): ?>
                                                        <div class="alert alert-warning">
                                                            <h6 class="alert-heading">
                                                                <i class="fas fa-user-shield me-2"></i><strong>Eliminación de Usuario</strong>
                                                            </h6>
                                                            <p class="mb-2">
                                                                Este usuario tiene <strong><?= $total_pedidos_usuario ?> pedido(s)</strong> asociado(s).
                                                            </p>
                                                            <p class="mb-0">
                                                                <strong>Se eliminarán:</strong> Todos los datos personales (nombre, apellido, email, teléfono, dirección, contraseña) serán eliminados permanentemente.
                                                                <br><br>
                                                                <strong>Se conservarán:</strong> Los pedidos y pagos se mantendrán en el sistema para contabilidad, pero desvinculados del usuario eliminado.
                                                            </p>
                                                        </div>
                                                        <?php else: ?>
                                                        <div class="alert alert-info">
                                                            <i class="fas fa-info-circle me-2"></i>
                                                            Este usuario no tiene pedidos asociados. La eliminación es permanente e irreversible.
                                                        </div>
                                                        <?php endif; ?>
                                                        
                                                        <input type="hidden" name="del_user_id" value="<?= $user['id_usuario'] ?>">
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <?php if ($total_pedidos_usuario > 0): ?>
                                                        <button type="submit" name="eliminar_usuario_fisico" class="btn btn-danger">
                                                            <i class="fas fa-trash-alt me-1"></i>Eliminar Usuario
                                                        </button>
                                                        <?php else: ?>
                                                        <button type="submit" name="eliminar_usuario_fisico" class="btn btn-danger">
                                                            <i class="fas fa-trash-alt me-1"></i>Eliminar Permanentemente
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Pestaña 2: Crear Usuario -->
                <div class="tab-pane fade" id="crear-usuario" role="tabpanel">
                    <!-- Crear usuarios -->
                    <div class="card mb-4">
                        <div class="card-body">
                        <h3 class="mb-3"><i class="fas fa-user-plus me-2"></i>Crear Usuario</h3>
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="crear_usuario_staff" value="1">
                            <div class="col-md-3">
                                <label class="form-label">Nombre</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="nombre_staff" 
                                       id="nombre_staff"
                                       required
                                       minlength="2"
                                       maxlength="100"
                                       pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]+">
                                <div class="invalid-feedback">El nombre debe tener al menos 2 caracteres. Solo se permiten letras, espacios, apóstrofe (') y acento agudo (´).</div>
                                <div class="valid-feedback">¡Nombre válido!</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Apellido</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="apellido_staff" 
                                       id="apellido_staff"
                                       required
                                       minlength="2"
                                       maxlength="100"
                                       pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]+">
                                <div class="invalid-feedback">El apellido debe tener al menos 2 caracteres. Solo se permiten letras, espacios, apóstrofe (') y acento agudo (´).</div>
                                <div class="valid-feedback">¡Apellido válido!</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Email</label>
                                <input type="email" 
                                       class="form-control" 
                                       name="email_staff" 
                                       id="email_staff"
                                       required
                                       maxlength="150">
                                <div class="invalid-feedback">Ingresa un email válido.</div>
                                <div class="valid-feedback">¡Email válido!</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Rol</label>
                                <select class="form-select" name="rol_staff" required>
                                    <option value="cliente">Cliente</option>
                                    <option value="ventas">Ventas</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="fas fa-key me-1"></i>Contraseña Temporal
                                </label>
                                <div class="password-input-wrapper">
                                    <input type="password" 
                                           class="form-control" 
                                           name="password_temporal" 
                                           id="password_temporal"
                                           required
                                           minlength="6"
                                           maxlength="255"
                                           placeholder="Mínimo 6 caracteres"
                                           autocomplete="new-password">
                                    <button type="button" class="btn-toggle-password" data-toggle-password="password_temporal" aria-label="Mostrar contraseña">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Mínimo 6 caracteres. El usuario deberá cambiarla al iniciar sesión.
                                </small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="fas fa-key me-1"></i>Confirmar Contraseña Temporal
                                </label>
                                <div class="password-input-wrapper">
                                    <input type="password" 
                                           class="form-control" 
                                           name="confirmar_password_temporal" 
                                           id="confirmar_password_temporal"
                                           required
                                           minlength="6"
                                           maxlength="255"
                                           placeholder="Repite la contraseña temporal"
                                           autocomplete="new-password">
                                    <button type="button" class="btn-toggle-password" data-toggle-password="confirmar_password_temporal" aria-label="Mostrar contraseña">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback" id="password-match-feedback" style="display: none;">
                                    Las contraseñas no coinciden.
                                </div>
                                <div class="valid-feedback" id="password-match-success" style="display: none;">
                                    Las contraseñas coinciden.
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success" data-auto-lock="true" data-lock-time="2000" data-lock-text="Creando usuario...">
                                    <i class="fas fa-save me-2"></i>Crear usuario
                                </button>
                                <small class="text-muted ms-2">La contraseña temporal se mostrará una vez creado el usuario.</small>
                            </div>
                        </form>
                        </div>
                    </div>
                </div>

                <!-- Pestaña 3: Historial de Usuarios -->
                <div class="tab-pane fade" id="historial-usuarios" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="mb-4"><i class="fas fa-history me-2"></i>Historial de Usuarios</h3>
                            <p class="text-muted mb-4">
                                Historial de creación y modificación de usuarios ordenado por fecha (más reciente primero).
                            </p>
                            
                            <div class="table-responsive">
                                <table class="table sortable-table">
                                    <thead class="table-dark">
                                        <tr>
                                            <th class="sortable">Fecha</th>
                                            <th class="sortable">Tipo de Acción</th>
                                            <th class="sortable">ID</th>
                                            <th class="sortable">Usuario</th>
                                            <th class="sortable">Email</th>
                                            <th class="sortable">Rol</th>
                                            <th class="sortable">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                <?php if (empty($historial_usuarios)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-history me-2"></i>No hay historial de usuarios disponible
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($historial_usuarios as $historial): ?>
                                <tr>
                                    <td>
                                        <?php 
                                        // Usar fecha_actualizacion si existe, sino fecha_registro
                                        $fecha_mostrar = $historial['fecha_actualizacion'] ?? $historial['fecha_registro'];
                                        echo date('d/m/Y H:i', strtotime($fecha_mostrar));
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $tipo_accion = $historial['tipo_accion'];
                                        $badge_class = $tipo_accion === 'Creación' ? 'bg-success' : 'bg-info';
                                        ?>
                                        <span class="badge <?= $badge_class ?>">
                                            <i class="fas <?= $tipo_accion === 'Creación' ? 'fa-plus-circle' : 'fa-edit' ?> me-1"></i>
                                            <?= htmlspecialchars($tipo_accion) ?>
                                        </span>
                                    </td>
                                    <td><?= $historial['id_usuario'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars(($historial['nombre'] ?? 'Usuario') . ' ' . ($historial['apellido'] ?? 'Eliminado')) ?></strong>
                                        <?php if (($historial['nombre'] ?? null) === null && ($historial['apellido'] ?? null) === null): ?>
                                            <span class="badge bg-secondary ms-1">Eliminado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($historial['email'] ?? 'Usuario eliminado') ?></td>
                                    <td>
                                        <?php 
                                        $rol_lower = strtolower($historial['rol']);
                                        $badge_class_rol = 'bg-secondary';
                                        if ($rol_lower === 'admin') $badge_class_rol = 'bg-danger';
                                        elseif ($rol_lower === 'ventas') $badge_class_rol = 'bg-info';
                                        elseif ($rol_lower === 'marketing') $badge_class_rol = 'bg-warning';
                                        ?>
                                        <span class="badge <?= $badge_class_rol ?>">
                                            <?= strtoupper($rol_lower) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $activo = intval($historial['activo'] ?? 1);
                                        if ($activo === 1): ?>
                                            <span class="badge bg-success" title="Activo">A</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary" title="Inactivo">I</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Botones de acción -->
            <div class="text-center mt-4">
                <a href="perfil.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-user me-2"></i>Mi Perfil
                </a>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-home me-2"></i>Volver al Inicio
                </a>
                <a href="logout.php" class="btn btn-outline-danger btn-logout">
                    <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                </a>
            </div>
        </div>
    </main>

<?php include 'includes/footer.php'; render_footer(); ?>

