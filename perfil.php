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

// Cargar funciones de seguridad
require_once 'includes/security_functions.php';

// Cargar funciones de contraseñas
require_once 'includes/password_functions.php';

// Cargar funciones de perfil
require_once 'includes/perfil_functions.php';

// Cargar componentes reutilizables de perfil
require_once 'includes/perfil_components.php';

// Conectar a la base de datos
require_once __DIR__ . '/config/database.php';

// Cargar queries de perfil
require_once __DIR__ . '/includes/queries_helper.php'; // Necesario para cargarArchivoQueries()
require_once __DIR__ . '/includes/queries/usuario_queries.php'; // Necesario para verificarHashContrasena()
require_once __DIR__ . '/includes/queries/perfil_queries.php';
require_once __DIR__ . '/includes/queries/pedido_queries.php';
require_once __DIR__ . '/includes/queries/stock_queries.php';
require_once __DIR__ . '/includes/queries/pago_queries.php'; // Necesario para marcarPagoPagadoPorCliente()
require_once __DIR__ . '/includes/queries/detalle_pedido_queries.php'; // Necesario para obtenerDetallesPedido() en operaciones de stock
require_once __DIR__ . '/includes/sales_functions.php';
require_once __DIR__ . '/includes/estado_helpers.php';
require_once __DIR__ . '/includes/state_functions.php'; // Necesario para validarTransicionPedido() y estaEnRecorridoActivoPedido()
require_once __DIR__ . '/includes/envio_functions.php'; // Necesario para parsearDireccion()

// Configurar título de la página
$titulo_pagina = 'Mi Perfil';

$id_usuario = $_SESSION['id_usuario'];

// Leer mensajes de sesión (patrón Post-Redirect-Get)
$mensaje = '';
$mensaje_tipo = '';
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $mensaje_tipo = $_SESSION['mensaje_tipo'] ?? '';
    // Limpiar mensajes de sesión después de leerlos
    unset($_SESSION['mensaje']);
    unset($_SESSION['mensaje_tipo']);
}

// ============================================================================
// OBTENER PREGUNTAS DE RECUPERO DESDE BASE DE DATOS
// ============================================================================
$preguntas_recupero = obtenerPreguntasRecupero($mysqli);
// Si no hay preguntas en BD, usar valores por defecto (fallback)
if (empty($preguntas_recupero)) {
    $preguntas_recupero = [
        ['id_pregunta' => 1, 'texto_pregunta' => '¿Cuál es el nombre de tu primera mascota?'],
        ['id_pregunta' => 2, 'texto_pregunta' => '¿En qué ciudad naciste?'],
        ['id_pregunta' => 3, 'texto_pregunta' => '¿Cuál es el nombre de tu mejor amigo/a de la infancia?'],
        ['id_pregunta' => 4, 'texto_pregunta' => '¿Cuál es el nombre de tu colegio primario?']
    ];
}

