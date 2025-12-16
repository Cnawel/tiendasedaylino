<?php
/**
 * ========================================================================
 * FUNCIONES DE PERFIL - Tienda Seda y Lino
 * ========================================================================
 * Funciones helper para el perfil de usuario
 * 
 * Funcionalidades:
 * - Funciones para procesar formularios del perfil (actualización de datos, cambio de contraseña, etc.)
 * 
 * Funciones de procesamiento:
 * - procesarActualizacionRecupero(): Actualiza pregunta/respuesta de recupero
 * - procesarActualizacionDatos(): Actualiza datos personales del usuario
 * - procesarCambioContrasena(): Procesa cambio de contraseña
 * - procesarEliminacionCuenta(): Procesa eliminación de cuenta
 * 
 * @package TiendaSedaYLino
 * @version 2.0
 * ========================================================================
 */

// NOTA: PHPMailer se carga solo cuando se necesita dentro de procesarEliminacionCuenta()
// para evitar errores si las dependencias no están disponibles en el hosting

// Cargar funciones de validación centralizadas
$admin_functions_path = __DIR__ . '/admin_functions.php';
if (!file_exists($admin_functions_path)) {
    error_log("ERROR: No se pudo encontrar admin_functions.php en " . $admin_functions_path);
    die("Error crítico: Archivo de funciones de administración no encontrado. Por favor, contacta al administrador.");
}
require_once $admin_functions_path;

// Cargar funciones de validación centralizadas adicionales
$validation_functions_path = __DIR__ . '/validation_functions.php';
if (!file_exists($validation_functions_path)) {
    error_log("ERROR: No se pudo encontrar validation_functions.php en " . $validation_functions_path);
    die("Error crítico: Archivo de funciones de validación no encontrado. Por favor, contacta al administrador.");
}
require_once $validation_functions_path;

// Cargar funciones de contraseñas
$password_functions_path = __DIR__ . '/password_functions.php';
if (!file_exists($password_functions_path)) {
    error_log("ERROR: No se pudo encontrar password_functions.php en " . $password_functions_path);
    die("Error crítico: Archivo de funciones de contraseña no encontrado. Por favor, contacta al administrador.");
}
require_once $password_functions_path;

/**
 * Procesa la actualización de pregunta y respuesta de recupero
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @param array $post Datos POST del formulario
 * @return array Array con 'mensaje' y 'mensaje_tipo' (success/danger)
 */
function procesarActualizacionRecupero($mysqli, $id_usuario, $post) {
    $mensaje = '';
    $mensaje_tipo = '';
    
    // Validar y procesar pregunta de recupero (opcional)
    $pregunta_recupero = trim($post['pregunta_recupero'] ?? '');
    $pregunta_recupero_id = null;
    if (!empty($pregunta_recupero)) {
        $pregunta_id = intval($pregunta_recupero);
        if ($pregunta_id <= 0) {
            $mensaje = 'La pregunta de recupero seleccionada no es válida.';
            $mensaje_tipo = 'danger';
        } else {
            // Verificar que la pregunta existe y está activa
            if (!verificarPreguntaRecupero($mysqli, $pregunta_id)) {
                $mensaje = 'La pregunta de recupero seleccionada no existe o no está disponible.';
                $mensaje_tipo = 'danger';
            } else {
                $pregunta_recupero_id = $pregunta_id;
            }
        }
    }
    
    // Validar y procesar respuesta de recupero (opcional, pero si hay pregunta debe haber respuesta)
    $respuesta_recupero = trim($post['respuesta_recupero'] ?? '');
    $respuesta_recupero_final = null;
    if (!empty($respuesta_recupero)) {
                if (strlen($respuesta_recupero) < 4) {
            if (empty($mensaje)) {
                $mensaje = 'La respuesta de recupero debe tener al menos 4 caracteres.';
                $mensaje_tipo = 'danger';
            }
        } elseif (strlen($respuesta_recupero) > 20) {
            if (empty($mensaje)) {
                $mensaje = 'La respuesta de recupero no puede exceder 20 caracteres.';
                $mensaje_tipo = 'danger';
            }
        } elseif (!preg_match('/^[a-zA-Z0-9 ]+$/', $respuesta_recupero)) {
            if (empty($mensaje)) {
                $mensaje = 'La respuesta de recupero solo puede contener letras, números y espacios.';
                $mensaje_tipo = 'danger';
            }
        }
        // Nota: El hash se genera después de validar todos los campos (ver más abajo)
    }
    
    // Validar que si hay pregunta, también haya respuesta
    if (!empty($pregunta_recupero_id) && empty($respuesta_recupero)) {
        if (empty($mensaje)) {
            $mensaje = 'Si seleccionas una pregunta de recupero, debes proporcionar una respuesta.';
            $mensaje_tipo = 'danger';
        }
    }
    
    // Solo actualizar si no hay errores
    if (empty($mensaje)) {
        // Verificar que el usuario existe
        $usuario_actual = obtenerDatosBasicosUsuario($mysqli, $id_usuario);
        
        if ($usuario_actual) {
            // Generar hash de la respuesta de recupero si se proporcionó
            $hash_respuesta_recupero = null;
            if (!empty($respuesta_recupero)) {
                $hash_respuesta_recupero = generarHashRespuestaRecupero($respuesta_recupero, $mysqli);
                if ($hash_respuesta_recupero === false) {
                    $mensaje = 'Error al procesar la respuesta de recupero. Inténtalo de nuevo.';
                    $mensaje_tipo = 'danger';
                }
            }
            
            // Actualizar solo pregunta y respuesta de recupero (si no hubo error al generar hash)
            if (empty($mensaje) && actualizarPreguntaRecupero($mysqli, $id_usuario, $pregunta_recupero_id, $hash_respuesta_recupero)) {
                $mensaje = 'Pregunta de recupero actualizada correctamente';
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'Error al actualizar la pregunta de recupero';
                $mensaje_tipo = 'danger';
            }
        } else {
            $mensaje = 'Error al obtener datos del usuario';
            $mensaje_tipo = 'danger';
        }
    }
    
    return ['mensaje' => $mensaje, 'mensaje_tipo' => $mensaje_tipo];
}

