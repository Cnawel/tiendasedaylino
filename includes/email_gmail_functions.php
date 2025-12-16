<?php
/**
 * ========================================================================
 * FUNCIONES DE EMAIL CON GMAIL SMTP - Tienda Seda y Lino
 * ========================================================================
 * Funciones para envío de emails usando PHPMailer con Gmail SMTP
 * 
 * Funciones disponibles:
 * - enviar_email_gmail(): Función base para enviar emails con PHPMailer y Gmail SMTP
 * - enviar_email_bienvenida(): Envía email de bienvenida a nuevos usuarios
 * - enviar_email_confirmacion_pedido_gmail(): Envía confirmación de pedido al cliente
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 */

// Cargar configuración de Gmail si existe
$gmail_path = __DIR__ . '/../config/gmail.php';
if (file_exists($gmail_path)) {
    try {
        require_once $gmail_path;
    } catch (Exception $e) {
        error_log("Error al cargar gmail.php: " . $e->getMessage());
        // Continuar con valores por defecto
    }
} else {
    // Definir constantes por defecto si el archivo no existe
    if (!defined('GMAIL_SMTP_HOST')) define('GMAIL_SMTP_HOST', 'smtp.gmail.com');
    if (!defined('GMAIL_SMTP_PORT')) define('GMAIL_SMTP_PORT', 587);
    if (!defined('GMAIL_SMTP_USERNAME')) define('GMAIL_SMTP_USERNAME', '');
    if (!defined('GMAIL_SMTP_PASSWORD')) define('GMAIL_SMTP_PASSWORD', '');
    if (!defined('GMAIL_SMTP_ENCRYPTION')) define('GMAIL_SMTP_ENCRYPTION', 'tls');
    if (!defined('GMAIL_FROM_EMAIL')) define('GMAIL_FROM_EMAIL', '');
    if (!defined('GMAIL_FROM_NAME')) define('GMAIL_FROM_NAME', '');
    if (!defined('GMAIL_ENABLED')) define('GMAIL_ENABLED', false);
    
    // Definir función por defecto si no existe
    if (!function_exists('gmail_smtp_esta_configurado')) {
        function gmail_smtp_esta_configurado() {
            return (
                !empty(GMAIL_SMTP_HOST) &&
                !empty(GMAIL_SMTP_PORT) &&
                !empty(GMAIL_SMTP_USERNAME) &&
                !empty(GMAIL_SMTP_PASSWORD) &&
                GMAIL_SMTP_USERNAME !== 'tu_email@gmail.com' &&
                GMAIL_SMTP_PASSWORD !== 'tu_contraseña_de_aplicacion_aqui'
            );
        }
    }
}

// Definir URL base para los enlaces en los emails (dinámica según entorno)
if (!defined('BASE_URL')) {
    // Detectar entorno automáticamente
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Para desarrollo local, usar localhost
    if ($host === 'localhost' || strpos($host, 'localhost:') === 0) {
        $base_url = $protocol . '://' . $host . '/tienda-beta/';
    }
    // Para hosting, usar la URL del hosting
    elseif (strpos($host, 'infinityfree') !== false) {
        $base_url = 'https://sedaylino.infinityfreeapp.com/';
    }
    // Para otros entornos, construir automáticamente
    else {
        $base_url = $protocol . '://' . $host . '/';
    }

    define('BASE_URL', $base_url);
}


/**
 * Renderiza un template de email unificado (PHP)
 * 
 * @param string $template_name Nombre del archivo de template (sin .php)
 * @param array $variables Variables para pasar al template
 * @return array ['html' => string, 'text' => string]
 */
function render_email_template($template_name, $variables = []) {
    $template_path = __DIR__ . '/../templates/emails/' . $template_name . '.php';
    
    if (file_exists($template_path)) {
        // Extraer variables para que estén disponibles en el template
        if (!empty($variables)) {
            extract($variables);
        }
        
        // El template debe retornar un array ['html' => ..., 'text' => ...]
        $result = include $template_path;
        
        if (is_array($result) && isset($result['html']) && isset($result['text'])) {
            return $result;
        }
        
        error_log("render_email_template: El template $template_name no retornó un array válido.");
    } else {
        error_log("render_email_template: Template no encontrado: $template_name");
    }
    
    return ['html' => '', 'text' => ''];
}

/**
 * Envía un email usando PHPMailer con Gmail SMTP
 * 
 * @param string $destinatario Email del destinatario
 * @param string $nombre_destinatario Nombre del destinatario
 * @param string $asunto Asunto del email
 * @param string $cuerpo_html Cuerpo del email en formato HTML
 * @param string $cuerpo_texto Cuerpo del email en texto plano (opcional)
 * @return bool True si se envió correctamente, false si hubo error
 */
