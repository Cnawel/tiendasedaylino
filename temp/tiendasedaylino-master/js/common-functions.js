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
 * - cambiarLimitePedidos(): Cambia límite de pedidos mostrados (ventas)
 * 
 * USO:
 * Incluir este archivo antes de cerrar el tag </body> o en la sección <script>
 * <script src="js/common-functions.js"></script>
 * 
 * @package TiendaSedaYLino
 * @version 2.0
 * ========================================================================
 */

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
 * @param {string} inputId - ID del input de contraseña
 */
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const wrapper = input.closest('.password-input-wrapper');
    if (!wrapper) return;
    
    const button = wrapper.querySelector('.btn-toggle-password');
    const icon = button ? button.querySelector('i') : null;
    
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
    if (inputId === 'password' || inputId === 'confirmar_password') {
        if (typeof validarCoincidenciaPassword === 'function') {
            validarCoincidenciaPassword();
        }
    }
}

/**
 * Validar que las contraseñas coincidan en el formulario de creación
 */
function validarCoincidenciaPassword() {
    const password = document.getElementById('password');
    const confirmar = document.getElementById('confirmar_password');
    const feedbackError = document.getElementById('password-match-feedback');
    const feedbackSuccess = document.getElementById('password-match-success');
    
    if (!password || !confirmar) return;
    
    if (confirmar.value === '') {
        // Si el campo de confirmación está vacío, ocultar ambos mensajes
        if (feedbackError) feedbackError.style.display = 'none';
        if (feedbackSuccess) feedbackSuccess.style.display = 'none';
        confirmar.classList.remove('is-invalid', 'is-valid');
        return;
    }
    
    if (password.value === confirmar.value && password.value.length >= 8) {
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
 * Validación de contraseña para el formulario de edición de usuarios
 * @param {number} userId - ID del usuario
 * @returns {boolean} True si la validación es exitosa
 */
function validarContrasenaUsuario(userId) {
    const nuevaContrasena = document.querySelector('input[name="nueva_contrasena"]').value;
    const confirmarContrasena = document.querySelector('input[name="confirmar_contrasena"]').value;
    
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
    
    // Validar longitud mínima
    if (nuevaContrasena.length < 8) {
        alert('La contraseña debe tener al menos 8 caracteres');
        return false;
    }
    
    // Validar que las contraseñas coincidan
    if (nuevaContrasena !== confirmarContrasena) {
        alert('Las contraseñas no coinciden');
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