/**
 * Procesa la actualización de datos personales del usuario
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @param array $post Datos POST del formulario
 * @return array Array con 'mensaje', 'mensaje_tipo' y 'datos_actualizados' (nombre, apellido, email)
 */
function procesarActualizacionDatos($mysqli, $id_usuario, $post) {
    $mensaje = '';
    $mensaje_tipo = '';
    $datos_actualizados = [];
    
    // Obtener email actual desde la base de datos (no se puede editar)
    $perfil_queries_path = __DIR__ . '/queries/perfil_queries.php';
    if (!file_exists($perfil_queries_path)) {
        error_log("ERROR: No se pudo encontrar perfil_queries.php en " . $perfil_queries_path);
        die("Error crítico: Archivo de consultas de perfil no encontrado. Por favor, contacta al administrador.");
    }
    require_once $perfil_queries_path;
    $usuario_actual = obtenerDatosBasicosUsuario($mysqli, $id_usuario);
    if (!$usuario_actual) {
        return ['mensaje' => 'Error al obtener datos del usuario', 'mensaje_tipo' => 'danger', 'datos_actualizados' => []];
    }
    $email = strtolower($usuario_actual['email']); // Email actual de la BD (no editable)
    
    // Campos de dirección separados (igual que checkout.php)
    // Solo validar si se están enviando campos de envío (para permitir actualizaciones parciales)
    $direccion_calle = trim($post['direccion_calle'] ?? '');
    $direccion_numero = trim($post['direccion_numero'] ?? '');
    $direccion_piso = trim($post['direccion_piso'] ?? '');
    $localidad = trim($post['localidad'] ?? '');
    $provincia = trim($post['provincia'] ?? '');
    $codigo_postal = trim($post['codigo_postal'] ?? '');
    
    // Determinar si se están actualizando datos de envío (si al menos un campo está presente)
    $actualizando_envio = !empty($direccion_calle) || !empty($direccion_numero) || !empty($localidad) || !empty($provincia) || !empty($codigo_postal);
    
    // Determinar si se están actualizando datos personales (si nombre o apellido están presentes en POST)
    $actualizando_personales = isset($post['nombre']) || isset($post['apellido']);
    
    // Campos personales - validar y procesar solo si se están actualizando
    $nombre = trim($post['nombre'] ?? '');
    if (empty($nombre)) {
        $nombre = $usuario_actual['nombre'] ?? '';
    }
    
    // Validar APELLIDO solo si se están actualizando datos personales
    $apellido = trim($post['apellido'] ?? '');
    if (empty($apellido)) {
        // Si está vacío, intentar mantener valor actual
        $apellido = $usuario_actual['apellido'] ?? '';
    }
    
    // Validación de apellido: solo si se están actualizando datos personales
    if ($actualizando_personales) {
        $validacion_apellido = validarNombreApellido($apellido, 'apellido');
        if (!$validacion_apellido['valido']) {
            $mensaje = $validacion_apellido['error'];
            $mensaje_tipo = 'danger';
        } else {
            $apellido = $validacion_apellido['valor'];
        }
    }
    
    // Validar teléfono usando función centralizada (opcional)
    $telefono = trim($post['telefono'] ?? '');
    if (!empty($telefono)) {
        $validacion_telefono = validarTelefono($telefono, true);
        if (!$validacion_telefono['valido']) {
            if (empty($mensaje)) {
                $mensaje = $validacion_telefono['error'];
                $mensaje_tipo = 'danger';
            }
        } else {
            $telefono = $validacion_telefono['valor'];
        }
    }
    
    // Validar campos de dirección solo si se están actualizando
    if ($actualizando_envio) {
        // Validar componentes de dirección usando funciones centralizadas
        $validacion_calle = validarDireccion($direccion_calle, true, 2, 'calle');
        if (!$validacion_calle['valido']) {
            $mensaje = $validacion_calle['error'];
            $mensaje_tipo = 'danger';
        }
        
        $validacion_numero = validarDireccion($direccion_numero, true, 1, 'numero');
        if (!$validacion_numero['valido'] && empty($mensaje)) {
            $mensaje = $validacion_numero['error'];
            $mensaje_tipo = 'danger';
        }
        
        // Validar piso (opcional)
        if (!empty($direccion_piso)) {
            $validacion_piso = validarDireccion($direccion_piso, false, 1, 'piso');
            if (!$validacion_piso['valido'] && empty($mensaje)) {
                $mensaje = $validacion_piso['error'];
                $mensaje_tipo = 'danger';
            }
        }
        
        // Validar provincia (longitud 3-100, solo letras y espacios)
        $provincia_trimmed = trim($provincia);
        if (empty($provincia_trimmed)) {
            if (empty($mensaje)) {
                $mensaje = 'La provincia es requerida.';
                $mensaje_tipo = 'danger';
            }
        } elseif (strlen($provincia_trimmed) < 3) {
            if (empty($mensaje)) {
                $mensaje = 'La provincia debe tener al menos 3 caracteres.';
                $mensaje_tipo = 'danger';
            }
        } elseif (strlen($provincia_trimmed) > 100) {
            if (empty($mensaje)) {
                $mensaje = 'La provincia no puede exceder 100 caracteres.';
                $mensaje_tipo = 'danger';
            }
        } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/', $provincia_trimmed)) {
            if (empty($mensaje)) {
                $mensaje = 'La provincia solo puede contener letras y espacios.';
                $mensaje_tipo = 'danger';
            }
        } else {
            $provincia = $provincia_trimmed;
        }
        
        // Validar localidad (longitud 3-100, solo letras y espacios)
        $localidad_trimmed = trim($localidad);
        if (empty($localidad_trimmed)) {
            if (empty($mensaje)) {
                $mensaje = 'La localidad es requerida.';
                $mensaje_tipo = 'danger';
            }
        } elseif (strlen($localidad_trimmed) < 3) {
            if (empty($mensaje)) {
                $mensaje = 'La localidad debe tener al menos 3 caracteres.';
                $mensaje_tipo = 'danger';
            }
        } elseif (strlen($localidad_trimmed) > 100) {
            if (empty($mensaje)) {
                $mensaje = 'La localidad no puede exceder 100 caracteres.';
                $mensaje_tipo = 'danger';
            }
        } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/', $localidad_trimmed)) {
            if (empty($mensaje)) {
                $mensaje = 'La localidad solo puede contener letras y espacios.';
                $mensaje_tipo = 'danger';
            }
        } else {
            $localidad = $localidad_trimmed;
        }
        
        // Validar código postal usando función centralizada
        $validacion_codigo_postal = validarCodigoPostal($codigo_postal);
        if (!$validacion_codigo_postal['valido'] && empty($mensaje)) {
            $mensaje = $validacion_codigo_postal['error'];
            $mensaje_tipo = 'danger';
        } else {
            $codigo_postal = $validacion_codigo_postal['valor'];
        }
        
        // Validar dirección completa combinada
        if (empty($mensaje)) {
            $validacion_direccion_completa = validarDireccionCompleta($direccion_calle, $direccion_numero, $direccion_piso);
            if (!$validacion_direccion_completa['valido']) {
                $mensaje = $validacion_direccion_completa['error'];
                $mensaje_tipo = 'danger';
            } else {
                $direccion = $validacion_direccion_completa['direccion_completa'];
            }
        }
    } elseif (!$actualizando_envio) {
        // Si no se están actualizando datos de envío, mantener los valores actuales
        $direccion = $usuario_actual['direccion'] ?? '';
        $localidad = $usuario_actual['localidad'] ?? '';
        $provincia = $usuario_actual['provincia'] ?? '';
        $codigo_postal = $usuario_actual['codigo_postal'] ?? '';
    }
    
    // Validar y procesar fecha de nacimiento (opcional) usando función centralizada
    // Si el campo está vacío, preservar el valor existente de la BD
    $fecha_nacimiento = trim($post['fecha_nacimiento'] ?? '');
    // Inicializar con el valor existente para preservarlo si no se proporciona uno nuevo
    $fecha_nacimiento_final = $usuario_actual['fecha_nacimiento'] ?? null;
    
    if (!empty($fecha_nacimiento)) {
        // Usar función centralizada de validación que incluye: formato, rango de años, edad mínima
        // La función validarFechaNacimiento() acepta formato YYYY-MM-DD (HTML5 date input) o dd/mm/yyyy
        $validacion_fecha = validarFechaNacimiento($fecha_nacimiento);
        
        if (!$validacion_fecha['valido']) {
            $mensaje = $validacion_fecha['error'];
            $mensaje_tipo = 'danger';
        } else {
            // Guardar fecha validada en formato YYYY-MM-DD para MySQL DATE
            // validarFechaNacimiento() retorna null si estaba vacía, o string YYYY-MM-DD si es válida
            $fecha_nacimiento_final = $validacion_fecha['valor'];
        }
    }
    // Si fecha_nacimiento está vacío, $fecha_nacimiento_final mantiene el valor existente (o null si no existe)
    // Esto permite que el usuario no modifique la fecha si no quiere cambiarla
    
    // Solo actualizar si no hay errores
    if (empty($mensaje)) {
        // Actualizar con los nuevos campos incluyendo email (sin pregunta/respuesta de recupero)
        if (actualizarDatosUsuario($mysqli, $id_usuario, $nombre, $apellido, $email, $telefono, $direccion, $localidad, $provincia, $codigo_postal, $fecha_nacimiento_final)) {
            $datos_actualizados = [
                'nombre' => $nombre,
                'apellido' => $apellido,
                'email' => $email
            ];
            $mensaje = 'Datos actualizados correctamente';
            $mensaje_tipo = 'success';
        } else {
            $mensaje = 'Error al actualizar los datos';
            $mensaje_tipo = 'danger';
        }
    }
    
    return ['mensaje' => $mensaje, 'mensaje_tipo' => $mensaje_tipo, 'datos_actualizados' => $datos_actualizados];
}

