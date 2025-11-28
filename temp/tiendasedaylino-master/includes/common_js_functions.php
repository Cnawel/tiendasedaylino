<?php
/**
 * ========================================================================
 * FUNCIONES JAVASCRIPT COMUNES - Tienda Seda y Lino
 * ========================================================================
 * Funciones JavaScript reutilizables para evitar duplicación de código
 * 
 * FUNCIONES:
 * - confirmLogout(): Confirma cierre de sesión antes de redirigir
 * - togglePassword(): Muestra/oculta contraseña en campos de password
 * - validarCoincidenciaPassword(): Valida que las contraseñas coincidan
 * - validarContrasenaUsuario(): Valida contraseña en formularios de edición
 * - validateEmail(): Valida formato de email (función pura)
 * - validateEmailInput(): Valida email en un input directamente
 * - validarCodigoPostal(): Valida y limpia código postal
 * - cambiarLimitePedidos(): Cambia límite de pedidos mostrados (ventas)
 * - setFieldValidation(): Establece estado de validación de un campo
 * - scrollToFirstError(): Hace scroll al primer campo con error en un formulario
 * - mostrarErrorCampo(): Muestra error en un campo (soporta elementos preexistentes o creación dinámica)
 * - limpiarErrorCampo(): Limpia error de un campo (soporta elementos preexistentes o búsqueda automática)
 * - mostrarFeedbackValidacion(): Muestra feedback de validación (válido/inválido) en un campo
 * 
 * USO:
 * Incluir este archivo antes de cerrar el tag </body> o en la sección <script>
 * 
 * @package TiendaSedaYLino
 * @version 3.0
 * ========================================================================
 */
?>
<script>
/**
 * Confirmar logout y asegurar redirección
 * @returns {boolean} True si se confirma el logout, false si se cancela
 */
function confirmLogout() {
    if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
        setTimeout(function() {
            window.location.href = 'login.php?logout=1';
        }, 100);
        return true;
    }
    return false;
}

/**
 * Función para mostrar/ocultar contraseña
 * Soporta múltiples casos de uso: wrapper, botón directo, o por ID
 * @param {string|HTMLElement} inputIdOrElement - ID del input o elemento input directamente
 */
function togglePassword(inputIdOrElement) {
    let input;
    
    // Si es un string, buscar por ID
    if (typeof inputIdOrElement === 'string') {
        input = document.getElementById(inputIdOrElement);
    } else {
        // Si es un elemento, usarlo directamente
        input = inputIdOrElement;
    }
    
    if (!input) return;
    
    // Buscar botón toggle de diferentes formas
    let button = null;
    let icon = null;
    
    // Método 1: Buscar en wrapper
    const wrapper = input.closest('.password-input-wrapper');
    if (wrapper) {
        button = wrapper.querySelector('.btn-toggle-password');
    }
    
    // Método 2: Si no hay wrapper, buscar botón siguiente (para login.js)
    if (!button) {
        button = input.nextElementSibling;
        if (button && !button.classList.contains('btn-toggle-password')) {
            button = null;
        }
    }
    
    // Método 3: Buscar por ID togglePassword (para login.js)
    if (!button && input.id === 'password') {
        button = document.getElementById('togglePassword');
    }
    
    if (button) {
        icon = button.querySelector('i');
    }
    
    // Cambiar tipo de input
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
            button.setAttribute('aria-label', 'Ocultar contraseña');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
            button.setAttribute('aria-label', 'Mostrar contraseña');
        }
    }
    
    // Validar coincidencia de contraseñas cuando se cambia la visibilidad
    const inputId = input.id || '';
    if (inputId === 'password' || inputId === 'confirmar_password' || 
        inputId === 'password_temporal' || inputId === 'confirmar_password_temporal') {
        if (typeof validarCoincidenciaPassword === 'function') {
            validarCoincidenciaPassword();
        }
        if (typeof validarCoincidenciaPasswordStaff === 'function') {
            validarCoincidenciaPasswordStaff();
        }
    }
}

/**
 * Validar que las contraseñas coincidan
 * Soporta múltiples contextos con parámetros opcionales
 * @param {string} passwordId - ID del campo password (opcional, default: 'password')
 * @param {string} confirmId - ID del campo confirmación (opcional, default: 'confirmar_password')
 * @param {string} errorFeedbackId - ID del elemento de feedback de error (opcional)
 * @param {string} successFeedbackId - ID del elemento de feedback de éxito (opcional)
 * @param {number} minLength - Longitud mínima requerida (opcional, default: 8)
 */
