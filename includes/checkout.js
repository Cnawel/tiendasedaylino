// Validación del formulario antes de enviar
document.addEventListener('DOMContentLoaded', function() {
    const formCheckout = document.getElementById('formCheckout');
    
    if (formCheckout) {
        formCheckout.addEventListener('submit', function(e) {
            // Validar que se haya seleccionado una provincia válida
            const provinciaSelect = document.getElementById('provincia');
            if (provinciaSelect && provinciaSelect.value === '') {
                e.preventDefault();
                alert('Por favor, selecciona una provincia');
                provinciaSelect.focus();
                return false;
            }
            
            // Validar que todos los campos requeridos estén completos
            const requiredFields = formCheckout.querySelectorAll('[required]');
            let isValid = true;
            let firstInvalidField = null;
            
            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    isValid = false;
                    if (!firstInvalidField) {
                        firstInvalidField = field;
                    }
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                if (firstInvalidField) {
                    firstInvalidField.focus();
                    firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                alert('Por favor, completa todos los campos obligatorios');
                return false;
            }
            
            // Mostrar indicador de carga
            const submitButton = formCheckout.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
            }
        });
    }
    
    // Validación en tiempo real del campo teléfono
    const telefonoInput = document.getElementById('telefono');
    
    if (telefonoInput) {
        // Validar al escribir
        telefonoInput.addEventListener('input', function(e) {
            const value = e.target.value;
            // Solo permitir números, espacios y símbolos: +, -, (, )
            const validPattern = /^[0-9+\-() ]*$/;
            
            if (!validPattern.test(value)) {
                // Remover caracteres no permitidos
                e.target.value = value.replace(/[^0-9+\-() ]/g, '');
            }
            
            // Validar formato y longitudes según diccionario: 6-20 caracteres
            const valueLength = e.target.value.length;
            if (valueLength > 0) {
                const isValid = /^[0-9+\-() ]+$/.test(e.target.value);
                if (isValid && valueLength >= 6 && valueLength <= 20) {
                    e.target.classList.remove('is-invalid');
                    e.target.classList.add('is-valid');
                    e.target.setCustomValidity('');
                } else {
                    e.target.classList.remove('is-valid');
                    e.target.classList.add('is-invalid');
                    if (!isValid) {
                        e.target.setCustomValidity('Solo se permiten números y símbolos (+, -, paréntesis, espacios)');
                    } else if (valueLength < 6) {
                        e.target.setCustomValidity('El teléfono debe tener al menos 6 caracteres');
                    } else if (valueLength > 20) {
                        e.target.setCustomValidity('El teléfono no puede exceder 20 caracteres');
                    }
                }
            } else {
                e.target.classList.remove('is-valid', 'is-invalid');
                e.target.setCustomValidity('');
            }
        });
        
        // Validar al perder el foco
        telefonoInput.addEventListener('blur', function(e) {
            const value = e.target.value.trim();
            if (value.length > 0) {
                const isValid = /^[0-9+\-() ]+$/.test(value);
                const valueLength = value.length;
                if (!isValid) {
                    e.target.classList.add('is-invalid');
                    e.target.setCustomValidity('Solo se permiten números y símbolos (+, -, paréntesis, espacios)');
                } else if (valueLength < 6) {
                    e.target.classList.add('is-invalid');
                    e.target.setCustomValidity('El teléfono debe tener al menos 6 caracteres');
                } else if (valueLength > 20) {
                    e.target.classList.add('is-invalid');
                    e.target.setCustomValidity('El teléfono no puede exceder 20 caracteres');
                } else {
                    e.target.classList.remove('is-invalid');
                    e.target.setCustomValidity('');
                }
            }
        });
    }
    
    // Validación del código postal usando función consolidada
    const codigoPostalInput = document.getElementById('codigo_postal');
    if (codigoPostalInput) {
        codigoPostalInput.addEventListener('input', function(e) {
            // Usar función consolidada de common_js_functions.php
            validarCodigoPostal(e.target);
        });
    }
    
    // Actualizar costo de envío cuando cambia la provincia
    const provinciaSelect = document.getElementById('provincia');
    const cardBody = document.querySelector('.card-body[data-subtotal]');
    
    if (provinciaSelect && cardBody) {
        // Leer atributos data usando getAttribute (más confiable para atributos con múltiples guiones)
        const montoMinimoGratis = parseFloat(cardBody.getAttribute('data-monto-minimo-gratis')) || 80000;
        const costoCabaGba = parseFloat(cardBody.getAttribute('data-costo-caba-gba')) || 10000;
        const costoArgentina = parseFloat(cardBody.getAttribute('data-costo-argentina')) || 15000;
        
        /**
         * Verifica si una provincia corresponde a CABA o Buenos Aires
         * Solo se considera la provincia, la localidad NO es relevante para el cálculo
         * @param {string} provincia - Nombre de la provincia
         * @returns {boolean} True si es CABA o Buenos Aires
         */
        function esCABAGBA(provincia) {
            if (!provincia) return false;
            
            const provinciaNormalizada = provincia.trim().toUpperCase();
            
            // Si la provincia es directamente CABA
            if (provinciaNormalizada === 'CABA') {
                return true;
            }
            
            // Verificar que sea provincia de Buenos Aires
            if (provinciaNormalizada === 'BUENOS AIRES') {
                return true;
            }
            
            return false;
        }
        
        /**
         * Calcula el costo de envío según la provincia seleccionada
         * Solo se considera la provincia, la localidad NO es relevante
         * @param {string} provincia - Nombre de la provincia
         * @param {number} subtotal - Subtotal del pedido
         * @returns {object} Objeto con costo, es_gratis y mensaje
         */
        function calcularEnvioDinamico(provincia, subtotal) {
            // Verificar si es CABA o Buenos Aires
            const esCabaGba = esCABAGBA(provincia);
            
            if (esCabaGba) {
                // CABA o Buenos Aires: gratis si supera $80,000, sino $10,000
                if (subtotal >= montoMinimoGratis) {
                    return {
                        costo: 0,
                        es_gratis: true,
                        mensaje: 'GRATIS'
                    };
                } else {
                    return {
                        costo: costoCabaGba,
                        es_gratis: false,
                        mensaje: '$' + costoCabaGba.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    };
                }
            } else {
                // Resto de Argentina: siempre $15,000
                return {
                    costo: costoArgentina,
                    es_gratis: false,
                    mensaje: '$' + costoArgentina.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                };
            }
        }
        
        /**
         * Actualiza la interfaz con el nuevo costo de envío
         * @param {object} infoEnvio - Información del envío calculado
         * @param {number} subtotal - Subtotal del pedido
         */
        function actualizarEnvioUI(infoEnvio, subtotal) {
            const envioCostoEl = document.getElementById('envio-costo');
            const totalPedidoEl = document.getElementById('total-pedido');
            const envioAlertEl = document.getElementById('envio-alert');
            
            if (!envioCostoEl || !totalPedidoEl || !envioAlertEl) {
                return;
            }
            
            // Actualizar costo de envío
            if (infoEnvio.es_gratis) {
                envioCostoEl.innerHTML = '<span class="text-success fw-bold">GRATIS</span>';
            } else {
                envioCostoEl.innerHTML = '<strong>$' + infoEnvio.costo.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong>';
            }
            
            // Calcular y actualizar total
            const totalConEnvio = subtotal + infoEnvio.costo;
            totalPedidoEl.textContent = '$' + totalConEnvio.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            // Actualizar alertas de envío
            let alertHTML = '';
            const provinciaActual = provinciaSelect.value;
            const esCabaGba = esCABAGBA(provinciaActual);
            
            if (infoEnvio.es_gratis) {
                alertHTML = '<div class="alert alert-success mb-3 alert-compact">' +
                    '<i class="fas fa-truck me-2"></i>' +
                    '<strong>¡Envío gratis!</strong><br>' +
                    '<small>Tu compra supera los $' + montoMinimoGratis.toLocaleString('es-AR') + ' en CABA y GBA</small>' +
                    '</div>';
            } else if (esCabaGba) {
                const montoFaltante = montoMinimoGratis - subtotal;
                if (montoFaltante > 0) {
                    alertHTML = '<div class="alert alert-info mb-3 alert-compact">' +
                        '<i class="fas fa-truck me-2"></i>' +
                        '<strong>¡Agrega $' + montoFaltante.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' más y obtén envío gratis!</strong><br>' +
                        '<small>En compras superiores a $' + montoMinimoGratis.toLocaleString('es-AR') + ' en CABA y GBA</small>' +
                        '</div>';
                }
            }
            envioAlertEl.innerHTML = alertHTML;
        }
        
        /**
         * Recalcula y actualiza el costo de envío basado en la provincia seleccionada
         */
        function recalcularEnvio() {
            const provinciaSeleccionada = provinciaSelect.value;
            
            if (provinciaSeleccionada) {
                // Leer subtotal dinámicamente del DOM cada vez que se recalcula
                const subtotal = parseFloat(cardBody.getAttribute('data-subtotal')) || 0;
                const infoEnvio = calcularEnvioDinamico(provinciaSeleccionada, subtotal);
                actualizarEnvioUI(infoEnvio, subtotal);
            }
        }
        
        // Event listener para cambios en la provincia
        provinciaSelect.addEventListener('change', recalcularEnvio);
        
        // Calcular envío inicial al cargar la página
        recalcularEnvio();
    }
});

