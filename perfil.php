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

// Cargar funciones de contraseñas
require_once 'includes/password_functions.php';

// Cargar funciones de perfil
require_once 'includes/perfil_functions.php';

// Cargar componentes reutilizables de perfil
require_once 'includes/perfil_components.php';

// Conectar a la base de datos
require_once __DIR__ . '/config/database.php';

// Cargar queries de perfil
require_once 'includes/queries/usuario_queries.php'; // Necesario para verificarHashContrasena()
require_once 'includes/queries/perfil_queries.php';
require_once 'includes/queries/pedido_queries.php';
require_once 'includes/queries/stock_queries.php';
require_once 'includes/queries/pago_queries.php';
require_once 'includes/sales_functions.php';

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
    // Procesar actualización de pregunta/respuesta de recupero
    if (isset($_POST['actualizar_recupero'])) {
        $resultado = procesarActualizacionRecupero($mysqli, $id_usuario, $_POST);
        // Guardar mensaje en sesión y redirigir (patrón Post-Redirect-Get)
        $_SESSION['mensaje'] = $resultado['mensaje'];
        $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
        header('Location: perfil.php');
        exit;
    }
    // Procesar actualización de datos personales
    elseif (isset($_POST['actualizar_datos'])) {
        $resultado = procesarActualizacionDatos($mysqli, $id_usuario, $_POST);
        // Actualizar sesión si los datos se actualizaron correctamente
        if (!empty($resultado['datos_actualizados'])) {
            $_SESSION['nombre'] = $resultado['datos_actualizados']['nombre'];
            $_SESSION['apellido'] = $resultado['datos_actualizados']['apellido'];
            $_SESSION['email'] = $resultado['datos_actualizados']['email'];
        }
        // Guardar mensaje en sesión y redirigir (patrón Post-Redirect-Get)
        $_SESSION['mensaje'] = $resultado['mensaje'];
        $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
        header('Location: perfil.php');
        exit;
    }
    // Procesar cambio de contraseña
    elseif (isset($_POST['cambiar_contrasena'])) {
        $resultado = procesarCambioContrasena($mysqli, $id_usuario, $_POST);
        // Guardar mensaje en sesión y redirigir (patrón Post-Redirect-Get)
        $_SESSION['mensaje'] = $resultado['mensaje'];
        $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
        header('Location: perfil.php');
        exit;
    }
    // Procesar marcar pago como pagado
    elseif (isset($_POST['marcar_pago_pagado'])) {
        require_once __DIR__ . '/includes/queries/pago_queries.php';
        require_once __DIR__ . '/includes/queries/pedido_queries.php';
        require_once __DIR__ . '/includes/queries/stock_queries.php';
        
        $id_pago = intval($_POST['id_pago'] ?? 0);
        $numero_transaccion = trim($_POST['numero_transaccion'] ?? '');
        
        if ($id_pago <= 0) {
            $_SESSION['mensaje'] = 'ID de pago inválido';
            $_SESSION['mensaje_tipo'] = 'danger';
            header('Location: perfil.php');
            exit;
        } else {
            // Obtener información del pago
            $pago = obtenerPagoPorId($mysqli, $id_pago);
            
            if (!$pago) {
                $_SESSION['mensaje'] = 'Pago no encontrado';
                $_SESSION['mensaje_tipo'] = 'danger';
                header('Location: perfil.php');
                exit;
            } else {
                // Verificar que el pedido pertenece al usuario actual
                $pedido = obtenerPedidoPorId($mysqli, $pago['id_pedido']);
                
                if (!$pedido || intval($pedido['id_usuario']) !== $id_usuario) {
                    $_SESSION['mensaje'] = 'No tienes permiso para modificar este pago';
                    $_SESSION['mensaje_tipo'] = 'danger';
                    header('Location: perfil.php');
                    exit;
                } elseif ($pago['estado_pago'] !== 'pendiente') {
                    $_SESSION['mensaje'] = 'Solo se pueden marcar como pagados los pagos pendientes';
                    $_SESSION['mensaje_tipo'] = 'warning';
                    header('Location: perfil.php');
                    exit;
                } else {
                    // Actualizar pago completo con numero_transaccion y marcar como pendiente_aprobacion
                    // NO se descuenta stock aquí, solo cuando ventas apruebe el pago
                    $monto = floatval($pago['monto']);
                    
                    // Modo debug: activar para mostrar información detallada de errores
                    $debug_mode = (ini_get('display_errors') == 1);
                    
                    // Loggear información antes de procesar (para debugging)
                    error_log("=== INICIO PROCESAR PAGO ===");
                    error_log("ID Pago: {$id_pago}");
                    error_log("ID Usuario: {$id_usuario}");
                    error_log("ID Pedido: {$pago['id_pedido']}");
                    error_log("Estado Pago Actual: {$pago['estado_pago']}");
                    error_log("Monto: {$monto}");
                    error_log("Número Transacción: " . ($numero_transaccion ?: '(vacío)'));
                    error_log("Estado Pedido: " . ($pedido['estado_pedido'] ?? 'N/A'));
                    
                    // Verificar estado de conexión a BD
                    if ($mysqli && $mysqli->connect_error) {
                        error_log("ERROR: Conexión BD fallida: " . $mysqli->connect_error);
                    } else {
                        error_log("Conexión BD: OK");
                    }
                    
                    try {
                        // Normalizar numero_transaccion: convertir string vacío a null
                        $numero_transaccion_normalizado = (!empty($numero_transaccion)) ? $numero_transaccion : null;
                        
                        error_log("Llamando a actualizarPagoCompleto() con parámetros:");
                        error_log("  - id_pago: {$id_pago}");
                        error_log("  - estado_pago: 'pendiente_aprobacion'");
                        error_log("  - monto: {$monto}");
                        error_log("  - numero_transaccion: " . ($numero_transaccion_normalizado ?: 'null'));
                        
                        if (actualizarPagoCompleto($mysqli, $id_pago, 'pendiente_aprobacion', $monto, $numero_transaccion_normalizado)) {
                            error_log("=== PAGO MARCADO COMO PENDIENTE_APROBACION EXITOSAMENTE ===");
                            $_SESSION['mensaje'] = 'Pago marcado como pagado correctamente. Está pendiente de aprobación por el equipo de ventas.';
                            $_SESSION['mensaje_tipo'] = 'success';
                            header('Location: perfil.php');
                            exit;
                        } else {
                            // Este caso no debería ocurrir ya que la función lanza excepciones, pero lo mantenemos por seguridad
                            error_log("ERROR: actualizarPagoCompleto() retornó false sin lanzar excepción");
                            error_log("MySQL Error: " . ($mysqli->error ?: 'N/A'));
                            error_log("MySQL Errno: " . ($mysqli->errno ?: 'N/A'));
                            $_SESSION['mensaje'] = 'Error al marcar el pago como pagado. Por favor, comunícate con nosotros para más información.';
                            $_SESSION['mensaje_tipo'] = 'danger';
                            header('Location: perfil.php');
                            exit;
                        }
                    } catch (Exception $e) {
                        $error_message = $e->getMessage();
                        $error_file = $e->getFile();
                        $error_line = $e->getLine();
                        $error_trace = $e->getTraceAsString();
                        
                        // Loggear el error completo para debugging
                        error_log("=== ERROR AL MARCAR PAGO ===");
                        error_log("ID Pago: {$id_pago}");
                        error_log("ID Usuario: {$id_usuario}");
                        error_log("ID Pedido: {$pago['id_pedido']}");
                        error_log("Mensaje Error: " . $error_message);
                        error_log("Archivo: {$error_file}");
                        error_log("Línea: {$error_line}");
                        error_log("Stack Trace:");
                        error_log($error_trace);
                        
                        // Capturar errores específicos de MySQL
                        if ($mysqli) {
                            error_log("MySQL Error: " . ($mysqli->error ?: 'N/A'));
                            error_log("MySQL Errno: " . ($mysqli->errno ?: 'N/A'));
                        }
                        
                        error_log("=== FIN ERROR ===");
                        
                        // Construir mensaje de error según el tipo
                        $mensaje_usuario = '';
                        $tipo_mensaje = 'danger';
                        
                        // Verificar si el error es por stock insuficiente
                        if (strpos($error_message, 'STOCK_INSUFICIENTE') !== false) {
                            $mensaje_usuario = 'No hay stock disponible para completar este pedido. Por favor, <a href="index.php#contacto" class="alert-link">comunícate con nosotros</a> para más información.';
                            $tipo_mensaje = 'warning';
                        }
                        // Verificar si ya existe otro pago aprobado
                        elseif (strpos($error_message, 'Ya existe otro pago aprobado') !== false) {
                            $mensaje_usuario = 'Ya existe un pago aprobado para este pedido. No se puede aprobar otro pago.';
                            $tipo_mensaje = 'warning';
                        }
                        // Verificar si el monto es inválido
                        elseif (strpos($error_message, 'monto menor o igual a cero') !== false) {
                            $mensaje_usuario = 'El monto del pago no es válido. Por favor, comunícate con nosotros para más información.';
                            $tipo_mensaje = 'danger';
                        }
                        // Verificar si el pago no fue encontrado
                        elseif (strpos($error_message, 'Pago no encontrado') !== false) {
                            $mensaje_usuario = 'El pago no fue encontrado en el sistema. Por favor, recarga la página e intenta nuevamente.';
                            $tipo_mensaje = 'danger';
                        }
                        // Verificar si el estado es inválido
                        elseif (strpos($error_message, 'Estado de pago inválido') !== false) {
                            $mensaje_usuario = 'El estado del pago no es válido. Por favor, comunícate con nosotros para más información.';
                            $tipo_mensaje = 'danger';
                        }
                        // Verificar errores de base de datos
                        elseif (strpos($error_message, 'Error al preparar') !== false || 
                                strpos($error_message, 'Error al ejecutar') !== false ||
                                strpos($error_message, 'Error al actualizar') !== false ||
                                strpos($error_message, 'Error al obtener') !== false) {
                            $mensaje_usuario = 'Error de base de datos al procesar el pago. Por favor, <a href="index.php#contacto" class="alert-link">comunícate con nosotros</a> para más información.';
                            $tipo_mensaje = 'danger';
                        }
                        // Otros errores genéricos
                        else {
                            $mensaje_usuario = 'Error al procesar el pago. Por favor, <a href="index.php#contacto" class="alert-link">comunícate con nosotros</a> para más información.';
                            $tipo_mensaje = 'danger';
                        }
                        
                        // Si está en modo debug, agregar información detallada al mensaje
                        if ($debug_mode) {
                            $mensaje_usuario .= "<br><br><strong>Información de Debug:</strong><br>";
                            $mensaje_usuario .= "<strong>Error:</strong> " . htmlspecialchars($error_message) . "<br>";
                            $mensaje_usuario .= "<strong>Archivo:</strong> " . htmlspecialchars($error_file) . "<br>";
                            $mensaje_usuario .= "<strong>Línea:</strong> {$error_line}<br>";
                            if ($mysqli) {
                                $mensaje_usuario .= "<strong>MySQL Error:</strong> " . htmlspecialchars($mysqli->error ?: 'N/A') . "<br>";
                                $mensaje_usuario .= "<strong>MySQL Errno:</strong> " . ($mysqli->errno ?: 'N/A') . "<br>";
                            }
                            $mensaje_usuario .= "<strong>Stack Trace:</strong><pre style='font-size: 0.85rem; max-height: 200px; overflow-y: auto;'>" . htmlspecialchars($error_trace) . "</pre>";
                        }
                        
                        $_SESSION['mensaje'] = $mensaje_usuario;
                        $_SESSION['mensaje_tipo'] = $tipo_mensaje;
                        
                        // Redirigir después de procesar cualquier error
                        header('Location: perfil.php');
                        exit;
                    }
                }
            }
        }
    }
    // Procesar cancelación de pedido
    elseif (isset($_POST['cancelar_pedido_cliente'])) {
        require_once __DIR__ . '/includes/queries/pedido_queries.php';
        require_once __DIR__ . '/includes/queries/pago_queries.php';
        require_once __DIR__ . '/includes/queries/stock_queries.php';
        
        $id_pedido = intval($_POST['id_pedido'] ?? 0);
        
        if ($id_pedido <= 0) {
            $_SESSION['mensaje'] = 'ID de pedido inválido';
            $_SESSION['mensaje_tipo'] = 'danger';
            header('Location: perfil.php');
            exit;
        } else {
            // Verificar que el pedido pertenece al usuario actual
            $pedido = obtenerPedidoPorId($mysqli, $id_pedido);
            
            if (!$pedido || intval($pedido['id_usuario']) !== $id_usuario) {
                $_SESSION['mensaje'] = 'No tienes permiso para cancelar este pedido';
                $_SESSION['mensaje_tipo'] = 'danger';
                header('Location: perfil.php');
                exit;
            } else {
                $estado_actual = trim(strtolower($pedido['estado_pedido'] ?? ''));
                
                // Solo permitir cancelar si está en pendiente o preparacion
                if (!in_array($estado_actual, ['pendiente', 'preparacion'])) {
                    $_SESSION['mensaje'] = 'Solo se pueden cancelar pedidos en estado pendiente o preparación';
                    $_SESSION['mensaje_tipo'] = 'warning';
                    header('Location: perfil.php');
                    exit;
                } else {
                    try {
                        // Usar actualizarEstadoPedidoConValidaciones() para validaciones centralizadas
                        // según las reglas de negocio del plan
                        require_once __DIR__ . '/includes/queries/pedido_queries.php';
                        if (!actualizarEstadoPedidoConValidaciones($mysqli, $id_pedido, 'cancelado', $id_usuario)) {
                            throw new Exception('Error al cancelar el pedido');
                        }
                        
                        $_SESSION['mensaje'] = 'Pedido cancelado correctamente. El stock ha sido restaurado si era necesario.';
                        $_SESSION['mensaje_tipo'] = 'success';
                        header('Location: perfil.php');
                        exit;
                    } catch (Exception $e) {
                        $_SESSION['mensaje'] = 'Error al cancelar el pedido: ' . $e->getMessage();
                        $_SESSION['mensaje_tipo'] = 'danger';
                        header('Location: perfil.php');
                        exit;
                    }
                }
            }
        }
    }
    // Procesar eliminación de cuenta
    elseif (isset($_POST['eliminar_cuenta'])) {
        try {
            $resultado = procesarEliminacionCuenta($mysqli, $id_usuario, $_POST);
            
            if ($resultado['eliminado']) {
                // Limpiar todas las variables de sesión ANTES de destruir
                $_SESSION = array();
                
                // Destruir la sesión (debe hacerse mientras la sesión está abierta)
                session_destroy();
                
                // Cerrar sesión después de destruir
                session_write_close();
                
                // Destruir la cookie de sesión si existe
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(
                        session_name(), 
                        '', 
                        time() - 42000,
                        $params["path"], 
                        $params["domain"],
                        $params["secure"], 
                        $params["httponly"]
                    );
                }
                
                // Limpiar cualquier buffer de salida para asegurar redirección limpia
                // Limpiar todos los niveles de buffer
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                // Verificar que los headers no se hayan enviado
                if (!headers_sent()) {
                    // Redirigir a página de confirmación
                    header('Location: cuenta_eliminada.php', true, 302);
                    exit;
                } else {
                    // Si los headers ya se enviaron, usar JavaScript para redirigir
                    echo '<script>window.location.href = "cuenta_eliminada.php";</script>';
                    exit;
                }
            } else {
                // Guardar mensaje en sesión y redirigir (patrón Post-Redirect-Get)
                // IMPORTANTE: Hacer esto ANTES de cualquier output
                $_SESSION['mensaje'] = $resultado['mensaje'];
                $_SESSION['mensaje_tipo'] = $resultado['mensaje_tipo'];
                
                // Limpiar buffer antes de redirigir
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                if (!headers_sent()) {
                    header('Location: perfil.php', true, 302);
                    exit;
                } else {
                    echo '<script>window.location.href = "perfil.php";</script>';
                    exit;
                }
            }
        } catch (Exception $e) {
            // Log error para debugging en hosting
            error_log("Error en eliminación de cuenta. ID Usuario: {$id_usuario}. Error: " . $e->getMessage());
            error_log("Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
            
            // Guardar mensaje de error en sesión
            $_SESSION['mensaje'] = 'Error al procesar la eliminación de cuenta. Por favor, intenta nuevamente.';
            $_SESSION['mensaje_tipo'] = 'danger';
            
            // Limpiar buffer antes de redirigir
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            if (!headers_sent()) {
                header('Location: perfil.php', true, 302);
                exit;
            } else {
                echo '<script>window.location.href = "perfil.php";</script>';
                exit;
            }
        }
    }
}

