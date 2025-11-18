<?php
/**
 * ========================================================================
 * JAVASCRIPT PARA DETALLE DE PRODUCTO - Tienda Seda y Lino
 * ========================================================================
 * JavaScript específico para la página de detalle de producto
 * Incluye funciones para manejo de talles, colores, stock e imágenes
 * 
 * REQUIERE:
 * - window.productoData: Objeto JavaScript generado por detalle_producto_data.php
 *   que contiene: stockVariantes, stockPorTalleColor, fotosPorColor, imagenes, etc.
 * 
 * IMPORTANTE:
 * - Este archivo debe incluirse DESPUÉS de detalle_producto_data.php
 * - detalle_producto_data.php genera window.productoData con todos los datos necesarios
 * 
 * RELACIÓN CON OTROS ARCHIVOS:
 * - detalle_producto_image_navigation.js: Agrega funcionalidad de navegación por clic en la imagen principal.
 *   Ambos archivos comparten la función cambiarImagenPrincipal() y window.indiceImagenActual.
 *   No hay duplicación de listeners, solo complementariedad de funcionalidades.
 * 
 * USO:
 *   <?php include 'includes/detalle_producto_data.php'; ?>
 *   <?php include 'includes/detalle_producto_js.php'; ?>
 *   <!-- Opcional: para navegación por clic en imagen -->
 *   <script src="includes/detalle_producto_image_navigation.js"></script>
 * 
 * @package TiendaSedaYLino
 * @version 2.0
 * ========================================================================
 */
