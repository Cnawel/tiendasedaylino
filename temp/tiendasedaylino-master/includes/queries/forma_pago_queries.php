<?php
/**
 * ========================================================================
 * CONSULTAS SQL DE FORMAS DE PAGO - Tienda Seda y Lino
 * ========================================================================
 * Archivo centralizado con todas las consultas relacionadas a formas de pago
 * 
 * Uso:
 *   require_once __DIR__ . '/includes/queries/forma_pago_queries.php';
 *   $formas_pago = obtenerFormasPago($mysqli);
 * ========================================================================
 */

/**
 * Obtiene todas las formas de pago disponibles y activas
 * 
 * Esta función retorna todas las formas de pago que tienen activo = 1,
 * ordenadas por id_forma_pago. Útil para mostrar opciones de pago
 * en formularios de checkout o administración.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array asociativo con todas las columnas de Forma_Pagos (id_forma_pago, nombre, descripcion, activo)
 */
function obtenerFormasPago($mysqli) {
    $sql = "SELECT * FROM Forma_Pagos WHERE activo = 1 ORDER BY id_forma_pago";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        // Registrar error detallado para debugging
        error_log("ERROR obtenerFormasPago - prepare falló: " . $mysqli->error . " (Errno: " . $mysqli->errno . ")");
        error_log("SQL: " . $sql);
        return [];
    }
    
    if (!$stmt->execute()) {
        // Registrar error si execute falla
        error_log("ERROR obtenerFormasPago - execute falló: " . $stmt->error . " (Errno: " . $stmt->errno . ")");
        $stmt->close();
        return [];
    }
    
    $result = $stmt->get_result();
    
    $formas_pago = [];
    while ($row = $result->fetch_assoc()) {
        $formas_pago[] = $row;
    }
    
    $stmt->close();
    return $formas_pago;
}

/**
 * Obtiene una forma de pago por su ID
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_forma_pago ID de la forma de pago
 * @return array|null Array con datos de la forma de pago o null si no existe
 */
function obtenerFormaPagoPorId($mysqli, $id_forma_pago) {
    $sql = "SELECT nombre, descripcion FROM Forma_Pagos WHERE id_forma_pago = ? AND activo = 1 LIMIT 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $id_forma_pago);
    $stmt->execute();
    $result = $stmt->get_result();
    $forma_pago = $result->fetch_assoc();
    $stmt->close();
    
    return $forma_pago;
}

/**
 * Obtiene todas las formas de pago activas con sus IDs y descripciones (para elementos select)
 * 
 * Esta función retorna solo los campos necesarios para elementos HTML select:
 * id_forma_pago (value), nombre (texto visible), y descripcion (texto adicional).
 * Útil para generar dropdowns de formas de pago en formularios.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array asociativo de formas de pago con id_forma_pago, nombre, descripcion
 */
function obtenerFormasPagoSelect($mysqli) {
    $sql = "SELECT id_forma_pago, nombre, descripcion FROM Forma_Pagos WHERE activo = 1 ORDER BY id_forma_pago";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $formas_pago = [];
    while ($row = $result->fetch_assoc()) {
        $formas_pago[] = $row;
    }
    
    $stmt->close();
    return $formas_pago;
}

/**
 * Crea una nueva forma de pago
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $nombre Nombre de la forma de pago
 * @param string|null $descripcion Descripción de la forma de pago (opcional)
 * @return int|false ID de la forma de pago creada o false en caso de error
 */
