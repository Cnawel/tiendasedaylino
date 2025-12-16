<?php
/**
 * Template unificado para email de confirmación de pedido
 * 
 * Variables disponibles:
 * @var array $pedido Datos del pedido
 * @var string $nombre_completo Nombre completo del usuario
 */

// === LÓGICA COMÚN ===
$id_pedido_formateado = str_pad($pedido['id_pedido'], 6, '0', STR_PAD_LEFT);

// Construir dirección completa
$direccion_parts = [];
if (!empty($pedido['direccion'])) {
    $direccion_parts[] = $pedido['direccion']; // Usaremos htmlspecialchars en HTML version
}
if (!empty($pedido['localidad'])) {
    $direccion_parts[] = $pedido['localidad'];
}
if (!empty($pedido['provincia'])) {
    $direccion_parts[] = $pedido['provincia'];
}
if (!empty($pedido['codigo_postal'])) {
    $direccion_parts[] = 'CP: ' . $pedido['codigo_postal'];
}

$direccion_completa_raw = !empty($direccion_parts) ? implode(', ', $direccion_parts) : 'N/A';
$direccion_completa_html = htmlspecialchars($direccion_completa_raw, ENT_QUOTES, 'UTF-8');

// Calcular totales
$subtotal_pedido = isset($pedido['subtotal']) ? (float)$pedido['subtotal'] : (float)$pedido['total'];
$costo_envio_pedido = isset($pedido['costo_envio']) ? (float)$pedido['costo_envio'] : ((float)$pedido['total'] - $subtotal_pedido);
$es_envio_gratis = isset($pedido['es_envio_gratis']) ? (bool)$pedido['es_envio_gratis'] : ($costo_envio_pedido == 0);
if ($es_envio_gratis && $costo_envio_pedido > 0) {
    $costo_envio_pedido = 0;
}
if ($costo_envio_pedido == 0 && !$es_envio_gratis) {
    $es_envio_gratis = true;
}

// Método de pago
$metodo_pago_raw = $pedido['metodo_pago'] ?? 'N/A';
$metodo_pago_html = htmlspecialchars($metodo_pago_raw, ENT_QUOTES, 'UTF-8');
$metodo_pago_descripcion_raw = $pedido['metodo_pago_descripcion'] ?? '';
$metodo_pago_descripcion_html = !empty($metodo_pago_descripcion_raw) ? htmlspecialchars($metodo_pago_descripcion_raw, ENT_QUOTES, 'UTF-8') : '';

// Detectar warnings según método de pago
$nombre_metodo_lower = strtolower($metodo_pago_raw);
$mostrar_warning_aprobacion = false;
$mensaje_warning = '';
if (strpos($nombre_metodo_lower, 'transferencia') !== false || 
    strpos($nombre_metodo_lower, 'depósito') !== false || 
    strpos($nombre_metodo_lower, 'efectivo') !== false ||
    strpos($nombre_metodo_lower, 'manual') !== false) {
    $mostrar_warning_aprobacion = true;
    $mensaje_warning = 'Tu pago será revisado manualmente. Recibirás confirmación por email en 24-48 horas una vez que se procese el pago.';
} elseif (strpos($nombre_metodo_lower, 'transferencia') !== false || 
          strpos($nombre_metodo_lower, 'depósito') !== false) {
    $mensaje_warning = 'Los pagos por transferencia pueden tardar 24-48hs en procesarse. Te notificaremos por email cuando se confirme el pago.';
}

