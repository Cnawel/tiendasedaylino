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
    $stmt->execute();
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
    if ($excluir_id !== null && $excluir_id > 0) {
        $sql = "SELECT 1 FROM Usuarios WHERE email = ? AND id_usuario <> ? LIMIT 1";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $email, $excluir_id);
    } else {
        $sql = "SELECT 1 FROM Usuarios WHERE email = ? LIMIT 1";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $email);
    }
    
    $stmt->execute();
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
    if ($contrasena_hash !== null) {
        $sql = "UPDATE Usuarios SET nombre = ?, apellido = ?, email = ?, rol = ?, contrasena = ?, fecha_actualizacion = NOW() WHERE id_usuario = ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sssssi', $nombre, $apellido, $email, $rol, $contrasena_hash, $id_usuario);
    } else {
        $sql = "UPDATE Usuarios SET nombre = ?, apellido = ?, email = ?, rol = ?, fecha_actualizacion = NOW() WHERE id_usuario = ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssssi', $nombre, $apellido, $email, $rol, $id_usuario);
    }
    
    $resultado = $stmt->execute();
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
    $sql = "INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_registro) VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param('sssss', $nombre, $apellido, $email, $contrasena_hash, $rol);
    $resultado = $stmt->execute();
    
    if ($resultado) {
        $id_usuario = $mysqli->insert_id;
        $stmt->close();
        return $id_usuario;
    } else {
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
    $stmt->execute();
    $result = $stmt->get_result();
    $tiene_pedidos = $result->num_rows > 0;
    $stmt->close();
    
    return $tiene_pedidos;
}

/**
 * Verifica si un usuario tiene pedidos activos con pago aprobado
 * 
 * Esta función verifica si un usuario tiene pedidos en estados activos (preparacion, en_viaje)
 * que además tienen un pago aprobado. Solo estos pedidos bloquean la eliminación del usuario.
 * 
 * Estados que bloquean eliminación:
 * - preparacion O en_viaje
 * - Y estado_pago = 'aprobado'
 * 
 * NO bloquean eliminación:
 * - Pedidos en estados finales: completado, cancelado, devolucion
 * - Pedidos en pendiente sin pago aprobado
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return bool True si el usuario tiene pedidos activos con pago aprobado, false en caso contrario
 */
