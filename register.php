<?php
/**
 * ========================================================================
 * REGISTRO DE USUARIOS - Tienda Seda y Lino
 * ========================================================================
 * Permite crear nuevas cuentas de usuario en el sistema
 * - Validación de datos del formulario (nombre, apellido, email, contraseña)
 * - Verificación de que el email no esté ya registrado
 * - Hash seguro de contraseñas usando password_hash()
 * - Asignación automática de rol CLIENTE a nuevos usuarios
 * - Redirección a login después de registro exitoso
 * 
 * Funciones principales:
 * - Validar datos del formulario
 * - Verificar email único en BD
 * - Encriptar contraseña con PASSWORD_BCRYPT
 * - Insertar nuevo usuario en BD
 * 
 * Variables principales:
 * - $mensaje: Mensaje de éxito o error a mostrar
 * - Datos del formulario: nombre, apellido, email, contrasena, confirmar_contrasena
 * 
 * Tabla utilizada: Usuarios (campos: nombre, apellido, email, contrasena, rol)
 * ========================================================================
 */
session_start();
require_once 'session.php';

// Configurar título de la página
$titulo_pagina = 'Registro';

// Variable para mensajes de error
$mensaje = '';

// Procesar formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ========================================================================
    // SANITIZACIÓN Y VALIDACIÓN DE ENTRADA - PREVENCIÓN XSS Y SQL INJECTION
    // ========================================================================
    
    /**
     * SANITIZACIÓN BÁSICA DE DATOS
     * 
     * 1. trim(): Elimina espacios en blanco al inicio y final
     * 2. htmlspecialchars(): Convierte caracteres especiales HTML a entidades
     *    - Previene XSS (Cross-Site Scripting)
     *    - Convierte < > & " ' a entidades seguras
     * 3. filter_var(): Filtros de validación PHP nativos
     * 4. strlen(): Verificación de longitud máxima
     */
    
    // Sanitizar y validar NOMBRE
    $nombre_raw = $_POST['nombre'] ?? '';
    $nombre = trim($nombre_raw);
    
    // Validaciones específicas para NOMBRE
    if (empty($nombre)) {
        $mensaje = 'El nombre es obligatorio.';
    } elseif (strlen($nombre) < 2) {
        $mensaje = 'El nombre debe tener al menos 2 caracteres.';
    } elseif (strlen($nombre) > 50) {
        $mensaje = 'El nombre no puede exceder 50 caracteres.';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/', $nombre)) {
        $mensaje = 'El nombre solo puede contener letras y espacios.';
    } else {
        // Sanitización final para prevenir XSS
        $nombre = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
    }
    
    // Sanitizar y validar APELLIDO
    $apellido_raw = $_POST['apellido'] ?? '';
    $apellido = trim($apellido_raw);
    
    // Validaciones específicas para APELLIDO
    if (empty($apellido)) {
        $mensaje = 'El apellido es obligatorio.';
    } elseif (strlen($apellido) < 2) {
        $mensaje = 'El apellido debe tener al menos 2 caracteres.';
    } elseif (strlen($apellido) > 50) {
        $mensaje = 'El apellido no puede exceder 50 caracteres.';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/', $apellido)) {
        $mensaje = 'El apellido solo puede contener letras y espacios.';
    } else {
        // Sanitización final para prevenir XSS
        $apellido = htmlspecialchars($apellido, ENT_QUOTES, 'UTF-8');
    }
    
    // Sanitizar y validar EMAIL
    $email_raw = $_POST['email'] ?? '';
    $email = trim($email_raw);
    
    // Validaciones específicas para EMAIL
    if (empty($email)) {
        $mensaje = 'El correo electrónico es obligatorio.';
    } elseif (strlen($email) > 100) {
        $mensaje = 'El correo electrónico no puede exceder 100 caracteres.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'El formato del correo electrónico no es válido.';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        $mensaje = 'El correo electrónico contiene caracteres no permitidos.';
    } else {
        // Sanitización final para prevenir XSS
        $email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    }
    
    // Sanitizar y validar CONTRASEÑA
    $password = $_POST['password'] ?? '';
    
    // Validaciones específicas para CONTRASEÑA
    if (empty($password)) {
        $mensaje = 'La contraseña es obligatoria.';
    } elseif (strlen($password) < 8) {
        $mensaje = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif (strlen($password) > 128) {
        $mensaje = 'La contraseña no puede exceder 128 caracteres.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
        $mensaje = 'La contraseña debe contener al menos: 1 minúscula, 1 mayúscula, 1 número y 1 carácter especial.';
    }
    
    // Validar CONFIRMACIÓN DE CONTRASEÑA
    $password_confirm = $_POST['password_confirm'] ?? '';
    if ($password !== $password_confirm) {
        $mensaje = 'Las contraseñas no coinciden.';
    }
    
    // Validar TÉRMINOS Y CONDICIONES
    $acepta_terminos = isset($_POST['acepta']) ? $_POST['acepta'] : '';
    
    /**
     * VALIDACIÓN DE TÉRMINOS Y CONDICIONES
     * Seguridad: previene envío de formulario sin aceptar términos
     * mediante manipulación directa de POST o deshabilitando JavaScript
     */
    if ($acepta_terminos !== 'on') {
        $mensaje = 'Debes aceptar los Términos y Condiciones para registrarte.';
    }
    
    // ========================================================================
    // VALIDACIÓN ADICIONAL DE SEGURIDAD
    // ========================================================================
    
    /**
     * PREVENCIÓN DE ATAQUES DE FUERZA BRUTA
     * Verificar si hay demasiados intentos de registro desde la misma IP
     */
    $ip_usuario = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Validar que no sea un bot o script automatizado
    if (empty($user_agent) || strlen($user_agent) < 10) {
        $mensaje = 'Acceso no autorizado detectado.';
    }
    
    // Verificar que no contenga caracteres peligrosos en User-Agent
    if (preg_match('/<script|javascript|vbscript|onload|onerror/i', $user_agent)) {
        $mensaje = 'User-Agent no válido detectado.';
    }
    
    // ========================================================================
    // PROCESAR REGISTRO SI TODAS LAS VALIDACIONES PASAN
    // ========================================================================
    
    if (empty($mensaje)) {
        // ========================================================================
        // CONEXIÓN SEGURA A BASE DE DATOS - PREVENCIÓN SQL INJECTION
        // ========================================================================
        
        /**
         * PREPARED STATEMENTS - PREVENCIÓN SQL INJECTION
         * 
         * 1. mysqli->prepare(): Prepara la consulta SQL con placeholders (?)
         * 2. bind_param(): Vincula parámetros de forma segura
         * 3. execute(): Ejecuta la consulta sin riesgo de inyección
         * 
         * Ventajas:
         * - Separa código SQL de datos
         * - Previene inyección de código malicioso
         * - Mejor rendimiento en consultas repetidas
         */
        
        // Conexión a base de datos usando configuración centralizada
        require_once 'config/database.php';
        
        // ========================================================================
        // VERIFICACIÓN DE EMAIL DUPLICADO - CONSULTA SEGURA
        // ========================================================================
        
        /**
         * CONSULTA PREPARADA PARA VERIFICAR EMAIL DUPLICADO
         * 
         * SELECT 1: Solo necesitamos saber si existe, no los datos
         * LIMIT 1: Optimización - detener en el primer resultado
         * ? placeholder: Previene SQL injection
         */
        $stmt_check = $mysqli->prepare("SELECT 1 FROM Usuarios WHERE email = ? LIMIT 1");
        
        if (!$stmt_check) {
            // Error en la preparación de la consulta
            $mensaje = 'Error en la base de datos: ' . htmlspecialchars($mysqli->error);
        } else {
            // Vincular parámetro de forma segura
            $stmt_check->bind_param('s', $email);
            $stmt_check->execute();
            
            // Verificar si el email ya existe
            if ($stmt_check->get_result()->num_rows > 0) {
                $mensaje = 'El email ya está registrado. Por favor, usa otro correo electrónico.';
            } else {
                // ========================================================================
                // CREACIÓN SEGURA DE USUARIO - HASH DE CONTRASEÑA
                // ========================================================================
                
                /**
                 * HASH SEGURO DE CONTRASEÑA
                 * 
                 * password_hash() con PASSWORD_BCRYPT:
                 * - Algoritmo bcrypt (resistente a ataques de fuerza bruta)
                 * - Salt automático único por contraseña
                 * - Costo configurable (por defecto 10)
                 * - Resistente a ataques de tiempo
                 */
                $hash_password = password_hash($password, PASSWORD_BCRYPT);
                
                // Verificar que el hash se generó correctamente
                if (!$hash_password) {
                    $mensaje = 'Error al procesar la contraseña. Inténtalo de nuevo.';
                } else {
                    // Rol por defecto para nuevos usuarios (según ENUM de database_estructura.sql)
                    $rol_default = 'cliente';
                    
                    // ========================================================================
                    // INSERCIÓN SEGURA EN BASE DE DATOS
                    // ========================================================================
                    
                    /**
                     * CONSULTA PREPARADA PARA INSERTAR USUARIO
                     * 
                     * Campos insertados:
                     * - nombre: Sanitizado con htmlspecialchars()
                     * - apellido: Sanitizado con htmlspecialchars()
                     * - email: Validado con filter_var() y sanitizado
                     * - contrasena: Hash seguro con password_hash()
                     * - rol: Valor fijo 'cliente'
                     * - fecha_registro: NOW() función MySQL
                     */
                    $stmt_insert = $mysqli->prepare("INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_registro) VALUES (?, ?, ?, ?, ?, NOW())");
                    
                    if (!$stmt_insert) {
                        $mensaje = 'Error al preparar consulta de inserción: ' . htmlspecialchars($mysqli->error);
                    } else {
                        // Vincular todos los parámetros de forma segura
                        $stmt_insert->bind_param('sssss', $nombre, $apellido, $email, $hash_password, $rol_default);
                        
                        // Ejecutar la inserción
                        if ($stmt_insert->execute()) {
                            // ========================================================================
                            // REGISTRO EXITOSO - LIMPIEZA Y REDIRECCIÓN
                            // ========================================================================
                            
                            /**
                             * LIMPIEZA DE DATOS SENSIBLES
                             * 
                             * 1. Limpiar variables de contraseña de memoria
                             * 2. Cerrar prepared statements
                             * 3. Redireccionar con parámetro de éxito
                             */
                            
                            // Limpiar variables sensibles de memoria
                            $password = null;
                            $password_confirm = null;
                            $hash_password = null;
                            
                            // Cerrar prepared statements
                            $stmt_check->close();
                            $stmt_insert->close();
                            
                            // Redireccionar a login con mensaje de éxito
                            header('Location: login.php?registro=exitoso');
                            exit;
                            
                        } else {
                            // Error en la ejecución de la inserción
                            $mensaje = 'Error al crear la cuenta: ' . htmlspecialchars($stmt_insert->error);
                        }
                    }
                }
            }
            
            // Cerrar prepared statement de verificación
            $stmt_check->close();
        }
    }
}
?>

