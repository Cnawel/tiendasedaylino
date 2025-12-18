/**
 * ========================================================================
 * VENTAS - JavaScript para panel de ventas
 * ========================================================================
 * Funciones JavaScript para el panel de ventas
 * Usa funciones de common_js_functions.php (mostrarErrorCampo, limpiarErrorCampo, scrollToFirstError)
 * ========================================================================
 */

/**
 * Función para toggle de pedidos inactivos
 * @param {boolean} mostrar - true para mostrar inactivos, false para ocultar
 */
function togglePedidosInactivos(mostrar) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('tab', 'pedidos');
    
    if (mostrar) {
        urlParams.set('mostrar_inactivos', '1');
    } else {
        urlParams.delete('mostrar_inactivos');
    }
    
    window.location.href = 'ventas.php?' + urlParams.toString();
}

/**
 * Función para toggle de métodos de pago inactivos
 * @param {boolean} mostrar - true para mostrar inactivos, false para ocultar
 */
function toggleMetodosPagoInactivos(mostrar) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('tab', 'metodos-pago');
    
    if (mostrar) {
        urlParams.set('mostrar_metodos_inactivos', '1');
    } else {
        urlParams.delete('mostrar_metodos_inactivos');
    }
    
    window.location.href = 'ventas.php?' + urlParams.toString();
}

/**
 * Función para cambiar límite de pedidos mostrados
 * @param {string} limite - Valor del límite ('10', '50', 'TODOS')
 */
function cambiarLimitePedidos(limite) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('tab', 'pedidos');
    urlParams.set('limite', limite);
    window.location.href = 'ventas.php?' + urlParams.toString();
}

