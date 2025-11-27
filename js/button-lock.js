/**
 * ========================================================================
 * BLOQUEO DE BOTONES DURANTE PROCESAMIENTO - Tienda Seda y Lino
 * ========================================================================
 * Utilidad para bloquear botones durante operaciones críticas y prevenir
 * doble click, spam, y operaciones duplicadas.
 * 
 * Funciones principales:
 * - procesarOperacionCritica(): Bloquea botón durante operación
 * - bloquearBoton(): Bloquea un botón específico
 * - desbloquearBoton(): Desbloquea un botón específico
 * - bloquearFormulario(): Bloquea todos los botones de un formulario
 * - desbloquearFormulario(): Desbloquea todos los botones de un formulario
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Procesar operación crítica con bloqueo de botón
 * Bloquea el botón, ejecuta la operación, y desbloquea después de un tiempo
 * 
 * @param {HTMLElement} boton - Elemento del botón a bloquear
 * @param {Function} operacion - Función (puede retornar Promise) que ejecuta la operación
 * @param {Object} opciones - Opciones de configuración
 * @param {number} opciones.tiempoBloqueo - Tiempo en ms para mantener bloqueado (default: 2000)
 * @param {string} opciones.textoProcesando - Texto a mostrar durante procesamiento (default: 'Procesando...')
 * @param {boolean} opciones.mostrarSpinner - Mostrar spinner (default: true)
 * @returns {Promise} Promise que resuelve cuando la operación termina
 */
function procesarOperacionCritica(boton, operacion, opciones = {}) {
    // Configuración por defecto
    const config = {
        tiempoBloqueo: opciones.tiempoBloqueo || 2000,
        textoProcesando: opciones.textoProcesando || 'Procesando...',
        mostrarSpinner: opciones.mostrarSpinner !== false
    };
    
    // Guardar estado original del botón
    const textoOriginal = boton.innerHTML;
    const estabaDeshabilitado = boton.disabled;
    
    // Bloquear botón inmediatamente
    boton.disabled = true;
    
    // Cambiar contenido del botón
    if (config.mostrarSpinner) {
        boton.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${config.textoProcesando}`;
    } else {
        boton.innerHTML = config.textoProcesando;
    }
    
    // Agregar clase visual de procesamiento
    boton.classList.add('btn-procesando');
    
    // Función para restaurar el botón
    const restaurarBoton = () => {
        setTimeout(() => {
            boton.innerHTML = textoOriginal;
            boton.disabled = estabaDeshabilitado; // Respetar el estado original
            boton.classList.remove('btn-procesando');
        }, config.tiempoBloqueo);
    };
    
    // Ejecutar la operación
    try {
        // Si la operación retorna una Promise
        const resultado = operacion();
        
        if (resultado && typeof resultado.then === 'function') {
            return resultado
                .then(res => {
                    restaurarBoton();
                    return res;
                })
                .catch(err => {
                    restaurarBoton();
                    throw err;
                });
        } else {
            // Operación síncrona
            restaurarBoton();
            return Promise.resolve(resultado);
        }
    } catch (error) {
        // Error en operación síncrona
        restaurarBoton();
        throw error;
    }
}

/**
 * Bloquear un botón específico
 * Cambia el estado visual y funcional del botón
 * 
 * @param {HTMLElement|string} boton - Elemento del botón o selector CSS
 * @param {string} textoProcesando - Texto a mostrar (opcional)
 * @param {boolean} mostrarSpinner - Mostrar spinner (default: true)
 */
function bloquearBoton(boton, textoProcesando = 'Procesando...', mostrarSpinner = true) {
    // Si es un selector, buscar el elemento
    if (typeof boton === 'string') {
        boton = document.querySelector(boton);
    }
    
    if (!boton) {
        console.warn('bloquearBoton: Botón no encontrado');
        return;
    }
    
    // Guardar estado original si no existe
    if (!boton.dataset.originalText) {
        boton.dataset.originalText = boton.innerHTML;
        boton.dataset.originalDisabled = boton.disabled;
    }
    
    // Bloquear botón
    boton.disabled = true;
    
    // Cambiar contenido
    if (mostrarSpinner) {
        boton.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${textoProcesando}`;
    } else {
        boton.innerHTML = textoProcesando;
    }
    
    boton.classList.add('btn-procesando');
}

/**
 * Desbloquear un botón específico
 * Restaura el estado original del botón
 * 
 * @param {HTMLElement|string} boton - Elemento del botón o selector CSS
 */
function desbloquearBoton(boton) {
    // Si es un selector, buscar el elemento
    if (typeof boton === 'string') {
        boton = document.querySelector(boton);
    }
    
    if (!boton) {
        console.warn('desbloquearBoton: Botón no encontrado');
        return;
    }
    
    // Restaurar estado original
    if (boton.dataset.originalText) {
        boton.innerHTML = boton.dataset.originalText;
        boton.disabled = boton.dataset.originalDisabled === 'true';
        
        // Limpiar datos temporales
        delete boton.dataset.originalText;
        delete boton.dataset.originalDisabled;
    } else {
        // Si no hay estado guardado, solo habilitar
        boton.disabled = false;
    }
    
    boton.classList.remove('btn-procesando');
}

/**
 * Bloquear todos los botones de un formulario
 * Útil durante el envío de formularios
 * 
 * NOTA: Esta función actualmente no se usa en el código, pero se mantiene
 * para uso futuro. Es útil para bloquear formularios completos durante el envío.
 * 
 * @param {HTMLElement|string} formulario - Elemento del formulario o selector CSS
 * @param {string} textoProcesando - Texto a mostrar en botones (opcional)
 */