// Generar filas de productos HTML
$filas_productos = '';
foreach ($pedido['productos'] as $producto) {
    $nombre_producto = htmlspecialchars($producto['nombre_producto'], ENT_QUOTES, 'UTF-8');
    $talle = htmlspecialchars($producto['talle'], ENT_QUOTES, 'UTF-8');
    $color = htmlspecialchars($producto['color'], ENT_QUOTES, 'UTF-8');
    $cantidad = (int)$producto['cantidad'];
    $precio_unitario = number_format((float)$producto['precio_unitario'], 2);
    $subtotal = number_format((float)$producto['subtotal'], 2);
    
    $filas_productos .= "
    <tr>
        <td style='padding: 15px; border-bottom: 1px solid #E8DDD0;'>
            <strong style='color: #8B7355; font-size: 16px;'>$nombre_producto</strong><br>
            <small style='color: #6B5D47;'>Talla: $talle | Color: $color</small>
        </td>
        <td style='padding: 15px; border-bottom: 1px solid #E8DDD0; text-align: center; color: #6B5D47;'>$cantidad</td>
        <td style='padding: 15px; border-bottom: 1px solid #E8DDD0; text-align: right; color: #6B5D47;'>\$precio_unitario</td>
        <td style='padding: 15px; border-bottom: 1px solid #E8DDD0; text-align: right;'><strong style='color: #8B7355;'>\$subtotal</strong></td>
    </tr>";
}

// === VERSIÓN HTML ===
$html = "
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Confirmación de Pedido</title>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px; background-color: #F5E6D3;'>
    
    <!-- Header -->
    <div style='background: #B8A082; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
        <h1 style='margin: 0; font-size: 28px; font-weight: 700;'>SEDA Y LINO</h1>
        <p style='margin: 10px 0 0 0; font-size: 14px; opacity: 0.95;'>Elegancia atemporal en cada prenda</p>
    </div>
    
    <!-- Confirmación -->
    <div style='background: white; padding: 40px 30px; text-align: center;'>
        <div style='background: #E8DDD0; display: inline-block; padding: 20px 30px; border-radius: 50px; margin-bottom: 20px;'>
            <span style='color: #8B7355; font-size: 48px;'>✓</span>
        </div>
        <h2 style='color: #8B7355; margin: 20px 0 15px 0; font-size: 26px; font-weight: 700;'>¡Pedido Confirmado!</h2>
        <p style='color: #6B5D47; margin: 0; font-size: 16px; line-height: 1.8;'>
            Hola <strong>" . htmlspecialchars($nombre_completo, ENT_QUOTES, 'UTF-8') . "</strong>, recibimos tu pedido correctamente.
        </p>
    </div>
    
    <!-- Método de Pago Destacado -->
    <div style='background: #F5E6D3; border: 2px solid #B8A082; border-radius: 12px; padding: 25px; margin: 20px 0;'>
        <div style='margin-bottom: 15px;'>
            <div style='font-size: 14px; font-weight: 700; color: #8B7355; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;'>
                💳 Método de Pago
            </div>
            <div style='font-size: 22px; font-weight: 800; color: #8B7355; text-transform: uppercase; letter-spacing: 1.5px;'>$metodo_pago_html</div>
        </div>";

if (!empty($metodo_pago_descripcion_html)) {
    $html .= "
        <div style='background: white; border: 1px solid #D4C4A8; border-left: 4px solid #B8A082; border-radius: 8px; padding: 15px; margin-top: 15px;'>
            <div style='font-size: 12px; font-weight: 700; color: #6B5D47; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;'>Detalles del Pago</div>
            <div style='font-size: 14px; font-weight: 600; color: #8B7355; line-height: 1.6; word-break: break-word;'>$metodo_pago_descripcion_html</div>
        </div>";
}

if ($mostrar_warning_aprobacion && !empty($mensaje_warning)) {
    $html .= "
        <div style='background: #E3F2FD; border-left: 4px solid #0dcaf0; border-radius: 5px; padding: 15px; margin-top: 15px;'>
            <div style='font-weight: 700; color: #1976d2; margin-bottom: 5px;'>📌 Próximos pasos:</div>
            <div style='font-size: 13px; color: #495057; line-height: 1.6;'>" . htmlspecialchars($mensaje_warning, ENT_QUOTES, 'UTF-8') . "</div>
        </div>";
}