document.addEventListener('DOMContentLoaded', function() {
    // ========================================================================
    // Función para manejar la visibilidad de campos según el estado del pago
    // ========================================================================
    function toggleCamposPago(pedidoId) {
        const selectEstadoPago = document.getElementById('nuevo_estado_pago_' + pedidoId);
        const codigoPagoContainer = document.getElementById('codigo_pago_container_' + pedidoId);
        const motivoRechazoContainer = document.getElementById('motivo_rechazo_container_' + pedidoId);
        
        if (!selectEstadoPago) {
            return; // Select no encontrado, salir
        }
        
        const estadoSeleccionado = selectEstadoPago.value;
        // Si no hay selección, usar el estado actual (valor del option selected)
        const estadoActual = estadoSeleccionado || (selectEstadoPago.options[selectEstadoPago.selectedIndex]?.value || '');
        const estadoFinal = estadoSeleccionado || estadoActual;
        
        // Deshabilitar SELECT si el pago está aprobado (no puede cambiar de estado)
        // También verificar si ya está deshabilitado desde PHP (pedido completado)
        if (estadoFinal === 'aprobado' || selectEstadoPago.disabled) {
            selectEstadoPago.disabled = true;
        }
        
        // Mostrar/ocultar código de pago cuando se aprueba o ya está aprobado
        // Nota: El código de pago es solo lectura (llenado por el cliente), solo se muestra si existe
        if (codigoPagoContainer) {
            if (estadoFinal === 'aprobado') {
                codigoPagoContainer.style.display = 'block';
            } else {
                codigoPagoContainer.style.display = 'none';
            }
        }
        
        // Mostrar/ocultar motivo de rechazo cuando se rechaza
        if (motivoRechazoContainer) {
            if (estadoFinal === 'rechazado') {
                motivoRechazoContainer.style.display = 'block';
                // Inicializar validación de motivo de rechazo
                inicializarValidacionMotivoRechazo(pedidoId);
            } else {
                motivoRechazoContainer.style.display = 'none';
            }
        }
    }
    
    /**
     * Inicializa validación de motivo de rechazo (máximo 500 caracteres según diccionario)
     */
    function inicializarValidacionMotivoRechazo(pedidoId) {
        const textareaMotivo = document.getElementById('motivo_rechazo_' + pedidoId);
        const textareaMotivoModal = document.getElementById('motivo_rechazo_modal_' + pedidoId);
        const contadorMotivo = document.getElementById('contador_motivo_rechazo_' + pedidoId);
        const contadorMotivoModal = document.getElementById('contador_motivo_rechazo_modal_' + pedidoId);
        
        function configurarValidacion(textarea, contador) {
            if (!textarea) return;
            
            function actualizarContador() {
                if (contador) {
                    const longitud = textarea.value.length;
                    contador.textContent = `${longitud}/500 caracteres`;
                    if (longitud > 500) {
                        contador.classList.add('text-danger');
                        contador.classList.remove('text-muted');
                    } else {
                        contador.classList.remove('text-danger');
                        contador.classList.add('text-muted');
                    }
                }
            }
            
            textarea.addEventListener('input', function() {
                actualizarContador();
                if (this.value.length > 500) {
                    this.classList.add('is-invalid');
                    if (this.setCustomValidity) {
                        this.setCustomValidity('El motivo de rechazo no puede exceder 500 caracteres');
                    }
                } else {
                    this.classList.remove('is-invalid');
                    if (this.setCustomValidity) {
                        this.setCustomValidity('');
                    }
                }
            });
            
            textarea.addEventListener('blur', function() {
                if (this.value.length > 500) {
                    this.classList.add('is-invalid');
                    if (this.setCustomValidity) {
                        this.setCustomValidity('El motivo de rechazo no puede exceder 500 caracteres');
                    }
                } else {
                    this.classList.remove('is-invalid');
                    if (this.setCustomValidity) {
                        this.setCustomValidity('');
                    }
                }
            });
            
            actualizarContador();
        }
        
        configurarValidacion(textareaMotivo, contadorMotivo);
        configurarValidacion(textareaMotivoModal, contadorMotivoModal);
    }
    
    // Inicializar para todos los modales al cargar la página
    const modalesEstado = document.querySelectorAll('[id^="editarEstadoModal"]');
    modalesEstado.forEach(function(modal) {
        const pedidoId = modal.id.replace('editarEstadoModal', '');
        const selectEstadoPago = document.getElementById('nuevo_estado_pago_' + pedidoId);
        
        if (selectEstadoPago) {
            // Ejecutar al cargar (para estado inicial)
            toggleCamposPago(pedidoId);
            
            // Ejecutar cuando cambia el select
            selectEstadoPago.addEventListener('change', function() {
                toggleCamposPago(pedidoId);
            });
        }
    });
    
    // También ejecutar cuando se abre el modal (por si acaso)
    modalesEstado.forEach(function(modal) {
        modal.addEventListener('shown.bs.modal', function() {
            const pedidoId = modal.id.replace('editarEstadoModal', '');
            toggleCamposPago(pedidoId);
        });
    });
    
    // ========================================================================
    // Validación de descripción de método de pago
    // ========================================================================
    /**
     * Valida la descripción del método de pago según el patrón del backend
     * Hace trim() antes de validar para asegurar consistencia con el backend
     * Patrón permitido: letras (A-Z, a-z con tildes y diéresis: á, é, í, ó, ú, ñ, ü), números (0-9), espacios, puntos (.), comas (,), dos puntos (:), guiones (-), comillas simples (')
     * @param {string} descripcion - Texto a validar
     * @return {boolean} - true si es válido, false si no
     */
    function validarDescripcionMetodoPago(descripcion) {
        // Hacer trim() igual que el backend (línea 414 de sales_functions.php)
        const descripcionTrimmed = descripcion ? descripcion.trim() : '';
        
        // Si está vacía después del trim, es válida (es opcional)
        if (descripcionTrimmed === '') {
            return true;
        }
        
        // Validar caracteres permitidos: letras (con tildes y diéresis), números, espacios, puntos, comas, dos puntos, guiones, comillas simples
        const patron = /^[A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9\s\.,\:\-\']+$/;
        return patron.test(descripcionTrimmed);
    }
    
    /**
     * Valida y muestra/oculta el mensaje de error para el campo descripción
     * Usa funciones consolidadas de common_js_functions.php
     * @param {HTMLElement} textarea - Elemento textarea a validar
     * @param {HTMLElement} errorDiv - Elemento div donde mostrar el error
     */
    function validarCampoDescripcion(textarea, errorDiv) {
        const valor = textarea.value;
        const esValido = validarDescripcionMetodoPago(valor);
        
        if (!esValido && valor.trim() !== '') {
            // Mostrar error usando función consolidada
            mostrarErrorCampo(textarea, errorDiv, 'La descripción contiene caracteres no permitidos');
        } else {
            // Limpiar error usando función consolidada
            limpiarErrorCampo(textarea, errorDiv);
        }
    }
    
    // Validación para el formulario de agregar método de pago
    const descripcionNuevo = document.getElementById('descripcion_metodo_nuevo');
    const errorDescripcionNuevo = document.getElementById('error_descripcion_nuevo');
    if (descripcionNuevo) {
        // Validar al escribir
        descripcionNuevo.addEventListener('input', function() {
            validarCampoDescripcion(descripcionNuevo, errorDescripcionNuevo);
        });
        
        // Normalizar valor en blur (aplicar trim automáticamente)
        descripcionNuevo.addEventListener('blur', function() {
            const valorOriginal = descripcionNuevo.value;
            const valorTrimmed = valorOriginal.trim();
            if (valorOriginal !== valorTrimmed) {
                descripcionNuevo.value = valorTrimmed;
            }
            validarCampoDescripcion(descripcionNuevo, errorDescripcionNuevo);
        });
        
        // Validar antes de enviar el formulario
        const formAgregar = descripcionNuevo.closest('form');
        if (formAgregar) {
            formAgregar.addEventListener('submit', function(e) {
                // Aplicar trim antes de validar
                const valorTrimmed = descripcionNuevo.value.trim();
                if (descripcionNuevo.value !== valorTrimmed) {
                    descripcionNuevo.value = valorTrimmed;
                }
                if (!validarDescripcionMetodoPago(descripcionNuevo.value)) {
                    e.preventDefault();
                    validarCampoDescripcion(descripcionNuevo, errorDescripcionNuevo);
                    descripcionNuevo.focus();
                    return false;
                }
            });
        }
    }
    
    // Validación para los formularios de editar método de pago (múltiples modales)
    function inicializarValidacionEditar() {
        const textareasEdit = document.querySelectorAll('[id^="descripcion_metodo_edit_"]');
        textareasEdit.forEach(function(textarea) {
            // Evitar agregar listeners múltiples veces
            if (textarea.dataset.validationInitialized === 'true') {
                return;
            }
            textarea.dataset.validationInitialized = 'true';
            
            const metodoId = textarea.id.replace('descripcion_metodo_edit_', '');
            const errorDiv = document.getElementById('error_descripcion_edit_' + metodoId);
            
            // Validar al escribir
            textarea.addEventListener('input', function() {
                validarCampoDescripcion(textarea, errorDiv);
            });
            
            // Normalizar valor en blur (aplicar trim automáticamente)
            textarea.addEventListener('blur', function() {
                const valorOriginal = textarea.value;
                const valorTrimmed = valorOriginal.trim();
                if (valorOriginal !== valorTrimmed) {
                    textarea.value = valorTrimmed;
                }
                validarCampoDescripcion(textarea, errorDiv);
            });
            
            // Validar antes de enviar el formulario
            const formEdit = textarea.closest('form');
            if (formEdit) {
                formEdit.addEventListener('submit', function(e) {
                    // Aplicar trim antes de validar
                    const valorTrimmed = textarea.value.trim();
                    if (textarea.value !== valorTrimmed) {
                        textarea.value = valorTrimmed;
                    }
                    if (!validarDescripcionMetodoPago(textarea.value)) {
                        e.preventDefault();
                        validarCampoDescripcion(textarea, errorDiv);
                        textarea.focus();
                        return false;
                    }
                });
            }
        });
    }
    
    // Inicializar validación al cargar la página
    inicializarValidacionEditar();
    
    // Reinicializar cuando se abren modales de edición
    const modalesEditarMetodo = document.querySelectorAll('[id^="editarMetodoModal"]');
    modalesEditarMetodo.forEach(function(modal) {
        modal.addEventListener('shown.bs.modal', function() {
            inicializarValidacionEditar();
        });
    });
    
    // ========================================================================
    // CONFIRMACIONES PARA CAMBIOS DE ESTADO DE PAGO (Mejora Crítica)
    // ========================================================================
    
    /**
     * Intercepta el envío del formulario de editar estado
     * Muestra confirmación si se está aprobando o rechazando un pago
     */
    function inicializarConfirmacionesPago() {
        const formulariosEditarEstado = document.querySelectorAll('[id^="formEditarEstado"]');
        
        formulariosEditarEstado.forEach(function(formulario) {
            // Remover listener anterior si existe (para evitar duplicados al reinicializar)
            if (formulario.dataset.confirmacionInicializada === 'true' && formulario.dataset.submitHandler) {
                const handlerId = formulario.dataset.submitHandler;
                const oldHandler = window[handlerId];
                if (oldHandler) {
                    formulario.removeEventListener('submit', oldHandler);
                    delete window[handlerId];
                }
            }
            
            // Marcar como inicializado
            formulario.dataset.confirmacionInicializada = 'true';
            
            // Guardar referencia al handler para poder removerlo después
            const submitHandler = function(e) {
                const pedidoId = formulario.id.replace('formEditarEstado', '');
                const selectEstadoPago = document.getElementById('nuevo_estado_pago_' + pedidoId);
                const estadoPagoAnterior = formulario.querySelector('[name="estado_pago_anterior"]');
                
                if (!selectEstadoPago) {
                    return; // No hay select de estado de pago, continuar normal
                }
                
                const estadoNuevo = selectEstadoPago.value;
                const estadoActual = estadoPagoAnterior ? estadoPagoAnterior.value : '';
                
                // Si no se está cambiando el estado del pago, continuar normal
                if (!estadoNuevo || estadoNuevo === '' || estadoNuevo === estadoActual) {
                    return; // NO prevenir el submit, permitir que se envíe normalmente
                }
                
                // Detectar si se está aprobando o rechazando
                const esAprobacion = estadoNuevo === 'aprobado';
                const esRechazo = estadoNuevo === 'rechazado';
                
                // Interceptar solo cuando se está aprobando o rechazando el pago
                if (esAprobacion || esRechazo) {
                    // IMPORTANTE: Asegurar que el valor del select esté correctamente establecido
                    // antes de que el formulario se envíe
                    if (selectEstadoPago && estadoNuevo && estadoNuevo !== '') {
                        // Forzar que el select tenga el valor correcto
                        selectEstadoPago.value = estadoNuevo;
                        // Quitar temporalmente el disabled para que el valor se envie
                        selectEstadoPago.disabled = false;
                        
                        // Verificar que el valor se estableció correctamente
                        const valorVerificado = selectEstadoPago.value;
                        if (valorVerificado !== estadoNuevo) {
                            e.preventDefault();
                            alert('Error: No se pudo establecer el estado del pago. Por favor, intente nuevamente.');
                            return;
                        }
                    }
                    
                    // NO prevenir el submit - permitir que el formulario se envíe normalmente
                    // El formulario HTML enviará todos los valores correctamente incluyendo el select
                }
            };
            
            // Guardar referencia al handler en el formulario para poder removerlo después
            const handlerId = 'submitHandler_' + formulario.id;
            formulario.dataset.submitHandler = handlerId;
            window[handlerId] = submitHandler;
            
            // Agregar listener en fase de captura para detectar el evento antes que otros
            formulario.addEventListener('submit', submitHandler, true); // capture: true
        });
    }
    
    // Inicializar confirmaciones al cargar la página
    inicializarConfirmacionesPago();
    
    // ========================================================================
    // Event listeners para reemplazar onclick inline
    // ========================================================================
    
    // Botón de logout con confirmación
    const btnLogout = document.querySelector('.btn-logout');
    if (btnLogout) {
        btnLogout.addEventListener('click', function(e) {
            if (typeof confirmLogout === 'function') {
                if (!confirmLogout()) {
                    e.preventDefault();
                }
            }
        });
    }
    
    // Checkbox para mostrar pedidos inactivos
    const checkboxInactivos = document.querySelector('[data-toggle-inactivos]');
    if (checkboxInactivos) {
        checkboxInactivos.addEventListener('change', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof togglePedidosInactivos === 'function') {
                togglePedidosInactivos(this.checked);
            }
        });
    }
    
    // Checkbox para mostrar métodos de pago inactivos
    const checkboxMetodosInactivos = document.querySelector('[data-toggle-metodos-inactivos]');
    if (checkboxMetodosInactivos) {
        checkboxMetodosInactivos.addEventListener('change', function() {
            toggleMetodosPagoInactivos(this.checked);
        });
    }
    
    // Select para cambiar límite de pedidos
    const selectLimite = document.getElementById('selectLimitePedidos');
    if (selectLimite) {
        selectLimite.addEventListener('change', function() {
            cambiarLimitePedidos(this.value);
        });
    }
    
    // ========================================================================
    // Activar pestaña según parámetro URL (solo en ventas.php)
    // ========================================================================
    if (window.location.pathname.includes('ventas.php')) {
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        
        if (tabParam) {
            const tabsValidos = ['pedidos', 'clientes', 'metodos-pago', 'metricas'];
            if (tabsValidos.includes(tabParam)) {
                // Activar pestaña usando Bootstrap
                const tabButton = document.getElementById(tabParam + '-tab');
                if (tabButton && typeof bootstrap !== 'undefined') {
                    const tab = new bootstrap.Tab(tabButton);
                    tab.show();
                }
            }
        }
    }
    
    // NOTA: Se eliminó la delegación de eventos a nivel del documento para evitar conflictos
    // El listener del submit del formulario es suficiente y más confiable

    // NOTA: Se eliminó la reinicialización de listeners al abrir modales para evitar conflictos
    // El listener del submit del formulario funciona correctamente sin necesidad de reinicialización
});

