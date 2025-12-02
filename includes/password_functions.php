<?php
/**
 * ========================================================================
 * FUNCIONES DE MANEJO DE CONTRASEÑAS - Tienda Seda y Lino
 * ========================================================================
 * Funciones centralizadas para el manejo seguro de contraseñas
 * 
 * Funcionalidades:
 * - Generación de hash seguro de contraseñas
 * - Verificación de contraseñas
 * - Configuración de charset/collation para MySQLi
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Configura la conexión MySQLi con charset y collation correctos
 * Esta función debe llamarse después de require_once 'config/database.php'
 * 
 * CUÁNDO LLAMAR ESTA FUNCIÓN:
 * - Antes de insertar/actualizar datos con caracteres especiales (acentos, emojis, etc.)
 * - Antes de trabajar con contraseñas (generar hash, verificar)
 * - Antes de operaciones que requieran comparaciones de texto consistentes
 * - En funciones de queries que manejan datos de usuario (nombre, apellido, email)
 * 
 * NOTA: Esta función es idempotente (puede llamarse múltiples veces sin problemas).
 * Sin embargo, para mejor rendimiento, se recomienda llamarla una vez al inicio
 * de funciones que realizan múltiples operaciones con la BD.
 * 
 * @param mysqli $mysqli Conexión MySQLi a configurar
 * @return bool True si se configuró correctamente, false en caso contrario
 */
function configurarConexionBD($mysqli) {
    // LÓGICA DE NEGOCIO: Configura charset UTF-8 para garantizar compatibilidad con caracteres especiales en hashes.
    // REGLA DE NEGOCIO: Las contraseñas deben procesarse con charset UTF-8 para evitar problemas de codificación.
    // LÓGICA: UTF-8 es necesario para que los hashes de contraseñas con caracteres especiales funcionen correctamente.
    
    // VALIDACIÓN: Verificar que la conexión sea válida
    // REGLA: La conexión debe ser una instancia válida de mysqli
    if (!$mysqli || !($mysqli instanceof mysqli)) {
        error_log("ERROR: configurarConexionBD() recibió una conexión inválida");
        return false;
    }
    
    // Configurar charset utf8mb4 (UTF-8 completo con soporte para emojis y caracteres especiales)
    // REGLA: utf8mb4 es necesario para caracteres especiales en contraseñas
    if (!$mysqli->set_charset("utf8mb4")) {
        error_log("ERROR: No se pudo establecer charset utf8mb4: " . $mysqli->error);
        return false;
    }
    
    // Establecer collation explícitamente para comparaciones consistentes
    // REGLA: utf8mb4_unicode_ci permite comparaciones case-insensitive correctas
    if (!$mysqli->query("SET collation_connection = 'utf8mb4_unicode_ci'")) {
        error_log("ERROR: No se pudo establecer collation_connection: " . $mysqli->error);
        return false;
    }
    
    // Establecer NAMES con charset y collation (sintaxis correcta)
    // REGLA: Configuración completa de charset para toda la conexión
    if (!$mysqli->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci")) {
        error_log("ERROR: No se pudo establecer NAMES: " . $mysqli->error);
        return false;
    }
    
    return true;
}

/**
 * Genera un hash seguro de contraseña usando PASSWORD_DEFAULT
 * 
 * @param string $password Contraseña en texto plano
 * @param mysqli|null $mysqli Conexión MySQLi (opcional, para configurar charset antes de generar hash)
 * @return string|false Hash de la contraseña o false en caso de error
 */
function generarHashPassword($password, $mysqli = null) {
    // LÓGICA DE NEGOCIO: Genera hash seguro de contraseña usando algoritmo recomendado por PHP.
    // REGLA DE SEGURIDAD: NUNCA almacenar contraseñas en texto plano, siempre usar hash.
    // REGLA: Usa PASSWORD_DEFAULT que selecciona automáticamente el algoritmo más seguro disponible.
    // LÓGICA: Verifica que el hash funcione correctamente antes de retornarlo.
    
    // VALIDACIÓN 1: Validar que la contraseña no esté vacía
    // REGLA: Contraseñas vacías no son válidas
    if (empty($password) || strlen($password) === 0) {
        error_log("ERROR: generarHashPassword() recibió una contraseña vacía");
        return false;
    }
    
    // Configurar charset UTF-8 si se proporciona conexión
    // REGLA: Charset UTF-8 es necesario para caracteres especiales en contraseñas
    if ($mysqli !== null) {
        configurarConexionBD($mysqli);
    }
    
    // Generar hash usando PASSWORD_DEFAULT (usa el algoritmo más seguro disponible)
    // REGLA: PASSWORD_DEFAULT usa bcrypt (PHP 5.5+) o argon2 (PHP 7.2+) según disponibilidad
    // LÓGICA: El algoritmo se actualiza automáticamente según versiones futuras de PHP
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // VALIDACIÓN 2: Verificar que la generación fue exitosa
    if ($hash === false || empty($hash)) {
        error_log("ERROR: password_hash() falló al generar hash");
        return false;
    }
    
    // VALIDACIÓN 3: Verificar longitud mínima del hash (60 caracteres para bcrypt)
    // REGLA: Hash de bcrypt tiene longitud fija de 60 caracteres
    if (strlen($hash) < 60) {
        error_log("ERROR: Hash generado tiene longitud incorrecta: " . strlen($hash) . " caracteres");
        return false;
    }
    
    // VALIDACIÓN 4: Verificar que el hash funciona correctamente (auto-verificación)
    // REGLA: El hash debe poder verificar la contraseña original
    // LÓGICA: Prueba de integridad para asegurar que el hash es válido
    if (!password_verify($password, $hash)) {
        error_log("ERROR: Hash generado no verifica correctamente con la contraseña original");
        return false;
    }
    
    return $hash;
}

