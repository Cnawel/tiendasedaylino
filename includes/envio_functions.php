<?php
/**
 * ========================================================================
 * FUNCIONES DE ENVÍO - Tienda Seda y Lino
 * ========================================================================
 * Funciones para calcular costos de envío y parsear direcciones
 * 
 * Lógica de envío:
 * - Envío gratis para CABA/GBA con compras superiores a $80,000
 * - Envío fijo de $10,000 para CABA/GBA con compras menores a $80,000
 * - Envío fijo de $15,000 para resto de provincias (sin envío gratis)
 * 
 * Funciones:
 * - calcularCostoEnvio(): Calcula el costo de envío según total y ubicación
 * - obtenerInfoEnvioCarrito(): Obtiene información de envío para mostrar en carrito
 * - esCABAGBA(): Verifica si la localidad es CABA o GBA
 * - parsearDireccion(): Parsea una dirección completa en componentes (calle, número, piso)
 * ========================================================================
 */

/**
 * Verifica si una localidad corresponde a CABA o GBA
 * 
 * @param string $provincia Nombre de la provincia
 * @param string $localidad Nombre de la localidad
 * @return bool True si es CABA o GBA, False en caso contrario
 */
function esCABAGBA($provincia, $localidad) {
    // Normalizar strings para comparación
    $provincia_normalizada = mb_strtoupper(trim($provincia), 'UTF-8');
    $localidad_normalizada = mb_strtoupper(trim($localidad), 'UTF-8');
    
    // Si la provincia es directamente CABA, retornar true
    if ($provincia_normalizada === 'CABA') {
        return true;
    }
    
    // Verificar que sea provincia de Buenos Aires
    if ($provincia_normalizada !== 'BUENOS AIRES') {
        return false;
    }
    
    // Lista de localidades que corresponden a CABA
    $localidades_caba = [
        'CABA',
        'C.A.B.A',
        'C.A.B.A.',
        'CIUDAD AUTONOMA DE BUENOS AIRES',
        'CIUDAD AUTÓNOMA DE BUENOS AIRES',
        'CIUDAD AUTONOMA',
        'CIUDAD AUTÓNOMA',
        'CAPITAL FEDERAL',
        'CAPITAL',
        'BUENOS AIRES', // Algunos usuarios pueden poner solo "Buenos Aires"
        'CABA CAPITAL'
    ];
    
    // Lista de localidades comunes de GBA (Gran Buenos Aires)
    $localidades_gba = [
        'GBA',
        'GRAN BUENOS AIRES',
        'ALMIRANTE BROWN',
        'AVELLANEDA',
        'BERAZATEGUI',
        'BERISSO',
        'BRANDEN',
        'CAMPANA',
        'CAÑUELAS',
        'ESCOBAR',
        'ESTEBAN ECHEVERRIA',
        'EZEIZA',
        'FLORENCIO VARELA',
        'GENERAL RODRIGUEZ',
        'GENERAL SAN MARTIN',
        'HURLINGHAM',
        'ITUZAINGO',
        'JOSE C PAZ',
        'LA MATANZA',
        'LANUS',
        'LOMAS DE ZAMORA',
        'LUJAN',
        'MALVINAS ARGENTINAS',
        'MARCOS PAZ',
        'MERLO',
        'MORENO',
        'MORON',
        'PILAR',
        'QUILMES',
        'SAN FERNANDO',
        'SAN ISIDRO',
        'SAN MIGUEL',
        'SAN VICENTE',
        'TIGRE',
        'TRES DE FEBRERO',
        'VICENTE LOPEZ',
        'ZARATE',
        // Agregar más localidades comunes si es necesario
        'BANFIELD',
        'TEMPERLEY',
        'ADROGUE',
        'BURZACO',
        'LONGCHAMPS',
        'CLAYPOLE',
        'MONTE GRANDE',
        'OLIVOS',
        'MARTINEZ',
        'ACASSUSO',
        'BELLA VISTA',
        'MUÑIZ',
        'SAN MARTIN',
        'SAN ANDRES',
        'VICTORIA',
        'CARAPACHAY',
        'VICENTE LOPEZ',
        'VILLA MARTELLI',
        'FLORIDA',
        'MUNRO',
        'PARQUE PATRICIOS',
        'BOEDO',
        'CABALLITO',
        'FLORES',
        'VERSALLES',
        'VILLA CRESPO',
        'PALERMO',
        'BELGRANO',
        'NUÑEZ',
        'COLEGIALES',
        'CHACARITA',
        'VILLA URQUIZA',
        'SAVEDRA',
        'COGHLAN',
        'VILLA DEL PARQUE',
        'VERSALLES',
        'LINIERS',
        'MATADEROS',
        'PARQUE AVELLANEDA',
        'VILLA LUGANO',
        'VILLA SOLDATI',
        'BARRACAS',
        'LA BOCA',
        'SAN TELMO',
        'CONSTITUCION',
        'SOLDATI',
        'VILLA RIACHUELO',
        'VILLA LUGANO',
        'PARQUE PATRICIOS',
        'NUEVA POMPEYA',
        'FLORES',
        'FLORESTA',
        'VELEZ SARSFIELD',
        'VILLA REAL',
        'MONTE CASTRO',
        'VILLA DEVOTO',
        'VILLA DEL PARQUE',
        'VILLA SANTA RITA',
        'COGHLAN',
        'SAVEDRA',
        'BELGRANO',
        'NUÑEZ',
        'COLEGIALES',
        'CHACARITA',
        'VILLA CRESPO',
        'PALERMO',
        'RECOLETA',
        'RETIRO',
        'PUERTO MADERO',
        'SAN NICOLAS',
        'MONSERRAT',
        'CONSTITUCION',
        'SAN CRISTOBAL',
        'BALVANERA',
        'ALMAGRO',
        'BOEDO',
        'CABALLITO',
        'FLORES',
        'FLORESTA',
        'VELEZ SARSFIELD',
        'VILLA REAL',
        'MONTE CASTRO',
        'VILLA DEVOTO',
        'VILLA DEL PARQUE',
        'VILLA SANTA RITA',
        'PARQUE CHACABUCO',
        'PARQUE PATRICIOS',
        'BARRACAS',
        'LA BOCA',
        'SAN TELMO',
        'CONSTITUCION',
        'SOLDATI',
        'VILLA RIACHUELO',
        'VILLA LUGANO',
        'PARQUE PATRICIOS',
        'NUEVA POMPEYA'
    ];
    
    // Verificar si la localidad está en alguna de las listas
    if (in_array($localidad_normalizada, array_map('mb_strtoupper', $localidades_caba))) {
        return true;
    }
    
    if (in_array($localidad_normalizada, array_map('mb_strtoupper', $localidades_gba))) {
        return true;
    }
    
    // Verificar si contiene palabras clave comunes de GBA
    $palabras_clave_gba = ['GBA', 'GRAN BUENOS AIRES', 'ZONA NORTE', 'ZONA SUR', 'ZONA OESTE'];
    foreach ($palabras_clave_gba as $palabra) {
        if (mb_strpos($localidad_normalizada, mb_strtoupper($palabra, 'UTF-8')) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Calcula el costo de envío según el total del pedido y la ubicación
 * 
 * @param float $total_pedido Total del pedido (subtotal de productos)
 * @param string $provincia Nombre de la provincia
 * @param string $localidad Nombre de la localidad
 * @return array Array con 'costo' (float), 'es_gratis' (bool) y 'mensaje' (string)
 */
function calcularCostoEnvio($total_pedido, $provincia = '', $localidad = '') {
    // Monto mínimo para envío gratis (solo para CABA/GBA)
    $monto_minimo_gratis = 80000;
    
    // Costo fijo de envío para CABA y GBA
    $costo_fijo_caba_gba = 10000;
    
    // Costo fijo de envío para Todo Argentina
    $costo_fijo_argentina = 15000;
    
    // Verificar si es CABA o GBA
    $es_caba_gba = false;
    if (!empty($provincia) && !empty($localidad)) {
        $es_caba_gba = esCABAGBA($provincia, $localidad);
    }
    
    // Si es CABA o GBA y el total es mayor o igual al monto mínimo, envío gratis
    if ($es_caba_gba && $total_pedido >= $monto_minimo_gratis) {
        return [
            'costo' => 0,
            'es_gratis' => true,
            'mensaje' => 'GRATIS'
        ];
    }
    
    // Si es CABA o GBA pero el total es menor al monto mínimo
    if ($es_caba_gba) {
        return [
            'costo' => $costo_fijo_caba_gba,
            'es_gratis' => false,
            'mensaje' => '$' . number_format($costo_fijo_caba_gba, 2, ',', '.')
        ];
    }
    
    // Para otras provincias (Todo Argentina) - siempre $15,000 sin envío gratis
    return [
        'costo' => $costo_fijo_argentina,
        'es_gratis' => false,
        'mensaje' => '$' . number_format($costo_fijo_argentina, 2, ',', '.')
    ];
}

/**
 * Obtiene información de envío para mostrar en el carrito
 * Retorna información completa de costos de envío según el monto del pedido
 * 
 * @param float $total_pedido Total del pedido (subtotal de productos)
 * @return array Array con información completa de envío
 */
function obtenerInfoEnvioCarrito($total_pedido) {
    // Monto mínimo para envío gratis
    $monto_minimo_gratis = 80000;
    
    // Costo fijo de envío para CABA y GBA
    $costo_fijo_caba_gba = 10000;
    
    // Costo fijo de envío para Todo Argentina
    $costo_fijo_argentina = 15000;
    
    // Si el total es mayor o igual al monto mínimo, envío gratis
    if ($total_pedido >= $monto_minimo_gratis) {
        return [
            'es_gratis' => true,
            'costo_caba_gba' => 0,
            'costo_argentina' => 0,
            'costo' => 0,
            'mensaje' => 'GRATIS'
        ];
    }
    
    // Si el total es menor al monto mínimo, mostrar ambos costos
    return [
        'es_gratis' => false,
        'costo_caba_gba' => $costo_fijo_caba_gba,
        'costo_argentina' => $costo_fijo_argentina,
        'costo' => $costo_fijo_caba_gba, // Costo por defecto (mínimo)
        'mensaje' => 'Desde $' . number_format($costo_fijo_caba_gba, 2, ',', '.')
    ];
}

/**
 * Calcula el total del pedido incluyendo envío
 * 
 * @param float $subtotal Subtotal de productos
 * @param float $costo_envio Costo de envío
 * @return float Total del pedido
 */
function calcularTotalConEnvio($subtotal, $costo_envio) {
    return $subtotal + $costo_envio;
}

/**
 * Obtiene el monto faltante para envío gratis
 * 
 * @param float $total_actual Total actual del pedido
 * @param float $monto_minimo_gratis Monto mínimo para envío gratis (default: 80000)
 * @return float Monto faltante, 0 si ya alcanza el mínimo
 */
function obtenerMontoFaltanteEnvioGratis($total_actual, $monto_minimo_gratis = 80000) {
    if ($total_actual >= $monto_minimo_gratis) {
        return 0;
    }
    return $monto_minimo_gratis - $total_actual;
}

/**
 * Parsea una dirección completa en componentes (calle, número, piso)
 * 
 * Esta función centraliza la lógica de parseo de direcciones que se repetía
 * en múltiples archivos (perfil.php, checkout.php, etc.)
 * 
 * @param string $direccion_completa Dirección completa a parsear
 * @return array Array asociativo con 'calle', 'numero', 'piso'
 *               Ejemplo: ['calle' => 'Av. Corrientes', 'numero' => '1234', 'piso' => '2° A']
 */
function parsearDireccion($direccion_completa) {
    $direccion_parseada = ['calle' => '', 'numero' => '', 'piso' => ''];
    
    if (empty($direccion_completa)) {
        return $direccion_parseada;
    }
    
    // Parseo simple: buscar primer número
    // Patrón: texto + número + texto opcional (piso/depto)
    if (preg_match('/^(.+?)\s+(\d+)(.*)$/', trim($direccion_completa), $matches)) {
        $direccion_parseada['calle'] = trim($matches[1]);
        $direccion_parseada['numero'] = $matches[2];
        $direccion_parseada['piso'] = trim($matches[3]);
    } else {
        // Si no hay número, toda la dirección es la calle
        $direccion_parseada['calle'] = trim($direccion_completa);
    }
    
    return $direccion_parseada;
}

