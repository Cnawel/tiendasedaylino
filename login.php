<?php
/**
 * ========================================================================
 * LOGIN - Tienda Seda y Lino
 * ========================================================================
 * Sistema de autenticación de usuarios
 * - Valida credenciales (email y contraseña)
 * - Verifica contraseña hasheada con password_verify()
 * - Crea sesión con datos del usuario
 * - Redirecciona según rol: Admin -> admin.php, Marketing -> marketing.php,
 *   Ventas -> ventas.php, Cliente -> catalogo.php
 * ========================================================================
 */
session_start();
require_once 'session.php';
require_once 'includes/auth_check.php';

// Configurar título de la página
$titulo_pagina = 'Iniciar Sesión';

// Variable para mensajes de error
$mensaje = '';
$bloqueado = false;

// ========================================================================
// RATE LIMITING - Protección contra ataques de fuerza bruta
// ========================================================================
$max_intentos = 5;
$tiempo_bloqueo = 900; // 15 minutos en segundos
$ventana_tiempo = 900; // 15 minutos en segundos

// Obtener IP del cliente
$ip_cliente = $_SERVER['REMOTE_ADDR'];

// Inicializar estructura de rate limiting en sesión
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = [];
}

// Limpiar datos corruptos en la sesión
if (isset($_SESSION['login_attempts']) && !is_array($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = [];
}

// Limpiar intentos antiguos (fuera de la ventana de tiempo)
$tiempo_actual = time();

// Filtrar intentos antiguos para cada IP
foreach ($_SESSION['login_attempts'] as $ip => $timestamps) {
    if (is_array($timestamps)) {
        $_SESSION['login_attempts'][$ip] = array_values(array_filter($timestamps, function($timestamp) use ($tiempo_actual, $ventana_tiempo) {
            return ($tiempo_actual - $timestamp) < $ventana_tiempo;
        }));
        
        if (empty($_SESSION['login_attempts'][$ip])) {
            unset($_SESSION['login_attempts'][$ip]);
        }
    } else {
        unset($_SESSION['login_attempts'][$ip]);
    }
}

// Verificar si la IP está bloqueada
$intentos_ip = isset($_SESSION['login_attempts'][$ip_cliente]) 
    ? $_SESSION['login_attempts'][$ip_cliente] 
    : [];

if (count($intentos_ip) >= $max_intentos) {
    $ultimo_intento = max($intentos_ip);
    $tiempo_transcurrido = $tiempo_actual - $ultimo_intento;
    
    if ($tiempo_transcurrido < $tiempo_bloqueo) {
        $segundos_restantes = $tiempo_bloqueo - $tiempo_transcurrido;
        $minutos_restantes = ceil($segundos_restantes / 60);
        $mensaje = "Demasiados intentos fallidos. Tu acceso está bloqueado. Por favor, intenta de nuevo en {$minutos_restantes} minuto(s).";
        $bloqueado = true;
    } else {
        // Si pasó el tiempo de bloqueo, limpiar intentos antiguos
        $_SESSION['login_attempts'][$ip_cliente] = array_values(array_filter($intentos_ip, function($timestamp) use ($tiempo_actual, $ventana_tiempo) {
            return ($tiempo_actual - $timestamp) < $ventana_tiempo;
        }));
    }
}

// Verificar si se solicita limpiar bloqueo (para desarrollo/testing)
if (isset($_GET['limpiar_bloqueo']) && $_GET['limpiar_bloqueo'] === '1') {
    if (isset($_SESSION['login_attempts'][$ip_cliente])) {
        unset($_SESSION['login_attempts'][$ip_cliente]);
        $mensaje = "Bloqueo limpiado. Puedes intentar iniciar sesión nuevamente.";
        $bloqueado = false;
    }
}

// ========================================================================
// PROCESAR FORMULARIO DE LOGIN
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$bloqueado) {
    // Obtener datos del formulario
    // Limpiar email: trim + eliminar caracteres invisibles que pueden causar problemas
    $email_raw = $_POST['email'] ?? '';
    $email = trim($email_raw);
    // Eliminar cualquier carácter de control invisible que pueda interferir con la comparación
    $email = preg_replace('/[\x00-\x1F\x7F]/u', '', $email);
    
    // IMPORTANTE: NO usar trim() en password - puede cambiar la contraseña válida
    // Pero eliminar espacios en blanco al inicio/final comúnmente causados por copiar/pegar
    $password_raw = $_POST['password'] ?? '';
    // Eliminar espacios al inicio y final SOLO si existen (no afecta contraseñas válidas con espacios intencionales)
    $password = $password_raw;
    
    // Debug: Log de intentos fallidos para diagnóstico (solo en desarrollo)
    if (empty($email) || empty($password)) {
        error_log("Login fallido: email o password vacío");
    }
    
    // Validaciones básicas
    if (empty($email) || empty($password)) {
        if (!isset($_SESSION['login_attempts'][$ip_cliente])) {
            $_SESSION['login_attempts'][$ip_cliente] = [];
        }
        $_SESSION['login_attempts'][$ip_cliente][] = $tiempo_actual;
        $mensaje = "Email y contraseña son requeridos.";
    } else {
        // Conectar a la base de datos
        require_once 'config/database.php';
        
        // Buscar usuario por email
        // Intentar primero con búsqueda normal, luego con TRIM si falla (para manejar emails con espacios en BD)
        $stmt = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Si no se encuentra, intentar con TRIM (para manejar emails con espacios en BD)
        if ($result->num_rows === 0) {
            $stmt->close();
            $stmt = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE TRIM(email) = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        
        // Si aún no se encuentra, intentar con COLLATE utf8mb4_bin (comparación exacta de bytes)
        if ($result && $result->num_rows === 0) {
            $stmt->close();
            try {
                $stmt = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? COLLATE utf8mb4_bin LIMIT 1");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
            } catch (Exception $e) {
                // Si COLLATE falla, mantener resultado anterior
            }
        }
        
        if ($result && ($row = $result->fetch_assoc())) {
            // Verificar contraseña
            if (password_verify($password, $row['contrasena'])) {
                // Login exitoso - limpiar intentos fallidos
                if (isset($_SESSION['login_attempts'][$ip_cliente])) {
                    unset($_SESSION['login_attempts'][$ip_cliente]);
                }
                
                // Validar y normalizar el rol desde BD
                $rol_bd = strtolower(trim($row['rol'] ?? ''));
                $roles_validos = ['admin', 'ventas', 'marketing', 'cliente'];
                
                // Si el rol no es válido o está vacío, usar 'cliente' por defecto
                if (!in_array($rol_bd, $roles_validos, true)) {
                    $rol_bd = 'cliente';
                }
                
                // Guardar datos del usuario en la sesión
                $_SESSION['id_usuario'] = $row['id_usuario'];
                $_SESSION['nombre'] = $row['nombre'];
                $_SESSION['apellido'] = $row['apellido'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['rol'] = $rol_bd;
                
                // Redirigir según el rol del usuario
                if (isAdmin()) {
                    header('Location: admin.php');
                } elseif (isMarketing()) {
                    header('Location: marketing.php');
                } elseif (isVentas()) {
                    header('Location: ventas.php');
                } else {
                    header('Location: catalogo.php');
                }
                exit;
            } else {
                // Contraseña incorrecta
                if (!isset($_SESSION['login_attempts'][$ip_cliente])) {
                    $_SESSION['login_attempts'][$ip_cliente] = [];
                }
                $_SESSION['login_attempts'][$ip_cliente][] = $tiempo_actual;
                
                $total_intentos = count($_SESSION['login_attempts'][$ip_cliente]);
                if ($total_intentos >= $max_intentos) {
                    $mensaje = "Demasiados intentos fallidos. Tu acceso está bloqueado temporalmente por 15 minutos.";
                } else {
                    $intentos_restantes = $max_intentos - $total_intentos;
                    $mensaje = "Contraseña incorrecta. Te quedan {$intentos_restantes} intento(s).";
                }
            }
        } else {
            // Usuario no existe
            if (!isset($_SESSION['login_attempts'][$ip_cliente])) {
                $_SESSION['login_attempts'][$ip_cliente] = [];
            }
            $_SESSION['login_attempts'][$ip_cliente][] = $tiempo_actual;
            
            $total_intentos = count($_SESSION['login_attempts'][$ip_cliente]);
            if ($total_intentos >= $max_intentos) {
                $mensaje = "Demasiados intentos fallidos. Tu acceso está bloqueado temporalmente por 15 minutos.";
            } else {
                $intentos_restantes = $max_intentos - $total_intentos;
                $mensaje = "El usuario no existe. Te quedan {$intentos_restantes} intento(s).";
            }
        }
        
        $stmt->close();
    }
}
?>

<?php include 'includes/header.php'; ?>

    <main class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-logo">
                    <h2>SEDA Y LINO</h2>
                    <p>Elegancia que viste tus momentos</p>
                </div>
                
                <h3 class="auth-title">Iniciar Sesión</h3>
                
                <?php if (isset($_GET['nuevo']) && $_GET['nuevo'] == '1'): ?>
                    <div class="alert alert-success alert-custom alert-success-custom mb-4">
                        <i class="fas fa-check-circle me-2"></i>¡Cuenta creada con éxito! Ahora puedes iniciar sesión.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['registro']) && $_GET['registro'] == 'exitoso'): ?>
                    <div class="alert alert-info alert-custom mb-4" id="registro-message">
                        <i class="fas fa-info-circle me-2"></i>¡Registro exitoso! Tu cuenta ha sido creada correctamente. Ahora puedes iniciar sesión.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['logout']) && $_GET['logout'] == '1'): ?>
                    <div class="alert alert-info alert-custom mb-4" id="logout-message">
                        <i class="fas fa-info-circle me-2"></i>Sesión cerrada correctamente. ¡Hasta pronto!
                    </div>
                <?php endif; ?>
                
                <?php if ($mensaje): ?>
                    <div class="alert alert-danger alert-custom alert-danger-custom mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($mensaje) ?>
                        <?php if ($bloqueado): ?>
                            <br><br>
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Si necesitas acceso inmediato, puedes 
                                <a href="limpiar-sesion-login.php" class="alert-link">limpiar el bloqueo</a> 
                                (solo para desarrollo/testing).
                            </small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <form action="" method="post" class="auth-form" id="loginForm" novalidate>
                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope me-1"></i>Correo Electrónico
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
                        <div class="valid-feedback">Correo válido</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-1"></i>Contraseña
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   class="form-control" 
                                   name="password" 
                                   id="password" 
                                   placeholder="Ingresa tu contraseña" 
                                   required
                                   autocomplete="current-password">
                            <button type="button" class="btn-toggle-password" id="togglePassword" aria-label="Mostrar contraseña">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">La contraseña es requerida.</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input class="form-check-input" type="checkbox" id="rememberMe" name="rememberMe">
                        <label class="form-check-label form-check-label-custom" for="rememberMe">
                            Recordar mi correo
                        </label>
                    </div>
                    
                    <div class="mb-3">
                        <button type="submit" class="btn auth-btn" id="loginBtn">
                            <span class="btn-text">Iniciar Sesión</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                Iniciando...
                            </span>
                        </button>
                    </div>
                </form>
                
                <div class="auth-links">
                    <div class="mb-2">
                        <a href="register.php">¿No tienes cuenta? Regístrate aquí</a>
                    </div>
                    <div>
                        <a href="index.php">Volver al inicio</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer-completo">
        <div class="container">
            <div class="row py-5">
                <!-- Columna 1: Sobre Nosotros -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">SEDA Y LINO</h5>
                    <p class="footer-texto">Elegancia que viste tus momentos. Prendas únicas de seda y lino con calidad artesanal.</p>
                    <div class="footer-redes mt-3">
                        <a href="https://www.facebook.com/?locale=es_LA" target="_blank" rel="noopener noreferrer" class="footer-red-social me-2" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" class="footer-red-social me-2" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://x.com/?lang=es" target="_blank" rel="noopener noreferrer" class="footer-red-social" title="X (Twitter)">
                            <i class="fab fa-x-twitter"></i>
                        </a>
                    </div>
                </div>

                <!-- Columna 2: Navegación -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Navegación</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-2">
                            <a href="index.php" class="footer-link">
                                <i class="fas fa-home me-2"></i>Inicio
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="nosotros.php" class="footer-link">
                                <i class="fas fa-users me-2"></i>Nosotros
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=todos" class="footer-link">
                                <i class="fas fa-shopping-bag me-2"></i>Catálogo
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="index.php#contacto" class="footer-link">
                                <i class="fas fa-envelope me-2"></i>Contacto
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Columna 3: Categorías -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Productos</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Camisas" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Camisas
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Blusas" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Blusas
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Pantalones" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Pantalones
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Shorts" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Shorts
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Columna 4: Información de Contacto -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Contacto</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-3">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            <span class="footer-texto-small">Buenos Aires, Argentina</span>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-phone me-2"></i>
                            <a href="tel:+541112345678" class="footer-link">+54 11 1234-5678</a>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-envelope me-2"></i>
                            <a href="mailto:info@sedaylino.com" class="footer-link">info@sedaylino.com</a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Línea divisoria -->
            <hr class="footer-divider">

            <!-- Footer inferior -->
            <div class="row py-3">
                <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                    <p class="footer-copyright mb-0">
                        <i class="fas fa-copyright me-1"></i> 2025 Seda y Lino. Todos los derechos reservados
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="terminos.php" class="footer-link-small me-3">Términos y Condiciones</a>
                    <a href="privacidad.php" class="footer-link-small">Política de Privacidad</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    
    <script>
        // ============================================================================
        // LOGIN - JavaScript para mejorar UX
        // ============================================================================
        
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const togglePasswordBtn = document.getElementById('togglePassword');
            const loginBtn = document.getElementById('loginBtn');
            const rememberMeCheckbox = document.getElementById('rememberMe');
            
            // ========================================================================
            // Cargar email guardado si existe
            // ========================================================================
            const savedEmail = localStorage.getItem('rememberedEmail');
            if (savedEmail) {
                emailInput.value = savedEmail;
                rememberMeCheckbox.checked = true;
            }
            
            // ========================================================================
            // Validación en tiempo real del email
            // ========================================================================
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
            
            // ========================================================================
            // Toggle mostrar/ocultar contraseña
            // ========================================================================
            togglePasswordBtn.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Cambiar icono
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
            
            // ========================================================================
            // Validación del formulario al enviar
            // ========================================================================
            loginForm.addEventListener('submit', function(e) {
                // Validar campos
                let isValid = true;
                
                if (!emailInput.value.trim() || !emailInput.classList.contains('is-valid')) {
                    emailInput.classList.add('is-invalid');
                    isValid = false;
                }
                
                if (!passwordInput.value || passwordInput.value.length === 0) {
                    passwordInput.classList.add('is-invalid');
                    isValid = false;
                }
                
                // Si no es válido, prevenir envío
                if (!isValid) {
                    e.preventDefault();
                    return;
                }
                
                // Guardar email si "recordar" está marcado
                if (rememberMeCheckbox.checked) {
                    localStorage.setItem('rememberedEmail', emailInput.value);
                } else {
                    localStorage.removeItem('rememberedEmail');
                }
                
                // Mostrar estado de carga
                const btnText = loginBtn.querySelector('.btn-text');
                const btnLoading = loginBtn.querySelector('.btn-loading');
                btnText.classList.add('d-none');
                btnLoading.classList.remove('d-none');
                loginBtn.disabled = true;
            });
            
            // ========================================================================
            // Limpiar estado de error al escribir
            // ========================================================================
            passwordInput.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
            
            // ========================================================================
            // Limpiar parámetros de la URL después de mostrar el mensaje
            // ========================================================================
            if (window.location.search.includes('logout=1')) {
                // Esperar 3 segundos antes de limpiar la URL
                setTimeout(function() {
                    // Limpiar el parámetro de la URL sin recargar la página
                    const url = new URL(window.location);
                    url.searchParams.delete('logout');
                    window.history.replaceState({}, '', url.toString());
                }, 3000);
            }
            
            // Limpiar parámetro registro exitoso de la URL después de mostrar el mensaje
            if (window.location.search.includes('registro=exitoso')) {
                // Esperar 3 segundos antes de limpiar la URL
                setTimeout(function() {
                    // Limpiar el parámetro de la URL sin recargar la página
                    const url = new URL(window.location);
                    url.searchParams.delete('registro');
                    window.history.replaceState({}, '', url.toString());
                }, 3000);
            }
            
            // ========================================================================
            // Animación suave de entrada
            // ========================================================================
            const authCard = document.querySelector('.auth-card');
            authCard.style.opacity = '0';
            authCard.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                authCard.style.transition = 'all 0.5s ease';
                authCard.style.opacity = '1';
                authCard.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>

<?php include 'includes/footer.php'; render_footer(); ?>

