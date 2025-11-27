<?php
/**
 * ========================================================================
 * EJEMPLO DE ENDPOINT AJAX PROTEGIDO - Tienda Seda y Lino
 * ========================================================================
 * Este es un archivo de ejemplo que muestra cómo usar el middleware
 * de autenticación en endpoints AJAX.
 * 
 * NO SE USA EN PRODUCCIÓN - Solo ejemplo de referencia
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

// ============================================================================
// PASO 1: Incluir el middleware de autenticación
// ============================================================================
require_once __DIR__ . '/middleware_auth.php';

// ============================================================================
// PASO 2: Validar autenticación y rol requerido
// ============================================================================
// Opciones:
// - requireLoginAjax(): Solo requiere estar logueado
// - requireAdminAjax(): Requiere rol de administrador
// - requireRoleAjax('ventas'): Requiere rol específico (ventas, marketing, admin, cliente)
requireRoleAjax('ventas'); // Este endpoint requiere rol de ventas

// ============================================================================
// PASO 3 (Opcional): Validar método HTTP
// ============================================================================
requirePostAjax(); // Solo acepta POST
// O requireGetAjax(); para GET

// ============================================================================
// PASO 4 (Opcional): Validar que sea petición AJAX
// ============================================================================
requireAjaxRequest(); // Solo acepta peticiones AJAX

// ============================================================================
// PASO 5: Procesar la petición
// ============================================================================
try {
    // Obtener parámetros de forma segura
    $id_pedido = getPostParam('id_pedido', true); // Requerido
    $accion = getPostParam('accion', true); // Requerido
    $comentario = getPostParam('comentario', false, ''); // Opcional
    
    // Validar parámetros
    if (!is_numeric($id_pedido) || $id_pedido <= 0) {
        errorAjax('ID de pedido inválido', 400, ['id_pedido' => $id_pedido]);
    }
    
    // ========================================================================
    // Aquí va la lógica de negocio del endpoint
    // ========================================================================
    
    // Ejemplo: Aprobar un pedido (código de ejemplo)
    // $resultado = aprobarPedido($mysqli, $id_pedido);
    
    // ========================================================================
    // PASO 6: Retornar respuesta exitosa
    // ========================================================================
    exitoAjax([
        'id_pedido' => $id_pedido,
        'accion_realizada' => $accion,
        'timestamp' => date('Y-m-d H:i:s')
    ], 'Operación realizada con éxito');
    
} catch (Exception $e) {
    // Capturar excepciones y retornar error
    errorAjax(
        'Error al procesar la petición: ' . $e->getMessage(),
        500,
        ['exception' => get_class($e)]
    );
}


