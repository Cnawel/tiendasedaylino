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
 *    - Estados disponibles: pendiente, preparacion, en_viaje, completado, cancelado
 *    - NOTA: devolucion existe en DB pero NO está implementado en MVP
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

// Cargar helpers de estados (mapeos y normalización)
require_once __DIR__ . '/includes/estado_helpers.php';

// Cargar funciones de validación de estados (transiciones)
require_once __DIR__ . '/includes/state_functions.php';

// Cargar componentes de ventas (modales y secciones)
require_once __DIR__ . '/includes/ventas_components.php';

// Cargar funciones de dashboard
require_once __DIR__ . '/includes/dashboard_functions.php';
require_once __DIR__ . '/includes/admin_functions.php';
require_once __DIR__ . '/includes/security_functions.php';

// Configurar título de la página
$titulo_pagina = 'Panel de Ventas';

// ============================================================================
// PROCESAMIENTO DE FORMULARIOS
// ============================================================================

// Obtener mensajes de sesión usando función centralizada
$resultado_mensaje = obtenerMensajeSession();
$mensaje = $resultado_mensaje['mensaje'];
$mensaje_tipo = $resultado_mensaje['mensaje_tipo'];


// Incluir queries necesarias
require_once __DIR__ . '/includes/queries/stock_queries.php';
require_once __DIR__ . '/includes/queries/pago_queries.php';
require_once __DIR__ . '/includes/queries/forma_pago_queries.php';
require_once __DIR__ . '/includes/queries/cliente_queries.php';

