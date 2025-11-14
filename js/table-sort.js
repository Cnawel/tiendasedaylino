/**
 * ========================================================================
 * ORDENAMIENTO DE TABLAS - Tienda Seda y Lino
 * ========================================================================
 * Funcionalidad simple de ordenamiento para tablas con clase 'sortable-table'
 * 
 * USO:
 * - Agregar clase 'sortable-table' a la tabla
 * - Agregar clase 'sortable' a los <th> que se quieran ordenar
 * - El script detecta automáticamente y agrega iconos de flecha
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Inicializar ordenamiento en todas las tablas sortable-table
 */
function initTableSort() {
    const tables = document.querySelectorAll('table.sortable-table');
    
    tables.forEach(function(table) {
        const headers = table.querySelectorAll('thead th.sortable');
        
        headers.forEach(function(header, index) {
            // Agregar estructura de icono si no existe
            if (!header.querySelector('.sort-arrow')) {
                const text = header.textContent.trim();
                header.innerHTML = '<span class="sortable-text">' + text + '</span> <span class="sort-arrow"><i class="fas fa-sort"></i></span>';
            }
            
            // Agregar evento click
            header.addEventListener('click', function() {
                sortTable(table, index, header);
            });
        });
    });
}

/**
 * Ordenar tabla por columna
 * @param {HTMLElement} table - Elemento tabla
 * @param {number} columnIndex - Índice de la columna
 * @param {HTMLElement} header - Elemento header clickeado
 */
function sortTable(table, columnIndex, header) {
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (rows.length === 0) return;
    
    // Determinar dirección de ordenamiento
    const isAscending = !header.classList.contains('sort-asc');
    
    // Remover clases de ordenamiento de todos los headers
    const allHeaders = table.querySelectorAll('thead th.sortable');
    allHeaders.forEach(function(h) {
        h.classList.remove('sort-asc', 'sort-desc');
    });
    
    // Agregar clase al header actual
    if (isAscending) {
        header.classList.add('sort-asc');
    } else {
        header.classList.add('sort-desc');
    }
    
    // Ordenar filas
    rows.sort(function(a, b) {
        const aCell = a.cells[columnIndex];
        const bCell = b.cells[columnIndex];
        
        if (!aCell || !bCell) return 0;
        
        const aText = getCellValue(aCell);
        const bText = getCellValue(bCell);
        
        // Detectar tipo de dato
        const aNum = parseFloat(aText);
        const bNum = parseFloat(bText);
        const isNumeric = !isNaN(aNum) && !isNaN(bNum) && aText.trim() !== '';
        
        if (isNumeric) {
            // Ordenamiento numérico
            return isAscending ? aNum - bNum : bNum - aNum;
        } else {
            // Ordenamiento de texto
            const aLower = aText.toLowerCase().trim();
            const bLower = bText.toLowerCase().trim();
            
            if (aLower < bLower) return isAscending ? -1 : 1;
            if (aLower > bLower) return isAscending ? 1 : -1;
            return 0;
        }
    });
    
    // Reordenar filas en el DOM
    rows.forEach(function(row) {
        tbody.appendChild(row);
    });
}

/**
 * Obtener valor de texto de una celda (ignorando HTML interno)
 * @param {HTMLElement} cell - Elemento td o th
 * @returns {string} Texto limpio de la celda
 */
function getCellValue(cell) {
    // Obtener texto directo, ignorando elementos hijos como badges, botones, etc.
    // Si hay un elemento con texto principal, usarlo; sino, usar todo el texto
    const strong = cell.querySelector('strong');
    if (strong) {
        return strong.textContent.trim();
    }
    
    // Si hay un badge con número, extraer el número
    const badge = cell.querySelector('.badge');
    if (badge && !isNaN(parseFloat(badge.textContent))) {
        return badge.textContent.trim();
    }
    
    // Usar todo el texto de la celda
    return cell.textContent.trim();
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTableSort);
} else {
    initTableSort();
}

