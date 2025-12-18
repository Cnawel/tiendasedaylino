/**
 * ========================================================================
 * EDICIÓN INLINE DE DESCRIPCIÓN DE CATEGORÍAS - Marketing Panel
 * ========================================================================
 * Script para manejar la edición inline de descripciones de categorías
 * en la página de gestión de marketing.
 *
 * Funcionalidad:
 * - Mostrar/ocultar formulario de edición inline
 * - Validar descripción en cliente (antes de enviar al servidor)
 * - Mostrar contador de caracteres
 * - Enviar actualización por AJAX
 * - Actualizar tabla sin recargar la página
 * - Manejo de errores
 *
 * ========================================================================
 */

(function() {
    'use strict';

    // Esperar a que el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        inicializarEditoresCategorias();
    });

    /**
     * Inicializa todos los editores de descripción de categoría
     */
    function inicializarEditoresCategorias() {
        // Obtener todos los botones de editar
        const botonesEditar = document.querySelectorAll('.editar-descripcion-btn');

        botonesEditar.forEach(boton => {
            boton.addEventListener('click', function() {
                const idCategoria = this.getAttribute('data-id-categoria');
                mostrarEditorDescripcion(idCategoria);
            });
        });

        // Inicializar listeners para guardar y cancelar
        inicializarListenersGuardarCancelar();

        // Inicializar contador de caracteres
        inicializarContadorCaracteres();
    }

    /**
     * Muestra el editor inline para una categoría específica
     * @param {string} idCategoria - ID de la categoría
     */
    function mostrarEditorDescripcion(idCategoria) {
        // Obtener contenedores de la categoría
        const container = document.querySelector(`.categoria-descripcion-container[data-id-categoria="${idCategoria}"]`);
        const editor = document.querySelector(`.categoria-descripcion-editor[data-id-categoria="${idCategoria}"]`);

        if (!container || !editor) {
            console.error('No se encontró el contenedor o editor para categoría ' + idCategoria);
            return;
        }

        // Ocultar el texto y mostrar el editor
        container.classList.add('d-none');
        editor.classList.remove('d-none');

        // Enfocar el input
        const input = editor.querySelector('.descripcion-input');
        input.focus();
        input.select();

        // Actualizar contador de caracteres
        actualizarContador(input);
    }

    /**
     * Oculta el editor inline para una categoría específica
     * @param {string} idCategoria - ID de la categoría
     */
    function ocultarEditorDescripcion(idCategoria) {
        const container = document.querySelector(`.categoria-descripcion-container[data-id-categoria="${idCategoria}"]`);
        const editor = document.querySelector(`.categoria-descripcion-editor[data-id-categoria="${idCategoria}"]`);

        if (!container || !editor) {
            return;
        }

        container.classList.remove('d-none');
        editor.classList.add('d-none');
    }

    /**
     * Inicializa listeners para botones de guardar y cancelar
     */
    function inicializarListenersGuardarCancelar() {
        // Botones de guardar
        document.querySelectorAll('.guardar-descripcion-btn').forEach(boton => {
            boton.addEventListener('click', function() {
                const editor = this.closest('.categoria-descripcion-editor');
                const idCategoria = editor.getAttribute('data-id-categoria');
                const input = editor.querySelector('.descripcion-input');
                const descripcion = input.value;

                guardarDescripcionCategoria(idCategoria, descripcion);
            });
        });

        // Botones de cancelar
        document.querySelectorAll('.cancelar-descripcion-btn').forEach(boton => {
            boton.addEventListener('click', function() {
                const editor = this.closest('.categoria-descripcion-editor');
                const idCategoria = editor.getAttribute('data-id-categoria');
                ocultarEditorDescripcion(idCategoria);
            });
        });

        // Permitir guardar con Enter
        document.querySelectorAll('.descripcion-input').forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const editor = this.closest('.categoria-descripcion-editor');
                    const idCategoria = editor.getAttribute('data-id-categoria');
                    const descripcion = this.value;
                    guardarDescripcionCategoria(idCategoria, descripcion);
                }
            });

            // Permitir cancelar con Escape
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const editor = this.closest('.categoria-descripcion-editor');
                    const idCategoria = editor.getAttribute('data-id-categoria');
                    ocultarEditorDescripcion(idCategoria);
                }
            });
        });
    }

    /**
     * Inicializa el contador de caracteres para todos los inputs
     */
    function inicializarContadorCaracteres() {
        document.querySelectorAll('.descripcion-input').forEach(input => {
            input.addEventListener('input', function() {
                actualizarContador(this);
            });
        });
    }

    /**
     * Actualiza el contador de caracteres de un input
     * @param {HTMLElement} input - Elemento input
     */
    function actualizarContador(input) {
        const editor = input.closest('.categoria-descripcion-editor');
        const contador = editor.querySelector('.contador-caracteres');

        if (!contador) return;

        const longitud = input.value.length;
        const maximo = 255;
        const restantes = maximo - longitud;

        contador.textContent = `${longitud}/${maximo} caracteres`;

        // Cambiar color si se acerca al máximo
        if (restantes < 50) {
            contador.style.color = '#ff9800'; // Naranja
        } else if (restantes < 20) {
            contador.style.color = '#f44336'; // Rojo
        } else {
            contador.style.color = '#666';
        }
    }

    /**
     * Valida la descripción en cliente antes de enviar
     * @param {string} descripcion - Descripción a validar
     * @returns {object} {valido: boolean, error: string}
     */
    function validarDescripcionCliente(descripcion) {
        // Validación 1: Longitud máxima
        if (descripcion.length > 255) {
            return {
                valido: false,
                error: 'La descripción no puede exceder 255 caracteres.'
            };
        }

        // Validación 2: Caracteres permitidos (según diccionario de datos)
        // Permitir solo: A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, 0-9, espacios, ., -, ,, :, ;
        // También permitir campo vacío (opcional)
        if (descripcion.length > 0 && !/^[A-Za-záéíóúÁÉÍÓÚñÑüÜ0-9\s\.\,\-\:\;]*$/.test(descripcion)) {
            return {
                valido: false,
                error: 'La descripción contiene caracteres no permitidos. Solo se permiten letras, números, espacios y los símbolos: . , - : ;'
            };
        }

        // Validación 3: Caracteres bloqueados explícitamente (< > { } [ ] | \ / &)
        // Esta validación es adicional para mayor seguridad
        if (/[<>{}[\]|\\\/&]/.test(descripcion)) {
            return {
                valido: false,
                error: 'La descripción contiene caracteres bloqueados: < > { } [ ] | \\ / &'
            };
        }

        return {
            valido: true,
            error: ''
        };
    }

    /**
     * Guarda la descripción de una categoría mediante AJAX
     * @param {string} idCategoria - ID de la categoría
     * @param {string} descripcion - Nueva descripción
     */
    function guardarDescripcionCategoria(idCategoria, descripcion) {
        // Validación cliente
        const validacion = validarDescripcionCliente(descripcion);
        if (!validacion.valido) {
            alert(validacion.error);
            return;
        }

        // Obtener el editor para mostrar estado
        const editor = document.querySelector(`.categoria-descripcion-editor[data-id-categoria="${idCategoria}"]`);
        const botonesAccion = editor.querySelectorAll('.guardar-descripcion-btn, .cancelar-descripcion-btn');

        // Deshabilitar botones durante el envío
        botonesAccion.forEach(boton => {
            boton.disabled = true;
        });

        // Preparar datos para enviar
        const datos = {
            id_categoria: parseInt(idCategoria),
            descripcion: descripcion.trim()
        };

        // Realizar petición AJAX
        fetch('actualizar_descripcion_categoria.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(datos)
        })
            .then(response => response.json())
            .then(data => {
                if (data.exito) {
                    // Éxito: actualizar la tabla
                    const textoSpan = document.querySelector(`.categoria-descripcion-container[data-id-categoria="${idCategoria}"] .categoria-descripcion-text`);
                    textoSpan.innerHTML = data.html_descripcion;

                    // Ocultar editor y mostrar texto
                    ocultarEditorDescripcion(idCategoria);

                    // Mostrar mensaje de éxito (opcional, usando toast o alert)
                    console.log('✓ Descripción actualizada correctamente');
                } else {
                    // Error del servidor
                    alert('Error: ' + data.mensaje);
                    console.error('Error en la respuesta del servidor:', data);
                }
            })
            .catch(error => {
                // Error de conectividad
                console.error('Error en la petición AJAX:', error);
                alert('Error de conectividad. Por favor, intenta de nuevo.');
            })
            .finally(() => {
                // Re-habilitar botones
                botonesAccion.forEach(boton => {
                    boton.disabled = false;
                });
            });
    }

})();
