<?php
/**
 * ========================================================================
 * FUNCIONES AUXILIARES DE VENTAS - Tienda Seda y Lino
 * ========================================================================
 * Funciones auxiliares para el panel de ventas
 * 
 * FUNCIONES:
 * - procesarActualizacionPedidoPago(): Procesa actualización completa de pedido y pago
 * - procesarAprobacionPago(): Procesa aprobación de pago
 * - procesarRechazoPago(): Procesa rechazo de pago
 * - procesarAgregarMetodoPago(): Procesa agregación de método de pago
 * - procesarActualizarMetodoPago(): Procesa actualización de método de pago
 * - procesarEliminarMetodoPago(): Procesa eliminación de método de pago
 * - procesarToggleActivoMetodoPago(): Procesa cambio de estado activo/inactivo de método de pago
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */


/**
 * Procesa errores de actualización de pedido y pago, retornando mensajes amigables
 * 
 * @deprecated Usar procesarErrorStock() de includes/error_handlers.php en su lugar
 * @param string $error_message Mensaje de error original
 * @param int $pedido_id ID del pedido
 * @return array Array con ['mensaje' => string, 'mensaje_tipo' => string]
 */
function _procesarErroresActualizacionPedidoPago($error_message, $pedido_id) {
    // Sanitizar mensaje de error removiendo información técnica sensible
    $error_message = _sanitizarMensajeError($error_message);

    // Primero intentar procesar como error de stock
    require_once __DIR__ . '/error_handlers.php';
    $resultado_stock = procesarErrorStock($error_message, ['id_pedido' => $pedido_id]);

    // Si procesarErrorStock retornó un mensaje genérico (no procesó el error específico),
    // intentar procesar como error de pago
    if (strpos($resultado_stock['mensaje'], 'Error al procesar la operación') !== false) {
        // Procesar errores específicos de pago
        if (strpos($error_message, 'No se puede cambiar el estado del pedido sin pago aprobado') !== false) {
            return ['mensaje' => 'No se puede cambiar el estado del pedido sin pago aprobado. Primero aprueba el pago desde el panel de ventas.', 'mensaje_tipo' => 'danger'];
        }
        elseif (strpos($error_message, 'No se puede aprobar el pago') !== false) {
            return ['mensaje' => $error_message, 'mensaje_tipo' => 'warning'];
        }
        elseif (strpos($error_message, 'No se puede rechazar') !== false || strpos($error_message, 'No se puede cancelar') !== false) {
            return ['mensaje' => 'No se puede cambiar el estado del pago en este momento. Verifica que cumpla con las reglas de negocio.', 'mensaje_tipo' => 'warning'];
        }
        elseif (strpos($error_message, 'Transición de estado') !== false) {
            return ['mensaje' => 'La transición de estado solicitada no está permitida según las reglas de negocio.', 'mensaje_tipo' => 'warning'];
        }
        elseif (strpos($error_message, 'Error al actualizar estado del pago') !== false) {
            return ['mensaje' => 'Error al actualizar el estado del pago. Inténtalo nuevamente.', 'mensaje_tipo' => 'danger'];
        }
        elseif (strpos($error_message, 'Error al actualizar estado del pedido') !== false) {
            return ['mensaje' => 'Error al actualizar el estado del pedido. Inténtalo nuevamente.', 'mensaje_tipo' => 'danger'];
        }
        // Si no se reconoce el error, mostrar mensaje genérico pero más corto
        else {
            $id_contexto = $pedido_id ? "pedido #$pedido_id" : "";
            return ['mensaje' => "Error al procesar la operación" . ($id_contexto ? " del {$id_contexto}" : "") . ". Contacta al administrador si el problema persiste.", 'mensaje_tipo' => 'danger'];
        }
    }

    return $resultado_stock;
}

/**
 * Sanitiza mensajes de error removiendo información técnica sensible
 *
 * @param string $mensaje_error Mensaje de error original
 * @return string Mensaje sanitizado
 */
function _sanitizarMensajeError($mensaje_error) {
    // Remover errores de MySQL que contienen información técnica
    $mensaje_error = preg_replace('/Error al preparar.*?: .*/', 'Error de base de datos', $mensaje_error);
    $mensaje_error = preg_replace('/Error al ejecutar.*?: .*/', 'Error de base de datos', $mensaje_error);
    $mensaje_error = preg_replace('/You have an error in your SQL syntax.*?/', 'Error de consulta SQL', $mensaje_error);

    // Remover paths de archivos del sistema
    $mensaje_error = preg_replace('/\/[a-zA-Z0-9_\/\-]+\.php/', '[archivo]', $mensaje_error);

    // Limitar longitud del mensaje a 200 caracteres para evitar mensajes muy largos
    if (strlen($mensaje_error) > 200) {
        $mensaje_error = substr($mensaje_error, 0, 200) . '...';
    }

    return $mensaje_error;
}

