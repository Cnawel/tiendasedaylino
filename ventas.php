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
 *    - Pedidos en preparación
 * 
 * 2. GESTIÓN DE PEDIDOS:
 *    - Ver pedidos con selector de cantidad (10/50/Todos)
 *    - Editar estado de pedidos mediante SELECT y actualizar en BD
 *    - Estados disponibles: pendiente, preparacion, en_viaje, completado, devolucion, cancelado
 *    - Información completa de cada pedido (cliente, fecha, total, estado)
 * 
 * 3. GESTIÓN DE CLIENTES:
 *    - Ver lista completa de clientes (usuarios con rol 'cliente')
 *    - Datos mostrados: nombre, email, teléfono, dirección, fecha registro
 *    - Total de pedidos realizados por cada cliente
 * 
 * 4. GESTIÓN DE MÉTODOS DE PAGO:
 *    - Agregar nuevos métodos de pago (nombre y descripción)
 *    - Editar nombre y descripción de métodos existentes
 *    - Eliminar métodos de pago (con validación de uso en tabla Pagos)
 *    - Lista completa de métodos disponibles en el sistema
 * 
 * FUNCIONES DEL ARCHIVO:
 * - actualizar_estado_pedido(): Actualiza estado de pedido en tabla Pedidos
 *   * Valida que el estado sea válido
 *   * Actualiza con MySQLi prepared statement
 * 
 * - agregar_metodo_pago(): Inserta nuevo método de pago en tabla Forma_Pagos
 *   * Valida que el nombre no esté vacío
 *   * Descripción opcional
 * 
 * - actualizar_metodo_pago(): Actualiza nombre y descripción de método de pago
 *   * Valida ID y nombre
 *   * Usa MySQLi prepared statement
 * 
 * - eliminar_metodo_pago(): Elimina método de pago de la tabla Forma_Pagos
 *   * Verifica que no esté en uso en tabla Pagos antes de eliminar
 *   * Muestra mensaje de error si está en uso
 * 
 * - Obtener pedidos con límite seleccionado (10/50/TODOS)
 * - Obtener lista de clientes con total de pedidos por cliente
 * - Obtener lista de métodos de pago desde tabla Forma_Pagos
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
 * - $lista_metodos_pago: Array con todos los métodos de pago disponibles
 * - $limite_pedidos: Límite seleccionado desde URL (10/50/TODOS)
 * - $mensaje/$mensaje_tipo: Mensajes de feedback al usuario
 * 
 * CONSULTAS SQL PRINCIPALES:
 * - COUNT de pedidos totales, pendientes, pagados
 * - SELECT de pedidos con JOIN a Usuarios y límite dinámico
 * - SELECT de clientes (rol='cliente') con COUNT de pedidos por cliente
 * - UPDATE de estado_pedido en tabla Pedidos
 * - SELECT de métodos de pago desde tabla Forma_Pagos
 * - INSERT de nuevo método de pago en Forma_Pagos
 * - UPDATE de nombre y descripción en Forma_Pagos
 * - COUNT de uso de método de pago en tabla Pagos (para validación antes de eliminar)
 * - DELETE de método de pago en Forma_Pagos
 * 
 * TABLAS UTILIZADAS: Pedidos, Usuarios, Forma_Pagos, Pagos
 * ACCESO: Solo usuarios con rol 'ventas' o 'admin' (mediante requireRole('ventas'))
 * ========================================================================
 */
session_start();

// ============================================================================
// VERIFICACIÓN DE ACCESO - SOLO USUARIOS VENTAS
// ============================================================================

// Cargar sistema de autenticación centralizado
require_once __DIR__ . '/includes/auth_check.php';

// Verificar que el usuario esté logueado y tenga rol ventas
requireRole('ventas');

// Obtener información del usuario actual
$id_usuario = getCurrentUserId();
$usuario_actual = getCurrentUser();

// Conectar a la base de datos
require_once __DIR__ . '/config/database.php';

// Cargar funciones auxiliares de ventas
require_once __DIR__ . '/includes/sales_functions.php';

// Cargar funciones de perfil para parsear direcciones
require_once __DIR__ . '/includes/perfil_functions.php';

// Configurar título de la página
$titulo_pagina = 'Panel de Ventas';

// ============================================================================
// PROCESAMIENTO DE FORMULARIOS
// ============================================================================

// Obtener mensajes de sesión (si hay redirección)
$mensaje = '';
$mensaje_tipo = '';
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $mensaje_tipo = isset($_SESSION['mensaje_tipo']) ? $_SESSION['mensaje_tipo'] : 'success';
    // Limpiar mensaje de sesión después de leerlo
    unset($_SESSION['mensaje']);
    unset($_SESSION['mensaje_tipo']);
}


// Incluir queries necesarias
require_once __DIR__ . '/includes/queries/stock_queries.php';
require_once __DIR__ . '/includes/queries/pago_queries.php';
require_once __DIR__ . '/includes/queries/forma_pago_queries.php';
require_once __DIR__ . '/includes/queries/cliente_queries.php';

// ============================================================================
// PROCESAR ACTUALIZACIÓN DE PEDIDO Y PAGO COMPLETO
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = procesarActualizacionPedidoPago($mysqli, $_POST, $id_usuario);
    if ($resultado !== false) {
        $_SESSION['mensaje'] = $resultado['mensaje'];
        $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
        $redirect_url = construirRedirectUrl('ventas.php');
        header('Location: ' . $redirect_url);
        exit;
    }
}

// ============================================================================
// PROCESAR APROBACIÓN DE PAGO
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = procesarAprobacionPago($mysqli, $_POST, $id_usuario);
    if ($resultado !== false) {
        $_SESSION['mensaje'] = $resultado['mensaje'];
        $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
        $redirect_url = construirRedirectUrl('ventas.php');
        header('Location: ' . $redirect_url);
        exit;
    }
}

// ============================================================================
// PROCESAR RECHAZO DE PAGO
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = procesarRechazoPago($mysqli, $_POST, $id_usuario);
    if ($resultado !== false) {
        $_SESSION['mensaje'] = $resultado['mensaje'];
        $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
        $redirect_url = construirRedirectUrl('ventas.php');
        header('Location: ' . $redirect_url);
        exit;
    }
}

// Procesar agregar método de pago
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = procesarAgregarMetodoPago($mysqli, $_POST);
    if ($resultado !== false) {
        $_SESSION['mensaje'] = $resultado['mensaje'];
        $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
        $redirect_url = construirRedirectUrl('ventas.php');
        header('Location: ' . $redirect_url);
        exit;
    }
}

// Procesar actualización de método de pago
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = procesarActualizarMetodoPago($mysqli, $_POST);
    if ($resultado !== false) {
        $_SESSION['mensaje'] = $resultado['mensaje'];
        $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
        $redirect_url = construirRedirectUrl('ventas.php');
        header('Location: ' . $redirect_url);
        exit;
    }
}

// Procesar eliminación de método de pago
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = procesarEliminarMetodoPago($mysqli, $_POST);
    if ($resultado !== false) {
        $_SESSION['mensaje'] = $resultado['mensaje'];
        $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
        $redirect_url = construirRedirectUrl('ventas.php');
        header('Location: ' . $redirect_url);
        exit;
    }
}


