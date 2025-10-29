<?php
/**
 * ========================================================================
 * PANEL DE VENTAS - Tienda Seda y Lino
 * ========================================================================
 * Panel para usuarios con rol Ventas que permite:
 * - Ver pedidos pendientes y completados
 * - Cambiar estado de pedidos (pendiente, en proceso, enviado, completado)
 * - Ver detalles de pedidos
 * - Filtrar pedidos por estado y fecha
 * 
 * Funciones principales:
 * - Visualización de pedidos con filtros
 * - Cambio de estado de pedidos
 * - Ver detalles completos de cada pedido
 * 
 * Variables principales:
 * - $id_usuario: ID del usuario ventas actual
 * - $usuario_actual: Datos del usuario actual
 * - $mensaje/$mensaje_tipo: Mensajes de feedback
 * 
 * Tablas utilizadas: Pedidos, Detalle_Pedidos, Usuarios, Productos
 * ========================================================================
 */
session_start();

// ============================================================================
// VERIFICACIÓN DE ACCESO - SOLO USUARIOS VENTAS
// ============================================================================

// Cargar sistema de autenticación centralizado
require_once 'includes/auth_check.php';

// Verificar que el usuario esté logueado y tenga rol ventas
requireRole('ventas');

// Obtener información del usuario actual
$id_usuario = getCurrentUserId();
$usuario_actual = getCurrentUser();

// Conectar a la base de datos
require_once 'config/database.php';

// Configurar título de la página
$titulo_pagina = 'Panel de Ventas';

// ============================================================================
// PROCESAMIENTO DE FORMULARIOS
// ============================================================================

$mensaje = '';
$mensaje_tipo = '';

// ============================================================================
// OBTENER DATOS PARA EL DASHBOARD DE VENTAS
// ============================================================================

// Obtener estadísticas de ventas
$stats_ventas = [];

// Total de pedidos
$stmt = $mysqli->prepare("SELECT COUNT(*) as total_pedidos FROM Pedidos");
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats_ventas['total_pedidos'] = $result['total_pedidos'];

// Pedidos pendientes
$stmt = $mysqli->prepare("SELECT COUNT(*) as pedidos_pendientes FROM Pedidos WHERE estado_pedido = 'pendiente'");
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats_ventas['pedidos_pendientes'] = $result['pedidos_pendientes'];

// Pedidos pagados
$stmt = $mysqli->prepare("SELECT COUNT(*) as pedidos_pagados FROM Pedidos WHERE estado_pedido = 'pagado'");
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats_ventas['pedidos_pagados'] = $result['pedidos_pagados'];

// Obtener pedidos recientes
$stmt = $mysqli->prepare("
    SELECT p.id_pedido, p.fecha_pedido, p.estado_pedido, u.nombre, u.apellido, u.email
    FROM Pedidos p 
    JOIN Usuarios u ON p.id_usuario = u.id_usuario 
    ORDER BY p.fecha_pedido DESC 
    LIMIT 10
");
$stmt->execute();
$pedidos_recientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>

<?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <!-- Header del Dashboard -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-1">Dashboard Ventas</h1>
                        <p class="text-muted mb-0">Bienvenido, <?= htmlspecialchars($usuario_actual['nombre'] . ' ' . $usuario_actual['apellido']) ?></p>
                    </div>
                    <div>
                        <a href="perfil.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-user"></i> Mi Perfil
                        </a>
                        <a href="logout.php" class="btn btn-outline-secondary" onclick="return confirmLogout()">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mensajes -->
        <?php if ($mensaje): ?>
        <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-shopping-cart fa-2x text-primary mb-2"></i>
                        <h5 class="card-title">Total Pedidos</h5>
                        <h3 class="text-primary"><?= $stats_ventas['total_pedidos'] ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <h5 class="card-title">Pendientes</h5>
                        <h3 class="text-warning"><?= $stats_ventas['pedidos_pendientes'] ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h5 class="card-title">Pagados</h5>
                        <h3 class="text-success"><?= $stats_ventas['pedidos_pagados'] ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pedidos Recientes -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list"></i> Pedidos Recientes
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pedidos_recientes)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>No hay pedidos registrados</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID Pedido</th>
                                        <th>Cliente</th>
                                        <th>Email</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pedidos_recientes as $pedido): ?>
                                    <tr>
                                        <td>#<?= $pedido['id_pedido'] ?></td>
                                        <td><?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?></td>
                                        <td><?= htmlspecialchars($pedido['email']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $pedido['estado_pedido'] === 'pendiente' ? 'warning' : ($pedido['estado_pedido'] === 'pagado' ? 'success' : 'info') ?>">
                                                <?= ucfirst($pedido['estado_pedido']) ?>
                                            </span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    /**
     * Confirmar logout y asegurar redirección
     */
    function confirmLogout() {
        if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
            // Forzar redirección después de un breve delay para asegurar que el logout se procese
            setTimeout(function() {
                window.location.href = 'login.php?logout=1';
            }, 100);
            return true;
        }
        return false;
    }
    </script>

<?php include 'includes/footer.php'; render_footer(); ?>
