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
require_once __DIR__ . '/includes/queries/usuario_queries.php';
require_once __DIR__ . '/includes/queries/perfil_queries.php';
require_once __DIR__ . '/includes/security_functions.php';
require_once __DIR__ . '/includes/admin_functions.php';
require_once __DIR__ . '/includes/auth_check.php';

// Configurar título de la página
$titulo_pagina = 'Iniciar Sesión';

// Variables para mensajes y errores
$errores = [];
$mensaje = '';
$mensaje_tipo = '';

// Verificar si el usuario ya está logueado, redirigir según su rol
if (!empty($_SESSION['id_usuario'])) {
    $rol_sesion = $_SESSION['rol'] ?? 'cliente';
    redirigirSegunRol($rol_sesion);
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
// PROCESAR FORMULARIO DE LOGIN
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validar EMAIL usando función centralizada
    $email_raw = $_POST['email'] ?? '';
    $validacion_email = validarEmail($email_raw);
    
    if (!$validacion_email['valido']) {
        $errores['email'] = $validacion_email['error'];
    } else {
        // La función validarEmail() ya sanitiza, pero necesitamos el valor original para búsqueda
        // Normalizar email para búsqueda: convertir a minúsculas
        $email = strtolower(trim($email_raw));
        $email = preg_replace('/[\x00-\x1F\x7F]/u', '', $email);
    }
    
    // Validación HTML básica de CONTRASEÑA
    // IMPORTANTE: NO usar trim() en password - puede cambiar la contraseña
    $password = $_POST['password'] ?? '';
    
    // Guardar password en variable local para evitar que se pierda
    $password_original = $password;
    
    if (empty($password) || strlen($password) === 0) {
        $errores['password'] = 'La contraseña es obligatoria.';
    } elseif (strlen($password) < 6) {
        $errores['password'] = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif (strlen($password) > 32) {
        $errores['password'] = 'La contraseña no puede exceder 32 caracteres.';
    }
    
    // Si no hay errores de validación HTML, verificar bloqueo y consultar DB
    if (empty($errores)) {
        
        // Usar email ya normalizado para búsqueda
        $email_busqueda = $email;
        
        // Verificar bloqueo de intentos fallidos (10 intentos para login, bloqueo por email)
        $verificacion_bloqueo = verificarIntentosFormulario($email_busqueda, 'login');
        if (!$verificacion_bloqueo['permitido']) {
            $errores['general'] = $verificacion_bloqueo['mensaje'];
        } else {
            
            // Conectar a la base de datos
            require_once __DIR__ . '/config/database.php';
            // Configurar charset antes de cualquier operación con BD
            configurarConexionBD($mysqli);
            
            // Buscar usuario por email (usar email normalizado en minúsculas)
            $usuario = buscarUsuarioPorEmail($mysqli, $email_busqueda);
            
            if ($usuario) {
                // Verificar que el hash no esté vacío
                $hash_password = $usuario['contrasena'] ?? '';
                
                if (empty($hash_password) || strlen(trim($hash_password)) === 0) {
                    // Hash vacío - error de seguridad
                    $errores['general'] = 'Error de seguridad: cuenta sin contraseña válida. Contacta al administrador.';
                    // Incrementar intentos fallidos
                    incrementarIntentosFormulario($email_busqueda, 'login');
                } else {
                    // Usar password_original para verificación (password puede haberse perdido)
                    $password_para_verificar = $password_original;
                    
                    $verificacion_resultado = verificarPassword($password_para_verificar, $hash_password);
                    
                    if ($verificacion_resultado) {
                        // PRESERVAR intentos de otras cuentas antes de limpiar y regenerar sesión
                        // Esto previene que se pierdan los bloqueos de otras cuentas cuando se regenera el ID de sesión
                        $email_normalizado = strtolower(trim($email_busqueda));
                        $intentos_backup = isset($_SESSION['intentos_login']) ? $_SESSION['intentos_login'] : [];
                        
                        // Login exitoso - limpiar intentos fallidos solo del email actual
                        limpiarIntentosFormulario($email_busqueda, 'login');
                        
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
                        foreach ($intentos_backup as $email_key => $datos_intentos) {
                            if ($email_key !== $email_normalizado) {
                                $_SESSION['intentos_login'][$email_key] = $datos_intentos;
                            }
                        }
                        
                        // Obtener ID de usuario para cookies
                        $id_usuario = (int)$usuario['id_usuario'];
                        
                        // Limpiar contraseña de memoria
                        $password = null;
                        $password_original = null;
                        $password_para_verificar = null;
                        
                        // Redirigir según rol usando función centralizada
                        $rol = $usuario['rol'];
                        redirigirSegunRol($rol);
                    } else {
                        // Contraseña incorrecta - incrementar intentos fallidos
                        $resultado_intentos = incrementarIntentosFormulario($email_busqueda, 'login');
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
                $resultado_intentos = incrementarIntentosFormulario($email_busqueda, 'login');
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
                        <button type="button" class="btn-toggle-password" id="togglePassword" aria-label="Mostrar contraseña">
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

<?php include __DIR__ . '/includes/footer.php'; render_footer(); ?>

<script src="/includes/login.js"></script>