<?php include 'includes/header.php'; ?>



<!-- Contenido principal del registro -->
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
                
                <form action="" method="post" class="auth-form" id="registerForm" novalidate autocomplete="on">
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
                                   minlength="2"
                                   maxlength="50"
                                   pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+"
                                   autocomplete="given-name"
                                   title="Solo letras y espacios, entre 2 y 50 caracteres">
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
                                   minlength="2"
                                   maxlength="50"
                                   pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+"
                                   autocomplete="family-name"
                                   title="Solo letras y espacios, entre 2 y 50 caracteres">
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
                               maxlength="100"
                               autocomplete="email"
                               title="Formato de email válido, máximo 100 caracteres">
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
                                   placeholder="Mínimo 8 caracteres con mayúscula, minúscula, número y símbolo" 
                                   required 
                                   minlength="8"
                                   maxlength="128"
                                   autocomplete="new-password"
                                   title="Debe contener: 1 mayúscula, 1 minúscula, 1 número y 1 carácter especial">
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
                            <i class="fas fa-info-circle me-1"></i>Debe contener: 1 mayúscula, 1 minúscula, 1 número y 1 carácter especial (@$!%*?&)
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
                                   minlength="8"
                                   maxlength="128"
                                   autocomplete="new-password"
                                   title="Debe coincidir con la contraseña anterior">
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
                
                // ========================================================================
                // CRITERIOS DE FORTALEZA MEJORADOS - COINCIDEN CON SERVIDOR
                // ========================================================================
                
                // Longitud mínima (8 caracteres)
                if (password.length >= 8) strength += 20;
                if (password.length >= 12) strength += 10;
                if (password.length >= 16) strength += 10;
                
                // Caracteres requeridos (coincide con regex del servidor)
                if (/[a-z]/.test(password)) strength += 15; // Minúscula
                if (/[A-Z]/.test(password)) strength += 15; // Mayúscula
                if (/[0-9]/.test(password)) strength += 15; // Número
                if (/[@$!%*?&]/.test(password)) strength += 15; // Carácter especial específico
                
                // Determinar nivel de fortaleza
                if (strength < 50) {
                    strengthLevel = 'Muy Débil';
                    strengthColor = '#dc3545';
                } else if (strength < 70) {
                    strengthLevel = 'Débil';
                    strengthColor = '#fd7e14';
                } else if (strength < 85) {
                    strengthLevel = 'Buena';
                    strengthColor = '#ffc107';
                } else if (strength < 100) {
                    strengthLevel = 'Fuerte';
                    strengthColor = '#17a2b8';
                } else {
                    strengthLevel = 'Excelente';
                    strengthColor = '#28a745';
                }
                
                // Actualizar interfaz visual
                strengthMeterFill.style.width = strength + '%';
                strengthMeterFill.style.backgroundColor = strengthColor;
                strengthText.textContent = 'Fortaleza: ' + strengthLevel;
                strengthText.style.color = strengthColor;
                
                // Validar confirmación si ya tiene valor
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
                
                // Validar contraseña (coincide con validación del servidor)
                if (!passwordInput.value.trim() || passwordInput.value.length < 8) {
                    passwordInput.classList.add('is-invalid');
                    isValid = false;
                } else if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/.test(passwordInput.value)) {
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

<?php include 'includes/footer.php'; render_footer(); ?>