// Obtener datos del usuario (sin contraseña por seguridad)
$usuario = obtenerDatosUsuario($mysqli, $id_usuario);

if (!$usuario) {
    header('Location: logout.php');
    exit;
}

// Parsear dirección en componentes (calle, número, piso) - simplificado
$direccion_completa = $usuario['direccion'] ?? '';
$direccion_parseada = ['calle' => '', 'numero' => '', 'piso' => ''];
if (!empty($direccion_completa)) {
    // Parseo simple: buscar primer número
    if (preg_match('/^(.+?)\s+(\d+)(.*)$/', trim($direccion_completa), $matches)) {
        $direccion_parseada['calle'] = trim($matches[1]);
        $direccion_parseada['numero'] = $matches[2];
        $direccion_parseada['piso'] = trim($matches[3]);
    } else {
        $direccion_parseada['calle'] = trim($direccion_completa);
    }
}

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
                                               max="2012-12-31"
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
                                            // Mostrar dirección parseada formateada
                                            // Parseo simple de dirección
                                            $direccion_completa = $usuario['direccion'] ?? '';
                                            $dir_parseada = ['calle' => '', 'numero' => '', 'piso' => ''];
                                            if (!empty($direccion_completa)) {
                                                if (preg_match('/^(.+?)\s+(\d+)(.*)$/', trim($direccion_completa), $matches)) {
                                                    $dir_parseada['calle'] = trim($matches[1]);
                                                    $dir_parseada['numero'] = $matches[2];
                                                    $dir_parseada['piso'] = trim($matches[3]);
                                                } else {
                                                    $dir_parseada['calle'] = trim($direccion_completa);
                                                }
                                            }
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
                                        <a href="admin.php" class="btn btn-danger">
                                            <i class="fas fa-shield-alt me-2"></i>Panel de Administración
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
                                                <i class="fas fa-road me-2"></i>Dirección <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" 
                                                   class="form-control datos-envio-input" 
                                                   id="envio_direccion_calle" 
                                                   name="direccion_calle" 
                                                   value="<?= htmlspecialchars($direccion_parseada['calle']) ?>" 
                                                   placeholder="Ej: Av. Corrientes"
                                                   pattern="[A-Za-z0-9 ]+"
                                                   title="Solo se permiten letras, números y espacios"
                                                   required>
                                        </div>
                                        
                                        <!-- Número -->
                                        <div class="col-md-3">
                                            <label for="envio_direccion_numero" class="form-label datos-envio-label">
                                                <i class="fas fa-hashtag me-2"></i>Número <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" 
                                                   class="form-control datos-envio-input" 
                                                   id="envio_direccion_numero" 
                                                   name="direccion_numero" 
                                                   value="<?= htmlspecialchars($direccion_parseada['numero']) ?>" 
                                                   placeholder="Ej: 1234"
                                                   pattern="[A-Za-z0-9 ]+"
                                                   title="Solo se permiten letras, números y espacios"
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
                                                   pattern="[A-Za-z0-9 ]+"
                                                   title="Solo se permiten letras, números y espacios">
                                        </div>
                                    </div>
                                    
                                    <!-- Provincia, Localidad y Código Postal -->
                                    <div class="row g-3 mt-2">
                                        <!-- Provincia -->
                                        <div class="col-md-4">
                                            <label for="envio_provincia" class="form-label datos-envio-label">
                                                <i class="fas fa-map me-2"></i>Provincia <span class="text-danger">*</span>
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
                                                <?php if (!empty($usuario['provincia'])): 
                                                    $provincias_lista = ['Buenos Aires', 'CABA', 'Catamarca', 'Chaco', 'Chubut', 'Córdoba', 'Corrientes', 'Entre Ríos', 'Formosa', 'Jujuy', 'La Pampa', 'La Rioja', 'Mendoza', 'Misiones', 'Neuquén', 'Río Negro', 'Salta', 'San Juan', 'San Luis', 'Santa Cruz', 'Santa Fe', 'Santiago del Estero', 'Tierra del Fuego', 'Tucumán'];
                                                    if (!in_array($usuario['provincia'], $provincias_lista)): ?>
                                                        <option value="<?php echo htmlspecialchars($usuario['provincia']); ?>" selected><?php echo htmlspecialchars($usuario['provincia']); ?></option>
                                                    <?php endif; 
                                                endif; ?>
                                            </select>
                                        </div>
                                        
                                        <!-- Localidad -->
                                        <div class="col-md-4">
                                            <label for="envio_localidad" class="form-label datos-envio-label">
                                                <i class="fas fa-city me-2"></i>Localidad <span class="text-danger">*</span>
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
                                                <i class="fas fa-mail-bulk me-2"></i>Código Postal <span class="text-danger">*</span>
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
                                // ============================================================================
                                // MAPEOS DE ESTADOS - Definidos una vez antes del loop para evitar redundancia
                                // ============================================================================
                                
                                // Mapeo de estados de pedido para mostrar badges con colores y textos
                                $estados_pedido_map = [
                                    'pendiente' => ['clase' => 'bg-warning', 'texto' => 'Pendiente'],
                                    'preparacion' => ['clase' => 'bg-info', 'texto' => 'En Preparación'],
                                    'en_viaje' => ['clase' => 'bg-primary', 'texto' => 'En Viaje'],
                                    'completado' => ['clase' => 'bg-success', 'texto' => 'Completado'],
                                    'devolucion' => ['clase' => 'bg-secondary', 'texto' => 'En Devolución'],
                                    'cancelado' => ['clase' => 'bg-secondary', 'texto' => 'Cancelado']
                                ];
                                
                                // Mapeo de estados de pago para mostrar badges con colores y nombres
                                $estados_pago_map = [
                                    'pendiente' => ['color' => 'warning', 'nombre' => 'Pendiente'],
                                    'pendiente_aprobacion' => ['color' => 'info', 'nombre' => 'Pendiente Aprobación'],
                                    'aprobado' => ['color' => 'success', 'nombre' => 'Aprobado'],
                                    'rechazado' => ['color' => 'danger', 'nombre' => 'Rechazado'],
                                    'cancelado' => ['color' => 'secondary', 'nombre' => 'Cancelado']
                                ];
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
                                            
                                            // Normalizar estado del pedido una sola vez (consolidación de variables)
                                            $estado_pedido = trim(strtolower($pedido['estado_pedido'] ?? 'pendiente'));
                                            
                                            // Validar si se puede cancelar el pedido
                                            // Lógica mejorada: verificar tanto el estado del pedido como el estado del pago
                                            // - El pedido debe estar en 'pendiente' o 'preparacion'
                                            // - El pago NO debe estar cancelado (si existe pago)
                                            // Nota: Si el pago está cancelado, el pedido debería estar cancelado según la lógica de negocio.
                                            // Este check previene mostrar el botón en casos de inconsistencia de datos.
                                            $puede_cancelar_pedido = in_array($estado_pedido, ['pendiente', 'preparacion']) 
                                                && (!$pago_pedido || $pago_pedido['estado_pago'] !== 'cancelado');
                                            
                                            // Validar si se puede marcar el pago como pagado (solo pagos pendientes)
                                            $puede_marcar_pago = $pago_pedido && $pago_pedido['estado_pago'] === 'pendiente';
                                            
                                            
                                            // Determinar si hay acciones disponibles
                                            $tiene_acciones_disponibles = $puede_marcar_pago || $puede_cancelar_pedido;
                                            
                                            // Contar productos del pedido
                                            $cantidad_productos = 0;
                                            foreach ($detalles_pedido as $detalle) {
                                                $cantidad_productos += $detalle['cantidad'];
                                            }
                                            
                                            // Obtener información de estado del pedido usando el mapeo
                                            $info_estado_pedido = $estados_pedido_map[$estado_pedido] ?? ['clase' => 'bg-secondary', 'texto' => ucfirst($estado_pedido)];
                                            
                                            // Obtener información de estado del pago usando el mapeo (si existe pago)
                                            $info_estado_pago = $pago_pedido ? ($estados_pago_map[$pago_pedido['estado_pago']] ?? ['color' => 'secondary', 'nombre' => ucfirst($pago_pedido['estado_pago'])]) : null;
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
                                                    <span class="badge <?= htmlspecialchars($info_estado_pedido['clase']) ?> text-white">
                                                        <?= htmlspecialchars($info_estado_pedido['texto']) ?>
                                                    </span>
                                                    <?php if ($hay_inconsistencia): ?>
                                                        <br><small class="text-danger">
                                                            <i class="fas fa-exclamation-triangle"></i> Inconsistencia detectada
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($pago_pedido): ?>
                                                        <span class="badge bg-<?= htmlspecialchars($info_estado_pago['color']) ?> text-white">
                                                            <?= htmlspecialchars($info_estado_pago['nombre']) ?>
                                                        </span>
                                                        <?php if ($hay_inconsistencia): ?>
                                                            <br><small class="text-danger">
                                                                <i class="fas fa-exclamation-triangle"></i> Revisar estado
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if (!empty($pago_pedido['numero_transaccion'])): ?>
                                                            <br><small class="text-muted">Código: <?= htmlspecialchars($pago_pedido['numero_transaccion']) ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary text-white">Sin pago</span>
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
                                                        
                                                        <!-- Botón Cancelar Pedido (solo si está pendiente o en preparación) -->
                                                        <?php if ($puede_cancelar_pedido): ?>
                                                        <?php renderFormularioCancelarPedido($pedido); ?>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Botón Reclamo/Consulta (solo si no hay más acciones disponibles) -->
                                                        <?php if (!$tiene_acciones_disponibles): ?>
                                                        <?php
                                                        // Verificar si ya se envió un mensaje para este pedido
                                                        ?>
                                                        <?php if (false): ?>
                                                        <button type="button" 
                                                                class="btn btn-outline-secondary btn-sm" 
                                                                disabled
                                                                title="Ya has enviado un mensaje sobre este pedido. Aguarda nuestra respuesta.">
                                                            <i class="fas fa-clock me-1"></i>Aguarde Respuesta
                                                        </button>
                                                        <?php else: ?>
                                                        <a href="index.php?asunto=problema_pedido&pedido=<?= htmlspecialchars($pedido['id_pedido'], ENT_QUOTES, 'UTF-8') ?>#contacto" 
                                                           class="btn btn-outline-info btn-sm">
                                                            <i class="fas fa-comment-dots me-1"></i>Reclamo/Consulta
                                                        </a>
                                                        <?php endif; ?>
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
                                    <h5 class="mb-3"><i class="fas fa-question-circle me-2"></i>Pregunta de Recupero</h5>
                                    <form method="POST" action="" data-protect>
                                        <div class="mb-3">
                                            <label for="pregunta_recupero" class="form-label">
                                                <i class="fas fa-question-circle me-1"></i>Pregunta de Recupero
                                            </label>
                                            <select class="form-select" name="pregunta_recupero" id="pregunta_recupero">
                                                <option value="">Selecciona una pregunta (opcional)</option>
                                                <?php foreach ($preguntas_recupero as $pregunta): ?>
                                                    <option value="<?= $pregunta['id_pregunta'] ?>" 
                                                            <?= (isset($usuario['pregunta_recupero']) && $usuario['pregunta_recupero'] == $pregunta['id_pregunta']) ? 'selected' : '' ?>>
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
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="respuesta_recupero" 
                                                   name="respuesta_recupero" 
                                                   value="<?= htmlspecialchars($usuario['respuesta_recupero'] ?? '') ?>"
                                                   minlength="4"
                                                   maxlength="20"
                                                   pattern="[a-zA-Z0-9 ]+"
                                                   title="Letras, números y espacios, mínimo 4 caracteres, máximo 20 caracteres">
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
                                                       maxlength="32"
                                                       autocomplete="new-password"
                                                       title="Mínimo 6 caracteres, máximo 32 caracteres">
                                                <button type="button" class="btn-toggle-password" data-input-id="nueva_contrasena" aria-label="Mostrar contraseña">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>Mínimo 6 caracteres, máximo 32 caracteres
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
                                                       maxlength="32"
                                                       autocomplete="new-password">
                                                <button type="button" class="btn-toggle-password" data-input-id="confirmar_contrasena" aria-label="Mostrar contraseña">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <div id="password-match" class="mt-2"></div>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button type="submit" name="cambiar_contrasena" class="btn btn-warning">
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
                                    <h5 class="mb-3 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Eliminar Cuenta</h5>
                                    <div class="alert alert-danger">
                                        <p class="mb-2"><strong>ESTE PROCESO NO TIENE VUELTA ATRAS</strong></p>
                                        <p class="mb-0 small">Al eliminar tu cuenta, esta será desactivada durante 30 días. Pasado ese período, será eliminada permanentemente. Podrás reactivarla iniciando sesión dentro de los 30 días.</p>
                                    </div>
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalEliminarCuenta">
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
