<?php
/**
 * ========================================================================
 * PROCESADOR DE CAMBIO DE ESTADO DE PAGO (CON VALIDACIONES COMPLETAS)
 * ========================================================================
 *
 * Archivo responsable de procesar cambios de estado de pagos desde el
 * panel de ventas. Utiliza funciones validadas que incluyen:
 *
 * ✅ VALIDACIONES AUTOMÁTICAS:
 * - Transiciones de estado válidas
 * - Lógica de negocio completa
 * - Gestión automática de stock
 * - Envío automático de emails
 * - Auditoría completa
 *
 * ✅ FUNCIONES UTILIZADAS:
 * - actualizarEstadoPagoConPedido(): Maneja todo el flujo de cambio de estado
 * - Incluye validaciones, stock, emails y auditoría automáticamente
 *
 * POST Parameters:
 * - pedido_id: ID del pedido (required)
 * - nuevo_estado_pago: Nuevo estado para el pago (required)
 * - estado_pago_anterior: Estado anterior (para auditoría)
 * - motivo_rechazo: Motivo si se rechaza el pago (optional)
 *
 * Response:
 * - JSON con campos: success, message, data (si aplica)
 *
 * NOTA: Este archivo delega toda la lógica compleja a las funciones
 * validadas, manteniendo simplicidad y consistencia.
 *
 * @package TiendaSedaYLino
 * ========================================================================
 */

session_start();

// Detectar si es AJAX
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Headers para respuesta JSON
if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
}

// Cargar configuración y dependencias
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/estado_helpers.php';
require_once __DIR__ . '/includes/state_functions.php';
require_once __DIR__ . '/includes/queries/pago_queries.php';
require_once __DIR__ . '/includes/queries/pedido_queries.php';
require_once __DIR__ . '/includes/email_gmail_functions.php'; // Incluir funciones de email

// Función auxiliar para respuestas
function responder($success, $message, $data = null) {
    global $is_ajax;

    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    if ($is_ajax) {
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    } else {
        // Para requests no-AJAX, redirigir a ventas.php con mensaje en sesión
        $_SESSION['mensaje'] = $message;
        $_SESSION['mensaje_tipo'] = $success ? 'success' : 'danger';

        $redirect_url = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'ventas.php';
        header('Location: ' . $redirect_url);
    }

    exit;
}

