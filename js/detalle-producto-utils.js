/**
 * ========================================================================
 * UTILIDADES DE DETALLE DE PRODUCTO - Tienda Seda y Lino
 * ========================================================================
 * Funciones reutilizables para manejo de stock, imágenes, tallas y colores
 *
 * FUNCIONES:
 * - normalizeColor(): Normaliza formato de color
 * - getStock(): Obtiene stock para combinación talle-color
 * - setButtonsState(): Cambia estado de botones
 * - updateStockMessage(): Actualiza mensaje de stock
 * - checkStockForColor(): Verifica stock para un color
 * - checkStockForCombination(): Verifica stock para talle-color
 * - hasStockInAnyTalle(): Verifica si hay stock en algún talle
 *
 * USO:
 *   <script src="js/detalle-producto-utils.js"></script>
 *
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Normaliza un color al formato: Primera Letra Mayúscula
 * Sincronizado con normalizeColor() de PHP
 *
 * @param {string} color - Color a normalizar
 * @returns {string} Color normalizado
 */
function normalizeColor(color) {
    if (!color) return '';
    return color.charAt(0).toUpperCase() + color.slice(1).toLowerCase();
}

/**
 * Obtiene stock para una combinación de talle y color
 * Intenta búsqueda indexada primero, luego jerárquica
 *
 * @param {string} talla - Talla seleccionada
 * @param {string} color - Color seleccionado
 * @param {Object} stockVariantes - Array indexado talle-color => stock
 * @param {Object} stockPorTalleColor - Array jerárquico [talle][color] => stock
 * @returns {number} Stock disponible (0 si no hay)
 */
function getStock(talla, color, stockVariantes = {}, stockPorTalleColor = {}) {
    const colorNormalizado = normalizeColor(color);
    const clave = talla + '-' + colorNormalizado;

    // Intenta búsqueda indexada primero (más rápida)
    if (stockVariantes && stockVariantes[clave] !== undefined) {
        return parseInt(stockVariantes[clave]) || 0;
    }

    // Fallback a búsqueda jerárquica
    if (stockPorTalleColor && stockPorTalleColor[talla] &&
        stockPorTalleColor[talla][colorNormalizado] !== undefined) {
        return parseInt(stockPorTalleColor[talla][colorNormalizado]) || 0;
    }

    return 0;
}

/**
 * Cambia el estado de múltiples botones
 * Deshabilitado: opacity 0.6, cursor not-allowed
 * Habilitado: opacity 1, cursor pointer
 *
 * @param {HTMLElement[]} buttons - Array de elementos button
 * @param {boolean} enabled - true para habilitar, false para deshabilitar
 */
function setButtonsState(buttons, enabled) {
    if (!Array.isArray(buttons)) {
        buttons = [buttons];
    }

    buttons.forEach(btn => {
        btn.disabled = !enabled;
        btn.style.opacity = enabled ? '1' : '0.6';
        btn.style.cursor = enabled ? 'pointer' : 'not-allowed';
    });
}

/**
 * Configuración de mensajes de stock
 * Centraliza textos, iconos y estilos
 */
const STOCK_MESSAGES = {
    available: {
        getText: (stock) => `${stock} unidades disponibles`,
        icon: 'fas fa-check-circle me-1',
        className: 'text-success',
        borderLeftColor: '#4A9FD6',
        background: '#E3F2F8'
    },
    unavailable: {
        getText: () => 'Sin stock disponible',
        icon: 'fas fa-exclamation-triangle me-1',
        className: 'text-warning',
        borderLeftColor: '#8B8B7A',
        background: '#F5F5F0'
    },
    selectColor: {
        getText: () => 'Selecciona un color',
        icon: 'fas fa-info-circle me-1',
        className: 'text-warning',
        borderLeftColor: '#ffc107',
        background: '#fff3cd'
    },
    selectTalle: {
        getText: () => 'Selecciona un talle',
        icon: 'fas fa-info-circle me-1',
        className: 'text-warning',
        borderLeftColor: '#ffc107',
        background: '#fff3cd'
    },
    selectBoth: {
        getText: () => 'Selecciona talle y color',
        icon: 'fas fa-info-circle me-1',
        className: 'text-warning',
        borderLeftColor: '#ffc107',
        background: '#fff3cd'
    }
};

