/**
 * ========================================================================
 * JAVASCRIPT PARA DETALLE DE PRODUCTO - Tienda Seda y Lino
 * ========================================================================
 * JavaScript específico para la página de detalle de producto
 * Incluye funciones para manejo de talles, colores, stock e imágenes
 * 
 * REQUIERE VARIABLES GLOBALES:
 * - window.productoData.stockVariantes: Array de stock indexado por talle-color
 * - window.productoData.stockPorTalleColor: Array de stock organizado por talle y color
 * - window.productoData.fotosPorColor: Array de fotos organizadas por color
 * - window.productoData.imagenes: Array de imágenes iniciales del producto
 * - window.productoData.nombreProducto: Nombre del producto
 * - window.productoData.idProducto: ID del producto
 * 
 * @package TiendaSedaYLino
 * @version 2.0
 * ========================================================================
 */

// Esperar a que los datos estén disponibles
document.addEventListener('DOMContentLoaded', function() {
    // Verificar que los datos estén disponibles
    if (!window.productoData) {
        console.error('productoData no está disponible');
        return;
    }

    const stockVariantes = window.productoData.stockVariantes || {};
    const stockPorTalleColor = window.productoData.stockPorTalleColor || {};
    const fotosPorColor = window.productoData.fotosPorColor || {};
    const imagenesProducto = window.productoData.imagenes || ['imagenes/imagen.png'];
    const nombreProducto = window.productoData.nombreProducto || '';
    const idProducto = window.productoData.idProducto || 0;

    // Inicializar variable global de imágenes
    window.imagenesProducto = imagenesProducto;
    // Inicializar índice de imagen actual
    window.indiceImagenActual = 0;

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
            const input = document.getElementById('cantidad');
            const delta = this.querySelector('i').classList.contains('fa-plus') ? 1 : -1;
            let val = parseInt(input.value) + delta;
            if (val < 1) val = 1;
            input.value = val;
        });
    });
    
    /**
     * Actualizar talles tachados según el color seleccionado
     */
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
    
    /**
     * Actualizar colores tachados según el talle seleccionado
     */
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
                    imagenPrincipal.alt = nombreProducto + ' - ' + (colorSeleccionado || '');
                    
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
                
                // Usar closure para mantener el índice correcto
                (function(idx) {
                    thumbnailDiv.onclick = function() { 
                        cambiarImagenPrincipal(idx); 
                    };
                })(index);
                
                const thumbnailImg = document.createElement('img');
                thumbnailImg.src = imagen;
                thumbnailImg.alt = 'Miniatura ' + (index + 1);
                
                thumbnailDiv.appendChild(thumbnailImg);
                thumbnailsContainer.appendChild(thumbnailDiv);
            });
        }
        
        // Actualizar variable global de imágenes para compatibilidad con cambiarImagenPrincipal
        // Esta variable se usa en la función global cambiarImagenPrincipal
        window.imagenesProducto = nuevasImagenes;
        // Reiniciar índice cuando cambian las imágenes por color
        window.indiceImagenActual = 0;
        
        // Si hay imágenes, asegurar que la primera esté visible
        if (nuevasImagenes.length > 0 && imagenPrincipal) {
            // La imagen principal ya se actualizó arriba, solo asegurar que esté visible
            imagenPrincipal.style.opacity = '1';
        }
    }
    
    /**
     * Actualizar indicador de stock - Solo JS (feedback inmediato)
     */
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
});

/**
 * Funciones globales para UX mejorada
 */

/**
 * Cambia la imagen principal cuando se hace clic en una miniatura
 * Mantiene la posición fija para mejorar UX
 * @param {number} index - Índice de la imagen a mostrar
 */
function cambiarImagenPrincipal(index) {
    // Actualizar imagen principal
    const imagenPrincipal = document.getElementById('imagenPrincipalLimpia');
    
    // Siempre usar window.imagenesProducto que se actualiza dinámicamente
    const imagenes = window.imagenesProducto || [];
    
    // Validar que el índice sea válido
    if (!imagenPrincipal || !imagenes || !Array.isArray(imagenes) || index < 0 || index >= imagenes.length) {
        console.warn('cambiarImagenPrincipal: Índice inválido o imágenes no disponibles', { index, imagenes });
        return;
    }
    
    const nuevaImagenSrc = imagenes[index];
    if (!nuevaImagenSrc) {
        console.warn('cambiarImagenPrincipal: URL de imagen no válida', { index, nuevaImagenSrc });
        return;
    }
    
    // Guardar índice actual en variable global para navegación por clic
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
        console.warn('cambiarImagenPrincipal: Error al cargar imagen', nuevaImagenSrc);
    };
    
    // Iniciar carga
    imgPreload.src = nuevaImagenSrc;
    
    // Actualizar thumbnails activos (compatible con ambos estilos)
    document.querySelectorAll('.thumbnail-compacto, .thumbnail-mini').forEach((item, i) => {
        item.classList.toggle('active', i === index);
    });
}

