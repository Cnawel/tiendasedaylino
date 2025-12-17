<?php
/**
 * ========================================================================
 * FUNCIONES DE SEGURIDAD - Tienda Seda y Lino
 * ========================================================================
 * Funciones para prevenir ataques de fuerza bruta y mejorar la seguridad
 * ========================================================================
 */

if (!function_exists('verificarIntentosFormulario')) {
    /**
     * Verifica si un email puede realizar intentos en un formulario
     * según el límite de intentos establecido
     * 
     * @param string $email Email del usuario
     * @param string $tipo_formulario Tipo de formulario ('recupero', 'login', 'register', etc.)
     * @return array Array con 'permitido' (bool) y 'mensaje' (string) si está bloqueado
     */
    function verificarIntentosFormulario($email, $tipo_formulario = 'recupero') {
        $max_intentos = 10;
        $tiempo_bloqueo = 300; // 5 minutos en segundos
        $key = "intentos_{$tipo_formulario}";
        
        // Normalizar email para usar como clave (misma lógica que normalizarEmail())
        // Usar normalizarEmail() si está disponible, sino usar la misma lógica
        if (function_exists('normalizarEmail')) {
            $email_normalizado = normalizarEmail($email);
        } else {
            // Misma lógica que normalizarEmail(): convertir a minúsculas, eliminar espacios y caracteres de control
            $email_normalizado = strtolower(trim($email));
            $email_normalizado = preg_replace('/[\x00-\x1F\x7F]/u', '', $email_normalizado);
        }
        
        // Inicializar estructura si no existe
        if (!isset($_SESSION[$key][$email_normalizado])) {
            $_SESSION[$key][$email_normalizado] = [
                'intentos' => 0,
                'primer_intento' => null,
                'bloqueado_hasta' => null
            ];
        }
        
        $datos_intentos = &$_SESSION[$key][$email_normalizado];
        $ahora = time();
        
        // Verificar si está bloqueado
        if ($datos_intentos['bloqueado_hasta'] && $ahora < $datos_intentos['bloqueado_hasta']) {
            $tiempo_restante = ceil(($datos_intentos['bloqueado_hasta'] - $ahora) / 60);
            return [
                'permitido' => false,
                'mensaje' => "Este correo está temporalmente bloqueado. Intenta nuevamente en {$tiempo_restante} " . ($tiempo_restante == 1 ? 'minuto' : 'minutos') . "."
            ];
        }
        
        // Si el bloqueo expiró, reiniciar
        if ($datos_intentos['bloqueado_hasta'] && $ahora >= $datos_intentos['bloqueado_hasta']) {
            $datos_intentos['intentos'] = 0;
            $datos_intentos['primer_intento'] = null;
            $datos_intentos['bloqueado_hasta'] = null;
        }
        
        return ['permitido' => true];
    }
}

