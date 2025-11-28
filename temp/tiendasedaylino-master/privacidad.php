<?php
/**
 * ========================================================================
 * PÁGINA POLÍTICA DE PRIVACIDAD - Tienda Seda y Lino
 * ========================================================================
 * Página informativa sobre la política de privacidad de la tienda
 * - Informa sobre la recopilación y uso de datos personales
 * - Detalla los derechos del usuario sobre sus datos
 * - Proporciona información de contacto para consultas
 * 
 * Funciones principales:
 * - Página estática que muestra información legal
 * - No requiere procesamiento de datos
 * 
 * Variables principales:
 * - $titulo_pagina: Título de la página
 * 
 * Tablas utilizadas: Ninguna (contenido estático)
 * ========================================================================
 */
session_start();

// Configurar título de la página
$titulo_pagina = 'Política de Privacidad';
?>

<?php include 'includes/header.php'; ?>

<!-- Contenido de privacidad -->
<main>
    <section class="secciones">
        <div class="container">
            <h1 class="titulo-productos text-center mb-5">POLÍTICA DE PRIVACIDAD</h1>
            
            <div class="row justify-content-center">
                <div class="col-lg-10 col-md-12">
                    <p class="text-muted mb-4"><strong>Última actualización:</strong> Octubre 2025</p>
                    
                    <h3>1. Información que Recopilamos</h3>
                    <p class="mb-4">En <span>Seda y Lino</span>, recopilamos información que usted nos proporciona directamente cuando:</p>
                    <ul class="mb-4">
                        <li>Crea una cuenta en nuestro sitio web</li>
                        <li>Realiza una compra</li>
                        <li>Se suscribe a nuestro boletín</li>
                        <li>Se comunica con nosotros</li>
                    </ul>
                    
                    <h3>2. Uso de la Información</h3>
                    <p class="mb-4">Utilizamos la información recopilada para:</p>
                    <ul class="mb-4">
                        <li>Procesar sus pedidos y gestionar su cuenta</li>
                        <li>Comunicarnos con usted sobre sus compras</li>
                        <li>Mejorar nuestros productos y servicios</li>
                        <li>Enviarle información promocional (si ha dado su consentimiento)</li>
                        <li>Cumplir con nuestras obligaciones legales</li>
                    </ul>
                    
                    <h3>3. Protección de Datos</h3>
                    <p class="mb-4">Implementamos medidas de seguridad apropiadas para proteger su información personal contra acceso no autorizado, alteración, divulgación o destrucción.</p>
                    
                    <h3>4. Compartir Información</h3>
                    <p class="mb-4">No vendemos, intercambiamos ni transferimos su información personal a terceros sin su consentimiento, excepto cuando sea necesario para:</p>
                    <ul class="mb-4">
                        <li>Procesar pagos</li>
                        <li>Enviar productos</li>
                        <li>Cumplir con la ley</li>
                    </ul>
                    
                    <h3>5. Cookies</h3>
                    <p class="mb-4">Utilizamos cookies para mejorar su experiencia de navegación. Puede configurar su navegador para rechazar cookies, aunque esto podría afectar algunas funcionalidades del sitio.</p>
                    
                    <h3>6. Sus Derechos</h3>
                    <p class="mb-4">Usted tiene derecho a:</p>
                    <ul class="mb-4">
                        <li>Acceder a sus datos personales</li>
                        <li>Rectificar datos incorrectos</li>
                        <li>Solicitar la eliminación de sus datos</li>
                        <li>Oponerse al procesamiento de sus datos</li>
                        <li>Solicitar la portabilidad de sus datos</li>
                    </ul>
                    
                    <h3>7. Cambios en la Política</h3>
                    <p class="mb-4">Nos reservamos el derecho de actualizar esta política de privacidad en cualquier momento. Los cambios serán efectivos inmediatamente después de su publicación en esta página.</p>
                    
                    <h3>8. Contacto</h3>
                    <p class="mb-4">Si tiene preguntas sobre esta Política de Privacidad, puede contactarnos:</p>
                    <ul class="mb-4">
                        <li><strong>Email:</strong> info.sedaylino@gmail.com</li>
                    </ul>
                    
                    <div class="text-center mt-5">
                        <a href="index.php" class="btn btn-perfil-home">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Inicio
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; render_footer(); ?>