try {
    // Validar autenticación
    if (!isset($_SESSION['id_usuario']) || $_SESSION['id_usuario'] === null) {
        responder(false, 'No autorizado. Inicie sesión primero.');
    }

    // Validar rol: Solo Ventas y Admin pueden cambiar estado de pago
    // Los roles vienen en minúsculas (ventas, admin)
    $rol_norm = strtolower($_SESSION['rol']);
    if (!in_array($rol_norm, ['ventas', 'admin'])) {
        responder(false, 'No tiene permiso para cambiar el estado de pagos.');
    }

    // Validar parámetros POST
    if (empty($_POST['pedido_id']) || empty($_POST['nuevo_estado_pago'])) {
        responder(false, 'Parámetros faltantes: pedido_id y nuevo_estado_pago son requeridos.');
    }

    $pedido_id = intval($_POST['pedido_id']);
    $nuevo_estado_pago = trim($_POST['nuevo_estado_pago']);
    $motivo_rechazo = !empty($_POST['motivo_rechazo']) ? trim($_POST['motivo_rechazo']) : null;

    // Validar que el pedido_id sea válido
    if ($pedido_id <= 0) {
        responder(false, 'ID de pedido inválido.');
    }

    // Obtener pago asociado al pedido
    $pago = obtenerPagoPorPedido($mysqli, $pedido_id);
    if (!$pago) {
        responder(false, 'No se encontró pago para este pedido.');
    }

    $id_pago = $pago['id_pago'];
    $estado_pago_actual = $pago['estado_pago'];

    // Normalizar estado
    $nuevo_estado_pago = normalizarEstado($nuevo_estado_pago);

    // Validar que sea un estado válido
    $estados_validos = ['pendiente', 'pendiente_aprobacion', 'aprobado', 'rechazado', 'cancelado'];
    if (!in_array($nuevo_estado_pago, $estados_validos)) {
        responder(false, "Estado de pago inválido: {$nuevo_estado_pago}");
    }

    // Validar transición usando state_functions
    if (!puedeTransicionarPago($estado_pago_actual, $nuevo_estado_pago)) {
        responder(false, "No se puede pasar de '{$estado_pago_actual}' a '{$nuevo_estado_pago}'");
    }

    // Si es rechazo, requiere motivo
    if ($nuevo_estado_pago === 'rechazado' && empty($motivo_rechazo)) {
        responder(false, 'Debe ingresar un motivo para rechazar el pago.');
    }

    // Iniciar transacción para garantizar atomicidad
    $mysqli->begin_transaction();

    try {
        // =========================================================================
        // USAR FUNCIONES VALIDADAS QUE INCLUYEN TODA LA LÓGICA DE NEGOCIO
        // =========================================================================
        // actualizarEstadoPagoConPedido() maneja automáticamente:
        // - Validación de transiciones de estado
        // - Actualización de estado del pedido cuando se aprueba el pago
        // - Gestión de stock (deducción automática al aprobar)
        // - Envío de emails
        // - Auditoría completa
        // =========================================================================

        $success_pago = actualizarEstadoPagoConPedido(
            $mysqli,
            $id_pago,
            $nuevo_estado_pago,
            $motivo_rechazo,
            $_SESSION['id_usuario'] // ID del usuario que realiza la acción
        );

        if (!$success_pago) {
            throw new Exception('Error al actualizar el estado del pago. Verifique que la transición sea válida.');
        }

        // =========================================================================
        // NOTA IMPORTANTE:
        // La función actualizarEstadoPagoConPedido() YA MANEJA automáticamente:
        // - Cambio de estado del pedido cuando se aprueba el pago
        // - Cancelación del pedido cuando se rechaza/cancela el pago
        // - Gestión de stock (deducción al aprobar, reversión al rechazar)
        // - Envío de emails de notificación
        // - Validaciones de negocio completas
        //
        // NO es necesario hacer actualizaciones manuales adicionales aquí.
        // =========================================================================

        // =========================================================================
        // LA TRANSACCIÓN Y EL ENVÍO DE EMAILS YA FUERON MANEJADOS POR
        // actualizarEstadoPagoConPedido()
        // =========================================================================

        // No necesitamos hacer commit aquí - actualizarEstadoPagoConPedido() ya lo hizo
        // No necesitamos enviar emails aquí - actualizarEstadoPagoConPedido() ya los envió

        // =========================================================================
        // MENSAJES DE ÉXITO - LA FUNCIÓN VALIDADORA YA MANEJÓ TODO
        // =========================================================================
        // actualizarEstadoPagoConPedido() ya actualizó estados, stock, emails, etc.
        // Solo necesitamos confirmar que la operación fue exitosa.

        $mensajes = [
            'aprobado' => 'Pago aprobado correctamente. Pedido actualizado a preparación y stock descontado.',
            'rechazado' => 'Pago rechazado correctamente. Pedido cancelado y cliente notificado.',
            'cancelado' => 'Pago cancelado correctamente. Pedido cancelado y cliente notificado.',
            'pendiente_aprobacion' => 'Pago marcado como pendiente de aprobación.',
            'pendiente' => 'Pago vuelto a estado pendiente.'
        ];

        $mensaje = $mensajes[$nuevo_estado_pago] ?? "Estado del pago actualizado correctamente a: {$nuevo_estado_pago}";

        // Obtener datos actualizados para respuesta
        $pago_actualizado = obtenerPagoPorId($mysqli, $id_pago);
        $pedido_actualizado = obtenerPedidoPorId($mysqli, $pedido_id);

        responder(true, $mensaje, [
            'pago' => $pago_actualizado,
            'pedido' => $pedido_actualizado
        ]);

    } catch (Exception $e) {
        // Rollback en caso de error
        $mysqli->rollback();
        // SEGURIDAD: Registrar excepción pero no exponerla al usuario
        error_log("[PAGO ESTADO EXCEPTION] " . $e->getMessage());
        responder(false, 'Error al procesar el cambio de estado. Por favor, inténtalo nuevamente.');
    }

} catch (Exception $e) {
    // SEGURIDAD: Registrar excepción pero no exponerla al usuario
    error_log("[PAGO ESTADO OUTER EXCEPTION] " . $e->getMessage());
    responder(false, 'Error del sistema. Por favor, inténtalo nuevamente.');
}
?>