$html .= "
    </div>
    
    <!-- Detalles del Pedido -->
    <div style='background: white; padding: 30px; border-left: 4px solid #B8A082; margin-top: 20px;'>
        <h3 style='color: #8B7355; margin-top: 0; border-bottom: 2px solid #E8DDD0; padding-bottom: 10px; font-size: 20px;'>
            📋 Detalles del Pedido
        </h3>
        
        <table style='width: 100%; margin-top: 20px;'>
            <tr>
                <td style='padding: 12px 0; color: #6B5D47;'><strong>Número de Pedido:</strong></td>
                <td style='padding: 12px 0; text-align: right; color: #8B7355; font-size: 20px; font-weight: 700; letter-spacing: 2px;'>#$id_pedido_formateado</td>
            </tr>
        </table>
        
        <hr style='border: none; border-top: 1px solid #E8DDD0; margin: 25px 0;'>
        
        <h4 style='color: #8B7355; margin-top: 30px; margin-bottom: 15px; font-size: 18px;'>📦 Productos (" . count($pedido['productos']) . ")</h4>
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 25px;'>
            <thead>
                <tr style='background: #F5E6D3;'>
                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #D4C4A8; color: #8B7355; font-size: 14px;'>Producto</th>
                    <th style='padding: 12px; text-align: center; border-bottom: 2px solid #D4C4A8; color: #8B7355; font-size: 14px;'>Cant.</th>
                    <th style='padding: 12px; text-align: right; border-bottom: 2px solid #D4C4A8; color: #8B7355; font-size: 14px;'>Precio</th>
                    <th style='padding: 12px; text-align: right; border-bottom: 2px solid #D4C4A8; color: #8B7355; font-size: 14px;'>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                $filas_productos
            </tbody>
            <tfoot>
                <tr style='background: #F5E6D3;'>
                    <td colspan='3' style='padding: 15px; text-align: right; border-top: 2px solid #D4C4A8; color: #6B5D47;'><strong>Subtotal:</strong></td>
                    <td style='padding: 15px; text-align: right; border-top: 2px solid #D4C4A8;'><strong style='color: #8B7355;'>\$" . number_format($subtotal_pedido, 2) . "</strong></td>
                </tr>
                <tr style='background: #F5E6D3;'>
                    <td colspan='3' style='padding: 15px; text-align: right; color: #6B5D47;'><strong>Envío:</strong></td>
                    <td style='padding: 15px; text-align: right;'>";

if ($es_envio_gratis) {
    $html .= "<span style='font-weight: 700; color: #8B7355; font-size: 16px;'>GRATIS</span>";
} else {
    $html .= "<strong style='color: #8B7355;'>\$" . number_format($costo_envio_pedido, 2) . "</strong>";
}

