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
// MANEJO DE ERRORES
// ========================================================================
// NOTA: En producción, los errores se registran en el log del servidor
ini_set('log_errors', 1);

session_start();

// DEBUG MODE - Mostrar TODOS los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Cargar funciones de contraseñas
$password_functions_path = __DIR__ . '/includes/password_functions.php';
if (!file_exists($password_functions_path)) {
    error_log("ERROR: No se pudo encontrar password_functions.php en " . $password_functions_path);
    die("Error crítico: Archivo de funciones de contraseña no encontrado. Por favor, contacta al administrador.");
}
require_once $password_functions_path;

// Cargar funciones de consultas de perfil
$perfil_queries_path = __DIR__ . '/includes/queries/perfil_queries.php';
if (!file_exists($perfil_queries_path)) {
    error_log("ERROR: No se pudo encontrar perfil_queries.php en " . $perfil_queries_path);
    die("Error crítico: Archivo de consultas de perfil no encontrado. Por favor, contacta al administrador.");
}
require_once $perfil_queries_path;

// Cargar funciones de administración
// NOTA: admin_functions.php ya incluye usuario_queries.php, no es necesario incluirlo por separado
$admin_functions_path = __DIR__ . '/includes/admin_functions.php';
if (!file_exists($admin_functions_path)) {
    error_log("ERROR: No se pudo encontrar admin_functions.php en " . $admin_functions_path);
    die("Error crítico: Archivo de funciones de administración no encontrado. Por favor, contacta al administrador.");
}
require_once $admin_functions_path; // Incluye usuario_queries.php

// Cargar funciones de validación centralizadas adicionales
$validation_functions_path = __DIR__ . '/includes/validation_functions.php';
if (!file_exists($validation_functions_path)) {
    error_log("ERROR: No se pudo encontrar validation_functions.php en " . $validation_functions_path);
    die("Error crítico: Archivo de funciones de validación no encontrado. Por favor, contacta al administrador.");
}
require_once $validation_functions_path;

