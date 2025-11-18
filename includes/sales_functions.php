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
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */


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
    $pedido_queries_path = __DIR__ . '/queries/pedido_queries.php';
    if (!file_exists($pedido_queries_path)) {
        error_log("ERROR: No se pudo encontrar pedido_queries.php en " . $pedido_queries_path);
        die("Error crítico: Archivo de consultas de pedido no encontrado. Por favor, contacta al administrador.");
    }
    require_once $pedido_queries_path;
    
    $pago_queries_path = __DIR__ . '/queries/pago_queries.php';
    if (!file_exists($pago_queries_path)) {
        error_log("ERROR: No se pudo encontrar pago_queries.php en " . $pago_queries_path);
        die("Error crítico: Archivo de consultas de pago no encontrado. Por favor, contacta al administrador.");
    }
    require_once $pago_queries_path;
    
    // Extraer y normalizar datos del formulario
    $pedido_id = intval($post['pedido_id'] ?? 0);
    $nuevo_estado = trim(strtolower($post['nuevo_estado'] ?? ''));
    $nuevo_estado_pago = trim(strtolower($post['nuevo_estado_pago'] ?? ''));
    $estado_anterior = trim(strtolower($post['estado_anterior'] ?? ''));
    $estado_pago_anterior = trim(strtolower($post['estado_pago_anterior'] ?? ''));
    
    // Campos adicionales del pago
    // Nota: monto_pago y numero_transaccion son solo lectura (llenados por el cliente)
    // Solo se procesa el motivo de rechazo si se rechaza el pago
    $motivo_rechazo = trim($post['motivo_rechazo'] ?? '');
    
    // Validar estados permitidos (según ENUM de la base de datos)
    $estados_pedido_validos = ['pendiente', 'preparacion', 'en_viaje', 'completado', 'devolucion', 'cancelado'];
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
    $estado_pago_anterior_real = $pago_actual ? strtolower(trim($pago_actual['estado_pago'])) : '';
    
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
        $motivo_rechazo_final = $motivo_rechazo;
    }
    
    // Iniciar transacción para manejar ambos cambios
    $mysqli->begin_transaction();
    
    try {
        // ESTRATEGIA: Si se está cambiando el estado del pago, actualizar el pago primero
        // usando actualizarEstadoPagoConPedido() que maneja automáticamente:
        // - Actualización del estado del pago
        // - Cambios en el estado del pedido (si aplica)
        // - Gestión de stock (descuento al aprobar, reversión al rechazar/cancelar)
        if ($cambiar_estado_pago && $pago_actual) {
            if (!actualizarEstadoPagoConPedido($mysqli, $pago_actual['id_pago'], $estado_pago_final, $motivo_rechazo_final, $id_usuario)) {
                throw new Exception('Error al actualizar el estado del pago');
            }
            
            // Obtener el estado actual del pedido después de actualizar el pago
            // porque actualizarEstadoPagoConPedido() puede haber cambiado el estado del pedido
            $pedido_actualizado = obtenerPedidoPorId($mysqli, $pedido_id);
            $estado_pedido_despues_pago = $pedido_actualizado ? strtolower(trim($pedido_actualizado['estado_pedido'])) : '';
            
            // Si el estado del pedido cambió por la actualización del pago y es diferente al deseado,
            // actualizar el estado del pedido solo si es necesario
            // Nota: Si el pago se rechazó/canceló, el pedido ya fue cancelado por actualizarEstadoPagoConPedido()
            if ($estado_pedido_despues_pago !== $nuevo_estado) {
                // Solo actualizar si el nuevo estado es válido para el estado actual
                // Por ejemplo, no se puede cambiar de cancelado a otro estado
                if ($estado_pedido_despues_pago !== 'cancelado' || $nuevo_estado === 'cancelado') {
                    if (!actualizarEstadoPedidoConValidaciones($mysqli, $pedido_id, $nuevo_estado, $id_usuario)) {
                        throw new Exception('Error al actualizar el estado del pedido');
                    }
                }
            }
        } else {
            // Si no se está cambiando el estado del pago, solo actualizar el estado del pedido
            // actualizarEstadoPedidoConValidaciones() maneja automáticamente la reversión de stock
            // cuando se cancela el pedido
            if (!actualizarEstadoPedidoConValidaciones($mysqli, $pedido_id, $nuevo_estado, $id_usuario)) {
                throw new Exception('Error al actualizar el pedido');
            }
            
            // Si se canceló el pedido y existe un pago, cancelar el pago también si no está cancelado
            if ($nuevo_estado === 'cancelado' && $pago_actual && $estado_pago_anterior_real !== 'cancelado') {
                // Solo cancelar si el estado anterior permite cancelación
                if (in_array($estado_pago_anterior_real, ['pendiente', 'pendiente_aprobacion', 'aprobado'])) {
                    // Usar actualizarEstadoPagoConPedido() para cancelar el pago
                    // Esto maneja automáticamente la reversión de stock si el pago estaba aprobado
                    if (!actualizarEstadoPagoConPedido($mysqli, $pago_actual['id_pago'], 'cancelado', null, $id_usuario)) {
                        throw new Exception('Error al cancelar el pago');
                    }
                }
            }
        }
        
        $mysqli->commit();
        
        // Mensaje de éxito
        $mensaje_exito = 'Pedido y pago actualizados correctamente';
        if ($nuevo_estado === 'cancelado') {
            $mensaje_exito = 'Pedido cancelado correctamente. Stock restaurado automáticamente.';
        } elseif ($cambiar_estado_pago && $estado_pago_final === 'aprobado' && $estado_pago_anterior_real !== 'aprobado') {
            $mensaje_exito = 'Pedido y pago actualizados correctamente. Stock descontado automáticamente.';
        } elseif ($cambiar_estado_pago && in_array($estado_pago_final, ['rechazado', 'cancelado']) && $estado_pago_anterior_real === 'aprobado') {
            $mensaje_exito = 'Pago actualizado correctamente. Stock restaurado automáticamente.';
        }
        
        return ['mensaje' => $mensaje_exito, 'mensaje_tipo' => 'success'];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        return ['mensaje' => 'Error al actualizar pedido y pago: ' . $e->getMessage(), 'mensaje_tipo' => 'danger'];
    }
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
        return false;
    }
    
    // Cargar funciones necesarias
    $pago_queries_path = __DIR__ . '/queries/pago_queries.php';
    if (!file_exists($pago_queries_path)) {
        error_log("ERROR: No se pudo encontrar pago_queries.php en " . $pago_queries_path);
        die("Error crítico: Archivo de consultas de pago no encontrado. Por favor, contacta al administrador.");
    }
    require_once $pago_queries_path;
    
    $pago_id = intval($post['pago_id'] ?? 0);
    
    if ($pago_id <= 0) {
        return ['mensaje' => 'ID de pago inválido', 'mensaje_tipo' => 'danger'];
    }
    
    try {
        // NOTA: Se puede usar actualizarEstadoPagoConPedido($mysqli, $pago_id, 'aprobado', null, $id_usuario)
        // de pago_queries.php para una implementación más centralizada de las reglas de negocio según el plan.
        if (aprobarPago($mysqli, $pago_id, $id_usuario)) {
            return ['mensaje' => 'Pago aprobado correctamente. Stock descontado automáticamente.', 'mensaje_tipo' => 'success'];
        } else {
            return ['mensaje' => 'Error al aprobar el pago. Verifique el stock disponible.', 'mensaje_tipo' => 'danger'];
        }
    } catch (Exception $e) {
        // Verificar si el error es por stock insuficiente
        if (strpos($e->getMessage(), 'STOCK_INSUFICIENTE') !== false) {
            return ['mensaje' => 'No hay stock disponible para aprobar este pago. Verifique el stock de los productos del pedido.', 'mensaje_tipo' => 'warning'];
        } else {
            return ['mensaje' => 'Error al aprobar el pago: ' . $e->getMessage(), 'mensaje_tipo' => 'danger'];
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
    $pago_queries_path = __DIR__ . '/queries/pago_queries.php';
    if (!file_exists($pago_queries_path)) {
        error_log("ERROR: No se pudo encontrar pago_queries.php en " . $pago_queries_path);
        die("Error crítico: Archivo de consultas de pago no encontrado. Por favor, contacta al administrador.");
    }
    require_once $pago_queries_path;
    
    $pago_id = intval($post['pago_id'] ?? 0);
    $motivo_rechazo = trim($post['motivo_rechazo'] ?? '');
    
    if ($pago_id <= 0) {
        return ['mensaje' => 'ID de pago inválido', 'mensaje_tipo' => 'danger'];
    }
    
    // NOTA: Se puede usar actualizarEstadoPagoConPedido($mysqli, $pago_id, 'rechazado', $motivo_rechazo, $id_usuario)
    // de pago_queries.php para una implementación más centralizada de las reglas de negocio según el plan.
    if (rechazarPago($mysqli, $pago_id, $id_usuario, $motivo_rechazo)) {
        return ['mensaje' => 'Pago rechazado correctamente. Stock restaurado automáticamente si había sido descontado.', 'mensaje_tipo' => 'success'];
    } else {
        return ['mensaje' => 'Error al rechazar el pago', 'mensaje_tipo' => 'danger'];
    }
}

/**
 * Valida el nombre de un método de pago según los requisitos de la base de datos
 * 
 * Requisitos según database_estructura.sql y diccionario_datos_tiendasedaylino.md:
 * - Longitud: 3-100 caracteres
 * - Caracteres permitidos: A-Z, a-z, 0-9, espacios, guiones (-)
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
    
    // Validar caracteres permitidos: letras (A-Z, a-z), números (0-9), espacios, guiones (-)
    // Patrón: solo letras, números, espacios y guiones
    if (!preg_match('/^[A-Za-z0-9\s\-]+$/', $nombre)) {
        return ['mensaje' => 'El nombre solo puede contener letras, números, espacios y guiones', 'mensaje_tipo' => 'danger'];
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
 * - Caracteres permitidos: A-Z, a-z (con tildes y diéresis: á, é, í, ó, ú, ñ, ü), 0-9, espacios, puntos (.), guiones (-), comas (,), dos puntos (:), comillas simples (')
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
    
    // Validar caracteres permitidos: letras (A-Z, a-z con tildes y diéresis), números (0-9), espacios, puntos (.), guiones (-), comas (,), dos puntos (:), comillas simples (')
    if (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9\s\.,\:\-\']+$/u', $descripcion)) {
        return ['mensaje' => 'La descripción solo puede contener letras (incluyendo tildes y diéresis), números, espacios, puntos, comas, dos puntos, guiones y comillas simples', 'mensaje_tipo' => 'danger'];
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
    $forma_pago_queries_path = __DIR__ . '/queries/forma_pago_queries.php';
    if (!file_exists($forma_pago_queries_path)) {
        error_log("ERROR: No se pudo encontrar forma_pago_queries.php en " . $forma_pago_queries_path);
        die("Error crítico: Archivo de consultas de forma de pago no encontrado. Por favor, contacta al administrador.");
    }
    require_once $forma_pago_queries_path;
    
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
    $forma_pago_queries_path = __DIR__ . '/queries/forma_pago_queries.php';
    if (!file_exists($forma_pago_queries_path)) {
        error_log("ERROR: No se pudo encontrar forma_pago_queries.php en " . $forma_pago_queries_path);
        die("Error crítico: Archivo de consultas de forma de pago no encontrado. Por favor, contacta al administrador.");
    }
    require_once $forma_pago_queries_path;
    
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
 * Procesa la eliminación (desactivación) de un método de pago
 * 
 * Esta función valida que el método no esté en uso y lo desactiva.
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
    $forma_pago_queries_path = __DIR__ . '/queries/forma_pago_queries.php';
    if (!file_exists($forma_pago_queries_path)) {
        error_log("ERROR: No se pudo encontrar forma_pago_queries.php en " . $forma_pago_queries_path);
        die("Error crítico: Archivo de consultas de forma de pago no encontrado. Por favor, contacta al administrador.");
    }
    require_once $forma_pago_queries_path;
    
    $id_forma_pago = intval($post['id_forma_pago'] ?? 0);
    
    if ($id_forma_pago <= 0) {
        return ['mensaje' => 'ID de método de pago inválido', 'mensaje_tipo' => 'danger'];
    }
    
    // Verificar si el método de pago está en uso usando función centralizada
    $total_usos = contarUsosFormaPago($mysqli, $id_forma_pago);
    
    if ($total_usos > 0) {
        return ['mensaje' => 'No se puede eliminar el método de pago porque está siendo utilizado en ' . $total_usos . ' pago(s)', 'mensaje_tipo' => 'danger'];
    }
    
    // Soft delete usando función centralizada
    if (desactivarFormaPago($mysqli, $id_forma_pago)) {
        return ['mensaje' => 'Método de pago desactivado correctamente', 'mensaje_tipo' => 'success'];
    } else {
        return ['mensaje' => 'Error al desactivar el método de pago', 'mensaje_tipo' => 'danger'];
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
    
    // Preservar parámetro mostrar_inactivos si está presente
    if (isset($_GET['mostrar_inactivos']) && $_GET['mostrar_inactivos'] == '1') {
        $params[] = 'mostrar_inactivos=1';
    }
    
    // Preservar parámetro limite si está presente y es válido
    if (isset($_GET['limite']) && in_array($_GET['limite'], ['10', '50', 'TODOS'])) {
        $params[] = 'limite=' . urlencode($_GET['limite']);
    }
    
    // Agregar parámetros adicionales si se proporcionan
    if (is_array($params_adicionales) && !empty($params_adicionales)) {
        foreach ($params_adicionales as $key => $value) {
            $params[] = urlencode($key) . '=' . urlencode($value);
        }
    }
    
    // Construir URL final
    $redirect_url = $base_url;
    if (!empty($params)) {
        $redirect_url .= '?' . implode('&', $params);
    }
    
    return $redirect_url;
}


