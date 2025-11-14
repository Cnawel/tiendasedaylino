<?php
/**
 * ========================================================================
 * REGISTRO DE USUARIOS - Tienda Seda y Lino
 * ========================================================================
 * Permite crear nuevas cuentas de usuario en el sistema
 * - Validación de datos del formulario (nombre, apellido, email, contraseña)
 * - Verificación de que el email no esté ya registrado
 * - Hash seguro de contraseñas usando password_hash()
 * - Asignación automática de rol CLIENTE a nuevos usuarios
 * - Redirección a login después de registro exitoso
 * 
 * Funciones principales:
 * - Validar datos del formulario
 * - Verificar email único en BD
 * - Encriptar contraseña con PASSWORD_DEFAULT (algoritmo más seguro disponible)
 * - Insertar nuevo usuario en BD
 * 
 * Variables principales:
 * - $mensaje: Mensaje de éxito o error a mostrar
 * - Datos del formulario: nombre, apellido, email, contrasena, confirmar_contrasena
 * 
 * Tabla utilizada: Usuarios (campos: nombre, apellido, email, contrasena, rol)
 * ========================================================================
 */

// ========================================================================
// MANEJO DE ERRORES TEMPORAL PARA DIAGNÓSTICO
// ========================================================================
// Activar reporte de errores para ver el error real (temporal para debugging)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

// Cargar funciones de contraseñas
$password_functions_path = __DIR__ . '/includes/password_functions.php';
if (!file_exists($password_functions_path)) {
    error_log("ERROR: No se pudo encontrar password_functions.php en " . $password_functions_path);
    die("Error crítico: Archivo de funciones de contraseña no encontrado. Por favor, contacta al administrador.");
}
require_once $password_functions_path;

// Cargar funciones de consultas de usuarios
$usuario_queries_path = __DIR__ . '/includes/queries/usuario_queries.php';
if (!file_exists($usuario_queries_path)) {
    error_log("ERROR: No se pudo encontrar usuario_queries.php en " . $usuario_queries_path);
    die("Error crítico: Archivo de consultas de usuario no encontrado. Por favor, contacta al administrador.");
}
require_once $usuario_queries_path;

// Cargar funciones de consultas de perfil
$perfil_queries_path = __DIR__ . '/includes/queries/perfil_queries.php';
if (!file_exists($perfil_queries_path)) {
    error_log("ERROR: No se pudo encontrar perfil_queries.php en " . $perfil_queries_path);
    die("Error crítico: Archivo de consultas de perfil no encontrado. Por favor, contacta al administrador.");
}
require_once $perfil_queries_path;

// Cargar funciones de administración
$admin_functions_path = __DIR__ . '/includes/admin_functions.php';
if (!file_exists($admin_functions_path)) {
    error_log("ERROR: No se pudo encontrar admin_functions.php en " . $admin_functions_path);
    die("Error crítico: Archivo de funciones de administración no encontrado. Por favor, contacta al administrador.");
}
require_once $admin_functions_path;

// Configurar título de la página
$titulo_pagina = 'Registro';

// Array para almacenar errores por campo
$errores = [];
// Variables para mantener valores ingresados (para no perderlos al mostrar errores)
$valores_form = [
    'nombre' => '',
    'apellido' => '',
    'email' => '',
    'fecha_nacimiento' => '',
    'pregunta_recupero' => '',
    'respuesta_recupero' => ''
];

// ========================================================================
// OBTENER PREGUNTAS DE RECUPERO DESDE BASE DE DATOS
// ========================================================================
require_once __DIR__ . '/config/database.php';
$preguntas_recupero = obtenerPreguntasRecupero($mysqli);