if (!function_exists('incrementarIntentosFormulario')) {
    /**
     * Incrementa el contador de intentos fallidos para un email
     * 
     * @param string $email Email del usuario
     * @param string $tipo_formulario Tipo de formulario ('recupero', 'login', 'register', etc.)
     * @return array Array con 'bloqueado' (bool) y 'mensaje' (string) si se bloquea
     */
    function incrementarIntentosFormulario($email, $tipo_formulario = 'recupero') {
        $max_intentos = 10;
        $tiempo_bloqueo = 300; // 5 minutos en segundos
        $key = "intentos_{$tipo_formulario}";
        
        // Normalizar email para usar como clave (misma lógica que normalizarEmail())
        // Usar normalizarEmail() si está disponible, sino usar la misma lógica
        if (function_exists('normalizarEmail')) {
            $email_normalizado = normalizarEmail($email);
        } else {
            // Misma lógica que normalizarEmail(): convertir a minúsculas, eliminar espacios y caracteres de control
            $email_normalizado = strtolower(trim($email));
            $email_normalizado = preg_replace('/[\x00-\x1F\x7F]/u', '', $email_normalizado);
        }
        
        // Asegurar que existe la estructura
        if (!isset($_SESSION[$key][$email_normalizado])) {
            $_SESSION[$key][$email_normalizado] = [
                'intentos' => 0,
                'primer_intento' => null,
                'bloqueado_hasta' => null
            ];
        }
        
        $datos_intentos = &$_SESSION[$key][$email_normalizado];
        $datos_intentos['intentos']++;
        
        // Si es el primer intento fallido, guardar timestamp
        if ($datos_intentos['primer_intento'] === null) {
            $datos_intentos['primer_intento'] = time();
        }
        
        // Si alcanza el límite, bloquear
        if ($datos_intentos['intentos'] >= $max_intentos) {
            $datos_intentos['bloqueado_hasta'] = $datos_intentos['primer_intento'] + $tiempo_bloqueo;
            return [
                'bloqueado' => true,
                'mensaje' => "Has alcanzado el límite de {$max_intentos} intentos. Por favor, espera 5 minutos antes de intentar nuevamente."
            ];
        }
        
        // Calcular intentos restantes
        $intentos_restantes = $max_intentos - $datos_intentos['intentos'];
        
        return [
            'bloqueado' => false,
            'intentos_restantes' => $intentos_restantes,
            'intentos_totales' => $datos_intentos['intentos']
        ];
    }
}

if (!function_exists('limpiarIntentosFormulario')) {
    /**
     * Limpia los intentos fallidos para un email (usar cuando el intento es exitoso)
     *
     * @param string $email Email del usuario
     * @param string $tipo_formulario Tipo de formulario ('recupero', 'login', 'register', etc.)
     * @return void
     */
    function limpiarIntentosFormulario($email, $tipo_formulario = 'recupero') {
        $key = "intentos_{$tipo_formulario}";
        // Normalizar email para usar como clave (misma lógica que normalizarEmail())
        // Usar normalizarEmail() si está disponible, sino usar la misma lógica
        if (function_exists('normalizarEmail')) {
            $email_normalizado = normalizarEmail($email);
        } else {
            // Misma lógica que normalizarEmail(): convertir a minúsculas, eliminar espacios y caracteres de control
            $email_normalizado = strtolower(trim($email));
            $email_normalizado = preg_replace('/[\x00-\x1F\x7F]/u', '', $email_normalizado);
        }

        if (isset($_SESSION[$key][$email_normalizado])) {
            unset($_SESSION[$key][$email_normalizado]);
        }
    }
}

if (!function_exists('destruirSesionSegura')) {
    /**
     * Destruye la sesión de manera segura eliminando cookies y limpiando buffers
     * Centraliza la lógica de destrucción de sesión usada en logout y perfil
     *
     * @param string $redirect_url URL a redirigir después de destruir sesión (por defecto 'login.php')
     * @return void
     */
    function destruirSesionSegura($redirect_url = 'login.php') {
        // Limpiar array de sesión
        $_SESSION = array();
        session_destroy();
        session_write_close();

        // Destruir cookie de sesión
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // Limpiar buffers de salida
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Redirigir al usuario
        if (!headers_sent()) {
            header('Location: ' . $redirect_url, true, 302);
        } else {
            echo '<script>window.location.href = "' . htmlspecialchars($redirect_url, ENT_QUOTES, 'UTF-8') . '";</script>';
        }
        exit;
    }
}

if (!function_exists('redirigirConMensaje')) {
    /**
     * Redirige a una URL guardando un mensaje en la sesión
     * Centraliza la lógica de redirección con mensaje usada en perfil.php
     *
     * @param string $url URL de destino para redirigir
     * @param string $mensaje Mensaje a mostrar después de redirigir
     * @param string $tipo Tipo de mensaje ('success', 'error', 'warning', 'info')
     * @return void
     */
    function redirigirConMensaje($url, $mensaje, $tipo = 'success') {
        // Guardar mensaje en sesión
        $_SESSION['mensaje'] = $mensaje;
        $_SESSION['mensaje_tipo'] = $tipo;

        // Limpiar buffers de salida
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Redirigir al usuario
        if (!headers_sent()) {
            header('Location: ' . $url, true, 302);
        } else {
            echo '<script>window.location.href = "' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '";</script>';
        }
        exit;
    }
}