// ============================================================================
// PROCESAR FORMULARIOS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determinar acción usando switch para reducir anidación
    $accion = null;
    if (isset($_POST['actualizar_recupero'])) {
        $accion = 'actualizar_recupero';
    } elseif (isset($_POST['actualizar_datos'])) {
        $accion = 'actualizar_datos';
    } elseif (isset($_POST['cambiar_contrasena'])) {
        $accion = 'cambiar_contrasena';
    } elseif (isset($_POST['marcar_pago_pagado'])) {
        $accion = 'marcar_pago_pagado';
    } elseif (isset($_POST['cancelar_pedido_cliente'])) {
        $accion = 'cancelar_pedido_cliente';
    } elseif (isset($_POST['eliminar_cuenta'])) {
        $accion = 'eliminar_cuenta';
    }
    
    // Procesar acción usando switch
    switch ($accion) {
        case 'actualizar_recupero':
            $resultado = procesarActualizacionRecupero($mysqli, $id_usuario, $_POST);
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
            $redirect_url = construirRedirectUrl('perfil.php');
            header('Location: ' . $redirect_url);
            exit;
            
        case 'actualizar_datos':
            $resultado = procesarActualizacionDatos($mysqli, $id_usuario, $_POST);
            // Actualizar sesión si los datos se actualizaron correctamente
            if (!empty($resultado['datos_actualizados'])) {
                $_SESSION['nombre'] = $resultado['datos_actualizados']['nombre'];
                $_SESSION['apellido'] = $resultado['datos_actualizados']['apellido'];
                $_SESSION['email'] = $resultado['datos_actualizados']['email'];
            }
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
            $redirect_url = construirRedirectUrl('perfil.php');
            header('Location: ' . $redirect_url);
            exit;
            
        case 'cambiar_contrasena':
            $resultado = procesarCambioContrasena($mysqli, $id_usuario, $_POST);
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
            $redirect_url = construirRedirectUrl('perfil.php');
            header('Location: ' . $redirect_url);
            exit;
            
        case 'marcar_pago_pagado':
            // Usar función centralizada para marcar pago como pagado por cliente
            $id_pago = intval($_POST['id_pago'] ?? 0);
            $numero_transaccion = trim($_POST['numero_transaccion'] ?? '');

            $resultado = marcarPagoPagadoPorCliente($mysqli, $id_pago, $id_usuario, $numero_transaccion);

            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
            header('Location: perfil.php?tab=pedidos');
            exit;
            
        case 'cancelar_pedido_cliente':
            $id_pedido = intval($_POST['id_pedido'] ?? 0);

            if ($id_pedido <= 0) {
                $_SESSION['mensaje'] = 'ID de pedido inválido';
                $_SESSION['mensaje_tipo'] = 'danger';
                header('Location: perfil.php?tab=pedidos');
                exit;
            }

            try {
                $pedido = obtenerPedidoPorId($mysqli, $id_pedido);

                if (!$pedido || intval($pedido['id_usuario']) !== $id_usuario) {
                    $_SESSION['mensaje'] = 'No tienes permiso para cancelar este pedido';
                    $_SESSION['mensaje_tipo'] = 'danger';
                    header('Location: perfil.php?tab=pedidos');
                    exit;
                }

                $estado_pedido_actual = normalizarEstado($pedido['estado_pedido'] ?? '');
                $pago_pedido = obtenerPagoPorPedido($mysqli, $id_pedido);
                $estado_pago_actual = $pago_pedido ? normalizarEstado($pago_pedido['estado_pago'] ?? '') : '';

                if ($estado_pedido_actual !== 'pendiente') {
                    $_SESSION['mensaje'] = 'Solo se pueden cancelar pedidos en estado Pendiente';
                    $_SESSION['mensaje_tipo'] = 'warning';
                    header('Location: perfil.php?tab=pedidos');
                    exit;
                }

                if ($pago_pedido && $estado_pago_actual !== 'pendiente') {
                    $_SESSION['mensaje'] = 'Solo se pueden cancelar pedidos cuando el pago está en estado Pendiente';
                    $_SESSION['mensaje_tipo'] = 'warning';
                    header('Location: perfil.php?tab=pedidos');
                    exit;
                }

                if (!actualizarEstadoPedidoConValidaciones($mysqli, $id_pedido, 'cancelado', $id_usuario)) {
                    throw new Exception('Error al cancelar el pedido');
                }

                $_SESSION['mensaje'] = 'Pedido cancelado correctamente.';
                $_SESSION['mensaje_tipo'] = 'success';
                header('Location: perfil.php?tab=pedidos');
                exit;
                
            } catch (Exception $e) {
                // SEGURIDAD: Registrar error técnico en logs pero no exponerlo al usuario
                error_log("[CANCELAR PEDIDO EXCEPTION] " . $e->getMessage());
                if ($mysqli && $mysqli->error) {
                    error_log("MySQL Error: {$mysqli->error} (Errno: {$mysqli->errno})");
                }

                $_SESSION['mensaje'] = 'Error al cancelar el pedido. Por favor, inténtalo nuevamente.';
                $_SESSION['mensaje_tipo'] = 'danger';
                header('Location: perfil.php?tab=pedidos');
                exit;
            }
            break;
            
        case 'eliminar_cuenta':
            try {
                $resultado = procesarEliminacionCuenta($mysqli, $id_usuario, $_POST);

                if ($resultado['eliminado']) {
                    // Usar función centralizada para destruir sesión de manera segura
                    destruirSesionSegura('cuenta_eliminada.php');
                } else {
                    // Usar función centralizada para redirigir con mensaje
                    redirigirConMensaje(
                        construirRedirectUrl('perfil.php'),
                        $resultado['mensaje'],
                        $resultado['mensaje_tipo']
                    );
                }
            } catch (Exception $e) {
                // Log error para debugging en hosting
                error_log("Error en eliminación de cuenta. ID Usuario: {$id_usuario}. Error: " . $e->getMessage() . " | Archivo: " . $e->getFile() . " Línea: " . $e->getLine());

                // Usar función centralizada para redirigir con mensaje de error
                redirigirConMensaje(
                    construirRedirectUrl('perfil.php'),
                    'Error al procesar la eliminación de cuenta. Por favor, intenta nuevamente.',
                    'danger'
                );
            }
            break;
            
        default:
            // Acción no reconocida, no hacer nada
            break;
}
}

// Obtener datos del usuario (sin contraseña por seguridad)
$usuario = obtenerDatosUsuario($mysqli, $id_usuario);

if (!$usuario) {
    header('Location: logout.php');
    exit;
}

