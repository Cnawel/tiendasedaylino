/**
 * ========================================================================
 * CARRITO - JavaScript para gestión del carrito de compras
 * ========================================================================
 * Funciones JavaScript para actualización dinámica del carrito mediante AJAX
 * ========================================================================
 */

/**
 * Actualizar cantidad de producto en el carrito usando AJAX
 * @param {number} index - Índice del producto
 * @param {number} cambio - Cambio a aplicar (-1 o 1)
 */
function updateCantidad(index, cambio) {
    const input = document.getElementById('cantidad-' + index);
    const form = document.getElementById('form-cantidad-' + index);
    let nuevaCantidad = parseInt(input.value) + cambio;
    
    // Validar límites
    if (nuevaCantidad < 1) {
        nuevaCantidad = 1;
    }
    
    // Actualizar valor y enviar vía AJAX
    input.value = nuevaCantidad;
    actualizarCantidadAjax(form);
}

/**
 * Actualizar cantidad de producto mediante AJAX
 * @param {HTMLElement} form - Formulario con los datos del producto
 */
function actualizarCantidadAjax(form) {
    const formData = new FormData(form);
    formData.append('ajax', '1');
    
    // Deshabilitar inputs mientras se procesa
    const inputs = form.querySelectorAll('input, button');
    inputs.forEach(input => input.disabled = true);
    
    fetch('carrito.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar subtotal del producto si existe
            const claveInput = form.querySelector('input[name="clave"]');
            if (claveInput && data.subtotal_producto !== undefined) {
                const subtotalEl = document.getElementById('subtotal-producto-' + claveInput.value);
                if (subtotalEl) {
                    subtotalEl.textContent = '$' + data.subtotal_producto.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
            }
            
            // Si el producto fue eliminado, recargar la página
            if (data.producto_eliminado) {
                window.location.reload();
                return;
            }
            
            // Actualizar resumen del carrito
            actualizarResumenCarrito(data);
        } else {
            alert(data.mensaje || 'Error al actualizar la cantidad');
            // Recargar para mostrar estado actual
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al actualizar la cantidad. Por favor, intenta nuevamente.');
        window.location.reload();
    })
    .finally(() => {
        // Rehabilitar inputs
        inputs.forEach(input => input.disabled = false);
    });
}

/**
 * Actualizar resumen del carrito con nuevos datos
 * @param {object} datos - Datos de respuesta del servidor
 */
