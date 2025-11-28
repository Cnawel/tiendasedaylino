/**
 * ========================================================================
 * NAVEGACIÓN DE IMÁGENES POR CLIC - Tienda Seda y Lino
 * ========================================================================
 * Permite navegar entre imágenes del producto haciendo clic en la imagen principal
 * - Clic en la mitad izquierda: imagen anterior
 * - Clic en la mitad derecha: imagen siguiente
 * 
 * REQUIERE:
 * - Función cambiarImagenPrincipal(index) disponible globalmente
 * - window.imagenesProducto con array de imágenes
 * - Elemento #imagenPrincipalLimpia presente en el DOM
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * Inicializa la navegación de imágenes por clic
 * Se ejecuta cuando el DOM está listo
 */
document.addEventListener('DOMContentLoaded', function() {
    const imagenPrincipal = document.getElementById('imagenPrincipalLimpia');
    
    if (!imagenPrincipal) {
        return; // No hay imagen principal, salir sin errores
    }
    
    // Verificar que haya imágenes disponibles
    const imagenes = window.imagenesProducto || [];
    if (!imagenes || imagenes.length <= 1) {
        return; // No hay suficientes imágenes para navegar
    }
    
    /**
     * Obtiene el índice de la imagen actual
     * Usa la variable global window.indiceImagenActual si está disponible
     * @returns {number} Índice de la imagen actual o 0 si no se encuentra
     */
    function obtenerIndiceImagenActual() {
        // Usar variable global si está disponible (actualizada por cambiarImagenPrincipal)
        if (window.indiceImagenActual !== undefined && window.indiceImagenActual >= 0 && window.indiceImagenActual < imagenes.length) {
            return window.indiceImagenActual;
        }
        
        // Fallback: buscar por URL de la imagen actual
        const srcActual = imagenPrincipal.src;
        const baseUrl = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
        const rutaRelativa = srcActual.replace(baseUrl, '');
        
        // Buscar el índice de la imagen actual en el array
        for (let i = 0; i < imagenes.length; i++) {
            if (rutaRelativa === imagenes[i] || srcActual.endsWith(imagenes[i])) {
                return i;
            }
        }
        
        // Si no se encuentra, buscar por nombre de archivo
        const nombreArchivo = rutaRelativa.split('/').pop();
        for (let i = 0; i < imagenes.length; i++) {
            const nombreImagen = imagenes[i].split('/').pop();
            if (nombreArchivo === nombreImagen) {
                return i;
            }
        }
        
        return 0; // Por defecto, primera imagen
    }
    
    /**
     * Maneja el clic en la imagen principal para navegar entre imágenes
     * @param {MouseEvent} event - Evento de clic
     */
    imagenPrincipal.addEventListener('click', function(event) {
        // Solo procesar si hay más de una imagen
        if (imagenes.length <= 1) {
            return;
        }
        
        // Obtener posición del clic relativa a la imagen
        const rect = imagenPrincipal.getBoundingClientRect();
        const clickX = event.clientX - rect.left;
        const mitadAncho = rect.width / 2;
        
        // Obtener índice de la imagen actual
        const indiceActual = obtenerIndiceImagenActual();
        let nuevoIndice;
        
        // Determinar si el clic fue en la izquierda (anterior) o derecha (siguiente)
        if (clickX < mitadAncho) {
            // Clic en la mitad izquierda: imagen anterior
            nuevoIndice = indiceActual > 0 ? indiceActual - 1 : imagenes.length - 1;
        } else {
            // Clic en la mitad derecha: imagen siguiente
            nuevoIndice = indiceActual < imagenes.length - 1 ? indiceActual + 1 : 0;
        }
        
        // Cambiar a la nueva imagen usando la función existente
        if (typeof cambiarImagenPrincipal === 'function') {
            cambiarImagenPrincipal(nuevoIndice);
        }
    });
    
    // Agregar cursor pointer para indicar que es clickeable
    imagenPrincipal.style.cursor = 'pointer';
    
    // Agregar título para indicar funcionalidad
    imagenPrincipal.title = 'Clic izquierdo: imagen anterior | Clic derecho: imagen siguiente';
});