/**
 * Procesa la actualización completa de pedido y pago
 * 
 * Esta función valida los datos, actualiza el pedido y pago, maneja cambios de stock
 * según los estados, y gestiona transacciones para garantizar integridad.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @param int $id_usuario ID del usuario ventas que realiza la acción
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarActualizacionPedidoPago($mysqli, $post, $id_usuario) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['actualizar_estado_pedido'])) {
        return false;
    }

    // Cargar funciones necesarias
    require_once __DIR__ . '/queries_helper.php';
    require_once __DIR__ . '/estado_helpers.php';
    try {
        cargarArchivoQueries('pedido_queries', __DIR__ . '/queries');
        cargarArchivoQueries('pago_queries', __DIR__ . '/queries');
        require_once __DIR__ . '/queries/pedido_queries.php';
        require_once __DIR__ . '/email_gmail_functions.php';
    } catch (Exception $e) {
        error_log("ERROR: No se pudo cargar archivos de queries - " . $e->getMessage());
        die("Error crítico: Archivo de consultas no encontrado. Por favor, contacta al administrador.");
    }

    // Extraer y normalizar datos del formulario
    $pedido_id = intval($post['pedido_id'] ?? 0);
    $nuevo_estado = normalizarEstado($post['nuevo_estado'] ?? '');

    // IMPORTANTE: No normalizar nuevo_estado_pago si está vacío (opción "-- Mantener estado actual --")
    // Solo normalizar si tiene un valor real
    $nuevo_estado_pago_raw = trim($post['nuevo_estado_pago'] ?? '');
    $nuevo_estado_pago = !empty($nuevo_estado_pago_raw) ? normalizarEstado($nuevo_estado_pago_raw) : '';

    $estado_anterior = normalizarEstado($post['estado_anterior'] ?? '');
    $estado_pago_anterior = normalizarEstado($post['estado_pago_anterior'] ?? '');
    
    // Campos adicionales del pago
    // Nota: monto_pago y numero_transaccion son solo lectura (llenados por el cliente)
    // Solo se procesa el motivo de rechazo si se rechaza el pago
    // Solo se procesa el motivo de rechazo si se rechaza el pago
    $motivo_rechazo = trim($post['motivo_rechazo'] ?? '');

    // Extraer datos de seguimiento (opcionales)
    $codigo_seguimiento = trim($post['codigo_seguimiento'] ?? '');
    $empresa_envio = trim($post['empresa_envio'] ?? '');
    
    // Validar estados permitidos (según ENUM de la base de datos)
    // NOTA: Todo pedido en 'pendiente' ya tiene stock validado (se valida antes de crear el pedido)
    $estados_pedido_validos = ['pendiente', 'preparacion', 'en_viaje', 'completado', 'cancelado'];
    $estados_pago_validos = ['pendiente', 'pendiente_aprobacion', 'aprobado', 'rechazado', 'cancelado'];
    
    // Validar que el estado del pedido sea válido
    if ($pedido_id <= 0) {
        return ['mensaje' => 'ID de pedido inválido', 'mensaje_tipo' => 'danger'];
    }
    
    if (empty($nuevo_estado) || !in_array($nuevo_estado, $estados_pedido_validos)) {
        return ['mensaje' => 'Estado de pedido inválido. Estado recibido: ' . htmlspecialchars($nuevo_estado), 'mensaje_tipo' => 'danger'];
    }
    
    // Obtener información del pago
    $pago_actual = obtenerPagoPorPedido($mysqli, $pedido_id);
    $estado_pago_anterior_real = $pago_actual ? normalizarEstado($pago_actual['estado_pago']) : '';
    
    // Determinar si se está cambiando el estado del pago
    $cambiar_estado_pago = false;
    $estado_pago_final = '';
    if ($pago_actual && !empty($nuevo_estado_pago) && in_array($nuevo_estado_pago, $estados_pago_validos)) {
        if ($nuevo_estado_pago !== $estado_pago_anterior_real) {
            $cambiar_estado_pago = true;
            $estado_pago_final = $nuevo_estado_pago;
        }
    }
    
    // Preparar motivo de rechazo si se está rechazando el pago
    $motivo_rechazo_final = null;
    if ($cambiar_estado_pago && $estado_pago_final === 'rechazado' && !empty($motivo_rechazo)) {
        // Validar motivo de rechazo usando función centralizada
        require_once __DIR__ . '/validation_functions.php';
        $validacion_motivo = validarObservaciones($motivo_rechazo, 500, 'motivo de rechazo');
        if (!$validacion_motivo['valido']) {
            return ['mensaje' => $validacion_motivo['error'], 'mensaje_tipo' => 'danger'];
        }
        $motivo_rechazo_final = $validacion_motivo['valor'];
    }
    
    // Validar transiciones de estado antes de iniciar transacción
    $pedido_actual = obtenerPedidoPorId($mysqli, $pedido_id);
    if (!$pedido_actual) {
        return ['mensaje' => 'Pedido no encontrado', 'mensaje_tipo' => 'danger'];
    }
    
    $estado_pedido_actual = normalizarEstado($pedido_actual['estado_pedido'] ?? '');
    $nuevo_estado_norm = normalizarEstado($nuevo_estado);
    
        // Si no hay cambios reales en ningún estado, informar al usuario
        // Solo verificar si realmente se está intentando cambiar el estado del pago
        $cambio_pedido = $estado_pedido_actual !== $nuevo_estado_norm;
        $cambio_pago = $cambiar_estado_pago && !empty($estado_pago_final) && $estado_pago_anterior_real !== normalizarEstado($estado_pago_final);
        // Si hay un pago y se recibió un valor vacío para nuevo_estado_pago, verificar si hay transiciones disponibles
        // MEJORADO: Detectar cuando el campo viene vacío (usuario no seleccionó nada del dropdown)
        if ($pago_actual) {
            $nuevo_estado_pago_viene_vacio = isset($_POST['nuevo_estado_pago']) && trim($_POST['nuevo_estado_pago']) === '';

            if ($nuevo_estado_pago_viene_vacio) {
                // Verificar si hay transiciones disponibles
                require_once __DIR__ . '/ventas_components.php';
                $transiciones_disponibles = obtenerTransicionesValidasPago($estado_pago_anterior_real);

                if (!empty($transiciones_disponibles)) {
                    return [
                        'mensaje' => '⚠️ Debe seleccionar un nuevo estado de pago del menú desplegable.<br><br>' .
                                '<strong>Estado actual:</strong> ' . strtoupper($estado_pago_anterior_real) . '<br>' .
                                '<strong>Opciones disponibles:</strong> ' . implode(', ', array_map('ucfirst', $transiciones_disponibles)) . '<br><br>' .
                                '<em>Si desea cambiar solo el estado del pedido sin modificar el pago, primero debe seleccionar una opción del menú "Estado del Pago".</em>',
                        'mensaje_tipo' => 'warning'
                    ];
                }
            }
        }

        if (!$cambio_pedido && !$cambio_pago) {
            return false; // No mostrar mensaje
        }
    
    // Validar que no se intente cambiar un pedido cancelado a otro estado (excepto mantener cancelado)
    if ($estado_pedido_actual === 'cancelado' && $nuevo_estado !== 'cancelado') {
        return ['mensaje' => 'No se puede cambiar el estado de un pedido cancelado', 'mensaje_tipo' => 'danger'];
    }
    
    // Validar que no se retroceda desde estados finales (en_viaje, completado)
    // en_viaje solo puede ir a completado (NO retrocesos)
    if ($estado_pedido_actual === 'en_viaje' && !in_array($nuevo_estado, ['en_viaje', 'completado'])) {
        return ['mensaje' => 'No se puede retroceder desde "En Viaje". Solo se permite avanzar a "Completado".', 'mensaje_tipo' => 'danger'];
    }
    
    // completado es terminal, no puede cambiar a ningún otro estado
    if ($estado_pedido_actual === 'completado' && $nuevo_estado !== 'completado') {
        return ['mensaje' => 'No se puede cambiar desde "Completado". Es un estado terminal y no admite cambios.', 'mensaje_tipo' => 'danger'];
    }

    // NOTA: No validar manualmente transiciones de pago aquí
    // validarTransicionPago() ya maneja todas las reglas (línea 196)
    // Eliminada validación inline duplicada que verificaba aprobado→rechazado

    // Validar transiciones de estado
    
        // ESTRATEGIA: Si se está cambiando el estado del pago, actualizar el pago primero
        // usando actualizarEstadoPagoConPedido() que maneja automáticamente:
        // - Actualización del estado del pago
        // - Cambios en el estado del pedido (si aplica)
        // - Gestión de stock (descuento al aprobar, reversión al rechazar/cancelar)
        // NOTA: actualizarEstadoPagoConPedido() maneja su propia transacción, no iniciar una aquí
        if ($cambiar_estado_pago && $pago_actual) {
            try {
                // Validar transición de pago antes de ejecutar
                validarTransicionPago($estado_pago_anterior_real, $estado_pago_final);

                $resultado_actualizacion = actualizarEstadoPagoConPedido($mysqli, $pago_actual['id_pago'], $estado_pago_final, $motivo_rechazo_final, $id_usuario);
                if (!$resultado_actualizacion) {
                    throw new Exception('Error al actualizar el estado del pago: la función retornó false');
                }
            } catch (Exception $e) {
                // Procesar error y retornar mensaje amigable
                $mensaje_error = _sanitizarMensajeError($e->getMessage());
                return _procesarErroresActualizacionPedidoPago($mensaje_error, $pedido_id);
            }
        
        // Obtener el estado actual del pedido después de actualizar el pago
        // porque actualizarEstadoPagoConPedido() puede haber cambiado el estado del pedido
        $pedido_actualizado = obtenerPedidoPorId($mysqli, $pedido_id);
        $estado_pedido_despues_pago = $pedido_actualizado ? normalizarEstado($pedido_actualizado['estado_pedido']) : '';
        
            $pedido_actualizado = obtenerPedidoPorId($mysqli, $pedido_id);
        $estado_pedido_despues_pago = $pedido_actualizado ? normalizarEstado($pedido_actualizado['estado_pedido']) : '';
        
        // Si el estado del pedido actual es diferente del estado deseado, intentar actualizarlo
        if ($estado_pedido_despues_pago !== $nuevo_estado) {
            // Si el nuevo estado deseado es 'cancelado', permitir la transición incluso desde un estado que no lo permite directamente
            // Esto es porque el pago fue rechazado/cancelado, y el pedido *debe* terminar en cancelado.
            // En este caso, no se llama a validarTransicionPedido ya que la cancelación es forzada por el pago.
            if ($nuevo_estado === 'cancelado' && in_array($estado_pago_final, ['rechazado', 'cancelado'])) {
                // Si el pedido ya está en cancelado, no hacer nada
                if ($estado_pedido_despues_pago !== 'cancelado') {
                    try {
                        if (!actualizarEstadoPedido($mysqli, $pedido_id, $nuevo_estado, $id_usuario)) {
                            throw new Exception('Error al actualizar el estado del pedido a cancelado');
                        }
                    } catch (Exception $e) {
                        return _procesarErroresActualizacionPedidoPago($e->getMessage(), $pedido_id);
                    }
                }
            } else {
                // Para otros cambios de estado del pedido (no es una cancelación forzada por pago)
                try {
                    // Validar transición de pedido desde el estado actual (después de la actualización del pago)
                    validarTransicionPedido($estado_pedido_despues_pago, $nuevo_estado);
                    
                    if (!actualizarEstadoPedidoConValidaciones($mysqli, $pedido_id, $nuevo_estado, $id_usuario)) {
                        throw new Exception('Error al actualizar el estado del pedido');
                    }
                } catch (Exception $e) {
                    return _procesarErroresActualizacionPedidoPago($e->getMessage(), $pedido_id);
                }
            }
        } else {
        }
            
            // Obtener el estado final real del pedido (puede haber cambiado)
            $pedido_final = obtenerPedidoPorId($mysqli, $pedido_id);
            $estado_pedido_final_real = $pedido_final ? normalizarEstado($pedido_final['estado_pedido']) : $nuevo_estado;
            
            // Actualización exitosa
            
            // Preparar mensaje de éxito
            require_once __DIR__ . '/estado_helpers.php';
            $info_adicional = [];
            if ($estado_pago_final === 'aprobado' && $estado_pago_anterior_real !== 'aprobado') {
                $info_adicional['stock_descontado'] = true;
                $info_adicional['pago_aprobado'] = true;
            }
            if (in_array($estado_pago_final, ['rechazado', 'cancelado']) && $estado_pago_anterior_real === 'aprobado') {
                $info_adicional['stock_restaurado'] = true;
            }
            if ($estado_pago_final === 'rechazado') {
                $info_adicional['pago_rechazado'] = true;
            }
            
            // Verificar si realmente hubo cambios antes de mostrar mensaje
            $cambio_pago = $estado_pago_anterior_real !== $estado_pago_final;
            $cambio_pedido = $estado_pedido_actual !== $estado_pedido_final_real;

            if (!$cambio_pago && !$cambio_pedido) {
                return false;
            }
            
            // Si se cambió el estado del pago, formatear mensaje de pago primero
            $mensaje_exito = '';
            if ($cambio_pago) {
                $mensaje_pago = formatearMensajeExito($estado_pago_anterior_real, $estado_pago_final, 'pago', $info_adicional);
                if ($cambio_pedido) {
                    $mensaje_pedido = formatearMensajeExito($estado_pedido_actual, $estado_pedido_final_real, 'pedido', []);
                    $mensaje_exito = $mensaje_pago . ' ' . $mensaje_pedido;
                } else {
                    $mensaje_exito = $mensaje_pago;
                }
            } else {
                $mensaje_exito = formatearMensajeExito($estado_pedido_actual, $estado_pedido_final_real, 'pedido', $info_adicional);
            }
            

            
            // === NOTIFICACIONES POR EMAIL ===
            // Obtener ID de usuario
            $id_usuario_notif = $pago_actual['id_usuario'];
            
            // 1. Notificar Pago Rechazado o Cancelado
            if (in_array($estado_pago_final, ['rechazado', 'cancelado'])) {
                $motivo_email = ($estado_pago_final === 'rechazado') ? $motivo_rechazo_final : null;
                enviar_email_pedido_cancelado_o_rechazado($pedido_id, $id_usuario_notif, $estado_pago_final, $motivo_email, $mysqli);
            }
            
            // 2. Notificar Pedido En Viaje (si cambió el pedido a "En Viaje")
            if ($estado_pedido_final_real === 'en_viaje' && $estado_pedido_actual !== 'en_viaje') {
                 enviar_email_pedido_en_viaje($pedido_id, $id_usuario_notif, $codigo_seguimiento, $empresa_envio, $mysqli);
            }
            
            return ['mensaje' => $mensaje_exito, 'mensaje_tipo' => 'success'];
        }
        
        // Si no se está cambiando el estado del pago, solo actualizar el estado del pedido
        // actualizarEstadoPedidoConValidaciones() maneja automáticamente la reversión de stock
        // cuando se cancela el pedido
        // NOTA: actualizarEstadoPedidoConValidaciones() maneja su propia transacción
        if ($estado_pedido_actual === $nuevo_estado_norm) {
            return false;
        }

        try {
            // Validar transición de pedido antes de ejecutar
            validarTransicionPedido($estado_pedido_actual, $nuevo_estado);

            if (!actualizarEstadoPedidoConValidaciones($mysqli, $pedido_id, $nuevo_estado, $id_usuario)) {
                throw new Exception('Error al actualizar el pedido');
            }
        } catch (Exception $e) {
            $mensaje_error = _sanitizarMensajeError($e->getMessage());
            return _procesarErroresActualizacionPedidoPago($mensaje_error, $pedido_id);
        }
        
        // Si se canceló el pedido y existe un pago, cancelar el pago también si no está cancelado
        // IMPORTANTE: Cancelación forzada por cancelación de pedido - se cancela SIEMPRE
        // incluso si el pago está en recorrido activo (aprobado, pendiente_aprobacion)
        if ($nuevo_estado === 'cancelado' && $pago_actual && $estado_pago_anterior_real !== 'cancelado' && $estado_pago_anterior_real !== 'rechazado') {
            try {
                // El stock ya fue restaurado por actualizarEstadoPedidoConValidaciones() cuando canceló el pedido
                // Solo necesitamos actualizar el estado del pago sin afectar el pedido
                // NOTA: actualizarEstadoPago() maneja su propia transacción
                if (!actualizarEstadoPago($mysqli, $pago_actual['id_pago'], 'cancelado', null, null)) {
                    throw new Exception('Error al cancelar el pago');
                }
            } catch (Exception $e) {
                // No fallar la operación completa si solo falla cancelar el pago
                // El pedido ya fue cancelado exitosamente
            }
        }
        
        // Actualización exitosa
        
        // Cargar función helper para formatear mensajes de éxito
        require_once __DIR__ . '/estado_helpers.php';
        
        // Preparar información adicional para el mensaje
        $info_adicional = [];
        
        // Determinar si se restauró stock (pedido cancelado)
        if ($nuevo_estado === 'cancelado') {
            $info_adicional['stock_restaurado'] = true;
        }
        

        // Formatear mensaje de éxito con transición de estados
        $mensaje_exito = formatearMensajeExito($estado_pedido_actual, $nuevo_estado, 'pedido', $info_adicional);

        // === NOTIFICACIONES POR EMAIL ===
        $id_usuario_notif = $pedido_actual['id_usuario'];

        // 1. Notificar Pedido Cancelado
        if ($nuevo_estado === 'cancelado') {
             enviar_email_pedido_cancelado_o_rechazado($pedido_id, $id_usuario_notif, 'cancelado', null, $mysqli);
        }

        // 2. Notificar Pedido En Viaje
        if ($nuevo_estado === 'en_viaje') {
             enviar_email_pedido_en_viaje($pedido_id, $id_usuario_notif, $codigo_seguimiento, $empresa_envio, $mysqli);
        }

        return ['mensaje' => $mensaje_exito, 'mensaje_tipo' => 'success'];
}

/**
 * Procesa la aprobación de un pago
 * 
 * Esta función aprueba un pago y descuenta el stock automáticamente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @param int $id_usuario ID del usuario ventas que realiza la acción
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarAprobacionPago($mysqli, $post, $id_usuario) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['aprobar_pago'])) {
        return ['mensaje' => 'La solicitud no es válida. Por favor, intenta nuevamente.', 'mensaje_tipo' => 'danger'];
    }
    
    // Cargar funciones necesarias
    require_once __DIR__ . '/queries_helper.php';
    try {
        cargarArchivoQueries('pago_queries', __DIR__ . '/queries');
    } catch (Exception $e) {
        error_log("ERROR: No se pudo cargar pago_queries.php - " . $e->getMessage());
        die("Error crítico: Archivo de consultas de pago no encontrado. Por favor, contacta al administrador.");
    }
    
    $pago_id = intval($post['pago_id'] ?? 0);

    if ($pago_id <= 0) {
        return ['mensaje' => 'ID de pago inválido', 'mensaje_tipo' => 'danger'];
    }

    try {
        $resultado = aprobarPago($mysqli, $pago_id, $id_usuario);

        if ($resultado) {
            return ['mensaje' => 'Pago aprobado correctamente. Stock validado y descontado.', 'mensaje_tipo' => 'success'];
        } else {
            return ['mensaje' => 'Error al aprobar el pago. Verifique el stock disponible.', 'mensaje_tipo' => 'danger'];
        }
    } catch (Exception $e) {
        $mensaje_error = _sanitizarMensajeError($e->getMessage());

        // Verificar si el error es por stock insuficiente
        if (strpos($mensaje_error, 'STOCK_INSUFICIENTE') !== false) {
            // Remover prefijo técnico para mensaje más limpio
            $mensaje_limpio = str_replace('STOCK_INSUFICIENTE: ', '', $mensaje_error);

            // Detectar tipo específico de error y personalizar mensaje
            if (strpos($mensaje_limpio, 'está inactiva') !== false || strpos($mensaje_limpio, 'está inactivo') !== false) {
                // Producto o variante inactiva
                return ['mensaje' => "No se puede aprobar el pago: $mensaje_limpio Los productos deben estar activos para procesar pagos. Reactiva el producto desde el panel de Marketing.", 'mensaje_tipo' => 'warning'];
            } elseif (strpos($mensaje_limpio, 'Stock insuficiente') !== false) {
                // Stock insuficiente
                return ['mensaje' => "No se puede aprobar el pago: $mensaje_limpio Verifica el stock disponible en el panel de Marketing o contacta al cliente para ajustar la cantidad.", 'mensaje_tipo' => 'warning'];
            } else {
                // Otro error de stock
                return ['mensaje' => "No se puede aprobar el pago: $mensaje_limpio", 'mensaje_tipo' => 'warning'];
            }
        } else {
            // Error que no es de stock - procesar con función centralizada
            $resultado_error = _procesarErroresActualizacionPedidoPago($mensaje_error, 0);
            return $resultado_error;
        }
    }
}

/**
 * Procesa el rechazo de un pago
 * 
 * Esta función rechaza un pago y restaura el stock si había sido descontado.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @param int $id_usuario ID del usuario ventas que realiza la acción
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarRechazoPago($mysqli, $post, $id_usuario) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['rechazar_pago'])) {
        return false;
    }
    
    // Cargar funciones necesarias
    require_once __DIR__ . '/queries_helper.php';
    try {
        cargarArchivoQueries('pago_queries', __DIR__ . '/queries');
    } catch (Exception $e) {
        error_log("ERROR: No se pudo cargar pago_queries.php - " . $e->getMessage());
        die("Error crítico: Archivo de consultas de pago no encontrado. Por favor, contacta al administrador.");
    }
    
    $pago_id = intval($post['pago_id'] ?? 0);
    $motivo_rechazo_raw = trim($post['motivo_rechazo'] ?? '');
    
    if ($pago_id <= 0) {
        return ['mensaje' => 'ID de pago inválido', 'mensaje_tipo' => 'danger'];
    }
    
    // Validar motivo de rechazo usando función centralizada
    require_once __DIR__ . '/validation_functions.php';
    $validacion_motivo = validarObservaciones($motivo_rechazo_raw, 500, 'motivo de rechazo');
    if (!$validacion_motivo['valido']) {
        return ['mensaje' => $validacion_motivo['error'], 'mensaje_tipo' => 'danger'];
    }
    $motivo_rechazo = $validacion_motivo['valor'];
    
    try {
        if (rechazarPago($mysqli, $pago_id, $id_usuario, $motivo_rechazo)) {
            return ['mensaje' => 'Pago rechazado correctamente. Stock restaurado automáticamente si había sido descontado.', 'mensaje_tipo' => 'success'];
        } else {
            return ['mensaje' => 'Error al rechazar el pago', 'mensaje_tipo' => 'danger'];
        }
    } catch (Exception $e) {
        // Procesar error usando función centralizada
        $mensaje_error = _sanitizarMensajeError($e->getMessage());
        $resultado_error = _procesarErroresActualizacionPedidoPago($mensaje_error, 0);
        return $resultado_error;
    }
}

/**
 * Valida el nombre de un método de pago según los requisitos de la base de datos
 * 
 * Requisitos según database_estructura.sql y diccionario_datos_tiendasedaylino.md:
 * - Longitud: 3-100 caracteres
 * - Caracteres permitidos: A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, 0-9, espacios, guiones (-)
 * 
 * @param string $nombre Nombre a validar
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] si hay error, o false si es válido
 */
