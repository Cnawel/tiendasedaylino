/**
 * ========================================================================
 * VALIDACIÓN DE ADMINISTRACIÓN - Tienda Seda y Lino
 * ========================================================================
 * Funciones JavaScript para validación en tiempo real en el panel de administración
 * 
 * FUNCIONES:
 * - validarNombreApellido(): Valida nombre o apellido
 * - inicializarValidacionEdicion(): Inicializa validación en formularios de edición
 * - validarCoincidenciaContrasenaEdicion(): Valida coincidencia de contraseñas en edición
 * 
 * NOTA: Las siguientes funciones están disponibles en common_js_functions.php (incluido globalmente):
 * - confirmLogout(), togglePassword(), validateEmail(), validarContrasenaUsuario(), etc.
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * NOTA: Las siguientes funciones están disponibles en common_js_functions.php (incluido globalmente):
 * - confirmLogout()
 * - togglePassword() (también disponible como togglePasswordStaff)
 * - validarCoincidenciaPassword() (también disponible como validarCoincidenciaPasswordStaff)
 * - validateEmail() y validateEmailInput()
 * 
 * Estas funciones se cargan automáticamente en footer.php antes de este script.
 */

/**
 * Valida nombre o apellido (solo letras y espacios, mínimo 2 caracteres)
 * 
 * NOTA: Existe versión PHP equivalente en admin_functions.php
 * Ambas versiones deben mantener la misma lógica de validación.
 * 
 * @param {string} valor - Valor a validar
 * @returns {boolean} - true si es válido
 */
function validarNombreApellido(valor) {
    const re = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]+$/;
    return valor.trim().length >= 2 && re.test(valor.trim());
}

/**
 * Inicializar validación en tiempo real para campos de edición cuando se abre un modal
 * @param {string} modalId - ID del modal
 */
function inicializarValidacionEdicion(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    // Esperar a que el modal esté completamente visible
    modal.addEventListener('shown.bs.modal', function() {
        const userId = modalId.replace('cambiarRolModal', '');
        
        // Validar nombre
        const nombreInput = document.getElementById('edit_nombre_' + userId);
        if (nombreInput) {
            nombreInput.addEventListener('input', function() {
                const value = this.value.trim();
                if (value === '') {
                    this.classList.remove('is-valid', 'is-invalid');
                } else if (validarNombreApellido(value)) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });
        }
        
        // Validar apellido
        const apellidoInput = document.getElementById('edit_apellido_' + userId);
        if (apellidoInput) {
            apellidoInput.addEventListener('input', function() {
                const value = this.value.trim();
                if (value === '') {
                    this.classList.remove('is-valid', 'is-invalid');
                } else if (validarNombreApellido(value)) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });
        }
        
        // Validar email
        const emailInput = document.getElementById('edit_email_' + userId);
        if (emailInput) {
            emailInput.addEventListener('input', function() {
                const value = this.value.trim();
                if (value === '') {
                    this.classList.remove('is-valid', 'is-invalid');
                } else if (typeof validateEmail === 'function' && validateEmail(value)) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });
        }
        
        // Validar contraseña nueva
        const nuevaContrasenaInput = document.getElementById('nueva_contrasena_' + userId);
        if (nuevaContrasenaInput) {
            nuevaContrasenaInput.addEventListener('input', function() {
                const value = this.value;
                const confirmarInput = document.getElementById('confirmar_contrasena_' + userId);
                
                if (value === '') {
                    this.classList.remove('is-valid', 'is-invalid');
                    // Si ambos están vacíos, limpiar validación del confirmar
                    if (confirmarInput && confirmarInput.value === '') {
                        confirmarInput.classList.remove('is-valid', 'is-invalid');
                    }
                } else {
                    // Validar longitud: mínimo 6 caracteres, máximo 32
                    if (value.length >= 6 && value.length <= 32) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.remove('is-valid');
                        this.classList.add('is-invalid');
                    }
                }
                
                // Validar coincidencia
                if (confirmarInput && confirmarInput.value !== '') {
                    validarCoincidenciaContrasenaEdicion(userId);
                }
            });
        }
        
        // Validar confirmar contraseña
        const confirmarContrasenaInput = document.getElementById('confirmar_contrasena_' + userId);
        if (confirmarContrasenaInput) {
            confirmarContrasenaInput.addEventListener('input', function() {
                validarCoincidenciaContrasenaEdicion(userId);
            });
        }
    });
}

/**
 * Valida que las contraseñas coincidan en el formulario de edición
 * @param {string} userId - ID del usuario
 */
