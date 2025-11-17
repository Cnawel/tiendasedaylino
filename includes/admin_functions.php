<?php
/**
 * ========================================================================
 * FUNCIONES AUXILIARES DE ADMINISTRACIÓN - Tienda Seda y Lino
 * ========================================================================
 * Funciones auxiliares para el panel de administración
 * 
 * NOTA: Las funciones de consultas a BD (esUltimoAdmin, actualizarUsuarioBD, emailEnUso)
 * ahora usan las funciones centralizadas de includes/queries/usuario_queries.php
 * para mantener coherencia y seguridad.
 * 
 * FUNCIONES:
 * - esUltimoAdmin(): Verifica si un usuario es el último administrador
 * - actualizarUsuarioBD(): Actualiza datos de usuario en BD
 * - validarNombreApellido(): Valida y sanitiza nombre/apellido
 * - validarEmail(): Valida y sanitiza email
 * - validarPassword(): Valida contraseña con reglas de complejidad
 * - procesarCreacionUsuarioStaff(): Procesa creación de usuarios staff (ventas/marketing)
 * - procesarCambioRol(): Procesa cambio de rol de usuario
 * - procesarActualizacionUsuario(): Procesa actualización de datos de usuario
 * - procesarEliminacionUsuario(): Procesa eliminación (desactivación) de usuario
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

// Cargar funciones de queries de usuarios
$usuario_queries_path = __DIR__ . '/queries/usuario_queries.php';
if (!file_exists($usuario_queries_path)) {
    error_log("ERROR: No se pudo encontrar usuario_queries.php en " . $usuario_queries_path);
    die("Error crítico: Archivo de consultas de usuario no encontrado. Por favor, contacta al administrador.");
}
require_once $usuario_queries_path;

// Cargar funciones de contraseñas (incluye configurarConexionBD)
$password_functions_path = __DIR__ . '/password_functions.php';
if (!file_exists($password_functions_path)) {
    error_log("ERROR: No se pudo encontrar password_functions.php en " . $password_functions_path);
    die("Error crítico: Archivo de funciones de contraseña no encontrado. Por favor, contacta al administrador.");
}
require_once $password_functions_path;

/**
 * Verifica si un usuario es el último administrador del sistema
 * 
 * Esta función es un wrapper de las funciones centralizadas de usuario_queries.php
 * para mantener compatibilidad con código existente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $usuario_id ID del usuario a verificar
 * @return bool True si es el último admin, false en caso contrario
 */
function esUltimoAdmin($mysqli, $usuario_id) {
    // LÓGICA DE NEGOCIO: Verifica si un usuario es el último administrador activo del sistema.
    // Esta validación es crítica para prevenir que se elimine o modifique el último admin,
    // lo cual dejaría el sistema sin capacidad de administración.
    
    // Obtener rol del usuario usando función centralizada
    // Se normaliza el rol a minúsculas para comparación consistente
    $rol_actual = obtenerRolUsuario($mysqli, $usuario_id);
    
    // Solo verificar si el usuario es realmente un administrador
    // LÓGICA: Si no es admin, no puede ser el último admin
    if ($rol_actual && strtolower(trim($rol_actual)) === 'admin') {
        // Contar todos los administradores activos en el sistema
        // LÓGICA: Si solo hay 1 admin, este usuario es el último y no puede ser modificado/eliminado
        $total_admins = contarUsuariosPorRol($mysqli, 'admin');
        
        // Retorna true solo si hay 1 admin o menos (último admin)
        // REGLA DE NEGOCIO: Debe existir al menos 1 administrador activo en el sistema
        return $total_admins <= 1;
    }
    
    // Si no es admin, retorna false (no es el último admin)
    return false;
}

