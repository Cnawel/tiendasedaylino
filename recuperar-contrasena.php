<?php
/**
 * ========================================================================
 * RECUPERACIÓN DE CONTRASEÑA - Tienda Seda y Lino
 * ========================================================================
 * Permite recuperar la contraseña olvidada mediante:
 * - Validación de email, fecha de nacimiento y respuesta de recupero
 * - Cambio de contraseña si las validaciones son correctas
 * ========================================================================
 */
session_start();

// Cargar funciones de seguridad
$security_functions_path = __DIR__ . '/includes/security_functions.php';
if (!file_exists($security_functions_path)) {
    error_log("ERROR: No se pudo encontrar security_functions.php en " . $security_functions_path);
    die("Error crítico: Archivo de funciones de seguridad no encontrado. Por favor, contacta al administrador.");
}
require_once $security_functions_path;

// Cargar funciones de consultas de usuarios
$usuario_queries_path = __DIR__ . '/includes/queries/usuario_queries.php';
if (!file_exists($usuario_queries_path)) {
    error_log("ERROR: No se pudo encontrar usuario_queries.php en " . $usuario_queries_path);
    die("Error crítico: Archivo de consultas de usuario no encontrado. Por favor, contacta al administrador.");
}
require_once $usuario_queries_path;

// Cargar funciones de consultas de perfil
$perfil_queries_path = __DIR__ . '/includes/queries/perfil_queries.php';
if (!file_exists($perfil_queries_path)) {
    error_log("ERROR: No se pudo encontrar perfil_queries.php en " . $perfil_queries_path);
    die("Error crítico: Archivo de consultas de perfil no encontrado. Por favor, contacta al administrador.");
}
require_once $perfil_queries_path;

// Cargar funciones de administración
$admin_functions_path = __DIR__ . '/includes/admin_functions.php';
if (!file_exists($admin_functions_path)) {
    error_log("ERROR: No se pudo encontrar admin_functions.php en " . $admin_functions_path);
    die("Error crítico: Archivo de funciones de administración no encontrado. Por favor, contacta al administrador.");
}
require_once $admin_functions_path;

// Configurar título de la página
$titulo_pagina = 'Recuperar Contraseña';

// Variables para controlar el flujo
$paso = 1; // Paso 1: Validación, Paso 2: Cambiar contraseña
$mensaje = '';
$mensaje_tipo = 'danger'; // danger, success, warning, info
$usuario_validado = false;
$id_usuario_validado = null;

// ========================================================================
// OBTENER PREGUNTAS DE RECUPERO DESDE BASE DE DATOS
// ========================================================================
require_once __DIR__ . '/config/database.php';
$preguntas_recupero = obtenerPreguntasRecupero($mysqli);

