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
        if (strlen($respuesta_recupero) <= 4) {
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
        } else {
            // Convertir a minúsculas para normalización
            $respuesta_recupero_final = strtolower($respuesta_recupero);
        }
    }
    
    // Validar que si hay pregunta, también haya respuesta
    if (!empty($pregunta_recupero_id) && empty($respuesta_recupero_final)) {
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
            // Actualizar solo pregunta y respuesta de recupero
            if (actualizarPreguntaRecupero($mysqli, $id_usuario, $pregunta_recupero_id, $respuesta_recupero_final)) {
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
        if (empty($apellido)) {
            $mensaje = 'El apellido es obligatorio.';
            $mensaje_tipo = 'danger';
        } elseif (strlen($apellido) < 2) {
            $mensaje = 'El apellido debe tener al menos 2 caracteres.';
            $mensaje_tipo = 'danger';
        } elseif (strlen($apellido) > 100) {
            $mensaje = 'El apellido no puede exceder 100 caracteres.';
            $mensaje_tipo = 'danger';
        } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\'´]+$/', $apellido)) {
            $mensaje = 'El apellido solo puede contener letras, espacios, apóstrofe (\') y acento agudo (´).';
            $mensaje_tipo = 'danger';
        }
    }
    
    $telefono = trim($post['telefono'] ?? '');
    // Telefono puede estar vacío, pero si se proporciona debe ser válido
    if (!empty($telefono)) {
        // Validar longitud según diccionario: 6-20 caracteres
        if (strlen($telefono) < 6) {
            if (empty($mensaje)) {
                $mensaje = 'El teléfono debe tener al menos 6 caracteres.';
                $mensaje_tipo = 'danger';
            }
        } elseif (strlen($telefono) > 20) {
            if (empty($mensaje)) {
                $mensaje = 'El teléfono no puede exceder 20 caracteres.';
                $mensaje_tipo = 'danger';
            }
        } elseif (!preg_match('/^[0-9+\-() ]+$/', $telefono)) {
            // Validar caracteres permitidos según diccionario: [0-9, +, (, ), -]
            if (empty($mensaje)) {
                $mensaje = 'El teléfono solo puede contener números y símbolos (+, -, paréntesis, espacios).';
                $mensaje_tipo = 'danger';
            }
        }
    }
    
    // Validar campos de dirección solo si se están actualizando
    if ($actualizando_envio) {
        if (empty($direccion_calle)) {
            $mensaje = 'La dirección (calle) es requerida';
            $mensaje_tipo = 'danger';
        } elseif (empty($direccion_numero)) {
            $mensaje = 'El número de dirección es requerido';
            $mensaje_tipo = 'danger';
        }
        
        // Validar provincia
        if (empty($provincia)) {
            if (empty($mensaje)) {
                $mensaje = 'La provincia es requerida';
                $mensaje_tipo = 'danger';
            }
        } else {
            // Validar longitud según diccionario: 3-100 caracteres
            if (strlen($provincia) < 3) {
                if (empty($mensaje)) {
                    $mensaje = 'La provincia debe tener al menos 3 caracteres.';
                    $mensaje_tipo = 'danger';
                }
            } elseif (strlen($provincia) > 100) {
                if (empty($mensaje)) {
                    $mensaje = 'La provincia no puede exceder 100 caracteres.';
                    $mensaje_tipo = 'danger';
                }
            } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/', $provincia)) {
                // Validar caracteres permitidos según diccionario: [A-Z, a-z, ] (solo letras y espacios)
                if (empty($mensaje)) {
                    $mensaje = 'La provincia solo puede contener letras y espacios.';
                    $mensaje_tipo = 'danger';
                }
            }
        }
        
        // Validar localidad
        if (empty($localidad)) {
            if (empty($mensaje)) {
                $mensaje = 'La localidad es requerida';
                $mensaje_tipo = 'danger';
            }
        } else {
            // Validar longitud según diccionario: 3-100 caracteres
            if (strlen($localidad) < 3) {
                if (empty($mensaje)) {
                    $mensaje = 'La localidad debe tener al menos 3 caracteres.';
                    $mensaje_tipo = 'danger';
                }
            } elseif (strlen($localidad) > 100) {
                if (empty($mensaje)) {
                    $mensaje = 'La localidad no puede exceder 100 caracteres.';
                    $mensaje_tipo = 'danger';
                }
            } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/', $localidad)) {
                // Validar caracteres permitidos según diccionario: [A-Z, a-z, ] (solo letras y espacios)
                if (empty($mensaje)) {
                    $mensaje = 'La localidad solo puede contener letras y espacios.';
                    $mensaje_tipo = 'danger';
                }
            }
        }
        
        // Validar código postal
        if (empty($codigo_postal)) {
            if (empty($mensaje)) {
                $mensaje = 'El código postal es requerido';
                $mensaje_tipo = 'danger';
            }
        } elseif (!preg_match('/^[A-Za-z0-9 ]+$/', $codigo_postal)) {
            if (empty($mensaje)) {
                $mensaje = 'El código postal solo puede contener letras, números y espacios';
                $mensaje_tipo = 'danger';
            }
        }
    }
    
    // Combinar dirección para guardar en BD (solo si no hay errores y se está actualizando)
    $direccion = '';
    if ($actualizando_envio && empty($mensaje)) {
        $direccion = trim($direccion_calle) . ' ' . trim($direccion_numero);
        if (!empty($direccion_piso)) {
            $direccion .= ' ' . trim($direccion_piso);
        }
        $direccion = trim($direccion);
        
        // Validar dirección combinada según diccionario: longitud 5-100, caracteres [A-Z, a-z, 0-9, , .,-]
        if (strlen($direccion) < 5) {
            $mensaje = 'La dirección debe tener al menos 5 caracteres.';
            $mensaje_tipo = 'danger';
        } elseif (strlen($direccion) > 100) {
            $mensaje = 'La dirección no puede exceder 100 caracteres.';
            $mensaje_tipo = 'danger';
        } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ0-9\s\.,\-]+$/', $direccion)) {
            // Validar caracteres permitidos según diccionario: [A-Z, a-z, 0-9, , .,-]
            $mensaje = 'La dirección contiene caracteres no permitidos. Solo se permiten letras, números, espacios, puntos, comas y guiones.';
            $mensaje_tipo = 'danger';
        }
    } elseif (!$actualizando_envio) {
        // Si no se están actualizando datos de envío, mantener los valores actuales
        $direccion = $usuario_actual['direccion'] ?? '';
        $localidad = $usuario_actual['localidad'] ?? '';
        $provincia = $usuario_actual['provincia'] ?? '';
        $codigo_postal = $usuario_actual['codigo_postal'] ?? '';
    }
    
    // Validar y procesar fecha de nacimiento (opcional)
    // Si el campo está vacío, preservar el valor existente de la BD
    $fecha_nacimiento = trim($post['fecha_nacimiento'] ?? '');
    // Inicializar con el valor existente para preservarlo si no se proporciona uno nuevo
    $fecha_nacimiento_final = $usuario_actual['fecha_nacimiento'] ?? null;
    
    if (!empty($fecha_nacimiento)) {
        // Flatpickr puede enviar en formato dd/mm/yyyy, convertir a YYYY-MM-DD
        $fecha_procesada = null;
        
        // Intentar parsear formato dd/mm/yyyy
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha_nacimiento, $matches)) {
            $dia = intval($matches[1]);
            $mes = intval($matches[2]);
            $ano = intval($matches[3]);
            if (checkdate($mes, $dia, $ano)) {
                $fecha_procesada = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
            }
        }
        // Si no es dd/mm/yyyy, intentar formato YYYY-MM-DD
        elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_nacimiento)) {
            $fecha_parts = explode('-', $fecha_nacimiento);
            if (count($fecha_parts) === 3 && checkdate(intval($fecha_parts[1]), intval($fecha_parts[2]), intval($fecha_parts[0]))) {
                $fecha_procesada = $fecha_nacimiento;
            }
        }
        
        if ($fecha_procesada) {
            try {
                $fecha_nac = new DateTime($fecha_procesada);
                $hoy = new DateTime();
                $hoy->setTime(0, 0, 0);
                $fecha_nac->setTime(0, 0, 0);
                
                // Extraer año de la fecha procesada
                $año = intval($fecha_nac->format('Y'));
                
                // Validar rango de año permitido (1925-2012)
                if ($año < 1925 || $año > 2012) {
                    $mensaje = 'La fecha de nacimiento debe estar entre 1925 y 2012.';
                    $mensaje_tipo = 'danger';
                }
                // Validar que no sea futura
                elseif ($fecha_nac > $hoy) {
                    $mensaje = 'La fecha de nacimiento no puede ser futura.';
                    $mensaje_tipo = 'danger';
                } else {
                    // Guardar en formato YYYY-MM-DD para MySQL DATE
                    $fecha_nacimiento_final = $fecha_nac->format('Y-m-d');
                }
            } catch (Exception $e) {
                $mensaje = 'La fecha de nacimiento no es válida.';
                $mensaje_tipo = 'danger';
            }
        } else {
            $mensaje = 'El formato de fecha de nacimiento no es válido. Use formato dd/mm/aaaa.';
            $mensaje_tipo = 'danger';
        }
    }
    // Si fecha_nacimiento está vacío, $fecha_nacimiento_final ya tiene el valor existente (o null si no existe)
    
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
            // Usar función centralizada para generar hash
            configurarConexionBD($mysqli);
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
    // Debug: Inicio de función
    $debug_info = [];
    $debug_info['function_start'] = [
        'id_usuario' => $id_usuario,
        'post_data' => $post,
        'email_confirmacion_raw' => $post['email_confirmacion'] ?? 'NO SET'
    ];
    
    $mensaje = '';
    $mensaje_tipo = '';
    $eliminado = false;
    
    $email_confirmacion_raw = $post['email_confirmacion'] ?? '';
    $debug_info['email_after_trim'] = $email_confirmacion_raw;
    
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
        $debug_info['validation'] = 'EMAIL_INVALIDO';
    } else {
        // Email válido - usar valor original sin sanitizar para comparación
        $email_confirmacion = strtolower(trim($email_confirmacion_raw));
        $debug_info['validation'] = 'EMAIL_VALIDO';
        
        // Obtener datos del usuario antes de desactivar la cuenta (para notificación admin)
        $perfil_queries_path = __DIR__ . '/queries/perfil_queries.php';
        if (!file_exists($perfil_queries_path)) {
            error_log("ERROR: No se pudo encontrar perfil_queries.php en " . $perfil_queries_path);
            die("Error crítico: Archivo de consultas de perfil no encontrado. Por favor, contacta al administrador.");
        }
        require_once $perfil_queries_path;
        $datos_usuario = obtenerDatosUsuario($mysqli, $id_usuario);
        $debug_info['datos_usuario_obtenidos'] = [
            'usuario_existe' => !empty($datos_usuario),
            'email_usuario' => $datos_usuario['email'] ?? 'NO SET',
            'nombre' => $datos_usuario['nombre'] ?? 'NO SET'
        ];
        
        // Procesar eliminación de cuenta
        $debug_info['antes_eliminarCuentaUsuario'] = [
            'id_usuario' => $id_usuario,
            'email_confirmacion' => $email_confirmacion,
            'email_usuario' => $datos_usuario['email'] ?? 'NO SET',
            'emails_coinciden' => isset($datos_usuario['email']) && strtolower(trim($datos_usuario['email'])) === strtolower(trim($email_confirmacion))
        ];
        
        $resultado_eliminacion = eliminarCuentaUsuario($mysqli, $id_usuario, $email_confirmacion);
        $debug_info['despues_eliminarCuentaUsuario'] = [
            'resultado' => $resultado_eliminacion,
            'tipo' => gettype($resultado_eliminacion)
        ];
        
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
            $debug_info['eliminacion_fallida'] = [
                'razon' => 'eliminarCuentaUsuario retornó false',
                'mysqli_error' => $mysqli->error ?? 'NO SET',
                'mysqli_errno' => $mysqli->errno ?? 'NO SET'
            ];
        }
    }
    
    // Agregar debug info al resultado
    $debug_info['resultado_final'] = [
        'eliminado' => $eliminado,
        'mensaje' => $mensaje,
        'mensaje_tipo' => $mensaje_tipo
    ];
    
    // Guardar debug info en variable global para acceso desde perfil.php
    if (function_exists('addDebug')) {
        addDebug('DEBUG procesarEliminacionCuenta()', $debug_info);
    }
    
    return ['mensaje' => $mensaje, 'mensaje_tipo' => $mensaje_tipo, 'eliminado' => $eliminado, 'debug_info' => $debug_info];
}


