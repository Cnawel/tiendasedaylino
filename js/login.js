// ============================================================================
// LOGIN - JavaScript para mejorar UX
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const loginBtn = document.getElementById('loginBtn');
    
    // ========================================================================
    // Validación en tiempo real del email
    // Usa la función validateEmailInput de common_js_functions.php
    // ========================================================================
    emailInput.addEventListener('input', function() {
        // Usar función consolidada de common_js_functions.php
        if (typeof validateEmailInput === 'function') {
            validateEmailInput(this);
        } else {
            // Fallback si la función no está disponible
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value.trim() === '') {
                this.classList.remove('is-valid', 'is-invalid');
            } else if (emailRegex.test(this.value)) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        }
    });
    
    emailInput.addEventListener('blur', function() {
        // Validar al perder el foco usando función consolidada
        if (typeof validateEmailInput === 'function') {
            validateEmailInput(this);
        } else {
            // Fallback si la función no está disponible
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value.trim() === '') {
                this.classList.remove('is-valid', 'is-invalid');
            } else if (emailRegex.test(this.value)) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        }
    });
    
    // ========================================================================
    // Toggle mostrar/ocultar contraseña
    // Usa la función togglePassword de common_js_functions.php
    // ========================================================================
    togglePasswordBtn.addEventListener('click', function() {
        // Usar función consolidada de common_js_functions.php
        togglePassword(passwordInput);
    });
    
    // ========================================================================
    // Validación del formulario al enviar
    // ========================================================================
    loginForm.addEventListener('submit', function(e) {
        let isValid = true;
        
        // Validar email (obligatorio)
        // Usar función consolidada de common_js_functions.php
        if (!emailInput.value.trim() || 
            (typeof validateEmail === 'function' && !validateEmail(emailInput.value.trim())) ||
            (!emailInput.classList.contains('is-valid'))) {
            emailInput.classList.add('is-invalid');
            isValid = false;
        }
        
        // Validar contraseña (obligatorio)
        if (!passwordInput.value || passwordInput.value.length === 0) {
            passwordInput.classList.add('is-invalid');
            isValid = false;
        }
        
        // Si no es válido, prevenir envío
        if (!isValid) {
            e.preventDefault();
            scrollToFirstError(loginForm);
            return;
        }
        
        // Mostrar estado de carga
        const btnText = loginBtn.querySelector('.btn-text');
        const btnLoading = loginBtn.querySelector('.btn-loading');
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');
        loginBtn.disabled = true;
    });
    
    // ========================================================================
    // Limpiar errores al interactuar
    // NOTA: El listener de 'input' en emailInput ya está manejado arriba con validateEmailInput
    // que automáticamente limpia los errores, por lo que este listener duplicado se elimina
    // ========================================================================
    passwordInput.addEventListener('input', function() {
        if (this.value) {
            this.classList.remove('is-invalid');
        }
    });
    
    // ========================================================================
    // Animación suave de entrada (reducida para mejor rendimiento)
    // ========================================================================
    const authCard = document.querySelector('.auth-card');
    authCard.style.opacity = '0';
    authCard.style.transform = 'translateY(20px)';
    
    // Animación inmediata sin delay
    setTimeout(() => {
        authCard.style.transition = 'all 0.3s ease';
        authCard.style.opacity = '1';
        authCard.style.transform = 'translateY(0)';
    }, 1);
});

