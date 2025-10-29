<?php
/**
 * ========================================================================
 * PÁGINA NOSOTROS - Tienda Seda y Lino
 * ========================================================================
 * Página informativa sobre la tienda
 * - Presenta la historia y valores de la empresa
 * - Muestra la filosofía y compromiso con la calidad
 * - Información sobre los materiales (seda y lino)
 * 
 * Funciones principales:
 * - Página estática que muestra información corporativa
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
$titulo_pagina = 'Nosotros';
?>

<?php include 'includes/header.php'; ?>

<!-- Contenido de nosotros -->
<main>
        <section class="secciones">
            <div class="container">
                <h1 class="titulo-productos text-center mb-5">NOSOTROS</h1>
                
                <div class="row justify-content-center">
                    <div class="col-lg-10 col-md-12">
                        <p class="mb-4">Bienvenidos a <span>Seda y Lino</span>, donde el arte de la confección se une con la elegancia de la seda y el lino para ofrecer piezas únicas que celebran la sofisticación y el estilo. Somos una tienda de ropa para adultos que valora la calidad y la artesanía en cada detalle.</p>
                        
                        <p class="mb-4">Nuestro equipo de diseñadores apasionados y artesanos experimentados se dedica a crear prendas que no solo se ven bien, sino que también se sienten maravillosas al usarlas. Cada tela que utilizamos es seleccionada cuidadosamente para asegurar su suavidad, durabilidad y belleza natural.</p>
                        
                        <p class="mb-4">En <span>Seda y Lino</span>, creemos que la moda es una forma de expresión y estamos comprometidos a ayudarles a encontrar su voz a través de nuestras colecciones.</p>
                        
                        <p class="text-center"><strong>¡Descubran el encanto de la seda y el lino con nosotros y transformen su guardarropa con elegancia y distinción!</strong></p>
                    </div>
                </div>
                
                <div class="row mt-5">
                    <div class="col-md-4 text-center mb-4">
                        <div class="nosotros-icono mb-3">
                            <i class="fas fa-award fa-3x text-primary"></i>
                        </div>
                        <h3>Calidad</h3>
                        <p>Utilizamos únicamente las mejores telas de seda y lino, seleccionadas por su suavidad y durabilidad.</p>
                    </div>
                    <div class="col-md-4 text-center mb-4">
                        <div class="nosotros-icono mb-3">
                            <i class="fas fa-hands fa-3x text-success"></i>
                        </div>
                        <h3>Artesanía</h3>
                        <p>Cada prenda es confeccionada con dedicación y atención al detalle por nuestros artesanos experimentados.</p>
                    </div>
                    <div class="col-md-4 text-center mb-4">
                        <div class="nosotros-icono mb-3">
                            <i class="fas fa-gem fa-3x text-warning"></i>
                        </div>
                        <h3>Elegancia</h3>
                        <p>Diseñamos piezas únicas que celebran la sofisticación y el estilo personal de cada cliente.</p>
                    </div>
                </div>
            </div>
        </section>
</main>


<?php include 'includes/footer.php'; render_footer(); ?>

