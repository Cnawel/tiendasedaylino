<?php
/**
 * ========================================================================
 * CONSULTAS SQL DE USUARIOS - Tienda Seda y Lino
 * ========================================================================
 * Archivo centralizado con todas las consultas relacionadas a usuarios
 * 
 * Uso:
 *   require_once __DIR__ . '/includes/queries/usuario_queries.php';
 *   $rol = obtenerRolUsuario($mysqli, $id_usuario);
 * ========================================================================
 */



// Cargar funciones de contraseñas (incluye configurarConexionBD)
// Usar ruta relativa desde includes/queries/ hacia includes/
$password_functions_path = __DIR__ . '/../password_functions.php';
if (!file_exists($password_functions_path)) {
    error_log("ERROR: No se pudo encontrar password_functions.php en " . $password_functions_path);
    die("Error crítico: Archivo de funciones de contraseña no encontrado. Por favor, contacta al administrador.");
}
require_once $password_functions_path;

/**
 * Normaliza un email para búsqueda en la base de datos
 * 
 * Esta función auxiliar limpia y normaliza emails:
 * - Elimina espacios al inicio y final
 * - Elimina caracteres de control (0x00-0x1F, 0x7F)
 * - Convierte a minúsculas
 * 
 * @param string $email Email a normalizar
 * @return string|null Email normalizado o null si está vacío
 */
function _normalizarEmail($email) {
    $email = trim($email);
    $email = preg_replace('/[\x00-\x1F\x7F]/u', '', $email);
    $email = strtolower($email);
    
    return empty($email) ? null : $email;
}

/**
 * Verifica que el hash de contraseña se guardó correctamente en la base de datos
 * 
 * Esta función auxiliar verifica que el hash guardado coincide con el hash original
 * y registra advertencias si hay discrepancias.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario creado
 * @param string $hash_original Hash original que se intentó guardar
 * @return void
 */
function _verificarHashGuardado($mysqli, $id_usuario, $hash_original) {
    $sql_verify = "SELECT contrasena FROM Usuarios WHERE id_usuario = ? LIMIT 1";
    $stmt_verify = $mysqli->prepare($sql_verify);
    
    if (!$stmt_verify) {
        error_log("ADVERTENCIA crearUsuarioCliente: No se pudo preparar consulta de verificación. ID Usuario: $id_usuario");
        return;
    }
    
    $stmt_verify->bind_param('i', $id_usuario);
    if (!$stmt_verify->execute()) {
        error_log("ERROR _verificarHashGuardado: No se pudo ejecutar consulta - " . $stmt_verify->error);
        $stmt_verify->close();
        return;
    }
    $result_verify = $stmt_verify->get_result();
    $row_verify = $result_verify->fetch_assoc();
    $stmt_verify->close();
    
    if (!$row_verify) {
        error_log("ADVERTENCIA crearUsuarioCliente: No se pudo recuperar hash para verificación. ID Usuario: $id_usuario");
        return;
    }
    
    $hash_guardado = $row_verify['contrasena'];
    $hash_original_length = strlen($hash_original);
    $hash_guardado_length = strlen($hash_guardado);
    
    // Verificar que el hash guardado coincide con el hash original
    if ($hash_guardado !== $hash_original) {
        error_log("ADVERTENCIA crearUsuarioCliente: Hash guardado no coincide con hash original. ID Usuario: $id_usuario");
    }
    
    // Verificar longitud del hash guardado
    if ($hash_guardado_length !== $hash_original_length) {
        error_log("ADVERTENCIA crearUsuarioCliente: Longitud del hash guardado ($hash_guardado_length) difiere de la original ($hash_original_length). ID Usuario: $id_usuario");
    }
}

/**
 * Función auxiliar base para buscar usuario por email
 * 
 * Esta función centraliza la lógica de búsqueda de usuarios por email.
 * Los emails ya están normalizados al insertar/actualizar, por lo que
 * solo se requiere una búsqueda directa.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $email Email normalizado del usuario
 * @param string $campos Campos a seleccionar en la consulta SQL (separados por comas)
 * @return array|null Array con datos del usuario o null si no se encuentra
 */
