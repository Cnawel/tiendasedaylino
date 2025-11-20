/**
 * ========================================================================
 * FORMULARIOS DE MARKETING - Tienda Seda y Lino
 * ========================================================================
 * Funciones JavaScript para manejo de formularios en el panel de marketing
 * 
 * FUNCIONES:
 * - cambiarLimiteProductos(): Cambia el límite de productos a mostrar
 * - toggleProductosInactivos(): Toggle para mostrar/ocultar productos inactivos
 * - copiarNombre(): Copia nombre de imagen al portapapeles
 * - validarNombreProducto(): Valida nombre de producto (caracteres permitidos)
 * - validarPrecio(): Valida precio (formato numérico)
 * - validarDescripcionProducto(): Valida descripción de producto (caracteres permitidos)
 * 
 * @package TiendaSedaYLino
 * @version 2.0
 * ========================================================================
 */

/**
 * Función para cambiar límite de productos
 * Preserva los parámetros de URL existentes (como tab y mostrar_inactivos)
 * @param {string|number} limite - Límite de productos a mostrar
 */
function cambiarLimiteProductos(limite) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('limite', limite);
    urlParams.set('tab', 'productos');
    window.location.href = 'marketing.php?' + urlParams.toString();
}

/**
 * Función para toggle de productos inactivos
 * @param {boolean} mostrar - true para mostrar inactivos, false para ocultar
 */
function toggleProductosInactivos(mostrar) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('tab', 'productos');
    
    if (mostrar) {
        urlParams.set('mostrar_inactivos', '1');
    } else {
        urlParams.delete('mostrar_inactivos');
    }
    
    window.location.href = 'marketing.php?' + urlParams.toString();
}

/**
 * Función para copiar nombre de imagen al portapapeles
 * @param {string} nombre - Nombre de la imagen a copiar
 * @param {HTMLElement} boton - Botón que activó la copia (para feedback visual)
 */
function copiarNombre(nombre, boton) {
    navigator.clipboard.writeText(nombre).then(function() {
        // Cambiar ícono temporalmente para indicar éxito
        const icono = boton.querySelector('i');
        const claseOriginal = icono.className;
        icono.className = 'fas fa-check';
        boton.classList.remove('btn-outline-secondary');
        boton.classList.add('btn-success');
        
        setTimeout(function() {
            icono.className = claseOriginal;
            boton.classList.remove('btn-success');
            boton.classList.add('btn-outline-secondary');
        }, 2000);
    }).catch(function(err) {
        alert('Error al copiar: ' + err);
    });
}

/**
 * Valida nombre de producto
 * Permite: letras, números, espacios, acentos, guiones
 * 
 * NOTA: Existe versión PHP equivalente en marketing_functions.php
 * Ambas versiones deben mantener la misma lógica de validación.
 * 
 * @param {string} valor - Valor a validar
 * @return {object} {valido: boolean, error: string}
 */
function validarNombreProducto(valor) {
    valor = valor.trim();
    
    if (!valor) {
        return {valido: false, error: 'El nombre del producto es obligatorio.'};
    }
    
    // Validar longitud mínima según diccionario: 3 caracteres
    if (valor.length < 3) {
        return {valido: false, error: 'El nombre del producto debe tener al menos 3 caracteres.'};
    }
    
    // Validar caracteres permitidos: letras, números, espacios, acentos, guiones
    // Regex: letras (incluyendo acentos), números, espacios, guiones
    if (!/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ0-9\s\-]+$/.test(valor)) {
        return {valido: false, error: 'El nombre del producto contiene caracteres no permitidos. Solo se permiten letras, números, espacios y guiones.'};
    }
    
    // Validar longitud máxima (100 caracteres)
    if (valor.length > 100) {
        return {valido: false, error: 'El nombre del producto no puede exceder 100 caracteres.'};
    }
    
    return {valido: true, error: ''};
}

/**
 * Valida precio numérico
 * Permite: solo números y punto decimal
 * 
 * NOTA: Existe versión PHP equivalente en marketing_functions.php
 * Ambas versiones deben mantener la misma lógica de validación.
 * 
 * @param {string} valor - Valor a validar
 * @return {object} {valido: boolean, error: string}
 */
function validarPrecio(valor) {
    valor = valor.trim();
    
    if (!valor) {
        return {valido: false, error: 'El precio es obligatorio.'};
    }
    
    // Validar que sea numérico puro (solo números y punto decimal)
    if (!/^[0-9]+\.?[0-9]*$/.test(valor)) {
        return {valido: false, error: 'El precio debe ser un número válido sin símbolos de moneda (ej: 15000.50)'};
    }
    
    const precioFloat = parseFloat(valor);
    
    if (isNaN(precioFloat) || precioFloat <= 0) {
        return {valido: false, error: 'El precio debe ser mayor a cero.'};
    }
    
    return {valido: true, error: ''};
}