/**
 * Verifica una contraseña contra un hash almacenado
 * 
 * @param string $password Contraseña en texto plano a verificar
 * @param string $hash Hash almacenado en la base de datos
 * @return bool True si la contraseña es correcta, false en caso contrario
 */
function verificarPassword($password, $hash) {
    // LÓGICA DE NEGOCIO: Verifica una contraseña contra un hash almacenado de forma segura.
    // REGLA DE SEGURIDAD: Usa password_verify() que es resistente a timing attacks.
    // REGLA: No validar longitud del hash manualmente, password_verify() lo maneja automáticamente.
    // LÓGICA: Soporta diferentes algoritmos de hash (bcrypt, argon2, etc.) automáticamente.
    
    // VALIDACIÓN 1: Validar que la contraseña no esté vacía
    // REGLA: Contraseña vacía no puede ser válida
    if (empty($password) || strlen($password) === 0) {
        return false;
    }
    
    // VALIDACIÓN 2: Validar que el hash no esté vacío
    // REGLA: Hash vacío indica datos corruptos o inválidos
    if (empty($hash) || strlen($hash) === 0) {
        error_log("ERROR: verificarPassword() recibió un hash vacío");
        return false;
    }
    
    // IMPORTANTE: NO validar longitud del hash antes de password_verify
    // REGLA: password_verify() es la función oficial de PHP y sabe manejar diferentes tipos de hash
    // LÓGICA: Algunos algoritmos de hash pueden tener longitudes diferentes (bcrypt=60, argon2=varía)
    // REGLA DE SEGURIDAD: Dejar que password_verify() maneje la validación completa
    
    // Verificar contraseña usando password_verify() directamente
    // REGLA DE SEGURIDAD: password_verify() es seguro y resistente a timing attacks
    // LÓGICA: Maneja automáticamente diferentes formatos de hash sin necesidad de configuración
    $resultado = password_verify($password, $hash);
    
    return $resultado;
}

/**
 * Genera un hash seguro de respuesta de recupero usando PASSWORD_DEFAULT
 * 
 * La respuesta se normaliza a minúsculas antes de hashear para mantener consistencia.
 * 
 * @param string $respuesta Respuesta de recupero en texto plano
 * @param mysqli|null $mysqli Conexión MySQLi (opcional, para configurar charset antes de generar hash)
 * @return string|false Hash de la respuesta o false en caso de error
 */
function generarHashRespuestaRecupero($respuesta, $mysqli = null) {
    // LÓGICA DE NEGOCIO: Genera hash seguro de respuesta de recupero usando algoritmo recomendado por PHP.
    // REGLA DE SEGURIDAD: NUNCA almacenar respuestas de recupero en texto plano, siempre usar hash.
    // REGLA: Usa PASSWORD_DEFAULT que selecciona automáticamente el algoritmo más seguro disponible.
    // LÓGICA: Normaliza a minúsculas antes de hashear para mantener consistencia.
    
    // VALIDACIÓN 1: Validar que la respuesta no esté vacía
    // REGLA: Respuestas vacías no son válidas
    if (empty($respuesta) || strlen($respuesta) === 0) {
        error_log("ERROR: generarHashRespuestaRecupero() recibió una respuesta vacía");
        return false;
    }
    
    // Configurar charset UTF-8 si se proporciona conexión
    // REGLA: Charset UTF-8 es necesario para caracteres especiales en respuestas
    if ($mysqli !== null) {
        configurarConexionBD($mysqli);
    }
    
    // Normalizar respuesta a minúsculas antes de hashear
    // REGLA: Normalización para mantener consistencia (las respuestas se comparan case-insensitive)
    $respuesta_normalizada = strtolower(trim($respuesta));
    
    // Generar hash usando PASSWORD_DEFAULT (usa el algoritmo más seguro disponible)
    // REGLA: PASSWORD_DEFAULT usa bcrypt (PHP 5.5+) o argon2 (PHP 7.2+) según disponibilidad
    // LÓGICA: El algoritmo se actualiza automáticamente según versiones futuras de PHP
    $hash = password_hash($respuesta_normalizada, PASSWORD_DEFAULT);
    
    // VALIDACIÓN 2: Verificar que la generación fue exitosa
    if ($hash === false || empty($hash)) {
        error_log("ERROR: password_hash() falló al generar hash de respuesta de recupero");
        return false;
    }
    
    // VALIDACIÓN 3: Verificar longitud mínima del hash (60 caracteres para bcrypt)
    // REGLA: Hash de bcrypt tiene longitud fija de 60 caracteres
    if (strlen($hash) < 60) {
        error_log("ERROR: Hash de respuesta generado tiene longitud incorrecta: " . strlen($hash) . " caracteres");
        return false;
    }
    
    // VALIDACIÓN 4: Verificar que el hash funciona correctamente (auto-verificación)
    // REGLA: El hash debe poder verificar la respuesta original normalizada
    // LÓGICA: Prueba de integridad para asegurar que el hash es válido
    if (!password_verify($respuesta_normalizada, $hash)) {
        error_log("ERROR: Hash de respuesta generado no verifica correctamente con la respuesta original");
        return false;
    }
    
    return $hash;
}