/**
 * Función auxiliar para mostrar zoom
 */
function mostrarZoom() {
    const detalleZoom = document.getElementById('detalleZoom');
    if (detalleZoom) {
        detalleZoom.style.transform = 'scale(1.1)';
        setTimeout(() => {
            detalleZoom.style.transform = 'scale(1)';
        }, 200);
    }
}

/**
 * Función auxiliar para cambiar imagen (alias)
 */
function cambiarImagen(i) { 
    cambiarImagenPrincipal(i); 
}

/**
 * Función auxiliar para cambiar cantidad
 */
function cambiarCantidad(d) { 
    const input = document.getElementById('cantidad');
    if (!input) return;
    let val = parseInt(input.value) + d;
    input.value = val < 1 ? 1 : val;
}

/**
 * Agrega producto al carrito usando AJAX (sin salir de la página)
 * Valida talla/color antes de enviar
 * @param {boolean} redirigirCheckout - Si es true, redirige a checkout después de agregar
 */
function agregarAlCarrito(redirigirCheckout = false) {
    // Verificar que window.productoData esté disponible
    if (!window.productoData) {
        console.error('agregarAlCarrito: window.productoData no está disponible');
        alert('Error: No se pudieron cargar los datos del producto. Por favor, recarga la página.');
        return false;
    }
    
    // Obtener datos del producto con validaciones
    const idProducto = window.productoData.idProducto || 0;
    const stockVariantes = window.productoData.stockVariantes || {};
    const stockPorTalleColor = window.productoData.stockPorTalleColor || {};
    
    // Validar que idProducto sea válido
    if (!idProducto || idProducto <= 0) {
        console.error('agregarAlCarrito: ID de producto inválido', idProducto);
        alert('Error: ID de producto inválido. Por favor, recarga la página.');
        return false;
    }
    
    const talla = document.querySelector('input[name="talla"]:checked');
    const color = document.querySelector('input[name="color"]:checked');
    const cantidadInput = document.getElementById('cantidad');
    const btnAgregar = document.getElementById('btn-agregar-carrito');
    const btnComprar = document.getElementById('btn-comprar-ahora');
    
    // Validar elementos del DOM
    if (!cantidadInput) {
        console.error('agregarAlCarrito: No se encontró el input de cantidad');
        alert('Error: No se encontró el campo de cantidad. Por favor, recarga la página.');
        return false;
    }
    
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
    if (isNaN(cantidad) || cantidad < 1) {
        alert('La cantidad debe ser mayor a 0');
        cantidadInput.value = 1;
        return false;
    }
    
    // Verificar stock antes de agregar - usar múltiples fuentes para mayor robustez
    let stock = 0;
    const claveStock = talla.value + '-' + color.value;
    
    // Intentar obtener stock de stockVariantes primero
    if (stockVariantes && typeof stockVariantes === 'object' && stockVariantes[claveStock] !== undefined) {
        stock = parseInt(stockVariantes[claveStock]) || 0;
    }
    
    // Si no se encuentra en stockVariantes, intentar en stockPorTalleColor
    if (stock === 0 && stockPorTalleColor && stockPorTalleColor[talla.value] && stockPorTalleColor[talla.value][color.value]) {
        stock = parseInt(stockPorTalleColor[talla.value][color.value]) || 0;
    }
    
    // Validar que haya stock disponible (stock > 0)
    // El backend se encargará de ajustar la cantidad si excede el stock disponible
    // y mostrará el mensaje apropiado ("Has agregado la cantidad máxima disponible")
    if (stock <= 0) {
        // Mostrar mensaje claro de falta de stock usando la función de mensajes
        mostrarMensajeCarrito('No hay stock disponible para esta combinación de talle y color. Por favor, selecciona otra opción.', 'error');
        return false;
    }
    
    // No validar aquí si la cantidad excede el stock, permitir que el backend lo maneje
    // El backend ajustará automáticamente la cantidad al stock disponible si es necesario
    
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
    formData.append('id_producto', idProducto);
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
        // Verificar si la respuesta es válida
        if (!response.ok) {
            // Intentar leer el mensaje de error si está disponible
            return response.text().then(text => {
                try {
                    const errorData = JSON.parse(text);
                    // Crear objeto de error personalizado que preserve el mensaje del backend
                    const customError = new Error(errorData.mensaje || 'Error en la respuesta del servidor');
                    customError.isBackendError = true;
                    customError.mensaje = errorData.mensaje || 'Error en la respuesta del servidor';
                    throw customError;
                } catch (e) {
                    // Si ya es nuestro error personalizado, re-lanzarlo
                    if (e.isBackendError) {
                        throw e;
                    }
                    // Si no se pudo parsear como JSON, crear error genérico
                    const genericError = new Error('Error en la respuesta del servidor (HTTP ' + response.status + ')');
                    genericError.isBackendError = false;
                    throw genericError;
                }
            });
        }
        
        // Intentar parsear como JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const parseError = new Error('La respuesta del servidor no es JSON válido');
            parseError.isBackendError = false;
            throw parseError;
        }
        
        return response.json();
    })
    .then(data => {
        // Validar estructura de datos de respuesta
        if (!data || typeof data !== 'object') {
            const formatError = new Error('La respuesta del servidor no tiene el formato esperado');
            formatError.isBackendError = false;
            throw formatError;
        }
        
        // Restaurar botones siempre (incluso si hay error)
        if (btnAgregar) {
            btnAgregar.disabled = false;
            btnAgregar.innerHTML = btnAgregarOriginalHTML || '<i class="fas fa-shopping-cart me-2"></i>Agregar al Carrito';
        }
        if (btnComprar) {
            btnComprar.disabled = false;
            btnComprar.innerHTML = btnComprarOriginalHTML || '<i class="fas fa-credit-card me-2"></i>Comprar Ahora';
        }
        
        // Verificar si la respuesta es exitosa (success puede ser true, 1, o "true")
        if (data.success === true || data.success === 1 || data.success === 'true') {
            // Mostrar mensaje de éxito
            const mensaje = data.mensaje || 'Producto agregado al carrito';
            // Usar tipo de mensaje del backend si está disponible, sino usar 'success' por defecto
            const tipoMensaje = data.tipo_mensaje || 'success';
            mostrarMensajeCarrito(mensaje, tipoMensaje);
            
            // Actualizar contador del carrito en la navegación
            if (data.cantidad_carrito !== undefined) {
                actualizarContadorCarrito(data.cantidad_carrito);
            }
            
            // Si se debe redirigir a checkout, hacerlo después de un breve delay
            if (redirigirCheckout) {
                setTimeout(() => {
                    window.location.href = 'checkout.php';
                }, 500);
            }
        } else {
            // Mostrar mensaje de error específico del backend
            const mensajeError = data.mensaje || 'Error al agregar el producto al carrito';
            mostrarMensajeCarrito(mensajeError, 'error');
            console.error('Error al agregar al carrito:', data);
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
        console.error('Error:', error);
        
        // Verificar si el error tiene un mensaje específico del backend
        if (error && error.isBackendError && error.mensaje) {
            // Mostrar mensaje específico del backend (ej: "Stock insuficiente. Disponible: X unidades...")
            mostrarMensajeCarrito(error.mensaje, 'error');
        } else {
            // Solo mostrar mensaje genérico para errores de red o inesperados
            mostrarMensajeCarrito('Error al agregar el producto al carrito. Por favor, intenta nuevamente.', 'error');
        }
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
 * Muestra mensaje de éxito/error/warning al agregar al carrito
 * @param {string} mensaje - Mensaje a mostrar
 * @param {string} tipo - Tipo de mensaje ('success', 'error' o 'warning')
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
    let claseBootstrap, icono;
    if (tipo === 'warning') {
        claseBootstrap = 'alert-warning';
        icono = 'fa-exclamation-triangle';
    } else if (tipo === 'success') {
        claseBootstrap = 'alert-success';
        icono = 'fa-check-circle';
    } else {
        claseBootstrap = 'alert-danger';
        icono = 'fa-exclamation-circle';
    }
    
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

/**
 * Función para "Comprar ahora" - redirige al checkout después de agregar
 */
function comprarAhora() { 
    // Para "Comprar ahora", redirigir al checkout después de agregar exitosamente
    agregarAlCarrito(true);
}

