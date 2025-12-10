<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

$id_pedido = 20; // Cambiar según tu pedido

$sql = "SELECT * FROM Pedidos WHERE id_pedido = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $id_pedido);
$stmt->execute();
$result = $stmt->get_result();
$pedido = $result->fetch_assoc();

echo "<pre>";
echo "PEDIDO #$id_pedido:\n\n";
print_r($pedido);
echo "</pre>";

echo "<h3>Estado del pedido:</h3>";
echo "<p>Valor: '" . ($pedido['estado_pedido'] ?? 'NULL') . "'</p>";
echo "<p>Longitud: " . strlen($pedido['estado_pedido'] ?? '') . "</p>";
echo "<p>Es vacío: " . (empty($pedido['estado_pedido']) ? 'SÍ' : 'NO') . "</p>";

// Arreglar si está vacío
if (empty($pedido['estado_pedido'])) {
    echo "<hr>";
    echo "<h3>¿Quieres arreglar este pedido?</h3>";
    echo "<p>El pedido tiene estado vacío. Deberíamos cambiarlo a 'pendiente'.</p>";

    if (isset($_GET['arreglar'])) {
        $sql_update = "UPDATE Pedidos SET estado_pedido = 'pendiente' WHERE id_pedido = ?";
        $stmt_update = $mysqli->prepare($sql_update);
        $stmt_update->bind_param('i', $id_pedido);
        $stmt_update->execute();

        echo "<p style='color: green;'><strong>✓ Pedido actualizado a 'pendiente'</strong></p>";
        echo "<p><a href='verificar_pedido.php'>Ver resultado</a></p>";
    } else {
        echo "<p><a href='?arreglar=1' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Arreglar Ahora</a></p>";
    }
}
?>