/**
 * Actualiza un usuario en la base de datos con validación de hash
 * 
 * Esta función actualiza los datos de un usuario usando la función centralizada,
 * pero incluye validación adicional del hash de contraseña para seguridad.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $user_id ID del usuario
 * @param string $nombre Nombre del usuario
 * @param string $apellido Apellido del usuario
 * @param string $email Email del usuario
 * @param string $rol Rol del usuario
 * @param string|null $contrasena_hash Hash de contraseña (opcional)
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function actualizarUsuarioBD($mysqli, $user_id, $nombre, $apellido, $email, $rol, $contrasena_hash = null) {
    // Configurar conexión con charset y collation correctos
    configurarConexionBD($mysqli);
    
    // Validar hash si se proporciona
    if ($contrasena_hash !== null) {
        if (empty($contrasena_hash) || strlen(trim($contrasena_hash)) === 0) {
            error_log("ADMIN: Intento de actualizar con hash vacío");
            return false;
        }
    }
    
    // Usar función centralizada para actualizar usuario
    $resultado = actualizarUsuario($mysqli, $user_id, $nombre, $apellido, $email, $rol, $contrasena_hash);
    
    // Si se actualizó la contraseña, confiar en actualizarUsuario() que retorna true
    // La verificación redundante requiere el password en texto plano que ya no está disponible
    // Si actualizarUsuario() retorna true, el hash se guardó correctamente
    
    return $resultado;
}

/**
 * Valida y sanitiza un nombre o apellido
 * @param string $valor Valor a validar
 * @param string $campo Nombre del campo (para mensajes de error)
 * @return array ['valido' => bool, 'valor' => string, 'error' => string]
 */
function validarNombreApellido($valor, $campo = 'campo') {
    // LÓGICA DE NEGOCIO: Valida y sanitiza nombres/apellidos según reglas de negocio.
    // REGLAS: Obligatorio, mínimo 2 caracteres, máximo 100, solo letras y espacios, prevenir XSS.
    
    // Normalizar: remover espacios al inicio y final
    // LÓGICA: Los espacios adicionales no aportan valor y pueden causar problemas de visualización
    $valor = trim($valor);
    
    // VALIDACIÓN 1: Campo obligatorio
    // REGLA DE NEGOCIO: Los nombres y apellidos son datos obligatorios para identificar usuarios
    if (empty($valor)) {
        return ['valido' => false, 'valor' => '', 'error' => "El $campo es obligatorio."];
    }
    
    // VALIDACIÓN 2: Longitud mínima (2 caracteres)
    // REGLA DE NEGOCIO: Un nombre/apellido debe tener al menos 2 caracteres para ser válido
    // LÓGICA: Previene nombres de un solo carácter que no son realistas
    if (strlen($valor) < 2) {
        return ['valido' => false, 'valor' => '', 'error' => "El $campo debe tener al menos 2 caracteres."];
    }
    
    // VALIDACIÓN 3: Longitud máxima (100 caracteres)
    // REGLA DE NEGOCIO: Límite de 100 caracteres para prevenir datos excesivamente largos
    // LÓGICA: Compatible con límites de base de datos y UX razonable
    if (strlen($valor) > 100) {
        return ['valido' => false, 'valor' => '', 'error' => "El $campo no puede exceder 100 caracteres."];
    }
    
    // VALIDACIÓN 4: Solo letras, espacios y caracteres especiales comunes en nombres
    // REGLA DE NEGOCIO: Los nombres deben contener solo letras (incluyendo acentos), espacios y caracteres especiales comunes
    // LÓGICA: Previene inyección de caracteres peligrosos, números, símbolos que no pertenecen a nombres
    // Permite: letras mayúsculas/minúsculas, acentos (á, é, í, ó, ú), ñ, ü, espacios, apóstrofe ('), acento agudo (´)
    if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\'´]+$/', $valor)) {
        return ['valido' => false, 'valor' => '', 'error' => "El $campo solo puede contener letras, espacios, apóstrofe (') y acento agudo (´)."];
    }
    
    // SANITIZACIÓN: Prevenir ataques XSS
    // REGLA DE SEGURIDAD: Escapar caracteres HTML especiales para prevenir inyección de código
    // LÓGICA: Aunque el regex ya valida el contenido, se sanitiza adicionalmente para mostrar en HTML de forma segura
    $valor = htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
    
    return ['valido' => true, 'valor' => $valor, 'error' => ''];
}

/**
 * Valida y sanitiza un email
 * @param string $valor Valor a validar
 * @return array ['valido' => bool, 'valor' => string, 'error' => string]
 */