function _buscarUsuarioPorEmailBase($mysqli, $email, $campos) {
    // Validar conexión
    if (!$mysqli || !($mysqli instanceof mysqli)) {
        return null;
    }
    
    // Validar campos contra lista blanca de campos permitidos (seguridad)
    $campos_permitidos = [
        'id_usuario', 'nombre', 'apellido', 'email', 'contrasena', 'rol', 'activo',
        'telefono', 'direccion', 'localidad', 'provincia', 'codigo_postal',
        'fecha_registro', 'fecha_actualizacion', 'fecha_nacimiento',
        'pregunta_recupero', 'respuesta_recupero'
    ];
    
    // Validar que todos los campos solicitados estén en la lista blanca
    $campos_array = array_map('trim', explode(',', $campos));
    foreach ($campos_array as $campo) {
        if (!in_array($campo, $campos_permitidos, true)) {
            error_log("ERROR _buscarUsuarioPorEmailBase: Campo no permitido: $campo");
            return null;
        }
    }
    
    // Normalizar email
    $email = _normalizarEmail($email);
    if ($email === null) {
        return null;
    }
    
    // Configurar conexión antes de consultar
    configurarConexionBD($mysqli);
    
    // Búsqueda directa (emails ya están normalizados en BD)
    // Los campos ya fueron validados contra lista blanca, así que es seguro usarlos
    $sql = "SELECT $campos FROM Usuarios WHERE email = ? AND activo = 1 LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('s', $email);
    if (!$stmt->execute()) {
        error_log("ERROR _buscarUsuarioPorEmailBase: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    
    return $row;
}

/**
 * Obtiene el rol de un usuario
 * 
 * Esta función retorna el rol de un usuario específico.
 * Útil para verificar permisos y autorización en el sistema.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return string|null Rol del usuario o null si no existe
 */
function obtenerRolUsuario($mysqli, $id_usuario) {
    $sql = "SELECT rol FROM Usuarios WHERE id_usuario = ? LIMIT 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $id_usuario);
    if (!$stmt->execute()) {
        error_log("ERROR obtenerRolUsuario: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row ? $row['rol'] : null;
}

/**
 * Verifica si un email ya está registrado en la base de datos
 * 
 * Esta función verifica si un email está en uso por otro usuario.
 * Útil para validar emails únicos antes de crear o actualizar usuarios.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $email Email a verificar
 * @param int|null $excluir_id ID del usuario a excluir de la verificación (opcional, para actualizaciones)
 * @return bool True si el email está en uso, false si está disponible
 */
function verificarEmailExistente($mysqli, $email, $excluir_id = null) {
    // Normalizar email antes de buscar (consistencia con otras funciones)
    $email = _normalizarEmail($email);
    if ($email === null) {
        return false;
    }
    
    // Construir SQL con condición opcional
    $sql = "SELECT 1 FROM Usuarios WHERE email = ?";
    if ($excluir_id !== null && $excluir_id > 0) {
        $sql .= " AND id_usuario <> ?";
    }
    $sql .= " LIMIT 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    // Vincular parámetros según si hay exclusión
    if ($excluir_id !== null && $excluir_id > 0) {
        $stmt->bind_param('si', $email, $excluir_id);
    } else {
        $stmt->bind_param('s', $email);
    }
    
    if (!$stmt->execute()) {
        error_log("ERROR verificarEmailExistente: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return false;
    }
    $result = $stmt->get_result();
    $existe = $result->num_rows > 0;
    $stmt->close();
    
    return $existe;
}

/**
 * Cuenta usuarios activos por rol específico
 * 
 * Esta función cuenta cuántos usuarios activos tienen un rol determinado.
 * Útil para validar reglas de negocio (ej: no eliminar último admin).
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $rol Rol a contar ('admin', 'cliente', 'marketing', 'ventas')
 * @return int Cantidad de usuarios activos con ese rol
 */
function contarUsuariosPorRol($mysqli, $rol) {
    $sql = "SELECT COUNT(*) as total FROM Usuarios WHERE rol = ? AND activo = 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param('s', $rol);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return intval($row['total'] ?? 0);
}

/**
 * Actualiza los datos de un usuario en la base de datos
 * 
 * Esta función actualiza los datos básicos de un usuario (nombre, apellido, email, rol).
 * Opcionalmente puede actualizar la contraseña si se proporciona el hash.
 * Siempre actualiza fecha_actualizacion automáticamente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario a actualizar
 * @param string $nombre Nuevo nombre del usuario
 * @param string $apellido Nuevo apellido del usuario
 * @param string $email Nuevo email del usuario
 * @param string $rol Nuevo rol del usuario
 * @param string|null $contrasena_hash Hash de contraseña (opcional, si es null no se actualiza)
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function actualizarUsuario($mysqli, $id_usuario, $nombre, $apellido, $email, $rol, $contrasena_hash = null) {
    // Asegurar que la conexión esté configurada con charset UTF-8 para caracteres especiales
    configurarConexionBD($mysqli);
    
    // Construir SQL dinámicamente según si se actualiza contraseña
    $sql = "UPDATE Usuarios SET nombre = ?, apellido = ?, email = ?, rol = ?";
    if ($contrasena_hash !== null) {
        $sql .= ", contrasena = ?";
    }
    $sql .= ", fecha_actualizacion = NOW() WHERE id_usuario = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR actualizarUsuario: No se pudo preparar consulta - " . $mysqli->error . " (Código: " . $mysqli->errno . ")");
        return false;
    }
    
    // Vincular parámetros según si hay contraseña
    if ($contrasena_hash !== null) {
        $stmt->bind_param('sssssi', $nombre, $apellido, $email, $rol, $contrasena_hash, $id_usuario);
    } else {
        $stmt->bind_param('ssssi', $nombre, $apellido, $email, $rol, $id_usuario);
    }
    
    $resultado = $stmt->execute();
    if (!$resultado) {
        error_log("ERROR actualizarUsuario: No se pudo ejecutar consulta - " . $stmt->error . " (Código: " . $stmt->errno . ")");
    }
    $stmt->close();
    
    return $resultado;
}

/**
 * Crea un nuevo usuario staff (admin, marketing, ventas)
 * 
 * Esta función crea un nuevo usuario con rol staff (no cliente).
 * La contraseña debe venir como hash ya generado.
 * Asigna fecha_registro automáticamente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $nombre Nombre del usuario
 * @param string $apellido Apellido del usuario
 * @param string $email Email del usuario (debe ser único)
 * @param string $contrasena_hash Hash de la contraseña (generado con generarHashPassword())
 * @param string $rol Rol del usuario ('admin', 'marketing', 'ventas')
 * @return int ID del usuario creado o 0 si falló
 */
function crearUsuarioStaff($mysqli, $nombre, $apellido, $email, $contrasena_hash, $rol) {
    // Asegurar que la conexión esté configurada con charset UTF-8 para caracteres especiales
    configurarConexionBD($mysqli);
    
    $sql = "INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_registro) VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR crearUsuarioStaff: No se pudo preparar consulta - " . $mysqli->error . " (Código: " . $mysqli->errno . ")");
        return 0;
    }
    
    $stmt->bind_param('sssss', $nombre, $apellido, $email, $contrasena_hash, $rol);
    $resultado = $stmt->execute();
    
    if ($resultado) {
        $id_usuario = $mysqli->insert_id;
        $stmt->close();
        return $id_usuario;
    } else {
        error_log("ERROR crearUsuarioStaff: No se pudo ejecutar consulta - " . $stmt->error . " (Código: " . $stmt->errno . ")");
        $stmt->close();
        return 0;
    }
}

/**
 * Verifica si un usuario tiene pedidos asociados
 * 
 * Esta función verifica si un usuario tiene al menos un pedido en el sistema.
 * Útil para prevenir eliminación de usuarios con historial de pedidos.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return bool True si el usuario tiene pedidos, false en caso contrario
 */
function verificarUsuarioTienePedidos($mysqli, $id_usuario) {
    $sql = "SELECT 1 FROM Pedidos WHERE id_usuario = ? LIMIT 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('i', $id_usuario);
    if (!$stmt->execute()) {
        error_log("ERROR verificarUsuarioTienePedidos: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return false;
    }
    $result = $stmt->get_result();
    $tiene_pedidos = $result->num_rows > 0;
    $stmt->close();
    
    return $tiene_pedidos;
}

/**
 * Verifica si un usuario tiene pedidos activos con pago aprobado
 *
 * REFACTORIZACIÓN: Esta función usa el patrón de múltiples queries simples
 * en lugar de subconsulta EXISTS. Divide la lógica en:
 * - Query 1: Obtener pedidos activos del usuario (preparacion, en_viaje)
 * - Query 2: Para cada pedido, verificar si tiene pago aprobado
 * - PHP: Retornar true si al menos un pedido tiene pago aprobado
 *
 * Esta función verifica si un usuario tiene pedidos en estados activos (preparacion, en_viaje)
 * que además tienen un pago aprobado. Solo estos pedidos bloquean la eliminación del usuario.
 *
 * Estados que bloquean eliminación:
 * - preparacion O en_viaje
 * - Y estado_pago = 'aprobado'
 *
 * NO bloquean eliminación:
 * - Pedidos en estados finales: completado, cancelado
 * - Pedidos en pendiente sin pago aprobado
 *
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return bool True si el usuario tiene pedidos activos con pago aprobado, false en caso contrario
 */
function verificarUsuarioTienePedidosActivos($mysqli, $id_usuario) {
    // Query 1: Obtener pedidos activos del usuario (simple, sin subconsultas)
    $sql_pedidos = "
        SELECT id_pedido
        FROM Pedidos
        WHERE id_usuario = ?
          AND LOWER(TRIM(estado_pedido)) IN ('preparacion', 'en_viaje')
    ";

    $stmt_pedidos = $mysqli->prepare($sql_pedidos);
    if (!$stmt_pedidos) {
        error_log("ERROR verificarUsuarioTienePedidosActivos - prepare pedidos falló: " . $mysqli->error);
        return false;
    }

    $stmt_pedidos->bind_param('i', $id_usuario);

    if (!$stmt_pedidos->execute()) {
        error_log("ERROR verificarUsuarioTienePedidosActivos - execute pedidos falló: " . $stmt_pedidos->error);
        $stmt_pedidos->close();
        return false;
    }

    $result_pedidos = $stmt_pedidos->get_result();

    // Obtener IDs de pedidos activos
    $pedidos_activos = [];
    while ($row = $result_pedidos->fetch_assoc()) {
        $pedidos_activos[] = intval($row['id_pedido']);
    }
    $stmt_pedidos->close();

    // Si no tiene pedidos activos, retornar false inmediatamente
    if (empty($pedidos_activos)) {
        return false;
    }

    // Query 2: Para cada pedido, verificar si tiene pago aprobado
    $sql_pago = "
        SELECT estado_pago
        FROM Pagos
        WHERE id_pedido = ?
        LIMIT 1
    ";

    $stmt_pago = $mysqli->prepare($sql_pago);
    if (!$stmt_pago) {
        error_log("ERROR verificarUsuarioTienePedidosActivos - prepare pago falló: " . $mysqli->error);
        return false;
    }

    // PHP: Verificar si algún pedido tiene pago aprobado
    foreach ($pedidos_activos as $id_pedido) {
        $stmt_pago->bind_param('i', $id_pedido);

        if (!$stmt_pago->execute()) {
            error_log("ERROR verificarUsuarioTienePedidosActivos - execute pago falló para pedido #{$id_pedido}: " . $stmt_pago->error);
            continue; // Saltar este pedido y continuar con el siguiente
        }

        $result_pago = $stmt_pago->get_result();
        $row_pago = $result_pago->fetch_assoc();

        // Si el pago está aprobado, el usuario tiene pedidos activos
        if ($row_pago && strtolower(trim($row_pago['estado_pago'])) === 'aprobado') {
            $stmt_pago->close();
            return true; // Encontramos un pedido activo con pago aprobado
        }
    }

    $stmt_pago->close();

    // No se encontró ningún pedido activo con pago aprobado
    return false;
}

/**
 * Desactiva un usuario (soft delete)
 * 
 * Esta función marca un usuario como inactivo (activo = 0) en lugar de eliminarlo.
 * Preserva el historial del usuario en el sistema (pedidos, movimientos, etc.).
 * Actualiza fecha_actualizacion automáticamente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario a desactivar
 * @return bool True si se desactivó correctamente, false en caso contrario
 */
function desactivarUsuario($mysqli, $id_usuario) {
    $sql = "UPDATE Usuarios SET activo = 0, fecha_actualizacion = NOW() WHERE id_usuario = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR desactivarUsuario: No se pudo preparar consulta - " . $mysqli->error . " (Código: " . $mysqli->errno . ")");
        return false;
    }
    
    $stmt->bind_param('i', $id_usuario);
    $resultado = $stmt->execute();
    if (!$resultado) {
        error_log("ERROR desactivarUsuario: No se pudo ejecutar consulta - " . $stmt->error . " (Código: " . $stmt->errno . ")");
    }
    $stmt->close();
    
    return $resultado;
}

/**
 * Reactiva un usuario (marca como activo)
 * 
 * Esta función marca un usuario como activo (activo = 1) permitiéndole
 * volver a iniciar sesión en el sistema.
 * Actualiza fecha_actualizacion automáticamente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario a reactivar
 * @return bool True si se reactivó correctamente, false en caso contrario
 */
function reactivarUsuario($mysqli, $id_usuario) {
    $sql = "UPDATE Usuarios SET activo = 1, fecha_actualizacion = NOW() WHERE id_usuario = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR reactivarUsuario: No se pudo preparar consulta - " . $mysqli->error . " (Código: " . $mysqli->errno . ")");
        return false;
    }
    
    $stmt->bind_param('i', $id_usuario);
    $resultado = $stmt->execute();
    if (!$resultado) {
        error_log("ERROR reactivarUsuario: No se pudo ejecutar consulta - " . $stmt->error . " (Código: " . $stmt->errno . ")");
    }
    $stmt->close();
    
    return $resultado;
}

