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
            } else {
                motivoRechazoContainer.style.display = 'none';
            }
        }
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
});