/**
 * Actualiza el mensaje de stock en la UI
 *
 * @param {string} state - Clave de estado (available, unavailable, selectColor, etc)
 * @param {HTMLElement} stockEl - Elemento de texto de stock
 * @param {HTMLElement} stockContainer - Elemento contenedor del stock
 * @param {HTMLElement} stockIcon - Elemento de ícono
 * @param {Object} extraData - Datos adicionales (ej: {stock: 5})
 */
function updateStockMessage(state, stockEl, stockContainer, stockIcon, extraData = {}) {
    if (!state || !STOCK_MESSAGES[state]) {
        console.warn('Estado de stock inválido:', state);
        return;
    }

    const config = STOCK_MESSAGES[state];

    // Actualizar texto
    if (stockEl) {
        const text = typeof config.getText === 'function'
            ? config.getText(extraData.stock)
            : config.getText;
        stockEl.textContent = text;
        stockEl.className = config.className;
    }

    // Actualizar contenedor
    if (stockContainer) {
        stockContainer.style.borderLeftColor = config.borderLeftColor;
        stockContainer.style.background = config.background;
    }

    // Actualizar ícono
    if (stockIcon) {
        stockIcon.className = config.icon;
    }
}

/**
 * Verifica si hay stock para un color considerando talle seleccionado
 *
 * @param {string} colorValue - Valor del color
 * @param {HTMLElement} tallaActual - Input de talle seleccionado (puede ser null)
 * @param {Object} stockPorTalleColor - Array [talle][color] => stock
 * @returns {boolean} true si hay stock, false si no
 */
function checkStockForColor(colorValue, tallaActual, stockPorTalleColor) {
    if (tallaActual) {
        const talleValue = tallaActual.getAttribute('data-talle');
        return checkStockForCombination(talleValue, colorValue, stockPorTalleColor);
    }
    return hasStockInAnyTalle(colorValue, stockPorTalleColor);
}

/**
 * Verifica stock para una combinación específica de talle-color
 *
 * @param {string} talle - Talle a verificar
 * @param {string} color - Color a verificar
 * @param {Object} stockPorTalleColor - Array [talle][color] => stock
 * @returns {boolean} true si hay stock > 0
 */
function checkStockForCombination(talle, color, stockPorTalleColor) {
    const colorNormalizado = normalizeColor(color);
    return (stockPorTalleColor[talle] &&
            stockPorTalleColor[talle][colorNormalizado] &&
            stockPorTalleColor[talle][colorNormalizado] > 0) || false;
}

/**
 * Verifica si hay stock para un color en algún talle
 *
 * @param {string} color - Color a verificar
 * @param {Object} stockPorTalleColor - Array [talle][color] => stock
 * @returns {boolean} true si hay stock en al menos un talle
 */
function hasStockInAnyTalle(color, stockPorTalleColor) {
    const colorNormalizado = normalizeColor(color);

    for (const talle in stockPorTalleColor) {
        if (stockPorTalleColor[talle] &&
            stockPorTalleColor[talle][colorNormalizado] &&
            stockPorTalleColor[talle][colorNormalizado] > 0) {
            return true;
        }
    }

    return false;
}

/**
 * Crea/obtiene un namespace global para funciones del detalle de producto
 * Previene contaminación global del namespace
 *
 * USO:
 *   const detalleProducto = window.detalleProducto || {};
 *   detalleProducto.someFunction();
 */
if (typeof window.detalleProducto === 'undefined') {
    window.detalleProducto = {
        // Funciones exportadas
        normalizeColor,
        getStock,
        setButtonsState,
        updateStockMessage,
        checkStockForColor,
        checkStockForCombination,
        hasStockInAnyTalle,
        STOCK_MESSAGES
    };
}