function validarCoincidenciaPassword(passwordId, confirmId, errorFeedbackId, successFeedbackId, minLength) {
    // Valores por defecto
    passwordId = passwordId || 'password';
    confirmId = confirmId || 'confirmar_password';
    minLength = minLength || 8;
    
    const password = document.getElementById(passwordId);
    const confirmar = document.getElementById(confirmId);
    
    if (!password || !confirmar) return;
    
    // Buscar elementos de feedback si no se proporcionan IDs
    let feedbackError = errorFeedbackId ? document.getElementById(errorFeedbackId) : 
        document.getElementById('password-match-feedback');
    let feedbackSuccess = successFeedbackId ? document.getElementById(successFeedbackId) : 
        document.getElementById('password-match-success');
    
    if (confirmar.value === '') {
        // Si el campo de confirmación está vacío, ocultar ambos mensajes
        if (feedbackError) feedbackError.style.display = 'none';
        if (feedbackSuccess) feedbackSuccess.style.display = 'none';
        confirmar.classList.remove('is-invalid', 'is-valid');
        return;
    }
    
    if (password.value === confirmar.value && password.value.length >= minLength) {
        // Contraseñas coinciden
        confirmar.classList.remove('is-invalid');
        confirmar.classList.add('is-valid');
        if (feedbackError) feedbackError.style.display = 'none';
        if (feedbackSuccess) feedbackSuccess.style.display = 'block';
    } else {
        // Contraseñas no coinciden
        confirmar.classList.remove('is-valid');
        confirmar.classList.add('is-invalid');
        if (feedbackError) feedbackError.style.display = 'block';
        if (feedbackSuccess) feedbackSuccess.style.display = 'none';
    }
}

/**
 * Validar formato de email (función pura)
 * 
 * NOTA: Existe versión PHP equivalente en admin_functions.php (validarEmail)
 * Ambas versiones deben mantener la misma lógica de validación.
 * 
 * @param {string} email - Email a validar
 * @returns {boolean} True si el formato es válido
 */
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Valida email en un input directamente y aplica clases de validación
 * @param {HTMLElement} input - Elemento input a validar
 * @returns {boolean} True si el email es válido
 */
function validateEmailInput(input) {
    if (!input) return false;
    
    const email = input.value.trim();
    
    if (email === '') {
        input.classList.remove('is-valid', 'is-invalid');
        return false;
    } else if (validateEmail(email)) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        return true;
    } else {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        return false;
    }
}

/**
 * Valida y limpia código postal (permite números y letras)
 * @param {HTMLElement} input - Elemento input de código postal
 */
function validarCodigoPostal(input) {
    if (!input) return;
    
    const value = input.value;
    // Solo permitir letras, números y espacios
    const validPattern = /^[A-Za-z0-9 ]*$/;
    
    if (!validPattern.test(value)) {
        // Remover caracteres no permitidos
        input.value = value.replace(/[^A-Za-z0-9 ]/g, '');
    }
}

/**
 * Validación de contraseña para el formulario de edición de usuarios
 * Soporta modales y diferentes contextos
 * @param {number|string} userId - ID del usuario (opcional, para modales)
 * @param {HTMLElement} nuevaContrasenaInput - Input de nueva contraseña (opcional)
 * @param {HTMLElement} confirmarContrasenaInput - Input de confirmar contraseña (opcional)
 * @param {number} minLength - Longitud mínima (opcional, default: 6)
 * @param {number} maxLength - Longitud máxima (opcional, default: 32)
 * @returns {boolean} True si la validación es exitosa
 */
