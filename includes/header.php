<?php
/**
 * HEADER COMÚN CON MENÚ DE USUARIO DINÁMICO
 * Incluye navbar con menú desplegable según estado de login
 */

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está logueado
$usuario_logueado = isset($_SESSION['user_id']);
$nombre_usuario = $_SESSION['user_name'] ?? '';
$rol_usuario = $_SESSION['user_role'] ?? '';
?>
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
                    
                    <!-- Menú de usuario dinámico -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle" style="font-size: 1.5rem; color: #2c3e50;"></i>
                            <?php if ($usuario_logueado): ?>
                                <span class="ms-1 d-none d-md-inline"><?php echo htmlspecialchars($nombre_usuario); ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($usuario_logueado): ?>
                                <!-- Usuario logueado -->
                                <li><h6 class="dropdown-header">Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?></h6></li>
                                <li><hr class="dropdown-divider"></li>
                                
                                <?php if ($rol_usuario == 'cliente'): ?>
                                    <li><a class="dropdown-item" href="panel_cliente.php"><i class="fas fa-user me-2"></i>Panel Cliente</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="dashboard/<?php echo $rol_usuario; ?>.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                <?php endif; ?>
                                
                                <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-user-edit me-2"></i>Mi Perfil</a></li>
                                <li><a class="dropdown-item" href="pedidos.php"><i class="fas fa-shopping-bag me-2"></i>Mis Pedidos</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                            <?php else: ?>
                                <!-- Usuario no logueado -->
                                <li><a class="dropdown-item" href="login.php"><i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión</a></li>
                                <li><a class="dropdown-item" href="registro.php"><i class="fas fa-user-plus me-2"></i>Registrarse</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>

