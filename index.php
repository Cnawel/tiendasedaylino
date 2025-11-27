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

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir header completo (head + navigation)
include 'includes/header.php';
?>

    <main class="main-content-spaced">
        <!-- Carrusel de imágenes destacadas -->
        <section class="carousel-section-compact section-spaced">
            <div id="carouselExampleIndicators" class="carousel slide carousel-hero" data-bs-ride="carousel" data-bs-interval="4000">
                <div class="carousel-indicators">
                   <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                   <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1" aria-label="Slide 2"></button>
                   <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2" aria-label="Slide 3"></button>
                </div>
                <div class="carousel-inner">
                    <div class="carousel-item active">
                      <a href="catalogo.php" class="d-block">
                        <img src="imagenes/productos/camisas/camisa_grupal.png" class="d-block w-100" alt="Colección de camisas elegantes de seda y lino" loading="lazy">
                      </a>
                    </div>
                    <div class="carousel-item">
                      <a href="catalogo.php" class="d-block">
                        <img src="imagenes/productos/pantalones/pantalon_grupal.png" class="d-block w-100" alt="Colección de pantalones de lino premium" loading="lazy">
                      </a>
                    </div>
                    <div class="carousel-item">
                      <a href="catalogo.php" class="d-block">
                        <img src="imagenes/productos/blusas/blusa_mujer_modelogrupal_oficina.png" class="d-block w-100" alt="Blusas elegantes de seda y lino" loading="lazy">
                      </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sección de presentación -->
        <section class="seccion-presentacion section-spaced">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-10 col-md-12">
                        <div class="texto-presentacion text-center">
                            <p class="presentacion-texto">Seda y Lino. Elegancia atemporal en cada prenda</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sección productos - Compacta y centrada en imágenes -->
        <section id="productos" class="seccion-productos-compact section-spaced">
            <div class="container-fluid">
                <div class="row g-4">
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <a href="catalogo.php?categoria=Camisas" class="text-decoration-none">
                            <div class="card tarjeta h-100 shadow-sm tarjeta-compact">
                                <div class="card-header-compact">
                                    <h5 class="card-title titulo-tarjeta mb-0">Camisas</h5>
                                </div>
                                <div class="card-img-wrapper card-img-wrapper-compact">
                                    <img src="imagenes/productos/camisas/camisa_grupal.png" class="card-img-top" alt="Camisas elegantes de seda y lino" loading="lazy">
                                    <div class="card-overlay">
                                        <span class="overlay-text">Explorar</span>
                                    </div>
                                    <!-- Indicador visual sutil -->
                                    <div class="card-indicator"></div>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <a href="catalogo.php?categoria=Blusas" class="text-decoration-none">
                            <div class="card tarjeta h-100 shadow-sm tarjeta-compact">
                                <div class="card-header-compact">
                                    <h5 class="card-title titulo-tarjeta mb-0">Blusas</h5>
                                </div>
                                <div class="card-img-wrapper card-img-wrapper-compact">
                                    <img src="imagenes/productos/blusas/blusa_mujer_modelogrupal_oficina.png" class="card-img-top" alt="Blusas femeninas de calidad" loading="lazy">
                                    <div class="card-overlay">
                                        <span class="overlay-text">Explorar</span>
                                    </div>
                                    <!-- Indicador visual sutil -->
                                    <div class="card-indicator"></div>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <a href="catalogo.php?categoria=Shorts" class="text-decoration-none">
                            <div class="card tarjeta h-100 shadow-sm tarjeta-compact">
                                <div class="card-header-compact">
                                    <h5 class="card-title titulo-tarjeta mb-0">Shorts</h5>
                                </div>
                                <div class="card-img-wrapper card-img-wrapper-compact">
                                    <img src="imagenes/productos/shorts/pantalon_mujer_grupal.png" class="card-img-top" alt="Shorts cómodos y elegantes" loading="lazy">
                                    <div class="card-overlay">
                                        <span class="overlay-text">Explorar</span>
                                    </div>
                                    <!-- Indicador visual sutil -->
                                    <div class="card-indicator"></div>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <a href="catalogo.php?categoria=Pantalones" class="text-decoration-none">
                            <div class="card tarjeta h-100 shadow-sm tarjeta-compact">
                                <div class="card-header-compact">
                                    <h5 class="card-title titulo-tarjeta mb-0">Pantalones</h5>
                                </div>
                                <div class="card-img-wrapper card-img-wrapper-compact">
                                    <img src="imagenes/productos/pantalones/pantalon_grupal.png" class="card-img-top" alt="Pantalón de lino" loading="lazy">
                                    <div class="card-overlay">
                                        <span class="overlay-text">Explorar</span>
                                    </div>
                                    <!-- Indicador visual sutil -->
                                    <div class="card-indicator"></div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <?php 
        // Mostrar mensaje de contacto si existe
        $mostrar_reset_formulario = false;
        $limpiar_params_get = false;
        
        if (isset($_SESSION['mensaje_contacto'])) {
            $mensaje_tipo = isset($_SESSION['mensaje_contacto_tipo']) ? $_SESSION['mensaje_contacto_tipo'] : 'info';
            $es_exitoso = ($mensaje_tipo === 'success');
            
            echo '<div class="container mt-4">';
            echo '<div class="alert alert-' . htmlspecialchars($mensaje_tipo) . ' alert-dismissible fade show" role="alert">';
            echo '<i class="fas fa-info-circle me-2"></i>';
            echo htmlspecialchars($_SESSION['mensaje_contacto']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            echo '</div>';
            echo '</div>';
            
            // Si el mensaje es de éxito, marcar para resetear el formulario y limpiar GET
            if ($es_exitoso) {
                $mostrar_reset_formulario = true;
                $limpiar_params_get = true;
            }
            
            // Limpiar mensaje de sesión inmediatamente después de mostrarlo
            unset($_SESSION['mensaje_contacto']);
            unset($_SESSION['mensaje_contacto_tipo']);
            
            // Si fue exitoso, limpiar también el flag de envío después de mostrar el mensaje
            if ($es_exitoso && isset($_SESSION['mensaje_contacto_enviado'])) {
                unset($_SESSION['mensaje_contacto_enviado']);
            }
        }
        
        // Limpiar parámetros GET relacionados con el formulario después de envío exitoso
        // Esto evita que el formulario se pre-llene después de un envío exitoso
        if ($limpiar_params_get && (isset($_GET['asunto']) || isset($_GET['pedido']))) {
            // Construir URL sin parámetros de formulario
            $url_sin_params = strtok($_SERVER['REQUEST_URI'], '?');
            if ($url_sin_params !== $_SERVER['REQUEST_URI']) {
                // Redirigir a la misma página sin parámetros GET del formulario
                header('Location: ' . $url_sin_params . '#contacto', true, 302);
                exit;
            }
        }
        ?>

        <?php include 'includes/formulario_contacto.php'; ?>
        
        <?php if ($mostrar_reset_formulario): ?>
        <script>
        // Variable global para indicar que se debe resetear el formulario
        window.resetearFormulario = true;
        </script>
        <script src="js/index.js"></script>
        <?php endif; ?>
    </main>

<?php include 'includes/footer.php'; render_footer(); ?>