function actualizarResumenCarrito(datos) {
    const cardBody = document.querySelector('.card-body[data-monto-minimo-gratis]');
    if (!cardBody) return;
    
    const montoMinimoGratis = parseFloat(cardBody.getAttribute('data-monto-minimo-gratis')) || 80000;
    const costoCabaGba = parseFloat(cardBody.getAttribute('data-costo-caba-gba')) || 10000;
    const costoArgentina = parseFloat(cardBody.getAttribute('data-costo-argentina')) || 15000;
    
    // Actualizar subtotal
    const subtotalEl = document.getElementById('carrito-subtotal');
    if (subtotalEl) {
        subtotalEl.textContent = '$' + datos.subtotal.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    
    // Actualizar información de envío
    const envioEl = document.getElementById('carrito-envio');
    if (envioEl && datos.info_envio) {
        if (datos.info_envio.es_gratis) {
            envioEl.innerHTML = '<span class="text-success fw-bold small">GRATIS</span>';
        } else {
            envioEl.innerHTML = '<div class="small text-small-carrito">' +
                '<div class="text-muted mb-1">' +
                'CABA/GBA: <strong class="text-dark">$' + datos.info_envio.costo_caba_gba.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong>' +
                '</div>' +
                '<div class="text-muted">' +
                'Todo Argentina: <strong class="text-dark">$' + datos.info_envio.costo_argentina.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong>' +
                '</div>' +
                '</div>';
        }
    }
    
    // Actualizar alertas de envío
    const alertEl = document.getElementById('carrito-envio-alert');
    if (alertEl && datos.info_envio) {
        let alertHTML = '';
        if (datos.info_envio.es_gratis) {
            alertHTML = '<div class="alert alert-success mb-3 py-2 alert-compact-carrito alert-compact-carrito-success">' +
                '<div class="d-flex align-items-start">' +
                '<i class="fas fa-truck me-2 mt-1"></i>' +
                '<div>' +
                '<strong class="d-block mb-1">¡Envío gratis!</strong>' +
                '<small>Tu compra supera los $' + montoMinimoGratis.toLocaleString('es-AR') + ' en CABA y GBA</small>' +
                '</div>' +
                '</div>' +
                '</div>';
        } else if (datos.monto_faltante > 0) {
            alertHTML = '<div class="alert alert-info mb-3 py-2 alert-compact-carrito alert-compact-carrito-info">' +
                '<div class="d-flex align-items-start">' +
                '<i class="fas fa-truck me-2 mt-1"></i>' +
                '<div>' +
                '<strong class="d-block mb-1">¡Agrega $' + datos.monto_faltante.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' más y obtén envío gratis!</strong>' +
                '<small>En compras superiores a $' + montoMinimoGratis.toLocaleString('es-AR') + ' en CABA y GBA</small>' +
                '</div>' +
                '</div>' +
                '</div>';
        } else {
            alertHTML = '<div class="alert alert-warning mb-3 py-2 alert-compact-carrito alert-compact-carrito-warning">' +
                '<div class="d-flex align-items-start">' +
                '<i class="fas fa-info-circle me-2 mt-1"></i>' +
                '<div>' +
                '<strong class="d-block mb-1">Envío desde $' + costoCabaGba.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong>' +
                '<small>Gratis en compras superiores a $' + montoMinimoGratis.toLocaleString('es-AR') + ' en CABA y GBA</small>' +
                '</div>' +
                '</div>' +
                '</div>';
        }
        alertEl.innerHTML = alertHTML;
    }
    
    // Actualizar total estimado
    const totalEl = document.getElementById('carrito-total');
    if (totalEl && datos.total_estimado !== undefined) {
        if (datos.info_envio && datos.info_envio.es_gratis) {
            totalEl.innerHTML = '<h6 class="text-primary mb-0 fw-bold">$' + datos.subtotal.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</h6>';
        } else {
            totalEl.innerHTML = '<div class="text-end">' +
                '<h6 class="text-primary mb-0 fw-bold">$' + datos.total_estimado.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '*</h6>' +
                '<small class="text-muted text-tiny">*Incluye envío desde $' + costoCabaGba.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</small>' +
                '</div>';
        }
    }
}

// Agregar event listener a inputs de cantidad para actualización manual
document.addEventListener('DOMContentLoaded', function() {
    const cantidadInputs = document.querySelectorAll('input[name="cantidad"]');
    cantidadInputs.forEach(input => {
        // Remover el onchange inline y agregar event listener
        input.removeAttribute('onchange');
        input.addEventListener('change', function() {
            const form = this.closest('form');
            if (form) {
                actualizarCantidadAjax(form);
            }
        });
    });
    
    // Event listeners para botones de cantidad (reemplaza onclick inline)
    document.querySelectorAll('.btn-cantidad-carrito[data-index][data-action]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const index = parseInt(this.getAttribute('data-index'));
            const action = this.getAttribute('data-action');
            const cambio = action === 'increment' ? 1 : -1;
            
            if (typeof updateCantidad === 'function') {
                updateCantidad(index, cambio);
            }
        });
    });
    
    // Event listener para formulario de vaciar carrito (reemplaza onclick inline)
    const formVaciarCarrito = document.getElementById('form-vaciar-carrito');
    if (formVaciarCarrito) {
        formVaciarCarrito.addEventListener('submit', function(e) {
            if (!confirm('¿Estás seguro de vaciar el carrito?')) {
                e.preventDefault();
                return false;
            }
        });
    }
});

