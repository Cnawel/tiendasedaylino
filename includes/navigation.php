<?php
/**
 * ========================================================================
 * NAVEGACIÓN COMÚN - Tienda Seda y Lino
 * ========================================================================
 * Componente de navegación reutilizable para todas las páginas
 * 
 * Funcionalidades:
 * - Navegación consistente entre páginas
 * - Indicador de carrito con cantidad
 * - Enlaces de perfil y logout según estado de sesión
 * - Responsive design con Bootstrap
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

// Incluir funciones de autenticación
require_once 'auth_check.php';

// Verificar si el usuario está logueado
$usuario_logueado = isLoggedIn();
$num_items_carrito = isset($_SESSION['carrito']) ? count($_SESSION['carrito']) : 0;
?>

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
                    
                    <?php if ($usuario_logueado): ?>
                    <!-- Usuario logueado: mostrar acceso a dashboard según rol, carrito, perfil y logout -->
                    <?php if (isAdmin()): ?>
                    <!-- Administradores: SOLO acceso a su panel Admin, sin Marketing ni Ventas -->
                    <li class="nav-item">
                        <a class="nav-link link-tienda" href="admin.php" title="Ir al Panel de Administración">
                            <i class="fas fa-shield-alt me-1"></i>Panel ADMIN
                        </a>
                    </li>
                    <?php else: ?>
                        <?php if (isMarketing()): ?>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="marketing.php" title="Ir al Panel de Marketing">
                                <i class="fas fa-bullhorn me-1"></i>Panel MARKETING
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (isVentas()): ?>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="ventas.php" title="Ir al Panel de Ventas">
                                <i class="fas fa-briefcase me-1"></i>Panel VENTAS
                            </a>
                        </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="carrito.php" title="Carrito">
                            <i class="fas fa-shopping-cart fa-lg"></i>
                            <?php if ($num_items_carrito > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $num_items_carrito ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="perfil.php" title="Mi Perfil">
                            <img src="iconos/avatar-usuario.png" alt="icono de avatar de usuario">
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php" title="Cerrar Sesión">
                            <i class="fas fa-sign-out-alt fa-lg"></i>
                        </a>
                    </li>
                    <?php else: ?>
                    <!-- Usuario no logueado: mostrar solo login -->
                    <li class="nav-item">
                        <a class="nav-link" href="login.php" title="Iniciar Sesión">
                            <img src="iconos/avatar-usuario.png" alt="icono de avatar de usuario">
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>