function validarEmail($valor) {
    // LÓGICA DE NEGOCIO: Valida y sanitiza emails según estándares internacionales.
    // REGLAS: Obligatorio, máximo 150 caracteres, formato válido según RFC, prevenir XSS.
    
    // Normalizar: remover espacios al inicio y final
    // LÓGICA: Los espacios en emails son inválidos y pueden causar errores de autenticación
    $valor = trim($valor);
    
    // VALIDACIÓN 1: Campo obligatorio
    // REGLA DE NEGOCIO: El email es el identificador único del usuario y es obligatorio
    if (empty($valor)) {
        return ['valido' => false, 'valor' => '', 'error' => 'El correo electrónico es obligatorio.'];
    }
    
    // VALIDACIÓN 2: Longitud mínima (6 caracteres)
    // REGLA DE NEGOCIO: Según diccionario de datos, longitud mínima: 6 caracteres
    // LÓGICA: Previene emails demasiado cortos que no son válidos
    if (strlen($valor) < 6) {
        return ['valido' => false, 'valor' => '', 'error' => 'El correo electrónico debe tener al menos 6 caracteres.'];
    }
    
    // VALIDACIÓN 3: Longitud máxima (150 caracteres)
    // REGLA DE NEGOCIO: Límite compatible con estándares de base de datos y RFC 5321
    // LÓGICA: Previene emails excesivamente largos que pueden causar problemas de almacenamiento
    if (strlen($valor) > 150) {
        return ['valido' => false, 'valor' => '', 'error' => 'El correo electrónico no puede exceder 150 caracteres.'];
    }
    
    // VALIDACIÓN 4: Formato válido según RFC
    // REGLA DE NEGOCIO: El email debe cumplir con el formato estándar (usuario@dominio.extensión)
    // LÓGICA: Usa filter_var() de PHP que valida según RFC 5322, el estándar internacional para emails
    // Valida: estructura básica (usuario@dominio), caracteres permitidos, extensión válida
    if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
        return ['valido' => false, 'valor' => '', 'error' => 'El formato del correo electrónico no es válido.'];
    }
    
    // SANITIZACIÓN: Prevenir ataques XSS
    // REGLA DE SEGURIDAD: Escapar caracteres HTML especiales para prevenir inyección de código
    // LÓGICA: Aunque el email es validado por formato, se sanitiza para mostrar en HTML de forma segura
    $valor = htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
    
    return ['valido' => true, 'valor' => $valor, 'error' => ''];
}

/**
 * Valida una contraseña
 * @param string $password Contraseña a validar
 * @param bool $requiere_complejidad Si requiere complejidad (minúscula, mayúscula, número, especial)
 * @return array ['valido' => bool, 'error' => string]
 */
