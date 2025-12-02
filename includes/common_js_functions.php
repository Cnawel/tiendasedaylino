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
 * VALIDACIÓN CLIENTE (JavaScript): Solo para UX, no es seguridad
 * VALIDACIÓN SERVIDOR (PHP): Siempre validar en servidor, esta es la validación definitiva
 * PHP también valida longitud (6-100 caracteres) - esta función valida formato y longitud
 * 
 * Según diccionario: solo se permiten [A-Z, a-z, 0-9, @, _, -, ., +]
 * 
 * @param {string} email - Email a validar
 * @returns {boolean} True si el formato es válido
 */
function validateEmail(email) {
    // Validar estructura básica (usuario@dominio.extensión)
    const estructuraBasica = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!estructuraBasica.test(email)) {
        return false;
    }
    
    // Validar caracteres permitidos según diccionario
    // Separar parte local (antes del @) y dominio (después del @)
    const partes = email.split('@');
    if (partes.length !== 2) {
        return false;
    }
    
    const parteLocal = partes[0];
    const dominio = partes[1];
    
    // Validar parte local: solo [A-Z, a-z, 0-9, _, -, ., +]
    const parteLocalRegex = /^[A-Za-z0-9_\-\.+]+$/;
    if (!parteLocalRegex.test(parteLocal)) {
        return false;
    }
    
    // Validar dominio: solo [A-Z, a-z, 0-9, _, -, .]
    const dominioRegex = /^[A-Za-z0-9_\-\.]+$/;
    if (!dominioRegex.test(dominio)) {
        return false;
    }
    
    return true;
}

/**
 * Valida email en un input directamente y aplica clases de validación
 * 
 * NOTA: Esta función debe coincidir con la validación PHP en admin_functions.php:validarEmail()
 * PHP valida: longitud 6-100 caracteres, formato con filter_var()
 * Esta función valida: formato básico y longitud (6-100 caracteres)
 * 
 * VALIDACIÓN CLIENTE (JavaScript): Solo para UX, no es seguridad
 * VALIDACIÓN SERVIDOR (PHP): Siempre validar en servidor, esta es la validación definitiva
 * 
 * @param {HTMLElement} input - Elemento input a validar
 * @returns {boolean} True si el email es válido
 */