// Parsear dirección en componentes (calle, número, piso) usando función centralizada
$direccion_completa = $usuario['direccion'] ?? '';
$direccion_parseada = parsearDireccion($direccion_completa);

// Obtener pedidos del usuario (todos los roles)
$pedidos_usuario = [];
$pedidos_usuario = obtenerPedidosUsuario($mysqli, $id_usuario);
?>

<?php include 'includes/header.php'; ?>


    <main class="perfil-page">
        <div class="container">
            <div class="perfil-header">
                <h2><i class="fas fa-user-circle me-2"></i>Mi Perfil</h2>
                <p>Gestiona tu información personal y datos de envío</p>
            </div>
            
            <!-- Mensajes -->
            <?php if ($mensaje): ?>
            <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show animate-in" role="alert">
                <?php if ($mensaje_tipo === 'success'): ?>
                    <i class="fas fa-check-circle me-2"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle me-2"></i>
                <?php endif; ?>
                <?php 
                // Si el mensaje contiene HTML (como enlaces), no escapar, sino mostrar directamente
                // Solo escapar si no contiene tags HTML
                if (strip_tags($mensaje) !== $mensaje) {
                    echo $mensaje; // Contiene HTML, mostrar sin escapar
                } else {
                    echo htmlspecialchars($mensaje); // Solo texto, escapar para seguridad
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Sistema de Pestañas -->
            <ul class="nav nav-tabs mb-4" id="perfilTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="datos-tab" data-bs-toggle="tab" data-bs-target="#datos" type="button" role="tab">
                        <i class="fas fa-user me-2"></i>Mis Datos
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="envio-tab" data-bs-toggle="tab" data-bs-target="#envio" type="button" role="tab">
                        <i class="fas fa-map-marker-alt me-2"></i>Datos de Envío
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pedidos-tab" data-bs-toggle="tab" data-bs-target="#pedidos" type="button" role="tab">
                        <i class="fas fa-shopping-bag me-2"></i>Mis Pedidos
                        <?php if (count($pedidos_usuario) > 0): ?>
                            <span class="badge bg-primary ms-2"><?= count($pedidos_usuario) ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="contrasena-tab" data-bs-toggle="tab" data-bs-target="#contrasena" type="button" role="tab">
                        <i class="fas fa-key me-2"></i>Cambio Contraseña
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="perfilTabsContent">
                <!-- Pestaña: Mis Datos -->
                <div class="tab-pane fade show active" id="datos" role="tabpanel">
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
                                        <input type="text" class="form-control" id="apellido" name="apellido" value="<?= htmlspecialchars($usuario['apellido']) ?>" required minlength="2" maxlength="100" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]+" title="Letras, espacios, apóstrofe (') y acento agudo (´), entre 2 y 100 caracteres">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Correo Electrónico</label>
                                        <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($usuario['email']) ?>" readonly>
                                        <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Tu correo electrónico para iniciar sesión (no se puede modificar)</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="telefono" class="form-label">Teléfono</label>
                                        <input type="tel" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>" placeholder="Ej: +54 9 11 1234-5678" pattern="[+0-9\s\-()]+">
                                        <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Formato: código país + área + número</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="fecha_nacimiento" class="form-label">
                                            <i class="fas fa-birthday-cake me-1"></i>Fecha de Nacimiento
                                        </label>
                                        <input type="date" 
                                               class="form-control" 
                                               id="fecha_nacimiento" 
                                               name="fecha_nacimiento" 
                                               max="<?= date('Y-m-d', strtotime('-13 years')) ?>"
                                               min="1925-01-01"
                                               value="<?php 
                                                    if (!empty($usuario['fecha_nacimiento']) && $usuario['fecha_nacimiento'] !== null) {
                                                        // La fecha de BD está en formato YYYY-MM-DD, usar directamente
                                                        echo htmlspecialchars($usuario['fecha_nacimiento']);
                                                    } else {
                                                        echo '';
                                                    }
                                                ?>">
                                        <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Opcional: Útil para personalizar tu experiencia</small>
                                        <div id="fecha-nacimiento-feedback" class="invalid-feedback"></div>
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
                                
                                <?php if ($usuario['direccion']): ?>
                                    <div class="perfil-info-item">
                                        <strong><i class="fas fa-map-marker-alt me-2"></i>Dirección de Envío</strong>
                                        <span>
                                            <?php 
                                            // Mostrar dirección parseada formateada usando función centralizada
                                            $direccion_completa = $usuario['direccion'] ?? '';
                                            $dir_parseada = parsearDireccion($direccion_completa);
                                            $direccion_formateada = trim($dir_parseada['calle']);
                                            if (!empty($dir_parseada['numero'])) {
                                                $direccion_formateada .= ' ' . $dir_parseada['numero'];
                                            }
                                            if (!empty($dir_parseada['piso'])) {
                                                $direccion_formateada .= ' ' . $dir_parseada['piso'];
                                            }
                                            echo htmlspecialchars($direccion_formateada);
                                            ?><br>
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
                                
                                <?php if (!empty($usuario['fecha_nacimiento']) && $usuario['fecha_nacimiento'] !== null): ?>
                                    <div class="perfil-info-item">
                                        <strong><i class="fas fa-birthday-cake me-2"></i>Fecha de Nacimiento</strong>
                                        <span><?php 
                                            $fecha_ts = strtotime($usuario['fecha_nacimiento']);
                                            echo ($fecha_ts !== false) ? date('d/m/Y', $fecha_ts) : htmlspecialchars($usuario['fecha_nacimiento']);
                                        ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <hr class="my-4">
                                
                                <div class="d-grid gap-2">
                                    <?php 
                                    // Mostrar paneles según el rol del usuario
                                    // Los admins SOLO tienen acceso a su panel de administración
                                    ?>
                                    <?php if (isAdmin()): ?>
                                        <a href="admin.php" class="btn btn-secondary">
                                            <i class="fas fa-shield-alt me-2"></i>Panel de Administración
                                        </a>
                                    <?php else: ?>
                                        <?php if (isMarketing()): ?>
                                            <a href="marketing.php" class="btn btn-primary">
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
                                    <a href="logout.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pestaña: Datos de Envío -->
                <div class="tab-pane fade" id="envio" role="tabpanel">
                    <div class="row justify-content-center">
                        <div class="col-lg-8 col-md-10">
                            <div class="perfil-card">
                                <h4><i class="fas fa-map-marker-alt me-2"></i>Datos de Envío</h4>
                                <p class="text-muted mb-4">Completa tu dirección para agilizar tus compras</p>
                                
                                <form method="POST" action="" data-protect>
                                <!-- Dirección separada en campos -->
                                <div class="datos-envio-fields">
                                    <div class="row g-3">
                                        <!-- Dirección -->
                                        <div class="col-md-6">
                                            <label for="envio_direccion_calle" class="form-label datos-envio-label">
                                                <i class="fas fa-road me-2"></i>Dirección <span class="text-secondary">*</span>
                                            </label>
                                            <input type="text" 
                                                   class="form-control datos-envio-input" 
                                                   id="envio_direccion_calle" 
                                                   name="direccion_calle" 
                                                   value="<?= htmlspecialchars($direccion_parseada['calle']) ?>" 
                                                   placeholder="Ej: Av. Corrientes"
                                                   pattern="[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]+"
                                                   title="Solo se permiten letras (incluyendo acentos), números, espacios, guiones, apóstrofes y acentos graves"
                                                   required>
                                        </div>
                                        
                                        <!-- Número -->
                                        <div class="col-md-3">
                                            <label for="envio_direccion_numero" class="form-label datos-envio-label">
                                                <i class="fas fa-hashtag me-2"></i>Número <span class="text-secondary">*</span>
                                            </label>
                                            <input type="text" 
                                                   class="form-control datos-envio-input" 
                                                   id="envio_direccion_numero" 
                                                   name="direccion_numero" 
                                                   value="<?= htmlspecialchars($direccion_parseada['numero']) ?>" 
                                                   placeholder="Ej: 1234"
                                                   pattern="[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]+"
                                                   title="Solo se permiten letras (incluyendo acentos), números, espacios, guiones, apóstrofes y acentos graves"
                                                   required>
                                        </div>
                                        
                                        <!-- Piso / Departamento -->
                                        <div class="col-md-3">
                                            <label for="envio_direccion_piso" class="form-label datos-envio-label">
                                                <i class="fas fa-building me-2"></i>Piso / Depto.
                                            </label>
                                            <input type="text" 
                                                   class="form-control datos-envio-input" 
                                                   id="envio_direccion_piso" 
                                                   name="direccion_piso" 
                                                   value="<?= htmlspecialchars($direccion_parseada['piso']) ?>" 
                                                   placeholder="Ej: 2° A (Opcional)"
                                                   pattern="[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]+"
                                                   title="Solo se permiten letras (incluyendo acentos), números, espacios, guiones, apóstrofes y acentos graves">
                                        </div>
                                    </div>
                                    
                                    <!-- Provincia, Localidad y Código Postal -->
                                    <div class="row g-3 mt-2">
                                        <!-- Provincia -->
                                        <div class="col-md-4">
                                            <label for="envio_provincia" class="form-label datos-envio-label">
                                                <i class="fas fa-map me-2"></i>Provincia <span class="text-secondary">*</span>
                                            </label>
                                            <select class="form-select datos-envio-input" id="envio_provincia" name="provincia" required>
                                                <option value="">Seleccionar provincia</option>
                                                <option value="Buenos Aires" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Buenos Aires') ? 'selected' : ''; ?>>Buenos Aires</option>
                                                <option value="CABA" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'CABA') ? 'selected' : ''; ?>>CABA (Ciudad Autónoma de Buenos Aires)</option>
                                                <option value="Catamarca" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Catamarca') ? 'selected' : ''; ?>>Catamarca</option>
                                                <option value="Chaco" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Chaco') ? 'selected' : ''; ?>>Chaco</option>
                                                <option value="Chubut" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Chubut') ? 'selected' : ''; ?>>Chubut</option>
                                                <option value="Córdoba" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Córdoba') ? 'selected' : ''; ?>>Córdoba</option>
                                                <option value="Corrientes" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Corrientes') ? 'selected' : ''; ?>>Corrientes</option>
                                                <option value="Entre Ríos" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Entre Ríos') ? 'selected' : ''; ?>>Entre Ríos</option>
                                                <option value="Formosa" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Formosa') ? 'selected' : ''; ?>>Formosa</option>
                                                <option value="Jujuy" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Jujuy') ? 'selected' : ''; ?>>Jujuy</option>
                                                <option value="La Pampa" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'La Pampa') ? 'selected' : ''; ?>>La Pampa</option>
                                                <option value="La Rioja" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'La Rioja') ? 'selected' : ''; ?>>La Rioja</option>
                                                <option value="Mendoza" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Mendoza') ? 'selected' : ''; ?>>Mendoza</option>
                                                <option value="Misiones" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Misiones') ? 'selected' : ''; ?>>Misiones</option>
                                                <option value="Neuquén" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Neuquén') ? 'selected' : ''; ?>>Neuquén</option>
                                                <option value="Río Negro" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Río Negro') ? 'selected' : ''; ?>>Río Negro</option>
                                                <option value="Salta" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Salta') ? 'selected' : ''; ?>>Salta</option>
                                                <option value="San Juan" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'San Juan') ? 'selected' : ''; ?>>San Juan</option>
                                                <option value="San Luis" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'San Luis') ? 'selected' : ''; ?>>San Luis</option>
                                                <option value="Santa Cruz" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Santa Cruz') ? 'selected' : ''; ?>>Santa Cruz</option>
                                                <option value="Santa Fe" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Santa Fe') ? 'selected' : ''; ?>>Santa Fe</option>
                                                <option value="Santiago del Estero" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Santiago del Estero') ? 'selected' : ''; ?>>Santiago del Estero</option>
                                                <option value="Tierra del Fuego" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Tierra del Fuego') ? 'selected' : ''; ?>>Tierra del Fuego</option>
                                                <option value="Tucumán" <?php echo (isset($usuario['provincia']) && $usuario['provincia'] === 'Tucumán') ? 'selected' : ''; ?>>Tucumán</option>
                                                <?php 
                                                // Agregar provincia personalizada si no está en la lista estándar
                                                if (!empty($usuario['provincia'])) {
                                                    $provincias_lista = ['Buenos Aires', 'CABA', 'Catamarca', 'Chaco', 'Chubut', 'Córdoba', 'Corrientes', 'Entre Ríos', 'Formosa', 'Jujuy', 'La Pampa', 'La Rioja', 'Mendoza', 'Misiones', 'Neuquén', 'Río Negro', 'Salta', 'San Juan', 'San Luis', 'Santa Cruz', 'Santa Fe', 'Santiago del Estero', 'Tierra del Fuego', 'Tucumán'];
                                                    if (!in_array($usuario['provincia'], $provincias_lista)) {
                                                        echo '<option value="' . htmlspecialchars($usuario['provincia']) . '" selected>' . htmlspecialchars($usuario['provincia']) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        
                                        <!-- Localidad -->
                                        <div class="col-md-4">
                                            <label for="envio_localidad" class="form-label datos-envio-label">
                                                <i class="fas fa-city me-2"></i>Localidad <span class="text-secondary">*</span>
                                            </label>
                                            <input type="text" 
                                                   class="form-control datos-envio-input" 
                                                   id="envio_localidad" 
                                                   name="localidad" 
                                                   value="<?= htmlspecialchars($usuario['localidad'] ?? '') ?>" 
                                                   placeholder="Ej: Buenos Aires"
                                                   pattern="[A-Za-z0-9 ]+"
                                                   title="Solo se permiten letras, números y espacios"
                                                   required>
                                        </div>
                                        
                                        <!-- Código Postal -->
                                        <div class="col-md-4">
                                            <label for="envio_codigo_postal" class="form-label datos-envio-label">
                                                <i class="fas fa-mail-bulk me-2"></i>Código Postal <span class="text-secondary">*</span>
                                            </label>
                                            <input type="text" 
                                                   class="form-control datos-envio-input" 
                                                   id="envio_codigo_postal" 
                                                   name="codigo_postal" 
                                                   value="<?= htmlspecialchars($usuario['codigo_postal'] ?? '') ?>" 
                                                   pattern="[A-Za-z0-9 ]+" 
                                                   title="Solo se permiten letras, números y espacios"
                                                   placeholder="Ej: 1234 o C1234ABC"
                                                   required>
                                            <small class="form-text text-muted mt-1">
                                                <i class="fas fa-info-circle me-1"></i>Permite números y letras
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit" name="actualizar_datos" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Guardar Cambios
                                    </button>
                                </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pestaña: Mis Pedidos -->
                <div class="tab-pane fade" id="pedidos" role="tabpanel">
                    <div class="row justify-content-center">
                        <div class="col-lg-10 col-md-12">
                            <div class="perfil-card">
                                <h4><i class="fas fa-shopping-bag me-2"></i>Mis Pedidos</h4>
                                <p class="text-muted mb-4">Consulta el estado de todos tus pedidos realizados</p>
                                
                                <?php if (empty($pedidos_usuario)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>No tienes pedidos aún</strong><br>
                                    Cuando realices tu primera compra, aparecerá aquí.
                                </div>
                            <?php else: ?>
                                <?php
                                // Mapeos de estados ahora se obtienen desde funciones centralizadas
                                // Se convierten al formato necesario para este contexto
                                ?>
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col">ID Pedido</th>
                                                <th scope="col">Fecha</th>
                                                <th scope="col">Estado</th>
                                                <th scope="col">Pago</th>
                                                <th scope="col" class="text-end">Total</th>
                                                <th scope="col">Productos</th>
                                                <th scope="col">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pedidos_usuario as $pedido): ?>
                                            <?php
                                            // Obtener información del pedido y pago
                                            $detalles_pedido = obtenerDetallesPedido($mysqli, $pedido['id_pedido']);
                                            $pago_pedido = obtenerPagoPorPedido($mysqli, $pedido['id_pedido']);
                                            
                                            // Normalizar estado del pedido usando función centralizada
                                            $estado_pedido = normalizarEstado($pedido['estado_pedido'] ?? '');
                                            
                                            // Validar si se puede cancelar el pedido
                                            // Lógica de negocio: Cliente puede cancelar un Pedido sólo en estado Pendiente
                                            // Requisitos: Tanto el estado del pedido como el estado del pago deben ser "Pendiente"
                                            $estado_pago = $pago_pedido ? normalizarEstado($pago_pedido['estado_pago'] ?? '') : '';
                                            $puede_cancelar_pedido = ($estado_pedido === 'pendiente') 
                                                && ($estado_pago === 'pendiente' || !$pago_pedido);
            
                                            // Validar si se puede marcar el pago como pagado (solo pagos pendientes)
                                            $puede_marcar_pago = $pago_pedido && normalizarEstado($pago_pedido['estado_pago'] ?? '') === 'pendiente';
                                            
                                            
                                            // Determinar si hay acciones disponibles
                                            $tiene_acciones_disponibles = $puede_marcar_pago || $puede_cancelar_pedido;
                                            
                                            // Contar productos del pedido
                                            $cantidad_productos = 0;
                                            foreach ($detalles_pedido as $detalle) {
                                                $cantidad_productos += $detalle['cantidad'];
                                            }
                                            
                                            // Obtener información de estado del pedido usando función centralizada
                                            // Convertir al formato necesario para este contexto (clase/texto)
                                            $info_estado_pedido_raw = obtenerInfoEstadoPedido($estado_pedido);
                                            $info_estado_pedido = [
                                                'clase' => 'bg-' . $info_estado_pedido_raw['color'],
                                                'texto' => $info_estado_pedido_raw['nombre']
                                            ];
                                            
                                            // Obtener información de estado del pago usando función centralizada (si existe pago)
                                            $info_estado_pago = $pago_pedido ? obtenerInfoEstadoPago($pago_pedido['estado_pago'] ?? '') : null;
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong>#<?= htmlspecialchars($pedido['id_pedido']) ?></strong>
                                                </td>
                                                <td>
                                                    <i class="fas fa-calendar-alt me-2 text-muted"></i>
                                                    <?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    // Detectar inconsistencia: pedido completado/en_viaje con pago rechazado/cancelado
                                                    $estado_pago_actual = $pago_pedido ? strtolower(trim($pago_pedido['estado_pago'] ?? '')) : '';
                                                    $hay_inconsistencia = in_array($estado_pedido, ['completado', 'en_viaje']) 
                                                                          && in_array($estado_pago_actual, ['rechazado', 'cancelado']);
                                                    ?>
                                                    <span class="badge <?= htmlspecialchars($info_estado_pedido['clase']) ?>">
                                                        <?= htmlspecialchars($info_estado_pedido['texto']) ?>
                                                    </span>
                                                    <?php if ($hay_inconsistencia): ?>
                                                        <br><small class="text-secondary">
                                                            <i class="fas fa-exclamation-triangle"></i> Inconsistencia detectada
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($pago_pedido): ?>
                                                        <span class="badge bg-<?= htmlspecialchars($info_estado_pago['color']) ?>">
                                                            <?= htmlspecialchars($info_estado_pago['nombre']) ?>
                                                        </span>
                                                        <?php 
                                                        // Mostrar advertencias y código de transacción si existen
                                                        if ($hay_inconsistencia) {
                                                            echo '<br><small class="text-secondary"><i class="fas fa-exclamation-triangle"></i> Revisar estado</small>';
                                                        }
                                                        if (!empty($pago_pedido['numero_transaccion'])) {
                                                            echo '<br><small class="text-muted">Código: ' . htmlspecialchars($pago_pedido['numero_transaccion']) . '</small>';
                                                        }
                                                        ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Pendiente</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <strong>$<?= number_format($pedido['total_pedido'], 2, ',', '.') ?></strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= count($detalles_pedido) ?> producto(s)<br>
                                                        <?= $cantidad_productos ?> unidad(es)
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <!-- Botón Ver Pedido (siempre disponible) -->
                                                        <button type="button" 
                                                                class="btn btn-outline-primary btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#verPedidoModal<?= htmlspecialchars($pedido['id_pedido'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fas fa-eye me-1"></i>Ver Pedido
                                                        </button>
                                                        
                                                        <!-- Botón Marcar Pago (solo si el pago está pendiente) -->
                                                        <?php if ($puede_marcar_pago): ?>
                                                        <?php renderFormularioMarcarPago($pago_pedido); ?>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Botón Cancelar Pedido (solo si pedido y pago están en estado Pendiente) -->
                                                        <?php if ($puede_cancelar_pedido): ?>
                                                        <?php renderFormularioCancelarPedido($pedido, $pago_pedido); ?>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Botón Reclamo/Consulta (solo si no hay más acciones disponibles) -->
                                                        <?php if (!$tiene_acciones_disponibles): ?>
                                                        <a href="index.php?asunto=problema_pedido&pedido=<?= htmlspecialchars($pedido['id_pedido'], ENT_QUOTES, 'UTF-8') ?>#contacto" 
                                                           class="btn btn-outline-info btn-sm">
                                                            <i class="fas fa-comment-dots me-1"></i>Reclamo/Consulta
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Modal para ver detalles del pedido -->
                                                    <?php renderModalVerPedido($mysqli, $pedido); ?>
                                                    
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Total de pedidos: <strong><?= count($pedidos_usuario) ?></strong>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pestaña: Cambio Contraseña -->
                <div class="tab-pane fade" id="contrasena" role="tabpanel">
                    <div class="row justify-content-center">
                        <div class="col-lg-8 col-md-10">
                            <div class="perfil-card">
                                <h4><i class="fas fa-key me-2"></i>Cambio de Contraseña y Recupero</h4>
                                <p class="text-muted mb-4">Gestiona tu contraseña y configura tu pregunta de recupero</p>
                                
                                <!-- Pregunta y Respuesta de Recupero -->
                                <div class="mb-4">
                                    <form method="POST" action="" data-protect>
                                        <div class="mb-3">
                                            <label for="pregunta_recupero" class="form-label">
                                                <i class="fas fa-question-circle me-1"></i>Pregunta de Recupero
                                            </label>
                                            <select class="form-select" name="pregunta_recupero" id="pregunta_recupero">
                                                <option value="">Selecciona una pregunta (opcional)</option>
                                                <?php foreach ($preguntas_recupero as $pregunta): ?>
                                                    <option value="<?= $pregunta['id_pregunta'] ?>" 
                                                            <?= (isset($usuario['pregunta_recupero']) && !empty($usuario['pregunta_recupero']) && intval($usuario['pregunta_recupero']) == intval($pregunta['id_pregunta'])) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($pregunta['texto_pregunta']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Opcional: Para recuperar tu cuenta si olvidas la contraseña</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="respuesta_recupero" class="form-label">
                                                <i class="fas fa-key me-1"></i>Respuesta de Recupero
                                            </label>
                                            <div class="password-input-wrapper">
                                                <input type="password"
                                                       class="form-control"
                                                       id="respuesta_recupero"
                                                       name="respuesta_recupero"
                                                       value="<?php
                                                           // No mostrar el hash, solo mostrar si es texto plano (menos de 20 caracteres)
                                                           $respuesta = $usuario['respuesta_recupero'] ?? '';
                                                           echo (strlen($respuesta) > 20) ? '' : htmlspecialchars($respuesta);
                                                       ?>"
                                                       minlength="4"
                                                       maxlength="20"
                                                       pattern="[a-zA-Z0-9 ]+"
                                                       title="Letras, números y espacios, mínimo 4 caracteres, máximo 20 caracteres"
                                                       autocomplete="off">
                                                <button type="button" class="btn-toggle-password" data-input-id="respuesta_recupero" aria-label="Mostrar respuesta">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Letras, números y espacios, entre 4 y 20 caracteres</small>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button type="submit" name="actualizar_recupero" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Guardar Pregunta de Recupero
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                
                                <hr class="my-4">
                                
                                <!-- Cambio de Contraseña -->
                                <div class="mt-4">
                                    <h5 class="mb-3"><i class="fas fa-lock me-2"></i>Cambiar Contraseña</h5>
                                    
                                    <form method="POST" action="" id="formCambiarContrasena">
                                        <div class="mb-3">
                                            <label for="contrasena_actual" class="form-label">
                                                <i class="fas fa-lock me-1"></i>Contraseña Actual
                                            </label>
                                            <div class="password-input-wrapper">
                                                <input type="password" 
                                                       class="form-control" 
                                                       id="contrasena_actual" 
                                                       name="contrasena_actual" 
                                                       required
                                                       autocomplete="current-password">
                                                <button type="button" class="btn-toggle-password" data-input-id="contrasena_actual" aria-label="Mostrar contraseña">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="nueva_contrasena" class="form-label">
                                                <i class="fas fa-key me-1"></i>Nueva Contraseña
                                            </label>
                                            <div class="password-input-wrapper">
                                                <input type="password"
                                                       class="form-control"
                                                       id="nueva_contrasena"
                                                       name="nueva_contrasena"
                                                       required
                                                       minlength="6"
                                                       maxlength="20"
                                                       autocomplete="new-password"
                                                       title="Mínimo 6 caracteres, máximo 20 caracteres">
                                                <button type="button" class="btn-toggle-password" data-input-id="nueva_contrasena" aria-label="Mostrar contraseña">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>Mínimo 6 caracteres, máximo 20 caracteres
                                            </small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="confirmar_contrasena" class="form-label">
                                                <i class="fas fa-key me-1"></i>Confirmar Nueva Contraseña
                                            </label>
                                            <div class="password-input-wrapper">
                                                <input type="password"
                                                       class="form-control"
                                                       id="confirmar_contrasena"
                                                       name="confirmar_contrasena"
                                                       required
                                                       minlength="6"
                                                       maxlength="20"
                                                       autocomplete="new-password">
                                                <button type="button" class="btn-toggle-password" data-input-id="confirmar_contrasena" aria-label="Mostrar contraseña">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <div id="password-match" class="mt-2"></div>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button type="submit" name="cambiar_contrasena" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Cambiar Contraseña
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Tips de Seguridad -->
                                <div class="alert alert-info mt-4">
                                    <h6 class="mb-2"><i class="fas fa-shield-alt me-2"></i>Consejos de Seguridad</h6>
                                    <ul class="list-unstyled small mb-0">
                                        <li class="mb-1"><i class="fas fa-check text-success me-2"></i>Nunca compartas tu contraseña</li>
                                        <li class="mb-1"><i class="fas fa-check text-success me-2"></i>Cierra sesión en dispositivos compartidos</li>
                                        <li class="mb-1"><i class="fas fa-check text-success me-2"></i>Cambia tu contraseña periódicamente</li>
                                        <li class="mb-1"><i class="fas fa-check text-success me-2"></i>Configura tu pregunta de recupero para mayor seguridad</li>
                                    </ul>
                                </div>
                                
                                <hr class="my-4">
                                
                                <!-- Sección Eliminar Cuenta -->
                                <div class="mt-4">
                                    <h5 class="mb-3 text-secondary"><i class="fas fa-exclamation-triangle me-2"></i>Eliminar Cuenta</h5>
                                    <div class="alert alert-secondary">
                                        <p class="mb-2"><strong>ESTE PROCESO NO TIENE VUELTA ATRAS</strong></p>
                                        <p class="mb-0 small">Al eliminar tu cuenta, esta será desactivada durante 30 días. Pasado ese período, será eliminada permanentemente. Podrás reactivarla iniciando sesión dentro de los 30 días.</p>
                                    </div>
                                    <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#modalEliminarCuenta">
                                        <i class="fas fa-trash-alt me-2"></i>BORRAR CUENTA
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Eliminar Cuenta -->
                <?php renderModalEliminarCuenta(); ?>
                
            </div>
        </div>
    </main>

<?php include 'includes/footer.php'; render_footer(); ?>