// ========================================================================
// PROCESAR PASO 1: VALIDACIÓN DE DATOS
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validar_datos'])) {
    // Validar EMAIL usando función centralizada
    $email_raw = $_POST['email'] ?? '';
    $validacion_email = validarEmail($email_raw);
    
    if (!$validacion_email['valido']) {
        $mensaje = $validacion_email['error'];
    } else {
        // La función validarEmail() ya sanitiza, pero necesitamos el valor original para búsqueda
        // Normalizar email para búsqueda: convertir a minúsculas
        $email = strtolower(trim($email_raw));
        $email = preg_replace('/[\x00-\x1F\x7F]/u', '', $email);
        // NOTA: No usar htmlspecialchars() aquí - se aplica solo al mostrar en HTML
        
        // ========================================================================
        // VERIFICAR LÍMITE DE INTENTOS (Estándar de Seguridad)
        // ========================================================================
        $verificacion_intentos = verificarIntentosFormulario($email, 'recupero');
        if (!$verificacion_intentos['permitido']) {
            $mensaje = $verificacion_intentos['mensaje'];
            $mensaje_tipo = 'warning';
        }
    }
    
    // Validar FECHA DE NACIMIENTO
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $fecha_nacimiento_procesada = null;
    
    if (empty($fecha_nacimiento)) {
        $mensaje = 'La fecha de nacimiento es obligatoria.';
    } else {
        // HTML5 date input envía formato YYYY-MM-DD directamente
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_nacimiento)) {
            $mensaje = 'El formato de fecha de nacimiento no es válido.';
        } else {
            // Validar que sea una fecha válida
            $fecha_parts = explode('-', $fecha_nacimiento);
            $year = intval($fecha_parts[0]);
            $month = intval($fecha_parts[1]);
            $day = intval($fecha_parts[2]);
            
            if (!checkdate($month, $day, $year)) {
                $mensaje = 'La fecha de nacimiento no es una fecha válida.';
            } else {
                // Validar rango de año permitido (1925-2012)
                if ($year < 1925 || $year > 2012) {
                    $mensaje = 'La fecha de nacimiento debe estar entre 1925 y 2012.';
                } else {
                    // La fecha ya está en formato YYYY-MM-DD
                    $fecha_nacimiento_procesada = $fecha_nacimiento;
                }
            }
        }
    }
    
    // Si se procesó correctamente, validar fecha futura
    if ($fecha_nacimiento_procesada && empty($mensaje)) {
        try {
            $fecha_nac = new DateTime($fecha_nacimiento_procesada);
            $fecha_actual = new DateTime();
            if ($fecha_nac > $fecha_actual) {
                $mensaje = 'La fecha de nacimiento no puede ser una fecha futura.';
            } else {
                // Actualizar variable para usar en comparación
                $fecha_nacimiento = $fecha_nacimiento_procesada;
            }
        } catch (Exception $e) {
            $mensaje = 'La fecha de nacimiento no es válida.';
        }
    }
    
    // Validar PREGUNTA DE RECUPERO
    $pregunta_recupero_id = intval($_POST['pregunta_recupero'] ?? 0);
    if ($pregunta_recupero_id <= 0) {
        $mensaje = 'Debes seleccionar una pregunta de recupero.';
    } else {
        // Verificar que la pregunta existe y está activa
        if (!verificarPreguntaRecupero($mysqli, $pregunta_recupero_id)) {
            $mensaje = 'La pregunta de recupero seleccionada no es válida.';
        }
    }
    
    // Validar RESPUESTA DE RECUPERO
    $respuesta_recupero = trim($_POST['respuesta_recupero'] ?? '');
    if (empty($respuesta_recupero)) {
        $mensaje = 'La respuesta de recupero es obligatoria.';
    } elseif (strlen($respuesta_recupero) < 4) {
        $mensaje = 'La respuesta de recupero debe tener al menos 4 caracteres.';
    } elseif (strlen($respuesta_recupero) > 255) {
        $mensaje = 'La respuesta de recupero no puede exceder 255 caracteres.';
    } elseif (!preg_match('/^[a-zA-Z0-9 ]+$/', $respuesta_recupero)) {
        $mensaje = 'La respuesta de recupero solo puede contener letras, números y espacios.';
    } else {
        // Normalizar respuesta a minúsculas para comparación
        $respuesta_recupero = strtolower($respuesta_recupero);
    }
    
    // Si todas las validaciones pasan, verificar en base de datos
    if (empty($mensaje)) {
        // PASO 1: Buscar usuario por email y verificar fecha de nacimiento
        // Solo usuarios activos pueden recuperar su contraseña (seguridad)
        $usuario = obtenerUsuarioPorEmailRecupero($mysqli, $email);
        
        if ($usuario) {
            // PASO 2: Verificar fecha de nacimiento primero
            $fecha_nac_bd = $usuario['fecha_nacimiento'] ?? null;
            
            if (empty($fecha_nac_bd)) {
                $mensaje = 'Este usuario no tiene fecha de nacimiento registrada. Contacta al administrador.';
            } else {
                // Comparar fechas (formato DATE: YYYY-MM-DD)
                $fecha_nac_formateada = date('Y-m-d', strtotime($fecha_nac_bd));
                if ($fecha_nac_formateada !== $fecha_nacimiento) {
                    $mensaje = 'Los datos proporcionados no coinciden. Verifica tu email y fecha de nacimiento.';
                } else {
                    // PASO 3: Si la fecha coincide, verificar pregunta y respuesta de recupero
                    $pregunta_bd = $usuario['pregunta_recupero'] ?? null;
                    $respuesta_bd = $usuario['respuesta_recupero'] ?? null;
                    
                    if (empty($pregunta_bd)) {
                        $mensaje = 'Este usuario no tiene pregunta de recupero registrada. Contacta al administrador.';
                    } elseif (empty($respuesta_bd)) {
                        $mensaje = 'Este usuario no tiene respuesta de recupero registrada. Contacta al administrador.';
                    } else {
                        // Comparar pregunta de recupero
                        if (intval($pregunta_bd) !== $pregunta_recupero_id) {
                            $mensaje = 'La pregunta de recupero seleccionada no coincide con la registrada.';
                        } else {
                            // Comparar respuesta (normalizada a minúsculas)
                            $respuesta_bd_normalizada = strtolower(trim($respuesta_bd));
                            if ($respuesta_bd_normalizada !== $respuesta_recupero) {
                                $mensaje = 'La respuesta de recupero no es correcta. Verifica tu respuesta.';
                            } else {
                                // Validación exitosa - limpiar intentos y guardar en sesión
                                limpiarIntentosFormulario($email, 'recupero');
                                
                                $_SESSION['recuperar_contrasena_id'] = $usuario['id_usuario'];
                                $_SESSION['recuperar_contrasena_email'] = $usuario['email'];
                                $usuario_validado = true;
                                $id_usuario_validado = $usuario['id_usuario'];
                                $paso = 2; // Avanzar al paso 2
                                $mensaje = 'Datos validados correctamente. Ahora puedes cambiar tu contraseña.';
                                $mensaje_tipo = 'success';
                            }
                        }
                    }
                }
            }
        } else {
            $mensaje = 'El correo electrónico no está registrado en el sistema.';
        }
        
        // ========================================================================
        // INCREMENTAR INTENTOS FALLIDOS (Estándar de Seguridad)
        // Solo si hubo un error de validación Y el email es válido (no errores de formato)
        // ========================================================================
        if (!empty($mensaje) && $mensaje_tipo !== 'warning' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $resultado_intentos = incrementarIntentosFormulario($email, 'recupero');
            
            if ($resultado_intentos['bloqueado']) {
                $mensaje = $resultado_intentos['mensaje'];
                $mensaje_tipo = 'warning';
            } elseif (isset($resultado_intentos['intentos_restantes']) && $resultado_intentos['intentos_restantes'] <= 3) {
                // Advertir cuando quedan pocos intentos
                $mensaje .= ' ' . "Te quedan {$resultado_intentos['intentos_restantes']} " . ($resultado_intentos['intentos_restantes'] == 1 ? 'intento' : 'intentos') . " antes del bloqueo temporal.";
            }
        }
    }
}