function validarNombreMetodoPago($nombre) {
    // Validar que no esté vacío
    if (empty($nombre)) {
        return ['mensaje' => 'El nombre del método de pago es obligatorio', 'mensaje_tipo' => 'danger'];
    }
    
    // Validar longitud mínima (3 caracteres)
    if (mb_strlen($nombre) < 3) {
        return ['mensaje' => 'El nombre debe tener al menos 3 caracteres', 'mensaje_tipo' => 'danger'];
    }
    
    // Validar longitud máxima (100 caracteres)
    if (mb_strlen($nombre) > 100) {
        return ['mensaje' => 'El nombre no puede exceder 100 caracteres', 'mensaje_tipo' => 'danger'];
    }
    
    // Validar caracteres permitidos según diccionario: letras (con acentos), números, espacios, guiones
    // Patrón: letras (incluyendo acentos), números, espacios y guiones
    if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ0-9\s\-]+$/', $nombre)) {
        return ['mensaje' => 'El nombre solo puede contener letras (incluyendo acentos), números, espacios y guiones', 'mensaje_tipo' => 'danger'];
    }
    
    // Rechazar strings que sean solo guiones o caracteres especiales
    // Verificar que tenga al menos una letra o número
    if (!preg_match('/[A-Za-z0-9]/', $nombre)) {
        return ['mensaje' => 'El nombre debe contener al menos una letra o número', 'mensaje_tipo' => 'danger'];
    }
    
    return false; // Válido
}

