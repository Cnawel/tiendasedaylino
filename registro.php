<?php
/**
 * PÁGINA DE REGISTRO DE USUARIOS
 * Registro simple para nuevos clientes
 */

session_start();
require_once 'config/database.php';

$error = '';
$success = '';

// Si ya está logueado, redirigir
if (isLoggedIn()) {
    $role = getUserRole();
    if ($role == 'cliente') {
        header('Location: index.html');
    } else {
        header('Location: dashboard/' . $role . '.php');
    }
    exit;
}

// Procesar formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $localidad = trim($_POST['localidad'] ?? '');
    $provincia = trim($_POST['provincia'] ?? '');
    $codigo_postal = trim($_POST['codigo_postal'] ?? '');
    
    // Validaciones básicas
    if (empty($nombre) || empty($apellido) || empty($email) || empty($password)) {
        $error = "Por favor, completa todos los campos obligatorios.";
    } elseif ($password !== $confirm_password) {
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Por favor, ingresa un email válido.";
    } else {
        try {
            // Verificar si el email ya existe
            $stmt_check = $pdo->prepare("SELECT id_usuario FROM Usuarios WHERE email = ?");
            $stmt_check->execute([$email]);
            
            if ($stmt_check->fetch()) {
                $error = "Este email ya está registrado. Por favor, usa otro email o inicia sesión.";
            } else {
                // Insertar nuevo usuario
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt_insert = $pdo->prepare("
                    INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, telefono, direccion, localidad, provincia, codigo_postal, fecha_registro)
                    VALUES (?, ?, ?, ?, 'cliente', ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt_insert->execute([
                    $nombre, $apellido, $email, $hashed_password,
                    $telefono, $direccion, $localidad, $provincia, $codigo_postal
                ]);
                
                $success = "¡Registro exitoso! Ya puedes iniciar sesión con tus credenciales.";
                
                // Limpiar formulario
                $nombre = $apellido = $email = $telefono = $direccion = $localidad = $provincia = $codigo_postal = '';
            }
        } catch (PDOException $e) {
            $error = "Error al registrar usuario. Por favor, intenta nuevamente.";
            error_log("Error en registro: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Seda y Lino</title>
    
    <!-- Estilos personalizados de la tienda -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Bootstrap 5.3.8 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6.0.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="login-page">
    <!-- ============================================================================
         HEADER Y NAVEGACIÓN PRINCIPAL
         ============================================================================ -->
    
    <header>
        <!-- Navegación responsiva con Bootstrap -->
        <nav class="navbar navbar-expand-lg bg-body-tertiary">
            <div class="container-fluid">
                <!-- Logo/Brand de la tienda -->
                <a class="navbar-brand nombre-tienda" href="index.html">SEDA Y LINO</a>
                
                <!-- Botón hamburguesa para dispositivos móviles -->
                <button class="navbar-toggler" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#navbarNav" 
                        aria-controls="navbarNav" 
                        aria-expanded="false" 
                        aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <!-- Menú de navegación colapsable -->
                <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                    <ul class="navbar-nav lista-nav">
                        <!-- Enlace a página de inicio -->
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.html">INICIO</a>
                        </li>
                        <!-- Enlace a página "Nosotros" -->
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="nosotros.html">NOSOTROS</a>
                        </li>
                        <!-- Enlace a sección de productos -->
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.html#productos">PRODUCTOS</a>
                        </li>
                        <!-- Enlace a sección de contacto -->
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.html#contacto">CONTACTO</a>
                        </li>
                        <!-- Enlace a login -->
                        <li class="nav-item">
                            <a class="nav-link" href="login.php" title="Iniciar Sesión">
                                <i class="fas fa-user-circle" style="font-size: 1.5rem; color: #2c3e50;"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="d-flex flex-column justify-content-center align-items-center" style="flex-grow: 1;">
        <div class="login-container">
            <div class="login-section">
                <h2 class="login-title">Crear Cuenta</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <div class="mt-2">
                            <a href="login.php" class="btn btn-success btn-sm">Ir al Login</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="divider"><span>DATOS PERSONALES</span></div>
                
                <form action="registro.php" method="POST" id="registroForm">
                    <!-- Datos personales -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nombre" class="form-label">Nombre *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" 
                                       value="<?php echo htmlspecialchars($nombre ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="apellido" class="form-label">Apellido *</label>
                                <input type="text" class="form-control" id="apellido" name="apellido" 
                                       value="<?php echo htmlspecialchars($apellido ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Correo Electrónico *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password" class="form-label">Contraseña *</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       minlength="6" required>
                                <small class="text-muted">Mínimo 6 caracteres</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirmar Contraseña *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       minlength="6" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="divider mt-4"><span>DATOS DE CONTACTO</span></div>
                    
                    <div class="form-group">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="telefono" name="telefono" 
                               value="<?php echo htmlspecialchars($telefono ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="direccion" class="form-label">Dirección</label>
                        <input type="text" class="form-control" id="direccion" name="direccion" 
                               value="<?php echo htmlspecialchars($direccion ?? ''); ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="localidad" class="form-label">Localidad</label>
                                <input type="text" class="form-control" id="localidad" name="localidad" 
                                       value="<?php echo htmlspecialchars($localidad ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="provincia" class="form-label">Provincia</label>
                                <input type="text" class="form-control" id="provincia" name="provincia" 
                                       value="<?php echo htmlspecialchars($provincia ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="codigo_postal" class="form-label">Código Postal</label>
                        <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" 
                               value="<?php echo htmlspecialchars($codigo_postal ?? ''); ?>">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="terminos" required>
                        <label class="form-check-label" for="terminos">
                            Acepto los <a href="#" class="text-decoration-none">términos y condiciones</a> *
                        </label>
                    </div>
                    
                    <button type="submit" class="login-btn">Crear Cuenta</button>
                </form>
                
                <div class="signup-link">
                    ¿YA TIENES CUENTA? <a href="login.php">INICIAR SESIÓN</a>
                </div>
            </div>
            
            <div class="image-section d-none d-md-flex">
                <!-- Imagen promocional -->
                <img src="imagenes/lino_main_login.webp" alt="Registro" class="img-fluid login-image">
            </div>
        </div>
    </main>

    <footer>
        <a href="https://www.facebook.com/?locale=es_LA" target="_blank">
            <img class="red-social" src="iconos/facebook.png" alt="icono de facebook">
        </a>
        <a href="https://www.instagram.com/" target="_blank">
            <img class="red-social" src="iconos/instagram.png" alt="icono de instagram">
        </a>
        <a href="https://x.com/?lang=es" target="_blank">
            <img class="red-social" src="iconos/x.png" alt="icono de x">
        </a>
        <h6>2025.Todos los derechos reservados</h6>
    </footer>

    <!-- Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript personalizado -->
    <script>
        /**
         * VALIDACIÓN DE CONTRASEÑAS
         * Verifica que las contraseñas coincidan
         */
        document.getElementById('registroForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Las contraseñas no coinciden. Por favor, verifica e intenta nuevamente.');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 6 caracteres.');
                return false;
            }
        });
        
        /**
         * VALIDACIÓN EN TIEMPO REAL
         * Muestra feedback visual mientras el usuario escribe
         */
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else if (confirmPassword && password === confirmPassword) {
                this.classList.add('is-valid');
                this.classList.remove('is-invalid');
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });
        
        /**
         * VALIDACIÓN DE EMAIL
         * Verifica formato de email en tiempo real
         */
        document.getElementById('email').addEventListener('input', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else if (email && emailRegex.test(email)) {
                this.classList.add('is-valid');
                this.classList.remove('is-invalid');
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });
    </script>
</body>
</html>