// ========================================================================
// PROCESAR PASO 2: CAMBIAR CONTRASEÑA
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_contrasena'])) {
    // Verificar que el usuario está validado en sesión
    if (!isset($_SESSION['recuperar_contrasena_id']) || empty($_SESSION['recuperar_contrasena_id'])) {
        $mensaje = 'Sesión expirada. Por favor, inicia el proceso nuevamente.';
        $paso = 1;
    } else {
        $id_usuario = intval($_SESSION['recuperar_contrasena_id']);
        
        // Validar NUEVA CONTRASEÑA
        $nueva_contrasena = $_POST['nueva_contrasena'] ?? '';
        $confirmar_contrasena = $_POST['confirmar_contrasena'] ?? '';
        
        if (empty($nueva_contrasena) || strlen($nueva_contrasena) === 0) {
            $mensaje = 'La nueva contraseña es obligatoria.';
        } elseif (strlen($nueva_contrasena) < 6) {
            $mensaje = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif (strlen($nueva_contrasena) > 32) {
            $mensaje = 'La contraseña no puede exceder 32 caracteres.';
        } elseif ($nueva_contrasena !== $confirmar_contrasena) {
            $mensaje = 'Las contraseñas no coinciden.';
        } else {
            // Generar hash seguro de la nueva contraseña usando función centralizada
            require_once __DIR__ . '/includes/password_functions.php';
            configurarConexionBD($mysqli);
            $hash_password = generarHashPassword($nueva_contrasena, $mysqli);
            
            if ($hash_password === false) {
                $mensaje = 'Error al procesar la contraseña. Inténtalo de nuevo.';
            } else {
                // Actualizar contraseña en base de datos usando función centralizada
                if (actualizarContrasena($mysqli, $id_usuario, $hash_password)) {
                    // Limpiar sesión de recuperación
                    unset($_SESSION['recuperar_contrasena_id']);
                    unset($_SESSION['recuperar_contrasena_email']);
                    
                    // Limpiar variables sensibles
                    $nueva_contrasena = null;
                    $confirmar_contrasena = null;
                    $hash_password = null;
                    
                    // Redireccionar a login con mensaje de éxito
                    header('Location: login.php?contrasena_actualizada=1');
                    exit;
                } else {
                    $mensaje = 'Error al actualizar la contraseña en la base de datos.';
                }
            }
        }
        
        $paso = 2; // Mantener en paso 2 si hay error
    }
}