function validarPassword($password, $requiere_complejidad = true) {
    // LÓGICA DE NEGOCIO: Valida contraseñas según reglas de seguridad y complejidad.
    // REGLAS: Obligatoria, mínimo 8 caracteres, máximo 128, complejidad opcional (minúscula, mayúscula, número, especial).
    // IMPORTANTE: NO usar trim() en password - puede cambiar la contraseña (los espacios son válidos en algunos casos).
    
    // VALIDACIÓN 1: Campo obligatorio
    // REGLA DE NEGOCIO: Las contraseñas son obligatorias para proteger cuentas de usuario
    // LÓGICA: Verifica que la contraseña no esté vacía (sin usar trim para no modificar espacios)
    if ($password === '' || strlen($password) === 0) {
        return ['valido' => false, 'error' => 'La contraseña es obligatoria.'];
    }
    
    // VALIDACIÓN 2: Longitud mínima (8 caracteres)
    // REGLA DE SEGURIDAD: Mínimo 8 caracteres es el estándar de seguridad recomendado
    // LÓGICA: Contraseñas más cortas son vulnerables a ataques de fuerza bruta
    if (strlen($password) < 8) {
        return ['valido' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres.'];
    }
    
    // VALIDACIÓN 3: Longitud máxima (128 caracteres)
    // REGLA DE NEGOCIO: Límite de 128 caracteres para compatibilidad con sistemas de hash
    // LÓGICA: Previene contraseñas excesivamente largas que pueden causar problemas de rendimiento
    if (strlen($password) > 128) {
        return ['valido' => false, 'error' => 'La contraseña no puede exceder 128 caracteres.'];
    }
    
    // VALIDACIÓN 4: Complejidad (solo si se requiere)
    // REGLA DE SEGURIDAD: Requiere combinación de tipos de caracteres para mayor seguridad
    // LÓGICA: Contraseñas con múltiples tipos de caracteres son más resistentes a ataques
    if ($requiere_complejidad) {
        // Requiere: 1 minúscula, 1 mayúscula, 1 número, 1 carácter especial
        // REGLA: (?=.*[a-z]) = al menos una minúscula
        //        (?=.*[A-Z]) = al menos una mayúscula
        //        (?=.*\d) = al menos un número
        //        (?=.*[@$!%*?&]) = al menos un carácter especial de la lista permitida
        // LÓGICA: Los caracteres especiales permitidos son seguros y comunes en contraseñas
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
            return ['valido' => false, 'error' => 'La contraseña debe contener al menos: 1 minúscula, 1 mayúscula, 1 número y 1 carácter especial (@$!%*?&).'];
        }
    }
    
    // Si pasa todas las validaciones, la contraseña es válida
    return ['valido' => true, 'error' => ''];
}