// Cargar funciones de email con Gmail SMTP
require_once __DIR__ . '/includes/email_gmail_functions.php';

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
    // DEBUG: Mostrar POST recibido
    echo "<hr><pre style='background:#fffacd; padding:10px; border:2px solid #ff0000;'>";
    echo "<strong>DEBUG: POST RECIBIDO</strong>\n";
    echo "Total campos: " . count($_POST) . "\n";
    echo "Contenido POST:\n";
    foreach ($_POST as $key => $value) {
        if ($key !== 'password' && $key !== 'password_confirm') {
            echo "  $key = " . htmlspecialchars(substr($value, 0, 50)) . "\n";
        } else {
            echo "  $key = [ESCONDIDO POR SEGURIDAD]\n";
        }
    }
    echo "</pre><hr>\n";

    // Iniciar output buffering para prevenir errores de headers ya enviados
    ob_start();

    try {
        // Verificar que la conexión a la base de datos esté disponible
        if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
            throw new Exception('Error de conexión a la base de datos. Por favor, intenta nuevamente.');
        }

        echo "<pre style='background:#e8f4f8; padding:10px;'><strong>DEBUG: BD conectada correctamente</strong></pre>\n";
    
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
            // Validación exitosa - usar valor original para guardar en BD
            // NOTA: NO sanitizar con htmlspecialchars() antes de guardar en BD
            // Los datos se sanitizan al MOSTRAR en HTML, no al guardar
            // La función validarNombreApellido() retorna valor original sin sanitizar
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
            // Validación exitosa - usar valor original para guardar en BD
            // NOTA: NO sanitizar con htmlspecialchars() antes de guardar en BD
            // Los datos se sanitizan al MOSTRAR en HTML, no al guardar
            // La función validarNombreApellido() retorna valor original sin sanitizar
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
        // Normalizar email usando función centralizada
        // NOTA: La función validarEmail() ya sanitiza con htmlspecialchars(),
        // pero para guardar en BD necesitamos el valor original sin sanitizar
        $email = normalizarEmail($email_raw);
    }
    
    // Validar CONTRASEÑA usando función centralizada
    // IMPORTANTE: NO usar trim() en password - puede cambiar la contraseña
    $password = $_POST['password'] ?? '';
    
    // Usar función centralizada que valida: longitud 6-32 (sin requerir complejidad)
    $validacion_password = validarPassword($password, false); // false = no requiere complejidad
    if (!$validacion_password['valido']) {
        $errores['password'] = $validacion_password['error'];
    }
    
    // Validar CONFIRMACIÓN DE CONTRASEÑA (OBLIGATORIO)
    // IMPORTANTE: NO usar trim() en password_confirm - debe coincidir exactamente
    $password_confirm = $_POST['password_confirm'] ?? '';
    if (empty($password_confirm)) {
        $errores['password_confirm'] = 'La confirmación de contraseña es obligatoria.';
    } elseif ($password !== $password_confirm) {
        $errores['password_confirm'] = 'Las contraseñas no coinciden.';
    }
    
    // Validar FECHA DE NACIMIENTO usando función centralizada (OBLIGATORIO)
    // HTML5 date input envía formato YYYY-MM-DD directamente
    $fecha_nacimiento_raw = trim($_POST['fecha_nacimiento'] ?? '');
    $valores_form['fecha_nacimiento'] = $fecha_nacimiento_raw;
    
    if (empty($fecha_nacimiento_raw)) {
        $errores['fecha_nacimiento'] = 'La fecha de nacimiento es obligatoria.';
    } else {
        // Usar función centralizada que valida: formato, rango de años (1925 - Actualidad), edad mínima (13 años), no futura
        $validacion_fecha = validarFechaNacimiento($fecha_nacimiento_raw);
        if (!$validacion_fecha['valido']) {
            $errores['fecha_nacimiento'] = $validacion_fecha['error'];
        } else {
            // Validación exitosa - asignar fecha validada
            $fecha_nacimiento = $validacion_fecha['valor'];
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
            // Verificar que la pregunta existe en la base de datos
            if (!verificarPreguntaRecupero($mysqli, $pregunta_id)) {
                $errores['pregunta_recupero'] = 'La pregunta de recupero seleccionada no es válida o no está disponible.';
            }
        }
    }
    
    // Validar RESPUESTA DE RECUPERO (OBLIGATORIO) usando función centralizada
    $respuesta_recupero_raw = $_POST['respuesta_recupero'] ?? '';
    // Guardar el valor original para mostrar en el formulario si hay error
    $valores_form['respuesta_recupero'] = $respuesta_recupero_raw;
    
    $validacion_respuesta = validarRespuestaRecupero($respuesta_recupero_raw);
    if (!$validacion_respuesta['valido']) {
        $errores['respuesta_recupero'] = $validacion_respuesta['error'];
    } else {
        $respuesta_recupero = $validacion_respuesta['valor'];
    }
    // Nota: El hash se genera después de validar todos los campos (ver más abajo)
    
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
    // IMPORTANTE: Usar email normalizado para comparar
    if (!isset($errores['email'])) {
        $email_busqueda = normalizarEmail($email_raw);
        if (verificarEmailExistente($mysqli, $email_busqueda)) {
            $errores['email'] = 'Este correo electrónico ya se encuentra registrado.';
        }
    }
    
    // ========================================================================
    // PROCESAR REGISTRO SI TODAS LAS VALIDACIONES PASAN
    // ========================================================================

    // DEBUG: Estado de validaciones
    echo "<pre style='background:#fff0f5; padding:10px;'><strong>DEBUG: VALIDACIONES COMPLETADAS</strong>\n";
    echo "Total de errores: " . count($errores) . "\n";
    if (!empty($errores)) {
        echo "Errores encontrados:\n";
        foreach ($errores as $campo => $error) {
            echo "  $campo: $error\n";
        }
    } else {
        echo "✓ SIN ERRORES - Procederá a crear usuario\n";
    }
    echo "</pre>\n";

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
            // Verificar longitud del hash antes de crear el usuario
            $hash_length = strlen($hash_password);
            if ($hash_length < 60) {
                error_log("ERROR register.php: Hash generado tiene longitud incorrecta: $hash_length caracteres (mínimo esperado: 60)");
                $errores['password'] = 'Error al procesar la contraseña. Inténtalo de nuevo.';
                $hash_password = false;
            } elseif ($hash_length > 255) {
                error_log("ERROR register.php: Hash generado excede longitud máxima: $hash_length caracteres (máximo: 255)");
                $errores['password'] = 'Error al procesar la contraseña. Inténtalo de nuevo.';
                $hash_password = false;
            }
        }
        
        if ($hash_password !== false) {
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
             * - respuesta_recupero: Hash de la respuesta de recupero
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
                    // Generar hash de la respuesta de recupero usando función centralizada
                    $hash_respuesta_recupero = generarHashRespuestaRecupero($respuesta_recupero, $mysqli);
                    
                    if ($hash_respuesta_recupero === false) {
                        $errores['respuesta_recupero'] = 'Error al procesar la respuesta de recupero. Inténtalo de nuevo.';
                    } else {
                        // La fecha ya está validada y en formato YYYY-MM-DD
                        // DEBUG: Antes de insertar
                        echo "<pre style='background:#f0fff0; padding:10px;'><strong>DEBUG: ANTES DE INSERTAR EN BD</strong>\n";
                        echo "Nombre: " . htmlspecialchars($nombre) . "\n";
                        echo "Apellido: " . htmlspecialchars($apellido) . "\n";
                        echo "Email: " . htmlspecialchars($email) . "\n";
                        echo "Fecha nacimiento: $fecha_nacimiento\n";
                        echo "Pregunta ID: $pregunta_id\n";
                        echo "Hash password length: " . strlen($hash_password) . "\n";
                        echo "Hash respuesta length: " . strlen($hash_respuesta_recupero) . "\n";
                        echo "</pre>\n";

                        // Crear usuario cliente usando función centralizada con el ID ya validado
                        $id_usuario_nuevo = crearUsuarioCliente($mysqli, $nombre, $apellido, $email, $hash_password, $fecha_nacimiento, $pregunta_id, $hash_respuesta_recupero);

                        // DEBUG: Después de insertar
                        echo "<pre style='background:#fff8dc; padding:10px;'><strong>DEBUG: DESPUÉS DE INSERTAR EN BD</strong>\n";
                        echo "ID usuario nuevo: " . var_export($id_usuario_nuevo, true) . "\n";
                        echo "Inserción exitosa: " . ($id_usuario_nuevo > 0 ? '✓ SÍ' : '✗ NO') . "\n";
                        echo "</pre>\n";

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
                            
                            // Guardar mensaje de éxito en sesión ANTES de enviar email
                            // Esto asegura que el mensaje se guarde incluso si el email falla
                            $_SESSION['mensaje_registro'] = 'Tu cuenta ha sido creada exitosamente. Por favor, inicia sesión.';
                            $_SESSION['mensaje_registro_tipo'] = 'success';
                            
                            // Enviar email de bienvenida (no bloquear flujo si falla)
                            // Usar try-catch para capturar tanto Exception como Error
                            try {
                                $email_enviado = enviar_email_bienvenida($nombre, $apellido, $email);
                                if (!$email_enviado) {
                                    error_log("No se pudo enviar email de bienvenida para usuario ID: $id_usuario_nuevo (email: $email)");
                                }
                            } catch (Exception $e) {
                                // Solo loggear error, no interrumpir el flujo
                                error_log("Error al enviar email de bienvenida para usuario ID: $id_usuario_nuevo. Error: " . $e->getMessage());
                            } catch (Error $e) {
                                // Capturar también errores fatales de PHP
                                error_log("Error fatal al enviar email de bienvenida para usuario ID: $id_usuario_nuevo. Error: " . $e->getMessage());
                            }
                            
                            // Redirigir a login (después de guardar sesión y enviar email)
                            // Limpiar cualquier output previo antes de redirigir
                            ob_clean();
                            
                            if (!headers_sent()) {
                                header('Location: login.php');
                                exit;
                            } else {
                                // Si ya se envió output, usar JavaScript para redirigir
                                echo '<script>window.location.href = "login.php";</script>';
                                exit;
                            }
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
                } // Cierre del else que verifica hash de respuesta
            } // Cierre del else que verifica pregunta en BD
        }
    }
    } // Cierre del try block
} catch (Exception $e) {
    echo "<pre style='background:#ffe4e1; border:3px solid red; padding:10px;'><strong>⚠️ EXCEPTION CAPTURADA:</strong>\n";
    echo "Mensaje: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "Archivo: " . htmlspecialchars($e->getFile()) . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Trace:\n" . htmlspecialchars($e->getTraceAsString()) . "\n";
    echo "</pre>\n";
    error_log("Error en registro: " . $e->getMessage());
    $errores['general'] = $e->getMessage();
} catch (Error $e) {
    echo "<pre style='background:#ffe4e1; border:3px solid red; padding:10px;'><strong>⚠️ ERROR FATAL CAPTURADO:</strong>\n";
    echo "Mensaje: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "Archivo: " . htmlspecialchars($e->getFile()) . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Trace:\n" . htmlspecialchars($e->getTraceAsString()) . "\n";
    echo "</pre>\n";
    error_log("Error fatal en registro: " . $e->getMessage());
    $errores['general'] = "Ocurrió un error inesperado al procesar tu registro. Por favor, intenta más tarde.";
}
} // Cierre del if ($_SERVER['REQUEST_METHOD'] === 'POST')

