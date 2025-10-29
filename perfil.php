<?php
/**
 * ========================================================================
 * PERFIL DE USUARIO - Tienda Seda y Lino
 * ========================================================================
 * Página de perfil donde el usuario puede:
 * - Ver y actualizar sus datos personales
 * - Gestionar dirección de envío
 * - Acceder a sus paneles según su rol (Admin, Marketing, Ventas)
 * 
 * Funciones principales:
 * - Actualización de datos personales (nombre, apellido, teléfono, dirección)
 * - Verificación de acceso mediante requireLogin()
 * 
 * Variables principales:
 * - $id_usuario: ID del usuario en sesión
 * - $mensaje: Mensajes de éxito/error para mostrar al usuario
 * - $mensaje_tipo: Tipo de mensaje (success/error)
 * 
 * Tablas utilizadas: Usuarios
 * ========================================================================
 */
session_start();

// ============================================================================
// VERIFICACIÓN DE ACCESO - USUARIO LOGUEADO
// ============================================================================

// Cargar sistema de autenticación centralizado
require_once 'includes/auth_check.php';

// Verificar que el usuario esté logueado
requireLogin();

// Conectar a la base de datos
require_once 'config/database.php';

// Configurar título de la página
$titulo_pagina = 'Mi Perfil';

$id_usuario = $_SESSION['id_usuario'];
$mensaje = '';
$mensaje_tipo = '';

// Procesar actualización de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['actualizar_datos'])) {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $localidad = trim($_POST['localidad'] ?? '');
        $provincia = trim($_POST['provincia'] ?? '');
        $codigo_postal = trim($_POST['codigo_postal'] ?? '');
        
        $stmt = $mysqli->prepare("UPDATE Usuarios SET nombre=?, apellido=?, telefono=?, direccion=?, localidad=?, provincia=?, codigo_postal=? WHERE id_usuario=?");
        $stmt->bind_param('sssssssi', $nombre, $apellido, $telefono, $direccion, $localidad, $provincia, $codigo_postal, $id_usuario);
        
        if ($stmt->execute()) {
            $_SESSION['nombre'] = $nombre;
            $_SESSION['apellido'] = $apellido;
            $mensaje = 'Datos actualizados correctamente';
            $mensaje_tipo = 'success';
        } else {
            $mensaje = 'Error al actualizar los datos';
            $mensaje_tipo = 'danger';
        }
    }
}