/**
 * Valida el formulario de edición de estado antes de enviarlo
 * Incluye confirmación especial para cambios a estados terminales (pedido y pago)
 * @param {number} pedidoId ID del pedido
 * @return {boolean} true si puede continuar, false si se cancela
 */
function validarFormularioEstado(pedidoId) {
    const selectEstadoPedido = document.getElementById('nuevo_estado_pedido_' + pedidoId);
    const estadoAnteriorPedido = document.querySelector('#formEditarEstado' + pedidoId + ' [name="estado_anterior"]');
    const selectEstadoPago = document.getElementById('nuevo_estado_pago_' + pedidoId);
    const estadoAnteriorPago = document.querySelector('#formEditarEstado' + pedidoId + ' [name="estado_pago_anterior"]');

    // Verificar cambios en estado del pedido
    if (selectEstadoPedido && estadoAnteriorPedido) {
        const estadoNuevoPedido = selectEstadoPedido.value.toLowerCase().trim();
        const estadoActualPedido = estadoAnteriorPedido.value.toLowerCase().trim();

        // Estados terminales del pedido que requieren confirmación especial
        const estadosTerminalesPedido = ['completado', 'cancelado'];

        if (estadosTerminalesPedido.includes(estadoNuevoPedido) && estadoActualPedido !== estadoNuevoPedido) {
            const nombresEstadosPedido = {
                'completado': 'Completado',
                'cancelado': 'Cancelado'
            };

            const nombreEstadoPedido = nombresEstadosPedido[estadoNuevoPedido] || estadoNuevoPedido;

            const confirmacionPedido = confirm(
                `⚠️ ATENCIÓN: Está a punto de cambiar el PEDIDO a estado "${nombreEstadoPedido}".\n\n` +
                `Esto marcará el pedido como definitivamente ${estadoNuevoPedido === 'completado' ? 'cerrado' : 'cancelado'}.\n\n` +
                `¿Está completamente seguro de que desea continuar?\n\n` +
                `Esta acción puede afectar el inventario y no se puede deshacer fácilmente.`
            );

            if (!confirmacionPedido) {
                return false; // Cancelar el envío
            }
        }
    }

    // Verificar cambios en estado del pago
    if (selectEstadoPago && estadoAnteriorPago) {
        const estadoNuevoPago = selectEstadoPago.value.toLowerCase().trim();
        const estadoActualPago = estadoAnteriorPago.value.toLowerCase().trim();

        // Estados terminales del pago que requieren confirmación especial
        const estadosTerminalesPago = ['aprobado', 'rechazado', 'cancelado'];

        if (estadosTerminalesPago.includes(estadoNuevoPago) && estadoActualPago !== estadoNuevoPago) {
            const nombresEstadosPago = {
                'aprobado': 'Pago Aprobado',
                'rechazado': 'Pago Rechazado',
                'cancelado': 'Pago Cancelado'
            };

            const nombreEstadoPago = nombresEstadosPago[estadoNuevoPago] || estadoNuevoPago;

            let mensajeAdicional = '';
            if (estadoNuevoPago === 'aprobado') {
                mensajeAdicional = 'Esto descontará el stock del inventario y marcará el pago como definitivamente aprobado.';
            } else if (estadoNuevoPago === 'rechazado') {
                mensajeAdicional = 'Esto rechazará definitivamente el pago y podrá afectar el estado del pedido.';
            } else if (estadoNuevoPago === 'cancelado') {
                mensajeAdicional = 'Esto cancelará definitivamente el pago y podrá restaurar el stock si ya fue descontado.';
            }

            const confirmacionPago = confirm(
                `⚠️ ATENCIÓN: Está a punto de cambiar el PAGO a estado "${nombreEstadoPago}".\n\n` +
                `${mensajeAdicional}\n\n` +
                `¿Está completamente seguro de que desea continuar?\n\n` +
                `Esta acción puede afectar el inventario y el estado del pedido. No se puede deshacer fácilmente.`
            );

            if (!confirmacionPago) {
                return false; // Cancelar el envío
            }
        }
    }

    return true; // Continuar con el envío
}