function validarContrasenaUsuario(userId, nuevaContrasenaInput, confirmarContrasenaInput, minLength, maxLength) {
    minLength = minLength || 6;
    maxLength = maxLength || 32;
    
    let nuevaContrasena, confirmarContrasena;
    
    // Si se proporcionan inputs directamente, usarlos
    if (nuevaContrasenaInput && confirmarContrasenaInput) {
        nuevaContrasena = nuevaContrasenaInput.value;
        confirmarContrasena = confirmarContrasenaInput.value;
    } else if (userId) {
        // Buscar por ID con userId
        const nueva = document.querySelector('#nueva_contrasena_' + userId);
        const confirmar = document.querySelector('#confirmar_contrasena_' + userId);
        
        if (!nueva || !confirmar) {
            // Si no existen, buscar en el modal activo
            const modal = document.querySelector('.modal.show');
            if (modal) {
                nuevaContrasena = modal.querySelector('input[name="nueva_contrasena"]')?.value || '';
                confirmarContrasena = modal.querySelector('input[name="confirmar_contrasena"]')?.value || '';
            } else {
                return true; // Si no se encuentra, permitir envío
            }
        } else {
            nuevaContrasena = nueva.value;
            confirmarContrasena = confirmar.value;
        }
    } else {
        // Buscar por name (comportamiento original)
        const nueva = document.querySelector('input[name="nueva_contrasena"]');
        const confirmar = document.querySelector('input[name="confirmar_contrasena"]');
        
        if (!nueva || !confirmar) {
            return true; // Si no se encuentra, permitir envío
        }
        
        nuevaContrasena = nueva.value;
        confirmarContrasena = confirmar.value;
    }
    
    // Si ambos campos están vacíos, permitir envío (no cambiar contraseña)
    if (nuevaContrasena === '' && confirmarContrasena === '') {
        return true;
    }
    
    // Si solo uno está lleno, mostrar error
    if ((nuevaContrasena === '' && confirmarContrasena !== '') || 
        (nuevaContrasena !== '' && confirmarContrasena === '')) {
        alert('Debes completar ambos campos de contraseña o dejarlos vacíos');
        return false;
    }
    
    // Validar longitud mínima y máxima
    if (nuevaContrasena.length < minLength || nuevaContrasena.length > maxLength) {
        alert('La contraseña debe tener entre ' + minLength + ' y ' + maxLength + ' caracteres');
        if (nuevaContrasenaInput) nuevaContrasenaInput.focus();
        return false;
    }
    
    // Validar que las contraseñas coincidan
    if (nuevaContrasena !== confirmarContrasena) {
        alert('Las contraseñas no coinciden');
        if (confirmarContrasenaInput) confirmarContrasenaInput.focus();
        return false;
    }
    
    return true;
}

/**
 * Cambiar límite de pedidos mostrados (específico para ventas.php)
 * @param {string} limite - Límite seleccionado (10, 50, TODOS)
 */
function cambiarLimitePedidos(limite) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('limite', limite);
    window.location.search = urlParams.toString();
}

/**
 * Alias para validarCoincidenciaPasswordStaff (compatibilidad)
 * Usa la función consolidada con parámetros específicos para staff
 */
function validarCoincidenciaPasswordStaff() {
    validarCoincidenciaPassword('password_temporal', 'confirmar_password_temporal', 
        'password-match-feedback', 'password-match-success', 6);
}

/**
 * Alias para togglePasswordStaff (compatibilidad)
 * Usa la función consolidada togglePassword
 */
function togglePasswordStaff(inputId) {
    togglePassword(inputId);
}

/**
 * Alias para validarContrasenaUsuarioModal (compatibilidad)
 * Usa la función consolidada con inputs directos
 */
function validarContrasenaUsuarioModal(nuevaContrasena, confirmarContrasena) {
    return validarContrasenaUsuario(null, nuevaContrasena, confirmarContrasena, 6, 32);
}

/**
 * Establece el estado de validación de un campo (helper para patrones repetidos)
 * @param {HTMLElement} input - Elemento input a validar
 * @param {boolean} isValid - true si es válido, false si es inválido, null para limpiar
 */
function setFieldValidation(input, isValid) {
    if (!input) return;
    
    if (isValid === null || isValid === undefined) {
        // Limpiar validación
        input.classList.remove('is-valid', 'is-invalid');
    } else if (isValid) {
        // Campo válido
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
    } else {
        // Campo inválido
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
    }
}

/**
 * Hace scroll al primer campo con error en un formulario (helper para patrones repetidos)
 * @param {HTMLElement} form - Elemento formulario
 * @returns {HTMLElement|null} - Primer elemento con error o null si no hay errores
 */
