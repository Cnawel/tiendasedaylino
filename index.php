<?php
/**
 * PÁGINA PRINCIPAL DE LA TIENDA
 * Reemplaza index.html con funcionalidad PHP
 */

require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- ============================================================================
         CONFIGURACIÓN HTML Y META TAGS
         ============================================================================ -->
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- ============================================================================
         HOJAS DE ESTILO EXTERNAS
         ============================================================================ -->
    
    <!-- Estilos personalizados de la tienda -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Bootstrap 5.3.8 - Framework CSS para diseño responsivo -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" 
          rel="stylesheet" 
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" 
          crossorigin="anonymous">
    
    <!-- Font Awesome 6.0.0 - Iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- ============================================================================
         TÍTULO DE LA PÁGINA
         ============================================================================ -->
    
    <title>Seda y Lino - Inicio</title>
</head>
<body>
    
    <!-- ============================================================================
         HEADER Y NAVEGACIÓN PRINCIPAL
         ============================================================================ -->
    
    <?php include 'includes/header.php'; ?>

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
                        <img src="imagenes/imagen.png" class="d-block w-100" alt="Imagen 1">
                    </div>
                    <div class="carousel-item">
                        <img src="imagenes/imagen.png" class="d-block w-100" alt="Imagen 2">
                    </div>
                    <div class="carousel-item">
                        <img src="imagenes/imagen.png" class="d-block w-100" alt="Imagen 3">
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        </section>

        <!-- ============================================================================
             SECCIÓN DE PRODUCTOS DESTACADOS
             ============================================================================ -->
        
        <section id="productos" class="py-5">
            <div class="container">
                <h2 class="text-center mb-5">Nuestros Productos</h2>
                <div class="row">
                    <div class="col-md-3 mb-4">
                        <div class="card h-100">
                            <img src="imagenes/productos/camisas/camisa_mujer_lino_modelo.png" class="card-img-top" alt="Camisas">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">Camisas</h5>
                                <p class="card-text">Elegantes camisas de lino para hombre y mujer</p>
                                <div class="mt-auto">
                                    <a href="camisas.php" class="btn btn-primary">Ver Colección</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card h-100">
                            <img src="imagenes/productos/blusas/blusa_mujer_beige.png" class="card-img-top" alt="Blusas">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">Blusas</h5>
                                <p class="card-text">Delicadas blusas de seda para ocasiones especiales</p>
                                <div class="mt-auto">
                                    <a href="blusas.php" class="btn btn-primary">Ver Colección</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card h-100">
                            <img src="imagenes/productos/pantalones/pantalon_hombre_lino_azul.png" class="card-img-top" alt="Pantalones">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">Pantalones</h5>
                                <p class="card-text">Cómodos pantalones de lino para el día a día</p>
                                <div class="mt-auto">
                                    <a href="pantalones.php" class="btn btn-primary">Ver Colección</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card h-100">
                            <img src="imagenes/productos/pantalones/pantalon_lino_hombre_modelo_gris.png" class="card-img-top" alt="Shorts">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">Shorts</h5>
                                <p class="card-text">Frescos shorts de lino para el verano</p>
                                <div class="mt-auto">
                                    <a href="shorts.php" class="btn btn-primary">Ver Colección</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================================
             SECCIÓN DE CONTACTO
             ============================================================================ -->
        
        <section id="contacto" class="py-5 bg-light">
            <div class="container">
                <h2 class="text-center mb-5">Contacto</h2>
                <div class="row">
                    <div class="col-md-6">
                        <h4>Información de Contacto</h4>
                        <p><strong>Dirección:</strong> Av. Principal 123, Ciudad</p>
                        <p><strong>Teléfono:</strong> +54 11 1234-5678</p>
                        <p><strong>Email:</strong> info@sedaylino.com</p>
                        <p><strong>Horarios:</strong> Lunes a Viernes 9:00 - 18:00</p>
                    </div>
                    <div class="col-md-6">
                        <h4>Redes Sociales</h4>
                        <div class="d-flex gap-3">
                            <a href="https://www.facebook.com/?locale=es_LA" target="_blank" class="btn btn-outline-primary">
                                <i class="fab fa-facebook-f"></i> Facebook
                            </a>
                            <a href="https://www.instagram.com/" target="_blank" class="btn btn-outline-danger">
                                <i class="fab fa-instagram"></i> Instagram
                            </a>
                            <a href="https://x.com/?lang=es" target="_blank" class="btn btn-outline-dark">
                                <i class="fab fa-twitter"></i> Twitter
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- ============================================================================
         FOOTER
         ============================================================================ -->
    
    <footer>
        <a href="https://www.facebook.com/?locale=es_LA" target="_blank">
            <img class="red-social" src="iconos/facebook.png" alt="icono de facebook">
        </a>
        <a href="https://www.instagram.com/" target="_blank">
            <img class="red-social" src="iconos/instagram.png" alt="icono de instagram">
        </a>
        <a href="https://x.com/?lang=es" target="_blank">
            <img class="red-social" src="iconos/x.png" alt="icono de x">
        </a>
        <h6>2025.Todos los derechos reservados</h6>
    </footer>

    <!-- ============================================================================
         SCRIPTS DE JAVASCRIPT
         ============================================================================ -->
    
    <!-- Bootstrap JavaScript Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" 
            crossorigin="anonymous"></script>
</body>
</html>

