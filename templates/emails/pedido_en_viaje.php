<?php
/**
 * Template unificado para email de pedido en viaje
 * 
 * Variables disponibles:
 * @var string $id_pedido_formateado ID del pedido formateado
 * @var string $nombre Nombre del usuario
 * @var string|null $codigo_seguimiento Código de seguimiento
 * @var string|null $empresa_envio Empresa de envío
 */

// === PREPARAR DATOS ===
$tracking_info_html = "";
$empresa = $empresa_envio ? $empresa_envio : "Correo";
$empresa_html = htmlspecialchars($empresa, ENT_QUOTES, 'UTF-8');
$codigo = $codigo_seguimiento ? htmlspecialchars($codigo_seguimiento, ENT_QUOTES, 'UTF-8') : null;

if ($codigo_seguimiento) {
    $tracking_info_html = "
    <div style='background: white; border: 1px solid #D4C4A8; border-left: 4px solid #B8A082; border-radius: 8px; padding: 20px; margin-top: 20px; text-align: left;'>
        <h4 style='color: #8B7355; margin-top: 0; margin-bottom: 15px; font-size: 16px; border-bottom: 1px solid #E8DDD0; padding-bottom: 5px;'>📦 Información de Seguimiento</h4>
        
        <div style='margin-bottom: 10px;'>
            <strong style='color: #6B5D47;'>Empresa:</strong> 
            <span style='color: #333;'>$empresa_html</span>
        </div>
        
        <div>
            <strong style='color: #6B5D47;'>Código de seguimiento:</strong><br>
            <div style='background: #F5E6D3; padding: 10px; margin-top: 5px; border-radius: 4px; font-family: monospace; font-size: 16px; color: #333; letter-spacing: 1px;'>$codigo</div>
        </div>
    </div>";
}

// === VERSIÓN HTML ===
$html = "
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Pedido Enviado</title>
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
            <span style='color: #8B7355; font-size: 48px;'>🚚</span>
        </div>
        <h2 style='color: #8B7355; margin: 20px 0 15px 0; font-size: 24px; font-weight: 700;'>¡Tu pedido está en camino!</h2>
        
        <div style='background: #28a745; color: white; display: inline-block; padding: 5px 15px; border-radius: 15px; font-size: 12px; font-weight: 700; margin-bottom: 15px;'>EN VIAJE</div>
        
        <p style='color: #6B5D47; margin: 0 0 15px 0; font-size: 16px; line-height: 1.8;'>
            Hola <strong>" . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . "</strong>, buenas noticias. Hemos despachado tu pedido <strong>#$id_pedido_formateado</strong>.
        </p>
        
        <p style='color: #6B5D47; margin: 0; font-size: 16px; line-height: 1.8;'>
            Pronto podrás disfrutar de tu compra. Si tienes alguna duda sobre el envío, estamos aquí para ayudarte.
        </p>
        
        $tracking_info_html
        
        <div style='margin-top: 30px;'>
            <a href='" . BASE_URL . "perfil.php?tab=pedidos' style='background: #B8A082; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: 600; display: inline-block;'>Ver Mis Pedidos</a>
        </div>
    </div>
    
    <!-- Footer -->
    <div style='background: #8B7355; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; margin-top: 20px;'>
        <p style='margin: 0; font-size: 14px;'>Gracias por elegir <strong>Seda y Lino</strong></p>
        <p style='margin: 10px 0 0 0; font-size: 12px; opacity: 0.9;'>© 2025 Seda y Lino. Todos los derechos reservados.</p>
    </div>
    
</body>
</html>
";

// === VERSIÓN TEXTO PLANO ===
$text = "========================================
SEDA Y LINO - ¡Tu pedido está en camino!
========================================

¡Hola $nombre!

Buenas noticias. Hemos despachado tu pedido #$id_pedido_formateado.
";

if ($codigo_seguimiento) {
    $text .= "
INFORMACIÓN DE SEGUIMIENTO
--------------------------
Empresa: $empresa
Código: $codigo_seguimiento
";
}

$text .= "
Puedes ver el estado de tus pedidos ingresando a tu cuenta: " . BASE_URL . "perfil.php?tab=pedidos

Gracias por elegir Seda y Lino.
";

return [
    'html' => $html,
    'text' => $text
];
?>