$html .= "
                    </td>
                </tr>
                <tr style='background: #B8A082; color: white;'>
                    <td colspan='3' style='padding: 18px; text-align: right; font-size: 18px;'><strong>TOTAL:</strong></td>
                    <td style='padding: 18px; text-align: right; font-size: 20px; font-weight: 800;'>\$" . number_format($pedido['total'], 2) . "</td>
                </tr>
            </tfoot>
        </table>
        
        <h4 style='color: #8B7355; margin-top: 30px; margin-bottom: 15px; font-size: 18px;'>🚚 Dirección de Envío</h4>
        <div style='background: #F5E6D3; padding: 15px; border-radius: 5px; border-left: 4px solid #B8A082;'>
            <p style='margin: 0; color: #6B5D47; line-height: 1.8;'>$direccion_completa_html</p>
        </div>
    </div>
    
    <!-- Próximos Pasos -->
    <div style='background: #E8DDD0; padding: 30px; border-radius: 5px; margin-top: 20px;'>
        <h3 style='color: #8B7355; margin-top: 0; font-size: 18px;'>📌 Próximos Pasos</h3>
        <ul style='color: #6B5D47; padding-left: 20px; line-height: 2;'>
            <li style='margin-bottom: 10px;'><strong>Prepararemos tu pedido</strong> en las próximas 24-48 horas.</li>
            <li style='margin-bottom: 10px;'><strong>Envío</strong> - Recibirás tu pedido en 3-5 días hábiles" . ($es_envio_gratis ? ' (GRATIS)' : '') . ".</li>
            <li style='margin-bottom: 10px;'><strong>Te avisaremos</strong> cuando tu pedido sea despachado.</li>
            <li><strong>Guarda este email</strong> como comprobante de tu compra.</li>
        </ul>
    </div>
    
    <!-- Información de Contacto -->
    <div style='background: white; padding: 20px; text-align: center; margin-top: 20px; border-top: 3px solid #B8A082;'>
        <p style='color: #6B5D47; margin: 10px 0;'>¿Tienes alguna duda?</p>
        <p style='margin: 10px 0;'>
            <a href='mailto:" . (defined('GMAIL_FROM_EMAIL') ? GMAIL_FROM_EMAIL : 'info@sedaylino.com') . "' style='color: #8B7355; text-decoration: none; font-weight: 600;'>" . (defined('GMAIL_FROM_EMAIL') ? GMAIL_FROM_EMAIL : 'info@sedaylino.com') . "</a>
        </p>
    </div>
    
    <!-- Footer -->
    <div style='background: #8B7355; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; margin-top: 20px;'>
        <p style='margin: 0; font-size: 14px;'>Gracias por tu compra en <strong>Seda y Lino</strong></p>
        <p style='margin: 10px 0 0 0; font-size: 12px; opacity: 0.9;'>© 2025 Seda y Lino. Todos los derechos reservados.</p>
    </div>
    
</body>
</html>
";

// === VERSIÓN TEXTO PLANO ===
$text = "========================================
SEDA Y LINO - Confirmación de Pedido
========================================

¡Hola $nombre_completo!

Tu pedido ha sido confirmado exitosamente.

DETALLES DEL PEDIDO
-------------------
Número de Pedido: #$id_pedido_formateado
Método de Pago: $metodo_pago_raw
";

if (!empty($metodo_pago_descripcion_raw)) {
    $text .= "Detalles del Pago: $metodo_pago_descripcion_raw\n";
}
$text .= "\n";

$text .= "PRODUCTOS
---------
";

foreach ($pedido['productos'] as $producto) {
    $text .= "- {$producto['nombre_producto']}\n";
    $text .= "  Talla: {$producto['talle']} | Color: {$producto['color']}\n";
    $text .= "  Cantidad: {$producto['cantidad']} x \$" . number_format($producto['precio_unitario'], 2) . "\n";
    $text .= "  Subtotal: \$" . number_format($producto['subtotal'], 2) . "\n\n";
}

$text .= "RESUMEN
-------
Subtotal: \$" . number_format($subtotal_pedido, 2) . "\n";
if ($es_envio_gratis) {
    $text .= "Envío: GRATIS\n";
} else {
    $text .= "Envío: \$" . number_format($costo_envio_pedido, 2) . "\n";
}
$text .= "TOTAL: \$" . number_format($pedido['total'], 2) . "\n\n";

$text .= "DIRECCIÓN DE ENVÍO
------------------
$direccion_completa_raw

PRÓXIMOS PASOS
--------------
- Prepararemos tu pedido en 24-48 horas
- Envío en 3-5 días hábiles" . ($es_envio_gratis ? ' (GRATIS)' : '') . "
- Te avisaremos cuando sea despachado

¿Dudas? Contáctanos: " . (defined('GMAIL_FROM_EMAIL') ? GMAIL_FROM_EMAIL : 'info@sedaylino.com') . "

Gracias por tu compra en Seda y Lino
© 2025 Seda y Lino
";

return [
    'html' => $html,
    'text' => $text
];
?>