/**
 * Valida la descripción de un método de pago según los requisitos de la base de datos
 * 
 * Requisitos según database_estructura.sql y diccionario_datos_tiendasedaylino.md:
 * - Longitud: 0-255 caracteres (opcional)
 * - Caracteres permitidos: A-Z, a-z (con tildes y diéresis: á, é, í, ó, ú, ñ, ü), 0-9, espacios, puntos (.), guiones (-), comas (,), dos puntos (:), punto y coma (;)
 * - Bloquea: < > { } [ ] | \ / &
 * 
 * @param string $descripcion Descripción a validar
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] si hay error, o false si es válido
 */
function validarDescripcionMetodoPago($descripcion) {
    // Si está vacía, es válida (es opcional)
    if (empty($descripcion)) {
        return false; // Válido (opcional)
    }
    
    // Validar longitud máxima (255 caracteres)
    if (mb_strlen($descripcion) > 255) {
        return ['mensaje' => 'La descripción no puede exceder 255 caracteres', 'mensaje_tipo' => 'danger'];
    }
    
    // VALIDACIÓN 1: Bloquear caracteres peligrosos según diccionario: < > { } [ ] | \ / &
    if (preg_match('/[<>{}\[\]|\\\\\/&]/', $descripcion)) {
        return ['mensaje' => 'La descripción contiene caracteres no permitidos. No se permiten los símbolos: < > { } [ ] | \\ / &', 'mensaje_tipo' => 'danger'];
    }
    
    // VALIDACIÓN 2: Validar caracteres permitidos: letras (con tildes y diéresis), números, espacios, puntos, guiones, comas, dos puntos, punto y coma
    if (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9\s\.,\:\-\;]+$/u', $descripcion)) {
        return ['mensaje' => 'La descripción solo puede contener letras (incluyendo tildes y diéresis), números, espacios, puntos, comas, dos puntos, punto y coma y guiones', 'mensaje_tipo' => 'danger'];
    }
    
    return false; // Válido
}