// Obtener datos para el dashboard de ventas
// Límite de pedidos desde URL (10, 50, o todos)
$limite_pedidos = isset($_GET['limite']) ? $_GET['limite'] : '10';
if ($limite_pedidos !== '10' && $limite_pedidos !== '50') {
    $limite_pedidos = 'TODOS';
}

// Obtener parámetro para mostrar pedidos de usuarios inactivos
$mostrar_inactivos = isset($_GET['mostrar_inactivos']) && $_GET['mostrar_inactivos'] == '1';

// Incluir queries de pedidos y formas de pago
require_once __DIR__ . '/includes/queries/pedido_queries.php';
require_once __DIR__ . '/includes/queries/forma_pago_queries.php';

// Obtener estadísticas de ventas usando función centralizada
$stats_ventas = obtenerEstadisticasPedidos($mysqli);

// Obtener pedidos con límite seleccionado usando función centralizada
$limite_numero = ($limite_pedidos === 'TODOS') ? 0 : (int)$limite_pedidos;
$pedidos_recientes = obtenerPedidos($mysqli, $limite_numero, $mostrar_inactivos);

// Obtener lista de clientes usando función centralizada
$lista_clientes = obtenerClientesConPedidos($mysqli);

// Obtener lista de métodos de pago usando función centralizada
$lista_metodos_pago = obtenerFormasPagoSelect($mysqli);

