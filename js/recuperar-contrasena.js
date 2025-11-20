/**
 * ========================================================================
 * RECUPERAR CONTRASEÑA - JavaScript para validación y UX
 * ========================================================================
 * Funciones JavaScript para el proceso de recuperación de contraseña
 * Usa funciones de common_js_functions.php (validateEmailInput, togglePassword, validarCoincidenciaPassword)
 * ========================================================================
 */
document.addEventListener('DOMContentLoaded', function() {
    const validarForm = document.getElementById('validarForm');
    const cambiarForm = document.getElementById('cambiarForm');
    const emailInput = document.getElementById('email');
    const respuestaRecuperoInput = document.getElementById('respuesta_recupero');
    const nuevaContrasenaInput = document.getElementById('nueva_contrasena');
    const confirmarContrasenaInput = document.getElementById('confirmar_contrasena');
    const togglePasswordNueva = document.getElementById('togglePasswordNueva');
    const togglePasswordConfirmar = document.getElementById('togglePasswordConfirmar');
    
    // ========================================================================
    // Validación en tiempo real del email (Paso 1)
    // Usa validateEmailInput() de common_js_functions.php
    // ========================================================================
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            validateEmailInput(this);
        });
        
        emailInput.addEventListener('blur', function() {
            validateEmailInput(this);
        });
    }
    
    // ========================================================================
    // Toggle mostrar/ocultar contraseñas (Paso 2)
    // Usa togglePassword() de common_js_functions.php
    // ========================================================================
    if (togglePasswordNueva && nuevaContrasenaInput) {
        togglePasswordNueva.addEventListener('click', function(e) {
            e.preventDefault();
            togglePassword(nuevaContrasenaInput);
        });
    }
    
    if (togglePasswordConfirmar && confirmarContrasenaInput) {
        togglePasswordConfirmar.addEventListener('click', function(e) {
            e.preventDefault();
            togglePassword(confirmarContrasenaInput);
        });
    }
    
    // ========================================================================
    // Validación de confirmación de contraseña (Paso 2)
    // Usa validarCoincidenciaPassword() de common_js_functions.php
    // ========================================================================
    if (confirmarContrasenaInput && nuevaContrasenaInput) {
        confirmarContrasenaInput.addEventListener('input', function() {
            validarCoincidenciaPassword('nueva_contrasena', 'confirmar_contrasena');
        });
        confirmarContrasenaInput.addEventListener('blur', function() {
            validarCoincidenciaPassword('nueva_contrasena', 'confirmar_contrasena');
        });
        nuevaContrasenaInput.addEventListener('input', function() {
            validarCoincidenciaPassword('nueva_contrasena', 'confirmar_contrasena');
        });
    }
    
    // ========================================================================
    // Validación del formulario de validación (Paso 1)
    // ========================================================================
    if (validarForm) {
        const validarBtn = document.getElementById('validarBtn');
        
        validarForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            if (emailInput && (!emailInput.value.trim() || !emailInput.classList.contains('is-valid'))) {
                emailInput.classList.add('is-invalid');
                isValid = false;
            }
            
            const fechaNacimientoInput = document.getElementById('fecha_nacimiento');
            if (fechaNacimientoInput && !fechaNacimientoInput.value) {
                fechaNacimientoInput.classList.add('is-invalid');
                isValid = false;
            }
            
            const preguntaRecuperoInput = document.getElementById('pregunta_recupero');
            if (preguntaRecuperoInput && !preguntaRecuperoInput.value) {
                preguntaRecuperoInput.classList.add('is-invalid');
                isValid = false;
            }
            
            if (respuestaRecuperoInput && !respuestaRecuperoInput.value.trim()) {
                respuestaRecuperoInput.classList.add('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                scrollToFirstError(validarForm);
                return;
            }
            
            // Mostrar estado de carga
            if (validarBtn) {
                const btnText = validarBtn.querySelector('.btn-text');
                const btnLoading = validarBtn.querySelector('.btn-loading');
                if (btnText && btnLoading) {
                    btnText.classList.add('d-none');
                    btnLoading.classList.remove('d-none');
                    validarBtn.disabled = true;
                }
            }
        });
    }
    
    // ========================================================================
    // Validación del formulario de cambio (Paso 2)
    // ========================================================================
    if (cambiarForm) {
        const cambiarBtn = document.getElementById('cambiarBtn');
        
        cambiarForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            if (nuevaContrasenaInput && (!nuevaContrasenaInput.value || nuevaContrasenaInput.value.length < 6)) {
                nuevaContrasenaInput.classList.add('is-invalid');
                isValid = false;
            }
            
            if (confirmarContrasenaInput && nuevaContrasenaInput && nuevaContrasenaInput.value !== confirmarContrasenaInput.value) {
                confirmarContrasenaInput.classList.add('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                scrollToFirstError(cambiarForm);
                return;
            }
            
            // Mostrar estado de carga
            if (cambiarBtn) {
                const btnText = cambiarBtn.querySelector('.btn-text');
                const btnLoading = cambiarBtn.querySelector('.btn-loading');
                if (btnText && btnLoading) {
                    btnText.classList.add('d-none');
                    btnLoading.classList.remove('d-none');
                    cambiarBtn.disabled = true;
                }
            }
        });
    }
    
    // ========================================================================
    // Limpiar errores al interactuar
    // ========================================================================
    if (respuestaRecuperoInput) {
        respuestaRecuperoInput.addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('is-invalid');
            }
        });
    }
    
    if (nuevaContrasenaInput) {
        nuevaContrasenaInput.addEventListener('input', function() {
            if (this.value && this.value.length >= 6) {
                this.classList.remove('is-invalid');
            }
        });
    }
    
    // ========================================================================
    // Animación suave de entrada
    // ========================================================================
    const authCard = document.querySelector('.auth-card');
    if (authCard) {
        authCard.style.opacity = '0';
        authCard.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            authCard.style.transition = 'all 0.5s ease';
            authCard.style.opacity = '1';
            authCard.style.transform = 'translateY(0)';
        }, 100);
    }
});

