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
 * - toggleUsuariosInactivos(): Toggle para mostrar/ocultar usuarios inactivos
 * 
 * NOTA: Las siguientes funciones están disponibles en common_js_functions.php (incluido globalmente):
 * - confirmLogout(), togglePassword(), validateEmail(), validarContrasenaUsuario(), etc.
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Función para toggle de usuarios inactivos
 * Preserva la pestaña activa actual
 * @param {boolean} mostrar - true para mostrar inactivos, false para ocultar
 */
function toggleUsuariosInactivos(mostrar) {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Preservar pestaña activa si existe
    const tabActiva = urlParams.get('tab');
    if (!tabActiva) {
        // Si no hay pestaña en URL, intentar detectar la pestaña activa visualmente
        const tabButtonActivo = document.querySelector('.nav-link.active[data-bs-toggle="tab"]');
        if (tabButtonActivo) {
            const target = tabButtonActivo.getAttribute('data-bs-target');
            if (target) {
                const tabId = target.replace('#', '');
                const tabsValidos = ['usuarios', 'crear-usuario', 'historial-usuarios'];
                if (tabsValidos.includes(tabId)) {
                    urlParams.set('tab', tabId);
                }
            }
        }
    }
    
    if (mostrar) {
        urlParams.set('mostrar_inactivos', '1');
    } else {
        urlParams.delete('mostrar_inactivos');
    }
    
    // Construir URL correctamente
    const queryString = urlParams.toString();
    const newUrl = queryString ? 'admin.php?' + queryString : 'admin.php';
    window.location.href = newUrl;
}

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
 * NOTA: validarNombreApellido() está disponible en common_js_functions.php
 * Se incluye globalmente en footer.php, usar directamente la función global
 */

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
                    // Validar longitud: mínimo 6 caracteres, máximo 20
                    if (value.length >= 6 && value.length <= 20) {
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
    // ========================================================================
    // Event listeners para reemplazar onclick inline
    // ========================================================================
    
    // Botones de logout con confirmación
    const btnLogout = document.querySelectorAll('.btn-logout');
    btnLogout.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            if (typeof confirmLogout === 'function') {
                if (!confirmLogout()) {
                    e.preventDefault();
                }
            }
        });
    });
    
    // Select de filtro de rol - auto-submit
    const filtroRol = document.getElementById('filtro_rol');
    if (filtroRol) {
        const form = filtroRol.closest('form');
        if (form) {
            filtroRol.addEventListener('change', function() {
                form.submit();
            });
        }
    }
    
    // Checkbox para mostrar usuarios inactivos
    const checkboxInactivos = document.querySelector('[data-toggle-usuarios-inactivos]');
    if (checkboxInactivos) {
        checkboxInactivos.addEventListener('change', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof toggleUsuariosInactivos === 'function') {
                toggleUsuariosInactivos(this.checked);
            }
        });
    }
    
    // Botones toggle password
    const btnTogglePassword = document.querySelectorAll('[data-toggle-password]');
    btnTogglePassword.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const inputId = this.getAttribute('data-toggle-password');
            if (inputId && typeof togglePasswordStaff === 'function') {
                togglePasswordStaff(inputId);
            }
        });
    });
    
    // ========================================================================
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
    
    // Validación contraseñas con carteles informativos
    const passwordInput = document.getElementById('password_temporal');
    const confirmarInput = document.getElementById('confirmar_password_temporal');
    
    // Crear carteles informativos
    function crearCartelesInformativos() {
        const passwordWrapper = passwordInput?.closest('.col-md-6');
        const confirmarWrapper = confirmarInput?.closest('.col-md-6');
        
        if (!passwordWrapper || !confirmarWrapper) return;
        
        // Remover carteles existentes si los hay
        const cartelExistente = passwordWrapper.querySelector('.password-info-alert');
        if (cartelExistente) {
            cartelExistente.remove();
        }
        
        // Crear cartel informativo
        const cartelInfo = document.createElement('div');
        cartelInfo.className = 'alert alert-info password-info-alert mt-2';
        cartelInfo.innerHTML = '<i class="fas fa-info-circle me-2"></i><strong>Opciones:</strong> Puedes escribir la contraseña y repetirla, o dejar ambos campos vacíos para generar una contraseña aleatoria (se mostrará al crear el usuario).';
        cartelInfo.style.fontSize = '0.875rem';
        
        // Insertar después del campo de confirmar contraseña
        confirmarWrapper.appendChild(cartelInfo);
    }
    
    // Función para actualizar carteles según estado de los campos
    function actualizarCartelesInformativos() {
        const cartelInfo = document.querySelector('.password-info-alert');
        if (!cartelInfo) return;
        
        const passwordValue = passwordInput?.value || '';
        const confirmarValue = confirmarInput?.value || '';
        
        if (passwordValue === '' && confirmarValue === '') {
            // Ambos vacíos: mostrar mensaje de generación aleatoria
            cartelInfo.className = 'alert alert-info password-info-alert mt-2';
            cartelInfo.innerHTML = '<i class="fas fa-key me-2"></i><strong>Contraseña aleatoria:</strong> Los campos están vacíos. Se generará una contraseña aleatoria segura que se mostrará al crear el usuario.';
        } else if (passwordValue !== '' || confirmarValue !== '') {
            // Al menos uno tiene valor: mostrar mensaje de opciones
            cartelInfo.className = 'alert alert-info password-info-alert mt-2';
            cartelInfo.innerHTML = '<i class="fas fa-info-circle me-2"></i><strong>Opciones:</strong> Puedes escribir la contraseña y repetirla, o dejar ambos campos vacíos para generar una contraseña aleatoria (se mostrará al crear el usuario).';
        }
    }
    
    // Crear carteles al cargar
    if (passwordInput && confirmarInput) {
        crearCartelesInformativos();
    }
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const value = this.value;
            
            // Remover required si ambos están vacíos
            if (value === '' && (!confirmarInput || confirmarInput.value === '')) {
                this.removeAttribute('required');
                if (confirmarInput) confirmarInput.removeAttribute('required');
                this.classList.remove('is-valid', 'is-invalid');
            } else if (value !== '') {
                // Si tiene valor, hacer required
                this.setAttribute('required', 'required');
                if (confirmarInput) confirmarInput.setAttribute('required', 'required');
                
                // Validar longitud
                if (value.length >= 6 && value.length <= 20) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            }
            
            // Actualizar carteles
            actualizarCartelesInformativos();
            
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
            const value = this.value;
            
            // Remover required si ambos están vacíos
            if (value === '' && (!passwordInput || passwordInput.value === '')) {
                this.removeAttribute('required');
                if (passwordInput) passwordInput.removeAttribute('required');
                this.classList.remove('is-valid', 'is-invalid');
            } else if (value !== '') {
                // Si tiene valor, hacer required
                this.setAttribute('required', 'required');
                if (passwordInput) passwordInput.setAttribute('required', 'required');
            }
            
            // Actualizar carteles
            actualizarCartelesInformativos();
            
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
            
            // Validar contraseñas (permite campos vacíos para generar aleatoria)
            const password = passwordInput?.value || '';
            const confirmar = confirmarInput?.value || '';
            
            // Si ambos están vacíos, está bien (se generará aleatoria)
            if (password === '' && confirmar === '') {
                // Ambos vacíos: válido, se generará contraseña aleatoria
            } else if (password === '' && confirmar !== '') {
                // Solo confirmar tiene valor: error
                passwordInput?.classList.add('is-invalid');
                isValid = false;
            } else if (password !== '' && confirmar === '') {
                // Solo password tiene valor: error
                confirmarInput?.classList.add('is-invalid');
                isValid = false;
            } else {
                // Ambos tienen valor: validar longitud y coincidencia
                if (password.length < 6 || password.length > 20) {
                    passwordInput?.classList.add('is-invalid');
                    isValid = false;
                }
                
                if (password !== confirmar) {
                    confirmarInput?.classList.add('is-invalid');
                    isValid = false;
                }
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
    
    // ========================================================================
    // CONFIRMACIONES PARA CAMBIOS DE ROL Y DESACTIVACIÓN (Mejora Crítica)
    // ========================================================================
    
    /**
     * Intercepta el envío del formulario de cambio de rol
     * Muestra confirmación antes de cambiar el rol de un usuario
     */
    function inicializarConfirmacionesCambioRol() {
        const formulariosCambioRol = document.querySelectorAll('form button[name="cambiar_rol"]');
        
        formulariosCambioRol.forEach(function(boton) {
            const formulario = boton.closest('form');
            if (!formulario) return;
            
            // Evitar agregar listeners múltiples
            if (formulario.dataset.confirmacionRolInicializada === 'true') {
                return;
            }
            formulario.dataset.confirmacionRolInicializada = 'true';
            
            formulario.addEventListener('submit', function(e) {
                // Solo interceptar si se está cambiando el rol (no otros campos)
                const esCambioRol = e.submitter && e.submitter.name === 'cambiar_rol';
                if (!esCambioRol) {
                    return;
                }
                
                e.preventDefault();
                
                // Obtener datos del formulario
                const idUsuario = formulario.querySelector('[name="user_id"]')?.value;
                const rolNuevo = formulario.querySelector('[name="nuevo_rol"]')?.value;
                
                // Obtener información del usuario del modal
                const modal = formulario.closest('.modal');
                let nombreUsuario = 'Usuario';
                let rolActual = '';
                let esUsuarioActual = false;
                let esUltimoAdmin = false;
                
                if (modal) {
                    const titleElement = modal.querySelector('.modal-title');
                    if (titleElement) {
                        nombreUsuario = titleElement.textContent.trim().replace('Modificar Usuario', '').trim() || 'Usuario';
                    }
                    
                    // Obtener rol actual del badge
                    const badgeRol = modal.querySelector('.badge');
                    if (badgeRol) {
                        rolActual = badgeRol.textContent.trim().toLowerCase();
                    }
                    
                    // Verificar si es el usuario actual (el botón estará deshabilitado pero por si acaso)
                    const botonModificar = modal.previousElementSibling;
                    if (botonModificar && botonModificar.hasAttribute('disabled')) {
                        esUsuarioActual = true;
                    }
                    
                    // Verificar si es el último admin (buscar en el HTML si hay advertencia)
                    const advertenciaAdmin = modal.querySelector('.alert-danger');
                    if (advertenciaAdmin && advertenciaAdmin.textContent.includes('último administrador')) {
                        esUltimoAdmin = true;
                    }
                }
                
                // Enviar formulario con bloqueo de botón
                procesarOperacionCritica(boton, function() {
                    formulario.submit();
                }, {
                    textoProcesando: 'Cambiando rol...',
                    tiempoBloqueo: 2000
                });
            });
        });
    }
    
    /**
     * Intercepta el envío del formulario de desactivación de usuario
     * Muestra confirmación antes de desactivar un usuario
     */
    function inicializarConfirmacionesDesactivacion() {
        const botonesDesactivar = document.querySelectorAll('button[name="desactivar_usuario"]');
        
        botonesDesactivar.forEach(function(boton) {
            const formulario = boton.closest('form');
            if (!formulario) return;
            
            // Remover el onsubmit inline (reemplazar con nuestra confirmación)
            formulario.removeAttribute('onsubmit');
            
            // Evitar agregar listeners múltiples
            if (formulario.dataset.confirmacionDesactivacionInicializada === 'true') {
                return;
            }
            formulario.dataset.confirmacionDesactivacionInicializada = 'true';
            
            formulario.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Mostrar confirmación antes de desactivar
                if (!confirm('¿Estás seguro de desactivar esta cuenta? El usuario no podrá iniciar sesión.')) {
                    return; // Usuario canceló la acción
                }
                
                // Obtener datos del formulario
                const idUsuario = formulario.querySelector('[name="del_user_id"]')?.value;
                
                // Obtener información del usuario del modal padre
                const modal = formulario.closest('.modal');
                let nombreUsuario = 'Usuario';
                let rol = 'cliente';
                let esUsuarioActual = false;
                let esUltimoAdmin = false;
                
                if (modal) {
                    // Obtener nombre del título del modal
                    const titleElement = modal.querySelector('.modal-title');
                    if (titleElement) {
                        nombreUsuario = titleElement.textContent.trim().replace('Modificar Usuario', '').trim() || 'Usuario';
                    }
                    
                    // Obtener rol del badge
                    const badgeRol = modal.querySelector('.badge');
                    if (badgeRol) {
                        rol = badgeRol.textContent.trim().toLowerCase();
                    }
                    
                    // Verificar si es el último admin
                    const advertenciaAdmin = modal.querySelector('.alert-danger');
                    if (advertenciaAdmin && advertenciaAdmin.textContent.includes('último administrador')) {
                        esUltimoAdmin = true;
                    }
                }
                
                // Agregar campo hidden con la acción para asegurar que se envíe en POST
                // (necesario porque submit() programático no incluye el botón en POST)
                let campoAccion = formulario.querySelector('input[name="desactivar_usuario"]');
                if (!campoAccion) {
                    campoAccion = document.createElement('input');
                    campoAccion.type = 'hidden';
                    campoAccion.name = 'desactivar_usuario';
                    campoAccion.value = '1';
                    formulario.appendChild(campoAccion);
                }
                
                // Enviar formulario con bloqueo de botón
                procesarOperacionCritica(boton, function() {
                    formulario.submit();
                }, {
                    textoProcesando: 'Desactivando...',
                    tiempoBloqueo: 2000
                });
            });
        });
    }
    
    // Inicializar confirmaciones al cargar la página
    inicializarConfirmacionesCambioRol();
    inicializarConfirmacionesDesactivacion();
    
    // Reinicializar cuando se abren modales
    modales.forEach(function(modal) {
        modal.addEventListener('shown.bs.modal', function() {
            inicializarConfirmacionesCambioRol();
            inicializarConfirmacionesDesactivacion();
        });
    });
});