function bloquearFormulario(formulario, textoProcesando = 'Procesando...') {
    // Si es un selector, buscar el elemento
    if (typeof formulario === 'string') {
        formulario = document.querySelector(formulario);
    }
    
    if (!formulario) {
        console.warn('bloquearFormulario: Formulario no encontrado');
        return;
    }
    
    // Buscar todos los botones del formulario
    const botones = formulario.querySelectorAll('button[type="submit"], button[type="button"], input[type="submit"]');
    
    botones.forEach(boton => {
        bloquearBoton(boton, textoProcesando, true);
    });
    
    // Deshabilitar todos los inputs también
    const inputs = formulario.querySelectorAll('input:not([type="hidden"]), select, textarea');
    inputs.forEach(input => {
        if (!input.dataset.originalDisabled) {
            input.dataset.originalDisabled = input.disabled;
        }
        input.disabled = true;
    });
}

/**
 * Desbloquear todos los botones de un formulario
 * Restaura el estado original del formulario
 * 
 * NOTA: Esta función actualmente no se usa en el código, pero se mantiene
 * para uso futuro. Úsala junto con bloquearFormulario().
 * 
 * @param {HTMLElement|string} formulario - Elemento del formulario o selector CSS
 */
function desbloquearFormulario(formulario) {
    // Si es un selector, buscar el elemento
    if (typeof formulario === 'string') {
        formulario = document.querySelector(formulario);
    }
    
    if (!formulario) {
        console.warn('desbloquearFormulario: Formulario no encontrado');
        return;
    }
    
    // Desbloquear todos los botones
    const botones = formulario.querySelectorAll('button, input[type="submit"]');
    botones.forEach(boton => {
        desbloquearBoton(boton);
    });
    
    // Re-habilitar inputs
    const inputs = formulario.querySelectorAll('input:not([type="hidden"]), select, textarea');
    inputs.forEach(input => {
        if (input.dataset.originalDisabled !== undefined) {
            input.disabled = input.dataset.originalDisabled === 'true';
            delete input.dataset.originalDisabled;
        }
    });
}

/**
 * Proteger botón contra doble click
 * Agrega un listener que previene múltiples clicks
 * 
 * NOTA: Esta función actualmente no se usa en el código, pero se mantiene
 * para uso futuro. Alternativa a procesarOperacionCritica() para casos simples.
 * 
 * @param {HTMLElement|string} boton - Elemento del botón o selector CSS
 * @param {Function} callback - Función a ejecutar en el click
 * @param {number} tiempoProteccion - Tiempo en ms para proteger (default: 2000)
 */
function protegerContraDobleClick(boton, callback, tiempoProteccion = 2000) {
    // Si es un selector, buscar el elemento
    if (typeof boton === 'string') {
        boton = document.querySelector(boton);
    }
    
    if (!boton) {
        console.warn('protegerContraDobleClick: Botón no encontrado');
        return;
    }
    
    let procesando = false;
    
    boton.addEventListener('click', function(e) {
        // Si ya está procesando, prevenir
        if (procesando) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
        
        // Marcar como procesando
        procesando = true;
        
        // Ejecutar callback
        if (callback) {
            callback.call(this, e);
        }
        
        // Desbloquear después del tiempo de protección
        setTimeout(() => {
            procesando = false;
        }, tiempoProteccion);
    });
}

/**
 * Crear wrapper de función con bloqueo automático
 * Retorna una nueva función que bloquea el botón automáticamente
 * 
 * NOTA: Esta función actualmente no se usa en el código, pero se mantiene
 * para uso futuro. Útil para crear funciones con protección automática.
 * 
 * @param {HTMLElement} boton - Elemento del botón
 * @param {Function} funcion - Función original a ejecutar
 * @param {Object} opciones - Opciones de configuración
 * @returns {Function} Nueva función con bloqueo automático
 */
function crearFuncionConBloqueo(boton, funcion, opciones = {}) {
    return function(...args) {
        return procesarOperacionCritica(boton, () => {
            return funcion.apply(this, args);
        }, opciones);
    };
}

/**
 * Inicializar bloqueo automático en elementos con data-attribute
 * Busca elementos con data-auto-lock="true" y les agrega protección
 */
function inicializarBloqueosAutomaticos() {
    const botonesProtegidos = document.querySelectorAll('[data-auto-lock="true"]');
    
    botonesProtegidos.forEach(boton => {
        const tiempoProteccion = parseInt(boton.dataset.lockTime) || 2000;
        const textoProcesando = boton.dataset.lockText || 'Procesando...';
        
        boton.addEventListener('click', function(e) {
            // No bloquear si es un botón de tipo submit en un formulario
            // (el formulario se encargará del bloqueo)
            if (this.type === 'submit' && this.form) {
                return;
            }
            
            bloquearBoton(this, textoProcesando, true);
            
            setTimeout(() => {
                desbloquearBoton(this);
            }, tiempoProteccion);
        });
    });
}

// Inicializar bloqueos automáticos cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarBloqueosAutomaticos);
} else {
    inicializarBloqueosAutomaticos();
}

// CSS inline para estado de procesamiento
if (!document.getElementById('button-lock-styles')) {
    const style = document.createElement('style');
    style.id = 'button-lock-styles';
    style.textContent = `
        .btn-procesando {
            opacity: 0.7;
            cursor: wait !important;
            pointer-events: none;
        }
        
        .btn-procesando:hover {
            opacity: 0.7 !important;
        }
    `;
    document.head.appendChild(style);
}