function validarCoincidenciaContrasenaEdicion(userId) {
    const nuevaContrasena = document.getElementById('nueva_contrasena_' + userId);
    const confirmarContrasena = document.getElementById('confirmar_contrasena_' + userId);
    const feedbackError = document.getElementById('password-match-feedback-' + userId);
    const feedbackSuccess = document.getElementById('password-match-success-' + userId);
    
    if (!nuevaContrasena || !confirmarContrasena) return;
    
    const nuevaValue = nuevaContrasena.value;
    const confirmarValue = confirmarContrasena.value;
    
    // Si ambos están vacíos, no mostrar validación
    if (nuevaValue === '' && confirmarValue === '') {
        confirmarContrasena.classList.remove('is-valid', 'is-invalid');
        if (feedbackError) feedbackError.style.display = 'none';
        if (feedbackSuccess) feedbackSuccess.style.display = 'none';
        return;
    }
    
    // Si solo uno está lleno, mostrar error
    if ((nuevaValue === '' && confirmarValue !== '') || (nuevaValue !== '' && confirmarValue === '')) {
        confirmarContrasena.classList.remove('is-valid');
        confirmarContrasena.classList.add('is-invalid');
        if (feedbackError) {
            feedbackError.textContent = 'Debes completar ambos campos de contraseña o dejarlos vacíos.';
            feedbackError.style.display = 'block';
        }
        if (feedbackSuccess) feedbackSuccess.style.display = 'none';
        return;
    }
    
    // Si ambos tienen valor, validar coincidencia
    if (nuevaValue === confirmarValue && nuevaContrasena.classList.contains('is-valid')) {
        confirmarContrasena.classList.remove('is-invalid');
        confirmarContrasena.classList.add('is-valid');
        if (feedbackError) feedbackError.style.display = 'none';
        if (feedbackSuccess) feedbackSuccess.style.display = 'block';
    } else {
        confirmarContrasena.classList.remove('is-valid');
        confirmarContrasena.classList.add('is-invalid');
        if (feedbackError) {
            feedbackError.textContent = 'Las contraseñas no coinciden.';
            feedbackError.style.display = 'block';
        }
        if (feedbackSuccess) feedbackSuccess.style.display = 'none';
    }
}

/**
 * NOTA: validarContrasenaUsuario() y validarContrasenaUsuarioModal() están disponibles en common_js_functions.php
 * Se incluyen globalmente en footer.php, usar directamente las funciones globales
 */

// ============================================================================
// VALIDACIÓN EN TIEMPO REAL - FORMULARIO CREAR USUARIO STAFF
// ============================================================================

// Agregar listeners para validación en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    // Validación campo Nombre
    const nombreInput = document.getElementById('nombre_staff');
    if (nombreInput) {
        nombreInput.addEventListener('input', function() {
            const value = this.value.trim();
            if (value === '') {
                this.classList.remove('is-valid', 'is-invalid');
            } else if (validarNombreApellido(value)) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    }
    
    // Validación campo Apellido
    const apellidoInput = document.getElementById('apellido_staff');
    if (apellidoInput) {
        apellidoInput.addEventListener('input', function() {
            const value = this.value.trim();
            if (value === '') {
                this.classList.remove('is-valid', 'is-invalid');
            } else if (validarNombreApellido(value)) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    }
    
    // Validación campo Email
    const emailInput = document.getElementById('email_staff');
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            const value = this.value.trim();
            if (value === '') {
                this.classList.remove('is-valid', 'is-invalid');
            } else if (validateEmail(value)) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    }
    
    // Validación contraseñas
    const passwordInput = document.getElementById('password_temporal');
    const confirmarInput = document.getElementById('confirmar_password_temporal');
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const value = this.value;
            if (value === '') {
                this.classList.remove('is-valid', 'is-invalid');
            } else if (value.length >= 6 && value.length <= 32) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
            // Validar coincidencia cuando cambia la contraseña usando función consolidada
            if (confirmarInput) {
                if (typeof validarCoincidenciaPasswordStaff === 'function') {
                    validarCoincidenciaPasswordStaff();
                }
            }
        });
    }
    
    if (confirmarInput) {
        confirmarInput.addEventListener('input', function() {
            // Usar función consolidada de common_js_functions.php
            if (typeof validarCoincidenciaPasswordStaff === 'function') {
                validarCoincidenciaPasswordStaff();
            }
        });
    }
    
    // Validar antes de enviar el formulario
    const formStaff = document.querySelector('form input[name="crear_usuario_staff"]')?.closest('form');
    if (formStaff) {
        formStaff.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validar nombre
            if (!validarNombreApellido(nombreInput?.value || '')) {
                nombreInput?.classList.add('is-invalid');
                isValid = false;
            }
            
            // Validar apellido
            if (!validarNombreApellido(apellidoInput?.value || '')) {
                apellidoInput?.classList.add('is-invalid');
                isValid = false;
            }
            
            // Validar email
            if (!validateEmail(emailInput?.value || '')) {
                emailInput?.classList.add('is-invalid');
                isValid = false;
            }
            
            // Validar contraseñas
            const password = passwordInput?.value || '';
            const confirmar = confirmarInput?.value || '';
            
            if (password.length < 6 || password.length > 32) {
                passwordInput?.classList.add('is-invalid');
                isValid = false;
            }
            
            if (password !== confirmar) {
                confirmarInput?.classList.add('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                scrollToFirstError(formStaff);
                return false;
            }
        });
    }
    
    // Inicializar validación para todos los modales de edición
    const modales = document.querySelectorAll('[id^="cambiarRolModal"]');
    modales.forEach(function(modal) {
        inicializarValidacionEdicion(modal.id);
    });
});