/**
 * Procesa el cambio de contraseña del usuario
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @param array $post Datos POST del formulario
 * @return array Array con 'mensaje' y 'mensaje_tipo' (success/danger/warning)
 */
function procesarCambioContrasena($mysqli, $id_usuario, $post) {
    $mensaje = '';
    $mensaje_tipo = '';
    
    // IMPORTANTE: NO usar trim() en passwords - puede cambiar la contraseña
    $contrasena_actual = $post['contrasena_actual'] ?? '';
    $nueva_contrasena = $post['nueva_contrasena'] ?? '';
    $confirmar_contrasena = $post['confirmar_contrasena'] ?? '';
    
    // Validar que todos los campos estén completos (validación estricta)
    if ($contrasena_actual === '' || strlen($contrasena_actual) === 0 || 
        $nueva_contrasena === '' || strlen($nueva_contrasena) === 0 || 
        $confirmar_contrasena === '' || strlen($confirmar_contrasena) === 0) {
        $mensaje = 'Todos los campos de contraseña son requeridos';
        $mensaje_tipo = 'danger';
    } else {
        // Verificar contraseña actual usando función centralizada
        if (!verificarHashContrasena($mysqli, $id_usuario, $contrasena_actual)) {
            $mensaje = 'La contraseña actual es incorrecta';
            $mensaje_tipo = 'danger';
        } elseif ($nueva_contrasena !== $confirmar_contrasena) {
            // Las nuevas contraseñas no coinciden
            $mensaje = 'Las nuevas contraseñas no coinciden';
            $mensaje_tipo = 'danger';
        } elseif (strlen($nueva_contrasena) < 6) {
            // Validar longitud mínima
            $mensaje = 'La nueva contraseña debe tener al menos 6 caracteres';
            $mensaje_tipo = 'danger';
        } elseif (strlen($nueva_contrasena) > 32) {
            // Validar longitud máxima
            $mensaje = 'La nueva contraseña no puede exceder 32 caracteres';
            $mensaje_tipo = 'danger';
        } elseif (verificarHashContrasena($mysqli, $id_usuario, $nueva_contrasena)) {
            // La nueva contraseña no puede ser igual a la actual (validación estricta)
            $mensaje = 'La nueva contraseña debe ser diferente a la contraseña actual';
            $mensaje_tipo = 'warning';
        } else {
            // Todo válido, cambiar contraseña
            // Usar función centralizada para generar hash (ya llama a configurarConexionBD internamente)
            $nueva_contrasena_hash = generarHashPassword($nueva_contrasena, $mysqli);
            
            if ($nueva_contrasena_hash === false) {
                $mensaje = 'Error al procesar la nueva contraseña. No se pudo generar el hash.';
                $mensaje_tipo = 'danger';
            } else {
                if (actualizarContrasena($mysqli, $id_usuario, $nueva_contrasena_hash)) {
                    // Verificar que el hash se guardó correctamente usando función centralizada
                    if (verificarHashContrasena($mysqli, $id_usuario, $nueva_contrasena)) {
                        $mensaje = 'Contraseña actualizada correctamente. La contraseña se guardó y verifica correctamente.';
                        $mensaje_tipo = 'success';
                    } else {
                        $mensaje = 'Contraseña actualizada pero no se pudo verificar. Posible problema de codificación.';
                        $mensaje_tipo = 'warning';
                    }
                    
                    // Limpiar variables sensibles de memoria
                    $contrasena_actual = null;
                    $nueva_contrasena = null;
                    $confirmar_contrasena = null;
                    $nueva_contrasena_hash = null;
                } else {
                    $mensaje = 'Error al actualizar la contraseña en la base de datos';
                    $mensaje_tipo = 'danger';
                }
            }
        }
    }
    
    return ['mensaje' => $mensaje, 'mensaje_tipo' => $mensaje_tipo];
}