// Incluir header solo si no se hizo redirección (es decir, si hay errores o es GET)
include 'includes/header.php';
?>

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
                                   pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]+"
                                   autocomplete="given-name"
                                   title="Letras, espacios, apóstrofe (') y acento agudo (´), entre 2 y 50 caracteres">
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
                                   pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]+"
                                   autocomplete="family-name"
                                   title="Letras, espacios, apóstrofe (') y acento agudo (´), entre 2 y 100 caracteres">
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
                               max="<?= date('Y-m-d', strtotime('-13 years')) ?>"
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
                            <button type="button" class="btn-toggle-password" data-input-id="password" id="togglePassword" aria-label="Mostrar contraseña">
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
                    </div>
                    
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">
                            <i class="fas fa-lock me-1"></i>Confirmar Contraseña <span class="text-danger">*</span>
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   class="form-control <?= isset($errores['password_confirm']) ? 'is-invalid' : '' ?>" 
                                   name="password_confirm" 
                                   id="password_confirm" 
                                   placeholder="Repite tu contraseña" 
                                   minlength="6"
                                   maxlength="32"
                                   required
                                   autocomplete="new-password"
                                   title="Debe coincidir con la contraseña anterior">
                            <button type="button" class="btn-toggle-password" data-input-id="password_confirm" id="togglePasswordConfirm" aria-label="Mostrar contraseña">
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

    <script src="js/register.js"></script>

<?php include 'includes/footer.php'; render_footer(); ?>
