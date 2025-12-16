<?php
/**
 * Template unificado para email de bienvenida
 * 
 * Variables disponibles:
 * @var string $nombre Nombre del usuario
 * @var string $apellido Apellido del usuario
 * @var string $email Email del usuario
 * @var string $nombre_completo Nombre completo (concatenado)
 */

if (!isset($nombre_completo)) {
    $nombre_completo = $nombre . ' ' . $apellido;
}

// === VERSIÓN HTML ===
$html = "
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Bienvenido a Seda y Lino</title>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #F5E6D3;'>
    
    <!-- Header -->
    <div style='background: #B8A082; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
        <h1 style='margin: 0; font-size: 28px; font-weight: 700;'>SEDA Y LINO</h1>
        <p style='margin: 10px 0 0 0; font-size: 14px; opacity: 0.95;'>Elegancia atemporal en cada prenda</p>
    </div>
    
    <!-- Mensaje de bienvenida -->
    <div style='background: white; padding: 40px 30px; text-align: center;'>
        <div style='background: #E8DDD0; display: inline-block; padding: 20px 40px; border-radius: 50px; margin-bottom: 20px;'>
            <span style='color: #8B7355; font-size: 48px;'>👋</span>
        </div>
        <h2 style='color: #8B7355; margin: 20px 0 15px 0; font-size: 24px; font-weight: 700;'>¡Bienvenido, " . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . "!</h2>
        <p style='color: #6B5D47; margin: 0 0 20px 0; font-size: 16px; line-height: 1.8;'>
            Estamos muy contentos de tenerte en nuestra comunidad. En <strong>Seda y Lino</strong> encontrarás prendas elegantes y de calidad que reflejan tu estilo único.
        </p>
    </div>
    
    <!-- Información de la cuenta -->
    <div style='background: white; padding: 30px; border-left: 4px solid #B8A082; margin-top: 20px;'>
        <h3 style='color: #8B7355; margin-top: 0; border-bottom: 2px solid #E8DDD0; padding-bottom: 10px; font-size: 18px;'>
            📋 Información de tu cuenta
        </h3>
        
        <table style='width: 100%; margin-top: 20px;'>
            <tr>
                <td style='padding: 10px 0; color: #6B5D47;'><strong>Nombre completo:</strong></td>
                <td style='padding: 10px 0; text-align: right; color: #8B7355; font-weight: 600;'>" . htmlspecialchars($nombre_completo, ENT_QUOTES, 'UTF-8') . "</td>
            </tr>
            <tr>
                <td style='padding: 10px 0; color: #6B5D47;'><strong>Email registrado:</strong></td>
                <td style='padding: 10px 0; text-align: right; color: #8B7355; font-weight: 600;'>" . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</td>
            </tr>
        </table>
    </div>
    
    <!-- Enlaces útiles -->
    <div style='background: #E8DDD0; padding: 30px; border-radius: 5px; margin-top: 20px;'>
        <h3 style='color: #8B7355; margin-top: 0; font-size: 18px;'>🚀 Comienza a explorar</h3>
        <p style='color: #6B5D47; margin-bottom: 20px;'>Ahora que tu cuenta está lista, puedes:</p>
        <ul style='color: #6B5D47; padding-left: 20px; line-height: 2;'>
            <li style='margin-bottom: 10px;'><strong>Explorar nuestro catálogo</strong> de prendas elegantes de seda y lino</li>
            <li style='margin-bottom: 10px;'><strong>Iniciar sesión</strong> para acceder a tu cuenta y realizar compras</li>
            <li style='margin-bottom: 10px;'><strong>Guardar tus productos favoritos</strong> para comprarlos más tarde</li>
            <li><strong>Disfrutar de envíos gratuitos</strong> en compras elegibles</li>
        </ul>
        
        <div style='text-align: center; margin-top: 30px;'>
            <a href='" . BASE_URL . "catalogo.php' style='background: #B8A082; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: 600; margin-right: 10px;'>Ver Catálogo</a>
            <a href='" . BASE_URL . "login.php' style='background: white; color: #8B7355; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: 600; border: 2px solid #B8A082;'>Iniciar Sesión</a>
        </div>
    </div>
    
    <!-- Información de contacto -->
    <div style='background: white; padding: 20px; text-align: center; margin-top: 20px; border-top: 3px solid #B8A082;'>
        <p style='color: #6B5D47; margin: 10px 0;'>¿Tienes alguna pregunta?</p>
        <p style='margin: 10px 0;'>
            <a href='mailto:" . (defined('GMAIL_FROM_EMAIL') ? GMAIL_FROM_EMAIL : 'info@sedaylino.com') . "' style='color: #8B7355; text-decoration: none; font-weight: 600;'>" . (defined('GMAIL_FROM_EMAIL') ? GMAIL_FROM_EMAIL : 'info@sedaylino.com') . "</a>
        </p>
    </div>
    
    <!-- Footer -->
    <div style='background: #8B7355; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; margin-top: 20px;'>
        <p style='margin: 0; font-size: 14px;'>Gracias por unirte a <strong>Seda y Lino</strong></p>
        <p style='margin: 10px 0 0 0; font-size: 12px; opacity: 0.9;'>© 2025 Seda y Lino. Todos los derechos reservados.</p>
    </div>
    
</body>
</html>
";

// === VERSIÓN TEXTO PLANO ===
$text = "========================================
SEDA Y LINO - Bienvenido
========================================

¡Hola $nombre!

Estamos muy contentos de tenerte en nuestra comunidad. En Seda y Lino encontrarás prendas elegantes y de calidad que reflejan tu estilo único.

INFORMACIÓN DE TU CUENTA
-----------------------
Nombre completo: $nombre_completo
Email registrado: $email


COMENZA A EXPLORAR
------------------
- Explora nuestro catálogo de prendas elegantes de seda y lino
- Inicia sesión para acceder a tu cuenta y realizar compras
- Guarda tus productos favoritos para comprarlos más tarde
- Disfruta de envíos gratuitos en compras elegibles

¿Dudas? Contáctanos: " . (defined('GMAIL_FROM_EMAIL') ? GMAIL_FROM_EMAIL : 'info@sedaylino.com') . "

Gracias por unirte a Seda y Lino
© 2025 Seda y Lino
";

return [
    'html' => $html,
    'text' => $text
];
?>
