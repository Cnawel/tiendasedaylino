<?php
/**
 * ========================================================================
 * LOGIN - Tienda Seda y Lino
 * ========================================================================
 * Sistema de autenticación de usuarios
 * 
 * Funcionalidades:
 * - Validación de credenciales contra tabla Usuarios
 * - Verificación de contraseñas hasheadas con password_verify
 * - Creación de sesión con datos del usuario
 * - Redirección según rol (ADMIN -> admin.php, otros -> perfil.php)
 * 
 * Tabla utilizada: Usuarios (database_estructura.sql)
 * Campos: id_usuario, nombre, apellido, email, contrasena, rol
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */
session_start();
require_once 'session.php';

// Variable para mensajes de error
$mensaje = '';

// ========================================================================
// RATE LIMITING - Protección contra ataques de fuerza bruta
// ========================================================================
/**
 * Configuración del rate limiting:
 * - Máximo 10 intentos fallidos por IP
 * - Ventana de tiempo: 15 minutos
 * - Bloqueo temporal: 15 minutos
 */
$max_intentos = 10;
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
        // Filtrar timestamps antiguos dentro del array de esta IP
        $_SESSION['login_attempts'][$ip] = array_values(array_filter($timestamps, function($timestamp) use ($tiempo_actual, $ventana_tiempo) {
            return ($tiempo_actual - $timestamp) < $ventana_tiempo;
        }));
        
        // Si no quedan timestamps válidos, eliminar la IP
        if (empty($_SESSION['login_attempts'][$ip])) {
            unset($_SESSION['login_attempts'][$ip]);
        }
    } else {
        // Si no es array, eliminar entrada corrupta
        unset($_SESSION['login_attempts'][$ip]);
    }
}

// Verificar si la IP está bloqueada
$intentos_ip = isset($_SESSION['login_attempts'][$ip_cliente]) 
    ? $_SESSION['login_attempts'][$ip_cliente] 
    : [];

if (count($intentos_ip) >= $max_intentos) {
    $primer_intento = min($intentos_ip);
    $tiempo_transcurrido = $tiempo_actual - $primer_intento;
    
    if ($tiempo_transcurrido < $tiempo_bloqueo) {
        $minutos_restantes = ceil(($tiempo_bloqueo - $tiempo_transcurrido) / 60);
        $mensaje = "Demasiados intentos fallidos. Por favor, intenta de nuevo en {$minutos_restantes} minuto(s).";
        $bloqueado = true;
    } else {
        // Restablecer intentos después del período de bloqueo
        $_SESSION['login_attempts'][$ip_cliente] = [];
        $bloqueado = false;
    }
} else {
    $bloqueado = false;
}

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$bloqueado) {
    // Sanitizar datos de entrada
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Conexión a base de datos usando configuración centralizada
    require_once 'config/database.php';
    
    // Preparar consulta para buscar usuario por email
    $stmt = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Verificar si existe el usuario
    if ($row = $result->fetch_assoc()) {
        // Debug: Mostrar información del usuario (solo para debugging)
        // echo "Usuario encontrado: " . $row['email'] . "<br>";
        // echo "Hash en BD: " . substr($row['contrasena'], 0, 20) . "...<br>";
        // echo "Password ingresado: " . $password . "<br>";
        
        // Verificar contraseña usando password_verify (contraseña hasheada en BD)
        if (password_verify($password, $row['contrasena'])) {
            // Login exitoso - limpiar intentos fallidos
            if (isset($_SESSION['login_attempts'][$ip_cliente])) {
                unset($_SESSION['login_attempts'][$ip_cliente]);
            }
            
            // Guardar datos del usuario en la sesión
            $_SESSION['id_usuario'] = $row['id_usuario'];
            $_SESSION['nombre'] = $row['nombre'];
            $_SESSION['apellido'] = $row['apellido'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['rol'] = $row['rol'] ?? 'cliente';  // Valor por defecto si no tiene rol
            
            // Redirigir según el rol del usuario
            if ($_SESSION['rol'] === 'admin') {
                header('Location: admin.php');  // Administradores al panel admin
            } else {
                header('Location: perfil.php'); // Otros usuarios al perfil
            }
            exit;
        } else {
            // Contraseña incorrecta - registrar intento fallido
            if (!isset($_SESSION['login_attempts'][$ip_cliente])) {
                $_SESSION['login_attempts'][$ip_cliente] = [];
            }
            $_SESSION['login_attempts'][$ip_cliente][] = $tiempo_actual;
            
            $intentos_restantes = $max_intentos - count($_SESSION['login_attempts'][$ip_cliente]);
            if ($intentos_restantes > 0) {
                $mensaje = "Contraseña incorrecta. Te quedan {$intentos_restantes} intento(s).";
            } else {
                $mensaje = "Demasiados intentos fallidos. Cuenta bloqueada temporalmente por 15 minutos.";
            }
        }
    } else {
        // Usuario no existe - registrar intento fallido
        if (!isset($_SESSION['login_attempts'][$ip_cliente])) {
            $_SESSION['login_attempts'][$ip_cliente] = [];
        }
        $_SESSION['login_attempts'][$ip_cliente][] = $tiempo_actual;
        
        $intentos_restantes = $max_intentos - count($_SESSION['login_attempts'][$ip_cliente]);
        if ($intentos_restantes > 0) {
            $mensaje = "El usuario no existe. Te quedan {$intentos_restantes} intento(s).";
        } else {
            $mensaje = "Demasiados intentos fallidos. Cuenta bloqueada temporalmente por 15 minutos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | Seda y Lino</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <!-- Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Custom CSS - Con versión para evitar cache -->
    <link rel="stylesheet" href="css/style.css?v=2.0">
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg bg-body-tertiary">
            <div class="container-fluid">
                <a class="navbar-brand nombre-tienda" href="index.php">SEDA Y LINO</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                    <ul class="navbar-nav lista-nav">
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.php">INICIO</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="nosotros.php">NOSOTROS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.php#productos">PRODUCTOS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.php#contacto">CONTACTO</a>
                        </li>
                        <li class="nav-item">
                            <?php if (isset($_SESSION['id_usuario'])): ?>
                                <a class="nav-link" href="perfil.php" title="Mi Perfil">
                                    <img src="iconos/avatar-usuario.png" alt="icono de avatar de usuario">
                                </a>
                            <?php else: ?>
                                <a class="nav-link" href="login.php" title="Iniciar Sesión">
                                    <img src="iconos/avatar-usuario.png" alt="icono de avatar de usuario">
                                </a>
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

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
                
                <?php if (isset($_GET['logout']) && $_GET['logout'] == '1'): ?>
                    <div class="alert alert-info alert-custom mb-4">
                        <i class="fas fa-info-circle me-2"></i>Sesión cerrada correctamente. ¡Hasta pronto!
                    </div>
                <?php endif; ?>
                
                <?php if ($mensaje): ?>
                    <div class="alert alert-danger alert-custom alert-danger-custom mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($mensaje) ?>
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
                               autocomplete="email">
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
                        <label class="form-check-label" for="rememberMe" style="font-family: 'DM Sans', sans-serif; font-size: 0.88em; color: #555;">
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
                
                if (!passwordInput.value.trim()) {
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
</body>
</html>