// Obtener datos del usuario
$stmt = $mysqli->prepare("SELECT nombre, apellido, email, telefono, direccion, localidad, provincia, codigo_postal FROM Usuarios WHERE id_usuario = ? LIMIT 1");
$stmt->bind_param('i', $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

if (!$usuario) {
    header('Location: logout.php');
    exit;
}
?>

<?php include 'includes/header.php'; ?>

    <main class="perfil-page">
        <div class="container">
            <div class="perfil-header">
                <h2><i class="fas fa-user-circle me-2"></i>Mi Perfil</h2>
                <p>Gestiona tu información personal y datos de envío</p>
            </div>
            
            <?php if ($mensaje): ?>
            <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show animate-in" role="alert">
                <?php if ($mensaje_tipo === 'success'): ?>
                    <i class="fas fa-check-circle me-2"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle me-2"></i>
                <?php endif; ?>
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <div class="row justify-content-center">
                <!-- Información Personal -->
                <div class="col-lg-5 col-md-10 mb-4">
                    <div class="perfil-card">
                        <h4><i class="fas fa-user me-2"></i>Información Personal</h4>
                        
                        <form method="POST" action="" data-protect>
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>" required minlength="2">
                            </div>
                            
                            <div class="mb-3">
                                <label for="apellido" class="form-label">Apellido</label>
                                <input type="text" class="form-control" id="apellido" name="apellido" value="<?= htmlspecialchars($usuario['apellido']) ?>" required minlength="2">
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($usuario['email']) ?>" readonly disabled>
                                <small class="text-muted"><i class="fas fa-lock me-1"></i>El email no puede ser modificado</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>" placeholder="Ej: +54 9 11 1234-5678" pattern="[+0-9\s\-()]+">
                                <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Formato: código país + área + número</small>
                            </div>
                            
                            <h5 class="mt-4 mb-3"><i class="fas fa-map-marker-alt me-2"></i>Datos de Envío</h5>
                            
                            <div class="mb-3">
                                <label for="direccion" class="form-label">Dirección Completa</label>
                                <input type="text" class="form-control" id="direccion" name="direccion" value="<?= htmlspecialchars($usuario['direccion'] ?? '') ?>" placeholder="Ej: Av. Corrientes 1234, Piso 5, Depto. B">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="localidad" class="form-label">Localidad / Ciudad</label>
                                    <input type="text" class="form-control" id="localidad" name="localidad" value="<?= htmlspecialchars($usuario['localidad'] ?? '') ?>" placeholder="Ej: Buenos Aires">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="provincia" class="form-label">Provincia / Estado</label>
                                    <input type="text" class="form-control" id="provincia" name="provincia" value="<?= htmlspecialchars($usuario['provincia'] ?? '') ?>" placeholder="Ej: Buenos Aires">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="codigo_postal" class="form-label">Código Postal</label>
                                <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" value="<?= htmlspecialchars($usuario['codigo_postal'] ?? '') ?>" placeholder="Ej: C1043">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="actualizar_datos" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Resumen de Cuenta -->
                <div class="col-lg-5 col-md-10 mb-4">
                    <div class="perfil-card">
                        <h4><i class="fas fa-info-circle me-2"></i>Resumen de Cuenta</h4>
                        
                        <div class="perfil-info-item">
                            <strong><i class="fas fa-envelope me-2"></i>Email</strong>
                            <span><?= htmlspecialchars($usuario['email']) ?></span>
                        </div>
                        
                        <?php if ($usuario['telefono']): ?>
                        <div class="perfil-info-item">
                            <strong><i class="fas fa-phone me-2"></i>Teléfono</strong>
                            <span><?= htmlspecialchars($usuario['telefono']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($usuario['direccion']): ?>
                        <div class="perfil-info-item">
                            <strong><i class="fas fa-map-marker-alt me-2"></i>Dirección de Envío</strong>
                            <span>
                                <?= htmlspecialchars($usuario['direccion']) ?><br>
                                <?php if ($usuario['localidad']): ?><?= htmlspecialchars($usuario['localidad']) ?>, <?php endif; ?>
                                <?php if ($usuario['provincia']): ?><?= htmlspecialchars($usuario['provincia']) ?><?php endif; ?>
                                <?php if ($usuario['codigo_postal']): ?> (<?= htmlspecialchars($usuario['codigo_postal']) ?>)<?php endif; ?>
                            </span>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>¡Completa tus datos de envío!</strong><br>
                            Agrega tu dirección para agilizar tus futuras compras.
                        </div>
                        <?php endif; ?>
                        
                        <hr class="my-4">
                        
                        <div class="d-grid gap-2">
                            <?php 
                            // Mostrar todos los paneles disponibles según el rol del usuario
                            // Los admins pueden acceder a todos los paneles
                            ?>
                            <?php if (isAdmin()): ?>
                            <a href="admin.php" class="btn btn-danger">
                                <i class="fas fa-shield-alt me-2"></i>Panel de Administración
                            </a>
                            <a href="marketing.php" class="btn btn-warning">
                                <i class="fas fa-bullhorn me-2"></i>Panel de Marketing
                            </a>
                            <a href="ventas.php" class="btn btn-info">
                                <i class="fas fa-briefcase me-2"></i>Panel de Ventas
                            </a>
                            <?php else: ?>
                                <?php if (isMarketing()): ?>
                                <a href="marketing.php" class="btn btn-warning">
                                    <i class="fas fa-bullhorn me-2"></i>Panel de Marketing
                                </a>
                                <?php endif; ?>
                                <?php if (isVentas()): ?>
                                <a href="ventas.php" class="btn btn-info">
                                    <i class="fas fa-briefcase me-2"></i>Panel de Ventas
                                </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            <a href="index.php" class="btn btn-outline-primary">
                                <i class="fas fa-home me-2"></i>Volver al Inicio
                            </a>
                            <a href="logout.php" class="btn btn-outline-danger">
                                <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                            </a>
                        </div>
                    </div>
                    
                    <!-- Tips de Seguridad -->
                    <div class="perfil-card mt-3">
                        <h5><i class="fas fa-shield-alt me-2"></i>Consejos de Seguridad</h5>
                        <ul class="list-unstyled small">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Nunca compartas tu contraseña</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Mantén tu información actualizada</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Cierra sesión en dispositivos compartidos</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Verifica tus datos de envío antes de comprar</li>
                        </ul>
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


<?php include 'includes/footer.php'; render_footer(); ?>
