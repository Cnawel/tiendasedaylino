<?php
/**
 * ========================================================================
 * CONSULTAS SQL DE PERFIL - Tienda Seda y Lino
 * ========================================================================
 * Archivo centralizado con todas las consultas relacionadas al perfil de usuario
 * 
 * Uso:
 *   require_once __DIR__ . '/includes/queries/perfil_queries.php';
 *   $preguntas = obtenerPreguntasRecupero($mysqli);
 * ========================================================================
 */

/**
 * Obtiene todas las preguntas de recupero activas ordenadas
 * 
 * Esta función retorna todas las preguntas de recupero que tienen activa = 1,
 * ordenadas primero por el campo 'orden' y luego por id_pregunta.
 * Útil para mostrar opciones de preguntas en formularios de registro
 * o recuperación de contraseña.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @return array Array asociativo con preguntas de recupero (id_pregunta, texto_pregunta)
 */
function obtenerPreguntasRecupero($mysqli) {
    $sql = "SELECT id_pregunta, texto_pregunta FROM Preguntas_Recupero WHERE activa = 1 ORDER BY orden ASC, id_pregunta ASC";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    if (!$stmt->execute()) {
        error_log("ERROR obtenerPreguntasRecupero: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return [];
    }
    $result = $stmt->get_result();
    
    $preguntas = [];
    while ($row = $result->fetch_assoc()) {
        $preguntas[] = $row;
    }
    
    $stmt->close();
    return $preguntas;
}

/**
 * Verifica si una pregunta de recupero existe y está activa
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $pregunta_id ID de la pregunta
 * @return bool True si existe y está activa, false en caso contrario
 */
function verificarPreguntaRecupero($mysqli, $pregunta_id) {
    // Validar que la conexión sea válida
    if (!$mysqli || !($mysqli instanceof mysqli)) {
        error_log("ERROR: verificarPreguntaRecupero() recibió una conexión inválida");
        return false;
    }
    
    // Validar que el ID sea un entero válido
    if (!is_numeric($pregunta_id) || $pregunta_id <= 0) {
        error_log("ERROR: verificarPreguntaRecupero() recibió un ID inválido: " . $pregunta_id);
        return false;
    }
    
    $stmt = $mysqli->prepare("SELECT id_pregunta FROM Preguntas_Recupero WHERE id_pregunta = ? AND activa = 1 LIMIT 1");
    if (!$stmt) {
        error_log("ERROR: verificarPreguntaRecupero() falló al preparar consulta: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param('i', $pregunta_id);
    
    if (!$stmt->execute()) {
        error_log("ERROR: verificarPreguntaRecupero() falló al ejecutar consulta: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        error_log("ERROR: verificarPreguntaRecupero() falló al obtener resultado: " . $mysqli->error);
        $stmt->close();
        return false;
    }
    
    $existe = $result->num_rows > 0;
    $stmt->close();
    return $existe;
}

/**
 * Obtiene los datos del usuario sin contraseña
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return array|null Datos del usuario o null si no existe
 */
function obtenerDatosUsuario($mysqli, $id_usuario) {
    // Asegurar que la conexión esté configurada con charset UTF-8 para leer caracteres especiales correctamente
    configurarConexionBD($mysqli);
    
    $stmt = $mysqli->prepare("SELECT nombre, apellido, email, telefono, direccion, localidad, provincia, codigo_postal, fecha_nacimiento, pregunta_recupero, respuesta_recupero FROM Usuarios WHERE id_usuario = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id_usuario);
    if (!$stmt->execute()) {
        error_log("ERROR obtenerDatosUsuario: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    $stmt->close();
    return $usuario;
}

/**
 * Obtiene los datos básicos del usuario para mantener en actualizaciones parciales
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return array|null Datos básicos del usuario o null si no existe
 */
function obtenerDatosBasicosUsuario($mysqli, $id_usuario) {
    // Asegurar que la conexión esté configurada con charset UTF-8 para leer caracteres especiales correctamente
    configurarConexionBD($mysqli);
    
    $stmt = $mysqli->prepare("SELECT nombre, apellido, email, telefono, direccion, localidad, provincia, codigo_postal, fecha_nacimiento FROM Usuarios WHERE id_usuario = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id_usuario);
    if (!$stmt->execute()) {
        error_log("ERROR obtenerDatosBasicosUsuario: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    $stmt->close();
    return $usuario;
}

/**
 * Actualiza los datos personales del usuario
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @param string $nombre Nombre del usuario
 * @param string $apellido Apellido del usuario
 * @param string $email Email del usuario
 * @param string $telefono Teléfono del usuario
 * @param string $direccion Dirección del usuario
 * @param string $localidad Localidad del usuario
 * @param string $provincia Provincia del usuario
 * @param string $codigo_postal Código postal del usuario
 * @param string|null $fecha_nacimiento Fecha de nacimiento (puede ser null)
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function actualizarDatosUsuario($mysqli, $id_usuario, $nombre, $apellido, $email, $telefono, $direccion, $localidad, $provincia, $codigo_postal, $fecha_nacimiento) {
    // Asegurar que la conexión esté configurada con charset UTF-8 para guardar caracteres especiales correctamente
    configurarConexionBD($mysqli);
    
    // Manejar fecha_nacimiento NULL correctamente
    // Incluir fecha_actualizacion explícitamente (aunque tiene ON UPDATE CURRENT_TIMESTAMP, es mejor ser explícito)
    if ($fecha_nacimiento === null || $fecha_nacimiento === '') {
        $stmt = $mysqli->prepare("UPDATE Usuarios SET nombre=?, apellido=?, email=?, telefono=?, direccion=?, localidad=?, provincia=?, codigo_postal=?, fecha_nacimiento=NULL, fecha_actualizacion=NOW() WHERE id_usuario=?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssssssssi', $nombre, $apellido, $email, $telefono, $direccion, $localidad, $provincia, $codigo_postal, $id_usuario);
    } else {
        $stmt = $mysqli->prepare("UPDATE Usuarios SET nombre=?, apellido=?, email=?, telefono=?, direccion=?, localidad=?, provincia=?, codigo_postal=?, fecha_nacimiento=?, fecha_actualizacion=NOW() WHERE id_usuario=?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sssssssssi', $nombre, $apellido, $email, $telefono, $direccion, $localidad, $provincia, $codigo_postal, $fecha_nacimiento, $id_usuario);
    }
    $resultado = $stmt->execute();
    if (!$resultado) {
        error_log("ERROR actualizarDatosUsuario: No se pudo ejecutar consulta - " . $stmt->error);
    }
    $stmt->close();
    return $resultado;
}

/**
 * Actualiza solo la pregunta y respuesta de recupero del usuario
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @param int|null $pregunta_recupero_id ID de la pregunta de recupero (puede ser null)
 * @param string|null $respuesta_recupero Respuesta de recupero (puede ser null)
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function actualizarPreguntaRecupero($mysqli, $id_usuario, $pregunta_recupero_id, $respuesta_recupero) {
    // Incluir fecha_actualizacion explícitamente (aunque tiene ON UPDATE CURRENT_TIMESTAMP, es mejor ser explícito)
    $stmt = $mysqli->prepare("UPDATE Usuarios SET pregunta_recupero=?, respuesta_recupero=?, fecha_actualizacion=NOW() WHERE id_usuario=?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('isi', $pregunta_recupero_id, $respuesta_recupero, $id_usuario);
    $resultado = $stmt->execute();
    if (!$resultado) {
        error_log("ERROR actualizarPreguntaRecupero: No se pudo ejecutar consulta - " . $stmt->error);
    }
    $stmt->close();
    return $resultado;
}

/**
 * NOTA: obtenerHashContrasena() está definida en usuario_queries.php
 * Para usar esta función, incluir: require_once __DIR__ . '/usuario_queries.php';
 */

/**
 * Actualiza la contraseña del usuario
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @param string $hash_contrasena Hash de la nueva contraseña
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function actualizarContrasena($mysqli, $id_usuario, $hash_contrasena) {
    // Incluir fecha_actualizacion explícitamente (aunque tiene ON UPDATE CURRENT_TIMESTAMP, es mejor ser explícito)
    $stmt = $mysqli->prepare("UPDATE Usuarios SET contrasena = ?, fecha_actualizacion = NOW() WHERE id_usuario = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('si', $hash_contrasena, $id_usuario);
    $resultado = $stmt->execute();
    if (!$resultado) {
        error_log("ERROR actualizarContrasena: No se pudo ejecutar consulta - " . $stmt->error);
    }
    $stmt->close();
    return $resultado;
}

/**
 * Obtiene los pedidos del usuario con total calculado
 * 
 * ESTRATEGIA DE MÚLTIPLES QUERIES:
 * - Query 1: Obtener pedidos básicos del usuario
 * - Query 2: Calcular totales desde Detalle_Pedido (por lote de pedidos)
 * 
 * Esta estrategia simplifica las consultas y mejora el rendimiento al evitar
 * múltiples JOINs y subconsultas anidadas.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return array Array con los pedidos del usuario
 */
function obtenerPedidosUsuario($mysqli, $id_usuario) {
    // Requerir funciones auxiliares de pedido_queries.php
    $pedido_queries_path = __DIR__ . '/pedido_queries.php';
    if (!file_exists($pedido_queries_path)) {
        error_log("ERROR: No se pudo encontrar pedido_queries.php en " . $pedido_queries_path);
        die("Error crítico: Archivo de consultas de pedido no encontrado. Por favor, contacta al administrador.");
    }
    require_once $pedido_queries_path;
    
    // Query 1: Obtener pedidos básicos del usuario
    $sql = "
        SELECT p.id_pedido, p.fecha_pedido, p.estado_pedido, p.total
        FROM Pedidos p 
        WHERE p.id_usuario = ?
        ORDER BY p.fecha_pedido DESC
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('i', $id_usuario);
    if (!$stmt->execute()) {
        error_log("ERROR obtenerPedidosUsuario: No se pudo ejecutar consulta - " . $stmt->error);
        $stmt->close();
        return [];
    }
    $result = $stmt->get_result();
    
    $pedidos = [];
    $pedidos_ids = [];
    while ($row = $result->fetch_assoc()) {
        $pedidos[$row['id_pedido']] = $row;
        $pedidos_ids[] = $row['id_pedido'];
    }
    $stmt->close();
    
    if (empty($pedidos_ids)) {
        return [];
    }
    
    // Query 2: Calcular totales desde Detalle_Pedido (por lote)
    $placeholders = str_repeat('?,', count($pedidos_ids) - 1) . '?';
    $sql_totales = "
        SELECT id_pedido, COALESCE(SUM(cantidad * precio_unitario), 0) as total
        FROM Detalle_Pedido
        WHERE id_pedido IN ($placeholders)
        GROUP BY id_pedido
    ";
    
    $stmt_totales = $mysqli->prepare($sql_totales);
    if ($stmt_totales) {
        $types = str_repeat('i', count($pedidos_ids));
        $stmt_totales->bind_param($types, ...$pedidos_ids);
        if (!$stmt_totales->execute()) {
            error_log("ERROR obtenerPedidosUsuario: No se pudo ejecutar consulta de totales - " . $stmt_totales->error);
            $stmt_totales->close();
            $totales = [];
        } else {
            $result_totales = $stmt_totales->get_result();
            
            $totales = [];
            while ($row = $result_totales->fetch_assoc()) {
                $totales[$row['id_pedido']] = floatval($row['total']);
            }
            $stmt_totales->close();
        }
    } else {
        $totales = [];
    }
    
    // Combinar resultados en PHP
    $pedidos_finales = [];
    foreach ($pedidos as $id_pedido => $pedido) {
        // Usar total de la BD si existe, sino calcular desde detalles
        if ($pedido['total'] !== null && $pedido['total'] > 0) {
            $total_pedido = floatval($pedido['total']);
        } else {
            $total_pedido = $totales[$id_pedido] ?? 0.0;
        }
        
        $pedido['total_pedido'] = $total_pedido;
        $pedidos_finales[] = $pedido;
    }
    
    return $pedidos_finales;
}

/**
 * Reactiva la cuenta de un usuario
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return bool True si se reactivó correctamente, false en caso contrario
 */
function reactivarCuentaUsuario($mysqli, $id_usuario) {
    $stmt = $mysqli->prepare("UPDATE Usuarios SET activo = 1, fecha_actualizacion = NOW() WHERE id_usuario = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $id_usuario);
    $resultado = $stmt->execute();
    if (!$resultado) {
        error_log("ERROR reactivarCuentaUsuario: No se pudo ejecutar consulta - " . $stmt->error);
    }
    $stmt->close();
    
    return $resultado;
}

/**
 * Procesa la eliminación de cuenta del usuario
 * Marca la cuenta como inactiva (soft delete)
 * 
 * NOTA: Esta función ha sido movida a usuario_queries.php para centralizar
 * las funciones de gestión de usuarios. La implementación está en usuario_queries.php
 * 
 * @deprecated Use eliminarCuentaUsuario() de usuario_queries.php
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @param string $email Email del usuario para validación
 * @return bool True si se procesó correctamente, false en caso contrario
 */