/**
 * Procesa la agregación de un nuevo método de pago
 * 
 * Esta función valida los datos y crea un nuevo método de pago.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarAgregarMetodoPago($mysqli, $post) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['agregar_metodo_pago'])) {
        return false;
    }
    
    // Cargar funciones necesarias
    require_once __DIR__ . '/queries_helper.php';
    try {
        cargarArchivoQueries('forma_pago_queries', __DIR__ . '/queries');
    } catch (Exception $e) {
        error_log("ERROR: No se pudo cargar forma_pago_queries.php - " . $e->getMessage());
        die("Error crítico: Archivo de consultas de forma de pago no encontrado. Por favor, contacta al administrador.");
    }
    
    $nombre = trim($post['nombre_metodo'] ?? '');
    $descripcion = trim($post['descripcion_metodo'] ?? '');
    
    // Validar nombre
    $error_nombre = validarNombreMetodoPago($nombre);
    if ($error_nombre !== false) {
        return $error_nombre;
    }
    
    // Validar descripción
    $error_descripcion = validarDescripcionMetodoPago($descripcion);
    if ($error_descripcion !== false) {
        return $error_descripcion;
    }
    
    // Convertir descripción vacía a null
    $descripcion = empty($descripcion) ? null : $descripcion;
    
    // Usar función centralizada para crear forma de pago
    $id_forma_pago = crearFormaPago($mysqli, $nombre, $descripcion);
    
    if ($id_forma_pago) {
        return ['mensaje' => 'Método de pago agregado correctamente', 'mensaje_tipo' => 'success'];
    } else {
        return ['mensaje' => 'Error al agregar el método de pago', 'mensaje_tipo' => 'danger'];
    }
}

/**
 * Procesa la actualización de un método de pago
 * 
 * Esta función valida los datos y actualiza un método de pago existente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarActualizarMetodoPago($mysqli, $post) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['actualizar_metodo_pago'])) {
        return false;
    }
    
    // Cargar funciones necesarias
    require_once __DIR__ . '/queries_helper.php';
    try {
        cargarArchivoQueries('forma_pago_queries', __DIR__ . '/queries');
    } catch (Exception $e) {
        error_log("ERROR: No se pudo cargar forma_pago_queries.php - " . $e->getMessage());
        die("Error crítico: Archivo de consultas de forma de pago no encontrado. Por favor, contacta al administrador.");
    }
    
    $id_forma_pago = intval($post['id_forma_pago'] ?? 0);
    $nombre = trim($post['nombre_metodo'] ?? '');
    $descripcion = trim($post['descripcion_metodo'] ?? '');
    
    // Validar ID
    if ($id_forma_pago <= 0) {
        return ['mensaje' => 'ID de método de pago inválido', 'mensaje_tipo' => 'danger'];
    }
    
    // Validar nombre
    $error_nombre = validarNombreMetodoPago($nombre);
    if ($error_nombre !== false) {
        return $error_nombre;
    }
    
    // Validar descripción
    $error_descripcion = validarDescripcionMetodoPago($descripcion);
    if ($error_descripcion !== false) {
        return $error_descripcion;
    }
    
    // Convertir descripción vacía a null
    $descripcion = empty($descripcion) ? null : $descripcion;
    
    // Usar función centralizada para actualizar forma de pago
    if (actualizarFormaPago($mysqli, $id_forma_pago, $nombre, $descripcion)) {
        return ['mensaje' => 'Método de pago actualizado correctamente', 'mensaje_tipo' => 'success'];
    } else {
        return ['mensaje' => 'Error al actualizar el método de pago', 'mensaje_tipo' => 'danger'];
    }
}

/**
 * Procesa la eliminación física de un método de pago
 * 
 * Esta función valida que el método no esté en uso y lo elimina físicamente
 * de la base de datos. Solo se elimina si no tiene pagos asociados.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarEliminarMetodoPago($mysqli, $post) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['eliminar_metodo_pago'])) {
        return false;
    }
    
    // Cargar funciones necesarias
    require_once __DIR__ . '/queries_helper.php';
    try {
        cargarArchivoQueries('forma_pago_queries', __DIR__ . '/queries');
    } catch (Exception $e) {
        error_log("ERROR: No se pudo cargar forma_pago_queries.php - " . $e->getMessage());
        die("Error crítico: Archivo de consultas de forma de pago no encontrado. Por favor, contacta al administrador.");
    }
    
    $id_forma_pago = intval($post['id_forma_pago'] ?? 0);
    
    if ($id_forma_pago <= 0) {
        return ['mensaje' => 'ID de método de pago inválido', 'mensaje_tipo' => 'danger'];
    }
    
    // Verificar si el método de pago está en uso usando función centralizada
    $total_usos = contarUsosFormaPago($mysqli, $id_forma_pago);
    
    if ($total_usos > 0) {
        return ['mensaje' => 'No se puede eliminar el método de pago porque está siendo utilizado en ' . $total_usos . ' pago(s)', 'mensaje_tipo' => 'danger'];
    }
    
    // Eliminación física usando función centralizada
    if (eliminarFormaPago($mysqli, $id_forma_pago)) {
        return ['mensaje' => 'Método de pago eliminado correctamente', 'mensaje_tipo' => 'success'];
    } else {
        return ['mensaje' => 'Error al eliminar el método de pago', 'mensaje_tipo' => 'danger'];
    }
}

/**
 * Procesa el cambio de estado activo/inactivo de un método de pago
 * 
 * Esta función alterna el estado activo de un método de pago, permitiendo
 * activarlo o desactivarlo sin eliminarlo de la base de datos.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarToggleActivoMetodoPago($mysqli, $post) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['toggle_activo_metodo_pago'])) {
        return false;
    }
    
    // Cargar funciones necesarias
    require_once __DIR__ . '/queries_helper.php';
    try {
        cargarArchivoQueries('forma_pago_queries', __DIR__ . '/queries');
    } catch (Exception $e) {
        error_log("ERROR: No se pudo cargar forma_pago_queries.php - " . $e->getMessage());
        die("Error crítico: Archivo de consultas de forma de pago no encontrado. Por favor, contacta al administrador.");
    }
    
    $id_forma_pago = intval($post['id_forma_pago'] ?? 0);
    
    if ($id_forma_pago <= 0) {
        return ['mensaje' => 'ID de método de pago inválido', 'mensaje_tipo' => 'danger'];
    }
    
    // Alternar estado usando función centralizada
    $nuevo_estado = toggleActivoFormaPago($mysqli, $id_forma_pago);
    
    if ($nuevo_estado !== false) {
        $estado_texto = $nuevo_estado === 1 ? 'activado' : 'desactivado';
        return ['mensaje' => 'Método de pago ' . $estado_texto . ' correctamente', 'mensaje_tipo' => 'success'];
    } else {
        return ['mensaje' => 'Error al cambiar el estado del método de pago', 'mensaje_tipo' => 'danger'];
    }
}

/**
 * Construye una URL de redirección con parámetros de consulta preservados
 * 
 * Esta función construye una URL de redirección preservando los parámetros
 * de consulta relevantes como 'mostrar_inactivos' y 'limite' de la petición actual.
 * 
 * @param string $base_url URL base para la redirección (ej: 'ventas.php')
 * @param array|null $params_adicionales Parámetros adicionales opcionales a incluir
 * @return string URL completa con parámetros de consulta
 */