/**
 * Procesa la eliminación de cuenta del usuario
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @param array $post Datos POST del formulario
 * @return array Array con 'mensaje', 'mensaje_tipo' y 'eliminado' (bool) - si eliminado=true, redirigir
 */
function procesarEliminacionCuenta($mysqli, $id_usuario, $post) {
    $mensaje = '';
    $mensaje_tipo = '';
    $eliminado = false;
    
    $email_confirmacion_raw = $post['email_confirmacion'] ?? '';
    
    // Validar email usando función centralizada
    $validacion_email = validarEmail($email_confirmacion_raw);
    
    if (!$validacion_email['valido']) {
        // Personalizar mensaje para confirmación de eliminación
        if (empty(trim($email_confirmacion_raw))) {
            $mensaje = 'Debes ingresar tu correo electrónico para confirmar la eliminación.';
        } else {
            $mensaje = $validacion_email['error'];
        }
        $mensaje_tipo = 'danger';
    } else {
        // Email válido - usar valor original sin sanitizar para comparación
        $email_confirmacion = strtolower(trim($email_confirmacion_raw));
        
        // Obtener datos del usuario antes de desactivar la cuenta (para notificación admin)
        $perfil_queries_path = __DIR__ . '/queries/perfil_queries.php';
        if (!file_exists($perfil_queries_path)) {
            error_log("ERROR: No se pudo encontrar perfil_queries.php en " . $perfil_queries_path);
            die("Error crítico: Archivo de consultas de perfil no encontrado. Por favor, contacta al administrador.");
        }
        require_once $perfil_queries_path;
        $datos_usuario = obtenerDatosUsuario($mysqli, $id_usuario);
        
        // Procesar eliminación de cuenta
        $resultado_eliminacion = eliminarCuentaUsuario($mysqli, $id_usuario, $email_confirmacion);
        
        if ($resultado_eliminacion) {
            $eliminado = true;
            
            // Enviar email de notificación para admin
            if ($datos_usuario) {
                // Intentar incluir configuración de Mailgun y PHPMailer con manejo de errores
                // Esto evita errores fatales si los archivos no existen en el hosting
                $mailgun_loaded = false;
                $phpmailer_loaded = false;
                
                // Cargar mailgun.php si existe
                $mailgun_path = __DIR__ . '/../config/mailgun.php';
                if (file_exists($mailgun_path)) {
                    try {
                        require_once $mailgun_path;
                        $mailgun_loaded = true;
                    } catch (Exception $e) {
                        error_log("Error al cargar mailgun.php en eliminación de cuenta. ID Usuario: {$id_usuario}. Error: " . $e->getMessage());
                    }
                } else {
                    error_log("Archivo mailgun.php no encontrado en eliminación de cuenta. Ruta: {$mailgun_path}");
                }
                
                // Cargar vendor/autoload.php solo si todas las dependencias críticas existen
                // IMPORTANTE: No intentar cargar si faltan dependencias para evitar errores fatales
                $vendor_path = __DIR__ . '/../vendor/autoload.php';
                if (file_exists($vendor_path)) {
                    // Verificar que TODAS las dependencias críticas existan antes de intentar cargar
                    $phpmailer_path = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
                    $react_promise_path = __DIR__ . '/../vendor/react/promise/src/functions_include.php';
                    
                    // Verificar que PHPMailer existe
                    $phpmailer_exists = file_exists($phpmailer_path);
                    
                    // Verificar que react/promise existe (requerido por autoload)
                    $react_promise_exists = file_exists($react_promise_path);
                    
                    // Solo intentar cargar si TODAS las dependencias críticas existen
                    if ($phpmailer_exists && $react_promise_exists) {
                        // Intentar cargar autoload con manejo de errores
                        // Usar output buffering para capturar cualquier output de error
                        ob_start();
                        $autoload_result = @include $vendor_path;
                        $autoload_output = ob_get_clean();
                        
                        // Si hubo output, significa que hubo un error
                        if (!empty($autoload_output)) {
                            error_log("Error al cargar vendor/autoload.php (output capturado). ID Usuario: {$id_usuario}. Output: {$autoload_output}");
                            $autoload_result = false;
                        }
                        
                        // Verificar si se cargó correctamente verificando que PHPMailer esté disponible
                        if ($autoload_result !== false && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                            $phpmailer_loaded = true;
                        } else {
                            error_log("vendor/autoload.php no se pudo cargar o PHPMailer no disponible. ID Usuario: {$id_usuario}");
                        }
                    } else {
                        // Dependencias incompletas - no intentar cargar autoload
                        $missing_deps = [];
                        if (!$phpmailer_exists) {
                            $missing_deps[] = 'phpmailer/phpmailer';
                        }
                        if (!$react_promise_exists) {
                            $missing_deps[] = 'react/promise';
                        }
                        error_log("Dependencias incompletas en hosting. ID Usuario: {$id_usuario}. Faltan: " . implode(', ', $missing_deps) . ". No se intentará cargar autoload.");
                    }
                } else {
                    error_log("Archivo vendor/autoload.php no encontrado en eliminación de cuenta. Ruta: {$vendor_path}");
                }
                
                // Construir nombre completo del usuario
                $nombre_completo = trim(($datos_usuario['nombre'] ?? '') . ' ' . ($datos_usuario['apellido'] ?? ''));
                if (empty($nombre_completo)) {
                    $nombre_completo = 'Usuario sin nombre';
                }
                
                // Construir mensaje detallado para admin
                $fecha_desactivacion = date('d/m/Y H:i:s');
                $mensaje_admin = "El usuario ha desactivado su cuenta.\n\n";
                $mensaje_admin .= "ID Usuario: {$id_usuario}\n";
                $mensaje_admin .= "Nombre: {$nombre_completo}\n";
                $mensaje_admin .= "Email: " . ($datos_usuario['email'] ?? 'N/A') . "\n";
                $mensaje_admin .= "Email confirmado: {$email_confirmacion}\n";
                $mensaje_admin .= "Fecha y hora de desactivación: {$fecha_desactivacion}\n\n";
                $mensaje_admin .= "Nota: La cuenta permanecerá desactivada durante 30 días. El usuario puede reactivarla iniciando sesión dentro de ese período.";
                
                // Preparar email
                $subject_email = 'Notificación: Cuenta Desactivada - Usuario ID: ' . $id_usuario;
                
                // Verificar que Mailgun SMTP esté configurado y que los archivos se hayan cargado
                if ($mailgun_loaded && $phpmailer_loaded && function_exists('mailgun_smtp_esta_configurado') && mailgun_smtp_esta_configurado()) {
                    try {
                        // Importar PHPMailer solo cuando se va a usar (evita cargar si no está disponible)
                        // Usar el namespace completo para evitar problemas con autoload
                        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                            throw new Exception('PHPMailer no está disponible aunque se intentó cargar');
                        }
                        
                        // Configuración de Mailgun SMTP desde archivo de config
                        $smtp_host = MAILGUN_SMTP_HOST;
                        $smtp_port = MAILGUN_SMTP_PORT;
                        $smtp_username = trim(MAILGUN_SMTP_USERNAME);
                        $smtp_password = trim(MAILGUN_SMTP_PASSWORD);
                        $smtp_encryption = MAILGUN_SMTP_ENCRYPTION;
                        $from_email = MAILGUN_FROM_EMAIL;
                        $from_name = MAILGUN_FROM_NAME;
                        $to_email = MAILGUN_CONTACT_TO_EMAIL;
                        $to_name = MAILGUN_CONTACT_TO_NAME;
                        
                        // Crear instancia de PHPMailer usando namespace completo
                        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        
                        // Configuración del servidor SMTP
                        $mail->isSMTP();
                        $mail->Host = $smtp_host;
                        $mail->SMTPAuth = true;
                        $mail->Username = $smtp_username;
                        $mail->Password = $smtp_password;
                        $mail->SMTPSecure = $smtp_encryption;
                        $mail->Port = $smtp_port;
                        $mail->SMTPDebug = 0;
                        
                        // Remitente
                        $mail->setFrom($from_email, $from_name);
                        
                        // Destinatario
                        $mail->addAddress($to_email, $to_name);
                        
                        // Contenido del email
                        $mail->isHTML(false);
                        $mail->Subject = $subject_email;
                        $mail->Body = $mensaje_admin;
                        $mail->CharSet = 'UTF-8';
                        
                        // Enviar email
                        $mail->send();
                    } catch (Exception $e) {
                        // Log error pero no afectar el proceso de eliminación
                        error_log("Error al enviar email de notificación admin para eliminación de cuenta. ID Usuario: {$id_usuario}. Error: " . $e->getMessage());
                    }
                } else {
                    // Log error si Mailgun no está configurado o los archivos no se cargaron
                    if (!$mailgun_loaded || !$phpmailer_loaded) {
                        error_log("Archivos de email no disponibles en eliminación de cuenta. ID Usuario: {$id_usuario}. Mailgun: " . ($mailgun_loaded ? 'OK' : 'NO') . ", PHPMailer: " . ($phpmailer_loaded ? 'OK' : 'NO'));
                    } else {
                        error_log("Mailgun SMTP no está configurado. No se pudo enviar notificación de eliminación de cuenta. ID Usuario: {$id_usuario}");
                    }
                }
            }
        } else {
            $mensaje = 'Error al procesar la eliminación. Verifica que el correo electrónico sea correcto.';
            $mensaje_tipo = 'danger';
        }
    }
    
    return ['mensaje' => $mensaje, 'mensaje_tipo' => $mensaje_tipo, 'eliminado' => $eliminado];
}

