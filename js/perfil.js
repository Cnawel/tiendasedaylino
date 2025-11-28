/**
 * ========================================================================
 * PERFIL - JavaScript para validación y UX
 * ========================================================================
 * Event listeners consolidados en un solo DOMContentLoaded
 * Usa funciones de common_js_functions.php
 * ========================================================================
 */

// Log inmediato para verificar que el archivo se carga
try {
    console.log('[Perfil] Script perfil.js cargado - Timestamp:', new Date().toISOString());
} catch(e) {
    // Si console no está disponible, intentar alert
    if (typeof alert !== 'undefined') {
        alert('Perfil.js cargado pero console no disponible');
    }
}

// Verificar si el DOM ya está listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('[Perfil] DOMContentLoaded ejecutado');
        inicializarPerfil();
    });
} else {
    // DOM ya está listo
    console.log('[Perfil] DOM ya está listo, ejecutando inmediatamente');
    inicializarPerfil();
}

function inicializarPerfil() {
    // ====================================================================
    // 1. Event listeners para botones de toggle password
    // Reemplaza onclick inline, usa la función togglePassword de common_js_functions.php
    // ====================================================================
    const toggleButtons = document.querySelectorAll('.btn-toggle-password');
    
    toggleButtons.forEach(function(button) {
        // Si el botón ya tiene onclick, lo removemos y agregamos event listener
        if (button.hasAttribute('onclick')) {
            const onclickValue = button.getAttribute('onclick');
            // Extraer el inputId del onclick (ej: "togglePassword('contrasena_actual')")
            const match = onclickValue.match(/togglePassword\(['"]([^'"]+)['"]\)/);
            if (match) {
                const inputId = match[1];
                button.removeAttribute('onclick');
                button.setAttribute('data-input-id', inputId);
            }
        }
        
        // Agregar event listener
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const inputId = this.getAttribute('data-input-id');
            if (inputId) {
                // Usar función consolidada de common_js_functions.php
                togglePassword(inputId);
            } else {
                // Buscar el input anterior (hermano)
                const input = this.previousElementSibling;
                if (input && (input.type === 'password' || input.type === 'text')) {
                    togglePassword(input.id);
                }
            }
        });
    });
    
    // ====================================================================
    // 2. Validación de fecha de nacimiento (HTML5 date input)
    // ====================================================================
    const fechaInput = document.getElementById('fecha_nacimiento');
    const fechaFeedback = document.getElementById('fecha-nacimiento-feedback');
    
    if (fechaInput) {
        fechaInput.addEventListener('change', function() {
            // Limpiar feedback anterior
            this.classList.remove('is-invalid', 'is-valid');
            if (fechaFeedback) {
                fechaFeedback.textContent = '';
            }
            
            if (this.value) {
                const fechaSeleccionada = new Date(this.value);
                const hoy = new Date();
                hoy.setHours(0, 0, 0, 0);
                
                // Extraer año de la fecha seleccionada
                const añoSeleccionado = fechaSeleccionada.getFullYear();
                
                // Validar rango de año permitido (1925-2012)
                if (añoSeleccionado < 1925 || añoSeleccionado > 2012) {
                    this.classList.add('is-invalid');
                    if (fechaFeedback) {
                        fechaFeedback.textContent = 'La fecha de nacimiento debe estar entre 1925 y 2012';
                    }
                }
                // Validar que no sea futura
                else if (fechaSeleccionada > hoy) {
                    this.classList.add('is-invalid');
                    if (fechaFeedback) {
                        fechaFeedback.textContent = 'La fecha no puede ser futura';
                    }
                } else {
                    // Calcular edad
                    const edad = hoy.getFullYear() - fechaSeleccionada.getFullYear();
                    const mes = hoy.getMonth() - fechaSeleccionada.getMonth();
                    const edadCompleta = (mes < 0 || (mes === 0 && hoy.getDate() < fechaSeleccionada.getDate())) ? edad - 1 : edad;
                    
                    this.classList.add('is-valid');
                    if (fechaFeedback) {
                        fechaFeedback.textContent = 'Edad: ' + edadCompleta + ' años';
                    }
                }
            }
        });
    }
    
    // ====================================================================
    // 2.1. Validación de teléfono según diccionario de datos
    // Usa la función validarTelefono de common_js_functions.php
    // Longitud: 6-20 caracteres, solo [0-9, +, (, ), -]
    // ====================================================================
    const telefonoInput = document.getElementById('telefono');
    if (telefonoInput) {
        // Filtrado en tiempo real mientras se escribe (input)
        telefonoInput.addEventListener('input', function(e) {
            const value = e.target.value;
            // Patrón para filtrar: solo permitir [0-9, +, (, ), -]
            const validPattern = /^[0-9+()\- ]*$/;
            
            // Filtrar caracteres no permitidos
            if (!validPattern.test(value)) {
                e.target.value = value.replace(/[^0-9+()\- ]/g, '');
            }
            
            // Limitar longitud máxima
            if (e.target.value.length > 20) {
                e.target.value = e.target.value.substring(0, 20);
            }
            
            // Limpiar validación mientras se escribe
            if (this.classList.contains('is-invalid')) {
                this.classList.remove('is-invalid');
                const feedback = this.parentElement.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.textContent = '';
                    feedback.style.display = 'none';
                }
            }
        });
        
        // Validación al perder el foco usando función consolidada
        telefonoInput.addEventListener('blur', function() {
            if (typeof validarTelefono === 'function') {
                // Teléfono es opcional en perfil
                validarTelefono(this, true);
            }
        });
    }
    
    // ====================================================================
    // 2.2. Validación en tiempo real para nombre y apellido (formulario "Mis Datos")
    // Usa la función validarNombreApellido de common_js_functions.php
    // ====================================================================
    const nombreInputDatos = document.getElementById('nombre');
    const apellidoInputDatos = document.getElementById('apellido');
    
    // Validación en tiempo real de nombre
    if (nombreInputDatos) {
        nombreInputDatos.addEventListener('input', function() {
            const valor = this.value.trim();
            const pattern = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]*$/;
            
            // Limpiar validación mientras se escribe
            if (this.classList.contains('is-invalid')) {
                this.classList.remove('is-invalid');
            }
            
            // Filtrar caracteres no permitidos
            if (!pattern.test(valor)) {
                this.value = valor.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]/g, '');
            }
            
            // Limitar longitud máxima
            if (this.value.length > 50) {
                this.value = this.value.substring(0, 50);
            }
        });
        
        nombreInputDatos.addEventListener('blur', function() {
            const valor = this.value.trim();
            
            if (!valor) {
                this.classList.remove('is-valid', 'is-invalid');
            } else if (typeof validarNombreApellido === 'function') {
                const esValido = validarNombreApellido(valor, 2, 50);
                if (esValido) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                }
            }
        });
    }
    
    // Validación en tiempo real de apellido
    if (apellidoInputDatos) {
        apellidoInputDatos.addEventListener('input', function() {
            const valor = this.value.trim();
            const pattern = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]*$/;
            
            // Limpiar validación mientras se escribe
            if (this.classList.contains('is-invalid')) {
                this.classList.remove('is-invalid');
            }
            
            // Filtrar caracteres no permitidos
            if (!pattern.test(valor)) {
                this.value = valor.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]/g, '');
            }
            
            // Limitar longitud máxima
            if (this.value.length > 100) {
                this.value = this.value.substring(0, 100);
            }
        });
        
        apellidoInputDatos.addEventListener('blur', function() {
            const valor = this.value.trim();
            
            if (!valor) {
                // Apellido es opcional
                this.classList.remove('is-valid', 'is-invalid');
            } else if (typeof validarNombreApellido === 'function') {
                const esValido = validarNombreApellido(valor, 2, 100);
                if (esValido) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                }
            }
        });
    }
    
    // ====================================================================
    // 2.3. Validación submit para formulario "Mis Datos" (actualizar_datos)
    // ====================================================================
    const formActualizarDatos = document.querySelector('form[data-protect] button[name="actualizar_datos"]')?.closest('form');
    
    if (formActualizarDatos) {
        formActualizarDatos.addEventListener('submit', function(e) {
            let hayErrores = false;
            
            // Obtener referencias a los campos
            const nombreInput = document.getElementById('nombre');
            const apellidoInput = document.getElementById('apellido');
            const telefonoInput = document.getElementById('telefono');
            const fechaNacimientoInput = document.getElementById('fecha_nacimiento');
            
            // Validar nombre (obligatorio)
            // Usa la función validarNombreApellido de common_js_functions.php
            if (nombreInput) {
                const nombreValor = nombreInput.value.trim();
                
                if (!nombreValor) {
                    nombreInput.classList.add('is-invalid');
                    nombreInput.classList.remove('is-valid');
                    hayErrores = true;
                } else if (typeof validarNombreApellido === 'function') {
                    const esValido = validarNombreApellido(nombreValor, 2, 50);
                    if (esValido) {
                        nombreInput.classList.remove('is-invalid');
                        nombreInput.classList.add('is-valid');
                    } else {
                        nombreInput.classList.add('is-invalid');
                        nombreInput.classList.remove('is-valid');
                        hayErrores = true;
                    }
                } else {
                    // Fallback si la función no está disponible
                    const nombrePattern = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]+$/;
                    if (nombreValor.length < 2 || !nombrePattern.test(nombreValor)) {
                        nombreInput.classList.add('is-invalid');
                        nombreInput.classList.remove('is-valid');
                        hayErrores = true;
                    } else {
                        nombreInput.classList.remove('is-invalid');
                        nombreInput.classList.add('is-valid');
                    }
                }
            }
            
            // Validar apellido (obligatorio)
            // Usa la función validarNombreApellido de common_js_functions.php
            if (apellidoInput) {
                const apellidoValor = apellidoInput.value.trim();
                
                if (!apellidoValor) {
                    apellidoInput.classList.add('is-invalid');
                    apellidoInput.classList.remove('is-valid');
                    hayErrores = true;
                } else if (typeof validarNombreApellido === 'function') {
                    const esValido = validarNombreApellido(apellidoValor, 2, 100);
                    if (esValido) {
                        apellidoInput.classList.remove('is-invalid');
                        apellidoInput.classList.add('is-valid');
                    } else {
                        apellidoInput.classList.add('is-invalid');
                        apellidoInput.classList.remove('is-valid');
                        hayErrores = true;
                    }
                } else {
                    // Fallback si la función no está disponible
                    const apellidoPattern = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'´]+$/;
                    if (apellidoValor.length < 2 || apellidoValor.length > 100 || !apellidoPattern.test(apellidoValor)) {
                        apellidoInput.classList.add('is-invalid');
                        apellidoInput.classList.remove('is-valid');
                        hayErrores = true;
                    } else {
                        apellidoInput.classList.remove('is-invalid');
                        apellidoInput.classList.add('is-valid');
                    }
                }
            }
            
            // Validar teléfono (opcional, pero si tiene valor debe ser válido)
            // Usa la función validarTelefono de common_js_functions.php
            if (telefonoInput) {
                const telefonoValor = telefonoInput.value.trim();
                
                if (telefonoValor) {
                    // Si tiene valor, validar usando función consolidada
                    if (typeof validarTelefono === 'function') {
                        // Validar sin mostrar feedback (solo verificar)
                        const pattern = /^[0-9+()\- ]+$/;
                        if (telefonoValor.length < 6 || telefonoValor.length > 20 || !pattern.test(telefonoValor)) {
                            telefonoInput.classList.add('is-invalid');
                            telefonoInput.classList.remove('is-valid');
                            hayErrores = true;
                        } else {
                            telefonoInput.classList.remove('is-invalid');
                            telefonoInput.classList.add('is-valid');
                        }
                    } else {
                        // Fallback si la función no está disponible
                        const telefonoPattern = /^[0-9+()\-]+$/;
                        if (telefonoValor.length < 6 || telefonoValor.length > 20 || !telefonoPattern.test(telefonoValor)) {
                            telefonoInput.classList.add('is-invalid');
                            telefonoInput.classList.remove('is-valid');
                            hayErrores = true;
                        } else {
                            telefonoInput.classList.remove('is-invalid');
                            telefonoInput.classList.add('is-valid');
                        }
                    }
                } else {
                    // Si está vacío, limpiar validación (es opcional)
                    telefonoInput.classList.remove('is-invalid', 'is-valid');
                }
            }
            
            // Validar fecha de nacimiento (opcional, pero si tiene valor debe ser válida)
            if (fechaNacimientoInput) {
                const fechaValor = fechaNacimientoInput.value;
                
                if (fechaValor) {
                    // Si tiene valor, validar
                    const fechaSeleccionada = new Date(fechaValor);
                    const hoy = new Date();
                    hoy.setHours(0, 0, 0, 0);
                    
                    const añoSeleccionado = fechaSeleccionada.getFullYear();
                    
                    // Validar rango de año (1925-2012)
                    if (añoSeleccionado < 1925 || añoSeleccionado > 2012) {
                        fechaNacimientoInput.classList.add('is-invalid');
                        fechaNacimientoInput.classList.remove('is-valid');
                        if (fechaFeedback) {
                            fechaFeedback.textContent = 'La fecha de nacimiento debe estar entre 1925 y 2012';
                        }
                        hayErrores = true;
                    }
                    // Validar que no sea futura
                    else if (fechaSeleccionada > hoy) {
                        fechaNacimientoInput.classList.add('is-invalid');
                        fechaNacimientoInput.classList.remove('is-valid');
                        if (fechaFeedback) {
                            fechaFeedback.textContent = 'La fecha no puede ser futura';
                        }
                        hayErrores = true;
                    }
                    // Validar edad mínima (13 años)
                    else {
                        const edad = hoy.getFullYear() - fechaSeleccionada.getFullYear();
                        const mes = hoy.getMonth() - fechaSeleccionada.getMonth();
                        const edadCompleta = (mes < 0 || (mes === 0 && hoy.getDate() < fechaSeleccionada.getDate())) ? edad - 1 : edad;
                        
                        if (edadCompleta < 13) {
                            fechaNacimientoInput.classList.add('is-invalid');
                            fechaNacimientoInput.classList.remove('is-valid');
                            if (fechaFeedback) {
                                fechaFeedback.textContent = 'Debes tener al menos 13 años';
                            }
                            hayErrores = true;
                        } else {
                            fechaNacimientoInput.classList.remove('is-invalid');
                            fechaNacimientoInput.classList.add('is-valid');
                            if (fechaFeedback) {
                                fechaFeedback.textContent = 'Edad: ' + edadCompleta + ' años';
                            }
                        }
                    }
                } else {
                    // Si está vacío, limpiar validación (es opcional)
                    fechaNacimientoInput.classList.remove('is-invalid', 'is-valid');
                    if (fechaFeedback) {
                        fechaFeedback.textContent = '';
                    }
                }
            }
            
            // Si hay errores, prevenir envío y scroll al primer error
            if (hayErrores) {
                e.preventDefault();
                
                // Scroll al primer campo con error
                const primerError = formActualizarDatos.querySelector('.is-invalid');
                if (primerError) {
                    scrollToFirstError(formActualizarDatos);
                    primerError.focus();
                }
                
                return false;
            }
            
            return true;
        });
    }
    
    // ====================================================================
    // 3. Validación de coincidencia de contraseñas
    // Usa la función validarContrasenaUsuario de common_js_functions.php
    // ====================================================================
    const nuevaContrasena = document.getElementById('nueva_contrasena');
    const confirmarContrasena = document.getElementById('confirmar_contrasena');
    const passwordMatch = document.getElementById('password-match');
    const formCambiarContrasena = document.getElementById('formCambiarContrasena');
    
    if (nuevaContrasena && confirmarContrasena) {
        // Validación en tiempo real usando función consolidada
        function validatePasswordMatch() {
            if (confirmarContrasena.value === '') {
                if (passwordMatch) passwordMatch.innerHTML = '';
                return;
            }
            
            if (nuevaContrasena.value === confirmarContrasena.value) {
                if (passwordMatch) {
                    passwordMatch.innerHTML = '<small class="text-success"><i class="fas fa-check-circle me-1"></i>Las contraseñas coinciden</small>';
                }
                confirmarContrasena.setCustomValidity('');
            } else {
                if (passwordMatch) {
                    passwordMatch.innerHTML = '<small class="text-danger"><i class="fas fa-times-circle me-1"></i>Las contraseñas no coinciden</small>';
                }
                confirmarContrasena.setCustomValidity('Las contraseñas no coinciden');
            }
        }
        
        nuevaContrasena.addEventListener('input', validatePasswordMatch);
        confirmarContrasena.addEventListener('input', validatePasswordMatch);
        
        // Validación del formulario antes de enviar usando función consolidada
        if (formCambiarContrasena) {
            formCambiarContrasena.addEventListener('submit', function(e) {
                // Usar función consolidada de common_js_functions.php
                if (typeof validarContrasenaUsuario === 'function') {
                    if (!validarContrasenaUsuario(null, nuevaContrasena, confirmarContrasena, 6, 32)) {
                        e.preventDefault();
                        return false;
                    }
                } else {
                    // Fallback si la función no está disponible
                    if (nuevaContrasena.value !== confirmarContrasena.value) {
                        e.preventDefault();
                        if (passwordMatch) {
                            passwordMatch.innerHTML = '<small class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>Las contraseñas no coinciden</small>';
                        }
                        confirmarContrasena.focus();
                        return false;
                    }
                    
                    if (nuevaContrasena.value.length < 6 || nuevaContrasena.value.length > 32) {
                        e.preventDefault();
                        alert('La contraseña debe tener entre 6 y 32 caracteres');
                        nuevaContrasena.focus();
                        return false;
                    }
                }
                
                return true;
            });
        }
    }
    
    // ====================================================================
    // 4. Validación del código postal usando función consolidada
    // ====================================================================
    const codigoPostalInput = document.getElementById('codigo_postal');
    if (codigoPostalInput) {
        codigoPostalInput.addEventListener('input', function(e) {
            // Usar función consolidada de common_js_functions.php
            validarCodigoPostal(e.target);
        });
    }
    
    // ====================================================================
    // 5. Validación campo por campo para formulario de Datos de Envío
    // ====================================================================
    
    // ====================================================================
    // FUNCIONES AUXILIARES - Definidas primero para estar disponibles
    // ====================================================================
    
    /**
     * NOTA: La función mostrarFeedbackValidacion() está disponible
     * en common_js_functions.php (incluido globalmente en footer.php).
     * Usar esta función consolidada en lugar de la función local.
     */
    
    // Función para procesar mensajes de error del servidor y mostrarlos como feedback de campo
    function procesarMensajesServidor() {
        const alertMensaje = document.querySelector('.alert.alert-danger, .alert.alert-warning');
        if (!alertMensaje) return;
        
        const textoMensaje = alertMensaje.textContent.trim();
        
        // Mapeo de mensajes del servidor a campos y sus IDs
        const mapeoMensajes = {
            'La dirección (calle) es requerida': { campo: 'envio_direccion_calle', mensaje: 'La dirección es requerida' },
            'La dirección es requerida': { campo: 'envio_direccion_calle', mensaje: 'La dirección es requerida' },
            'El número de dirección es requerido': { campo: 'envio_direccion_numero', mensaje: 'El número es requerido' },
            'La provincia es requerida': { campo: 'envio_provincia', mensaje: 'La provincia es requerida' },
            'La provincia solo puede contener letras y espacios': { campo: 'envio_provincia', mensaje: 'La provincia solo puede contener letras y espacios' },
            'La localidad es requerida': { campo: 'envio_localidad', mensaje: 'La localidad es requerida' },
            'La localidad debe tener al menos 3 caracteres': { campo: 'envio_localidad', mensaje: 'La localidad debe tener al menos 3 caracteres' },
            'La localidad solo puede contener letras y espacios': { campo: 'envio_localidad', mensaje: 'Solo se permiten letras y espacios' },
            'El código postal es requerido': { campo: 'envio_codigo_postal', mensaje: 'El código postal es requerido' },
            'El código postal solo puede contener letras, números y espacios': { campo: 'envio_codigo_postal', mensaje: 'Solo se permiten letras, números y espacios' }
        };
        
        // Buscar coincidencia en el mapeo
        for (const [mensajeServidor, info] of Object.entries(mapeoMensajes)) {
            if (textoMensaje.includes(mensajeServidor) || textoMensaje === mensajeServidor) {
                const campo = document.getElementById(info.campo);
                if (campo) {
                    mostrarFeedbackValidacion(campo, false, info.mensaje);
                    // Ocultar el banner de alerta ya que el mensaje está en el campo
                    alertMensaje.style.display = 'none';
                    // Scroll al campo con error
                    campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    campo.focus();
                    break;
                }
            }
        }
    }
    
    // Función para inicializar validación de campos de envío
    function inicializarValidacionEnvio() {
        // Validación de dirección (calle)
        // Usa la función validarDireccion de common_js_functions.php
        const envioDireccionCalle = document.getElementById('envio_direccion_calle');
        if (envioDireccionCalle && !envioDireccionCalle.hasAttribute('data-validacion-inicializada')) {
            envioDireccionCalle.setAttribute('data-validacion-inicializada', 'true');
            
            envioDireccionCalle.addEventListener('blur', function() {
                if (typeof validarDireccion === 'function') {
                    validarDireccion(this, true, 2, 'calle');
                }
            });
            
            envioDireccionCalle.addEventListener('input', function(e) {
                // Filtrar caracteres no permitidos mientras se escribe
                const value = e.target.value;
                const validPattern = /^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]*$/;
                if (!validPattern.test(value)) {
                    e.target.value = value.replace(/[^A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]/g, '');
                }
                
                // Limpiar validación mientras se escribe
                if (this.classList.contains('is-invalid')) {
                    this.classList.remove('is-invalid');
                    const feedback = this.parentElement.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.textContent = '';
                        feedback.style.display = 'none';
                    }
                }
            });
        }
        
        // Validación de número de dirección
        // Usa la función validarDireccion de common_js_functions.php
        const envioDireccionNumero = document.getElementById('envio_direccion_numero');
        if (envioDireccionNumero && !envioDireccionNumero.hasAttribute('data-validacion-inicializada')) {
            envioDireccionNumero.setAttribute('data-validacion-inicializada', 'true');
            
            envioDireccionNumero.addEventListener('blur', function() {
                if (typeof validarDireccion === 'function') {
                    validarDireccion(this, true, 1, 'numero');
                }
            });
            
            envioDireccionNumero.addEventListener('input', function(e) {
                // Filtrar caracteres no permitidos mientras se escribe
                const value = e.target.value;
                const validPattern = /^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]*$/;
                if (!validPattern.test(value)) {
                    e.target.value = value.replace(/[^A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]/g, '');
                }
                
                // Limpiar validación mientras se escribe
                if (this.classList.contains('is-invalid')) {
                    this.classList.remove('is-invalid');
                    const feedback = this.parentElement.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.textContent = '';
                        feedback.style.display = 'none';
                    }
                }
            });
        }
        
        // Validación de piso/departamento
        // Usa la función validarDireccion de common_js_functions.php
        const envioDireccionPiso = document.getElementById('envio_direccion_piso');
        if (envioDireccionPiso && !envioDireccionPiso.hasAttribute('data-validacion-inicializada')) {
            envioDireccionPiso.setAttribute('data-validacion-inicializada', 'true');
            
            envioDireccionPiso.addEventListener('blur', function() {
                if (typeof validarDireccion === 'function') {
                    // Piso/Depto es opcional
                    validarDireccion(this, false, 1, 'piso');
                }
            });
            
            envioDireccionPiso.addEventListener('input', function(e) {
                // Filtrar caracteres no permitidos mientras se escribe
                const value = e.target.value;
                const validPattern = /^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]*$/;
                if (!validPattern.test(value)) {
                    e.target.value = value.replace(/[^A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\-'`]/g, '');
                }
                
                // Limpiar validación mientras se escribe
                if (this.classList.contains('is-invalid')) {
                    this.classList.remove('is-invalid');
                    const feedback = this.parentElement.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.textContent = '';
                        feedback.style.display = 'none';
                    }
                }
            });
        }
        
        // Validación de provincia (select)
        const envioProvincia = document.getElementById('envio_provincia');
        if (envioProvincia && !envioProvincia.hasAttribute('data-validacion-inicializada')) {
            envioProvincia.setAttribute('data-validacion-inicializada', 'true');
            
            envioProvincia.addEventListener('change', function() {
                const valor = this.value.trim();
                
                if (!valor) {
                    mostrarFeedbackValidacion(this, false, 'La provincia es requerida');
                } else {
                    mostrarFeedbackValidacion(this, true, '');
                }
            });
        }
        
        // Validación de localidad
        const envioLocalidad = document.getElementById('envio_localidad');
        if (envioLocalidad && !envioLocalidad.hasAttribute('data-validacion-inicializada')) {
            envioLocalidad.setAttribute('data-validacion-inicializada', 'true');
            
            envioLocalidad.addEventListener('blur', function() {
                const valor = this.value.trim();
                const pattern = /^[A-Za-z0-9 ]+$/;
                
                if (!valor) {
                    mostrarFeedbackValidacion(this, false, 'La localidad es requerida');
                } else if (valor.length < 3) {
                    mostrarFeedbackValidacion(this, false, 'La localidad debe tener al menos 3 caracteres');
                } else if (!pattern.test(valor)) {
                    mostrarFeedbackValidacion(this, false, 'Solo se permiten letras, números y espacios');
                } else {
                    mostrarFeedbackValidacion(this, true, '');
                }
            });
            
            envioLocalidad.addEventListener('input', function(e) {
                // Filtrar caracteres no permitidos mientras se escribe
                const value = e.target.value;
                const validPattern = /^[A-Za-z0-9 ]*$/;
                if (!validPattern.test(value)) {
                    e.target.value = value.replace(/[^A-Za-z0-9 ]/g, '');
                }
                
                // Limpiar validación mientras se escribe
                if (this.classList.contains('is-invalid')) {
                    this.classList.remove('is-invalid');
                    const feedback = this.parentElement.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.textContent = '';
                        feedback.style.display = 'none';
                    }
                }
            });
        }
        
        // Validación de código postal
        const envioCodigoPostal = document.getElementById('envio_codigo_postal');
        if (envioCodigoPostal && !envioCodigoPostal.hasAttribute('data-validacion-inicializada')) {
            envioCodigoPostal.setAttribute('data-validacion-inicializada', 'true');
            
            envioCodigoPostal.addEventListener('blur', function() {
                const valor = this.value.trim();
                const pattern = /^[A-Za-z0-9 ]+$/;
                
                if (!valor) {
                    mostrarFeedbackValidacion(this, false, 'El código postal es requerido');
                } else if (!pattern.test(valor)) {
                    mostrarFeedbackValidacion(this, false, 'Solo se permiten letras, números y espacios');
                } else {
                    mostrarFeedbackValidacion(this, true, '');
                }
            });
            
            envioCodigoPostal.addEventListener('input', function(e) {
                // Filtrar caracteres no permitidos mientras se escribe
                const value = e.target.value;
                const validPattern = /^[A-Za-z0-9 ]*$/;
                if (!validPattern.test(value)) {
                    e.target.value = value.replace(/[^A-Za-z0-9 ]/g, '');
                }
                
                // Limpiar validación mientras se escribe
                if (this.classList.contains('is-invalid')) {
                    this.classList.remove('is-invalid');
                    const feedback = this.parentElement.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.textContent = '';
                        feedback.style.display = 'none';
                    }
                }
            });
        }
        
        // Validación antes de enviar el formulario de envío
        // Buscar el formulario dentro de la pestaña de envío
        const envioPane = document.getElementById('envio');
        const formEnvio = envioPane ? envioPane.querySelector('form[data-protect]') : document.querySelector('#envio form[data-protect]');
        if (formEnvio && !formEnvio.hasAttribute('data-validacion-inicializada')) {
            formEnvio.setAttribute('data-validacion-inicializada', 'true');
            
            formEnvio.addEventListener('submit', function(e) {
                let hayErrores = false;
                
                // Obtener referencias a los campos nuevamente para asegurar que estén disponibles
                const campoDireccion = document.getElementById('envio_direccion_calle');
                const campoNumero = document.getElementById('envio_direccion_numero');
                const campoPiso = document.getElementById('envio_direccion_piso');
                const campoProvincia = document.getElementById('envio_provincia');
                const campoLocalidad = document.getElementById('envio_localidad');
                const campoCodigoPostal = document.getElementById('envio_codigo_postal');
                
                // Validar dirección
                if (campoDireccion) {
                    campoDireccion.dispatchEvent(new Event('blur'));
                    if (campoDireccion.classList.contains('is-invalid')) {
                        hayErrores = true;
                    }
                }
                
                // Validar número
                if (campoNumero) {
                    campoNumero.dispatchEvent(new Event('blur'));
                    if (campoNumero.classList.contains('is-invalid')) {
                        hayErrores = true;
                    }
                }
                
                // Validar piso/departamento (opcional, solo si tiene valor)
                if (campoPiso && campoPiso.value.trim()) {
                    campoPiso.dispatchEvent(new Event('blur'));
                    if (campoPiso.classList.contains('is-invalid')) {
                        hayErrores = true;
                    }
                }
                
                // Validar provincia
                if (campoProvincia) {
                    campoProvincia.dispatchEvent(new Event('change'));
                    if (campoProvincia.classList.contains('is-invalid')) {
                        hayErrores = true;
                    }
                }
                
                // Validar localidad
                if (campoLocalidad) {
                    campoLocalidad.dispatchEvent(new Event('blur'));
                    if (campoLocalidad.classList.contains('is-invalid')) {
                        hayErrores = true;
                    }
                }
                
                // Validar código postal
                if (campoCodigoPostal) {
                    campoCodigoPostal.dispatchEvent(new Event('blur'));
                    if (campoCodigoPostal.classList.contains('is-invalid')) {
                        hayErrores = true;
                    }
                }
                
                if (hayErrores) {
                    e.preventDefault();
                    // Scroll al primer campo con error
                    const primerError = formEnvio.querySelector('.is-invalid');
                    if (primerError) {
                        scrollToFirstError(formActualizarDatos);
                        primerError.focus();
                    }
                    return false;
                }
                
                return true;
            });
        }
    }
    
    // Función para verificar si Bootstrap está cargado
    function verificarBootstrapCargado() {
        return typeof bootstrap !== 'undefined' && bootstrap && bootstrap.Tab;
    }
    
    // Función para inicializar validación con múltiples intentos
    function intentarInicializarValidacion() {
        const envioPane = document.getElementById('envio');
        const formEnvio = document.querySelector('#envio form[data-protect]');
        
        // Si los elementos existen, inicializar
        if (envioPane && formEnvio) {
            inicializarValidacionEnvio();
            return true;
        }
        return false;
    }
    
    // Función para inicializar todo el sistema de validación
    function inicializarSistemaValidacion() {
        // Primero inicializar la validación de campos
        if (intentarInicializarValidacion()) {
            // Si se inicializó correctamente, procesar mensajes del servidor
            setTimeout(function() {
                procesarMensajesServidor();
            }, 100);
        }
    }
    
    // Intentar inicializar inmediatamente
    inicializarSistemaValidacion();
    
    // Si no se pudo inicializar, intentar después de delays
    setTimeout(function() {
        if (!intentarInicializarValidacion()) {
            // Último intento después de más tiempo
            setTimeout(function() {
                inicializarSistemaValidacion();
            }, 500);
        } else {
            // Si se inicializó en este intento, procesar mensajes
            setTimeout(procesarMensajesServidor, 100);
        }
    }, 200);
    
    // Reprocesar mensajes después de más tiempo (por si el DOM cambió o se activó la pestaña)
    setTimeout(function() {
        procesarMensajesServidor();
    }, 500);
    
    // Inicializar cuando se active la pestaña de envío (evento de Bootstrap)
    // NOTA: Solo usar eventos de Bootstrap, NO agregar listeners de click que puedan interferir
    const envioTab = document.getElementById('envio-tab');
    if (envioTab) {
        // Usar SOLO el evento de Bootstrap tabs - no agregar listeners de click
        // Bootstrap maneja los clics automáticamente, solo escuchamos cuando la pestaña se muestra
        envioTab.addEventListener('shown.bs.tab', function() {
            // Esperar un momento para que el DOM se actualice
            setTimeout(function() {
                inicializarValidacionEnvio();
                // Reprocesar mensajes después de activar la pestaña
                setTimeout(procesarMensajesServidor, 150);
            }, 100);
        });
        
        // Si Bootstrap no está cargado, esperar a que se cargue
        if (!verificarBootstrapCargado()) {
            // Esperar a que Bootstrap se cargue y luego agregar el listener
            let bootstrapCheckInterval = setInterval(function() {
                if (verificarBootstrapCargado()) {
                    clearInterval(bootstrapCheckInterval);
                    // El listener ya está agregado arriba, Bootstrap lo manejará
                }
            }, 100);
            
            // Limpiar el intervalo después de 5 segundos si Bootstrap no se carga
            setTimeout(function() {
                clearInterval(bootstrapCheckInterval);
            }, 5000);
        }
    }
    
    // También escuchar cambios en las pestañas usando MutationObserver como fallback
    const tabContent = document.getElementById('perfilTabsContent');
    if (tabContent) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    const envioPane = document.getElementById('envio');
                    if (envioPane && envioPane.classList.contains('active') && envioPane.classList.contains('show')) {
                        setTimeout(function() {
                            inicializarValidacionEnvio();
                            // Reprocesar mensajes después de que la pestaña se active
                            setTimeout(procesarMensajesServidor, 150);
                        }, 100);
                    }
                }
            });
        });
        
        // Observar cambios en el tab-content
        observer.observe(tabContent, {
            attributes: true,
            attributeFilter: ['class'],
            subtree: true
        });
    }
    
    // ========================================================================
    // CONFIRMACIÓN DE CÓDIGO DE PAGO ANTES DE MARCAR PAGO
    // ========================================================================
    
    /**
     * Inicializa confirmaciones para formularios de "Marcar Pago"
     * Intercepta el envío y muestra confirmación simple con el código de pago
     */
    function inicializarConfirmacionesMarcarPago() {
        // Buscar formularios de múltiples formas para asegurar que se encuentren
        let formulariosMarcarPago = document.querySelectorAll('form.form-marcar-pago');
        
        // Si no se encuentran, intentar buscar por el botón dentro del formulario
        if (formulariosMarcarPago.length === 0) {
            const botonesMarcarPago = document.querySelectorAll('button[name="marcar_pago_pagado"]');
            console.log('[Perfil] No se encontraron formularios con clase, buscando por botón. Botones encontrados:', botonesMarcarPago.length);
            botonesMarcarPago.forEach(function(boton) {
                const form = boton.closest('form');
                if (form && !form.dataset.confirmacionInicializada) {
                    // Agregar la clase si no la tiene
                    if (!form.classList.contains('form-marcar-pago')) {
                        form.classList.add('form-marcar-pago');
                        console.log('[Perfil] Agregada clase form-marcar-pago al formulario');
                    }
                    formulariosMarcarPago = document.querySelectorAll('form.form-marcar-pago');
                }
            });
        }
        
        // Log para depuración
        console.log('[Perfil] Buscando formularios de marcar pago...');
        console.log('[Perfil] Formularios encontrados:', formulariosMarcarPago.length);
        
        if (formulariosMarcarPago.length === 0) {
            console.warn('[Perfil] No se encontraron formularios de marcar pago');
            // Intentar buscar de otra forma para debug
            const todosFormularios = document.querySelectorAll('form');
            console.log('[Perfil] Total de formularios en la página:', todosFormularios.length);
            todosFormularios.forEach(function(form, index) {
                console.log('[Perfil] Formulario', index, ':', form.className, 'action:', form.action, 'botones:', form.querySelectorAll('button').length);
            });
        }
        
        formulariosMarcarPago.forEach(function(formulario, index) {
            // Evitar agregar listeners múltiples
            if (formulario.dataset.confirmacionInicializada === 'true') {
                console.log('[Perfil] Formulario', index, 'ya inicializado, saltando');
                return;
            }
            formulario.dataset.confirmacionInicializada = 'true';
            console.log('[Perfil] Inicializando formulario', index, 'ID pago:', formulario.dataset.idPago);
            
            // Crear función nombrada para poder remover el listener después
            const submitHandler = function(e) {
                // Si el formulario ya tiene la marca de permitir envío, no hacer nada
                if (formulario.dataset.allowSubmit === 'true') {
                    return true;
                }
                
                // Prevenir el envío por defecto para mostrar confirmación
                e.preventDefault();
                e.stopPropagation();
                
                const inputCodigo = formulario.querySelector('[name="numero_transaccion"]');
                const codigoPago = inputCodigo ? inputCodigo.value.trim() : '';
                
                // Validar que el código no esté vacío
                if (!codigoPago) {
                    inputCodigo.classList.add('is-invalid');
                    inputCodigo.focus();
                    return false;
                }
                
                // Limpiar errores previos
                inputCodigo.classList.remove('is-invalid');
                
                // Mostrar confirmación simple con el código
                const mensaje = 'Por favor, verifica que el código de pago sea correcto:\n\nCódigo: ' + codigoPago + '\n\n¿Confirmas que el código es correcto?';
                
                if (!confirm(mensaje)) {
                    return false;
                }
                
                // Agregar parámetro para mantener la pestaña activa
                const formAction = formulario.getAttribute('action') || '';
                const separator = formAction.includes('?') ? '&' : '?';
                formulario.setAttribute('action', formAction + separator + 'tab=pedidos');
                
                // PROBLEMA: formulario.submit() NO incluye el botón submit en el POST
                // SOLUCIÓN: Agregar un input hidden con la acción antes de enviar
                let actionInput = formulario.querySelector('input[name="marcar_pago_pagado"]');
                if (!actionInput) {
                    actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'marcar_pago_pagado';
                    actionInput.value = '1';
                    formulario.appendChild(actionInput);
                }
                
                // Marcar que se permite el envío ANTES de remover el listener
                formulario.dataset.allowSubmit = 'true';
                
                // Remover el listener para evitar que se ejecute de nuevo
                formulario.removeEventListener('submit', submitHandler);
                
                // Enviar el formulario directamente usando submit()
                formulario.submit();
                
                return false;
            };
            
            formulario.addEventListener('submit', submitHandler);
            
            // También agregar listener al botón como fallback para debug
            const submitButton = formulario.querySelector('button[name="marcar_pago_pagado"]');
            if (submitButton) {
                submitButton.addEventListener('click', function(e) {
                    console.log('[Perfil] Click en botón Marcar Pago detectado');
                    // No prevenir el default aquí, dejar que el submit handler lo maneje
                });
            } else {
                console.warn('[Perfil] No se encontró el botón submit en el formulario');
            }
        });
    }
    
    // Inicializar confirmaciones al cargar la página
    try {
        console.log('[Perfil] Iniciando inicialización de formularios de marcar pago...');
        inicializarConfirmacionesMarcarPago();
    } catch (error) {
        console.error('[Perfil] Error al inicializar formularios de marcar pago:', error);
    }
    
    // ========================================================================
    // RE-INICIALIZAR CONFIRMACIONES CUANDO SE MUESTRA LA PESTAÑA DE PEDIDOS
    // ========================================================================
    // Los formularios de "Marcar Pago" están dentro de la pestaña #pedidos
    // que puede no estar visible al cargar la página, por lo que necesitamos
    // re-inicializar cuando se muestre la pestaña
    const pedidosTab = document.getElementById('pedidos-tab');
    if (pedidosTab) {
        // Usar el evento de Bootstrap tabs para re-inicializar cuando se muestre la pestaña
        pedidosTab.addEventListener('shown.bs.tab', function() {
            // Esperar un momento para que el DOM se actualice completamente
            setTimeout(function() {
                inicializarConfirmacionesMarcarPago();
            }, 100);
        });
        
        // Si Bootstrap no está cargado, esperar a que se cargue
        if (!verificarBootstrapCargado()) {
            let bootstrapCheckInterval = setInterval(function() {
                if (verificarBootstrapCargado()) {
                    clearInterval(bootstrapCheckInterval);
                    // El listener ya está agregado arriba, Bootstrap lo manejará
                }
            }, 100);
            
            // Limpiar el intervalo después de 5 segundos si Bootstrap no se carga
            setTimeout(function() {
                clearInterval(bootstrapCheckInterval);
            }, 5000);
        }
    }
    
    // También usar MutationObserver como fallback para detectar cuando la pestaña se muestra
    const pedidosPane = document.getElementById('pedidos');
    if (pedidosPane) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    if (pedidosPane.classList.contains('active') && pedidosPane.classList.contains('show')) {
                        setTimeout(function() {
                            inicializarConfirmacionesMarcarPago();
                        }, 100);
                    }
                }
            });
        });
        
        // Observar cambios en el tab-pane de pedidos
        observer.observe(pedidosPane, {
            attributes: true,
            attributeFilter: ['class']
        });
    }
    
    // ========================================================================
    // ACTIVAR PESTAÑA SEGÚN PARÁMETRO URL (solo en perfil.php)
    // ========================================================================
    if (window.location.pathname.includes('perfil.php')) {
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        
        if (tabParam) {
            const tabsValidos = ['datos', 'envio', 'pedidos', 'contrasena'];
            if (tabsValidos.includes(tabParam)) {
                // Activar pestaña usando Bootstrap
                const tabButton = document.getElementById(tabParam + '-tab');
                if (tabButton && typeof bootstrap !== 'undefined') {
                    // Si es la pestaña de pedidos, inicializar formularios después de activarla
                    if (tabParam === 'pedidos') {
                        // Agregar listener ANTES de activar la pestaña
                        tabButton.addEventListener('shown.bs.tab', function() {
                            console.log('[Perfil] Pestaña pedidos activada, inicializando formularios...');
                            setTimeout(function() {
                                inicializarConfirmacionesMarcarPago();
                            }, 200);
                        }, { once: true });
                    }
                    
                    // Activar la pestaña
                    const tab = new bootstrap.Tab(tabButton);
                    tab.show();
                } else if (tabButton) {
                    // Si Bootstrap no está disponible, esperar a que se cargue
                    console.log('[Perfil] Esperando a que Bootstrap se cargue...');
                    let bootstrapCheckInterval = setInterval(function() {
                        if (typeof bootstrap !== 'undefined' && bootstrap && bootstrap.Tab) {
                            clearInterval(bootstrapCheckInterval);
                            if (tabParam === 'pedidos') {
                                tabButton.addEventListener('shown.bs.tab', function() {
                                    console.log('[Perfil] Pestaña pedidos activada, inicializando formularios...');
                                    setTimeout(function() {
                                        inicializarConfirmacionesMarcarPago();
                                    }, 200);
                                }, { once: true });
                            }
                            const tab = new bootstrap.Tab(tabButton);
                            tab.show();
                        }
                    }, 100);
                    
                    setTimeout(function() {
                        clearInterval(bootstrapCheckInterval);
                    }, 5000);
                }
            }
        } else {
            // Si no hay parámetro tab pero estamos en perfil.php, inicializar de todas formas
            // (por si la pestaña de pedidos ya está activa por defecto)
            setTimeout(function() {
                inicializarConfirmacionesMarcarPago();
            }, 500);
        }
    } else {
        // Si no estamos en perfil.php pero el código se ejecuta, inicializar de todas formas
        setTimeout(function() {
            inicializarConfirmacionesMarcarPago();
        }, 500);
    }
    
}