// Si no hay preguntas en BD, insertar preguntas por defecto
if (empty($preguntas_recupero) && isset($mysqli) && $mysqli instanceof mysqli) {
    // Definir preguntas por defecto
    $preguntas_por_defecto = [
        ['texto' => '¿Cuál es el nombre de tu primera mascota?', 'orden' => 1],
        ['texto' => '¿En qué ciudad naciste?', 'orden' => 2],
        ['texto' => '¿Cuál es el nombre de tu mejor amigo/a de la infancia?', 'orden' => 3],
        ['texto' => '¿Cuál es el nombre de tu colegio primario?', 'orden' => 4]
    ];
    
    // Insertar preguntas por defecto en la BD
    $sql_insert = "INSERT INTO Preguntas_Recupero (texto_pregunta, activa, orden) VALUES (?, 1, ?)";
    $stmt_insert = $mysqli->prepare($sql_insert);
    
    if ($stmt_insert) {
        foreach ($preguntas_por_defecto as $pregunta) {
            $stmt_insert->bind_param('si', $pregunta['texto'], $pregunta['orden']);
            $stmt_insert->execute();
        }
        $stmt_insert->close();
        
        // Obtener las preguntas recién insertadas
        $preguntas_recupero = obtenerPreguntasRecupero($mysqli);
    }
    
    // Si aún no hay preguntas (fallback final), usar array local
    if (empty($preguntas_recupero)) {
        $preguntas_recupero = [
            ['id_pregunta' => 1, 'texto_pregunta' => '¿Cuál es el nombre de tu primera mascota?'],
            ['id_pregunta' => 2, 'texto_pregunta' => '¿En qué ciudad naciste?'],
            ['id_pregunta' => 3, 'texto_pregunta' => '¿Cuál es el nombre de tu mejor amigo/a de la infancia?'],
            ['id_pregunta' => 4, 'texto_pregunta' => '¿Cuál es el nombre de tu colegio primario?']
        ];
    }
}

