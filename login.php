<?php
/**
 * ========================================================================
 * LOGIN - Tienda Seda y Lino
 * ========================================================================
 * Página de inicio de sesión con:
 * - Validaciones HTML básicas antes de consultar DB
 * - Verificación de contraseña usando password_verify()
 * - Redirección según rol del usuario
 * - Links a registro y recuperar contraseña
 * - Bloqueo de cuenta después de 10 intentos fallidos (por email específico)
 * ========================================================================
 */

// ========================================================================
// INICIALIZACIÓN DE SESIÓN
// ========================================================================
// Iniciar sesión antes de cualquier uso de $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inicializar carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// ========================================================================
// CARGAR DEPENDENCIAS
// ========================================================================
// Cargar funciones necesarias (usar rutas absolutas con __DIR__)
require_once __DIR__ . '/includes/password_functions.php';
// NOTA: usuario_queries.php ya está incluido por admin_functions.php
require_once __DIR__ . '/includes/queries/perfil_queries.php';
require_once __DIR__ . '/includes/security_functions.php';
require_once __DIR__ . '/includes/admin_functions.php'; // Incluye usuario_queries.php
require_once __DIR__ . '/includes/validation_functions.php';
require_once __DIR__ . '/includes/auth_check.php';

// Configurar título de la página
$titulo_pagina = 'Iniciar Sesión';

// Variables para mensajes y errores
$errores = [];
$mensaje = '';
$mensaje_tipo = '';
$mostrar_modal_reactivacion = false;

// Verificar si el usuario ya está logueado, redirigir según su rol
if (!empty($_SESSION['id_usuario'])) {
    $rol_sesion = $_SESSION['rol'] ?? 'cliente';
    redirigirSegunRol($rol_sesion);
}

// Verificar si hay datos de reactivación pendiente (para mostrar modal)
if (isset($_SESSION['usuario_reactivacion']) && !empty($_SESSION['usuario_reactivacion'])) {
    $mostrar_modal_reactivacion = true;
}

// Verificar mensaje de registro exitoso desde sesión
if (isset($_SESSION['mensaje_registro'])) {
    $mensaje = $_SESSION['mensaje_registro'];
    $mensaje_tipo = isset($_SESSION['mensaje_registro_tipo']) ? $_SESSION['mensaje_registro_tipo'] : 'success';
    // Limpiar mensaje de sesión después de leerlo
    unset($_SESSION['mensaje_registro']);
    unset($_SESSION['mensaje_registro_tipo']);
}

// ========================================================================
// PROCESAR REACTIVACIÓN DE CUENTA (antes del login normal)
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivar_cuenta'])) {
    // Validar que hay datos de usuario temporal en sesión
    if (!isset($_SESSION['usuario_reactivacion']) || empty($_SESSION['usuario_reactivacion'])) {
        $errores['general'] = 'Sesión de reactivación no válida. Por favor, intenta iniciar sesión nuevamente.';
    } else {
        $usuario_reactivacion = $_SESSION['usuario_reactivacion'];
        $id_usuario = intval($usuario_reactivacion['id_usuario'] ?? 0);
        
        if ($id_usuario <= 0) {
            $errores['general'] = 'ID de usuario inválido para reactivación.';
        } else {
            // Conectar a la base de datos
            require_once __DIR__ . '/config/database.php';
            configurarConexionBD($mysqli);
            
            // Reactivar usuario
            if (reactivarUsuario($mysqli, $id_usuario)) {
                // Limpiar datos temporales de reactivación
                unset($_SESSION['usuario_reactivacion']);
                
                // Establecer variables de sesión
                $_SESSION['id_usuario'] = $usuario_reactivacion['id_usuario'];
                $_SESSION['nombre'] = $usuario_reactivacion['nombre'];
                $_SESSION['apellido'] = $usuario_reactivacion['apellido'];
                $_SESSION['email'] = $usuario_reactivacion['email'];
                $_SESSION['rol'] = strtolower($usuario_reactivacion['rol']);
                
                // Regenerar ID de sesión después de autenticación
                session_regenerate_id(true);
                
                // Redirigir a perfil.php (no usar redirigirSegunRol)
                header('Location: perfil.php', true, 302);
                exit;
            } else {
                $errores['general'] = 'Error al reactivar la cuenta. Por favor, intenta nuevamente.';
            }
        }
    }
}

