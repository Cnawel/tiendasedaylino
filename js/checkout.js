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
                    setFieldValidation(e.target, true);
                    e.target.setCustomValidity('');
                } else {
                    setFieldValidation(e.target, false);
                    if (!isValid) {
                        e.target.setCustomValidity('Solo se permiten números y símbolos (+, -, paréntesis, espacios)');
                    } else if (valueLength < 6) {
                        e.target.setCustomValidity('El teléfono debe tener al menos 6 caracteres');
                    } else if (valueLength > 20) {
                        e.target.setCustomValidity('El teléfono no puede exceder 20 caracteres');
                    }
                }
            } else {
                setFieldValidation(e.target, null);
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
                    setFieldValidation(e.target, false);
                    e.target.setCustomValidity('Solo se permiten números y símbolos (+, -, paréntesis, espacios)');
                } else if (valueLength < 6) {
                    setFieldValidation(e.target, false);
                    e.target.setCustomValidity('El teléfono debe tener al menos 6 caracteres');
                } else if (valueLength > 20) {
                    setFieldValidation(e.target, false);
                    e.target.setCustomValidity('El teléfono no puede exceder 20 caracteres');
                } else {
                    setFieldValidation(e.target, true);
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
    
    // Validación de dirección (calle)
    const direccionCalleInput = document.getElementById('direccion_calle');
    if (direccionCalleInput) {
        const direccionCallePattern = /^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]+$/;
        
        direccionCalleInput.addEventListener('blur', function() {
            const valor = this.value.trim();
            if (!valor) {
                this.setCustomValidity('La dirección es requerida');
                setFieldValidation(this, false);
            } else if (valor.length < 2) {
                this.setCustomValidity('La dirección debe tener al menos 2 caracteres');
                setFieldValidation(this, false);
            } else if (!direccionCallePattern.test(valor)) {
                this.setCustomValidity('Solo se permiten letras (incluyendo acentos), números, espacios, guiones, apóstrofes y acentos graves');
                setFieldValidation(this, false);
            } else {
                this.setCustomValidity('');
                setFieldValidation(this, true);
            }
        });
        
        direccionCalleInput.addEventListener('input', function(e) {
            // Filtrar caracteres no permitidos mientras se escribe
            const value = e.target.value;
            const validPattern = /^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]*$/;
            if (!validPattern.test(value)) {
                e.target.value = value.replace(/[^A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]/g, '');
            }
            
            // Limpiar validación mientras se escribe
            if (this.classList.contains('is-invalid')) {
                this.classList.remove('is-invalid');
                this.setCustomValidity('');
            }
        });
    }
    
    // Validación de número de dirección
    const direccionNumeroInput = document.getElementById('direccion_numero');
    if (direccionNumeroInput) {
        const direccionNumeroPattern = /^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]+$/;
        
        direccionNumeroInput.addEventListener('blur', function() {
            const valor = this.value.trim();
            if (!valor) {
                this.setCustomValidity('El número es requerido');
                setFieldValidation(this, false);
            } else if (!direccionNumeroPattern.test(valor)) {
                this.setCustomValidity('Solo se permiten letras (incluyendo acentos), números, espacios, guiones, apóstrofes y acentos graves');
                setFieldValidation(this, false);
            } else {
                this.setCustomValidity('');
                setFieldValidation(this, true);
            }
        });
        
        direccionNumeroInput.addEventListener('input', function(e) {
            // Filtrar caracteres no permitidos mientras se escribe
            const value = e.target.value;
            const validPattern = /^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]*$/;
            if (!validPattern.test(value)) {
                e.target.value = value.replace(/[^A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]/g, '');
            }
            
            // Limpiar validación mientras se escribe
            if (this.classList.contains('is-invalid')) {
                this.classList.remove('is-invalid');
                this.setCustomValidity('');
            }
        });
    }
    
    // Validación de piso/departamento (opcional)
    const direccionPisoInput = document.getElementById('direccion_piso');
    if (direccionPisoInput) {
        const direccionPisoPattern = /^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]+$/;
        
        direccionPisoInput.addEventListener('blur', function() {
            const valor = this.value.trim();
            // Piso/Depto es opcional, solo validar si tiene valor
            if (valor && !direccionPisoPattern.test(valor)) {
                this.setCustomValidity('Solo se permiten letras (incluyendo acentos), números, espacios, guiones, apóstrofes y acentos graves');
                setFieldValidation(this, false);
            } else {
                this.setCustomValidity('');
                setFieldValidation(this, true);
            }
        });
        
        direccionPisoInput.addEventListener('input', function(e) {
            // Filtrar caracteres no permitidos mientras se escribe
            const value = e.target.value;
            const validPattern = /^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]*$/;
            if (!validPattern.test(value)) {
                e.target.value = value.replace(/[^A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]/g, '');
            }
            
            // Limpiar validación mientras se escribe
            if (this.classList.contains('is-invalid')) {
                this.classList.remove('is-invalid');
                this.setCustomValidity('');
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
    
    // Mostrar warnings informativos según método de pago seleccionado
    const warningMetodoPago = document.getElementById('warning-metodo-pago');
    const mensajeMetodoPago = document.getElementById('mensaje-metodo-pago');
    const radiosFormaPago = document.querySelectorAll('input[name="id_forma_pago"]');
    
    /**
     * Determina el mensaje de warning según el método de pago
     * @param {string} nombreMetodo - Nombre del método de pago
     * @returns {string|null} Mensaje de warning o null si no hay warning
     */
    function obtenerMensajeMetodoPago(nombreMetodo) {
        if (!nombreMetodo) return null;
        
        const nombreLower = nombreMetodo.toLowerCase();
        
        // Detectar métodos que requieren aprobación manual
        if (nombreLower.includes('transferencia') || nombreLower.includes('depósito') || 
            nombreLower.includes('efectivo') || nombreLower.includes('manual')) {
            return 'Tu pago será revisado manualmente. Recibirás confirmación por email en 24-48 horas.';
        }
        
        // Detectar métodos con tiempo de procesamiento específico
        if (nombreLower.includes('transferencia') || nombreLower.includes('depósito')) {
            return 'Los pagos por transferencia pueden tardar 24-48hs en procesarse.';
        }
        
        // Métodos de pago inmediato (tarjeta, etc.) no requieren warning
        return null;
    }
    
    /**
     * Actualiza el warning del método de pago según la selección
     */
    function actualizarWarningMetodoPago() {
        if (!warningMetodoPago || !mensajeMetodoPago) return;
        
        const radioSeleccionado = document.querySelector('input[name="id_forma_pago"]:checked');
        
        if (radioSeleccionado) {
            const nombreMetodo = radioSeleccionado.getAttribute('data-forma-pago-nombre');
            const mensaje = obtenerMensajeMetodoPago(nombreMetodo);
            
            if (mensaje) {
                mensajeMetodoPago.textContent = mensaje;
                warningMetodoPago.classList.remove('d-none');
            } else {
                warningMetodoPago.classList.add('d-none');
            }
        } else {
            warningMetodoPago.classList.add('d-none');
        }
    }
    
    // Agregar event listeners a todos los radios de método de pago
    if (radiosFormaPago.length > 0) {
        radiosFormaPago.forEach(function(radio) {
            radio.addEventListener('change', actualizarWarningMetodoPago);
        });
        
        // Mostrar warning inicial si hay un método seleccionado por defecto
        actualizarWarningMetodoPago();
    }
});