// Procesar formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verificar que la conexión a la base de datos esté disponible
    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        error_log("ERROR: Conexión a base de datos no disponible en procesamiento POST");
        $errores['general'] = 'Error de conexión a la base de datos. Por favor, intenta nuevamente.';
    } else {
    
    // Inicializar variables para evitar errores de "undefined variable"
    $nombre = null;
    $apellido = null;
    $email = null;
    $fecha_nacimiento = null;
    $respuesta_recupero = null;
    
    // ========================================================================
    // SANITIZACIÓN Y VALIDACIÓN DE ENTRADA - PREVENCIÓN XSS Y SQL INJECTION
    // ========================================================================
    
    /**
     * SANITIZACIÓN BÁSICA DE DATOS
     * 
     * 1. trim(): Elimina espacios en blanco al inicio y final
     * 2. htmlspecialchars(): Convierte caracteres especiales HTML a entidades
     *    - Previene XSS (Cross-Site Scripting)
     *    - Convierte < > & " ' a entidades seguras
     * 3. filter_var(): Filtros de validación PHP nativos
     * 4. strlen(): Verificación de longitud máxima
     */
    
    // Validar NOMBRE usando función centralizada
    // NOTA: La función validarNombreApellido() permite máximo 100 caracteres,
    // pero para registro limitamos a 50 caracteres según requisitos específicos
    $nombre_raw = $_POST['nombre'] ?? '';
    $valores_form['nombre'] = $nombre_raw;
    
    // Usar función centralizada para validar nombre
    $validacion_nombre = validarNombreApellido($nombre_raw, 'nombre');
    
    if (!$validacion_nombre['valido']) {
        $errores['nombre'] = $validacion_nombre['error'];
    } else {
        // Validar límite específico de registro (50 caracteres)
        // La función centralizada permite hasta 100, pero registro requiere máximo 50
        $nombre_trimmed = trim($nombre_raw);
        if (strlen($nombre_trimmed) > 50) {
            $errores['nombre'] = 'El nombre no puede exceder 50 caracteres.';
        } else {
            // Validación exitosa - usar valor sin sanitizar para guardar en BD
            // NOTA: NO sanitizar con htmlspecialchars() antes de guardar en BD
            // Los datos se sanitizan al MOSTRAR en HTML, no al guardar
            // La función validarNombreApellido() retorna valor sanitizado, pero necesitamos el original
            $nombre = $nombre_trimmed;
        }
    }
    
    // Validar APELLIDO (OBLIGATORIO) usando función centralizada
    $apellido_raw = $_POST['apellido'] ?? '';
    $valores_form['apellido'] = $apellido_raw;
    
    $apellido_trimmed = trim($apellido_raw);
    if (empty($apellido_trimmed)) {
        // El apellido es obligatorio
        $errores['apellido'] = 'El apellido es obligatorio.';
    } else {
        // Usar función centralizada para validar apellido
        $validacion_apellido = validarNombreApellido($apellido_raw, 'apellido');
        
        if (!$validacion_apellido['valido']) {
            $errores['apellido'] = $validacion_apellido['error'];
        } else {
            // Validación exitosa - usar valor sin sanitizar para guardar en BD
            // La función validarNombreApellido() retorna valor sanitizado, pero necesitamos el original
            $apellido = $apellido_trimmed;
        }
    }
    
    // Validar EMAIL usando función centralizada
    $email_raw = $_POST['email'] ?? '';
    $valores_form['email'] = $email_raw;
    
    $validacion_email = validarEmail($email_raw);
    if (!$validacion_email['valido']) {
        $errores['email'] = $validacion_email['error'];
    } else {
        // Convertir a minúsculas antes de guardar (normalización)
        // NOTA: La función validarEmail() ya sanitiza con htmlspecialchars(),
        // pero para guardar en BD necesitamos el valor original sin sanitizar
        $email = strtolower(trim($email_raw));
    }
    
    // Sanitizar y validar CONTRASEÑA
    // IMPORTANTE: NO usar trim() en password - puede cambiar la contraseña
    $password = $_POST['password'] ?? '';
    
    // Validaciones básicas para CONTRASEÑA (permite contraseñas débiles)
    // Solo se requiere longitud mínima, sin requisitos de complejidad
    if ($password === '' || strlen($password) === 0) {
        $errores['password'] = 'La contraseña es obligatoria.';
    } elseif (strlen($password) < 6) {
        $errores['password'] = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif (strlen($password) > 32) {
        $errores['password'] = 'La contraseña no puede exceder 32 caracteres.';
    }
    // Nota: Se eliminó la validación de complejidad (mayúscula, minúscula, número, carácter especial)
    // para permitir contraseñas más débiles según lo solicitado
    
    // Validar CONFIRMACIÓN DE CONTRASEÑA (OPCIONAL - solo validar si se proporciona)
    // IMPORTANTE: NO usar trim() en password_confirm - debe coincidir exactamente
    $password_confirm = $_POST['password_confirm'] ?? '';
    if (!empty($password_confirm) && $password !== $password_confirm) {
        $errores['password_confirm'] = 'Las contraseñas no coinciden.';
    }
    
    // Validar FECHA DE NACIMIENTO (OBLIGATORIO)
    // HTML5 date input envía formato YYYY-MM-DD directamente
    $fecha_nacimiento_raw = trim($_POST['fecha_nacimiento'] ?? '');
    $valores_form['fecha_nacimiento'] = $fecha_nacimiento_raw;
    
    if (empty($fecha_nacimiento_raw)) {
        $errores['fecha_nacimiento'] = 'La fecha de nacimiento es obligatoria.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_nacimiento_raw)) {
        // Validar formato YYYY-MM-DD
        $errores['fecha_nacimiento'] = 'El formato de fecha de nacimiento no es válido.';
    } else {
        // Validar que sea una fecha válida
        $fecha_parts = explode('-', $fecha_nacimiento_raw);
        $year = intval($fecha_parts[0]);
        $month = intval($fecha_parts[1]);
        $day = intval($fecha_parts[2]);
        
        if (!checkdate($month, $day, $year)) {
            $errores['fecha_nacimiento'] = 'La fecha de nacimiento no es una fecha válida.';
        } else {
            // Validar rango de año permitido (1925-2012)
            if ($year < 1925 || $year > 2012) {
                $errores['fecha_nacimiento'] = 'La fecha de nacimiento debe estar entre 1925 y 2012.';
            } else {
                // Validar fecha futura y edad mínima
                try {
                    $fecha_nac = new DateTime($fecha_nacimiento_raw);
                    $fecha_actual = new DateTime();
                    if ($fecha_nac > $fecha_actual) {
                        $errores['fecha_nacimiento'] = 'La fecha de nacimiento no puede ser una fecha futura.';
                    } else {
                        // Validar edad mínima (13 años)
                        $edad = $fecha_actual->diff($fecha_nac)->y;
                        if ($edad < 13) {
                            $errores['fecha_nacimiento'] = 'Debes tener al menos 13 años para registrarte.';
                        } else {
                            // Validación exitosa - asignar fecha validada
                            $fecha_nacimiento = $fecha_nacimiento_raw;
                        }
                    }
                } catch (Exception $e) {
                    $errores['fecha_nacimiento'] = 'La fecha de nacimiento no es válida.';
                }
            }
        }
    }
    
    // Validar PREGUNTA DE RECUPERO (OBLIGATORIO)
    // NOTA: No usar trim() porque es un valor numérico de select
    $pregunta_recupero = $_POST['pregunta_recupero'] ?? '';
    $valores_form['pregunta_recupero'] = $pregunta_recupero;
    $pregunta_id = null; // Inicializar variable para uso posterior

    if (empty($pregunta_recupero)) {
        $errores['pregunta_recupero'] = 'Debes seleccionar una pregunta de recupero.';
    } else {
        // Convertir a entero (verificarPreguntaRecupero() validará formato y rango internamente)
        $pregunta_id = intval($pregunta_recupero);

        // Verificar que la conversión fue exitosa y es un ID válido
        if ($pregunta_id <= 0) {
            $errores['pregunta_recupero'] = 'La pregunta de recupero seleccionada no es válida.';
        } else {
            // Verificar que la pregunta existe en las preguntas disponibles (BD o por defecto)
            $pregunta_valida = false;
            
            // Primero verificar si existe en el array de preguntas disponibles (ya sea de BD o por defecto)
            foreach ($preguntas_recupero as $pregunta) {
                if (isset($pregunta['id_pregunta']) && intval($pregunta['id_pregunta']) === $pregunta_id) {
                    $pregunta_valida = true;
                    break;
                }
            }
            
            // Si no se encontró en el array local, verificar en la base de datos
            if (!$pregunta_valida && isset($mysqli) && $mysqli instanceof mysqli) {
                $pregunta_valida = verificarPreguntaRecupero($mysqli, $pregunta_id);
            }
            
            // Si aún no es válida, mostrar error
            if (!$pregunta_valida) {
                $errores['pregunta_recupero'] = 'La pregunta de recupero seleccionada no es válida o no está disponible.';
            }
        }
    }
    
    // Validar RESPUESTA DE RECUPERO (OBLIGATORIO)
    // IMPORTANTE: trim() solo elimina espacios al inicio/final, NO los espacios en el medio
    $respuesta_recupero_raw = $_POST['respuesta_recupero'] ?? '';
    $respuesta_recupero = trim($respuesta_recupero_raw);
    // Guardar el valor original (sin trim) para mostrar en el formulario si hay error
    $valores_form['respuesta_recupero'] = $respuesta_recupero_raw;
    
    if (empty($respuesta_recupero)) {
        $errores['respuesta_recupero'] = 'La respuesta de recupero es obligatoria.';
    } elseif (strlen($respuesta_recupero) < 4) {
        $errores['respuesta_recupero'] = 'La respuesta de recupero debe tener al menos 4 caracteres.';
    } elseif (strlen($respuesta_recupero) > 255) {
        $errores['respuesta_recupero'] = 'La respuesta de recupero no puede exceder 255 caracteres.';
    } elseif (!preg_match('/^[a-zA-Z0-9 ]+$/', $respuesta_recupero)) {
        $errores['respuesta_recupero'] = 'La respuesta de recupero solo puede contener letras, números y espacios.';
    } else {
        // Convertir a minúsculas para normalización (pero guardar original)
        $respuesta_recupero = strtolower($respuesta_recupero);
    }
    
    // Validar ACEPTACIÓN DE TÉRMINOS Y CONDICIONES (OBLIGATORIO)
    $acepta_terminos = isset($_POST['acepta']) && $_POST['acepta'] === 'on';
    
    if (!$acepta_terminos) {
        $errores['acepta'] = 'Debes aceptar los términos y condiciones para continuar.';
    }
    
    // ========================================================================
    // VALIDACIÓN ADICIONAL DE SEGURIDAD
    // ========================================================================
    
    /**
     * PREVENCIÓN DE ATAQUES DE FUERZA BRUTA
     * Verificar si hay demasiados intentos de registro desde la misma IP
     */
    $ip_usuario = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Validar que no sea un bot o script automatizado
    if (empty($user_agent) || strlen($user_agent) < 10) {
        $errores['general'] = 'Acceso no autorizado detectado.';
    }
    
    // Verificar que no contenga caracteres peligrosos en User-Agent
    if (preg_match('/<script|javascript|vbscript|onload|onerror/i', $user_agent)) {
        $errores['general'] = 'User-Agent no válido detectado.';
    }
    
    // ========================================================================
    // VERIFICACIÓN DE EMAIL DUPLICADO (solo si no hay errores en email)
    // ========================================================================
    // Nota: $mysqli ya está disponible desde la línea 48 (obtener preguntas de recupero)
    // IMPORTANTE: Usar email en minúsculas para comparar (normalización)
    if (!isset($errores['email'])) {
        $email_busqueda = strtolower(trim($email_raw));
        if (verificarEmailExistente($mysqli, $email_busqueda)) {
            $errores['email'] = 'El email ya está registrado. Por favor, usa otro correo electrónico.';
        }
    }
    
    // ========================================================================
    // PROCESAR REGISTRO SI TODAS LAS VALIDACIONES PASAN
    // ========================================================================
    
    if (empty($errores)) {
        // ========================================================================
        // CONEXIÓN SEGURA A BASE DE DATOS - PREVENCIÓN SQL INJECTION
        // ========================================================================
        
        /**
         * PREPARED STATEMENTS - PREVENCIÓN SQL INJECTION
         * 
         * 1. mysqli->prepare(): Prepara la consulta SQL con placeholders (?)
         * 2. bind_param(): Vincula parámetros de forma segura
         * 3. execute(): Ejecuta la consulta sin riesgo de inyección
         * 
         * Ventajas:
         * - Separa código SQL de datos
         * - Previene inyección de código malicioso
         * - Mejor rendimiento en consultas repetidas
         */
        
        // Nota: La conexión $mysqli ya está disponible desde la línea 48 (obtener preguntas de recupero)
        
        // ========================================================================
        // CREACIÓN SEGURA DE USUARIO - HASH DE CONTRASEÑA
        // ========================================================================
        
        /**
         * HASH SEGURO DE CONTRASEÑA
         * 
         * Usa función centralizada generarHashPassword():
         * - Usa el algoritmo más seguro disponible (actualmente bcrypt, puede cambiar a argon2)
         * - Salt automático único por contraseña
         * - Configuración automática optimizada
         * - Compatible con versiones futuras de PHP
         * - Resistente a ataques de fuerza bruta y tiempo
         * - Verifica que el hash funciona antes de retornarlo
         */
        // Generar hash usando función centralizada
        $hash_password = generarHashPassword($password, $mysqli);
        
        if ($hash_password === false) {
            $errores['password'] = 'Error al procesar la contraseña. Inténtalo de nuevo.';
        } else {
            // ========================================================================
            // INSERCIÓN SEGURA EN BASE DE DATOS
            // ========================================================================
            
            /**
             * CREACIÓN DE USUARIO CLIENTE
             * 
             * Campos insertados:
             * - nombre: Sanitizado con htmlspecialchars()
             * - apellido: Sanitizado con htmlspecialchars() (obligatorio)
             * - email: Validado con filter_var() y sanitizado
             * - contrasena: Hash seguro con generarHashPassword()
             * - rol: Valor fijo 'cliente'
             * - fecha_nacimiento: Fecha validada
             * - pregunta_recupero: ID de pregunta seleccionada
             * - respuesta_recupero: Respuesta normalizada
             * - fecha_registro: NOW() función MySQL
             */
            // Asegurar que la conexión esté configurada antes de insertar
            configurarConexionBD($mysqli);
            
            // Usar $pregunta_id ya validado (no recalcular)
            // La variable $pregunta_id está disponible en este scope porque fue definida en la validación
            if ($pregunta_id === null || $pregunta_id <= 0) {
                $errores['general'] = 'Error: La pregunta de recupero no está validada correctamente.';
            } elseif ($nombre === null || $apellido === null || $email === null || $fecha_nacimiento === null || $respuesta_recupero === null) {
                // Verificar que todas las variables requeridas estén definidas
                $errores['general'] = 'Error: Faltan datos requeridos para crear la cuenta. Por favor, completa todos los campos.';
            } else {
                // Verificar que la pregunta existe en la base de datos antes de insertar
                // Esto previene errores de clave foránea
                if (!verificarPreguntaRecupero($mysqli, $pregunta_id)) {
                    $errores['pregunta_recupero'] = 'La pregunta de recupero seleccionada no existe en la base de datos. Por favor, selecciona otra pregunta.';
                } else {
                    // La fecha ya está validada y en formato YYYY-MM-DD
                    // Crear usuario cliente usando función centralizada con el ID ya validado
                    $id_usuario_nuevo = crearUsuarioCliente($mysqli, $nombre, $apellido, $email, $hash_password, $fecha_nacimiento, $pregunta_id, $respuesta_recupero);
                
                    if ($id_usuario_nuevo > 0) {
                        // Verificar que el hash se guardó correctamente
                        if (verificarHashContrasena($mysqli, $id_usuario_nuevo, $password)) {
                            // ========================================================================
                            // REGISTRO EXITOSO - LIMPIEZA Y REDIRECCIÓN
                            // ========================================================================
                            
                            /**
                             * LIMPIEZA DE DATOS SENSIBLES
                             * 
                             * 1. Limpiar variables de contraseña de memoria
                             * 2. Redireccionar con parámetro de éxito
                             */
                            
                            // Limpiar variables sensibles de memoria
                            $password = null;
                            $password_confirm = null;
                            $hash_password = null;
                            
                            // Guardar mensaje de éxito en sesión
                            $_SESSION['mensaje_registro'] = 'Tu cuenta ha sido creada exitosamente. Por favor, inicia sesión.';
                            $_SESSION['mensaje_registro_tipo'] = 'success';
                            
                            // Redireccionar a login
                            header('Location: login.php');
                            exit;
                        } else {
                            // Hash no verifica correctamente
                            $errores['general'] = 'Error: El hash se guardó pero no verifica correctamente. Por favor, intenta registrarte nuevamente.';
                            // Soft delete: marcar usuario como inactivo (rollback de registro fallido)
                            desactivarUsuario($mysqli, $id_usuario_nuevo);
                        }
                    } else {
                        // Error en la creación del usuario
                        // El error ya fue registrado en crearUsuarioCliente()
                        $errores['general'] = 'Error al crear la cuenta. Por favor, verifica que todos los datos sean correctos e inténtalo de nuevo. Si el problema persiste, contacta al administrador.';
                    }
                } // Cierre del else que verifica pregunta en BD
            }
        }
    }
    } // Cierre del else que verifica la conexión $mysqli
}
?>

