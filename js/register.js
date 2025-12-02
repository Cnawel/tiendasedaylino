// ============================================================================
// REGISTER - JavaScript para mejorar UX
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');
    const nombreInput = document.getElementById('nombre');
    const apellidoInput = document.getElementById('apellido');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirm');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const togglePasswordConfirmBtn = document.getElementById('togglePasswordConfirm');
    const registerBtn = document.getElementById('registerBtn');
    const aceptaCheckbox = document.getElementById('acepta');
    const strengthMeterFill = document.getElementById('strengthMeterFill');
    const strengthText = document.getElementById('strengthText');
    const fechaNacimientoInput = document.getElementById('fecha_nacimiento');
    const preguntaRecuperoInput = document.getElementById('pregunta_recupero');
    const respuestaRecuperoInput = document.getElementById('respuesta_recupero');
    
    // ========================================================================
    // Función centralizada para validar campo completo
    // Valida cada campo específico y retorna estado de validación
    // ========================================================================
    /**
     * Valida un campo completo y aplica clases de validación
     * @param {string} campoId - ID del campo a validar ('nombre', 'apellido', 'email', etc.)
     * @param {HTMLElement} input - Elemento input a validar
     * @returns {Object} {valido: boolean, mensaje: string}
     */
    function validarCampoCompleto(campoId, input) {
        if (!input) {
            return { valido: false, mensaje: 'Campo no encontrado' };
        }
        
        const valor = input.value;
        const valorTrimmed = valor.trim();
        
        switch (campoId) {
            case 'nombre':
                if (!valorTrimmed) {
                    return { valido: false, mensaje: 'El nombre es obligatorio.' };
                } else if (typeof validarNombreApellido === 'function') {
                    // Usar función consolidada de common_js_functions.php
                    // NOTA: Límite máximo 100 caracteres para coincidir con validación PHP en admin_functions.php
                    const esValido = validarNombreApellido(valorTrimmed, 2, 100);
                    if (!esValido) {
                        if (valorTrimmed.length < 2) {
                            return { valido: false, mensaje: 'El nombre debe tener al menos 2 caracteres.' };
                        } else if (valorTrimmed.length > 100) {
                            return { valido: false, mensaje: 'El nombre no puede exceder 100 caracteres.' };
                        } else {
                            return { valido: false, mensaje: 'El nombre solo puede contener letras, espacios, apóstrofe (\') y acento agudo (´).' };
                        }
                    }
                } else {
                    // Fallback si la función no está disponible
                    const nombrePattern = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]+$/;
                    if (valorTrimmed.length < 2) {
                        return { valido: false, mensaje: 'El nombre debe tener al menos 2 caracteres.' };
                    } else if (valorTrimmed.length > 100) {
                        return { valido: false, mensaje: 'El nombre no puede exceder 100 caracteres.' };
                    } else if (!nombrePattern.test(valorTrimmed)) {
                        return { valido: false, mensaje: 'El nombre solo puede contener letras, espacios, apóstrofe (\') y acento agudo (´).' };
                    }
                }
                return { valido: true, mensaje: '' };
                
            case 'apellido':
                if (!valorTrimmed) {
                    // Apellido es obligatorio
                    return { valido: false, mensaje: 'El apellido es obligatorio.' };
                } else if (typeof validarNombreApellido === 'function') {
                    // Usar función consolidada de common_js_functions.php
                    const esValido = validarNombreApellido(valorTrimmed, 2, 100);
                    if (!esValido) {
                        if (valorTrimmed.length < 2) {
                            return { valido: false, mensaje: 'El apellido debe tener al menos 2 caracteres.' };
                        } else if (valorTrimmed.length > 100) {
                            return { valido: false, mensaje: 'El apellido no puede exceder 100 caracteres.' };
                        } else {
                            return { valido: false, mensaje: 'El apellido solo puede contener letras, espacios, apóstrofe (\') y acento agudo (´).' };
                        }
                    }
                } else {
                    // Fallback si la función no está disponible
                    const apellidoPattern = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]+$/;
                    if (valorTrimmed.length < 2) {
                        return { valido: false, mensaje: 'El apellido debe tener al menos 2 caracteres.' };
                    } else if (valorTrimmed.length > 100) {
                        return { valido: false, mensaje: 'El apellido no puede exceder 100 caracteres.' };
                    } else if (!apellidoPattern.test(valorTrimmed)) {
                        return { valido: false, mensaje: 'El apellido solo puede contener letras, espacios, apóstrofe (\') y acento agudo (´).' };
                    }
                }
                return { valido: true, mensaje: '' };
                
            case 'email':
                if (!valorTrimmed) {
                    return { valido: false, mensaje: 'El correo electrónico es obligatorio.' };
                } else if (valorTrimmed.length < 6 || valorTrimmed.length > 100) {
                    // NOTA: Límite máximo 100 caracteres según diccionario de datos
                    return { valido: false, mensaje: 'El correo electrónico debe tener entre 6 y 100 caracteres.' };
                } else {
                    // Usar función centralizada validateEmail() de common_js_functions.php
                    if (typeof validateEmail === 'function' && !validateEmail(valorTrimmed)) {
                        return { valido: false, mensaje: 'El formato del correo electrónico no es válido.' };
                    } else if (typeof validateEmail !== 'function') {
                        // Fallback si la función no está disponible
                        const estructuraBasica = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!estructuraBasica.test(valorTrimmed)) {
                            return { valido: false, mensaje: 'El formato del correo electrónico no es válido.' };
                        }
                    }
                }
                return { valido: true, mensaje: '' };
                
            case 'fecha_nacimiento':
                if (!valor) {
                    return { valido: false, mensaje: 'La fecha de nacimiento es obligatoria.' };
                }
                const fechaSeleccionada = new Date(valor);
                const hoy = new Date();
                hoy.setHours(0, 0, 0, 0);
                const edadMinima = new Date();
                edadMinima.setFullYear(hoy.getFullYear() - 13);
                
                if (fechaSeleccionada > hoy) {
                    return { valido: false, mensaje: 'La fecha de nacimiento no puede ser una fecha futura.' };
                } else if (fechaSeleccionada > edadMinima) {
                    return { valido: false, mensaje: 'Debes tener al menos 13 años para registrarte.' };
                }
                
                return { valido: true, mensaje: '' };
                
            case 'pregunta_recupero':
                if (!valor || valor === '') {
                    return { valido: false, mensaje: 'Debes seleccionar una pregunta de recupero.' };
                }
                return { valido: true, mensaje: '' };
                
            case 'respuesta_recupero':
                const respuestaPattern = /^[a-zA-Z0-9 ]+$/;
                if (!valorTrimmed) {
                    return { valido: false, mensaje: 'La respuesta de recupero es obligatoria.' };
                } else if (valorTrimmed.length < 4) {
                    return { valido: false, mensaje: 'La respuesta de recupero debe tener al menos 4 caracteres.' };
                } else if (valorTrimmed.length > 20) {
                    return { valido: false, mensaje: 'La respuesta de recupero no puede exceder 20 caracteres.' };
                } else if (!respuestaPattern.test(valorTrimmed)) {
                    return { valido: false, mensaje: 'La respuesta de recupero solo puede contener letras, números y espacios.' };
                }
                return { valido: true, mensaje: '' };
                
            case 'password':
                if (!valor) {
                    return { valido: false, mensaje: 'La contraseña es obligatoria.' };
                } else if (valor.length < 6) {
                    return { valido: false, mensaje: 'La contraseña debe tener al menos 6 caracteres.' };
                } else if (valor.length > 20) {
                    return { valido: false, mensaje: 'La contraseña no puede exceder 20 caracteres.' };
                }
                return { valido: true, mensaje: '' };
                
            case 'password_confirm':
                if (!valor) {
                    return { valido: false, mensaje: 'La confirmación de contraseña es obligatoria.' };
                } else if (passwordInput.value !== valor) {
                    return { valido: false, mensaje: 'Las contraseñas no coinciden.' };
                }
                return { valido: true, mensaje: '' };
                
            case 'acepta':
                if (!input.checked) {
                    return { valido: false, mensaje: 'Debes aceptar los términos y condiciones para continuar.' };
                }
                return { valido: true, mensaje: '' };
                
            default:
                return { valido: false, mensaje: 'Campo desconocido' };
        }
    }
    
    /**
     * Aplica clases de validación y muestra mensaje de error/éxito específico por campo
     * Usa funciones consolidadas de common_js_functions.php
     * @param {HTMLElement} input - Elemento input a validar
     * @param {Object} resultado - Resultado de validarCampoCompleto()
     */
    function aplicarValidacionCampo(input, resultado) {
        if (!input) return;
        
        // Buscar elementos de feedback
        const container = input.closest('.mb-3') || input.closest('.form-check');
        const invalidFeedback = container?.querySelector('.invalid-feedback');
        const validFeedback = container?.querySelector('.valid-feedback');
        
        if (resultado.valido) {
            if (typeof setFieldValidation === 'function') {
                setFieldValidation(input, true);
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
            }
            // Limpiar mensajes de error si existen
            if (invalidFeedback) {
                invalidFeedback.style.display = 'none';
            }
            // Mostrar mensaje de éxito si existe (solo para algunos campos)
            if (validFeedback && (input.id === 'email' || input.id === 'password_confirm')) {
                validFeedback.style.display = 'block';
            }
        } else {
            // Usar función consolidada para mostrar error
            if (typeof mostrarErrorCampo === 'function') {
                mostrarErrorCampo(input, invalidFeedback, resultado.mensaje || '');
            } else {
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
                if (invalidFeedback) {
                    invalidFeedback.textContent = resultado.mensaje || '';
                    invalidFeedback.style.display = 'block';
                }
            }
            // Ocultar mensaje de éxito si existe
            if (validFeedback) {
                validFeedback.style.display = 'none';
            }
        }
    }
    
    // ========================================================================
    // Validación en tiempo real campo por campo
    // Usa función centralizada validarCampoCompleto() para validación progresiva
    // ========================================================================
    
    // Validación en tiempo real de nombre
    nombreInput.addEventListener('input', function() {
        const resultado = validarCampoCompleto('nombre', this);
        if (this.value.trim() === '') {
            if (typeof setFieldValidation === 'function') {
                setFieldValidation(this, null);
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        } else {
            aplicarValidacionCampo(this, resultado);
        }
    });
    
    nombreInput.addEventListener('blur', function() {
        const resultado = validarCampoCompleto('nombre', this);
        aplicarValidacionCampo(this, resultado);
    });
    
    // Validación en tiempo real de apellido (obligatorio)
    apellidoInput.addEventListener('input', function() {
        const resultado = validarCampoCompleto('apellido', this);
        if (this.value.trim() === '') {
            // No mostrar validación mientras está vacío durante el input
            this.classList.remove('is-valid', 'is-invalid');
        } else {
            aplicarValidacionCampo(this, resultado);
        }
    });
    
    apellidoInput.addEventListener('blur', function() {
        const resultado = validarCampoCompleto('apellido', this);
        aplicarValidacionCampo(this, resultado);
    });
    
    // Validación en tiempo real del email
    emailInput.addEventListener('input', function() {
        const resultado = validarCampoCompleto('email', this);
        if (this.value.trim() === '') {
            if (typeof setFieldValidation === 'function') {
                setFieldValidation(this, null);
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        } else {
            aplicarValidacionCampo(this, resultado);
        }
    });
    
    emailInput.addEventListener('blur', function() {
        const resultado = validarCampoCompleto('email', this);
        aplicarValidacionCampo(this, resultado);
    });
    
    // ========================================================================
    // Medidor de fortaleza de contraseña y validación en tiempo real
    // ========================================================================
    passwordInput.addEventListener('input', function() {
        checkPasswordStrength(this.value);
        // Validar usando función centralizada
        const resultado = validarCampoCompleto('password', this);
        if (this.value.length === 0) {
            this.classList.remove('is-valid', 'is-invalid');
        } else {
            aplicarValidacionCampo(this, resultado);
        }
    });
    
    passwordInput.addEventListener('blur', function() {
        // Validar al perder el foco usando función centralizada
        const resultado = validarCampoCompleto('password', this);
        aplicarValidacionCampo(this, resultado);
    });
    
    function checkPasswordStrength(password) {
        let strength = 0;
        let strengthLevel = '';
        let strengthColor = '';
        
        if (password.length === 0) {
            strengthMeterFill.style.width = '0%';
            strengthText.textContent = '';
            return;
        }
        
        // ========================================================================
        // CRITERIOS DE FORTALEZA (solo informativo, no bloquea registro)
        // ========================================================================
        
        // Longitud mínima (6 caracteres ahora, coincidiendo con PHP)
        if (password.length >= 6) strength += 30;
        if (password.length >= 12) strength += 10;
        if (password.length >= 16) strength += 10;
        if (password.length >= 20) strength += 10;
        
        // Caracteres opcionales (mejoran la fortaleza pero no son requeridos)
        if (/[a-z]/.test(password)) strength += 10; // Minúscula
        if (/[A-Z]/.test(password)) strength += 10; // Mayúscula
        if (/[0-9]/.test(password)) strength += 10; // Número
        if (/[@$!%*?&]/.test(password)) strength += 10; // Carácter especial específico
        
        // Determinar nivel de fortaleza
        if (strength < 40) {
            strengthLevel = 'Muy Débil';
            strengthColor = '#dc3545';
        } else if (strength < 60) {
            strengthLevel = 'Débil';
            strengthColor = '#fd7e14';
        } else if (strength < 75) {
            strengthLevel = 'Buena';
            strengthColor = '#ffc107';
        } else if (strength < 90) {
            strengthLevel = 'Fuerte';
            strengthColor = '#17a2b8';
        } else {
            strengthLevel = 'Excelente';
            strengthColor = '#28a745';
        }
        
        // Actualizar interfaz visual
        strengthMeterFill.style.width = strength + '%';
        strengthMeterFill.style.backgroundColor = strengthColor;
        strengthText.textContent = 'Fortaleza: ' + strengthLevel;
        strengthText.style.color = strengthColor;
        
        // Validar confirmación si ya tiene valor
        if (passwordConfirmInput.value) {
            const resultado = validarCampoCompleto('password_confirm', passwordConfirmInput);
            aplicarValidacionCampo(passwordConfirmInput, resultado);
        }
    }
    
    // ========================================================================
    // Toggle mostrar/ocultar contraseñas
    // Usa la función togglePassword de common_js_functions.php
    // ========================================================================
    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Usar función consolidada de common_js_functions.php
            if (typeof togglePassword === 'function') {
                togglePassword(passwordInput.id || passwordInput);
            }
            
            return false;
        });
    }
    
    if (togglePasswordConfirmBtn && passwordConfirmInput) {
        togglePasswordConfirmBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Usar función consolidada de common_js_functions.php
            if (typeof togglePassword === 'function') {
                togglePassword(passwordConfirmInput.id || passwordConfirmInput);
            }
            
            return false;
        });
    }
    
    // ========================================================================
    // Validación de confirmación de contraseña usando función centralizada
    // ========================================================================
    passwordConfirmInput.addEventListener('input', function() {
        const resultado = validarCampoCompleto('password_confirm', this);
        if (this.value === '') {
            this.classList.remove('is-valid', 'is-invalid');
        } else {
            aplicarValidacionCampo(this, resultado);
        }
    });
    
    passwordConfirmInput.addEventListener('blur', function() {
        const resultado = validarCampoCompleto('password_confirm', this);
        if (this.value === '') {
            this.classList.remove('is-valid', 'is-invalid');
        } else {
            aplicarValidacionCampo(this, resultado);
        }
    });
    
    // ========================================================================
    // Validación del formulario al enviar - usando función centralizada
    // ========================================================================
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            try {
                let isValid = true;
                
                // Validar todos los campos usando función centralizada
                const campos = [
                    { id: 'nombre', input: nombreInput },
                    { id: 'apellido', input: apellidoInput },
                    { id: 'email', input: emailInput },
                    { id: 'fecha_nacimiento', input: fechaNacimientoInput },
                    { id: 'pregunta_recupero', input: preguntaRecuperoInput },
                    { id: 'respuesta_recupero', input: respuestaRecuperoInput },
                    { id: 'password', input: passwordInput },
                    { id: 'password_confirm', input: passwordConfirmInput },
                    { id: 'acepta', input: aceptaCheckbox }
                ];
                
                // Validar cada campo y aplicar clases de validación
                campos.forEach(function(campo) {
                    if (!campo.input) return;
                    
                    try {
                        const resultado = validarCampoCompleto(campo.id, campo.input);
                        aplicarValidacionCampo(campo.input, resultado);
                        
                        if (!resultado.valido) {
                            isValid = false;
                        }
                    } catch (err) {
                        // Si hay error en la validación de un campo, continuar con los demás
                        console.error('Error validando campo ' + campo.id + ':', err);
                    }
                });
                
                // Si no es válido, prevenir envío
                if (!isValid) {
                    e.preventDefault();
                    if (registerForm && typeof scrollToFirstError === 'function') {
                        scrollToFirstError(registerForm);
                    }
                    return;
                }
                
                // Mostrar estado de carga
                if (registerBtn) {
                    const btnText = registerBtn.querySelector('.btn-text');
                    const btnLoading = registerBtn.querySelector('.btn-loading');
                    if (btnText) btnText.classList.add('d-none');
                    if (btnLoading) btnLoading.classList.remove('d-none');
                    registerBtn.disabled = true;
                }
            } catch (err) {
                // Si hay un error crítico, permitir que el formulario se envíe
                // La validación del servidor se encargará de validar los datos
                console.error('Error en validación del formulario:', err);
                // No prevenir el envío, dejar que el formulario se envíe normalmente
            }
        });
    }
    
    // ========================================================================
    // Validación en tiempo real de campos restantes usando función centralizada
    // ========================================================================
    
    // Validación en tiempo real de fecha de nacimiento
    // Agregar eventos 'input', 'change' y 'blur' para validación progresiva completa
    if (fechaNacimientoInput) {
        // Evento 'input' para validar mientras el usuario selecciona la fecha
        fechaNacimientoInput.addEventListener('input', function() {
            const resultado = validarCampoCompleto('fecha_nacimiento', this);
            if (!this.value) {
                this.classList.remove('is-valid', 'is-invalid');
            } else {
                aplicarValidacionCampo(this, resultado);
            }
        });
        
        // Evento 'change' para validar cuando se completa la selección
        fechaNacimientoInput.addEventListener('change', function() {
            const resultado = validarCampoCompleto('fecha_nacimiento', this);
            if (!this.value) {
                this.classList.remove('is-valid', 'is-invalid');
            } else {
                aplicarValidacionCampo(this, resultado);
            }
        });
        
        // Evento 'blur' para validar al perder el foco
        fechaNacimientoInput.addEventListener('blur', function() {
            const resultado = validarCampoCompleto('fecha_nacimiento', this);
            aplicarValidacionCampo(this, resultado);
        });
    }
    
    // Validación en tiempo real de pregunta de recupero
    if (preguntaRecuperoInput) {
        preguntaRecuperoInput.addEventListener('change', function() {
            const resultado = validarCampoCompleto('pregunta_recupero', this);
            aplicarValidacionCampo(this, resultado);
        });

        preguntaRecuperoInput.addEventListener('blur', function() {
            const resultado = validarCampoCompleto('pregunta_recupero', this);
            aplicarValidacionCampo(this, resultado);
        });
    }
    
    // Validación en tiempo real de respuesta de recupero
    if (respuestaRecuperoInput) {
        respuestaRecuperoInput.addEventListener('input', function() {
            const resultado = validarCampoCompleto('respuesta_recupero', this);
            if (this.value.trim() === '') {
                this.classList.remove('is-valid', 'is-invalid');
            } else {
                aplicarValidacionCampo(this, resultado);
            }
        });

        respuestaRecuperoInput.addEventListener('blur', function() {
            const resultado = validarCampoCompleto('respuesta_recupero', this);
            aplicarValidacionCampo(this, resultado);
        });
    }
    
    // Validación en tiempo real de aceptación de términos y condiciones
    if (aceptaCheckbox) {
        aceptaCheckbox.addEventListener('change', function() {
            const resultado = validarCampoCompleto('acepta', this);
            aplicarValidacionCampo(this, resultado);
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

