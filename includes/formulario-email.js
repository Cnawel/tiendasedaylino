/**
 * ========================================================================
 * VALIDACIÓN DE FORMULARIO DE CONTACTO EMAIL (MAILGUN) - Tienda Seda y Lino
 * ========================================================================
 * Valida campos requeridos, formato de email y muestra feedback
 * Adaptado para el formulario-email con IDs únicos
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */
(function() {
    'use strict';
    
    // Función para obtener referencias a los elementos del formulario
    function obtenerElementosFormulario() {
        const contactForm = document.querySelector('.formulario-email');
        if (!contactForm) return null;
        
        return {
            form: contactForm,
            nameInput: contactForm.querySelector('#name-email'),
            emailInput: contactForm.querySelector('#email-email'),
            asuntoSelect: contactForm.querySelector('#asunto-email'),
            messageTextarea: contactForm.querySelector('#message-email'),
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
    
    // Validación en tiempo real del email
    emailInput.addEventListener('input', function() {
        if (this.value.trim() === '') {
            this.classList.remove('is-valid', 'is-invalid');
        } else if (typeof validateEmail === 'function' && validateEmail(this.value)) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
        }
    });
    
    // Validación en tiempo real del nombre
    nameInput.addEventListener('input', function() {
        const value = this.value.trim();
        if (value === '') {
            this.classList.remove('is-valid', 'is-invalid');
            this.setCustomValidity('');
        } else if (!validarCaracteres(value)) {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
            this.setCustomValidity('El nombre contiene caracteres no permitidos.');
        } else {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
            this.setCustomValidity('');
        }
    });
    
    // Validación en tiempo real del mensaje
    messageTextarea.addEventListener('input', function() {
        const value = this.value.trim();
        if (value === '') {
            this.classList.remove('is-valid', 'is-invalid');
            this.setCustomValidity('');
        } else if (!validarCaracteres(value)) {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
            this.setCustomValidity('El mensaje contiene caracteres no permitidos.');
        } else {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
            this.setCustomValidity('');
        }
    });
    
    // Validar mensaje inmediatamente si ya tiene valor al cargar
    // Esto es importante para campos pre-llenados por PHP
    if (messageTextarea.value && messageTextarea.value.trim().length > 0) {
        const messageValue = messageTextarea.value.trim();
        if (validarCaracteres(messageValue)) {
            messageTextarea.classList.remove('is-invalid');
            messageTextarea.classList.add('is-valid');
            messageTextarea.setCustomValidity('');
        }
    }
    
    /**
     * Manejo del envío del formulario
     * Permite envío sin validaciones (admite campos vacíos)
     */
    contactForm.addEventListener('submit', function(e) {
        // Mostrar estado de carga
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');
        submitBtn.disabled = true;
        
        // Permitir envío del formulario (no prevenir envío)
        // El formulario se enviará a procesar-contacto-email.php incluso con campos vacíos
    });
    
    /**
     * Validar campos pre-llenados al cargar la página
     * Esto asegura que los campos pre-llenados por PHP se validen correctamente
     */
    function validarCamposPrellenados() {
        // Validar nombre si tiene valor
        if (nameInput && nameInput.value.trim()) {
            const nameValue = nameInput.value.trim();
            if (validarCaracteres(nameValue)) {
                nameInput.classList.remove('is-invalid');
                nameInput.classList.add('is-valid');
                nameInput.setCustomValidity('');
            } else {
                nameInput.classList.remove('is-valid');
                nameInput.classList.add('is-invalid');
            }
        }
        
        // Validar email si tiene valor
        if (emailInput && emailInput.value.trim()) {
            if (typeof validateEmail === 'function' && validateEmail(emailInput.value)) {
                emailInput.classList.remove('is-invalid');
                emailInput.classList.add('is-valid');
            } else {
                emailInput.classList.remove('is-valid');
                emailInput.classList.add('is-invalid');
            }
        }
        
        // Validar asunto si tiene valor
        if (asuntoSelect && asuntoSelect.value) {
            asuntoSelect.classList.remove('is-invalid');
            asuntoSelect.classList.add('is-valid');
        }
        
        // Validar mensaje si tiene valor
        // IMPORTANTE: Leer el valor directamente del textarea, no usar trim() hasta después
        if (messageTextarea) {
            const messageValue = messageTextarea.value;
            // Verificar si hay contenido (incluso con espacios/saltos de línea)
            if (messageValue && messageValue.trim().length > 0) {
                const messageTrimmed = messageValue.trim();
                if (validarCaracteres(messageTrimmed)) {
                    messageTextarea.classList.remove('is-invalid');
                    messageTextarea.classList.add('is-valid');
                    messageTextarea.setCustomValidity('');
                } else {
                    messageTextarea.classList.remove('is-valid');
                    messageTextarea.classList.add('is-invalid');
                    messageTextarea.setCustomValidity('El mensaje contiene caracteres no permitidos.');
                }
            } else {
                // Si está vacío, no hacer nada (mantener estado neutro)
                messageTextarea.classList.remove('is-valid', 'is-invalid');
                messageTextarea.setCustomValidity('');
            }
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

