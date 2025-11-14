/**
 * ========================================================================
 * VALIDACIÓN DE FORMULARIO DE CONTACTO - Tienda Seda y Lino
 * ========================================================================
 * Valida campos requeridos, formato de email y muestra feedback
 * 
 * @package TiendaSedaYLino
 * @version 2.0
 * ========================================================================
 */
(function() {
    'use strict';
    
    // Función para obtener referencias a los elementos del formulario
    function obtenerElementosFormulario() {
        const contactForm = document.querySelector('.formulario');
        if (!contactForm) return null;
        
        return {
            form: contactForm,
            nameInput: contactForm.querySelector('#name'),
            emailInput: contactForm.querySelector('#email'),
            asuntoSelect: contactForm.querySelector('#asunto'),
            messageTextarea: contactForm.querySelector('#message'),
            submitBtn: contactForm.querySelector('button[type="submit"]')
        };
    }
    
    // Obtener elementos del formulario
    let elementos = obtenerElementosFormulario();
    if (!elementos) return;
    
    const { form: contactForm, nameInput, emailInput, asuntoSelect, messageTextarea, submitBtn } = elementos;
    
    // Validar que los elementos existan
    if (!nameInput || !emailInput || !asuntoSelect || !messageTextarea || !submitBtn) {
        return;
    }
    
    const btnText = submitBtn.querySelector('.button-text');
    const btnLoading = submitBtn.querySelector('.button-loading');
    
    // Validar elementos del botón
    if (!btnText || !btnLoading) {
        return;
    }
    
    /**
     * NOTA: validateEmail() está disponible en common_js_functions.php
     * Se incluye globalmente en footer.php, usar directamente validateEmail()
     */
    
    /**
     * Valida que el texto no contenga caracteres peligrosos
     * @param {string} texto - Texto a validar
     * @returns {boolean} true si el texto es válido
     */
    function validarCaracteres(texto) {
        // Permitir letras, números, espacios, acentos, signos de puntuación básicos
        // Incluir # para números de pedido
        // Excluir: < > { } [ ] | \ / & caracteres de control
        const re = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ0-9\s.,;:!?¡¿\-()"'@#]+$/;
        return re.test(texto);
    }
    
    /**
     * Función auxiliar para mostrar mensaje de error en un campo
     * @param {HTMLElement} campo - Campo a marcar como inválido
     * @param {string} mensaje - Mensaje de error a mostrar
     */
    function mostrarError(campo, mensaje) {
        campo.classList.remove('is-valid');
        campo.classList.add('is-invalid');
        campo.setCustomValidity(mensaje);
        
        // Remover mensaje de error existente si hay uno
        const feedbackExistente = campo.parentElement.querySelector('.invalid-feedback');
        if (feedbackExistente) {
            feedbackExistente.remove();
        }
        
        // Crear y agregar mensaje de error
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = mensaje;
        campo.parentElement.appendChild(feedback);
    }
    
    /**
     * Función auxiliar para limpiar error de un campo
     * @param {HTMLElement} campo - Campo a limpiar
     */
    function limpiarError(campo) {
        campo.classList.remove('is-invalid', 'is-valid');
        campo.setCustomValidity('');
        
        // Remover mensaje de error si existe
        const feedback = campo.parentElement.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.remove();
        }
    }
    
    /**
     * Valida todos los campos del formulario antes del envío
     * Combina validación HTML5 nativa con validación JavaScript personalizada
     * @returns {boolean} true si todos los campos son válidos, false en caso contrario
     */
    function validarFormulario() {
        let esValido = true;
        
        // Limpiar errores previos
        limpiarError(nameInput);
        limpiarError(emailInput);
        limpiarError(asuntoSelect);
        limpiarError(messageTextarea);
        
        // Validar campo Nombre - HTML5 + JavaScript
        const nombreValor = nameInput.value.trim();
        if (!nameInput.checkValidity()) {
            // Validación HTML5 falló
            const mensaje = nameInput.validationMessage || 'El nombre es requerido';
            mostrarError(nameInput, mensaje);
            esValido = false;
        } else if (nombreValor.length < 4) {
            // Validación JavaScript personalizada (mínimo 4 letras)
            mostrarError(nameInput, 'El nombre debe tener al menos 4 letras');
            esValido = false;
        }
        
        // Validar campo Email - HTML5 + JavaScript
        const emailValor = emailInput.value.trim();
        if (!emailInput.checkValidity()) {
            // Validación HTML5 falló (required o formato email)
            const mensaje = emailInput.validationMessage || 'Ingresa un email válido';
            mostrarError(emailInput, mensaje);
            esValido = false;
        } else if (typeof validateEmail === 'function' && !validateEmail(emailValor)) {
            // Validación JavaScript adicional con función personalizada
            mostrarError(emailInput, 'Ingresa un email válido');
            esValido = false;
        }
        
        // Validar campo Asunto - HTML5
        if (!asuntoSelect.checkValidity()) {
            // Validación HTML5 falló (required)
            const mensaje = asuntoSelect.validationMessage || 'Selecciona un asunto';
            mostrarError(asuntoSelect, mensaje);
            esValido = false;
        }
        
        // Validar campo Mensaje - HTML5 + JavaScript
        const mensajeValor = messageTextarea.value.trim();
        if (!messageTextarea.checkValidity()) {
            // Validación HTML5 falló
            const mensaje = messageTextarea.validationMessage || 'El mensaje es requerido';
            mostrarError(messageTextarea, mensaje);
            esValido = false;
        } else if (mensajeValor.length <= 20) {
            // Validación JavaScript personalizada (más de 20 caracteres)
            mostrarError(messageTextarea, 'Por favor, detalle un poco más el problema');
            esValido = false;
        }
        
        return esValido;
    }
    
    // Validación en tiempo real - limpiar errores cuando el usuario escribe
    emailInput.addEventListener('input', function() {
        limpiarError(this);
    });
    
    nameInput.addEventListener('input', function() {
        limpiarError(this);
    });
    
    messageTextarea.addEventListener('input', function() {
        limpiarError(this);
    });
    
    asuntoSelect.addEventListener('change', function() {
        limpiarError(this);
    });
    
    /**
     * Manejo del envío del formulario
     * Valida todos los campos antes de permitir el envío
     */
    contactForm.addEventListener('submit', function(e) {
        // Validar formulario antes del envío
        if (!validarFormulario()) {
            // Prevenir envío si la validación falla
            e.preventDefault();
            e.stopPropagation();
            
            // Hacer scroll al primer campo con error
            const primerError = contactForm.querySelector('.is-invalid');
            if (primerError) {
                primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                primerError.focus();
            }
            
            return false;
        }
        
        // Si la validación pasa, permitir envío y mostrar estado de carga
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');
        submitBtn.disabled = true;
    });
    
    /**
     * Limpia validaciones visuales de campos pre-llenados
     * Mantiene compatibilidad con campos pre-llenados desde PHP/URL
     * La validación real se realizará al intentar enviar el formulario
     */
    function validarCamposPrellenados() {
        // Limpiar cualquier validación visual previa
        // Esto permite que los campos pre-llenados no muestren errores inicialmente
        // La validación se ejecutará cuando el usuario intente enviar el formulario
        if (nameInput) {
            limpiarError(nameInput);
        }
        if (emailInput) {
            limpiarError(emailInput);
        }
        if (asuntoSelect) {
            limpiarError(asuntoSelect);
        }
        if (messageTextarea) {
            limpiarError(messageTextarea);
        }
    }
    
    /**
     * Pre-llenar formulario desde parámetros URL (fallback si PHP no lo hace)
     * Solo pre-llena campos que estén vacíos para no sobrescribir valores de PHP
     */
    function prellenarDesdeURL() {
        // Obtener parámetros de la URL
        const urlParams = new URLSearchParams(window.location.search);
        const hashParams = new URLSearchParams(window.location.hash.split('?')[1] || '');
        
        // Combinar parámetros de URL y hash
        const asuntoParam = urlParams.get('asunto') || hashParams.get('asunto');
        const pedidoParam = urlParams.get('pedido') || hashParams.get('pedido');
        
        // Pre-llenar asunto si está vacío y hay parámetro
        if (asuntoParam && !asuntoSelect.value) {
            asuntoSelect.value = asuntoParam;
            // Disparar evento change para activar validación visual
            asuntoSelect.dispatchEvent(new Event('change'));
        }
        
        // Pre-llenar mensaje si está vacío y hay parámetro de pedido
        if (pedidoParam && !messageTextarea.value.trim()) {
            const numeroPedido = parseInt(pedidoParam, 10);
            if (numeroPedido > 0) {
                messageTextarea.value = `Hola, quiero realizar una consulta por el pedido #${numeroPedido}`;
                // Disparar evento input para activar validación visual
                messageTextarea.dispatchEvent(new Event('input'));
            }
        }
        
        // Validar campos pre-llenados después de cualquier pre-llenado
        validarCamposPrellenados();
    }
    
    // Ejecutar pre-llenado y validación cuando el DOM esté listo
    function inicializarFormulario() {
        // Re-verificar que los elementos existan
        elementos = obtenerElementosFormulario();
        if (!elementos) return;
        
        // Primero validar campos pre-llenados por PHP
        validarCamposPrellenados();
        
        // Luego pre-llenar desde URL si es necesario
        prellenarDesdeURL();
        
        // Validar nuevamente después de pre-llenar
        setTimeout(function() {
            validarCamposPrellenados();
        }, 100);
    }
    
    // Ejecutar cuando el DOM esté completamente listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(inicializarFormulario, 100);
        });
    } else {
        // DOM ya está listo
        setTimeout(inicializarFormulario, 100);
    }
    
    // Los errores se limpian automáticamente con las validaciones en tiempo real
})();