/**
 * Cuenta el total de pedidos de un usuario
 * 
 * Esta función retorna el número total de pedidos asociados a un usuario,
 * independientemente de su estado. Útil para mostrar información antes de eliminar.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return int Número total de pedidos del usuario
 */
function contarPedidosUsuario($mysqli, $id_usuario) {
    $sql = "SELECT COUNT(*) as total FROM Pedidos WHERE id_usuario = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param('i', $id_usuario);
    if (!$stmt->execute()) {
        error_log("ERROR contarPedidosUsuario: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return 0;
    }
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return intval($row['total'] ?? 0);
}

/**
 * Elimina físicamente un usuario de la base de datos (hard delete)
 * 
 * Esta función elimina permanentemente el registro del usuario de la tabla Usuarios.
 * Las tablas relacionadas (Pedidos, Pagos, Movimientos_Stock) se mantienen intactas.
 * 
 * IMPORTANTE: Esta acción es irreversible. Solo debe usarse cuando se requiere
 * eliminar completamente el usuario del sistema.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario a eliminar
 * @return bool True si se eliminó correctamente, false en caso contrario
 */
function eliminarUsuarioFisicamente($mysqli, $id_usuario) {
    // PASO DE SEGURIDAD 1: Desvincular pedidos antes de eliminar
    // Esto asegura que si la restricción FK es CASCADE, los pedidos NO se eliminen.
    // Aunque ya debería haberse hecho en el proceso de anonimización, esto actúa como
    // una red de seguridad final (fail-safe).
    $sql_pedidos = "UPDATE Pedidos SET id_usuario = NULL WHERE id_usuario = ?";
    $stmt_pedidos = $mysqli->prepare($sql_pedidos);
    if ($stmt_pedidos) {
        $stmt_pedidos->bind_param('i', $id_usuario);
        $stmt_pedidos->execute();
        $stmt_pedidos->close();
    }

    // PASO DE SEGURIDAD 2: Desvincular movimientos de stock
    $sql_stock = "UPDATE Movimientos_Stock SET id_usuario = NULL WHERE id_usuario = ?";
    $stmt_stock = $mysqli->prepare($sql_stock);
    if ($stmt_stock) {
        $stmt_stock->bind_param('i', $id_usuario);
        $stmt_stock->execute();
        $stmt_stock->close();
    }

    // PASO 3: Eliminar usuario físicamente
    $sql = "DELETE FROM Usuarios WHERE id_usuario = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR eliminarUsuarioFisicamente: No se pudo preparar consulta - " . $mysqli->error . " (Código: " . $mysqli->errno . ")");
        return false;
    }
    
    $stmt->bind_param('i', $id_usuario);
    $resultado = $stmt->execute();
    if (!$resultado) {
        error_log("ERROR eliminarUsuarioFisicamente: No se pudo ejecutar consulta - " . $stmt->error . " (Código: " . $stmt->errno . ")");
    }
    $stmt->close();
    
    return $resultado;
}