function crearFormaPago($mysqli, $nombre, $descripcion = null) {
    $sql = "INSERT INTO Forma_Pagos (nombre, descripcion) VALUES (?, ?)";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR crearFormaPago - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('ss', $nombre, $descripcion);
    if (!$stmt->execute()) {
        error_log("ERROR crearFormaPago - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $id_forma_pago = $mysqli->insert_id;
    $stmt->close();
    
    return $id_forma_pago;
}

/**
 * Actualiza una forma de pago existente
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_forma_pago ID de la forma de pago
 * @param string $nombre Nuevo nombre
 * @param string|null $descripcion Nueva descripción (opcional)
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function actualizarFormaPago($mysqli, $id_forma_pago, $nombre, $descripcion = null) {
    $sql = "UPDATE Forma_Pagos SET nombre = ?, descripcion = ? WHERE id_forma_pago = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR actualizarFormaPago - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('ssi', $nombre, $descripcion, $id_forma_pago);
    if (!$stmt->execute()) {
        error_log("ERROR actualizarFormaPago - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $stmt->close();
    return true;
}

/**
 * Desactiva una forma de pago (soft delete)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_forma_pago ID de la forma de pago
 * @return bool True si se desactivó correctamente, false en caso contrario
 */
function desactivarFormaPago($mysqli, $id_forma_pago) {
    $sql = "UPDATE Forma_Pagos SET activo = 0 WHERE id_forma_pago = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR desactivarFormaPago - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('i', $id_forma_pago);
    if (!$stmt->execute()) {
        error_log("ERROR desactivarFormaPago - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $stmt->close();
    return true;
}

/**
 * Activa una forma de pago
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_forma_pago ID de la forma de pago
 * @return bool True si se activó correctamente, false en caso contrario
 */
function activarFormaPago($mysqli, $id_forma_pago) {
    $sql = "UPDATE Forma_Pagos SET activo = 1 WHERE id_forma_pago = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR activarFormaPago - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('i', $id_forma_pago);
    if (!$stmt->execute()) {
        error_log("ERROR activarFormaPago - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $stmt->close();
    return true;
}

/**
 * Elimina físicamente una forma de pago de la base de datos
 * 
 * IMPORTANTE: Esta función realiza un DELETE físico. Solo debe usarse
 * después de verificar que no tiene referencias en otras tablas (ej: Pagos).
 * La tabla Pagos tiene ON DELETE RESTRICT, por lo que MySQL impedirá
 * el DELETE si hay referencias activas.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_forma_pago ID de la forma de pago
 * @return bool True si se eliminó correctamente, false en caso contrario
 */
function eliminarFormaPago($mysqli, $id_forma_pago) {
    $sql = "DELETE FROM Forma_Pagos WHERE id_forma_pago = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR eliminarFormaPago - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('i', $id_forma_pago);
    if (!$stmt->execute()) {
        error_log("ERROR eliminarFormaPago - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $filas_afectadas = $stmt->affected_rows;
    $stmt->close();
    
    // Verificar que se eliminó al menos una fila
    return $filas_afectadas > 0;
}

/**
 * Alterna el estado activo/inactivo de una forma de pago
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_forma_pago ID de la forma de pago
 * @return bool|int Retorna el nuevo estado (1 o 0) si se alternó correctamente, false en caso contrario
 */
function toggleActivoFormaPago($mysqli, $id_forma_pago) {
    // Primero obtener el estado actual
    $sql = "SELECT activo FROM Forma_Pagos WHERE id_forma_pago = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR toggleActivoFormaPago - prepare falló: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('i', $id_forma_pago);
    if (!$stmt->execute()) {
        error_log("ERROR toggleActivoFormaPago - execute falló: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$row) {
        error_log("ERROR toggleActivoFormaPago - forma de pago no encontrada: " . $id_forma_pago);
        return false;
    }
    
    $estado_actual = (int)$row['activo'];
    $nuevo_estado = $estado_actual === 1 ? 0 : 1;
    
    // Actualizar al nuevo estado
    $sql_update = "UPDATE Forma_Pagos SET activo = ? WHERE id_forma_pago = ?";
    $stmt_update = $mysqli->prepare($sql_update);
    if (!$stmt_update) {
        error_log("ERROR toggleActivoFormaPago - prepare update falló: " . $mysqli->error);
        return false;
    }
    
    $stmt_update->bind_param('ii', $nuevo_estado, $id_forma_pago);
    if (!$stmt_update->execute()) {
        error_log("ERROR toggleActivoFormaPago - execute update falló: " . $stmt_update->error);
        $stmt_update->close();
        return false;
    }
    
    $stmt_update->close();
    return $nuevo_estado;
}

/**
 * Obtiene todas las formas de pago (activas e inactivas) para administración
 * 
 * Esta función retorna todas las formas de pago con su estado activo,
 * ordenadas por id_forma_pago. Útil para el panel de administración donde
 * se necesita ver y gestionar todos los métodos, independientemente de su estado.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array asociativo con todas las columnas de Forma_Pagos incluyendo activo
 */
function obtenerFormasPagoAdmin($mysqli) {
    $sql = "SELECT * FROM Forma_Pagos ORDER BY id_forma_pago";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR obtenerFormasPagoAdmin - prepare falló: " . $mysqli->error);
        return [];
    }
    
    if (!$stmt->execute()) {
        error_log("ERROR obtenerFormasPagoAdmin - execute falló: " . $stmt->error);
        $stmt->close();
        return [];
    }
    
    $result = $stmt->get_result();
    
    $formas_pago = [];
    while ($row = $result->fetch_assoc()) {
        $formas_pago[] = $row;
    }
    
    $stmt->close();
    return $formas_pago;
}

/**
 * Cuenta cuántas veces se ha usado una forma de pago en la tabla Pagos
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_forma_pago ID de la forma de pago
 * @return int Número de usos de la forma de pago
 */
function contarUsosFormaPago($mysqli, $id_forma_pago) {
    $sql = "SELECT COUNT(*) as total FROM Pagos WHERE id_forma_pago = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param('i', $id_forma_pago);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return (int)($row['total'] ?? 0);
}

