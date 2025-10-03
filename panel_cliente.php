<?php
/**
 * PANEL DE CLIENTE
 * Dashboard específico para usuarios con rol 'cliente'
 */

session_start();
require_once 'config/database.php';

// Verificar que el usuario esté logueado y sea cliente
if (!isLoggedIn() || getUserRole() !== 'cliente') {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['user_id'];
$nombre_usuario = $_SESSION['user_name'];

// Obtener información del usuario
$stmt_usuario = $pdo->prepare("SELECT * FROM Usuarios WHERE id_usuario = ?");
$stmt_usuario->execute([$usuario_id]);
$usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);

// Obtener pedidos del usuario (simulado)
$pedidos = [
    ['id' => 1, 'fecha' => '2025-01-15', 'total' => 35000, 'estado' => 'Enviado'],
    ['id' => 2, 'fecha' => '2025-01-10', 'total' => 42000, 'estado' => 'Entregado'],
    ['id' => 3, 'fecha' => '2025-01-05', 'total' => 28000, 'estado' => 'Procesando']
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Cliente - Seda y Lino</title>
    
    <!-- Estilos personalizados de la tienda -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Bootstrap 5.3.8 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6.0.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <!-- Header -->
    <?php include 'includes/header.php'; ?>

    <main class="py-5">
        <div class="container">
            <!-- Bienvenida -->
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="display-5 fw-bold text-center mb-3">Panel de Cliente</h1>
                    <p class="lead text-center text-muted">Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?></p>
                </div>
            </div>

            <!-- Resumen de cuenta -->
            <div class="row mb-5">
                <div class="col-md-4 mb-3">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <i class="fas fa-shopping-bag fa-3x text-primary mb-3"></i>
                            <h5 class="card-title">Mis Pedidos</h5>
                            <p class="card-text"><?php echo count($pedidos); ?> pedidos realizados</p>
                            <a href="#pedidos" class="btn btn-primary">Ver Pedidos</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <i class="fas fa-heart fa-3x text-danger mb-3"></i>
                            <h5 class="card-title">Favoritos</h5>
                            <p class="card-text">0 productos favoritos</p>
                            <a href="#" class="btn btn-outline-danger">Ver Favoritos</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <i class="fas fa-user-edit fa-3x text-success mb-3"></i>
                            <h5 class="card-title">Mi Perfil</h5>
                            <p class="card-text">Actualizar información personal</p>
                            <a href="perfil.php" class="btn btn-outline-success">Editar Perfil</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información del usuario -->
            <div class="row mb-5">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-user me-2"></i>Información Personal</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($usuario['email']); ?></p>
                                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($usuario['telefono'] ?: 'No registrado'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Dirección:</strong> <?php echo htmlspecialchars($usuario['direccion'] ?: 'No registrada'); ?></p>
                                    <p><strong>Localidad:</strong> <?php echo htmlspecialchars($usuario['localidad'] ?: 'No registrada'); ?></p>
                                    <p><strong>Provincia:</strong> <?php echo htmlspecialchars($usuario['provincia'] ?: 'No registrada'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pedidos recientes -->
            <div class="row" id="pedidos">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-shopping-bag me-2"></i>Pedidos Recientes</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pedidos)): ?>
                                <p class="text-muted text-center">No tienes pedidos realizados.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID Pedido</th>
                                                <th>Fecha</th>
                                                <th>Total</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pedidos as $pedido): ?>
                                            <tr>
                                                <td>#<?php echo $pedido['id']; ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($pedido['fecha'])); ?></td>
                                                <td>$<?php echo number_format($pedido['total'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $pedido['estado'] == 'Entregado' ? 'success' : 
                                                            ($pedido['estado'] == 'Enviado' ? 'info' : 'warning'); 
                                                    ?>">
                                                        <?php echo $pedido['estado']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="#" class="btn btn-sm btn-outline-primary">Ver Detalles</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
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

