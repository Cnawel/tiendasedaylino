<?php
/**
 * ========================================================================
 * MANEJO CENTRALIZADO DE ERRORES - Tienda Seda y Lino
 * ========================================================================
 * Funciones centralizadas para procesar y formatear errores de la aplicaci?n
 * Elimina c?digo duplicado de manejo de errores en m?ltiples archivos
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
 * Esta funci?n centraliza la l?gica de procesamiento de errores de stock,
 * eliminando c?digo duplicado en m?ltiples archivos.
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
        // Extraer informaci?n del mensaje si est? disponible
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
        $mensaje_usuario = "No se puede procesar el pedido porque una de las variantes est? inactiva. Acci?n sugerida: Verifica el estado de los productos en el panel de marketing, activa la variante si corresponde, o contacta al cliente para informarle que el producto ya no est? disponible.";
        $tipo_mensaje = 'warning';
    }
    // Error de producto inactivo
    elseif (strpos($error_message, 'producto inactivo') !== false || strpos($error_message, 'No se puede vender un producto inactivo') !== false) {
        $mensaje_usuario = "No se puede procesar el pedido porque uno de los productos est? inactivo. Acci?n sugerida: Verifica el estado de los productos en el panel de marketing, activa el producto si corresponde, o contacta al cliente para informarle que el producto ya no est? disponible.";
        $tipo_mensaje = 'warning';
    }
    // Error de variante no existe
    elseif (strpos($error_message, 'variante de stock no existe') !== false || strpos($error_message, 'La variante de stock no existe') !== false) {
        $mensaje_usuario = "No se puede procesar el pedido porque una de las variantes no existe en el sistema. Acci?n sugerida: Verifica los productos del pedido, revisa si la variante fue eliminada, y contacta al cliente si es necesario.";
        $tipo_mensaje = 'danger';
    }
    // Error de venta ya descontada (guardrail)
    elseif (strpos($error_message, 'VENTA_YA_DESCONTADA_PARA_PEDIDO') !== false || strpos($error_message, 'Ya se descont? stock para este pedido') !== false) {
        $mensaje_usuario = "El stock de este pedido ya fue descontado previamente. No se puede descontar nuevamente.";
        $tipo_mensaje = 'info';
    }
    // Error de pago ya aprobado
    elseif (strpos($error_message, 'Ya existe otro pago aprobado') !== false) {
        $mensaje_usuario = "Ya existe un pago aprobado para este pedido. No se puede aprobar otro pago. Acci?n sugerida: Revisa el historial de pagos del pedido y verifica que no haya duplicados.";
        $tipo_mensaje = 'warning';
    }
    // Error de monto inv?lido
    elseif (strpos($error_message, 'monto menor o igual a cero') !== false) {
        $mensaje_usuario = "El monto del pago no es v?lido. Acci?n sugerida: Verifica el monto del pago en los detalles del pedido y corrige si es necesario.";
        $tipo_mensaje = 'danger';
    }
    // Error de estado inv?lido
    elseif (strpos($error_message, 'Estado de pago inv?lido') !== false) {
        $mensaje_usuario = "El estado del pago no es v?lido. Acci?n sugerida: Recarga la p?gina para obtener la informaci?n actualizada del pedido e intenta nuevamente.";
        $tipo_mensaje = 'danger';
    }
    // Errores de transici?n de estado de pedido
    elseif (strpos($error_message, 'Transici?n no permitida') !== false || strpos($error_message, 'Transici?n de estado') !== false) {
        // Extraer estados del mensaje si est?n disponibles
        $mensaje_usuario = "La transici?n de estado solicitada no est? permitida. " . $error_message . " Sugerencia: Revisa el estado actual del pedido o pago y verifica que la transici?n sea v?lida seg?n el flujo de estados permitidos. Si necesitas hacer un cambio diferente, primero ajusta el estado actual.";
        $tipo_mensaje = 'danger';
    }
    elseif (strpos($error_message, 'Estado de pedido desconocido') !== false) {
        $mensaje_usuario = "El estado del pedido no es v?lido. Acci?n sugerida: Recarga la p?gina para obtener la informaci?n actualizada del pedido e intenta nuevamente.";
        $tipo_mensaje = 'danger';
    }
    elseif (strpos($error_message, 'No se puede cambiar el pedido a preparaci?n') !== false || strpos($error_message, 'preparaci?n') !== false) {
        $mensaje_usuario = $error_message . " Sugerencia: Verifica que el pago est? aprobado antes de cambiar el pedido a preparaci?n. Cuando apruebes el pago desde el panel de ventas, el pedido pasar? autom?ticamente a preparaci?n.";
        $tipo_mensaje = 'danger';
    }
    elseif (strpos($error_message, 'No se puede retroceder') !== false) {
        $mensaje_usuario = "No se puede retroceder el estado del pedido. " . $error_message . " Acci?n sugerida: Solo se pueden avanzar estados de pedido hacia adelante, no retroceder.";
        $tipo_mensaje = 'danger';
    }
    // Error de pago no aprobado para cambios de estado de pedido
    elseif (strpos($error_message, 'No se puede cambiar el estado del pedido sin pago aprobado') !== false) {
        $mensaje_usuario = "No se puede cambiar el estado del pedido sin pago aprobado. " . $error_message . " Acci?n sugerida: Primero aprueba el pago desde el panel de ventas antes de cambiar el estado del pedido.";
        $tipo_mensaje = 'danger';
    }
    // Error de stock insuficiente para cambios de estado
    elseif (strpos($error_message, 'STOCK_INSUFICIENTE: No se puede cambiar el pedido') !== false) {
        // Extraer informaci?n espec?fica del error
        if (preg_match('/STOCK_INSUFICIENTE: (.+)/', $error_message, $matches)) {
            $detalles_error = $matches[1];
            $mensaje_usuario = "No hay suficiente stock para cambiar el pedido de estado. {$detalles_error} Acci?n sugerida: Verifica el stock disponible en el panel de productos y ajusta el inventario antes de cambiar el estado del pedido.";
        } else {
            $mensaje_usuario = "No hay suficiente stock para cambiar el pedido de estado. Acci?n sugerida: Verifica el stock disponible antes de proceder con el cambio.";
        }
        $tipo_mensaje = 'danger';
    }
    elseif (strpos($error_message, 'No se puede cambiar el estado de un pedido devuelto') !== false) {
        $mensaje_usuario = "No se puede cambiar el estado de un pedido devuelto (estado terminal). Acci?n sugerida: Los pedidos devueltos son estados finales y no admiten cambios.";
        $tipo_mensaje = 'danger';
    }
    elseif (strpos($error_message, 'PEDIDO_COMPLETADO_NO_ADMITE_CAMBIOS') !== false || strpos($error_message, 'Un pedido completado no puede cambiar de estado') !== false || strpos($error_message, 'venta cerrada') !== false) {
        $mensaje_usuario = "Un pedido completado no puede cambiar de estado porque es una venta cerrada. Los pedidos completados son estados finales y no admiten modificaciones. Sugerencia: Si necesitas hacer cambios en un pedido completado, contacta al administrador del sistema.";
        $tipo_mensaje = 'danger';
    }
    // Error gen?rico - mostrar mensaje t?cnico pero m?s claro
    else {
        // Si el mensaje contiene informaci?n t?cnica ?til, mostrarlo
        if (strpos($error_message, 'Error al descontar stock') !== false || strpos($error_message, 'Error al registrar movimiento') !== false) {
            // El mensaje ya tiene contexto, agregar acci?n sugerida
            $mensaje_usuario = $error_message . " Acci?n sugerida: Verifica el stock disponible y los movimientos de stock recientes. Si el problema persiste, contacta al administrador.";
        } else {
            $id_contexto = $id_pedido ? "pedido #{$id_pedido}" : ($id_pago ? "pago #{$id_pago}" : "");
            $mensaje_usuario = "Error al procesar la operaci?n" . ($id_contexto ? " del {$id_contexto}" : "") . ". Acci?n sugerida: Verifica que todos los datos sean correctos e intenta nuevamente. Si el problema persiste, contacta al administrador.";
        }
        $tipo_mensaje = 'danger';
    }

    return ['mensaje' => $mensaje_usuario, 'mensaje_tipo' => $tipo_mensaje];
}