function validateEmailInput(input) {
    if (!input) return false;
    
    const email = input.value.trim();
    
    if (email === '') {
        input.classList.remove('is-valid', 'is-invalid');
        return false;
    }
    
    // Validar longitud como en PHP (6-100 caracteres según diccionario de datos)
    if (email.length < 6) {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        return false;
    }
    
    if (email.length > 100) {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        return false;
    }
    
    // Validar formato
    if (validateEmail(email)) {
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
 * Valida y limpia código postal (permite números y letras, sin espacios)
 * 
 * NOTA: Esta función debe coincidir exactamente con la validación PHP en validation_functions.php:validarCodigoPostal()
 * Patrón PHP: /^[A-Za-z0-9]+$/ (coincide con este patrón JavaScript)
 * Según diccionario: [0-9, A-Z, a-z] sin espacios, longitud: 4-10 caracteres
 * 
 * VALIDACIÓN CLIENTE (JavaScript): Solo para UX, no es seguridad
 * VALIDACIÓN SERVIDOR (PHP): Siempre validar en servidor, esta es la validación definitiva
 * 
 * @param {HTMLElement} input - Elemento input de código postal
 * @param {string} evento - Tipo de evento: 'input' (solo limpieza) o 'blur' (validación completa)
 * @param {boolean} requerido - Si el campo es requerido (default: true)
 */
function validarCodigoPostal(input, evento, requerido) {
    if (!input) return;
    
    if (requerido === undefined) requerido = true;
    if (!evento) evento = 'input';
    
    let value = input.value;
    // Eliminar espacios
    value = value.replace(/\s/g, '');
    // Patrón debe coincidir exactamente con PHP: /^[A-Za-z0-9]+$/
    const validPattern = /^[A-Za-z0-9]+$/;
    
    // En evento 'input': solo limpieza y limitar longitud
    if (evento === 'input') {
        if (!validPattern.test(value)) {
            value = value.replace(/[^A-Za-z0-9]/g, '');
        }
        if (value.length > 10) {
            value = value.substring(0, 10);
        }
        input.value = value;
        
        // Limpiar validación mientras se escribe
        if (input.classList.contains('is-invalid')) {
            input.classList.remove('is-invalid');
            const feedback = input.parentElement?.querySelector('.invalid-feedback');
            if (feedback) {
                feedback.textContent = '';
                feedback.style.display = 'none';
            }
        }
    }
    // En evento 'blur': validación completa
    else if (evento === 'blur') {
        // Limpiar primero
        if (!validPattern.test(value)) {
            value = value.replace(/[^A-Za-z0-9]/g, '');
        }
        if (value.length > 10) {
            value = value.substring(0, 10);
        }
        input.value = value;
        
        // Validar
        if (!value && requerido) {
            mostrarFeedbackValidacion(input, false, 'El código postal es requerido');
        } else if (value && value.length < 4) {
            mostrarFeedbackValidacion(input, false, 'El código postal debe tener al menos 4 caracteres');
        } else if (value && value.length > 10) {
            mostrarFeedbackValidacion(input, false, 'El código postal no puede exceder 10 caracteres');
        } else if (value && !validPattern.test(value)) {
            mostrarFeedbackValidacion(input, false, 'Solo se permiten letras y números');
        } else if (value) {
            mostrarFeedbackValidacion(input, true, '');
        } else {
            input.classList.remove('is-invalid', 'is-valid');
        }
    }
}

/**
 * Validación de contraseña para el formulario de edición de usuarios
 * Soporta modales y diferentes contextos
 * 
 * REGLAS DE VALIDACIÓN (según diccionario de datos):
 * - Longitud: 6-20 caracteres (antes de hashear)
 * - Patrón: A-Z, a-z, 0-9, ! @ # $ % ^ & * ? _ - . = + | \ / { } [ ] ( ) < > : ; " ' ~ ` espacios
 * - Almacenamiento: Se hashea y almacena en VARCHAR(255)
 * 
 * NOTA: El límite lógico es 20 caracteres ANTES de hashear, aunque se almacene como VARCHAR(255)
 * 
 * @param {number|string} userId - ID del usuario (opcional, para modales)
 * @param {HTMLElement} nuevaContrasenaInput - Input de nueva contraseña (opcional)
 * @param {HTMLElement} confirmarContrasenaInput - Input de confirmar contraseña (opcional)
 * @param {number} minLength - Longitud mínima (opcional, default: 6)
 * @param {number} maxLength - Longitud máxima (opcional, default: 20 según diccionario)
 * @returns {boolean} True si la validación es exitosa
 */
function validarContrasenaUsuario(userId, nuevaContrasenaInput, confirmarContrasenaInput, minLength, maxLength) {
    minLength = minLength || 6;
    maxLength = maxLength || 20; // Según diccionario de datos, máximo 20 caracteres
    
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
    return validarContrasenaUsuario(null, nuevaContrasena, confirmarContrasena, 6, 20);
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

/**
 * Valida nombre o apellido (solo letras, espacios, apóstrofes y acentos, mínimo 2 caracteres)
 * 
 * NOTA: Existe versión PHP equivalente en admin_functions.php
 * Ambas versiones deben mantener la misma lógica de validación.
 * 
 * @param {string} valor - Valor a validar
 * @param {number} minLength - Longitud mínima (opcional, default: 2)
 * @param {number} maxLength - Longitud máxima (opcional, default: 100)
 * @returns {boolean} - true si es válido
 */
function validarNombreApellido(valor, minLength, maxLength) {
    minLength = minLength || 2;
    maxLength = maxLength || 100;
    
    const valorTrimmed = valor ? valor.trim() : '';
    const re = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]+$/;
    
    return valorTrimmed.length >= minLength && 
           valorTrimmed.length <= maxLength && 
           re.test(valorTrimmed);
}

/**
 * Valida teléfono (números, espacios y símbolos: +, -, (, ), longitud 6-20 caracteres)
 * 
 * NOTA: Esta función debe coincidir exactamente con la validación PHP en validation_functions.php:validarTelefono()
 * Patrón PHP: /^[0-9+\-() ]+$/ (coincide con este patrón JavaScript)
 * 
 * VALIDACIÓN CLIENTE (JavaScript): Solo para UX, no es seguridad
 * VALIDACIÓN SERVIDOR (PHP): Siempre validar en servidor, esta es la validación definitiva
 * 
 * REGLAS DE VALIDACIÓN:
 * - Longitud: 6-20 caracteres (si tiene valor)
 * - Patrón: [0-9, +, (, ), -, espacios]
 * - Campo opcional: Si está vacío y esOpcional=true, es válido
 * 
 * @param {HTMLElement} input - Elemento input de teléfono
 * @param {boolean} esOpcional - Si el campo es opcional (default: true)
 */
function validarTelefono(input, esOpcional) {
    if (!input) return;
    
    esOpcional = esOpcional !== false; // Por defecto es opcional
    
    const valor = input.value.trim();
    // Patrón debe coincidir exactamente con PHP: /^[0-9+\-() ]+$/
    const pattern = /^[0-9+\-() ]+$/;
    
    // Si es opcional y está vacío, es válido
    if (esOpcional && valor === '') {
        input.classList.remove('is-valid', 'is-invalid');
        if (input.setCustomValidity) {
            input.setCustomValidity('');
        }
        return;
    }
    
    // Si no es opcional y está vacío, es inválido
    if (!esOpcional && valor === '') {
        mostrarFeedbackValidacion(input, false, 'El teléfono es requerido');
        return;
    }
    
    // Validar longitud
    if (valor.length < 6) {
        mostrarFeedbackValidacion(input, false, 'El teléfono debe tener al menos 6 caracteres');
        return;
    }
    
    if (valor.length > 20) {
        mostrarFeedbackValidacion(input, false, 'El teléfono no puede exceder 20 caracteres');
        return;
    }
    
    // Validar formato
    if (!pattern.test(valor)) {
        mostrarFeedbackValidacion(input, false, 'Solo se permiten números y símbolos (+, -, paréntesis, espacios)');
        return;
    }
    
    // Es válido
    mostrarFeedbackValidacion(input, true, '');
}

/**
 * Valida dirección (calle, número o piso/departamento)
 * Permite letras (con acentos), números, espacios, guiones, apóstrofes y acentos graves
 * 
 * NOTA: Esta función debe coincidir exactamente con la validación PHP en validation_functions.php:validarDireccion()
 * Patrón PHP: /^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]+$/ (coincide con este patrón JavaScript)
 * 
 * VALIDACIÓN CLIENTE (JavaScript): Solo para UX, no es seguridad
 * VALIDACIÓN SERVIDOR (PHP): Siempre validar en servidor, esta es la validación definitiva
 * 
 * @param {HTMLElement} input - Elemento input de dirección
 * @param {boolean} esRequerido - Si es true, el campo es obligatorio (default: true)
 * @param {number} minLength - Longitud mínima (opcional, default: 2)
 * @param {string} tipoCampo - Tipo de campo: 'calle', 'numero', 'piso' (para mensajes personalizados)
 */
function validarDireccion(input, esRequerido, minLength, tipoCampo) {
    if (!input) return;
    
    esRequerido = esRequerido !== false; // Por defecto es requerido
    minLength = minLength || 2;
    tipoCampo = tipoCampo || 'dirección';
    
    const valor = input.value.trim();
    // Patrón debe coincidir exactamente con PHP: /^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]+$/
    const pattern = /^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]+$/;
    
    // Si es opcional (piso/depto) y está vacío, es válido
    if (!esRequerido && valor === '') {
        input.classList.remove('is-valid', 'is-invalid');
        if (input.setCustomValidity) {
            input.setCustomValidity('');
        }
        return;
    }
    
    // Si es requerido y está vacío, es inválido
    if (esRequerido && valor === '') {
        const mensaje = tipoCampo === 'calle' ? 'La dirección es requerida' : 
                       tipoCampo === 'numero' ? 'El número es requerido' : 
                       'La dirección es requerida';
        mostrarFeedbackValidacion(input, false, mensaje);
        return;
    }
    
    // Validar longitud mínima
    if (valor.length < minLength) {
        mostrarFeedbackValidacion(input, false, 'La dirección debe tener al menos ' + minLength + ' caracteres');
        return;
    }
    
    // Validar formato
    if (!pattern.test(valor)) {
        mostrarFeedbackValidacion(input, false, 'Solo se permiten letras (incluyendo acentos), números, espacios, guiones, apóstrofes y acentos graves');
        return;
    }
    
    // Es válido
    mostrarFeedbackValidacion(input, true, '');
}

/**
 * Filtra caracteres no permitidos en campos de dirección
 * 
 * REGLAS DE FILTRADO:
 * - Permite: letras (con acentos), números, espacios, guión, apóstrofe, backtick
 * - Elimina: cualquier otro carácter
 * - Limpia validación visual mientras se escribe
 * 
 * NOTA: Esta función solo filtra caracteres en tiempo real (evento 'input')
 * La validación completa se hace con validarDireccion() en evento 'blur'
 * 
 * @param {HTMLElement} element - El input a filtrar
 * @param {string} tipo - Tipo de dirección: 'calle', 'numero', 'piso' (para referencia, no afecta el filtrado)
 */
function filtrarDireccion(element, tipo) {
    if (!element) return;
    
    const value = element.value;
    // Patrón permitido: letras (con acentos), números, espacios, guión, apóstrofe, backtick
    const validPattern = /^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]*$/;
    
    if (!validPattern.test(value)) {
        element.value = value.replace(/[^A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]/g, '');
    }
    
    // Limpiar validación mientras se escribe
    if (element.classList.contains('is-invalid')) {
        element.classList.remove('is-invalid');
        
        // Buscar feedback de validación (puede estar en parentElement o ser setCustomValidity)
        const feedback = element.parentElement?.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = '';
            feedback.style.display = 'none';
        }
        
        // También limpiar setCustomValidity si existe (para checkout.js)
        if (element.setCustomValidity) {
            element.setCustomValidity('');
        }
    }
}
</script>

