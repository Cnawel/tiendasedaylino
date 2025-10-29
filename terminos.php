<?php 
session_start();

// Configurar título de la página
$titulo_pagina = 'Términos y Condiciones';
?>

<?php include 'includes/header.php'; ?>

<!-- Contenido de términos -->
<div style="display:none">
    <header>
        <nav class="navbar navbar-expand-lg bg-body-tertiary">
            <div class="container-fluid">
                <a class="navbar-brand nombre-tienda" href="index.php">SEDA Y LINO</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                    <ul class="navbar-nav lista-nav">
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.php">INICIO</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="nosotros.php">NOSOTROS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.php#productos">PRODUCTOS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.php#contacto">CONTACTO</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="carrito.php" title="Carrito">
                                <i class="fas fa-shopping-cart fa-lg"></i>
                                <?php 
                                $num_items_carrito = isset($_SESSION['carrito']) ? count($_SESSION['carrito']) : 0;
                                if ($num_items_carrito > 0): 
                                ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $num_items_carrito; ?>
                                </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <?php if (isset($_SESSION['id_usuario'])): ?>
                                <a class="nav-link" href="perfil.php" title="Mi Perfil">
                                    <img src="iconos/avatar-usuario.png" alt="icono de avatar de usuario">
                                </a>
                            <?php else: ?>
                                <a class="nav-link" href="login.php" title="Iniciar Sesión">
                                    <img src="iconos/avatar-usuario.png" alt="icono de avatar de usuario">
                                </a>
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="terminos-page">
        <div class="container">
            <div class="terminos-container">
                <h1>Términos y Condiciones de Compra</h1>
                <p>Al registrarte o realizar una compra en <strong>Seda y Lino</strong>, aceptas estos términos y condiciones que rigen el uso de nuestra tienda online:</p>
                
                <h4>1. Proceso de Compra y Pagos</h4>
                <ul>
                    <li>Todos los productos, precios y promociones están sujetos a disponibilidad y pueden variar sin previo aviso.</li>
                    <li>La compra se considerará realizada cuando recibas una confirmación por email y el pago haya sido validado.</li>
                    <li>Los métodos de pago aceptados se detallan en la sección de checkout. No almacenamos datos de tu tarjeta en nuestro sitio.</li>
                </ul>
                
                <h4>2. Envíos y Entregas</h4>
                <ul>
                    <li>Los plazos de entrega varían según destino y disponibilidad. Sabrás el plazo estimado antes de finalizar tu compra.</li>
                    <li>Seda y Lino se compromete a despachar todos los pedidos en el menor tiempo posible.</li>
                </ul>
                
                <h4>3. Devoluciones y Garantía</h4>
                <ul>
                    <li>Podrás devolver tu compra dentro de los 30 días de recibida, siempre que los productos no hayan sido usados y conserven etiquetas originales.</li>
                    <li>Si recibes un producto defectuoso, lo cambiaremos o te devolveremos el importe íntegro si nos avisas en un plazo razonable.</li>
                    <li>Para cualquier reclamo o consulta sobre garantías, puedes contactarnos a través del formulario de contacto de la tienda.</li>
                </ul>
                
                <h4>4. Protección de Datos</h4>
                <ul>
                    <li>La información personal que nos facilitas se utilizará solo para procesar tu pedido y ofrecerte una mejor experiencia de compra.</li>
                    <li>Respetamos la confidencialidad de tus datos y no los cederemos a terceros sin tu consentimiento, salvo obligaciones legales.</li>
                </ul>
                
                <h4>5. Jurisdicción y Legislación</h4>
                <ul>
                    <li>Las operaciones realizadas en esta tienda se someten a la legislación vigente en el país donde opera Seda y Lino.</li>
                    <li>Cualquier controversia se someterá a los tribunales competentes correspondientes.</li>
                </ul>
                
                <p class="mt-5"><small>Última actualización: Octubre 2025</small></p>
                
                <div class="text-center mt-4">
                    <a href="index.php" class="btn btn-perfil-home">Volver al Inicio</a>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer-completo">
        <div class="container">
            <div class="row py-5">
                <!-- Columna 1: Sobre Nosotros -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">SEDA Y LINO</h5>
                    <p class="footer-texto">Elegancia que viste tus momentos. Prendas únicas de seda y lino con calidad artesanal.</p>
                    <div class="footer-redes mt-3">
                        <a href="https://www.facebook.com/?locale=es_LA" target="_blank" rel="noopener noreferrer" class="footer-red-social me-2" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" class="footer-red-social me-2" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://x.com/?lang=es" target="_blank" rel="noopener noreferrer" class="footer-red-social" title="X (Twitter)">
                            <i class="fab fa-x-twitter"></i>
                        </a>
                    </div>
                </div>

                <!-- Columna 2: Navegación -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Navegación</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-2">
                            <a href="index.php" class="footer-link">
                                <i class="fas fa-home me-2"></i>Inicio
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="nosotros.php" class="footer-link">
                                <i class="fas fa-users me-2"></i>Nosotros
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=todos" class="footer-link">
                                <i class="fas fa-shopping-bag me-2"></i>Catálogo
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="index.php#contacto" class="footer-link">
                                <i class="fas fa-envelope me-2"></i>Contacto
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Columna 3: Categorías -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Productos</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Camisas" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Camisas
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Blusas" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Blusas
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Pantalones" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Pantalones
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Shorts" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Shorts
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Columna 4: Información de Contacto -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Contacto</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-3">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            <span class="footer-texto-small">Buenos Aires, Argentina</span>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-phone me-2"></i>
                            <a href="tel:+541112345678" class="footer-link">+54 11 1234-5678</a>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-envelope me-2"></i>
                            <a href="mailto:info@sedaylino.com" class="footer-link">info@sedaylino.com</a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Línea divisoria -->
            <hr class="footer-divider">

            <!-- Footer inferior -->
            <div class="row py-3">
                <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                    <p class="footer-copyright mb-0">
                        <i class="fas fa-copyright me-1"></i> 2025 Seda y Lino. Todos los derechos reservados
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="terminos.php" class="footer-link-small me-3">Términos y Condiciones</a>
                    <a href="privacidad.php" class="footer-link-small">Política de Privacidad</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

<?php include 'includes/footer.php'; render_footer(); ?>