/**
 * Procesa la creación de un usuario de staff (ventas/marketing)
 * 
 * Esta función valida los datos, verifica email único, genera hash de contraseña,
 * crea el usuario y verifica que el hash se guardó correctamente.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarCreacionUsuarioStaff($mysqli, $post) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['crear_usuario_staff'])) {
        return false;
    }
    
    // Extraer y normalizar datos del formulario
    $nombre_staff = trim($post['nombre_staff'] ?? '');
    $apellido_staff = trim($post['apellido_staff'] ?? '');
    $email_staff_raw = trim($post['email_staff'] ?? '');
    $email_staff = strtolower($email_staff_raw); // Normalizar a minúsculas
    $rol_staff = $post['rol_staff'] ?? '';
    // IMPORTANTE: NO usar trim() en passwords - puede cambiar la contraseña
    $password_temporal = $post['password_temporal'] ?? '';
    $confirmar_password_temporal = $post['confirmar_password_temporal'] ?? '';
    
    // Validar rol permitido
    $roles_staff_validos = ['cliente', 'ventas', 'marketing', 'admin'];
    
    // Validar datos básicos
    if ($nombre_staff === '' || $apellido_staff === '' || $email_staff === '' || !in_array($rol_staff, $roles_staff_validos, true)) {
        return ['mensaje' => 'Datos inválidos. Completa todos los campos y selecciona un rol válido.', 'mensaje_tipo' => 'danger'];
    }
    
    // Validar email usando función centralizada
    $validacion_email = validarEmail($email_staff_raw);
    if (!$validacion_email['valido']) {
        return ['mensaje' => $validacion_email['error'], 'mensaje_tipo' => 'danger'];
    }
    
    // Verificar email existente usando función centralizada (email ya en minúsculas)
    $existe = verificarEmailExistente($mysqli, $email_staff);
    
    if ($existe) {
        return ['mensaje' => 'El email ya está registrado en el sistema.', 'mensaje_tipo' => 'warning'];
    }
    
    // Validar contraseña temporal (permite contraseñas débiles para staff)
    if ($password_temporal === '' || strlen($password_temporal) === 0) {
        return ['mensaje' => 'Debes proporcionar una contraseña temporal para el usuario.', 'mensaje_tipo' => 'danger'];
    } elseif ($confirmar_password_temporal === '' || strlen($confirmar_password_temporal) === 0) {
        return ['mensaje' => 'Debes confirmar la contraseña temporal.', 'mensaje_tipo' => 'danger'];
    } elseif ($password_temporal !== $confirmar_password_temporal) {
        return ['mensaje' => 'Las contraseñas no coinciden.', 'mensaje_tipo' => 'danger'];
    } elseif (strlen($password_temporal) < 6) {
        // Mínimo 6 caracteres para contraseña temporal
        return ['mensaje' => 'La contraseña temporal debe tener al menos 6 caracteres.', 'mensaje_tipo' => 'danger'];
    } elseif (strlen($password_temporal) > 32) {
        return ['mensaje' => 'La contraseña temporal no puede exceder 32 caracteres.', 'mensaje_tipo' => 'danger'];
    }
    
    // Generar hash usando función centralizada
    $password_hash = generarHashPassword($password_temporal, $mysqli);
    
    if ($password_hash === false) {
        return ['mensaje' => 'Error al procesar la contraseña temporal.', 'mensaje_tipo' => 'danger'];
    }
    
    // Asegurar que la conexión esté configurada antes de insertar
    configurarConexionBD($mysqli);
    
    // Crear usuario staff usando función centralizada
    $id_usuario_nuevo = crearUsuarioStaff($mysqli, $nombre_staff, $apellido_staff, $email_staff, $password_hash, $rol_staff);
    
    if ($id_usuario_nuevo > 0) {
        // Verificar que el hash se guardó correctamente usando función centralizada
        if (verificarHashContrasena($mysqli, $id_usuario_nuevo, $password_temporal)) {
            $mensaje_exito = 'Usuario de ' . strtoupper($rol_staff) . ' creado exitosamente. Contraseña temporal: ' . htmlspecialchars($password_temporal) . ' (El usuario debe cambiarla al iniciar sesión)';
            
            // Limpiar variables sensibles de memoria
            $password_temporal = null;
            $confirmar_password_temporal = null;
            $password_hash = null;
            
            return ['mensaje' => $mensaje_exito, 'mensaje_tipo' => 'success'];
        } else {
            // Hash no verifica correctamente
            $mensaje_error = 'Error: El hash se guardó pero no verifica correctamente. Posible problema de codificación o truncamiento en BD.';
            // Soft delete: marcar usuario como inactivo usando función centralizada
            desactivarUsuario($mysqli, $id_usuario_nuevo);
            
            // Limpiar variables sensibles de memoria
            $password_temporal = null;
            $confirmar_password_temporal = null;
            $password_hash = null;
            
            return ['mensaje' => $mensaje_error, 'mensaje_tipo' => 'danger'];
        }
    } else {
        // Limpiar variables sensibles de memoria
        $password_temporal = null;
        $confirmar_password_temporal = null;
        $password_hash = null;
        
        return ['mensaje' => 'Error al crear el usuario de staff.', 'mensaje_tipo' => 'danger'];
    }
}

/**
 * Procesa el cambio de rol de un usuario
 * 
 * Esta función valida el nuevo rol, previene auto-eliminación de rol admin,
 * verifica que no sea el último admin y actualiza el rol.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @param int $id_usuario_actual ID del administrador que realiza la acción
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarCambioRol($mysqli, $post, $id_usuario_actual) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['cambiar_rol'])) {
        return false;
    }
    
    $usuario_id = intval($post['usuario_id'] ?? 0);
    $nuevo_rol = $post['nuevo_rol'] ?? '';
    
    // Validar que el rol sea válido (coincidente con ENUM en BD)
    $roles_validos = ['cliente', 'ventas', 'marketing', 'admin'];
    
    if (!in_array($nuevo_rol, $roles_validos, true)) {
        return ['mensaje' => 'Rol no válido', 'mensaje_tipo' => 'danger'];
    }
    
    // No permitir que el admin se quite su propio rol de admin
    if ($usuario_id == $id_usuario_actual && $nuevo_rol !== 'admin') {
        return ['mensaje' => 'No puedes cambiar tu propio rol de administrador', 'mensaje_tipo' => 'warning'];
    }
    
    // Verificar si el usuario a modificar es admin y se intenta cambiar a otro rol
    if (esUltimoAdmin($mysqli, $usuario_id) && $nuevo_rol !== 'admin') {
        return ['mensaje' => 'No se puede cambiar el rol: debe existir al menos 1 administrador en el sistema', 'mensaje_tipo' => 'danger'];
    }
    
    // Actualizar rol usando función centralizada
    if (actualizarRolUsuario($mysqli, $usuario_id, $nuevo_rol)) {
        return ['mensaje' => 'Rol actualizado correctamente', 'mensaje_tipo' => 'success'];
    } else {
        return ['mensaje' => 'Error al actualizar el rol', 'mensaje_tipo' => 'danger'];
    }
}

/**
 * Procesa la actualización de datos de un usuario
 * 
 * Esta función valida los datos, verifica email único, valida contraseña opcional,
 * previene auto-eliminación de rol admin y actualiza el usuario.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @param int $id_usuario_actual ID del administrador que realiza la acción
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarActualizacionUsuario($mysqli, $post, $id_usuario_actual) {
    // Verificar que se está procesando la acción correcta
    if (!isset($post['actualizar_usuario'])) {
        return false;
    }
    
    // Extraer y normalizar datos del formulario
    $edit_user_id = intval($post['edit_user_id'] ?? 0);
    $edit_nombre_raw = trim($post['edit_nombre'] ?? '');
    $edit_apellido_raw = trim($post['edit_apellido'] ?? '');
    $edit_email_raw = trim($post['edit_email'] ?? '');
    $edit_email = strtolower($edit_email_raw); // Normalizar a minúsculas
    $edit_rol = $post['nuevo_rol'] ?? '';
    // IMPORTANTE: NO usar trim() en passwords - puede cambiar la contraseña
    $nueva_contrasena = $post['nueva_contrasena'] ?? '';
    $confirmar_contrasena = $post['confirmar_contrasena'] ?? '';
    
    $roles_validos = ['cliente', 'ventas', 'marketing', 'admin'];
    
    // Validar nombre usando función centralizada
    $validacion_nombre = validarNombreApellido($edit_nombre_raw, 'nombre');
    if (!$validacion_nombre['valido']) {
        return ['mensaje' => $validacion_nombre['error'], 'mensaje_tipo' => 'danger'];
    }
    $edit_nombre = $validacion_nombre['valor'];
    
    // Validar apellido usando función centralizada
    $validacion_apellido = validarNombreApellido($edit_apellido_raw, 'apellido');
    if (!$validacion_apellido['valido']) {
        return ['mensaje' => $validacion_apellido['error'], 'mensaje_tipo' => 'danger'];
    }
    $edit_apellido = $validacion_apellido['valor'];
    
    // Validar datos básicos
    if ($edit_user_id <= 0 || $edit_email === '' || !in_array($edit_rol, $roles_validos, true)) {
        return ['mensaje' => 'Datos inválidos al actualizar usuario', 'mensaje_tipo' => 'danger'];
    }
    
    // Validar email usando función centralizada
    $validacion_email = validarEmail($edit_email_raw);
    if (!$validacion_email['valido']) {
        return ['mensaje' => $validacion_email['error'], 'mensaje_tipo' => 'danger'];
    }
    
    // Validar contraseña si se proporciona (validaciones estrictas)
    $cambiar_contrasena = false;
    if (($nueva_contrasena !== '' && strlen($nueva_contrasena) > 0) && 
        ($confirmar_contrasena !== '' && strlen($confirmar_contrasena) > 0)) {
        if ($nueva_contrasena !== $confirmar_contrasena) {
            return ['mensaje' => 'Las contraseñas no coinciden', 'mensaje_tipo' => 'danger'];
        } elseif (strlen($nueva_contrasena) < 6) {
            // Mínimo 6 caracteres según diccionario de datos
            return ['mensaje' => 'La contraseña debe tener al menos 6 caracteres', 'mensaje_tipo' => 'danger'];
        } elseif (strlen($nueva_contrasena) > 32) {
            return ['mensaje' => 'La contraseña no puede exceder 32 caracteres', 'mensaje_tipo' => 'danger'];
        } else {
            $cambiar_contrasena = true;
        }
    } elseif (($nueva_contrasena !== '' && strlen($nueva_contrasena) > 0) || 
              ($confirmar_contrasena !== '' && strlen($confirmar_contrasena) > 0)) {
        // Si solo uno de los campos está lleno
        return ['mensaje' => 'Debes completar ambos campos de contraseña o dejarlos vacíos', 'mensaje_tipo' => 'danger'];
    }
    
    // Evitar quitarse el rol de admin a sí mismo
    if ($edit_user_id == $id_usuario_actual && $edit_rol !== 'admin') {
        return ['mensaje' => 'No puedes quitarte tu rol de ADMIN', 'mensaje_tipo' => 'warning'];
    }
    
    // Verificar si el usuario a modificar es admin y se intenta cambiar a otro rol
    if (esUltimoAdmin($mysqli, $edit_user_id) && $edit_rol !== 'admin') {
        return ['mensaje' => 'No se puede cambiar el rol: debe existir al menos 1 administrador en el sistema', 'mensaje_tipo' => 'danger'];
    }
    
    // Verificar email único (excluyendo el mismo usuario)
    if (verificarEmailExistente($mysqli, $edit_email, $edit_user_id)) {
        return ['mensaje' => 'El email ya está en uso por otro usuario', 'mensaje_tipo' => 'warning'];
    }
    
    // Preparar hash de contraseña si se proporciona
    $contrasena_hash = null;
    if ($cambiar_contrasena) {
        // Generar hash usando función centralizada
        $contrasena_hash = generarHashPassword($nueva_contrasena, $mysqli);
        
        if ($contrasena_hash === false) {
            return ['mensaje' => 'Error al procesar la nueva contraseña. No se pudo generar el hash.', 'mensaje_tipo' => 'danger'];
        }
    }
    
    // Actualizar usuario
    if (actualizarUsuarioBD($mysqli, $edit_user_id, $edit_nombre, $edit_apellido, $edit_email, $edit_rol, $contrasena_hash)) {
        // Si se cambió la contraseña, verificar que se guardó correctamente
        if ($cambiar_contrasena && $contrasena_hash !== null) {
            // Verificar usando función centralizada
            if (verificarHashContrasena($mysqli, $edit_user_id, $nueva_contrasena)) {
                // Limpiar variables sensibles de memoria
                $nueva_contrasena = null;
                $confirmar_contrasena = null;
                $contrasena_hash = null;
                
                return ['mensaje' => 'Usuario y contraseña actualizados correctamente. La contraseña se guardó y verifica correctamente.', 'mensaje_tipo' => 'success'];
            } else {
                // Limpiar variables sensibles de memoria
                $nueva_contrasena = null;
                $confirmar_contrasena = null;
                $contrasena_hash = null;
                
                return ['mensaje' => 'Usuario actualizado pero la contraseña no se guardó correctamente. Verificar codificación de la base de datos.', 'mensaje_tipo' => 'warning'];
            }
        } else {
            return ['mensaje' => 'Usuario actualizado correctamente', 'mensaje_tipo' => 'success'];
        }
    } else {
        // Limpiar variables sensibles de memoria
        $nueva_contrasena = null;
        $confirmar_contrasena = null;
        $contrasena_hash = null;
        
        return ['mensaje' => 'Error al actualizar el usuario.', 'mensaje_tipo' => 'danger'];
    }
}

/**
 * Procesa la eliminación o desactivación de un usuario
 * 
 * Esta función maneja dos acciones:
 * 1. Desactivar usuario (soft delete): marca activo = 0
 * 2. Eliminar usuario físicamente (hard delete): borra el registro de Usuarios
 * 
 * Valida el usuario, previene auto-eliminación, verifica último admin,
 * y maneja confirmaciones para usuarios con pedidos.
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param array $post Datos POST del formulario
 * @param int $id_usuario_actual ID del administrador que realiza la acción
 * @return array|false Array con ['mensaje' => string, 'mensaje_tipo' => string] o false si no hay acción
 */