// Obtener métricas analíticas
$pedidos_tiempo_estado = obtenerPedidosTiempoEstado($mysqli, 10);
$top_productos_vendidos = obtenerTopProductosVendidos($mysqli, 10);
$movimientos_stock = obtenerMovimientosStockRecientes($mysqli, 50);

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
                        <i class="fas fa-box fa-2x text-info mb-2"></i>
                        <h5 class="card-title">En Preparación</h5>
                        <h3 class="text-info"><?= $stats_ventas['pedidos_preparacion'] ?></h3>
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
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="metodos-pago-tab" data-bs-toggle="tab" data-bs-target="#metodos-pago" type="button" role="tab">
                    <i class="fas fa-credit-card me-2"></i>Métodos de Pago
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="metricas-tab" data-bs-toggle="tab" data-bs-target="#metricas" type="button" role="tab">
                    <i class="fas fa-chart-line me-2"></i>Métricas
                </button>
            </li>
        </ul>

        <div class="tab-content" id="ventasTabsContent">
            <!-- Pestaña de Pedidos -->
            <div class="tab-pane fade show active" id="pedidos" role="tabpanel">
                <!-- Mensajes -->
                <?php if ($mensaje): ?>
                <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Pedidos
                        </h5>
                        <div class="d-flex align-items-center gap-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="mostrarInactivos" 
                                       <?= $mostrar_inactivos ? 'checked' : '' ?>
                                       onchange="togglePedidosInactivos(this.checked)">
                                <label class="form-check-label" for="mostrarInactivos">
                                    <small>Mostrar pedidos de usuarios inactivos</small>
                                </label>
                            </div>
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
                            <table class="table table-hover sortable-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="sortable">ID Pedido</th>
                                        <th class="sortable">Cliente</th>
                                        <th class="sortable">Email</th>
                                        <th class="sortable text-center" style="width: 60px;">A/I</th>
                                        <th class="sortable">Fecha</th>
                                        <th class="sortable">Total</th>
                                        <th class="sortable">Estado Pedido</th>
                                        <th class="sortable">Estado Pago</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pedidos_recientes as $pedido): ?>
                                    <?php
                                    // Obtener información del pago para este pedido
                                    $pago_pedido = obtenerPagoPorPedido($mysqli, $pedido['id_pedido']);
                                    
                                    // Validar y limpiar el estado del pedido
                                    $estado_pedido = trim($pedido['estado_pedido'] ?? '');
                                    if (empty($estado_pedido)) {
                                        $estado_pedido = 'pendiente'; // Valor por defecto si está vacío
                                    }
                                    
                                    // Mapeo inline de estados de pedido
                                    $estados_pedido_map = [
                                        'pendiente' => ['color' => 'warning', 'nombre' => 'Pendiente'],
                                        'preparacion' => ['color' => 'info', 'nombre' => 'Preparación'],
                                        'en_viaje' => ['color' => 'primary', 'nombre' => 'En Viaje'],
                                        'completado' => ['color' => 'success', 'nombre' => 'Completado'],
                                        'devolucion' => ['color' => 'secondary', 'nombre' => 'Devolución'],
                                        'cancelado' => ['color' => 'secondary', 'nombre' => 'Cancelado']
                                    ];
                                    $info_estado = $estados_pedido_map[$estado_pedido] ?? ['color' => 'secondary', 'nombre' => ucfirst(str_replace('_', ' ', $estado_pedido))];
                                    
                                    // Mapeo inline de estados de pago
                                    $estados_pago_map = [
                                        'pendiente' => ['color' => 'warning', 'nombre' => 'Pendiente'],
                                        'pendiente_aprobacion' => ['color' => 'info', 'nombre' => 'Pendiente Aprobación'],
                                        'aprobado' => ['color' => 'success', 'nombre' => 'Aprobado'],
                                        'rechazado' => ['color' => 'danger', 'nombre' => 'Rechazado'],
                                        'cancelado' => ['color' => 'secondary', 'nombre' => 'Cancelado']
                                    ];
                                    ?>
                                    <tr>
                                        <td>#<?= $pedido['id_pedido'] ?></td>
                                        <td><?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?></td>
                                        <td><?= htmlspecialchars($pedido['email']) ?></td>
                                        <td class="text-center">
                                            <?php 
                                            $activo = intval($pedido['activo'] ?? 1);
                                            if ($activo === 1): ?>
                                                <span class="badge bg-success" title="Activo">A</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary" title="Inactivo">I</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></td>
                                        <td>$<?= number_format($pedido['total_pedido'] ?? 0, 2, ',', '.') ?></td>
                                        <td>
                                            <span class="badge bg-<?= htmlspecialchars($info_estado['color']) ?>">
                                                <?= htmlspecialchars($info_estado['nombre']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($pago_pedido): ?>
                                                <?php
                                                // Normalizar estado de pago antes de buscar en el mapa
                                                $estado_pago_raw = $pago_pedido['estado_pago'] ?? '';
                                                $estado_pago_normalizado = strtolower(trim($estado_pago_raw));
                                                // Asegurar que el estado normalizado no esté vacío
                                                if (empty($estado_pago_normalizado)) {
                                                    $estado_pago_normalizado = 'pendiente';
                                                }
                                                // Buscar en el mapa de estados
                                                if (isset($estados_pago_map[$estado_pago_normalizado])) {
                                                    $info_estado_pago = $estados_pago_map[$estado_pago_normalizado];
                                                } else {
                                                    // Fallback si no se encuentra en el mapa
                                                    $info_estado_pago = ['color' => 'secondary', 'nombre' => ucfirst(str_replace('_', ' ', $estado_pago_normalizado))];
                                                }
                                                ?>
                                                <span class="badge bg-<?= htmlspecialchars($info_estado_pago['color']) ?>">
                                                    <?= htmlspecialchars($info_estado_pago['nombre']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Sin pago</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-info me-1" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#verPedidoModal<?= $pedido['id_pedido'] ?>">
                                                <i class="fas fa-eye me-1"></i>Ver Pedido
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editarEstadoModal<?= $pedido['id_pedido'] ?>">
                                                <i class="fas fa-edit me-1"></i>Editar Estado
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <!-- Modales fuera de la tabla para evitar problemas al ordenar -->
                            <?php foreach ($pedidos_recientes as $pedido): ?>
                            <?php
                            // Obtener información del pago para este pedido
                            $pago_pedido = obtenerPagoPorPedido($mysqli, $pedido['id_pedido']);
                            
                            // Obtener información completa del pedido para los modales
                            $pedido_completo = obtenerPedidoPorId($mysqli, $pedido['id_pedido']);
                            $detalles_pedido = obtenerDetallesPedido($mysqli, $pedido['id_pedido']);
                            $pago_detalle = obtenerPagoPorPedido($mysqli, $pedido['id_pedido']);
                            $pedido_completo_modal = obtenerPedidoPorId($mysqli, $pedido['id_pedido']);
                            $pago_modal = obtenerPagoPorPedido($mysqli, $pedido['id_pedido']);
                            
                            // Normalizar estado actual del pedido para el formulario
                            $estado_actual_modal = trim(strtolower($pedido_completo_modal['estado_pedido'] ?? ''));
                            if (empty($estado_actual_modal)) {
                                $estado_actual_modal = 'pendiente';
                            }
                            ?>
                            
                            <!-- Modal para ver detalles del pedido -->
                            <div class="modal fade" id="verPedidoModal<?= $pedido['id_pedido'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header bg-info text-white">
                                            <h5 class="modal-title">
                                                <i class="fas fa-shopping-cart me-2"></i>Detalles del Pedido #<?= $pedido['id_pedido'] ?>
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <!-- Información del Cliente -->
                                            <div class="card mb-3">
                                                <div class="card-header bg-light">
                                                    <h6 class="mb-0"><i class="fas fa-user me-2"></i>Información del Cliente</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <p class="mb-1"><strong>Nombre:</strong> <?= htmlspecialchars($pedido_completo['nombre'] . ' ' . $pedido_completo['apellido']) ?></p>
                                                            <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($pedido_completo['email']) ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p class="mb-1"><strong>Teléfono:</strong> <?= htmlspecialchars($pedido_completo['telefono'] ?: 'N/A') ?></p>
                                                            <p class="mb-1"><strong>Dirección:</strong> 
                                                                <?php 
                                                                if (!empty($pedido_completo['direccion'])) {
                                                                    echo htmlspecialchars($pedido_completo['direccion']);
                                                                } else {
                                                                    echo 'N/A';
                                                                }
                                                                ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Información del Pedido -->
                                            <div class="card mb-3">
                                                <div class="card-header bg-light">
                                                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información del Pedido</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <p class="mb-1"><strong>Fecha Pedido:</strong> <?= date('d/m/Y H:i', strtotime($pedido_completo['fecha_pedido'])) ?></p>
                                                            <?php if (!empty($pedido_completo['fecha_actualizacion'])): ?>
                                                            <p class="mb-1"><strong>Última Actualización:</strong> <?= date('d/m/Y H:i', strtotime($pedido_completo['fecha_actualizacion'])) ?></p>
                                                            <?php endif; ?>
                                                            <p class="mb-1">
                                                                <strong>Estado:</strong> 
                                                                <?php
                                                                $estado_actual = trim($pedido_completo['estado_pedido'] ?? 'pendiente');
                                                                $estados_pedido_map = [
                                                                    'pendiente' => ['color' => 'warning', 'nombre' => 'Pendiente'],
                                                                    'preparacion' => ['color' => 'info', 'nombre' => 'Preparación'],
                                                                    'en_viaje' => ['color' => 'primary', 'nombre' => 'En Viaje'],
                                                                    'completado' => ['color' => 'success', 'nombre' => 'Completado'],
                                                                    'devolucion' => ['color' => 'secondary', 'nombre' => 'Devolución'],
                                                                    'cancelado' => ['color' => 'secondary', 'nombre' => 'Cancelado']
                                                                ];
                                                                $info_estado_detalle = $estados_pedido_map[$estado_actual] ?? ['color' => 'secondary', 'nombre' => ucfirst(str_replace('_', ' ', $estado_actual))];
                                                                ?>
                                                                <span class="badge bg-<?= htmlspecialchars($info_estado_detalle['color']) ?>">
                                                                    <?= htmlspecialchars($info_estado_detalle['nombre']) ?>
                                                                </span>
                                                            </p>
                                                            <?php if (!empty($pedido_completo['direccion_entrega'])): ?>
                                                            <p class="mb-1"><strong>Dirección de Entrega:</strong><br>
                                                                <small><?= nl2br(htmlspecialchars($pedido_completo['direccion_entrega'])) ?></small>
                                                            </p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($pedido_completo['telefono_contacto'])): ?>
                                                            <p class="mb-1"><strong>Teléfono de Contacto:</strong> <?= htmlspecialchars($pedido_completo['telefono_contacto']) ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <?php if (!empty($pedido_completo['observaciones'])): ?>
                                                            <p class="mb-1"><strong>Observaciones:</strong><br>
                                                                <small><?= nl2br(htmlspecialchars($pedido_completo['observaciones'])) ?></small>
                                                            </p>
                                                            <?php endif; ?>
                                                            <?php if ($pago_detalle): ?>
                                                            <p class="mb-1">
                                                                <strong>Estado Pago:</strong> 
                                                                <?php
                                                                $estados_pago_map = [
                                                                    'pendiente' => ['color' => 'warning', 'nombre' => 'Pendiente'],
                                                                    'pendiente_aprobacion' => ['color' => 'info', 'nombre' => 'Pendiente Aprobación'],
                                                                    'aprobado' => ['color' => 'success', 'nombre' => 'Aprobado'],
                                                                    'rechazado' => ['color' => 'danger', 'nombre' => 'Rechazado'],
                                                                    'cancelado' => ['color' => 'secondary', 'nombre' => 'Cancelado']
                                                                ];
                                                                // Normalizar estado de pago antes de buscar en el mapa
                                                                $estado_pago_raw = $pago_detalle['estado_pago'] ?? '';
                                                                $estado_pago_normalizado = strtolower(trim($estado_pago_raw));
                                                                // Asegurar que el estado normalizado no esté vacío
                                                                if (empty($estado_pago_normalizado)) {
                                                                    $estado_pago_normalizado = 'pendiente';
                                                                }
                                                                // Buscar en el mapa de estados
                                                                if (isset($estados_pago_map[$estado_pago_normalizado])) {
                                                                    $info_estado_pago_detalle = $estados_pago_map[$estado_pago_normalizado];
                                                                } else {
                                                                    // Fallback si no se encuentra en el mapa
                                                                    $info_estado_pago_detalle = ['color' => 'secondary', 'nombre' => ucfirst(str_replace('_', ' ', $estado_pago_normalizado))];
                                                                }
                                                                ?>
                                                                <span class="badge bg-<?= htmlspecialchars($info_estado_pago_detalle['color']) ?>">
                                                                    <?= htmlspecialchars($info_estado_pago_detalle['nombre']) ?>
                                                                </span>
                                                            </p>
                                                            <p class="mb-1"><strong>Monto:</strong> $<?= number_format($pago_detalle['monto'], 2, ',', '.') ?></p>
                                                            <p class="mb-1"><strong>Método de Pago:</strong> <?= htmlspecialchars($pago_detalle['forma_pago_nombre']) ?></p>
                                                            <?php if (!empty($pago_detalle['numero_transaccion'])): ?>
                                                            <p class="mb-1"><strong>Número de Transacción:</strong> <?= htmlspecialchars($pago_detalle['numero_transaccion']) ?></p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($pago_detalle['fecha_aprobacion'])): ?>
                                                            <p class="mb-1"><strong>Fecha de Aprobación:</strong> <?= date('d/m/Y H:i', strtotime($pago_detalle['fecha_aprobacion'])) ?></p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($pago_detalle['motivo_rechazo'])): ?>
                                                            <p class="mb-1"><strong>Motivo de Rechazo:</strong><br>
                                                                <small class="text-danger"><?= nl2br(htmlspecialchars($pago_detalle['motivo_rechazo'])) ?></small>
                                                            </p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($pago_detalle['fecha_actualizacion'])): ?>
                                                            <p class="mb-1"><strong>Última Actualización Pago:</strong> <?= date('d/m/Y H:i', strtotime($pago_detalle['fecha_actualizacion'])) ?></p>
                                                            <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Productos del Pedido -->
                                            <div class="card">
                                                <div class="card-header bg-light">
                                                    <h6 class="mb-0"><i class="fas fa-box me-2"></i>Productos del Pedido</h6>
                                                </div>
                                                <div class="card-body">
                                                    <?php if (empty($detalles_pedido)): ?>
                                                        <p class="text-muted text-center mb-0">No hay productos en este pedido</p>
                                                    <?php else: ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm">
                                                            <thead>
                                                                <tr>
                                                                    <th>Producto</th>
                                                                    <th>Talla</th>
                                                                    <th>Color</th>
                                                                    <th class="text-end">Cantidad</th>
                                                                    <th class="text-end">Precio Unit.</th>
                                                                    <th class="text-end">Subtotal</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php 
                                                                $total_calculado = 0;
                                                                foreach ($detalles_pedido as $detalle): 
                                                                    $subtotal = $detalle['cantidad'] * $detalle['precio_unitario'];
                                                                    $total_calculado += $subtotal;
                                                                ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($detalle['nombre_producto']) ?></td>
                                                                    <td><?= htmlspecialchars($detalle['talle']) ?></td>
                                                                    <td><?= htmlspecialchars($detalle['color']) ?></td>
                                                                    <td class="text-end"><?= $detalle['cantidad'] ?></td>
                                                                    <td class="text-end">$<?= number_format($detalle['precio_unitario'], 2, ',', '.') ?></td>
                                                                    <td class="text-end"><strong>$<?= number_format($subtotal, 2, ',', '.') ?></strong></td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                            <tfoot>
                                                                <tr class="table-info">
                                                                    <th colspan="5" class="text-end">Total del Pedido:</th>
                                                                    <th class="text-end">$<?= number_format($total_calculado, 2, ',', '.') ?></th>
                                                                </tr>
                                                            </tfoot>
                                                        </table>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Modal para editar estado -->
                            <div class="modal fade" id="editarEstadoModal<?= $pedido['id_pedido'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Editar Estado - Pedido #<?= $pedido['id_pedido'] ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="" id="formEditarEstado<?= $pedido['id_pedido'] ?>">
                                            <div class="modal-body">
                                                <input type="hidden" name="pedido_id" value="<?= $pedido['id_pedido'] ?>">
                                                <input type="hidden" name="estado_anterior" value="<?= htmlspecialchars($estado_actual_modal) ?>">
                                                
                                                <!-- Información del Cliente (compacta) -->
                                                <div class="mb-3">
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        <strong><?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?></strong> | 
                                                        <?= htmlspecialchars($pedido['email']) ?>
                                                    </small>
                                                </div>
                                                
                                                <!-- Estado del Pedido -->
                                                <div class="mb-3">
                                                    <label class="form-label"><strong>Estado del Pedido:</strong></label>
                                                    <select class="form-select" name="nuevo_estado" required>
                                                        <option value="pendiente" <?= $estado_actual_modal === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                                        <option value="preparacion" <?= $estado_actual_modal === 'preparacion' ? 'selected' : '' ?>>Preparación</option>
                                                        <option value="en_viaje" <?= $estado_actual_modal === 'en_viaje' ? 'selected' : '' ?>>En Viaje</option>
                                                        <option value="completado" <?= $estado_actual_modal === 'completado' ? 'selected' : '' ?>>Completado</option>
                                                        <option value="devolucion" <?= $estado_actual_modal === 'devolucion' ? 'selected' : '' ?>>Devolución</option>
                                                        <option value="cancelado" <?= $estado_actual_modal === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                                    </select>
                                                    <?php if ($estado_actual_modal !== 'cancelado'): ?>
                                                    <small class="text-muted d-block mt-1">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Si cancela el pedido, el stock será restaurado automáticamente.
                                                    </small>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Información del Pago -->
                                                <?php if ($pago_modal): ?>
                                                <?php
                                                // Mapeo de estados de pago para el modal
                                                $estados_pago_map_modal = [
                                                    'pendiente' => ['color' => 'warning', 'nombre' => 'Pendiente'],
                                                    'pendiente_aprobacion' => ['color' => 'info', 'nombre' => 'Pendiente Aprobación'],
                                                    'aprobado' => ['color' => 'success', 'nombre' => 'Aprobado'],
                                                    'rechazado' => ['color' => 'danger', 'nombre' => 'Rechazado'],
                                                    'cancelado' => ['color' => 'secondary', 'nombre' => 'Cancelado']
                                                ];
                                                // Normalizar estado actual del pago para mostrar y comparar
                                                $estado_pago_actual_modal = strtolower(trim($pago_modal['estado_pago'] ?? ''));
                                                if (empty($estado_pago_actual_modal)) {
                                                    $estado_pago_actual_modal = 'pendiente';
                                                }
                                                // Obtener información del estado actual para el badge
                                                if (isset($estados_pago_map_modal[$estado_pago_actual_modal])) {
                                                    $info_estado_pago_actual = $estados_pago_map_modal[$estado_pago_actual_modal];
                                                } else {
                                                    $info_estado_pago_actual = ['color' => 'secondary', 'nombre' => ucfirst(str_replace('_', ' ', $estado_pago_actual_modal))];
                                                }
                                                ?>
                                                <input type="hidden" name="estado_pago_anterior" value="<?= htmlspecialchars($estado_pago_actual_modal) ?>">
                                                
                                                <hr class="my-3">
                                                <h6 class="mb-3"><i class="fas fa-credit-card me-2"></i>Información del Pago</h6>
                                                
                                                <!-- Estado del Pago -->
                                                <div class="mb-3">
                                                    <label class="form-label"><strong>Estado del Pago:</strong></label>
                                                    <!-- Badge mostrando el estado actual -->
                                                    <div class="mb-2">
                                                        <span class="badge bg-<?= htmlspecialchars($info_estado_pago_actual['color']) ?>">
                                                            <i class="fas fa-info-circle me-1"></i>Estado Actual: <?= htmlspecialchars($info_estado_pago_actual['nombre']) ?>
                                                        </span>
                                                    </div>
                                                    <select class="form-select" name="nuevo_estado_pago" id="nuevo_estado_pago_<?= $pedido['id_pedido'] ?>">
                                                        <option value="">-- Mantener estado actual --</option>
                                                        <option value="pendiente" <?= $estado_pago_actual_modal === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                                        <option value="pendiente_aprobacion" <?= $estado_pago_actual_modal === 'pendiente_aprobacion' ? 'selected' : '' ?>>Pendiente Aprobación</option>
                                                        <option value="aprobado" <?= $estado_pago_actual_modal === 'aprobado' ? 'selected' : '' ?>>Aprobado</option>
                                                        <option value="rechazado" <?= $estado_pago_actual_modal === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                                                        <option value="cancelado" <?= $estado_pago_actual_modal === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                                    </select>
                                                    <small class="text-muted d-block mt-1">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Al aprobar el pago, el stock se descontará automáticamente. Al rechazar/cancelar, se restaurará si había sido descontado.
                                                    </small>
                                                </div>
                                                
                                                <!-- Monto del Pago (solo lectura - llenado por el cliente) -->
                                                <div class="mb-3">
                                                    <label class="form-label"><strong>Monto del Pago:</strong></label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="text" class="form-control" 
                                                               value="<?= number_format($pago_modal['monto'] ?? 0, 2, ',', '.') ?>" 
                                                               readonly style="background-color: #e9ecef;">
                                                    </div>
                                                    <small class="text-muted">Monto ingresado por el cliente.</small>
                                                </div>
                                                
                                                <!-- Código de Pago (solo lectura - llenado por el cliente) -->
                                                <?php 
                                                $estado_pago_actual = strtolower(trim($pago_modal['estado_pago'] ?? ''));
                                                $mostrar_codigo_pago = ($estado_pago_actual === 'aprobado' && !empty($pago_modal['numero_transaccion']));
                                                $mostrar_motivo_rechazo = ($estado_pago_actual === 'rechazado');
                                                ?>
                                                <?php if ($mostrar_codigo_pago): ?>
                                                <div class="mb-3" id="codigo_pago_container_<?= $pedido['id_pedido'] ?>">
                                                    <label class="form-label"><strong>Código de Pago:</strong></label>
                                                    <input type="text" class="form-control" 
                                                           value="<?= htmlspecialchars($pago_modal['numero_transaccion'] ?? '') ?>" 
                                                           readonly style="background-color: #e9ecef;">
                                                    <small class="text-muted">Código ingresado por el cliente.</small>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <!-- Motivo de Rechazo (solo visible cuando se rechaza) -->
                                                <div class="mb-3" id="motivo_rechazo_container_<?= $pedido['id_pedido'] ?>" style="display: <?= $mostrar_motivo_rechazo ? 'block' : 'none' ?>;">
                                                    <label class="form-label"><strong>Motivo de Rechazo:</strong></label>
                                                    <textarea class="form-control" name="motivo_rechazo" rows="2" placeholder="Motivo del rechazo"><?= htmlspecialchars($pago_modal['motivo_rechazo'] ?? '') ?></textarea>
                                                    <small class="text-muted">Opcional. Motivo del rechazo del pago.</small>
                                                </div>
                                                
                                                <?php else: ?>
                                                <hr class="my-3">
                                                <div class="alert alert-warning mb-3">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    <strong>No hay pago registrado para este pedido</strong>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" name="actualizar_estado_pedido" class="btn btn-primary">
                                                    <i class="fas fa-save me-1"></i>Guardar Cambios
                                                </button>
                                            </div>
                                        </form>
                                        
                                        <!-- Formulario oculto para aprobar pago -->
                                        <?php if ($pago_modal && $pago_modal['estado_pago'] === 'pendiente'): ?>
                                        <form id="aprobar_pago_<?= $pedido['id_pedido'] ?>" method="POST" action="" style="display: none;">
                                            <input type="hidden" name="pago_id" value="<?= $pago_modal['id_pago'] ?>">
                                            <input type="hidden" name="aprobar_pago" value="1">
                                        </form>
                                        
                                        <!-- Modal para rechazar pago -->
                                        <div class="modal fade" id="rechazarPagoModal<?= $pedido['id_pedido'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title">Rechazar Pago - Pedido #<?= $pedido['id_pedido'] ?></h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="pago_id" value="<?= $pago_modal['id_pago'] ?>">
                                                            <div class="alert alert-warning">
                                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                                <strong>¿Estás seguro?</strong> Al rechazar el pago, el stock será restaurado si había sido descontado.
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label"><strong>Motivo del rechazo (opcional):</strong></label>
                                                                <textarea class="form-control" name="motivo_rechazo" rows="3" placeholder="Ej: Pago insuficiente, Tarjeta rechazada, etc."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" name="rechazar_pago" class="btn btn-danger">
                                                                <i class="fas fa-times me-1"></i>Rechazar Pago
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php endforeach; ?>
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
                            <table class="table table-hover sortable-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="sortable">ID</th>
                                        <th class="sortable">Nombre Completo</th>
                                        <th class="sortable">Email</th>
                                        <th class="sortable">Teléfono</th>
                                        <th class="sortable">Dirección</th>
                                        <th class="sortable">Fecha Registro</th>
                                        <th class="sortable">Total Pedidos</th>
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
                                            <?php 
                                            if (!empty($cliente['direccion'])) {
                                                echo '<small>' . htmlspecialchars($cliente['direccion']) . '</small>';
                                            } else {
                                                echo '<small>Sin dirección</small>';
                                            }
                                            ?>
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

            <!-- Pestaña de Métodos de Pago -->
            <div class="tab-pane fade" id="metodos-pago" role="tabpanel">
                <div class="row">
                    <!-- Formulario para agregar nuevo método -->
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus-circle me-2"></i>Agregar Método de Pago
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="nombre_metodo_nuevo" class="form-label">Nombre *</label>
                                        <input type="text" class="form-control" id="nombre_metodo_nuevo" 
                                               name="nombre_metodo" required 
                                               minlength="3" maxlength="100"
                                               pattern="[A-Za-z0-9\s\-]+"
                                               placeholder="Ej: Tarjeta de Crédito"
                                               title="El nombre debe tener entre 3 y 100 caracteres y solo puede contener letras, números, espacios y guiones">
                                        <small class="text-muted">Nombre que aparecerá en el checkout (mínimo 3 caracteres)</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="descripcion_metodo_nuevo" class="form-label">Descripción</label>
                                        <textarea class="form-control" id="descripcion_metodo_nuevo" 
                                                  name="descripcion_metodo" rows="3" 
                                                  maxlength="255"
                                                  placeholder="Descripción que verán los clientes"></textarea>
                                        <small class="text-muted">Descripción opcional que aparece en el sitio (máximo 255 caracteres). Solo letras (incluyendo tildes y diéresis: á, é, í, ó, ú, ñ, ü), números, espacios, puntos (.), comas (,), dos puntos (:), guiones (-) y comillas simples (')</small>
                                        <div class="invalid-feedback" id="error_descripcion_nuevo" style="display: none;">
                                            La descripción solo puede contener letras (incluyendo tildes y diéresis), números, espacios, puntos, comas, dos puntos, guiones y comillas simples
                                        </div>
                                    </div>
                                    <button type="submit" name="agregar_metodo_pago" class="btn btn-primary w-100">
                                        <i class="fas fa-save me-1"></i>Agregar Método
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de métodos existentes -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list me-2"></i>Métodos de Pago Disponibles
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($lista_metodos_pago)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-credit-card fa-3x mb-3"></i>
                                    <p>No hay métodos de pago registrados</p>
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover sortable-table">
                                        <thead class="table-dark">
                                            <tr>
                                                <th class="sortable">ID</th>
                                                <th class="sortable">Nombre</th>
                                                <th class="sortable">Descripción</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lista_metodos_pago as $metodo): ?>
                                            <tr>
                                                <td>#<?= $metodo['id_forma_pago'] ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($metodo['nombre']) ?></strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($metodo['descripcion'] ?: 'Sin descripción') ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editarMetodoModal<?= $metodo['id_forma_pago'] ?>">
                                                        <i class="fas fa-edit me-1"></i>Editar
                                                    </button>
                                                    
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#eliminarMetodoModal<?= $metodo['id_forma_pago'] ?>">
                                                        <i class="fas fa-trash me-1"></i>Eliminar
                                                    </button>

                                                    <!-- Modal para editar método -->
                                                    <div class="modal fade" id="editarMetodoModal<?= $metodo['id_forma_pago'] ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Editar Método de Pago</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST" action="">
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="id_forma_pago" value="<?= $metodo['id_forma_pago'] ?>">
                                                                        
                                                                        <div class="mb-3">
                                                                            <label class="form-label"><strong>Nombre *</strong></label>
                                                                            <input type="text" class="form-control" 
                                                                                   name="nombre_metodo" 
                                                                                   value="<?= htmlspecialchars($metodo['nombre']) ?>" 
                                                                                   required minlength="3" maxlength="100"
                                                                                   pattern="[A-Za-z0-9\s\-]+"
                                                                                   title="El nombre debe tener entre 3 y 100 caracteres y solo puede contener letras, números, espacios y guiones">
                                                                            <small class="text-muted">Nombre que aparecerá en el checkout (mínimo 3 caracteres)</small>
                                                                        </div>
                                                                        
                                                                        <div class="mb-3">
                                                                            <label class="form-label"><strong>Descripción</strong></label>
                                                                            <textarea class="form-control" 
                                                                                      name="descripcion_metodo" 
                                                                                      id="descripcion_metodo_edit_<?= $metodo['id_forma_pago'] ?>"
                                                                                      rows="3" 
                                                                                      maxlength="255"><?= htmlspecialchars($metodo['descripcion'] ?? '') ?></textarea>
                                                                            <small class="text-muted">Descripción que verán los clientes (máximo 255 caracteres). Solo letras (incluyendo tildes y diéresis: á, é, í, ó, ú, ñ, ü), números, espacios, puntos (.), comas (,), dos puntos (:), guiones (-) y comillas simples (')</small>
                                                                            <div class="invalid-feedback" id="error_descripcion_edit_<?= $metodo['id_forma_pago'] ?>" style="display: none;">
                                                                                La descripción solo puede contener letras (incluyendo tildes y diéresis), números, espacios, puntos, comas, dos puntos, guiones y comillas simples
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                        <button type="submit" name="actualizar_metodo_pago" class="btn btn-primary">
                                                                            <i class="fas fa-save me-1"></i>Guardar Cambios
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Modal para eliminar método -->
                                                    <div class="modal fade" id="eliminarMetodoModal<?= $metodo['id_forma_pago'] ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-danger text-white">
                                                                    <h5 class="modal-title">Eliminar Método de Pago</h5>
                                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST" action="">
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="id_forma_pago" value="<?= $metodo['id_forma_pago'] ?>">
                                                                        
                                                                        <div class="alert alert-warning">
                                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                                            <strong>¿Estás seguro?</strong>
                                                                        </div>
                                                                        
                                                                        <p>Se eliminará el método de pago:</p>
                                                                        <ul>
                                                                            <li><strong>ID:</strong> #<?= $metodo['id_forma_pago'] ?></li>
                                                                            <li><strong>Nombre:</strong> <?= htmlspecialchars($metodo['nombre']) ?></li>
                                                                        </ul>
                                                                        
                                                                        <p class="text-danger mb-0">
                                                                            <small>
                                                                                <i class="fas fa-info-circle me-1"></i>
                                                                                No se podrá eliminar si está siendo utilizado en algún pago registrado.
                                                                            </small>
                                                                        </p>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                        <button type="submit" name="eliminar_metodo_pago" class="btn btn-danger">
                                                                            <i class="fas fa-trash me-1"></i>Eliminar
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
                                        Total de métodos de pago: <?= count($lista_metodos_pago) ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestaña 4: Métricas -->
            <div class="tab-pane fade" id="metricas" role="tabpanel">
                <div class="row mb-4">
                    <!-- Top 10 Productos Más Vendidos -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-trophy me-2"></i>Top 10 Productos Más Vendidos
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($top_productos_vendidos)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover sortable-table">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th class="sortable">#</th>
                                                    <th class="sortable">Producto</th>
                                                    <th class="sortable">Categoría</th>
                                                    <th class="sortable">Talle</th>
                                                    <th class="sortable">Color</th>
                                                    <th class="sortable text-end">Unidades Vendidas</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $posicion = 1; ?>
                                                <?php foreach ($top_productos_vendidos as $producto): ?>
                                                    <tr>
                                                        <td><strong><?= $posicion++ ?></strong></td>
                                                        <td><?= htmlspecialchars($producto['nombre_producto']) ?></td>
                                                        <td><?= htmlspecialchars($producto['nombre_categoria']) ?></td>
                                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($producto['talle']) ?></span></td>
                                                        <td><span class="badge bg-info"><?= htmlspecialchars($producto['color']) ?></span></td>
                                                        <td class="text-end"><strong><?= number_format($producto['unidades_vendidas'], 0, ',', '.') ?></strong></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center mb-0">No hay productos vendidos aún</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Pedidos con más tiempo en estado -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Pedidos con Más Tiempo en Estado
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($pedidos_tiempo_estado)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover sortable-table">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th class="sortable">ID</th>
                                                    <th class="sortable">Cliente</th>
                                                    <th class="sortable">Estado</th>
                                                    <th class="sortable">Tiempo</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_slice($pedidos_tiempo_estado, 0, 5) as $pedido_tiempo): ?>
                                                    <?php
                                                    $estado_actual = trim($pedido_tiempo['estado_pedido'] ?? 'pendiente');
                                                    $estados_pedido_map = [
                                                        'pendiente' => ['color' => 'warning', 'nombre' => 'Pendiente'],
                                                        'preparacion' => ['color' => 'info', 'nombre' => 'Preparación'],
                                                        'en_viaje' => ['color' => 'primary', 'nombre' => 'En Viaje'],
                                                        'completado' => ['color' => 'success', 'nombre' => 'Completado'],
                                                        'devolucion' => ['color' => 'secondary', 'nombre' => 'Devolución'],
                                                        'cancelado' => ['color' => 'secondary', 'nombre' => 'Cancelado']
                                                    ];
                                                    $info_estado = $estados_pedido_map[$estado_actual] ?? ['color' => 'secondary', 'nombre' => ucfirst(str_replace('_', ' ', $estado_actual))];
                                                    $horas = intval($pedido_tiempo['horas_en_estado'] ?? 0);
                                                    $dias = intval($pedido_tiempo['dias_en_estado'] ?? 0);
                                                    $tiempo_formato = $dias > 0 ? $dias . ' día' . ($dias > 1 ? 's' : '') : $horas . ' hora' . ($horas > 1 ? 's' : '');
                                                    ?>
                                                    <tr>
                                                        <td>#<?= $pedido_tiempo['id_pedido'] ?></td>
                                                        <td><small><?= htmlspecialchars($pedido_tiempo['nombre'] . ' ' . $pedido_tiempo['apellido']) ?></small></td>
                                                        <td>
                                                            <span class="badge bg-<?= htmlspecialchars($info_estado['color']) ?>">
                                                                <?= htmlspecialchars($info_estado['nombre']) ?>
                                                            </span>
                                                        </td>
                                                        <td><strong><?= $tiempo_formato ?></strong></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center mb-0">No hay pedidos en estado activo</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Movimientos de Stock -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-exchange-alt me-2"></i>Movimientos de Stock Recientes
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($movimientos_stock)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover sortable-table">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th class="sortable">Fecha/Hora</th>
                                                    <th class="sortable">Tipo</th>
                                                    <th class="sortable">Producto</th>
                                                    <th class="sortable">Variante</th>
                                                    <th class="sortable">Categoría</th>
                                                    <th class="sortable text-end">Cantidad</th>
                                                    <th class="sortable">Pedido</th>
                                                    <th class="sortable">Usuario</th>
                                                    <th>Observaciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($movimientos_stock as $movimiento): ?>
                                                    <?php
                                                    // Mapeo de tipos de movimiento con colores
                                                    $tipos_movimiento_map = [
                                                        'venta' => ['color' => 'success', 'nombre' => 'Venta', 'signo' => '-'],
                                                        'ingreso' => ['color' => 'info', 'nombre' => 'Ingreso', 'signo' => '+'],
                                                        'ajuste' => ['color' => 'warning', 'nombre' => 'Ajuste', 'signo' => ''],
                                                        'devolucion' => ['color' => 'secondary', 'nombre' => 'Devolución', 'signo' => '+']
                                                    ];
                                                    $tipo_mov = strtolower(trim($movimiento['tipo_movimiento'] ?? ''));
                                                    $info_tipo = $tipos_movimiento_map[$tipo_mov] ?? ['color' => 'secondary', 'nombre' => ucfirst($tipo_mov), 'signo' => ''];
                                                    
                                                    // Formatear cantidad con signo
                                                    $cantidad = intval($movimiento['cantidad'] ?? 0);
                                                    $signo = $info_tipo['signo'];
                                                    if ($tipo_mov === 'ajuste' && $cantidad < 0) {
                                                        $signo = '';
                                                    } elseif ($tipo_mov === 'ajuste' && $cantidad > 0) {
                                                        $signo = '+';
                                                    }
                                                    $cantidad_formato = $signo . number_format(abs($cantidad), 0, ',', '.');
                                                    
                                                    // Clase de color para cantidad
                                                    $clase_cantidad = '';
                                                    if ($tipo_mov === 'venta') {
                                                        $clase_cantidad = 'text-danger';
                                                    } elseif (in_array($tipo_mov, ['ingreso', 'devolucion']) || ($tipo_mov === 'ajuste' && $cantidad > 0)) {
                                                        $clase_cantidad = 'text-success';
                                                    }
                                                    
                                                    // Truncar observaciones si son muy largas
                                                    $observaciones = $movimiento['observaciones'] ?? '';
                                                    $observaciones_truncadas = '';
                                                    if (!empty($observaciones)) {
                                                        if (strlen($observaciones) > 50) {
                                                            $observaciones_truncadas = htmlspecialchars(substr($observaciones, 0, 50)) . '...';
                                                            $observaciones_completas = htmlspecialchars($observaciones);
                                                        } else {
                                                            $observaciones_truncadas = htmlspecialchars($observaciones);
                                                            $observaciones_completas = '';
                                                        }
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><small><?= date('d/m/Y H:i', strtotime($movimiento['fecha_movimiento'])) ?></small></td>
                                                        <td>
                                                            <span class="badge bg-<?= htmlspecialchars($info_tipo['color']) ?>">
                                                                <?= htmlspecialchars($info_tipo['nombre']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= htmlspecialchars($movimiento['nombre_producto']) ?></td>
                                                        <td>
                                                            <span class="badge bg-secondary"><?= htmlspecialchars($movimiento['talle']) ?></span>
                                                            <span class="badge bg-info"><?= htmlspecialchars($movimiento['color']) ?></span>
                                                        </td>
                                                        <td><?= htmlspecialchars($movimiento['nombre_categoria']) ?></td>
                                                        <td class="text-end">
                                                            <strong class="<?= $clase_cantidad ?>"><?= $cantidad_formato ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($movimiento['id_pedido'])): ?>
                                                                <span class="badge bg-primary">#<?= $movimiento['id_pedido'] ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted"><small>N/A</small></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($movimiento['nombre_usuario'])): ?>
                                                                <small><?= htmlspecialchars($movimiento['nombre_usuario']) ?></small>
                                                            <?php else: ?>
                                                                <span class="text-muted"><small>N/A</small></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($observaciones_truncadas)): ?>
                                                                <?php if (!empty($observaciones_completas)): ?>
                                                                    <span data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $observaciones_completas ?>">
                                                                        <small><?= $observaciones_truncadas ?></small>
                                                                    </span>
                                                                <?php else: ?>
                                                                    <small><?= $observaciones_truncadas ?></small>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="text-muted"><small>-</small></span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center mb-0">No hay movimientos de stock registrados</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript para mostrar/ocultar campos dinámicamente en modal Editar Estado -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Función para manejar la visibilidad de campos según el estado del pago
        function toggleCamposPago(pedidoId) {
            const selectEstadoPago = document.getElementById('nuevo_estado_pago_' + pedidoId);
            const codigoPagoContainer = document.getElementById('codigo_pago_container_' + pedidoId);
            const motivoRechazoContainer = document.getElementById('motivo_rechazo_container_' + pedidoId);
            
            if (!selectEstadoPago) {
                return; // Select no encontrado, salir
            }
            
            const estadoSeleccionado = selectEstadoPago.value;
            // Si no hay selección, usar el estado actual (valor del option selected)
            const estadoActual = estadoSeleccionado || (selectEstadoPago.options[selectEstadoPago.selectedIndex]?.value || '');
            const estadoFinal = estadoSeleccionado || estadoActual;
            
            // Mostrar/ocultar código de pago cuando se aprueba o ya está aprobado
            // Nota: El código de pago es solo lectura (llenado por el cliente), solo se muestra si existe
            if (codigoPagoContainer) {
                if (estadoFinal === 'aprobado') {
                    codigoPagoContainer.style.display = 'block';
                } else {
                    codigoPagoContainer.style.display = 'none';
                }
            }
            
            // Mostrar/ocultar motivo de rechazo cuando se rechaza
            if (motivoRechazoContainer) {
                if (estadoFinal === 'rechazado') {
                    motivoRechazoContainer.style.display = 'block';
                } else {
                    motivoRechazoContainer.style.display = 'none';
                }
            }
        }
        
        // Inicializar para todos los modales al cargar la página
        const modalesEstado = document.querySelectorAll('[id^="editarEstadoModal"]');
        modalesEstado.forEach(function(modal) {
            const pedidoId = modal.id.replace('editarEstadoModal', '');
            const selectEstadoPago = document.getElementById('nuevo_estado_pago_' + pedidoId);
            
            if (selectEstadoPago) {
                // Ejecutar al cargar (para estado inicial)
                toggleCamposPago(pedidoId);
                
                // Ejecutar cuando cambia el select
                selectEstadoPago.addEventListener('change', function() {
                    toggleCamposPago(pedidoId);
                });
            }
        });
        
        // También ejecutar cuando se abre el modal (por si acaso)
        modalesEstado.forEach(function(modal) {
            modal.addEventListener('shown.bs.modal', function() {
                const pedidoId = modal.id.replace('editarEstadoModal', '');
                toggleCamposPago(pedidoId);
            });
        });
    });
    
    // Función para toggle de pedidos inactivos
    function togglePedidosInactivos(mostrar) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('tab', 'pedidos');
        
        if (mostrar) {
            urlParams.set('mostrar_inactivos', '1');
        } else {
            urlParams.delete('mostrar_inactivos');
        }
        
        window.location.href = 'ventas.php?' + urlParams.toString();
    }
    
    // Validación de descripción de método de pago
    document.addEventListener('DOMContentLoaded', function() {
        /**
         * Valida la descripción del método de pago según el patrón del backend
         * Hace trim() antes de validar para asegurar consistencia con el backend
         * Patrón permitido: letras (A-Z, a-z con tildes y diéresis: á, é, í, ó, ú, ñ, ü), números (0-9), espacios, puntos (.), comas (,), dos puntos (:), guiones (-), comillas simples (')
         * @param {string} descripcion - Texto a validar
         * @return {boolean} - true si es válido, false si no
         */
        function validarDescripcionMetodoPago(descripcion) {
            // Hacer trim() igual que el backend (línea 414 de sales_functions.php)
            const descripcionTrimmed = descripcion ? descripcion.trim() : '';
            
            // Si está vacía después del trim, es válida (es opcional)
            if (descripcionTrimmed === '') {
                return true;
            }
            
            // Validar caracteres permitidos: letras (con tildes y diéresis), números, espacios, puntos, comas, dos puntos, guiones, comillas simples
            const patron = /^[A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9\s\.,\:\-\']+$/;
            return patron.test(descripcionTrimmed);
        }
        
        /**
         * Valida y muestra/oculta el mensaje de error para el campo descripción
         * @param {HTMLElement} textarea - Elemento textarea a validar
         * @param {HTMLElement} errorDiv - Elemento div donde mostrar el error
         */
        function validarCampoDescripcion(textarea, errorDiv) {
            const valor = textarea.value;
            const esValido = validarDescripcionMetodoPago(valor);
            
            if (!esValido && valor.trim() !== '') {
                // Mostrar error
                textarea.classList.add('is-invalid');
                if (errorDiv) {
                    errorDiv.style.display = 'block';
                }
            } else {
                // Ocultar error
                textarea.classList.remove('is-invalid');
                if (errorDiv) {
                    errorDiv.style.display = 'none';
                }
            }
        }
        
        // Validación para el formulario de agregar método de pago
        const descripcionNuevo = document.getElementById('descripcion_metodo_nuevo');
        const errorDescripcionNuevo = document.getElementById('error_descripcion_nuevo');
        if (descripcionNuevo) {
            // Validar al escribir
            descripcionNuevo.addEventListener('input', function() {
                validarCampoDescripcion(descripcionNuevo, errorDescripcionNuevo);
            });
            
            // Normalizar valor en blur (aplicar trim automáticamente)
            descripcionNuevo.addEventListener('blur', function() {
                const valorOriginal = descripcionNuevo.value;
                const valorTrimmed = valorOriginal.trim();
                if (valorOriginal !== valorTrimmed) {
                    descripcionNuevo.value = valorTrimmed;
                }
                validarCampoDescripcion(descripcionNuevo, errorDescripcionNuevo);
            });
            
            // Validar antes de enviar el formulario
            const formAgregar = descripcionNuevo.closest('form');
            if (formAgregar) {
                formAgregar.addEventListener('submit', function(e) {
                    // Aplicar trim antes de validar
                    const valorTrimmed = descripcionNuevo.value.trim();
                    if (descripcionNuevo.value !== valorTrimmed) {
                        descripcionNuevo.value = valorTrimmed;
                    }
                    if (!validarDescripcionMetodoPago(descripcionNuevo.value)) {
                        e.preventDefault();
                        validarCampoDescripcion(descripcionNuevo, errorDescripcionNuevo);
                        descripcionNuevo.focus();
                        return false;
                    }
                });
            }
        }
        
        // Validación para los formularios de editar método de pago (múltiples modales)
        function inicializarValidacionEditar() {
            const textareasEdit = document.querySelectorAll('[id^="descripcion_metodo_edit_"]');
            textareasEdit.forEach(function(textarea) {
                // Evitar agregar listeners múltiples veces
                if (textarea.dataset.validationInitialized === 'true') {
                    return;
                }
                textarea.dataset.validationInitialized = 'true';
                
                const metodoId = textarea.id.replace('descripcion_metodo_edit_', '');
                const errorDiv = document.getElementById('error_descripcion_edit_' + metodoId);
                
                // Validar al escribir
                textarea.addEventListener('input', function() {
                    validarCampoDescripcion(textarea, errorDiv);
                });
                
                // Normalizar valor en blur (aplicar trim automáticamente)
                textarea.addEventListener('blur', function() {
                    const valorOriginal = textarea.value;
                    const valorTrimmed = valorOriginal.trim();
                    if (valorOriginal !== valorTrimmed) {
                        textarea.value = valorTrimmed;
                    }
                    validarCampoDescripcion(textarea, errorDiv);
                });
                
                // Validar antes de enviar el formulario
                const formEdit = textarea.closest('form');
                if (formEdit) {
                    formEdit.addEventListener('submit', function(e) {
                        // Aplicar trim antes de validar
                        const valorTrimmed = textarea.value.trim();
                        if (textarea.value !== valorTrimmed) {
                            textarea.value = valorTrimmed;
                        }
                        if (!validarDescripcionMetodoPago(textarea.value)) {
                            e.preventDefault();
                            validarCampoDescripcion(textarea, errorDiv);
                            textarea.focus();
                            return false;
                        }
                    });
                }
            });
        }
        
        // Inicializar validación al cargar la página
        inicializarValidacionEditar();
        
        // Reinicializar cuando se abren modales de edición
        const modalesEditarMetodo = document.querySelectorAll('[id^="editarMetodoModal"]');
        modalesEditarMetodo.forEach(function(modal) {
            modal.addEventListener('shown.bs.modal', function() {
                inicializarValidacionEditar();
            });
        });
    });
    </script>
    <script src="js/table-sort.js"></script>

<?php include 'includes/footer.php'; render_footer(); ?>