function enviar_email_gmail($destinatario, $nombre_destinatario, $asunto, $cuerpo_html, $cuerpo_texto = '') {
    // Verificar que Gmail SMTP esté configurado
    if (!gmail_smtp_esta_configurado()) {
        error_log("Gmail SMTP no está configurado correctamente");
        return false;
    }
    
    // Verificar que Gmail esté habilitado
    if (defined('GMAIL_ENABLED') && !GMAIL_ENABLED) {
        error_log("Gmail está deshabilitado en configuración");
        return false;
    }
    
    // Verificar que todas las constantes necesarias estén definidas
    $constantes_requeridas = [
        'GMAIL_SMTP_HOST',
        'GMAIL_SMTP_PORT',
        'GMAIL_SMTP_USERNAME',
        'GMAIL_SMTP_PASSWORD',
        'GMAIL_SMTP_ENCRYPTION',
        'GMAIL_FROM_EMAIL',
        'GMAIL_FROM_NAME'
    ];
    
    foreach ($constantes_requeridas as $constante) {
        if (!defined($constante)) {
            error_log("Constante requerida no definida: $constante");
            return false;
        }
    }
    
    // Verificar que PHPMailer esté disponible
    $autoload_path = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload_path)) {
        error_log("No se encontró vendor/autoload.php");
        return false;
    }
    
    try {
        require_once $autoload_path;
    } catch (Exception $e) {
        error_log("Error al cargar autoload.php: " . $e->getMessage());
        return false;
    }
    
    // Verificar que PHPMailer esté disponible
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer no está disponible");
        return false;
    }
    
    // Configuración de Gmail SMTP desde archivo de config
    $smtp_host = GMAIL_SMTP_HOST;
    $smtp_port = GMAIL_SMTP_PORT;
    $smtp_username = trim(GMAIL_SMTP_USERNAME);
    $smtp_password = trim(GMAIL_SMTP_PASSWORD);
    $smtp_encryption = GMAIL_SMTP_ENCRYPTION;
    $from_email = GMAIL_FROM_EMAIL;
    $from_name = GMAIL_FROM_NAME;
    
    // Intentar enviar email con Gmail usando SMTP
    try {
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
        $mail->SMTPDebug = 0; // Desactivado en producción
        
        // Remitente
        $mail->setFrom($from_email, $from_name);
        
        // Destinatario
        $mail->addAddress($destinatario, $nombre_destinatario);
        
        // Contenido del email
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = $cuerpo_html;
        $mail->CharSet = 'UTF-8';
        
        // Agregar versión texto plano si está disponible
        if (!empty($cuerpo_texto)) {
            $mail->AltBody = $cuerpo_texto;
        }
        
        // Enviar email
        $mail->send();
        
        return true;
        
    } catch (Exception | Error $e) {
        // Capturar tanto Exception como Error (PHP 7+)
        // Log del error para debugging
        error_log("Error al enviar email con Gmail: " . $e->getMessage());
        error_log("Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
        
        if (isset($mail) && !empty($mail->ErrorInfo)) {
            error_log("PHPMailer ErrorInfo: " . $mail->ErrorInfo);
        }
        
        return false;
    }
}

/**
 * Envía email de bienvenida a un nuevo usuario
 * 
 * @param string $nombre Nombre del usuario
 * @param string $apellido Apellido del usuario
 * @param string $email Email del usuario
 * @return bool True si se envió correctamente, false si hubo error
 */
function enviar_email_bienvenida($nombre, $apellido, $email) {
    // Sanitizar datos
    $nombre_sanitizado = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
    $apellido_sanitizado = htmlspecialchars($apellido, ENT_QUOTES, 'UTF-8');
    $email_sanitizado = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $nombre_completo = $nombre_sanitizado . ' ' . $apellido_sanitizado;
    
    // Generar asunto
    $asunto = "¡Bienvenido a Seda y Lino, $nombre_sanitizado!";
    
    // Renderizar template unificado
    $content = render_email_template('bienvenida', [
        'nombre' => $nombre_sanitizado,
        'apellido' => $apellido_sanitizado,
        'email' => $email_sanitizado,
        'nombre_completo' => $nombre_completo
    ]);
    
    // Enviar email
    return enviar_email_gmail($email, $nombre_completo, $asunto, $content['html'], $content['text']);
}

