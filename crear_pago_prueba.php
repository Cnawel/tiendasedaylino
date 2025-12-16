<?php
/**
 * Crea un pago de prueba con productos ACTIVOS
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/queries_helper.php';
require_once __DIR__ . '/includes/estado_helpers.php';
require_once __DIR__ . '/includes/state_functions.php';

try {
    cargarArchivoQueries('pago_queries', __DIR__ . '/includes/queries');
    cargarArchivoQueries('pedido_queries', __DIR__ . '/includes/queries');
} catch (Exception $e) {
    die("ERROR: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Crear Pago de Prueba</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .info { color: #569cd6; }
        pre { background: #2d2d2d; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
<h1 class="info">🔧 Crear Pago de Prueba</h1>

<?php

echo "<h2>PASO 1: Buscar variante ACTIVA con stock</h2>";

$sql = "SELECT sv.id_variante, sv.id_producto, sv.talle, sv.color, sv.stock, p.nombre_producto, p.precio_actual
        FROM Stock_Variantes sv
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        WHERE sv.activo = 1 AND sv.stock > 0 AND p.activo = 1
        LIMIT 1";

$result = $mysqli->query($sql);

if (!$result || $result->num_rows === 0) {
    echo "<p class='error'>❌ No hay variantes activas con stock disponible</p>";
    echo "<p class='info'>Necesitas activar al menos un producto y su variante en la base de datos.</p>";
    exit;
}

$variante = $result->fetch_assoc();

echo "<pre>";
echo "<span class='success'>✓ Variante encontrada:</span>\n";
echo "ID Variante: {$variante['id_variante']}\n";
echo "Producto: {$variante['nombre_producto']}\n";
echo "Talle: {$variante['talle']}\n";
echo "Color: {$variante['color']}\n";
echo "Stock: {$variante['stock']}\n";
echo "Precio: \${$variante['precio_actual']}\n";
echo "</pre>";

echo "<h2>PASO 2: Crear pedido</h2>";

$sql_pedido = "INSERT INTO Pedidos (id_usuario, estado_pedido, total, fecha_pedido)
               VALUES (1, 'pendiente', ?, NOW())";
$stmt = $mysqli->prepare($sql_pedido);
$total = $variante['precio_actual'];
$stmt->bind_param('d', $total);
$stmt->execute();
$id_pedido = $mysqli->insert_id;

echo "<p class='success'>✓ Pedido creado: ID <strong>$id_pedido</strong></p>";

echo "<h2>PASO 3: Crear detalle del pedido</h2>";

$sql_detalle = "INSERT INTO Detalle_Pedido (id_pedido, id_variante, cantidad, precio_unitario)
                VALUES (?, ?, 1, ?)";
$stmt_detalle = $mysqli->prepare($sql_detalle);
$stmt_detalle->bind_param('iid', $id_pedido, $variante['id_variante'], $variante['precio_actual']);
$stmt_detalle->execute();

echo "<p class='success'>✓ Detalle creado: 1x {$variante['nombre_producto']} ({$variante['talle']} {$variante['color']})</p>";

echo "<h2>PASO 4: Crear pago en 'pendiente_aprobacion'</h2>";

try {
    $id_pago = crearPago($mysqli, $id_pedido, 1, 'pendiente_aprobacion', $total);

    echo "<p class='success'>✓ Pago creado: ID <strong>$id_pago</strong></p>";

    echo "<h2>✅ LISTO</h2>";
    echo "<pre>";
    echo "<span class='success'>Pago de prueba creado exitosamente:</span>\n\n";
    echo "ID Pago: <strong>$id_pago</strong>\n";
    echo "ID Pedido: <strong>$id_pedido</strong>\n";
    echo "Estado: <strong>pendiente_aprobacion</strong>\n";
    echo "Monto: <strong>\$$total</strong>\n";
    echo "Producto: <strong>{$variante['nombre_producto']}</strong> ({$variante['talle']} {$variante['color']})\n";
    echo "Stock disponible: <strong>{$variante['stock']}</strong>\n";
    echo "</pre>";

    echo "<p class='info'>Ahora puedes aprobar este pago desde:</p>";
    echo "<ul>";
    echo "<li><a href='debug_aprobar_pago.php' style='color: #569cd6;'>debug_aprobar_pago.php</a></li>";
    echo "<li><a href='ventas.php' style='color: #569cd6;'>Panel de Ventas</a></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p class='error'>❌ Error al crear pago: " . htmlspecialchars($e->getMessage()) . "</p>";
}

?>

</body>
</html>