// ========================================================================
// PROCESAR FORMULARIO DE LOGIN
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['reactivar_cuenta'])) {
    
    // Limpiar datos de reactivación previos si existen (nuevo intento de login)
    if (isset($_SESSION['usuario_reactivacion'])) {
        unset($_SESSION['usuario_reactivacion']);
    }
    
    // Validar EMAIL usando función centralizada
    $email_raw = $_POST['email'] ?? '';
    $validacion_email = validarEmail($email_raw);
    
    if (!$validacion_email['valido']) {
        $errores['email'] = $validacion_email['error'];
    } else {
        // Normalizar email usando función centralizada (una sola vez)
        // Este email normalizado se usará en todas las funciones siguientes
        $email = normalizarEmail($email_raw);
    }
    
    // Validación HTML básica de CONTRASEÑA
    // IMPORTANTE: NO usar trim() en password - puede cambiar la contraseña
    // Preservar password original inmediatamente para verificación
    $password_original = $_POST['password'] ?? '';
    $password = $password_original;
    
    if (empty($password) || strlen($password) === 0) {
        $errores['password'] = 'La contraseña es obligatoria.';
    } elseif (strlen($password) < 6) {
        $errores['password'] = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif (strlen($password) > 32) {
        $errores['password'] = 'La contraseña no puede exceder 32 caracteres.';
    }
    
    // Si no hay errores de validación HTML, verificar bloqueo y consultar DB
    if (empty($errores)) {
        
        // Email ya está normalizado desde arriba, usar directamente
        // Verificar bloqueo de intentos fallidos (10 intentos para login, bloqueo por email)
        $verificacion_bloqueo = verificarIntentosFormulario($email, 'login');
        if (!$verificacion_bloqueo['permitido']) {
            $errores['general'] = $verificacion_bloqueo['mensaje'];
        } else {
            
            // Conectar a la base de datos
            require_once __DIR__ . '/config/database.php';
            // Configurar charset antes de cualquier operación con BD
            configurarConexionBD($mysqli);
            
            // Buscar usuario por email incluyendo usuarios inactivos (para detectar cuentas desactivadas)
            // El email ya está normalizado, pasarlo directamente
            $usuario = buscarUsuarioPorEmailIncluyendoInactivos($mysqli, $email);
            
            if ($usuario) {
                // Verificar que el hash no esté vacío
                $hash_password = $usuario['contrasena'] ?? '';
                
                if (empty($hash_password) || strlen(trim($hash_password)) === 0) {
                    // Hash vacío - error de seguridad
                    $errores['general'] = 'Error de seguridad: cuenta sin contraseña válida. Contacta al administrador.';
                    // Incrementar intentos fallidos
                    incrementarIntentosFormulario($email, 'login');
                } else {
                    // Usar password_original para verificación (preservado desde $_POST)
                    $verificacion_resultado = verificarPassword($password_original, $hash_password);
                    
                    if ($verificacion_resultado) {
                        // Verificar si la cuenta está inactiva
                        $activo = intval($usuario['activo'] ?? 1);
                        
                        if ($activo === 0) {
                            // Cuenta inactiva - guardar datos temporalmente en sesión para reactivación
                            // NO establecer sesión de usuario aún, solo datos temporales
                            $_SESSION['usuario_reactivacion'] = [
                                'id_usuario' => $usuario['id_usuario'],
                                'nombre' => $usuario['nombre'],
                                'apellido' => $usuario['apellido'],
                                'email' => $usuario['email'],
                                'rol' => $usuario['rol']
                            ];
                            
                            // Limpiar intentos fallidos del email actual (credenciales válidas)
                            limpiarIntentosFormulario($email, 'login');
                            
                            // Limpiar contraseña de memoria
                            $password = null;
                            $password_original = null;
                            
                            // Establecer flag para mostrar modal de reactivación en esta misma carga
                            $mostrar_modal_reactivacion = true;
                            
                            // No establecer errores - se mostrará modal de reactivación
                            
                        } else {
                            // Cuenta activa - proceder con login normal
                            // PRESERVAR intentos de otras cuentas antes de limpiar y regenerar sesión
                            // Esto previene que se pierdan los bloqueos de otras cuentas cuando se regenera el ID de sesión
                            $intentos_backup = isset($_SESSION['intentos_login']) ? $_SESSION['intentos_login'] : [];
                            
                            // Login exitoso - limpiar intentos fallidos solo del email actual
                            limpiarIntentosFormulario($email, 'login');
                            
                            // Login exitoso - establecer variables de sesión
                            $_SESSION['id_usuario'] = $usuario['id_usuario'];
                            $_SESSION['nombre'] = $usuario['nombre'];
                            $_SESSION['apellido'] = $usuario['apellido'];
                            $_SESSION['email'] = $usuario['email'];
                            $_SESSION['rol'] = strtolower($usuario['rol']);
                            
                            // Regenerar ID de sesión después de autenticación para prevenir session fixation
                            // El parámetro true elimina la sesión antigua del servidor
                            session_regenerate_id(true);
                            
                            // RESTAURAR intentos de otras cuentas después de regenerar sesión
                            // Esto asegura que los bloqueos de otras cuentas persistan
                            if (!isset($_SESSION['intentos_login'])) {
                                $_SESSION['intentos_login'] = [];
                            }
                            // Restaurar solo los intentos de otras cuentas (excluir el email que se logueó exitosamente)
                            // El email ya está normalizado, usarlo directamente como clave
                            foreach ($intentos_backup as $email_key => $datos_intentos) {
                                if ($email_key !== $email) {
                                    $_SESSION['intentos_login'][$email_key] = $datos_intentos;
                                }
                            }
                            
                            // Obtener ID de usuario para cookies
                            $id_usuario = (int)$usuario['id_usuario'];
                            
                            // Limpiar contraseña de memoria
                            $password = null;
                            $password_original = null;
                            
                            // Redirigir según rol usando función centralizada
                            $rol = $usuario['rol'];
                            redirigirSegunRol($rol);
                        }
                    } else {
                        // Contraseña incorrecta - incrementar intentos fallidos
                        $resultado_intentos = incrementarIntentosFormulario($email, 'login');
                        if ($resultado_intentos['bloqueado']) {
                            $errores['general'] = $resultado_intentos['mensaje'];
                        } else {
                            $errores['general'] = 'Credenciales incorrectas. Verifica tu email y contraseña.';
                            // Mostrar advertencia si quedan pocos intentos
                            if (isset($resultado_intentos['intentos_restantes']) && $resultado_intentos['intentos_restantes'] <= 2) {
                                $errores['general'] .= ' Te quedan ' . $resultado_intentos['intentos_restantes'] . ' ' . 
                                    ($resultado_intentos['intentos_restantes'] == 1 ? 'intento' : 'intentos') . ' antes del bloqueo.';
                            }
                        }
                    }
                }
                
            } else {
                // Usuario no encontrado - por seguridad, no revelar si el email existe o no
                // Incrementar intentos fallidos (por seguridad)
                $resultado_intentos = incrementarIntentosFormulario($email, 'login');
                if ($resultado_intentos['bloqueado']) {
                    $errores['general'] = $resultado_intentos['mensaje'];
                } else {
                    $errores['general'] = 'Credenciales incorrectas. Verifica tu email y contraseña.';
                }
            }
        }
        
        // Limpiar contraseña de memoria
        $password = null;
    }
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- Contenido principal del login -->
<main class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">
                <h2>SEDA Y LINO</h2>
                <p>Bienvenido de nuevo</p>
            </div>
            
            <h3 class="auth-title">Iniciar Sesión</h3>
            
            <?php if ($mensaje && $mensaje_tipo === 'success'): ?>
                <div class="alert alert-success mb-4">
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errores['general'])): ?>
                <div class="alert alert-danger mb-4">
                    <?= htmlspecialchars($errores['general']) ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="post" class="auth-form" id="loginForm" novalidate autocomplete="on">
                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope me-1"></i>Correo Electrónico <span class="text-danger">*</span>
                    </label>
                    <input type="email" 
                           class="form-control <?= isset($errores['email']) ? 'is-invalid' : '' ?>" 
                           name="email" 
                           id="email" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="tu@email.com" 
                           required
                           minlength="6"
                           maxlength="150"
                           autocomplete="email"
                           title="Formato de email válido, mínimo 6 caracteres, máximo 150 caracteres">
                    <?php if (isset($errores['email'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errores['email']) ?></div>
                    <?php else: ?>
                        <div class="invalid-feedback">Por favor, ingresa un correo electrónico válido.</div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-1"></i>Contraseña <span class="text-danger">*</span>
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               class="form-control <?= isset($errores['password']) ? 'is-invalid' : '' ?>" 
                               name="password" 
                               id="password" 
                               placeholder="Tu contraseña" 
                               required 
                               minlength="6"
                               maxlength="32"
                               autocomplete="current-password"
                               title="Mínimo 6 caracteres, máximo 32 caracteres">
                        <button type="button" class="btn-toggle-password" data-input-id="password" id="togglePassword" aria-label="Mostrar contraseña">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <?php if (isset($errores['password'])): ?>
                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errores['password']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <button type="submit" class="btn auth-btn" id="loginBtn">
                        <span class="btn-text">Iniciar Sesión</span>
                        <span class="btn-loading d-none">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            Iniciando sesión...
                        </span>
                    </button>
                </div>
            </form>
            
            <div class="auth-links">
                <div class="mb-2">
                    <a href="register.php">¿No tienes cuenta? Regístrate aquí</a>
                </div>
                <div>
                    <a href="recuperar-contrasena.php">¿Olvidaste tu contraseña?</a>
                </div>
                <div class="mt-2">
                    <a href="index.php">Volver al inicio</a>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal de Reactivación de Cuenta -->
<?php if ($mostrar_modal_reactivacion): ?>
<div class="modal fade" id="modalReactivarCuenta" tabindex="-1" aria-labelledby="modalReactivarCuentaLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="modalReactivarCuentaLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Cuenta Desactivada
                </h5>
            </div>
            <div class="modal-body">
                <p class="mb-3">
                    Tu cuenta está desactivada. ¿Deseas reactivarla?
                </p>
                <p class="text-muted small mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    Al reactivar tu cuenta, podrás acceder nuevamente a todos los servicios.
                </p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="" id="formReactivarCuenta" style="display: inline;">
                    <input type="hidden" name="reactivar_cuenta" value="1">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnReactivarCuenta">
                        <span class="btn-text">
                            <i class="fas fa-check me-1"></i>Sí, reactivar cuenta
                        </span>
                        <span class="btn-loading d-none">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            Reactivando...
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; render_footer(); ?>

<script src="js/login.js"></script>
<?php if ($mostrar_modal_reactivacion): ?>
<script>
    // Mostrar modal automáticamente cuando la página carga
    document.addEventListener('DOMContentLoaded', function() {
        const modalElement = document.getElementById('modalReactivarCuenta');
        if (!modalElement) return;
        
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        
        // Manejar envío del formulario de reactivación
        const formReactivar = document.getElementById('formReactivarCuenta');
        const btnReactivar = document.getElementById('btnReactivarCuenta');
        
        if (formReactivar && btnReactivar) {
            formReactivar.addEventListener('submit', function(e) {
                const btnText = btnReactivar.querySelector('.btn-text');
                const btnLoading = btnReactivar.querySelector('.btn-loading');
                
                if (btnText && btnLoading) {
                    btnText.classList.add('d-none');
                    btnLoading.classList.remove('d-none');
                    btnReactivar.disabled = true;
                }
            });
        }
    });
</script>
<?php endif; ?>