/**
 * Verifica una respuesta de recupero contra un hash almacenado
 * 
 * La respuesta se normaliza a minúsculas antes de verificar para mantener consistencia.
 * 
 * @param string $respuesta Respuesta de recupero en texto plano a verificar
 * @param string $hash Hash almacenado en la base de datos
 * @return bool True si la respuesta es correcta, false en caso contrario
 */
function verificarRespuestaRecupero($respuesta, $hash) {
    // LÓGICA DE NEGOCIO: Verifica una respuesta de recupero contra un hash almacenado de forma segura.
    // REGLA DE SEGURIDAD: Usa password_verify() que es resistente a timing attacks.
    // REGLA: Normaliza a minúsculas antes de verificar para mantener consistencia.
    // LÓGICA: Soporta diferentes algoritmos de hash (bcrypt, argon2, etc.) automáticamente.
    
    // VALIDACIÓN 1: Validar que la respuesta no esté vacía
    // REGLA: Respuesta vacía no puede ser válida
    if (empty($respuesta) || strlen($respuesta) === 0) {
        return false;
    }
    
    // VALIDACIÓN 2: Validar que el hash no esté vacío
    // REGLA: Hash vacío indica datos corruptos o inválidos
    if (empty($hash) || strlen($hash) === 0) {
        error_log("ERROR: verificarRespuestaRecupero() recibió un hash vacío");
        return false;
    }
    
    // Normalizar respuesta a minúsculas antes de verificar
    // REGLA: Normalización para mantener consistencia (las respuestas se comparan case-insensitive)
    $respuesta_normalizada = strtolower(trim($respuesta));
    
    // Verificar respuesta usando password_verify() directamente
    // REGLA DE SEGURIDAD: password_verify() es seguro y resistente a timing attacks
    // LÓGICA: Maneja automáticamente diferentes formatos de hash sin necesidad de configuración
    $resultado = password_verify($respuesta_normalizada, $hash);
    
    return $resultado;
}

/**
 * Genera una contraseña aleatoria segura
 * 
 * Genera una contraseña aleatoria con mínimo 12 caracteres que incluye:
 * - Letras mayúsculas (A-Z)
 * - Letras minúsculas (a-z)
 * - Números (0-9)
 * 
 * @param int $longitud Longitud de la contraseña (por defecto 12, mínimo 12)
 * @return string Contraseña aleatoria generada
 */
function generarPasswordAleatoria($longitud = 12) {
    // LÓGICA DE NEGOCIO: Genera contraseña aleatoria segura para usuarios nuevos.
    // REGLA DE SEGURIDAD: Mínimo 12 caracteres con diferentes tipos de caracteres.
    // LÓGICA: Usa random_int() para generar números aleatorios criptográficamente seguros.
    
    // VALIDACIÓN: Asegurar longitud mínima de 12 caracteres
    if ($longitud < 12) {
        $longitud = 12;
    }
    
    // Definir caracteres permitidos
    $mayusculas = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $minusculas = 'abcdefghijklmnopqrstuvwxyz';
    $numeros = '0123456789';
    $todos_caracteres = $mayusculas . $minusculas . $numeros;
    
    // Asegurar que la contraseña tenga al menos un carácter de cada tipo
    $password = '';
    $password .= $mayusculas[random_int(0, strlen($mayusculas) - 1)];
    $password .= $minusculas[random_int(0, strlen($minusculas) - 1)];
    $password .= $numeros[random_int(0, strlen($numeros) - 1)];
    
    // Completar el resto de la longitud con caracteres aleatorios
    for ($i = strlen($password); $i < $longitud; $i++) {
        $password .= $todos_caracteres[random_int(0, strlen($todos_caracteres) - 1)];
    }
    
    // Mezclar los caracteres para que no estén siempre en el mismo orden
    return str_shuffle($password);
}