/**
 * Valida que un pago puede ser marcado como pagado por el cliente
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_pago ID del pago a validar
 * @param int $id_usuario ID del usuario que intenta marcar el pago
 * @return array Array con 'valido' => bool, 'mensaje' => string, 'mensaje_tipo' => string, 'pago' => array|null, 'pedido' => array|null
 */
function validarPagoParaMarcarPagado($mysqli, $id_pago, $id_usuario) {
    require_once __DIR__ . '/queries/pago_queries.php';
    require_once __DIR__ . '/queries/pedido_queries.php';
    
    // Cargar función de normalización de estados si no está disponible
    if (!function_exists('normalizarEstado')) {
        require_once __DIR__ . '/estado_helpers.php';
    }
    
    // Validar ID de pago
    if ($id_pago <= 0) {
        return [
            'valido' => false,
            'mensaje' => 'ID de pago inválido',
            'mensaje_tipo' => 'danger',
            'pago' => null,
            'pedido' => null
        ];
    }
    
    // Obtener información del pago
    $pago = obtenerPagoPorId($mysqli, $id_pago);
    if (!$pago) {
        return [
            'valido' => false,
            'mensaje' => 'Pago no encontrado',
            'mensaje_tipo' => 'danger',
            'pago' => null,
            'pedido' => null
        ];
    }
    
    // Verificar que el pedido pertenece al usuario actual
    $pedido = obtenerPedidoPorId($mysqli, $pago['id_pedido']);
    if (!$pedido || intval($pedido['id_usuario']) !== $id_usuario) {
        return [
            'valido' => false,
            'mensaje' => 'No tienes permiso para modificar este pago',
            'mensaje_tipo' => 'danger',
            'pago' => $pago,
            'pedido' => $pedido
        ];
    }
    
    // Validar estado del pago usando normalización para comparación consistente
    $estado_pago_normalizado = normalizarEstado($pago['estado_pago'] ?? '');
    if ($estado_pago_normalizado !== 'pendiente') {
        return [
            'valido' => false,
            'mensaje' => 'Solo se pueden marcar como pagados los pagos pendientes',
            'mensaje_tipo' => 'warning',
            'pago' => $pago,
            'pedido' => $pedido
        ];
    }
    
    return [
        'valido' => true,
        'mensaje' => '',
        'mensaje_tipo' => '',
        'pago' => $pago,
        'pedido' => $pedido
    ];
}