function procesarEliminacionUsuario($mysqli, $post, $id_usuario_actual) {
    // Verificar que se está procesando alguna acción de gestión de usuario
    $accion_desactivar = isset($post['desactivar_usuario']);
    $accion_reactivar = isset($post['reactivar_usuario']);
    $accion_eliminar = isset($post['eliminar_usuario_fisico']);
    
    if (!$accion_desactivar && !$accion_reactivar && !$accion_eliminar) {
        return false;
    }
    
    $del_user_id = intval($post['del_user_id'] ?? 0);
    
    if ($del_user_id <= 0) {
        return ['mensaje' => 'Usuario inválido', 'mensaje_tipo' => 'danger'];
    } elseif ($del_user_id == $id_usuario_actual) {
        return ['mensaje' => 'No puedes eliminar o desactivar tu propio usuario', 'mensaje_tipo' => 'warning'];
    }
    
    // Verificar si es el último admin (aplica para desactivar y eliminar, no para reactivar)
    if (($accion_desactivar || $accion_eliminar) && esUltimoAdmin($mysqli, $del_user_id)) {
        return ['mensaje' => 'No se puede eliminar o desactivar: debe existir al menos 1 administrador en el sistema', 'mensaje_tipo' => 'danger'];
    }
    
    // ============================================================================
    // ACCIÓN 1: REACTIVAR USUARIO
    // ============================================================================
    if ($accion_reactivar) {
        // Reactivar usuario usando función centralizada
        if (reactivarUsuario($mysqli, $del_user_id)) {
            return ['mensaje' => 'Usuario reactivado correctamente', 'mensaje_tipo' => 'success'];
        } else {
            return ['mensaje' => 'Error al reactivar usuario', 'mensaje_tipo' => 'danger'];
        }
    }
    
    // ============================================================================
    // ACCIÓN 2: DESACTIVAR USUARIO (Soft Delete)
    // ============================================================================
    if ($accion_desactivar) {
        // Verificar si tiene pedidos activos con pago aprobado
        $tiene_pedidos_activos = verificarUsuarioTienePedidosActivos($mysqli, $del_user_id);
        
        if ($tiene_pedidos_activos) {
            return ['mensaje' => 'No se puede desactivar: el usuario tiene pedidos activos con pago aprobado', 'mensaje_tipo' => 'danger'];
        }
        
        // Desasociar movimientos de stock usando función centralizada
        anularUsuarioEnMovimientosStock($mysqli, $del_user_id);
        
        // Soft delete: marcar como inactivo usando función centralizada
        if (desactivarUsuario($mysqli, $del_user_id)) {
            return ['mensaje' => 'Usuario desactivado correctamente', 'mensaje_tipo' => 'success'];
        } else {
            return ['mensaje' => 'Error al desactivar usuario', 'mensaje_tipo' => 'danger'];
        }
    }
    
    // ============================================================================
    // ACCIÓN 3: ELIMINAR USUARIO FÍSICAMENTE (Hard Delete)
    // ============================================================================
    if ($accion_eliminar) {
        // Contar pedidos del usuario
        $total_pedidos = contarPedidosUsuario($mysqli, $del_user_id);
        
        // Si tiene pedidos, verificar confirmación explícita
        if ($total_pedidos > 0) {
            $confirmacion = isset($post['confirmar_eliminar_con_pedidos']) && $post['confirmar_eliminar_con_pedidos'] === '1';
            
            if (!$confirmacion) {
                // Retornar mensaje especial que indica que se necesita confirmación
                return [
                    'mensaje' => 'confirmar_eliminar',
                    'mensaje_tipo' => 'warning',
                    'usuario_id' => $del_user_id,
                    'total_pedidos' => $total_pedidos
                ];
            }
        }
        
        // Desasociar movimientos de stock antes de eliminar
        anularUsuarioEnMovimientosStock($mysqli, $del_user_id);
        
        // Hard delete: eliminar físicamente de la base de datos
        if (eliminarUsuarioFisicamente($mysqli, $del_user_id)) {
            $mensaje = 'Usuario eliminado permanentemente del sistema';
            if ($total_pedidos > 0) {
                $mensaje .= '. Los pedidos y pagos asociados se mantienen en el sistema.';
            }
            return ['mensaje' => $mensaje, 'mensaje_tipo' => 'success'];
        } else {
            return ['mensaje' => 'Error al eliminar usuario', 'mensaje_tipo' => 'danger'];
        }
    }
    
    return false;
}