/**
 * Genera el template HTML para email de bienvenida
 * 
 * @param string $nombre Nombre del usuario
 * @param string $apellido Apellido del usuario
 * @param string $email Email del usuario
 * @return string HTML del email
 */
function generar_template_bienvenida($nombre, $apellido, $email) {
    $content = render_email_template('bienvenida', [
        'nombre' => $nombre,
        'apellido' => $apellido,
        'email' => $email
    ]);
    return $content['html'];
}

/**
 * Genera versión en texto plano del email de bienvenida
 * 
 * @param string $nombre Nombre del usuario
 * @param string $apellido Apellido del usuario
 * @param string $email Email del usuario
 * @return string Texto plano del email
 */
function generar_texto_bienvenida($nombre, $apellido, $email) {
    $content = render_email_template('bienvenida', [
        'nombre' => $nombre,
        'apellido' => $apellido,
        'email' => $email
    ]);
    return $content['text'];
}

/**
 * Envía email de confirmación de pedido al cliente usando PHPMailer con Gmail SMTP
 * 
 * NOTA: Esta es la función principal usada actualmente en el sistema (procesar-pedido.php).
 * Utiliza PHPMailer con Gmail SMTP para envío confiable de emails.
 * 
 * DIFERENCIAS:
 * - enviar_email_confirmacion_pedido_gmail(): Usa PHPMailer/Gmail SMTP, recibe $pedido_exitoso (de $_SESSION) y $datos_usuario (FUNCIÓN PRINCIPAL)
 * - enviar_email_confirmacion_pedido(): Usa mail() nativo, recibe $datos_pedido y $datos_usuario (alternativa)
 * 
 * @param array $pedido_exitoso Datos del pedido (de $_SESSION['pedido_exitoso'])
 * @param array $datos_usuario Datos del usuario (nombre, apellido, email)
 * @return bool True si se envió correctamente, false si hubo error
 */
