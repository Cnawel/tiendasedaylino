<?php
/**
 * PÁGINA DE LOGIN SIMPLE
 * Login básico sin cookies
 */

session_start();
require_once 'config/database.php';

$error = '';

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

// Procesar formulario
if ($_POST) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        $user = checkLogin($email, $password);
        if ($user) {
            // Crear sesión
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['user_name'] = $user['nombre'] . ' ' . $user['apellido'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['rol'];
            
            // Redirigir según rol
            if ($user['rol'] == 'cliente') {
                header('Location: index.php');
            } else {
                header('Location: dashboard/' . $user['rol'] . '.php');
            }
            exit;
        } else {
            $error = 'Email o contraseña incorrectos';
        }
    } else {
        $error = 'Completa todos los campos';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Seda y Lino</title>
    
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
                        <!-- Menú de usuario -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle" style="font-size: 1.5rem; color: #2c3e50;"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="login.php"><i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión</a></li>
                                <li><a class="dropdown-item" href="registro.php"><i class="fas fa-user-plus me-2"></i>Registrarse</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <!-- ============================================================================
             CONTENIDO PRINCIPAL DE LOGIN
             ============================================================================ -->
        
        <div class="container-fluid py-5">
            <div class="row justify-content-center">
                <div class="col-12">
                    <div class="login-container">
                        <!-- Sección de Login -->
                        <div class="login-section">
                            <h1 class="login-title">Iniciar<br>Sesión</h1>
                            
                            <?php if (isset($_GET['mensaje']) && $_GET['mensaje'] == 'logout'): ?>
                                <div class="alert alert-success" role="alert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Sesión cerrada correctamente.
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Separador -->
                            <div class="divider">
                                <span>INGRESA TUS DATOS</span>
                            </div>
                            
                            <!-- Formulario -->
                            <form method="POST">
                                <div class="form-group">
                                    <label for="email" class="form-label">Correo Electrónico</label>
                                    <input type="email" 
                                           id="email" 
                                           name="email" 
                                           class="form-control" 
                                           placeholder="CORREO ELECTRÓNICO"
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password" class="form-label">Contraseña</label>
                                    <input type="password" 
                                           id="password" 
                                           name="password" 
                                           class="form-control" 
                                           placeholder="CONTRASEÑA"
                                           required>
                                </div>
                                
                                <div class="forgot-password">
                                    <a href="#" onclick="alert('Función próximamente')">
                                        ¿Olvidaste tu contraseña?
                                    </a>
                                </div>
                                
                                <button type="submit" class="login-btn">
                                    Iniciar Sesión
                                </button>
                            </form>
                            
                            <div class="signup-link">
                                ¿NUEVO AQUÍ? <a href="registro.php">REGISTRARSE</a>
                            </div>
                        </div>
                        
                        <!-- Sección de Imagen -->
                        <div class="image-section">
                            <img src="imagenes/lino_main_login.webp" 
                                 alt="Seda y Lino - Productos de lino de alta calidad" 
                                 class="login-image">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- ============================================================================
         FOOTER
         ============================================================================ -->
    
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
</body>
</html>