function scrollToFirstError(form) {
    if (!form) return null;
    
    const firstError = form.querySelector('.is-invalid');
    if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstError.focus();
        return firstError;
    }
    return null;
}

/**
 * Muestra error en un campo de forma genérica
 * Soporta tanto elementos de error preexistentes como creación dinámica
 * @param {HTMLElement} campo - Campo a marcar como inválido
 * @param {HTMLElement|string|null} errorElementOrId - Elemento de error existente, ID del elemento, o null para crear dinámicamente
 * @param {string} mensaje - Mensaje de error a mostrar
 */
function mostrarErrorCampo(campo, errorElementOrId, mensaje) {
    if (!campo) return;
    
    campo.classList.remove('is-valid');
    campo.classList.add('is-invalid');
    
    let errorElement = null;
    
    // Si es un string, buscar por ID
    if (typeof errorElementOrId === 'string') {
        errorElement = document.getElementById(errorElementOrId);
    } else if (errorElementOrId) {
        // Si es un elemento, usarlo directamente
        errorElement = errorElementOrId;
    }
    
    // Si no hay elemento de error, crear uno dinámicamente
    if (!errorElement) {
        // Buscar si ya existe un elemento de feedback
        const container = campo.closest('.mb-3') || campo.closest('.form-group') || campo.parentElement;
        errorElement = container.querySelector('.invalid-feedback');
        
        if (!errorElement) {
            // Crear nuevo elemento de feedback
            errorElement = document.createElement('div');
            errorElement.className = 'invalid-feedback';
            container.appendChild(errorElement);
        }
    }
    
    if (errorElement) {
        errorElement.textContent = mensaje || '';
        errorElement.style.display = 'block';
    }
    
    // Establecer custom validity si el campo lo soporta
    if (campo.setCustomValidity) {
        campo.setCustomValidity(mensaje || '');
    }
}

/**
 * Limpia error de un campo de forma genérica
 * Soporta tanto elementos de error preexistentes como elementos creados dinámicamente
 * @param {HTMLElement} campo - Campo a limpiar
 * @param {HTMLElement|string|null} errorElementOrId - Elemento de error existente, ID del elemento, o null para buscar automáticamente
 */
function limpiarErrorCampo(campo, errorElementOrId) {
    if (!campo) return;
    
    campo.classList.remove('is-invalid', 'is-valid');
    
    if (campo.setCustomValidity) {
        campo.setCustomValidity('');
    }
    
    let errorElement = null;
    
    // Si es un string, buscar por ID
    if (typeof errorElementOrId === 'string') {
        errorElement = document.getElementById(errorElementOrId);
    } else if (errorElementOrId) {
        // Si es un elemento, usarlo directamente
        errorElement = errorElementOrId;
    } else {
        // Buscar automáticamente en el contenedor
        const container = campo.closest('.mb-3') || campo.closest('.form-group') || campo.parentElement;
        errorElement = container.querySelector('.invalid-feedback');
    }
    
    if (errorElement) {
        errorElement.textContent = '';
        errorElement.style.display = 'none';
    }
}

/**
 * Muestra feedback de validación (válido o inválido) en un campo
 * Crea elementos de feedback dinámicamente si no existen
 * @param {HTMLElement} input - Campo a validar
 * @param {boolean} esValido - true si es válido, false si es inválido
 * @param {string} mensaje - Mensaje a mostrar (opcional, solo para errores)
 */
function mostrarFeedbackValidacion(input, esValido, mensaje) {
    if (!input) return;
    
    input.classList.remove('is-valid', 'is-invalid');
    
    if (esValido) {
        input.classList.add('is-valid');
    } else {
        input.classList.add('is-invalid');
    }
    
    // Buscar o crear elemento de feedback
    const container = input.closest('.mb-3') || input.closest('.form-group') || input.parentElement;
    let feedback = container.querySelector('.invalid-feedback');
    
    if (!feedback && !esValido) {
        // Crear elemento de feedback solo si hay error
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        container.appendChild(feedback);
    }
    
    if (feedback) {
        feedback.textContent = mensaje || '';
        feedback.style.display = esValido ? 'none' : 'block';
    }
    
    // Establecer custom validity si el campo lo soporta
    if (input.setCustomValidity) {
        input.setCustomValidity(esValido ? '' : (mensaje || ''));
    }
}
</script>

