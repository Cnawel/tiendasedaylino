/**
 * ========================================================================
 * EDICIÓN DE PRODUCTO - MARKETING - Tienda Seda y Lino
 * ========================================================================
 * Funciones JavaScript para la edición de productos en el panel de marketing
 * 
 * FUNCIONES:
 * - agregarVariante(): Agrega una nueva variante (talle + color + stock)
 * - marcarEliminar(): Marca una variante para eliminar
 * - prepararEnvioFormulario(): Prepara el formulario antes de enviar
 * 
 * VARIABLES GLOBALES (deben ser definidas antes de cargar este script):
 * - tallesDisponibles: Array de talles disponibles
 * - coloresDisponibles: Array de colores disponibles
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

let contadorVariantes = 0;

/**
 * NOTA: La lógica para mostrar/ocultar inputs de nuevo nombre de producto y categoría
 * está consolidada en marketing_forms.js. Asegúrate de incluir marketing_forms.js
 * antes de este archivo para que la funcionalidad esté disponible.
 */

/**
 * Agregar una nueva variante (talle + color + stock)
 */
function agregarVariante() {
    // Obtener variables globales (definidas en el script inline antes de este archivo)
    const tallesDisponibles = window.tallesDisponibles || [];
    const coloresDisponibles = window.coloresDisponibles || [];
    
    const container = document.getElementById('variantesContainer');
    const index = contadorVariantes;
    
    const varianteDiv = document.createElement('div');
    varianteDiv.className = 'row mb-2 g-2';
    varianteDiv.innerHTML = `
        <div class="col-md-4">
            <select class="form-select form-select-sm" name="nuevas_variantes[${index}][talle]" required>
                <option value="">Seleccionar talle...</option>
                ${tallesDisponibles.map(t => `<option value="${t}">${t}</option>`).join('')}
            </select>
        </div>
        <div class="col-md-4">
            <select class="form-select form-select-sm" name="nuevas_variantes[${index}][color]" required>
                <option value="">Seleccionar color...</option>
                ${coloresDisponibles.map(c => `<option value="${c}">${c}</option>`).join('')}
            </select>
        </div>
        <div class="col-md-3">
            <input type="number" min="0" class="form-control form-control-sm" name="nuevas_variantes[${index}][stock]" value="0" placeholder="Stock" required>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-variante">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    container.appendChild(varianteDiv);
    
    // Agregar event listener al botón de eliminar usando addEventListener en lugar de onclick
    const btnEliminar = varianteDiv.querySelector('.btn-eliminar-variante');
    if (btnEliminar) {
        btnEliminar.addEventListener('click', function() {
            varianteDiv.remove();
        });
    }
    
    contadorVariantes++;
}

/**
 * Marcar una variante para eliminar
 * @param {number} idVariante - ID de la variante a marcar/desmarcar
 */
function marcarEliminar(idVariante) {
    const inputEliminar = document.getElementById('eliminar_' + idVariante);
    const fila = document.getElementById('fila_' + idVariante);
    
    if (!fila || !inputEliminar) return;
    
    if (inputEliminar.value === '' || inputEliminar.value === '0') {
        // Marcar para eliminar
        inputEliminar.value = idVariante;
        fila.style.opacity = '0.5';
        fila.style.backgroundColor = '#ffebee';
        fila.style.textDecoration = 'line-through';
        
        // Deshabilitar campos de edición cuando está marcada para eliminar
        const inputs = fila.querySelectorAll('input[type="number"], select');
        inputs.forEach(input => {
            input.disabled = true;
            input.required = false;
        });
    } else {
        // Desmarcar
        inputEliminar.value = '';
        fila.style.opacity = '1';
        fila.style.backgroundColor = '';
        fila.style.textDecoration = '';
        
        // Rehabilitar campos de edición
        const inputs = fila.querySelectorAll('input[type="number"], select');
        inputs.forEach(input => {
            input.disabled = false;
            input.required = true;
        });
    }
}

/**
 * Preparar formulario antes de enviar - limpiar inputs vacíos de eliminación
 * @returns {boolean} - true para permitir envío del formulario
 */
function prepararEnvioFormulario() {
    // Remover todos los inputs hidden de eliminación que están vacíos
    const inputsEliminar = document.querySelectorAll('input[name="variantes_eliminar[]"]');
    inputsEliminar.forEach(input => {
        if (input.value === '' || input.value === '0') {
            input.remove();
        }
    });
    
    // Remover campos deshabilitados de las filas marcadas para eliminar (para evitar conflictos)
    const filasMarcadas = document.querySelectorAll('tr[id^="fila_"]');
    filasMarcadas.forEach(fila => {
        const inputEliminarFila = fila.querySelector('input[name="variantes_eliminar[]"]');
        if (inputEliminarFila && inputEliminarFila.value !== '' && inputEliminarFila.value !== '0') {
            // Esta fila está marcada para eliminar, remover sus campos de actualización
            const inputsActualizar = fila.querySelectorAll('input[name^="variantes_actualizar"], select[name^="variantes_actualizar"]');
            inputsActualizar.forEach(input => {
                input.remove();
            });
        }
    });
    
    return true; // Permitir envío del formulario
}