?>
<script>
    // Solo JavaScript esencial para UX del navegador (no replicable en PHP)
    // NOTA: Los datos del producto (stockVariantes, stockPorTalleColor, fotosPorColor) 
    // se obtienen de window.productoData que es generado por detalle_producto_data.php
    // Asegúrate de incluir detalle_producto_data.php antes de este archivo.
    
    // Obtener datos del producto desde window.productoData (generado por detalle_producto_data.php)
    const productoData = window.productoData || {};
    const stockVariantes = productoData.stockVariantes || <?php echo json_encode($stockVariantes ?? []); ?>;
    const stockPorTalleColor = productoData.stockPorTalleColor || <?php echo json_encode($stockPorTalleColor ?? []); ?>;
    const fotosPorColor = productoData.fotosPorColor || {};
    
    document.addEventListener('DOMContentLoaded', function() {
        const formCarrito = document.getElementById('formCarrito');
        
        // Thumbnails del carrusel - Solo JS (Bootstrap)
        document.querySelectorAll('.producto-thumbnail').forEach(function(thumb, i) {
            thumb.style.cursor = 'pointer'; // Asegurar cursor de mano
            thumb.addEventListener('click', function() {
                const carousel = bootstrap.Carousel.getOrCreateInstance(document.getElementById('carouselProducto'));
                carousel.to(i);
                document.querySelectorAll('.producto-thumbnail').forEach(function(t, idx) {
                    t.classList.toggle('active', idx === i);
                });
            });
        });
        
        // Controles de cantidad - Solo JS (UX inmediata)
        document.querySelectorAll('.btn[onclick*="cambiarCantidad"]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const action = this.getAttribute('data-action');
                const input = document.getElementById('cantidad');
                if (!input) return;
                
                let val = parseInt(input.value) || 1;
                if (action === 'increment') {
                    val += 1;
                } else if (action === 'decrement') {
                    val -= 1;
                    if (val < 1) val = 1;
                }
                input.value = val;
            });
        });
        
        // Actualizar talles tachados según el color seleccionado
        function actualizarTallesPorColor() {
            const color = document.querySelector('input[name="color"]:checked');
            
            if (!color) {
                // Si no hay color seleccionado, mostrar todos los talles normales
                document.querySelectorAll('.talla-label').forEach(function(label) {
                    label.classList.remove('talla-sin-stock');
                });
                return;
            }
            
            const colorSeleccionado = color.getAttribute('data-color');
            
            // Actualizar cada talle según el stock disponible para este color
            document.querySelectorAll('.talla-label').forEach(function(label) {
                const talleValue = label.getAttribute('data-talle');
                
                // Verificar stock para esta combinación talle-color
                if (stockPorTalleColor[talleValue] && stockPorTalleColor[talleValue][colorSeleccionado]) {
                    const stock = stockPorTalleColor[talleValue][colorSeleccionado];
                    if (stock > 0) {
                        label.classList.remove('talla-sin-stock');
                    } else {
                        label.classList.add('talla-sin-stock');
                    }
                } else {
                    // No hay stock para esta combinación
                    label.classList.add('talla-sin-stock');
                }
            });
        }
        
        // Actualizar colores tachados según el talle seleccionado
        function actualizarColoresPorTalle() {
            const talla = document.querySelector('input[name="talla"]:checked');
            
            if (!talla) {
                // Si no hay talle seleccionado, verificar si cada color tiene al menos un talle disponible
                document.querySelectorAll('.color-label').forEach(function(label) {
                    const colorValue = label.getAttribute('data-color');
                    let tieneStockEnAlgunTalle = false;
                    
                    // Verificar si este color tiene stock en algún talle
                    for (const talleKey in stockPorTalleColor) {
                        if (stockPorTalleColor[talleKey] && stockPorTalleColor[talleKey][colorValue]) {
                            const stock = stockPorTalleColor[talleKey][colorValue];
                            if (stock > 0) {
                                tieneStockEnAlgunTalle = true;
                                break;
                            }
                        }
                    }
                    
                    if (tieneStockEnAlgunTalle) {
                        label.classList.remove('color-sin-stock');
                    } else {
                        // No hay stock para este color en ningún talle
                        label.classList.add('color-sin-stock');
                    }
                });
                return;
            }
            
            const talleSeleccionado = talla.getAttribute('data-talle');
            
            // Actualizar cada color según el stock disponible para este talle
            document.querySelectorAll('.color-label').forEach(function(label) {
                const colorValue = label.getAttribute('data-color');
                
                // Verificar stock para esta combinación talle-color
                if (stockPorTalleColor[talleSeleccionado] && stockPorTalleColor[talleSeleccionado][colorValue]) {
                    const stock = stockPorTalleColor[talleSeleccionado][colorValue];
                    if (stock > 0) {
                        label.classList.remove('color-sin-stock');
                    } else {
                        label.classList.add('color-sin-stock');
                    }
                } else {
                    // No hay stock para esta combinación
                    label.classList.add('color-sin-stock');
                }
            });
        }
        
        /**
         * Cambia las imágenes del producto según el color seleccionado
         * Mantiene la posición fija de la imagen para mejorar UX
         * @param {string} colorSeleccionado - Color seleccionado
         */
        function cambiarImagenesPorColor(colorSeleccionado) {
            const imagenPrincipal = document.getElementById('imagenPrincipalLimpia');
            const thumbnailsContainer = document.getElementById('thumbnailsContainer');
            
            if (!imagenPrincipal || !thumbnailsContainer) return;
            
            // Normalizar color para coincidencia exacta (primera letra mayúscula, resto minúscula)
            let colorNormalizado = '';
            if (colorSeleccionado) {
                colorNormalizado = colorSeleccionado.charAt(0).toUpperCase() + colorSeleccionado.slice(1).toLowerCase();
            }
            
            // Obtener imágenes del color seleccionado
            let nuevasImagenes = [];
            
            // Buscar fotos del color - buscar en todas las claves posibles (case-insensitive)
            let fotosEncontradas = null;
            
            // Primero intentar con el color normalizado
            if (colorNormalizado) {
                // Buscar coincidencia exacta (normalizada)
                if (fotosPorColor[colorNormalizado] && fotosPorColor[colorNormalizado].length > 0) {
                    fotosEncontradas = fotosPorColor[colorNormalizado];
                } 
                // Si no encuentra, buscar case-insensitive en todas las claves
                else {
                    for (const key in fotosPorColor) {
                        if (key !== '_generales') {
                            const keyNormalizada = key.charAt(0).toUpperCase() + key.slice(1).toLowerCase();
                            if (keyNormalizada === colorNormalizado && fotosPorColor[key] && fotosPorColor[key].length > 0) {
                                fotosEncontradas = fotosPorColor[key];
                                break;
                            }
                        }
                    }
                }
            }
            
            // Si no se encontraron fotos del color, usar fotos generales o imagen por defecto
            if (fotosEncontradas && fotosEncontradas.length > 0) {
                nuevasImagenes = fotosEncontradas;
            } else if (fotosPorColor['_generales'] && fotosPorColor['_generales'].length > 0) {
                nuevasImagenes = fotosPorColor['_generales'];
            } else {
                nuevasImagenes = ['imagenes/imagen.png'];
            }
            
            // Pre-cargar la nueva imagen antes de cambiar el src para evitar el "salto"
            if (nuevasImagenes.length > 0) {
                const nuevaImagenSrc = nuevasImagenes[0];
                
                // Crear objeto Image para pre-cargar
                const imgPreload = new Image();
                
                // Guardar dimensiones actuales del contenedor para mantener la posición
                const contenedorPadre = imagenPrincipal.parentElement;
                const alturaActual = contenedorPadre ? contenedorPadre.offsetHeight : null;
                
                // Cuando la imagen se carga, cambiar el src sin movimiento
                imgPreload.onload = function() {
                    // Aplicar fade-out suave
                    imagenPrincipal.style.opacity = '0';
                    
                    // Cambiar imagen después de un breve delay para suavizar la transición
                    setTimeout(function() {
                        imagenPrincipal.src = nuevaImagenSrc;
                        imagenPrincipal.alt = '<?php echo htmlspecialchars($producto['nombre_producto']); ?> - ' + (colorSeleccionado || '');
                        
                        // Asegurar que object-fit y object-position se mantengan
                        imagenPrincipal.style.objectFit = 'contain';
                        imagenPrincipal.style.objectPosition = 'center center';
                        
                        // Fade-in suave
                        setTimeout(function() {
                            imagenPrincipal.style.opacity = '1';
                        }, 50);
                    }, 150);
                };
                
                // Si hay error cargando la imagen, intentar con la siguiente o fallback
                imgPreload.onerror = function() {
                    if (nuevasImagenes.length > 1) {
                        imgPreload.src = nuevasImagenes[1];
                    } else if (fotosPorColor['_generales'] && fotosPorColor['_generales'].length > 0) {
                        imgPreload.src = fotosPorColor['_generales'][0];
                    } else {
                        imgPreload.src = 'imagenes/imagen.png';
                    }
                };
                
                // Iniciar carga de la imagen
                imgPreload.src = nuevaImagenSrc;
            }
            
            // Actualizar miniaturas - siempre mostrar al menos una si existe
            thumbnailsContainer.innerHTML = '';
            
            // Crear miniaturas para todas las imágenes disponibles (incluso si solo hay una)
            if (nuevasImagenes.length > 0) {
                nuevasImagenes.forEach(function(imagen, index) {
                    const thumbnailDiv = document.createElement('div');
                    thumbnailDiv.className = 'thumbnail-compacto' + (index === 0 ? ' active' : '');
                    
                    // Usar closure para mantener el índice correcto y addEventListener en lugar de onclick
                    thumbnailDiv.setAttribute('data-image-index', index);
                    (function(idx) {
                        thumbnailDiv.addEventListener('click', function() { 
                            if (typeof window.cambiarImagenPrincipal === 'function') {
                                window.cambiarImagenPrincipal(idx); 
                            }
                        });
                    })(index);
                    
                    const thumbnailImg = document.createElement('img');
                    thumbnailImg.src = imagen;
                    thumbnailImg.alt = 'Miniatura ' + (index + 1);
                    
                    thumbnailDiv.appendChild(thumbnailImg);
                    thumbnailsContainer.appendChild(thumbnailDiv);
                });
            }
            
            // Actualizar variable global de imágenes para compatibilidad con cambiarImagenPrincipal
            // Esta variable se usa en la función global cambiarImagenPrincipal y detalle_producto_image_navigation.js
            window.imagenesProducto = nuevasImagenes;
            // Resetear índice cuando cambian las imágenes por color
            window.indiceImagenActual = 0;
            
            // Si hay imágenes, asegurar que la primera esté visible
            if (nuevasImagenes.length > 0 && imagenPrincipal) {
                // La imagen principal ya se actualizó arriba, solo asegurar que esté visible
                imagenPrincipal.style.opacity = '1';
            }
        }
        
        // Actualizar indicador de stock - Solo JS (feedback inmediato)
        function actualizarStock() {
            const talla = document.querySelector('input[name="talla"]:checked');
            const color = document.querySelector('input[name="color"]:checked');
            const stockEl = document.getElementById('stock-texto');
            const stockContainer = document.getElementById('stock-indicador');
            const btnComprar = document.getElementById('btn-comprar-ahora');
            const btnCarrito = document.getElementById('btn-agregar-carrito');
            
            // Actualizar opciones tachadas según la selección
            if (color) {
                actualizarTallesPorColor();
            } else {
                // Si no hay color seleccionado, inicializar talles sin restricciones
                document.querySelectorAll('.talla-label').forEach(function(label) {
                    label.classList.remove('talla-sin-stock');
                });
            }
            
            // Siempre actualizar colores (funciona con o sin talle seleccionado)
            actualizarColoresPorTalle();
            
            // Determinar si hay stock disponible
            let tieneStock = false;
            let stockDisponible = 0;
            
            if (talla && color) {
                // Normalizar la clave para que coincida (asegurar que el formato sea consistente)
                const claveStock = talla.value + '-' + color.value;
                stockDisponible = stockVariantes[claveStock];
                
                // Si no se encuentra con ese formato, intentar buscar en stockPorTalleColor
                if (stockDisponible === undefined || stockDisponible === null) {
                    if (stockPorTalleColor[talla.value] && stockPorTalleColor[talla.value][color.value]) {
                        stockDisponible = stockPorTalleColor[talla.value][color.value];
                    } else {
                        stockDisponible = 0;
                    }
                }
                
                // Convertir a número para asegurar comparación correcta
                stockDisponible = parseInt(stockDisponible) || 0;
                tieneStock = stockDisponible > 0;
            }
            
            // Deshabilitar/habilitar botones según stock
            if (btnComprar && btnCarrito) {
                // Si no hay talle o color seleccionado, deshabilitar botones
                if (!talla || !color || !tieneStock) {
                    btnComprar.disabled = true;
                    btnCarrito.disabled = true;
                    btnComprar.style.opacity = '0.6';
                    btnCarrito.style.opacity = '0.6';
                    btnComprar.style.cursor = 'not-allowed';
                    btnCarrito.style.cursor = 'not-allowed';
                } else {
                    btnComprar.disabled = false;
                    btnCarrito.disabled = false;
                    btnComprar.style.opacity = '1';
                    btnCarrito.style.opacity = '1';
                    btnComprar.style.cursor = 'pointer';
                    btnCarrito.style.cursor = 'pointer';
                }
            }
            
            // Actualizar mensaje de stock
            const stockIcon = stockContainer ? stockContainer.querySelector('i') : null;
            
            if (stockEl && stockContainer) {
                if (talla && color) {
                    if (stockDisponible > 0) {
                        stockEl.textContent = stockDisponible + ' unidades disponibles';
                        stockEl.className = 'text-success';
                        stockContainer.style.borderLeftColor = '#4A9FD6';
                        stockContainer.style.background = '#E3F2F8';
                        
                        // Cambiar icono a check para stock disponible
                        if (stockIcon) {
                            stockIcon.className = 'fas fa-check-circle me-1';
                        }
                    } else {
                        // Mensaje cuando no hay stock
                        stockEl.textContent = 'Sin stock disponible';
                        stockEl.className = 'text-danger';
                        stockContainer.style.borderLeftColor = '#dc3545';
                        stockContainer.style.background = '#f8d7da';
                        
                        // Cambiar icono a times para sin stock
                        if (stockIcon) {
                            stockIcon.className = 'fas fa-times-circle me-1';
                        }
                    }
                } else if (talla) {
                    stockEl.textContent = 'Selecciona un color';
                    stockEl.className = 'text-warning';
                    stockContainer.style.borderLeftColor = '#ffc107';
                    stockContainer.style.background = '#fff3cd';
                    
                    // Cambiar icono a info
                    if (stockIcon) {
                        stockIcon.className = 'fas fa-info-circle me-1';
                    }
                } else if (color) {
                    stockEl.textContent = 'Selecciona un talle';
                    stockEl.className = 'text-warning';
                    stockContainer.style.borderLeftColor = '#ffc107';
                    stockContainer.style.background = '#fff3cd';
                    
                    // Cambiar icono a info
                    if (stockIcon) {
                        stockIcon.className = 'fas fa-info-circle me-1';
                    }
                } else {
                    stockEl.textContent = 'Selecciona talle y color';
                    stockEl.className = 'text-muted';
                    stockContainer.style.borderLeftColor = '#6c757d';
                    stockContainer.style.background = '#f8f9fa';
                    
                    // Cambiar icono a info
                    if (stockIcon) {
                        stockIcon.className = 'fas fa-info-circle me-1';
                    }
                }
            }
        }
        
        // Variables para mantener el estado anterior de selección
        let colorAnteriorSeleccionado = null;
        let tallaAnteriorSeleccionada = null;
        
        // Escuchar cambios en color para actualizar talles tachados
        document.querySelectorAll('input[name="color"]').forEach(function(input) {
            input.addEventListener('click', function() {
                // Guardar el color anterior antes de cambiar
                const colorActual = document.querySelector('input[name="color"]:checked');
                if (colorActual && colorActual !== this) {
                    colorAnteriorSeleccionado = colorActual.value;
                } else if (!colorActual) {
                    colorAnteriorSeleccionado = null;
                }
            });
            
            input.addEventListener('change', function() {
                const colorValue = this.getAttribute('data-color');
                const label = document.querySelector(`label[for="color-${colorValue.toLowerCase()}"]`);
                const talla = document.querySelector('input[name="talle"]:checked');
                
                // Cambiar imágenes según el color seleccionado
                cambiarImagenesPorColor(colorValue);
                
                // Actualizar primero para mostrar el mensaje de stock
                actualizarTallesPorColor();
                actualizarStock();
                
                // Verificar stock después de actualizar para aplicar restricciones si es necesario
                const tallaActual = document.querySelector('input[name="talle"]:checked');
                let tieneStockEnAlgunTalle = false;
                
                if (tallaActual) {
                    // Si hay talle seleccionado, verificar stock para esta combinación
                    const talleValue = tallaActual.getAttribute('data-talle');
                    if (stockPorTalleColor[talleValue] && stockPorTalleColor[talleValue][colorValue]) {
                        const stock = stockPorTalleColor[talleValue][colorValue];
                        tieneStockEnAlgunTalle = stock > 0;
                    }
                } else {
                    // Si no hay talle, verificar si tiene stock en algún talle
                    for (const talleKey in stockPorTalleColor) {
                        if (stockPorTalleColor[talleKey] && stockPorTalleColor[talleKey][colorValue]) {
                            const stock = stockPorTalleColor[talleKey][colorValue];
                            if (stock > 0) {
                                tieneStockEnAlgunTalle = true;
                                break;
                            }
                        }
                    }
                }
                
                // Solo deseleccionar si no tiene stock en ningún talle y no hay talle seleccionado
                // Si hay talle seleccionado, permitir la selección para mostrar el mensaje de "Sin stock"
                if (label && label.classList.contains('color-sin-stock') && !tieneStockEnAlgunTalle && !tallaActual) {
                    // Si está sin stock y no hay talle, restaurar el color anterior o deseleccionar
                    setTimeout(() => {
                        if (colorAnteriorSeleccionado) {
                            const colorAnterior = document.querySelector(`input[name="color"][value="${colorAnteriorSeleccionado}"]`);
                            const labelAnterior = document.querySelector(`label[for="color-${colorAnteriorSeleccionado.toLowerCase()}"]`);
                            if (colorAnterior && labelAnterior && !labelAnterior.classList.contains('color-sin-stock')) {
                                colorAnterior.checked = true;
                                this.checked = false;
                                actualizarTallesPorColor();
                                actualizarStock();
                                return;
                            }
                        }
                        // Si no hay color anterior válido, deseleccionar
                        this.checked = false;
                        colorAnteriorSeleccionado = null;
                        actualizarStock();
                    }, 100);
                }
            });
        });
        
        // Escuchar cambios en talle para actualizar colores tachados
        document.querySelectorAll('input[name="talla"]').forEach(function(input) {
            input.addEventListener('click', function() {
                // Guardar el talle anterior antes de cambiar
                const tallaActual = document.querySelector('input[name="talle"]:checked');
                if (tallaActual && tallaActual !== this) {
                    tallaAnteriorSeleccionada = tallaActual.value;
                } else if (!tallaActual) {
                    tallaAnteriorSeleccionada = null;
                }
            });
            
            input.addEventListener('change', function() {
                const talleValue = this.getAttribute('data-talle');
                const label = document.querySelector(`label[for="talla-${talleValue}"]`);
                const color = document.querySelector('input[name="color"]:checked');
                
                // Actualizar primero para mostrar el mensaje de stock
                actualizarColoresPorTalle();
                actualizarStock();
                
                // Verificar stock después de actualizar para aplicar restricciones si es necesario
                const colorActual = document.querySelector('input[name="color"]:checked');
                let tieneStockEnAlgunColor = false;
                
                if (colorActual) {
                    // Si hay color seleccionado, verificar stock para esta combinación
                    const colorValue = colorActual.getAttribute('data-color');
                    if (stockPorTalleColor[talleValue] && stockPorTalleColor[talleValue][colorValue]) {
                        const stock = stockPorTalleColor[talleValue][colorValue];
                        tieneStockEnAlgunColor = stock > 0;
                    }
                } else {
                    // Si no hay color, verificar si tiene stock en algún color
                    if (stockPorTalleColor[talleValue]) {
                        for (const colorKey in stockPorTalleColor[talleValue]) {
                            if (stockPorTalleColor[talleValue][colorKey] > 0) {
                                tieneStockEnAlgunColor = true;
                                break;
                            }
                        }
                    }
                }
                
                // Solo deseleccionar si no tiene stock en ningún color y no hay color seleccionado
                // Si hay color seleccionado, permitir la selección para mostrar el mensaje de "Sin stock"
                if (label && label.classList.contains('talla-sin-stock') && !tieneStockEnAlgunColor && !colorActual) {
                    // Si está sin stock y no hay color, restaurar el talle anterior o deseleccionar
                    setTimeout(() => {
                        if (tallaAnteriorSeleccionada) {
                            const tallaAnterior = document.querySelector(`input[name="talle"][value="${tallaAnteriorSeleccionada}"]`);
                            const labelAnterior = document.querySelector(`label[for="talla-${tallaAnteriorSeleccionada}"]`);
                            if (tallaAnterior && labelAnterior && !labelAnterior.classList.contains('talla-sin-stock')) {
                                tallaAnterior.checked = true;
                                this.checked = false;
                                actualizarColoresPorTalle();
                                actualizarStock();
                                return;
                            }
                        }
                        // Si no hay talle anterior válido, deseleccionar
                        this.checked = false;
                        tallaAnteriorSeleccionada = null;
                        actualizarStock();
                    }, 100);
                }
            });
        });
        
        
        // Este listener problemático fue eliminado - la validación ahora se hace en el listener de 'change' arriba
        
        // Inicializar al cargar la página
        // Esperar un momento para asegurar que todos los elementos estén cargados
        setTimeout(function() {
            actualizarStock();
        }, 100);
        
        // También inicializar inmediatamente por si acaso
        actualizarStock();
        
        // Validación de formulario carrito - Solo JS (UX antes de enviar)
        if (formCarrito) {
            formCarrito.addEventListener('submit', function(e) {
                const talla = document.querySelector('input[name="talla"]:checked');
                if (!talla) {
                    e.preventDefault();
                    alert('Por favor selecciona un talle');
                    return false;
                }
                
                const color = document.querySelector('input[name="color"]:checked');
                const tallaHidden = document.getElementById('talla_hidden');
                const colorHidden = document.getElementById('color_hidden');
                const cantidadHidden = document.getElementById('cantidad_hidden');
                
                if (tallaHidden) tallaHidden.value = talla.value;
                if (colorHidden) colorHidden.value = color ? color.value : '';
                if (cantidadHidden) cantidadHidden.value = document.getElementById('cantidad').value;
            });
        }
        
        // Event listeners para botones comprar y agregar al carrito (reemplaza onclick inline)
        const btnComprarAhora = document.getElementById('btn-comprar-ahora');
        if (btnComprarAhora && typeof window.comprarAhora === 'function') {
            btnComprarAhora.addEventListener('click', function(e) {
                e.preventDefault();
                window.comprarAhora();
            });
        }
        
        const btnAgregarCarrito = document.getElementById('btn-agregar-carrito');
        if (btnAgregarCarrito && typeof window.agregarAlCarrito === 'function') {
            btnAgregarCarrito.addEventListener('click', function(e) {
                e.preventDefault();
                window.agregarAlCarrito();
            });
        }
    });
    
    // Funciones globales para UX mejorada
    // Variable global de imágenes - se actualiza dinámicamente cuando cambia el color
    var imagenesProducto = <?php echo json_encode(isset($imagenes) && is_array($imagenes) ? $imagenes : ['imagenes/imagen.png']); ?>;
    // Asegurar que esté disponible en window
    window.imagenesProducto = imagenesProducto;
    
    /**
     * Cambia la imagen principal cuando se hace clic en una miniatura
     * Mantiene la posición fija para mejorar UX
     * 
     * NOTA: Esta función es compartida con detalle_producto_image_navigation.js
     * que permite navegar entre imágenes haciendo clic en la imagen principal.
     * Se actualiza window.indiceImagenActual para mantener sincronización.
     * 
     * @param {number} index - Índice de la imagen a mostrar
     */
    function cambiarImagenPrincipal(index) {
        // Actualizar imagen principal
        const imagenPrincipal = document.getElementById('imagenPrincipalLimpia');
        
        // Siempre usar window.imagenesProducto que se actualiza dinámicamente
        const imagenes = window.imagenesProducto || imagenesProducto || [];
        
        // Validar que el índice sea válido
        if (!imagenPrincipal || !imagenes || !Array.isArray(imagenes) || index < 0 || index >= imagenes.length) {
            return;
        }
        
        const nuevaImagenSrc = imagenes[index];
        if (!nuevaImagenSrc) {
            return;
        }
        
        // Actualizar índice actual para sincronización con detalle_producto_image_navigation.js
        window.indiceImagenActual = index;
        
        // Pre-cargar la imagen antes de cambiar para evitar el salto
        const imgPreload = new Image();
        
        imgPreload.onload = function() {
            // Aplicar fade-out suave
            imagenPrincipal.style.opacity = '0';
            
            // Cambiar imagen después de un breve delay
            setTimeout(function() {
                imagenPrincipal.src = nuevaImagenSrc;
                
                // Asegurar que object-fit y object-position se mantengan
                imagenPrincipal.style.objectFit = 'contain';
                imagenPrincipal.style.objectPosition = 'center center';
                
                // Fade-in suave
                setTimeout(function() {
                    imagenPrincipal.style.opacity = '1';
                }, 50);
            }, 150);
        };
        
        imgPreload.onerror = function() {
            // Si hay error, simplemente cambiar sin pre-carga
            imagenPrincipal.src = nuevaImagenSrc;
            imagenPrincipal.style.opacity = '1';
        };
        
        // Iniciar carga
        imgPreload.src = nuevaImagenSrc;
        
        // Actualizar thumbnails activos (compatible con ambos estilos)
        document.querySelectorAll('.thumbnail-compacto, .thumbnail-mini').forEach((item, i) => {
            item.classList.toggle('active', i === index);
        });
    }
    
    function mostrarZoom() {
        const detalleZoom = document.getElementById('detalleZoom');
        if (detalleZoom) {
            detalleZoom.style.transform = 'scale(1.1)';
            setTimeout(() => {
                detalleZoom.style.transform = 'scale(1)';
            }, 200);
        }
    }
    
    function cambiarImagen(i) { cambiarImagenPrincipal(i); }
    function cambiarCantidad(d) { 
        const input = document.getElementById('cantidad');
        let val = parseInt(input.value) + d;
        input.value = val < 1 ? 1 : val;
    }
    
    /**
     * Agrega producto al carrito usando AJAX (sin salir de la página)
     * Valida talla/color antes de enviar
     * @param {boolean} redirigirCheckout - Si es true, redirige a checkout después de agregar
     */
    function agregarAlCarrito(redirigirCheckout = false) {
        const talla = document.querySelector('input[name="talla"]:checked');
        const color = document.querySelector('input[name="color"]:checked');
        const cantidadInput = document.getElementById('cantidad');
        const btnAgregar = document.getElementById('btn-agregar-carrito');
        const btnComprar = document.getElementById('btn-comprar-ahora');
        
        if (!talla) {
            alert('Por favor selecciona una talla');
            return false;
        }
        
        if (!color) {
            alert('Por favor selecciona un color');
            return false;
        }
        
        // Validar cantidad
        const cantidad = parseInt(cantidadInput.value) || 1;
        if (cantidad < 1) {
            alert('La cantidad debe ser mayor a 0');
            return false;
        }
        
        // Verificar stock antes de agregar
        const stock = stockVariantes[talla.value + '-' + color.value] || 0;
        if (stock <= 0) {
            alert('No hay stock disponible para esta combinación de talle y color');
            return false;
        }
        
        // Verificar que la cantidad no exceda el stock disponible
        // Nota: El backend suma cantidades si ya existe en el carrito, así que esta validación
        // solo verifica que la cantidad solicitada no exceda el stock total disponible
        if (cantidad > stock) {
            alert('No hay suficiente stock. Disponible: ' + stock + ' unidades');
            return false;
        }
        
        // Guardar estado original de los botones para restaurar correctamente
        const btnAgregarOriginalHTML = btnAgregar ? btnAgregar.innerHTML : '';
        const btnComprarOriginalHTML = btnComprar ? btnComprar.innerHTML : '';
        
        // Deshabilitar botones mientras se procesa
        if (btnAgregar) {
            btnAgregar.disabled = true;
            btnAgregar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Agregando...';
        }
        if (btnComprar) {
            btnComprar.disabled = true;
            btnComprar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
        }
        
        // Preparar datos para enviar
        const formData = new FormData();
        formData.append('accion', 'agregar');
        formData.append('id_producto', <?php echo $id_producto; ?>);
        formData.append('talla', talla.value);
        formData.append('color', color.value);
        formData.append('cantidad', cantidad);
        formData.append('ajax', '1');
        
        // Enviar petición AJAX
        fetch('carrito.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Verificar si la respuesta es JSON válida
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(data => {
            // Restaurar botones siempre (incluso si hay error)
            if (btnAgregar) {
                btnAgregar.disabled = false;
                btnAgregar.innerHTML = btnAgregarOriginalHTML || '<i class="fas fa-shopping-cart me-2"></i>Agregar al Carrito';
            }
            if (btnComprar) {
                btnComprar.disabled = false;
                btnComprar.innerHTML = btnComprarOriginalHTML || '<i class="fas fa-credit-card me-2"></i>Comprar Ahora';
            }
            
            if (data.success) {
                // Mostrar mensaje de éxito
                mostrarMensajeCarrito(data.mensaje, 'success');
                
                // Actualizar contador del carrito en la navegación
                actualizarContadorCarrito(data.cantidad_carrito);
                
                // Si se debe redirigir a checkout, hacerlo después de un breve delay
                if (redirigirCheckout) {
                    setTimeout(() => {
                        window.location.href = 'checkout.php';
                    }, 500);
                }
            } else {
                // Mostrar mensaje de error
                mostrarMensajeCarrito(data.mensaje || 'Error al agregar el producto al carrito', 'error');
            }
        })
        .catch(error => {
            // Restaurar botones siempre en caso de error
            if (btnAgregar) {
                btnAgregar.disabled = false;
                btnAgregar.innerHTML = btnAgregarOriginalHTML || '<i class="fas fa-shopping-cart me-2"></i>Agregar al Carrito';
            }
            if (btnComprar) {
                btnComprar.disabled = false;
                btnComprar.innerHTML = btnComprarOriginalHTML || '<i class="fas fa-credit-card me-2"></i>Comprar Ahora';
            }
            mostrarMensajeCarrito('Error al agregar el producto al carrito. Por favor, intenta nuevamente.', 'error');
        });
        
        return false;
    }
    
    /**
     * Actualiza el contador del carrito en la navegación
     * @param {number} cantidad - Cantidad de items en el carrito
     */
    function actualizarContadorCarrito(cantidad) {
        // Buscar el badge del carrito en la navegación
        const carritoLink = document.querySelector('a[href*="carrito"]');
        if (!carritoLink) return;
        
        // Buscar el badge existente
        let badge = carritoLink.querySelector('.badge');
        
        const cantidadNum = parseInt(cantidad) || 0;
        
        if (cantidadNum > 0) {
            if (badge) {
                // Actualizar badge existente
                badge.textContent = cantidadNum;
            } else {
                // Crear nuevo badge
                badge = document.createElement('span');
                badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                badge.textContent = cantidadNum;
                carritoLink.appendChild(badge);
            }
        } else {
            // Eliminar badge si no hay items
            if (badge) {
                badge.remove();
            }
        }
    }
    
    /**
     * Muestra mensaje de éxito/error al agregar al carrito
     */
    function mostrarMensajeCarrito(mensaje, tipo) {
        // Crear o actualizar contenedor de mensaje
        let mensajeContainer = document.getElementById('mensaje-carrito');
        if (!mensajeContainer) {
            mensajeContainer = document.createElement('div');
            mensajeContainer.id = 'mensaje-carrito';
            mensajeContainer.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px; max-width: 400px;';
            
            // Insertar después del contenedor principal
            const mainContainer = document.querySelector('.detalle-producto .container');
            if (mainContainer) {
                mainContainer.parentNode.insertBefore(mensajeContainer, mainContainer.nextSibling);
            } else {
                document.body.appendChild(mensajeContainer);
            }
        }
        
        // Configurar clases según el tipo
        const claseBootstrap = tipo === 'success' ? 'alert-success' : 'alert-danger';
        const icono = tipo === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        
        mensajeContainer.innerHTML = `
            <div class="alert ${claseBootstrap} alert-dismissible fade show shadow-lg" role="alert">
                <i class="fas ${icono} me-2"></i>
                <strong>${mensaje}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        // Auto-ocultar después de 3 segundos
        setTimeout(() => {
            const alert = mensajeContainer.querySelector('.alert');
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 3000);
    }
    
    function comprarAhora() { 
        // Para "Comprar ahora", redirigir al checkout después de agregar exitosamente
        agregarAlCarrito(true);
    }
    
    // Asegurar que las funciones estén disponibles globalmente
    window.agregarAlCarrito = agregarAlCarrito;
    window.comprarAhora = comprarAhora;
</script>