function verificarUsuarioTienePedidosActivos($mysqli, $id_usuario) {
    $sql = "
        SELECT 1 
        FROM Pedidos p
        INNER JOIN Pagos pag ON p.id_pedido = pag.id_pedido
        WHERE p.id_usuario = ?
          AND LOWER(TRIM(p.estado_pedido)) IN ('preparacion', 'en_viaje')
          AND LOWER(TRIM(IFNULL(pag.estado_pago, ''))) = 'aprobado'
        LIMIT 1
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('i', $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $tiene_pedidos_activos = $result->num_rows > 0;
    $stmt->close();
    
    return $tiene_pedidos_activos;
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
        return false;
    }
    
    $stmt->bind_param('i', $id_usuario);
    $resultado = $stmt->execute();
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
        return false;
    }
    
    $stmt->bind_param('i', $id_usuario);
    $resultado = $stmt->execute();
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
    $stmt->execute();
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
    $sql = "DELETE FROM Usuarios WHERE id_usuario = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('i', $id_usuario);
    $resultado = $stmt->execute();
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
        return false;
    }
    
    $stmt->bind_param('si', $nuevo_rol, $id_usuario);
    $resultado = $stmt->execute();
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
    $stmt->execute();
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
    
    $stmt->execute();
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
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN rol = 'admin' THEN 1 ELSE 0 END) as admins,
                SUM(CASE WHEN rol = 'ventas' THEN 1 ELSE 0 END) as ventas,
                SUM(CASE WHEN rol = 'marketing' THEN 1 ELSE 0 END) as marketing,
                SUM(CASE WHEN rol = 'cliente' THEN 1 ELSE 0 END) as clientes
            FROM Usuarios";
    
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
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    return [
        'total' => intval($stats['total'] ?? 0),
        'admins' => intval($stats['admins'] ?? 0),
        'ventas' => intval($stats['ventas'] ?? 0),
        'marketing' => intval($stats['marketing'] ?? 0),
        'clientes' => intval($stats['clientes'] ?? 0)
    ];
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
    // Consultar usuarios con fechas de registro y actualización
    // Usar COALESCE para ordenar por la fecha más reciente (actualización o registro)
    // Determinar tipo de acción: Creación si fecha_actualizacion IS NULL o igual a fecha_registro,
    // Modificación si fecha_actualizacion es diferente y no NULL
    $sql = "SELECT 
                id_usuario, 
                nombre, 
                apellido, 
                email, 
                rol, 
                activo,
                fecha_registro,
                fecha_actualizacion,
                COALESCE(fecha_actualizacion, fecha_registro) as fecha_orden,
                CASE 
                    WHEN fecha_actualizacion IS NULL THEN 'Creación'
                    WHEN fecha_actualizacion = fecha_registro THEN 'Creación'
                    ELSE 'Modificación'
                END as tipo_accion
            FROM Usuarios
            ORDER BY fecha_orden DESC, id_usuario DESC";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $historial = [];
    while ($row = $result->fetch_assoc()) {
        $historial[] = $row;
    }
    
    $stmt->close();
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
        return false;
    }
    
    $stmt->bind_param('i', $id_usuario);
    $resultado = $stmt->execute();
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
    
    $sql = "INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_nacimiento, pregunta_recupero, respuesta_recupero, fecha_registro) VALUES (?, ?, ?, ?, 'cliente', ?, ?, ?, NOW())";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("ERROR crearUsuarioCliente: No se pudo preparar consulta - " . $mysqli->error . " (Código: " . $mysqli->errno . ")");
        return 0;
    }
    
    $stmt->bind_param('sssssis', $nombre, $apellido, $email, $hash_password, $fecha_nacimiento, $pregunta_recupero_id, $respuesta_recupero);
    $resultado = $stmt->execute();
    
    if ($resultado) {
        $id_usuario = $mysqli->insert_id;
        $stmt->close();
        error_log("DEBUG crearUsuarioCliente: Usuario creado exitosamente con ID: $id_usuario");
        return $id_usuario;
    } else {
        error_log("ERROR crearUsuarioCliente: No se pudo ejecutar consulta - " . $stmt->error . " (Código: " . $stmt->errno . ")");
        error_log("DEBUG crearUsuarioCliente: Parámetros - nombre: " . substr($nombre, 0, 20) . ", email: " . substr($email, 0, 30) . ", pregunta_id: $pregunta_recupero_id");
        $stmt->close();
        return 0;
    }
}

/**
 * Obtiene un usuario por email con datos de recupero
 * 
 * Esta función busca un usuario activo por email y retorna los datos necesarios
 * para el proceso de recuperación de contraseña (fecha_nacimiento, pregunta_recupero, respuesta_recupero).
 * Intenta múltiples estrategias de búsqueda para compatibilidad.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $email Email del usuario (debe estar normalizado en minúsculas)
 * @return array|null Array con datos del usuario (id_usuario, email, fecha_nacimiento, pregunta_recupero, respuesta_recupero) o null si no existe
 */
