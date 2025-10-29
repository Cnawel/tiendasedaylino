<?php
/**
 * ========================================================================
 * PÁGINA PRINCIPAL (HOME) - Tienda Seda y Lino
 * ========================================================================
 * Landing page del e-commerce de ropa de lino y seda
 * - Carrusel de imágenes destacadas
 * - Presentación de la tienda y valores
 * - Catálogo de productos destacados por categorías
 * - Formulario de contacto
 * 
 * Funciones principales:
 * - Muestra productos destacados de cada categoría
 * - Procesa formulario de contacto y envía emails
 * - Enlaces rápidos a catálogo y otras secciones
 * 
 * Variables principales:
 * - $titulo_pagina: Título de la página para el head
 * - Productos cargados desde BD según categoría
 * 
 * Tablas utilizadas: Productos, Categorias (solo lectura)
 * ========================================================================
 */

// Configurar título de la página
$titulo_pagina = 'Inicio';

// Incluir header completo (head + navigation)
include 'includes/header.php';
?>

    <main>
        <section>
            <div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000">
                <div class="carousel-indicators">
                   <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                   <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1" aria-label="Slide 2"></button>
                   <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2" aria-label="Slide 3"></button>
                </div>
                <div class="carousel-inner">
                    <div class="carousel-item active">
                      <img src="imagenes/productos/camisas/camisa_grupal.png" class="d-block w-100" alt="Colección de camisas elegantes de seda y lino">
                    </div>
                    <div class="carousel-item">
                      <img src="imagenes/productos/pantalones/pantalon_grupal.png" class="d-block w-100" alt="Colección de pantalones de lino premium">
                    </div>
                    <div class="carousel-item">
                      <img src="imagenes/productos/camisas/camisa_grupal.png" class="d-block w-100" alt="Camisas de calidad para todas las ocasiones">
                    </div>
                </div>
            </div>
        </section>

        <section id="nosotros" class="secciones">
            <p>En <span>Seda y Lino</span> creamos prendas únicas que celebran la elegancia y sofisticación. <a href="nosotros.php" class="link-nosotros">Conoce más sobre nosotros</a>.</p>
        </section>

        <section id="productos" class="secciones seccion-productos">
            <div class="container">
                <h2 class="text-center mb-4">NUESTROS PRODUCTOS</h2>
                <p class="text-center mb-5 descripcion-productos">Descubre nuestra colección de prendas elegantes confeccionadas con los mejores materiales</p>
                
                <div class="row justify-content-center">
                    <div class="col-lg-3 col-md-6 col-sm-12 mb-4">
                        <a href="catalogo.php?categoria=Camisas" class="text-decoration-none">
                            <div class="card tarjeta h-100 shadow-sm">
                                <div class="card-img-wrapper">
                                    <img src="imagenes/productos/camisas/camisa_grupal.png" class="card-img-top" alt="Camisas elegantes de seda y lino">
                                    <div class="card-overlay">
                                        <span class="overlay-text">Explorar Colección</span>
                                    </div>
                                </div>
                                <div class="card-body d-flex flex-column">
                                   <h5 class="card-title titulo-tarjeta">Camisas</h5>
                                   <p class="card-text texto-tarjeta">Camisas elegantes de seda y lino para todas las ocasiones</p>
                                   <span class="btn boton-tarjeta mt-auto">Ver Colección</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-lg-3 col-md-6 col-sm-12 mb-4">
                        <a href="catalogo.php?categoria=Blusas" class="text-decoration-none">
                            <div class="card tarjeta h-100 shadow-sm">
                                <div class="card-img-wrapper">
                                    <img src="imagenes/imagen.png" class="card-img-top" alt="Blusas femeninas de calidad">
                                    <div class="card-overlay">
                                        <span class="overlay-text">Explorar Colección</span>
                                    </div>
                                </div>
                                <div class="card-body d-flex flex-column">
                                  <h5 class="card-title titulo-tarjeta">Blusas</h5>
                                  <p class="card-text texto-tarjeta">Blusas elegantes de seda que realzan tu silueta con diseños únicos y refinados</p>
                                  <span class="btn boton-tarjeta mt-auto">Ver Colección</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-lg-3 col-md-6 col-sm-12 mb-4">
                        <a href="catalogo.php?categoria=Shorts" class="text-decoration-none">
                            <div class="card tarjeta h-100 shadow-sm">
                                <div class="card-img-wrapper">
                                    <img src="imagenes/imagen.png" class="card-img-top" alt="Shorts cómodos y elegantes">
                                    <div class="card-overlay">
                                        <span class="overlay-text">Explorar Colección</span>
                                    </div>
                                </div>
                                <div class="card-body d-flex flex-column">
                                   <h5 class="card-title titulo-tarjeta">Shorts</h5>
                                   <p class="card-text texto-tarjeta">Shorts de lino premium, ligeros y frescos, perfectos para días de verano con estilo</p>
                                   <span class="btn boton-tarjeta mt-auto">Ver Colección</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-lg-3 col-md-6 col-sm-12 mb-4">
                        <a href="catalogo.php?categoria=Pantalones" class="text-decoration-none">
                            <div class="card tarjeta h-100 shadow-sm">
                                <div class="card-img-wrapper">
                                    <img src="imagenes/productos/pantalones/pantalon_grupal.png" class="card-img-top" alt="Pantalón de lino">
                                    <div class="card-overlay">
                                        <span class="overlay-text">Explorar Colección</span>
                                    </div>
                                </div>
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title titulo-tarjeta">Pantalón de Lino</h5>
                                    <p class="card-text texto-tarjeta">Pantalón de lino premium, disponible en varios colores.</p>
                                    <span class="btn boton-tarjeta mt-auto">Ver Colección</span>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section id="contacto" class="secciones seccion-contacto">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8 col-md-10">
                        <div class="formulario-container">
                            <h2 class="text-center mb-4">CONTÁCTANOS</h2>
                            <p class="text-center mb-5">¿Tienes alguna pregunta sobre nuestros productos? ¿Necesitas asesoramiento personalizado? Estamos aquí para ayudarte.</p>
                            
                            <form class="formulario" novalidate>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                       <label for="name" class="form-label label-contacto">Nombre*</label>
                                       <input type="text" class="form-control input-contacto" id="name" placeholder="Tu nombre" required>
                                       <div class="invalid-feedback">Por favor, ingresa tu nombre.</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                       <label for="email" class="form-label label-contacto">Email*</label>
                                       <input type="email" class="form-control input-contacto" id="email" placeholder="tu@email.com" required>
                                       <div class="invalid-feedback">Por favor, ingresa un email válido.</div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                   <label for="asunto" class="form-label label-contacto">Asunto*</label>
                                   <select class="form-control input-contacto" id="asunto" required>
                                       <option value="">Selecciona un tema</option>
                                       <option value="consulta">Consulta general</option>
                                       <option value="pedido">Información sobre pedidos</option>
                                       <option value="tallas">Asesoramiento de tallas</option>
                                       <option value="otro">Otro</option>
                                   </select>
                                   <div class="invalid-feedback">Por favor, selecciona un asunto.</div>
                                </div>
                                <div class="mb-3">
                                  <label for="message" class="form-label label-contacto">Mensaje*</label>
                                  <textarea class="form-control input-contacto" id="message" rows="6" placeholder="Escribe tu mensaje aquí..." required></textarea>
                                  <div class="invalid-feedback">Por favor, escribe tu mensaje.</div>
                                </div>
                               <button type="submit" class="btn boton-formulario w-100">
                                   <span class="button-text">Enviar Mensaje</span>
                                   <span class="button-loading d-none">Enviando...</span>
                               </button>
                               <div class="form-text texto-contacto mt-3">*Todos los campos son obligatorios</div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
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

    <!-- Bootstrap 5.3.8 JS Bundle -->
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    
    <!-- Validación del formulario de contacto -->
    <script>
        /**
         * ====================================================================
         * VALIDACIÓN DE FORMULARIO DE CONTACTO
         * ====================================================================
         * Valida campos requeridos, formato de email y muestra feedback
         */
        (function() {
            'use strict';
            
            const contactForm = document.querySelector('.formulario');
            if (!contactForm) return;
            
            // Referencias a elementos del formulario
            const nameInput = contactForm.querySelector('#name');
            const emailInput = contactForm.querySelector('#email');
            const asuntoSelect = contactForm.querySelector('#asunto');
            const messageTextarea = contactForm.querySelector('#message');
            const submitBtn = contactForm.querySelector('button[type="submit"]');
            const btnText = submitBtn.querySelector('.button-text');
            const btnLoading = submitBtn.querySelector('.button-loading');
            
            /**
             * Valida formato de email usando expresión regular
             * @param {string} email - Email a validar
             * @returns {boolean} true si el formato es válido
             */
            function validateEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }
            
            // Validación en tiempo real del email
            emailInput.addEventListener('input', function() {
                if (this.value.trim() === '') {
                    this.classList.remove('is-valid', 'is-invalid');
                } else if (validateEmail(this.value)) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });
            
            /**
             * Manejo del envío del formulario
             * Valida todos los campos y simula envío
             */
            contactForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                let isValid = true;
                
                // Validate name
                if (!nameInput.value.trim()) {
                    nameInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    nameInput.classList.remove('is-invalid');
                    nameInput.classList.add('is-valid');
                }
                
                // Validate email
                if (!emailInput.value.trim() || !validateEmail(emailInput.value)) {
                    emailInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    emailInput.classList.remove('is-invalid');
                    emailInput.classList.add('is-valid');
                }
                
                // Validate asunto
                if (!asuntoSelect.value) {
                    asuntoSelect.classList.add('is-invalid');
                    isValid = false;
                } else {
                    asuntoSelect.classList.remove('is-invalid');
                    asuntoSelect.classList.add('is-valid');
                }
                
                // Validate message
                if (!messageTextarea.value.trim()) {
                    messageTextarea.classList.add('is-invalid');
                    isValid = false;
                } else {
                    messageTextarea.classList.remove('is-invalid');
                    messageTextarea.classList.add('is-valid');
                }
                
                if (!isValid) {
                    const firstError = contactForm.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstError.focus();
                    }
                    return;
                }
                
                // Show loading state
                btnText.classList.add('d-none');
                btnLoading.classList.remove('d-none');
                submitBtn.disabled = true;
                
                // Simulate form submission
                setTimeout(function() {
                    btnText.classList.remove('d-none');
                    btnLoading.classList.add('d-none');
                    submitBtn.disabled = false;
                    
                    // Show success message
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success alert-dismissible fade show mt-3';
                    successAlert.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i>
                        ¡Mensaje enviado con éxito! Nos pondremos en contacto contigo pronto.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    contactForm.insertAdjacentElement('afterend', successAlert);
                    
                    // Reset form
                    contactForm.reset();
                    contactForm.querySelectorAll('.is-valid').forEach(el => el.classList.remove('is-valid'));
                    
                    // Scroll to success message
                    successAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 1500);
            });
            
            // Clear errors on input
            [nameInput, emailInput, asuntoSelect, messageTextarea].forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                });
            });
        })();
    </script>

<?php include 'includes/footer.php'; render_footer(); ?>