<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidad | Seda y Lino</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css?v=2.0">
</head>
<body>
    
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
                            <li><strong>Email:</strong> privacidad@sedaylino.com</li>
                            <li><strong>Teléfono:</strong> +54 11 1234-5678</li>
                            <li><strong>Dirección:</strong> Buenos Aires, Argentina</li>
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
    
    <!-- UX Mejoras Simples -->
    <script src="js/ux-mejoras.js"></script>
</body>
</html>