function obtenerUsuarioPorEmailRecupero($mysqli, $email) {
    // MEJORA DE ABREVIACIÓN: Código duplicado con buscarUsuarioPorEmail().
    // Extraer normalización de email y configuración de conexión a funciones auxiliares.
    // MEJORA DE RENDIMIENTO: Múltiples intentos de búsqueda son ineficientes.
    // Normalizar emails en la BD al insertar/actualizar para evitar necesidad de múltiples búsquedas.
    
    // Normalizar email usando función auxiliar
    $email = _normalizarEmail($email);
    
    if ($email === null) {
        return null;
    }
    
    // Configurar conexión antes de consultar
    configurarConexionBD($mysqli);
    
    // Intento 1: Búsqueda directa (solo usuarios activos)
    $stmt = $mysqli->prepare("SELECT id_usuario, email, fecha_nacimiento, pregunta_recupero, respuesta_recupero FROM Usuarios WHERE email = ? AND activo = 1 LIMIT 1");
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }
    
    $stmt->close();
    
    // Intento 2: Búsqueda con TRIM (para emails con espacios en BD, solo usuarios activos)
    $stmt = $mysqli->prepare("SELECT id_usuario, email, fecha_nacimiento, pregunta_recupero, respuesta_recupero FROM Usuarios WHERE TRIM(email) = ? AND activo = 1 LIMIT 1");
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }
    
    $stmt->close();
    
    return null;
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
    $stmt->execute();
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
    $stmt->execute();
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
    $stmt->execute();
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
 * Esta función busca un usuario activo por email con múltiples estrategias de búsqueda
 * para compatibilidad (búsqueda directa, con TRIM, con COLLATE).
 * Retorna los datos completos del usuario necesarios para autenticación.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param string $email Email del usuario a buscar (será normalizado a minúsculas)
 * @return array|null Array con datos del usuario (id_usuario, nombre, apellido, email, contrasena, rol, activo) o null si no se encuentra
 */
function buscarUsuarioPorEmail($mysqli, $email) {
    // MEJORA DE ABREVIACIÓN: Esta función tiene código duplicado con obtenerUsuarioPorEmailRecupero().
    // Considerar crear función auxiliar _normalizarEmail() y _configurarConexionBD() para reutilizar.
    // MEJORA DE RENDIMIENTO: Los múltiples intentos de búsqueda (3 intentos) son ineficientes.
    // Considerar normalizar emails en la base de datos al insertar/actualizar para evitar
    // necesidad de múltiples búsquedas. Crear índice funcional en email normalizado si es posible.
    
    if (!$mysqli || !($mysqli instanceof mysqli)) {
        error_log("ERROR: buscarUsuarioPorEmail() recibió una conexión inválida");
        return null;
    }
    
    // Limpiar y normalizar email usando función auxiliar
    $email = _normalizarEmail($email);
    
    if ($email === null) {
        return null;
    }
    
    // Configurar conexión antes de consultar
    configurarConexionBD($mysqli);
    
    // Buscar usuario por email (múltiples intentos para compatibilidad)
    $row = null;
    
    // Intento 1: Búsqueda directa (solo usuarios activos)
    $stmt = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol, activo FROM Usuarios WHERE email = ? AND activo = 1 LIMIT 1");
    if (!$stmt) {
        error_log("ERROR: No se pudo preparar consulta (intento 1): " . $mysqli->error);
        return null;
    }
    
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }
    
    $stmt->close();
    
    // Intento 2: Búsqueda con TRIM (para emails con espacios en BD, solo usuarios activos)
    $stmt = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol, activo FROM Usuarios WHERE TRIM(email) = ? AND activo = 1 LIMIT 1");
    if (!$stmt) {
        error_log("ERROR: No se pudo preparar consulta (intento 2): " . $mysqli->error);
        return null;
    }
    
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }
    
    $stmt->close();
    
    // Intento 3: Búsqueda con COLLATE utf8mb4_bin (comparación exacta de bytes, solo usuarios activos)
    try {
        $stmt = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol, activo FROM Usuarios WHERE email = ? COLLATE utf8mb4_bin AND activo = 1 LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                return $row;
            }
            
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("ERROR: Excepción en búsqueda con COLLATE: " . $e->getMessage());
    }
    
    // No se encontró el usuario
    return null;
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
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    $stmt->close();
    
    return $usuario;
}