// ============================================================================
// PROCESAR FORMULARIOS POST
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = false;

    // Determinar acción basada en parámetros POST
    if (isset($_POST['actualizar_estado_pedido'])) {
        $resultado = procesarActualizacionPedidoPago($mysqli, $_POST, $id_usuario);
    } elseif (isset($_POST['aprobar_pago'])) {
        $resultado = procesarAprobacionPago($mysqli, $_POST, $id_usuario);
    } elseif (isset($_POST['rechazar_pago'])) {
        $resultado = procesarRechazoPago($mysqli, $_POST, $id_usuario);
    } elseif (isset($_POST['agregar_metodo_pago'])) {
        $resultado = procesarAgregarMetodoPago($mysqli, $_POST);
    } elseif (isset($_POST['actualizar_metodo_pago'])) {
        $resultado = procesarActualizarMetodoPago($mysqli, $_POST);
    } elseif (isset($_POST['eliminar_metodo_pago'])) {
        $resultado = procesarEliminarMetodoPago($mysqli, $_POST);
    } elseif (isset($_POST['toggle_activo_metodo_pago'])) {
        $resultado = procesarToggleActivoMetodoPago($mysqli, $_POST);
    }
    
    // Si hay resultado, procesar redirección
    if ($resultado !== false) {
        $_SESSION['mensaje'] = $resultado['mensaje'];
        $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];

        // Preservar tab desde POST si está presente (para acciones de métodos de pago)
        $params_adicionales = null;
        if (isset($_POST['tab']) && !empty($_POST['tab'])) {
            $params_adicionales = ['tab' => $_POST['tab']];
        }

        $redirect_url = construirRedirectUrl('ventas.php', $params_adicionales);
        header('Location: ' . $redirect_url);
        exit;
    } else {
        // Si la acción fue reconocida pero retornó false, NO mostrar ningún mensaje
        // (significa que no hubo cambios, el usuario simplemente cerró el modal sin modificar nada)

        // Preservar tab si existe
        $params_adicionales = null;
        if (isset($_POST['tab']) && !empty($_POST['tab'])) {
            $params_adicionales = ['tab' => $_POST['tab']];
        }

        $redirect_url = construirRedirectUrl('ventas.php', $params_adicionales);
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

// Obtener parámetro para mostrar métodos de pago inactivos
$mostrar_metodos_inactivos = isset($_GET['mostrar_metodos_inactivos']) && $_GET['mostrar_metodos_inactivos'] == '1';

// Obtener parámetro para determinar pestaña activa
$tab_activo = isset($_GET['tab']) ? $_GET['tab'] : 'pedidos';
// Validar que el tab sea válido
$tabs_validos = ['pedidos', 'clientes', 'metodos-pago', 'metricas'];
if (!in_array($tab_activo, $tabs_validos)) {
    $tab_activo = 'pedidos';
}

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

// Obtener lista de métodos de pago usando función centralizada (incluye activos e inactivos para admin)
$lista_metodos_pago = obtenerFormasPagoAdmin($mysqli);

// Filtrar métodos de pago según el toggle (por defecto solo mostrar activos)
if (!$mostrar_metodos_inactivos) {
    $lista_metodos_pago = array_filter($lista_metodos_pago, function($metodo) {
        return (int)($metodo['activo'] ?? 1) === 1;
    });
    // Reindexar el array después del filtro
    $lista_metodos_pago = array_values($lista_metodos_pago);
}

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
                        <p class="text-secondary mb-0">Bienvenido, <?= htmlspecialchars($usuario_actual['nombre'] . ' ' . $usuario_actual['apellido']) ?></p>
                    </div>
                    <div>
                        <a href="perfil.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-user"></i> Mi Perfil
                        </a>
                        <a href="logout.php" class="btn btn-outline-secondary btn-logout">
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
                <button class="nav-link <?= $tab_activo === 'pedidos' ? 'active' : '' ?>" id="pedidos-tab" data-bs-toggle="tab" data-bs-target="#pedidos" type="button" role="tab">
                    <i class="fas fa-shopping-cart me-2"></i>Pedidos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $tab_activo === 'clientes' ? 'active' : '' ?>" id="clientes-tab" data-bs-toggle="tab" data-bs-target="#clientes" type="button" role="tab">
                    <i class="fas fa-users me-2"></i>Clientes
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $tab_activo === 'metodos-pago' ? 'active' : '' ?>" id="metodos-pago-tab" data-bs-toggle="tab" data-bs-target="#metodos-pago" type="button" role="tab">
                    <i class="fas fa-credit-card me-2"></i>Métodos de Pago
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $tab_activo === 'metricas' ? 'active' : '' ?>" id="metricas-tab" data-bs-toggle="tab" data-bs-target="#metricas" type="button" role="tab">
                    <i class="fas fa-chart-line me-2"></i>Métricas
                </button>
            </li>
        </ul>

        <div class="tab-content" id="ventasTabsContent">
            <!-- Pestaña de Pedidos -->
            <div class="tab-pane fade <?= $tab_activo === 'pedidos' ? 'show active' : '' ?>" id="pedidos" role="tabpanel">
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
                                       data-toggle-inactivos>
                                <label class="form-check-label" for="mostrarInactivos">
                                    <small class="text-secondary">Mostrar pedidos de usuarios inactivos</small>
                                </label>
                            </div>
                            <label class="mb-0"><small>Mostrar:</small></label>
                            <select class="form-select form-select-sm" style="width: auto;" id="selectLimitePedidos">
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
                        <?php
                        // Obtener todos los pagos en una sola query (optimización N+1)
                        $pedidos_ids = array_column($pedidos_recientes, 'id_pedido');
                        $pagos_por_pedido = obtenerPagosPorPedidos($mysqli, $pedidos_ids);
                        ?>
                        <div class="table-responsive">
                            <table class="table sortable-table">
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
                                    // Obtener pago del mapa precargado (optimización N+1)
                                    $pago_pedido = $pagos_por_pedido[$pedido['id_pedido']] ?? null;
                                    
                                    // Normalizar estado del pedido usando función centralizada
                                    $estado_pedido = normalizarEstado($pedido['estado_pedido'] ?? '');
                                    
                                    // Normalizar estado del pago si existe
                                    $estado_pago_para_detectar = null;
                                    if ($pago_pedido && isset($pago_pedido['estado_pago'])) {
                                        $estado_pago_para_detectar = normalizarEstado($pago_pedido['estado_pago']);
                                    }
                                    
                                    // Obtener información del estado usando función centralizada
                                    $info_estado = obtenerInfoEstadoPedido($estado_pedido);
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
                                            <?php
                                            // Detectar inconsistencias usando función centralizada
                                            // Detecta todos los casos críticos y de advertencia según análisis completo
                                            // Usar estado normalizado del pago
                                            $inconsistencias = detectarInconsistenciasEstado(
                                                $estado_pedido, 
                                                $estado_pago_para_detectar
                                            );
                                            $hay_inconsistencia = $inconsistencias['hay_inconsistencia'];
                                            $tipo_inconsistencia = $inconsistencias['tipo'] ?? null;
                                            $mensaje_inconsistencia = $inconsistencias['mensaje'] ?? '';
                                            $accion_sugerida = $inconsistencias['accion_sugerida'] ?? '';
                                            $severidad = $inconsistencias['severidad'] ?? '';
                                            ?>
                                            <span class="badge bg-<?= htmlspecialchars($info_estado['color']) ?>">
                                                <?= htmlspecialchars($info_estado['nombre']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($pago_pedido): ?>
                                                <?php
                                                // Obtener información del estado de pago usando función centralizada
                                                $info_estado_pago = obtenerInfoEstadoPago($pago_pedido['estado_pago'] ?? '');
                                                ?>
                                                <span class="badge bg-<?= htmlspecialchars($info_estado_pago['color']) ?>">
                                                    <?= htmlspecialchars($info_estado_pago['nombre']) ?>
                                                </span>
                                            <?php else: ?>
                                                <?php
                                                // Obtener información del estado de pago por defecto (pendiente) cuando no hay pago registrado
                                                $info_estado_pago = obtenerInfoEstadoPago(null);
                                                ?>
                                                <span class="badge bg-<?= htmlspecialchars($info_estado_pago['color']) ?>">
                                                    <?= htmlspecialchars($info_estado_pago['nombre']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary me-1"
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
                            // Obtener pago del mapa precargado
                            $pago_pedido = $pagos_por_pedido[$pedido['id_pedido']] ?? null;
                            
                            // Obtener información completa del pedido para los modales
                            $pedido_completo = obtenerPedidoPorId($mysqli, $pedido['id_pedido']);
                            $detalles_pedido = obtenerDetallesPedido($mysqli, $pedido['id_pedido']);
                            $pago_detalle = $pago_pedido;
                            $pedido_completo_modal = $pedido_completo;
                            $pago_modal = $pago_pedido;
                            
                            // Normalizar estado actual del pedido para el formulario
                            $estado_actual_modal = normalizarEstado($pedido_completo_modal['estado_pedido'] ?? '');
                            
                            // Renderizar modales usando funciones helper (reduce anidación)
                            renderModalVerPedido($pedido, $pedido_completo, $detalles_pedido, $pago_detalle);
                            renderModalEditarEstado($pedido, $pedido_completo_modal, $pago_modal, $estado_actual_modal);
                            ?>
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
            <div class="tab-pane fade <?= $tab_activo === 'clientes' ? 'show active' : '' ?>" id="clientes" role="tabpanel">
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
                            <table class="table sortable-table">
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
                                                echo '<small class="text-secondary">' . htmlspecialchars($cliente['direccion']) . '</small>';
                                            } else {
                                                echo '<small class="text-secondary">Sin dirección</small>';
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
            <div class="tab-pane fade <?= $tab_activo === 'metodos-pago' ? 'show active' : '' ?>" id="metodos-pago" role="tabpanel">
                <!-- Mensajes -->
                <?php if ($mensaje): ?>
                <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
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
                                        <small class="text-secondary">Nombre que aparecerá en el checkout (mínimo 3 caracteres)</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="descripcion_metodo_nuevo" class="form-label">Descripción</label>
                                        <textarea class="form-control" id="descripcion_metodo_nuevo" 
                                                  name="descripcion_metodo" rows="3" 
                                                  maxlength="255"
                                                  placeholder="Descripción que verán los clientes"></textarea>
                                        <small class="text-secondary">Máximo 255 caracteres.</small>
                                        <div class="invalid-feedback" id="error_descripcion_nuevo" style="display: none;">
                                            La descripción solo puede contener letras (incluyendo tildes y diéresis), números, espacios, puntos, comas, dos puntos, guiones y comillas simples
                                        </div>
                                    </div>
                                    <button type="submit" name="agregar_metodo_pago" class="btn btn-primary w-100" data-auto-lock="true" data-lock-time="2000" data-lock-text="Agregando método...">
                                        <i class="fas fa-save me-1"></i>Agregar Método
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de métodos existentes -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list me-2"></i>Métodos de Pago Disponibles
                                </h5>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="mostrarMetodosInactivos" 
                                           <?= $mostrar_metodos_inactivos ? 'checked' : '' ?>
                                           data-toggle-metodos-inactivos>
                                    <label class="form-check-label" for="mostrarMetodosInactivos">
                                        <small>Ver Métodos de Pago Inactivos</small>
                                    </label>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($lista_metodos_pago)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-credit-card fa-3x mb-3"></i>
                                    <p>No hay métodos de pago registrados</p>
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table sortable-table">
                                        <thead class="table-dark">
                                            <tr>
                                                <th class="sortable">ID</th>
                                                <th class="sortable">Nombre</th>
                                                <th class="sortable">Descripción</th>
                                                <th class="sortable">Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lista_metodos_pago as $metodo): ?>
                                            <?php 
                                            $activo = (int)($metodo['activo'] ?? 1);
                                            $es_activo = $activo === 1;
                                            ?>
                                            <tr class="<?= !$es_activo ? 'table-secondary' : '' ?>">
                                                <td>#<?= $metodo['id_forma_pago'] ?></td>
                                                <td>
                                                    <strong class="<?= !$es_activo ? 'text-decoration-line-through text-muted' : '' ?>">
                                                        <?= htmlspecialchars($metodo['nombre']) ?>
                                                    </strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($metodo['descripcion'] ?: 'Sin descripción') ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($es_activo): ?>
                                                        <span class="badge bg-success">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('¿Estás seguro de <?= $es_activo ? 'desactivar' : 'activar' ?> este método de pago?');">
                                                        <input type="hidden" name="id_forma_pago" value="<?= $metodo['id_forma_pago'] ?>">
                                                        <button type="submit" 
                                                                name="toggle_activo_metodo_pago"
                                                                class="btn btn-sm btn-outline-primary"
                                                                data-auto-lock="true" 
                                                                data-lock-time="2000" 
                                                                data-lock-text="<?= $es_activo ? 'Desactivando...' : 'Activando...' ?>">
                                                            <i class="fas fa-<?= $es_activo ? 'toggle-on' : 'toggle-off' ?> me-1"></i>
                                                            <?= $es_activo ? 'Desactivar' : 'Activar' ?>
                                                        </button>
                                                    </form>
                                                    
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editarMetodoModal<?= $metodo['id_forma_pago'] ?>">
                                                        <i class="fas fa-edit me-1"></i>Editar
                                                    </button>
                                                    
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-primary" 
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
                                                                            <small class="text-secondary">Nombre que aparecerá en el checkout (mínimo 3 caracteres)</small>
                                                                        </div>
                                                                        
                                                                        <div class="mb-3">
                                                                            <label class="form-label"><strong>Descripción</strong></label>
                                                                            <textarea class="form-control" 
                                                                                      name="descripcion_metodo" 
                                                                                      id="descripcion_metodo_edit_<?= $metodo['id_forma_pago'] ?>"
                                                                                      rows="3" 
                                                                                      maxlength="255"><?= htmlspecialchars($metodo['descripcion'] ?? '') ?></textarea>
                                                                            <small class="text-secondary">Máximo 255 caracteres.</small>
                                                                            <div class="invalid-feedback" id="error_descripcion_edit_<?= $metodo['id_forma_pago'] ?>" style="display: none;">
                                                                                La descripción solo puede contener letras (incluyendo tildes y diéresis), números, espacios, puntos, comas, dos puntos, guiones y comillas simples
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                        <button type="submit" name="actualizar_metodo_pago" class="btn btn-primary" data-auto-lock="true" data-lock-time="2000" data-lock-text="Guardando cambios...">
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
                                                                        <input type="hidden" name="tab" value="metodos-pago">
                                                                        
                                                                        <div class="alert alert-warning">
                                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                                            <strong>¿Estás seguro?</strong>
                                                                        </div>
                                                                        
                                                                        <p>Se eliminará el método de pago:</p>
                                                                        <ul>
                                                                            <li><strong>ID:</strong> #<?= $metodo['id_forma_pago'] ?></li>
                                                                            <li><strong>Nombre:</strong> <?= htmlspecialchars($metodo['nombre']) ?></li>
                                                                        </ul>
                                                                        
                                                                        <p class="text-dark mb-0">
                                                                            <small class="text-secondary">
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
            <div class="tab-pane fade <?= $tab_activo === 'metricas' ? 'show active' : '' ?>" id="metricas" role="tabpanel">
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
                                        <table class="table sortable-table">
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
                                                        <td><span class="badge bg-dark"><?= htmlspecialchars($producto['talle']) ?></span></td>
                                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($producto['color']) ?></span></td>
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
                                        <table class="table table-sm sortable-table">
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
                                                    // Obtener información del estado usando función centralizada
                                                    $info_estado = obtenerInfoEstadoPedido($pedido_tiempo['estado_pedido'] ?? '');
                                                    // Usar nueva función de formateo de tiempo
                                                    $tiempo_formato = formatearTiempoEstado($pedido_tiempo['tiempo_detallado'] ?? [
                                                        'dias' => intval($pedido_tiempo['dias_en_estado'] ?? 0),
                                                        'horas' => 0,
                                                        'minutos' => 0,
                                                        'segundos' => 0
                                                    ]);
                                                    ?>
                                                    <tr>
                                                        <td>#<?= $pedido_tiempo['id_pedido'] ?></td>
                                                        <td><small class="text-secondary"><?= htmlspecialchars($pedido_tiempo['nombre'] . ' ' . $pedido_tiempo['apellido']) ?></small></td>
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
                                        <table class="table sortable-table">
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
                                                    // Obtener mapeo de tipos de movimiento
                                                    $tipos_movimiento_map = obtenerTiposMovimientoMap();
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
                                                    
                                                    // Truncar observaciones si son muy largas (aumentado a 120 caracteres)
                                                    $observaciones = $movimiento['observaciones'] ?? '';
                                                    $observaciones_truncadas = '';
                                                    $observaciones_completas = '';
                                                    if (!empty($observaciones)) {
                                                        $observaciones_escaped = htmlspecialchars($observaciones);
                                                        if (strlen($observaciones) > 500) {
                                                            $observaciones_truncadas = substr($observaciones_escaped, 0, 500) . '...';
                                                            $observaciones_completas = nl2br($observaciones_escaped); // Preservar saltos de línea
                                                        } else {
                                                            $observaciones_truncadas = $observaciones_escaped;
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
                                                                        <small class="text-secondary"><?= $observaciones_truncadas ?></small>
                                                                    </span>
                                                                <?php else: ?>
                                                                    <small class="text-secondary"><?= $observaciones_truncadas ?></small>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="text-secondary"><small>-</small></span>
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


<?php include 'includes/footer.php'; render_footer(); ?>
