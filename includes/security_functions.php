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
        
        // Normalizar email para usar como clave
        $email_normalizado = strtolower(trim($email));
        
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
        
        // Normalizar email para usar como clave
        $email_normalizado = strtolower(trim($email));
        
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
        $email_normalizado = strtolower(trim($email));
        
        if (isset($_SESSION[$key][$email_normalizado])) {
            unset($_SESSION[$key][$email_normalizado]);
        }
    }
}