<?php include 'includes/header.php'; ?>


<!-- Contenido principal del registro -->
<main class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-logo">
                    <h2>SEDA Y LINO</h2>
                    <p>Únete a nuestra comunidad</p>
                </div>
                
                <h3 class="auth-title">Crear Cuenta</h3>
                
                <?php if (isset($errores['general'])): ?>
                    <div class="alert alert-danger mb-4">
                        <?= htmlspecialchars($errores['general']) ?>
                    </div>
                <?php endif; ?>
                
                <form action="" method="post" class="auth-form" id="registerForm" novalidate autocomplete="on">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">
                                <i class="fas fa-user me-1"></i>Nombre <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control <?= isset($errores['nombre']) ? 'is-invalid' : '' ?>" 
                                   name="nombre" 
                                   id="nombre" 
                                   value="<?= htmlspecialchars($valores_form['nombre'] ?? '') ?>"
                                   placeholder="Tu nombre" 
                                   required
                                   minlength="2"
                                   maxlength="50"
                                   pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+"
                                   autocomplete="given-name"
                                   title="Solo letras y espacios, entre 2 y 50 caracteres">
                            <?php if (isset($errores['nombre'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['nombre']) ?></div>
                            <?php else: ?>
                                <div class="invalid-feedback">Por favor, ingresa tu nombre.</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="apellido" class="form-label">
                                <i class="fas fa-user me-1"></i>Apellido <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control <?= isset($errores['apellido']) ? 'is-invalid' : '' ?>" 
                                   name="apellido" 
                                   id="apellido" 
                                   value="<?= htmlspecialchars($valores_form['apellido'] ?? '') ?>"
                                   placeholder="Tu apellido" 
                                   required
                                   minlength="2"
                                   maxlength="100"
                                   pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+"
                                   autocomplete="family-name"
                                   title="Solo letras y espacios, entre 2 y 100 caracteres">
                            <?php if (isset($errores['apellido'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['apellido']) ?></div>
                            <?php else: ?>
                                <div class="invalid-feedback">Por favor, ingresa tu apellido.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope me-1"></i>Correo Electrónico <span class="text-danger">*</span>
                        </label>
                        <input type="email" 
                               class="form-control <?= isset($errores['email']) ? 'is-invalid' : '' ?>" 
                               name="email" 
                               id="email" 
                               value="<?= htmlspecialchars($valores_form['email'] ?? '') ?>"
                               placeholder="tu@email.com" 
                               required
                               maxlength="100"
                               autocomplete="email"
                               title="Formato de email válido, máximo 100 caracteres">
                        <?php if (isset($errores['email'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errores['email']) ?></div>
                        <?php else: ?>
                            <div class="invalid-feedback">Por favor, ingresa un correo electrónico válido.</div>
                            <div class="valid-feedback">Correo válido</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fecha_nacimiento" class="form-label">
                            <i class="fas fa-calendar me-1"></i>Fecha de Nacimiento <span class="text-danger">*</span>
                        </label>
                        <input type="date" 
                               class="form-control <?= isset($errores['fecha_nacimiento']) ? 'is-invalid' : '' ?>" 
                               name="fecha_nacimiento" 
                               id="fecha_nacimiento" 
                               required
                               max="2012-12-31"
                               min="1925-01-01"
                               value="<?= !empty($valores_form['fecha_nacimiento']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valores_form['fecha_nacimiento']) ? htmlspecialchars($valores_form['fecha_nacimiento']) : '' ?>">
                        <?php if (isset($errores['fecha_nacimiento'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errores['fecha_nacimiento']) ?></div>
                        <?php else: ?>
                            <div class="invalid-feedback">Por favor, ingresa tu fecha de nacimiento.</div>
                        <?php endif; ?>
                        <small class="form-text text-muted">
                            <i class="fas fa-info-circle me-1"></i>Debes tener al menos 13 años
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="pregunta_recupero" class="form-label">
                            <i class="fas fa-question-circle me-1"></i>Pregunta de Recupero <span class="text-danger">*</span>
                        </label>
                        <select class="form-select <?= isset($errores['pregunta_recupero']) ? 'is-invalid' : '' ?>" 
                                name="pregunta_recupero" 
                                id="pregunta_recupero" 
                                required>
                            <option value="">Selecciona una pregunta...</option>
                            <?php foreach ($preguntas_recupero as $pregunta): ?>
                                <option value="<?= $pregunta['id_pregunta'] ?>" 
                                        <?= (isset($valores_form['pregunta_recupero']) && $valores_form['pregunta_recupero'] == $pregunta['id_pregunta']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pregunta['texto_pregunta']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errores['pregunta_recupero'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errores['pregunta_recupero']) ?></div>
                        <?php else: ?>
                            <div class="invalid-feedback">Por favor, selecciona una pregunta.</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="respuesta_recupero" class="form-label">
                            <i class="fas fa-key me-1"></i>Respuesta de Recupero <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control <?= isset($errores['respuesta_recupero']) ? 'is-invalid' : '' ?>" 
                               name="respuesta_recupero" 
                               id="respuesta_recupero" 
                               value="<?= htmlspecialchars($valores_form['respuesta_recupero'] ?? '') ?>"
                               placeholder="Tu respuesta (entre 4 y 255 caracteres)" 
                               required
                               minlength="4"
                               maxlength="255"
                               pattern="[a-zA-Z0-9 ]+"
                               title="Letras, números y espacios, entre 4 y 255 caracteres">
                        <?php if (isset($errores['respuesta_recupero'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errores['respuesta_recupero']) ?></div>
                        <?php else: ?>
                            <div class="invalid-feedback">Por favor, ingresa una respuesta válida.</div>
                        <?php endif; ?>
                        <small class="form-text text-muted">
                            <i class="fas fa-info-circle me-1"></i>Letras, números y espacios, entre 4 y 255 caracteres
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-1"></i>Contraseña <span class="text-danger">*</span>
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   class="form-control <?= isset($errores['password']) ? 'is-invalid' : '' ?>" 
                                   name="password" 
                                   id="password" 
                                   placeholder="Mínimo 6 caracteres" 
                                   required 
                                   minlength="6"
                                   maxlength="32"
                                   autocomplete="new-password"
                                   title="Mínimo 6 caracteres, máximo 32 caracteres">
                            <button type="button" class="btn-toggle-password" id="togglePassword" aria-label="Mostrar contraseña">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <?php if (isset($errores['password'])): ?>
                            <div class="invalid-feedback d-block"><?= htmlspecialchars($errores['password']) ?></div>
                        <?php endif; ?>
                        <div class="password-strength mt-2" id="passwordStrength">
                            <div class="strength-meter">
                                <div class="strength-meter-fill" id="strengthMeterFill"></div>
                            </div>
                            <small class="strength-text" id="strengthText"></small>
                        </div>
                        <small class="form-text text-muted">
                            <i class="fas fa-info-circle me-1"></i>Mínimo 6 caracteres (se recomienda usar una contraseña más segura)
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">
                            <i class="fas fa-lock me-1"></i>Confirmar Contraseña
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   class="form-control <?= isset($errores['password_confirm']) ? 'is-invalid' : '' ?>" 
                                   name="password_confirm" 
                                   id="password_confirm" 
                                   placeholder="Repite tu contraseña (opcional)" 
                                   minlength="6"
                                   maxlength="32"
                                   autocomplete="new-password"
                                   title="Debe coincidir con la contraseña anterior">
                            <button type="button" class="btn-toggle-password" id="togglePasswordConfirm" aria-label="Mostrar contraseña">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <?php if (isset($errores['password_confirm'])): ?>
                            <div class="invalid-feedback d-block"><?= htmlspecialchars($errores['password_confirm']) ?></div>
                        <?php else: ?>
                            <div class="invalid-feedback">Las contraseñas no coinciden.</div>
                            <div class="valid-feedback">Las contraseñas coinciden</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input class="form-check-input <?= isset($errores['acepta']) ? 'is-invalid' : '' ?>" type="checkbox" id="acepta" name="acepta" required>
                        <label class="form-check-label form-check-label-custom" for="acepta">
                            <a href="terminos.php" target="_blank">Términos y Condiciones</a> <span class="text-danger">*</span>
                        </label>
                        <?php if (isset($errores['acepta'])): ?>
                            <div class="invalid-feedback d-block"><?= htmlspecialchars($errores['acepta']) ?></div>
                        <?php else: ?>
                            <div class="invalid-feedback">Debes aceptar los términos y condiciones para continuar.</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <button type="submit" class="btn auth-btn" id="registerBtn">
                            <span class="btn-text">Crear Cuenta</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                Creando cuenta...
                            </span>
                        </button>
                    </div>
                </form>
                
                <div class="auth-links">
                    <div class="mb-2">
                        <a href="login.php">¿Ya tienes cuenta? Inicia sesión aquí</a>
                    </div>
                    <div>
                        <a href="index.php">Volver al inicio</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="/includes/register.js"></script>

<?php include 'includes/footer.php'; render_footer(); ?>