/**
 * Construye mensaje de error para el usuario basado en el tipo de error
 * 
 * @param string $error_message Mensaje de error de la excepción
 * @param int $id_pago ID del pago (para logging)
 * @param int $id_usuario ID del usuario (para logging)
 * @return array Array con 'mensaje' => string, 'mensaje_tipo' => string
 */
function construirMensajeErrorPago($error_message, $id_pago = 0, $id_usuario = 0) {
    // Loggear error crítico
    error_log("ERROR al marcar pago #{$id_pago} (Usuario: {$id_usuario}): {$error_message}");
    
    // Construir mensaje según el tipo de error
    if (strpos($error_message, 'STOCK_INSUFICIENTE') !== false) {
        return [
            'mensaje' => 'No hay stock disponible para completar este pedido. Por favor, <a href="index.php#contacto" class="alert-link">comunícate con nosotros</a> para más información.',
            'mensaje_tipo' => 'warning'
        ];
    } elseif (strpos($error_message, 'Ya existe otro pago aprobado') !== false) {
        return [
            'mensaje' => 'Ya existe un pago aprobado para este pedido. No se puede aprobar otro pago.',
            'mensaje_tipo' => 'warning'
        ];
    } elseif (strpos($error_message, 'monto menor o igual a cero') !== false) {
        return [
            'mensaje' => 'El monto del pago no es válido. Por favor, comunícate con nosotros para más información.',
            'mensaje_tipo' => 'danger'
        ];
    } elseif (strpos($error_message, 'Pago no encontrado') !== false) {
        return [
            'mensaje' => 'El pago no fue encontrado en el sistema. Por favor, recarga la página e intenta nuevamente.',
            'mensaje_tipo' => 'danger'
        ];
    } elseif (strpos($error_message, 'Estado de pago inválido') !== false) {
        return [
            'mensaje' => 'El estado del pago no es válido. Por favor, comunícate con nosotros para más información.',
            'mensaje_tipo' => 'danger'
        ];
    } elseif (strpos($error_message, 'Error al preparar') !== false || 
              strpos($error_message, 'Error al ejecutar') !== false ||
              strpos($error_message, 'Error al actualizar') !== false ||
              strpos($error_message, 'Error al obtener') !== false) {
        return [
            'mensaje' => 'Error de base de datos al procesar el pago. Por favor, <a href="index.php#contacto" class="alert-link">comunícate con nosotros</a> para más información.',
            'mensaje_tipo' => 'danger'
        ];
    } else {
        return [
            'mensaje' => 'Error al procesar el pago. Por favor, <a href="index.php#contacto" class="alert-link">comunícate con nosotros</a> para más información.',
            'mensaje_tipo' => 'danger'
        ];
    }
}