/**
 * Valida descripción de producto
 * Permite: letras, números, espacios, acentos, guiones, puntos, comas, dos puntos, punto y coma
 * Bloquea: símbolos peligrosos (< > { } [ ] | \ / &)
 * 
 * NOTA: Existe versión PHP equivalente en marketing_functions.php
 * Ambas versiones deben mantener la misma lógica de validación.
 * 
 * @param {string} valor - Valor a validar
 * @return {object} {valido: boolean, error: string}
 */
function validarDescripcionProducto(valor) {
    valor = valor.trim();
    
    // La descripción es opcional, si está vacía es válida
    if (!valor) {
        return {valido: true, error: ''};
    }
    
    // Validar caracteres permitidos: letras, números, espacios, acentos, guiones, puntos, comas, dos puntos, punto y coma
    // Bloquear símbolos peligrosos: < > { } [ ] | \ / &
    if (/[<>{}\[\]|\\\/&]/.test(valor)) {
        return {valido: false, error: 'La descripción contiene caracteres no permitidos. No se permiten los símbolos: < > { } [ ] | \\ / &'};
    }
    
    // Validar longitud máxima (255 caracteres)
    if (valor.length > 255) {
        return {valido: false, error: 'La descripción no puede exceder 255 caracteres.'};
    }
    
    return {valido: true, error: ''};
}

/**
 * NOTA: Las funciones mostrarErrorCampo() y limpiarErrorCampo() están disponibles
 * en common_js_functions.php (incluido globalmente en footer.php).
 * Estas funciones consolidadas soportan tanto elementos de error preexistentes
 * como creación dinámica de elementos de feedback.
 * 
 * Uso: mostrarErrorCampo(campo, errorElement, mensaje)
 *      limpiarErrorCampo(campo, errorElement)
 */

