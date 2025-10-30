<?php
/**
 * ========================================================================
 * PANEL DE VENTAS - Tienda Seda y Lino
 * ========================================================================
 * Dashboard para usuarios con rol Ventas para gestionar pedidos
 * 
 * FUNCIONALIDADES PRINCIPALES:
 * 1. DASHBOARD CON ESTADÍSTICAS:
 *    - Total de pedidos
 *    - Pedidos pendientes
 *    - Pedidos pagados
 * 
 * 2. GESTIÓN DE PEDIDOS:
 *    - Ver pedidos con selector de cantidad (10/50/Todos)
 *    - Editar estado de pedidos mediante SELECT y actualizar en BD
 *    - Estados disponibles: pendiente, pagado, en_proceso, enviado, completado, cancelado
 *    - Información completa de cada pedido (cliente, fecha, total, estado)
 * 
 * 3. GESTIÓN DE CLIENTES:
 *    - Ver lista completa de clientes (usuarios con rol 'cliente')
 *    - Datos mostrados: nombre, email, teléfono, dirección, fecha registro
 *    - Total de pedidos realizados por cada cliente
 * 
 * FUNCIONES DEL ARCHIVO:
 * - actualizar_estado_pedido(): Actualiza estado de pedido en tabla Pedidos
 *   * Valida que el estado sea válido
 *   * Actualiza con MySQLi prepared statement
 * 
 * - Obtener pedidos con límite seleccionado (10/50/TODOS)
 * - Obtener lista de clientes con total de pedidos por cliente
 * 
 * FUNCIONES JavaScript:
 * - confirmLogout(): Confirma cierre de sesión antes de redirigir
 * - cambiarLimitePedidos(): Cambia la cantidad de pedidos a mostrar (recarga página con parámetro)
 * 
 * VARIABLES PRINCIPALES:
 * - $id_usuario: ID del usuario ventas en sesión
 * - $usuario_actual: Array con datos del usuario (nombre, apellido, rol)
 * - $stats_ventas: Array con estadísticas (total_pedidos, pedidos_pendientes, pedidos_pagados)
 * - $pedidos_recientes: Array con pedidos según límite seleccionado
 * - $lista_clientes: Array con todos los clientes y sus datos
 * - $limite_pedidos: Límite seleccionado desde URL (10/50/TODOS)
 * - $mensaje/$mensaje_tipo: Mensajes de feedback al usuario
 * 
 * CONSULTAS SQL PRINCIPALES:
 * - COUNT de pedidos totales, pendientes, pagados
 * - SELECT de pedidos con JOIN a Usuarios y límite dinámico
 * - SELECT de clientes (rol='cliente') con COUNT de pedidos por cliente
 * - UPDATE de estado_pedido en tabla Pedidos
 * 
 * TABLAS UTILIZADAS: Pedidos, Usuarios
 * ACCESO: Solo usuarios con rol 'ventas' o 'admin' (mediante requireRole('ventas'))
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

// Procesar actualización de estado de pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_estado_pedido'])) {
    $pedido_id = intval($_POST['pedido_id'] ?? 0);
    $nuevo_estado = trim($_POST['nuevo_estado'] ?? '');
    
    // Validar estados permitidos
    $estados_validos = ['pendiente', 'pagado', 'en_proceso', 'enviado', 'completado', 'cancelado'];
    
    if ($pedido_id <= 0 || !in_array($nuevo_estado, $estados_validos)) {
        $mensaje = 'Datos inválidos al actualizar estado';
        $mensaje_tipo = 'danger';
    } else {
        $stmt = $mysqli->prepare("UPDATE Pedidos SET estado_pedido = ? WHERE id_pedido = ?");
        $stmt->bind_param('si', $nuevo_estado, $pedido_id);
        
        if ($stmt->execute()) {
            $mensaje = 'Estado del pedido actualizado correctamente';
            $mensaje_tipo = 'success';
        } else {
            $mensaje = 'Error al actualizar el estado del pedido';
            $mensaje_tipo = 'danger';
        }
    }
}

// ============================================================================
// OBTENER DATOS PARA EL DASHBOARD DE VENTAS
// ============================================================================

// Obtener límite de pedidos desde URL (10, 50, o todos)
$limite_pedidos = isset($_GET['limite']) ? $_GET['limite'] : '10';
$limite_sql = '';
if ($limite_pedidos === '50') {
    $limite_sql = 'LIMIT 50';
} elseif ($limite_pedidos === 'TODOS') {
    $limite_sql = '';
} else {
    $limite_sql = 'LIMIT 10';
    $limite_pedidos = '10';
}

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

// Obtener pedidos con límite seleccionado
$sql_pedidos = "
    SELECT p.id_pedido, p.fecha_pedido, p.estado_pedido, p.total_pedido,
           u.nombre, u.apellido, u.email, u.telefono, u.direccion
    FROM Pedidos p 
    JOIN Usuarios u ON p.id_usuario = u.id_usuario 
    ORDER BY p.fecha_pedido DESC 
    " . $limite_sql;
$resultado_pedidos = $mysqli->query($sql_pedidos);
$pedidos_recientes = [];
if ($resultado_pedidos) {
    while ($row = $resultado_pedidos->fetch_assoc()) {
        $pedidos_recientes[] = $row;
    }
}

// Obtener lista de clientes (usuarios con rol cliente)
$sql_clientes = "
    SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.telefono, u.direccion, u.fecha_registro,
           COUNT(p.id_pedido) as total_pedidos
    FROM Usuarios u
    LEFT JOIN Pedidos p ON u.id_usuario = p.id_usuario
    WHERE u.rol = 'cliente'
    GROUP BY u.id_usuario, u.nombre, u.apellido, u.email, u.telefono, u.direccion, u.fecha_registro
    ORDER BY u.apellido, u.nombre
";
$resultado_clientes = $mysqli->query($sql_clientes);
$lista_clientes = [];
if ($resultado_clientes) {
    while ($row = $resultado_clientes->fetch_assoc()) {
        $lista_clientes[] = $row;
    }
}

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

        <!-- Navegación por pestañas -->
        <ul class="nav nav-tabs mb-4" id="ventasTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pedidos-tab" data-bs-toggle="tab" data-bs-target="#pedidos" type="button" role="tab">
                    <i class="fas fa-shopping-cart me-2"></i>Pedidos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="clientes-tab" data-bs-toggle="tab" data-bs-target="#clientes" type="button" role="tab">
                    <i class="fas fa-users me-2"></i>Clientes
                </button>
            </li>
        </ul>

        <div class="tab-content" id="ventasTabsContent">
            <!-- Pestaña de Pedidos -->
            <div class="tab-pane fade show active" id="pedidos" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Pedidos
                        </h5>
                        <div class="d-flex align-items-center gap-2">
                            <label class="mb-0"><small>Mostrar:</small></label>
                            <select class="form-select form-select-sm" style="width: auto;" onchange="cambiarLimitePedidos(this.value)">
                                <option value="10" <?= $limite_pedidos == '10' ? 'selected' : '' ?>>Últimos 10</option>
                                <option value="50" <?= $limite_pedidos == '50' ? 'selected' : '' ?>>Últimos 50</option>
                                <option value="TODOS" <?= $limite_pedidos == 'TODOS' ? 'selected' : '' ?>>Todos</option>
                            </select>
                        </div>
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
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pedidos_recientes as $pedido): ?>
                                    <tr>
                                        <td>#<?= $pedido['id_pedido'] ?></td>
                                        <td><?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?></td>
                                        <td><?= htmlspecialchars($pedido['email']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></td>
                                        <td>$<?= number_format($pedido['total_pedido'] ?? 0, 2, ',', '.') ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $pedido['estado_pedido'] === 'pendiente' ? 'warning' : 
                                                ($pedido['estado_pedido'] === 'pagado' ? 'success' : 
                                                ($pedido['estado_pedido'] === 'enviado' ? 'info' : 
                                                ($pedido['estado_pedido'] === 'completado' ? 'success' : 
                                                ($pedido['estado_pedido'] === 'cancelado' ? 'danger' : 'secondary'))))
                                            ?>">
                                                <?= ucfirst(str_replace('_', ' ', $pedido['estado_pedido'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editarEstadoModal<?= $pedido['id_pedido'] ?>">
                                                <i class="fas fa-edit me-1"></i>Editar Estado
                                            </button>
                                            
                                            <!-- Modal para editar estado -->
                                            <div class="modal fade" id="editarEstadoModal<?= $pedido['id_pedido'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Editar Estado - Pedido #<?= $pedido['id_pedido'] ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST" action="">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="pedido_id" value="<?= $pedido['id_pedido'] ?>">
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label"><strong>Estado Actual:</strong></label>
                                                                    <p>
                                                                        <span class="badge bg-<?= 
                                                                            $pedido['estado_pedido'] === 'pendiente' ? 'warning' : 
                                                                            ($pedido['estado_pedido'] === 'pagado' ? 'success' : 'info')
                                                                        ?>">
                                                                            <?= ucfirst(str_replace('_', ' ', $pedido['estado_pedido'])) ?>
                                                                        </span>
                                                                    </p>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label"><strong>Nuevo Estado:</strong></label>
                                                                    <select class="form-select" name="nuevo_estado" required>
                                                                        <option value="pendiente" <?= $pedido['estado_pedido'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                                                        <option value="pagado" <?= $pedido['estado_pedido'] === 'pagado' ? 'selected' : '' ?>>Pagado</option>
                                                                        <option value="en_proceso" <?= $pedido['estado_pedido'] === 'en_proceso' ? 'selected' : '' ?>>En Proceso</option>
                                                                        <option value="enviado" <?= $pedido['estado_pedido'] === 'enviado' ? 'selected' : '' ?>>Enviado</option>
                                                                        <option value="completado" <?= $pedido['estado_pedido'] === 'completado' ? 'selected' : '' ?>>Completado</option>
                                                                        <option value="cancelado" <?= $pedido['estado_pedido'] === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                                                    </select>
                                                                </div>
                                                                
                                                                <div class="alert alert-info">
                                                                    <small>
                                                                        <i class="fas fa-info-circle me-1"></i>
                                                                        <strong>Cliente:</strong> <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?><br>
                                                                        <strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?><br>
                                                                        <strong>Total:</strong> $<?= number_format($pedido['total_pedido'] ?? 0, 2, ',', '.') ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                <button type="submit" name="actualizar_estado_pedido" class="btn btn-primary">
                                                                    <i class="fas fa-save me-1"></i>Guardar Cambio
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                Mostrando <?= count($pedidos_recientes) ?> pedido(s)
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Pestaña de Clientes -->
            <div class="tab-pane fade" id="clientes" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-users me-2"></i>Lista de Clientes
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($lista_clientes)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-user-slash fa-3x mb-3"></i>
                            <p>No hay clientes registrados</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre Completo</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Dirección</th>
                                        <th>Fecha Registro</th>
                                        <th>Total Pedidos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lista_clientes as $cliente): ?>
                                    <tr>
                                        <td>#<?= $cliente['id_usuario'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($cliente['email']) ?></td>
                                        <td><?= htmlspecialchars($cliente['telefono'] ?: 'N/A') ?></td>
                                        <td>
                                            <small><?= htmlspecialchars($cliente['direccion'] ?: 'Sin dirección') ?></small>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($cliente['fecha_registro'])) ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= $cliente['total_pedidos'] ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                Total de clientes: <?= count($lista_clientes) ?>
                            </small>
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
            setTimeout(function() {
                window.location.href = 'login.php?logout=1';
            }, 100);
            return true;
        }
        return false;
    }
    
    /**
     * Cambiar límite de pedidos mostrados
     * @param {string} limite - Límite seleccionado (10, 50, TODOS)
     */
    function cambiarLimitePedidos(limite) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('limite', limite);
        window.location.search = urlParams.toString();
    }
    </script>

<?php include 'includes/footer.php'; render_footer(); ?>