/**
 * ========================================================================
 * MANEJO SEGURO DE ERRORES
 * ========================================================================
 * Funciones para registrar errores en logs y mostrar mensajes genéricos
 * al usuario, previniendo information disclosure
 */

if (!function_exists('manejarErrorExcepcion')) {
    /**
     * Maneja errores de excepción de forma segura
     * Registra detalles técnicos en logs y muestra mensaje genérico al usuario
     *
     * @param Exception $excepcion Excepción capturada
     * @param string $contexto Contexto del error (ej: 'registro', 'login', 'carrito', 'checkout', 'pago')
     * @param string $datos_usuario Datos no sensibles para debugging (opcional)
     * @return string Mensaje genérico para mostrar al usuario
     */
    function manejarErrorExcepcion($excepcion, $contexto = 'sistema', $datos_usuario = '') {
        $timestamp = date('Y-m-d H:i:s');
        $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
        $usuario_id = $_SESSION['id_usuario'] ?? 'anonimo';

        $log_mensaje = "[$timestamp] [ERROR EN " . strtoupper($contexto) . "] IP: $ip_cliente | Usuario: $usuario_id";
        if ($datos_usuario) {
            $log_mensaje .= " | Contexto: $datos_usuario";
        }
        $log_mensaje .= "\nMensaje: " . $excepcion->getMessage() . "\n" .
                       "Archivo: " . $excepcion->getFile() . " (línea " . $excepcion->getLine() . ")\n" .
                       "Trace: " . $excepcion->getTraceAsString() . "\n---\n";

        error_log($log_mensaje);

        $mensajes_usuario = [
            'registro' => 'Error al procesar tu registro. Por favor, inténtalo nuevamente.',
            'login' => 'Error al iniciar sesión. Por favor, verifica tus credenciales.',
            'recupero' => 'Error al procesar tu solicitud de recuperación. Por favor, inténtalo nuevamente.',
            'carrito' => 'Error al procesar tu carrito. Por favor, recarga la página.',
            'checkout' => 'Error al procesar tu pedido. Por favor, inténtalo nuevamente.',
            'pago' => 'Error al procesar el pago. Por favor, inténtalo nuevamente.',
            'perfil' => 'Error al actualizar tu perfil. Por favor, inténtalo nuevamente.',
            'imagen' => 'Error al procesar la imagen. Verifica que sea JPG, PNG o GIF.',
            'default' => 'Ha ocurrido un error. Por favor, inténtalo nuevamente.'
        ];

        return $mensajes_usuario[$contexto] ?? $mensajes_usuario['default'];
    }
}

if (!function_exists('manejarErrorBD')) {
    /**
     * Maneja errores de base de datos de forma segura
     * Registra detalles técnicos en logs y muestra mensaje genérico al usuario
     *
     * @param mysqli $mysqli Conexión de base de datos
     * @param string $contexto Contexto del error
     * @param string $query Consulta que causó el error (opcional)
     * @return string Mensaje genérico para mostrar al usuario
     */
    function manejarErrorBD($mysqli, $contexto = 'sistema', $query = '') {
        $timestamp = date('Y-m-d H:i:s');
        $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
        $usuario_id = $_SESSION['id_usuario'] ?? 'anonimo';

        $log_mensaje = "[$timestamp] [ERROR BD EN " . strtoupper($contexto) . "] IP: $ip_cliente | Usuario: $usuario_id\n" .
                       "Error MySQL: " . $mysqli->error . " (Código: " . $mysqli->errno . ")\n";
        if ($query) {
            $log_mensaje .= "Consulta: $query\n";
        }
        $log_mensaje .= "---\n";

        error_log($log_mensaje);

        return 'Error al acceder a la base de datos. Por favor, inténtalo nuevamente.';
    }
}