function construirRedirectUrl($base_url, $params_adicionales = null) {
    $params = [];
    
    // Preservar parámetro tab si está presente (para mantener pestaña activa)
    if (isset($_GET['tab']) && !empty($_GET['tab'])) {
        $params[] = 'tab=' . urlencode($_GET['tab']);
    }
    
    // Preservar parámetro mostrar_inactivos si está presente
    if (isset($_GET['mostrar_inactivos']) && $_GET['mostrar_inactivos'] == '1') {
        $params[] = 'mostrar_inactivos=1';
    }
    
    // Preservar parámetro limite si está presente y es válido
    if (isset($_GET['limite']) && in_array($_GET['limite'], ['10', '50', 'TODOS'])) {
        $params[] = 'limite=' . urlencode($_GET['limite']);
    }
    
    // Preservar parámetro mostrar_metodos_inactivos si está presente
    if (isset($_GET['mostrar_metodos_inactivos']) && $_GET['mostrar_metodos_inactivos'] == '1') {
        $params[] = 'mostrar_metodos_inactivos=1';
    }
    
    // Agregar parámetros adicionales si se proporcionan
    // Si un parámetro adicional tiene la misma clave que uno preservado, el adicional tiene prioridad
    if (is_array($params_adicionales) && !empty($params_adicionales)) {
        foreach ($params_adicionales as $key => $value) {
            // Remover parámetro preservado si existe con la misma clave
            $key_encoded = urlencode($key);
            $params = array_filter($params, function($param) use ($key_encoded) {
                return strpos($param, $key_encoded . '=') !== 0;
            });
            // Agregar parámetro adicional
            $params[] = $key_encoded . '=' . urlencode($value);
        }
    }
    
    // Construir URL final
    $redirect_url = $base_url;
    if (!empty($params)) {
        $redirect_url .= '?' . implode('&', $params);
    }
    
    return $redirect_url;
}