/**
 * Actualiza el rol de un usuario
 * 
 * Esta función actualiza únicamente el rol de un usuario.
 * Útil para cambios de rol sin modificar otros datos.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @param string $nuevo_rol Nuevo rol ('cliente', 'admin', 'marketing', 'ventas')
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function actualizarRolUsuario($mysqli, $id_usuario, $nuevo_rol) {
    $sql = "UPDATE Usuarios SET rol = ?, fecha_actualizacion = NOW() WHERE id_usuario = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR actualizarRolUsuario: No se pudo preparar consulta - " . $mysqli->error . " (Código: " . $mysqli->errno . ")");
        return false;
    }
    
    $stmt->bind_param('si', $nuevo_rol, $id_usuario);
    $resultado = $stmt->execute();
    if (!$resultado) {
        error_log("ERROR actualizarRolUsuario: No se pudo ejecutar consulta - " . $stmt->error . " (Código: " . $stmt->errno . ")");
    }
    $stmt->close();
    
    return $resultado;
}

/**
 * Obtiene la contraseña hash de un usuario
 * 
 * Esta función retorna el hash de contraseña almacenado para un usuario.
 * Útil para verificación de contraseñas o validación de cambios.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return string|null Hash de contraseña o null si no existe
 */
function obtenerHashContrasena($mysqli, $id_usuario) {
    $sql = "SELECT contrasena FROM Usuarios WHERE id_usuario = ? LIMIT 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $id_usuario);
    if (!$stmt->execute()) {
        error_log("ERROR obtenerHashContrasena: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row ? $row['contrasena'] : null;
}

/**
 * Obtiene todos los usuarios con ordenamiento por rol y nombre
 * 
 * Esta función retorna todos los usuarios ordenados por rol (prioridad: admin, marketing, ventas, cliente)
 * y luego por apellido y nombre. Útil para listados de usuarios en panel de administración.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string|null $rol_filtro Rol específico para filtrar (opcional). Si se proporciona, solo retorna usuarios con ese rol.
 * @return array Array asociativo de usuarios con todas sus columnas
 */
function obtenerTodosUsuarios($mysqli, $rol_filtro = null) {
    // Validar rol_filtro contra lista de roles permitidos
    $roles_permitidos = ['admin', 'marketing', 'ventas', 'cliente'];
    if ($rol_filtro !== null && $rol_filtro !== '') {
        if (!in_array($rol_filtro, $roles_permitidos, true)) {
            // Rol inválido, retornar array vacío
            return [];
        }
    }
    
    // Construir consulta SQL con filtro opcional por rol
    $sql = "SELECT 
                id_usuario, nombre, apellido, email, rol, activo, 
                telefono, direccion, localidad, provincia, codigo_postal,
                fecha_registro, fecha_actualizacion, fecha_nacimiento
            FROM Usuarios";
    
    // Agregar filtro por rol si se especifica
    if ($rol_filtro !== null && $rol_filtro !== '') {
        $sql .= " WHERE rol = ?";
    }
    
    $sql .= " ORDER BY 
                CASE rol
                    WHEN 'admin' THEN 1
                    WHEN 'marketing' THEN 2
                    WHEN 'ventas' THEN 3
                    WHEN 'cliente' THEN 4
                END,
                apellido, nombre";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    // Vincular parámetro si hay filtro por rol
    if ($rol_filtro !== null && $rol_filtro !== '') {
        $stmt->bind_param('s', $rol_filtro);
    }
    
    if (!$stmt->execute()) {
        error_log("ERROR obtenerTodosUsuarios: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return [];
    }
    $result = $stmt->get_result();
    
    $usuarios = [];
    while ($row = $result->fetch_assoc()) {
        $usuarios[] = $row;
    }
    
    $stmt->close();
    return $usuarios;
}

/**
 * Obtiene estadísticas de usuarios por rol
 * 
 * Esta función retorna un array con el total de usuarios y desglose por rol.
 * Útil para mostrar métricas en el panel de administración.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array asociativo con: total, admins, ventas, marketing, clientes
 */
function obtenerEstadisticasUsuarios($mysqli) {
    // ✅ NIVEL 5: Query simple sin CASE WHEN
    $sql = "SELECT rol FROM Usuarios WHERE activo = 1";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [
            'total' => 0,
            'admins' => 0,
            'ventas' => 0,
            'marketing' => 0,
            'clientes' => 0
        ];
    }

    $stmt->execute();
    $result = $stmt->get_result();

    // ✅ NIVEL 5: Contar en PHP (sin CASE WHEN)
    $stats = [
        'total' => 0,
        'admins' => 0,
        'ventas' => 0,
        'marketing' => 0,
        'clientes' => 0
    ];

    while ($row = $result->fetch_assoc()) {
        $rol = $row['rol'] ?? '';
        $stats['total']++;

        if ($rol === 'admin') {
            $stats['admins']++;
        } elseif ($rol === 'ventas') {
            $stats['ventas']++;
        } elseif ($rol === 'marketing') {
            $stats['marketing']++;
        } elseif ($rol === 'cliente') {
            $stats['clientes']++;
        }
    }

    $stmt->close();

    return $stats;
}

/**
 * Obtiene el historial de creación y modificación de usuarios
 * 
 * Esta función retorna todos los usuarios con información de fechas de registro y actualización,
 * ordenados por la fecha más reciente (creación o modificación). Útil para mostrar historial
 * de cambios en el panel de administración.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array asociativo de usuarios con información de historial ordenado por fecha descendente
 */
function obtenerHistorialUsuarios($mysqli) {
    // ✅ NIVEL 5: Query simple sin COALESCE ni CASE WHEN
    $sql = "SELECT
                id_usuario,
                nombre,
                apellido,
                email,
                rol,
                activo,
                fecha_registro,
                fecha_actualizacion
            FROM Usuarios
            ORDER BY fecha_actualizacion DESC, fecha_registro DESC, id_usuario DESC";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if (!$stmt->execute()) {
        error_log("ERROR obtenerHistorialUsuarios: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return [];
    }
    $result = $stmt->get_result();

    $historial = [];
    while ($row = $result->fetch_assoc()) {
        // ✅ NIVEL 5: Calcular fecha_orden y tipo_accion en PHP (sin COALESCE ni CASE WHEN)
        $fecha_registro = $row['fecha_registro'];
        $fecha_actualizacion = $row['fecha_actualizacion'];

        $row['fecha_orden'] = $fecha_actualizacion ?? $fecha_registro;

        if ($fecha_actualizacion === null || $fecha_actualizacion === $fecha_registro) {
            $row['tipo_accion'] = 'Creación';
        } else {
            $row['tipo_accion'] = 'Modificación';
        }

        $historial[] = $row;
    }

    $stmt->close();

    // Ordenar en PHP por fecha_orden DESC
    usort($historial, function($a, $b) {
        return strtotime($b['fecha_orden']) - strtotime($a['fecha_orden']);
    });

    return $historial;
}

/**
 * Actualiza el id_usuario en Movimientos_Stock a NULL
 * 
 * Esta función establece id_usuario = NULL en Movimientos_Stock para un usuario específico.
 * Útil cuando se desactiva un usuario para mantener integridad referencial
 * pero permitir que los movimientos de stock se mantengan en el historial.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function anularUsuarioEnMovimientosStock($mysqli, $id_usuario) {
    $sql = "UPDATE Movimientos_Stock SET id_usuario = NULL WHERE id_usuario = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR anularUsuarioEnMovimientosStock: No se pudo preparar consulta - " . $mysqli->error . " (Código: " . $mysqli->errno . ")");
        return false;
    }
    
    $stmt->bind_param('i', $id_usuario);
    $resultado = $stmt->execute();
    if (!$resultado) {
        error_log("ERROR anularUsuarioEnMovimientosStock: No se pudo ejecutar consulta - " . $stmt->error . " (Código: " . $stmt->errno . ")");
    }
    $stmt->close();
    
    return $resultado;
}

/**
 * Crea un nuevo usuario cliente
 * 
 * Esta función crea un nuevo usuario con rol cliente.
 * La contraseña debe venir como hash ya generado.
 * Asigna fecha_registro automáticamente y permite especificar fecha de nacimiento,
 * pregunta y respuesta de recupero.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $nombre Nombre del usuario
 * @param string $apellido Apellido del usuario
 * @param string $email Email del usuario (debe ser único)
 * @param string $hash_password Hash de la contraseña (generado con generarHashPassword())
 * @param string $fecha_nacimiento Fecha de nacimiento en formato YYYY-MM-DD
 * @param int $pregunta_recupero_id ID de la pregunta de recupero
 * @param string $respuesta_recupero Respuesta de recupero normalizada
 * @return int ID del usuario creado o 0 si falló
 */
function crearUsuarioCliente($mysqli, $nombre, $apellido, $email, $hash_password, $fecha_nacimiento, $pregunta_recupero_id, $respuesta_recupero) {
    // Validar parámetros antes de intentar insertar
    if (empty($nombre) || empty($apellido) || empty($email) || empty($hash_password)) {
        error_log("ERROR crearUsuarioCliente: Parámetros requeridos vacíos");
        return 0;
    }
    
    // Validar longitud del hash antes de insertar
    $hash_length = strlen($hash_password);
    if ($hash_length < 60) {
        error_log("ERROR crearUsuarioCliente: Hash de contraseña tiene longitud incorrecta: $hash_length caracteres (mínimo esperado: 60)");
        return 0;
    }
    if ($hash_length > 255) {
        error_log("ERROR crearUsuarioCliente: Hash de contraseña excede longitud máxima de columna: $hash_length caracteres (máximo: 255)");
        return 0;
    }
    
    // Asegurar que la conexión esté configurada con charset UTF-8
    configurarConexionBD($mysqli);
    
    // Manejar fecha_nacimiento NULL correctamente
    // Si fecha_nacimiento está vacío o es NULL, usar NULL en la BD
    if ($fecha_nacimiento === null || $fecha_nacimiento === '') {
        $sql = "INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_nacimiento, pregunta_recupero, respuesta_recupero, fecha_registro) VALUES (?, ?, ?, ?, 'cliente', NULL, ?, ?, NOW())";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            echo "<pre style='background:#ffe4e1;'><strong>DEBUG SQL PREPARE FAILED (sin fecha):</strong> " . htmlspecialchars($mysqli->error) . "</pre>\n";
            error_log("ERROR crearUsuarioCliente: No se pudo preparar consulta - " . $mysqli->error . " (Código: " . $mysqli->errno . ")");
            return 0;
        }
        echo "<pre style='background:#e8f4f8;'><strong>DEBUG SQL (sin fecha):</strong>\n$sql\nParámetros: nombre=$nombre, apellido=$apellido, email=$email, hash_len=" . strlen($hash_password) . ", pregunta=$pregunta_recupero_id, respuesta_len=" . strlen($respuesta_recupero) . "</pre>\n";
        $stmt->bind_param('ssssis', $nombre, $apellido, $email, $hash_password, $pregunta_recupero_id, $respuesta_recupero);
    } else {
        $sql = "INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_nacimiento, pregunta_recupero, respuesta_recupero, fecha_registro) VALUES (?, ?, ?, ?, 'cliente', ?, ?, ?, NOW())";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            echo "<pre style='background:#ffe4e1;'><strong>DEBUG SQL PREPARE FAILED (con fecha):</strong> " . htmlspecialchars($mysqli->error) . "</pre>\n";
            error_log("ERROR crearUsuarioCliente: No se pudo preparar consulta - " . $mysqli->error . " (Código: " . $mysqli->errno . ")");
            return 0;
        }
        echo "<pre style='background:#e8f4f8;'><strong>DEBUG SQL (con fecha):</strong>\n$sql\nParámetros: nombre=$nombre, apellido=$apellido, email=$email, hash_len=" . strlen($hash_password) . ", fecha=$fecha_nacimiento, pregunta=$pregunta_recupero_id, respuesta_len=" . strlen($respuesta_recupero) . "</pre>\n";
        $stmt->bind_param('sssssis', $nombre, $apellido, $email, $hash_password, $fecha_nacimiento, $pregunta_recupero_id, $respuesta_recupero);
    }

    $resultado = $stmt->execute();

    echo "<pre style='background:#f5f5dc;'><strong>DEBUG EXECUTE RESULT:</strong> " . ($resultado ? 'SUCCESS' : 'FAILED') . "\n";
    if (!$resultado) {
        echo "Error: " . htmlspecialchars($stmt->error) . "\n";
        echo "Errno: " . $stmt->errno . "\n";
    }
    echo "</pre>\n";

    if ($resultado) {
        $id_usuario = $mysqli->insert_id;
        echo "<pre style='background:#f0fff0;'><strong>DEBUG INSERT SUCCESS:</strong> Nuevo ID = $id_usuario</pre>\n";

        // Verificar que el hash se guardó correctamente
        _verificarHashGuardado($mysqli, $id_usuario, $hash_password);

        $stmt->close();
        return $id_usuario;
    } else {
        echo "<pre style='background:#ffe4e1;'><strong>DEBUG INSERT FAILED</strong></pre>\n";
        error_log("ERROR crearUsuarioCliente: No se pudo ejecutar consulta - " . $stmt->error . " (Código: " . $stmt->errno . ")");
        $stmt->close();
        return 0;
    }
}

