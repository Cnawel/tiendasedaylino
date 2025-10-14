<?php
session_start();

// ============================================================================
// VERIFICACIÓN DE ACCESO - SOLO ADMINISTRADORES
// ============================================================================

// Verificar que el usuario esté logueado
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

// Conectar a la base de datos
$mysqli = new mysqli('localhost', 'root', '', 'tiendasedaylino_db');
if ($mysqli->connect_errno) {
    die('Error de conexión a la base de datos: ' . $mysqli->connect_error);
}

// Verificar que el usuario tenga rol ADMIN
$id_usuario = $_SESSION['id_usuario'];
$stmt = $mysqli->prepare("SELECT rol FROM Usuarios WHERE id_usuario = ? LIMIT 1");
$stmt->bind_param('i', $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$usuario_actual = $result->fetch_assoc();

if (!$usuario_actual || $usuario_actual['rol'] !== 'ADMIN') {
    header('Location: index.php');
    exit;
}

// ============================================================================
// PROCESAR CAMBIOS DE ROL
// ============================================================================

$mensaje = '';
$mensaje_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_rol'])) {
    $usuario_id = intval($_POST['usuario_id']);
    $nuevo_rol = $_POST['nuevo_rol'];
    
    // Validar que el rol sea válido
    $roles_validos = ['CLIENTE', 'VENTAS', 'MARKETING', 'ADMIN'];
    
    if (in_array($nuevo_rol, $roles_validos)) {
        // No permitir que el admin se quite su propio rol de ADMIN
        if ($usuario_id == $id_usuario && $nuevo_rol !== 'ADMIN') {
            $mensaje = 'No puedes cambiar tu propio rol de administrador';
            $mensaje_tipo = 'warning';
        } else {
            $stmt = $mysqli->prepare("UPDATE Usuarios SET rol = ? WHERE id_usuario = ?");
            $stmt->bind_param('si', $nuevo_rol, $usuario_id);
            
            if ($stmt->execute()) {
                $mensaje = 'Rol actualizado correctamente';
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'Error al actualizar el rol';
                $mensaje_tipo = 'danger';
            }
        }
    } else {
        $mensaje = 'Rol no válido';
        $mensaje_tipo = 'danger';
    }
}

// ============================================================================
// OBTENER TODOS LOS USUARIOS
// ============================================================================

$sql = "SELECT id_usuario, nombre, apellido, email, rol, fecha_registro 
        FROM Usuarios 
        ORDER BY 
            CASE rol 
                WHEN 'ADMIN' THEN 1 
                WHEN 'VENTAS' THEN 2 
                WHEN 'MARKETING' THEN 3 
                WHEN 'CLIENTE' THEN 4 
            END,
            apellido, nombre";

$usuarios = $mysqli->query($sql);

// Contar usuarios por rol
$sql_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN rol = 'ADMIN' THEN 1 ELSE 0 END) as admins,
    SUM(CASE WHEN rol = 'VENTAS' THEN 1 ELSE 0 END) as ventas,
    SUM(CASE WHEN rol = 'MARKETING' THEN 1 ELSE 0 END) as marketing,
    SUM(CASE WHEN rol = 'CLIENTE' THEN 1 ELSE 0 END) as clientes
FROM Usuarios";

