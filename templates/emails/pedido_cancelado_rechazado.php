<?php
/**
 * Template unificado para email de pedido cancelado o rechazado
 * 
 * Variables disponibles:
 * @var string $id_pedido_formateado ID del pedido
 * @var string $nombre Nombre del usuario
 * @var string $tipo 'rechazado' o 'cancelado'
 * @var string|null $motivo Motivo del rechazo/cancelación
 */

$email_contacto = defined('GMAIL_FROM_EMAIL') ? GMAIL_FROM_EMAIL : 'info.sedaylino@gmail.com';

// === CONFIGURACIÓN COMÚN ===
if ($tipo === 'rechazado') {
    $titulo = "Pago Rechazado";
    $estado_badge = "PAGO RECHAZADO";
    $titulo_razon = "⚠️ ¿Por qué se rechazó tu pago?";
    $razon_texto_html = $motivo
        ? "<p style='color: #6B5D47; margin: 10px 0; line-height: 1.8;'><strong>Motivo:</strong> " . htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8') . "</p>"
        : "<p style='color: #6B5D47; margin: 10px 0; line-height: 1.8;'>Tu pago no pudo ser verificado. Por favor, contacta con nosotros para más detalles.</p>";
    $mensaje_inicial = "lamentamos informarte que el pago de tu pedido ha sido rechazado";
    
    $razon_titulo_text = "MOTIVO DEL RECHAZO";
    $razon_default_text = "Tu pago no pudo ser verificado. Por favor, contacta con nosotros para más detalles.";
} else {
    $titulo = "Pedido Cancelado";
    $estado_badge = "PEDIDO CANCELADO";
    $titulo_razon = "⚠️ ¿Por qué se canceló tu pedido?";
    $razon_texto_html = $motivo
        ? "<p style='color: #6B5D47; margin: 10px 0; line-height: 1.8;'><strong>Motivo:</strong> " . htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8') . "</p>"
        : "<p style='color: #6B5D47; margin: 10px 0; line-height: 1.8;'>Tu pedido fue cancelado automáticamente porque <strong>han transcurrido más de 24 horas sin recibir confirmación del pago</strong>.</p>";
    $mensaje_inicial = "lamentamos informarte que tu pedido ha sido cancelado";
    
    $razon_titulo_text = "MOTIVO DE LA CANCELACIÓN";
    $razon_default_text = "Tu pedido fue cancelado automáticamente porque han transcurrido más de 24 horas sin recibir confirmación del pago.";
}

// === VERSIÓN HTML ===
$html = "
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>$titulo</title>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #F5E6D3;'>
    
    <!-- Header -->
    <div style='background: #B8A082; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
        <h1 style='margin: 0; font-size: 28px; font-weight: 700;'>SEDA Y LINO</h1>
        <p style='margin: 10px 0 0 0; font-size: 14px; opacity: 0.95;'>Elegancia atemporal en cada prenda</p>
    </div>
    
    <!-- Mensaje Principal -->
    <div style='background: white; padding: 40px 30px; text-align: center;'>
        <div style='background: #E8DDD0; display: inline-block; padding: 20px 30px; border-radius: 50px; margin-bottom: 20px;'>
            <span style='color: #8B7355; font-size: 48px;'>⚠️</span>
        </div>
        <h2 style='color: #8B7355; margin: 20px 0 15px 0; font-size: 24px; font-weight: 700;'>$titulo</h2>
        
        <div style='background: #dc3545; color: white; display: inline-block; padding: 5px 15px; border-radius: 15px; font-size: 12px; font-weight: 700; margin-bottom: 15px;'>$estado_badge</div>
        
        <p style='color: #6B5D47; margin: 0 0 15px 0; font-size: 16px; line-height: 1.8;'>
            Hola <strong>" . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . "</strong>, $mensaje_inicial <strong>#$id_pedido_formateado</strong>.
        </p>
        
        <div style='background: #FFF3F3; border-left: 4px solid #dc3545; padding: 15px; text-align: left; margin-top: 20px; border-radius: 4px;'>
            <h4 style='color: #dc3545; margin: 0 0 10px 0; font-size: 16px;'>$titulo_razon</h4>
            $razon_texto_html
        </div>
        
        <div style='margin-top: 30px;'>
            <p style='color: #6B5D47; font-size: 15px;'>Si crees que esto es un error o necesitas ayuda, por favor contáctanos.</p>
            <a href='mailto:$email_contacto' style='background: #B8A082; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: 600; display: inline-block;'>Contactar Soporte</a>
        </div>
    </div>
    
    <!-- Footer -->
    <div style='background: #8B7355; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; margin-top: 20px;'>
        <p style='margin: 0; font-size: 14px;'>Seda y Lino</p>
        <p style='margin: 10px 0 0 0; font-size: 12px; opacity: 0.9;'>© 2025 Todos los derechos reservados.</p>
    </div>
    
</body>
</html>
";

// === VERSIÓN TEXTO PLANO ===
$motivo_text = ($motivo ? $motivo : $razon_default_text);

$text = "========================================
SEDA Y LINO - $titulo
========================================

¡Hola $nombre!

" . ucfirst($mensaje_inicial) . " #$id_pedido_formateado.

$razon_titulo_text
------------------------
$motivo_text


Si crees que esto es un error, por favor contáctanos: $email_contacto

Sentimos las molestias ocasionadas.
© 2025 Seda y Lino
";

return [
    'html' => $html,
    'text' => $text
];
?>
