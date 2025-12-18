<?php
/**
 * ========================================================================
 * ACTUALIZAR DESCRIPCIÓN DE CATEGORÍA - AJAX Endpoint
 * ========================================================================
 * Endpoint AJAX para actualizar la descripción de una categoría
 * desde la edición inline en la página de marketing
 *
 * Métodos HTTP soportados: POST
 * Parámetros esperados:
 * - id_categoria (int) - ID de la categoría a actualizar
 * - descripcion (string) - Nueva descripción (0-255 caracteres)
 *
 * Respuestas JSON:
 * {
 *   "exito": true|false,
 *   "mensaje": "Descripción actualizada correctamente"|"Error...",
 *   "descripcion": "Nueva descripción",
 *   "html_descripcion": "Descripción escapada para HTML"
 * }
 * ========================================================================
 */

session_start();

// Verificación de acceso: solo usuarios con rol 'marketing' pueden actualizar categorías
require_once __DIR__ . '/includes/auth_check.php';
requireRole('marketing');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validation_functions.php';
require_once __DIR__ . '/includes/queries/categoria_queries.php';

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'exito' => false,
        'mensaje' => 'Método no permitido. Use POST.'
    ]);
    exit;
}

// Obtener parámetros JSON del body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'exito' => false,
        'mensaje' => 'Datos JSON inválidos'
    ]);
    exit;
}

// Obtener y sanitizar parámetros
$id_categoria = isset($data['id_categoria']) ? (int)$data['id_categoria'] : 0;
$descripcion = isset($data['descripcion']) ? (string)$data['descripcion'] : '';

// VALIDACIÓN 1: ID de categoría válido
if ($id_categoria <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'exito' => false,
        'mensaje' => 'ID de categoría inválido'
    ]);
    exit;
}

// VALIDACIÓN 2: Validar descripción usando la función centralizada
$validacion = validarDescripcionCategoria($descripcion, true); // true = opcional
if (!$validacion['valido']) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'exito' => false,
        'mensaje' => $validacion['error']
    ]);
    exit;
}

// VALIDACIÓN 3: Verificar que la categoría existe
$categorias = obtenerCategorias($mysqli);
$categoria_existe = false;
foreach ($categorias as $cat) {
    if ((int)$cat['id_categoria'] === $id_categoria) {
        $categoria_existe = true;
        break;
    }
}

if (!$categoria_existe) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'exito' => false,
        'mensaje' => 'La categoría no existe'
    ]);
    exit;
}

// ACTUALIZAR descripción en la base de datos
$resultado_actualizacion = actualizarDescripcionCategoria($mysqli, $id_categoria, $validacion['valor']);

if (!$resultado_actualizacion) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'exito' => false,
        'mensaje' => 'Error al actualizar la descripción en la base de datos'
    ]);
    exit;
}

// ÉXITO: Retornar respuesta JSON
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');

$descripcion_final = $validacion['valor'];
$html_escapado = htmlspecialchars($descripcion_final, ENT_QUOTES, 'UTF-8');
$html_mostrar = !empty($html_escapado) ? $html_escapado : '<em class="text-secondary">Sin descripción</em>';

echo json_encode([
    'exito' => true,
    'mensaje' => 'Descripción actualizada correctamente',
    'descripcion' => $descripcion_final,
    'html_descripcion' => $html_mostrar
]);

exit;