// Si estamos en paso 2, verificar que la sesión de validación existe
if ($paso === 2 && !isset($_SESSION['recuperar_contrasena_id'])) {
    $paso = 1;
    $mensaje = 'Sesión expirada. Por favor, inicia el proceso nuevamente.';
}

// Obtener pregunta de recupero del usuario si está validado
$pregunta_usuario = null;
if (isset($_SESSION['recuperar_contrasena_id'])) {
    $id_usuario = intval($_SESSION['recuperar_contrasena_id']);
    $pregunta_id = obtenerPreguntaRecuperoUsuario($mysqli, $id_usuario);
    if ($pregunta_id) {
        $pregunta_usuario = obtenerTextoPreguntaRecupero($mysqli, $pregunta_id);
    }
}
?>

<?php include 'includes/header.php'; ?>


    <main class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-logo">
                    <h2>SEDA Y LINO</h2>
                    <p>Recuperar contraseña</p>
                </div>
                
                <?php if ($paso === 1): ?>
                    <h3 class="auth-title">Recuperar Contraseña</h3>
                    
                    <?php if ($mensaje): ?>
                        <div class="alert alert-<?= $mensaje_tipo ?> mb-4">
                            <i class="fas fa-<?= $mensaje_tipo === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i><?= htmlspecialchars($mensaje) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="" method="post" class="auth-form" id="validarForm" novalidate>
                        <input type="hidden" name="validar_datos" value="1">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-1"></i>Correo Electrónico <span class="text-danger">*</span>
                            </label>
                            <input type="email" 
                                   class="form-control" 
                                   name="email" 
                                   id="email" 
                                   placeholder="tu@email.com" 
                                   required 
                                   autofocus
                                   autocomplete="email"
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                            <div class="invalid-feedback">Por favor, ingresa un correo electrónico válido.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="fecha_nacimiento" class="form-label">
                                <i class="fas fa-calendar me-1"></i>Fecha de Nacimiento <span class="text-danger">*</span>
                            </label>
                            <input type="date" 
                                   class="form-control" 
                                   name="fecha_nacimiento" 
                                   id="fecha_nacimiento" 
                                   required
                                   max="2012-12-31"
                                   min="1925-01-01"
                                   value="<?= isset($_POST['fecha_nacimiento']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha_nacimiento']) ? htmlspecialchars($_POST['fecha_nacimiento']) : '' ?>">
                            <div class="invalid-feedback">Por favor, ingresa tu fecha de nacimiento.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="pregunta_recupero" class="form-label">
                                <i class="fas fa-question-circle me-1"></i>Pregunta de Recupero <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" 
                                    name="pregunta_recupero" 
                                    id="pregunta_recupero" 
                                    required>
                                <option value="">Selecciona una pregunta...</option>
                                <?php foreach ($preguntas_recupero as $pregunta): ?>
                                    <option value="<?= $pregunta['id_pregunta'] ?>" 
                                            <?= (isset($_POST['pregunta_recupero']) && intval($_POST['pregunta_recupero']) === $pregunta['id_pregunta']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pregunta['texto_pregunta']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Por favor, selecciona una pregunta de recupero.</div>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>Selecciona la pregunta que registraste cuando creaste tu cuenta
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="respuesta_recupero" class="form-label">
                                <i class="fas fa-key me-1"></i>Respuesta de Recupero <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   name="respuesta_recupero" 
                                   id="respuesta_recupero" 
                                   placeholder="Tu respuesta (mínimo 6 caracteres)" 
                                   required
                                   minlength="6"
                                   maxlength="255"
                                   pattern="[a-zA-Z0-9 ]+"
                                   title="Letras, números y espacios, mínimo 6 caracteres, máximo 255 caracteres"
                                   value="<?= isset($_POST['respuesta_recupero']) ? htmlspecialchars($_POST['respuesta_recupero']) : '' ?>">
                            <div class="invalid-feedback">La respuesta debe contener letras, números y espacios, mínimo 6 caracteres, máximo 255 caracteres.</div>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>Ingresa la respuesta que registraste cuando creaste tu cuenta
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <button type="submit" class="btn auth-btn" id="validarBtn">
                                <span class="btn-text">Validar Datos</span>
                                <span class="btn-loading d-none">
                                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                    Validando...
                                </span>
                            </button>
                        </div>
                    </form>
                    
                <?php elseif ($paso === 2): ?>
                    <h3 class="auth-title">Cambiar Contraseña</h3>
                    
                    <?php if ($mensaje): ?>
                        <div class="alert alert-<?= $mensaje_tipo ?> mb-4">
                            <i class="fas fa-<?= $mensaje_tipo === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i><?= htmlspecialchars($mensaje) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($pregunta_usuario): ?>
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Pregunta registrada:</strong> <?= htmlspecialchars($pregunta_usuario) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="" method="post" class="auth-form" id="cambiarForm" novalidate>
                        <input type="hidden" name="cambiar_contrasena" value="1">
                        
                        <div class="mb-3">
                            <label for="nueva_contrasena" class="form-label">
                                <i class="fas fa-lock me-1"></i>Nueva Contraseña <span class="text-danger">*</span>
                            </label>
                            <div class="password-input-wrapper">
                                <input type="password" 
                                       class="form-control" 
                                       name="nueva_contrasena" 
                                       id="nueva_contrasena" 
                                       placeholder="Mínimo 6 caracteres" 
                                       required 
                                       minlength="6"
                                       maxlength="32"
                                       autocomplete="new-password"
                                       autofocus>
                                <button type="button" class="btn-toggle-password" id="togglePasswordNueva" aria-label="Mostrar contraseña">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">La contraseña debe tener al menos 6 caracteres.</div>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>Mínimo 6 caracteres
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirmar_contrasena" class="form-label">
                                <i class="fas fa-lock me-1"></i>Confirmar Contraseña <span class="text-danger">*</span>
                            </label>
                            <div class="password-input-wrapper">
                                <input type="password" 
                                       class="form-control" 
                                       name="confirmar_contrasena" 
                                       id="confirmar_contrasena" 
                                       placeholder="Repite tu contraseña" 
                                       required 
                                       minlength="6"
                                       maxlength="32"
                                       autocomplete="new-password">
                                <button type="button" class="btn-toggle-password" id="togglePasswordConfirmar" aria-label="Mostrar contraseña">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Las contraseñas no coinciden.</div>
                            <div class="valid-feedback">Las contraseñas coinciden</div>
                        </div>
                        
                        <div class="mb-3">
                            <button type="submit" class="btn auth-btn" id="cambiarBtn">
                                <span class="btn-text">Cambiar Contraseña</span>
                                <span class="btn-loading d-none">
                                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                    Cambiando...
                                </span>
                            </button>
                        </div>
                    </form>
                    
                <?php endif; ?>
                
                <div class="auth-links">
                    <div class="mb-2">
                        <a href="login.php">Volver al inicio de sesión</a>
                    </div>
                    <div>
                        <a href="index.php">Volver al inicio</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Flatpickr JS para calendario personalizado -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    
    <script>
        // ============================================================================
        // RECUPERAR CONTRASEÑA - JavaScript para mejorar UX
        // ============================================================================
        
        document.addEventListener('DOMContentLoaded', function() {
            const validarForm = document.getElementById('validarForm');
            const cambiarForm = document.getElementById('cambiarForm');
            const emailInput = document.getElementById('email');
            const respuestaRecuperoInput = document.getElementById('respuesta_recupero');
            const nuevaContrasenaInput = document.getElementById('nueva_contrasena');
            const confirmarContrasenaInput = document.getElementById('confirmar_contrasena');
            const togglePasswordNueva = document.getElementById('togglePasswordNueva');
            const togglePasswordConfirmar = document.getElementById('togglePasswordConfirmar');
            
            // ========================================================================
            // Validación en tiempo real del email (Paso 1)
            // ========================================================================
            if (emailInput) {
                emailInput.addEventListener('input', function() {
                    validateEmail(this);
                });
                
                emailInput.addEventListener('blur', function() {
                    validateEmail(this);
                });
                
                function validateEmail(input) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (input.value.trim() === '') {
                        input.classList.remove('is-valid', 'is-invalid');
                    } else if (emailRegex.test(input.value)) {
                        input.classList.remove('is-invalid');
                        input.classList.add('is-valid');
                    } else {
                        input.classList.remove('is-valid');
                        input.classList.add('is-invalid');
                    }
                }
            }
            
            // ========================================================================
            // Toggle mostrar/ocultar contraseñas (Paso 2)
            // ========================================================================
            function setupPasswordToggle(button, input) {
                if (button && input) {
                    button.addEventListener('click', function() {
                        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                        input.setAttribute('type', type);
                        
                        const icon = this.querySelector('i');
                        if (type === 'password') {
                            icon.classList.remove('fa-eye-slash');
                            icon.classList.add('fa-eye');
                            this.setAttribute('aria-label', 'Mostrar contraseña');
                        } else {
                            icon.classList.remove('fa-eye');
                            icon.classList.add('fa-eye-slash');
                            this.setAttribute('aria-label', 'Ocultar contraseña');
                        }
                    });
                }
            }
            
            setupPasswordToggle(togglePasswordNueva, nuevaContrasenaInput);
            setupPasswordToggle(togglePasswordConfirmar, confirmarContrasenaInput);
            
            // ========================================================================
            // Validación de confirmación de contraseña (Paso 2)
            // ========================================================================
            if (confirmarContrasenaInput && nuevaContrasenaInput) {
                confirmarContrasenaInput.addEventListener('input', validatePasswordConfirm);
                confirmarContrasenaInput.addEventListener('blur', validatePasswordConfirm);
                nuevaContrasenaInput.addEventListener('input', validatePasswordConfirm);
                
                function validatePasswordConfirm() {
                    if (confirmarContrasenaInput.value === '') {
                        confirmarContrasenaInput.classList.remove('is-valid', 'is-invalid');
                    } else if (nuevaContrasenaInput.value === confirmarContrasenaInput.value) {
                        confirmarContrasenaInput.classList.remove('is-invalid');
                        confirmarContrasenaInput.classList.add('is-valid');
                    } else {
                        confirmarContrasenaInput.classList.remove('is-valid');
                        confirmarContrasenaInput.classList.add('is-invalid');
                    }
                }
            }
            
            // ========================================================================
            // Validación del formulario de validación (Paso 1)
            // ========================================================================
            if (validarForm) {
                const validarBtn = document.getElementById('validarBtn');
                
                validarForm.addEventListener('submit', function(e) {
                    let isValid = true;
                    
                    if (emailInput && (!emailInput.value.trim() || !emailInput.classList.contains('is-valid'))) {
                        emailInput.classList.add('is-invalid');
                        isValid = false;
                    }
                    
                    const fechaNacimientoInput = document.getElementById('fecha_nacimiento');
                    if (fechaNacimientoInput && !fechaNacimientoInput.value) {
                        fechaNacimientoInput.classList.add('is-invalid');
                        isValid = false;
                    }
                    
                    const preguntaRecuperoInput = document.getElementById('pregunta_recupero');
                    if (preguntaRecuperoInput && !preguntaRecuperoInput.value) {
                        preguntaRecuperoInput.classList.add('is-invalid');
                        isValid = false;
                    }
                    
                    if (respuestaRecuperoInput && !respuestaRecuperoInput.value.trim()) {
                        respuestaRecuperoInput.classList.add('is-invalid');
                        isValid = false;
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        const firstError = validarForm.querySelector('.is-invalid');
                        if (firstError) {
                            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            firstError.focus();
                        }
                        return;
                    }
                    
                    // Mostrar estado de carga
                    if (validarBtn) {
                        const btnText = validarBtn.querySelector('.btn-text');
                        const btnLoading = validarBtn.querySelector('.btn-loading');
                        if (btnText && btnLoading) {
                            btnText.classList.add('d-none');
                            btnLoading.classList.remove('d-none');
                            validarBtn.disabled = true;
                        }
                    }
                });
            }
            
            // ========================================================================
            // Validación del formulario de cambio (Paso 2)
            // ========================================================================
            if (cambiarForm) {
                const cambiarBtn = document.getElementById('cambiarBtn');
                
                cambiarForm.addEventListener('submit', function(e) {
                    let isValid = true;
                    
                    if (nuevaContrasenaInput && (!nuevaContrasenaInput.value || nuevaContrasenaInput.value.length < 6)) {
                        nuevaContrasenaInput.classList.add('is-invalid');
                        isValid = false;
                    }
                    
                    if (confirmarContrasenaInput && nuevaContrasenaInput && nuevaContrasenaInput.value !== confirmarContrasenaInput.value) {
                        confirmarContrasenaInput.classList.add('is-invalid');
                        isValid = false;
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        const firstError = cambiarForm.querySelector('.is-invalid');
                        if (firstError) {
                            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            firstError.focus();
                        }
                        return;
                    }
                    
                    // Mostrar estado de carga
                    if (cambiarBtn) {
                        const btnText = cambiarBtn.querySelector('.btn-text');
                        const btnLoading = cambiarBtn.querySelector('.btn-loading');
                        if (btnText && btnLoading) {
                            btnText.classList.add('d-none');
                            btnLoading.classList.remove('d-none');
                            cambiarBtn.disabled = true;
                        }
                    }
                });
            }
            
            // ========================================================================
            // Limpiar errores al interactuar
            // ========================================================================
            if (respuestaRecuperoInput) {
                respuestaRecuperoInput.addEventListener('input', function() {
                    if (this.value.trim()) {
                        this.classList.remove('is-invalid');
                    }
                });
            }
            
            if (nuevaContrasenaInput) {
                nuevaContrasenaInput.addEventListener('input', function() {
                    if (this.value && this.value.length >= 6) {
                        this.classList.remove('is-invalid');
                    }
                });
            }
            
            // ========================================================================
            // Animación suave de entrada
            // ========================================================================
            const authCard = document.querySelector('.auth-card');
            if (authCard) {
                authCard.style.opacity = '0';
                authCard.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    authCard.style.transition = 'all 0.5s ease';
                    authCard.style.opacity = '1';
                    authCard.style.transform = 'translateY(0)';
                }, 100);
            }
        });
    </script>

<?php include 'includes/footer.php'; render_footer(); ?>