/**
 * Obtiene un usuario por email con datos de recupero
 * 
 * Esta función busca un usuario activo por email y retorna los datos necesarios
 * para el proceso de recuperación de contraseña (fecha_nacimiento, pregunta_recupero, respuesta_recupero).
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $email Email del usuario (será normalizado automáticamente)
 * @return array|null Array con datos del usuario (id_usuario, email, fecha_nacimiento, pregunta_recupero, respuesta_recupero) o null si no existe
 */
function obtenerUsuarioPorEmailRecupero($mysqli, $email) {
    $campos = "id_usuario, email, fecha_nacimiento, pregunta_recupero, respuesta_recupero";
    return _buscarUsuarioPorEmailBase($mysqli, $email, $campos);
}

/**
 * Obtiene el texto de una pregunta de recupero por su ID
 * 
 * Esta función retorna el texto de una pregunta de recupero específica.
 * Útil para mostrar la pregunta registrada por el usuario.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $pregunta_id ID de la pregunta de recupero
 * @return string|null Texto de la pregunta o null si no existe
 */
function obtenerTextoPreguntaRecupero($mysqli, $pregunta_id) {
    $stmt = $mysqli->prepare("SELECT texto_pregunta FROM Preguntas_Recupero WHERE id_pregunta = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $pregunta_id);
    if (!$stmt->execute()) {
        error_log("ERROR obtenerTextoPreguntaRecupero: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row ? $row['texto_pregunta'] : null;
}

/**
 * Obtiene la pregunta de recupero de un usuario
 * 
 * Esta función retorna el ID de la pregunta de recupero registrada por un usuario.
 * Útil para obtener la pregunta antes de mostrar el formulario de cambio de contraseña.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return int|null ID de la pregunta de recupero o null si no tiene
 */
function obtenerPreguntaRecuperoUsuario($mysqli, $id_usuario) {
    $stmt = $mysqli->prepare("SELECT pregunta_recupero FROM Usuarios WHERE id_usuario = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $id_usuario);
    if (!$stmt->execute()) {
        error_log("ERROR obtenerPreguntaRecuperoUsuario: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row && isset($row['pregunta_recupero']) ? intval($row['pregunta_recupero']) : null;
}

/**
 * Verifica que el hash de contraseña guardado es correcto
 * 
 * Esta función verifica que el hash de contraseña guardado en la base de datos
 * puede verificar correctamente con una contraseña en texto plano.
 * Solo usuarios activos pueden tener su hash verificado (seguridad).
 * Útil para validar que el hash se guardó correctamente después de crear un usuario.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @param string $password_plain Contraseña en texto plano para verificar
 * @return bool True si el hash verifica correctamente, false en caso contrario
 */
function verificarHashContrasena($mysqli, $id_usuario, $password_plain) {
    // Verificar que existe la función verificarPassword
    if (!function_exists('verificarPassword')) {
        return false;
    }
    
    // Solo usuarios activos pueden tener su hash verificado (seguridad)
    $stmt = $mysqli->prepare("SELECT contrasena FROM Usuarios WHERE id_usuario = ? AND activo = 1 LIMIT 1");
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('i', $id_usuario);
    if (!$stmt->execute()) {
        error_log("ERROR verificarHashContrasena: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return false;
    }
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$row || empty($row['contrasena'])) {
        return false;
    }
    
    $hash_guardado = $row['contrasena'];
    return verificarPassword($password_plain, $hash_guardado);
}

/**
 * Busca un usuario por email en la base de datos
 * 
 * Esta función busca un usuario activo por email y retorna los datos completos
 * necesarios para autenticación.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $email Email del usuario a buscar (será normalizado automáticamente)
 * @return array|null Array con datos del usuario (id_usuario, nombre, apellido, email, contrasena, rol, activo) o null si no se encuentra
 */
function buscarUsuarioPorEmail($mysqli, $email) {
    $campos = "id_usuario, nombre, apellido, email, contrasena, rol, activo";
    return _buscarUsuarioPorEmailBase($mysqli, $email, $campos);
}

/**
 * Busca un usuario por email incluyendo usuarios inactivos
 * 
 * Esta función busca un usuario por email sin restricción de activo = 1,
 * permitiendo encontrar usuarios inactivos. Útil para procesos de reactivación
 * de cuentas durante el login.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $email Email del usuario a buscar (será normalizado automáticamente)
 * @return array|null Array con datos del usuario (id_usuario, nombre, apellido, email, contrasena, rol, activo) o null si no se encuentra
 */
function buscarUsuarioPorEmailIncluyendoInactivos($mysqli, $email) {
    // Validar conexión
    if (!$mysqli || !($mysqli instanceof mysqli)) {
        return null;
    }
    
    // Normalizar email
    $email = _normalizarEmail($email);
    if ($email === null) {
        return null;
    }
    
    // Configurar conexión antes de consultar
    configurarConexionBD($mysqli);
    
    // Búsqueda sin restricción de activo (incluye usuarios inactivos)
    $campos = "id_usuario, nombre, apellido, email, contrasena, rol, activo";
    $sql = "SELECT $campos FROM Usuarios WHERE email = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('s', $email);
    if (!$stmt->execute()) {
        error_log("ERROR buscarUsuarioPorEmailIncluyendoInactivos: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    
    return $row;
}

/**
 * Obtiene todos los datos de un usuario por su ID (solo usuarios activos)
 * 
 * Esta función retorna todos los campos del usuario para uso en checkout y otras operaciones
 * que requieren datos completos del usuario. Solo retorna usuarios activos por seguridad.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return array|null Array con todos los datos del usuario o null si no existe o está inactivo
 */
function obtenerUsuarioPorId($mysqli, $id_usuario) {
    $sql = "SELECT * FROM Usuarios WHERE id_usuario = ? AND activo = 1 LIMIT 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $id_usuario);
    if (!$stmt->execute()) {
        error_log("ERROR obtenerUsuarioPorId: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    $stmt->close();

    return $usuario;
}

/**
 * Elimina una cuenta de usuario (soft delete) con validación de email
 * 
 * Esta función realiza un soft delete de la cuenta de usuario, marcándola como inactiva.
 * Valida que el email proporcionado coincida con el usuario antes de proceder.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario a eliminar
 * @param string $email Email del usuario para validación
 * @return bool True si se eliminó correctamente, false en caso contrario
 */
function eliminarCuentaUsuario($mysqli, $id_usuario, $email) {
    // Verificar que el email coincida con el usuario
    $stmt = $mysqli->prepare("SELECT id_usuario, email FROM Usuarios WHERE id_usuario = ? LIMIT 1");
    if (!$stmt) {
        error_log("ERROR eliminarCuentaUsuario: No se pudo preparar consulta SELECT - " . $mysqli->error . " (Código: " . $mysqli->errno . ")");
        return false;
    }
    $stmt->bind_param('i', $id_usuario);
    if (!$stmt->execute()) {
        error_log("ERROR eliminarCuentaUsuario: No se pudo ejecutar consulta SELECT - " . $stmt->error . " (Código: " . $stmt->errno . ")");
        $stmt->close();
        return false;
    }
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    $stmt->close();
    
    if (!$usuario) {
        return false;
    }
    
    // Validar que el email coincida
    if (strtolower(trim($usuario['email'])) !== strtolower(trim($email))) {
        return false;
    }
    
    // Marcar cuenta como inactiva (soft delete)
    $stmt = $mysqli->prepare("UPDATE Usuarios SET activo = 0, fecha_actualizacion = NOW() WHERE id_usuario = ?");
    if (!$stmt) {
        error_log("ERROR eliminarCuentaUsuario: No se pudo preparar consulta UPDATE - " . $mysqli->error . " (Código: " . $mysqli->errno . ")");
        return false;
    }
    $stmt->bind_param('i', $id_usuario);
    $resultado = $stmt->execute();
    if (!$resultado) {
        error_log("ERROR eliminarCuentaUsuario: No se pudo ejecutar actualización - " . $stmt->error . " (Código: " . $stmt->errno . ")");
    }
    $stmt->close();
    
    return $resultado;
}

/**
 * Actualiza los datos completos de un usuario con validación centralizada
 * 
 * Esta función valida todos los datos del usuario usando funciones centralizadas
 * y luego actualiza la base de datos. Reemplaza el SQL inline de procesar-pedido.php.
 * 
 * VALIDACIONES APLICADAS:
 * - validarDatosUsuario(): Valida nombre, apellido, email, teléfono
 * - validarDireccionCompleta(): Valida dirección completa (calle + número + piso)
 * - validarCodigoPostal(): Valida código postal
 * - Validación de localidad y provincia (longitud y formato)
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario a actualizar
 * @param string $nombre Nombre del usuario
 * @param string $apellido Apellido del usuario
 * @param string $email Email del usuario
 * @param string $telefono Teléfono del usuario
 * @param string $direccion_calle Calle de la dirección
 * @param string $direccion_numero Número de la dirección
 * @param string $direccion_piso Piso/Departamento (opcional)
 * @param string $localidad Localidad
 * @param string $provincia Provincia
 * @param string $codigo_postal Código postal
 * @return array ['exito' => bool, 'error' => string] Error contiene mensaje si falla
 */
function actualizarDatosUsuarioCompleto($mysqli, $id_usuario, $nombre, $apellido, $email, $telefono, $direccion_calle, $direccion_numero, $direccion_piso, $localidad, $provincia, $codigo_postal) {
    // Cargar funciones de validación centralizadas
    $validation_functions_path = __DIR__ . '/../validation_functions.php';
    if (!file_exists($validation_functions_path)) {
        error_log("ERROR actualizarDatosUsuarioCompleto: No se pudo encontrar validation_functions.php");
        return ['exito' => false, 'error' => 'Error de configuración del sistema.'];
    }
    require_once $validation_functions_path;
    
    // Cargar funciones de perfil para usar actualizarDatosUsuario()
    $perfil_queries_path = __DIR__ . '/perfil_queries.php';
    if (!file_exists($perfil_queries_path)) {
        error_log("ERROR actualizarDatosUsuarioCompleto: No se pudo encontrar perfil_queries.php");
        return ['exito' => false, 'error' => 'Error de configuración del sistema.'];
    }
    require_once $perfil_queries_path;
    
    // Validar datos personales (nombre, apellido, email, teléfono)
    $validacion_datos = validarDatosUsuario($nombre, $apellido, $email, $telefono);
    if (!$validacion_datos['valido']) {
        $primer_error = reset($validacion_datos['errores']);
        return ['exito' => false, 'error' => $primer_error];
    }
    
    // Validar dirección completa
    $validacion_direccion = validarDireccionCompleta($direccion_calle, $direccion_numero, $direccion_piso);
    if (!$validacion_direccion['valido']) {
        return ['exito' => false, 'error' => $validacion_direccion['error']];
    }
    
    // Validar código postal
    $validacion_codigo_postal = validarCodigoPostal($codigo_postal);
    if (!$validacion_codigo_postal['valido']) {
        return ['exito' => false, 'error' => $validacion_codigo_postal['error']];
    }
    
    // Validar localidad (longitud 3-100, solo letras y espacios)
    $localidad_trimmed = trim($localidad);
    if (empty($localidad_trimmed)) {
        return ['exito' => false, 'error' => 'La localidad es requerida.'];
    }
    if (strlen($localidad_trimmed) < 3) {
        return ['exito' => false, 'error' => 'La localidad debe tener al menos 3 caracteres.'];
    }
    if (strlen($localidad_trimmed) > 100) {
        return ['exito' => false, 'error' => 'La localidad no puede exceder 100 caracteres.'];
    }
    if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/', $localidad_trimmed)) {
        return ['exito' => false, 'error' => 'La localidad solo puede contener letras y espacios.'];
    }
    
    // Validar provincia (longitud 3-100, solo letras y espacios)
    $provincia_trimmed = trim($provincia);
    if (empty($provincia_trimmed)) {
        return ['exito' => false, 'error' => 'La provincia es requerida.'];
    }
    if (strlen($provincia_trimmed) < 3) {
        return ['exito' => false, 'error' => 'La provincia debe tener al menos 3 caracteres.'];
    }
    if (strlen($provincia_trimmed) > 100) {
        return ['exito' => false, 'error' => 'La provincia no puede exceder 100 caracteres.'];
    }
    if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/', $provincia_trimmed)) {
        return ['exito' => false, 'error' => 'La provincia solo puede contener letras y espacios.'];
    }
    
    // Usar función existente de perfil_queries.php para actualizar
    // NOTA: actualizarDatosUsuario() espera fecha_nacimiento como último parámetro (puede ser null)
    $resultado = actualizarDatosUsuario(
        $mysqli,
        $id_usuario,
        $validacion_datos['datos']['nombre'],
        $validacion_datos['datos']['apellido'],
        $validacion_datos['datos']['email'],
        $validacion_datos['datos']['telefono'],
        $validacion_direccion['direccion_completa'],
        $localidad_trimmed,
        $provincia_trimmed,
        $validacion_codigo_postal['valor'],
        null // fecha_nacimiento no se actualiza en checkout
    );
    
    if (!$resultado) {
        error_log("ERROR actualizarDatosUsuarioCompleto: No se pudo actualizar usuario ID: $id_usuario");
        return ['exito' => false, 'error' => 'Error al actualizar los datos del usuario.'];
    }
    
    return ['exito' => true, 'error' => ''];
}

/**
 * Desvincula los pedidos de un usuario (establece id_usuario = NULL)
 * 
 * Esta función desvincula los pedidos de un usuario antes de anonimizarlo.
 * Los pedidos se conservan en el sistema pero ya no están asociados al usuario.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario cuyos pedidos se desvincularán
 * @return bool True si se desvincularon correctamente, false en caso contrario
 */
function desvincularPedidosUsuario($mysqli, $id_usuario) {
    $sql = "UPDATE Pedidos SET id_usuario = NULL WHERE id_usuario = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR desvincularPedidosUsuario: No se pudo preparar consulta - " . $mysqli->error . " (Código: " . $mysqli->errno . ")");
        return false;
    }
    
    $stmt->bind_param('i', $id_usuario);
    $resultado = $stmt->execute();
    if (!$resultado) {
        error_log("ERROR desvincularPedidosUsuario: No se pudo ejecutar consulta - " . $stmt->error . " (Código: " . $stmt->errno . ")");
    }
    $stmt->close();
    
    return $resultado;
}

/**
 * Anonimiza un usuario eliminando todos sus datos personales
 * 
 * Esta función anonimiza un usuario estableciendo todos los datos personales a NULL
 * y marcando la fecha de eliminación. El registro del usuario se mantiene en la base
 * de datos pero sin información que permita identificarlo.
 * 
 * Datos que se anonimizan (se establecen a NULL):
 * - nombre, apellido, email, telefono
 * - direccion, localidad, provincia, codigo_postal
 * - contrasena, pregunta_recupero, respuesta_recupero
 * - fecha_nacimiento
 * 
 * También establece:
 * - activo = 0
 * - fecha_actualizacion = NOW()
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario a anonimizar
 * @return bool True si se anonimizó correctamente, false en caso contrario
 */
function anonimizarUsuario($mysqli, $id_usuario) {
    // Asegurar que la conexión esté configurada con charset UTF-8
    configurarConexionBD($mysqli);
    
    $sql = "UPDATE Usuarios SET 
                nombre = NULL,
                apellido = NULL,
                email = NULL,
                contrasena = NULL,
                telefono = NULL,
                direccion = NULL,
                localidad = NULL,
                provincia = NULL,
                codigo_postal = NULL,
                fecha_nacimiento = NULL,
                pregunta_recupero = NULL,
                respuesta_recupero = NULL,
                activo = 0,
                fecha_actualizacion = NOW()
            WHERE id_usuario = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR anonimizarUsuario: No se pudo preparar consulta - " . $mysqli->error . " (Código: " . $mysqli->errno . ")");
        return false;
    }
    
    $stmt->bind_param('i', $id_usuario);
    $resultado = $stmt->execute();
    if (!$resultado) {
        error_log("ERROR anonimizarUsuario: No se pudo ejecutar consulta - " . $stmt->error . " (Código: " . $stmt->errno . ")");
    }
    $stmt->close();
    
    return $resultado;
}

/**
 * Verifica si un usuario ya está anonimizado
 * 
 * Esta función verifica si un usuario ya fue anonimizado previamente
 * comprobando si está inactivo (activo = 0) y tiene email NULL.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario a verificar
 * @return bool True si el usuario ya está anonimizado, false en caso contrario
 */
function verificarUsuarioAnonimizado($mysqli, $id_usuario) {
    $sql = "SELECT activo, email FROM Usuarios WHERE id_usuario = ? LIMIT 1";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('i', $id_usuario);
    if (!$stmt->execute()) {
        error_log("ERROR verificarUsuarioAnonimizado: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return false;
    }
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    // Si está inactivo y tiene email NULL, el usuario ya está anonimizado
    return !empty($row) && $row['activo'] == 0 && $row['email'] === NULL;
}

