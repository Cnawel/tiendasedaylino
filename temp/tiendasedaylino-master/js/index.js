/**
 * ========================================================================
 * INDEX - JavaScript para página principal
 * ========================================================================
 * Funciones JavaScript para la página de inicio
 * ========================================================================
 */

/**
 * Resetear formulario después de envío exitoso
 * Solo se ejecuta si window.resetearFormulario está definido como true
 */
document.addEventListener('DOMContentLoaded', function() {
    // Solo ejecutar si la variable global está definida
    if (window.resetearFormulario === true) {
        const formulario = document.querySelector('form.formulario');
        if (formulario) {
            // Resetear el formulario
            formulario.reset();
            
            // Limpiar cualquier valor que pueda haber quedado en los campos
            const campos = formulario.querySelectorAll('input, textarea, select');
            campos.forEach(function(campo) {
                // Solo limpiar si no viene de sesión de usuario (nombre y email pueden venir de sesión)
                if (campo.id !== 'name' && campo.id !== 'email') {
                    campo.value = '';
                }
            });
        }
    }
});