$stats = $mysqli->query($sql_stats)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración | Seda y Lino</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-page {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .users-table {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .badge-rol {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge-ADMIN {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        
        .badge-VENTAS {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        .badge-MARKETING {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
        }
        
        .badge-CLIENTE {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
        }
        
        .btn-cambiar-rol {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498db, #2ecc71);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 0.5rem;
        }
    </style>
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
                            <a class="nav-link" href="perfil.php" title="Mi Perfil">
                                <img src="iconos/avatar-usuario.png" alt="icono de avatar de usuario">
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="admin-page">
        <div class="container">
            <!-- Header -->
            <div class="admin-header">
                <h1><i class="fas fa-shield-alt me-3"></i>Panel de Administración</h1>
                <p class="mb-0">Gestión de usuarios y roles del sistema</p>
            </div>
            
            <!-- Mensajes -->
            <?php if ($mensaje): ?>
            <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show animate-in" role="alert">
                <?php if ($mensaje_tipo === 'success'): ?>
                    <i class="fas fa-check-circle me-2"></i>
                <?php elseif ($mensaje_tipo === 'warning'): ?>
                    <i class="fas fa-exclamation-triangle me-2"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle me-2"></i>
                <?php endif; ?>
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon text-primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?= $stats['total'] ?></h3>
                        <p class="text-muted mb-0">Total Usuarios</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon text-danger">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3><?= $stats['admins'] ?></h3>
                        <p class="text-muted mb-0">Administradores</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon text-info">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <h3><?= $stats['ventas'] + $stats['marketing'] ?></h3>
                        <p class="text-muted mb-0">Staff (Ventas + Marketing)</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon text-secondary">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <h3><?= $stats['clientes'] ?></h3>
                        <p class="text-muted mb-0">Clientes</p>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de Usuarios -->
            <div class="users-table">
                <h3 class="mb-4"><i class="fas fa-list me-2"></i>Gestión de Usuarios</h3>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Rol Actual</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $usuarios->fetch_assoc()): ?>
                            <tr>
                                <td><?= $user['id_usuario'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar">
                                            <?= strtoupper(substr($user['nombre'], 0, 1) . substr($user['apellido'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($user['nombre'] . ' ' . $user['apellido']) ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="badge badge-rol badge-<?= $user['rol'] ?>">
                                        <?= $user['rol'] ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($user['fecha_registro'])) ?></td>
                                <td>
                                    <button type="button" 
                                            class="btn btn-primary btn-sm btn-cambiar-rol" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#cambiarRolModal<?= $user['id_usuario'] ?>"
                                            <?= ($user['id_usuario'] == $id_usuario) ? 'title="No puedes cambiar tu propio rol"' : '' ?>>
                                        <i class="fas fa-edit me-1"></i>Cambiar Rol
                                    </button>
                                    
                                    <!-- Modal para cambiar rol -->
                                    <div class="modal fade" id="cambiarRolModal<?= $user['id_usuario'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Cambiar Rol de Usuario</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="usuario_id" value="<?= $user['id_usuario'] ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label"><strong>Usuario:</strong></label>
                                                            <p><?= htmlspecialchars($user['nombre'] . ' ' . $user['apellido']) ?></p>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label"><strong>Email:</strong></label>
                                                            <p><?= htmlspecialchars($user['email']) ?></p>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label"><strong>Rol Actual:</strong></label>
                                                            <p><span class="badge badge-rol badge-<?= $user['rol'] ?>"><?= $user['rol'] ?></span></p>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="nuevo_rol<?= $user['id_usuario'] ?>" class="form-label"><strong>Nuevo Rol:</strong></label>
                                                            <select class="form-select" name="nuevo_rol" id="nuevo_rol<?= $user['id_usuario'] ?>" required>
                                                                <option value="CLIENTE" <?= $user['rol'] == 'CLIENTE' ? 'selected' : '' ?>>CLIENTE</option>
                                                                <option value="VENTAS" <?= $user['rol'] == 'VENTAS' ? 'selected' : '' ?>>VENTAS</option>
                                                                <option value="MARKETING" <?= $user['rol'] == 'MARKETING' ? 'selected' : '' ?>>MARKETING</option>
                                                                <option value="ADMIN" <?= $user['rol'] == 'ADMIN' ? 'selected' : '' ?>>ADMIN</option>
                                                            </select>
                                                            <small class="text-muted">
                                                                <i class="fas fa-info-circle me-1"></i>
                                                                <?php if ($user['id_usuario'] == $id_usuario): ?>
                                                                    <strong class="text-warning">No puedes cambiar tu propio rol de ADMIN</strong>
                                                                <?php else: ?>
                                                                    Selecciona el nuevo rol para este usuario
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                        
                                                        <div class="alert alert-info">
                                                            <small>
                                                                <strong>Descripción de roles:</strong><br>
                                                                <strong>CLIENTE:</strong> Usuario estándar con acceso básico<br>
                                                                <strong>VENTAS:</strong> Acceso a gestión de ventas<br>
                                                                <strong>MARKETING:</strong> Acceso a herramientas de marketing<br>
                                                                <strong>ADMIN:</strong> Acceso total al sistema
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" name="cambiar_rol" class="btn btn-primary">
                                                            <i class="fas fa-save me-1"></i>Guardar Cambios
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Botones de acción -->
            <div class="text-center mt-4">
                <a href="perfil.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-user me-2"></i>Mi Perfil
                </a>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-home me-2"></i>Volver al Inicio
                </a>
                <a href="logout.php" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                </a>
            </div>
        </div>
    </main>

    <footer class="footer-completo mt-5">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>

