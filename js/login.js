// ============================================================================
// LOGIN - JavaScript para mejorar UX
// ============================================================================

// Prevenir ejecución múltiple
if (window.loginScriptLoaded) {
    // Script ya cargado, no hacer nada
} else {
    window.loginScriptLoaded = true;

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    if (!loginForm) return;
    
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = loginForm.querySelector('#togglePassword') || document.getElementById('togglePassword');
    const loginBtn = document.getElementById('loginBtn');
    
    // ========================================================================
    // Validación en tiempo real del email
    // Usa la función validateEmailInput de common_js_functions.php
    // ========================================================================
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            // Usar función consolidada de common_js_functions.php
            if (typeof validateEmailInput === 'function') {
                validateEmailInput(this);
            }
        });
        
        emailInput.addEventListener('blur', function() {
            // Validar al perder el foco usando función consolidada
            if (typeof validateEmailInput === 'function') {
                validateEmailInput(this);
            }
        });
    }
    
    // ========================================================================
    // Toggle mostrar/ocultar contraseña
    // Usa la función togglePassword de common_js_functions.php
    // ========================================================================
    if (togglePasswordBtn && passwordInput) {
        // Remover listeners previos si existen
        const newToggleBtn = togglePasswordBtn.cloneNode(true);
        togglePasswordBtn.parentNode.replaceChild(newToggleBtn, togglePasswordBtn);
        
        newToggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Usar función consolidada de common_js_functions.php
            if (typeof togglePassword === 'function') {
                togglePassword(passwordInput.id || passwordInput);
            }
            
            return false;
        });
    }
    
    // ========================================================================
    // Validación del formulario al enviar
    // ========================================================================
    if (loginForm) {
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
            
            // Scroll al primer error
            const firstError = loginForm.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
            return;
        }
        
        // Mostrar estado de carga
        const btnText = loginBtn.querySelector('.btn-text');
        const btnLoading = loginBtn.querySelector('.btn-loading');
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');
        loginBtn.disabled = true;
    });
    }
    
    // ========================================================================
    // Limpiar errores al interactuar
    // NOTA: El listener de 'input' en emailInput ya está manejado arriba con validateEmailInput
    // que automáticamente limpia los errores, por lo que este listener duplicado se elimina
    // ========================================================================
    if (passwordInput) {
    passwordInput.addEventListener('input', function() {
        if (this.value) {
            this.classList.remove('is-invalid');
        }
    });
    }
    
    // ========================================================================
    // Animación suave de entrada (reducida para mejor rendimiento)
    // ========================================================================
    const authCard = document.querySelector('.auth-card');
    if (authCard) {
    authCard.style.opacity = '0';
    authCard.style.transform = 'translateY(20px)';
    
    // Animación inmediata sin delay
    setTimeout(() => {
        authCard.style.transition = 'all 0.3s ease';
        authCard.style.opacity = '1';
        authCard.style.transform = 'translateY(0)';
    }, 1);
    }
});
} // Cerrar el bloque else de prevención de ejecución múltiple
