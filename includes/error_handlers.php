<?php
/**
 * ========================================================================
 * MANEJO CENTRALIZADO DE ERRORES - Tienda Seda y Lino
 * ========================================================================
 * Funciones centralizadas para procesar y formatear errores de la aplicación
 * Elimina código duplicado de manejo de errores en múltiples archivos
 * 
 * FUNCIONES:
 * - procesarErrorStock(): Procesa errores relacionados con stock y retorna mensaje formateado
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Procesa errores relacionados con stock y retorna mensaje formateado para el usuario
 * 
 * Esta función centraliza la lógica de procesamiento de errores de stock,
 * eliminando código duplicado en múltiples archivos.
 * 
 * @param string $error_message Mensaje de error original
 * @param array $contexto Contexto adicional del error (id_pedido, id_pago, etc.)
 * @return array Array con ['mensaje' => string, 'mensaje_tipo' => string]
 */
function procesarErrorStock($error_message, $contexto = []) {
    $mensaje_usuario = '';
    $tipo_mensaje = 'danger';
    $id_pedido = $contexto['id_pedido'] ?? null;
    $id_pago = $contexto['id_pago'] ?? null;
    
    // Error de stock insuficiente
    if (strpos($error_message, 'Stock insuficiente') !== false || strpos($error_message, 'STOCK_INSUFICIENTE') !== false) {
        // Extraer información del mensaje si está disponible
        if (preg_match('/Stock disponible: (\d+), Intento de venta: (\d+)/', $error_message, $matches)) {
            $stock_disponible = $matches[1];
            $intento_venta = $matches[2];
            $mensaje_usuario = "No hay suficiente stock para completar este pedido. Stock disponible: {$stock_disponible} unidades, pero se necesitan {$intento_venta} unidades. Sugerencia: Revisa el stock disponible en el panel de productos y ajusta la cantidad del pedido, o contacta al cliente para informarle sobre la disponibilidad limitada.";
        } elseif (preg_match('/Variante #(\d+): Tiene (\d+) unidades disponibles pero se necesitan (\d+) unidades/', $error_message, $matches)) {
            $id_variante = $matches[1];
            $stock_disponible = $matches[2];
            $intento_venta = $matches[3];
            $mensaje_usuario = "No hay suficiente stock para completar este pedido. La variante #{$id_variante} tiene {$stock_disponible} unidades disponibles pero se necesitan {$intento_venta} unidades. Sugerencia: Revisa el stock disponible o contacta al cliente para ajustar la cantidad.";
        } else {
            $mensaje_usuario = "No hay suficiente stock para completar este pedido. Sugerencia: Revisa el stock disponible en el panel de productos y ajusta la cantidad del pedido, o contacta al cliente para informarle sobre la disponibilidad.";
        }
        $tipo_mensaje = 'warning';
    }
    // Error de variante inactiva
    elseif (strpos($error_message, 'variante inactiva') !== false || strpos($error_message, 'No se puede vender una variante inactiva') !== false) {
        $mensaje_usuario = "No se puede procesar el pedido porque una de las variantes está inactiva. Acción sugerida: Verifica el estado de los productos en el panel de marketing, activa la variante si corresponde, o contacta al cliente para informarle que el producto ya no está disponible.";
        $tipo_mensaje = 'warning';
    }
    // Error de producto inactivo
    elseif (strpos($error_message, 'producto inactivo') !== false || strpos($error_message, 'No se puede vender un producto inactivo') !== false) {
        $mensaje_usuario = "No se puede procesar el pedido porque uno de los productos está inactivo. Acción sugerida: Verifica el estado de los productos en el panel de marketing, activa el producto si corresponde, o contacta al cliente para informarle que el producto ya no está disponible.";
        $tipo_mensaje = 'warning';
    }
    // Error de variante no existe
    elseif (strpos($error_message, 'variante de stock no existe') !== false || strpos($error_message, 'La variante de stock no existe') !== false) {
        $mensaje_usuario = "No se puede procesar el pedido porque una de las variantes no existe en el sistema. Acción sugerida: Verifica los productos del pedido, revisa si la variante fue eliminada, y contacta al cliente si es necesario.";
        $tipo_mensaje = 'danger';
    }
    // Error de venta ya descontada (guardrail)
    elseif (strpos($error_message, 'VENTA_YA_DESCONTADA_PARA_PEDIDO') !== false || strpos($error_message, 'Ya se descontó stock para este pedido') !== false) {
        $mensaje_usuario = "El stock de este pedido ya fue descontado previamente. No se puede descontar nuevamente.";
        $tipo_mensaje = 'info';
    }
    // Error de pago ya aprobado
    elseif (strpos($error_message, 'Ya existe otro pago aprobado') !== false) {
        $mensaje_usuario = "Ya existe un pago aprobado para este pedido. No se puede aprobar otro pago. Acción sugerida: Revisa el historial de pagos del pedido y verifica que no haya duplicados.";
        $tipo_mensaje = 'warning';
    }
    // Error de monto inválido
    elseif (strpos($error_message, 'monto menor o igual a cero') !== false) {
        $mensaje_usuario = "El monto del pago no es válido. Acción sugerida: Verifica el monto del pago en los detalles del pedido y corrige si es necesario.";
        $tipo_mensaje = 'danger';
    }
    // Error de estado inválido
    elseif (strpos($error_message, 'Estado de pago inválido') !== false) {
        $mensaje_usuario = "El estado del pago no es válido. Acción sugerida: Recarga la página para obtener la información actualizada del pedido e intenta nuevamente.";
        $tipo_mensaje = 'danger';
    }
    // Errores de transición de estado de pedido
    elseif (strpos($error_message, 'Transición no permitida') !== false || strpos($error_message, 'Transición de estado') !== false) {
        // Extraer estados del mensaje si están disponibles
        $mensaje_usuario = "La transición de estado solicitada no está permitida. " . $error_message . " Sugerencia: Revisa el estado actual del pedido o pago y verifica que la transición sea válida según el flujo de estados permitidos. Si necesitas hacer un cambio diferente, primero ajusta el estado actual.";
        $tipo_mensaje = 'danger';
    }
    elseif (strpos($error_message, 'Estado de pedido desconocido') !== false) {
        $mensaje_usuario = "El estado del pedido no es válido. Acción sugerida: Recarga la página para obtener la información actualizada del pedido e intenta nuevamente.";
        $tipo_mensaje = 'danger';
    }
    elseif (strpos($error_message, 'No se puede cambiar el pedido a preparación') !== false || strpos($error_message, 'preparación') !== false) {
        $mensaje_usuario = $error_message . " Sugerencia: Verifica que el pago esté aprobado antes de cambiar el pedido a preparación. Cuando apruebes el pago desde el panel de ventas, el pedido pasará automáticamente a preparación.";
        $tipo_mensaje = 'danger';
    }
    elseif (strpos($error_message, 'No se puede retroceder') !== false) {
        $mensaje_usuario = "No se puede retroceder el estado del pedido. " . $error_message . " Acción sugerida: Solo se pueden avanzar estados de pedido hacia adelante, no retroceder.";
        $tipo_mensaje = 'danger';
    }
    elseif (strpos($error_message, 'No se puede cambiar el estado de un pedido devuelto') !== false) {
        $mensaje_usuario = "No se puede cambiar el estado de un pedido devuelto (estado terminal). Acción sugerida: Los pedidos devueltos son estados finales y no admiten cambios.";
        $tipo_mensaje = 'danger';
    }
    elseif (strpos($error_message, 'PEDIDO_COMPLETADO_NO_ADMITE_CAMBIOS') !== false || strpos($error_message, 'Un pedido completado no puede cambiar de estado') !== false || strpos($error_message, 'venta cerrada') !== false) {
        $mensaje_usuario = "Un pedido completado no puede cambiar de estado porque es una venta cerrada. Los pedidos completados son estados finales y no admiten modificaciones. Sugerencia: Si necesitas hacer cambios en un pedido completado, contacta al administrador del sistema.";
        $tipo_mensaje = 'danger';
    }
    // Error genérico - mostrar mensaje técnico pero más claro
    else {
        // Si el mensaje contiene información técnica útil, mostrarlo
        if (strpos($error_message, 'Error al descontar stock') !== false || strpos($error_message, 'Error al registrar movimiento') !== false) {
            // El mensaje ya tiene contexto, agregar acción sugerida
            $mensaje_usuario = $error_message . " Acción sugerida: Verifica el stock disponible y los movimientos de stock recientes. Si el problema persiste, contacta al administrador.";
        } else {
            $id_contexto = $id_pedido ? "pedido #{$id_pedido}" : ($id_pago ? "pago #{$id_pago}" : "");
            $mensaje_usuario = "Error al procesar la operación" . ($id_contexto ? " del {$id_contexto}" : "") . ". Acción sugerida: Verifica que todos los datos sean correctos e intenta nuevamente. Si el problema persiste, contacta al administrador.";
        }
        $tipo_mensaje = 'danger';
    }
    
    return ['mensaje' => $mensaje_usuario, 'mensaje_tipo' => $tipo_mensaje];
}