// Manejar select de nombre de producto y categoría
document.addEventListener('DOMContentLoaded', function() {
    const selectNombreProducto = document.getElementById('select_nombre_producto');
    const inputNombreProductoNuevo = document.getElementById('input_nombre_producto_nuevo');
    
    if (selectNombreProducto && inputNombreProductoNuevo) {
        selectNombreProducto.addEventListener('change', function() {
            if (this.value === '__NUEVO__') {
                // Mostrar input para nuevo producto
                inputNombreProductoNuevo.classList.remove('d-none');
                inputNombreProductoNuevo.setAttribute('required', 'required');
                inputNombreProductoNuevo.setAttribute('name', 'nombre_producto_nuevo');
                inputNombreProductoNuevo.focus();
                // Mantener el name del select como 'nombre_producto' con valor '__NUEVO__'
                // para que el PHP pueda detectar que debe leer nombre_producto_nuevo
                this.setAttribute('name', 'nombre_producto');
            } else {
                // Ocultar input y usar el valor del select
                inputNombreProductoNuevo.classList.add('d-none');
                inputNombreProductoNuevo.removeAttribute('required');
                inputNombreProductoNuevo.removeAttribute('name');
                inputNombreProductoNuevo.value = '';
                // Restaurar el name del select
                this.setAttribute('name', 'nombre_producto');
            }
        });
    }
    
    // Manejar select de categoría
    const selectCategoria = document.getElementById('select_categoria');
    const inputCategoriaNueva = document.getElementById('input_categoria_nueva');
    
    if (selectCategoria && inputCategoriaNueva) {
        selectCategoria.addEventListener('change', function() {
            if (this.value === '__NUEVO__') {
                // Mostrar input para nueva categoría
                inputCategoriaNueva.classList.remove('d-none');
                inputCategoriaNueva.setAttribute('required', 'required');
                inputCategoriaNueva.setAttribute('name', 'id_categoria');
                inputCategoriaNueva.focus();
                // Cambiar el name del select para que no se procese
                this.setAttribute('name', 'select_categoria_temp');
            } else {
                // Ocultar input y usar el valor del select
                inputCategoriaNueva.classList.add('d-none');
                inputCategoriaNueva.removeAttribute('required');
                inputCategoriaNueva.removeAttribute('name');
                inputCategoriaNueva.value = '';
                // Restaurar el name del select
                this.setAttribute('name', 'id_categoria');
            }
        });
    }
    
    // Validación del formulario de crear producto
    const formCrearProducto = document.getElementById('form_crear_producto');
    if (formCrearProducto) {
        const inputPrecio = document.getElementById('input_precio_actual');
        const errorPrecio = document.getElementById('error_precio');
        const errorNombreProducto = document.getElementById('error_nombre_producto');
        const inputDescripcion = document.getElementById('input_descripcion_producto');
        const errorDescripcion = document.getElementById('error_descripcion');
        
        // Validar precio en tiempo real
        if (inputPrecio) {
            inputPrecio.addEventListener('blur', function() {
                const validacion = validarPrecio(this.value);
                if (!validacion.valido) {
                    mostrarErrorCampo(this, errorPrecio, validacion.error);
                } else {
                    limpiarErrorCampo(this, errorPrecio);
                }
            });
            
            inputPrecio.addEventListener('input', function() {
                // Limpiar error mientras el usuario escribe
                if (this.classList.contains('is-invalid')) {
                    limpiarErrorCampo(this, errorPrecio);
                }
            });
        }
        
        // Validar nombre de producto nuevo en tiempo real
        if (inputNombreProductoNuevo) {
            inputNombreProductoNuevo.addEventListener('blur', function() {
                if (!this.classList.contains('d-none') && this.value.trim()) {
                    const validacion = validarNombreProducto(this.value);
                    if (!validacion.valido) {
                        mostrarErrorCampo(this, errorNombreProducto, validacion.error);
                    } else {
                        limpiarErrorCampo(this, errorNombreProducto);
                    }
                }
            });
            
            inputNombreProductoNuevo.addEventListener('input', function() {
                // Limpiar error mientras el usuario escribe
                if (this.classList.contains('is-invalid')) {
                    limpiarErrorCampo(this, errorNombreProducto);
                }
            });
        }
        
        // Validar descripción en tiempo real
        if (inputDescripcion) {
            inputDescripcion.addEventListener('blur', function() {
                if (this.value.trim()) {
                    const validacion = validarDescripcionProducto(this.value);
                    if (!validacion.valido) {
                        mostrarErrorCampo(this, errorDescripcion, validacion.error);
                    } else {
                        limpiarErrorCampo(this, errorDescripcion);
                    }
                } else {
                    // Si está vacío, es válido (campo opcional)
                    limpiarErrorCampo(this, errorDescripcion);
                }
            });
            
            inputDescripcion.addEventListener('input', function() {
                // Limpiar error mientras el usuario escribe
                if (this.classList.contains('is-invalid')) {
                    limpiarErrorCampo(this, errorDescripcion);
                }
            });
        }
        
        // Validar antes de enviar el formulario
        formCrearProducto.addEventListener('submit', function(e) {
            let hayErrores = false;
            
            // Validar precio
            if (inputPrecio) {
                const validacionPrecio = validarPrecio(inputPrecio.value);
                if (!validacionPrecio.valido) {
                    mostrarErrorCampo(inputPrecio, errorPrecio, validacionPrecio.error);
                    hayErrores = true;
                } else {
                    limpiarErrorCampo(inputPrecio, errorPrecio);
                }
            }
            
            // Validar nombre de producto si es nuevo
            if (selectNombreProducto && selectNombreProducto.value === '__NUEVO__') {
                if (inputNombreProductoNuevo && !inputNombreProductoNuevo.classList.contains('d-none')) {
                    const validacionNombre = validarNombreProducto(inputNombreProductoNuevo.value);
                    if (!validacionNombre.valido) {
                        mostrarErrorCampo(inputNombreProductoNuevo, errorNombreProducto, validacionNombre.error);
                        hayErrores = true;
                    } else {
                        limpiarErrorCampo(inputNombreProductoNuevo, errorNombreProducto);
                    }
                } else {
                    // Campo nuevo producto visible pero vacío
                    mostrarErrorCampo(inputNombreProductoNuevo, errorNombreProducto, 'El nombre del producto es obligatorio.');
                    hayErrores = true;
                }
            }
            
            // Validar descripción
            if (inputDescripcion) {
                const validacionDescripcion = validarDescripcionProducto(inputDescripcion.value);
                if (!validacionDescripcion.valido) {
                    mostrarErrorCampo(inputDescripcion, errorDescripcion, validacionDescripcion.error);
                    hayErrores = true;
                } else {
                    limpiarErrorCampo(inputDescripcion, errorDescripcion);
                }
            }
            
            // Prevenir envío si hay errores
            if (hayErrores) {
                e.preventDefault();
                scrollToFirstError(formCrearProducto);
                return false;
            }
        });
    }
    
    // ========================================================================
    // Activar pestaña según parámetro URL (solo en marketing.php)
    // ========================================================================
    if (window.location.pathname.includes('marketing.php')) {
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        
        if (tabParam) {
            const tabsValidos = ['productos', 'csv', 'agregar', 'fotos', 'metricas'];
            if (tabsValidos.includes(tabParam)) {
                // Activar pestaña usando Bootstrap
                const tabButton = document.getElementById(tabParam + '-tab');
                if (tabButton && typeof bootstrap !== 'undefined') {
                    const tab = new bootstrap.Tab(tabButton);
                    tab.show();
                }
            }
        }
    }
});

