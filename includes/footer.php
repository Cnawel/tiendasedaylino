<?php
/**
 * ========================================================================
 * FOOTER REUTILIZABLE - Tienda Seda y Lino
 * ========================================================================
 * Renderiza el pie de página común del sitio, con enlaces y scripts base.
 * Pensado para ser incluido en todas las páginas públicas.
 * 
 * Funciones
 * - render_footer(): imprime el footer y scripts compartidos (Bootstrap, UX).
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

if (!function_exists('render_footer')) {
    /**
     * Imprime el footer global del sitio con scripts compartidos.
     * - Incluye enlaces de navegación, redes y legales.
     * - Carga Bootstrap JS y mejoras de UX.
     *
     * @return void
     */
    function render_footer() {
        ?>
        <footer class="footer-completo">
            <div class="container">
                <div class="row py-5">
                    <!-- Columna 1: Sobre Nosotros -->
                    <div class="col-lg-3 col-md-6 mb-4">
                        <h5 class="footer-titulo mb-3">SEDA Y LINO</h5>
                        <p class="footer-texto">Elegancia que viste tus momentos. Prendas únicas de seda y lino con calidad artesanal.</p>
                        <div class="footer-redes mt-3">
                            <a href="https://www.instagram.com/sedaylino.oficial" target="_blank" rel="noopener noreferrer" class="footer-red-social" title="Instagram">
                                <i class="fab fa-instagram"></i>
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
                                <a href="mailto:info.sedaylino@gmail.com" class="footer-link">info.sedaylino@gmail.com</a>
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
                            <i class="fas fa-copyright me-1"></i> <?php echo date('Y'); ?> Seda y Lino. Todos los derechos reservados
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
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Funciones JavaScript Comunes (debe incluirse antes de otros scripts específicos) -->
        <!-- Proporciona: togglePassword, validateEmail, validateEmailInput, validarCodigoPostal, etc. -->
        <?php include __DIR__ . '/common_js_functions.php'; ?>
        <!-- UX Mejoras Simples (usa funciones de common_js_functions.php si es necesario) -->
        <script src="includes/ux-mejoras.js"></script>
        <!-- 
        NOTA: Otros scripts específicos de página deben incluirse después de este punto:
        - login.js, register.js, perfil.js: Dependen de common_js_functions.php
        - detalle_producto_js.php: Depende de detalle_producto_data.php y common_js_functions.php
        - detalle_producto_image_navigation.js: Depende de detalle_producto_js.php (función cambiarImagenPrincipal)
        - admin_validation.js: Depende de common_js_functions.php
        - checkout.js: Depende de common_js_functions.php
        - formulario-contacto.js: Depende de common_js_functions.php (validateEmail)
        -->
        <?php
        // Cargar scripts específicos de página según el archivo actual
        $current_page = basename($_SERVER['PHP_SELF']);
        if ($current_page === 'perfil.php') {
            echo '<script src="includes/perfil.js"></script>' . "\n";
        }
        ?>
        <?php
    }
}