function enviar_email_confirmacion_pedido_gmail($pedido_exitoso, $datos_usuario) {
    // Sanitizar datos del usuario
    $nombre_sanitizado = htmlspecialchars($datos_usuario['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
    $apellido_sanitizado = htmlspecialchars($datos_usuario['apellido'] ?? '', ENT_QUOTES, 'UTF-8');
    $email_sanitizado = htmlspecialchars($datos_usuario['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $nombre_completo = trim($nombre_sanitizado . ' ' . $apellido_sanitizado);
    
    // Generar asunto
    $id_pedido_formateado = str_pad($pedido_exitoso['id_pedido'], 6, '0', STR_PAD_LEFT);
    $asunto = "Confirmación de Pedido #$id_pedido_formateado - Seda y Lino";
    
    // Renderizar template unificado
    $content = render_email_template('confirmacion_pedido', [
        'pedido' => $pedido_exitoso,
        'nombre_completo' => $nombre_completo
    ]);
    
    // Enviar email
    return enviar_email_gmail($email_sanitizado, $nombre_completo, $asunto, $content['html'], $content['text']);
}

/**
 * Genera el template HTML para email de confirmación de pedido
 * 
 * @param array $pedido Datos del pedido
 * @param string $nombre_completo Nombre completo del usuario
 * @return string HTML del email
 */
function generar_template_confirmacion_pedido_gmail($pedido, $nombre_completo) {
    $content = render_email_template('confirmacion_pedido', [
        'pedido' => $pedido,
        'nombre_completo' => $nombre_completo
    ]);
    return $content['html'];
}

/**
 * Genera versión en texto plano del email de confirmación de pedido
 * 
 * @param array $pedido Datos del pedido
 * @param string $nombre_completo Nombre completo del usuario
 * @return string Texto plano del email
 */
function generar_texto_confirmacion_pedido_gmail($pedido, $nombre_completo) {
    $content = render_email_template('confirmacion_pedido', [
        'pedido' => $pedido,
        'nombre_completo' => $nombre_completo
    ]);
    return $content['text'];
}

/**
 * Envía email de notificación cuando un pedido es cancelado o un pago es rechazado
 *
 * CASOS DE USO:
 * - Pedido cancelado automáticamente (auto_cleanup_reservas.php)
 * - Pedido cancelado manualmente por ventas
 * - Pedido cancelado por el cliente
 * - Pago rechazado por ventas
 *
 * @param int $id_pedido ID del pedido
 * @param int $id_usuario ID del usuario
 * @param string $tipo Tipo de notificación: 'cancelado' o 'rechazado'
 * @param string|null $motivo Motivo del rechazo/cancelación (opcional)
 * @param mysqli $mysqli Conexión a la base de datos
 * @return bool True si se envió correctamente, false si hubo error
 */
function enviar_email_pedido_cancelado_o_rechazado($id_pedido, $id_usuario, $tipo, $motivo = null, $mysqli = null) {
    try {
        // Si no se pasó mysqli, intentar obtener la conexión global
        if ($mysqli === null) {
            global $mysqli;
            if (!$mysqli) {
                error_log("enviar_email_pedido_cancelado_o_rechazado: No se pudo obtener conexión mysqli");
                return false;
            }
        }

        // Cargar función de usuario si no está cargada
        if (!function_exists('obtenerUsuarioPorId')) {
            require_once __DIR__ . '/../queries/usuario_queries.php';
        }

        // Obtener datos del usuario
        $usuario = obtenerUsuarioPorId($mysqli, $id_usuario);
        if (!$usuario || empty($usuario['email'])) {
            error_log("enviar_email_pedido_cancelado_o_rechazado: Usuario no encontrado o sin email. ID: $id_usuario");
            return false;
        }

        // Sanitizar datos
        $nombre_sanitizado = htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8');
        $apellido_sanitizado = htmlspecialchars($usuario['apellido'], ENT_QUOTES, 'UTF-8');
        $email_sanitizado = htmlspecialchars($usuario['email'], ENT_QUOTES, 'UTF-8');
        $nombre_completo = trim($nombre_sanitizado . ' ' . $apellido_sanitizado);

        // Formatear ID de pedido
        $id_pedido_formateado = str_pad($id_pedido, 6, '0', STR_PAD_LEFT);

        // Determinar asunto según tipo
        if ($tipo === 'rechazado') {
            $asunto = "Pago Rechazado - Pedido #$id_pedido_formateado - Seda y Lino";
        } else {
            $asunto = "Pedido Cancelado - #$id_pedido_formateado - Seda y Lino";
        }
        
        // Renderizar template unificado
        $content = render_email_template('pedido_cancelado_rechazado', [
            'id_pedido_formateado' => $id_pedido_formateado,
            'nombre' => $nombre_sanitizado,
            'tipo' => $tipo,
            'motivo' => $motivo
        ]);
        
        $cuerpo_html = $content['html'];
        $cuerpo_texto = $content['text'];

        // Enviar email
        $resultado = enviar_email_gmail($email_sanitizado, $nombre_completo, $asunto, $cuerpo_html, $cuerpo_texto);

        if ($resultado) {
            error_log("Email enviado exitosamente a $email_sanitizado - Pedido #$id_pedido - Tipo: $tipo");
        } else {
            error_log("Error al enviar email a $email_sanitizado - Pedido #$id_pedido - Tipo: $tipo");
        }

        return $resultado;

    } catch (Exception $e) {
        error_log("Error en enviar_email_pedido_cancelado_o_rechazado: " . $e->getMessage());
        return false;
    }
}

/**
 * Genera el template HTML para email de pedido cancelado o pago rechazado
 *
 * @param string $id_pedido_formateado ID del pedido formateado
 * @param string $nombre Nombre del usuario
 * @param string $tipo 'cancelado' o 'rechazado'
 * @param string|null $motivo Motivo del rechazo/cancelación (opcional)
 * @return string HTML del email
 */
function generar_template_pedido_cancelado_rechazado($id_pedido_formateado, $nombre, $tipo, $motivo = null) {
    $content = render_email_template('pedido_cancelado_rechazado', [
        'id_pedido_formateado' => $id_pedido_formateado,
        'nombre' => $nombre,
        'tipo' => $tipo,
        'motivo' => $motivo
    ]);
    return $content['html'];
}

/**
 * Genera versión en texto plano del email de pedido cancelado o pago rechazado
 *
 * @param string $id_pedido_formateado ID del pedido formateado
 * @param string $nombre Nombre del usuario
 * @param string $tipo 'cancelado' o 'rechazado'
 * @param string|null $motivo Motivo del rechazo/cancelación (opcional)
 * @return string Texto plano del email
 */
function generar_texto_pedido_cancelado_rechazado($id_pedido_formateado, $nombre, $tipo, $motivo = null) {
    $content = render_email_template('pedido_cancelado_rechazado', [
        'id_pedido_formateado' => $id_pedido_formateado,
        'nombre' => $nombre,
        'tipo' => $tipo,
        'motivo' => $motivo
    ]);
    return $content['text'];
}


/**
 * Envía email de notificación cuando un pedido cambia a estado 'En Viaje'
 *
 * @param int $id_pedido ID del pedido
 * @param int $id_usuario ID del usuario
 * @param string|null $codigo_seguimiento Código de seguimiento (opcional)
 * @param string|null $empresa_envio Empresa de envío (opcional)
 * @param mysqli $mysqli Conexión a la base de datos
 * @return bool True si se envió correctamente, false si hubo error
 */
function enviar_email_pedido_en_viaje($id_pedido, $id_usuario, $codigo_seguimiento = null, $empresa_envio = null, $mysqli = null) {
    try {
        // La conexión mysqli puede venir nula o cerrada, manejarlo con cuidado
        if ($mysqli === null) {
            global $mysqli;
        }

        // Cargar función de usuario si no está cargada
        if (!function_exists('obtenerUsuarioPorId')) {
            $usuario_queries_path = __DIR__ . '/queries/usuario_queries.php';
            if (file_exists($usuario_queries_path)) {
                require_once $usuario_queries_path;
            } elseif (file_exists(__DIR__ . '/../includes/queries/usuario_queries.php')) {
                require_once __DIR__ . '/../includes/queries/usuario_queries.php';
            }
        }

        if (!function_exists('obtenerUsuarioPorId')) {
            error_log("enviar_email_pedido_en_viaje: No se encontró la función obtenerUsuarioPorId");
            return false;
        }

        // Obtener datos del usuario
        $usuario = obtenerUsuarioPorId($mysqli, $id_usuario);
        if (!$usuario || empty($usuario['email'])) {
            error_log("enviar_email_pedido_en_viaje: Usuario no encontrado o sin email. ID: $id_usuario");
            return false;
        }

        // Sanitizar datos
        $nombre_sanitizado = htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8');
        $apellido_sanitizado = htmlspecialchars($usuario['apellido'], ENT_QUOTES, 'UTF-8');
        $email_sanitizado = htmlspecialchars($usuario['email'], ENT_QUOTES, 'UTF-8');
        $nombre_completo = trim($nombre_sanitizado . ' ' . $apellido_sanitizado);

        // Formatear ID de pedido
        $id_pedido_formateado = str_pad($id_pedido, 6, '0', STR_PAD_LEFT);

        $asunto = "¡Tu Pedido está en Camino! - #$id_pedido_formateado - Seda y Lino";
        
        // Renderizar template unificado
        $content = render_email_template('pedido_en_viaje', [
            'id_pedido_formateado' => $id_pedido_formateado,
            'nombre' => $nombre_sanitizado,
            'codigo_seguimiento' => $codigo_seguimiento,
            'empresa_envio' => $empresa_envio
        ]);
        
        $cuerpo_html = $content['html'];
        $cuerpo_texto = $content['text'];

        // Enviar email
        $resultado = enviar_email_gmail($email_sanitizado, $nombre_completo, $asunto, $cuerpo_html, $cuerpo_texto);

        if ($resultado) {
            error_log("Email de 'En Viaje' enviado exitosamente a $email_sanitizado - Pedido #$id_pedido");
        } else {
            error_log("Error al enviar email de 'En Viaje' a $email_sanitizado - Pedido #$id_pedido");
        }

        return $resultado;

    } catch (Exception $e) {
        error_log("Error en enviar_email_pedido_en_viaje: " . $e->getMessage());
        return false;
    }
}

/**
 * Genera el template HTML para email de pedido en viaje
 *
 * @param string $id_pedido_formateado ID del pedido formateado
 * @param string $nombre Nombre del usuario
 * @param string|null $codigo_seguimiento Código de seguimiento
 * @param string|null $empresa_envio Empresa de envío
 * @return string HTML del email
 */
function generar_template_pedido_en_viaje($id_pedido_formateado, $nombre, $codigo_seguimiento = null, $empresa_envio = null) {
    $content = render_email_template('pedido_en_viaje', [
        'id_pedido_formateado' => $id_pedido_formateado,
        'nombre' => $nombre,
        'codigo_seguimiento' => $codigo_seguimiento,
        'empresa_envio' => $empresa_envio
    ]);
    return $content['html'];
}

/**
 * Genera versión texto plano del email de pedido en viaje
 */
function generar_texto_pedido_en_viaje($id_pedido_formateado, $nombre, $codigo_seguimiento = null, $empresa_envio = null) {
    $content = render_email_template('pedido_en_viaje', [
        'id_pedido_formateado' => $id_pedido_formateado,
        'nombre' => $nombre,
        'codigo_seguimiento' => $codigo_seguimiento,
        'empresa_envio' => $empresa_envio
    ]);
    return $content['text'];
}

?>

