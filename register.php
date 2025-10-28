<?php
/**
 * ========================================================================
 * REGISTRO DE USUARIOS - Tienda Seda y Lino
 * ========================================================================
 * Sistema de registro para nuevos usuarios
 * 
 * Funcionalidades:
 * - Validación de datos del formulario
 * - Verificación de email único
 * - Hash seguro de contraseñas con PASSWORD_BCRYPT
 * - Inserción en tabla Usuarios con rol CLIENTE por defecto
 * - Redirección a login tras registro exitoso
 * 
 * Tabla utilizada: Usuarios (database_estructura.sql)
 * Campos: nombre, apellido, email, contrasena, rol, fecha_registro
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */
session_start();
require_once 'session.php';

// Variable para mensajes de error
$mensaje = '';

// Procesar formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar datos de entrada
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $acepta_terminos = isset($_POST['acepta']) ? $_POST['acepta'] : '';
    
    // ========================================================================
    // VALIDACIÓN DEL LADO DEL SERVIDOR
    // ========================================================================
    /**
     * Validación de términos y condiciones
     * Seguridad: previene envío de formulario sin aceptar términos
     * mediante manipulación directa de POST o deshabilitando JavaScript
     */
    if ($acepta_terminos !== 'on') {
        $mensaje = 'Debes aceptar los Términos y Condiciones para registrarte.';
    } else {
        // Conexión a base de datos usando configuración centralizada
        require_once 'config/database.php';
        
        // Verificar si el email ya está registrado
        $stmt = $mysqli->prepare("SELECT 1 FROM Usuarios WHERE email = ? LIMIT 1");
        if (!$stmt) {
            $mensaje = 'Error en la base de datos: ' . htmlspecialchars($mysqli->error);
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows) {
                // Email duplicado
                $mensaje = 'El email ya está registrado.';
            } else {
                // Hash seguro de la contraseña
                $hash = password_hash($password, PASSWORD_BCRYPT);
                
                // Rol por defecto para nuevos usuarios (minúscula según ENUM de database_estructura.sql)
                $rol_default = 'cliente';
                
                // Insertar nuevo usuario en tabla Usuarios
                $stmt = $mysqli->prepare("INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_registro) VALUES (?,?,?,?,?,NOW())");
                if (!$stmt) {
                    $mensaje = 'Error al preparar consulta: ' . htmlspecialchars($mysqli->error);
                } else {
                    $stmt->bind_param('sssss', $nombre, $apellido, $email, $hash, $rol_default);
                    
                    if ($stmt->execute()) {
                        // Registro exitoso - redirigir a login
                        header('Location: login.php?nuevo=1'); 
                        exit;
                    } else {
                        // Error en la inserción con detalles
                        $mensaje = 'Error al crear cuenta: ' . htmlspecialchars($stmt->error);
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro | Seda y Lino</title>
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
                    <p>Únete a nuestra comunidad</p>
                </div>
                
                <h3 class="auth-title">Crear Cuenta</h3>
                
                <?php if ($mensaje): ?>
                    <div class="alert alert-danger alert-custom alert-danger-custom mb-4">
                        <?= htmlspecialchars($mensaje) ?>
                    </div>
                <?php endif; ?>
                
                <form action="" method="post" class="auth-form" id="registerForm" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">
                                <i class="fas fa-user me-1"></i>Nombre
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   name="nombre" 
                                   id="nombre" 
                                   placeholder="Tu nombre" 
                                   required
                                   autocomplete="given-name">
                            <div class="invalid-feedback">Por favor, ingresa tu nombre.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="apellido" class="form-label">
                                <i class="fas fa-user me-1"></i>Apellido
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   name="apellido" 
                                   id="apellido" 
                                   placeholder="Tu apellido" 
                                   required
                                   autocomplete="family-name">
                            <div class="invalid-feedback">Por favor, ingresa tu apellido.</div>
                        </div>
                    </div>
                    
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
                                   placeholder="Mínimo 6 caracteres" 
                                   required 
                                   minlength="6"
                                   autocomplete="new-password">
                            <button type="button" class="btn-toggle-password" id="togglePassword" aria-label="Mostrar contraseña">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength mt-2" id="passwordStrength">
                            <div class="strength-meter">
                                <div class="strength-meter-fill" id="strengthMeterFill"></div>
                            </div>
                            <small class="strength-text" id="strengthText"></small>
                        </div>
                        <small class="form-text text-muted">
                            <i class="fas fa-info-circle me-1"></i>Usa al menos 8 caracteres, incluye mayúsculas, minúsculas y números
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">
                            <i class="fas fa-lock me-1"></i>Confirmar Contraseña
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   class="form-control" 
                                   name="password_confirm" 
                                   id="password_confirm" 
                                   placeholder="Repite tu contraseña" 
                                   required
                                   autocomplete="new-password">
                            <button type="button" class="btn-toggle-password" id="togglePasswordConfirm" aria-label="Mostrar contraseña">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Las contraseñas no coinciden.</div>
                        <div class="valid-feedback">Las contraseñas coinciden</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input class="form-check-input" type="checkbox" required id="acepta" name="acepta">
                        <label class="form-check-label form-check-label-custom" for="acepta">
                            Acepto los <a href="terminos.php" target="_blank">Términos y Condiciones</a>
                        </label>
                        <div class="invalid-feedback">Debes aceptar los términos y condiciones.</div>
                    </div>
                    
                    <div class="mb-3">
                        <button type="submit" class="btn auth-btn" id="registerBtn">
                            <span class="btn-text">Crear Cuenta</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                Creando cuenta...
                            </span>
                        </button>
                    </div>
                </form>
                
                <div class="auth-links">
                    <div class="mb-2">
                        <a href="login.php">¿Ya tienes cuenta? Inicia sesión aquí</a>
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
        // REGISTER - JavaScript para mejorar UX
        // ============================================================================
        
        document.addEventListener('DOMContentLoaded', function() {
            const registerForm = document.getElementById('registerForm');
            const nombreInput = document.getElementById('nombre');
            const apellidoInput = document.getElementById('apellido');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const passwordConfirmInput = document.getElementById('password_confirm');
            const togglePasswordBtn = document.getElementById('togglePassword');
            const togglePasswordConfirmBtn = document.getElementById('togglePasswordConfirm');
            const registerBtn = document.getElementById('registerBtn');
            const aceptaCheckbox = document.getElementById('acepta');
            const strengthMeterFill = document.getElementById('strengthMeterFill');
            const strengthText = document.getElementById('strengthText');
            
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
            // Medidor de fortaleza de contraseña
            // ========================================================================
            passwordInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
            });
            
            function checkPasswordStrength(password) {
                let strength = 0;
                let strengthLevel = '';
                let strengthColor = '';
                
                if (password.length === 0) {
                    strengthMeterFill.style.width = '0%';
                    strengthText.textContent = '';
                    return;
                }
                
                // Criterios de fortaleza
                if (password.length >= 6) strength += 20;
                if (password.length >= 8) strength += 20;
                if (password.length >= 12) strength += 10;
                if (/[a-z]/.test(password)) strength += 15;
                if (/[A-Z]/.test(password)) strength += 15;
                if (/[0-9]/.test(password)) strength += 10;
                if (/[^a-zA-Z0-9]/.test(password)) strength += 10;
                
                // Determinar nivel
                if (strength < 40) {
                    strengthLevel = 'Débil';
                    strengthColor = '#dc3545';
                } else if (strength < 60) {
                    strengthLevel = 'Regular';
                    strengthColor = '#ffc107';
                } else if (strength < 80) {
                    strengthLevel = 'Buena';
                    strengthColor = '#17a2b8';
                } else {
                    strengthLevel = 'Excelente';
                    strengthColor = '#28a745';
                }
                
                // Actualizar UI
                strengthMeterFill.style.width = strength + '%';
                strengthMeterFill.style.backgroundColor = strengthColor;
                strengthText.textContent = 'Fortaleza: ' + strengthLevel;
                strengthText.style.color = strengthColor;
                
                // Validar también confirmación si ya tiene valor
                if (passwordConfirmInput.value) {
                    validatePasswordConfirm();
                }
            }
            
            // ========================================================================
            // Toggle mostrar/ocultar contraseñas
            // ========================================================================
            function setupPasswordToggle(button, input) {
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
            
            setupPasswordToggle(togglePasswordBtn, passwordInput);
            setupPasswordToggle(togglePasswordConfirmBtn, passwordConfirmInput);
            
            // ========================================================================
            // Validación de confirmación de contraseña
            // ========================================================================
            passwordConfirmInput.addEventListener('input', validatePasswordConfirm);
            passwordConfirmInput.addEventListener('blur', validatePasswordConfirm);
            
            function validatePasswordConfirm() {
                if (passwordConfirmInput.value === '') {
                    passwordConfirmInput.classList.remove('is-valid', 'is-invalid');
                } else if (passwordInput.value === passwordConfirmInput.value) {
                    passwordConfirmInput.classList.remove('is-invalid');
                    passwordConfirmInput.classList.add('is-valid');
                } else {
                    passwordConfirmInput.classList.remove('is-valid');
                    passwordConfirmInput.classList.add('is-invalid');
                }
            }
            
            // ========================================================================
            // Validación del formulario al enviar
            // ========================================================================
            registerForm.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Validar nombre
                if (!nombreInput.value.trim()) {
                    nombreInput.classList.add('is-invalid');
                    isValid = false;
                }
                
                // Validar apellido
                if (!apellidoInput.value.trim()) {
                    apellidoInput.classList.add('is-invalid');
                    isValid = false;
                }
                
                // Validar email
                if (!emailInput.value.trim() || !emailInput.classList.contains('is-valid')) {
                    emailInput.classList.add('is-invalid');
                    isValid = false;
                }
                
                // Validar contraseña
                if (!passwordInput.value.trim() || passwordInput.value.length < 6) {
                    passwordInput.classList.add('is-invalid');
                    isValid = false;
                }
                
                // Validar confirmación
                if (passwordInput.value !== passwordConfirmInput.value) {
                    passwordConfirmInput.classList.add('is-invalid');
                    isValid = false;
                }
                
                // Validar términos
                if (!aceptaCheckbox.checked) {
                    aceptaCheckbox.classList.add('is-invalid');
                    isValid = false;
                }
                
                // Si no es válido, prevenir envío
                if (!isValid) {
                    e.preventDefault();
                    
                    // Scroll al primer error
                    const firstError = registerForm.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstError.focus();
                    }
                    return;
                }
                
                // Mostrar estado de carga
                const btnText = registerBtn.querySelector('.btn-text');
                const btnLoading = registerBtn.querySelector('.btn-loading');
                btnText.classList.add('d-none');
                btnLoading.classList.remove('d-none');
                registerBtn.disabled = true;
            });
            
            // ========================================================================
            // Limpiar errores al interactuar
            // ========================================================================
            nombreInput.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.classList.remove('is-invalid');
                }
            });
            
            apellidoInput.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.classList.remove('is-invalid');
                }
            });
            
            aceptaCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    this.classList.remove('is-invalid');
                }
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
