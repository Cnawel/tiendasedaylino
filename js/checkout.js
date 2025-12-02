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
                    setFieldValidation(field, false);
                } else {
                    setFieldValidation(field, null);
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                scrollToFirstError(formCheckout);
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
    // Usa la función validarTelefono de common_js_functions.php
    const telefonoInput = document.getElementById('telefono');
    
    if (telefonoInput) {
        // Filtrar caracteres no permitidos mientras se escribe
        telefonoInput.addEventListener('input', function(e) {
            const value = e.target.value;
            // Solo permitir números, espacios y símbolos: +, -, (, )
            const validPattern = /^[0-9+\-() ]*$/;
            
            if (!validPattern.test(value)) {
                // Remover caracteres no permitidos
                e.target.value = value.replace(/[^0-9+\-() ]/g, '');
            }
            
            // Limitar longitud máxima
            if (e.target.value.length > 20) {
                e.target.value = e.target.value.substring(0, 20);
            }
            
            // Limpiar validación mientras se escribe
            if (this.classList.contains('is-invalid')) {
                this.classList.remove('is-invalid');
                if (this.setCustomValidity) {
                    this.setCustomValidity('');
                }
            }
        });
        
        // Validar al perder el foco usando función consolidada
        telefonoInput.addEventListener('blur', function() {
            if (typeof validarTelefono === 'function') {
                // NOTA: Teléfono es obligatorio para coincidir con validación PHP en procesar-pedido.php
                validarTelefono(this, false);
            }
        });
    }
    
    // Validación del código postal usando función consolidada
    const codigoPostalInput = document.getElementById('codigo_postal');
    if (codigoPostalInput) {
        codigoPostalInput.addEventListener('input', function(e) {
            if (typeof validarCodigoPostal === 'function') {
                validarCodigoPostal(this, 'input', true);
            }
        });
        
        codigoPostalInput.addEventListener('blur', function() {
            if (typeof validarCodigoPostal === 'function') {
                validarCodigoPostal(this, 'blur', true);
            }
        });
    }
    
    // Validación de dirección (calle)
    // Usa la función validarDireccion de common_js_functions.php
    const direccionCalleInput = document.getElementById('direccion_calle');
    if (direccionCalleInput) {
        direccionCalleInput.addEventListener('blur', function() {
            if (typeof validarDireccion === 'function') {
                validarDireccion(this, true, 2, 'calle');
            }
        });
        
        direccionCalleInput.addEventListener('input', function(e) {
            if (typeof filtrarDireccion === 'function') {
                filtrarDireccion(this, 'calle');
            }
        });
    }
    
    // Validación de número de dirección
    // Usa la función validarDireccion de common_js_functions.php
    const direccionNumeroInput = document.getElementById('direccion_numero');
    if (direccionNumeroInput) {
        direccionNumeroInput.addEventListener('blur', function() {
            if (typeof validarDireccion === 'function') {
                validarDireccion(this, true, 1, 'numero');
            }
        });
        
        direccionNumeroInput.addEventListener('input', function(e) {
            if (typeof filtrarDireccion === 'function') {
                filtrarDireccion(this, 'numero');
            }
        });
    }
    
    // Validación de piso/departamento (opcional)
    // Usa la función validarDireccion de common_js_functions.php
    const direccionPisoInput = document.getElementById('direccion_piso');
    if (direccionPisoInput) {
        direccionPisoInput.addEventListener('blur', function() {
            if (typeof validarDireccion === 'function') {
                // Piso/Depto es opcional
                validarDireccion(this, false, 1, 'piso');
            }
        });
        
        direccionPisoInput.addEventListener('input', function(e) {
            if (typeof filtrarDireccion === 'function') {
                filtrarDireccion(this, 'piso');
            }
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
                    alertHTML = '<div class="alert alert-info mb-3 alert-compact" style="color: #000;">' +
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
    
    // Validación de observaciones (máximo 500 caracteres según diccionario)
    const observacionesTextarea = document.getElementById('observaciones');
    if (observacionesTextarea) {
        // Contador de caracteres
        const contadorObservaciones = document.createElement('small');
        contadorObservaciones.className = 'form-text text-muted mt-1';
        contadorObservaciones.id = 'contador_observaciones';
        observacionesTextarea.parentElement.appendChild(contadorObservaciones);
        
        function actualizarContador() {
            const longitud = observacionesTextarea.value.length;
            contadorObservaciones.textContent = `${longitud}/500 caracteres`;
            if (longitud > 500) {
                contadorObservaciones.classList.add('text-danger');
                contadorObservaciones.classList.remove('text-muted');
            } else {
                contadorObservaciones.classList.remove('text-danger');
                contadorObservaciones.classList.add('text-muted');
            }
        }
        
        // Actualizar contador al escribir
        observacionesTextarea.addEventListener('input', function() {
            actualizarContador();
            
            // Validar longitud máxima
            if (this.value.length > 500) {
                this.classList.add('is-invalid');
                if (this.setCustomValidity) {
                    this.setCustomValidity('Las observaciones no pueden exceder 500 caracteres');
                }
            } else {
                this.classList.remove('is-invalid');
                if (this.setCustomValidity) {
                    this.setCustomValidity('');
                }
            }
        });
        
        // Validar al perder el foco
        observacionesTextarea.addEventListener('blur', function() {
            if (this.value.length > 500) {
                this.classList.add('is-invalid');
                if (this.setCustomValidity) {
                    this.setCustomValidity('Las observaciones no pueden exceder 500 caracteres');
                }
            } else {
                this.classList.remove('is-invalid');
                if (this.setCustomValidity) {
                    this.setCustomValidity('');
                }
            }
        });
        
        // Inicializar contador
        actualizarContador();
    }
    
    // Mensaje fijo de pago manual - ya no se usa lógica dinámica
    // El mensaje ahora es fijo y siempre visible en el HTML
});

